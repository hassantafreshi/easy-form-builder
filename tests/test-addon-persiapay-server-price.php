<?php
/**
 * Persia Pay add-on: the ZarinPal bill is priced on the server (B7, security).
 *
 * zarinPalEFB::pay_persia_sub_Emsfb_api() (vendores-efb persiapay/zarinpal.php) has its own copy
 * of core's price loop. Option prices came from the stored form, but a price field (prcfld) was
 * charged at whatever `price` the browser sent: no min/max, a negative number turned positive,
 * and a price field a logic rule computes ("guests x 50,000") was charged at the browser's number,
 * or failed with "Error Code:V01" when the runtime's row carried no `price` key. Now the add-on
 * runs core's Builder_Api::apply_server_prices() first (as fun_validation_pay_elements_efb()
 * does) and bounds a typed price like core.
 *
 * Runs the real method with the real Builder_Api and, when found, the real
 * Emsfb_Logic_Validator; the ZarinPal HTTP call is replaced by a recorder.
 * Add-on source: EFB_PERSIAPAY_DIR, vendor/persiapay (git-ignored symlink), or
 * /home/user/vendores-efb/persiapay.
 *
 * Run: php tests/test-addon-persiapay-server-price.php
 */
namespace {
if (!defined('ABSPATH')) define('ABSPATH', __DIR__ . '/');
$GLOBALS['mails'] = array();
$GLOBALS['opt'] = array('emsfb_pro' => 1);
function get_option($k, $d = false) { return array_key_exists($k, $GLOBALS['opt']) ? $GLOBALS['opt'][$k] : $d; }
function add_filter() {} function add_action() {} function apply_filters($h, $v) { return $v; }
function esc_html__($t, $d = '') { return $t; }
function sanitize_text_field($s) { return is_scalar($s) ? trim(strip_tags((string) $s)) : ''; }
function sanitize_url($s) { return (string) $s; }
function get_bloginfo() { return 'Site'; }
function wp_mail($to, $s, $m) { $GLOBALS['mails'][] = $s; return true; }
function get_setting_Emsfb() { return (object) array('payToken' => 'merchant-token-123'); }
function emsfb_is_addon_compatible_efb() { return true; }
class EfbPzStop extends \Exception { public $payload; public function __construct($p) { parent::__construct('json'); $this->payload = $p; } }
function wp_send_json_success($d, $c = 200) { throw new EfbPzStop($d); }
class EfbPzRequest { private $p; public function __construct($p) { $this->p = $p; } public function get_json_params() { return $this->p; } }
class EfbPzDb {
	public $prefix = 'wp_'; public $form = '';
	public function prepare($q) { return $q; }
	public function get_results($q) { return array((object) array('form_structer' => $this->form, 'form_type' => 'payment')); }
}
$GLOBALS['wpdb'] = new EfbPzDb();
require __DIR__ . '/../includes/json-efb.php';
require __DIR__ . '/../includes/class-Emsfb-builder-api.php';
$vf = null;
foreach (array(getenv('EFB_LOGIC_VALIDATOR') ?: '', __DIR__ . '/../vendor/logic/logic/class-Emsfb-logic-validator.php', '/home/user/vendores-efb/logic/logic/class-Emsfb-logic-validator.php') as $f) {
	if ($f !== '' && is_readable($f)) { $vf = $f; break; }
}
if ($vf) require_once $vf;
$pz_dir = null;
foreach (array(getenv('EFB_PERSIAPAY_DIR') ?: '', __DIR__ . '/../vendor/persiapay', '/home/user/vendores-efb/persiapay') as $d) {
	if ($d !== '' && is_readable($d . '/zarinpal.php')) { $pz_dir = realpath($d); break; }
}
if (!$pz_dir) { echo "[SKIP] the Persia Pay add-on source was not found\n"; exit(0); }
// The method require_once's EMSFB_PLUGIN_DIRECTORY/vendor/persiapay/zarinpal.php: point that at the source.
$plugin_dir = sys_get_temp_dir() . '/efb-persiapay-test-' . getmypid();
@mkdir($plugin_dir . '/vendor', 0777, true);
@symlink($pz_dir, $plugin_dir . '/vendor/persiapay');
register_shutdown_function(function () use ($plugin_dir) { @unlink($plugin_dir . '/vendor/persiapay'); @rmdir($plugin_dir . '/vendor'); @rmdir($plugin_dir); });
define('EMSFB_PLUGIN_DIRECTORY', $plugin_dir . '/');
require_once $plugin_dir . '/vendor/persiapay/zarinpal.php';
}

