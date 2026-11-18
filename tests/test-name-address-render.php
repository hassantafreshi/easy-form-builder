<?php
/**
 * F17-A / B: the public markup of name and address parts (the real Emsfb\Formbuilder::addNewElement_efb()):
 *  - autocomplete per part (given-name, family-name, honorific-prefix, additional-name, address-level1..3, address-line1/2,
 *    postal-code, country-name) — and an old firstName / lastName (stored as type text) gets given-name / family-name
 *    (the bug of spec §19: it got "off");
 *  - numeric parts: inputmode numeric, dir ltr, their rule for the page; text parts dir auto; the short code upper case;
 *  - lists: data-grp / data-addr-part; a cascading city closed with "Choose the province first";
 *  - an old address_line / postalcode / text without the keys renders byte for byte as before (no data-grp);
 *  - the fieldset runs (two parts next to each other in one step), the closing-tag count unchanged.
 *
 * Run: php tests/test-name-address-render.php
 */
require __DIR__ . '/field-rules-render-lib.php';
require_once __DIR__ . '/../includes/class-Emsfb-a11y.php';
require_once __DIR__ . '/../includes/class-Emsfb-address-schemes.php';

$pass = 0; $fail = 0;
function t($name, $ok, $info = '') { global $pass, $fail; if ($ok) { $pass++; echo "[PASS] {$name}\n"; } else { $fail++; echo "[FAIL] {$name}" . ($info !== '' ? ": " . substr($info, 0, 400) : '') . "\n"; } }
function attr($html, $name) { return preg_match('/<(?:input|select)[^>]*\s' . preg_quote($name, '/') . '="([^"]*)"/', $html, $m) ? $m[1] : null; }
$TX = array('nothingSelected' => 'Nothing selected', 'addrPickStateFirst' => 'Choose the province first');
$part = function ($id, $type, $el, $kind, $set, $p, $extra = array()) {
	$k = $kind === 'name' ? array('name_format' => $set, 'name_part' => $p) : array('addr_scheme' => $set, 'addr_part' => $p);
	return efb_fr_field($id, $type, array_merge(array('elementId' => $el, 'cmp_grp' => 'g1', 'cmp_kind' => $kind), $k, $extra));
};

// Names.
$h = efb_fr_render($part('n1', 'text', 'firstName', 'name', 'ar4', 'first', array('name_strict' => '1')));
t('first: autocomplete given-name', attr($h, 'autocomplete') === 'given-name', $h);
t('first: data-grp and data-name-part', attr($h, 'data-grp') === 'g1' && attr($h, 'data-name-part') === 'first');
t('strict name part: data-na-strict', attr($h, 'data-na-strict') === '1');
$h = efb_fr_render($part('n2', 'text', 'text', 'name', 'ar4', 'father'));
t('father: additional-name', attr($h, 'autocomplete') === 'additional-name');
$h = efb_fr_render($part('n3', 'text', 'text', 'name', 'full', 'full'));
t('single field: name', attr($h, 'autocomplete') === 'name');
$h = efb_fr_render($part('n4', 'text', 'lastName', 'name', 'ar4', 'family'));
t('family: family-name', attr($h, 'autocomplete') === 'family-name');
$h = efb_fr_render($part('n5', 'text', 'text', 'name', 'fl', 'first', array('autocomplete' => 'nickname')));
t('the field\'s own autocomplete (F16) wins over the part', attr($h, 'autocomplete') === 'nickname');
// The bug of §19: an old firstName / lastName (no F17 keys) now says given-name / family-name.
$h = efb_fr_render(efb_fr_field('o1', 'text', array('elementId' => 'firstName')));
t('old firstName (type text): given-name (was off)', attr($h, 'autocomplete') === 'given-name', $h);
$h = efb_fr_render(efb_fr_field('o2', 'text', array('elementId' => 'lastName')));
t('old lastName: family-name', attr($h, 'autocomplete') === 'family-name');
$h = efb_fr_render(efb_fr_field('o3', 'text', array('elementId' => 'text')));
t('a plain text field keeps "off" on a form from before F16', attr($h, 'autocomplete') === 'off');

