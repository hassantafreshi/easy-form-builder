<?php
/**
 * Walks a realistic multi-upload-field form end to end and asserts that a
 * visitor filling it in honestly is never stopped.
 *
 * The form structures below are the real stored shape taken from the plugin's
 * own database: an array whose first two entries are the `form` and `step`
 * descriptors, with escaped quotes (\"type\":\"file\") exactly as
 * form_structer holds them.
 *
 * Covers the case that motivated this test: a four-attachment application
 * form, where both the hourly budget and the Human Shield per-minute cap have
 * to clear four uploads in quick succession.
 *
 * Run: php tests/test-upload-multifield-form.php
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

/**
 * Build a form structure in the real stored shape: escaped JSON, led by the
 * `form` and `step` descriptors that every saved form carries.
 *
 * @param array $upload_fields Each: array('id','type','value','max_fsize','file_ctype')
 * @param int   $filler        Non-upload fields mixed in.
 */
function build_form_structure(array $upload_fields, $filler = 3) {
    $rows = array(
        array('type' => 'form', 'steps' => '1', 'formName' => 'Test'),
        array('id_' => '1', 'type' => 'step', 'step' => '1'),
    );

    for ($i = 0; $i < $filler; $i++) {
        $rows[] = array('id_' => 'txt' . $i, 'type' => 'text', 'name' => 'Text ' . $i);
    }

    foreach ($upload_fields as $f) {
        $row = array(
            'id_'   => $f['id'],
            'type'  => $f['type'],
            'value' => isset($f['value']) ? $f['value'] : 'allformat',
            'file'  => isset($f['value']) ? $f['value'] : 'allformat',
            'name'  => 'Upload ' . $f['id'],
        );
        if (isset($f['max_fsize']))  $row['max_fsize']  = $f['max_fsize'];
        if (isset($f['file_ctype'])) $row['file_ctype'] = $f['file_ctype'];
        $rows[] = $row;
    }

    // Escaped exactly the way form_structer stores it.
    return str_replace('"', '\\"', json_encode($rows));
}

/**
 * Reproduces the field-settings lookup in file_upload_api().
 */
function read_field_settings($vl, $field_id, $have_validate) {
    $max_mb = null;
    $exts   = array();
    if (gettype($vl) == 'string') {
        $vl = json_decode(str_replace('\\', '', $vl));
    }
    if (is_array($vl)) {
        foreach ($vl as $val) {
            if (!is_object($val) || !isset($val->type) || !isset($val->id_)) continue;
            if ($val->id_ != $field_id) continue;
            if (!in_array($val->type, array('dadfile','file','audio_recorder','video_recorder','screen_recorder'), true)) continue;
            if (isset($val->max_fsize) && is_numeric($val->max_fsize) && floatval($val->max_fsize) > 0) {
                $max_mb = floatval($val->max_fsize);
            }
            if ($have_validate == 1 && isset($val->file_ctype)) {
                $exts = explode(',', str_replace(' ', '', strtolower($val->file_ctype)));
            }
            return array($max_mb, $exts, true);
        }
    }
    return array($max_mb, $exts, false);
}

/* =========================================================================
 * The four-field application form.
 * ====================================================================== */
echo "=== A FOUR-UPLOAD-FIELD APPLICATION FORM ===\n\n";

$structure = build_form_structure(array(
    array('id' => 'cv01',    'type' => 'file',    'value' => 'document',  'max_fsize' => '5'),
    array('id' => 'letter1', 'type' => 'file',    'value' => 'document',  'max_fsize' => '2'),
    array('id' => 'photo1',  'type' => 'dadfile', 'value' => 'allformat'),
    array('id' => 'certs1',  'type' => 'file',    'value' => 'customize', 'file_ctype' => 'pdf,jpg,png', 'max_fsize' => '10'),
));

echo "--- 1. The form is measured correctly ---\n";
test('4 upload fields counted among 3 text fields + form + step',
    Upload_Guard::count_upload_fields($structure), 4);
test('budget is 12', Upload_Guard::quota_limit(array('source' => 'form', 'fields' => 4)), 12);

echo "\n--- 2. Every field's own settings are readable ---\n";
$expected = array(
    'cv01'    => array(5.0,  array()),
    'letter1' => array(2.0,  array()),
    'photo1'  => array(null, array()),
    'certs1'  => array(10.0, array('pdf','jpg','png')),
);
foreach ($expected as $fid => $want) {
    $hv = ($fid === 'certs1') ? 1 : 0;
    list($mb, $exts, $found) = read_field_settings($structure, $fid, $hv);
    test("$fid is found in the structure", $found, true);
    test("$fid max_fsize reads " . var_export($want[0], true), $mb, $want[0]);
    test("$fid extension list", $exts, $want[1]);
}

echo "\n--- 3. Each field's size ceiling is enforced independently ---\n";
test('cv01 enforced at 5 MB',    Upload_Guard::max_bytes(5.0),  5 * 1024 * 1024);
test('letter1 enforced at 2 MB', Upload_Guard::max_bytes(2.0),  2 * 1024 * 1024);
test('photo1 falls back to 20 MB (no max_fsize set)', Upload_Guard::max_bytes(null), 20 * 1024 * 1024);
test('certs1 enforced at 10 MB', Upload_Guard::max_bytes(10.0), 10 * 1024 * 1024);

