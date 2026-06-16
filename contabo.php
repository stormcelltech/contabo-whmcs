<?php
/**
 * Contabo VPS/VDS WHMCS Server Module
 * Supports WHMCS 8.x+
 * Cleaned & Refactored for Native Custom Field Instance ID Management
 */

if (!defined('WHMCS')) {
    die('This file cannot be accessed directly');
}

use Illuminate\Database\Capsule\Manager as Capsule;
use WHMCS\Module\Server\Contabo\ContaboHelper;

// Autoload the lib directory files
require_once __DIR__ . '/lib/ContaboApi.php';
require_once __DIR__ . '/lib/ContaboHelper.php';
function contabo_ConfigOptions()
{
    return [
        'API Client ID' => [
            'FriendlyName' => 'API Client ID',
            'Type'         => 'text',
            'Size'         => '50',
            'Description'  => 'Your Contabo API Client ID',
        ],
        'API Client Secret' => [
            'FriendlyName' => 'API Client Secret',
            'Type'         => 'password',
            'Size'         => '50',
            'Description'  => 'Your Contabo API Client Secret',
        ],
        'API Username' => [
            'FriendlyName' => 'API Username (Email)',
            'Type'         => 'text',
            'Size'         => '50',
            'Description'  => 'Your Contabo API Account Email Address',
        ],
        'API Password' => [
            'FriendlyName' => 'API Password',
            'Type'         => 'password',
            'Size'         => '50',
            'Description'  => 'Your Contabo API Password',
        ],
        'Default Region' => [
            'FriendlyName' => 'Default Region',
            'Type'         => 'dropdown',
            'Options'      => 'European Union (EU),US Central (US-central),US East (US-east),US West (US-west),Singapore (SIN),United Kingdom (UK),Australia (AUS),Japan (JPN),India (IND)',
            'Default'      => 'European Union (EU)',
        ],
        'Product ID' => [
            'FriendlyName' => 'Default Product ID',
            'Type'         => 'text',
            'Size'         => 10,
            'Default'      => 'V92',
        ],
        'Image ID' => [
            'FriendlyName' => 'Default OS Image UUID',
            'Type'         => 'text',
            'Size'         => 40,
            'Default'      => 'afecbb85-e2fc-46f0-9684-b46b1faf00bb',
        ],
        'Hostname Prefix' => [
            'FriendlyName' => 'Hostname Prefix',
            'Type'         => 'text',
            'Size'         => 20,
            'Default'      => 'srv',
        ],
        'Contract Period' => [
            'FriendlyName' => 'Contract Period (months)',
            'Type'         => 'dropdown',
            'Options'      => '1,3,6,12',
            'Default'      => '1',
        ],
        'Enable Snapshots' => [
            'FriendlyName' => 'Allow Client Snapshots',
            'Type'         => 'yesno',
            'Default'      => 'yes',
        ],
    ];
}

function contabo_getApi(array $params)
{
    // Fetch directly from the module product settings instead of the server fields
    $clientId     = $params['configoption1'] ?? '';
    $clientSecret = $params['configoption2'] ?? '';
    $apiUsername  = $params['configoption3'] ?? '';
    $apiPassword  = $params['configoption4'] ?? '';

    if (empty($clientId) || empty($clientSecret) || empty($apiUsername) || empty($apiPassword)) {
        throw new \Exception('Contabo API credentials are fundamentally missing from the product configuration.');
    }

    return new \Contabo\Api\ContaboApi(
        $clientId,
        $clientSecret,
        $apiUsername,
        $apiPassword
    );
}

/**
 * Clean and robust helper to fetch the Instance ID straight from the Custom Fields database mapping
 */
function contabo_getInstanceId(array $params)
{
    $serviceId = (int)$params['serviceid'];

    // Read directly from the WHMCS custom fields value table for this specific service row
    $customFieldVal = Capsule::table('tblcustomfields')
        ->join('tblcustomfieldsvalues', 'tblcustomfields.id', '=', 'tblcustomfieldsvalues.fieldid')
        ->where('tblcustomfields.fieldname', 'like', 'Instance ID%')
        ->where('tblcustomfieldsvalues.relid', $serviceId)
        ->value('tblcustomfieldsvalues.value');

    if (!empty($customFieldVal) && is_numeric($customFieldVal)) {
        return (int)$customFieldVal;
    }

    // Backup Fallback: Check if it is still inside the Dedicated IP box for older unmigrated accounts
    $row = Capsule::table('tblhosting')->where('id', $serviceId)->first();
    if ($row && $row->dedicatedip && is_numeric($row->dedicatedip)) {
        return (int)$row->dedicatedip;
    }

    throw new \Exception('Contabo Instance ID not found. Please scroll down and enter a numeric ID into your "Instance ID" Custom Field box.');
}

