<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Runs against a temporary copy of tests/fixtures (via APP_DATA_DIR), so the real data files are never written.
 */
final class DataEndpointTest extends WebTestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir().'/data-endpoint-'.bin2hex(random_bytes(4));
        mkdir($this->dir);
        copy(dirname(__DIR__).'/fixtures/employees.json', "$this->dir/employees.json");
        copy(dirname(__DIR__).'/fixtures/tasks.json', "$this->dir/tasks.json");
        $_ENV['APP_DATA_DIR'] = $_SERVER['APP_DATA_DIR'] = $this->dir;
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        unset($_ENV['APP_DATA_DIR'], $_SERVER['APP_DATA_DIR']);
        array_map('unlink', glob("$this->dir/*") ?: []);
        rmdir($this->dir);
    }

    public function testGetReturnsTheDefaultData(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/data');

        self::assertResponseIsSuccessful();
        $body = json_decode((string) $client->getResponse()->getContent(), true);
        self::assertCount(10, $body['employees']);
        self::assertCount(30, $body['tasks']);
    }

    public function testPutOverwritesTheFilesAndOptimizeUsesThem(): void
    {
        $client = static::createClient();
        $client->request('PUT', '/api/data', content: json_encode([
            'employees' => [['employeeId' => 1, 'skillLevel' => 2, 'hourlyRate' => 10]],
            'tasks' => [['taskId' => 1, 'skillLevel' => 1, 'estimation' => 3], ['taskId' => 2, 'skillLevel' => 2, 'estimation' => 4]],
        ]));

        self::assertResponseIsSuccessful();
        self::assertCount(2, json_decode((string) file_get_contents("$this->dir/tasks.json"), true));

        // An optimize request without a body now runs on the saved data.
        $client->request('POST', '/api/optimize/cost');
        $result = json_decode((string) $client->getResponse()->getContent(), true);
        self::assertSame(70, $result['totalPrice']);
        self::assertSame(7, $result['makespan']);
    }

    public function testInvalidPutIs400AndWritesNothing(): void
    {
        $before = (string) file_get_contents("$this->dir/employees.json");

        $client = static::createClient();
        $client->request('PUT', '/api/data', content: '{"employees": [{"employeeId": "x"}], "tasks": []}');

        self::assertResponseStatusCodeSame(400);
        self::assertSame('invalid_input', json_decode((string) $client->getResponse()->getContent(), true)['error']);
        self::assertSame($before, file_get_contents("$this->dir/employees.json"));
    }

    public function testEmptyPutIs400(): void
    {
        $client = static::createClient();
        $client->request('PUT', '/api/data');

        self::assertResponseStatusCodeSame(400);
    }
}
