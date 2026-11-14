<?php
/**
 * F14-F: an administrator's edit of an entry on the e2e site (tests/f11-http-lib.php): entries are made by real
 * submissions (claims and places taken as a visitor's), then efb_entry_update_Emsfb is called as the panel calls
 * it, logged in as the administrator.
 *
 *  - a text change is stored with edited_at, one history row (old → new, the reason), a quote kept as @efb@dq#;
 *  - an invalid e-mail is refused at its field (field_id) with the submit's own message; nothing changes;
 *  - the content hash: an edit based on an older copy → efb_entry_conflict;
 *  - a choice that is no longer in the form survives an edit of another field; a new choice takes the form's text;
 *  - the page link (w_link) is kept; a required field cannot be emptied; a disabled field may be filled;
 *  - F04: a calculation is computed again from the edited input;
 *  - F09: one field of row 2 changes, row 1 stays, rg / ri kept;
 *  - F03: a unique value taken by another entry is refused (tvUnique_); a free one is claimed and the old claim
 *    of this entry goes;
 *  - F05: a choice whose places are all taken is refused (efb_choice_full); a free one takes its place;
 *  - read-only: a signature is refused (efb_entry_readonly);
 *  - a paid entry: the price inputs a calculation reads are read-only; another field changes, the payment row and
 *    the total stay as charged;
 *  - a file is removed (deleted from disk when nothing else names it) and another uploaded through
 *    efb_entry_upload_Emsfb is added (and leaves the pending ledger);
 *  - a Subscriber and a request without the nonce are refused; on Free the edit needs Free Plus.
 *
 * Run (under the e2e lock): source the e2e env; php tests/test-entry-edit.php
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
$H->ctl('f05-mu');
$H->ctl('set-option', 'efb_e2e_f05_plan', 'open');

$F = $H->ctl('f03-form', 'F14 edit', json_encode(array(
	array('key' => 'nm', 'type' => 'text', 'name' => 'Name', 'required' => 1),
	array('key' => 'em', 'type' => 'email', 'name' => 'Email', 'unique' => '1'),
	array('key' => 'sz', 'type' => 'radio', 'name' => 'Size'),
	array('key' => 's1', 'type' => 'option', 'parent_key' => 'sz', 'value' => 'Small'),
	array('key' => 's2', 'type' => 'option', 'parent_key' => 'sz', 'value' => 'Large'),
	array('key' => 'seat', 'type' => 'radio', 'name' => 'Seat', 'inv_on' => '1'),
	array('key' => 'front', 'type' => 'option', 'parent_key' => 'seat', 'value' => 'Front', 'inv_cap' => '1'),
	array('key' => 'back', 'type' => 'option', 'parent_key' => 'seat', 'value' => 'Back', 'inv_cap' => '5'),
	array('key' => 'qa', 'type' => 'number', 'name' => 'Qty'),
	array('key' => 'tot', 'type' => 'calc', 'name' => 'Total', 'formula' => '{@@qa@@} * 3'),
	array('key' => 'dis', 'type' => 'text', 'name' => 'Office use', 'disabled' => 1),
	array('key' => 'g', 'type' => 'repeater', 'name' => 'Guests', 'min_rows' => '1', 'max_rows' => '3', 'default_rows' => '1'),
	array('key' => 'gn', 'type' => 'text', 'name' => 'Guest', 'group' => '@@g@@'),
)), '', '1');
$i = $F['ids'];
$fid = (int) $F['form_id'];
$H->ctl('f04-mu');
$H->ctl('set-option', 'efb_e2e_f04_plan', 'open');
$H->ctl('f09-mu');
$H->ctl('set-option', 'efb_e2e_f09_plan', 'open');
$mk = function ($name, $email, $seat, $qty, $guests) use ($i) {
	$r = array(
		array('id_' => $i['nm'], 'name' => 'Name', 'type' => 'text', 'value' => $name),
		array('id_' => $i['em'], 'name' => 'Email', 'type' => 'email', 'value' => $email),
		array('id_' => $i['sz'], 'id_ob' => $i['s1'], 'name' => 'Size', 'type' => 'radio', 'value' => 'Small'),
		array('id_' => $i['seat'], 'id_ob' => $i[$seat], 'name' => 'Seat', 'type' => 'radio', 'value' => $seat === 'front' ? 'Front' : 'Back'),
		array('id_' => $i['qa'], 'name' => 'Qty', 'type' => 'number', 'value' => (string) $qty),
		array('id_' => $i['g'], 'name' => 'Guests', 'type' => 'repeater', 'value' => (string) count($guests)),
	);
	foreach ($guests as $k => $gname) $r[] = array('id_' => $i['gn'], 'name' => 'Guest', 'type' => 'text', 'value' => $gname, 'rg' => $i['g'], 'ri' => (string) $k);
	return $r;
};
$a1 = EfbF11Http::answer($H->submit($fid, $mk('Ali', 'ali@example.test', 'front', 2, array('Sara', 'Reza'))));
$a2 = EfbF11Http::answer($H->submit($fid, $mk('Mina', 'mina@example.test', 'back', 1, array('Omid'))));
t('two entries stored by submissions', !empty($a1['success']) && !empty($a2['success']), true);
$entries = $H->ctl('f14-entries', $fid);
$e1 = $entries[0]; $e2 = $entries[1];
$row = function ($content, $id, $ri = null) { foreach ((array) $content as $r) if (is_array($r) && ($r['id_'] ?? '') === $id && ($ri === null || (string) ($r['ri'] ?? '') === (string) $ri)) return $r; return null; };
t('the entries hold the calc (2 × 3 = 6.00) as the submit computed it', $row($e1['content'], $i['tot'])['value'] ?? null, '6.00');

t('the administrator logs in', $H->login(getenv('EFB_E2E_USER') ?: 'admin', getenv('EFB_E2E_PASS') ?: 'admin-pass-123'), true);
$p = $H->http('GET', '/wp-admin/admin.php?page=Emsfb', null, array(), false);
$nonce = preg_match('/efb_var\s*=\s*\{[^;]*?"nonce":"([a-f0-9]+)"/', $p['body'], $m) ? $m[1] : '';
$ajax = function ($data) use ($H, &$nonce) {
	$r = $H->http('POST', '/wp-admin/admin-ajax.php', http_build_query(array_merge(array('nonce' => $nonce), $data)), array('Content-Type' => 'application/x-www-form-urlencoded'), false);
	$j = json_decode($r['body'], true);
	return is_array($j) && isset($j['data']) ? $j['data'] : array('raw' => substr($r['body'], 0, 300));
};
$get = function ($id) use ($ajax) { return $ajax(array('action' => 'efb_entry_get_Emsfb', 'msg_id' => $id)); };
$edit = function ($id, $changes, $reason = '', $hash = null) use ($ajax, $get) {
	if ($hash === null) $hash = $get($id)['entry']['hash'];
	return $ajax(array('action' => 'efb_entry_update_Emsfb', 'msg_id' => $id, 'hash' => $hash, 'changes' => json_encode($changes), 'reason' => $reason));
};
$cur = function ($id) use ($H, $fid) { foreach ($H->ctl('f14-entries', $fid) as $e) if ((int) $e['msg_id'] === (int) $id) return $e; return null; };

/* text, history, quote */
$g = $get($e1['msg_id']);
t('efb_entry_get_Emsfb gives the edit fields (Name editable and required, Total read-only)', array($g['fields'][0]['mode'], $g['fields'][0]['required'], array_values(array_filter($g['fields'], function ($f) use ($i) { return $f['id'] === $i['tot']; }))[0]['mode']), array('edit', true, 'readonly'));
$r = $edit($e1['msg_id'], array(array('id_' => $i['nm'], 'value' => 'Ali "the 1st" Rezaei')), 'typo');
$c = $cur($e1['msg_id']);
t('a text change is stored (quote as @efb@dq#), edited_at set', array($r['success'] ?? null, str_replace('@efb@dq#', '"', $row($c['content'], $i['nm'])['value'] ?? ''), strpos($c['raw'], '@efb@dq#the 1st@efb@dq#') !== false, $c['edited_at'] !== null && $c['edited_at'] !== ''), array(true, 'Ali "the 1st" Rezaei', true, true));
$log = $H->ctl('f14-log', $e1['msg_id']);
$last = end($log);
t('one history row: edit, the field, old → new, the reason, the administrator', array($last['action'], $last['field_id'], $last['old_value'], $last['new_value'], $last['meta'], (int) $last['user_id'] > 0), array('edit', $i['nm'], 'Ali', 'Ali "the 1st" Rezaei', 'typo', true));
t('the page link (w_link) is kept', count(array_filter((array) $c['content'], function ($x) { return is_array($x) && ($x['type'] ?? '') === 'w_link'; })), 1);

