<?php
/**
 * Not a test. WordPress stand-ins for the F21 unit tests (tests/test-renewal-*.php): options, transients, user meta,
 * hooks, translations (English), escaping, wp_date() with the site's time zone, a recording mailer (the plugin's
 * send_email_state_new() through a fake efbFunction that runs the phpmailer_init hooks on a fake PHPMailer), and
 * the clock of Renewal_Reminder. Several "sites" (multisite) are separate option tables: efb_rt_site($n).
 */

if (!defined('ABSPATH')) define('ABSPATH', __DIR__ . '/');
if (!defined('DAY_IN_SECONDS')) define('DAY_IN_SECONDS', 86400);
if (!defined('HOUR_IN_SECONDS')) define('HOUR_IN_SECONDS', 3600);
if (!defined('MINUTE_IN_SECONDS')) define('MINUTE_IN_SECONDS', 60);
if (!defined('EMSFB_SERVER_URL')) define('EMSFB_SERVER_URL', 'https://whitestudio.team');

$GLOBALS['efb_rt'] = array('site' => 1, 'opts' => array(1 => array()), 'meta' => array(), 'hooks' => array(), 'mails' => array(), 'locale' => 'en_US', 'user_locale' => null, 'caps' => array('manage_options' => true), 'uid' => 1, 'now' => 1763700000, 'json' => null, 'switched' => array(), 'mail_ok' => true, 'screen' => null, 'scheduled' => array());

function efb_rt_site($n) { $GLOBALS['efb_rt']['site'] = $n; if (!isset($GLOBALS['efb_rt']['opts'][$n])) $GLOBALS['efb_rt']['opts'][$n] = array(); }
function efb_rt_reset() { $s = $GLOBALS['efb_rt']['site']; $GLOBALS['efb_rt']['opts'][$s] = array(); $GLOBALS['efb_rt']['meta'] = array(); $GLOBALS['efb_rt']['mails'] = array(); }
function &efb_rt_opts() { $s = $GLOBALS['efb_rt']['site']; return $GLOBALS['efb_rt']['opts'][$s]; }

function get_option($k, $d = false) { $o = &efb_rt_opts(); return array_key_exists($k, $o) ? $o[$k] : $d; }
function update_option($k, $v, $autoload = null) { $o = &efb_rt_opts(); $o[$k] = $v; return true; }
function add_option($k, $v = '', $dep = '', $autoload = 'yes') { $o = &efb_rt_opts(); if (array_key_exists($k, $o)) return false; $o[$k] = $v; return true; }
function delete_option($k) { $o = &efb_rt_opts(); if (!array_key_exists($k, $o)) return false; unset($o[$k]); return true; }
function get_transient($k) { $o = &efb_rt_opts(); return isset($o['_t_' . $k]) && $o['_t_' . $k][1] > $GLOBALS['efb_rt']['now'] ? $o['_t_' . $k][0] : false; }
function set_transient($k, $v, $ttl = 0) { $o = &efb_rt_opts(); $o['_t_' . $k] = array($v, $GLOBALS['efb_rt']['now'] + $ttl); return true; }
function delete_transient($k) { $o = &efb_rt_opts(); unset($o['_t_' . $k]); return true; }
function get_user_meta($u, $k, $single = false) { return isset($GLOBALS['efb_rt']['meta'][$u][$k]) ? $GLOBALS['efb_rt']['meta'][$u][$k] : ''; }
function update_user_meta($u, $k, $v) { $GLOBALS['efb_rt']['meta'][$u][$k] = $v; return true; }
function get_current_user_id() { return $GLOBALS['efb_rt']['uid']; }
function current_user_can($c) { return !empty($GLOBALS['efb_rt']['caps'][$c]); }

