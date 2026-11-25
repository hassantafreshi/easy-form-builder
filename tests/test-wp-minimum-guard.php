<?php
/**
 * COMP-01 (owner decision 7: WordPress 6.3 or later), without WordPress.
 *
 *  - Emsfb::MIN_WP_VERSION_EFB is the "Requires at least" of emsfb.php and readme.txt;
 *  - on an older WordPress (an update copied onto a site that was not updated; WordPress before 5.8 does not even
 *    refuse the activation) the plugin loads nothing: no class of includes/, no hook but the notice, and the notice
 *    names both versions (in <bdi>), says the data is kept and links to Updates for whoever may update;
 *  - 6.3 and later pass; a bootstrap without $wp_version is not stopped on a guess;
 *  - mb_chr(), mb_ord() and utf8_encode() are gone from the three places Plugin Check flagged (mbstring is optional
 *    and WordPress polyfills them only from 7.1 / 6.9): the replacements give the same bytes and code points;
 *  - workarounds for WordPress before 6.3 that are dead now: block.json through register_block_type() only, the
 *    CAPTCHA scripts always deferred (6.3's strategy), wp_after_insert_post always, group flushes only where the
 *    object cache supports them (wp_cache_supports, not function_exists), %i in Install::install().
 *
 * Run: php tests/test-wp-minimum-guard.php
 */

$passed = 0;
$failed = 0;
function t($name, $actual, $expected) {
	global $passed, $failed;
	if ($actual === $expected) { echo "[PASS] {$name}\n"; $passed++; return; }
	echo "[FAIL] {$name}: expected " . json_encode($expected) . ', got ' . json_encode($actual) . "\n"; $failed++;
}
$root = dirname(__DIR__);

/* ---- minimal WordPress stand-ins: only what an inert Emsfb may touch ---- */
define('ABSPATH', sys_get_temp_dir() . '/');
define('EMSFB_PLUGIN_DIRECTORY', $root . '/');
define('EMSFB_PLUGIN_URL', 'https://example.test/wp-content/plugins/easy-form-builder/');
define('EMSFB_PLUGIN_FILE', $root . '/emsfb.php');
$GLOBALS['efb_t_hooks'] = array();
function add_action($hook, $cb, $prio = 10, $args = 1) { $GLOBALS['efb_t_hooks'][] = array($hook, is_array($cb) ? (is_object($cb[0]) ? get_class($cb[0]) : $cb[0]) . '::' . $cb[1] : 'closure'); return true; }
function add_filter($hook, $cb, $prio = 10, $args = 1) { return add_action($hook, $cb, $prio, $args); }
function register_activation_hook($f, $cb) { $GLOBALS['efb_t_hooks'][] = array('activate', 'x'); }
function register_deactivation_hook($f, $cb) { $GLOBALS['efb_t_hooks'][] = array('deactivate', 'x'); }
function is_admin() { return true; }
$GLOBALS['efb_t_caps'] = array('activate_plugins' => true, 'update_core' => true);
function current_user_can($c) { return !empty($GLOBALS['efb_t_caps'][$c]); }
function esc_html__($s, $d = '') { return htmlspecialchars($s, ENT_QUOTES); }
function esc_html($s) { return htmlspecialchars((string) $s, ENT_QUOTES); }
function esc_url($s) { return (string) $s; }
function network_admin_url($p = '') { return 'https://example.test/wp-admin/network/' . $p; }

$GLOBALS['wp_version'] = '6.2.6';
require $root . '/includes/class-Emsfb.php';

t('Emsfb::MIN_WP_VERSION_EFB is defined', defined('Emsfb::MIN_WP_VERSION_EFB'), true);
if (!defined('Emsfb::MIN_WP_VERSION_EFB')) {
	echo "\nRESULTS: {$passed} passed, " . ($failed) . " failed\n";
	exit(1);
}
$head = (string) file_get_contents($root . '/emsfb.php', false, null, 0, 2048);
preg_match('/Requires at least:\s*([0-9.]+)/', $head, $m);
t('it is the "Requires at least" of emsfb.php', Emsfb::MIN_WP_VERSION_EFB, isset($m[1]) ? $m[1] : '?');
t('… which is 6.3 (owner decision 7)', Emsfb::MIN_WP_VERSION_EFB, '6.3');
$readme = (string) @file_get_contents($root . '/readme.txt', false, null, 0, 2048);
if (preg_match('/Requires at least:\s*([0-9.]+)/', $readme, $r)) t('… and of readme.txt', $r[1], Emsfb::MIN_WP_VERSION_EFB);