function contabo_CreateAccount(array $params)
{
    try {
        $api = contabo_getApi($params);

        $regionFull = $params['configoption1'] ?? 'European Union (EU)';
        preg_match('/\(([^)]+)\)/', $regionFull, $m);
        $region = $m[1] ?? 'EU';

        $productId = $params['configoption2'] ?: 'V92';
        $imageId   = $params['configoption3'] ?: 'afecbb85-e2fc-46f0-9684-b46b1faf00bb';
        $prefix    = $params['configoption4'] ?: 'srv';
        $period    = (int)($params['configoption5'] ?? 1);

        $displayName = $prefix . '-' . $params['serviceid'];

        $result = $api->createInstance([
            'imageId'     => $imageId,
            'productId'   => $productId,
            'region'      => $region,
            'period'      => $period,
            'displayName' => $displayName,
            'defaultUser' => 'root',
        ]);

        if (!empty($result['data'][0]['instanceId'])) {
            $instanceId = $result['data'][0]['instanceId'];

            // Store inside our namespaced Custom Field via the custom field box helper
            ContaboHelper::setServiceCustomField($params['serviceid'], 'Instance ID', $instanceId);

            // Fetch primary network properties to set the Dedicated IP input automatically
            $ipAddress = $result['data'][0]['ipConfig']['v4']['ip'] ?? '';
            
            Capsule::table('tblhosting')->where('id', $params['serviceid'])->update([
                'dedicatedip' => $ipAddress, // The plain text IP goes here now!
                'notes'       => 'Contabo instanceId: ' . $instanceId,
            ]);

            localAPI('SendEmail', [
                'messagename' => 'Contabo VPS Created',
                'id'          => $params['serviceid'],
            ]);
        }

        return 'success';
    } catch (\Exception $e) {
        logModuleCall('contabo', 'CreateAccount', $params, $e->getMessage(), 'error');
        return $e->getMessage();
    }
}

function contabo_SuspendAccount(array $params)
{
    try {
        $api = contabo_getApi($params);
        $api->shutdownInstance(contabo_getInstanceId($params));
        return 'success';
    } catch (\Exception $e) {
        logModuleCall('contabo', 'SuspendAccount', $params, $e->getMessage(), 'error');
        return $e->getMessage();
    }
}

function contabo_UnsuspendAccount(array $params)
{
    try {
        $api = contabo_getApi($params);
        $api->startInstance(contabo_getInstanceId($params));
        return 'success';
    } catch (\Exception $e) {
        logModuleCall('contabo', 'UnsuspendAccount', $params, $e->getMessage(), 'error');
        return $e->getMessage();
    }
}

function contabo_TerminateAccount(array $params)
{
    try {
        $api = contabo_getApi($params);
        $api->cancelInstance(contabo_getInstanceId($params));
        return 'success';
    } catch (\Exception $e) {
        logModuleCall('contabo', 'TerminateAccount', $params, $e->getMessage(), 'error');
        return $e->getMessage();
    }
}

function contabo_AdminCustomButtonArray()
{
    return [
        'Power On'       => 'PowerOn',
        'Power Off'      => 'PowerOff',
        'Shut Down'      => 'ShutDown',
        'Reboot'         => 'Reboot',
        'View Details'   => 'ViewDetails',
        'Snapshots'      => 'Snapshots',
        'Rebuild'        => 'Rebuild',
        'Rename'         => 'Rename',
        'Task History'   => 'TaskHistory',
    ];
}

