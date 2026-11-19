<?php
/**
 * UX-01 (batch B4): Emsfb\Notices, the one notice manager of Easy Form Builder, without WordPress (stubs below).
 *
 * BUG: ten admin_notices sources stacked four notices on every EFB screen (a whole phone screen), the payment readiness
 * one had no dismiss, three were English on fa_IR / ar sites. Checked here:
 *  - order: the highest priority first; equal priorities keep their registration order; an "error" (a payment failing
 *    now) before everything; a notice taken in from admin_notices gets its priority from its severity;
 *  - one notice on an EFB screen: the most important above, the others in the list behind "Notices" with their count;
 *    the Create (builder) screen: none above; other screens: each source where it was, nothing grouped;
 *  - every notice is marked (data-efb-nm) and, inside the center, gets WordPress' "inline" class (common.js would move it
 *    out of the list); "Remind me in 7 days" per user hides a source until its time is up;
 *  - a callback that printed only a script is printed as it is, not counted; an empty one is nothing; "efb" sources stay
 *    off other screens; a broken callback does not break the screen;
 *  - Easy Form Builder's own callbacks registered straight on admin_notices are taken in (absorb()), never another
 *    plugin's; the sources of B4's files are registered with the manager (not admin_notices) and readiness is no notice.
 *
 * Run: php tests/test-notices-manager.php
 */
namespace {
	define('ABSPATH', __DIR__ . '/');
	define('DAY_IN_SECONDS', 86400);
	// The plugin's folder for absorb(): includes/ here, so this test file stands for another plugin's file.
	define('EMSFB_PLUGIN_DIRECTORY', dirname(__DIR__) . '/includes/');
	$GLOBALS['t_screen'] = 'toplevel_page_Emsfb';
	$GLOBALS['t_meta'] = array();
	$GLOBALS['t_actions'] = array();
	$GLOBALS['wp_filter'] = array();
	class T_Hook { public $callbacks = array(); }
	function add_filter($h, $cb, $p = 10, $n = 1) { $GLOBALS['t_actions'][] = array($h, $cb, $p); return true; }
	function add_action($h, $cb, $p = 10, $n = 1) {
		$GLOBALS['t_actions'][] = array($h, $cb, $p);
		if ($h === 'admin_notices') {
			if (!isset($GLOBALS['wp_filter']['admin_notices'])) $GLOBALS['wp_filter']['admin_notices'] = new T_Hook();
			$key = is_string($cb) ? $cb : (is_array($cb) ? (is_object($cb[0]) ? spl_object_hash($cb[0]) : $cb[0]) . '::' . $cb[1] : spl_object_hash($cb));
			$GLOBALS['wp_filter']['admin_notices']->callbacks[$p][$key] = array('function' => $cb, 'accepted_args' => $n);
		}
		return true;
	}
	function remove_action($h, $cb, $p = 10) {
		if (!isset($GLOBALS['wp_filter'][$h])) return false;
		foreach ($GLOBALS['wp_filter'][$h]->callbacks[$p] as $k => $c) if ($c['function'] === $cb) unset($GLOBALS['wp_filter'][$h]->callbacks[$p][$k]);
		return true;
	}
	function get_current_screen() { return $GLOBALS['t_screen'] === null ? null : (object) array('id' => $GLOBALS['t_screen']); }
	function get_current_user_id() { return 1; }
	function get_user_meta($u, $k, $s = false) { return isset($GLOBALS['t_meta'][$k]) ? $GLOBALS['t_meta'][$k] : ''; }
	function update_user_meta($u, $k, $v) { $GLOBALS['t_meta'][$k] = $v; return true; }
	function esc_attr($s) { return htmlspecialchars((string) $s, ENT_QUOTES); }
	function esc_html($s) { return htmlspecialchars((string) $s, ENT_QUOTES); }
	function admin_url($p = '') { return 'http://site.test/wp-admin/' . $p; }
	function wp_create_nonce($a) { return 'nonce'; }
	function wp_json_encode($d) { return json_encode($d); }
	function number_format_i18n($n) { return (string) $n; }
	function __($s, $d = '') { return $s; }
	function _n($a, $b, $n, $d = '') { return $n == 1 ? $a : $b; }
	function wp_normalize_path($p) { return str_replace('\\', '/', $p); }
	require dirname(__DIR__) . '/includes/class-Emsfb-notices.php';
}

namespace Emsfb {
	function t_note($id, $cls, $extra = '') { return function () use ($id, $cls, $extra) { echo '<div class="notice ' . $cls . ' efb-' . $id . '" id="t-' . $id . '"><p>' . $id . '</p></div>' . $extra; }; }
	// An add-on's notice registered straight on admin_notices (a closure of the Emsfb namespace), as Human Shield does.
	function t_addon_closure() { return function () { echo '<div class="notice notice-error efb-notice t-addon"><p>Add-on could not load</p></div>'; }; }
}

