<?php
/**
 * F06-E on a real WordPress (the e2e site, tests/f11-http-lib.php), over HTTP as a browser would send:
 *
 *  1. A valid date is stored with the row rebuilt by the server (value, cal, local, disp, tz, mode, v);
 *     a forged disp / local / mode / approx from the browser is dropped; a calendar outside cal_allowed
 *     falls back to the default; Persian digits and direction marks are read.
 *  2. Every refusal answers success:false with the visitor sentence, field_id and code efb_date_<code>,
 *     and stores nothing: before_min (today in the site zone), weekday_disabled, invalid_date
 *     (1404/12/30 sent as a Jalali legacy_local value), invalid_format (free text), required,
 *     range_contains_disabled, too_many_dates, time_step, age_too_young, a field limit (check-out
 *     before check-in + 1) and the plan: range mode on Free Plus is a single date.
 *  3. The Booking hooks (stand-in mu-plugin efb-e2e-f06.php): efb_date_availability "full" refuses at
 *     the field; efb_date_before_commit refuses a date taken meanwhile (no entry); efb_date_after_commit
 *     gets the tracking code; a failed insert calls efb_date_commit_failed.
 *  4. Old date / pdate fields on the same site keep their behaviour (a pdate value is stored as typed).
 *
 * Run (under the e2e lock): source the e2e env; php tests/test-advdate-submission-http.php
 */
$passed = 0;
$failed = 0;
function t($name, $actual, $expected) {
	global $passed, $failed;
	if ($actual === $expected) { echo "[PASS] {$name}\n"; $passed++; return; }
	echo "[FAIL] {$name}: expected " . var_export($expected, true) . ', got ' . var_export($actual, true) . "\n";
	$failed++;
}
require __DIR__ . '/f11-http-lib.php';
$H = new EfbF11Http();
if ($why = $H->unavailable()) { echo "[SKIP] {$why}\nRESULTS: 0 passed, 0 failed\n"; exit(0); }

$H->ctl('f06-mu');
$H->ctl('f06-reset');
$H->ctl('f06-set', 'plan', '2');
$T = $H->ctl('f06-today');
$today = $T['today'];
$day = function ($n) use ($today) { return gmdate('Y-m-d', strtotime($today . ' 12:00:00 UTC') + 86400 * $n); };
$wd = function ($iso) { return (int) gmdate('N', strtotime($iso . ' 12:00:00 UTC')); };
/* the next Friday after tomorrow and a non-Friday after it */
$fri = $day(2); while ($wd($fri) !== 5) $fri = gmdate('Y-m-d', strtotime($fri . ' 12:00:00 UTC') + 86400);
$open = $day(2); while ($wd($open) === 5) $open = gmdate('Y-m-d', strtotime($open . ' 12:00:00 UTC') + 86400);
$plus = function ($iso, $n) { return gmdate('Y-m-d', strtotime($iso . ' 12:00:00 UTC') + 86400 * $n); };
/* a two-night stay whose nights are not Fridays */
$st = $day(2); while ($wd($st) === 5 || $wd($plus($st, 1)) === 5) $st = $plus($st, 1);

$fields = array(
	array('key' => 'appt', 'type' => 'advdate', 'cal_default' => 'persian', 'cal_allowed' => array('persian', 'gregory'), 'cal_switch' => 1, 'date_min' => array('type' => 'relative', 'value' => 1), 'disabled_weekdays' => array(5), 'required' => 1),
	array('key' => 'stay', 'type' => 'advdate', 'date_mode' => 'range', 'range_min_days' => 1, 'range_max_days' => 14, 'disabled_weekdays' => array(5)),
	array('key' => 'days', 'type' => 'advdate', 'date_mode' => 'multiple', 'multi_max' => 2),
	array('key' => 'slot', 'type' => 'advdate', 'time_enabled' => 1, 'time_step' => 30, 'time_min' => '09:00', 'time_max' => '17:00'),
	array('key' => 'born', 'type' => 'advdate', 'age_min' => 18, 'input_mode' => 'typed'),
	array('key' => 'chk_in', 'type' => 'advdate'),
	array('key' => 'chk_out', 'type' => 'advdate'),
	array('key' => 'old', 'type' => 'advdate', 'cal_default' => 'persian', 'store_format' => 'legacy_local'),
	array('key' => 'name', 'type' => 'text'),
);
$F = $H->ctl('f03-form', 'F06 submit', json_encode($fields));
$fid = $F['form_id'];
$ids = $F['ids'];
/* the field limit names the other field's id, so it is set after the ids exist */
$H->ctl('f03-field', (string) $fid, $ids['chk_out'], 'date_min=' . urlencode(json_encode(array('type' => 'field', 'field' => $ids['chk_in'], 'offset' => 1))));
$entries = function () use ($H, $fid) { return (array) $H->ctl('entries', (string) $fid); };
$row = function ($key, $value, $extra = array()) use ($ids) { return array_merge(array('id_' => $ids[$key], 'name' => $key, 'type' => 'advdate', 'id_ob' => $ids[$key] . '_', 'value' => $value), $extra); };
$base = function () use ($row, $open) { return array($row('appt', $open, array('cal' => 'persian'))); };
$send = function ($rows) use ($H, $fid) { $r = $H->submit($fid, $rows); return EfbF11Http::answer($r); };
$last = function () use ($entries) { $e = $entries(); usort($e, function ($a, $b) { return (int) $b['msg_id'] - (int) $a['msg_id']; }); return $e ? $e[0] : null; };
$adv = function ($entry, $id) { foreach ((array) $entry['content'] as $r) if (isset($r['id_']) && $r['id_'] === $id) return $r; return null; };

