<?php

namespace App\Traits;

use App\Support\ApiResponseHelper;
use Illuminate\Http\JsonResponse;
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
     * Return an error JSON response.
     *
     * @param  mixed  $errors
     */
    protected function errorResponse(string $message = 'Error', int $code = 400, MessageBag|array|null $errors = null): JsonResponse
    {
        return ApiResponseHelper::error($message, $code, $errors);
    }
}
