/**
 * F02: Settings > Verification (?page=Emsfb&state=setting&tab=verify). Spec §4.2-4.4.
 *
 * Element ids start with "vrfs-": the Localization tab gives every phrase key its own input id.
 * Reads and saves through wp_ajax_efb_verify_settings_Emsfb (administrators only, wp_rest nonce);
 * the test code through wp_ajax_efb_verify_test_send_Emsfb (counted in the daily budget). SMS codes
 * go through the WP SMS plugin only (owner rule): its gateway, credentials and sender number are set
 * up in WP SMS; this card shows whether WP SMS and the SMS add-on are ready and links to them.
 */
(function (root) {
  'use strict';

  const T = (k, fb) => { try { if (root.efb_var && root.efb_var.text && root.efb_var.text[k]) return root.efb_var.text[k]; } catch (e) { /* ignore */ } return fb; };
  const esc = (v) => String(v == null ? '' : v).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#039;');
  const digits = (v) => String(v == null ? '' : v).replace(/[۰-۹]/g, (d) => String(d.charCodeAt(0) - 0x06F0)).replace(/[٠-٩]/g, (d) => String(d.charCodeAt(0) - 0x0660));
  let current = null;

  function post(action, extra) {
    const url = (root.ajax_object_efm && root.ajax_object_efm.ajax_url) || (root.efb_var && root.efb_var.ajax_url) || (typeof ajaxurl !== 'undefined' ? ajaxurl : '/wp-admin/admin-ajax.php');
    const nonce = typeof _efb_core_nonce_ !== 'undefined' ? _efb_core_nonce_ : (root.efb_var && root.efb_var.nonce);
    const body = new URLSearchParams(Object.assign({ action: action, nonce: nonce }, extra || {}));
    return fetch(url, { method: 'POST', credentials: 'same-origin', body: body })
      .then((r) => r.json()).then((j) => (j && j.data ? j.data : { success: false })).catch(() => ({ success: false, m: T('error', 'Error') }));
  }

  /** The tab's shell; the content is drawn once the state is read. */
  function efbVerifySettingsHtml(mx) {
    setTimeout(load, 0);
    return `<div class="efb m-3" id="efb-verify-settings" data-mx="${esc(mx || '')}">
      <h5 class="efb card-title mt-3 mobile-title"><i class="efb bi-patch-check m-3" aria-hidden="true"></i>${esc(T('vrfTab', 'Verification'))}</h5>
      <p class="efb ${esc(mx || '')} mobile-text">${esc(T('vrfTabIntro', 'Visitors confirm a mobile number or an e-mail address with a one-time code before the form is sent.'))}</p>
      <div id="efb-verify-body" aria-live="polite"><p class="efb text-muted ${esc(mx || '')}">${esc(T('loading', 'Loading…'))}</p></div>
    </div>`;
  }

  function load() {
    const box = document.getElementById('efb-verify-body');
    if (!box) return;
    post('efb_verify_settings_Emsfb', { op: 'get' }).then((r) => {
      if (!r || !r.success) { box.innerHTML = `<p class="efb text-danger" role="alert">${esc((r && r.m) || T('error', 'Error'))}</p>`; return; }
      current = r.state;
      draw();
    });
  }

  function num(id, label, val, help) {
    return `<div class="efb col-sm-6 col-md-4 mb-2"><label class="efb form-label fs-7 mb-1" for="${id}">${esc(label)}</label>
      <input type="text" inputmode="numeric" autocomplete="off" class="efb form-control h-d-efb border-d efb-rounded" id="${id}" value="${esc(val)}" ${help ? `aria-describedby="${id}Help"` : ''}>
      ${help ? `<small class="efb text-muted fs-8" id="${id}Help">${esc(help)}</small>` : ''}</div>`;
  }
  function txt(id, label, val, opts) {
    opts = opts || {};
    return `<div class="efb ${opts.col || 'col-12'} mb-2"><label class="efb form-label fs-7 mb-1" for="${id}">${esc(label)}</label>
      <input type="${opts.type || 'text'}" autocomplete="off" class="efb form-control h-d-efb border-d efb-rounded" id="${id}" value="${esc(val)}" ${opts.dir ? `dir="${opts.dir}"` : ''} placeholder="${esc(opts.placeholder || '')}" ${opts.help ? `aria-describedby="${id}Help"` : ''}>
      ${opts.help ? `<small class="efb text-muted fs-8 d-block" id="${id}Help">${esc(opts.help)}</small>` : ''}</div>`;
  }
  function chk(id, label, on) {
    return `<div class="efb form-check my-1"><input class="efb form-check-input" type="checkbox" id="${id}" ${on ? 'checked' : ''}><label class="efb form-check-label mx-2" for="${id}">${esc(label)}</label></div>`;
  }
  function stateLine(name, st) {
    return `<li class="efb"><strong>${esc(name)}</strong>: <span class="efb ${st.ready ? 'text-success' : 'text-danger'}" data-vrf-ready="${st.ready ? 1 : 0}">${esc(st.ready ? T('vrfChannelReady', 'Ready') : T('vrfChannelOff', 'Not available'))}</span></li>`;
  }

  /** Where SMS codes go and what is missing (the SMS add-on, WP SMS), with the links to fix it. */
  function senderBox() {
    const L = current.links || {};
    const link = (href, text) => `<a class="efb" href="${esc(href)}" target="_blank" rel="noopener">${esc(text)}</a>`;
    let html = `<p class="efb fs-7 mb-1" id="vrfs-SmsVia">${esc(T('vrfSmsVia', 'SMS codes are sent through the WP SMS plugin. Its gateway, credentials and sender number are set up in WP SMS.'))} ${link(L.docs || 'https://wp-sms-pro.com/resources/wp_sms_send/', 'wp_sms_send()')}</p>`;
    if (!current.sms_addon || current.state.sms.reason === 'no_provider') html += `<p class="efb fs-7 text-danger mb-1" role="alert" id="vrfs-AddonOff">${esc(T('vrfNoProviders', 'The SMS add-on is off. Turn it on in Add-ons: SMS codes are sent through the WP SMS plugin.'))} ${link(L.addons || '#', T('vrfAddons', 'Add-ons'))}</p>`;
    if (!current.wp_sms) html += `<p class="efb fs-7 text-danger mb-1" role="alert" id="vrfs-WpSmsMissing">${esc(T('vrfWpSmsMissing', 'WP SMS is not installed or not active.'))} ${link(L.wp_sms_install || '#', T('vrfWpSmsInstall', 'Install and set up WP SMS'))}</p>`;
    else html += `<p class="efb fs-7 mb-1">${link(L.wp_sms_settings || '#', T('vrfWpSmsSettings', 'WP SMS settings'))}</p>`;
    return html;
  }

  function draw() {
    const box = document.getElementById('efb-verify-body');
    if (!box || !current) return;
    const s = current.settings;
    const can = !!(root.efb_var && root.efb_var.privacy ? root.efb_var.privacy.can_manage : true);
    const stats = (ch) => {
      const st = current.stats[ch] || { sent: 0, verified: 0 };
      const warn = st.sent >= 20 && st.verified * 100 < 30 * st.sent;
      return `<p class="efb fs-7 mb-1" data-vrf-stats="${ch}">${esc(T('vrfStats', 'Last 24 hours: %1$s codes sent, %2$s verified.').replace('%1$s', st.sent).replace('%2$s', st.verified))}</p>${warn ? `<p class="efb fs-7 text-danger" role="alert">${esc(T('vrfStatsWarn', 'Few codes are entered after being sent. This can be SMS pumping: check the limits and the countries.'))}</p>` : ''}`;
    };
    box.innerHTML = `
      <ul class="efb list-unstyled fs-7" id="vrfs-ChannelStates">${stateLine(T('vrfEmailSection', 'E-mail codes'), current.state.email)}${stateLine(T('vrfSmsSection', 'SMS codes'), current.state.sms)}</ul>
      <p class="efb fs-8 text-muted" role="note">${esc(T('vrfSecurityNote', 'An SMS code shows that the visitor receives messages on that number. It is not a strong login factor.'))}</p>
      <fieldset class="efb border-0 p-0 mt-3"><legend class="efb fs-6 fw-bold">${esc(T('vrfSmsSection', 'SMS codes'))}</legend>
        ${senderBox()}
        <div class="efb row">
          ${txt('vrfs-CountriesSet', T('vrfAllowedCountries', 'Countries that may receive SMS codes (ISO codes, comma separated; empty = no SMS codes)'), (s.sms.allowed_countries || []).join(','), { dir: 'ltr', col: 'col-md-6' })}
          ${txt('vrfs-Message', T('vrfMessage', 'Message text ({code}, {site}, {domain})'), s.sms.message, { col: 'col-md-6' })}
          ${num('vrfs-SmsLen', T('vrfCodeLength', 'Code length (digits)'), s.sms.code_length)}
          ${num('vrfs-SmsTtl', T('vrfTtl', 'Code lifetime (seconds)'), s.sms.ttl)}
          ${num('vrfs-SmsBudget', T('vrfDailyBudget', 'Daily budget (codes per day)'), s.sms.daily_budget)}
        </div>
        ${chk('vrfs-WebOtp', T('vrfWebOtp', 'Add the line for automatic code filling (@domain #code)'), Number(s.sms.webotp) === 1)}
        ${stats('sms')}
      </fieldset>
      <fieldset class="efb border-0 p-0 mt-3"><legend class="efb fs-6 fw-bold">${esc(T('vrfEmailSection', 'E-mail codes'))}</legend>
        <div class="efb row">
          ${num('vrfs-EmailLen', T('vrfCodeLength', 'Code length (digits)'), s.email.code_length)}
          ${num('vrfs-EmailTtl', T('vrfTtl', 'Code lifetime (seconds)'), s.email.ttl)}
          ${num('vrfs-EmailBudget', T('vrfDailyBudget', 'Daily budget (codes per day)'), s.email.daily_budget)}
          ${txt('vrfs-Blocked', T('vrfBlockedDomains', 'Blocked e-mail domains (comma separated)'), (s.email.blocked_domains || []).join(','), { dir: 'ltr' })}
        </div>
        ${chk('vrfs-CodeInSubject', T('vrfCodeInSubject', 'Put the code in the subject (shown on lock screens)'), Number(s.email.code_in_subject) === 1)}
        ${stats('email')}
      </fieldset>
      <fieldset class="efb border-0 p-0 mt-3"><legend class="efb fs-6 fw-bold">${esc(T('vrfLimits', 'Limits'))}</legend>
        <small class="efb text-muted fs-8 d-block mb-2">${esc(T('vrfLimitsHelp', 'Per destination, per visitor address and per form. A refused request costs nothing.'))}</small>
        <div class="efb row">${Object.keys(s.limits).map((k) => num('vrfs-L_' + k, k.replace(/_/g, ' '), s.limits[k])).join('')}
          ${num('vrfs-SmsDestHour', 'SMS / destination / hour', s.sms.max_sends_dest_hour)}${num('vrfs-SmsDestDay', 'SMS / destination / day', s.sms.max_sends_dest_day)}
          ${num('vrfs-EmailDestHour', 'e-mail / destination / hour', s.email.max_sends_dest_hour)}${num('vrfs-EmailDestDay', 'e-mail / destination / day', s.email.max_sends_dest_day)}
        </div>
      </fieldset>
      <div class="efb mt-3 d-flex flex-wrap gap-2 align-items-center">
        <button type="button" class="efb btn btn-primary" id="vrfs-SaveBtn" ${can ? '' : 'disabled'}>${esc(T('save', 'Save'))}</button>
        <span class="efb fs-7" id="vrfs-SaveMsg" role="status" aria-live="polite"></span>
      </div>
      <fieldset class="efb border-0 p-0 mt-3"><legend class="efb fs-6 fw-bold">${esc(T('vrfTestSend', 'Send a test code'))}</legend>
        <div class="efb d-flex flex-wrap gap-2 align-items-end">
          <select class="efb form-select border-d efb-rounded w-auto" id="vrfs-TestCh" aria-label="${esc(T('vrfTestSend', 'Send a test code'))}"><option value="sms">SMS</option><option value="email">e-mail</option></select>
          <input type="text" dir="ltr" class="efb form-control h-d-efb border-d efb-rounded w-auto" id="vrfs-TestTo" placeholder="+98… / name@example.com" aria-label="${esc(T('vrfTestTo', 'Your number or address'))}">
          <button type="button" class="efb btn btn-outline-primary" id="vrfs-TestBtn" ${can ? '' : 'disabled'}>${esc(T('vrfTestSend', 'Send a test code'))}</button>
        </div>
        <span class="efb fs-7 d-block mt-1" id="vrfs-TestMsg" role="status" aria-live="polite"></span>
      </fieldset>`;
    document.getElementById('vrfs-SaveBtn').addEventListener('click', save);
    document.getElementById('vrfs-TestBtn').addEventListener('click', testSend);
  }

  const val = (id) => { const el = document.getElementById(id); return el ? el.value : ''; };
  const n = (id) => digits(val(id)).replace(/[^0-9]/g, '');

  function save() {
    const s = current.settings;
    const limits = {};
    Object.keys(s.limits).forEach((k) => { limits[k] = n('vrfs-L_' + k); });
    const settings = {
      sms: { allowed_countries: val('vrfs-CountriesSet'), message: val('vrfs-Message'),
        code_length: n('vrfs-SmsLen'), ttl: n('vrfs-SmsTtl'), daily_budget: n('vrfs-SmsBudget'), webotp: document.getElementById('vrfs-WebOtp').checked ? 1 : 0,
        max_sends_dest_hour: n('vrfs-SmsDestHour'), max_sends_dest_day: n('vrfs-SmsDestDay') },
      email: { code_length: n('vrfs-EmailLen'), ttl: n('vrfs-EmailTtl'), daily_budget: n('vrfs-EmailBudget'), blocked_domains: val('vrfs-Blocked'),
        code_in_subject: document.getElementById('vrfs-CodeInSubject').checked ? 1 : 0, max_sends_dest_hour: n('vrfs-EmailDestHour'), max_sends_dest_day: n('vrfs-EmailDestDay') },
      limits: limits,
    };
    const out = document.getElementById('vrfs-SaveMsg');
    const btn = document.getElementById('vrfs-SaveBtn');
    btn.disabled = true;
    post('efb_verify_settings_Emsfb', { op: 'save', settings: JSON.stringify(settings) }).then((r) => {
      btn.disabled = false;
      if (r && r.success) {
        current = r.state;
        // efb_var is frozen on this page: the builder reads the fresh state on its next load.
        draw();
        const m = document.getElementById('vrfs-SaveMsg');
        if (m) { m.className = 'efb fs-7 text-success'; m.textContent = T('vrfSaved', 'Verification settings saved.'); }
      } else if (out) { out.className = 'efb fs-7 text-danger'; out.textContent = (r && r.m) || T('error', 'Error'); }
    });
  }

  function testSend() {
    const out = document.getElementById('vrfs-TestMsg');
    const btn = document.getElementById('vrfs-TestBtn');
    btn.disabled = true;
    post('efb_verify_test_send_Emsfb', { channel: val('vrfs-TestCh'), to: val('vrfs-TestTo') }).then((r) => {
      btn.disabled = false;
      out.className = 'efb fs-7 d-block mt-1 ' + (r && r.success ? 'text-success' : 'text-danger');
      out.textContent = (r && r.m) || T('error', 'Error');
    });
  }

  root.efbVerifySettingsHtml = efbVerifySettingsHtml;
  root.efbVerifySettingsLoad = load;
}(typeof window !== 'undefined' ? window : this));
