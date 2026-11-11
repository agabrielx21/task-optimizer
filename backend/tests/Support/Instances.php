<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Domain\Employee;
use App\Domain\OptimizationResult;
use App\Domain\Task;
use App\Optimizer\InputValidator;
use PHPUnit\Framework\Assert;
use Random\Engine\Mt19937;
use Random\Randomizer;

/**
 * Test helpers: the bundled case data, seeded random instances, an exhaustive reference solver
 * and a validity check every optimizer result must pass.
 */
final class Instances
{
    /**
     * @return array{0: list<Employee>, 1: list<Task>}
     */
    public static function bundled(): array
    {
        $dir = dirname(__DIR__, 2).'/data';

        return (new InputValidator())->validate(
            json_decode((string) file_get_contents("$dir/employees.json"), true),
            json_decode((string) file_get_contents("$dir/tasks.json"), true),
        );
    }

    /**
     * Seeded (own Randomizer, no global state) -> the same seed always gives the same instance.
     * One employee always has the max skill, so the instance is feasible.
     *
     * @param callable(Randomizer): int|null $duration
     *
     * @return array{0: list<Employee>, 1: list<Task>}
     */
    public static function random(int $seed, int $employeeCount, int $taskCount, int $maxSkill = 3, ?callable $duration = null): array
    {
        $random = new Randomizer(new Mt19937($seed));
        $duration ??= static fn (Randomizer $r): int => $r->getInt(1, 8);

        $employees = [new Employee(1, $maxSkill, $random->getInt(50, 700))];
        for ($id = 2; $id <= $employeeCount; ++$id) {
            $employees[] = new Employee($id, $random->getInt(1, $maxSkill), $random->getInt(50, 700));
        }

        $tasks = [];
        for ($id = 1; $id <= $taskCount; ++$id) {
            $tasks[] = new Task($id, $random->getInt(1, $maxSkill), $duration($random));
        }

        return [$employees, $tasks];
    }

    /**
     * Tries all E^T assignments. Only for tiny instances; it is the "obviously correct" reference.
     *
     * @param list<Employee> $employees
     * @param list<Task>     $tasks
     *
     * @return array{cost: int|float, makespan: int}
     */
    public static function bruteForce(array $employees, array $tasks): array
    {
        $best = ['cost' => INF, 'makespan' => PHP_INT_MAX];
        $choice = array_fill(0, count($tasks), 0);
        $e = count($employees);
        $total = $e ** count($tasks);

        for ($n = 0; $n < $total; ++$n) {
            $x = $n;
            $loads = array_fill(0, $e, 0);
            $cost = 0;
            $valid = true;
            foreach ($tasks as $i => $task) {
                $choice[$i] = $x % $e;
                $x = intdiv($x, $e);
                $employee = $employees[$choice[$i]];
                if (!$employee->canExecute($task)) {
                    $valid = false;
                    break;
                }
                $loads[$choice[$i]] += $task->estimation;
                $cost += $employee->hourlyRate * $task->estimation;
            }
            if ($valid) {
                $best['cost'] = min($best['cost'], $cost);
                $best['makespan'] = min($best['makespan'], max($loads));
            }
        }

        return $best;
    }

    /**
     * Every task assigned exactly once, to an eligible employee; reported metrics match the assignment.
     *
     * @param list<Employee> $employees
     * @param list<Task>     $tasks
     */
    public static function assertValid(OptimizationResult $result, array $employees, array $tasks): void
    {
        Assert::assertTrue($result->isFeasible());
        Assert::assertCount(count($tasks), $result->assignments);

        $employeesById = [];
        foreach ($employees as $employee) {
            $employeesById[$employee->id] = $employee;
        }
        $tasksById = [];
        foreach ($tasks as $task) {
            $tasksById[$task->id] = $task;
        }

        $hours = array_fill_keys(array_keys($employeesById), 0);
        $price = 0;
        $seen = [];
        foreach ($result->assignments as ['taskId' => $taskId, 'employeeId' => $employeeId]) {
            Assert::assertArrayNotHasKey($taskId, $seen, "task $taskId assigned twice");
            $seen[$taskId] = true;
            $task = $tasksById[$taskId];
            $employee = $employeesById[$employeeId];
            Assert::assertTrue($employee->canExecute($task), "task $taskId given to ineligible employee $employeeId");
            $hours[$employeeId] += $task->estimation;
            $price += $employee->hourlyRate * $task->estimation;
        }

        Assert::assertEquals($price, $result->totalPrice);
        Assert::assertSame([] === $hours ? 0 : max($hours), $result->makespan);
        Assert::assertSame(
            $hours,
            array_column($result->employeeSummary, 'totalAssignedHours', 'employeeId'),
        );
    }
}
