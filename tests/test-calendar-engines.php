<?php
/**
 * F06-B: the server's calendar engines (includes/class-Emsfb-calendar.php, \Emsfb\Calendar), without
 * WordPress and without ext-intl (run it with `php -n` too), against the same verified vectors as the
 * picker (docs/feature-gaps/reference/calendar-test-vectors.json).
 *
 *  1. All 91 vectors (local -> JDN -> Gregorian and back, ISO weekday).
 *  2. Every edge case: validity (1404-12-30 and 1446-09-30 refused), month lengths, Hijri adjustment,
 *     typed text, age, nights, weekday, today in a time zone, the Umm al-Qura range and fallback.
 *  3. With ext-intl: day by day against IntlCalendar (Jalali AP 1300..1499, islamic-civil and
 *     islamic-tbla AH 1300..1599, Umm al-Qura only AH 1420..1450); skipped without it.
 *  4. Format / parse / convert / units / wall time with DST, the locale table: the same answers as
 *     tests/test-calendar-engines.js (the twins are compared in bulk by tests/test-calendar-parity.js).
 *
 * Run: php tests/test-calendar-engines.php   and   php -n tests/test-calendar-engines.php
 */
define('ABSPATH', __DIR__ . '/');
require_once __DIR__ . '/../includes/class-Emsfb-calendar.php';
require_once __DIR__ . '/../includes/calendar-functions-efb.php';
use Emsfb\Calendar as C;

$passed = 0; $failed = 0;
function t($name, $actual, $expected) {
	global $passed, $failed;
	if ($actual === $expected) { $passed++; echo "[PASS] {$name}\n"; return; }
	$failed++; echo "[FAIL] {$name}\n   expected " . json_encode($expected, JSON_UNESCAPED_UNICODE) . "\n   actual   " . json_encode($actual, JSON_UNESCAPED_UNICODE) . "\n";
}
$V = json_decode(file_get_contents(__DIR__ . '/../docs/feature-gaps/reference/calendar-test-vectors.json'), true);
$sp = function ($s) { return array_map('intval', explode('-', $s)); };

/* 1 */
$ok = 0;
foreach ($V['vectors'] as $v) {
	list($y, $m, $d) = $sp($v['date']);
	$e = C::engine($v['calendar']);
	$j = $e->to_jdn($y, $m, $d);
	if ($j === $v['jdn'] && C::jdn_to_iso($j) === $v['gregorian'] && $e->from_jdn($j) === array($y, $m, $d) && C::weekday_of_jdn($j) === $v['weekday_iso']) $ok++;
	else echo '   vector failed: ' . json_encode($v) . "\n";
}
t('1 vectors ' . count($V['vectors']) . '/91', $ok, 91);

