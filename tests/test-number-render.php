<?php
/**
 * F04-B / C: the PHP renderer's number markup (Emsfb\Formbuilder::addNewElement_efb).
 *
 *  0  Old forms: number, slider, price, a text field with F01's numeric mask and a choice field without the
 *     F04 keys render byte for byte as on the commit before F04 (tests/fixtures/f04-old-form-render.json, made
 *     with that commit's renderer from tests/fixtures/f04-old-form-rows.json).
 *  1  A number with F04 keys: a text box (inputmode decimal, dir ltr, data-num, data-numcfg with the limits as
 *     decimals: -10 and 9.5, never maxlength="9"), no minlength / maxlength; "Between -10 and 9.5" read
 *     through aria-describedby; the page's wrapper -f still opened (closing-tag count).
 *  2  Affixes: num_suffix in the F03 input group; the form currency (USD: "$" before, IRR on an RTL site:
 *     the symbol after); an F03 prefix wins over num_prefix.
 *  3  Settings that a visitor could abuse are escaped (a prefix with markup).
 *
 * Run: php tests/test-number-render.php
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
require_once __DIR__ . '/../includes/class-Emsfb-calc.php';

function efb_nr_render(array $field, $row0 = array(), $rtl = false, $locale = 'en_US', $options = array()) {
	$GLOBALS['efb_test_rtl'] = $rtl;
	$GLOBALS['efb_test_locale'] = $locale;
	$all = array((object) array_merge(array('type' => 'form', 'steps' => 1, 'formName' => 'T', 'currency' => 'usd'), $row0), (object) array('type' => 'step', 'id_' => '1', 'step' => '1', 'name' => 'S'), (object) $field);
	foreach ($options as $o) $all[] = (object) $o;
	$fb = new \Emsfb\Formbuilder($all, true);
	$r = $fb->addNewElement_efb(2, $field['id_'], 7, array('numRange' => 'Between %1$s and %2$s'), true);
	return is_array($r) ? (string) $r[0] : (string) $r;
}

/* 0 */
$rows = json_decode(file_get_contents(__DIR__ . '/fixtures/f04-old-form-rows.json'), true);
$gold = json_decode(file_get_contents(__DIR__ . '/fixtures/f04-old-form-render.json'), true);
foreach ($rows as $k => $c) {
	t("0 old form, byte for byte as before F04: {$k}", efb_nr_render($c['field'], array(), !empty($c['rtl']), isset($c['locale']) ? $c['locale'] : 'en_US', isset($c['options']) ? $c['options'] : array()), $gold[$k]);
}

/* 1 */
$h = efb_nr_render(efb_fr_field('n1', 'number', array('milen' => '-10', 'mlen' => '9.5', 'num_step' => '0.5')));
preg_match('/<input[^>]*id="n1_"[^>]*>/', $h, $m);
$tag = isset($m[0]) ? $m[0] : '';
t('1 type text, inputmode decimal, dir ltr, data-num', (bool) preg_match('/type="text"/', $tag) && strpos($tag, 'inputmode="decimal"') !== false && strpos($tag, 'dir="ltr"') !== false && strpos($tag, 'data-num="1"') !== false, true);
t('1 no minlength / maxlength (the old maxlength="9" of a 9.5 maximum)', (bool) preg_match('/(min|max)length=/', $tag), false);
preg_match('/data-numcfg="([^"]*)"/', $tag, $cm);
$cfg = isset($cm[1]) ? json_decode(html_entity_decode($cm[1], ENT_QUOTES, 'UTF-8'), true) : null;
t('1 data-numcfg: limits as decimals, the step', is_array($cfg) ? array($cfg['min'], $cfg['max'], $cfg['step'], $cfg['decimals']) : null, array('-10', '9.5', '0.5', null));
t('1 "Between -10 and 9.5" for a screen reader, in aria-describedby', strpos($h, 'id="n1_-nr">Between -10 and 9.5</span>') !== false && (bool) preg_match('/aria-describedby="[^"]*n1_-nr/', $tag), true);
t('1 the -f wrapper is opened (closing-tag count unchanged)', substr_count($h, 'id="n1-f"'), 1);
$plain = efb_nr_render(efb_fr_field('n0', 'number', array('milen' => '1', 'mlen' => '10')));
t('1 a number without F04 keys is still type number', (bool) preg_match('/<input type="number"[^>]*id="n0_"/', $plain), true);

/* 2 */
$h = efb_nr_render(efb_fr_field('n2', 'number', array('num_format' => 'group', 'num_suffix' => 'kg')));
t('2 num_suffix in the F03 input group', strpos($h, 'id="n2_-sfx">kg</span>') !== false, true);
$h = efb_nr_render(efb_fr_field('n3', 'number', array('num_currency' => 'form')), array('currency' => 'usd'));
t('2 form currency (USD, LTR): "$" before the box', strpos($h, 'id="n3_-pfx">$</span>') !== false && strpos($h, '-sfx') === false, true);
$h = efb_nr_render(efb_fr_field('n4', 'number', array('num_currency' => 'form', 'num_digits' => 'native')), array('currency' => 'irr'), true, 'fa_IR');
t('2 form currency on an RTL site (IRR): the symbol after the box; Persian digits in data-numcfg script', strpos($h, 'id="n4_-sfx">﷼</span>') !== false && strpos($h, '&quot;script&quot;:&quot;fa&quot;') !== false, true);
$h = efb_nr_render(efb_fr_field('n5', 'number', array('num_step' => '1', 'prefix' => '€', 'num_prefix' => '$')));
t('2 an F03 prefix wins over num_prefix', strpos($h, 'id="n5_-pfx">€</span>') !== false, true);

/* 3 */
$h = efb_nr_render(efb_fr_field('n6', 'number', array('num_step' => '1', 'num_prefix' => '<img src=x onerror=alert(1)>', 'milen' => '1', 'mlen' => '"><svg>')));
t('3 markup in a prefix or a limit never reaches the page as markup', strpos($h, '<img') === false && strpos($h, '<svg') === false, true);

echo "\nRESULTS: {$passed} passed, {$failed} failed\n";
exit($failed ? 1 : 0);
