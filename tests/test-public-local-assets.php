<?php
/**
 * SEC-02 (owner decision 9): fonts and the country / province / city lists come from the plugin, not from CDNs.
 *  - The response box's 17 fonts were loaded from fonts.googleapis.com (visitors' addresses to Google) and from
 *    cdn.jsdelivr.net/gh/rastikerdar/…@latest (unpinned, no SRI); the PDF probed Google Fonts on every print.
 *  - The province and city lists came from jsDelivr / cdn.easyformbuilder.ir (BUGS-FOUND 107: no list where the CDN is
 *    blocked); Chart.js for poll results from jsDelivr.
 * The release build bundles them (bin/fetch-public-assets.sh: pinned versions, OFL fonts as woff2, the MIT lists at a
 * commit); the plugin uses its own copy when it is there (_Public::local_font_css_efb(), geo_base_url_efb(),
 * efbPollChart.chartjs) and never names a font CDN.
 *
 * Run: php tests/test-public-local-assets.php   (no WordPress, no network)
 */
$root = dirname(__DIR__);
$pass = 0; $fail = 0;
function t($label, $ok, $info = '') { global $pass, $fail; if ($ok) { $pass++; echo "[PASS] $label\n"; return; } $fail++; echo "[FAIL] $label" . ($info !== '' ? ": $info" : '') . "\n"; }

$pub = file_get_contents("$root/includes/class-Emsfb-public.php");
$rv  = file_get_contents("$root/includes/admin/assets/js/response-viewer-efb.js");
$poll = file_get_contents("$root/public/assets/js/poll-chart-efb.js");
$sh  = file_get_contents("$root/bin/fetch-public-assets.sh");

foreach (array('class-Emsfb-public.php' => $pub, 'response-viewer-efb.js' => $rv) as $f => $src) {
	t("$f names no font CDN (fonts.googleapis.com, fonts.gstatic.com, jsDelivr)", !preg_match('#fonts\.googleapis\.com|fonts\.gstatic\.com|cdn\.jsdelivr\.net#', $src));
	t("$f has no unpinned @latest address", strpos($src, '@latest') === false);
}
t('the PDF does not probe or load Google Fonts', strpos($rv, 'checkGoogleFontsAccess_efb') === false && strpos($rv, 'openPrintWindow_efb(generatePDFContent_efb(false));') !== false);
t('the response box font is an enqueued style (no <link> printed in the post content)', strpos($pub, "wp_enqueue_style('efb-resp-font', \$local_font_efb[0], array(), \$local_font_efb[1]);") !== false && strpos($pub, '<link rel="stylesheet" href="') === false);
t('the page tells the viewer the bundled font (resp_font_css) and the lists\' place (zone_area)', strpos($pub, "'resp_font_css' =>") !== false && strpos($pub, "'zone_area'=>self::geo_base_url_efb()") !== false);
t('Chart.js: the bundled copy first (efbPollChart.chartjs), the pinned CDN file only without it', strpos($pub, "'chartjs' => is_readable(EMSFB_PLUGIN_DIRECTORY . 'public/assets/js/vendor/chart.umd.min.js')") !== false && strpos($poll, "script.src = local || 'https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js';") !== false);

/* The three lists of fonts agree: PHP, the viewer, the build script. */
preg_match('/const RESP_FONTS_EFB = array\((.*?)\);/s', $pub, $m);
preg_match_all("/(?:'([^']+)'|\"([^\"]+)\") => '([a-z-]+)'/", $m[1] ?? '', $pp, PREG_SET_ORDER);
$php_map = array(); foreach ($pp as $x) $php_map[$x[1] !== '' ? $x[1] : $x[2]] = $x[3];
preg_match('/const builtinFontDirs = \{(.*?)\};/s', $rv, $m2);
preg_match_all("/\"([^\"]+)\": '([a-z-]+)'/", $m2[1] ?? '', $jj, PREG_SET_ORDER);
$js_map = array(); foreach ($jj as $x) $js_map[$x[1]] = $x[2];
preg_match_all('/^\s*"([a-z-]+)\|/m', $sh, $ss);
$sh_slugs = $ss[1]; sort($sh_slugs);
$php_slugs = array_values(array_unique($php_map)); sort($php_slugs);
t('17 fonts in the PHP map', count($php_map) === 17, (string) count($php_map));
t('the viewer has the same fonts and folders as the PHP map', $php_map === $js_map, json_encode(array_diff_assoc($php_map, $js_map)));
t('the build script fetches every folder the plugin asks for', $php_slugs === $sh_slugs, implode(',', array_diff($php_slugs, $sh_slugs)) . ' / ' . implode(',', array_diff($sh_slugs, $php_slugs)));
t('the build script pins every source (versions, the lists\' commit), no @latest', strpos($sh, '@latest') === false && preg_match_all('/"[a-z]+\|[a-z]+-font@[0-9.]+"/', $sh) === 6 && preg_match("/GEO_COMMIT='[0-9a-f]{40}'/", $sh) === 1 && strpos($sh, 'chart.js@4.4.1') !== false);
t('the build script is valid bash', trim((string) shell_exec('bash -n ' . escapeshellarg("$root/bin/fetch-public-assets.sh") . ' 2>&1')) === '');

