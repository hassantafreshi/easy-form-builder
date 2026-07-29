<?php
/**
 * Standalone PHP test for Phase 3 conditional notification + confirmation rules
 * (E2E Scenario M / doc section 17) against the REAL Emsfb\_Public methods.
 *
 * Covers:
 *  - notification recipients per condition path (17.5-17.8) without real emails
 *  - invalid recipient skipped, disabled rules skipped (17.9/17.10)
 *  - confirmation priority (redirect CR1 wins; CR2 before CR3)
 *  - styled done-screen overrides (done/icon/tracking_label/colors) returned
 *    sanitized for the frontend, and the no-match path returning null so the
 *    default thank-you (and default admin email path) stays untouched.
 *
 * Run: php tests/test-conditional-logic-notification-confirmation.php
 */

define('ABSPATH', __DIR__ . '/');

/* Capture error_log output in a scratch file so the email-debug trace
 * (EMSFB_EMAIL_DEBUG, doc 17.11) can be asserted. The constant is defined
 * only in group D below — every group before it must log NOTHING. */
$GLOBALS['efb_error_log_file'] = sys_get_temp_dir() . '/efb-email-debug-test-' . getmypid() . '.log';
@unlink($GLOBALS['efb_error_log_file']);
ini_set('error_log', $GLOBALS['efb_error_log_file']);
function efb_debug_log_lines() {
    $file = $GLOBALS['efb_error_log_file'];
    if (!file_exists($file)) return [];
    $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    return array_values(array_filter($lines, function ($l) { return strpos($l, '[EFB Email Debug]') !== false; }));
}

function add_action() {}
function add_filter() { return true; }
function add_shortcode() {}
function register_rest_route() {}
function get_current_user_id() { return 1; }
function get_efbFunction() { return null; }
function get_setting_Emsfb() { return []; }
function do_action() {}
function is_admin() { return false; }
function sanitize_email($v) { return filter_var((string)$v, FILTER_SANITIZE_EMAIL); }
function is_email($v) { return filter_var((string)$v, FILTER_VALIDATE_EMAIL) !== false; }
function sanitize_text_field($v) { return is_array($v) ? '' : trim(strip_tags((string)$v)); }
function esc_url($v) { return filter_var((string)$v, FILTER_SANITIZE_URL); }
function wp_kses_post($v) { return strip_tags((string)$v, '<b><strong><em><i><br><p><a>'); }

require dirname(__DIR__) . '/vendor/logic/class-Emsfb-logic-validator.php';
require dirname(__DIR__) . '/includes/class-Emsfb-public.php';

class Test_EFB_Public extends Emsfb\_Public {
    public $sent = [];
    public function send_email_Emsfb_($to, $track, $pro, $state, $link, $content = 'null', $sub = 'null') {
        $this->sent[] = ['to' => is_array($to) ? $to[0] : $to, 'sub' => $sub];
    }
}

$pass = 0;
$fail = 0;
function test($label, $actual, $expected) {
    global $pass, $fail;
    $ok = $actual === $expected;
    if ($ok) { $pass++; echo "[PASS] $label\n"; }
    else {
        $fail++;
        echo "[FAIL] $label\n";
        echo '  Expected: ' . var_export($expected, true) . "\n";
        echo '  Actual:   ' . var_export($actual, true) . "\n";
    }
}
function testTrue($label, $actual) { test($label, (bool)$actual, true); }
function testFalse($label, $actual) { test($label, (bool)$actual, false); }

$ref = new ReflectionClass('Test_EFB_Public');
$obj = $ref->newInstanceWithoutConstructor();
$parent = new ReflectionClass('Emsfb\\_Public');
$notify = $parent->getMethod('process_conditional_notification_rules');
$notify->setAccessible(true);
$confirm = $parent->getMethod('get_conditional_confirmation_result');
$confirm->setAccessible(true);

function sentRecipients($obj) {
    return array_map(function ($s) { return $s['to']; }, $obj->sent);
}

