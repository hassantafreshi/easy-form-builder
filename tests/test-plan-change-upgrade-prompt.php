<?php
/**
 * F18 sub-task C: efb_save_plan_selection for the upgrade dialog (real handler, WordPress stubs).
 *
 * - Free → Free Plus from the dialog (source upgrade_prompt) succeeds with credit_ack and is
 *   refused (400 credit_ack_required, nothing written) without it; setup / settings sources keep
 *   working without it (the plan picker shows its own description);
 * - permission: efb_can_change_plan_efb() = the plugin's admin permission (manage_options or the
 *   Emsfb capability), filter efb_can_change_plan; a refused user gets 403 and nothing is written;
 *   cron and REST requests are refused; a bad nonce is refused;
 * - the answer carries package_type and previous_package_type; do_action('efb_plan_changed',
 *   $old, $new, $context) fires once with source/feature/user_id, after the option is written;
 *   form caches are cleared and the marked pages purged;
 * - unchanged: lapsed Pro (0) with a stored key → 409 without downgrade_confirmed, then success,
 *   the key is removed and Pro add-ons are switched off; choosing the plan already in use is
 *   silent (no action); Pro without a key does not change the plan;
 * - efb_var.can_change_plan is added by the localize filter.
 *
 * Run: php tests/test-plan-change-upgrade-prompt.php
 */
define('ABSPATH', __DIR__ . '/');
define('EMSFB_PLUGIN_VERSION', '4.3.1-test');
define('EMSFB_API_VERSION', 1);
define('EMSFB_PLUGIN_DIRECTORY', sys_get_temp_dir() . '/efb-f18-c-' . getmypid() . '/');

$pass = 0; $fail = 0;
function check($label, $ok, $detail = null) {
	global $pass, $fail;
	if ($ok) { $pass++; echo "[PASS] $label\n"; }
	else { $fail++; echo "[FAIL] $label" . ($detail !== null ? ' ' . json_encode($detail) : '') . "\n"; }
}

class EfbF18Stop extends Exception { public $ok; public $data; public $code; public function __construct($ok, $data, $code) { parent::__construct('json'); $this->ok = $ok; $this->data = $data; $this->code = $code; } }
$GLOBALS['W'] = array();
function reset_world($opt, $settings, $caps = array('manage_options' => true)) {
	$GLOBALS['W'] = array('opt' => $opt, 'settings' => (object) $settings, 'caps' => $caps, 'actions' => array(), 'hooks' => array(), 'writes' => array(),
		'cache_cleared' => 0, 'purged' => 0, 'nonce_ok' => true, 'cron' => false, 'saved_settings' => null);
}
function get_option($k, $d = false) { return array_key_exists($k, $GLOBALS['W']['opt']) ? $GLOBALS['W']['opt'][$k] : $d; }
function update_option($k, $v, $a = null) { $GLOBALS['W']['writes'][] = $k; $GLOBALS['W']['opt'][$k] = $v; return true; }
function delete_option($k) { $GLOBALS['W']['writes'][] = '-' . $k; unset($GLOBALS['W']['opt'][$k]); return true; }
function add_action() {}
function add_filter($h, $cb, $p = 10, $n = 1) { $GLOBALS['W']['hooks'][$h][] = $cb; }
function apply_filters($h, $v) {
	$a = func_get_args(); array_shift($a);
	if (empty($GLOBALS['W']['hooks'][$h])) return $v;
	foreach ($GLOBALS['W']['hooks'][$h] as $cb) { $a[0] = call_user_func_array($cb, $a); }
	return $a[0];
}
function do_action($h) {
	$a = func_get_args(); array_shift($a);
	$GLOBALS['W']['actions'][] = array('hook' => $h, 'args' => $a, 'emsfb_pro_at_call' => get_option('emsfb_pro', null));
}
function current_user_can($c) { return !empty($GLOBALS['W']['caps'][$c]); }
function is_user_logged_in() { return true; }
function is_admin() { return false; }
function wp_get_current_user() { return (object) array('ID' => 7); }
function get_current_user_id() { return 7; }
function wp_verify_nonce() { return $GLOBALS['W']['nonce_ok']; }
function wp_doing_cron() { return $GLOBALS['W']['cron']; }
function wp_unslash($v) { return is_string($v) ? stripslashes($v) : $v; }
function sanitize_text_field($v) { return is_scalar($v) ? trim(strip_tags((string) $v)) : ''; }
function sanitize_textarea_field($v) { return is_scalar($v) ? trim((string) $v) : ''; }
function __($s) { return $s; }
function get_locale() { return 'en_US'; }
function current_time() { return '2026-11-11 10:00:00'; }
function wp_cache_flush_group($g) { $GLOBALS['W']['cache_cleared']++; return true; }
function get_setting_Emsfb($m = 'decoded') { return clone $GLOBALS['W']['settings']; }
function emsfb_onboarding_pending_efb() { return false; }
function wp_send_json_success($d = null, $c = 200) { throw new EfbF18Stop(true, $d, $c); }
function wp_send_json_error($d = null, $c = 200) { throw new EfbF18Stop(false, $d, $c); }
eval('namespace Emsfb; class Cache_Compat { public static function purge_marked_pages() { $GLOBALS["W"]["purged"]++; return 2; } }');

