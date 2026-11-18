<?php
/**
 * F20-core end to end on the real WordPress (tests/e2e-wp, EFB_E2E_SITE_DIR), through a mock of the portal add-on
 * (tests/e2e-wp/mu-plugins/efb-e2e-f20.php, never shipped) that calls every contract function over its own REST route
 * with its own session cookie and CSRF header — what AdnPRT will do:
 *
 *  - inert: with the add-on off every function answers efb_portal_unavailable and capabilities say ready false (addon);
 *  - P4 efb_entry_stored once per stored entry, with the rows; P5 the owner index of submitted entries (the
 *    identifying field, case and Persian digits, a mobile number, not an address inside a message);
 *  - P7 sign-in by e-mail code (the same answer for an address without entries and no e-mail; the code e-mail without
 *    a link; Persian digits; a wrong code, a reused code, an expired code, a form's challenge refused) and by SMS code
 *    through the WP SMS stand-in (the bridge; without WP SMS: unavailable);
 *  - P9 the list (only this identity's active entries of forms in the portal, filters, search) and one entry (no
 *    hidden F08 value, no password; another owner's entry, spam and a form off the portal: not found);
 *  - P10 / P11 the thread, the owner's reply (read_ 3, history "owner"), CSRF, the reply limit, close and reopen,
 *    a closed ticket past its window; P12 payments without the gateway reference or payer address, the receipt flag,
 *    the legacy row; P13 a private file as a 10-minute signed link, a public one as is;
 *  - P14 cancel and reschedule with the Booking add-on absent (addon_required) and on (places given back once, a
 *    taken date refused with nothing changed, the old date released, the window, the count); P15 the owner's edit
 *    (window, after a support reply, a full choice, prices locked, history); P16 drafts and a new link (the old one
 *    stops); §4.10 privacy requests.
 *
 * Run (the e2e site, under the e2e lock): php tests/test-portal-core-http.php
 */
require __DIR__ . '/f12b-http-lib.php';
$H = new EfbF12bHttp();
if ($why = $H->unavailable()) { echo "[SKIP] {$why}\n"; echo "\nRESULTS: 0 passed, 0 failed\n"; exit(0); }

$c20 = function (...$a) use ($H) {
	$parts = array_merge(preg_split('/\s+/', trim($H->wpcli)), array('eval-file', $H->root . '/tests/e2e-wp/ctl-f20.php'), array_map('strval', $a), array('--path=' . $H->site, '--allow-root'));
	$lines = array_values(array_filter(explode("\n", (string) shell_exec(implode(' ', array_map('escapeshellarg', $parts)) . ' 2>/dev/null')), 'strlen'));
	return json_decode($lines ? end($lines) : '', true);
};
$fa = function ($s) { return strtr((string) $s, array('0' => '۰', '1' => '۱', '2' => '۲', '3' => '۳', '4' => '۴', '5' => '۵', '6' => '۶', '7' => '۷', '8' => '۸', '9' => '۹')); };
$run = substr(md5((string) microtime(true)), 0, 6);
$mail_a = "ana.{$run}@example.test"; $mail_b = "bob.{$run}@example.test"; $mobile = '+4915112' . substr((string) mt_rand(100000, 999999), 0, 5);

/* A portal client: its own cookie jar, the session's CSRF header. */
class EfbPortalClient {
	public $jar; public $csrf = ''; private $H;
	public function __construct($H) { $this->H = $H; $this->jar = sys_get_temp_dir() . '/efb-prt-' . getmypid() . '-' . mt_rand(1000, 99999); }
	public function op($op, array $body = array(), $csrf = true, array $headers = array()) {
		if ($csrf && $this->csrf !== '') $headers['X-EFB-Portal-CSRF'] = $this->csrf;
		$r = $this->H->http('POST', '/wp-json/Emsfb/v1/e2e-portal/' . $op, $body ?: new stdClass(), $headers, true, $this->jar);
		$j = is_array($r['json']) ? $r['json'] : array('raw' => substr($r['body'], 0, 300));
		$j['_status'] = $r['status']; $j['_headers'] = $r['headers'];
		return $j;
	}
	public function __destruct() { @unlink($this->jar); }
}
$mails = function () use ($H) { $l = $H->ctl('option', 'efb_e2e_mail_bodies'); return is_array($l) ? array_values($l) : array(); };
$code_of = function ($m) { return $m && preg_match('/letter-spacing:6px[^>]*>(\d{4,10})</', $m['message'], $x) ? $x[1] : ''; };
$sms = function () use ($H) { $l = $H->ctl('f02-sms'); return is_array($l) ? array_values($l) : array(); };
$signin = function (EfbPortalClient $P, $dest, $channel = 'email') use ($mails, $code_of, $sms, $H) {
	$H->ctl('set-option', 'efb_e2e_mail_bodies', 'del');
	$s = $P->op('start', array('channel' => $channel, 'dest' => $dest));
	if (empty($s['success'])) return array('start' => $s);
	if ($channel === 'email') { $m = $mails(); $code = $code_of(end($m)); } else { $l = $sms(); $code = preg_match('/(\d{6})/', (string) (end($l)['msg'] ?? ''), $x) ? $x[1] : ''; }
	$v = $P->op('verify', array('cid' => $s['data']['challenge_id'], 'code' => $code));
	if (!empty($v['success'])) $P->csrf = $v['data']['csrf'];
	return array('start' => $s, 'verify' => $v, 'code' => $code);
};

