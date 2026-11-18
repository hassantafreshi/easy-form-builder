<?php
/**
 * PAY-OUT step 3: the server pricing of a legacy gateway order (Stripe, PayPal) moved from core's
 * _Public::fun_validation_pay_elements_efb() to the add-ons (Legacy_Server_Price_V1). It must stay exactly as strict:
 *
 *  1  parity: the add-ons' code and core's code before PAY-OUT (git EFB_PAYOUT_CORE_BASE, default e890a6db) give the
 *     same answer — the same refusal or the same amount, the same stored items and the same warning to the owner — for
 *     every request of a corpus of honest and tampered requests (lowered, raised, negative, non-numeric and array
 *     prices; choices from another field or not on the form; the multiselect ids; typed amounts outside Min / Max;
 *     wrong shapes; duplicated rows; a calc amount without its add-on; 400 seeded random variations);
 *  2  tampering, by name: the charged amount is the server's whatever the request says, and the owner is warned;
 *  3  the owner's rule (Conditional Logic forward jump): a priced choice on a step the answers jumped over is not
 *     charged (the only intended difference; the jump is computed from the submitted answers).
 *
 * Run: php tests/test-payout-price-parity.php
 */
if (!defined('ABSPATH')) define('ABSPATH', __DIR__ . '/');
$GLOBALS['mails'] = array();
$GLOBALS['opt'] = array('emsfb_pro' => 1);
function get_option($k, $d = false) { return array_key_exists($k, $GLOBALS['opt']) ? $GLOBALS['opt'][$k] : $d; }
function add_filter() {} function add_action() {} function do_action() {}
$GLOBALS['efb_t_logic'] = null;
function apply_filters($h, $v, ...$a) {
	if ($h === 'efb_logic_evaluate' && $GLOBALS['efb_t_logic']) { $vd = new \Emsfb\Emsfb_Logic_Validator(); $vd->set_environment(array('query' => array(), 'user' => array('logged_in' => false, 'roles' => array()), 'current_step' => 1)); return $vd->evaluate($a[0], $a[1]); }
	return $v;
}
function esc_html__($t, $d = '') { return $t; }
function __($t, $d = '') { return $t; }
function esc_html($t) { return htmlspecialchars((string) $t, ENT_QUOTES); }
function get_bloginfo() { return 'Site'; }
function wp_mail($to, $s, $m) { $GLOBALS['mails'][] = $s; return true; }
class EfbPpStop extends Exception { public $payload; public function __construct($p) { parent::__construct('json'); $this->payload = $p; } }
function wp_send_json_success($d, $c = 200) { throw new EfbPpStop($d); }
require __DIR__ . '/../includes/class-Emsfb-builder-api.php';
require __DIR__ . '/payout-price-lib.php';

$passed = 0; $failed = 0;
/** t(name, actual, expected [, detail]); t(name, bool [, detail]) for a plain check. */
function t($name, $actual, $expected = true, $detail = null) {
	global $passed, $failed;
	if (func_num_args() === 3 && !is_bool($expected) && !is_array($expected) && !is_numeric($expected) && $expected !== null) { $detail = $expected; $expected = true; }
	if ($actual === $expected) { $passed++; echo "[PASS] {$name}\n"; return; }
	$failed++; echo "[FAIL] {$name}: expected " . json_encode($expected) . ', got ' . json_encode($actual) . ($detail !== null ? ' — ' . substr(json_encode($detail, JSON_UNESCAPED_UNICODE), 0, 700) : '') . "\n";
}

/* The baseline: core's method before PAY-OUT, from git. */
$rev = getenv('EFB_PAYOUT_CORE_BASE') ?: 'e890a6db';
$old_src = shell_exec('git -C ' . escapeshellarg(dirname(__DIR__)) . ' show ' . escapeshellarg($rev . ':includes/class-Emsfb-public.php') . ' 2>/dev/null');
if (!is_string($old_src) || !preg_match('/\tpublic function fun_validation_pay_elements_efb\(.*?\n\t}\n/s', $old_src, $m)) {
	echo "[SKIP] 1 core {$rev} is not in this repository (git)\n";
	$old = null;
} else {
	eval('class EfbOldCorePrice { public $id = 9; public $ip = ""; public function get_ip_address() { return "127.0.0.1"; } ' . $m[0] . ' }');
	$old = new EfbOldCorePrice();
}
class EfbNewAddonPrice extends EfbLegacyPriceFixture { public $id = 9; }
$new = new EfbNewAddonPrice();

