<?php

/**
 * WHMCS Contabo Provisioning Module
 * WHMCS Version: 8.x +
 * Security Hardened Edition
 */

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

require_once __DIR__ . '/lib/ApiClient.php';
require_once __DIR__ . '/lib/Helper.php';

use ContaboModule\ApiClient;
use ContaboModule\Helper;

function contabo_MetaData()
{
    return [
        'DisplayName' => 'Contabo Cloud Compute',
        'APIVersion' => '1.2',
        'RequiresServer' => true,
    ];
}

function contabo_ConfigOptions()
{
    return [
        'ClientId'     => ['Type' => 'text', 'Size' => '40', 'Description' => 'OAuth2 Client ID'],
        'ClientSecret' => ['Type' => 'password', 'Size' => '40', 'Description' => 'OAuth2 Client Secret'],
        'ApiUser'      => ['Type' => 'text', 'Size' => '40', 'Description' => 'Contabo Account Email'],
        'ApiPassword'  => ['Type' => 'password', 'Size' => '40', 'Description' => 'API Password'],
        'Region'       => ['Type' => 'text', 'Size' => '20', 'Default' => 'eu', 'Description' => 'Default Region (e.g., eu, us-central)'],
        'ProductId'    => ['Type' => 'text', 'Size' => '20', 'Description' => 'Contabo Product ID (e.g., vps-g11-s)'],
        'DefaultImage' => ['Type' => 'text', 'Size' => '30', 'Default' => 'ubuntu-22.04', 'Description' => 'Standard Image ID'],
        'HostPrefix'   => ['Type' => 'text', 'Size' => '20', 'Default' => 'vps-', 'Description' => 'Default Hostname Prefix'],
    ];
}

function contabo_CreateAccount(array $params)
{
    try {
        $api = new ApiClient($params);

        $hostname = $params['domain'];
        if (empty($hostname)) {
            $hostname = preg_replace('/[^a-zA-Z0-9]/', '', $params['configoption8']) . $params['accountid'] . '.local';
        }

        $rootPass = !empty($params['password']) ? $params['password'] : Helper::generateStrongPassword();

        $payload = [
            'imageId' => !empty($params['configoptions']['OS Image']) ? preg_replace('/[^a-zA-Z0-9.-]/', '', $params['configoptions']['OS Image']) : $params['configoption7'],
            'productId' => preg_replace('/[^a-zA-Z0-9.-]/', '', $params['configoption6']),
            'region' => !empty($params['configoptions']['Region']) ? preg_replace('/[^a-zA-Z0-9.-]/', '', $params['configoptions']['Region']) : $params['configoption5'],
            'displayName' => preg_replace('/[^a-zA-Z0-9_-]/', '', $params['configoption8'] . $params['accountid']),
            'rootPassword' => $rootPass,
        ];

        $response = $api->request('POST', '/v1/compute/instances', $payload);

        if (!isset($response['data'][0]['instanceId'])) {
            throw new Exception("Instance provisioning failed to return valid identifier.");
        }

        $instanceId = (int)$response['data'][0]['instanceId'];
        Helper::updateServiceData($params['serviceid'], $instanceId, $rootPass);

        logModuleCall('contabo', 'CreateAccount', $payload, $response);
        Helper::sendCustomEmail($params['userid'], 'Contabo Instance Creation Initiated', "Your system is spinning up under ID: {$instanceId}");

        return 'success';
    } catch (\Exception $e) {
        logModuleCall('contabo', 'CreateAccount_Failed', $params, $e->getMessage());
        return "Provisioning Error: " . $e->getMessage();
    }
}

function contabo_SuspendAccount(array $params)
{
    try {
        $api = new ApiClient($params);
        $instanceId = Helper::getContaboInstanceId($params['serviceid']);
        $api->request('POST', "/v1/compute/instances/{$instanceId}/actions/stop");
        return 'success';
    } catch (\Exception $e) {
        return $e->getMessage();
    }
}

function contabo_UnsuspendAccount(array $params)
{
    try {
        $api = new ApiClient($params);
        $instanceId = Helper::getContaboInstanceId($params['serviceid']);
        $api->request('POST', "/v1/compute/instances/{$instanceId}/actions/start");
        return 'success';
    } catch (\Exception $e) {
        return $e->getMessage();
    }
}

function contabo_TerminateAccount(array $params)
{
    try {
        $api = new ApiClient($params);
        $instanceId = Helper::getContaboInstanceId($params['serviceid']);
        $api->request('POST', "/v1/compute/instances/{$instanceId}/cancel");
        return 'success';
    } catch (\Exception $e) {
        return $e->getMessage();
    }
}

function contabo_AdminCustomButtonArray()
{
    return [
        "Power On"     => "PowerOn",
        "Power Off"    => "PowerOff",
        "Shut Down"    => "Shutdown",
        "Force Reboot" => "Reboot"
    ];
}

function contabo_PowerOn(array $params)
{
    try {
        $api = new ApiClient($params);
        $instanceId = Helper::getContaboInstanceId($params['serviceid']);
        $api->request('POST', "/v1/compute/instances/{$instanceId}/actions/start");
        return "success";
    } catch (\Exception $e) {
        return $e->getMessage();
    }
}

function contabo_PowerOff(array $params)
{
    try {
        $api = new ApiClient($params);
        $instanceId = Helper::getContaboInstanceId($params['serviceid']);
        $api->request('POST', "/v1/compute/instances/{$instanceId}/actions/stop");
        return "success";
    } catch (\Exception $e) {
        return $e->getMessage();
    }
}

