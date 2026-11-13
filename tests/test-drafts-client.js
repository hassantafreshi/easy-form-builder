/**
 * F11-E/F in public/assets/js/core-efb.js, in a vm with a small fake DOM:
 *  - efb_draft_rows_efb(): only this form's rows, never password / file / signature / recordings /
 *    payment / captcha / computed price / a gateway field, nor a field hidden by logic; only the
 *    row keys a draft needs;
 *  - efb_apply_saved_rows_efb(): fills text, textarea (@n# → line break), radio, select through the
 *    normal change handler (handle_change_event_efb_v4); reports the types it cannot show;
 *  - the offline restore uses the same function (one path);
 *  - post_api_forms_efb() sends draft_token from sessionStorage and forgets it after success;
 *  - partial answers: nothing is sent without consent (checkbox unticked) or without a contact
 *    (e-mail / mobile) when partial_need_contact is on; the same answers are not sent twice;
 *    the notice mode needs no checkbox; the consent box is not part of the answers (no
 *    emsFormBuilder_v class, no data-vid).
 * Run: node tests/test-drafts-client.js
 */
'use strict';
const fs = require('fs');
const vm = require('vm');
const path = require('path');
let passed = 0, failed = 0;
function t(name, actual, expected) {
  const a = JSON.stringify(actual), e = JSON.stringify(expected);
  if (a === e) { passed++; console.log('[PASS] ' + name); } else { failed++; console.log('[FAIL] ' + name + '\n   expected ' + e + '\n   actual   ' + a); }
}
const core = fs.readFileSync(path.join(__dirname, '..', 'public/assets/js/core-efb.js'), 'utf8');
const from = (a, b) => { const i = core.indexOf(a); const j = b ? core.indexOf(b, i) : core.length; if (i < 0 || j <= i) throw new Error('slice ' + a); return core.slice(i, j); };

