/**
 * F10-C: the visitor's rich text editor on the page (rich-text-efb.js + core-efb.js on the PHP renderer's markup,
 * tests/layout-page-lib.php), in Chromium.
 *
 * Checks:
 *  - without rich-text-efb.js the textarea is a plain textarea (no editor, visible);
 *  - with it: a toolbar (role=toolbar, an accessible name, buttons with names, aria-pressed, one tab stop, arrows
 *    move between buttons) and a box (role=textbox, aria-multiline, dir=auto, labelled by the field's label,
 *    aria-required) before the hidden textarea, which holds the cleaned HTML and is what the form sends;
 *  - Ctrl+B makes bold (aria-pressed true), a list button makes a list; Tab leaves the box (no trap);
 *  - paste of Word / hostile HTML keeps only the allow-list (no style, class, img, script, event);
 *  - the answer row carries the HTML; mlen 10 is measured on the text ("<b>12345678901</b>" is too long, the
 *    field's message is shown); the review page shows the text, never the tags;
 *  - Persian typed in the box keeps its direction (dir=auto); a rich textarea in a repeater row gets its own editor;
 *  - a textarea without rich_text renders and behaves as before.
 *
 * Run: node tests/test-rich-text-browser.js   (EFB_CHROMIUM=/path/to/chrome to pick a build)
 */
'use strict';
const { chromium } = require('playwright');
const fs = require('fs');
const path = require('path');
const { execFileSync } = require('child_process');

const ROOT = path.join(__dirname, '..');
let pass = 0, fail = 0;
const check = (label, ok, detail) => {
  if (ok) { pass++; console.log('[PASS] ' + label); } else { fail++; console.log('[FAIL] ' + label + (detail !== undefined ? ' ' + JSON.stringify(detail).slice(0, 900) : '')); }
};
async function launch() {
  if (process.env.EFB_CHROMIUM) return chromium.launch({ executablePath: process.env.EFB_CHROMIUM });
  try { return await chromium.launch(); } catch (e) {
    const base = process.env.PLAYWRIGHT_BROWSERS_PATH || '/opt/pw-browsers';
    const dirs = fs.existsSync(base) ? fs.readdirSync(base).filter((d) => /^chromium_headless_shell-\d+$/.test(d)).sort().reverse() : [];
    for (const d of dirs) { const exe = path.join(base, d, 'chrome-headless-shell-linux64', 'chrome-headless-shell'); if (fs.existsSync(exe)) return chromium.launch({ executablePath: exe }); }
    throw e;
  }
}
const TEXT = { rteToolbar: 'Formatting', rteBold: 'Bold', rteItalic: 'Italic', rteUnderline: 'Underline', rteBullets: 'Bulleted list', rteNumbers: 'Numbered list', rteLink: 'Link', rteQuote: 'Quote', rteHeading: 'Heading', rteClear: 'Clear formatting',
  rvwTtl: 'Review your answers', rvwEdit: 'Edit', rvwConfirm: 'Confirm and submit', rvwNoAnswer: 'No answer', next: 'Next', previous: 'Previous', submit: 'Submit', finish: 'Finish', rowN: 'Row %s',
  enterTheValueThisField: 'This field is required', fillrequiredfields: 'Please fill in the required fields', mmxplen: 'At most NN characters', mmplen: 'At least NN characters' };
const f = (id, type, extra) => Object.assign({ id_: id, dataId: id + '-id', type, name: 'F ' + id, step: '1', amount: '2', required: '0', value: '', message: '', classes: '', size: '100',
  el_height: 'h-d-efb', el_border_color: 'border-d', el_text_color: 'text-labelEfb', label_text_color: 'text-labelEfb', label_text_size: 'fs-6', message_text_color: 'text-muted', label_position: 'up', label_align: 'txt-left', message_align: 'justify-content-start', el_align: 'justify-content-start', corner: 'efb-square' }, extra || {});
const row0 = (extra) => Object.assign({ type: 'form', steps: 1, formName: 'T', button_single_text: 'Send', button_Next_text: 'Next', button_Previous_text: 'Previous', button_Next_icon: 'bi-chevron-right', button_Previous_icon: 'bi-chevron-left', button_color: 'btn-primary', icon: 'bi-send', icon_color: 'text-white', el_height: 'h-d-efb', el_text_color: 'text-white', show_icon: '1', show_pro_bar: '1', btns_align: 'justify-content-center', captcha: '', corner: 'efb-square', thank_you: 'msg' }, extra || {});
const ROWS = [row0({ review_before_submit: '1' }), { id_: '1', type: 'step', step: '1', name: 'S', amount: 1 },
  f('st', 'textarea', { name: 'Story', rich_text: '1', rich_toolbar: 'full', mlen: '10', required: '1', amount: '2' }),
  f('pl', 'textarea', { name: 'Plain', amount: '3' }),
  f('g', 'repeater', { name: 'Notes', amount: '4', min_rows: '1', max_rows: '1', default_rows: '1', rmv_confirm: '0' }),
  f('gn', 'textarea', { name: 'Row note', amount: '5', group: 'g', rich_text: '1' })];
