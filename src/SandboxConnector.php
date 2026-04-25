<?php

declare(strict_types=1);

namespace Moffhub\ConnectorSdk;

use Moffhub\MpsSpec\Contracts\HasChargeCapability;
use Moffhub\MpsSpec\Contracts\HasRefundCapability;
use Moffhub\MpsSpec\Contracts\HasWebhookCapability;
use Moffhub\MpsSpec\Data\ChargeRequest;
use Moffhub\MpsSpec\Data\ChargeResponse;
use Moffhub\MpsSpec\Data\ConfigField;
use Moffhub\MpsSpec\Data\ConnectorManifest;
use Moffhub\MpsSpec\Data\HealthStatus;
use Moffhub\MpsSpec\Data\MoneyAmount;
use Moffhub\MpsSpec\Data\RefundResponse;
use Moffhub\MpsSpec\Data\WebhookResult;
use Moffhub\MpsSpec\Enums\Capability;
use Moffhub\MpsSpec\Enums\Channel;
use Moffhub\MpsSpec\Enums\ChargeStatus;
use Moffhub\MpsSpec\Enums\SettlementModel;

class SandboxConnector extends BaseConnector implements HasChargeCapability, HasRefundCapability, HasWebhookCapability
{
    private const array TEST_PHONE_RESULTS = [
        '254700000001' => ChargeStatus::Completed,  // Always succeeds
        '254700000002' => ChargeStatus::Failed,      // Always fails
        '254700000003' => ChargeStatus::Pending,     // Always pending (timeout test)
    ];

    private const array TEST_CARD_RESULTS = [
        '4242424242424242' => ChargeStatus::Completed,  // Visa success
        '4000000000000002' => ChargeStatus::Failed,      // Visa decline
        '5555555555554444' => ChargeStatus::Completed,  // Mastercard success
    ];

    public function manifest(): ConnectorManifest
    {
        return new ConnectorManifest(
            connectorId: 'sandbox',
            displayName: 'Sandbox (Test Mode)',
            version: '0.1.0',
            specVersion: '0.1.0',
            vendorName: 'PayOrchestra',
            vendorWebsite: 'https://payorchestra.com',
            vendorSupportEmail: 'support@payorchestra.com',
            supportedChannels: [Channel::StkPush, Channel::Card, Channel::BankTransfer, Channel::MobileMoney],
            supportedCurrencies: ['KES', 'NGN', 'USD', 'GHS', 'ZAR', 'UGX', 'TZS'],
            capabilities: [Capability::Payment, Capability::Refund, Capability::Webhook],
            settlementModel: SettlementModel::VendorLed,
            requiredConfig: [
                new ConfigField(key: 'mode', label: 'Default Outcome', type: 'select', required: false, description: 'success, fail, or timeout'),
            ],
            webhookEvents: ['payment.completed', 'payment.failed'],
        );
    }

    public function createCharge(ChargeRequest $request): ChargeResponse
    {
        $this->ensureInitialized();

        $outcome = $this->determineOutcome($request);
        $vendorRef = 'sandbox-'.uniqid();

        // Simulate processing delay
        usleep(rand(100_000, 500_000));

        if ($outcome === ChargeStatus::Completed) {
            return new ChargeResponse(
                vendorRef: $vendorRef,
                status: ChargeStatus::Completed,
                channelData: ['sandbox' => true, 'simulated' => true],
            );
        }

        if ($outcome === ChargeStatus::Failed) {
            return new ChargeResponse(
                vendorRef: $vendorRef,
                status: ChargeStatus::Failed,
                channelData: ['sandbox' => true, 'reason' => 'Simulated failure'],
            );
        }

        // Pending — simulates async flow (webhook will complete it)
        return new ChargeResponse(
            vendorRef: $vendorRef,
            status: ChargeStatus::Pending,
            channelData: ['sandbox' => true, 'note' => 'Waiting for simulated callback'],
            estimatedCompletionSeconds: 5,
        );
    }

    public function queryCharge(string $chargeId): ChargeResponse
    {
        $this->ensureInitialized();

        return new ChargeResponse(
            vendorRef: $chargeId,
            status: ChargeStatus::Completed,
            channelData: ['sandbox' => true],
        );
    }

    public function refund(string $chargeId, MoneyAmount $amount): RefundResponse
    {
        $this->ensureInitialized();

        return new RefundResponse(
            vendorRef: 'sandbox-refund-'.uniqid(),
            status: 'completed',
            amount: $amount,
        );
    }

    /**
     * @param  array<string, string>  $headers
     */
    public function handleWebhook(array $headers, mixed $body): WebhookResult
    {
        $this->ensureInitialized();

        $data = is_string($body) ? json_decode($body, true) : $body;
        $data = is_array($data) ? $data : [];

        /** @var string $intentId */
        $intentId = $data['intent_id'] ?? '';
        /** @var string $vendorRef */
        $vendorRef = $data['vendor_ref'] ?? 'sandbox-'.uniqid();
        /** @var string $status */
        $status = $data['status'] ?? 'completed';
        /** @var int|string $amount */
        $amount = $data['amount'] ?? 0;
        /** @var string $currency */
        $currency = $data['currency'] ?? 'KES';

        return new WebhookResult(
            intentId: $intentId,
            vendorRef: $vendorRef,
            status: ChargeStatus::from($status),
            amount: new MoneyAmount((int) $amount, $currency),
            rawPayload: $data,
        );
    }

    public function healthCheck(): HealthStatus
    {
        return new HealthStatus(
            status: 'healthy',
            message: 'Sandbox connector is always healthy',
            latencyMs: 1.0,
        );
    }

    private function determineOutcome(ChargeRequest $request): ChargeStatus
    {
        // Check test phone numbers
        if (isset(self::TEST_PHONE_RESULTS[$request->payerIdentifier])) {
            return self::TEST_PHONE_RESULTS[$request->payerIdentifier];
        }

        // Check test card numbers
        $card = $request->metadata['card_number'] ?? '';
        if (isset(self::TEST_CARD_RESULTS[$card])) {
            return self::TEST_CARD_RESULTS[$card];
        }

        // Default based on config
        $mode = $this->getConfig('mode', 'success');

        return match ($mode) {
            'fail' => ChargeStatus::Failed,
            'timeout' => ChargeStatus::Pending,
            default => ChargeStatus::Completed,
        };
    }
}
