<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
/**
 * Entries list fixes from docs/feature-gaps/BUGS-FOUND.fa.md (admin table):
 *   - entry dates are stored in site time, so they must be read in site time
 *     (Emsfb\Entries::format_entry_date);
 *   - a reply by a deleted user must not crash (Entries::responder_name in the
 *     dashboard, the same guard in the public tracker);
 *   - global search escapes LIKE wildcards; JSON_UNESCAPED_UNICODE goes to
 *     json_encode, not isHTML().
 *
 * Run: php tests/test-entries-date-responder.php
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

/* WordPress pins PHP to UTC and keeps the site zone separately. */
date_default_timezone_set('UTC');
define('ABSPATH', __DIR__ . '/');

$GLOBALS['efb_tz']    = 'Asia/Tehran'; // +03:30, no DST since 2022
$GLOBALS['efb_users'] = array(5 => (object) array('ID' => 5, 'display_name' => 'Sara'), 6 => (object) array('ID' => 6, 'display_name' => ''));
function wp_timezone() { return new DateTimeZone($GLOBALS['efb_tz']); }
function wp_date($format, $ts = null) {
    $d = new DateTime('@' . ($ts === null ? time() : $ts));
    $d->setTimezone(wp_timezone());
    return $d->format($format);
}
function get_option($k, $d = false) { return array('date_format' => 'Y-m-d', 'time_format' => 'H:i')[$k] ?? $d; }
function get_user_by($field, $id) { return $GLOBALS['efb_users'][(int) $id] ?? false; }

require_once __DIR__ . '/../includes/admin/class-Emsfb-entries.php';
use Emsfb\Entries;

echo "--- 1. entry dates ---\n";
// The submit handler stores wp_date('Y-m-d H:i:s'): site-local wall time.
$submitted_at = 1762866000; // 2025-11-11 13:00:00 UTC
$stored = wp_date('Y-m-d H:i:s', $submitted_at);
test('fixture: stored value is site-local', $stored, '2025-11-11 16:30:00');
$old = wp_date('Y-m-d H:i', strtotime($stored));
test('fixture: the old strtotime() path showed it shifted by the offset', $old, '2025-11-11 20:00');
test('stored local time is shown unchanged in the site zone', Entries::format_entry_date($stored, 'Y-m-d H:i'), '2025-11-11 16:30');
test('default format is the site date + time format', Entries::format_entry_date($stored), '2025-11-11 16:30');

$GLOBALS['efb_tz'] = 'America/New_York';
$stored = wp_date('Y-m-d H:i:s', $submitted_at);
test('negative offset (New York) round-trips too', Entries::format_entry_date($stored, 'Y-m-d H:i'), wp_date('Y-m-d H:i', $submitted_at));
$GLOBALS['efb_tz'] = 'Europe/Berlin';
$summer = 1751284800; // 2025-06-30 12:00 UTC, Berlin on CEST (+2)
test('DST date in a DST zone round-trips', Entries::format_entry_date(wp_date('Y-m-d H:i:s', $summer), 'Y-m-d H:i'), '2025-06-30 14:00');
$GLOBALS['efb_tz'] = 'UTC';
test('UTC site: unchanged', Entries::format_entry_date('2025-11-11 13:00:00', 'Y-m-d H:i'), '2025-11-11 13:00');
test('zero date is returned as is', Entries::format_entry_date('0000-00-00 00:00:00', 'Y-m-d'), '0000-00-00 00:00:00');
test('unparseable value is returned as is', Entries::format_entry_date('yesterday', 'Y-m-d'), 'yesterday');
test('null becomes empty string', Entries::format_entry_date(null, 'Y-m-d'), '');

echo "\n--- 2. reply author ---\n";
test('existing user shows display name', Entries::responder_name('5', 'Guest', 'Support'), 'Sara');
test('deleted user falls back to guest instead of a fatal', Entries::responder_name(99, 'Guest', 'Support'), 'Guest');
test('user with empty display name falls back to guest', Entries::responder_name(6, 'Guest', 'Support'), 'Guest');
test('-1 is the support team', Entries::responder_name('-1', 'Guest', 'Support'), 'Support');
test('0 is a guest', Entries::responder_name(0, 'Guest', 'Support'), 'Guest');

echo "\n--- 3. wiring in the shipped handlers ---\n";
$admin  = file_get_contents(__DIR__ . '/../includes/admin/class-Emsfb-admin.php');
$public = file_get_contents(__DIR__ . '/../includes/class-Emsfb-public.php');
test('admin loads the Entries helpers', strpos($admin, "require_once __DIR__ . '/class-Emsfb-entries.php';") !== false, true);
test('entries list formats dates with Entries::format_entry_date', strpos($admin, 'Entries::format_entry_date( $row->date, $date_format )') !== false, true);
test('no strtotime() on stored entry dates left in admin', (bool) preg_match('/strtotime\(\s*\$row->date\s*\)/', $admin), false);
test('dashboard replies use Entries::responder_name', strpos($admin, "Entries::responder_name(\$val->rsp_by, \$lang['guest'], \$lang['spprt'])") !== false, true);
test('no unguarded $usr->display_name after get_user_by in admin', (bool) preg_match('/get_user_by\([^;]*;\s*\$val->rsp_by\s*=\s*\$usr->display_name/', $admin), false);
test('public tracker guards a deleted user', strpos($public, "\$val->rsp_by= (\$usr && !empty(\$usr->display_name)) ? \$usr->display_name : \$lanText['guest'];") !== false, true);
test('global search escapes LIKE wildcards', strpos($admin, "\$search_term = '%' . \$this->db->esc_like(\$id) . '%';") !== false, true);
test('raw "%$id%" search term is gone', strpos($admin, '"%$id%"'), false);
test('JSON_UNESCAPED_UNICODE is passed to json_encode, not isHTML', strpos($admin, "\$this->isHTML(json_encode(\$_POST['value'] ?? '', JSON_UNESCAPED_UNICODE))") !== false, true);
test('the misplaced isHTML(..., JSON_UNESCAPED_UNICODE) call is gone', (bool) preg_match('/isHTML\(json_encode\([^()]*\),\s*JSON_UNESCAPED_UNICODE\)/', $admin), false);

echo "\n--- 4. LIKE escaping behaviour ---\n";
// Same rule as wpdb::esc_like(): backslash, % and _ become literals.
$esc = function ($t) { return addcslashes($t, '_%\\'); };
$like = function ($pattern, $subject) {
    $re = '';
    for ($i = 0, $n = strlen($pattern); $i < $n; $i++) {
        $c = $pattern[$i];
        if ($c === '\\' && $i + 1 < $n) { $re .= preg_quote($pattern[++$i], '/'); continue; }
        $re .= $c === '%' ? '.*' : ($c === '_' ? '.' : preg_quote($c, '/'));
    }
    return (bool) preg_match('/^' . $re . '$/s', $subject);
};
test('old: "%" matched every entry', $like('%%%', '{"value":"hello"}'), true);
test('new: "%" only matches a literal percent', array($like('%' . $esc('%') . '%', '{"value":"hello"}'), $like('%' . $esc('%') . '%', '{"value":"50% off"}')), array(false, true));
test('new: "_" is literal', array($like('%' . $esc('a_b') . '%', 'axb'), $like('%' . $esc('a_b') . '%', 'a_b')), array(false, true));

echo "\n========================================\n";
echo "RESULTS: $pass passed, $fail failed\n";
echo "========================================\n";
exit($fail > 0 ? 1 : 0);
