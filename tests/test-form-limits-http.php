<?php
/**
 * F11-C on a real WordPress (the e2e site; tests/f11-http-lib.php), over HTTP:
 *
 *  - each dimension works on its own: per IP (X-Forwarded-For through the site's own trusted
 *    local proxy), per cookie (efb_lim_<id>, HttpOnly, SameSite=Lax), per e-mail (upper case and
 *    spaces = the same address), per logged-in user (a guest is not limited by it);
 *  - prepare refuses a limited visitor too (403 efb_limit_reached, data.reopen_at at the next
 *    period for a "day" limit);
 *  - an injected database failure on the entry INSERT (mu-plugin efb-e2e-f11.php) answers
 *    efb_storage_failed and gives every place back (counters unchanged);
 *  - the IP mode "none" of F13: no per-IP counter (nothing stored about the address);
 *  - the builder's reset (AJAX efb_limits_reset_Emsfb, administrator only) frees the people;
 *  - Free plan: per-person limits are not applied (spec "سطح نسخه": Free Plus);
 *  - an unknown key in the body (draft_token) is accepted and never stored.
 *
 * Run (under the e2e lock): source the e2e env; php tests/test-form-limits-http.php
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
$H->ctl('plan', '3');
$H->ctl('f11-mu');
$rows = function ($F, $email = 'x@example.test') { return array(array('id_' => $F['email'], 'name' => 'Email', 'type' => 'email', 'value' => $email), array('id_' => $F['name'], 'name' => 'Name', 'type' => 'text', 'value' => 'Visitor')); };
$ok = function ($r) { return !empty(EfbF11Http::answer($r)['success']); };
$code = function ($r) { $a = EfbF11Http::answer($r); return isset($a['code']) ? $a['code'] : ''; };
$sub = function ($F, $email, $ip, $extra = array()) use ($H, $rows) {
	$prep = $H->http('POST', '/wp-json/Emsfb/v1/forms/session/prepare', array('form_id' => $F['form_id']), array('X-Forwarded-For' => $ip));
	$p = (array) $prep['json'];
	if ($prep['status'] !== 200) {
		// prepare refused this visitor (as the page would): a hand-made submit with any valid nonce.
		$p0 = (array) $H->http('POST', '/wp-json/Emsfb/v1/forms/session/prepare', array('form_id' => 0), array('X-Forwarded-For' => $ip))['json'];
		$p = array('nonce' => $p0['nonce'] ?? '', 'sid' => $p0['sid'] ?? '', 'runtime_token' => '');
		$extra += array('request_id' => '');
	}
	$rr = $rows($F, $email);
	foreach ($rr as $i => $r) $rr[$i] = array_merge(array('form_id' => $F['form_id'], 'amount' => $i + 2), $r);
	$body = array_merge(array('id' => $F['form_id'], 'type' => 'form', 'sid' => $p['sid'] ?? '', 'runtime_token' => $p['runtime_token'] ?? '', 'request_id' => EfbF11Http::uuid(), 'name' => 'F11', 'url' => $H->base . '/', 'page_id' => 0, 'value' => json_encode($rr)), $extra);
	return array($prep, $H->http('POST', '/wp-json/Emsfb/v1/forms/message/add', $body, array('X-WP-Nonce' => $p['nonce'] ?? '', 'sid' => $p['sid'] ?? '', 'X-Forwarded-For' => $ip)));
};

/* per IP, per day */
$F = $H->ctl('f11-form', 'F11 per IP', 'lim_ip=2&lim_period=day');
$H->fresh_jar(); list(, $a) = $sub($F, 'a@example.test', '203.0.113.10');
$H->fresh_jar(); list(, $b) = $sub($F, 'b@example.test', '203.0.113.10');
$H->fresh_jar(); list($p3, $c) = $sub($F, 'c@example.test', '203.0.113.10');
t('IP: two from one address saved', array($ok($a), $ok($b)), array(true, true));
t('IP: prepare for the third refuses (403 efb_limit_reached, reopen_at = next local midnight)', array($p3['status'], $p3['json']['code'] ?? '', isset($p3['json']['data']['reopen_at']) && $p3['json']['data']['reopen_at'] > time() && $p3['json']['data']['reopen_at'] <= time() + 86400), array(403, 'efb_limit_reached', true));
t('IP: the third submit is refused', array($c['status'], $code($c)), array(403, 'efb_limit_reached'));
$H->fresh_jar(); list(, $d) = $sub($F, 'd@example.test', '198.51.100.20');
t('IP: another address is not limited', $ok($d), true);
$cnt = $H->ctl('f11-counters', (string) $F['form_id']);
t('IP: counters hold HMACs only, never the address', strpos(json_encode($cnt), '203.0.113') === false && strpos(json_encode($cnt), '198.51.100') === false && count($cnt) === 2, true);

