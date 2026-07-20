<?php
/**
 * Standalone regression test for the tracking-code lookup rate limiter.
 * Mirrors the exact logic added to includes/class-Emsfb-public.php:
 *   - efb_track_lookup_allowed()   (burst cap + failure hard-block)
 *   - efb_track_register_failure() (sliding failure window)
 *
 * Goal: prove enumeration gets throttled while realistic legitimate use of the
 * response box (a handful of manual lookups, incl. successes) is never blocked.
 *
 * Run: php tests/test-track-lookup-ratelimit.php
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

if (!defined('MINUTE_IN_SECONDS')) define('MINUTE_IN_SECONDS', 60);

/*
 * Minimal transient store with virtual-clock TTL, matching WP semantics:
 * get_transient() returns false once expired; set_transient() (re)sets TTL.
 */
class FakeTransients {
    public $now = 0;              // virtual clock (seconds)
    private $store = [];          // key => [value, expires_at]
    public function get($k) {
        if (!isset($this->store[$k])) return false;
        if ($this->store[$k][1] <= $this->now) { unset($this->store[$k]); return false; }
        return $this->store[$k][0];
    }
    public function set($k, $v, $ttl) { $this->store[$k] = [$v, $this->now + $ttl]; }
    public function advance($s) { $this->now += $s; }
}

/*
 * Faithful port of the two private methods. $ip='' models an undeterminable IP.
 * Thresholds match the shipped defaults (also filterable in the real code).
 */
class TrackLimiter {
    const FAIL_MAX = 25;
    const FAIL_WINDOW = 900;   // 15 * MINUTE_IN_SECONDS
    const BURST_MAX = 40;      // DoS backstop, kept ABOVE FAIL_MAX on purpose
    const BURST_WINDOW = 30;

    public $t;
    public function __construct(FakeTransients $t) { $this->t = $t; }

    public function allowed($ip) {
        if ($ip === '') return true;
        $h = md5($ip);
        if (((int)$this->t->get('efb_trk_f_' . $h)) >= self::FAIL_MAX) return false;
        // Fixed-window burst counter via a per-window time bucket (no sliding TTL).
        $bucket = (int) floor($this->t->now / self::BURST_WINDOW);
        $bkey = 'efb_trk_b_' . $h . '_' . $bucket;
        $burst = (int)$this->t->get($bkey);
        if ($burst >= self::BURST_MAX) return false;
        $this->t->set($bkey, $burst + 1, self::BURST_WINDOW + 5);
        return true;
    }

    public function register_failure($ip) {
        if ($ip === '') return;
        $h = md5($ip);
        $fails = (int)$this->t->get('efb_trk_f_' . $h);
        $this->t->set('efb_trk_f_' . $h, $fails + 1, self::FAIL_WINDOW);
    }
}

/*
 * Simulates one lookup end-to-end: enforce limiter, then (if allowed) resolve
 * the code against $valid_codes and register a failure on a miss — exactly the
 * order the endpoint uses. Returns 'blocked' | 'found' | 'notfound'.
 */
function do_lookup(TrackLimiter $lim, $ip, $code, array $valid_codes) {
    if (!$lim->allowed($ip)) return 'blocked';
    if (in_array($code, $valid_codes, true)) return 'found';
    $lim->register_failure($ip);
    return 'notfound';
}

// ---------------------------------------------------------------------------
echo "--- NO DISRUPTION: realistic legitimate response-box usage ---\n";
$t = new FakeTransients(); $lim = new TrackLimiter($t);
$valid = ['260703-AB7KQ'];
$legit_ip = '203.0.113.9';
// user mistypes twice, then enters the correct code, then re-checks it 3x
$seq = ['260703-XXXXX','260703-YYYYY','260703-AB7KQ','260703-AB7KQ','260703-AB7KQ','260703-AB7KQ'];
$results = array_map(fn($c) => do_lookup($lim, $legit_ip, $c, $valid), $seq);
test('legit user never blocked', in_array('blocked', $results, true), false);
test('legit correct code resolves as found', do_lookup($lim, $legit_ip, '260703-AB7KQ', $valid), 'found');

echo "\n--- NO DISRUPTION: heavy but valid re-checking (20 successful lookups) ---\n";
$t = new FakeTransients(); $lim = new TrackLimiter($t);
$blocked_any = false;
for ($i = 0; $i < 20; $i++) {
    // spread across time so the 15/30s burst window never trips for a human
    $t->advance(3);
    if (do_lookup($lim, '198.51.100.5', '260703-AB7KQ', $valid) === 'blocked') $blocked_any = true;
}
test('20 spaced successful lookups never blocked', $blocked_any, false);

echo "\n--- SECURITY: enumeration is throttled by the failure cap ---\n";
$t = new FakeTransients(); $lim = new TrackLimiter($t);
$atk = '192.0.2.66';
$reached = 0; $blocked_at = null;
for ($i = 0; $i < 200; $i++) {
    $t->advance(2); // stay clear of the 30s burst window to isolate the failure cap
    $r = do_lookup($lim, $atk, 'guess-' . $i, $valid); // always wrong
    if ($r === 'blocked') { $blocked_at = $i; break; }
    if ($r === 'notfound') $reached++;
}
test('attacker gets hard-blocked', $blocked_at !== null, true);
test('DB is spared after FAIL_MAX misses (<=25 reached)', $reached <= 25, true);

echo "\n--- SECURITY: rapid burst is capped within the fixed window ---\n";
$t = new FakeTransients(); $lim = new TrackLimiter($t);
$rapid = '192.0.2.77';
$allowed_in_burst = 0;
for ($i = 0; $i < 60; $i++) { // all within the same 30s bucket (no advance)
    if ($lim->allowed($rapid) === true) $allowed_in_burst++;
}
test('burst capped at BURST_MAX (40) per fixed window', $allowed_in_burst, 40);
// and the very next fixed window starts fresh
$t->advance(TrackLimiter::BURST_WINDOW);
test('burst counter resets in the next fixed window', $lim->allowed($rapid), true);

echo "\n--- RECOVERY: block clears after the failure window elapses ---\n";
$t = new FakeTransients(); $lim = new TrackLimiter($t);
$ip = '192.0.2.88';
for ($i = 0; $i < 30; $i++) { $t->advance(2); do_lookup($lim, $ip, 'x-' . $i, $valid); }
test('blocked while window active', $lim->allowed($ip), false);
$t->advance(TrackLimiter::FAIL_WINDOW + 1);
test('allowed again after window expiry', $lim->allowed($ip), true);

echo "\n--- EDGE: undeterminable IP is never locked out ---\n";
$t = new FakeTransients(); $lim = new TrackLimiter($t);
$never = true;
for ($i = 0; $i < 100; $i++) { if (do_lookup($lim, '', 'z-' . $i, $valid) === 'blocked') $never = false; }
test('empty IP never blocked', $never, true);

echo "\n--- ISOLATION: one attacker IP does not throttle another visitor ---\n";
$t = new FakeTransients(); $lim = new TrackLimiter($t);
for ($i = 0; $i < 30; $i++) { $t->advance(2); do_lookup($lim, '192.0.2.1', 'q-' . $i, $valid); } // attacker blocked
test('attacker IP blocked', $lim->allowed('192.0.2.1'), false);
test('different visitor IP still allowed', $lim->allowed('203.0.113.200'), true);

echo "\n========================================\n";
echo "RESULTS: $pass passed, $fail failed\n";
echo "========================================\n";
exit($fail > 0 ? 1 : 0);
