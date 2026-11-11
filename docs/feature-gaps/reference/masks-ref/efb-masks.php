<?php
/**
 * Easy Form Builder - input masks, normalizers and validators (reference implementation).
 * PHP 7.4+ (no PHP 8 syntax), needs only PCRE with UTF-8 support (always on in WordPress).
 * Line-for-line twin of efb-masks.js; both are tested against masks-catalog.json.
 * In the plugin, wrap these in a class (e.g. Emsfb_Masks) instead of global functions.
 */

// #region normalize
function efbm_normalize_digits($s) {
	$map = array();
	for ($i = 0; $i < 10; $i++) {
		$map[efbm_chr(0x06F0 + $i)] = (string) $i; // Persian
		$map[efbm_chr(0x0660 + $i)] = (string) $i; // Arabic-Indic
		$map[efbm_chr(0xFF10 + $i)] = (string) $i; // full-width
	}
	return strtr($s, $map);
}

function efbm_chr($cp) {
	return html_entity_decode('&#' . $cp . ';', ENT_QUOTES, 'UTF-8');
}

function efbm_ascii_upper($s) {
	return strtr($s, 'abcdefghijklmnopqrstuvwxyz', 'ABCDEFGHIJKLMNOPQRSTUVWXYZ');
}

/** efbm_normalize($raw, $mask) -> canonical string (see efb-masks.js normalize()). $mask is an assoc array. */
function efbm_normalize($raw, $mask = array()) {
	$s = (string) ($raw === null ? '' : $raw);
	$s = efbm_normalize_digits($s);
	$s = preg_replace('/[\x{200B}-\x{200F}\x{202A}-\x{202E}\x{2066}-\x{2069}\x{FEFF}\x{00AD}]/u', '', $s);
	$s = preg_replace('/[\x{00A0}\x{2007}\x{202F}\x{3000}]/u', ' ', $s);
	$s = preg_replace('/\x{066B}/u', '.', $s);
	$s = preg_replace('/[\x{066C}\x{060C}]/u', ',', $s);
	$s = preg_replace('/[\x{2010}-\x{2015}\x{2212}\x{FE58}\x{FE63}\x{FF0D}]/u', '-', $s);
	$s = preg_replace(array('/\x{FF0B}/u', '/\x{FF0F}/u', '/\x{FF1A}/u'), array('+', '/', ':'), $s);
	if (isset($mask['fold_letters']) && $mask['fold_letters'] === 'fa') {
		$s = preg_replace(array('/[\x{064A}\x{0649}]/u', '/\x{0643}/u'), array("\u{06CC}", "\u{06A9}"), $s);
	}
	$s = trim(preg_replace('/[\t\n\x0B\f\r ]+/', ' ', $s));
	if (!empty($mask['uppercase'])) {
		$s = efbm_ascii_upper($s);
	}
	if (!empty($mask['strip'])) {
		$chars = preg_split('//u', $mask['strip'], -1, PREG_SPLIT_NO_EMPTY);
		$s = str_replace($chars, '', $s);
	}
	if (!empty($mask['pad_left']) && preg_match('/^\d+$/', $s)
		&& strlen($s) >= $mask['pad_left']['min'] && strlen($s) < $mask['pad_left']['to']) {
		$s = str_pad($s, $mask['pad_left']['to'], '0', STR_PAD_LEFT);
	}
	return $s;
}
// #endregion normalize

// #region luhn
function efbm_luhn($digits) {
	if (!preg_match('/^\d+$/', $digits)) {
		return false;
	}
	$sum = 0;
	$dbl = false;
	for ($i = strlen($digits) - 1; $i >= 0; $i--) {
		$d = (int) $digits[$i];
		if ($dbl) {
			$d *= 2;
			if ($d > 9) {
				$d -= 9;
			}
		}
		$sum += $d;
		$dbl = !$dbl;
	}
	return $sum % 10 === 0;
}
// #endregion luhn

// #region iran_national_code
function efbm_iran_national_code($code) {
	if (!preg_match('/^\d{10}$/', $code) || preg_match('/^(\d)\1{9}$/', $code)) {
		return false;
	}
	if (substr($code, 3, 6) === '000000') {
		return false;
	}
	$sum = 0;
	for ($i = 0; $i < 9; $i++) {
		$sum += (int) $code[$i] * (10 - $i);
	}
	$r = $sum % 11;
	$c = (int) $code[9];
	return $r < 2 ? $c === $r : $c === 11 - $r;
}
// #endregion iran_national_code

