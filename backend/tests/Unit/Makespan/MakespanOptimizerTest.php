<?php

declare(strict_types=1);

namespace App\Tests\Unit\Makespan;

use App\Domain\Employee;
use App\Domain\Task;
use App\Optimizer\Input\InputValidator;
use App\Optimizer\Makespan\LowerBound;
use App\Optimizer\Makespan\MakespanOptimizer;
use App\Tests\Support\Instances;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Random\Engine\Mt19937;
use Random\Randomizer;

final class MakespanOptimizerTest extends TestCase
{
    public function testBundledDataReachesLowerBound(): void
    {
        [$employees, $tasks] = Instances::bundled();
        $result = (new MakespanOptimizer())->optimize($employees, $tasks);

        Instances::assertValid($result, $employees, $tasks);
        self::assertSame(11, $result->makespan);
        self::assertSame(11, $result->meta['lowerBound']);
        self::assertTrue($result->meta['isOptimal']);
        self::assertSame(78, array_sum(array_column($result->employeeSummary, 'totalAssignedHours')));
    }

    public function testInfeasibleTaskIsReported(): void
    {
        $result = (new MakespanOptimizer())->optimize([new Employee(1, 1, 10)], [new Task(4, 2, 1), new Task(1, 1, 1)]);

        self::assertFalse($result->isFeasible());
        self::assertSame([4], $result->unassignableTaskIds);
    }

    public function testNoEmployeesIsInfeasible(): void
    {
        $result = (new MakespanOptimizer())->optimize([], [new Task(1, 0, 1)]);

        self::assertSame([1], $result->unassignableTaskIds);
    }

    public function testHighlySkewedDurations(): void
    {
        // One 100h task dominates: the makespan can never go below it, and the 50 short tasks
        // must all land on the other employees.
        $employees = [new Employee(1, 1, 10), new Employee(2, 1, 10), new Employee(3, 1, 10)];
        $tasks = [new Task(1, 1, 100)];
        for ($id = 2; $id <= 51; ++$id) {
            $tasks[] = new Task($id, 1, 1);
        }

        $result = (new MakespanOptimizer())->optimize($employees, $tasks);

        Instances::assertValid($result, $employees, $tasks);
        self::assertSame(100, $result->makespan);
        self::assertTrue($result->meta['isOptimal']);
        $longTaskOwner = $result->assignments[0]['employeeId'];
        $hours = array_column($result->employeeSummary, 'totalAssignedHours', 'employeeId');
        self::assertSame(100, $hours[$longTaskOwner], 'nothing else should be stacked on the 100h task');
    }

    public function testClassicLptCounterexampleIsSolvedOptimally(): void
    {
        // Greedy LPT gives 7 (3+2+2 | 3+2); the optimum is 6 (3+3 | 2+2+2).
        $employees = [new Employee(1, 1, 10), new Employee(2, 1, 10)];
        $tasks = [new Task(1, 1, 3), new Task(2, 1, 3), new Task(3, 1, 2), new Task(4, 1, 2), new Task(5, 1, 2)];

        $result = (new MakespanOptimizer())->optimize($employees, $tasks);

        self::assertSame(6, $result->makespan);
        self::assertTrue($result->meta['isOptimal']);
    }

    public function testOutputIsDeterministicAndIndependentOfInputOrder(): void
    {
        [$employees, $tasks] = Instances::random(42, 12, 120);
        $optimizer = new MakespanOptimizer();

        $first = $optimizer->optimize($employees, $tasks)->toArray();
        self::assertEquals($first, $optimizer->optimize($employees, $tasks)->toArray());

        // The validator sorts by id; feed it the same data shuffled -> same answer.
        $rawEmployees = array_map(static fn (Employee $e): array => ['employeeId' => $e->id, 'skillLevel' => $e->skillLevel, 'hourlyRate' => $e->hourlyRate], $employees);
        $rawTasks = array_map(static fn (Task $t): array => ['taskId' => $t->id, 'skillLevel' => $t->skillLevel, 'estimation' => $t->estimation], $tasks);
        $shuffler = new Randomizer(new Mt19937(1));
        [$e2, $t2] = (new InputValidator())->validate($shuffler->shuffleArray($rawEmployees), $shuffler->shuffleArray($rawTasks));

        self::assertEquals($first, $optimizer->optimize($e2, $t2)->toArray());
    }

    /**
     * @return iterable<string, array{int, int, int}>
     */
    public static function smallInstances(): iterable
    {
        foreach (range(1, 40) as $seed) {
            yield "seed $seed" => [$seed, 2 + $seed % 3, 5 + $seed % 4];
        }
    }

    #[DataProvider('smallInstances')]
    public function testMatchesBruteForceOnSmallInstances(int $seed, int $employeeCount, int $taskCount): void
    {
        [$employees, $tasks] = Instances::random($seed, $employeeCount, $taskCount);

        $result = (new MakespanOptimizer())->optimize($employees, $tasks);

        Instances::assertValid($result, $employees, $tasks);
        self::assertSame(Instances::bruteForce($employees, $tasks)['makespan'], $result->makespan);
        self::assertTrue($result->meta['isOptimal']);
    }

    /**
     * @return iterable<string, array{int, int, bool}>
     */
    public static function performanceInstances(): iterable
    {
        foreach ([[5, 50], [5, 200], [30, 50], [30, 200], [15, 120]] as [$e, $t]) {
            yield "$e employees, $t tasks, uniform" => [$e, $t, false];
            yield "$e employees, $t tasks, skewed" => [$e, $t, true];
        }
    }

    #[DataProvider('performanceInstances')]
    public function testPerformanceOnLargeInstances(int $employeeCount, int $taskCount, bool $skewed): void
    {
        $duration = $skewed
            ? static fn (Randomizer $r): int => 1 === $r->getInt(1, 10) ? $r->getInt(40, 100) : $r->getInt(1, 3)
            : null;
        [$employees, $tasks] = Instances::random($employeeCount * 1000 + $taskCount, $employeeCount, $taskCount, 5, $duration);

        $start = hrtime(true);
        $result = (new MakespanOptimizer())->optimize($employees, $tasks);
        $seconds = (hrtime(true) - $start) / 1e9;

        Instances::assertValid($result, $employees, $tasks);
        self::assertGreaterThanOrEqual(LowerBound::compute($employees, $tasks), $result->makespan);
        self::assertLessThan(3.0, $seconds, 'node budget should cap the exact search');
    }

    public function testHardInstanceHitsNodeBudgetAndIsHonestAboutIt(): void
    {
        // Found by scanning seeds: few long tasks that only a few skilled employees can take.
        // The schedule is valid but optimality is not proven within the budget.
        $duration = static fn (Randomizer $r): int => 1 === $r->getInt(1, 10) ? $r->getInt(40, 100) : $r->getInt(1, 3);
        [$employees, $tasks] = Instances::random(4, 15, 150, 5, $duration);
        $optimizer = new MakespanOptimizer();

        $start = hrtime(true);
        $result = $optimizer->optimize($employees, $tasks);
        $seconds = (hrtime(true) - $start) / 1e9;

        Instances::assertValid($result, $employees, $tasks);
        self::assertFalse($result->meta['isOptimal']);
        self::assertSame(MakespanOptimizer::DEFAULT_NODE_LIMIT, $result->meta['searchNodes']);
        self::assertGreaterThanOrEqual($result->meta['lowerBound'], $result->makespan);
        self::assertLessThan(3.0, $seconds);
        // Node budget instead of a time limit -> even the aborted search is reproducible.
        self::assertEquals($result->toArray(), $optimizer->optimize($employees, $tasks)->toArray());
    }
}