function el(props) {
  const n = Object.assign({ tagName: 'INPUT', type: 'text', value: '', checked: false, disabled: false, readOnly: false, dataset: {}, classList: { contains: () => false }, closest: () => null, options: [] }, props);
  return n;
}
const dom = {};
const changed = [];
const sb = {
  document: { getElementById: (id) => dom[id] || null, querySelectorAll: () => [], body: { appendChild() {} } },
  window: { EFB_GATE_NO_BOOT: true, addEventListener() {} },
  sessionStorage: { _s: {}, getItem(k) { return this._s[k] || null; }, setItem(k, v) { this._s[k] = String(v); }, removeItem(k) { delete this._s[k]; } },
  efb_var: { pay: { gateways: ['efbmock'] }, rest_url: '/wp-json/' },
  ajax_object_efm: { text: {}, f11: { drafts: true, partials: true } },
  handle_change_event_efb_v4: async (e, fid) => { changed.push([e.id, e.value || e.checked, fid]); },
  efb_mask_display_efb: (e, v) => v,
  text_nr_efb: (v) => String(v).replace(/@n#/g, '\n'),
  efb_ascii_digits_efb: (s) => String(s),
  fetchCalls: [],
  setTimeout, clearTimeout, Date, JSON, Math, Number, String, Object, Array, Promise,
};
sb.fetch = async (url, init) => { sb.fetchCalls.push([url, JSON.parse(init.body)]); return { ok: true, status: 200, json: async () => ({ success: true }) }; };
sb.window.EFBRuntime = { prepare: async () => ({ nonce: 'n', sid: 's', runtime_token: 'rt' }) };
vm.createContext(sb);
vm.runInContext('var sendBack_emsFormBuilder_pub = [];', sb);
vm.runInContext(from('const EFB_RESTORABLE_FIELD_TYPES', 'function efb_get_error_panel_efb'), sb);
vm.runInContext(from('/* ---------------------------------------------------------------------------------------------\n * F11: the form gate in the page'), sb);

/* efb_draft_rows_efb */
const rows = [
  { id_: 'a', id_ob: 'a_', type: 'text', value: 'Ann', form_id: 5, extra: 'x' },
  { id_: 'b', id_ob: 'b_', type: 'password', value: 'secret', form_id: 5 },
  { id_: 'c', id_ob: 'c_', type: 'file', value: '@file@', url: 'u', form_id: 5 },
  { id_: 'd', id_ob: 'd_', type: 'esign', value: 'data:', form_id: 5 },
  { id_: 'e', id_ob: 'e_', type: 'video_recorder', value: 'v', form_id: 5 },
  { id_: 'f', id_ob: 'f_', type: 'payment', value: 'pi_1', form_id: 5 },
  { id_: 'g', id_ob: 'g_', type: 'efbmock', value: '10', form_id: 5 },
  { id_: 'h', id_ob: 'h_', type: 'captcha_v2', value: 'tok', form_id: 5 },
  { id_: 'i', id_ob: 'i_', type: 'prcfld', value: '25', form_id: 5 },
  { id_: 'j', id_ob: 'j_', type: 'email', value: 'a@b.test', form_id: 5 },
  { id_: 'k', id_ob: 'k_', type: 'text', value: 'other form', form_id: 6 },
  { id_: 'l', id_ob: 'l_', type: 'text', value: 'hidden by logic', form_id: 5 },
];
dom.l_ = el({ id: 'l_', closest: (sel) => (sel.indexOf('data-efb-logic-hidden') !== -1 ? {} : null) });
vm.runInContext('sendBack_emsFormBuilder_pub = ' + JSON.stringify(rows) + ';', sb);
const kept = sb.efb_draft_rows_efb(5);
t('draft rows: only text and e-mail of this form, not hidden by logic', kept.map((r) => r.id_), ['a', 'j']);
t('draft rows: only the keys a draft needs', Object.keys(kept[0]).sort(), ['form_id', 'id_', 'id_ob', 'type', 'value']);

/* efb_apply_saved_rows_efb */
dom.t1 = el({ id: 't1' });
dom.t2 = el({ id: 't2', tagName: 'TEXTAREA', type: 'textarea' });
dom.r1 = el({ id: 'r1', type: 'radio' });
dom.s1 = el({ id: 's1', tagName: 'SELECT', type: 'select-one', options: [{ value: 'x' }, { value: 'y' }] });
dom.ph = el({ id: 'ph', classList: { contains: (c) => c === 'intlPhone' } });
(async () => {
  const skipped = await sb.efb_apply_saved_rows_efb(5, [
    { id_: 'T1', id_ob: 't1', type: 'text', value: 'Hello', form_id: 5 },
    { id_: 'T2', id_ob: 't2', type: 'textarea', value: 'a@n#b', form_id: 5 },
    { id_: 'R1', id_ob: 'r1', type: 'radio', value: 'r1', form_id: 5 },
    { id_: 'S1', id_ob: 's1', type: 'select', value: 'y', form_id: 5 },
    { id_: 'S2', id_ob: 's1', type: 'select', value: 'nope', form_id: 5 },
    { id_: 'YN', id_ob: 'yn', type: 'yesNo', value: 'Yes', form_id: 5 },
    { id_: 'PH', id_ob: 'ph', type: 'tel', value: '+98912', form_id: 5 },
    { id_: 'X', id_ob: 't1', type: 'text', value: 'other form', form_id: 6 },
  ]);
  t('restore through the change handler: text, textarea (line break), radio, select', changed.map((c) => c[0]), ['t1', 't2', 'r1', 's1']);
  t('restored values', [dom.t1.value, dom.t2.value, dom.r1.checked, dom.s1.value], ['Hello', 'a\nb', true, 'y']);
  t('types that draw their own UI are reported (to fill again)', skipped, ['YN', 'PH']);
  t('the offline restore uses the same function', /await efb_apply_saved_rows_efb\(fid, rows\);/.test(core), true);

  /* post_api_forms_efb and draft_token */
  t('submit sends draft_token from this tab and forgets it after success', /data\.draft_token = dt/.test(core) && /sessionStorage\.removeItem\('efb_draft_' \+ Number\(form_id\)\)/.test(core), true);

  /* partial answers */
  sb.document.getElementById = (id) => dom[id] || null;
  const consent = { checked: false };
  dom['efb-partial-consent-5'] = consent;
  vm.runInContext('EFB_PARTIAL[5] = { mode: "checkbox", needContact: true, last: "", at: 0, timer: null, stopped: false };', sb);
  vm.runInContext('sendBack_emsFormBuilder_pub = ' + JSON.stringify([{ id_: 'a', id_ob: 'a_', type: 'text', value: 'Ann', form_id: 5 }]) + ';', sb);
  delete dom.l_;
  t('partial: without consent nothing is sent', [await sb.efb_partial_send_efb(5, false), sb.fetchCalls.length], [false, 0]);
  consent.checked = true;
  t('partial: consent but no e-mail / mobile (need contact) → nothing sent', [await sb.efb_partial_send_efb(5, false), sb.fetchCalls.length], [false, 0]);
  vm.runInContext('sendBack_emsFormBuilder_pub.push({ id_: "j", id_ob: "j_", type: "email", value: "a@b.test", form_id: 5 });', sb);
  t('partial: consent and an e-mail → forms/partial/save with consent 1', [await sb.efb_partial_send_efb(5, false), sb.fetchCalls.length, sb.fetchCalls[0][0], sb.fetchCalls[0][1].consent, sb.fetchCalls[0][1].runtime_token], [true, 1, '/wp-json/Emsfb/v1/forms/partial/save', 1, 'rt']);
  t('partial: the same answers are not sent twice', [await sb.efb_partial_send_efb(5, false), sb.fetchCalls.length], [false, 1]);
  vm.runInContext('EFB_PARTIAL[6] = { mode: "notice", needContact: false, last: "", at: 0, timer: null, stopped: false }; sendBack_emsFormBuilder_pub.push({ id_: "z", id_ob: "z_", type: "text", value: "n", form_id: 6 });', sb);
  t('partial (notice mode): no checkbox needed', [await sb.efb_partial_send_efb(6, false), sb.fetchCalls.length], [true, 2]);
  sb.efb_partial_stop_efb(6);
  vm.runInContext('sendBack_emsFormBuilder_pub.push({ id_: "z2", id_ob: "z2_", type: "text", value: "m", form_id: 6 });', sb);
  t('partial: after a successful submit nothing more is sent', [await sb.efb_partial_send_efb(6, false), sb.fetchCalls.length], [false, 2]);
  const consentHtml = from("? '<p class=\"efb efb-partial-notice", "form.insertAdjacentHTML('afterbegin', html);");
  t('the consent box is not an answer (no emsFormBuilder_v, no data-vid)', [consentHtml.indexOf('emsFormBuilder_v'), consentHtml.indexOf('data-vid')], [-1, -1]);
  console.log(`\nRESULTS: ${passed} passed, ${failed} failed`);
  process.exit(failed ? 1 : 0);
})().catch((e) => { console.error(e); process.exit(1); });
