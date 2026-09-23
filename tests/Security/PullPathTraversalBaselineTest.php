<?php

namespace Smartness\TranslationClient\Tests\Security;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Group;
use Smartness\TranslationClient\Tests\TestCase;

/**
 * SECURITY BASELINE (@group security-baseline).
 *
 * Pins the CURRENT (vulnerable) behaviour: the pull command interpolates the
 * server-supplied filename and language directly into the output path
 * ("{$outputDir}/{$language}/{$filename}.php") with no confinement, so a
 * malicious/compromised translation server (or a MITM on the plaintext
 * default URL) can write PHP files anywhere the process can write — outside
 * lang/ and even at an absolute path.
 *
 * These assertions describe the vulnerability. The security/sast PR fixes the
 * command to confine writes to the output dir and REPLACES the assertions
 * below with their fixed counterparts (marked "SAST-FLIP").
 */
#[Group('security-baseline')]
class PullPathTraversalBaselineTest extends TestCase
{
    private string $base;
    private string $lang;

    protected function setUp(): void
    {
        parent::setUp();
        $this->base = sys_get_temp_dir().'/tc-trav-'.uniqid();
        $this->lang = $this->base.'/lang';
        File::ensureDirectoryExists($this->lang);
        config()->set('translation-client.output_dir', $this->lang);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->base);
        parent::tearDown();
    }

    private function fake(array $translations): void
    {
        Http::fake(['api.example.com/*' => Http::response([
            'success' => true, 'data' => ['translations' => $translations],
        ], 200)]);
    }

    public function test_server_filename_escapes_lang_dir_via_dot_dot(): void
    {
        // filename "../../evil" -> lang/en/../../evil.php == base/evil.php
        $this->fake(['../../evil' => ['x' => ['en' => 'pwned']]]);
        $this->artisan('translations:pull')->assertExitCode(0);

        $escaped = $this->base.'/evil.php';
        // SAST-FLIP: after the fix this becomes assertFileDoesNotExist($escaped)
        $this->assertFileExists($escaped, 'BASELINE: traversal via filename writes outside lang/');
    }

    public function test_server_language_escapes_lang_dir_via_dot_dot(): void
    {
        // language "../../.." -> lang/../../../auth.php
        $this->fake(['auth' => ['x' => ['../../..' => 'pwned']]]);
        $this->artisan('translations:pull')->assertExitCode(0);

        $escaped = dirname($this->lang, 3).'/auth.php';
        // SAST-FLIP: after the fix this becomes assertFileDoesNotExist($escaped)
        $this->assertFileExists($escaped, 'BASELINE: traversal via language writes outside lang/');
        @unlink($escaped);
    }
}