// #region iran_legal_id
function efbm_iran_legal_id($code) {
	if (!preg_match('/^\d{11}$/', $code) || preg_match('/^(\d)\1{10}$/', $code)) {
		return false;
	}
	if (substr($code, 3, 6) === '000000') {
		return false;
	}
	$weights = array(29, 27, 23, 19, 17);
	$add = (int) $code[9] + 2;
	$sum = 0;
	for ($i = 0; $i < 10; $i++) {
		$sum += ((int) $code[$i] + $add) * $weights[$i % 5];
	}
	$r = $sum % 11;
	if ($r === 10) {
		$r = 0;
	}
	return $r === (int) $code[10];
}
// #endregion iran_legal_id

// #region verhoeff
function efbm_verhoeff($digits) {
	static $d = array(
		array(0, 1, 2, 3, 4, 5, 6, 7, 8, 9), array(1, 2, 3, 4, 0, 6, 7, 8, 9, 5), array(2, 3, 4, 0, 1, 7, 8, 9, 5, 6),
		array(3, 4, 0, 1, 2, 8, 9, 5, 6, 7), array(4, 0, 1, 2, 3, 9, 5, 6, 7, 8), array(5, 9, 8, 7, 6, 0, 4, 3, 2, 1),
		array(6, 5, 9, 8, 7, 1, 0, 4, 3, 2), array(7, 6, 5, 9, 8, 2, 1, 0, 4, 3), array(8, 7, 6, 5, 9, 3, 2, 1, 0, 4),
		array(9, 8, 7, 6, 5, 4, 3, 2, 1, 0));
	static $p = array(
		array(0, 1, 2, 3, 4, 5, 6, 7, 8, 9), array(1, 5, 7, 6, 2, 8, 3, 0, 9, 4), array(5, 8, 0, 3, 7, 9, 6, 1, 4, 2),
		array(8, 9, 1, 6, 0, 4, 3, 5, 2, 7), array(9, 4, 5, 3, 1, 2, 6, 8, 7, 0), array(4, 2, 8, 6, 5, 7, 3, 9, 0, 1),
		array(2, 7, 9, 3, 8, 0, 6, 4, 1, 5), array(7, 0, 4, 6, 9, 1, 3, 2, 5, 8));
	if (!preg_match('/^\d+$/', $digits)) {
		return false;
	}
	$c = 0;
	$n = strlen($digits);
	for ($i = 0; $i < $n; $i++) {
		$c = $d[$c][$p[$i % 8][(int) $digits[$n - 1 - $i]]];
	}
	return $c === 0;
}
// #endregion verhoeff

// #region iban
function efbm_iban_lengths() {
	return array(
		'AD' => 24, 'AE' => 23, 'AL' => 28, 'AT' => 20, 'AZ' => 28, 'BA' => 20, 'BE' => 16, 'BG' => 22, 'BH' => 22,
		'BI' => 27, 'BR' => 29, 'BY' => 28, 'CH' => 21, 'CR' => 22, 'CY' => 28, 'CZ' => 24, 'DE' => 22, 'DJ' => 27,
		'DK' => 18, 'DO' => 28, 'EE' => 20, 'EG' => 29, 'ES' => 24, 'FI' => 18, 'FK' => 18, 'FO' => 18, 'FR' => 27,
		'GB' => 22, 'GE' => 22, 'GI' => 23, 'GL' => 18, 'GR' => 27, 'GT' => 28, 'HN' => 28, 'HR' => 21, 'HU' => 28,
		'IE' => 22, 'IL' => 23, 'IQ' => 23, 'IS' => 26, 'IT' => 27, 'JO' => 30, 'KW' => 30, 'KZ' => 20, 'LB' => 28,
		'LC' => 32, 'LI' => 21, 'LT' => 20, 'LU' => 20, 'LV' => 21, 'LY' => 25, 'MC' => 27, 'MD' => 24, 'ME' => 22,
		'MK' => 19, 'MN' => 20, 'MR' => 27, 'MT' => 31, 'MU' => 30, 'NI' => 28, 'NL' => 18, 'NO' => 15, 'OM' => 23,
		'PK' => 24, 'PL' => 28, 'PS' => 29, 'PT' => 25, 'QA' => 29, 'RO' => 24, 'RS' => 22, 'RU' => 33, 'SA' => 24,
		'SC' => 31, 'SD' => 18, 'SE' => 24, 'SI' => 19, 'SK' => 24, 'SM' => 27, 'SO' => 23, 'ST' => 25, 'SV' => 28,
		'TL' => 23, 'TN' => 24, 'TR' => 26, 'UA' => 29, 'VA' => 22, 'VG' => 24, 'XK' => 20, 'YE' => 30,
		// national use only (not in the SWIFT registry)
		'IR' => 26, 'DZ' => 26, 'MA' => 28, 'AO' => 25, 'BJ' => 28, 'BF' => 28, 'CV' => 25, 'CM' => 27, 'CF' => 27,
		'TD' => 27, 'KM' => 27, 'CG' => 27, 'CI' => 28, 'GQ' => 27, 'GA' => 27, 'GW' => 25, 'MG' => 27, 'ML' => 28,
		'MZ' => 25, 'NE' => 28, 'SN' => 28, 'TG' => 28,
	);
}