// ── Fixture: mirrors tests/seed-scenario-m-form.php (form "EFB Scenario M...") ──
function scenario_m_form($overrides = []) {
    $form = [[
        'type' => 'form',
        'notification_rules' => [
            ['id' => 'nr1_sales', 'enabled' => true, 'priority' => 10, 'recipient' => 'sales@example.com', 'subject' => 'Sales lead [confirmation_code]',
                'conditions' => ['type' => 'group', 'operator' => 'AND', 'items' => [
                    ['type' => 'condition', 'field_id' => 'customer_type', 'compare' => 'is', 'value' => 'Company'],
                ]]],
            ['id' => 'nr2_vip', 'enabled' => true, 'priority' => 20, 'recipient' => 'vip@example.com', 'subject' => 'VIP lead [confirmation_code]',
                'conditions' => ['type' => 'group', 'operator' => 'AND', 'items' => [
                    ['type' => 'condition', 'field_id' => 'has_budget', 'compare' => 'is', 'value' => 'yes'],
                    ['type' => 'condition', 'field_id' => 'budget', 'compare' => 'gt', 'value' => '1000', 'connector' => 'AND'],
                ]]],
            ['id' => 'nr3_support', 'enabled' => true, 'priority' => 30, 'recipient' => 'support@example.com', 'subject' => 'Support request [confirmation_code]',
                'conditions' => ['type' => 'group', 'operator' => 'AND', 'items' => [
                    ['type' => 'condition', 'field_id' => 'logic_command', 'compare' => 'is', 'value' => 'support'],
                ]]],
        ],
        'confirmation_rules' => [
            ['id' => 'cr1_vip_redirect', 'enabled' => true, 'priority' => 5, 'action' => 'redirect', 'url' => 'https://example.com/vip-thanks', 'message' => '',
                'conditions' => ['type' => 'group', 'operator' => 'AND', 'items' => [
                    ['type' => 'condition', 'field_id' => 'has_budget', 'compare' => 'is', 'value' => 'yes'],
                    ['type' => 'condition', 'field_id' => 'budget', 'compare' => 'gt', 'value' => '1000', 'connector' => 'AND'],
                ]]],
            ['id' => 'cr2_support_message', 'enabled' => true, 'priority' => 20, 'action' => 'message', 'url' => '',
                'message' => 'درخواست پشتیبانی شما ثبت شد.',
                'done' => 'پشتیبانی', 'icon' => 'bi-envelope-check', 'tracking_label' => 'کد پیگیری پشتیبانی',
                'icon_color' => '#0D6EFD', 'title_color' => '#0d6efd', 'message_color' => '#334155',
                'conditions' => ['type' => 'group', 'operator' => 'AND', 'items' => [
                    ['type' => 'condition', 'field_id' => 'logic_command', 'compare' => 'is', 'value' => 'support'],
                ]]],
            ['id' => 'cr3_individual_message', 'enabled' => true, 'priority' => 30, 'action' => 'message', 'url' => '',
                'message' => 'فرم شخص حقیقی با موفقیت ثبت شد.',
                'done' => 'ثبت شد', 'icon' => 'bi-patch-check', 'tracking_label' => '',
                'icon_color' => '#198754', 'title_color' => '#198754', 'message_color' => '',
                'conditions' => ['type' => 'group', 'operator' => 'AND', 'items' => [
                    ['type' => 'condition', 'field_id' => 'customer_type', 'compare' => 'is', 'value' => 'Individual'],
                ]]],
        ],
    ],
        ['id_' => 'customer_type', 'type' => 'select'],
        ['id_' => 'customer_type_individual', 'type' => 'option', 'parent' => 'customer_type', 'value' => 'Individual'],
        ['id_' => 'customer_type_company', 'type' => 'option', 'parent' => 'customer_type', 'value' => 'Company'],
        ['id_' => 'has_budget', 'type' => 'yesNo'],
        ['id_' => 'budget', 'type' => 'number'],
        ['id_' => 'logic_command', 'type' => 'text'],
    ];
    foreach ($overrides as $key => $value) {
        $form[0][$key] = $value;
    }
    return $form;
}

function submission($customer_type, $has_budget_yes, $budget, $logic_command) {
    return [
        ['id_' => 'customer_type', 'type' => 'select', 'value' => $customer_type],
        ['id_' => 'has_budget', 'type' => 'yesNo', 'id_ob' => $has_budget_yes ? 'has_budget_1' : 'has_budget_2'],
        ['id_' => 'budget', 'type' => 'number', 'value' => $budget],
        ['id_' => 'logic_command', 'type' => 'text', 'value' => $logic_command],
    ];
}

