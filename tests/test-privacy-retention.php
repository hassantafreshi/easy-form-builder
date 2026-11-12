<?php
/**
 * F13 sub-task D: the shared purge, uploaded files and the retention job.
 *
 * Real Privacy and Upload_Guard code on an in-memory SQLite database (tests/privacy-test-db.php)
 * and real files in a temporary uploads folder with year/month subfolders:
 *  1. last activity: a 100-day-old entry goes with prv_days = 90; one with a 10-day-old reply stays;
 *     per-form prv_days beats the global one; "0" on a form means never.
 *  2. files: found in uploads/2026/02/ and deleted with the entry; kept when another entry names
 *     them; a path outside uploads or a foreign name is never deleted.
 *  3. anonymization is exactly §4.3 (personal answers @prv@, choices kept, prv_pii overrides,
 *     page link without query, ip empty, replies replaced, prv_state 1, uid 0); a second run
 *     changes nothing.
 *  4. a single delete removes the replies too (BUGS-FOUND: replies left behind); the admin
 *     delete paths call the shared purge.
 *  5. 5000 old entries: the run stops at 2000 and schedules the continuation.
 *  6. the preview count equals what the run removes.
 *  7. payment entries are anonymized and kept (filter efb_privacy_keep_payment_entries).
 *  8. the email log loses rows older than prv_days; the last run is recorded.
 *
 * Run: php tests/test-privacy-retention.php
 */
define('ABSPATH', __DIR__ . '/');
define('HOUR_IN_SECONDS', 3600);

$passed = 0;
$failed = 0;
function t($name, $actual, $expected) {
	global $passed, $failed;
	if ($actual === $expected) { echo "[PASS] {$name}\n"; $passed++; return; }
	echo "[FAIL] {$name}: expected " . var_export($expected, true) . ', got ' . var_export($actual, true) . "\n";
	$failed++;
}

$GLOBALS['opts'] = array();
$GLOBALS['settings'] = new stdClass();
$GLOBALS['actions'] = array();
function get_option($k, $d = false) { return array_key_exists($k, $GLOBALS['opts']) ? $GLOBALS['opts'][$k] : $d; }
function update_option($k, $v, $a = null) { $GLOBALS['opts'][$k] = $v; return true; }
function get_setting_Emsfb($m = 'decoded') { return $GLOBALS['settings']; }
function apply_filters($h, $v) { return isset($GLOBALS['test_filters'][$h]) ? call_user_func($GLOBALS['test_filters'][$h], $v) : $v; }
function do_action($h, ...$a) { $GLOBALS['actions'][] = array($h, $a); }
function add_action() {} function add_filter() {}
function emsfb_json_normalize_efb($s) { return str_replace('\\"', '"', (string) $s); }

require __DIR__ . '/privacy-test-db.php';
require __DIR__ . '/../includes/class-Emsfb-upload-guard.php';
require __DIR__ . '/../includes/class-Emsfb-privacy.php';
use Emsfb\Privacy;
use Emsfb\Upload_Guard;

$tmp = sys_get_temp_dir() . '/efb-prv-ret-' . getmypid();
@mkdir($tmp . '/uploads/2026/02', 0777, true);
@mkdir($tmp . '/outside', 0777, true);
efb_privacy_test_wp_stubs($tmp . '/uploads', 'http://example.test/wp-content/uploads');
register_shutdown_function(function () use ($tmp) { exec('rm -rf ' . escapeshellarg($tmp)); });
$URL = 'http://example.test/wp-content/uploads/2026/02/';
function mkfile($rel) { global $tmp; $p = $tmp . '/uploads/' . $rel; file_put_contents($p, 'x'); return $p; }

$days_ago = function ($d) { return date('Y-m-d H:i:s', time() - $d * 86400); };
$fresh = function () { $GLOBALS['wpdb'] = new EfbPrivacyTestDb(); $GLOBALS['opts'] = array(); $GLOBALS['efb_cron'] = array(); $GLOBALS['efb_transients'] = array(); return $GLOBALS['wpdb']; };

