<?php
/**
 * Server-only form settings must not reach visitors.
 *
 * Covers includes/public-form-filter-efb.php (the deny-list applied to index 0
 * of form_structer) and checks that the public render path actually calls it
 * at the point where form JSON is localized into the page.
 *
 * Run: php tests/test-public-form-structure-filter.php
 */

define('ABSPATH', __DIR__ . '/');

/* WordPress stubs: a filter registry just big enough for the add-on hook. */
$GLOBALS['efb_test_filters'] = array();
function add_filter($hook, $cb) { $GLOBALS['efb_test_filters'][$hook][] = $cb; }
function apply_filters($hook, $value) {
	foreach ($GLOBALS['efb_test_filters'][$hook] ?? array() as $cb) $value = $cb($value);
	return $value;
}

require __DIR__ . '/../includes/json-efb.php';
require __DIR__ . '/../includes/public-form-filter-efb.php';

$pass = 0;
$fail = 0;
function check_pff($label, $condition) {
	global $pass, $fail;
	if ($condition) { $pass++; echo "[PASS] $label\n"; return; }
	$fail++;
	echo "[FAIL] $label\n";
}

/* Every server-only key observed in stored forms (admin save, SMS/Telegram
 * add-ons, conditional notification/webhook/confirmation rules). */
$private = array(
	'email' => 'owner@corp.test',
	'email_to' => 'fem',
	'email_sub' => 'Internal subject',
	'email_temp' => '<p>internal template</p>',
	'email_noti_type' => 'msg',
	'email_send_type' => 'smtp',
	'sendEmail' => true,
	'rePage' => 'https://partner.test/secret-thanks',
	'smsnoti' => 1,
	'sms_admins_phone_no' => '+15550000001',
	'sms_msg_new_noti' => 'sms new',
	'sms_msg_recived_usr' => 'sms usr',
	'sms_msg_recived_user' => 'sms usr legacy',
	'sms_msg_responsed_noti' => 'sms resp',
	'telegramnoti' => 1,
	'telegram_msg_new_noti' => 'tg new',
	'telegram_msg_responsed_noti' => 'tg resp',
	'telegram_msg_recived_usr' => 'tg usr',
	'telegram_bot_token' => '123:abc',
	'telegram_admin_chat_ids' => '-100200',
	'notification_rules' => array(array('id' => 'nr1', 'recipient' => 'sales@corp.test', 'cc' => 'cc@corp.test', 'bcc' => 'bcc@corp.test', 'subject' => 's', 'template' => 't')),
	'webhook_rules' => array(array('id' => 'wr1', 'url' => 'https://hook.test/x?token=abc', 'method' => 'POST', 'payload_fields' => array('fname'), 'headers' => array('Authorization' => 'Bearer x'))),
	'confirmation_rules' => array(array('id' => 'cr1', 'action' => 'redirect', 'url' => 'https://partner.test/{fname}')),
	'webhook_secret' => 'future key, caught by prefix',
	'crm_api_key' => 'future key, caught by suffix',
);

/* Header keys the public runtime reads (core-efb.js, payment scripts,
 * poll chart, conditional-logic public script). */
$required = array(
	'type' => 'form', 'formName' => 'Contact', 'steps' => 1, 'stateForm' => 0, 'captcha' => false,
	'trackingCode' => true, 'thank_you' => 'msg',
	'thank_you_message' => array('thankYou' => 'Thanks!', 'done' => 'Done', 'trackingCode' => 'Code', 'error' => 'Err', 'pleaseFillInRequiredFields' => 'Fill', 'icon' => 'bi-hand-thumbs-up'),
	'show_icon' => true, 'show_pro_bar' => false, 'logic' => true,
	'logic_rules' => array(array('id' => 'lr1', 'enabled' => true, 'conditions' => array('type' => 'group', 'operator' => 'AND', 'items' => array(array('type' => 'condition', 'source' => 'field', 'field_id' => 'fname', 'compare' => 'is', 'value' => 'x'))), 'actions' => array(array('type' => 'hide_field', 'target' => 'fem')))),
	'conditions' => array(array('id_' => 'fem', 'state' => 1, 'show' => 0, 'condition' => array(array('one' => 'fname', 'two' => 'x')))),
	'booking' => 0, 'getway' => 'stripe', 'paymentmethod' => 'charge', 'currency' => 'usd', 'persiaPay' => 'zarinPal',
	'AfLnFrm' => 1, 'autofill_id' => '', 'survey_chart_type' => 'bar', 'button_color' => 'btn-primary',
	'button_Next_icon' => 'bi-chevron-right', 'button_Previous_icon' => 'bi-chevron-left', 'el_height' => 'h-d-efb',
	'corner' => 'efb-square', 'progress_style' => 'bar', 'steps_style' => 'circle', 'shield_silent_captcha' => 1,
	'icon_color' => 'text-pinkEfb', 'dShowBg' => true, 'empty_object' => new stdClass(), 'ratio' => 1.0,
);

