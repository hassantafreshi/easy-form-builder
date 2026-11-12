/**
 * F19-B: the JavaScript core of includes/admin/assets/js/step-bg-efb.js against the shared vectors
 * (tests/fixtures/step-bg-vectors.json), and byte-for-byte parity of its CSS with the PHP twin
 * (includes/step-bg-functions-efb.php, run through tests/test-step-bg-core.php --emit-css).
 * Also: random fuzz of the sanitizer and the CSS builder in both languages.
 *
 * Run: node tests/test-step-bg-parity.js
 */
'use strict';
const path = require('path');
const fs = require('fs');
const { execFileSync } = require('child_process');

const ROOT = path.join(__dirname, '..');
const core = require(path.join(ROOT, 'includes', 'admin', 'assets', 'js', 'step-bg-efb.js'));
const V = JSON.parse(fs.readFileSync(path.join(__dirname, 'fixtures', 'step-bg-vectors.json'), 'utf8'));

let pass = 0, fail = 0;
const check = (label, ok, detail) => {
  if (ok) { pass++; console.log('[PASS] ' + label); } else { fail++; console.log('[FAIL] ' + label + (detail !== undefined ? ' ' + JSON.stringify(detail) : '')); }
};
const eq = (label, a, b) => check(label, a === b, { expected: b, actual: a });

V.sanitize.forEach((v, i) => eq(`sanitize #${i} ${v[0]} ${JSON.stringify(v[1])}`, core.sanitizeKey(v[0], v[1]), v[2]));
V.contrast.forEach((v, i) => eq(`contrast #${i} ${v[0]} on ${v[1]} @${v[2]}`, core.contrast(v[0], [core.rgb(v[1], v[2])]), Math.round(v[3] * 100) / 100));
V.auto.forEach((v, i) => {
  const r = core.resolve({ step_bg_type: 'linear', step_bg_stops: v[0], step_bg_opacity: String(v[1]) }, {}, { gradient: true });
  eq(`auto text #${i} ${v[0]} @${v[1]}`, core.autoText(core.samples(r)), v[2]);
});

const out = execFileSync('php', [path.join(__dirname, 'test-step-bg-core.php'), '--emit-css']).toString('utf8');
const line = out.split('\n').find((l) => l.startsWith('CSSJSON:'));
const phpCss = line ? JSON.parse(line.slice(8)) : [];
check('PHP emitted one CSS string per vector', phpCss.length === V.css.length, phpCss.length);
V.css.forEach((c, i) => {
  const js = core.css(c.rows, c.prefix, c.opts);
  eq(`css #${i} JS == PHP (${c.name})`, js, phpCss[i]);
  if (c.expected !== null) eq(`css #${i} JS exact`, js, c.expected);
  (c.contains || []).forEach((n, j) => check(`css #${i} contains #${j}`, js.includes(n), n));
  (c.absent || []).forEach((n) => check(`css #${i} lacks ${n}`, !js.includes(n)));
});

/* Fuzz: random (often hostile) values for every key, sanitized and rendered in both languages. */
let seed = 20261112;
const rnd = () => { seed = (seed * 1103515245 + 12345) & 0x7fffffff; return seed / 0x7fffffff; };
const pick = (a) => a[Math.floor(rnd() * a.length)];
const pool = {
  step_bg_type: ['', 'none', 'solid', 'linear', 'radial', 'Linear', 'x;}', null],
  step_bg_color: ['#abc', '#A1B2C3', '#12', 'red', ' #fff ', '#ffffff;}', 7],
  step_bg_stops: ['#fff 0,#000 100', '#123 50,#abcdef 10,#999 90', '#fff 0', '#fff 0,#000 100%', '#000 999,#fff 0', '#1 0,#2 1', '#aaa 1,#bbb 2,#ccc 3,#ddd 4,#eee 5,#fff 6', ''],
  step_bg_angle: ['0', '360', '45.5', 720, -3, 'x', '', 12.49],
  step_bg_pos: ['center', 'top', 'left', 'nope', ''],
  step_bg_opacity: ['0', '5', '33', '99.5', '100', '150', 'x', 50],
  step_bg_text: ['', 'auto', '#fff', '#000000', 'black'],
  mobile_step_bg: ['', 'none', 'solid', 'x']
};
const forms = [];
for (let n = 0; n < 60; n++) {
  const rows = [{ type: 'form' }];
  const mk = (row) => { Object.keys(pool).forEach((k) => { if (rnd() < 0.7) row[k] = pick(pool[k]); }); return row; };
  if (rnd() < 0.6) mk(rows[0]);
  const steps = 1 + Math.floor(rnd() * 4);
  for (let s = 1; s <= steps; s++) rows.push(rnd() < 0.6 ? mk({ type: 'step', id_: String(s), step: s }) : { type: 'step', id_: String(s), step: s });
  forms.push({ rows, opts: { gradient: rnd() < 0.7, mobile: rnd() < 0.5 } });
}
const fuzzFile = path.join(require('os').tmpdir(), 'efb-step-bg-fuzz-' + process.pid + '.json');
fs.writeFileSync(fuzzFile, JSON.stringify(forms));
const phpFuzz = JSON.parse(execFileSync('php', ['-r', `
  define('ABSPATH', '/');
  require '${path.join(ROOT, 'includes', 'step-bg-functions-efb.php').replace(/\\/g, '/')}';
  $forms = json_decode(file_get_contents($argv[1]), true);
  $out = array();
  foreach ($forms as $f) {
    $san = array();
    foreach ($f['rows'] as $row) { $r = array(); foreach ($row as $k => $v) $r[$k] = in_array($k, efb_step_bg_keys_efb(), true) ? efb_step_bg_sanitize_key_efb($k, $v) : $v; $san[] = $r; }
    $out[] = array('raw' => efb_step_bg_css_efb($f['rows'], 'P', $f['opts']), 'san' => $san);
  }
  echo json_encode($out, JSON_UNESCAPED_SLASHES);
`, fuzzFile]).toString('utf8'));
fs.unlinkSync(fuzzFile);
let same = 0, sameSan = 0, painted = 0;
forms.forEach((f, i) => {
  const js = core.css(f.rows, 'P', f.opts);
  if (js === phpFuzz[i].raw) same++; else console.log('  fuzz css mismatch #' + i, JSON.stringify(f), '\n  JS ', js, '\n  PHP', phpFuzz[i].raw);
  if (js) painted++;
  const san = f.rows.map((row) => { const r = {}; Object.keys(row).forEach((k) => { r[k] = core.KEYS.includes(k) ? core.sanitizeKey(k, row[k]) : row[k]; }); return r; });
  if (JSON.stringify(san) === JSON.stringify(phpFuzz[i].san)) sameSan++; else console.log('  fuzz sanitize mismatch #' + i);
  check(`fuzz #${i}: CSS free of markup, url() and braces from input`, !/<|url\(|javascript|x;\}|;\}x|ffffff;\}/.test(js), js);
});
eq('fuzz: CSS identical in PHP and JS for 60 random forms', same, forms.length);
eq('fuzz: sanitizer identical in PHP and JS', sameSan, forms.length);
check('fuzz: some forms painted, some not', painted > 5 && painted < forms.length, painted);

console.log('\n========================================\nRESULTS: ' + pass + ' passed, ' + fail + ' failed\n========================================');
process.exit(fail ? 1 : 0);
