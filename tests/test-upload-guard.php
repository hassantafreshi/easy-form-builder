<?php
/**
 * Regression tests for Emsfb\Upload_Guard - the shared upload policy used by
 * includes/class-Emsfb-public.php and includes/admin/class-Emsfb-admin.php.
 *
 * Unlike tests/test-upload-html-blocklist.php this does NOT mirror the source
 * logic; it loads the real class against WordPress stubs, so the test fails if
 * the shipped rules change.
 *
 * Run: php tests/test-upload-guard.php
 */

$pass = 0;
$fail = 0;

function test($label, $actual, $expected) {
    global $pass, $fail;
    $ok = $actual === $expected;
    if ($ok) {
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
 * Minimal WordPress surface the class touches.
 * ------------------------------------------------------------------------ */

define('ABSPATH', __DIR__ . '/');
define('HOUR_IN_SECONDS', 3600);

$GLOBALS['efb_filters']    = array();
$GLOBALS['efb_transients'] = array();
$GLOBALS['efb_options']    = array();

function add_filter($hook, $callback, $priority = 10, $args = 1) {
    $GLOBALS['efb_filters'][$hook][] = $callback;
}
function apply_filters($hook, $value) {
    $extra = array_slice(func_get_args(), 2);
    if (empty($GLOBALS['efb_filters'][$hook])) {
        return $value;
    }
    foreach ($GLOBALS['efb_filters'][$hook] as $callback) {
        $value = call_user_func_array($callback, array_merge(array($value), $extra));
    }
    return $value;
}
function add_action($hook, $callback, $priority = 10, $args = 1) {}
function esc_html__($text, $domain = '') { return $text; }
function _n($single, $plural, $number, $domain = '') { return $number === 1 ? $single : $plural; }
function wp_basename($path) { return basename(str_replace('\\', '/', $path)); }
function absint($value) { return abs((int) $value); }
function wp_normalize_path($path) { return str_replace('\\', '/', $path); }
function trailingslashit($path) { return rtrim($path, '/\\') . '/'; }
function size_format($bytes, $decimals = 0) { return round($bytes / 1048576, $decimals) . ' MB'; }
function wp_max_upload_size() { return $GLOBALS['efb_host_limit']; }
function get_transient($key) {
    return isset($GLOBALS['efb_transients'][$key]) ? $GLOBALS['efb_transients'][$key] : false;
}
function set_transient($key, $value, $ttl = 0) {
    $GLOBALS['efb_transients'][$key] = $value;
    return true;
}
function delete_transient($key) {
    unset($GLOBALS['efb_transients'][$key]);
    return true;
}
function get_option($key, $default = false) {
    return isset($GLOBALS['efb_options'][$key]) ? $GLOBALS['efb_options'][$key] : $default;
}
function update_option($key, $value, $autoload = null) {
    $GLOBALS['efb_options'][$key] = $value;
    return true;
}
function emsfb_is_php_function_available_efb($name) { return function_exists($name); }

$GLOBALS['efb_host_limit'] = 64 * 1024 * 1024; // Generous host, so field settings dominate.

require_once __DIR__ . '/../includes/class-Emsfb-upload-guard.php';

use Emsfb\Upload_Guard;

/* ---------------------------------------------------------------------------
 * 1. Blocklist - the reported gap and every previously-blocked type.
 * ------------------------------------------------------------------------ */
echo "--- 1. BLOCKLIST: reported gap (pht family) is now closed ---\n";
foreach (array('pht', 'phtm', 'phps', 'php-s', 'inc', 'hphp') as $ext) {
    test("blocked: .$ext", Upload_Guard::is_blocked_extension($ext), true);
}

echo "\n--- 1b. BLOCKLIST: previously-blocked types stay blocked ---\n";
foreach (array('php', 'php5', 'phtml', 'phar', 'exe', 'sh', 'aspx', 'jsp',
               'svg', 'svgz', 'html', 'htm', 'xhtml', 'shtml', 'htaccess') as $ext) {
    test("still blocked: .$ext", Upload_Guard::is_blocked_extension($ext), true);
}

echo "\n--- 1c. BLOCKLIST: case-insensitive ---\n";
foreach (array('PHT', 'PhTml', 'EXE') as $ext) {
    test("blocked (case): .$ext", Upload_Guard::is_blocked_extension($ext), true);
}

echo "\n--- 1d. BLOCKLIST: legitimate types are not blocked ---\n";
foreach (array('pdf', 'jpg', 'png', 'docx', 'xlsx', 'zip', 'mp4', 'webm', 'txt') as $ext) {
    test("not blocked: .$ext", Upload_Guard::is_blocked_extension($ext), false);
}

/* ---------------------------------------------------------------------------
 * 2. Type gate - extension allow-list joined to the sniffed MIME.
 * ------------------------------------------------------------------------ */
echo "\n--- 2. TYPE GATE: the reported bypass is refused ---\n";
test(
    '.pht sniffed as text/plain is refused',
    Upload_Guard::is_allowed_type('pht', 'text/plain'),
    false
);
test(
    '.php sniffed as text/plain is refused',
    Upload_Guard::is_allowed_type('php', 'text/plain'),
    false
);
test(
    'unknown extension .xyz is refused even with a benign MIME',
    Upload_Guard::is_allowed_type('xyz', 'text/plain'),
    false
);
test(
    'empty extension is refused',
    Upload_Guard::is_allowed_type('', 'application/pdf'),
    false
);

echo "\n--- 2b. TYPE GATE: extension and sniffed MIME must agree ---\n";
test('pdf + application/pdf accepted', Upload_Guard::is_allowed_type('pdf', 'application/pdf'), true);
test('pdf + text/plain refused (the old hole)', Upload_Guard::is_allowed_type('pdf', 'text/plain'), false);
test('png + image/png accepted', Upload_Guard::is_allowed_type('png', 'image/png'), true);
test('png + image/jpeg refused', Upload_Guard::is_allowed_type('png', 'image/jpeg'), false);
test('txt + text/plain accepted', Upload_Guard::is_allowed_type('txt', 'text/plain'), true);
test('docx + application/zip accepted (real OOXML sniff)', Upload_Guard::is_allowed_type('docx', 'application/zip'), true);
test('mp4 + video/mp4 accepted', Upload_Guard::is_allowed_type('mp4', 'video/mp4'), true);
test('webm + audio/webm accepted (audio-only recorder)', Upload_Guard::is_allowed_type('webm', 'audio/webm'), true);
test('zip + any sniff accepted (extension-only entry)', Upload_Guard::is_allowed_type('zip', 'application/x-zip-compressed'), true);

echo "\n--- 2c. TYPE GATE: no libmagic falls back to the allow-list ---\n";
test('pdf accepted when sniffing unavailable', Upload_Guard::is_allowed_type('pdf', false), true);
test('pht STILL refused when sniffing unavailable', Upload_Guard::is_allowed_type('pht', false), false);

echo "\n--- 2d. TYPE GATE: per-field list narrows, never widens ---\n";
test(
    'field allows only pdf: a png is refused',
    Upload_Guard::is_allowed_type('png', 'image/png', array('pdf')),
    false
);
test(
    'field allows only pdf: a pdf is accepted',
    Upload_Guard::is_allowed_type('pdf', 'application/pdf', array('pdf')),
    true
);
test(
    'field tries to allow pht: still refused',
    Upload_Guard::is_allowed_type('pht', 'text/plain', array('pht', 'pdf')),
    false
);

/* ---------------------------------------------------------------------------
 * 3. Size ceiling.
 * ------------------------------------------------------------------------ */
echo "\n--- 3. SIZE: field setting is authoritative, host can only lower it ---\n";
test('field 8 MB -> 8 MB', Upload_Guard::max_bytes(8), 8 * 1024 * 1024);
test('no field setting -> 20 MB default', Upload_Guard::max_bytes(null), 20 * 1024 * 1024);
test('zero/garbage field setting -> 20 MB default', Upload_Guard::max_bytes(0), 20 * 1024 * 1024);
test('hostile field setting is capped at 1 GB', Upload_Guard::max_bytes(999999), 64 * 1024 * 1024);

$GLOBALS['efb_host_limit'] = 2 * 1024 * 1024; // Tight host.
test('host limit 2 MB lowers a field set to 8 MB', Upload_Guard::max_bytes(8), 2 * 1024 * 1024);
$GLOBALS['efb_host_limit'] = 64 * 1024 * 1024;
test(
    'PHP-level oversized request reports the field cap',
    Upload_Guard::validate_file(array('error' => UPLOAD_ERR_INI_SIZE), array('max_mb' => 2)),
    Upload_Guard::size_message(2 * 1024 * 1024)
);

/* ---------------------------------------------------------------------------
 * 4. Counting upload fields (drives the quota budget).
 * ------------------------------------------------------------------------ */
echo "\n--- 4. FIELD COUNT: budget follows the form's shape ---\n";
$structure = json_encode(array(
    array('type' => 'text',    'id_' => 'a'),
    array('type' => 'file',    'id_' => 'b'),
    array('type' => 'dadfile', 'id_' => 'c'),
    array('type' => 'email',   'id_' => 'd'),
));
test('2 upload fields among 4', Upload_Guard::count_upload_fields($structure), 2);
test('no upload fields', Upload_Guard::count_upload_fields(json_encode(array(array('type' => 'text')))), 0);
test('recorders count too', Upload_Guard::count_upload_fields(json_encode(array(
    array('type' => 'audio_recorder'),
    array('type' => 'video_recorder'),
    array('type' => 'screen_recorder'),
))), 3);
test('decoded array input works', Upload_Guard::count_upload_fields(json_decode($structure)), 2);
test('garbage input is 0, not a crash', Upload_Guard::count_upload_fields(null), 0);

/* Stored structures are escaped; the raw-string fallback must still count. */
$escaped = '[{\"type\":\"file\",\"id_\":\"b\"},{\"type\":\"dadfile\",\"id_\":\"c\"}]';
test('escaped stored structure counts 2', Upload_Guard::count_upload_fields($escaped), 2);

/* ---------------------------------------------------------------------------
 * 5. Quota - the brief: response box = 3 per nonce, form = fields x 3.
 * ------------------------------------------------------------------------ */
echo "\n--- 5. QUOTA: derived budgets ---\n";
test('response box budget is 3', Upload_Guard::quota_limit(array('source' => 'response')), 3);
test('1-field form budget is 3', Upload_Guard::quota_limit(array('source' => 'form', 'fields' => 1)), 3);
test('2-field form budget is 6', Upload_Guard::quota_limit(array('source' => 'form', 'fields' => 2)), 6);
test('4-field form budget is 12', Upload_Guard::quota_limit(array('source' => 'form', 'fields' => 4)), 12);
test('0-field form still gets a floor of 3', Upload_Guard::quota_limit(array('source' => 'form', 'fields' => 0)), 3);

echo "\n--- 5b. QUOTA: the counter actually stops the 4th response-box upload ---\n";
$ctx = array('source' => 'response', 'nonce' => 'abc123', 'sid' => 'sid-1', 'form_id' => 0);
test('upload 1 allowed', Upload_Guard::quota_allows($ctx), true);
Upload_Guard::quota_consume($ctx);
test('upload 2 allowed', Upload_Guard::quota_allows($ctx), true);
Upload_Guard::quota_consume($ctx);
test('upload 3 allowed', Upload_Guard::quota_allows($ctx), true);
Upload_Guard::quota_consume($ctx);
test('upload 4 REFUSED', Upload_Guard::quota_allows($ctx), false);
test('counter reads 3', Upload_Guard::quota_used($ctx), 3);

echo "\n--- 5c. QUOTA: buckets are isolated ---\n";
$other_nonce = array('source' => 'response', 'nonce' => 'different', 'sid' => 'sid-1', 'form_id' => 0);
test('a different nonce has its own budget', Upload_Guard::quota_allows($other_nonce), true);
$other_form = array('source' => 'form', 'nonce' => 'abc123', 'sid' => 'sid-1', 'form_id' => 7, 'fields' => 1);
test('a different form has its own budget', Upload_Guard::quota_allows($other_form), true);

echo "\n--- 5d. QUOTA: a 2-field form gets 6 before it stops ---\n";
$form_ctx = array('source' => 'form', 'nonce' => 'n2', 'sid' => 's2', 'form_id' => 11, 'fields' => 2);
for ($i = 1; $i <= 6; $i++) {
    test("form upload $i allowed", Upload_Guard::quota_allows($form_ctx), true);
    Upload_Guard::quota_consume($form_ctx);
}
test('form upload 7 REFUSED', Upload_Guard::quota_allows($form_ctx), false);

/* ---------------------------------------------------------------------------
 * 6. Human Shield override path - the filters must actually win.
 * ------------------------------------------------------------------------ */
echo "\n--- 6. OVERRIDES: Human Shield can raise, lower and disable ---\n";
add_filter('emsfb_upload_quota_limit', function ($limit, $context) { return 25; });
test('filter raises the response-box budget to 25', Upload_Guard::quota_limit(array('source' => 'response')), 25);
$GLOBALS['efb_filters']['emsfb_upload_quota_limit'] = array();

add_filter('emsfb_upload_quota_limit', function ($limit, $context) { return 1; });
test('filter lowers the budget to 1', Upload_Guard::quota_limit(array('source' => 'response')), 1);
$GLOBALS['efb_filters']['emsfb_upload_quota_limit'] = array();

add_filter('emsfb_upload_quota_limit', function ($limit, $context) { return 0; });
test('a filter returning 0 is floored to 1, never disabled by accident', Upload_Guard::quota_limit(array('source' => 'response')), 1);
$GLOBALS['efb_filters']['emsfb_upload_quota_limit'] = array();

add_filter('emsfb_upload_retry_allowance', function ($allowance, $context) { return 1; });
test('retry allowance 1 makes a 2-field form budget 2', Upload_Guard::quota_limit(array('source' => 'form', 'fields' => 2)), 2);
$GLOBALS['efb_filters']['emsfb_upload_retry_allowance'] = array();

$spent = array('source' => 'response', 'nonce' => 'abc123', 'sid' => 'sid-1', 'form_id' => 0);
test('exhausted bucket is refused before the override', Upload_Guard::quota_allows($spent), false);
add_filter('emsfb_upload_quota_enabled', function ($enabled, $context) { return false; });
test('quota_enabled=false lets it through', Upload_Guard::quota_allows($spent), true);
$GLOBALS['efb_filters']['emsfb_upload_quota_enabled'] = array();

add_filter('emsfb_upload_max_bytes', function ($bytes, $context) { return 1024; });
test('filter overrides the byte ceiling', Upload_Guard::max_bytes(8), 1024);
$GLOBALS['efb_filters']['emsfb_upload_max_bytes'] = array();

add_filter('emsfb_upload_blocked_extensions', function ($list) { $list[] = 'pdf'; return $list; });
test('filter can add a site-specific block', Upload_Guard::is_blocked_extension('pdf'), true);
test('a blocked extension is refused by the type gate too', Upload_Guard::is_allowed_type('pdf', 'application/pdf'), false);
$GLOBALS['efb_filters']['emsfb_upload_blocked_extensions'] = array();

echo "\n--- 6b. OVERRIDES: window is clamped to a sane floor ---\n";
add_filter('emsfb_upload_quota_window', function ($w, $c) { return 5; });
test('a 5-second window is floored to 60', Upload_Guard::quota_window(array()), 60);
$GLOBALS['efb_filters']['emsfb_upload_quota_window'] = array();

/* ---------------------------------------------------------------------------
 * 7. Messages - must be a real sentence, with the real number in it.
 * ------------------------------------------------------------------------ */
echo "\n--- 7. MESSAGES ---\n";
$msg = Upload_Guard::quota_message(array('source' => 'response'));
test('quota message names the limit', strpos($msg, '3') !== false, true);
test('quota message names the wait', strpos($msg, '60') !== false, true);
test('quota message is not an error code', strpos($msg, 'Error') === false, true);
$size_msg = Upload_Guard::size_message(8 * 1024 * 1024);
test('size message names the ceiling', strpos($size_msg, '8 MB') !== false, true);

/* ---------------------------------------------------------------------------
 * 8. Stored files: final form submission must re-check the bytes on disk.
 * ------------------------------------------------------------------------ */
echo "\n--- 8. STORED FILES: final submission cannot bypass field rules ---\n";
$stored_pdf = sys_get_temp_dir() . '/efb-upload-guard-real.pdf';
$stored_php_as_pdf = sys_get_temp_dir() . '/efb-upload-guard-report.pdf';
$stored_svg = sys_get_temp_dir() . '/efb-upload-guard-logo.svg';
$stored_big = sys_get_temp_dir() . '/efb-upload-guard-big.txt';
file_put_contents($stored_pdf, "%PDF-1.4\n1 0 obj\n<<>>\nendobj\ntrailer\n<<>>\n%%EOF\n");
file_put_contents($stored_php_as_pdf, "<?php echo 'not a PDF';\n");
file_put_contents($stored_svg, '<svg xmlns="http://www.w3.org/2000/svg"></svg>');
file_put_contents($stored_big, str_repeat('x', 1024 * 1024 + 1));

test(
    'a real PDF is accepted from disk',
    Upload_Guard::validate_stored_file($stored_pdf, 'report.pdf', array('field_extensions' => array('pdf'))),
    true
);
test(
    'PHP renamed to .pdf is refused from disk',
    Upload_Guard::validate_stored_file($stored_php_as_pdf, 'report.pdf', array('field_extensions' => array('pdf'))) === Upload_Guard::type_message(),
    true
);
test(
    '.svg is refused from disk even for all formats',
    Upload_Guard::validate_stored_file($stored_svg, 'logo.svg') === Upload_Guard::type_message(),
    true
);
$stored_size_error = Upload_Guard::validate_stored_file($stored_big, 'big.txt', array('max_mb' => 1));
test('stored file over the field cap is refused', $stored_size_error === Upload_Guard::size_message(1024 * 1024), true);

@unlink($stored_pdf);
@unlink($stored_php_as_pdf);
@unlink($stored_svg);
@unlink($stored_big);

/* ---------------------------------------------------------------------------
 * 9. Pending ledger bookkeeping.
 * ------------------------------------------------------------------------ */
echo "\n--- 9. LEDGER: track and release ---\n";
$GLOBALS['efb_options'] = array();
$tmp_file = sys_get_temp_dir() . '/efb-PLG-260728-TESTAAAA.pdf';
file_put_contents($tmp_file, 'x');
Upload_Guard::track_pending_upload($tmp_file);
$ledger = get_option(\Emsfb\Upload_Guard::PENDING_OPTION, array());
test('upload is recorded as pending', isset($ledger['efb-PLG-260728-TESTAAAA.pdf']), true);

Upload_Guard::release_pending_uploads(array('https://example.com/wp-content/uploads/efb-PLG-260728-TESTAAAA.pdf'));
$ledger = get_option(\Emsfb\Upload_Guard::PENDING_OPTION, array());
test('a submitted file is released from the ledger', isset($ledger['efb-PLG-260728-TESTAAAA.pdf']), false);
@unlink($tmp_file);

Upload_Guard::track_pending_upload('/nowhere/does-not-exist.pdf');
$ledger = get_option(\Emsfb\Upload_Guard::PENDING_OPTION, array());
test('a non-existent path is not tracked', isset($ledger['does-not-exist.pdf']), false);

/* ---------------------------------------------------------------------------
 * 10. Response-box reservations - broad safe format, ticket-bound delivery.
 * ------------------------------------------------------------------------ */
echo "\n--- 10. RESPONSE BOX: attachment is bound to its ticket ---\n";
$response_file = 'efb-PLG-260728-RESPTEST.pdf';
$response_url = 'https://example.com/wp-content/uploads/' . $response_file;
$response_payload = array(
    (object) array('id_' => 'message', 'type' => 'text', 'value' => 'Reply'),
    (object) array('id_' => 'resp_file_efb_1', 'type' => 'allformat', 'url' => $response_url),
);

test('response attachment reservation is created', Upload_Guard::reserve_response_attachment($response_file, 42, 'TRACK-42'), true);
test('same ticket may save its attachment', Upload_Guard::response_attachment_is_reserved($response_url, 42, 'TRACK-42'), true);
test('different ticket cannot reuse its attachment', Upload_Guard::response_attachment_is_reserved($response_url, 43, 'TRACK-43'), false);
test('different tracking code cannot reuse its attachment', Upload_Guard::response_attachment_is_reserved($response_url, 42, 'OTHER-TRACK'), false);
test('response reply finds its safe attachment URL', Upload_Guard::response_attachment_urls($response_payload), array($response_url));
test('malformed response attachment is rejected', Upload_Guard::response_attachment_urls(array((object) array('type' => 'allformat'))), false);
Upload_Guard::release_response_attachment_reservations($response_url);
test('reservation is consumed only after a reply is saved', Upload_Guard::response_attachment_is_reserved($response_url, 42, 'TRACK-42'), false);

echo "\n========================================\n";
echo "RESULTS: $pass passed, $fail failed\n";
echo "========================================\n";

exit($fail > 0 ? 1 : 0);
