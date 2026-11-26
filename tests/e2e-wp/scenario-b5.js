/**
 * Batch B5 (the public runtime) on the real site, in Chromium, as a visitor.
 *
 *  b5-a  PUB-02 + PUB-01 + PERF-01: a form whose Conditional Logic rules were written without the builder (no
 *        `logic:"1"` flag on row 0) and an old colour pair (text-labelEfb on btn-darkb):
 *         - the page loads the logic runtime and runs it: "x" in Trigger hides the second field, another value shows it;
 *         - the Send label is drawn white on the dark button (4.5:1 against both ends of its gradient);
 *         - no response viewer, one ajax_object_efm, the form's JSON once (ajax_value "@vf");
 *         - en at 1280, fa_IR and ar at 375 px (screenshots b5-a-*), right to left, no horizontal scroll.
 *  b5-b  SEC-02 with the release's bundled files in place (bin/fetch-public-assets.sh, copied in for the scenario and
 *        removed after): the code finder with the Vazirmatn response font: the font from the plugin (its font.css and
 *        woff2), no request to a font CDN, the response viewer loaded; the province list address on the form page is the
 *        plugin's copy and answers; fa_IR at 375 px (screenshot b5-b-finder-fa_IR-375).
 * Nothing on the page asks a third-party host (fonts.googleapis.com, fonts.gstatic.com, cdn.jsdelivr.net).
 *
 * Needs the bundled files of bin/fetch-public-assets.sh in EFB_B5_ASSETS (a folder holding public/assets/…) or in the
 * checkout itself, else b5-b fetches them into a temporary folder first (network).
 */
'use strict';
const path = require('path');
const fs = require('fs');
const os = require('os');
const { execFileSync } = require('child_process');

