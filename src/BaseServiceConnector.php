<?php

declare(strict_types=1);

namespace Moffhub\ConnectorSdk;

use Moffhub\MpsSpec\Contracts\ServiceConnectorInterface;
use Moffhub\MpsSpec\Data\HealthStatus;

abstract class BaseServiceConnector implements ServiceConnectorInterface
{
    /** @var array<string, mixed> */
    protected array $config = [];

    protected bool $initialized = false;

    /**
     * @param  array<string, mixed>  $config
     */
    public function initialize(array $config): void
    {
        $this->config = $config;
        $this->validateConfig($config);
        $this->initialized = true;
    }

    public function destroy(): void
    {
        $this->config = [];
        $this->initialized = false;
    }

    protected function ensureInitialized(): void
    {
        if (!$this->initialized) {
            throw new \RuntimeException('Service connector has not been initialized.');
        }
    }

    protected function getConfig(string $key, mixed $default = null): mixed
    {
        return $this->config[$key] ?? $default;
    }

    protected function requireConfig(string $key): mixed
    {
        if (!isset($this->config[$key])) {
            throw new \InvalidArgumentException("Required config [{$key}] is missing.");
        }

        return $this->config[$key];
    }

    /**
     * @param  array<string, mixed>  $config
     */
    protected function validateConfig(array $config): void
    {
        $manifest = $this->manifest();

        foreach ($manifest->requiredConfig as $field) {
            if ($field->required && !isset($config[$field->key])) {
                throw new \InvalidArgumentException("Required config [{$field->key}] is missing.");
            }
        }
    }

    public function healthCheck(): HealthStatus
    {
        $this->ensureInitialized();

        return new HealthStatus(status: 'healthy');
    }
}
