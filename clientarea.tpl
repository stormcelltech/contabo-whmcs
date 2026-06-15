<div class="card panel panel-default">
    <div class="card-body panel-body">
        <h3>Server Status & Details</h3>
        <hr>
        {if $success}
            <div class="alert alert-success">{$success|escape}</div>
        {/if}
        {if $error}
            <div class="alert alert-danger">{$error|escape}</div>
        {/if}

        <div class="row">
            <div class="col-sm-6">
                <p><strong>Instance ID:</strong> {$vm.instanceId|escape}</p>
                <p><strong>Name:</strong> {$vm.displayName|escape}</p>
                <p><strong>Status:</strong> <span class="label label-success">{$vm.status|escape}</span></p>
            </div>
            <div class="col-sm-6">
                <p><strong>Primary IPv4:</strong> {$vm.ipAddress|escape}</p>
                <p><strong>MAC Address:</strong> {$vm.macAddress|escape}</p>
                <p><strong>Region/Data Center:</strong> {$vm.region|escape}</p>
            </div>
        </div>

        <hr>
        <h4>Power Controls</h4>
        <form method="post" class="form-inline">
            <input type="hidden" name="token" value="{$csrfToken}" />
            <button type="submit" name="mod_action" value="start" class="btn btn-success"><i class="fa fa-play"></i> Power On</button>
            <button type="submit" name="mod_action" value="stop" class="btn btn-warning"><i class="fa fa-stop"></i> Power Off</button>
            <button type="submit" name="mod_action" value="shutdown" class="btn btn-danger"><i class="fa fa-power-off"></i> ACPI Shutdown</button>
            <button type="submit" name="mod_action" value="restart" class="btn btn-info"><i class="fa fa-refresh"></i> Reboot</button>
        </form>

        <hr>
        <h4>Rebuild System OS</h4>
        <form method="post" class="form-inline">
            <input type="hidden" name="token" value="{$csrfToken}" />
            <select name="os_image" class="form-control" required>
                <option value="">-- Choose Target OS Distribution --</option>
                {foreach from=$os_images item=img}
                    <option value="{$img.imageId|escape}">{$img.name|escape} ({$img.osType|escape})</option>
                {/foreach}
            </select>
            <button type="submit" name="mod_action" value="rebuild" class="btn btn-danger" onclick="return confirm('WARNING: Rebuilding wipes data permanently. Proceed?');">Execute OS Rebuild</button>
        </form>

        {if isset($vm.productId) && $vm.productId|strstr:"vps"}
            <hr>
            <h4>Snapshots (VPS Exclusive)</h4>
            <form method="post" class="form-inline margin-bottom">
                <input type="hidden" name="token" value="{$csrfToken}" />
                <input type="text" name="snap_name" class="form-control" placeholder="Snapshot Tag Name" required>
                <button type="submit" name="mod_action" value="create_snapshot" class="btn btn-primary">Take Snapshot</button>
            </form>
            <br>
            <table class="table table-striped">
                <thead>
                    <tr><th>Name</th><th>Created At</th><th>Actions</th></tr>
                </thead>
                <tbody>
                    {foreach from=$snapshots item=snap}
                    <tr>
                        <td>{$snap.name|escape}</td>
                        <td>{$snap.createdDate|escape}</td>
                        <td>
                            <form method="post" style="display:inline;">
                                <input type="hidden" name="token" value="{$csrfToken}" />
                                <input type="hidden" name="snap_id" value="{$snap.snapshotId|escape}">
                                <button type="submit" name="mod_action" value="rollback_snapshot" class="btn btn-xs btn-warning" onclick="return confirm('Restore this snapshot state? All data since creation will be lost.');">Restore</button>
                                <button type="submit" name="mod_action" value="delete_snapshot" class="btn btn-xs btn-danger" onclick="return confirm('Permanently delete snapshot?');">Delete</button>
                            </form>
                        </td>
                    </tr>
                    {/foreach}
                </tbody>
            </table>
        {/if}

        <hr>
        <h4>Activity Task Log History</h4>
        <table class="table table-condensed table-hover">
            <thead>
                <tr><th>Action Task</th><th>Status</th><th>Timestamp</th></tr>
            </thead>
            <tbody>
                {foreach from=$history item=task}
                <tr>
                    <td>{$task.action|escape}</td>
                    <td>{$task.status|escape}</td>
                    <td>{$task.timestamp|escape}</td>
                </tr>
                {/foreach}
            </tbody>
        </table>
    </div>
</div>