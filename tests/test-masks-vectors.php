<?php
/**
 * F01: every catalog vector through \Emsfb\Masks (includes/class-Emsfb-masks.php), the PHP twin of
 * public/assets/js/masks-efb.js.
 *
 * Run: php tests/test-masks-vectors.php                 (human output, exit 1 on failure)
 *      php tests/test-masks-vectors.php --json          (catalog vector rows, for tests/test-masks-parity.js)
 *      php tests/test-masks-vectors.php --custom-json   (tests/fixtures/masks-custom-vectors.json results,
 *                                                        for tests/test-masks-custom.js)
 */

if (!defined('ABSPATH')) {
	define('ABSPATH', __DIR__ . '/');
}
require_once __DIR__ . '/../includes/class-Emsfb-masks.php';

use Emsfb\Masks;

$cat = json_decode(file_get_contents(__DIR__ . '/../includes/data/masks-catalog-efb.json'), true);

function efbm_test_rows($cat) {
	$rows = array();
	foreach ($cat['masks'] as $m) {
		foreach ($m['examples_valid'] as $v) {
			$r = Masks::validate($m, $v);
			$rows[] = array('id' => $m['id'], 'kind' => 'valid', 'input' => $v, 'ok' => $r['ok'], 'code' => $r['code'], 'value' => $r['value'], 'stored' => $r['ok'] ? Masks::to_stored($m, $r['value']) : null, 'pass' => $r['ok']);
		}
		foreach ($m['examples_invalid'] as $x) {
			$r = Masks::validate($m, $x['value']);
			$rows[] = array('id' => $m['id'], 'kind' => 'invalid', 'input' => $x['value'], 'ok' => $r['ok'], 'code' => $r['code'], 'value' => $r['value'], 'stored' => null, 'pass' => !$r['ok'] && $r['code'] === $x['expect']);
		}
		if (!empty($m['examples_format'])) {
			foreach ($m['examples_format'] as $f) {
				$out = Masks::format_value($m, $f['input']);
				$rows[] = array('id' => $m['id'], 'kind' => 'format', 'input' => $f['input'], 'ok' => $out === $f['output'], 'code' => null, 'value' => $out, 'stored' => null, 'pass' => $out === $f['output']);
			}
		}
	}
	return $rows;
}

function efbm_test_custom_rows($cat) {
	$fx = json_decode(file_get_contents(__DIR__ . '/fixtures/masks-custom-vectors.json'), true);
	$byId = array();
	foreach ($cat['masks'] as $m) {
		$byId[$m['id']] = $m;
	}
	$out = array('validate_custom' => array(), 'is_complete' => array(), 'to_national_display' => array(), 'format_display' => array(), 'check_mask' => array());
	foreach ($fx['validate_custom'] as $c) {
		$def = array('mask' => $c['mask'], 'checksum' => $c['checksum']);
		$r = Masks::validate_custom($def, $c['input']);
		$out['validate_custom'][] = array('ok' => $r['ok'], 'code' => $r['code'], 'value' => $r['value'], 'stored' => $r['ok'] ? Masks::to_stored_custom($def, $r['value']) : null);
	}
	foreach ($fx['is_complete'] as $c) {
		$out['is_complete'][] = Masks::is_complete($c['mask'], $c['input']);
	}
	foreach ($fx['to_national_display'] as $c) {
		$out['to_national_display'][] = Masks::to_national_display($byId[$c['preset']], $c['input']);
	}
	foreach ($fx['format_display'] as $c) {
		$out['format_display'][] = Masks::format_display($byId[$c['preset']], $c['input']);
	}
	foreach ($fx['check_mask'] as $c) {
		$out['check_mask'][] = Masks::check_mask($c['mask']);
	}
	return $out;
}

if (in_array('--json', $argv, true)) {
	echo json_encode(efbm_test_rows($cat), JSON_UNESCAPED_UNICODE);
	exit(0);
}
if (in_array('--custom-json', $argv, true)) {
	echo json_encode(efbm_test_custom_rows($cat), JSON_UNESCAPED_UNICODE);
	exit(0);
}

$pass = 0;
$fail = 0;
function efbm_check($label, $cond, $detail = '') {
	global $pass, $fail;
	if ($cond) {
		$pass++;
		return;
	}
	$fail++;
	echo "[FAIL] $label" . ($detail !== '' ? ' ' . $detail : '') . "\n";
}

