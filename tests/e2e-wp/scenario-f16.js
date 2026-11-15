/**
 * F16 scenarios of tests/e2e-wp/run.js (docs/feature-gaps/F16-a11y-inline-validation.fa.md, the scenario list of
 * subtasks A–G), on the real site. axe-core (devDependency, node_modules/axe-core/axe.min.js, injected from disk: no CDN)
 * checks the public form against WCAG 2.0 / 2.1 / 2.2 A and AA.
 *
 *  f16-a  builder (en 1280): a new form gets val_timing blur / err_summary 1 / warn_unsaved 0; Form settings shows
 *         "Check fields" and the two switches (aria-pressed from the start); a field's "Browser autofill"; Save through
 *         admin-ajax (the real sanitizer keeps the keys, drops a bad token set by hand); reopened, the controls come back;
 *         efbBuilderApi.setAutocomplete; capabilities and forms/{id}/schema.
 *  f16-b  a guest, en at 1280 and 375, keyboard only: autocomplete tokens on the page; Tab through → no error; a wrong
 *         e-mail left → the error (aria-invalid, described by it, said in the live region); typed right → gone; Next →
 *         the summary (focus, links); Enter on a link → its field; put right → Next → focus on step 2 and its legend;
 *         the accessibility tree names every field by its label; axe: no violation with errors and the summary on screen.
 *  f16-c  fa_IR and ar at 375 and 1280: right to left, labels on the right (English ones too), the summary in Persian
 *         digits, a message never over its label, nothing off-screen; axe clean; screenshots.
 *  f16-d  several forms on one page (two F16 forms and one from before F16): own live regions and summaries, errors stay
 *         in their form, the old form keeps its toast and change timing; the server refuses a used value (F03 unique):
 *         back to the field's step with its summary; sent: the focus on the thank-you heading.
 *  f16-e  a repeater group and three steps: an error inside a row is a summary link to that row's field; the group's help
 *         text starts where its legend starts (F09 indent, fixed); ar 375.
 *  f16-f  the add-ons' fields with the add-ons on and off: calculation error on the result (aria-invalid, described,
 *         said once), the typed signature's name box, the map named by its label; off: the page still works, axe clean.
 *  f16-g  "Leave this page?" on the real page (typed → asked; sent → not); the F15 versions dialog's dates in fa_IR
 *         (Solar Hijri, Persian digits).
 */
'use strict';

const path = require('path');

