<?php

namespace Smartness\TranslationClient;

use Illuminate\Support\ServiceProvider;
use Smartness\TranslationClient\Commands\PullTranslationsCommand;

class TranslationClientServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../config/translation-client.php',
            'translation-client'
        );

        $this->app->singleton(TranslationClient::class, function ($app) {
            return new TranslationClient(
                config('translation-client.api_url'),
                config('translation-client.api_token')
            );
        });
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            // Publish configuration
            $this->publishes([
                __DIR__ . '/../config/translation-client.php' => config_path('translation-client.php'),
            ], 'translation-client-config');

            // Register commands
            $this->commands([
                PullTranslationsCommand::class,
            ]);
        }
    }
}