$rows = efbm_test_rows($cat);
$counts = array('valid' => array(0, 0), 'invalid' => array(0, 0), 'format' => array(0, 0));
$byId = array();
foreach ($rows as $r) {
	$counts[$r['kind']][$r['pass'] ? 0 : 1]++;
	$byId[$r['id']][] = $r;
}
foreach ($byId as $id => $list) {
	$bad = array_filter($list, function ($r) {
		return !$r['pass'];
	});
	if (!$bad) {
		$pass++;
		echo "[PASS] $id: " . count($list) . " vectors\n";
		continue;
	}
	foreach ($bad as $r) {
		$fail++;
		echo "[FAIL] $id {$r['kind']} " . json_encode($r['input'], JSON_UNESCAPED_UNICODE) . ' -> ok=' . var_export($r['ok'], true) . ' code=' . var_export($r['code'], true) . ' value=' . json_encode($r['value'], JSON_UNESCAPED_UNICODE) . "\n";
	}
}
printf("vectors: valid %d/%d, invalid %d/%d, format %d/%d\n", $counts['valid'][0], array_sum($counts['valid']), $counts['invalid'][0], array_sum($counts['invalid']), $counts['format'][0], array_sum($counts['format']));
efbm_check('all 612 catalog vectors present', count($rows) === 612, '(got ' . count($rows) . ')');

// Catalog loading, preset lookup and the browser subset.
Masks::reset();
$t0 = microtime(true);
$loaded = Masks::catalog();
$ms = (microtime(true) - $t0) * 1000;
echo sprintf("catalog decode: %.2f ms, %d presets, %d bytes\n", $ms, count($loaded['masks']), filesize(Masks::catalog_path()));
efbm_check('catalog() loads 99 presets', count($loaded['masks']) === 99);
efbm_check('preset() finds ir.mobile', is_array(Masks::preset('ir.mobile')) && Masks::preset('ir.mobile')['mask'] === '0\\999 999 9999');
efbm_check('preset() refuses a path-like id', Masks::preset('../x') === null && Masks::preset('IR.MOBILE') === null);
efbm_check('preset() returns null for an unknown id', Masks::preset('zz.nothing') === null);
$sub = Masks::runtime_subset(array('ir.mobile', 'ir.mobile', 'zz.nothing', 'intl.card'), 'fa_IR');
efbm_check('runtime_subset keeps known ids only', array_keys($sub) === array('ir.mobile', 'intl.card'));
$leak = false;
foreach ($sub as $row) {
	foreach (array('examples_valid', 'examples_invalid', 'examples_format', 'metadata', 'sources', 'notes', 'label') as $k) {
		if (array_key_exists($k, $row)) {
			$leak = true;
		}
	}
}
efbm_check('runtime_subset has no examples, metadata, sources, notes or labels', !$leak);
efbm_check('runtime_subset picks the fa placeholder', $sub['ir.mobile']['placeholder'] === '۰۹۱۲ ۳۴۵ ۶۷۸۹');
$en = Masks::runtime_subset(array('ir.mobile'), 'de_DE');
efbm_check('runtime_subset falls back to the en placeholder', $en['ir.mobile']['placeholder'] === '0912 345 6789');
$all = Masks::runtime_subset(array_map(function ($m) {
	return $m['id'];
}, $loaded['masks']), 'en_US');
$size = strlen(json_encode($all, JSON_UNESCAPED_UNICODE));
echo "runtime subset of all 99 presets: $size bytes\n";
efbm_check('runtime subset of all presets stays small (< 40 KB)', $size < 40960);
efbm_check('is_sensitive: national id, cards, iban, cvc', Masks::is_sensitive(Masks::preset('ir.national_id')) && Masks::is_sensitive(Masks::preset('intl.card')) && Masks::is_sensitive(Masks::preset('ir.bank_card')) && Masks::is_sensitive(Masks::preset('ir.sheba')) && Masks::is_sensitive(Masks::preset('intl.cvc')));
efbm_check('is_sensitive: mobile is not', !Masks::is_sensitive(Masks::preset('ir.mobile')));

