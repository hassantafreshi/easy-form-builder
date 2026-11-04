/**
 * Browser regression test for hostile stored rows in the response viewer and
 * for the ?track= parameter of the confirmation-code finder page.
 *
 * Loads the shipped response-viewer-efb.js (plus the helpers it needs from
 * new-efb.js) into Chromium and renders rows a visitor could have stored:
 * javascript: attachment urls, a CSS-injecting color, a huge rating, a bad
 * currency, an imgRadio without src and a legacy plaintext password - and the
 * same field kinds with legitimate values, which must still render.
 *
 * Run: node tests/test-response-viewer-hostile-rows-browser.js
 *      (EFB_CHROMIUM=/path/to/chrome to use a specific Chromium build)
 */
const { chromium } = require('playwright');
const fs = require('fs');
const path = require('path');
const R = path.join(__dirname, '..', 'includes', 'admin', 'assets', 'js') + path.sep;
const newSrc = fs.readFileSync(R + 'new-efb.js', 'utf8');
const grab = n => newSrc.match(new RegExp('function ' + n + '\\([\\s\\S]*?\\n}\\n'))[0];
const sanitizeSrc = newSrc.match(/const sanitize_text_efb=\(str[\s\S]*?\n}\n/)[0];
const imgRadio = newSrc.match(/fun_imgRadio_efb=\(id ,link,row ,state=true\)=>\{[\s\S]*?\n}\n/)[0];
const legitPng = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==';
let failures = 0;
const check = (label, ok, detail) => { console.log((ok ? '[PASS] ' : '[FAIL] ') + label + (ok ? '' : ' ' + JSON.stringify(detail))); if (!ok) failures++; };
(async () => {
  const b = await chromium.launch(process.env.EFB_CHROMIUM ? { executablePath: process.env.EFB_CHROMIUM } : {});
  const ctx = await b.newContext();
  let dialogs = 0, external = [];
  const p = await ctx.newPage();
  p.on('dialog', d => { dialogs++; d.dismiss(); });
  await p.route('**/*', r => { const u = r.request().url(); if (!u.startsWith('http://site.test')) external.push(u); r.fulfill({ status: 200, contentType: 'text/html', body: '<div id=resp_efb></div><div id=body_tracker_emsFormBuilder></div>' }); });
  const errs = []; p.on('pageerror', e => errs.push(e.message));
  const go = async (q) => {
    await p.goto('http://site.test/track' + q);
    await p.addScriptTag({ content: `var efb_var={rtl:0,text:new Proxy({}, {get:(t,k)=>typeof k==='string'?k:undefined}),images:{head:'http://site.test/head.png'}};var lan_name_emsFormBuilder='en';var pro_efb=true;var poster_emsFormBuilder='';var ajax_object_efm={rtl:0,text:new Proxy({}, {get:(t,k)=>typeof k==='string'?k:undefined})};var setting_emsFormBuilder={scaptcha:false};function change_url_back_persia_pay_efb(){}` + grab('replaceContentMessageEfb') + grab('text_nr_efb') + sanitizeSrc + imgRadio });
    await p.addScriptTag({ path: R + 'response-viewer-efb.js' });
  };
  // 1) tracker page
  for (const [label, q, expectVal] of [['tracker attack', '?track=' + encodeURIComponent('ABC" autofocus onfocus="alert(document.domain)" x="'), null], ['tracker legit', '?track=EFB-2026-AB12', 'EFB-2026-AB12']]) {
    await go(q);
    const r = await p.evaluate(() => new Promise(res => { fun_tracking_show_emsFormBuilder(); setTimeout(() => { const i = document.getElementById('trackingCodeEfb'); res({ onfocus: i && i.hasAttribute('onfocus'), value: i && i.value }); }, 1200); }));
    check(label, expectVal ? r.value === expectVal && !r.onfocus : !r.onfocus, r);
  }
  // 2) viewer rows
  await go('');
  const cases = {
    'reply media javascript: url': [{ id_: 'message', type: 'media', value: '@file@', url: 'javascript:alert(document.domain)//clip.mp4', amount: 1 }],
    'reply document javascript: url': [{ id_: 'message', type: 'document', value: '@file@', url: 'javascript:alert(1)', amount: 1 }],
    'reply color css overlay': [{ id_: 'message', name: 'c', type: 'color', value: 'red;position:fixed;inset:0;z-index:99999;background:url(https://attacker.test/x.png)', amount: 1 }],
    'reply rating DoS': [{ id_: 'r', name: 'r', type: 'rating', value: '999999999', amount: 1 }],
    'bad currency + price': [{ id_: 'x', name: 'x', type: 'payRadio', value: 'a', price: '5', currency: 'NOT_A_CODE', amount: 1 }],
    'imgRadio without src': [{ id_: 'x', name: 'x', type: 'imgRadio', value: 'a', amount: 1 }],
    'legacy plaintext password': [{ id_: 'usernameRegisterEFB', name: 'Username', type: 'text', value: 'bob', amount: 1 }, { id_: 'passwordRegisterEFB', name: 'Password', type: 'password', value: 'S3cret!Pass', amount: 2 }],
    'LEGIT file/image/doc/audio/video': [
      { id_: 'f1', name: 'Photo', type: 'image', value: '@file@', url: 'http://site.test/wp-content/uploads/a.png', amount: 1 },
      { id_: 'f2', name: 'Doc', type: 'allformat', value: '@file@', url: 'https://site.test/wp-content/uploads/b.pdf', amount: 2 },
      { id_: 'f3', name: 'Song', type: 'media', value: '@file@', url: 'https://site.test/wp-content/uploads/c.mp3', amount: 3 },
      { id_: 'f4', name: 'Clip', type: 'media', value: '@file@', url: 'https://site.test/wp-content/uploads/d.mp4', amount: 4 }],
    'LEGIT color/rating/text/payment/esign': [
      { id_: 'c', name: 'Color', type: 'color', value: '#ff8800', amount: 1 },
      { id_: 'r', name: 'Stars', type: 'rating', value: '4', amount: 2 },
      { id_: 't', name: 'Msg', type: 'text', value: "Hello <b>x</b> & it's **bold** https://site.test/p @efb@nq#line2", amount: 3 },
      { id_: 'pr', name: 'Plan', type: 'payRadio', value: 'Gold', price: '9.5', currency: 'eur', amount: 4 },
      { id_: 's', name: 'Signature', type: 'esign', value: legitPng, amount: 5 }],
  };
  for (const [label, rows] of Object.entries(cases)) {
    const r = await p.evaluate(rows => {
      const box = document.getElementById('resp_efb');
      let html = '', err = null; const t0 = performance.now();
      try { html = fun_emsFormBuilder_show_messages(JSON.parse(JSON.stringify(rows)).concat([{ type: 'w_link', value: 'x' }]), 0, '1.2.3.4', 'TRK1', '2026-11-04'); } catch (e) { err = e.message; }
      box.innerHTML = html;
      const links = [...box.querySelectorAll('a[href],img[src],video[src],source[src]')].map(e => e.getAttribute('href') || e.getAttribute('src'));
      const ov = [...box.querySelectorAll('[style]')].some(e => /fixed|url\(/.test(e.getAttribute('style')));
      return { err, ms: Math.round(performance.now() - t0), jsLinks: links.filter(u => /^\s*(javascript|data:text)/i.test(u)).length, links: links.length,
        stars: box.querySelectorAll('.bi-star-fill').length, swatch: !!box.querySelector('.efb-msg-color-swatch'), overlay: ov,
        pwd: box.textContent.includes('S3cret!Pass'), esign: !!box.querySelector('img.efb-msg-esign-img'),
        bold: !!box.querySelector('.efb-formatted strong'), br: box.innerHTML.includes('<br>'), price: /9\.50|€/.test(box.textContent), rawB: !!box.querySelector('.efb-formatted b') };
    }, rows);
    const expect = {
      'reply media javascript: url': r.jsLinks === 0,
      'reply document javascript: url': r.jsLinks === 0,
      'reply color css overlay': !r.overlay && !r.swatch,
      'reply rating DoS': r.ms < 1000 && r.stars <= 10,
      'bad currency + price': r.err === null,
      'imgRadio without src': r.err === null,
      'legacy plaintext password': !r.pwd,
      'LEGIT file/image/doc/audio/video': r.links === 5 && r.jsLinks === 0,
      'LEGIT color/rating/text/payment/esign': r.swatch && r.stars === 4 && r.esign && r.bold && r.br && r.price && !r.rawB,
    }[label];
    check(label, r.err === null && expect, r);
  }
  check('no script ran', dialogs === 0, dialogs);
  check('no request left the site', external.every(u => u.startsWith('https://site.test/')), external);
  check('no page errors', errs.length === 0, errs);
  await b.close();
  console.log('\n========================================\nRESULTS: ' + (failures ? failures + ' failed' : 'all passed') + '\n========================================');
  process.exit(failures ? 1 : 0);
})();
