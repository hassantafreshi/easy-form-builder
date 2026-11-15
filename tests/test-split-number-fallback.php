<?php
/**
 * SPLIT §6.4 (subtask C): numbers without the Numbers & Calculations add-on (AdnCLC absent, off, too old or above the
 * plan). The core renders and checks a number exactly as before F04:
 *  - an old form (no F04 keys) renders byte for byte as the F04 fixture (tests/fixtures/f04-old-form-render.json);
 *  - a number with F04 keys is the plain <input type="number"> with step="any" (decimals are not blocked), min / max
 *    from milen / mlen; no data-numcfg;
 *  - a calc field: the neutral notice for the visitor, "Needs the Numbers & Calculations add-on" (in <bdi>) with an
 *    Install link for an administrator; a two-handle slider: the notice, not one handle;
 *  - the server: is_numeric + min / max, Persian / Arabic-Indic digits read (open question 1, kept in core); the
 *    browser's calc row is never stored;
 *  - the core has no calc engine file any more.
 *
 * Run: php tests/test-split-number-fallback.php
 */
$passed = 0;
$failed = 0;
function t($name, $actual, $expected) {
	global $passed, $failed;
	if ($actual === $expected) { echo "[PASS] {$name}\n"; $passed++; return; }
	echo "[FAIL] {$name}: expected " . var_export($expected, true) . ', got ' . var_export($actual, true) . "\n";
	$failed++;
}
if (!function_exists('get_locale')) { function get_locale() { return $GLOBALS['efb_test_locale'] ?? 'en_US'; } }
require __DIR__ . '/field-rules-render-lib.php';
if (!function_exists('admin_url')) { function admin_url($p = '') { return 'https://site.test/wp-admin/' . $p; } }
if (!function_exists('esc_url')) { function esc_url($u) { return (string) $u; } }
if (!function_exists('current_user_can')) { function current_user_can($c) { return !empty($GLOBALS['efb_can']); } }
require_once __DIR__ . '/../includes/class-Emsfb-addon-catalog.php';
require_once __DIR__ . '/../includes/class-Emsfb-addon-keys.php';

t('the Calc engine is not loaded (no add-on)', class_exists('\\Emsfb\\Calc'), false);
t('core no longer ships the engine', array(is_file(__DIR__ . '/../includes/class-Emsfb-calc.php'), is_file(__DIR__ . '/../public/assets/js/calc-efb.js'), is_file(__DIR__ . '/../includes/admin/assets/js/calc-builder-efb.js'), is_file(__DIR__ . '/../public/assets/css/calc-efb.css')), array(false, false, false, false));

function efb_nf_render(array $field, $row0 = array(), $rtl = false, $locale = 'en_US', $options = array(), $texts = null) {
	$GLOBALS['efb_test_rtl'] = $rtl;
	$GLOBALS['efb_test_locale'] = $locale;
	$all = array((object) array_merge(array('type' => 'form', 'steps' => 1, 'formName' => 'T', 'currency' => 'usd'), $row0), (object) array('type' => 'step', 'id_' => '1', 'step' => '1', 'name' => 'S'), (object) $field);
	foreach ($options as $o) $all[] = (object) $o;
	$fb = new \Emsfb\Formbuilder($all, true);
	$r = $fb->addNewElement_efb(2, $field['id_'], 7, $texts ?: array('numRange' => 'Between %1$s and %2$s', 'tfnapca' => 'This field is currently unavailable. Please contact the site administrator.'), true);
	return is_array($r) ? (string) $r[0] : (string) $r;
}

// An old form: byte for byte what core rendered before F04 (and with F04 in core, for a field without the keys).
$rows = json_decode(file_get_contents(__DIR__ . '/fixtures/f04-old-form-rows.json'), true);
$gold = json_decode(file_get_contents(__DIR__ . '/fixtures/f04-old-form-render.json'), true);
foreach ($rows as $k => $c) {
	t("old form without the add-on, byte for byte: {$k}", efb_nf_render($c['field'], array(), !empty($c['rtl']), isset($c['locale']) ? $c['locale'] : 'en_US', isset($c['options']) ? $c['options'] : array()), $gold[$k]);
}