// check_field: the server gate used by the submit handler.
$f = array('id_' => 'f1', 'name' => 'Mobile', 'type' => 'tel', 'mask_preset' => 'ir.mobile', 'required' => '1');
$r = Masks::check_field($f, '۰۹۱۲ ۱۲۳ ۴۵۶۷');
efbm_check('check_field: valid mobile stored as E.164', $r['ok'] && $r['stored'] === '+989121234567' && !$r['legacy']);
$r = Masks::check_field($f, '+989121234567');
efbm_check('check_field: canonical value is accepted again', $r['ok'] && $r['stored'] === '+989121234567');
$r = Masks::check_field($f + array('mask_store' => 'display'), '+989121234567');
efbm_check('check_field: display storage re-formats to the national mask', $r['ok'] && $r['stored'] === '0912 123 4567');
$r = Masks::check_field($f, '0812345678');
efbm_check('check_field: wrong prefix -> format', !$r['ok'] && $r['code'] === 'format');
$r = Masks::check_field($f, '');
efbm_check('check_field: empty required -> empty', !$r['ok'] && $r['code'] === 'empty');
$r = Masks::check_field(array('mask_preset' => 'ir.mobile', 'required' => '0'), '');
efbm_check('check_field: empty optional passes', $r['ok'] && $r['stored'] === '');
$r = Masks::check_field(array('mask_preset' => 'ir.national_id'), '1234567892');
efbm_check('check_field: national id checksum', !$r['ok'] && $r['code'] === 'checksum');
$r = Masks::check_field(array('mask_preset' => 'ir.national_id'), '12345679');
efbm_check('check_field: national id left-padded and stored as digits', $r['ok'] && $r['stored'] === '0012345679');
$r = Masks::check_field(array('mask_preset' => 'zz.unknown'), 'anything');
efbm_check('check_field: unknown preset -> legacy pass-through', $r['ok'] && $r['legacy'] && $r['stored'] === 'anything');
$r = Masks::check_field(array('mask_preset' => 'custom', 'mask_custom' => '9{'), 'x');
efbm_check('check_field: broken custom mask -> legacy pass-through', $r['ok'] && $r['legacy']);
$r = Masks::check_field(array('mask_preset' => 'custom', 'mask_custom' => '0\\999 999 9999'), '0991 234 5678');
efbm_check('check_field: custom mask clean value', $r['ok'] && $r['stored'] === '09912345678');
$r = Masks::check_field(array('mask_preset' => 'custom', 'mask_custom' => '9999 9999 9999 9999', 'mask_checksum' => 'luhn'), '4111111111111112');
efbm_check('check_field: custom luhn', !$r['ok'] && $r['code'] === 'checksum');
$r = Masks::check_field(array('mask_preset' => 'custom', 'mask_custom' => '9999', 'mask_checksum' => 'bogus'), '1234');
efbm_check('check_field: unknown custom checksum is ignored', $r['ok'] && $r['stored'] === '1234');
$r = Masks::check_field(array('mask_preset' => 'ir.postal_code'), '1023456789');
$rs = Masks::check_field(array('mask_preset' => 'ir.postal_code', 'mask_strict' => '1'), '1023456789');
efbm_check('check_field: mask_strict switches to regex_strict', $r['ok'] && !$rs['ok'] && $rs['code'] === 'format');
$r = Masks::check_field(array('mask_preset' => 'intl.slug'), str_repeat('a', 300));
efbm_check('check_field: values over 256 characters -> format', !$r['ok'] && $r['code'] === 'format');
$r = Masks::check_field(array('mask_preset' => 'intl.slug'), "ab\xC3");
efbm_check('check_field: invalid UTF-8 -> format', !$r['ok'] && $r['code'] === 'format');
$r = Masks::check_field(array('mask_preset' => 'intl.percentage'), '50%');
efbm_check('check_field: 50% is a valid percentage', $r['ok'] && $r['stored'] === '50%');

// message()
$lan = array('mskFormat_' => 'F:%1$s|%2$s', 'mskChecksum_' => 'C:%s', 'mskRule_' => 'R:%s', 'mskIncomplete_' => 'I:%s');
efbm_check('message format uses the field placeholder', Masks::message('format', array('name' => 'M', 'placeholder' => 'ex', 'mask_preset' => 'ir.mobile'), $lan) === 'F:M|ex');
efbm_check('message format falls back to the catalog example', Masks::message('format', array('name' => 'M', 'mask_preset' => 'ir.mobile'), $lan) === 'F:M|0912 345 6789');
efbm_check('message checksum / rule / empty', Masks::message('checksum', array('name' => 'N'), $lan) === 'C:N' && Masks::message('rule', array('name' => 'N'), $lan) === 'R:N' && Masks::message('empty', array('name' => 'N'), $lan) === 'I:N');
efbm_check('message English fallback', Masks::message('checksum', array('name' => 'N'), array()) === 'N is not valid. Please check the digits for typos.');

echo "\n========================================\n";
echo "RESULTS: $pass passed, $fail failed (catalog vectors failing: " . ($counts['valid'][1] + $counts['invalid'][1] + $counts['format'][1]) . ")\n";
echo "========================================\n";
exit($fail > 0 ? 1 : 0);
