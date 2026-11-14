/**
 * F05-C/E/G in a browser page (Chromium, Playwright), without WordPress: public/assets/js/choices-efb.js on the
 * markup the PHP renderer prints, with core-efb.js's helpers replaced by small stand-ins.
 *
 *  - shuffle: the order is kept for the session (a second init / a reload reads sessionStorage), a locked choice
 *    and "Other" keep their places, another session gets another order (randomize stability);
 *  - "Other": the text box follows its choice (enabled, focused), the row carries the text, an empty text
 *    drops the row and Next / Send say otherReq at the field;
 *  - "Select all": checks every choice but "Other", mixed state (indeterminate, aria-checked=mixed);
 *  - the maximum: the other choices turned off (disabled, aria-disabled) with a polite live sentence; the minimum
 *    and maximum on Next / Send; a forged count is the server's job (tests/test-choices-limits-submit.php);
 *  - capacity from forms/session/prepare: "N left" in Persian digits, a used-up choice off / hidden / labelled;
 *  - ranking: Alt+↓ / Alt+↑ / Home / End on the focused item, the arrows, aria-live "X, position N of M" in the
 *    language's digits, the answer row (labels with @efb!, ids), rank_top, "Keep this order", a saved ranking
 *    restored; a server refusal clears the field and shows the sentence (text only).
 *
 * Run: node tests/test-choices-runtime.js
 */
'use strict';
const { chromium } = require('playwright');
const fs = require('fs');
const path = require('path');
const ROOT = path.join(__dirname, '..');
async function launch() {
  if (process.env.EFB_CHROMIUM) return chromium.launch({ executablePath: process.env.EFB_CHROMIUM });
  try { return await chromium.launch(); } catch (e) {
    const base = process.env.PLAYWRIGHT_BROWSERS_PATH || '/opt/pw-browsers';
    const dirs = fs.existsSync(base) ? fs.readdirSync(base).filter((d) => /^chromium_headless_shell-\d+$/.test(d)).sort().reverse() : [];
    for (const d of dirs) { const exe = path.join(base, d, 'chrome-headless-shell-linux64', 'chrome-headless-shell'); if (fs.existsSync(exe)) return chromium.launch({ executablePath: exe }); }
    throw e;
  }
}

let pass = 0, fail = 0;
function t(label, actual, expected) {
  const a = JSON.stringify(actual), e = JSON.stringify(expected);
  if (a === e) { pass++; console.log('[PASS] ' + label); } else { fail++; console.log('[FAIL] ' + label + '\n  expected ' + e + '\n  actual   ' + a); }
}
const RUNTIME = fs.readFileSync(path.join(ROOT, 'public', 'assets', 'js', 'choices-efb.js'), 'utf8');
const CSS = fs.readFileSync(path.join(ROOT, 'public', 'assets', 'css', 'choices-efb.css'), 'utf8');

