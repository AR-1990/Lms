<?php

namespace App\Support;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\MessageBag;

class ApiResponseHelper
{
    public static function success(mixed $data = null, string $message = 'Success', int $code = 200): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $data,
            'errors' => null,
        ], $code);
    }

    public static function error(string $message = 'Error', int $code = 400, MessageBag|array|null $errors = null, mixed $data = null): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'data' => $data,
            'errors' => ValidationHelper::formatErrors($errors, $message),
        ], $code);
    }

    public static function validation(MessageBag|array $errors, string $message = 'Validation failed.', int $code = 422): JsonResponse
    {
        return self::error($message, $code, $errors);
    }

    public static function unauthenticated(string $message = 'Unauthenticated.'): JsonResponse
    {
        return self::error($message, 401, [
            'auth' => ['You must be logged in to perform this action.'],
        ]);
    }

    public static function forbidden(string $message, string $key, string $detail): JsonResponse
    {
        return self::error($message, 403, [
            $key => [$detail],
        ]);
    }
}
