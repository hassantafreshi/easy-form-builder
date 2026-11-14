/**
 * F10: one save rule for every layout key: EFBLayoutCore.sanitizeKey() (public/assets/js/layout-efb.js, the builder
 * and efbBuilderApi) and \Emsfb\Layout::sanitize_key() (the save, sanitize_obj_msg_efb) give the same stored value
 * for every vector, and list the same keys, defaults and limits.
 *
 * Run: node tests/test-layout-parity.js
 */
'use strict';
const path = require('path');
const { execFileSync } = require('child_process');
const C = require(path.join(__dirname, '..', 'public', 'assets', 'js', 'layout-efb.js'));
let pass = 0, fail = 0;
function t(label, actual, expected) {
  const a = JSON.stringify(actual), e = JSON.stringify(expected);
  if (a === e) { pass++; console.log('[PASS] ' + label); } else { fail++; console.log('[FAIL] ' + label + '\n  expected ' + e + '\n  actual   ' + a); }
}
const values = ['1', 1, true, 'true', 'on', '0', 0, false, '', 'yes', 'x', 'basic', 'full', 'FULL', 'all', 'in', 'out', 'roles', 'Roles', 'draw', 'type', 'both', 'auto', 'latin', 'arabic',
  'editor,author', 'editor, <b>author</b>', 'administrator,nonexistent', 'Check <b>it</b> %41 now', 'بررسی پاسخ‌ها', 'ب'.repeat(150), 'a"b<c>d', '  spaced   value  ', 'line\nbreak', null];
const keys = C.FORM_KEYS.concat(C.FIELD_KEYS).concat(C.RUNTIME_KEYS);
const vectors = [];
keys.forEach((k) => values.forEach((v) => vectors.push([k, v])));
const php = `
define('ABSPATH', '/');
function sanitize_text_field($v) { if (is_array($v) || is_object($v)) return ''; $s = strip_tags((string) $v); $s = preg_replace('/%[a-f0-9]{2}/i', '', $s); $s = preg_replace('/[\\r\\n\\t ]+/', ' ', $s); return trim($s); }
function sanitize_key($v) { return preg_replace('/[^a-z0-9_\\-]/', '', strtolower((string) $v)); }
function wp_roles() { return new class { public function get_names() { return array('administrator' => 'Administrator', 'editor' => 'Editor', 'author' => 'Author', 'subscriber' => 'Subscriber'); } }; }
require '${path.join(__dirname, '..', 'includes', 'class-Emsfb-layout.php').replace(/\\/g, '/')}';
$in = json_decode(stream_get_contents(STDIN), true);
$out = array();
foreach ($in as $v) $out[] = \\Emsfb\\Layout::sanitize_key($v[0], $v[1]);
echo json_encode(array('out' => $out, 'keys' => array(\\Emsfb\\Layout::FORM_KEYS, \\Emsfb\\Layout::FIELD_KEYS, \\Emsfb\\Layout::RUNTIME_KEYS), 'defaults' => \\Emsfb\\Layout::DEFAULTS, 'limits' => \\Emsfb\\Layout::LIMITS), JSON_UNESCAPED_UNICODE);
`;
const res = JSON.parse(execFileSync('php', ['-r', php], { input: JSON.stringify(vectors) }).toString());
// The builder knows the site's roles from efb_var.roles (the same list the PHP stub has).
global.efb_var = { roles: [{ slug: 'administrator', name: 'Administrator' }, { slug: 'editor', name: 'Editor' }, { slug: 'author', name: 'Author' }, { slug: 'subscriber', name: 'Subscriber' }] };
let same = 0;
vectors.forEach((v, i) => {
  const js = C.sanitizeKey(v[0], v[1]);
  if (js === res.out[i]) same++;
  else t(`${v[0]} ${JSON.stringify(v[1])}: JS = PHP`, js, res.out[i]);
});
t(`every vector the same in JS and PHP (${vectors.length})`, same, vectors.length);
t('the same keys (form, field, run time)', [C.FORM_KEYS, C.FIELD_KEYS, C.RUNTIME_KEYS], res.keys);
t('the same defaults', C.DEFAULTS, res.defaults);
t('the same limits', C.LIMITS, res.limits);
console.log('\nRESULTS: ' + pass + ' passed, ' + fail + ' failed');
process.exit(fail ? 1 : 0);
