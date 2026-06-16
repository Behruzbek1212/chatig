<?php

return [

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    'allowed_origins' => array_filter([
        env('SPA_URL', 'http://localhost:5173'),
    ]),

    // Dev convenience: allow any localhost / 127.0.0.1 port (vite, etc.) and
    // ngrok tunnels (the Telegram Mini App is served from its own ngrok URL in
    // dev). Production locks down to the explicit SPA_URL above.
    'allowed_origins_patterns' => [
        '/^https?:\/\/(localhost|127\.0\.0\.1)(:\d+)?$/',
        '/^https:\/\/[a-z0-9-]+\.ngrok(-free)?\.app$/',
    ],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => false,
];
