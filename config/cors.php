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

    'paths' => ['*'],

    'allowed_methods' => ['*'],

    'allowed_origins' => [
        'http://localhost:3000',
        'http://localhost:5173',
        'http://10.10.10.35:3000',
        'https://real-state-frontend-with-nextjs-meo.vercel.app',
        'https://real-state-frontend-with-nex-git-b2de8e-ayyub-humeid-s-projects.vercel.app',
        'https://real-state-frontend-with-nextjs-meoj-o9yen8oeg.vercel.app'
    ],

    'allowed_origins_patterns' => [
        '#^https://.*\.vercel\.app$#',
    ],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => false,

];