function efbm_mod97($numeric) {
	$rem = 0;
	$len = strlen($numeric);
	for ($i = 0; $i < $len; $i += 7) {
		$rem = (int) ((string) $rem . substr($numeric, $i, 7)) % 97;
	}
	return $rem;
}

function efbm_iban_to_numeric($iban) {
	$r = substr($iban, 4) . substr($iban, 0, 4);
	$out = '';
	$len = strlen($r);
	for ($i = 0; $i < $len; $i++) {
		$c = ord($r[$i]);
		$out .= ($c >= 65 && $c <= 90) ? (string) ($c - 55) : $r[$i];
	}
	return $out;
}

function efbm_iban($value) {
	if (!preg_match('/^[A-Z]{2}\d{2}[A-Z0-9]{11,30}$/', $value)) {
		return false;
	}
	$lengths = efbm_iban_lengths();
	$cc = substr($value, 0, 2);
	if (!isset($lengths[$cc]) || strlen($value) !== $lengths[$cc]) {
		return false;
	}
	return efbm_mod97(efbm_iban_to_numeric($value)) === 1;
}
// #endregion iban

// #region saudi_nin
function efbm_saudi_nin($id) {
	return (bool) preg_match('/^[12]\d{9}$/', $id) && efbm_luhn($id);
}
// #endregion saudi_nin

// #region emirates_id
function efbm_emirates_id($id) {
	return (bool) preg_match('/^784\d{12}$/', $id) && efbm_luhn($id);
}
// #endregion emirates_id

// #region kuwait_civil_id
function efbm_is_valid_gregorian($y, $m, $d) {
	return $m >= 1 && $m <= 12 && $d >= 1 && checkdate($m, $d, $y);
}

function efbm_kuwait_civil_id($id) {
	if (!preg_match('/^[123]\d{11}$/', $id)) {
		return false;
	}
	$centuries = array('1' => 1800, '2' => 1900, '3' => 2000);
	$century = $centuries[$id[0]];
	if (!efbm_is_valid_gregorian($century + (int) substr($id, 1, 2), (int) substr($id, 3, 2), (int) substr($id, 5, 2))) {
		return false;
	}
	$w = array(2, 1, 6, 3, 7, 9, 10, 5, 8, 4, 2);
	$sum = 0;
	for ($i = 0; $i < 11; $i++) {
		$sum += $w[$i] * (int) $id[$i];
	}
	return 11 - ($sum % 11) === (int) $id[11];
}
// #endregion kuwait_civil_id

// #region egypt_nid
function efbm_egypt_nid($id) {
	static $gov = array('01', '02', '03', '04', '11', '12', '13', '14', '15', '16', '17', '18', '19', '21', '22',
		'23', '24', '25', '26', '27', '28', '29', '31', '32', '33', '34', '35', '88');
	if (!preg_match('/^[23]\d{13}$/', $id)) {
		return false;
	}
	$year = ($id[0] === '2' ? 1900 : 2000) + (int) substr($id, 1, 2);
	if (!efbm_is_valid_gregorian($year, (int) substr($id, 3, 2), (int) substr($id, 5, 2))) {
		return false;
	}
	return in_array(substr($id, 7, 2), $gov, true);
}
// #endregion egypt_nid

