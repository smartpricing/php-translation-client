<?php

namespace Smartness\TranslationClient\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use Smartness\TranslationClient\TranslationClientServiceProvider;

/**
 * Base test case that boots a minimal Laravel application (via Orchestra
 * Testbench) so the package's facades (Http, File, config, artisan) work.
 *
 * The committed suite extended PHPUnit\Framework\TestCase directly, so every
 * facade-using test errored with "A facade root has not been set". These
 * tests replace that harness.
 */
abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [TranslationClientServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('translation-client.api_url', 'https://api.example.com');
        $app['config']->set('translation-client.api_token', 'test-token');
    }
}
