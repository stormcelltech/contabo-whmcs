<?php

declare(strict_types=1);

if (!defined('WHMCS')) {
    http_response_code(404);
    exit;
}

use Contabo\Whmcs\ContaboApi;
use Contabo\Whmcs\ContaboException;
use Contabo\Whmcs\CurlTransport;
use Contabo\Whmcs\ModuleConfig;
use Contabo\Whmcs\ProvisioningService;
use Contabo\Whmcs\WhmcsNamedLock;
use Contabo\Whmcs\WhmcsSafeLogger;
use Contabo\Whmcs\WhmcsServiceProperties;

require_once __DIR__ . '/lib/Contracts.php';
require_once __DIR__ . '/lib/Http.php';
require_once __DIR__ . '/lib/ContaboApi.php';
require_once __DIR__ . '/lib/ProvisioningService.php';
require_once __DIR__ . '/lib/WhmcsAdapters.php';

/** @return array<string, mixed> */
function contabo_MetaData(): array
{
    return [
        'DisplayName' => 'Contabo Cloud Compute',
        'APIVersion' => '1.1',
        'RequiresServer' => false,
    ];
}

/** @return array<string, array<string, int|string>> */
function contabo_ConfigOptions(): array
{
    return [
        'API Client ID' => ['Type' => 'text', 'Size' => 50, 'Description' => 'Contabo OAuth2 client ID'],
        'API Client Secret' => ['Type' => 'password', 'Size' => 50, 'Description' => 'Contabo OAuth2 client secret'],
        'API User' => ['Type' => 'text', 'Size' => 50, 'Description' => 'Dedicated least-privilege Contabo API user email'],
        'API Password' => ['Type' => 'password', 'Size' => 50, 'Description' => 'Dedicated Contabo API user password'],
        'Region' => [
            'Type' => 'dropdown',
            'Options' => 'European Union (EU),US Central (US-central),Singapore (SIN)',
            'Default' => 'European Union (EU)',
        ],
        'Product ID' => ['Type' => 'text', 'Size' => 20, 'Default' => 'V153', 'Description' => 'Exact current Contabo product ID'],
        'Image ID' => ['Type' => 'text', 'Size' => 40, 'Description' => 'Exact Contabo image UUID; never use an unverified default'],
        'Hostname Prefix' => ['Type' => 'text', 'Size' => 24, 'Default' => 'whmcs', 'Description' => 'Lowercase prefix used for deterministic reconciliation'],
        'Contract Period' => ['Type' => 'dropdown', 'Options' => '1,3,6,12', 'Default' => '1'],
        'Client Power Controls' => ['Type' => 'yesno', 'Description' => 'Allow authenticated clients to start, stop, and restart their instance'],
        'Automatic Cancellation' => ['Type' => 'yesno', 'Description' => 'Allow WHMCS TerminateAccount to submit a billable Contabo cancellation'],
    ];
}

/** @param array<string, mixed> $params */
function contabo_CreateAccount(array $params): string
{
    try {
        [$service, $config] = contabo_service($params);
        $service->create(contabo_service_id($params), $config);
        return 'success';
    } catch (Throwable $exception) {
        return contabo_failure('create_account', $exception, $params);
    }
}

/** @param array<string, mixed> $params */
function contabo_SuspendAccount(array $params): string
{
    try {
        [$service] = contabo_service($params);
        $service->suspend(contabo_service_id($params));
        return 'success';
    } catch (Throwable $exception) {
        return contabo_failure('suspend_account', $exception, $params);
    }
}

/** @param array<string, mixed> $params */
function contabo_UnsuspendAccount(array $params): string
{
    try {
        [$service] = contabo_service($params);
        $service->unsuspend(contabo_service_id($params));
        return 'success';
    } catch (Throwable $exception) {
        return contabo_failure('unsuspend_account', $exception, $params);
    }
}

/** @param array<string, mixed> $params */
function contabo_TerminateAccount(array $params): string
{
    try {
        [$service, $config] = contabo_service($params);
        $service->terminate(contabo_service_id($params), $config);
        return 'success';
    } catch (Throwable $exception) {
        return contabo_failure('terminate_account', $exception, $params);
    }
}

/** @return array<string, string> */
function contabo_AdminCustomButtonArray(): array
{
    return ['View Contabo Details' => 'ViewDetails'];
}

