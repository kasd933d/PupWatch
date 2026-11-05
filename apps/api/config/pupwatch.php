<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Check engine
    |--------------------------------------------------------------------------
    */

    // PRD §16 — identify PupWatch in every outbound request.
    'user_agent' => env('CHECK_USER_AGENT', 'PupWatch/1.0 (+https://pupwatch.dev/about)'),

    // Hard cap on how much of a response body is ever read (PRD §5.7).
    'max_body_bytes' => 1024 * 1024,

    // Redirect hops followed, each one fully re-validated (PRD §5.5).
    'max_redirects' => 5,

    // Public origin used in alert links.
    'app_url' => env('FRONTEND_URL', 'http://localhost:3000'),

    /*
    |--------------------------------------------------------------------------
    | Retention (PRD §4.9)
    |--------------------------------------------------------------------------
    */

    'retention' => [
        'check_results_days' => 30,
        'daily_stats_days' => 400,
    ],

    // How long a public status payload is cached, in seconds (PRD §4.8).
    'status_page_cache_seconds' => 60,

];
