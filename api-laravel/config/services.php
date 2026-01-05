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
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'browsershot' => [
        'chrome_path' => env('BROWSERSHOT_CHROME_PATH'),
        'node_path' => env('BROWSERSHOT_NODE_PATH'),
        'disable_sandbox' => env('BROWSERSHOT_DISABLE_SANDBOX', true),
    ],

    'celesc' => [
        'base_url' => env('CELESC_BASE_URL', 'https://conecte.celesc.com.br/graphql'),
        'auth_url' => env('CELESC_AUTH_URL', 'https://conecte.celesc.com.br/auth/login'),
        'token' => env('CELESC_TOKEN'),
        'refresh_token' => env('CELESC_REFRESH_TOKEN'),
        'channel' => env('CELESC_CHANNEL', 'ZAW'),
        'username' => env('CELESC_USERNAME'),
        'password' => env('CELESC_PASSWORD'),
        'cookies' => env('CELESC_COOKIES'),
    ],

    'node' => [
      'binary' => env('NODE_BINARY', 'node'),
    ],
];
