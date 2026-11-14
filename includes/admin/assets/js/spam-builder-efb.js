/**
 * F12 in the builder (docs/feature-gaps/F12-spam-core.fa.md, "builder"): the form's CAPTCHA service and its spam
 * settings, in the form settings panel (show_setting_window_efb case 'formSet', under the CAPTCHA switch).
 *
 *  - "CAPTCHA service" (captcha_provider): Site default (now …) or one of the four; a service without both keys in
 *    Settings is offered disabled, "Keys are not set for this service." (efb_var.captcha_ready, from the server);
 *  - "Minimum score" (rc3_threshold) only for reCAPTCHA v3; Persian / Arabic-Indic digits and the Arabic decimal
 *    separator are accepted ('' = the site's score);
 *  - "Turn off spam checks for this form" (spam_off) and "Check this form with Akismet" (spam_akismet "0" = off);
 *  - efbSpamCaptchaReady(row 0): whether the form's service can show (the CAPTCHA switch asks it);
 *  - efbSpamCaptchaPlaceholder(): the preview shows a placeholder with the service's name, never a live widget
 *    (reCAPTCHA v2 keeps its 4.3.1 preview).
 *
 * Every key goes through EFBSpamCore.sanitizeKey (builder-api-efb.js), the twin of \Emsfb\Spam::sanitize_form_key().
 */
