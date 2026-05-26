<?php

namespace Smartness\TranslationClient\Commands;

use Illuminate\Console\Command;
use Smartness\TranslationClient\Commands\Concerns\ResolvesScanConfig;
use Smartness\TranslationClient\Exceptions\ApiException;
use Smartness\TranslationClient\Exceptions\AuthenticationException;
use Smartness\TranslationClient\SourceScanner;
use Smartness\TranslationClient\TranslationClient;

class CleanupTranslationsCommand extends Command
{
    use ResolvesScanConfig;

    protected $signature = 'translations:cleanup
                            {--delete : Actually delete the unused keys (without this flag the command only reports them)}';

    protected $description = 'Scan source files for translation usage and report (or delete) unused remote keys.';

    public function handle(TranslationClient $client): int
    {
        if (! config('translation-client.api_token')) {
            $this->error('API token not configured. Please set TRANSLATION_API_TOKEN in your .env file.');

            return 1;
        }

        $shouldDelete = (bool) $this->option('delete');

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
            $this->line('Found '.count($scan['prefixes']).' dynamic prefix(es) — remote keys starting with these will be protected:');
            foreach ($scan['prefixes'] as $prefix) {
                $this->line("  • {$prefix}*");
            }
        }

        try {
            $response = $client->cleanup($scan['keys'], $scan['prefixes'], $shouldDelete);
        } catch (AuthenticationException $e) {
            $this->error('❌ Authentication failed: '.$e->getMessage());

            return 1;
        } catch (ApiException $e) {
            $this->error('❌ API error: '.$e->getMessage());

            return 1;
        }

        $data = $response['data'] ?? [];
        $unused = $data['unused'] ?? [];

        $this->newLine();
        $this->line('Remote keys: '.($data['remote_total'] ?? 0));
        $this->line('Used keys received: '.($data['used_received'] ?? 0));
        if (isset($data['protected_by_prefix'])) {
            $this->line('Protected by prefix: '.$data['protected_by_prefix']);
        }
        $this->line('Unused keys: '.count($unused));

        $preview = array_slice($unused, 0, 20);
        foreach ($preview as $key) {
            $this->line("  - {$key}");
        }
        if (count($unused) > count($preview)) {
            $this->line('  … and '.(count($unused) - count($preview)).' more');
        }

        if ($shouldDelete) {
            $this->info('✓ Deleted '.($data['deleted'] ?? 0).' unused translation(s).');
        } elseif (count($unused) > 0) {
            $this->comment('Re-run with --delete to remove them.');
        }

        return 0;
    }
}