namespace {
	use Emsfb\Notices;
	$passed = 0; $failed = 0;
	function t($name, $actual, $expected) {
		global $passed, $failed;
		if ($actual === $expected) { $passed++; echo "[PASS] {$name}\n"; return; }
		$failed++; echo "[FAIL] {$name}\n  expected: " . var_export($expected, true) . "\n  actual:   " . var_export($actual, true) . "\n";
	}
	function render_now() { ob_start(); Notices::render(); return ob_get_clean(); }
	/** id => where (top | list | screen) of the last render, from the markup. */
	function where($html) {
		$out = array();
		$top = preg_match('#<div class="efb efb-nm-top">(.*?)</div></div>#s', $html, $m) ? $m[1] : '';
		$list = preg_match('#<div class="efb efb-nm-list" id="efbNmList"[^>]*>(.*)</div></div>#s', $html, $m2) ? $m2[1] : '';
		if (preg_match_all('/data-efb-nm="([a-z0-9_]+)"/', $html, $mm)) foreach (array_unique($mm[1]) as $id) $out[$id] = strpos($top, 'data-efb-nm="' . $id . '"') !== false ? 'top' : (strpos($list, 'data-efb-nm="' . $id . '"') !== false ? 'list' : 'screen');
		return $out;
	}

	Notices::reset();
	Notices::add('low', array('render' => \Emsfb\t_note('low', 'notice-info'), 'priority' => 10));
	Notices::add('high', array('render' => \Emsfb\t_note('high', 'notice-warning'), 'priority' => 80));
	Notices::add('mid_a', array('render' => \Emsfb\t_note('mid_a', 'notice-warning'), 'priority' => 50));
	Notices::add('mid_b', array('render' => \Emsfb\t_note('mid_b', 'notice-warning'), 'priority' => 50));
	Notices::add('empty', array('render' => function () {}, 'priority' => 99));
	Notices::add('script', array('render' => function () { echo '<script>var tScript=1;</script>'; }, 'priority' => 99));
	Notices::add('efb_only', array('render' => \Emsfb\t_note('efb_only', 'notice-info'), 'priority' => 5, 'screens' => 'efb'));
	Notices::add('broken', array('render' => function () { throw new \RuntimeException('boom'); }, 'priority' => 70));
	Notices::add('dyn', array('render' => \Emsfb\t_note('dyn', 'notice-info'), 'priority' => function () { return 60; }, 'snooze' => false));

	$loose = '';
	$items = Notices::collect('toplevel_page_Emsfb', $loose);
	t('order: the highest priority first, equal priorities in registration order, a callable priority, empty and broken ones dropped', array_map(function ($i) { return $i['id']; }, $items), array('high', 'dyn', 'mid_a', 'mid_b', 'low', 'efb_only'));
	t('a callback that printed only a script is kept as it is, not a notice', $loose, '<script>var tScript=1;</script>');
	t('severity from the markup when the source does not give one', $items[0]['severity'], 'warning');

	// An EFB screen: one above, the rest in the list.
	$h = render_now();
	$w = where($h);
	t('EFB screen: one notice above (the most important), every other one in the list', $w, array('high' => 'top', 'dyn' => 'list', 'mid_a' => 'list', 'mid_b' => 'list', 'low' => 'list', 'efb_only' => 'list'));
	t('EFB screen: the "Notices" button with the list\'s count, the list closed and labelled by the button', array((bool) preg_match('#class="efb efb-nm-count">5</span>#', $h), strpos($h, 'id="efbNmList" role="region" aria-labelledby="efbNmToggle" hidden') !== false, strpos($h, 'aria-expanded="false" aria-controls="efbNmList"') !== false), array(true, true, true));
	t('EFB screen: every notice in the center has WordPress\' "inline" class (common.js leaves it in place) and data-efb-nm', array(substr_count($h, ' inline"'), substr_count($h, 'data-efb-nm="')), array(6, 6));
	t('the script-only output is printed (outside the center), once', substr_count($h, 'var tScript=1;'), 1);
	t('the snooze control is offered to the sources that have none of their own ("dyn" has its own)', array(strpos($h, '"ids":["low","high","mid_a","mid_b","empty","script","efb_only","broken"]') !== false, strpos($h, 'Remind me in 7 days') !== false), array(true, true));

	// The form builder's own screen: none above.
	$GLOBALS['t_screen'] = 'easy-form-builder_page_Emsfb_create';
	$w = where(render_now());
	t('Create (the builder): no notice above the canvas, all in the list', array_values(array_unique($w)), array('list'));

	// Another screen: each where it was, "efb" sources not there, no center.
	$GLOBALS['t_screen'] = 'dashboard';
	$h = render_now();
	t('other screens: each notice printed as before (no center, no "inline"), "efb" sources left out', array(strpos($h, 'efbNoticeCenter') === false, array_keys(where($h)), strpos($h, ' inline"') === false), array(true, array('high', 'dyn', 'mid_a', 'mid_b', 'low'), true));

