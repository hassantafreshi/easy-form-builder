/**
 * AutoPilot A6: "window.intlTelInput is not a function" on a public page with a mobile field.
 *
 * The PHP renderer prints the mobile field's init as an inline <script> inside the form markup and
 * calls window.intlTelInput 1 s later, while intlTelInput.min-efb.js is a footer script with no
 * relation to it. A cache plugin that delays or combines footer scripts (LiteSpeed "delay JS",
 * Cloudflare Rocket Loader) loads the library later than that, and the call throws. The builder /
 * JS renderer (load_intlTelInput_efb in pro_els-efb.js) has the same race.
 *
 * Reproduction: the real PHP output (Formbuilder::create_intlTelInput_efb through reflection) and
 * the real pro_els-efb.js run in Chromium with the library injected 2.5 s late, as a delaying cache
 * plugin does. The init must wait for it instead of throwing, then attach (.iti wrapper).
 * Also checks that the public enqueue declares the library as a dependency of the core script.
 *
 * Run: node tests/test-ap-a6-intltelinput-late.js
 */
'use strict';
const fs = require('fs');
const path = require('path');
const { execFileSync } = require('child_process');
const { chromium } = require('playwright');
const { makeChecker, ROOT } = require('./ap-builder-harness.js');
const c = makeChecker();
const LIB = path.join(ROOT, 'includes/admin/assets/js/intlTelInput.min-efb.js');

const phpOut = JSON.parse(execFileSync('php', ['-r', `
  namespace { define('ABSPATH','/'); define('EMSFB_PLUGIN_URL','https://site.test/'); define('EMSFB_PLUGIN_VERSION','t');
    function get_option($k,$d=false){return $d;} function esc_attr($v){return htmlspecialchars((string)$v,ENT_QUOTES);} function add_action(){}
    class F { function text_efb($k){ $o=[]; foreach($k as $x) $o[$x]='T:'.$x; return $o; } } function get_efbFunction(){ return new F(); }
    require '${path.join(ROOT, 'includes/class-Emsfb-formbuilder.php').replace(/\\/g, '/')}';
    $r = new ReflectionClass('Emsfb\\\\Formbuilder'); $fb = $r->newInstanceWithoutConstructor();
    $m = $r->getMethod('create_intlTelInput_efb'); $m->setAccessible(true);
    $vj = (object)['id_'=>'mob1','name'=>'Phone','amount'=>2,'type'=>'mobile','required'=>0,'message'=>'','value'=>'','classes'=>'','el_border_color'=>'','el_height'=>'','el_text_color'=>'','c_c'=>[]];
    echo json_encode($m->invoke($fb, 'mob1', $vj, true, 'rounded', 7)); }
`]).toString());

async function launch() {
  try { return await chromium.launch(); } catch (e) {
    const base = process.env.PLAYWRIGHT_BROWSERS_PATH || '/opt/pw-browsers';
    const d = fs.readdirSync(base).filter((x) => /^chromium_headless_shell-\d+$/.test(x)).sort().reverse()[0];
    return chromium.launch({ executablePath: path.join(base, d, 'chrome-headless-shell-linux64', 'chrome-headless-shell') });
  }
}

(async () => {
  const browser = await launch();
  // 1. PHP-rendered field, library 2.5 s late.
  {
    const page = await browser.newPage();
    const errors = [];
    page.on('pageerror', (e) => errors.push(e.message));
    await page.route('**/*', (r) => r.fulfill({ status: 200, contentType: 'text/html', body: '<!doctype html><body></body>' }));
    await page.goto('http://site.test/form/');
    await page.evaluate(([html, js]) => {
      window.sessionPub_emsFormBuilder = 's'; window.fun_sendBack_emsFormBuilder = () => {}; window.get_row_sendback_by_id_efb = () => -1;
      document.body.insertAdjacentHTML('beforeend', '<div>' + html + '<small id="mob1_-message"></small></div>');
      const s = document.createElement('script'); s.textContent = js; document.body.appendChild(s);
    }, phpOut);
    await page.waitForTimeout(2500);
    c.check('PHP renderer: no error while the library is still loading', errors.length === 0, errors);
    await page.addScriptTag({ path: LIB });
    await page.waitForTimeout(1500);
    c.check('PHP renderer: the field is initialised once the library arrives', await page.evaluate(() => !!document.querySelector('.iti #mob1_')));
    c.check('PHP renderer: still no page error', errors.length === 0, errors);
    await page.close();
  }
  // 2. JS renderer (pro_els-efb.js load_intlTelInput_efb), library 2.5 s late.
  {
    const page = await browser.newPage();
    const errors = [];
    page.on('pageerror', (e) => errors.push(e.message));
    await page.route('**/*', (r) => r.fulfill({ status: 200, contentType: 'text/html', body: '<!doctype html><body><input id="m2_" type="tel"></body>' }));
    await page.goto('http://site.test/form/');
    await page.addScriptTag({ content: "var efb_var={wp_lan:'en_US',language:'en_US',text:{},images:{utilsJs:'https://site.test/u.js'}};var valj_efb=[{type:'form'},{type:'step'},{id_:'m2',type:'mobile',c_c:[]}];" });
    await page.addScriptTag({ path: path.join(ROOT, 'includes/admin/assets/js/pro_els-efb.js') });
    await page.evaluate(() => load_intlTelInput_efb('m2', 2));
    await page.waitForTimeout(2500);
    c.check('JS renderer: no error while the library is still loading', errors.length === 0, errors);
    await page.addScriptTag({ path: LIB });
    await page.waitForTimeout(2000);
    c.check('JS renderer: the field is initialised once the library arrives', await page.evaluate(() => !!document.querySelector('.iti #m2_')));
    c.check('JS renderer: still no page error', errors.length === 0, errors);
    await page.close();
  }
  await browser.close();

  // 3. Dependencies: the public core script waits for the library when the form has a mobile field.
  const pub = fs.readFileSync(path.join(ROOT, 'includes/class-Emsfb-public.php'), 'utf8');
  const head = pub.slice(pub.indexOf('function public_scripts_and_css_head('), pub.indexOf("wp_register_script('Emsfb-core_js'"));
  c.check('public enqueue registers intlTelInput-js for a mobile field and adds it to the core dependencies',
    /intlTelInput-js/.test(head) && /\$core_deps\[\] = 'intlTelInput-js'/.test(head));
  const nv = fs.readFileSync(path.join(ROOT, 'includes/admin/assets/js/new-efb.js'), 'utf8');
  c.check('response viewer does not call window.intlTelInput unguarded', !/\n\s*const iti = window\.intlTelInput\(el, \{/.test(nv) || /typeof window\.intlTelInput [!=]== 'function'/.test(nv));
  c.done();
})();
