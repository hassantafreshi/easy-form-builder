<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
/**
 * Shared entry purge: Emsfb\Privacy::purge_entries() and
 * Emsfb\Upload_Guard::path_from_url() (F13 §6.3), and the dashboard delete
 * paths that must use them.
 *
 * Loads the real classes against WordPress stubs and an in-memory $wpdb with
 * emsfb_msg_ / emsfb_rsp_ rows. Uploaded files are real files in a temporary
 * uploads directory with the default YYYY/MM layout.
 *
 * Run: php tests/test-entries-purge.php
 */

$pass = 0;
$fail = 0;

function test($label, $actual, $expected) {
    global $pass, $fail;
    if ($actual === $expected) {
        $pass++;
        echo "[PASS] $label\n";
    } else {
        $fail++;
        echo "[FAIL] $label\n";
        echo "  Expected: " . var_export($expected, true) . "\n";
        echo "  Actual:   " . var_export($actual, true) . "\n";
    }
}

/* ---------------------------------------------------------------------------
 * WordPress surface.
 * ------------------------------------------------------------------------ */

define('ABSPATH', __DIR__ . '/');
define('HOUR_IN_SECONDS', 3600);
define('ARRAY_A', 'ARRAY_A');

$GLOBALS['efb_options']    = array();
$GLOBALS['efb_transients'] = array();
$GLOBALS['efb_actions']    = array();

function add_filter($hook, $cb, $p = 10, $a = 1) {}
function apply_filters($hook, $value) { return $value; }
function add_action($hook, $cb, $p = 10, $a = 1) {}
function do_action($hook) { $GLOBALS['efb_actions'][] = func_get_args(); }
function esc_html__($t, $d = '') { return $t; }
function _n($s, $p, $n, $d = '') { return $n === 1 ? $s : $p; }
function wp_basename($path) { return basename(str_replace('\\', '/', $path)); }
function absint($v) { return abs((int) $v); }
function wp_normalize_path($p) { return str_replace('\\', '/', $p); }
function trailingslashit($p) { return rtrim($p, '/\\') . '/'; }
function get_transient($k) { return $GLOBALS['efb_transients'][$k] ?? false; }
function set_transient($k, $v, $t = 0) { $GLOBALS['efb_transients'][$k] = $v; return true; }
function delete_transient($k) { unset($GLOBALS['efb_transients'][$k]); return true; }
function get_option($k, $d = false) { return $GLOBALS['efb_options'][$k] ?? $d; }
function update_option($k, $v, $a = null) { $GLOBALS['efb_options'][$k] = $v; return true; }
function delete_option($k) { unset($GLOBALS['efb_options'][$k]); return true; }
function emsfb_is_php_function_available_efb($n) { return function_exists($n); }
function wp_delete_file($path) { if (is_file($path)) unlink($path); }

$tmp = sys_get_temp_dir() . '/efb-purge-' . getmypid();
$GLOBALS['efb_upload_dir'] = array(
    'basedir' => $tmp . '/wp-content/uploads',
    'baseurl' => 'http://example.test/wp-content/uploads',
    'error'   => false,
);
function wp_upload_dir($time = null, $create = true) { return $GLOBALS['efb_upload_dir']; }

/**
 * In-memory $wpdb for the statements Privacy and Upload_Guard issue.
 */
class Fake_WPDB {
    public $prefix = 'wp_';
    public $tables = array('wp_emsfb_msg_' => array(), 'wp_emsfb_rsp_' => array());
    public $queries = array();
    public $fail_delete_on = null;

    public function esc_like($t) { return addcslashes($t, '_%\\'); }

    public function prepare($sql, ...$args) {
        if (count($args) === 1 && is_array($args[0])) $args = $args[0];
        $i = 0;
        return preg_replace_callback('/%[ds]/', function ($m) use (&$i, $args) {
            $v = $args[$i++];
            return $m[0] === '%d' ? (string) (int) $v : "'" . addslashes((string) $v) . "'";
        }, $sql);
    }

