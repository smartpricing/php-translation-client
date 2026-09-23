<?php

namespace Smartness\TranslationClient\Tests\Feature;

use Smartness\TranslationClient\TranslationClient;
use Smartness\TranslationClient\Tests\TestCase;

/**
 * Regression tests for the runtime/CI hardening (security/dast-hardening):
 * https-by-default, the configured timeout is actually wired into the client,
 * and the CI action is pinned to an immutable SHA.
 */
class HardeningTest extends TestCase
{
    public function test_packaged_config_default_api_url_is_https(): void
    {
        $config = require __DIR__.'/../../config/translation-client.php';
        // env() is unset in this context, so this is the shipped default.
        $this->assertStringStartsWith('https://', $config['api_url']);
    }

    public function test_provider_wires_configured_timeout_into_client(): void
    {
        config()->set('translation-client.timeout', 7);
        // Rebind so the singleton picks up the new config value.
        $this->app->forgetInstance(TranslationClient::class);

        $client = $this->app->make(TranslationClient::class);

        $ref = new \ReflectionProperty($client, 'timeout');
        $this->assertSame(7, $ref->getValue($client));
    }

    public function test_client_exposes_a_connect_timeout(): void
    {
        $client = $this->app->make(TranslationClient::class);
        $ref = new \ReflectionProperty($client, 'connectTimeout');
        $this->assertGreaterThan(0, $ref->getValue($client));
    }

    public function test_ci_action_is_pinned_to_a_commit_sha(): void
    {
        $yml = file_get_contents(__DIR__.'/../../.github/workflows/auto-tag.yml');
        // No mutable "@vN" / "@main" action refs may remain.
        $this->assertDoesNotMatchRegularExpression('/uses:\s*\S+@(v\d+|main|master)\s*$/m', $yml);
        // The checkout action must be pinned to a 40-char SHA.
        $this->assertMatchesRegularExpression('/uses:\s*actions\/checkout@[0-9a-f]{40}\b/', $yml);
    }
}
