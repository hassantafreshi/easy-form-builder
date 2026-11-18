<?php
/**
 * The ticket state of an entry (emsfb_msg_.read_: 0 new, 1 read, 2 pending payment, 3 new visitor reply, 4 closed) on
 * the real WordPress (tests/e2e-wp, EFB_E2E_SITE_DIR), through the routes a browser uses:
 *
 *  - BUG (F20 §15): the panel's Close wrote 4 and the same request then wrote 1, and every later administrator action
 *    (a reply, opening the entry, "Mark as read" on the list) wrote 1 again, so a closed ticket never stayed closed;
 *  - BUG (F20 §15): a visitor's reply wrote 0 and nothing ever wrote 3 ("new reply"), which F14's tabs, the panel's
 *    counter and the entries API read;
 *  - what stays as it was: a new entry opened → read; a visitor's reply on a new entry stays new; Reopen → read;
 *    the replies of an entry are marked read by "Mark as read"; a pending payment (2) is never turned into read or new
 *    by opening, replying or "Mark as read" (the legacy gateways find their pending entry by read_ = 2);
 *  - old rows stay readable: entries stored with 3 and 4 by older versions open in F14 and count in its tabs; the admin
 *    menu's badge counts new visitor replies (3) besides new entries (0).
 *
 * Run (the e2e site, under the e2e lock): php tests/test-ticket-state.php
 */
require __DIR__ . '/f12b-http-lib.php';
$H = new EfbF12bHttp();
if ($why = $H->unavailable()) { echo "[SKIP] {$why}\n"; echo "\nRESULTS: 0 passed, 0 failed\n"; exit(0); }

$H->ctl('plan', '3');
$H->ctlb('rl-reset');
$H->ctl('f12-setting', 'secretKey=del&siteKey=del&reply_rl_track=100&reply_rl_ip=0');
$F = $H->ctl('f03-form', 'Ticket states', json_encode(array(array('key' => 'nm', 'type' => 'text', 'name' => 'Name'))));
$fid = (int) $F['form_id'];
$seed = function ($v, $extra = '') use ($H, $F) { return $H->ctl('f14-seed', (string) $F['form_id'], json_encode(array(array('id_' => $F['ids']['nm'], 'name' => 'Name', 'type' => 'text', 'value' => $v))), $extra); };
$state = function ($E) use ($H, $fid) { foreach ((array) $H->ctl('f14-entries', $fid) as $e) if ((int) $e['msg_id'] === (int) $E['msg_id']) return (int) $e['read_']; return -1; };
$replies = function ($E) use ($H) { return (array) $H->ctl('replies', (string) $E['msg_id']); };

/* A visitor's reply through forms/response/add, as the tracker sends it (own cookie jar, no CAPTCHA on this site). */
$vjar = sys_get_temp_dir() . '/efb-ticket-visitor-' . getmypid();
$visitor = function ($E, $text = 'A visitor reply') use ($H, $vjar) {
	@unlink($vjar);
	$p = $H->http('POST', '/wp-json/Emsfb/v1/forms/session/prepare', array('form_id' => 0, 'runtime_token' => ''), array(), true, $vjar);
	$S = is_array($p['json']) ? $p['json'] : array();
	$msg = json_encode(array(array('id_' => 'message', 'name' => 'message', 'type' => 'text', 'amount' => 0, 'value' => $text, 'by' => '', 'session' => '')));
	$body = array('id' => (string) $E['msg_id'], 'track' => $E['track'], 'message' => $msg, 'sid' => $S['sid'] ?? '', 'user_type' => 'user', 'sc' => '', 'page_id' => 0, 'valid' => '', 'efb_hp' => '', 'type' => 'form', 'request_id' => EfbF11Http::uuid(), 'runtime_token' => $S['runtime_token'] ?? '');
	return EfbF11Http::answer($H->http('POST', '/wp-json/Emsfb/v1/forms/response/add', $body, array('X-WP-Nonce' => $S['nonce'] ?? '', 'sid' => $S['sid'] ?? '', 'form-id' => '0'), true, $vjar));
};

