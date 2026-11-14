/**
 * F05: one save rule for every choice key: EfbChoices.rules.sanitizeKey() (public/assets/js/choices-efb.js, the
 * builder and efbBuilderApi) and \Emsfb\Choices::sanitize_key() (the save, sanitize_obj_msg_efb) give the same
 * stored value for every vector, and list the same keys, types, enums and limits.
 *
 * Run: node tests/test-choices-parity.js
 */
'use strict';
const path = require('path');
const { execFileSync } = require('child_process');
const C = require(path.join(__dirname, '..', 'public', 'assets', 'js', 'choices-efb.js'));
let pass = 0, fail = 0;
function t(label, actual, expected) {
  const a = JSON.stringify(actual), e = JSON.stringify(expected);
  if (a === e) { pass++; console.log('[PASS] ' + label); } else { fail++; console.log('[FAIL] ' + label + '\n  expected ' + e + '\n  actual   ' + a); }
}
const values = ['1', 1, true, 'true', 'on', '0', 0, false, '', 'yes', 'x', '۳۰', '٣', '-2', '5000', '12', ' 7 ', 'disable', 'HIDE', 'Label', 'posts', 'ENTRIES', 'desc', 'Asc', 'title', 'rand',
  'Product<script>', '12, News ,x<y', 'author,EDITOR', 'IR-23', 'تهران <b>x</b>', 'a"b<c>d', 'x'.repeat(250), 'د'.repeat(90), '  spaced   value  ', 'line\nbreak', null];
const keys = C.rules.OPTION_KEYS.concat(C.rules.PARENT_KEYS);
const vectors = [];
keys.forEach((k) => values.forEach((v) => vectors.push([k, v])));
const php = `
define('ABSPATH', '/');
function sanitize_text_field($v) { if (is_array($v) || is_object($v)) return ''; $s = strip_tags((string) $v); $s = preg_replace('/%[a-f0-9]{2}/i', '', $s); $s = preg_replace('/[\\r\\n\\t ]+/', ' ', $s); return trim($s); }
require '${path.join(__dirname, '..', 'includes', 'class-Emsfb-choices.php').replace(/\\/g, '/')}';
$in = json_decode(stream_get_contents(STDIN), true);
$out = array();
foreach ($in as $v) $out[] = \\Emsfb\\Choices::sanitize_key($v[0], $v[1]);
echo json_encode(array('out' => $out, 'keys' => array(\\Emsfb\\Choices::OPTION_KEYS, \\Emsfb\\Choices::PARENT_KEYS), 'types' => \\Emsfb\\Choices::TYPES, 'enums' => \\Emsfb\\Choices::ENUMS, 'limits' => \\Emsfb\\Choices::LIMITS, 'flags' => \\Emsfb\\Choices::FLAGS), JSON_UNESCAPED_UNICODE);
`;
const res = JSON.parse(execFileSync('php', ['-r', php], { input: JSON.stringify(vectors) }).toString());
let same = 0;
vectors.forEach((v, i) => {
  const js = C.rules.sanitizeKey(v[0], v[1]);
  if (js === res.out[i]) same++;
  else t(`${v[0]} ${JSON.stringify(v[1])}: JS = PHP`, js, res.out[i]);
});
t(`every vector the same in JS and PHP (${vectors.length})`, same, vectors.length);
t('the same option and field keys', [C.rules.OPTION_KEYS, C.rules.PARENT_KEYS], res.keys);
t('the same types per part', C.rules.TYPES, res.types);
t('the same enums', C.rules.ENUMS, res.enums);
t('the same limits', C.rules.LIMITS, res.limits);
t('the same flags', C.rules.FLAGS, res.flags);
console.log('\nRESULTS: ' + pass + ' passed, ' + fail + ' failed');
process.exit(fail ? 1 : 0);
