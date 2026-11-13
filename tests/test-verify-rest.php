<?php
/**
 * F02-D: POST Emsfb/v1/verify/send and verify/check (spec §5.2) on the SQLite stand-in with the real
 * counters (Rate_Limit), a fake SMS provider and a recording mailer. Every error code of §5.2, the
 * order of the gates (Human Shield refuses before anything is sent or counted against the visitor),
 * the security checks of §8: brute force, replay, a session that is not the challenge's, the
 * failure count that a resend does not reset, the same answer for every destination (no enumeration),
 * no code in any trace.
 *
 * Run: php tests/test-verify-rest.php
 */
require __DIR__ . '/verify-test-lib.php';
use Emsfb\Verification as V;

/* ---------------------------------------------------------------- shape, runtime, field */
vt_env();
$fid = vt_form(array(vt_mobile('mob1'), vt_email('eml1', 'otp'), vt_email('ret1', 'retype'), array('id_' => 'txt1', 'type' => 'text', 'name' => 'Name')));
vt_provider();
list($st, $d) = vt_answer(V::rest_send(vt_req(array('form_id' => $fid, 'field_id' => 'mob1', 'destination' => '+989121234567'))));
vt('no runtime token (a cached page\'s shared sid) → 409 efb_context_expired', array($st, $d['code']), array(409, 'efb_context_expired'));
list($st, $d) = vt_send($fid, 'mob1', '+989121234567', 'sid-a', array('runtime_token' => vt_token($fid + 1)));
vt('a runtime token of another form → 409', $st, 409);
$GLOBALS['vt_dead_sid'] = 1;
list($st, $d) = vt_send($fid, 'mob1', '+989121234567');
vt('a session that no longer exists → 409', array($st, $d['code']), array(409, 'efb_context_expired'));
unset($GLOBALS['vt_dead_sid']);
$GLOBALS['vt_cross_origin'] = 1;
list($st, $d) = vt_send($fid, 'mob1', '+989121234567');
vt('another origin → 403 efb_origin_denied', array($st, $d['code']), array(403, 'efb_origin_denied'));
unset($GLOBALS['vt_cross_origin']);
list($st, $d) = vt_answer(V::rest_send(vt_req(array('form_id' => $fid, 'field_id' => array('x'), 'runtime_token' => vt_token($fid)))));
vt('a non-scalar field id → 400 efb_verify_invalid', array($st, $d['code']), array(400, 'efb_verify_invalid'));
list($st, $d) = vt_send($fid, 'nope', '+989121234567');
vt('an unknown field → 400 efb_verify_invalid', array($st, $d['code']), array(400, 'efb_verify_invalid'));
list($st, $d) = vt_send($fid, 'txt1', '+989121234567');
vt('a field without a code → 400 efb_verify_disabled', array($st, $d['code']), array(400, 'efb_verify_disabled'));
list($st, $d) = vt_send($fid, 'ret1', 'a@example.com');
vt('a "type it twice" field sends no code → 400 efb_verify_disabled', array($st, $d['code']), array(400, 'efb_verify_disabled'));
list($st, $d) = vt_send($fid, 'mob1', 'a@example.com', 'sid-a', array('channel' => 'email'));
vt('the client names another channel than the stored field → 400 (the server trusts the field only)', array($st, $d['code']), array(400, 'efb_verify_invalid'));
list($st, $d) = vt_send($fid, 'mob1', '09121234567');
vt('a number that is not E.164 → 422 efb_verify_destination', array($st, $d['code']), array(422, 'efb_verify_destination'));
list($st, $d) = vt_send($fid, 'mob1', '+491701234567');
vt('a country outside the list → 422 efb_verify_country', array($st, $d['code']), array(422, 'efb_verify_country'));
list($st, $d) = vt_send($fid, 'mob1', '+18765550123');
vt('a Caribbean +1 number → 422 efb_verify_country', array($st, $d['code']), array(422, 'efb_verify_country'));
vt('nothing was sent for the refusals above', count($GLOBALS['vt_sms']), 0);
$fid_reg = vt_form(array(vt_mobile('mob1')), 'register');
list($st, $d) = vt_send($fid_reg, 'mob1', '+989121234567');
vt('a registration form → 400 efb_verify_disabled (v1)', array($st, $d['code']), array(400, 'efb_verify_disabled'));
$fid_priv = vt_form(array(vt_mobile('mob1')));
$GLOBALS['vt_forms'][$fid_priv][0]['stateForm'] = 1;
list($st, $d) = vt_send($fid_priv, 'mob1', '+989121234567');
vt('a members-only form for a guest → 403 efb_login_required', array($st, $d['code']), array(403, 'efb_login_required'));