$status_email = ['content' => 'null', 'type' => 'traking_link', 'subject' => 'Default form notification'];

// ── 17.5: Company + budget 1500 (VIP path) ──────────────────────────────────
$form = scenario_m_form();
$vip = submission('Company', true, '1500', '');
$obj->sent = [];
$notify->invoke($obj, $form, $vip, 'TRK-VIP-1', false, 'https://example.test/x', $status_email);
$recipients = sentRecipients($obj);
testTrue('M1.1 VIP path: NR1 sales@example.com sent', in_array('sales@example.com', $recipients, true));
testTrue('M1.2 VIP path: NR2 vip@example.com sent', in_array('vip@example.com', $recipients, true));
testFalse('M1.3 VIP path: NR3 support@example.com NOT sent', in_array('support@example.com', $recipients, true));
test('M1.4 VIP path: exactly the 2 matching conditional emails (default admin email path untouched)', count($obj->sent), 2);
testTrue('M1.5 VIP path: subject keeps the [confirmation_code] pattern', strpos($obj->sent[0]['sub'], '[confirmation_code]') !== false);

$result = $confirm->invoke($obj, $form, $vip);
test('M1.6 VIP path: CR1 redirect wins (priority 5)', $result['action'], 'redirect');
test('M1.7 VIP path: redirect URL', $result['url'], 'https://example.com/vip-thanks');

// ── 17.6: Company + budget 500 (non-VIP) ────────────────────────────────────
$nonvip = submission('Company', true, '500', '');
$obj->sent = [];
$notify->invoke($obj, $form, $nonvip, 'TRK-NV-1', false, 'https://example.test/x', $status_email);
$recipients = sentRecipients($obj);
testTrue('M2.1 non-VIP: NR1 sales@example.com sent', in_array('sales@example.com', $recipients, true));
testFalse('M2.2 non-VIP: NR2 vip@example.com NOT sent', in_array('vip@example.com', $recipients, true));
testFalse('M2.3 non-VIP: NR3 support@example.com NOT sent', in_array('support@example.com', $recipients, true));
test('M2.4 non-VIP: no confirmation rule matches -> default thank-you (null)', $confirm->invoke($obj, $form, $nonvip), null);

// ── 17.7: Individual + support command ──────────────────────────────────────
$support = submission('Individual', false, '', 'support');
$obj->sent = [];
$notify->invoke($obj, $form, $support, 'TRK-SUP-1', false, 'https://example.test/x', $status_email);
$recipients = sentRecipients($obj);
testTrue('M3.1 support: NR3 support@example.com sent', in_array('support@example.com', $recipients, true));
testFalse('M3.2 support: NR1 sales@example.com NOT sent', in_array('sales@example.com', $recipients, true));
testFalse('M3.3 support: NR2 vip@example.com NOT sent', in_array('vip@example.com', $recipients, true));

$result = $confirm->invoke($obj, $form, $support);
test('M3.4 support: CR2 message wins over CR3 (priority 20 < 30)', $result['message'], 'درخواست پشتیبانی شما ثبت شد.');
test('M3.5 support: styled done title returned', $result['done'], 'پشتیبانی');
test('M3.6 support: styled icon returned', $result['icon'], 'bi-envelope-check');
test('M3.7 support: styled tracking label returned', $result['tracking_label'], 'کد پیگیری پشتیبانی');
test('M3.8 support: icon color normalized to lowercase hex', $result['icon_color'], '#0d6efd');
test('M3.9 support: message color returned', $result['message_color'], '#334155');
test('M3.10 support: no redirect', $result['url'], '');

// ── 17.8: Individual, no command ─────────────────────────────────────────────
$plain = submission('Individual', false, '', '');
$obj->sent = [];
$notify->invoke($obj, $form, $plain, 'TRK-IND-1', false, 'https://example.test/x', $status_email);
test('M4.1 individual: NO conditional email at all (only default admin email would go out)', count($obj->sent), 0);

