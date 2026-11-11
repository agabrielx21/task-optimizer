<?php

declare(strict_types=1);

namespace App\Optimizer;

use App\Domain\Employee;
use App\Domain\Task;

/**
 * Lower bound on the optimal makespan.
 *
 * 1. The longest task must run somewhere: LB >= max estimation.
 * 2. Skill levels are ordered, so eligibility sets are nested: a task with skill >= k can only go to
 *    an employee with skill >= k. Those H_k hours are shared by at most N_k employees, so one of
 *    them works at least H_k / N_k hours. Hours are integers -> LB >= ceil(H_k / N_k), for every k.
 *    (k = lowest level gives the classic "total work / number of employees" bound.)
 * 3. Tasks are indivisible. If more than N_k tasks need skill >= k, then among the N_k + 1 longest
 *    of them two must share an employee (pigeonhole), so LB >= d[N_k - 1] + d[N_k] with d sorted
 *    descending (0-based). Bound 2 misses this when a few long tasks dominate.
 *
 * Only task skill levels need checking as thresholds: between two consecutive task levels, H_k
 * stays the same while N_k can only shrink, so the bound is tightest exactly at a task level.
 */
final class LowerBound
{
    /**
     * @param list<Employee> $employees
     * @param list<Task>     $tasks     must be feasible
     */
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
            // $count > 0 because the instance is feasible. intdiv-ceil keeps it in integers.
            $bound = max($bound, intdiv($hours + $count - 1, $count));

            if (count($durations) > $count) {
                rsort($durations);
                $bound = max($bound, $durations[$count - 1] + $durations[$count]);
            }
        }

        return $bound;
    }
}
