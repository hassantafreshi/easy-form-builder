<?php
/**
 * F09-A: the repeater group's data model and its save round trip, as the builder sends it (JSON.stringify ->
 * transport -> wp_unslash -> sanitize_obj_msg_efb -> stored column -> readers): every group key and a child's
 * `group` survive ({n}, {field:abc}, numbers); refused values are dropped; run-time keys never stored;
 * Repeater::children_map() ignores orphans, nested groups and types a row may not hold; the array order is
 * kept; normalize_rows() puts a child in its group's step and right after it; an old form comes back unchanged;
 * the list of child types is the same in PHP, val-efb.js and repeater-efb.js.
 *
 * Run: php tests/test-repeater-model.php
 */
define('ABSPATH', __DIR__ . '/');
define('EMSFB_PLUGIN_VERSION', '4.3.1-test');
$EFB = __DIR__ . '/..';

/* WordPress-faithful stubs for the sanitiser path (arrays become '', as WordPress does). */
function sanitize_text_field($v) {
	if (is_array($v) || is_object($v)) return '';
	$s = strip_tags((string) $v);
	$s = preg_replace('/%[a-f0-9]{2}/i', '', $s);
	$s = preg_replace('/[\r\n\t ]+/', ' ', $s);
	return trim($s);
}
function wp_check_invalid_utf8($s) { return preg_match('//u', (string) $s) ? (string) $s : ''; }
function sanitize_email($v) { return filter_var((string) $v, FILTER_SANITIZE_EMAIL); }
function sanitize_url($v) { return filter_var((string) $v, FILTER_SANITIZE_URL); }
function is_email($v) { return filter_var((string) $v, FILTER_VALIDATE_EMAIL) !== false; }
function esc_url_raw($v) { return filter_var((string) $v, FILTER_SANITIZE_URL); }
function wp_kses_post($v) { return strip_tags((string) $v, '<b><strong><em><i><br><p><a>'); }
function esc_html__($s, $d = null) { return $s; }
function esc_html($s) { return htmlspecialchars((string) $s, ENT_QUOTES); }
function __($s, $d = null) { return $s; }
$GLOBALS['efb_filters'] = array();
function apply_filters($h, $v) { return isset($GLOBALS['efb_filters'][$h]) ? call_user_func($GLOBALS['efb_filters'][$h], $v) : $v; }
function add_filter() { return true; }
function add_action() { return true; }
function do_action() {}
function wp_cache_get() { return false; }
function wp_cache_set() { return true; }
function wp_cache_delete() { return true; }
function get_option($k, $d = false) { return $d; }
function update_option() { return true; }
function absint($v) { return abs(intval($v)); }
function wp_json_encode($v) { return json_encode($v); }
function wp_unslash($v) { return is_string($v) ? stripslashes($v) : $v; }
function wp_slash($v) { return is_string($v) ? addslashes($v) : $v; }

require $EFB . '/includes/json-efb.php';
require $EFB . '/includes/class-Emsfb-masks.php';
require $EFB . '/includes/class-Emsfb-field-rules.php';
require $EFB . '/includes/class-Emsfb-choices.php';
require (getenv('EFB_CALC_DIR') ?: $EFB . '/vendor/calc') . '/class-Emsfb-calc.php';
require $EFB . '/includes/class-Emsfb-repeater.php';
require $EFB . '/includes/functions.php';

$pass = 0;
$fail = 0;
function t($label, $actual, $expected) {
	global $pass, $fail;
	if ($actual === $expected) { $pass++; echo "[PASS] $label\n"; return; }
	$fail++;
	echo "[FAIL] $label\n      expected " . json_encode($expected, JSON_UNESCAPED_UNICODE) . "\n      actual   " . json_encode($actual, JSON_UNESCAPED_UNICODE) . "\n";
}

