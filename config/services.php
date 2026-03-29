<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
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

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'tourcast' => [
        'base_url' => env('TOURCAST_BASE_URL', 'https://api.tourcast.io/v1'),
        'api_key'  => env('TOURCAST_API_KEY', ''),
        'timeout'  => (int) env('TOURCAST_TIMEOUT', 10),
        'retry'    => (int) env('TOURCAST_RETRY', 3),
    ],

    'chatbot_engine' => [
        'url'   => env('CHATBOT_ENGINE_URL', 'http://localhost:8001'),
        'token' => env('LARAVEL_SERVICE_TOKEN'),
    ],

];