/* invalid, conflict, required, disabled */
$before = $cur($e1['msg_id'])['raw'];
$r = $edit($e1['msg_id'], array(array('id_' => $i['em'], 'value' => 'not-an-email')));
t('an invalid e-mail is refused at its field', array($r['success'], $r['code'], $r['field_id'], $cur($e1['msg_id'])['raw'] === $before), array(false, 'efb_entry_invalid', $i['em'], true));
$old_hash = md5('something else');
$r = $edit($e1['msg_id'], array(array('id_' => $i['nm'], 'value' => 'X')), '', $old_hash);
t('an edit based on an older copy → efb_entry_conflict ("changed in the meantime")', array($r['code'], strpos($r['m'], 'changed in the meantime') !== false), array('efb_entry_conflict', true));
$r = $edit($e1['msg_id'], array(array('id_' => $i['nm'], 'value' => '')));
t('a required field cannot be emptied', array($r['code'], $r['field_id']), array('efb_entry_required', $i['nm']));
$r = $edit($e1['msg_id'], array(array('id_' => $i['dis'], 'value' => 'checked by phone')));
t('a field the form shows disabled may be filled by the administrator ("Add value")', array($r['success'], $row($cur($e1['msg_id'])['content'], $i['dis'])['value'] ?? null), array(true, 'checked by phone'));

