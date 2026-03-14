<?php

$allowedOriginsFromEnv = array_values(array_unique(array_filter(array_map(
    static fn (string $origin): string => rtrim(trim($origin), '/'),
    explode(',', (string) env('CORS_ALLOWED_ORIGINS', ''))
), static fn (string $origin): bool => $origin !== '')));

if ($allowedOriginsFromEnv === []) {
    $allowedOriginsFromEnv = array_values(array_unique(array_filter(array_map(
        static fn (string $origin): string => rtrim(trim($origin), '/'),
        [
            'http://localhost:3000',
            'http://127.0.0.1:3000',
            (string) env('FRONTEND_URL', ''),
        ]
    ), static fn (string $origin): bool => $origin !== '')));
}

return [
    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    'allowed_origins' => $allowedOriginsFromEnv,

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => true,
];
