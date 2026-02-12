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
     *
     * @type string $format Output format (json|php|raw)
     * @type string $language Filter by language code
     * @type string $status Filter by status (approved|pending|rejected)
     * @type bool $missing Include only missing translations
     * @type string $filename Filter by filename
     *              }
     *
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
                ->get("{$this->apiUrl}/translation-projects/translations", $options);

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
     * Push translations to the API
     *
     * @param  array  $translations  Translation data organized by language and filename
     * @param  array  $options  {
     *
     * @type string $language Language code for single language push
     * @type string $filename Filename for single file push
     * @type bool $overwrite Whether to overwrite existing translations
     *            }
     *
     * @return array API response data
     *
     * @throws AuthenticationException
     * @throws ApiException
     */
    public function push(array $translations, array $options = []): array
    {
        try {
            $payload = array_merge($options, [
                'translations' => $translations,
            ]);

            $response = Http::withToken($this->apiToken)
                ->timeout($this->timeout)
                ->post("{$this->apiUrl}/translation-projects/translations", $payload);

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

        } catch (ConnectionException $e) {
            throw new ApiException("Failed to connect to SmartPMS API: {$e->getMessage()}");
        }
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
