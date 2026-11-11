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
		if (!empty($m['examples_invalid_strict'])) {
			foreach ($m['examples_invalid_strict'] as $x) {
				$r = Masks::validate(Masks::strict_def($m), $x['value']);
				$rows[] = array('id' => $m['id'], 'kind' => 'invalid_strict', 'input' => $x['value'], 'ok' => $r['ok'], 'code' => $r['code'], 'value' => $r['value'], 'stored' => null, 'pass' => !$r['ok'] && $r['code'] === $x['expect']);
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
	$out = array('validate_custom' => array(), 'is_complete' => array(), 'to_national_display' => array(), 'format_display' => array(), 'check_mask' => array(), 'apply_mask' => array(), 'numeric' => array());
	foreach ($fx['validate_custom'] as $c) {
		$def = array('mask' => $c['mask'], 'checksum' => $c['checksum'], 'case' => isset($c['case']) ? $c['case'] : '');
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
	foreach ($fx['numeric'] as $c) {
		$def = Masks::num_def($c['cfg']);
		$r = Masks::num_parse($def, $c['input']);
		$out['numeric'][] = array('ok' => $r['ok'], 'code' => $r['code'], 'value' => $r['value'], 'display' => $r['ok'] ? Masks::num_format($def, $r['value']) : null);
	}
	foreach ($fx['apply_mask'] as $c) {
		$out['apply_mask'][] = Masks::apply_mask($c['mask'], $c['input']);
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
$counts = array('valid' => array(0, 0), 'invalid' => array(0, 0), 'format' => array(0, 0), 'invalid_strict' => array(0, 0));
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
$expected_rows = 0;
foreach ($cat['masks'] as $m) {
	$expected_rows += count($m['examples_valid']) + count($m['examples_invalid']) + (empty($m['examples_format']) ? 0 : count($m['examples_format'])) + (empty($m['examples_invalid_strict']) ? 0 : count($m['examples_invalid_strict']));
}
printf("strict vectors: %d/%d\n", $counts['invalid_strict'][0], array_sum($counts['invalid_strict']));
efbm_check("all $expected_rows catalog vectors present", count($rows) === $expected_rows && $expected_rows >= 612, '(got ' . count($rows) . ')');
$npresets = count($cat['masks']);

// Catalog loading, preset lookup and the browser subset.
Masks::reset();
$t0 = microtime(true);
$loaded = Masks::catalog();
$ms = (microtime(true) - $t0) * 1000;
echo sprintf("catalog decode: %.2f ms, %d presets, %d bytes\n", $ms, count($loaded['masks']), filesize(Masks::catalog_path()));
efbm_check("catalog() loads all $npresets presets", count($loaded['masks']) === $npresets);
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
echo "runtime subset of all $npresets presets: $size bytes\n";
efbm_check('runtime subset stays small (< 400 bytes per preset on average)', $size < 400 * $npresets);
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

// Connector API (SitePilot AI): catalog_public() and check_value().
$pub = Masks::catalog_public();
efbm_check("catalog_public lists all $npresets presets", isset($pub['presets']) && count($pub['presets']) === $npresets);
$p0 = $pub['presets'][0];
efbm_check('catalog_public row has the discovery fields', $p0['id'] === 'ir.mobile' && $p0['country'] === 'IR' && $p0['category'] === 'phone_mobile'
	&& $p0['label']['fa'] === 'شماره موبایل' && $p0['inputmode'] === 'tel' && array_key_exists('checksum', $p0) && $p0['verification'] === 'secondary' && $p0['has_strict'] === false);
$hasRegex = false;
foreach ($pub['presets'] as $row) {
	if (array_key_exists('regex', $row) || array_key_exists('regex_strict', $row) || array_key_exists('examples_valid', $row) || array_key_exists('metadata', $row)) {
		$hasRegex = true;
	}
}
efbm_check('catalog_public has no regex, examples or metadata', !$hasRegex);
efbm_check('catalog_public documents the field keys', array_keys($pub['field_keys']) === array('mask_preset', 'num_group', 'num_radix', 'num_digits', 'num_digits_fixed', 'num_negative', 'num_min', 'num_max', 'num_prefix', 'num_suffix', 'num_grouping', 'mask_custom', 'mask_checksum', 'mask_store', 'mask_strict', 'mask_case', 'inputmode'));
$cv = Masks::check_value('ir.mobile', '0912 123 4567');
efbm_check('check_value preset ok', $cv === array('ok' => true, 'code' => null, 'stored' => '+989121234567'));
$cv = Masks::check_value('ir.national_id', '1234567892');
efbm_check('check_value preset checksum', $cv['ok'] === false && $cv['code'] === 'checksum' && $cv['stored'] === '');
$cv = Masks::check_value('custom:999-999', '123456');
efbm_check('check_value custom mask', $cv === array('ok' => true, 'code' => null, 'stored' => '123456'));
$cv = Masks::check_value(array('mask_preset' => 'ir.mobile', 'mask_store' => 'display'), '+989121234567');
efbm_check('check_value with field keys (display storage)', $cv['ok'] && $cv['stored'] === '0912 123 4567');
efbm_check('check_value empty -> empty', Masks::check_value('ir.mobile', '')['code'] === 'empty');
efbm_check('check_value unknown preset -> unknown_mask', Masks::check_value('zz.nothing', 'x') === array('ok' => false, 'code' => 'unknown_mask', 'stored' => ''));
efbm_check('check_value hostile id -> unknown_mask', Masks::check_value('../../etc', 'x')['code'] === 'unknown_mask' && Masks::check_value(null, 'x')['code'] === 'unknown_mask');

$byPub = array();
foreach ($pub['presets'] as $row) {
	$byPub[$row['id']] = $row;
}
efbm_check('catalog_public: unverified presets carry a warning', $byPub['ir.legal_id']['warning'] === 'unverified' && $byPub['ir.mobile']['warning'] === null);
efbm_check('catalog_public: pii flag on national ids, cards, IBAN, CVC only', $byPub['ir.national_id']['pii'] && $byPub['intl.card']['pii'] && $byPub['ir.sheba']['pii'] && $byPub['intl.cvc']['pii'] && !$byPub['ir.mobile']['pii'] && !$byPub['us.zip']['pii']);
efbm_check('check_value custom incomplete -> incomplete', Masks::check_value('custom:999-999', '123')['code'] === 'incomplete');
efbm_check('message for incomplete', Masks::message('incomplete', array('name' => 'N'), array('mskIncomplete_' => 'I:%s')) === 'I:N');
$vk = Masks::validate_field_keys(array('mask_preset' => 'zz.nothing', 'mask_checksum' => 'bogus', 'mask_store' => 'raw', 'inputmode' => 'x'));
efbm_check('validate_field_keys reports unknown_mask and bad keys', array_map(function ($e) { return $e['code']; }, $vk['errors']) === array('unknown_mask', 'invalid_checksum', 'invalid_store', 'invalid_inputmode'));
$vk = Masks::validate_field_keys(array('mask_preset' => 'custom', 'mask_custom' => '9{'));
efbm_check('validate_field_keys reports invalid_mask', $vk['errors'] === array(array('key' => 'mask_custom', 'code' => 'invalid_mask')));
$vk = Masks::validate_field_keys(array('mask_preset' => 'ir.legal_id', 'mask_store' => 'canonical', 'inputmode' => 'auto'));
efbm_check('validate_field_keys warns on unverified presets', $vk['errors'] === array() && $vk['warnings'] === array(array('key' => 'mask_preset', 'code' => 'unverified')));
efbm_check('validate_field_keys accepts an unmasked row', Masks::validate_field_keys(array('name' => 'x')) === array('errors' => array(), 'warnings' => array()));

// message()
$lan = array('mskFormat_' => 'F:%1$s|%2$s', 'mskChecksum_' => 'C:%s', 'mskRule_' => 'R:%s', 'mskIncomplete_' => 'I:%s');
efbm_check('message format uses the field placeholder', Masks::message('format', array('name' => 'M', 'placeholder' => 'ex', 'mask_preset' => 'ir.mobile'), $lan) === 'F:M|ex');
efbm_check('message format falls back to the catalog example', Masks::message('format', array('name' => 'M', 'mask_preset' => 'ir.mobile'), $lan) === 'F:M|0912 345 6789');
efbm_check('message checksum / rule / empty', Masks::message('checksum', array('name' => 'N'), $lan) === 'C:N' && Masks::message('rule', array('name' => 'N'), $lan) === 'R:N' && Masks::message('empty', array('name' => 'N'), $lan) === 'I:N');
efbm_check('message English fallback', Masks::message('checksum', array('name' => 'N'), array()) === 'N is not valid. Please check the digits for typos.');

echo "\n========================================\n";
echo "RESULTS: $pass passed, $fail failed (catalog vectors failing: " . ($counts['valid'][1] + $counts['invalid'][1] + $counts['format'][1] + $counts['invalid_strict'][1]) . ")\n";
echo "========================================\n";
exit($fail > 0 ? 1 : 0);
