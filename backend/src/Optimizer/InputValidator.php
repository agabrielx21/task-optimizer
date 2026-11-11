<?php

declare(strict_types=1);

namespace App\Optimizer;

use App\Domain\Employee;
use App\Domain\Task;

/**
 * Turns decoded JSON (untrusted, mixed) into domain objects, collecting every error instead of
 * stopping at the first one. Output lists are sorted by id, so the optimizers never depend on the
 * order of the input file -> part of the determinism guarantee.
 *
 * Assumptions (stated in the README): ids are unique integers, skill levels are integers >= 0,
 * estimations are whole hours > 0 (the makespan lower bound and branch & bound rely on integers),
 * hourly rates are non-negative numbers.
 */
final class InputValidator
{
    /**
     * @return array{0: list<Employee>, 1: list<Task>}
     *
     * @throws InvalidInputException
     */
    public function validate(mixed $employees, mixed $tasks): array
    {
        $errors = [];
        $parsedEmployees = $this->parseEmployees($employees, $errors);
        $parsedTasks = $this->parseTasks($tasks, $errors);

        if ([] !== $errors) {
            throw new InvalidInputException($errors);
        }

        return [$parsedEmployees, $parsedTasks];
    }

    /**
     * @param list<string> $errors
     *
     * @return list<Employee>
     */
    private function parseEmployees(mixed $raw, array &$errors): array
    {
        if (!is_array($raw) || !array_is_list($raw)) {
            $errors[] = 'employees must be a JSON array';

            return [];
        }

        $result = [];
        $seen = [];
        foreach ($raw as $i => $item) {
            $path = "employees[$i]";
            if (!is_array($item)) {
                $errors[] = "$path must be an object";
                continue;
            }

            $id = $this->requireInt($item, 'employeeId', $path, 0, $errors);
            $skill = $this->requireInt($item, 'skillLevel', $path, 0, $errors);
            $rate = $item['hourlyRate'] ?? null;
            if ((!is_int($rate) && !is_float($rate)) || $rate < 0) {
                $errors[] = "$path.hourlyRate must be a non-negative number";
                $rate = null;
            }

            if (null !== $id && isset($seen[$id])) {
                $errors[] = "$path.employeeId $id is duplicated";
                continue;
            }
            if (null === $id || null === $skill || null === $rate) {
                continue;
            }

            $seen[$id] = true;
            $result[] = new Employee($id, $skill, $rate);
        }

        usort($result, static fn (Employee $a, Employee $b): int => $a->id <=> $b->id);

        return $result;
    }

    /**
     * @param list<string> $errors
     *
     * @return list<Task>
     */
    private function parseTasks(mixed $raw, array &$errors): array
    {
        if (!is_array($raw) || !array_is_list($raw)) {
            $errors[] = 'tasks must be a JSON array';

            return [];
        }

        $result = [];
        $seen = [];
        foreach ($raw as $i => $item) {
            $path = "tasks[$i]";
            if (!is_array($item)) {
                $errors[] = "$path must be an object";
                continue;
            }

            $id = $this->requireInt($item, 'taskId', $path, 0, $errors);
            $skill = $this->requireInt($item, 'skillLevel', $path, 0, $errors);
            $estimation = $this->requireInt($item, 'estimation', $path, 1, $errors);

            if (null !== $id && isset($seen[$id])) {
                $errors[] = "$path.taskId $id is duplicated";
                continue;
            }
            if (null === $id || null === $skill || null === $estimation) {
                continue;
            }

            $seen[$id] = true;
            $result[] = new Task($id, $skill, $estimation);
        }

        usort($result, static fn (Task $a, Task $b): int => $a->id <=> $b->id);

        return $result;
    }

    /**
     * @param array<mixed>  $item
     * @param list<string>  $errors
     */
    private function requireInt(array $item, string $field, string $path, int $min, array &$errors): ?int
    {
        $value = $item[$field] ?? null;
        if (!is_int($value) || $value < $min) {
            $errors[] = sprintf('%s.%s must be an integer >= %d', $path, $field, $min);

            return null;
        }

        return $value;
    }
}