$result = $confirm->invoke($obj, $form, $plain);
test('M4.2 individual: CR3 message', $result['message'], 'فرم شخص حقیقی با موفقیت ثبت شد.');
test('M4.3 individual: CR3 done title', $result['done'], 'ثبت شد');
test('M4.4 individual: CR3 icon', $result['icon'], 'bi-patch-check');
test('M4.5 individual: empty color override stays empty (form default wins)', $result['message_color'], '');

// ── 17.9: disabled rules are skipped ─────────────────────────────────────────
$disabledForm = scenario_m_form();
foreach ($disabledForm[0]['notification_rules'] as &$r) { $r['enabled'] = false; }
unset($r);
$obj->sent = [];
$notify->invoke($obj, $disabledForm, $vip, 'TRK-DIS-1', false, 'https://example.test/x', $status_email);
test('M5.1 all notification rules disabled: nothing sent', count($obj->sent), 0);

$disabledCr = scenario_m_form();
$disabledCr[0]['confirmation_rules'][0]['enabled'] = false; // CR1 off
$result = $confirm->invoke($obj, $disabledCr, $vip);
test('M5.2 disabled CR1: evaluation falls through (VIP submission matches no other rule)', $result, null);

// ── 17.10: sanitize / XSS ────────────────────────────────────────────────────
$xssForm = scenario_m_form([
    'notification_rules' => [
        ['id' => 'nr_bad_email', 'enabled' => true, 'priority' => 10, 'recipient' => 'not-an-email', 'subject' => 'x',
            'conditions' => ['type' => 'group', 'operator' => 'AND', 'items' => [
                ['type' => 'condition', 'field_id' => 'customer_type', 'compare' => 'is', 'value' => 'Company'],
            ]]],
        ['id' => 'nr_xss_subject', 'enabled' => true, 'priority' => 20, 'recipient' => 'safe@example.com', 'subject' => '<script>alert(1)</script> Sales',
            'conditions' => ['type' => 'group', 'operator' => 'AND', 'items' => [
                ['type' => 'condition', 'field_id' => 'customer_type', 'compare' => 'is', 'value' => 'Company'],
            ]]],
    ],
    'confirmation_rules' => [
        ['id' => 'cr_xss', 'enabled' => true, 'priority' => 10, 'action' => 'message', 'url' => '',
            'message' => '<strong>OK</strong><script>alert(1)</script>',
            'done' => '<script>x</script>Done!',
            'icon' => 'javascript:alert(1)',
            'tracking_label' => '<b>Code</b>',
            'icon_color' => 'red', 'title_color' => '#ZZZZZZ', 'message_color' => '#AABBCC',
            'conditions' => ['type' => 'group', 'operator' => 'AND', 'items' => [
                ['type' => 'condition', 'field_id' => 'customer_type', 'compare' => 'is', 'value' => 'Company'],
            ]]],
    ],
]);
$obj->sent = [];
$notify->invoke($obj, $xssForm, $vip, 'TRK-XSS-1', false, 'https://example.test/x', $status_email);
test('M6.1 invalid recipient skipped, valid one still sent', count($obj->sent), 1);
test('M6.2 surviving recipient', $obj->sent[0]['to'], 'safe@example.com');
testFalse('M6.3 subject script stripped', strpos($obj->sent[0]['sub'], '<script') !== false);

$result = $confirm->invoke($obj, $xssForm, $vip);
testTrue('M6.4 message keeps allowed <strong>', strpos($result['message'], '<strong>OK</strong>') !== false);
testFalse('M6.5 message script tag stripped', strpos($result['message'], '<script') !== false);
testFalse('M6.6 done title tags stripped', strpos($result['done'], '<') !== false);
test('M6.7 non bi-* icon dropped', $result['icon'], '');
testFalse('M6.8 tracking label tags stripped', strpos($result['tracking_label'], '<') !== false);
test('M6.9 named color rejected', $result['icon_color'], '');
test('M6.10 invalid hex rejected', $result['title_color'], '');
test('M6.11 valid hex normalized to lowercase', $result['message_color'], '#aabbcc');

// ── No rules at all: both processors are no-ops ──────────────────────────────
$bareForm = scenario_m_form(['notification_rules' => [], 'confirmation_rules' => []]);
$obj->sent = [];
$notify->invoke($obj, $bareForm, $vip, 'TRK-BARE-1', false, 'https://example.test/x', $status_email);
test('M7.1 no notification rules: nothing sent', count($obj->sent), 0);
test('M7.2 no confirmation rules: null (default thank-you untouched)', $confirm->invoke($obj, $bareForm, $vip), null);

