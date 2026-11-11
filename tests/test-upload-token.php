<?php
/**
 * Submitted files must belong to this visitor's upload for this form and field.
 *
 * Bug (BUGS-FOUND.fa.md, "امنیت و حریم خصوصی"; F07 §1 row 13, §7.1):
 * validate_submitted_upload_url_efb() only checked that a URL was inside the
 * uploads folder and of an allowed type, so any existing file - another
 * visitor's upload, a site media item - could be submitted as an attachment.
 *
 * Fix (F07 §7.1): the upload route returns a signed token "ut" bound to the
 * file, form, field and live session (sid of forms/session/prepare, or the IP
 * when there is none). The submit path accepts a file only with a valid token,
 * or - for pages cached before this release, while emsfb_upload_require_token
 * is off - when the file is still in the pending ledger (recent and unclaimed).
 *
 * Part 1 drives Upload_Guard directly. Part 2 runs the real
 * validate_submitted_upload_url_efb() and its helpers, cut out of
 * class-Emsfb-public.php, against real files in a temporary uploads folder.
 * Part 3 checks the wiring in the upload route, the submit path and the client.
 *
 * Run: php tests/test-upload-token.php
 */

namespace {
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
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
function _n($s, $p, $n) { return $n === 1 ? $s : $p; }
function size_format($b) { return round($b / 1048576) . ' MB'; }
function wp_max_upload_size() { return 64 * 1048576; }
function absint($v) { return abs((int) $v); }
function wp_basename($p) { return basename(str_replace('\\', '/', (string) $p)); }
function wp_normalize_path($p) { return str_replace('\\', '/', (string) $p); }
function trailingslashit($p) { return rtrim($p, '/\\') . '/'; }
function sanitize_text_field($s) { return trim(strip_tags((string) $s)); }
function wp_unslash($s) { return $s; }
function wp_salt($scheme = 'auth') { return 'test-salt-' . $scheme; }
function get_option($k, $d = false) { return array_key_exists($k, $GLOBALS['efb_options']) ? $GLOBALS['efb_options'][$k] : $d; }
function update_option($k, $v, $a = null) { $GLOBALS['efb_options'][$k] = $v; return true; }
function delete_option($k) { unset($GLOBALS['efb_options'][$k]); return true; }
function get_transient($k) { return false; }
function set_transient() { return true; }
function delete_transient() { return true; }
function emsfb_is_php_function_available_efb($n) { return function_exists($n); }
function wp_upload_dir($time = null, $create = true) {
    return array('basedir' => $GLOBALS['efb_uploads'], 'baseurl' => 'https://site.test/wp-content/uploads', 'error' => false);
}
class Efb_Test_Wpdb {
    public $prefix = 'wp_';
    public $msg = array();
    public function esc_like($t) { return addcslashes($t, '_%\\'); }
    public function prepare($q, ...$a) { return array($q, $a); }
    public function get_var($p) {
        if (strpos($p[0], 'emsfb_msg_') === false) return 0;
        $needle = stripcslashes(trim($p[1][0], '%'));
        foreach ($this->msg as $c) if (strpos($c, $needle) !== false) return 1;
        return 0;
    }
}
$GLOBALS['wpdb'] = new Efb_Test_Wpdb();
require_once __DIR__ . '/../includes/class-Emsfb-upload-guard.php';
require_once __DIR__ . '/../includes/class-Emsfb-client-ip.php';
}

