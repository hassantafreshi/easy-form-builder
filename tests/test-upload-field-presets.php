<?php
/**
 * The "Acceptable file types" presets, enforced server-side.
 *
 * Until now only the "Customize" option was checked on the server: a field set
 * to Document would still store a .jpg, because Image/Media/Document/Zip were
 * enforced in the browser only. These tests pin the server lists to the same
 * behaviour as filetype_efb in includes/admin/assets/js/new-efb.js.
 *
 * The other half of this is the reply box on a tracked conversation. It has no
 * field definition to read a preset from and its own client check runs against
 * "allformat", so it must keep the global allow-list. A preset rollout that
 * broke the reply box would be a regression, so that is asserted here too.
 *
 * Run: php tests/test-upload-field-presets.php
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
define('MINUTE_IN_SECONDS', 60);

$GLOBALS['efb_filters']    = array();
$GLOBALS['efb_transients'] = array();
$GLOBALS['efb_options']    = array();
$GLOBALS['efb_cache']      = array();
$GLOBALS['efb_host_limit'] = 64 * 1024 * 1024;

function add_filter($h, $c, $p = 10, $a = 1) { $GLOBALS['efb_filters'][$h][] = $c; }
function apply_filters($h, $v) {
    $e = array_slice(func_get_args(), 2);
    if (empty($GLOBALS['efb_filters'][$h])) return $v;
    foreach ($GLOBALS['efb_filters'][$h] as $cb) $v = call_user_func_array($cb, array_merge(array($v), $e));
    return $v;
}
function add_action($h, $c, $p = 10, $a = 1) {}
function esc_html__($t, $d = '') { return $t; }
function _n($s, $p, $n, $d = '') { return $n === 1 ? $s : $p; }
function wp_basename($p) { return basename(str_replace('\\', '/', $p)); }
function wp_normalize_path($p) { return str_replace('\\', '/', $p); }
function trailingslashit($p) { return rtrim($p, '/\\') . '/'; }
function size_format($b, $d = 0) { return round($b / 1048576, $d) . ' MB'; }
function wp_max_upload_size() { return $GLOBALS['efb_host_limit']; }
function get_transient($k) { return isset($GLOBALS['efb_transients'][$k]) ? $GLOBALS['efb_transients'][$k] : false; }
function set_transient($k, $v, $t = 0) { $GLOBALS['efb_transients'][$k] = $v; return true; }
function get_option($k, $d = false) { return isset($GLOBALS['efb_options'][$k]) ? $GLOBALS['efb_options'][$k] : $d; }
function update_option($k, $v, $a = null) { $GLOBALS['efb_options'][$k] = $v; return true; }
function wp_cache_get($k, $g = '') { return isset($GLOBALS['efb_cache']["$g:$k"]) ? $GLOBALS['efb_cache']["$g:$k"] : false; }
function wp_cache_set($k, $v, $g = '', $t = 0) { $GLOBALS['efb_cache']["$g:$k"] = $v; return true; }
function emsfb_is_php_function_available_efb($n) { return function_exists($n); }

require_once __DIR__ . '/../includes/class-Emsfb-upload-guard.php';

use Emsfb\Upload_Guard;

/** A field as the builder stores it: preset in both `file` and `value`. */
function field($preset, $ctype = null) {
    $f = (object) array('id_' => 'x1', 'type' => 'file', 'file' => $preset, 'value' => $preset);
    if ($ctype !== null) $f->file_ctype = $ctype;
    return $f;
}

/** Would this field accept this file? Mirrors the handler's call chain. */
function accepts($field, $ext, $mime, $source = 'form') {
    $list = Upload_Guard::field_extensions($field, $source);
    return Upload_Guard::is_allowed_type($ext, $mime, $list);
}

/* =====================================================================
 * 1. THE GAP THAT WAS REPORTED: a Document field used to take an image.
 * ================================================================== */
