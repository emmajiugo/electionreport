<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Hash Key
    |--------------------------------------------------------------------------
    |
    | Secret used to HMAC voter identification numbers and IP addresses. It is
    | deliberately separate from APP_KEY: rotating it breaks duplicate VIN
    | detection for every report already stored, so never rotate mid-election.
    |
    */

    'hash_key' => env('ELECTIONWATCH_HASH_KEY'),

    /*
    |--------------------------------------------------------------------------
    | Mode
    |--------------------------------------------------------------------------
    |
    | "test" lets the public try the app before the election: every page shows
    | a TEST badge and reports are kept in the test_reports table. Set "live"
    | for the election itself. Any other value is treated as "test".
    |
    */

    'mode' => env('ELECTIONWATCH_MODE', 'test'),

    /*
    |--------------------------------------------------------------------------
    | Feedback URL
    |--------------------------------------------------------------------------
    |
    | Where testers send feedback, such as a form or a WhatsApp link. Shown in
    | the test mode banner when set.
    |
    */

    'feedback_url' => env('ELECTIONWATCH_FEEDBACK_URL'),

];