try {
	echo "--- setup ---\n";
	$smtp0 = $H->ctl('setting', 'smtp');
	$H->ctl('plan', '3');
	$H->ctl('f11-mu'); $H->ctl('f02-mu'); $H->ctl('f06-mu');
	$c20('mu');
	$H->ctl('set-option', 'efb_e2e_capture_mail', '1');
	$H->ctl('set-option', 'efb_e2e_f11_plan', '1');
	$H->ctl('f12-setting', 'smtp=1&secretKey=del&siteKey=del');
	$c20('on', '0');
	$c20('reset');

	echo "--- inert without the add-on ---\n";
	$x = $c20('call', 'efb_portal_entries', json_encode(array(array(array('k' => 'e', 'h' => str_repeat('a', 64))))));
	$api = $c20('call', 'efb_portal_core_api', '[]');
	t('add-on off: efb_portal_entries answers efb_portal_unavailable (503); the API says version 1, not active', array($x['error'] ?? null, $x['data']['status'] ?? null, $api['version'] ?? null, $api['active'] ?? null), array('efb_portal_unavailable', 503, 1, false));
	$r = $H->http('POST', '/wp-json/Emsfb/v1/e2e-portal/api', new stdClass());
	t('… and no portal route exists (core adds none)', $r['status'], 404);

	echo "--- P4: efb_entry_stored ---\n";
	$H->ctl('set-option', 'efb_e2e_f20_stored', '[]');
	$c20('stored', 'clear');
	$F = $H->ctl('f03-form', 'Portal tickets ' . $run, json_encode(array(
		array('key' => 'nm', 'type' => 'text', 'name' => 'Name'),
		array('key' => 'em', 'type' => 'email', 'name' => 'Email'),
		array('key' => 'em2', 'type' => 'email', 'name' => 'Colleague email'),
		array('key' => 'mo', 'type' => 'mobile', 'name' => 'Mobile'),
		array('key' => 'msg', 'type' => 'textarea', 'name' => 'Message'),
		array('key' => 'hid', 'type' => 'hiddenField', 'name' => 'Internal', 'value' => 'agent-42'),
		array('key' => 'pw', 'type' => 'password', 'name' => 'Pin'),
	)), 'prv_id_field=@@em@@', '1');
	$fid = (int) $F['form_id']; $i = $F['ids'];
	$c20('on', '1');
	$c20('index', 'restart');
	$sub = function ($name, $email, $extra = array()) use ($H, $fid, $i, $mobile) {
		$rows = array_merge(array(
			array('id_' => $i['nm'], 'name' => 'Name', 'type' => 'text', 'value' => $name),
			array('id_' => $i['em'], 'name' => 'Email', 'type' => 'email', 'value' => $email),
		), $extra);
		return EfbF11Http::answer($H->submit($fid, $rows));
	};
	$a1 = $sub('Ana', strtoupper($mail_a), array(array('id_' => $i['msg'], 'name' => 'Message', 'type' => 'textarea', 'value' => "Please also write to {$mail_b}"), array('id_' => $i['pw'], 'name' => 'Pin', 'type' => 'password', 'value' => 'pw-SECRET-9')));
	$a2 = $sub('Ana again', $mail_a, array(array('id_' => $i['mo'], 'name' => 'Mobile', 'type' => 'mobile', 'value' => $mobile)));
	$b1 = $sub('Bob', $mail_b, array(array('id_' => $i['em2'], 'name' => 'Colleague email', 'type' => 'email', 'value' => $mail_a)));
	$st = $c20('stored');
	$E = $H->ctl('f14-entries', $fid);
	$byname = array(); foreach ((array) $E as $e) foreach ((array) $e['content'] as $row) if (is_array($row) && ($row['id_'] ?? '') === $i['nm']) $byname[$row['value']] = $e;
	$A1 = $byname['Ana']; $A2 = $byname['Ana again']; $B1 = $byname['Bob'];
	t('three submissions stored', array(!empty($a1['success']), !empty($a2['success']), !empty($b1['success']), count($E)), array(true, true, true, 3));
	t('P4: one efb_entry_stored per stored entry, with its form, tracking code and rows', array(count($st), $st[0][0] ?? 0, $st[0][1] ?? 0, $st[0][2] ?? '', ($st[0][3] ?? 0) >= 2), array(3, (int) $A1['msg_id'], $fid, $A1['track'], true));

	echo "--- P5: the owner index ---\n";
	$o1 = $c20('owners', $A1['msg_id']); $o2 = $c20('owners', $A2['msg_id']); $ob = $c20('owners', $B1['msg_id']);
	t('Ana\'s first entry: one e-mail owner (the identifying field, in capitals); the address inside the message is not an owner', array(count($o1), $o1[0]['kind'] ?? ''), array(1, 'e'));
	t('… the same hash as her second entry (lower case), which also has a mobile owner', array($o1[0]['h'] ?? 1, array_column($o2, 'kind')), array($o2[0]['h'] ?? 2, array('e', 'p')));
	t('Bob\'s entry: only his identifying field — Ana\'s address in another e-mail field is not an owner', array(count($ob), ($ob[0]['h'] ?? '') !== ($o1[0]['h'] ?? '')), array(1, true));

	echo "--- P7: sign-in by e-mail code ---\n";
	$c20('policy', $fid, json_encode(array('on' => true, 'reply' => true, 'attach' => true, 'close' => true, 'reopen_days' => 14, 'edit' => true, 'edit_hours' => 24)));
	$P = new EfbPortalClient($H);
	$H->ctl('set-option', 'efb_e2e_mail_bodies', 'del');
	$none = $P->op('start', array('channel' => 'email', 'dest' => "nobody.{$run}@example.test"));
	$after_none = $mails();
	$c20('reset');
	$ok = $P->op('start', array('channel' => 'email', 'dest' => $fa(strtoupper($mail_a))));
	$m = $mails(); $m = end($m);
	t('an address without entries gets the same answer and no e-mail', array(!empty($none['success']), array_keys($none['data'] ?? array()) === array_keys($ok['data'] ?? array()), count($after_none), strpos($none['data']['message'] ?? '', 'If we find submissions for') === 0), array(true, true, 0, true));
	t('an address with entries (typed in capitals) gets one e-mail: "Your sign-in code for …", the code in a left-to-right block, no link', array(!empty($ok['success']), (bool) $m, strpos((string) $m['subject'], 'Your sign-in code for') !== false, $code_of($m) !== '', (bool) preg_match('#href=["\']https?://[^"\']*(track|efb)#i', (string) $m['message'])), array(true, true, true, true, false));
	t('… the answer names the address masked, never in full', array(strpos(json_encode($ok), $mail_a), (bool) preg_match('/\*\*\*/', $ok['data']['masked'] ?? '')), array(false, true));
	$code = $code_of($m);
	$wrong = $P->op('verify', array('cid' => $ok['data']['challenge_id'], 'code' => $code === '111111' ? '222222' : '111111'));
	t('a wrong code: efb_verify_wrong with attempts left (status 422 inside the answer)', array($wrong['code'] ?? '', $wrong['status'] ?? 0, ($wrong['attempts_left'] ?? 0) > 0), array('efb_verify_wrong', 422, true));
	$v = $P->op('verify', array('cid' => $ok['data']['challenge_id'], 'code' => 'Your code: ' . $fa($code)));
	t('the right code in Persian digits inside pasted text: signed in (the session cookie is HttpOnly)', array(!empty($v['success']), (bool) preg_match('/efb_e2e_prt=[a-f0-9]{64}.*HttpOnly/i', $v['_headers'])), array(true, true));
	$P->csrf = $v['data']['csrf'] ?? '';
	$Q = new EfbPortalClient($H);
	$again = $Q->op('verify', array('cid' => $ok['data']['challenge_id'], 'code' => $code));
	t('the same code again (another browser): refused (efb_verify_expired)', $again['code'] ?? '', 'efb_verify_expired');
	$c20('reset');
	$s2 = $Q->op('start', array('channel' => 'email', 'dest' => $mail_b));
	$m2 = $mails(); $m2 = end($m2);
	$R = new EfbPortalClient($H);
	$other = $R->op('verify', array('cid' => $s2['data']['challenge_id'], 'code' => $code_of($m2)));
	t('a code checked from another pre-session (another browser) is not found', $other['code'] ?? '', 'efb_verify_expired');
	$c20('reset');
	$Rc = new EfbPortalClient($H);
	$s3 = $Rc->op('start', array('channel' => 'email', 'dest' => $mail_b));
	$ch = $c20('chal', $s3['data']['challenge_id']);
	t('a sign-in challenge is fid 0, field "portal", bound to the pre-session; a form\'s lookup of the same id finds nothing', array($ch['row']['fid'] ?? -1, $ch['row']['field_id'] ?? '', ($ch['row']['sid'] ?? 0) >= 16, $ch['form_lookup']), array(0, 'portal', true, false));
	$m3 = $mails(); $m3 = end($m3);
	$c20('expire', $s3['data']['challenge_id']);
	$ex = $Rc->op('verify', array('cid' => $s3['data']['challenge_id'], 'code' => $code_of($m3)));
	t('an expired code: efb_verify_expired (410)', array($ex['code'] ?? '', $ex['status'] ?? 0), array('efb_verify_expired', 410));

	echo "--- P7: sign-in by SMS code (the WP SMS bridge) ---\n";
	$H->ctl('addon-flag', 'AdnSS', '1');
	$H->ctl('f02-settings', json_encode(array('sms' => array('allowed_countries' => array('DE', 'IR')))));
	$H->ctl('f02-wpsms', '0');
	$c20('reset');
	$S = new EfbPortalClient($H);
	$no = $S->op('start', array('channel' => 'sms', 'dest' => $mobile));
	t('without WP SMS: efb_verify_unavailable (core never calls an SMS service itself)', $no['code'] ?? '', 'efb_verify_unavailable');
	$H->ctl('f02-wpsms', '1', 'ok');
	$H->ctl('f02-sms', 'clear');
	$si = $signin($S, $fa($mobile), 'sms');
	$last = $sms(); $last = end($last);
	t('with the WP SMS stand-in: the code goes through wp_sms_send ("… is your sign-in code for …"), the number in Persian digits accepted, signed in', array(!empty($si['start']['success']), strpos((string) ($last['msg'] ?? ''), 'is your sign-in code for') !== false, !empty($si['verify']['success'])), array(true, true, true));
	$H->ctl('f02-wpsms', '0');

	echo "--- P9: my entries, one entry ---\n";
	$l = $P->op('entries');
	$ids = array_map(function ($r) { return $r['msg_id']; }, $l['data']['rows'] ?? array());
	t('the e-mail identity lists Ana\'s two entries, newest first; not Bob\'s', $ids, array((int) $A2['msg_id'], (int) $A1['msg_id']));
	$ls = $S->op('entries');
	t('the mobile identity lists only the entry with that number', array_map(function ($r) { return $r['msg_id']; }, $ls['data']['rows'] ?? array()), array((int) $A2['msg_id']));
	$row = $l['data']['rows'][1] ?? array();
	t('a row: form, tracking code, date as text, status received, no answers in the list', array($row['form'] ?? '', $row['track'] ?? '', ($row['date_text'] ?? '') !== '', $row['status'] ?? '', isset($row['answers'])), array('Portal tickets ' . $run, $A1['track'], true, 'awaiting_support', false));
	$q = $P->op('entries', array('q' => array('search' => $A1['track'])));
	t('search by tracking code (on the server, within these entries)', array_map(function ($r) { return $r['msg_id']; }, $q['data']['rows'] ?? array()), array((int) $A1['msg_id']));
	$q = $P->op('entries', array('q' => array('search' => $B1['track'])));
	t('… another owner\'s tracking code finds nothing', $q['data']['total'] ?? -1, 0);
	$en = $P->op('entry', array('id' => $A1['msg_id']));
	$labels = array_column($en['data']['answers'] ?? array(), 'label');
	$raw = json_encode($en);
	t('one entry: the answers as text with their labels; no hidden F08 value, no password, no IP', array(in_array('Name', $labels, true), in_array('Email', $labels, true), strpos($raw, 'agent-42'), strpos($raw, 'pw-SECRET-9'), strpos($raw, '203.0.113'), in_array('Internal', $labels, true)), array(true, true, false, false, false, false));
	$nf = $P->op('entry', array('id' => $B1['msg_id']));
	$nx = $P->op('entry', array('id' => 99999999));
	t('another owner\'s entry and an id that does not exist: the same efb_portal_not_found (404)', array($nf['code'] ?? '', $nf['status'] ?? 0, $nx['code'] ?? '', array_diff_key($nf, array('_headers' => 1)) == array_diff_key($nx, array('_headers' => 1))), array('efb_portal_not_found', 404, 'efb_portal_not_found', true));
	t('portal answers are not cached (Cache-Control no-store)', (bool) preg_match('/Cache-Control:[^\r\n]*no-store/i', $en['_headers']), true);
	$H->ctl('f14-set', (string) $A1['msg_id'], 'entry_status=1');
	$sp = $P->op('entry', array('id' => $A1['msg_id']));
	$ls2 = $P->op('entries');
	t('an entry in the spam: not found, not listed', array($sp['code'] ?? '', $ls2['data']['total'] ?? -1), array('efb_portal_not_found', 1));
	$H->ctl('f14-set', (string) $A1['msg_id'], 'entry_status=0');
	$c20('policy', $fid, json_encode(array('on' => false)));
	$off = $P->op('entry', array('id' => $A1['msg_id']));
	t('a form the portal does not show (policy off): not found', $off['code'] ?? '', 'efb_portal_not_found');
	$c20('policy', $fid, json_encode(array('on' => true, 'reply' => true, 'attach' => true, 'close' => true, 'reopen_days' => 14, 'edit' => true, 'edit_hours' => 24)));
	$nos = $P->op('entries');
	t('back in the portal', $nos['data']['total'] ?? -1, 2);

	echo "--- P10 / P11: thread, reply, ticket state ---\n";
	$H->ctl('f14-set', (string) $A1['msg_id'], 'read_=1');
	$nocsrf = $P->op('reply', array('id' => $A1['msg_id'], 'rows' => array(array('id_' => 'message', 'value' => 'no csrf'))), false);
	t('a write without the CSRF header is refused by the add-on (403) and stores nothing', array($nocsrf['_status'], count((array) $H->ctl('replies', (string) $A1['msg_id']))), array(403, 0));
	$rp = $P->op('reply', array('id' => $A1['msg_id'], 'rows' => array(array('id_' => 'message', 'value' => 'Is there news? "quoted" <b>x</b>'))));
	$rep = $H->ctl('replies', (string) $A1['msg_id']);
	$m1 = $c20('msg', $A1['msg_id']);
	t('the owner\'s reply: stored once (rsp_by 0), the entry is a new reply (1 → 3)', array(!empty($rp['success']), count($rep), (int) $m1['read_']), array(true, 1, 3));
	$th = $P->op('thread', array('id' => $A1['msg_id']));
	t('the thread shows it as "you", its text without markup', array($th['data'][0]['by'] ?? '', strpos($th['data'][0]['text'] ?? '', '<b>')), array('you', false));
	$log = $c20('log', $A1['msg_id']);
	t('F14\'s history: owner_reply with the actor "owner"', in_array(array('owner_reply', 'owner'), array_map(function ($x) { return array($x['action'], $x['meta']); }, $log), true), true);
	$bad = $P->op('reply', array('id' => $A1['msg_id'], 'rows' => array(array('id_' => 'message', 'type' => 'media', 'url' => 'javascript:alert(1)', 'value' => 'x'), array('type' => 'allformat', 'url' => 'https://evil.example/x.exe'))));
	t('a row outside the allow-list (a foreign file address) is refused, nothing stored', array($bad['code'] ?? '', count((array) $H->ctl('replies', (string) $A1['msg_id']))), array('efb_portal_invalid', 1));
	$nfr = $P->op('reply', array('id' => $B1['msg_id'], 'rows' => array(array('id_' => 'message', 'value' => 'hi'))));
	t('a reply to another owner\'s entry: not found', $nfr['code'] ?? '', 'efb_portal_not_found');
	$nonce = $H->admin();
	$H->ajax(array('action' => 'set_replyMessage_id_Emsfb', 'nonce' => $nonce, 'id' => (string) $A1['msg_id'], 'message' => json_encode(array(array('id_' => 'message', 'name' => 'message', 'type' => 'text', 'amount' => 0, 'value' => 'Support answer', 'by' => 'admin', 'session' => '')))));
	$th = $P->op('thread', array('id' => $A1['msg_id']));
	$en = $P->op('entry', array('id' => $A1['msg_id']));
	t('the support answer is "support" in the thread; the status is "awaiting_you"', array($th['data'][1]['by'] ?? '', $en['data']['status'] ?? ''), array('support', 'awaiting_you'));
	$cl = $P->op('state', array('id' => $A1['msg_id'], 'state' => 'closed'));
	$m1 = $c20('msg', $A1['msg_id']);
	$th = $P->op('thread', array('id' => $A1['msg_id']));
	t('the owner closes the request: read_ 4, a "closed" line in the thread, history "ticket" by owner', array(!empty($cl['success']), (int) $m1['read_'], end($th['data'])['kind'] ?? '', in_array(array('ticket', 'owner'), array_map(function ($x) { return array($x['action'], $x['meta']); }, $c20('log', $A1['msg_id'])), true)), array(true, 4, 'closed', true));
	$ro = $P->op('reply', array('id' => $A1['msg_id'], 'rows' => array(array('id_' => 'message', 'value' => 'One more thing'))));
	t('a reply within the reopen window reopens it as a new reply (4 → 3)', array(!empty($ro['success']), (int) $c20('msg', $A1['msg_id'])['read_']), array(true, 3));
	$P->op('state', array('id' => $A1['msg_id'], 'state' => 'closed'));
	$c20('policy', $fid, json_encode(array('on' => true, 'reply' => true, 'close' => true, 'reopen_days' => 0, 'edit' => true, 'edit_hours' => 24)));
	$cr = $P->op('reply', array('id' => $A1['msg_id'], 'rows' => array(array('id_' => 'message', 'value' => 'Too late'))));
	$cs = $P->op('state', array('id' => $A1['msg_id'], 'state' => 'open'));
	t('reopen window 0 ("never"): a reply is efb_portal_closed ("send a new form"), reopen is efb_portal_window_closed', array($cr['code'] ?? '', strpos($cr['message'] ?? '', 'send a new form') !== false, $cs['code'] ?? ''), array('efb_portal_closed', true, 'efb_portal_window_closed'));
	$c20('policy', $fid, json_encode(array('on' => true, 'reply' => true, 'attach' => true, 'close' => true, 'reopen_days' => 14, 'edit' => true, 'edit_hours' => 24)));
	$c20('limits', json_encode(array('prp' => 2)));
	$c20('reset');
	$codes = array();
	for ($k = 0; $k < 3; $k++) { $x = $P->op('reply', array('id' => $A2['msg_id'], 'rows' => array(array('id_' => 'message', 'value' => 'r' . $k)))); $codes[] = !empty($x['success']) ? 'ok' : ($x['code'] ?? '?'); }
	t('the reply limit (here 2 an hour): the third is efb_portal_rate_limited with retry_after', array($codes, ($x['retry_after'] ?? 0) > 0, $x['status'] ?? 0), array(array('ok', 'ok', 'efb_portal_rate_limited'), true, 429));
	$c20('limits', '');
	$ut = $P->op('upload_token', array('id' => $A2['msg_id']));
	t('an upload token for the reply\'s attachment (the response box\'s own upload route takes it)', array(!empty($ut['success']), strlen($ut['data']['token'] ?? ''), $ut['data']['track'] ?? ''), array(true, 48, $A2['track']));

	echo "--- P12 payments, P13 files ---\n";
	$c20('pmt', $A2['msg_id'], 'paid', 'legacy');
	$pay = $P->op('payments', array('id' => $A2['msg_id']));
	$praw = json_encode($pay);
	t('payments: amount, currency, status paid, receipt; never the gateway reference or the payer\'s address; the legacy row too (without its intent id)', array(count($pay['data'] ?? array()), $pay['data'][0]['amount_minor'] ?? 0, $pay['data'][0]['currency'] ?? '', $pay['data'][0]['receipt'] ?? null, strpos($praw, 'SECRETREF'), strpos($praw, 'payer-secret'), strpos($praw, 'SECRETINTENT'), $pay['data'][1]['legacy'] ?? null), array(2, 1250, 'EUR', true, false, false, false, true));
	$pf = $c20('private-file', $run);
	$H->ctl('f14-set', (string) $A2['msg_id'], 'read_=1');
	$m2r = $c20('msg', $A2['msg_id']);
	$rows2 = $m2r['rows'];
	array_splice($rows2, 1, 0, array(array('id_' => $i['msg'], 'name' => 'Message', 'type' => 'file', 'value' => '@file@', 'url' => $pf['url'], 'amount' => 3), array('id_' => $i['msg'], 'name' => 'Message', 'type' => 'file', 'value' => '@file@', 'url' => $H->base . '/wp-content/uploads/public-' . $run . '.png', 'amount' => 3)));
	$c20('raw-content', $A2['msg_id'], json_encode($rows2));
	$en2 = $P->op('entry', array('id' => $A2['msg_id']));
	$files = array_values(array_filter($en2['data']['answers'] ?? array(), function ($a) { return isset($a['file']); }));
	t('a private file in the entry has no address in the answers; a public one has its own', array(count($files), $files[0]['file']['private'] ?? null, isset($files[0]['file']['url']), $files[1]['file']['private'] ?? null, isset($files[1]['file']['url'])), array(2, true, false, false, true));
	$fl = $P->op('file', array('id' => $A2['msg_id'], 'n' => 0));
	$exp = preg_match('/[?&]exp=(\d+)/', $fl['data']['url'] ?? '', $mm) ? (int) $mm[1] - time() : 0;
	$dl = $H->http('GET', preg_replace('#^https?://[^/]+#', '', (string) ($fl['data']['url'] ?? '')), null, array(), false);
	t('the private file: a signed link for 10 minutes, made on request, that downloads', array(!empty($fl['success']), $exp > 500 && $exp <= 600, $dl['status'], strpos($dl['body'], 'portal private file') !== false), array(true, true, 200, true));
	$fo = $P->op('file', array('id' => $B1['msg_id'], 'n' => 0));
	t('a file of another owner\'s entry: not found', $fo['code'] ?? '', 'efb_portal_not_found');

	echo "--- P14: cancel and reschedule a booking ---\n";
	$H->ctl('f05-mu'); $H->ctl('set-option', 'efb_e2e_f05_plan', 'open');
	$H->ctl('set-option', 'efb_e2e_f06_plan', '2');
	$H->ctl('f06-set', 'booking', '1'); $H->ctl('f06-set', 'taken', '');
	$B = $H->ctl('f03-form', 'Portal booking ' . $run, json_encode(array(
		array('key' => 'em', 'type' => 'email', 'name' => 'Email'),
		array('key' => 'seat', 'type' => 'radio', 'name' => 'Seat', 'inv_on' => '1'),
		array('key' => 'front', 'type' => 'option', 'parent_key' => 'seat', 'value' => 'Front', 'inv_cap' => '1'),
		array('key' => 'back', 'type' => 'option', 'parent_key' => 'seat', 'value' => 'Back', 'inv_cap' => '5'),
		array('key' => 'day', 'type' => 'advdate', 'name' => 'Day', 'date_min' => array('type' => 'relative', 'value' => 1)),
	)), 'prv_id_field=@@em@@&lim_total=4', '1');
	$bfid = (int) $B['form_id']; $bi = $B['ids'];
	$c20('policy', $bfid, json_encode(array('on' => true, 'cancel' => true, 'cancel_hours' => 24, 'reschedule' => true, 'reschedule_hours' => 24, 'reschedule_max' => 1, 'edit' => true, 'edit_hours' => 24)));
	$d = function ($n) { return gmdate('Y-m-d', time() + $n * 86400); };
	$book = function ($email, $seat, $day) use ($H, $bfid, $bi) {
		return EfbF11Http::answer($H->submit($bfid, array(
			array('id_' => $bi['em'], 'name' => 'Email', 'type' => 'email', 'value' => $email),
			array('id_' => $bi['seat'], 'id_ob' => $bi[$seat], 'name' => 'Seat', 'type' => 'radio', 'value' => $seat === 'front' ? 'Front' : 'Back'),
			array('id_' => $bi['day'], 'name' => 'Day', 'type' => 'advdate', 'value' => $day, 'cal' => 'gregory'),
		)));
	};
	$k1 = $book($mail_a, 'front', $d(10));
	$k2 = $book($mail_a, 'back', $d(12));
	$BE = $H->ctl('f14-entries', $bfid);
	$K1 = $BE[0]; $K2 = $BE[1];
	$stock = function () use ($c20, $bfid) { $o = array(); foreach ((array) $c20('stock', $bfid) as $r) $o[$r['option_id']] = (int) $r['used']; return $o; };
	$s0 = $stock();
	t('two bookings stored; the front seat (1 place) is taken', array(!empty($k1['success']), !empty($k2['success']), $s0[$bi['front']] ?? null), array(true, true, 1));
	$full = $book($mail_b, 'front', $d(11));
	t('… so another visitor cannot take it (F05)', empty($full['success']), true);
	$c20('bef', '0');
	$en = $P->op('entry', array('id' => $K1['msg_id']));
	$x = $P->op('cancel', array('id' => $K1['msg_id']));
	$y = $P->op('reschedule', array('id' => $K2['msg_id'], 'field' => $bi['day'], 'value' => $d(14)));
	t('without the Booking add-on: cancel and reschedule answer addon_required (AdnBEF); the entry says it cannot', array($x['code'] ?? '', $x['addon'] ?? '', $y['code'] ?? '', $en['data']['can']['cancel'] ?? null, $en['data']['booking']['state'] ?? ''), array('addon_required', 'AdnBEF', 'addon_required', false, 'upcoming'));
	$bef = $c20('bef', '1');
	$en = $P->op('entry', array('id' => $K1['msg_id']));
	t('the Booking add-on on (its contract efb_portal_booking_api 1): the entry may be cancelled until 24 hours before', array($bef['AdnBEF'], $en['data']['can']['cancel'] ?? null, ($en['data']['booking']['cancel_until'] ?? 0) > time()), array(true, true, true));
	$c1 = $P->op('cancel', array('id' => $K1['msg_id'], 'reason' => 'Plans changed'));
	$m = $c20('msg', $K1['msg_id']);
	$s1 = $stock();
	t('cancel: owner_state 1, the front seat given back (1 → 0), the entry "cancelled", history owner_cancel', array(!empty($c1['success']), (int) $m['owner_state'], $s1[$bi['front']] ?? null, $P->op('entry', array('id' => $K1['msg_id']))['data']['status'] ?? '', in_array('owner_cancel', array_column($c20('log', $K1['msg_id']), 'action'), true)), array(true, 1, 0, 'cancelled', true));
	$c2 = $P->op('cancel', array('id' => $K1['msg_id']));
	t('a second cancel changes nothing (efb_portal_conflict; the seat count stays 0)', array($c2['code'] ?? '', $stock()[$bi['front']] ?? null), array('efb_portal_conflict', 0));
	$now = $book($mail_b, 'front', $d(11));
	t('the freed seat can be booked again', !empty($now['success']), true);
	$H->ctl('f06-set', 'taken', $d(14));
	$before = $c20('msg', $K2['msg_id'])['content'];
	$r1 = $P->op('reschedule', array('id' => $K2['msg_id'], 'field' => $bi['day'], 'value' => $d(14)));
	t('reschedule to a taken date: efb_portal_capacity ("no longer available"), nothing changed', array($r1['code'] ?? '', strpos($r1['message'] ?? '', 'no longer available') !== false, $c20('msg', $K2['msg_id'])['content'] === $before), array('efb_portal_capacity', true, true));
	$r2 = $P->op('reschedule', array('id' => $K2['msg_id'], 'field' => $bi['day'], 'value' => $d(15)));
	$after = $c20('msg', $K2['msg_id']);
	$dayrow = array_values(array_filter((array) $after['rows'], function ($r) use ($bi) { return is_array($r) && ($r['id_'] ?? '') === $bi['day']; }))[0] ?? array();
	$taken = (array) $H->ctl('option', 'efb_e2e_f06_taken');
	$ev = array_column((array) $H->ctl('option', 'efb_e2e_f20_events'), 0);
	t('reschedule to a free date: the row rebuilt by the server (value, disp), the new date reserved, the old one released (efb_date_released)', array(!empty($r2['success']), $dayrow['value'] ?? '', isset($dayrow['disp']), in_array($d(15), $taken, true), in_array($d(12), $taken, true), in_array('efb_date_released', $ev, true)), array(true, $d(15), true, true, false, true));
	$r3 = $P->op('reschedule', array('id' => $K2['msg_id'], 'field' => $bi['day'], 'value' => $d(16)));
	t('a second reschedule (at most 1): efb_portal_window_closed', $r3['code'] ?? '', 'efb_portal_window_closed');
	$k3 = $book($mail_a, 'back', $d(1));
	$BL = $H->ctl('f14-entries', $bfid); $K3 = end($BL);
	$c3 = $P->op('cancel', array('id' => $K3['msg_id']));
	t('a booking within 24 hours: cancel is efb_portal_window_closed', $c3['code'] ?? '', 'efb_portal_window_closed');
	$k4 = $book($mail_a, 'back', $d(20));
	$BL = $H->ctl('f14-entries', $bfid); $K4 = end($BL);
	t('the form\'s total cap (4) took the 4th booking: the cancelled one does not count (F11 place given back)', array(!empty($k4['success']), count($BL)), array(true, 5));
	$H->ctl('f14-set', (string) $K4['msg_id'], 'read_=2');
	$c4 = $P->op('cancel', array('id' => $K4['msg_id']));
	t('a booking with a payment (paid_cancel off): efb_portal_paid_locked', $c4['code'] ?? '', 'efb_portal_paid_locked');
	$xb = $P->op('cancel', array('id' => $B1['msg_id']));
	t('cancel of another owner\'s entry: not found', $xb['code'] ?? '', 'efb_portal_not_found');

	echo "--- P15: the owner's edit ---\n";
	$ed = $P->op('editable', array('id' => $K2['msg_id']));
	t('a booking without a support answer stays editable in its window: the seat (and the date), with the entry\'s hash', array(in_array($bi['seat'], $ed['data']['fields'] ?? array(), true), strlen($ed['data']['hash'] ?? '')), array(true, 32));
	$u1 = $P->op('update', array('id' => $K2['msg_id'], 'hash' => $ed['data']['hash'], 'changes' => array(array('id_' => $bi['seat'], 'value' => $bi['front']))));
	t('a full choice (front, taken again): efb_portal_capacity, nothing changed', array($u1['code'] ?? '', $u1['reason'] ?? ''), array('efb_portal_capacity', 'efb_choice_full'));
	$u0 = $P->op('update', array('id' => $K2['msg_id'], 'hash' => md5('old'), 'changes' => array(array('id_' => $bi['em'], 'value' => $mail_a))));
	t('an edit based on an older copy: efb_portal_conflict', $u0['code'] ?? '', 'efb_portal_conflict');
	$sb = $stock();
	$u2 = $P->op('update', array('id' => $K2['msg_id'], 'hash' => $ed['data']['hash'], 'changes' => array(array('id_' => $bi['day'], 'value' => $d(18)))));
	$after = $c20('msg', $K2['msg_id']);
	$dayrow = array_values(array_filter((array) $after['rows'], function ($r) use ($bi) { return is_array($r) && ($r['id_'] ?? '') === $bi['day']; }))[0] ?? array();
	t('an F06 date in the edit is reserved again (the new date taken, the earlier one released)', array(!empty($u2['success']), $dayrow['value'] ?? '', in_array($d(18), (array) $H->ctl('option', 'efb_e2e_f06_taken'), true), in_array($d(15), (array) $H->ctl('option', 'efb_e2e_f06_taken'), true)), array(true, $d(18), true, false));
	$ed2 = $P->op('editable', array('id' => $A1['msg_id']));
	t('an entry support has answered: nothing editable', $ed2['data'] ?? null, array());
	$Afresh = $sub('Edit me', $mail_a);
	$FL = $H->ctl('f14-entries', $fid); $AF = end($FL);
	$ef = $P->op('editable', array('id' => $AF['msg_id']));
	t('a fresh entry: editable fields without the password, the hidden field or any price', array(in_array($i['nm'], $ef['data']['fields'] ?? array(), true), in_array($i['pw'], $ef['data']['fields'] ?? array(), true), in_array($i['hid'], $ef['data']['fields'] ?? array(), true)), array(true, false, false));
	$up = $P->op('update', array('id' => $AF['msg_id'], 'hash' => $ef['data']['hash'], 'changes' => array(array('id_' => $i['nm'], 'value' => 'Edited by me'))));
	$lg = $c20('log', $AF['msg_id']);
	t('the owner\'s edit is stored; history: edit, the field, old → new, actor "owner"', array(!empty($up['success']), in_array(array('edit', $i['nm'], 'Edit me', 'Edited by me', 'owner'), array_map(function ($x) { return array($x['action'], $x['field_id'], $x['old_value'], $x['new_value'], $x['meta']); }, $lg), true)), array(true, true));
	$bad = $P->op('update', array('id' => $AF['msg_id'], 'hash' => $up['data']['hash'] ?? '', 'changes' => array(array('id_' => $i['em'], 'value' => 'not-an-email'))));
	t('an invalid e-mail is refused with the submit\'s check, at its field', array($bad['code'] ?? '', $bad['field_id'] ?? ''), array('efb_portal_invalid', $i['em']));
	$ro = $P->op('update', array('id' => $AF['msg_id'], 'hash' => $up['data']['hash'] ?? '', 'changes' => array(array('id_' => $i['pw'], 'value' => 'x'))));
	t('a field that may not be edited (the password): efb_portal_forbidden', $ro['code'] ?? '', 'efb_portal_forbidden');
	$c20('policy', $fid, json_encode(array('on' => true, 'reply' => true, 'edit' => true, 'edit_hours' => 0)));
	$late = $P->op('update', array('id' => $AF['msg_id'], 'hash' => $up['data']['hash'] ?? '', 'changes' => array(array('id_' => $i['nm'], 'value' => 'Too late'))));
	t('outside the window (edit_hours 0): efb_portal_window_closed', $late['code'] ?? '', 'efb_portal_window_closed');
	$c20('policy', $fid, json_encode(array('on' => true, 'reply' => true, 'attach' => true, 'close' => true, 'reopen_days' => 14, 'edit' => true, 'edit_hours' => 24, 'drafts' => true)));

	echo "--- P5 again: the index follows edits and deletions ---\n";
	$c20('purge', $A1['msg_id'], 'anon');
	t('an anonymized entry (F13) leaves the index and the portal', array($c20('owners', $A1['msg_id']), $P->op('entry', array('id' => $A1['msg_id']))['code'] ?? ''), array(array(), 'efb_portal_not_found'));
	$c20('purge', $B1['msg_id']);
	t('a deleted entry (F13 purge) leaves the index', $c20('owners', $B1['msg_id']), array());
	$H->ctl('f14-set', (string) $AF['msg_id'], 'read_=1');

	echo "--- P16: drafts ---\n";
	$dr = $c20('draft', $fid, strtoupper($mail_a));
	$dl = $P->op('drafts');
	$ids_d = array_column($dl['data'] ?? array(), 'id');
	t('the identity\'s unfinished form is listed (progress, expiry); another\'s draft is not', array(in_array($dr['id'], $ids_d, true), $dl['data'][0]['progress'] ?? null), array(true, 40));
	$lk = $P->op('draft_link', array('id' => $dr['id'], 'page' => $H->base . '/'));
	$newtok = preg_match('/efb_resume=([a-f0-9]{64})/', $lk['data'] ?? '', $mm) ? $mm[1] : '';
	t('a new link: the new token opens the draft, the old one no longer does', array($newtok !== '' && $newtok !== $dr['token'], $c20('draft-load', $fid, $newtok)['opens'], $c20('draft-load', $fid, $dr['token'])['opens']), array(true, true, false));
	$dB = $c20('draft', $fid, $mail_b);
	$lx = $P->op('draft_link', array('id' => $dB['id']));
	t('a link to another person\'s draft: not found', $lx['code'] ?? '', 'efb_portal_not_found');

	echo "--- §4.10 privacy, the WordPress account, another identity ---\n";
	$pr = $P->op('privacy', array('email' => $mail_a, 'type' => 'export'));
	t('a data request with the identity\'s own address: WordPress\' own request (e-mail confirmation)', array(!empty($pr['success']), $pr['data']['sent'] ?? ''), array(true, 'email'));
	$pb = $P->op('privacy', array('email' => $mail_b, 'type' => 'erase'));
	t('… with someone else\'s address: not for this identity (not found)', $pb['code'] ?? '', 'efb_portal_not_found');
	$ps = $S->op('privacy', array('email' => '', 'type' => 'erase'));
	t('a mobile-only identity: a note for the site on its entries', array(!empty($ps['success']), $ps['data']['sent'] ?? ''), array(true, 'owner'));
	$c20('reset');
	$si = $signin($S, $mail_a);
	$both = $S->op('entries');
	t('"add another e-mail": the session now has both identities and lists the entries of both', array(!empty($si['verify']['success']), count($both['data']['rows'] ?? array()) >= 3), array(true, true));
	$caps = $c20('call', 'efb_portal_core_api', '[]');
	t('with the add-on on: active, the index ready', array($caps['active'] ?? null, $caps['index']['state'] ?? ''), array(true, 'ready'));

	echo "--- P4 on a form that stores nothing; P6 the WordPress account ---\n";
	$N = $H->ctl('f03-form', 'Portal nostore ' . $run, json_encode(array(array('key' => 'em', 'type' => 'email', 'name' => 'Email'))), 'prv_nostore=1', '1');
	$c20('stored', 'clear');
	$ns = EfbF11Http::answer($H->submit((int) $N['form_id'], array(array('id_' => $N['ids']['em'], 'name' => 'Email', 'type' => 'email', 'value' => $mail_a))));
	t('a form that stores nothing (F13 prv_nostore): sent, no efb_entry_stored, no entry', array(!empty($ns['success']), $c20('stored'), count((array) $H->ctl('f14-entries', $N['form_id']))), array(true, array(), 0));
	$login = 'efb_prt_' . $run;
	$u = $H->ctlb('user', $login);
	$H->ctlb('msg-uid', (string) $K2['msg_id'], $login);
	$W = new EfbPortalClient($H);
	$keep = $H->jar; $H->jar = $W->jar;
	$H->login($login, $login . '-pass-123');
	$rn = trim($H->http('GET', '/wp-admin/admin-ajax.php?action=rest-nonce', null, array(), false)['body']);
	$H->jar = $keep;
	$w = $W->op('wp', array('wp_email' => false), false, array('X-WP-Nonce' => $rn));
	if (!empty($w['success'])) $W->csrf = $w['data']['csrf'];
	$wl = $W->op('entries', array(), true, array('X-WP-Nonce' => $rn));
	$wid = $W->op('identity', array(), true, array('X-WP-Nonce' => $rn));
	t('a signed-in WordPress user without a code: the entries sent while signed in (uid), the identity "u" signed by core', array(!empty($w['success']), array_column($wl['data']['rows'] ?? array(), 'msg_id'), array_column($wid['data'] ?? array(), 'k')), array(true, array((int) $K2['msg_id']), array('u')));
	$forged = $c20('call', 'efb_portal_entries', json_encode(array(array(array('k' => 'u', 'h' => str_repeat('b', 64), 'uid' => (int) $u['id'])))));
	t('a "u" identity with a hash core did not make is ignored (no entries)', $forged['total'] ?? -1, 0);
	$H->ctlb('user-del', $login);
} finally {
	// The site as it was for the other tests: the mock off, the stand-ins off.
	$c20('on', '0'); $c20('bef', '0'); $c20('limits', '');
	$H->ctl('f06-set', 'booking', '0'); $H->ctl('f06-set', 'taken', '');
	$H->ctl('f02-wpsms', '0'); $H->ctl('addon-flag', 'AdnSS', '0');
	$H->ctl('f12-setting', 'smtp=' . ($smtp0 === null ? 'del' : rawurlencode((string) $smtp0)));
	$H->ctl('set-option', 'efb_e2e_f20_stored', 'del');
}
efb_done();
