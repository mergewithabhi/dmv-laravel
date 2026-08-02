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

    'newsletter' => [
        'driver' => env('NEWSLETTER_DRIVER', 'log'),
        'mailchimp' => [
            'api_key' => env('MAILCHIMP_API_KEY'),
            'list_id' => env('MAILCHIMP_LIST_ID'),
            'data_center' => env('MAILCHIMP_DATA_CENTER'),
        ],
        'brevo' => [
            'api_key' => env('BREVO_API_KEY'),
            'list_id' => env('BREVO_LIST_ID'),
        ],
    ],

    'turnstile' => [
        'enabled' => env('TURNSTILE_ENABLED', false),
        'site_key' => env('TURNSTILE_SITE_KEY'),
        'secret_key' => env('TURNSTILE_SECRET_KEY'),
        'hostname' => env('TURNSTILE_HOSTNAME'),
        'action' => env('TURNSTILE_ACTION'),
    ],

];
