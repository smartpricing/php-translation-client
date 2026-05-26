# Smartness Translation Client

[![Latest Version on Packagist](https://img.shields.io/packagist/v/smartness/translation-client.svg?style=flat-square)](https://packagist.org/packages/smartness/translation-client)
[![Total Downloads](https://img.shields.io/packagist/dt/smartness/translation-client.svg?style=flat-square)](https://packagist.org/packages/smartness/translation-client)

A Laravel package to synchronize translations between your Laravel application and a centralized translation management system. Pull translations from the server or push local translations back to keep everything in sync.

## Features

- 🚀 **One Command Install** - Get started in seconds
- 🔄 **Bi-directional Sync** - Pull and push translations with simple commands
- 🌍 **Multi-language** - Support for all languages
- 📦 **Laravel Compliant** - Generates proper Laravel translation files with nested arrays
- ⚡ **CI/CD Ready** - Perfect for automated deployments
- 🔒 **Secure** - API token authentication
- 🎯 **Smart Filtering** - Filter by language, status, or specific files
- ⬆️ **Push Support** - Send local translations back to the server
- 🔎 **Source-Code Sync** - `translations:missing` and `translations:cleanup` reconcile the catalog with the actual `$t()` / `trans()` / `__()` / `@lang` usage in your code
- 🏠 **Centralized Scan Config** - Scan settings live on the project so every developer uses the same patterns (local `.env` still wins)

## Requirements

- PHP 8.1 or higher
- Laravel 10, 11, 12, or 13

## Installation

Install via Composer:

```bash
composer require smartness/translation-client
```

The package will automatically register itself via Laravel's package auto-discovery.

## Configuration

### Step 1: Publish Configuration (Optional)

```bash
php artisan vendor:publish --tag=translation-client-config
```

This creates a `config/translation-client.php` file where you can customize settings.

### Step 2: Configure Environment Variables

Add these variables to your `.env` file:

```env
# Required: Your API token
TRANSLATION_API_TOKEN=your_api_token_here

# Required: API endpoint URL
TRANSLATION_API_URL=https://your-translation-service.com/api

# Optional: Override default settings
TRANSLATION_OUTPUT_DIR=  # Default: lang_path()
TRANSLATION_FORMAT=php   # Options: php, json, raw
TRANSLATION_STATUS=approved  # Filter: approved, pending, rejected
TRANSLATION_TIMEOUT=30   # HTTP timeout in seconds

# Optional: Source-scanning overrides (used by translations:cleanup and translations:missing).
# These are fetched from the server's project config by default, so most teams
# don't need to set them. Local values always override the server config.
SMARTPMS_TRANSLATION_SCAN_DIRS="resources,app"
SMARTPMS_TRANSLATION_SCAN_EXTENSIONS="php,blade.php,ts,tsx,js,jsx,vue"
SMARTPMS_TRANSLATION_KEY_PATTERN=
SMARTPMS_TRANSLATION_PREFIX_PATTERN=
```

**Note:** You'll receive your API token and endpoint URL from your translation service administrator.

## Usage

### Pulling Translations (Download)

Pull all translations from the server:

```bash
php artisan translations:pull
```

Pull translations for a specific language:

```bash
php artisan translations:pull --language=en
```

Preview changes without saving (dry-run):

```bash
php artisan translations:pull --dry-run
```

Test API connection:

```bash
php artisan translations:pull --test
```

#### Advanced Pull Options

```bash
# Override format for this pull
php artisan translations:pull --format=json

# Override status filter
php artisan translations:pull --status=approved

# Combine multiple options
php artisan translations:pull --language=de --status=approved --dry-run
```

### Pushing Translations (Upload)

Push all local translations to the server:

```bash
php artisan translations:push
```

Push translations for a specific language:

```bash
php artisan translations:push --language=en
```

Push a specific translation file:

```bash
php artisan translations:push --language=en --file=messages
```

Preview without actually pushing:

```bash
php artisan translations:push --dry-run
```

Overwrite existing translations on the server:

```bash
php artisan translations:push --overwrite
```

Use a custom translation directory:

```bash
php artisan translations:push --dir=/path/to/translations
```

#### Advanced Push Options

```bash
# Combine multiple options
php artisan translations:push --language=en --file=auth --overwrite

# Preview what will be pushed
php artisan translations:push --dry-run --language=de
```

### Reconciling the Catalog with Source Code

These two commands scan your local source for `$t('…')`, `useTranslate('…')`, `i18n.t('…')`, `trans('…')`, `__('…')` and `@lang('…')` calls and reconcile what they find with what the server stores.

Both commands first fetch the project's central scan configuration from `GET /translation-projects/config`. Local config (`config/translation-client.php` or `SMARTPMS_TRANSLATION_*` env vars) always wins; the server values are a shared default; package defaults are used if neither is set. The resolved config is printed at the start of each run so you can verify which source provided each value.

#### `translations:missing` — Find Keys Used in Code but Absent Remotely

```bash
# Dry-run: report keys referenced in source that don't exist on the server.
php artisan translations:missing

# Create those keys as placeholder rows on the project's primary language
# (value=null, missing=true, is_new=true), so they show up in the New Keys UI.
php artisan translations:missing --insert
```

Sample output:

```
Scan configuration:
  scan_dirs        (server ) resources, app
  scan_extensions  (server ) php, blade.php, ts, tsx, vue
  key_pattern      (default) (?:^|[^\w$])(?:\$?t|useTranslat(?:e|ion)|i18n…
  prefix_pattern   (default) (?:^|[^\w$])(?:\$?t|useTranslat(?:e|ion)|i18n…

Scanned 412 files.
Found 837 unique used key(s).
Remote keys: 540
Used keys sent: 837
Missing keys: 12
  - dashboard.banner.welcome
  - dashboard.banner.dismiss
  …
```

#### `translations:cleanup` — Find Keys Stored Remotely but Unused in Code

```bash
# Dry-run: report stale keys.
php artisan translations:cleanup

# Actually delete them from the remote project.
php artisan translations:cleanup --delete
```

Dynamic keys built with template literals (e.g. `` $t(`amenities.${name}`) ``) contribute a static prefix that protects every matching remote key from deletion — no false positives when the key set is computed at runtime.

## Output Structure

The package creates translation files following Laravel's standard structure:

```
lang/
├── en/
│   ├── messages.php
│   ├── validation.php
│   └── auth.php
├── de/
│   ├── messages.php
│   ├── validation.php
│   └── auth.php
└── es/
    ├── messages.php
    ├── validation.php
    └── auth.php
```

### Example Generated File

```php
<?php

// lang/en/auth.php

return [
    'failed' => 'These credentials do not match our records.',
    'password' => 'The provided password is incorrect.',
    'throttle' => 'Too many login attempts. Please try again in :seconds seconds.',

    // Nested arrays for dot-notation keys
    'verification' => [
        'sent' => 'A fresh verification link has been sent to your email address.',
        'verified' => 'Your email address has been verified.',
    ],
];
```

## Programmatic Usage

You can use the Translation Client directly in your code:

```php
<?php

namespace App\Services;

use Smartness\TranslationClient\TranslationClient;

class TranslationSync
{
    public function __construct(
        protected TranslationClient $client
    ) {}

    // Pull translations
    public function syncTranslations(string $language): array
    {
        // Fetch translations as Laravel PHP arrays
        $response = $this->client->fetchAsPhp($language);

        return $response['data'];
    }

    public function syncAsJson(string $language): array
    {
        // Fetch translations as JSON format
        $response = $this->client->fetchAsJson($language);

        return $response['data'];
    }

    public function getRawTranslations(string $language): array
    {
        // Fetch raw format with metadata
        $response = $this->client->fetchRaw($language);

        return $response['data'];
    }

    // Push translations
    public function pushTranslations(array $translations, bool $overwrite = false): array
    {
        // Push all translations
        return $this->client->push($translations, [
            'overwrite' => $overwrite,
        ]);
    }

    public function pushLanguageTranslations(string $language, array $translations, bool $overwrite = false): array
    {
        // Push translations for a specific language
        return $this->client->pushLanguage($language, $translations, $overwrite);
    }

    public function pushFileTranslations(string $language, string $filename, array $translations): array
    {
        // Push a specific translation file
        return $this->client->pushFile($language, $filename, $translations);
    }

    public function verifyConnection(): bool
    {
        return $this->client->testConnection();
    }
}
```

### Available Methods

| Method | Description | Returns |
|--------|-------------|---------|
| `fetchAsPhp(?string $language)` | Fetch translations as nested PHP arrays (Laravel format) | `array` |
| `fetchAsJson(?string $language)` | Fetch translations as flat JSON structure | `array` |
| `fetchRaw(?string $language)` | Fetch raw format with full metadata | `array` |
| `fetch(array $options)` | Fetch with custom options | `array` |
| `push(array $translations, array $options)` | Push translations to the server | `array` |
| `pushLanguage(string $language, array $translations, bool $overwrite)` | Push translations for a specific language | `array` |
| `pushFile(string $language, string $filename, array $translations, bool $overwrite)` | Push a specific translation file | `array` |
| `fetchProjectConfig()` | Fetch the centralized scan config from the server. Returns `null` on failure so callers can fall back to local config. | `?array` |
| `cleanup(array $usedKeys, array $usedPrefixes, bool $delete)` | Report (and optionally delete) remote keys not referenced in source. | `array` |
| `discoverMissing(array $usedKeys, array $usedPrefixes, bool $insert)` | Report (and optionally insert) keys referenced in source but missing remotely. | `array` |
| `testConnection()` | Verify API connection and token | `bool` |

## CI/CD Integration

### GitHub Actions

Add this workflow to automatically sync translations:

```yaml
# .github/workflows/sync-translations.yml
name: Sync Translations

on:
  schedule:
    - cron: '0 */6 * * *'  # Every 6 hours
  workflow_dispatch:  # Allow manual trigger

jobs:
  sync-translations:
    runs-on: ubuntu-latest

    steps:
      - name: Checkout code
        uses: actions/checkout@v3

      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.3'

      - name: Install dependencies
        run: composer install --no-dev --optimize-autoloader

      - name: Pull translations
        env:
          TRANSLATION_API_TOKEN: ${{ secrets.TRANSLATION_API_TOKEN }}
          TRANSLATION_API_URL: ${{ secrets.TRANSLATION_API_URL }}
        run: php artisan translations:pull

      - name: Commit and push changes
        run: |
          git config user.name "GitHub Actions"
          git config user.email "actions@github.com"
          git add lang/
          if git diff --staged --quiet; then
            echo "No translation changes"
          else
            git commit -m "chore: update translations [skip ci]"
            git push
          fi
```

### GitLab CI

```yaml
# .gitlab-ci.yml
sync-translations:
  stage: deploy
  script:
    - composer install --no-dev --optimize-autoloader
    - php artisan translations:pull
    - git config user.name "GitLab CI"
    - git config user.email "ci@gitlab.com"
    - git add lang/
    - git diff --staged --quiet || git commit -m "chore: update translations [skip ci]"
    - git push https://${GITLAB_USER}:${GITLAB_TOKEN}@${CI_REPOSITORY_URL#*@}
  only:
    - schedules
  variables:
    TRANSLATION_API_TOKEN: $TRANSLATION_API_TOKEN
    TRANSLATION_API_URL: $TRANSLATION_API_URL
```

## Configuration Reference

All configuration options available in `config/translation-client.php`:

```php
return [
    // API endpoint (required)
    'api_url' => env('TRANSLATION_API_URL'),

    // API authentication token (required)
    'api_token' => env('TRANSLATION_API_TOKEN'),

    // Output directory for translation files
    // Default: lang_path() resolves to 'lang/' directory
    'output_dir' => env('TRANSLATION_OUTPUT_DIR', null),

    // Format: json, php, or raw
    'format' => env('TRANSLATION_FORMAT', 'php'),

    // Status filter: approved, pending, rejected, or null (all)
    'status_filter' => env('TRANSLATION_STATUS', 'approved'),

    // HTTP request timeout in seconds
    'timeout' => env('TRANSLATION_TIMEOUT', 30),
];
```

## Error Handling

The package provides clear, actionable error messages:

```bash
# Missing configuration
❌ API token not configured. Please set TRANSLATION_API_TOKEN in your .env file.

# Invalid credentials
❌ Authentication failed: Invalid API token. Please check your TRANSLATION_API_TOKEN configuration.

# Connection issues
❌ API error: Failed to connect to translation service: Connection timeout

# No translations found
⚠ No translations found matching the criteria.
```

## Troubleshooting

### "API token not configured"

**Solution:** Add your API token to `.env`:
```env
TRANSLATION_API_TOKEN=your_token_here
TRANSLATION_API_URL=https://your-service.com/api
```

### "Authentication failed"

**Solution:** Verify your API token is correct. Contact your translation service administrator if needed.

### "Connection timeout"

**Solutions:**
- Check your network connection
- Verify the API URL is correct
- Increase timeout: `TRANSLATION_TIMEOUT=60`

### Translations not updating

**Solutions:**
- Run with `--dry-run` to preview changes
- Check status filter: `--status=approved`
- Verify you have translations in the system

## Advanced Usage

### Custom Fetch Options

```php
use Smartness\TranslationClient\TranslationClient;

$client = app(TranslationClient::class);

$response = $client->fetch([
    'format' => 'php',
    'language' => 'en',
    'status' => 'approved',
    'missing' => false,
    'filename' => 'messages',
]);
```

### Custom Output Directory

```env
# Save to different directory
TRANSLATION_OUTPUT_DIR=/path/to/custom/lang
```

### Multiple Environments

```env
# Development
TRANSLATION_API_URL=https://dev-translation-service.com/api

# Production
TRANSLATION_API_URL=https://translation-service.com/api
```

## Security

- ✅ API token authentication
- ✅ HTTPS required for API communication
- ✅ Token validation before requests
- ✅ No sensitive data in logs

**Important:** Never commit your API token to version control. Always use environment variables.

## Support

For issues, questions, or feature requests:
- **Email:** dev@smartpricing.com
- **Issues:** [GitHub Issues](https://github.com/smartness/translation-client/issues)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.

## Credits

- [Smartness Team](https://github.com/smartness)
- [All Contributors](../../contributors)
