<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
/**
 * Orphan upload sweeper finds files in uploads/yyyy/mm/ and deletes them safely.
 *
 * Bug (BUGS-FOUND.fa.md, "پیشخوان و مدیریت پاسخ‌ها", F07 §1 row 17, F13 §19):
 * Upload_Guard::cleanup_orphan_uploads() built the path as
 * basedir + basename, but wp_upload_bits() stores the file under the
 * year/month subfolder, so realpath() failed and nothing was ever deleted.
 *
 * Loads the real Upload_Guard against WordPress stubs, with real files in a
 * temporary uploads folder and an in-memory $wpdb for the "still referenced"
 * check.
 *
 * Run: php tests/test-upload-orphan-cleanup.php
 */

$pass = 0;
$fail = 0;
function test($label, $actual, $expected) {
    global $pass, $fail;
    if ($actual === $expected) { $pass++; echo "[PASS] $label\n"; return; }
    $fail++;
    echo "[FAIL] $label\n  Expected: " . var_export($expected, true) . "\n  Actual:   " . var_export($actual, true) . "\n";
}

define('ABSPATH', __DIR__ . '/');
define('HOUR_IN_SECONDS', 3600);

$GLOBALS['efb_options'] = array();
$GLOBALS['efb_filters'] = array();
function add_filter($hook, $cb) { $GLOBALS['efb_filters'][$hook][] = $cb; }
function apply_filters($hook, $value) {
    foreach ($GLOBALS['efb_filters'][$hook] ?? array() as $cb) $value = $cb($value);
    return $value;
}
function add_action() {}
function esc_html__($t) { return $t; }
function wp_basename($p) { return basename(str_replace('\\', '/', (string) $p)); }
function wp_normalize_path($p) { return str_replace('\\', '/', (string) $p); }
function trailingslashit($p) { return rtrim($p, '/\\') . '/'; }
function get_option($k, $d = false) { return array_key_exists($k, $GLOBALS['efb_options']) ? $GLOBALS['efb_options'][$k] : $d; }
function update_option($k, $v, $a = null) { $GLOBALS['efb_options'][$k] = $v; return true; }
function delete_option($k) { unset($GLOBALS['efb_options'][$k]); return true; }
function delete_transient($k) { return true; }
function maybe_unserialize($v) { return is_string($v) && @unserialize($v) !== false ? unserialize($v) : $v; }
function wp_delete_file($f) { $GLOBALS['efb_deleted'][] = $f; @unlink($f); }
function wp_upload_dir($time = null, $create = true) {
    return array('basedir' => $GLOBALS['efb_uploads'], 'baseurl' => 'https://site.test/wp-content/uploads', 'error' => false);
}

/* $wpdb: the stored content of msg/rsp rows, searched with LIKE. */
class Efb_Test_Wpdb {
    public $prefix = 'wp_';
    public $options = 'wp_options';
    public $msg = array();
    public $rsp = array();
    public function esc_like($t) { return addcslashes($t, '_%\\'); }
    public function prepare($q, ...$args) { return array($q, $args); }
    public function get_results($p) { return array(); }
    public function get_var($p) {
        list($q, $args) = $p;
        $needle = stripcslashes(trim($args[0], '%'));
        $rows = strpos($q, 'emsfb_msg_') !== false ? $this->msg : $this->rsp;
        $n = 0;
        foreach ($rows as $content) if (strpos($content, $needle) !== false) $n++;
        return $n;
    }
}
$GLOBALS['wpdb'] = new Efb_Test_Wpdb();

require_once __DIR__ . '/../includes/class-Emsfb-upload-guard.php';
use Emsfb\Upload_Guard;

/* ---- temporary uploads folder ------------------------------------------ */
$root = sys_get_temp_dir() . '/efb-orphan-' . bin2hex(random_bytes(4));
$GLOBALS['efb_uploads'] = $root . '/uploads';
@mkdir($root . '/uploads/2026/02', 0777, true);
@mkdir($root . '/uploads/2026/11', 0777, true);
@mkdir($root . '/outside', 0777, true);
function put($rel) { $p = $GLOBALS['efb_uploads'] . '/' . $rel; file_put_contents($p, 'x'); return $p; }
function ledger() { return get_option(Upload_Guard::PENDING_OPTION, array()); }

echo "--- 1. LEDGER: the stored path is recorded ---\n";
$a = put('2026/11/efb-PLG-261111-AbCdEf123456.pdf');
Upload_Guard::track_pending_upload($a);
$row = ledger()['efb-PLG-261111-AbCdEf123456.pdf'] ?? null;
test('ledger row is keyed by file name', is_array($row), true);
test('ledger row keeps the path inside uploads', $row['p'] ?? '', '2026/11/efb-PLG-261111-AbCdEf123456.pdf');
test('ledger row keeps the time', is_int($row['t'] ?? null), true);
test('a claimed file is released by URL, as before',
    (Upload_Guard::release_pending_uploads(array('https://site.test/wp-content/uploads/2026/11/efb-PLG-261111-AbCdEf123456.pdf')) || true) && !isset(ledger()['efb-PLG-261111-AbCdEf123456.pdf']), true);
@unlink($a);

