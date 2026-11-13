<?php
/**
 * F06-F on a real WordPress (the e2e site): the calendar REST routes and the form schema.
 *
 *  1. calendar/availability: 404 efb_not_found for an unknown field, a non-date field, a hidden field and an
 *     unknown or draft form; 400 for a window over 92 days or bad dates; the states (out_of_range, closed,
 *     holiday, open omitted); "full" from efb_date_availability; remaining (r) only with bk_show_remaining;
 *     Cache-Control private while an availability filter is attached (public without one, as for convert).
 *  2. A members-only form: 403 efb_login_required for a guest on every route, 200 for the administrator.
 *  3. calendar/convert: Persian, Umm al-Qura, the Hijri adjustment; a Tier 3 calendar and a bad date are 400.
 *  4. calendar/check: the server's verdict with the stable code and the visitor sentence; ok with the row.
 *  5. forms/{id}/schema: the advdate entry (fill call, value_schema, x-efb-date with limits as configured),
 *     other fields with fill api dom; no secret: no notification e-mail, webhook URL, SMS / Telegram text,
 *     logic rule, payment key, F03 pattern or e-mail domain list; Cache-Control private, no-store.
 *  6. The rate limit (efb_calendar_rest_per_minute, set low by the mu-plugin option): 429.
 *
 * Run (under the e2e lock): source the e2e env; php tests/test-calendar-rest-http.php
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
$plus = function ($n) use ($today) { return gmdate('Y-m-d', strtotime($today . ' 12:00 UTC') + 86400 * $n); };
$get = function ($path) use ($H) { return $H->http('GET', '/wp-json/Emsfb/v1/' . $path, null, array(), false); };

$fields = array(
	array('key' => 'appt', 'type' => 'advdate', 'date_min' => array('type' => 'relative', 'value' => 1), 'date_max' => array('type' => 'relative', 'value' => 60), 'disabled_weekdays' => array(5),
		'holidays_custom' => array(array('date' => $plus(10), 'label' => 'Shop closed')), 'required' => 1, 'message' => 'Pick a day'),
	array('key' => 'room', 'type' => 'advdate', 'date_mode' => 'range', 'bk_show_remaining' => '1'),
	array('key' => 'secret', 'type' => 'advdate', 'hidden' => 1),
	array('key' => 'mail', 'type' => 'email', 'unique' => '1', 'email_domain_mode' => 'allow', 'email_domains' => 'internal-only.example'),
	array('key' => 'code', 'type' => 'text', 'pattern_b64' => 'XltBLVpdezJ9JA'),
	array('key' => 'pick', 'type' => 'select'),
);
$row0 = 'email=owner-secret@example.test&email_to=&smsnoti=0&sms_msg_new_noti=SMS-SECRET-TEXT&telegram_msg_new_noti=TG-SECRET&notification_rules=' . urlencode(json_encode(array(array('id' => 'n1', 'recipient' => 'rule-secret@example.test'))))
	. '&webhook_rules=' . urlencode(json_encode(array(array('id' => 'w1', 'url' => 'https://hooks.example.test/SECRET-HOOK'))));
$F = $H->ctl('f03-form', 'F06 REST', json_encode($fields), $row0);
$fid = $F['form_id']; $ids = $F['ids'];
$av = function ($field, $from, $to, $extra = '') use ($get, $fid, $ids) { return $get('calendar/availability?form_id=' . $fid . '&field_id=' . rawurlencode(isset($ids[$field]) ? $ids[$field] : $field) . '&from=' . $from . '&to=' . $to . $extra); };

/* 1 */
$r = $av('nope', $plus(1), $plus(5));
t('1 unknown field: 404 efb_not_found', array($r['status'], json_decode($r['body'], true)['code'] ?? ''), array(404, 'efb_not_found'));
$r = $av('mail', $plus(1), $plus(5));
t('1 a field that is not a date: 404', $r['status'], 404);
$r = $av('secret', $plus(1), $plus(5));
t('1 a hidden date field: 404 (no probing)', $r['status'], 404);
$r = $get('calendar/availability?form_id=999999&field_id=x&from=' . $plus(1) . '&to=' . $plus(2));
t('1 an unknown form: 404', $r['status'], 404);
$r = $av('appt', $plus(1), $plus(100));
t('1 more than 92 days: 400 efb_range_too_long', array($r['status'], json_decode($r['body'], true)['code'] ?? ''), array(400, 'efb_range_too_long'));
$r = $av('appt', 'tomorrow', $plus(3));
t('1 a bad date: 400', $r['status'], 400);
$r = $av('appt', $today, $plus(12));
$j = json_decode($r['body'], true);
t('1 today and min / max', array($j['today'] ?? '', $j['min'] ?? '', $j['max'] ?? ''), array($today, $plus(1), $plus(60)));
t('1 today: out_of_range (before_min)', $j['dates'][$today] ?? null, array('s' => 'out_of_range', 'code' => 'before_min'));
$fri = null; for ($i = 1; $i <= 12; $i++) if ((int) gmdate('N', strtotime($plus($i) . ' 12:00 UTC')) === 5) { $fri = $plus($i); break; }
t('1 a Friday: closed (weekday_disabled)', $j['dates'][$fri] ?? null, array('s' => 'closed', 'code' => 'weekday_disabled'));
$hol = $plus(10);
t('1 the own holiday: holiday', ($j['dates'][$hol]['s'] ?? '') === 'holiday' || ((int) gmdate('N', strtotime($hol . ' 12:00 UTC')) === 5), true);
$open = 0; foreach (range(1, 12) as $i) if (!isset($j['dates'][$plus($i)])) $open++;
t('1 open days are omitted', $open > 0, true);
t('1 an availability filter is on (the stand-in add-on): Cache-Control private, max-age=15', (bool) preg_match('/Cache-Control: private, max-age=15/i', $r['headers']), true);
t('1 convert (no visitor data): Cache-Control public', (bool) preg_match('/Cache-Control: public/i', $get('calendar/convert?date=2025-03-20&from=gregory&to=persian')['headers']), true);
$H->ctl('f06-set', 'full', $plus(3) . ',' . $plus(4));
$r = $av('room', $plus(1), $plus(5));
$j = json_decode($r['body'], true);
t('1 efb_date_availability: full', $j['dates'][$plus(3)] ?? null, array('s' => 'full', 'code' => 'full'));
t('1 with a filter: Cache-Control private', (bool) preg_match('/Cache-Control: private, max-age=15/i', $r['headers']), true);
$r = $av('appt', $plus(1), $plus(5));
$j = json_decode($r['body'], true);
$hasR = false; foreach ((array) $j['dates'] as $d) if (isset($d['r'])) $hasR = true;
t('1 remaining never sent without bk_show_remaining', $hasR, false);
$H->ctl('f06-set', 'full', '');

