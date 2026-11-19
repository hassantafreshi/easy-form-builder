<?php
/**
 * PERF-01: _Public::asset_efb($rel) gives the address and version of a plugin asset: its minified twin (x.min.js /
 * x.min.css, written by the release build next to the source) when it exists, is not older than the source and
 * SCRIPT_DEBUG is off; else the source. The version carries the file's time (no browser or CDN keeps an older copy).
 * The method is lifted from class-Emsfb-public.php and run on a scratch plugin folder.
 *
 * Run: php tests/test-public-asset-min.php   (no WordPress needed)
 */
$pass = 0; $fail = 0;
function t($label, $ok, $info = '') { global $pass, $fail; if ($ok) { $pass++; echo "[PASS] $label\n"; return; } $fail++; echo "[FAIL] $label" . ($info !== '' ? ": $info" : '') . "\n"; }

$src = file_get_contents(__DIR__ . '/../includes/class-Emsfb-public.php');
if (!preg_match('/\n\tpublic static function asset_efb\(\$rel\) \{.*?\n\t\}\n/s', $src, $m)) { echo "[FAIL] asset_efb() not found\n\nRESULTS: 0 passed, 1 failed\n"; exit(1); }
$dir = sys_get_temp_dir() . '/efb-asset-' . getmypid() . '/';
@mkdir($dir . 'public/assets/js', 0777, true);
@mkdir($dir . 'includes/admin/assets/css', 0777, true);
define('EMSFB_PLUGIN_DIRECTORY', $dir);
define('EMSFB_PLUGIN_URL', 'https://site.test/wp-content/plugins/easy-form-builder/');
define('EMSFB_PLUGIN_VERSION', '4.3.2');
$code = $m[0];
// One class per case: the method memoizes its answers per request.
$n = 0;
$run = function ($rel, $debug = false) use ($code, &$n) {
	$n++;
	$cls = 'EfbAssetHarness' . $n;
	eval('class ' . $cls . ' { ' . str_replace("defined('SCRIPT_DEBUG') && SCRIPT_DEBUG", $debug ? 'true' : 'false', $code) . ' }');
	return $cls::asset_efb($rel);
};
$w = function ($rel, $t) use ($dir) { file_put_contents($dir . $rel, "/* $rel */"); touch($dir . $rel, $t); clearstatcache(); };

$w('public/assets/js/core-efb.js', 1700000000);
$r = $run('public/assets/js/core-efb.js');
t('no minified twin: the source, versioned by its time', $r[0] === EMSFB_PLUGIN_URL . 'public/assets/js/core-efb.js' && $r[1] === '4.3.2.1700000000', json_encode($r));
$w('public/assets/js/core-efb.min.js', 1700000100);
$r = $run('public/assets/js/core-efb.js');
t('a twin made by the build (as new or newer): the .min.js, versioned by its time', $r[0] === EMSFB_PLUGIN_URL . 'public/assets/js/core-efb.min.js' && $r[1] === '4.3.2.1700000100', json_encode($r));
$r = $run('/public/assets/js/core-efb.js');
t('a leading slash is fine', $r[0] === EMSFB_PLUGIN_URL . 'public/assets/js/core-efb.min.js');
$r = $run('public/assets/js/core-efb.js', true);
t('SCRIPT_DEBUG: the source', $r[0] === EMSFB_PLUGIN_URL . 'public/assets/js/core-efb.js');
$w('public/assets/js/core-efb.js', 1700000200);
$r = $run('public/assets/js/core-efb.js');
t('the source edited after the build (a stale twin): the source', $r[0] === EMSFB_PLUGIN_URL . 'public/assets/js/core-efb.js' && $r[1] === '4.3.2.1700000200', json_encode($r));
$w('includes/admin/assets/css/style-efb.css', 1700000000);
$w('includes/admin/assets/css/style-efb.min.css', 1700000000);
$r = $run('includes/admin/assets/css/style-efb.css');
t('CSS too (same second as the source counts as built)', $r[0] === EMSFB_PLUGIN_URL . 'includes/admin/assets/css/style-efb.min.css');
$w('includes/admin/assets/css/bootstrap.min-efb.css', 1700000000);
$r = $run('includes/admin/assets/css/bootstrap.min-efb.css');
t('an already minified library keeps its name', $r[0] === EMSFB_PLUGIN_URL . 'includes/admin/assets/css/bootstrap.min-efb.css');
$w('public/assets/js/x.min.js', 1700000000);
$r = $run('public/assets/js/x.min.js');
t('a .min.js is not looked up as .min.min.js', $r[0] === EMSFB_PLUGIN_URL . 'public/assets/js/x.min.js');
$r = $run('public/assets/js/missing-efb.js');
t('a missing file: its address and the plugin version', $r[0] === EMSFB_PLUGIN_URL . 'public/assets/js/missing-efb.js' && $r[1] === '4.3.2');
/* No warning for a twin that is not there (the usual case): an error handler (WP_DEBUG tools, tests/test-php-warnings-wp.php)
   sees a filemtime() warning even behind @. */
$warn = array();
set_error_handler(function ($no, $msg) use (&$warn) { $warn[] = $msg; return true; });
$run('public/assets/js/core-efb.js'); $run('includes/admin/assets/css/bootstrap.min-efb.css'); $run('public/assets/js/missing-efb.js');
restore_error_handler();
t('no PHP warning for a missing twin or file', !$warn, implode(' | ', $warn));

/* The page's own scripts go through it. */
foreach (array('public/assets/js/core-efb.js', 'includes/admin/assets/js/new-efb.js', 'public/assets/js/inline-efb.js', 'includes/admin/assets/js/pro_els-efb.js', 'includes/admin/assets/js/response-viewer-efb.js', 'includes/admin/assets/css/style-efb.css', 'public/assets/js/upload-efb.js', 'public/assets/js/field-rules-efb.js') as $rel) {
	t("the form page loads $rel through asset_efb()", strpos($src, "self::asset_efb('$rel')[0]") !== false);
}

array_map('unlink', array_merge(glob($dir . 'public/assets/js/*'), glob($dir . 'includes/admin/assets/css/*')));
@rmdir($dir . 'public/assets/js'); @rmdir($dir . 'public/assets'); @rmdir($dir . 'public'); @rmdir($dir . 'includes/admin/assets/css'); @rmdir($dir . 'includes/admin/assets'); @rmdir($dir . 'includes/admin'); @rmdir($dir . 'includes'); @rmdir($dir);

echo "\nRESULTS: $pass passed, $fail failed\n";
exit($fail ? 1 : 0);
