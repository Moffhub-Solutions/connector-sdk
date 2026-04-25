<?php

declare(strict_types=1);

namespace Moffhub\ConnectorSdk\Support;

use Moffhub\MpsSpec\Exceptions\WebhookVerificationFailedException;

class WebhookVerifier
{
    public static function verifyHmacSha256(string $payload, string $signature, string $secret): void
    {
        $computed = hash_hmac('sha256', $payload, $secret);

        if (!hash_equals($computed, $signature)) {
            throw new WebhookVerificationFailedException('HMAC-SHA256 signature verification failed.');
        }
    }

    public static function verifyHmacSha512(string $payload, string $signature, string $secret): void
    {
        $computed = hash_hmac('sha512', $payload, $secret);

        if (!hash_equals($computed, $signature)) {
            throw new WebhookVerificationFailedException('HMAC-SHA512 signature verification failed.');
        }
    }

    public static function verifyTimestamp(string $timestamp, int $maxAgeSeconds = 300): void
    {
        $requestTime = strtotime($timestamp);

        if ($requestTime === false) {
            throw new WebhookVerificationFailedException('Invalid timestamp format.');
        }

        if (abs(time() - $requestTime) > $maxAgeSeconds) {
            throw new WebhookVerificationFailedException('Webhook timestamp is too old (replay protection).');
        }
    }
}
