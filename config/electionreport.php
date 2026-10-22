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

    'hash_key' => env('ELECTIONREPORT_HASH_KEY'),

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

    'mode' => env('ELECTIONREPORT_MODE', 'test'),

    /*
    |--------------------------------------------------------------------------
    | Feedback URL
    |--------------------------------------------------------------------------
    |
    | The form testers fill in, such as a Tally form. It is embedded on the
    | feedback page, which the test mode banner links to when this is set.
    |
    */

    'feedback_url' => env('ELECTIONREPORT_FEEDBACK_URL'),

    /*
    |--------------------------------------------------------------------------
    | Result Sheet Disk
    |--------------------------------------------------------------------------
    |
    | The filesystem disk that holds photos of polling unit result sheets.
    | Result sheets are public documents, so the disk must serve files
    | publicly: "public" locally, the Laravel Cloud bucket in production.
    |
    */

    'result_sheet_disk' => env('ELECTIONREPORT_RESULT_SHEET_DISK', 'public'),

];
