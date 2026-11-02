<?php

declare(strict_types=1);

namespace Contabo\Whmcs;

use Illuminate\Database\Capsule\Manager as Capsule;
use RuntimeException;

final class WhmcsServiceProperties implements ServiceProperties
{
    public function __construct(private readonly object $model)
    {
        if (!isset($model->serviceProperties)) {
            throw new RuntimeException('WHMCS did not provide service properties to the module.');
        }
    }

    public function get(string $name): ?string
    {
        $value = $this->model->serviceProperties->get($name);
        if ($value === null || $value === '') {
            return null;
        }
        return (string) $value;
    }

    public function save(array $properties): void
    {
        $this->model->serviceProperties->save($properties);
    }
}

final class WhmcsNamedLock implements LockManager
{
    public function synchronized(string $key, callable $callback): mixed
    {
        $lockName = 'contabo:' . substr(hash('sha256', $key), 0, 48);
        $row = Capsule::selectOne('SELECT GET_LOCK(?, 10) AS acquired', [$lockName]);
        if ((int) ($row->acquired ?? 0) !== 1) {
            throw new ContaboException('Another Contabo operation is already running for this service.', 'operation_locked', true);
        }
        try {
            return $callback();
        } finally {
            Capsule::selectOne('SELECT RELEASE_LOCK(?) AS released', [$lockName]);
        }
    }
}

final class WhmcsSafeLogger implements SafeLogger
{
    /** @param list<string> $secrets */
    public function __construct(private readonly array $secrets)
    {
    }

    public function info(string $action, array $context = []): void
    {
        $this->write($action, $context);
    }

    public function error(string $action, array $context = []): void
    {
        $this->write($action, $context);
    }

    /** @param array<string, scalar|null> $context */
    private function write(string $action, array $context): void
    {
        if (function_exists('logModuleCall')) {
            logModuleCall('contabo', $action, $context, [], [], $this->secrets);
        }
    }
}
