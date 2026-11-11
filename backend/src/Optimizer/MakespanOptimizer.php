<?php

declare(strict_types=1);

namespace App\Optimizer;

use App\Domain\Employee;
use App\Domain\OptimizationResult;
use App\Domain\Task;
use App\Domain\Variant;

/**
 * Variant B: minimum makespan (max hours of any employee).
 *
 * This is scheduling on unrelated-but-nested machines (P|M_j(nested)|Cmax), NP-hard in general.
 * Strategy (hybrid):
 *   1. LPT greedy        -> a good schedule fast (upper bound UB).
 *   2. Local search      -> move / swap tasks off the busiest employee while it strictly helps.
 *   3. Lower bound LB    -> if UB == LB the schedule is provably optimal; stop.
 *   4. Branch & bound    -> otherwise search exactly for something better, within a node budget.
 * The result carries meta.isOptimal and meta.lowerBound, so a non-proven answer is reported honestly.
 *
 * Only the makespan is optimized (spec); among equally good schedules the one returned is fixed by
 * the id-based ordering, not by cost.
 */
final class MakespanOptimizer implements Optimizer
{
    public const DEFAULT_NODE_LIMIT = 250_000;

    public function __construct(private readonly int $nodeLimit = self::DEFAULT_NODE_LIMIT)
    {
    }

    public function variant(): Variant
    {
        return Variant::Makespan;
    }

    public function optimize(array $employees, array $tasks): OptimizationResult
    {
        $unassignable = Feasibility::unassignableTaskIds($employees, $tasks);
        if ([] !== $unassignable) {
            return OptimizationResult::infeasible(Variant::Makespan, $unassignable);
        }
        if ([] === $tasks) {
            return OptimizationResult::feasible(Variant::Makespan, $employees, $tasks, [], ['isOptimal' => true, 'lowerBound' => 0]);
        }

        $lowerBound = LowerBound::compute($employees, $tasks);
        $order = self::lptOrder($tasks);

        // Plain arrays: tasks by LPT position, employees by index in the id-sorted list.
        $durations = array_map(static fn (Task $t): int => $t->estimation, $order);
        $taskSkills = array_map(static fn (Task $t): int => $t->skillLevel, $order);
        $employeeSkills = array_map(static fn (Employee $e): int => $e->skillLevel, $employees);

        $assignment = $this->greedy($durations, $taskSkills, $employeeSkills);
        $assignment = $this->localSearch($assignment, $durations, $taskSkills, $employeeSkills, $lowerBound);
        $upperBound = self::makespanOf($assignment, $durations, count($employees));

        $isOptimal = $upperBound === $lowerBound;
        $nodes = 0;
        if (!$isOptimal) {
            $search = (new BranchAndBound($this->nodeLimit))
                ->search($durations, $taskSkills, $employeeSkills, $assignment, $upperBound, $lowerBound);
            $assignment = $search['assignment'];
            $isOptimal = $search['complete'];
            $nodes = $search['nodes'];
            // B&B exhausting the tree proves the incumbent optimal, which can sit above the LB:
            // the LB is only a bound, not always reachable.
        }

        $byTaskId = [];
        foreach ($order as $position => $task) {
            $byTaskId[$task->id] = $employees[$assignment[$position]]->id;
        }

        return OptimizationResult::feasible(Variant::Makespan, $employees, $tasks, $byTaskId, [
            'isOptimal' => $isOptimal,
            'lowerBound' => $lowerBound,
            'searchNodes' => $nodes,
        ]);
    }

    /**
     * Most constrained first (higher skill), then longest first (classic LPT), then id.
     * Constrained tasks have few possible employees, so they get placed before flexible tasks
     * fill those employees up.
     *
     * @param list<Task> $tasks
     *
     * @return list<Task>
     */
    private static function lptOrder(array $tasks): array
    {
        usort($tasks, static fn (Task $a, Task $b): int => [$b->skillLevel, $b->estimation, $a->id] <=> [$a->skillLevel, $a->estimation, $b->id]);

        return $tasks;
    }

