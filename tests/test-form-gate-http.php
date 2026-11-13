<?php
/**
 * F11-B and D on a real WordPress (the e2e site; tests/f11-http-lib.php), over HTTP as a browser:
 *
 *  B1 a closed form is refused at render (card, no form), at forms/session/prepare (403
 *     efb_form_closed with the visitor's sentence) and at a direct forms/message/add (403, no entry);
 *  B2 a form that is not open yet: pending card with the date, prepare 403 efb_form_pending + reopen_at;
 *  B3 an open form: prepare answers gate {state open, closes_at, left};
 *  B4 total cap 3 with 10 submissions at the same moment: exactly 3 entries, 7 refusals
 *     (efb_form_full), "places left" on the page; deleting one entry frees one place;
 *  B5 an old form without the keys: no gate block, submits as before;
 *  B6 payments/create on a full payment form is refused with efb_form_full before anything is
 *     stored (no entry, no payment row);
 *  D  a private form with allowed_roles=editor: a subscriber gets the role card, prepare and
 *     submit 403 efb_role_denied; an editor and an administrator pass; a guest still gets
 *     efb_login_required (today's behaviour).
 *
 * Run (under the e2e lock): source the e2e env; php tests/test-form-gate-http.php
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
$rows = function ($F, $email = 'guest@example.test') { return array(array('id_' => $F['email'], 'name' => 'Email', 'type' => 'email', 'value' => $email), array('id_' => $F['name'], 'name' => 'Name', 'type' => 'text', 'value' => 'Guest')); };
$entries = function ($fid) use ($H) { return count((array) $H->ctl('entries', (string) $fid)); };

/* B1 closed */
$F = $H->ctl('f11-form', 'F11 closed', 'sched_open=2025-01-01T09:00&sched_close=2025-02-01T18:00&sched_closed_msg=' . urlencode('Registration closed on %s.'));
$fid = $F['form_id'];
$html = $H->http('GET', $H->page($fid), null, array(), false)['body'];
t('B1 render: the closed card (role=status), no form markup', (bool) (preg_match('/data-efb-gate="closed"/', $html) && strpos($html, 'role="status"') !== false && strpos($html, 'id="body_efb_' . $fid . '"') === false), true);
t('B1 render: the owner\'s own sentence with the closing date', (bool) preg_match('/Registration closed on [^<]*2025[^<]*6:00 ?pm\./i', $html), true);
$p = $H->prepare($fid);
t('B1 prepare: 403 efb_form_closed with the sentence', array($p['status'], $p['json']['code'] ?? '', (bool) preg_match('/Registration closed on/', $p['json']['message'] ?? '')), array(403, 'efb_form_closed', true));
$s = $H->submit($fid, $rows($F), array('runtime_token' => '', 'request_id' => ''), $H->prepare(0));
t('B1 direct submit with a valid nonce: 403 efb_form_closed', array($s['status'], EfbF11Http::answer($s)['code'] ?? ''), array(403, 'efb_form_closed'));
t('B1 nothing stored', $entries($fid), 0);
$gs = $H->http('POST', '/wp-json/Emsfb/v1/forms/gate/state', array('form_id' => $fid));
t('B1 forms/gate/state (cached widgets): 200 closed with the sentence, no session made', array($gs['status'], $gs['json']['state'] ?? '', $gs['json']['code'] ?? '', (bool) preg_match('/Registration closed on/', $gs['json']['message'] ?? ''), (bool) preg_match('/Cache-Control:[^\r\n]*no-(store|cache)/i', $gs['headers'])), array(200, 'closed', 'efb_form_closed', true, true));

/* B2 pending */
$F = $H->ctl('f11-form', 'F11 pending', 'sched_open=2030-06-01T09:30');
$fid = $F['form_id'];
$html = $H->http('GET', $H->page($fid), null, array(), false)['body'];
t('B2 render: the pending card names the opening', (bool) (preg_match('/data-efb-gate="pending"/', $html) && preg_match('/This form opens on [^<]*2030[^<]*9:30 ?am\./i', $html)), true);
$p = $H->prepare($fid);
t('B2 prepare: 403 efb_form_pending, data.reopen_at = the opening', array($p['status'], $p['json']['code'] ?? '', isset($p['json']['data']['reopen_at']) && $p['json']['data']['reopen_at'] > time()), array(403, 'efb_form_pending', true));

