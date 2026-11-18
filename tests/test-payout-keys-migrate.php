<?php
/**
 * PAY-OUT step 2: the one-time move of the legacy gateway keys (stripePKey / stripeSKey, paypalPKey / paypalSKey,
 * payToken) from core's settings row to each add-on's own key (pay_<type>), and the add-on's write-only save — the
 * add-ons' shared Legacy_Gateway_Keys_V1 (vendores-efb/<addon>/legacy-keys-efb.php) with an in-memory settings table:
 *
 *  1  a failed write: nothing removed, not marked done, the old key still used; the next request moves it;
 *  2  written, read back from the table (the secret decrypts to the same value), then the old keys removed (null);
 *     marked done; the secret is stored encrypted (enc:v1:…), never in clear;
 *  3  idempotent: a second run changes nothing;
 *  4  a write the table did not keep (read back differs): the old keys stay, tried again;
 *  5  a value already in the add-on's key wins; the old keys are just removed;
 *  6  an older core (legacy_gateway 1.1): nothing moves, the old keys are read;
 *  7  the screen's save: an empty secret keeps the saved one, a new one is encrypted, "remove" clears it, a malformed
 *     value is refused; fill() gives code that reads $setting->stripeSKey the add-on's key;
 *  8  the three add-ons ship the same shared file.
 *
 * Run: php tests/test-payout-keys-migrate.php
 */
define('ABSPATH', __DIR__ . '/');
$passed = 0; $failed = 0;
function t($name, $actual, $expected) {
	global $passed, $failed;
	if ($actual === $expected) { echo "[PASS] {$name}\n"; $passed++; return; }
	echo "[FAIL] {$name}: expected " . var_export($expected, true) . ', got ' . var_export($actual, true) . "\n"; $failed++;
}

/* WordPress stand-ins */
$GLOBALS['efb_t_opts'] = array(); $GLOBALS['efb_t_core'] = 1.2;
function get_option($k, $d = false) { return array_key_exists($k, $GLOBALS['efb_t_opts']) ? $GLOBALS['efb_t_opts'][$k] : $d; }
function update_option($k, $v, $a = null) { $GLOBALS['efb_t_opts'][$k] = $v; return true; }
function delete_option($k) { unset($GLOBALS['efb_t_opts'][$k]); return true; }
function add_action() {} function add_filter() {}
function admin_url($p = '') { return 'http://example.test/wp-admin/' . $p; }
function wp_json_encode($v, $f = 0) { return json_encode($v, $f); }
function wp_salt($s = '') { return 'efb-test-salt-not-secret'; }
function efb_addon_contract_versions() { return array('legacy_gateway' => array('version' => $GLOBALS['efb_t_core'], 'min' => 1)); }
function emsfb_json_decode_efb($s, $assoc = false) { return json_decode($s, $assoc); }
class Efb_T_Table { // the emsfb_setting table: the last row
	public $row = array(); public $fail = 0; public $stale = 0;
	public $prefix = 'wp_';
	public function get_var($sql) { if ($this->stale > 0) { $this->stale--; return json_encode(array('stripePKey' => 'pk_test_OLDOLDOLD', 'stripeSKey' => 'sk_test_OLDOLDOLD')); } return json_encode($this->row); }
}
$GLOBALS['wpdb'] = new Efb_T_Table();
class Efb_T_Fn {
	public function set_setting_Emsfb($o, $email = '') {
		if ($GLOBALS['wpdb']->fail > 0) { $GLOBALS['wpdb']->fail--; return false; }
		foreach (get_object_vars($o) as $k => $v) $GLOBALS['wpdb']->row[$k] = json_decode(json_encode($v), true); // the wipe guard keeps the other keys
		return true;
	}
	public function text_efb($k) { return array(); }
}
function get_efbFunction() { return new Efb_T_Fn(); }
function get_setting_Emsfb($m = 'decoded') { return json_decode(json_encode($GLOBALS['wpdb']->row)); }

require dirname(__DIR__) . '/includes/payments/class-Emsfb-payment-secrets.php';
$addons = getenv('EFB_ADDONS_REPO') ?: dirname((string) readlink(dirname(__DIR__) . '/vendor/stripe'));
require $addons . '/stripe/class-Emsfb-stripe-keys.php';
use Emsfb\Stripe_Keys_Efb as K;

$PK = 'pk_test_' . str_repeat('Abc123', 3); $SK = 'sk_test_' . str_repeat('Xyz789', 3);
$reset = function ($row) { $GLOBALS['wpdb']->row = $row; $GLOBALS['wpdb']->fail = 0; $GLOBALS['wpdb']->stale = 0; $GLOBALS['efb_t_opts'] = array(); K::forget(); };

