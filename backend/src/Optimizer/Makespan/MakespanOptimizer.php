<?php

declare(strict_types=1);

namespace App\Optimizer\Makespan;

use App\Domain\Employee;
use App\Domain\OptimizationResult;
use App\Domain\Task;
use App\Domain\Variant;
use App\Optimizer\Shared\Feasibility;
use App\Optimizer\Shared\Optimizer;

// LPT greedy → local search → lower bound → branch & bound (only while not proven optimal).
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

    // Most constrained first (skill desc), then longest first, then id.
    private static function lptOrder(array $tasks): array
    {
        usort($tasks, static fn (Task $a, Task $b): int => [$b->skillLevel, $b->estimation, $a->id] <=> [$a->skillLevel, $a->estimation, $b->id]);

        return $tasks;
    }

    private function greedy(array $durations, array $taskSkills, array $employeeSkills): array
    {
        $loads = array_fill(0, count($employeeSkills), 0);
        $assignment = [];
        foreach ($durations as $position => $duration) {
            $chosen = -1;
            foreach ($employeeSkills as $e => $skill) {
                if ($skill >= $taskSkills[$position] && (-1 === $chosen || $loads[$e] < $loads[$chosen])) {
                    $chosen = $e;
                }
            }
            $assignment[$position] = $chosen;
            $loads[$chosen] += $duration;
        }

        return $assignment;
    }

    // Move or swap a task off a busiest employee while that strictly lowers the makespan.
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
                return $assignment;
            }

            $step = $this->findImprovingStep($assignment, $loads, $makespan, $durations, $taskSkills, $employeeSkills);
            if (null === $step) {
                return $assignment;
            }

            foreach ($step as [$position, $to]) {
                $from = $assignment[$position];
                $loads[$from] -= $durations[$position];
                $loads[$to] += $durations[$position];
                $assignment[$position] = $to;
            }
        }
    }

    private function findImprovingStep(array $assignment, array $loads, int $makespan, array $durations, array $taskSkills, array $employeeSkills): ?array
    {
        foreach ($loads as $c => $load) {
            if ($load !== $makespan) {
                continue;
            }

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

    private static function makespanOf(array $assignment, array $durations, int $employeeCount): int
    {
        $loads = array_fill(0, $employeeCount, 0);
        foreach ($assignment as $position => $e) {
            $loads[$e] += $durations[$position];
        }

        return max($loads);
    }
}
