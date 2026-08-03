<?php
/**
 * Standalone model test for the nonce/refresh policy + sliding session
 * extension. Mirrors the logic shipped in:
 *   - includes/class-Emsfb-public.php  efb_nonce_refresh_api()
 *   - includes/functions.php           efb_code_touch_session()
 *
 * POLICY CHANGE (2026-07-30). This file previously asserted that a fresh nonce
 * is issued only to a "proven live session" (logged-in OR valid sid), and
 * assumed that an anonymous visitor without one would simply reload and
 * self-heal. That assumption was wrong and it hid a serious defect: on a site
 * with full-page caching the sid is frozen into the stored HTML alongside the
 * nonce, so by the time a refresh is needed the sid has expired too. The
 * refresh then 403s, and reloading re-serves the very same cached HTML with the
 * very same dead nonce - the form stays unsubmittable until someone purges the
 * cache. See docs/audits/2026-07-30-sales-and-installs-decline-root-cause.md.
 *
 * The sid requirement also protected nothing. For a logged-out visitor
 * wp_create_nonce('wp_rest') is derived from user id 0 and an empty session
 * token, so every anonymous visitor shares one value and anyone can mint one by
 * loading any page containing a form.
 *
 * Current policy: logged-in -> nonce; anonymous -> nonce, per-IP rate limited;
 * the sid is still used, but only to slide a live session forward.
 *
 * NOTE: this file models the policy. The real endpoint is exercised over HTTP
 * in tests/test-decline-root-cause-regressions.php (checks A2.1 / A2.2), which
 * is what stops the model and the shipped code drifting apart again.
 *
 * Run: php tests/test-nonce-refresh-session-gating.php
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
 * In-memory model of the emsfb_stts_ session table + a virtual clock.
 * Each session: sid => ['active'=>1|0, 'created'=>ts, 'read_date'=>ts].
 */
class SessionTable {
    public $now = 1000000;          // virtual clock (unix-ish seconds)
    public $rows = [];              // sid => row
    const DAY = 86400;

    public function create($sid, $session_duration_days = 1, $created_offset = 0) {
        $created = $this->now + $created_offset;
        $this->rows[$sid] = [
            'active'    => 1,
            'created'   => $created,
            'read_date' => $created + $session_duration_days * self::DAY,
        ];
    }

    // Mirrors efb_code_validate_select() primary path: active row, not expired.
    public function validate_select($sid, $fid = 0) {
        if ($sid === '' || !isset($this->rows[$sid])) return false;
        $r = $this->rows[$sid];
        return $r['active'] === 1 && $r['read_date'] > $this->now;
    }

    // Mirrors efb_code_touch_session(): extend read_date only for an active row
    // whose creation is still within the absolute cap. Returns rows affected.
    public function touch_session($sid, $session_duration_days = 1, $max_days = 7) {
        if (!isset($this->rows[$sid])) return 0;
        $r =& $this->rows[$sid];
        $cap_cutoff = $this->now - $max_days * self::DAY;
        if ($r['active'] === 1 && $r['created'] > $cap_cutoff) {
            $r['read_date'] = $this->now + $session_duration_days * self::DAY;
            return 1;
        }
        return 0;
    }

    public function advance_days($d) { $this->now += $d * self::DAY; }
    public function advance_secs($s) { $this->now += $s; }
    public function read_date($sid) { return $this->rows[$sid]['read_date'] ?? null; }
}

/*
 * Port of efb_nonce_refresh_api(): returns 'nonce' when a fresh token would be
 * issued, or 'throttled' (429) when the per-IP limit is hit. The sid never gates
 * the token any more; it is only used to slide a live session forward.
 */
function nonce_refresh_gate(SessionTable $t, $is_logged_in, $sid, $fid = 0, $rate_ok = true) {
    if ($is_logged_in) return 'nonce';
    if (!$rate_ok) return 'throttled';
    if ($sid !== '' && $t->validate_select($sid, $fid)) {
        $t->touch_session($sid);
    }
    return 'nonce';
}

