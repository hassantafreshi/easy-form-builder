<?php
// Isolated diagnostic: no WordPress bootstrap, database, network or real mail.
define('ABSPATH', dirname(__DIR__) . '/');
define('EMSFB_EMAIL_DEBUG', false);
$captured = [];
$memory_options = [];
$allow_email = true;
function add_filter(...$args) {}
function remove_filter(...$args) {}
function add_action(...$args) {}
function remove_action(...$args) {}
function apply_filters($name, $value, ...$args) {
    global $allow_email;
    return $name === 'emsfb_get_server_host' ? 'www.ong-grandcoeur.org' : ($name === 'efb_shield_allow_side_effect' ? $allow_email : $value);
}
function get_bloginfo($name) { return 'GRAND COEUR e.V.'; }
function is_email($value) { return is_string($value) && filter_var($value, FILTER_VALIDATE_EMAIL); }
function get_option($name, $default = false) { global $memory_options; return $memory_options[$name] ?? $default; }
function update_option($name, $value, ...$args) { global $memory_options; $memory_options[$name] = $value; return true; }
function wp_date($format) { return date($format); }
function wp_mail($to, $subject, $body, $headers) {
    global $captured;
    $captured[] = compact('to', 'subject', 'body', 'headers');
    return true; // Simulate local acceptance, with no delivery whatsoever.
}
require dirname(__DIR__) . '/includes/class-email-handler.php';
class SenderDiagnosticHandler extends EmsfbEmailHandler {
    public function email_template_efb($pro, $state, $m, $link, $email_content_type, $st = 'null') { return '<p>Diagnostic</p>'; }
}
function check_diagnosis($label, $condition) {
    if (!$condition) { fwrite(STDERR, "FAIL: $label\n"); exit(1); }
    echo "PASS: $label\n";
}
$handler = new SenderDiagnosticHandler();
$settings = (object) ['femail' => 'postmaster@ong-grandcoeur.org'];
$result = $handler->send_email_state_new('recipient@example.invalid', 'Diagnostic', 'body', false, 'newMessage', '', $settings);
check_diagnosis('Form sender uses configured femail even without recipient index 2', $captured[0]['headers'][1] === 'From:GRAND COEUR e.V. <postmaster@ong-grandcoeur.org>');
check_diagnosis('Successful handoff reports success without delivery', $result === true && $memory_options['efb_email_log'][0]['success'] === true);
$handler->send_email_state_new(['recipient@example.invalid', '', 'postmaster@ong-grandcoeur.org'], 'Diagnostic', 'body', false, 'newMessage', '', $settings);
check_diagnosis('Explicit recipient index 2 selects configured sender', $captured[1]['headers'][1] === 'From:GRAND COEUR e.V. <postmaster@ong-grandcoeur.org>');
check_diagnosis('Sender index is removed from recipients', $captured[1]['to'] === 'recipient@example.invalid' && count($captured) === 2);
$handler->send_email_state_new(['admin@example.invalid', 'user@example.invalid'], ['Admin', 'User'], ['body', 'body'], false, ['newMessage', 'newMessage'], ['', ''], $settings);
check_diagnosis('Paired notification without sender index uses configured sender for both', count($captured) === 4 && $captured[2]['headers'][1] === $captured[0]['headers'][1] && $captured[3]['headers'][1] === $captured[0]['headers'][1]);
$allow_email = false;
$handler->send_email_state_new('recipient@example.invalid', 'Diagnostic', 'body', false, 'newMessage', '', $settings);
check_diagnosis('Guard veto prevents handoff; supplied trace shows a different path', count($captured) === 4);
$allow_email = true;
$handler->send_email_state_new(['recipient@example.invalid', '', 'override@example.org'], 'Diagnostic', 'body', false, 'newMessage', '', $settings);
check_diagnosis('Explicit sender keeps precedence over global settings', $captured[4]['headers'][1] === 'From:GRAND COEUR e.V. <override@example.org>');
$handler->send_email_state_new('recipient@example.invalid', 'Diagnostic', 'body', false, 'newMessage', '', ['femail' => 'array@example.org']);
check_diagnosis('Array settings select the configured sender', $captured[5]['headers'][1] === 'From:GRAND COEUR e.V. <array@example.org>');
$handler->send_email_state_new('recipient@example.invalid', 'Diagnostic', 'body', false, 'newMessage', '', ['femail' => 'invalid']);
check_diagnosis('Invalid configured sender retains existing host fallback', $captured[6]['headers'][1] === 'From:GRAND COEUR e.V. <no-reply@www.ong-grandcoeur.org>');
echo "No real email sent; no database or settings changed.\n";
