<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    // Push to the mobile apps: FIREBASE_CREDENTIALS is the path to the service-account key file.
    'firebase' => [
        'project_id' => env('FIREBASE_PROJECT_ID'),
        'credentials' => env('FIREBASE_CREDENTIALS'),
    ],

    // SMS / WhatsApp provider HTTP API: URL with {to} {message} {sender}; GET or POST (form fields).
    'sms' => [
        'url' => env('SMS_GATEWAY_URL'),
        'method' => env('SMS_GATEWAY_METHOD', 'GET'),
        'token' => env('SMS_GATEWAY_TOKEN'),
        'sender' => env('SMS_GATEWAY_SENDER'),
    ],

    'whatsapp' => [
        'url' => env('WHATSAPP_GATEWAY_URL'),
        'method' => env('WHATSAPP_GATEWAY_METHOD', 'POST'),
        'token' => env('WHATSAPP_GATEWAY_TOKEN'),
        'sender' => env('WHATSAPP_GATEWAY_SENDER'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

];
