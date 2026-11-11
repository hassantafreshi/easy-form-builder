<?php
/**
 * F01-D/H: PHP renderer attributes of masked fields, and a regression check that fields without mask
 * keys render byte for byte like the renderer before F01 (tests/fixtures/masks-render-golden.json was
 * produced from includes/class-Emsfb-formbuilder.php at commit 3ec6ea5, before sub-task D).
 * Also checks the JS twin efb_mask_attrs_efb() in admin-efb.js and the public enqueue/runtime wiring.
 *
 * Run: php tests/test-masks-render.php
 */
if (!defined('ABSPATH')) {
	define('ABSPATH', __DIR__ . '/');
}
if (!function_exists('get_option')) {
	function get_option($o, $d = false) { return $d; }
}
if (!function_exists('esc_attr')) {
	function esc_attr($v) { return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8'); }
}
require_once __DIR__ . '/../includes/class-Emsfb-masks.php';
require_once __DIR__ . '/../includes/class-Emsfb-formbuilder.php';

$pass = 0;
$fail = 0;
function rt($label, $cond, $detail = '') {
	global $pass, $fail;
	if ($cond) { $pass++; echo "[PASS] $label\n"; } else { $fail++; echo "[FAIL] $label" . ($detail !== '' ? "\n      $detail" : '') . "\n"; }
}
function render_efb($elementId, array $row) {
	static $m = null;
	if ($m === null) {
		$m = new ReflectionMethod('\Emsfb\Formbuilder', 'generateElementSpecificFields_efb');
		$m->setAccessible(true);
	}
	$vj = (object) $row;
	$b = new \Emsfb\Formbuilder(array($vj), false);
	$res = $m->invoke($b, $elementId, $vj->id_, $vj, array('', '', '', 'col-md-12'), '<div>d</div>', '<label>l</label>', '<small>t</small>', '<div id="f">', 'aria-describedby="x"', '', 7, array());
	return $res['ui'];
}
function input_tag($html) {
	return preg_match('/<input [^>]*>/', $html, $m) ? $m[0] : '';
}
function base_row($id, $type, $extra = array()) {
	return array_merge(array('type' => $type, 'id_' => $id, 'name' => 'F', 'placeholder' => '', 'el_border_color' => 'border-d', 'required' => '0', 'value' => '', 'classes' => ''), $extra);
}

// 1. Old forms: byte-identical markup.
$rows = json_decode(file_get_contents(__DIR__ . '/fixtures/masks-render-rows.json'), true);
$golden = json_decode(file_get_contents(__DIR__ . '/fixtures/masks-render-golden.json'), true);
foreach ($rows as $i => $r) {
	$html = render_efb($r['elementId'], $r['row']);
	rt('unmasked ' . $r['elementId'] . ' (' . $r['row']['id_'] . ') renders as before F01', $html === $golden[$i], "got: $html");
}
// An empty mask_preset or an unknown preset id also renders as before.
foreach (array('' => 'empty', 'zz.unknown' => 'unknown') as $p => $what) {
	$r = $rows[2];
	$r['row']['mask_preset'] = $p;
	rt("tel with $what mask_preset renders as before", render_efb('tel', $r['row']) === $golden[2]);
}

// 2. Preset on tel: catalog attributes, no fixed tel pattern, no legacy length.
$tag = input_tag(render_efb('tel', base_row('m1', 'tel', array('mask_preset' => 'ir.mobile', 'mlen' => '15', 'milen' => '3', 'required' => '1'))));
rt('tel + ir.mobile: data-mask-preset', strpos($tag, 'data-mask-preset="ir.mobile"') !== false, $tag);
rt('tel + ir.mobile: inputmode/dir/store', strpos($tag, 'inputmode="tel"') !== false && strpos($tag, 'dir="ltr"') !== false && strpos($tag, 'data-mask-store="canonical"') !== false);
rt('tel + ir.mobile: catalog autocomplete', strpos($tag, 'autocomplete="tel-national"') !== false);
rt('tel + ir.mobile: fixed tel pattern removed', strpos($tag, 'pattern=') === false);
rt('tel + ir.mobile: no legacy minlength/maxlength, no maxlength for phone presets', strpos($tag, 'minlength') === false && strpos($tag, 'maxlength') === false);
rt('tel + ir.mobile: RTL alignment class', strpos($tag, 'efb-ltr-in-rtl') !== false);
rt('tel + ir.mobile: still type="tel"', strpos($tag, 'type="tel"') !== false);

$tag = input_tag(render_efb('text', base_row('m2', 'text', array('mask_preset' => 'ir.national_id', 'mask_store' => 'display'))));
rt('text + ir.national_id: maxlength from the mask (12)', strpos($tag, 'maxlength="12"') !== false, $tag);
rt('text + ir.national_id: numeric keyboard, display storage, type text', strpos($tag, 'inputmode="numeric"') !== false && strpos($tag, 'data-mask-store="display"') !== false && strpos($tag, 'type="text"') !== false);
rt('text + ir.national_id: autocomplete stays off (preset has none)', strpos($tag, 'autocomplete="off"') !== false);

$tag = input_tag(render_efb('postalcode', base_row('m3', 'postalcode', array('mask_preset' => 'ir.postal_code', 'mask_strict' => '1'))));
rt('postalcode + strict: data-mask-strict and postal-code autocomplete', strpos($tag, 'data-mask-strict="1"') !== false && strpos($tag, 'autocomplete="postal-code"') !== false, $tag);
$tag = input_tag(render_efb('text', base_row('m4', 'text', array('mask_preset' => 'ir.mobile', 'mask_strict' => '1'))));
rt('mask_strict ignored for presets without a strict rule', strpos($tag, 'data-mask-strict') === false);
$tag = input_tag(render_efb('text', base_row('m5', 'text', array('mask_preset' => 'intl.card', 'autocomplete' => 'off'))));
rt('explicit field autocomplete wins over the preset default', strpos($tag, 'autocomplete="off"') !== false && strpos($tag, 'cc-number') === false, $tag);
$tag = input_tag(render_efb('text', base_row('m6', 'text', array('mask_preset' => 'intl.card'))));
rt('formatter preset (card): no maxlength, cc-number autocomplete', strpos($tag, 'maxlength') === false && strpos($tag, 'autocomplete="cc-number"') !== false, $tag);

// 3. Custom masks.
$tag = input_tag(render_efb('text', base_row('c1', 'text', array('mask_preset' => 'custom', 'mask_custom' => '(999) 999-9999'))));
rt('custom: data-mask, maxlength 14, numeric keyboard', strpos($tag, 'data-mask="(999) 999-9999"') !== false && strpos($tag, 'maxlength="14"') !== false && strpos($tag, 'inputmode="numeric"') !== false, $tag);
$tag = input_tag(render_efb('text', base_row('c2', 'text', array('mask_preset' => 'custom', 'mask_custom' => 'aa9&9'))));
rt('custom: attribute escaped, no numeric keyboard for letters', strpos($tag, 'data-mask="aa9&amp;9"') !== false && strpos($tag, 'inputmode') === false, $tag);
$tag = input_tag(render_efb('text', base_row('c3', 'text', array('mask_preset' => 'custom', 'mask_custom' => '9{'))));
rt('custom: broken mask renders as unmasked', strpos($tag, 'data-mask') === false && strpos($tag, 'dir=') === false);

// 4. Mask control not applied to name / address parts even with keys.
$tag = input_tag(render_efb('firstName', base_row('n1', 'text', array('mask_preset' => 'ir.mobile'))));
rt('firstName ignores mask keys', strpos($tag, 'data-mask') === false);

// 5. H: inputmode on any text-like field, alone.
$tag = input_tag(render_efb('number', base_row('h1', 'number', array('inputmode' => 'decimal'))));
rt('H: number + inputmode=decimal', strpos($tag, 'inputmode="decimal"') !== false && strpos($tag, 'type="number"') !== false, $tag);
$tag = input_tag(render_efb('tel', base_row('h2', 'tel', array('inputmode' => 'numeric'))));
rt('H: tel keeps its pattern and gets the inputmode', strpos($tag, 'pattern=') !== false && strpos($tag, 'inputmode="numeric"') !== false, $tag);
$tag = input_tag(render_efb('text', base_row('h3', 'text', array('inputmode' => 'auto'))));
rt('H: inputmode=auto adds nothing', strpos($tag, 'inputmode') === false);
$tag = input_tag(render_efb('text', base_row('h4', 'text', array('inputmode' => '" onfocus="x'))));
rt('H: invalid inputmode ignored', strpos($tag, 'inputmode') === false && strpos($tag, 'onfocus') === false);
$tag = input_tag(render_efb('text', base_row('h5', 'text', array('mask_preset' => 'ir.national_id', 'inputmode' => 'tel'))));
rt('H: explicit inputmode overrides the preset', strpos($tag, 'inputmode="tel"') !== false && substr_count($tag, 'inputmode=') === 1, $tag);

// 6. Wiring: public enqueue only with masks, runtime subset, JS renderer twin.
$pub = file_get_contents(__DIR__ . '/../includes/class-Emsfb-public.php');
rt('public: masks-efb.js enqueued only when the form JSON has a non-empty mask_preset', strpos($pub, "wp_register_script('efb-masks-js'") !== false && strpos($pub, "preg_match('/mask_preset") !== false);
preg_match("/preg_match\\('(\\/mask_preset[^']+)', \\\$form_structure_json\\)/", $pub, $mre);
$re = isset($mre[1]) ? str_replace('\\\\', '\\', $mre[1]) : '/x^/'; // single-quoted PHP: \\\\ is one backslash
rt('enqueue test matches the stored (escaped) JSON', preg_match($re, '[{\"type\":\"tel\",\"mask_preset\":\"ir.mobile\"}]') === 1 && preg_match($re, '[{"mask_preset":"custom"}]') === 1);
rt('enqueue test ignores an empty preset and forms without masks', preg_match($re, '[{\"mask_preset\":\"\"}]') === 0 && preg_match($re, '[{\"type\":\"tel\"}]') === 0);
rt('public: ajax_object_efm.masks built from runtime_subset of collected ids', strpos($pub, "\$ar_core['masks'] = \$masks_efb") !== false && strpos($pub, '$this->mask_ids_efb[] = $valj_efb[$i]->mask_preset') !== false);
$admin = file_get_contents(__DIR__ . '/../includes/admin/assets/js/admin-efb.js');
rt('JS renderer uses efb_mask_attrs_efb in the text case', strpos($admin, 'const maskEfb = efb_mask_attrs_efb(valj_efb[iVJ], elementId);') !== false);
$css = file_get_contents(__DIR__ . '/../includes/admin/assets/css/admin-rtl-efb.css');
rt('RTL stylesheet keeps masked inputs LTR', strpos($css, 'input.efb.efb-ltr-in-rtl') !== false && strpos($css, 'direction: ltr !important') !== false);

echo "\n========================================\n";
echo "RESULTS: $pass passed, $fail failed\n";
echo "========================================\n";
exit($fail > 0 ? 1 : 0);
