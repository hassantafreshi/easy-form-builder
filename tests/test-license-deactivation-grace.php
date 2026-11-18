<?php
/**
 * BUGS-FOUND (F21): a site the licence server deactivated got Pro back for up to 21 days when the server could not
 * be reached (update_pro_status_efb() wrote emsfb_pro = 1 before asking, and the outage grace wrote 1 again), and
 * for ever when only the reachability probe failed (make_post_request_efb() then made up a local "active").
 *
 * The rule now: the outage grace period keeps Pro on for a licence that is on; it never turns Pro on. An explicit
 * verdict (deactive, notExists / invalid_key) stays in force until the server itself says active or new again.
 * The real efbFunction methods run here (functions.php with WordPress stand-ins); only the network answer is scripted.
 *
 * Run: php tests/test-license-deactivation-grace.php
 */
define('ABSPATH', __DIR__ . '/');
define('EMSFB_PLUGIN_VERSION', '4.3.2-test');
define('DAY_IN_SECONDS', 86400);
define('HOUR_IN_SECONDS', 3600);
define('EMSFB_LICENSE_SERVER_URL', 'https://license.invalid');
define('EMSFB_SERVER_URL', 'https://whitestudio.team');
$EFB = dirname(__DIR__);

$GLOBALS['opts'] = array();
$GLOBALS['locale'] = 'en_US';
$GLOBALS['head_ok'] = true;
function get_option($k, $d = false) { return array_key_exists($k, $GLOBALS['opts']) ? $GLOBALS['opts'][$k] : $d; }
function update_option($k, $v, $a = null) { $GLOBALS['opts'][$k] = $v; return true; }
function delete_option($k) { unset($GLOBALS['opts'][$k]); return true; }
function get_transient($k) { return false; }
function set_transient($k, $v, $t = 0) { return true; }
function get_locale() { return $GLOBALS['locale']; }
function sanitize_text_field($v) { return is_scalar($v) ? trim(strip_tags((string) $v)) : ''; }
function wp_unslash($v) { return is_string($v) ? stripslashes($v) : $v; }
function wp_parse_url($u, $c = -1) { return parse_url($u, $c); }
function apply_filters($h, $v) { return $v; }
function add_filter() { return true; }
function add_action() { return true; }
function do_action() {}
function esc_html__($s, $d = null) { return $s; }
function __($s, $d = null) { return $s; }
function wp_json_encode($v, $f = 0) { return json_encode($v, $f); }
function is_wp_error($v) { return $v instanceof WP_Error; }
class WP_Error {}
function wp_remote_head($u, $a = array()) { return $GLOBALS['head_ok'] ? array('response' => array('code' => 200)) : new WP_Error(); }
function wp_remote_post($u, $a = array()) { return $GLOBALS['post']; }
function wp_remote_retrieve_response_code($r) { return is_array($r) ? $r['code'] : 0; }
function wp_remote_retrieve_body($r) { return is_array($r) ? $r['body'] : ''; }
function get_bloginfo($k) { return '6.9'; }
function get_template() { return 'twentytwentyfive'; }
function get_setting_Emsfb($m = 'decoded') { return (object) array(); }
function emsfb_is_email_sending_enabled_efb($s) { return false; }

require $EFB . '/includes/json-efb.php';
require $EFB . '/includes/class-Emsfb-masks.php';
require $EFB . '/includes/functions.php';
require $EFB . '/includes/class-Emsfb-renewal-reminder.php';

/* Only the side effects outside the licence decision are stood in (the settings row, the add-on download queue). */
$fn = new class extends efbFunction {
	public function __construct() {}
	public function setting_version_efb_update($st, $pro, $skip_redirect = false) { $GLOBALS['opts']['settings_row_code'] = $st->activeCode; }
	public function resume_addon_downloads_efb() {}
};
$CODE = md5('site.example') . '@PLACEHOLDER0000';

$pass = 0; $fail = 0;
function t($label, $actual, $expected) {
	global $pass, $fail;
	if ($actual === $expected) { $pass++; echo "[PASS] $label\n"; return; }
	$fail++; echo "[FAIL] $label\n      expected " . var_export($expected, true) . "\n      actual   " . var_export($actual, true) . "\n";
}
/** The licence server answers $state (HTTP 200), or is unreachable ('down': the POST fails; 'probe': the HEAD fails). */
function server($state) {
	$GLOBALS['head_ok'] = $state !== 'probe';
	$GLOBALS['post'] = $state === 'down' ? new WP_Error() : array('code' => 200, 'body' => json_encode(array('r' => in_array($state, array('active', 'new'), true), 'state' => $state, 'key' => $GLOBALS['CODE'])));
}
function site($pro) {
	$GLOBALS['opts'] = array('siteurl' => 'https://site.example', 'home' => 'https://site.example', 'emsfb_pro_activeCode' => $GLOBALS['CODE']);
	if ($pro !== null) $GLOBALS['opts']['emsfb_pro'] = $pro;
	if ($pro === 1) $GLOBALS['opts']['emsfb_pro_ac_date'] = date('Y-m-d H:i:s', time() - 8 * DAY_IN_SECONDS);
}
$pro = function () { return (int) get_option('emsfb_pro', 2); };
echo "--- an active licence keeps the grace period ---\n";
site(1); server('active');
t('active verdict: Pro on', array($fn->update_pro_status_efb($CODE), $pro()), array(true, 1));
server('down');
t('the server down (request failed): Pro kept within the grace period', array($fn->update_pro_status_efb($CODE), $pro()), array(true, 1));
t('… the outage streak is recorded, the key kept', array(get_option('emsfb_license_failed_since') > 0, get_option('emsfb_pro_activeCode')), array(true, $CODE));
update_option('emsfb_license_failed_since', time() - 22 * DAY_IN_SECONDS);
t('past the grace period: Pro paused, cause outage', array($fn->update_pro_status_efb($CODE), $pro(), get_option('emsfb_license_lapse_cause')), array(false, 0, 'outage'));
server('active');
t('the server back with active: Pro on again, streak cleared', array($fn->update_pro_status_efb($CODE), $pro(), get_option('emsfb_license_failed_since', 'none')), array(true, 1, 'none'));
site(1); server('probe');
t('only the reachability probe fails on an active licence: Pro kept', array($fn->update_pro_status_efb($CODE), $pro()), array(true, 1));