// ------------------------------------------------------------------ 1. last activity
$db = $fresh();
$GLOBALS['settings'] = (object) array('prv_days' => '90', 'prv_action' => 'delete', 'prv_files' => '1');
$f1 = $db->add_form(array(array('type' => 'form')));
$old = $db->add_entry($f1, array(array('id_' => 'a', 'type' => 'text', 'value' => 'old')), $days_ago(100));
$old_r = $db->add_reply($old, array(array('id_' => 'message', 'type' => 'text', 'value' => 'ancient reply')), $days_ago(99));
$kept = $db->add_entry($f1, array(array('id_' => 'a', 'type' => 'text', 'value' => 'active')), $days_ago(100));
$db->add_reply($kept, array(array('id_' => 'message', 'type' => 'text', 'value' => 'recent reply')), $days_ago(10));
$new = $db->add_entry($f1, array(array('id_' => 'a', 'type' => 'text', 'value' => 'new')), $days_ago(5));
$preview = Privacy::preview_count(90);
$run = Privacy::run_retention();
t('preview before the run: 1 entry', $preview, 1);
t('run deleted exactly what the preview said', $run['deleted'], $preview);
t('100-day-old entry with prv_days 90 is deleted', $db->count('wp_emsfb_msg_', "msg_id = $old"), 0);
t('...and its reply too', $db->count('wp_emsfb_rsp_', "msg_id = $old"), 0);
t('100-day-old entry with a 10-day-old reply stays (last activity)', $db->count('wp_emsfb_msg_', "msg_id = $kept"), 1);
t('5-day-old entry stays', $db->count('wp_emsfb_msg_', "msg_id = $new"), 1);
t('last run recorded', isset($GLOBALS['opts']['emsfb_prv_last_run']['time'], $GLOBALS['opts']['emsfb_prv_last_run']['deleted']) && $GLOBALS['opts']['emsfb_prv_last_run']['deleted'] === 1, true);
t('efb_entries_purged fired with the ids', array_values(array_filter($GLOBALS['actions'], function ($a) { return $a[0] === 'efb_entries_purged'; }))[0][1][0], array($old));

// per-form override
$db = $fresh();
$GLOBALS['settings'] = (object) array('prv_days' => '30');
$fa = $db->add_form(array(array('type' => 'form', 'prv_days' => '0')));
$fb = $db->add_form(array(array('type' => 'form', 'prv_days' => '200')));
$fc = $db->add_form(array(array('type' => 'form')));
foreach (array($fa, $fb, $fc) as $f) Privacy::on_form_saved($f, Privacy::decode_content($db->get_var("SELECT form_structer FROM wp_emsfb_form WHERE form_id = $f")));
$ea = $db->add_entry($fa, array(), $days_ago(100));
$eb = $db->add_entry($fb, array(), $days_ago(100));
$eb2 = $db->add_entry($fb, array(), $days_ago(250));
$ec = $db->add_entry($fc, array(), $days_ago(100));
t('preview of the global rule excludes forms with their own days', Privacy::preview_count(30), 1);
t('preview of one form', Privacy::preview_count(200, $fb), 1);
Privacy::run_retention();
t('form with prv_days "0": never deleted', $db->count('wp_emsfb_msg_', "msg_id = $ea"), 1);
t('form with prv_days 200: 100-day entry kept, 250-day entry deleted', array($db->count('wp_emsfb_msg_', "msg_id = $eb"), $db->count('wp_emsfb_msg_', "msg_id = $eb2")), array(1, 0));
t('form without its own days follows the global 30', $db->count('wp_emsfb_msg_', "msg_id = $ec"), 0);
$GLOBALS['settings'] = (object) array();
$db->add_entry($fc, array(), $days_ago(4000));
Privacy::run_retention();
t('no setting (default 0): nothing is ever deleted', $db->count('wp_emsfb_msg_', "form_id = $fc"), 1);

