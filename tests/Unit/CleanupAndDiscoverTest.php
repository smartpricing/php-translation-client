<?php

namespace Smartness\TranslationClient\Tests\Unit;

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\TestCase;
use Smartness\TranslationClient\TranslationClient;

class CleanupAndDiscoverTest extends TestCase
{
    protected TranslationClient $client;

    protected function setUp(): void
    {
        parent::setUp();
        $this->client = new TranslationClient('https://api.example.com', 'test-token', 30);
    }

    public function test_cleanup_sends_used_keys_and_prefixes(): void
    {
        Http::fake([
            'api.example.com/*' => Http::response([
                'success' => true,
                'data' => [
                    'remote_total' => 10,
                    'used_received' => 7,
                    'prefixes_received' => 1,
                    'protected_by_prefix' => 2,
                    'unused' => ['foo.bar'],
                    'deleted' => 0,
                ],
            ], 200),
        ]);

        $response = $this->client->cleanup(['a', 'b', 'c'], ['amenities.'], false);

        $this->assertTrue($response['success']);
        $this->assertSame(['foo.bar'], $response['data']['unused']);

        Http::assertSent(function (Request $request): bool {
            return $request->url() === 'https://api.example.com/translation-projects/translations/cleanup'
                && $request->method() === 'POST'
                && $request->hasHeader('Authorization', 'Bearer test-token')
                && $request['used_keys'] === ['a', 'b', 'c']
                && $request['used_prefixes'] === ['amenities.']
                && $request['delete'] === false;
        });
    }

    public function test_cleanup_forwards_delete_flag(): void
    {
        Http::fake([
            'api.example.com/*' => Http::response([
                'success' => true,
                'data' => ['remote_total' => 1, 'used_received' => 0, 'unused' => ['x'], 'deleted' => 1],
            ], 200),
        ]);

        $this->client->cleanup([], [], true);

        Http::assertSent(fn (Request $request) => $request['delete'] === true);
    }

    public function test_discover_sends_used_keys(): void
    {
        Http::fake([
            'api.example.com/*' => Http::response([
                'success' => true,
                'data' => [
                    'remote_total' => 5,
                    'used_received' => 6,
                    'prefixes_received' => 0,
                    'missing' => ['new.one'],
                    'inserted' => 0,
                ],
            ], 200),
        ]);

        $response = $this->client->discoverMissing(['a', 'new.one'], [], false);

        $this->assertSame(['new.one'], $response['data']['missing']);

        Http::assertSent(function (Request $request): bool {
            return $request->url() === 'https://api.example.com/translation-projects/translations/discover'
                && $request->method() === 'POST'
                && $request['used_keys'] === ['a', 'new.one']
                && $request['insert'] === false;
        });
    }

    public function test_discover_forwards_insert_flag(): void
    {
        Http::fake([
            'api.example.com/*' => Http::response([
                'success' => true,
                'data' => ['remote_total' => 0, 'used_received' => 1, 'missing' => [], 'inserted' => 1],
            ], 200),
        ]);

        $this->client->discoverMissing(['x'], [], true);

        Http::assertSent(fn (Request $request) => $request['insert'] === true);
    }

    public function test_fetch_project_config_returns_data(): void
    {
        Http::fake([
            'api.example.com/translation-projects/config' => Http::response([
                'success' => true,
                'data' => [
                    'scan_dirs' => ['app', 'server'],
                    'scan_extensions' => ['ts', 'vue'],
                    'scan_key_pattern' => null,
                    'scan_prefix_pattern' => null,
                ],
            ], 200),
        ]);

        $config = $this->client->fetchProjectConfig();

        $this->assertNotNull($config);
        $this->assertSame(['app', 'server'], $config['scan_dirs']);
        $this->assertSame(['ts', 'vue'], $config['scan_extensions']);
    }

    public function test_fetch_project_config_returns_null_on_404(): void
    {
        Http::fake([
            'api.example.com/*' => Http::response('', 404),
        ]);

        $this->assertNull($this->client->fetchProjectConfig());
    }

    public function test_fetch_project_config_returns_null_on_failure(): void
    {
        Http::fake([
            'api.example.com/*' => Http::response('boom', 500),
        ]);

        $this->assertNull($this->client->fetchProjectConfig());
    }
}