    private function ids($sql) {
        preg_match('/msg_id IN \(([^)]*)\)/', $sql, $m);
        return array_map('intval', explode(',', $m[1]));
    }

    public function get_col($sql) {
        $this->queries[] = $sql;
        if (preg_match('/SELECT content FROM `(\w+)` WHERE msg_id IN/', $sql, $m)) {
            $ids = $this->ids($sql);
            $out = array();
            foreach ($this->tables[$m[1]] as $row) if (in_array($row['msg_id'], $ids, true)) $out[] = $row['content'];
            return $out;
        }
        if (preg_match('/SELECT msg_id FROM `(\w+)` WHERE form_id = (\d+)/', $sql, $m)) {
            $out = array();
            foreach ($this->tables[$m[1]] as $row) if ($row['form_id'] === (int) $m[2]) $out[] = (string) $row['msg_id'];
            return $out;
        }
        throw new Exception("unexpected get_col: $sql");
    }

    public function query($sql) {
        $this->queries[] = $sql;
        if (preg_match('/DELETE FROM `(\w+)` WHERE msg_id IN/', $sql, $m)) {
            if ($this->fail_delete_on === $m[1]) return false;
            $ids = $this->ids($sql);
            $before = count($this->tables[$m[1]]);
            $this->tables[$m[1]] = array_values(array_filter($this->tables[$m[1]], function ($r) use ($ids) {
                return !in_array($r['msg_id'], $ids, true);
            }));
            return $before - count($this->tables[$m[1]]);
        }
        throw new Exception("unexpected query: $sql");
    }

    public function get_var($sql) {
        $this->queries[] = $sql;
        if (preg_match("/SELECT COUNT\\(1\\) FROM `(\\w+)` WHERE content LIKE '(.*)'$/", $sql, $m)) {
            $needle = stripcslashes(trim(stripslashes($m[2]), '%'));
            $n = 0;
            foreach ($this->tables[$m[1]] as $row) if (strpos($row['content'], $needle) !== false) $n++;
            return (string) $n;
        }
        throw new Exception("unexpected get_var: $sql");
    }
}

$GLOBALS['wpdb'] = $wpdb = new Fake_WPDB();

require_once __DIR__ . '/../includes/json-efb.php';
require_once __DIR__ . '/../includes/class-Emsfb-upload-guard.php';
require_once __DIR__ . '/../includes/class-Emsfb-privacy.php';

use Emsfb\Privacy;
use Emsfb\Upload_Guard;

/* ---------------------------------------------------------------------------
 * Fixtures.
 * ------------------------------------------------------------------------ */

function make_file($rel) {
    $path = $GLOBALS['efb_upload_dir']['basedir'] . '/' . $rel;
    if (!is_dir(dirname($path))) mkdir(dirname($path), 0777, true);
    file_put_contents($path, 'x');
    return $path;
}
function url_of($rel, $scheme = 'https') {
    return $scheme . '://example.test/wp-content/uploads/' . $rel;
}
function entry_json(array $rows) { return json_encode($rows); } // escapes "/" as "\/", as stored
function msg($id, $form, $content) { return array('msg_id' => $id, 'form_id' => $form, 'content' => $content); }
function rsp($msg_id, $content) { return array('msg_id' => $msg_id, 'content' => $content); }
function reset_db() {
    global $wpdb;
    $wpdb->tables = array('wp_emsfb_msg_' => array(), 'wp_emsfb_rsp_' => array());
    $wpdb->queries = array();
    $wpdb->fail_delete_on = null;
    $GLOBALS['efb_actions'] = array();
}

/* ---------------------------------------------------------------------------
 * 1. path_from_url
 * ------------------------------------------------------------------------ */
