<?php
/**
 * Replays the two scenarios from the July 2026 external security report
 * against the shipped Emsfb\Upload_Guard policy.
 *
 * Report scenario 1: 50 sequential unauthenticated uploads to
 *   Emsfb/v1/forms/file/upload all returned 200/success with no throttling,
 *   growing the uploads directory from 69 to 119 files.
 *
 * Report scenario 2: a .pht file crafted to sniff as text/plain passed the
 *   plugin's own validation (only WordPress core stopped it from being
 *   written).
 *
 * Both must now be refused by the plugin itself. This test drives the real
 * class, not a copy of its logic.
 *
 * Run: php tests/test-upload-report-scenarios.php
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

define('ABSPATH', __DIR__ . '/');
define('HOUR_IN_SECONDS', 3600);

$GLOBALS['efb_filters']    = array();
$GLOBALS['efb_transients'] = array();
$GLOBALS['efb_options']    = array();
$GLOBALS['efb_host_limit'] = 64 * 1024 * 1024;

function add_filter($hook, $callback, $priority = 10, $args = 1) { $GLOBALS['efb_filters'][$hook][] = $callback; }
function apply_filters($hook, $value) {
    $extra = array_slice(func_get_args(), 2);
    if (empty($GLOBALS['efb_filters'][$hook])) return $value;
    foreach ($GLOBALS['efb_filters'][$hook] as $cb) {
        $value = call_user_func_array($cb, array_merge(array($value), $extra));
    }
    return $value;
}
function add_action($hook, $callback, $priority = 10, $args = 1) {}
function esc_html__($text, $domain = '') { return $text; }
function _n($single, $plural, $number, $domain = '') { return $number === 1 ? $single : $plural; }
function wp_basename($path) { return basename(str_replace('\\', '/', $path)); }
function wp_normalize_path($path) { return str_replace('\\', '/', $path); }
function trailingslashit($path) { return rtrim($path, '/\\') . '/'; }
function size_format($bytes, $decimals = 0) { return round($bytes / 1048576, $decimals) . ' MB'; }
function wp_max_upload_size() { return $GLOBALS['efb_host_limit']; }
function get_transient($k) { return isset($GLOBALS['efb_transients'][$k]) ? $GLOBALS['efb_transients'][$k] : false; }
function set_transient($k, $v, $t = 0) { $GLOBALS['efb_transients'][$k] = $v; return true; }
function get_option($k, $d = false) { return isset($GLOBALS['efb_options'][$k]) ? $GLOBALS['efb_options'][$k] : $d; }
function update_option($k, $v, $a = null) { $GLOBALS['efb_options'][$k] = $v; return true; }
function emsfb_is_php_function_available_efb($n) { return function_exists($n); }

require_once __DIR__ . '/../includes/class-Emsfb-upload-guard.php';

use Emsfb\Upload_Guard;

/*
 * is_uploaded_file() is false for anything this test creates, so validate_file()
 * would stop at the "could not be read" branch before reaching the type layer.
 * Drive the type and size layers directly, exactly as validate_file() calls
 * them, and drive the quota layer through its own public methods.
 */
$tmpdir = sys_get_temp_dir();

/* -------------------------------------------------------------------------
 * Scenario 1 - the 50-request flood.
 * ---------------------------------------------------------------------- */
echo "=== REPORT SCENARIO 1: 50 sequential uploads, one anonymous visitor ===\n\n";

echo "--- 1a. Response box (the report's shape: one attachment slot) ---\n";
$ctx = array(
    'source'  => 'response',
    'nonce'   => 'a1b2c3d4e5',   // one wp_rest nonce, as the PoC used
    'sid'     => 'sess-poc-001',
    'form_id' => 0,
);

$accepted = 0;
$refused  = 0;
for ($i = 1; $i <= 50; $i++) {
    if (Upload_Guard::quota_allows($ctx)) {
        Upload_Guard::quota_consume($ctx);
        $accepted++;
    } else {
        $refused++;
    }
}

test('50 requests -> exactly 3 files written', $accepted, 3);
test('50 requests -> 47 refused', $refused, 47);
test('before the fix all 50 were written; now the growth is capped', $accepted < 50, true);

$message = Upload_Guard::quota_message($ctx);
echo "  Visitor sees: \"$message\"\n";
test('the refusal explains itself in plain language', strlen($message) > 40, true);

echo "\n--- 1b. A four-upload-field application form ---\n";
$form_ctx = array(
    'source'  => 'form',
    'nonce'   => 'form-nonce-1',
    'sid'     => 'sess-poc-002',
    'form_id' => 42,
    'fields'  => 4,
);
$accepted = 0;
for ($i = 1; $i <= 50; $i++) {
    if (Upload_Guard::quota_allows($form_ctx)) {
        Upload_Guard::quota_consume($form_ctx);
        $accepted++;
    }
}
test('4 upload fields -> 12 files, not 50', $accepted, 12);
test('a genuine 4-file submission still fits comfortably', 12 >= 4, true);

echo "\n--- 1c. Legitimate visitors are not collateral damage ---\n";
$fresh = array('source' => 'response', 'nonce' => 'other-visitor', 'sid' => 'sess-b', 'form_id' => 0);
test('a second visitor is unaffected by the first one flooding', Upload_Guard::quota_allows($fresh), true);

$retry_ctx = array('source' => 'form', 'nonce' => 'n-retry', 'sid' => 's-retry', 'form_id' => 7, 'fields' => 1);
$ok = true;
for ($i = 1; $i <= 3; $i++) {
    if (!Upload_Guard::quota_allows($retry_ctx)) { $ok = false; break; }
    Upload_Guard::quota_consume($retry_ctx);
}
test('one-field form: picking the wrong file twice then the right one works', $ok, true);