$fields = array(
	array('id_' => '1', 'type' => 'step', 'name' => 'Step 1'),
	array('id_' => 'fname', 'type' => 'text', 'name' => 'Name', 'value' => 'has "quotes" and \\backslash'),
	/* A field row may carry keys that are private on the header; field rows are not filtered. */
	array('id_' => 'fem', 'type' => 'email', 'name' => 'Email', 'noti' => 1, 'email' => 'kept-on-field@example.test'),
);

$structure = array_merge(array($required + $private), $fields);
$json = json_encode($structure, JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION);

function assert_filtered_pff($label, $rows, $private, $required, $fields) {
	check_pff("$label: decodes to a form array", is_array($rows) && isset($rows[0]));
	$head = (array) $rows[0];
	$leaked = array_values(array_intersect(array_keys($private), array_keys($head)));
	check_pff("$label: no private header key remains" . ($leaked ? ' (leaked: ' . implode(', ', $leaked) . ')' : ''), $leaked === array());
	$missing = array_values(array_diff(array_keys($required), array_keys($head)));
	check_pff("$label: every runtime header key remains" . ($missing ? ' (missing: ' . implode(', ', $missing) . ')' : ''), $missing === array());
	check_pff("$label: field rows unchanged", json_decode(json_encode(array_slice($rows, 1)), true) === $fields);
}

echo "--- emsfb_public_form_json_efb on plain JSON ---\n";
$out = emsfb_public_form_json_efb($json);
$rows = json_decode($out, false);
assert_filtered_pff('plain', $rows, $private, $required, $fields);
check_pff('plain: nested logic rules intact', json_decode(json_encode($rows[0]->logic_rules), true) === $required['logic_rules']);
check_pff('plain: thank_you_message intact', (array) $rows[0]->thank_you_message === $required['thank_you_message']);
check_pff('plain: empty object stays an object ({} not [])', strpos($out, '"empty_object":{}') !== false);
check_pff('plain: 1.0 keeps its fraction', strpos($out, '"ratio":1.0') !== false);
foreach (array('owner@corp.test', 'sales@corp.test', 'hook.test', 'partner.test', '+15550000001', '123:abc', 'internal template') as $needle) {
	check_pff("plain: output text has no '$needle'", strpos($out, $needle) === false);
}

echo "--- legacy escaped storage (admin save writes \\\" for every quote) ---\n";
$escaped = str_replace('"', '\\"', $json);
$rows_e = json_decode(emsfb_public_form_json_efb($escaped), false);
assert_filtered_pff('escaped', $rows_e, $private, $required, $fields);

echo "--- idempotent: the sink re-filters already-filtered JSON ---\n";
check_pff('second pass returns identical text', emsfb_public_form_json_efb($out) === $out);

echo "--- decoded rows (array and stdClass) ---\n";
assert_filtered_pff('array rows', emsfb_public_form_structure_efb($structure), $private, $required, $fields);
$objects = json_decode($json, false);
$filtered_objects = emsfb_public_form_structure_efb($objects);
assert_filtered_pff('object rows', $filtered_objects, $private, $required, $fields);
check_pff('object rows: caller\'s header object is not mutated', isset($objects[0]->email, $objects[0]->webhook_rules));

echo "--- non-form input ---\n";
check_pff("'track' passes through", emsfb_public_form_json_efb('track') === 'track');
check_pff("'' passes through", emsfb_public_form_json_efb('') === '');
check_pff('null passes through', emsfb_public_form_json_efb(null) === null);
check_pff('broken JSON fails closed to empty', emsfb_public_form_json_efb('[{"type":"form","email":"x@y.z"') === '');
check_pff('a JSON object (not a form array) fails closed', emsfb_public_form_json_efb('{"email":"x@y.z"}') === '');
check_pff('text that is not JSON passes through', emsfb_public_form_json_efb('"just a string"') === '"just a string"');

echo "--- add-on hook can add keys, never remove core keys ---\n";
add_filter('emsfb_public_form_private_keys', function ($keys) { $keys[] = 'crm_list_id'; return $keys; });
add_filter('emsfb_public_form_private_keys', function ($keys) { return array(); });
$keys = emsfb_public_form_private_keys_efb();
check_pff('core keys survive a filter that returns []', in_array('notification_rules', $keys, true) && in_array('email', $keys, true));
$GLOBALS['efb_test_filters'] = array();
add_filter('emsfb_public_form_private_keys', function ($keys) { $keys[] = 'crm_list_id'; return $keys; });
$with_addon = json_decode(emsfb_public_form_json_efb(json_encode(array(array('type' => 'form', 'crm_list_id' => '42', 'formName' => 'x')))), true);
check_pff('add-on key removed', !isset($with_addon[0]['crm_list_id']) && $with_addon[0]['formName'] === 'x');
$GLOBALS['efb_test_filters'] = array();

