<?php
/**
 * F06: calendar engines, display text and date rules of the advanced date field `advdate`
 * (docs/feature-gaps/F06-advanced-calendar.fa.md, docs/feature-gaps/reference/calendar-engine-contract.en.md).
 *
 * Pure PHP for Tier 1 (gregory, persian, islamic-umalqura, islamic-civil, islamic-tbla): ext-intl is
 * not needed. ext-intl, when loaded, gives month names for languages other than fa / ar / en and the
 * Tier 3 labels. The browser twin is public/assets/js/calendar/efb-calendar-core.js (window.EFBCalendar);
 * both run the same vectors (docs/feature-gaps/reference/calendar-test-vectors.json and
 * tests/fixtures/calendar-constraint-cases.json), so the server decides with the same rules the picker
 * shows. Global wrappers (efb_calendar_*, efb_date_*) are at the end of this file.
 *
 * Algorithms and licences:
 * - persian: Borkowski "breaks" algorithm as in jalaali-js (MIT, (c) 2015-2026 Behrang Noruzi Niya and
 *   contributors).
 * - islamic-umalqura: the official table AH 1318..1500 from dotnet/runtime UmAlQuraCalendar.cs (MIT,
 *   (c) .NET Foundation and Contributors); outside it islamic-civil, approximate.
 * - islamic-civil / islamic-tbla: tabular, epochs JDN 1948440 / 1948439.
 * - gregory: proleptic Gregorian <-> JDN (Fliegel - Van Flandern).
 */

namespace Emsfb;

if (!defined('ABSPATH')) {
	exit;
}

/** One calendar (contract §3, PHP side). */
abstract class Calendar_Engine {
	public $id = '';
	public $tier = 1;
	/** Exact range (outside it approximate() is true). */
	public $range = array(array(1, 1, 1), array(9999, 12, 31));
	/** Hard limits of the arithmetic. */
	public $limits = array(array(1, 1, 1), array(9999, 12, 31));
	abstract public function to_jdn($y, $m, $d);
	/** @return int[] [y, m, d] */
	abstract public function from_jdn($jdn);
	abstract public function days_in_month($y, $m);
	abstract public function is_leap($y);
	public function months_in_year($y) { return 12; }
	public function approximate($y) { return false; }
	public function is_valid($y, $m, $d) {
		foreach (array($y, $m, $d) as $n) {
			if (!is_int($n)) return false;
		}
		if ($y < $this->limits[0][0] || $y > $this->limits[1][0]) return false;
		return $m >= 1 && $m <= $this->months_in_year($y) && $d >= 1 && $d <= $this->days_in_month($y, $m);
	}
	public function month_name($m, $locale, $style = 'long') {
		$n = Calendar::month_names($this->id, $locale, $style);
		return isset($n[$m - 1]) ? $n[$m - 1] : (string) $m;
	}
	/** Year as shown in long text (japanese era); the plain year otherwise. */
	public function display_year($y, $locale) { return null; }
}

class Calendar_Gregorian extends Calendar_Engine {
	public $id = 'gregory';
	public function to_jdn($y, $m, $d) { return Calendar::g2jdn($y, $m, $d); }
	public function from_jdn($jdn) { return Calendar::jdn2g($jdn); }
	public function days_in_month($y, $m) { return Calendar::g_days($y, $m); }
	public function is_leap($y) { return Calendar::g_is_leap($y); }
}

class Calendar_Persian extends Calendar_Engine {
	public $id = 'persian';
	public $range = array(array(1179, 1, 1), array(1501, 12, 29));
	public $limits = array(array(1, 1, 1), array(3000, 12, 29));
	private static $breaks = array(-61, 9, 38, 199, 426, 686, 756, 818, 1111, 1181, 1210, 1635, 2060, 2097, 2192, 2262, 2324, 2394, 2456, 3178);
	private static function div($a, $b) { return intdiv($a, $b); }
	private static function mod($a, $b) { return $a - intdiv($a, $b) * $b; }
	private function jal_cal($jy, $without_leap = false) {
		$b = self::$breaks;
		$bl = count($b); $gy = $jy + 621; $leap_j = -14; $jp = $b[0]; $jump = 0;
		if ($jy < $jp || $jy >= $b[$bl - 1]) throw new \RangeError('Jalali year out of range ' . $jy);
		for ($i = 1; $i < $bl; $i++) {
			$jm = $b[$i]; $jump = $jm - $jp;
			if ($jy < $jm) break;
			$leap_j = $leap_j + self::div($jump, 33) * 8 + self::div(self::mod($jump, 33), 4);
			$jp = $jm;
		}
		$n = $jy - $jp;
		$leap_j = $leap_j + self::div($n, 33) * 8 + self::div(self::mod($n, 33) + 3, 4);
		if (self::mod($jump, 33) === 4 && $jump - $n === 4) $leap_j += 1;
		$leap_g = self::div($gy, 4) - self::div((self::div($gy, 100) + 1) * 3, 4) - 150;
		$march = 20 + $leap_j - $leap_g;
		if ($without_leap) return array('gy' => $gy, 'march' => $march);
		if ($jump - $n < 6) $n = $n - $jump + self::div($jump + 4, 33) * 33;
		$leap = self::mod(self::mod($n + 1, 33) - 1, 4);
		if ($leap === -1) $leap = 4;
		return array('leap' => $leap, 'gy' => $gy, 'march' => $march);
	}
	public function approximate($y) { return $y < 1179 || $y > 1501; }
	public function is_leap($y) { $r = $this->jal_cal($y); return $r['leap'] === 0; }
	public function days_in_month($y, $m) { return $m <= 6 ? 31 : ($m <= 11 ? 30 : ($this->is_leap($y) ? 30 : 29)); }
	public function to_jdn($jy, $jm, $jd) {
		$r = $this->jal_cal($jy, true);
		return Calendar::g2jdn($r['gy'], 3, $r['march']) + ($jm - 1) * 31 - self::div($jm, 7) * ($jm - 7) + $jd - 1;
	}
	public function from_jdn($jdn) {
		$g = Calendar::jdn2g($jdn); $jy = $g[0] - 621; $r = $this->jal_cal($jy);
		$k = $jdn - Calendar::g2jdn($g[0], 3, $r['march']);
		if ($k >= 0) {
			if ($k <= 185) return array($jy, 1 + self::div($k, 31), self::mod($k, 31) + 1);
			$k -= 186;
		} else {
			$jy -= 1; $k += 179;
			if ($r['leap'] === 1) $k += 1;
		}
		return array($jy, 7 + self::div($k, 30), self::mod($k, 30) + 1);
	}
}

class Calendar_Islamic_Tabular extends Calendar_Engine {
	public $limits = array(array(1, 1, 1), array(9666, 12, 30));
	public $range = array(array(1, 1, 1), array(9666, 12, 30));
	private $epoch;
	public function __construct($id, $epoch) { $this->id = $id; $this->epoch = $epoch; }
	public function is_leap($y) { return ((((14 + 11 * $y) % 30) + 30) % 30) < 11; }
	public function days_in_month($y, $m) { return $m === 12 ? ($this->is_leap($y) ? 30 : 29) : ($m % 2 === 1 ? 30 : 29); }
	public function to_jdn($y, $m, $d) { return $d + (int) ceil(29.5 * ($m - 1)) + ($y - 1) * 354 + Calendar::fdiv(3 + 11 * $y, 30) + $this->epoch - 1; }
	public function from_jdn($jdn) {
		$y = Calendar::fdiv(30 * ($jdn - $this->epoch) + 10646, 10631);
		$m = min(12, (int) ceil(($jdn - (29 + $this->to_jdn($y, 1, 1))) / 29.5) + 1);
		return array($y, $m, $jdn - $this->to_jdn($y, $m, 1) + 1);
	}
}

class Calendar_Umm_Al_Qura extends Calendar_Engine {
	public $id = 'islamic-umalqura';
	public $range = array(array(1318, 1, 1), array(1500, 12, 30));
	public $limits = array(array(1, 1, 1), array(9666, 12, 30));
	const FIRST_YEAR = 1318;
	const LAST_YEAR = 1500;
	const FIRST_JDN = 2415140;
	const MASKS = '2ea6e9ed2ea4d4aa96536ab5daaba4b49a9352ba574b6ab55aad55d2aa564ae95d2ec6d56aa5554ab95b2ba575bb27647496552ab55bada6d4ec9d92d25a4d2ad56db6ab52aa5a4b4979372b6575d6ad52a9692d25d4ddada5d4da9d52aaa4d69b63747697526a554baab55aad5dd2da4d49a9552da5d55aad56aa69552ba574ae97656cb55aaaa554ad95d2da5d9db2ba4b4aa552b5575b6abd2bc4b89a9552d5adb6a6d4dc9d92aa69562ae56d36ab55aaa94d49d95d2ba5b55aad55a9a92e26e55dada6d46a554ba9754eaae5acba9d92b2564bcab55ab556d2ea5e4aa9552daad36c7596d269552da5b4ba9ba3b4b69b52aa64b696d2ec6d9eb2d54d2aa564ae96dd6ab54b29a9352ba57536ab56aae93';
	private static $starts = null;
	private $civil;
	public function __construct($civil) { $this->civil = $civil; }
	private static function starts() {
		if (self::$starts === null) {
			$s = array(); $jdn = self::FIRST_JDN; $n = strlen(self::MASKS) / 3;
			for ($i = 0; $i < $n; $i++) {
				$mask = hexdec(substr(self::MASKS, $i * 3, 3));
				for ($m = 0; $m < 12; $m++) { $s[] = $jdn; $jdn += (($mask >> $m) & 1) ? 30 : 29; }
			}
			$s[] = $jdn;
			self::$starts = $s;
		}
		return self::$starts;
	}
	private function in_table($y) { return $y >= self::FIRST_YEAR && $y <= self::LAST_YEAR; }
	private static function idx($y, $m) { return ($y - self::FIRST_YEAR) * 12 + ($m - 1); }
	public function approximate($y) { return !$this->in_table($y); }
	public function is_leap($y) {
		if (!$this->in_table($y)) return $this->civil->is_leap($y);
		$s = self::starts();
		return $s[self::idx($y + 1, 1)] - $s[self::idx($y, 1)] === 355;
	}
	public function days_in_month($y, $m) {
		if (!$this->in_table($y)) return $this->civil->days_in_month($y, $m);
		$s = self::starts();
		return $s[self::idx($y, $m) + 1] - $s[self::idx($y, $m)];
	}
	public function to_jdn($y, $m, $d) {
		if (!$this->in_table($y)) return $this->civil->to_jdn($y, $m, $d);
		$s = self::starts();
		return $s[self::idx($y, $m)] + $d - 1;
	}
	public function from_jdn($jdn) {
		$s = self::starts();
		if ($jdn < $s[0] || $jdn >= $s[count($s) - 1]) return $this->civil->from_jdn($jdn);
		$lo = 0; $hi = count($s) - 2;
		while ($lo < $hi) { $mid = ($lo + $hi + 1) >> 1; if ($s[$mid] <= $jdn) $lo = $mid; else $hi = $mid - 1; }
		return array(self::FIRST_YEAR + intdiv($lo, 12), $lo % 12 + 1, $jdn - $s[$lo] + 1);
	}
	/** The compact table, for tests. */
	public static function table() { return array('firstYear' => self::FIRST_YEAR, 'lastYear' => self::LAST_YEAR, 'firstJdn' => self::FIRST_JDN, 'masksHex' => self::MASKS); }
}

