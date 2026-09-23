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

                // Language and filename come straight from the (potentially
                // compromised / MITM'd) translation server and are used to build
                // a filesystem path. Reject anything that is not a single, safe
                // path segment so a malicious response cannot write PHP files
                // outside the lang/ directory (path traversal / absolute paths).
                if (! $this->isSafeSegment($language) || ! $this->isSafeSegment($filename)) {
                    $this->warn("Skipping unsafe language/filename from server: {$language}/{$filename}");

                    continue;
                }

                $this->saveFile($language, $filename, $translations, $format, $outputDir);
            }
        }
    }

    /**
     * A safe path segment is a single non-empty component with no directory
     * separators, no "." / ".." traversal and no NUL byte. Laravel language
     * directories and translation file names are always single segments
     * (e.g. "en", "auth"), so this rejects only hostile input.
     */
    protected function isSafeSegment(string $segment): bool
    {
        if ($segment === '' || $segment === '.' || $segment === '..') {
            return false;
        }

        if (str_contains($segment, "\0")) {
            return false;
        }

        // Any slash/backslash means it is not a single segment.
        return preg_match('#[/\\\\]#', $segment) !== 1;
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
            // Defence in depth: even after per-segment validation, make sure the
            // resolved target really lives under the configured output dir.
            if (! $this->isWithinBase($outputDir, $filePath)) {
                $this->warn("Refusing to write outside the output directory: {$filePath}");

                return;
            }

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
     * Return true when $target, resolved lexically, stays inside $base.
     *
     * Neither path needs to exist yet, so we normalise "." / ".." segments
     * ourselves instead of relying on realpath().
     */
    protected function isWithinBase(string $base, string $target): bool
    {
        $normalize = static function (string $path): string {
            $isAbsolute = str_starts_with($path, '/');
            $out = [];
            foreach (explode('/', str_replace('\\', '/', $path)) as $part) {
                if ($part === '' || $part === '.') {
                    continue;
                }
                if ($part === '..') {
                    array_pop($out);

                    continue;
                }
                $out[] = $part;
            }

            return ($isAbsolute ? '/' : '') . implode('/', $out);
        };

        $base = rtrim($normalize($base), '/');
        $target = $normalize($target);

        return $target === $base || str_starts_with($target, $base . '/');
    }

    /**
     * Merge server translations into an existing PHP file.
     *
     * The previous implementation rewrote the raw file text with preg_replace()
     * and a replacement string built from the server value; preg_replace()
     * interprets back-reference tokens ($0/$1/\1) inside that value, which let
     * a compromised server (or a MITM on the plaintext default URL) corrupt or
     * inject into the generated PHP. We now build the merged data as a plain
     * array and re-render it with var_export(), which escapes every value, so
     * no server-controlled byte ever reaches the file unescaped.
     *
     * Trade-off: inline comments and PHP constants in the existing file are not
     * preserved (constants are resolved to their values by include). This is an
     * acceptable and deliberate change for translation string files.
     */
    protected function mergePhpFile(string $filePath, array $serverTranslations): string
    {
        $resolved = include $filePath;
        if (! is_array($resolved)) {
            return $this->generatePhpContent($serverTranslations);
        }

        // Local (existing) values first, server values win. Server keys are in
        // dot-notation, matching Arr::dot() of the resolved local array.
        $merged = array_merge(Arr::dot($resolved), $serverTranslations);

        return $this->generatePhpContent($merged);
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
