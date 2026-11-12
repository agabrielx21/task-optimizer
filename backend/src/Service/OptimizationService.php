<?php

declare(strict_types=1);

namespace App\Service;

use App\Domain\OptimizationResult;
use App\Domain\Variant;
use App\Optimizer\Cost\CostOptimizer;
use App\Optimizer\Input\InputValidator;
use App\Optimizer\Input\InvalidInputException;
use App\Optimizer\Makespan\MakespanOptimizer;

final class OptimizationService
{
    public function __construct(
        private readonly InputValidator $validator,
        private readonly CostOptimizer $costOptimizer,
        private readonly MakespanOptimizer $makespanOptimizer,
        private readonly string $defaultEmployeesFile,
        private readonly string $defaultTasksFile,
    ) {
    }

    public function run(Variant $variant, mixed $employees = null, mixed $tasks = null): OptimizationResult
    {
        $employees ??= self::readJsonFile($this->defaultEmployeesFile);
        $tasks ??= self::readJsonFile($this->defaultTasksFile);

        [$validEmployees, $validTasks] = $this->validator->validate($employees, $tasks);

        $optimizer = match ($variant) {
            Variant::Cost => $this->costOptimizer,
            Variant::Makespan => $this->makespanOptimizer,
        };

        return $optimizer->optimize($validEmployees, $validTasks);
    }

    public static function readJsonFile(string $path): mixed
    {
        if (!is_file($path) || !is_readable($path)) {
            throw new InvalidInputException([sprintf('file "%s" not found or not readable', $path)]);
        }

        try {
            return json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new InvalidInputException([sprintf('file "%s" is not valid JSON: %s', $path, $e->getMessage())]);
        }
    }
}