// ── REAL builder-save sanitizer (functions.php sanitize_confirmation_rules) ──
function esc_url_raw($v) { return filter_var((string)$v, FILTER_SANITIZE_URL); }
if (!class_exists('efbFunction')) {
    require dirname(__DIR__) . '/includes/functions.php';
}
$fnRef = new ReflectionClass('efbFunction');
$fnObj = $fnRef->newInstanceWithoutConstructor();
$sanitizeCr = $fnRef->getMethod('sanitize_confirmation_rules');
$sanitizeCr->setAccessible(true);

$structure = [
    ['type' => 'form'],
    ['id_' => 'logic_command', 'type' => 'text'],
];
$dirtyRules = [[
    'id' => 'cr_style',
    'enabled' => true,
    'priority' => 20,
    'action' => 'message',
    'url' => '',
    'message' => '<strong>OK</strong><script>alert(1)</script>',
    'done' => '  <script>x</script>پشتیبانی  ',
    'icon' => 'bi-envelope-check',
    'tracking_label' => '<b>کد</b>',
    'icon_color' => '#0D6EFD',
    'title_color' => 'red',
    'message_color' => '#33415G',
    'conditions' => ['type' => 'group', 'operator' => 'AND', 'items' => [
        ['type' => 'condition', 'field_id' => 'logic_command', 'compare' => 'is', 'value' => 'support'],
    ]],
], [
    'id' => 'cr_bad_icon',
    'action' => 'message',
    'message' => 'x',
    'icon' => 'javascript:alert(1)',
    'conditions' => ['type' => 'group', 'operator' => 'AND', 'items' => [
        ['type' => 'condition', 'field_id' => 'logic_command', 'compare' => 'is', 'value' => 'y'],
    ]],
]];
$cleanRules = $sanitizeCr->invoke($fnObj, $dirtyRules, $structure);
test('S1.1 both rules survive sanitize', count($cleanRules), 2);
testTrue('S1.2 message keeps <strong>', strpos($cleanRules[0]['message'], '<strong>OK</strong>') !== false);
testFalse('S1.3 message script stripped', strpos($cleanRules[0]['message'], '<script') !== false);
test('S1.4 done title stripped+trimmed', $cleanRules[0]['done'], 'xپشتیبانی');
test('S1.5 valid icon kept', $cleanRules[0]['icon'], 'bi-envelope-check');
testFalse('S1.6 tracking label tags stripped', strpos($cleanRules[0]['tracking_label'], '<') !== false);
test('S1.7 hex color normalized lowercase', $cleanRules[0]['icon_color'], '#0d6efd');
test('S1.8 named color dropped', $cleanRules[0]['title_color'], '');
test('S1.9 malformed hex dropped', $cleanRules[0]['message_color'], '');
test('S1.10 non bi-* icon dropped', $cleanRules[1]['icon'], '');
test('S1.11 missing style keys default to empty strings', $cleanRules[1]['done'], '');

// ── GROUP D: email debug trace (EMSFB_EMAIL_DEBUG, doc 17.11) ────────────────
// Everything above ran WITHOUT the constant: the trace must be silent.
test('D1.1 no [EFB Email Debug] lines while the switch is off', count(efb_debug_log_lines()), 0);

define('EMSFB_EMAIL_DEBUG', true);

// L1 department path (Company + budget 1500): sales + vip matched, support not.
$obj->sent = [];
$notify->invoke($obj, scenario_m_form(), $vip, 'TRK-LOG-1', false, 'https://example.test/x', $status_email);
$logLines = efb_debug_log_lines();
$logText = implode("\n", $logLines);
testTrue('D1.2 switch on: trace lines were written', count($logLines) > 0);
testTrue('D1.3 evaluation start logged with track and active rule count',
    strpos($logText, '[notification-rules]') !== false
    && strpos($logText, '"track":"TRK-LOG-1"') !== false
    && strpos($logText, '"active_rules":3') !== false);
