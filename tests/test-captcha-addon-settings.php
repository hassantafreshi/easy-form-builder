<?php
/**
 * F12b-L (approved by owner 2026-11-15): a CAPTCHA add-on's settings live in the main settings JSON (emsfb_setting),
 * under its own top-level key captcha_<id>, written only through core's efbFunction::set_setting_Emsfb() — the real
 * method, read out of includes/functions.php and run on an in-memory settings table (its wipe guard, history row,
 * option, transient and caches as they are).
 *
 *  - merge safety: an add-on's save writes its key only (every other key, another add-on's included, stays); a save
 *    of the main Settings window keeps the add-ons' keys whether it leaves them out or sends something under them
 *    (Admin::without_addon_keys(), the server-side rule of set_settings_Emsfb);
 *  - secrets: encrypted at rest (enc:v1:), never in the redacted row an EFB screen gets (the add-on keys are not in it
 *    at all), not in Builder_Api::redact_settings() (AutoPilot), not in the catalogue (a mask only), and an AJAX/HTML
 *    answer of the save does not echo them;
 *  - migration from the F12b options emsfb_cpv_<id>: before it, the provider reads the option (no window without
 *    keys); written, read back, then the option deleted; emsfb_cpv_public gone; a second run does nothing; a value
 *    already in the row wins; a failed write keeps the option and runs again later;
 *  - uninstall (Admin::remove()) clears only its own key; import() only when the add-on has no settings yet;
 *  - an add-on id whose key would be one of core's (captcha_login, captcha_provider) is refused.
 *
 * Run: php tests/test-captcha-addon-settings.php
 */

namespace {
define('ABSPATH', __DIR__ . '/');
$GLOBALS['T'] = array('options' => array(), 'transients' => array(), 'cache_deleted' => 0, 'fail_writes' => false, 'actions' => array());
function get_option($k, $d = false) { return array_key_exists($k, $GLOBALS['T']['options']) ? $GLOBALS['T']['options'][$k] : $d; }
function update_option($k, $v, $a = null) { $GLOBALS['T']['options'][$k] = $v; return true; }
function delete_option($k) { unset($GLOBALS['T']['options'][$k]); return true; }
function set_transient($k, $v, $t = 0) { $GLOBALS['T']['transients'][$k] = $v; return true; }
function get_transient($k) { return $GLOBALS['T']['transients'][$k] ?? false; }
function wp_cache_delete($k, $g = '') { $GLOBALS['T']['cache_deleted']++; return true; }
function wp_json_encode($v, $f = 0) { return json_encode($v, $f); }
function get_current_user_id() { return 1; }
function wp_date($f) { return gmdate($f); }
function wp_salt($s = '') { return 'unit-test-salt-not-a-real-one'; }
function apply_filters($h, $v) { return $v; }
function do_action($h) { $a = func_get_args(); if (isset($GLOBALS['T']['actions'][$h])) call_user_func_array($GLOBALS['T']['actions'][$h], array_slice($a, 1)); }
function sanitize_text_field($s) { return trim(preg_replace('/[\r\n\t ]+/', ' ', strip_tags((string) $s))); }
function admin_url($p = '') { return 'https://site.test/wp-admin/' . $p; }
function home_url() { return 'https://site.test'; }
function emsfb_json_normalize_efb($raw) { return (string) $raw; }
function emsfb_json_decode_efb($raw, $assoc = false) { return json_decode((string) $raw, $assoc); }
/** The settings as core's get_setting_Emsfb('decoded') reads them (transient, else the table's last row). */
function get_setting_Emsfb($mode = 'decoded') {
	if ($mode === '_clear_cache') return true;
	$raw = get_transient('emsfb_settings_transient');
	if ($raw === false) $raw = $GLOBALS['wpdb']->last();
	$d = json_decode((string) $raw);
	return is_object($d) ? $d : new \stdClass();
}
function get_efbFunction() { return new EfbFnUnderTest(); }

/** The settings table (emsfb_setting) in memory: the queries set_setting_Emsfb() makes. */
class EfbTestDb {
	public $prefix = 'wp_';
	public $rows = array();
	public function last() { return $this->rows ? end($this->rows)['setting'] : null; }
	public function get_var($q) {
		if (stripos($q, 'COUNT(*)') !== false) return count($this->rows);
		if (stripos($q, 'MAX(id)') !== false) return $this->rows ? max(array_keys($this->rows)) : null;
		if (stripos($q, 'SELECT setting') !== false) return $this->last();
		return null;
	}
	public function update($t, $data, $where) { if ($GLOBALS['T']['fail_writes']) return false; $this->rows[$where['id']] = array_merge($this->rows[$where['id']], $data); return 1; }
	public function insert($t, $data) { if ($GLOBALS['T']['fail_writes']) return false; $this->rows[count($this->rows) + 1] = $data; return 1; }
}
$GLOBALS['wpdb'] = new EfbTestDb();

// The real efbFunction::set_setting_Emsfb(), taken from includes/functions.php as it is.
$src = file_get_contents(dirname(__DIR__) . '/includes/functions.php');
$a = strpos($src, 'public static function set_setting_Emsfb');
$b = strpos($src, "\n    }\n", $a);
if ($a === false || $b === false) { echo "[FAIL] set_setting_Emsfb() not found in includes/functions.php\n\nRESULTS: 0 passed, 1 failed\n"; exit(1); }
$method = substr($src, $a, $b - $a + 6);
// A failed database write: the stub refuses, and the real method still caches what it was given; the test reads the table.
eval('class EfbFnUnderTest { ' . $method . ' }');

$efb_pass = 0; $efb_fail = 0;
function t($name, $actual, $expected) {
	global $efb_pass, $efb_fail;
	if ($actual === $expected) { $efb_pass++; echo "[PASS] {$name}\n"; }
	else { $efb_fail++; echo "[FAIL] {$name}\n  expected: " . json_encode($expected, JSON_UNESCAPED_UNICODE) . "\n  actual:   " . json_encode($actual, JSON_UNESCAPED_UNICODE) . "\n"; }
}
/** The table's last row, decoded as an array. */
function row() { return json_decode((string) $GLOBALS['wpdb']->last(), true); }
function seed(array $s) { $GLOBALS['wpdb']->rows = array(1 => array('setting' => json_encode($s))); $GLOBALS['T']['transients'] = array(); }
}