/* 2 */
foreach ($V['edge_cases'] as $c) {
	switch ($c['kind']) {
		case 'validity':
			list($y, $m, $d) = $sp($c['date']);
			t("2 validity {$c['calendar']} {$c['date']}", C::engine($c['calendar'])->is_valid($y, $m, $d), $c['valid']);
			if ($c['valid'] && isset($c['gregorian'])) t("2 validity {$c['date']} = {$c['gregorian']}", C::local_to_iso(array($y, $m, $d), $c['calendar']), $c['gregorian']);
			break;
		case 'month_lengths':
			$L = array(); for ($m = 1; $m <= 12; $m++) $L[] = C::engine($c['calendar'])->days_in_month($c['year'], $m);
			t("2 month lengths {$c['calendar']} {$c['year']}", $L, $c['lengths']);
			break;
		case 'hijri_adjust':
			$l = C::iso_to_local($c['gregorian'], 'islamic-umalqura', $c['cal_hijri_adjust']);
			t("2 hijri adjust {$c['cal_hijri_adjust']}", C::iso_of($l[0], $l[1], $l[2]), $c['displayed']);
			t("2 hijri adjust {$c['cal_hijri_adjust']} back", C::local_to_iso($sp($c['displayed']), 'islamic-umalqura', $c['cal_hijri_adjust']), $c['gregorian']);
			break;
		case 'normalize':
			$p = C::parse_local(C::engine($c['calendar']), $c['input'], 'YYYY-MM-DD');
			t("2 normalize {$c['calendar']} " . json_encode($c['input'], JSON_UNESCAPED_UNICODE), $p ? C::iso_of($p[0], $p[1], $p[2]) : null, $c['normalized']);
			if ($p && isset($c['gregorian'])) t('2 normalize = ' . $c['gregorian'], C::local_to_iso($p, $c['calendar']), $c['gregorian']);
			break;
		case 'age':
			t("2 age {$c['birth']} on {$c['on']}", C::age_on($c['birth'], $c['on']), $c['completed_years']);
			if (isset($c['birth_persian'])) { $r = efb_calendar_convert($c['birth_persian'], 'persian', 'gregory'); t('2 age Persian birth', $r['iso'], $c['birth']); }
			break;
		case 'range_nights':
			t("2 nights {$c['start']}..{$c['end']}", C::nights($c['start'], $c['end']), $c['nights']);
			$r = efb_calendar_convert($c['start'], 'gregory', 'persian'); t('2 nights start Persian', $r['iso'], $c['start_persian']);
			$r = efb_calendar_convert($c['end'], 'gregory', 'persian'); t('2 nights end Persian', $r['iso'], $c['end_persian']);
			break;
		case 'weekday':
			t("2 weekday {$c['gregorian']}", C::weekday($c['gregorian']), $c['weekday_iso']);
			break;
		case 'today_in_timezone':
			$d = C::today($c['tz'], strtotime($c['instant']));
			t("2 today {$c['instant']} in {$c['tz']}", $d, $c['local_date']);
			$r = efb_calendar_convert($d, 'gregory', 'persian'); t("2 today {$c['tz']} Persian", $r['iso'], $c['persian']);
			$r = efb_calendar_convert($d, 'gregory', 'islamic-umalqura'); t("2 today {$c['tz']} Umm al-Qura", $r['iso'], $c['umalqura']);
			break;
		case 'range':
			if ($c['calendar'] === 'islamic-umalqura') {
				$r = efb_calendar_convert($c['first'], 'islamic-umalqura', 'gregory'); t('2 Umm al-Qura first', $r['iso'], $c['first_gregorian']);
				$r = efb_calendar_convert($c['last'], 'islamic-umalqura', 'gregory'); t('2 Umm al-Qura last', $r['iso'], $c['last_gregorian']);
				$a = efb_calendar_convert('1501-01-01', 'islamic-umalqura', 'gregory'); $b = efb_calendar_convert('1501-01-01', 'islamic-civil', 'gregory');
				t('2 outside the table: approximate, islamic-civil', array($a['approx'], $a['iso']), array(true, $b['iso']));
			}
			break;
	}
}
t('2 table = vectors file', \Emsfb\Calendar_Umm_Al_Qura::table(), array('firstYear' => $V['ummalqura_compact']['firstYear'], 'lastYear' => $V['ummalqura_compact']['lastYear'], 'firstJdn' => $V['ummalqura_compact']['firstJdn'], 'masksHex' => $V['ummalqura_compact']['masksHex']));

/* 3 */
if (extension_loaded('intl')) {
	$icu = function ($cal, $jdn) {
		static $cache = array();
		if (!isset($cache[$cal])) $cache[$cal] = IntlCalendar::createInstance('UTC', 'en_US@calendar=' . $cal);
		$ic = $cache[$cal]; $ic->setTime((float) ($jdn - 2440588) * 86400000.0 + 43200000.0);
		return array($ic->get(IntlCalendar::FIELD_EXTENDED_YEAR), $ic->get(IntlCalendar::FIELD_MONTH) + 1, $ic->get(IntlCalendar::FIELD_DAY_OF_MONTH));
	};
	foreach (array(array('persian', 1300, 1499, 73049), array('islamic-civil', 1300, 1599, 106310), array('islamic-tbla', 1300, 1599, 106310), array('islamic-umalqura', 1420, 1450, 10985)) as $w) {
		$e = C::engine($w[0]); $a = 0; $n = 0; $rt = 0;
		for ($j = $e->to_jdn($w[1], 1, 1); $j < $e->to_jdn($w[2] + 1, 1, 1); $j++) {
			$n++; $mine = $e->from_jdn($j);
			if ($mine === $icu($w[0], $j)) $a++;
			if ($e->to_jdn($mine[0], $mine[1], $mine[2]) === $j) $rt++;
		}
		t("3 {$w[0]} vs IntlCalendar {$w[1]}..{$w[2]}", array($n, $a, $rt), array($w[3], $w[3], $w[3]));
	}
} else {
	echo "[SKIP] 3 ext-intl not loaded: day-by-day comparison skipped\n";
}