function contabo_PowerOn(array $params)
{
    try {
        // Pass an explicit empty array array block to prevent empty body handling errors
        contabo_getApi($params)->startInstance(contabo_getInstanceId($params), []);
        logModuleCall('contabo', 'PowerOn', $params, 'Instance powered on', 'success');
        return 'success';
    } catch (\Exception $e) {
        logModuleCall('contabo', 'PowerOn', $params, $e->getMessage(), 'error');
        return $e->getMessage();
    }
}
function contabo_PowerOff(array $params)
{
    try {
        // Pass empty array payload to satisfy Content-Type requirements
        contabo_getApi($params)->stopInstance(contabo_getInstanceId($params), []);
        logModuleCall('contabo', 'PowerOff', $params, 'Instance powered off', 'success');
        return 'success';
    } catch (\Exception $e) {
        logModuleCall('contabo', 'PowerOff', $params, $e->getMessage(), 'error');
        return $e->getMessage();
    }
}
function contabo_ShutDown(array $params)
{
    try {
        // Pass empty array payload to satisfy Content-Type requirements
        contabo_getApi($params)->shutdownInstance(contabo_getInstanceId($params), []);
        logModuleCall('contabo', 'ShutDown', $params, 'Instance shut down', 'success');
        return 'success';
    } catch (\Exception $e) {
        logModuleCall('contabo', 'ShutDown', $params, $e->getMessage(), 'error');
        return $e->getMessage();
    }
}

function contabo_Reboot(array $params)
{
    try {
        // Pass an explicit empty array array block here too
        contabo_getApi($params)->restartInstance(contabo_getInstanceId($params), []);
        logModuleCall('contabo', 'Reboot', $params, 'Instance rebooted', 'success');
        return 'success';
    } catch (\Exception $e) {
        logModuleCall('contabo', 'Reboot', $params, $e->getMessage(), 'error');
        return $e->getMessage();
    }
}

/**
 * Display clean diagnostic indicators using the native custom field mapping data
 */
function contabo_AdminServicesTabFields(array $params)
{
    try {
        $api        = contabo_getApi($params);
        $instanceId = contabo_getInstanceId($params);
        $data       = $api->getInstance($instanceId);
        
        // Debug check: If API returns nothing, throw an error so it hits the catch block
        if (empty($data) || empty($data['data'][0])) {
            throw new \Exception('API connected successfully, but no instance data was returned for ID: ' . $instanceId);
        }

        $inst = $data['data'][0];

        $ipv4 = $inst['ipConfig']['v4']['ip']      ?? 'N/A';
        $ipv6 = $inst['ipConfig']['v6']['ip']      ?? 'N/A';
        $gw   = $inst['ipConfig']['v4']['gateway'] ?? 'N/A';

        // Cast everything to strings. WHMCS can break if an integer or null is passed directly into a tab array value.
        return [
            'Instance ID'    => (string)($inst['instanceId'] ?? 'N/A'),
            'Display Name'   => (string)($inst['displayName'] ?? 'N/A'),
            'Status'         => '<span style="text-transform:capitalize; font-weight:bold;">' . htmlspecialchars($inst['status'] ?? 'unknown') . '</span>',
            'Region'         => htmlspecialchars(($inst['regionName'] ?? '') . ' (' . ($inst['region'] ?? '') . ')'),
            'Data Center'    => htmlspecialchars($inst['dataCenter'] ?? 'N/A'),
            'Product'        => htmlspecialchars(($inst['productName'] ?? '') . ' (' . ($inst['productId'] ?? '') . ')'),
            'Product Type'   => htmlspecialchars($inst['productType'] ?? 'N/A'),
            'RAM (MB)'       => (string)($inst['ramMb'] ?? 'N/A'),
            'CPU Cores'      => (string)($inst['cpuCores'] ?? 'N/A'),
            'Disk (MB)'      => (string)($inst['diskMb'] ?? 'N/A'),
            'OS Type'        => htmlspecialchars($inst['osType'] ?? 'N/A'),
            'IPv4 Address'   => htmlspecialchars($ipv4),
            'IPv6 Address'   => htmlspecialchars($ipv6),
            'Gateway'        => htmlspecialchars($gw),
            'MAC Address'    => htmlspecialchars($inst['macAddress'] ?? 'N/A'),
            'Created'        => htmlspecialchars($inst['createdDate'] ?? 'N/A'),
            'Default User'   => htmlspecialchars($inst['defaultUser'] ?? 'N/A'),
        ];
    } catch (\Exception $e) {
        // If it fails, log it immediately to WHMCS Module Logs and return the error visually
        logModuleCall('contabo', 'AdminServicesTabFields_Error', $params, $e->getMessage(), 'error');
        return [
            'Contabo Error' => '<div class="alert alert-danger" style="margin:0;">' . htmlspecialchars($e->getMessage()) . '</div>'
        ];
    }
}

