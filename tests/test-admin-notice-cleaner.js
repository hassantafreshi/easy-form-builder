/**
 * The notice cleaner of the EFB admin screens (admin-efb.js, fun_observer_state_efb): it hides other plugins'
 * notices (update nags, ads) but must keep every notice of Easy Form Builder and of its add-ons. It used to keep only
 * class "efb", so the payment add-on's "made for another version", "WP-Cron is disabled", the language-pack install,
 * the SPLIT Stripe notice, the Data Tools card and the F12b notices disappeared (BUGS-FOUND F12b).
 *
 *  - efb_is_own_notice_efb(): class efb / efb-* / emsfb-*, an id efb* / emsfb*, data-efb-notice → kept; a WordPress
 *    update nag or another plugin's notice → removed;
 *  - noti_check() removes only the others;
 *  - every notice markup in core (includes/) and in the add-ons (../vendores-efb) carries one of those marks.
 *
 * Run: node tests/test-admin-notice-cleaner.js
 */
'use strict';
const fs = require('fs');
const path = require('path');
const vm = require('vm');
const ROOT = path.join(__dirname, '..');
let passed = 0, failed = 0;
const t = (n, a, e) => { const A = JSON.stringify(a), E = JSON.stringify(e); if (A === E) { passed++; console.log('[PASS] ' + n); } else { failed++; console.log('[FAIL] ' + n + '\n  expected: ' + E + '\n  actual:   ' + A); } };

const src = fs.readFileSync(path.join(ROOT, 'includes/admin/assets/js/admin-efb.js'), 'utf8');
const pred = src.match(/efb_is_own_notice_efb=\(el\)=>\{[\s\S]*?\n\}\n/);
const obs = src.match(/fun_observer_state_efb=\(mutation\)=>\{[\s\S]*?\n\}\n/);
t('admin-efb.js has the predicate and the observer uses it', [!!pred, !!obs && /if\(!efb_is_own_notice_efb\(el\)\) el\.remove\(\)/.test(obs[0])], [true, true]);

const el = (cls, id, attrs) => {
  const a = Object.assign({}, attrs || {});
  const e = { id: id || '', classList: cls.split(/\s+/).filter(Boolean), removed: false, label: cls + '#' + (id || ''), hasAttribute: (k) => k in a };
  e.classList.contains = (c) => e.classList.indexOf(c) !== -1;
  e.remove = () => { e.removed = true; };
  return e;
};
const page = [
  el('notice notice-warning efb-notice'), // payment add-on "made for another version", WP-Cron
  el('notice notice-info efb-notice', 'emsfb-install'), // language packs
  el('notice notice-warning efb-split-stripe-pending'),
  el('notice notice-info is-dismissible efb-dt-card', 'efbDataToolsCard'),
  el('notice notice-info is-dismissible efb efb-spam-notice', 'efb-spam-notice', { 'data-efb-notice': 'spam' }),
  el('efb notice notice-error efb-delivery-notice'),
  el('notice notice-warning', '', { 'data-efb-notice': 'x' }),
  el('update-nag notice notice-warning inline'), // WordPress core update nag
  el('notice notice-info is-dismissible', 'other-plugin-promo'),
  el('nf-admin-notice notice'),
];
const ctx = { document: { querySelectorAll: () => page } };
vm.createContext(ctx);
vm.runInContext(pred[0] + obs[0] + ';this.own = efb_is_own_notice_efb; this.obs = fun_observer_state_efb;', ctx);
t('the predicate: the seven EFB / add-on notices are own, the three others are not', page.map((e) => ctx.own(e)), [true, true, true, true, true, true, true, false, false, false]);
ctx.obs({ type: 'childList' });
t('noti_check(): only the other plugins\' notices and WordPress\' nag are removed', page.filter((e) => e.removed).map((e) => e.label), ['update-nag notice notice-warning inline#', 'notice notice-info is-dismissible#other-plugin-promo', 'nf-admin-notice notice#']);

// Every notice markup of core and of the add-ons carries a mark the cleaner keeps.
const files = [];
const walk = (dir, skip) => { if (!fs.existsSync(dir)) return; for (const f of fs.readdirSync(dir)) { const p = path.join(dir, f); if (skip.test(p)) continue; const s = fs.statSync(p); if (s.isDirectory()) walk(p, skip); else if (/\.php$/.test(f)) files.push(p); } };
walk(path.join(ROOT, 'includes'), /node_modules/);
const addons = fs.existsSync(path.join(ROOT, '..', 'vendores-efb')) ? path.join(ROOT, '..', 'vendores-efb') : null;
if (addons) walk(addons, /node_modules|\.git|v4\.1|backup|vendor[\/\\](?!.*efb)/);
const bad = [];
for (const f of files) {
  fs.readFileSync(f, 'utf8').split('\n').forEach((line, i) => {
    const m = line.match(/<div[^>]*class=\\?"([^"\\]*\bnotice\b[^"\\]*)\\?"[^>]*>/) || line.match(/<div[^>]*class=\\?'([^'\\]*\bnotice\b[^'\\]*)\\?'[^>]*>/);
    if (!m) return;
    const tag = m[0];
    const own = /(^|\s)(efb|emsfb)[\w-]*/.test(m[1]) || /id=\\?["'](efb|emsfb|%1\$s)/i.test(tag) || /data-efb-notice/.test(tag);
    if (!own) bad.push(path.relative(path.join(ROOT, '..'), f) + ':' + (i + 1));
  });
}
t('every notice markup in core' + (addons ? ' and the add-ons' : '') + ' carries efb / efb-* / an efb id (' + files.length + ' files read)', bad, []);
console.log('\nRESULTS: ' + passed + ' passed, ' + failed + ' failed');
process.exit(failed ? 1 : 0);