/** @param array<string, mixed> $params */
function contabo_ViewDetails(array $params): string
{
    try {
        [$service] = contabo_service($params);
        $instance = $service->instance();
        $safe = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        return '<div class="panel panel-default"><div class="panel-heading"><strong>Contabo instance</strong></div>'
            . '<table class="table table-striped"><tbody>'
            . '<tr><th>Instance ID</th><td>' . $safe($instance->id) . '</td></tr>'
            . '<tr><th>Display name</th><td>' . $safe($instance->displayName) . '</td></tr>'
            . '<tr><th>Status</th><td>' . $safe($instance->status) . '</td></tr>'
            . '<tr><th>IPv4</th><td>' . $safe($instance->ipv4 ?? 'Not assigned') . '</td></tr>'
            . '</tbody></table></div>';
    } catch (Throwable $exception) {
        return '<div class="alert alert-danger">' . htmlspecialchars(contabo_failure('view_details', $exception, $params), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</div>';
    }
}

/** @param array<string, mixed> $params @return array<string, mixed> */
function contabo_ClientArea(array $params): array
{
    try {
        [$service, $config] = contabo_service($params);
        $instance = $service->instance();
        return [
            'templatefile' => 'clientarea',
            'vars' => [
                'instance' => $instance->raw,
                'clientPowerEnabled' => $config->clientPowerEnabled,
                'actionBase' => (string) ($params['modulelink'] ?? ''),
                'apiError' => null,
            ],
        ];
    } catch (Throwable $exception) {
        return [
            'templatefile' => 'clientarea',
            'vars' => ['apiError' => contabo_failure('client_area', $exception, $params)],
        ];
    }
}

/** @return list<string> */
function contabo_ClientAreaAllowedFunctions(): array
{
    return ['ClientPowerOn', 'ClientPowerOff', 'ClientReboot'];
}

/** @param array<string, mixed> $params */
function contabo_ClientPowerOn(array $params): string
{
    return contabo_client_power($params, 'on');
}

/** @param array<string, mixed> $params */
function contabo_ClientPowerOff(array $params): string
{
    return contabo_client_power($params, 'off');
}

/** @param array<string, mixed> $params */
function contabo_ClientReboot(array $params): string
{
    return contabo_client_power($params, 'reboot');
}

/** @param array<string, mixed> $params */
function contabo_client_power(array $params, string $action): string
{
    try {
        contabo_require_post_csrf();
        [$service, $config] = contabo_service($params);
        if (!$config->clientPowerEnabled) {
            throw new ContaboException('Client power controls are disabled for this product.', 'client_power_disabled');
        }
        $serviceId = contabo_service_id($params);
        match ($action) {
            'on' => $service->powerOn($serviceId),
            'off' => $service->powerOff($serviceId),
            'reboot' => $service->reboot($serviceId),
            default => throw new LogicException('Unsupported power action.'),
        };
        return 'success';
    } catch (Throwable $exception) {
        return contabo_failure('client_power_' . $action, $exception, $params);
    }
}

/** @param array<string, mixed> $params @return array{0: ProvisioningService, 1: ModuleConfig} */
function contabo_service(array $params): array
{
    $config = ModuleConfig::fromWhmcs($params);
    $model = $params['model'] ?? null;
    if (!is_object($model)) {
        throw new RuntimeException('WHMCS did not provide the service model.');
    }
    $logger = new WhmcsSafeLogger($config->secrets());
    $api = new ContaboApi($config, new CurlTransport());
    return [
        new ProvisioningService($api, new WhmcsServiceProperties($model), new WhmcsNamedLock(), $logger),
        $config,
    ];
}

/** @param array<string, mixed> $params */
function contabo_service_id(array $params): int
{
    $id = filter_var($params['serviceid'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if ($id === false) {
        throw new InvalidArgumentException('WHMCS supplied an invalid service ID.');
    }
    return (int) $id;
}

function contabo_require_post_csrf(): void
{
    if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== 'POST') {
        throw new ContaboException('This action requires a POST request.', 'method_not_allowed');
    }
    if (!function_exists('check_token')) {
        throw new RuntimeException('WHMCS CSRF validation is unavailable.');
    }
    check_token('WHMCS.default');
}

/** @param array<string, mixed> $params */
function contabo_failure(string $action, Throwable $exception, array $params): string
{
    $serviceId = filter_var($params['serviceid'] ?? null, FILTER_VALIDATE_INT);
    $code = $exception instanceof ContaboException ? $exception->safeCode : 'unexpected_error';
    $requestId = $exception instanceof ContaboException ? $exception->requestId : null;
    $context = ['service_id' => $serviceId === false ? null : (int) $serviceId, 'error_code' => $code, 'request_id' => $requestId];
    try {
        $config = ModuleConfig::fromWhmcs($params);
        (new WhmcsSafeLogger($config->secrets()))->error($action, $context);
    } catch (Throwable) {
        if (function_exists('logModuleCall')) {
            logModuleCall('contabo', $action, $context, [], [], []);
        }
    }
    if ($exception instanceof ContaboException || $exception instanceof InvalidArgumentException) {
        return $exception->getMessage() . ($requestId !== null ? ' Request ID: ' . $requestId : '');
    }
    return 'The Contabo operation failed safely. Review the WHMCS module log using error code ' . $code . '.';
}
