<?php
/**
 * F05-D on a real WordPress (the e2e site; tests/f11-http-lib.php), over HTTP as browsers:
 *
 *  1. 20 submissions of the same choice with 5 places at the same moment (20 sessions, curl_multi): exactly 5
 *     entries; 15 answers efb_choice_full naming the field and the choice; the count row says 5; the stored
 *     form did not change by one byte (the old code rewrote form_structer on every booking submit).
 *  2. The same with an older booking form (mlen 6, registered_count 3): exactly 3 more.
 *  3. A repeated request (same request_id, a lost answer): the saved answer, no second place.
 *  4. A refusal after the place was taken (a required field missing on the server side) gives nothing away.
 *  5. Deleting an entry (the panel's delete path) gives its place back only with inv_release.
 *
 * Run (under the e2e lock): source the e2e env; php tests/test-stock-concurrency-wp.php
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
$H->ctl('f05-mu');
$H->ctl('set-option', 'efb_e2e_f05_plan', 'open');

$burst = function ($fid, $rows_fn, $n) use ($H) {
	$preps = array();
	for ($i = 0; $i < $n; $i++) { $H->fresh_jar(); $preps[] = array($H->prepare($fid), $H->jar); }
	$mh = curl_multi_init();
	$hs = array();
	foreach ($preps as $i => $pp) {
		$pj = $pp[0]['json'];
		$rr = $rows_fn($i);
		foreach ($rr as $k => $r) $rr[$k] = array_merge(array('form_id' => $fid, 'amount' => $k + 2, 'session' => 'reciveFromClient'), $r);
		$body = json_encode(array('id' => $fid, 'type' => 'form', 'sid' => $pj['sid'], 'runtime_token' => $pj['runtime_token'], 'request_id' => EfbF11Http::uuid(), 'name' => 'F05', 'url' => $H->base . '/', 'page_id' => 0, 'value' => json_encode($rr)));
		$ch = curl_init($H->base . '/wp-json/Emsfb/v1/forms/message/add');
		curl_setopt_array($ch, array(CURLOPT_POST => true, CURLOPT_POSTFIELDS => $body, CURLOPT_RETURNTRANSFER => true, CURLOPT_COOKIEFILE => $pp[1], CURLOPT_COOKIEJAR => $pp[1], CURLOPT_TIMEOUT => 120,
			CURLOPT_HTTPHEADER => array('Content-Type: application/json', 'X-WP-Nonce: ' . $pj['nonce'], 'sid: ' . $pj['sid'], 'form-id: ' . $fid, 'Origin: ' . $H->base, 'Referer: ' . $H->base . '/')));
		curl_multi_add_handle($mh, $ch);
		$hs[] = $ch;
	}
	do { curl_multi_exec($mh, $run); curl_multi_select($mh, 1.0); } while ($run > 0);
	$out = array();
	foreach ($hs as $ch) { $j = json_decode(curl_multi_getcontent($ch), true); $out[] = is_array($j) && isset($j['data']) ? $j['data'] : $j; curl_multi_remove_handle($mh, $ch); }
	curl_multi_close($mh);
	return $out;
};
$count = function ($answers, $pred) { return count(array_filter($answers, $pred)); };

/* 1 */
$F = $H->ctl('f03-form', 'F05 stock burst', json_encode(array(
	array('key' => 'seat', 'type' => 'radio', 'name' => 'Seat', 'required' => 1, 'inv_on' => '1'),
	array('key' => 'a', 'type' => 'option', 'parent_key' => 'seat', 'value' => 'Last seats', 'inv_cap' => '5'),
	array('key' => 'b', 'type' => 'option', 'parent_key' => 'seat', 'value' => 'Plenty'),
)), '', '1');
$fid = $F['form_id']; $ids = $F['ids'];
$H->ctl('f05-stock', (string) $fid, 'reset');
$md5 = $H->ctl('f05-stock', (string) $fid)['form_md5'];
$ans = $burst($fid, function ($i) use ($ids) { return array(array('id_' => $ids['seat'], 'name' => 'Seat', 'id_ob' => $ids['a'], 'type' => 'radio', 'value' => 'Last seats')); }, 20);
$ok = $count($ans, function ($a) { return !empty($a['success']); });
$full = $count($ans, function ($a) use ($ids) { return isset($a['code']) && $a['code'] === 'efb_choice_full' && $a['field_id'] === $ids['seat'] && strpos($a['m'], 'Last seats') !== false; });
t('20 at once on 5 places: exactly 5 entries', array($ok, count((array) $H->ctl('entries', (string) $fid))), array(5, 5));
t('the other 15 are told the choice is full (efb_choice_full, field and choice named)', $full, 15);
$st = $H->ctl('f05-stock', (string) $fid);
t('the count row says 5, no place left', array($st['rows'][0]['used'] ?? null, $st['left'][$ids['a']] ?? null), array('5', 0));
t('the stored form did not change by one byte', $st['form_md5'], $md5);

