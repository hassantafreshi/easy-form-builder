/**
 * F12b: a small page for public/assets/js/captcha-efb.js in a vm (no browser): boxes with their config
 * (data-efb-captcha), a live region each, the scripts already on the page, timers run by hand, optional
 * IntersectionObserver. Used by test-captcha-runtime.js, test-rc-modes-client.js and test-captcha-multiform.js.
 */
'use strict';
const fs = require('fs');
const path = require('path');
const vm = require('vm');
const ROOT = path.join(__dirname, '..');
const SRC = fs.readFileSync(path.join(ROOT, 'public/assets/js/captcha-efb.js'), 'utf8');

function el(attrs, parent) {
  const a = Object.assign({}, attrs);
  const e = {
    nodeType: 1, attrs: a, parentNode: parent || null, textContent: '', events: [],
    getAttribute: (k) => (k in a ? String(a[k]) : null), setAttribute: (k, v) => { a[k] = String(v); }, hasAttribute: (k) => k in a, removeAttribute: (k) => { delete a[k]; },
    getBoundingClientRect: () => ({ width: a._w || 300 }),
    dispatchEvent: (ev) => { e.events.push(ev); return true; },
    closest: (sel) => (sel === '.efb-captcha-box' && e.parentNode && e.parentNode.isBox ? e.parentNode : null),
  };
  return e;
}
function matches(e, sel) {
  const m = sel.match(/^\.([\w-]+)((?:\[[^\]]+\])*)$/);
  if (!m || !String(e.attrs.class || '').split(/\s+/).includes(m[1])) return false;
  const conds = (m[2].match(/\[[^\]]+\]/g) || []).map((c) => c.slice(1, -1).split('='));
  return conds.every(([k, v]) => (v === undefined ? k in e.attrs : String(e.attrs[k]) === v.replace(/^"|"$/g, '')));
}

/** boxes: [{provider, key, fid, ctx, cfg:{…}}]; opts: width, io (true = IntersectionObserver), scripts: [src], late: true (DOMContentLoaded later). */
function page(boxes, opts) {
  opts = opts || {};
  const all = [];
  const added = [];
  const boxEls = [];
  const io = { observed: [], cb: null };
  const mkBox = (b) => {
    const box = { isBox: true, focused: 0, focus() { this.focused++; }, msg: { textContent: '' } };
    box.querySelector = (s) => (s === '.efb-captcha-msg' ? box.msg : null);
    const cfg = Object.assign({ p: b.provider, k: b.key || (b.provider + '-site'), kind: b.kind || (b.provider === 'recaptcha_v3' ? 'invisible' : 'widget') }, b.cfg || {});
    const attrs = { class: 'efb efb-captcha', 'data-provider': b.provider, 'data-sitekey': cfg.k, 'data-formid': String(b.fid == null ? -1 : b.fid), 'data-ctx': b.ctx || (b.fid > 0 ? 'form' : 'tracker'), 'data-efb-captcha': JSON.stringify(cfg) };
    if (b.width) attrs._w = b.width;
    if (b.provider === 'recaptcha_v3') attrs.hidden = '';
    const e = el(attrs, box);
    e.box = box;
    all.push(e);
    boxEls.push(box);
    return e;
  };
  boxes.forEach(mkBox);
  const timers = [];
  const calls = { rows: [], expired: [] };
  const win = {
    innerWidth: opts.width || 1200,
    efb_captcha_cfg: { action: 'efb_submit', text: { captchaExpired: 'EXPIRED', captchaFailed: 'FAILED', cpChallenge: 'CHALLENGE' } },
    document: {
      readyState: 'complete', documentElement: { getAttribute: () => opts.lang || 'en' }, addEventListener() {},
      querySelectorAll: (sel) => all.filter((e) => matches(e, sel)),
      querySelector: (sel) => all.find((e) => matches(e, sel)) || null,
      scripts: (opts.scripts || []).map((src) => ({ src })),
      createElement: (t) => ({ tagName: t }),
      head: { appendChild: (n) => { added.push(n.src); } },
    },
    setTimeout: (fn, ms) => { timers.push({ fn, ms }); return timers.length; }, clearTimeout(id) { if (timers[id - 1]) timers[id - 1].fn = () => {}; },
    recaptchaSuccessEfb: (tok, fid) => calls.rows.push({ tok, fid }),
    recaptchaExpiredEfb: (fid) => calls.expired.push(fid),
    CustomEvent: function (n, o) { this.type = n; this.detail = o.detail; },
    Promise, Object, String, Number, isFinite, JSON, Array, Error,
  };
  if (opts.io) {
    win.IntersectionObserver = function (cb) { io.cb = cb; this.observe = (t) => io.observed.push(t); this.unobserve = () => {}; };
  }
  if (opts.matchDark) win.matchMedia = () => ({ matches: true });
  if (opts.before) opts.before(win);
  win.window = win;
  vm.runInContext(SRC, vm.createContext(win));
  return {
    win, all, calls, timers, added, io,
    C: win.EFBCaptcha,
    run: () => { for (let i = 0; i < 400 && timers.length; i++) { const ts = timers.splice(0); ts.forEach((x) => x.fn()); } },
    show: (e) => io.cb([{ isIntersecting: true, target: e }]),
    add: (b) => mkBox(b),
  };
}
const tick = () => new Promise((r) => setImmediate(r));

function checker() {
  let passed = 0, failed = 0;
  return {
    t(name, actual, expected) {
      const a = JSON.stringify(actual), e = JSON.stringify(expected);
      if (a === e) { passed++; console.log('[PASS] ' + name); } else { failed++; console.log('[FAIL] ' + name + '\n  expected: ' + e + '\n  actual:   ' + a); }
    },
    done() { console.log('\nRESULTS: ' + passed + ' passed, ' + failed + ' failed'); process.exit(failed ? 1 : 0); },
  };
}

/** A fake Google: render / execute / reset / ready; widgets keep their options and callbacks. */
function fakeGoogle() {
  const g = { widgets: [], execs: [], resets: [], v3: [] };
  g.render = (e, o) => { g.widgets.push(o); return g.widgets.length - 1; };
  g.execute = (k, o) => {
    if (typeof k === 'number') { g.execs.push(k); return undefined; }
    g.v3.push([k, o && o.action]);
    return Promise.resolve('V3-' + g.v3.length);
  };
  g.reset = (id) => g.resets.push(id);
  g.ready = (cb) => cb();
  return g;
}

module.exports = { page, tick, checker, fakeGoogle, SRC };
