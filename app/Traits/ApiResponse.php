<?php

namespace App\Traits;

use App\Support\ApiResponseHelper;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\MessageBag;

trait ApiResponse
{
    /**
     * Return a success JSON response.
     *
     * @param  mixed  $data
     */
    protected function successResponse($data = null, string $message = 'Success', int $code = 200): JsonResponse
    {
        return ApiResponseHelper::success($data, $message, $code);
    }

    /**
     * Return a paginated list in the standard envelope: data = { items, meta }.
     *
     * @param  class-string<JsonResource>  $resourceClass
     */
    protected function paginatedResponse(LengthAwarePaginator $paginator, string $resourceClass, string $message = 'Success'): JsonResponse
    {
        return ApiResponseHelper::success([
            'items' => $resourceClass::collection($paginator->getCollection())->resolve(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        ], $message);
    }

    /**
     * Return an error JSON response.
     *
     * @param  mixed  $errors
     */
    protected function errorResponse(string $message = 'Error', int $code = 400, MessageBag|array|null $errors = null): JsonResponse
    {
        return ApiResponseHelper::error($message, $code, $errors);
    }
}