/* 1 */
$reset(array('emailSupporter' => 'owner@example.test', 'stripePKey' => $PK, 'stripeSKey' => $SK, 'siteKey' => 'other'));
$GLOBALS['wpdb']->fail = 1;
t('1 a failed write: not done', K::migrate(), false);
t('1 nothing removed, not marked done, the old key still used', array($GLOBALS['wpdb']->row['stripeSKey'], isset($GLOBALS['wpdb']->row['pay_stripe']), get_option('emsfb_pay_stripe_moved', null), K::get('skey')), array($SK, false, null, $SK));
/* 2 */
t('2 the next request moves it', K::migrate(), true);
$r = $GLOBALS['wpdb']->row;
t('2 the add-on key: public in clear, the secret encrypted; the old keys null; the other keys kept', array($r['pay_stripe']['public']['pkey'], strpos($r['pay_stripe']['secret']['skey'], 'enc:v1:') === 0, strpos(json_encode($r), $SK) === false, $r['stripePKey'], $r['stripeSKey'], $r['siteKey'], $r['emailSupporter']), array($PK, true, true, null, null, 'other', 'owner@example.test'));
t('2 marked done; the key resolves to the same secret (decrypted for the request only)', array(get_option('emsfb_pay_stripe_moved'), K::get('skey'), K::legacy('stripeSKey'), K::get('pkey')), array(1, $SK, $SK, $PK));
/* 3 */
$before = json_encode($GLOBALS['wpdb']->row);
t('3 a second run: nothing to do, nothing changes', array(K::migrate(), json_encode($GLOBALS['wpdb']->row) === $before), array(false, true));
/* 4 */
$reset(array('stripePKey' => $PK, 'stripeSKey' => $SK));
// The table answers the read-back (the second read) with a row that does not hold the write.
$orig = $GLOBALS['wpdb'];
$GLOBALS['wpdb'] = new class extends Efb_T_Table { public $n = 0; public function get_var($sql) { $this->n++; return $this->n === 2 ? json_encode(array('stripePKey' => $GLOBALS['efb_t_pk'], 'stripeSKey' => $GLOBALS['efb_t_sk'])) : json_encode($this->row); } };
$GLOBALS['efb_t_pk'] = $PK; $GLOBALS['efb_t_sk'] = $SK;
$GLOBALS['wpdb']->row = array('stripePKey' => $PK, 'stripeSKey' => $SK);
t('4 a write the table did not keep (read back differs): not done', K::migrate(), false);
t('4 the old keys stay and are still used', array($GLOBALS['wpdb']->row['stripeSKey'], get_option('emsfb_pay_stripe_moved', null), K::get('skey')), array($SK, null, $SK));
t('4 tried again on the next request: done', array(K::migrate(), $GLOBALS['wpdb']->row['stripeSKey'], K::get('skey')), array(true, null, $SK));
$GLOBALS['wpdb'] = $orig;
/* 5 */
$reset(array('stripePKey' => $PK, 'stripeSKey' => $SK));
K::save(array('pkey' => 'pk_test_' . str_repeat('NewNew', 3)), array('skey' => 'sk_test_' . str_repeat('NewNew', 3)));
t('5 a value already in the add-on key wins; the old keys are just removed', array(K::migrate(), K::get('skey'), K::get('pkey'), $GLOBALS['wpdb']->row['stripeSKey']), array(true, 'sk_test_' . str_repeat('NewNew', 3), 'pk_test_' . str_repeat('NewNew', 3), null));
/* 6 */
$reset(array('stripePKey' => $PK, 'stripeSKey' => $SK));
$GLOBALS['efb_t_core'] = 1.1;
t('6 an older core (1.1): the add-on does not own the keys; nothing moves; the old keys are read', array(K::own(), K::migrate(), isset($GLOBALS['wpdb']->row['pay_stripe']), K::get('skey'), K::legacy('stripeSKey')), array(false, false, false, $SK, $SK));
$GLOBALS['efb_t_core'] = 1.2;
/* 7 */
$reset(array());
$r1 = K::save(array('pkey' => $PK), array('skey' => $SK));
$enc1 = $GLOBALS['wpdb']->row['pay_stripe']['secret']['skey'];
$r2 = K::save(array('pkey' => $PK), array('skey' => ''));
t('7 save: a new secret is encrypted; an empty box keeps it (same ciphertext)', array($r1['ok'], strpos($enc1, 'enc:v1:') === 0, $GLOBALS['wpdb']->row['pay_stripe']['secret']['skey'] === $enc1, K::get('skey')), array(true, true, true, $SK));
$r3 = K::save(array('pkey' => 'has spaces in it'), array('skey' => "sk_test_with\nnewline"));
t('7 malformed values are refused, the saved ones stay', array($r3['refused'], K::get('pkey'), K::get('skey')), array(array('pkey', 'skey'), $PK, $SK));
K::save(array(), array(), array('skey' => '1'));
t('7 "Remove the saved key" clears it; status needs keys', array(K::get('skey'), K::status()), array('', 'keys'));
K::save(array(), array('skey' => $SK));
$filled = K::fill((object) array('stripeSKey' => 'null', 'other' => 1));
t('7 fill(): code reading $setting->stripeSKey gets the add-on\'s key', array($filled->stripeSKey, $filled->stripePKey, $filled->other, K::status()), array($SK, $PK, 1, 'ready'));
/* 8 */
$a = md5_file($addons . '/stripe/legacy-keys-efb.php');
t('8 the three add-ons ship the same shared file', array(md5_file($addons . '/paypal/legacy-keys-efb.php'), md5_file($addons . '/persiapay/legacy-keys-efb.php')), array($a, $a));

echo "\nRESULTS: {$passed} passed, {$failed} failed\n";
exit($failed ? 1 : 0);
