<?php
/**
 * The Stripe and PayPal add-ons' older payments screens (page=Emsfb_stripe_payment / Emsfb_paypal_payment):
 *
 *  QA3-010  every message the screens' AJAX answers is a gettext string of the add-on's own text domain (no literal
 *           wp_send_json_error( '…' ), no English fallback such as 'Refund failed'), each with a translators comment,
 *           and each is in the add-on's phrase list (languages/efb-<slug>.pot);
 *  QA-5     with no older payment the screen has no parent in the menu, so WordPress found no title for it and
 *           admin-header.php passed null to strip_tags() ("PHP Deprecated: strip_tags(): Passing null"): the screen's
 *           load hook gives it its title.
 * Each check fails on the add-ons before this change.
 *
 * Run: php tests/test-addon-older-screens.php   (EFB_ADDONS_REPO: the add-ons repository; default the vendor/ links)
 */
define('ABSPATH', __DIR__ . '/');
$passed = 0; $failed = 0;
$t = function ($name, $actual, $expected) {
	global $passed, $failed;
	if ($actual === $expected) { echo "[PASS] {$name}\n"; $passed++; return; }
	echo "[FAIL] {$name}: expected " . var_export($expected, true) . ', got ' . var_export($actual, true) . "\n";
	$failed++;
};
$addons = getenv('EFB_ADDONS_REPO') ?: dirname((string) @readlink(__DIR__ . '/../vendor/stripe'));
if (!is_dir($addons . '/stripe')) $addons = __DIR__ . '/../vendor';

foreach (array('stripe' => 'Stripe', 'paypal' => 'PayPal') as $slug => $name) {
	$file = $addons . '/' . $slug . '/class-Emsfb-' . $slug . '-payment.php';
	$src = preg_replace('#^\s*//.*$#m', '', (string) file_get_contents($file)); // code only, not the comments
	echo "\n=== {$name}\n";
	preg_match_all("/wp_send_json_error\\(\\s*'[^']*'/", $src, $lit);
	preg_match_all("/\\?\\?\\s*'[A-Z][a-z][^']*'/", $src, $fb);
	$t("QA3-010 {$name}: no literal message in an AJAX answer (wp_send_json_error( '…' ), ?? 'English')", array($lit[0], $fb[0]), array(array(), array()));
	preg_match_all("/(?:esc_html__|esc_attr__|__|_e|esc_html_e)\\(\\s*'(?:[^'\\\\]|\\\\.)*'\\s*,\\s*'([^']+)'\\s*\\)/", $src, $doms);
	$t("QA3-010 {$name}: every gettext call of the screen uses the add-on's text domain efb-{$slug}", array_values(array_unique($doms[1])), array('efb-' . $slug));
	// The messages of msg_efb(): each with a translators comment and in the phrase list.
	$i = strpos($src, 'function msg_efb');
	$body = $i !== false ? substr($src, $i, strpos($src, 'return isset', $i) - $i) : '';
	preg_match_all("#/\\*\\s*translators:[^*]*\\*/\\s*'[a-z_]+'\\s*=>\\s*__\\(\\s*'((?:[^'\\\\]|\\\\.)*)'#", $body, $withc);
	preg_match_all("/=>\\s*__\\(\\s*'((?:[^'\\\\]|\\\\.)*)'/", $body, $all);
	$t("QA3-010 {$name}: the screen's messages (msg_efb) each have a translators comment", array(count($all[1]) >= 7, count($withc[1]) === count($all[1])), array(true, true));
	$pot = (string) file_get_contents($addons . '/' . $slug . '/languages/efb-' . $slug . '.pot');
	$missing = array();
	foreach ($all[1] as $m) {
		$m = str_replace("\\'", "'", $m);
		if (strpos($pot, 'msgid "' . addcslashes($m, '"\\') . '"') === false) $missing[] = $m;
	}
	$t("QA3-010 {$name}: each of them is in the phrase list efb-{$slug}.pot", $missing, array());
}

/* QA-5: the title of the screen while it has no parent in the menu. */
echo "\n=== the screens' title without rows (strip_tags(null))\n";
$GLOBALS['efb_hooks'] = array();
$GLOBALS['efb_sub'] = array();
function add_action($h, $cb, $p = 10, $n = 1) { $GLOBALS['efb_hooks'][$h][] = $cb; return true; }
function get_option($k, $d = false) { return true; }
function get_role($r) { return null; }
function update_option($k, $v, $a = null) { return true; }
function efb_get_addon_phrases($g) { return array('stripeOlderTitle' => 'Older Stripe payments', 'paypalOlderTitle' => 'Older PayPal payments', 'stripes' => 'Stripe %s', 'paypals' => 'PayPal %s', 'pay_payments' => 'Payments'); }
function add_submenu_page($parent, $page_title, $menu_title, $cap, $slug, $cb = null) { $GLOBALS['efb_sub'][$slug] = $parent; return ($parent === '' ? 'admin_page_' : 'easy-form-builder_page_') . $slug; }
function wp_strip_all_tags($s) { return trim(strip_tags((string) $s)); }
function esc_html($s) { return htmlspecialchars((string) $s, ENT_QUOTES); }
function __($s, $d = null) { return $s; }
if (!defined('EMSFB_PLUGIN_DIRECTORY')) define('EMSFB_PLUGIN_DIRECTORY', __DIR__ . '/../');
$GLOBALS['wpdb'] = new class { public $prefix = 'wp_'; public function prepare($q) { return $q; } public function esc_like($s) { return $s; } public function get_var($q) { return null; } };
foreach (array('stripe' => array('StripePayment', 'Older Stripe payments'), 'paypal' => array('PaypalPayment', 'Older PayPal payments')) as $slug => $c) {
	require_once $addons . '/' . $slug . '/class-Emsfb-' . $slug . '-payment.php';
	$cls = '\\Emsfb\\' . $c[0];
	$o = (new ReflectionClass($cls))->newInstanceWithoutConstructor();
	$o->admin_menu();
	$page = 'Emsfb_' . $slug . '_payment';
	$hook = 'load-admin_page_' . $page;
	global $title;
	$title = null;
	foreach (isset($GLOBALS['efb_hooks'][$hook]) ? $GLOBALS['efb_hooks'][$hook] : array() as $cb) call_user_func($cb);
	$t("QA-5 {$c[1]}: no rows, so no parent in the menu; the screen's load hook gives it its title (never null for admin-header.php)", array($GLOBALS['efb_sub'][$page], $title), array('', $c[1]));
}

echo "\nRESULTS: {$passed} passed, {$failed} failed\n";
exit($failed ? 1 : 0);