/** Tier 2: the Gregorian grid with the year in another calendar (buddhist +543, roc -1911, japanese era). */
class Calendar_Offset extends Calendar_Engine {
	public $tier = 2;
	private $offset;
	public function __construct($id, $offset) {
		$this->id = $id; $this->offset = $offset;
		$this->limits = array(array(1 + $offset, 1, 1), array(9999 + $offset, 12, 31));
		$this->range = $this->limits;
	}
	public function to_jdn($y, $m, $d) { return Calendar::g2jdn($y - $this->offset, $m, $d); }
	public function from_jdn($jdn) { $g = Calendar::jdn2g($jdn); return array($g[0] + $this->offset, $g[1], $g[2]); }
	public function days_in_month($y, $m) { return Calendar::g_days($y - $this->offset, $m); }
	public function is_leap($y) { return Calendar::g_is_leap($y - $this->offset); }
	public function display_year($y, $locale) {
		if ($this->id !== 'japanese' || !class_exists('\IntlDateFormatter')) return null;
		$f = @new \IntlDateFormatter(str_replace('-', '_', (string) $locale) . '@calendar=japanese', \IntlDateFormatter::NONE, \IntlDateFormatter::NONE, 'UTC', \IntlDateFormatter::TRADITIONAL, 'GGGGy');
		$t = $f ? $f->format(gmmktime(12, 0, 0, 7, 1, (int) $y)) : false;
		return is_string($t) && $t !== '' ? $t : null;
	}
}

class Calendar {

	const VERSION = '1.0.0';

	/** Stable error codes (contract §4.3). */
	const CODES = array('invalid_format', 'invalid_date', 'calendar_not_allowed', 'before_min', 'after_max',
		'weekday_disabled', 'date_disabled', 'not_enabled', 'holiday', 'unavailable', 'full',
		'range_order', 'range_too_short', 'range_too_long', 'range_contains_disabled', 'too_many_dates',
		'time_required', 'time_out_of_range', 'time_step', 'time_closed_weekday', 'age_too_young',
		'age_too_old', 'depends_on_empty', 'required');

	/** Calendar ids of the field config (contract §7.1 $defs.cal). */
	const CALS = array('gregory', 'persian', 'islamic-umalqura', 'islamic-civil', 'islamic-tbla', 'buddhist', 'roc', 'japanese',
		'hebrew', 'indian', 'coptic', 'ethiopic');

	const TIER3 = array('hebrew', 'indian', 'coptic', 'ethiopic');

	/* ---------------------------------------------------------------- numbers and Gregorian */