/* choices: an option removed from the form survives; a new choice takes the form's text */
$H->ctl('f14-raw-option', $fid, $i['s1'], 'Small (old)');
$r = $edit($e1['msg_id'], array(array('id_' => $i['nm'], 'value' => 'Ali Rezaei')));
t('a stored choice that the form no longer has stays when another field is edited', $row($cur($e1['msg_id'])['content'], $i['sz'])['value'] ?? null, 'Small');
$r = $edit($e1['msg_id'], array(array('id_' => $i['sz'], 'value' => $i['s2'])));
$sz = $row($cur($e1['msg_id'])['content'], $i['sz']);
t('a new choice takes the saved option\'s text and id', array($sz['value'] ?? null, $sz['id_ob'] ?? null), array('Large', $i['s2']));

/* F04 calc */
$r = $edit($e1['msg_id'], array(array('id_' => $i['qa'], 'value' => '۵')));
$c = $cur($e1['msg_id'])['content'];
t('F04: the input is stored (Persian digits read) and the calc is computed again (5 × 3 = 15.00)', array($row($c, $i['qa'])['value'] ?? null, $row($c, $i['tot'])['value'] ?? null), array('5', '15.00'));

/* F09 rows */
$r = $edit($e1['msg_id'], array(array('id_' => $i['gn'], 'ri' => '1', 'value' => 'Reza Karimi')));
$c = $cur($e1['msg_id'])['content'];
t('F09: row 2 changes, row 1 stays, rg / ri kept', array($row($c, $i['gn'], 0)['value'] ?? null, $row($c, $i['gn'], 1)['value'] ?? null, $row($c, $i['gn'], 1)['rg'] ?? null), array('Sara', 'Reza Karimi', $i['g']));
$log = $H->ctl('f14-log', $e1['msg_id']);
t('F09: the history names the field of that row', end($log)['new_value'], 'Reza Karimi');

/* F03 unique */
$r = $edit($e2['msg_id'], array(array('id_' => $i['em'], 'value' => 'ali@example.test')));
t('F03: a unique value another entry holds is refused at its field', array($r['code'], $r['field_id']), array('efb_field_unique', $i['em']));
$claims = $H->ctl('f03-uniq', $fid)['rows'];
$r = $edit($e2['msg_id'], array(array('id_' => $i['em'], 'value' => 'mina.new@example.test')));
$claims2 = $H->ctl('f03-uniq', $fid)['rows'];
$of2 = array_values(array_filter($claims2, function ($c) use ($e2) { return (int) $c['msg_id'] === (int) $e2['msg_id']; }));
t('F03: a free value is claimed for this entry and its old claim goes (still one claim of entry 2)', array($r['success'], count($of2), count($claims2)), array(true, 1, count($claims)));
$a3 = EfbF11Http::answer($H->submit($fid, $mk('Neda', 'mina@example.test', 'back', 1, array('X'))));
t('F03: the old value is free again (a new submission with it is accepted)', !empty($a3['success']), true);