class EfbF18Fn {
	public function text_efb($keys) { $o = array(); foreach ((array) $keys as $k) $o[$k] = 'T:' . $k; return $o; }
	public function user_permission_efb_admin_dashboard() { return is_user_logged_in() && (current_user_can('manage_options') || current_user_can('Emsfb')); }
	public function get_all_addon_keys_efb() { return array('AdnSPF', 'AdnOF', 'AdnSS', 'AdnPAP', 'AdnSMF', 'AdnTLG'); }
	public function addon_required_package_efb($a) { $t = array('AdnOF' => 0, 'AdnSMF' => 3, 'AdnSPF' => 3, 'AdnPAP' => 3); return isset($t[$a]) ? $t[$a] : 1; }
	public function addon_keys_allowed_by_package_efb($p) {
		$out = array();
		foreach ($this->get_all_addon_keys_efb() as $k) { $r = $this->addon_required_package_efb($k); if ($r === 0 || (int) $p === 1 || ((int) $p === 3 && $r === 3)) $out[] = $k; }
		return $out;
	}
	public function set_setting_Emsfb($s, $email = '') { $GLOBALS['W']['saved_settings'] = $s; $GLOBALS['W']['settings'] = $s; return true; }
}
function get_efbFunction() { return new EfbF18Fn(); }

require_once __DIR__ . '/../includes/json-efb.php';
require_once __DIR__ . '/../includes/class-Emsfb-builder-api.php';
require_once __DIR__ . '/../includes/admin/class-Emsfb-admin.php';
$admin = (new ReflectionClass('\Emsfb\Admin'))->newInstanceWithoutConstructor();

function call_plan($admin, array $plan_data) {
	$_POST = array('nonce' => 'n', 'plan_data' => json_encode($plan_data));
	try { $admin->efb_save_plan_selection(); } catch (EfbF18Stop $s) { return $s; }
	return null;
}
$fp = function (array $extra = array()) { return array_merge(array('selected_plan' => 'free_plus', 'source' => 'upgrade_prompt', 'feature' => 'field:esign', 'credit_ack' => true, 'timestamp' => 1), $extra); };

// 1. Free → Free Plus from the dialog, with credit_ack
reset_world(array('emsfb_pro' => 2), array('AdnSPF' => 0, 'emailSupporter' => ''));
$r = call_plan($admin, $fp());
check('dialog + credit_ack: success, package_type 3, previous 2', $r && $r->ok && $r->data['package_type'] === 3 && $r->data['previous_package_type'] === 2 && $r->data['source'] === 'upgrade_prompt', $r ? $r->data : null);
check('dialog + credit_ack: emsfb_pro and settings.package_type are 3', get_option('emsfb_pro') === 3 && $GLOBALS['W']['saved_settings'] && $GLOBALS['W']['saved_settings']->package_type === 3);
$acts = array_values(array_filter($GLOBALS['W']['actions'], function ($a) { return $a['hook'] === 'efb_plan_changed'; }));
check('do_action(efb_plan_changed, 2, 3, context) fires once, after the option is written', count($acts) === 1 && $acts[0]['args'][0] === 2 && $acts[0]['args'][1] === 3 && $acts[0]['emsfb_pro_at_call'] === 3, $acts);
check('the action context has source, feature and user_id', isset($acts[0]) && $acts[0]['args'][2] === array('source' => 'upgrade_prompt', 'feature' => 'field:esign', 'user_id' => 7), isset($acts[0]) ? $acts[0]['args'][2] : null);
check('form caches cleared and the marked pages purged', $GLOBALS['W']['cache_cleared'] >= 1 && $GLOBALS['W']['purged'] === 1, array($GLOBALS['W']['cache_cleared'], $GLOBALS['W']['purged']));

