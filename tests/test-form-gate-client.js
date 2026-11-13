/**
 * F11-B in the browser code (public/assets/js/core-efb.js, request-health-efb.js), in a vm with a
 * small fake DOM:
 *  - efb_failure_cause_efb() maps the five gate codes to 'form_gate' and efb_visitor_text_efb()
 *    shows the server's sentence (also when the refusal came as 403 from prepare);
 *  - request-health-efb.js treats the gate codes as final (prepare does not fall back to the
 *    page nonce, so a closed form is not "sent" by an old cached page) and passes reopen_at;
 *  - efb_gate_check_efb(): a cached widget whose card says "pending" shows the form once prepare
 *    answers open (and hides the card); a cached open form whose prepare answers 403
 *    efb_form_closed hides the form and shows the card with the sentence (role=status);
 *    places left are updated in the language's digits; another refusal leaves the page alone;
 *  - a card in "card" mode reloads the page when the form opens within a day (timer), not later.
 * Run: node tests/test-form-gate-client.js
 */
'use strict';
const fs = require('fs');
const vm = require('vm');
const path = require('path');
const ROOT = path.join(__dirname, '..');
let passed = 0, failed = 0;
function t(name, actual, expected) {
  const a = JSON.stringify(actual), e = JSON.stringify(expected);
  if (a === e) { passed++; console.log('[PASS] ' + name); } else { failed++; console.log('[FAIL] ' + name + '\n   expected ' + e + '\n   actual   ' + a); }
}
const core = fs.readFileSync(path.join(ROOT, 'public/assets/js/core-efb.js'), 'utf8');
const slice = (from, to) => { const a = core.indexOf(from); const b = to ? core.indexOf(to, a) : core.length; if (a < 0 || b <= a) throw new Error('slice ' + from); return core.slice(a, b); };

/* ---- causes */
const sb = { ajax_object_efm: { text: {} } };
vm.createContext(sb);
vm.runInContext(slice('function efb_failure_cause_efb', 'async function efb_fetch_public_request_efb'), sb);
for (const code of ['efb_form_pending', 'efb_form_closed', 'efb_form_full', 'efb_limit_reached', 'efb_role_denied']) {
  t('cause of ' + code + ' (403, efb answer) is form_gate', sb.efb_failure_cause_efb({ status: 403, code, m: 'x', shape: 'efb' }), 'form_gate');
}
t('cause of an in-band 200 refusal stays server_refusal (shape efb)', sb.efb_failure_cause_efb({ status: 500, code: 'efb_storage_failed', m: 'x', shape: 'efb' }), 'server_failed');
t('visitor text = the server sentence', sb.efb_visitor_text_efb('form_gate', { status: 403, m: 'This form closed on 13 Nov.' }), 'This form closed on 13 Nov.');
t('efb_login_required is not mistaken for the gate', sb.efb_failure_cause_efb({ status: 403, code: 'efb_login_required', m: 'Please log in', shape: 'efb' }), 'server_refusal');

/* ---- request-health FINAL */
const rh = fs.readFileSync(path.join(ROOT, 'public/assets/js/request-health-efb.js'), 'utf8');
const finalList = /const FINAL = \[([^\]]*)\]/.exec(rh)[1];
t('prepare treats the gate codes as final', ['efb_role_denied', 'efb_form_pending', 'efb_form_closed', 'efb_form_full', 'efb_limit_reached'].every((c) => finalList.indexOf("'" + c + "'") !== -1), true);
t('prepare passes data.reopen_at with the error', /error\.reopen_at=Number\(value\.data\.reopen_at\)/.test(rh), true);
t('draft_token is not part of the request fingerprint', /\['sid','runtime_token','request_id','valid','nonce','draft_token'\]/.test(rh), true);

/* ---- fake DOM */
function makeDom() {
  const nodes = {};
  const doc = {
    readyState: 'complete',
    getElementById: (id) => nodes[id] || null,
    querySelectorAll: (sel) => (sel === '[data-efb-gate]' ? Object.values(nodes).filter((n) => n.attrs && n.attrs['data-efb-gate'] !== undefined) : []),
    documentElement: { lang: 'en-US' },
  };
  function el(id, attrs, classes) {
    const n = {
      id, attrs: Object.assign({}, attrs), cls: new Set(classes || []), html: '',
      getAttribute: (k) => (n.attrs[k] === undefined ? null : n.attrs[k]),
      classList: { toggle: (c, on) => { if (on === undefined ? !n.cls.has(c) : on) n.cls.add(c); else n.cls.delete(c); }, contains: (c) => n.cls.has(c) },
      insertAdjacentHTML: (pos, html) => { parse(html); },
    };
    Object.defineProperty(n, 'outerHTML', { get: () => n.html, set: (h) => { delete nodes[id]; parse(h); } });
    nodes[id] = n;
    return n;
  }
  function parse(html) {
    const m = /^<(\w+) class="([^"]*)" id="([^"]+)"([^>]*)>/.exec(html);
    if (!m) throw new Error('cannot parse ' + html.slice(0, 80));
    const attrs = {};
    m[4].replace(/([\w-]+)="([^"]*)"/g, (x, k, v) => { attrs[k] = v; });
    const n = el(m[3], attrs, m[2].split(/\s+/));
    n.html = html;
    return n;
  }
  return { doc, nodes, el, parse };
}