/* 1 */
$n0 = count($entries());
$a = $send(array($row('appt', $open, array('cal' => 'persian', 'disp' => 'HACKED <b>x</b>', 'local' => '9999-01-01', 'mode' => 'range', 'approx' => '1', 'utc' => 'x'))));
t('1 a valid date is stored', array($a['success'] ?? null, count($entries()) - $n0), array(true, 1));
$r = $adv($last(), $ids['appt']);
$conv = $H->ctl('f06-today');
t('1 the row is rebuilt by the server: value, cal, mode, v, tz', array($r['value'], $r['cal'], $r['mode'], (int) $r['v'], $r['tz']), array($open, 'persian', 'single', 1, $T['tz']));
t('1 forged disp / local / approx / utc are not kept', array(strpos((string) $r['disp'], 'HACKED') === false, $r['local'] !== '9999-01-01', isset($r['approx']), isset($r['utc'])), array(true, true, false, false));
t('1 local is the Persian date of the value', (bool) preg_match('/^1[34]\d\d-\d\d-\d\d$/', $r['local']), true);
$a = $send(array($row('appt', $open, array('cal' => 'islamic-civil'))));
t('1 a calendar outside cal_allowed: the default is stored', $adv($last(), $ids['appt'])['cal'], 'persian');
$a = $send(array($row('appt', strtr("\u{200F}" . $open, array('0' => '۰', '1' => '۱', '2' => '۲', '3' => '۳', '4' => '۴', '5' => '۵', '6' => '۶', '7' => '۷', '8' => '۸', '9' => '۹')), array('cal' => 'gregory'))));
t('1 Persian digits and an RLM are read', array($a['success'] ?? null, $adv($last(), $ids['appt'])['value'], $adv($last(), $ids['appt'])['cal']), array(true, $open, 'gregory'));

/* 2 */
$refused = function ($name, $rows, $code, $field_key, $msg_re = null) use ($send, $entries, $ids, $n0) {
	$before = count($entries());
	$a = $send($rows);
	t('2 ' . $name . ': refused with efb_date_' . $code, array($a['success'] ?? null, $a['code'] ?? null, $a['field_id'] ?? null), array(false, 'efb_date_' . $code, $ids[$field_key]));
	if ($msg_re) t('2 ' . $name . ': the sentence', (bool) preg_match($msg_re, (string) ($a['m'] ?? '')), true);
	t('2 ' . $name . ': nothing stored', count($entries()) - $before, 0);
};
$refused('today (min = tomorrow, site zone)', array($row('appt', $today)), 'before_min', 'appt', '/^Please choose \x{2068}.+\x{2069} or a later date\.$/u');
$refused('a closed Friday', array($row('appt', $fri)), 'weekday_disabled', 'appt', '/^This day of the week cannot be chosen\.$/');
$refused('free text', array($row('appt', 'tomorrow')), 'invalid_format', 'appt');
$before = count($entries());
$a = $send(array($row('appt', ''), $row('stay', $st . '/' . $plus($st, 2))));
t('2 required and empty: refused at the field by the presence check (as every type)', array($a['success'] ?? null, $a['field_id'] ?? null, count($entries()) - $before), array(false, $ids['appt'], 0));
$stay_a = $fri; $stay_b = gmdate('Y-m-d', strtotime($fri . ' 12:00 UTC') + 86400 * 3);
$prev = gmdate('Y-m-d', strtotime($fri . ' 12:00 UTC') - 86400);
$refused('a stay over a closed night', array_merge($base(), array($row('stay', $prev . '/' . $stay_b))), 'range_contains_disabled', 'stay', '/^The dates you chose include \x{2068}.+\x{2069}, which cannot be chosen\.$/u');
$refused('too many dates', array_merge($base(), array($row('days', $day(3) . ',' . $day(4) . ',' . $day(5)))), 'too_many_dates', 'days', '/^You can choose at most 2 dates\.$/');
$refused('a time off the 30-minute steps', array_merge($base(), array($row('slot', $day(3) . 'T09:15'))), 'time_step', 'slot');
$refused('a time without the date', array_merge($base(), array($row('slot', $day(3)))), 'time_required', 'slot');
$refused('under 18', array_merge($base(), array($row('born', gmdate('Y-m-d', strtotime($today . ' -17 years'))))), 'age_too_young', 'born', '/^You must be at least 18 years old\.$/');
$refused('check-out not after check-in + 1', array_merge($base(), array($row('chk_in', $day(10)), $row('chk_out', $day(10)))), 'before_min', 'chk_out');
$a = $send(array_merge($base(), array($row('chk_in', $day(10)), $row('chk_out', $day(11)))));
t('2 check-out after check-in + 1: stored', $a['success'] ?? null, true);
$refused('1404-12-30 as a Jalali value of a legacy_local field', array_merge($base(), array($row('old', '1404-12-30'))), 'invalid_date', 'old');
$a = $send(array_merge($base(), array($row('old', '1403-12-30'))));
$r = $adv($last(), $ids['old']);
t('2 legacy_local keeps the local string and adds the ISO date', array($a['success'] ?? null, $r['value'], $r['iso'], $r['local']), array(true, '1403-12-30', '2025-03-20', '1403-12-30'));
$a = $send(array_merge($base(), array($row('stay', $st . '/' . $plus($st, 2)), $row('slot', $day(3) . 'T10:30'), $row('days', $day(3) . ',' . $day(4)))));
$e = $last();
t('2 a stay, a time and two dates stored', array($a['success'] ?? null, $adv($e, $ids['slot'])['time'], $adv($e, $ids['slot'])['utc'], $adv($e, $ids['days'])['mode']), array(true, '10:30', $day(3) . 'T10:30:00Z', 'multiple'));
$refused('a 0-night stay (range_min_days 1)', array_merge($base(), array($row('stay', $day(3) . '/' . $day(3)))), 'range_too_short', 'stay');
$H->ctl('f06-set', 'plan', '1');
$refused('Free Plus: a range field takes a single date', array_merge($base(), array($row('stay', $day(3) . '/' . $day(5)))), 'invalid_format', 'stay');
$a = $send(array_merge($base(), array($row('stay', $day(3)))));
t('2 Free Plus: the range field stores a single date', array($a['success'] ?? null, $adv($last(), $ids['stay'])['mode']), array(true, 'single'));
$H->ctl('f06-set', 'plan', '2');

