<?php
/**
 * F20-core P14 under load, on the real WordPress (tests/e2e-wp, the mock portal add-on efb-e2e-f20.php): the last place
 * of a choice (F05, capacity 1) is held by an entry; its owner cancels it 10 times at once while 10 visitors try to
 * book that place at the same moment (20 requests in parallel, curl_multi against the PHP server's workers):
 *  - exactly one cancel succeeds, the others answer efb_portal_conflict;
 *  - the place is given back once: the used count ends equal to the active bookings that hold it (never above the
 *    capacity, never below zero), whatever the order;
 *  - at most one visitor gets the place.
 *
 * Run (the e2e site, under the e2e lock): php tests/test-owner-cancel-http.php
 */
require __DIR__ . '/f12b-http-lib.php';
$H = new EfbF12bHttp();
if ($why = $H->unavailable()) { echo "[SKIP] {$why}\n"; echo "\nRESULTS: 0 passed, 0 failed\n"; exit(0); }
$c20 = function (...$a) use ($H) {
	$parts = array_merge(preg_split('/\s+/', trim($H->wpcli)), array('eval-file', $H->root . '/tests/e2e-wp/ctl-f20.php'), array_map('strval', $a), array('--path=' . $H->site, '--allow-root'));
	$lines = array_values(array_filter(explode("\n", (string) shell_exec(implode(' ', array_map('escapeshellarg', $parts)) . ' 2>/dev/null')), 'strlen'));
	return json_decode($lines ? end($lines) : '', true);
};
$run = substr(md5((string) microtime(true)), 0, 6);
$mail = "cancel.{$run}@example.test";
try {
	$H->ctl('plan', '3'); $H->ctl('f11-mu'); $H->ctl('f05-mu'); $H->ctl('f06-mu');
	$H->ctl('set-option', 'efb_e2e_f05_plan', 'open'); $H->ctl('set-option', 'efb_e2e_f06_plan', '2');
	$H->ctl('set-option', 'efb_e2e_capture_mail', '1');
	$smtp0 = $H->ctl('setting', 'smtp');
	$H->ctl('f12-setting', 'smtp=1&secretKey=del&siteKey=del');
	$H->ctl('f06-set', 'booking', '0'); // the F06 Booking stand-in's one-place-per-date rule is not this test's
	$c20('mu'); $c20('on', '1'); $c20('reset'); $c20('index'); $c20('bef', '1');
	$F = $H->ctl('f03-form', 'Last seat ' . $run, json_encode(array(
		array('key' => 'em', 'type' => 'email', 'name' => 'Email'),
		array('key' => 'seat', 'type' => 'radio', 'name' => 'Seat', 'inv_on' => '1'),
		array('key' => 'last', 'type' => 'option', 'parent_key' => 'seat', 'value' => 'Last', 'inv_cap' => '1', 'inv_release' => '1'),
		array('key' => 'day', 'type' => 'advdate', 'name' => 'Day', 'date_min' => array('type' => 'relative', 'value' => 1)),
	)), 'prv_id_field=@@em@@', '1');
	$fid = (int) $F['form_id']; $i = $F['ids'];
	$c20('policy', $fid, json_encode(array('on' => true, 'cancel' => true, 'cancel_hours' => 24)));
	$c20('limits', json_encode(array('prc' => 100)));
	$rows = function ($email) use ($i) { return array(
		array('id_' => $i['em'], 'name' => 'Email', 'type' => 'email', 'value' => $email),
		array('id_' => $i['seat'], 'id_ob' => $i['last'], 'name' => 'Seat', 'type' => 'radio', 'value' => 'Last'),
		array('id_' => $i['day'], 'name' => 'Day', 'type' => 'advdate', 'value' => gmdate('Y-m-d', time() + 9 * 86400), 'cal' => 'gregory'),
	); };
	$a = EfbF11Http::answer($H->submit($fid, $rows($mail)));
	$E = $H->ctl('f14-entries', $fid);
	$used = function () use ($c20, $fid, $i) { foreach ((array) $c20('stock', $fid) as $r) if ($r['option_id'] === $i['last']) return (int) $r['used']; return 0; };
	if (empty($a['success'])) echo json_encode($a), "\n";
	t('the entry holds the last place (used 1 of 1)', array(!empty($a['success']), $used()), array(true, 1));

	// The owner's session (signed in by e-mail code through the mock).
	$jar = sys_get_temp_dir() . '/efb-cancel-' . getmypid();
	$H->ctl('set-option', 'efb_e2e_mail_bodies', 'del');
	$s = $H->http('POST', '/wp-json/Emsfb/v1/e2e-portal/start', array('channel' => 'email', 'dest' => $mail), array(), true, $jar);
	$ml = $H->ctl('option', 'efb_e2e_mail_bodies'); $ml = is_array($ml) ? array_values($ml) : array();
	$code = preg_match('/letter-spacing:6px[^>]*>(\d{4,10})</', (string) (end($ml)['message'] ?? ''), $x) ? $x[1] : '';
	$v = $H->http('POST', '/wp-json/Emsfb/v1/e2e-portal/verify', array('cid' => $s['json']['data']['challenge_id'] ?? '', 'code' => $code), array(), true, $jar);
	$csrf = $v['json']['data']['csrf'] ?? '';
	t('the owner is signed in', $csrf !== '', true);

	// 10 visitors, each with its own session, ready to send.
	$subs = array();
	for ($k = 0; $k < 10; $k++) {
		$H->fresh_jar();
		$p = $H->prepare($fid);
		$sid = $p['json']['sid'] ?? '';
		$body = array('id' => $fid, 'type' => 'form', 'sid' => $sid, 'runtime_token' => $p['json']['runtime_token'] ?? '', 'request_id' => EfbF11Http::uuid(), 'name' => 'F', 'url' => $H->base . '/', 'page_id' => 0,
			'value' => json_encode(array_map(function ($r, $n) use ($fid) { return array_merge(array('form_id' => $fid, 'amount' => $n + 2), $r); }, $rows("rush{$k}.{$run}@example.test"), array_keys($rows('x')))));
		$subs[] = array('url' => $H->base . '/wp-json/Emsfb/v1/forms/message/add', 'body' => json_encode($body), 'headers' => array('Content-Type: application/json', 'X-WP-Nonce: ' . ($p['json']['nonce'] ?? ''), 'sid: ' . $sid, 'form-id: ' . $fid, 'Origin: ' . $H->base), 'jar' => $H->jar);
		$H->jar .= '-k'; // keep the file of this visitor
	}
	$mh = curl_multi_init(); $hs = array();
	$add = function ($url, $body, $headers, $jar) use ($mh, &$hs) {
		$ch = curl_init($url);
		curl_setopt_array($ch, array(CURLOPT_POST => true, CURLOPT_POSTFIELDS => $body, CURLOPT_HTTPHEADER => $headers, CURLOPT_RETURNTRANSFER => true, CURLOPT_COOKIEFILE => $jar, CURLOPT_TIMEOUT => 120));
		curl_multi_add_handle($mh, $ch); $hs[] = $ch;
	};
	for ($k = 0; $k < 10; $k++) {
		$add($H->base . '/wp-json/Emsfb/v1/e2e-portal/cancel', json_encode(array('id' => (int) $E[0]['msg_id'])), array('Content-Type: application/json', 'X-EFB-Portal-CSRF: ' . $csrf, 'Origin: ' . $H->base), $jar);
		$add($subs[$k]['url'], $subs[$k]['body'], $subs[$k]['headers'], $subs[$k]['jar']);
	}
	do { curl_multi_exec($mh, $act); curl_multi_select($mh, 1.0); } while ($act > 0);
	$cancel_ok = 0; $conflict = 0; $booked = 0; $other = array();
	foreach ($hs as $n => $ch) {
		$j = json_decode((string) curl_multi_getcontent($ch), true);
		if ($n % 2 === 0) { if (!empty($j['success'])) $cancel_ok++; elseif (($j['code'] ?? '') === 'efb_portal_conflict') $conflict++; else $other[] = $j['code'] ?? substr((string) curl_multi_getcontent($ch), 0, 80); }
		else { $d = isset($j['data']) ? $j['data'] : $j; if (!empty($d['success'])) $booked++; }
		curl_multi_remove_handle($mh, $ch); curl_close($ch);
	}
	curl_multi_close($mh);
	echo "visitors who got the place: {$booked}\n";
	$owner = $c20('msg', $E[0]['msg_id']);
	t('20 requests at once: exactly one cancel succeeded, the other nine are efb_portal_conflict', array($cancel_ok, $conflict, $other), array(1, 9, array()));
	t('the entry is cancelled once', (int) $owner['owner_state'], 1);
	$active = 0;
	foreach ((array) $H->ctl('f14-entries', $fid) as $e) if ((int) $e['msg_id'] !== (int) $E[0]['msg_id']) $active++;
	t('at most one visitor got the place, and the count is exact (used = the active bookings holding it, ≤ 1)', array($booked <= 1, $active, $used()), array(true, $booked, $booked));
	foreach (glob(sys_get_temp_dir() . '/efb-f11-jar-' . getmypid() . '*') ?: array() as $f) @unlink($f);
	@unlink($jar);
} finally {
	$c20('on', '0'); $c20('bef', '0'); $c20('limits', '');
	$H->ctl('f12-setting', 'smtp=' . (isset($smtp0) && $smtp0 !== null ? rawurlencode((string) $smtp0) : 'del'));
}
efb_done();
