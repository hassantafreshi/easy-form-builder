<?php
/**
 * F13 sub-task D: Upload_Guard::path_from_url() / locate_upload() / delete_unreferenced_upload().
 *
 * A stored URL is turned into a file only when it is the site's upload URL (either scheme or
 * protocol-relative), stays inside the uploads folder after realpath() (no ../, no symlink out),
 * and names a file this plugin generated (efb-PLG-… / efb-rec-…). The year/month folder of the
 * URL is kept; locate_upload() finds a name in the year/month folders when only the name is known.
 *
 * Run: php tests/test-upload-path-from-url.php
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
function get_option($k, $d = false) { return array_key_exists($k, $GLOBALS['opts']) ? $GLOBALS['opts'][$k] : $d; }
function update_option($k, $v, $a = null) { $GLOBALS['opts'][$k] = $v; return true; }
function apply_filters($h, $v) { return $v; }
function add_action() {}
require __DIR__ . '/privacy-test-db.php';
require __DIR__ . '/../includes/class-Emsfb-upload-guard.php';
use Emsfb\Upload_Guard;

$tmp = sys_get_temp_dir() . '/efb-upu-' . getmypid();
@mkdir($tmp . '/uploads/2026/02', 0777, true);
@mkdir($tmp . '/uploads/2025/12', 0777, true);
@mkdir($tmp . '/secret', 0777, true);
register_shutdown_function(function () use ($tmp) { exec('rm -rf ' . escapeshellarg($tmp)); });
efb_privacy_test_wp_stubs($tmp . '/uploads', 'https://example.test/wp-content/uploads');
$real = function ($p) { return wp_normalize_path(realpath($p)); };
$a = $tmp . '/uploads/2026/02/efb-PLG-260201-AAAA1111.pdf'; file_put_contents($a, 'x');
$b = $tmp . '/uploads/efb-PLG-250101-FLAT2222.pdf'; file_put_contents($b, 'x');
$c = $tmp . '/uploads/2025/12/efb-rec-251201-abcDEF123456.webm'; file_put_contents($c, 'x');
$d = $tmp . '/uploads/2026/02/efb-PLG-260201-DUPE3333-1.pdf'; file_put_contents($d, 'x');
$s = $tmp . '/secret/efb-PLG-260201-SECR4444.pdf'; file_put_contents($s, 'x');
$f = $tmp . '/uploads/2026/02/report.pdf'; file_put_contents($f, 'x');
@symlink($s, $tmp . '/uploads/2026/02/efb-PLG-260201-LINK5555.pdf');

$U = 'https://example.test/wp-content/uploads/';
t('https URL with year/month', Upload_Guard::path_from_url($U . '2026/02/efb-PLG-260201-AAAA1111.pdf'), $real($a));
t('http URL of the same site (scheme differs)', Upload_Guard::path_from_url('http://example.test/wp-content/uploads/2026/02/efb-PLG-260201-AAAA1111.pdf'), $real($a));
t('protocol-relative URL', Upload_Guard::path_from_url('//example.test/wp-content/uploads/2026/02/efb-PLG-260201-AAAA1111.pdf'), $real($a));
t('query string ignored', Upload_Guard::path_from_url($U . '2026/02/efb-PLG-260201-AAAA1111.pdf?ver=2'), $real($a));
t('file directly in uploads (no year/month)', Upload_Guard::path_from_url($U . 'efb-PLG-250101-FLAT2222.pdf'), $real($b));
t('recording name (efb-rec-)', Upload_Guard::path_from_url($U . '2025/12/efb-rec-251201-abcDEF123456.webm'), $real($c));
t('WordPress "-1" duplicate suffix', Upload_Guard::path_from_url($U . '2026/02/efb-PLG-260201-DUPE3333-1.pdf'), $real($d));
t('another host never resolves', Upload_Guard::path_from_url('https://evil.test/wp-content/uploads/2026/02/efb-PLG-260201-AAAA1111.pdf'), '');
t('../ out of uploads never resolves', Upload_Guard::path_from_url($U . '../secret/efb-PLG-260201-SECR4444.pdf'), '');
t('encoded ../ never resolves', Upload_Guard::path_from_url($U . '%2e%2e/secret/efb-PLG-260201-SECR4444.pdf'), '');
t('symlink pointing outside uploads never resolves', Upload_Guard::path_from_url($U . '2026/02/efb-PLG-260201-LINK5555.pdf'), '');
t('a name the plugin did not generate never resolves', Upload_Guard::path_from_url($U . '2026/02/report.pdf'), '');
t('missing file', Upload_Guard::path_from_url($U . '2026/02/efb-PLG-260201-NONE0000.pdf'), '');
t('empty URL', Upload_Guard::path_from_url(''), '');
t('locate_upload finds a name in a year/month folder', Upload_Guard::locate_upload('efb-PLG-260201-AAAA1111.pdf'), $real($a));
t('locate_upload finds a flat file', Upload_Guard::locate_upload('efb-PLG-250101-FLAT2222.pdf'), $real($b));
t('locate_upload refuses glob characters', Upload_Guard::locate_upload('efb-PLG-*-AAAA1111.pdf'), '');
t('locate_upload refuses a foreign name', Upload_Guard::locate_upload('report.pdf'), '');

$GLOBALS['wpdb'] = new EfbPrivacyTestDb();
$GLOBALS['wpdb']->add_entry(1, array(array('id_' => 'f', 'type' => 'file', 'value' => '@file@', 'url' => $U . '2026/02/efb-PLG-260201-AAAA1111.pdf')), date('Y-m-d H:i:s'));
t('delete_unreferenced_upload keeps a file an entry names', array(Upload_Guard::delete_unreferenced_upload('efb-PLG-260201-AAAA1111.pdf', $U . '2026/02/efb-PLG-260201-AAAA1111.pdf'), file_exists($a)), array(false, true));
$GLOBALS['wpdb']->query('DELETE FROM wp_emsfb_msg_');
t('...and deletes it once nothing does', array(Upload_Guard::delete_unreferenced_upload('efb-PLG-260201-AAAA1111.pdf', $U . '2026/02/efb-PLG-260201-AAAA1111.pdf'), file_exists($a)), array(true, false));
t('by name only (URL of another host): found and deleted in its folder', array(Upload_Guard::delete_unreferenced_upload('efb-rec-251201-abcDEF123456.webm', 'https://cdn.example.net/efb-rec-251201-abcDEF123456.webm'), file_exists($c)), array(true, false));
t('the file behind a symlink outside uploads is never deleted', array(Upload_Guard::delete_unreferenced_upload('efb-PLG-260201-LINK5555.pdf', $U . '2026/02/efb-PLG-260201-LINK5555.pdf'), file_exists($s)), array(false, true));
t('a foreign name is never deleted', array(Upload_Guard::delete_unreferenced_upload('report.pdf', $U . '2026/02/report.pdf'), file_exists($f)), array(false, true));

echo "\nRESULTS: {$passed} passed, {$failed} failed\n";
exit($failed ? 1 : 0);
