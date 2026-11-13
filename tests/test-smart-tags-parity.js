#!/usr/bin/env node
/**
 * F08-A: Emsfb\Smart_Tags (PHP) and window.EFBSmart (JS) give the same answers: every vector of
 * docs/feature-gaps/reference/smart-tag-vectors.json, plus 600 generated texts (braces, known and unknown
 * namespaces, colons, pipes, Persian text and digits, control and direction characters, long values)
 * through parse, classify, clean_template and resolve in every mode and place.
 *
 * Run: node tests/test-smart-tags-parity.js   (needs php on PATH)
 */
'use strict';
const fs = require('fs');
const path = require('path');
const vm = require('vm');
const { spawnSync } = require('child_process');

let pass = 0, fail = 0;
function test(label, actual, expected) {
  const ok = JSON.stringify(actual) === JSON.stringify(expected);
  if (ok) { pass++; } else { fail++; console.log('[FAIL] ' + label + '\n  php ' + JSON.stringify(expected) + '\n  js  ' + JSON.stringify(actual)); }
}
const root = path.join(__dirname, '..');
const win = {}; win.window = win; vm.createContext(win);
vm.runInContext(fs.readFileSync(path.join(root, 'public', 'assets', 'js', 'smart-tags-efb.js'), 'utf8'), win);
const S = win.EFBSmart;
const V = JSON.parse(fs.readFileSync(path.join(root, 'docs', 'feature-gaps', 'reference', 'smart-tag-vectors.json'), 'utf8'));
const ctx = V.ctx;

// Generated texts (seeded, so a failure can be replayed).
let seed = 20261113;
const rnd = (n) => { seed = (seed * 1103515245 + 12345) & 0x7fffffff; return seed % n; };
const parts = ['{', '}', ':', '|', '.', ' ', 'get', 'user', 'field', 'date', 'ip', 'uid', 'ref', 'page', 'post', 'form', 'site', 'entry', 'cookie', 'geo', 'bogus',
  'utm_source', 'email', 'display_name', 'color', 'color.value', 'langs', 'name1', 'plan', 'H:i', 'Y-m-d', 'D l', '8', '40', 'url', 'title', 'campaign', 'wp_x',
  'سلام', '۱۲۳', 'é', '‮', '\n', '\t', '<b>', '"', "'", '&', '%0a', 'x'.repeat(70), '{{', '}}', 'fallback text', 'q', 'evil', 'crlf', 'list', 'list[]', 'long'];
const texts = [];
for (let i = 0; i < 600; i++) {
  let s = '';
  const n = 1 + rnd(12);
  for (let k = 0; k < n; k++) s += parts[rnd(parts.length)];
  if (rnd(3) === 0) s = '{' + ['get', 'user', 'field', 'date', 'page', 'cookie', 'uid', 'post', 'site', 'form', 'entry'][rnd(11)] + ':' + parts[6 + rnd(parts.length - 6)] + (rnd(2) ? '|' + parts[rnd(parts.length)] : '') + '}' + s;
  texts.push(s);
}
V.parse.forEach((v) => texts.push(v.in));
V.resolve.forEach((v) => texts.push(v.in));
const modes = ['text', 'html', 'attr', 'url_query', 'subject'];
const wheres = [null, 'default', 'hidden', 'placeholder', 'label', 'message', 'email_admin', 'email_visitor', 'redirect'];
const cases = texts.map((t, i) => ({ t, mode: modes[i % modes.length], where: wheres[i % wheres.length], legacy: i % 7 === 0 }));

