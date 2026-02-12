<?php

namespace Smartness\TranslationClient\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Smartness\TranslationClient\Exceptions\ApiException;
use Smartness\TranslationClient\Exceptions\AuthenticationException;
use Smartness\TranslationClient\TranslationClient;

class PullTranslationsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'translations:pull
                            {--language= : Pull translations for specific language only}
                            {--format= : Override format (json|php|raw)}
                            {--status= : Override status filter (approved|pending|rejected)}
                            {--dry-run : Preview without saving files}
                            {--test : Test API connection}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Pull translations from SmartPMS Translation Manager';

    protected array $stats = [
        'files' => 0,
        'keys' => 0,
    ];

    /**
     * Execute the console command.
     */
    public function handle(TranslationClient $client): int
    {
        // Test connection if requested
        if ($this->option('test')) {
            return $this->testConnection($client);
        }

        // Validate configuration
        if (! config('translation-client.api_token')) {
            $this->error('API token not configured. Please set TRANSLATION_API_TOKEN in your .env file.');

            return 1;
        }

        $this->info('🔄 Pulling translations from SmartPMS...');
        $this->newLine();

        try {
            // Fetch translations
            $format = $this->option('format') ?: config('translation-client.format', 'php');
            $language = $this->option('language');

            $options = [
                'format' => $format,
                'status' => $this->option('status') ?: config('translation-client.status_filter'),
            ];

            if ($language) {
                $options['language'] = $language;
            }

            $response = $client->fetch($options);

            $translations = $response['data']['translations'] ?? [];

            if (empty($translations)) {
                $this->warn('No translations found.');

                return 0;
            }

            // Determine output directory
            $outputDir = config('translation-client.output_dir') ?: lang_path();

            $this->line("📍 Output: {$outputDir}");
            $this->line("📄 Format: {$format}");
            $this->newLine();

            // Save translations
            $this->saveTranslations($translations, $format, $outputDir, $language);

            // Summary
            $this->newLine();
            $this->info('✅ Translations pulled successfully!');
            $this->table(
                ['Metric', 'Value'],
                [
                    ['Files created', $this->stats['files']],
                    ['Translation keys', $response['data']['total'] ?? $this->stats['keys']],
                    ['Languages', implode(', ', $response['data']['meta']['languages'] ?? [])],
                ]
            );

            if ($this->option('dry-run')) {
                $this->warn('This was a dry run. No files were saved.');
            }

            return 0;

        } catch (AuthenticationException $e) {
            $this->error('❌ Authentication failed: ' . $e->getMessage());

            return 1;
        } catch (ApiException $e) {
            $this->error('❌ API error: ' . $e->getMessage());

            return 1;
        } catch (\Exception $e) {
            $this->error('❌ Unexpected error: ' . $e->getMessage());

            return 1;
        }
    }

    /**
     * Pivot API data from filename -> key -> language -> value
     * to language -> filename -> key -> value
     *
     * @return array<string, array<string, array<string, string>>>
     */
    protected function pivotByLanguage(array $data, ?string $languageFilter = null): array
    {
        $pivoted = [];

        foreach ($data as $filename => $keys) {
            $filename = str_replace('.php', '', $filename);

            foreach ($keys as $key => $languages) {
                if (! is_array($languages)) {
                    continue;
                }

                foreach ($languages as $language => $value) {
                    if ($value === null) {
                        continue;
                    }

                    if ($languageFilter && $language !== $languageFilter) {
                        continue;
                    }

                    $pivoted[$language][$filename][$key] = $value;
                }
            }
        }

        return $pivoted;
    }

    /**
     * Save translations to files
     */
    protected function saveTranslations(array $data, string $format, string $outputDir, ?string $languageFilter = null): void
    {
        $byLanguage = $this->pivotByLanguage($data, $languageFilter);

        foreach ($byLanguage as $language => $files) {
            foreach ($files as $filename => $translations) {
                $this->saveFile($language, $filename, $translations, $format, $outputDir);
            }
        }
    }

    /**
     * Save a single translation file
     */
    protected function saveFile(
        string $language,
        string $filename,
        array $translations,
        string $format,
        string $outputDir
    ): void {
        // Create language directory
        $langDir = "{$outputDir}/{$language}";

        if (! $this->option('dry-run')) {
            File::ensureDirectoryExists($langDir);
        }

        // Determine file extension
        $extension = match ($format) {
            'php' => 'php',
            'json' => 'json',
            default => 'php',
        };

        $filePath = "{$langDir}/{$filename}.{$extension}";

        // Generate file content
        $content = match ($format) {
            'php' => $this->generatePhpContent($translations),
            'json' => json_encode($translations, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n",
            default => $this->generatePhpContent($translations),
        };

        // Save or preview
        if ($this->option('dry-run')) {
            $this->line("Would create: {$filePath}");
        } else {
            File::put($filePath, $content);
            $this->info("✓ {$language}/{$filename}.{$extension}");
        }

        $this->stats['files']++;
        $this->stats['keys'] += count($translations, COUNT_RECURSIVE) - count($translations);
    }

    /**
     * Expand dot-notation keys into nested arrays
     */
    protected function undot(array $data): array
    {
        $result = [];

        foreach ($data as $key => $value) {
            data_set($result, $key, $value);
        }

        return $result;
    }

    /**
     * Generate PHP file content with short array syntax
     */
    protected function generatePhpContent(array $data): string
    {
        $data = $this->undot($data);

        return "<?php\n\nreturn " . $this->exportArray($data) . ";\n";
    }

    /**
     * Export array using short [] syntax
     */
    protected function exportArray(array $array, int $indent = 1): string
    {
        if (empty($array)) {
            return '[]';
        }

        $spaces = str_repeat('    ', $indent);
        $closingSpaces = str_repeat('    ', $indent - 1);
        $lines = [];

        foreach ($array as $key => $value) {
            $exportedKey = var_export($key, true);

            if (is_array($value)) {
                $lines[] = "{$spaces}{$exportedKey} => " . $this->exportArray($value, $indent + 1) . ',';
            } else {
                $lines[] = "{$spaces}{$exportedKey} => " . var_export($value, true) . ',';
            }
        }

        return "[\n" . implode("\n", $lines) . "\n{$closingSpaces}]";
    }

    /**
     * Test API connection
     */
    protected function testConnection(TranslationClient $client): int
    {
        $this->info('Testing connection to SmartPMS Translation API...');
        $this->newLine();

        try {
            if ($client->testConnection()) {
                $this->info('✅ Connection successful!');
                $this->line('API URL: ' . config('translation-client.api_url'));
                $this->line('Token configured: Yes');

                return 0;
            } else {
                $this->error('❌ Connection failed');

                return 1;
            }
        } catch (AuthenticationException $e) {
            $this->error('❌ Authentication failed: ' . $e->getMessage());
            $this->newLine();
            $this->line('Please check your API token in .env:');
            $this->line('TRANSLATION_API_TOKEN=your_token_here');

            return 1;
        } catch (ApiException $e) {
            $this->error('❌ API error: ' . $e->getMessage());

            return 1;
        }
    }
}
