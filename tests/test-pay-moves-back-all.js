/**
 * FX-PAY hand-off (FX-ADMIN): Settings › Payments › forms moved to the new payment fields.
 *  - "Move every form back" is shown while a form can be moved back, asks first, and calls REST
 *    payments/legacy-migration/rollback-all; the list is drawn again from its answer; forms that stayed are counted;
 *  - a per-form Move back that the server refuses says why (field_changed, no_backup, no_form; else the server's
 *    message) instead of "That did not work".
 * The script of list_form-efb.js runs in a small stand-in page (vm).
 *
 * Run: node tests/test-pay-moves-back-all.js
 */
'use strict';
const fs = require('fs');
const path = require('path');
const vm = require('vm');
let pass = 0, fail = 0;
const t = (n, ok, d) => { if (ok) { pass++; console.log('[PASS] ' + n); } else { fail++; console.log('[FAIL] ' + n + (d !== undefined ? ' — ' + JSON.stringify(d) : '')); } };
const src = fs.readFileSync(path.join(__dirname, '../includes/admin/assets/js/list_form-efb.js'), 'utf8');
const fn = src.slice(src.indexOf('function efb_pay_txt_efb('), src.indexOf('\nfunction fun_show_setting__emsFormBuilder'));

function page(replies, confirmed) {
  const els = {};
  const el = (id) => els[id] || (els[id] = { id, hidden: new Set(['d-none']), innerHTML: '', textContent: '', disabled: false, attrs: {}, classList: { toggle(c, on) { on ? els[id].hidden.add(c) : els[id].hidden.delete(c); }, add(c) { els[id].hidden.add(c); } }, setAttribute(k, v) { this.attrs[k] = v; }, getAttribute(k) { return this.attrs[k] || null; }, addEventListener(e, f) { this.on = f; } });
  ['efbPayMoves', 'efbPayMovesList', 'efbPayMovesRun', 'efbPayMovesMsg', 'efbPayMovesBackAll'].forEach(el);
  const calls = [];
  const ctx = {
    efb_var: { legacy_gw: { moves: { rest: '/wp-json/Emsfb/v1/payments/legacy-migration' } }, text: {}, nonce: 'n' },
    document: { getElementById: (id) => els[id] || null },
    window: { confirm: () => confirmed },
    fetch: (u, o) => { calls.push([u.replace(/^.*legacy-migration/, ''), o.method]); const r = replies.shift() || [200, {}]; return Promise.resolve({ ok: r[0] < 300, json: () => Promise.resolve(r[1]) }); },
  };
  vm.createContext(ctx);
  vm.runInContext(fn + '\nefb_pay_moves_load_efb();', ctx);
  return { els, calls, click: (b) => { els.efbPayMoves.on({ target: { closest: () => b } }); return new Promise((r) => setTimeout(r, 30)); } };
}
const moved = { forms: [{ form_id: 7, name: 'Workshop', status: 'migrated', can_rollback: true }] };
(async () => {
  let p = page([[200, moved], [207, { done: [], failed: { 7: 'field_changed' }, status: moved }]], true);
  await new Promise((r) => setTimeout(r, 30));
  t('"Move every form back" is shown while a form can be moved back', !p.els.efbPayMovesBackAll.hidden.has('d-none'));
  await p.click(Object.assign(p.els.efbPayMovesBackAll, { id: 'efbPayMovesBackAll' }));
  t('…it asks first, then POSTs rollback-all', JSON.stringify(p.calls[1]) === JSON.stringify(['/rollback-all', 'POST']), p.calls);
  t('…a form that stayed is counted ("Forms not moved back: 1.")', /^Forms not moved back: 1\.$/.test(p.els.efbPayMovesMsg.textContent), p.els.efbPayMovesMsg.textContent);
  p = page([[200, moved]], false);
  await new Promise((r) => setTimeout(r, 30));
  await p.click(Object.assign(p.els.efbPayMovesBackAll, { id: 'efbPayMovesBackAll' }));
  t('…nothing is sent when the question is answered No', p.calls.length === 1, p.calls);
  for (const [code, re] of [['field_changed', /changed after the move/], ['no_backup', /no copy of this form/], ['no_form', /no longer exists/]]) {
    p = page([[200, moved], [409, { ok: false, code }]], true);
    await new Promise((r) => setTimeout(r, 30));
    const btn = { id: '', disabled: false, attrs: { 'data-pmv': 'rollback', 'data-id': '7' }, getAttribute(k) { return this.attrs[k] || null; }, setAttribute(k, v) { this.attrs[k] = v; } };
    await p.click(btn);
    t(`a refused Move back says why (${code}), not "That did not work"`, re.test(p.els.efbPayMovesMsg.textContent), p.els.efbPayMovesMsg.textContent);
  }
  p = page([[200, moved], [500, { code: 'x', message: 'The server said no.' }]], true);
  await new Promise((r) => setTimeout(r, 30));
  await p.click({ id: '', disabled: false, attrs: { 'data-pmv': 'rollback', 'data-id': '7' }, getAttribute(k) { return this.attrs[k] || null; }, setAttribute() {} });
  t('another refusal shows the server\'s message', p.els.efbPayMovesMsg.textContent === 'The server said no.', p.els.efbPayMovesMsg.textContent);
  console.log(`\nRESULTS: ${pass} passed, ${fail} failed`);
  process.exit(fail ? 1 : 0);
})();