echo "--- 1. Upload_Guard::path_from_url ---\n";
$a = make_file('2026/02/efb-PLG-260201-AAAA1111.jpg');
$real_a = wp_normalize_path(realpath($a));
test('dated subfolder resolves (the sweeper bug: basename-only paths miss it)', Upload_Guard::path_from_url(url_of('2026/02/efb-PLG-260201-AAAA1111.jpg')), $real_a);
test('https address on an http baseurl still resolves', Upload_Guard::path_from_url(url_of('2026/02/efb-PLG-260201-AAAA1111.jpg', 'https')), $real_a);
test('JSON-escaped slashes resolve', Upload_Guard::path_from_url(str_replace('/', '\\/', url_of('2026/02/efb-PLG-260201-AAAA1111.jpg'))), $real_a);
test('query string is ignored', Upload_Guard::path_from_url(url_of('2026/02/efb-PLG-260201-AAAA1111.jpg') . '?ver=1'), $real_a);
make_file('2026/02/photo.jpg');
test('a media library file (foreign name) is refused', Upload_Guard::path_from_url(url_of('2026/02/photo.jpg')), false);
test('another host is refused', Upload_Guard::path_from_url('https://evil.test/wp-content/uploads/2026/02/efb-PLG-260201-AAAA1111.jpg'), false);
$outside = $tmp . '/wp-content/efb-PLG-260201-OUTSIDE1.jpg';
file_put_contents($outside, 'x');
test('"../" out of uploads is refused', Upload_Guard::path_from_url(url_of('../efb-PLG-260201-OUTSIDE1.jpg')), false);
test('encoded "../" out of uploads is refused', Upload_Guard::path_from_url(url_of('%2e%2e/efb-PLG-260201-OUTSIDE1.jpg')), false);
test('a missing file is refused', Upload_Guard::path_from_url(url_of('2026/02/efb-PLG-260201-MISSING1.jpg')), false);
test('empty / non-string input is refused', array(Upload_Guard::path_from_url(''), Upload_Guard::path_from_url(null)), array(false, false));

/* ---------------------------------------------------------------------------
 * 2. Single entry: rows, replies and files go together.
 * ------------------------------------------------------------------------ */
echo "\n--- 2. purge_entries: one entry ---\n";
reset_db();
$entry_file = make_file('2026/02/efb-PLG-260210-ENTRY001.pdf');
$reply_file = make_file('2026/03/efb-PLG-260301-REPLY001.png');
$rec_file   = make_file('2026/03/efb-rec-260301-abcdefABCDEF.webm');
$keep_file  = make_file('2026/03/efb-PLG-260301-OTHER001.png');
$wpdb->tables['wp_emsfb_msg_'] = array(
    msg(10, 1, entry_json(array(array('id_' => 'f1', 'type' => 'file', 'value' => '@file@', 'url' => url_of('2026/02/efb-PLG-260210-ENTRY001.pdf'))))),
    msg(11, 1, entry_json(array(array('id_' => 'f1', 'type' => 'file', 'value' => '@file@', 'url' => url_of('2026/03/efb-PLG-260301-OTHER001.png'))))),
);
$wpdb->tables['wp_emsfb_rsp_'] = array(
    rsp(10, entry_json(array(array('id_' => 'message', 'type' => 'text', 'value' => 'see file'), array('id_' => 'a', 'type' => 'file', 'url' => url_of('2026/03/efb-PLG-260301-REPLY001.png'))))),
    rsp(10, entry_json(array(array('id_' => 'r', 'type' => 'audio_recorder', 'url' => url_of('2026/03/efb-rec-260301-abcdefABCDEF.webm'))))),
    rsp(11, entry_json(array(array('id_' => 'message', 'type' => 'text', 'value' => 'keep me')))),
);
$r = Privacy::purge_entries(array(10));
test('result counts', $r, array('deleted' => 1, 'anonymized' => 0, 'files' => 3));
test('entry row removed, the other entry kept', array_column($wpdb->tables['wp_emsfb_msg_'], 'msg_id'), array(11));
test('its replies removed (no orphan rsp rows), other replies kept', array_column($wpdb->tables['wp_emsfb_rsp_'], 'msg_id'), array(11));
test('entry file deleted from uploads/2026/02', file_exists($entry_file), false);
test('reply attachment deleted', file_exists($reply_file), false);
test('recorder file deleted', file_exists($rec_file), false);
test('another entry\'s file kept', file_exists($keep_file), true);
test('efb_entries_purged fired with the ids and mode', $GLOBALS['efb_actions'], array(array('efb_entries_purged', array(10), 'delete')));
$deletes = array_values(array_filter($wpdb->queries, function ($q) { return strpos($q, 'DELETE') === 0; }));
test('deletes are prepared IN() statements', $deletes, array('DELETE FROM `wp_emsfb_msg_` WHERE msg_id IN (10)', 'DELETE FROM `wp_emsfb_rsp_` WHERE msg_id IN (10)'));