function add_action($h, $cb, $p = 10, $a = 1) { $GLOBALS['efb_rt']['hooks'][$h][] = array($cb, $p); return true; }
function add_filter($h, $cb, $p = 10, $a = 1) { return add_action($h, $cb, $p, $a); }
function remove_action($h, $cb, $p = 10) { if (empty($GLOBALS['efb_rt']['hooks'][$h])) return false; foreach ($GLOBALS['efb_rt']['hooks'][$h] as $i => $x) if ($x[0] === $cb) { unset($GLOBALS['efb_rt']['hooks'][$h][$i]); return true; } return false; }
function do_action($h, ...$a) { foreach (isset($GLOBALS['efb_rt']['hooks'][$h]) ? $GLOBALS['efb_rt']['hooks'][$h] : array() as $x) call_user_func_array($x[0], $a); }
function apply_filters($h, $v, ...$a) { foreach (isset($GLOBALS['efb_rt']['hooks'][$h]) ? $GLOBALS['efb_rt']['hooks'][$h] : array() as $x) $v = call_user_func_array($x[0], array_merge(array($v), $a)); return $v; }
function efb_rt_hook_count($h) { return isset($GLOBALS['efb_rt']['hooks'][$h]) ? count($GLOBALS['efb_rt']['hooks'][$h]) : 0; }

