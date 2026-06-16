{* Contabo VPS/VDS – Client Area Template *}
{* Compatible with WHMCS 8.x+ / Bootstrap 3 *}

<style>
  /* ── Design tokens ──────────────────────────────────────────────── */
  :root {
    --c-bg:          #0f1117;
    --c-surface:     #181b24;
    --c-surface-alt: #1e2230;
    --c-border:      #2a2f42;
    --c-accent:      #4f8ef7;
    --c-accent-dim:  rgba(79,142,247,.12);
    --c-success:     #34c97b;
    --c-warn:        #f5a623;
    --c-danger:      #e84b4b;
    --c-text:        #e2e6f0;
    --c-muted:       #6b7494;
    --c-code:        #c9d1f0;
    --radius:        8px;
    --radius-lg:     14px;
    --font-mono:     'JetBrains Mono', 'Fira Code', ui-monospace, monospace;
    --font-ui:       'Inter', system-ui, -apple-system, sans-serif;
  }

  /* ── Layout ─────────────────────────────────────────────────────── */
  .ctb-wrap {
    font-family: var(--font-ui);
    color: var(--c-text);
    background: var(--c-bg);
    border-radius: var(--radius-lg);
    overflow: hidden;
    border: 1px solid var(--c-border);
    max-width: 980px;
    margin: 0 auto;
  }

  /* ── Hero header ─────────────────────────────────────────────────── */
  .ctb-hero {
    background: linear-gradient(135deg, #131828 0%, #1a2340 100%);
    border-bottom: 1px solid var(--c-border);
    padding: 28px 32px 24px;
    display: flex;
    align-items: flex-start;
    gap: 20px;
  }
  .ctb-hero-icon {
    width: 52px;
    height: 52px;
    border-radius: 12px;
    background: var(--c-accent-dim);
    border: 1px solid rgba(79,142,247,.3);
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    color: var(--c-accent);
    font-size: 22px;
  }
  .ctb-hero-meta { flex: 1; }
  .ctb-hero-title {
    margin: 0 0 4px;
    font-size: 20px;
    font-weight: 700;
    letter-spacing: -.3px;
    color: #fff;
  }
  .ctb-hero-subtitle {
    margin: 0;
    font-size: 13px;
    color: var(--c-muted);
    font-family: var(--font-mono);
  }
  .ctb-hero-badges { display: flex; gap: 8px; align-items: center; margin-top: 10px; }

  /* ── Status pill ──────────────────────────────────────────────── */
  .ctb-status {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 3px 10px 3px 8px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
    letter-spacing: .3px;
    text-transform: uppercase;
  }
  .ctb-status::before {
    content: '';
    display: block;
    width: 6px;
    height: 6px;
    border-radius: 50%;
  }
  .ctb-status.active   { background: rgba(52,201,123,.15); color: var(--c-success); }
  .ctb-status.active::before { background: var(--c-success); box-shadow: 0 0 0 2px rgba(52,201,123,.3); animation: ctb-pulse 2s infinite; }
  .ctb-status.stopped  { background: rgba(245,166,35,.12);  color: var(--c-warn); }
  .ctb-status.stopped::before { background: var(--c-warn); }
  .ctb-status.pending  { background: rgba(79,142,247,.12);  color: var(--c-accent); }
  .ctb-status.pending::before { background: var(--c-accent); }
  .ctb-status.error,
  .ctb-status.suspended { background: rgba(232,75,75,.12); color: var(--c-danger); }
  .ctb-status.error::before,
  .ctb-status.suspended::before { background: var(--c-danger); }

  @keyframes ctb-pulse {
    0%,100% { box-shadow: 0 0 0 2px rgba(52,201,123,.3); }
    50%      { box-shadow: 0 0 0 4px rgba(52,201,123,.1); }
  }

  /* ── Alerts ───────────────────────────────────────────────────── */
  .ctb-alert {
    margin: 20px 24px;
    padding: 12px 16px;
    border-radius: var(--radius);
    font-size: 14px;
    display: flex;
    align-items: flex-start;
    gap: 10px;
  }
  .ctb-alert.success { background: rgba(52,201,123,.1); border: 1px solid rgba(52,201,123,.3); color: #7de8ac; }
  .ctb-alert.error   { background: rgba(232,75,75,.1);  border: 1px solid rgba(232,75,75,.3);  color: #f08080; }

  /* ── Tab nav ──────────────────────────────────────────────────── */
  .ctb-tabs {
    display: flex;
    border-bottom: 1px solid var(--c-border);
    padding: 0 24px;
    background: var(--c-surface);
    gap: 2px;
    overflow-x: auto;
  }
  .ctb-tab-btn {
    display: flex;
    align-items: center;
    gap: 7px;
    padding: 14px 16px 13px;
    font-size: 13px;
    font-weight: 500;
    color: var(--c-muted);
    background: none;
    border: none;
    border-bottom: 2px solid transparent;
    cursor: pointer;
    white-space: nowrap;
    transition: color .15s, border-color .15s;
    font-family: var(--font-ui);
  }
  .ctb-tab-btn:hover { color: var(--c-text); }
  .ctb-tab-btn.active { color: var(--c-accent); border-bottom-color: var(--c-accent); }
  .ctb-tab-btn .ctb-tab-icon { font-size: 15px; opacity: .8; }

  /* ── Tab panels ────────────────────────────────────────────────── */
  .ctb-tab-panel { display: none; padding: 28px 28px 32px; }
  .ctb-tab-panel.active { display: block; }

  /* ── Section title ─────────────────────────────────────────────── */
  .ctb-section-title {
    font-size: 11px;
    font-weight: 700;
    letter-spacing: 1px;
    text-transform: uppercase;
    color: var(--c-muted);
    margin: 0 0 14px;
  }

  /* ── Info grid ─────────────────────────────────────────────────── */
  .ctb-info-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    gap: 12px;
    margin-bottom: 24px;
  }
  .ctb-info-card {
    background: var(--c-surface);
    border: 1px solid var(--c-border);
    border-radius: var(--radius);
    padding: 14px 16px;
  }
  .ctb-info-label {
    font-size: 11px;
    font-weight: 600;
    letter-spacing: .6px;
    text-transform: uppercase;
    color: var(--c-muted);
    margin-bottom: 5px;
  }
  .ctb-info-value {
    font-size: 15px;
    font-weight: 600;
    color: var(--c-text);
  }
  .ctb-info-value.mono {
    font-family: var(--font-mono);
    font-size: 13px;
    font-weight: 500;
    color: var(--c-code);
  }

  /* ── Spec bar ──────────────────────────────────────────────────── */
  .ctb-specs {
    display: flex;
    gap: 12px;
    margin-bottom: 24px;
    flex-wrap: wrap;
  }
  .ctb-spec {
    flex: 1;
    min-width: 110px;
    background: var(--c-surface-alt);
    border: 1px solid var(--c-border);
    border-radius: var(--radius);
    padding: 16px;
    text-align: center;
  }
  .ctb-spec-val {
    font-family: var(--font-mono);
    font-size: 22px;
    font-weight: 700;
    color: var(--c-accent);
    line-height: 1;
    margin-bottom: 4px;
  }
  .ctb-spec-label {
    font-size: 11px;
    font-weight: 600;
    letter-spacing: .8px;
    text-transform: uppercase;
    color: var(--c-muted);
  }

  /* ── IP block ──────────────────────────────────────────────────── */
  .ctb-ip-row {
    background: var(--c-surface);
    border: 1px solid var(--c-border);
    border-radius: var(--radius);
    padding: 14px 16px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    margin-bottom: 8px;
  }
  .ctb-ip-label { font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: .6px; color: var(--c-muted); margin-bottom: 3px; }
  .ctb-ip-val   { font-family: var(--font-mono); font-size: 14px; color: var(--c-code); }
  .ctb-copy-btn {
    background: var(--c-accent-dim);
    border: 1px solid rgba(79,142,247,.25);
    color: var(--c-accent);
    border-radius: 6px;
    padding: 5px 11px;
    font-size: 12px;
    font-weight: 600;
    cursor: pointer;
    font-family: var(--font-ui);
    transition: background .15s;
  }
  .ctb-copy-btn:hover { background: rgba(79,142,247,.22); }
  .ctb-copy-btn.copied { color: var(--c-success); border-color: rgba(52,201,123,.3); background: rgba(52,201,123,.1); }

  /* ── Power controls ─────────────────────────────────────────────── */
  .ctb-power-row {
    display: flex;
    gap: 10px;
    margin-bottom: 28px;
    flex-wrap: wrap;
  }
  .ctb-btn {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    padding: 9px 18px;
    border-radius: var(--radius);
    font-size: 13px;
    font-weight: 600;
    font-family: var(--font-ui);
    border: 1px solid transparent;
    cursor: pointer;
    transition: opacity .15s, transform .1s;
    text-decoration: none;
  }
  .ctb-btn:active { transform: scale(.97); }
  .ctb-btn:disabled { opacity: .4; cursor: not-allowed; }
  .ctb-btn-primary { background: var(--c-accent); color: #fff; border-color: var(--c-accent); }
  .ctb-btn-success { background: rgba(52,201,123,.15); color: var(--c-success); border-color: rgba(52,201,123,.35); }
  .ctb-btn-warn    { background: rgba(245,166,35,.12);  color: var(--c-warn);    border-color: rgba(245,166,35,.3); }
  .ctb-btn-danger  { background: rgba(232,75,75,.1);    color: var(--c-danger);  border-color: rgba(232,75,75,.3); }
  .ctb-btn-ghost   { background: var(--c-surface); color: var(--c-text); border-color: var(--c-border); }

  /* ── Snapshot table ─────────────────────────────────────────────── */
  .ctb-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 13px;
  }
  .ctb-table thead th {
    font-size: 11px;
    font-weight: 700;
    letter-spacing: .7px;
    text-transform: uppercase;
    color: var(--c-muted);
    padding: 10px 14px;
    border-bottom: 1px solid var(--c-border);
    text-align: left;
  }
  .ctb-table tbody tr { border-bottom: 1px solid var(--c-border); transition: background .1s; }
  .ctb-table tbody tr:last-child { border-bottom: none; }
  .ctb-table tbody tr:hover { background: var(--c-surface-alt); }
  .ctb-table td {
    padding: 11px 14px;
    color: var(--c-text);
    vertical-align: middle;
  }
  .ctb-table td code {
    font-family: var(--font-mono);
    font-size: 12px;
    color: var(--c-code);
    background: var(--c-surface);
    padding: 2px 6px;
    border-radius: 4px;
    border: 1px solid var(--c-border);
  }
  .ctb-action-row { display: flex; gap: 6px; }
  .ctb-btn-xs { padding: 4px 10px; font-size: 11px; border-radius: 5px; }

  /* ── Snapshot form ──────────────────────────────────────────────── */
  .ctb-form-row  { margin-bottom: 16px; }
  .ctb-form-label { display: block; font-size: 12px; font-weight: 600; color: var(--c-muted); margin-bottom: 6px; letter-spacing: .4px; text-transform: uppercase; }
  .ctb-input, .ctb-textarea, .ctb-select {
    width: 100%;
    background: var(--c-surface);
    border: 1px solid var(--c-border);
    border-radius: var(--radius);
    color: var(--c-text);
    padding: 9px 12px;
    font-size: 13px;
    font-family: var(--font-ui);
    outline: none;
    transition: border-color .15s;
    box-sizing: border-box;
  }
  .ctb-input:focus, .ctb-textarea:focus, .ctb-select:focus { border-color: var(--c-accent); }
  .ctb-select option { background: #1e2230; }
  .ctb-textarea { resize: vertical; min-height: 72px; }

  /* ── History timeline ───────────────────────────────────────────── */
  .ctb-timeline { list-style: none; padding: 0; margin: 0; }
  .ctb-timeline-item {
    display: flex;
    gap: 14px;
    padding: 12px 0;
    border-bottom: 1px solid var(--c-border);
    align-items: flex-start;
  }
  .ctb-timeline-item:last-child { border-bottom: none; }
  .ctb-tl-dot {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background: var(--c-accent);
    margin-top: 5px;
    flex-shrink: 0;
  }
  .ctb-tl-dot.error   { background: var(--c-danger); }
  .ctb-tl-dot.success { background: var(--c-success); }
  .ctb-tl-dot.pending { background: var(--c-warn); }
  .ctb-tl-action { font-size: 13px; font-weight: 600; color: var(--c-text); }
  .ctb-tl-time   { font-size: 12px; color: var(--c-muted); font-family: var(--font-mono); margin-top: 2px; }
  .ctb-tl-badge  {
    display: inline-block;
    padding: 1px 7px;
    border-radius: 4px;
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: .4px;
    margin-left: 8px;
  }
  .ctb-tl-badge.success { background: rgba(52,201,123,.12); color: var(--c-success); }
  .ctb-tl-badge.error   { background: rgba(232,75,75,.1);   color: var(--c-danger); }
  .ctb-tl-badge.pending { background: rgba(245,166,35,.12);  color: var(--c-warn); }

  /* ── Empty state ────────────────────────────────────────────────── */
  .ctb-empty {
    text-align: center;
    padding: 40px 20px;
    color: var(--c-muted);
    font-size: 14px;
  }
  .ctb-empty-icon { font-size: 36px; margin-bottom: 10px; opacity: .5; }

  /* ── Divider ────────────────────────────────────────────────────── */
  .ctb-divider { border: none; border-top: 1px solid var(--c-border); margin: 24px 0; }

  /* ── Warning notice ─────────────────────────────────────────────── */
  .ctb-notice {
    background: rgba(245,166,35,.08);
    border: 1px solid rgba(245,166,35,.25);
    border-radius: var(--radius);
    padding: 12px 16px;
    font-size: 13px;
    color: #e0bb6a;
    margin-bottom: 20px;
    display: flex;
    gap: 10px;
    align-items: flex-start;
  }

  /* ── Spinner ────────────────────────────────────────────────────── */
  .ctb-spinner {
    display: none;
    width: 14px;
    height: 14px;
    border: 2px solid rgba(255,255,255,.3);
    border-top-color: currentColor;
    border-radius: 50%;
    animation: ctb-spin .6s linear infinite;
  }
  @keyframes ctb-spin { to { transform: rotate(360deg); } }
  .ctb-loading .ctb-spinner { display: inline-block; }
  .ctb-loading .ctb-btn-label { display: none; }

  /* ── Rebuild select panel ───────────────────────────────────────── */
  .ctb-image-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
    gap: 8px;
    margin-bottom: 20px;
  }
  .ctb-image-opt {
    position: relative;
    cursor: pointer;
  }
  .ctb-image-opt input[type="radio"] { position: absolute; opacity: 0; width: 0; height: 0; }
  .ctb-image-label {
    display: block;
    padding: 12px 14px;
    border-radius: var(--radius);
    border: 1px solid var(--c-border);
    background: var(--c-surface);
    cursor: pointer;
    transition: border-color .15s, background .15s;
    font-size: 13px;
    font-weight: 500;
    color: var(--c-text);
  }
  .ctb-image-opt input:checked + .ctb-image-label {
    border-color: var(--c-accent);
    background: var(--c-accent-dim);
    color: #a8c8ff;
  }
  .ctb-image-label:hover { border-color: rgba(79,142,247,.4); }
  .ctb-image-os { font-size: 11px; color: var(--c-muted); margin-top: 2px; }
</style>

{* ────────────────────────────────────────────────────────────────── *}
{*  Main wrapper                                                      *}
{* ────────────────────────────────────────────────────────────────── *}
<div class="ctb-wrap">

  {* ── Error state ────────────────────────────────────────────────── *}
  {if $error}
    <div class="ctb-hero">
      <div class="ctb-hero-icon">⚠</div>
      <div class="ctb-hero-meta">
        <p class="ctb-hero-title">Unable to load server</p>
        <p class="ctb-hero-subtitle">{$error|escape}</p>
      </div>
    </div>
  {else}

    {* ── Hero ─────────────────────────────────────────────────────── *}
    {assign var="inst" value=$instance}
    {assign var="instStatus" value=$inst.status|default:"unknown"|lower}
    <div class="ctb-hero">
      <div class="ctb-hero-icon">&#x1F5A5;</div>
      <div class="ctb-hero-meta">
        <h2 class="ctb-hero-title">{$inst.displayName|default:"Server"|escape}</h2>
        <p class="ctb-hero-subtitle">
          ID #{$inst.instanceId|default:"—"} &nbsp;·&nbsp;
          {$inst.regionName|default:""|escape}
          {if $inst.region} ({$inst.region|escape}){/if}
        </p>
        <div class="ctb-hero-badges">
          <span class="ctb-status {$instStatus}">{$instStatus|capitalize}</span>
          {if $inst.productName}
            <span style="font-size:12px;color:var(--c-muted);">{$inst.productName|escape}</span>
          {/if}
        </div>
      </div>
    </div>

    {* ── Flash messages ───────────────────────────────────────────── *}
    {if $smarty.get.success}
      <div class="ctb-alert success">✓ {$smarty.get.success|escape}</div>
    {/if}
    {if $smarty.get.error}
      <div class="ctb-alert error">✗ {$smarty.get.error|escape}</div>
    {/if}

    {* ── Tab navigation ───────────────────────────────────────────── *}
    <div class="ctb-tabs" role="tablist">
      <button class="ctb-tab-btn active" onclick="ctbTab(event,'overview')" role="tab">
        <span class="ctb-tab-icon">&#x2139;</span> Overview
      </button>
      <button class="ctb-tab-btn" onclick="ctbTab(event,'power')" role="tab">
        <span class="ctb-tab-icon">&#x23FB;</span> Power
      </button>
      {if $snapshotsEnabled}
      <button class="ctb-tab-btn" onclick="ctbTab(event,'snapshots')" role="tab">
        <span class="ctb-tab-icon">&#x1F4BE;</span> Snapshots
      </button>
      {/if}
      <button class="ctb-tab-btn" onclick="ctbTab(event,'rebuild')" role="tab">
        <span class="ctb-tab-icon">&#x1F527;</span> Rebuild
      </button>
      <button class="ctb-tab-btn" onclick="ctbTab(event,'history')" role="tab">
        <span class="ctb-tab-icon">&#x1F4CB;</span> History
      </button>
    </div>

    {* ══════════════════════════════════════════════════════════════ *}
    {*  TAB: Overview                                                *}
    {* ══════════════════════════════════════════════════════════════ *}
    <div id="ctb-overview" class="ctb-tab-panel active">

      {* — Specs bar — *}
      <div class="ctb-specs">
        <div class="ctb-spec">
          <div class="ctb-spec-val">{$inst.cpuCores|default:"—"}</div>
          <div class="ctb-spec-label">CPU Cores</div>
        </div>
        <div class="ctb-spec">
          {assign var="ramGb" value=$inst.ramMb/1024}
          <div class="ctb-spec-val">{$ramGb|string_format:"%.0f"}<span style="font-size:14px;color:var(--c-muted);">GB</span></div>
          <div class="ctb-spec-label">RAM</div>
        </div>
        <div class="ctb-spec">
          {assign var="diskGb" value=$inst.diskMb/1024}
          <div class="ctb-spec-val">{$diskGb|string_format:"%.0f"}<span style="font-size:14px;color:var(--c-muted);">GB</span></div>
          <div class="ctb-spec-label">Storage</div>
        </div>
        <div class="ctb-spec">
          <div class="ctb-spec-val" style="font-size:14px;text-transform:uppercase;">{$inst.osType|default:"—"}</div>
          <div class="ctb-spec-label">OS Type</div>
        </div>
      </div>

      {* — Network — *}
      <p class="ctb-section-title">Network</p>

      {assign var="ipv4" value=$inst.ipConfig.v4.ip|default:"N/A"}
      {assign var="ipv6" value=$inst.ipConfig.v6.ip|default:"N/A"}
      {assign var="gw"   value=$inst.ipConfig.v4.gateway|default:"N/A"}

      <div class="ctb-ip-row">
        <div>
          <div class="ctb-ip-label">IPv4 Address</div>
          <div class="ctb-ip-val" id="ctb-ipv4-val">{$ipv4}</div>
        </div>
        {if $ipv4 != "N/A"}
          <button class="ctb-copy-btn" onclick="ctbCopy('ctb-ipv4-val',this)">Copy</button>
        {/if}
      </div>

      {if $ipv6 != "N/A"}
      <div class="ctb-ip-row">
        <div>
          <div class="ctb-ip-label">IPv6 Address</div>
          <div class="ctb-ip-val" id="ctb-ipv6-val">{$ipv6}</div>
        </div>
        <button class="ctb-copy-btn" onclick="ctbCopy('ctb-ipv6-val',this)">Copy</button>
      </div>
      {/if}

      <div class="ctb-ip-row" style="margin-top:8px;">
        <div>
          <div class="ctb-ip-label">Default Gateway</div>
          <div class="ctb-ip-val">{$gw}</div>
        </div>
        {if $inst.macAddress}
        <div style="text-align:right;">
          <div class="ctb-ip-label">MAC Address</div>
          <div class="ctb-ip-val">{$inst.macAddress|escape}</div>
        </div>
        {/if}
      </div>

      <hr class="ctb-divider">

      {* — Details grid — *}
      <p class="ctb-section-title">Details</p>
      <div class="ctb-info-grid">
        {if $inst.dataCenter}
        <div class="ctb-info-card">
          <div class="ctb-info-label">Data Centre</div>
          <div class="ctb-info-value">{$inst.dataCenter|escape}</div>
        </div>
        {/if}
        {if $inst.productType}
        <div class="ctb-info-card">
          <div class="ctb-info-label">Product Type</div>
          <div class="ctb-info-value">{$inst.productType|upper}</div>
        </div>
        {/if}
        {if $inst.defaultUser}
        <div class="ctb-info-card">
          <div class="ctb-info-label">Default User</div>
          <div class="ctb-info-value mono">{$inst.defaultUser|escape}</div>
        </div>
        {/if}
        {if $inst.createdDate}
        <div class="ctb-info-card">
          <div class="ctb-info-label">Created</div>
          <div class="ctb-info-value mono" style="font-size:12px;">{$inst.createdDate|date_format:"%Y-%m-%d"}</div>
        </div>
        {/if}
      </div>

    </div>


    {* ══════════════════════════════════════════════════════════════ *}
    {*  TAB: Power                                                   *}
    {* ══════════════════════════════════════════════════════════════ *}
    <div id="ctb-power" class="ctb-tab-panel">

      <p class="ctb-section-title">Power Controls</p>

      <div class="ctb-power-row">
        <button class="ctb-btn ctb-btn-success" onclick="ctbAction('PowerOn',this)">
          <span class="ctb-spinner"></span>
          <span class="ctb-btn-label">&#x25B6; Power On</span>
        </button>
        <button class="ctb-btn ctb-btn-warn" onclick="ctbAction('PowerOff',this)">
          <span class="ctb-spinner"></span>
          <span class="ctb-btn-label">&#x23F9; Power Off</span>
        </button>
        <button class="ctb-btn ctb-btn-ghost" onclick="ctbAction('Reboot',this)">
          <span class="ctb-spinner"></span>
          <span class="ctb-btn-label">&#x21BA; Reboot</span>
        </button>
      </div>

      <div class="ctb-notice">
        <span>&#9432;</span>
        <span>Power Off performs an immediate shutdown. Use with caution — unsaved data may be lost. Reboot sends a graceful restart command to the server.</span>
      </div>

      {* — Live status chip — *}
      <p class="ctb-section-title" style="margin-top:8px;">Current Status</p>
      <div style="display:flex;align-items:center;gap:12px;">
        <span class="ctb-status {$instStatus}" id="ctb-live-status">{$instStatus|capitalize}</span>
        <span style="font-size:12px;color:var(--c-muted);" id="ctb-status-note">Refresh the page to update.</span>
      </div>

    </div>


    {* ══════════════════════════════════════════════════════════════ *}
    {*  TAB: Snapshots                                               *}
    {* ══════════════════════════════════════════════════════════════ *}
    {if $snapshotsEnabled}
    <div id="ctb-snapshots" class="ctb-tab-panel">

      <p class="ctb-section-title">Create Snapshot</p>

      <div id="ctb-snap-form">
        <div class="ctb-form-row">
          <label class="ctb-form-label" for="snap-name">Snapshot Name</label>
          <input class="ctb-input" type="text" id="snap-name"
                 value="snap-{$smarty.now|date_format:"%Y%m%d%H%M%S"}"
                 placeholder="snap-20240101120000">
        </div>
        <div class="ctb-form-row">
          <label class="ctb-form-label" for="snap-desc">Description (optional)</label>
          <textarea class="ctb-textarea" id="snap-desc" placeholder="Optional notes about this snapshot…"></textarea>
        </div>
        <button class="ctb-btn ctb-btn-primary" onclick="ctbCreateSnapshot(this)">
          <span class="ctb-spinner"></span>
          <span class="ctb-btn-label">&#x1F4BE; Save Snapshot</span>
        </button>
      </div>

      <hr class="ctb-divider">
      <p class="ctb-section-title">Saved Snapshots</p>

      {if $snapshots|count > 0}
        <table class="ctb-table">
          <thead>
            <tr>
              <th>Name</th>
              <th>Created</th>
              <th>Size</th>
              <th style="text-align:right;">Actions</th>
            </tr>
          </thead>
          <tbody>
            {foreach from=$snapshots item=snap}
            <tr>
              <td>
                {$snap.name|escape}<br>
                <code>{$snap.snapshotId|escape}</code>
              </td>
              <td style="color:var(--c-muted);font-size:12px;font-family:var(--font-mono);">
                {$snap.createdDate|date_format:"%Y-%m-%d %H:%M"}
              </td>
              <td style="font-family:var(--font-mono);font-size:12px;">
                {math equation="round(size/1073741824,2)" size=$snap.size} GB
              </td>
              <td>
                <div class="ctb-action-row" style="justify-content:flex-end;">
                  <button class="ctb-btn ctb-btn-ghost ctb-btn-xs"
                          onclick="ctbRestoreSnapshot('{$snap.snapshotId|escape}',this)">
                    <span class="ctb-spinner"></span>
                    <span class="ctb-btn-label">Restore</span>
                  </button>
                  <button class="ctb-btn ctb-btn-danger ctb-btn-xs"
                          onclick="ctbDeleteSnapshot('{$snap.snapshotId|escape}',this)">
                    <span class="ctb-spinner"></span>
                    <span class="ctb-btn-label">Delete</span>
                  </button>
                </div>
              </td>
            </tr>
            {/foreach}
          </tbody>
        </table>
      {else}
        <div class="ctb-empty">
          <div class="ctb-empty-icon">&#x1F4BE;</div>
          No snapshots yet. Create one above to save the current state.
        </div>
      {/if}

    </div>
    {/if}


    {* ══════════════════════════════════════════════════════════════ *}
    {*  TAB: Rebuild                                                 *}
    {* ══════════════════════════════════════════════════════════════ *}
    <div id="ctb-rebuild" class="ctb-tab-panel">

      <div class="ctb-notice">
        <span>&#9888;</span>
        <span><strong>This will erase all data.</strong> Rebuilding reinstalls the OS and wipes every file on the server. Make sure you have a snapshot or backup before proceeding.</span>
      </div>

      <p class="ctb-section-title">Choose Operating System</p>

      {if $images|count > 0}
        <input type="hidden" id="ctb-selected-image" value="">
        <div class="ctb-image-grid" id="ctb-image-grid">
          {foreach from=$images item=img}
          <label class="ctb-image-opt">
            <input type="radio" name="ctb_image" value="{$img.imageId|escape}"
                   onchange="document.getElementById('ctb-selected-image').value=this.value">
            <span class="ctb-image-label">
              {$img.name|escape}
              <div class="ctb-image-os">{$img.operatingSystem|escape}</div>
            </span>
          </label>
          {/foreach}
        </div>

        <button class="ctb-btn ctb-btn-danger" onclick="ctbRebuild(this)" id="ctb-rebuild-btn">
          <span class="ctb-spinner"></span>
          <span class="ctb-btn-label">&#x1F527; Rebuild Server</span>
        </button>
      {else}
        <div class="ctb-empty">
          <div class="ctb-empty-icon">&#x1F4BF;</div>
          No OS images available. Please contact support.
        </div>
      {/if}

    </div>


    {* ══════════════════════════════════════════════════════════════ *}
    {*  TAB: History                                                 *}
    {* ══════════════════════════════════════════════════════════════ *}
    <div id="ctb-history" class="ctb-tab-panel">

      <p class="ctb-section-title">Recent Activity</p>

      {if $audits|count > 0}
        <ul class="ctb-timeline">
          {foreach from=$audits item=entry}
          {assign var="aStatus" value=$entry.status|default:"pending"|lower}
          <li class="ctb-timeline-item">
            <span class="ctb-tl-dot {$aStatus}"></span>
            <div style="flex:1;">
              <div class="ctb-tl-action">
                {$entry.action|escape}
                <span class="ctb-tl-badge {$aStatus}">{$aStatus|capitalize}</span>
              </div>
              <div class="ctb-tl-time">
                {$entry.actionTime|date_format:"%Y-%m-%d %H:%M:%S"}
              </div>
            </div>
          </li>
          {/foreach}
        </ul>
      {else}
        <div class="ctb-empty">
          <div class="ctb-empty-icon">&#x1F4CB;</div>
          No activity recorded yet.
        </div>
      {/if}

    </div>

  {/if}{* /if error *}
</div>{* /ctb-wrap *}


{* ── JavaScript ──────────────────────────────────────────────────── *}
<script>
(function () {
  'use strict';

  var moduleLink = '{$modulelink|escape:"javascript"}';

  /* ── Tab switcher ─────────────────────────────────────────────── */
  window.ctbTab = function (e, id) {
    document.querySelectorAll('.ctb-tab-btn').forEach(function (b) { b.classList.remove('active'); });
    document.querySelectorAll('.ctb-tab-panel').forEach(function (p) { p.classList.remove('active'); });
    e.currentTarget.classList.add('active');
    var panel = document.getElementById('ctb-' + id);
    if (panel) panel.classList.add('active');
  };

  /* ── Copy to clipboard ────────────────────────────────────────── */
  window.ctbCopy = function (elId, btn) {
    var text = document.getElementById(elId).textContent.trim();
    if (!navigator.clipboard) {
      var ta = document.createElement('textarea');
      ta.value = text;
      document.body.appendChild(ta);
      ta.select();
      document.execCommand('copy');
      document.body.removeChild(ta);
    } else {
      navigator.clipboard.writeText(text);
    }
    btn.textContent = 'Copied!';
    btn.classList.add('copied');
    setTimeout(function () { btn.textContent = 'Copy'; btn.classList.remove('copied'); }, 2000);
  };

  /* ── Generic AJAX helper ─────────────────────────────────────── */
  function ctbPost(action, data, btn, onSuccess) {
    if (btn) btn.classList.add('ctb-loading');

    var body = Object.assign({ action: action }, data);
    var params = Object.keys(body).map(function (k) {
      return encodeURIComponent(k) + '=' + encodeURIComponent(body[k]);
    }).join('&');

    fetch(moduleLink, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/x-www-form-urlencoded',
        'X-Requested-With': 'XMLHttpRequest'
      },
      body: params
    })
    .then(function (r) { return r.json(); })
    .then(function (resp) {
      if (btn) btn.classList.remove('ctb-loading');
      if (resp.success) {
        ctbToast(resp.message || 'Done.', 'success');
        if (typeof onSuccess === 'function') onSuccess(resp);
      } else {
        ctbToast(resp.message || 'Something went wrong.', 'error');
      }
    })
    .catch(function (err) {
      if (btn) btn.classList.remove('ctb-loading');
      ctbToast('Request failed: ' + err.message, 'error');
    });
  }

  /* ── Toast notifications ─────────────────────────────────────── */
  var toastEl = null;
  var toastTimer = null;
  function ctbToast(msg, type) {
    if (!toastEl) {
      toastEl = document.createElement('div');
      toastEl.style.cssText = [
        'position:fixed;bottom:24px;right:24px;z-index:9999',
        'padding:12px 18px;border-radius:10px;font-size:13px;font-weight:600',
        'font-family:var(--font-ui,-apple-system,sans-serif)',
        'box-shadow:0 4px 20px rgba(0,0,0,.5)',
        'transition:opacity .3s,transform .3s',
        'max-width:320px;pointer-events:none'
      ].join(';');
      document.body.appendChild(toastEl);
    }
    toastEl.textContent = msg;
    if (type === 'success') {
      toastEl.style.background = 'rgba(52,201,123,.15)';
      toastEl.style.border     = '1px solid rgba(52,201,123,.4)';
      toastEl.style.color      = '#7de8ac';
    } else {
      toastEl.style.background = 'rgba(232,75,75,.15)';
      toastEl.style.border     = '1px solid rgba(232,75,75,.4)';
      toastEl.style.color      = '#f08080';
    }
    toastEl.style.opacity   = '1';
    toastEl.style.transform = 'translateY(0)';
    clearTimeout(toastTimer);
    toastTimer = setTimeout(function () {
      toastEl.style.opacity   = '0';
      toastEl.style.transform = 'translateY(8px)';
    }, 3500);
  }

  /* ── Power actions ────────────────────────────────────────────── */
  window.ctbAction = function (action, btn) {
    var labels = { PowerOn: 'power on', PowerOff: 'power off', Reboot: 'reboot' };
    if (!confirm('Are you sure you want to ' + (labels[action] || action) + ' this server?')) return;
    ctbPost(action, {}, btn);
  };

  /* ── Snapshot actions ─────────────────────────────────────────── */
  window.ctbCreateSnapshot = function (btn) {
    var name = document.getElementById('snap-name').value.trim();
    var desc = document.getElementById('snap-desc').value.trim();
    if (!name) { ctbToast('Please enter a snapshot name.', 'error'); return; }
    ctbPost('CreateSnapshot', { snapshot_name: name, snapshot_desc: desc }, btn, function () {
      setTimeout(function () { window.location.reload(); }, 1200);
    });
  };

  window.ctbRestoreSnapshot = function (id, btn) {
    if (!confirm('Restore this snapshot? The server will be reverted to the saved state and any changes since will be lost.')) return;
    ctbPost('RestoreSnapshot', { snapshot_id: id }, btn);
  };

  window.ctbDeleteSnapshot = function (id, btn) {
    if (!confirm('Permanently delete this snapshot? This cannot be undone.')) return;
    ctbPost('DeleteSnapshot', { snapshot_id: id }, btn, function () {
      var row = btn.closest('tr');
      if (row) row.remove();
    });
  };

  /* ── Rebuild ─────────────────────────────────────────────────── */
  window.ctbRebuild = function (btn) {
    var imageId = document.getElementById('ctb-selected-image').value;
    if (!imageId) { ctbToast('Please select an OS image first.', 'error'); return; }
    if (!confirm('Rebuild the server with the selected OS? ALL DATA WILL BE PERMANENTLY ERASED.')) return;
    ctbPost('Rebuild', { image_id: imageId }, btn, function () {
      ctbToast('Rebuild started. You\'ll receive an email when it\'s complete.', 'success');
    });
  };

})();
</script>
