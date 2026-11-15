<?php
/**
 * F16-E / F16-F: the markup of the public form (docs/feature-gaps/F16-a11y-inline-validation.fa.md §5.1, §7; subtasks
 * E and F), with the real renderer (Emsfb\Formbuilder) and Emsfb\A11y.
 *
 *  E  the required star is hidden from screen readers (aria-hidden, the field says aria-required); the empty error box
 *     holds nothing (no " ! "); a radio group is a radiogroup named by its label (no for="" pointing at nothing) and
 *     says it is required, a checkbox group a group; a mobile field is type="tel" with autocomplete="tel"; a textarea
 *     has no value attribute and an escaped placeholder; a field without a label is named by its placeholder, never
 *     aria-label=""; the dead generateSwitchInput_efb() is gone; a step has a hidden legend "Step 2 of 4: name".
 *  F  every row of the §5.1 table: the token each type gets on a form of F16 and on an older one (text stays "off");
 *     the field's own key wins (also over a mask preset); an unknown token never reaches the page.
 *
 * Run: php tests/test-a11y-render.php
 */
$passed = 0; $failed = 0;
function t($name, $ok, $info = '') {
	global $passed, $failed;
	if ($ok) { echo "[PASS] {$name}\n"; $passed++; return; }
	echo "[FAIL] {$name}" . ($info !== '' ? ': ' . (is_string($info) ? $info : json_encode($info)) : '') . "\n"; $failed++;
}
if (!function_exists('get_locale')) { function get_locale() { return 'en_US'; } }
if (!function_exists('wp_json_encode')) { function wp_json_encode($v, $f = 0) { return json_encode($v, $f); } }
if (!function_exists('wp_strip_all_tags')) { function wp_strip_all_tags($s) { return trim(strip_tags((string) $s)); } }
if (!function_exists('get_efbFunction')) { class EfbA11yTestFn { public function text_efb($k) { return array_fill_keys((array) $k, 'x'); } } function get_efbFunction() { return new EfbA11yTestFn(); } }
if (!function_exists('wp_localize_script')) { function wp_localize_script() {} }
if (!function_exists('wp_register_style')) { function wp_register_style() {} }
if (!defined('EMSFB_PLUGIN_DIRECTORY')) define('EMSFB_PLUGIN_DIRECTORY', dirname(__DIR__) . '/');
if (!function_exists('wp_script_is')) { function wp_script_is() { return false; } }
if (!function_exists('wp_style_is')) { function wp_style_is() { return false; } }
if (!function_exists('wp_add_inline_script')) { function wp_add_inline_script() {} }
require __DIR__ . '/field-rules-render-lib.php';
require_once __DIR__ . '/../includes/class-Emsfb-a11y.php';
require_once __DIR__ . '/../includes/class-Emsfb-stock.php';
require_once __DIR__ . '/../includes/class-Emsfb-choices.php';
require_once __DIR__ . '/../includes/class-Emsfb-smart-tags.php';
$TX = array('tfnapca' => 'Not available', 'thisFeatureAvailableFreePlusPro' => 'Free Plus', 'updateUrbrowser' => 'Update', 'nothingSelected' => 'Nothing selected');

/** One field (and its options) on a form whose row 0 is $row0; returns the markup of the field. */
function efb_a11y_r(array $field, array $row0 = array(), array $options = array()) {
	global $TX;
	$rows = array((object) array_merge(array('type' => 'form', 'steps' => 1, 'formName' => 'T'), $row0), (object) array('type' => 'step', 'id_' => '1', 'step' => '1', 'name' => 'S'), json_decode(json_encode($field)));
	foreach ($options as $o) $rows[] = json_decode(json_encode(array_merge(array('type' => 'option', 'parent' => $field['id_'], 'step' => '1', 'amount' => '3', 'el_text_size' => 'fs-6', 'el_text_color' => 'text-labelEfb'), $o)));
	$fb = new \Emsfb\Formbuilder($rows, true);
	$r = @$fb->addNewElement_efb(2, $field['id_'], 7, $TX, true);
	return is_array($r) ? (string) $r[0] : (string) $r;
}
$NEW = array('val_timing' => 'blur', 'err_summary' => '1');
$ac = function ($html, $id) { return preg_match('/<(?:input|select|textarea)[^>]*id="' . $id . '_(?:options)?"[^>]*>/', $html, $m) ? (preg_match('/autocomplete="([^"]*)"/', $m[0], $a) ? $a[1] : null) : 'NO-INPUT'; };