    /**
     * Each task (in LPT order) goes to the currently least-loaded eligible employee; ties -> lowest id.
     *
     * @param list<int> $durations
     * @param list<int> $taskSkills
     * @param list<int> $employeeSkills
     *
     * @return list<int> employee index per task position
     */
    private function greedy(array $durations, array $taskSkills, array $employeeSkills): array
    {
        $loads = array_fill(0, count($employeeSkills), 0);
        $assignment = [];
        foreach ($durations as $position => $duration) {
            $chosen = -1;
            foreach ($employeeSkills as $e => $skill) {
                if ($skill >= $taskSkills[$position] && (-1 === $chosen || $loads[$e] < $loads[$chosen])) {
                    $chosen = $e; // strict "<" keeps the lowest index on ties
                }
            }
            $assignment[$position] = $chosen;
            $loads[$chosen] += $duration;
        }

        return $assignment;
    }

    /**
     * Repeatedly improve a busiest ("critical") employee c:
     *   move: task t from c to the least-loaded eligible e if load(e) + d(t) < load(c);
     *   swap: t1 on c with a shorter t2 on e if both are eligible and load(e) - d2 + d1 < load(c).
     * Every accepted step lowers c below the old makespan and keeps e below it, so either the number
     * of critical employees or the makespan strictly drops -> the loop terminates.
     *
     * @param list<int> $assignment
     * @param list<int> $durations
     * @param list<int> $taskSkills
     * @param list<int> $employeeSkills
     *
     * @return list<int>
     */
    private function localSearch(array $assignment, array $durations, array $taskSkills, array $employeeSkills, int $lowerBound): array
    {
        $employeeCount = count($employeeSkills);
        $loads = array_fill(0, $employeeCount, 0);
        foreach ($assignment as $position => $e) {
            $loads[$e] += $durations[$position];
        }

        while (true) {
            $makespan = max($loads);
            if ($makespan <= $lowerBound) {
                return $assignment; // cannot do better
            }

            $step = $this->findImprovingStep($assignment, $loads, $makespan, $durations, $taskSkills, $employeeSkills);
            if (null === $step) {
                return $assignment; // local optimum
            }

            foreach ($step as [$position, $to]) {
                $from = $assignment[$position];
                $loads[$from] -= $durations[$position];
                $loads[$to] += $durations[$position];
                $assignment[$position] = $to;
            }
        }
    }

    /**
     * @param list<int> $assignment
     * @param list<int> $loads
     * @param list<int> $durations
     * @param list<int> $taskSkills
     * @param list<int> $employeeSkills
     *
     * @return list<array{0: int, 1: int}>|null list of [taskPosition, newEmployeeIndex] reassignments
     */
    private function findImprovingStep(array $assignment, array $loads, int $makespan, array $durations, array $taskSkills, array $employeeSkills): ?array
    {
        foreach ($loads as $c => $load) {
            if ($load !== $makespan) {
                continue;
            }

            // Move
            foreach ($assignment as $position => $owner) {
                if ($owner !== $c) {
                    continue;
                }
                $best = -1;
                foreach ($employeeSkills as $e => $skill) {
                    if ($e !== $c && $skill >= $taskSkills[$position] && (-1 === $best || $loads[$e] < $loads[$best])) {
                        $best = $e;
                    }
                }
                if (-1 !== $best && $loads[$best] + $durations[$position] < $load) {
                    return [[$position, $best]];
                }
            }

            // Swap
            foreach ($assignment as $p1 => $owner1) {
                if ($owner1 !== $c) {
                    continue;
                }
                foreach ($assignment as $p2 => $e) {
                    if ($e === $c
                        || $durations[$p2] >= $durations[$p1]
                        || $employeeSkills[$e] < $taskSkills[$p1]
                        || $employeeSkills[$c] < $taskSkills[$p2]) {
                        continue;
                    }
                    if ($loads[$e] - $durations[$p2] + $durations[$p1] < $load) {
                        return [[$p1, $e], [$p2, $c]];
                    }
                }
            }
        }

        return null;
    }

    /**
     * @param list<int> $assignment
     * @param list<int> $durations
     */
    private static function makespanOf(array $assignment, array $durations, int $employeeCount): int
    {
        $loads = array_fill(0, $employeeCount, 0);
        foreach ($assignment as $position => $e) {
            $loads[$e] += $durations[$position];
        }

        return max($loads);
    }
}
