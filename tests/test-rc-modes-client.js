/**
 * F12b-B (§1): the four Google modes in the browser (captcha-efb.js, a fake grecaptcha in a vm).
 *
 *  - v2 checkbox: its own site key, theme (dark / auto), size auto → compact under 304 px / normal, or fixed;
 *  - v2 invisible: rendered invisible with its badge (hidden → Google's own position, the notice is in the page);
 *    beforeSend() executes that widget and waits for its callback; no row is needed; a 10-second limit;
 *  - v3: beforeSend() asks for a token with the form's own action (rc3_action);
 *  - v3 → v2: challenge(form) renders the v2 checkbox with the fallback key in the same box, says "please confirm" in
 *    its live region and moves the focus there; then the form needs the row and sends captcha_kind v2; a form without
 *    the fallback is not changed;
 *  - core-efb.js: an efb_captcha_challenge answer calls EFBCaptcha.challenge() instead of resetting.
 *
 * Run: node tests/test-rc-modes-client.js
 */
'use strict';
const fs = require('fs');
const path = require('path');
const { page, tick, checker, fakeGoogle } = require('./captcha-runtime-lib.js');
const c = checker();
const core = fs.readFileSync(path.join(__dirname, '..', 'public/assets/js/core-efb.js'), 'utf8');

(async () => {
  {
    const g = fakeGoogle();
    const P = page([
      { provider: 'recaptcha_v2', key: 'v2-a', fid: 1, cfg: { theme: 'dark', size: 'auto' }, width: 280 },
      { provider: 'recaptcha_v2', key: 'v2-b', fid: 2, cfg: { theme: 'light', size: 'auto' }, width: 600 },
      { provider: 'recaptcha_v2', key: 'v2-c', fid: 3, cfg: { size: 'compact' }, width: 600 },
    ], { before: (w) => { w.grecaptcha = g; } });
    c.t('v2: each box its own key; theme; size auto → compact under 304 px, normal otherwise; compact fixed',
      g.widgets.map((o) => [o.sitekey, o.theme, o.size]), [['v2-a', 'dark', 'compact'], ['v2-b', 'light', 'normal'], ['v2-c', 'light', 'compact']]);
    c.t('v2: widget ids kept as F12 did (data-grecaptcha-widget-id)', P.all.map((e) => e.attrs['data-grecaptcha-widget-id']), ['0', '1', '2']);
  }
  {
    const g = fakeGoogle();
    const P = page([{ provider: 'recaptcha_v2i', key: 'v2i-key', fid: 4, kind: 'invisible', cfg: { badge: 'hidden', theme: 'auto' } }, { provider: 'recaptcha_v2i', key: 'v2i-key', fid: 5, kind: 'invisible', cfg: { badge: 'inline' } }], { before: (w) => { w.grecaptcha = g; } });
    c.t('v2 invisible: size invisible; badge hidden → Google\'s default place (the notice is on the page), inline kept', g.widgets.map((o) => [o.sitekey, o.size, o.badge]), [['v2i-key', 'invisible', 'bottomright'], ['v2i-key', 'invisible', 'inline']]);
    c.t('v2 invisible: no row needed, invisible', [P.C.needsWidgetToken(4), P.C.isInvisible(4)], [false, true]);
    const d = {};
    const pr = P.C.beforeSend(d, 4);
    await tick();
    c.t('beforeSend executes that widget only', g.execs, [0]);
    g.widgets[0].callback('V2I-TOKEN');
    c.t('… and sends its token in data.valid (no captcha_v2 row)', [await pr, d.valid, P.calls.rows.length], [true, 'V2I-TOKEN', 0]);
    const pend = P.C.beforeSend({}, 5);
    await tick();
    P.timers.filter((x) => x.ms === 10000).forEach((x) => x.fn());
    c.t('a challenge never solved: 10-second limit, not sent, message in its box', [await pend, P.all[1].box.msg.textContent], [false, 'FAILED']);
  }
  {
    const g = fakeGoogle();
    const P = page([{ provider: 'recaptcha_v3', key: 'v3-key', fid: 6, width: 600, cfg: { action: 'contact_us', fb: 'v2-fallback-key' } }, { provider: 'recaptcha_v3', key: 'v3-key', fid: 7, cfg: { action: 'efb_submit' } }], { before: (w) => { w.grecaptcha = g; } });
    const d = {};
    c.t('v3: beforeSend gets a token for the form\'s own action', [await P.C.beforeSend(d, 6), d.valid, g.v3[0]], [true, 'V3-1', ['v3-key', 'contact_us']]);
    c.t('v3: no widget drawn', g.widgets.length, 0);
    c.t('v3 → v2: challenge() on a form without the fallback changes nothing', P.C.challenge(7), false);
    c.t('v3 → v2: challenge() on a form with the fallback', P.C.challenge(6), true);
    c.t('… renders the v2 checkbox with the fallback key in the same box (unhidden)', [g.widgets.length, g.widgets[0] && g.widgets[0].sitekey, g.widgets[0] && g.widgets[0].size, 'hidden' in P.all[0].attrs], [1, 'v2-fallback-key', 'normal', false]);
    c.t('… says so in its live region and moves the focus to the box', [P.all[0].box.msg.textContent, P.all[0].box.focused], ['CHALLENGE', 1]);
    c.t('… the form now needs the row', P.C.needsWidgetToken(6), true);
    g.widgets[0].callback('V2-FB-TOKEN');
    c.t('… the solved checkbox gives the form its row', P.calls.rows[0], { tok: 'V2-FB-TOKEN', fid: 6 });
    const d2 = {};
    c.t('… and beforeSend marks the request captcha_kind v2 (no new v3 token)', [await P.C.beforeSend(d2, 6), d2.captcha_kind, d2.valid, g.v3.length], [true, 'v2', undefined, 1]);
    c.t('the other v3 form on the page is untouched', [P.C.needsWidgetToken(7), 'hidden' in P.all[1].attrs], [false, true]);
  }
  /* core-efb.js wiring */
  const resp = core.slice(core.indexOf('async function response_fill_form_efb('), core.indexOf('async function response_fill_form_efb(') + 1600);
  c.t('core: efb_captcha_challenge → EFBCaptcha.challenge(form), not a reset', /res\.data\.code === 'efb_captcha_challenge' && window\.EFBCaptcha && typeof window\.EFBCaptcha\.challenge === 'function' && window\.EFBCaptcha\.challenge\(form_id\)\) \{[\s\S]{0,80}\} else if \(!\(res && res\.data && res\.data\.success == true\) && window\.EFBCaptcha/.test(resp), true);
  c.done();
})();
