# Smartness Translation Client

[![Latest Version on Packagist](https://img.shields.io/packagist/v/smartness/translation-client.svg?style=flat-square)](https://packagist.org/packages/smartness/translation-client)
[![Total Downloads](https://img.shields.io/packagist/dt/smartness/translation-client.svg?style=flat-square)](https://packagist.org/packages/smartness/translation-client)

A Laravel package to easily pull translations from SmartPMS Translation Manager into your Laravel application.

## Features

- 🚀 **One Command Install** - Get started in seconds
- 🔄 **Auto-sync** - Pull translations with a single command
- 🌍 **Multi-language** - Support for all languages
- 📦 **Laravel Compliant** - Generates proper Laravel translation files
- ⚡ **CI/CD Ready** - Perfect for automated deployments
- 🔒 **Secure** - API token authentication

## Requirements

- PHP 8.1 or higher
- Laravel 10, 11, or 12

## Installation

Install via Composer:

```bash
composer require smartness/translation-client
```

The package will automatically register itself.

## Configuration

### 1. Publish the configuration file (optional):

```bash
php artisan vendor:publish --tag=translation-client-config
```

### 2. Add your API token to `.env`:

```env
SMARTPMS_TRANSLATION_TOKEN=your_api_token_here
```

You can get your API token from the SmartPMS Translation Manager project settings.

### 3. (Optional) Customize the API URL:

```env
SMARTPMS_TRANSLATION_API_URL=https://pms-intool.smartness.com/api
```

## Usage

### Pull Translations

Pull all translations:

```bash
php artisan translations:pull
```

Pull translations for specific language:

```bash
php artisan translations:pull --language=en
```

Preview without saving (dry-run):

```bash
php artisan translations:pull --dry-run
```

Test API connection:

```bash
php artisan translations:pull --test
```

### Advanced Options

```bash
# Override format
php artisan translations:pull --format=json

# Override status filter
php artisan translations:pull --status=approved

# Combine options
php artisan translations:pull --language=de --status=approved --dry-run
```

## Output

The package creates translation files in Laravel's standard structure:

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

### Example Output File

```php
<?php

// lang/en/auth.php

return [
    'failed' => 'These credentials do not match our records.',
    'password' => 'The provided password is incorrect.',
    'throttle' => 'Too many login attempts. Please try again in :seconds seconds.',
];
```

## Programmatic Usage

You can also use the Translation Client programmatically in your code:

```php
use Smartness\TranslationClient\TranslationClient;

class YourService
{
    public function __construct(
        protected TranslationClient $client
    ) {}

    public function pullTranslations()
    {
        // Fetch all translations as PHP arrays
        $response = $this->client->fetchAsPhp();

        // Fetch specific language
        $response = $this->client->fetchAsPhp('en');

        // Fetch as JSON format
        $response = $this->client->fetchAsJson('en');

        // Fetch raw format
        $response = $this->client->fetchRaw();

        // Test connection
        if ($this->client->testConnection()) {
            // Connection is working
        }
    }
}
```

## CI/CD Integration

### GitHub Actions

```yaml
name: Pull Translations

on:
  schedule:
    - cron: '0 */6 * * *'  # Every 6 hours
  workflow_dispatch:

jobs:
  pull-translations:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v3

      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.3'

      - name: Install dependencies
        run: composer install --no-dev

      - name: Pull translations
        env:
          SMARTPMS_TRANSLATION_TOKEN: ${{ secrets.SMARTPMS_TRANSLATION_TOKEN }}
        run: php artisan translations:pull

      - name: Commit changes
        run: |
          git config user.name "GitHub Actions"
          git config user.email "actions@github.com"
          git add lang/
          git diff --staged --quiet || git commit -m "chore: update translations"
          git push
```

### GitLab CI

```yaml
translations:
  stage: deploy
  script:
    - composer install --no-dev
    - php artisan translations:pull
    - git add lang/
    - git commit -m "chore: update translations" || true
    - git push
  only:
    - schedules
  variables:
    SMARTPMS_TRANSLATION_TOKEN: $SMARTPMS_TRANSLATION_TOKEN
```

## Configuration Options

You can customize the package behavior in `config/translation-client.php`:

```php
return [
    // API URL
    'api_url' => env('SMARTPMS_TRANSLATION_API_URL', 'https://pms-intool.smartness.com/api'),

    // Your API token
    'api_token' => env('SMARTPMS_TRANSLATION_TOKEN'),

    // Output directory (default: lang_path())
    'output_dir' => env('SMARTPMS_TRANSLATION_OUTPUT_DIR', null),

    // Format: json, php, or raw
    'format' => env('SMARTPMS_TRANSLATION_FORMAT', 'php'),

    // Status filter: approved, pending, rejected, or null
    'status_filter' => env('SMARTPMS_TRANSLATION_STATUS', 'approved'),

    // HTTP timeout in seconds
    'timeout' => env('SMARTPMS_TRANSLATION_TIMEOUT', 30),
];
```

## Error Handling

The package provides clear error messages:

```bash
# Missing API token
❌ API token not configured. Please set SMARTPMS_TRANSLATION_TOKEN in your .env file.

# Invalid token
❌ Authentication failed: Invalid API token. Please check your SMARTPMS_TRANSLATION_TOKEN configuration.

# Connection issues
❌ API error: Failed to connect to SmartPMS API: Connection timeout
```

## Testing

```bash
composer test
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

## Security

If you discover any security related issues, please email dev@smartpricing.com instead of using the issue tracker.

## Credits

- [SmartPricing Team](https://github.com/smartpricing)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.

## Support

For support, email dev@smartpricing.com or visit https://smartpricing.com