// 2. Without credit_ack: 400, nothing written
reset_world(array('emsfb_pro' => 2), array('emailSupporter' => ''));
$r = call_plan($admin, $fp(array('credit_ack' => false)));
check('dialog without credit_ack: 400 credit_ack_required', $r && !$r->ok && $r->code === 400 && $r->data['code'] === 'credit_ack_required', $r ? array($r->code, $r->data) : null);
check('dialog without credit_ack: nothing written, no action', get_option('emsfb_pro') === 2 && count($GLOBALS['W']['writes']) === 0 && count($GLOBALS['W']['actions']) === 0 && $GLOBALS['W']['saved_settings'] === null);
$r = call_plan($admin, $fp(array('credit_ack' => 'yes please')));
check('credit_ack must be true/1, not any string', $r && !$r->ok && $r->data['code'] === 'credit_ack_required');

// 3. The plan picker (setup / settings) keeps working as before
foreach (array('setup', 'settings') as $src) {
	reset_world(array('emsfb_pro' => 2), array('emailSupporter' => ''));
	$r = call_plan($admin, array('selected_plan' => 'free_plus', 'source' => $src, 'timestamp' => 1));
	check("source $src: Free Plus without credit_ack still works (the picker describes the credit)", $r && $r->ok && get_option('emsfb_pro') === 3 && $r->data['source'] === $src);
}
reset_world(array('emsfb_pro' => 2), array('emailSupporter' => ''));
$r = call_plan($admin, array('selected_plan' => 'free_plus', 'timestamp' => 1));
check('no source (an old page): treated as setup, works', $r && $r->ok && get_option('emsfb_pro') === 3 && $r->data['source'] === 'setup');
reset_world(array('emsfb_pro' => 2), array('emailSupporter' => ''));
$r = call_plan($admin, $fp(array('source' => 'evil', 'credit_ack' => false)));
check('an unknown source is read as setup', $r && $r->ok && $r->data['source'] === 'setup');

// 4. Permission
reset_world(array('emsfb_pro' => 2), array('emailSupporter' => ''), array('Emsfb' => true));
$r = call_plan($admin, $fp());
check('the plugin\'s own admin capability (Emsfb, no manage_options) may change the plan (owner decision)', $r && $r->ok && get_option('emsfb_pro') === 3);
reset_world(array('emsfb_pro' => 2), array('emailSupporter' => ''), array('edit_posts' => true));
$r = call_plan($admin, $fp());
check('a user without the plugin admin permission: 403, nothing written', $r && !$r->ok && $r->code === 403 && get_option('emsfb_pro') === 2 && count($GLOBALS['W']['actions']) === 0);
reset_world(array('emsfb_pro' => 2), array('emailSupporter' => ''));
add_filter('efb_can_change_plan', function ($ok) { return false; });
$r = call_plan($admin, $fp());
check('filter efb_can_change_plan → false: 403', $r && !$r->ok && $r->code === 403 && get_option('emsfb_pro') === 2);
reset_world(array('emsfb_pro' => 2), array('emailSupporter' => ''));
$GLOBALS['W']['cron'] = true;
$r = call_plan($admin, $fp());
check('a cron request is refused (no human click there)', $r && !$r->ok && $r->code === 403 && get_option('emsfb_pro') === 2);
reset_world(array('emsfb_pro' => 2), array('emailSupporter' => ''));
$GLOBALS['W']['nonce_ok'] = false;
$r = call_plan($admin, $fp());
check('a bad nonce is refused', $r && !$r->ok && $r->code === 403 && get_option('emsfb_pro') === 2);

