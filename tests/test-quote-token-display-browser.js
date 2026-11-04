/**
 * A visitor's double quote is shown as a double quote, and stays inert.
 *
 * The client stores " as the @efb@dq# token. Every reader used to turn that
 * token into '' (two apostrophes) before parsing the stored JSON, so the
 * response viewer, the list preview, the CSV and the notification email all
 * showed  ''quotes''  for  "quotes". Now the token survives parsing and is
 * decoded afterwards: to &quot; in the (already escaped) viewer, to " before
 * escaping in the list preview and the CSV, and to " in the email, whose
 * every branch escapes the value.
 *
 * Also covers the CSV export of a Signature: a short marker instead of the
 * base64 image, which could pass Excel's 32,767-character cell limit.
 *
 * Run: node tests/test-quote-token-display-browser.js
 *      (EFB_CHROMIUM=/path/to/chrome to use a specific Chromium build)
 */
'use strict';
const { chromium } = require('playwright');
const fs = require('fs');
const path = require('path');
const JS = path.join(__dirname, '..', 'includes', 'admin', 'assets', 'js');
const newEfb = fs.readFileSync(path.join(JS, 'new-efb.js'), 'utf8');
const list = fs.readFileSync(path.join(JS, 'list_form-efb.js'), 'utf8');
const lift = (src, name) => { const m = src.match(new RegExp('function ' + name + '\\([\\s\\S]*?\\n}\\n')); if (!m) throw new Error('missing ' + name); return m[0]; };
const imgRadio = newEfb.match(/fun_imgRadio_efb=\(id ,link,row ,state=true\)=>\{[\s\S]*?\n}\n/)[0];

let failures = 0;
const check = (label, ok, detail) => { console.log((ok ? '[PASS] ' : '[FAIL] ') + label + (ok || detail === undefined ? '' : ' ' + JSON.stringify(detail))); if (!ok) failures++; };

(async () => {
  const browser = await chromium.launch(process.env.EFB_CHROMIUM ? { executablePath: process.env.EFB_CHROMIUM } : {});
  const p = await browser.newPage();
  let dialogs = 0; p.on('dialog', d => { dialogs++; d.dismiss(); });
  const errors = []; p.on('pageerror', e => errors.push(e.message));
  await p.setContent('<div id="box"></div>');
  await p.addScriptTag({ content: 'var efb_var={rtl:0,images:{head:"https://site.test/h.png"},text:new Proxy({esign:"Signature"},{get:(t,k)=>typeof k==="string"?(t[k]||k):undefined})};var lan_name_emsFormBuilder="en";'
    + lift(newEfb, 'replaceContentMessageEfb') + lift(newEfb, 'text_nr_efb') + lift(newEfb, 'efbParseJson') + imgRadio
    + lift(list, 'escapeContentPreviewText_efb') + lift(list, 'getContentPreview_efb') + lift(list, 'convertToCSV_emsFormBuilder') });
  await p.addScriptTag({ path: path.join(JS, 'response-viewer-efb.js') });

  // Stored the way the server stores it: " -> @efb@dq#, then the JSON's own quotes escaped.
  const rows = [
    { id_: 't', name: 'Message', type: 'textarea', value: 'He said @efb@dq#hello@efb@dq# & it@efb@sq#s fine', amount: 1 },
    { id_: 'x', name: 'Nick', type: 'text', value: '@efb@dq# onmouseover=alert(1) x=@efb@dq#<img src=x onerror=alert(2)>', amount: 2 },
    { id_: 'r', name: 'Pick', type: 'imgRadio', value: 'A @efb@dq#quoted@efb@dq# option@efb@dq# onerror=@efb@dq#alert(3)', src: 'https://site.test/a.png', amount: 3 },
    { type: 'w_link', value: 'https://site.test/', amount: -1 },
  ];
  const stored = JSON.stringify(rows).replace(/"/g, '\\"');

  const r = await p.evaluate((stored) => {
    // The panel's own path: decode for parsing (keeping the quote token), parse, render.
    const content = efbParseJson(replaceContentMessageEfb(stored, true));
    const box = document.getElementById('box');
    box.innerHTML = fun_emsFormBuilder_show_messages(content, 0, '', 'T1', 'd');
    const text = box.innerText;
    const alts = [...box.querySelectorAll('img')].map(i => i.getAttribute('alt'));
    const handlers = [...box.querySelectorAll('*')].filter(e => [...e.attributes].some(a => /^on/i.test(a.name))).length;
    return { text, alts, handlers, injectedImg: !!box.querySelector('img[src="x"]'), preview: getContentPreview_efb(stored).full };
  }, stored);

  check('viewer shows a real double quote', r.text.includes('He said "hello" & it\'s fine'), r.text);
  check('viewer no longer shows two apostrophes', !r.text.includes("''hello''"));
  check('a quote token cannot open an attribute (no on* handler)', r.handlers === 0, r.handlers);
  check('markup stays text', r.text.includes('<img src=x onerror=alert(2)>') && !r.injectedImg);
  check('a quote inside an image choice stays inside its alt text', r.alts.some(a => a && a.includes('"quoted"')) && r.handlers === 0, r.alts);
  check('list preview shows a real double quote, escaped', r.preview.includes('He said &quot;hello&quot;'), r.preview);

  // CSV: quote token -> ", signature -> marker
  const csv = await p.evaluate(() => convertToCSV_emsFormBuilder([['id', 'Message'], ['1', 'He said @efb@dq#hello@efb@dq#']]));
  check('CSV cell carries the real quote (CSV-escaped)', csv.includes('"He said ""hello"""'), csv);
  const src = fs.readFileSync(path.join(JS, 'list_form-efb.js'), 'utf8');
  check('CSV export writes a [Signature] marker, not the base64 image', /type == 'esign'\)\{[\s\S]{0,400}'\[' \+ \(efb_var\.text\.esign \|\| 'Signature'\) \+ '\]'/.test(src));
  check('the panel parses stored rows keeping the quote token',
    (src.match(/replaceContentMessageEfb\((contentStr|v\.content), true\)/g) || []).length === 2);

  check('no script ran', dialogs === 0, dialogs);
  check('no page errors', errors.length === 0, errors);
  await browser.close();
  console.log('\n========================================\nRESULTS: ' + (failures ? failures + ' failed' : 'all passed') + '\n========================================');
  process.exit(failures ? 1 : 0);
})();
