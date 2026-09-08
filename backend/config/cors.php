<?php

declare(strict_types=1);

/*
 * CORS para la API. El renderer (Nuxt) y el admin (Vue) consumen /api/*. Con
 * tokens Bearer no se usan cookies, así que no se requieren credenciales.
 * MVP: se permiten todos los orígenes en /api/*; en producción se restringe a
 * los dominios del admin y del renderer.
 */

return [
    'paths' => ['api/*'],
    'allowed_methods' => ['*'],
    'allowed_origins' => ['*'],
    'allowed_origins_patterns' => [],
    'allowed_headers' => ['*'],
    'exposed_headers' => ['ETag'],
    'max_age' => 0,
    'supports_credentials' => false,
];