$run = function ($impl, $rows, $form) {
	$GLOBALS['mails'] = array();
	try { $r = $impl->fun_validation_pay_elements_efb($rows, $form); $out = array('ok' => true, 'total' => $r['price_total'], 'email' => $r['email'], 'items' => $r['valobj']); }
	catch (EfbPpStop $s) { $out = array('ok' => false, 'answer' => $s->payload); }
	catch (Throwable $e) { $out = array('error' => get_class($e) . ': ' . $e->getMessage()); }
	$out['mails'] = count($GLOBALS['mails']);
	return $out;
};

/* The form: every priced kind core priced. */
$form = array(
	array('type' => 'payment', 'getway' => 'stripe', 'currency' => 'usd', 'paymentmethod' => 'charge', 'formName' => 'Shop'),
	array('id_' => '1', 'type' => 'step', 'step' => 1),
	array('id_' => 'em', 'type' => 'email', 'name' => 'Email', 'step' => 1),
	array('id_' => 'rad', 'type' => 'payRadio', 'name' => 'Ticket', 'step' => 1),
	array('id_' => 'r1', 'parent' => 'rad', 'type' => 'option', 'value' => 'Adult', 'price' => '25'),
	array('id_' => 'r2', 'parent' => 'rad', 'type' => 'option', 'value' => 'Child', 'price' => '12.5'),
	array('id_' => 'chk', 'type' => 'payCheckbox', 'name' => 'Extras', 'step' => 1),
	array('id_' => 'c1', 'parent' => 'chk', 'type' => 'option', 'value' => 'Insurance', 'price' => '40'),
	array('id_' => 'c2', 'parent' => 'chk', 'type' => 'option', 'value' => 'Breakfast', 'price' => '15'),
	array('id_' => 'sel', 'type' => 'paySelect', 'name' => 'Room', 'step' => 1),
	array('id_' => 's1', 'parent' => 'sel', 'type' => 'option', 'value' => 'Single', 'price' => '60'),
	array('id_' => 's2', 'parent' => 'sel', 'type' => 'option', 'value' => 'Double', 'price' => '90'),
	array('id_' => 'mul', 'type' => 'payMultiselect', 'name' => 'Tours', 'step' => 1),
	array('id_' => 'm1', 'parent' => 'mul', 'type' => 'option', 'value' => 'City', 'price' => '20'),
	array('id_' => 'm2', 'parent' => 'mul', 'type' => 'option', 'value' => 'Lake', 'price' => '35'),
	array('id_' => 'tip', 'type' => 'prcfld', 'name' => 'Tip', 'step' => 1, 'milen' => '1', 'mlen' => '100'),
	array('id_' => 'pay', 'type' => 'stripe', 'step' => 1),
);
$R = array(
	'em' => array('id_' => 'em', 'type' => 'email', 'name' => 'Email', 'value' => 'payer@example.test'),
	'r1' => array('id_' => 'rad', 'type' => 'payRadio', 'name' => 'Ticket', 'id_ob' => 'r1', 'value' => 'Adult', 'price' => '25'),
	'r2' => array('id_' => 'rad', 'type' => 'payRadio', 'name' => 'Ticket', 'id_ob' => 'r2', 'value' => 'Child', 'price' => '12.5'),
	'c1' => array('id_' => 'chk', 'type' => 'payCheckbox', 'name' => 'Extras', 'id_ob' => 'c1', 'value' => 'Insurance', 'price' => '40'),
	'c2' => array('id_' => 'chk', 'type' => 'payCheckbox', 'name' => 'Extras', 'id_ob' => 'c2', 'value' => 'Breakfast', 'price' => '15'),
	's2' => array('id_' => 'sel', 'type' => 'paySelect', 'name' => 'Room', 'id_ob' => 's2', 'value' => 'Double', 'price' => '90'),
	'mu' => array('id_' => 'mul', 'type' => 'payMultiselect', 'name' => 'Tours', 'ids' => 'm1,m2', 'value' => 'City@efb!Lake', 'price' => '55'),
	'tip' => array('id_' => 'tip', 'type' => 'prcfld', 'name' => 'Tip', 'value' => '5', 'price' => '5'),
);
$w = function ($k, $patch = array()) use ($R) { return array_merge($R[$k], $patch); };
$cases = array(
	'honest: everything' => array($R['em'], $R['r1'], $R['c1'], $R['c2'], $R['s2'], $R['mu'], $R['tip']),
	'honest: a child ticket only' => array($R['em'], $R['r2']),
	'lowered radio price' => array($R['em'], $w('r1', array('price' => '0.01'))),
	'raised radio price' => array($R['em'], $w('r1', array('price' => '999'))),
	'radio: option of another field' => array($R['em'], $w('r1', array('id_ob' => 'c1'))),
	'radio: option not on the form' => array($R['em'], $w('r1', array('id_ob' => 'zz'))),
	'radio: value of the other option' => array($R['em'], $w('r1', array('value' => 'Child'))),
	'checkbox: lowered price' => array($R['em'], $w('c1', array('price' => '1'))),
	'checkbox: option of another parent' => array($R['em'], $w('c1', array('id_' => 'rad'))),
	'select: lowered' => array($R['em'], $w('s2', array('price' => '0'))),
	'select: option not on the form' => array($R['em'], $w('s2', array('id_ob' => 'nope'))),
	'multiselect: ids of another field' => array($R['em'], $w('mu', array('ids' => 'r1,c1'))),
	'multiselect: unknown ids' => array($R['em'], $w('mu', array('ids' => 'x,y,'))),
	'multiselect: lowered price' => array($R['em'], $w('mu', array('price' => '1'))),
	'multiselect: ids not a string' => array($R['em'], $w('mu', array('ids' => array('m1')))),
	'typed amount: negative' => array($R['em'], $w('tip', array('price' => '-20'))),
	'typed amount: below Min' => array($R['em'], $w('tip', array('price' => '0.5'))),
	'typed amount: above Max' => array($R['em'], $w('tip', array('price' => '101'))),
	'typed amount: not a number' => array($R['em'], $w('tip', array('price' => '5abc'))),
	'price as an array' => array($R['em'], $w('r1', array('price' => array(1)))),
	'price as an object' => array($R['em'], $w('r1', array('price' => (object) array('a' => 1)))),
	'duplicated rows' => array($R['em'], $R['r1'], $R['r1'], $R['c1'], $R['c1']),
	'rows not a list' => 'abc',
	'a number instead of rows' => 5,
	'the form not a list' => null,
	'no price rows' => array($R['em']),
	'a row that is not an array' => array($R['em'], 'junk', $R['r1']),
	'a row without a type' => array($R['em'], array('id_' => 'rad', 'price' => '25', 'id_ob' => 'r1', 'value' => 'Adult')),
	'email of the payer is the first email row' => array($w('em', array('price' => '0')), $R['r1']),
);
$rng = 20261118;
$rand = function ($n) use (&$rng) { $rng = ($rng * 1103515245 + 12345) & 0x7fffffff; return $rng % $n; };
$prices = array('25', '12.5', '0', '0.01', '-1', '1e3', '99999', '', 'NaN', ' 25', '25 ');
$keys = array_keys($R);
for ($i = 0; $i < 400; $i++) {
	$rows = array($R['em']);
	for ($j = 0, $n = 1 + $rand(4); $j < $n; $j++) {
		$row = $R[$keys[$rand(count($keys))]];
		if ($rand(3) === 0) $row['price'] = $prices[$rand(count($prices))];
		if ($rand(7) === 0) $row['id_ob'] = array('r1', 'r2', 'c1', 's1', 'm2', 'zz')[$rand(6)];
		if ($rand(9) === 0) $row['value'] = array('Adult', 'Child', 'Double', 'x')[$rand(4)];
		$rows[] = $row;
	}
	$cases['random #' . $i] = $rows;
}