/* 2 */
$P = $H->ctl('f03-form', 'F06 members', json_encode(array(array('key' => 'd', 'type' => 'advdate'))), 'stateForm=1');
$r = $get('calendar/availability?form_id=' . $P['form_id'] . '&field_id=' . $P['ids']['d'] . '&from=' . $plus(1) . '&to=' . $plus(2));
t('2 members-only form, guest: 403 efb_login_required (availability)', array($r['status'], json_decode($r['body'], true)['code'] ?? ''), array(403, 'efb_login_required'));
$r = $get('forms/' . $P['form_id'] . '/schema');
t('2 members-only form, guest: 403 (schema)', $r['status'], 403);
$r = $H->http('POST', '/wp-json/Emsfb/v1/calendar/check', array('form_id' => $P['form_id'], 'field_id' => $P['ids']['d'], 'value' => $plus(1)));
t('2 members-only form, guest: 403 (check)', $r['status'], 403);
$H->fresh_jar();
$H->login('admin', getenv('EFB_E2E_PASS') ?: 'admin-pass-123');
$nonce = $H->http('GET', '/wp-admin/admin-ajax.php?action=rest-nonce', null, array(), false)['body'];
$r = $H->http('GET', '/wp-json/Emsfb/v1/forms/' . $P['form_id'] . '/schema', null, array('X-WP-Nonce' => trim($nonce)), false);
t('2 the administrator: 200', $r['status'], 200);
$H->fresh_jar();

/* 3 */
$c = json_decode($get('calendar/convert?date=1403-12-30&from=persian&to=gregory')['body'], true);
t('3 convert Persian -> Gregorian', array($c['iso'] ?? '', $c['approx'] ?? null), array('2025-03-20', false));
$c = json_decode($get('calendar/convert?date=2025-03-30&from=gregory&to=islamic-umalqura')['body'], true);
t('3 convert -> Umm al-Qura', $c['iso'] ?? '', '1446-10-01');
$c = json_decode($get('calendar/convert?date=2025-03-30&from=gregory&to=islamic-umalqura&hijri_adjust=-1')['body'], true);
t('3 Hijri adjustment -1', $c['iso'] ?? '', '1446-09-29');
t('3 a Tier 3 calendar: 400', $get('calendar/convert?date=2025-03-30&from=gregory&to=hebrew')['status'], 400);
t('3 1404-12-30 Persian: 400', $get('calendar/convert?date=1404-12-30&from=persian&to=gregory')['status'], 400);

