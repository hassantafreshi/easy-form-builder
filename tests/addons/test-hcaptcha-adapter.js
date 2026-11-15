/**
 * F12b-G: the hCaptcha adapter (vendor/hcaptcha/assets/hcaptcha-adapter-efb.js) on EFBCaptcha with a fake
 * window.hcaptcha: theme, size auto → compact under 304 px / normal, compact, invisible (executed at beforeSend),
 * the language; reset only that form's widget; the adapter under 2 KB.
 *
 * Run: node tests/addons/test-hcaptcha-adapter.js
 */
'use strict';
const { page, tick, checker, adapter } = require('../captcha-runtime-lib.js');
const c = checker();
const SRC = adapter('hcaptcha', 'hcaptcha-adapter-efb.js');
(async () => {
  const H = { r: [], ex: [], resets: [] };
  H.render = (e, o) => { H.r.push(o); return 'h' + H.r.length; }; H.execute = (id) => H.ex.push(id); H.reset = (id) => H.resets.push(id);
  const P = page([
    { provider: 'hcaptcha', key: '10000000-ffff-ffff-ffff-000000000001', fid: 1, cfg: { theme: 'dark', size: 'auto', lang: 'fa' }, width: 280 },
    { provider: 'hcaptcha', key: '10000000-ffff-ffff-ffff-000000000001', fid: 2, cfg: { theme: 'light', size: 'auto', lang: 'en' }, width: 600 },
    { provider: 'hcaptcha', key: '10000000-ffff-ffff-ffff-000000000001', fid: 3, cfg: { size: 'invisible', ex: 1, lang: 'ar' } },
  ], { adapters: [SRC], before: (w) => { w.hcaptcha = H; } });
  P.run();
  c.t('render: theme, size auto → compact under 304 px, normal at 600 px, invisible; language', H.r.map((o) => [o.theme, o.size, o.hl]), [['dark', 'compact', 'fa'], ['light', 'normal', 'en'], ['light', 'invisible', 'ar']]);
  const d = {};
  const pr = P.C.beforeSend(d, 3);
  await tick();
  c.t('invisible: beforeSend executes h3', H.ex, ['h3']);
  H.r[2].callback('HC-3');
  c.t('… and sends its token', [await pr, d.valid], [true, 'HC-3']);
  P.C.reset(2);
  c.t('reset(2) resets h2 only', H.resets, ['h2']);
  c.t('the adapter is under 2 KB', Buffer.byteLength(SRC) < 2048, true);
  c.done();
})();