async function run() {
  const gateSrc = slice('/* ---------------------------------------------------------------------------------------------\n * F11: the form gate in the page');
  function sandbox(prepare) {
    const D = makeDom();
    const timers = [];
    const s = {
      document: D.doc, window: { EFB_GATE_NO_BOOT: true }, location: { reloaded: 0, reload() { this.reloaded++; } },
      setTimeout: (fn, ms) => { timers.push({ fn, ms }); return timers.length; }, Date, Number, String, Math, Object,
      ajax_object_efm: { language: 'fa_IR', text: { limLeft: '%s جای خالی باقی مانده' } },
    };
    s.window.EFBRuntime = { prepare };
    vm.createContext(s);
    vm.runInContext(gateSrc, s);
    return { s, D, timers };
  }

  // A cached widget printed while the form was pending; now prepare says open.
  let calls = 0;
  let X = sandbox(async (fid, force) => { calls++; return { nonce: 'n', sid: 's', runtime_token: 'r', gate: { state: 'open', closes_at: Math.floor(Date.now() / 1000) + 3600, left: 3 } }; });
  X.D.parse(X.s.efb_gate_card_html_efb('pending', 'This form opens on …', 7, 0, 'swap'));
  const body = X.D.el('body_efb_7', {}, ['efb', 'd-none']);
  const st = await X.s.efb_gate_check_efb(7);
  t('pending card in a cached page, prepare open → form shown, card hidden', [st, body.cls.has('d-none'), X.D.nodes['efb-gate-7'].cls.has('d-none'), X.D.nodes['efb-gate-7'].attrs['data-efb-gate']], ['open', false, true, 'open']);
  t('prepare was forced (fresh answer, not the cached context)', calls, 1);
  t('places left inserted in Persian digits, aria-live', [X.D.nodes['efb-gate-left-7'] && X.D.nodes['efb-gate-left-7'].html.indexOf('۳ جای خالی باقی مانده') !== -1, X.D.nodes['efb-gate-left-7'].attrs['aria-live']], [true, 'polite']);
  t('a timer is set for the close within the hour (swap mode → prepare again)', X.timers.length === 1 && X.timers[0].ms > 3500000 && X.timers[0].ms < 3700000, true);

  // A cached open form; now prepare refuses with efb_form_closed.
  X = sandbox(async () => { const e = new Error('This form closed on ۱۳ نوامبر.'); e.code = 'efb_form_closed'; e.status = 403; throw e; });
  X.D.parse(X.s.efb_gate_card_html_efb('open', '', 8, 0, 'swap'));
  const body8 = X.D.el('body_efb_8', {}, ['efb']);
  await X.s.efb_gate_check_efb(8);
  const card8 = X.D.nodes['efb-gate-8'];
  t('cached open form, prepare 403 closed → form hidden, card shown with the sentence, role=status', [body8.cls.has('d-none'), card8.cls.has('d-none'), card8.attrs['data-efb-gate'], card8.attrs.role, card8.html.indexOf('This form closed on ۱۳ نوامبر.') !== -1], [true, false, 'closed', 'status', true]);
  t('the closed card has no timer (no reopening)', X.timers.length, 0);

  // A limited visitor: reopen_at tomorrow → timer.
  X = sandbox(async () => { const e = new Error('You can send this form again after …'); e.code = 'efb_limit_reached'; e.reopen_at = Math.floor(Date.now() / 1000) + 7200; throw e; });
  X.D.parse(X.s.efb_gate_card_html_efb('open', '', 9, 0, 'swap'));
  X.D.el('body_efb_9', {}, ['efb']);
  await X.s.efb_gate_check_efb(9);
  t('limited: card state limited, timer at reopen_at', [X.D.nodes['efb-gate-9'].attrs['data-efb-gate'], X.timers.length], ['limited', 1]);

  // Another refusal (network, nonce) leaves the page as printed.
  X = sandbox(async () => { const e = new Error('offline'); e.code = 'efb_context_expired'; throw e; });
  X.D.parse(X.s.efb_gate_card_html_efb('open', '', 10, 0, 'swap'));
  const body10 = X.D.el('body_efb_10', {}, ['efb']);
  t('another refusal: nothing changes', [await X.s.efb_gate_check_efb(10), body10.cls.has('d-none')], [null, false]);

  // Card mode (page not cached): reload when the form opens within a day.
  X = sandbox(async () => ({}));
  X.D.parse(X.s.efb_gate_card_html_efb('pending', 'opens soon', 11, Math.floor(Date.now() / 1000) + 600, 'card'));
  X.s.efb_gate_boot_efb(X.D.doc);
  t('card mode: a reload timer for an opening in 10 minutes', X.timers.length === 1 && X.timers[0].ms > 590000 && X.timers[0].ms < 610000, true);
  X.timers[0].fn();
  t('the timer reloads the page', X.s.location.reloaded, 1);
  X = sandbox(async () => ({}));
  X.D.parse(X.s.efb_gate_card_html_efb('pending', 'opens later', 12, Math.floor(Date.now() / 1000) + 3 * 86400, 'card'));
  X.s.efb_gate_boot_efb(X.D.doc);
  t('card mode: no timer for an opening in 3 days', X.timers.length, 0);

  /* card markup */
  const h = X.s.efb_gate_card_html_efb('full', 'Full <b> & "x"', 5, 0, 'swap');
  t('card: escaped, aria-hidden icon, role=status', [h.indexOf('Full &lt;b&gt; &amp; &quot;x&quot;') !== -1, h.indexOf('aria-hidden="true"') !== -1, h.indexOf('role="status"') !== -1, h.indexOf('bi-people-fill') !== -1], [true, true, true, true]);
  t('digits: fa / ar / en', [X.s.efb_gate_digits_efb('12', 'fa_IR'), X.s.efb_gate_digits_efb('12', 'ar'), X.s.efb_gate_digits_efb('12', 'en_US')], ['۱۲', '١٢', '12']);

  console.log(`\nRESULTS: ${passed} passed, ${failed} failed`);
  process.exit(failed ? 1 : 0);
}
run().catch((e) => { console.error(e); process.exit(1); });