// Address parts.
$h = efb_fr_render($part('a1', 'postalcode', 'postalcode', 'address', 'ir', 'postal'));
t('IR postal: postal-code, numeric keyboard, ltr', attr($h, 'autocomplete') === 'postal-code' && attr($h, 'inputmode') === 'numeric' && attr($h, 'dir') === 'ltr', $h);
t('IR postal: its rule and normalizer for the page', attr($h, 'data-na-rx') === htmlspecialchars('^(?!(\d)\1{9})\d{10}$', ENT_QUOTES) && attr($h, 'data-na-nm') === 's' && attr($h, 'data-na-n') === '1');
t('IR postal: class efb-ltr-in-rtl (right-aligned in a right-to-left form)', strpos($h, 'efb-ltr-in-rtl') !== false);
$h = efb_fr_render($part('a2', 'address_line', 'address_line', 'address', 'ir', 'street'));
t('IR street: address-line1, dir auto', attr($h, 'autocomplete') === 'address-line1' && attr($h, 'dir') === 'auto');
$h = efb_fr_render($part('a3', 'address_line', 'address_line', 'address', 'sa', 'district'));
t('SA district: address-level3', attr($h, 'autocomplete') === 'address-level3');
$h = efb_fr_render($part('a4', 'address_line', 'address_line', 'address', 'sa', 'short_code'));
t('SA short code: upper case keyboard, ltr, soft rule', attr($h, 'autocapitalize') === 'characters' && attr($h, 'dir') === 'ltr' && attr($h, 'data-na-opt') === '1');
$h = efb_fr_render($part('a5', 'address_line', 'address_line', 'address', 'ir', 'plaque'));
t('IR plaque: off, numeric', attr($h, 'autocomplete') === 'off' && attr($h, 'inputmode') === 'numeric');
$h = efb_fr_render($part('a6', 'address_line', 'address_line', 'address', 'sa', 'building_no', array('addr_req_mode' => 'sa_short_or_full')));
t('the first SA part says the group mode', attr($h, 'data-na-mode') === 'sa_short_or_full');
$h = efb_fr_render($part('a7', 'address_line', 'address_line', 'address', 'us', 'line2'));
t('US line 2: address-line2', attr($h, 'autocomplete') === 'address-line2');
$h = efb_fr_render($part('a8', 'address_line', 'address_line', 'address', 'uk', 'county'));
t('UK county: address-level1', attr($h, 'autocomplete') === 'address-level1');
$h = efb_fr_render($part('a9', 'postalcode', 'postalcode', 'address', 'ir', 'postal', array('mask_preset' => 'ir.postal_code')));
t('with the AdnADR mask the mask draws the keyboard (no attribute twice)', substr_count($h, 'inputmode=') <= 1 && substr_count($h, ' dir=') <= 1, $h);

// Lists.
$h = efb_fr_render($part('s1', 'stateProvince', 'stateProvince', 'address', 'ir', 'state', array('country' => 'ir')), $TX);
t('state list: data-grp, data-addr-part, address-level1', attr($h, 'data-addr-part') === 'state' && attr($h, 'autocomplete') === 'address-level1' && attr($h, 'data-grp') === 'g1', $h);
$h = efb_fr_render($part('c1', 'cityList', 'cityList', 'address', 'ir', 'city', array('addr_cascade' => '1', 'country' => 'ir', 'statePov' => '')), $TX);
t('cascading city: closed, data-cascade, "Choose the province first"', attr($h, 'data-cascade') === '1' && preg_match('/<select[^>]*\sdisabled/', $h) && strpos($h, 'Choose the province first') !== false, $h);
$h = efb_fr_render($part('c2', 'cityList', 'cityList', 'address', 'intl', 'city', array('country' => 'GB', 'statePov' => 'x')), $TX);
t('a city without cascade is open', !preg_match('/<select[^>]*\sdisabled/', $h) && attr($h, 'data-cascade') === null);
$h = efb_fr_render($part('p1', 'select', 'select', 'name', 'pfl', 'prefix'), $TX);
t('title select: honorific-prefix, data-name-part', attr($h, 'autocomplete') === 'honorific-prefix' && attr($h, 'data-name-part') === 'prefix', $h);