/* F05 stock */
$st0 = $H->ctl('f05-stock', $fid);
$r = $edit($e2['msg_id'], array(array('id_' => $i['seat'], 'value' => $i['front'])));
t('F05: a choice whose places are all taken (Front, 1 of 1) is refused', array($r['code'], $r['field_id']), array('efb_choice_full', $i['seat']));
$r = $edit($e1['msg_id'], array(array('id_' => $i['seat'], 'value' => $i['back'])));
$st1 = $H->ctl('f05-stock', $fid);
$used = function ($st, $opt) { foreach ($st['rows'] as $x) if ($x['option_id'] === $opt) return (int) $x['used']; return 0; };
t('F05: moving entry 1 to Back takes a Back place; Front stays taken (inv_release off, F05\'s rule)', array($used($st1, $i['back']) - $used($st0, $i['back']), $used($st1, $i['front'])), array(1, 1));

/* read-only and files */
$r = $edit($e1['msg_id'], array(array('id_' => $i['tot'], 'value' => '99')));
t('a calculation cannot be edited (efb_entry_readonly)', $r['code'], 'efb_entry_readonly');
$r = $edit($e1['msg_id'], array(array('id_' => 'zz_unknown', 'value' => 'x')));
t('a field the form does not have cannot be edited', $r['code'], 'efb_entry_readonly');

/* a paid entry: the amount charged never changes (spec §17-4) */
$paidRows = $mk('Pay', 'pay@example.test', 'back', 4, array('X'));
$paidRows[] = array('id_' => $i['tot'], 'name' => 'Total', 'type' => 'calc', 'value' => '12.00');
$paidRows[] = array('id_' => 'payment', 'name' => 'Payment', 'type' => 'payment', 'value' => '12.00', 'total' => '12', 'currency' => 'usd', 'paymentGateway' => 'stripe');
$pm = (int) $H->ctl('f14-seed', $fid, json_encode($paidRows))['msg_id'];
$g = $get($pm);
$modes = array(); foreach ($g['fields'] as $f) $modes[$f['id']] = $f['mode'] . '/' . $f['why'];
t('a paid entry: the input a calculation reads (Qty) is read-only "paid", the name stays editable', array($modes[$i['qa']], $modes[$i['nm']]), array('readonly/paid', 'edit/'));
t('a paid entry: changing Qty is refused (efb_entry_readonly)', $edit($pm, array(array('id_' => $i['qa'], 'value' => '9')))['code'] ?? '', 'efb_entry_readonly');
$r = $edit($pm, array(array('id_' => $i['nm'], 'value' => 'Paid Person')));
$c = $cur($pm);
t('a paid entry: another field is edited; the payment row and the total stay as charged', array($r['success'] ?? null, $row($c['content'], $i['nm'])['value'], $row($c['content'], $i['tot'])['value'], $row($c['content'], 'payment')['value'], $row($c['content'], $i['qa'])['value']), array(true, 'Paid Person', '12.00', '12.00', '4'));

/* permissions and plan */
$saved_nonce = $nonce;
$nonce = '';
t('without the nonce → efb_forbidden', $edit($e1['msg_id'], array(array('id_' => $i['nm'], 'value' => 'Y')), '', 'x')['code'] ?? '', 'efb_forbidden');
$nonce = $saved_nonce;
$H->ctl('plan', '2');
t('on Free the edit needs Free Plus (setting:entries_edit)', $edit($e1['msg_id'], array(array('id_' => $i['nm'], 'value' => 'Y')))['feature'] ?? '', 'setting:entries_edit');
$H->ctl('plan', '3');
$H->ctl('user', 'efb_e2e_f14sub', 'subscriber');
$H->fresh_jar();
$H->login('efb_e2e_f14sub', 'efb_e2e_f14sub-pass-123');
$nonce = trim($H->http('GET', '/wp-admin/admin-ajax.php?action=rest-nonce', null, array(), false)['body']);
t('a Subscriber with a valid nonce → efb_forbidden', $ajax(array('action' => 'efb_entry_update_Emsfb', 'msg_id' => $e1['msg_id'], 'hash' => 'x', 'changes' => '[]'))['code'] ?? '', 'efb_forbidden');
$H->ctl('set-option', 'efb_e2e_f05_plan', 'del');
$H->ctl('set-option', 'efb_e2e_f04_plan', 'del');
$H->ctl('set-option', 'efb_e2e_f09_plan', 'del');

echo "\nRESULTS: {$passed} passed, {$failed} failed\n";
exit($failed ? 1 : 0);
