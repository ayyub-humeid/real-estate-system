<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Feature Flags
    |--------------------------------------------------------------------------
    |
    | Feature flags allow enabling/disabling modules without code changes.
    | Use config('features.key') or the Feature::enabled('key') helper.
    |
    */

    'advertisements' => env('FEATURE_ADVERTISEMENTS', false),

    // Future feature flags can be added here:
    // 'advanced_reports' => env('FEATURE_ADVANCED_REPORTS', false),
    // 'mobile_app_api' => env('FEATURE_MOBILE_APP_API', false),
];