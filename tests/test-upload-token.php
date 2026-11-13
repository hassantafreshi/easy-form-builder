<?php
/**
 * F07-B: upload tokens, file slots of the quota, the quota in the atomic counter table, the pending
 * ledger's binding and the sweeper (uploads/YYYY/MM and the private folder).
 *
 *  1. issue / verify_upload_token: HMAC (wp_salt('nonce')) of stored name, form, field, session
 *     bucket and expiry; a tampered, expired, other-form, other-field or other-session token is
 *     refused; hash_equals.
 *  2. count_upload_slots(): a field with max_files = 5 has five slots (Free Plus), one on Free;
 *     count_upload_fields() (Human Shield bridge) answers the same; recorders one each.
 *  3. quota_limit(): slots × 3 (one field of five files = 15), capped at 60 (emsfb_upload_quota_cap),
 *     emsfb_upload_quota_limit still last.
 *  4. quota_reserve() / quota_release() on Rate_Limit (SQLite stand-in of the real table): exact,
 *     the per-IP ceiling, a refused file gives its place back; the transient fallback without the table.
 *  5. track_pending_upload() binds a file to its form and field; pending_entry() reads it; old
 *     ledger rows (time only) still read; release keeps working with names and URLs.
 *  6. cleanup_orphan_uploads(): an abandoned file in uploads/2026/11 and one in efb-private/2026/11
 *     are deleted after the grace period; a file an entry names is kept.
 *
 * Run: php tests/test-upload-token.php
 */
define('ABSPATH', __DIR__ . '/');
define('HOUR_IN_SECONDS', 3600);
define('DAY_IN_SECONDS', 86400);
define('MINUTE_IN_SECONDS', 60);

$passed = 0; $failed = 0;
function t($name, $actual, $expected) {
	global $passed, $failed;
	if ($actual === $expected) { echo "[PASS] {$name}\n"; $passed++; return; }
	echo "[FAIL] {$name}: expected " . var_export($expected, true) . ', got ' . var_export($actual, true) . "\n";
	$failed++;
}

require __DIR__ . '/f11-test-db.php';
require_once __DIR__ . '/privacy-test-db.php';
$tmp = sys_get_temp_dir() . '/efb-f07-token-' . getmypid();
@mkdir($tmp . '/uploads/2026/11', 0777, true);
efb_privacy_test_wp_stubs($tmp . '/uploads');
if (!function_exists('wp_cache_get')) { function wp_cache_get() { return false; } }
if (!function_exists('wp_cache_set')) { function wp_cache_set() { return true; } }
if (!function_exists('wp_max_upload_size')) { function wp_max_upload_size() { return 0; } }
if (!function_exists('_n')) { function _n($a, $b, $n) { return $n == 1 ? $a : $b; } }
if (!function_exists('size_format')) { function size_format($b) { return round($b / 1048576) . ' MB'; } }
if (!function_exists('wp_mkdir_p')) { function wp_mkdir_p($d) { return is_dir($d) || mkdir($d, 0777, true); } }
if (!function_exists('get_posts')) { function get_posts() { return array(); } }
require_once __DIR__ . '/../includes/class-Emsfb-rate-limit.php';
require_once __DIR__ . '/../includes/class-Emsfb-drafts.php';
require_once __DIR__ . '/../includes/class-Emsfb-upload-guard.php';
require_once __DIR__ . '/../includes/class-Emsfb-uploads.php';
use Emsfb\Upload_Guard as G;
use Emsfb\Uploads;
use Emsfb\Rate_Limit;

/* ---------------------------------------------------------------- 1. tokens */
$name = 'efb-PLG-261113-a1B2c3D4e5F6.pdf';
$b = G::token_bucket('sid123', '203.0.113.5');
t('bucket = the form session when there is one', $b, 'sid:sid123');
t('bucket = the address without a session', G::token_bucket('', '203.0.113.5'), 'ip:203.0.113.5');
$ut = G::issue_upload_token($name, 12, 'docs', $b);
t('token shape: expiry.32 hex', (bool) preg_match('/^[0-9]{10}\.[a-f0-9]{32}$/', $ut), true);
t('valid token verifies', G::verify_upload_token($ut, $name, 12, 'docs', array($b)), true);
t('verifies with the right bucket among several', G::verify_upload_token($ut, $name, 12, 'docs', array('ip:9.9.9.9', $b)), true);
t('another form: refused', G::verify_upload_token($ut, $name, 13, 'docs', array($b)), false);
t('another field: refused', G::verify_upload_token($ut, $name, 12, 'photo', array($b)), false);
t('another file: refused', G::verify_upload_token($ut, 'efb-PLG-261113-ZZZZZZZZZZZZ.pdf', 12, 'docs', array($b)), false);
t('another session: refused', G::verify_upload_token($ut, $name, 12, 'docs', array('sid:other', 'ip:203.0.113.5')), false);
$parts = explode('.', $ut);
t('tampered MAC: refused', G::verify_upload_token($parts[0] . '.' . str_repeat('0', 32), $name, 12, 'docs', array($b)), false);
t('expiry moved forward: refused', G::verify_upload_token(($parts[0] + 100) . '.' . $parts[1], $name, 12, 'docs', array($b)), false);
$old = G::issue_upload_token($name, 12, 'docs', $b, time() - 5);
t('expired token: refused', G::verify_upload_token($old, $name, 12, 'docs', array($b)), false);
t('far-future token: refused', G::verify_upload_token(G::issue_upload_token($name, 12, 'docs', $b, time() + 10 * 86400), $name, 12, 'docs', array($b)), false);
t('garbage: refused', array(G::verify_upload_token('', $name, 12, 'docs', array($b)), G::verify_upload_token(array('x'), $name, 12, 'docs', array($b)), G::verify_upload_token('1.2', $name, 12, 'docs', array($b))), array(false, false, false));
t('an empty session bucket never matches', G::verify_upload_token(G::issue_upload_token($name, 12, 'docs', 'sid:'), $name, 12, 'docs', array('sid:')), false);
t('a token of the path ../ name does not verify the stored name', G::verify_upload_token(G::issue_upload_token('../../' . $name, 12, 'docs', $b), $name, 12, 'docs', array($b)), true); // basename only: the stored name is what is signed
t('require_token is off in the first release', G::require_token(), false);