/* ---------------------------------------------------------------- E */
$h = efb_a11y_r(efb_fr_field('t1', 'text', array('required' => '1')));
t('E1 the star is aria-hidden (not role="none"), the input says aria-required="true"', strpos($h, 'id="t1_req" aria-hidden="true">*</span>') !== false && strpos($h, 'role="none"') === false && strpos($h, 'aria-required="true"') !== false, $h);
t('E1 the empty error box holds nothing (no " ! " read through aria-describedby)', strpos($h, '<small id="t1_-message" class="efb py-1 fs-7 tx ttiptext px-2" style="display:none"></small>') !== false);
t('E1 the box is still inline right after the id div (wpautop: no new paragraph)', (bool) preg_match('#<div class="efb [^"]*" id="t1-f"> <small id="t1_-message"#', $h));
$h = efb_a11y_r(efb_fr_field('r1', 'radio', array('required' => '1', 'elementId' => 'radio')), array(), array(array('id_' => 'o1', 'id_op' => 'o1', 'value' => 'A'), array('id_' => 'o2', 'id_op' => 'o2', 'value' => 'B')));
t('E2 a radio group: role="radiogroup", named by its label, aria-required', (bool) preg_match('/id="r1_options"[^>]*role="radiogroup" aria-labelledby="r1_labG" aria-required="true"/', $h), $h);
t('E2 its label points at nothing no more (no for="r1_")', strpos($h, 'for="r1_"') === false && strpos($h, 'id="r1_labG"') !== false);
$h = efb_a11y_r(efb_fr_field('k1', 'checkbox', array('required' => '1', 'elementId' => 'checkbox')), array(), array(array('id_' => 'q1', 'id_op' => 'q1', 'value' => 'A')));
t('E2 a checkbox group: role="group", named by its label, no aria-required on a group', (bool) preg_match('/id="k1_options"[^>]*role="group" aria-labelledby="k1_labG"/', $h) && !preg_match('/id="k1_options"[^>]*aria-required/', $h), $h);
$h = efb_a11y_r(efb_fr_field('m1', 'mobile', array('c_c' => array())));
t('E3 mobile: type="tel" (was the invalid "phone") with autocomplete="tel"', strpos($h, '<input type="tel" autocomplete="tel" class="efb input-efb intlPhone') !== false && strpos($h, 'type="phone"') === false, substr($h, 0, 600));
$h = efb_a11y_r(efb_fr_field('a1', 'textarea', array('placeholder' => 'Say "hi" <now>', 'value' => 'old text')));
t('E4 textarea: no value attribute, the text is its content', !preg_match('/<textarea[^>]* value=/', $h) && strpos($h, '>old text</textarea>') !== false, $h);
t('E4 textarea: the placeholder escaped', strpos($h, 'placeholder="Say &quot;hi&quot; &lt;now&gt;"') !== false);
$h = efb_a11y_r(efb_fr_field('a2', 'textarea', array('value' => '{date:Y}')));
t('E4 textarea with a tag default keeps data-st-value (no value attribute)', strpos($h, 'data-st-value="{date:Y}"') !== false && !preg_match('/<textarea[^>]* value=/', $h), $h);
$h = efb_a11y_r(efb_fr_field('n1', 'text', array('name' => '', 'placeholder' => 'Your city')));
t('E5 no label: named by the placeholder (never aria-label="")', strpos($h, 'aria-label="Your city"') !== false && strpos($h, 'aria-label=""') === false);
$h = efb_a11y_r(efb_fr_field('n2', 'text', array('name' => '', 'placeholder' => '')));
t('E5 no label and no placeholder: no aria-label at all (the <label for> stays)', strpos($h, 'aria-label=') === false);
t('E6 the dead generateSwitchInput_efb() (undefined $pos) is gone', !method_exists('\\Emsfb\\Formbuilder', 'generateSwitchInput_efb'));
t('E7 step legend: hidden, "Step 2 of 4: Contact"', \Emsfb\A11y::step_legend(2, 4, 'Contact') === '<legend class="efb efb-vh">Step 2 of 4: Contact</legend>');
t('E7 a step without a name: "Step 1 of 1", the name\'s colon dropped; a name\'s markup is text', \Emsfb\A11y::step_legend(1, 1, '') === '<legend class="efb efb-vh">Step 1 of 1</legend>' && \Emsfb\A11y::step_legend(1, 2, '<b>Hi</b> & you') === '<legend class="efb efb-vh">Step 1 of 2: Hi &amp; you</legend>');
t('E7 a translated pattern is used', \Emsfb\A11y::step_legend(3, 5, 'تماس', 'مرحلهٔ %1$s از %2$s: %3$s') === '<legend class="efb efb-vh">مرحلهٔ 3 از 5: تماس</legend>');