/* 4 */
$ck = function ($v) use ($H, $fid, $ids) { return json_decode($H->http('POST', '/wp-json/Emsfb/v1/calendar/check', array('form_id' => $fid, 'field_id' => $ids['appt'], 'value' => $v))['body'], true); };
$j = $ck($today);
t('4 check: before_min with the sentence', array($j['ok'], $j['errors'][0]['code'], (bool) preg_match('/^Please choose \x{2068}.+\x{2069} or a later date\.$/u', $j['errors'][0]['message'])), array(false, 'before_min', true));
$j = $ck($fri);
t('4 check: weekday_disabled', $j['errors'][0]['code'] ?? '', 'weekday_disabled');
$ok = $plus(2); while ((int) gmdate('N', strtotime($ok . ' 12:00 UTC')) === 5 || $ok === $hol) $ok = gmdate('Y-m-d', strtotime($ok . ' 12:00 UTC') + 86400);
$j = $ck($ok);
t('4 check: ok with the row', array($j['ok'], $j['value']['iso'], $j['value']['mode']), array(true, $ok, 'single'));

/* 5 */
$r = $get('forms/' . $fid . '/schema');
$s = json_decode($r['body'], true);
t('5 schema 200, Cache-Control private, no-store', array($r['status'], (bool) preg_match('/Cache-Control: private, no-store/i', $r['headers'])), array(200, true));
$byId = array(); foreach ($s['fields'] as $f) $byId[$f['id']] = $f;
$a = $byId[$ids['appt']] ?? array();
t('5 the advdate entry', array($a['type'] ?? '', $a['required'] ?? null, $a['help'] ?? '', $a['fill']['api'] ?? '', $a['fill']['call'] ?? ''), array('advdate', true, 'Pick a day', 'EFBCalendar', "EFBCalendar.get('" . $ids['appt'] . "', " . $fid . ').setValue(value)'));
t('5 limits as configured (cacheable)', array($a['x-efb-date']['constraints']['min'] ?? null, $a['x-efb-date']['constraints']['disabled_weekdays'] ?? null), array(array('type' => 'relative', 'value' => 1, 'unit' => 'day'), array(5)));
t('5 availability url', (bool) preg_match('#/wp-json/Emsfb/v1/calendar/availability\?form_id=' . $fid . '&field_id=' . $ids['appt'] . '$#', $a['x-efb-date']['availability_url'] ?? ''), true);
t('5 range entry: value_schema start / end', $byId[$ids['room']]['value_schema']['oneOf'][0]['required'] ?? null, array('start', 'end'));
t('5 the hidden field is left out', isset($byId[$ids['secret']]), false);
t('5 other fields: fill api dom', $byId[$ids['mail']]['fill'] ?? null, array('api' => 'dom', 'input' => $ids['mail'] . '_'));
foreach (array('owner-secret@example.test', 'rule-secret@example.test', 'SECRET-HOOK', 'SMS-SECRET-TEXT', 'TG-SECRET', 'internal-only.example', 'XltBLVpdezJ9JA', 'pattern_b64', 'email_domains', 'notification_rules', 'webhook_rules', 'bk_show_remaining', 'stripeSKey', 'logic_rules') as $secret) {
	t('5 no secret in the schema: ' . $secret, strpos($r['body'], $secret), false);
}
$php = $H->ctl('f06-schema', (string) $fid, $ids['appt']);
t('5 efb_form_schema() (PHP, contract §5) answers what the route answers', json_encode($php['schema'] ?? null), json_encode($s));
$one = $php['field'] ?? array();
t('5 efb_date_field_schema() gives the advdate part of the entry', array($one['fill']['api'] ?? '', $one['x-efb-date']['constraints']['min'] ?? null), array('EFBCalendar', array('type' => 'relative', 'value' => 1, 'unit' => 'day')));

/* 6 */
$H->ctl('set-option', 'efb_e2e_f06_rate', '3');
$codes = array(); for ($i = 0; $i < 6; $i++) $codes[] = $get('calendar/convert?date=2025-03-20&from=gregory&to=persian')['status'];
$H->ctl('set-option', 'efb_e2e_f06_rate', 'del');
t('6 the rate limit answers 429', in_array(429, $codes, true), true);

$H->ctl('f06-reset');
echo "\nRESULTS: {$passed} passed, {$failed} failed\n";
exit($failed ? 1 : 0);
