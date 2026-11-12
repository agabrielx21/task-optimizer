<?php

declare(strict_types=1);

namespace App\Service;

use App\Domain\Employee;
use App\Domain\Task;
use App\Optimizer\Input\InputValidator;
use App\Optimizer\Input\InvalidInputException;

final class DataStore
{
    public function __construct(
        private readonly InputValidator $validator,
        private readonly string $defaultEmployeesFile,
        private readonly string $defaultTasksFile,
    ) {
    }

    public function load(): array
    {
        [$employees, $tasks] = $this->validator->validate(
            OptimizationService::readJsonFile($this->defaultEmployeesFile),
            OptimizationService::readJsonFile($this->defaultTasksFile),
        );

        return self::toArrays($employees, $tasks);
    }

    // Validate first; write temp files, then rename them over the originals (never a half-written file).
    public function save(mixed $employees, mixed $tasks): array
    {
        [$validEmployees, $validTasks] = $this->validator->validate($employees, $tasks);
        $data = self::toArrays($validEmployees, $validTasks);

        $employeesTmp = $this->writeTemporary($this->defaultEmployeesFile, $data['employees']);
        $tasksTmp = $this->writeTemporary($this->defaultTasksFile, $data['tasks']);

        $this->replace($employeesTmp, $this->defaultEmployeesFile);
        $this->replace($tasksTmp, $this->defaultTasksFile);

        return $data;
    }

    private static function toArrays(array $employees, array $tasks): array
    {
        return [
            'employees' => array_map(
                static fn (Employee $e): array => ['employeeId' => $e->id, 'skillLevel' => $e->skillLevel, 'hourlyRate' => $e->hourlyRate],
                $employees,
            ),
            'tasks' => array_map(
                static fn (Task $t): array => ['taskId' => $t->id, 'skillLevel' => $t->skillLevel, 'estimation' => $t->estimation],
                $tasks,
            ),
        ];
    }

    private function writeTemporary(string $target, array $rows): string
    {
        $temporary = $target.'.tmp-'.bin2hex(random_bytes(4));
        $json = json_encode($rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)."\n";

        if (false === @file_put_contents($temporary, $json)) {
            throw new \RuntimeException(sprintf('Could not write "%s".', $temporary));
        }

        return $temporary;
    }

    private function replace(string $temporary, string $target): void
    {
        if (!@rename($temporary, $target)) {
            @unlink($temporary);
            throw new \RuntimeException(sprintf('Could not replace "%s".', $target));
        }
    }
}