// ------------------------------------------------------------------ 2. files
$db = $fresh();
$GLOBALS['settings'] = (object) array('prv_days' => '90', 'prv_files' => '1');
$f = $db->add_form(array(array('type' => 'form')));
$p1 = mkfile('2026/02/efb-PLG-260201-AAAA1111.pdf');
$p2 = mkfile('2026/02/efb-PLG-260201-BBBB2222.pdf');
$p3 = mkfile('2026/02/efb-rec-260201-CCCC3333aaaa.webm');
$p4 = mkfile('2026/02/efb-PLG-260201-RPLY4444.png');
$outside = $tmp . '/outside/efb-PLG-260201-OUTS5555.pdf'; file_put_contents($outside, 'x');
$foreign = mkfile('2026/02/holiday.jpg');
$e1 = $db->add_entry($f, array(
	array('id_' => 'f1', 'type' => 'file', 'value' => '@file@', 'url' => $URL . 'efb-PLG-260201-AAAA1111.pdf'),
	array('id_' => 'f2', 'type' => 'dadfile', 'value' => '@file@', 'url' => $URL . 'efb-PLG-260201-BBBB2222.pdf'),
	array('id_' => 'f3', 'type' => 'video_recorder', 'value' => '@file@', 'url' => $URL . 'efb-rec-260201-CCCC3333aaaa.webm'),
	array('id_' => 'f4', 'type' => 'file', 'value' => '@file@', 'url' => 'http://example.test/../../outside/efb-PLG-260201-OUTS5555.pdf'),
	array('id_' => 'f5', 'type' => 'file', 'value' => '@file@', 'url' => $URL . 'holiday.jpg'),
), $days_ago(100));
$db->add_reply($e1, array(array('id_' => 'resp_file_efb_1', 'type' => 'allformat', 'value' => '@file@', 'url' => $URL . 'efb-PLG-260201-RPLY4444.png')), $days_ago(95));
$e2 = $db->add_entry($f, array(array('id_' => 'f2', 'type' => 'file', 'value' => '@file@', 'url' => $URL . 'efb-PLG-260201-BBBB2222.pdf')), $days_ago(1));
$r = Privacy::run_retention();
t('file in uploads/2026/02/ deleted with its entry', file_exists($p1), false);
t('recording (efb-rec-) deleted', file_exists($p3), false);
t('reply attachment deleted', file_exists($p4), false);
t('file another entry still names is kept', file_exists($p2), true);
t('file outside uploads is never deleted', file_exists($outside), true);
t('a name the plugin did not generate is never deleted', file_exists($foreign), true);
t('files counted', $r['files'], 3);
$GLOBALS['settings'] = (object) array('prv_days' => '90', 'prv_files' => '');
$p6 = mkfile('2026/02/efb-PLG-260201-KEEP6666.pdf');
$db->add_entry($f, array(array('id_' => 'f1', 'type' => 'file', 'value' => '@file@', 'url' => $URL . 'efb-PLG-260201-KEEP6666.pdf')), $days_ago(100));
Privacy::run_retention();
t('"also delete uploaded files" off: the file stays', file_exists($p6), true);

