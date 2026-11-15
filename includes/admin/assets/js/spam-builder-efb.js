/**
 * F12 / F12b in the builder: the form's CAPTCHA service, its options and its spam settings, in the form settings
 * panel (show_setting_window_efb case 'formSet', under the CAPTCHA switch).
 *
 *  - "CAPTCHA service" (captcha_provider): Site default (now …), the Google modes (core) and every CAPTCHA add-on
 *    from efb_var.captcha_providers (the server's registry): one without its keys is offered disabled ("Keys are not
 *    set"), one not installed says "Install the add-on" with a link, one above the plan says "Available on Free Plus
 *    and Pro"; the form keeps a service whose add-on is gone (the server protects it with the fallback);
 *  - the options of the chosen service are drawn from its schema (enum → select, number, text, toggle), all cp_*
 *    keys plus Google's rc3_*; "" = the site's / default. Hiding Google's badge says that its notice is shown;
 *  - "Turn off spam checks for this form" (spam_off) and "Check this form with Akismet" (spam_akismet "0" = off);
 *  - efbSpamCaptchaReady(row 0), efbSpamCaptchaPlaceholder() (the preview never shows a live widget).
 *
 * Every key goes through EFBSpamCore (sanitizeKey, sanitizeOption), the twin of Spam::sanitize_form_key() and
 * Captcha\Registry::sanitize_form_option().
 */