// #region au_abn
function efbm_au_abn($abn) {
	if (!preg_match('/^\d{11}$/', $abn)) {
		return false;
	}
	$w = array(10, 1, 3, 5, 7, 9, 11, 13, 15, 17, 19);
	$sum = 0;
	for ($i = 0; $i < 11; $i++) {
		$d = (int) $abn[$i];
		if ($i === 0) {
			$d -= 1;
		}
		$sum += $d * $w[$i];
	}
	return $sum % 89 === 0;
}
// #endregion au_abn

// #region uk_nino
function efbm_uk_nino($v) {
	if (!preg_match('/^([A-Z])([A-Z])\d{6}[A-D]$/', $v, $m)) {
		return false;
	}
	if (strpos('DFIQUV', $m[1]) !== false || strpos('DFIOQUV', $m[2]) !== false) {
		return false;
	}
	return !in_array($m[1] . $m[2], array('BG', 'GB', 'KN', 'NK', 'NT', 'TN', 'ZZ'), true);
}
// #endregion uk_nino

// #region us_ssn
function efbm_us_ssn($v) {
	if (!preg_match('/^\d{9}$/', $v)) {
		return false;
	}
	$area = substr($v, 0, 3);
	if ($area === '000' || $area === '666' || $area[0] === '9') {
		return false;
	}
	return substr($v, 3, 2) !== '00' && substr($v, 5, 4) !== '0000';
}

function efbm_us_ein($v) {
	static $prefixes = '01 02 03 04 05 06 10 11 12 13 14 15 16 20 21 22 23 24 25 26 27 30 31 32 33 34 35 36 37 38 39 40 41 42 43 44 45 46 47 48 50 51 52 53 54 55 56 57 58 59 60 61 62 63 64 65 66 67 68 71 72 73 74 75 76 77 80 81 82 83 84 85 86 87 88 90 91 92 93 94 95 98 99';
	return (bool) preg_match('/^\d{9}$/', $v) && in_array(substr($v, 0, 2), explode(' ', $prefixes), true);
}

function efbm_nanp($v) {
	$d = preg_replace('/^\+?1(?=\d{10}$)/', '', $v);
	if (!preg_match('/^[2-9]\d{2}[2-9]\d{6}$/', $d)) {
		return false;
	}
	return substr($d, 1, 2) !== '11' && substr($d, 4, 2) !== '11';
}
// #endregion us_ssn

// #region card_brand
function efbm_card_brands() {
	return array(
		array('brand' => 'amex', 're' => '/^3[47]/', 'lengths' => array(15), 'groups' => array(4, 6, 5), 'cvc' => 4),
		array('brand' => 'diners', 're' => '/^3(?:0[0-5]|095|[689])/', 'lengths' => array(14, 15, 16, 17, 18, 19), 'groups' => array(4, 6, 4), 'cvc' => 3),
		array('brand' => 'jcb', 're' => '/^35(?:2[89]|[3-8]\d)/', 'lengths' => array(16, 17, 18, 19), 'groups' => array(4, 4, 4, 4, 3), 'cvc' => 3),
		array('brand' => 'mir', 're' => '/^220[0-4]/', 'lengths' => array(16, 17, 18, 19), 'groups' => array(4, 4, 4, 4, 3), 'cvc' => 3),
		array('brand' => 'mastercard', 're' => '/^(?:5[1-5]|222[1-9]|22[3-9]\d|2[3-6]\d\d|27[01]\d|2720)/', 'lengths' => array(16), 'groups' => array(4, 4, 4, 4), 'cvc' => 3),
		array('brand' => 'discover', 're' => '/^(?:6011|64[4-9]|65)/', 'lengths' => array(16, 17, 18, 19), 'groups' => array(4, 4, 4, 4, 3), 'cvc' => 3),
		array('brand' => 'unionpay', 're' => '/^62/', 'lengths' => array(16, 17, 18, 19), 'groups' => array(4, 4, 4, 4, 3), 'cvc' => 3),
		array('brand' => 'maestro', 're' => '/^(?:5[06-9]|6[37])/', 'lengths' => array(12, 13, 14, 15, 16, 17, 18, 19), 'groups' => array(4, 4, 4, 4, 3), 'cvc' => 3),
		array('brand' => 'visa', 're' => '/^4/', 'lengths' => array(13, 16, 19), 'groups' => array(4, 4, 4, 4, 3), 'cvc' => 3),
	);
}

