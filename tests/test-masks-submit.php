<?php
/**
 * F01-F: server validation of masked fields. Runs the real `default:` branch of the submit loop in
 * Emsfb\_Public::get_form_public_efb() (located by the fixed "// Mask gate:" marker) together with the real
 * filter_attributes_by_type_efb() and its helpers, all extracted from includes/class-Emsfb-public.php,
 * against every catalog vector (pattern of tests/test-sms-failure-email-continuation.php).
 *
 * Checks: invalid vectors -> is_valid 0, the right field id and the translated message for the code;
 * valid vectors -> the stored value equals the JS engine's toStored() (node tests/test-masks-vectors.js
 * --json, so client and server agree); display storage; empty optional field; unknown preset and broken
 * custom mask fall back to the old length rules; unmasked fields unchanged; "50%"; the message keys reach
 * $translation_keys and the public subset.
 *
 * Run: php tests/test-masks-submit.php   (needs node on PATH for the JS comparison; [SKIP] without it)
 */
if (!defined('ABSPATH')) {
	define('ABSPATH', __DIR__ . '/');
}
/* WordPress-faithful sanitize_text_field: invalid UTF-8 -> '', tags stripped, %XX octets removed,
 * whitespace collapsed, trimmed. */
function sanitize_text_field($v) {
	if (is_array($v) || is_object($v)) {
		return '';
	}
	$s = (string) $v;
	if (!preg_match('//u', $s)) {
		return '';
	}
	$s = strip_tags($s);
	$s = preg_replace('/%[a-f0-9]{2}/i', '', $s);
	$s = preg_replace('/[\r\n\t ]+/', ' ', $s);
	return trim($s);
}
function sanitize_email($v) { return filter_var((string) $v, FILTER_SANITIZE_EMAIL); }
function sanitize_url($v) { return filter_var((string) $v, FILTER_SANITIZE_URL); }
require_once __DIR__ . '/../includes/class-Emsfb-masks.php';

$src = file_get_contents(__DIR__ . '/../includes/class-Emsfb-public.php');
$marker = strpos($src, '// Mask gate:');
if ($marker === false) {
	fwrite(STDERR, "Mask gate marker not found\n");
	exit(1);
}
$start = strrpos(substr($src, 0, $marker), 'default:');
$endMarker = strpos($src, '$still_processing = false;', strpos($src, '// End mask gate.', $marker));
$end = strpos($src, 'break;', $endMarker) + strlen('break;');
$branch = substr($src, $start, $end - $start);
$methods = '';
foreach (array('filter_attributes_by_type_efb', 'sanitize_value_efb', 'filter_and_sanitize_attributes_efb') as $mname) {
	if (!preg_match('/\tprivate function ' . $mname . '\(.*?\n\t}\n/s', $src, $mm)) {
		fwrite(STDERR, "method $mname not found\n");
		exit(1);
	}
	$methods .= $mm[0];
}
eval('class MaskSubmitFixture { public $lanText = array();' . $methods . '
	public function run($f, $item) {
		$is_valid = null; $error_message = "provisional"; $error_field_id = ""; $validated_item = null; $still_processing = true;
		switch ($f["type"]) {' . $branch . '
		}
		return array("is_valid" => $is_valid, "m" => $error_message, "field_id" => $error_field_id, "item" => $validated_item);
	}
}');

$pass = 0;
$fail = 0;
function st($label, $cond, $detail = '') {
	global $pass, $fail;
	if ($cond) { $pass++; return; }
	$fail++;
	echo "[FAIL] $label" . ($detail !== '' ? "\n      $detail" : '') . "\n";
}
$fx = new MaskSubmitFixture();
$fx->lanText = array('mskFormat_' => 'F[%1$s][%2$s]', 'mskChecksum_' => 'C[%s]', 'mskRule_' => 'R[%s]', 'mskIncomplete_' => 'I[%s]', 'mskRange_' => 'RG[%s]',
	'ptrnMmm_' => 'MIN[%1$s][%2$s]', 'ptrnMmx_' => 'MAX[%1$s][%2$s]');
function row_item($id, $type, $value) {
	return array('id_' => $id, 'name' => 'F', 'id_ob' => $id . '_', 'amount' => '2', 'type' => $type, 'value' => $value, 'session' => 's', 'form_id' => '7', 'junk' => 'x');
}