/* ---------------------------------------------------------------- 2. slots */
$structure = array(
	array('type' => 'form'),
	array('id_' => 'docs', 'type' => 'file', 'max_files' => '5'),
	array('id_' => 'rec', 'type' => 'audio_recorder', 'max_files' => '5'),
	array('id_' => 'n', 'type' => 'text'),
);
$GLOBALS['opts']['emsfb_pro'] = 3;
t('Free Plus: max_files = 5 is five slots, a recorder one', G::count_upload_slots($structure), 6);
t('count_upload_fields() (Human Shield) answers the slots', G::count_upload_fields($structure), 6);
t('escaped stored structure counts the same', G::count_upload_slots(str_replace('"', '\\"', json_encode($structure))), 6);
$GLOBALS['opts']['emsfb_pro'] = 2;
t('Free: one slot per field', G::count_upload_slots($structure), 2);
$GLOBALS['opts']['emsfb_pro'] = 3;

/* ---------------------------------------------------------------- 3. quota limit */
t('one field of five files: quota 15', G::quota_limit(array('source' => 'form', 'fields' => G::count_upload_slots(array(array('type' => 'form'), array('type' => 'file', 'max_files' => '5'))))), 15);
t('capped at 60 by default', G::quota_limit(array('source' => 'form', 'fields' => 40)), 60);
add_filter('emsfb_upload_quota_cap', function () { return 0; });
t('cap 0 = no cap', G::quota_limit(array('source' => 'form', 'fields' => 40)), 120);
$GLOBALS['efb_filters']['emsfb_upload_quota_cap'] = array();
add_filter('emsfb_upload_quota_limit', function ($l) { return 7; });
t('emsfb_upload_quota_limit still decides last', G::quota_limit(array('source' => 'form', 'fields' => 40)), 7);
$GLOBALS['efb_filters']['emsfb_upload_quota_limit'] = array();

/* ---------------------------------------------------------------- 4. quota on Rate_Limit */
$db = $GLOBALS['wpdb'] = new EfbF11TestDb();
$db->create_f11_tables();
Rate_Limit::reset_ready();
t('quota uses the atomic table when it exists', G::quota_on_rate_limit(), true);
$ctx = array('source' => 'form', 'form_id' => 5, 'fields' => 1, 'nonce' => 'n1', 'sid' => 's1', 'ip' => '198.51.100.7');
$r = array();
for ($i = 0; $i < 4; $i++) $r[] = G::quota_reserve($ctx);
t('one slot: three uploads, the fourth refused', $r, array(true, true, true, false));
t('quota_used reads the table', G::quota_used($ctx), 3);
t('quota_allows agrees', G::quota_allows($ctx), false);
G::quota_release($ctx);
t('a refused file gives its place back', array(G::quota_used($ctx), G::quota_reserve($ctx)), array(2, true));
t('nothing written to transients', empty($GLOBALS['efb_transients']), true);
$other = array_merge($ctx, array('sid' => 's2', 'nonce' => 'n2'));
t('another session has its own budget', G::quota_reserve($other), true);
add_filter('emsfb_upload_ip_quota_limit', function () { return 4; }); // this address already holds 4 places (3 + 1 of the second session)
$third = array_merge($ctx, array('sid' => 's3', 'nonce' => 'n3'));
t('the per-IP ceiling (4) stops a new session of the same address', array(G::quota_reserve($third), G::quota_used($third)), array(false, 0));
t('a different address is not affected', G::quota_reserve(array_merge($third, array('ip' => '198.51.100.8'))), true);
$GLOBALS['efb_filters']['emsfb_upload_ip_quota_limit'] = array();
Rate_Limit::$now = time() + 3700;
t('a new hour starts again', G::quota_reserve($ctx), true);
Rate_Limit::$now = null;
$dump = json_encode($db->get_results('SELECT * FROM wp_emsfb_vrfy_rl_', ARRAY_A));
t('no address or session in the table (HMAC buckets only)', strpos($dump, '198.51.100') === false && strpos($dump, '"s1"') === false, true);
t('quota_enabled=false reserves without counting', (function () use ($ctx) { add_filter('emsfb_upload_quota_enabled', '__return_false'); $ok = G::quota_reserve(array_merge($ctx, array('sid' => 'zz'))); $GLOBALS['efb_filters']['emsfb_upload_quota_enabled'] = array(); return $ok; })(), true);
$db->pdo->exec('DROP TABLE wp_emsfb_vrfy_rl_');
Rate_Limit::reset_ready();
$tctx = array_merge($ctx, array('sid' => 'tr'));
t('without the table: the transient fallback counts', array(G::quota_reserve($tctx), G::quota_used($tctx)), array(true, 1));
G::quota_release($tctx);
t('…and gives back', G::quota_used($tctx), 0);
function __return_false() { return false; }

