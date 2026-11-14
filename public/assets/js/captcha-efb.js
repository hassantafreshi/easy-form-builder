/**
 * F12: the CAPTCHA widgets of Easy Form Builder on a page (window.EFBCaptcha). docs/feature-gaps/F12-spam-core.fa.md §3,
 * docs/spam/captcha-contract.en.md (P20: a widget outside a form).
 *
 *  - renders every .efb-captcha[data-provider] when its service's script is there: hCaptcha, Cloudflare Turnstile and
 *    reCAPTCHA v2 (explicit render; core-efb.js loadCaptcha_efb() renders the same v2 widgets, whichever comes first
 *    marks data-grecaptcha-widget-id), in the page's language, compact / flexible on a phone;
 *  - a solved widget hands its token to the form exactly as reCAPTCHA v2 always did (the "captcha_v2" row, sent as
 *    "valid"; never stored in localStorage) and fires "efb:captcha" on the widget ({ctx, formId, token});
 *  - reCAPTCHA v3 has no widget: beforeSend() asks Google for a fresh token right before the request (a token lives
 *    two minutes), with a 10-second limit; a retry gets a new one (the request's fingerprint leaves "valid" out);
 *  - reset(formId) after every refused answer (a token is spent once); messages go to the widget's live region.
 */
(function () {
  'use strict';
  if (typeof window === 'undefined') return;
  var cfg = window.efb_captcha_cfg || {};
  var ACTION = cfg.action || 'efb_submit';
  var tokens = {};
  var tries = 0;

  function T(k, fb) {
    var t = cfg.text || {};
    if (t[k]) return t[k];
    try { if (typeof efb_var !== 'undefined' && efb_var && efb_var.text && efb_var.text[k]) return efb_var.text[k]; } catch (e) {}
    try { if (typeof ajax_object_efm !== 'undefined' && ajax_object_efm.text && ajax_object_efm.text[k]) return ajax_object_efm.text[k]; } catch (e) {}
    return fb;
  }
  function fidOf(el) { var n = Number(el.getAttribute('data-formid')); return isFinite(n) && n !== 0 ? n : -1; }
  function ctxOf(el) { return el.getAttribute('data-ctx') || (fidOf(el) > 0 ? 'form' : 'tracker'); }
  function box(el) { return el.closest ? el.closest('.efb-captcha-box') || el.parentNode : el.parentNode; }
  function msgEl(el) { var b = box(el); return b && b.querySelector ? b.querySelector('.efb-captcha-msg') : null; }
  function say(el, text) { var m = msgEl(el); if (m) m.textContent = text || ''; }
  function narrow(el) {
    var w = 0;
    try { w = el.getBoundingClientRect().width; } catch (e) {}
    return (w > 0 && w < 300) || (window.innerWidth || 1024) < 400;
  }
  function lang(el) {
    var l = el.getAttribute('data-lang') || '';
    if (!l) { try { l = (document.documentElement.getAttribute('lang') || '').split('-')[0]; } catch (e) {} }
    return l || '';
  }

  function solved(el, token) {
    var fid = fidOf(el);
    tokens[ctxOf(el)] = token;
    say(el, '');
    // The form reads the token from its "captcha_v2" row (as reCAPTCHA v2 always did), the tracking box too (-1).
    try { if (typeof recaptchaSuccessEfb === 'function') recaptchaSuccessEfb(token, fid); } catch (e) {}
    try { el.dispatchEvent(new CustomEvent('efb:captcha', { bubbles: true, detail: { ctx: ctxOf(el), formId: fid, token: token } })); } catch (e) {}
  }
  function expired(el) {
    delete tokens[ctxOf(el)];
    say(el, T('captchaExpired', 'The check expired. Please complete it again.'));
    try { if (typeof recaptchaExpiredEfb === 'function') recaptchaExpiredEfb(fidOf(el)); } catch (e) {}
  }
  function failed(el) {
    delete tokens[ctxOf(el)];
    say(el, T('captchaFailed', 'The security check could not be completed. Please try again.'));
  }

  /** Draws one widget; false while its service's script is not there yet. */
  function render(el) {
    if (!el || el.getAttribute('data-efb-rendered') === '1' || el.getAttribute('data-grecaptcha-widget-id')) return true;
    var p = el.getAttribute('data-provider');
    var key = el.getAttribute('data-sitekey') || '';
    if (!key) return true;
    var id;
    var cbs = { callback: function (t) { solved(el, t); }, 'expired-callback': function () { expired(el); }, 'error-callback': function () { failed(el); } };
    try {
      if (p === 'hcaptcha') {
        if (!window.hcaptcha || typeof window.hcaptcha.render !== 'function') return false;
        id = window.hcaptcha.render(el, Object.assign({ sitekey: key, hl: lang(el), size: narrow(el) ? 'compact' : 'normal' }, cbs));
      } else if (p === 'turnstile') {
        if (!window.turnstile || typeof window.turnstile.render !== 'function') return false;
        id = window.turnstile.render(el, Object.assign({ sitekey: key, action: ACTION, language: lang(el) || 'auto', size: narrow(el) ? 'flexible' : 'normal', 'timeout-callback': function () { expired(el); } }, cbs));
      } else if (p === 'recaptcha_v2') {
        if (!window.grecaptcha || typeof window.grecaptcha.render !== 'function') return false;
        id = window.grecaptcha.render(el, Object.assign({ sitekey: key }, cbs));
        el.setAttribute('data-grecaptcha-widget-id', String(id));
      } else if (p === 'recaptcha_v3') {
        el.setAttribute('data-efb-rendered', '1');
        return true;
      } else {
        return true;
      }
    } catch (e) {
      failed(el);
      el.setAttribute('data-efb-rendered', '1');
      return true;
    }
    el.setAttribute('data-efb-rendered', '1');
    el.setAttribute('data-efb-widget-id', String(id));
    return true;
  }

  function boot() {
    var waiting = 0;
    var list = document.querySelectorAll('.efb-captcha[data-provider]');
    for (var i = 0; i < list.length; i++) if (!render(list[i])) waiting++;
    if (waiting && tries < 240) { tries++; setTimeout(boot, tries < 30 ? 100 : 500); }
  }

  function v3Marker(fid, ctx) {
    var sel = '.efb-captcha[data-provider="recaptcha_v3"]' + (fid > 0 ? '[data-formid="' + fid + '"]' : '[data-ctx="' + String(ctx || '').replace(/[^a-z0-9_]/g, '') + '"]');
    return document.querySelector(sel);
  }

  /** A fresh reCAPTCHA v3 token for the action (Promise; rejects after timeoutMs). */
  function v3Token(key, timeoutMs) {
    return new Promise(function (resolve, reject) {
      var done = false;
      var timer = setTimeout(function () { if (!done) { done = true; reject(new Error('timeout')); } }, timeoutMs || 10000);
      var go = function () {
        if (done) return;
        if (!window.grecaptcha || typeof window.grecaptcha.ready !== 'function') { setTimeout(go, 150); return; }
        window.grecaptcha.ready(function () {
          try {
            window.grecaptcha.execute(key, { action: ACTION }).then(function (t) {
              if (done) return; done = true; clearTimeout(timer);
              if (t) resolve(t); else reject(new Error('empty'));
            }, function (e) { if (done) return; done = true; clearTimeout(timer); reject(e || new Error('execute')); });
          } catch (e) { if (!done) { done = true; clearTimeout(timer); reject(e); } }
        });
      };
      go();
    });
  }

  /** Before a form request: a v3 form gets its token in data.valid; false = do not send (the message is shown). */
  async function beforeSend(data, fid) {
    var el = v3Marker(Number(fid) || 0);
    if (!el) return true;
    try {
      data.valid = await v3Token(el.getAttribute('data-sitekey'), 10000);
      say(el, '');
      return !!data.valid;
    } catch (e) {
      failed(el);
      return false;
    }
  }

  /** After a refused answer: the spent token is gone, the widget asks again. */
  function reset(fid) {
    fid = Number(fid) || -1;
    var list = document.querySelectorAll('.efb-captcha[data-provider][data-formid="' + fid + '"]');
    for (var i = 0; i < list.length; i++) {
      var el = list[i], p = el.getAttribute('data-provider');
      delete tokens[ctxOf(el)];
      try {
        if (p === 'hcaptcha' && window.hcaptcha && el.hasAttribute('data-efb-widget-id')) window.hcaptcha.reset(el.getAttribute('data-efb-widget-id'));
        else if (p === 'turnstile' && window.turnstile && el.hasAttribute('data-efb-widget-id')) window.turnstile.reset(el.getAttribute('data-efb-widget-id'));
        else if (p === 'recaptcha_v2' && window.grecaptcha && el.hasAttribute('data-grecaptcha-widget-id')) window.grecaptcha.reset(Number(el.getAttribute('data-grecaptcha-widget-id')));
      } catch (e) {}
    }
    try { if (typeof recaptchaExpiredEfb === 'function') recaptchaExpiredEfb(fid); } catch (e) {}
  }

  /** P20: the token of a widget outside a form (a v3 context asks Google now). Promise of '' when none. */
  async function token(ctx) {
    ctx = String(ctx || '');
    var el = v3Marker(0, ctx);
    if (el) { try { return await v3Token(el.getAttribute('data-sitekey'), 10000); } catch (e) { failed(el); return ''; } }
    return tokens[ctx] || '';
  }

  function isInvisible(fid) { return !!v3Marker(Number(fid) || 0); }

  window.EFBCaptcha = { boot: boot, render: render, reset: reset, beforeSend: beforeSend, isInvisible: isInvisible, token: token, v3Token: v3Token, message: say };

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot); else boot();
  // Forms drawn later (a popup, a page builder's preview) bring their widgets with them.
  try {
    if (typeof MutationObserver !== 'undefined') {
      new MutationObserver(function (muts) {
        for (var i = 0; i < muts.length; i++) {
          var n = muts[i].addedNodes;
          for (var j = 0; j < n.length; j++) {
            if (n[j].nodeType === 1 && (n[j].matches && n[j].matches('.efb-captcha[data-provider]') || n[j].querySelector && n[j].querySelector('.efb-captcha[data-provider]'))) { tries = 0; boot(); return; }
          }
        }
      }).observe(document.documentElement, { childList: true, subtree: true });
    }
  } catch (e) {}
})();
