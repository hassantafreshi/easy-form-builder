/**
 * F13: the admin-side JavaScript of privacy and retention, lifted from the shipped files and run
 * without a browser (pattern of tests/test-response-viewer-escaping.js). The real-WordPress run of
 * the same screens is scenario 8 of tests/e2e-wp/run.js.
 *
 *  - Settings > Privacy: Persian / Arabic digits in the day field, the 0-3650 clamp, the keys the
 *    Save sends (and nothing for a user without manage_options), when Save asks first;
 *  - an anonymized answer (@prv@) reads "Removed for privacy" in the entry list preview and in the
 *    CSV; the viewer has a branch for it and a badge;
 *  - the builder's prvIpEl / prvDaysEl handlers write prv_ip / prv_days on valj_efb[0];
 *  - the dead showIp setting is gone; ?tab=privacy is a known settings tab.
 *
 * Run: node tests/test-privacy-admin-js.js
 */
'use strict';
const fs = require('fs');
const path = require('path');
const ROOT = path.join(__dirname, '..');
const LIST = fs.readFileSync(path.join(ROOT, 'includes/admin/assets/js/list_form-efb.js'), 'utf8');
const VIEW = fs.readFileSync(path.join(ROOT, 'includes/admin/assets/js/response-viewer-efb.js'), 'utf8');
const ADMIN = fs.readFileSync(path.join(ROOT, 'includes/admin/assets/js/admin-efb.js'), 'utf8');
const VAL = fs.readFileSync(path.join(ROOT, 'includes/admin/assets/js/val-efb.js'), 'utf8');

let pass = 0, fail = 0;
function t(label, ok, detail) { if (ok) { pass++; console.log('[PASS] ' + label); } else { fail++; console.log('[FAIL] ' + label + (detail !== undefined ? ' — ' + JSON.stringify(detail) : '')); } }
function grab(src, name) {
  const m = src.match(new RegExp('(?:async )?function ' + name + '\\([\\s\\S]*?\\n}\\n'));
  if (!m) throw new Error('missing ' + name);
  return m[0];
}

// A tiny DOM for the settings controls.
function makeDoc(nodes) {
  return {
    getElementById: (id) => nodes[id] || null,
    querySelector: (sel) => {
      const m = sel.match(/^input\[name="(\w+)"\]:checked$/);
      if (m) return Object.values(nodes).find((n) => n.name === m[1] && n.checked) || null;
      return null;
    },
  };
}
const fns = [
  'efb_prv_t_efb', 'efb_prv_can_manage_efb', 'efb_prv_digits_efb', 'efb_prv_number_efb', 'efb_privacy_days_input_efb',
  'efb_privacy_patch_efb', 'efb_privacy_save_needs_check_efb', 'efb_privacy_last_run_text_efb',
  'escapeContentPreviewText_efb', 'getContentPreview_efb', 'convertToCSV_emsFormBuilder',
].map((n) => grab(LIST, n)).join('\n');
const sandbox = (env) => new Function('env', `
  const document = env.document, window = env.window; let efb_var = env.efb_var; let valueJson_ws_setting = env.stored;
  const setTimeout = (f) => { env.timers.push(f); return 1; }, clearTimeout = () => {};
  const efbParseJson = (s) => JSON.parse(s); const replaceContentMessageEfb = (s) => s;
  const efb_privacy_preview_efb = (d) => { env.previews.push(d); };
  ${fns}
  return { efb_privacy_days_input_efb, efb_privacy_patch_efb, efb_privacy_save_needs_check_efb, efb_privacy_last_run_text_efb, getContentPreview_efb, convertToCSV_emsFormBuilder };
`)(env);

const text = { prvRedacted: 'Removed for privacy', prvLastRun: 'Last run: %1$s, %2$s entries removed', prvNeverRun: 'Not yet.' };
const radios = (name, values, checked) => Object.fromEntries(values.map((v) => [name + '_' + v, { name, value: v, checked: v === checked }]));
function env(can, nodes, stored) {
  return { document: makeDoc(nodes), window: {}, efb_var: { text, wp_lan: 'en_US', privacy: { can_manage: can } }, stored: stored || {}, timers: [], previews: [] };
}

// Day field
let e = env(true, { prvDays_efb: { value: '' } });
let F = sandbox(e);
const day = { value: '۹۰' };
F.efb_privacy_days_input_efb(day);
t('Persian digits in the day field become 90', day.value === '90', day.value);
day.value = '٣٦٥٠٠';
F.efb_privacy_days_input_efb(day);
t('Arabic-Indic digits, capped at 3650', day.value === '3650', day.value);
day.value = '12abc';
F.efb_privacy_days_input_efb(day);
t('letters dropped', day.value === '12', day.value);
e.timers.forEach((f) => f());
t('the preview follows the field (debounced)', e.previews.length >= 1 && e.previews[e.previews.length - 1] === '12', e.previews);

// Save patch
const nodes = Object.assign({ 'efb-privacy-settings': {}, prvDays_efb: { value: '۳۰' }, prvFiles_efb: { checked: false } },
  radios('prvIpMode_efb', ['store', 'anon', 'none'], 'anon'), radios('prvAction_efb', ['delete', 'anon'], 'anon'));
