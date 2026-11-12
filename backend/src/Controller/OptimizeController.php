<?php

declare(strict_types=1);

namespace App\Controller;

use App\Domain\Variant;
use App\Optimizer\Input\InvalidInputException;
use App\Service\OptimizationService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

// 200 also for an infeasible result (a valid answer); 400 only for broken input.
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
            [$employees, $tasks] = RequestPayload::employeesAndTasks($request, allowEmpty: true);
            $result = $this->service->run($variant, $employees, $tasks);
        } catch (InvalidInputException $e) {
            return new JsonResponse(['error' => 'invalid_input', 'details' => $e->errors], Response::HTTP_BAD_REQUEST);
        }

        return new JsonResponse($result->toArray());
    }
}
