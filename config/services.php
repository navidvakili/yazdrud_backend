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

    /*
    |--------------------------------------------------------------------------
    | IPPanel SMS Service
    |--------------------------------------------------------------------------
    |
    | Credentials for IPPanel (ippanel.com) REST API for sending SMS messages.
    | The verify_pattern is the pattern code used for sending verification codes.
    |
    */
    'ippanel' => [
        'api_key' => env('IPPANEL_API_KEY'),
        'sender_number' => env('IPPANEL_SENDER_NUMBER', '10003538264099'),
        'verify_pattern' => env('IPPANEL_VERIFY_PATTERN', 'b4gofrp80wrccix'),
        'enrollment_pattern' => env('IPPANEL_ENROLLMENT_PATTERN'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Public Site Locales Path
    |--------------------------------------------------------------------------
    |
    | Absolute path to the public site's `src/locales` directory (the Vite SPA
    | under ../public). When a new language is created, its `<code>.ts` file is
    | generated here from the Persian template (fa.ts). Override in .env via
    | PUBLIC_LOCALES_PATH when the folders live elsewhere.
    |
    */
    'public_locales_path' => env('PUBLIC_LOCALES_PATH', base_path('../public/src/locales')),

];
