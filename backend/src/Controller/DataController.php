<?php

declare(strict_types=1);

namespace App\Controller;

use App\Optimizer\Input\InvalidInputException;
use App\Service\DataStore;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
final class DataController
{
    public function __construct(private readonly DataStore $store)
    {
    }

    #[Route('/api/data', name: 'data_get', methods: ['GET'])]
    public function get(): JsonResponse
    {
        try {
            return new JsonResponse($this->store->load());
        } catch (InvalidInputException $e) {
            return new JsonResponse(['error' => 'stored_data_invalid', 'details' => $e->errors], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/api/data', name: 'data_put', methods: ['PUT'])]
    public function put(Request $request): JsonResponse
    {
        try {
            [$employees, $tasks] = RequestPayload::employeesAndTasks($request, allowEmpty: false);

            return new JsonResponse($this->store->save($employees, $tasks));
        } catch (InvalidInputException $e) {
            return new JsonResponse(['error' => 'invalid_input', 'details' => $e->errors], Response::HTTP_BAD_REQUEST);
        } catch (\RuntimeException $e) {
            return new JsonResponse(['error' => 'write_failed', 'details' => [$e->getMessage()]], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