// Old fields: byte for byte (the keys missing: no attribute added; same markup as a field rendered without F17's class).
foreach (array('address_line', 'postalcode') as $ty) {
	$h = efb_fr_render(efb_fr_field('x' . $ty, $ty, array('elementId' => $ty)));
	t("old $ty: no group attributes", strpos($h, 'data-grp') === false && strpos($h, 'data-na-') === false && strpos($h, 'data-addr-part') === false);
}
$h = efb_fr_render(efb_fr_field('xc', 'cityList', array('elementId' => 'cityList', 'country' => 'GB', 'statePov' => 'x')), $TX);
t('old city list: no group attributes, open', strpos($h, 'data-grp') === false && !preg_match('/<select[^>]*\sdisabled/', $h));
$h = efb_fr_render(efb_fr_field('xb', 'address_line', array('elementId' => 'address_line', 'cmp_grp' => 'zz', 'cmp_kind' => 'address', 'addr_scheme' => 'ir', 'addr_part' => 'nope')));
t('an unknown part is not a part (nothing added)', strpos($h, 'data-grp') === false);
// Hostile values are escaped in the attributes.
$h = efb_fr_render(efb_fr_field('xe', 'address_line', array('elementId' => 'address_line', 'cmp_grp' => '"><script>', 'cmp_kind' => 'address', 'addr_scheme' => 'ir', 'addr_part' => 'street')));
t('a hostile cmp_grp is escaped', strpos($h, '"><script>') === false);
// The closing-tag count: a part renders with the same number of </div> as the same field without keys.
$a = efb_fr_render(efb_fr_field('q1', 'postalcode', array('elementId' => 'postalcode')));
$b = efb_fr_render($part('q1', 'postalcode', 'postalcode', 'address', 'ir', 'postal'));
t('closing tags unchanged', substr_count($a, '</div>') === substr_count($b, '</div>') && substr_count($a, '<div') === substr_count($b, '<div'));

// Fieldset runs.
$rows = array(array('type' => 'form'), array('type' => 'step', 'id_' => '1', 'step' => 1),
	array('id_' => 'a', 'type' => 'text', 'cmp_grp' => 'a', 'cmp_kind' => 'name', 'step' => 1),
	array('id_' => 'b', 'type' => 'select', 'cmp_grp' => 'a', 'cmp_kind' => 'name', 'step' => 1),
	array('id_' => 'o', 'type' => 'option', 'parent' => 'b'),
	array('id_' => 'c', 'type' => 'text', 'cmp_grp' => 'a', 'cmp_kind' => 'name', 'step' => 1),
	array('id_' => 'd', 'type' => 'email', 'step' => 1),
	array('id_' => 'e', 'type' => 'text', 'cmp_grp' => 'e', 'cmp_kind' => 'address', 'step' => 1),
	array('id_' => 'f', 'type' => 'text', 'cmp_grp' => 'f', 'cmp_kind' => 'name', 'step' => 1),
	array('type' => 'step', 'id_' => '2', 'step' => 2),
	array('id_' => 'g', 'type' => 'text', 'cmp_grp' => 'f', 'cmp_kind' => 'name', 'step' => 2),
	array('id_' => 'h', 'type' => 'text', 'cmp_grp' => 'h', 'cmp_kind' => 'address', 'step' => 2),
	array('id_' => 'i', 'type' => 'text', 'cmp_grp' => 'h', 'cmp_kind' => 'address', 'step' => 2, 'group' => 'R'),
);
list($open, $close) = \Emsfb\Address_Schemes::fieldset_runs($rows, array('i' => 'R'));
t('fieldset around a, b (its options in between), c', $open === array(2 => 'a') && $close === array(5 => true), json_encode(array($open, $close)));
$fs = \Emsfb\Address_Schemes::fieldset_open('a', 'name', array('name' => 'نام'));
t('the fieldset: legend (screen readers), no border', strpos($fs, '<legend class="efb efb-vh">نام</legend>') !== false && strpos($fs, 'border:0') !== false);

echo "\nRESULTS: {$pass} passed, {$fail} failed\n";
exit($fail ? 1 : 0);
