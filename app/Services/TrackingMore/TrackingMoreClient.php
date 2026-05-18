<?php

namespace App\Services\TrackingMore;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;

class TrackingMoreClient
{
    protected Client $http;

    public function __construct(
        protected ?string $apiKey = null,
        protected ?string $baseUrl = null,
        protected ?float $timeout = null,
    ) {
        $this->apiKey ??= config('services.trackingmore.key');
        $this->baseUrl = rtrim($this->baseUrl ?: config('services.trackingmore.base_url', 'https://api.trackingmore.com/v4'), '/');
        $this->timeout ??= (float) config('services.trackingmore.timeout', 20);

        $this->http = new Client([
            'base_uri' => $this->baseUrl . '/',
            'timeout' => $this->timeout,
            'headers' => [
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
                'Tracking-Api-Key' => (string) $this->apiKey,
            ],
        ]);
    }

    public function request(string $method, string $uri, array $options = []): array
    {
        if (blank($this->apiKey)) {
            throw new TrackingMoreException('TrackingMore API key is not configured.');
        }

        try {
            $response = $this->http->request($method, ltrim($uri, '/'), $options);
            $payload = json_decode((string) $response->getBody(), true);

            if (! is_array($payload)) {
                throw new TrackingMoreException('TrackingMore returned an invalid JSON response.', $response->getStatusCode());
            }

            $this->throwIfApiError($payload, $response->getStatusCode());

            return $payload;
        } catch (RequestException $e) {
            $payload = $e->hasResponse()
                ? json_decode((string) $e->getResponse()->getBody(), true)
                : null;

            if (is_array($payload)) {
                $message = data_get($payload, 'meta.message')
                    ?: data_get($payload, 'message')
                    ?: $e->getMessage();

                throw new TrackingMoreException(
                    $message,
                    $e->getResponse()?->getStatusCode(),
                    data_get($payload, 'meta.code'),
                    $payload
                );
            }

            throw new TrackingMoreException($e->getMessage(), $e->getResponse()?->getStatusCode(), response: []);
        } catch (GuzzleException $e) {
            throw new TrackingMoreException($e->getMessage());
        }
    }

    protected function throwIfApiError(array $payload, int $httpStatus): void
    {
        $metaCode = data_get($payload, 'meta.code');

        if ($httpStatus >= 200 && $httpStatus < 300 && ((int) $metaCode === 200 || $metaCode === null)) {
            return;
        }

        if ((int) $metaCode === 4101) {
            return;
        }

        throw new TrackingMoreException(
            data_get($payload, 'meta.message', 'TrackingMore request failed.'),
            $httpStatus,
            $metaCode,
            $payload
        );
    }
}
