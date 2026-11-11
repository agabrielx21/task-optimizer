<?php

declare(strict_types=1);

namespace App\Optimizer;

use App\Domain\Employee;
use App\Domain\OptimizationResult;
use App\Domain\Variant;

/**
 * Variant A: minimum total cost.
 *
 * Total cost = sum over tasks of rate(employee(t)) * estimation(t). There is no capacity limit and
 * no deadline, so each term depends on one task only: minimizing every term independently
 * minimizes the sum. Hence: give each task to the cheapest eligible employee. This is exact, not a
 * heuristic. Ties on rate go to the lowest employeeId (determinism).
 *
 * Complexity: O(E log E) sort + O(T * E) scan.
 */
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

        // Cheapest first; the first eligible employee in this order is the answer for a task.
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