echo "--- every index-0 key the public JavaScript reads is kept ---\n";
$js_files = glob(__DIR__ . '/../public/assets/js/*.js');
foreach (array(
	__DIR__ . '/../vendor/logic/logic/assets/public/js/conditional-logic-efb.js',
	__DIR__ . '/../_workspace/easy-form-builder-form-builder/legacy-snapshot/vendor/logic/logic/assets/public/js/conditional-logic-efb.js',
) as $logic_js) {
	if (is_readable($logic_js)) { $js_files[] = $logic_js; break; }
}
$read = array();
foreach ($js_files as $file) {
	$src = file_get_contents($file);
	/* valj_efb[0].key, valueJson_ws[0].key, structure[0].key, form_structer[0].key ... */
	preg_match_all('/\[0\]\s*\??\.\s*([A-Za-z_][A-Za-z0-9_]*)/', $src, $m1);
	preg_match_all('/\[0\]\s*\.hasOwnProperty\(\s*[\'"]([A-Za-z0-9_]+)[\'"]\s*\)/', $src, $m2);
	/* const head = valj_efb[0]; head.key   /   var header = structure[0] ? structure[0] : {}; header.key */
	preg_match_all('/\b(?:const|let|var)\s+([A-Za-z_][A-Za-z0-9_]*)\s*=\s*[A-Za-z_][A-Za-z0-9_]*\[0\]\s*[;?]/', $src, $aliases);
	$alias_keys = array();
	foreach (array_unique($aliases[1]) as $alias) {
		preg_match_all('/\b' . preg_quote($alias, '/') . '\.([A-Za-z_][A-Za-z0-9_]*)\b(?!\s*\()/', $src, $ma);
		preg_match_all('/\b' . preg_quote($alias, '/') . '\.hasOwnProperty\(\s*[\'"]([A-Za-z0-9_]+)[\'"]/', $src, $mb);
		$alias_keys = array_merge($alias_keys, $ma[1], $mb[1]);
	}
	foreach (array_merge($m1[1], $m2[1], $alias_keys) as $key) $read[$key][basename($file)] = true;
}
/* Array/string members picked up by the pattern, not header keys. */
foreach (array('hasOwnProperty', 'length', 'value', 'value_', 'trim', 'id_', 'files', 'checked', 'name', 'classList', 'style', 'innerHTML', 'querySelector', 'dataset') as $noise) unset($read[$noise]);
check_pff('scanned public scripts found header reads (' . count($read) . ' keys in ' . count($js_files) . ' files)', count($read) > 10);
$stripped = array();
foreach ($read as $key => $files) {
	if (emsfb_public_form_is_private_key_efb($key)) $stripped[] = $key . ' (' . implode(', ', array_keys($files)) . ')';
}
check_pff('no key read by public JS is stripped' . ($stripped ? ': ' . implode('; ', $stripped) : ''), $stripped === array());
foreach (array('logic', 'logic_rules', 'thank_you', 'thank_you_message', 'steps', 'captcha', 'type', 'getway') as $key) {
	check_pff("public JS reads '$key' and it is kept", isset($read[$key]) && !emsfb_public_form_is_private_key_efb($key));
}

echo "--- the render path uses the filter at the localize boundary ---\n";
$public = file_get_contents(__DIR__ . '/../includes/class-Emsfb-public.php');
check_pff('public class loads the filter', strpos($public, "require_once __DIR__ . '/public-form-filter-efb.php';") !== false);
check_pff('over-broad "email" regex is gone', strpos($public, "preg_replace('/\"email\":\"[^\"]*\"/'") === false);
if (preg_match('/private function ajax_object_efm_efb\(.*?wp_localize_script\(\s*\'Emsfb-core_js\'/s', $public, $sink)) {
	check_pff('sink filters ajax_value', strpos($sink[0], '$values = \\emsfb_public_form_json_efb($values);') !== false);
	check_pff('sink filters every ajax_value_forms[].form_structer', strpos($sink[0], "\\emsfb_public_form_json_efb(\$row_form['form_structer'])") !== false);
	check_pff('filtering happens before the values are placed in $ar_core', strpos($sink[0], 'emsfb_public_form_json_efb') < strpos($sink[0], "'ajax_value' =>"));
} else {
	check_pff('ajax_object_efm_efb() found', false);
}
check_pff('inline render filters $fs after decoding the server copy', (bool) preg_match('/\$valj_efb = json_decode\(\$fs[^;]*;\s*\$fs = \\\\emsfb_public_form_json_efb\(\$fs\);/', $public));
$prepare = preg_match('/public function prepare_form_session_api\(.*?\n\t\}\n/s', $public, $pm) ? $pm[0] : '';
check_pff('runtime prepare endpoint returns no form JSON', $prepare !== '' && strpos($prepare, 'WP_REST_Response(array(\'nonce\'') !== false && strpos($prepare, "'form_structer'") === false && strpos($prepare, 'ajax_value') === false);
$loader = file_get_contents(__DIR__ . '/../includes/class-Emsfb.php');
check_pff('missing-file guard knows the filter file', strpos($loader, "require_plugin_file_efb('includes/public-form-filter-efb.php')") !== false);

echo "\n$pass passed, $fail failed\n";
exit($fail ? 1 : 0);
