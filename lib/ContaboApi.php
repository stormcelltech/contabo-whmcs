<?php

declare(strict_types=1);

namespace Contabo\Whmcs;

use JsonException;

final class ContaboApi implements ContaboClient
{
    private const API_BASE = 'https://api.contabo.com/v1';
    private const AUTH_URL = 'https://auth.contabo.com/auth/realms/contabo/protocol/openid-connect/token';

    private ?string $accessToken = null;
    private int $tokenExpiresAt = 0;

    /** @param callable(int): void|null $sleeper */
    public function __construct(
        private readonly ModuleConfig $config,
        private readonly HttpTransport $transport,
        private readonly mixed $sleeper = null,
    ) {
    }

    public function createInstance(CreateInstanceCommand $command, string $requestId): Instance
    {
        $data = $this->request('POST', '/compute/instances', $command->toPayload(), false, $requestId);
        return Instance::fromApi($this->firstDataRow($data, true, $requestId));
    }

    public function findInstancesByDisplayName(string $displayName): array
    {
        $query = http_build_query(['displayName' => $displayName, 'size' => 100], '', '&', PHP_QUERY_RFC3986);
        $data = $this->request('GET', '/compute/instances?' . $query, null, true);
        $rows = $data['data'] ?? [];
        if (!is_array($rows)) {
            throw new ProtocolException('Contabo returned an invalid instance list.');
        }

        $instances = [];
        foreach ($rows as $row) {
            if (is_array($row) && (string) ($row['displayName'] ?? '') === $displayName) {
                $instances[] = Instance::fromApi($row);
            }
        }
        return $instances;
    }

    public function getInstance(int $instanceId): Instance
    {
        $data = $this->request('GET', '/compute/instances/' . $this->positiveId($instanceId), null, true);
        return Instance::fromApi($this->firstDataRow($data));
    }

    public function startInstance(int $instanceId): void
    {
        $this->request('POST', '/compute/instances/' . $this->positiveId($instanceId) . '/actions/start', [], true);
    }

    public function stopInstance(int $instanceId): void
    {
        $this->request('POST', '/compute/instances/' . $this->positiveId($instanceId) . '/actions/stop', [], true);
    }

    public function restartInstance(int $instanceId): void
    {
        $this->request('POST', '/compute/instances/' . $this->positiveId($instanceId) . '/actions/restart', [], true);
    }

    public function cancelInstance(int $instanceId, string $cancelDate, string $requestId): void
    {
        if (preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/', $cancelDate) !== 1) {
            throw new \InvalidArgumentException('Cancellation date must be an RFC 3339 UTC timestamp.');
        }
        $this->request(
            'POST',
            '/compute/instances/' . $this->positiveId($instanceId) . '/cancel',
            ['cancelDate' => $cancelDate],
            false,
            $requestId,
        );
    }

    /**
     * @param array<string, mixed>|null $payload
     * @return array<string, mixed>
     */
    private function request(string $method, string $path, ?array $payload, bool $retrySafe, ?string $requestId = null): array
    {
        $requestId ??= self::uuidV4();
        $attempts = $retrySafe ? 3 : 1;
        $refreshedToken = false;

        for ($attempt = 1; $attempt <= $attempts; $attempt++) {
            $body = $payload === null ? null : $this->encodeJson($payload);
            try {
                $response = $this->transport->send(new HttpRequest(
                    method: $method,
                    url: self::API_BASE . $path,
                    headers: [
                        'Authorization' => 'Bearer ' . $this->accessToken(),
                        'Accept' => 'application/json',
                        'Content-Type' => 'application/json',
                        'User-Agent' => 'contabo-whmcs/2.0',
                        'x-request-id' => $requestId,
                        'x-trace-id' => 'whmcs-' . substr(hash('sha256', $requestId), 0, 24),
                    ],
                    body: $body,
                ));
            } catch (ContaboException $exception) {
                if ($retrySafe && $attempt < $attempts) {
                    $this->sleep($attempt);
                    continue;
                }
                throw new ContaboException(
                    $exception->getMessage(),
                    $exception->safeCode,
                    $exception->retryable,
                    $exception->ambiguous,
                    $requestId,
                    $exception->httpStatus,
                );
            }

            if ($response->status === 401 && !$refreshedToken) {
                $this->accessToken = null;
                $this->tokenExpiresAt = 0;
                $refreshedToken = true;
                $attempt--;
                continue;
            }
            if ($this->isRetryableStatus($response->status) && $retrySafe && $attempt < $attempts) {
                $this->sleep($attempt, $response->header('retry-after'));
                continue;
            }
            if ($response->status < 200 || $response->status >= 300) {
                throw new ContaboException(
                    'Contabo rejected the request (HTTP ' . $response->status . ').',
                    'api_http_' . $response->status,
                    $this->isRetryableStatus($response->status),
                    !$retrySafe && $response->status >= 500,
                    $requestId,
                    $response->status,
                );
            }
            if ($response->status === 204 || trim($response->body) === '') {
                return [];
            }
            return $this->decodeJson($response->body, !$retrySafe, $requestId);
        }

        throw new ContaboException('Contabo request retries were exhausted.', 'retry_exhausted', true, false, $requestId);
    }