	public static function fdiv($a, $b) { return (int) floor($a / $b); }
	public static function pad($n, $w = 2) { return ($n < 0 ? '-' : '') . str_pad((string) abs((int) $n), $w, '0', STR_PAD_LEFT); }
	public static function iso_of($y, $m, $d) { return self::pad($y, 4) . '-' . self::pad($m) . '-' . self::pad($d); }
	/** @return int[]|null [y, m, d] */
	public static function split_iso($iso) {
		return is_string($iso) && preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $iso, $m) ? array((int) $m[1], (int) $m[2], (int) $m[3]) : null;
	}
	public static function g_is_leap($y) { return ($y % 4 === 0 && $y % 100 !== 0) || $y % 400 === 0; }
	public static function g_days($y, $m) {
		static $d = array(31, 0, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31);
		return $m === 2 ? (self::g_is_leap($y) ? 29 : 28) : $d[$m - 1];
	}
	public static function g2jdn($y, $m, $d) {
		$a = self::fdiv(14 - $m, 12); $yy = $y + 4800 - $a; $mm = $m + 12 * $a - 3;
		return $d + self::fdiv(153 * $mm + 2, 5) + 365 * $yy + self::fdiv($yy, 4) - self::fdiv($yy, 100) + self::fdiv($yy, 400) - 32045;
	}
	public static function jdn2g($jdn) {
		$a = $jdn + 32044; $b = self::fdiv(4 * $a + 3, 146097); $c = $a - self::fdiv(146097 * $b, 4);
		$d = self::fdiv(4 * $c + 3, 1461); $e = $c - self::fdiv(1461 * $d, 4); $m = self::fdiv(5 * $e + 2, 153);
		return array(100 * $b + $d - 4800 + self::fdiv($m, 10), $m + 3 - 12 * self::fdiv($m, 10), $e - self::fdiv(153 * $m + 2, 5) + 1);
	}
	public static function iso_valid($iso) {
		$p = self::split_iso($iso);
		return $p !== null && $p[0] >= 1 && $p[1] >= 1 && $p[1] <= 12 && $p[2] >= 1 && $p[2] <= self::g_days($p[0], $p[1]);
	}
	public static function iso_to_jdn($iso) { $p = self::split_iso($iso); return $p ? self::g2jdn($p[0], $p[1], $p[2]) : null; }
	public static function jdn_to_iso($jdn) { $g = self::jdn2g($jdn); return self::iso_of($g[0], $g[1], $g[2]); }
	/** ISO weekday 1 = Monday .. 7 = Sunday. */
	public static function weekday_of_jdn($jdn) { return ((($jdn % 7) + 7) % 7) + 1; }
	public static function weekday($iso) { return self::weekday_of_jdn(self::iso_to_jdn($iso)); }
	public static function add_days($iso, $n) { return self::jdn_to_iso(self::iso_to_jdn($iso) + (int) $n); }
	/** today + value units; month / year clamp to the last day of the target month. */
	public static function add_unit($iso, $value, $unit = 'day') {
		$value = (int) $value;
		if ($unit === 'week') return self::add_days($iso, $value * 7);
		if ($unit !== 'month' && $unit !== 'year') return self::add_days($iso, $value);
		$p = self::split_iso($iso);
		$t = $p[0] * 12 + ($p[1] - 1) + ($unit === 'year' ? $value * 12 : $value);
		$y = self::fdiv($t, 12); $m = $t - $y * 12 + 1;
		return self::iso_of($y, $m, min($p[2], self::g_days($y, $m)));
	}
	/** Completed years; a 29 February birthday counts on 1 March in a common year. */
	public static function age_on($birth, $on) {
		$b = self::split_iso($birth); $t = self::split_iso($on);
		if (!$b || !$t) return null;
		$bm = $b[1]; $bd = $b[2];
		if ($bm === 2 && $bd === 29 && !self::g_is_leap($t[0])) { $bm = 3; $bd = 1; }
		$age = $t[0] - $b[0];
		if ($t[1] < $bm || ($t[1] === $bm && $t[2] < $bd)) $age--;
		return $age;
	}
	public static function nights($start, $end) { return self::iso_to_jdn($end) - self::iso_to_jdn($start); }

	/* ---------------------------------------------------------------- engines */

	private static $engines = null;

	/** id => engine; filter efb_calendar_engines (add or replace an engine). */
	public static function engines() {
		if (self::$engines === null) {
			$civil = new Calendar_Islamic_Tabular('islamic-civil', 1948440);
			$list = array(
				'gregory'          => new Calendar_Gregorian(),
				'persian'          => new Calendar_Persian(),
				'islamic-umalqura' => new Calendar_Umm_Al_Qura($civil),
				'islamic-civil'    => $civil,
				'islamic-tbla'     => new Calendar_Islamic_Tabular('islamic-tbla', 1948439),
				'buddhist'         => new Calendar_Offset('buddhist', 543),
				'roc'              => new Calendar_Offset('roc', -1911),
				'japanese'         => new Calendar_Offset('japanese', 0),
			);
			if (function_exists('apply_filters')) {
				$f = apply_filters('efb_calendar_engines', $list);
				if (is_array($f)) {
					foreach ($f as $id => $e) {
						if (is_object($e) && method_exists($e, 'to_jdn') && method_exists($e, 'from_jdn')) $list[(string) $id] = $e;
					}
				}
			}
			self::$engines = $list;
		}
		return self::$engines;
	}
	/** @return Calendar_Engine|null */
	public static function engine($id) {
		$e = self::engines();
		return is_string($id) && isset($e[$id]) ? $e[$id] : null;
	}
	/** id => tier for this server (Tier 3 only with ext-intl). */
	public static function supported() {
		$out = array();
		foreach (self::engines() as $id => $e) $out[$id] = isset($e->tier) ? (int) $e->tier : 1;
		if (class_exists('\IntlDateFormatter')) {
			foreach (self::TIER3 as $c) if (!isset($out[$c])) $out[$c] = 3;
		}
		return $out;
	}
	public static function reset() { self::$engines = null; self::$names = null; }

	/* ---------------------------------------------------------------- text */

	/** UTF-8 of a code point (no mbstring needed). */
	public static function chr($cp) {
		if ($cp < 0x80) return chr($cp);
		if ($cp < 0x800) return chr(0xC0 | ($cp >> 6)) . chr(0x80 | ($cp & 0x3F));
		return chr(0xE0 | ($cp >> 12)) . chr(0x80 | (($cp >> 6) & 0x3F)) . chr(0x80 | ($cp & 0x3F));
	}
	/** Persian / Arabic-Indic / full-width digits to ASCII; direction marks, ALM, NBSP and BOM removed. */
	public static function normalize($text) {
		$s = is_scalar($text) ? (string) $text : '';
		$map = array();
		for ($i = 0; $i < 10; $i++) {
			$map[self::chr(0x06F0 + $i)] = (string) $i;
			$map[self::chr(0x0660 + $i)] = (string) $i;
			$map[self::chr(0xFF10 + $i)] = (string) $i;
		}
		$s = strtr($s, $map);
		$s = preg_replace('/[\x{200B}-\x{200F}\x{202A}-\x{202E}\x{2060}-\x{2069}\x{061C}\x{00A0}\x{FEFF}]/u', '', $s);
		return trim((string) $s);
	}
	public static function to_digits($str, $sys) {
		$base = $sys === 'arabext' ? 0x06F0 : ($sys === 'arab' ? 0x0660 : 0);
		if (!$base) return (string) $str;
		return preg_replace_callback('/[0-9]/', function ($m) use ($base) { return Calendar::chr($base + (int) $m[0]); }, (string) $str);
	}
	/** @return array list of ['lit'=>string] | ['tok'=>string] */
	public static function tokenize($pattern) {
		$out = array();
		preg_match_all('/\[([^\]]*)\]|YYYY|YY|MMMM|MMM|MM|M|DD|D|dddd|ddd|HH|hh|mm|A/', (string) $pattern, $mm, PREG_OFFSET_CAPTURE | PREG_SET_ORDER);
		$last = 0;
		foreach ($mm as $m) {
			$pos = $m[0][1];
			if ($pos > $last) $out[] = array('lit' => substr($pattern, $last, $pos - $last));
			if (isset($m[1]) && $m[1][1] !== -1) $out[] = array('lit' => $m[1][0]); else $out[] = array('tok' => $m[0][0]);
			$last = $pos + strlen($m[0][0]);
		}
		if ($last < strlen($pattern)) $out[] = array('lit' => substr($pattern, $last));
		return $out;
	}

	/* ---------------------------------------------------------------- names */

	private static $names = null;

	/** public/assets/json/calendar-names.json (fa, ar, en), the same table the picker gets. */
	public static function names() {
		if (self::$names === null) {
			$file = dirname(__DIR__) . '/public/assets/json/calendar-names.json';
			$d = is_readable($file) ? json_decode((string) file_get_contents($file), true) : null;
			self::$names = is_array($d) ? $d : array();
		}
		return self::$names;
	}
	public static function lang($locale) {
		$l = strtolower((string) preg_split('/[-_]/', (string) $locale)[0]);
		return $l !== '' ? $l : 'en';
	}
	private static function family($cal) { return strpos((string) $cal, 'islamic') === 0 ? 'islamic' : ($cal === 'persian' ? 'persian' : 'gregory'); }
	public static function month_names($cal, $locale, $style = 'long') {
		$style = $style === 'short' ? 'short' : 'long';
		$fam = self::family($cal); $l = self::lang($locale); $n = self::names();
		if (isset($n[$l]['months'][$fam][$style])) return $n[$l]['months'][$fam][$style];
		$out = array();
		if (class_exists('\IntlDateFormatter')) {
			$icu_cal = $fam === 'gregory' ? 'gregorian' : ($fam === 'islamic' ? 'islamic-umalqura' : 'persian');
			$f = @new \IntlDateFormatter(str_replace('-', '_', (string) $locale) . '@calendar=' . $icu_cal, \IntlDateFormatter::NONE, \IntlDateFormatter::NONE, 'UTC',
				$fam === 'gregory' ? \IntlDateFormatter::GREGORIAN : \IntlDateFormatter::TRADITIONAL, $style === 'short' ? 'MMM' : 'MMMM');
			$e = self::engine($fam === 'gregory' ? 'gregory' : ($fam === 'islamic' ? 'islamic-umalqura' : 'persian'));
			for ($m = 1; $m <= 12 && $f; $m++) {
				$jdn = $fam === 'gregory' ? self::g2jdn(2025, $m, 15) : $e->to_jdn($fam === 'persian' ? 1404 : 1447, $m, 15);
				$t = $f->format((float) ($jdn - 2440588) * 86400);
				$out[] = is_string($t) ? $t : '';
			}
		}
		if (count($out) === 12 && !in_array('', $out, true)) return $out;
		return isset($n['en']['months'][$fam][$style]) ? $n['en']['months'][$fam][$style] : range(1, 12);
	}
	/** Index 0 = Monday. */
	public static function weekday_names($locale, $style = 'long') {
		$style = in_array($style, array('short', 'narrow'), true) ? $style : 'long';
		$l = self::lang($locale); $n = self::names();
		if (isset($n[$l]['weekdays'][$style])) return $n[$l]['weekdays'][$style];
		$out = array();
		if (class_exists('\IntlDateFormatter')) {
			$f = @new \IntlDateFormatter(str_replace('-', '_', (string) $locale), \IntlDateFormatter::NONE, \IntlDateFormatter::NONE, 'UTC', \IntlDateFormatter::GREGORIAN, $style === 'long' ? 'EEEE' : ($style === 'short' ? 'EEE' : 'EEEEE'));
			for ($i = 0; $i < 7 && $f; $i++) { $t = $f->format(gmmktime(12, 0, 0, 1, 1 + $i, 2024)); $out[] = is_string($t) ? $t : ''; }
		}
		if (count($out) === 7 && !in_array('', $out, true)) return $out;
		return isset($n['en']['weekdays'][$style]) ? $n['en']['weekdays'][$style] : array('Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun');
	}
	public static function ampm($locale) {
		$n = self::names(); $l = self::lang($locale);
		return isset($n[$l]['ampm']) ? $n[$l]['ampm'] : array('AM', 'PM');
	}
	/** Calendar name for labels (bundled for fa / ar / en; English id otherwise). */
	public static function calendar_label($cal, $locale, $long = false) {
		$n = self::names(); $l = self::lang($locale); $k = $long ? 'calendars' : 'calendars_short';
		if (isset($n[$l][$k][$cal])) return $n[$l][$k][$cal];
		return isset($n['en'][$k][$cal]) ? $n['en'][$k][$cal] : (string) $cal;
	}
	/** Tier 3 label through ext-intl ('' without it). */
	public static function intl_label($iso, $cal, $locale) {
		$p = self::split_iso($iso);
		if (!$p || !class_exists('\IntlDateFormatter')) return '';
		$f = @new \IntlDateFormatter(str_replace('-', '_', (string) $locale) . '@calendar=' . $cal, \IntlDateFormatter::MEDIUM, \IntlDateFormatter::NONE, 'UTC', \IntlDateFormatter::TRADITIONAL);
		$t = $f ? $f->format(gmmktime(12, 0, 0, $p[1], $p[2], $p[0])) : false;
		return is_string($t) ? $t : '';
	}

	/* ---------------------------------------------------------------- format / parse / convert */

	public static function format_local($engine, array $dt, $pattern, $locale, array $opts = array()) {
		list($y, $m, $d) = $dt;
		$digits = isset($opts['digits']) ? $opts['digits'] : 'latn';
		$wd = self::weekday_of_jdn($engine->to_jdn($y, $m, $d));
		$time = isset($opts['time']) && is_string($opts['time']) && $opts['time'] !== '' ? array_map('intval', explode(':', $opts['time'])) : null;
		$era = method_exists($engine, 'display_year') && strpos((string) $pattern, 'MMMM') !== false ? $engine->display_year($y, $locale) : null;
		$out = '';
		foreach (self::tokenize($pattern === '' ? 'YYYY-MM-DD' : $pattern) as $p) {
			if (isset($p['lit'])) { $out .= $p['lit']; continue; }
			switch ($p['tok']) {
				case 'YYYY': $out .= self::to_digits($era !== null ? $era : ($y >= 1000 ? (string) $y : self::pad($y, 4)), $digits); break;
				case 'YY': $out .= self::to_digits(self::pad($y % 100), $digits); break;
				case 'MMMM': $n = self::month_names($engine->id, $locale, 'long'); $out .= $n[$m - 1]; break;
				case 'MMM': $n = self::month_names($engine->id, $locale, 'short'); $out .= $n[$m - 1]; break;
				case 'MM': $out .= self::to_digits(self::pad($m), $digits); break;
				case 'M': $out .= self::to_digits((string) $m, $digits); break;
				case 'DD': $out .= self::to_digits(self::pad($d), $digits); break;
				case 'D': $out .= self::to_digits((string) $d, $digits); break;
				case 'dddd': $n = self::weekday_names($locale, 'long'); $out .= $n[$wd - 1]; break;
				case 'ddd': $n = self::weekday_names($locale, 'short'); $out .= $n[$wd - 1]; break;
				case 'HH': $out .= $time ? self::to_digits(self::pad($time[0]), $digits) : ''; break;
				case 'hh': $out .= $time ? self::to_digits(self::pad((($time[0] + 11) % 12) + 1), $digits) : ''; break;
				case 'mm': $out .= $time ? self::to_digits(self::pad($time[1]), $digits) : ''; break;
				case 'A': $a = self::ampm($locale); $out .= $time ? $a[$time[0] < 12 ? 0 : 1] : ''; break;
			}
		}
		return $out;
	}
	/** Typed text in the pattern (numeric tokens only) to [y, m, d] in this calendar, or null. */
	public static function parse_local($engine, $text, $pattern) {
		$s = self::normalize($text);
		if ($s === '') return null;
		$re = '^'; $order = array();
		foreach (self::tokenize($pattern === '' ? 'YYYY-MM-DD' : $pattern) as $p) {
			if (isset($p['lit'])) {
				$lit = preg_replace('/\s+/', '', $p['lit']);
				if ($lit === '') { $re .= '\s*'; continue; }
				$re .= '\s*' . (preg_match('#^[/.\-]+$#', $lit) ? '[\/.\-]' : preg_quote($lit, '/')) . '\s*';
				continue;
			}
			if ($p['tok'] === 'YYYY') { $re .= '(\d{4})'; $order[] = 'y'; }
			elseif ($p['tok'] === 'MM' || $p['tok'] === 'M') { $re .= '(\d{1,2})'; $order[] = 'm'; }
			elseif ($p['tok'] === 'DD' || $p['tok'] === 'D') { $re .= '(\d{1,2})'; $order[] = 'd'; }
			else return null;
		}
		if (!preg_match('/' . $re . '$/u', $s, $mt)) return null;
		$dt = array();
		foreach ($order as $i => $k) $dt[$k] = (int) $mt[$i + 1];
		if (!isset($dt['y'], $dt['m'], $dt['d'])) return null;
		return $engine->is_valid($dt['y'], $dt['m'], $dt['d']) ? array($dt['y'], $dt['m'], $dt['d']) : null;
	}
	public static function adjust_of($cal, $adjust) { return strpos((string) $cal, 'islamic') === 0 ? (int) $adjust : 0; }
	/** @return array|null [y, m, d, approx] */
	public static function iso_to_local($iso, $cal, $adjust = 0) {
		$e = self::engine($cal); $jdn = self::iso_to_jdn($iso);
		if (!$e || $jdn === null) return null;
		$dt = $e->from_jdn($jdn + self::adjust_of($cal, $adjust));
		return array($dt[0], $dt[1], $dt[2], $e->approximate($dt[0]));
	}
	public static function local_to_iso(array $dt, $cal, $adjust = 0) {
		$e = self::engine($cal);
		if (!$e || !$e->is_valid($dt[0], $dt[1], $dt[2])) return null;
		return self::jdn_to_iso($e->to_jdn($dt[0], $dt[1], $dt[2]) - self::adjust_of($cal, $adjust));
	}
	/** @return array{y:int,m:int,d:int,iso:string,approx:bool}|\WP_Error|null */
	public static function convert($date, $from, $to, array $args = array()) {
		$fe = self::engine($from); $te = self::engine($to);
		if (is_array($date)) $dt = array((int) ($date['y'] ?? 0), (int) ($date['m'] ?? 0), (int) ($date['d'] ?? 0));
		else $dt = preg_match('/^(\d{1,4})-(\d{1,2})-(\d{1,2})$/', self::normalize($date), $m) ? array((int) $m[1], (int) $m[2], (int) $m[3]) : null;
		if (!$fe || !$te) return self::error('calendar_not_allowed', 'Unknown calendar.');
		if (!$dt || !$fe->is_valid($dt[0], $dt[1], $dt[2])) return self::error('invalid_date', 'Not a date in this calendar.');
		$adj = isset($args['hijri_adjust']) ? (int) $args['hijri_adjust'] : 0;
		$jdn = $fe->to_jdn($dt[0], $dt[1], $dt[2]) - self::adjust_of($from, $adj);
		$o = $te->from_jdn($jdn + self::adjust_of($to, $adj));
		return array('y' => $o[0], 'm' => $o[1], 'd' => $o[2], 'iso' => self::iso_of($o[0], $o[1], $o[2]), 'approx' => $fe->approximate($dt[0]) || $te->approximate($o[0]));
	}
	public static function error($code, $message, $data = array()) {
		if (class_exists('\WP_Error')) return new \WP_Error($code, $message, $data);
		return array('code' => $code, 'message' => $message, 'data' => $data);
	}

	/* ---------------------------------------------------------------- time zones */

	public static function site_tz() {
		if (function_exists('wp_timezone_string')) {
			$s = wp_timezone_string();
			if ($s === '+00:00' || $s === '-00:00') return 'UTC';
			if (is_string($s) && $s !== '') return $s;
		}
		return 'UTC';
	}
	public static function valid_tz($tz) {
		if (!is_string($tz) || $tz === '') return false;
		try { new \DateTimeZone($tz); return true; } catch (\Exception $e) { return false; }
	}
	public static function zone($tz) {
		$z = ($tz === '' || $tz === 'site' || $tz === null) ? self::site_tz() : $tz;
		try { return new \DateTimeZone($z); } catch (\Exception $e) { return new \DateTimeZone('UTC'); }
	}
	/** ISO date "now" in that zone ('site' = wp_timezone()); $now: DateTimeInterface or unix seconds for tests. */
	public static function today($tz = 'site', $now = null) {
		$zone = self::zone($tz);
		if ($now instanceof \DateTimeInterface) $ts = $now->getTimestamp();
		elseif (is_numeric($now)) $ts = (int) $now;
		else $ts = time();
		$d = new \DateTimeImmutable('@' . $ts);
		return $d->setTimezone($zone)->format('Y-m-d');
	}
	private static function offset_minutes($ts, \DateTimeZone $zone) {
		return (int) round($zone->getOffset(new \DateTimeImmutable('@' . (int) $ts)) / 60);
	}
	/** Instant of a wall time, or null when it does not exist or happens twice (DST). 'YYYY-MM-DDTHH:mm:00Z'. */
	public static function wall_to_utc($iso, $hm, $tz) {
		$p = self::split_iso($iso);
		if (!$p || !is_string($hm) || !preg_match('/^(\d{2}):(\d{2})$/', $hm, $t)) return null;
		$zone = self::zone($tz);
		$wall = gmmktime((int) $t[1], (int) $t[2], 0, $p[1], $p[2], $p[0]);
		$found = array();
		foreach (array(-14, -6, -3, 0, 3, 6, 14) as $h) {
			$off = self::offset_minutes($wall + $h * 3600, $zone);
			$cand = $wall - $off * 60;
			if (self::offset_minutes($cand, $zone) === $off) $found[$cand] = true;
		}
		if (count($found) !== 1) return null;
		return gmdate('Y-m-d\TH:i:00\Z', (int) key($found));
	}

	/* ---------------------------------------------------------------- locale table (F06 §5.1) */

	/** Same table as EFBCalendar._.localeDefaults(); filter efb_calendar_default_for_locale in resolve_field(). */
	public static function locale_defaults($locale) {
		$loc = str_replace('-', '_', (string) ($locale ?: 'en_US'));
		$l = self::lang($loc); $parts = explode('_', $loc); $region = isset($parts[1]) ? strtoupper($parts[1]) : '';
		$r = array('default' => 'gregory', 'allowed' => array('gregory'), 'secondary' => '', 'digits' => 'latn', 'hour12' => false);
		if ($l === 'fa' || $l === 'ps') {
			$r['default'] = 'persian'; $r['digits'] = 'arabext';
			$r['allowed'] = ($region === 'AF' || $l === 'ps') ? array('persian', 'gregory', 'islamic-umalqura') : array('persian', 'gregory');
		} elseif ($l === 'ar') {
			$r['hour12'] = $region !== 'MA';
			$r['digits'] = in_array($region, array('MA', 'DZ', 'TN', 'LY', 'EH', 'AE'), true) ? 'latn' : 'arab';
			if ($region === 'SA' || $region === 'YE') { $r['default'] = 'islamic-umalqura'; $r['allowed'] = array('islamic-umalqura', 'gregory'); $r['secondary'] = 'gregory'; }
			elseif (in_array($region, array('AE', 'KW', 'QA', 'BH', 'OM'), true)) { $r['allowed'] = array('gregory', 'islamic-umalqura'); $r['secondary'] = 'islamic-umalqura'; }
			else { $r['allowed'] = array('gregory', 'islamic-civil'); }
		} elseif ($l === 'ur') { $r['allowed'] = array('gregory', 'islamic-civil'); $r['hour12'] = true; }
		elseif ($l === 'he') { $r['secondary'] = 'hebrew'; }
		elseif ($l === 'th') { $r['default'] = 'buddhist'; $r['allowed'] = array('buddhist', 'gregory'); }
		elseif ($l === 'ja') { $r['allowed'] = array('gregory', 'japanese'); }
		elseif ($l === 'zh' && $region === 'TW') { $r['allowed'] = array('gregory', 'roc'); }
		if ($l === 'en' && in_array($region, array('US', 'CA', 'AU', 'NZ', 'PH', 'IN'), true)) $r['hour12'] = true;
		if (in_array($l, array('hi', 'bn', 'ko'), true)) $r['hour12'] = true;
		return $r;
	}
	public static function typed_pattern($cal, $locale) {
		$loc = str_replace('-', '_', (string) ($locale ?: 'en_US'));
		$l = self::lang($loc); $parts = explode('_', $loc); $region = isset($parts[1]) ? strtoupper($parts[1]) : '';
		if ($cal === 'persian') return 'YYYY/MM/DD';
		if (strpos((string) $cal, 'islamic') === 0) return 'DD/MM/YYYY';
		if (in_array($l, array('fa', 'ar', 'ps', 'ja', 'zh', 'ko'), true)) return 'YYYY/MM/DD';
		if ($l === 'en' && in_array($region, array('US', 'PH', ''), true)) return 'MM/DD/YYYY';
		if (in_array($l, array('de', 'ru', 'pl', 'tr', 'fi', 'nb', 'da', 'cs', 'sk', 'uk', 'ro', 'hu', 'az', 'kk'), true)) return 'DD.MM.YYYY';
		return 'DD/MM/YYYY';
	}
	public static function medium_pattern($locale) {
		$loc = str_replace('-', '_', (string) ($locale ?: 'en_US'));
		$l = self::lang($loc); $parts = explode('_', $loc); $region = isset($parts[1]) ? strtoupper($parts[1]) : '';
		if ($l === 'en' && ($region === 'US' || $region === '')) return 'MMMM D, YYYY';
		if (in_array($l, array('ja', 'zh', 'ko'), true)) return 'YYYY-MM-DD';
		return 'D MMMM YYYY';
	}

	/* ---------------------------------------------------------------- rules (F06 §6.1, §7; JS twin: EFBCalendar.rules) */

	const TIER12 = array('gregory', 'persian', 'islamic-umalqura', 'islamic-civil', 'islamic-tbla', 'buddhist', 'roc', 'japanese');
	const PLAN_RANK = array('free' => 0, 'free_plus' => 1, 'pro' => 2);

	private static function json($v) { return function_exists('wp_json_encode') ? (string) wp_json_encode($v) : (string) json_encode($v); }
	private static function has($o, $k) { return is_array($o) && array_key_exists($k, $o) && $o[$k] !== null; }
	private static function on($o, $k) { $v = self::has($o, $k) ? $o[$k] : 0; return $v === 1 || $v === '1' || $v === true || $v === 'true'; }
	public static function int_in($v, $lo, $hi, $dflt) {
		if ($v === '' || $v === null || is_bool($v) || is_array($v) || is_object($v)) return $dflt;
		$s = self::normalize($v);
		if (!is_numeric($s) || (float) $s != floor((float) $s)) return $dflt;
		$n = (int) $s;
		return $n < $lo ? $lo : ($n > $hi ? $hi : $n);
	}
	public static function list_of($v) {
		if (is_object($v)) $v = json_decode(self::json($v), true);
		if (is_array($v)) return array_values($v);
		if (is_string($v) && $v !== '') {
			$j = json_decode($v, true);
			if (is_array($j) && array_keys($j) === range(0, count($j) - 1)) return $j;
			return explode(',', $v);
		}
		return array();
	}
	private static function obj_of($v) {
		if (is_object($v)) $v = json_decode(self::json($v), true);
		if (is_array($v)) return $v;
		if (is_string($v) && $v !== '') { $j = json_decode($v, true); if (is_array($j)) return $j; }
		return array();
	}
	public static function limit_of($v) {
		$v = self::obj_of($v);
		$t = isset($v['type']) ? $v['type'] : '';
		$unit = isset($v['unit']) && in_array($v['unit'], array('day', 'week', 'month', 'year'), true) ? $v['unit'] : 'day';
		if ($t === 'absolute' && isset($v['value']) && is_string($v['value']) && self::iso_valid(self::normalize($v['value']))) return array('type' => 'absolute', 'value' => self::normalize($v['value']));
		if ($t === 'relative' && self::int_in(isset($v['value']) ? $v['value'] : null, -36500, 36500, null) !== null) return array('type' => 'relative', 'value' => self::int_in($v['value'], -36500, 36500, 0), 'unit' => $unit);
		if ($t === 'field' && isset($v['field']) && is_string($v['field']) && preg_match('/^[A-Za-z0-9_\-]{1,64}$/', $v['field'])) return array('type' => 'field', 'field' => $v['field'], 'offset' => self::int_in(isset($v['offset']) ? $v['offset'] : 0, -36500, 36500, 0), 'unit' => $unit);
		return array('type' => 'none');
	}
	public static function dates_list($v, $max = 2000) {
		$out = array();
		foreach (self::list_of($v) as $x) {
			if (count($out) >= $max) break;
			if (!is_scalar($x) || !preg_match('#^(\d{4}-\d{2}-\d{2})(?:/(\d{4}-\d{2}-\d{2}))?$#', self::normalize($x), $m)) continue;
			if (!self::iso_valid($m[1])) continue;
			if (!empty($m[2]) && (!self::iso_valid($m[2]) || $m[2] < $m[1] || self::nights($m[1], $m[2]) > 3660)) continue;
			$out[] = !empty($m[2]) ? array($m[1], $m[2]) : $m[1];
		}
		return $out;
	}
	public static function label_of($l, $locale) {
		if (is_string($l)) return $l;
		if (!is_array($l) || !$l) return '';
		$k = self::lang($locale);
		if (!empty($l[$k])) return (string) $l[$k];
		if (!empty($l['en'])) return (string) $l['en'];
		$f = reset($l);
		return is_string($f) ? $f : '';
	}
	public static function holiday_rules($v) {
		$out = array();
		foreach (self::list_of($v) as $x) {
			if (count($out) >= 500) break;
			$r = self::obj_of($x);
			if (isset($r['cal'], $r['md']) && is_string($r['cal']) && in_array($r['cal'], self::TIER12, true) && is_string($r['md']) && preg_match('/^\d{2}-\d{2}$/', $r['md'])) {
				$out[] = array('cal' => $r['cal'], 'md' => $r['md'], 'days' => self::int_in(isset($r['days']) ? $r['days'] : 1, 1, 14, 1), 'label' => isset($r['label']) ? $r['label'] : '');
			} elseif (isset($r['date']) && is_string($r['date'])) {
				$d = self::dates_list(array($r['date']), 1);
				if ($d) $out[] = array('date' => $d[0], 'label' => isset($r['label']) ? $r['label'] : '');
			}
		}
		return $out;
	}
	public static function weekday_windows($v) {
		$out = array();
		foreach (self::obj_of($v) as $k => $list) {
			if (!preg_match('/^[1-7]$/', (string) $k) || !is_array($list)) continue;
			$w = array();
			foreach (array_slice(array_values($list), 0, 12) as $p) {
				if (is_array($p) && count($p) === 2) {
					$p = array_values($p);
					if (is_string($p[0]) && is_string($p[1]) && preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $p[0]) && preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $p[1]) && $p[0] < $p[1]) $w[] = array($p[0], $p[1]);
				}
			}
			$out[(string) $k] = $w;
		}
		ksort($out);
		return $out;
	}
	/** Locale defaults through efb_calendar_default_for_locale. */
	public static function locale_defaults_filtered($locale) {
		$d = self::locale_defaults($locale);
		if (function_exists('apply_filters')) {
			$f = apply_filters('efb_calendar_default_for_locale', $d, (string) $locale);
			if (is_array($f)) $d = array_merge($d, $f);
		}
		return $d;
	}
	/**
	 * The field row as the rules read it (twin of EFBCalendar.rules.normalizeField): every key checked,
	 * defaults filled, `auto` resolved for the page locale, keys of a higher plan dropped.
	 * $ctx: locale, plan (free | free_plus | pro; default: this site's plan, pro outside WordPress).
	 */
	public static function normalize_field($f, array $ctx = array()) {
		$f = self::obj_of($f);
		$page_locale = isset($ctx['locale']) && $ctx['locale'] !== '' ? (string) $ctx['locale'] : (function_exists('get_locale') ? get_locale() : 'en_US');
		$locale = self::has($f, 'cal_locale') && $f['cal_locale'] !== 'auto' && is_string($f['cal_locale']) && preg_match('/^[a-zA-Z]{2,3}([_-][A-Za-z0-9]{2,8})*$/', $f['cal_locale']) ? str_replace('-', '_', $f['cal_locale']) : $page_locale;
		$ld = isset($ctx['locale_defaults']) && is_array($ctx['locale_defaults']) ? $ctx['locale_defaults'] : self::locale_defaults_filtered($locale);
		$plan = isset($ctx['plan']) && isset(self::PLAN_RANK[$ctx['plan']]) ? self::PLAN_RANK[$ctx['plan']] : self::site_plan_rank();
		$engines = self::engines();
		$known = function ($c) use ($engines) { return is_string($c) && in_array($c, Calendar::TIER12, true) && isset($engines[$c]); };
		$def = self::has($f, 'cal_default') && $f['cal_default'] !== 'auto' && $known($f['cal_default']) ? $f['cal_default'] : $ld['default'];
		if (!$known($def)) $def = 'gregory';
		$allowed = array();
		foreach (self::list_of(isset($f['cal_allowed']) ? $f['cal_allowed'] : array()) as $c) {
			if ($known($c) && !in_array($c, $allowed, true)) $allowed[] = $c;
		}
		if (!in_array($def, $allowed, true)) array_unshift($allowed, $def);
		$hm = '/^([01]\d|2[0-3]):[0-5]\d$/';
		$tz = isset($f['tz']) ? $f['tz'] : 'site';
		$c = array(
			'locale'           => $locale,
			'cal_default'      => $def,
			'cal_allowed'      => $allowed,
			'cal_switch'       => self::on($f, 'cal_switch') && count($allowed) > 1 ? 1 : 0,
			'cal_secondary'    => self::has($f, 'cal_secondary') && is_string($f['cal_secondary']) && ($known($f['cal_secondary']) || in_array($f['cal_secondary'], self::TIER3, true)) ? $f['cal_secondary'] : '',
			'cal_hijri_adjust' => self::int_in(isset($f['cal_hijri_adjust']) ? $f['cal_hijri_adjust'] : 0, -2, 2, 0),
			'digits'           => isset($f['cal_digits']) && in_array($f['cal_digits'], array('latn', 'arabext', 'arab'), true) ? $f['cal_digits'] : $ld['digits'],
			'week_start'       => self::int_in(isset($f['week_start']) ? $f['week_start'] : null, 1, 7, 0),
			'date_mode'        => isset($f['date_mode']) && in_array($f['date_mode'], array('single', 'range', 'multiple'), true) ? $f['date_mode'] : 'single',
			'display_format'   => isset($f['display_format']) && is_string($f['display_format']) && preg_match('#^(YYYY|MM|M|DD|D|[/.\- ])+$#', $f['display_format']) && strpos($f['display_format'], 'YYYY') !== false && strpos($f['display_format'], 'M') !== false && strpos($f['display_format'], 'D') !== false ? $f['display_format'] : 'auto',
			'store_format'     => isset($f['store_format']) && $f['store_format'] === 'legacy_local' ? 'legacy_local' : 'iso',
			'input_mode'       => isset($f['input_mode']) && in_array($f['input_mode'], array('both', 'picker', 'typed'), true) ? $f['input_mode'] : 'both',
			'date_min'         => self::limit_of(isset($f['date_min']) ? $f['date_min'] : null),
			'date_max'         => self::limit_of(isset($f['date_max']) ? $f['date_max'] : null),
			'disabled_weekdays' => array(),
			'disabled_dates'   => self::dates_list(isset($f['disabled_dates']) ? $f['disabled_dates'] : array()),
			'enabled_dates'    => self::dates_list(isset($f['enabled_dates']) ? $f['enabled_dates'] : array()),
			'holiday_sets'     => array(),
			'holidays_custom'  => self::holiday_rules(isset($f['holidays_custom']) ? $f['holidays_custom'] : array()),
			'holiday_mode'     => isset($f['holiday_mode']) && $f['holiday_mode'] === 'mark' ? 'mark' : 'disable',
			'range_unit'       => isset($f['range_unit']) && $f['range_unit'] === 'days' ? 'days' : 'nights',
			'range_min_days'   => self::int_in(isset($f['range_min_days']) ? $f['range_min_days'] : 0, 0, 3660, 0),
			'range_max_days'   => self::int_in(isset($f['range_max_days']) ? $f['range_max_days'] : 0, 0, 3660, 0),
			'multi_max'        => self::int_in(isset($f['multi_max']) ? $f['multi_max'] : 0, 0, 366, 0),
			'time_enabled'     => self::on($f, 'time_enabled') ? 1 : 0,
			'time_format'      => isset($f['time_format']) && in_array((string) $f['time_format'], array('12', '24'), true) ? (string) $f['time_format'] : (!empty($ld['hour12']) ? '12' : '24'),
			'time_step'        => self::int_in(isset($f['time_step']) ? $f['time_step'] : 15, 1, 720, 15),
			'time_min'         => isset($f['time_min']) && is_string($f['time_min']) && preg_match($hm, $f['time_min']) ? $f['time_min'] : '',
			'time_max'         => isset($f['time_max']) && is_string($f['time_max']) && preg_match($hm, $f['time_max']) ? $f['time_max'] : '',
			'time_by_weekday'  => self::weekday_windows(isset($f['time_by_weekday']) ? $f['time_by_weekday'] : array()),
			'age_min'          => self::int_in(isset($f['age_min']) ? $f['age_min'] : null, 0, 150, null),
			'age_max'          => self::int_in(isset($f['age_max']) ? $f['age_max'] : null, 0, 150, null),
			'tz'               => ($tz === 'visitor' || (is_string($tz) && $tz !== 'site' && self::valid_tz($tz))) ? $tz : 'site',
			'availability'     => isset($f['availability']) && $f['availability'] === 'booking' ? 'booking' : 'none',
			'required'         => self::on($f, 'required') ? 1 : 0,
		);
		foreach (self::list_of(isset($f['disabled_weekdays']) ? $f['disabled_weekdays'] : array()) as $w) {
			$w = self::int_in($w, 1, 7, null);
			if ($w !== null && !in_array($w, $c['disabled_weekdays'], true)) $c['disabled_weekdays'][] = $w;
		}
		sort($c['disabled_weekdays']);
		foreach (self::list_of(isset($f['holiday_sets']) ? $f['holiday_sets'] : array()) as $h) {
			if (is_string($h) && preg_match('/^[a-z0-9-]{2,40}$/', $h) && !in_array($h, $c['holiday_sets'], true)) $c['holiday_sets'][] = $h;
		}
		if ($plan < 1) {
			$c['cal_allowed'] = array($c['cal_default']); $c['cal_switch'] = 0; $c['cal_secondary'] = ''; $c['cal_hijri_adjust'] = 0;
			$c['disabled_dates'] = array(); $c['enabled_dates'] = array(); $c['holiday_sets'] = array(); $c['holidays_custom'] = array();
			if ($c['date_min']['type'] === 'field') $c['date_min'] = array('type' => 'none');
			if ($c['date_max']['type'] === 'field') $c['date_max'] = array('type' => 'none');
			$c['age_min'] = null; $c['age_max'] = null; $c['time_enabled'] = 0;
		}
		if ($plan < 2) { $c['date_mode'] = 'single'; $c['time_by_weekday'] = array(); $c['availability'] = 'none'; }
		return $c;
	}
	/** 0 free, 1 free_plus, 2 pro (F06 §10.1 through efb_plan_at_least_efb; pro outside WordPress). */
	public static function site_plan_rank() {
		if (!function_exists('efb_plan_at_least_efb')) return 2;
		$r = efb_plan_at_least_efb('pro') ? 2 : (efb_plan_at_least_efb('free_plus') ? 1 : 0);
		return function_exists('apply_filters') ? (int) apply_filters('efb_calendar_plan_rank', $r) : $r;
	}
	public static function first_date($v) {
		$s = explode('T', (string) preg_split('#[/,]#', self::normalize($v))[0])[0];
		return self::iso_valid($s) ? $s : null;
	}
	private static function eval_limit($l, $today, $values) {
		if ($l['type'] === 'absolute') return array('iso' => $l['value'], 'source' => 'absolute');
		if ($l['type'] === 'relative') return array('iso' => self::add_unit($today, $l['value'], $l['unit']), 'source' => 'relative');
		if ($l['type'] === 'field') {
			$src = is_array($values) && isset($values[$l['field']]) && is_scalar($values[$l['field']]) ? self::first_date($values[$l['field']]) : null;
			return $src ? array('iso' => self::add_unit($src, $l['offset'], $l['unit']), 'source' => 'field:' . $l['field'])
				: array('iso' => null, 'source' => 'field:' . $l['field'], 'empty' => true);
		}
		return array('iso' => null, 'source' => 'none');
	}
	private static function now_ts(array $ctx) {
		if (isset($ctx['now'])) {
			if ($ctx['now'] instanceof \DateTimeInterface) return $ctx['now']->getTimestamp();
			if (is_numeric($ctx['now'])) return (int) $ctx['now'];
			$t = strtotime((string) $ctx['now']);
			if ($t !== false) return $t;
		}
		return time();
	}
	public static function zone_of(array $c, array $ctx) {
		$site = isset($ctx['site_tz']) && self::valid_tz($ctx['site_tz']) ? $ctx['site_tz'] : self::site_tz();
		if ($c['tz'] === 'visitor') {
			$v = isset($ctx['visitor_tz']) ? $ctx['visitor_tz'] : '';
			if (is_string($v) && $v !== '' && in_array($v, \DateTimeZone::listIdentifiers(), true)) {
				$now = self::now_ts($ctx);
				if (abs(self::iso_to_jdn(self::today($v, $now)) - self::iso_to_jdn(self::today($site, $now))) <= 1) return $v;
			}
			return $site;
		}
		return $c['tz'] === 'site' ? $site : $c['tz'];
	}
	/** Holiday label of a date, or null. $ctx['holiday_sets']: id => set (default: Calendar::holiday_sets()). */
	public static function holiday_of(array $c, $iso, array $ctx = array()) {
		$jdn = self::iso_to_jdn($iso);
		$hit = function ($rules, $adjust) use ($iso, $jdn) {
			foreach ($rules as $r) {
				if (isset($r['date'])) {
					if (is_array($r['date']) ? ($iso >= $r['date'][0] && $iso <= $r['date'][1]) : $r['date'] === $iso) return $r;
					continue;
				}
				$e = Calendar::engine($r['cal']);
				if (!$e) continue;
				$adj = Calendar::adjust_of($r['cal'], isset($r['adjust']) ? $r['adjust'] : $adjust);
				$loc = $e->from_jdn($jdn + $adj);
				$md = explode('-', $r['md']); $rm = (int) $md[0]; $rd = (int) $md[1]; $days = isset($r['days']) ? (int) $r['days'] : 1;
				for ($y = $loc[0] - 1; $y <= $loc[0]; $y++) {
					if (!$e->is_valid($y, $rm, $rd)) continue;
					$start = $e->to_jdn($y, $rm, $rd) - $adj;
					if ($jdn >= $start && $jdn < $start + $days) return $r;
				}
			}
			return null;
		};
		$r = $hit($c['holidays_custom'], $c['cal_hijri_adjust']);
		if ($r) return array('label' => self::label_of($r['label'], $c['locale']));
		if (!$c['holiday_sets']) return null;
		$sets = isset($ctx['holiday_sets']) && is_array($ctx['holiday_sets']) ? $ctx['holiday_sets'] : self::holiday_sets($c['holiday_sets']);
		foreach ($c['holiday_sets'] as $id) {
			if (empty($sets[$id]) || !is_array($sets[$id])) continue;
			$s = $sets[$id];
			if (isset($s['dates']) && is_array($s['dates']) && array_key_exists($iso, $s['dates'])) return array('label' => self::label_of($s['dates'][$iso], $c['locale']));
			$hr = $hit(self::holiday_rules(isset($s['rules']) ? $s['rules'] : array()), 0);
			if ($hr) return array('label' => self::label_of($hr['label'], $c['locale']));
			$cov = isset($s['explicit']) && is_array($s['explicit']) && count($s['explicit']) === 2 ? $s['explicit'] : null;
			if (!empty($s['fallback']['cal']) && !($cov && $iso >= $cov[0] && $iso <= $cov[1])) {
				$fr = array();
				foreach ((array) (isset($s['fallback']['rules']) ? $s['fallback']['rules'] : array()) as $x) { if (is_array($x)) { $x['cal'] = $s['fallback']['cal']; $fr[] = $x; } }
				$fr = self::holiday_rules($fr);
				foreach ($fr as $i => $x) $fr[$i]['adjust'] = isset($s['fallback']['adjust']) ? (int) $s['fallback']['adjust'] : 0;
				$fh = $hit($fr, 0);
				if ($fh) return array('label' => self::label_of($fh['label'], $c['locale']), 'approx' => true);
			}
		}
		return null;
	}
	/** Resolved constraints (contract §4.2). Applies efb_date_constraints when $ctx['form_id'] or $ctx['filter'] is set. */
	public static function resolve(array $c, array $ctx = array()) {
		$tz = self::zone_of($c, $ctx);
		$today = isset($ctx['today']) && self::iso_valid($ctx['today']) ? $ctx['today'] : self::today($tz, self::now_ts($ctx));
		$values = isset($ctx['values']) && is_array($ctx['values']) ? $ctx['values'] : array();
		$mn = self::eval_limit($c['date_min'], $today, $values);
		$mx = self::eval_limit($c['date_max'], $today, $values);
		return array(
			'today' => $today, 'tz' => $tz,
			'min' => $mn['iso'], 'max' => $mx['iso'], 'min_source' => $mn['source'], 'max_source' => $mx['source'],
			'min_empty' => !empty($mn['empty']), 'max_empty' => !empty($mx['empty']),
			'disabled_weekdays' => $c['disabled_weekdays'], 'disabled' => $c['disabled_dates'], 'enabled' => $c['enabled_dates'],
			'holidays' => new \stdClass(), 'holiday_mode' => $c['holiday_mode'],
			'mode' => $c['date_mode'], 'range' => array('unit' => $c['range_unit'], 'min' => $c['range_min_days'], 'max' => $c['range_max_days']),
			'multi_max' => $c['multi_max'],
			'time' => array('enabled' => (bool) $c['time_enabled'], 'step' => $c['time_step'], 'min' => $c['time_min'], 'max' => $c['time_max'], 'by_weekday' => $c['time_by_weekday'] ? $c['time_by_weekday'] : new \stdClass()),
			'age' => array('min' => $c['age_min'], 'max' => $c['age_max']),
			'calendars' => array('default' => $c['cal_default'], 'allowed' => $c['cal_allowed'], 'switch' => (bool) $c['cal_switch'], 'hijri_adjust' => $c['cal_hijri_adjust']),
		);
	}
	public static function list_holidays(array $c, array $r, array $ctx = array(), $from = null, $to = null) {
		$out = array();
		if (!$c['holidays_custom'] && !$c['holiday_sets']) return $out;
		$a = $from ? $from : ($r['min'] && $r['min'] > $r['today'] ? $r['min'] : $r['today']);
		$b = $to ? $to : self::add_days($r['today'], 400);
		if ($r['max'] && $r['max'] < $b) $b = $r['max'];
		for ($j = self::iso_to_jdn($a), $e = self::iso_to_jdn($b); $j <= $e; $j++) {
			$iso = self::jdn_to_iso($j); $h = self::holiday_of($c, $iso, $ctx);
			if ($h) $out[$iso] = $h['label'];
		}
		return $out;
	}
	private static function in_list($list, $iso) {
		foreach ($list as $x) {
			if (is_array($x) ? ($iso >= $x[0] && $iso <= $x[1]) : $x === $iso) return true;
		}
		return false;
	}
	/** First rule (F06 §6.1 order) that refuses the date; null when allowed. */
	public static function refuse(array $c, array $r, $iso, array $ctx = array()) {
		if (!self::iso_valid($iso)) return array('code' => 'invalid_date', 'date' => $iso);
		if ($r['min'] && $iso < $r['min']) return array('code' => 'before_min', 'date' => $iso, 'limit' => $r['min']);
		if ($r['max'] && $iso > $r['max']) return array('code' => 'after_max', 'date' => $iso, 'limit' => $r['max']);
		if ($c['enabled_dates'] && !self::in_list($c['enabled_dates'], $iso)) return array('code' => 'not_enabled', 'date' => $iso);
		if (self::in_list($c['disabled_dates'], $iso)) return array('code' => 'date_disabled', 'date' => $iso);
		if (in_array(self::weekday($iso), $c['disabled_weekdays'], true)) return array('code' => 'weekday_disabled', 'date' => $iso);
		if ($c['holiday_mode'] === 'disable') {
			$h = self::holiday_of($c, $iso, $ctx);
			if ($h) return array('code' => 'holiday', 'date' => $iso, 'limit' => $h['label']);
		}
		if (isset($ctx['availability']) && is_callable($ctx['availability'])) {
			$a = call_user_func($ctx['availability'], $iso);
			if (is_array($a) && array_key_exists('available', $a) && $a['available'] === false) return array('code' => isset($a['code']) && $a['code'] === 'full' ? 'full' : 'unavailable', 'date' => $iso);
		}
		return null;
	}
	public static function disp_text(array $c, $cal, $iso, $time = null) {
		$e = self::engine($cal); $l = self::iso_to_local($iso, $cal, $c['cal_hijri_adjust']);
		$dt = array($l[0], $l[1], $l[2]);
		$s = self::format_local($e, $dt, self::medium_pattern($c['locale']), $c['locale'], array('digits' => $c['digits']));
		if ($time) $s .= ' ' . self::format_local($e, $dt, $c['time_format'] === '12' ? 'hh:mm A' : 'HH:mm', $c['locale'], array('digits' => $c['digits'], 'time' => $time));
		return $s;
	}
	public static function joiner(array $c, $mode) {
		if ($mode === 'range') return ' ' . "\u{2013}" . ' ';
		return in_array(self::lang($c['locale']), array('fa', 'ar', 'ur'), true) ? "\u{060C} " : ', ';
	}
	/** The stored row (contract §6) of a canonical value; every key recomputed here, never taken from the browser. */
	public static function build_row(array $c, $cal, $value, array $r) {
		$mode = $c['date_mode']; $time = null;
		if ($mode === 'single') { $p = explode('T', $value); $dates = array($p[0]); $time = isset($p[1]) ? $p[1] : null; }
		else $dates = explode($mode === 'range' ? '/' : ',', $value);
		$locals = array(); $disps = array(); $approx = false;
		foreach ($dates as $i => $d) {
			$l = self::iso_to_local($d, $cal, $c['cal_hijri_adjust']);
			$locals[] = self::iso_of($l[0], $l[1], $l[2]) . ($i === 0 && $time ? 'T' . $time : '');
			$approx = $approx || $l[3];
			$disps[] = self::disp_text($c, $cal, $d, $i === 0 ? $time : null);
		}
		$row = array('value' => $value, 'cal' => $cal, 'local' => implode($mode === 'range' ? '/' : ',', $locals), 'disp' => implode(self::joiner($c, $mode), $disps), 'tz' => $r['tz'], 'mode' => $mode, 'v' => 1);
		if ($time) { $row['time'] = $time; $u = self::wall_to_utc($dates[0], $time, $r['tz']); if ($u) $row['utc'] = $u; }
		if ($approx) $row['approx'] = 1;
		if ($c['store_format'] === 'legacy_local') { $row['iso'] = $value; $row['value'] = $row['local']; }
		return $row;
	}
	private static function hm_min($hm) { $p = explode(':', $hm); return (int) $p[0] * 60 + (int) $p[1]; }
	public static function time_error(array $c, array $r, $iso, $time) {
		$wd = (string) self::weekday($iso);
		if (array_key_exists($wd, $c['time_by_weekday'])) {
			$windows = $c['time_by_weekday'][$wd];
			if (!$windows) return array('code' => 'time_closed_weekday', 'date' => $iso);
		} else {
			$windows = array(array($c['time_min'] !== '' ? $c['time_min'] : '00:00', $c['time_max'] !== '' ? $c['time_max'] : '23:59'));
		}
		$t = self::hm_min($time); $win = null;
		foreach ($windows as $w) { if ($t >= self::hm_min($w[0]) && $t <= self::hm_min($w[1])) { $win = $w; break; } }
		if (!$win) return array('code' => 'time_out_of_range', 'date' => $iso, 'limit' => implode(',', array_map(function ($w) { return $w[0] . '-' . $w[1]; }, $windows)));
		if (($t - self::hm_min($win[0])) % $c['time_step'] !== 0) return array('code' => 'time_step', 'date' => $iso, 'limit' => $c['time_step']);
		if (self::wall_to_utc($iso, $time, $r['tz']) === null) return array('code' => 'time_out_of_range', 'date' => $iso);
		return null;
	}
	/**
	 * Full check of a submitted value (F06 §7; twin of EFBCalendar.rules.validateValue).
	 * @return array{ok:bool,row:array|null,errors:array,warnings:array,resolved:array}
	 */
	public static function validate_value($field, $raw, array $ctx = array()) {
		$c = isset($ctx['normalized']) && is_array($ctx['normalized']) ? $ctx['normalized'] : self::normalize_field($field, $ctx);
		$r = isset($ctx['resolved']) && is_array($ctx['resolved']) ? $ctx['resolved'] : self::resolve($c, $ctx);
		$warnings = array();
		$fail = function ($e) use (&$warnings, $r) { return array('ok' => false, 'row' => null, 'errors' => array($e), 'warnings' => $warnings, 'resolved' => $r); };
		$s = preg_replace('#\s*([/,T])\s*#', '$1', self::normalize(is_scalar($raw) ? $raw : ''));
		$cal = isset($ctx['cal']) && is_string($ctx['cal']) && in_array($ctx['cal'], $c['cal_allowed'], true) ? $ctx['cal'] : $c['cal_default'];
		if ($s === '') {
			if ($c['required']) return $fail(array('code' => 'required'));
			return array('ok' => true, 'row' => array('value' => ''), 'errors' => array(), 'warnings' => $warnings, 'resolved' => $r);
		}
		if ($c['store_format'] === 'legacy_local') {
			$tp = explode('T', $s, 2);
			$parts = preg_split('#([/,])#', $tp[0], -1, PREG_SPLIT_DELIM_CAPTURE);
			foreach ($parts as $i => $x) {
				if ($x === '/' || $x === ',' || !preg_match('/^(\d{4})-(\d{1,2})-(\d{1,2})$/', $x, $m) || (int) $m[1] >= 1700) continue;
				$iso = self::local_to_iso(array((int) $m[1], (int) $m[2], (int) $m[3]), $c['cal_default'], $c['cal_hijri_adjust']);
				$parts[$i] = $iso ? $iso : '0000-00-00';
			}
			$s = implode('', $parts) . (isset($tp[1]) ? 'T' . $tp[1] : '');
		}
		$D = '\d{4}-\d{2}-\d{2}';
		$re = $c['date_mode'] === 'range' ? '#^' . $D . '/' . $D . '$#' : ($c['date_mode'] === 'multiple' ? '#^' . $D . '(,' . $D . ')*$#' : '#^' . $D . '(T\d{2}:\d{2})?$#');
		if (!preg_match($re, $s)) return $fail(array('code' => 'invalid_format'));
		$time = null;
		if ($c['date_mode'] === 'single') { $sp = explode('T', $s); $dates = array($sp[0]); $time = isset($sp[1]) ? $sp[1] : null; }
		else $dates = explode($c['date_mode'] === 'range' ? '/' : ',', $s);
		foreach ($dates as $d) if (!self::iso_valid($d)) return $fail(array('code' => 'invalid_date', 'date' => $d));
		if ($time !== null && (!$c['time_enabled'] || !preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $time))) return $fail(array('code' => 'invalid_format'));
		if ($c['time_enabled'] && $c['date_mode'] === 'single' && $time === null) return $fail(array('code' => 'time_required'));
		if ($c['date_mode'] === 'range') {
			$a = $dates[0]; $b = $dates[1];
			if ($b < $a) return $fail(array('code' => 'range_order'));
			$n = self::nights($a, $b); $len = $c['range_unit'] === 'nights' ? $n : $n + 1;
			if ($n > 3660) return $fail(array('code' => 'range_too_long', 'limit' => 3660));
			if ($c['range_min_days'] && $len < $c['range_min_days']) return $fail(array('code' => 'range_too_short', 'limit' => $c['range_min_days']));
			if ($c['range_max_days'] && $len > $c['range_max_days']) return $fail(array('code' => 'range_too_long', 'limit' => $c['range_max_days']));
			if ($e = self::refuse($c, $r, $a, $ctx)) return $fail($e);
			$last = $c['range_unit'] === 'nights' ? $n - 1 : $n;
			for ($k = 1; $k <= $last; $k++) {
				$d = self::add_days($a, $k);
				if ($x = self::refuse($c, $r, $d, $ctx)) return $fail(array('code' => 'range_contains_disabled', 'date' => $d, 'limit' => $x['code']));
			}
			if ($c['range_unit'] === 'nights' && $n > 0 && $r['max'] && $b > $r['max']) return $fail(array('code' => 'after_max', 'date' => $b, 'limit' => $r['max']));
			$s = $a . '/' . $b;
		} elseif ($c['date_mode'] === 'multiple') {
			$u = array_values(array_unique($dates));
			sort($u);
			if ($c['multi_max'] && count($u) > $c['multi_max']) return $fail(array('code' => 'too_many_dates', 'limit' => $c['multi_max']));
			foreach ($u as $d) if ($e = self::refuse($c, $r, $d, $ctx)) return $fail($e);
			$s = implode(',', $u);
		} else {
			if ($e = self::refuse($c, $r, $dates[0], $ctx)) return $fail($e);
			if ($time !== null && ($e = self::time_error($c, $r, $dates[0], $time))) return $fail($e);
			if ($c['age_min'] !== null || $c['age_max'] !== null) {
				$age = self::age_on($dates[0], $r['today']);
				if ($c['age_min'] !== null && $age < $c['age_min']) return $fail(array('code' => 'age_too_young', 'limit' => $c['age_min']));
				if ($c['age_max'] !== null && $age > $c['age_max']) return $fail(array('code' => 'age_too_old', 'limit' => $c['age_max']));
			}
		}
		if ($r['min_empty'] || $r['max_empty']) $warnings[] = array('code' => 'depends_on_empty');
		$row = self::build_row($c, $cal, $s, $r);
		if (!empty($row['approx'])) $warnings[] = array('code' => 'approximate_date');
		return array('ok' => true, 'row' => $row, 'errors' => array(), 'warnings' => $warnings, 'resolved' => $r);
	}

	/* ---------------------------------------------------------------- messages */

	/** English defaults of the visitor messages (text_efb() keys cal_err_<code>; JS twin EFBCalendar.messages). */
	const MESSAGES = array(
		'invalid_format'          => 'Please enter the date as %s.',
		'invalid_date'            => 'This date does not exist.',
		'calendar_not_allowed'    => 'This calendar cannot be used in this field.',
		'before_min'              => 'Please choose %s or a later date.',
		'after_max'               => 'Please choose %s or an earlier date.',
		'weekday_disabled'        => 'This day of the week cannot be chosen.',
		'date_disabled'           => 'This date cannot be chosen.',
		'not_enabled'             => 'This date cannot be chosen.',
		'holiday'                 => 'This date is a holiday: %s.',
		'unavailable'             => 'This date is not available.',
		'full'                    => 'This date is fully booked.',
		'range_order'             => 'The end date must come after the start date.',
		'range_too_short'         => 'Please choose at least %s.',
		'range_too_long'          => 'Please choose at most %s.',
		'range_contains_disabled' => 'The dates you chose include %s, which cannot be chosen.',
		'too_many_dates'          => 'You can choose at most %s dates.',
		'time_required'           => 'Please choose a time.',
		'time_out_of_range'       => 'This time is not available.',
		'time_step'               => 'Please choose a time in steps of %s minutes.',
		'time_closed_weekday'     => 'No time can be chosen on this day.',
		'age_too_young'           => 'You must be at least %s years old.',
		'age_too_old'             => 'The age can be at most %s years.',
		'depends_on_empty'        => 'Please choose the date it depends on first.',
		'required'                => 'Please fill in this field.',
		'nights_n'                => '%s nights',
		'days_n'                  => '%s days',
	);

	/**
	 * The visitor sentence of an error (plain text; a date is shown in the field's calendar and wrapped in
	 * FSI / PDI so it keeps its order inside a right-to-left sentence). $lang: text_efb() map (cal_err_*).
	 */
	public static function message(array $err, array $c, $cal = '', array $lang = array()) {
		$code = isset($err['code']) ? $err['code'] : 'invalid_date';
		$t = isset($lang['cal_err_' . $code]) && $lang['cal_err_' . $code] !== '' ? $lang['cal_err_' . $code] : (isset(self::MESSAGES[$code]) ? self::MESSAGES[$code] : self::MESSAGES['invalid_date']);
		$cal = $cal !== '' ? $cal : $c['cal_default'];
		$iso_text = function ($iso) use ($c, $cal) { return "\u{2068}" . self::disp_text($c, $cal, $iso) . "\u{2069}"; };
		$arg = '';
		switch ($code) {
			case 'before_min': case 'after_max':
				$arg = isset($err['limit']) && self::iso_valid($err['limit']) ? $iso_text($err['limit']) : ''; break;
			case 'range_contains_disabled':
				$arg = isset($err['date']) && self::iso_valid($err['date']) ? $iso_text($err['date']) : ''; break;
			case 'holiday':
				if (empty($err['limit'])) $t = isset($lang['cal_err_date_disabled']) ? $lang['cal_err_date_disabled'] : self::MESSAGES['date_disabled'];
				$arg = isset($err['limit']) ? "\u{2068}" . (string) $err['limit'] . "\u{2069}" : ''; break;
			case 'range_too_short': case 'range_too_long':
				$k = $c['range_unit'] === 'nights' ? 'nights_n' : 'days_n';
				$u = isset($lang['cal_' . $k]) && $lang['cal_' . $k] !== '' ? $lang['cal_' . $k] : self::MESSAGES[$k];
				$arg = str_replace('%s', self::to_digits((string) (isset($err['limit']) ? $err['limit'] : ''), $c['digits']), $u); break;
			case 'invalid_format':
				$arg = "\u{2068}" . self::format_hint($c, $cal) . "\u{2069}"; break;
			default:
				$arg = isset($err['limit']) && is_scalar($err['limit']) ? self::to_digits((string) $err['limit'], $c['digits']) : '';
		}
		return str_replace('%s', $arg, $t);
	}
	/** The typed pattern of a calendar on this field ('YYYY/MM/DD'). */
	public static function typed_pattern_of(array $c, $cal) {
		return $c['display_format'] !== 'auto' ? $c['display_format'] : self::typed_pattern($cal, $c['locale']);
	}
	/** The format hint shown to visitors: the pattern with a sample date ('1403/12/30'). */
	public static function format_hint(array $c, $cal) {
		$e = self::engine($cal);
		$l = self::iso_to_local('2025-03-20', $cal, 0);
		return $e ? self::format_local($e, array($l[0], $l[1], $l[2]), self::typed_pattern_of($c, $cal), $c['locale'], array('digits' => $c['digits'])) : '';
	}

	/* ---------------------------------------------------------------- page integration (F06 §9) */

	/** Row keys of an advdate field (contract §4.1); bk_* are the Booking add-on's and never read by core. */
	const FIELD_KEYS = array('cal_v', 'cal_default', 'cal_allowed', 'cal_switch', 'cal_secondary', 'cal_hijri_adjust', 'cal_locale', 'cal_digits',
		'week_start', 'date_mode', 'date_min', 'date_max', 'disabled_weekdays', 'disabled_dates', 'enabled_dates', 'holiday_sets', 'holidays_custom',
		'holiday_mode', 'range_unit', 'range_min_days', 'range_max_days', 'multi_max', 'time_enabled', 'time_format', 'time_step', 'time_min',
		'time_max', 'time_by_weekday', 'age_min', 'age_max', 'tz', 'display_format', 'store_format', 'input_mode', 'availability');

	public static function plan_name($rank = null) {
		$r = $rank === null ? self::site_plan_rank() : (int) $rank;
		return $r >= 2 ? 'pro' : ($r === 1 ? 'free_plus' : 'free');
	}
	/** A form JSON (raw form_structer text, escaped or not) holds an advdate field. */
	public static function json_has_advdate($json) {
		return is_string($json) && (bool) preg_match('/type\\\\?"\s*:\s*\\\\?"advdate\\\\?"/', $json);
	}
	/** The advdate keys of a row (object or array), as sent to the picker (public data: the form JSON is public anyway). */
	public static function row_keys($vj) {
		$row = is_object($vj) ? json_decode(self::json($vj), true) : (array) $vj;
		$out = array('type' => 'advdate', 'id_' => isset($row['id_']) ? (string) $row['id_'] : '');
		foreach (self::FIELD_KEYS as $k) if (array_key_exists($k, $row)) $out[$k] = $row[$k];
		$out['required'] = isset($row['required']) && in_array($row['required'], array(1, '1', true, 'true'), true) ? 1 : 0;
		return $out;
	}
	/** The data-efb-cal JSON of one field: the row keys plus the page context the twins need to agree. */
	public static function public_config($vj, $form_id, $locale = '') {
		$locale = $locale !== '' ? $locale : (function_exists('get_locale') ? get_locale() : 'en_US');
		$row = self::row_keys($vj);
		$value = is_object($vj) && isset($vj->value) ? $vj->value : (is_array($vj) && isset($vj['value']) ? $vj['value'] : '');
		$initial = is_string($value) && preg_match('#^(today([+-]\d{1,4})?|\d{4}-\d{2}-\d{2}([T/,][0-9:\-/,T]*)?)$#', trim($value)) ? trim($value) : '';
		return array(
			'v' => 1, 'form_id' => (int) $form_id, 'field' => $row, 'locale' => $locale,
			'locale_defaults' => self::locale_defaults_filtered($locale), 'plan' => self::plan_name(),
			'site' => array('tz' => self::site_tz(), 'today' => self::today('site')), 'initial' => $initial,
		);
	}
	/** Scripts and styles of the picker, only the engines this form can show (F06 §9). */
	public static function enqueue_public($json, $locale = '') {
		if (!function_exists('wp_enqueue_script') || !self::json_has_advdate($json)) return false;
		$locale = $locale !== '' ? $locale : (function_exists('get_locale') ? get_locale() : 'en_US');
		$dir = dirname(__DIR__) . '/';
		$url = defined('EMSFB_PLUGIN_URL') ? EMSFB_PLUGIN_URL : '';
		$ver = function ($rel) use ($dir) { return is_readable($dir . $rel) ? (string) filemtime($dir . $rel) : (defined('EMSFB_PLUGIN_VERSION') ? EMSFB_PLUGIN_VERSION : '1'); };
		$base = 'public/assets/js/calendar/';
		wp_register_script('efb-cal-core', $url . $base . 'efb-calendar-core.js', array(), $ver($base . 'efb-calendar-core.js'), true);
		wp_enqueue_script('efb-cal-core');
		$ld = self::locale_defaults_filtered($locale);
		$text = stripslashes((string) $json);
		$need = array('persian' => 'efb-cal-persian.js', 'islamic' => 'efb-cal-islamic.js', 'intl' => 'efb-cal-intl.js');
		$uses = array(
			'persian' => strpos($text, 'persian') !== false || $ld['default'] === 'persian' || in_array('persian', $ld['allowed'], true),
			'islamic' => strpos($text, 'islamic') !== false || strpos($ld['default'] . implode(',', $ld['allowed']) . $ld['secondary'], 'islamic') !== false,
			'intl'    => (bool) preg_match('/buddhist|roc|japanese|hebrew|indian|coptic|ethiopic/', $text . $ld['default'] . implode(',', $ld['allowed']) . $ld['secondary']),
		);
		$deps = array('efb-cal-core');
		foreach ($need as $k => $file) {
			if (!$uses[$k]) continue;
			wp_register_script('efb-cal-' . $k, $url . $base . $file, array('efb-cal-core'), $ver($base . $file), true);
			wp_enqueue_script('efb-cal-' . $k);
			$deps[] = 'efb-cal-' . $k;
		}
		wp_register_script('efb-cal-ui', $url . $base . 'efb-calendar-ui.js', $deps, $ver($base . 'efb-calendar-ui.js'), true);
		wp_enqueue_script('efb-cal-ui');
		wp_enqueue_style('efb-cal-css', $url . 'public/assets/css/calendar-efb.css', array(), $ver('public/assets/css/calendar-efb.css'));
		if (function_exists('is_rtl') && is_rtl()) wp_enqueue_style('efb-cal-rtl-css', $url . 'public/assets/css/calendar-rtl-efb.css', array('efb-cal-css'), $ver('public/assets/css/calendar-rtl-efb.css'));
		$names = self::names();
		$l = self::lang($locale);
		$pick = array();
		foreach (array_unique(array($l, 'en')) as $k) if (isset($names[$k])) $pick[$k] = $names[$k];
		$boot = 'window.EFB_CAL_HOLIDAYS_URL=' . wp_json_encode($url . 'public/assets/json/holidays/') . ';'
			. 'if(window.EFBCalendar){EFBCalendar._.setNames(' . wp_json_encode($pick, JSON_UNESCAPED_UNICODE) . ');EFBCalendar._.setSiteTz(' . wp_json_encode(self::site_tz()) . ');}';
		wp_add_inline_script('efb-cal-core', $boot, 'after');
		return true;
	}

	/* ---------------------------------------------------------------- save (builder, AutoPilot) */

	/**
	 * One advdate key as the builder save stores it (sanitize_obj_msg_efb() calls this for advdate rows;
	 * the same allow-lists as the JSON Schema of contract §7.1). Arrays and objects keep their shape;
	 * a value that does not fit becomes the key's default. bk_* keys (Booking add-on) keep any JSON of
	 * plain text, three levels deep.
	 */
	public static function sanitize_key($k, $v) {
		$txt = function ($x) { return function_exists('sanitize_text_field') ? sanitize_text_field((string) $x) : trim(strip_tags((string) $x)); };
		$known = function ($c, $tier3 = false) { return is_string($c) && (in_array($c, self::TIER12, true) || ($tier3 && in_array($c, self::TIER3, true))); };
		$int = function ($x, $lo, $hi, $d) { return self::int_in($x, $lo, $hi, $d); };
		$hm = function ($x) { return is_string($x) && preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $x) ? $x : ''; };
		if (strpos((string) $k, 'bk_') === 0) {
			$clean = function ($x, $depth) use (&$clean, $txt) {
				if (is_array($x) || is_object($x)) {
					if ($depth > 3) return array();
					$o = array();
					foreach ((array) $x as $kk => $vv) $o[is_int($kk) ? $kk : $txt($kk)] = $clean($vv, $depth + 1);
					return $o;
				}
				return is_bool($x) || is_int($x) || is_float($x) ? $x : $txt($x);
			};
			return $clean($v, 0);
		}
		switch ($k) {
			case 'cal_v': return 1;
			case 'cal_default': return $known($v) ? $v : 'auto';
			case 'cal_allowed':
				$o = array();
				foreach (self::list_of($v) as $c) if ($known($c) && !in_array($c, $o, true)) $o[] = $c;
				return $o;
			case 'cal_switch': case 'time_enabled': return in_array($v, array(1, '1', true, 'true'), true) ? 1 : 0;
			case 'cal_secondary': return $known($v, true) ? $v : '';
			case 'cal_hijri_adjust': return $int($v, -2, 2, 0);
			case 'cal_locale': return is_string($v) && preg_match('/^[a-zA-Z]{2,3}([_-][A-Za-z0-9]{2,8})*$/', $v) ? $v : 'auto';
			case 'cal_digits': return in_array($v, array('latn', 'arabext', 'arab'), true) ? $v : 'auto';
			case 'week_start': $w = $int($v, 1, 7, null); return $w === null ? 'auto' : $w;
			case 'date_mode': return in_array($v, array('single', 'range', 'multiple'), true) ? $v : 'single';
			case 'date_min': case 'date_max': return self::limit_of($v);
			case 'disabled_weekdays':
				$o = array();
				foreach (self::list_of($v) as $w) { $w = $int($w, 1, 7, null); if ($w !== null && !in_array($w, $o, true)) $o[] = $w; }
				sort($o);
				return $o;
			case 'disabled_dates': case 'enabled_dates':
				return array_map(function ($x) { return is_array($x) ? $x[0] . '/' . $x[1] : $x; }, self::dates_list($v));
			case 'holiday_sets':
				$o = array();
				foreach (self::list_of($v) as $h) if (is_string($h) && preg_match('/^[a-z0-9-]{2,40}$/', $h) && !in_array($h, $o, true) && count($o) < 20) $o[] = $h;
				return $o;
			case 'holidays_custom':
				$o = array();
				foreach (self::holiday_rules($v) as $r) {
					$l = $r['label'];
					$l = is_array($l) ? array_map($txt, array_filter($l, 'is_scalar')) : $txt($l);
					if (isset($r['date'])) $o[] = array('date' => is_array($r['date']) ? $r['date'][0] . '/' . $r['date'][1] : $r['date'], 'label' => $l);
					else $o[] = array('cal' => $r['cal'], 'md' => $r['md'], 'days' => $r['days'], 'label' => $l);
				}
				return $o;
			case 'holiday_mode': return $v === 'mark' ? 'mark' : 'disable';
			case 'range_unit': return $v === 'days' ? 'days' : 'nights';
			case 'range_min_days': case 'range_max_days': return $int($v, 0, 3660, 0);
			case 'multi_max': return $int($v, 0, 366, 0);
			case 'time_format': return in_array((string) $v, array('12', '24'), true) ? (string) $v : 'auto';
			case 'time_step': return $int($v, 1, 720, 15);
			case 'time_min': case 'time_max': return $hm($v);
			case 'time_by_weekday': $w = self::weekday_windows($v); return $w ? $w : new \stdClass();
			case 'age_min': case 'age_max': $a = $int($v, 0, 150, null); return $a === null ? '' : $a;
			case 'tz': return ($v === 'visitor' || (is_string($v) && $v !== 'site' && in_array($v, \DateTimeZone::listIdentifiers(), true))) ? $v : 'site';
			case 'display_format': return is_string($v) && preg_match('#^(YYYY|MM|M|DD|D|[/.\- ])+$#', $v) && strpos($v, 'YYYY') !== false && strpos($v, 'M') !== false && strpos($v, 'D') !== false ? $v : 'auto';
			case 'store_format': return $v === 'legacy_local' ? 'legacy_local' : 'iso';
			case 'input_mode': return in_array($v, array('both', 'picker', 'typed'), true) ? $v : 'both';
			case 'availability': return is_string($v) && preg_match('/^[a-z0-9_-]{1,40}$/', $v) ? $v : 'none';
		}
		return is_scalar($v) ? $txt($v) : '';
	}
	/** Keys sanitize_key() owns (the save sanitizer switches on these for advdate rows). */
	public static function is_key($k) { return in_array($k, self::FIELD_KEYS, true) || strpos((string) $k, 'bk_') === 0; }

	/* ---------------------------------------------------------------- holiday sets (F06 §16) */

	private static $holiday_cache = array();

	/** Available set ids (files in public/assets/json/holidays plus efb_calendar_holiday_sets). */
	public static function holiday_set_ids() {
		$ids = array();
		foreach ((array) glob(dirname(__DIR__) . '/public/assets/json/holidays/*.json') as $f) {
			$id = basename((string) $f, '.json');
			if (preg_match('/^[a-z0-9-]{2,40}$/', $id)) $ids[] = $id;
		}
		if (function_exists('apply_filters')) {
			$extra = apply_filters('efb_calendar_holiday_sets', array());
			if (is_array($extra)) foreach (array_keys($extra) as $id) if (is_string($id) && !in_array($id, $ids, true)) $ids[] = $id;
		}
		return $ids;
	}
	/** id => set data for the ids asked (files cached per version in a transient; filter efb_calendar_holiday_sets). */
	public static function holiday_sets(array $ids) {
		$out = array();
		foreach ($ids as $id) {
			if (!is_string($id) || !preg_match('/^[a-z0-9-]{2,40}$/', $id)) continue;
			if (!array_key_exists($id, self::$holiday_cache)) {
				$file = dirname(__DIR__) . '/public/assets/json/holidays/' . $id . '.json';
				$data = null;
				if (is_readable($file)) {
					$key = 'efb_hol_' . $id . '_' . filemtime($file);
					$data = function_exists('get_transient') ? get_transient($key) : false;
					if (!is_array($data)) {
						$data = json_decode((string) file_get_contents($file), true);
						if (is_array($data) && function_exists('set_transient')) set_transient($key, $data, DAY_IN_SECONDS);
					}
				}
				self::$holiday_cache[$id] = is_array($data) ? $data : null;
			}
			if (self::$holiday_cache[$id] !== null) $out[$id] = self::$holiday_cache[$id];
		}
		if (function_exists('apply_filters')) {
			$f = apply_filters('efb_calendar_holiday_sets', $out);
			if (is_array($f)) foreach ($ids as $id) if (isset($f[$id]) && is_array($f[$id])) $out[$id] = $f[$id];
		}
		return $out;
	}
}