$nonce = $H->admin();
t('the administrator is signed in (wp_rest nonce)', strlen($nonce), 10);
$ajax = function (array $f) use ($H, &$nonce) { $r = $H->ajax(array_merge(array('nonce' => $nonce), $f)); $j = json_decode($r['body'], true); return is_array($j) && isset($j['data']) ? $j['data'] : array('raw' => substr($r['body'], 0, 200)); };
/* The panel's reply box (set_replyMessage_id_Emsfb): a text, Close (type closed) or Reopen (type opened). */
$admin = function ($E, $type = 'text') use ($ajax) {
	$v = $type === 'text' ? 'Support answer' : ($type === 'closed' ? 'This conversation was closed.' : 'This conversation was reopened.');
	return $ajax(array('action' => 'set_replyMessage_id_Emsfb', 'id' => (string) $E['msg_id'], 'message' => json_encode(array(array('id_' => 'message', 'name' => 'message', 'type' => $type, 'amount' => 0, 'value' => $v, 'by' => 'admin', 'session' => '')))));
};
$open = function ($E) use ($ajax) { return $ajax(array('action' => 'update_message_state_Emsfb', 'id' => (string) $E['msg_id'])); };
$markRead = function (array $Es) use ($ajax) { return $ajax(array('action' => 'read_list_Emsfb', 'state' => 'msg', 'val' => json_encode(array_map(function ($E) { return array('msg_id' => (string) $E['msg_id']); }, $Es)))); };
$badge = function () use ($H) { $p = $H->http('GET', '/wp-admin/admin.php?page=Emsfb', null, array(), false); return preg_match('/id="efbCountM" class="efb awaiting-mod">(\d+)</', $p['body'], $m) ? (int) $m[1] : 0; };

echo "--- a new entry, read, a visitor's reply ---\n";
$b0 = $badge();
$E = $seed('Ticket A');
t('a new entry is 0 and counts in the admin menu badge', array($state($E), $badge() - $b0), array(0, 1));
$a = $visitor($E, 'Still new');
t('a visitor\'s reply on a new entry: stored, the entry stays new (0)', array(!empty($a['success']), $state($E), count($replies($E))), array(true, 0, 1));
$open($E);
t('the administrator opens it: read (1), no longer in the badge', array($state($E), $badge() - $b0), array(1, 0));
$a = $visitor($E, 'One more question');
t('BUG: a visitor\'s reply on a read entry is a new reply (3), not 0', array(!empty($a['success']), $state($E)), array(true, 3));
t('the admin menu badge counts the new visitor reply (3) as before it counted 0', $badge() - $b0, 1);
$a = $admin($E);
t('the administrator\'s reply reads it again (3 → 1)', array(!empty($a['success']), $state($E)), array(true, 1));

echo "--- Close, and what comes after ---\n";
$a = $admin($E, 'closed');
t('BUG: Close stays closed in the same request (4, not 4 then 1)', array(!empty($a['success']), $state($E)), array(true, 4));
$admin($E);
t('BUG: a later reply of the administrator keeps it closed', $state($E), 4);
$open($E);
t('BUG: opening the closed entry again keeps it closed', $state($E), 4);
$markRead(array($E));
t('BUG: "Mark as read" on the old list keeps it closed', $state($E), 4);
$r = $ajax(array('action' => 'efb_entries_bulk_Emsfb', 'op' => 'read', 'form_id' => (string) $fid, 'ids' => json_encode(array((int) $E['msg_id']))));
t('F14\'s "Mark as read" keeps it closed (as before)', array(!empty($r['success']), (int) ($r['done'] ?? -1), $state($E)), array(true, 0, 4));
$a = $visitor($E, 'Please reopen');
t('a visitor\'s reply on a closed ticket is a new reply (4 → 3)', array(!empty($a['success']), $state($E)), array(true, 3));
$admin($E, 'closed');
t('Close again → 4', $state($E), 4);
$admin($E, 'opened');
t('Reopen → read (1)', $state($E), 1);
$admin($E, 'closed');

