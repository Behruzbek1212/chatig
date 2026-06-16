<?php

return [

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    'allowed_origins' => array_filter([
        env('SPA_URL', 'http://localhost:5173'),
    ]),

    // Dev convenience: allow any localhost / 127.0.0.1 port (vite, etc.).
    // Production locks down to the explicit SPA_URL above.
    'allowed_origins_patterns' => [
        '/^https?:\/\/(localhost|127\.0\.0\.1)(:\d+)?$/',
    ],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => false,
];