// ------------------------------------------------------------------ 3. anonymization (§4.3)
$db = $fresh();
$GLOBALS['settings'] = (object) array('prv_days' => '30', 'prv_action' => 'anon', 'prv_files' => '1');
$f = $db->add_form(array(array('type' => 'form'), array('id_' => 'n1', 'type' => 'number', 'prv_pii' => '1'), array('id_' => 't2', 'type' => 'text', 'prv_pii' => '0')));
Privacy::on_form_saved($f, array(array('type' => 'form')));
$pa = mkfile('2026/02/efb-PLG-260201-ANON7777.pdf');
$rows = array(
	array('id_' => 'e1', 'name' => 'Email', 'type' => 'email', 'value' => 'jane@example.test'),
	array('id_' => 't1', 'name' => 'Name', 'type' => 'text', 'value' => 'Jane'),
	array('id_' => 't2', 'name' => 'Code', 'type' => 'text', 'value' => 'keep-me'),
	array('id_' => 'n1', 'name' => 'Age', 'type' => 'number', 'value' => '41'),
	array('id_' => 'r1', 'name' => 'Pick', 'type' => 'radio', 'value' => 'Yes'),
	array('id_' => 'c1', 'name' => 'Many', 'type' => 'checkbox', 'value' => 'A'),
	array('id_' => 'i1', 'name' => 'Img', 'type' => 'imgRadio', 'value' => 'Cat', 'src' => 'http://x/cat.png', 'sub_value' => 'c'),
	array('id_' => 'm1', 'name' => 'Map', 'type' => 'maps', 'value' => array(array('lat' => 1, 'lng' => 2, 'address' => 'Home'))),
	array('id_' => 's1', 'name' => 'Sign', 'type' => 'esign', 'value' => 'data:image/png;base64,AAAA'),
	array('id_' => 'u1', 'name' => 'CV', 'type' => 'file', 'value' => '@file@', 'url' => $URL . 'efb-PLG-260201-ANON7777.pdf'),
	array('id_' => 'q1', 'name' => 'Odd', 'type' => 'newfangled', 'value' => 'secret'),
	array('id_' => 'x1', 'name' => 'Qty', 'type' => 'chlCheckBox', 'value' => 'Item', 'qty' => '2'),
	array('type' => 'w_link', 'id_' => 'w_link', 'value' => 'https://site.test/contact/?utm_source=x&email=jane@example.test#top'),
);
$e = $db->add_entry($f, $rows, $days_ago(60), array('uid' => 7));
$db->add_reply($e, array(array('id_' => 'message', 'type' => 'text', 'value' => 'my phone is 0912'), array('id_' => 'resp_file_efb_1', 'type' => 'allformat', 'value' => '@file@', 'url' => $URL . 'efb-PLG-260201-ANON7777.pdf')), $days_ago(50));
$r = Privacy::run_retention();
$row = $db->row('wp_emsfb_msg_', "msg_id = $e");
$c = array();
foreach (Privacy::decode_content($row['content']) as $x) $c[$x['id_']] = $x;
t('anonymized, not deleted', array($r['anonymized'], $r['deleted']), array(1, 0));
t('email -> @prv@', $c['e1']['value'], '@prv@');
t('text -> @prv@', $c['t1']['value'], '@prv@');
t('prv_pii "0" keeps a text answer', $c['t2']['value'], 'keep-me');
t('prv_pii "1" clears a number', $c['n1']['value'], '@prv@');
t('radio kept', $c['r1']['value'], 'Yes');
t('checkbox kept', $c['c1']['value'], 'A');
t('imgRadio kept with its src', array($c['i1']['value'], $c['i1']['src']), array('Cat', 'http://x/cat.png'));
t('maps -> @prv@', $c['m1']['value'], '@prv@');
t('esign -> @prv@', $c['s1']['value'], '@prv@');
t('file -> @prv@ and url emptied', array($c['u1']['value'], $c['u1']['url']), array('@prv@', ''));
t('unknown type -> @prv@ (conservative)', $c['q1']['value'], '@prv@');
t('chlCheckBox kept with qty', array($c['x1']['value'], $c['x1']['qty']), array('Item', '2'));
t('page link keeps the path only', $c['w_link']['value'], 'https://site.test/contact/');
t('labels kept', $c['e1']['name'], 'Email');
t('entry ip emptied, prv_state 1, uid 0', array($row['ip'], (int) $row['prv_state'], (int) $row['uid']), array('', 1, 0));
$rr = $db->row('wp_emsfb_rsp_', "msg_id = $e");
t('reply replaced by one @prv@ message', Privacy::decode_content($rr['content']), array(array('id_' => 'message', 'type' => 'text', 'value' => '@prv@')));
t('reply ip and reader_ip emptied', array($rr['ip'], $rr['reader_ip']), array('', ''));
t('the file of a cleared answer is deleted', file_exists($pa), false);
$before = $row['content'];
$r2 = Privacy::run_retention();
t('second run: nothing more', array($r2['anonymized'], $r2['deleted']), array(0, 0));
t('second run: content unchanged', $db->row('wp_emsfb_msg_', "msg_id = $e")['content'], $before);
$r3 = Privacy::purge_entries(array($e), 'anon');
t('purge_entries(anon) on an anonymized entry is a no-op', $r3['anonymized'], 0);

