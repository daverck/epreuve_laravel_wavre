<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Configuration Cross-Origin Resource Sharing (CORS)
    |--------------------------------------------------------------------------
    | Autorise le Wallboard Angular (http://localhost:4200) et Swagger UI
    | à interroger l'API REST et le flux SSE sans blocage navigateur.
    */
    'paths' => ['api/*', 'up'],
    'allowed_methods' => ['*'],
    'allowed_origins' => ['*'],
    'allowed_origins_patterns' => [],
    'allowed_headers' => ['*'],
    'exposed_headers' => ['Content-Type', 'Cache-Control', 'X-Accel-Buffering'],
    'max_age' => 0,
    'supports_credentials' => false,
];
