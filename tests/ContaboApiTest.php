<?php

declare(strict_types=1);

use Contabo\Whmcs\ContaboApi;
use Contabo\Whmcs\ContaboException;
use Contabo\Whmcs\CreateInstanceCommand;
use Contabo\Whmcs\HttpRequest;
use Contabo\Whmcs\HttpResponse;
use Contabo\Whmcs\HttpTransport;
use Contabo\Whmcs\ModuleConfig;
use PHPUnit\Framework\TestCase;

final class ContaboApiTest extends TestCase
{
    public function test_create_uses_exact_payload_and_stable_request_id(): void
    {
        $transport = new QueueTransport([
            self::json(200, ['access_token' => 'token', 'expires_in' => 3600]),
            self::json(201, ['data' => [['instanceId' => 44, 'displayName' => 'whmcs-7', 'status' => 'pending']]]),
        ]);
        $api = new ContaboApi(self::config(), $transport, static fn (int $seconds) => null);
        $api->createInstance(new CreateInstanceCommand(self::image(), 'V153', 'EU', 1, 'whmcs-7'), '11111111-1111-4111-8111-111111111111');

        self::assertCount(2, $transport->requests);
        $request = $transport->requests[1];
        self::assertSame('11111111-1111-4111-8111-111111111111', $request->headers['x-request-id']);
        self::assertSame('V153', json_decode((string) $request->body, true, 512, JSON_THROW_ON_ERROR)['productId']);
    }

    public function test_retry_safe_get_retries_server_failure(): void
    {
        $transport = new QueueTransport([
            self::json(200, ['access_token' => 'token', 'expires_in' => 3600]),
            self::json(503, ['message' => 'busy']),
            self::json(200, ['data' => [['instanceId' => 44, 'displayName' => 'server', 'status' => 'active']]]),
        ]);
        $api = new ContaboApi(self::config(), $transport, static fn (int $seconds) => null);
        self::assertSame(44, $api->getInstance(44)->id);
        self::assertCount(3, $transport->requests);
    }

    public function test_create_never_retries_a_server_failure(): void
    {
        $transport = new QueueTransport([
            self::json(200, ['access_token' => 'token', 'expires_in' => 3600]),
            self::json(503, ['message' => 'busy']),
        ]);
        $api = new ContaboApi(self::config(), $transport, static fn (int $seconds) => null);
        try {
            $api->createInstance(new CreateInstanceCommand(self::image(), 'V153', 'EU', 1, 'whmcs-7'), ContaboApi::uuidV4());
            self::fail('Expected failure.');
        } catch (ContaboException $exception) {
            self::assertTrue($exception->ambiguous);
            self::assertSame('api_http_503', $exception->safeCode);
        }
        self::assertCount(2, $transport->requests);
    }

    public function test_unauthorized_response_refreshes_token_once(): void
    {
        $transport = new QueueTransport([
            self::json(200, ['access_token' => 'old', 'expires_in' => 3600]),
            self::json(401, ['message' => 'expired']),
            self::json(200, ['access_token' => 'new', 'expires_in' => 3600]),
            self::json(200, ['data' => [['instanceId' => 44, 'displayName' => 'server', 'status' => 'active']]]),
        ]);
        $api = new ContaboApi(self::config(), $transport, static fn (int $seconds) => null);
        self::assertSame(44, $api->getInstance(44)->id);
        self::assertSame('Bearer new', $transport->requests[3]->headers['Authorization']);
    }

    public function test_cancel_uses_provider_datetime_contract_and_is_not_retried(): void
    {
        $transport = new QueueTransport([
            self::json(200, ['access_token' => 'token', 'expires_in' => 3600]),
            self::json(201, ['data' => [['instanceId' => 44]]]),
        ]);
        $api = new ContaboApi(self::config(), $transport, static fn (int $seconds) => null);
        $api->cancelInstance(44, '2026-11-02T11:45:00Z', '22222222-2222-4222-8222-222222222222');

        $request = $transport->requests[1];
        self::assertSame('POST', $request->method);
        self::assertSame('22222222-2222-4222-8222-222222222222', $request->headers['x-request-id']);
        self::assertSame(
            '2026-11-02T11:45:00Z',
            json_decode((string) $request->body, true, 512, JSON_THROW_ON_ERROR)['cancelDate'],
        );
        self::assertCount(2, $transport->requests);
    }

    public function test_uuid_is_rfc4122_version_four(): void
    {
        self::assertMatchesRegularExpression('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/', ContaboApi::uuidV4());
    }

    private static function config(): ModuleConfig
    {
        return new ModuleConfig('client', 'secret', 'api@example.com', 'password', 'EU', 'V153', self::image(), 'whmcs', 1, false, false);
    }
    private static function image(): string { return '3f184ab8-a600-4e7c-8c9b-3413e21a3752'; }
    /** @param array<string, mixed> $body */
    private static function json(int $status, array $body): HttpResponse { return new HttpResponse($status, [], json_encode($body, JSON_THROW_ON_ERROR)); }
}

final class QueueTransport implements HttpTransport
{
    /** @var list<HttpRequest> */ public array $requests = [];
    /** @param list<HttpResponse|Throwable> $queue */
    public function __construct(private array $queue) {}
    public function send(HttpRequest $request): HttpResponse
    {
        $this->requests[] = $request;
        $next = array_shift($this->queue);
        if ($next instanceof Throwable) { throw $next; }
        if (!$next instanceof HttpResponse) { throw new RuntimeException('No queued response.'); }
        return $next;
    }
}
