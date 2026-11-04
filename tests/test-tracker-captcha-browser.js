/**
 * Browser test of the confirmation-code finder with reCAPTCHA on.
 *
 * The server now verifies the token sent with every lookup, and a token is
 * good for one verification only. This drives the shipped page code with a
 * stand-in grecaptcha and a stubbed lookup endpoint and checks the visitor
 * flow end to end:
 *   - no lookup is sent before the box is ticked;
 *   - the token from the widget is sent as "valid";
 *   - a refused lookup shows the error card (its button reloads the page),
 *     and the widget is reset and the spent token dropped on the way;
 *   - after the reload a fresh token is sent and the ticket opens;
 *   - with reCAPTCHA off nothing changes.
 *
 * Run: node tests/test-tracker-captcha-browser.js
 *      (EFB_CHROMIUM=/path/to/chrome to use a specific Chromium build)
 */
'use strict';
const { chromium } = require('playwright');
const fs = require('fs');
const path = require('path');
const JS = path.join(__dirname, '..', 'includes', 'admin', 'assets', 'js');
const viewer = fs.readFileSync(path.join(JS, 'response-viewer-efb.js'), 'utf8');
const core = fs.readFileSync(path.join(__dirname, '..', 'public', 'assets', 'js', 'core-efb.js'), 'utf8');
const newEfb = fs.readFileSync(path.join(JS, 'new-efb.js'), 'utf8');
const lift = (src, re, name) => { const m = src.match(re); if (!m) throw new Error('missing ' + name); return m[0]; };
const pieces = [
  lift(newEfb, /const sanitize_text_efb=\(str[\s\S]*?\n}\n/, 'sanitize_text_efb'),
  lift(core, /post_api_tracker_check_efb=\(data,innrBtn\)=>\{[\s\S]*?\n}\n/, 'post_api_tracker_check_efb'),
  lift(core, /efb_reset_tracker_captcha_efb=\(\)=>\{[\s\S]*?\n}\n/, 'efb_reset_tracker_captcha_efb'),
  lift(core, /recaptchaExpiredEfb = \(formId\)=>\{[\s\S]*?\n}\n/, 'recaptchaExpiredEfb'),
  lift(core, /recaptchaSuccessEfb = \(token, formid\) => \{[\s\S]*?\n}\n/, 'recaptchaSuccessEfb'),
  lift(core, /function loadCaptcha_efb\(timer = 20\) \{[\s\S]*?\n};\n/, 'loadCaptcha_efb'),
].join('\n');

let failures = 0;
const check = (label, ok, detail) => { console.log((ok ? '[PASS] ' : '[FAIL] ') + label + (ok || detail === undefined ? '' : ' ' + JSON.stringify(detail))); if (!ok) failures++; };

async function page(browser, captchaOn) {
  const p = await browser.newPage();
  const lookups = [];
  let answers = [];
  await p.route('**/*', async (route) => {
    const req = route.request();
    if (req.url().includes('/wp-json/Emsfb/v1/forms/response/get')) {
      lookups.push(JSON.parse(req.postData() || '{}'));
      const a = answers.shift() || { success: true, data: { success: false, m: 'no' } };
      return route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify(a) });
    }
    return route.fulfill({ status: 200, contentType: 'text/html', body: '<div id="body_tracker_emsFormBuilder"></div>' });
  });
  await p.goto('http://site.test/track');
  const T = 'new Proxy({}, {get:(t,k)=>typeof k==="string"?k:undefined})';
  await p.addScriptTag({ content: `
    var efb_var={rtl:0,rest_url:'http://site.test/wp-json/',nonce:'n',sid:'s',text:${T}};
    var ajax_object_efm={rtl:0,text:${T},form_setting:{siteKey:${captchaOn ? "'site-key'" : "''"}}};
    var setting_emsFormBuilder={scaptcha:${captchaOn},siteKey:'site-key'};
    var pro_efb=true, currentTab_emsFormBuilder=0, formNameEfb='', recaptcha_emsFormBuilder='', el, data;
    var sendBack_emsFormBuilder_pub=[];
    window.notices=[]; window.results=[]; window.resets=[];
    function noti_message_efb_v4(m){ window.notices.push(m); }
    function change_url_back_persia_pay_efb(){}
    function efb_fetch_public_request_efb(url, opts){ return fetch(url, opts); }
    function fun_sendBack_emsFormBuilder(ob){
      const i = sendBack_emsFormBuilder_pub.findIndex(x => x.id_ === ob.id_ && x.form_id === ob.form_id);
      i === -1 ? sendBack_emsFormBuilder_pub.push(ob) : (sendBack_emsFormBuilder_pub[i] = ob);
    }
    window.grecaptcha = { widgets: [], render(el, o){ this.widgets.push(o); return this.widgets.length - 1; }, reset(id){ window.resets.push(id); } };
  ` + pieces });
  await p.addScriptTag({ path: path.join(JS, 'response-viewer-efb.js') });
  /* Record every result; a refused lookup still goes through the shipped
     handler, which swaps in the error card. */
  await p.addScriptTag({ content: `const realValidEfb = response_Valid_tracker_efb;
    response_Valid_tracker_efb = (r) => { window.results.push(r); if (!(r && r.data && r.data.success === true)) realValidEfb(r); };` });
  await p.evaluate(() => new Promise(r => { fun_tracking_show_emsFormBuilder(); setTimeout(r, 100); }));
  if (captchaOn) await p.evaluate(() => loadCaptcha_efb(20));
  await p.fill('#trackingCodeEfb', 'EFB12345');
  const state = () => p.evaluate(() => ({
    token: (sendBack_emsFormBuilder_pub.find(x => x.id_ === 'captcha_v2' && x.form_id === -1) || {}).value || null,
    disabled: document.getElementById('vaid_check_emsFormBuilder') ? document.getElementById('vaid_check_emsFormBuilder').classList.contains('disabled') : null,
    errorCard: !!document.querySelector('#body_efb-track #emsFormBuilder-text-prevBtn-view') && document.getElementById('body_efb-track').textContent.includes('errorMRobot'),
    resets: window.resets.slice(), notices: window.notices.slice(), results: window.results.length,
  }));
  const click = async () => { await p.evaluate(() => fun_vaid_tracker_check_emsFormBuilder()); await p.waitForTimeout(300); };
  const solve = (tok) => p.evaluate((tok) => window.grecaptcha.widgets[0].callback(tok), tok);
  return { p, lookups, setAnswers: (a) => { answers = a; }, state, click, solve };
}