function efbm_card_brand_info($pan) {
	foreach (efbm_card_brands() as $b) {
		if (preg_match($b['re'], $pan)) {
			return $b;
		}
	}
	return null;
}

function efbm_card_brand($pan) {
	$b = efbm_card_brand_info($pan);
	return $b ? $b['brand'] : null;
}

function efbm_card($pan) {
	if (!preg_match('/^\d{12,19}$/', $pan) || !efbm_luhn($pan)) {
		return false;
	}
	$b = efbm_card_brand_info($pan);
	return $b ? in_array(strlen($pan), $b['lengths'], true) : true;
}

function efbm_format_card($pan) {
	$b = efbm_card_brand_info($pan);
	$groups = $b ? $b['groups'] : array(4, 4, 4, 4, 3);
	$out = array();
	$pos = 0;
	$len = strlen($pan);
	foreach ($groups as $g) {
		if ($pos >= $len) {
			break;
		}
		$out[] = substr($pan, $pos, $g);
		$pos += $g;
	}
	if ($pos < $len) {
		$out[] = substr($pan, $pos);
	}
	return implode(' ', $out);
}
// #endregion card_brand

// #region jalali
function efbm_jalali_is_leap($jy) {
	static $breaks = array(-61, 9, 38, 199, 426, 686, 756, 818, 1111, 1181, 1210, 1635, 2060, 2097, 2192, 2262,
		2324, 2394, 2456, 3178);
	$jp = $breaks[0];
	$jump = 0;
	$count = count($breaks);
	for ($i = 1; $i < $count; $i++) {
		$jm = $breaks[$i];
		$jump = $jm - $jp;
		if ($jy < $jm) {
			break;
		}
		$jp = $jm;
	}
	$n = $jy - $jp;
	if ($jump - $n < 6) {
		$n = $n - $jump + intdiv($jump + 4, 33) * 33;
	}
	$leap = ((($n + 1) % 33) - 1) % 4; // PHP % truncates like JS
	if ($leap === -1) {
		$leap = 4;
	}
	return $leap === 0;
}

function efbm_jalali_month_length($y, $m) {
	if ($m <= 6) {
		return 31;
	}
	if ($m <= 11) {
		return 30;
	}
	return efbm_jalali_is_leap($y) ? 30 : 29;
}
// #endregion jalali

// #region dates
function efbm_valid_date($v, $mask) {
	$p = preg_split('/\D+/', $v);
	if (count($p) !== 3) {
		return false;
	}
	$order = isset($mask['date_order']) ? $mask['date_order'] : 'YMD';
	$y = (int) $p[strpos($order, 'Y')];
	$m = (int) $p[strpos($order, 'M')];
	$d = (int) $p[strpos($order, 'D')];
	$yr = isset($mask['year_range']) ? $mask['year_range'] : array(1, 9999);
	if ($y < $yr[0] || $y > $yr[1] || $m < 1 || $m > 12 || $d < 1) {
		return false;
	}
	$cal = isset($mask['calendar']) ? $mask['calendar'] : 'gregorian';
	if ($cal === 'jalali') {
		return $d <= efbm_jalali_month_length($y, $m);
	}
	if ($cal === 'hijri') {
		return $d <= 30;
	}
	return efbm_is_valid_gregorian($y, $m, $d);
}

/** MM/YY; pass $now (DateTimeInterface) to also reject expired cards. */
function efbm_card_expiry($v, $mask = array(), $now = null) {
	if (!preg_match('/^(\d{2})\/(\d{2})$/', $v, $m)) {
		return false;
	}
	$mm = (int) $m[1];
	$yy = 2000 + (int) $m[2];
	if ($mm < 1 || $mm > 12) {
		return false;
	}
	if ($now) {
		$ny = (int) $now->format('Y');
		return $yy > $ny || ($yy === $ny && $mm >= (int) $now->format('n'));
	}
	return true;
}
// #endregion dates