/* B3/B4 open, cap 3 */
$F = $H->ctl('f11-form', 'F11 cap', 'lim_total=3&lim_show_left=1&sched_close=2035-01-01T00:00');
$fid = $F['form_id'];
$p = $H->prepare($fid);
t('B3 prepare: 200 with gate {state open, closes_at, left 3}', array($p['status'], $p['json']['gate']['state'] ?? '', isset($p['json']['gate']['closes_at']) && $p['json']['gate']['closes_at'] > time(), $p['json']['gate']['left'] ?? null), array(200, 'open', true, 3));
$html = $H->http('GET', $H->page($fid), null, array(), false)['body'];
t('B4 render: "3 places left" above the form (aria-live)', (bool) (preg_match('/<p class="efb efb-gate-left[^"]*"[^>]*aria-live="polite"[^>]*>3 places left<\/p>/', $html) && strpos($html, 'id="body_efb_' . $fid . '"') !== false), true);
// 10 at once: each with its own session.
$preps = array();
for ($i = 0; $i < 10; $i++) { $H->fresh_jar(); $preps[] = array($H->prepare($fid), $H->jar); }
$mh = curl_multi_init();
$hs = array();
foreach ($preps as $i => $pp) {
	$pj = $pp[0]['json'];
	$rr = $rows($F, "p{$i}@example.test");
	foreach ($rr as $k => $r) $rr[$k] = array_merge(array('form_id' => $fid, 'amount' => $k + 2), $r);
	$body = json_encode(array('id' => $fid, 'type' => 'form', 'sid' => $pj['sid'], 'runtime_token' => $pj['runtime_token'], 'request_id' => EfbF11Http::uuid(), 'name' => 'F11 cap', 'url' => $H->base . '/', 'page_id' => 0, 'value' => json_encode($rr)));
	$ch = curl_init($H->base . '/wp-json/Emsfb/v1/forms/message/add');
	curl_setopt_array($ch, array(CURLOPT_POST => true, CURLOPT_POSTFIELDS => $body, CURLOPT_RETURNTRANSFER => true, CURLOPT_COOKIEFILE => $pp[1], CURLOPT_COOKIEJAR => $pp[1],
		CURLOPT_HTTPHEADER => array('Content-Type: application/json', 'Origin: ' . $H->base, 'X-WP-Nonce: ' . $pj['nonce'], 'sid: ' . $pj['sid'], 'form-id: ' . $fid), CURLOPT_TIMEOUT => 120));
	curl_multi_add_handle($mh, $ch);
	$hs[] = $ch;
}
do { $st = curl_multi_exec($mh, $running); if ($running) curl_multi_select($mh, 1); } while ($running && $st === CURLM_OK);
$ok = 0; $full = 0; $other = array();
foreach ($hs as $ch) {
	$j = json_decode(curl_multi_getcontent($ch), true);
	$d = is_array($j) && isset($j['data']) ? $j['data'] : array();
	if (!empty($d['success'])) $ok++;
	elseif (($d['code'] ?? '') === 'efb_form_full') $full++;
	else $other[] = substr((string) curl_multi_getcontent($ch), 0, 200);
	curl_multi_remove_handle($mh, $ch);
	foreach ($preps as $pp) @unlink($pp[1]);
}
curl_multi_close($mh);
t('B4 10 submissions at once on a cap of 3: exactly 3 saved, 7 refused efb_form_full', array($ok, $full, $other), array(3, 7, array()));
t('B4 exactly 3 entries in the database', $entries($fid), 3);
$cnt = $H->ctl('f11-counters', (string) $fid);
t('B4 the total counter holds 3 (scope lt:<id>, no raw key)', array(count($cnt), $cnt[0]['scope'] ?? '', (int) ($cnt[0]['hits'] ?? -1)), array(1, 'lt:' . $fid, 3));
$H->fresh_jar();
$p = $H->prepare($fid);
t('B4 prepare on the full form: 403 efb_form_full', array($p['status'], $p['json']['code'] ?? ''), array(403, 'efb_form_full'));
$list = $H->ctl('entries', (string) $fid);
$H->ctl('f11-delete-entry', (string) $list[0]['msg_id']);
$H->fresh_jar();
$s = $H->submit($fid, $rows($F, 'again@example.test'));
t('B4 deleting one entry frees one place: the next submit is saved', !empty(EfbF11Http::answer($s)['success']), true);
$H->fresh_jar();
$s = $H->submit($fid, $rows($F, 'more@example.test'), array('runtime_token' => '', 'request_id' => ''), $H->prepare(0));
t('B4 then full again', array($s['status'], EfbF11Http::answer($s)['code'] ?? ''), array(403, 'efb_form_full'));

