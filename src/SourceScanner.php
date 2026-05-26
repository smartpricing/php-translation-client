<?php

namespace Smartness\TranslationClient;

/**
 * Walks the configured source directories and extracts translation keys (and
 * dynamic prefixes) using user-configurable regex patterns. Mirrors the
 * scanner in public/downloads/translation-sync.js so behavior stays in sync.
 */
class SourceScanner
{
    private const SKIP_DIRS = [
        'node_modules', '.git', 'dist', 'build', 'out', '.next', '.nuxt',
        'coverage', 'vendor', 'storage', 'bootstrap/cache',
    ];

    /**
     * @param  string[]  $scanDirs
     * @param  string[]  $scanExtensions
     */
    public function __construct(
        private array $scanDirs,
        private array $scanExtensions,
        private ?string $keyPattern,
        private ?string $prefixPattern,
    ) {}

    /**
     * @return array{keys: string[], prefixes: string[], files_scanned: int}
     */
    public function scan(): array
    {
        $files = $this->collectFiles();
        $keys = [];
        $prefixes = [];

        foreach ($files as $file) {
            $contents = @file_get_contents($file);
            if ($contents === false) {
                continue;
            }

            foreach ($this->matchAll($contents, $this->keyPattern) as $key) {
                if ($key !== '' && ! str_contains($key, '${')) {
                    $keys[$key] = true;
                }
            }

            foreach ($this->matchAll($contents, $this->prefixPattern) as $prefix) {
                if ($prefix !== '') {
                    $prefixes[$prefix] = true;
                }
            }
        }

        return [
            'keys' => array_keys($keys),
            'prefixes' => array_keys($prefixes),
            'files_scanned' => count($files),
        ];
    }

    /**
     * @return string[]
     */
    private function collectFiles(): array
    {
        $allowed = array_fill_keys(array_map('strtolower', $this->scanExtensions), true);
        $found = [];

        $skipDirs = self::SKIP_DIRS;

        foreach ($this->scanDirs as $dir) {
            $absolute = realpath($dir);
            if ($absolute === false || ! is_dir($absolute)) {
                continue;
            }

            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveCallbackFilterIterator(
                    new \RecursiveDirectoryIterator($absolute, \FilesystemIterator::SKIP_DOTS),
                    static function (\SplFileInfo $current) use ($skipDirs): bool {
                        $name = $current->getFilename();
                        if ($current->isDir()) {
                            return ! in_array($name, $skipDirs, true) && ! str_starts_with($name, '.');
                        }

                        return true;
                    },
                ),
            );

            foreach ($iterator as $file) {
                /** @var \SplFileInfo $file */
                if (! $file->isFile()) {
                    continue;
                }

                $name = $file->getFilename();
                // Match compound extensions like blade.php first.
                $matched = false;
                foreach ($allowed as $ext => $_) {
                    if (str_ends_with(strtolower($name), '.' . $ext)) {
                        $matched = true;
                        break;
                    }
                }

                if ($matched) {
                    $found[] = $file->getPathname();
                }
            }
        }

        return $found;
    }

    /**
     * Extract capture-group-1 matches of $pattern from $content. Returns [] if
     * the pattern is null/empty or invalid.
     *
     * @return string[]
     */
    private function matchAll(string $content, ?string $pattern): array
    {
        if ($pattern === null || $pattern === '') {
            return [];
        }

        $delimited = '/' . str_replace('/', '\\/', $pattern) . '/u';

        $matches = [];
        $result = @preg_match_all($delimited, $content, $matches);
        if ($result === false || ! isset($matches[1])) {
            return [];
        }

        return $matches[1];
    }
}