/* ---------------------------------------------------------------------------
 * 3. Shared files and unsafe references.
 * ------------------------------------------------------------------------ */
echo "\n--- 3. shared and unsafe references ---\n";
reset_db();
$shared = make_file('2026/04/efb-PLG-260401-SHARED01.jpg');
$media  = make_file('2026/04/efb-logo.png');
$wpdb->tables['wp_emsfb_msg_'] = array(
    msg(20, 2, entry_json(array(
        array('id_' => 'f', 'type' => 'file', 'url' => url_of('2026/04/efb-PLG-260401-SHARED01.jpg')),
        array('id_' => 'u', 'type' => 'url', 'value' => url_of('2026/04/efb-logo.png')),
        array('id_' => 'x', 'type' => 'file', 'url' => 'https://evil.test/wp-content/uploads/2026/02/efb-PLG-260201-AAAA1111.jpg'),
        array('id_' => 'y', 'type' => 'file', 'url' => url_of('../efb-PLG-260201-OUTSIDE1.jpg')),
    ))),
    msg(21, 2, entry_json(array(array('id_' => 'f', 'type' => 'file', 'url' => url_of('2026/04/efb-PLG-260401-SHARED01.jpg'))))),
);
$r = Privacy::purge_entries(array(20));
test('nothing reported as removed', $r['files'], 0);
test('a file another entry still uses is kept', file_exists($shared), true);
test('a non-plugin name in uploads is never touched', file_exists($media), true);
test('a same-named file referenced through another host is kept', file_exists($a), true);
test('a file outside uploads is kept', file_exists($outside), true);
$r = Privacy::purge_entries(array(21));
test('once the last reference goes, the shared file goes', array($r['files'], file_exists($shared)), array(1, false));

/* ---------------------------------------------------------------------------
 * 4. Bulk, batching, options and failures.
 * ------------------------------------------------------------------------ */
echo "\n--- 4. bulk and options ---\n";
reset_db();
for ($i = 1; $i <= 250; $i++) {
    $wpdb->tables['wp_emsfb_msg_'][] = msg($i, 3, entry_json(array(array('id_' => 't', 'type' => 'text', 'value' => "v$i"))));
    $wpdb->tables['wp_emsfb_rsp_'][] = rsp($i, entry_json(array(array('id_' => 'message', 'type' => 'text', 'value' => 'r'))));
}
$r = Privacy::purge_entries(array_merge(range(1, 250), array(0, -5, '7', 7)));
test('250 entries deleted (bad and duplicate ids ignored)', $r['deleted'], 250);
test('all replies deleted', count($wpdb->tables['wp_emsfb_rsp_']), 0);
$msg_deletes = array_filter($wpdb->queries, function ($q) { return strpos($q, 'DELETE FROM `wp_emsfb_msg_`') === 0; });
test('processed in batches of 100 (3 statements)', count($msg_deletes), 3);

reset_db();
$kept = make_file('2026/05/efb-PLG-260501-NOFILES1.jpg');
$wpdb->tables['wp_emsfb_msg_'] = array(msg(30, 4, entry_json(array(array('id_' => 'f', 'type' => 'file', 'url' => url_of('2026/05/efb-PLG-260501-NOFILES1.jpg'))))));
$r = Privacy::purge_entries(array(30), 'delete', false);
test('$with_files = false removes the row but keeps the file', array($r['deleted'], $r['files'], file_exists($kept)), array(1, 0, true));

