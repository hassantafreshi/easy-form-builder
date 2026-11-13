/**
 * F11 in the builder: the "Access and limits" section of the form settings (formSet), its
 * handlers, and the browser twin of the per-form CSS cleaner (Emsfb\Form_Css).
 * Spec: docs/feature-gaps/F11-form-limits-resume.fa.md ("تغییرات سازنده یا پیشخوان").
 *
 * Every key lives in valj_efb[0] as a string ('' = not set) and is checked again on the
 * server (sanitize_obj_msg_efb → Form_Gate::sanitize_key); these controls write directly,
 * without change_el_edit_Efb (which refuses " < > that CSS needs and runs on a timer).
 * Numbers accept Persian and Arabic digits. efbBuilderApi.setFormSettings() uses the same
 * rules (efb_limits_validate_efb).
 */
(function (root) {
  'use strict';

  const T = (k, fb) => {
    try { if (root.efb_var && root.efb_var.text && root.efb_var.text[k]) return root.efb_var.text[k]; } catch (e) {}
    return fb;
  };
  const esc = (v) => String(v == null ? '' : v).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#039;');

  /* Persian (۰-۹) and Arabic-Indic (٠-٩) digits → Latin (copy of core-efb.js efb_ascii_digits_efb, which the builder does not load). */
  function efb_builder_ascii_digits_efb(str) {
    return String(str == null ? '' : str)
      .replace(/[۰-۹]/g, (d) => String(d.charCodeAt(0) - 0x06F0))
      .replace(/[٠-٩]/g, (d) => String(d.charCodeAt(0) - 0x0660));
  }

  /* ------------------------------------------------------------------ rules (twin of Form_Gate::sanitize_key) */
  const PERIODS = ['ever', 'day', 'week', 'month', 'year'];
  const RANGES = { lim_total: [1, 10000000], lim_user: [1, 100000], lim_ip: [1, 100000], lim_cookie: [1, 100000], lim_email: [1, 100000], sched_grace: [0, 60], draft_days: [1, 90], partial_days: [1, 365] };
  const BOOLS = ['lim_show_left', 'draft_on', 'partial_on'];
  const TEXTS = ['lim_full_msg', 'lim_user_msg', 'sched_pending_msg', 'sched_closed_msg'];
  const KEYS = ['lim_total', 'lim_user', 'lim_ip', 'lim_cookie', 'lim_email', 'lim_period', 'lim_email_field', 'lim_show_left', 'lim_full_msg', 'lim_user_msg', 'sched_open', 'sched_close', 'sched_grace', 'sched_pending_msg', 'sched_closed_msg', 'allowed_roles', 'custom_css', 'draft_on', 'draft_days', 'partial_on', 'partial_days', 'partial_consent', 'partial_need_contact'];

  function validDateTime(v) {
    if (!/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/.test(v)) return false;
    const [d, t] = v.split('T');
    const [y, m, dd] = d.split('-').map(Number);
    const [h, mi] = t.split(':').map(Number);
    const x = new Date(Date.UTC(y, m - 1, dd, h, mi));
    return x.getUTCFullYear() === y && x.getUTCMonth() === m - 1 && x.getUTCDate() === dd && x.getUTCHours() === h && x.getUTCMinutes() === mi;
  }

  /** The stored value of one key as the server keeps it; null = not an F11 key. */
  function efb_limits_value_efb(key, v) {
    if (RANGES[key]) {
      if (typeof v === 'boolean' || v == null || typeof v === 'object') return '';
      const d = efb_builder_ascii_digits_efb(String(v).replace(/[\s,\u066C\u060C]+/g, ''));
      if (!/^\d{1,9}$/.test(d)) return '';
      const n = Number(d);
      return (n < RANGES[key][0] || n > RANGES[key][1]) ? '' : String(n);
    }
    if (BOOLS.includes(key)) return [true, 1, '1', 'true', 'on'].includes(v) ? '1' : '';
    if (key === 'partial_need_contact') return [true, 1, '1', 'true', 'on'].includes(v) ? '1' : '0';
    if (key === 'lim_period') return PERIODS.includes(v) ? v : '';
    if (key === 'lim_email_field') return typeof v === 'string' && /^[A-Za-z0-9_-]{1,64}$/.test(v) ? v : '';
    if (TEXTS.includes(key)) return String(v == null ? '' : v).replace(/<[^>]*>?/g, '').replace(/[\r\n\t]+/g, ' ').replace(/\s{2,}/g, ' ').trim().slice(0, 300);
    if (key === 'sched_open' || key === 'sched_close') {
      const s = efb_builder_ascii_digits_efb(String(v == null ? '' : v).trim()).replace(' ', 'T');
      return validDateTime(s) ? s : '';
    }
    if (key === 'partial_consent') return v === 'notice' ? 'notice' : 'checkbox';
    if (key === 'allowed_roles') {
      const known = (root.efb_var && root.efb_var.wp_roles) || {};
      const out = [];
      String(v == null ? '' : v).split(',').forEach((r) => { r = r.trim().toLowerCase().replace(/[^a-z0-9_-]/g, ''); if (r && Object.prototype.hasOwnProperty.call(known, r) && !out.includes(r)) out.push(r); });
      return out.join(',');
    }
    if (key === 'custom_css') {
      const css = efb_css_decode_efb(v);
      return css === '' ? '' : efb_css_encode_efb(efb_css_sanitize_efb(css));
    }
    return null;
  }

  /** Errors of a patch for row 0 (efbBuilderApi.setFormSettings): [{key, code, message}]. */
  function efb_limits_validate_efb(patch, row0) {
    const errs = [];
    const next = Object.assign({}, row0 || {}, patch || {});
    Object.keys(patch || {}).forEach((k) => {
      if (!KEYS.includes(k)) return;
      const v = patch[k];
      if (v !== '' && v != null && efb_limits_value_efb(k, v) === '' && !BOOLS.includes(k)) errs.push({ key: k, code: 'invalid_setting', message: k + ' has a value the server would not keep.' });
    });
    if (next.sched_open && next.sched_close && validDateTime(String(next.sched_open)) && validDateTime(String(next.sched_close)) && String(next.sched_close) <= String(next.sched_open)) {
      errs.push({ key: 'sched_close', code: 'sched_order', message: T('schedOrderErr', 'The form must close after it opens.') });
    }
    if (Object.prototype.hasOwnProperty.call(patch || {}, 'custom_css') && patch.custom_css && !(root.efb_var && root.efb_var.can_unfiltered_html)) {
      errs.push({ key: 'custom_css', code: 'no_unfiltered_html', message: T('customCssNoPerm', 'Only users who may post unfiltered HTML can change this CSS.') });
    }
    return errs;
  }

  /* ------------------------------------------------------------------ CSS (twin of Emsfb\Form_Css) */
  const CSS_MAX = 20480;
  const WS = ' \t\n\r\0\x0B\f';
  const trimWs = (s) => { let a = 0, b = s.length; while (a < b && WS.indexOf(s[a]) !== -1) a++; while (b > a && WS.indexOf(s[b - 1]) !== -1) b--; return s.slice(a, b); };
  const lower = (s) => s.replace(/[A-Z]/g, (c) => c.toLowerCase());
  const utf8Len = (s) => { try { return new TextEncoder().encode(s).length; } catch (e) { return unescape(encodeURIComponent(s)).length; } };

  function efb_css_decode_efb(stored) {
    if (typeof stored !== 'string' || stored.indexOf('b64:') !== 0) return '';
    try {
      const bin = atob(stored.slice(4));
      if (bin.length > CSS_MAX) return '';
      return decodeURIComponent(escape(bin));
    } catch (e) { return ''; }
  }
  function efb_css_encode_efb(css) {
    return css === '' ? '' : 'b64:' + btoa(unescape(encodeURIComponent(css)));
  }
  function skipString(s, i) {
    const q = s[i]; const n = s.length; i++;
    while (i < n) { const c = s[i]; if (c === '\\') { i += 2; continue; } if (c === q) return i + 1; i++; }
    return n;
  }
  function stripComments(s) {
    let out = ''; const n = s.length; let i = 0;
    while (i < n) {
      const c = s[i];
      if (c === '"' || c === "'") { const j = skipString(s, i); out += s.slice(i, j); i = j; continue; }
      if (c === '\\') { out += s.slice(i, i + 2); i += 2; continue; }
      if (c === '/' && s[i + 1] === '*') { const e = s.indexOf('*/', i + 2); i = e === -1 ? n : e + 2; continue; }
      out += c; i++;
    }
    return out;
  }
  function parse(s) {
    const rules = []; const n = s.length; let i = 0; let start = 0;
    while (i < n) {
      const c = s[i];
      if (c === '"' || c === "'") { i = skipString(s, i); continue; }
      if (c === '\\') { i += 2; continue; }
      if (c === ';') { rules.push([s.slice(start, i), null]); i++; start = i; continue; }
      if (c === '}') { i++; start = i; continue; }
      if (c === '{') {
        let depth = 1; let j = i + 1;
        while (j < n && depth > 0) {
          const d = s[j];
          if (d === '"' || d === "'") { j = skipString(s, j); continue; }
          if (d === '\\') { j += 2; continue; }
          if (d === '{') depth++; else if (d === '}') depth--;
          j++;
        }
        if (depth > 0) break;
        rules.push([s.slice(start, i), s.slice(i + 1, j - 1)]);
        i = j; start = i; continue;
      }
      i++;
    }
    return rules;
  }
  function decodeEscapes(s) {
    s = s.replace(/\\([0-9a-fA-F]{1,6})[ \t\n\r\f]?/g, (m, h) => {
      let cp = parseInt(h, 16);
      if (cp === 0 || cp > 0x10FFFF || (cp >= 0xD800 && cp <= 0xDFFF)) cp = 0xFFFD;
      return String.fromCodePoint(cp);
    });
    s = s.replace(/\\([^\n])/g, '$1');
    return s.replace(/\\/g, '');
  }
  function urlOk(u) {
    const l = lower(trimWs(u));
    if (l === '') return true;
    if (l.indexOf('https:') === 0) return true;
    if (l.indexOf('data:') === 0) return /^data:image\/(png|gif|jpeg|webp)[;,]/.test(l);
    if (l.indexOf('//') === 0) return false;
    return !/^[a-z][a-z0-9+.\-]*:/.test(l);
  }
  function dangerous(text) {
    const d = decodeEscapes(text);
    const l = lower(d);
    const c = l.replace(/[ \t\n\r\f\x0B]+/g, '');
    if (d.indexOf('<') !== -1) return true;
    for (const bad of ['@import', '@charset', '@namespace', 'expression(', '-moz-binding', 'behavior:', 'behaviour:', 'javascript:', 'vbscript:']) if (c.indexOf(bad) !== -1) return true;
    let off = 0, p;
    while ((p = l.indexOf('url(', off)) !== -1) {
      off = p + 4;
      let k = off; const n = d.length;
      while (k < n && ' \t\r\n\f'.indexOf(d[k]) !== -1) k++;
      let arg;
      if (k < n && (d[k] === '"' || d[k] === "'")) { const q = d[k]; const e = d.indexOf(q, k + 1); arg = e === -1 ? d.slice(k + 1) : d.slice(k + 1, e); }
      else { const e = d.indexOf(')', k); arg = e === -1 ? d.slice(k) : d.slice(k, e); }
      if (!urlOk(arg)) return true;
    }
    for (const fn of ['image-set(', 'src(']) {
      off = 0;
      while ((p = l.indexOf(fn, off)) !== -1) {
        off = p + fn.length;
        const e = d.indexOf(')', off);
        const args = e === -1 ? d.slice(off) : d.slice(off, e);
        const re = /"([^"]*)"|'([^']*)'/g; let m;
        while ((m = re.exec(args))) if (!urlOk(m[2] !== undefined ? m[2] : m[1])) return true;
      }
    }
    return false;
  }
  function cleanList(rules) {
    const out = [];
    for (const r of rules) {
      const pre = trimWs(r[0]); const body = r[1];
      if (pre === '') continue;
      if (pre[0] === '@') {
        const m = /^@([-a-zA-Z0-9_]+)/.exec(pre); if (!m) continue;
        const name = lower(m[1]);
        if (body === null) { if (name === 'layer' && !dangerous(pre) && /^@layer\s+[-\w\s,.]+$/i.test(pre)) out.push(['stmt', pre + ';']); continue; }
        if (['media', 'supports', 'container', 'layer'].includes(name)) { if (dangerous(pre)) continue; const kids = cleanList(parse(body)); if (kids.length) out.push(['group', pre, kids]); continue; }
        if (['font-face', 'keyframes', '-webkit-keyframes'].includes(name)) { if (!dangerous(pre + '{' + body + '}')) out.push(['raw', pre, trimWs(body)]); continue; }
        continue;
      }
      if (body === null) continue;
      if (dangerous(pre + '{' + body + '}')) continue;
      out.push(['rule', pre, trimWs(body)]);
    }
    return out;
  }
  function splitSelectors(s) {
    const out = []; let depth = 0; let cur = '';
    for (let i = 0; i < s.length; i++) {
      const c = s[i];
      if (c === '"' || c === "'") { const j = skipString(s, i); cur += s.slice(i, j); i = j - 1; continue; }
      if (c === '\\') { cur += s.slice(i, i + 2); i++; continue; }
      if (c === '(' || c === '[') depth++; else if ((c === ')' || c === ']') && depth > 0) depth--;
      if (c === ',' && depth === 0) { out.push(cur); cur = ''; continue; }
      cur += c;
    }
    out.push(cur);
    return out;
  }
  function scopeOne(sel, scope) {
    let s = trimWs(sel.replace(/[ \t\n\r\f\x0B]+/g, ' '));
    if (s === '') return '';
    if (s.indexOf(scope) === 0 && (s.length === scope.length || !/[-_a-zA-Z0-9]/.test(s[scope.length]))) return s;
    let isRoot = false; let sep = ' '; let m;
    while ((m = /^(?::root|html|body)(?![-_a-zA-Z0-9(])/i.exec(s))) {
      isRoot = true;
      s = s.slice(m[0].length);
      const w = /^\s*>?\s*/.exec(s);
      if (s !== '' && w && w[0] !== '') { s = s.slice(w[0].length); sep = ' '; } else { sep = ''; break; }
    }
    if (isRoot) return s === '' ? scope : scope + sep + s;
    return scope + ' ' + s;
  }
  function serialize(nodes, scope) {
    const parts = [];
    for (const n of nodes) {
      if (n[0] === 'stmt') parts.push(n[1]);
      else if (n[0] === 'raw') parts.push(n[1] + '{' + n[2] + '}');
      else if (n[0] === 'group') parts.push(n[1] + '{\n' + serialize(n[2], scope) + '\n}');
      else {
        const sel = scope == null ? n[1] : splitSelectors(n[1]).map((x) => scopeOne(x, scope)).filter((x) => x !== '').join(',');
        if (sel !== '') parts.push(sel + '{' + n[2] + '}');
      }
    }
    return parts.join('\n');
  }
  function efb_css_sanitize_efb(css) {
    if (typeof css !== 'string' || css === '' || utf8Len(css) > CSS_MAX) return '';
    return serialize(cleanList(parse(stripComments(css.replace(/\0/g, '')))), null);
  }
  function efb_css_scope_efb(css, scope) {
    return serialize(cleanList(parse(css)), scope);
  }
  /** Number of rules the cleaner removed (for the builder note). */
  function efb_css_dropped_efb(css) {
    if (typeof css !== 'string' || css === '') return 0;
    const count = (nodes) => nodes.reduce((a, n) => a + (n[0] === 'group' ? count(n[2]) : 1), 0);
    const all = parse(stripComments(css));
    let total = 0;
    all.forEach((r) => { const pre = trimWs(r[0]); if (pre !== '') total += (r[1] !== null && /^@(media|supports|container|layer)\b/i.test(pre)) ? Math.max(1, parse(r[1]).length) : 1; });
    return Math.max(0, total - count(cleanList(all)));
  }
  /** Live preview on the builder canvas (scoped to #dropZoneEFB). */
  function efb_scope_css_preview_efb(css) {
    if (typeof document === 'undefined') return '';
    let st = document.getElementById('efb-custom-css-preview');
    if (!st) { st = document.createElement('style'); st.id = 'efb-custom-css-preview'; document.head.appendChild(st); }
    const out = efb_css_scope_efb(efb_css_sanitize_efb(css || ''), '#dropZoneEFB').replace(/</g, '');
    st.textContent = out;
    return out;
  }

  /* ------------------------------------------------------------------ builder section */
  const row0 = () => (typeof valj_efb !== 'undefined' && valj_efb[0]) ? valj_efb[0] : {};
  const val = (k) => { const r = row0(); return Object.prototype.hasOwnProperty.call(r, k) && r[k] != null ? String(r[k]) : ''; };
  const on = (k) => ['1', 'true'].includes(val(k));
  const icon = () => (typeof iconMarginGlobal !== 'undefined' ? iconMarginGlobal : 'me-1');
  const f11 = () => (root.efb_var && root.efb_var.f11) || {};

  function lock(planKey) {
    const ok = planKey === 'person' ? f11().plan_person !== false : planKey === 'drafts' ? f11().plan_drafts !== false : true;
    return ok ? '' : ' disabled aria-disabled="true"';
  }
  function gem(planKey) {
    const ok = planKey === 'person' ? f11().plan_person !== false : f11().plan_drafts !== false;
    if (ok) return '';
    const cap = planKey === 'person' ? 'setting:form_person_limits' : 'setting:form_drafts';
    return ` <button type="button" class="efb btn btn-link p-0 fs-7 efb-f11-gem" data-cap="${cap}" onclick="efb_limits_gem_efb(this)"><i class="efb bi-gem" aria-hidden="true"></i><span class="efb visually-hidden">${esc(T('thisFeatureAvailableFreePlusPro', 'Upgrade'))}</span></button>`;
  }
  function numEl(id, key, label, min, max, extra, plan) {
    return `<div class="efb mt-2 mb-2">
      <label for="${id}" class="efb mb-1">${esc(label)}${plan ? gem(plan) : ''}</label>
      <input type="text" inputmode="numeric" maxlength="9" class="efb form-control efb h-d-efb border-d rounded-4 efb-f11" id="${id}" data-key="${key}" value="${esc(val(key))}" aria-describedby="${id}Help" onchange="efb_limits_change_efb(this)"${plan ? lock(plan) : ''}>
      ${extra ? `<small class="efb form-text text-muted d-block mt-1" id="${id}Help">${extra}</small>` : ''}
    </div>`;
  }
  function textEl(id, key, label) {
    return `<div class="efb mt-2 mb-2">
      <label for="${id}" class="efb mb-1">${esc(label)}</label>
      <input type="text" maxlength="300" class="efb form-control efb h-d-efb border-d rounded-4 efb-f11" id="${id}" data-key="${key}" value="${esc(val(key))}" onchange="efb_limits_change_efb(this)">
    </div>`;
  }
  function toggleEl(id, key, label, plan, isOn) {
    const active = isOn === undefined ? on(key) : isOn;
    return `<div class="efb mx-1 my-3 efb">
      <button type="button" id="${id}" data-key="${key}" class="efb mx-0 btn h-s-efb btn-toggle efb-f11 ${active ? 'active' : ''}" aria-pressed="${active ? 'true' : 'false'}" autocomplete="off" onclick="efb_limits_change_efb(this)"${plan ? lock(plan) : ''}>
      <div class="efb handle"></div>
      </button>
      <label class="efb form-check-label" for="${id}">${esc(label)}${plan ? gem(plan) : ''}</label>
    </div>`;
  }
  function calendarHint(v) {
    if (!validDateTime(v)) return '';
    const lang = String((root.efb_var && (root.efb_var.wp_lan || root.efb_var.language)) || '').slice(0, 2);
    const cal = lang === 'fa' ? 'fa-IR-u-ca-persian' : (lang === 'ar' ? 'ar-SA-u-ca-islamic-umalqura' : '');
    if (!cal || typeof Intl === 'undefined') return '';
    const [d, t] = v.split('T'); const [y, m, dd] = d.split('-').map(Number); const [h, mi] = t.split(':').map(Number);
    try { return new Intl.DateTimeFormat(cal, { dateStyle: 'medium', timeStyle: 'short', timeZone: 'UTC' }).format(new Date(Date.UTC(y, m - 1, dd, h, mi))); } catch (e) { return ''; }
  }
  function dateEl(id, key, label) {
    const v = val(key);
    const now = esc(String(f11().site_now || (root.efb_var && root.efb_var.site_now) || '').replace('T', ' '));
    const tz = esc((root.efb_var && root.efb_var.site_tz) || '');
    return `<div class="efb mt-2 mb-2">
      <label for="${id}" class="efb mb-1"><i class="efb bi-calendar-event fs-7 ${icon()}" aria-hidden="true"></i>${esc(label)}</label>
      <input type="datetime-local" class="efb form-control efb h-d-efb border-d rounded-4 efb-f11" id="${id}" data-key="${key}" value="${esc(v)}" aria-describedby="${id}Help" onchange="efb_limits_change_efb(this)">
      <small class="efb form-text text-muted d-block mt-1" id="${id}Help"><span class="efb efb-f11-cal" id="${id}Cal">${esc(calendarHint(v))}</span> ${esc(T('schedSiteTime', 'Site time now: %1$s (%2$s)').replace('%1$s', now).replace('%2$s', tz))}</small>
      ${key === 'sched_close' ? `<small class="efb text-danger d-none d-block mt-1" id="schedOrderErrEl" role="alert">${esc(T('schedOrderErr', 'The form must close after it opens.'))}</small>` : ''}
    </div>`;
  }
  function rolesEl() {
    const roles = (root.efb_var && root.efb_var.wp_roles) || {};
    const cur = val('allowed_roles').split(',').filter(Boolean);
    const boxes = Object.keys(roles).map((slug) => `<div class="efb form-check">
        <input class="efb form-check-input efb-role-cb" type="checkbox" id="efbRole_${esc(slug)}" value="${esc(slug)}" ${cur.includes(slug) ? 'checked' : ''} onchange="efb_limits_change_efb(document.getElementById('allowedRolesEl'))">
        <label class="efb form-check-label" for="efbRole_${esc(slug)}">${esc(roles[slug])}</label>
      </div>`).join('');
    const priv = ['1', 'true'].includes(String(row0().stateForm));
    return `<fieldset class="efb mt-2 mb-2 ${priv ? '' : 'd-none'}" id="allowedRolesEl" data-key="allowed_roles">
      <legend class="efb fs-6 mb-1"><i class="efb bi-people fs-7 ${icon()}" aria-hidden="true"></i>${esc(T('allowedRoles', 'Only these roles (empty = every logged-in user)'))}</legend>
      ${boxes}
    </fieldset>`;
  }
  function emailFieldEl() {
    const cur = val('lim_email_field');
    const opts = [`<option value="" ${cur === '' ? 'selected' : ''}>—</option>`].concat((typeof valj_efb !== 'undefined' ? valj_efb : []).filter((x, i) => i > 0 && x && x.type === 'email').map((x) => `<option value="${esc(x.id_)}" ${cur === x.id_ ? 'selected' : ''}>${esc(String(x.name || x.id_))}</option>`)).join('');
    return `<div class="efb mt-2 mb-2">
      <label for="limEmailFieldEl" class="efb mb-1">${esc(T('limEmailField', 'Email field for this limit'))}</label>
      <select class="efb form-select efb border-d rounded-4 efb-f11" id="limEmailFieldEl" data-key="lim_email_field" onchange="efb_limits_change_efb(this)"${lock('person')}>${opts}</select>
    </div>`;
  }
  function periodEl() {
    const cur = val('lim_period') || 'ever';
    const opts = [['ever', 'limEver', 'Ever'], ['day', 'limDay', 'Per day'], ['week', 'limWeek', 'Per week'], ['month', 'limMonth', 'Per month'], ['year', 'limYear', 'Per year']]
      .map(([v, k, fb]) => `<option value="${v}" ${cur === v ? 'selected' : ''}>${esc(T(k, fb))}</option>`).join('');
    return `<div class="efb mt-2 mb-2">
      <label for="limPeriodEl" class="efb mb-1">${esc(T('limPeriod', 'Period of the limits'))}</label>
      <select class="efb form-select efb border-d rounded-4 efb-f11" id="limPeriodEl" data-key="lim_period" onchange="efb_limits_change_efb(this)"${lock('person')}>${opts}</select>
    </div>`;
  }
  function cssEl() {
    const can = !!(root.efb_var && root.efb_var.can_unfiltered_html);
    const css = efb_css_decode_efb(val('custom_css'));
    return `<div class="efb mt-3 mb-2">
      <label for="customCssEl" class="efb mb-1"><i class="efb bi-code-slash fs-7 ${icon()}" aria-hidden="true"></i>${esc(T('customCss', 'Custom CSS for this form'))}</label>
      <textarea class="efb form-control efb border-d rounded-4 font-monospace efb-f11" id="customCssEl" data-key="custom_css" dir="ltr" rows="6" spellcheck="false" maxlength="20480" aria-describedby="customCssElHelp" oninput="efb_limits_css_input_efb(this)" ${can ? '' : 'disabled aria-disabled="true"'}>${esc(css)}</textarea>
      <small class="efb form-text text-muted d-block mt-1" id="customCssElHelp">${esc(can ? T('customCssHelp', 'Applies to this form only. :root, html and body mean the form itself.') : T('customCssNoPerm', 'Only users who may post unfiltered HTML can change this CSS.'))}</small>
      <small class="efb text-warning d-none d-block mt-1" id="customCssDroppedEl" role="status"></small>
    </div>`;
  }

  /** The whole collapse (formSet), placed before the Privacy section by val-efb.js. */
  function accessLimitsEls() {
    const ipNote = esc(T('limSharedIpWarn', 'Many people can share one address (office, school, mobile network). A cookie limit is easy to clear.'));
    const isForm = !['login'].includes(row0().type);
    if (!isForm) return '<!--efb access limits: not for login forms-->';
    return `
          <!-- access and limits section (F11) -->
          <div class="efb d-grid gap-2">
            <button class="efb btn btn-outline-light mt-3" id="access_collapse" type="button" data-bs-toggle="collapse" data-bs-target="#collapseAccess" aria-expanded="false" aria-controls="collapseAccess">
              <i class="efb bi-door-closed me-1" aria-hidden="true"></i>${esc(T('accessLimits', 'Access and limits'))}
            </button>
          </div>
          <div class="efb mb-3 mt-3 collapse" id="collapseAccess">
            <div class="efb mb-3 px-3 row">
              ${dateEl('schedOpenEl', 'sched_open', T('schedOpen', 'Opens on'))}
              ${dateEl('schedCloseEl', 'sched_close', T('schedClose', 'Closes on'))}
              ${numEl('schedGraceEl', 'sched_grace', T('schedGrace', 'Minutes still accepted after closing (for pages opened before)'), 0, 60, '')}
              ${textEl('schedPendingMsgEl', 'sched_pending_msg', T('schedPendingMsg', 'Message before the form opens'))}
              ${textEl('schedClosedMsgEl', 'sched_closed_msg', T('schedClosedMsg', 'Message after the form closed'))}
              ${numEl('limTotalEl', 'lim_total', T('limTotal', 'Maximum number of answers'), 1, 10000000, esc(T('limTotalHelp', 'Empty = no limit. Deleting an answer frees its place.')))}
              ${toggleEl('limShowLeftEl', 'lim_show_left', T('limShowLeft', 'Show the number of places left'))}
              ${textEl('limFullMsgEl', 'lim_full_msg', T('limFullMsg', 'Message when the form is full'))}
              ${numEl('limUserEl', 'lim_user', T('limPerUser', 'Answers per logged-in user'), 1, 100000, '', 'person')}
              ${numEl('limIpEl', 'lim_ip', T('limPerIp', 'Answers per IP address'), 1, 100000, ipNote, 'person')}
              ${numEl('limCookieEl', 'lim_cookie', T('limPerCookie', 'Answers per browser (cookie)'), 1, 100000, '', 'person')}
              ${numEl('limEmailEl', 'lim_email', T('limPerEmail', 'Answers per email address'), 1, 100000, '', 'person')}
              ${emailFieldEl()}
              ${periodEl()}
              ${textEl('limUserMsgEl', 'lim_user_msg', T('limUserMsg', 'Message when a person reached the limit'))}
              <div class="efb my-2"><button type="button" class="efb btn btn-sm btn-outline-pink" id="limResetBtn" onclick="efb_limits_reset_efb(this)"${lock('person')}><i class="efb bi-arrow-counterclockwise ${icon()}" aria-hidden="true"></i>${esc(T('limReset', 'Reset the counters'))}</button> <span class="efb fs-7" id="limResetMsg" role="status"></span></div>
              ${rolesEl()}
              ${toggleEl('draftOnEl', 'draft_on', T('draftOn', 'Save and continue later'), 'drafts')}
              ${numEl('draftDaysEl', 'draft_days', T('draftDays', 'Saved answers are kept for (days)'), 1, 90, '', 'drafts')}
              ${toggleEl('partialOnEl', 'partial_on', T('partialOn', 'Keep partial answers (abandoned forms)'), 'drafts')}
              <div class="efb mt-2 mb-2">
                <label for="partialConsentEl" class="efb mb-1">${esc(T('partialConsentMode', 'How visitors agree'))}</label>
                <select class="efb form-select efb border-d rounded-4 efb-f11" id="partialConsentEl" data-key="partial_consent" onchange="efb_limits_change_efb(this)"${lock('drafts')}>
                  <option value="checkbox" ${val('partial_consent') !== 'notice' ? 'selected' : ''}>${esc(T('partialConsentBox', 'A checkbox the visitor ticks'))}</option>
                  <option value="notice" ${val('partial_consent') === 'notice' ? 'selected' : ''}>${esc(T('partialConsentNotice', 'A notice above the form'))}</option>
                </select>
              </div>
              ${toggleEl('partialNeedContactEl', 'partial_need_contact', T('partialNeedContact', 'Only when an email or mobile number is filled in'), 'drafts', val('partial_need_contact') !== '0')}
              ${numEl('partialDaysEl', 'partial_days', T('partialDays', 'Partial answers are kept for (days)'), 1, 365, '', 'drafts')}
              ${cssEl()}
            </div>
          </div>
          <!-- access and limits section end -->`;
  }

  /** One control changed: write valj_efb[0] by the server's rules. */
  function efb_limits_change_efb(el) {
    if (!el || typeof valj_efb === 'undefined' || !valj_efb[0]) return;
    const key = el.getAttribute('data-key');
    if (!key) return;
    if (el.getAttribute('aria-disabled') === 'true') return;
    let v;
    if (el.classList.contains('btn-toggle')) {
      const isOn = !el.classList.contains('active');
      el.classList.toggle('active', isOn);
      el.setAttribute('aria-pressed', isOn ? 'true' : 'false');
      v = efb_limits_value_efb(key, isOn ? '1' : (key === 'partial_need_contact' ? '0' : ''));
    } else if (key === 'allowed_roles') {
      v = efb_limits_value_efb(key, Array.from(el.querySelectorAll('.efb-role-cb:checked')).map((x) => x.value).join(','));
    } else {
      v = efb_limits_value_efb(key, el.value);
      if (el.tagName === 'INPUT' && el.type !== 'datetime-local' && el.value !== v && RANGES[key]) el.value = v;
    }
    valj_efb[0][key] = v;
    if (key === 'sched_open' || key === 'sched_close') {
      const cal = document.getElementById(el.id + 'Cal');
      if (cal) cal.textContent = calendarHint(v);
      const err = document.getElementById('schedOrderErrEl');
      const o = String(valj_efb[0].sched_open || ''), c = String(valj_efb[0].sched_close || '');
      const bad = o !== '' && c !== '' && c <= o;
      if (err) err.classList.toggle('d-none', !bad);
      const ce = document.getElementById('schedCloseEl');
      if (ce) ce.setAttribute('aria-invalid', bad ? 'true' : 'false');
    }
    if (typeof efbApiNoteUserEditEfb === 'function') { try { efbApiNoteUserEditEfb(); } catch (e) {} }
  }
  let cssTimer = null;
  function efb_limits_css_input_efb(el) {
    if (!el || el.disabled) return;
    clearTimeout(cssTimer);
    cssTimer = setTimeout(() => {
      const css = String(el.value || '');
      valj_efb[0].custom_css = css === '' ? '' : efb_css_encode_efb(css);
      efb_scope_css_preview_efb(css);
      const n = efb_css_dropped_efb(css);
      const note = document.getElementById('customCssDroppedEl');
      if (note) { note.textContent = n > 0 ? T('customCssDropped', '%s rules were removed because they could load content from outside the page.').replace('%s', String(n)) : ''; note.classList.toggle('d-none', n === 0); }
    }, 300);
  }
  function efb_limits_gem_efb(btn) {
    const cap = btn && btn.getAttribute('data-cap');
    if (typeof efb_capability_prompt_efb === 'function') efb_capability_prompt_efb(cap, cap === 'setting:form_person_limits' ? 3 : 1, btn);
  }
  function efb_limits_reset_efb(btn) {
    const fid = typeof form_ID_emsFormBuilder !== 'undefined' ? Number(form_ID_emsFormBuilder) : 0;
    const msg = document.getElementById('limResetMsg');
    if (!fid || typeof jQuery === 'undefined') return;
    btn.disabled = true;
    jQuery.post(root.efb_var.ajax_url || (typeof ajaxurl !== 'undefined' ? ajaxurl : ''), { action: 'efb_limits_reset_Emsfb', nonce: root.efb_var.nonce, form_id: fid }, (res) => {
      btn.disabled = false;
      if (msg) msg.textContent = res && res.data && res.data.m ? res.data.m : '';
    }).fail(() => { btn.disabled = false; });
  }
  /* The roles list follows the private-form switch (its handler runs on a timer). */
  if (typeof document !== 'undefined') {
    document.addEventListener('click', (e) => {
      const t = e.target && e.target.closest ? e.target.closest('#showformLoggedEl') : null;
      if (!t) return;
      setTimeout(() => {
        const box = document.getElementById('allowedRolesEl');
        if (box && typeof valj_efb !== 'undefined') box.classList.toggle('d-none', !['1', 'true'].includes(String(valj_efb[0].stateForm)));
      }, 1200);
    });
  }

  const api = {
    efb_builder_ascii_digits_efb, efb_limits_value_efb, efb_limits_validate_efb, efb_css_sanitize_efb, efb_css_scope_efb,
    efb_css_decode_efb, efb_css_encode_efb, efb_css_dropped_efb, efb_scope_css_preview_efb, accessLimitsEls,
    efb_limits_change_efb, efb_limits_css_input_efb, efb_limits_gem_efb, efb_limits_reset_efb,
  };
  Object.keys(api).forEach((k) => { root[k] = api[k]; });
  root.EFB_F11_KEYS = KEYS;
  if (typeof module !== 'undefined' && module.exports) module.exports = api;
})(typeof window !== 'undefined' ? window : globalThis);