echo "=== 1. The reported gap is closed ===\n";
$doc = field('document');
test('Document field REFUSES a .jpg (was accepted before)', accepts($doc, 'jpg', 'image/jpeg'), false);
test('Document field REFUSES a .png', accepts($doc, 'png', 'image/png'), false);
test('Document field REFUSES an .mp4', accepts($doc, 'mp4', 'video/mp4'), false);
test('Document field REFUSES a .zip', accepts($doc, 'zip', 'application/zip'), false);
test('Document field accepts a .pdf', accepts($doc, 'pdf', 'application/pdf'), true);
test('Document field accepts a .docx', accepts($doc, 'docx', 'application/zip'), true);
test('Document field accepts a .xlsx', accepts($doc, 'xlsx', 'application/zip'), true);
test('Document field accepts a .txt', accepts($doc, 'txt', 'text/plain'), true);
test('Document field accepts an .odt', accepts($doc, 'odt', 'application/zip'), true);
test('Document field accepts a .pptm', accepts($doc, 'pptm', 'application/zip'), true);

echo "\n=== 2. Image preset ===\n";
$img = field('image');
test('Image accepts .jpg', accepts($img, 'jpg', 'image/jpeg'), true);
test('Image accepts .png', accepts($img, 'png', 'image/png'), true);
test('Image accepts .gif', accepts($img, 'gif', 'image/gif'), true);
test('Image accepts .heic', accepts($img, 'heic', 'image/heic'), true);
test('Image REFUSES .pdf', accepts($img, 'pdf', 'application/pdf'), false);
test('Image REFUSES .mp4', accepts($img, 'mp4', 'video/mp4'), false);
test('Image REFUSES .docx', accepts($img, 'docx', 'application/zip'), false);

echo "\n=== 3. Media preset ===\n";
$med = field('media');
test('Media accepts .mp4', accepts($med, 'mp4', 'video/mp4'), true);
test('Media accepts .mp3', accepts($med, 'mp3', 'audio/mpeg'), true);
test('Media accepts .webm', accepts($med, 'webm', 'video/webm'), true);
test('Media accepts .mov', accepts($med, 'mov', 'video/quicktime'), true);
test('Media accepts .mkv', accepts($med, 'mkv', 'video/x-matroska'), true);
test('Media REFUSES .pdf', accepts($med, 'pdf', 'application/pdf'), false);
test('Media REFUSES .jpg', accepts($med, 'jpg', 'image/jpeg'), false);
test('Media REFUSES .zip', accepts($med, 'zip', 'application/zip'), false);

echo "\n=== 4. Zip preset (keeps the builder's long archive tail) ===\n";
$zip = field('zip');
foreach (array('zip','rar','7z','tar','gz','tgz','bz2','tbz','tz','z','gzip','bzip2') as $e) {
    test("Zip accepts .$e", accepts($zip, $e, 'application/octet-stream'), true);
}
test('Zip REFUSES .pdf', accepts($zip, 'pdf', 'application/pdf'), false);
test('Zip REFUSES .jpg', accepts($zip, 'jpg', 'image/jpeg'), false);
test('Zip REFUSES .jar even though the builder lists it (executable)',
    accepts($zip, 'jar', 'application/zip'), false);
test('Zip REFUSES .war (executable)', accepts($zip, 'war', 'application/zip'), false);

echo "\n=== 5. All formats keeps the global allow-list ===\n";
$all = field('allformat');
test('All formats accepts .pdf', accepts($all, 'pdf', 'application/pdf'), true);
test('All formats accepts .jpg', accepts($all, 'jpg', 'image/jpeg'), true);
test('All formats accepts .mp4', accepts($all, 'mp4', 'video/mp4'), true);
test('All formats accepts .zip', accepts($all, 'zip', 'application/zip'), true);
test('All formats still REFUSES .pht', accepts($all, 'pht', 'text/plain'), false);
test('All formats still REFUSES .php', accepts($all, 'php', 'text/plain'), false);
test('All formats still REFUSES .html', accepts($all, 'html', 'text/html'), false);

echo "\n=== 6. Customize is unchanged ===\n";
$cst = field('customize', 'pdf, jpg , png');
test('Customize accepts a listed .pdf', accepts($cst, 'pdf', 'application/pdf'), true);
test('Customize accepts a listed .jpg (spaces trimmed)', accepts($cst, 'jpg', 'image/jpeg'), true);
test('Customize REFUSES an unlisted .docx', accepts($cst, 'docx', 'application/zip'), false);
test('Customize cannot admit .pht', accepts(field('customize', 'pht,pdf'), 'pht', 'text/plain'), false);
$dotted = field('customize', '.pdf, .jpg');
test('Customize tolerates leading dots', accepts($dotted, 'pdf', 'application/pdf'), true);
$empty = field('customize', '   ');
test('Customize left blank does not lock the field out', accepts($empty, 'pdf', 'application/pdf'), true);