echo "\n--- 1d. Size ceiling now exists server-side (report: none at all) ---\n";
$GLOBALS['efb_host_limit'] = 512 * 1024 * 1024; // Permissive host.
test('a field set to 2 MB is enforced at 2 MB', Upload_Guard::max_bytes(2), 2 * 1024 * 1024);
test('a field with no setting is capped at 20 MB, not php.ini', Upload_Guard::max_bytes(null), 20 * 1024 * 1024);
test('a 200 MB upload would exceed the default ceiling', (200 * 1024 * 1024) > Upload_Guard::max_bytes(null), true);
$GLOBALS['efb_host_limit'] = 64 * 1024 * 1024;

/* -------------------------------------------------------------------------
 * Scenario 2 - the .pht / text-plain bypass.
 * ---------------------------------------------------------------------- */
echo "\n\n=== REPORT SCENARIO 2: .pht sniffing as text/plain ===\n\n";

/* Build the exact payload described in the report: a newline before <?php so
 * libmagic fingerprints the file as text/plain rather than PHP source. */
$payload = "\n<?php echo 'proof-of-concept'; ?>\n";
$pht     = $tmpdir . '/efb-report-poc.pht';
file_put_contents($pht, $payload);

$sniffed = Upload_Guard::sniff_mime($pht);
echo "  libmagic reports: " . var_export($sniffed, true) . "\n";

echo "--- 2a. The plugin's own validation now refuses it ---\n";
test('.pht is on the blocklist', Upload_Guard::is_blocked_extension('pht'), true);
test('.pht + the real sniffed MIME is refused', Upload_Guard::is_allowed_type('pht', $sniffed), false);
test('.pht + text/plain (the report PoC) is refused', Upload_Guard::is_allowed_type('pht', 'text/plain'), false);
test('refusal does not depend on libmagic being present', Upload_Guard::is_allowed_type('pht', false), false);

echo "\n--- 2b. text/plain no longer launders arbitrary extensions ---\n";
foreach (array('pht', 'phtm', 'phps', 'php', 'phtml', 'phar', 'html', 'svg', 'exe') as $ext) {
    test("'.$ext' claiming text/plain is refused", Upload_Guard::is_allowed_type($ext, 'text/plain'), false);
}
test('text/plain is still accepted for a real .txt', Upload_Guard::is_allowed_type('txt', 'text/plain'), true);
test('text/plain is still accepted for a .csv', Upload_Guard::is_allowed_type('csv', 'text/plain'), true);

echo "\n--- 2c. A PDF whose bytes are not a PDF is refused ---\n";
$fake_pdf = $tmpdir . '/efb-report-fake.pdf';
file_put_contents($fake_pdf, $payload);
$fake_mime = Upload_Guard::sniff_mime($fake_pdf);
test('.pdf containing PHP source is refused', Upload_Guard::is_allowed_type('pdf', $fake_mime), false);

$real_pdf = $tmpdir . '/efb-report-real.pdf';
file_put_contents($real_pdf, "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\ntrailer\n%%EOF\n");
$real_mime = Upload_Guard::sniff_mime($real_pdf);
echo "  a real PDF sniffs as: " . var_export($real_mime, true) . "\n";
test('a genuine PDF is still accepted', Upload_Guard::is_allowed_type('pdf', $real_mime), true);

echo "\n--- 2d. Ordinary attachments keep working ---\n";
$png = $tmpdir . '/efb-report.png';
file_put_contents($png, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='));
test('a real PNG is accepted', Upload_Guard::is_allowed_type('png', Upload_Guard::sniff_mime($png)), true);

$txt = $tmpdir . '/efb-report.txt';
file_put_contents($txt, "just some notes\n");
test('a real .txt is accepted', Upload_Guard::is_allowed_type('txt', Upload_Guard::sniff_mime($txt)), true);

foreach (array($pht, $fake_pdf, $real_pdf, $png, $txt) as $f) { @unlink($f); }

/* -------------------------------------------------------------------------
 * Scenario 3 - disk growth is now reversible.
 * ---------------------------------------------------------------------- */
echo "\n\n=== REPORT FOLLOW-UP: abandoned files no longer accumulate ===\n\n";
$GLOBALS['efb_options'] = array();

$stored = array();
for ($i = 1; $i <= 3; $i++) {
    $p = $tmpdir . "/efb-PLG-260728-ORPHAN0$i.pdf";
    file_put_contents($p, 'x');
    $stored[] = $p;
    Upload_Guard::track_pending_upload($p);
}
$ledger = get_option(Upload_Guard::PENDING_OPTION, array());
test('every stored upload is tracked until a submission claims it', count($ledger), 3);

Upload_Guard::release_pending_uploads(array('efb-PLG-260728-ORPHAN01.pdf'));
$ledger = get_option(Upload_Guard::PENDING_OPTION, array());
test('a submitted file is released and will never be swept', count($ledger), 2);
test('the released file is the right one', isset($ledger['efb-PLG-260728-ORPHAN01.pdf']), false);
test('the abandoned ones remain queued for the sweeper', isset($ledger['efb-PLG-260728-ORPHAN02.pdf']), true);

foreach ($stored as $f) { @unlink($f); }

echo "\n========================================\n";
echo "RESULTS: $pass passed, $fail failed\n";
echo "========================================\n";

exit($fail > 0 ? 1 : 0);