namespace Emsfb {
use Emsfb\Upload_Guard as G;

/* Live sessions known to the fake session table: sid => form id. */
class FakeFunctions_Efb {
    public $live = array();
    public function efb_code_validate_select($sid, $fid) { return isset($this->live[$sid]) && (int) $this->live[$sid] === (int) $fid ? 1 : 0; }
}

$src = file_get_contents(__DIR__ . '/../includes/class-Emsfb-public.php');
$body = '';
foreach (array('validate_submitted_upload_url_efb', 'issue_upload_token_efb', 'upload_token_buckets_efb', 'get_ip_address') as $fn) {
    if (!preg_match('/\t(?:public|private)(?: static)? function ' . $fn . '\(.*?\n\t\}\n/s', $src, $m)) { echo "[FAIL] $fn() not found in class-Emsfb-public.php\n"; exit(1); }
    $body .= str_replace(array('private function', '__DIR__'), array('public function', "'" . __DIR__ . "/../includes'"), $m[0]);
}
eval('namespace Emsfb; class Upload_Harness_Efb { public $id = 0; public $efbFunction; private $upload_session_sid_efb = ""; '
    . 'function set_sid($s) { $this->upload_session_sid_efb = $s; } ' . $body . '}');

/* ---- uploads folder with real files ------------------------------------ */
$root = sys_get_temp_dir() . '/efb-ut-' . bin2hex(random_bytes(4));
$GLOBALS['efb_uploads'] = $root;
@mkdir($root . '/2026/11', 0777, true);
$pdf = "%PDF-1.4\n1 0 obj\n<<>>\nendobj\ntrailer\n<<>>\n%%EOF\n";
function stored($name) { global $pdf; $p = $GLOBALS['efb_uploads'] . '/2026/11/' . $name; file_put_contents($p, $pdf); G::track_pending_upload($p); return 'https://site.test/wp-content/uploads/2026/11/' . $name; }

$field  = array('id_' => 'fileA', 'type' => 'file', 'file' => 'document', 'name' => 'CV');
$field2 = array('id_' => 'fileB', 'type' => 'file', 'file' => 'document', 'name' => 'Letter');
$fn = new FakeFunctions_Efb();
$fn->live = array('SID-ALICE' => 12, 'SID-BOB' => 12, 'SID-OTHERFORM' => 7);

/* Upload route: the token the server hands back for this request. */
function upload_as($sid, $fid, $field_id, $name, $ip = '198.51.100.9') {
    global $fn;
    $_SERVER['HTTP_SID'] = $sid; $_SERVER['REMOTE_ADDR'] = $ip;
    $h = new Upload_Harness_Efb(); $h->efbFunction = $fn;
    return $h->issue_upload_token_efb($name, $fid, $field_id);
}
/* Submit route: the visitor's live sid (as get_form_public_efb records it) and form. */
function submit_as($sid, $fid, $url, $field, $ut, $ip = '198.51.100.9') {
    global $fn;
    $_SERVER['REMOTE_ADDR'] = $ip; unset($_SERVER['HTTP_SID']);
    $h = new Upload_Harness_Efb(); $h->efbFunction = $fn; $h->id = $fid;
    $h->set_sid($fn->efb_code_validate_select($sid, $fid) === 1 ? $sid : '');
    return $h->validate_submitted_upload_url_efb($url, $field, $ut);
}
$NOT_OWNED = 'This file could not be verified. Please attach it again.';

echo "--- 1. Token: issue and verify ---\n";
$t = G::issue_upload_token('efb-PLG-261111-abcDEF123456.pdf', 12, 'fileA', 'sid:S1');
test('token shape is exp.mac', (bool) preg_match('/^[0-9]+\.[a-f0-9]{32}$/', $t), true);
test('valid for the same file, form, field and session', G::verify_upload_token($t, 'efb-PLG-261111-abcDEF123456.pdf', 12, 'fileA', array('sid:S1')), true);
test('refused for another file', G::verify_upload_token($t, 'efb-PLG-261111-zzzzzz123456.pdf', 12, 'fileA', 'sid:S1'), false);
test('refused for another form', G::verify_upload_token($t, 'efb-PLG-261111-abcDEF123456.pdf', 13, 'fileA', 'sid:S1'), false);
test('refused for another field', G::verify_upload_token($t, 'efb-PLG-261111-abcDEF123456.pdf', 12, 'fileB', 'sid:S1'), false);
test('refused for another session', G::verify_upload_token($t, 'efb-PLG-261111-abcDEF123456.pdf', 12, 'fileA', 'sid:S2'), false);
$parts = explode('.', $t);
test('refused when the expiry is pushed forward', G::verify_upload_token(($parts[0] + 86400) . '.' . $parts[1], 'efb-PLG-261111-abcDEF123456.pdf', 12, 'fileA', 'sid:S1'), false);
test('refused when the mac is altered', G::verify_upload_token($parts[0] . '.' . str_repeat('0', 32), 'efb-PLG-261111-abcDEF123456.pdf', 12, 'fileA', 'sid:S1'), false);
test('refused once expired', G::verify_upload_token(G::issue_upload_token('efb-PLG-261111-abcDEF123456.pdf', 12, 'fileA', 'sid:S1', time() - 2 * 86400), 'efb-PLG-261111-abcDEF123456.pdf', 12, 'fileA', 'sid:S1'), false);
test('refused when not a string', G::verify_upload_token(array('x'), 'efb-PLG-261111-abcDEF123456.pdf', 12, 'fileA', 'sid:S1'), false);
test('lifetime equals the sweeper grace period', G::upload_token_ttl(), G::DEFAULT_ORPHAN_TTL);
test('a live sid is the session bucket', G::upload_token_bucket('S1', true, '1.2.3.4'), 'sid:S1');
test('no live sid: the IP is the bucket', G::upload_token_bucket('S1', false, '1.2.3.4'), 'ip:1.2.3.4');

echo "\n--- 2. Submit path (real validate_submitted_upload_url_efb) ---\n";
$alice_url = stored('efb-PLG-261111-ALICEfile001.pdf');
$alice_ut  = upload_as('SID-ALICE', 12, 'fileA', 'efb-PLG-261111-ALICEfile001.pdf');
test('own file with its token is accepted', submit_as('SID-ALICE', 12, $alice_url, $field, $alice_ut), true);
test('another visitor presenting the URL and token from a different session is refused while tokens are required',
    (function () use ($alice_url, $field, $alice_ut, $NOT_OWNED) {
        add_filter('emsfb_upload_require_token', __NAMESPACE__ . '\__return_true_efb');
        $r = submit_as('SID-BOB', 12, $alice_url, $field, $alice_ut, '203.0.113.7');
        $GLOBALS['efb_filters']['emsfb_upload_require_token'] = array();
        return $r;
    })(), $NOT_OWNED);
function __return_true_efb() { return true; }
add_filter('emsfb_upload_require_token', __NAMESPACE__ . '\__return_true_efb');
test('[require token] another field of the same form is refused', submit_as('SID-ALICE', 12, $alice_url, $field2, $alice_ut), $NOT_OWNED);
test('[require token] another form is refused', submit_as('SID-OTHERFORM', 7, $alice_url, $field, $alice_ut), $NOT_OWNED);
test('[require token] no token is refused', submit_as('SID-ALICE', 12, $alice_url, $field, ''), $NOT_OWNED);
test('[require token] own file with its token is accepted', submit_as('SID-ALICE', 12, $alice_url, $field, $alice_ut), true);
$GLOBALS['efb_filters']['emsfb_upload_require_token'] = array();

$media = $root . '/2026/11/company-logo.pdf';
file_put_contents($media, $pdf);
test('site media inside uploads is refused (not a plugin upload)', submit_as('SID-ALICE', 12, 'https://site.test/wp-content/uploads/2026/11/company-logo.pdf', $field, ''), $NOT_OWNED);
test('site media is refused even with a token minted for its name', submit_as('SID-ALICE', 12, 'https://site.test/wp-content/uploads/2026/11/company-logo.pdf', $field, upload_as('SID-ALICE', 12, 'fileA', 'company-logo.pdf')), $NOT_OWNED);

/* A file that a stored entry already claimed (released from the ledger). */
$claimed_url = stored('efb-PLG-261111-CLAIMEDbyBOB.pdf');
G::release_pending_uploads(array($claimed_url));
$GLOBALS['wpdb']->msg[] = '{"url":"' . $claimed_url . '"}';
test('another visitor\'s submitted file without a token is refused', submit_as('SID-ALICE', 12, $claimed_url, $field, ''), $NOT_OWNED);
test('a file already stored in an entry is refused even with a valid token',
    submit_as('SID-BOB', 12, $claimed_url, $field, upload_as('SID-BOB', 12, 'fileA', 'efb-PLG-261111-CLAIMEDbyBOB.pdf')), $NOT_OWNED);

/* Old file on disk, no longer in the ledger, never submitted. */
$old = $root . '/2026/11/efb-PLG-250101-OLDunusedFILE.pdf';
file_put_contents($old, $pdf);
test('an old plugin file outside the ledger is refused without a token', submit_as('SID-ALICE', 12, 'https://site.test/wp-content/uploads/2026/11/efb-PLG-250101-OLDunusedFILE.pdf', $field, ''), $NOT_OWNED);

echo "\n--- 3. Legacy cached bundles (no ut), emsfb_upload_require_token off ---\n";
$legacy_url = stored('efb-PLG-261111-LEGACYcached.pdf');
test('a pending (recent, unclaimed) upload without a token is accepted', submit_as('SID-ALICE', 12, $legacy_url, $field, ''), true);
test('a pending upload with a token for another session falls back to the ledger', submit_as('SID-ALICE', 12, $legacy_url, $field, upload_as('SID-BOB', 12, 'fileA', 'efb-PLG-261111-LEGACYcached.pdf')), true);
G::release_pending_uploads(array($legacy_url));
test('once claimed, the same URL is refused', submit_as('SID-ALICE', 12, $legacy_url, $field, ''), $NOT_OWNED);

echo "\n--- 4. Token outlives an evicted ledger row ---\n";
$evicted_url = stored('efb-PLG-261111-EVICTEDrow01.pdf');
$evicted_ut = upload_as('SID-ALICE', 12, 'fileA', 'efb-PLG-261111-EVICTEDrow01.pdf');
G::release_pending_uploads(array($evicted_url)); // pushed out by the 500-row cap
test('a valid token still proves ownership of an unclaimed file', submit_as('SID-ALICE', 12, $evicted_url, $field, $evicted_ut), true);

echo "\n--- 5. No live session: the token binds to the IP ---\n";
$ip_url = stored('efb-PLG-261111-NOSESSION001.pdf');
$ip_ut = upload_as('', 12, 'fileA', 'efb-PLG-261111-NOSESSION001.pdf', '192.0.2.10');
add_filter('emsfb_upload_require_token', __NAMESPACE__ . '\__return_true_efb');
test('same IP, no session: accepted', submit_as('', 12, $ip_url, $field, $ip_ut, '192.0.2.10'), true);
test('another IP: refused', submit_as('', 12, $ip_url, $field, $ip_ut, '192.0.2.11'), $NOT_OWNED);
$_SERVER['HTTP_X_FORWARDED_FOR'] = '192.0.2.10';
test('a spoofed X-Forwarded-For does not borrow the IP binding', submit_as('', 12, $ip_url, $field, $ip_ut, '192.0.2.11'), $NOT_OWNED);
unset($_SERVER['HTTP_X_FORWARDED_FOR']);
test('a sid that is not live for this form does not bind', upload_as('SID-OTHERFORM', 12, 'fileA', 'x') === upload_as('', 12, 'fileA', 'x'), true);
$GLOBALS['efb_filters']['emsfb_upload_require_token'] = array();

echo "\n--- 6. Wiring ---\n";
test('upload route returns ut for form uploads', strpos($src, "\$response['ut'] = \$this->issue_upload_token_efb(wp_basename(\$upload['file']), \$fid, \$_POST['id']);") !== false, true);
test('recorder uploads get a token, a quota charge and a ledger row', strpos($src, "\$response['ut'] = \$this->issue_upload_token_efb(wp_basename(\$stored_path), \$fid, \$_POST['id']);") !== false
    && strpos($src, "\\Emsfb\\Upload_Guard::track_pending_upload(\$stored_path);") !== false, true);
test('the recorder path never reaches the browser', strpos($src, "unset(\$response['_path']);") !== false, true);
test('submit reads ut before the attribute allow-list drops it', (bool) preg_match('/\$upload_token = isset\(\$item\[\'ut\'\]\)[^\n]*\n\s*\$item = \$this->filter_attributes_by_type_efb\(\$item,\$f\[\'type\'\]\);/', $src), true);
test('submit passes ut to the validator', strpos($src, "validate_submitted_upload_url_efb(\$item['url'], \$f, \$upload_token)") !== false, true);
test('register forms check ownership too', strpos($src, "validate_submitted_upload_url_efb(\$url, \$field, \$upload_token)") !== false, true);
test('the live sid of the submission is recorded', strpos($src, "\$this->upload_session_sid_efb = (int) \$session_is_valid === 1 ? (string) \$session_id : '';") !== false, true);
test('ut is not in the stored attribute allow-list', (bool) preg_match("/'ut' *=>/", $src), false);
test('file names come from a CSPRNG', substr_count($src, "'efb-PLG-'. wp_date(\"ymd\"). '-'.wp_generate_password(12, false, false)"), 2);
$client = file_get_contents(__DIR__ . '/../includes/admin/assets/js/new-efb.js');
test('client copies ut into the submitted row', strpos($client, "o[0].ut = responseData.ut") !== false, true);

$it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
foreach ($it as $f) { $f->isDir() ? @rmdir($f->getPathname()) : @unlink($f->getPathname()); }
@rmdir($root);

echo "\n========================================\n";
echo "RESULTS: $pass passed, $fail failed\n";
echo "========================================\n";
exit($fail > 0 ? 1 : 0);
}