/* 1 */
if ($old) {
	$diff = array();
	foreach ($cases as $label => $rows) {
		$f = $label === 'the form not a list' ? null : $form;
		$a = $run($old, $rows, $f);
		$b = $run($new, $rows, $f);
		if ($a !== $b) $diff[$label] = array('core' => $a, 'add-on' => $b);
	}
	t('1 the add-ons\' pricing answers ' . count($cases) . ' honest and tampered requests exactly as core ' . $rev . ' did (refusal, amount, stored items, owner warning)', !$diff, true, array_slice($diff, 0, 2, true));
	$amounts = array_filter(array_map(function ($rows) use ($run, $new, $form) { $r = $run($new, $rows, $form); return isset($r['ok']) && $r['ok'] ? $r['total'] : null; }, $cases), 'is_numeric');
	t('1 the corpus holds both: requests charged (' . count($amounts) . ') and refused (' . (count($cases) - count($amounts)) . ')', count($amounts) > 50 && count($cases) - count($amounts) > 50, true);
}

/* 2 */
$r = $run($new, $cases['honest: everything'], $form);
t('2 an honest order: 25 + 40 + 15 + 90 + 20 + 35 + 5 = 230, no warning', array($r['ok'] ?? null, (isset($r['total']) ? (float) $r['total'] : null), $r['mails']), array(true, 230.0, 0), $r);
$r = $run($new, $cases['lowered radio price'], $form);
t('2 a lowered price: the server\'s 25 is charged, the owner is warned', array($r['ok'] ?? null, (isset($r['total']) ? (float) $r['total'] : null), $r['mails']), array(true, 25.0, 1), $r);
$r = $run($new, $cases['radio: option not on the form'], $form);
t('2 a choice that is not on the form: refused', array($r['ok'] ?? null, isset($r['answer']['success']) && $r['answer']['success'] === false), array(false, true), $r);
$r = $run($new, $cases['typed amount: negative'], $form);
t('2 a negative typed amount: refused (never subtracted)', $r['ok'] ?? null, false, $r);
$r = $run($new, $cases['multiselect: lowered price'], $form);
t('2 the multiselect: the server\'s 55, the owner warned', array((isset($r['total']) ? (float) $r['total'] : null), $r['mails']), array(55.0, 1), $r);

