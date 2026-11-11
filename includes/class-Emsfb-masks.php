<?php
/**
 * Easy Form Builder - input masks, normalizers and validators (F01).
 *
 * Ported from docs/feature-gaps/reference/masks-ref/efb-masks.php (each efbm_* function is a static method
 * here). The JS twin is public/assets/js/masks-efb.js; both run the catalog vectors in
 * includes/data/masks-catalog-efb.json (tests/test-masks-vectors.php|js, tests/test-masks-parity.js).
 * PHP 7.4 compatible. Regexes come only from the catalog file shipped with the plugin, never from a request.
 *
 * Extension points (stored form keys stay stable):
 * - new presets: add catalog entries (or filter 'efb_mask_presets'); forms store only the preset id.
 * - new checksum / rule: add a method and list it in validate() (and the JS CHECKSUMS / VALIDATORS).
 * - new formatter (numeric/currency with group separators, ...): a branch in format_value() keyed by 'formatter'.
 * - new storage shape: a branch in to_stored() keyed by 'store'.
 * - per-country dynamic phone: a new 'formatter' or 'validator' name; the field keeps mask_preset as is.
 *
 * Connector API (SitePilot AI and other callers that do not go through the builder's AJAX nonce):
 * - Masks::catalog_public($locale)   machine-readable preset list, filter 'efb_mask_catalog'.
 * - Masks::check_value($mask, $v)    validate one value outside a form submit -> {ok, code, stored}.
 * - Field keys (flat scalars on a form_structer row): mask_preset, mask_custom, mask_checksum, mask_store,
 *   mask_strict, inputmode. Values outside the allow-lists are reset by sanitize_obj_msg_efb() on save.
 */

namespace Emsfb;

if (!defined('ABSPATH')) {
	exit;
}

class Masks {

	/** Longest input (code points) the validators look at; longer values fail with 'format'. */
	const MAX_INPUT = 256;

	/** Longest custom mask accepted by the builder and the save sanitizer. */
	const MAX_CUSTOM_MASK = 64;

	/** Checksums an admin may attach to a custom mask (mask_checksum allow-list). */
	const CUSTOM_CHECKSUMS = array('luhn', 'iso7064_mod97_10', 'iran_national_code', 'verhoeff');

	/** Catalog fields sent to the browser for one preset (no examples, metadata, notes or sources). */
	const RUNTIME_FIELDS = array('id', 'category', 'mask', 'formatter', 'regex', 'regex_strict', 'checksum', 'validator',
		'calendar', 'date_order', 'year_range', 'strip', 'uppercase', 'fold_letters', 'pad_left', 'store', 'e164',
		'inputmode', 'dir');

	/** Allowed values of the per-field inputmode key ('auto' = from the preset, else no attribute). */
	const INPUTMODES = array('auto', 'text', 'numeric', 'decimal', 'tel', 'email', 'url', 'none');

	/** Preset categories whose values are not kept in the visitor's localStorage draft. */
	const SENSITIVE_CATEGORIES = array('national_id', 'bank_card', 'iban', 'tax_id', 'passport');

	private static $catalog = null;
	private static $index = null;
	private static $logged = array();

	/** Number of code points in a UTF-8 string. */
	public static function length($s) {
		$n = preg_match_all('/./us', (string) $s);
		return $n === false ? strlen((string) $s) : $n;
	}

	// #region normalize
	public static function normalize_digits($s) {
		$map = array();
		for ($i = 0; $i < 10; $i++) {
			$map[self::uchr(0x06F0 + $i)] = (string) $i; // Persian
			$map[self::uchr(0x0660 + $i)] = (string) $i; // Arabic-Indic
			$map[self::uchr(0xFF10 + $i)] = (string) $i; // full-width
		}
		return strtr($s, $map);
	}

	public static function uchr($cp) {
		return html_entity_decode('&#' . $cp . ';', ENT_QUOTES, 'UTF-8');
	}

	public static function ascii_upper($s) {
		return strtr($s, 'abcdefghijklmnopqrstuvwxyz', 'ABCDEFGHIJKLMNOPQRSTUVWXYZ');
	}

