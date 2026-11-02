<?php

declare(strict_types=1);

namespace Contabo\Whmcs;

use InvalidArgumentException;
use RuntimeException;

final class ModuleConfig
{
    public function __construct(
        public readonly string $clientId,
        public readonly string $clientSecret,
        public readonly string $apiUser,
        public readonly string $apiPassword,
        public readonly string $region,
        public readonly string $productId,
        public readonly string $imageId,
        public readonly string $hostnamePrefix,
        public readonly int $contractPeriod,
        public readonly bool $clientPowerEnabled,
        public readonly bool $automaticCancellationEnabled,
    ) {
        foreach (['clientId', 'clientSecret', 'apiUser', 'apiPassword'] as $field) {
            if (trim($this->{$field}) === '') {
                throw new InvalidArgumentException('Contabo API credentials are incomplete.');
            }
        }
        if (filter_var($this->apiUser, FILTER_VALIDATE_EMAIL) === false) {
            throw new InvalidArgumentException('The Contabo API user must be an email address.');
        }
        if (!preg_match('/^[A-Za-z][A-Za-z0-9-]{1,31}$/', $this->region)) {
            throw new InvalidArgumentException('The Contabo region code is invalid.');
        }
        if (!preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]{1,63}$/', $this->productId)) {
            throw new InvalidArgumentException('The Contabo product ID is invalid.');
        }
        if (!preg_match('/^[0-9a-fA-F-]{32,36}$/', $this->imageId)) {
            throw new InvalidArgumentException('The Contabo image ID is invalid.');
        }
        if (!preg_match('/^[a-z0-9](?:[a-z0-9-]{0,30}[a-z0-9])?$/', $this->hostnamePrefix)) {
            throw new InvalidArgumentException('The hostname prefix must be lowercase letters, digits, or hyphens.');
        }
        if (!in_array($this->contractPeriod, [1, 3, 6, 12], true)) {
            throw new InvalidArgumentException('The contract period must be 1, 3, 6, or 12 months.');
        }
    }

    /** @param array<string, mixed> $params */
    public static function fromWhmcs(array $params): self
    {
        return new self(
            clientId: self::requiredString($params, 'configoption1'),
            clientSecret: self::requiredString($params, 'configoption2'),
            apiUser: self::requiredString($params, 'configoption3'),
            apiPassword: self::requiredString($params, 'configoption4'),
            region: self::regionCode((string) ($params['configoption5'] ?? 'EU')),
            productId: trim((string) ($params['configoption6'] ?? '')),
            imageId: trim((string) ($params['configoption7'] ?? '')),
            hostnamePrefix: strtolower(trim((string) ($params['configoption8'] ?? 'whmcs'))),
            contractPeriod: (int) ($params['configoption9'] ?? 1),
            clientPowerEnabled: self::enabled($params['configoption10'] ?? false),
            automaticCancellationEnabled: self::enabled($params['configoption11'] ?? false),
        );
    }

    /** @param array<string, mixed> $params */
    private static function requiredString(array $params, string $key): string
    {
        return trim((string) ($params[$key] ?? ''));
    }

    private static function regionCode(string $value): string
    {
        $value = trim($value);
        if (preg_match('/\(([A-Za-z0-9-]+)\)\s*$/', $value, $matches) === 1) {
            return $matches[1];
        }
        return $value;
    }

    private static function enabled(mixed $value): bool
    {
        return in_array(strtolower(trim((string) $value)), ['1', 'on', 'true', 'yes'], true);
    }

    /** @return list<string> */
    public function secrets(): array
    {
        return [$this->clientId, $this->clientSecret, $this->apiUser, $this->apiPassword];
    }
}

final class Instance
{
    /** @param array<string, mixed> $raw */
    public function __construct(
        public readonly int $id,
        public readonly string $displayName,
        public readonly string $status,
        public readonly ?string $ipv4,
        public readonly array $raw,
    ) {
        if ($this->id <= 0) {
            throw new InvalidArgumentException('Contabo returned an invalid instance ID.');
        }
    }

    /** @param array<string, mixed> $data */
    public static function fromApi(array $data): self
    {
        $id = filter_var($data['instanceId'] ?? null, FILTER_VALIDATE_INT);
        if ($id === false) {
            throw new ProtocolException('Contabo returned an instance without a valid ID.');
        }
        $ipv4 = $data['ipConfig']['v4']['ip'] ?? null;
        return new self(
            id: (int) $id,
            displayName: (string) ($data['displayName'] ?? ''),
            status: (string) ($data['status'] ?? 'unknown'),
            ipv4: is_string($ipv4) && filter_var($ipv4, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false ? $ipv4 : null,
            raw: $data,
        );
    }
}

final class CreateInstanceCommand
{
    public function __construct(
        public readonly string $imageId,
        public readonly string $productId,
        public readonly string $region,
        public readonly int $period,
        public readonly string $displayName,
    ) {
        if ($displayName === '' || strlen($displayName) > 255) {
            throw new InvalidArgumentException('The generated instance display name is invalid.');
        }
    }

    /** @return array<string, int|string> */
    public function toPayload(): array
    {
        return [
            'imageId' => $this->imageId,
            'productId' => $this->productId,
            'region' => $this->region,
            'period' => $this->period,
            'displayName' => $this->displayName,
            'defaultUser' => 'root',
        ];
    }

    public function hash(): string
    {
        return hash('sha256', (string) json_encode($this->toPayload(), JSON_THROW_ON_ERROR));
    }
}

class ContaboException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly string $safeCode,
        public readonly bool $retryable = false,
        public readonly bool $ambiguous = false,
        public readonly ?string $requestId = null,
        public readonly ?int $httpStatus = null,
    ) {
        parent::__construct($message);
    }
}

final class ProtocolException extends ContaboException
{
    public function __construct(string $message, bool $ambiguous = false, ?string $requestId = null)
    {
        parent::__construct($message, 'protocol_error', false, $ambiguous, $requestId);
    }
}

interface ContaboClient
{
    public function createInstance(CreateInstanceCommand $command, string $requestId): Instance;

    /** @return list<Instance> */
    public function findInstancesByDisplayName(string $displayName): array;

    public function getInstance(int $instanceId): Instance;

    public function startInstance(int $instanceId): void;

    public function stopInstance(int $instanceId): void;

    public function restartInstance(int $instanceId): void;

    public function cancelInstance(int $instanceId, string $cancelDate, string $requestId): void;
}

interface ServiceProperties
{
    public function get(string $name): ?string;

    /** @param array<string, scalar|null> $properties */
    public function save(array $properties): void;
}

interface LockManager
{
    /**
     * @template T
     * @param callable(): T $callback
     * @return T
     */
    public function synchronized(string $key, callable $callback): mixed;
}

interface SafeLogger
{
    /** @param array<string, scalar|null> $context */
    public function info(string $action, array $context = []): void;

    /** @param array<string, scalar|null> $context */
    public function error(string $action, array $context = []): void;
}
