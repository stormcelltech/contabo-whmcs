<?php

declare(strict_types=1);

use Contabo\Whmcs\ContaboClient;
use Contabo\Whmcs\ContaboException;
use Contabo\Whmcs\CreateInstanceCommand;
use Contabo\Whmcs\Instance;
use Contabo\Whmcs\LockManager;
use Contabo\Whmcs\ModuleConfig;
use Contabo\Whmcs\ProvisioningService;
use Contabo\Whmcs\SafeLogger;
use Contabo\Whmcs\ServiceProperties;
use PHPUnit\Framework\TestCase;

final class ProvisioningServiceTest extends TestCase
{
    public function test_create_is_idempotent_after_completion(): void
    {
        $client = new FakeClient();
        $properties = new MemoryProperties(['Contabo Instance ID' => '42']);
        $service = new ProvisioningService($client, $properties, new ImmediateLock(), new MemoryLogger());

        $instance = $service->create(7, self::config());

        self::assertSame(42, $instance->id);
        self::assertSame(0, $client->createCalls);
        self::assertSame([42], $client->getCalls);
    }

    public function test_create_persists_intent_before_call_and_result_after_success(): void
    {
        $client = new FakeClient();
        $properties = new MemoryProperties();
        $service = new ProvisioningService($client, $properties, new ImmediateLock(), new MemoryLogger());

        $instance = $service->create(7, self::config());

        self::assertSame(101, $instance->id);
        self::assertSame(1, $client->createCalls);
        self::assertSame('completed', $properties->values['Contabo Create Status']);
        self::assertSame('101', $properties->values['Contabo Instance ID']);
        self::assertSame('203.0.113.10', $properties->values['Dedicated IP']);
        self::assertNotEmpty($properties->values['Contabo Create Request ID']);
    }

    public function test_ambiguous_create_is_reconciled_without_second_purchase(): void
    {
        $client = new FakeClient();
        $client->createException = new ContaboException('timeout', 'transport_failure', true, true, 'request-1');
        $properties = new MemoryProperties();
        $service = new ProvisioningService($client, $properties, new ImmediateLock(), new MemoryLogger());

        try {
            $service->create(7, self::config());
            self::fail('Expected ambiguous create failure.');
        } catch (ContaboException $exception) {
            self::assertSame('create_outcome_ambiguous', $exception->safeCode);
        }
        $client->createException = null;
        $client->searchResults = [FakeClient::instance(101, 'whmcs-7')];

        $instance = $service->create(7, self::config());

        self::assertSame(101, $instance->id);
        self::assertSame(1, $client->createCalls);
        self::assertSame(1, $client->searchCalls);
    }

    public function test_ambiguous_create_with_no_match_never_reorders_automatically(): void
    {
        $properties = new MemoryProperties([
            'Contabo Create Status' => 'ambiguous',
            'Contabo Create Request ID' => 'request-1',
            'Contabo Create Request Hash' => (new CreateInstanceCommand('3f184ab8-a600-4e7c-8c9b-3413e21a3752', 'V153', 'EU', 1, 'whmcs-7'))->hash(),
        ]);
        $client = new FakeClient();
        $service = new ProvisioningService($client, $properties, new ImmediateLock(), new MemoryLogger());

        $this->expectException(ContaboException::class);
        $this->expectExceptionMessage('Automatic re-ordering remains blocked');
        try {
            $service->create(7, self::config());
        } finally {
            self::assertSame(0, $client->createCalls);
        }
    }

    public function test_termination_requires_explicit_opt_in(): void
    {
        $service = new ProvisioningService(new FakeClient(), new MemoryProperties(['Contabo Instance ID' => '42']), new ImmediateLock(), new MemoryLogger());
        $this->expectException(ContaboException::class);
        $this->expectExceptionMessage('disabled');
        $service->terminate(7, self::config(false));
    }

    public function test_termination_is_not_submitted_twice(): void
    {
        $client = new FakeClient();
        $properties = new MemoryProperties(['Contabo Instance ID' => '42']);
        $service = new ProvisioningService($client, $properties, new ImmediateLock(), new MemoryLogger());

        $service->terminate(7, self::config(true));
        $service->terminate(7, self::config(true));

        self::assertSame(1, $client->cancelCalls);
        self::assertSame('completed', $properties->values['Contabo Termination Status']);
    }

