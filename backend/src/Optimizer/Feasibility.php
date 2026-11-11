<?php

declare(strict_types=1);

namespace App\Optimizer;

use App\Domain\Employee;
use App\Domain\Task;

final class Feasibility
{
    /**
     * A task is assignable iff the most skilled employee can do it, so one max() suffices: O(E + T).
     *
     * @param list<Employee> $employees
     * @param list<Task>     $tasks
     *
     * @return list<int> ids of tasks nobody is skilled enough for (empty = feasible)
     */
    public static function unassignableTaskIds(array $employees, array $tasks): array
    {
        $maxSkill = PHP_INT_MIN;
        foreach ($employees as $employee) {
            $maxSkill = max($maxSkill, $employee->skillLevel);
        }

        $ids = [];
        foreach ($tasks as $task) {
            if ($task->skillLevel > $maxSkill) {
                $ids[] = $task->id;
            }
        }

        return $ids;
    }
}
