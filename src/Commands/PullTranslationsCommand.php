<?php

namespace Smartness\TranslationClient\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\File;
use Smartness\TranslationClient\Exceptions\ApiException;
use Smartness\TranslationClient\Exceptions\AuthenticationException;
use Smartness\TranslationClient\TranslationClient;

class PullTranslationsCommand extends Command
{
    protected $signature = 'translations:pull
                            {--language= : Pull translations for specific language only}
                            {--format= : Override format (json|php|raw)}
                            {--status= : Override status filter (approved|pending|rejected)}
                            {--dry-run : Preview without saving files}
                            {--test : Test API connection}';

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
                if ($filename === '') {
                    $this->warn("Skipping translations with no filename for language: {$language}");

                    continue;
                }

                $this->saveFile($language, $filename, $translations, $format, $outputDir);
            }
        }
    }

    protected function saveFile(
        string $language,
        string $filename,
        array $translations,
        string $format,
        string $outputDir
    ): void {
        $langDir = "{$outputDir}/{$language}";
        $isJson = $format === 'json';
        $extension = $isJson ? 'json' : 'php';
        $filePath = "{$langDir}/{$filename}.{$extension}";

        if ($this->option('dry-run')) {
            $this->line("Would create: {$filePath}");
        } else {
            File::ensureDirectoryExists($langDir);

            if (! $isJson && File::exists($filePath)) {
                $content = $this->mergePhpFile($filePath, $translations);
            } else {
                $content = $isJson
                    ? json_encode($translations, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n"
                    : $this->generatePhpContent($translations);
            }

            File::put($filePath, $content);
            $this->info("✓ {$language}/{$filename}.{$extension}");
        }

        $this->stats['files']++;
        $this->stats['keys'] += count($translations, COUNT_RECURSIVE) - count($translations);
    }

    /**
     * Merge server translations into an existing PHP file,
     * preserving comments, constants, and key ordering.
     */
    protected function mergePhpFile(string $filePath, array $serverTranslations): string
    {
        $rawContent = File::get($filePath);

        // Include the file to get resolved values (constants, expressions, etc.)
        $resolved = include $filePath;
        if (! is_array($resolved)) {
            return $this->generatePhpContent($serverTranslations);
        }

        $currentFlat = Arr::dot($resolved);
        $serverFlat = $serverTranslations; // Already dotted keys from the API

        // Separate changed values and new keys
        $changed = [];
        $newKeys = [];

        foreach ($serverFlat as $dottedKey => $serverValue) {
            if (array_key_exists($dottedKey, $currentFlat)) {
                if ((string) $currentFlat[$dottedKey] !== (string) $serverValue) {
                    $changed[$dottedKey] = [
                        'old' => $currentFlat[$dottedKey],
                        'new' => $serverValue,
                    ];
                }
            } else {
                $newKeys[$dottedKey] = $serverValue;
            }
        }

        // Replace changed values in the raw content line-by-line
        if (! empty($changed)) {
            $rawContent = $this->replaceValuesInSource($rawContent, $changed);
        }

        // Append new keys before the final ];
        if (! empty($newKeys)) {
            $rawContent = $this->appendKeysToSource($rawContent, $newKeys);
        }

        return $rawContent;
    }

    /**
     * Replace old values with new values in the raw PHP source.
     * Handles duplicate values by processing line-by-line and tracking handled keys.
     */
    protected function replaceValuesInSource(string $content, array $changed): string
    {
        $lines = explode("\n", $content);
        $handled = [];

        foreach ($lines as $index => $line) {
            foreach ($changed as $dottedKey => $values) {
                if (isset($handled[$dottedKey])) {
                    continue;
                }

                $oldValue = $values['old'];
                $newValue = $values['new'];

                // Get the last segment of the dotted key to match in the source
                $segments = explode('.', $dottedKey);
                $lastKey = end($segments);
                $quotedKey = preg_quote(var_export($lastKey, true), '/');

                // Escape old value for regex
                $escapedOld = preg_quote(var_export((string) $oldValue, true), '/');

                // Match: 'key' => 'old_value' or "key" => "old_value"
                $pattern = '/(' . $quotedKey . '\s*=>\s*)' . $escapedOld . '/';

                if (preg_match($pattern, $line)) {
                    $lines[$index] = preg_replace(
                        $pattern,
                        '${1}' . var_export((string) $newValue, true),
                        $line,
                        1
                    );
                    $handled[$dottedKey] = true;

                    break;
                }
            }
        }

        return implode("\n", $lines);
    }

    /**
     * Append new keys before the final `];` in the PHP source.
     */
    protected function appendKeysToSource(string $content, array $newKeys): string
    {
        $nested = Arr::undot($newKeys);
        $exported = $this->exportArrayEntries($nested, 1);

        // Insert before the last ];
        $pos = strrpos($content, '];');
        if ($pos === false) {
            return $content;
        }

        return substr($content, 0, $pos) . $exported . "\n" . '];' . substr($content, $pos + 2);
    }

    /**
     * Export array entries as PHP source lines (without wrapping [] brackets).
     */
    protected function exportArrayEntries(array $array, int $indent): string
    {
        $spaces = str_repeat('    ', $indent);
        $lines = [];

        foreach ($array as $key => $value) {
            $exportedKey = var_export($key, true);

            if (is_array($value)) {
                $lines[] = "{$spaces}{$exportedKey} => " . $this->exportArray($value, $indent + 1) . ',';
            } else {
                $lines[] = "{$spaces}{$exportedKey} => " . var_export($value, true) . ',';
            }
        }

        return implode("\n", $lines);
    }

    protected function generatePhpContent(array $data): string
    {
        $data = Arr::undot($data);

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