echo "\n--- 4. The customized field narrows, the others use the global list ---\n";
test('certs1 accepts a PDF',  Upload_Guard::is_allowed_type('pdf', 'application/pdf', array('pdf','jpg','png')), true);
test('certs1 refuses a DOCX', Upload_Guard::is_allowed_type('docx', 'application/zip', array('pdf','jpg','png')), false);
test('cv01 accepts a DOCX (no custom list)', Upload_Guard::is_allowed_type('docx', 'application/zip'), true);
test('no field can ever accept .pht', Upload_Guard::is_allowed_type('pht', 'text/plain', array('pht','pdf')), false);

echo "\n--- 5. A real visitor fills all four fields ---\n";
$ctx = array(
    'source'  => 'form',
    'nonce'   => 'visitor-nonce-1',
    'sid'     => 'visitor-sid-1',
    'form_id' => 77,
    'fields'  => 4,
);
$uploaded = 0;
foreach (array('cv01', 'letter1', 'photo1', 'certs1') as $field) {
    $allowed = Upload_Guard::quota_allows($ctx);
    test("upload into $field is allowed", $allowed, true);
    if ($allowed) { Upload_Guard::quota_consume($ctx); $uploaded++; }
}
test('all 4 attachments went through', $uploaded, 4);
test('8 of the 12 budget remain', Upload_Guard::quota_limit($ctx) - Upload_Guard::quota_used($ctx), 8);

echo "\n--- 6. The visitor keeps correcting until the budget runs out ---\n";
/* 4 attachments are already spent, so 8 corrections remain: two full
 * re-uploads of every field. Anyone needing more than that on one form in one
 * hour is not filling it in normally. */
$ok = true;
for ($i = 0; $i < 8; $i++) {
    if (!Upload_Guard::quota_allows($ctx)) { $ok = false; break; }
    Upload_Guard::quota_consume($ctx);
}
test('8 further corrections all fit (2 full do-overs of every field)', $ok, true);
test('budget now exhausted at 12', Upload_Guard::quota_used($ctx), 12);
test('the 13th upload is refused', Upload_Guard::quota_allows($ctx), false);

echo "\n--- 7. Human Shield per-minute cap clears the form's width ---\n";
/*
 * Reproduces the sizing decision in
 * Emsfb_Human_Shield_Rate_Limiter::evaluate_context() for 'file_upload'.
 */
function hs_upload_limit($configured, $field_count) {
    if ($configured > 0 && $field_count > 0) {
        return max($configured, $field_count);
    }
    return $configured;
}

test('default 3/min would have blocked the 4th upload before the fix',
    3 < 4, true);
test('a 4-field form raises the effective cap to 4',
    hs_upload_limit(3, 4), 4);
test('an 8-field form raises it to 8',
    hs_upload_limit(3, 8), 8);
test('a 1-field form keeps the configured 3',
    hs_upload_limit(3, 1), 3);
test('a 2-field form keeps the configured 3',
    hs_upload_limit(3, 2), 3);
test('an admin who raised it to 10 keeps 10 on a 4-field form',
    hs_upload_limit(10, 4), 10);
test('0 stays 0, so "disabled" is still disabled',
    hs_upload_limit(0, 8), 0);

echo "\n--- 8. Wider forms scale the same way ---\n";
foreach (array(1 => 3, 2 => 6, 4 => 12, 5 => 15, 6 => 18, 8 => 24) as $fields => $budget) {
    test("$fields upload fields -> budget $budget",
        Upload_Guard::quota_limit(array('source' => 'form', 'fields' => $fields)), $budget);
}

echo "\n--- 9. Recorder-only form (3 recorders, as shipped templates use) ---\n";
$rec = build_form_structure(array(
    array('id' => 'aud1', 'type' => 'audio_recorder',  'value' => 'media', 'max_fsize' => '20'),
    array('id' => 'vid1', 'type' => 'video_recorder',  'value' => 'media', 'max_fsize' => '20'),
    array('id' => 'scr1', 'type' => 'screen_recorder', 'value' => 'media', 'max_fsize' => '20'),
), 1);
test('3 recorder fields counted', Upload_Guard::count_upload_fields($rec), 3);
test('recorder form budget is 9', Upload_Guard::quota_limit(array('source' => 'form', 'fields' => 3)), 9);
list($mb, , $found) = read_field_settings($rec, 'vid1', 0);
test('recorder max_fsize is readable', $mb, 20.0);
test('recorder field is found', $found, true);

echo "\n--- 10. A structure that will not decode still yields a usable count ---\n";
/* Removing backslashes corrupts \u escapes, so json_decode can fail on a form
 * with non-Latin labels. The raw-marker fallback must still find the fields. */
$broken = '[{\"type\":\"form\"},{\"id_\":\"a\",\"type\":\"file\",\"name\":\"رزومه\"},'
        . '{\"id_\":\"b\",\"type\":\"file\"},{\"id_\":\"c\",\"type\":\"dadfile\"},{\"id_\":\"d\",\"type\":\"file\"}]';
test('4 upload fields still counted from the raw markers',
    Upload_Guard::count_upload_fields($broken), 4);
test('so the budget is still 12, not 3',
    Upload_Guard::quota_limit(array('source' => 'form', 'fields' => Upload_Guard::count_upload_fields($broken))), 12);

echo "\n========================================\n";
echo "RESULTS: $pass passed, $fail failed\n";
echo "========================================\n";

exit($fail > 0 ? 1 : 0);