/* ---------------------------------------------------------------- unavailable */
vt_env();
$fid = vt_form(array(vt_mobile('mob1'), vt_email('eml1', 'otp')));
list($st, $d) = vt_send($fid, 'mob1', '+989121234567');
vt('no SMS provider (SMS add-on off) → 503 efb_verify_unavailable', array($st, $d['code']), array(503, 'efb_verify_unavailable'));
vt('… and the visitor reads a general sentence, no provider name', $d['m'], V::TEXTS['vrfUnavailable']);
$GLOBALS['vt_email_off'] = 1;
list($st, $d) = vt_send($fid, 'eml1', 'a@example.com');
vt('e-mail sending off → 503', array($st, $d['code']), array(503, 'efb_verify_unavailable'));
unset($GLOBALS['vt_email_off']);
$GLOBALS['vt_plan'] = 'free';
list($st, $d) = vt_send($fid, 'eml1', 'a@example.com');
vt('Free plan: e-mail codes are Free Plus → 503', array($st, $d['code']), array(503, 'efb_verify_unavailable'));
$GLOBALS['vt_plan'] = 'free_plus';
vt_provider();
list($st, $d) = vt_send($fid, 'mob1', '+989121234567');
vt('Free Plus: SMS codes are Pro → 503', array($st, $d['code']), array(503, 'efb_verify_unavailable'));
$GLOBALS['vt_plan'] = 'pro';
V::save_settings(array('sms' => array('provider' => 'fake', 'allowed_countries' => array())));
list($st, $d) = vt_send($fid, 'mob1', '+989121234567');
vt('an empty country list = no SMS codes (fail-closed) → 503', array($st, $d['code']), array(503, 'efb_verify_unavailable'));
vt_env();
$GLOBALS['wpdb']->query('DROP TABLE wp_emsfb_vrfy_');
V::reset_ready();
$fid = vt_form(array(vt_email('eml1', 'otp')));
list($st, $d) = vt_send($fid, 'eml1', 'a@example.com');
vt('the table is missing → 503 (fail-closed)', array($st, $d['code']), array(503, 'efb_verify_unavailable'));