namespace Emsfb {
/** The add-on with the ZarinPal request recorded instead of sent. */
class EfbPzRecorder extends zarinPalEFB {
	public function create_bill_zarinPal($jsonData, $check) {
		$GLOBALS['pz']['bill'] = json_decode($jsonData, true);
		return array('success' => true, 'trackingCode' => $check, 're' => 'ok', 'url' => 'https://www.zarinpal.com/pg/StartPay/A1');
	}
}
/** The calling _Public instance, as the route passes it. */
class EfbPzPublic {
	public $id = 0; public $ip = ''; public $value = ''; public $name = ''; public $lanText = array();
	public $efbFunction;
	public function __construct() { $this->efbFunction = new class { public function text_efb($k) { return array('sxnlex' => 'x'); } public function send_email_noti_sid_plugins_efb() {} }; }
	public function get_ip_address() { return '127.0.0.1'; }
	public function insert_message_db($a, $b) { $GLOBALS['pz']['stored'] = json_decode(str_replace('\\"', '"', $this->value), true); return 'TRACK1'; }
}
}

namespace {
$pass = 0; $fail = 0;
function pz($label, $ok, $detail = null) {
	global $pass, $fail;
	if ($ok) { $pass++; echo "[PASS] $label\n"; } else { $fail++; echo "[FAIL] $label" . ($detail !== null ? ' ' . json_encode($detail, JSON_UNESCAPED_UNICODE) : '') . "\n"; }
}
function pz_form($row0 = array(), $fields = array()) {
	return array_merge(array(
		array_merge(array('type' => 'payment', 'formName' => 'Order', 'getway' => 'persiaPay', 'currency' => 'IRR', 'paymentmethod' => 'charge'), $row0),
		array('type' => 'step', 'id_' => '1', 'step' => 1),
		array('id_' => 'meal', 'type' => 'payRadio', 'name' => 'Meal', 'step' => 1, 'amount' => 2),
		array('id_' => 'm1', 'parent' => 'meal', 'type' => 'option', 'value' => 'Fish', 'price' => '70000', 'step' => 1, 'amount' => 2),
		array('id_' => 'pay', 'type' => 'persiaPay', 'step' => 1, 'amount' => 3),
	), $fields);
}
/** POST the Persia Pay route; returns the JSON answer. $GLOBALS['pz'] holds the bill and the stored rows. */
function pz_pay($form, $rows) {
	$GLOBALS['pz'] = array(); $GLOBALS['mails'] = array();
	$GLOBALS['wpdb']->form = json_encode($form);
	$post = array('id' => 5, 'sid' => 's', 'value' => json_encode($rows), 'url' => 'https://example.com/pay', 'name' => 'Order');
	try { (new \Emsfb\EfbPzRecorder())->pay_persia_sub_Emsfb_api(new \EfbPzRequest($post), new \Emsfb\EfbPzPublic()); }
	catch (EfbPzStop $s) { return $s->payload; }
	return null;
}
function pz_amount() { return isset($GLOBALS['pz']['bill']['amount']) ? $GLOBALS['pz']['bill']['amount'] : null; }
$fish = array('id_' => 'meal', 'id_ob' => 'm1', 'type' => 'payRadio', 'name' => 'Meal', 'value' => 'Fish', 'price' => '1000');

// Option prices: already from the stored form (kept).
$r = pz_pay(pz_form(), array($fish));
pz('an option priced 70,000 in the form is billed 70,000 whatever the browser says', pz_amount() == 70000 && is_array($r) && $r['success'] === true, array(pz_amount(), $r));

// A price field the visitor types (no rule): bounded by the field's min/max, like core.
$tip_field = array('id_' => 'tip', 'type' => 'prcfld', 'name' => 'Tip', 'step' => 1, 'amount' => 4, 'milen' => '10000', 'mlen' => '100000');
$tip = function ($v) { return array('id_' => 'tip', 'id_ob' => 'tip_', 'type' => 'prcfld', 'name' => 'Tip', 'value' => $v, 'price' => $v); };
$r = pz_pay(pz_form(array(), array($tip_field)), array($tip('20000')));
pz('typed price 20,000 within min/max: billed 20,000', pz_amount() == 20000, array(pz_amount(), $r));
pz('and the typed row is stored with its price (the verify step sums it)', is_array($GLOBALS['pz']['stored'] ?? null) && count(array_filter($GLOBALS['pz']['stored'], function ($x) { return ($x['id_'] ?? '') === 'tip' && (float) $x['price'] === 20000.0; })) === 1, $GLOBALS['pz']['stored'] ?? null);
$r = pz_pay(pz_form(array(), array($tip_field)), array($tip('500000')));
pz('typed price above the field max (500,000 > 100,000): refused, no bill', pz_amount() === null && is_array($r) && $r['success'] === false, array(pz_amount(), $r));
$r = pz_pay(pz_form(array(), array($tip_field)), array($tip('-20000')));
pz('negative typed price: refused, not billed as +20,000', pz_amount() === null && is_array($r) && $r['success'] === false, array(pz_amount(), $r));

if (!$vf) {
	echo "[SKIP] the Conditional Logic validator source was not found: computed-price checks skipped\n";
} else {
	$cond = array('type' => 'condition', 'source' => 'field', 'field_id' => 'guests', 'compare' => 'is_not_empty', 'value' => '');
	$calc_rule = function ($formula) use ($cond) {
		return array('id' => 'calc', 'enabled' => true, 'priority' => 1, 'conditions' => array('type' => 'group', 'operator' => 'AND', 'items' => array($cond)),
			'actions' => array(array('type' => 'calculate', 'target' => 'total', 'value' => $formula, 'decimals' => 0)));
	};
	$fields = array(array('id_' => 'guests', 'type' => 'number', 'name' => 'Guests', 'step' => 1, 'amount' => 4), array('id_' => 'total', 'type' => 'prcfld', 'name' => 'Total', 'step' => 1, 'amount' => 5));
	$calc = pz_form(array('logic_rules' => array($calc_rule('{guests} * 50000'))), $fields);
	$guests = function ($n) { return array('id_' => 'guests', 'id_ob' => 'guests_', 'type' => 'number', 'name' => 'Guests', 'value' => (string) $n); };

	$r = pz_pay($calc, array($guests(3), array('id_' => 'total', 'type' => 'prcfld', 'value' => '150000')));
	pz('3 guests x 50,000 (runtime row without price): billed 150,000', pz_amount() == 150000, array(pz_amount(), $r));
	pz('the computed row is stored with price 150,000', is_array($GLOBALS['pz']['stored'] ?? null) && count(array_filter($GLOBALS['pz']['stored'], function ($x) { return ($x['id_'] ?? '') === 'total' && (float) $x['price'] === 150000.0; })) === 1, $GLOBALS['pz']['stored'] ?? null);
	$r = pz_pay($calc, array($guests(3), array('id_' => 'total', 'type' => 'prcfld', 'value' => '10000', 'price' => '10000')));
	pz('a computed price lowered in the browser to 10,000: billed 150,000', pz_amount() == 150000, array(pz_amount(), $r));
	pz('and the owner gets the "invalid value" warning mail', count($GLOBALS['mails']) === 1, $GLOBALS['mails']);
	$r = pz_pay($calc, array($guests(4), array('id_' => 'total', 'type' => 'prcfld', 'value' => '150000', 'price' => '150000')));
	pz('4 guests while the browser still says 150,000: billed 200,000', pz_amount() == 200000, array(pz_amount(), $r));
	$r = pz_pay($calc, array($guests(3), array('id_' => 'total', 'type' => 'prcfld', 'value' => '150000'), $fish));
	pz('computed 150,000 + option 70,000 = 220,000', pz_amount() == 220000, array(pz_amount(), $r));

	$hide = pz_form(array('logic_rules' => array($calc_rule('{guests} * 50000'), array('id' => 'h', 'enabled' => true, 'priority' => 2,
		'conditions' => array('type' => 'group', 'operator' => 'AND', 'items' => array(array('type' => 'condition', 'source' => 'field', 'field_id' => 'guests', 'compare' => 'gt', 'value' => '10'))),
		'actions' => array(array('type' => 'hide_field', 'target' => 'total'))))), $fields);
	$r = pz_pay($hide, array($guests(11), array('id_' => 'total', 'type' => 'prcfld', 'value' => '550000', 'price' => '550000'), $fish));
	pz('a price field the rules hide is not billed: only the option, 70,000', pz_amount() == 70000, array(pz_amount(), $r));

	$r = pz_pay(pz_form(array('logic_rules' => array($calc_rule('{total} + {guests}'))), $fields), array($guests(3), array('id_' => 'total', 'type' => 'prcfld', 'value' => '50000', 'price' => '50000')));
	pz('rules that never settle: refused, no bill', pz_amount() === null && is_array($r) && $r['success'] === false, array(pz_amount(), $r));
}

echo "\n========================================\nRESULTS: $pass passed, $fail failed\n========================================\n";
exit($fail > 0 ? 1 : 0);
}
