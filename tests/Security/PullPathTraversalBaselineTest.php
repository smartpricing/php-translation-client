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
 * As of the security/sast fix these are REGRESSION tests: the command
 * validates the server-supplied language/filename as single safe path segments
 * and refuses to write outside the output directory.
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

    public function test_server_filename_traversal_is_blocked(): void
    {
        // filename "../../evil" -> lang/en/../../evil.php == base/evil.php
        $this->fake(['../../evil' => ['x' => ['en' => 'pwned']]]);
        $this->artisan('translations:pull')->assertExitCode(0);

        $escaped = $this->base.'/evil.php';
        $this->assertFileDoesNotExist($escaped, 'traversal via filename must not write outside lang/');
    }

    public function test_server_language_traversal_is_blocked(): void
    {
        // language "../../.." -> lang/../../../auth.php
        $this->fake(['auth' => ['x' => ['../../..' => 'pwned']]]);
        $this->artisan('translations:pull')->assertExitCode(0);

        $escaped = dirname($this->lang, 3).'/auth.php';
        $this->assertFileDoesNotExist($escaped, 'traversal via language must not write outside lang/');
    }
}