(function (root) {
  'use strict';
  // Phrases arrive escaped for HTML (esc_html__): decoded here, escaped once where they are written.
  const dec = (v) => { const s = String(v == null ? '' : v); if (s.indexOf('&') === -1) return s; try { const d = document.createElement('textarea'); d.innerHTML = s; return d.value; } catch (e) { return s; } };
  const T = (k, fb) => { try { if (root.efb_var && root.efb_var.text && root.efb_var.text[k]) return dec(root.efb_var.text[k]); } catch (e) { /* no phrases */ } return fb; };
  const esc = (v) => String(v == null ? '' : v).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#039;');
  const list = () => (typeof valj_efb !== 'undefined' ? valj_efb : []);
  const on = (v) => v === true || v === 1 || v === '1' || v === 'true';
  const NAMES = { recaptcha_v2: ['reCAPTCHAv2', 'reCAPTCHA v2'], recaptcha_v2i: ['reCAPTCHAv2i', 'Google reCAPTCHA v2 (invisible)'], recaptcha_v3: ['reCAPTCHAv3', 'Google reCAPTCHA v3'] };
  const ID_RE = /^[a-z][a-z0-9_]{2,23}$/;
  const latin = (s) => String(s == null ? '' : s).replace(/[۰-۹]/g, (d) => String(d.charCodeAt(0) - 1776)).replace(/[٠-٩]/g, (d) => String(d.charCodeAt(0) - 1632)).replace(/٫/g, '.');
  /** The registry's providers (efb_var.captcha_providers), or the F12 ready map of an older server. */
  function catalog() {
    const c = root.efb_var && root.efb_var.captcha_providers;
    if (Array.isArray(c) && c.length) return c;
    const m = (root.efb_var && root.efb_var.captcha_ready) || {};
    return Object.keys(m).map((id) => ({ id, label: NAMES[id] ? NAMES[id][1] : id, installed: true, available: true, ready: !!m[id], kind: id === 'recaptcha_v3' ? 'invisible' : 'widget', options: [] }));
  }
  const entry = (id) => catalog().find((e) => e.id === id) || null;
  const name = (p) => (NAMES[p] ? T(NAMES[p][0], NAMES[p][1]) : ((entry(p) || {}).label || p));
  const ready = () => (root.efb_var && root.efb_var.captcha_ready) || null;
  const siteProvider = () => { const s = root.efb_var && root.efb_var.spam; return s && ID_RE.test(String(s.site_provider || '')) ? s.site_provider : 'recaptcha_v2'; };

  /** One option value by its schema entry (twin of Provider::by_schema()); null = refused. */
  function bySchema(f, v) {
    if (v === true) v = '1'; else if (v === false) v = '';
    if (v !== null && typeof v === 'object') return null;
    let s = String(v == null ? '' : v).replace(/<[^>]*>/g, '').replace(/[\r\n\t]+/g, ' ').trim();
    switch (f.type) {
      case 'toggle': return s === '1' || s === 'true' || s === 'on' ? '1' : '';
      case 'enum': case 'select': return s === '' || (f.values || []).map(String).indexOf(s) !== -1 ? s : null;
      case 'number': {
        s = latin(s);
        if (s === '') return '';
        if (!/^-?\d*\.?\d+$/.test(s)) return null;
        let n = Number(s);
        if (f.min !== undefined) n = Math.max(Number(f.min), n);
        if (f.max !== undefined) n = Math.min(Number(f.max), n);
        return String(n);
      }
    }
    s = Array.from(s).slice(0, f.maxlen ? Number(f.maxlen) : 200).join('');
    if (f.pattern && s !== '' && !new RegExp(f.pattern).test(s)) return null;
    return s;
  }
  /** A cp_* option of a form for its provider ('' = the default; unknown key ''; provider unknown: plain text). */
  function sanitizeOption(p, k, v) {
    if (!/^cp_[a-z0-9_]{1,24}$/.test(String(k))) return '';
    const e = entry(p);
    if (!e || !e.installed) { if (v !== null && typeof v === 'object') return ''; return Array.from(String(v == null ? '' : v).replace(/<[^>]*>/g, '').trim()).slice(0, 200).join(''); }
    const f = (e.options || []).find((o) => o.key === k);
    if (!f) return '';
    const r = bySchema(f, v);
    return r === null ? '' : r;
  }

  /** The save rule of the row-0 keys ('' = not set), twin of \Emsfb\Spam::sanitize_form_key(). */
  function sanitizeKey(k, v) {
    if (k === 'spam_akismet' && v === false) return '0'; // "Akismet for this form": off
    const s = v === true ? '1' : v === false ? '' : String(v == null ? '' : v).trim();
    switch (k) {
      case 'captcha_provider': return ID_RE.test(s) ? s : '';
      case 'rc3_threshold': {
        const n = latin(s).replace(',', '.');
        if (n === '' || !/^-?\d*\.?\d+$/.test(n)) return '';
        return String(Math.round(Math.max(0, Math.min(1, Number(n))) * 100) / 100);
      }
      case 'rc3_action': return /^[A-Za-z0-9_/]{1,40}$/.test(s) ? s : '';
      case 'rc3_fallback': return s === '1' || s === 'true' ? '1' : '';
      case 'spam_off': return s === '1' || s === 'true' ? '1' : '';
      case 'spam_akismet': return s === '0' || s === 'false' || s === 'off' ? '0' : '';
    }
    return '';
  }
  const formProvider = () => { const r0 = list()[0] || {}; const p = r0.captcha_provider; return ID_RE.test(String(p || '')) ? p : siteProvider(); };
  root.EFBSpamCore = { sanitizeKey, sanitizeOption, catalog, entry, formProvider, ID_RE, FORM_KEYS: ['captcha_provider', 'rc3_threshold', 'rc3_action', 'rc3_fallback', 'spam_off', 'spam_akismet'] };

  /** The service a form asks for (its own, else the site's). */
  function efbSpamProvider(r0) { const p = r0 && r0.captcha_provider; return ID_RE.test(String(p || '')) ? p : siteProvider(); }

  /** Whether the form's CAPTCHA can show (its service registered, on the plan, keys set). Old builders: efb_var.captcha. */
  function efbSpamCaptchaReady(r0) {
    const m = ready();
    if (!m) return root.efb_var && (root.efb_var.captcha == 'true' || root.efb_var.captcha === true);
    const e = entry(efbSpamProvider(r0 || list()[0] || {}));
    return !!(e && e.available !== false && (e.ready || m[e.id]));
  }

  function changed() {
    try { if (typeof sessionStorage !== 'undefined') sessionStorage.setItem('valj_efb', JSON.stringify(valj_efb)); } catch (e) { /* storage off */ }
    try { document.dispatchEvent(new CustomEvent('efb:form-changed', { detail: { source: 'user', action: 'setting', ids: [] } })); } catch (e) { /* old browser */ }
  }

  const OPT_LABELS = { light: ['cpLight', 'Light'], dark: ['cpDark', 'Dark'], auto: ['cpAuto', 'Automatic'], normal: ['cpNormal', 'Normal'], compact: ['cpCompact', 'Compact'], flexible: ['cpFlexible', 'Full width'],
    invisible: ['cpInvisible', 'Invisible'], start: ['cpStartAlign', 'Start'], center: ['cpCenter', 'Center'], end: ['cpEndAlign', 'End'], inline: ['cpInline', 'Inline'], hidden: ['cpHidden', 'Hidden'],
    bottomright: ['cpBottomRight', 'Bottom right'], bottomleft: ['cpBottomLeft', 'Bottom left'] };
  /** The controls of a service's options (rc3_threshold keeps its F12 control). */
  function optionEls(p, r0) {
    const e = entry(p);
    if (!e || !e.installed) return '';
    return (e.options || []).filter((f) => f.key !== 'rc3_threshold').map((f) => {
      const id = 'cpOpt_' + f.key;
      const v = r0[f.key] == null ? '' : String(r0[f.key]);
      const lab = esc(T(f.label_key, f.label || f.key));
      if (f.type === 'toggle') return `<div class="efb form-check form-switch mx-1 my-2"><input type="checkbox" role="switch" class="efb elEdit form-check-input" id="${id}" data-id="formSet" data-key="${f.key}" ${on(v) ? 'checked' : ''}><label class="efb form-check-label" for="${id}">${lab}</label></div>`;
      if (f.type === 'enum') {
        const opts = [`<option value=""${v === '' ? ' selected' : ''}>${esc(T('siteDefault', 'Site default'))}</option>`].concat((f.values || []).map((x) => `<option value="${esc(x)}"${v === String(x) ? ' selected' : ''}>${esc(OPT_LABELS[x] ? T(OPT_LABELS[x][0], OPT_LABELS[x][1]) : T('cpv_' + x, x))}</option>`)).join('');
        const note = f.key === 'cp_badge' ? `<small class="efb form-text text-muted d-block fs-8 ${v === 'hidden' ? '' : 'd-none'}" id="cpBadgeNote" dir="auto">${esc(T('cpBadgeHiddenNote', 'Google requires its notice when the badge is hidden; it is shown under the submit button.'))}</small>` : '';
        return `<label for="${id}" class="efb form-label mt-2 mb-1">${lab}</label><select class="efb elEdit form-select border-d rounded-4 mb-1" id="${id}" data-id="formSet" data-key="${f.key}">${opts}</select>${note}`;
      }
      const num = f.type === 'number';
      return `<label for="${id}" class="efb form-label mt-2 mb-1">${lab}</label><input type="text" ${num ? 'inputmode="decimal" dir="ltr"' : 'dir="auto"'} class="efb elEdit form-control border-d rounded-4 h-d-efb mb-1" id="${id}" data-id="formSet" data-key="${f.key}" value="${esc(v)}"${f.maxlen ? ` maxlength="${Number(f.maxlen)}"` : ''}>`;
    }).join('');
  }

  /** The controls under the CAPTCHA switch (not on a payment form: it has no CAPTCHA). */
  function efbSpamFormEls() {
    const r0 = list()[0] || {};
    const cat = catalog();
    const own = ID_RE.test(String(r0.captcha_provider || '')) ? r0.captcha_provider : '';
    const eff = efbSpamProvider(r0);
    const e = entry(eff);
    const why = (x) => (!x.installed ? T('cpNotInstalled', 'Not installed') : x.available === false ? T('cpPlanNeeded', 'Available on Free Plus and Pro') : x.ready === false ? T('captchaKeyMissing', 'Keys are not set for this service.') : '');
    const opt = (v, label, dis) => `<option value="${esc(v)}"${own === v ? ' selected' : ''}${dis ? ' disabled' : ''}>${esc(label)}</option>`;
    const site = siteProvider();
    let opts = opt('', T('captchaSiteDefaultNow', 'Site default (now %s)').replace('%s', name(site)), false)
      + cat.map((x) => { const w = why(x); return opt(x.id, name(x.id) + (w ? ' — ' + w : ''), w !== '' && own !== x.id); }).join('');
    if (own && !entry(own)) opts += opt(own, own + ' — ' + T('cpNotInstalled', 'Not installed'), false);
    const problem = e ? why(e) : T('cpNotInstalled', 'Not installed');
    const addonUrl = root.efb_var && root.efb_var.spam && root.efb_var.spam.plugin_url;
    const pay = r0.type === 'payment';
    const ipNone = r0.prv_ip === 'none' && e && e.privacy && (e.privacy.sends || []).indexOf('ip_from_browser') !== -1;
    const captchaPart = pay ? '' : `
      <label for="captchaProviderEl" class="efb form-label mt-2 mb-1">${esc(T('captchaProvider', 'CAPTCHA service'))}</label>
      <select class="efb elEdit form-select border-d rounded-4 mb-1" id="captchaProviderEl" data-id="formSet" data-key="captcha_provider"${problem ? ' aria-describedby="captchaProviderEl-help"' : ''}>${opts}</select>
      <small class="efb form-text text-danger d-block fs-8 ${problem ? '' : 'd-none'}" id="captchaProviderEl-help" role="status" dir="auto">${esc(problem)}${problem && e && !e.installed && addonUrl ? ` <a href="${esc(addonUrl)}" target="_blank" rel="noopener">${esc(T('cpInstallAddon', 'Install the add-on'))}</a>` : ''}</small>
      ${ipNone ? `<small class="efb form-text text-muted d-block fs-8" dir="auto">${esc(T('cpPrivacyIp', "This service gets the visitor's IP address from the browser, whatever your IP setting."))}</small>` : ''}
      <div class="efb ${eff === 'recaptcha_v3' ? '' : 'd-none'}" id="efbSpamRc3">
        <label for="rc3ThresholdEl" class="efb form-label mt-2 mb-1">${esc(T('rc3Threshold', 'Minimum score'))}</label>
        <input type="text" inputmode="decimal" dir="ltr" class="efb elEdit form-control border-d rounded-4 h-d-efb mb-1" id="rc3ThresholdEl" data-id="formSet" data-key="rc3_threshold" value="${esc(r0.rc3_threshold || '')}" placeholder="${esc(T('siteDefault', 'Site default'))}" aria-describedby="rc3ThresholdEl-help">
        <small class="efb form-text text-muted d-block fs-8" id="rc3ThresholdEl-help">${esc(T('rc3ThresholdHelp', 'Scores run from 0.0 (likely a bot) to 1.0 (likely a person). 0.5 suits most sites.'))}</small>
      </div>
      <div class="efb efb-cp-opts" id="efbCpOpts">${optionEls(eff, r0)}</div>`;
    const sw = (id, key, checked, label, help) => `<div class="efb form-check form-switch mx-1 my-2">
        <input type="checkbox" role="switch" class="efb elEdit form-check-input" id="${id}" data-id="formSet" data-key="${key}" ${checked ? 'checked' : ''} aria-describedby="${id}-help">
        <label class="efb form-check-label" for="${id}">${esc(label)}</label>
        <small class="efb form-text text-muted d-block fs-8" id="${id}-help">${esc(help)}</small></div>`;
    const ak = root.efb_var && root.efb_var.spam && root.efb_var.spam.akismet;
    const rtl = root.efb_var && Number(root.efb_var.rtl) === 1;
    return `<div class="efb efb-spam-form mt-1" id="efbSpamForm"${rtl ? ' dir="rtl"' : ''}>${captchaPart}
      ${sw('spamOffEl', 'spam_off', on(r0.spam_off), T('spamOff', 'Turn off spam checks for this form'), T('spamOffHelp', 'For an internal form only: the hidden field, the minimum time and the content checks are skipped. The CAPTCHA still applies.'))}
      ${ak ? sw('spamAkismetEl', 'spam_akismet', String(r0.spam_akismet) !== '0', T('spamFormAkismet', 'Check this form with Akismet'), T('spamAkismetPrivacy', 'Akismet sends the answers to Automattic to check them.')) : ''}
    </div>`;
  }

  /** change_el_edit_Efb for the F12 / F12b controls; false when nothing was written. */
  function efbSpamBuilderChange(el) {
    if (!el) return false;
    const L = list();
    if (!L[0]) return false;
    if (el.id && el.id.indexOf('cpOpt_') === 0) {
      const k = el.getAttribute('data-key');
      const p = efbSpamProvider(L[0]);
      const raw = el.type === 'checkbox' ? (el.checked ? '1' : '') : el.value;
      const v = /^rc3_/.test(k) ? sanitizeKey(k, raw) : sanitizeOption(p, k, raw);
      if (v === '') delete L[0][k]; else L[0][k] = v;
      if (el.type !== 'checkbox' && el.tagName !== 'SELECT') el.value = v;
      if (k === 'cp_badge') { const n = document.getElementById('cpBadgeNote'); if (n) n.classList.toggle('d-none', v !== 'hidden'); }
      changed();
      return true;
    }
    switch (el.id) {
      case 'captchaProviderEl': {
        const v = sanitizeKey('captcha_provider', el.value);
        if (v === '') delete L[0].captcha_provider; else L[0].captcha_provider = v;
        const eff = efbSpamProvider(L[0]);
        // The options of another service mean nothing to this one: they are dropped (the defaults apply).
        const keep = ((entry(eff) || {}).options || []).map((f) => f.key);
        Object.keys(L[0]).forEach((k) => { if ((/^cp_/.test(k) || k === 'rc3_action' || k === 'rc3_fallback') && keep.indexOf(k) === -1) delete L[0][k]; });
        const box = document.getElementById('efbSpamRc3');
        if (box) box.classList.toggle('d-none', eff !== 'recaptcha_v3');
        const o = document.getElementById('efbCpOpts');
        if (o) {
          o.innerHTML = optionEls(eff, L[0]);
          o.querySelectorAll('.elEdit').forEach((x) => x.addEventListener('change', () => { if (typeof change_el_edit_Efb === 'function') change_el_edit_Efb(x); }));
        }
        const help = document.getElementById('captchaProviderEl-help');
        const e = entry(eff);
        const m = ready();
        const notReady = !!(m && (!e || e.available === false || (e.ready === false && m[eff] !== true)));
        if (help) help.classList.toggle('d-none', !notReady);
        // A service that cannot show now: the CAPTCHA switch goes off in the builder (the form keeps the choice).
        if (notReady && on(L[0].captcha)) {
          L[0].captcha = false;
          const sw = document.getElementById('captchaEl');
          if (sw) { sw.classList.remove('active'); sw.setAttribute('aria-pressed', 'false'); }
          try { document.getElementById('dropZoneEFB').classList.remove('captcha'); } catch (e2) { /* no canvas */ }
        }
        changed();
        return true;
      }
      case 'rc3ThresholdEl': {
        const v = sanitizeKey('rc3_threshold', el.value);
        if (v === '') delete L[0].rc3_threshold; else L[0].rc3_threshold = v;
        el.value = v;
        changed();
        return true;
      }
      case 'spamOffEl':
        if (el.checked) L[0].spam_off = '1'; else delete L[0].spam_off;
        changed();
        return true;
      case 'spamAkismetEl':
        if (el.checked) delete L[0].spam_akismet; else L[0].spam_akismet = '0';
        changed();
        return true;
    }
    return false;
  }

  /** The preview's CAPTCHA: a placeholder with the service's name ('' = reCAPTCHA v2, drawn as before). */
  function efbSpamCaptchaPlaceholder() {
    const r0 = list()[0] || {};
    const p = efbSpamProvider(r0);
    if (p === 'recaptcha_v2') return '';
    const al = { center: 'center', end: 'flex-end' }[r0.cp_align] || 'flex-start';
    return `<div class="efb row mx-0" style="flex-direction:column;align-items:${al}"><div class="efb efb-captcha-ph my-2" role="img" aria-label="${esc(T('captchaLabel', 'Security check'))}"><i class="efb bi-shield-check mx-1" aria-hidden="true"></i><span dir="auto" style="unicode-bidi:plaintext">${esc(T('captchaPreview', '%s will show here on the page.').replace('%s', name(p)))}</span></div></div>`;
  }

  const api = { efbSpamFormEls, efbSpamBuilderChange, efbSpamCaptchaReady, efbSpamProvider, efbSpamCaptchaPlaceholder };
  Object.keys(api).forEach((k) => { root[k] = api[k]; });
})(typeof window !== 'undefined' ? window : globalThis);
