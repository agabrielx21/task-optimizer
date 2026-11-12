<?php

declare(strict_types=1);

namespace App\Controller;

use App\Optimizer\Input\InvalidInputException;
use Symfony\Component\HttpFoundation\Request;

final class RequestPayload
{
    public static function employeesAndTasks(Request $request, bool $allowEmpty): array
    {
        $content = trim($request->getContent());
        if ('' === $content) {
            if ($allowEmpty) {
                return [null, null];
            }
            throw new InvalidInputException(['request body is empty']);
        }

        try {
            $body = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new InvalidInputException(['request body is not valid JSON: '.$e->getMessage()]);
        }

        if (!is_array($body) || !array_key_exists('employees', $body) || !array_key_exists('tasks', $body)) {
            throw new InvalidInputException(['request body must be an object with "employees" and "tasks"']);
        }

        return [$body['employees'], $body['tasks']];
    }
}