/* ---------------------------------------------------------------- happy path sms */
vt_env();
$fid = vt_form(array(vt_mobile('mob1')));
vt_provider();
list($st, $d) = vt_send($fid, 'mob1', '+98 ۹۱۲ 123 4567', 'sid-a', array('lang' => 'fa_IR'));
vt('send: 200 with challenge, channel, mask, code length, lifetime, resend wait, attempts', array($st, $d['success'], strlen($d['challenge_id']), $d['channel'], $d['masked'], $d['code_length'], $d['expires_in'], $d['resend_after'], $d['attempts_left']), array(200, true, 32, 'sms', '+98 912 ***-4567', 6, 300, 60, 5));
$sms = end($GLOBALS['vt_sms']);
vt('the provider got E.164, the code and a text with the WebOTP line', array($sms['to'], strlen($sms['code']), strpos($sms['text'], '@site.example #' . $sms['code']) !== false), array('+989121234567', 6, true));
$row = $GLOBALS['wpdb']->row('wp_emsfb_vrfy_', "challenge_id = '{$d['challenge_id']}'");
vt('stored: an HMAC of the code, a hash and a mask of the number, no raw number', array(strlen($row['code_hash']), $row['code_hash'] !== $sms['code'], strpos(json_encode($row), '9121234567'), $row['dest_mask'], $row['status']), array(64, true, false, '+98 912 ***-4567', 'pending'));
vt('the IP is stored only as an HMAC', $row['ip_hash'] === V::ip_hash('203.0.113.7') && strpos($row['ip_hash'], '203.0') === false, true);
$traces = json_encode($GLOBALS['vt_trace']);
vt('no trace holds the code or the full number', array(strpos($traces, $sms['code']), strpos($traces, '9121234567')), array(false, false));
$cid = $d['challenge_id'];
list($st, $e) = vt_check($fid, 'mob1', $cid, '000000' === $sms['code'] ? '111111' : '000000');
vt('check: a wrong code → 422 efb_verify_wrong, 4 attempts left', array($st, $e['code'], $e['data']['attempts_left']), array(422, 'efb_verify_wrong', 4));
list($st, $e) = vt_check($fid, 'mob1', $cid, '12');
vt('check: a code of the wrong length → 400, no attempt used', array($st, (int) $GLOBALS['wpdb']->get_var("SELECT attempts FROM wp_emsfb_vrfy_ WHERE challenge_id = '$cid'")), array(400, 1));
list($st, $e) = vt_check($fid, 'mob1', $cid, $sms['code'], 'sid-b');
vt('check from another session → 404 efb_verify_expired (the challenge is not revealed)', array($st, $e['code']), array(404, 'efb_verify_expired'));
list($st, $e) = vt_check($fid, 'mob1', $cid, V::ascii_digits($sms['code']) === $sms['code'] ? strtr($sms['code'], array('0' => '۰', '1' => '۱', '2' => '۲', '3' => '۳', '4' => '۴', '5' => '۵', '6' => '۶', '7' => '۷', '8' => '۸', '9' => '۹')) : $sms['code']);
vt('check: the right code typed in Persian digits → 200 with a token', array($st, $e['success'], V::token_verify($e['token']) !== false, $e['expires_in']), array(200, true, true, 1800));
$p = V::token_verify($e['token']);
vt('the token is bound to form, field, session and the number\'s hash', array($p['fid'], $p['fld'], $p['sid'], $p['dh']), array($fid, 'mob1', 'sid-a', V::dest_hash('sms', '+989121234567')));
list($st, $e) = vt_check($fid, 'mob1', $cid, $sms['code']);
vt('replay: the same code again → 410 efb_verify_expired', array($st, $e['code']), array(410, 'efb_verify_expired'));

/* ---------------------------------------------------------------- brute force, lock */
vt_env();
$fid = vt_form(array(vt_mobile('mob1')));
vt_provider();
list($st, $d) = vt_send($fid, 'mob1', '+989121234567');
$code = end($GLOBALS['vt_sms'])['code'];
$wrong = $code === '999999' ? '888888' : '999999';
$res = array();
for ($i = 0; $i < 5; $i++) { list($s1, $e1) = vt_check($fid, 'mob1', $d['challenge_id'], $wrong); $res[] = $s1; }
vt('brute force: 4 × 422 then the 5th wrong code locks (429)', $res, array(422, 422, 422, 422, 429));
list($st, $e) = vt_check($fid, 'mob1', $d['challenge_id'], $code);
vt('after the lock even the right code is refused (429 efb_verify_locked)', array($st, $e['code']), array(429, 'efb_verify_locked'));
vt('the challenge is locked in the database', $GLOBALS['wpdb']->get_var("SELECT status FROM wp_emsfb_vrfy_ WHERE challenge_id = '{$d['challenge_id']}'"), 'locked');

/* failures per destination survive a resend (NIST: a new code does not reset the count) */
vt_env();
$fid = vt_form(array(vt_mobile('mob1')));
vt_provider();
V::save_settings(array('sms' => array('provider' => 'fake', 'allowed_countries' => array('ir'), 'max_sends_dest_hour' => 20, 'max_sends_dest_day' => 50), 'limits' => array('fail_dest_day' => 4, 'sid_sends' => 50)));
$st_all = array();
for ($round = 0; $round < 3; $round++) {
	list($st, $d) = vt_send($fid, 'mob1', '+989121234567');
	$st_all[] = $st;
	if ($st !== 200) break;
	vt_check($fid, 'mob1', $d['challenge_id'], '000001');
	vt_check($fid, 'mob1', $d['challenge_id'], '000002');
	V::$now += 700; // past every cooldown
}
vt('4 wrong codes over two codes lock the number: the third send is refused (429 locked)', $st_all, array(200, 200, 429));

