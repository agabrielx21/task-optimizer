<?php

declare(strict_types=1);

namespace App\Tests\Unit\Makespan;

use App\Domain\Employee;
use App\Domain\Task;
use App\Optimizer\Makespan\BranchAndBound;
use App\Optimizer\Makespan\LowerBound;
use App\Tests\Support\Instances;
use PHPUnit\Framework\TestCase;

final class LowerBoundAndSearchTest extends TestCase
{
    public function testBundledLowerBoundIsElevenFromTheSkillTwoLevel(): void
    {
        // max(6, ceil(78/10)=8, ceil(51/5)=11, ceil(20/2)=10) = 11
        [$employees, $tasks] = Instances::bundled();

        self::assertSame(11, LowerBound::compute($employees, $tasks));
    }

    public function testLongestTaskBound(): void
    {
        $employees = [new Employee(1, 1, 1), new Employee(2, 1, 1), new Employee(3, 1, 1)];

        self::assertSame(9, LowerBound::compute($employees, [new Task(1, 1, 9), new Task(2, 1, 1)]));
    }

    public function testPigeonholeBound(): void
    {
        // 3 tasks of 10h on 2 employees: average says 15, but two tasks must share -> 20.
        $employees = [new Employee(1, 1, 1), new Employee(2, 1, 1)];
        $tasks = [new Task(1, 1, 10), new Task(2, 1, 10), new Task(3, 1, 10)];

        self::assertSame(20, LowerBound::compute($employees, $tasks));
    }

    public function testBranchAndBoundImprovesABadStartAndProvesOptimality(): void
    {
        // Start: everything on employee 0 (makespan 12). Optimum is 6 | 6.
        $search = (new BranchAndBound(10_000))->search(
            durations: [3, 3, 2, 2, 2],
            taskSkills: [1, 1, 1, 1, 1],
            employeeSkills: [1, 1],
            initial: [0, 0, 0, 0, 0],
            upperBound: 12,
            lowerBound: 6,
        );

        self::assertSame(6, $search['makespan']);
        self::assertTrue($search['complete']);
    }

    public function testBranchAndBoundProvesUpperBoundOptimalAboveLowerBound(): void
    {
        // 2 employees, tasks 5,4,3: LB = max(5, ceil(12/2)=6, 4+3=7) = 7 and 7 is optimal (5 | 4+3).
        // With LB artificially weakened to 6 the search must exhaust the tree to prove 7.
        $search = (new BranchAndBound(10_000))->search([5, 4, 3], [1, 1, 1], [1, 1], [0, 1, 1], 7, 6);

        self::assertSame(7, $search['makespan']);
        self::assertTrue($search['complete'], 'exhausted tree = proof, even though LB < makespan');
    }

    public function testNodeBudgetStopsSearchAndFlagsIt(): void
    {
        $search = (new BranchAndBound(1))->search([3, 3, 2, 2, 2], [1, 1, 1, 1, 1], [1, 1], [0, 0, 0, 0, 0], 12, 6);

        self::assertFalse($search['complete']);
        self::assertSame([0, 0, 0, 0, 0], $search['assignment'], 'falls back to the incumbent');
    }
}
