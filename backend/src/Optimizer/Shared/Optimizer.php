<?php

declare(strict_types=1);

namespace App\Optimizer\Shared;

use App\Domain\Employee;
use App\Domain\OptimizationResult;
use App\Domain\Task;
use App\Domain\Variant;

interface Optimizer
{
    public function variant(): Variant;

    // Employees and tasks arrive sorted by id (from InputValidator).
    public function optimize(array $employees, array $tasks): OptimizationResult;
}
