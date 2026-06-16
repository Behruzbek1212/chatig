<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Business types (preset, shown in registration)
    |--------------------------------------------------------------------------
    */
    'business_types' => [
        'elektronika',
        'kiyim',
        'kosmetika',
        'oziq_ovqat',
        'aksessuar',
        'maishiy_texnika',
        'boshqa',
    ],

    /*
    |--------------------------------------------------------------------------
    | Trial
    |--------------------------------------------------------------------------
    */
    'trial_days' => 14,

    /*
    |--------------------------------------------------------------------------
    | OTP settings
    |--------------------------------------------------------------------------
    */
    'otp' => [
        'length' => 6,
        'ttl_seconds' => 300,          // 5 minutes
        'max_attempts' => 5,           // verify attempts per code
        'resend_cooldown' => 30,       // seconds between sends
        'max_per_hour' => 5,           // sends per phone per hour
    ],

    /*
    |--------------------------------------------------------------------------
    | SMS
    |--------------------------------------------------------------------------
    */
    'sms' => [
        'driver' => env('SMS_DRIVER', 'log'), // log | eskiz
        'eskiz' => [
            'base_url' => env('ESKIZ_BASE_URL', 'https://notify.eskiz.uz/api'),
            'email' => env('ESKIZ_EMAIL'),
            'password' => env('ESKIZ_PASSWORD'),
            'from' => env('ESKIZ_FROM', '4546'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | OpenAI / LLM model ids
    |--------------------------------------------------------------------------
    */
    'llm' => [
        'driver' => env('LLM_DRIVER', 'openai'), // openai | fake
        'api_key' => env('OPENAI_API_KEY'),
        'models' => [
            'sales' => env('OPENAI_MODEL_SALES', 'gpt-4o'),
            'intent' => env('OPENAI_MODEL_INTENT', 'gpt-4o-mini'),
            'prompt_generator' => env('OPENAI_MODEL_PROMPT', 'gpt-4o'),
        ],
        'embedding' => [
            'model' => env('OPENAI_EMBEDDING_MODEL', 'text-embedding-3-small'),
            'dimensions' => (int) env('OPENAI_EMBEDDING_DIMENSIONS', 1536),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Instagram (Instagram API with Instagram Login)
    |--------------------------------------------------------------------------
    | Direct Instagram login flow — users authenticate with their Instagram
    | (Business/Creator) account, no Facebook Page required. Credentials are
    | the Instagram App ID / Secret from the "Instagram API with Instagram
    | login" product. All Graph calls go to graph.instagram.com.
    */
    'instagram' => [
        'app_id' => env('INSTAGRAM_APP_ID'),
        'app_secret' => env('INSTAGRAM_APP_SECRET'),
        'redirect_uri' => env('INSTAGRAM_REDIRECT_URI'),
        'graph_version' => env('INSTAGRAM_GRAPH_VERSION', 'v23.0'),
        'verify_token' => env('INSTAGRAM_WEBHOOK_VERIFY_TOKEN'),
        'scopes' => [
            'instagram_business_basic',
            'instagram_business_manage_messages',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Telegram (shared platform bot + Mini App)
    |--------------------------------------------------------------------------
    | One platform bot opens the catalog Mini App for every store via deep
    | link t.me/<bot_username>/app?startapp=<store_public_id>. The Mini App
    | authenticates customers by verifying Telegram WebApp initData (HMAC with
    | the bot token). There is NO Telegram AI chat yet — Mini App only.
    */
    'telegram' => [
        'bot_username' => env('TELEGRAM_BOT_USERNAME'),
        'mini_app_bot_token' => env('TELEGRAM_MINI_APP_BOT_TOKEN'),
        'mini_app_url' => env('TELEGRAM_MINI_APP_URL', 'http://localhost:5174'),
        'init_data_max_age' => (int) env('TELEGRAM_INIT_DATA_MAX_AGE', 3600), // seconds
    ],

    /*
    |--------------------------------------------------------------------------
    | Frontend SPA
    |--------------------------------------------------------------------------
    */
    'spa_url' => env('SPA_URL', 'http://localhost:5173'),

    /*
    |--------------------------------------------------------------------------
    | Media storage disk (product images)
    |--------------------------------------------------------------------------
    | 'public' = local filesystem (default for now). Switch to 'minio' when the
    | MinIO/S3 bucket is ready — no code changes needed.
    */
    'media_disk' => env('MEDIA_DISK', 'public'),
];