/* =====================================================================
 * 7. THE REPLY BOX — must NOT be narrowed by the preset rollout.
 * ================================================================== */
echo "\n=== 7. Reply box on a tracked conversation still works ===\n";
test('reply box narrows to nothing (global list applies)',
    Upload_Guard::field_extensions(null, 'response'), array());
test('reply box accepts .pdf', accepts(null, 'pdf', 'application/pdf', 'response'), true);
test('reply box accepts .jpg', accepts(null, 'jpg', 'image/jpeg', 'response'), true);
test('reply box accepts .docx', accepts(null, 'docx', 'application/zip', 'response'), true);
test('reply box accepts .mp4', accepts(null, 'mp4', 'video/mp4', 'response'), true);
test('reply box accepts .zip', accepts(null, 'zip', 'application/zip'  , 'response'), true);
test('reply box accepts .txt', accepts(null, 'txt', 'text/plain', 'response'), true);
test('reply box STILL refuses .pht', accepts(null, 'pht', 'text/plain', 'response'), false);
test('reply box STILL refuses .php', accepts(null, 'php', 'text/plain', 'response'), false);
test('reply box STILL refuses .exe', accepts(null, 'exe', 'application/octet-stream', 'response'), false);
test('reply box STILL refuses .svg', accepts(null, 'svg', 'image/svg+xml', 'response'), false);

echo "\n--- 7b. A stray field definition cannot narrow the reply box ---\n";
test('source=response wins over any field passed in',
    Upload_Guard::field_extensions(field('image'), 'response'), array());

echo "\n--- 7c. Reply box CAN be restricted by a site that wants it ---\n";
add_filter('emsfb_upload_response_box_extensions', function ($e) { return array('pdf', 'jpg'); });
test('filter narrows the reply box to pdf/jpg',
    Upload_Guard::field_extensions(null, 'response'), array('pdf', 'jpg'));
test('restricted reply box accepts .pdf', accepts(null, 'pdf', 'application/pdf', 'response'), true);
test('restricted reply box refuses .docx', accepts(null, 'docx', 'application/zip', 'response'), false);
$GLOBALS['efb_filters']['emsfb_upload_response_box_extensions'] = array();
test('removing the filter restores the global list',
    accepts(null, 'docx', 'application/zip', 'response'), true);

/* =====================================================================
 * 8. Missing / malformed preset must not lock a field out.
 * ================================================================== */
echo "\n=== 8. Degraded field definitions stay usable ===\n";
test('unknown preset does not narrow', Upload_Guard::preset_extensions('somethingelse'), array());
test('empty preset does not narrow', Upload_Guard::preset_extensions(''), array());
$legacy = (object) array('id_' => 'x1', 'type' => 'file'); // no file/value at all
test('a field with no preset key accepts a .pdf', accepts($legacy, 'pdf', 'application/pdf'), true);
$value_only = (object) array('id_' => 'x1', 'type' => 'file', 'value' => 'image');
test('a field with only `value` still applies the preset', accepts($value_only, 'pdf', 'application/pdf'), false);
test('a field with only `value` accepts its own type', accepts($value_only, 'png', 'image/png'), true);
test('`file` wins over `value` when they disagree',
    accepts((object) array('id_' => 'x', 'type' => 'file', 'file' => 'image', 'value' => 'document'), 'png', 'image/png'), true);
test('case is ignored in the preset name',
    accepts((object) array('id_' => 'x', 'type' => 'file', 'file' => 'Document'), 'pdf', 'application/pdf'), true);

echo "\n=== 9. Presets never widen past the security floor ===\n";
add_filter('emsfb_upload_preset_extensions', function ($p) { $p['document'][] = 'php'; return $p; });
test('a filter adding php to Document is still blocked', accepts(field('document'), 'php', 'text/plain'), false);
$GLOBALS['efb_filters']['emsfb_upload_preset_extensions'] = array();

test('a preset entry not in the global map is still refused',
    Upload_Guard::is_allowed_type('xyz', 'text/plain', array('xyz')), false);

echo "\n========================================\n";
echo "RESULTS: $pass passed, $fail failed\n";
echo "========================================\n";

exit($fail > 0 ? 1 : 0);
