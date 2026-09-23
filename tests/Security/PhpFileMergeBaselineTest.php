<?php

namespace Smartness\TranslationClient\Tests\Security;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Group;
use Smartness\TranslationClient\Tests\TestCase;

/**
 * SECURITY BASELINE (@group security-baseline).
 *
 * PullTranslationsCommand::mergePhpFile updates an existing lang file by
 * running preg_replace() with a replacement string built from the
 * server-supplied value: '${1}' . var_export($newValue). preg_replace()
 * interprets back-reference tokens ($0, $1, \1, ${1}) *inside the server
 * value*, so a value that contains "$0"/"$1" is expanded into the written
 * file — the server controls bytes that are NOT passed through var_export.
 * This corrupts the generated PHP (and is the seed of a code-generation
 * injection when combined with the raw-content rewrite).
 *
 * The security/sast PR rebuilds the merged file from a plain array via
 * var_export (no preg_replace on server data) and REPLACES the assertions
 * below (SAST-FLIP): the merged value then equals the server value exactly.
 */
#[Group('security-baseline')]
class PhpFileMergeBaselineTest extends TestCase
{
    private string $lang;

    protected function setUp(): void
    {
        parent::setUp();
        $this->lang = sys_get_temp_dir().'/tc-merge-'.uniqid();
        File::ensureDirectoryExists($this->lang.'/en');
        config()->set('translation-client.output_dir', $this->lang);
        // Existing file so mergePhpFile() (not generatePhpContent) runs.
        File::put($this->lang.'/en/messages.php', "<?php\n\nreturn [\n    'greeting' => 'Hello',\n];\n");
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->lang);
        parent::tearDown();
    }

    public function test_backreference_in_server_value_corrupts_merged_value(): void
    {
        $payload = 'a $0 b'; // contains a preg back-reference token
        Http::fake(['api.example.com/*' => Http::response([
            'success' => true,
            'data' => ['translations' => ['messages' => ['greeting' => ['en' => $payload]]]],
        ], 200)]);

        $this->artisan('translations:pull')->assertExitCode(0);

        $content = File::get($this->lang.'/en/messages.php');
        // BASELINE: the back-reference $0 expands into the raw file, corrupting
        // it so it no longer parses as PHP.
        // SAST-FLIP: assertTrue($this->parses($content));
        //            $merged = include $this->lang.'/en/messages.php';
        //            assertSame($payload, $merged['greeting']);
        $this->assertFalse($this->parses($content),
            'BASELINE: preg_replace expands back-references from the server value, corrupting the file');
    }

    private function parses(string $php): bool
    {
        try {
            token_get_all($php, TOKEN_PARSE);
            return true;
        } catch (\ParseError $e) {
            return false;
        }
    }
}
