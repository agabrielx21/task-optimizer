<?php

declare(strict_types=1);

namespace App\Optimizer;

/**
 * Exact search for a schedule with makespan strictly below a known upper bound.
 *
 * Works on plain integer arrays (indexes, not objects) because it is the hot loop.
 * Tasks are decided one by one in the given order (LPT order: big, constrained tasks first, so bad
 * branches fail early). A branch is cut when:
 *   - putting the task on an employee reaches the best makespan found so far (we only want better);
 *   - symmetry: two eligible employees with the same skill and the same current load are
 *     interchangeable for the rest of the search, so only the first one is tried;
 *   - the nested lower bound, applied to the remaining work, shows the best cannot be beaten:
 *     ceil((current hours of employees with skill >= k + unassigned hours needing skill >= k) / N_k);
 *   - the node budget is used up (then the answer is the best found, flagged as not proven optimal).
 *
 * The budget counts nodes, not seconds: a wall-clock limit would make the output depend on machine
 * speed, which breaks the "identical input -> identical output" requirement.
 */
final class BranchAndBound
{
    /** @var list<int> */
    private array $durations;
    /** @var list<list<int>> eligible employee indexes per task position */
    private array $eligible;
    /** @var list<int> */
    private array $skills;
    /** @var list<list<int>> level indexes each employee counts towards */
    private array $employeeLevels;
    /** @var list<int> */
    private array $levelEmployeeCount;
    /** @var list<list<int>> [level][position] = hours of tasks at positions >= position needing that level */
    private array $remaining;

    /** @var list<int> */
    private array $loads;
    /** @var list<int> */
    private array $levelLoads;
    /** @var list<int> */
    private array $current;
    /** @var list<int> */
    private array $best;
    private int $bestMakespan;
    private int $lowerBound;
    private int $nodes = 0;
    private bool $aborted = false;

    public function __construct(private readonly int $nodeLimit)
    {
    }

    /**
     * @param list<int>       $durations       per task position
     * @param list<int>       $taskSkills      per task position
     * @param list<int>       $employeeSkills  per employee index
     * @param list<int>       $initial         employee index per task position (the heuristic solution)
     *
     * @return array{assignment: list<int>, makespan: int, complete: bool, nodes: int}
     *         complete = the whole tree was explored (or the lower bound was reached) -> result is optimal
     */
    public function search(array $durations, array $taskSkills, array $employeeSkills, array $initial, int $upperBound, int $lowerBound): array
    {
        $taskCount = count($durations);
        $employeeCount = count($employeeSkills);

        $this->durations = $durations;
        $this->skills = $employeeSkills;
        $this->best = $initial;
        $this->bestMakespan = $upperBound;
        $this->lowerBound = $lowerBound;
        $this->nodes = 0;
        $this->aborted = false;

        $this->eligible = [];
        foreach ($taskSkills as $position => $skill) {
            $list = [];
            for ($e = 0; $e < $employeeCount; ++$e) {
                if ($employeeSkills[$e] >= $skill) {
                    $list[] = $e;
                }
            }
            $this->eligible[$position] = $list;
        }

        // Thresholds = distinct task skill levels (same reasoning as LowerBound).
        $levels = array_values(array_unique($taskSkills));
        sort($levels);
        $this->employeeLevels = array_fill(0, $employeeCount, []);
        $this->levelEmployeeCount = [];
        $this->remaining = [];
        foreach ($levels as $li => $level) {
            $count = 0;
            for ($e = 0; $e < $employeeCount; ++$e) {
                if ($employeeSkills[$e] >= $level) {
                    $this->employeeLevels[$e][] = $li;
                    ++$count;
                }
            }
            $this->levelEmployeeCount[$li] = $count;

            $suffix = array_fill(0, $taskCount + 1, 0);
            for ($p = $taskCount - 1; $p >= 0; --$p) {
                $suffix[$p] = $suffix[$p + 1] + ($taskSkills[$p] >= $level ? $durations[$p] : 0);
            }
            $this->remaining[$li] = $suffix;
        }

        $this->loads = array_fill(0, $employeeCount, 0);
        $this->levelLoads = array_fill(0, count($levels), 0);
        $this->current = array_fill(0, $taskCount, -1);

        if ($upperBound > $lowerBound) {
            $this->branch(0);
        }

        return [
            'assignment' => $this->best,
            'makespan' => $this->bestMakespan,
            'complete' => !$this->aborted,
            'nodes' => $this->nodes,
        ];
    }

    private function branch(int $position): void
    {
        if ($this->aborted || $this->bestMakespan <= $this->lowerBound) {
            return; // budget exhausted, or proven optimal already
        }
        if ($this->nodes >= $this->nodeLimit) {
            $this->aborted = true;

            return;
        }
        ++$this->nodes;

        if ($position === count($this->durations)) {
            // Every load stayed < bestMakespan on the way down, so this is a strict improvement.
            $this->best = $this->current;
            $this->bestMakespan = max($this->loads);

            return;
        }

        $duration = $this->durations[$position];

        // Try least-loaded employees first (finds good schedules early). asort is stable since PHP 8,
        // so equal loads keep index order -> deterministic. (Cheaper than usort with a closure.)
        $candidateLoads = [];
        foreach ($this->eligible[$position] as $e) {
            $candidateLoads[$e] = $this->loads[$e];
        }
        asort($candidateLoads);

        $tried = [];
        foreach ($candidateLoads as $e => $load) {
            if ($this->loads[$e] + $duration >= $this->bestMakespan) {
                break; // sorted by load, so every later candidate is at least as bad
            }
            $key = $this->skills[$e].':'.$this->loads[$e];
            if (isset($tried[$key])) {
                continue; // symmetric to an employee already tried
            }
            $tried[$key] = true;

            $this->assign($position, $e, $duration);
            if (!$this->remainingWorkCannotBeat($position + 1)) {
                $this->branch($position + 1);
            }
            $this->unassign($position, $e, $duration);

            if ($this->aborted || $this->bestMakespan <= $this->lowerBound) {
                return;
            }
        }
    }

    private function remainingWorkCannotBeat(int $nextPosition): bool
    {
        foreach ($this->levelEmployeeCount as $li => $count) {
            $hours = $this->levelLoads[$li] + $this->remaining[$li][$nextPosition];
            if (intdiv($hours + $count - 1, $count) >= $this->bestMakespan) {
                return true;
            }
        }

        return false;
    }

    private function assign(int $position, int $e, int $duration): void
    {
        $this->current[$position] = $e;
        $this->loads[$e] += $duration;
        foreach ($this->employeeLevels[$e] as $li) {
            $this->levelLoads[$li] += $duration;
        }
    }

    private function unassign(int $position, int $e, int $duration): void
    {
        $this->current[$position] = -1;
        $this->loads[$e] -= $duration;
        foreach ($this->employeeLevels[$e] as $li) {
            $this->levelLoads[$li] -= $duration;
        }
    }
}
