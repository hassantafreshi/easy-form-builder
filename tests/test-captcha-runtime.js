/**
 * F12b-A (§2 browser side, §4 rules 5–7): window.EFBCaptcha with a fake add-on adapter, in a vm.
 *
 *  - EFBCaptcha.provider(id, adapter): a malformed id, an adapter without render() or a second one for the same id is
 *    refused; registering renders the boxes that waited for it (no global callback, no inline script);
 *  - render(): the box's public config reaches the adapter (site key, theme, language, narrow width), the widget id is
 *    kept, a solved token goes to the form's row and fires efb:captcha, expired / error say so in the box's live region
 *    (a form's own failure text, cp_msg_failed, wins);
 *  - lazy: with IntersectionObserver a widget renders when its box comes into view; beforeSend forces it;
 *  - an adapter that executes on submit (cfg.ex): beforeSend asks it for the token, 10-second limit;
 *  - a form drawn after the footer: the provider's script and adapter are injected from the config (https only) when
 *    they are not on the page; never twice, never when the page already has them;
 *  - theme auto follows prefers-color-scheme; reset() reaches the adapter.
 *
 * Run: node tests/test-captcha-runtime.js
 */
'use strict';
const { page, tick, checker } = require('./captcha-runtime-lib.js');
const c = checker();

(async () => {
  /* registration */
  {
    const P = page([{ provider: 'fakecap', fid: 4, cfg: { theme: 'dark', lang: 'fa' } }], { width: 375 });
    P.run();
    c.t('before its adapter: not rendered, waiting', P.all[0].attrs['data-efb-rendered'], undefined);
    const got = [];
    const A = { ready: () => true, render: (e, cfg, cb) => { got.push(cfg); A.cb = cb; return 'w1'; }, reset: (id) => got.push('reset:' + id) };
    c.t('a malformed id is refused', P.C.provider('Bad Id', A), false);
    c.t('an adapter without render() is refused', P.C.provider('other', { ready: () => true }), false);
    c.t('the adapter registers', P.C.provider('fakecap', A), true);
    c.t('a second adapter for the same id is refused', P.C.provider('fakecap', A), false);
    c.t('registering renders the waiting box with its config: key, theme, language, narrow at 375 px', [got[0].k, got[0].theme, got[0].lang, got[0].narrow, P.all[0].attrs['data-efb-widget-id']], ['fakecap-site', 'dark', 'fa', true, 'w1']);
    A.cb.solved('FAKE-TOKEN-1');
    c.t('solved: the token goes to form 4 (captcha_v2 row) and efb:captcha fires', [P.calls.rows[0], P.all[0].events[0] && P.all[0].events[0].detail.token], [{ tok: 'FAKE-TOKEN-1', fid: 4 }, 'FAKE-TOKEN-1']);
    c.t('a visible widget form needs its row; it is not invisible', [P.C.needsWidgetToken(4), P.C.isInvisible(4)], [true, false]);
    A.cb.expired();
    c.t('expired: the live region says so, the row is dropped', [P.all[0].box.msg.textContent, P.calls.expired], ['EXPIRED', [4]]);
    A.cb.error();
    c.t('error: the live region says so', P.all[0].box.msg.textContent, 'FAILED');
    P.C.reset(4);
    c.t('reset(4) reaches the adapter with the widget id', got.indexOf('reset:w1') !== -1, true);
  }
  /* the form's own failure text */
  {
    const P = page([{ provider: 'fakecap', fid: 5, cfg: { mf: 'لطفاً دوباره امتحان کنید' } }]);
    const A = { ready: () => true, render: (e, cfg, cb) => { A.cb = cb; return 1; } };
    P.C.provider('fakecap', A);
    A.cb.error();
    c.t('cp_msg_failed of the form is the text of a failure', P.all[0].box.msg.textContent, 'لطفاً دوباره امتحان کنید');
  }
  /* lazy rendering */
  {
    const P = page([{ provider: 'fakecap', fid: 6 }], { io: true });
    let n = 0;
    P.C.provider('fakecap', { ready: () => true, render: () => { n++; return 'w'; } });
    c.t('lazy: a box out of view is observed, not rendered', [n, P.io.observed.length], [0, 1]);
    P.show(P.all[0]);
    c.t('lazy: rendered when it comes into view', n, 1);
    const Q = page([{ provider: 'fakecap', fid: 7, cfg: { ex: 1 } }], { io: true });
    const execs = [];
    Q.C.provider('fakecap', { ready: () => true, render: () => 'w7', execute: (id) => { execs.push(id); return Promise.resolve('EXEC-TOKEN'); } });
    const d = {};
    const ok = await Q.C.beforeSend(d, 7);
    c.t('execute-on-submit (cfg.ex): beforeSend renders it out of view and asks the adapter for the token', [ok, d.valid, execs], [true, 'EXEC-TOKEN', ['w7']]);
    c.t('… such a form needs no row before sending', Q.C.needsWidgetToken(7), false);
  }
  /* the 10-second limit of an adapter that never answers */
  {
    const P = page([{ provider: 'fakecap', fid: 8, cfg: { ex: 1 } }]);
    P.C.provider('fakecap', { ready: () => true, render: () => 'w8', execute: () => new Promise(() => {}) });
    const pend = P.C.beforeSend({}, 8);
    await tick();
    c.t('a 10-second limit is set', P.timers.some((x) => x.ms === 10000), true);
    P.timers.filter((x) => x.ms === 10000).forEach((x) => x.fn());
    c.t('after 10 seconds: not sent, the message is in the box', [await pend, P.all[0].box.msg.textContent], [false, 'FAILED']);
  }
  /* a form drawn after the footer: scripts from the config */
  {
    const P = page([{ provider: 'latecap', fid: 9, cfg: { src: 'https://late.example/api.js?render=explicit', ad: 'https://site.test/wp-content/plugins/x/late-adapter.js?ver=1' } }]);
    P.run();
    c.t('its script and adapter are injected once (after a short wait for deferred scripts)', P.added, ['https://late.example/api.js?render=explicit', 'https://site.test/wp-content/plugins/x/late-adapter.js?ver=1']);
    P.C.scan(); P.run();
    c.t('never twice', P.added.length, 2);
    const Q = page([{ provider: 'latecap', fid: 9, cfg: { src: 'https://late.example/api.js?render=explicit' } }], { scripts: ['https://late.example/api.js?render=explicit&x=1'] });
    Q.run();
    c.t('not injected when the page has it already', Q.added, []);
    const R = page([{ provider: 'latecap', fid: 9, cfg: { src: 'http://insecure.example/api.js' } }]);
    R.run();
    c.t('only https', R.added, []);
  }
  /* scan() for boxes added later; theme auto */
  {
    const P = page([], { matchDark: true });
    const got = [];
    P.C.provider('fakecap', { ready: () => true, render: (e, cfg) => { got.push(cfg.dark); return 1; } });
    P.add({ provider: 'fakecap', fid: 10, cfg: { theme: 'auto' } });
    P.C.scan();
    c.t('scan() renders a box added later; theme auto follows prefers-color-scheme: dark', got, [true]);
  }
  c.done();
})();
