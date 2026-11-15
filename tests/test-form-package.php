<?php
/**
 * F15-D: the form package contract (includes/class-Emsfb-form-package.php) without WordPress. The save path's
 * modules are stand-ins here (the real path: tests/test-form-package-http.php on the e2e site):
 *  - the envelope: schema 1, a newer schema refused (package_newer), no schema / no form / no rows refused;
 *  - malicious input: > 8 MB, nesting deeper than 64, a PHP object string anywhere (dropped), rows without a type,
 *    unknown types (and their options) dropped, c_c / logic_rules … that are not arrays removed (no PHP warning),
 *    a settings row that is not one refused;
 *  - row 0: secrets, autofill_id, st_uses never kept (SMS / Telegram texts kept in the JSON while off stay); `email`
 *    only with with_emails; steps counted again;
 *  - ids: kept; an invalid id gets a new one and every reference follows through efb_form_package_remap_ids
 *    (parent, dataId, group, email_to, lim_email_field, logic rules, calc formulas {field:id}/{id.part}, smart tags
 *    {field:id|fallback}) while free text (labels) keeps the word; a second use of an id → only that row and its
 *    options renamed; core's handlers are hooks (a module can add its own);
 *  - extras (P23): export filter, secrets removed from every block of an export (kept on a duplicate), a block of an
 *    inactive add-on dropped with a notice, a block nobody applied reported, the import filter gets the id map;
 *  - api() / discovery, the registry entry.
 *
 * Run: php tests/test-form-package.php
 */
define('ABSPATH', __DIR__ . '/');
$passed = 0; $failed = 0;
function t($label, $actual, $expected) {
	global $passed, $failed;
	if ($actual === $expected) { $passed++; echo "[PASS] {$label}\n"; return; }
	$failed++; echo "[FAIL] {$label}\n      expected " . json_encode($expected, JSON_UNESCAPED_UNICODE) . "\n      actual   " . json_encode($actual, JSON_UNESCAPED_UNICODE) . "\n";
}
/* A tiny filter registry (WordPress' semantics: priority order, the first argument passed along). */
$GLOBALS['efb_f'] = array();
function add_filter($h, $cb, $p = 10, $n = 1) { $GLOBALS['efb_f'][$h][$p][] = array($cb, $n); return true; }
function apply_filters($h, $v) {
	$args = func_get_args();
	if (empty($GLOBALS['efb_f'][$h])) return $v;
	ksort($GLOBALS['efb_f'][$h]);
	foreach ($GLOBALS['efb_f'][$h] as $list) foreach ($list as $c) { $args[1] = $v; $v = call_user_func_array($c[0], array_slice($args, 1, max(1, $c[1]))); }
	return $v;
}
function add_action() { return true; }
function do_action() { $GLOBALS['efb_actions'][] = func_get_args(); }
function sanitize_text_field($v) { return is_scalar($v) ? trim(strip_tags((string) $v)) : ''; }
function sanitize_textarea_field($v) { return is_scalar($v) ? trim(strip_tags((string) $v)) : ''; }
function wp_json_encode($v, $f = 0) { return json_encode($v, $f); }
function get_option($k, $d = false) { return isset($GLOBALS['efb_opt'][$k]) ? $GLOBALS['efb_opt'][$k] : $d; }
function get_setting_Emsfb($m = 'decoded') { return (object) $GLOBALS['efb_settings']; }
function home_url() { return 'https://site.test'; }
$GLOBALS['efb_settings'] = array('AdnSS' => 1, 'AdnGoS' => 0);
/* The sanitizer: tags out of every scalar, arrays kept (stand-in; the real one runs in the HTTP test). */
class EfbPkgFn {
	public $calls = 0;
	public function text_efb($k) { return array(); }
	public function sanitize_obj_msg_efb($rows) {
		$this->calls++;
		$w = function ($v) use (&$w) { if (is_array($v)) { foreach ($v as $k => $x) $v[$k] = $w($x); return $v; } return is_string($v) ? strip_tags($v) : $v; };
		return $w($rows);
	}
}
$GLOBALS['fn'] = new EfbPkgFn();
function get_efbFunction() { return $GLOBALS['fn']; }
require __DIR__ . '/../includes/class-Emsfb-form-package.php';
require __DIR__ . '/../includes/form-package-functions-efb.php';
use Emsfb\Form_Package as P;
P::register();

