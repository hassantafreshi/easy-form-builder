/**
 * BUG-03, the settings and list rows of BUGS-FOUND (B7):
 *  - row 301: Settings › Localization made one input per phrase with id="<phrase key>" (and a second id="labelEl"
 *    on the same tag), so a phrase key equal to another id of the settings screen (vrfWebOtp …) made
 *    getElementById() return the wrong element. Now id="efbLoc_<key>", data-key="<key>", one id, a name
 *    (aria-label), and the change handler stores the text under data-key;
 *  - row 389: the global search's results showed the stored date ("2026-11-19 10:05:00") while the entries list
 *    shows the site's format: get_ajax_track_admin() adds date_display (Entries::display_date()) to each row and the
 *    list prints it (efb_list_date_efb(): the site's digits, escaped; the stored date when there is none).
 * The functions are taken from the shipped list_form-efb.js and run by node.
 *
 * Run: node tests/test-bug03-settings-list.js
 */
'use strict';
const fs = require('fs');
const path = require('path');
const root = path.join(__dirname, '..');
let pass = 0, fail = 0;
const t = (name, actual, expected) => {
  const ok = JSON.stringify(actual) === JSON.stringify(expected);
  if (ok) { pass++; console.log('[PASS] ' + name); } else { fail++; console.log('[FAIL] ' + name + ': expected ' + JSON.stringify(expected) + ', got ' + JSON.stringify(actual)); }
};
const js = fs.readFileSync(path.join(root, 'includes/admin/assets/js/list_form-efb.js'), 'utf8');

/* ---- row 301 ---- */
const loopStart = js.indexOf('  Object.entries(text).forEach(([key, value]) => {');
const loopEnd = js.indexOf('  });', loopStart) + 5;
t('the Localization list loop is found', loopStart > 0 && loopEnd > loopStart, true);
const loop = js.slice(loopStart, loopEnd);
const build = new Function('text', 'efb_var', 'let state; let textList = ""; ' + loop + '; return textList;');
const html = build({ vrfWebOtp: 'Get the code by SMS', create: 'Create', easyFormBuilder: 'Easy Form Builder', forbiddenChr: 'x' }, { text: { localization: 'Localization' } });
const tags = html.match(/<input [^>]*>/g) || [];
t('one input per phrase (forbiddenChr left out)', tags.length, 3);
t('every input has exactly one id', tags.map((x) => (x.match(/\sid="/g) || []).length), [1, 1, 1]);
t('the ids are prefixed (efbLoc_<key>), never the bare key', tags.map((x) => (x.match(/\sid="([^"]+)"/) || [])[1]), ['efbLoc_vrfWebOtp', 'efbLoc_create', 'efbLoc_easyFormBuilder']);
t('the key is in data-key', tags.map((x) => (x.match(/data-key="([^"]+)"/) || [])[1]), ['vrfWebOtp', 'create', 'easyFormBuilder']);
t('each input has a name (aria-label)', tags.every((x) => /aria-label="[^"]+"/.test(x)), true);
t('no id="labelEl" left on these inputs', /id="labelEl"/.test(html), false);
t('the change handler stores the text under data-key', js.includes('text[el.dataset.key || el.id] = el.value;'), true);

/* ---- row 389 ---- */
// QA1-028: efb_list_date_efb() (list_form-efb.js) escapes the admin's one date formatter efb_admin_date_efb() (admin-efb.js).
const adminJs = fs.readFileSync(path.join(root, 'includes/admin/assets/js/admin-efb.js'), 'utf8');
const fs0 = js.indexOf('function efb_list_date_efb(v) {');
const fe = js.indexOf('\n}\n', fs0) + 2;
const as0 = adminJs.indexOf('function efb_admin_date_efb(raw) {');
const ae = adminJs.indexOf('\n}\n', as0) + 2;
t('efb_list_date_efb() is in list_form-efb.js, efb_admin_date_efb() in admin-efb.js', fs0 > 0 && as0 > 0, true);
const dateFn = (lan) => new Function('efb_var', adminJs.slice(as0, ae) + js.slice(fs0, fe) + '; return efb_list_date_efb;')({ wp_lan: lan });
t('en: the date as the entries list shows it (date_display)', dateFn('en_US')({ date: '2026-11-19 10:05:00', date_display: 'November 19, 2026 10:05 am' }), 'November 19, 2026 10:05 am');
t('fa_IR: in Persian digits', dateFn('fa_IR')({ date: '2026-11-19 10:05:00', date_display: '19 نوامبر 2026 10:05' }), '۱۹ نوامبر ۲۰۲۶ ۱۰:۰۵');
t('ar: in Arabic-Indic digits', dateFn('ar')({ date: '2026-11-19 10:05:00', date_display: '19 نوفمبر 2026' }), '١٩ نوفمبر ٢٠٢٦');
t('no date_display (an older server answer): the stored date', dateFn('en_US')({ date: '2026-11-19 10:05:00' }), '2026-11-19 10:05:00');
t('escaped', dateFn('en_US')({ date_display: '<b>x</b>' }), '&lt;b&gt;x&lt;/b&gt;');
t('the list prints it in <bdi>', js.includes('data-label="${efb_var.text.ddate}"><bdi>${efb_list_date_efb(v)}</bdi></td>'), true);
const adm = fs.readFileSync(path.join(root, 'includes/admin/class-Emsfb-admin.php'), 'utf8');
t('the search answer adds date_display (Entries::display_date()) to the track lookup and to the full search', adm.includes('$row->date_display = \\Emsfb\\Entries::display_date((string) $row->date);') && adm.includes('$value = $date_display_efb($value);') && adm.includes('$value = $date_display_efb($this->db->get_results($combined_sql));'), true);

t('the partial search\'s UNION has no parentheses (the SQLite integration refused them: "Not found")', adm.includes('$combined_sql = "$sql_msg UNION $sql_rsp ORDER BY date DESC";') && !adm.includes('"($sql_msg) UNION ($sql_rsp)'), true);

console.log(`\nRESULTS: ${pass} passed, ${fail} failed`);
process.exit(fail ? 1 : 0);
