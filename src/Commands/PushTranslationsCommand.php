<?php

namespace Smartness\TranslationClient\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Smartness\TranslationClient\Exceptions\ApiException;
use Smartness\TranslationClient\Exceptions\AuthenticationException;
use Smartness\TranslationClient\TranslationClient;

class PushTranslationsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'translations:push
                            {--language= : Push translations for specific language only}
                            {--file= : Push specific translation file only}
                            {--dir= : Override translation directory}
                            {--overwrite : Overwrite existing translations on the server}
                            {--dry-run : Preview without actually pushing}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Push local translations to SmartPMS Translation Manager';

    protected array $stats = [
        'files' => 0,
        'keys' => 0,
        'created' => 0,
        'updated' => 0,
    ];

    /**
     * Execute the console command.
     */
    public function handle(TranslationClient $client): int
    {
        // Validate configuration
        if (! config('translation-client.api_token')) {
            $this->error('API token not configured. Please set SMARTPMS_TRANSLATION_TOKEN in your .env file.');

            return 1;
        }

        $this->info('⬆️  Pushing translations to SmartPMS...');
        $this->newLine();

        try {
            // Determine input directory
            $inputDir = $this->option('dir') ?: lang_path();

            if (! File::isDirectory($inputDir)) {
                $this->error("Translation directory not found: {$inputDir}");

                return 1;
            }

            $this->line("📍 Source: {$inputDir}");
            $this->newLine();

            // Read local translations
            $translations = $this->readTranslations($inputDir);

            if (empty($translations)) {
                $this->warn('No translations found to push.');

                return 0;
            }

            // Display summary before pushing
            $this->displaySummary($translations);

            if (! $this->option('dry-run') && ! $this->confirm('Do you want to push these translations?', true)) {
                $this->info('Push cancelled.');

                return 0;
            }

            // Push translations
            if (! $this->option('dry-run')) {
                $response = $client->push($translations, [
                    'overwrite' => $this->option('overwrite'),
                ]);

                $this->displayResults($response);
            } else {
                $this->warn('This was a dry run. No translations were pushed.');
            }

            $this->newLine();
            $this->info('✅ Translations push completed!');

            return 0;

        } catch (AuthenticationException $e) {
            $this->error('❌ Authentication failed: '.$e->getMessage());

            return 1;
        } catch (ApiException $e) {
            $this->error('❌ API error: '.$e->getMessage());

            return 1;
        } catch (\Exception $e) {
            $this->error('❌ Unexpected error: '.$e->getMessage());

            return 1;
        }
    }

    /**
     * Read translations from local files
     */
    protected function readTranslations(string $inputDir): array
    {
        $translations = [];
        $languageFilter = $this->option('language');
        $fileFilter = $this->option('file');

        // Get all language directories
        $languages = File::directories($inputDir);

        foreach ($languages as $langPath) {
            $language = basename($langPath);

            // Apply language filter if specified
            if ($languageFilter && $language !== $languageFilter) {
                continue;
            }

            $translations[$language] = [];

            // Get all translation files in the language directory
            $files = File::files($langPath);

            foreach ($files as $file) {
                $filename = $file->getFilenameWithoutExtension();
                $extension = $file->getExtension();

                // Apply file filter if specified
                if ($fileFilter && $filename !== $fileFilter) {
                    continue;
                }

                // Only process PHP and JSON files
                if (! in_array($extension, ['php', 'json'])) {
                    continue;
                }

                $content = $this->loadTranslationFile($file->getPathname(), $extension);

                if (! empty($content)) {
                    $translations[$language][$filename] = $content;
                    $this->stats['files']++;
                    $this->stats['keys'] += $this->countKeys($content);
                }
            }

            // Remove language if no files were loaded
            if (empty($translations[$language])) {
                unset($translations[$language]);
            }
        }

        return $translations;
    }

    /**
     * Load translation file content
     */
    protected function loadTranslationFile(string $path, string $extension): array
    {
        try {
            if ($extension === 'json') {
                return json_decode(File::get($path), true) ?? [];
            }

            if ($extension === 'php') {
                return include $path;
            }

            return [];
        } catch (\Exception $e) {
            $this->warn("Failed to load {$path}: {$e->getMessage()}");

            return [];
        }
    }

    /**
     * Count translation keys recursively
     */
    protected function countKeys(array $array): int
    {
        $count = 0;

        foreach ($array as $value) {
            if (is_array($value)) {
                $count += $this->countKeys($value);
            } else {
                $count++;
            }
        }

        return $count;
    }

    /**
     * Display summary of translations to be pushed
     */
    protected function displaySummary(array $translations): void
    {
        $rows = [];

        foreach ($translations as $language => $files) {
            foreach ($files as $filename => $content) {
                $rows[] = [
                    $language,
                    $filename,
                    $this->countKeys($content),
                ];
            }
        }

        $this->table(
            ['Language', 'File', 'Keys'],
            $rows
        );

        $this->newLine();
        $this->line("Total: {$this->stats['files']} files, {$this->stats['keys']} keys");
        $this->newLine();
    }

    /**
     * Display results from API response
     */
    protected function displayResults(array $response): void
    {
        $this->newLine();

        if (isset($response['data']['summary'])) {
            $summary = $response['data']['summary'];

            $this->table(
                ['Metric', 'Value'],
                [
                    ['Created', $summary['created'] ?? 0],
                    ['Updated', $summary['updated'] ?? 0],
                    ['Skipped', $summary['skipped'] ?? 0],
                    ['Total', $summary['total'] ?? 0],
                ]
            );
        }

        if (isset($response['message'])) {
            $this->info($response['message']);
        }
    }
}