echo "--- the staff reply from the tracking page (the administrator signed in) ---\n";
$E2 = $seed('Ticket B', 'read_=3');
$p = $H->http('POST', '/wp-json/Emsfb/v1/forms/session/prepare', array('form_id' => 0, 'runtime_token' => ''), array('X-WP-Nonce' => $nonce));
$SA = is_array($p['json']) ? $p['json'] : array();
$staff = function ($E) use ($H, $SA, $nonce) {
	$msg = json_encode(array(array('id_' => 'message', 'name' => 'message', 'type' => 'text', 'amount' => 0, 'value' => 'Staff answer', 'by' => '', 'session' => '')));
	$body = array('id' => (string) $E['msg_id'], 'track' => $E['track'], 'message' => $msg, 'sid' => $SA['sid'] ?? '', 'user_type' => 'admin', 'sc' => '', 'page_id' => 0, 'valid' => '', 'efb_hp' => '', 'type' => 'form');
	return EfbF11Http::answer($H->http('POST', '/wp-json/Emsfb/v1/forms/response/add', $body, array('X-WP-Nonce' => !empty($SA['nonce']) ? $SA['nonce'] : $nonce, 'sid' => $SA['sid'] ?? '', 'form-id' => '0')));
};
$a = $staff($E2);
t('a staff reply from the tracking page reads a new visitor reply (3 → 1)', array(!empty($a['success']), $state($E2)), array(true, 1));
$a = $staff($E);
t('BUG: a staff reply from the tracking page keeps a closed ticket closed (4)', array(!empty($a['success']), $state($E)), array(true, 4));

echo "--- a pending payment (2) is never changed by these ---\n";
$P = $seed('Unpaid', 'read_=2');
$open($P);
t('opening a pending payment keeps 2', $state($P), 2);
$admin($P);
t('the administrator\'s reply keeps 2', $state($P), 2);
$a = $visitor($P, 'How do I pay?');
t('a visitor\'s reply keeps 2 (the legacy gateways find the entry by read_ = 2)', array(!empty($a['success']), $state($P)), array(true, 2));
$markRead(array($P));
t('"Mark as read" keeps 2', $state($P), 2);

echo "--- the old list's \"Mark as read\" still works on new entries and marks their replies ---\n";
$N1 = $seed('New one');
$N2 = $seed('New two', 'read_=3');
$visitor($N1, 'A reply to read');
$markRead(array($N1, $N2));
$rs = $replies($N1);
t('0 and 3 → 1', array($state($N1), $state($N2)), array(1, 1));
t('the entry\'s replies are marked read', is_array($rs) && $rs && count(array_filter($rs, function ($x) { return (string) ($x['read_'] ?? '') !== '1'; })) === 0, true);

echo "--- old rows (3 and 4 written by older versions) stay readable ---\n";
$O3 = $seed('Older new reply', 'read_=3');
$O4 = $seed('Older closed', 'read_=4');
$g3 = $ajax(array('action' => 'efb_entry_get_Emsfb', 'msg_id' => (string) $O3['msg_id']));
$g4 = $ajax(array('action' => 'efb_entry_get_Emsfb', 'msg_id' => (string) $O4['msg_id']));
t('F14 opens them with their state', array(!empty($g3['success']), (int) ($g3['entry']['read_'] ?? -1), !empty($g4['success']), (int) ($g4['entry']['read_'] ?? -1)), array(true, 3, true, 4));
$q = $ajax(array('action' => 'efb_entries_query_Emsfb', 'form_id' => (string) $fid, 'tab' => 'closed'));
$closed_ids = array_map(function ($r) { return (int) $r['msg_id']; }, (array) ($q['rows'] ?? array()));
sort($closed_ids);
$want = array((int) $E['msg_id'], (int) $O4['msg_id']);
sort($want);
t('F14\'s Closed tab lists the ticket closed in the panel and the older closed one', $closed_ids, $want);
$q = $ajax(array('action' => 'efb_entries_query_Emsfb', 'form_id' => (string) $fid, 'tab' => 'unread'));
t('F14\'s Unread tab lists the older new reply (3)', in_array((int) $O3['msg_id'], array_map(function ($r) { return (int) $r['msg_id']; }, (array) ($q['rows'] ?? array())), true), true);

@unlink($vjar);
$H->ctl('f12-setting', 'reply_rl_track=del&reply_rl_ip=del');
efb_done();