function page(lang, dir) {
  const opt = (fid, id, label, extra) => `<div class="efb form-check mt-1" data-parent="${fid}" data-id="${id}" id="${id}-v"${extra || ''}><input class="efb form-check-input emsFormBuilder_v" type="${fid === 'col' ? 'radio' : 'checkbox'}" name="${fid}" value="${label}" id="${id}" data-vid="${fid}" data-formid="1"><label for="${id}" id="${id}_lab">${label}</label></div>`;
  return `<!doctype html><html lang="${lang}" dir="${dir}"><head><meta charset="utf-8"><style>${CSS}</style></head><body>
  <div id="body_efb_1">
    <div id="col_labG">Color</div>
    <div class="efb" id="col_options" role="radiogroup" data-random="1">
      ${opt('col', 'c1', 'Red')}${opt('col', 'c2', 'Blue')}${opt('col', 'c3', 'Green')}${opt('col', 'c4', 'Yellow')}${opt('col', 'c5', 'Gray')}${opt('col', 'c6', 'None', ' data-lock="1"')}
      <div class="efb form-check efb-other" data-id="col_oth" data-lock="1" id="col_oth-v"><input class="efb form-check-input emsFormBuilder_v" type="radio" name="col" value="" id="col_oth" data-vid="col" data-formid="1"><label id="col_oth_lab" for="col_oth">Other</label><input type="text" class="efb-other-t" id="col_oth_t" dir="auto" maxlength="20" disabled></div>
    </div><small id="col_-message" style="display:none"></small>
    <div class="efb" id="tg_options" role="group">
      <div class="efb form-check efb-selall" id="tg_all-v"><input type="checkbox" id="tg_all" aria-controls="t1 t2 t3"><label for="tg_all">Select all</label></div>
      ${opt('tg', 't1', 'A')}${opt('tg', 't2', 'B')}${opt('tg', 't3', 'C')}
      <div class="efb form-check efb-other" data-id="tg_oth" id="tg_oth-v"><input class="efb form-check-input emsFormBuilder_v" type="checkbox" value="" id="tg_oth" data-vid="tg" data-formid="1"><label id="tg_oth_lab" for="tg_oth">Other</label><input type="text" id="tg_oth_t" disabled></div>
    </div><small id="tg_-message" style="display:none"></small>
    <div class="efb" id="two_options" role="group">${opt('two', 'w1', 'One')}${opt('two', 'w2', 'Two')}${opt('two', 'w3', 'Three')}</div><small id="two_-message" style="display:none"></small>
    <div class="efb" id="st_options" role="radiogroup">${opt('st', 's1', 'Front')}${opt('st', 's2', 'Back')}${opt('st', 's3', 'Hidden')}</div>
    <div class="efb efb-rank" id="rk_options" data-efb-rank="1" data-formid="1" data-vid="rk" data-top="0" data-touch="1" data-random="0" role="group">
      <ol class="efb efb-rank-list" role="list">
        ${['Price', 'Quality', 'Speed', 'Support'].map((l, i) => `<li class="efb efb-rank-item" tabindex="0" data-op="k${i + 1}" data-label="${l}" id="k${i + 1}-v"><span class="efb efb-rank-grip" aria-hidden="true"></span><span class="efb efb-rank-n">${i + 1}</span><span class="efb efb-rank-label">${l}</span><span class="efb efb-rank-btns"><button type="button" class="efb efb-rank-up">↑</button><button type="button" class="efb efb-rank-down">↓</button></span></li>`).join('')}
      </ol><button type="button" class="efb efb-rank-confirm" aria-pressed="false">Keep this order</button><div class="efb efb-rank-live" aria-live="polite"></div>
    </div><small id="rk_-message" style="display:none"></small>
    <div class="efb efb-rank" id="tp_options" data-efb-rank="1" data-formid="1" data-vid="tp" data-top="2" data-touch="0" data-random="0"><ol class="efb efb-rank-list">
      ${['One', 'Two', 'Three'].map((l, i) => `<li class="efb efb-rank-item" tabindex="0" data-op="j${i + 1}" data-label="${l}" id="j${i + 1}-v"><span class="efb efb-rank-n">${i + 1}</span><span class="efb efb-rank-label">${l}</span><span class="efb efb-rank-btns"><button type="button" class="efb efb-rank-up">↑</button><button type="button" class="efb efb-rank-down">↓</button></span></li>`).join('')}
    </ol><div class="efb efb-rank-live" aria-live="polite"></div></div>
  </div>
  <script>
    var ajax_object_efm = { language: '${lang === 'fa' ? 'fa_IR' : 'en_US'}', text: { otherReq: 'Describe your Other answer for %s.', maxSelM_: 'Choose at most %2$s options in %1$s.', minSelM_: 'Choose at least %2$s options in %1$s.', rnkReq: 'Order %s.', rnkMoved: '%1$s, position %2$s of %3$s', invLeftN: '%s left', soldOut: 'Fully booked' } };
    let sendBack_emsFormBuilder_pub = [];
    let sessionPub_emsFormBuilder = 'reciveFromClient';
    const ROWS = [{ type: 'form' }, { id_: 'col', type: 'radio', name: 'Color', op_random: '1', op_other: '1', required: 1 },
      ...['c1','c2','c3','c4','c5','c6'].map((id) => ({ id_: id, type: 'option', parent: 'col' })),
      { id_: 'tg', type: 'checkbox', name: 'Tags', op_select_all: '1', op_other: '1' }, ...['t1','t2','t3'].map((id) => ({ id_: id, type: 'option', parent: 'tg' })),
      { id_: 'two', type: 'checkbox', name: 'Pick two', maxSelect: '2', minSelect: '2' }, ...['w1','w2','w3'].map((id) => ({ id_: id, type: 'option', parent: 'two' })),
      { id_: 'st', type: 'radio', name: 'Seat', inv_on: '1', inv_left: '1', inv_show: 'disable' }, { id_: 's1', type: 'option', parent: 'st', value: 'Front' }, { id_: 's2', type: 'option', parent: 'st', value: 'Back' }, { id_: 's3', type: 'option', parent: 'st', value: 'Hidden' },
      { id_: 'rk', type: 'ranking', name: 'Priorities', required: 1 }, { id_: 'tp', type: 'ranking', name: 'Top two', rank_top: '2', rank_touch: '0' }];
    window.get_structure_by_form_id_efb = () => ROWS;
    window.sendback_state_handler_efb_v4 = () => {};
    window.handle_change_event_efb_v4 = (el, fid) => {
      const f = ROWS.find((r) => r.id_ === el.dataset.vid);
      if (el.type === 'radio') { sendBack_emsFormBuilder_pub = sendBack_emsFormBuilder_pub.filter((r) => r.id_ !== f.id_); if (el.checked && el.value !== '') sendBack_emsFormBuilder_pub.push({ id_: f.id_, id_ob: el.id, value: el.value, type: f.type, form_id: '1' }); return; }
      sendBack_emsFormBuilder_pub = sendBack_emsFormBuilder_pub.filter((r) => r.id_ob !== el.id);
      if (el.checked && el.value !== '') sendBack_emsFormBuilder_pub.push({ id_: f.id_, id_ob: el.id, value: el.value, type: f.type, form_id: '1' });
    };
    document.addEventListener('change', (e) => { if (e.target.classList && e.target.classList.contains('emsFormBuilder_v')) handle_change_event_efb_v4(e.target, 1); });
  </script>
  <script>${RUNTIME}</script>
  </body></html>`;
}

