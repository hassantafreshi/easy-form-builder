<?php
/**
 * QA3-002: Addon_Installer, without WordPress (ZipArchive stands in for unzip_file()).
 *  - a new add-on is unzipped through a staging folder into vendor/;
 *  - an outdated add-on's folder is replaced in one step: the old files are gone, the new ones in place, and the
 *    opcode cache is told about the folder (QA-4's fatal "undefined method" came from stale cached files);
 *  - a package without the add-on's folder, or one that cannot be unzipped, fails with a WP_Error and the add-on
 *    already there is untouched; no staging or old folder is left behind;
 *  - a linked folder (a development checkout) is never replaced or deleted, and its target is untouched;
 *  - remove_folder() deletes a real folder, not a link and not what a link inside it points to.
 * And the wiring: add_addons_Emsfb() downloads over an existing folder (no more "success" with
 * download_skipped_existing_directory), Remove deletes the files, the card offers Update.
 *
 * Run: php tests/test-addon-installer.php
 */
define('ABSPATH', __DIR__ . '/');
$passed = 0; $failed = 0;
function t($name, $actual, $expected) {
	global $passed, $failed;
	if ($actual === $expected) { echo "[PASS] {$name}\n"; $passed++; return; }
	echo "[FAIL] {$name}: expected " . json_encode($expected) . ', got ' . json_encode($actual) . "\n";
	$failed++;
}
if (!class_exists('ZipArchive')) { echo "[SKIP] ZipArchive is not available\nRESULTS: 0 passed, 0 failed\n"; exit(0); }

class WP_Error { public $c; public $m; public function __construct($c = '', $m = '') { $this->c = $c; $this->m = $m; } public function get_error_code() { return $this->c; } public function get_error_message() { return $this->m; } }
function is_wp_error($x) { return $x instanceof WP_Error; }
function esc_html__($s, $d = '') { return $s; }
function wp_normalize_path($p) { return str_replace('\\', '/', (string) $p); }
function wp_mkdir_p($d) { return is_dir($d) || @mkdir($d, 0777, true); }
function wp_generate_password($n = 12, $a = true, $b = false) { return substr(bin2hex(random_bytes($n)), 0, $n); }
function wp_delete_file($f) { @unlink($f); }
$GLOBALS['opcache_dirs'] = array();
function wp_opcache_invalidate_directory($d) { $GLOBALS['opcache_dirs'][] = $d; }
function unzip_file($zip, $to) {
	$z = new ZipArchive();
	if ($z->open($zip) !== true) return new WP_Error('incompatible_archive', 'Incompatible Archive.');
	$ok = $z->extractTo($to);
	$z->close();
	return $ok ? true : new WP_Error('extract_failed', 'Could not extract.');
}
require __DIR__ . '/../includes/class-Emsfb-addon-installer.php';
use Emsfb\Addon_Installer as I;

$root = sys_get_temp_dir() . '/efb-installer-' . getmypid();
register_shutdown_function(function () use ($root) { if (is_dir($root)) shell_exec('rm -rf ' . escapeshellarg($root)); });
$vendor = $root . '/plugin/vendor/';
$temp = $root . '/plugin/temp/';
@mkdir($vendor, 0777, true);
$mkzip = function ($name, array $files) use ($root) {
	$p = $root . '/' . $name . '-' . wp_generate_password(4) . '.zip';
	$z = new ZipArchive();
	$z->open($p, ZipArchive::CREATE);
	foreach ($files as $k => $v) $z->addFromString($k, $v);
	$z->close();
	return $p;
};
$leftovers = function () use ($temp) { return is_dir($temp) ? array_values(array_diff(scandir($temp), array('.', '..'))) : array(); };

// 1. a new add-on
$r = I::install_zip($mkzip('stripe', array('stripe/routes-efb.php' => '<?php // v2', 'stripe/class-Emsfb-stripe-payment.php' => '<?php // new')), $vendor, 'stripe', $temp);
t('a new add-on is installed', array($r, is_file($vendor . 'stripe/routes-efb.php')), array(true, true));
t('... no staging folder is left', $leftovers(), array());

// 2. an outdated add-on (QA-3: the 5c4051e Stripe folder, contract 1) is replaced
file_put_contents($vendor . 'stripe/only-in-old.php', '<?php // old');
file_put_contents($vendor . 'stripe/class-Emsfb-stripe-payment.php', '<?php // old');
$GLOBALS['opcache_dirs'] = array();
$r = I::install_zip($mkzip('stripe', array('stripe/class-Emsfb-stripe-payment.php' => '<?php // newer', 'stripe/routes-efb.php' => '<?php // v2')), $vendor, 'stripe', $temp);
t('QA3-002: an existing folder is replaced (no more skipped "success")', array($r, file_get_contents($vendor . 'stripe/class-Emsfb-stripe-payment.php'), file_exists($vendor . 'stripe/only-in-old.php')), array(true, '<?php // newer', false));
t('... the opcode cache is told about the replaced folder', $GLOBALS['opcache_dirs'], array(rtrim($vendor, '/') . '/stripe'));
t('... no staging or old folder is left', $leftovers(), array());

