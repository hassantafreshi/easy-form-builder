<?php
/* PHP twin of engines.js (pure PHP, no ext-intl needed), cross-checked against
 * vectors.json (written by verify.js) and against ext-intl when it is loaded.
 * Usage: php verify.php vectors.json */

function efbv_fdiv($a, $b) { return (int) floor($a / $b); }
function efbv_div($a, $b) { return intdiv($a, $b); }               // truncating, like ~~(a/b)
function efbv_mod($a, $b) { return $a - intdiv($a, $b) * $b; }

function efbv_g2jdn($y, $m, $d) {
	$a = efbv_fdiv(14 - $m, 12); $yy = $y + 4800 - $a; $mm = $m + 12 * $a - 3;
	return $d + efbv_fdiv(153 * $mm + 2, 5) + 365 * $yy + efbv_fdiv($yy, 4) - efbv_fdiv($yy, 100) + efbv_fdiv($yy, 400) - 32045;
}
function efbv_jdn2g($jdn) {
	$a = $jdn + 32044; $b = efbv_fdiv(4 * $a + 3, 146097); $c = $a - efbv_fdiv(146097 * $b, 4);
	$d = efbv_fdiv(4 * $c + 3, 1461); $e = $c - efbv_fdiv(1461 * $d, 4); $m = efbv_fdiv(5 * $e + 2, 153);
	return array(100 * $b + $d - 4800 + efbv_fdiv($m, 10), $m + 3 - 12 * efbv_fdiv($m, 10), $e - efbv_fdiv(153 * $m + 2, 5) + 1);
}

/* Jalali (Borkowski breaks) */
function efbv_jal_cal($jy, $without_leap = false) {
	static $breaks = array(-61, 9, 38, 199, 426, 686, 756, 818, 1111, 1181, 1210, 1635, 2060, 2097, 2192, 2262, 2324, 2394, 2456, 3178);
	$bl = count($breaks); $gy = $jy + 621; $leap_j = -14; $jp = $breaks[0]; $jump = 0;
	if ($jy < $jp || $jy >= $breaks[$bl - 1]) throw new RangeError("Jalali year out of range $jy");
	for ($i = 1; $i < $bl; $i++) {
		$jm = $breaks[$i]; $jump = $jm - $jp;
		if ($jy < $jm) break;
		$leap_j = $leap_j + efbv_div($jump, 33) * 8 + efbv_div(efbv_mod($jump, 33), 4);
		$jp = $jm;
	}
	$n = $jy - $jp;
	$leap_j = $leap_j + efbv_div($n, 33) * 8 + efbv_div(efbv_mod($n, 33) + 3, 4);
	if (efbv_mod($jump, 33) === 4 && $jump - $n === 4) $leap_j += 1;
	$leap_g = efbv_div($gy, 4) - efbv_div((efbv_div($gy, 100) + 1) * 3, 4) - 150;
	$march = 20 + $leap_j - $leap_g;
	if ($without_leap) return array('gy' => $gy, 'march' => $march);
	if ($jump - $n < 6) $n = $n - $jump + efbv_div($jump + 4, 33) * 33;
	$leap = efbv_mod(efbv_mod($n + 1, 33) - 1, 4);
	if ($leap === -1) $leap = 4;
	return array('leap' => $leap, 'gy' => $gy, 'march' => $march);
}
function efbv_jal_to_jdn($jy, $jm, $jd) {
	$r = efbv_jal_cal($jy, true);
	return efbv_g2jdn($r['gy'], 3, $r['march']) + ($jm - 1) * 31 - efbv_div($jm, 7) * ($jm - 7) + $jd - 1;
}
function efbv_jdn_to_jal($jdn) {
	$g = efbv_jdn2g($jdn); $jy = $g[0] - 621; $r = efbv_jal_cal($jy);
	$k = $jdn - efbv_g2jdn($g[0], 3, $r['march']);
	if ($k >= 0) {
		if ($k <= 185) return array($jy, 1 + efbv_div($k, 31), efbv_mod($k, 31) + 1);
		$k -= 186;
	} else { $jy -= 1; $k += 179; if ($r['leap'] === 1) $k += 1; }
	return array($jy, 7 + efbv_div($k, 30), efbv_mod($k, 30) + 1);
}

/* Tabular Islamic */
function efbv_tab_to_jdn($y, $m, $d, $epoch) { return $d + (int) ceil(29.5 * ($m - 1)) + ($y - 1) * 354 + efbv_fdiv(3 + 11 * $y, 30) + $epoch - 1; }
function efbv_jdn_to_tab($jdn, $epoch) {
	$y = efbv_fdiv(30 * ($jdn - $epoch) + 10646, 10631);
	$m = min(12, (int) ceil(($jdn - (29 + efbv_tab_to_jdn($y, 1, 1, $epoch))) / 29.5) + 1);
	return array($y, $m, $jdn - efbv_tab_to_jdn($y, $m, 1, $epoch) + 1);
}

/* Umm al-Qura from the compact table */
function efbv_uq_starts($c) {
	$starts = array(); $jdn = $c['firstJdn']; $n = strlen($c['masksHex']) / 3;
	for ($i = 0; $i < $n; $i++) { $mask = hexdec(substr($c['masksHex'], $i * 3, 3)); for ($m = 0; $m < 12; $m++) { $starts[] = $jdn; $jdn += (($mask >> $m) & 1) ? 30 : 29; } }
	$starts[] = $jdn; return $starts;
}
function efbv_uq_to_jdn($starts, $first, $y, $m, $d) { return $starts[($y - $first) * 12 + $m - 1] + $d - 1; }
function efbv_jdn_to_uq($starts, $first, $jdn) {
	$lo = 0; $hi = count($starts) - 2;
	while ($lo < $hi) { $mid = ($lo + $hi + 1) >> 1; if ($starts[$mid] <= $jdn) $lo = $mid; else $hi = $mid - 1; }
	return array($first + intdiv($lo, 12), $lo % 12 + 1, $jdn - $starts[$lo] + 1);
}

