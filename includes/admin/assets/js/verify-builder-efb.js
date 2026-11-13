/**
 * F02 in the builder: the one-time code controls of the mobile and e-mail fields (settings panel),
 * their handler and a canvas hint. Spec: docs/feature-gaps/F02-verification-otp.fa.md (§4.1).
 *
 * The keys are written into valj_efb directly with the save rules (EFBVerify.sanitizeKey, the twin of
 * Verification::sanitize_key on the server), like the F03 controls. Plan: "type it twice" on every
 * plan, an e-mail code from Free Plus, an SMS code on Pro with the SMS add-on (efb_var.verify.plan).
 * A code that this site cannot send now (no SMS provider, e-mail sending off) is refused here with
 * the reason and a link to Settings > Verification (SMS: through the WP SMS plugin only, so the
 * link explains how to install and set up WP SMS); a form saved that way is told in the save answer
 * (verify_unavailable) and refuses visitors who fill the field (fail-closed), never skips the check.
 */
(function (root) {
  'use strict';

  const T = (k, fb) => {
    try { if (root.efb_var && root.efb_var.text && root.efb_var.text[k]) return root.efb_var.text[k]; } catch (e) { /* ignore */ }
    return fb;
  };
  const esc = (v) => String(v == null ? '' : v).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#039;');
  const V = () => root.EFBVerify || null;
  const state = () => (root.efb_var && root.efb_var.verify) || { email: { ready: false, reason: '' }, sms: { ready: false, reason: '' }, plan: { retype: 'free', email: 'free_plus', sms: 'pro' } };
  const settingsUrl = () => {
    const base = (root.efb_var && root.efb_var.ajax_url) ? String(root.efb_var.ajax_url).replace(/admin-ajax\.php.*$/, '') : '/wp-admin/';
    return base + 'admin.php?page=Emsfb&state=setting&tab=verify';
  };

  function rowById(dataId) {
    if (typeof valj_efb === 'undefined') return null;
    const i = valj_efb.findIndex((x) => x && x.dataId == dataId);
    return i > 0 ? valj_efb[i] : null;
  }
  /** Plan of a part through the registry (efb_var.capabilities), else the plan number. */
  function planOk(part) {
    const key = { retype: 'setting:verify_email_retype', email: 'setting:verify_email_otp', sms: 'setting:verify_sms_otp' }[part];
    const cap = root.efb_var && root.efb_var.capabilities && root.efb_var.capabilities.features && root.efb_var.capabilities.features[key];
    const need = (state().plan || {})[part] || 'pro';
    if (need === 'free') return true;
    const pkg = Number(root.efb_var && root.efb_var.setting && root.efb_var.setting.package_type);
    if (cap && cap.reason !== 'plan') return true;
    if (cap && cap.reason === 'plan') return false;
    if (need === 'pro') return pkg === 1;
    return pkg === 1 || pkg === 3 || (typeof pro_efb !== 'undefined' && pro_efb === true);
  }
  const CAP = { retype: 'setting:verify_email_retype', email: 'setting:verify_email_otp', sms: 'setting:verify_sms_otp' };
  function gem(part) {
    if (planOk(part)) return '';
    const pro = (state().plan || {})[part] === 'pro';
    const label = esc(T(pro ? 'proVersion' : 'upgTipFreePlus', pro ? 'Pro' : 'Free Plus'));
    return ` <a type="button" role="button" tabindex="0" class="efb pro-version-efb efb-vrf-gem" data-cap="${CAP[part]}" onclick="efb_vrf_gem_efb(this)" title="${label}" aria-label="${label}"><i class="efb bi-gem text-light" aria-hidden="true"></i></a>`;
  }
  /** F18: the upgrade dialog of the registry entry (its text and plan come from the registry). */
  function efb_vrf_gem_efb(btn) {
    const cap = btn && btn.getAttribute ? btn.getAttribute('data-cap') : btn;
    const fallback = cap === CAP.sms ? 'pro' : 'free_plus';
    if (typeof efb_upgrade_prompt_efb === 'function') efb_upgrade_prompt_efb({ feature: cap, fallback: fallback, origin: 'field_settings', opener: btn && btn.getAttribute ? btn : null });
    else if (typeof efb_capability_prompt_efb === 'function') efb_capability_prompt_efb(cap, fallback === 'pro' ? 1 : 3, btn);
  }
  function isAuthForm() {
    return typeof valj_efb !== 'undefined' && valj_efb[0] && ['register', 'login'].indexOf(String(valj_efb[0].type)) !== -1;
  }
  function note(id, text, kind, link) {
    return `<div class="efb efb-vrf-note fs-8 mt-1 ${kind === 'warn' ? 'text-danger' : 'text-muted'}" id="${id}" role="note">${esc(text)}${link ? ` <a class="efb" href="${esc(settingsUrl())}" target="_blank" rel="noopener">${esc(T('vrfOpenSettings', 'Open the verification settings'))}</a>` : ''}</div>`;
  }
  function textEl(r, idset, id, key, label, opts) {
    opts = opts || {};
    const v = r && typeof r[key] === 'string' ? r[key] : (Array.isArray(r[key]) ? r[key].join(',') : '');
    const tag = opts.area ? 'textarea' : 'input';
    const val = opts.area ? esc(v.replace(/@n#/g, '\n')) : '';
    return `<div class="efb mt-2 mb-1 efb-vrf-ctl" data-vrf-show="${opts.show || ''}">
      <label for="${id}" class="efb mb-1 fs-7">${esc(label)}</label>
      <${tag} ${opts.area ? 'rows="4"' : 'type="text"'} maxlength="${opts.max || 150}" class="efb form-control efb ${opts.area ? '' : 'h-d-efb'} border-d rounded-4 efb-vrf-in" id="${id}" data-id="${esc(idset)}" data-key="${key}" ${opts.area ? '' : `value="${esc(v)}"`} ${opts.dir ? `dir="${opts.dir}"` : ''} placeholder="${esc(opts.placeholder || '')}" onchange="efb_vrf_change_efb(this)">${opts.area ? val + '</textarea>' : ''}
      ${opts.help ? `<small class="efb form-text text-muted d-block mt-1 fs-8">${esc(opts.help)}</small>` : ''}
      <div class="efb efb-vrf-msg fs-8 text-danger" id="${id}Msg" role="alert"></div>
    </div>`;
  }
  function selEl(r, idset, id, key, label, options, dflt, opts) {
    opts = opts || {};
    const cur = r && typeof r[key] === 'string' && r[key] !== '' ? r[key] : dflt;
    return `<div class="efb mt-2 mb-1 efb-vrf-ctl" data-vrf-show="${opts.show || ''}">
      <label for="${id}" class="efb mb-1 fs-7">${esc(label)}${opts.gem || ''}</label>
      <select class="efb form-select efb border-d rounded-4 efb-vrf-in" id="${id}" data-id="${esc(idset)}" data-key="${key}" onchange="efb_vrf_change_efb(this)">
        ${options.map((o) => `<option value="${esc(o[0])}" ${cur === o[0] ? 'selected' : ''} ${o[2] ? 'disabled' : ''}>${esc(o[1])}</option>`).join('')}
      </select>
    </div>`;
  }

  /** Controls of the main part of the panel for a mobile or e-mail field (val-efb.js inserts them). */
  function efbVerifyBuilderEls(indx, idset, tag) {
    if (typeof valj_efb === 'undefined' || !valj_efb[indx]) return '';
    const r = valj_efb[indx];
    const st = state();
    let html = '';
    if (tag === 'email') {
      const mode = (V() && V().emailMode) ? V().emailMode(r) : (r.email_confirm_mode || 'none');
      const otpLocked = !planOk('email');
      html += selEl(r, idset, 'vrfEmailModeEl', 'email_confirm_mode', T('vrfEmailMode', 'Email confirmation'), [
        ['none', T('vrfModeNone', 'Off')],
        ['retype', T('vrfModeRetype', 'Type it twice')],
        ['otp', T('vrfModeOtp', 'Send a code'), otpLocked],
        ['retype_otp', T('vrfModeRetypeOtp', 'Type it twice and send a code'), otpLocked],
      ], 'none', { gem: gem('email') });
      if (isAuthForm() && mode !== 'none') html += note('vrfAuthNoteEl', T('vrfNotOnRegister', 'Codes are not used on registration and login forms yet.'), 'warn');
      if ((mode === 'otp' || mode === 'retype_otp') && !st.email.ready) html += note('vrfEmailNotReadyEl', T('vrfEmailNotReady', 'This site does not send e-mails, so e-mail codes cannot be sent. Turn on e-mail sending first.'), 'warn', true);
      html += textEl(r, idset, 'vrfRetypeLabelEl', 'email_retype_label', T('vrfRetypeLabel', 'Label of the second box'), { max: 60, show: 'retype', placeholder: T('vrfEmailRetype', 'Confirm email') });
      html += selEl(r, idset, 'vrfEmailWhenEl', 'email_otp_required_when', T('vrfWhen', 'Ask for the code'), [['filled', T('vrfWhenFilled', 'When the field is filled in')], ['always', T('vrfWhenAlways', 'Always (an empty field cannot be sent)')]], 'filled', { show: 'otp' });
      html += textEl(r, idset, 'vrfSubjectEl', 'email_otp_subject', T('vrfSubject', 'E-mail subject ([website_name], [otp_code])'), { max: 150, show: 'otp' });
      html += textEl(r, idset, 'vrfBodyEl', 'email_otp_body', T('vrfBody', 'E-mail text (must contain [otp_code])'), { max: 1000, show: 'otp', area: true });
      html += textEl(r, idset, 'vrfBtnLabelEl', 'otp_btn_label', T('vrfBtnLabel', 'Button text'), { max: 60, show: 'otp', placeholder: T('vrfSend', 'Send code') });
    } else if (tag === 'mobile') {
      const onNow = V() ? V().sanitizeKey('otp_enabled', r.otp_enabled) === '1' : String(r.otp_enabled) === '1';
      const locked = !planOk('sms');
      html += `<div class="efb mx-1 my-2 efb-vrf-ctl">
        <button type="button" id="vrfSmsEl" data-id="${esc(idset)}" data-key="otp_enabled" class="efb mx-0 btn h-s-efb btn-toggle efb-vrf-in ${onNow ? 'active' : ''}" aria-pressed="${onNow ? 'true' : 'false'}" autocomplete="off" onclick="efb_vrf_change_efb(this)"${locked && !onNow ? ' disabled aria-disabled="true"' : ''}><div class="efb handle"></div></button>
        <label class="efb form-check-label fs-7" for="vrfSmsEl">${esc(T('vrfEnableSms', 'Verify with an SMS code'))}${gem('sms')}</label>
        <div class="efb efb-vrf-msg fs-8 text-danger" id="vrfSmsElMsg" role="alert"></div>
      </div>`;
      if (isAuthForm() && onNow) html += note('vrfAuthNoteEl', T('vrfNotOnRegister', 'Codes are not used on registration and login forms yet.'), 'warn');
      if (onNow && !st.sms.ready) html += note('vrfSmsNotReadyEl', T('vrfSmsNotReady', 'SMS codes cannot be sent on this site yet.'), 'warn', true);
      html += selEl(r, idset, 'vrfSmsWhenEl', 'otp_required_when', T('vrfWhen', 'Ask for the code'), [['filled', T('vrfWhenFilled', 'When the field is filled in')], ['always', T('vrfWhenAlways', 'Always (an empty field cannot be sent)')]], 'filled', { show: 'sms' });
      html += textEl(r, idset, 'vrfCountriesEl', 'otp_countries', T('vrfCountries', 'Countries that may receive the code (ISO codes, empty = the site list)'), { max: 300, show: 'sms', dir: 'ltr', placeholder: (st.countries || []).join(',') });
      html += textEl(r, idset, 'vrfSmsBtnEl', 'otp_btn_label', T('vrfBtnLabel', 'Button text'), { max: 60, show: 'sms', placeholder: T('vrfSend', 'Send code') });
    }
    if (!html) return '';
    setTimeout(() => { efb_vrf_visibility_efb(idset); }, 0);
    return `<div class="efb efb-vrf-builder mt-2" id="vrfBuilderEl" data-id="${esc(idset)}">${html}</div>`;
  }

  /** Shows the controls that apply to the chosen mode / switch. */
  function efb_vrf_visibility_efb(idset) {
    const r = rowById(idset);
    const box = document.getElementById('vrfBuilderEl');
    if (!r || !box) return;
    const spec = V() ? V().fieldSpec(r) : null;
    box.querySelectorAll('[data-vrf-show]').forEach((el) => {
      const need = el.getAttribute('data-vrf-show');
      let show = true;
      if (need === 'retype') show = !!(spec && spec.retype);
      else if (need === 'otp') show = !!(spec && spec.otp && spec.channel === 'email');
      else if (need === 'sms') show = !!(spec && spec.otp && spec.channel === 'sms');
      el.classList.toggle('d-none', !show);
    });
  }

  function msg(el, text) {
    const m = el ? document.getElementById(el.id + 'Msg') : null;
    if (m) m.textContent = text || '';
    if (text && typeof alert_message_efb === 'function') { try { alert_message_efb('', text, 10, 'warning'); } catch (e) { /* ignore */ } }
  }

  /** One control changed: the server's rules, the plan and the channel are checked before writing. */
  function efb_vrf_change_efb(el) {
    if (!el || el.getAttribute('aria-disabled') === 'true') return;
    const key = el.getAttribute('data-key');
    const r = rowById(el.getAttribute('data-id'));
    if (!key || !r) return;
    const st = state();
    let raw;
    if (el.classList.contains('btn-toggle')) raw = el.classList.contains('active') ? '0' : '1';
    else raw = el.tagName === 'TEXTAREA' ? el.value : el.value;
    const v = V() ? V().sanitizeKey(key, raw) : String(raw);
    msg(el, '');
    if (key === 'email_confirm_mode' && (v === 'otp' || v === 'retype_otp')) {
      if (!planOk('email')) { el.value = r.email_confirm_mode || 'none'; efb_vrf_gem_efb(CAP.email); return; }
      if (!st.email.ready) { el.value = r.email_confirm_mode || 'none'; msg(el, T('vrfEmailNotReady', 'This site does not send e-mails, so e-mail codes cannot be sent. Turn on e-mail sending first.')); if (typeof efb_builder_delivery_needs_question === 'function') { try { efb_builder_delivery_needs_question(); } catch (e) { /* ignore */ } } return; }
    }
    if (key === 'otp_enabled' && v === '1') {
      if (!planOk('sms')) { efb_vrf_gem_efb(CAP.sms); return; }
      if (!st.sms.ready) { msg(el, T('vrfSmsNotReady', 'SMS codes cannot be sent on this site yet.')); return; }
    }
    if (key === 'email_otp_body' && v !== '' && v.indexOf('[otp_code]') === -1) { msg(el, T('vrfBodyNeedsCode', 'The e-mail text must contain [otp_code].')); return; }
    if (key === 'otp_countries') {
      r[key] = v;
      el.value = v.join(',');
    } else {
      r[key] = v;
    }
    if (el.classList.contains('btn-toggle')) {
      el.classList.toggle('active', v === '1');
      el.setAttribute('aria-pressed', v === '1' ? 'true' : 'false');
    }
    if (key === 'otp_enabled' && v === '1') {
      if (!r.otp_required_when) r.otp_required_when = 'filled';
      r.otp_channel = 'sms';
    }
    efb_vrf_visibility_efb(el.getAttribute('data-id'));
    efb_vrf_decorate_efb(document);
    if (typeof efbApiNoteUserEditEfb === 'function') { try { efbApiNoteUserEditEfb(); } catch (e) { /* ignore */ } }
  }

  /** Canvas and preview: a disabled "Send code" hint under a field that asks for a code (never sends). */
  function efb_vrf_decorate_efb(scope) {
    if (typeof valj_efb === 'undefined' || !scope || !scope.querySelectorAll || !V()) return;
    valj_efb.forEach((r, i) => {
      if (i < 1 || !r || !r.id_) return;
      const spec = V().fieldSpec(r);
      const field = document.getElementById(r.id_ + '_');
      const holder = field ? (field.closest('.iti') || field) : null;
      const old = document.getElementById(r.id_ + '_vrf-preview');
      if (!spec || !spec.otp || !holder) { if (old) old.remove(); return; }
      if (old) return;
      const d = document.createElement('div');
      d.id = r.id_ + '_vrf-preview';
      d.className = 'efb efb-vrf-preview mt-1';
      const b = document.createElement('button');
      b.type = 'button';
      b.className = 'efb btn btn-sm efb-vrf-send';
      b.disabled = true;
      b.setAttribute('aria-disabled', 'true');
      b.textContent = (typeof r.otp_btn_label === 'string' && r.otp_btn_label) ? r.otp_btn_label : T('vrfSend', 'Send code');
      d.appendChild(b);
      holder.insertAdjacentElement('afterend', d);
    });
  }

  root.efbVerifyBuilderEls = efbVerifyBuilderEls;
  root.efb_vrf_change_efb = efb_vrf_change_efb;
  root.efb_vrf_gem_efb = efb_vrf_gem_efb;
  root.efb_vrf_decorate_efb = efb_vrf_decorate_efb;
  root.efb_vrf_visibility_efb = efb_vrf_visibility_efb;
  if (typeof document !== 'undefined' && document.addEventListener) {
    // The canvas re-renders on many actions: refresh the hints after them (cheap, idempotent).
    let pending = null;
    const obs = typeof MutationObserver !== 'undefined' ? new MutationObserver(() => { if (pending) return; pending = setTimeout(() => { pending = null; try { efb_vrf_decorate_efb(document); } catch (e) { /* ignore */ } }, 400); }) : null;
    const start = () => { const c = document.getElementById('dropZoneEFB') || document.body; if (obs && c) obs.observe(c, { childList: true, subtree: true }); };
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', start); else start();
  }
}(typeof window !== 'undefined' ? window : this));