module.exports = function (S) {
  const { scenario, R, ctl, CFG, sleep, SHOT } = S;
  const c16 = (...a) => { const out = S.wp('eval-file', path.join(__dirname, 'ctl-f16.php'), ...a.map(String)).trim().split('\n'); return JSON.parse(out[out.length - 1]); };
  const AXE = path.join(__dirname, '..', '..', 'node_modules', 'axe-core', 'axe.min.js');
  const form = (name, fields, row0, steps) => ctl('f03-form', name, JSON.stringify(fields), row0 || '', String(steps || 1));
  const guest = async (context, vp, label) => (await context({ viewport: vp || { width: 1280, height: 900 } }, label || 'guest')).newPage();
  const go = async (p, url) => { await p.goto(url, { waitUntil: 'load' }); await p.waitForFunction(() => document.querySelector('.body_efb .emsFormBuilder_v[data-efb-init="1"]'), null, { timeout: 20000 }); await sleep(600); };
  /** axe on the forms of the page (WCAG 2.x A / AA); [] when clean. */
  const axe = async (p) => {
    await p.addScriptTag({ path: AXE });
    return p.evaluate(async () => {
      const out = [];
      for (const b of document.querySelectorAll('.body_efb')) {
        const r = await window.axe.run(b, { runOnly: { type: 'tag', values: ['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa'] } });
        r.violations.forEach((v) => out.push(v.id + ' (' + v.nodes.length + '): ' + v.nodes.slice(0, 2).map((n) => n.target.join(' ')).join(', ')));
      }
      return out;
    });
  };
  const msg = (p, id) => p.evaluate((i) => { const m = document.getElementById(i + '_-message'); return m && m.style.display === 'block' ? m.textContent : ''; }, id);
  const field = (p, id) => p.evaluate((i) => { const e = document.getElementById(i + '_') || document.getElementById(i + '_options'); return e ? { invalid: e.getAttribute('aria-invalid'), desc: e.getAttribute('aria-describedby'), ac: e.getAttribute('autocomplete'), type: e.type } : null; }, id);
  const summary = (p, fid) => p.evaluate((f) => { const b = document.getElementById('efb-errsum-' + f); return b ? { shown: !b.classList.contains('d-none'), title: (b.querySelector('h2') || {}).textContent || '', links: Array.from(b.querySelectorAll('a[data-efb-field]')).map((a) => a.textContent.trim()), focus: document.activeElement === b, dir: getComputedStyle(b).direction } : null; }, String(fid));
  const where = (p) => p.evaluate(() => { const a = document.activeElement; return a ? a.tagName + (a.id ? '#' + a.id : '') : ''; });
  const tabTo = async (p, sel, max) => { for (let i = 0; i < (max || 30); i++) { if (await p.evaluate((s) => document.activeElement && document.activeElement.matches(s), sel)) return true; await p.keyboard.press('Tab'); } return false; };
  const overlap = (p, fid) => p.evaluate((f) => {
    const bad = [];
    document.querySelectorAll('#body_efb_' + f + ' [id$="_-message"]').forEach((m) => {
      if (m.style.display !== 'block') return;
      const id = m.id.slice(0, -10), l = document.getElementById(id + '_lab');
      if (!l) return;
      const a = m.getBoundingClientRect(), b = l.getBoundingClientRect();
      if (!(a.bottom <= b.top + 1 || a.top >= b.bottom - 1 || a.right <= b.left + 1 || a.left >= b.right - 1)) bad.push(id);
    });
    return bad;
  }, String(fid));
  const offscreen = (p) => p.evaluate(() => document.documentElement.scrollWidth > document.documentElement.clientWidth + 1);

  const FIELDS = (L) => [
    { key: 'em', type: 'email', name: L.em, required: '1' },
    { key: 'nm', type: 'text', name: L.nm, required: '1', autocomplete: 'name' },
    { key: 'cl', type: 'radio', name: L.cl, required: '1' },
    { key: 'c1', type: 'option', parent_key: 'cl', value: L.c1 },
    { key: 'c2', type: 'option', parent_key: 'cl', value: L.c2 },
    { key: 'ph', type: 'tel', name: L.ph, required: '0', step: 2 },
    { key: 'ws', type: 'url', name: L.ws, required: '0', step: 2 },
  ];
  const EN = { em: 'Email', nm: 'Full name', cl: 'Colour', c1: 'Red', c2: 'Blue', ph: 'Phone', ws: 'Website' };
  const F16 = 'val_timing=blur&err_summary=1&warn_unsaved=1';

  /* ------------------------------------------------------------------------------------------------ f16-a */
  scenario('f16-a', 'F16 builder: the defaults of a new form, Check fields / summary / leave warning, Browser autofill, Save and back, AutoPilot, schema', async ({ adminPage }) => {
    const a = await adminPage();
    await S.openNewForm(a);
    const r0 = await a.evaluate(() => ({ v: valj_efb[0].val_timing, e: valj_efb[0].err_summary, w: valj_efb[0].warn_unsaved, caps: ['setting:val_timing', 'setting:err_summary', 'setting:warn_unsaved', 'setting:autocomplete'].map((k) => !!(efb_var.capabilities.features[k] && efb_var.capabilities.features[k].available_now)), a11y: efb_var.capabilities.a11y && efb_var.capabilities.a11y.v }));
    R.check('f16-a: a new form: val_timing blur, err_summary 1, warn_unsaved 0 (one step: open question 1)', r0.v === 'blur' && r0.e === '1' && r0.w === '0', r0);
    R.check('f16-a: the registry lists the four settings (free) and capabilities.a11y', r0.caps.every(Boolean) && r0.a11y === 1, r0);
    await a.evaluate(() => show_setting_window_efb('formSet'));
    await sleep(1500);
    const fs = await a.evaluate(() => ({ vt: (document.getElementById('valTimingEl') || {}).value, es: document.getElementById('errSummaryEl') && document.getElementById('errSummaryEl').getAttribute('aria-pressed'), wu: document.getElementById('warnUnsavedEl') && document.getElementById('warnUnsavedEl').getAttribute('aria-pressed'), mismatch: Array.from(document.querySelectorAll('#childsSideMenuConEfb .btn-toggle')).filter((b) => b.classList.contains('active') !== (b.getAttribute('aria-pressed') === 'true')).map((b) => b.id) }));
    R.check('f16-a: Form settings: "Check fields" = blur, the summary switch on, the leave switch off; every toggle says its state', fs.vt === 'blur' && fs.es === 'true' && fs.wu === 'false' && fs.mismatch.length === 0, fs);
    await a.$eval('#warnUnsavedEl', (e) => e.click());
    await sleep(300);
    R.check('f16-a: the leave switch writes warn_unsaved "1" and says aria-pressed="true"', await a.evaluate(() => valj_efb[0].warn_unsaved === '1' && document.getElementById('warnUnsavedEl').getAttribute('aria-pressed') === 'true'), null);
    await S.closePanel(a);
    const em = await S.addTile(a, 'email');
    const tx = await S.addTile(a, 'text');
    await S.openField(a, em);
    await a.waitForSelector('#autocompleteEl', { timeout: 8000 });
    const acUi = await a.evaluate(() => { const s = document.getElementById('autocompleteEl'); return { first: s.options[0].text, groups: Array.from(s.querySelectorAll('optgroup')).map((g) => g.label) }; });
    R.check('f16-a: the field\'s "Browser autofill": Automatic (email), the five groups', /\(email\)$/.test(acUi.first) && acUi.groups.length === 5, acUi);
    await a.selectOption('#autocompleteEl', 'off');
    await sleep(300);
    R.check('f16-a: "Off" is written to the field and its canvas input', await a.evaluate((id) => valj_efb.find((r) => r.id_ === id).autocomplete === 'off' && document.getElementById(id + '_').getAttribute('autocomplete') === 'off', em.id_), null);
    await S.closePanel(a);
    const api = await a.evaluate((id) => { try { return efbBuilderApi.setAutocomplete(id, 'section-x billing given-name'); } catch (e) { return { code: e.code }; } }, tx.id_);
    R.check('f16-a: efbBuilderApi.setAutocomplete() writes a token with section and billing', api.autocomplete === 'section-x billing given-name', api);
    const ur = await S.addTile(a, 'url');
    await S.closePanel(a);
    await a.evaluate((id) => { valj_efb.find((r) => r.id_ === id).autocomplete = 'evil onfocus'; valj_efb[0].formName = 'F16 e2e builder'; }, ur.id_);
    const saveResp = a.waitForResponse((r) => /action=(add_form_Emsfb|update_form_Emsfb)/.test(r.request().postData() || ''), { timeout: 20000 });
    await a.click("a[onclick=\"saveFormEfb(1)\"]");
    const save = await (await saveResp).json();
    const fid = Number(save.data && (save.data.id || save.data.form_id));
    R.check('f16-a: Save through admin-ajax', save.success && fid > 0, save.data);
    await sleep(1200);
    const rows = c16('rows', fid);
    const by = (id) => rows.find((x) => x.id_ === id) || {};
    R.check('f16-a: stored (real sanitizer): val_timing blur, err_summary 1, warn_unsaved 1; the e-mail "off", the text "section-x billing given-name"', rows[0].val_timing === 'blur' && rows[0].err_summary === '1' && rows[0].warn_unsaved === '1' && by(em.id_).autocomplete === 'off' && by(tx.id_).autocomplete === 'section-x billing given-name', { r0: [rows[0].val_timing, rows[0].err_summary, rows[0].warn_unsaved], em: by(em.id_).autocomplete, tx: by(tx.id_).autocomplete });
    R.check('f16-a: a token set by hand that browsers do not know ("evil onfocus") is dropped on save', !('autocomplete' in by(ur.id_)), by(ur.id_).autocomplete);
    const schema = await (await a.request.get(CFG.base + '/wp-json/Emsfb/v1/forms/' + fid + '/schema')).json();
    const sf = (schema.fields || []).reduce((o, f) => { o[f.id] = f.autocomplete; return o; }, {});
    R.check('f16-a: forms/{id}/schema: a11y {blur, 1, 1} and each field\'s effective token', schema.a11y && schema.a11y.val_timing === 'blur' && schema.a11y.warn_unsaved === '1' && sf[em.id_] === 'off' && sf[tx.id_] === 'section-x billing given-name', { a11y: schema.a11y, sf });
  });

  /* ------------------------------------------------------------------------------------------------ f16-b */
  scenario('f16-b', 'F16 a guest by keyboard only, en at 1280 and 375: Baymard timing, the summary, focus on the steps, the accessibility tree, axe', async ({ context }) => {
    const F = form('F16 e2e en', FIELDS(EN), F16, 2);
    const pg = ctl('page', F.form_id);
    for (const vp of [{ width: 1280, height: 900 }, { width: 375, height: 800 }]) {
      const w = vp.width;
      const p = await guest(context, vp, 'guest-' + w);
      await go(p, pg.url);
      const ac = { em: (await field(p, F.ids.em)).ac, nm: (await field(p, F.ids.nm)).ac, ph: (await field(p, F.ids.ph)).ac, ws: (await field(p, F.ids.ws)).ac };
      R.check(`f16-b (${w}): autocomplete: email, the owner's "name", tel, url`, ac.em === 'email' && ac.nm === 'name' && ac.ph === 'tel' && ac.ws === 'url', ac);
      await p.focus('[id="' + F.ids.em + '_"]');
      await p.keyboard.press('Tab');
      await p.keyboard.press('Tab');
      await sleep(300);
      R.check(`f16-b (${w}): Tab through required fields: no error yet`, (await msg(p, F.ids.em)) === '' && (await msg(p, F.ids.nm)) === '', null);
      await p.focus('[id="' + F.ids.em + '_"]');
      await p.keyboard.type('ana@', { delay: 25 });
      await sleep(400);
      R.check(`f16-b (${w}): typing: no error while typing`, (await msg(p, F.ids.em)) === '', null);
      await p.keyboard.press('Tab');
      await sleep(400);
      const e1 = await field(p, F.ids.em);
      const live = await p.evaluate((f) => document.getElementById('efb-live-' + f).textContent, String(F.form_id));
      R.check(`f16-b (${w}): left wrong: the error, aria-invalid, described by it, said once`, (await msg(p, F.ids.em)) !== '' && e1.invalid === 'true' && (e1.desc || '').split(' ')[0] === F.ids.em + '_-message' && live.indexOf('Email: ') === 0, { e1, live });
      await p.focus('[id="' + F.ids.em + '_"]');
      await p.keyboard.press('End');
      await p.keyboard.type('example.com', { delay: 20 });
      await sleep(500);
      R.check(`f16-b (${w}): typed right: the error gone before leaving`, (await msg(p, F.ids.em)) === '' && (await field(p, F.ids.em)).invalid === null, null);
      R.check(`f16-b (${w}): Tab reaches Next`, await tabTo(p, '#next_efb'), await where(p));
      await p.keyboard.press('Enter');
      await sleep(900);
      let s = await summary(p, F.form_id);
      R.check(`f16-b (${w}): Next: the summary has the focus, two links (name, colour) in page order`, s.shown && s.focus && s.title === '2 problems need your attention' && s.links.length === 2 && s.links[0].indexOf('Full name') === 0 && s.links[1].indexOf('Colour') === 0, s);
      const axe1 = await axe(p);
      R.check(`f16-b (${w}): axe (WCAG 2.2 AA) with the errors and the summary on screen: no violation`, axe1.length === 0, axe1);
      await p.keyboard.press('Tab');
      await p.keyboard.press('Enter');
      await sleep(400);
      R.check(`f16-b (${w}): Enter on the first link: the focus in "Full name"`, (await where(p)) === 'INPUT#' + F.ids.nm + '_', await where(p));
      await p.keyboard.type('Ana Example');
      await p.keyboard.press('Tab');
      await p.keyboard.press('Space');
      await sleep(400);
      s = await summary(p, F.form_id);
      R.check(`f16-b (${w}): both put right: the summary hides`, !s.shown, s);
      const tree = await p.locator('#body_efb_' + F.form_id).ariaSnapshot();
      R.check(`f16-b (${w}): the accessibility tree names the fields by their labels (textbox "Email", "Full name", radiogroup "Colour")`, /textbox "Email/.test(tree) && /textbox "Full name/.test(tree) && /radiogroup "Colour/.test(tree), tree.slice(0, 600));
      R.check(`f16-b (${w}): the star is not read ("Email *" never)`, !/"Email \*"|"Full name \*"/.test(tree), null);
      await tabTo(p, '#next_efb');
      await p.keyboard.press('Enter');
      await sleep(1000);
      const st = await p.evaluate((f) => ({ act: document.activeElement.id, legend: (document.activeElement.querySelector('legend') || {}).textContent, live: document.getElementById('efb-live-' + f).textContent }), String(F.form_id));
      R.check(`f16-b (${w}): step 2: the focus on its fieldset, legend "Step 2 of 2: …", "Step 2 of 2" said`, st.act === 'step-2-efb' && /^Step 2 of 2/.test(st.legend) && st.live === 'Step 2 of 2', st);
      const axe2 = await axe(p);
      R.check(`f16-b (${w}): axe on step 2: no violation`, axe2.length === 0, axe2);
      if (w === 375) R.check('f16-b (375): nothing off-screen', !(await offscreen(p)), null);
      await p.screenshot({ path: SHOT(`f16-b-en-${w}`), fullPage: true });
      await p.context().close();
    }
  });

  /* ------------------------------------------------------------------------------------------------ f16-c */
  scenario('f16-c', 'F16 fa_IR and ar at 375 and 1280: right to left, labels on the right (English too), Persian digits, messages beside nothing, axe', async ({ context }) => {
    try {
      for (const loc of ['fa_IR', 'ar']) {
        const fa = loc === 'fa_IR';
        const L = fa ? { em: 'Email', nm: 'نام کامل', cl: 'رنگ', c1: 'قرمز', c2: 'آبی', ph: 'تلفن', ws: 'Website' } : { em: 'Email', nm: 'الاسم الكامل', cl: 'اللون', c1: 'أحمر', c2: 'أزرق', ph: 'الهاتف', ws: 'Website' };
        const F = form('F16 e2e ' + loc, FIELDS(L), F16, 2);
        const pg = ctl('page', F.form_id);
        ctl('locale', loc);
        for (const vp of [{ width: 375, height: 800 }, { width: 1280, height: 900 }]) {
          const p = await guest(context, vp, `guest-${loc}-${vp.width}`);
          await go(p, pg.url);
          await p.fill('[id="' + F.ids.em + '_"]', 'x');
          await p.press('[id="' + F.ids.em + '_"]', 'Tab');
          await sleep(400);
          await p.click('#body_efb_' + F.form_id + ' #next_efb');
          await sleep(900);
          const s = await summary(p, F.form_id);
          const digits = fa ? /^[۰-۹]/.test(s.title) : true;
          R.check(`f16-c (${loc} ${vp.width}): the summary right to left, its count in the locale's digits, three links`, s.shown && s.dir === 'rtl' && digits && s.links.length === 3, s);
          const al = await p.evaluate((f) => Array.from(document.querySelectorAll('#body_efb_' + f + ' label.col-form-label')).filter((l) => l.offsetParent).map((l) => [l.textContent.trim().slice(0, 12), getComputedStyle(l).textAlign]), String(F.form_id));
          R.check(`f16-c (${loc} ${vp.width}): every label on the right, the English "Email" too`, al.length >= 3 && al.every((x) => x[1] === 'right'), al);
          R.check(`f16-c (${loc} ${vp.width}): no message covers its label`, (await overlap(p, F.form_id)).length === 0, await overlap(p, F.form_id));
          if (vp.width === 375) R.check(`f16-c (${loc} 375): nothing off-screen`, !(await offscreen(p)), null);
          const ax = await axe(p);
          R.check(`f16-c (${loc} ${vp.width}): axe: no violation`, ax.length === 0, ax);
          await p.screenshot({ path: SHOT(`f16-c-${loc}-${vp.width}`), fullPage: vp.width === 375 });
          await p.context().close();
        }
        ctl('locale', '');
      }
    } finally { ctl('locale', ''); }
  });

  /* ------------------------------------------------------------------------------------------------ f16-d */
  scenario('f16-d', 'F16 several forms on one page (two F16 forms, one older): own regions and summaries; the server refuses a used value; sent → the heading', async ({ context }) => {
    const A = form('F16 e2e multi A', [{ key: 'em', type: 'email', name: 'Email', required: '1' }, { key: 'cd', type: 'text', name: 'Code', required: '1', unique: '1', step: 2 }], F16, 2);
    const B = form('F16 e2e multi B', [{ key: 'em', type: 'email', name: 'Email', required: '1' }], 'val_timing=blur&err_summary=1');
    const O = form('F16 e2e multi old', [{ key: 'em', type: 'email', name: 'Email', required: '1' }, { key: 'nm', type: 'text', name: 'Name', required: '1' }], '');
    const pg = c16('page', 'F16 e2e three forms', A.form_id, B.form_id, O.form_id);
    const p = await guest(context, { width: 1280, height: 900 }, 'guest-multi');
    await go(p, pg.url);
    const ids = await p.evaluate(() => { const all = Array.from(document.querySelectorAll('[id]')).map((e) => e.id); const live = Array.from(document.querySelectorAll('[data-efb-live]')).map((e) => e.id); return { live, dupLive: live.length !== new Set(live).size, sums: Array.from(document.querySelectorAll('.efb-errsum')).map((e) => e.id) }; });
    R.check('f16-d: three live regions and three summary boxes, each with its form\'s id', ids.live.length === 3 && !ids.dupLive && ids.sums.length === 3, ids);
    await p.click('#body_efb_' + B.form_id + ' #btn_send_efb');
    await sleep(800);
    const sb = await summary(p, B.form_id), sa = await summary(p, A.form_id);
    R.check('f16-d: Send on form B: B\'s summary only; A untouched', sb.shown && sb.focus && !sa.shown && (await msg(p, A.ids.em)) === '', { sb, sa });
    await p.click('#body_efb_' + O.form_id + ' #btn_send_efb');
    await sleep(800);
    const old = await p.evaluate((f) => ({ sum: !document.getElementById('efb-errsum-' + f).classList.contains('d-none'), toast: (document.querySelector('.alert_item_efb') || { getAttribute: () => null }).getAttribute('role'), act: document.activeElement.id }), String(O.form_id));
    R.check('f16-d: the older form: no summary, the toast (role alert), the focus on its first field', !old.sum && old.toast === 'alert' && old.act === O.ids.em + '_', old);
    const ax = await axe(p);
    R.check('f16-d: axe on the three forms: no violation (no id shared between them in ARIA references)', ax.length === 0, ax);
    // The server refuses a used code on form A (F03 unique): back to step 2 with the summary.
    const send = async (code) => {
      await p.fill('[id="' + A.ids.em + '_"]', 'ana@example.com');
      await p.press('[id="' + A.ids.em + '_"]', 'Tab');
      await p.click('#body_efb_' + A.form_id + ' #next_efb');
      await sleep(900);
      await p.fill('[id="' + A.ids.cd + '_"]', code);
      await p.press('[id="' + A.ids.cd + '_"]', 'Tab');
      await sleep(300);
      await p.click('#body_efb_' + A.form_id + ' #btn_send_efb, #body_efb_' + A.form_id + ' #next_efb');
      await p.waitForFunction((f) => { const fin = document.querySelector('#body_efb_' + f + ' #efb-final-step, [id="efb-final-step"][data-formid="' + f + '"]'); return (fin && !fin.classList.contains('d-none') && !/Sending|pleaseWaiting|spin/.test(fin.innerHTML)) || !document.getElementById('efb-errsum-' + f).classList.contains('d-none'); }, String(A.form_id), { timeout: 20000 }).catch(() => {});
      await sleep(1200);
    };
    await send('F16-XY-1');
    const fin = await p.evaluate(() => { const a = document.activeElement; return { tag: a.tagName, inFinal: !!a.closest('#efb-final-step'), body: a === document.body }; });
    R.check('f16-d: sent: the focus on the thank-you heading (never <body>)', fin.inFinal && /^H\d$/.test(fin.tag), fin);
    await p.goto(pg.url, { waitUntil: 'load' });
    await sleep(1200);
    await send('F16-XY-1');
    const ref = await p.evaluate((f) => ({ step: document.getElementById('body_efb_' + f).dataset.currentstep, fin: (document.querySelector('[id="efb-final-step"][data-formid="' + f + '"]') || { classList: { contains: () => true } }).classList.contains('d-none') }), String(A.form_id));
    const sr = await summary(p, A.form_id);
    const cd = await field(p, A.ids.cd);
    R.check('f16-d: the same code again: the server refuses it; back on step 2 (the field\'s), the final step hidden', ref.step === '2' && ref.fin, ref);
    R.check('f16-d: the field says why (aria-invalid) and the summary has that one line with the focus', cd.invalid === 'true' && sr.shown && sr.focus && sr.links.length === 1 && sr.links[0].indexOf('Code') === 0, { cd, sr });
    await p.screenshot({ path: SHOT('f16-d-server-refusal-1280'), fullPage: true });
    await p.context().close();
  });

  /* ------------------------------------------------------------------------------------------------ f16-e */
  scenario('f16-e', 'F16 a repeater group over three steps: an error in a row is a summary link to that row\'s field; the group\'s help text aligned; ar 375', async ({ context }) => {
    try {
      ctl('f09-mu');
      ctl('set-option', 'efb_e2e_f09_plan', 'open');
      const F = form('F16 e2e repeater', [
        { key: 'nm', type: 'text', name: 'Name', required: '1' },
        { key: 'g', type: 'repeater', name: 'المسافرون', message: 'صف لكل مسافر (Email لازم)', min_rows: '1', max_rows: '3', default_rows: '1', step: 2, label_align: 'txt-right' },
        { key: 'ge', type: 'email', name: 'Email', required: '1', group: '@@g@@', step: 2 },
        { key: 'nt', type: 'text', name: 'Note', required: '0', step: 3 },
      ], F16, 3);
      const pg = ctl('page', F.form_id);
      ctl('locale', 'ar');
      const p = await guest(context, { width: 375, height: 800 }, 'guest-rpt');
      await go(p, pg.url);
      await p.fill('[id="' + F.ids.nm + '_"]', 'سارا');
      await p.click('#body_efb_' + F.form_id + ' #next_efb');
      await sleep(1000);
      await p.click('#body_efb_' + F.form_id + ' #next_efb');
      await sleep(900);
      const s = await summary(p, F.form_id);
      R.check('f16-e: step 2: the required e-mail of row 1 is a summary line', s.shown && s.focus && s.links.length === 1 && s.links[0].indexOf('Email') !== -1, s);
      await p.click('#efb-errsum-' + F.form_id + ' a[data-efb-field]');
      await sleep(400);
      const act = await p.evaluate(() => ({ id: document.activeElement.id, type: document.activeElement.type, row: !!document.activeElement.closest('.efb-rpt-rows') }));
      R.check('f16-e: the link focuses the e-mail inside the row', act.type === 'email' && act.row, act);
      const ind = await p.evaluate((g) => { const leg = document.getElementById(g + '_labG'), des = document.getElementById(g + '-des'); if (!leg || !des) return null; const a = leg.getBoundingClientRect(), b = des.getBoundingClientRect(); return { legR: Math.round(a.right), desR: Math.round(b.right), txt: des.textContent.slice(0, 10) }; }, F.ids.g);
      R.check('f16-e: ar 375: the group\'s help text starts where its legend starts (no indent)', ind && Math.abs(ind.legR - ind.desR) <= 2, ind);
      const ax = await axe(p);
      R.check('f16-e: axe with the repeater and the summary: no violation', ax.length === 0, ax);
      await p.screenshot({ path: SHOT('f16-e-repeater-ar-375'), fullPage: true });
      await p.context().close();
    } finally { ctl('locale', ''); }
  });

  /* ------------------------------------------------------------------------------------------------ f16-f */
  scenario('f16-f', 'F16 the add-ons\' fields (calculation, typed signature, map) with the add-ons on and off', async ({ context }) => {
    const F = form('F16 e2e add-ons', [
      { key: 'q', type: 'number', name: 'Divide by', required: '0' },
      { key: 'tot', type: 'calc', name: 'Result', formula: '100 / {@@q@@}' },
      { key: 'sg', type: 'esign', name: 'Signature', esign_mode: 'both', button_single_text: 'Clear', icon: 'bi-x-lg', icon_color: 'default', button_color: 'btn-secondary' },
      { key: 'mp', type: 'maps', name: 'Where', mark: '1', lat: '35.7', lng: '51.4', zoom: '8' },
    ], F16);
    const pg = ctl('page', F.form_id);
    try {
      for (const on of [true, false]) {
        ctl('addon-flags', 'AdnCLC,AdnTSG,AdnADR', on ? '1' : '0');
        const p = await guest(context, { width: 1280, height: 900 }, 'guest-addons-' + (on ? 'on' : 'off'));
        await go(p, pg.url);
        if (on) {
          await p.fill('[id="' + F.ids.q + '_"]', '0');
          await p.press('[id="' + F.ids.q + '_"]', 'Tab');
          await sleep(900);
          const calc = await p.evaluate((id) => { const o = document.getElementById(id + '_out'), m = document.getElementById(id + '_-message'); return o && m ? { inv: o.getAttribute('aria-invalid'), desc: o.getAttribute('aria-describedby'), shown: m.style.display === 'block', text: m.textContent } : null; }, F.ids.tot);
          R.check('f16-f (on): a result that cannot be calculated: the message, the output invalid and described by it', calc && calc.shown && calc.inv === 'true' && (calc.desc || '').indexOf(F.ids.tot + '_-message') !== -1, calc);
          await p.fill('[id="' + F.ids.q + '_"]', '4');
          await p.press('[id="' + F.ids.q + '_"]', 'Tab');
          await sleep(900);
          const ok = await p.evaluate((id) => ({ inv: document.getElementById(id + '_out').getAttribute('aria-invalid'), shown: document.getElementById(id + '_-message').style.display === 'block' }), F.ids.tot);
          R.check('f16-f (on): calculated again: cleared', ok.inv === null && !ok.shown, ok);
          const sig = await p.evaluate((id) => { const t = document.getElementById(id + '_sigtype'); return t ? t.getAttribute('autocomplete') : null; }, F.ids.sg);
          R.check('f16-f (on): the typed signature\'s name box: autocomplete="name"', sig === 'name', sig);
          const map = await p.evaluate((id) => { const m = document.getElementById(id + '-map'); return m ? { role: m.getAttribute('role'), lab: m.getAttribute('aria-labelledby') } : null; }, F.ids.mp);
          R.check('f16-f (on): the map is a group named by its label', map && map.role === 'group' && map.lab === F.ids.mp + '_labG', map);
        } else {
          const off = await p.evaluate((f) => ({ num: !!document.getElementById(f.q + '_'), send: !!document.querySelector('#body_efb_' + f.fid + ' #btn_send_efb'), live: !!document.getElementById('efb-live-' + f.fid) }), { q: F.ids.q, fid: F.form_id });
          R.check('f16-f (off): without the add-ons the form still draws (number box, Send, its live region)', off.num && off.send && off.live, off);
        }
        const ax = await axe(p);
        R.check(`f16-f (${on ? 'on' : 'off'}): axe: no violation`, ax.length === 0, ax);
        await p.screenshot({ path: SHOT('f16-f-addons-' + (on ? 'on' : 'off')), fullPage: true });
        await p.context().close();
      }
    } finally { ctl('addon-flags', 'AdnCLC,AdnTSG,AdnADR', '1'); }
  });

  /* ------------------------------------------------------------------------------------------------ f16-g */
  scenario('f16-g', 'F16 "Leave this page?" on the real page; the F15 versions dialog dates in fa_IR', async ({ context }) => {
    const F = form('F16 e2e leave', [{ key: 'nm', type: 'text', name: 'Name', required: '0' }], F16 + '&warn_unsaved=1');
    const pg = ctl('page', F.form_id);
    const leave = async (p) => {
      let asked = 0;
      const on = (d) => { if (d.type() === 'beforeunload') asked++; }; // the console guard (lib.js) answers it: the page is left
      p.on('dialog', on);
      try { await p.goto(CFG.base + '/', { timeout: 4000 }); } catch (e) { /* stayed */ }
      await sleep(400);
      p.off('dialog', on);
      return asked;
    };
    let p = await guest(context, { width: 1280, height: 900 }, 'guest-leave');
    await go(p, pg.url);
    R.check('f16-g: opened and left without typing: not asked', (await leave(p)) === 0, null);
    await go(p, pg.url);
    await p.click('[id="' + F.ids.nm + '_"]');
    await p.keyboard.type('Sam');
    R.check('f16-g: typed and leaving: the browser asks', (await leave(p)) === 1, null);
    await go(p, pg.url);
    await p.click('[id="' + F.ids.nm + '_"]');
    await p.keyboard.type('Sam');
    await p.click('#body_efb_' + F.form_id + ' #btn_send_efb');
    await sleep(2500);
    R.check('f16-g: sent: leaving asks nothing', (await leave(p)) === 0, null);
    await p.context().close();
    // F15 versions: the date in the administrator's calendar and digits.
    c16('record', F.form_id);
    const fa = c16('revisions', F.form_id, 'fa_IR');
    const en = c16('revisions', F.form_id, 'en_US');
    R.check('f16-g: F15 versions in fa_IR: Solar Hijri with Persian digits (no Latin digit)', fa.length > 0 && /[۰-۹]/.test(fa[0].date) && !/[0-9]/.test(fa[0].date) && /(فروردین|اردیبهشت|خرداد|تیر|مرداد|شهریور|مهر|آبان|آذر|دی|بهمن|اسفند)/.test(fa[0].date), fa[0]);
    R.check('f16-g: F15 versions in en_US: the site\'s own format (Latin digits)', en.length > 0 && /[0-9]/.test(en[0].date), en[0]);
  });
};