function get_locale() { return $GLOBALS['efb_rt']['locale']; }
function determine_locale() { $s = $GLOBALS['efb_rt']['switched']; return $s ? end($s) : ($GLOBALS['efb_rt']['user_locale'] ?: $GLOBALS['efb_rt']['locale']); }
function switch_to_locale($l) { $GLOBALS['efb_rt']['switched'][] = $l; return true; }
function restore_previous_locale() { array_pop($GLOBALS['efb_rt']['switched']); return true; }
function is_rtl() { return in_array(strtok(determine_locale(), '_'), array('ar', 'he', 'fa'), true); }
function __($s, $d = null) { return $s; }
function _n($a, $b, $n, $d = null) { return (int) $n === 1 ? $a : $b; }
function esc_html($s) { return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8'); }
function esc_attr($s) { return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8'); }
function esc_html__($s, $d = null) { return esc_html($s); }
function esc_attr__($s, $d = null) { return esc_attr($s); }
function esc_url($u) { return preg_match('#^https?://#i', (string) $u) ? str_replace(array('&', "'", '"'), array('&#038;', '&#039;', '&quot;'), (string) $u) : ''; }
function esc_url_raw($u) { return (string) $u; }
function esc_js($s) { return addslashes((string) $s); }
function wp_json_encode($v, $f = 0) { return json_encode($v, $f); }
function wp_unslash($v) { return is_string($v) ? stripslashes($v) : $v; }
function wp_specialchars_decode($s, $q = ENT_NOQUOTES) { return htmlspecialchars_decode((string) $s, ENT_QUOTES); }
function is_email($e) { return is_string($e) && (bool) filter_var($e, FILTER_VALIDATE_EMAIL); }
function number_format_i18n($n, $d = 0) { return number_format((float) $n, $d); }
function get_bloginfo($k) { return $k === 'name' ? get_option('blogname', 'Example Site') : ''; }
function admin_url($p = '') { return 'https://site.example/wp-admin/' . ltrim($p, '/'); }
function home_url($p = '') { return 'https://site.example/' . ltrim($p, '/'); }
function wp_create_nonce($a) { return 'nonce-' . $a; }
function wp_date($fmt, $ts = null, $tz = null) {
	$tzs = get_option('timezone_string', 'UTC') ?: 'UTC';
	$d = new DateTime('@' . (int) $ts);
	$d->setTimezone(new DateTimeZone($tzs));
	return $d->format($fmt);
}
function get_setting_Emsfb($mode = 'decoded') { $s = get_option('emsfb_settings_obj', null); return $s === null ? (object) array('emailSupporter' => 'support@site.example', 'smtp' => '1') : $s; }
function emsfb_is_email_sending_enabled_efb($s) { return !get_option('efb_rt_no_mail'); }
function wp_next_scheduled($h) { return isset($GLOBALS['efb_rt']['scheduled'][$h]) ? $GLOBALS['efb_rt']['scheduled'][$h] : false; }
function wp_schedule_single_event($t, $h) { $GLOBALS['efb_rt']['scheduled'][$h] = $t; return true; }
function get_current_screen() { return $GLOBALS['efb_rt']['screen']; }

/** A PHPMailer stand-in: what wp_mail() would hand to phpmailer_init. */
class EfbRtMailer { public $Subject = ''; public $Body = ''; public $AltBody = ''; public $to = array(); }

/** The plugin's mailer, recorded: one entry per recipient, with the AltBody the hooks set. */
class EfbRtFn {
	public $farsi = null;
	public $calls = array();
	public function is_farsi_offline_license_efb() { return $this->farsi !== null ? $this->farsi : get_locale() === 'fa_IR'; }
	public function send_email_state_new($to, $sub, $cont, $pro, $state, $link, $st = 'null') {
		$this->calls[] = compact('to', 'sub', 'state');
		if (!$GLOBALS['efb_rt']['mail_ok']) return false;
		foreach ((array) $to as $t) {
			$m = new EfbRtMailer();
			$m->Subject = $sub; $m->Body = $cont; $m->to = array($t);
			do_action('phpmailer_init', $m);
			$GLOBALS['efb_rt']['mails'][] = array('site' => $GLOBALS['efb_rt']['site'], 'to' => $t, 'subject' => $sub, 'html' => $cont, 'alt' => $m->AltBody, 'state' => $state);
		}
		return true;
	}
	public function update_pro_status_efb($code) {
		$j = $GLOBALS['efb_rt']['json'];
		if ($j && isset($j->state) && $j->state === 'deactive') update_option('emsfb_pro', 0); elseif ($j && isset($j->state) && $j->state === 'active') update_option('emsfb_pro', 1);
		\Emsfb\Renewal_Reminder::store($j, $code);
		return true;
	}
}
$GLOBALS['efb_rt_fn'] = new EfbRtFn();
function get_efbFunction() { return $GLOBALS['efb_rt_fn']; }

require_once __DIR__ . '/../includes/class-Emsfb-renewal-reminder.php';
\Emsfb\Renewal_Reminder::$clock = function () { return $GLOBALS['efb_rt']['now']; };

/** The licence server's answer with a v1 field, ends in $days days (server clock = site clock + $skew). */
function efb_rt_answer($days, array $over = array(), $state = 'active', $skew = 0) {
	$server = $GLOBALS['efb_rt']['now'] + $skew;
	$l = array('v' => 1, 'expires_at' => gmdate('Y-m-d\TH:i:s\Z', $server + (int) round($days * 86400)), 'server_time' => gmdate('Y-m-d\TH:i:s\Z', $server), 'auto_renew' => false, 'renew_url' => 'https://whitestudio.team/checkout?renew=tok_e2e_short_lived', 'lapse_effect' => 'pause_pro');
	foreach ($over as $k => $v) { if ($v === '__unset__') unset($l[$k]); else $l[$k] = $v; }
	return json_decode(json_encode(array('r' => true, 'state' => $state, 'pakcage' => 1, 'license' => $l)));
}

/** A Pro site: the plan, a code made for the test host (placeholder; never a real key), the answer stored. */
define('EFB_RT_CODE', md5('site.example') . '@E2EPLACEHOLDER0000');
function efb_rt_pro($days, array $over = array(), $state = 'active', $skew = 0) {
	update_option('emsfb_pro', $state === 'deactive' ? 0 : 1);
	update_option('emsfb_pro_activeCode', EFB_RT_CODE);
	$GLOBALS['efb_rt']['json'] = efb_rt_answer($days, $over, $state, $skew);
	return \Emsfb\Renewal_Reminder::store($GLOBALS['efb_rt']['json'], EFB_RT_CODE);
}

$GLOBALS['efb_rt_pass'] = 0; $GLOBALS['efb_rt_fail'] = 0;
function t($name, $actual, $expected) {
	if ($actual === $expected) { $GLOBALS['efb_rt_pass']++; echo "[PASS] {$name}\n"; }
	else { $GLOBALS['efb_rt_fail']++; echo "[FAIL] {$name}\n  expected: " . var_export($expected, true) . "\n  actual:   " . var_export($actual, true) . "\n"; }
}
function efb_rt_done() {
	echo "\nRESULTS: {$GLOBALS['efb_rt_pass']} passed, {$GLOBALS['efb_rt_fail']} failed\n";
	exit($GLOBALS['efb_rt_fail'] ? 1 : 0);
}
