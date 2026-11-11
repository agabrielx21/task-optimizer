<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Optimizer\InputValidator;
use App\Optimizer\InvalidInputException;
use PHPUnit\Framework\TestCase;

final class InputValidatorTest extends TestCase
{
    public function testParsesAndSortsById(): void
    {
        [$employees, $tasks] = (new InputValidator())->validate(
            [['employeeId' => 3, 'skillLevel' => 1, 'hourlyRate' => 9.5], ['employeeId' => 1, 'skillLevel' => 2, 'hourlyRate' => 0]],
            [['taskId' => 2, 'skillLevel' => 1, 'estimation' => 1], ['taskId' => 1, 'skillLevel' => 2, 'estimation' => 4]],
        );

        self::assertSame([1, 3], array_map(static fn ($e) => $e->id, $employees));
        self::assertSame(9.5, $employees[1]->hourlyRate);
        self::assertSame([1, 2], array_map(static fn ($t) => $t->id, $tasks));
    }

    public function testCollectsAllErrors(): void
    {
        try {
            (new InputValidator())->validate(
                [['employeeId' => 1, 'skillLevel' => '2', 'hourlyRate' => -1], ['employeeId' => 1, 'skillLevel' => 1, 'hourlyRate' => 1], 'x'],
                [['taskId' => 1, 'skillLevel' => 1, 'estimation' => 0], ['taskId' => 2, 'skillLevel' => 1, 'estimation' => 1.5]],
            );
            self::fail('expected InvalidInputException');
        } catch (InvalidInputException $e) {
            self::assertSame([
                'employees[0].skillLevel must be an integer >= 0',
                'employees[0].hourlyRate must be a non-negative number',
                'employees[2] must be an object',
                'tasks[0].estimation must be an integer >= 1',
                'tasks[1].estimation must be an integer >= 1',
            ], $e->errors);
        }
    }

    public function testRejectsDuplicateIdsAndNonLists(): void
    {
        $this->expectException(InvalidInputException::class);
        $this->expectExceptionMessage('employees[1].employeeId 1 is duplicated; tasks must be a JSON array');

        (new InputValidator())->validate(
            [['employeeId' => 1, 'skillLevel' => 1, 'hourlyRate' => 1], ['employeeId' => 1, 'skillLevel' => 1, 'hourlyRate' => 1]],
            ['taskId' => 1],
        );
    }
}