(async () => {
  const browser = await launch();
  const ctx = await browser.newContext();
  const p = await ctx.newPage();
  const errs = [];
  p.on('pageerror', (e) => errs.push(e.message));
  // A real origin (sessionStorage is not there on about:blank).
  await ctx.route('http://site.test/**', (r) => r.fulfill({ contentType: 'text/html', body: page(r.request().url().indexOf('fa') !== -1 ? 'fa' : 'en', r.request().url().indexOf('fa') !== -1 ? 'rtl' : 'ltr') }));
  await p.goto('http://site.test/en');
  await p.evaluate(() => EfbChoices.initForm('1'));
  const order = () => p.evaluate(() => [...document.getElementById('col_options').children].map((n) => n.dataset.id));
  const o1 = await order();
  t('shuffle: the locked "None" and "Other" keep their places (last two)', [o1[5], o1[6]], ['c6', 'col_oth']);
  await p.evaluate(() => { const b = document.getElementById('col_options'); b.innerHTML = b.innerHTML; b.querySelectorAll('[data-efb-bound]').forEach((x) => x.removeAttribute('data-efb-bound')); EfbChoices._state.bound = {}; EfbChoices.initForm('1'); });
  t('the same order on a second init in this session (sessionStorage efb_ord_1_col)', await order(), o1);
  const stored = await p.evaluate(() => sessionStorage.getItem('efb_ord_1_col'));
  t('the order is kept under efb_ord_{form}_{field}', JSON.parse(stored), o1);
  const seen = new Set();
  for (let i = 0; i < 8; i++) seen.add(JSON.stringify(await p.evaluate(() => EfbChoices.sessionOrder('9', 'f' + Math.random(), ['a', 'b', 'c', 'd', 'e'], ['e'], { getItem: () => null, setItem: () => {} }))));
  t('other sessions get other orders (8 tries), the locked one always last', [seen.size > 1, [...seen].every((s) => JSON.parse(s)[4] === 'e')], [true, true]);

  // Other
  await p.check('#col_oth');
  let st = await p.evaluate(() => ({ dis: document.getElementById('col_oth_t').disabled, focus: document.activeElement.id, rows: sendBack_emsFormBuilder_pub.filter((r) => r.id_ === 'col').length }));
  t('"Other": the text box is enabled and focused; no row while it is empty', st, { dis: false, focus: 'col_oth_t', rows: 0 });
  t('Next / Send: otherReq at the field, the label isolated', await p.evaluate(() => { const ok = EfbChoices.check(get_structure_by_form_id_efb()[1], '1'); const m = document.getElementById('col_-message'); return [ok, m.textContent, m.getAttribute('role')]; }), [false, 'Describe your Other answer for ⁨Color⁩.', 'alert']);
  await p.fill('#col_oth_t', 'Purple');
  st = await p.evaluate(() => sendBack_emsFormBuilder_pub.filter((r) => r.id_ === 'col').map((r) => [r.id_ob, r.value]));
  t('typing: the row carries the text with the Other id', st, [['col_oth', 'Purple']]);
  t('Next / Send pass once the text is there', await p.evaluate(() => EfbChoices.check(get_structure_by_form_id_efb()[1], '1')), true);
  await p.check('#c1');
  t('another choice: the text box is off again', await p.evaluate(() => document.getElementById('col_oth_t').disabled), true);

  // Select all
  await p.check('#tg_all');
  st = await p.evaluate(() => ({ rows: sendBack_emsFormBuilder_pub.filter((r) => r.id_ === 'tg').map((r) => r.id_ob), oth: document.getElementById('tg_oth').checked }));
  t('"Select all": every choice but "Other", itself not a row', st, { rows: ['t1', 't2', 't3'], oth: false });
  await p.uncheck('#t2');
  t('one cleared: mixed (indeterminate, aria-checked=mixed)', await p.evaluate(() => { const a = document.getElementById('tg_all'); return [a.indeterminate, a.getAttribute('aria-checked'), a.checked]; }), [true, 'mixed', false]);
  await p.check('#t2');
  t('all checked again: "Select all" checked', await p.evaluate(() => { const a = document.getElementById('tg_all'); return [a.indeterminate, a.getAttribute('aria-checked'), a.checked]; }), [false, 'true', true]);

  // Maximum / minimum
  await p.check('#w1');
  t('minimum 2 with one chosen: refused on Next / Send', await p.evaluate(() => [EfbChoices.check(get_structure_by_form_id_efb().find((r) => r.id_ === 'two'), '1'), document.getElementById('two_-message').textContent]), [false, 'Choose at least ⁨2⁩ options in ⁨Pick two⁩.']);
  await p.check('#w2');
  st = await p.evaluate(() => ({ d3: document.getElementById('w3').disabled, a3: document.getElementById('w3').getAttribute('aria-disabled'), live: document.getElementById('two_-message').getAttribute('aria-live'), text: document.getElementById('two_-message').textContent }));
  t('at the maximum the third is off (disabled, aria-disabled) and the field says so (polite)', st, { d3: true, a3: 'true', live: 'polite', text: 'Choose at most ⁨2⁩ options in ⁨Pick two⁩.' });
  await p.uncheck('#w2');
  t('below the maximum again: the third is back', await p.evaluate(() => [document.getElementById('w3').disabled, document.getElementById('w3').hasAttribute('aria-disabled')]), [false, false]);

  // Stock
  await p.evaluate(() => EfbChoices.applyStock('1', { s1: 3, s2: 0 }));
  st = await p.evaluate(() => ({ a: document.querySelector('#s1-v .efb-ch-left').textContent, b: document.querySelector('#s2-v .efb-ch-left').textContent, dis: document.getElementById('s2').disabled, sold: document.getElementById('s2-v').classList.contains('efb-soldout'), c: !!document.querySelector('#s3-v .efb-ch-left') }));
  t('prepare\'s stock: "3 left" (isolated), "Fully booked" turned off, nothing beside a choice without a count', st, { a: '⁨3⁩ left', b: 'Fully booked', dis: true, sold: true, c: false });

  // Ranking
  await p.focus('#k1-v');
  await p.keyboard.press('Alt+ArrowDown');
  st = await p.evaluate(() => ({ order: [...document.querySelectorAll('#rk_options .efb-rank-item')].map((l) => l.dataset.op), live: document.querySelector('#rk_options .efb-rank-live').textContent, focus: document.activeElement.id, n: [...document.querySelectorAll('#rk_options .efb-rank-n')].map((x) => x.textContent) }));
  t('Alt+↓ moves the focused item down, keeps the focus, renumbers, announces it', st, { order: ['k2', 'k1', 'k3', 'k4'], live: '⁨Price⁩, position ⁨2⁩ of ⁨4⁩', focus: 'k1-v', n: ['1', '2', '3', '4'] });
  await p.keyboard.press('End');
  await p.focus('#k3-v');
  await p.keyboard.press('Home');
  t('End / Home', await p.evaluate(() => [...document.querySelectorAll('#rk_options .efb-rank-item')].map((l) => l.dataset.op)), ['k3', 'k2', 'k4', 'k1']);
  await p.click('#k4-v .efb-rank-up');
  st = await p.evaluate(() => ({ order: [...document.querySelectorAll('#rk_options .efb-rank-item')].map((l) => l.dataset.op), row: sendBack_emsFormBuilder_pub.find((r) => r.id_ === 'rk') }));
  t('the up arrow; the answer row: labels joined with @efb!, the ids in order', [st.order, st.row && st.row.value, st.row && st.row.ids, st.row && st.row.type], [['k3', 'k4', 'k2', 'k1'], 'Speed@efb!Support@efb!Quality@efb!Price@efb!', 'k3,k4,k2,k1', 'ranking']);
  t('rank_top 2 without "must move": the first two are the answer at once', await p.evaluate(() => { const r = sendBack_emsFormBuilder_pub.find((x) => x.id_ === 'tp'); return r && [r.ids, r.value]; }), ['j1,j2', 'One@efb!Two@efb!']);
  t('the down arrow is off on the last item, the up arrow on the first', await p.evaluate(() => [document.querySelector('#k1-v .efb-rank-down').disabled, document.querySelector('#k3-v .efb-rank-up').disabled]), [true, true]);
  await p.evaluate(() => EfbChoices.restoreRanking('1', { id_: 'rk', ids: 'k4,k1,k2,k3' }));
  t('a saved ranking (a draft) comes back in its order', await p.evaluate(() => [...document.querySelectorAll('#rk_options .efb-rank-item')].map((l) => l.dataset.op)), ['k4', 'k1', 'k2', 'k3']);
  // A server refusal at a field: cleared, the sentence as text.
  await p.evaluate(() => EfbChoices.serverError('1', 'tg', 'efb_choice_max', 'Choose at most <b>2</b> <img src=x onerror="window.pwn=1">', ''));
  t('a server refusal: the field\'s choices cleared, its sentence as text (no element)', await p.evaluate(() => [[...document.querySelectorAll('#tg_options input')].filter((i) => i.checked).length, document.getElementById('tg_-message').textContent, document.querySelectorAll('#tg_-message img').length, !!window.pwn]), [0, 'Choose at most 2 ', 0, false]);
  t('no page error', errs, []);
  await p.close();

  // Persian page: digits.
  const f = await ctx.newPage();
  await f.goto('http://site.test/fa');
  await f.evaluate(() => { ajax_object_efm.language = 'fa_IR'; EfbChoices.initForm('1'); EfbChoices.applyStock('1', { s1: 12 }); });
  await f.focus('#k2-v');
  await f.keyboard.press('Alt+ArrowUp');
  st = await f.evaluate(() => ({ left: document.querySelector('#s1-v .efb-ch-left').textContent, live: document.querySelector('#rk_options .efb-rank-live').textContent, n: document.querySelector('#rk_options .efb-rank-n').textContent, bidi: getComputedStyle(document.querySelector('#s1-v .efb-ch-left')).unicodeBidi }));
  t('fa_IR: "N left", positions and the announcement in Persian digits; isolated text', st, { left: '⁨۱۲⁩ left', live: '⁨Quality⁩, position ⁨۱⁩ of ⁨۴⁩', n: '۱', bidi: 'plaintext' });
  await browser.close();
  console.log('\nRESULTS: ' + pass + ' passed, ' + fail + ' failed');
  process.exit(fail ? 1 : 0);
})().catch((e) => { console.log('[FAIL] ' + (e && e.stack || e)); console.log('\nRESULTS: ' + pass + ' passed, ' + (fail + 1) + ' failed'); process.exit(1); });