/* ---------------------------------------------------------------- cooldown */
vt_env();
$fid = vt_form(array(vt_mobile('mob1')));
vt_provider();
list($st, $d) = vt_send($fid, 'mob1', '+989121234567');
list($st2, $d2) = vt_send($fid, 'mob1', '+989121234567');
vt('a resend before 60 s → 429 efb_verify_cooldown with retry_after', array($st2, $d2['code'], $d2['data']['retry_after']), array(429, 'efb_verify_cooldown', 60));
V::$now += 61;
list($st3, $d3) = vt_send($fid, 'mob1', '+989121234567');
vt('after 60 s: sent, the next wait is 120 s', array($st3, $d3['resend_after']), array(200, 120));
vt('the earlier pending code is superseded', $GLOBALS['wpdb']->get_var("SELECT status FROM wp_emsfb_vrfy_ WHERE challenge_id = '{$d['challenge_id']}'"), 'superseded');
list($st, $e) = vt_check($fid, 'mob1', $d['challenge_id'], $GLOBALS['vt_sms'][0]['code']);
vt('the superseded code no longer verifies (410)', $st, 410);
V::$now += 60;
list($st4, $d4) = vt_send($fid, 'mob1', '+989121234567');
vt('the 3rd send needs 120 s (only 60 passed) → 429', $st4, 429);

/* ---------------------------------------------------------------- Human Shield first */
vt_env();
$fid = vt_form(array(vt_email('eml1', 'otp')));
add_filter('efb_submit_bot_decision', function ($block, $ctx) { $GLOBALS['vt_bot_ctx'] = $ctx; return true; });
list($st, $d) = vt_send($fid, 'eml1', 'victim@example.com');
vt('Human Shield says bot → 403 efb_verify_blocked', array($st, $d['code']), array(403, 'efb_verify_blocked'));
vt('… with context verify_send', $GLOBALS['vt_bot_ctx']['context'], 'verify_send');
vt('… nothing sent, no challenge, no counter taken', array(count($GLOBALS['vt_mails']), $GLOBALS['wpdb']->count('wp_emsfb_vrfy_'), $GLOBALS['wpdb']->count('wp_emsfb_vrfy_rl_')), array(0, 0, 0));
$GLOBALS['efb_filters'] = array();
add_filter('efb_shield_allow_side_effect', function ($ok, $ctx) { $GLOBALS['vt_side_ctx'] = $ctx; return false; });
list($st, $d) = vt_send($fid, 'eml1', 'victim@example.com');
vt('the side-effect gate refuses → 403, channel otp_email, masked recipient only', array($st, $GLOBALS['vt_side_ctx']['channel'], $GLOBALS['vt_side_ctx']['recipients']), array(403, 'otp_email', array('vi***@example.com')));
$GLOBALS['efb_filters'] = array();

/* ---------------------------------------------------------------- e-mail, no enumeration */
vt_env();
$fid = vt_form(array(vt_email('eml1', 'otp')));
list($st, $a) = vt_send($fid, 'eml1', 'first@example.com', 'sid-1');
list($st2, $b) = vt_send($fid, 'eml1', 'second@example.org', 'sid-2');
unset($a['challenge_id'], $a['masked'], $b['challenge_id'], $b['masked']);
vt('two destinations get the same answer (no enumeration)', $a, $b);
vt('e-mail lifetime 600 s', $a['expires_in'], 600);
V::save_settings(array('email' => array('blocked_domains' => 'mailinator.com')));
list($st, $d) = vt_send($fid, 'eml1', 'x@sub.mailinator.com', 'sid-3');
vt('a blocked domain (and its sub-domains) → 422 efb_verify_destination', array($st, $d['code']), array(422, 'efb_verify_destination'));
add_filter('efb_verify_email_allowed', function ($ok, $email) { return strpos($email, 'temp') === false; });
list($st, $d) = vt_send($fid, 'eml1', 'temp@example.net', 'sid-4');
vt('efb_verify_email_allowed refuses → 422', $st, 422);
$GLOBALS['efb_filters'] = array();
$GLOBALS['vt_mail_result'] = 'n';
list($st, $d) = vt_send($fid, 'eml1', 'fail@example.net', 'sid-5');
vt('wp_mail said no ("n") → 502 efb_verify_provider, the challenge is "failed"', array($st, $d['code'], $GLOBALS['wpdb']->get_var("SELECT status FROM wp_emsfb_vrfy_ ORDER BY id DESC LIMIT 1")), array(502, 'efb_verify_provider', 'failed'));
unset($GLOBALS['vt_mail_result']);
V::save_settings(array('email' => array('max_sends_dest_hour' => 1)));
V::$now += 61;
list($st, $d) = vt_send($fid, 'eml1', 'fail@example.net', 'sid-5');
vt('a provider failure is still counted (a cut-off provider is no way around the cap): next send → 429 efb_verify_limit', array($st, $d['code']), array(429, 'efb_verify_limit'));