// #region dispatch
function efbm_validate($mask, $raw) {
	static $checksums = array(
		'luhn' => 'efbm_luhn', 'verhoeff' => 'efbm_verhoeff', 'iso7064_mod97_10' => 'efbm_iban',
		'iran_national_code' => 'efbm_iran_national_code', 'iran_legal_id' => 'efbm_iran_legal_id',
		'saudi_nin' => 'efbm_saudi_nin', 'emirates_id' => 'efbm_emirates_id',
		'kuwait_civil_id' => 'efbm_kuwait_civil_id', 'au_abn' => 'efbm_au_abn', 'card' => 'efbm_card',
	);
	static $validators = array(
		'date' => 'efbm_valid_date', 'card_expiry' => 'efbm_card_expiry', 'egypt_nid' => 'efbm_egypt_nid',
		'uk_nino' => 'efbm_uk_nino', 'us_ssn' => 'efbm_us_ssn', 'us_ein' => 'efbm_us_ein', 'nanp' => 'efbm_nanp',
	);
	$v = efbm_normalize($raw, $mask);
	if ($v === '') {
		return array('ok' => false, 'code' => 'empty', 'value' => $v);
	}
	// Catalog regexes never contain "~"; /u makes . and classes code-point aware like the JS "u" flag.
	if (!preg_match('~' . $mask['regex'] . '~u', $v)) {
		return array('ok' => false, 'code' => 'format', 'value' => $v);
	}
	if (!empty($mask['checksum'])) {
		if (!isset($checksums[$mask['checksum']])) {
			throw new InvalidArgumentException('Unknown checksum ' . $mask['checksum']);
		}
		if (!call_user_func($checksums[$mask['checksum']], $v)) {
			return array('ok' => false, 'code' => 'checksum', 'value' => $v);
		}
	}
	if (!empty($mask['validator'])) {
		if (!isset($validators[$mask['validator']])) {
			throw new InvalidArgumentException('Unknown validator ' . $mask['validator']);
		}
		if (!call_user_func($validators[$mask['validator']], $v, $mask)) {
			return array('ok' => false, 'code' => 'rule', 'value' => $v);
		}
	}
	return array('ok' => true, 'code' => null, 'value' => $v);
}
// #endregion dispatch

// #region store
function efbm_to_stored($mask, $v) {
	$store = isset($mask['store']) ? $mask['store'] : null;
	if ($store === 'e164' && !empty($mask['e164'])) {
		$cc = $mask['e164']['cc'];
		$trunk = isset($mask['e164']['trunk']) ? $mask['e164']['trunk'] : '';
		$nsn = isset($mask['e164']['nsn_length']) ? $mask['e164']['nsn_length'] : array();
		if (substr($v, 0, 1) === '+') {
			return $v;
		}
		if (substr($v, 0, 2) === '00') {
			return '+' . substr($v, 2);
		}
		if ($trunk !== '' && substr($v, 0, strlen($trunk)) === $trunk) {
			return '+' . $cc . substr($v, strlen($trunk));
		}
		if (in_array(strlen($v), $nsn, true)) {
			return '+' . $cc . $v;
		}
		if (substr($v, 0, strlen($cc)) === $cc) {
			return '+' . $v;
		}
		return $v;
	}
	if ($store === 'digits') {
		return preg_replace('/\D+/', '', $v);
	}
	if ($store === 'strip_grouping') {
		return str_replace(',', '', $v);
	}
	return $v;
}
// #endregion store

