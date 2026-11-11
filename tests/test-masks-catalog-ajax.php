<?php
/**
 * F01-B: admin AJAX efb_masks_catalog_Emsfb (builder "Format / mask" list) and the translatable labels file.
 * Standalone with WordPress stubs: nonce and permission refusal, group order per site locale, labels per
 * admin locale (translation pack or catalog fa/ar fallback), caching, and id parity with the catalog.
 *
 * Run: php tests/test-masks-catalog-ajax.php
 */

if (!defined('ABSPATH')) {
	define('ABSPATH', __DIR__ . '/');
}
if (!defined('EMSFB_PLUGIN_VERSION')) {
	define('EMSFB_PLUGIN_VERSION', '4.3.1-test');
}
if (!defined('DAY_IN_SECONDS')) {
	define('DAY_IN_SECONDS', 86400);
}

class EfbMasksAjaxStop extends Exception {
	public $payload;
	public function __construct($payload) {
		parent::__construct('json');
		$this->payload = $payload;
	}
}

$GLOBALS['efbt'] = array('nonce' => true, 'can' => true, 'user_locale' => 'en_US', 'site_locale' => 'en_US', 'tr' => array(), 'cache' => array(), 'cache_sets' => 0);

function add_action() {}
function wp_get_current_user() { return (object) array('ID' => 1); } // the admin class file instantiates itself on load
function is_admin() { return false; }
function check_ajax_referer($action, $arg, $die) { return $GLOBALS['efbt']['nonce'] && $action === 'wp_rest' && $arg === 'nonce' && $die === false; }
function get_user_locale() { return $GLOBALS['efbt']['user_locale']; }
function get_locale() { return $GLOBALS['efbt']['site_locale']; }
function __($t, $d = 'default') { return isset($GLOBALS['efbt']['tr'][$t]) ? $GLOBALS['efbt']['tr'][$t] : $t; }
function wp_cache_get($k, $g = '') { return isset($GLOBALS['efbt']['cache'][$g . '|' . $k]) ? $GLOBALS['efbt']['cache'][$g . '|' . $k] : false; }
function wp_cache_set($k, $v, $g = '', $e = 0) { $GLOBALS['efbt']['cache'][$g . '|' . $k] = $v; $GLOBALS['efbt']['cache_sets']++; $GLOBALS['efbt']['last_expire'] = $e; return true; }
function wp_send_json_success($data, $code = 200) { throw new EfbMasksAjaxStop($data); }
class EfbMasksAjaxFn {
	public function text_efb($keys) { return array('error403' => 'E403', 'somethingWentWrongPleaseRefresh' => 'REFRESH'); }
	public function user_permission_efb_admin_dashboard() { return $GLOBALS['efbt']['can']; }
}
function get_efbFunction() { return new EfbMasksAjaxFn(); }

require_once __DIR__ . '/../includes/class-Emsfb-masks.php';
require_once __DIR__ . '/../includes/admin/class-Emsfb-admin.php';

$pass = 0;
$fail = 0;
function efbt($label, $cond) {
	global $pass, $fail;
	if ($cond) { $pass++; echo "[PASS] $label\n"; } else { $fail++; echo "[FAIL] $label\n"; }
}
function efbt_call() {
	$admin = (new ReflectionClass('\Emsfb\Admin'))->newInstanceWithoutConstructor();
	try {
		$admin->masks_catalog_Emsfb();
	} catch (EfbMasksAjaxStop $s) {
		return $s->payload;
	}
	return null;
}
function efbt_reset($o) {
	$GLOBALS['efbt'] = array_merge($GLOBALS['efbt'], array('nonce' => true, 'can' => true, 'tr' => array(), 'cache' => array(), 'cache_sets' => 0), $o);
	\Emsfb\Masks::reset();
}

$src = file_get_contents(__DIR__ . '/../includes/admin/class-Emsfb-admin.php');
efbt('action is registered for logged-in users only', strpos($src, "add_action('wp_ajax_efb_masks_catalog_Emsfb', [\$this, 'masks_catalog_Emsfb'])") !== false && strpos($src, 'wp_ajax_nopriv_efb_masks') === false);

