/**
 * F12: Settings › Captchas — the CAPTCHA service of the site (reCAPTCHA v2 / v3, hCaptcha, Cloudflare Turnstile) and
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
  function T(k, fb) { try { var t = W.efb_var && W.efb_var.text; return t && t[k] ? t[k] : fb; } catch (e) { return fb; } }
  function esc(s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); }
  function on(v) { return v === true || v === 1 || v === '1' || v === 'true'; }
  function latin(s) { return String(s == null ? '' : s).replace(/[۰-۹]/g, function (d) { return String(d.charCodeAt(0) - 1776); }).replace(/[٠-٩]/g, function (d) { return String(d.charCodeAt(0) - 1632); }).replace(/٫/g, '.'); }
  function rtl() { try { return Number(W.efb_var && W.efb_var.rtl) === 1; } catch (e) { return false; } }
  var PROVIDERS = [['recaptcha_v2', 'reCAPTCHAv2', 'reCAPTCHA v2'], ['recaptcha_v3', 'reCAPTCHAv3', 'Google reCAPTCHA v3'], ['hcaptcha', 'hCaptcha', 'hCaptcha'], ['turnstile', 'turnstile', 'Cloudflare Turnstile']];
  var KEY_LINKS = { recaptcha_v3: 'https://www.google.com/recaptcha/admin/create', hcaptcha: 'https://dashboard.hcaptcha.com/sites', turnstile: 'https://dash.cloudflare.com/?to=/:account/turnstile' };

  function val(s, k, d) { return s && Object.prototype.hasOwnProperty.call(s, k) && s[k] !== null && s[k] !== undefined && s[k] !== 'NotFoundEl' ? s[k] : d; }

  function toggle(id, label, checked, extra, help) {
    return '<div class="efb form-check form-switch efb-spam-row">'
      + '<input class="efb form-check-input" type="checkbox" role="switch" id="' + id + '"' + (checked ? ' checked' : '') + (extra || '') + (help ? ' aria-describedby="' + id + '-help"' : '') + '>'
      + '<label class="efb form-check-label" for="' + id + '">' + esc(label) + '</label>'
      + (help ? '<div class="efb form-text efb-spam-help" id="' + id + '-help">' + help + '</div>' : '') + '</div>';
  }
  function keyPair(prov, s, siteKey, secretKey) {
    var hasSecret = s && s[secretKey] && String(s[secretKey]).length > 5 && s[secretKey] !== 'NotFoundEl';
    var dir = 'dir="ltr"';
    return '<div class="efb efb-spam-keys" data-provider="' + prov + '">'
      + '<label class="efb form-label" for="' + siteKey + '_efb">' + esc(T('siteKey', 'Site Key')) + '</label>'
      + '<input type="text" class="efb form-control efb-spam-input" ' + dir + ' autocomplete="off" spellcheck="false" id="' + siteKey + '_efb" value="' + esc(val(s, siteKey, '')) + '">'
      + '<label class="efb form-label mt-2" for="' + secretKey + '_efb">' + esc(T('SecreTKey', 'Secret Key')) + '</label>'
      + '<div class="efb d-flex gap-2 align-items-center"><input type="password" class="efb form-control efb-spam-input" ' + dir + ' autocomplete="new-password" spellcheck="false" id="' + secretKey + '_efb" value="" data-saved="' + (hasSecret ? '1' : '') + '" placeholder="' + esc(hasSecret ? T('captchaSecretSaved', 'Saved. Type a new key to replace it.') : '') + '">'
      + (hasSecret ? '<button type="button" class="efb btn btn-sm btn-outline-secondary efb-spam-clear" data-for="' + secretKey + '_efb" onclick="efb_spam_clear_secret_efb(this)">' + esc(T('remove', 'Remove')) + '</button>' : '') + '</div>'
      + (KEY_LINKS[prov] ? '<a class="efb d-inline-block mt-1" target="_blank" rel="noopener" href="' + KEY_LINKS[prov] + '">' + esc(T('captchaGetKeys', 'Get the keys')) + '</a>' : '')
      + '</div>';
  }

  /** The sections of the Captchas tab. */
  W.efb_spam_settings_html_efb = function (s, cls) {
    s = s || {};
    var sp = (W.efb_var && W.efb_var.spam) || {};
    var prov = val(s, 'captcha_provider', 'recaptcha_v2');
    var opts = PROVIDERS.map(function (p) { return '<option value="' + p[0] + '"' + (prov === p[0] ? ' selected' : '') + '>' + esc(T(p[1], p[2])) + '</option>'; }).join('');
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
      + '<fieldset class="efb efb-spam-prov" data-for="recaptcha_v3"' + (prov === 'recaptcha_v3' ? '' : ' hidden') + '><legend class="efb fs-6 fw-bold">' + esc(T('reCAPTCHAv3', 'Google reCAPTCHA v3')) + '</legend>'
      + keyPair('recaptcha_v3', s, 'rc3SiteKey', 'rc3SecretKey')
      + '<label class="efb form-label mt-2" for="rc3Threshold_efb">' + esc(T('rc3Threshold', 'Minimum score')) + '</label>'
      + '<input type="text" inputmode="decimal" dir="ltr" class="efb form-control efb-spam-input efb-spam-num" id="rc3Threshold_efb" value="' + esc(thr) + '" aria-describedby="rc3Threshold_efb-help">'
      + '<div class="efb form-text efb-spam-help" id="rc3Threshold_efb-help">' + esc(T('rc3ThresholdHelp', 'Scores run from 0.0 (likely a bot) to 1.0 (likely a person). 0.5 suits most sites.')) + '</div>'
      + '<div class="efb mt-2" role="radiogroup" aria-labelledby="rc3Low_efb-lbl"><span class="efb form-label d-block" id="rc3Low_efb-lbl">' + esc(T('rc3LowAction', 'Low score')) + '</span>'
      + '<div class="efb form-check"><input class="efb form-check-input" type="radio" name="rc3Low_efb" id="rc3Low_spam_efb" value="spam"' + (low !== 'block' ? ' checked' : '') + '><label class="efb form-check-label" for="rc3Low_spam_efb">' + esc(T('markAsSpam', 'Save as spam')) + '</label></div>'
      + '<div class="efb form-check"><input class="efb form-check-input" type="radio" name="rc3Low_efb" id="rc3Low_block_efb" value="block"' + (low === 'block' ? ' checked' : '') + '><label class="efb form-check-label" for="rc3Low_block_efb">' + esc(T('blockSubmission', 'Block the submission')) + '</label></div></div>'
      + '</fieldset>'
      + '<fieldset class="efb efb-spam-prov" data-for="hcaptcha"' + (prov === 'hcaptcha' ? '' : ' hidden') + '><legend class="efb fs-6 fw-bold">' + esc(T('hCaptcha', 'hCaptcha')) + '</legend>' + keyPair('hcaptcha', s, 'hcSiteKey', 'hcSecretKey') + '</fieldset>'
      + '<fieldset class="efb efb-spam-prov" data-for="turnstile"' + (prov === 'turnstile' ? '' : ' hidden') + '><legend class="efb fs-6 fw-bold">' + esc(T('turnstile', 'Cloudflare Turnstile')) + '</legend>' + keyPair('turnstile', s, 'tsSiteKey', 'tsSecretKey') + '</fieldset>'
      + '<p class="efb text-danger efb-spam-err" id="efbSpamErr" role="alert" aria-live="polite"></p>'
      + '</div>'
      + '<h5 class="efb card-title mt-3 mobile-title"><i class="efb bi-funnel m-3" aria-hidden="true"></i>' + esc(T('antiSpamNoCaptcha', 'Spam protection without CAPTCHA')) + '</h5>'
      + '<div class="efb card-body mx-0 py-1 ' + c + '">'
      + toggle('spamHoneypot_efb', T('spamHoneypot', 'Hidden trap field (honeypot)'), on(val(s, 'spam_honeypot', '')), '', esc(T('spamHoneypotHelp', 'A field visitors never see or reach. Bots that fill every field are caught; people are not asked anything.')))
      + '<label class="efb form-label mt-2" for="spamMinTime_efb">' + esc(T('spamMinTime', 'Minimum time to fill (seconds)')) + '</label>'
      + '<input type="text" inputmode="numeric" dir="ltr" class="efb form-control efb-spam-input efb-spam-num" id="spamMinTime_efb" value="' + esc(minT) + '" aria-describedby="spamMinTime_efb-help">'
      + '<div class="efb form-text efb-spam-help" id="spamMinTime_efb-help">' + esc(T('spamMinTimeHelp', 'An answer sent sooner after the visitor starts the form is treated as spam. 0 turns this off.')) + '</div>'
      + toggle('spamRequireJs_efb', T('spamRequireJs', 'Require JavaScript'), on(val(s, 'spam_require_js', '')), '', '<span class="efb text-warning-emphasis">' + esc(T('spamRequireJsWarn', 'Only after checking Request Health: visitors whose firewall blocks the plugin\'s REST route, and pages cached before this version, would be treated as spam.')) + '</span>')
      + '<div class="efb mt-2" role="radiogroup" aria-labelledby="spamAction_efb-lbl" aria-describedby="spamAction_efb-help"><span class="efb form-label d-block" id="spamAction_efb-lbl">' + esc(T('spamAction', 'What to do with spam')) + '</span>'
      + '<div class="efb form-check"><input class="efb form-check-input" type="radio" name="spamAction_efb" id="spamAction_spam_efb" value="spam"' + (act !== 'reject' ? ' checked' : '') + '><label class="efb form-check-label" for="spamAction_spam_efb">' + esc(T('markAsSpam', 'Save as spam')) + '</label></div>'
      + '<div class="efb form-check"><input class="efb form-check-input" type="radio" name="spamAction_efb" id="spamAction_reject_efb" value="reject"' + (act === 'reject' ? ' checked' : '') + '><label class="efb form-check-label" for="spamAction_reject_efb">' + esc(T('spamReject', 'Refuse it (nothing is saved)')) + '</label></div>'
      + '<div class="efb form-text efb-spam-help" id="spamAction_efb-help">' + esc(T('spamActionHelp', 'Payment, registration and login forms always refuse spam.')) + '</div></div>'
      + toggle('spamAkismet_efb', T('spamAkismet', 'Check answers with Akismet'), akOk && on(val(s, 'spam_akismet', '')), akOk ? '' : ' disabled aria-disabled="true"', esc(akOk ? T('spamAkismetPrivacy', 'Akismet sends the answers to Automattic to check them.') : T('spamAkismetMissing', 'Akismet is not installed or active.')))
      + toggle('spamDisallowed_efb', T('spamDisallowed', 'Check WordPress\' disallowed words'), on(val(s, 'spam_disallowed_keys', '')), '', sp.discussion_url ? '<a href="' + esc(sp.discussion_url) + '#disallowed_keys">' + esc(T('spamDisallowedHelp', 'Uses the list in Settings › Discussion › Disallowed Comment Keys.')) + '</a>' : esc(T('spamDisallowedHelp', 'Uses the list in Settings › Discussion › Disallowed Comment Keys.')))
      + '<label class="efb form-label mt-2" for="spamRetention_efb">' + esc(T('spamRetention', 'Delete spam after (days)')) + '</label>'
      + '<input type="text" inputmode="numeric" dir="ltr" class="efb form-control efb-spam-input efb-spam-num" id="spamRetention_efb" value="' + esc(ret) + '" aria-describedby="spamRetention_efb-help">'
      + '<div class="efb form-text efb-spam-help" id="spamRetention_efb-help">' + esc(T('spamRetentionHelp', '0 keeps spam until you delete it.')) + '</div>'
      + '<p class="efb form-text efb-spam-help mt-3">' + esc(T('shieldVsHuman', 'Shield Security is a separate plugin; Human Shield is the security add-on of Easy Form Builder.')) + '</p>'
      + '</div></section>';
  };

  W.efb_spam_provider_changed_efb = function (sel) {
    var root = document.getElementById('efb-spam-settings');
    if (!root) return;
    Array.prototype.forEach.call(root.querySelectorAll('.efb-spam-prov'), function (f) { f.hidden = f.getAttribute('data-for') !== sel.value; });
  };
  W.efb_spam_clear_secret_efb = function (btn) {
    var inp = document.getElementById(btn.getAttribute('data-for'));
    if (!inp) return;
    inp.value = '';
    inp.setAttribute('data-clear', '1');
    inp.setAttribute('data-saved', '');
    inp.placeholder = '';
    btn.disabled = true;
  };

  /** The F12 keys for the settings save; {} without the section (the stored values stay). */
  W.efb_spam_settings_patch_efb = function () {
    var root = typeof document !== 'undefined' ? document.getElementById('efb-spam-settings') : null;
    if (!root) return {};
    var out = {};
    var g = function (id) { return document.getElementById(id); };
    var p = g('captchaProvider_efb');
    if (p && PROVIDERS.some(function (x) { return x[0] === p.value; })) out.captcha_provider = p.value;
    ['rc3SiteKey', 'hcSiteKey', 'tsSiteKey'].forEach(function (k) { var e = g(k + '_efb'); if (e) out[k] = String(e.value || '').trim(); });
    ['rc3SecretKey', 'hcSecretKey', 'tsSecretKey'].forEach(function (k) {
      var e = g(k + '_efb');
      if (!e) return;
      var v = String(e.value || '').trim();
      if (v !== '') out[k] = v; else if (e.getAttribute('data-clear') === '1') out[k] = '';
    });
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

  /** A Turnstile key that does not look like one (0x…, or 1x / 2x / 3x test keys): said before saving. */
  W.efb_spam_settings_check_efb = function () {
    var e = document.getElementById('efbSpamErr');
    var bad = ['tsSiteKey_efb', 'tsSecretKey_efb'].some(function (id) { var x = document.getElementById(id); return x && x.value.trim() !== '' && !/^[0-3]x[A-Za-z0-9_-]{6,98}$/.test(x.value.trim()); });
    if (e) e.textContent = bad ? T('captchaKeyBad', 'This key does not look like a Cloudflare Turnstile key.') : '';
    return !bad;
  };
})();
