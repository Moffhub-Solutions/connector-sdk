# Moffhub Connector SDK

Base classes and helpers for building **MPS-compliant payment and service connectors**. Extend `BaseConnector`, declare your manifest, implement the capabilities you support, and you're done — config validation, lifecycle, and HTTP plumbing are handled for you.

This package implements the contracts defined in [`moffhub/mps-spec`](https://packagist.org/packages/moffhub/mps-spec).

## Installation

```bash
composer require moffhub/connector-sdk
```

Requires PHP 8.3+. Pulls in `moffhub/mps-spec` and `guzzlehttp/guzzle`.

## What you get

- **`BaseConnector`** — abstract base for **payment connectors**. Implements `ConnectorInterface` (initialize/healthCheck/destroy) and validates config against the manifest.
- **`BaseServiceConnector`** — abstract base for **service connectors** (domain logic providers).
- **`SandboxConnector`** — in-memory connector for tests and local development. Use it as a stand-in when you don't want to hit a real provider.
- **`Support\HttpClient`** — thin Guzzle wrapper with sensible defaults (timeouts, JSON, retries) for talking to provider APIs.
- **`Support\WebhookVerifier`** — HMAC signature verification helper for webhook endpoints.

## Quick start: a payment connector

```php
use Moffhub\ConnectorSdk\BaseConnector;
use Moffhub\MpsSpec\Contracts\HasChargeCapability;
use Moffhub\MpsSpec\Data\{ChargeRequest, ChargeResponse, ConfigField, ConnectorManifest};
use Moffhub\MpsSpec\Enums\{Capability, Channel, ChargeStatus, SettlementModel};

final class AcmeConnector extends BaseConnector implements HasChargeCapability
{
    public function manifest(): ConnectorManifest
    {
        return new ConnectorManifest(
            connectorId: 'acme',
            displayName: 'Acme Payments',
            version: '1.0.0',
            specVersion: '0.1',
            vendorName: 'Acme Inc.',
            vendorWebsite: 'https://acme.example',
            vendorSupportEmail: 'support@acme.example',
            supportedChannels: [Channel::Card],
            supportedCurrencies: ['USD'],
            capabilities: [Capability::Payment],
            settlementModel: SettlementModel::T1,
            requiredConfig: [
                new ConfigField(key: 'api_key', label: 'API Key', required: true, secret: true),
                new ConfigField(key: 'environment', label: 'Environment', required: true),
            ],
        );
    }

    public function createCharge(ChargeRequest $request): ChargeResponse
    {
        $this->ensureInitialized();

        // requireConfig() throws if the key is missing
        $apiKey = $this->requireConfig('api_key');

        // ... call provider API, return ChargeResponse
        return new ChargeResponse(/* ... */);
    }

    public function queryCharge(string $chargeId): ChargeResponse
    {
        $this->ensureInitialized();
        // ...
    }
}
```

### Wiring it up

```php
$connector = new AcmeConnector();
$connector->initialize([
    'api_key' => $_ENV['ACME_KEY'],
    'environment' => 'live',
]);

$response = $connector->createCharge(new ChargeRequest(/* ... */));
```

`initialize()` validates the supplied config against `requiredConfig` from your manifest and throws `InvalidArgumentException` if anything required is missing.

## Helpers

### `HttpClient` — talking to providers

```php
use Moffhub\ConnectorSdk\Support\HttpClient;

$client = new HttpClient(baseUri: 'https://api.acme.example', timeout: 10);
$response = $client->post('/charges', [
    'headers' => ['Authorization' => "Bearer {$apiKey}"],
    'json' => $payload,
]);
```

### `WebhookVerifier` — verifying inbound webhooks

```php
use Moffhub\ConnectorSdk\Support\WebhookVerifier;

$verifier = new WebhookVerifier(secret: $this->requireConfig('webhook_secret'));
$verifier->verify(rawBody: $request->getContent(), signature: $request->header('X-Signature'));
// Throws WebhookVerificationFailedException on mismatch
```

### `SandboxConnector` — in-memory connector for tests

```php
use Moffhub\ConnectorSdk\SandboxConnector;

$connector = new SandboxConnector();
$connector->initialize([]);
$response = $connector->createCharge($request); // returns deterministic fake data
```

## Service connectors

Use `BaseServiceConnector` instead of `BaseConnector` for service connectors (domain logic providers — utility lookups, ticketing, etc.). The lifecycle is the same; the contract is `ServiceConnectorInterface` from `moffhub/mps-spec`.

## Testing your connector

The Moffhub CLI (`moffhub/cli`) ships a certification suite that exercises your connector against the spec — manifest validation, capability conformance, lifecycle, and contract tests. See its README for usage.

## License

MIT. See `LICENSE`.
