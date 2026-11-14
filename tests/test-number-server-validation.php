<?php
/**
 * F04-B: the server's number check (Calc::check_row(), called from the range / number branch of
 * get_form_public_efb() for every submit endpoint).
 *
 *  - an F04 number: local digits and separators read, the canonical number stored; decimals, step (scaled
 *    integers: 0.1 + 0.2 has no rounding trouble), minimum and maximum as decimals, in that order, with the
 *    field's name in the message and a stable code;
 *  - an old number (no F04 keys): today's rule and today's stored text for an ASCII number; a number typed
 *    with Persian digits (refused before) is now read and checked the old way;
 *  - the branch in class-Emsfb-public.php (between its comment markers) calls Calc::check_row for range and
 *    number, keeps prcfld on the old path, and answers with field_id and efb_num_* codes.
 *
 * Run: php tests/test-number-server-validation.php
 */
define('ABSPATH', __DIR__ . '/');
function esc_html($v) { return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8'); }
function get_locale() { return $GLOBALS['loc'] ?? 'en_US'; }
require __DIR__ . '/../includes/class-Emsfb-calc.php';
use Emsfb\Calc;
$pass = 0; $fail = 0;
function t($label, $actual, $expected) {
	global $pass, $fail;
	if ($actual === $expected) { $pass++; echo "[PASS] $label\n"; return; }
	$fail++; echo "[FAIL] $label\n  expected " . var_export($expected, true) . "\n  actual   " . var_export($actual, true) . "\n";
}
$lan = array('numStepM_' => '%1$s must be a multiple of %2$s.', 'numDecM_' => '%1$s allows at most %2$s decimal places.', 'numNanM_' => 'Please enter a number in %s.', 'mcplen' => 'At least NN.', 'mxcplen' => 'At most NN', 'mnvvXXX_' => 'Please enter valid value for the %s field.');
function f(array $x) { return array_merge(array('id_' => 'q1', 'type' => 'number', 'name' => 'Qty'), $x); }
function row($v) { return array('id_' => 'q1', 'name' => 'Qty', 'id_ob' => 'q1_', 'amount' => '2', 'type' => 'number', 'value' => $v, 'session' => 's', 'form_id' => '5'); }
function chk($f, $v) { global $lan; $r = Calc::check_row($f, row($v), $lan, array('currency' => 'usd')); return $r['ok'] ? 'ok:' . $r['row']['value'] : $r['code'] . ':' . $r['m']; }

$GLOBALS['loc'] = 'fa_IR';
t('۱۲٫۵ with step 0.5 stored as 12.5', chk(f(array('num_step' => '0.5')), '۱۲٫۵'), 'ok:12.5');
t('12.3 with step 0.5 refused (step)', chk(f(array('num_step' => '0.5')), '12.3'), 'step:<b>Qty</b> must be a multiple of 0.5.');
$GLOBALS['loc'] = 'en_US';
t('0.3 with step 0.1 (no float trouble)', chk(f(array('num_step' => '0.1')), '0.3'), 'ok:0.3');
t('step from the minimum: 7 with step 5 and min 2', chk(f(array('num_step' => '5', 'milen' => '2')), '7'), 'ok:7');
t('min -10 and max 9.5: -10 ok', chk(f(array('num_decimals' => '1', 'milen' => '-10', 'mlen' => '9.5')), '-10'), 'ok:-10');
t('min -10: -10.5 refused (min)', chk(f(array('num_decimals' => '1', 'milen' => '-10', 'mlen' => '9.5')), '-10.5'), 'min:At least -10.');
t('max 9.5: 9.6 refused (max), 9.5 ok', array(chk(f(array('num_decimals' => '1', 'milen' => '-10', 'mlen' => '9.5')), '9.6'), chk(f(array('num_decimals' => '1', 'milen' => '-10', 'mlen' => '9.5')), '9.5')), array('max:At most 9.5', 'ok:9.5'));
t('decimals before step: 1.234 with 2 decimals and step 0.5 → dec', chk(f(array('num_decimals' => '2', 'num_step' => '0.5')), '1.234'), 'dec:<b>Qty</b> allows at most 2 decimal places.');
t('grouped input stored canonical', chk(f(array('num_format' => 'group')), '1,250,000.50'), 'ok:1250000.5');
t('a forged non-number refused with the field name', chk(f(array('num_step' => '1')), '12abc'), 'nan:Please enter a number in <b>Qty</b>.');
t('a forged "1e5" refused (not a canonical number)', chk(f(array('num_step' => '1')), '1e5'), 'nan:Please enter a number in <b>Qty</b>.');
t('the field name is escaped in the message', strpos(Calc::check_row(f(array('name' => '<img src=x>', 'num_step' => '1')), row('x'), $lan)['m'], '<img') === false, true);
t('max 0 is "no maximum" unless num_max_zero', array(chk(f(array('num_step' => '1', 'mlen' => '0')), '50'), chk(f(array('num_step' => '1', 'milen' => '-5', 'mlen' => '0', 'num_max_zero' => '1')), '1')), array('ok:50', 'max:At most 0'));
t('99999999999999.99 with step 0.01 (scaled integers above 2^32)', chk(f(array('num_step' => '0.01')), '99999999999999.99'), 'ok:99999999999999.99');
// Old fields (no F04 keys).
t('old number: an ASCII number is stored as typed (12.50 stays 12.50)', chk(f(array('milen' => '1', 'mlen' => '100')), '12.50'), 'ok:12.50');
t('old number: max 100 refused as before (mnvvXXX_)', chk(f(array('milen' => '1', 'mlen' => '100')), '101'), 'range:Please enter valid value for the <b>Qty</b> field.');
t('old number: Persian digits are read now (they were refused)', chk(f(array('milen' => '1', 'mlen' => '100')), '۱۲'), 'ok:12');
t('old number: a decimal max compared as a decimal (9.5)', chk(f(array('mlen' => '9.5')), '9.6'), 'range:Please enter valid value for the <b>Qty</b> field.');
t('old range: 40 as typed', Calc::check_row(array('id_' => 'r', 'type' => 'range', 'name' => 'R', 'milen' => '0', 'mlen' => '100'), array('value' => '40'), $lan)['row']['value'], '40');

// The branch in the submit handler.
$src = file_get_contents(__DIR__ . '/../includes/class-Emsfb-public.php');
$a = strpos($src, "case 'range':\n\t\t\t\t\t\t\t\tcase 'number':\n\t\t\t\t\t\t\t\tcase 'prcfld':");
$b = $a === false ? false : strpos($src, "case 'hiddenField':", $a);
$block = $a !== false && $b !== false ? substr($src, $a, $b - $a) : '';
t('the range / number branch is found', $block !== '', true);
t('it calls Calc::check_row for range and number (prcfld keeps the old path)', strpos($block, "\$f['type'] !== 'prcfld' && class_exists('\\\\Emsfb\\\\Calc')") !== false && strpos($block, '\\Emsfb\\Calc::check_row(') !== false, true);
t('a refusal sets the message, field_id and the code', strpos($block, '$error_field_id = isset($f[\'id_\'])') !== false && strpos($block, '$this->num_code_efb = $num_efb[\'code\'];') !== false, true);
t('the answer carries efb_num_<code>', strpos($src, "'efb_num_' . \$this->num_code_efb") !== false, true);
t('the server messages are in $translation_keys', (bool) preg_match("/'numStepM_', 'numDecM_', 'numNanM_', 'calcErr', 'mcplen', 'mxcplen'/", $src), true);

echo "\nRESULTS: $pass passed, $fail failed\n";
exit($fail ? 1 : 0);