const php = `define('ABSPATH', 'x'); require ${JSON.stringify(path.join(root, 'includes', 'class-Emsfb-smart-tags.php'))};
$in = json_decode(stream_get_contents(STDIN), true); $ctx = $in['ctx']; $out = array();
foreach ($in['cases'] as $c) {
  $p = array_map(function ($x) { return $x['t'] === 'text' ? array('t' => 'text', 'v' => $x['v']) : array('t' => 'tag', 'ns' => $x['ns'], 'arg' => $x['arg'], 'fb' => $x['fb'], 'legacy' => $x['legacy']); }, \\Emsfb\\Smart_Tags::parse($c['t']));
  $out[] = array('parse' => $p, 'classify' => \\Emsfb\\Smart_Tags::classify($c['t']), 'clean' => \\Emsfb\\Smart_Tags::clean_template($c['t']),
    'resolve' => \\Emsfb\\Smart_Tags::resolve($c['t'], $ctx, $c['mode'], $c['where'], $c['legacy'] ? array('legacy_drop' => true) : array()),
    'misplaced' => \\Emsfb\\Smart_Tags::url_tag_misplaced($c['t']), 'strip' => \\Emsfb\\Smart_Tags::strip_tags_text($c['t']), 'clean_value' => \\Emsfb\\Smart_Tags::clean_value($c['t']));
}
echo json_encode($out, JSON_UNESCAPED_UNICODE);`;
const r = spawnSync('php', ['-r', php], { input: JSON.stringify({ ctx, cases }), encoding: 'utf8', maxBuffer: 64 * 1024 * 1024 });
if (r.status !== 0 || !r.stdout) { console.log('[FAIL] php did not run: ' + (r.stderr || r.error)); console.log('\nRESULTS: 0 passed, 1 failed'); process.exit(1); }
const P = JSON.parse(r.stdout);
cases.forEach((c, i) => {
  const label = `#${i} ${JSON.stringify(c.t).slice(0, 60)}`;
  test(label + ' parse', S.parse(c.t).map((x) => x.t === 'text' ? { t: 'text', v: x.v } : { t: 'tag', ns: x.ns, arg: x.arg, fb: x.fb, legacy: x.legacy }), P[i].parse);
  test(label + ' classify', S.classify(c.t), P[i].classify);
  test(label + ' clean_template', S.cleanTemplate(c.t), P[i].clean);
  test(`${label} resolve [${c.mode}, ${c.where}${c.legacy ? ', legacy_drop' : ''}]`, S.resolve(c.t, ctx, c.mode, c.where, c.legacy ? { legacy_drop: true } : {}), P[i].resolve);
  test(label + ' url_tag_misplaced', S.urlTagMisplaced(c.t), P[i].misplaced);
  test(label + ' strip_tags_text', S.stripTagsText(c.t), P[i].strip);
  test(label + ' clean_value', S.cleanValue(c.t), P[i].clean_value);
});
// Dates: every format letter at several times and zones.
const fmts = ['Y-m-d H:i:s', 'y n j G g h A a', 'D l M F N U', 'Y/m/d', 'H:i', 'd.m.Y, H:i'];
const times = [0, 951782400, 1767225600, 1772323199, 2147483000];
const zones = [0, 12600, -18000, 19800, 46800];
const dcases = [];
fmts.forEach((f) => times.forEach((ts) => zones.forEach((z) => dcases.push([f, ts, z]))));
const rd = spawnSync('php', ['-r', `define('ABSPATH','x'); require ${JSON.stringify(path.join(root, 'includes', 'class-Emsfb-smart-tags.php'))}; $o=array(); foreach (json_decode(stream_get_contents(STDIN), true) as $c) $o[] = \\Emsfb\\Smart_Tags::format_date($c[0], $c[1], $c[2]); echo json_encode($o);`], { input: JSON.stringify(dcases), encoding: 'utf8' });
const PD = JSON.parse(rd.stdout);
dcases.forEach((c, i) => test(`format_date ${c.join(' ')}`, S.formatDate(c[0], c[1], c[2]), PD[i]));

console.log(`\nRESULTS: ${pass} passed, ${fail} failed (${cases.length} texts, ${dcases.length} dates)`);
process.exit(fail ? 1 : 0);