    private function accessToken(): string
    {
        if ($this->accessToken !== null && $this->tokenExpiresAt > time() + 60) {
            return $this->accessToken;
        }
        $body = http_build_query([
            'grant_type' => 'password',
            'client_id' => $this->config->clientId,
            'client_secret' => $this->config->clientSecret,
            'username' => $this->config->apiUser,
            'password' => $this->config->apiPassword,
        ], '', '&', PHP_QUERY_RFC3986);
        try {
            $response = $this->transport->send(new HttpRequest(
                'POST',
                self::AUTH_URL,
                ['Accept' => 'application/json', 'Content-Type' => 'application/x-www-form-urlencoded', 'User-Agent' => 'contabo-whmcs/2.0'],
                $body,
            ));
        } catch (ContaboException $exception) {
            throw new ContaboException(
                'Contabo authentication could not be completed.',
                'authentication_transport_failure',
                true,
                false,
                null,
                $exception->httpStatus,
            );
        }
        if ($response->status < 200 || $response->status >= 300) {
            throw new ContaboException('Contabo authentication failed.', 'authentication_failed', false, false, null, $response->status);
        }
        $data = $this->decodeJson($response->body, false, null);
        $token = $data['access_token'] ?? null;
        if (!is_string($token) || $token === '') {
            throw new ProtocolException('Contabo authentication returned no access token.');
        }
        $expiresIn = filter_var($data['expires_in'] ?? 300, FILTER_VALIDATE_INT);
        $this->accessToken = $token;
        $this->tokenExpiresAt = time() + max(120, $expiresIn === false ? 300 : (int) $expiresIn);
        return $token;
    }

    /** @param array<string, mixed> $payload */
    private function encodeJson(array $payload): string
    {
        try {
            return json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        } catch (JsonException) {
            throw new ProtocolException('Unable to encode a Contabo request.');
        }
    }

    /** @return array<string, mixed> */
    private function decodeJson(string $body, bool $ambiguous, ?string $requestId): array
    {
        try {
            $decoded = json_decode($body, true, 64, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new ProtocolException('Contabo returned malformed JSON.', $ambiguous, $requestId);
        }
        if (!is_array($decoded)) {
            throw new ProtocolException('Contabo returned an invalid JSON document.', $ambiguous, $requestId);
        }
        return $decoded;
    }

    /**
     * @param array<string, mixed> $response
     * @return array<string, mixed>
     */
    private function firstDataRow(array $response, bool $ambiguous = false, ?string $requestId = null): array
    {
        $row = $response['data'][0] ?? null;
        if (!is_array($row)) {
            throw new ProtocolException('Contabo returned no instance record.', $ambiguous, $requestId);
        }
        return $row;
    }

    private function positiveId(int $id): int
    {
        if ($id <= 0) {
            throw new \InvalidArgumentException('Instance ID must be positive.');
        }
        return $id;
    }

    private function isRetryableStatus(int $status): bool
    {
        return in_array($status, [408, 425, 429], true) || $status >= 500;
    }

    private function sleep(int $attempt, ?string $retryAfter = null): void
    {
        $seconds = min(8, 2 ** ($attempt - 1));
        if ($retryAfter !== null && ctype_digit($retryAfter)) {
            $seconds = min(30, max($seconds, (int) $retryAfter));
        }
        $seconds = (int) $seconds;
        if (is_callable($this->sleeper)) {
            ($this->sleeper)($seconds);
        } else {
            sleep($seconds);
        }
    }

    public static function uuidV4(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
    }
}
