(function () {
  'use strict';

  const cfg = window.EFBHumanShieldAdmin || {};
  const app = document.getElementById('efb-human-shield-app');
  if (!app) return;

  function renderFatal(message) {
    app.innerHTML = '';
    const box = document.createElement('div');
    box.className = 'efb-hs-fatal';
    box.setAttribute('role', 'alert');
    box.style.cssText = 'margin:24px;padding:16px 20px;border:1px solid #d63638;border-radius:8px;background:#fcf0f1;color:#8a1f21;font-size:14px;line-height:1.7;';
    box.textContent = message;
    app.appendChild(box);
  }

  if (!cfg.ajaxUrl || !cfg.nonce) {
    renderFatal(
      (cfg.text && cfg.text.configMissing) ||
      'Human Shield admin data could not be loaded. Another plugin may be blocking the script settings (wp_localize_script), or the page was cached. Reload the page; if it persists, disable admin script optimization for this page.'
    );
    return;
  }

  const text = cfg.text || {};
  let settings = Object.assign({}, cfg.settings || {});
  let logs = Array.isArray(cfg.logs) ? cfg.logs : [];
  let stats = cfg.stats || {};
  let hourly = Array.isArray(cfg.hourly) ? cfg.hourly : [];
  let persistedEnabled = Number(settings.enabled) === 1;

  /* Status palette, validated (lightness band, chroma floor, CVD >= 12,
   * contrast >= 3:1 on the panel surface). Identity is never color-alone:
   * legend labels + per-column tooltips + 2px surface gaps. */
  const CHART_COLORS = { allow: '#2e7d32', block: '#c62828', quarantine: '#b26a00', monitor: '#3f6fd1' };
  const CHART_ORDER = ['allow', 'block', 'quarantine', 'monitor'];
  const isRtl = Number(cfg.rtl) === 1;
  const sideClass = isRtl ? 'ms' : 'me';

  const fields = [
    ['enabled', 'checkbox'],
    ['protect_response_lookup', 'checkbox'],
    ['fail_closed_on_missing_requirements', 'checkbox'],
    ['trusted_proxy_headers', 'checkbox'],
    ['trusted_proxy_ips', 'textarea'],
    ['store_raw_metrics', 'checkbox'],
    ['client_attest_timeout_ms', 'number'],
    ['mode', 'select'],
    ['min_score_submit', 'number'],
    ['min_score_paid_notification', 'number'],
    ['block_score_below', 'number'],
    ['quarantine_score_below', 'number'],
    ['token_ttl_seconds', 'number'],
    ['challenge_ttl_seconds', 'number'],
    ['min_fill_time_seconds', 'number'],
    ['submit_ip_per_minute', 'number'],
    ['submit_ip_per_hour', 'number'],
    ['form_global_per_minute', 'number'],
    ['response_get_ip_per_minute', 'number'],
    ['response_add_ip_per_minute', 'number'],
    ['file_upload_ip_per_minute', 'number'],
    ['payment_ip_per_minute', 'number'],
    ['api_ip_per_minute', 'number'],
    ['sms_daily_stop_loss', 'number'],
    ['telegram_daily_stop_loss', 'number'],
    ['webhook_daily_stop_loss', 'number'],
    ['recipient_daily_cap', 'number'],
    ['ip_blocklist', 'textarea'],
    ['ip_allowlist', 'textarea'],
    ['log_retention_days', 'number']
  ];

  function esc(value) {
    return String(value == null ? '' : value)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
  }

  function statValue(key) {
    return Number(stats && stats[key] ? stats[key] : 0);
  }

  function render() {
    app.innerHTML = `
      <section class="efb-hs-header m-2 m-md-0 p-3 p-md-4">
        <div class="efb-hs-header-main">
          <div class="efb-hs-logo"><i class="efb bi-shield-lock"></i></div>
          <div>
            <h1>${esc(text.title || 'Human Shield')}</h1>
            <p class="d-none d-md-block">${esc(text.subtitle || 'Behavior-based anti-spam and cost protection')}</p>
            <div class="efb-hs-badges d-none d-md-flex">
              <span><i class="efb bi-activity ${sideClass}-1"></i>Behavior scoring</span>
              <span><i class="efb bi-speedometer2 ${sideClass}-1"></i>Rate limits</span>
              <span><i class="efb bi-wallet2 ${sideClass}-1"></i>Cost guard</span>
            </div>
          </div>
        </div>
        <div class="efb-hs-status" aria-live="polite">
          <div id="efb-hs-status-indicator">${statusPill()}</div>
          <small class="efb-hs-status-version">v${esc(cfg.version || '0.1.0')}</small>
        </div>
      </section>

      ${requirementsBanner()}

      <nav class="efb-hs-tabs" aria-label="Security & Spam Protection tabs">
        ${tabButton('overview', text.overview || 'Overview', 'bi-grid', true)}
        ${tabButton('protection', text.protection || 'Protection', 'bi-sliders', false)}
        ${tabButton('paid', text.paidLimits || 'Paid Limits', 'bi-wallet2', false)}
        ${tabButton('logs', text.logs || 'Logs', 'bi-list-check', false)}
        ${tabButton('system', text.system || 'System', 'bi-cpu', false)}
      </nav>

      <div class="efb-hs-alert" id="efb-hs-alert" hidden></div>
      <main class="efb-hs-content" id="efb-hs-content">${overviewTab()}</main>
    `;
  }

  /* Always-visible banner when the server cannot run the protection: names
   * the exact disabled PHP functions and what the admin should do about it. */
  function requirementsBanner() {
    const req = cfg.requirements || {};
    if (req.ok) return '';
    let message = '';
    if (req.missing_required) {
      const missing = Array.isArray(req.missing_functions) && req.missing_functions.length
        ? req.missing_functions.join(', ')
        : '';
      message = (text.missingRequired ||
        'Protection is paused: required PHP functions are disabled on this server: %s. Ask your host to remove them from the disable_functions line in php.ini. Your forms keep working without protection until then.'
      ).replace('%s', missing);
    } else if (!req.db_tables_ready) {
      message = text.missingTables ||
        'Protection is paused: the add-on database tables could not be created. Check that the database user can CREATE tables, then reload this page.';
    } else if (!req.rest_available) {
      message = 'Protection is paused: the WordPress REST API is not available on this site.';
    }
    if (!message) return '';
    return `<div class="efb-hs-req-banner" role="alert" style="display:flex;gap:10px;align-items:flex-start;margin:0 0 16px;padding:14px 18px;border:1px solid #dba617;border-radius:10px;background:#fcf9e8;color:#6d5a12;font-size:13.5px;line-height:1.7;">
      <i class="efb bi-exclamation-triangle" style="font-size:18px;flex:none;margin-top:2px;"></i>
      <span>${esc(message)}</span>
    </div>`;
  }

  function statusState() {
    const enabled = Number(settings.enabled) === 1;
    const reqOk = cfg.requirements && cfg.requirements.ok;
    const changed = enabled !== persistedEnabled;
    if (changed) {
      return enabled
        ? { cls: 'pending', icon: 'shield-plus', label: 'Ready to enable', hint: 'Save settings to start protection.' }
        : { cls: 'pending', icon: 'shield-slash', label: 'Ready to disable', hint: 'Save settings to pause protection.' };
    }
    if (!enabled) return { cls: 'off', icon: 'shield-x', label: 'Disabled', hint: 'Protection is paused.' };
    if (!reqOk) return { cls: 'warn', icon: 'shield-exclamation', label: 'Needs attention', hint: 'Fix system requirements before protection can run.' };
    return { cls: 'ok', icon: 'shield-check', label: 'Active', hint: 'Behavior checks and rate limits are running.' };
  }

  function statusPill() {
    const state = statusState();
    return `<span class="efb-hs-pill ${state.cls}">
      <i class="efb bi-${state.icon}"></i>
      <span class="efb-hs-pill-copy"><strong>${esc(state.label)}</strong><small>${esc(state.hint)}</small></span>
    </span>`;
  }

  function updateStatusIndicator() {
    const indicator = document.getElementById('efb-hs-status-indicator');
    if (indicator) indicator.innerHTML = statusPill();
  }

  function tabButton(id, label, icon, active) {
    return `<button type="button" class="efb-hs-tab ${active ? 'active' : ''}" data-tab="${esc(id)}">
      <i class="efb ${esc(icon)}"></i><span>${esc(label)}</span>
    </button>`;
  }

  function overviewTab() {
    return `
      <section class="efb-hs-grid efb-hs-grid-4">
        ${statCard('Allowed', statValue('allow'), 'bi-check2-circle', 'ok')}
        ${statCard('Blocked', statValue('block'), 'bi-ban', 'danger')}
        ${statCard('Quarantined', statValue('quarantine'), 'bi-inbox', 'warn')}
        ${statCard('Cost Suppressed', statValue('suppressed'), 'bi-wallet2', 'accent')}
      </section>
      <section class="efb-hs-panel">
        <div class="efb-hs-panel-head">
          <div><h2>Protection summary</h2><p>Current operating mode and the most important thresholds.</p></div>
          <button type="button" class="efb-hs-primary" data-action="save"><i class="efb bi-save ${sideClass}-1"></i>${esc(text.save || 'Save Settings')}</button>
        </div>
        <div class="efb-hs-summary">
          ${summaryItem('Mode', esc(settings.mode || 'soft_block'))}
          ${summaryItem('Submit score', esc(settings.min_score_submit))}
          ${summaryItem('Paid notification score', esc(settings.min_score_paid_notification))}
          ${summaryItem('Token TTL', esc(settings.token_ttl_seconds) + 's')}
          ${summaryItem('IP submit/min', esc(settings.submit_ip_per_minute))}
          ${summaryItem('Response add/min', esc(settings.response_add_ip_per_minute))}
        </div>
      </section>
      ${logsPanel(8)}
    `;
  }

  function protectionTab() {
    return `
      <section class="efb-hs-panel">
        <div class="efb-hs-panel-head">
          <div><h2>Request protection</h2><p>Controls that run before EFB REST callbacks.</p></div>
          <button type="button" class="efb-hs-primary" data-action="save"><i class="efb bi-save ${sideClass}-1"></i>${esc(text.save || 'Save Settings')}</button>
        </div>
        <div class="efb-hs-form-grid">
          ${toggleField('enabled', 'Enable Human Shield', 'Activate behavior tokens and rate limits.')}
          ${selectField('mode', 'Mode', [
            ['monitor', text.monitor || 'Monitor only'],
            ['soft_block', text.softBlock || 'Soft block'],
            ['strict', text.strict || 'Strict']
          ], 'Monitor logs only; soft block is recommended for production rollout.')}
          ${numberField('min_score_submit', 'Minimum submit score', 'Requests below this score are suspicious.')}
          ${numberField('block_score_below', 'Block score below', 'Hard block threshold.')}
          ${numberField('quarantine_score_below', 'Quarantine score below', 'Soft rejection threshold.')}
          ${numberField('min_fill_time_seconds', 'Minimum fill time', 'Base minimum seconds before submit.')}
          ${numberField('token_ttl_seconds', 'Token TTL seconds', 'Short-lived single-use human token.')}
          ${numberField('challenge_ttl_seconds', 'Challenge TTL seconds', 'How long a browser challenge can stay open.')}
          ${numberField('api_ip_per_minute', 'All API requests per IP/min', 'Global application-layer backstop.')}
          ${numberField('submit_ip_per_minute', 'Submits per IP/min', 'Per-IP submit limit.')}
          ${numberField('submit_ip_per_hour', 'Submits per IP/hour', 'Longer per-IP submit limit.')}
          ${numberField('form_global_per_minute', 'Form global/min', 'Total submit pressure per form.')}
          ${numberField('response_get_ip_per_minute', 'Tracking lookups per IP/min', 'Protects confirmation-code lookup.')}
          ${numberField('response_add_ip_per_minute', 'Responses per IP/min', 'Protects public reply box.')}
          ${numberField('file_upload_ip_per_minute', 'Uploads per IP/min', 'Protects upload endpoint.')}
          ${numberField('payment_ip_per_minute', 'Payment starts per IP/min', 'Protects payment REST routes.')}
          ${toggleField('protect_response_lookup', 'Protect response lookup', 'Rate-limit tracking-code searches by request count (no human-token check).')}
        </div>
      </section>
      <section class="efb-hs-panel">
        <div class="efb-hs-panel-head">
          <div><h2>Manual access lists</h2><p>One entry per line: an exact IP or a wildcard prefix such as 203.0.113.* — allowlist bypasses all checks, blocklist blocks in every mode.</p></div>
        </div>
        <div class="efb-hs-form-grid">
          ${textareaField('ip_allowlist', 'IP allowlist', 'Trusted IPs that skip the shield completely (your office, monitoring bots).')}
          ${textareaField('ip_blocklist', 'IP blocklist', 'IPs that are always blocked on protected form routes.')}
        </div>
      </section>
    `;
  }

  function paidTab() {
    return `
      <section class="efb-hs-panel">
        <div class="efb-hs-panel-head">
          <div><h2>Paid service stop-loss</h2><p>Limits used by the future side-effect filter before SMS, Telegram, email and webhooks.</p></div>
          <button type="button" class="efb-hs-primary" data-action="save"><i class="efb bi-save ${sideClass}-1"></i>${esc(text.save || 'Save Settings')}</button>
        </div>
        <div class="efb-hs-form-grid">
          ${numberField('min_score_paid_notification', 'Minimum paid notification score', 'Suppress costly notifications below this score.')}
          ${numberField('sms_daily_stop_loss', 'SMS daily stop-loss', 'Global SMS budget per day, across all recipients.')}
          ${numberField('telegram_daily_stop_loss', 'Telegram daily stop-loss', 'Global Telegram budget per day, across all recipients.')}
          ${numberField('webhook_daily_stop_loss', 'Webhook/Email daily stop-loss', 'Global webhook/email/sheet budget per day.')}
          ${numberField('recipient_daily_cap', 'Per-recipient daily cap', 'Maximum notifications per single recipient per day (0 disables).')}
        </div>
        <div class="efb-hs-note">
          <i class="efb bi-info-circle"></i>
          These limits become active for paid services after the core integration filter is added.
        </div>
      </section>
    `;
  }

  function logsTab() {
    return `
      <section class="efb-hs-panel">
        <div class="efb-hs-panel-head">
          <div><h2>Decisions — last 24 hours</h2><p>Stacked per hour: allowed, blocked, quarantined and monitored requests.</p></div>
        </div>
        ${decisionsChart()}
      </section>
      <section class="efb-hs-panel">
        <div class="efb-hs-panel-head">
          <div><h2>Security logs</h2><p>Recent decisions without storing raw IP addresses.</p></div>
          <div class="efb-hs-actions">
            <button type="button" class="efb-hs-secondary" data-action="export-logs"><i class="efb bi-filetype-csv ${sideClass}-1"></i>Export CSV</button>
            <button type="button" class="efb-hs-secondary" data-action="refresh-logs"><i class="efb bi-arrow-clockwise ${sideClass}-1"></i>Refresh</button>
            <button type="button" class="efb-hs-danger" data-action="clear-logs"><i class="efb bi-trash3 ${sideClass}-1"></i>${esc(text.clearLogs || 'Clear Logs')}</button>
          </div>
        </div>
        ${logsTable(50)}
      </section>
    `;
  }

  /* Stacked columns, plain divs. Thin marks with 2px surface gaps between
   * segments and columns; the topmost segment gets the rounded data-end;
   * grid/axes stay recessive; per-column native tooltip carries the numbers. */
  function decisionsChart() {
    const rows = Array.isArray(hourly) ? hourly : [];
    let peak = 0;
    let total = 0;
    rows.forEach((row) => {
      const sum = CHART_ORDER.reduce((acc, key) => acc + Number(row[key] || 0), 0);
      peak = Math.max(peak, sum);
      total += sum;
    });

    if (!rows.length || !total) {
      return `<div class="efb-hs-empty"><i class="efb bi-bar-chart"></i><span>No decisions recorded in the last 24 hours.</span></div>`;
    }

    const plotHeight = 120;
    const columns = rows.map((row, index) => {
      const parts = CHART_ORDER.map((key) => ({ key, value: Number(row[key] || 0) })).filter((part) => part.value > 0);
      const tooltip = `${row.hour} — ` + CHART_ORDER.map((key) => `${key}: ${Number(row[key] || 0)}`).join(', ');
      // Render top-to-bottom (monitor..allow reversed) so allow sits on the baseline.
      const segments = parts.slice().reverse().map((part, segIndex) => {
        const h = Math.max(2, Math.round((part.value / peak) * plotHeight));
        const isTop = segIndex === 0;
        return `<div style="height:${h}px;background:${CHART_COLORS[part.key]};${isTop ? 'border-radius:4px 4px 0 0;' : ''}margin-top:2px;"></div>`;
      }).join('');
      const showLabel = index % 6 === 0 || index === rows.length - 1;
      return `<div style="flex:1;display:flex;flex-direction:column;justify-content:flex-end;min-width:4px;" title="${esc(tooltip)}">
        <div style="display:flex;flex-direction:column;justify-content:flex-end;height:${plotHeight}px;">${segments}</div>
        <div style="height:16px;font-size:10px;line-height:16px;text-align:center;color:#8a8a99;overflow:visible;white-space:nowrap;">${showLabel ? esc(row.hour) : ''}</div>
      </div>`;
    }).join('');

    const legend = CHART_ORDER.map((key) =>
      `<span style="display:inline-flex;align-items:center;gap:6px;font-size:12px;color:#50505e;">
        <span style="width:10px;height:10px;border-radius:3px;background:${CHART_COLORS[key]};flex:none;"></span>${esc(key)}
      </span>`
    ).join('');

    return `<div class="efb-hs-chart">
      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;flex-wrap:wrap;gap:8px;">
        <span style="font-size:11px;color:#8a8a99;">peak ${esc(peak)}/h</span>
        <div style="display:flex;gap:14px;flex-wrap:wrap;">${legend}</div>
      </div>
      <div style="display:flex;align-items:flex-end;gap:2px;border-bottom:1px solid #e3e3ea;padding-bottom:0;">${columns}</div>
    </div>`;
  }

  function systemTab() {
    const req = cfg.requirements || {};
    const items = req.items || {};
    return `
      <section class="efb-hs-panel">
        <div class="efb-hs-panel-head">
          <div><h2>System readiness</h2><p>Human Shield checks these functions before enforcing security rules.</p></div>
          <button type="button" class="efb-hs-primary" data-action="save"><i class="efb bi-save ${sideClass}-1"></i>${esc(text.save || 'Save Settings')}</button>
        </div>
        <div class="efb-hs-system-status ${req.ok ? 'ok' : 'warn'}">
          <i class="efb bi-${req.ok ? 'check-circle' : 'exclamation-triangle'}"></i>
          <div>
            <strong>${req.ok ? 'PHP requirements are ready.' : 'Some required PHP functions are unavailable.'}</strong>
            <p>PHP ${esc(req.php_version || '')} | REST API: ${req.rest_available ? 'available' : 'missing'} | Tables: ${req.db_tables_ready ? 'ready' : 'not ready yet'}</p>
          </div>
        </div>
        <div class="efb-hs-function-list">
          ${Object.keys(items).map((key) => functionItem(items[key])).join('')}
        </div>
        <div class="efb-hs-form-grid">
          ${toggleField('fail_closed_on_missing_requirements', 'Fail closed when requirements are missing', 'Recommended only after you verify PHP functions and tables are ready.')}

          ${toggleField('trusted_proxy_headers', 'Trust proxy IP headers', 'Use forwarding headers only when the immediate peer matches the trusted proxy list below.')}

          ${textareaField('trusted_proxy_ips', 'Trusted proxy IPs', 'One exact IP or wildcard prefix per line. Add only your load balancer/CDN egress addresses; keep this empty when the origin is directly reachable.')}
          ${toggleField('store_raw_metrics', 'Store raw behavior metrics', 'Keep off for privacy unless debugging a rollout.')}
          ${numberField('log_retention_days', 'Log retention days', 'How long events should be retained.')}
          ${numberField('client_attest_timeout_ms', 'Client attestation timeout', 'Milliseconds before the browser falls back to the normal request.')}
        </div>
        <div class="efb-hs-note">
          <i class="efb bi-tools"></i>
          If a required PHP function is disabled, enable it in php.ini disable_functions or ask the host to allow it. Cache and security plugins must not cache or rewrite POST responses from /wp-json/EmsfbShield/v1/*.
        </div>
      </section>
    `;
  }

  function statCard(label, value, icon, kind) {
    return `<div class="efb-hs-stat ${kind}">
      <div class="efb-hs-stat-icon"><i class="efb ${icon}"></i></div>
      <div class="efb-hs-stat-body">
        <strong>${esc(value)}</strong>
        <span>${esc(label)}</span>
      </div>
    </div>`;
  }

  function summaryItem(label, value) {
    return `<div class="efb-hs-summary-item"><span>${esc(label)}</span><strong>${value}</strong></div>`;
  }

  function numberField(key, label, hint) {
    return `<label class="efb-hs-field">
      <span>${esc(label)}</span>
      <input type="number" min="0" step="1" data-setting="${esc(key)}" value="${esc(settings[key])}">
      <small>${esc(hint || '')}</small>
    </label>`;
  }

  function selectField(key, label, options, hint) {
    return `<label class="efb-hs-field">
      <span>${esc(label)}</span>
      <select data-setting="${esc(key)}">
        ${options.map((opt) => `<option value="${esc(opt[0])}" ${String(settings[key]) === String(opt[0]) ? 'selected' : ''}>${esc(opt[1])}</option>`).join('')}
      </select>
      <small>${esc(hint || '')}</small>
    </label>`;
  }

  function textareaField(key, label, hint) {
    return `<label class="efb-hs-field" style="grid-column:1/-1;">
      <span>${esc(label)}</span>
      <textarea data-setting="${esc(key)}" rows="4" spellcheck="false" style="resize:vertical;font-family:monospace;font-size:12.5px;">${esc(settings[key] || '')}</textarea>
      <small>${esc(hint || '')}</small>
    </label>`;
  }

  function toggleField(key, label, hint) {
    return `<label class="efb-hs-toggle">
      <input type="checkbox" data-setting="${esc(key)}" ${Number(settings[key]) === 1 ? 'checked' : ''}>
      <span class="efb-hs-toggle-ui"></span>
      <span><strong>${esc(label)}</strong><small>${esc(hint || '')}</small></span>
    </label>`;
  }

  function functionItem(item) {
    const available = Boolean(item.available);
    return `<div class="efb-hs-function ${available ? 'ok' : item.required ? 'danger' : 'warn'}">
      <i class="efb bi-${available ? 'check2-circle' : item.required ? 'x-circle' : 'exclamation-circle'}"></i>
      <span>${esc(item.label || '')}</span>
      <small>${item.required ? 'Required' : 'Recommended'}</small>
    </div>`;
  }

  function logsPanel(limit) {
    return `<section class="efb-hs-panel"><div class="efb-hs-panel-head"><div><h2>Recent activity</h2><p>Latest security decisions.</p></div></div>${logsTable(limit)}</section>`;
  }

  function logsTable(limit) {
    const rows = logs.slice(0, limit || 20);
    if (!rows.length) {
      return `<div class="efb-hs-empty"><i class="efb bi-inbox"></i><span>No logs yet.</span></div>`;
    }
    return `<div class="efb-hs-table-wrap"><table class="efb-hs-table">
      <thead><tr><th>Date</th><th>Route</th><th>Form</th><th>Decision</th><th>Score</th><th>Reasons</th></tr></thead>
      <tbody>${rows.map((row) => `
        <tr>
          <td>${esc(row.created_at)}</td>
          <td><code>${esc(row.route)}</code></td>
          <td>${esc(row.form_id)}</td>
          <td><span class="efb-hs-decision ${esc(row.decision)}">${esc(row.decision)}</span></td>
          <td>${esc(row.score)}</td>
          <td>${esc(row.reason_codes)}</td>
        </tr>
      `).join('')}</tbody>
    </table></div>`;
  }

  function switchTab(tab) {
    document.querySelectorAll('.efb-hs-tab').forEach((el) => el.classList.toggle('active', el.dataset.tab === tab));
    const content = document.getElementById('efb-hs-content');
    if (!content) return;
    if (tab === 'protection') content.innerHTML = protectionTab();
    else if (tab === 'paid') content.innerHTML = paidTab();
    else if (tab === 'logs') content.innerHTML = logsTab();
    else if (tab === 'system') content.innerHTML = systemTab();
    else content.innerHTML = overviewTab();
  }

  function collectSettings() {
    fields.forEach(([key, type]) => {
      const el = document.querySelector(`[data-setting="${key}"]`);
      if (!el) return;
      if (type === 'checkbox') settings[key] = el.checked ? 1 : 0;
      else if (type === 'number') settings[key] = parseInt(el.value || '0', 10);
      else settings[key] = el.value;
    });
  }

  function showAlert(message, kind) {
    const alert = document.getElementById('efb-hs-alert');
    if (!alert) return;
    alert.className = `efb-hs-alert ${kind || 'ok'}`;
    alert.textContent = message;
    alert.hidden = false;
    window.setTimeout(() => { alert.hidden = true; }, 4500);
  }

  async function ajax(action, payload) {
    const body = new URLSearchParams();
    body.set('action', action);
    body.set('nonce', cfg.nonce || '');
    Object.keys(payload || {}).forEach((key) => body.set(key, payload[key]));
    const response = await fetch(cfg.ajaxUrl, {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
      body
    });
    return response.json();
  }

  async function saveSettings() {
    collectSettings();
    const buttons = document.querySelectorAll('[data-action="save"]');
    buttons.forEach((button) => { button.disabled = true; button.dataset.original = button.innerHTML; button.innerHTML = esc(text.saving || 'Saving...'); });
    try {
      const result = await ajax('efb_human_shield_save_settings', { settings: JSON.stringify(settings) });
      if (!result || !result.success) throw new Error('save failed');
      settings = Object.assign({}, result.data.settings || settings);
      stats = result.data.stats || stats;
      persistedEnabled = Number(settings.enabled) === 1;
      render();
      showAlert(text.saved || 'Settings saved.', 'ok');
    } catch (error) {
      showAlert(text.failed || 'Request failed.', 'danger');
    } finally {
      buttons.forEach((button) => { button.disabled = false; if (button.dataset.original) button.innerHTML = button.dataset.original; });
    }
  }

  async function refreshLogs() {
    try {
      const result = await ajax('efb_human_shield_load_logs', {});
      if (!result || !result.success) throw new Error('log failed');
      logs = result.data.logs || [];
      stats = result.data.stats || stats;
      hourly = Array.isArray(result.data.hourly) ? result.data.hourly : hourly;
      switchTab('logs');
    } catch (error) {
      showAlert(text.failed || 'Request failed.', 'danger');
    }
  }

  function exportLogs() {
    const url = cfg.ajaxUrl +
      (cfg.ajaxUrl.indexOf('?') === -1 ? '?' : '&') +
      'action=efb_human_shield_export_logs&nonce=' + encodeURIComponent(cfg.nonce || '');
    window.location.assign(url);
  }

  async function clearLogs() {
    if (!window.confirm('Clear Human Shield logs?')) return;
    try {
      const result = await ajax('efb_human_shield_clear_logs', {});
      if (!result || !result.success) throw new Error('clear failed');
      logs = [];
      stats = result.data.stats || {};
      switchTab('logs');
      showAlert('Logs cleared.', 'ok');
    } catch (error) {
      showAlert(text.failed || 'Request failed.', 'danger');
    }
  }

  app.addEventListener('click', (event) => {
    const tab = event.target.closest('.efb-hs-tab');
    if (tab) {
      switchTab(tab.dataset.tab || 'overview');
      return;
    }
    const action = event.target.closest('[data-action]');
    if (!action) return;
    const name = action.dataset.action;
    if (name === 'save') saveSettings();
    if (name === 'refresh-logs') refreshLogs();
    if (name === 'clear-logs') clearLogs();
    if (name === 'export-logs') exportLogs();
  });

  app.addEventListener('change', (event) => {
    const toggle = event.target.closest('[data-setting="enabled"]');
    if (!toggle) return;
    settings.enabled = toggle.checked ? 1 : 0;
    updateStatusIndicator();
  });

  try {
    render();
  } catch (error) {
    renderFatal(
      ((cfg.text && cfg.text.renderFailed) || 'The Human Shield panel failed to render.') +
      ' [' + ((error && error.message) || 'unknown error') + ']'
    );
  }
})();
