<?php
/**
 * F03-D on a real WordPress (the e2e site; tests/f11-http-lib.php), over HTTP as a browser:
 *
 *  1. 10 submissions with the same e-mail at the same moment (10 sessions, curl_multi): exactly one
 *     entry; nine answers efb_field_unique with the field id; one claim row holding the entry id.
 *  2. The same address in other case and with spaces is refused; a masked mobile typed in Persian
 *     digits is the same value as in Latin digits.
 *  3. Deleting the entry (the panel's delete path) frees the value.
 *  4. Older answers: a form with 3 answers (two the same) gets unique on its e-mail field (a save):
 *     the save hook hashes them (backfill done, 2 rows, 1 duplicate counted, nothing deleted), and a
 *     new submission with an old address is refused.
 *  5. Probing: after the per-minute limit of refusals the answer is the general sentence, no field.
 *  6. A form that stores no entries (F13 prv_nostore) still accepts each value once.
 *  7. An old form without the keys: same answers as before (saved, no claim row).
 *
 * Run (under the e2e lock): source the e2e env; php tests/test-unique-values-wp.php
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
$H->ctl('set-option', 'efb_e2e_unique_fail_limit', 'del');
$entries = function ($fid) use ($H) { return (array) $H->ctl('entries', (string) $fid); };
$uniq = function ($fid, $reset = '') use ($H) { return $H->ctl('f03-uniq', (string) $fid, $reset); };
$form = function ($name, $fields, $row0 = '') use ($H) { return $H->ctl('f03-form', $name, json_encode($fields), $row0); };
$F = $form('F03 unique http', array(
	array('key' => 'email', 'type' => 'email', 'name' => 'Email', 'required' => 1, 'unique' => '1'),
	array('key' => 'mobile', 'type' => 'tel', 'name' => 'Mobile', 'unique' => '1', 'mask_preset' => 'ir.mobile'),
));
$fid = $F['form_id'];
$uniq($fid, 'reset');
$row = function ($email, $mobile = '') use ($F) {
	$r = array(array('id_' => $F['ids']['email'], 'name' => 'Email', 'type' => 'email', 'value' => $email));
	if ($mobile !== '') $r[] = array('id_' => $F['ids']['mobile'], 'name' => 'Mobile', 'type' => 'tel', 'value' => $mobile);
	return $r;
};

/* 1 */
$preps = array();
for ($i = 0; $i < 10; $i++) { $H->fresh_jar(); $preps[] = array($H->prepare($fid), $H->jar); }
$mh = curl_multi_init();
$hs = array();
foreach ($preps as $i => $pp) {
	$pj = $pp[0]['json'];
	$rr = $row('same@example.test');
	foreach ($rr as $k => $r) $rr[$k] = array_merge(array('form_id' => $fid, 'amount' => $k + 2), $r);
	$body = json_encode(array('id' => $fid, 'type' => 'form', 'sid' => $pj['sid'], 'runtime_token' => $pj['runtime_token'], 'request_id' => EfbF11Http::uuid(), 'name' => 'F03', 'url' => $H->base . '/', 'page_id' => 0, 'value' => json_encode($rr)));
	$ch = curl_init($H->base . '/wp-json/Emsfb/v1/forms/message/add');
	curl_setopt_array($ch, array(CURLOPT_POST => true, CURLOPT_POSTFIELDS => $body, CURLOPT_RETURNTRANSFER => true, CURLOPT_COOKIEFILE => $pp[1], CURLOPT_COOKIEJAR => $pp[1],
		CURLOPT_HTTPHEADER => array('Content-Type: application/json', 'Origin: ' . $H->base, 'X-WP-Nonce: ' . $pj['nonce'], 'sid: ' . $pj['sid'], 'form-id: ' . $fid), CURLOPT_TIMEOUT => 120));
	curl_multi_add_handle($mh, $ch);
	$hs[] = $ch;
}
do { $st = curl_multi_exec($mh, $running); if ($running) curl_multi_select($mh, 1); } while ($running && $st === CURLM_OK);
$ok = 0; $dup = 0; $other = array();
foreach ($hs as $ch) {
	$j = json_decode(curl_multi_getcontent($ch), true);
	$d = is_array($j) && isset($j['data']) ? $j['data'] : array();
	if (!empty($d['success'])) $ok++;
	elseif (($d['code'] ?? '') === 'efb_field_unique' && ($d['field_id'] ?? '') === $F['ids']['email']) $dup++;
	else $other[] = substr((string) curl_multi_getcontent($ch), 0, 200);
	curl_multi_remove_handle($mh, $ch);
}
foreach ($preps as $pp) @unlink($pp[1]);
curl_multi_close($mh);
t('1 10 submissions with one address at once: 1 saved, 9 refused efb_field_unique at the e-mail field', array($ok, $dup, $other), array(1, 9, array()));
$e = $entries($fid);
$u = $uniq($fid, 'reset')['rows'];
t('1 one entry, one claim row holding that entry', array(count($e), count($u), (string) ($u[0]['msg_id'] ?? ''), strpos(json_encode($u), 'same@') === false), array(1, 1, (string) ($e[0]['msg_id'] ?? '-'), true));