// ------------------------------------------------------------------ 4. single delete removes replies; admin paths
$db = $fresh();
$GLOBALS['settings'] = (object) array();
$f = $db->add_form(array(array('type' => 'form')));
$e = $db->add_entry($f, array(), $days_ago(1));
$db->add_reply($e, array(array('id_' => 'message', 'type' => 'text', 'value' => 'hi')), $days_ago(1));
$db->add_reply($e, array(array('id_' => 'message', 'type' => 'text', 'value' => 'again')), $days_ago(1));
$other = $db->add_entry($f, array(), $days_ago(1));
$db->add_reply($other, array(array('id_' => 'message', 'type' => 'text', 'value' => 'x')), $days_ago(1));
$r = Privacy::delete_entries(array($e));
t('single delete: entry gone', $db->count('wp_emsfb_msg_', "msg_id = $e"), 0);
t('single delete: both replies gone (no orphans)', $db->count('wp_emsfb_rsp_', "msg_id = $e"), 0);
t('single delete: other entry and its reply untouched', array($db->count('wp_emsfb_msg_', "msg_id = $other"), $db->count('wp_emsfb_rsp_', "msg_id = $other")), array(1, 1));
t('manual delete of a payment entry still deletes it (today)', (function () use ($db, $f) {
	$pe = $db->add_entry($f, array(array('id_' => 'payment', 'type' => 'payment', 'value' => '10 usd')), date('Y-m-d H:i:s'));
	Privacy::delete_entries(array($pe));
	return $db->count('wp_emsfb_msg_', "msg_id = $pe");
})(), 0);
$r = Privacy::delete_form_entries($f);
t('form delete: every entry and reply of the form', array($db->count('wp_emsfb_msg_', "form_id = $f"), $db->count('wp_emsfb_rsp_')), array(0, 0));
$adm = file_get_contents(__DIR__ . '/../includes/admin/class-Emsfb-admin.php');
t('delete_message_id_public uses the shared purge', (bool) preg_match('/function delete_message_id_public\(\).*?Privacy::delete_entries\(\[\$id\]\)/s', $adm), true);
t('delete_messages_Emsfb uses the shared purge', (bool) preg_match('/function delete_messages_Emsfb\(\).*?Privacy::delete_entries\(\$msg_id_list\)/s', $adm), true);
t('delete_form_id_public uses the shared purge', (bool) preg_match('/function delete_form_id_public\(\).*?Privacy::delete_form_entries\(\$id\)/s', $adm), true);

// ------------------------------------------------------------------ 5. 5000 rows: limit and continuation
$db = $fresh();
$GLOBALS['settings'] = (object) array('prv_days' => '10');
$f = $db->add_form(array(array('type' => 'form')));
$db->pdo->beginTransaction();
$ins = $db->pdo->prepare('INSERT INTO wp_emsfb_msg_ (form_id, track, ip, content, date) VALUES (?, ?, ?, ?, ?)');
for ($i = 0; $i < 5000; $i++) $ins->execute(array($f, 'K' . $i, '1.2.3.4', '[]', $days_ago(20)));
$db->pdo->commit();
t('preview: 5000', Privacy::preview_count(10), 5000);
$r = Privacy::run_retention();
t('first run stops after 2000 entries', array($r['deleted'], $db->count('wp_emsfb_msg_')), array(2000, 3000));
t('...says there is more', $r['more'], true);
t('...and schedules emsfb_privacy_retention_continue in about 5 minutes', isset($GLOBALS['efb_cron']['emsfb_privacy_retention_continue']) && abs($GLOBALS['efb_cron']['emsfb_privacy_retention_continue'] - (time() + 300)) < 5, true);
Privacy::run_retention();
$r = Privacy::run_retention();
t('two more runs finish the job', array($db->count('wp_emsfb_msg_'), $r['more']), array(0, false));
$GLOBALS['efb_transients']['emsfb_prv_retention_lock'] = time();
$db->add_entry($f, array(), $days_ago(20));
Privacy::run_retention();
t('a run already in progress (lock) is not started twice', $db->count('wp_emsfb_msg_'), 1);

