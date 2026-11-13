/**
 * F08 in the builder (docs/feature-gaps/F08-hidden-smart-tags.fa.md §6): the hidden field's panel
 * ("Fill with" presets, parameter / cookie name, the value template, the visitor's e-mail copy), the URL
 * prefill controls of the fields an address may fill (key, live example link, copy, read-only after
 * prefill), the "Insert a smart tag" list next to the label, description, placeholder, default value,
 * thank-you texts, e-mail subject and redirect address, the warnings (unknown tag, not here, later step,
 * tag in the redirect host, IP privacy, sign-in cookies) and the hidden field's bar on the canvas.
 *
 * Keys are written to valj_efb directly with the save rules of EFBSmart (sanitizeKey, cleanTemplate,
 * normalizeRows = Emsfb\Smart_Tags on the server), like the F03 / F07 controls; the other inputs keep
 * their own change_el_edit_Efb handler (an inserted tag goes through it). Everything is escaped; the
 * insert list is a native <select> (keyboard, screen readers, right to left).
 */
(function (root) {
  'use strict';

  const T = (k, fb) => {
    try { if (root.efb_var && root.efb_var.text && root.efb_var.text[k]) return root.efb_var.text[k]; } catch (e) {}
    return fb;
  };
  const esc = (v) => String(v == null ? '' : v).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#039;');
  const S = () => root.EFBSmart || null;
  const icon = () => (typeof iconMarginGlobal !== 'undefined' ? iconMarginGlobal : 'me-1');

  /* Presets of "Fill with" (spec §4.4). */
  function presets() {
    return [
      ['static', T('hdnStatic', 'Fixed value'), ''],
      ['utm_source', T('hdnUtm', 'UTM %s').replace('%s', 'source'), '{get:utm_source}'],
      ['utm_medium', T('hdnUtm', 'UTM %s').replace('%s', 'medium'), '{get:utm_medium}'],
      ['utm_campaign', T('hdnUtm', 'UTM %s').replace('%s', 'campaign'), '{get:utm_campaign}'],
      ['utm_term', T('hdnUtm', 'UTM %s').replace('%s', 'term'), '{get:utm_term}'],
      ['utm_content', T('hdnUtm', 'UTM %s').replace('%s', 'content'), '{get:utm_content}'],
      ['gclid', T('hdnAdClick', 'Ad click ID') + ' (gclid)', '{get:gclid}'],
      ['fbclid', T('hdnAdClick', 'Ad click ID') + ' (fbclid)', '{get:fbclid}'],
      ['msclkid', T('hdnAdClick', 'Ad click ID') + ' (msclkid)', '{get:msclkid}'],
      ['param', T('hdnQueryParam', 'URL parameter'), '{get:%s}'],
      ['ref', T('hdnReferrer', 'Referring page'), '{ref}'],
      ['page_url', T('hdnPageUrl', 'Page address'), '{page:url}'],
      ['page_title', T('hdnPageTitle', 'Page title'), '{page:title}'],
      ['post_id', T('hdnPostId', 'Post ID'), '{post:id}'],
      ['ip', T('hdnIp', 'Visitor IP address'), '{ip}'],
      ['user_id', T('hdnUserId', 'User ID'), '{user:id}'],
      ['user_email', T('hdnUserEmail', 'User email'), '{user:email}'],
      ['user_login', T('hdnUserLogin', 'Username'), '{user:login}'],
      ['user_name', T('hdnUserName', 'User display name'), '{user:display_name}'],
      ['user_role', T('hdnUserRole', 'User role'), '{user:role}'],
      ['date', T('hdnDate', 'Date'), '{date:Y-m-d}'],
      ['time', T('hdnTime', 'Time'), '{date:H:i}'],
      ['datetime', T('hdnDateTime', 'Date and time'), '{date:Y-m-d H:i}'],
      ['uid', T('hdnUid', 'Unique ID'), '{uid}'],
      ['cookie', T('hdnCookie', 'Cookie'), '{cookie:%s}'],
      ['geo', T('hdnCountry', 'Visitor country') + ' — ' + T('soon', 'coming soon'), null],
      ['custom', T('hdnTemplate', 'Value template'), null]
    ];
  }
  /** Which preset a stored template is (and the parameter / cookie name of the two that take one). */
  function presetOf(tpl) {
    tpl = String(tpl || '');
    const m = /^\{(get|cookie):([A-Za-z0-9_\-[\].]{1,64})\}$/.exec(tpl);
    for (const p of presets()) if (p[2] !== null && p[2].indexOf('%s') === -1 && p[2] === tpl && p[0] !== 'static') return { id: p[0], arg: '' };
    if (m) return { id: m[1] === 'get' ? 'param' : 'cookie', arg: m[2] };
    if (!S() || !S().hasTags(tpl)) return { id: 'static', arg: '' };
    return { id: 'custom', arg: '' };
  }
  function rowById(dataId) {
    if (typeof valj_efb === 'undefined') return null;
    const i = valj_efb.findIndex((x) => x && (x.dataId == dataId || x.id_ == dataId));
    return i > 0 ? valj_efb[i] : null;
  }
  function pipingOk() {
    if (typeof efb_capability_available_efb === 'function') { try { return efb_capability_available_efb('setting:answer_piping', isPaid()); } catch (e) {} }
    const cap = root.efb_var && root.efb_var.capabilities && root.efb_var.capabilities.features;
    if (cap && cap['setting:answer_piping']) return !!cap['setting:answer_piping'].available_now;
    return isPaid();
  }
  function isPaid() {
    return (typeof pro_efb !== 'undefined' && pro_efb === true) || !!(root.efb_var && (root.efb_var.pro == '1' || root.efb_var.pro === 1 || root.efb_var.pro === true));
  }

  /* ---------------------------------------------------------------- the hidden field's panel */

  function efbHiddenPanelEls(indx, idset) {
    if (typeof valj_efb === 'undefined' || !valj_efb[indx] || valj_efb[indx].type !== 'hiddenField') return '';
    const r = valj_efb[indx];
    const pr = presetOf(r.value);
    const mailOn = r.hdn_visitor_mail === '1' || r.hdn_visitor_mail === 1;
    const opts = presets().map((p) => `<option value="${p[0]}" ${p[0] === pr.id ? 'selected' : ''} ${p[0] === 'geo' ? 'disabled' : ''}>${esc(p[1])}</option>`).join('');
    const showArg = pr.id === 'param' || pr.id === 'cookie';
    return `<div class="efb mt-2 efb-hdn-panel" id="hdnPanelEl" data-id="${esc(idset)}">
      <div class="efb mt-2 mb-1">
        <label for="hdnPresetEl" class="efb form-label mb-1 fs-7"><i class="efb bi-eye-slash ${icon()}" aria-hidden="true"></i>${esc(T('hdnPreset', 'Fill with'))}</label>
        <select class="efb form-select efb border-d rounded-4 efb-st-ctl" id="hdnPresetEl" data-id="${esc(idset)}" data-key="preset" aria-describedby="hdnWarnEl" onchange="efb_st_change_efb(this)">${opts}</select>
      </div>
      <div class="efb mt-2 mb-1 ${showArg ? '' : 'd-none'}" id="hdnParamBox">
        <label for="hdnParamEl" class="efb form-label mb-1 fs-7" id="hdnParamLab">${esc(pr.id === 'cookie' ? T('hdnCookieName', 'Cookie name') : T('hdnParamName', 'Parameter name'))}</label>
        <input type="text" dir="ltr" maxlength="64" class="efb form-control efb h-d-efb border-d rounded-4 efb-st-ctl" id="hdnParamEl" data-id="${esc(idset)}" data-key="arg" value="${esc(pr.arg)}" placeholder="utm_source" onchange="efb_st_change_efb(this)">
      </div>
      <div class="efb mt-2 mb-1">
        <label for="hdnTemplateEl" class="efb form-label mb-1 fs-7">${esc(pr.id === 'static' ? T('hdnStatic', 'Fixed value') : T('hdnTemplate', 'Value template'))}</label>
        <input type="text" maxlength="500" class="efb form-control efb h-d-efb border-d rounded-4 efb-st-ctl" id="hdnTemplateEl" data-id="${esc(idset)}" data-key="value" data-st-where="hidden" value="${esc(r.value || '')}" aria-describedby="hdnWarnEl" style="unicode-bidi:plaintext" dir="auto" onchange="efb_st_change_efb(this)">
      </div>
      <small class="efb d-block fs-8 text-danger mx-1" id="hdnWarnEl" role="status" aria-live="polite"></small>
      <div class="efb mx-1 my-3">
        <button type="button" id="hdnVisitorMailEl" data-id="${esc(idset)}" data-key="hdn_visitor_mail" class="efb mx-0 btn h-s-efb btn-toggle efb-st-ctl ${mailOn ? 'active' : ''}" aria-pressed="${mailOn ? 'true' : 'false'}" onclick="efb_st_change_efb(this)">
          <div class="efb handle"></div>
        </button>
        <label class="efb form-check-label pt-1" for="hdnVisitorMailEl">${esc(T('hdnVisitorMail', "Include in the visitor's email copy"))}</label>
      </div>
      ${efbPrefillEls(indx, idset)}
    </div>`;
  }

  /* ---------------------------------------------------------------- prefill (spec §4.5) */

  function efbPrefillEls(indx, idset) {
    if (typeof valj_efb === 'undefined' || !valj_efb[indx] || !S()) return '';
    const r = valj_efb[indx];
    if (S().PREFILL_TYPES.indexOf(r.type) === -1) return '';
    const key = typeof r.prefill_key === 'string' ? r.prefill_key : '';
    const lockOn = r.prefill_lock === '1' || r.prefill_lock === 1;
    return `<div class="efb mt-3 efb-prefill" id="prefillBoxEl">
      <label for="prefillKeyEl" class="efb form-label mb-1 fs-7"><i class="efb bi-link-45deg ${icon()}" aria-hidden="true"></i>${esc(T('prefillKey', 'URL parameter for prefill'))}</label>
      <input type="text" dir="ltr" maxlength="40" class="efb form-control efb h-d-efb border-d rounded-4 efb-st-ctl" id="prefillKeyEl" data-id="${esc(idset)}" data-key="prefill_key" value="${esc(key)}" placeholder="email" aria-describedby="prefillExampleEl prefillMsgEl" onchange="efb_st_change_efb(this)">
      <small class="efb d-block fs-8 text-danger mx-1" id="prefillMsgEl" role="status" aria-live="polite"></small>
      <div class="efb d-flex align-items-center gap-1 mt-1 ${key ? '' : 'd-none'}" id="prefillExampleBox">
        <small class="efb text-muted fs-8">${esc(T('prefillExample', 'Example link'))}:</small>
        <code class="efb fs-8 text-break flex-grow-1" dir="ltr" id="prefillExampleEl">${esc(exampleUrl(key))}</code>
        <button type="button" class="efb btn btn-sm btn-outline-secondary py-0 px-1" id="prefillCopyEl" onclick="efb_st_copy_example_efb()" aria-label="${esc(T('copy', 'Copy'))}"><i class="efb bi-clipboard" aria-hidden="true"></i></button>
      </div>
      <div class="efb mx-1 my-2 ${r.type === 'hiddenField' ? 'd-none' : ''}">
        <button type="button" id="prefillLockEl" data-id="${esc(idset)}" data-key="prefill_lock" class="efb mx-0 btn h-s-efb btn-toggle efb-st-ctl ${lockOn ? 'active' : ''}" aria-pressed="${lockOn ? 'true' : 'false'}" onclick="efb_st_change_efb(this)">
          <div class="efb handle"></div>
        </button>
        <label class="efb form-check-label pt-1" for="prefillLockEl">${esc(T('prefillLock', 'Make read-only after prefill'))}</label>
      </div>
    </div>`;
  }
  function exampleUrl(key) {
    let base = '';
    try { base = (root.efb_var && root.efb_var.home_url) || (root.location ? root.location.origin + '/' : ''); } catch (e) { base = ''; }
    return (base || 'https://example.com/') + 'page/?' + (key || 'key') + '=…';
  }
  function efb_st_copy_example_efb() {
    const el = document.getElementById('prefillExampleEl');
    if (!el) return;
    const text = el.textContent;
    try { if (navigator.clipboard) navigator.clipboard.writeText(text); } catch (e) {}
  }

  /* ---------------------------------------------------------------- handler */

  function efb_st_change_efb(el) {
    if (!el || !S()) return;
    const key = el.getAttribute('data-key');
    const r = rowById(el.getAttribute('data-id'));
    if (!r) return;
    if (el.classList.contains('btn-toggle')) {
      const on = !el.classList.contains('active');
      el.classList.toggle('active', on);
      el.setAttribute('aria-pressed', on ? 'true' : 'false');
      r[key] = S().sanitizeKey(key, on ? '1' : '');
      if (r[key] === '') delete r[key];
    } else if (key === 'preset' || key === 'arg') {
      const presetEl = document.getElementById('hdnPresetEl');
      const argEl = document.getElementById('hdnParamEl');
      const id = presetEl ? presetEl.value : 'static';
      const p = presets().find((x) => x[0] === id);
      const box = document.getElementById('hdnParamBox');
      if (box) box.classList.toggle('d-none', !(id === 'param' || id === 'cookie'));
      const lab = document.getElementById('hdnParamLab');
      if (lab) lab.textContent = id === 'cookie' ? T('hdnCookieName', 'Cookie name') : T('hdnParamName', 'Parameter name');
      if (p && p[2] !== null) {
        let arg = argEl ? String(argEl.value || '').trim() : '';
        if (p[2].indexOf('%s') !== -1) {
          arg = arg.replace(/[^A-Za-z0-9_\-[\].]/g, '').slice(0, 64);
          if (argEl) argEl.value = arg;
          r.value = arg ? p[2].replace('%s', arg) : '';
        } else if (id !== 'static') {
          r.value = p[2];
        } else if (S().hasTags(r.value)) {
          r.value = '';
        }
        const t = document.getElementById('hdnTemplateEl');
        if (t) t.value = r.value;
      }
    } else if (key === 'value') {
      r.value = S().cleanTemplate(sanitizeText(el.value));
      if (el.value !== r.value) el.value = r.value;
      const pr = presetOf(r.value);
      const presetEl = document.getElementById('hdnPresetEl');
      if (presetEl) presetEl.value = pr.id;
      const argEl = document.getElementById('hdnParamEl');
      if (argEl) argEl.value = pr.arg;
      const box = document.getElementById('hdnParamBox');
      if (box) box.classList.toggle('d-none', !(pr.id === 'param' || pr.id === 'cookie'));
    } else if (key === 'prefill_key') {
      const v = S().sanitizeKey('prefill_key', el.value);
      const msg = document.getElementById('prefillMsgEl');
      const dup = v !== '' && typeof valj_efb !== 'undefined' && valj_efb.some((x, i) => i > 0 && x && x !== r && typeof x.prefill_key === 'string' && x.prefill_key.toLowerCase() === v.toLowerCase());
      let text = '';
      if (el.value.trim() !== '' && v === '') text = /^[A-Za-z0-9_-]{1,40}$/.test(el.value.trim()) ? T('prefillReserved', 'WordPress already uses this parameter. Choose another name.') : T('prefillInvalid', 'Use letters, digits, "_" or "-" (up to 40).');
      else if (dup) text = T('prefillDup', 'Another field already uses this parameter.');
      if (msg) msg.textContent = text;
      if (text === '') {
        if (v === '') { delete r.prefill_key; delete r.prefill_lock; } else r.prefill_key = v;
      }
      const ex = document.getElementById('prefillExampleEl');
      if (ex) ex.textContent = exampleUrl(r.prefill_key || '');
      const exBox = document.getElementById('prefillExampleBox');
      if (exBox) exBox.classList.toggle('d-none', !r.prefill_key);
    }
    if (r.type === 'hiddenField') { warnHidden(r); canvas(r); }
    if (typeof efbApiNoteUserEditEfb === 'function') { try { efbApiNoteUserEditEfb(); } catch (e) {} }
  }
  function sanitizeText(v) {
    if (typeof sanitize_text_efb === 'function') { try { return sanitize_text_efb(String(v)); } catch (e) {} }
    return String(v == null ? '' : v).replace(/<[^>]*>/g, '').replace(/%[0-9a-f]{2}/gi, '').replace(/[\r\n\t ]+/g, ' ').trim();
  }

  /* ---------------------------------------------------------------- warnings */

  /** The builder's notes on one text (spec §4.2, §4.3, §12.3): [{code, text}]. */
  function warnings(text, where, row) {
    const out = [];
    if (!S() || typeof text !== 'string' || text.indexOf('{') === -1) return out;
    const unknown = text.match(/\{([a-z][a-z0-9_]{0,15}):[^{}]{0,200}\}/g) || [];
    unknown.forEach((u) => { const ns = /^\{([a-z][a-z0-9_]*)/.exec(u)[1]; if (S().NS.indexOf(ns) === -1) out.push({ code: 'stUnknown', text: T('stUnknown', 'Unknown smart tag: %s').replace('%s', u) }); });
    S().tags(text).forEach((t) => {
      const raw = t.raw;
      if (where && !S().allowed(where, t.ns, t.ns === 'field' ? '' : t.arg)) out.push({ code: 'stNotHere', text: T('stNotHere', '%s cannot be used here.').replace('%s', raw) });
      if (t.ns === 'ip') out.push({ code: 'stIpPrivacy', text: T('stIpPrivacy', 'Storing IP addresses may require consent in your country. Check the privacy settings.') });
      if (t.ns === 'cookie' && !S().cookieOk(t.arg, {})) out.push({ code: 'stCookieDenied', text: T('stCookieDenied', 'WordPress sign-in cookies cannot be read.') });
      if (t.ns === 'field' && row && typeof valj_efb !== 'undefined') {
        const fid = t.arg.replace(/\.(label|value)$/, '');
        const f = valj_efb.find((x, i) => i > 0 && x && x.id_ === fid);
        if (f && Number(f.step || 1) > Number(row.step || 1) && where !== 'hidden' && where !== 'message') out.push({ code: 'stFieldLater', text: T('stFieldLater', 'This answer comes from a later step, so it will be empty here.') });
      }
    });
    if (where === 'redirect' && S().urlTagMisplaced(text)) out.push({ code: 'stTagUrlPart', text: T('stTagUrlPart', 'Smart tags can only be used after "?" or "#" in a redirect address.') });
    const seen = {};
    return out.filter((w) => (seen[w.text] ? false : (seen[w.text] = true)));
  }
  function warnHidden(r) {
    const box = document.getElementById('hdnWarnEl');
    if (box) box.textContent = warnings(r.value || '', 'hidden', r).map((w) => w.text).join(' ');
  }

  /* ---------------------------------------------------------------- insert list */

  function catalog() {
    return [
      ['{get:utm_source}', T('hdnUtm', 'UTM %s').replace('%s', 'source')], ['{get:name}', T('hdnQueryParam', 'URL parameter')], ['{cookie:name}', T('hdnCookie', 'Cookie')],
      ['{ref}', T('hdnReferrer', 'Referring page')], ['{page:url}', T('hdnPageUrl', 'Page address')], ['{page:title}', T('hdnPageTitle', 'Page title')],
      ['{post:id}', T('hdnPostId', 'Post ID')], ['{post:title}', 'Post title'], ['{user:display_name}', T('hdnUserName', 'User display name')],
      ['{user:first_name}', 'User first name'], ['{user:email}', T('hdnUserEmail', 'User email')], ['{user:id}', T('hdnUserId', 'User ID')],
      ['{user:login}', T('hdnUserLogin', 'Username')], ['{user:role}', T('hdnUserRole', 'User role')], ['{date:Y-m-d}', T('hdnDate', 'Date')],
      ['{date:H:i}', T('hdnTime', 'Time')], ['{ip}', T('hdnIp', 'Visitor IP address')], ['{uid}', T('hdnUid', 'Unique ID')],
      ['{form:name}', 'Form name'], ['{site:name}', 'Site name'], ['{site:url}', 'Site address'], ['{entry:track}', T('trackingCode', 'Confirmation code')]
    ];
  }
  /** A <select> that inserts a tag allowed in `where` at the caret of the input `targetId`. */
  function insertSelect(targetId, where, row) {
    if (!S()) return null;
    const sel = document.createElement('select');
    sel.className = 'efb form-select form-select-sm efb border-d rounded-4 mt-1 efb-st-insert';
    sel.id = targetId + 'StIns';
    sel.setAttribute('aria-label', T('stInsert', 'Insert a smart tag'));
    sel.setAttribute('data-target', targetId);
    sel.setAttribute('data-where', where);
    const first = document.createElement('option');
    first.value = ''; first.textContent = '{…} ' + T('stInsert', 'Insert a smart tag');
    sel.appendChild(first);
    const piping = pipingOk();
    if (typeof valj_efb !== 'undefined' && S().allowed(where, 'field', '')) {
      const grp = document.createElement('optgroup');
      grp.label = T('fields', 'Fields');
      valj_efb.forEach((f, i) => {
        if (i < 1 || !f || !f.id_ || ['option', 'r_matrix', 'step', 'html', 'heading', 'link', 'esign', 'maps', 'password'].indexOf(f.type) !== -1) return;
        if (row && f.id_ === row.id_) return;
        const o = document.createElement('option');
        o.value = '{field:' + f.id_ + '}';
        o.textContent = (f.name || f.type) + ' — {field:' + f.id_ + '}';
        if (!piping && (where === 'label')) { o.disabled = true; o.textContent += ' (' + T('upgTipFreePlus', 'Free Plus') + ')'; }
        grp.appendChild(o);
      });
      if (grp.children.length) sel.appendChild(grp);
    }
    catalog().forEach((c) => {
      const p = S().parse(c[0])[0];
      if (!p || !S().allowed(where, p.ns, p.ns === 'field' ? '' : p.arg)) return;
      const o = document.createElement('option');
      o.value = c[0];
      o.textContent = c[1] + ' — ' + c[0];
      sel.appendChild(o);
    });
    sel.addEventListener('change', () => {
      const tag = sel.value;
      sel.value = '';
      if (!tag) return;
      const t = document.getElementById(targetId);
      if (!t) return;
      const a = typeof t.selectionStart === 'number' ? t.selectionStart : t.value.length;
      const b = typeof t.selectionEnd === 'number' ? t.selectionEnd : a;
      t.value = t.value.slice(0, a) + tag + t.value.slice(b);
      try { t.focus(); t.setSelectionRange(a + tag.length, a + tag.length); } catch (e) {}
      t.dispatchEvent(new Event('change', { bubbles: true }));
      noteField(targetId, where, row);
    });
    return sel;
  }
  function noteField(targetId, where, row) {
    const t = document.getElementById(targetId);
    if (!t) return;
    let n = document.getElementById(targetId + 'StWarn');
    if (!n) {
      n = document.createElement('small');
      n.id = targetId + 'StWarn';
      n.className = 'efb d-block fs-8 text-danger mx-1 efb-st-warn';
      n.setAttribute('role', 'status');
      n.setAttribute('aria-live', 'polite');
      const sel = document.getElementById(targetId + 'StIns');
      (sel || t).insertAdjacentElement('afterend', n);
    }
    n.textContent = warnings(t.value, where, row).map((w) => w.text).join(' ');
  }
  const PLACES = [
    ['labelEl', 'label'], ['desEl', 'label'], ['placeholderEl', 'placeholder'], ['valueEl', 'default'],
    ['thankYouMessageEl', 'message'], ['thankYouMessageDoneEl', 'message'], ['FormEmailSubjectEl', 'email_admin'], ['thankYouredirectEl', 'redirect'],
    ['hdnTemplateEl', 'hidden']
  ];
  /** After the settings panel is drawn: the insert lists and live notes next to the inputs that take tags. */
  function efbSmartPanelInit(idset, indx) {
    if (!S() || typeof document === 'undefined') return;
    const row = typeof valj_efb !== 'undefined' && indx > 0 ? valj_efb[indx] : null;
    PLACES.forEach(([id, where]) => {
      const el = document.getElementById(id);
      if (!el || el.disabled || document.getElementById(id + 'StIns')) return;
      if (id === 'valueEl' && row && S().DEFAULT_TYPES.indexOf(row.type) === -1 && row.type !== 'heading') return;
      if (id === 'labelEl' && row && row.type === 'hiddenField') return;
      const w = id === 'valueEl' && row && row.type === 'heading' ? 'label' : where;
      const sel = insertSelect(id, w, row);
      if (!sel) return;
      el.insertAdjacentElement('afterend', sel);
      el.addEventListener('input', () => noteField(id, w, row));
      if (el.value && el.value.indexOf('{') !== -1) noteField(id, w, row);
    });
    if (row && row.type === 'hiddenField') warnHidden(row);
  }

  /* ---------------------------------------------------------------- canvas */

  /** The hidden field on the canvas: a grey bar with its name and template (preview: nothing visible). */
  function efbHiddenCanvas(r, preview) {
    const tpl = String((r && r.value) || '');
    if (preview) return `<div class="efb d-none" id="${esc(r.id_)}-f"><input type="hidden" class="efb emsFormBuilder_v" data-type="hiddenField" data-vid="${esc(r.id_)}" id="${esc(r.id_)}_" value="">`;
    return `<div class="efb col-sm-12 px-0 mx-0" id="${esc(r.id_)}-f">
      <div class="efb efb-hdn-bar d-flex align-items-center gap-2 rounded-3 px-2 py-2 my-1 bg-light border text-muted fs-7" style="unicode-bidi:plaintext">
        <i class="efb bi-eye-slash" aria-hidden="true"></i>
        <span class="efb fw-bold" id="${esc(r.id_)}_lab">${esc(r.name || T('hiddenField', 'Hidden field'))}</span>
        <span class="efb badge bg-secondary">${esc(T('hdnBadge', 'Hidden'))}</span>
        <code class="efb text-break" dir="ltr" id="${esc(r.id_)}_tpl">${esc(tpl)}</code>
      </div>
      <input type="hidden" class="efb" data-type="hiddenField" data-vid="${esc(r.id_)}" id="${esc(r.id_)}_" value="">`;
  }
  function canvas(r) {
    const c = document.getElementById(r.id_ + '_tpl');
    if (c) c.textContent = String(r.value || '');
  }

  const api = { efbHiddenPanelEls, efbPrefillEls, efb_st_change_efb, efb_st_copy_example_efb, efbSmartPanelInit, efbHiddenCanvas, efbSmartWarnings: warnings, efbSmartPresetOf: presetOf, efbSmartPresets: presets };
  Object.keys(api).forEach((k) => { root[k] = api[k]; });
  if (typeof module === 'object' && module.exports) module.exports = api;
})(typeof window !== 'undefined' ? window : globalThis);
