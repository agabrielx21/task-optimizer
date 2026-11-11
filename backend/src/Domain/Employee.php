<?php

declare(strict_types=1);

namespace App\Domain;

final readonly class Employee
{
    public function __construct(
        public int $id,
        public int $skillLevel,
        public int|float $hourlyRate,
    ) {
    }

    public function canExecute(Task $task): bool
    {
        return $this->skillLevel >= $task->skillLevel;
    }
}