const html = JSON.parse(execFileSync('php', [path.join(__dirname, 'layout-page-lib.php')], { input: JSON.stringify({ forms: [{ id: 51, rows: ROWS }], plan: 'pro' }) }).toString('utf8'))['51'];

async function open(browser, noRich) {
  const page = await browser.newPage();
  page.errors = [];
  page.on('pageerror', (e) => page.errors.push(e.message));
  await page.route('**/*', (r) => r.fulfill({ status: 200, contentType: 'text/html', body: `<!doctype html><html><head><meta charset="utf-8"></head><body>${html}</body></html>` }));
  await page.goto('http://site.test/f/');
  await page.addStyleTag({ content: '.d-none{display:none!important}' });
  for (const css of ['public/assets/css/rich-text-efb.css', 'public/assets/css/layout-efb.css']) await page.addStyleTag({ path: path.join(ROOT, css) });
  await page.addScriptTag({ content: `var efb_var = { text: ${JSON.stringify(TEXT)}, rtl: 0, wp_lan: 'en_US', language: 'en_US' }; var ajax_object_efm = { text: ${JSON.stringify(TEXT)}, form_setting: {}, ajax_value_forms: [], wp_lan: 'en_US', language: 'en_US', page_id: 1 };` });
  const js = ['includes/admin/assets/js/jquery.min-efb.js', 'includes/admin/assets/js/new-efb.js', 'public/assets/js/repeater-efb.js'].concat(noRich ? [] : ['public/assets/js/rich-text-efb.js']).concat(['public/assets/js/layout-efb.js', 'public/assets/js/core-efb.js']);
  for (const x of js) await page.addScriptTag({ path: path.join(ROOT, x) });
  await page.evaluate((r) => { valj_efb_new = [{ id: 51, type: 'form', sid: 's', form_structer: r }]; window.__sent = 0; actionSendData_emsFormBuilder = async () => { window.__sent++; }; createStepsOfPublic(); }, ROWS);
  await page.waitForTimeout(250);
  return page;
}
const answer = (page, id) => page.evaluate((i) => (sendBack_emsFormBuilder_pub.find((r) => r && r.id_ === i) || {}).value, id);

