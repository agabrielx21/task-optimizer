<?php

declare(strict_types=1);

namespace App\Optimizer\Cost;

use App\Domain\Employee;
use App\Domain\OptimizationResult;
use App\Domain\Variant;
use App\Optimizer\Shared\Feasibility;
use App\Optimizer\Shared\Optimizer;

// Exact: no capacity limit, so tasks are independent; each goes to its cheapest eligible employee (tie: lowest id).
final class CostOptimizer implements Optimizer
{
    public function variant(): Variant
    {
        return Variant::Cost;
    }

    public function optimize(array $employees, array $tasks): OptimizationResult
    {
        $unassignable = Feasibility::unassignableTaskIds($employees, $tasks);
        if ([] !== $unassignable) {
            return OptimizationResult::infeasible(Variant::Cost, $unassignable);
        }

        $byPrice = $employees;
        usort($byPrice, static fn (Employee $a, Employee $b): int => [$a->hourlyRate, $a->id] <=> [$b->hourlyRate, $b->id]);

        $assignment = [];
        foreach ($tasks as $task) {
            foreach ($byPrice as $employee) {
                if ($employee->canExecute($task)) {
                    $assignment[$task->id] = $employee->id;
                    break;
                }
            }
        }

        return OptimizationResult::feasible(Variant::Cost, $employees, $tasks, $assignment, ['isOptimal' => true]);
    }
}
