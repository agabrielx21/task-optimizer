<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Domain\Employee;
use App\Domain\Task;
use App\Optimizer\CostOptimizer;
use App\Tests\Support\Instances;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class CostOptimizerTest extends TestCase
{
    public function testBundledDataMatchesHandCalculation(): void
    {
        [$employees, $tasks] = Instances::bundled();
        $result = (new CostOptimizer())->optimize($employees, $tasks);

        Instances::assertValid($result, $employees, $tasks);
        self::assertSame(17460, $result->totalPrice); // 27h * 80 + 51h * 300
        self::assertSame(51, $result->makespan);
        self::assertTrue($result->meta['isOptimal']);

        $hours = array_column($result->employeeSummary, 'totalAssignedHours', 'employeeId');
        self::assertSame([1 => 0, 2 => 0, 3 => 0, 4 => 51, 5 => 0, 6 => 0, 7 => 0, 8 => 27, 9 => 0, 10 => 0], $hours);

        $skillByTask = array_column(array_map(static fn (Task $t): array => ['id' => $t->id, 's' => $t->skillLevel], $tasks), 's', 'id');
        foreach ($result->assignments as ['taskId' => $taskId, 'employeeId' => $employeeId]) {
            self::assertSame(1 === $skillByTask[$taskId] ? 8 : 4, $employeeId);
        }
    }

    public function testInfeasibleTaskIsReported(): void
    {
        $employees = [new Employee(1, 2, 100)];
        $tasks = [new Task(7, 3, 1), new Task(2, 1, 4), new Task(3, 5, 2)];

        $result = (new CostOptimizer())->optimize($employees, $tasks);

        self::assertFalse($result->isFeasible());
        self::assertSame([3, 7], $result->unassignableTaskIds);
        self::assertSame([], $result->assignments);
    }

    public function testWidelyVaryingRates(): void
    {
        // A very expensive specialist must only get what nobody cheaper can do.
        $employees = [
            new Employee(1, 1, 1),
            new Employee(2, 2, 1_000_000),
            new Employee(3, 3, 0.5), // cheapest AND most skilled -> takes everything
        ];
        $tasks = [new Task(1, 1, 10), new Task(2, 2, 10), new Task(3, 3, 10)];

        $result = (new CostOptimizer())->optimize($employees, $tasks);

        self::assertSame([3, 3, 3], array_column($result->assignments, 'employeeId'));
        self::assertEqualsWithDelta(15.0, $result->totalPrice, 1e-9);

        // Without the cheap specialist, the skill-2 task has to pay 1,000,000/h.
        $result = (new CostOptimizer())->optimize([$employees[0], $employees[1]], [$tasks[0], $tasks[1]]);
        self::assertSame([1, 2], array_column($result->assignments, 'employeeId'));
        self::assertSame(10 + 10_000_000, $result->totalPrice);
    }

    public function testEqualRatesGoToLowestEmployeeId(): void
    {
        $employees = [new Employee(5, 1, 100), new Employee(2, 1, 100), new Employee(9, 1, 100)];
        usort($employees, static fn (Employee $a, Employee $b): int => $a->id <=> $b->id); // validator order

        $result = (new CostOptimizer())->optimize($employees, [new Task(1, 1, 3), new Task(2, 1, 3)]);

        self::assertSame([2, 2], array_column($result->assignments, 'employeeId'));
    }

    public function testNoTasks(): void
    {
        $result = (new CostOptimizer())->optimize([new Employee(1, 1, 10)], []);

        self::assertTrue($result->isFeasible());
        self::assertSame(0, $result->totalPrice);
        self::assertSame(0, $result->makespan);
        self::assertSame([['employeeId' => 1, 'totalAssignedHours' => 0]], $result->employeeSummary);
    }

    /**
     * @return iterable<string, array{int}>
     */
    public static function seeds(): iterable
    {
        foreach (range(1, 25) as $seed) {
            yield "seed $seed" => [$seed];
        }
    }

    #[DataProvider('seeds')]
    public function testMatchesBruteForceOnSmallInstances(int $seed): void
    {
        [$employees, $tasks] = Instances::random($seed, 3, 6);

        $result = (new CostOptimizer())->optimize($employees, $tasks);

        Instances::assertValid($result, $employees, $tasks);
        self::assertEquals(Instances::bruteForce($employees, $tasks)['cost'], $result->totalPrice);
    }
}