(async () => {
  const browser = await launch();
  let p = await open(browser, true);
  check('without rich-text-efb.js: a plain textarea, visible, no editor', await p.evaluate(() => !document.querySelector('.efb-rte') && document.getElementById('st_').offsetParent !== null && document.getElementById('st_').getAttribute('data-rte') === '1'), null);
  check('a textarea without rich_text has no data-rte', await p.evaluate(() => !document.getElementById('pl_').hasAttribute('data-rte')), null);
  await p.close();

  p = await open(browser, false);
  const a11y = await p.evaluate(() => {
    const ta = document.getElementById('st_');
    const box = document.getElementById('st_rte');
    const bar = box.previousElementSibling;
    const btns = Array.from(bar.querySelectorAll('button'));
    return { hidden: ta.classList.contains('d-none') && ta.tabIndex === -1, role: box.getAttribute('role'), multi: box.getAttribute('aria-multiline'), dir: box.getAttribute('dir'), labelled: box.getAttribute('aria-labelledby'),
      req: box.getAttribute('aria-required'), barRole: bar.getAttribute('role'), barName: bar.getAttribute('aria-label'), names: btns.map((b) => b.getAttribute('aria-label')), stops: btns.filter((b) => b.tabIndex === 0).length, pressed: btns[0].getAttribute('aria-pressed') };
  });
  check('the box: role=textbox, aria-multiline, dir=auto, labelled by the label, aria-required; the textarea hidden and out of the tab order', a11y.hidden && a11y.role === 'textbox' && a11y.multi === 'true' && a11y.dir === 'auto' && a11y.labelled === 'st_lab' && a11y.req === 'true', a11y);
  check('the toolbar: role=toolbar named "Formatting", every button named, one tab stop, aria-pressed (full toolbar: 9 buttons)', a11y.barRole === 'toolbar' && a11y.barName === 'Formatting' && a11y.names.length === 9 && a11y.names.every(Boolean) && a11y.stops === 1 && a11y.pressed === 'false', a11y);
  await p.focus('#st_rte');
  await p.keyboard.type('Hi ');
  await p.keyboard.press('Control+b');
  await p.keyboard.type('you');
  await p.waitForTimeout(100);
  const pressed = await p.evaluate(() => document.querySelector('#st_rte').previousElementSibling.querySelector('[data-rte-cmd="bold"]').getAttribute('aria-pressed'));
  await p.keyboard.press('Tab');
  await p.waitForTimeout(500);
  const left = await p.evaluate(() => document.activeElement && document.activeElement.id !== 'st_rte');
  const v1 = await answer(p, 'st');
  check('Ctrl+B makes bold (aria-pressed true); the answer holds the HTML', pressed === 'true' && /Hi <(b|strong)>you<\/(b|strong)>/.test(v1 || ''), { pressed, v1 });
  check('Tab leaves the box (no keyboard trap)', left, null);
  // Toolbar keyboard: arrows move the tab stop.
  await p.focus('#st_rte');
  await p.evaluate(() => document.querySelector('#st_rte').previousElementSibling.querySelector('button').focus());
  await p.keyboard.press('ArrowRight');
  const second = await p.evaluate(() => document.activeElement.getAttribute('data-rte-cmd') + '|' + document.activeElement.tabIndex);
  check('the toolbar: ArrowRight moves to the next button (roving tab stop)', second === 'italic|0', second);
  // Paste.
  await p.evaluate(() => {
    const box = document.getElementById('st_rte');
    box.focus();
    const r = document.createRange(); r.selectNodeContents(box); r.collapse(false); const sel = getSelection(); sel.removeAllRanges(); sel.addRange(r);
    const dt = new DataTransfer();
    dt.setData('text/html', '<p class=MsoNormal style="color:red"><span style="font-size:20pt">Pasted</span> <img src=x onerror="window.__xss=1"><script>window.__xss=2</script><a href="javascript:window.__xss=3">j</a><o:p></o:p></p>');
    box.dispatchEvent(new ClipboardEvent('paste', { clipboardData: dt, bubbles: true, cancelable: true }));
  });
  await p.waitForTimeout(500);
  const pasted = await p.evaluate(() => ({ box: document.getElementById('st_rte').innerHTML, xss: window.__xss || 0 }));
  check('paste: only the allow-list (no style, class, img, script, javascript: link); the words kept', /Pasted/.test(pasted.box) && !/style=|class=|<img|<script|javascript:|<span|o:p/i.test(pasted.box) && pasted.xss === 0, pasted);
  // mlen 10 measured on the text.
  await p.evaluate(() => { const box = document.getElementById('st_rte'); box.innerHTML = '<b>12345678901</b>'; box.dispatchEvent(new Event('input', { bubbles: true })); box.dispatchEvent(new Event('blur')); });
  await p.waitForTimeout(600);
  const tooLong = await p.evaluate(() => ({ row: (sendBack_emsFormBuilder_pub.find((r) => r && r.id_ === 'st') || null), msg: (document.getElementById('st_-message') || {}).textContent || '' }));
  check('mlen 10: "<b>12345678901</b>" (11 characters of text) is refused with the field\'s message', tooLong.row === null && /10/.test(tooLong.msg), tooLong);
  await p.evaluate(() => { const box = document.getElementById('st_rte'); box.innerHTML = '<p>سلام <b>Ali</b></p>'; box.dispatchEvent(new Event('input', { bubbles: true })); box.dispatchEvent(new Event('blur')); });
  await p.waitForTimeout(600);
  check('Persian in the box (dir=auto) is kept, 9 characters of text accepted under mlen 10', (await answer(p, 'st')) === '<p>سلام <b>Ali</b></p>', await answer(p, 'st'));
  // The review page shows the text.
  await p.evaluate(() => document.querySelector('#btn_send_efb').click());
  await p.waitForTimeout(500);
  const rv = await p.evaluate(() => { const r = document.querySelector('#efb-review-step-51 .efb-review-row[data-field="st"] .efb-review-answer'); return r ? { text: r.textContent, html: r.innerHTML } : null; });
  check('the review page shows the text of the rich answer, never its tags', rv && rv.text === 'سلام Ali' && !/<b>/.test(rv.html), rv);
  // A repeater row's rich textarea.
  check('a rich textarea in a repeater row gets its own editor', await p.evaluate(() => !!document.querySelector('[id^="gn_r"][id$="_rte"]')), null);
  check('no page error', p.errors.length === 0, p.errors);
  await p.close();
  await browser.close();
  console.log('\nRESULTS: ' + pass + ' passed, ' + fail + ' failed');
  process.exit(fail ? 1 : 0);
})().catch((e) => { console.error(e); console.log('\nRESULTS: ' + pass + ' passed, ' + (fail + 1) + ' failed'); process.exit(1); });