echo "\n--- 2. SWEEP: abandoned files in yyyy/mm are deleted ---\n";
$GLOBALS['efb_options'] = array();
$old = time() - 2 * 86400;
$orphan_new    = put('2026/11/efb-PLG-261111-NEWROW000001.pdf');   // new-format ledger row
$orphan_legacy = put('2026/02/efb-PLG-260203-LEGACY000001.png');   // old row: bare timestamp, no path
$orphan_misdated = put('2026/02/efb-rec-261111-MOVEDFOLDER1.webm'); // name date != folder: found by glob
$claimed       = put('2026/11/efb-PLG-261111-CLAIMED00001.pdf');
$fresh         = put('2026/11/efb-PLG-261111-FRESH0000001.pdf');
$media         = put('2026/11/holiday-photo.jpg');
$root_file     = put('efb-PLG-261111-ROOTFOLDER01.pdf');            // uploads_use_yearmonth_folders off
$outside       = $root . '/outside/efb-PLG-261111-OUTSIDE00001.pdf';
file_put_contents($outside, 'x');
$link_ok = @symlink($outside, $GLOBALS['efb_uploads'] . '/2026/11/efb-PLG-261111-SYMLINK00001.pdf');

$GLOBALS['wpdb']->msg[] = '[{"id_":"f1","type":"file","value":"@file@","url":"https:\/\/site.test\/wp-content\/uploads\/2026\/11\/efb-PLG-261111-CLAIMED00001.pdf"}]';

$GLOBALS['efb_options'][Upload_Guard::PENDING_OPTION] = array(
    'efb-PLG-261111-NEWROW000001.pdf'  => array('t' => $old, 'p' => '2026/11/efb-PLG-261111-NEWROW000001.pdf'),
    'efb-PLG-260203-LEGACY000001.png'  => $old,
    'efb-rec-261111-MOVEDFOLDER1.webm' => $old,
    'efb-PLG-261111-CLAIMED00001.pdf'  => array('t' => $old, 'p' => '2026/11/efb-PLG-261111-CLAIMED00001.pdf'),
    'efb-PLG-261111-FRESH0000001.pdf'  => array('t' => time(), 'p' => '2026/11/efb-PLG-261111-FRESH0000001.pdf'),
    'holiday-photo.jpg'                => $old,
    'efb-PLG-261111-ROOTFOLDER01.pdf'  => $old,
    'efb-PLG-261111-SYMLINK00001.pdf'  => array('t' => $old, 'p' => '2026/11/efb-PLG-261111-SYMLINK00001.pdf'),
    'efb-PLG-261111-TRAVERSAL001.pdf'  => array('t' => $old, 'p' => '../outside/efb-PLG-261111-TRAVERSAL001.pdf'),
);
file_put_contents($root . '/outside/efb-PLG-261111-TRAVERSAL001.pdf', 'x');

$deleted = Upload_Guard::cleanup_orphan_uploads();
test('abandoned file in uploads/2026/11 (path in ledger) is deleted', file_exists($orphan_new), false);
test('abandoned file from an old ledger row (no path) is found in 2026/02 and deleted', file_exists($orphan_legacy), false);
test('a file whose folder differs from its name date is found by the bounded glob', file_exists($orphan_misdated), false);
test('a file in the uploads root is still handled', file_exists($root_file), false);
test('a file a stored entry points at is kept', file_exists($claimed), true);
test('a file inside the grace period is kept', file_exists($fresh), true);
test('site media (not a plugin name) is never deleted', file_exists($media), true);
if ($link_ok) {
    test('a symlink out of the uploads folder is not followed', file_exists($outside), true);
} else {
    echo "[SKIP] symlink not supported here\n";
}
test('a "../" path in the ledger never reaches outside uploads', file_exists($root . '/outside/efb-PLG-261111-TRAVERSAL001.pdf'), true);
test('cleanup reports the number of deleted files', $deleted, 4);
test('expired rows leave the ledger; the fresh one stays', array_keys(ledger()), array('efb-PLG-261111-FRESH0000001.pdf'));
test('a second run deletes nothing', Upload_Guard::cleanup_orphan_uploads(), 0);

echo "\n--- 3. path_from_url (F13 §6.3) ---\n";
test('URL in yyyy/mm resolves to the real file', Upload_Guard::path_from_url('https://site.test/wp-content/uploads/2026/11/efb-PLG-261111-CLAIMED00001.pdf'), wp_normalize_path(realpath($claimed)));
test('http and https of the uploads URL are the same file', Upload_Guard::path_from_url('http://site.test/wp-content/uploads/2026/11/efb-PLG-261111-CLAIMED00001.pdf'), wp_normalize_path(realpath($claimed)));
test('site media URL is refused', Upload_Guard::path_from_url('https://site.test/wp-content/uploads/2026/11/holiday-photo.jpg'), false);
test('another host is refused', Upload_Guard::path_from_url('https://evil.test/wp-content/uploads/2026/11/efb-PLG-261111-CLAIMED00001.pdf'), false);
test('"../" in the URL is refused', Upload_Guard::path_from_url('https://site.test/wp-content/uploads/2026/../../outside/efb-PLG-261111-OUTSIDE00001.pdf'), false);
test('a missing file is refused', Upload_Guard::path_from_url('https://site.test/wp-content/uploads/2026/11/efb-PLG-261111-NOTHERE00001.pdf'), false);

/* ---- cleanup ------------------------------------------------------------ */
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
foreach ($it as $f) { $f->isDir() && !$f->isLink() ? @rmdir($f->getPathname()) : @unlink($f->getPathname()); }
@rmdir($root);

echo "\n========================================\n";
echo "RESULTS: $pass passed, $fail failed\n";
echo "========================================\n";
exit($fail > 0 ? 1 : 0);
