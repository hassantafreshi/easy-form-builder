/**
 * Batch B6 of the 4.3.2 backlog (Plugin Check hygiene and the hand-offs) on the real site, in Chromium, as the owner.
 *
 *  b6-ui     The builder's field settings panel: the yellow Pro gem after "Show this field to" sits beside the label's
 *            words, at the label's logical end, without covering them (it was absolutely placed over the text), in
 *            fa_IR and en at 1280 px (both directions). Settings › Privacy at 375 px (fa_IR): the descriptions under
 *            the IP choice, the retention days and "Delete all data when the plugin is deleted" are one size, the
 *            size of the other settings descriptions (text-muted fs-7); no sideways scroll.
 *  b6-local  With the release's bundled files in place (bin/fetch-public-assets.sh): the Panel loads the plugin's own
 *            Chart.js for the entry charts (no www.gstatic.com/charts) and the Create screen and the Panel read the
 *            country list (js/wp/countries.js) and the province lists from the plugin (efb_var.zone_area), not from
 *            cdn.jsdelivr.net; no request leaves the site.
 */
'use strict';
const fs = require('fs');
const os = require('os');
const path = require('path');
const { execFileSync } = require('child_process');

module.exports = function (S) {
  const { scenario, R, ctl, CFG, sleep, SHOT, builderReady, canvasDrawn, closePanel } = S;
  const ROOT = path.join(__dirname, '..', '..');

  async function openForm(a, fid) {
    await a.goto(CFG.base + '/wp-admin/admin.php?page=Emsfb', { waitUntil: 'load' });
    await a.waitForFunction(() => typeof fun_get_form_by_id === 'function', null, { timeout: 20000 });
    await a.evaluate((id) => { const b = document.querySelector(`.efb-act-edit[data-id="${id}"]`); if (b) b.click(); else fun_get_form_by_id(Number(id)); }, fid);
    await builderReady(a);
    await canvasDrawn(a);
  }
  async function openPanel(a, row) {
    await closePanel(a).catch(() => {});
    await a.evaluate((d) => { const e = document.querySelector(`#dropZoneEFB [data-id="${d}"]`); if (e) e.scrollIntoView({ block: 'center' }); }, row + '-id');
    await a.click(`#dropZoneEFB [data-id="${row}-id"]`, { position: { x: 20, y: 10 } });
    await a.waitForFunction(() => document.getElementById('sideBoxEfb').classList.contains('show'), null, { timeout: 8000 });
    await sleep(700);
  }

  /* ===================================================================== b6-ui */
  scenario('b6-ui', 'B6: the Pro gem beside "Show this field to" (fa_IR and en at 1280 px, fa_IR and ar at 375 px); the Privacy descriptions in one size (fa_IR and ar at 375, en at 1280)', async ({ adminPage }) => {
    const plan0 = String(ctl('state').emsfb_pro ?? '2');
    ctl('plan', '3');
    const L = ctl('f11-form', 'B6 gem form', '', '1');
    R.check('b6-ui: a form to edit', L.form_id > 0 && !!L.email, L);
    try {
      for (const run of [{ loc: 'fa_IR', rtl: true, w: 1280 }, { loc: '', rtl: false, w: 1280 }, { loc: 'fa_IR', rtl: true, w: 375 }, { loc: 'ar', rtl: true, w: 375 }]) {
        const tag = (run.loc || 'en') + ' ' + run.w;
        ctl('locale', run.loc);
        const a = await adminPage({ viewport: { width: run.w, height: run.w > 400 ? 900 : 812 }, locale: run.loc ? run.loc.replace('_', '-') : 'en-US' }, 'b6-ui-' + (run.loc || 'en') + run.w);
        await openForm(a, L.form_id);
        await openPanel(a, L.email);
        await a.evaluate(() => { const l = document.querySelector('label[for="visModeEl"]'); if (l) l.scrollIntoView({ block: 'center' }); });
        await sleep(300);
        const g = await a.evaluate(() => {
          const l = document.querySelector('label[for="visModeEl"]');
          if (!l) return null;
          const gem = l.querySelector('.pro-version-efb');
          const rg = document.createRange();
          const texts = [...l.childNodes].filter((n) => n.nodeType === 3 && n.textContent.trim() !== '');
          if (!texts.length) return { noText: true };
          rg.setStart(texts[0], 0);
          rg.setEnd(texts[texts.length - 1], texts[texts.length - 1].length);
          const t = rg.getBoundingClientRect();
          const r = gem ? gem.getBoundingClientRect() : null;
          const box = document.getElementById('sideBoxEfb').getBoundingClientRect();
          const ov = r ? Math.max(0, Math.min(t.right, r.right) - Math.max(t.left, r.left)) * Math.max(0, Math.min(t.bottom, r.bottom) - Math.max(t.top, r.top)) : -1;
          return { gem: !!gem, pos: gem ? getComputedStyle(gem).position : '', text: [Math.round(t.left), Math.round(t.right), Math.round(t.top), Math.round(t.bottom)], g: r ? [Math.round(r.left), Math.round(r.right), Math.round(r.top), Math.round(r.bottom)] : null, overlap: Math.round(ov), inBox: r ? r.left >= box.left - 1 && r.right <= box.right + 1 : false };
        });
        R.check(`b6-ui (${tag}): "Show this field to" carries the Pro gem on this plan`, !!(g && g.gem), g);
        if (g && g.gem) {
          R.check(`b6-ui (${tag}): the gem covers none of the label's words (overlap ${g.overlap} px², was the gem over the text)`, g.overlap === 0 && g.pos === 'static', g);
          R.check(`b6-ui (${tag}): the gem sits at the label's ${run.rtl ? 'left (logical end, RTL)' : 'right (logical end, LTR)'} on the same line, inside the panel`, (run.rtl ? g.g[1] <= g.text[0] + 1 : g.g[0] >= g.text[1] - 1) && g.g[2] < g.text[3] && g.g[3] > g.text[2] && g.inBox, g);
        }
        await a.locator('label[for="visModeEl"]').screenshot({ path: SHOT('b6-gem-' + (run.loc || 'en') + '-' + run.w) }).catch(() => {});
        await a.screenshot({ path: SHOT('b6-panel-' + (run.loc || 'en') + '-' + run.w) });
      }

      // Settings › Privacy: fa_IR and ar at 375 px, en at 1280 px.
      for (const run of [{ loc: 'fa_IR', w: 375 }, { loc: 'ar', w: 375 }, { loc: '', w: 1280 }]) {
      const tag = (run.loc || 'en') + ' ' + run.w;
      ctl('locale', run.loc);
      const p = await adminPage({ viewport: { width: run.w, height: 800 }, locale: run.loc ? run.loc.replace('_', '-') : 'en-US' }, 'b6-privacy-' + tag);
      await p.goto(CFG.base + '/wp-admin/admin.php?page=Emsfb&state=setting&tab=privacy', { waitUntil: 'load' });
      await p.waitForSelector('#prvDeleteOnUninstall_efb', { timeout: 30000 });
      await p.evaluate(() => { const t = document.getElementById('nav-privacy-tab'); if (t) t.click(); });
      await sleep(600);
      const f = await p.evaluate(() => {
        const px = (sel) => { const e = document.querySelector(sel); return e ? parseFloat(getComputedStyle(e).fontSize) : null; };
        return { help: ['#prvIpHelp_efb', '#prvDaysHelp_efb', '#prvUninstallHelp_efb'].map(px), other: px('#nav-general p.text-muted.fs-7') || px('#nav-email p.text-muted.fs-7'), scroll: document.documentElement.scrollWidth <= window.innerWidth + 1 };
      });
      R.check(`b6-ui (${tag}): the three Privacy descriptions are one size (the uninstall one was larger)`, f.help.every((x) => x !== null && x === f.help[0]), f);
      R.check(`b6-ui (${tag}): …the size of the other settings descriptions (text-muted fs-7), not the 16 px of small`, f.other !== null && f.help[2] === f.other && f.help[2] < 16, f);
      R.check(`b6-ui (${tag}): no sideways scroll`, f.scroll, f);
      await p.evaluate(() => document.getElementById('prvUninstallSet_efb').scrollIntoView({ block: 'center' }));
      await sleep(200);
      await p.screenshot({ path: SHOT('b6-privacy-' + (run.loc || 'en') + '-' + run.w) });
      }
    } finally {
      ctl('locale', '');
      ctl('plan', plan0);
    }
  });

  /* ================================================================== b6-local */
  scenario('b6-local', 'B6: the Panel\'s charts (Chart.js) and the builder\'s country list come from the plugin, not from Google or a CDN', async ({ adminPage }) => {
    const copied = [];
    try {
      let src = process.env.EFB_B6_ASSETS || process.env.EFB_B5_ASSETS || '';
      if (!src || !fs.existsSync(path.join(src, 'public/assets/json/geo/js/wp/countries.js'))) {
        src = fs.mkdtempSync(path.join(os.tmpdir(), 'efb-b6-assets-'));
        execFileSync('bash', [path.join(ROOT, 'bin/fetch-public-assets.sh'), src], { stdio: 'ignore', timeout: 600000 });
      }
      for (const rel of ['public/assets/json/geo', 'public/assets/js/vendor']) {
        const to = path.join(ROOT, rel);
        if (fs.existsSync(to)) continue; // a copy already there is the site's own
        fs.cpSync(path.join(src, rel), to, { recursive: true });
        copied.push(to);
      }
      const OUT = /www\.gstatic\.com|cdn\.jsdelivr\.net|cdn\.easyformbuilder\.ir|unpkg\.com/;
      for (const [label, url] of [['Panel', '/wp-admin/admin.php?page=Emsfb'], ['Create', '/wp-admin/admin.php?page=Emsfb_create']]) {
        const a = await adminPage({ viewport: { width: 1280, height: 900 } }, 'b6-local-' + label);
        const reqs = [];
        a.on('request', (r) => reqs.push(r.url()));
        await a.goto(CFG.base + url, { waitUntil: 'load' });
        await a.waitForFunction(() => typeof efb_var === 'object', null, { timeout: 20000 });
        await sleep(1500);
        const s = await a.evaluate(() => ({ zone: efb_var.zone_area || (window.ajax_object_efm && ajax_object_efm.zone_area) || '', countries: typeof counstries_list_efb === 'object' && Array.isArray(counstries_list_efb) ? counstries_list_efb.length : 0, chart: typeof Chart === 'function', scripts: [...document.scripts].map((x) => x.src).filter(Boolean) }));
        R.check(`b6-local (${label}): the province lists are the plugin's copy (efb_var.zone_area)`, s.zone.indexOf(CFG.base) === 0 && /\/public\/assets\/json\/geo\/$/.test(s.zone), s.zone);
        R.check(`b6-local (${label}): the country list is the plugin's js/wp/countries.js and is loaded`, s.countries > 200 && s.scripts.some((u) => u.indexOf(CFG.base) === 0 && /json\/geo\/js\/wp\/countries\.js/.test(u)), { n: s.countries });
        if (label === 'Panel') R.check('b6-local (Panel): the entry charts use the plugin\'s Chart.js (no Google Charts)', s.chart && s.scripts.some((u) => /public\/assets\/js\/vendor\/chart\.umd\.min\.js/.test(u)) && !s.scripts.some((u) => /gstatic\.com\/charts/.test(u)), s.scripts.filter((u) => /chart/i.test(u)));
        R.check(`b6-local (${label}): no request to Google Charts or a CDN`, !reqs.some((u) => OUT.test(u)), reqs.filter((u) => OUT.test(u)).slice(0, 4));
      }
    } finally {
      for (const to of copied) fs.rmSync(to, { recursive: true, force: true });
    }
  });
};