testTrue('D1.4 sales department send logged',
    strpos($logText, '[rule-matched-send]') !== false
    && strpos($logText, '"rule":"nr1_sales"') !== false
    && strpos($logText, '"recipient":"sales@example.com"') !== false);
testTrue('D1.5 vip department send logged', strpos($logText, '"rule":"nr2_vip"') !== false);
testTrue('D1.6 support rule logged as not matched',
    strpos($logText, '[rule-not-matched]') !== false && strpos($logText, '"rule":"nr3_support"') !== false);
testTrue('D1.7 subject with [confirmation_code] pattern logged',
    strpos($logText, 'Sales lead [confirmation_code]') !== false);

// Invalid recipient must be logged with its skip reason.
$notify->invoke($obj, $xssForm, $vip, 'TRK-LOG-2', false, 'https://example.test/x', $status_email);
$logText = implode("\n", efb_debug_log_lines());
testTrue('D1.8 invalid recipient skip logged with reason',
    strpos($logText, '[rule-skipped]') !== false && strpos($logText, '"reason":"invalid_recipient"') !== false);

// Custom email content must appear as a readable preview in the trace.
$statusWithContent = ['content' => '<p>Budget: 1500</p><p>Customer: Company</p>', 'type' => 'msg', 'subject' => 'Default'];
$notify->invoke($obj, scenario_m_form(), $vip, 'TRK-LOG-3', false, 'https://example.test/x', $statusWithContent);
$logText = implode("\n", efb_debug_log_lines());
testTrue('D1.9 content preview shows submitted data (tags stripped)',
    strpos($logText, 'Budget: 1500') !== false && strpos($logText, '<p>') === false);

// Handler-level full-content log (class-email-handler.php log_email_debug):
// summary goes to error_log, full HTML goes to WP_CONTENT_DIR/efb-email-debug.log.
define('WP_CONTENT_DIR', sys_get_temp_dir() . '/efb-email-debug-content-' . getmypid());
@mkdir(WP_CONTENT_DIR);
@unlink(WP_CONTENT_DIR . '/efb-email-debug.log');
function wp_date($format) { return date($format); }
/*
 * The email handler guards optional PHP functions against php.ini's
 * disable_functions through this helper. It normally arrives with
 * includes/class-Emsfb-addon-compatibility.php, which this harness does not
 * load, so stand in for it with the same semantics.
 */
if (!function_exists('emsfb_is_php_function_available_efb')) {
    function emsfb_is_php_function_available_efb($function_name) {
        return function_exists($function_name) && is_callable($function_name);
    }
}
require dirname(__DIR__) . '/includes/class-email-handler.php';
testTrue('D2.1 email_debug_enabled() reflects the constant', EmsfbEmailHandler::email_debug_enabled());

$handlerRef = new ReflectionClass('EmsfbEmailHandler');
$handlerObj = $handlerRef->newInstanceWithoutConstructor();
$logDebug = $handlerRef->getMethod('log_email_debug');
$logDebug->setAccessible(true);
$logDebug->invoke($handlerObj, 'newMessage', 'sales@example.com', 'Sales lead [TRK-LOG-9]',
    '<html><body><h2>New message</h2><p>Budget: 1500</p></body></html>', 'https://example.test/x', 'msg');

$contentLog = WP_CONTENT_DIR . '/efb-email-debug.log';
testTrue('D2.2 full HTML written to efb-email-debug.log', file_exists($contentLog));
$contentLogText = file_exists($contentLog) ? file_get_contents($contentLog) : '';
testTrue('D2.3 content log contains the email HTML', strpos($contentLogText, '<p>Budget: 1500</p>') !== false);
testTrue('D2.4 content log identifies recipient', strpos($contentLogText, 'sales@example.com') !== false);
$logText = implode("\n", efb_debug_log_lines());
testTrue('D2.5 handler summary line in error log points to the content log',
    strpos($logText, 'See full HTML in') !== false && strpos($logText, 'Sales lead [TRK-LOG-9]') !== false);

