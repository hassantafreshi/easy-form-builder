<?php
/**
 * F03-E: e-mail domain lists on the server (Field_Rules), on tests/fixtures/domain-vectors.json
 * (shared with tests/test-field-rules-domains.js).
 *  1. sanitize_key('email_domains'): the stored list (lower case, punycode via idn_to_ascii, the shapes
 *     of §4.5, no %XX, at most 200 items / 2000 characters); email_domain_mode allow-list.
 *  2. match_domain() on every pair: *.edu takes a@x.edu and not a@edu, *.example.com excludes
 *     example.com, name@example.com is that address only, *@example.com = example.com, Unicode domains.
 *  3. check(): allow refuses another domain (msg_domain / tvDomAllow_), deny refuses a listed domain
 *     (tvDomDeny_) — the browser can be bypassed, the server cannot; only email fields; no plan → no rule.
 *  4. The F02 hook mismatch_message(): the field's msg_mismatch, escaped, or F02's default.
 * Run: php tests/test-field-rules-domains.php
 */
define('ABSPATH', __DIR__ . '/');
$passed = 0;
$failed = 0;
function t($name, $actual, $expected) {
	global $passed, $failed;
	if ($actual === $expected) { echo "[PASS] {$name}\n"; $passed++; return; }
	echo "[FAIL] {$name}: expected " . var_export($expected, true) . ', got ' . var_export($actual, true) . "\n";
	$failed++;
}
$GLOBALS['plan'] = 'free';
function apply_filters($h, $v) { return $h === 'efb_f03_plan' ? $GLOBALS['plan'] : $v; }
function esc_html($s) { return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8'); }
function efb_plan_at_least_efb($n) { return false; }
require __DIR__ . '/../includes/class-Emsfb-masks.php';
require __DIR__ . '/../includes/class-Emsfb-field-rules.php';
use Emsfb\Field_Rules as FR;
$V = json_decode(file_get_contents(__DIR__ . '/fixtures/domain-vectors.json'), true);

/* 1 */
t('1 the example list', FR::sanitize_key('email_domains', $V['lists'][0]), 'example.com,*.edu,name@other.org,*@corp.io');
t('1 case and duplicates', FR::sanitize_key('email_domains', $V['lists'][1]), 'example.com,*.example.com');
t('1 Unicode domains in punycode', FR::sanitize_key('email_domains', $V['lists'][2]), 'xn--bcher-kva.de,*.xn--mgba3a4f16a');
$d = FR::domains_list($V['lists'][3]);
t('1 broken items dropped and counted (%41, spaces split, *.*, *@*, @x, trailing / leading / double dots)', array($d['value'], $d['dropped']), array('a,b,-x-,ok.com', 7));
$many = implode(',', array_map(function ($i) { return 'd' . $i . '.com'; }, range(1, 250)));
t('1 at most 200 items', FR::domains_list($many)['kept'], 200);
t('1 at most 2000 characters', strlen(FR::sanitize_key('email_domains', implode(' ', array_map(function ($i) { return str_repeat('a', 60) . $i . '.com'; }, range(1, 100))))) <= 2000, true);
t('1 mode allow-list', array(FR::sanitize_key('email_domain_mode', 'allow'), FR::sanitize_key('email_domain_mode', 'deny'), FR::sanitize_key('email_domain_mode', 'ALLOW'), FR::sanitize_key('email_domain_mode', array())), array('allow', 'deny', 'none', 'none'));

/* 2 */
foreach ($V['match'] as $m) t('2 ' . $m['p'] . ' ~ ' . $m['e'], FR::match_domain($m['p'], $m['e']), $m['ok']);

/* 3 */
$allow = array('id_' => 'e', 'type' => 'email', 'name' => 'Work email', 'email_domain_mode' => 'allow', 'email_domains' => 'example.com,*.edu');
$deny = array('id_' => 'e', 'type' => 'email', 'name' => 'Email', 'email_domain_mode' => 'deny', 'email_domains' => 'mailinator.com');
$chk = function ($f, $v) { return FR::check($f, array('id_' => 'e', 'type' => $f['type'], 'value' => $v), array(), array(), array(), array('no_db' => true)); };
$r = $chk($allow, 'a@gmail.com');
t('3 allow: another domain refused with the default sentence', array($r['ok'], $r['code'], $r['m']), array(false, 'domain', 'Please use an email address from an allowed domain.'));
t('3 allow: a listed domain passes', $chk($allow, 'a@uni.edu')['ok'], true);
$r = $chk($deny, 'x@Mailinator.com');
t('3 deny: a listed domain refused on the server', array($r['ok'], $r['m']), array(false, 'This email domain is not accepted.'));
t('3 msg_domain', $chk($allow + array('msg_domain' => 'Use your %s at work'), 'a@b.co')['m'], 'Use your <b>Work email</b> at work');
t('3 a text field takes no domain rule', FR::active_rules(array_merge($allow, array('type' => 'text'))), array());
$GLOBALS['plan'] = 'free_plus';
t('3 without the plan: no rule', $chk($allow, 'a@gmail.com')['ok'], true);
$GLOBALS['plan'] = 'free';

/* 4 */
t('4 F02 hook: msg_mismatch (escaped, label in bold) or the default', array(FR::mismatch_message(array('name' => 'E<x>', 'msg_mismatch' => 'Both %s boxes'), 'D'), FR::mismatch_message(array('name' => 'E'), 'D')), array('Both <b>E&lt;x&gt;</b> boxes', 'D'));

echo "\nRESULTS: {$passed} passed, {$failed} failed\n";
exit($failed ? 1 : 0);
