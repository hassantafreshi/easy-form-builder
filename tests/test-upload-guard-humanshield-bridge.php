<?php
/**
 * Verifies the contract between Emsfb\Upload_Guard (core, always on) and the
 * Human Shield add-on's override settings.
 *
 * The rule being tested: with the add-on absent or every box left at 0, the
 * core's derived budget applies unchanged. A non-zero box takes over. The size
 * ceiling is the one override that may only tighten, never widen, so an add-on
 * setting can never overrule a stricter per-field limit.
 *
 * The add-on's own closures are reproduced here exactly as
 * Emsfb_Human_Shield::register_upload_overrides() registers them, because that
 * class cannot load without the full WordPress + add-on runtime.
 *
 * Run: php tests/test-upload-guard-humanshield-bridge.php
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
$GLOBALS['efb_host_limit'] = 512 * 1024 * 1024;

function add_filter($hook, $callback, $priority = 10, $args = 1) { $GLOBALS['efb_filters'][$hook][] = $callback; }
function apply_filters($hook, $value) {
    $extra = array_slice(func_get_args(), 2);
    if (empty($GLOBALS['efb_filters'][$hook])) return $value;
    foreach ($GLOBALS['efb_filters'][$hook] as $cb) {
        $value = call_user_func_array($cb, array_merge(array($value), $extra));
    }
    return $value;
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
function emsfb_is_php_function_available_efb($n) { return function_exists($n); }

require_once __DIR__ . '/../includes/class-Emsfb-upload-guard.php';

use Emsfb\Upload_Guard;

/**
 * The add-on's shipped defaults for the four override keys. Must stay in sync
 * with Emsfb_Human_Shield::defaults().
 */
function hs_defaults() {
    return array(
        'upload_retry_allowance'      => 0,
        'upload_quota_per_window'     => 0,
        'upload_quota_window_seconds' => 0,
        'upload_max_mb'               => 0,
    );
}

/**
 * Register the same four closures as
 * Emsfb_Human_Shield::register_upload_overrides().
 */
function hs_register_overrides(array $settings) {
    $GLOBALS['efb_filters'] = array();

    add_filter('emsfb_upload_retry_allowance', function ($allowance, $context) use ($settings) {
        $configured = (int) $settings['upload_retry_allowance'];
        return $configured > 0 ? $configured : $allowance;
    }, 10, 2);

    add_filter('emsfb_upload_quota_limit', function ($limit, $context) use ($settings) {
        $configured = (int) $settings['upload_quota_per_window'];
        return $configured > 0 ? $configured : $limit;
    }, 10, 2);

    add_filter('emsfb_upload_quota_window', function ($window, $context) use ($settings) {
        $configured = (int) $settings['upload_quota_window_seconds'];
        return $configured > 0 ? $configured : $window;
    }, 10, 2);

    add_filter('emsfb_upload_max_bytes', function ($bytes, $context) use ($settings) {
        $configured = (int) $settings['upload_max_mb'];
        if ($configured <= 0) {
            return $bytes;
        }
        return min((int) $bytes, $configured * 1024 * 1024);
    }, 10, 2);
}

$response = array('source' => 'response');
$form2    = array('source' => 'form', 'fields' => 2);

/* ------------------------------------------------------------------------ */
echo "--- A. Add-on absent: core budget applies ---\n";
$GLOBALS['efb_filters'] = array();
test('response box = 3', Upload_Guard::quota_limit($response), 3);
test('2-field form = 6', Upload_Guard::quota_limit($form2), 6);
test('window = 3600', Upload_Guard::quota_window(array()), 3600);
test('field 8 MB honoured', Upload_Guard::max_bytes(8), 8 * 1024 * 1024);

/* ------------------------------------------------------------------------ */
echo "\n--- B. Add-on active, shipped defaults: nothing changes ---\n";
hs_register_overrides(hs_defaults());
test('response box still 3', Upload_Guard::quota_limit($response), 3);
test('2-field form still 6', Upload_Guard::quota_limit($form2), 6);
test('window still 3600', Upload_Guard::quota_window(array()), 3600);
test('field 8 MB still honoured', Upload_Guard::max_bytes(8), 8 * 1024 * 1024);

