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

];
