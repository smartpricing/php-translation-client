<?php

namespace Smartness\TranslationClient;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Smartness\TranslationClient\Exceptions\ApiException;
use Smartness\TranslationClient\Exceptions\AuthenticationException;

class TranslationClient
{
    protected string $apiUrl;

    protected string $apiToken;

    protected int $timeout;

    public function __construct(string $apiUrl, string $apiToken, int $timeout = 30)
    {
        $this->apiUrl = rtrim($apiUrl, '/');
        $this->apiToken = $apiToken;
        $this->timeout = $timeout;
    }

    /**
     * Fetch translations from the API
     *
     * @param  array  $options  {
     *     @type string $format Output format (json|php|raw)
     *     @type string $language Filter by language code
     *     @type string $status Filter by status (approved|pending|rejected)
     *     @type bool $missing Include only missing translations
     *     @type string $filename Filter by filename
     * }
     * @return array API response data
     *
     * @throws AuthenticationException
     * @throws ApiException
     */
    public function fetch(array $options = []): array
    {
        try {
            $response = Http::withToken($this->apiToken)
                ->timeout($this->timeout)
                ->get("{$this->apiUrl}/translation-projects/export", $options);

            if ($response->status() === 401) {
                throw new AuthenticationException('Invalid API token. Please check your SMARTPMS_TRANSLATION_TOKEN configuration.');
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