/* ---------------------------------------------------------------- the sender is not ready (WP SMS missing) */
vt_env();
$fid = vt_form(array(vt_mobile('mob1')));
$prov = vt_provider();
$prov->ready = false;
list($st, $d) = vt_send($fid, 'mob1', '+989121234567');
vt('WP SMS not active (the bridge is registered but cannot send) → 503 efb_verify_unavailable, nothing sent', array($st, $d['code'], count($GLOBALS['vt_sms'])), array(503, 'efb_verify_unavailable', 0));
vt('… the reason for the owner is sender_not_ready', V::channel_state('sms'), array('ready' => false, 'reason' => 'sender_not_ready'));
$prov->ready = true;
$GLOBALS['vt_sms_fail'] = 1;
$GLOBALS['efb_mails'] = array();
list($st, $d) = vt_send($fid, 'mob1', '+989121234567');
vt('the sender fails → 502 with the retry sentence only (no provider error to the visitor)', array($st, $d['code'], $d['m']), array(502, 'efb_verify_provider', V::TEXTS['vrfFailed']));
vt('… and the owner is told once', count(array_filter($GLOBALS['efb_mails'], function ($m) { return strpos($m['body'], 'WP SMS') !== false; })), 1);
unset($GLOBALS['vt_sms_fail']);

/* ---------------------------------------------------------------- routes and hooks */
vt_env();
V::routes();
vt('routes registered under Emsfb/v1 with the nonce permission', array(isset($GLOBALS['efb_routes']['Emsfb/v1/verify/send']), isset($GLOBALS['efb_routes']['Emsfb/v1/verify/check']), $GLOBALS['efb_routes']['Emsfb/v1/verify/send']['methods']), array(true, true, 'POST'));
$fid = vt_form(array(vt_email('eml1', 'otp')));
$GLOBALS['efb_actions_log'] = array();
list($st, $d) = vt_send($fid, 'eml1', 'hook@example.com');
$sent = array_values(array_filter($GLOBALS['efb_actions_log'], function ($a) { return $a[0] === 'efb_verify_sent'; }));
vt('efb_verify_sent fires with the mask, never the code or address', array(count($sent), $sent[0][1][0]['dest_mask'], strpos(json_encode($sent), 'hook@example.com')), array(1, 'ho***@example.com', false));

/* ---------------------------------------------------------------- the envelope of the routes */
$env = V::envelope(new WP_Error('efb_verify_wrong', 'That code is not correct. 4 attempts left.', array('status' => 422, 'attempts_left' => 4)));
vt('a refusal is HTTP 200 with success:false, code, message, status and attempts_left (no console error for a wrong code)', array($env->get_status(), $env->get_data()), array(200, array('success' => false, 'code' => 'efb_verify_wrong', 'message' => 'That code is not correct. 4 attempts left.', 'status' => 422, 'attempts_left' => 4)));
$env = V::envelope(new WP_Error('efb_verify_limit', 'x', array('status' => 429, 'retry_after' => 90)));
vt('… with retry_after for a limit', array($env->get_data()['status'], $env->get_data()['retry_after']), array(429, 90));
$ok = new WP_REST_Response(array('success' => true), 200);
vt('a success passes unchanged', V::envelope($ok), $ok);
vt('the routes use the envelope', array($GLOBALS['efb_routes']['Emsfb/v1/verify/send']['callback'][1], $GLOBALS['efb_routes']['Emsfb/v1/verify/check']['callback'][1]), array('route_send', 'route_check'));

vt_done();
