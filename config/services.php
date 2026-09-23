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

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],
    
    'httpsms' => [
        'base_url' => env('HTTPSMS_BASE_URL', 'https://abdaa.tiyar.cc/v1'),
        'api_key'  => env('HTTPSMS_API_KEY'),
        'sim'      => env('HTTPSMS_DEFAULT_SIM', 'SIM2'),
        'from_phone' => env('HTTPSMS_FROM_PHONE', '+967781152674'),
    ],

    'evolution' => [
        'url'           => env('EVOLUTION_API_URL', 'http://127.0.0.1:8080'),
        'instance_name' => env('EVOLUTION_INSTANCE_NAME', 'awad'),
        'api_key'       => env('EVOLUTION_API_KEY', ''),
    ],

    'gemini' => [
        'api_key' => env('GEMINI_API_KEY', ''),
    ],

];
