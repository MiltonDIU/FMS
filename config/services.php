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
    'anthropic' => [
        'api_key' => env('ANTHROPIC_API_KEY'),
    ],

    'diu_contacts_api' => env('DIU_CONTACTS_API', 'https://webbackend.daffodilvarsity.edu.bd/api/v1/public/department'),

    'integration' => [
        /*
         * Hosts the Integration Mapping screen may fetch from even though they
         * sit on a private network. Everything else resolving to a private or
         * reserved address is refused, so an operator cannot point the server at
         * internal services or a cloud metadata endpoint and read the response.
         *
         * The application's own host is always permitted, which is what lets the
         * default localhost integration URL keep working in development.
         *
         * Comma-separated, e.g. INTEGRATION_ALLOWED_HOSTS=erp.internal,10.0.0.5
         */
        'allowed_hosts' => array_filter(
            array_map('trim', explode(',', (string) env('INTEGRATION_ALLOWED_HOSTS', ''))),
        ),
    ],

];
