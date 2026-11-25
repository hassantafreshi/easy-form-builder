<?php
/**
 * QA1-014, QA3-004, QA3-006 and the plan gate of the add-on menus, without WordPress.
 *  - QA1-014: the catalogue script's address carries the site's host without its port (addons.js127.0.0.1:8091 answered
 *    404, which Chrome blocks): Addon::catalogue_src_efb() with HTTP_HOST example.test:8080 has no ':' in the host part;
 *    "www." goes, the host is URL-encoded;
 *  - QA3-004: the Pro card's title takes the card's own colour (WordPress' heading colour on the dark card was 1.02:1);
 *  - QA3-006: the Pro card's wrap has an id that names the plugin, so admin-efb.js (which removes every other child of
 *    #wpbody-content) keeps it: the Telegram screen without Pro showed nothing; the Telegram script stays quiet there;
 *  - Human Shield (Pro) shows the Pro card on another plan; Address search (Free Plus and Pro) leaves the menu on Free
 *    and its address says which plan has it.
 * The screens themselves were checked in a browser on a clean site (Free Plus / Free): the card visible, axe without a
 * serious or critical issue, no console error.
 *
 * Run: php tests/test-addon-screens-gating.php
 */
define('ABSPATH', __DIR__ . '/');
$passed = 0; $failed = 0;
function t($name, $actual, $expected) {
	global $passed, $failed;
	if ($actual === $expected) { echo "[PASS] {$name}\n"; $passed++; return; }
	echo "[FAIL] {$name}: expected " . json_encode($expected) . ', got ' . json_encode($actual) . "\n";
	$failed++;
}
$root = dirname(__DIR__);
function untrailingslashit($s) { return rtrim((string) $s, '/\\'); }
function home_url() { return 'https://example.test:8080/'; }
function wp_parse_url($u, $c = -1) { return parse_url($u, $c); }
// The class builds itself at the end of its file (it needs WordPress); its pure helper is lifted from the source.
$addon_src = (string) file_get_contents($root . '/includes/admin/class-Emsfb-addon.php');
preg_match('/\tpublic static function catalogue_src_efb\(.*?\n\t}\n/s', $addon_src, $mm);
eval('namespace Emsfb; class Addon { ' . (isset($mm[0]) ? $mm[0] : '') . ' }');

$src = \Emsfb\Addon::catalogue_src_efb('https://whitestudio.team/', 'example.test:8080');
$host = substr($src, strlen('https://whitestudio.team/wp-json/wl/v1/addons.js'));
t('QA1-014: HTTP_HOST example.test:8080 gives a host part without ":"', array($host, strpos($host, ':')), array('example.test', false));
t('... the address is the server\'s own form (addons.js<host>)', $src, 'https://whitestudio.team/wp-json/wl/v1/addons.jsexample.test');
t('... "www." and capitals go, as for the add-on downloads', \Emsfb\Addon::catalogue_src_efb('https://easyformbuilder.ir', 'WWW.Example.TEST:443'), 'https://easyformbuilder.ir/wp-json/wl/v1/addons.jsexample.test');
t('... 127.0.0.1:8091 (QA-1\'s site) becomes 127.0.0.1', \Emsfb\Addon::catalogue_src_efb('https://whitestudio.team', '127.0.0.1:8091'), 'https://whitestudio.team/wp-json/wl/v1/addons.js127.0.0.1');
t('... without a Host header, home_url()\'s host', \Emsfb\Addon::catalogue_src_efb('https://whitestudio.team', ''), 'https://whitestudio.team/wp-json/wl/v1/addons.jsexample.test');
$page = (string) file_get_contents($root . '/includes/admin/class-Emsfb-addon.php');
t('... and the page registers it through catalogue_src_efb()', strpos($page, "self::catalogue_src_efb(\$domain, \$http_host)") !== false && strpos($page, "'/wp-json/wl/v1/addons.js' .\$server_name") === false, true);

$fn = (string) file_get_contents($root . '/includes/functions.php');
$gate = preg_match('/public function render_pro_gate_efb\(.*?\n\t}\n/s', $fn, $m) ? $m[0] : '';
t('QA3-006: the Pro card\'s wrap carries an id that names the plugin (admin-efb.js keeps it)', (bool) preg_match('/<div class="wrap" id="efb-[a-z-]+">/', $gate), true);
$adm = (string) file_get_contents($root . '/includes/admin/assets/js/admin-efb.js');
t('... (admin-efb.js keeps children whose id has "efb")', strpos($adm, "els.children[i].id.toLowerCase().indexOf('efb') == -1") !== false, true);
t('QA3-004: the card\'s title takes the card\'s colour (color:inherit)', (bool) preg_match('/<h2 style="[^"]*color:inherit;[^"]*">/', $gate), true);

$vend = getenv('EFB_VENDORES_DIR') ?: '/home/user/vendores-efb';
$vdir = function ($d) use ($root, $vend) { foreach (array($root . '/vendor/' . $d, $vend . '/' . $d) as $x) if (is_dir($x)) return $x; return ''; };
if ($vdir('telegram') !== '') {
	$tj = (string) file_get_contents($vdir('telegram') . '/assets/js/telegram-efb.js');
	t('QA3-006: the Telegram script stays quiet on the Pro card (no console error)', strpos($tj, "console.error('[EFB Telegram JS] add_telegram_dashboard_efb() - ERROR: tab_container_efb not found!')") === false, true);
}
if ($vdir('human-shield') !== '') {
	$hs = (string) file_get_contents($vdir('human-shield') . '/class-Emsfb-human-shield-admin.php');
	$render = preg_match('/public function render\(\) \{.*?\n\t}\n/s', $hs, $m) ? $m[0] : '';
	t('Human Shield (Pro) shows the Pro card on another plan, before its app', strpos($render, 'render_pro_gate_efb(') !== false && strpos($render, 'render_pro_gate_efb(') < strpos($render, 'efb-human-shield-app'), true);
}
if ($vdir('address') !== '') {
	$ad = (string) file_get_contents($vdir('address') . '/class-Emsfb-address-lookup.php');
	t('Address search (Free Plus and Pro) leaves the menu on Free', strpos($ad, "add_submenu_page(self::plan_ok() ? 'Emsfb' : ''") !== false, true);
	$pg = preg_match('/public static function page\(\) \{.*?\n\t}\n/s', $ad, $m) ? $m[0] : '';
	t('... and its address says which plan has it', strpos($pg, "if (!self::plan_ok()) {") !== false && strpos($pg, 'addrLookupPlan') !== false, true);
}

echo "\nRESULTS: {$passed} passed, {$failed} failed\n";
exit($failed ? 1 : 0);