function contabo_ViewDetails(array $params)
{
    // Re-use the tab fields function
    $fields = contabo_AdminServicesTabFields($params);
    
    ob_start();
    ?>
    <div class="panel panel-default" style="margin-top: 15px;">
        <div class="panel-heading"><h3 class="panel-title">Contabo API Details</h3></div>
        <table class="table table-bordered table-striped">
            <tbody>
                <?php foreach ($fields as $label => $value): ?>
                    <tr>
                        <td width="30%"><strong><?php echo htmlspecialchars($label); ?></strong></td>
                        <td><?php echo $value; ?></td> </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php
    return ob_get_clean();
}

function contabo_Snapshots(array $params)
{
    try {
        $api        = contabo_getApi($params);
        $instanceId = contabo_getInstanceId($params);

        $action = $_POST['snapshot_action'] ?? '';
        $msg    = '';
        if ($action === 'create') {
            $name = htmlspecialchars($_POST['snapshot_name'] ?? 'admin-snap-' . date('YmdHis'));
            $desc = htmlspecialchars($_POST['snapshot_desc'] ?? '');
            $api->createSnapshot($instanceId, $name, $desc);
            $msg = '<div class="alert alert-success">Snapshot created successfully.</div>';
        } elseif ($action === 'delete') {
            $snapshotId = $_POST['snapshot_id'] ?? '';
            if ($snapshotId) {
                $api->deleteSnapshot($instanceId, $snapshotId);
                $msg = '<div class="alert alert-success">Snapshot deleted.</div>';
            }
        } elseif ($action === 'restore') {
            $snapshotId = $_POST['snapshot_id'] ?? '';
            if ($snapshotId) {
                $api->restoreSnapshot($instanceId, $snapshotId);
                $msg = '<div class="alert alert-success">Restore initiated.</div>';
            }
        }

        $snapData  = $api->getSnapshots($instanceId);
        $snapshots = $snapData['data'] ?? [];

        ob_start();
        ?>
        <div class="panel panel-default">
            <div class="panel-heading"><h3 class="panel-title">Manage Snapshots</h3></div>
            <div class="panel-body">
                <?php echo $msg; ?>
                <h4>Create New Snapshot</h4>
                <form method="post" class="form-horizontal">
                    <input type="hidden" name="snapshot_action" value="create">
                    <div class="form-group">
                        <label class="col-sm-2 control-label">Snapshot Name</label>
                        <div class="col-sm-6"><input type="text" class="form-control" name="snapshot_name" value="snap-<?php echo date('YmdHis'); ?>" required></div>
                    </div>
                    <div class="form-group">
                        <label class="col-sm-2 control-label">Description</label>
                        <div class="col-sm-6"><textarea class="form-control" name="snapshot_desc" rows="2"></textarea></div>
                    </div>
                    <div class="form-group">
                        <div class="col-sm-offset-2 col-sm-6"><button type="submit" class="btn btn-primary">Create Snapshot</button></div>
                    </div>
                </form>
                <hr>
                <h4>Existing Snapshots</h4>
                <?php if (empty($snapshots)): ?>
                    <p class="text-muted">No snapshots found.</p>
                <?php else: ?>
                    <table class="table table-striped table-condensed">
                        <thead><tr><th>Snapshot ID</th><th>Name</th><th>Created</th><th>Size (GB)</th><th>Actions</th></tr></thead>
                        <tbody>
                            <?php foreach ($snapshots as $snap): ?>
                                <tr>
                                    <td><code><?php echo htmlspecialchars($snap['snapshotId']); ?></code></td>
                                    <td><?php echo htmlspecialchars($snap['name']); ?></td>
                                    <td><?php echo date('Y-m-d H:i', strtotime($snap['createdDate'])); ?></td>
                                    <td><?php echo round($snap['size'] / 1024 / 1024 / 1024, 2); ?></td>
                                    <td>
                                        <form method="post" style="display: inline;">
                                            <input type="hidden" name="snapshot_action" value="restore">
                                            <input type="hidden" name="snapshot_id" value="<?php echo htmlspecialchars($snap['snapshotId']); ?>">
                                            <button type="submit" class="btn btn-xs btn-info" onclick="return confirm('Restore snapshot?');">Restore</button>
                                        </form>
                                        <form method="post" style="display: inline;">
                                            <input type="hidden" name="snapshot_action" value="delete">
                                            <input type="hidden" name="snapshot_id" value="<?php echo htmlspecialchars($snap['snapshotId']); ?>">
                                            <button type="submit" class="btn btn-xs btn-danger" onclick="return confirm('Delete snapshot?');">Delete</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
        <?php
        return ob_get_clean();
    } catch (\Exception $e) {
        return '<div class="alert alert-danger">' . htmlspecialchars($e->getMessage()) . '</div>';
    }
}
// rebuild server in admin
function contabo_Rebuild(array $params)
{
    try {
        $api        = contabo_getApi($params);
        $instanceId = contabo_getInstanceId($params);
        $msg        = '';

        // Handle Form Post submission for execution
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['image_id'])) {
            // Reinstall API expects an array with imageId matching Contabo spec
            $api->reinstallInstance($instanceId, [
                'imageId' => $_POST['image_id']
            ]);
            
            localAPI('SendEmail', [
                'messagename' => 'Contabo VPS Rebuild',
                'id'          => $params['serviceid'],
            ]);
            $msg = '<div class="alert alert-success">Rebuild request successfully sent to Contabo. Client has been notified.</div>';
        }

        // Fetch OS Images list from API
        $imagesData = $api->getImages(1, 100);
        $images     = $imagesData['data'] ?? [];

        ob_start();
        ?>
        <div class="panel panel-default" style="margin-top: 15px;">
            <div class="panel-heading">
                <h3 class="panel-title">Rebuild Server Instance</h3>
            </div>
            <div class="panel-body">
                <?php echo $msg; ?>
                
                <form method="post" class="form-horizontal">
                    <div class="alert alert-danger">
                        <strong>CRITICAL WARNING:</strong> Rebuilding this server will erase all existing files, installations, and data permanently. This action cannot be reversed.
                    </div>
                    
                    <div class="form-group">
                        <label class="col-sm-3 control-label">Available Operating Systems</label>
                        <div class="col-sm-7">
                            <select class="form-control" name="image_id" required>
                                <option value="">-- Choose Operating System Target --</option>
                                <?php if (empty($images)): ?>
                                    <option value="" disabled>No OS templates returned from Contabo API.</option>
                                <?php else: ?>
                                    <?php foreach ($images as $image): ?>
                                        <option value="<?php echo htmlspecialchars($image['imageId']); ?>">
                                            <?php echo htmlspecialchars(($image['name'] ?? 'Unknown') . ' [' . ($image['operatingSystem'] ?? 'Linux') . ']'); ?>
                                        </option>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <div class="col-sm-offset-3 col-sm-7">
                            <button type="submit" class="btn btn-danger" 
                                    onclick="return confirm('Are you completely sure you want to format and rebuild this server? All data will be lost.');">
                                Execute Rebuild Command
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
        <?php
        return ob_get_clean();
    } catch (\Exception $e) {
        return '<div class="alert alert-danger" style="margin-top:15px;"><strong>Failed to load Rebuild Utility:</strong> ' . htmlspecialchars($e->getMessage()) . '</div>';
    }
}