efbt_reset(array('nonce' => false));
$r = efbt_call();
efbt('no nonce -> success:false with error403', is_array($r) && $r['success'] === false && $r['m'] === 'E403' && !isset($r['groups']));
efbt_reset(array('can' => false));
$r = efbt_call();
efbt('no permission -> success:false with error403', is_array($r) && $r['success'] === false && $r['m'] === 'E403');

$cat = json_decode(file_get_contents(__DIR__ . '/../includes/data/masks-catalog-efb.json'), true);
$catIds = array_map(function ($m) { return $m['id']; }, $cat['masks']);
sort($catIds);

// fa_IR site and admin, no translation pack loaded: labels come from the catalog's fa column.
efbt_reset(array('user_locale' => 'fa_IR', 'site_locale' => 'fa_IR'));
$r = efbt_call();
efbt('fa_IR: success', is_array($r) && $r['success'] === true && $r['version'] === 1);
efbt('fa_IR: Iran group first, International second', $r['groups'][0]['country'] === 'ir' && $r['groups'][1]['country'] === 'intl');
$ir = $r['groups'][0]['presets'][0];
efbt('fa_IR: Persian label and placeholder', $ir['id'] === 'ir.mobile' && $ir['label'] === 'شماره موبایل' && $ir['placeholder'] === '۰۹۱۲ ۳۴۵ ۶۷۸۹');
efbt('preset row has exactly the documented keys', array_keys($ir) === array('id', 'label', 'placeholder', 'category', 'mask', 'formatter', 'checksum', 'inputmode', 'autocomplete', 'verification', 'has_strict'));
$ids = array();
$labelled = true;
$leak = false;
foreach ($r['groups'] as $g) {
	foreach ($g['presets'] as $p) {
		$ids[] = $p['id'];
		$labelled = $labelled && is_string($p['label']) && $p['label'] !== '';
		$leak = $leak || isset($p['regex']) || isset($p['examples_valid']);
	}
}
sort($ids);
efbt('every catalog id is listed once (99)', $ids === $catIds && count($ids) === 99);
efbt('every preset has a label', $labelled);
efbt('no regex or examples are sent to the builder', !$leak);
$rtLeak = false;
foreach ($r['runtime'] as $rt) {
	$rtLeak = $rtLeak || isset($rt['examples_valid']) || isset($rt['metadata']) || isset($rt['sources']) || !isset($rt['regex']);
}
efbt('runtime entries for the builder preview: 99, engine fields only', count($r['runtime']) === 99 && !$rtLeak && $r['runtime']['ir.mobile']['placeholder'] === '۰۹۱۲ ۳۴۵ ۶۷۸۹');
efbt('response is cached for a day', $GLOBALS['efbt']['cache_sets'] === 1 && $GLOBALS['efbt']['last_expire'] === 86400 && isset($GLOBALS['efbt']['cache']['emsfb|efb_masks_cat_fa_IR_fa_IR_4.3.1-test']));
$r2 = efbt_call();
efbt('second call served from the cache', $GLOBALS['efbt']['cache_sets'] === 1 && $r2 === $r);

// A loaded translation pack wins over the catalog fallback.
efbt_reset(array('user_locale' => 'fa_IR', 'site_locale' => 'fa_IR', 'tr' => array('Iran mobile number' => 'شماره همراه', 'Iran' => 'ایران')));
$r = efbt_call();
efbt('translation pack label is used', $r['groups'][0]['presets'][0]['label'] === 'شماره همراه' && $r['groups'][0]['label'] === 'ایران');

// Arabic site (Egypt): Egypt first, then the other Arab countries, then International.
efbt_reset(array('user_locale' => 'ar', 'site_locale' => 'ar_EG'));
$r = efbt_call();
$order = array_map(function ($g) { return $g['country']; }, $r['groups']);
efbt('ar_EG: Egypt first, Arab countries next, then International', array_slice($order, 0, 14) === array('eg', 'sa', 'ae', 'kw', 'qa', 'bh', 'om', 'jo', 'iq', 'lb', 'ma', 'dz', 'tn', 'intl'));
efbt('ar: Arabic labels from the catalog', $r['groups'][0]['presets'][0]['label'] === 'رقم المحمول المصري');

