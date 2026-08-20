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

    'soft_score' => [
        'base_url' => env('SOFT_SCORE_BASE_URL', 'https://prod.onpointapi.com'),
        'client_id' => env('SOFT_SCORE_CLIENT_ID'),
        'client_secret' => env('SOFT_SCORE_CLIENT_SECRET'),
        'freshness_days' => (int) env('SOFT_SCORE_FRESHNESS_DAYS', 15),
    ],

    'rnd' => [
        'base_url' => env('RND_BASE_URL', 'https://api.reassigned.us'),
        'refresh_token' => env('RND_REFRESH_TOKEN'),
        'company_id' => env('RND_COMPANY_ID'),
    ],

    'qualification' => [
        'instance_url' => env('SALESFORCE_INSTANCE_URL', 'https://onpointmrg--staging.sandbox.my.salesforce.com'),
        'client_id' => env('SALESFORCE_CLIENT_ID'),
        'client_secret' => env('SALESFORCE_CLIENT_SECRET'),
        'api_version' => env('SALESFORCE_API_VERSION', 'v64.0'),
        'freshness_days' => (int) env('QUALIFICATION_FRESHNESS_DAYS', 15),
    ],

    'dnc' => [
        'base_url' => env('DNC_BASE_URL', 'https://www.dncscrub.com'),
        'login_id' => env('DNC_LOGIN_ID'),
        'project_id' => env('DNC_PROJECT_ID', 'ONPNT'),
    ],

];
