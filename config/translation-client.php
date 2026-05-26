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

    'api_url' => env('TRANSLATION_API_URL', 'http://pms-intool.smartness.com/api'),

    /*
    |--------------------------------------------------------------------------
    | SmartPMS Translation API Token
    |--------------------------------------------------------------------------
    |
    | Your project's API token from SmartPMS Translation Manager.
    | You can generate this in the project settings.
    |
    */

    'api_token' => env('TRANSLATION_API_TOKEN'),

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
    | Push Status
    |--------------------------------------------------------------------------
    |
    | The status to assign to translations when pushing.
    | Options: reviewed, approved, pending
    |
    */

    'push_status' => env('SMARTPMS_TRANSLATION_PUSH_STATUS', 'reviewed'),

    /*
    |--------------------------------------------------------------------------
    | HTTP Timeout
    |--------------------------------------------------------------------------
    |
    | HTTP request timeout in seconds.
    |
    */

    'timeout' => env('SMARTPMS_TRANSLATION_TIMEOUT', 30),

    /*
    |--------------------------------------------------------------------------
    | Source-code scanning (cleanup / missing commands)
    |--------------------------------------------------------------------------
    |
    | These settings are used by the `translations:cleanup` and
    | `translations:missing` commands. Each may also be configured centrally on
    | the SmartPMS project (Settings → Source-Code Scanning) — when both a
    | local value and a server value exist, the local one wins.
    |
    | Leave any value null/empty to fall back to the server config, then to the
    | built-in defaults shown below.
    |
    */

    'scan_dirs' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('SMARTPMS_TRANSLATION_SCAN_DIRS', '')),
    ))) ?: null,

    'scan_extensions' => array_values(array_filter(array_map(
        fn ($v) => strtolower(ltrim(trim((string) $v), '.')),
        explode(',', (string) env('SMARTPMS_TRANSLATION_SCAN_EXTENSIONS', '')),
    ))) ?: null,

    'scan_key_pattern' => env('SMARTPMS_TRANSLATION_KEY_PATTERN'),

    'scan_prefix_pattern' => env('SMARTPMS_TRANSLATION_PREFIX_PATTERN'),

    /*
    |--------------------------------------------------------------------------
    | Default scan patterns
    |--------------------------------------------------------------------------
    |
    | Used when neither this config nor the server config supplies a value.
    | The defaults cover Laravel + Vue + i18n usage:
    |   - $t('key'), t('key')
    |   - useTranslate('key'), useTranslation('key')
    |   - i18n.t('key'), i18n.feature.t('key')
    |   - trans('key'), __('key'), @lang('key')
    |
    */

    'default_scan_dirs' => ['./resources', './app'],

    'default_scan_extensions' => ['php', 'blade.php', 'ts', 'tsx', 'js', 'jsx', 'vue'],

    'default_key_pattern' => "(?:^|[^\\w$])(?:\\$?t|useTranslat(?:e|ion)|i18n(?:\\.\\w+)*\\.t|trans|__|@lang)\\s*\\(\\s*['\"`]([^'\"`\\n\\r]+?)['\"`]",

    'default_prefix_pattern' => "(?:^|[^\\w$])(?:\\$?t|useTranslat(?:e|ion)|i18n(?:\\.\\w+)*\\.t)\\s*\\(\\s*`([^`$]+)\\$\\{",

];