namespace Emsfb {
class Rate_Limit { public static function ready() { return false; } }
}

namespace {
$R = dirname(__DIR__);
require_once $R . '/includes/class-Emsfb-captcha.php';
require_once $R . '/includes/class-Emsfb-spam.php';
require_once $R . '/includes/payments/class-Emsfb-payment-secrets.php';
require_once $R . '/includes/class-Emsfb-builder-api.php';
use Emsfb\Captcha\Registry;
use Emsfb\Captcha\Admin;

class Efb_L_Fake extends \Emsfb\Captcha\Provider {
	public function __construct($id, $key) { $this->i = $id; $this->k = $key; }
	public $i; public $k;
	public function id() { return $this->i; }
	public function label() { return 'Fake ' . $this->i; }
	public function addon_key() { return $this->k; }
	public function settings_schema() { return array(array('key' => 'site_key', 'type' => 'text', 'required' => true, 'public' => true, 'pattern' => '^[a-z0-9-]{4,40}$'), array('key' => 'secret', 'type' => 'secret', 'required' => true), array('key' => 'mode', 'type' => 'select', 'values' => array('a', 'b'), 'default' => 'a')); }
	public function widget(array $c) { return array('config' => array('k' => $this->setting('site_key'))); }
	public function scripts(array $c) { return array(); }
	public function verify($token, array $c) { return array('ok' => $this->setting('secret') === 'fake-secret-value-0001'); }
}
$GLOBALS['T']['actions']['efb_register_captcha_providers'] = function ($reg) {
	$reg->register(new Efb_L_Fake('fakecap', 'AdnCTS'));
	$reg->register(new Efb_L_Fake('fakecap2', 'AdnHCP'));
	$reg->register(new Efb_L_Fake('login', 'AdnALT'));
	$reg->register(new Efb_L_Fake('provider', 'AdnQCP'));
};
Registry::reset();
Registry::boot();
t('an add-on id whose key would be core\'s (captcha_login, captcha_provider) is refused; the others register', array(array_keys(Registry::all()), array_map(function ($e) { return $e['id'] . ':' . $e['code']; }, Registry::errors())), array(array('recaptcha_v2', 'recaptcha_v2i', 'recaptcha_v3', 'fakecap', 'fakecap2'), array('login:bad_id', 'provider:bad_id')));

$SECRET = 'fake-secret-value-0001';
$CORE = array('emailSupporter' => 'owner@example.test', 'captcha_provider' => 'fakecap', 'captcha_login' => '1', 'rc3SecretKey' => 'g-v3-secret-placeholder', 'package_type' => 3, 'spam_honeypot' => '1');

echo "--- an add-on's save ---\n";
seed($CORE + array('captcha_fakecap2' => array('v' => 2, 'public' => array('site_key' => 'other-site'), 'secret' => array('secret' => \Emsfb\Payments\Secrets::encrypt('other-secret-0002')))));
$before = row();
$r = Admin::save('fakecap', array('site_key' => 'fake-site', 'mode' => 'b'), array('secret' => $SECRET));
$after = row();
t('Admin::save: ok; captcha_fakecap = {v 2, public, secret encrypted (enc:v1:)}', array($r['ok'], $after['captcha_fakecap']['v'], $after['captcha_fakecap']['public'], strpos($after['captcha_fakecap']['secret']['secret'], 'enc:v1:') === 0), array(true, 2, array('site_key' => 'fake-site', 'mode' => 'b'), true));
unset($after['captcha_fakecap']);
t('… every other key of the row is as it was (core\'s, the other add-on\'s)', $after, $before);
t('… the plain secret is nowhere in the table, the option or the transient', strpos(json_encode(array($GLOBALS['wpdb']->rows, $GLOBALS['T']['options'], $GLOBALS['T']['transients'])), $SECRET), false);
t('… written by set_setting_Emsfb(): the emsfb_settings option and the 30-minute transient follow, no emsfb_cpv_ option', array(json_decode($GLOBALS['T']['options']['emsfb_settings'], true)['captcha_fakecap']['public']['site_key'], json_decode($GLOBALS['T']['transients']['emsfb_settings_transient'], true)['captcha_fakecap']['public']['site_key'], array_values(array_filter(array_keys($GLOBALS['T']['options']), function ($k) { return strpos($k, 'emsfb_cpv_') === 0 && $k !== 'emsfb_cpv_moved'; }))), array('fake-site', 'fake-site', array()));
Registry::forget('fakecap');
$p = Registry::get('fakecap');
t('… the provider reads it back (Registry::stored, the secret decrypted), ready, verify passes', array($p->setting('site_key'), $p->setting('secret') === $SECRET, $p->ready(), Registry::usable('fakecap')), array('fake-site', true, true, true));
$r2 = Admin::save('fakecap', array('site_key' => 'BAD KEY!'), array('secret' => ''));
Registry::forget('fakecap');
t('a value of the wrong shape is refused, the stored one kept; an empty secret box keeps the saved secret', array($r2['refused'], $p->setting('site_key'), $p->setting('secret') === $SECRET), array(array('site_key'), 'fake-site', true));
Admin::save('fakecap', array(), array('secret' => '••••0001'));
Registry::forget('fakecap');
t('a mask sent back keeps the secret', $p->setting('secret') === $SECRET, true);

echo "--- the main Settings window's save ---\n";
$screen = Admin::redact_settings(get_setting_Emsfb('decoded'));
t('the row an EFB screen gets has no add-on key (captcha_fakecap / captcha_fakecap2), keeps captcha_provider / captcha_login, masks rc3SecretKey', array(isset($screen->captcha_fakecap), isset($screen->captcha_fakecap2), $screen->captcha_provider, $screen->captcha_login, $screen->rc3SecretKey === 'g-v3-secret-placeholder'), array(false, false, 'fakecap', '1', false));
t('… as a JSON string too', strpos(Admin::redact_settings(json_encode(row())), 'captcha_fakecap'), false);
$stored = row();
// The window's save: what the page has (no add-on keys) with one change, through the server's rule and the real write.
$m = json_decode(json_encode($screen), true);
$m['spam_honeypot'] = '';
unset($m['rc3SecretKey']);
(new EfbFnUnderTest())->set_setting_Emsfb(json_encode(Admin::without_addon_keys($m)), 'owner@example.test');
$now = row();
t('a main save that leaves the add-on keys out keeps both of them, as they were', array($now['captcha_fakecap'] === $stored['captcha_fakecap'], $now['captcha_fakecap2'] === $stored['captcha_fakecap2'], $now['spam_honeypot']), array(true, true, ''));
// An old cached page, or a hand-made request, sends something under an add-on key.
$m['captcha_fakecap'] = '';
$m['captcha_fakecap2'] = array('v' => 2, 'public' => array('site_key' => 'hijacked'), 'secret' => array());
$m['captcha_provider'] = 'recaptcha_v3';
$clean = Admin::without_addon_keys($m);
t('without_addon_keys(): drops captcha_<id> keys only (captcha_provider stays)', array(array_key_exists('captcha_fakecap', $clean), array_key_exists('captcha_fakecap2', $clean), $clean['captcha_provider']), array(false, false, 'recaptcha_v3'));
(new EfbFnUnderTest())->set_setting_Emsfb(json_encode($clean), 'owner@example.test');
$now = row();
t('… so a main save that sends them changes neither add-on\'s settings; its own change is saved', array($now['captcha_fakecap'] === $stored['captcha_fakecap'], $now['captcha_fakecap2'] === $stored['captcha_fakecap2'], $now['captcha_provider']), array(true, true, 'recaptcha_v3'));
t('without_addon_keys() of an object too', property_exists(Admin::without_addon_keys((object) array('captcha_fakecap' => 1, 'x' => 2)), 'captcha_fakecap'), false);

echo "--- AutoPilot, catalogue, answers ---\n";
$red = \Emsfb\Builder_Api::redact_settings(get_setting_Emsfb('decoded'));
t('Builder_Api::redact_settings() (AutoPilot, add-on install answer): no add-on key, no secret', array(isset($red->captcha_fakecap), isset($red->captcha_fakecap2), strpos(json_encode($red), 'enc:v1:'), isset($red->captcha_provider)), array(false, false, false, true));
$cat = array_values(array_filter(Admin::catalog(get_setting_Emsfb('decoded')), function ($e) { return $e['id'] === 'fakecap'; }))[0];
$sec = array_values(array_filter($cat['settings'], function ($f) { return $f['key'] === 'secret'; }))[0];
t('catalogue: the settings screen\'s address, public values, the secret as a mask only (never its value or its cipher)', array($cat['settings_url'], $sec['saved'], array_key_exists('value', $sec), strpos(json_encode($cat), $SECRET), strpos(json_encode($cat), 'enc:v1:')), array('https://site.test/wp-admin/admin.php?page=Emsfb_captcha&provider=fakecap', array('set' => true, 'last4' => '0001'), false, false, false));
t('catalogue: Google modes and add-ons not installed have no settings screen', array_values(array_unique(array_map(function ($e) { return $e['settings_url']; }, array_filter(Admin::catalog(), function ($e) { return $e['addon'] === null || !$e['installed']; })))), array(null));

echo "--- uninstall, import ---\n";
$stored = row();
t('Admin::remove(): ok; only captcha_fakecap is cleared (null), the provider has no settings, everything else stays', (function () use ($stored) { $ok = Admin::remove('fakecap'); Registry::forget('fakecap'); $n = row(); $was = $stored; unset($was['captcha_fakecap'], $n['captcha_fakecap']); return array($ok, array_key_exists('captcha_fakecap', row()) ? row()['captcha_fakecap'] : 'missing', Registry::raw('fakecap'), Registry::get('fakecap')->ready(), $n === $was); })(), array(true, null, null, false, true));
t('import(): writes when the add-on has none …', array(Admin::import('fakecap', array('site_key' => 'imported'), array('secret' => 'imported-secret-0003')), Registry::get('fakecap')->setting('site_key')), array(true, 'imported'));
Registry::forget('fakecap');
t('… and never over settings it has', array(Admin::import('fakecap', array('site_key' => 'again'), array()), Registry::get('fakecap')->setting('site_key'), Registry::get('fakecap')->setting('secret')), array(false, 'imported', 'imported-secret-0003'));

echo "--- migration from emsfb_cpv_<id> ---\n";
seed($CORE);
$GLOBALS['T']['options'] = array('emsfb_cpv_fakecap' => array('v' => 1, 'public' => array('site_key' => 'legacy-site'), 'secret' => array('secret' => \Emsfb\Payments\Secrets::encrypt($SECRET)), 'updated_at' => 1700000000),
	'emsfb_cpv_public' => array('fakecap' => array('site_key' => 'legacy-site')));
Registry::forget('fakecap');
$p = Registry::get('fakecap');
t('before the move the provider reads the old option (no window without its keys)', array($p->setting('site_key'), $p->setting('secret') === $SECRET), array('legacy-site', true));
$cipher = $GLOBALS['T']['options']['emsfb_cpv_fakecap']['secret']['secret'];
t('migrate(): done', Admin::migrate(), true);
$n = row();
t('… captcha_fakecap holds the same public values and the same cipher; the options are gone; marked', array($n['captcha_fakecap']['public'], $n['captcha_fakecap']['secret']['secret'] === $cipher, $n['captcha_fakecap']['updated_at'], get_option('emsfb_cpv_fakecap', null), get_option('emsfb_cpv_public', null), get_option('emsfb_cpv_moved')), array(array('site_key' => 'legacy-site'), true, 1700000000, null, null, 1));
unset($n['captcha_fakecap']);
t('… core\'s keys untouched', $n, $CORE);
Registry::forget('fakecap');
t('… the provider reads the row now', array($p->setting('site_key'), $p->setting('secret') === $SECRET), array('legacy-site', true));
$rows = count($GLOBALS['wpdb']->rows); $tr = $GLOBALS['T']['transients'];
t('a second run does nothing (marked)', array(Admin::migrate(), count($GLOBALS['wpdb']->rows), $GLOBALS['T']['transients'] === $tr), array(false, $rows, true));
// A run that stopped after the write (the option still there): the row wins, the option goes.
$GLOBALS['T']['options']['emsfb_cpv_fakecap'] = array('public' => array('site_key' => 'older-copy'), 'secret' => array());
delete_option('emsfb_cpv_moved');
t('the row already has it: the row wins, the old option is removed', array(Admin::migrate(), row()['captcha_fakecap']['public']['site_key'], get_option('emsfb_cpv_fakecap', null)), array(true, 'legacy-site', null));
// The write fails: the option stays, the provider keeps reading it, the move is tried again later.
seed($CORE);
$GLOBALS['T']['options'] = array('emsfb_cpv_fakecap2' => array('public' => array('site_key' => 'legacy-two'), 'secret' => array()));
$GLOBALS['T']['fail_writes'] = true;
$GLOBALS['T']['transients'] = array();
$res = Admin::migrate();
$GLOBALS['T']['transients'] = array(); // what the table holds, not what the failed call cached
Registry::forget('fakecap2');
t('a failed write: not done, the option kept, not marked, the provider still has its keys', array($res, is_array(get_option('emsfb_cpv_fakecap2', null)), get_option('emsfb_cpv_moved', 0), Registry::get('fakecap2')->setting('site_key')), array(false, true, 0, 'legacy-two'));
$GLOBALS['T']['fail_writes'] = false;
t('… the next run moves it', array(Admin::migrate(), row()['captcha_fakecap2']['public']['site_key'], get_option('emsfb_cpv_fakecap2', null)), array(true, 'legacy-two', null));

echo "\nRESULTS: {$efb_pass} passed, {$efb_fail} failed\n";
exit($efb_fail ? 1 : 0);
}