F = sandbox(env(true, nodes, { prv_days: '0' }));
t('Save sends the four privacy keys from the screen', JSON.stringify(F.efb_privacy_patch_efb()) === JSON.stringify({ prv_ip_mode: 'anon', prv_days: '30', prv_action: 'anon', prv_files: '' }), F.efb_privacy_patch_efb());
t('Save asks first when automatic deletion is turned on', F.efb_privacy_save_needs_check_efb() === true);
F = sandbox(env(true, Object.assign({}, nodes, { prvDays_efb: { value: '30' } }), { prv_days: '30' }));
t('...not when the days did not change', F.efb_privacy_save_needs_check_efb() === false);
F = sandbox(env(true, Object.assign({}, nodes, { prvDays_efb: { value: '10' } }), { prv_days: '30' }));
t('...and again when they get shorter', F.efb_privacy_save_needs_check_efb() === true);
F = sandbox(env(true, Object.assign({}, nodes, { prvDays_efb: { value: '0' } }), { prv_days: '30' }));
t('...never when turning it off', F.efb_privacy_save_needs_check_efb() === false);
F = sandbox(env(false, nodes, {}));
t('a user without manage_options sends no privacy key (stored values kept)', JSON.stringify(F.efb_privacy_patch_efb()) === '{}' && F.efb_privacy_save_needs_check_efb() === false);
F = sandbox(env(true, {}, {}));
t('another settings screen (no Privacy tab) sends nothing', JSON.stringify(F.efb_privacy_patch_efb()) === '{}');

// Last run text
F = sandbox(env(true, {}, {}));
t('last run: never', F.efb_privacy_last_run_text_efb(null) === 'Not yet.');
t('last run: deleted + anonymized', /^Last run: .+, 5 entries removed$/.test(F.efb_privacy_last_run_text_efb({ time: 1700000000, deleted: 2, anonymized: 3 })), F.efb_privacy_last_run_text_efb({ time: 1700000000, deleted: 2, anonymized: 3 }));

// @prv@ in the list preview and the CSV
const content = JSON.stringify([{ id_: 'e', name: 'Email', type: 'email', value: '@prv@' }, { id_: 'r', name: 'Pick', type: 'radio', value: 'Yes' }]);
const pv = F.getContentPreview_efb(content);
t('entry list preview: "Removed for privacy" instead of @prv@', pv.full === 'Email: Removed for privacy | Pick: Yes', pv);
const csv = F.convertToCSV_emsFormBuilder([['Email', 'Pick'], ['@prv@', 'Yes']]);
t('CSV: "Removed for privacy" instead of @prv@', csv.split('\r\n')[1] === 'Removed for privacy,Yes', csv);
t('CSV: the export keeps @prv@ for file and signature rows (no URL lookup)', /if \(content\[c\]\.value === '@prv@'\) \{/.test(LIST));

// Viewer
t('viewer: a @prv@ answer gets its own row with prvRedacted, muted italic', /c\.value === '@prv@'[\s\S]{0,400}efb-prv-redacted[\s\S]{0,300}text-muted fst-italic[\s\S]{0,120}prvRedacted/.test(VIEW));
t('viewer: the "Anonymized" badge', /efb-prv-badge[\s\S]{0,200}prvAnonBadge/.test(VIEW));

// Builder handlers
const handler = (id) => { const m = ADMIN.match(new RegExp('case "' + id + '": \\{([\\s\\S]*?)\\n      \\}')); return m ? m[1] : null; };
for (const [id, input, want] of [['prvIpEl', 'none', 'none'], ['prvIpEl', 'bogus', ''], ['prvIpEl', '', ''], ['prvDaysEl', '۴۵', '45'], ['prvDaysEl', '99999', '3650'], ['prvDaysEl', '', '']]) {
  const body = handler(id);
  const valj_efb = [{}];
  const el = id === 'prvIpEl' ? { selectedIndex: 0, options: [{ value: input }] } : { value: input };
  try { new Function('el', 'valj_efb', 'switch (1) { case 1: ' + body + ' }')(el, valj_efb); } catch (err) { t(id + ' handler runs', false, String(err)); continue; }
  const key = id === 'prvIpEl' ? 'prv_ip' : 'prv_days';
  t(`builder ${id} "${input}" → ${key} "${want}"`, valj_efb[0][key] === want, valj_efb[0][key]);
}
t('builder: the Privacy section sits before Advanced in formSet', /\$\{privacyEls\(\)\}\s*<div class="efb  d-grid gap-2">\s*<button class="efb btn btn-outline-light mt-3" id="advanced_collapse"/.test(VAL));

// Dead setting and the tab
t('the dead showIp setting is gone', !/showIp/.test(LIST));
t('?tab=privacy is a known settings tab', /EFB_SETTING_TABS_EFB = \[[^\]]*'privacy'/.test(LIST));

console.log(`\nRESULTS: ${pass} passed, ${fail} failed`);
process.exit(fail ? 1 : 0);
