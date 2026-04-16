<?php

/**
 * Third-party service credentials configuration.
 */
return [

    'whatsapp' => [
        'account_id' => env('WHATSAPP_ACCOUNT_ID'),
        'token' => env('WHATSAPP_TOKEN'),
        'verify_token' => env('WHATSAPP_VERIFY_TOKEN', 'default_verify_token'),
        'phone_number_id' => env('WHATSAPP_PHONE_NUMBER_ID'),
        'api_version' => env('WHATSAPP_API_VERSION', 'v21.0'),
        'base_url' => 'https://graph.facebook.com',
    ],

    'anthropic' => [
        'api_key' => env('ANTHROPIC_API_KEY'),
        'model' => env('ANTHROPIC_MODEL', 'claude-sonnet-4-20250514'),
        'api_version' => '2023-06-01',
        'base_url' => 'https://api.anthropic.com',
    ],

    'stripe' => [
        'secret' => env('STRIPE_SECRET_KEY'),
        'publishable' => env('STRIPE_PUBLISHABLE_KEY'),
        'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
    ],

];
