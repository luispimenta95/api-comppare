<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may configure your settings for cross-origin resource sharing
    | or "CORS". This determines what cross-origin operations may execute
    | in web browsers. You are free to adjust these settings as needed.
    |
    | To learn more: https://developer.mozilla.org/en-US/docs/Web/HTTP/CORS
    |
    */

    'paths' => [
        'api/*',
        'sanctum/csrf-cookie',
    ],

    'allowed_methods' => ['*'],

    'allowed_origins' => [
        'http://localhost:3000',
        'http://localhost:3001',
        'http://localhost:8080',
        'http://localhost:8081',
        'https://comppare.com.br',
        'https://www.comppare.com.br',
        'https://app.comppare.com.br',
        'https://api.comppare.com.br',
        // Adicione outros domínios que precisam acessar sua API
    ],

    'allowed_origins_patterns' => [
        // Permite subdomínios do seu domínio principal
        '/^https?:\/\/.*\.comppare\.com\.br$/',
        // Permite localhost com qualquer porta para desenvolvimento
        '/^https?:\/\/localhost:\d+$/',
    ],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => true,
];