/* B5 old form */
$F = $H->ctl('f11-form', 'F11 old', '');
$H->fresh_jar();
$p = $H->prepare($F['form_id']);
t('B5 a form without F11 keys: prepare has no gate block', array($p['status'], array_key_exists('gate', (array) $p['json'])), array(200, false));
$s = $H->submit($F['form_id'], $rows($F));
t('B5 and submits as before', !empty(EfbF11Http::answer($s)['success']), true);

/* B6 payment form at its cap: payments/create refused before anything is stored */
$H->ctl('pay-mock', '1');
$H->ctl('pay-keys', 'test');
$P = $H->ctl('pay-form', 'fixed', 'F11 paid cap', 'lim_total=1');
$pf = $P['form_id'];
$H->ctl('f13-seed', (string) $pf, '1'); // one entry already: the cap of 1 is reached
$H->fresh_jar();
$pp = $H->prepare(0);
$pr = array(array('id_' => $P['email'], 'name' => 'Email', 'type' => 'email', 'value' => 'payer@example.test'), array('id_' => $P['radio'], 'id_ob' => $P['o1'], 'name' => 'Ticket', 'type' => 'payRadio', 'value' => 'Adult'));
$s = $H->submit($pf, $pr, array('type' => 'payment', 'runtime_token' => '', 'request_id' => '', 'pay' => array('field_id' => $P['pf'], 'attempt_key' => EfbF11Http::uuid(), 'method' => '', 'page_url' => $H->base . '/')), $pp, 'payments/create');
t('B6 payments/create on a full payment form: 403 efb_form_full with the sentence', array($s['status'], EfbF11Http::answer($s)['code'] ?? '', EfbF11Http::answer($s)['m'] ?? ''), array(403, 'efb_form_full', 'This form has reached its limit and no longer accepts answers.'));
t('B6 no new entry and no payment row', array($entries($pf), count((array) $H->ctl('payments', (string) $pf))), array(1, 0));

/* D roles */
$F = $H->ctl('f11-form', 'F11 roles', 'stateForm=1&allowed_roles=editor');
$fid = $F['form_id'];
t('D allowed_roles kept by the sanitizer (existing role)', $F['row0']['allowed_roles'] ?? '', 'editor');
$H->ctl('user', 'efb_f11_sub', 'subscriber');
$H->ctl('user', 'efb_f11_ed', 'editor');
$H->fresh_jar();
$p = $H->prepare($fid);
t('D guest: efb_login_required as before', array($p['status'], $p['json']['code'] ?? ''), array(403, 'efb_login_required'));
$H->fresh_jar();
t('D subscriber logs in', $H->login('efb_f11_sub', 'efb_f11_sub-pass-123'), true);
$html = $H->http('GET', $H->page($fid), null, array(), false)['body'];
t('D subscriber: the role card, no form', (bool) (preg_match('/data-efb-gate="role"/', $html) && strpos($html, 'Your account does not have access to this form.') !== false && strpos($html, 'id="body_efb_' . $fid . '"') === false), true);
$p = $H->prepare($fid);
t('D subscriber: prepare 403 efb_role_denied', array($p['status'], $p['json']['code'] ?? ''), array(403, 'efb_role_denied'));
$s = $H->submit($fid, $rows($F), array('runtime_token' => '', 'request_id' => ''), $H->prepare(0));
t('D subscriber: direct submit 403 efb_role_denied, nothing stored', array($s['status'], EfbF11Http::answer($s)['code'] ?? '', $entries($fid)), array(403, 'efb_role_denied', 0));
$H->fresh_jar();
$H->login('efb_f11_ed', 'efb_f11_ed-pass-123');
$s = $H->submit($fid, $rows($F));
t('D editor: saved', !empty(EfbF11Http::answer($s)['success']), true);
$H->fresh_jar();
$H->login(getenv('EFB_E2E_USER') ?: 'admin', getenv('EFB_E2E_PASS') ?: 'admin-pass-123');
$s = $H->submit($fid, $rows($F));
t('D administrator (manage_options) always passes', !empty(EfbF11Http::answer($s)['success']), true);

echo "\nRESULTS: {$passed} passed, {$failed} failed\n";
exit($failed ? 1 : 0);