function contabo_Rename(array $params)
{
    try {
        $api        = contabo_getApi($params);
        $instanceId = contabo_getInstanceId($params);

        $msg = '';
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['display_name'])) {
            $newName = htmlspecialchars($_POST['display_name']);
            $api->updateInstance($instanceId, $newName);
            $msg = '<div class="alert alert-success">Display name updated to: <strong>' . $newName . '</strong></div>';
        }

        $data = $api->getInstance($instanceId);
        $inst = $data['data'][0] ?? [];

        ob_start();
        ?>
        <div class="panel panel-default" style="margin-top: 15px;">
            <div class="panel-heading">
                <h3 class="panel-title">Rename Instance</h3>
            </div>
            <div class="panel-body">
                <?php echo $msg; ?>
                
                <form method="post" class="form-horizontal">
                    <div class="form-group">
                        <label class="col-sm-2 control-label">Current Name</label>
                        <div class="col-sm-6">
                            <p class="form-control-static"><strong><?php echo htmlspecialchars($inst['displayName'] ?? 'N/A'); ?></strong></p>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="col-sm-2 control-label">New Name</label>
                        <div class="col-sm-6">
                            <input type="text" class="form-control" name="display_name" 
                                   placeholder="Enter new custom name here..." required>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <div class="col-sm-offset-2 col-sm-6">
                            <button type="submit" class="btn btn-primary">Update Name</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
        <?php
        return ob_get_clean();
    } catch (\Exception $e) {
        return '<div class="alert alert-danger" style="margin-top:15px;">' . htmlspecialchars($e->getMessage()) . '</div>';
    }
}

