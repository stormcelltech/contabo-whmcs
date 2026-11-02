<?php

declare(strict_types=1);

namespace Contabo\Whmcs;

final class ProvisioningService
{
    private const INSTANCE_ID = 'Contabo Instance ID';
    private const CREATE_STATUS = 'Contabo Create Status';
    private const CREATE_REQUEST_ID = 'Contabo Create Request ID';
    private const CREATE_REQUEST_HASH = 'Contabo Create Request Hash';
    private const TERMINATION_STATUS = 'Contabo Termination Status';
    private const TERMINATION_REQUEST_ID = 'Contabo Termination Request ID';
    private const TERMINATION_REQUEST_HASH = 'Contabo Termination Request Hash';
    private const CLIENT_POWER_LAST_AT = 'Contabo Client Power Last At';
    private const CLIENT_POWER_COOLDOWN_SECONDS = 15;

    public function __construct(
        private readonly ContaboClient $client,
        private readonly ServiceProperties $properties,
        private readonly LockManager $locks,
        private readonly SafeLogger $logger,
    ) {
    }

    public function create(int $serviceId, ModuleConfig $config): Instance
    {
        return $this->locks->synchronized('create-' . $serviceId, function () use ($serviceId, $config): Instance {
            $existingId = $this->instanceIdOrNull();
            if ($existingId !== null) {
                $instance = $this->client->getInstance($existingId);
                $this->logger->info('create_already_complete', ['service_id' => $serviceId, 'instance_id' => $existingId]);
                return $instance;
            }

            $command = new CreateInstanceCommand(
                $config->imageId,
                $config->productId,
                $config->region,
                $config->contractPeriod,
                $config->hostnamePrefix . '-' . $serviceId,
            );
            $storedHash = $this->properties->get(self::CREATE_REQUEST_HASH);
            if ($storedHash !== null && !hash_equals($storedHash, $command->hash())) {
                throw new ContaboException(
                    'Provisioning settings changed after a create attempt. Reconcile the existing Contabo order before retrying.',
                    'create_contract_changed',
                );
            }

            $status = $this->properties->get(self::CREATE_STATUS);
            $requestId = $this->properties->get(self::CREATE_REQUEST_ID) ?? ContaboApi::uuidV4();
            if (in_array($status, ['pending', 'ambiguous'], true)) {
                return $this->reconcileAmbiguousCreate($serviceId, $command);
            }

            $this->properties->save([
                self::CREATE_STATUS => 'pending',
                self::CREATE_REQUEST_ID => $requestId,
                self::CREATE_REQUEST_HASH => $command->hash(),
            ]);

            try {
                $instance = $this->client->createInstance($command, $requestId);
            } catch (ContaboException $exception) {
                $this->properties->save([self::CREATE_STATUS => $exception->ambiguous ? 'ambiguous' : 'failed']);
                $this->logger->error('create_failed', [
                    'service_id' => $serviceId,
                    'request_id' => $exception->requestId ?? $requestId,
                    'error_code' => $exception->safeCode,
                    'ambiguous' => $exception->ambiguous ? 1 : 0,
                ]);
                if ($exception->ambiguous) {
                    throw new ContaboException(
                        'The Contabo create outcome is unknown. Automatic retry is blocked to prevent a duplicate paid server; retry the WHMCS command to reconcile first.',
                        'create_outcome_ambiguous',
                        true,
                        true,
                        $exception->requestId ?? $requestId,
                    );
                }
                throw $exception;
            }

            $this->completeCreate($serviceId, $instance);
            return $instance;
        });
    }

    public function suspend(int $serviceId): void
    {
        $this->locks->synchronized('instance-' . $serviceId, function () use ($serviceId): void {
            $instanceId = $this->requireInstanceId();
            $this->client->stopInstance($instanceId);
            $this->logger->info('suspend_complete', ['service_id' => $serviceId, 'instance_id' => $instanceId]);
        });
    }

    public function unsuspend(int $serviceId): void
    {
        $this->locks->synchronized('instance-' . $serviceId, function () use ($serviceId): void {
            $instanceId = $this->requireInstanceId();
            $this->client->startInstance($instanceId);
            $this->logger->info('unsuspend_complete', ['service_id' => $serviceId, 'instance_id' => $instanceId]);
        });
    }