/* ---------------------------------------------------------------- F: §5.1 */
$cases = array(
	// type => [token on an F16 form, token on an older form]
	'text' => array(null, 'off'), 'firstName' => array('given-name', 'given-name'), 'lastName' => array('family-name', 'family-name'),
	'email' => array('email', 'email'), 'password' => array('current-password', 'current-password'), 'tel' => array('tel', 'tel'), 'url' => array('url', 'url'),
	'number' => array('off', 'off'), 'address_line' => array('street-address', 'street-address'), 'postalcode' => array('postal-code', 'postal-code'), 'date' => array('off', 'off'),
	'textarea' => array(null, null),
);
foreach ($cases as $type => $want) {
	$f = efb_fr_field('x1', $type === 'firstName' || $type === 'lastName' || $type === 'address_line' || $type === 'postalcode' ? $type : $type, array('elementId' => $type));
	$f['type'] = in_array($type, array('firstName', 'lastName', 'address_line', 'postalcode'), true) ? $type : $type;
	$new = $ac(efb_a11y_r($f, $NEW), 'x1');
	$old = $ac(efb_a11y_r($f), 'x1');
	t("F1 {$type}: \"" . var_export($want[0], true) . '" on an F16 form, "' . var_export($want[1], true) . '" on an older one', $new === $want[0] && $old === $want[1], array($new, $old));
}
foreach (array('conturyList' => 'country-name', 'stateProvince' => 'address-level1', 'cityList' => 'address-level2') as $type => $want) {
	$got = $ac(efb_a11y_r(efb_fr_field('s1', $type, array('elementId' => $type))), 's1');
	t("F1 {$type} (a select): {$want}", $got === $want, $got);
}
t('F1 mobile: tel (also in the schema table)', \Emsfb\A11y::autocomplete_for('mobile', array()) === 'tel');
t('F1 prcfld: transaction-amount (schema table)', \Emsfb\A11y::autocomplete_for('prcfld', array()) === 'transaction-amount');
t('F2 a register form\'s password: new-password', $ac(efb_a11y_r(efb_fr_field('p1', 'password'), array('type' => 'register')), 'p1') === 'new-password');
t('F2 a password with a confirmation box: new-password', \Emsfb\A11y::autocomplete_for('password', array('pw_confirm' => '1')) === 'new-password');
t('F3 the field\'s own key wins: date → bday, textarea → street-address, text → section-a shipping postal-code', $ac(efb_a11y_r(efb_fr_field('d1', 'date', array('autocomplete' => 'bday'))), 'd1') === 'bday' && $ac(efb_a11y_r(efb_fr_field('d2', 'textarea', array('autocomplete' => 'street-address'))), 'd2') === 'street-address' && $ac(efb_a11y_r(efb_fr_field('d3', 'text', array('autocomplete' => 'section-a shipping postal-code')), $NEW), 'd3') === 'section-a shipping postal-code');
t('F3 "off" set by the owner on an e-mail', $ac(efb_a11y_r(efb_fr_field('d4', 'email', array('autocomplete' => 'off'))), 'd4') === 'off');
$h = efb_a11y_r(efb_fr_field('d5', 'text', array('autocomplete' => 'evil" onfocus="alert(1)')), $NEW);
t('F4 an unknown / hostile token never reaches the page', strpos($h, 'onfocus') === false && $ac($h, 'd5') === null, $ac($h, 'd5'));
t('F4 token(): allow-list with section- and shipping / billing, lower case, one space', \Emsfb\A11y::token(' Billing  CC-Number ') === 'billing cc-number' && \Emsfb\A11y::token('section-x billing tel') === 'section-x billing tel' && \Emsfb\A11y::token('nickname2') === '' && \Emsfb\A11y::token('section-<x> email') === '');
$h = efb_a11y_r(efb_fr_field('mk', 'tel', array('mask_preset' => 'ir.mobile', 'elementId' => 'tel')), $NEW);
$hk = efb_a11y_r(efb_fr_field('mk', 'tel', array('mask_preset' => 'ir.mobile', 'elementId' => 'tel', 'autocomplete' => 'off')), $NEW);
t('F5 a mask preset gives its token unless the field has its own (F01 + F16)', $ac($h, 'mk') !== null && $ac($hk, 'mk') === 'off', array($ac($h, 'mk'), $ac($hk, 'mk')));