module.exports = function (S) {
  const { scenario, R, ctl, sleep, SHOT } = S;
  const ROOT = path.join(__dirname, '..', '..');
  const c5 = (...a) => { const out = S.wp('eval-file', path.join(__dirname, 'ctl-b5.php'), ...a.map(String)).trim().split('\n'); return JSON.parse(out[out.length - 1]); };
  const vp375 = (loc) => ({ viewport: { width: 375, height: 812 }, locale: loc.replace('_', '-'), isMobile: true, hasTouch: true });
  const THIRD = /fonts\.googleapis\.com|fonts\.gstatic\.com|cdn\.jsdelivr\.net/;

  const field = (id, name, amount, extra) => Object.assign({ id_: id, dataId: id + '-id', type: 'text', name, step: '1', amount: String(amount), required: '0', value: '', message: '', classes: '', size: '100',
    label_text_color: 'text-labelEfb', el_text_color: 'text-labelEfb', el_border_color: 'border-d', el_height: 'h-d-efb', label_text_size: 'fs-6', label_position: 'up', label_align: 'txt-left', message_align: 'justify-content-start', el_align: 'justify-content-start', icon: '', icon_color: '' }, extra || {});
  const logicForm = () => [
    { type: 'form', steps: 1, formName: 'B5 logic e2e', email: '', trackingCode: '1', EfbVersion: 2, sendEmail: '0', captcha: '', stateForm: 0, thank_you: 'msg', show_icon: '1', show_pro_bar: '1',
      button_single_text: 'Send', button_Next_text: 'Next', button_Previous_text: 'Previous', button_Next_icon: 'bi-chevron-right', button_Previous_icon: 'bi-chevron-left', icon: 'bi-send',
      button_color: 'btn-darkb', el_text_color: 'text-labelEfb', icon_color: 'text-white', label_text_color: 'text-darkb', message_text_color: 'text-muted', el_height: 'h-d-efb', btns_align: 'justify-content-center', corner: 'efb-square',
      thank_you_message: { icon: 'bi-hand-thumbs-up', thankYou: 'Thank you', done: 'Done', trackingCode: 'Confirmation Code', pleaseFillInRequiredFields: 'Please fill in all required fields.' },
      // PUB-02: rules without the builder's logic switch (no "logic" key).
      logic_rules: [{ id: 'hide_b', name: 'x hides B', scope: 'field', enabled: true, priority: 10, stop_processing: false,
        conditions: { type: 'group', operator: 'AND', negate: false, items: [{ type: 'condition', source: 'field', field_id: 'b5a', compare: 'is', value: 'x' }] },
        actions: [{ type: 'hide_field', target: 'b5b' }] }] },
    { id_: '1', type: 'step', step: '1', name: 'Step 1', amount: 1, icon: 'bi-ui-checks-grid', label_text_color: 'text-darkb', message: '', icon_color: '' },
    field('b5a', 'Trigger', 2),
    field('b5b', 'Shown unless x', 3),
  ];
  const b64 = (o) => Buffer.from(JSON.stringify(o)).toString('base64');

  /* What a page asked for (scripts, styles, fonts, fetches) and its facts. */
  async function open(ctx, url) {
    const p = await ctx.newPage();
    p.reqs = [];
    p.on('request', (r) => p.reqs.push(r.url()));
    await p.goto(url, { waitUntil: 'networkidle' });
    return p;
  }
  const facts = (p, fid) => p.evaluate((fid) => {
    const sb = document.querySelector('#body_efb_' + fid + ' #button_group_button_single_text') || document.querySelector('#button_group_button_single_text');
    const btn = sb ? sb.closest('a,button') : null;
    const scripts = Array.from(document.scripts).map((s) => s.textContent);
    const vf = scripts.filter((t) => t.indexOf('var ajax_object_efm = ') !== -1);
    const b = document.querySelector('[data-vid="b5b"]') || document.getElementById('b5b_');
    return {
      label: sb ? getComputedStyle(sb).color : null, labelText: sb ? sb.textContent.trim() : '', btnBg: btn ? getComputedStyle(btn).backgroundColor : null, btnClass: btn ? btn.className : '',
      logicJs: !!Array.from(document.scripts).find((s) => /conditional-logic-efb\.js/.test(s.src)),
      viewerJs: !!Array.from(document.scripts).find((s) => /response-viewer-efb(\.min)?\.js/.test(s.src)),
      ajaxObjects: (scripts.join('\n').match(/var ajax_object_efm = /g) || []).length,
      formJsonCount: (vf.join('\n').match(/Shown unless x/g) || []).length,
      // As printed (core-efb.js puts the form's JSON back in ajax_value once it runs).
      ajaxValue: vf.join('\n').indexOf('"ajax_value":"@vf"') !== -1 ? '@vf' : 'other',
      ajaxValueRestored: typeof ajax_object_efm === 'object' && typeof ajax_object_efm.ajax_value === 'string' && ajax_object_efm.ajax_value.indexOf('Shown unless x') !== -1,
      bVisible: !!(b && b.offsetParent !== null),
      dir: getComputedStyle(document.body).direction, scroll: document.documentElement.scrollWidth - document.documentElement.clientWidth,
    };
  }, fid);
  const lum = (rgb) => { const m = String(rgb).match(/\d+(\.\d+)?/g); if (!m) return null; return m.slice(0, 3).map(Number).map((v) => { v /= 255; return v <= 0.03928 ? v / 12.92 : Math.pow((v + 0.055) / 1.055, 2.4); }).reduce((s, v, i) => s + v * [0.2126, 0.7152, 0.0722][i], 0); };
  const hexLum = (h) => lum('rgb(' + [1, 3, 5].map((i) => parseInt(h.substr(i, 2), 16)).join(',') + ')');
  const ratio = (a, b) => (Math.max(a, b) + 0.05) / (Math.min(a, b) + 0.05);

  scenario('b5', 'B5 public runtime: logic rules without the flag run (PUB-02), the Send label reads on its button (PUB-01), no response viewer and the form JSON once (PERF-01), fonts and lists from the plugin, no CDN (SEC-02); en 1280, fa_IR / ar 375', async ({ context }) => {
    const made = { forms: [], pages: [] };
    const priorFont = (() => { try { return ctl('setting', 'respFontFamily'); } catch (e) { return null; } })();
    let copied = [];
    try {
      ctl('plan', '3');
      ctl('addon-flag', 'AdnSMF', '1');
      const fid = c5('form', 'B5 logic e2e', b64(logicForm())).form_id;
      made.forms.push(fid);
      const page = ctl('page', String(fid));
      made.pages.push(page.id);

      /* b5-a */
      for (const loc of ['', 'fa_IR', 'ar']) {
        ctl('locale', loc);
        const ctx = await context(loc ? vp375(loc) : {}, 'b5-a-' + (loc || 'en'));
        const p = await open(ctx, page.url);
        await p.waitForSelector('#body_efb_' + fid, { timeout: 20000 });
        await sleep(600);
        let f = await facts(p, fid);
        const tag = loc ? `${loc} 375` : 'en 1280';
        R.check(`b5-a (${tag}): the logic runtime is on the page although row 0 has no logic flag (PUB-02)`, f.logicJs, f);
        const white = f.label === 'rgb(255, 255, 255)';
        const worst = Math.min(ratio(lum(f.label), hexLum('#202a8d')), ratio(lum(f.label), hexLum('#4757e7')));
        R.check(`b5-a (${tag}): the Send label is white on the dark button, ${worst.toFixed(1)}:1 against both ends of its gradient (PUB-01)`, white && worst >= 4.5 && /btn-darkb/.test(f.btnClass), { label: f.label, bg: f.btnBg });
        R.check(`b5-a (${tag}): no response viewer on a form page; one ajax_object_efm; the form JSON once (PERF-01)`, !f.viewerJs && f.ajaxObjects === 1 && f.formJsonCount === 1 && f.ajaxValue === '@vf' && f.ajaxValueRestored, f);
        if (loc) R.check(`b5-a (${tag}): right to left, no horizontal scroll`, f.dir === 'rtl' && f.scroll <= 1, f);
        await p.screenshot({ path: SHOT(`b5-a-form-${loc || 'en'}-${loc ? 375 : 1280}`), fullPage: true });
        if (!loc) {
          await p.fill('[data-vid="b5a"]', 'x');
          await p.dispatchEvent('[data-vid="b5a"]', 'change');
          await sleep(900);
          const hidden = !(await facts(p, fid)).bVisible;
          await p.fill('[data-vid="b5a"]', 'y');
          await p.dispatchEvent('[data-vid="b5a"]', 'change');
          await sleep(900);
          const shown = (await facts(p, fid)).bVisible;
          R.check('b5-a (en): "x" in Trigger hides the second field, "y" shows it again (the rules run in the browser)', hidden && shown, { hidden, shown });
          await p.locator('#btn_send_efb').screenshot({ path: SHOT('b5-a-send-en-1280') });
        } else {
          await p.locator('#btn_send_efb').screenshot({ path: SHOT(`b5-a-send-${loc}-375`) });
        }
        R.check(`b5-a (${tag}): no request to a font or script CDN`, !p.reqs.some((u) => THIRD.test(u)), p.reqs.filter((u) => THIRD.test(u)).slice(0, 4));
      }

      /* b5-b: the release's bundled files in place. */
      let src = process.env.EFB_B5_ASSETS || '';
      // The checkout already holds them when bin/fetch-public-assets.sh ran there (tests/e2e-wp/README.md's set-up): no
      // second fetch, which depends on Google Fonts answering (run 2 of the final regression failed on that fetch).
      if (!src && fs.existsSync(path.join(ROOT, 'public/assets/fonts/vazirmatn/font.css'))) src = ROOT;
      if (!src || !fs.existsSync(path.join(src, 'public/assets/fonts/vazirmatn/font.css'))) {
        src = fs.mkdtempSync(path.join(os.tmpdir(), 'efb-b5-assets-'));
        execFileSync('bash', [path.join(ROOT, 'bin/fetch-public-assets.sh'), src], { stdio: 'ignore', timeout: 600000 });
      }
      for (const rel of ['public/assets/fonts', 'public/assets/json/geo', 'public/assets/js/vendor']) {
        const to = path.join(ROOT, rel);
        if (fs.existsSync(to)) continue; // a copy already there is the site's own
        fs.cpSync(path.join(src, rel), to, { recursive: true });
        copied.push(to);
      }
      ctl('f12-setting', 'respFontFamily=' + encodeURIComponent('Vazirmatn, Tahoma, sans-serif'));
      const finder = c5('page-finder');
      made.pages.push(finder.id);
      ctl('locale', 'fa_IR');
      const ctx = await context(vp375('fa_IR'), 'b5-b');
      const p = await open(ctx, finder.url);
      await p.waitForSelector('#trackingCodeEfb', { timeout: 20000 });
      await p.evaluate(() => document.fonts && document.fonts.ready);
      await sleep(500);
      const fb = await p.evaluate(() => ({
        fontCss: Array.from(document.querySelectorAll('link[rel=stylesheet]')).map((l) => l.href).filter((h) => /\/public\/assets\/fonts\//.test(h)),
        family: getComputedStyle(document.querySelector('.efb-tracker-card') || document.body).fontFamily,
        loaded: document.fonts ? document.fonts.check('16px Vazirmatn', 'سلام') : null,
        viewer: !!Array.from(document.scripts).find((s) => /response-viewer-efb(\.min)?\.js/.test(s.src)),
        dir: getComputedStyle(document.body).direction, scroll: document.documentElement.scrollWidth - document.documentElement.clientWidth,
      }));
      const woff = p.reqs.filter((u) => /\/public\/assets\/fonts\/vazirmatn\/.+\.woff2/.test(u));
      R.check('b5-b (fa_IR 375): the code finder\'s Vazirmatn comes from the plugin (font.css, its woff2), and is in use', fb.fontCss.length === 1 && /vazirmatn\/font\.css/.test(fb.fontCss[0]) && woff.length >= 1 && fb.loaded === true && /Vazirmatn/.test(fb.family), Object.assign({ woff }, fb));
      R.check('b5-b (fa_IR 375): no request to Google Fonts or jsDelivr', !p.reqs.some((u) => THIRD.test(u)), p.reqs.filter((u) => THIRD.test(u)).slice(0, 4));
      R.check('b5-b (fa_IR 375): the code finder loads the response viewer; right to left, no horizontal scroll', fb.viewer && fb.dir === 'rtl' && fb.scroll <= 1, fb);
      await p.screenshot({ path: SHOT('b5-b-finder-fa_IR-375'), fullPage: true });
      /* The province lists: the form page reads them from the plugin. */
      const fp = await open(ctx, page.url);
      await fp.waitForSelector('#body_efb_' + fid, { timeout: 20000 });
      const geo = await fp.evaluate(async () => {
        const base = ajax_object_efm.zone_area;
        const r = await fetch(base + 'json/states/ir.json');
        const j = r.ok ? await r.json() : null;
        return { base, same: base.indexOf(location.origin) === 0, status: r.status, teh: j && j.THR ? j.THR.n : (j ? Object.keys(j).length : 0) };
      });
      R.check('b5-b: the page\'s province lists are the plugin\'s copy (zone_area on this site) and answer (Iran\'s provinces)', geo.same && /\/public\/assets\/json\/geo\/$/.test(geo.base) && geo.status === 200 && !!geo.teh, geo);
    } finally {
      try { ctl('locale', ''); } catch (e) { /* the site is down */ }
      try { ctl('f12-setting', 'respFontFamily=' + (priorFont ? encodeURIComponent(priorFont) : 'del')); } catch (e) { /* the site is down */ }
      for (const d of copied) fs.rmSync(d, { recursive: true, force: true });
      try { c5('delete', made.forms.join(','), made.pages.join(',')); } catch (e) { /* the site is down */ }
    }
  });
};