// #region mask_engine
function efbm_compile_mask($pattern) {
	$chars = preg_split('//u', $pattern, -1, PREG_SPLIT_NO_EMPTY);
	$tokens = array();
	$optional = 0;
	$n = count($chars);
	for ($i = 0; $i < $n; $i++) {
		$ch = $chars[$i];
		if ($ch === '\\' && $i + 1 < $n) {
			$tokens[] = array('k' => 'lit', 'ch' => $chars[++$i], 'opt' => $optional > 0);
			continue;
		}
		if ($ch === '[') {
			$optional++;
			continue;
		}
		if ($ch === ']') {
			$optional = max(0, $optional - 1);
			continue;
		}
		if ($ch === '{') {
			$spec = '';
			while (++$i < $n && $chars[$i] !== '}') {
				$spec .= $chars[$i];
			}
			$parts = explode(',', $spec);
			$min = (int) $parts[0];
			$max = count($parts) > 1 ? (int) $parts[1] : $min;
			$prev = array_pop($tokens);
			for ($r = 0; $r < $max; $r++) {
				$tokens[] = array('k' => $prev['k'], 'ch' => $prev['ch'], 'opt' => $prev['opt'] || $r >= $min);
			}
			continue;
		}
		$kinds = array('9' => 'digit', 'a' => 'letter', '*' => 'alnum');
		$tokens[] = isset($kinds[$ch])
			? array('k' => $kinds[$ch], 'ch' => null, 'opt' => $optional > 0)
			: array('k' => 'lit', 'ch' => $ch, 'opt' => $optional > 0);
	}
	return $tokens;
}

function efbm_is_letter($c) {
	return (bool) preg_match('/^[A-Za-z\x{0621}-\x{063A}\x{0641}-\x{064A}\x{066E}-\x{06D3}\x{06FA}-\x{06FC}]$/u', $c);
}

function efbm_slot_accepts($k, $c) {
	$digit = (bool) preg_match('/^[0-9]$/', $c);
	if ($k === 'digit') {
		return $digit;
	}
	if ($k === 'letter') {
		return efbm_is_letter($c);
	}
	return $digit || efbm_is_letter($c);
}

function efbm_apply_mask($pattern, $input) {
	$tokens = efbm_compile_mask($pattern);
	$s = preg_split('//u', efbm_normalize($input, array()), -1, PREG_SPLIT_NO_EMPTY);
	$out = '';
	$pending = array();
	$i = 0;
	$ns = count($s);
	$nt = count($tokens);
	for ($t = 0; $t < $nt && $i < $ns; $t++) {
		$tok = $tokens[$t];
		if ($tok['k'] === 'lit') {
			$pending[] = $tok;
			if ($s[$i] === $tok['ch']) {
				$i++;
			}
			continue;
		}
		while ($i < $ns && !efbm_slot_accepts('alnum', $s[$i])) {
			$i++;
		}
		if ($i >= $ns) {
			break;
		}
		if (efbm_slot_accepts($tok['k'], $s[$i])) {
			foreach ($pending as $p) {
				$out .= $p['ch'];
			}
			$pending = array();
			$out .= $s[$i];
			$i++;
		} elseif ($tok['opt']) {
			$pending = array_values(array_filter($pending, function ($x) {
				return !$x['opt'];
			}));
		} else {
			$i++;
			$t--;
		}
	}
	return $out;
}

function efbm_format_thousands($v, $sep = ',') {
	$s = preg_replace('/[^0-9.\-]/', '', efbm_normalize($v, array()));
	$neg = substr($s, 0, 1) === '-' ? '-' : '';
	$s = str_replace('-', '', $s);
	$dot = strpos($s, '.');
	$int = $dot === false ? $s : substr($s, 0, $dot);
	$dec = $dot === false ? '' : '.' . str_replace('.', '', substr($s, $dot + 1));
	$int = preg_replace('/^0+(?=\d)/', '', $int);
	$out = '';
	for ($i = strlen($int); $i > 0; $i -= 3) {
		$start = max(0, $i - 3);
		$out = substr($int, $start, $i - $start) . ($out !== '' ? $sep . $out : '');
	}
	return $neg . $out . $dec;
}

function efbm_format_value($mask, $input) {
	$s = !empty($mask['uppercase']) ? efbm_ascii_upper(efbm_normalize($input, array())) : $input;
	$formatter = isset($mask['formatter']) ? $mask['formatter'] : null;
	if ($formatter === 'thousands') {
		return efbm_format_thousands($s);
	}
	if ($formatter === 'card') {
		return efbm_format_card(substr(preg_replace('/\D/', '', efbm_normalize($s, array('strip' => ' -'))), 0, 19));
	}
	if (!empty($mask['mask'])) {
		return efbm_apply_mask($mask['mask'], $s);
	}
	return efbm_normalize($s, array());
}
// #endregion mask_engine