$data = json_decode(file_get_contents($argv[1]), true);
$c = $data['ummalqura_compact']; $starts = efbv_uq_starts($c); $first = $c['firstYear'];
$out = array('php' => PHP_VERSION, 'intl' => extension_loaded('intl') ? INTL_ICU_VERSION : 'not loaded');

/* 1. vectors */
$ok = 0; $bad = array();
foreach ($data['vectors'] as $v) {
	list($y, $m, $d) = array_map('intval', explode('-', $v['date']));
	switch ($v['calendar']) {
		case 'persian': $j = efbv_jal_to_jdn($y, $m, $d); $back = efbv_jdn_to_jal($j); break;
		case 'islamic-civil': $j = efbv_tab_to_jdn($y, $m, $d, 1948440); $back = efbv_jdn_to_tab($j, 1948440); break;
		case 'islamic-tbla': $j = efbv_tab_to_jdn($y, $m, $d, 1948439); $back = efbv_jdn_to_tab($j, 1948439); break;
		case 'islamic-umalqura': $j = efbv_uq_to_jdn($starts, $first, $y, $m, $d); $back = efbv_jdn_to_uq($starts, $first, $j); break;
	}
	$g = efbv_jdn2g($j);
	$gi = sprintf('%04d-%02d-%02d', $g[0], $g[1], $g[2]);
	if ($j === $v['jdn'] && $gi === $v['gregorian'] && $back === array($y, $m, $d)) $ok++; else $bad[] = $v;
}
$out['vectors_php_vs_js'] = "$ok/" . count($data['vectors']);
if ($bad) $out['vectors_bad'] = array_slice($bad, 0, 5);

/* 2. every day: PHP engine vs PHP ext-intl */
if (extension_loaded('intl')) {
	$cals = array('persian', 'islamic-umalqura', 'islamic-civil', 'islamic-tbla');
	$types = array();
	foreach ($cals as $cal) { $ic = IntlCalendar::createInstance('UTC', 'en_US@calendar=' . $cal); $types[$cal] = $ic->getType(); }
	$out['intl_calendar_types'] = $types;
	$icu = function ($cal, $jdn) {
		static $cache = array();
		if (!isset($cache[$cal])) $cache[$cal] = IntlCalendar::createInstance('UTC', 'en_US@calendar=' . $cal);
		$ic = $cache[$cal]; $ic->setTime((float) ($jdn - 2440588) * 86400000.0 + 43200000.0);
		return array($ic->get(IntlCalendar::FIELD_EXTENDED_YEAR), $ic->get(IntlCalendar::FIELD_MONTH) + 1, $ic->get(IntlCalendar::FIELD_DAY_OF_MONTH));
	};
	$a = 0; $n = 0; $diff = array();
	for ($j = efbv_jal_to_jdn(1300, 1, 1); $j < efbv_jal_to_jdn(1500, 1, 1); $j++) { $n++; $mine = efbv_jdn_to_jal($j); $i = $icu('persian', $j); if ($mine === $i) $a++; elseif (count($diff) < 5) $diff[] = array(implode('-', $mine), implode('-', $i)); }
	$out['persian_1300_1499_vs_php_intl'] = "$a/$n days"; if ($diff) $out['persian_diffs'] = $diff;
	foreach (array(array('islamic-civil', 1948440), array('islamic-tbla', 1948439)) as $t) {
		$a = 0; $n = 0;
		for ($j = efbv_tab_to_jdn(1300, 1, 1, $t[1]); $j < efbv_tab_to_jdn(1600, 1, 1, $t[1]); $j++) { $n++; if (efbv_jdn_to_tab($j, $t[1]) === $icu($t[0], $j)) $a++; }
		$out[$t[0] . '_1300_1599_vs_php_intl'] = "$a/$n days";
	}
	foreach (array(array(1318, 1419), array(1420, 1450), array(1451, 1500)) as $w) {
		$a = 0; $n = 0;
		for ($y = $w[0]; $y <= $w[1]; $y++) for ($m = 1; $m <= 12; $m++) { $n++; $j = efbv_uq_to_jdn($starts, $first, $y, $m, 1); if ($icu('islamic-umalqura', $j) === array($y, $m, 1)) $a++; }
		$out['umalqura_month_starts_' . $w[0] . '_' . $w[1] . '_vs_php_intl'] = "$a/$n";
	}
	// IntlDateFormatter display sample
	$fmt = new IntlDateFormatter('fa_IR@calendar=persian', IntlDateFormatter::FULL, IntlDateFormatter::NONE, 'Asia/Tehran', IntlDateFormatter::TRADITIONAL);
	$out['fa_IR_display_2025-03-20'] = $fmt->format(new DateTime('2025-03-20 12:00', new DateTimeZone('Asia/Tehran')));
	$fmt = new IntlDateFormatter('ar_SA@calendar=islamic-umalqura', IntlDateFormatter::FULL, IntlDateFormatter::NONE, 'Asia/Riyadh', IntlDateFormatter::TRADITIONAL);
	$out['ar_SA_display_2025-03-30'] = $fmt->format(new DateTime('2025-03-30 12:00', new DateTimeZone('Asia/Riyadh')));
}
echo json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), "\n";
file_put_contents(__DIR__ . '/results-php.json', json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
