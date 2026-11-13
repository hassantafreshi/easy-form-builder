<?php
/**
 * F11-E "save and continue later" on a real WordPress (the e2e site; tests/f11-http-lib.php):
 *
 *  - the page shows the button; forms/draft/save answers a 256-bit token, the link and its end;
 *  - the database row holds no password, no unknown field, no file / payment row, and neither the
 *    token nor the address: only HMACs (token_hash, owner_hash, ip_hash);
 *  - saving again in the same tab updates the same draft; load gives the answers back;
 *  - a wrong token 10 times → 429 for that address (also for the right token);
 *  - a logged-in user's draft opens only for that user (not for another user, not for a guest);
 *  - the e-mail carries the link and the form name, never an answer; limited per draft;
 *  - a submit with draft_token empties the draft (completed); discard empties it too;
 *  - refused without a runtime token (409), from another origin (403), without draft_on or below
 *    Pro (403 efb_feature_unavailable), on a closed form (efb_form_closed);
 *  - retention: an expired draft is removed by the daily job; WordPress' exporter / eraser find it.
 *
 * Run (under the e2e lock): source the e2e env; php tests/test-drafts-http.php
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
$H->ctl('f11-mu');
$H->ctl('plan', '3');
// Pro: a Pro plan needs a licence on a real site; the test opens the part through the plugin's filter (mu-plugin).
$H->ctl('set-option', 'efb_e2e_f11_plan', '1');
$F = $H->ctl('f11-form', 'F11 drafts', 'draft_on=1&draft_days=30', '2');
$fid = $F['form_id'];
$rows = array(
	array('id_' => $F['email'], 'id_ob' => $F['email'] . '_', 'name' => 'Email', 'type' => 'email', 'value' => 'draft.person@example.test'),
	array('id_' => $F['name'], 'id_ob' => $F['name'] . '_', 'name' => 'Name', 'type' => 'text', 'value' => 'Draft Person'),
	array('id_' => $F['city'], 'id_ob' => $F['city'] . '_', 'name' => 'City', 'type' => 'text', 'value' => 'Shiraz'),
	array('id_' => $F['password'], 'id_ob' => $F['password'] . '_', 'name' => 'Secret', 'type' => 'password', 'value' => 'hunter2-secret'),
	array('id_' => $F['notes'], 'id_ob' => $F['notes'] . '_', 'name' => 'Notes', 'type' => 'textarea', 'value' => 'line one@n#line two'),
	array('id_' => $F['file'], 'id_ob' => $F['file'] . '_', 'name' => 'Attachment', 'type' => 'text', 'value' => 'https://evil.test/x.php', 'url' => 'https://evil.test/x.php'),
	array('id_' => 'notafield', 'name' => 'Injected', 'type' => 'text', 'value' => 'should not be kept'),
	array('id_' => 'payment', 'name' => 'payment', 'type' => 'payment', 'value' => 'tok_123'),
);
// A fresh address for this run (the site's own local proxy is trusted): the draft routes count per address.
$RUN_IP = '198.51.' . mt_rand(1, 250) . '.' . mt_rand(1, 250);
$post = function ($route, $body, $prep = null, $headers = array()) use ($H, $fid, $RUN_IP) {
	$prep = $prep ?: $H->prepare($fid);
	$p = (array) $prep['json'];
	$body = array_merge(array('form_id' => $fid, 'runtime_token' => $p['runtime_token'] ?? ''), $body);
	return $H->http('POST', '/wp-json/Emsfb/v1/' . $route, $body, array_merge(array('X-WP-Nonce' => $p['nonce'] ?? '', 'sid' => $p['sid'] ?? '', 'X-Forwarded-For' => $RUN_IP), $headers));
};
$WRONG_IP = '203.0.' . mt_rand(1, 250) . '.' . mt_rand(1, 250);

$H->fresh_jar();
$html = $H->http('GET', $H->page($fid), null, array(), false)['body'];
t('the page shows "Save and continue later"', (bool) preg_match('/<button type="button" class="efb btn btn-outline-secondary btn-sm efb-draft-save" id="efb-draft-save-' . $fid . '"/', $html), true);
$prep = $H->prepare($fid);
$r = $post('forms/draft/save', array('rows' => $rows, 'step' => 2, 'url' => $H->base . '/f11/?a=1&efb_resume=old'), $prep);
$tok = $r['json']['resume_token'] ?? '';
if ($r['status'] !== 200) echo '  save answered: ' . substr($r['body'], 0, 300) . "\n";
t('save: 200, a 64-hex token, the link with ?efb_resume=<token>, its end', array($r['status'], (bool) preg_match('/^[a-f0-9]{64}$/', $tok), strpos($r['json']['url'] ?? '', 'efb_resume=' . $tok) !== false && strpos($r['json']['url'], 'efb_resume=old') === false, isset($r['json']['expires_text']) && $r['json']['expires_text'] !== ''), array(200, true, true, true));
$d = $H->ctl('f11-drafts', (string) $fid);
$dump = json_encode($d);
t('one draft row (kind resume, step 2)', array(count($d), $d[0]['kind'] ?? '', (int) ($d[0]['step'] ?? 0)), array(1, 'resume', 2));
t('stored: email, name, city', strpos($dump, 'draft.person@example.test') !== false && strpos($dump, 'Draft Person') !== false && strpos($dump, 'Shiraz') !== false, true);
t('not stored: the password, the upload field, an unknown field, the payment row', array(strpos($dump, 'hunter2'), strpos($dump, 'evil.test'), strpos($dump, 'should not be kept'), strpos($dump, 'tok_123')), array(false, false, false, false));
t('not stored: the token itself (only its HMAC) and no raw IP', array(strpos($dump, $tok), (bool) preg_match('/^[a-f0-9]{64}$/', $d[0]['token_hash']), strpos($dump, '127.0.0.1')), array(false, true, false));
$r2 = $post('forms/draft/save', array('rows' => array_slice($rows, 0, 2), 'step' => 1, 'resume_token' => $tok, 'url' => $H->base . '/f11/'), $prep);
t('saving again in the same tab updates the same draft', array($r2['status'], $r2['json']['resume_token'] ?? '', count($H->ctl('f11-drafts', (string) $fid))), array(200, $tok, 1));
$post('forms/draft/save', array('rows' => $rows, 'step' => 2, 'resume_token' => $tok, 'url' => $H->base . '/f11/'), $prep);
$H->fresh_jar();
$l = $post('forms/draft/load', array('resume_token' => $tok));
$ids = array_map(function ($x) { return $x['id_']; }, (array) ($l['json']['rows'] ?? array()));
t('load (another browser, guest draft): the answers and the step, no password', array($l['status'], in_array($F['email'], $ids, true), in_array($F['password'], $ids, true), (int) ($l['json']['step'] ?? 0)), array(200, true, false, 2));
t('load is not cached', (bool) preg_match('/Cache-Control:[^\r\n]*no-(store|cache)/i', $l['headers']), true);

// Wrong tokens: 10, then 429 even for the right one (per address).
$H->fresh_jar();
$codes = array();
for ($i = 0; $i < 10; $i++) $codes[] = $post('forms/draft/load', array('resume_token' => str_repeat(dechex($i), 64)), null, array('X-Forwarded-For' => $WRONG_IP))['status'];
t('10 wrong tokens: 404 each', array_unique($codes), array(404));
$x = $post('forms/draft/load', array('resume_token' => $tok), null, array('X-Forwarded-For' => $WRONG_IP));
t('then 429, also with the right token', $x['status'], 429);
t('another address is not blocked', $post('forms/draft/load', array('resume_token' => $tok), null, array('X-Forwarded-For' => '203.0.' . mt_rand(1, 250) . '.201'))['status'], 200);

// A logged-in user's draft.
$H->ctl('user', 'efb_f11_da', 'subscriber');
$H->ctl('user', 'efb_f11_db', 'subscriber');
$H->fresh_jar();
$H->login('efb_f11_da', 'efb_f11_da-pass-123');
$ra = $post('forms/draft/save', array('rows' => array_slice($rows, 0, 2), 'step' => 1, 'url' => $H->base . '/'));
$ta = $ra['json']['resume_token'] ?? '';
t('user A saves a draft', $ra['status'], 200);
$H->fresh_jar();
$H->login('efb_f11_db', 'efb_f11_db-pass-123');
t('user B cannot open it (404)', $post('forms/draft/load', array('resume_token' => $ta), null, array('X-Forwarded-For' => '203.0.' . mt_rand(1, 250) . '.202'))['status'], 404);
$H->fresh_jar();
t('a guest cannot open it (404)', $post('forms/draft/load', array('resume_token' => $ta), null, array('X-Forwarded-For' => '203.0.' . mt_rand(1, 250) . '.203'))['status'], 404);
$H->fresh_jar();
$H->login('efb_f11_da', 'efb_f11_da-pass-123');
t('user A opens it', $post('forms/draft/load', array('resume_token' => $ta))['status'], 200);

// E-mail.
$H->fresh_jar();
$H->ctl('mails', 'clear');
$H->ctl('set-option', 'efb_e2e_mail_bodies', '');
$pp = $H->prepare($fid);
$e = $post('forms/draft/email', array('resume_token' => $tok, 'email' => 'me@example.test', 'url' => $H->base . '/f11/'), $pp);
$bodies = $H->ctl('option', 'efb_e2e_mail_bodies');
$last = is_array($bodies) && $bodies ? end($bodies) : array();
t('e-mail: sent to the address, subject "Continue your form: F11 drafts"', array($e['status'], !empty($e['json']['success']), ($last['to'] ?? '') === 'me@example.test' || (is_array($last['to'] ?? null) && in_array('me@example.test', $last['to'], true)), $last['subject'] ?? ''), array(200, true, true, 'Continue your form: F11 drafts'));
t('e-mail: holds the link, never an answer', array(strpos($last['message'] ?? '', 'efb_resume=' . $tok) !== false, strpos($last['message'] ?? '', 'Draft Person'), strpos($last['message'] ?? '', 'Shiraz')), array(true, false, false));
$post('forms/draft/email', array('resume_token' => $tok, 'email' => 'me@example.test'), $pp);
$post('forms/draft/email', array('resume_token' => $tok, 'email' => 'me@example.test'), $pp);
t('e-mail: 3 per draft per hour, then 429', $post('forms/draft/email', array('resume_token' => $tok, 'email' => 'me@example.test'), $pp)['status'], 429);
t('e-mail: an invalid address is refused', $post('forms/draft/email', array('resume_token' => $tok, 'email' => 'not-an-address'), $pp)['status'], 400);

// Submit completes the draft.
$H->fresh_jar();
$sp = $H->prepare($fid);
$s = $H->submit($fid, array(array('id_' => $F['email'], 'name' => 'Email', 'type' => 'email', 'value' => 'draft.person@example.test'), array('id_' => $F['name'], 'name' => 'Name', 'type' => 'text', 'value' => 'Draft Person')), array('draft_token' => $tok), $sp);
$after = array_values(array_filter((array) $H->ctl('f11-drafts', (string) $fid), function ($x) use ($d) { return $x['id'] === $d[0]['id']; }));
t('submit with draft_token: saved, the draft completed and emptied, linked to the entry', array(!empty(EfbF11Http::answer($s)['success']), $after[0]['status'] ?? '', $after[0]['content'] ?? 'x', (int) ($after[0]['completed_msg_id'] ?? 0) > 0), array(true, 'completed', '', true));
t('a completed draft no longer opens', $post('forms/draft/load', array('resume_token' => $tok))['status'], 404);

// Discard.
$H->fresh_jar();
$rd = $post('forms/draft/save', array('rows' => array_slice($rows, 0, 2), 'step' => 1, 'url' => $H->base . '/'));
$td = $rd['json']['resume_token'] ?? '';
$dc = $post('forms/draft/discard', array('resume_token' => $td));
t('discard: emptied, no longer opens', array(!empty($dc['json']['success']), $post('forms/draft/load', array('resume_token' => $td))['status']), array(true, 404));

// Refusals.
$H->fresh_jar();
$pp = $H->prepare($fid);
t('no runtime token → 409', $H->http('POST', '/wp-json/Emsfb/v1/forms/draft/save', array('form_id' => $fid, 'rows' => array()), array('X-WP-Nonce' => $pp['json']['nonce'], 'sid' => $pp['json']['sid']))['status'], 409);
$ch = curl_init($H->base . '/wp-json/Emsfb/v1/forms/draft/save');
curl_setopt_array($ch, array(CURLOPT_POST => true, CURLOPT_RETURNTRANSFER => true, CURLOPT_POSTFIELDS => json_encode(array('form_id' => $fid, 'runtime_token' => $pp['json']['runtime_token'], 'rows' => array())), CURLOPT_COOKIEFILE => $H->jar, CURLOPT_HTTPHEADER => array('Content-Type: application/json', 'Origin: https://evil.test', 'X-WP-Nonce: ' . $pp['json']['nonce'], 'sid: ' . $pp['json']['sid'])));
curl_exec($ch);
t('another origin → 403', (int) curl_getinfo($ch, CURLINFO_HTTP_CODE), 403);
curl_close($ch);
$G = $H->ctl('f11-form', 'F11 no drafts', '');
$H->fresh_jar();
$pg = $H->prepare($G['form_id']);
t('a form without draft_on → 403 efb_feature_unavailable', $H->http('POST', '/wp-json/Emsfb/v1/forms/draft/save', array('form_id' => $G['form_id'], 'runtime_token' => $pg['json']['runtime_token'], 'rows' => array()), array('X-WP-Nonce' => $pg['json']['nonce'], 'sid' => $pg['json']['sid']))['json']['code'] ?? '', 'efb_feature_unavailable');
$H->ctl('set-option', 'efb_e2e_f11_plan', 'del');
t('Free Plus (below Pro) → 403 efb_feature_unavailable, no button', array($post('forms/draft/save', array('rows' => array()))['json']['code'] ?? '', strpos($H->http('GET', $H->page($fid), null, array(), false)['body'], 'efb-draft-save') === false), array('efb_feature_unavailable', true));
$H->ctl('set-option', 'efb_e2e_f11_plan', '1');
$Fc = $H->ctl('f11-form', 'F11 drafts closing', 'draft_on=1&sched_close=2030-01-01T00:00');
$H->fresh_jar();
$pc = $H->prepare($Fc['form_id']);
$H->ctl('f11-set', (string) $Fc['form_id'], 'sched_close=2020-01-01T00:00');
t('a form that closed meanwhile → efb_form_closed', $H->http('POST', '/wp-json/Emsfb/v1/forms/draft/save', array('form_id' => $Fc['form_id'], 'runtime_token' => $pc['json']['runtime_token'], 'rows' => array()), array('X-WP-Nonce' => $pc['json']['nonce'], 'sid' => $pc['json']['sid']))['json']['code'] ?? '', 'efb_form_closed');

// Privacy: exporter / eraser and retention.
$H->fresh_jar();
$rp = $post('forms/draft/save', array('rows' => array_slice($rows, 0, 2), 'step' => 1, 'url' => $H->base . '/'));
$ex = $H->ctl('f11-drafts-op', 'export', 'draft.person@example.test');
t('WordPress exporter: the draft of the address (group efb-drafts)', array(count($ex['data'] ?? array()) >= 1, $ex['data'][0]['group_id'] ?? ''), array(true, 'efb-drafts'));
$er = $H->ctl('f11-drafts-op', 'erase', 'draft.person@example.test');
t('WordPress eraser: removed', array(!empty($er['items_removed']), count($H->ctl('f11-drafts-op', 'export', 'draft.person@example.test')['data'] ?? array())), array(true, 0));
$rp = $post('forms/draft/save', array('rows' => array_slice($rows, 0, 2), 'step' => 1, 'url' => $H->base . '/'));
$H->ctl('f11-drafts-op', 'expire', (string) $fid);
$H->ctl('f11-drafts-op', 'cleanup');
t('retention: expired drafts are deleted by the daily job', count((array) $H->ctl('f11-drafts', (string) $fid)), 0);
$H->ctl('set-option', 'efb_e2e_f11_plan', 'del');

echo "\nRESULTS: {$passed} passed, {$failed} failed\n";
exit($failed ? 1 : 0);
