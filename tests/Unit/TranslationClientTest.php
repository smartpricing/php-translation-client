<?php

namespace Smartness\TranslationClient\Tests\Unit;

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\TestCase;
use Smartness\TranslationClient\Exceptions\ApiException;
use Smartness\TranslationClient\Exceptions\AuthenticationException;
use Smartness\TranslationClient\TranslationClient;

class TranslationClientTest extends TestCase
{
    protected TranslationClient $client;

    protected function setUp(): void
    {
        parent::setUp();

        $this->client = new TranslationClient(
            'https://api.example.com',
            'test-token',
            30
        );
    }

    public function test_push_sends_translations_to_api(): void
    {
        Http::fake([
            'api.example.com/*' => Http::response([
                'success' => true,
                'data' => [
                    'summary' => [
                        'created' => 5,
                        'updated' => 3,
                        'skipped' => 0,
                        'total' => 8,
                    ],
                ],
                'message' => 'Translations pushed successfully',
            ], 200),
        ]);

        $translations = [
            'en' => [
                'messages' => [
                    'welcome' => 'Welcome',
                    'goodbye' => 'Goodbye',
                ],
            ],
        ];

        $response = $this->client->push($translations);

        Http::assertSent(function (Request $request) {
            return $request->url() === 'https://api.example.com/translation-projects/import'
                && $request->hasHeader('Authorization', 'Bearer test-token')
                && $request->method() === 'POST';
        });

        $this->assertTrue($response['success']);
        $this->assertEquals(8, $response['data']['summary']['total']);
    }

    public function test_push_language_sends_language_specific_translations(): void
    {
        Http::fake([
            'api.example.com/*' => Http::response([
                'success' => true,
                'data' => [
                    'summary' => [
                        'created' => 2,
                        'updated' => 0,
                        'skipped' => 0,
                        'total' => 2,
                    ],
                ],
            ], 200),
        ]);

        $translations = [
            'messages' => [
                'hello' => 'Hello',
            ],
        ];

        $response = $this->client->pushLanguage('en', $translations, true);

        Http::assertSent(function (Request $request) {
            $body = $request->data();

            return $request->url() === 'https://api.example.com/translation-projects/import'
                && $body['language'] === 'en'
                && $body['overwrite'] === true
                && isset($body['translations']);
        });

        $this->assertTrue($response['success']);
    }

    public function test_push_file_sends_single_file_translations(): void
    {
        Http::fake([
            'api.example.com/*' => Http::response([
                'success' => true,
                'data' => [
                    'summary' => [
                        'created' => 1,
                        'updated' => 0,
                        'skipped' => 0,
                        'total' => 1,
                    ],
                ],
            ], 200),
        ]);

        $translations = [
            'login' => 'Log in',
            'logout' => 'Log out',
        ];

        $response = $this->client->pushFile('en', 'auth', $translations);

        Http::assertSent(function (Request $request) {
            $body = $request->data();

            return $request->url() === 'https://api.example.com/translation-projects/import'
                && $body['language'] === 'en'
                && $body['filename'] === 'auth'
                && isset($body['translations']['en']['auth']);
        });

        $this->assertTrue($response['success']);
    }

    public function test_push_throws_authentication_exception_on_401(): void
    {
        Http::fake([
            'api.example.com/*' => Http::response([], 401),
        ]);

        $this->expectException(AuthenticationException::class);

        $this->client->push([]);
    }

    public function test_push_throws_api_exception_on_failed_request(): void
    {
        Http::fake([
            'api.example.com/*' => Http::response([], 500),
        ]);

        $this->expectException(ApiException::class);

        $this->client->push([]);
    }

    public function test_push_throws_api_exception_on_unsuccessful_response(): void
    {
        Http::fake([
            'api.example.com/*' => Http::response([
                'success' => false,
                'message' => 'Something went wrong',
            ], 200),
        ]);

        $this->expectException(ApiException::class);

        $this->client->push([]);
    }
}
