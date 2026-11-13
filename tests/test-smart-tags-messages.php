<?php
/**
 * F08-F: messages, e-mail subjects and redirects through one resolver, on the methods of class-Emsfb-public.php
 * (extracted) without WordPress: efb_replace_field_tokens() (old {field_id} token: the option's label, not its id;
 * {ns:…} tags; one line), smart_message_values_efb() (the thank-you pieces the server sends: "message" place, no
 * ip / cookie / uid / user:email), smart_url_efb() (the host kept, values encoded, an open redirect refused),
 * the subject block of send_email_Emsfb_() (CR/LF never reach the header; the visitor's copy without ip / cookie;
 * the bracket tokens after). The real-site twin is tests/test-hidden-field-submit-http.php.
 *
 * Run: php tests/test-smart-tags-messages.php
 */
define('ABSPATH', __DIR__ . '/');
function sanitize_text_field($v) { if (is_array($v) || is_object($v)) return ''; $s = strip_tags((string) $v); $s = preg_replace('/%[a-f0-9]{2}/i', '', $s); return trim(preg_replace('/[\r\n\t ]+/', ' ', $s)); }
function esc_url_raw($v) { $v = preg_replace('|[^a-z0-9-~+_.?#=!&;,/:%@$\|*\'()\[\]\x80-\xff]|i', '', (string) $v); return preg_match('#^https?://#i', $v) ? $v : ''; }
function apply_filters($h, $v) { return $v; }
function get_bloginfo() { return 'Site'; }
function home_url($p = '') { return 'https://site.test' . $p; }
function wp_strip_all_tags($s) { return trim(strip_tags((string) $s)); }
function get_site_url() { return 'https://site.test'; }
require __DIR__ . '/../includes/class-Emsfb-smart-tags.php';
$pass = 0; $fail = 0;
function t($label, $actual, $expected) { global $pass, $fail; if ($actual === $expected) { $pass++; echo "[PASS] $label\n"; return; } $fail++; echo "[FAIL] $label\n  expected " . json_encode($expected, JSON_UNESCAPED_UNICODE) . "\n  actual   " . json_encode($actual, JSON_UNESCAPED_UNICODE) . "\n"; }
$src = file_get_contents(__DIR__ . '/../includes/class-Emsfb-public.php');
$method = function ($name) use ($src) { if (!preg_match('/\n\t(?:private|public)(?: static)? function ' . preg_quote($name, '/') . '\(.*?\n\t\}\n/s', $src, $m)) { echo "[FAIL] method $name not found\n"; exit(1); } return $m[0]; };
$sub = preg_match('/\n        if \(\$sub != "null"\) \{\n.*?\n        \}\n/s', $src, $m) ? $m[0] : '';
t('the subject block of send_email_Emsfb_ is found', $sub !== '' && strpos($sub, 'Smart_Tags::resolve($sub') !== false, true);
eval('namespace Emsfb; class Harness { public $smart_ctx_efb = null;
	' . $method('efb_replace_field_tokens') . $method('smart_message_values_efb') . $method('smart_url_efb') . '
	public function subjects($sub, $track, $link) { $subject = array("default 0", "default 1"); $link_w = array($link, $link); for ($i = 0; $i < 2; $i++) { ' . $sub . ' } return $subject; }
	public function call($m, $a) { return call_user_func_array(array($this, $m), $a); } }');

$V = json_decode(file_get_contents(__DIR__ . '/../docs/feature-gaps/reference/smart-tag-vectors.json'), true);
$ctx = $V['ctx'];
$h = new \Emsfb\Harness();
// Old rule (no context): unchanged behaviour.
t('without a context: the old rule (option id from the values map, unknown token removed)', $h->call('efb_replace_field_tokens', array('Order {color} {nofield}', array('color' => 'o1'), false)), 'Order o1 ');
$h->smart_ctx_efb = $ctx;
t('{color} in a subject is the option label (bug of §2: it was the option id)', $h->call('efb_replace_field_tokens', array('Order {color} {nofield}', array('color' => 'o1'), false)), 'Order Red');
t('{ns:…} tags in a notification subject', $h->call('efb_replace_field_tokens', array('{field:color.value} via {get:utm_source}', array(), false)), 'r via google');
t('a subject is one line (no header injection)', $h->call('efb_replace_field_tokens', array('X {get:crlf}', array(), false)), 'X a Bcc: x@evil.test');
t('a confirmation redirect token is encoded', $h->call('efb_replace_field_tokens', array('{plan}', array(), true)), 'Pro%20%26%20Co');
// Thank-you pieces.
$form = array(array('thank_you_message' => array('thankYou' => 'Thanks {field:name1|x}, {color}, {ip|no}, {cookie:campaign|no}, {uid|no}, {user:email|no}, {user:first_name}, {entry:track}', 'done' => 'Done {nofield}')));
$h->smart_ctx_efb = $ctx;
t('thank-you pieces: answers as text, ip / cookie / uid / user:email not allowed, entry:track', $h->call('smart_message_values_efb', array($form)), array('field:name1|x' => '<img src=x onerror=alert(1)>', 'field:color' => 'Red', 'ip|no' => 'no', 'cookie:campaign|no' => 'no', 'uid|no' => 'no', 'user:email|no' => 'no', 'user:first_name' => 'Ana', 'entry:track' => 'ABC123'));
t('a form without tags in its messages sends nothing extra', $h->call('smart_message_values_efb', array(array(array('thank_you_message' => array('thankYou' => 'Thanks', 'done' => 'Done'))))), array());
// Redirects.
t('redirect: tags in the query, encoded', $h->call('smart_url_efb', array('https://site.test/t?c={color}&q={get:q}')), 'https://site.test/t?c=Red&q=a%26b%20%23c%3Dd');
t('redirect: {get:next} cannot be the host', parse_url($h->call('smart_url_efb', array('https://{get:next}/x')), PHP_URL_HOST) !== 'evil.test', true);
t('redirect: an address without a tag is untouched', $h->call('smart_url_efb', array('https://site.test/thanks/')), 'https://site.test/thanks/');
// Subjects.
$s = $h->subjects('Lead {color} {get:crlf} {ip|-} {cookie:campaign|-} [confirmation_code]', 'TRK1', 'https://site.test/p');
t('subject: owner copy may use ip / cookie; one line; bracket tokens after', $s[0], 'Lead Red a Bcc: x@evil.test 203.0.113.9 spring TRK1');
t('subject: the visitor copy never shows ip or cookie', $s[1], 'Lead Red a Bcc: x@evil.test - - TRK1');
$h->smart_ctx_efb = null;
$s = $h->subjects('Code {entry:track} [confirmation_code] {get:x|-}', 'TRK2', 'https://site.test/p');
t('subject without a submission context (a later payment e-mail): the code, fallbacks, no raw tags', $s[0], 'Code TRK2 TRK2 -');
t('a subject without a brace is as before', $h->subjects('Plain [confirmation_code]', 'T3', 'x')[0], 'Plain T3');
echo "\nRESULTS: $pass passed, $fail failed\n";
exit($fail ? 1 : 0);