/* 2 */
$B = $H->ctl('f03-form', 'F05 stock booking burst', json_encode(array(
	array('key' => 'slot', 'type' => 'radio', 'name' => 'Slot', 'required' => 1),
	array('key' => 'm', 'type' => 'option', 'parent_key' => 'slot', 'value' => 'Morning', 'mlen' => '6', 'registered_count' => '3', 'dateExp' => '2099-01-01'),
)), 'booking=1', '1');
$bid = $B['form_id'];
$H->ctl('f05-stock', (string) $bid, 'reset');
$bmd5 = $H->ctl('f05-stock', (string) $bid)['form_md5'];
$ans = $burst($bid, function ($i) use ($B) { return array(array('id_' => $B['ids']['slot'], 'name' => 'Slot', 'id_ob' => $B['ids']['m'], 'type' => 'radio', 'value' => 'Morning')); }, 10);
t('an older booking form (6 places, 3 kept in its JSON), 10 at once: exactly 3 more', $count($ans, function ($a) { return !empty($a['success']); }), 3);
$bst = $H->ctl('f05-stock', (string) $bid);
t('its count row says 6; the form (and its registered_count) untouched', array($bst['rows'][0]['used'] ?? null, $bst['form_md5'] === $bmd5), array('6', true));

/* 3 */
$G = $H->ctl('f03-form', 'F05 stock replay', json_encode(array(
	array('key' => 'seat', 'type' => 'radio', 'name' => 'Seat', 'inv_on' => '1', 'inv_release' => '1'),
	array('key' => 'a', 'type' => 'option', 'parent_key' => 'seat', 'value' => 'Front', 'inv_cap' => '3'),
	array('key' => 'mail', 'type' => 'email', 'name' => 'Mail', 'required' => 1),
)), '', '1');
$gid = $G['form_id'];
$H->ctl('f05-stock', (string) $gid, 'reset');
$H->fresh_jar();
$prep = $H->prepare($gid);
$rows = array(array('id_' => $G['ids']['seat'], 'name' => 'Seat', 'id_ob' => $G['ids']['a'], 'type' => 'radio', 'value' => 'Front'), array('id_' => $G['ids']['mail'], 'name' => 'Mail', 'type' => 'email', 'value' => 'a@example.test'));
$rid = EfbF11Http::uuid();
$a1 = EfbF11Http::answer($H->submit($gid, $rows, array('request_id' => $rid), $prep));
$a2 = EfbF11Http::answer($H->submit($gid, $rows, array('request_id' => $rid), $prep));
t('a repeated request (same request_id): the saved answer, the place counted once', array(!empty($a1['success']), ($a2['track'] ?? 'x') === ($a1['track'] ?? 'y'), $H->ctl('f05-stock', (string) $gid)['rows'][0]['used'] ?? null), array(true, true, '1'));

/* 4 */
$H->fresh_jar();
$a = EfbF11Http::answer($H->submit($gid, array($rows[0])));
t('a refusal (the required e-mail missing) takes no place', array(!empty($a['success']), $H->ctl('f05-stock', (string) $gid)['rows'][0]['used'] ?? null), array(false, '1'));

/* 5 */
$e = (array) $H->ctl('entries', (string) $gid);
$H->ctl('f11-delete-entry', (string) $e[0]['msg_id']);
t('deleting the entry gives its place back (inv_release on)', $H->ctl('f05-stock', (string) $gid)['rows'][0]['used'] ?? null, '0');
$e = (array) $H->ctl('entries', (string) $fid);
$H->ctl('f11-delete-entry', (string) $e[0]['msg_id']);
t('without inv_release a deleted entry keeps its place', $H->ctl('f05-stock', (string) $fid)['rows'][0]['used'] ?? null, '5');

echo "\nRESULTS: {$passed} passed, {$failed} failed\n";
exit($failed ? 1 : 0);