echo "--- an explicit deactivation is never undone by an outage ---\n";
site(1); server('deactive');
t('deactive verdict: Pro off, cause expired', array($fn->update_pro_status_efb($CODE), $pro(), get_option('emsfb_license_lapse_cause')), array(false, 0, 'expired'));
server('down');
t('then the server down: Pro stays off (was: on for 21 days)', array($fn->update_pro_status_efb($CODE), $pro()), array(false, 0));
t('… the cause still says expired, the key kept', array(get_option('emsfb_license_lapse_cause'), get_option('emsfb_pro_activeCode')), array('expired', $CODE));
t('… and no "Pro suspended" outage e-mail marker or outage cause', get_option('emsfb_license_suspend_notified', 'none'), 'none');
server('probe');
t('the probe blocked (a firewall rule on the licence server): Pro stays off (was: on for good)', array($fn->update_pro_status_efb($CODE), $pro()), array(false, 0));
foreach (array('http500' => array('code' => 500, 'body' => 'oops'), 'garbled' => array('code' => 200, 'body' => '<html>'), 'unknown' => array('code' => 200, 'body' => '{"state":"maybe"}')) as $what => $answer) {
	$GLOBALS['head_ok'] = true; $GLOBALS['post'] = $answer;
	t("an answer that is not a verdict ({$what}): Pro stays off", array($fn->update_pro_status_efb($CODE), $pro()), array(false, 0));
}
server('active');
t('the server says active again (renewed): Pro on', array($fn->update_pro_status_efb($CODE), $pro(), get_option('emsfb_license_lapse_cause', '')), array(true, 1, ''));

site(1); server('notExists');
t('notExists verdict: Pro off, cause not_found', array($fn->update_pro_status_efb($CODE), $pro(), get_option('emsfb_license_lapse_cause')), array(false, 0, 'not_found'));
server('down');
t('then the server down: Pro stays off', array($fn->update_pro_status_efb($CODE), $pro()), array(false, 0));

echo "--- while the request is on the way ---\n";
site(0); update_option('emsfb_license_lapse_cause', 'expired');
/* Record what another request would read at the moment the licence request goes out. */
$GLOBALS['opts_at_post'] = null;
$fnSpy = new class extends efbFunction {
	public function __construct() {}
	public function resume_addon_downloads_efb() {}
	public function make_post_request_efb($ac) { $GLOBALS['opts_at_post'] = (int) get_option('emsfb_pro', 2); return (object) array('transport_error' => true, 'reason' => 'request_failed'); }
};
$fnSpy->update_pro_status_efb($CODE);
t('a deactivated site is not Pro while its licence request is on the way (was: emsfb_pro = 1 before asking)', $GLOBALS['opts_at_post'], 0);

echo "--- a site from before this fix (deactivated by an older version) ---\n";
site(0); server('down');
t('emsfb_pro 0 and the server down: Pro stays off', array($fn->update_pro_status_efb($CODE), $pro()), array(false, 0));

echo "--- a code typed in Settings ---\n";
site(2); server('active');
t('Free site, a valid code, the server says active: Pro on', array($fn->is_efb_pro($CODE), $pro()), array(true, 1));
site(2); server('new');
t('… the server says new: Pro on', array($fn->is_efb_pro($CODE), $pro()), array(true, 1));
site(0); update_option('emsfb_license_lapse_cause', 'expired'); server('down');
t('a deactivated site types its code again while the server is down: still off, nothing changed', array($fn->is_efb_pro($CODE), $pro(), get_option('emsfb_license_lapse_cause')), array(false, 0, 'expired'));

echo "--- Farsi sites (offline licence) are unchanged ---\n";
$GLOBALS['locale'] = 'fa_IR';
site(0); server('down');
t('fa_IR: the local code check is the verdict: Pro on with a code for this domain', array($fn->update_pro_status_efb($CODE), $pro()), array(true, 1));
site(1);
t('fa_IR: a code for another domain: off, not_found', array($fn->update_pro_status_efb(md5('other.example') . '@X'), $pro(), get_option('emsfb_license_lapse_cause')), array(false, 0, 'not_found'));
$GLOBALS['locale'] = 'en_US';

echo "\nRESULTS: {$pass} passed, {$fail} failed\n";
exit($fail ? 1 : 0);