function contabo_TaskHistory(array $params)
{
    try {
        $api        = contabo_getApi($params);
        $instanceId = contabo_getInstanceId($params);

        $page    = max(1, (int)($_GET['apage'] ?? 1));
        $audits  = $api->getInstanceAudits($instanceId, $page, 20);
        $actions = $api->getActionAudits($instanceId, $page, 20);

        ob_start();
        ?>
        <div class="panel panel-default">
            <div class="panel-heading"><h3 class="panel-title">Task History</h3></div>
            <div class="panel-body">
                <ul class="nav nav-tabs" role="tablist">
                    <li role="presentation" class="active"><a href="#actions" aria-controls="actions" role="tab" data-toggle="tab">Actions</a></li>
                    <li role="presentation"><a href="#audits" aria-controls="audits" role="tab" data-toggle="tab">Audits</a></li>
                </ul>
                <div class="tab-content">
                    <div role="tabpanel" class="tab-pane active" id="actions">
                        <?php if (empty($actions['data'])): ?>
                            <p class="text-muted" style="margin-top:20px;">No recent power action changes logs found.</p>
                        <?php else: ?>
                            <table class="table table-striped table-condensed" style="margin-top:20px;">
                                <thead><tr><th>Timestamp</th><th>Action</th><th>Status</th></tr></thead>
                                <tbody>
                                    <?php foreach ($actions['data'] as $action): ?>
                                        <tr>
                                            <td><?php echo date('Y-m-d H:i:s', strtotime($action['actionTime'])); ?></td>
                                            <td><?php echo htmlspecialchars($action['action']); ?></td>
                                            <td><span class="label label-<?php echo ($action['status'] === 'success') ? 'success' : 'warning'; ?>"><?php echo ucfirst($action['status']); ?></span></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </div>
                    <div role="tabpanel" class="tab-pane" id="audits">
                        <?php if (empty($audits['data'])): ?>
                            <p class="text-muted" style="margin-top:20px;">No infrastructure configuration audits logs found.</p>
                        <?php else: ?>
                            <table class="table table-striped table-condensed" style="margin-top:20px;">
                                <thead><tr><th>Timestamp</th><th>User</th><th>Property Change</th><th>Value</th></tr></thead>
                                <tbody>
                                    <?php foreach ($audits['data'] as $audit): ?>
                                        <tr>
                                            <td><?php echo date('Y-m-d H:i:s', strtotime($audit['auditTime'])); ?></td>
                                            <td><?php echo htmlspecialchars($audit['userEmail']); ?></td>
                                            <td><?php echo htmlspecialchars($audit['change']); ?></td>
                                            <td><code><?php echo htmlspecialchars($audit['newValue']); ?></code></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    } catch (\Exception $e) {
        return '<div class="alert alert-danger">' . htmlspecialchars($e->getMessage()) . '</div>';
    }
}
// ============================================================================
// Client Area - Global Overview & Product Details Controller
// ============================================================================