reset_db();
$wpdb->tables['wp_emsfb_msg_'] = array(msg(31, 4, entry_json(array(array('id_' => 'f', 'type' => 'file', 'url' => url_of('2026/05/efb-PLG-260501-NOFILES1.jpg'))))));
$r = Privacy::purge_entries(array(31), 'anonymize');
test('anonymize (not built yet) changes nothing', array($r, count($wpdb->tables['wp_emsfb_msg_']), file_exists($kept), $GLOBALS['efb_actions']), array(array('deleted' => 0, 'anonymized' => 0, 'files' => 0), 1, true, array()));

reset_db();
$wpdb->tables['wp_emsfb_msg_'] = array(msg(32, 4, entry_json(array(array('id_' => 'f', 'type' => 'file', 'url' => url_of('2026/05/efb-PLG-260501-NOFILES1.jpg'))))));
$wpdb->fail_delete_on = 'wp_emsfb_msg_';
$r = Privacy::purge_entries(array(32));
test('a failed row delete keeps the row\'s files and fires no action', array($r['deleted'], file_exists($kept), $GLOBALS['efb_actions']), array(0, true, array()));

reset_db();
$plain = make_file('efb-PLG-260601-FLAT0001.txt');
$wpdb->tables['wp_emsfb_msg_'] = array(msg(33, 4, '[{"id_":"t","type":"text","value":"uploaded efb-PLG-260601-FLAT0001.txt"}]'));
$r = Privacy::purge_entries(array(33));
test('a bare name (no address) is resolved in the flat uploads layout', array($r['files'], file_exists($plain)), array(1, false));

test('empty id list is a no-op', Privacy::purge_entries(array()), array('deleted' => 0, 'anonymized' => 0, 'files' => 0));

/* ---------------------------------------------------------------------------
 * 5. Every dashboard delete path uses the purge.
 * ------------------------------------------------------------------------ */
echo "\n--- 5. delete paths are wired to the purge ---\n";
$admin = file_get_contents(__DIR__ . '/../includes/admin/class-Emsfb-admin.php');
function method_body($src, $name) {
    if (!preg_match('/function ' . preg_quote($name, '/') . '\s*\([^)]*\)\s*\{(.*?)\n    \}\n/s', $src, $m)) return '';
    return $m[1];
}
// Direct deletes left in each handler: only the form row itself may remain.
$expected_direct = array('delete_form_id_public' => 1, 'delete_message_id_public' => 0, 'delete_messages_Emsfb' => 0);
foreach ($expected_direct as $fn => $allowed) {
    $body = method_body($admin, $fn);
    test("$fn calls purge_entries_efb()", strpos($body, 'purge_entries_efb(') !== false, true);
    test("$fn has no own DELETE of entries/replies", substr_count($body, '->delete(') + substr_count($body, 'DELETE FROM'), $allowed);
}
test('purge_entries_efb delegates to Privacy::purge_entries with files', strpos(method_body($admin, 'purge_entries_efb'), "Privacy::purge_entries(\$msg_ids, 'delete', true)") !== false, true);
$core = file_get_contents(__DIR__ . '/../includes/class-Emsfb.php');
test('Privacy is loaded with the core files', strpos($core, "require_plugin_file_efb('includes/class-Emsfb-privacy.php')") !== false, true);

/* ---------------------------------------------------------------------------
 * Cleanup.
 * ------------------------------------------------------------------------ */
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($tmp, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
foreach ($it as $f) { $f->isDir() ? rmdir($f->getPathname()) : unlink($f->getPathname()); }
rmdir($tmp);

echo "\n========================================\n";
echo "RESULTS: $pass passed, $fail failed\n";
echo "========================================\n";
exit($fail > 0 ? 1 : 0);
