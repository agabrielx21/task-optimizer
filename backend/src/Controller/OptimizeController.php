<?php

declare(strict_types=1);

namespace App\Controller;

use App\Domain\Variant;
use App\Optimizer\InvalidInputException;
use App\Optimizer\OptimizationService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

/**
 * POST /api/optimize/cost       body {"employees": [...], "tasks": [...]}
 * POST /api/optimize/makespan   same body
 * An empty body (or GET) runs on the bundled data/*.json files.
 *
 * 200 = optimization ran (status may still be "infeasible": that is a valid answer, not an error).
 * 400 = the input itself is broken (bad JSON, missing fields, wrong types).
 */
#[AsController]
final class OptimizeController
{
    public function __construct(private readonly OptimizationService $service)
    {
    }

    #[Route('/api/optimize/cost', name: 'optimize_cost', methods: ['GET', 'POST'])]
    public function cost(Request $request): JsonResponse
    {
        return $this->handle(Variant::Cost, $request);
    }

    #[Route('/api/optimize/makespan', name: 'optimize_makespan', methods: ['GET', 'POST'])]
    public function makespan(Request $request): JsonResponse
    {
        return $this->handle(Variant::Makespan, $request);
    }

    private function handle(Variant $variant, Request $request): JsonResponse
    {
        try {
            [$employees, $tasks] = $this->readPayload($request);
            $result = $this->service->run($variant, $employees, $tasks);
        } catch (InvalidInputException $e) {
            return new JsonResponse(['error' => 'invalid_input', 'details' => $e->errors], Response::HTTP_BAD_REQUEST);
        }

        return new JsonResponse($result->toArray());
    }

    /**
     * @return array{0: mixed, 1: mixed} [employees, tasks]; null = "use bundled file"
     *
     * @throws InvalidInputException
     */
    private function readPayload(Request $request): array
    {
        $content = trim($request->getContent());
        if ('' === $content) {
            return [null, null];
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
