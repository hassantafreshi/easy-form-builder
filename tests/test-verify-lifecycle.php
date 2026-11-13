<?php
/**
 * F02-C: the challenge's life (spec §5.3, §5.4, §5.7) on the SQLite stand-in:
 * attempts counted atomically before the compare, pending → verified once, consumed once
 * (all or nothing), release gives back only what this call took, attach, the cleanup windows,
 * the purge and form-delete hooks; and 6 concurrent hits on a limit of 5 (20 processes on one file
 * database, pcntl_fork) give exactly 5.
 *
 * Run: php tests/test-verify-lifecycle.php
 */
require __DIR__ . '/verify-test-lib.php';
use Emsfb\Verification as V;

$db = vt_env();
$mk = function ($extra = array()) {
	return V::create_challenge(array_merge(array('fid' => 5, 'field_id' => 'mob1', 'channel' => 'sms', 'dest_hash' => str_repeat('d', 64), 'dest_mask' => '+98 912 ***-4567', 'sid' => 'sid-a', 'ip_hash' => '', 'code' => '123456', 'provider' => 'wpsms', 'max_attempts' => 3, 'ttl' => 300), $extra));
};
$r = $mk();
vt('create: pending, 32-hex public id, HMAC of the code, expiry = now + ttl', array($r['status'], (bool) preg_match('/^[a-f0-9]{32}$/', $r['challenge_id']), $r['code_hash'] === V::hash_code($r['challenge_id'], '123456'), $r['expires_at']), array('pending', true, true, gmdate('Y-m-d H:i:s', V::$now + 300)));
$a = array();
for ($i = 0; $i < 5; $i++) $a[] = V::attempt($r['challenge_id']);
vt('attempt: true while attempts < max (3), then false', $a, array(true, true, true, false, false));
vt('attempts never pass the maximum', (int) $db->get_var("SELECT attempts FROM wp_emsfb_vrfy_ WHERE id = {$r['id']}"), 3);
$r2 = $mk();
vt('mark_verified once', array(V::mark_verified($r2['challenge_id']), V::mark_verified($r2['challenge_id'])), array(true, false));
vt('attempt on a verified challenge is refused', V::attempt($r2['challenge_id']), false);
$c2 = array('cid' => $r2['challenge_id'], 'field_id' => 'mob1');
vt('consume: 1 the first time', V::consume(array($c2)), 1);
vt('consume: 0 the second time (single use)', V::consume(array($c2)), 0);
$r3 = $mk(); V::mark_verified($r3['challenge_id']);
$r4 = $mk(array('field_id' => 'eml1')); // pending, not verified
$c3 = array('cid' => $r3['challenge_id']); $c4 = array('cid' => $r4['challenge_id']);
vt('consume all or nothing: one of two not verified → 0', V::consume(array($c3, $c4)), 0);
vt('… and the one it had taken is verified again (rolled back)', $db->get_var("SELECT status FROM wp_emsfb_vrfy_ WHERE id = {$r3['id']}"), 'verified');
vt('release only gives back consumed rows without an entry', V::release(array($c2)), 1);
vt('after release the same claim can be consumed again', V::consume(array($c2)), 1);
V::attach(array($c2), 77);
vt('attach writes the entry id', (int) $db->get_var("SELECT msg_id FROM wp_emsfb_vrfy_ WHERE id = {$r2['id']}"), 77);
vt('a consumed row that belongs to an entry is not released any more', V::release(array($c2)), 0);
$r5 = $mk(array('field_id' => 'x5'));
vt('supersede: the pending row of the same form/field/session/destination', V::supersede(5, 'x5', 'sid-a', str_repeat('d', 64)), 1);
vt('a superseded challenge cannot be verified', V::mark_verified($r5['challenge_id']), false);