/* 4 */
$P = C::engine('persian'); $G = C::engine('gregory'); $U = C::engine('islamic-umalqura');
t('4 format fa long', C::format_local($P, array(1403, 12, 30), 'dddd D MMMM YYYY', 'fa_IR', array('digits' => 'arabext')), 'پنجشنبه ۳۰ اسفند ۱۴۰۳');
t('4 format ar Umm al-Qura', C::format_local($U, array(1446, 10, 1), 'dddd، D MMMM YYYY', 'ar', array('digits' => 'arab')), 'الأحد، ١ شوال ١٤٤٦');
t('4 format en', C::format_local($G, array(2025, 3, 20), 'MMMM D, YYYY', 'en_US'), 'March 20, 2025');
t('4 format literal and time', C::format_local($G, array(2025, 3, 20), 'YYYY-MM-DD[T]HH:mm hh A', 'en_US', array('time' => '14:05')), '2025-03-20T14:05 02 PM');
t('4 format fa time', C::format_local($G, array(2025, 3, 20), 'hh:mm A', 'fa_IR', array('time' => '09:30', 'digits' => 'arabext')), '۰۹:۳۰ ق.ظ.');
t('4 parse DD/MM/YYYY Arabic digits', C::parse_local($U, '٠١/١٠/١٤٤٦', 'DD/MM/YYYY'), array(1446, 10, 1));
t('4 parse MM/DD/YYYY', C::parse_local($G, '03/20/2025', 'MM/DD/YYYY'), array(2025, 3, 20));
t('4 parse wrong order', C::parse_local($G, '20/03/2025', 'MM/DD/YYYY'), null);
t('4 parse 1404/12/30 refused', C::parse_local($P, '۱۴۰۴/۱۲/۳۰', 'YYYY/MM/DD'), null);
$r = efb_calendar_convert('1404-01-10', 'persian', 'islamic-umalqura'); t('4 convert persian -> umalqura', $r['iso'], '1446-10-01');
$r = efb_calendar_convert('2025-03-20', 'gregory', 'buddhist'); t('4 buddhist', $r['iso'], '2568-03-20');
$r = efb_calendar_convert('2025-03-20', 'gregory', 'roc'); t('4 roc', $r['iso'], '0114-03-20');
$r = efb_calendar_convert('2025-03-20', 'gregory', 'klingon'); t('4 convert unknown calendar is an error', is_array($r) && isset($r['iso']), false);
t('4 addUnit clamp', array(C::add_unit('2025-01-31', 1, 'month'), C::add_unit('2024-01-31', 1, 'month'), C::add_unit('2024-02-29', 1, 'year'), C::add_unit('2025-03-31', -1, 'month')), array('2025-02-28', '2024-02-29', '2025-02-28', '2025-02-28'));
t('4 wall time Tehran', C::wall_to_utc('2026-03-20', '14:30', 'Asia/Tehran'), '2026-03-20T11:00:00Z');
t('4 DST gap', C::wall_to_utc('2026-03-08', '02:30', 'America/New_York'), null);
t('4 DST fold', C::wall_to_utc('2026-11-01', '01:30', 'America/New_York'), null);
t('4 DST Berlin', C::wall_to_utc('2026-03-29', '03:30', 'Europe/Berlin'), '2026-03-29T01:30:00Z');
t('4 locale fa_IR', C::locale_defaults('fa_IR'), array('default' => 'persian', 'allowed' => array('persian', 'gregory'), 'secondary' => '', 'digits' => 'arabext', 'hour12' => false));
t('4 locale ar_SA', C::locale_defaults('ar_SA'), array('default' => 'islamic-umalqura', 'allowed' => array('islamic-umalqura', 'gregory'), 'secondary' => 'gregory', 'digits' => 'arab', 'hour12' => true));
t('4 typed patterns', array(C::typed_pattern('persian', 'fa_IR'), C::typed_pattern('islamic-umalqura', 'ar'), C::typed_pattern('gregory', 'en_US'), C::typed_pattern('gregory', 'en_GB'), C::typed_pattern('gregory', 'de_DE'), C::typed_pattern('gregory', 'fa_IR')), array('YYYY/MM/DD', 'DD/MM/YYYY', 'MM/DD/YYYY', 'DD/MM/YYYY', 'DD.MM.YYYY', 'YYYY/MM/DD'));
t('4 normalize', C::normalize("\u{200F}۱۴۰۳\u{00A0}/\u{202A}۱۲/۳۰\u{2069}"), '1403/12/30');
t('4 efb_calendar_format medium fa', efb_calendar_format('2026-03-19', 'persian', 'medium', 'fa_IR'), '۲۸ اسفند ۱۴۰۴');
t('4 efb_calendar_format auto ar', efb_calendar_format('2025-03-30', 'islamic-umalqura', 'auto', 'ar_SA'), '٠١/١٠/١٤٤٦');
t('4 supported has Tier 1 and 2', array_intersect_key(efb_calendar_supported(), array_flip(array('gregory', 'persian', 'islamic-umalqura', 'buddhist'))), array('gregory' => 1, 'persian' => 1, 'islamic-umalqura' => 1, 'buddhist' => 2));
t('4 Tier 3 only with ext-intl', isset(efb_calendar_supported()['hebrew']), extension_loaded('intl'));

echo "\nRESULTS: {$passed} passed, {$failed} failed\n";
exit($failed ? 1 : 0);