// 3. failures keep the add-on that is there
$before = file_get_contents($vendor . 'stripe/class-Emsfb-stripe-payment.php');
$r = I::install_zip($mkzip('wrong', array('paypal/paypalefb.php' => '<?php')), $vendor, 'stripe', $temp);
t('a package without the add-on\'s folder fails honestly', is_wp_error($r) ? $r->get_error_code() : $r, 'addon_package_invalid');
t('... and the installed add-on is untouched', array(file_get_contents($vendor . 'stripe/class-Emsfb-stripe-payment.php'), $leftovers()), array($before, array()));
$bad = $root . '/broken.zip';
file_put_contents($bad, 'not a zip');
$r = I::install_zip($bad, $vendor, 'stripe', $temp);
t('a download that is not a zip fails honestly (unzip_failed)', is_wp_error($r) ? $r->get_error_code() : $r, 'unzip_failed');
t('... and the installed add-on is untouched', file_get_contents($vendor . 'stripe/class-Emsfb-stripe-payment.php'), $before);
t('a folder name that is not an add-on\'s is refused', is_wp_error(I::install_zip($bad, $vendor, '../etc', $temp)), true);

// 4. a development checkout: vendor/<add-on> is a link to the add-ons' repository
$repo = $root . '/vendores/paypal';
@mkdir($repo, 0777, true);
file_put_contents($repo . '/paypalefb.php', '<?php // working copy');
symlink($repo, $vendor . 'paypal');
$r = I::install_zip($mkzip('paypal', array('paypal/paypalefb.php' => '<?php // download')), $vendor, 'paypal', $temp);
t('a linked folder is never replaced', array($r, is_link($vendor . 'paypal'), file_get_contents($repo . '/paypalefb.php')), array(true, true, '<?php // working copy'));
t('... nor deleted by Remove', array(I::remove_folder($vendor, 'paypal'), is_link($vendor . 'paypal'), is_file($repo . '/paypalefb.php')), array(false, true, true));

// 5. Remove
$outside = $root . '/outside';
@mkdir($outside, 0777, true);
file_put_contents($outside . '/keep.txt', 'keep');
symlink($outside, $vendor . 'stripe/link-out');
t('remove_folder() deletes the add-on\'s folder', array(I::remove_folder($vendor, 'stripe'), file_exists($vendor . 'stripe')), array(true, false));
t('... without following a link inside it', is_file($outside . '/keep.txt'), true);
t('... and a folder that is not there counts as removed', I::remove_folder($vendor, 'stripe'), true);

// 6. wiring
$adm = (string) file_get_contents(__DIR__ . '/../includes/admin/class-Emsfb-admin.php');
$fn = (string) file_get_contents(__DIR__ . '/../includes/functions.php');
$js = (string) file_get_contents(__DIR__ . '/../includes/admin/assets/js/admin-efb.js');
$cat = (string) file_get_contents(__DIR__ . '/../includes/class-Emsfb-addon-catalog.php');
t('add_addons_Emsfb downloads over an existing folder (only a linked one is skipped)', strpos($adm, "if (!\$directory_exists || !is_link(rtrim(\$directory, '/'))) {") !== false && strpos($adm, "'download_skipped_existing_directory'") === false, true);
$inst = (string) file_get_contents(__DIR__ . '/../includes/class-Emsfb-addon-installer.php');
t('both download paths install through Addon_Installer (staged, swapped, opcode cache)', strpos($adm, 'Addon_Installer::download_and_install($url)') !== false && strpos($fn, 'Addon_Installer::download_and_install($url)') !== false && strpos($inst, 'self::install_zip($tmp') !== false && strpos($adm . $fn, "unzip_file(EMSFB_PLUGIN_DIRECTORY . 'temp/temp.zip'") === false, true);
t('Remove deletes the add-on\'s files (Addon_Installer::remove_folder)', strpos($adm, 'Addon_Installer::remove_folder(EMSFB_PLUGIN_DIRECTORY') !== false, true);
t('the card of an outdated add-on offers Update (state "update" in efb_var.addon_catalog)', strpos($js, "addonState === 'update' ? (efb_var.text.addonUpdateBtn || 'Update')") !== false && strpos($cat, "'state' => \$state") !== false, true);

echo "\nRESULTS: {$passed} passed, {$failed} failed\n";
exit($failed ? 1 : 0);