$r0 = array('type' => 'form', 'steps' => 3, 'formName' => 'Pkg', 'email' => 'owner@example.test', 'EfbVersion' => 2, 'email_to' => 'em1',
	'telegram_bot_token' => '123:SECRET', 'telegram_admin_chat_ids' => '42', 'sms_admins_phone_no' => '+100', 'sms_msg_new_noti' => 'New', 'autofill_id' => '17', 'st_uses' => 'field',
	'lim_email_field' => 'bad id', 'thank_you_message' => array('thankYou' => 'Thanks {field:bad id}', 'done' => 'Done'),
	'logic_rules' => array(array('id' => 'r1', 'conditions' => array('items' => array(array('field' => 'bad id', 'op' => 'eq', 'value' => 'x'))), 'actions' => array(array('type' => 'show', 'target' => 'em1')))));
$st = array('type' => 'step', 'id_' => '1', 'step' => 1, 'name' => 'S');
$pkg = function ($rows, $extra = array()) { return array_merge(array('schema' => 1, 'generator' => 'easy-form-builder', 'plugin_version' => '4.3.1', 'form' => array('form_name' => 'Pkg', 'form_type' => 'form', 'form_structer' => $rows), 'extras' => array()), $extra); };
$rows = array($r0, $st,
	array('id_' => 'bad id', 'dataId' => 'bad id-id', 'type' => 'number', 'name' => 'bad id label', 'step' => 1),
	array('id_' => 'em1', 'dataId' => 'em1-id', 'type' => 'email', 'name' => 'E-mail', 'step' => 1),
	array('id_' => 'c1', 'dataId' => 'c1-id', 'type' => 'calc', 'formula' => '{field:bad id}*2+{bad id.price}', 'step' => 1),
	array('id_' => 'h1', 'dataId' => 'h1-id', 'type' => 'hiddenField', 'value' => '{field:bad id|0} and {field:bad idx}', 'step' => 1),
	array('id_' => 'rad', 'dataId' => 'rad-id', 'type' => 'radio', 'name' => 'R', 'value' => 'o1', 'step' => 1),
	array('id_' => 'o1', 'type' => 'option', 'parent' => 'rad', 'value' => 'One', 'id_op' => 'o1'),
	array('id_' => 'rad', 'dataId' => 'rad-id', 'type' => 'radio', 'name' => 'R2', 'step' => 1),
	array('id_' => 'o2', 'type' => 'option', 'parent' => 'rad', 'value' => 'Two', 'id_op' => 'o2'),
	array('id_' => 'g1', 'dataId' => 'g1-id', 'type' => 'repeater', 'step' => 1),
	array('id_' => 'kid', 'dataId' => 'kid-id', 'type' => 'text', 'group' => 'g1', 'c_c' => 'x', 'autofill_conditions' => 'oops', 'step' => 1, 'registered_count' => '9'),
	array('id_' => 'zz', 'type' => 'evil_widget', 'step' => 1),
	array('id_' => 'zo', 'type' => 'option', 'parent' => 'zz', 'value' => 'gone'),
	array('name' => 'no type'),
	'not a row',
	array('type' => 'step', 'id_' => '2', 'step' => 2, 'name' => 'S2'),
	array('id_' => 'x<script>', 'dataId' => 'x<script>-id', 'type' => 'text', 'name' => '<script>alert(1)</script>Label', 'step' => 2, 'obj' => 'O:8:"stdClass":0:{}'),
);
$v = P::validate($pkg($rows));
t('a package with everything wrong but fixable is valid after cleaning', array($v['ok'], $v['errors']), array(true, array()));
$out = $v['form']['form_structer'];
$byType = function ($t) use ($out) { return array_values(array_filter($out, function ($r) use ($t) { return $r['type'] === $t; })); };
$codes = array_column($v['dropped'], 'code');
sort($codes);
t('dropped: the unknown type, its option, two rows without a type, c_c and autofill_conditions that are not arrays, a PHP object string', $codes, array('not_an_array', 'not_an_array', 'orphan_option', 'php_object', 'row_without_type', 'row_without_type', 'unknown_type'));
t('no row of an unknown type is kept', count($byType('evil_widget')) + count(array_filter($out, function ($r) { return isset($r['parent']) && $r['parent'] === 'zz'; })), 0);
t('row 0: secrets, autofill_id and st_uses gone; an SMS text the JSON kept stays; email kept on import; steps counted (2)', array(isset($out[0]['telegram_bot_token']), isset($out[0]['telegram_admin_chat_ids']), isset($out[0]['sms_admins_phone_no']), $out[0]['sms_msg_new_noti'] ?? null, $out[0]['autofill_id'], isset($out[0]['st_uses']), $out[0]['email'], $out[0]['steps']), array(false, false, false, 'New', '', false, 'owner@example.test', 2));
$new = $v['id_map']['bad id'];
t('an invalid id gets a new 9-character id in the map', (bool) preg_match('/^[a-z0-9]{9}$/', $new), true);
$num = $byType('number')[0];
t('the field itself: id_ and dataId; its label keeps the words "bad id"', array($num['id_'], $num['dataId'], $num['name']), array($new, $new . '-id', 'bad id label'));
t('calc formula: {field:id} and {id.part} follow', $byType('calc')[0]['formula'], '{field:' . $new . '}*2+{' . $new . '.price}');
t('smart tag {field:id|fallback} follows; a longer id that only starts the same does not', $byType('hiddenField')[0]['value'], '{field:' . $new . '|0} and {field:bad idx}');
t('row 0: lim_email_field (an id) follows; the thank-you text tag follows', array($out[0]['lim_email_field'], $out[0]['thank_you_message']['thankYou']), array($new, 'Thanks {field:' . $new . '}'));
t('logic rules: the condition\'s field follows; the other target stays', array($out[0]['logic_rules'][0]['conditions']['items'][0]['field'], $out[0]['logic_rules'][0]['actions'][0]['target']), array($new, 'em1'));
$rad = $byType('radio');
$opts = $byType('option');
t('an id used twice: the second field and its own option get a new id; the first and its option are untouched', array($rad[0]['id_'], $rad[1]['id_'] !== 'rad' && $rad[1]['dataId'] === $rad[1]['id_'] . '-id', $opts[0]['parent'], $opts[1]['parent'] === $rad[1]['id_']), array('rad', true, 'rad', true));
t('a repeater child keeps its group; registered_count reset', array($byType('text')[0]['group'], $byType('text')[0]['registered_count']), array('g1', '0'));
$x = array_values(array_filter($out, function ($r) { return isset($r['step']) && (int) $r['step'] === 2 && $r['type'] === 'text'; }))[0];
t('the script id is replaced; the script tag is stripped from the label by the save path', array((bool) preg_match('/^[a-z0-9]{9}$/', $x['id_']), $x['name'], isset($x['obj'])), array(true, 'alert(1)Label', false));
t('notices: an id used twice, ids remapped, logic rules without the Logic add-on on this site', array_column($v['notices'], 'code'), array('duplicate_ids', 'ids_remapped', 'logic_addon_inactive'));
t('the save path (sanitize_obj_msg_efb) ran', $GLOBALS['fn']->calls > 0, true);

