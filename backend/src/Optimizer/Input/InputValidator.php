<?php

declare(strict_types=1);

namespace App\Optimizer\Input;

use App\Domain\Employee;
use App\Domain\Task;

// Collects every error; returns objects sorted by id, so input order never matters.
final class InputValidator
{
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
