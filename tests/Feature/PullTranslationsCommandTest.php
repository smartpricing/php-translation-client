<?php

namespace Smartness\TranslationClient\Tests\Feature;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Smartness\TranslationClient\Tests\TestCase;

/**
 * Characterization tests for `translations:pull`. They pin the CURRENT
 * behaviour of the pull pipeline (fetch -> pivotByLanguage -> saveFile ->
 * generatePhpContent / mergePhpFile) against a mocked translation server, and
 * must stay green on every branch.
 */
class PullTranslationsCommandTest extends TestCase
{
    private string $lang;

    protected function setUp(): void
    {
        parent::setUp();
        $this->lang = sys_get_temp_dir().'/tc-pull-'.uniqid();
        File::ensureDirectoryExists($this->lang);
        config()->set('translation-client.output_dir', $this->lang);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->lang);
        parent::tearDown();
    }

    private function fakeTranslations(array $translations, array $extra = []): void
    {
        Http::fake([
            'api.example.com/*' => Http::response(array_merge([
                'success' => true,
                'data' => array_merge(['translations' => $translations], $extra),
            ], []), 200),
        ]);
    }

    public function test_pull_writes_nested_php_lang_file_from_server_data(): void
    {
        // server shape: filename -> key -> language -> value
        $this->fakeTranslations([
            'messages' => [
                'welcome' => ['en' => 'Welcome'],
                'nav.home' => ['en' => 'Home'],
            ],
        ], ['total' => 2, 'meta' => ['languages' => ['en']]]);

        $this->artisan('translations:pull')->assertExitCode(0);

        $file = $this->lang.'/en/messages.php';
        $this->assertFileExists($file);
        $data = include $file;
        $this->assertSame('Welcome', $data['welcome']);
        $this->assertSame('Home', $data['nav']['home']); // dot keys become nested arrays
    }

    public function test_pull_language_filter_only_writes_that_language(): void
    {
        $this->fakeTranslations([
            'messages' => ['hi' => ['en' => 'Hi', 'it' => 'Ciao']],
        ]);

        $this->artisan('translations:pull', ['--language' => 'it'])->assertExitCode(0);

        $this->assertFileExists($this->lang.'/it/messages.php');
        $this->assertFileDoesNotExist($this->lang.'/en/messages.php');
    }

    public function test_pull_dry_run_writes_nothing(): void
    {
        $this->fakeTranslations(['messages' => ['hi' => ['en' => 'Hi']]]);

        $this->artisan('translations:pull', ['--dry-run' => true])->assertExitCode(0);

        $this->assertFileDoesNotExist($this->lang.'/en/messages.php');
    }

    public function test_pull_skips_null_values(): void
    {
        $this->fakeTranslations(['messages' => ['a' => ['en' => 'A'], 'b' => ['en' => null]]]);

        $this->artisan('translations:pull')->assertExitCode(0);
        $data = include $this->lang.'/en/messages.php';
        $this->assertArrayHasKey('a', $data);
        $this->assertArrayNotHasKey('b', $data);
    }

    public function test_generated_php_file_is_valid_and_parseable(): void
    {
        $this->fakeTranslations(['auth' => ['failed' => ['en' => 'These credentials do not match.']]]);
        $this->artisan('translations:pull')->assertExitCode(0);
        $content = File::get($this->lang.'/en/auth.php');
        $this->assertStringStartsWith("<?php", $content);
        // must tokenize without a parse error
        $this->assertIsArray(token_get_all($content, TOKEN_PARSE));
    }
}
