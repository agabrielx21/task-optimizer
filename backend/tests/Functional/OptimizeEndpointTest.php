<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Command\OptimizeCommand;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Console\Tester\CommandTester;

final class OptimizeEndpointTest extends WebTestCase
{
    public function testCostEndpointWithBundledData(): void
    {
        $client = static::createClient();
        $client->request('POST', '/api/optimize/cost');

        self::assertResponseIsSuccessful();
        $body = json_decode((string) $client->getResponse()->getContent(), true);
        self::assertSame('cost', $body['variant']);
        self::assertSame('feasible', $body['status']);
        self::assertSame(17460, $body['totalPrice']);
        self::assertSame(51, $body['makespan']);
        self::assertCount(30, $body['assignments']);
        self::assertCount(10, $body['employeeSummary']);
    }

    public function testMakespanEndpointWithPayload(): void
    {
        $client = static::createClient();
        $client->request('POST', '/api/optimize/makespan', content: json_encode([
            'employees' => [['employeeId' => 1, 'skillLevel' => 1, 'hourlyRate' => 10], ['employeeId' => 2, 'skillLevel' => 1, 'hourlyRate' => 20]],
            'tasks' => [['taskId' => 1, 'skillLevel' => 1, 'estimation' => 4], ['taskId' => 2, 'skillLevel' => 1, 'estimation' => 4]],
        ]));

        self::assertResponseIsSuccessful();
        $body = json_decode((string) $client->getResponse()->getContent(), true);
        self::assertSame(4, $body['makespan']);
        self::assertSame(120, $body['totalPrice']);
        self::assertSame(['isOptimal' => true, 'lowerBound' => 4, 'searchNodes' => 0], $body['meta']);
    }

    public function testInfeasibleIsA200WithTaskIds(): void
    {
        $client = static::createClient();
        $client->request('POST', '/api/optimize/makespan', content: json_encode([
            'employees' => [['employeeId' => 1, 'skillLevel' => 1, 'hourlyRate' => 10]],
            'tasks' => [['taskId' => 9, 'skillLevel' => 2, 'estimation' => 1]],
        ]));

        self::assertResponseIsSuccessful();
        $body = json_decode((string) $client->getResponse()->getContent(), true);
        self::assertSame('infeasible', $body['status']);
        self::assertSame([9], $body['unassignableTaskIds']);
        self::assertSame([], $body['assignments']);
    }

    public function testInvalidInputIs400(): void
    {
        $client = static::createClient();
        $client->request('POST', '/api/optimize/cost', content: '{"employees": []}');

        self::assertResponseStatusCodeSame(400);
        self::assertSame('invalid_input', json_decode((string) $client->getResponse()->getContent(), true)['error']);
    }

    public function testCliCommand(): void
    {
        $tester = new CommandTester((new Application(static::bootKernel()))->find('app:optimize'));

        self::assertSame(0, $tester->execute(['--variant' => 'makespan']));
        self::assertSame(11, json_decode($tester->getDisplay(), true)['makespan']);

        self::assertSame(0, $tester->execute(['--variant' => 'cost']));
        self::assertSame(17460, json_decode($tester->getDisplay(), true)['totalPrice']);

        self::assertSame(2, $tester->execute(['--variant' => 'fastest'])); // Command::INVALID

        $dir = sys_get_temp_dir();
        file_put_contents("$dir/opt-employees.json", '[{"employeeId": 1, "skillLevel": 1, "hourlyRate": 5}]');
        file_put_contents("$dir/opt-tasks.json", '[{"taskId": 3, "skillLevel": 4, "estimation": 1}]');
        $exit = $tester->execute(['--variant' => 'cost', 'employees' => "$dir/opt-employees.json", 'tasks' => "$dir/opt-tasks.json"]);
        self::assertSame(OptimizeCommand::EXIT_INFEASIBLE, $exit);
        self::assertSame([3], json_decode($tester->getDisplay(), true)['unassignableTaskIds']);

        self::assertSame(1, $tester->execute(['--variant' => 'cost', 'employees' => "$dir/does-not-exist.json"]));
    }
}