/* cleanup */
$db = vt_env();
$old = $mk(); V::$now += 31 * 86400; $new = $mk(); $v = $mk(); V::mark_verified($v['challenge_id']);
V::$now += 1801;
$n = V::cleanup();
vt('cleanup: a 31-day-old pending row is removed, a fresh one kept', array($db->count('wp_emsfb_vrfy_', "id = {$old['id']}"), $db->count('wp_emsfb_vrfy_', "id = {$new['id']}")), array(0, 1));
vt('cleanup: a verified token older than token_ttl expires', $db->get_var("SELECT status FROM wp_emsfb_vrfy_ WHERE id = {$v['id']}"), 'expired');
$cons = $mk(); V::mark_verified($cons['challenge_id']); V::consume(array(array('cid' => $cons['challenge_id']))); V::attach(array(array('cid' => $cons['challenge_id'])), 9);
V::$now += 60 * 86400;
V::cleanup();
vt('cleanup: a consumed row is kept 90 days (60 later still there)', $db->count('wp_emsfb_vrfy_', "id = {$cons['id']}"), 1);
V::$now += 31 * 86400;
V::cleanup();
vt('… and removed after 90 days', $db->count('wp_emsfb_vrfy_', "id = {$cons['id']}"), 0);
add_filter('efb_verify_retention_days', function ($d, $kind) { return $kind === 'open' ? 1 : $d; });
$x = $mk(); V::$now += 2 * 86400;
V::cleanup();
vt('efb_verify_retention_days shortens the open window', $db->count('wp_emsfb_vrfy_', "id = {$x['id']}"), 0);
$GLOBALS['efb_filters'] = array();
$e1 = $mk(); V::mark_verified($e1['challenge_id']); V::consume(array(array('cid' => $e1['challenge_id']))); V::attach(array(array('cid' => $e1['challenge_id'])), 501);
V::on_entries_purged(array(501), 'delete');
vt('deleting entry 501 removes its challenge', $db->count('wp_emsfb_vrfy_', "id = {$e1['id']}"), 0);
$f1 = $mk(array('fid' => 9));
V::on_form_deleted(9);
vt('deleting form 9 removes its challenges', $db->count('wp_emsfb_vrfy_', 'fid = 9'), 0);

/* the register() wiring */
$GLOBALS['efb_hooks'] = array(); $GLOBALS['efb_filters'] = array();
V::register();
vt('hooks: daily cleanup, hourly expiry, entry purge, form delete, exporter, eraser, capabilities', array(isset($GLOBALS['efb_hooks']['emsfb_daily_maintenance']), isset($GLOBALS['efb_hooks']['emsfb_operation_cleanup']), isset($GLOBALS['efb_hooks']['efb_entries_purged']), isset($GLOBALS['efb_hooks']['efb_form_deleted']), isset($GLOBALS['efb_filters']['wp_privacy_personal_data_exporters']), isset($GLOBALS['efb_filters']['wp_privacy_personal_data_erasers']), isset($GLOBALS['efb_filters']['efb_capabilities'])), array(true, true, true, true, true, true, true));
$GLOBALS['efb_hooks'] = array(); $GLOBALS['efb_filters'] = array();

/* concurrency: 20 processes, limit 5 */
if (!function_exists('pcntl_fork')) {
	echo "[SKIP] pcntl_fork is not available: concurrency check skipped\n";
} else {
	$file = sys_get_temp_dir() . '/efb-vrfy-conc-' . getmypid() . '.sqlite';
	foreach (array(1, 2, 3) as $round) {
		@unlink($file);
		$fdb = $GLOBALS['wpdb'] = new EfbF11TestDb($file);
		$fdb->create_f11_tables();
		\Emsfb\Rate_Limit::reset_ready();
		$pids = array();
		for ($i = 0; $i < 20; $i++) {
			$pid = pcntl_fork();
			if ($pid === 0) {
				$GLOBALS['wpdb'] = new EfbF11TestDb($file);
				\Emsfb\Rate_Limit::reset_ready();
				usleep(20000);
				exit(V::hit('vedh', str_repeat('e', 64), 3600, 5) ? 0 : 3);
			}
			$pids[] = $pid;
		}
		$ok = 0;
		foreach ($pids as $p) { pcntl_waitpid($p, $st); if (pcntl_wexitstatus($st) === 0) $ok++; }
		$GLOBALS['wpdb'] = new EfbF11TestDb($file);
		\Emsfb\Rate_Limit::reset_ready();
		vt("round {$round}: 20 concurrent sends to one address, cap 5 → exactly 5 pass", array($ok, V::count_of('vedh', str_repeat('e', 64), 3600)), array(5, 5));
	}
	@unlink($file);
}

vt_done();
