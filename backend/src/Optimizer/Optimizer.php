<?php

declare(strict_types=1);

namespace App\Optimizer;

use App\Domain\Employee;
use App\Domain\OptimizationResult;
use App\Domain\Task;
use App\Domain\Variant;

interface Optimizer
{
    public function variant(): Variant;

    /**
     * @param list<Employee> $employees sorted by id (as returned by InputValidator)
     * @param list<Task>     $tasks     sorted by id
     */
    public function optimize(array $employees, array $tasks): OptimizationResult;
}