(function (root) {
  'use strict';
  // Phrases arrive escaped for HTML (esc_html__): decoded here, escaped once where they are written.
  const dec = (v) => { const s = String(v == null ? '' : v); if (s.indexOf('&') === -1) return s; try { const d = document.createElement('textarea'); d.innerHTML = s; return d.value; } catch (e) { return s; } };
  const T = (k, fb) => { try { if (root.efb_var && root.efb_var.text && root.efb_var.text[k]) return dec(root.efb_var.text[k]); } catch (e) { /* no phrases */ } return fb; };
  const esc = (v) => String(v == null ? '' : v).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#039;');
  const list = () => (typeof valj_efb !== 'undefined' ? valj_efb : []);
  const on = (v) => v === true || v === 1 || v === '1' || v === 'true';
  const NAMES = { recaptcha_v2: ['reCAPTCHAv2', 'reCAPTCHA v2'], recaptcha_v3: ['reCAPTCHAv3', 'Google reCAPTCHA v3'], hcaptcha: ['hCaptcha', 'hCaptcha'], turnstile: ['turnstile', 'Cloudflare Turnstile'] };
  const PROVIDERS = Object.keys(NAMES);
  const name = (p) => (NAMES[p] ? T(NAMES[p][0], NAMES[p][1]) : p);
  const ready = () => (root.efb_var && root.efb_var.captcha_ready) || null;
  const siteProvider = () => { const s = root.efb_var && root.efb_var.spam; return s && PROVIDERS.indexOf(s.site_provider) !== -1 ? s.site_provider : 'recaptcha_v2'; };
  const latin = (s) => String(s == null ? '' : s).replace(/[۰-۹]/g, (d) => String(d.charCodeAt(0) - 1776)).replace(/[٠-٩]/g, (d) => String(d.charCodeAt(0) - 1632)).replace(/٫/g, '.');

  /** The save rule of the four row-0 keys ('' = not set), twin of \Emsfb\Spam::sanitize_form_key(). */
  function sanitizeKey(k, v) {
    if (k === 'spam_akismet' && v === false) return '0'; // "Akismet for this form": off
    const s = v === true ? '1' : v === false ? '' : String(v == null ? '' : v).trim();
    switch (k) {
      case 'captcha_provider': return PROVIDERS.indexOf(s) !== -1 ? s : '';
      case 'rc3_threshold': {
        const n = latin(s).replace(',', '.');
        if (n === '' || !/^-?\d*\.?\d+$/.test(n)) return '';
        return String(Math.round(Math.max(0, Math.min(1, Number(n))) * 100) / 100);
      }
      case 'spam_off': return s === '1' || s === 'true' ? '1' : '';
      case 'spam_akismet': return s === '0' || s === 'false' || s === 'off' ? '0' : '';
    }
    return '';
  }
  root.EFBSpamCore = { sanitizeKey, PROVIDERS, FORM_KEYS: ['captcha_provider', 'rc3_threshold', 'spam_off', 'spam_akismet'] };

  /** The service a form uses (its own, else the site's). */
  function efbSpamProvider(r0) { const p = r0 && r0.captcha_provider; return PROVIDERS.indexOf(p) !== -1 ? p : siteProvider(); }

  /** Whether the form's CAPTCHA can show (both keys of its service in Settings). Old builders: efb_var.captcha. */
  function efbSpamCaptchaReady(r0) {
    const m = ready();
    if (!m) return root.efb_var && (root.efb_var.captcha == 'true' || root.efb_var.captcha === true);
    return !!m[efbSpamProvider(r0 || list()[0] || {})];
  }

  function changed() {
    try { if (typeof sessionStorage !== 'undefined') sessionStorage.setItem('valj_efb', JSON.stringify(valj_efb)); } catch (e) { /* storage off */ }
    try { document.dispatchEvent(new CustomEvent('efb:form-changed', { detail: { source: 'user', action: 'setting', ids: [] } })); } catch (e) { /* old browser */ }
  }

  /** The controls under the CAPTCHA switch (not on a payment form: it has no CAPTCHA). */
  function efbSpamFormEls() {
    const r0 = list()[0] || {};
    const m = ready() || {};
    const own = PROVIDERS.indexOf(r0.captcha_provider) !== -1 ? r0.captcha_provider : '';
    const eff = efbSpamProvider(r0);
    const opt = (v, label, dis) => `<option value="${v}"${own === v ? ' selected' : ''}${dis ? ' disabled' : ''}>${esc(label)}</option>`;
    const site = siteProvider();
    const opts = opt('', T('captchaSiteDefaultNow', 'Site default (now %s)').replace('%s', name(site)), false)
      + PROVIDERS.map((p) => opt(p, name(p) + (m[p] === false ? ' — ' + T('captchaKeyMissing', 'Keys are not set for this service.') : ''), m[p] === false && own !== p)).join('');
    const notReady = ready() && m[eff] === false;
    const pay = r0.type === 'payment';
    const captchaPart = pay ? '' : `
      <label for="captchaProviderEl" class="efb form-label mt-2 mb-1">${esc(T('captchaProvider', 'CAPTCHA service'))}</label>
      <select class="efb elEdit form-select border-d rounded-4 mb-1" id="captchaProviderEl" data-id="formSet" data-key="captcha_provider"${notReady ? ' aria-describedby="captchaProviderEl-help"' : ''}>${opts}</select>
      <small class="efb form-text text-danger d-block fs-8 ${notReady ? '' : 'd-none'}" id="captchaProviderEl-help" role="status">${esc(T('captchaKeyMissing', 'Keys are not set for this service.'))}</small>
      <div class="efb ${eff === 'recaptcha_v3' ? '' : 'd-none'}" id="efbSpamRc3">
        <label for="rc3ThresholdEl" class="efb form-label mt-2 mb-1">${esc(T('rc3Threshold', 'Minimum score'))}</label>
        <input type="text" inputmode="decimal" dir="ltr" class="efb elEdit form-control border-d rounded-4 h-d-efb mb-1" id="rc3ThresholdEl" data-id="formSet" data-key="rc3_threshold" value="${esc(r0.rc3_threshold || '')}" placeholder="${esc(T('siteDefault', 'Site default'))}" aria-describedby="rc3ThresholdEl-help">
        <small class="efb form-text text-muted d-block fs-8" id="rc3ThresholdEl-help">${esc(T('rc3ThresholdHelp', 'Scores run from 0.0 (likely a bot) to 1.0 (likely a person). 0.5 suits most sites.'))}</small>
      </div>`;
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

  /** change_el_edit_Efb for the F12 controls; false when nothing was written. */
  function efbSpamBuilderChange(el) {
    if (!el) return false;
    const L = list();
    if (!L[0]) return false;
    switch (el.id) {
      case 'captchaProviderEl': {
        const v = sanitizeKey('captcha_provider', el.value);
        if (v === '') delete L[0].captcha_provider; else L[0].captcha_provider = v;
        const eff = efbSpamProvider(L[0]);
        const box = document.getElementById('efbSpamRc3');
        if (box) box.classList.toggle('d-none', eff !== 'recaptcha_v3');
        const help = document.getElementById('captchaProviderEl-help');
        const m = ready();
        const notReady = !!(m && m[eff] === false);
        if (help) help.classList.toggle('d-none', !notReady);
        // A service without keys cannot show: the CAPTCHA switch goes off (the form would not be shown).
        if (notReady && on(L[0].captcha)) {
          L[0].captcha = false;
          const sw = document.getElementById('captchaEl');
          if (sw) { sw.classList.remove('active'); sw.setAttribute('aria-pressed', 'false'); }
          try { document.getElementById('dropZoneEFB').classList.remove('captcha'); } catch (e) { /* no canvas */ }
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
    const p = efbSpamProvider(list()[0] || {});
    if (p === 'recaptcha_v2') return '';
    return `<div class="efb row mx-0"><div class="efb efb-captcha-ph my-2" role="img" aria-label="${esc(T('captchaLabel', 'Security check'))}"><i class="efb bi-shield-check mx-1" aria-hidden="true"></i><span dir="auto">${esc(T('captchaPreview', '%s will show here on the page.').replace('%s', name(p)))}</span></div></div>`;
  }

  const api = { efbSpamFormEls, efbSpamBuilderChange, efbSpamCaptchaReady, efbSpamProvider, efbSpamCaptchaPlaceholder };
  Object.keys(api).forEach((k) => { root[k] = api[k]; });
})(typeof window !== 'undefined' ? window : globalThis);
