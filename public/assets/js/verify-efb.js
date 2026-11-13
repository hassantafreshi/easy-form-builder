/**
 * F02 in the visitor's page: one-time codes for a mobile number or an e-mail address, and the
 * "type it twice" e-mail box. Spec: docs/feature-gaps/F02-verification-otp.fa.md (§3).
 *
 * UMD: window.EFBVerify in the page, require() in Node (the pure helpers are tested there; the
 * server twins are in includes/class-Emsfb-verification.php).
 *
 * State of a field (§3.2): idle → sending → code_sent → verifying → verified; expired and error
 * can go back to sending. The token is kept in memory only (never in web storage, never in the
 * answers), bound to the value it was issued for: a changed value drops it. The server decides
 * in any case (Verification::validate_submission()).
 *
 * Mixed scripts (an English sentence on a Persian page, a Persian page with a +98 number): the
 * status lines use unicode-bidi: plaintext (CSS) and every inserted value is a <bdi>.
 */
(function (root, factory) {
  if (typeof module === 'object' && module.exports) module.exports = factory(root || {});
  else root.EFBVerify = factory(root);
}(typeof window !== 'undefined' ? window : this, function (root) {
  'use strict';

  /* ================================================================ pure helpers */

  const EMAIL_MODES = ['none', 'retype', 'otp', 'retype_otp'];
  const STATES = ['idle', 'sending', 'code_sent', 'verifying', 'verified', 'expired', 'error'];
  const INVISIBLE = /[ ؜​-‏‪-‮⁠-⁩﻿]/g;

  function asciiDigits(s) {
    return String(s == null ? '' : s)
      .replace(/[۰-۹]/g, (d) => String(d.charCodeAt(0) - 0x06F0))
      .replace(/[٠-٩]/g, (d) => String(d.charCodeAt(0) - 0x0660));
  }
  /** Only the digits of a typed or pasted code ("کد شما: ۱۲۳۴۵۶" → "123456"), at most len. */
  function codeDigits(s, len) {
    const d = asciiDigits(s).replace(/[^0-9]/g, '');
    return len ? d.slice(0, len) : d;
  }
  /** Verification::normalize_destination('sms'): E.164 or null. */
  function normPhone(raw) {
    let v = asciiDigits(raw).replace(INVISIBLE, '').trim();
    let plus = v.indexOf('+') === 0;
    if (!plus && v.indexOf('00') === 0) { v = v.slice(2); plus = true; }
    v = v.replace(/[^0-9]/g, '');
    if (!plus) return null;
    v = '+' + v;
    return /^\+[1-9][0-9]{7,14}$/.test(v) ? v : null;
  }
  /** Verification::normalize_destination('email'): the submit handler's normalization, lower case; null when invalid. */
  function normEmail(raw) {
    let v = asciiDigits(raw).replace(/＠/g, '@').replace(/[．。٫۔]/g, '.').replace(INVISIBLE, '').trim().toLowerCase();
    if (v.length > 254) return null;
    return /^[^\s@<>()",;:\\[\]]+@[a-z0-9¡-￿](?:[a-z0-9¡-￿-]*[a-z0-9¡-￿])?(?:\.[a-z0-9¡-￿](?:[a-z0-9¡-￿-]*[a-z0-9¡-￿])?)+$/.test(v) ? v : null;
  }
  function normDest(channel, raw) {
    return channel === 'sms' ? normPhone(raw) : (channel === 'email' ? normEmail(raw) : null);
  }
  /** ASCII digits in the page language's digits (fa: ۰-۹, ar: ٠-٩). */
  function localDigits(s, locale) {
    const l = String(locale || '').toLowerCase();
    if (l.indexOf('fa') === 0) return String(s).replace(/[0-9]/g, (d) => String.fromCharCode(0x06F0 + Number(d)));
    if (l === 'ar' || l.indexOf('ar_') === 0 || l.indexOf('ar-') === 0 || l === 'ary') return String(s).replace(/[0-9]/g, (d) => String.fromCharCode(0x0660 + Number(d)));
    return String(s);
  }
  /** 59 → "0:59", 125 → "2:05" (Verification::duration()). */
  function fmtTime(sec) {
    sec = Math.max(0, Math.floor(Number(sec) || 0));
    if (sec >= 3600) return Math.floor(sec / 3600) + ':' + String(Math.floor((sec % 3600) / 60)).padStart(2, '0') + ':' + String(sec % 60).padStart(2, '0');
    return Math.floor(sec / 60) + ':' + String(sec % 60).padStart(2, '0');
  }
  /** Splits a phrase with %s / %1$s / %2$s into text parts and value indexes: [{t:'…'}, {v:0}, …]. */
  function parts(tpl) {
    const out = [];
    const re = /%(\d)\$s|%s/g;
    let last = 0; let auto = 0; let m;
    tpl = String(tpl == null ? '' : tpl);
    while ((m = re.exec(tpl))) {
      if (m.index > last) out.push({ t: tpl.slice(last, m.index) });
      out.push({ v: m[1] ? Number(m[1]) - 1 : auto++ });
      last = m.index + m[0].length;
    }
    if (last < tpl.length) out.push({ t: tpl.slice(last) });
    return out;
  }
  /** The plain text of a phrase with its values isolated (FSI … PDI), for aria and tests. */
  function fill(tpl, vals) {
    return parts(tpl).map((p) => (p.t !== undefined ? p.t : '⁨' + String(vals[p.v] == null ? '' : vals[p.v]) + '⁩')).join('');
  }
  function iso(v) {
    const list = Array.isArray(v) ? v : String(v == null ? '' : v).split(/[\s,]+/);
    const out = [];
    list.forEach((c) => { c = String(c).trim().toLowerCase(); if (/^[a-z]{2}$/.test(c) && out.indexOf(c) === -1) out.push(c); });
    return out;
  }
  function on(v) { return v === 1 || v === '1' || v === true || v === 'true'; }
  function plain(v, max) {
    if (v == null || typeof v === 'object') return '';
    return String(v).replace(/<[^>]*>/g, '').replace(/["<>]/g, '').replace(/\s+/g, ' ').trim().slice(0, max);
  }
  /** Verification::sanitize_key() (save rule of one field key; iso list checked against the server's table there). */
  function sanitizeKey(k, v) {
    switch (k) {
      case 'otp_enabled': return on(typeof v === 'string' ? v.trim().toLowerCase() : v) ? '1' : '0';
      case 'otp_channel': return 'sms';
      case 'otp_required_when':
      case 'email_otp_required_when': return v === 'always' ? 'always' : 'filled';
      case 'email_confirm_mode': return EMAIL_MODES.indexOf(v) !== -1 ? v : 'none';
      case 'otp_countries': return iso(v).slice(0, 60);
      case 'otp_btn_label':
      case 'email_retype_label': return plain(v, 60);
      case 'email_otp_subject': return plain(v, 150);
      case 'email_otp_body': {
        if (v == null || typeof v === 'object') return '';
        let t = String(v).replace(/\r\n|\r|\n/g, '@n#').replace(/<[^>]*>/g, '').replace(/["<>]/g, '').replace(/[ \t]+/g, ' ');
        return t.slice(0, 1000).trim();
      }
    }
    return '';
  }
  function emailMode(f) {
    const m = f && typeof f.email_confirm_mode === 'string' ? f.email_confirm_mode : 'none';
    return EMAIL_MODES.indexOf(m) !== -1 ? m : 'none';
  }
  /** Verification::field_spec(). */
  function fieldSpec(f) {
    if (!f || typeof f !== 'object') return null;
    if (f.type === 'mobile' && on(f.otp_enabled)) return { channel: 'sms', otp: true, retype: false, when: f.otp_required_when === 'always' ? 'always' : 'filled' };
    if (f.type === 'email' && emailMode(f) !== 'none') {
      const m = emailMode(f);
      return { channel: 'email', otp: m === 'otp' || m === 'retype_otp', retype: m === 'retype' || m === 'retype_otp', when: f.email_otp_required_when === 'always' ? 'always' : 'filled' };
    }
    return null;
  }
  /** The state machine of §3.2 (pure): the next state of a field, or the same one when the event does not apply. */
  function reduce(state, ev) {
    switch (ev) {
      case 'send': return ['idle', 'expired', 'error', 'code_sent'].indexOf(state) !== -1 ? 'sending' : state;
      case 'sent': return state === 'sending' ? 'code_sent' : state;
      case 'send_failed': return state === 'sending' ? 'error' : state;
      case 'check': return state === 'code_sent' ? 'verifying' : state;
      case 'wrong': return state === 'verifying' ? 'code_sent' : state;
      case 'ok': return state === 'verifying' ? 'verified' : state;
      case 'locked': return 'error';
      case 'expire': return ['code_sent', 'verifying', 'verified'].indexOf(state) !== -1 ? 'expired' : state;
      case 'edit':
      case 'value_changed': return 'idle';
    }
    return state;
  }

  const api = { asciiDigits, codeDigits, normPhone, normEmail, normDest, localDigits, fmtTime, parts, fill, sanitizeKey, fieldSpec, emailMode, reduce, STATES, EMAIL_MODES };
  if (typeof document === 'undefined') return api;

  /* ================================================================ page runtime */

  const ctrls = {}; // "fid|vid" → control
  const T = (k, fb) => {
    const sources = [root.efb_var && root.efb_var.text, root.ajax_object_efm && root.ajax_object_efm.text];
    for (const s of sources) { if (s && typeof s[k] === 'string' && s[k] !== '') return s[k]; }
    return fb;
  };
  const locale = () => (root.efb_var && (root.efb_var.wp_lan || root.efb_var.language)) || (root.ajax_object_efm && root.ajax_object_efm.language) || document.documentElement.lang || 'en';
  const restUrl = () => (root.efb_request_health && root.efb_request_health.rest_url) || ((root.efb_var && root.efb_var.rest_url) ? root.efb_var.rest_url + 'Emsfb/v1/' : '/wp-json/Emsfb/v1/');

  /** Fills an element with a phrase whose values are <bdi> (text only, never HTML). */
  function setMixed(el, tpl, vals) {
    if (!el) return;
    el.textContent = '';
    parts(tpl).forEach((p) => {
      if (p.t !== undefined) { el.appendChild(document.createTextNode(p.t)); return; }
      const b = document.createElement('bdi');
      const v = vals[p.v];
      if (v && typeof v === 'object') { b.setAttribute('dir', v.dir || 'auto'); b.textContent = String(v.text); } else { b.textContent = String(v == null ? '' : v); }
      el.appendChild(b);
    });
  }

  function fieldRow(fid, vid) {
    try {
      const rows = typeof root.get_structure_by_form_id_efb === 'function' ? root.get_structure_by_form_id_efb(fid) : root.valj_efb;
      return (rows || []).find((x) => x && x.id_ === vid) || null;
    } catch (e) { return null; }
  }
  function fieldName(c) {
    const row = fieldRow(c.fid, c.vid);
    if (row && row.name) return String(row.name);
    const lab = document.getElementById(c.vid + '_lab');
    return lab ? lab.textContent.replace(/\*\s*$/, '').trim() : c.vid;
  }

  /** The current value of the field as the server will normalize it (null = not a valid destination). */
  function currentValue(c) {
    if (!c.input) return null;
    if (c.channel === 'sms') {
      let iti = null;
      try { iti = root.intlTelInput && typeof root.intlTelInput.getInstance === 'function' ? root.intlTelInput.getInstance(c.input) : null; } catch (e) { iti = null; }
      if (iti && typeof iti.getNumber === 'function') {
        if (c.input.value.trim() === '') return null;
        if (typeof iti.isValidNumber === 'function' && iti.isValidNumber() === false) return null;
        return normPhone(iti.getNumber());
      }
      return normPhone(c.input.value);
    }
    return normEmail(c.input.value);
  }
  function rawEmpty(c) { return !c.input || String(c.input.value || '').trim() === ''; }

  function emit(c) {
    try { document.dispatchEvent(new CustomEvent('efb:verify-state', { detail: { form_id: c.fid, field_id: c.vid, channel: c.channel, state: c.state } })); } catch (e) { /* old browsers */ }
  }
  function setState(c, s) {
    if (c.state === s) return;
    c.state = s;
    if (c.wrap) c.wrap.setAttribute('data-state', s);
    emit(c);
  }
  function showError(c, text) {
    if (!c.err) return;
    c.err.textContent = text || '';
    c.err.hidden = !text;
  }
  function stopTimers(c) {
    if (c.timer) { clearInterval(c.timer); c.timer = null; }
    if (c.ttlTimer) { clearTimeout(c.ttlTimer); c.ttlTimer = null; }
    if (c.helpTimer) { clearTimeout(c.helpTimer); c.helpTimer = null; }
  }
  function abortOtp(c) {
    if (c.otpAbort) { try { c.otpAbort.abort(); } catch (e) { /* ignore */ } c.otpAbort = null; }
  }
  function refreshSend(c) {
    if (!c.send) return;
    const v = currentValue(c);
    const match = !c.retype || confirmMatches(c);
    const busy = c.state === 'sending' || c.state === 'verifying' || c.state === 'verified' || c.state === 'code_sent';
    const offline = typeof navigator !== 'undefined' && navigator.onLine === false;
    c.send.disabled = !(v && match && !busy && !offline && !c.locked);
  }
  function confirmMatches(c) {
    if (!c.confirm) return true;
    const a = normEmail(c.input ? c.input.value : '');
    const b = normEmail(c.confirm.value);
    return !!a && a === b;
  }

  /** Back to idle: the token is dropped, the code box closes, the main input is editable again. */
  function reset(c, keepError) {
    stopTimers(c);
    abortOtp(c);
    c.token = null; c.verifiedValue = null; c.challenge = null;
    if (c.box) c.box.hidden = true;
    if (c.code) { c.code.value = ''; c.code.removeAttribute('aria-invalid'); }
    if (c.input) { c.input.readOnly = false; c.input.classList.remove('efb-vrf-done'); }
    if (c.status) c.status.textContent = '';
    if (!keepError) showError(c, '');
    if (c.send) c.send.hidden = false;
    setState(c, 'idle');
    refreshSend(c);
  }

  async function post(c, route, body) {
    const fid = Number(c.fid);
    let rt = null;
    try { rt = root.EFBRuntime ? await root.EFBRuntime.prepare(fid) : null; } catch (e) { const er = new Error(e.message || ''); er.code = e.code || 'efb_context_expired'; er.status = e.status || 409; throw er; }
    if (!rt || !rt.runtime_token) { const er = new Error(T('nonceExpired', 'Your session has expired. Please refresh the page and try again.')); er.code = 'efb_context_expired'; er.status = 409; throw er; }
    let r;
    try {
      r = await fetch(restUrl() + route, {
        method: 'POST', credentials: 'same-origin', cache: 'no-store',
        headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': rt.nonce, 'sid': rt.sid, 'form-id': String(fid) },
        body: JSON.stringify(Object.assign({ form_id: fid, field_id: c.vid, runtime_token: rt.runtime_token }, body)),
      });
    } catch (e) {
      if (root.EFBRequestHealth) root.EFBRequestHealth.report('verify', 0);
      const er = new Error(T('vrfFailed', 'We could not send the code. Please try again shortly.')); er.code = 'network'; er.status = 0; throw er;
    }
    let j = null;
    try { j = await r.json(); } catch (e) { j = null; }
    // A refusal is HTTP 200 with success:false (the plugin's envelope; Verification::envelope()), its meaning in status.
    if (!r.ok || !j || j.success === false) {
      const status = j && j.success === false && j.status ? Number(j.status) : r.status;
      if (status >= 500 && root.EFBRequestHealth) root.EFBRequestHealth.report('verify', status);
      const er = new Error((j && (j.message || (j.data && j.data.m))) || T('vrfFailed', 'We could not send the code. Please try again shortly.'));
      er.code = j && j.code; er.status = status;
      er.data = Object.assign({}, (j && j.data) || {}, j && j.retry_after !== undefined ? { retry_after: j.retry_after } : {}, j && j.attempts_left !== undefined ? { attempts_left: j.attempts_left } : {});
      if ((er.code === 'efb_context_expired' || er.code === 'efb_identity_changed') && root.EFBRuntime && typeof root.EFBRuntime.reset === 'function') root.EFBRuntime.reset(fid);
      throw er;
    }
    return j || {};
  }

  function startCountdown(c, seconds) {
    if (c.timer) clearInterval(c.timer);
    let left = Math.max(0, Number(seconds) || 0);
    const tick = () => {
      if (!c.timerEl) return;
      if (left <= 0) {
        clearInterval(c.timer); c.timer = null;
        c.timerEl.textContent = '';
        if (c.resend) { c.resend.disabled = false; c.resend.hidden = false; }
        if (c.status) c.status.setAttribute('aria-live', 'polite'); // the end of the wait is announced once (the button appears)
        return;
      }
      setMixed(c.timerEl, T('vrfResendIn', 'Resend in %s'), [{ text: localDigits(fmtTime(left), locale()), dir: 'ltr' }]);
      left--;
    };
    // While the wait runs only "Resend in m:ss" shows (not announced every second: the timer is aria-hidden).
    if (c.resend) { c.resend.disabled = true; c.resend.hidden = true; }
    tick();
    c.timer = setInterval(tick, 1000);
  }

  /** WebOTP (Chrome on Android): fill the code from the SMS; aborted on edit, verify or leaving the page. */
  function startWebOtp(c) {
    abortOtp(c);
    if (c.channel !== 'sms' || !('OTPCredential' in root) || !navigator.credentials || typeof AbortController === 'undefined') return;
    const ac = new AbortController();
    c.otpAbort = ac;
    navigator.credentials.get({ otp: { transport: ['sms'] }, signal: ac.signal }).then((otp) => {
      if (!otp || !otp.code || c.state !== 'code_sent' || c.otpAbort !== ac) return;
      c.code.value = codeDigits(otp.code, c.len);
      check(c);
    }).catch(() => { /* silent by design (§3.6) */ });
  }

  async function send(c) {
    const v = currentValue(c);
    if (!v) { showError(c, T('vrfDestination', 'Please enter a valid number or address first.')); return; }
    if (typeof navigator !== 'undefined' && navigator.onLine === false) { showError(c, T('offlineMSend', 'You are offline. Please connect to the internet and try again.')); return; }
    if (c.countries.length && c.channel === 'sms' && !countryOk(c)) { showError(c, T('vrfCountry', 'Verification codes cannot be sent to this country.')); return; }
    stopTimers(c);
    showError(c, '');
    setState(c, reduce(c.state, 'send'));
    if (c.send) c.send.disabled = true;
    if (c.status) c.status.textContent = T('vrfSending', 'Sending code…');
    try {
      const j = await post(c, 'verify/send', { destination: v, channel: c.channel, lang: (root.efb_var && root.efb_var.wp_lan) || '' });
      c.challenge = String(j.challenge_id || '');
      c.sentValue = v;
      c.len = Number(j.code_length) || c.len;
      if (c.code) c.code.setAttribute('maxlength', String(c.len));
      setState(c, 'code_sent');
      if (c.input) c.input.readOnly = true;
      if (c.send) c.send.hidden = true;
      if (c.box) c.box.hidden = false;
      setMixed(c.status, c.channel === 'sms' ? T('vrfSentSms', 'We sent a %1$s-digit code to %2$s.') : T('vrfSentEmail', 'We emailed a %1$s-digit code to %2$s. Check your spam folder too.'),
        [{ text: localDigits(String(c.len), locale()), dir: 'auto' }, { text: String(j.masked || ''), dir: 'ltr' }]);
      startCountdown(c, Number(j.resend_after) || 60);
      c.ttlTimer = setTimeout(() => {
        if (c.state !== 'code_sent' && c.state !== 'verifying') return;
        setState(c, 'expired');
        abortOtp(c);
        showError(c, T('vrfExpired', 'The code has expired. Please request a new one.'));
        if (c.resend) { c.resend.disabled = false; c.resend.hidden = false; }
      }, (Number(j.expires_in) || 300) * 1000);
      if (c.help) { c.help.hidden = true; c.helpTimer = setTimeout(() => { if (c.help && c.state === 'code_sent') c.help.hidden = false; }, 30000); }
      if (c.code) { c.code.value = ''; try { c.code.focus({ preventScroll: false }); } catch (e) { c.code.focus(); } }
      startWebOtp(c);
    } catch (e) {
      setState(c, 'error');
      if (c.status) c.status.textContent = '';
      showError(c, e.message);
      if (e.code === 'efb_verify_locked') c.locked = true;
      if (e.code === 'efb_verify_cooldown' || e.code === 'efb_verify_limit') {
        const wait = Number(e.data && e.data.retry_after) || 60;
        c.locked = true;
        setTimeout(() => { c.locked = false; refreshSend(c); }, wait * 1000);
      }
      if (c.send) c.send.hidden = false;
      refreshSend(c);
    }
  }

  /** The country the phone widget selected is one of the field's (the server decides anyway: Verification::country_allowed). */
  function countryOk(c) {
    let iti = null;
    try { iti = root.intlTelInput && typeof root.intlTelInput.getInstance === 'function' ? root.intlTelInput.getInstance(c.input) : null; } catch (e) { iti = null; }
    const data = iti && typeof iti.getSelectedCountryData === 'function' ? iti.getSelectedCountryData() : null;
    if (!data || !data.iso2) return true;
    return c.countries.indexOf(String(data.iso2).toLowerCase()) !== -1;
  }

  async function check(c) {
    if (!c.code || (c.state !== 'code_sent')) return;
    const code = codeDigits(c.code.value, c.len);
    c.code.value = code;
    if (code.length !== c.len) { c.code.setAttribute('aria-invalid', 'true'); try { c.code.focus(); } catch (e) { /* ignore */ } return; }
    setState(c, reduce(c.state, 'check'));
    c.code.disabled = true;
    if (c.checkBtn) c.checkBtn.disabled = true;
    try {
      const j = await post(c, 'verify/check', { challenge_id: c.challenge, code: code });
      // A value changed while the request ran keeps nothing.
      if (currentValue(c) !== c.sentValue) { reset(c); return; }
      c.token = String(j.token || '');
      c.verifiedValue = c.sentValue;
      stopTimers(c);
      abortOtp(c);
      setState(c, 'verified');
      showError(c, '');
      if (c.box) c.box.hidden = true;
      if (c.input) { c.input.readOnly = false; c.input.classList.add('efb-vrf-done'); c.input.classList.remove('border-danger'); }
      if (c.status) { c.status.textContent = ''; const ok = document.createElement('span'); ok.className = 'efb efb-vrf-ok'; ok.textContent = T('vrfVerified', 'Verified'); c.status.appendChild(ok); }
      if (c.send) c.send.hidden = true;
      if (typeof root.sendback_state_handler_efb_v4 === 'function') { try { root.sendback_state_handler_efb_v4(c.vid, true, 0, c.fid); } catch (e) { /* older core */ } }
      const body = document.getElementById('body_efb_' + c.fid);
      const nextBtn = body ? Array.prototype.find.call(body.querySelectorAll('#next_efb, #btn_send_efb'), (b) => b.offsetParent !== null && !b.classList.contains('d-none')) : null;
      if (nextBtn) { try { nextBtn.focus({ preventScroll: true }); } catch (e) { /* ignore */ } }
    } catch (e) {
      if (e.code === 'efb_verify_wrong') {
        setState(c, reduce(c.state, 'wrong'));
        c.code.value = '';
        c.code.setAttribute('aria-invalid', 'true');
        const left = e.data && e.data.attempts_left !== undefined ? Number(e.data.attempts_left) : NaN;
        if (isFinite(left)) {
          setMixed(c.err, T('vrfWrong', 'That code is not correct. %s attempts left.'), [{ text: localDigits(String(left), locale()), dir: 'auto' }]);
          c.err.hidden = false;
        } else {
          showError(c, e.message);
        }
        try { c.code.focus(); } catch (x) { /* ignore */ }
      } else if (e.code === 'efb_verify_locked') {
        setState(c, 'error');
        c.locked = true;
        if (c.box) c.box.hidden = true;
        stopTimers(c); abortOtp(c);
        showError(c, e.message);
      } else if (e.code === 'efb_verify_expired') {
        setState(c, 'expired');
        stopTimers(c); abortOtp(c);
        showError(c, e.message);
        if (c.resend) { c.resend.disabled = false; c.resend.hidden = false; }
      } else {
        setState(c, 'code_sent');
        showError(c, e.message);
      }
    } finally {
      c.code.disabled = false;
      if (c.checkBtn) c.checkBtn.disabled = false;
      refreshSend(c);
    }
  }

  function onValueChange(c) {
    if (c.confirm) checkConfirm(c, false);
    if ((c.state === 'verified' || c.state === 'code_sent' || c.state === 'verifying' || c.state === 'expired') && currentValue(c) !== (c.verifiedValue || c.sentValue)) {
      reset(c);
      return;
    }
    refreshSend(c);
  }

  /** The second e-mail box: the message is told at the second box, on blur (or at Send). */
  function checkConfirm(c, loud) {
    if (!c.confirm) return true;
    const msg = document.getElementById(c.vid + '_confirm-message');
    const a = String(c.input ? c.input.value : '').trim();
    const b = String(c.confirm.value).trim();
    const bad = b !== '' || loud ? !confirmMatches(c) && !(a === '' && b === '') : false;
    if (bad && (loud || b !== '')) {
      c.confirm.setAttribute('aria-invalid', 'true');
      c.confirm.classList.add('border-danger');
      if (msg) { msg.setAttribute('dir', 'auto'); msg.textContent = mismatchText(c); }
      return false;
    }
    c.confirm.removeAttribute('aria-invalid');
    c.confirm.classList.remove('border-danger');
    if (msg) msg.textContent = '';
    return true;
  }
  function mismatchText(c) {
    const dflt = T('vrfEmailMismatch', 'The email addresses do not match.');
    const row = fieldRow(c.fid, c.vid);
    if (root.EfbRules && typeof root.EfbRules.mismatchMessage === 'function' && row) {
      try { return root.EfbRules.mismatchMessage(row, dflt); } catch (e) { return dflt; }
    }
    return dflt;
  }

  function bind(c) {
    if (c.input && !c.input.dataset.efbVrf) {
      c.input.dataset.efbVrf = '1';
      ['input', 'change', 'blur', 'countrychange'].forEach((ev) => c.input.addEventListener(ev, () => onValueChange(c)));
    }
    if (c.confirm && !c.confirm.dataset.efbVrf) {
      c.confirm.dataset.efbVrf = '1';
      c.confirm.addEventListener('input', () => { if (c.confirm.getAttribute('aria-invalid') === 'true') checkConfirm(c, false); refreshSend(c); });
      c.confirm.addEventListener('blur', () => { if (c.confirm.value.trim() !== '') checkConfirm(c, false); refreshSend(c); });
    }
    if (c.send) c.send.addEventListener('click', (e) => { e.preventDefault(); send(c); });
    if (c.resend) c.resend.addEventListener('click', (e) => { e.preventDefault(); send(c); });
    if (c.checkBtn) c.checkBtn.addEventListener('click', (e) => { e.preventDefault(); check(c); });
    if (c.edit) c.edit.addEventListener('click', (e) => { e.preventDefault(); reset(c); if (c.input) { try { c.input.focus(); } catch (x) { /* ignore */ } } });
    if (c.help) c.help.addEventListener('click', (e) => {
      e.preventDefault();
      const open = c.help.getAttribute('aria-expanded') !== 'true';
      c.help.setAttribute('aria-expanded', open ? 'true' : 'false');
      if (c.tips) c.tips.hidden = !open;
    });
    if (c.code) {
      c.code.addEventListener('input', () => {
        const d = codeDigits(c.code.value, c.len);
        if (d !== c.code.value) c.code.value = d;
        c.code.removeAttribute('aria-invalid');
        if (d.length === c.len) check(c); // auto-submit at full length (§3.1)
      });
      c.code.addEventListener('keydown', (e) => { if (e.key === 'Enter') { e.preventDefault(); check(c); } });
    }
    refreshSend(c);
  }

  /** Finds the blocks of every form on the page (the renderer printed them; nothing here is secret). */
  function init(scope) {
    const doc = scope || document;
    doc.querySelectorAll('.efb-vrf[data-vid]').forEach((w) => {
      const vid = w.getAttribute('data-vid');
      const fid = String(w.getAttribute('data-formid') || '0');
      const key = fid + '|' + vid;
      if (ctrls[key]) return;
      const $ = (sfx) => document.getElementById(vid + sfx);
      const c = {
        fid, vid, wrap: w, channel: w.getAttribute('data-channel') === 'sms' ? 'sms' : 'email', otp: true,
        len: Number(w.getAttribute('data-len')) || 6, when: w.getAttribute('data-when') === 'always' ? 'always' : 'filled',
        retype: w.getAttribute('data-retype') === '1', countries: String(w.getAttribute('data-countries') || '').split(',').filter(Boolean),
        input: $('_'), confirm: $('_confirm'), send: $('_vrf-send'), status: $('_vrf-status'), err: $('_vrf-error'), box: $('_vrf-box'),
        code: $('_vrf-code'), checkBtn: $('_vrf-check'), resend: $('_vrf-resend'), timerEl: $('_vrf-timer'), edit: $('_vrf-edit'), help: $('_vrf-help'), tips: $('_vrf-tips'),
        state: 'idle', token: null, verifiedValue: null, sentValue: null, challenge: null, locked: false,
      };
      if (c.err) c.err.hidden = true;
      ctrls[key] = c;
      w.setAttribute('data-state', 'idle');
      bind(c);
    });
    // "Type it twice" without a code: only the second box.
    doc.querySelectorAll('input.efb-email-confirm[data-for]').forEach((box) => {
      const vid = box.getAttribute('data-for');
      const input = document.getElementById(vid + '_');
      const fid = String((input && input.getAttribute('data-formid')) || '0');
      const key = fid + '|' + vid;
      if (ctrls[key]) return;
      const c = { fid, vid, wrap: null, channel: 'email', otp: false, retype: true, when: 'filled', countries: [], input, confirm: box, state: 'idle', token: null, len: 6 };
      ctrls[key] = c;
      bind(c);
    });
  }

  function forForm(fid) {
    return Object.keys(ctrls).filter((k) => k.split('|')[0] === String(fid)).map((k) => ctrls[k]);
  }
  function visible(c) {
    return !!(c.input && c.input.offsetParent !== null && !c.input.closest('.d-none, .efb-anim-hide'));
  }
  function stepOf(c) {
    const row = fieldRow(c.fid, c.vid);
    return row && row.step != null ? Number(row.step) : 1;
  }

  /**
   * Next / Send guard (core-efb.js btn_navigate_handle_efb): a visible, filled field of the steps
   * being left that is not verified for its current value (or whose second copy differs) stops the
   * move with the reason at the field. Hidden fields (conditional logic) need nothing.
   */
  function guard(fid, step, btnState, maxStep) {
    const last = btnState === 'btn_send_efb' || (btnState === 'next_efb' && Number(step) >= Number(maxStep));
    for (const c of forForm(fid)) {
      if (!visible(c)) continue;
      if (!last && stepOf(c) > Number(step)) continue;
      const empty = rawEmpty(c);
      if (c.retype && !(empty && String(c.confirm && c.confirm.value || '').trim() === '') && !checkConfirm(c, true)) {
        focusField(c, c.confirm);
        return false;
      }
      if (!c.otp) continue;
      if (empty) {
        if (c.when === 'always') { needVerify(c); return false; }
        continue;
      }
      const v = currentValue(c);
      if (!c.token || !v || v !== c.verifiedValue) { needVerify(c); return false; }
    }
    return true;
  }
  function needVerify(c) {
    setMixed(c.err, T('vrfRequired', 'Please verify %s before continuing.'), [{ text: fieldName(c), dir: 'auto' }]);
    c.err.hidden = false;
    focusField(c, c.state === 'code_sent' && c.code ? c.code : (c.send && !c.send.disabled ? c.send : c.input));
  }
  function focusField(c, el) {
    try { (el || c.input).scrollIntoView({ behavior: 'smooth', block: 'center' }); } catch (e) { /* ignore */ }
    try { (el || c.input).focus({ preventScroll: true }); } catch (e) { /* ignore */ }
  }

  /** The request keys (core-efb.js actionSendData_emsFormBuilder): tokens of verified values, second copies. */
  function addToRequest(data, fid) {
    if (!data || typeof data !== 'object') return;
    const tokens = {}; const confirms = {};
    forForm(fid).forEach((c) => {
      if (c.otp && c.token && c.verifiedValue && currentValue(c) === c.verifiedValue) tokens[c.vid] = c.token;
      if (c.retype && c.confirm) confirms[c.vid] = c.confirm.value;
    });
    if (Object.keys(tokens).length) data.verifications = tokens;
    if (Object.keys(confirms).length) data.email_confirms = confirms;
  }

  /** The server refused a field (efb_verify_*): its state goes back so the visitor can verify again. */
  function serverError(fid, vid, code, m) {
    const c = ctrls[String(fid) + '|' + vid];
    if (!c) return;
    let text = m;
    try { text = new DOMParser().parseFromString(String(m || ''), 'text/html').body.textContent || ''; } catch (e) { text = String(m || '').replace(/<[^>]*>/g, ''); }
    if (code === 'efb_verify_mismatch' && c.confirm) { checkConfirm(c, true); return; }
    if (c.otp) reset(c, true);
    showError(c, text);
  }

  if (typeof window !== 'undefined') {
    window.addEventListener('pagehide', () => Object.keys(ctrls).forEach((k) => abortOtp(ctrls[k])));
    window.addEventListener('online', () => Object.keys(ctrls).forEach((k) => refreshSend(ctrls[k])));
    window.addEventListener('offline', () => Object.keys(ctrls).forEach((k) => refreshSend(ctrls[k])));
    const boot = () => { init(document); setTimeout(() => init(document), 1500); };
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot); else boot();
  }

  return Object.assign(api, { init, guard, addToRequest, serverError, _ctrls: ctrls, _currentValue: currentValue });
}));
