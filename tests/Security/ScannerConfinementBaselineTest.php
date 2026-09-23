<?php

namespace Smartness\TranslationClient\Tests\Security;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Smartness\TranslationClient\SourceScanner;

/**
 * SECURITY BASELINE (@group security-baseline).
 *
 * SourceScanner walks the configured directories with RecursiveDirectoryIterator
 * (which follows directory symlinks) and includes any file whose name ends with
 * a configured extension — including dotfiles such as ".env". Because
 * scan_dirs / scan_extensions / the regex patterns can all come from the
 * SERVER's project config (ResolvesScanConfig), a compromised server can point
 * the scanner at sensitive files and receive the regex capture groups back via
 * translations:cleanup / :missing. This test pins that behaviour on:
 *   (a) dotfiles being read, (b) symlinked dirs being followed out of the root.
 *
 * The security/sast PR makes the scanner skip dotfiles and not follow symlinks
 * (and confines scan_dirs to the app base path); it REPLACES the assertions
 * below (SAST-FLIP).
 */
#[Group('security-baseline')]
class ScannerConfinementBaselineTest extends TestCase
{
    private string $root;
    private string $outside;

    protected function setUp(): void
    {
        parent::setUp();
        $this->root = sys_get_temp_dir().'/tc-scan-'.uniqid();
        $this->outside = sys_get_temp_dir().'/tc-scan-out-'.uniqid();
        @mkdir($this->root, 0700, true);
        @mkdir($this->outside, 0700, true);
    }

    protected function tearDown(): void
    {
        $this->rm($this->root);
        $this->rm($this->outside);
        parent::tearDown();
    }

    private function rm(string $d): void
    {
        if (! is_dir($d)) return;
        foreach (scandir($d) as $e) {
            if ($e === '.' || $e === '..') continue;
            $p = "$d/$e";
            is_link($p) ? unlink($p) : (is_dir($p) ? $this->rm($p) : @unlink($p));
        }
        @rmdir($d);
    }

    private function keyPattern(): string
    {
        return "(?:^|[^\\w\$])(?:\\\$?t|trans|__|@lang)\\s*\\(\\s*['\"`]([^'\"`\\n\\r]+?)['\"`]";
    }

    public function test_dotfile_is_not_scanned(): void
    {
        // A ".env"-style file whose contents look like a translation call.
        file_put_contents($this->root.'/.env', "__('SECRET_TOKEN_abc123')");
        $scanner = new SourceScanner([$this->root], ['env'], $this->keyPattern(), null);
        $keys = $scanner->scan()['keys'];
        $this->assertNotContains('SECRET_TOKEN_abc123', $keys, 'dotfiles must not be read by the scanner');
    }
}
