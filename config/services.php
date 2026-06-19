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

    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => env('GOOGLE_REDIRECT_URI'),
    ],

    'sepay' => [
        'webhook_api_key' => env('SEPAY_WEBHOOK_API_KEY'),
        'api_token' => env('SEPAY_API_TOKEN'),
        'api_base_url' => env('SEPAY_API_BASE_URL', 'https://userapi.sepay.vn/v2'),
        'qr_base_url' => env('SEPAY_QR_BASE_URL', 'https://qr.sepay.vn/img'),
    ],

    'vietqr' => [
        'banks_url' => env('VIETQR_BANKS_URL', 'https://api.vietqr.io/v2/banks'),
        'lookup_url' => env('VIETQR_LOOKUP_URL', 'https://api.vietqr.io/v2/lookup'),
        'client_id' => env('VIETQR_CLIENT_ID'),
        'api_key' => env('VIETQR_API_KEY'),
    ],

];
