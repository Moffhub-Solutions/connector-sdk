<?php

declare(strict_types=1);

namespace Moffhub\ConnectorSdk\Support;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Moffhub\MpsSpec\Exceptions\ConnectorException;

class HttpClient
{
    private Client $client;

    public function __construct(
        private readonly string $baseUrl,
        private readonly array $defaultHeaders = [],
        private readonly int $timeout = 30,
    ) {
        $this->client = new Client([
            'base_uri' => $this->baseUrl,
            'timeout' => $this->timeout,
            'headers' => $this->defaultHeaders,
        ]);
    }

    public function get(string $uri, array $query = []): array
    {
        return $this->request('GET', $uri, ['query' => $query]);
    }

    public function post(string $uri, array $data = []): array
    {
        return $this->request('POST', $uri, ['json' => $data]);
    }

    private function request(string $method, string $uri, array $options = []): array
    {
        try {
            $response = $this->client->request($method, $uri, $options);
            $body = $response->getBody()->getContents();

            return json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        } catch (GuzzleException $e) {
            throw new ConnectorException("HTTP request failed: {$e->getMessage()}", 0, $e);
        } catch (\JsonException $e) {
            throw new ConnectorException("Failed to decode response: {$e->getMessage()}", 0, $e);
        }
    }
}
