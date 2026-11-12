<?php

declare(strict_types=1);

namespace App\Optimizer\Makespan;

// Exact search below the heuristic's makespan. Capped by a node budget, not a time limit,
// so the result is the same on any machine.
final class BranchAndBound
{
    private array $durations;
    private array $eligible;
    private array $skills;
    private array $employeeLevels;
    private array $levelEmployeeCount;
    private array $remaining;

    private array $loads;
    private array $levelLoads;
    private array $current;
    private array $best;
    private int $bestMakespan;
    private int $lowerBound;
    private int $nodes = 0;
    private bool $aborted = false;

    public function __construct(private readonly int $nodeLimit)
    {
    }

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
            return;
        }
        if ($this->nodes >= $this->nodeLimit) {
            $this->aborted = true;

            return;
        }
        ++$this->nodes;

        if ($position === count($this->durations)) {
            $this->best = $this->current;
            $this->bestMakespan = max($this->loads);

            return;
        }

        $duration = $this->durations[$position];

        $candidateLoads = [];
        foreach ($this->eligible[$position] as $e) {
            $candidateLoads[$e] = $this->loads[$e];
        }
        asort($candidateLoads);

        $tried = [];
        foreach ($candidateLoads as $e => $load) {
            if ($this->loads[$e] + $duration >= $this->bestMakespan) {
                break;
            }
            // Same skill and same load as an employee already tried: symmetric branch, skip.
            $key = $this->skills[$e].':'.$this->loads[$e];
            if (isset($tried[$key])) {
                continue;
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