/* ------------------------------------------------------------------------ */
echo "\n--- C. Owner raises the per-field allowance to 10 ---\n";
hs_register_overrides(array_merge(hs_defaults(), array('upload_retry_allowance' => 10)));
test('response box = 10', Upload_Guard::quota_limit($response), 10);
test('2-field form = 20', Upload_Guard::quota_limit($form2), 20);

/* ------------------------------------------------------------------------ */
echo "\n--- D. Owner tightens the per-field allowance to 1 ---\n";
hs_register_overrides(array_merge(hs_defaults(), array('upload_retry_allowance' => 1)));
test('response box = 1', Upload_Guard::quota_limit($response), 1);
test('2-field form = 2', Upload_Guard::quota_limit($form2), 2);

/* ------------------------------------------------------------------------ */
echo "\n--- E. Owner sets a flat total, ignoring form shape ---\n";
hs_register_overrides(array_merge(hs_defaults(), array('upload_quota_per_window' => 5)));
test('response box = 5', Upload_Guard::quota_limit($response), 5);
test('2-field form = 5 too', Upload_Guard::quota_limit($form2), 5);
test('8-field form = 5 as well', Upload_Guard::quota_limit(array('source' => 'form', 'fields' => 8)), 5);

echo "\n--- E2. Flat total wins over the per-field allowance ---\n";
hs_register_overrides(array_merge(hs_defaults(), array(
    'upload_retry_allowance'  => 10,
    'upload_quota_per_window' => 4,
)));
test('flat total is the final word', Upload_Guard::quota_limit($form2), 4);

/* ------------------------------------------------------------------------ */
echo "\n--- F. Owner shortens the reset window ---\n";
hs_register_overrides(array_merge(hs_defaults(), array('upload_quota_window_seconds' => 300)));
test('window = 300', Upload_Guard::quota_window(array()), 300);
hs_register_overrides(array_merge(hs_defaults(), array('upload_quota_window_seconds' => 10)));
test('an unreasonably short window is floored to 60', Upload_Guard::quota_window(array()), 60);

/* ------------------------------------------------------------------------ */
echo "\n--- G. Size ceiling may only tighten ---\n";
hs_register_overrides(array_merge(hs_defaults(), array('upload_max_mb' => 5)));
test('add-on 5 MB tightens a 20 MB default', Upload_Guard::max_bytes(null), 5 * 1024 * 1024);
test('add-on 5 MB tightens a 50 MB field', Upload_Guard::max_bytes(50), 5 * 1024 * 1024);
test('add-on 5 MB does NOT widen a 2 MB field', Upload_Guard::max_bytes(2), 2 * 1024 * 1024);

hs_register_overrides(array_merge(hs_defaults(), array('upload_max_mb' => 200)));
test('a generous add-on ceiling still cannot widen a 2 MB field', Upload_Guard::max_bytes(2), 2 * 1024 * 1024);

$GLOBALS['efb_host_limit'] = 8 * 1024 * 1024;
test('the host limit still wins over everything', Upload_Guard::max_bytes(50), 8 * 1024 * 1024);
$GLOBALS['efb_host_limit'] = 512 * 1024 * 1024;

/* ------------------------------------------------------------------------ */
echo "\n--- H. Overrides drive the actual counter, not just the number ---\n";
hs_register_overrides(array_merge(hs_defaults(), array('upload_quota_per_window' => 2)));
$ctx = array('source' => 'response', 'nonce' => 'bridge-test', 'sid' => 'b1', 'form_id' => 0);
$GLOBALS['efb_transients'] = array();

$accepted = 0;
for ($i = 1; $i <= 10; $i++) {
    if (Upload_Guard::quota_allows($ctx)) {
        Upload_Guard::quota_consume($ctx);
        $accepted++;
    }
}
test('a limit of 2 stops the 3rd upload', $accepted, 2);

echo "\n========================================\n";
echo "RESULTS: $pass passed, $fail failed\n";
echo "========================================\n";

exit($fail > 0 ? 1 : 0);
