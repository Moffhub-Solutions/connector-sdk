<?php

declare(strict_types=1);

namespace Moffhub\ConnectorSdk\Tests\Unit;

use Moffhub\ConnectorSdk\SandboxConnector;
use Moffhub\MpsSpec\Contracts\ConnectorInterface;
use Moffhub\MpsSpec\Contracts\HasChargeCapability;
use Moffhub\MpsSpec\Data\ChargeRequest;
use Moffhub\MpsSpec\Data\MoneyAmount;
use Moffhub\MpsSpec\Enums\Capability;
use Moffhub\MpsSpec\Enums\Channel;
use Moffhub\MpsSpec\Enums\ChargeStatus;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class SandboxConnectorTest extends TestCase
{
    public function test_implements_connector_interface(): void
    {
        $connector = new SandboxConnector();

        $this->assertInstanceOf(ConnectorInterface::class, $connector);
        $this->assertInstanceOf(HasChargeCapability::class, $connector);
    }

    public function test_manifest_declares_payment_capability(): void
    {
        $manifest = (new SandboxConnector())->manifest();

        $this->assertSame('sandbox', $manifest->connectorId);
        $this->assertContains(Capability::Payment, $manifest->capabilities);
    }

    public function test_create_charge_before_initialize_throws(): void
    {
        $connector = new SandboxConnector();

        $this->expectException(RuntimeException::class);

        $connector->createCharge($this->buildRequest('254700000001'));
    }

    public function test_create_charge_with_success_phone_returns_completed(): void
    {
        $connector = new SandboxConnector();
        $connector->initialize([]);

        $response = $connector->createCharge($this->buildRequest('254700000001'));

        $this->assertSame(ChargeStatus::Completed, $response->status);
        $this->assertNotEmpty($response->vendorRef);
    }

    public function test_create_charge_with_failure_phone_returns_failed(): void
    {
        $connector = new SandboxConnector();
        $connector->initialize([]);

        $response = $connector->createCharge($this->buildRequest('254700000002'));

        $this->assertSame(ChargeStatus::Failed, $response->status);
    }

    public function test_health_check_is_healthy(): void
    {
        $connector = new SandboxConnector();
        $connector->initialize([]);

        $this->assertSame('healthy', $connector->healthCheck()->status);
    }

    private function buildRequest(string $payerIdentifier): ChargeRequest
    {
        return new ChargeRequest(
            intentId: 'test-' . uniqid(),
            amount: new MoneyAmount(10000, 'KES'),
            payerIdentifier: $payerIdentifier,
            channel: Channel::StkPush,
            callbackUrl: 'https://example.test/callback',
        );
    }
}
