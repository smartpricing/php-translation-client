<?php

namespace Smartness\TranslationClient\Commands;

use Illuminate\Console\Command;
use Smartness\TranslationClient\Commands\Concerns\ResolvesScanConfig;
use Smartness\TranslationClient\Exceptions\ApiException;
use Smartness\TranslationClient\Exceptions\AuthenticationException;
use Smartness\TranslationClient\SourceScanner;
use Smartness\TranslationClient\TranslationClient;

class MissingTranslationsCommand extends Command
{
    use ResolvesScanConfig;

    protected $signature = 'translations:missing
                            {--insert : Create the missing keys as new translation rows on the project\'s primary language}';

    protected $description = 'Scan source files for translation usage and report (or insert) keys that are missing remotely.';

    public function handle(TranslationClient $client): int
    {
        if (! config('translation-client.api_token')) {
            $this->error('API token not configured. Please set TRANSLATION_API_TOKEN in your .env file.');

            return 1;
        }

        $shouldInsert = (bool) $this->option('insert');

        $resolved = $this->resolveScanConfig($client);
        $this->printScanConfig($resolved);

        if ($resolved['scan_dirs'] === []) {
            $this->error('scan_dirs is empty — nothing to scan.');

            return 1;
        }

        $scanner = new SourceScanner(
            $resolved['scan_dirs'],
            $resolved['scan_extensions'],
            $resolved['key_pattern'],
            $resolved['prefix_pattern'],
        );

        $scan = $scanner->scan();
        $this->newLine();
        $this->info("Scanned {$scan['files_scanned']} files.");
        $this->line('Found '.count($scan['keys']).' unique used key(s).');

        if (count($scan['prefixes']) > 0) {
            $this->line('Found '.count($scan['prefixes']).' dynamic prefix(es) — these cannot be enumerated and will not be inserted.');
        }

        try {
            $response = $client->discoverMissing($scan['keys'], $scan['prefixes'], $shouldInsert);
        } catch (AuthenticationException $e) {
            $this->error('❌ Authentication failed: '.$e->getMessage());

            return 1;
        } catch (ApiException $e) {
            $this->error('❌ API error: '.$e->getMessage());

            return 1;
        }

        $data = $response['data'] ?? [];
        $missing = $data['missing'] ?? [];

        $this->newLine();
        $this->line('Remote keys: '.($data['remote_total'] ?? 0));
        $this->line('Used keys sent: '.($data['used_received'] ?? 0));
        $this->line('Missing keys: '.count($missing));

        $preview = array_slice($missing, 0, 20);
        foreach ($preview as $key) {
            $this->line("  - {$key}");
        }
        if (count($missing) > count($preview)) {
            $this->line('  … and '.(count($missing) - count($preview)).' more');
        }

        if ($shouldInsert) {
            $this->info('✓ Inserted '.($data['inserted'] ?? 0).' missing translation key(s) on the primary language.');
        } elseif (count($missing) > 0) {
            $this->comment('Re-run with --insert to create them as new keys.');
        }

        return 0;
    }
}
