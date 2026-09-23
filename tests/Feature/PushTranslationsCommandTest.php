<?php

namespace Smartness\TranslationClient\Tests\Feature;

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Smartness\TranslationClient\Tests\TestCase;

/**
 * Characterization tests for `translations:push`: read local lang files ->
 * pivotForApi (filename -> key -> language -> value) -> POST chunked by file.
 */
class PushTranslationsCommandTest extends TestCase
{
    private string $lang;

    protected function setUp(): void
    {
        parent::setUp();
        $this->lang = sys_get_temp_dir().'/tc-push-'.uniqid();
        File::ensureDirectoryExists($this->lang.'/en');
        File::put($this->lang.'/en/messages.php', "<?php\n\nreturn [\n    'welcome' => 'Welcome',\n    'nav' => ['home' => 'Home'],\n];\n");
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->lang);
        parent::tearDown();
    }

    public function test_push_posts_flattened_translations_pivoted_by_file(): void
    {
        Http::fake([
            'api.example.com/*' => Http::response([
                'success' => true,
                'data' => ['summary' => ['created' => 2, 'updated' => 0, 'skipped' => 0, 'total' => 2]],
            ], 200),
        ]);

        $this->artisan('translations:push', ['--dir' => $this->lang, '--overwrite' => true])
            ->expectsConfirmation('Do you want to push these translations?', 'yes')
            ->assertExitCode(0);

        Http::assertSent(function (Request $request): bool {
            $body = $request->data();
            return $request->url() === 'https://api.example.com/translation-projects/translations'
                && $request->method() === 'POST'
                && $request->hasHeader('Authorization', 'Bearer test-token')
                // pivotForApi: messages -> welcome -> en -> Welcome ; nav.home dot-flattened
                && ($body['translations']['messages']['welcome']['en'] ?? null) === 'Welcome'
                && ($body['translations']['messages']['nav.home']['en'] ?? null) === 'Home';
        });
    }

    public function test_push_dry_run_sends_no_request(): void
    {
        Http::fake();
        $this->artisan('translations:push', ['--dir' => $this->lang, '--dry-run' => true])->assertExitCode(0);
        Http::assertNothingSent();
    }
}