// A number that uses the add-on's keys.
$h = efb_nf_render(efb_fr_field('n1', 'number', array('milen' => '-10', 'mlen' => '9.5', 'num_step' => '0.5', 'num_decimals' => '2', 'num_format' => 'group')));
preg_match('/<input[^>]*id="n1_"[^>]*>/', $h, $m);
$tag = isset($m[0]) ? $m[0] : '';
t('number with F04 keys: the plain type="number" box', (bool) preg_match('/<input type="number"/', $tag), true);
t('…with step="any" (the browser does not refuse 9.25)', strpos($tag, 'step="any"') !== false, true);
t('…no data-numcfg / data-num (nothing for an engine that is not there)', array(strpos($tag, 'data-numcfg'), strpos($tag, 'data-num=')), array(false, false));
$plain = efb_nf_render(efb_fr_field('n0', 'number', array('milen' => '1', 'mlen' => '10')));
t('a number without F04 keys gets no step attribute (as before)', strpos($plain, 'step="any"'), false);

// The calc field and the two-handle slider.
$c = efb_nf_render(array('id_' => 'c1', 'type' => 'calc', 'name' => 'Total', 'formula' => '{n1}*2', 'step' => '1', 'amount' => '3', 'dataId' => 'c1-id', 'required' => '1'));
t('calc field, visitor: the neutral notice in its place, no result box', array(strpos($c, 'This field is currently unavailable') !== false, strpos($c, 'efb-addon-needed') !== false, strpos($c, '<output'), strpos($c, 'role="note"') !== false), array(true, true, false, true));
$GLOBALS['efb_can'] = true;
$c = efb_nf_render(array('id_' => 'c1', 'type' => 'calc', 'name' => 'Total', 'formula' => '{n1}*2', 'step' => '1', 'amount' => '3', 'dataId' => 'c1-id'), array(), true, 'fa_IR', array(), array('tfnapca' => 'x', 'addonNeededT' => 'Needs the %s add-on', 'addonInstallBtn' => 'Install'));
t('calc field, administrator (fa_IR page): "Needs the <bdi>Numbers & Calculations</bdi> add-on", plaintext, 44 px Install link', array(
	strpos($c, 'Needs the <bdi>Numbers &amp; Calculations</bdi> add-on') !== false, strpos($c, 'unicode-bidi:plaintext') !== false, strpos($c, 'min-height:44px') !== false, strpos($c, 'page=Emsfb_addon') !== false), array(true, true, true, true));
$GLOBALS['efb_can'] = false;
$d = efb_nf_render(array('id_' => 'r1', 'type' => 'range', 'name' => 'Budget', 'range_mode' => 'dual', 'milen' => '0', 'mlen' => '100', 'value' => '', 'step' => '1', 'amount' => '3', 'dataId' => 'r1-id', 'classes' => ''));
t('two-handle slider: the notice, not a single handle (the answer would change shape)', array(strpos($d, 'efb-addon-needed') !== false, strpos($d, 'type="range"')), array(true, false));
$one = efb_nf_render(efb_fr_field('r2', 'range', array('milen' => '0', 'mlen' => '10', 'value' => '5')));
t('a one-handle slider without F04 keys is drawn as before', strpos($one, 'type="range"') !== false, true);

// The server (class-Emsfb-public.php): the number branch without the engine; the calc row.
$src = file_get_contents(__DIR__ . '/../includes/class-Emsfb-public.php');
$i = strpos($src, "case 'number':\n\t\t\t\t\t\tcase 'prcfld':");
$branch = substr($src, $i, 3200);
t('server: without the engine a number is is_numeric + min / max, after Masks::normalize_digits()', array(
	strpos($branch, "class_exists('\\\\Emsfb\\\\Calc')") !== false, strpos($branch, 'Masks::normalize_digits') !== false,
	strpos($branch, 'Masks::normalize_digits') < strpos($branch, "is_numeric(\$item['value'])")), array(true, true, true));
require_once __DIR__ . '/../includes/class-Emsfb-masks.php';
$v = str_replace("\u{066B}", '.', \Emsfb\Masks::normalize_digits('۱۲٫۵'));
t('…"۱۲٫۵" reads as 12.5', array($v, is_numeric($v)), array('12.5', true));
$k = strpos($src, "case 'calc':\n\t\t\t\t\t\t\t// F04: what the browser says a calc field is never kept");
t('server: the browser\'s calc row is never stored (validated_item null), with or without the engine', $k !== false && strpos(substr($src, $k, 400), '$validated_item = null;') !== false, true);

echo "\nRESULTS: {$passed} passed, {$failed} failed\n";
exit($failed ? 1 : 0);