// 1. Every catalog vector, on a tel field for phones and a text field otherwise.
$cat = json_decode(file_get_contents(__DIR__ . '/../includes/data/masks-catalog-efb.json'), true);
$jsStored = null;
$node = trim((string) @shell_exec('command -v node'));
if ($node !== '') {
	$rows = json_decode((string) shell_exec(escapeshellarg($node) . ' ' . escapeshellarg(__DIR__ . '/test-masks-vectors.js') . ' --json'), true);
	if (is_array($rows)) {
		$jsStored = array();
		foreach ($rows as $r) {
			if ($r['kind'] === 'valid') {
				$jsStored[$r['id'] . "\0" . $r['input']] = $r['stored'];
			}
		}
	}
}
if ($jsStored === null) {
	echo "[SKIP] node not available: stored values are compared with Masks::to_stored() only\n";
}
$counts = array('valid' => 0, 'invalid' => 0, 'changed_by_sanitize' => 0);
foreach ($cat['masks'] as $m) {
	$type = strpos($m['category'], 'phone') === 0 ? 'tel' : 'text';
	$f = array('id_' => 'fld', 'type' => $type, 'name' => 'Label', 'required' => '1', 'mask_preset' => $m['id'], 'placeholder' => '');
	foreach ($m['examples_valid'] as $v) {
		$r = $fx->run($f, row_item('fld', $type, $v));
		$expected = $jsStored !== null ? $jsStored[$m['id'] . "\0" . $v] : Masks_expected_stored($m, $v);
		if (sanitize_text_field($v) !== $v) {
			$counts['changed_by_sanitize']++;
		}
		st("{$m['id']} valid " . json_encode($v, JSON_UNESCAPED_UNICODE), $r['is_valid'] === 1 && $r['item']['value'] === $expected && $r['m'] === 'provisional',
			json_encode($r, JSON_UNESCAPED_UNICODE) . ' expected ' . json_encode($expected, JSON_UNESCAPED_UNICODE));
		st("{$m['id']} valid row keeps only allowed keys", is_array($r['item']) && !isset($r['item']['junk']));
		$counts['valid']++;
	}
	foreach ($m['examples_invalid'] as $x) {
		$r = $fx->run($f, row_item('fld', $type, $x['value']));
		$msg = array('format' => 'F[Label][' . $m['placeholder']['en'] . ']', 'checksum' => 'C[Label]', 'rule' => 'R[Label]', 'empty' => 'I[Label]');
		st("{$m['id']} invalid " . json_encode($x['value'], JSON_UNESCAPED_UNICODE) . " ({$x['expect']})", $r['is_valid'] === 0 && $r['field_id'] === 'fld' && $r['m'] === $msg[$x['expect']],
			json_encode($r, JSON_UNESCAPED_UNICODE));
		$counts['invalid']++;
	}
}
function Masks_expected_stored($m, $v) {
	$r = \Emsfb\Masks::validate($m, sanitize_text_field($v));
	return \Emsfb\Masks::to_stored($m, $r['value']);
}
echo "catalog vectors through the server gate: {$counts['valid']} valid, {$counts['invalid']} invalid ({$counts['changed_by_sanitize']} inputs changed by sanitize_text_field first)\n";

