/**
 * F12b-C (§4): several forms and boxes on one page, captcha-efb.js in a vm and the PHP markup.
 *
 *  - two v2 forms: two widgets, each its own token; a refused answer of one resets that form only; an error is
 *    written in its own box; the other's row stays;
 *  - v2 + v2 invisible + v3 on one page: each gets its own token in its own way, nothing shared;
 *  - two boxes of one add-on (two forms): two widgets, two tokens;
 *  - outside a form: tracker, reply_1, reply_2 and a form: reset(-1, 'reply_1') resets that reply box only,
 *    reset(-1) every box outside a form, never the form's; token('reply_2') is reply_2's;
 *  - six boxes: every box has its own widget id;
 *  - PHP: Captcha::box_html() for two v2 forms, a v3 form and two add-on boxes: unique ids, one Google script whose
 *    URL carries the v3 key (one script per provider), the add-on's script and adapter once; no #gRecaptcha /
 *    #step-1-efb-msg in core's PHP or the tracking box any more.
 *
 * Run: node tests/test-captcha-multiform.js
 */
'use strict';
const fs = require('fs');
const path = require('path');
const { execFileSync } = require('child_process');
const { page, tick, checker, fakeGoogle } = require('./captcha-runtime-lib.js');
const c = checker();
const ROOT = path.join(__dirname, '..');

