<?php

return [

    /*
    |--------------------------------------------------------------------------
    | SmartPMS Translation API URL
    |--------------------------------------------------------------------------
    |
    | The base URL for the SmartPMS Translation API.
    |
    */

    'api_url' => env('SMARTPMS_TRANSLATION_API_URL', 'https://pms-intool.smartness.com/api'),

    /*
    |--------------------------------------------------------------------------
    | SmartPMS Translation API Token
    |--------------------------------------------------------------------------
    |
    | Your project's API token from SmartPMS Translation Manager.
    | You can generate this in the project settings.
    |
    */

    'api_token' => env('SMARTPMS_TRANSLATION_TOKEN'),

    /*
    |--------------------------------------------------------------------------
    | Output Directory
    |--------------------------------------------------------------------------
    |
    | The directory where translations will be saved.
    | Default: lang_path() which resolves to lang/ directory
    |
    */

    'output_dir' => env('SMARTPMS_TRANSLATION_OUTPUT_DIR', null),

    /*
    |--------------------------------------------------------------------------
    | Format
    |--------------------------------------------------------------------------
    |
    | The format to fetch translations in.
    | Options: json, php, raw
    |
    */

    'format' => env('SMARTPMS_TRANSLATION_FORMAT', 'php'),

    /*
    |--------------------------------------------------------------------------
    | Status Filter
    |--------------------------------------------------------------------------
    |
    | Filter translations by status.
    | Options: approved, pending, rejected, null (all)
    |
    */

    'status_filter' => env('SMARTPMS_TRANSLATION_STATUS', 'approved'),

    /*
    |--------------------------------------------------------------------------
    | HTTP Timeout
    |--------------------------------------------------------------------------
    |
    | HTTP request timeout in seconds.
    |
    */

    'timeout' => env('SMARTPMS_TRANSLATION_TIMEOUT', 30),

];