/* per cookie */
$F = $H->ctl('f11-form', 'F11 per cookie', 'lim_cookie=1');
$H->fresh_jar(); list(, $a) = $sub($F, 'a@example.test', '203.0.113.21');
t('cookie: first saved, efb_lim_<id> set HttpOnly and SameSite=Lax', array($ok($a), (bool) preg_match('/Set-Cookie: efb_lim_' . $F['form_id'] . '=[a-f0-9]{32};[^\r\n]*HttpOnly[^\r\n]*SameSite=Lax/i', $a['headers'])), array(true, true));
list(, $b) = $sub($F, 'b@example.test', '203.0.113.22');
t('cookie: the same browser (cookie) is refused even from another address', $code($b), 'efb_limit_reached');
$H->fresh_jar(); list(, $c) = $sub($F, 'c@example.test', '203.0.113.21');
t('cookie: a browser without the cookie is not (a cookie limit is weak by design)', $ok($c), true);

/* per e-mail */
$F = $H->ctl('f11-form', 'F11 per email', 'lim_email=1&lim_email_field={email}');
t('e-mail: lim_email_field kept (an email field of the form)', $F['row0']['lim_email_field'], $F['email']);
$H->fresh_jar(); list(, $a) = $sub($F, 'Same.Person@Example.test', '203.0.113.31');
$H->fresh_jar(); list(, $b) = $sub($F, '  same.person@example.TEST ', '203.0.113.32');
t('e-mail: upper case and spaces count as the same address', array($ok($a), $code($b)), array(true, 'efb_limit_reached'));
$H->fresh_jar(); list(, $c) = $sub($F, 'other@example.test', '203.0.113.31');
t('e-mail: another address is fine', $ok($c), true);
t('e-mail: the message of an "ever" limit', EfbF11Http::answer($b)['m'] ?? '', 'You have already sent this form.');

/* per logged-in user */
$F = $H->ctl('f11-form', 'F11 per user', 'lim_user=1&lim_period=week&lim_user_msg=' . urlencode('Once a week, please (until %s).'));
$H->ctl('user', 'efb_f11_member', 'subscriber');
$H->fresh_jar();
$H->login('efb_f11_member', 'efb_f11_member-pass-123');
list(, $a) = $sub($F, 'm@example.test', '203.0.113.41');
list($pp, $b) = $sub($F, 'm2@example.test', '203.0.113.42');
t('user: once, then refused with the owner\'s text and the reset date', array($ok($a), $code($b), (bool) preg_match('/^Once a week, please \(until .+\)\.$/', EfbF11Http::answer($b)['m'] ?? '')), array(true, 'efb_limit_reached', true));
$H->fresh_jar(); list(, $g1) = $sub($F, 'g@example.test', '203.0.113.43'); $H->fresh_jar(); list(, $g2) = $sub($F, 'g@example.test', '203.0.113.43');
t('user: a guest is not limited by lim_user', array($ok($g1), $ok($g2)), array(true, true));

/* failure injection: the places are given back */
$F = $H->ctl('f11-form', 'F11 rollback', 'lim_total=5&lim_email=1&lim_email_field={email}&lim_ip=3');
$H->fresh_jar(); list(, $a) = $sub($F, 'r1@example.test', '203.0.113.51');
$before = $H->ctl('f11-counters', (string) $F['form_id']);
$H->ctl('set-option', 'efb_e2e_f11_fail_insert', '1');
$H->fresh_jar(); list(, $b) = $sub($F, 'r2@example.test', '203.0.113.51');
$H->ctl('set-option', 'efb_e2e_f11_fail_insert', 'del');
$after = $H->ctl('f11-counters', (string) $F['form_id']);
$sum = function ($rows) { $s = array(); foreach ((array) $rows as $r) { $s[$r['scope']] = (isset($s[$r['scope']]) ? $s[$r['scope']] : 0) + (int) $r['hits']; } ksort($s); return $s; };
t('injected INSERT failure: 500 efb_storage_failed', array($b['status'], $code($b)), array(500, 'efb_storage_failed'));
t('injected INSERT failure: every place given back (counters as before)', $sum($after), $sum($before));
$H->fresh_jar(); list(, $c) = $sub($F, 'r2@example.test', '203.0.113.51');
t('the same visitor may send again at once', $ok($c), true);