(async () => {
  const browser = await chromium.launch(process.env.EFB_CHROMIUM ? { executablePath: process.env.EFB_CHROMIUM } : {});
  const errors = [];

  // reCAPTCHA on
  const t = await page(browser, true);
  t.p.on('pageerror', e => errors.push(e.message));
  check('widget is rendered for the tracker (form id -1)', await t.p.evaluate(() => window.grecaptcha.widgets.length === 1 && !!document.querySelector('.g-recaptcha[data-formid="-1"]')));
  await t.click();
  let s = await t.state();
  check('no lookup is sent before the box is ticked', t.lookups.length === 0 && s.notices.includes('checkedBoxIANotRobot'), { lookups: t.lookups.length, s });

  t.setAnswers([{ success: true, data: { success: false, m: 'errorMRobot' } }]);
  await t.solve('first-token-' + 'x'.repeat(30));
  s = await t.state();
  check('ticking the box enables the button', !s.disabled && s.token && s.token.startsWith('first-token-'), s);
  await t.click();
  check('the widget token is sent as "valid"', t.lookups.length === 1 && t.lookups[0].valid.startsWith('first-token-'), t.lookups);
  s = await t.state();
  check('a refused lookup shows the error card with its reload button', s.errorCard === true, s);
  check('... the widget was reset and the spent token dropped', JSON.stringify(s.resets) === '[0]' && s.token === null, s);
  await t.p.close();

  // The error card's button reloads the page: a new widget, a new token.
  const t2 = await page(browser, true);
  t2.p.on('pageerror', e => errors.push(e.message));
  t2.setAnswers([{ success: true, data: { success: true, id: 7, nonce_msg: 'x' } }]);
  await t2.solve('second-token-' + 'y'.repeat(30));
  await t2.click();
  s = await t2.state();
  check('after the reload the lookup carries the fresh token', t2.lookups.length === 1 && t2.lookups[0].valid.startsWith('second-token-'), t2.lookups.map(x => x.valid));
  check('a successful lookup is handed on without a reset', s.results === 1 && s.resets.length === 0, s);
  await t2.p.close();

  // reCAPTCHA off
  const o = await page(browser, false);
  o.p.on('pageerror', e => errors.push(e.message));
  o.setAnswers([{ success: true, data: { success: false, m: 'cCodeNFound' } }]);
  check('with reCAPTCHA off no widget is shown', await o.p.evaluate(() => !document.querySelector('.g-recaptcha')));
  await o.click();
  s = await o.state();
  check('... the lookup goes out straight away with an empty token', o.lookups.length === 1 && o.lookups[0].valid === '', o.lookups);
  check('... and a refused lookup shows its message without touching a widget', s.resets.length === 0 && s.results === 1, s);
  await o.p.close();

  check('no page errors', errors.length === 0, errors);
  await browser.close();
  console.log('\n========================================\nRESULTS: ' + (failures ? failures + ' failed' : 'all passed') + '\n========================================');
  process.exit(failures ? 1 : 0);
})();