function contabo_ClientArea(array $params)
{
    try {
        //  Only run full API validation if explicitly loading ONE specific service details page.
        // This completely prevents the global user dashboard service list page from loading blank.
        if (empty($params['serviceid']) || !isset($_GET['id']) || (int)$_GET['id'] !== (int)$params['serviceid']) {
            return [];
        }

        $api        = contabo_getApi($params);
        $instanceId = contabo_getInstanceId($params);
        $data       = $api->getInstance($instanceId);
        
        if (empty($data) || empty($data['data'][0])) {
            throw new \Exception('Unable to retrieve instance details from Contabo API.');
        }
        
        $inst = $data['data'][0];

        // Snapshots verification (config option shifted to index 10 due to API keys)
        $snapshots = [];
        $snapshotsEnabled = ($params['configoption10'] ?? 'yes') === 'yes';
        
        if ($snapshotsEnabled && isset($inst['productType']) && strtolower($inst['productType']) !== 'vds') {
            try {
                $snapData  = $api->getSnapshots($instanceId);
                $snapshots = $snapData['data'] ?? [];
            } catch (\Exception $se) {
                logModuleCall('contabo', 'ClientArea_Snapshots_Error', $params, $se->getMessage(), 'error');
            }
        }

        // Audits / Action Logs (Recent 10)
        $audits = [];
        try {
            $auditData = $api->getActionAudits($instanceId, 1, 10);
            $audits    = $auditData['data'] ?? [];
        } catch (\Exception $ae) {
            logModuleCall('contabo', 'ClientArea_Audits_Error', $params, $ae->getMessage(), 'error');
        }

        // Available OS Distribution Images for client reinstallation / rebuild
        $images = [];
        try {
            $imagesData = $api->getImages(1, 100);
            $images     = $imagesData['data'] ?? [];
        } catch (\Exception $ie) {
            logModuleCall('contabo', 'ClientArea_Images_Error', $params, $ie->getMessage(), 'error');
        }

        return [
            'templatefile' => 'clientarea',
            'vars'         => [
                'instance'         => $inst,
                'snapshots'        => $snapshots,
                'snapshotsEnabled' => $snapshotsEnabled,
                'audits'           => $audits,
                'images'           => $images,
                'modulelink'       => $params['modulelink'] ?? '',
            ],
        ];
    } catch (\Exception $e) {
        logModuleCall('contabo', 'ClientArea_Fatal_Error', $params, $e->getMessage(), 'error');
        return [
            'templatefile' => 'clientarea',
            'vars'         => [
                'apiError' => $e->getMessage(),
                'instance' => false,
            ],
        ];
    }
}

function contabo_ClientAreaCustomButtonArray()
{
   return [
        'Power On'  => 'ClientPowerOn',
        'Power Off' => 'ClientPowerOff',
        'Reboot'    => 'ClientReboot',
    ];
}

// ============================================================================
// Client Area - Default Direct Buttons Mapping
// ============================================================================
function contabo_ClientPowerOn(array $params)
{
    try {
        contabo_getApi($params)->startInstance(contabo_getInstanceId($params));
        logModuleCall('contabo', 'ClientPowerOn', $params, 'Power on command sent.', 'success');
        return 'success';
    } catch (\Exception $e) {
        logModuleCall('contabo', 'ClientPowerOn', $params, $e->getMessage(), 'error');
        return $e->getMessage();
    }
}

function contabo_ClientPowerOff(array $params)
{
    try {
    
    // Pass empty array payload to satisfy Content-Type requirements
        contabo_getApi($params)->stopInstance(contabo_getInstanceId($params), []);
        logModuleCall('contabo', 'ClientPowerOff', $params, 'Instance powered off by client', 'success');
        return 'success';
    } catch (\Exception $e) {
        logModuleCall('contabo', 'ClientPowerOff', $params, $e->getMessage(), 'error');
        return $e->getMessage();
    }
}

function contabo_ClientReboot(array $params)
{
    try {
        contabo_getApi($params)->restartInstance(contabo_getInstanceId($params));
        logModuleCall('contabo', 'ClientReboot', $params, 'Reboot cycle command sent.', 'success');
        return 'success';
    } catch (\Exception $e) {
        logModuleCall('contabo', 'ClientReboot', $params, $e->getMessage(), 'error');
        return $e->getMessage();
    }
}

function contabo_ClientAreaAllowedFunctions()
{
    return [
        'PowerOn', 'PowerOff', 'Reboot',
        'CreateSnapshot', 'DeleteSnapshot', 'RestoreSnapshot',
        'Rebuild', 'TaskHistory',
    ];
}