foreach (array('5.7' => false, '6.2.6' => false, '6.3-RC1' => false, '6.3' => true, '6.3.5' => true, '6.9' => true, '7.1.5' => true, '' => true) as $v => $ok) {
	t("wp_version_supported_efb('{$v}')", Emsfb::wp_version_supported_efb($v), $ok);
}

/* ---- WordPress 6.2.6: inert ---- */
$e = null;
try {
	$emsfb = new Emsfb();
} catch (\Throwable $ex) {
	$e = get_class($ex) . ': ' . $ex->getMessage();
}
t('WordPress 6.2.6: constructing the plugin throws nothing', $e, null);
t('… and loads nothing from includes/ (no Install, Form_Runtime, Notices class)', array(class_exists('\\Emsfb\\Install', false), class_exists('\\Emsfb\\Form_Runtime', false), class_exists('\\Emsfb\\Notices', false)), array(false, false, false));
$hooks = array_map(function ($h) { return $h[0]; }, $GLOBALS['efb_t_hooks']);
sort($hooks);
t('… and adds only the notice (admin_notices, network_admin_notices)', $hooks, array('admin_notices', 'network_admin_notices'));
t('… the notice is wp_version_notice_efb()', $GLOBALS['efb_t_hooks'][0][1], 'Emsfb::wp_version_notice_efb');

ob_start();
$emsfb->wp_version_notice_efb();
$html = ob_get_clean();
t('the notice is an error notice the screen reader announces', strpos($html, 'class="notice notice-error efb-notice" role="alert"') !== false, true);
t('… its sentences take the direction of their own script (dir="auto"; English on a right-to-left screen)', substr_count($html, '<p dir="auto">'), 2);
t('… names the version needed and the version running, each in <bdi dir="ltr">', strpos($html, '<bdi dir="ltr">6.3</bdi>') !== false && strpos($html, '<bdi dir="ltr">6.2.6</bdi>') !== false, true);
t('… says the forms, entries and settings are kept', strpos($html, 'Your forms, entries and settings are kept.') !== false, true);
t('… links to Updates for whoever may update WordPress', strpos($html, 'href="https://example.test/wp-admin/network/update-core.php"') !== false, true);
$GLOBALS['efb_t_caps'] = array('activate_plugins' => true);
ob_start(); $emsfb->wp_version_notice_efb(); $html2 = ob_get_clean();
t('… without the link for someone who may not update', strpos($html2, 'update-core.php') === false && strpos($html2, '6.2.6') !== false, true);
$GLOBALS['efb_t_caps'] = array();
ob_start(); $emsfb->wp_version_notice_efb(); $html3 = ob_get_clean();
t('… nothing for a user who can neither activate plugins nor update', $html3, '');

/* ---- mbstring-free helpers ---- */
$main = (string) file_get_contents($root . '/includes/class-Emsfb.php');
$fr = (string) file_get_contents($root . '/includes/class-Emsfb-field-rules.php');
$xw = (string) file_get_contents($root . '/includes/admin/class-Emsfb-xlsx-writer.php');
$code_only = function ($src) {
	$o = '';
	foreach (token_get_all($src) as $tk) if (!is_array($tk) || !in_array($tk[0], array(T_COMMENT, T_DOC_COMMENT), true)) $o .= is_array($tk) ? $tk[1] : $tk;
	return $o;
};
t('class-Emsfb.php calls no mb_chr()', (bool) preg_match('/\bmb_chr\s*\(/', $code_only($main)), false);
t('class-Emsfb-field-rules.php calls no mb_ord()', (bool) preg_match('/\bmb_ord\s*\(/', $code_only($fr)), false);
t('class-Emsfb-xlsx-writer.php calls no utf8_encode()', (bool) preg_match('/\butf8_encode\s*\(/', $code_only($xw)), false);