// wp_mail result lines (log_email_success / log_email_failure)
function get_option($k, $d = false) { return $d; }
function update_option() { return true; }
EmsfbEmailHandler::log_email_success('sales@example.com', 'Sales lead [TRK-LOG-9]');
EmsfbEmailHandler::log_email_failure('vip@example.com', 'VIP lead', null);
$logText = implode("\n", efb_debug_log_lines());
testTrue('D2.6 success result logged', strpos($logText, '"success":true') !== false && strpos($logText, '"to":"sales@example.com"') !== false);
testTrue('D2.7 failure result logged', strpos($logText, '"success":false') !== false && strpos($logText, '"to":"vip@example.com"') !== false);

// ── GROUP R: regression — "Cannot redeclare Emsfb\g()" fatal ─────────────────
// The default admin email plus a conditional department email in the SAME
// request call the secure-code helper twice; the nested named function g()
// used to fatal on the second call and killed the conditional email.
$settingProp = $parent->getProperty('setting');
$settingProp->setAccessible(true);
$settingProp->setValue($obj, (object)['email_key' => 'k123']);
$secure = $parent->getMethod('genrate_sacure_code_admin_email');
$secure->setAccessible(true);
$sc1 = $secure->invoke($obj, 'TRK-A');
$sc2 = $secure->invoke($obj, 'TRK-A');
testTrue('R1.1 secure-code helper callable twice in one request (no redeclare fatal)', is_string($sc2) && strlen($sc2) === 32);
test('R1.2 deterministic hash for the same track', $sc1, $sc2);
test('R1.3 hash formula md5(track.key) preserved', $sc1, md5('TRK-A' . 'k123'));

@unlink($GLOBALS['efb_error_log_file']);
@unlink($contentLog);
@rmdir(WP_CONTENT_DIR);

// ── GROUP S: CC/BCC copies + {field_id} tokens in subject and redirect URL ───
$obj->sent = [];
$formTokens = scenario_m_form([
    'notification_rules' => [
        ['id' => 'nr_tok', 'enabled' => true, 'priority' => 10,
            'recipient' => 'sales@example.com',
            'cc' => ['boss@example.com'],
            'bcc' => ['audit@example.com', 'sales@example.com'], // duplicate of recipient must be skipped
            'subject' => 'New {customer_type} lead — budget {budget}',
            'conditions' => ['type' => 'group', 'operator' => 'AND', 'items' => [
                ['type' => 'condition', 'field_id' => 'customer_type', 'compare' => 'is', 'value' => 'Company'],
            ]]],
    ],
    'confirmation_rules' => [
        ['id' => 'cr_tok', 'enabled' => true, 'priority' => 5, 'action' => 'redirect',
            'url' => 'https://example.com/thanks?type={customer_type}&b={budget}&missing={ghost}',
            'message' => '',
            'conditions' => ['type' => 'group', 'operator' => 'AND', 'items' => [
                ['type' => 'condition', 'field_id' => 'customer_type', 'compare' => 'is', 'value' => 'Company'],
            ]]],
    ],
]);
$tokenSubmission = submission('Company', true, '1500', '');
$notify->invoke($obj, $formTokens, $tokenSubmission, 'TRK-TOK-1', false, 'https://example.test/x', $status_email);
test('S1.1 recipient + cc + bcc = 3 sends (recipient duplicate skipped)', count($obj->sent), 3);
test('S1.2 primary recipient first', $obj->sent[0]['to'], 'sales@example.com');
testTrue('S1.3 cc copy sent', in_array('boss@example.com', sentRecipients($obj), true));
testTrue('S1.4 bcc copy sent', in_array('audit@example.com', sentRecipients($obj), true));
test('S1.5 subject tokens replaced with submitted values', $obj->sent[0]['sub'], 'New Company lead — budget 1500');
test('S1.6 cc copy uses the same personalized subject', $obj->sent[1]['sub'], 'New Company lead — budget 1500');

$redirectResult = $confirm->invoke($obj, $formTokens, $tokenSubmission);
test('S2.1 redirect action returned', $redirectResult['action'], 'redirect');
testTrue('S2.2 url tokens replaced (urlencoded)', strpos($redirectResult['url'], 'type=Company') !== false && strpos($redirectResult['url'], 'b=1500') !== false);
testTrue('S2.3 unknown token removed, not leaked', strpos($redirectResult['url'], '{ghost}') === false);

echo "\n========================================\n";
echo "RESULTS: $pass passed, $fail failed\n";
echo "========================================\n";
exit($fail > 0 ? 1 : 0);
