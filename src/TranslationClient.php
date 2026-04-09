<?php

namespace Smartness\TranslationClient;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Smartness\TranslationClient\Exceptions\ApiException;
use Smartness\TranslationClient\Exceptions\AuthenticationException;

class TranslationClient
{
    protected string $apiUrl;

    public function __construct(
        string $apiUrl,
        protected string $apiToken,
        protected int $timeout = 30,
    ) {
        $this->apiUrl = rtrim($apiUrl, '/');
    }

    /**
     * @param  array{format?: string, language?: string, status?: string, missing?: bool, filename?: string}  $options
     *
     * @throws AuthenticationException
     * @throws ApiException
     */
    public function fetch(array $options = []): array
    {
        try {
            $response = Http::withToken($this->apiToken)
                ->acceptJson()
                ->timeout($this->timeout)
                ->get("{$this->apiUrl}/translation-projects/translations", $options);

            return $this->parseResponse($response);
        } catch (ConnectionException $e) {
            throw new ApiException("Failed to connect to SmartPMS API: {$e->getMessage()}");
        }
    }

    /**
     * Fetch translations and convert to Laravel PHP format
     */
    public function fetchAsPhp(?string $language = null): array
    {
        return $this->fetch([
            'format' => 'php',
            'language' => $language,
            'status' => config('translation-client.status_filter'),
        ]);
    }

    /**
     * Fetch translations as JSON
     */
    public function fetchAsJson(?string $language = null): array
    {
        return $this->fetch([
            'format' => 'json',
            'language' => $language,
            'status' => config('translation-client.status_filter'),
        ]);
    }

    /**
     * Fetch translations in raw format
     */
    public function fetchRaw(?string $language = null): array
    {
        return $this->fetch([
            'format' => 'raw',
            'language' => $language,
            'status' => config('translation-client.status_filter'),
        ]);
    }

    /**
     * @param  array  $translations  Translation data organized by language and filename
     * @param  array{language?: string, filename?: string, overwrite?: bool}  $options
     *
     * @throws AuthenticationException
     * @throws ApiException
     */
    public function push(array $translations, array $options = []): array
    {
        try {
            $response = Http::withToken($this->apiToken)
                ->acceptJson()
                ->timeout($this->timeout)
                ->post("{$this->apiUrl}/translation-projects/translations", array_merge($options, [
                    'translations' => $translations,
                ]));

            return $this->parseResponse($response);
        } catch (ConnectionException $e) {
            throw new ApiException("Failed to connect to SmartPMS API: {$e->getMessage()}");
        }
    }

    private function parseResponse(Response $response): array
    {
        if ($response->status() === 401) {
            throw new AuthenticationException('Invalid API token. Please check your TRANSLATION_API_TOKEN configuration.');
        }

        if ($response->failed()) {
            throw new ApiException(
                "API request failed with status {$response->status()}: {$response->body()}"
            );
        }

        $data = $response->json();

        if (! isset($data['success']) || ! $data['success']) {
            throw new ApiException('API returned unsuccessful response');
        }

        return $data;
    }

    /**
     * Push translations for a specific language
     */
    public function pushLanguage(string $language, array $translations, bool $overwrite = false): array
    {
        return $this->push($translations, [
            'language' => $language,
            'overwrite' => $overwrite,
        ]);
    }

    /**
     * Push translations for a specific file
     */
    public function pushFile(string $language, string $filename, array $translations, bool $overwrite = false): array
    {
        return $this->push(
            [$language => [$filename => $translations]],
            [
                'language' => $language,
                'filename' => $filename,
                'overwrite' => $overwrite,
            ]
        );
    }

    /**
     * Check if API token is valid
     */
    public function testConnection(): bool
    {
        try {
            $this->fetch(['format' => 'json']);

            return true;
        } catch (\Exception $e) {
            return false;
        }
    }
}