// 2. Field options.
$f = array('id_' => 'm', 'type' => 'tel', 'name' => 'Mobile', 'required' => '1', 'mask_preset' => 'ir.mobile', 'placeholder' => '۰۹۱۲ ۳۴۵ ۶۷۸۹');
$r = $fx->run($f + array('mask_store' => 'display'), row_item('m', 'tel', '+989121234567'));
st('display storage: national display form stored', $r['is_valid'] === 1 && $r['item']['value'] === '0912 123 4567', json_encode($r));
$r = $fx->run($f, row_item('m', 'tel', '0812345678'));
st('format message uses the field placeholder as the example', $r['m'] === 'F[Mobile][۰۹۱۲ ۳۴۵ ۶۷۸۹]', $r['m']);
$r = $fx->run(array('required' => '0') + $f, row_item('m', 'tel', ''));
st('empty optional masked field passes', $r['is_valid'] === 1 && $r['item']['value'] === '', json_encode($r));
$r = $fx->run($f, row_item('m', 'tel', '   '));
st('blank required masked field: incomplete message', $r['is_valid'] === 0 && $r['m'] === 'I[Mobile]', json_encode($r));
$r = $fx->run(array('id_' => 'p', 'type' => 'text', 'name' => 'Pct', 'mask_preset' => 'intl.percentage'), row_item('p', 'text', '50%'));
st('50% survives sanitize_text_field and validates', $r['is_valid'] === 1 && $r['item']['value'] === '50%', json_encode($r));
$r = $fx->run(array('id_' => 'c', 'type' => 'text', 'name' => 'Code', 'mask_preset' => 'custom', 'mask_custom' => '(999) 999-9999', 'mask_store' => 'display'), row_item('c', 'text', '2025550123'));
st('custom mask, display storage', $r['is_valid'] === 1 && $r['item']['value'] === '(202) 555-0123', json_encode($r));
$r = $fx->run(array('id_' => 'c', 'type' => 'text', 'name' => 'Code', 'mask_preset' => 'custom', 'mask_custom' => '(999) 999-9999'), row_item('c', 'text', '(202) 555-012'));
st('custom mask incomplete', $r['is_valid'] === 0 && $r['m'] === 'I[Code]', json_encode($r));
$r = $fx->run(array('id_' => 'c', 'type' => 'text', 'name' => 'Card', 'mask_preset' => 'custom', 'mask_custom' => '9999 9999 9999 9999', 'mask_checksum' => 'luhn'), row_item('c', 'text', '4111 1111 1111 1112'));
st('custom mask with luhn', $r['is_valid'] === 0 && $r['m'] === 'C[Card]', json_encode($r));
$r = $fx->run(array('id_' => 's', 'type' => 'text', 'name' => 'Zip', 'mask_preset' => 'ir.postal_code', 'mask_strict' => '1'), row_item('s', 'text', '1023456789'));
st('mask_strict enforced on the server', $r['is_valid'] === 0, json_encode($r));
$r = $fx->run(array('id_' => 'pc', 'type' => 'postalcode', 'name' => 'Zip', 'mask_preset' => 'us.zip'), row_item('pc', 'postalcode', '12345-6789'));
st('postalcode field type goes through the gate', $r['is_valid'] === 1 && $r['item']['value'] === '12345-6789', json_encode($r));
$r = $fx->run($f, row_item('m', 'tel', str_repeat('9', 300)));
st('overlong value -> format', $r['is_valid'] === 0 && strpos($r['m'], 'F[') === 0, json_encode($r));

$r = $fx->run(array('id_' => 'cs', 'type' => 'text', 'name' => 'Code', 'mask_preset' => 'custom', 'mask_custom' => 'aa-99', 'mask_case' => 'upper'), row_item('cs', 'text', 'ab12'));
st('I2: mask_case upper applied on the server', $r['is_valid'] === 1 && $r['item']['value'] === 'AB12', json_encode($r));
$r = $fx->run(array('id_' => 'cs', 'type' => 'text', 'name' => 'Code', 'mask_preset' => 'custom', 'mask_custom' => '&&&-99', 'mask_store' => 'display'), row_item('cs', 'text', 'x1z45'));
st('I2: & token stores capitals', $r['is_valid'] === 1 && $r['item']['value'] === 'X1Z-45', json_encode($r));

$fa = array('id_' => 'al', 'type' => 'tel', 'name' => 'Phone', 'mask_preset' => 'custom', 'mask_custom' => '(99) 9999-9999||(99) 99999-9999', 'mask_store' => 'display');
$r = $fx->run($fa, row_item('al', 'tel', '11912345678'));
st('I4: alternation on the server picks the mobile mask', $r['is_valid'] === 1 && $r['item']['value'] === '(11) 91234-5678', json_encode($r));
$r = $fx->run($fa, row_item('al', 'tel', '113456789'));
st('I4: alternation incomplete', $r['is_valid'] === 0 && $r['m'] === 'I[Phone]', json_encode($r));

