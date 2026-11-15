/**
 * F12 / F12b: the CAPTCHA boxes of Easy Form Builder on a page (window.EFBCaptcha). docs/spam/captcha-contract.en.md.
 *
 *  - each box (.efb-captcha[data-provider], data-efb-captcha = its public config) has its own id, widget and token;
 *    many forms, mixed services and Google modes on one page do not share anything;
 *  - a service's adapter registers with EFBCaptcha.provider(id, {ready, render, execute, reset, remove}); core
 *    brings Google (v2 checkbox, v2 invisible, v3 score, v3 → v2 checkbox when the score is low);
 *  - a visible widget renders when its box comes into view (the last step of a long form spends nothing early); a
 *    token for the invisible kinds is asked for right before sending (10 s limit); no global callback;
 *  - a solved widget hands its token to its form ("captcha_v2" row, sent as "valid") and fires efb:captcha;
 *  - reset(formId) resets that form's boxes only; reset(-1, ctx) one context outside a form (tracker, reply_1 …).
 */
(function () {
  'use strict';
  if (typeof window === 'undefined') return;
  var W = window, D = W.document, cfg = W.efb_captcha_cfg || {}, ACTION = cfg.action || 'efb_submit';
  var A = {}, tokens = {}, loaded = {}, tries = 0, seq = 0, io = null;

  function T(k, fb) {
    var t = cfg.text || {};
    if (t[k]) return t[k];
    try { if (W.efb_var && W.efb_var.text && W.efb_var.text[k]) return W.efb_var.text[k]; } catch (e) {}
    return fb;
  }
  function at(el, k) { return el.getAttribute(k); }
  function fidOf(el) { var n = Number(at(el, 'data-formid')); return isFinite(n) && n !== 0 ? n : -1; }
  function ctxOf(el) { return at(el, 'data-ctx') || (fidOf(el) > 0 ? 'form' : 'tracker'); }
  function bid(el) { var b = at(el, 'data-efb-box'); if (!b) { b = 'b' + (++seq); el.setAttribute('data-efb-box', b); } return b; }
  function box(el) { return el.closest ? el.closest('.efb-captcha-box') || el.parentNode : el.parentNode; }
  function say(el, text) { var b = box(el), m = b && b.querySelector ? b.querySelector('.efb-captcha-msg') : null; if (m) m.textContent = text || ''; }
  function narrow(el) {
    var w = 0;
    try { w = el.getBoundingClientRect().width; } catch (e) {}
    return (w > 0 && w < 304) || (W.innerWidth || 1024) < 400;
  }
  function dark() { try { return !!(W.matchMedia && W.matchMedia('(prefers-color-scheme: dark)').matches); } catch (e) { return false; } }
  /** The box's config: data-efb-captcha (F12b) or the F12 attributes. */
  function conf(el) {
    if (el._c) return el._c;
    var c = {};
    try { c = JSON.parse(at(el, 'data-efb-captcha') || '{}') || {}; } catch (e) { c = {}; }
    c.p = c.p || at(el, 'data-provider');
    c.k = c.k || at(el, 'data-sitekey') || '';
    if (!c.lang) { c.lang = at(el, 'data-lang') || ''; try { if (!c.lang) c.lang = (D.documentElement.getAttribute('lang') || '').split('-')[0]; } catch (e) {} }
    c.kind = c.kind || (c.p === 'recaptcha_v3' ? 'invisible' : 'widget');
    c.dark = c.theme === 'dark' || (c.theme === 'auto' && dark());
    return (el._c = c);
  }
  function list(sel) { return D.querySelectorAll('.efb-captcha[data-provider]' + (sel || '')); }
  function boxFor(fid) { var l = list('[data-formid="' + (Number(fid) || 0) + '"]'); return l.length ? l[0] : null; }

  function solved(el, t) {
    tokens[bid(el)] = t;
    say(el, '');
    if (el._res) { var r = el._res; el._res = null; r(t); return; }
    try { if (typeof W.recaptchaSuccessEfb === 'function') W.recaptchaSuccessEfb(t, fidOf(el)); } catch (e) {}
    try { el.dispatchEvent(new CustomEvent('efb:captcha', { bubbles: true, detail: { ctx: ctxOf(el), formId: fidOf(el), token: t } })); } catch (e) {}
  }
  function expired(el) {
    delete tokens[bid(el)];
    say(el, T('captchaExpired', 'The check expired. Please complete it again.'));
    try { if (typeof W.recaptchaExpiredEfb === 'function') W.recaptchaExpiredEfb(fidOf(el)); } catch (e) {}
  }
  function failed(el) {
    delete tokens[bid(el)];
    if (el._rej) { var r = el._rej; el._rej = null; r(new Error('captcha')); }
    say(el, conf(el).mf || T('captchaFailed', 'The security check could not be completed. Please try again.'));
  }

  /** A provider's script (and adapter) that is not on the page: a form drawn after the footer. */
  function load(c) {
    if (tries < 10) return;
    [c.src, c.ad].forEach(function (u) {
      if (!u || loaded[u] || !/^https:\/\//.test(u)) return;
      loaded[u] = 1;
      try {
        var b = u.split('?')[0], s = D.scripts || [];
        for (var i = 0; i < s.length; i++) if (String(s[i].src || '').indexOf(b) === 0) return;
        var e = D.createElement('script');
        e.src = u; e.async = true;
        (D.head || D.documentElement).appendChild(e);
      } catch (x) {}
    });
  }

  /** Draws one widget (force: also when out of view); false while its service is not there yet. */
  function render(el, force) {
    if (at(el, 'data-efb-rendered') === '1') return true;
    var c = conf(el), a = A[c.p];
    if (!c.k && c.kind !== 'endpoint') return true;
    if (c.p === 'recaptcha_v3' && !el._fb) { el.setAttribute('data-efb-rendered', '1'); return true; }
    if (!a || !a.ready()) { load(c); return false; }
    if (io && !force && !el._vis) { if (!el._io) { el._io = 1; io.observe(el); } return true; }
    c.narrow = narrow(el);
    var id = null;
    try {
      id = a.render(el, c, { solved: function (t) { solved(el, t); }, expired: function () { expired(el); }, error: function () { failed(el); } });
    } catch (e) { failed(el); }
    el.setAttribute('data-efb-rendered', '1');
    if (id !== null && id !== undefined) {
      el.setAttribute('data-efb-widget-id', String(id));
      if (c.p.indexOf('recaptcha') === 0) el.setAttribute('data-grecaptcha-widget-id', String(id));
    }
    return true;
  }

  function boot() {
    var waiting = 0, l = list();
    for (var i = 0; i < l.length; i++) if (!render(l[i])) waiting++;
    if (waiting && tries < 240) { tries++; setTimeout(boot, tries < 30 ? 100 : 500); }
  }

  /** A promise that gives up after ms (the visitor reads the message). */
  function limit(p, ms) {
    return new Promise(function (ok, no) {
      var done = 0, t = setTimeout(function () { if (!done) { done = 1; no(new Error('timeout')); } }, ms);
      p.then(function (v) { if (!done) { done = 1; clearTimeout(t); v ? ok(v) : no(new Error('empty')); } }, function (e) { if (!done) { done = 1; clearTimeout(t); no(e); } });
    });
  }

  /** A fresh reCAPTCHA v3 token (Promise; rejects after timeoutMs). */
  function v3Token(key, timeoutMs, action) {
    return limit(new Promise(function (ok, no) {
      var go = function () {
        var g = W.grecaptcha;
        if (!g || typeof g.ready !== 'function') { setTimeout(go, 150); return; }
        g.ready(function () { try { g.execute(key, { action: action || ACTION }).then(ok, no); } catch (e) { no(e); } });
      };
      go();
    }), timeoutMs || 10000);
  }

  /** The token of an invisible box (v3, v2 invisible, an adapter that executes on submit). */
  function tokenOf(el) {
    var c = conf(el);
    if (c.p === 'recaptcha_v3' && !el._fb) return v3Token(c.k, 10000, c.action);
    var have = tokens[bid(el)];
    if (have && !c.ex && c.kind === 'widget') return Promise.resolve(have);
    render(el, 1);
    var a = A[c.p], id = at(el, 'data-efb-widget-id');
    if (!a || !a.execute) return Promise.reject(new Error('not ready'));
    return limit(new Promise(function (ok, no) { el._res = ok; el._rej = no; try { Promise.resolve(a.execute(id, c, el)).then(function (t) { if (t) solved(el, t); }, no); } catch (e) { no(e); } }), 10000);
  }

  function needs(el) { var c = conf(el); return !!el._fb || (c.kind === 'widget' && !c.ex); }
  function needsWidgetToken(fid) { var el = boxFor(fid); return !!el && needs(el); }

  /** Before a form request: an invisible box gets its token in data.valid; false = do not send (message shown). */
  async function beforeSend(data, fid) {
    var el = boxFor(fid);
    if (!el) return true;
    if (el._fb) { data.captcha_kind = 'v2'; return true; }
    if (needs(el)) return true;
    try {
      data.valid = await tokenOf(el);
      say(el, '');
      return !!data.valid;
    } catch (e) {
      failed(el);
      return false;
    }
  }

  /** v3 → v2: the server found the score low; the checkbox appears in the same box (true when the form has one). */
  function challenge(fid) {
    var el = boxFor(fid), c = el ? conf(el) : null;
    if (!c || !c.fb) return false;
    el._fb = 1;
    c.k = c.fb;
    c.p = 'recaptcha_v2';
    c.kind = 'widget';
    el.removeAttribute('hidden');
    el.setAttribute('data-efb-rendered', '0');
    render(el, 1) || boot();
    say(el, T('cpChallenge', 'Please confirm you are not a robot.'));
    try { box(el).focus(); } catch (e) {}
    return true;
  }

  /** After a refused answer: the spent token is gone, the widget asks again. reset(-1, ctx): one context only. */
  function reset(fid, ctx) {
    fid = Number(fid) || -1;
    var l = list('[data-formid="' + fid + '"]');
    for (var i = 0; i < l.length; i++) {
      var el = l[i], c = conf(el), a = A[c.p], id = at(el, 'data-efb-widget-id');
      if (fid < 0 && ctx && ctxOf(el) !== ctx) continue;
      delete tokens[bid(el)];
      try { if (a && a.reset && id !== null) a.reset(id, c, el); } catch (e) {}
    }
    try { if (typeof W.recaptchaExpiredEfb === 'function') W.recaptchaExpiredEfb(fid); } catch (e) {}
  }

  /** P20: the token of a box outside a form (an invisible one is asked for now). Promise of '' when none. */
  async function token(ctx) {
    var l = list('[data-ctx="' + String(ctx || '').replace(/[^a-z0-9_]/g, '') + '"]'), el = l.length ? l[0] : null;
    if (!el) return '';
    if (needs(el)) return tokens[bid(el)] || '';
    try { return await tokenOf(el); } catch (e) { failed(el); return ''; }
  }

  /* Google: one script for the page; every box renders on it with its own key and options. */
  var G = {
    ready: function () { return !!(W.grecaptcha && W.grecaptcha.render); },
    render: function (el, c, cb) {
      var o = { sitekey: c.k, theme: c.dark ? 'dark' : 'light', callback: cb.solved, 'expired-callback': cb.expired, 'error-callback': cb.error };
      if (c.p === 'recaptcha_v2i') { o.size = 'invisible'; o.badge = c.badge === 'hidden' ? 'bottomright' : (c.badge || 'bottomright'); }
      else o.size = c.size === 'compact' || ((c.size || 'auto') === 'auto' && c.narrow) ? 'compact' : 'normal';
      return W.grecaptcha.render(el, o);
    },
    execute: function (id) { W.grecaptcha.execute(Number(id)); },
    reset: function (id) { W.grecaptcha.reset(Number(id)); }
  };
  A.recaptcha_v2 = A.recaptcha_v2i = A.recaptcha_v3 = G;
  /* F12b-A until F12b-E moves them to their add-ons: hCaptcha and Turnstile of F12. */
  A.hcaptcha = { ready: function () { return !!(W.hcaptcha && W.hcaptcha.render); }, reset: function (id) { W.hcaptcha.reset(id); },
    render: function (el, c, cb) { return W.hcaptcha.render(el, { sitekey: c.k, hl: c.lang, size: c.narrow ? 'compact' : 'normal', callback: cb.solved, 'expired-callback': cb.expired, 'error-callback': cb.error }); } };
  A.turnstile = { ready: function () { return !!(W.turnstile && W.turnstile.render); }, reset: function (id) { W.turnstile.reset(id); },
    render: function (el, c, cb) { return W.turnstile.render(el, { sitekey: c.k, action: c.action || ACTION, language: c.lang || 'auto', size: c.narrow ? 'flexible' : 'normal', callback: cb.solved, 'expired-callback': cb.expired, 'error-callback': cb.error, 'timeout-callback': cb.expired }); } };

  function provider(id, adapter) {
    if (!/^[a-z][a-z0-9_]{2,23}$/.test(String(id)) || !adapter || typeof adapter.render !== 'function' || A[id]) return false;
    A[id] = adapter;
    tries = 0;
    boot();
    return true;
  }

  try {
    if (typeof IntersectionObserver !== 'undefined') io = new IntersectionObserver(function (es) {
      es.forEach(function (e) { if (e.isIntersecting) { e.target._vis = 1; io.unobserve(e.target); tries = 0; boot(); } });
    }, { rootMargin: '200px' });
  } catch (e) { io = null; }

  W.EFBCaptcha = { boot: boot, scan: function () { tries = 0; boot(); }, render: render, reset: reset, beforeSend: beforeSend, challenge: challenge,
    isInvisible: function (fid) { return !!boxFor(fid) && !needsWidgetToken(fid); }, needsWidgetToken: needsWidgetToken, token: token, v3Token: v3Token,
    message: say, provider: provider, config: conf };

  if (D.readyState === 'loading') D.addEventListener('DOMContentLoaded', boot); else boot();
  // Forms drawn later (a popup, a tab, AJAX, a page builder's preview) bring their boxes with them.
  try {
    if (typeof MutationObserver !== 'undefined') {
      new MutationObserver(function (m) {
        for (var i = 0; i < m.length; i++) for (var j = 0, n = m[i].addedNodes; j < n.length; j++) {
          if (n[j].nodeType === 1 && ((n[j].matches && n[j].matches('.efb-captcha[data-provider]')) || (n[j].querySelector && n[j].querySelector('.efb-captcha[data-provider]')))) { tries = 0; boot(); return; }
        }
      }).observe(D.documentElement, { childList: true, subtree: true });
    }
    W.addEventListener && W.addEventListener('elementor/popup/show', function () { tries = 0; boot(); });
  } catch (e) {}
})();
