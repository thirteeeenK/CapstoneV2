<?php

return [

    'turnstile' => [
        'site_key' => env('TURNSTILE_SITE_KEY'),
        'secret_key' => env('TURNSTILE_SECRET_KEY'),
        'hostname' => env('TURNSTILE_EXPECTED_HOSTNAME'),
    ],
    'trip_journey' => ['enabled' => env('TRIP_JOURNEY_ENABLED', false)],

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
        // A leading-slash value is resolved against APP_URL by Socialite, so the
        // same default works on localhost and in production without an override.
        // `?:` rather than an env() default, because a present-but-blank
        // GOOGLE_REDIRECT_URI= line yields '' and would skip the default.
        'redirect' => env('GOOGLE_REDIRECT_URI') ?: '/auth/google/callback',
        'reauth_redirect' => env('GOOGLE_REAUTH_REDIRECT_URI') ?: '/account-security/google/callback',
    ],

    'gemini' => [
        'api_key' => env('GEMINI_API_KEY'),
        'embedding_model' => env('EMBEDDING_MODEL', 'models/text-embedding-001'),
        'chat_model' => env('GEMINI_CHAT_MODEL', 'models/gemini-2.5-flash-lite'),
        'judge_model' => env('GEMINI_JUDGE_MODEL', 'models/gemini-3.1-pro-preview'),
        'review_summary_threshold' => (int) env('REVIEW_SUMMARY_THRESHOLD', 3),
        'chat_context_cache' => env('GEMINI_CHAT_CONTEXT_CACHE', true),
        // Cross-device consistency: grounded factual answers use the grounded
        // temperature, general chat uses the conversational one.
        'chat_grounded_temperature' => (float) env('GEMINI_CHAT_GROUNDED_TEMP', 0),
        'chat_conversational_temperature' => (float) env('GEMINI_CHAT_CONVERSATIONAL_TEMP', 0.2),
    ],

    'groq' => [
        'key' => env('GROQ_API_KEY'),
        'chat_model' => env('GROQ_CHAT_MODEL', 'openai/gpt-oss-120b'),
        'base_url' => env('GROQ_BASE_URL', 'https://api.groq.com/openai/v1'),
    ],

    'payment' => [
        // Default resolution does not hide or replace explicitly selected methods.
        'provider' => env('PAYMENT_PROVIDER', 'auto'),
        'mode' => env('PAYMENT_MODE'),
    ],

    'stripe' => [
        'test_secret_key' => env('STRIPE_TEST_SECRET_KEY'),
        'test_webhook_secret' => env('STRIPE_TEST_WEBHOOK_SECRET'),
        'live_secret_key' => env('STRIPE_LIVE_SECRET_KEY'),
        'live_webhook_secret' => env('STRIPE_LIVE_WEBHOOK_SECRET'),
        'secret_key' => env('STRIPE_SECRET_KEY'),
        'publishable_key' => env('STRIPE_PUBLISHABLE_KEY'),
        'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
    ],

    'qrph' => [
        'mode' => 'test',
        'secret_key' => env('PAYMONGO_TEST_SECRET_KEY') ?: env('PAYMONGO_SECRET_KEY'),
        'webhook_secret' => env('PAYMONGO_TEST_WEBHOOK_SECRET'),
        'expiry_seconds' => 1800,
    ],

    'paymongo' => [
        'mode' => 'live',
        'secret_key' => env('PAYMONGO_LIVE_SECRET_KEY') ?: env('PAYMONGO_SECRET_KEY'),
        'webhook_secret' => env('PAYMONGO_WEBHOOK_SECRET'),
        'webhook_url' => env('PAYMONGO_WEBHOOK_URL'),
        'expiry_seconds' => 1800,
    ],

    'gotyme' => [
        'qr_image' => env('GOTYME_QR_IMAGE', 'images/qr_gotyme.jpg'),
        'account_name' => env('GOTYME_ACCOUNT_NAME'),
        'account_number' => env('GOTYME_ACCOUNT_NUMBER'),
    ],

    'openweather' => [
        'api_key' => env('OPENWEATHER_API_KEY'),
        'base_url' => env('OPENWEATHER_BASE_URL', 'https://api.openweathermap.org/data/2.5'),
    ],

    'routing' => [
        // auto = OpenRouteService when its key is set, otherwise the self-hosted Valhalla.
        'provider' => env('ROUTING_PROVIDER', 'auto'),
        'base_url' => env('VALHALLA_URL'),
        'ors_api_key' => env('OPENROUTESERVICE'),
        'ors_base_url' => env('ORS_BASE_URL', 'https://api.openrouteservice.org'),
        'default_mode' => env('ROUTING_DEFAULT_MODE', 'walking'),
        'graph_version' => env('ROUTING_GRAPH_VERSION', 'western-visayas-v1'),
        'timeout' => (int) env('ROUTING_TIMEOUT', 10),
        'cache_minutes' => (int) env('ROUTING_CACHE_MINUTES', 1440),
        'matrix_batch_size' => (int) env('ROUTING_MATRIX_BATCH_SIZE', 25),
        'snap_radius_meters' => (int) env('ROUTING_SNAP_RADIUS_METERS', 500),
        // OpenRouteService refuses a point further than its snapping radius from a
        // mapped way. Beach landings and island meeting points fail that test, so a
        // second attempt with unlimited snapping keeps the leg drawable.
        'snap_retry_unlimited' => (bool) env('ROUTING_SNAP_RETRY_UNLIMITED', true),
    ],

];
