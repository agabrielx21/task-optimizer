<?php

declare(strict_types=1);

namespace App\Domain;

final readonly class OptimizationResult
{
    public const STATUS_FEASIBLE = 'feasible';
    public const STATUS_INFEASIBLE = 'infeasible';

    private function __construct(
        public Variant $variant,
        public string $status,
        public array $assignments,
        public int|float $totalPrice,
        public int $makespan,
        public array $employeeSummary,
        public array $unassignableTaskIds,
        public array $meta,
    ) {
    }

    // Price, makespan and hours per employee are computed here, once, for both variants.
    public static function feasible(Variant $variant, array $employees, array $tasks, array $assignment, array $meta = []): self
    {
        $employeesById = [];
        $hours = [];
        foreach ($employees as $employee) {
            $employeesById[$employee->id] = $employee;
            $hours[$employee->id] = 0;
        }

        $totalPrice = 0;
        $assignments = [];
        foreach ($tasks as $task) {
            if (!isset($assignment[$task->id])) {
                throw new \LogicException(sprintf('Task %d was not assigned.', $task->id));
            }
            $employee = $employeesById[$assignment[$task->id]];
            if (!$employee->canExecute($task)) {
                throw new \LogicException(sprintf('Task %d assigned to ineligible employee %d.', $task->id, $employee->id));
            }

            $hours[$employee->id] += $task->estimation;
            $totalPrice += $employee->hourlyRate * $task->estimation;
            $assignments[] = ['taskId' => $task->id, 'employeeId' => $employee->id];
        }

        usort($assignments, static fn (array $a, array $b): int => $a['taskId'] <=> $b['taskId']);
        ksort($hours);

        $summary = [];
        foreach ($hours as $employeeId => $total) {
            $summary[] = ['employeeId' => $employeeId, 'totalAssignedHours' => $total];
        }

        $makespan = [] === $hours ? 0 : max($hours);

        return new self($variant, self::STATUS_FEASIBLE, $assignments, $totalPrice, $makespan, $summary, [], $meta);
    }

    public static function infeasible(Variant $variant, array $unassignableTaskIds): self
    {
        sort($unassignableTaskIds);

        return new self($variant, self::STATUS_INFEASIBLE, [], 0, 0, [], $unassignableTaskIds, []);
    }

    public function isFeasible(): bool
    {
        return self::STATUS_FEASIBLE === $this->status;
    }

    public function toArray(): array
    {
        return [
            'variant' => $this->variant->value,
            'status' => $this->status,
            'assignments' => $this->assignments,
            'totalPrice' => $this->totalPrice,
            'makespan' => $this->makespan,
            'employeeSummary' => $this->employeeSummary,
            'unassignableTaskIds' => $this->unassignableTaskIds,
            // object: an empty meta is {} in JSON, not []
            'meta' => (object) $this->meta,
        ];
    }
}