    public function test_ambiguous_termination_is_never_resubmitted_automatically(): void
    {
        $client = new FakeClient();
        $client->cancelException = new ContaboException('timeout', 'transport_failure', true, true, 'cancel-request-1');
        $properties = new MemoryProperties(['Contabo Instance ID' => '42']);
        $service = new ProvisioningService($client, $properties, new ImmediateLock(), new MemoryLogger());

        try {
            $service->terminate(7, self::config(true));
            self::fail('Expected ambiguous cancellation failure.');
        } catch (ContaboException $exception) {
            self::assertTrue($exception->ambiguous);
        }
        $client->cancelException = null;

        try {
            $service->terminate(7, self::config(true));
            self::fail('Expected cancellation reconciliation block.');
        } catch (ContaboException $exception) {
            self::assertSame('termination_manual_reconciliation_required', $exception->safeCode);
        }

        self::assertSame(1, $client->cancelCalls);
        self::assertSame('ambiguous', $properties->values['Contabo Termination Status']);
        self::assertNotEmpty($properties->values['Contabo Termination Request ID']);
        self::assertNotEmpty($properties->values['Contabo Termination Request Hash']);
    }

    public function test_client_power_commands_are_rate_limited_per_service(): void
    {
        $client = new FakeClient();
        $properties = new MemoryProperties(['Contabo Instance ID' => '42']);
        $service = new ProvisioningService($client, $properties, new ImmediateLock(), new MemoryLogger());

        $service->powerOn(7);

        $this->expectException(ContaboException::class);
        $this->expectExceptionMessage('Please wait');
        $service->reboot(7);
    }

    private static function config(bool $cancel = false): ModuleConfig
    {
        return new ModuleConfig('id', 'secret', 'api@example.com', 'password', 'EU', 'V153', '3f184ab8-a600-4e7c-8c9b-3413e21a3752', 'whmcs', 1, false, $cancel);
    }
}

final class FakeClient implements ContaboClient
{
    public int $createCalls = 0;
    public int $searchCalls = 0;
    /** @var list<int> */ public array $getCalls = [];
    /** @var list<Instance> */ public array $searchResults = [];
    public ?ContaboException $createException = null;
    public int $cancelCalls = 0;
    public ?ContaboException $cancelException = null;

    public function createInstance(CreateInstanceCommand $command, string $requestId): Instance
    {
        $this->createCalls++;
        if ($this->createException !== null) {
            throw $this->createException;
        }
        return self::instance(101, $command->displayName);
    }
    public function findInstancesByDisplayName(string $displayName): array { $this->searchCalls++; return $this->searchResults; }
    public function getInstance(int $instanceId): Instance { $this->getCalls[] = $instanceId; return self::instance($instanceId, 'existing'); }
    public function startInstance(int $instanceId): void {}
    public function stopInstance(int $instanceId): void {}
    public function restartInstance(int $instanceId): void {}
    public function cancelInstance(int $instanceId, string $cancelDate, string $requestId): void
    {
        $this->cancelCalls++;
        if ($this->cancelException !== null) { throw $this->cancelException; }
    }

    public static function instance(int $id, string $name): Instance
    {
        return new Instance($id, $name, 'active', '203.0.113.10', ['instanceId' => $id, 'displayName' => $name, 'status' => 'active']);
    }
}

final class MemoryProperties implements ServiceProperties
{
    /** @param array<string, scalar|null> $values */
    public function __construct(public array $values = []) {}
    public function get(string $name): ?string { $value = $this->values[$name] ?? null; return $value === null ? null : (string) $value; }
    public function save(array $properties): void { foreach ($properties as $key => $value) { $this->values[$key] = $value; } }
}

final class ImmediateLock implements LockManager
{
    public function synchronized(string $key, callable $callback): mixed { return $callback(); }
}

final class MemoryLogger implements SafeLogger
{
    /** @var list<array{string, array<string, scalar|null>}> */
    public array $events = [];
    public function info(string $action, array $context = []): void { $this->events[] = [$action, $context]; }
    public function error(string $action, array $context = []): void { $this->events[] = [$action, $context]; }
}
