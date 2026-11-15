/**
 * F12b-F: the Turnstile adapter (vendor/turnstile/assets/turnstile-adapter-efb.js) on EFBCaptcha, in a vm with a fake
 * window.turnstile: the box's options reach turnstile.render (theme, size auto → flexible at 375 px, appearance,
 * execution on submit, language, action, retry, refresh-expired auto); a form set to "when the form is sent" gets
 * its token from turnstile.execute() at beforeSend; reset / remove reach turnstile; two Turnstile forms on one page
 * have their own widgets and tokens; the adapter loaded before the runtime registers on DOMContentLoaded.
 *
 * Run: node tests/addons/test-turnstile-adapter.js
 */
'use strict';
const { page, tick, checker, adapter } = require('../captcha-runtime-lib.js');
const c = checker();
const SRC = adapter('turnstile', 'turnstile-adapter-efb.js');
const fake = () => { const t = { r: [], ex: [], resets: [], removes: [] }; t.render = (e, o) => { t.r.push(o); return 'ts' + t.r.length; }; t.execute = (id) => { t.ex.push(id); }; t.reset = (id) => t.resets.push(id); t.remove = (id) => t.removes.push(id); return t; };

(async () => {
  const T = fake();
  const P = page([
    { provider: 'turnstile', key: '1x00000000000000000000AA', fid: 7, cfg: { theme: 'dark', size: 'auto', appearance: 'interaction-only', lang: 'fa', action: 'contact', retry: 'never' }, width: 280 },
    { provider: 'turnstile', key: '1x00000000000000000000AA', fid: 8, cfg: { theme: 'auto', size: 'compact', appearance: 'execute', ex: 1, lang: 'ar', action: 'efb_submit' } },
  ], { width: 375, adapters: [SRC], before: (w) => { w.turnstile = T; } });
  P.run();
  const o = T.r[0] || {};
  c.t('render: site key, theme dark, size auto → flexible at 375 px, appearance, language, action, retry, refresh-expired auto', [o.sitekey, o.theme, o.size, o.appearance, o.execution, o.language, o.action, o.retry, o['refresh-expired']], ['1x00000000000000000000AA', 'dark', 'flexible', 'interaction-only', 'render', 'fa', 'contact', 'never', 'auto']);
  const o2 = T.r[1] || {};
  c.t('a form checked when sent: execution execute, size compact, theme auto', [o2.execution, o2.size, o2.theme], ['execute', 'compact', 'auto']);
  c.t('two forms, two widgets', [P.all[0].attrs['data-efb-widget-id'], P.all[1].attrs['data-efb-widget-id']], ['ts1', 'ts2']);
  const d = {};
  const pr = P.C.beforeSend(d, 8);
  await tick();
  c.t('beforeSend runs turnstile.execute on that widget only', T.ex, ['ts2']);
  o2.callback('TS-TOKEN-8');
  c.t('… and sends its token', [await pr, d.valid], [true, 'TS-TOKEN-8']);
  o.callback('TS-TOKEN-7');
  c.t('the other form\'s token goes to its own row', P.calls.rows, [{ tok: 'TS-TOKEN-7', fid: 7 }]);
  o['timeout-callback']();
  c.t('timeout: the live region says expired', P.all[0].box.msg.textContent, 'EXPIRED');
  P.C.reset(7);
  c.t('reset(7) resets ts1 only', T.resets, ['ts1']);
  c.t('the adapter is under 2 KB', Buffer.byteLength(SRC) < 2048, true);
  c.done();
})();