    public function terminate(int $serviceId, ModuleConfig $config): void
    {
        if (!$config->automaticCancellationEnabled) {
            throw new ContaboException(
                'Automatic Contabo cancellation is disabled. Enable it on the WHMCS product only after confirming the cancellation policy.',
                'automatic_cancellation_disabled',
            );
        }
        $this->locks->synchronized('instance-' . $serviceId, function () use ($serviceId): void {
            $status = $this->properties->get(self::TERMINATION_STATUS);
            if ($status === 'completed') {
                return;
            }
            $instanceId = $this->requireInstanceId();
            $requestHash = hash('sha256', 'cancel:' . $instanceId);
            $storedHash = $this->properties->get(self::TERMINATION_REQUEST_HASH);
            if ($storedHash !== null && !hash_equals($storedHash, $requestHash)) {
                throw new ContaboException(
                    'The stored cancellation evidence belongs to a different instance. Manual reconciliation is required.',
                    'termination_contract_changed',
                );
            }
            if (in_array($status, ['pending', 'ambiguous'], true)) {
                throw new ContaboException(
                    'The earlier Contabo cancellation outcome is unknown. Verify the provider control panel before changing the operation evidence.',
                    'termination_manual_reconciliation_required',
                    true,
                    true,
                    $this->properties->get(self::TERMINATION_REQUEST_ID),
                );
            }

            $requestId = ContaboApi::uuidV4();
            $this->properties->save([
                self::TERMINATION_STATUS => 'pending',
                self::TERMINATION_REQUEST_ID => $requestId,
                self::TERMINATION_REQUEST_HASH => $requestHash,
            ]);
            try {
                $this->client->cancelInstance($instanceId, gmdate('Y-m-d\TH:i:s\Z'), $requestId);
            } catch (ContaboException $exception) {
                $this->properties->save([
                    self::TERMINATION_STATUS => $exception->ambiguous ? 'ambiguous' : 'failed',
                ]);
                $this->logger->error('termination_failed', [
                    'service_id' => $serviceId,
                    'instance_id' => $instanceId,
                    'request_id' => $exception->requestId ?? $requestId,
                    'error_code' => $exception->safeCode,
                    'ambiguous' => $exception->ambiguous ? 1 : 0,
                ]);
                throw $exception;
            }
            $this->properties->save([self::TERMINATION_STATUS => 'completed']);
            $this->logger->info('termination_requested', [
                'service_id' => $serviceId,
                'instance_id' => $instanceId,
                'request_id' => $requestId,
            ]);
        });
    }

    public function instance(): Instance
    {
        return $this->client->getInstance($this->requireInstanceId());
    }

    public function powerOn(int $serviceId): void
    {
        $this->clientPower($serviceId, 'power_on', fn (int $id) => $this->client->startInstance($id));
    }

    public function powerOff(int $serviceId): void
    {
        $this->clientPower($serviceId, 'power_off', fn (int $id) => $this->client->stopInstance($id));
    }

    public function reboot(int $serviceId): void
    {
        $this->clientPower($serviceId, 'reboot', fn (int $id) => $this->client->restartInstance($id));
    }

    /** @param callable(int): void $operation */
    private function clientPower(int $serviceId, string $action, callable $operation): void
    {
        $this->locks->synchronized('instance-' . $serviceId, function () use ($serviceId, $action, $operation): void {
            $now = time();
            $lastAt = filter_var($this->properties->get(self::CLIENT_POWER_LAST_AT), FILTER_VALIDATE_INT);
            if ($lastAt !== false && $lastAt > $now - self::CLIENT_POWER_COOLDOWN_SECONDS) {
                throw new ContaboException(
                    'Please wait before sending another power command.',
                    'client_power_rate_limited',
                    true,
                );
            }
            $id = $this->requireInstanceId();
            $this->properties->save([self::CLIENT_POWER_LAST_AT => $now]);
            $operation($id);
            $this->logger->info($action, ['service_id' => $serviceId, 'instance_id' => $id]);
        });
    }

    private function reconcileAmbiguousCreate(int $serviceId, CreateInstanceCommand $command): Instance
    {
        $matches = $this->client->findInstancesByDisplayName($command->displayName);
        if (count($matches) === 1) {
            $this->completeCreate($serviceId, $matches[0]);
            $this->logger->info('create_reconciled', ['service_id' => $serviceId, 'instance_id' => $matches[0]->id]);
            return $matches[0];
        }
        if (count($matches) > 1) {
            throw new ContaboException(
                'Multiple Contabo instances match this WHMCS service. Automatic reconciliation is blocked.',
                'duplicate_instances_detected',
            );
        }
        throw new ContaboException(
            'No Contabo instance is visible for the earlier ambiguous create. Automatic re-ordering remains blocked; verify the Contabo control panel before resetting the operation state.',
            'create_manual_reconciliation_required',
            true,
            true,
        );
    }

    private function completeCreate(int $serviceId, Instance $instance): void
    {
        $values = [
            self::INSTANCE_ID => (string) $instance->id,
            self::CREATE_STATUS => 'completed',
            self::TERMINATION_STATUS => null,
        ];
        if ($instance->ipv4 !== null) {
            $values['Dedicated IP'] = $instance->ipv4;
        }
        $this->properties->save($values);
        $this->logger->info('create_complete', ['service_id' => $serviceId, 'instance_id' => $instance->id]);
    }

    private function requireInstanceId(): int
    {
        $id = $this->instanceIdOrNull();
        if ($id === null) {
            throw new ContaboException('This WHMCS service has no valid Contabo instance ID.', 'instance_id_missing');
        }
        return $id;
    }

    private function instanceIdOrNull(): ?int
    {
        $value = $this->properties->get(self::INSTANCE_ID);
        $id = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        return $id === false ? null : (int) $id;
    }
}