$fn = (new ReflectionClass('efbFunction'))->newInstanceWithoutConstructor();
function builder_payload($form) {
	$json = json_encode($form, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
	return str_replace('\\"', '"', $json);
}
function save_and_read($fn, $payload) {
	$post = wp_unslash(wp_slash($payload));
	$valp = json_decode(emsfb_json_normalize_efb($post), true);
	if (!is_array($valp)) return null;
	$valp = $fn->sanitize_obj_msg_efb($valp);
	$stored = str_replace('"', '\"', json_encode($valp, JSON_UNESCAPED_UNICODE));
	return json_decode(emsfb_json_normalize_efb($stored), true);
}
function form_with($rows) {
	return array_merge(array(
		array('type' => 'form', 'steps' => '2', 'formName' => 'Trip', 'email' => ''),
		array('type' => 'step', 'id_' => '1', 'dataId' => '1-id', 'name' => 'Step 1', 'step' => '1'),
	), $rows);
}
function fld($id, $extra = array()) {
	return array_merge(array('id_' => $id, 'dataId' => $id . '-id', 'type' => 'text', 'elementId' => 'text', 'name' => 'F ' . $id, 'step' => '1', 'amount' => '2', 'required' => '0', 'placeholder' => '', 'value' => ''), $extra);
}
function opt($id, $parent, $label) {
	return array('id_' => $id, 'dataId' => $id . '-id', 'parent' => $parent, 'type' => 'option', 'value' => $label, 'id_op' => $id, 'step' => '1', 'amount' => '3');
}
function grp($id, $extra = array()) {
	return fld($id, array_merge(array('type' => 'repeater', 'elementId' => 'repeater', 'name' => 'Travellers', 'min_rows' => '1', 'max_rows' => '5', 'default_rows' => '2',
		'rows_from' => '', 'row_label' => 'Traveller {n}: {field:fname}', 'collapsible' => '1', 'collapse_prev' => '1', 'rpt_layout' => 'inline', 'btn_add_text' => 'Add a traveller', 'rmv_confirm' => '0'), $extra));
}

/* 1. Every key survives a save, byte for byte. */
$form = form_with(array(
	fld('cnt', array('type' => 'number')),
	grp('g1', array('rows_from' => 'cnt')),
	fld('fname', array('group' => 'g1', 'amount' => '3')),
	fld('meal', array('type' => 'checkbox', 'group' => 'g1', 'amount' => '4')),
	opt('m1', 'meal', 'Vegan'), opt('m2', 'meal', 'Halal'),
	fld('after', array('amount' => '5')),
));
$out = save_and_read($fn, builder_payload($form));
t('the form decodes after the round trip', is_array($out), true);
foreach (array('min_rows' => '1', 'max_rows' => '5', 'default_rows' => '2', 'rows_from' => 'cnt', 'row_label' => 'Traveller {n}: {field:fname}', 'collapsible' => '1', 'collapse_prev' => '1', 'rpt_layout' => 'inline', 'btn_add_text' => 'Add a traveller', 'rmv_confirm' => '0') as $k => $v) {
	t("group key $k = " . json_encode($v, JSON_UNESCAPED_UNICODE), $out[3][$k] ?? '(missing)', $v);
}
t('a child keeps group', array($out[4]['group'] ?? '', $out[5]['group'] ?? ''), array('g1', 'g1'));
t('options keep parent and get no group', array($out[6]['parent'], array_key_exists('group', $out[6])), array('meal', false));
t('the array order is kept', array_map(function ($r) { return $r['id_'] ?? $r['type']; }, $out), array('form', '1', 'cnt', 'g1', 'fname', 'meal', 'm1', 'm2', 'after'));
t('a second save changes nothing', save_and_read($fn, builder_payload($out)), $out);
$fa = save_and_read($fn, builder_payload(form_with(array(grp('g1', array('max_rows' => '۱۲', 'row_label' => 'مسافر {n}'))))));
t('Persian digits in a limit are read; a Persian title is kept', array($fa[2]['max_rows'], $fa[2]['row_label']), array('12', 'مسافر {n}'));

/* 2. Refused values. */
$bad = save_and_read($fn, builder_payload(form_with(array(
	grp('g1', array('max_rows' => 'ten', 'min_rows' => '-1', 'default_rows' => '1e3', 'rows_from' => 'a b', 'rpt_layout' => 'table', 'collapsible' => 'yes', 'row_label' => '<img src=x onerror=1>Row {n}', 'rpt_base' => 'x', 'rpt_n' => '3')),
	fld('c', array('group' => 'g1', 'rpt_group' => 'g1', 'rpt_ri' => '0')),
))));
t('a limit that is not a number falls back to the default (max 10)', $bad[2]['max_rows'], '10');
t('min -1 → default 1', $bad[2]['min_rows'], '1');
t('default 1e3 → clamped to [min,max]', $bad[2]['default_rows'], '1');
t('rows_from with a space is dropped', $bad[2]['rows_from'] ?? '(missing)', '(missing)');
t('an unknown layout → blocks; collapsible "yes" → 0', array($bad[2]['rpt_layout'], $bad[2]['collapsible']), array('blocks', '0'));
t('markup in the row title is removed, {n} kept', $bad[2]['row_label'], 'Row {n}');
t('run-time keys are never stored', array(array_key_exists('rpt_base', $bad[2]), array_key_exists('rpt_n', $bad[2]), array_key_exists('rpt_group', $bad[3]), array_key_exists('rpt_ri', $bad[3])), array(false, false, false, false));
$cap = save_and_read($fn, builder_payload(form_with(array(grp('g1', array('max_rows' => '900', 'min_rows' => '7', 'default_rows' => '9'))))));
t('max over the cap of 50 → 50; default kept in range', array($cap[2]['max_rows'], $cap[2]['min_rows'], $cap[2]['default_rows']), array('50', '7', '9'));
$inv = save_and_read($fn, builder_payload(form_with(array(grp('g1', array('max_rows' => '3', 'min_rows' => '5', 'default_rows' => '0'))))));
t('min above max → min = max; default raised to min', array($inv[2]['max_rows'], $inv[2]['min_rows'], $inv[2]['default_rows']), array('3', '3', '3'));

/* 3. Membership: orphans, nesting, types a row may not hold, rows_from rules, steps, order. */
$m = save_and_read($fn, builder_payload(form_with(array(
	fld('lone', array('group' => 'nope')),
	grp('g1', array('rows_from' => 'later', 'step' => '1')),
	fld('a', array('group' => 'g1', 'step' => '2')),
	fld('pw', array('type' => 'password', 'group' => 'g1')),
	grp('g2', array('group' => 'g1', 'rows_from' => 'a')),
	fld('later', array('type' => 'number')),
	fld('b', array('group' => 'g1')),
))));
$by = array();
foreach ($m as $r) if (isset($r['id_'])) $by[$r['id_']] = $r;
t('an orphan (group names nothing) loses group', array_key_exists('group', $by['lone']), false);
t('a password inside a group loses group (not allowed in a row)', array_key_exists('group', $by['pw']), false);
t('a group inside a group loses group (no nesting)', array_key_exists('group', $by['g2']), false);
t('rows_from on a field after the group is dropped', $by['g1']['rows_from'] ?? '', '');
t('rows_from on a child of a group is dropped', $by['g2']['rows_from'] ?? '', '');
t('a child in another step is moved to its group\'s step', $by['a']['step'], '1');
t('children are moved right after their group', array_map(function ($r) { return $r['id_'] ?? $r['type']; }, $m), array('form', '1', 'lone', 'g1', 'a', 'b', 'pw', 'g2', 'later'));
$map = \Emsfb\Repeater::children_map($m);
t('children_map: g1 → a, b; g2 has none', array_map(function ($idx) use ($m) { return array_map(function ($i) use ($m) { return $m[$i]['id_']; }, $idx); }, $map), array('g1' => array('a', 'b'), 'g2' => array()));
t('children_map works on the render path (stdClass rows)', array_keys(\Emsfb\Repeater::child_groups(json_decode(json_encode($m)))), array('a', 'b'));
$GLOBALS['efb_filters']['efb_repeater_children_cap'] = function () { return 1; };
t('the children cap (filter): extra children are ordinary fields', \Emsfb\Repeater::child_groups($m), array('a' => 'g1'));
unset($GLOBALS['efb_filters']['efb_repeater_children_cap']);
$GLOBALS['efb_filters']['efb_repeater_child_types'] = function ($t) { return array_values(array_diff($t, array('textarea'))); };
t('efb_repeater_child_types filters the allowed types', in_array('textarea', \Emsfb\Repeater::child_types(), true), false);
unset($GLOBALS['efb_filters']['efb_repeater_child_types']);

/* 4. Limits as the page and the server read them. */
t('limits: required group → min at least 1', \Emsfb\Repeater::limits(array('min_rows' => '0', 'max_rows' => '4', 'default_rows' => '0', 'required' => '1')), array('min' => 1, 'max' => 4, 'default' => 1));
t('limits: optional group may start empty', \Emsfb\Repeater::limits(array('min_rows' => '0', 'max_rows' => '4', 'default_rows' => '0')), array('min' => 0, 'max' => 4, 'default' => 0));
t('limits without repeating rows (plan): one row', \Emsfb\Repeater::limits(array('min_rows' => '2', 'max_rows' => '8', 'default_rows' => '3'), false), array('min' => 1, 'max' => 1, 'default' => 1));
t('limits: missing keys = defaults 1 / 10 / 1', \Emsfb\Repeater::limits(array()), array('min' => 1, 'max' => 10, 'default' => 1));

/* 5. Old forms come back unchanged. */
$old = form_with(array(fld('n', array('type' => 'number', 'milen' => '1')), fld('r', array('type' => 'radio')), opt('o1', 'r', 'A'), fld('t', array('type' => 'textarea'))));
t('an old form comes back unchanged', save_and_read($fn, builder_payload($old)), json_decode(json_encode($old), true));
t('normalize_rows leaves a form without groups as it is (same array)', \Emsfb\Repeater::normalize_rows($old), $old);

/* 6. One list of child types in PHP, the builder and the page. */
$val = file_get_contents($EFB . '/includes/admin/assets/js/val-efb.js');
$rpt = file_get_contents($EFB . '/public/assets/js/repeater-efb.js');
$js_list = function ($src, $name) {
	if (!preg_match('/' . preg_quote($name, '/') . '\s*=\s*\[([^\]]*)\]/', $src, $m)) return null;
	preg_match_all("/'([A-Za-z0-9_]+)'/", $m[1], $mm);
	return $mm[1];
};
t('val-efb.js EFB_REPEATER_CHILD_TYPES = Repeater::CHILD_TYPES', $js_list($val, 'EFB_REPEATER_CHILD_TYPES'), \Emsfb\Repeater::CHILD_TYPES);
t('repeater-efb.js CHILD_TYPES = Repeater::CHILD_TYPES', $js_list($rpt, 'var CHILD_TYPES'), \Emsfb\Repeater::CHILD_TYPES);
t('no type of the forbidden list is allowed', array_values(array_intersect(\Emsfb\Repeater::CHILD_TYPES, array('repeater', 'password', 'dadfile', 'esign', 'maps', 'pdate', 'ardate', 'conturyList', 'stateProvince', 'cityList', 'payRadio', 'payCheckbox', 'paySelect', 'payMultiselect', 'prcfld', 'ttlprc', 'stripe', 'paypal', 'persiaPay', 'table_matrix', 'chlCheckBox', 'imgRadio', 'trmCheckbox', 'step'))), array());

echo "\nRESULTS: $pass passed, $fail failed\n";
exit($fail ? 1 : 0);