/* F13 IP mode none: no per-IP counter */
$F = $H->ctl('f11-form', 'F11 no ip', 'lim_ip=1&prv_ip=none');
$H->fresh_jar(); list(, $a) = $sub($F, 'n1@example.test', '203.0.113.61');
$H->fresh_jar(); list(, $b) = $sub($F, 'n2@example.test', '203.0.113.61');
t('IP mode "none": the IP limit is not applied, no counter kept', array($ok($a), $ok($b), count((array) $H->ctl('f11-counters', (string) $F['form_id']))), array(true, true, 0));

/* reset (builder button) */
$F = $H->ctl('f11-form', 'F11 reset', 'lim_ip=1&lim_total=10');
$H->fresh_jar(); list(, $a) = $sub($F, 'z1@example.test', '203.0.113.71');
$H->fresh_jar(); list(, $b) = $sub($F, 'z2@example.test', '203.0.113.71');
t('reset: before, the second is refused', $code($b), 'efb_limit_reached');
$H->fresh_jar();
$H->login(getenv('EFB_E2E_USER') ?: 'admin', getenv('EFB_E2E_PASS') ?: 'admin-pass-123');
$nonce = $H->prepare(0)['json']['nonce'] ?? '';
$r = $H->http('POST', '/wp-admin/admin-ajax.php', http_build_query(array('action' => 'efb_limits_reset_Emsfb', 'nonce' => $nonce, 'form_id' => $F['form_id'])), array('Content-Type' => 'application/x-www-form-urlencoded'), false);
t('reset: the administrator\'s AJAX answers success', !empty($r['json']['data']['success']), true);
$left = $H->ctl('f11-counters', (string) $F['form_id']);
t('reset: per-person counters gone, the total kept', array_values(array_map(function ($x) { return $x['scope']; }, (array) $left)), array('lt:' . $F['form_id']));
$H->fresh_jar(); list(, $c) = $sub($F, 'z3@example.test', '203.0.113.71');
t('reset: the same address may send again', $ok($c), true);
$H->fresh_jar();
$H->ctl('user', 'efb_f11_member', 'subscriber');
$H->login('efb_f11_member', 'efb_f11_member-pass-123');
$n2 = $H->prepare(0)['json']['nonce'] ?? '';
$r = $H->http('POST', '/wp-admin/admin-ajax.php', http_build_query(array('action' => 'efb_limits_reset_Emsfb', 'nonce' => $n2, 'form_id' => $F['form_id'])), array('Content-Type' => 'application/x-www-form-urlencoded'), false);
t('reset: a subscriber is refused', empty($r['json']['data']['success']), true);

/* plan */
$F = $H->ctl('f11-form', 'F11 free', 'lim_ip=1');
$H->ctl('plan', '2');
$H->fresh_jar(); list(, $a) = $sub($F, 'f1@example.test', '203.0.113.81');
$H->fresh_jar(); list(, $b) = $sub($F, 'f2@example.test', '203.0.113.81');
$H->ctl('plan', '3');
t('Free plan: per-person limits are not applied', array($ok($a), $ok($b)), array(true, true));

/* unknown key */
$F = $H->ctl('f11-form', 'F11 unknown key', '');
$H->fresh_jar(); list(, $a) = $sub($F, 'u@example.test', '203.0.113.91', array('draft_token' => str_repeat('a', 64), 'efb_unknown' => array('x' => 1)));
$e = $H->ctl('entries', (string) $F['form_id']);
t('unknown body keys (draft_token, an object) are accepted and not stored', array($ok($a), strpos(json_encode($e), str_repeat('a', 64)) === false), array(true, true));

echo "\nRESULTS: {$passed} passed, {$failed} failed\n";
exit($failed ? 1 : 0);