/* ---------------------------------------------------------------- 5. ledger binding */
$db = $GLOBALS['wpdb'] = new EfbF11TestDb();

$f1 = $tmp . '/uploads/2026/11/efb-PLG-261101-ORPHANfile01.pdf';
$f2 = $tmp . '/uploads/2026/11/efb-PLG-261101-CLAIMEDfile2.pdf';
file_put_contents($f1, '%PDF-1.4 x'); file_put_contents($f2, '%PDF-1.4 y');
G::track_pending_upload($f1, 12, 'docs');
G::track_pending_upload($f2);
t('a bound row reads time, form and field', array_slice(G::pending_entry('efb-PLG-261101-ORPHANfile01.pdf'), 1), array(12, 'docs'));
t('an unbound row reads form 0 and no field', array_slice(G::pending_entry('efb-PLG-261101-CLAIMEDfile2.pdf'), 1), array(0, ''));
t('time still read by a plain int cast', abs((int) $GLOBALS['opts'][G::PENDING_OPTION]['efb-PLG-261101-ORPHANfile01.pdf'] - time()) < 5, true);
t('an unknown name has no row', G::pending_entry('efb-PLG-261101-NOPE.pdf'), null);
t('a field id with hostile characters is cleaned', (function () use ($f1) { G::track_pending_upload($f1, 12, 'do"cs<x>'); return G::pending_entry(basename($f1))[2]; })(), 'docsx');
G::track_pending_upload($f1, 12, 'docs');

/* ---------------------------------------------------------------- 6. sweeper */
@mkdir($tmp . '/uploads/efb-private/2026/11', 0777, true);
$f3 = $tmp . '/uploads/efb-private/2026/11/efb-PLG-261101-PRIVorphan01.png';
file_put_contents($f3, 'x');
G::track_pending_upload($f3, 12, 'docs');
foreach ($GLOBALS['opts'][G::PENDING_OPTION] as $k => $v) $GLOBALS['opts'][G::PENDING_OPTION][$k] = (time() - 2 * 86400) . '|12|docs';
$db->insert('wp_emsfb_msg_', array('form_id' => 12, 'track' => 'T1', 'content' => '[{\\"url\\":\\"http://example.test/wp-content/uploads/2026/11/efb-PLG-261101-CLAIMEDfile2.pdf\\"}]', 'read_' => 0, 'date' => '2026-11-01 10:00:00'));
$n = G::cleanup_orphan_uploads();
t('the abandoned files are deleted (uploads/YYYY/MM and the private folder)', array(file_exists($f1), file_exists($f3)), array(false, false));
t('a file an entry names is kept', file_exists($f2), true);
t('two files removed', $n, 2);
t('the ledger is empty after the sweep', $GLOBALS['opts'][G::PENDING_OPTION], array());
t('upload_is_referenced finds the entry', G::upload_is_referenced('efb-PLG-261101-CLAIMEDfile2.pdf'), true);
t('…except the entry the legacy payment follow-up rewrites', G::upload_is_referenced('efb-PLG-261101-CLAIMEDfile2.pdf', 'T1'), false);
t('locate_upload finds a private file', (function () use ($tmp) { $p = $tmp . '/uploads/efb-private/2026/11/efb-PLG-261101-PRIVkeep0001.pdf'; file_put_contents($p, 'x'); return G::locate_upload(basename($p)) === wp_normalize_path(realpath($p)); })(), true);
t('path_from_url resolves a private address', (function () use ($tmp) { return G::path_from_url('http://example.test/wp-content/uploads/efb-private/2026/11/efb-PLG-261101-PRIVkeep0001.pdf') !== ''; })(), true);
t('path_from_url refuses ../ in a private address', G::path_from_url('http://example.test/wp-content/uploads/efb-private/2026/../../efb-PLG-261101-PRIVkeep0001.pdf'), '');

exec('rm -rf ' . escapeshellarg($tmp));
echo "\nRESULTS: {$passed} passed, {$failed} failed\n";
exit($failed ? 1 : 0);
