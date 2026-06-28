<?php
/**
 * Standalone regression test for password recovery token isolation.
 * Run: php tests/test-recovery-token-security.php
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
        echo "  Expected: " . json_encode($expected) . "\n";
        echo "  Actual:   " . json_encode($actual) . "\n";
    }
}

function generate_recovery_token($existing_codes = array()) {
    do {
        $token = bin2hex(random_bytes(16));
    } while (isset($existing_codes[$token]));

    return $token;
}

function token_can_open_recovery_form($token, $temp_links) {
    if (!is_string($token) || strlen($token) < 32) {
        return false;
    }
    return isset($temp_links[$token])
        && $temp_links[$token]['status_'] === 0
        && $temp_links[$token]['age'] < 86400;
}

function consume_recovery_token($token, &$temp_links) {
    if (!token_can_open_recovery_form($token, $temp_links)) {
        return false;
    }
    unset($temp_links[$token]);
    return true;
}

$public_sid = '260627094352e496edd0c';
$temp_links = array();

$secret_token = generate_recovery_token($temp_links);
$temp_links[$secret_token] = array(
    'username' => 'ddddd',
    'status_' => 0,
    'age' => 60,
);

test('recovery token is not the public sid', $secret_token === $public_sid, false);
test('recovery token has secret-token length', strlen($secret_token) >= 32, true);
test('public sid cannot open recovery form', token_can_open_recovery_form($public_sid, $temp_links), false);
test('secret token can open recovery form', token_can_open_recovery_form($secret_token, $temp_links), true);
test('secret token can be consumed once', consume_recovery_token($secret_token, $temp_links), true);
test('consumed token cannot be reused', consume_recovery_token($secret_token, $temp_links), false);

echo "\n========================================\n";
echo "RESULTS: $pass passed, $fail failed\n";
echo "========================================\n";

exit($fail > 0 ? 1 : 0);