	// Snooze: per user, until its time.
	$GLOBALS['t_screen'] = 'toplevel_page_Emsfb';
	$GLOBALS['t_meta'][Notices::META_SNOOZE] = array('high' => time() + 7 * DAY_IN_SECONDS, 'mid_a' => time() - 10, 'dyn' => time() + 99);
	$w = where(render_now());
	t('"Remind me in 7 days": a snoozed source is gone and the next takes its place; an ended snooze shows again; a source with its own "later" ignores it', array(isset($w['high']), $w['dyn'] ?? null, $w['mid_a'] ?? null), array(false, 'top', 'list'));
	$GLOBALS['t_meta'] = array();

	// Error: a payment failing now comes before everything.
	Notices::add('pay_fail', array('render' => \Emsfb\t_note('pay_fail', 'notice-error'), 'priority' => 5));
	$w = where(render_now());
	t('an "error" (a payment failing now) is shown first, whatever its priority', $w['pay_fail'], 'top');

	// absorb(): Easy Form Builder's own callbacks straight on admin_notices are taken in; another plugin's are not.
	Notices::reset();
	$GLOBALS['wp_filter'] = array();
	add_action('admin_notices', \Emsfb\t_addon_closure());
	function t_other_plugin_notice() { echo '<div class="notice notice-info other-plugin"><p>Other</p></div>'; }
	add_action('admin_notices', 't_other_plugin_notice');
	Notices::add('mine', array('render' => \Emsfb\t_note('mine', 'notice-warning'), 'priority' => 80));
	$n = Notices::absorb();
	$src = Notices::sources();
	$abs = array_values(array_filter(array_keys($src), function ($k) { return strpos($k, 'x_') === 0; }));
	t('absorb(): the add-on\'s closure (Emsfb namespace) is taken off admin_notices into the manager; another plugin\'s stays', array($n, count($abs), isset($GLOBALS['wp_filter']['admin_notices']->callbacks[10]['t_other_plugin_notice'])), array(1, 1, true));
	$items = Notices::collect('toplevel_page_Emsfb');
	t('a notice taken in gets its priority from its severity (error: first)', array($items[0]['id'] === $abs[0], $items[0]['priority']), array(true, 90));
	$id2 = $abs[0];
	Notices::reset();
	$GLOBALS['wp_filter'] = array();
	add_action('admin_notices', \Emsfb\t_addon_closure());
	Notices::absorb();
	t('the id of a closure taken in is the same on the next request (its snooze is kept)', in_array($id2, array_keys(Notices::sources()), true), true);

	// mark()
	t('mark(): data-efb-nm on every .notice div; "inline" only inside the center', array(Notices::mark('<div class="notice notice-info" id="a"><div class="x"></div></div>', 'a1', true), Notices::mark('<div id="b" class="notice">x</div>', 'b1', false)), array('<div data-efb-nm="a1" class="notice notice-info inline" id="a"><div class="x"></div></div>', '<div id="b" data-efb-nm="b1" class="notice">x</div>'));

	// B4's sources register with the manager, not with admin_notices; readiness is not a notice any more.
	$root = dirname(__DIR__) . '/includes/';
	$files = array('class-Emsfb-request-health.php' => 'request_health', 'class-Emsfb-split.php' => 'split_addons', 'class-Emsfb-spam-notice.php' => 'captcha_unavailable', 'class-Emsfb-email-monitor.php' => 'email_delivery', 'class-Emsfb-renewal-reminder.php' => 'renewal', 'payments/class-Emsfb-payment-admin.php' => 'payment_addon_version');
	$ok = array();
	foreach ($files as $f => $id) {
		$src = file_get_contents($root . $f);
		$reg = preg_match('/Notices::add\(\s*\'' . $id . '\'/', $src) === 1;
		// admin_notices only as the fallback when the manager is missing (else branch).
		$direct = preg_match_all("/add_action\(\s*'admin_notices'/", $src);
		$ok[$f] = $reg && $direct <= 2 && strpos($src, "else add_action('admin_notices'") !== false || $reg && preg_match('/\} else \{\s*\n\s*add_action\(\s*\'admin_notices\'/', $src) === 1;
	}
	t('the sources of request health, SPLIT, spam, e-mail delivery, renewal and payments register with the manager', $ok, array_fill_keys(array_keys($files), true));
	t('payment readiness is not printed as a notice any more (Payments has the summary)', array(strpos(file_get_contents($root . 'payments/class-Emsfb-payment-admin.php'), 'Readiness::admin_notice_html(') === false, strpos(file_get_contents($root . 'payments/class-Emsfb-payment-admin.php'), 'function readiness_summary(') !== false), array(true, true));
	t('the e-mail delivery notice is a warning ("error" is kept for payments failing now)', array(strpos(file_get_contents($root . 'class-Emsfb-email-monitor.php'), 'notice notice-error is-dismissible efb-delivery-notice') === false, strpos(file_get_contents($root . 'class-Emsfb-email-monitor.php'), 'notice notice-warning is-dismissible efb-delivery-notice') !== false), array(true, true));

	echo "\nRESULTS: {$passed} passed, {$failed} failed\n";
	exit($failed ? 1 : 0);
}
