/**
 * The form's button label colour against its button colour in the builder (B5 hand-off to B4, PUB-01's builder part).
 *
 * BUG: the builder let an owner (or an assistant through efbBuilderApi) pick a label colour that reads 1.4:1 on the
 * button (text-labelEfb on btn-darkb); only the published page corrected it (B5: the label drawn white or near-black).
 * Now, with the page's one formula (efb_btn_text_class_efb(btn, text, true), new-efb.js):
 *  - the Buttons panel says so under its colour controls (role status, the ratio in <bdi>, the colour visitors see),
 *    and the note goes when the pair reads (>= 4.5:1);
 *  - efbBuilderApi.setForm() and setFormSettings() refuse a new pair under 4.5:1 with code low_contrast (ratio, fix,
 *    path) and change nothing; a pair the form already has still round-trips (getForm() → setForm());
 *  - the two-argument efb_btn_text_class_efb() answers as before (B5's page code).
 *
 * Real builder scripts in Chromium (tests/ap-builder-harness.js). Run: node tests/test-builder-button-contrast-browser.js
 */
'use strict';
const { openBuilder, makeChecker, blankForm } = require('./ap-builder-harness.js');
const c = makeChecker();

(async () => {
  const text = {
    btnContrastWhite: 'Low %s: white.', btnContrastDark: 'Low %s: near-black.', btnContrastNone: 'Low %s: choose.',
  };
  const h = await openBuilder({ builder: true, pro: 1, packageType: 1, form: blankForm({ button_color: 'btn-primary', el_text_color: 'text-white' }), text });
  const { page } = h;
  try {
    // The formula: one function, two answers.
    const f = await page.evaluate(() => ({ two: efb_btn_text_class_efb('btn-darkb', 'text-labelEfb'), three: efb_btn_text_class_efb('btn-darkb', 'text-labelEfb', true), ok: efb_btn_text_class_efb('btn-primary', 'text-white', true), unknown: efb_btn_text_class_efb('btn-nope', 'text-white', true) }));
    c.check('efb_btn_text_class_efb(): two arguments answer the class as before; a third (true) answers {cls, ratio, ok}', f.two === 'text-white' && f.three.cls === 'text-white' && f.three.ok === false && f.three.ratio > 1 && f.three.ratio < 2 && f.ok.ok === true && f.ok.ratio >= 4.5 && f.unknown.ok === true && f.unknown.ratio === null, f);

    // The Buttons panel (the gear of the canvas' buttons): the note under the colour controls.
    await page.evaluate(() => { if (typeof editFormEfb === 'function') editFormEfb(); });
    await page.waitForSelector('[data-id="button_group"]', { state: 'attached', timeout: 10000 });
    await page.evaluate(() => show_setting_window_efb('button_group'));
    await page.waitForSelector('#sideMenuConEfb #btnColorEl[data-id^="button_group"]', { state: 'attached', timeout: 8000 });
    await page.waitForTimeout(400);
    const note0 = await page.evaluate(() => { const n = document.getElementById('efbBtnContrastNote'); return n ? { hidden: n.hidden, text: n.textContent } : null; });
    c.check('Buttons panel: a pair that reads (white on primary): no warning shown', note0 === null || note0.hidden === true, note0);
    // The owner picks the dark blue button (#202a8d → btn-darkb) with the field label colour (#657096 → text-labelEfb).
    await page.evaluate(() => {
      const t = document.querySelector('#sideMenuConEfb #selectColorEl[data-el="el"][data-id^="button_group"]');
      t.value = '#898aa9'; t.dispatchEvent(new Event('change', { bubbles: true }));
    });
    await page.waitForTimeout(500);
    await page.evaluate(() => { const b = document.querySelector('#sideMenuConEfb #btnColorEl[data-id^="button_group"]'); b.value = '#202a8d'; b.dispatchEvent(new Event('change', { bubbles: true })); });
    await page.waitForTimeout(700);
    const note1 = await page.evaluate(() => { const n = document.getElementById('efbBtnContrastNote'); const b = document.querySelector('#sideMenuConEfb #btnColorEl[data-id^="button_group"]'); return { row: { btn: valj_efb[0].button_color, txt: valj_efb[0].el_text_color }, n: n ? { hidden: n.hidden, html: n.innerHTML, role: n.getAttribute('role'), live: n.getAttribute('aria-live'), fix: n.getAttribute('data-efb-contrast'), after: n.previousElementSibling === b, desc: b.getAttribute('aria-describedby') } : null }; });
    c.check('Buttons panel: a label colour under 4.5:1 on the button → a note under the button colour (role status, polite, described by), with the ratio in <bdi> and the colour visitors see (white)', note1.row.btn === 'btn-darkb' && note1.n && note1.n.hidden === false && note1.n.role === 'status' && note1.n.live === 'polite' && note1.n.after && note1.n.desc === 'efbBtnContrastNote' && /^Low <bdi dir="ltr">\d\.\d:1<\/bdi>: white\.$/.test(note1.n.html) && note1.n.fix === 'text-white', note1);
    await page.evaluate(() => { const t = document.querySelector('#sideMenuConEfb #selectColorEl[data-el="el"][data-id^="button_group"]'); t.value = '#ffffff'; t.dispatchEvent(new Event('change', { bubbles: true })); });
    await page.waitForTimeout(700);
    const note2 = await page.evaluate(() => ({ txt: valj_efb[0].el_text_color, hidden: document.getElementById('efbBtnContrastNote').hidden }));
    c.check('Buttons panel: a white label on it reads: the note goes', note2.txt === 'text-white' && note2.hidden === true, note2);

    // efbBuilderApi: low_contrast for a new pair, nothing changed.
    const api = await page.evaluate(() => {
      const out = {};
      const before = JSON.stringify(valj_efb);
      const f = efbBuilderApi.getForm();
      f[0].el_text_color = 'text-labelEfb';
      try { efbBuilderApi.setForm(f, { render: false }); out.set = 'accepted'; } catch (e) { out.set = { code: e.code, ratio: e.ratio, fix: e.fix, path: e.path, msg: e.message }; }
      out.unchanged = JSON.stringify(valj_efb) === before;
      try { efbBuilderApi.setFormSettings({ el_text_color: 'text-darkb' }, { refresh: false }); out.settings = 'accepted'; } catch (e) { out.settings = { code: e.code, fix: e.fix }; }
      out.unchanged2 = JSON.stringify(valj_efb) === before;
      try { out.ok = efbBuilderApi.setFormSettings({ button_color: 'btn-primary' }, { refresh: false }).changed; } catch (e) { out.ok = e.code; }
      return out;
    });
    c.check('efbBuilderApi.setForm(): a new label / button pair under 4.5:1 → low_contrast (ratio, the class that reads, the path), nothing changed', api.set && api.set.code === 'low_contrast' && api.set.ratio < 4.5 && api.set.fix === 'text-white' && api.set.path === 'rows[0].el_text_color' && /4\.5:1/.test(api.set.msg) && api.unchanged, api);
    c.check('efbBuilderApi.setFormSettings(): the same refusal for el_text_color; a pair that reads is set', api.settings && api.settings.code === 'low_contrast' && api.unchanged2 && Array.isArray(api.ok) && api.ok[0] === 'button_color', api);

    // A form that already has a poor pair (made before 4.3.2) still round-trips.
    const rt = await page.evaluate(() => {
      valj_efb[0].button_color = 'btn-darkb'; valj_efb[0].el_text_color = 'text-labelEfb';
      const f = efbBuilderApi.getForm();
      f[0].formName = 'Renamed';
      try { efbBuilderApi.setForm(f, { render: false }); return { ok: true, name: valj_efb[0].formName }; } catch (e) { return { ok: false, code: e.code }; }
    });
    c.check('efbBuilderApi.setForm(): a pair the form already has is kept (an older form round-trips through getForm() / setForm())', rt.ok === true && rt.name === 'Renamed', rt);
    c.check('no page errors', h.errors.length === 0 && h.bootErrors.length === 0, h.errors.concat(h.bootErrors));
  } catch (e) {
    c.check('ran without exception', false, String(e && e.stack || e));
  } finally {
    await h.browser.close();
  }
  c.done();
})();