$fn = array('id_' => 'nm', 'type' => 'text', 'name' => 'Amount', 'mask_preset' => 'numeric', 'num_group' => ',', 'num_digits' => '2', 'num_max' => '1000');
$r = $fx->run($fn, row_item('nm', 'text', '۱٬۲۳۴٫۵'));
st('I1: server range check (1234.5 > 1000)', $r['is_valid'] === 0 && $r['m'] === 'RG[Amount]', json_encode($r));
$r = $fx->run($fn, row_item('nm', 'text', '999.5'));
st('I1: server stores the canonical number', $r['is_valid'] === 1 && $r['item']['value'] === '999.5', json_encode($r));
$r = $fx->run($fn + array('mask_store' => 'display'), row_item('nm', 'text', '999.5'));
st('I1: display storage', $r['is_valid'] === 1 && $r['item']['value'] === '999.5', json_encode($r));
$r = $fx->run($fn, row_item('nm', 'text', '12abc'));
st('I1: server rejects text', $r['is_valid'] === 0, json_encode($r));

// 3. Fallbacks: never lock a form.
$r = $fx->run(array('id_' => 'u', 'type' => 'text', 'name' => 'U', 'mask_preset' => 'zz.unknown', 'mlen' => '5'), row_item('u', 'text', 'abcdefgh'));
st('unknown preset: old max length rule applies', $r['is_valid'] === 0 && $r['m'] === 'MAX[<b>U</b>][<b>5</b>]', json_encode($r));
$r = $fx->run(array('id_' => 'u', 'type' => 'text', 'name' => 'U', 'mask_preset' => 'zz.unknown'), row_item('u', 'text', 'anything'));
st('unknown preset: value accepted unchanged', $r['is_valid'] === 1 && $r['item']['value'] === 'anything', json_encode($r));
$r = $fx->run(array('id_' => 'b', 'type' => 'text', 'name' => 'B', 'mask_preset' => 'custom', 'mask_custom' => '9{'), row_item('b', 'text', 'x'));
st('broken custom mask: accepted like an unmasked field', $r['is_valid'] === 1 && $r['item']['value'] === 'x', json_encode($r));

// 4. Unmasked fields behave as before (length rules, ignored mask-less).
$r = $fx->run(array('id_' => 't', 'type' => 'text', 'name' => 'T', 'milen' => '3'), row_item('t', 'text', 'ab'));
st('unmasked: min length rule unchanged', $r['is_valid'] === 0 && $r['m'] === 'MIN[<b>T</b>][<b>3</b>]', json_encode($r));
$r = $fx->run(array('id_' => 't', 'type' => 'tel', 'name' => 'T', 'mlen' => '20'), row_item('t', 'tel', ' 0912 123 4567 '));
st('unmasked tel: value only sanitized, as before', $r['is_valid'] === 1 && $r['item']['value'] === '0912 123 4567', json_encode($r));
$r = $fx->run(array('id_' => 'k', 'type' => 'checkbox', 'name' => 'K', 'mask_preset' => 'ir.mobile'), row_item('k', 'checkbox', 'x'));
st('checkbox-family rows pass before any mask check', $r['is_valid'] === 1, json_encode($r));

// 5. Wiring.
st('message keys in $translation_keys', preg_match("/'mskFormat_', 'mskChecksum_', 'mskRule_', 'mskIncomplete_', 'mskRange_'/", $src) === 1);
st('message keys in the public text subset', strpos($src, "'mskFormat_','mskChecksum_','mskRule_','mskIncomplete_','mskRange_'];") !== false);
$gate = substr($src, $marker, strpos($src, '// End mask gate.', $marker) - $marker);
st('the gate runs inside the per-row validation (only submitted rows are checked)', strpos($src, '// Mask gate:') > strpos($src, '$_ignored_set') - 200000 && strpos($gate, '$item[\'value\']') !== false);
st('the gate never reads a regex from the request', strpos($gate, '$request_data') === false && strpos($gate, '$_POST') === false && strpos($gate, 'preg_match') === false);
$fsrc = file_get_contents(__DIR__ . '/../includes/functions.php');
foreach (array('mskFormat_', 'mskChecksum_', 'mskRule_', 'mskIncomplete_') as $k) {
	st("phrase $k in text_efb()", preg_match('/"' . $k . '" => \$state && isset\(\$ac->text->' . $k . '\)/', $fsrc) === 1);
}

echo "\n========================================\n";
echo "RESULTS: $pass passed, $fail failed\n";
echo "========================================\n";
exit($fail > 0 ? 1 : 0);