/* 2 */
$a = EfbF11Http::answer($H->submit($fid, $row('  SAME@Example.TEST ')));
t('2 other case and spaces: refused with the default sentence', array($a['success'] ?? null, $a['code'] ?? '', $a['m'] ?? ''), array(false, 'efb_field_unique', 'This value has already been used.'));
$a = EfbF11Http::answer($H->submit($fid, $row('m1@example.test', '۰۹۱۲۱۲۳۴۵۶۷')));
t('2 a masked mobile in Persian digits is saved (canonical)', $a['success'] ?? null, true);
$a = EfbF11Http::answer($H->submit($fid, $row('m2@example.test', '0912 123 4567')));
t('2 the same mobile in Latin digits: refused at the mobile field', array($a['code'] ?? '', $a['field_id'] ?? ''), array('efb_field_unique', $F['ids']['mobile']));
t('2 nothing stored for the refused ones', count($entries($fid)), 2);

/* 3 */
$first = $entries($fid)[0];
$H->ctl('f11-delete-entry', (string) $first['msg_id']);
$a = EfbF11Http::answer($H->submit($fid, $row('same@example.test')));
t('3 after the entry is deleted the address can be used again', $a['success'] ?? null, true);

/* 4 */
$uniq($fid, 'reset');
$G = $form('F03 unique backfill', array(array('key' => 'email', 'type' => 'email', 'name' => 'Email', 'required' => 1)));
$gid = $G['form_id'];
foreach (array('old1@example.test', 'old2@example.test', 'OLD1@example.test') as $addr) {
	EfbF11Http::answer($H->submit($gid, array(array('id_' => $G['ids']['email'], 'name' => 'Email', 'type' => 'email', 'value' => $addr))));
}
t('4 three older answers (two the same address) before unique', count($entries($gid)), 3);
$s = $H->ctl('f03-field', (string) $gid, $G['ids']['email'], 'unique=1');
t('4 the save hook hashed the older answers: done, 2 claims, 1 duplicate counted', array($s['backfill']['done'] ?? null, $s['backfill']['inserted'] ?? null, $s['backfill']['duplicates'] ?? null), array(true, 2, 1));
t('4 nothing was deleted', count($entries($gid)), 3);
$a = EfbF11Http::answer($H->submit($gid, array(array('id_' => $G['ids']['email'], 'name' => 'Email', 'type' => 'email', 'value' => 'Old2@Example.test'))));
t('4 an older address is refused now', $a['code'] ?? '', 'efb_field_unique');

/* 5 */
$H->ctl('set-option', 'efb_e2e_unique_fail_limit', '2');
$uniq($fid, 'reset');
$codes = array();
for ($i = 0; $i < 3; $i++) {
	$a = EfbF11Http::answer($H->submit($fid, $row('same@example.test')));
	$codes[] = array($a['code'] ?? '', !empty($a['field_id']) ? 'field' : 'nofield');
}
t('5 limit 2 a minute: two answers name the field, the third is general', $codes, array(array('efb_field_unique', 'field'), array('efb_field_unique', 'field'), array('', 'nofield')));
$H->ctl('set-option', 'efb_e2e_unique_fail_limit', 'del');
$uniq($fid, 'reset');

/* 6 */
$N = $form('F03 unique nostore', array(array('key' => 'email', 'type' => 'email', 'name' => 'Email', 'required' => 1, 'unique' => '1')), 'prv_nostore=1');
$nid = $N['form_id'];
$a1 = EfbF11Http::answer($H->submit($nid, array(array('id_' => $N['ids']['email'], 'name' => 'Email', 'type' => 'email', 'value' => 'ns@example.test'))));
$a2 = EfbF11Http::answer($H->submit($nid, array(array('id_' => $N['ids']['email'], 'name' => 'Email', 'type' => 'email', 'value' => 'ns@example.test'))));
$nu = $uniq($nid)['rows'];
t('6 a form that stores no entries: no entry, one claim (msg_id 0), the second is refused', array($a1['success'] ?? null, count($entries($nid)), count($nu), (string) ($nu[0]['msg_id'] ?? ''), $a2['code'] ?? ''), array(true, 0, 1, '0', 'efb_field_unique'));

/* 7 */
$O = $form('F03 old form', array(array('key' => 'email', 'type' => 'email', 'name' => 'Email', 'required' => 1)));
$oid = $O['form_id'];
$o1 = EfbF11Http::answer($H->submit($oid, array(array('id_' => $O['ids']['email'], 'name' => 'Email', 'type' => 'email', 'value' => 'x@example.test'))));
$o2 = EfbF11Http::answer($H->submit($oid, array(array('id_' => $O['ids']['email'], 'name' => 'Email', 'type' => 'email', 'value' => 'x@example.test'))));
t('7 an old form without the keys: both saved, no claim row', array($o1['success'] ?? null, $o2['success'] ?? null, count($uniq($oid)['rows'])), array(true, true, 0));

echo "\nRESULTS: {$passed} passed, {$failed} failed\n";
exit($failed ? 1 : 0);