/* A module's own handler on the remap hook (e.g. an add-on that keeps ids under its own key). */
add_filter('efb_form_package_remap_ids', function ($rows, $map) { foreach ($rows as $i => $r) if (isset($r['my_ref']) && strpos($r['my_ref'], 'mod:') === 0 && isset($map[substr($r['my_ref'], 4)])) $rows[$i]['my_ref'] = 'mod:' . $map[substr($r['my_ref'], 4)]; return $rows; }, 20, 2);
$rr = array($r0, $st, array('id_' => 'a b', 'type' => 'text'), array('id_' => 'm', 'type' => 'text', 'my_ref' => 'mod:a b'));
$vv = P::validate($pkg($rr));
t('a module\'s handler on efb_form_package_remap_ids runs after core\'s, with the map', $vv['form']['form_structer'][3]['my_ref'], 'mod:' . $vv['id_map']['a b']);
$clean = P::validate($pkg(array($r0, $st, array('id_' => 'ok1', 'type' => 'text', 'name' => 'A'))));
t('valid ids are kept as they are (no map)', array($clean['id_map'], $clean['form']['form_structer'][2]['id_']), array(array(), 'ok1'));

/* Refusals */
$code = function ($p) { $r = P::validate($p); return $r['ok'] ? 'ok' : $r['errors'][0]['code']; };
t('schema 2: package_newer', $code($pkg($rows, array('schema' => 2))), 'package_newer');
t('no schema / schema "x": package_invalid', array($code(array('form' => array('form_structer' => $rows))), $code($pkg($rows, array('schema' => 'x')))), array('package_invalid', 'package_invalid'));
t('no form, rows not a list, rows an object: refused', array($code(array('schema' => 1)), $code($pkg('nope')), $code($pkg(array('a' => $r0, 'b' => $st)))), array('package_invalid', 'package_no_rows', 'package_no_rows'));
t('a settings row of another type: refused; a step later but not first: refused', array($code($pkg(array(array('type' => 'text'), $st))), $code($pkg(array($r0, array('id_' => 'a', 'type' => 'text'), $st)))), array('package_bad_settings_row', 'package_no_step'));
$ns = P::validate($pkg(array($r0, array('id_' => 'a', 'type' => 'text', 'name' => 'A'))));
t('a form without any step row (saved by an older insertItems() on a blank form) gets step 1 (notice step_added)', array($ns['ok'], $ns['form']['form_structer'][1]['type'], $ns['form']['form_structer'][2]['id_'], in_array('step_added', array_column($ns['notices'], 'code'), true)), array(true, 'step', 'a', true));
t('not JSON: package_invalid_json', $code('{"schema":1,'), 'package_invalid_json');
$deep = '1'; for ($i = 0; $i < 80; $i++) $deep = '[' . $deep . ']';
t('a JSON string nested deeper than 64: package_too_deep', $code('{"schema":1,"form":{"form_structer":' . $deep . '}}'), 'package_too_deep');
$deepArr = 1; for ($i = 0; $i < 80; $i++) $deepArr = array($deepArr);
t('an array nested deeper than 64: package_too_deep', $code($pkg(array($r0, $st, array('id_' => 'd', 'type' => 'text', 'x' => $deepArr)))), 'package_too_deep');
t('more than 8 MB: package_too_large (before decoding)', $code(str_repeat(' ', P::MAX_BYTES + 1)), 'package_too_large');
t('more than 5000 rows: refused', $code($pkg(array_merge(array($r0, $st), array_fill(0, 5001, array('id_' => 'r', 'type' => 'text'))))), 'package_too_many_rows');
t('a JSON string package works like an array', P::validate(json_encode($pkg(array($r0, $st, array('id_' => 'a', 'type' => 'text')))))['ok'], true);
t('php object strings are recognised (O:… and C:…), plain text is not', array(P::is_php_object_string('O:8:"stdClass":0:{}'), P::is_php_object_string('a:1:{i:0;O:3:"Foo":0:{}}'), P::is_php_object_string('Order: 5:"x"')), array(true, true, false));