/* 3 */
$vf = null;
foreach (array(getenv('EFB_LOGIC_VALIDATOR') ?: '', __DIR__ . '/../vendor/logic/logic/class-Emsfb-logic-validator.php') as $f) if ($f !== '' && is_readable($f)) { $vf = $f; break; }
if ($vf) {
	require_once $vf;
	$GLOBALS['efb_t_logic'] = true;
	$jf = $form;
	$jf[0]['logic_rules'] = array(array('id' => 'skip', 'enabled' => true, 'priority' => 1, 'conditions' => array('type' => 'group', 'operator' => 'AND', 'items' => array(array('type' => 'condition', 'source' => 'field', 'field_id' => 'rad', 'compare' => 'is', 'value' => 'r2'))), 'actions' => array(array('type' => 'jump_to_step', 'target' => '3'))));
	$jf[] = array('id_' => '2', 'type' => 'step', 'step' => 2);
	$jf[] = array('id_' => 'chk2', 'type' => 'payCheckbox', 'name' => 'Extras 2', 'step' => 2);
	$jf[] = array('id_' => 'k1', 'parent' => 'chk2', 'type' => 'option', 'value' => 'Parking', 'price' => '30', 'step' => 2);
	$jf[] = array('id_' => '3', 'type' => 'step', 'step' => 3);
	$park = array('id_' => 'chk2', 'type' => 'payCheckbox', 'name' => 'Extras 2', 'id_ob' => 'k1', 'value' => 'Parking', 'price' => '30');
	$r = $run($new, array($R['em'], $R['r2'], $park), $jf);
	t('3 a priced choice on a step the answers jumped over is not charged (12.5, not 42.5)', array($r['ok'] ?? null, (isset($r['total']) ? (float) $r['total'] : null)), array(true, 12.5), $r);
	$r = $run($new, array($R['em'], $R['r1'], $park), $jf);
	t('3 no jump (Adult): the same choice is charged (25 + 30)', array($r['ok'] ?? null, (isset($r['total']) ? (float) $r['total'] : null)), array(true, 55.0), $r);
	$GLOBALS['efb_t_logic'] = null;
} else {
	echo "[SKIP] 3 the Conditional Logic add-on is not in vendor/logic\n";
}

echo "\nRESULTS: {$passed} passed, {$failed} failed\n";
exit($failed ? 1 : 0);