// 5. Lapsed Pro (0) with a stored key: 409, then confirmed
reset_world(array('emsfb_pro' => 0, 'emsfb_pro_activeCode' => 'KEY-123'), array('activeCode' => 'KEY-123', 'AdnSS' => 1, 'AdnSPF' => 1, 'AdnTLG' => 1, 'emailSupporter' => ''));
$r = call_plan($admin, $fp());
check('lapsed Pro + key: 409 downgrade_confirmation_required, nothing written', $r && !$r->ok && $r->code === 409 && $r->data['code'] === 'downgrade_confirmation_required' && $r->data['activation_code_present'] === true && get_option('emsfb_pro') === 0, $r ? $r->data : null);
$r = call_plan($admin, $fp(array('downgrade_confirmed' => true)));
$s = $GLOBALS['W']['saved_settings'];
check('confirmed: Free Plus, key removed, previous 0', $r && $r->ok && get_option('emsfb_pro') === 3 && $r->data['activation_code_removed'] === true && $r->data['previous_package_type'] === 0 && get_option('emsfb_pro_activeCode', null) === null && $s->activeCode === '');
check('confirmed: Pro add-ons off (SMS, Telegram), Free Plus add-ons kept (Stripe)', $s->AdnSS === 0 && $s->AdnTLG === 0 && (int) $s->AdnSPF === 1 && in_array('AdnSS', $r->data['disabled_addons'], true));
$acts = array_values(array_filter($GLOBALS['W']['actions'], function ($a) { return $a['hook'] === 'efb_plan_changed'; }));
check('lapsed → Free Plus: efb_plan_changed(0, 3)', count($acts) === 1 && $acts[0]['args'][0] === 0 && $acts[0]['args'][1] === 3);

// 6. Unchanged paths
reset_world(array('emsfb_pro' => 3), array('emailSupporter' => ''));
$r = call_plan($admin, $fp());
check('choosing the plan in use: silent success, plan_changed false, no action', $r && $r->ok && $r->data['plan_changed'] === false && $r->data['unchanged'] === true && count($GLOBALS['W']['actions']) === 0 && $r->data['previous_package_type'] === 3);
reset_world(array('emsfb_pro' => 3), array('emailSupporter' => ''));
$r = call_plan($admin, array('selected_plan' => 'pro', 'source' => 'settings', 'timestamp' => 1));
check('Pro without a key opens the price page and does not change the plan or fire the action', $r && $r->ok && $r->data['plan_changed'] === false && get_option('emsfb_pro') === 3 && count($GLOBALS['W']['actions']) === 0 && strpos($r->data['redirect_url'], '#pricing') !== false);
reset_world(array('emsfb_pro' => 3), array('emailSupporter' => ''));
$r = call_plan($admin, array('selected_plan' => 'free', 'source' => 'settings', 'timestamp' => 1));
check('Free Plus → Free still needs the downgrade confirmation (409)', $r && !$r->ok && $r->code === 409 && get_option('emsfb_pro') === 3);
reset_world(array('emsfb_pro' => -1), array('emailSupporter' => ''));
$r = call_plan($admin, $fp());
$acts = array_values(array_filter($GLOBALS['W']['actions'], function ($a) { return $a['hook'] === 'efb_plan_changed'; }));
check('no plan chosen yet (-1) → Free Plus: works, efb_plan_changed(2, 3)', $r && $r->ok && get_option('emsfb_pro') === 3 && count($acts) === 1 && $acts[0]['args'][0] === 2);
reset_world(array('emsfb_pro' => -1), array('emailSupporter' => ''));
$r = call_plan($admin, array('selected_plan' => 'free', 'source' => 'setup', 'timestamp' => 1));
check('no plan chosen yet (-1) → Free: the choice is saved and reported (efb_plan_changed(2, 2))', $r && $r->ok && get_option('emsfb_pro') === 2 && count($GLOBALS['W']['actions']) === 1);

// 7. efb_var.can_change_plan
reset_world(array('emsfb_pro' => 2), array(), array('manage_options' => true));
$v = \Emsfb\Builder_Api::localize_vars(array('pro' => 0), 'create');
check('efb_var.can_change_plan is true for an administrator', $v['can_change_plan'] === true);
add_filter('efb_can_change_plan', function () { return false; });
$v = \Emsfb\Builder_Api::localize_vars(array('pro' => 0), 'create');
check('efb_var.can_change_plan follows the filter', $v['can_change_plan'] === false);

echo "\n========================================\n";
echo "RESULTS: $pass passed, $fail failed\n";
echo "========================================\n";
exit($fail > 0 ? 1 : 0);