function contabo_ClientAreaPage(array $params)
{
    $action = $_REQUEST['action'] ?? '';

    if (!$action) {
        return contabo_ClientArea($params);
    }

    try {
        $api        = contabo_getApi($params);
        $instanceId = contabo_getInstanceId($params);
        $response   = ['success' => false, 'message' => 'Unknown action'];

        switch ($action) {
            case 'PowerOn':
                $api->startInstance($instanceId);
                $response = ['success' => true, 'message' => 'Instance is starting.'];
                break;

            case 'PowerOff':
                $api->stopInstance($instanceId);
                $response = ['success' => true, 'message' => 'Instance is powering off.'];
                break;

            case 'Reboot':
                $api->restartInstance($instanceId);
                $response = ['success' => true, 'message' => 'Instance is rebooting.'];
                break;

            case 'CreateSnapshot':
                $name = htmlspecialchars($_POST['snapshot_name'] ?? ('snap-' . date('YmdHis')));
                $desc = htmlspecialchars($_POST['snapshot_desc'] ?? '');
                $api->createSnapshot($instanceId, $name, $desc);
                $response = ['success' => true, 'message' => 'Snapshot creation initiated.'];
                break;

            case 'DeleteSnapshot':
                $snapshotId = $_POST['snapshot_id'] ?? '';
                if (!$snapshotId) throw new \Exception('Snapshot ID required.');
                $api->deleteSnapshot($instanceId, $snapshotId);
                $response = ['success' => true, 'message' => 'Snapshot layout row deleted.'];
                break;

            case 'RestoreSnapshot':
                $snapshotId = $_POST['snapshot_id'] ?? '';
                if (!$snapshotId) throw new \Exception('Snapshot ID required.');
                $api->restoreSnapshot($instanceId, $snapshotId);
                $response = ['success' => true, 'message' => 'Snapshot recovery processing initiated.'];
                break;

            case 'Rebuild':
                $imageId = $_POST['image_id'] ?? '';
                if (!$imageId) throw new \Exception('Image ID template selection required.');
                $api->reinstallInstance($instanceId, $imageId);

                localAPI('SendEmail', [
                    'messagename' => 'Contabo VPS Rebuild',
                    'id'          => $params['serviceid'],
                ]);

                $response = ['success' => true, 'message' => 'Rebuild processing script running successfully.'];
                break;
        }
    } catch (\Exception $e) {
        $response = ['success' => false, 'message' => $e->getMessage()];
    }

    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
        header('Content-Type: application/json');
        echo json_encode($response);
        exit;
    }

    $status = $response['success'] ? 'success' : 'error';
    header('Location: ' . $params['modulelink'] . '&' . $status . '=' . urlencode($response['message']));
    exit;
}

function contabo_TestConnection(array $params)
{
    try {
        $api = contabo_getApi($params);
        $api->getInstances(1, 1);
        return ['success' => true, 'message' => 'Connected successfully'];
    } catch (\Exception $e) {
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

// ============================================================================
// Client Area - Custom UI Action Form Router (Rebuild & Snapshots)
// ============================================================================

function contabo_ClientAreaCustomAction(array $params)
{
    $action = $_POST['custom_action'] ?? '';
    if (empty($action)) {
        return false;
    }

    try {
        $api        = contabo_getApi($params);
        $instanceId = contabo_getInstanceId($params);

        switch ($action) {
            case 'client_rebuild':
                if (!empty($_POST['image_id'])) {
                    // Triggers OS reinstall deployment via Contabo API
                    $api->reinstallInstance($instanceId, $_POST['image_id']);
                    localAPI('SendEmail', [
                        'messagename' => 'Contabo VPS Rebuild',
                        'id'          => $params['serviceid'],
                    ]);
                    return ['success' => true, 'message' => 'Server operating system rebuild has been successfully initiated.'];
                }
                break;

            case 'client_create_snapshot':
                $snapshotsEnabled = ($params['configoption10'] ?? 'yes') === 'yes';
                if ($snapshotsEnabled) {
                    $name = htmlspecialchars($_POST['snapshot_name'] ?? 'client-snap-' . date('YmdHis'));
                    $desc = htmlspecialchars($_POST['snapshot_desc'] ?? '');
                    $api->createSnapshot($instanceId, $name, $desc);
                    return ['success' => true, 'message' => 'Snapshot creation task queued.'];
                }
                break;

            case 'client_restore_snapshot':
                $snapshotsEnabled = ($params['configoption10'] ?? 'yes') === 'yes';
                $snapshotId       = $_POST['snapshot_id'] ?? '';
                if ($snapshotsEnabled && $snapshotId) {
                    $api->restoreSnapshot($instanceId, $snapshotId);
                    return ['success' => true, 'message' => 'Snapshot rollback restoration process initiated.'];
                }
                break;

            case 'client_delete_snapshot':
                $snapshotsEnabled = ($params['configoption10'] ?? 'yes') === 'yes';
                $snapshotId       = $_POST['snapshot_id'] ?? '';
                if ($snapshotsEnabled && $snapshotId) {
                    $api->deleteSnapshot($instanceId, $snapshotId);
                    return ['success' => true, 'message' => 'Snapshot removed permanently.'];
                }
                break;
        }
    } catch (\Exception $e) {
        logModuleCall('contabo', 'ClientAreaCustomAction::' . $action, $params, $e->getMessage(), 'error');
        return ['error' => $e->getMessage()];
    }

    return false;
}