/* Export cleaning */
$c = P::clean_row0(array($r0, $st, array('id_' => 'a', 'type' => 'option', 'registered_count' => '4')), false);
t('export: row 0 without secrets / autofill_id; email emptied without with_emails; registered_count 0', array(isset($c[0]['telegram_bot_token']), $c[0]['email'], $c[0]['autofill_id'], $c[2]['registered_count']), array(false, '', '', '0'));
t('export with with_emails keeps the address', P::clean_row0(array($r0, $st), true)[0]['email'], 'owner@example.test');

/* Extras (P23) */
add_filter('efb_form_export_extras', function ($x, $fid, $ctx) {
	$x['googlesheet'] = array('v' => 1, 'addon' => 'AdnGoS', 'data' => array('column_map' => array(array('source' => 'em1', 'header' => 'Mail')), 'spreadsheet_id' => 'abc', 'connection_id' => 'c1', 'api_key' => 'K', 'nested' => array('client_secret' => 'S', 'ok' => 1)));
	$x['bad name!'] = array('data' => 1);
	$x['portal'] = array('addon' => 'AdnPRT', 'data' => array('enabled' => 1));
	return $x;
}, 10, 3);
$ex = P::export_extras(5, array('context' => 'export'));
t('export extras: blocks {v, addon, data}; a bad block name ignored', array_keys($ex), array('googlesheet', 'portal'));
t('export extras: secrets removed at any depth (api_key, client_secret)', array(isset($ex['googlesheet']['data']['api_key']), isset($ex['googlesheet']['data']['nested']['client_secret']), $ex['googlesheet']['data']['nested']['ok']), array(false, false, 1));
$dup = P::export_extras(5, array('context' => 'duplicate'));
t('on a duplicate (never leaves the site) the block is whole', $dup['googlesheet']['data']['api_key'], 'K');
$seen = null;
add_filter('efb_form_import_extras', function ($rep, $fid, $blocks, $ctx) use (&$seen) {
	$seen = array('fid' => $fid, 'blocks' => array_keys($blocks), 'map' => $ctx['id_map'], 'context' => $ctx['context']);
	if (isset($blocks['portal'])) $rep['applied'][] = 'portal';
	return $rep;
}, 10, 4);
$GLOBALS['efb_settings']['AdnPRT'] = 1;
$rep = P::import_extras(9, $ex + array('weird' => 'x', 'helper' => array('addon' => 'AdnHLP', 'data' => array())), array('context' => 'import', 'id_map' => array('a b' => 'n1')));
$nc = array_map(function ($n) { return $n['code'] . ':' . $n['block']; }, $rep['notices']);
sort($nc);
t('import extras: an inactive add-on\'s block dropped with a notice (googlesheet: AdnGoS off; helper: not installed); an invalid block reported', $nc, array('extras_addon_inactive:googlesheet', 'extras_addon_inactive:helper', 'extras_invalid:weird'));
t('import extras: the filter gets only the active blocks, the form id, the id map, the context; what it applied is reported', array($seen, $rep['applied']), array(array('fid' => 9, 'blocks' => array('portal'), 'map' => array('a b' => 'n1'), 'context' => 'import'), array('portal')));
$GLOBALS['efb_settings']['AdnGoS'] = 1;
$rep = P::import_extras(9, $ex, array('context' => 'import'));
t('import extras: an active add-on whose block nobody applied is reported (extras_not_applied)', array_map(function ($n) { return $n['code'] . ':' . $n['block']; }, $rep['notices']), array('extras_not_applied:googlesheet'));
t('import extras: nothing / not an array → nothing to do', array(P::import_extras(9, null), P::import_extras(9, array())), array(array('applied' => array(), 'notices' => array()), array('applied' => array(), 'notices' => array())));
add_filter('efb_form_package_addon_active', function ($on, $code) { return $code === 'AdnGoS' ? false : $on; }, 10, 2);
t('efb_form_package_addon_active can say an add-on is off', P::addon_active('AdnGoS'), false);

/* Discovery and registry */
$api = efb_form_package_api();
t('efb_form_package_api(): version, schema, the functions, the hooks and limits', array($api['version'], $api['schema'], $api['functions'], in_array('efb_form_package_remap_ids', $api['filters'], true), $api['limits']['depth']), array(1, 1, array('export', 'validate', 'import', 'export_extras', 'import_extras'), true, 64));
$caps = P::capabilities(array());
t('registry: form_package (free, no UI in core)', array($caps['form_package']['plan'], $caps['form_package']['schema'], strpos($caps['form_package']['ui'], 'Data Tools') !== false), array('free', 1, true));

echo "\nRESULTS: {$passed} passed, {$failed} failed\n";
exit($failed ? 1 : 0);
