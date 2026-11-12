<?php

declare(strict_types=1);

namespace App\Optimizer\Makespan;

use App\Domain\Employee;
use App\Domain\Task;

// LB = max(longest task, ceil(H_k / N_k), d_k[N_k-1] + d_k[N_k]) over the task skill levels k.
final class LowerBound
{
    public static function compute(array $employees, array $tasks): int
    {
        $bound = 0;
        $levels = [];
        foreach ($tasks as $task) {
            $bound = max($bound, $task->estimation);
            $levels[$task->skillLevel] = true;
        }

        foreach (array_keys($levels) as $level) {
            $durations = [];
            foreach ($tasks as $task) {
                if ($task->skillLevel >= $level) {
                    $durations[] = $task->estimation;
                }
            }
            $hours = array_sum($durations);
            $count = 0;
            foreach ($employees as $employee) {
                if ($employee->skillLevel >= $level) {
                    ++$count;
                }
            }
            $bound = max($bound, intdiv($hours + $count - 1, $count));

            if (count($durations) > $count) {
                rsort($durations);
                $bound = max($bound, $durations[$count - 1] + $durations[$count]);
            }
        }

        return $bound;
    }
}
