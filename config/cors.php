<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | السماح لـ Next.js (localhost:3000) بإرسال طلبات إلى Laravel API
    |
    */

    'paths' => ['api/*'],

    'allowed_methods' => ['*'],

    'allowed_origins' => [
        'http://localhost:3000',
        'http://localhost:5173',
        'http://10.10.10.35:3000',
        'https://real-state-frontend-with-nextjs-meo.vercel.app',
        'https://real-state-frontend-with-nextjs.vercel.app' // added this one too just in case
    ],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => false,

];