// ---------------------------------------------------------------------------
echo "--- POLICY: an anonymous visitor can always recover a nonce ---\n";
$t = new SessionTable();
$t->create('SID_VALID');
test('logged-in user (no sid) -> nonce', nonce_refresh_gate($t, true, ''), 'nonce');
test('anonymous, no sid -> nonce (cached page, sid long gone)', nonce_refresh_gate($t, false, ''), 'nonce');
test('anonymous, unknown sid -> nonce', nonce_refresh_gate($t, false, 'SID_FAKE'), 'nonce');
test('anonymous, valid active sid -> nonce', nonce_refresh_gate($t, false, 'SID_VALID'), 'nonce');
test('anonymous over the per-IP limit -> throttled', nonce_refresh_gate($t, false, '', 0, false), 'throttled');

echo "\n--- POLICY: an expired/inactive session no longer blocks recovery ---\n";
$t = new SessionTable();
$t->create('SID_EXPIRED', 1);
$t->advance_days(2); // read_date was now+1d; now +2d => expired
test('anonymous, expired sid -> nonce (was the cached-page dead end)', nonce_refresh_gate($t, false, 'SID_EXPIRED'), 'nonce');
$t2 = new SessionTable();
$t2->create('SID_INACTIVE');
$t2->rows['SID_INACTIVE']['active'] = 0;
test('anonymous, inactive sid -> nonce', nonce_refresh_gate($t2, false, 'SID_INACTIVE'), 'nonce');
test('an inactive session is still not slid forward', $t2->touch_session('SID_INACTIVE'), 0);

echo "\n--- SLIDING WINDOW: a long-open form keeps refreshing while active ---\n";
$t = new SessionTable();
$t->create('SID_OPEN', 1); // 1-day window
$ok = true;
// Simulate the form staying open for ~5 days, refreshing every ~20 hours
// (i.e., before each 1-day window would lapse).
for ($i = 0; $i < 6; $i++) {
    $t->advance_secs((int)(0.83 * SessionTable::DAY)); // ~20h
    if (nonce_refresh_gate($t, false, 'SID_OPEN') !== 'nonce') { $ok = false; break; }
}
test('form open ~5 days keeps getting nonces', $ok, true);
test('session still valid after sustained refreshing', $t->validate_select('SID_OPEN'), true);

echo "\n--- ABSOLUTE CAP: an abandoned/too-old session cannot be revived ---\n";
$t = new SessionTable();
// Session created 8 days ago (beyond the 7-day cap) but somehow still 'active'.
$t->create('SID_OLD', 30, -8 * SessionTable::DAY); // created 8d ago, wide read window
$affected = $t->touch_session('SID_OLD', 1, 7);
test('touch does not extend a session past the absolute cap', $affected, 0);

echo "\n--- CAP BOUNDARY: a session just inside the cap is still extendable ---\n";
$t = new SessionTable();
$t->create('SID_EDGE', 1, -6 * SessionTable::DAY); // created 6d ago (< 7d cap)
$before = $t->read_date('SID_EDGE');
$affected = $t->touch_session('SID_EDGE', 1, 7);
test('touch extends a session still within the cap', $affected, 1);
test('read_date actually moved forward', $t->read_date('SID_EDGE') > $before, true);

echo "\n--- NO DISRUPTION: normal submit with a valid nonce never needs refresh ---\n";
// The refresh path only runs on HTTP 403; a valid nonce short-circuits it.
function submit_flow($nonce_valid) {
    if ($nonce_valid) return 'submitted';       // 200, no refresh attempted
    return 'refresh_then_retry';                // 403 path
}
test('valid nonce -> submitted without touching refresh', submit_flow(true), 'submitted');
test('expired nonce -> enters refresh/retry path', submit_flow(false), 'refresh_then_retry');

echo "\n--- CACHED PAGE: the scenario that used to be a dead end ---\n";
// A caching plugin stored the page days ago. Both the nonce and the sid baked
// into that HTML are stale, so the submit 403s and the sid cannot vouch for
// anything. Reloading is no escape: the same cached bytes come back.
$t = new SessionTable();
$t->create('SID_CACHED', 1, -3 * SessionTable::DAY); // minted 3 days ago
test('cached-page sid is expired', $t->validate_select('SID_CACHED'), false);
test('...but the visitor still gets a nonce and can submit', nonce_refresh_gate($t, false, 'SID_CACHED'), 'nonce');
// And an even older cached copy, where no sid is sent at all, must also work.
test('cached page with no sid at all -> nonce', nonce_refresh_gate($t, false, ''), 'nonce');

echo "\n========================================\n";
echo "RESULTS: $pass passed, $fail failed\n";
echo "========================================\n";
exit($fail > 0 ? 1 : 0);
