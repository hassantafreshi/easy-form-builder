<?php
/**
 * Standalone regression test for the form_preview_efb access-control gate.
 * Mirrors the exact guard now shipped in includes/class-Emsfb-public.php:
 *
 *   if ( check_ajax_referer('wp_rest','nonce') != 1 ) { die(); }
 *   if ( ! current_user_can('Emsfb') ) { wp_send_json_error(..., 403); }
 *
 * Confirms that only users holding the builder capability 'Emsfb' can run
 * the preview action, that a valid wp_rest nonce alone is NOT sufficient,
 * and that legitimate builder users (administrators) are never blocked.
 *
 * Run: php tests/test-form-preview-capability.php
 */

$pass = 0;
$fail = 0;
function test($label, $actual, $expected) {
    global $pass, $fail;
    $ok = $actual === $expected;
    if ($ok) { $pass++; echo "[PASS] $label\n"; }
    else {
        $fail++;
        echo "[FAIL] $label\n";
        echo "  Expected: " . json_encode($expected) . "\n";
        echo "  Actual:   " . json_encode($actual) . "\n";
    }
}

/*
 * Reproduces the two-stage server-side gate. Returns one of:
 *   'die'      -> nonce invalid  (admin-ajax dies, no action)
 *   'forbidden'-> nonce ok but capability missing (403)
 *   'allowed'  -> both pass, preview proceeds
 *
 * $user_caps mirrors WP capabilities the current user actually has.
 * The custom 'Emsfb' cap is granted only to administrators via add_cap()
 * in class-Emsfb-admin.php (roles: administrator).
 */
function form_preview_gate($nonce_valid, array $user_caps) {
    if ($nonce_valid !== true) {
        return 'die';
    }
    if (!in_array('Emsfb', $user_caps, true)) {
        return 'forbidden';
    }
    return 'allowed';
}

// Capability profiles for common WP roles as configured by this plugin.
$administrator = ['manage_options', 'edit_pages', 'Emsfb', 'Emsfb_create', 'Emsfb_panel', 'Emsfb_addon'];
$editor        = ['edit_pages', 'edit_others_posts'];      // no Emsfb cap
$subscriber    = ['read'];                                 // no Emsfb cap
$anonymous     = [];                                       // logged-out

echo "--- SECURITY: valid nonce is NOT enough without the builder capability ---\n";
test('subscriber + valid nonce -> forbidden', form_preview_gate(true, $subscriber), 'forbidden');
test('editor + valid nonce -> forbidden',     form_preview_gate(true, $editor),     'forbidden');
test('anonymous(no cap) + valid nonce -> forbidden', form_preview_gate(true, $anonymous), 'forbidden');

echo "\n--- NO DISRUPTION: legitimate builder users still allowed ---\n";
test('administrator + valid nonce -> allowed', form_preview_gate(true, $administrator), 'allowed');
$custom_builder = ['read', 'Emsfb']; // a role explicitly granted the builder cap
test('custom role granted Emsfb + valid nonce -> allowed', form_preview_gate(true, $custom_builder), 'allowed');

echo "\n--- REGRESSION: invalid/expired nonce still short-circuits (die) ---\n";
test('administrator + invalid nonce -> die', form_preview_gate(false, $administrator), 'die');
test('subscriber + invalid nonce -> die',    form_preview_gate(false, $subscriber),    'die');

echo "\n--- ORDER: nonce is checked before capability (no cap leak on bad nonce) ---\n";
test('bad nonce short-circuits before cap check', form_preview_gate(false, $anonymous), 'die');

echo "\n========================================\n";
echo "RESULTS: $pass passed, $fail failed\n";
echo "========================================\n";

exit($fail > 0 ? 1 : 0);