$cps = array(0, 0x41, 0x7F, 0x80, 0x627, 0x6F0, 0x7FF, 0x800, 0x30A2, 0x4E00, 0xFFFD, 0x10000, 0x1F600, 0x10FFFF);
$bad = array();
foreach ($cps as $cp) {
	$want = function_exists('mb_chr') ? mb_chr($cp, 'UTF-8') : html_entity_decode('&#' . $cp . ';', ENT_QUOTES, 'UTF-8');
	if (Emsfb::utf8_chr_efb($cp) !== $want) $bad[] = dechex($cp);
}
t('Emsfb::utf8_chr_efb() gives the bytes of mb_chr() (1 to 4 bytes)', $bad, array());
t('… and \'\' for a surrogate or a code point past U+10FFFF', array(Emsfb::utf8_chr_efb(0xD800), Emsfb::utf8_chr_efb(0x110000), Emsfb::utf8_chr_efb(-1)), array('', '', ''));
$bad = array();
for ($cp = 0x0600; $cp <= 0x06FF; $cp++) if (Emsfb::utf8_chr_efb($cp) !== html_entity_decode('&#' . $cp . ';', ENT_QUOTES, 'UTF-8')) $bad[] = dechex($cp);
t('… for the whole Arabic block (the Persian and Arabic script checks)', $bad, array());

require_once $root . '/includes/class-Emsfb-field-rules.php';
$bad = array();
foreach (array('a', 'é', 'ا', '۵', '中', '😀', "\u{10FFFF}") as $ch) {
	$want = function_exists('mb_ord') ? mb_ord($ch, 'UTF-8') : -2;
	if ($want !== -2 && \Emsfb\Field_Rules::utf8_ord($ch) !== $want) $bad[] = $ch;
}
t('Field_Rules::utf8_ord() gives the code points of mb_ord()', $bad, array());
t('… -1 for an empty string, a lone continuation byte, an overlong form', array(\Emsfb\Field_Rules::utf8_ord(''), \Emsfb\Field_Rules::utf8_ord("\x80"), \Emsfb\Field_Rules::utf8_ord("\xC0\xAF")), array(-1, -1, -1));

require_once $root . '/includes/admin/class-Emsfb-xlsx-writer.php';
$bytes = '';
for ($i = 0; $i < 256; $i++) $bytes .= chr($i);
$want = function_exists('mb_convert_encoding') ? mb_convert_encoding($bytes, 'UTF-8', 'ISO-8859-1') : null;
if ($want !== null) t('Xlsx_Writer::latin1_to_utf8() gives what utf8_encode() gave (all 256 bytes)', \Emsfb\Xlsx_Writer::latin1_to_utf8($bytes), $want);

/* ---- workarounds for WordPress before 6.3 ---- */
$gb = (string) file_get_contents($root . '/includes/page-builders/gutenberg/class-Emsfb-gutenberg-block.php');
t('the block registers through register_block_type() only (no 5.8 branch)', strpos($gb, 'register_block_type_from_metadata') === false && strpos($gb, "version_compare(get_bloginfo('version'), '5.8'") === false, true);
$cp = (string) file_get_contents($root . '/includes/class-Emsfb-captcha.php');
t('CAPTCHA scripts always load deferred in the footer (6.3 strategy, no version check)', (bool) preg_match("/function script_args\(\)\s*\{\s*return array\('in_footer' => true, 'strategy' => 'defer'\);\s*\}/", $cp), true);
$cc = (string) file_get_contents($root . '/includes/class-Emsfb-cache-compat.php');
t('Cache_Compat listens to wp_after_insert_post (no 5.6 branch)', strpos($cc, "add_action('wp_after_insert_post'") !== false && strpos($cc, "version_compare(\$wp_version,'5.6'") === false, true);
$adm = (string) file_get_contents($root . '/includes/admin/class-Emsfb-admin.php');
t('no group flush is guarded by function_exists() alone (a cache without group flushes answers _doing_it_wrong)', preg_match("/if \(function_exists\('wp_cache_flush_group'\)\)/", $main . $adm), 0);
$ins = (string) file_get_contents($root . '/includes/class-Emsfb-install.php');
t('Install::install() reads the settings row with %i', strpos($ins, "\$wpdb->prepare( 'SELECT setting FROM %i ORDER BY id DESC LIMIT 1', \$table_name_stng )") !== false, true);

echo "\nRESULTS: {$passed} passed, {$failed} failed\n";
exit($failed ? 1 : 0);
