<?php

namespace Smartness\TranslationClient\Commands\Concerns;

use Smartness\TranslationClient\TranslationClient;

/**
 * Resolves the four scan-related settings by merging in priority order:
 *   1. Local config (translation-client.scan_*)
 *   2. Server config (GET /translation-projects/config)
 *   3. Package defaults (translation-client.default_*)
 *
 * Local values always win; the server config keeps multiple developers in
 * sync without forcing them to keep matching env vars.
 *
 * @internal
 *
 * @mixin \Illuminate\Console\Command
 */
trait ResolvesScanConfig
{
    /**
     * @return array{
     *     scan_dirs: string[],
     *     scan_extensions: string[],
     *     key_pattern: string,
     *     prefix_pattern: ?string,
     *     sources: array{scan_dirs:string, scan_extensions:string, key_pattern:string, prefix_pattern:string}
     * }
     */
    protected function resolveScanConfig(TranslationClient $client): array
    {
        $localDirs = config('translation-client.scan_dirs');
        $localExts = config('translation-client.scan_extensions');
        $localKeyPattern = config('translation-client.scan_key_pattern');
        $localPrefixPattern = config('translation-client.scan_prefix_pattern');

        $server = $client->fetchProjectConfig() ?? [];

        // SECURITY: scan_dirs and scan_extensions decide WHICH files on the
        // developer's machine get read (and whose matched contents are sent back
        // to the server). We therefore never take those two from the server —
        // only from local config or the package defaults. The regex patterns
        // may still be shared from the server (that is the feature's point).
        $serverKeyPattern = $this->normalizeString($server['scan_key_pattern'] ?? null);
        $serverPrefixPattern = $this->normalizeString($server['scan_prefix_pattern'] ?? null);

        [$dirs, $dirsSource] = $this->pick($localDirs, null, config('translation-client.default_scan_dirs'));
        [$exts, $extsSource] = $this->pick($localExts, null, config('translation-client.default_scan_extensions'));
        [$keyPattern, $keySource] = $this->pick($localKeyPattern, $serverKeyPattern, config('translation-client.default_key_pattern'));
        [$prefixPattern, $prefixSource] = $this->pick($localPrefixPattern, $serverPrefixPattern, config('translation-client.default_prefix_pattern'));

        // Confine every scan directory to the application base path so a stray
        // absolute or traversing path cannot walk the wider filesystem.
        $dirs = $this->confineToBase($dirs);

        return [
            'scan_dirs' => $dirs,
            'scan_extensions' => $exts,
            'key_pattern' => $keyPattern,
            'prefix_pattern' => $prefixPattern,
            'sources' => [
                'scan_dirs' => $dirsSource,
                'scan_extensions' => $extsSource,
                'key_pattern' => $keySource,
                'prefix_pattern' => $prefixSource,
            ],
        ];
    }

    protected function printScanConfig(array $resolved): void
    {
        $sources = $resolved['sources'];
        $this->line('Scan configuration:');
        $this->line(sprintf('  scan_dirs        (%-7s) %s', $sources['scan_dirs'], implode(', ', $resolved['scan_dirs'])));
        $this->line(sprintf('  scan_extensions  (%-7s) %s', $sources['scan_extensions'], implode(', ', $resolved['scan_extensions'])));
        $this->line(sprintf('  key_pattern      (%-7s) %s', $sources['key_pattern'], $resolved['key_pattern']));
        $this->line(sprintf('  prefix_pattern   (%-7s) %s', $sources['prefix_pattern'], $resolved['prefix_pattern'] ?? '(disabled)'));
    }

    /**
     * @return array{0: mixed, 1: string}
     */
    private function pick(mixed $local, mixed $server, mixed $default): array
    {
        if ($local !== null && $local !== [] && $local !== '') {
            return [$local, 'local'];
        }

        if ($server !== null && $server !== [] && $server !== '') {
            return [$server, 'server'];
        }

        return [$default, 'default'];
    }

    private function normalizeList(mixed $raw, bool $normalizeExtension = false): ?array
    {
        if (! is_array($raw)) {
            return null;
        }

        $items = array_values(array_filter(array_map(
            function ($item) use ($normalizeExtension) {
                $value = trim((string) $item);
                if ($normalizeExtension) {
                    $value = strtolower(ltrim($value, '.'));
                }

                return $value;
            },
            $raw,
        ), fn ($v) => $v !== ''));

        return $items === [] ? null : $items;
    }

    /**
     * Keep only scan directories that resolve inside the application base path.
     *
     * @param  mixed  $dirs
     * @return string[]
     */
    private function confineToBase(mixed $dirs): array
    {
        if (! is_array($dirs)) {
            return [];
        }

        $base = realpath(base_path());
        if ($base === false) {
            return array_values($dirs);
        }

        $safe = [];
        foreach ($dirs as $dir) {
            // Resolve relative dirs against the app base, absolute ones as-is.
            $candidate = str_starts_with((string) $dir, '/') ? (string) $dir : $base . '/' . ltrim((string) $dir, '/');
            $real = realpath($candidate);

            if ($real === false) {
                // Not existing yet — keep the original relative value; the
                // scanner's own realpath() check will drop it if it is invalid.
                if (! str_starts_with((string) $dir, '/') && ! str_contains((string) $dir, '..')) {
                    $safe[] = $dir;
                }

                continue;
            }

            if ($real === $base || str_starts_with($real, $base . DIRECTORY_SEPARATOR)) {
                $safe[] = $dir;
            } else {
                $this->warn("Ignoring scan_dir outside the application: {$dir}");
            }
        }

        return $safe;
    }

    private function normalizeString(mixed $raw): ?string
    {
        if (! is_string($raw)) {
            return null;
        }
        $value = trim($raw);

        return $value === '' ? null : $value;
    }
}