/* ---------------------------------------------------------------- the JS twin */
$js = shell_exec('node -e ' . escapeshellarg('const a = require(' . json_encode(__DIR__ . '/../includes/admin/assets/js/a11y-builder-efb.js') . '); const r0n = {val_timing: "blur"}, r0o = {}; const out = {}; ["text","firstName","lastName","email","password","tel","mobile","url","number","prcfld","textarea","address_line","postalcode","conturyList","stateProvince","cityList","date"].forEach((t) => { out[t] = [a.efbA11yAcFor(t, {}, r0n), a.efbA11yAcFor(t, {}, r0o)]; }); out.reg = a.efbA11yAcFor("password", {}, {type: "register"}); out.own = a.efbA11yAcFor("date", {autocomplete: "bday"}, r0n); out.bad = a.efbA11yAcFor("text", {autocomplete: "evil x"}, r0n); out.clean = [a.efbA11yClean("val_timing", "BLUR"), a.efbA11yClean("val_timing", "x"), a.efbA11yClean("err_summary", true), a.efbA11yClean("warn_unsaved", "0"), a.efbA11yClean("autocomplete", " Billing  CC-Number ")]; console.log(JSON.stringify(out));'));
$jo = json_decode((string) $js, true);
$php = array();
foreach (array('text', 'firstName', 'lastName', 'email', 'password', 'tel', 'mobile', 'url', 'number', 'prcfld', 'textarea', 'address_line', 'postalcode', 'conturyList', 'stateProvince', 'cityList', 'date') as $tp) $php[$tp] = array(\Emsfb\A11y::autocomplete_for($tp, array(), array('val_timing' => 'blur')), \Emsfb\A11y::autocomplete_for($tp, array(), array()));
$php['reg'] = \Emsfb\A11y::autocomplete_for('password', array(), array('type' => 'register'));
$php['own'] = \Emsfb\A11y::autocomplete_for('date', array('autocomplete' => 'bday'), array('val_timing' => 'blur'));
$php['bad'] = \Emsfb\A11y::autocomplete_for('text', array('autocomplete' => 'evil x'), array('val_timing' => 'blur'));
$php['clean'] = array(\Emsfb\A11y::sanitize_key('val_timing', 'BLUR'), \Emsfb\A11y::sanitize_key('val_timing', 'x'), \Emsfb\A11y::sanitize_key('err_summary', true), \Emsfb\A11y::sanitize_key('warn_unsaved', '0'), \Emsfb\A11y::sanitize_key('autocomplete', ' Billing  CC-Number '));
t('F6 the builder\'s twin (a11y-builder-efb.js) gives the same tokens and keeps the same values as the server', is_array($jo) && $jo == $php, array('js' => $jo, 'php' => $php));

echo "\nRESULTS: {$passed} passed, {$failed} failed\n";
exit($failed ? 1 : 0);