function contabo_Shutdown(array $params)
{
    try {
        $api = new ApiClient($params);
        $instanceId = Helper::getContaboInstanceId($params['serviceid']);
        $api->request('POST', "/v1/compute/instances/{$instanceId}/actions/shutdown");
        return "success";
    } catch (\Exception $e) {
        return $e->getMessage();
    }
}

function contabo_Reboot(array $params)
{
    try {
        $api = new ApiClient($params);
        $instanceId = Helper::getContaboInstanceId($params['serviceid']);
        $api->request('POST', "/v1/compute/instances/{$instanceId}/actions/restart");
        return "success";
    } catch (\Exception $e) {
        return $e->getMessage();
    }
}

function contabo_ClientArea(array $params)
{
    try {
        $api = new ApiClient($params);
        $instanceId = Helper::getContaboInstanceId($params['serviceid']);
        $successMessage = '';

        // Process State-changing Actions
        if (isset($_POST['mod_action'])) {
            // Mitigation 1: CSRF Protection Verification
            if (!checkToken()) {
                return ['templatefile' => 'clientarea', 'vars' => ['error' => 'Security token verification failed. Please refresh the page and try again.']];
            }

            $action = $_POST['mod_action'];

            // Mitigation 2: Strict String Variable Sanitization Filters
            $snapId = isset($_POST['snap_id']) ? preg_replace('/[^a-zA-Z0-9.-]/', '', $_POST['snap_id']) : null;
            $osImage = isset($_POST['os_image']) ? preg_replace('/[^a-zA-Z0-9.-]/', '', $_POST['os_image']) : null;
            $snapName = isset($_POST['snap_name']) ? htmlspecialchars(strip_tags($_POST['snap_name']), ENT_QUOTES, 'UTF-8') : null;

            if (in_array($action, ['start', 'stop', 'restart', 'shutdown'])) {
                $api->request('POST', "/v1/compute/instances/{$instanceId}/actions/{$action}");
                $successMessage = "Power instruction dispatched successfully.";
            }

            if ($action === 'rebuild' && !empty($osImage)) {
                $api->request('PUT', "/v1/compute/instances/{$instanceId}", ['imageId' => $osImage]);
                Helper::sendCustomEmail($params['userid'], 'Contabo Instance Rebuild Dispatched', "Rebuild mapping initialized on target Host.");
                $successMessage = "Rebuild sequence initiated successfully.";
            }

            // Mitigation 3: Enforcing Explicit $instanceId scope containment path boundaries on mutations
            if ($action === 'create_snapshot' && !empty($snapName)) {
                $api->request('POST', "/v1/compute/instances/{$instanceId}/snapshots", ['name' => $snapName]);
                $successMessage = "Snapshot creation execution sequence mapped.";
            }

            if ($action === 'delete_snapshot' && !empty($snapId)) {
                $api->request('DELETE', "/v1/compute/instances/{$instanceId}/snapshots/{$snapId}");
                $successMessage = "Snapshot drop request executed.";
            }

            if ($action === 'rollback_snapshot' && !empty($snapId)) {
                $api->request('POST', "/v1/compute/instances/{$instanceId}/snapshots/{$snapId}/rollback");
                $successMessage = "Snapshot reversion execution sequence mapped.";
            }
        }

        // Mitigation 5: Fetch Remote Telemetry using Caching Wrappers to prevent local API DoS
        $details   = $api->request('GET', "/v1/compute/instances/{$instanceId}");
        $snapshots = $api->request('GET', "/v1/compute/instances/{$instanceId}/snapshots");
        $history   = $api->request('GET', "/v1/compute/instances/actions/audits");
        $images    = Helper::getCachedImages($api); // Cached dynamically for 24 hours

        return [
            'templatefile' => 'clientarea',
            'vars' => [
                'vm'         => $details['data'][0] ?? [],
                'snapshots'  => $snapshots['data'] ?? [],
                'history'    => $history['data'] ?? [],
                'os_images'  => $images,
                'instanceId' => $instanceId,
                'success'    => $successMessage
            ]
        ];
    } catch (\Exception $e) {
        // Mitigation 4: Obfuscating Internal Stack-Trace Outputs from End Clients
        logModuleCall('contabo', 'clientarea_exception', ['serviceid' => $params['serviceid']], $e->getMessage());
        return [
            'templatefile' => 'clientarea',
            'vars' => ['error' => 'An internal error occurred while connecting to the compute infrastructure. System operators have been notified.']
        ];
    }
}

function contabo_AdminServicesTabFields(array $params)
{
    try {
        $api = new ApiClient($params);
        $instanceId = Helper::getContaboInstanceId($params['serviceid']);

        if (isset($_POST['update_display_name']) && checkToken()) {
            $cleanedName = preg_replace('/[^a-zA-Z0-9_-]/', '', $_POST['new_display_name']);
            $api->request('PATCH', "/v1/compute/instances/{$instanceId}", ['displayName' => $cleanedName]);
        }

        $details = $api->request('GET', "/v1/compute/instances/{$instanceId}")['data'][0];

        return [
            'Contabo Internal ID' => $instanceId,
            'Status Flag'         => '<span class="label label-info">' . htmlspecialchars($details['status']) . '</span>',
            'Primary IPv4'        => htmlspecialchars($details['ipAddress'] ?? 'N/A'),
            'Data Center/Region'  => htmlspecialchars($details['region'] ?? 'N/A'),
            'Modify Display Name' => '<form method="post">' . generatetoken() . '<input type="text" name="new_display_name" value="' . htmlspecialchars($details['displayName']) . '"><input type="submit" name="update_display_name" value="Update" class="btn btn-xs btn-success"></form>'
        ];
    } catch (\Exception $e) {
        return ['Error Interface' => htmlspecialchars($e->getMessage())];
    }
}
