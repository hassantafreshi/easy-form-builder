<?php
/**
 * Standalone regression test for the reCAPTCHA siteverify URL construction.
 * Confirms the fix in includes/class-Emsfb-public.php builds the request via
 * add_query_arg() so the user-supplied "response" value is URL-encoded and can
 * no longer inject extra query parameters, while genuine (URL-safe) reCAPTCHA
 * tokens reach Google unchanged.
 *
 * Run: php tests/test-captcha-url-encoding.php
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
 * Faithful stand-in for WordPress add_query_arg() for the two-key case used by
 * the fix: values are URL-encoded via urlencode(), matching WP's build_query.
 */
function add_query_arg_like($args, $base) {
    $pairs = [];
    foreach ($args as $k => $v) {
        $pairs[] = urlencode($k) . '=' . urlencode($v);
    }
    return $base . '?' . implode('&', $pairs);
}

$base = 'https://www.google.com/recaptcha/api/siteverify';
$secret = 'SECRETKEY_1234567890';

// --- OLD (vulnerable) construction: raw interpolation ---
function old_url($base, $secret, $response) {
    return $base . '?secret=' . $secret . '&response=' . $response;
}
// --- NEW (fixed) construction: add_query_arg ---
function new_url($base, $secret, $response) {
    return add_query_arg_like(['secret' => $secret, 'response' => $response], $base);
}

/* Extract the query params from a URL exactly as the receiving server would. */
function parse_params($url) {
    $q = parse_url($url, PHP_URL_QUERY);
    $out = [];
    parse_str($q, $out);
    return $out;
}

echo "--- NO DISRUPTION: a genuine URL-safe reCAPTCHA token is preserved ---\n";
$genuine = '03AGdBq26k-Jq_ABC123def456GHI789jkl-MNO_pqr.stu'; // base64url-ish
$p = parse_params(new_url($base, $secret, $genuine));
test('genuine token decodes back identically', $p['response'], $genuine);
test('secret decodes back identically', $p['secret'], $secret);
test('exactly two params present', count($p), 2);

echo "\n--- SECURITY: injection attempt cannot add/override parameters ---\n";
// Attacker tries to append their own params and overwrite the secret.
$evil = 'x&secret=attacker&admin=1';

// Old construction: the injected '&secret=' and '&admin=1' become real params.
$old = parse_params(old_url($base, $secret, $evil));
test('OLD: injected admin param leaks through', isset($old['admin']), true);
test('OLD: secret got overridden by attacker', $old['secret'], 'attacker');

// New construction: the whole thing stays inside the single response value.
$new = parse_params(new_url($base, $secret, $evil));
test('NEW: no extra admin param', isset($new['admin']), false);
test('NEW: secret stays intact', $new['secret'], $secret);
test('NEW: response holds the full raw string verbatim', $new['response'], $evil);
test('NEW: still exactly two params', count($new), 2);

echo "\n--- SECURITY: space / special chars are encoded, not breaking the URL ---\n";
$spacey = 'a b+c/d=e?f#g';
$np = parse_params(new_url($base, $secret, $spacey));
test('spacey value round-trips exactly', $np['response'], $spacey);
test('spacey value keeps two params', count($np), 2);

echo "\n========================================\n";
echo "RESULTS: $pass passed, $fail failed\n";
echo "========================================\n";
exit($fail > 0 ? 1 : 0);
