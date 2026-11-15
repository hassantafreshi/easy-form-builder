/**
 * F12 / F12b: Settings › Captchas — the CAPTCHA service of the site (Google reCAPTCHA in core, every CAPTCHA add-on) and
 * spam protection without a CAPTCHA (hidden trap field, minimum time, JavaScript, what to do with spam, Akismet,
 * WordPress' disallowed words, how long spam is kept). docs/feature-gaps/F12-spam-core.fa.md.
 *
 * efb_spam_settings_html_efb(settings, cls) draws the sections (list_form-efb.js puts them in the Captchas tab);
 * efb_spam_settings_patch_efb() reads them for the settings save ({} when the section is not on the screen, so the
 * stored values stay; Admin::set_setting_Emsfb allow-lists every value again). Numbers may be typed in Persian or
 * Arabic-Indic digits. A secret key already saved is not shown: its box stays empty ("Saved. Type a new key to
 * replace it.") and an empty box keeps it; "Remove" empties it.
 */
(function () {
  'use strict';
  var W = typeof window !== 'undefined' ? window : globalThis;
  /* Phrases arrive escaped for HTML (esc_html__): decoded here, escaped once where they are written. */
  function dec(v) { var s = String(v == null ? '' : v); if (s.indexOf('&') === -1) return s; try { var d = document.createElement('textarea'); d.innerHTML = s; return d.value; } catch (e) { return s; } }
  function T(k, fb) { try { var t = W.efb_var && W.efb_var.text; return t && t[k] ? dec(t[k]) : fb; } catch (e) { return fb; } }
  function esc(s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); }
  function on(v) { return v === true || v === 1 || v === '1' || v === 'true'; }
  function latin(s) { return String(s == null ? '' : s).replace(/[۰-۹]/g, function (d) { return String(d.charCodeAt(0) - 1776); }).replace(/[٠-٩]/g, function (d) { return String(d.charCodeAt(0) - 1632); }).replace(/٫/g, '.'); }
  function rtl() { try { return Number(W.efb_var && W.efb_var.rtl) === 1; } catch (e) { return false; } }
  var BUILTIN = [['recaptcha_v2', 'reCAPTCHAv2', 'reCAPTCHA v2'], ['recaptcha_v2i', 'reCAPTCHAv2i', 'Google reCAPTCHA v2 (invisible)'], ['recaptcha_v3', 'reCAPTCHAv3', 'Google reCAPTCHA v3']];
  var KEY_LINKS = { recaptcha_v2i: 'https://www.google.com/recaptcha/admin/create', recaptcha_v3: 'https://www.google.com/recaptcha/admin/create' };
  /* F12b: every provider of the registry (efb_var.captcha_providers): [id, phrase key, label, entry]. */
  function catalog() { var c = W.efb_var && W.efb_var.captcha_providers; return Array.isArray(c) ? c : []; }
  function providers() {
    var cat = catalog(), out = [];
    if (!cat.length) return BUILTIN.map(function (b) { return [b[0], b[1], b[2], null]; });
    cat.forEach(function (e) { var b = BUILTIN.filter(function (x) { return x[0] === e.id; })[0]; out.push([e.id, b ? b[1] : '', b ? b[2] : e.label, e]); });
    return out;
  }
  var PROVIDERS = providers();
  /* D4: a saved secret is never sent to this page: "Saved (•••• 1234)", an empty box keeps it, Remove clears it. */
  function savedMask(v) {
    if (!v) return '';
    if (typeof v === 'object') return v.last4 ? '•••• ' + v.last4 : '••••';
    return String(v).length > 5 && v !== 'NotFoundEl' ? '••••' : '';
  }
  function secretBox(id, saved, attrs) {
    var m = savedMask(saved);
    return '<div class="efb d-flex gap-2 align-items-center flex-wrap"><input type="password" class="efb form-control efb-spam-input efb-secret-input" dir="ltr" autocomplete="new-password" spellcheck="false" id="' + id + '" value="" data-secret="1" data-saved="' + (m ? '1' : '') + '" placeholder="' + esc(m ? T('secretSavedMask', 'Saved (%s)').replace('%s', m) : '') + '" aria-describedby="' + id + '-keep"' + (attrs || '') + '>'
      + (m ? '<button type="button" class="efb btn btn-sm btn-outline-secondary efb-spam-clear" data-for="' + id + '" onclick="efb_spam_clear_secret_efb(this)">' + esc(T('secretRemove', 'Remove')) + '</button>' : '') + '</div>'
      + '<div class="efb form-text efb-spam-help" id="' + id + '-keep" dir="auto">' + esc(m ? T('secretKeep', 'Leave empty to keep the saved key.') : '') + '</div>';
  }
  W.efb_secret_box_efb = secretBox;
  /** A secret box's value for a save: '' keeps the stored one, '__clear__' removes it, else the new key. */
  W.efb_secret_value_efb = function (id) {
    var e = document.getElementById(id);
    if (!e) return undefined;
    var v = String(e.value || '').trim();
    if (v !== '') return v;
    return e.getAttribute('data-clear') === '1' ? '__clear__' : '';
  };

  function val(s, k, d) { return s && Object.prototype.hasOwnProperty.call(s, k) && s[k] !== null && s[k] !== undefined && s[k] !== 'NotFoundEl' ? s[k] : d; }

  function toggle(id, label, checked, extra, help) {
    return '<div class="efb efb-spam-row">'
      + '<div class="efb efb-spam-line"><input class="efb form-check-input efb-spam-check" type="checkbox" role="switch" id="' + id + '"' + (checked ? ' checked' : '') + (extra || '') + (help ? ' aria-describedby="' + id + '-help"' : '') + '>'
      + '<label class="efb efb-spam-lbl" for="' + id + '">' + esc(label) + '</label></div>'
      + (help ? '<div class="efb form-text efb-spam-help" id="' + id + '-help">' + help + '</div>' : '') + '</div>';
  }
  function radio(name, id, value, checked, label) {
    return '<div class="efb efb-spam-line"><input class="efb form-check-input efb-spam-check" type="radio" name="' + name + '" id="' + id + '" value="' + value + '"' + (checked ? ' checked' : '') + '><label class="efb efb-spam-lbl" for="' + id + '">' + esc(label) + '</label></div>';
  }
  function keyPair(prov, s, siteKey, secretKey) {
    var dir = 'dir="ltr"';
    return '<div class="efb efb-spam-keys" data-provider="' + prov + '">'
      + '<label class="efb form-label" for="' + siteKey + '_efb">' + esc(T('siteKey', 'Site Key')) + '</label>'
      + '<input type="text" class="efb form-control efb-spam-input" ' + dir + ' autocomplete="off" spellcheck="false" id="' + siteKey + '_efb" value="' + esc(val(s, siteKey, '')) + '">'
      + '<label class="efb form-label mt-2" for="' + secretKey + '_efb">' + esc(T('SecreTKey', 'Secret Key')) + '</label>'
      + secretBox(secretKey + '_efb', s ? s[secretKey] : '')
      + (KEY_LINKS[prov] ? '<a class="efb d-inline-block mt-1" target="_blank" rel="noopener" href="' + KEY_LINKS[prov] + '">' + esc(T('captchaGetKeys', 'Get the keys')) + '</a>' : '')
      + '</div>';
  }
  /* F12b: the settings of an add-on provider from its schema, saved with its own button (efb_captcha_provider_save). */
  function addonSet(e) {
    var id = e.id;
    var f = (e.settings || []).map(function (x) {
      var fid = 'cpv_' + id + '_' + x.key;
      var lab = '<label class="efb form-label mt-2" for="' + fid + '">' + esc(T(x.label_key, x.label || x.key)) + '</label>';
      var help = x.help_key || x.help ? '<div class="efb form-text efb-spam-help" dir="auto" style="unicode-bidi:plaintext">' + esc(T(x.help_key, x.help || '')) + '</div>' : '';
      if (x.type === 'secret') return lab + secretBox(fid, x.saved, ' data-cpv="' + esc(id) + '" data-key="' + esc(x.key) + '"') + help;
      var v = x.value == null ? '' : String(x.value);
      var at = ' id="' + fid + '" data-cpv="' + esc(id) + '" data-key="' + esc(x.key) + '"';
      if (x.type === 'toggle') return '<div class="efb efb-spam-line mt-2"><input class="efb form-check-input efb-spam-check" type="checkbox" role="switch"' + at + (v === '1' ? ' checked' : '') + '><label class="efb efb-spam-lbl" for="' + fid + '">' + esc(T(x.label_key, x.label || x.key)) + '</label></div>' + help;
      if (x.type === 'select') return lab + '<select class="efb form-select efb-spam-input"' + at + '>' + (x.values || []).map(function (o) { return '<option value="' + esc(o) + '"' + (String(o) === v ? ' selected' : '') + '>' + esc(T('cpv_' + o, o)) + '</option>'; }).join('') + '</select>' + help;
      if (x.type === 'textarea') return lab + '<textarea class="efb form-control efb-spam-input" rows="4" dir="auto"' + at + '>' + esc(v) + '</textarea>' + help;
      return lab + '<input type="text" class="efb form-control efb-spam-input" ' + (x.type === 'number' ? 'inputmode="numeric" ' : '') + 'dir="' + (x.type === 'number' || x.public ? 'ltr' : 'auto') + '" autocomplete="off" spellcheck="false" value="' + esc(v) + '"' + at + '>' + help;
    }).join('');
    var pv = e.privacy && e.privacy.where ? '<p class="efb form-text efb-spam-help" dir="auto" style="unicode-bidi:plaintext">' + esc(T('cpPrivacyWhere', 'Data goes to: %s').replace('%s', e.privacy.where)) + '</p>' : '';
    var state = !e.available ? '<p class="efb text-warning-emphasis" role="status">' + esc(e.state === 'plan' ? T('cpPlanNeeded', 'Available on Free Plus and Pro') : T('cpUpdateAddonShort', 'Update or turn on the add-on')) + '</p>' : '';
    return '<details class="efb efb-cpv mt-2" data-for="' + esc(id) + '"' + (e.ready ? '' : ' open') + '><summary class="efb fs-6 fw-bold">' + esc(e.label) + (e.ready ? '' : ' — ' + esc(T('captchaKeyMissing', 'Keys are not set for this service.'))) + '</summary>'
      + state + pv + f
      + '<button type="button" class="efb btn btn-sm btn-pinkEfb mt-2" data-cpv-save="' + esc(id) + '" onclick="efb_cpv_save_efb(this)">' + esc(T('save', 'Save')) + '</button>'
      + '<span class="efb ms-2 efb-cpv-status" role="status" aria-live="polite" id="cpv_' + esc(id) + '_status"></span></details>';
  }
  W.efb_cpv_save_efb = function (btn) {
    var id = btn.getAttribute('data-cpv-save');
    var pub = {}, sec = {};
    Array.prototype.forEach.call(document.querySelectorAll('[data-cpv="' + id + '"]'), function (x) {
      var k = x.getAttribute('data-key');
      if (x.getAttribute('data-secret') === '1') sec[k] = W.efb_secret_value_efb(x.id);
      else pub[k] = x.type === 'checkbox' ? (x.checked ? '1' : '') : x.value;
    });
    var st = document.getElementById('cpv_' + id + '_status');
    var fd = new FormData();
    fd.append('action', 'efb_captcha_provider_save'); fd.append('id', id); fd.append('public', JSON.stringify(pub)); fd.append('secret', JSON.stringify(sec));
    fd.append('nonce', (W.efb_var && W.efb_var.nonce) || '');
    btn.disabled = true;
    return fetch((W.ajaxurl || (W.efb_var && W.efb_var.ajax_url) || '/wp-admin/admin-ajax.php'), { method: 'POST', body: fd, credentials: 'same-origin' }).then(function (r) { return r.json(); }).then(function (j) {
      btn.disabled = false;
      var ok = j && j.success;
      if (st) st.textContent = ok ? (j.data.refused && j.data.refused.length ? T('cpvRefused', 'Not saved: %s').replace('%s', j.data.refused.join(', ')) : T('saved', 'Saved')) : T('error', 'Error');
      if (ok && j.data.provider && W.efb_var && Array.isArray(W.efb_var.captcha_providers)) {
        W.efb_var.captcha_providers = W.efb_var.captcha_providers.map(function (e) { return e.id === id ? j.data.provider : e; });
        Array.prototype.forEach.call(document.querySelectorAll('[data-cpv="' + id + '"][data-secret="1"]'), function (x) { x.value = ''; x.removeAttribute('data-clear'); });
      }
      return ok;
    }).catch(function () { btn.disabled = false; if (st) st.textContent = T('error', 'Error'); return false; });
  };

  /** The sections of the Captchas tab. */
  W.efb_spam_settings_html_efb = function (s, cls) {
    s = s || {};
    var sp = (W.efb_var && W.efb_var.spam) || {};
    var prov = val(s, 'captcha_provider', 'recaptcha_v2');
    PROVIDERS = providers();
    var opts = PROVIDERS.map(function (p) { var e = p[3], off = e && (!e.installed || !e.available); return '<option value="' + esc(p[0]) + '"' + (prov === p[0] ? ' selected' : '') + (off && prov !== p[0] ? ' disabled' : '') + '>' + esc(p[1] ? T(p[1], p[2]) : p[2]) + (e && !e.installed ? ' — ' + esc(T('cpNotInstalled', 'Not installed')) : (off ? ' — ' + esc(T('cpPlanNeeded', 'Available on Free Plus and Pro')) : '')) + '</option>'; }).join('');
    var host = val(s, 'rc_host', 'google.com');
    var addons = catalog().filter(function (e) { return e.installed && e.addon; });
    var thr = String(val(s, 'rc3Threshold', '0.5'));
    var low = val(s, 'rc3_low_action', 'spam');
    var act = val(s, 'spam_action', 'spam');
    var akOk = !!sp.akismet;
    var ret = String(val(s, 'spam_retention_days', '0'));
    var minT = String(val(s, 'spam_min_time', '0'));
    var c = cls || '';
    var d = rtl() ? ' dir="rtl"' : '';
    return '<section class="efb efb-spam-settings" id="efb-spam-settings"' + d + ' aria-labelledby="efbSpamCapTitle">'
      + '<h5 class="efb card-title mt-3 mobile-title" id="efbSpamCapTitle"><i class="efb bi-ui-checks m-3" aria-hidden="true"></i>' + esc(T('captchaProvider', 'CAPTCHA service')) + '</h5>'
      + '<div class="efb card-body mx-0 py-1 ' + c + '">'
      + '<label class="efb form-label" for="captchaProvider_efb">' + esc(T('captchaProvider', 'CAPTCHA service')) + '</label>'
      + '<select class="efb form-select efb-spam-input" id="captchaProvider_efb" onchange="efb_spam_provider_changed_efb(this)">' + opts + '</select>'
      + '<p class="efb form-text efb-spam-help">' + esc(T('purgeCacheAfterKeys', 'Clear the page cache of pages that show a form in a widget after you change CAPTCHA keys.')) + '</p>'
      + '<label class="efb form-label mt-2" for="rcHost_efb">' + esc(T('rcHost', 'Load reCAPTCHA from')) + '</label>'
      + '<select class="efb form-select efb-spam-input" id="rcHost_efb" dir="ltr" aria-describedby="rcHost_efb-help"><option value="google.com"' + (host !== 'recaptcha.net' ? ' selected' : '') + '>google.com</option><option value="recaptcha.net"' + (host === 'recaptcha.net' ? ' selected' : '') + '>recaptcha.net</option></select>'
      + '<div class="efb form-text efb-spam-help" id="rcHost_efb-help">' + esc(T('rcHostHelp', 'Use recaptcha.net where google.com is blocked.')) + '</div>'
      + toggle('captchaLogin_efb', T('loginCaptchaOn', 'Also check the CAPTCHA on login and registration'), val(s, 'captcha_login', '1') === '1' || val(s, 'captcha_login', '1') === true, '', '')
      + '<fieldset class="efb efb-spam-prov" data-for="recaptcha_v2i"><legend class="efb fs-6 fw-bold">' + esc(T('reCAPTCHAv2i', 'Google reCAPTCHA v2 (invisible)')) + '</legend>'
      + keyPair('recaptcha_v2i', s, 'rciSiteKey', 'rciSecretKey') + '</fieldset>'
      + '<fieldset class="efb efb-spam-prov" data-for="recaptcha_v3"><legend class="efb fs-6 fw-bold">' + esc(T('reCAPTCHAv3', 'Google reCAPTCHA v3')) + '</legend>'
      + keyPair('recaptcha_v3', s, 'rc3SiteKey', 'rc3SecretKey')
      + '<label class="efb form-label mt-2" for="rc3Threshold_efb">' + esc(T('rc3Threshold', 'Minimum score')) + '</label>'
      + '<input type="text" inputmode="decimal" dir="ltr" class="efb form-control efb-spam-input efb-spam-num" id="rc3Threshold_efb" value="' + esc(thr) + '" aria-describedby="rc3Threshold_efb-help">'
      + '<div class="efb form-text efb-spam-help" id="rc3Threshold_efb-help">' + esc(T('rc3ThresholdHelp', 'Scores run from 0.0 (likely a bot) to 1.0 (likely a person). 0.5 suits most sites.')) + '</div>'
      + '<div class="efb mt-2" role="radiogroup" aria-labelledby="rc3Low_efb-lbl"><span class="efb form-label d-block" id="rc3Low_efb-lbl">' + esc(T('rc3LowAction', 'Low score')) + '</span>'
      + radio('rc3Low_efb', 'rc3Low_spam_efb', 'spam', low !== 'block', T('markAsSpam', 'Save as spam'))
      + radio('rc3Low_efb', 'rc3Low_block_efb', 'block', low === 'block', T('blockSubmission', 'Block the submission')) + '</div>'
      + '</fieldset>'
      + '<p class="efb text-danger efb-spam-err" id="efbSpamErr" role="alert" aria-live="polite"></p>'
      + (addons.length ? '<h6 class="efb mt-3 fw-bold">' + esc(T('cpAddonProviders', 'CAPTCHA add-ons')) + '</h6>' + addons.map(addonSet).join('') : '')
      + '</div>'
      + '<h5 class="efb card-title mt-3 mobile-title"><i class="efb bi-funnel m-3" aria-hidden="true"></i>' + esc(T('antiSpamNoCaptcha', 'Spam protection without CAPTCHA')) + '</h5>'
      + '<div class="efb card-body mx-0 py-1 ' + c + '">'
      + toggle('spamHoneypot_efb', T('spamHoneypot', 'Hidden trap field (honeypot)'), on(val(s, 'spam_honeypot', '')), '', esc(T('spamHoneypotHelp', 'A field visitors never see or reach. Bots that fill every field are caught; people are not asked anything.')))
      + '<label class="efb form-label mt-2" for="spamMinTime_efb">' + esc(T('spamMinTime', 'Minimum time to fill (seconds)')) + '</label>'
      + '<input type="text" inputmode="numeric" dir="ltr" class="efb form-control efb-spam-input efb-spam-num" id="spamMinTime_efb" value="' + esc(minT) + '" aria-describedby="spamMinTime_efb-help">'
      + '<div class="efb form-text efb-spam-help" id="spamMinTime_efb-help">' + esc(T('spamMinTimeHelp', 'An answer sent sooner after the visitor starts the form is treated as spam. 0 turns this off.')) + '</div>'
      + toggle('spamRequireJs_efb', T('spamRequireJs', 'Require JavaScript'), on(val(s, 'spam_require_js', '')), '', '<span class="efb text-warning-emphasis">' + esc(T('spamRequireJsWarn', 'Only after checking Request Health: visitors whose firewall blocks the plugin\'s REST route, and pages cached before this version, would be treated as spam.')) + '</span>')
      + '<div class="efb mt-2" role="radiogroup" aria-labelledby="spamAction_efb-lbl" aria-describedby="spamAction_efb-help"><span class="efb form-label d-block" id="spamAction_efb-lbl">' + esc(T('spamAction', 'What to do with spam')) + '</span>'
      + radio('spamAction_efb', 'spamAction_spam_efb', 'spam', act !== 'reject', T('markAsSpam', 'Save as spam'))
      + radio('spamAction_efb', 'spamAction_reject_efb', 'reject', act === 'reject', T('spamReject', 'Refuse it (nothing is saved)'))
      + '<div class="efb form-text efb-spam-help" id="spamAction_efb-help">' + esc(T('spamActionHelp', 'Payment, registration and login forms always refuse spam.')) + '</div></div>'
      + toggle('spamAkismet_efb', T('spamAkismet', 'Check answers with Akismet'), akOk && on(val(s, 'spam_akismet', '')), akOk ? '' : ' disabled aria-disabled="true"', esc(akOk ? T('spamAkismetPrivacy', 'Akismet sends the answers to Automattic to check them.') : T('spamAkismetMissing', 'Akismet is not installed or active.')))
      + toggle('spamDisallowed_efb', T('spamDisallowed', 'Check WordPress\' disallowed words'), on(val(s, 'spam_disallowed_keys', '')), '', sp.discussion_url ? '<a href="' + esc(sp.discussion_url) + '#disallowed_keys">' + esc(T('spamDisallowedHelp', 'Uses the list in Settings › Discussion › Disallowed Comment Keys.')) + '</a>' : esc(T('spamDisallowedHelp', 'Uses the list in Settings › Discussion › Disallowed Comment Keys.')))
      + '<label class="efb form-label mt-2" for="spamRetention_efb">' + esc(T('spamRetention', 'Delete spam after (days)')) + '</label>'
      + '<input type="text" inputmode="numeric" dir="ltr" class="efb form-control efb-spam-input efb-spam-num" id="spamRetention_efb" value="' + esc(ret) + '" aria-describedby="spamRetention_efb-help">'
      + '<div class="efb form-text efb-spam-help" id="spamRetention_efb-help">' + esc(T('spamRetentionHelp', '0 keeps spam until you delete it.')) + '</div>'
      + '<p class="efb form-text efb-spam-help mt-3">' + esc(T('shieldVsHuman', 'Shield Security is a separate plugin; Human Shield is the security add-on of Easy Form Builder.')) + '</p>'
      + '</div></section>';
  };

  /* F12b: every form may choose its own service, so the keys of every service stay on the screen (F12 showed the
     site's only); the site's choice only marks its fieldset. */
  W.efb_spam_provider_changed_efb = function (sel) {
    var root = document.getElementById('efb-spam-settings');
    if (!root) return;
    Array.prototype.forEach.call(root.querySelectorAll('.efb-spam-prov'), function (f) { f.classList.toggle('efb-spam-prov-site', f.getAttribute('data-for') === sel.value); });
  };
  W.efb_spam_clear_secret_efb = function (btn) {
    var inp = document.getElementById(btn.getAttribute('data-for'));
    if (!inp) return;
    if (!W.confirm || W.confirm(T('secretRemoveConfirm', 'Remove the saved key? The forms that use it lose this check until you add a new one.'))) {
      inp.value = '';
      inp.setAttribute('data-clear', '1');
      inp.setAttribute('data-saved', '');
      inp.placeholder = '';
      btn.disabled = true;
    }
  };

  /** The F12 keys for the settings save; {} without the section (the stored values stay). */
  W.efb_spam_settings_patch_efb = function () {
    var root = typeof document !== 'undefined' ? document.getElementById('efb-spam-settings') : null;
    if (!root) return {};
    var out = {};
    var g = function (id) { return document.getElementById(id); };
    var p = g('captchaProvider_efb');
    if (p && /^[a-z][a-z0-9_]{2,23}$/.test(p.value)) out.captcha_provider = p.value;
    ['rc3SiteKey', 'rciSiteKey'].forEach(function (k) { var e = g(k + '_efb'); if (e) out[k] = String(e.value || '').trim(); });
    // D4: '' keeps the stored secret on the server, '__clear__' removes it.
    ['rc3SecretKey', 'rciSecretKey'].forEach(function (k) { var v = W.efb_secret_value_efb(k + '_efb'); if (v !== undefined) out[k] = v; });
    var rh = g('rcHost_efb'); if (rh) out.rc_host = rh.value === 'recaptcha.net' ? 'recaptcha.net' : 'google.com';
    var cl = g('captchaLogin_efb'); if (cl) out.captcha_login = cl.checked ? '1' : '';
    var thr = g('rc3Threshold_efb');
    if (thr) out.rc3Threshold = latin(thr.value).replace(',', '.').trim() || '0.5';
    var low = root.querySelector('input[name="rc3Low_efb"]:checked');
    if (low) out.rc3_low_action = low.value === 'block' ? 'block' : 'spam';
    var hp = g('spamHoneypot_efb'); if (hp) out.spam_honeypot = hp.checked ? '1' : '';
    var mt = g('spamMinTime_efb'); if (mt) out.spam_min_time = String(Math.min(60, Number(latin(mt.value).replace(/[^0-9]/g, '')) || 0));
    var js = g('spamRequireJs_efb'); if (js) out.spam_require_js = js.checked ? '1' : '';
    var act = root.querySelector('input[name="spamAction_efb"]:checked'); if (act) out.spam_action = act.value === 'reject' ? 'reject' : 'spam';
    var ak = g('spamAkismet_efb'); if (ak && !ak.disabled) out.spam_akismet = ak.checked ? '1' : '';
    var dk = g('spamDisallowed_efb'); if (dk) out.spam_disallowed_keys = dk.checked ? '1' : '';
    var rt = g('spamRetention_efb'); if (rt) out.spam_retention_days = String(Math.min(3650, Number(latin(rt.value).replace(/[^0-9]/g, '')) || 0));
    return out;
  };

  /** F12b: every key of a service is checked by the server (its schema) when saved; nothing to say before. */
  W.efb_spam_settings_check_efb = function () { return true; };
})();