/* The lookups, lifted from the source, on a scratch plugin folder. */
$dir = sys_get_temp_dir() . '/efb-local-assets-' . getmypid() . '/';
define('EMSFB_PLUGIN_DIRECTORY', $dir);
define('EMSFB_PLUGIN_URL', 'https://site.test/wp-content/plugins/easy-form-builder/');
define('EMSFB_PLUGIN_VERSION', '4.3.2');
define('CDN_ZONE_AREA', 'https://cdn.example/lists/');
$code = '';
foreach (array('asset_efb', 'local_font_css_efb', 'geo_base_url_efb') as $fn) {
	if (preg_match('/\n\tpublic static function ' . $fn . '\(.*?\n\t\}\n/s', $pub, $mm)) $code .= $mm[0]; else t("$fn() found", false);
}
eval('class EfbLocalAssetsHarness { const RESP_FONTS_EFB = ' . var_export($php_map, true) . '; ' . $code . ' }');
t('a font that is not bundled: nothing (the box keeps its fallback font)', EfbLocalAssetsHarness::local_font_css_efb('Vazir, Tahoma, sans-serif') === null);
t('the lists are not bundled: the CDN', EfbLocalAssetsHarness::geo_base_url_efb() === CDN_ZONE_AREA);
@mkdir($dir . 'public/assets/fonts/vazir', 0777, true);
file_put_contents($dir . 'public/assets/fonts/vazir/font.css', '@font-face{}');
@mkdir($dir . 'public/assets/json/geo/json/states', 0777, true);
file_put_contents($dir . 'public/assets/json/geo/json/states/ir.json', '{}');
clearstatcache();
$f = EfbLocalAssetsHarness::local_font_css_efb('Vazir, Tahoma, sans-serif');
t('a bundled font: its local font.css, versioned', is_array($f) && $f[0] === EMSFB_PLUGIN_URL . 'public/assets/fonts/vazir/font.css' && strpos($f[1], '4.3.2.') === 0, json_encode($f));
t('an unknown family: nothing', EfbLocalAssetsHarness::local_font_css_efb('Comic Sans') === null && EfbLocalAssetsHarness::local_font_css_efb(null) === null);
t('the lists bundled: the plugin\'s copy', EfbLocalAssetsHarness::geo_base_url_efb() === EMSFB_PLUGIN_URL . 'public/assets/json/geo/');
@unlink($dir . 'public/assets/fonts/vazir/font.css'); @unlink($dir . 'public/assets/json/geo/json/states/ir.json');
foreach (array('public/assets/fonts/vazir', 'public/assets/fonts', 'public/assets/json/geo/json/states', 'public/assets/json/geo/json', 'public/assets/json/geo', 'public/assets/json', 'public/assets', 'public', '') as $d) @rmdir($dir . $d);

/* The fetched files stay out of git (the build ships them). */
$gi = (string) @file_get_contents("$root/.gitignore");
t('.gitignore keeps the fetched fonts, lists and Chart.js out of the repository', strpos($gi, 'public/assets/fonts/') !== false && strpos($gi, 'public/assets/json/geo/') !== false && strpos($gi, 'public/assets/js/vendor/') !== false);

echo "\nRESULTS: $pass passed, $fail failed\n";
exit($fail ? 1 : 0);
