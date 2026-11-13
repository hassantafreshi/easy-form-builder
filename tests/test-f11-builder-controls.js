/**
 * F11 builder controls (includes/admin/assets/js/form-limits-efb.js), without a browser:
 *  - D: the roles list is shown only for a private form (stateForm) and lists efb_var.wp_roles;
 *    ticking roles writes allowed_roles as the server keeps it (existing roles only);
 *  - B/C: numbers with Persian digits are written as Latin digits; out-of-range is cleared;
 *    closing before opening shows the error (role=alert, aria-invalid) and setFormSettings refuses it;
 *  - per-person and draft controls are disabled with a plan gem below their plan;
 *  - custom CSS: disabled without unfiltered_html; setFormSettings refuses custom_css then.
 * Run: node tests/test-f11-builder-controls.js
 */
'use strict';
const path = require('path');
let passed = 0, failed = 0;
function t(name, actual, expected) {
  const a = JSON.stringify(actual), e = JSON.stringify(expected);
  if (a === e) { passed++; console.log('[PASS] ' + name); } else { failed++; console.log('[FAIL] ' + name + '\n   expected ' + e + '\n   actual   ' + a); }
}
global.efb_var = { text: {}, wp_roles: { administrator: 'Administrator', editor: 'Editor', subscriber: 'Subscriber' }, f11: { plan_person: true, plan_drafts: false }, can_unfiltered_html: false, site_tz: 'Asia/Tehran', site_now: '2026-11-13T12:00' };
global.valj_efb = [{ type: 'form', stateForm: '0' }];
const L = require(path.join(__dirname, '..', 'includes/admin/assets/js/form-limits-efb.js'));

let html = L.accessLimitsEls();
t('roles list hidden for a public form', /<fieldset class="efb mt-2 mb-2 d-none" id="allowedRolesEl"/.test(html), true);
valj_efb[0].stateForm = '1';
valj_efb[0].allowed_roles = 'editor';
html = L.accessLimitsEls();
t('roles list shown for a private form, one checkbox per role, editor ticked', [/<fieldset class="efb mt-2 mb-2 " id="allowedRolesEl"/.test(html), (html.match(/efb-role-cb/g) || []).length, /id="efbRole_editor" value="editor" checked/.test(html)], [true, 3, true]);
t('section for login forms: none', (() => { valj_efb[0].type = 'login'; const h = L.accessLimitsEls(); valj_efb[0].type = 'form'; return h.indexOf('collapseAccess'); })(), -1);

// A fake element for the change handler.
function el(attrs, extra) {
  const cls = new Set(extra && extra.cls || []);
  return Object.assign({ getAttribute: (k) => (attrs[k] === undefined ? null : attrs[k]), setAttribute: (k, v) => { attrs[k] = v; }, classList: { contains: (c) => cls.has(c), toggle: (c, on) => { if (on) cls.add(c); else cls.delete(c); } }, tagName: 'INPUT', type: 'text', value: '' }, extra || {});
}
const docEls = {};
global.document = { getElementById: (id) => docEls[id] || null, addEventListener() {} };
const roleBox = el({ 'data-key': 'allowed_roles' }, { querySelectorAll: () => [{ value: 'subscriber' }, { value: 'ghost' }, { value: 'editor' }] });
L.efb_limits_change_efb(roleBox);
t('ticked roles → allowed_roles (unknown role dropped)', valj_efb[0].allowed_roles, 'subscriber,editor');
const total = el({ 'data-key': 'lim_total' }, { value: '۲۵۰' });
L.efb_limits_change_efb(total);
t('Persian digits → "250", the input shows Latin digits', [valj_efb[0].lim_total, total.value], ['250', '250']);
const bad = el({ 'data-key': 'lim_total' }, { value: '0' });
L.efb_limits_change_efb(bad);
t('0 is cleared (no limit)', valj_efb[0].lim_total, '');
const errEl = el({}, { cls: ['d-none'] });
const closeEl = el({ 'data-key': 'sched_close' }, { type: 'datetime-local', id: 'schedCloseEl', value: '2026-11-01T09:00' });
docEls.schedOrderErrEl = errEl; docEls.schedCloseEl = closeEl;
valj_efb[0].sched_open = '2026-12-01T09:00';
L.efb_limits_change_efb(closeEl);
t('closing before opening: error shown, aria-invalid', [errEl.classList.contains('d-none'), closeEl.getAttribute('aria-invalid')], [false, 'true']);
t('setFormSettings rules: sched_order, invalid number, no unfiltered_html for CSS', [
  L.efb_limits_validate_efb({ sched_close: '2026-11-01T09:00' }, valj_efb[0])[0].code,
  L.efb_limits_validate_efb({ lim_ip: 'many' }, {})[0].code,
  L.efb_limits_validate_efb({ custom_css: 'b64:LmF7fQ==' }, {})[0].code,
  L.efb_limits_validate_efb({ lim_ip: '3', lim_period: 'week' }, {}).length,
], ['sched_order', 'invalid_setting', 'no_unfiltered_html', 0]);
t('per-person controls enabled on Free Plus; draft controls disabled with a gem below Pro', [/id="limIpEl"[^>]*disabled/.test(html), /id="draftOnEl"[^>]*disabled/.test(html), html.indexOf('data-cap="setting:form_drafts"') !== -1], [false, true, true]);
t('custom CSS textarea disabled without unfiltered_html, LTR', /<textarea[^>]*id="customCssEl"[^>]*dir="ltr"[^>]*disabled/.test(html), true);
const toggle = el({ 'data-key': 'lim_show_left', 'aria-pressed': 'false' }, { cls: ['btn-toggle'] });
L.efb_limits_change_efb(toggle);
t('toggle writes "1" and aria-pressed', [valj_efb[0].lim_show_left, toggle.getAttribute('aria-pressed')], ['1', 'true']);
L.efb_limits_change_efb(toggle);
t('toggle off writes ""', valj_efb[0].lim_show_left, '');
console.log(`\nRESULTS: ${passed} passed, ${failed} failed`);
process.exit(failed ? 1 : 0);
