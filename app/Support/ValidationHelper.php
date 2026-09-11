<?php

namespace App\Support;

use Illuminate\Support\MessageBag;
use Illuminate\Validation\ValidationException;

class ValidationHelper
{
    public static function exception(array $errors): ValidationException
    {
        return ValidationException::withMessages($errors);
    }

    public static function formatErrors(MessageBag|array|null $errors, string $fallbackMessage = 'The given data was invalid.'): ?array
    {
        if ($errors === null) {
            return null;
        }

        $fieldErrors = $errors instanceof MessageBag ? $errors->toArray() : $errors;
        $firstKey = array_key_first($fieldErrors);
        $firstMessages = $firstKey === null ? [] : ($fieldErrors[$firstKey] ?? []);
        $firstMessage = is_array($firstMessages) ? ($firstMessages[0] ?? $fallbackMessage) : $firstMessages;

        return [
            'summary' => $firstMessage ?? $fallbackMessage,
            'first_key' => $firstKey,
            'first_message' => $firstMessage ?? $fallbackMessage,
            'fields' => $fieldErrors,
        ];
    }
}
