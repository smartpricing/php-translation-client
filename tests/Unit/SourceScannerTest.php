<?php

namespace Smartness\TranslationClient\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Smartness\TranslationClient\SourceScanner;

class SourceScannerTest extends TestCase
{
    private string $tmpDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tmpDir = sys_get_temp_dir().'/translation-scanner-'.uniqid();
        mkdir($this->tmpDir, 0700, true);
    }

    protected function tearDown(): void
    {
        $this->rrmdir($this->tmpDir);
        parent::tearDown();
    }

    private function rrmdir(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }
        foreach (scandir($dir) as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $path = $dir.DIRECTORY_SEPARATOR.$entry;
            is_dir($path) ? $this->rrmdir($path) : unlink($path);
        }
        rmdir($dir);
    }

    private function write(string $relative, string $contents): void
    {
        $path = $this->tmpDir.'/'.$relative;
        @mkdir(dirname($path), 0700, true);
        file_put_contents($path, $contents);
    }

    private function defaultKeyPattern(): string
    {
        return "(?:^|[^\\w$])(?:\\$?t|useTranslat(?:e|ion)|i18n(?:\\.\\w+)*\\.t|trans|__|@lang)\\s*\\(\\s*['\"`]([^'\"`\\n\\r]+?)['\"`]";
    }

    private function defaultPrefixPattern(): string
    {
        return "(?:^|[^\\w$])(?:\\$?t|useTranslat(?:e|ion)|i18n(?:\\.\\w+)*\\.t)\\s*\\(\\s*`([^`$]+)\\$\\{";
    }

    public function test_scans_vue_and_blade_and_skips_node_modules(): void
    {
        $this->write('src/App.vue', "<template>{{ \$t('hello.world') }}</template>");
        $this->write('resources/views/x.blade.php', "@lang('blade.greeting') {{ __('blade.farewell') }}");
        $this->write('app/Service.php', "trans('service.label');");
        $this->write('node_modules/some/file.vue', "\$t('should.be.ignored')");

        $scanner = new SourceScanner(
            [$this->tmpDir.'/src', $this->tmpDir.'/resources', $this->tmpDir.'/app'],
            ['vue', 'blade.php', 'php'],
            $this->defaultKeyPattern(),
            $this->defaultPrefixPattern(),
        );

        $result = $scanner->scan();
        sort($result['keys']);

        $this->assertSame(
            ['blade.farewell', 'blade.greeting', 'hello.world', 'service.label'],
            $result['keys'],
        );
        $this->assertSame([], $result['prefixes']);
    }

    public function test_extracts_template_literal_prefixes(): void
    {
        $this->write('src/x.ts', "\$t('amenities.shared'); \$t(`amenities.\${dynamic}`)");

        $scanner = new SourceScanner(
            [$this->tmpDir.'/src'],
            ['ts'],
            $this->defaultKeyPattern(),
            $this->defaultPrefixPattern(),
        );

        $result = $scanner->scan();

        $this->assertContains('amenities.shared', $result['keys']);
        // Keys containing ${ are excluded from the keys list:
        foreach ($result['keys'] as $key) {
            $this->assertStringNotContainsString('${', $key);
        }
        $this->assertSame(['amenities.'], $result['prefixes']);
    }
}