(async () => {
  {
    const g = fakeGoogle();
    const P = page([{ provider: 'recaptcha_v2', key: 'k2', fid: 11 }, { provider: 'recaptcha_v2', key: 'k2', fid: 12 }], { before: (w) => { w.grecaptcha = g; } });
    c.t('two v2 forms: two widgets', g.widgets.length, 2);
    g.widgets[1].callback('T-12');
    c.t('solving form 12 gives form 12 its row only', P.calls.rows, [{ tok: 'T-12', fid: 12 }]);
    g.widgets[0]['error-callback']();
    c.t('an error of form 11 is written in form 11\'s box only', [P.all[0].box.msg.textContent, P.all[1].box.msg.textContent], ['FAILED', '']);
    P.C.reset(11);
    c.t('reset(11) resets form 11\'s widget only (form 12 keeps its token)', [g.resets, P.calls.expired], [[0], [11]]);
  }
  {
    const g = fakeGoogle();
    const P = page([{ provider: 'recaptcha_v2', key: 'k2', fid: 21 }, { provider: 'recaptcha_v2i', key: 'ki', fid: 22, kind: 'invisible' }, { provider: 'recaptcha_v3', key: 'k3', fid: 23, cfg: { action: 'efb_submit' } }], { before: (w) => { w.grecaptcha = g; } });
    c.t('v2 + v2 invisible + v3: two widgets (v3 has none), each with its own key', g.widgets.map((o) => [o.sitekey, o.size]), [['k2', 'normal'], ['ki', 'invisible']].map((x, i) => (i === 0 ? ['k2', 'compact'] : x)));
    const d3 = {};
    await P.C.beforeSend(d3, 23);
    const d2 = {};
    const p2 = P.C.beforeSend(d2, 22);
    await tick();
    g.widgets[1].callback('T-22');
    await p2;
    const d1 = {};
    await P.C.beforeSend(d1, 21);
    c.t('each form its own token: v3 from execute(key), v2 invisible from its widget, v2 through its row (untouched here)', [d3.valid, d2.valid, d1.valid], ['V3-1', 'T-22', undefined]);
    c.t('needs a row: v2 yes, v2 invisible and v3 no', [P.C.needsWidgetToken(21), P.C.needsWidgetToken(22), P.C.needsWidgetToken(23)], [true, false, false]);
  }
  {
    const P = page([{ provider: 'fakecap', fid: 31 }, { provider: 'fakecap', fid: 32 }]);
    const cbs = [];
    let n = 0;
    P.C.provider('fakecap', { ready: () => true, render: (e, cfg, cb) => { cbs.push(cb); return 'fw' + (++n); } });
    c.t('one add-on, two forms: two widgets with their own ids', P.all.map((e) => e.attrs['data-efb-widget-id']), ['fw1', 'fw2']);
    cbs[0].solved('A-31'); cbs[1].solved('A-32');
    c.t('two tokens, each to its form', P.calls.rows, [{ tok: 'A-31', fid: 31 }, { tok: 'A-32', fid: 32 }]);
  }
  {
    const g = fakeGoogle();
    const P = page([{ provider: 'recaptcha_v2', fid: -1, ctx: 'tracker' }, { provider: 'recaptcha_v2', fid: -1, ctx: 'reply_1' }, { provider: 'recaptcha_v2', fid: -1, ctx: 'reply_2' }, { provider: 'recaptcha_v2', fid: 41 }], { before: (w) => { w.grecaptcha = g; } });
    g.widgets.forEach((w, i) => w.callback('T' + i));
    P.C.reset(-1, 'reply_1');
    c.t('reset(-1, "reply_1"): that reply box only', g.resets, [1]);
    c.t('token("reply_2") is reply_2\'s, token("tracker") the tracker\'s', [await P.C.token('reply_2'), await P.C.token('tracker')], ['T2', 'T0']);
    c.t('token("reply_1") after its reset: none', await P.C.token('reply_1'), '');
    P.C.reset(-1);
    c.t('reset(-1): every box outside a form, never the form\'s', g.resets, [1, 0, 1, 2]);
  }
  {
    const g = fakeGoogle();
    const boxes = [];
    for (let i = 0; i < 6; i++) boxes.push({ provider: i % 2 ? 'recaptcha_v2' : 'fakecap', fid: 50 + i });
    const P = page(boxes, { before: (w) => { w.grecaptcha = g; } });
    let n = 0;
    P.C.provider('fakecap', { ready: () => true, render: () => 'f' + (++n) });
    const ids = P.all.map((e) => e.attrs['data-efb-widget-id']);
    c.t('six boxes, two services: six widget ids, none shared', [ids.length, new Set(ids.map((x, i) => P.all[i].attrs['data-provider'] + ':' + x)).size], [6, 6]);
  }

  /* PHP: markup and scripts of one page */
  const php = `
define('ABSPATH', '/'); define('EMSFB_PLUGIN_URL', 'https://site.test/wp-content/plugins/easy-form-builder/');
$GLOBALS['reg'] = array(); $GLOBALS['enq'] = array();
class WS { public $registered = array(); }
$GLOBALS['ws'] = new WS();
function wp_scripts() { return $GLOBALS['ws']; }
function wp_register_script($h, $src) { $GLOBALS['ws']->registered[$h] = (object) array('src' => $src, 'deps' => array()); }
function wp_enqueue_script($h) { $GLOBALS['enq'][$h] = true; }
function wp_script_is($h) { return isset($GLOBALS['enq'][$h]); }
function wp_localize_script() {}
function apply_filters($h, $v) { return $v; }
function do_action($h, $r = null) {}
function home_url() { return 'https://site.test'; }
function get_locale() { return 'ar'; }
function wp_json_encode($v) { return json_encode($v); }
require '${path.join(ROOT, 'includes/class-Emsfb-captcha.php').replace(/\\/g, '/')}';
require '${path.join(ROOT, 'includes/class-Emsfb-spam.php').replace(/\\/g, '/')}';
class Fake_Add extends \\Emsfb\\Captcha\\Provider {
  public function id() { return 'fakecap'; } public function label() { return 'F'; } public function addon_key() { return 'AdnCTS'; }
  public function settings_schema() { return array(); }
  public function widget(array $c) { return array('config' => array('k' => 'fk')); }
  public function scripts(array $c) { return array(array('handle' => 'efb-cp-fakecap', 'src' => 'https://fake.example/api.js', 'adapter' => 'https://site.test/a.js')); }
  public function verify($t, array $c) { return array('ok' => false); }
}
\\Emsfb\\Captcha\\Registry::boot();
\\Emsfb\\Captcha\\Registry::add(new Fake_Add());
$S = array('siteKey' => 'v2-site-key-xyz', 'secretKey' => 'v2-secret-xyz-123', 'rc3SiteKey' => 'v3-site-key-xyz', 'rc3SecretKey' => 'v3-secret-xyz-123');
$h = '';
$h .= \\Emsfb\\Captcha::box_html('recaptcha_v2', 1, 'form', 1, array(), $S);
$h .= \\Emsfb\\Captcha::box_html('recaptcha_v2', 2, 'form', 1, array(), $S);
$h .= \\Emsfb\\Captcha::box_html('recaptcha_v3', 3, 'form', 1, array(), $S);
$h .= \\Emsfb\\Captcha::box_html('fakecap', 4, 'form', 1, array(), $S);
$h .= \\Emsfb\\Captcha::box_html('fakecap', 5, 'form', 1, array(), $S);
$h .= \\Emsfb\\Captcha::box_html('recaptcha_v2', 0, 'tracker', 0, array(), $S);
echo json_encode(array('html' => $h, 'scripts' => array_map(function ($o) { return $o->src; }, $GLOBALS['ws']->registered)));
`;
  const out = JSON.parse(execFileSync('php', ['-r', php]).toString());
  const ids = (out.html.match(/ id="[^"]+"/g) || []).map((x) => x.slice(5, -1));
  c.t('PHP: every id on the page is unique (boxes, labels, live regions)', [ids.length >= 18, new Set(ids).size === ids.length], [true, true]);
  c.t('PHP: no #gRecaptcha and no #step-1-efb-msg', /id="gRecaptcha"|step-1-efb-msg/.test(out.html), false);
  const handles = Object.keys(out.scripts);
  c.t('PHP: one Google script (efb-recaptcha) whose URL carries the v3 key, one add-on script, its adapter, the runtime', [handles.filter((h) => h === 'efb-recaptcha').length, out.scripts['efb-recaptcha'], !!out.scripts['efb-cp-fakecap'], !!out.scripts['efb-cp-fakecap-adapter'], !!out.scripts['efb-captcha-js']],
    [1, 'https://www.google.com/recaptcha/api.js?hl=ar&render=v3-site-key-xyz', true, true, true]);
  c.t('PHP: the secrets are nowhere in the markup', /secret-xyz/.test(out.html), false);
  const fb = fs.readFileSync(path.join(ROOT, 'includes/class-Emsfb-formbuilder.php'), 'utf8');
  const pub = fs.readFileSync(path.join(ROOT, 'includes/class-Emsfb-public.php'), 'utf8');
  const coreJs = fs.readFileSync(path.join(ROOT, 'public/assets/js/core-efb.js'), 'utf8');
  c.t('core PHP: the form box and the tracking box have no fixed id any more', [/id="gRecaptcha"|id="step-1-efb-msg"/.test(fb), /<div id="gRecaptcha"/.test(pub)], [false, false]);
  c.t('core JS: no lookup of #gRecaptcha, no grecaptcha.render with a site-wide key', [coreJs.indexOf("'#gRecaptcha'") === -1, coreJs.indexOf('grecaptcha.render(el, {\n        sitekey: sitekye_emsFormBuilder') === -1], [true, true]);
  c.done();
})();