/* 3 */
$H->ctl('f06-set', 'full', $plus($open, 7));
$refused('efb_date_availability says full', array($row('appt', $plus($open, 7))), 'full', 'appt', '/^This date is fully booked\.$/');
$H->ctl('f06-set', 'full', '');
$H->ctl('f06-set', 'booking', '1');
$H->ctl('f06-set', 'taken', $open);
$before = count($entries());
$a = $send($base());
t('3 efb_date_before_commit refuses a date taken meanwhile: at the field, nothing stored', array($a['success'] ?? null, $a['code'] ?? null, $a['field_id'] ?? null, $a['m'] ?? null, count($entries()) - $before), array(false, 'efb_date_full', $ids['appt'], 'This date was just booked by someone else.', 0));
$H->ctl('f06-set', 'taken', '');
$a = $send($base());
$s = $H->ctl('f06-set', 'booking', '1');
$log = end($s['log']);
t('3 efb_date_after_commit gets the tracking code', array($a['success'] ?? null, $log[0], $log[1], $log[2] === ($a['track'] ?? ''), $log[3]), array(true, 'after', $fid, true, 1));
t('3 the place is taken for the next visitor', in_array($open, (array) $s['taken'], true), true);
$H->ctl('f11-mu');
$H->ctl('set-option', 'efb_e2e_f11_fail_insert', '1');
$H->ctl('f06-set', 'taken', '');
$a = $send($base());
$H->ctl('set-option', 'efb_e2e_f11_fail_insert', 'del');
$s = $H->ctl('f06-set', 'booking', '');
$log = end($s['log']);
t('3 a failed insert calls efb_date_commit_failed', array($log[0], $log[1]), array('failed', $fid));

/* 4 */
$G = $H->ctl('f03-form', 'F06 legacy', json_encode(array(array('key' => 'd', 'type' => 'date'), array('key' => 'p', 'type' => 'pdate'))));
$gid = $G['form_id'];
$a = EfbF11Http::answer($H->submit($gid, array(array('id_' => $G['ids']['d'], 'name' => 'd', 'type' => 'date', 'id_ob' => $G['ids']['d'] . '_', 'value' => '2025-03-20'), array('id_' => $G['ids']['p'], 'name' => 'p', 'type' => 'pdate', 'id_ob' => $G['ids']['p'] . '_', 'value' => '1403-12-30'))));
$e = (array) $H->ctl('entries', (string) $gid);
$vals = array();
foreach ((array) $e[0]['content'] as $r) if (isset($r['id_'])) $vals[$r['type']] = array($r['value'], isset($r['disp']) || isset($r['cal']));
t('4 old date and pdate rows stored as before (no calendar keys)', array($a['success'] ?? null, $vals['date'] ?? null, $vals['pdate'] ?? null), array(true, array('2025-03-20', false), array('1403-12-30', false)));

$H->ctl('f06-reset');
echo "\nRESULTS: {$passed} passed, {$failed} failed\n";
exit($failed ? 1 : 0);
