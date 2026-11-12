<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Optimizer\Input\InputValidator;
use App\Optimizer\Input\InvalidInputException;
use App\Service\DataStore;
use PHPUnit\Framework\TestCase;

final class DataStoreTest extends TestCase
{
    private string $dir;
    private DataStore $store;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir().'/datastore-'.bin2hex(random_bytes(4));
        mkdir($this->dir);
        copy(dirname(__DIR__, 2).'/fixtures/employees.json', "$this->dir/employees.json");
        copy(dirname(__DIR__, 2).'/fixtures/tasks.json', "$this->dir/tasks.json");
        $this->store = new DataStore(new InputValidator(), "$this->dir/employees.json", "$this->dir/tasks.json");
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob("$this->dir/*") ?: []);
        rmdir($this->dir);
    }

    public function testLoadReturnsTheFiles(): void
    {
        $data = $this->store->load();

        self::assertCount(10, $data['employees']);
        self::assertCount(30, $data['tasks']);
        self::assertSame(['employeeId' => 1, 'skillLevel' => 1, 'hourlyRate' => 100], $data['employees'][0]);
    }

    public function testSaveWritesValidatedDataSortedById(): void
    {
        $saved = $this->store->save(
            [['employeeId' => 2, 'skillLevel' => 1, 'hourlyRate' => 9.5], ['employeeId' => 1, 'skillLevel' => 3, 'hourlyRate' => 50]],
            [['taskId' => 7, 'skillLevel' => 2, 'estimation' => 4]],
        );

        self::assertSame([1, 2], array_column($saved['employees'], 'employeeId'));
        self::assertSame($saved, $this->store->load(), 'what save returns is what load reads back');
        self::assertSame(9.5, json_decode((string) file_get_contents("$this->dir/employees.json"), true)[1]['hourlyRate']);
        self::assertSame(['employees.json', 'tasks.json'], array_map('basename', glob("$this->dir/*") ?: []), 'no temporary files left');
    }

    public function testInvalidDataIsRejectedAndFilesStayUntouched(): void
    {
        $before = (string) file_get_contents("$this->dir/tasks.json");

        try {
            $this->store->save([], [['taskId' => 1, 'skillLevel' => 1, 'estimation' => 0]]);
            self::fail('expected InvalidInputException');
        } catch (InvalidInputException $e) {
            self::assertSame(['tasks[0].estimation must be an integer >= 1'], $e->errors);
        }

        self::assertSame($before, file_get_contents("$this->dir/tasks.json"));
    }

    public function testUnwritableFolderFailsCleanly(): void
    {
        $store = new DataStore(new InputValidator(), "$this->dir/missing/employees.json", "$this->dir/missing/tasks.json");

        $this->expectException(\RuntimeException::class);
        $store->save([['employeeId' => 1, 'skillLevel' => 1, 'hourlyRate' => 1]], []);
    }
}