	/** self::normalize($raw, $mask) -> canonical string (see efb-masks.js normalize()). $mask is an assoc array. */
	public static function normalize($raw, $mask = array()) {
		$s = (string) ($raw === null ? '' : $raw);
		$s = self::normalize_digits($s);
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
			$s = self::ascii_upper($s);
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
	public static function luhn($digits) {
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
	public static function iran_national_code($code) {
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
	public static function iran_legal_id($code) {
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
	public static function verhoeff($digits) {
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
	public static function iban_lengths() {
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

	public static function mod97($numeric) {
		$rem = 0;
		$len = strlen($numeric);
		for ($i = 0; $i < $len; $i += 7) {
			$rem = (int) ((string) $rem . substr($numeric, $i, 7)) % 97;
		}
		return $rem;
	}

	public static function iban_to_numeric($iban) {
		$r = substr($iban, 4) . substr($iban, 0, 4);
		$out = '';
		$len = strlen($r);
		for ($i = 0; $i < $len; $i++) {
			$c = ord($r[$i]);
			$out .= ($c >= 65 && $c <= 90) ? (string) ($c - 55) : $r[$i];
		}
		return $out;
	}

	public static function iban($value) {
		if (!preg_match('/^[A-Z]{2}\d{2}[A-Z0-9]{11,30}$/', $value)) {
			return false;
		}
		$lengths = self::iban_lengths();
		$cc = substr($value, 0, 2);
		if (!isset($lengths[$cc]) || strlen($value) !== $lengths[$cc]) {
			return false;
		}
		return self::mod97(self::iban_to_numeric($value)) === 1;
	}
	// #endregion iban

	// #region saudi_nin
	public static function saudi_nin($id) {
		return (bool) preg_match('/^[12]\d{9}$/', $id) && self::luhn($id);
	}
	// #endregion saudi_nin

	// #region emirates_id
	public static function emirates_id($id) {
		return (bool) preg_match('/^784\d{12}$/', $id) && self::luhn($id);
	}
	// #endregion emirates_id

	// #region kuwait_civil_id
	public static function is_valid_gregorian($y, $m, $d) {
		return $m >= 1 && $m <= 12 && $d >= 1 && checkdate($m, $d, $y);
	}

	public static function kuwait_civil_id($id) {
		if (!preg_match('/^[123]\d{11}$/', $id)) {
			return false;
		}
		$centuries = array('1' => 1800, '2' => 1900, '3' => 2000);
		$century = $centuries[$id[0]];
		if (!self::is_valid_gregorian($century + (int) substr($id, 1, 2), (int) substr($id, 3, 2), (int) substr($id, 5, 2))) {
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
	public static function egypt_nid($id) {
		static $gov = array('01', '02', '03', '04', '11', '12', '13', '14', '15', '16', '17', '18', '19', '21', '22',
			'23', '24', '25', '26', '27', '28', '29', '31', '32', '33', '34', '35', '88');
		if (!preg_match('/^[23]\d{13}$/', $id)) {
			return false;
		}
		$year = ($id[0] === '2' ? 1900 : 2000) + (int) substr($id, 1, 2);
		if (!self::is_valid_gregorian($year, (int) substr($id, 3, 2), (int) substr($id, 5, 2))) {
			return false;
		}
		return in_array(substr($id, 7, 2), $gov, true);
	}
	// #endregion egypt_nid

	// #region au_abn
	public static function au_abn($abn) {
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
	public static function uk_nino($v) {
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
	public static function us_ssn($v) {
		if (!preg_match('/^\d{9}$/', $v)) {
			return false;
		}
		$area = substr($v, 0, 3);
		if ($area === '000' || $area === '666' || $area[0] === '9') {
			return false;
		}
		return substr($v, 3, 2) !== '00' && substr($v, 5, 4) !== '0000';
	}

	public static function us_ein($v) {
		static $prefixes = '01 02 03 04 05 06 10 11 12 13 14 15 16 20 21 22 23 24 25 26 27 30 31 32 33 34 35 36 37 38 39 40 41 42 43 44 45 46 47 48 50 51 52 53 54 55 56 57 58 59 60 61 62 63 64 65 66 67 68 71 72 73 74 75 76 77 80 81 82 83 84 85 86 87 88 90 91 92 93 94 95 98 99';
		return (bool) preg_match('/^\d{9}$/', $v) && in_array(substr($v, 0, 2), explode(' ', $prefixes), true);
	}

	public static function nanp($v) {
		$d = preg_replace('/^\+?1(?=\d{10}$)/', '', $v);
		if (!preg_match('/^[2-9]\d{2}[2-9]\d{6}$/', $d)) {
			return false;
		}
		return substr($d, 1, 2) !== '11' && substr($d, 4, 2) !== '11';
	}
	// #endregion us_ssn

	// #region card_brand
	public static function card_brands() {
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

	public static function card_brand_info($pan) {
		foreach (self::card_brands() as $b) {
			if (preg_match($b['re'], $pan)) {
				return $b;
			}
		}
		return null;
	}

	public static function card_brand($pan) {
		$b = self::card_brand_info($pan);
		return $b ? $b['brand'] : null;
	}

	public static function card($pan) {
		if (!preg_match('/^\d{12,19}$/', $pan) || !self::luhn($pan)) {
			return false;
		}
		$b = self::card_brand_info($pan);
		return $b ? in_array(strlen($pan), $b['lengths'], true) : true;
	}

	public static function format_card($pan) {
		$b = self::card_brand_info($pan);
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
	public static function jalali_is_leap($jy) {
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

	public static function jalali_month_length($y, $m) {
		if ($m <= 6) {
			return 31;
		}
		if ($m <= 11) {
			return 30;
		}
		return self::jalali_is_leap($y) ? 30 : 29;
	}
	// #endregion jalali

	// #region dates
	public static function valid_date($v, $mask) {
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
			return $d <= self::jalali_month_length($y, $m);
		}
		if ($cal === 'hijri') {
			return $d <= 30;
		}
		return self::is_valid_gregorian($y, $m, $d);
	}

	/** MM/YY; pass $now (DateTimeInterface) to also reject expired cards. */
	public static function card_expiry($v, $mask = array(), $now = null) {
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
	public static function validate($mask, $raw) {
		static $checksums = array(
			'luhn' => 'luhn', 'verhoeff' => 'verhoeff', 'iso7064_mod97_10' => 'iban',
			'iran_national_code' => 'iran_national_code', 'iran_legal_id' => 'iran_legal_id',
			'saudi_nin' => 'saudi_nin', 'emirates_id' => 'emirates_id',
			'kuwait_civil_id' => 'kuwait_civil_id', 'au_abn' => 'au_abn', 'card' => 'card',
		);
		static $validators = array(
			'date' => 'valid_date', 'card_expiry' => 'card_expiry', 'egypt_nid' => 'egypt_nid',
			'uk_nino' => 'uk_nino', 'us_ssn' => 'us_ssn', 'us_ein' => 'us_ein', 'nanp' => 'nanp',
		);
		$v = self::normalize($raw, $mask);
		if ($v === '') {
			return array('ok' => false, 'code' => 'empty', 'value' => $v);
		}
		if (self::length($v) > self::MAX_INPUT) { // F01 §11: cap before the regex runs
			return array('ok' => false, 'code' => 'format', 'value' => $v);
		}
		// Catalog regexes never contain "~"; /u makes . and classes code-point aware like the JS "u" flag.
		if (!preg_match('~' . $mask['regex'] . '~u', $v)) {
			return array('ok' => false, 'code' => 'format', 'value' => $v);
		}
		if (!empty($mask['checksum'])) {
			if (!isset($checksums[$mask['checksum']])) {
				throw new \InvalidArgumentException('Unknown checksum ' . $mask['checksum']);
			}
			if (!call_user_func(array(__CLASS__, $checksums[$mask['checksum']]), $v)) {
				return array('ok' => false, 'code' => 'checksum', 'value' => $v);
			}
		}
		if (!empty($mask['validator'])) {
			if (!isset($validators[$mask['validator']])) {
				throw new \InvalidArgumentException('Unknown validator ' . $mask['validator']);
			}
			if (!call_user_func(array(__CLASS__, $validators[$mask['validator']]), $v, $mask)) {
				return array('ok' => false, 'code' => 'rule', 'value' => $v);
			}
		}
		return array('ok' => true, 'code' => null, 'value' => $v);
	}
	// #endregion dispatch

	// #region store
	public static function to_stored($mask, $v) {
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
	public static function compile_mask($pattern) {
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

	public static function is_letter($c) {
		return (bool) preg_match('/^[A-Za-z\x{0621}-\x{063A}\x{0641}-\x{064A}\x{066E}-\x{06D3}\x{06FA}-\x{06FC}]$/u', $c);
	}

	public static function slot_accepts($k, $c) {
		$digit = (bool) preg_match('/^[0-9]$/', $c);
		if ($k === 'digit') {
			return $digit;
		}
		if ($k === 'letter') {
			return self::is_letter($c);
		}
		return $digit || self::is_letter($c);
	}

	public static function run_mask($pattern, $input) {
		$tokens = self::compile_mask($pattern);
		$s = preg_split('//u', self::normalize($input, array()), -1, PREG_SPLIT_NO_EMPTY);
		$out = '';
		$pending = array();
		$i = 0;
		$t = 0;
		$dropped = 0;
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
			while ($i < $ns && !self::slot_accepts('alnum', $s[$i])) {
				$i++;
			}
			if ($i >= $ns) {
				break;
			}
			if (self::slot_accepts($tok['k'], $s[$i])) {
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
				$dropped++;
			}
		}
		for ($j = $i; $j < $ns; $j++) {
			if (self::slot_accepts('alnum', $s[$j])) {
				$dropped++; // data beyond the last slot
			}
		}
		return array('out' => $out, 'tokens' => $tokens, 'next' => $t, 'pending' => $pending, 'dropped' => $dropped);
	}

	public static function apply_mask($pattern, $input) {
		$r = self::run_mask($pattern, $input);
		return $r['out'];
	}

	public static function format_thousands($v, $sep = ',') {
		$s = preg_replace('/[^0-9.\-]/', '', self::normalize($v, array()));
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

	public static function format_value($mask, $input) {
		$s = !empty($mask['uppercase']) ? self::ascii_upper(self::normalize($input, array())) : $input;
		$formatter = isset($mask['formatter']) ? $mask['formatter'] : null;
		if ($formatter === 'thousands') {
			return self::format_thousands($s);
		}
		if ($formatter === 'card') {
			return self::format_card(substr(preg_replace('/\D/', '', self::normalize($s, array('strip' => ' -'))), 0, 19));
		}
		if (!empty($mask['mask'])) {
			return self::apply_mask($mask['mask'], $s);
		}
		return self::normalize($s, array());
	}
	// #endregion mask_engine

	// #region custom masks (F01 §9.2)
	/**
	 * Syntax check for an admin's custom mask: 1-64 characters, no control characters or < > " %,
	 * balanced [ ], every {n} / {n,m} closed and placed after a token, 1-64 tokens after expansion,
	 * at least one 9 / a / * slot.
	 */
	public static function check_mask($pattern) {
		if (!is_string($pattern) || $pattern === '' || !preg_match('//u', $pattern)) {
			return false;
		}
		if (self::length($pattern) > self::MAX_CUSTOM_MASK || preg_match('/[\x00-\x1F\x7F<>"%]/', $pattern)) {
			return false;
		}
		$chars = preg_split('//u', $pattern, -1, PREG_SPLIT_NO_EMPTY);
		$n = count($chars);
		$depth = 0;
		$count = 0;
		$slots = 0;
		$last = null; // 'slot' | 'lit' | null: what a {n,m} would repeat
		for ($i = 0; $i < $n; $i++) {
			$ch = $chars[$i];
			if ($ch === '\\') {
				if ($i + 1 >= $n) {
					return false;
				}
				$i++;
				$count++;
				$last = 'lit';
				continue;
			}
			if ($ch === '[') {
				$depth++;
				$last = null;
				continue;
			}
			if ($ch === ']') {
				if ($depth === 0) {
					return false;
				}
				$depth--;
				$last = null;
				continue;
			}
			if ($ch === '{') {
				$spec = '';
				while (++$i < $n && $chars[$i] !== '}') {
					$spec .= $chars[$i];
				}
				if ($i >= $n || $last === null || !preg_match('/^(\d{1,2})(?:,(\d{1,2}))?$/', $spec, $m)) {
					return false;
				}
				$min = (int) $m[1];
				$max = isset($m[2]) ? (int) $m[2] : $min;
				if ($max < 1 || $min > $max) {
					return false;
				}
				$count += $max - 1;
				if ($last === 'slot') {
					$slots += $max - 1;
				}
				$last = null;
				continue;
			}
			if ($ch === '}') {
				return false;
			}
			$count++;
			if ($ch === '9' || $ch === 'a' || $ch === '*') {
				$slots++;
				$last = 'slot';
			} else {
				$last = 'lit';
			}
		}
		return $depth === 0 && $slots > 0 && $count >= 1 && $count <= self::MAX_CUSTOM_MASK;
	}

	/** Required slots left after a run_mask() result: none means the value is complete. */
	private static function required_left($r) {
		$nt = count($r['tokens']);
		for ($t = $r['next']; $t < $nt; $t++) {
			if ($r['tokens'][$t]['k'] !== 'lit' && !$r['tokens'][$t]['opt']) {
				return true;
			}
		}
		return false;
	}

	/** Output plus the required literals that close the mask (literals are written lazily). */
	private static function finish_run($r) {
		$out = $r['out'];
		foreach ($r['pending'] as $p) {
			if (!$p['opt']) {
				$out .= $p['ch'];
			}
		}
		$nt = count($r['tokens']);
		for ($t = $r['next']; $t < $nt; $t++) {
			if ($r['tokens'][$t]['k'] === 'lit' && !$r['tokens'][$t]['opt']) {
				$out .= $r['tokens'][$t]['ch'];
			}
		}
		return $out;
	}

	/** apply_mask plus closing literals once every required slot is filled. */
	public static function finish_mask($pattern, $input) {
		$r = self::run_mask($pattern, $input);
		return self::required_left($r) ? $r['out'] : self::finish_run($r);
	}

	/** Every non-optional slot is filled and the value already has the mask's shape. Linear, no regex built. */
	public static function is_complete($pattern, $formatted) {
		$v = self::normalize($formatted, array());
		$r = self::run_mask($pattern, $v);
		if ($r['dropped'] > 0 || self::required_left($r)) {
			return false;
		}
		return $r['out'] === $v || self::finish_run($r) === $v;
	}

	/** Letters and digits only (what the mask engine calls data characters). */
	public static function data_only($s) {
		$out = '';
		foreach (preg_split('//u', (string) $s, -1, PREG_SPLIT_NO_EMPTY) as $c) {
			if (self::slot_accepts('alnum', $c)) {
				$out .= $c;
			}
		}
		return $out;
	}

	/** validate_custom(array('mask'=>..., 'checksum'=>...), $raw): empty -> format -> checksum. 'value' = display form. */
	public static function validate_custom($def, $raw) {
		$v = self::normalize($raw, array());
		if ($v === '') {
			return array('ok' => false, 'code' => 'empty', 'value' => $v);
		}
		if (self::length($v) > self::MAX_INPUT || empty($def['mask'])) {
			return array('ok' => false, 'code' => 'format', 'value' => $v);
		}
		$r = self::run_mask($def['mask'], $v);
		if ($r['dropped'] > 0 || self::required_left($r)) {
			return array('ok' => false, 'code' => 'format', 'value' => $r['out']);
		}
		$f = self::finish_run($r);
		if (!empty($def['checksum'])) {
			if (!in_array($def['checksum'], self::CUSTOM_CHECKSUMS, true)) {
				throw new \InvalidArgumentException('Unknown checksum ' . $def['checksum']);
			}
			$fn = $def['checksum'] === 'iso7064_mod97_10' ? 'iban' : $def['checksum'];
			if (!call_user_func(array(__CLASS__, $fn), preg_replace('/[^0-9A-Z]/', '', self::ascii_upper($f)))) {
				return array('ok' => false, 'code' => 'checksum', 'value' => $f);
			}
		}
		return array('ok' => true, 'code' => null, 'value' => $f);
	}

	/** Clean value of a custom mask: separators dropped, literal letters/digits kept. */
	public static function to_stored_custom($def, $formatted) {
		return self::data_only(self::normalize($formatted, array()));
	}
	// #endregion custom masks

	// #region display
	/**
	 * Phone presets with a national typing mask: +98 912..., 0098 912... or 98 + a full national number become
	 * trunk + national number. Returns null while the country code itself is still being typed.
	 */
	public static function to_national_display($mask, $raw) {
		$s = self::normalize($raw, array());
		if (empty($mask['e164']) || empty($mask['mask'])) {
			return $s;
		}
		$cc = (string) $mask['e164']['cc'];
		$trunk = isset($mask['e164']['trunk']) ? (string) $mask['e164']['trunk'] : '';
		$nsn = isset($mask['e164']['nsn_length']) ? $mask['e164']['nsn_length'] : array();
		$d = preg_replace('/[\s\-().\/]/', '', $s);
		$rest = null;
		$ccl = strlen($cc);
		if (substr($d, 0, 1) === '+') {
			if (strpos('+' . $cc, $d) === 0 && strlen($d) <= $ccl) {
				return null;
			}
			if (substr($d, 1, $ccl) === $cc) {
				$rest = (string) substr($d, 1 + $ccl);
			}
		} elseif (substr($d, 0, 2) === '00') {
			if (strpos('00' . $cc, $d) === 0 && strlen($d) < $ccl + 2) {
				return null;
			}
			if (substr($d, 2, $ccl) === $cc) {
				$rest = (string) substr($d, 2 + $ccl);
			}
		} elseif (substr($d, 0, $ccl) === $cc && ($trunk === '' || substr($d, 0, strlen($trunk)) !== $trunk)
			&& in_array(strlen($d) - $ccl, $nsn, true)) {
			$rest = (string) substr($d, $ccl);
		}
		if ($rest === null) {
			return $s;
		}
		if ($trunk !== '' && substr($rest, 0, strlen($trunk)) === $trunk) {
			$rest = (string) substr($rest, strlen($trunk));
		}
		return $trunk . $rest;
	}

	/** What the input box shows for a stored or pasted value. */
	public static function format_display($mask, $v) {
		$n = self::to_national_display($mask, $v);
		return self::format_value($mask, $n === null ? self::normalize($v, array()) : $n);
	}
	// #endregion display

	// #region catalog
	/** Path of the catalog shipped with the plugin. */
	public static function catalog_path() {
		return dirname(__FILE__) . '/data/masks-catalog-efb.json';
	}

	/** The decoded catalog (assoc array), read once per request. */
	public static function catalog() {
		if (self::$catalog !== null) {
			return self::$catalog;
		}
		$path = self::catalog_path();
		$data = null;
		if (function_exists('wp_json_file_decode')) {
			$data = wp_json_file_decode($path, array('associative' => true));
		}
		if (!is_array($data) && is_readable($path)) {
			$data = json_decode((string) file_get_contents($path), true);
		}
		if (!is_array($data) || !isset($data['masks']) || !is_array($data['masks'])) {
			$data = array('version' => 0, 'masks' => array());
		}
		if (function_exists('apply_filters')) {
			// Add or adjust presets (each needs at least id + regex). Runs once per request.
			$filtered = apply_filters('efb_mask_presets', $data);
			if (is_array($filtered) && isset($filtered['masks']) && is_array($filtered['masks'])) {
				$data = $filtered;
			}
		}
		self::$catalog = $data;
		self::$index = array();
		foreach ($data['masks'] as $m) {
			if (is_array($m) && isset($m['id'], $m['regex']) && is_string($m['id'])) {
				self::$index[$m['id']] = $m;
			}
		}
		return self::$catalog;
	}

	/** Drop the per-request cache (tests). */
	public static function reset() {
		self::$catalog = null;
		self::$index = null;
		self::$logged = array();
	}

	/** Shape of a stored preset id. */
	public static function is_preset_id($id) {
		return is_string($id) && (bool) preg_match('/^[a-z]{2,4}\.[a-z0-9_]{2,40}$/', $id);
	}

	/** Full catalog entry for an id, or null. */
	public static function preset($id) {
		if (!self::is_preset_id($id)) {
			return null;
		}
		self::catalog();
		return isset(self::$index[$id]) ? self::$index[$id] : null;
	}

	/** Locale key of the placeholder column: fa, ar or en. */
	public static function placeholder_lang($locale) {
		$l = strtolower(substr((string) $locale, 0, 2));
		return ($l === 'fa' || $l === 'ar') ? $l : 'en';
	}

	/**
	 * Browser copy of the presets used on a page: only the fields the runtime needs plus one placeholder.
	 * Unknown ids are skipped.
	 */
	public static function runtime_subset(array $ids, $locale = 'en_US') {
		$out = array();
		$lang = self::placeholder_lang($locale);
		foreach (array_unique($ids) as $id) {
			$m = self::preset($id);
			if (!$m) {
				continue;
			}
			$row = array();
			foreach (self::RUNTIME_FIELDS as $k) {
				if (array_key_exists($k, $m)) {
					$row[$k] = $m[$k];
				}
			}
			$row['placeholder'] = isset($m['placeholder'][$lang]) ? $m['placeholder'][$lang] : (isset($m['placeholder']['en']) ? $m['placeholder']['en'] : '');
			$out[$id] = $row;
		}
		return $out;
	}

	/**
	 * Machine-readable preset list for connectors (no regex, examples, metadata or sources):
	 * array('version' => int, 'tokens' => array, 'checksums' => array, 'presets' => array(array(id, country,
	 * category, label{en,fa,ar}, placeholder{en,fa,ar}, mask, formatter, inputmode, autocomplete, checksum,
	 * validator, store, verification, has_strict))). Filter: 'efb_mask_catalog'.
	 */
	public static function catalog_public($locale = null) {
		$cat = self::catalog();
		$presets = array();
		foreach ($cat['masks'] as $m) {
			if (!is_array($m) || !isset($m['id']) || !self::is_preset_id($m['id'])) {
				continue;
			}
			$row = array('id' => $m['id']);
			foreach (array('country', 'category', 'label', 'placeholder', 'mask', 'formatter', 'inputmode', 'autocomplete', 'checksum', 'validator', 'store', 'verification') as $k) {
				$row[$k] = array_key_exists($k, $m) ? $m[$k] : null;
			}
			$row['has_strict'] = !empty($m['regex_strict']);
			if ($locale !== null && self::load_labels()) {
				$row['label_localized'] = \Emsfb\Masks_Labels::label($m['id'], isset($m['label']['en']) ? $m['label']['en'] : $m['id']);
			}
			$presets[] = $row;
		}
		$out = array(
			'version' => isset($cat['version']) ? $cat['version'] : 0,
			'field_keys' => array(
				'mask_preset' => 'preset id, "custom" or ""',
				'mask_custom' => 'token mask (9 digit, a letter, * either, [ ] optional, {n,m} repeat, \\x literal), max ' . self::MAX_CUSTOM_MASK . ' characters',
				'mask_checksum' => implode('|', self::CUSTOM_CHECKSUMS) . ' (custom masks only)',
				'mask_store' => 'canonical|display',
				'mask_strict' => '0|1',
				'inputmode' => implode('|', self::INPUTMODES),
			),
			'custom_checksums' => self::CUSTOM_CHECKSUMS,
			'presets' => $presets,
		);
		if (function_exists('apply_filters')) {
			$f = apply_filters('efb_mask_catalog', $out, $locale);
			if (is_array($f)) {
				$out = $f;
			}
		}
		return $out;
	}

	/** Arab-country groups, in the order shown to Arabic sites (the site's own country moves to the front). */
	const ARAB_GROUPS = array('sa', 'ae', 'eg', 'kw', 'qa', 'bh', 'om', 'jo', 'iq', 'lb', 'ma', 'dz', 'tn');

	/** Load the translatable labels (includes/data/masks-labels-efb.php). */
	public static function load_labels() {
		if (!class_exists('\\Emsfb\\Masks_Labels')) {
			$f = dirname(__FILE__) . '/data/masks-labels-efb.php';
			if (is_readable($f)) {
				require_once $f;
			}
		}
		return class_exists('\\Emsfb\\Masks_Labels');
	}

	/** Group keys that come first for a site locale, before "International" and the alphabetical rest. */
	public static function leading_groups($site_locale) {
		$lang = strtolower(substr((string) $site_locale, 0, 2));
		$region = strtolower(substr((string) $site_locale, 3, 2));
		if ($lang === 'fa') {
			return array('ir');
		}
		if ($lang === 'ar') {
			$own = array('sa' => 'sa', 'ae' => 'ae', 'eg' => 'eg', 'kw' => 'kw', 'qa' => 'qa', 'bh' => 'bh', 'om' => 'om', 'jo' => 'jo', 'iq' => 'iq', 'lb' => 'lb', 'ma' => 'ma', 'dz' => 'dz', 'tn' => 'tn');
			$list = self::ARAB_GROUPS;
			if (isset($own[$region])) {
				$list = array_values(array_unique(array_merge(array($own[$region]), $list)));
			}
			return $list;
		}
		if ($lang === 'en') {
			$map = array('gb' => 'uk', 'ca' => 'ca', 'au' => 'au', 'in' => 'in');
			return array(isset($map[$region]) ? $map[$region] : 'us');
		}
		$map = array('de' => 'de', 'hi' => 'in');
		return isset($map[$lang]) ? array($map[$lang]) : array();
	}

	/**
	 * Grouped, translated preset list for the builder's "Format / mask" select (admin AJAX
	 * efb_masks_catalog_Emsfb). Labels follow the admin's locale ($user_locale, through __() and, for fa/ar
	 * when no translation is loaded, the catalog's own fa/ar labels); group order follows the site locale.
	 */
	public static function builder_catalog($user_locale, $site_locale) {
		$cat = self::catalog();
		self::load_labels();
		$labels = class_exists('\\Emsfb\\Masks_Labels') ? Masks_Labels::presets() : array();
		$names = class_exists('\\Emsfb\\Masks_Labels') ? Masks_Labels::groups() : array();
		$ulang = self::placeholder_lang($user_locale);
		$groups = array();
		foreach ($cat['masks'] as $m) {
			if (!is_array($m) || !isset($m['id']) || !self::is_preset_id($m['id'])) {
				continue;
			}
			$g = substr($m['id'], 0, strpos($m['id'], '.'));
			$en = isset($m['label']['en']) ? $m['label']['en'] : $m['id'];
			$label = isset($labels[$m['id']]) ? $labels[$m['id']] : $en;
			if ($label === $en && $ulang !== 'en' && !empty($m['label'][$ulang])) {
				$label = $m['label'][$ulang];
			}
			if (!isset($groups[$g])) {
				$groups[$g] = array('country' => $g, 'label' => isset($names[$g]) ? $names[$g] : strtoupper($g), 'presets' => array());
			}
			$groups[$g]['presets'][] = array(
				'id' => $m['id'],
				'label' => $label,
				'placeholder' => isset($m['placeholder'][$ulang]) ? $m['placeholder'][$ulang] : (isset($m['placeholder']['en']) ? $m['placeholder']['en'] : ''),
				'category' => isset($m['category']) ? $m['category'] : '',
				'mask' => isset($m['mask']) ? $m['mask'] : null,
				'formatter' => isset($m['formatter']) ? $m['formatter'] : null,
				'checksum' => isset($m['checksum']) ? $m['checksum'] : null,
				'inputmode' => isset($m['inputmode']) ? $m['inputmode'] : null,
				'autocomplete' => isset($m['autocomplete']) ? $m['autocomplete'] : null,
				'verification' => isset($m['verification']) ? $m['verification'] : 'unverified',
				'has_strict' => !empty($m['regex_strict']),
			);
		}
		$lead = array();
		foreach (self::leading_groups($site_locale) as $g) {
			if (isset($groups[$g])) {
				$lead[] = $groups[$g];
				unset($groups[$g]);
			}
		}
		if (isset($groups['intl'])) {
			$lead[] = $groups['intl'];
			unset($groups['intl']);
		}
		$rest = array_values($groups);
		usort($rest, function ($a, $b) {
			return strcmp($a['label'], $b['label']);
		});
		return array('success' => true, 'version' => isset($cat['version']) ? $cat['version'] : 0, 'groups' => array_merge($lead, $rest));
	}

	/** True for preset values that must not be kept in the visitor's localStorage draft. */
	public static function is_sensitive($m) {
		if (!is_array($m)) {
			return false;
		}
		return (isset($m['category']) && in_array($m['category'], self::SENSITIVE_CATEGORIES, true))
			|| (isset($m['id']) && $m['id'] === 'intl.cvc');
	}
	// #endregion catalog

	// #region field gate (server)
	/** Truthy value as saved by the builder (1, '1', true). */
	private static function flag($f, $k) {
		return isset($f[$k]) && in_array($f[$k], array(1, '1', true), true);
	}

	private static function log_once($key, $msg) {
		if (isset(self::$logged[$key])) {
			return;
		}
		self::$logged[$key] = true;
		if (function_exists('error_log')) {
			error_log('[EFB masks] ' . $msg);
		}
	}

	/**
	 * The definition used for a saved field (assoc array), or null when the field has no usable mask:
	 * array('kind' => 'preset'|'custom', 'def' => array(...)).
	 */
	public static function field_def($f) {
		$p = isset($f['mask_preset']) && is_string($f['mask_preset']) ? $f['mask_preset'] : '';
		if ($p === '') {
			return null;
		}
		if ($p === 'custom') {
			$mask = isset($f['mask_custom']) && is_string($f['mask_custom']) ? $f['mask_custom'] : '';
			if (!self::check_mask($mask)) {
				return null;
			}
			$cs = isset($f['mask_checksum']) && in_array($f['mask_checksum'], self::CUSTOM_CHECKSUMS, true) ? $f['mask_checksum'] : '';
			return array('kind' => 'custom', 'def' => array('mask' => $mask, 'checksum' => $cs));
		}
		$m = self::preset($p);
		if (!$m) {
			self::log_once('preset:' . $p, 'unknown preset "' . (self::is_preset_id($p) ? $p : 'invalid') . '"; length rules only');
			return null;
		}
		if (self::flag($f, 'mask_strict') && !empty($m['regex_strict'])) {
			$m['regex'] = $m['regex_strict'];
		}
		return array('kind' => 'preset', 'def' => $m);
	}

	/**
	 * Server gate for one submitted value of a masked field: normalize -> validate -> stored value.
	 * Returns array('ok', 'code', 'stored', 'legacy'). 'legacy' = true means "no usable mask, run the old
	 * length rules" (unknown preset from an older or newer catalog, broken custom mask): never locks the form.
	 */
	public static function check_field($f, $v) {
		$v = (string) $v;
		$fd = self::field_def($f);
		if ($fd === null) {
			return array('ok' => true, 'code' => null, 'stored' => $v, 'legacy' => true);
		}
		if ($v !== '' && !preg_match('//u', $v)) {
			return array('ok' => false, 'code' => 'format', 'stored' => '', 'legacy' => false);
		}
		$display = isset($f['mask_store']) && $f['mask_store'] === 'display';
		try {
			if ($fd['kind'] === 'custom') {
				$r = self::validate_custom($fd['def'], $v);
				$stored = $r['ok'] ? ($display ? $r['value'] : self::to_stored_custom($fd['def'], $r['value'])) : '';
			} else {
				$r = self::validate($fd['def'], $v);
				$stored = $r['ok'] ? ($display ? self::format_display($fd['def'], $r['value']) : self::to_stored($fd['def'], $r['value'])) : '';
			}
		} catch (\Exception $e) {
			self::log_once('err:' . (isset($f['mask_preset']) ? (string) $f['mask_preset'] : ''), 'catalog entry error: ' . $e->getMessage());
			return array('ok' => true, 'code' => null, 'stored' => $v, 'legacy' => true);
		}
		if (!$r['ok'] && $r['code'] === 'empty') {
			// An empty optional field is fine; a required one is reported by the required-field checks too.
			if (!self::flag($f, 'required')) {
				return array('ok' => true, 'code' => null, 'stored' => '', 'legacy' => false);
			}
		}
		return array('ok' => (bool) $r['ok'], 'code' => $r['code'], 'stored' => $stored, 'legacy' => false);
	}

	/**
	 * Validate one value outside a form submit (connectors, assistants). $mask is a preset id ("ir.mobile"),
	 * "custom:<token mask>", or an array of field keys (mask_preset, mask_custom, mask_checksum, mask_store,
	 * mask_strict, required). Returns array('ok' => bool, 'code' => null|'empty'|'format'|'checksum'|'rule'|
	 * 'unknown_mask', 'stored' => string). An empty value is 'empty' unless the array says required = 0.
	 */
	public static function check_value($mask, $value) {
		if (is_string($mask)) {
			$f = strpos($mask, 'custom:') === 0
				? array('mask_preset' => 'custom', 'mask_custom' => (string) substr($mask, 7))
				: array('mask_preset' => $mask);
			$f['required'] = '1';
		} elseif (is_array($mask)) {
			$f = $mask + array('required' => '1');
		} else {
			$f = array();
		}
		if (self::field_def($f) === null) {
			return array('ok' => false, 'code' => 'unknown_mask', 'stored' => '');
		}
		$r = self::check_field($f, is_scalar($value) ? (string) $value : '');
		return array('ok' => $r['ok'], 'code' => $r['code'], 'stored' => $r['stored']);
	}

	/**
	 * Translated message for a failure code. $lan holds the phrases (mskFormat_, mskChecksum_, mskRule_,
	 * mskIncomplete_); English fallbacks are used for any missing key.
	 */
	public static function message($code, $f, $lan) {
		$lan = is_array($lan) ? $lan : array();
		$name = isset($f['name']) ? (string) $f['name'] : '';
		$example = '';
		$fd = self::field_def($f);
		if (isset($f['placeholder']) && is_string($f['placeholder']) && $f['placeholder'] !== '') {
			$example = $f['placeholder'];
		} elseif ($fd && $fd['kind'] === 'preset' && isset($fd['def']['placeholder']['en'])) {
			$example = $fd['def']['placeholder']['en'];
		}
		$t = array(
			'mskFormat_' => 'The %1$s field is not in the correct format (example: %2$s).',
			'mskChecksum_' => '%s is not valid. Please check the digits for typos.',
			'mskRule_' => '%s contains a value that is not possible, for example a date that does not exist.',
			'mskIncomplete_' => 'Please complete the %s field.',
		);
		foreach ($t as $k => $en) {
			if (isset($lan[$k]) && is_string($lan[$k]) && $lan[$k] !== '') {
				$t[$k] = $lan[$k];
			}
		}
		switch ($code) {
			case 'checksum':
				return sprintf($t['mskChecksum_'], $name);
			case 'rule':
				return sprintf($t['mskRule_'], $name);
			case 'empty':
				return sprintf($t['mskIncomplete_'], $name);
			default:
				return $example !== '' ? sprintf($t['mskFormat_'], $name, $example) : sprintf($t['mskIncomplete_'], $name);
		}
	}
	// #endregion field gate
}