// ------------------------------------------------------------------ 7. payment entries kept (anonymized)
$db = $fresh();
$GLOBALS['settings'] = (object) array('prv_days' => '10', 'prv_action' => 'delete');
$f = $db->add_form(array(array('type' => 'payment')), 'payment');
$pe = $db->add_entry($f, array(array('id_' => 'e1', 'type' => 'email', 'value' => 'p@example.test'), array('id_' => 'payment', 'type' => 'payment', 'value' => '10 usd', 'paymentIntent' => 'pi_123')), $days_ago(20));
$r = Privacy::run_retention();
$prow = $db->row('wp_emsfb_msg_', "msg_id = $pe");
$pc = array(); foreach (Privacy::decode_content($prow['content']) as $x) $pc[$x['id_']] = $x;
t('payment entry kept (retained), personal answers cleared, transaction id kept', array($r['retained'], $r['deleted'], $pc['e1']['value'], $pc['payment']['paymentIntent'], (int) $prow['prv_state']), array(1, 0, '@prv@', 'pi_123', 1));
$GLOBALS['test_filters']['efb_privacy_keep_payment_entries'] = function () { return false; };
$pe2 = $db->add_entry($f, array(array('id_' => 'payment', 'type' => 'payment', 'value' => '5 usd')), $days_ago(20));
Privacy::run_retention();
t('with the filter off, payment entries are deleted too', $db->count('wp_emsfb_msg_', "msg_id = $pe2"), 0);
unset($GLOBALS['test_filters']['efb_privacy_keep_payment_entries']);

// ------------------------------------------------------------------ 8. email log
$db = $fresh();
$GLOBALS['settings'] = (object) array('prv_days' => '30');
$GLOBALS['opts']['efb_email_log'] = array(
	array('to' => 'old@example.test', 'date' => $days_ago(40), 'success' => true),
	array('to' => 'new@example.test', 'date' => $days_ago(2), 'success' => true),
);
$r = Privacy::run_retention();
t('email log: rows older than prv_days removed', array_column($GLOBALS['opts']['efb_email_log'], 'to'), array('new@example.test'));
t('email log count in the run summary', $r['email_log'], 1);

// ------------------------------------------------------------------ upload sweeper (BUGS-FOUND: orphans never removed)
$db = $fresh();
$orphan = mkfile('2026/02/efb-PLG-260201-ORPH8888.pdf');
$claimed = mkfile('2026/02/efb-PLG-260201-CLMD9999.pdf');
$db->add_entry(1, array(array('id_' => 'f', 'type' => 'file', 'value' => '@file@', 'url' => $URL . 'efb-PLG-260201-CLMD9999.pdf')), $days_ago(1));
$GLOBALS['opts'][Upload_Guard::PENDING_OPTION] = array('efb-PLG-260201-ORPH8888.pdf' => time() - 2 * 86400, 'efb-PLG-260201-CLMD9999.pdf' => time() - 2 * 86400);
$GLOBALS['wpdb']->options = 'wp_emsfb_form'; // the reservation query reads options; no rows there
if (!function_exists('maybe_unserialize')) { function maybe_unserialize($v) { return $v; } }
$n = Upload_Guard::cleanup_orphan_uploads();
t('sweeper finds the orphan in uploads/YYYY/MM/ and deletes it', array($n, file_exists($orphan)), array(1, false));
t('sweeper never deletes a file an entry names', file_exists($claimed), true);

echo "\nRESULTS: {$passed} passed, {$failed} failed\n";
exit($failed ? 1 : 0);
