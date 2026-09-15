<?php
/**
 * Problem-report emails must not look like form traffic.
 *
 * The add-on reinstall report (state addonsDlProblem) went through the default
 * "tracking link" renderer, so it arrived headed "New message!" with a
 * "View message and reply" button pointing at http://null. That renderer also
 * strips every &quot; from the body, mangling escaped server errors.
 *
 * The other states sharing email_template_efb() are rendered alongside so a
 * fix for the report cannot quietly change them.
 *
 * Run: php tests/test-problem-report-email-template.php
 */

define('ABSPATH', __DIR__ . '/');
define('EMSFB_PLUGIN_URL', 'https://site.test/wp-content/plugins/easy-form-builder/');

function __($v) { return $v; }
function esc_html__($v) { return $v; }
function esc_html($v) { return htmlspecialchars((string) $v, ENT_QUOTES); }
function esc_attr($v) { return htmlspecialchars((string) $v, ENT_QUOTES); }
function esc_url($v) { return filter_var((string) $v, FILTER_SANITIZE_URL); }
function is_rtl() { return false; }
function get_locale() { return 'en_US'; }
function home_url() { return 'https://site.test'; }
function get_bloginfo() { return 'Site'; }
function get_option($k, $d = false) { return $d; }

require dirname(__DIR__) . '/includes/class-email-handler.php';

$pass = 0;
$fail = 0;
function test($label, $ok, $detail = '') {
    global $pass, $fail;
    if ($ok) { $pass++; echo "[PASS] $label\n"; return; }
    $fail++;
    echo "[FAIL] $label\n";
    if ($detail !== '') echo "       $detail\n";
}

$handler  = new EmsfbEmailHandler();
$settings = (object) ['emailTemp' => ''];
$button   = 'View Messages';
$report_title = 'Report problem [Easy Form Builder]';

/* Content type exactly as send_email_state_new() derives it: $state[2] of a
 * plain string state is a single character, which lands in the default case. */
$render = function ($state, $content, $link = 'null', $st = null) use ($handler, $settings) {
    $type = isset($state[2]) ? $state[2] : 'traking_link';
    return (string) $handler->email_template_efb(0, $state, $content, $link, $type, $st ?: $settings);
};

$report_body = '<div style="font-family:Arial,sans-serif;"><p>Easy Form Builder could not reinstall its add-on files.</p>'
    . '<table><tr><td>Error: &quot;Connection timed out&quot;</td></tr></table></div>';

/* ── the add-on reinstall report ─────────────────────────────────────── */

$dl = $render('addonsDlProblem', $report_body);
test('addonsDlProblem: headed "Report problem [Easy Form Builder]"', strpos($dl, '>' . $report_title . '</h1>') !== false);
test('addonsDlProblem: no "New Message" heading', strpos($dl, 'New Message') === false);
test('addonsDlProblem: no "View Messages" button', strpos($dl, $button) === false);
test('addonsDlProblem: no link to http://null', stripos($dl, 'null') === false);
test('addonsDlProblem: body delivered untouched', strpos($dl, $report_body) !== false);
test('addonsDlProblem: escaped quotes in server errors survive', strpos($dl, '&quot;Connection timed out&quot;') !== false);
test('addonsDlProblem: <title> tag names the report', strpos($dl, '<title>' . $report_title . '</title>') !== false);

/* ── the legacy "admin pages did not load" report ────────────────────── */

$legacy_body = '<!--efb-->State:AdminPagesNotLoaded<br>PHP Version: 8.2<br>';
$rp = $render('reportProblem', $legacy_body);
test('reportProblem: same report heading', strpos($rp, '>' . $report_title . '</h1>') !== false);
test('reportProblem: no button', strpos($rp, $button) === false);
test('reportProblem: not dressed up as "We received your message"', strpos($rp, 'We received your message') === false);
test('reportProblem: body delivered untouched', strpos($rp, $legacy_body) !== false);

/* ── custom drag-and-drop template: title and message go through shortcodes */

$efbdata = urlencode(json_encode([
    'globalSettings' => ['btnBgColor' => '#202a8d'],
    'blocks' => [
        ['type' => 'header', 'data' => [], 'children' => [['type' => 'title', 'data' => ['text' => 'shortcode_title']]]],
        ['type' => 'message', 'data' => []],
    ],
]));
$custom = (object) ['emailTemp' => '<table class="efb-email-container"></table><!-- EFBDATA:' . $efbdata . ' -->'];

$dl_custom = $render('addonsDlProblem', $report_body, 'null', $custom);
test('custom template: report heading replaces shortcode_title', strpos($dl_custom, $report_title) !== false && strpos($dl_custom, 'New Message') === false);
test('custom template: no button inside the message block', strpos($dl_custom, $button) === false);

$nm_custom = $render('newMessage', $report_body, 'https://site.test/page/?track=ABC', $custom);
test('custom template: form notification keeps its heading and button', strpos($nm_custom, 'New Message') !== false && strpos($nm_custom, $button) !== false);

/* ── every other state keeps its previous rendering ──────────────────── */

$nm = $render('newMessage', 'ABC123', 'https://site.test/page/');
test('newMessage: still headed "New Message"', strpos($nm, '>New Message</h1>') !== false);
test('newMessage: still has the "View Messages" button', strpos($nm, $button) !== false);
test('newMessage: never gets the report heading', strpos($nm, $report_title) === false);

$nm_link = (string) $handler->email_template_efb(0, 'newMessage', ['ABC123', '<p>Name: Ali</p>'], 'https://site.test/page/', 'message_link', $settings);
test('newMessage message_link: button kept', strpos($nm_link, $button) !== false);

$nm_just = (string) $handler->email_template_efb(0, 'newMessage', ['ABC123', '<p>Name: Ali</p>'], 'https://site.test/page/', 'just_message', $settings);
test('newMessage just_message: still no button', strpos($nm_just, $button) === false);

$visitor = $render('notiToUserFormFilled_TrackingCode', 'ABC123', 'https://site.test/page/');
test('visitor copy: button kept', strpos($visitor, $button) !== false);

$ls = $render('licenseSuspended', '<p>Your licence was paused.</p>');
test('licenseSuspended: own heading kept', strpos($ls, 'Pro features are paused') !== false && strpos($ls, $report_title) === false);
test('licenseSuspended: no button', strpos($ls, $button) === false);

$tm = $render('testMailServer', 'testMailServer');
test('testMailServer: own heading kept', strpos($tm, 'Email Server Status') !== false && strpos($tm, $report_title) === false);
test('testMailServer: no button', strpos($tm, $button) === false);

echo "\n========================================\n";
echo "RESULTS: $pass passed, $fail failed\n";
exit($fail === 0 ? 0 : 1);
