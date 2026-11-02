{* Contabo WHMCS client area. All state changes use WHMCS custom-action POSTs with its CSRF token. *}
<div class="panel panel-default">
    <div class="panel-heading"><strong>Contabo Cloud Server</strong></div>
    <div class="panel-body">
        {if $apiError}
            <div class="alert alert-danger">{$apiError|escape}</div>
        {else}
            <dl class="dl-horizontal">
                <dt>Instance ID</dt><dd>{$instance.instanceId|escape}</dd>
                <dt>Name</dt><dd>{$instance.displayName|escape}</dd>
                <dt>Status</dt><dd>{$instance.status|escape}</dd>
                <dt>Region</dt><dd>{$instance.region|escape}</dd>
                <dt>IPv4</dt><dd>{$instance.ipConfig.v4.ip|default:'Not assigned'|escape}</dd>
            </dl>

            {if $clientPowerEnabled}
                <hr>
                <h4>Power controls</h4>
                <p class="text-muted">Each action is authenticated, CSRF-protected, and recorded in the WHMCS module log.</p>
                <div class="btn-group" role="group" aria-label="Server power controls">
                    <form method="post" action="{$actionBase|escape}&amp;modop=custom&amp;a=ClientPowerOn" style="display:inline">
                        <input type="hidden" name="token" value="{$token|escape}">
                        <button type="submit" class="btn btn-success">Power on</button>
                    </form>
                    <form method="post" action="{$actionBase|escape}&amp;modop=custom&amp;a=ClientPowerOff" style="display:inline" onsubmit="return confirm('Power off this server? Unsaved data may be lost.');">
                        <input type="hidden" name="token" value="{$token|escape}">
                        <button type="submit" class="btn btn-warning">Power off</button>
                    </form>
                    <form method="post" action="{$actionBase|escape}&amp;modop=custom&amp;a=ClientReboot" style="display:inline" onsubmit="return confirm('Restart this server now?');">
                        <input type="hidden" name="token" value="{$token|escape}">
                        <button type="submit" class="btn btn-default">Restart</button>
                    </form>
                </div>
            {/if}
        {/if}
    </div>
</div>