efbt_reset(array('user_locale' => 'en_US', 'site_locale' => 'en_US'));
$r = efbt_call();
$order = array_map(function ($g) { return $g['country']; }, $r['groups']);
efbt('en_US: United States first, then International', $order[0] === 'us' && $order[1] === 'intl');
$restLabels = array_map(function ($g) { return $g['label']; }, array_slice($r['groups'], 2));
$sorted = $restLabels;
sort($sorted, SORT_STRING);
efbt('en_US: remaining groups alphabetical', $restLabels === $sorted && count($r['groups']) === 21);
efbt('en_US: English labels', $r['groups'][0]['presets'][0]['label'] === 'US / Canada phone (NANP)');

efbt_reset(array('user_locale' => 'en_GB', 'site_locale' => 'en_GB'));
$r = efbt_call();
efbt('en_GB: United Kingdom first', $r['groups'][0]['country'] === 'uk' && $r['groups'][1]['country'] === 'intl');
efbt_reset(array('user_locale' => 'de_DE', 'site_locale' => 'de_DE'));
$r = efbt_call();
efbt('de_DE: Germany first', $r['groups'][0]['country'] === 'de');
$plz = array_values(array_filter($cat['masks'], function ($m) { return $m['id'] === 'de.plz'; }))[0];
efbt('en placeholder for non fa/ar admins', $r['groups'][0]['presets'][0]['placeholder'] === $plz['placeholder']['en']);

// Labels file: one literal __() per catalog id and per group, English = catalog label.en.
\Emsfb\Masks::load_labels();
$labels = \Emsfb\Masks_Labels::presets();
$lids = array_keys($labels);
sort($lids);
efbt('labels file covers exactly the catalog ids', $lids === $catIds);
$same = true;
foreach ($cat['masks'] as $m) {
	$same = $same && $labels[$m['id']] === $m['label']['en'];
}
efbt('labels file English text equals catalog label.en', $same);
efbt('labels file has 21 groups', count(\Emsfb\Masks_Labels::groups()) === 21);
$lsrc = file_get_contents(__DIR__ . '/../includes/data/masks-labels-efb.php');
efbt('labels are literal __() calls with the plugin text domain', preg_match_all("/__\\('[^']+', 'easy-form-builder'\\)/", $lsrc) === 120);

// Builder phrases exist in text_efb().
$fsrc = file_get_contents(__DIR__ . '/../includes/functions.php');
$keys = array('mskFormat', 'mskNone', 'mskCustom', 'mskCustomHelp', 'mskPreview', 'mskChecksum', 'mskStore', 'mskStoreCanonical', 'mskStoreDisplay', 'mskStrict', 'mskUnverified', 'mskInvalidMask', 'mskLoading', 'mskLenReplaced', 'mskIntl', 'mskCsLuhn', 'mskCsIban', 'mskCsIrNc', 'mskCsVerhoeff');
$missing = array();
foreach ($keys as $k) {
	if (!preg_match('/"' . $k . '" => \$state && isset\(\$ac->text->' . $k . '\) \? \$ac->text->' . $k . " : esc_html__\\('[^']+','easy-form-builder'\\),/", $fsrc)) {
		$missing[] = $k;
	}
}
efbt('builder phrases follow the text_efb pattern (' . count($keys) . ' keys)' . ($missing ? ' missing: ' . implode(',', $missing) : ''), !$missing);

// catalog_public() can add localized labels for connectors.
efbt_reset(array('user_locale' => 'fa_IR', 'site_locale' => 'fa_IR', 'tr' => array('Iran mobile number' => 'شماره همراه')));
$pub = \Emsfb\Masks::catalog_public('fa_IR');
efbt('catalog_public($locale) adds label_localized', $pub['presets'][0]['label_localized'] === 'شماره همراه');

echo "\n========================================\n";
echo "RESULTS: $pass passed, $fail failed\n";
echo "========================================\n";
exit($fail > 0 ? 1 : 0);
