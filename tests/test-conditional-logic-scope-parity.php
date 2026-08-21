<?php
/**
 * Scope parity: the SAME condition must reach the SAME verdict in all four rule
 * collections — field, notification, confirmation, webhook.
 *
 * Field rules are evaluated by the add-on validator; the other three are
 * processed in _Public. When the two disagree, a rule the admin built and tested
 * in one place quietly does nothing in another: the email is not sent, the
 * conditional thank-you never appears, the webhook is never called. That is
 * exactly what shipped — _Public never resolved an option id_ to the value a row
 * stores, so every select and multiselect condition in those three scopes failed
 * while the identical field rule worked.
 *
 * This sweeps every condition shape the builder can produce rather than a
 * hand-picked list, because the failure was invisible from the outside: both
 * sides returned a plain boolean and neither logged anything.
 *
 * Run: C:\xampp\php\php.exe tests/test-conditional-logic-scope-parity.php
 */
define('ABSPATH', __DIR__ . '/');
$GLOBALS['efb_webhook_posts'] = array(); $GLOBALS['efb_webhook_gets'] = array();
function add_action() {} function add_filter() { return true; } function remove_filter() { return true; }
function has_filter() { return false; } function apply_filters($h, $v) { return $v; }
function add_shortcode() {} function register_rest_route() {} function current_user_can() { return true; }
function get_current_user_id() { return 1; } function get_efbFunction() { return null; }
function get_setting_Emsfb() { return array(); } function wp_create_nonce($v = '') { return 'n'; }
function do_action() {} function is_admin() { return false; }
function sanitize_text_field($v) { return is_array($v) ? '' : trim(strip_tags((string) $v)); }
function sanitize_email($v) { return filter_var((string) $v, FILTER_SANITIZE_EMAIL); }
function is_email($v) { return filter_var((string) $v, FILTER_VALIDATE_EMAIL) !== false; }
function esc_url($v) { return filter_var((string) $v, FILTER_SANITIZE_URL); }
function esc_url_raw($v) { return filter_var((string) $v, FILTER_SANITIZE_URL); }
function wp_kses_post($v) { return strip_tags((string) $v, '<b><i>'); }
function wp_unslash($v) { return $v; } function get_site_url() { return 'https://site.test'; }
function wp_json_encode($v) { return json_encode($v); }
function add_query_arg($a, $u) { return $u . (strpos($u, '?') === false ? '?' : '&') . http_build_query($a); }
function wp_remote_post($u, $a = array()) { $GLOBALS['efb_webhook_posts'][] = array('url' => $u); return array('response' => array('code' => 200), 'body' => 'ok'); }
function wp_remote_get($u, $a = array()) { $GLOBALS['efb_webhook_gets'][] = array('url' => $u); return array('response' => array('code' => 200), 'body' => 'ok'); }
function wp_get_referer() { return 'https://site.test/f?utm=news&n=9'; }
function is_user_logged_in() { return true; }
function wp_get_current_user() { return (object) array('roles' => array('editor')); }

$EFB = 'c:/xampp/htdocs/wp/wp-content/plugins/easy-form-builder';
require $EFB . '/vendor/logic/logic/class-Emsfb-logic-validator.php';
require $EFB . '/includes/class-Emsfb-public.php';

class Diff_Public extends Emsfb\_Public {
    public $sent = array();
    public function send_email_Emsfb_($to, $t, $p, $s, $l, $c = 'null', $sub = 'null') { $this->sent[] = is_array($to) ? $to[0] : $to; }
}
$ref = new ReflectionClass('Diff_Public'); $obj = $ref->newInstanceWithoutConstructor();
$parent = new ReflectionClass('Emsfb\\_Public');
$notify  = $parent->getMethod('process_conditional_notification_rules'); $notify->setAccessible(true);
$confirm = $parent->getMethod('get_conditional_confirmation_result');    $confirm->setAccessible(true);
$hooks   = $parent->getMethod('process_conditional_webhook_rules');      $hooks->setAccessible(true);
$idp = $parent->getProperty('id'); $idp->setAccessible(true); $idp->setValue($obj, 902);

function f($id, $type, $step = 1) { return array('id_' => $id, 'type' => $type, 'step' => (string) $step, 'name' => $id, 'required' => '0', 'value' => ''); }
function o($id, $p, $v) { return array('id_' => $id, 'type' => 'option', 'parent' => $p, 'value' => $v, 'step' => '1'); }
function base($rules) {
    return array_merge(array(array_merge(array('type' => 'form', 'steps' => '2', 'logic' => '1'), $rules)), array(
        array('id_' => '9', 'type' => 'step', 'step' => '1', 'name' => 'A'),
        array('id_' => '1', 'type' => 'step', 'step' => '2', 'name' => 'B'),
        f('sel', 'select'), o('s_a', 'sel', 'Alpha'), o('s_b', 'sel', 'Beta'),
        f('rad', 'radio'), o('r_a', 'rad', 'RA'), o('r_b', 'rad', 'RB'),
        f('chk', 'checkbox'), o('c_a', 'chk', 'CA'), o('c_b', 'chk', 'CB'),
        f('mul', 'multiselect'), o('m_a', 'mul', 'MA'), o('m_b', 'mul', 'MB'),
        f('yn', 'yesNo'), f('txt', 'text'), f('num', 'number'), f('dt', 'date'),
        f('mail', 'email'), f('file', 'dadfile'), f('pay', 'stripe', 2), f('out', 'text', 2),
    ));
}
function dataRows() {
    return array(
        array('id_' => 'sel', 'type' => 'select', 'value' => 'Alpha'),
        array('id_' => 'rad', 'type' => 'radio', 'id_ob' => 'r_a', 'value' => 'r_a'),
        array('id_' => 'chk', 'type' => 'checkbox', 'id_ob' => 'c_a', 'value' => 'c_a'),
        array('id_' => 'mul', 'type' => 'multiselect', 'value' => 'MA'),
        array('id_' => 'yn', 'type' => 'yesNo', 'id_ob' => 'yn_1', 'value' => 'yes'),
        array('id_' => 'txt', 'type' => 'text', 'value' => 'Hello World'),
        array('id_' => 'num', 'type' => 'number', 'value' => '42'),
        array('id_' => 'dt', 'type' => 'date', 'value' => '2026-06-15'),
        array('id_' => 'mail', 'type' => 'email', 'value' => 'a@b.com'),
        array('id_' => 'pay', 'type' => 'stripe', 'amount' => '250', 'payment_status' => 'paid'),
    );
}
function C($fld, $cmp, $val, $extra = array()) {
    return array('type' => 'group', 'operator' => 'AND', 'items' => array(
        array_merge(array('type' => 'condition', 'source' => 'field', 'field_id' => $fld, 'compare' => $cmp, 'value' => $val), $extra)));
}

$rows = dataRows();
$ENV = array('query' => array('utm' => 'news', 'n' => '9'), 'user' => array('logged_in' => true, 'roles' => array('editor')), 'current_step' => 2);

function fieldHit($cond, $rows, $env) {
    $v = new \Emsfb\Emsfb_Logic_Validator(); $v->set_environment($env);
    $form = base(array('logic_rules' => array(array('id' => 'r', 'enabled' => true, 'priority' => 10,
        'stop_processing' => false, 'conditions' => $cond,
        'actions' => array(array('type' => 'hide_field', 'target' => 'out'))))));
    return in_array('r', $v->evaluate($form, $rows)['matched_rules'], true);
}
function notiHit($obj, $notify, $cond, $rows) {
    $obj->sent = array();
    $notify->invoke($obj, base(array('notification_rules' => array(array('id' => 'n', 'enabled' => true,
        'priority' => 10, 'recipient' => 'p@e.com', 'conditions' => $cond)))), $rows, 'T', false, 'https://x/',
        array('content' => 'null', 'type' => 'msg', 'subject' => 's'));
    return in_array('p@e.com', $obj->sent, true);
}
function confHit($obj, $confirm, $cond, $rows) {
    $r = $confirm->invoke($obj, base(array('confirmation_rules' => array(array('id' => 'c', 'enabled' => true,
        'priority' => 10, 'action' => 'message', 'message' => 'probe', 'conditions' => $cond)))), $rows);
    return is_array($r) && ($r['message'] ?? '') === 'probe';
}
function hookHit($obj, $hooks, $cond, $rows) {
    $GLOBALS['efb_webhook_posts'] = array();
    $hooks->invoke($obj, base(array('webhook_rules' => array(array('id' => 'w', 'enabled' => true, 'priority' => 10,
        'action' => 'trigger', 'url' => 'https://p.e.com/x', 'method' => 'POST', 'conditions' => $cond)))), $rows, 'T', 'new', array());
    foreach ($GLOBALS['efb_webhook_posts'] as $p) if ($p['url'] === 'https://p.e.com/x') return true;
    return false;
}

/* Every combination worth asking about. */
$cases = array();
foreach (array('s_a' => true, 's_b' => false, 'Alpha' => true, 'Beta' => false) as $v => $_) {
    foreach (array('is', 'is_not') as $op) $cases["sel $op $v"] = C('sel', $op, (string) $v);
}
foreach (array('r_a', 'r_b') as $v) foreach (array('is', 'is_not') as $op) $cases["rad $op $v"] = C('rad', $op, $v);
foreach (array('c_a', 'c_b', 'CA', 'CB') as $v) foreach (array('is', 'is_not') as $op) $cases["chk $op $v"] = C('chk', $op, $v);
foreach (array('m_a', 'm_b', 'MA', 'MB') as $v) foreach (array('is', 'is_not') as $op) $cases["mul $op $v"] = C('mul', $op, $v);
foreach (array('yes', 'no') as $v) $cases["yn is $v"] = C('yn', 'is', $v);
foreach (array('is', 'is_not', 'contains', 'not_contains', 'starts_with', 'ends_with', 'is_empty', 'is_not_empty') as $op) {
    foreach (array('Hello World', 'hello', 'World', 'zzz', '') as $v) $cases["txt $op '$v'"] = C('txt', $op, $v);
}
foreach (array('is', 'is_not', 'gt', 'gte', 'lt', 'lte', 'between', 'not_between', 'is_empty', 'is_not_empty') as $op) {
    foreach (array('42', '10', '99', '10,50', '50,60', 'abc', '') as $v) $cases["num $op '$v'"] = C('num', $op, $v);
}
foreach (array('is', 'date_before', 'date_after', 'date_between', 'is_empty', 'is_not_empty') as $op) {
    foreach (array('2026-06-15', '2026-01-01', '2026-12-31', '2026-01-01,2026-12-31', 'bad', '') as $v) $cases["dt $op '$v'"] = C('dt', $op, $v);
}
foreach (array('is_empty', 'is_not_empty') as $op) $cases["file $op"] = C('file', $op, '');
foreach (array('is_paid', 'is_not_paid') as $op) $cases["pay $op"] = C('pay', $op, '');
foreach (array('amount_eq', 'amount_gt', 'amount_lt') as $op) foreach (array('250', '100', '900') as $v) $cases["pay $op $v"] = C('pay', $op, $v);
foreach (array('is', 'is_not', 'contains', 'is_empty', 'is_not_empty') as $op) {
    foreach (array('news', 'other', '') as $v) $cases["query utm $op '$v'"] = C('utm', $op, $v, array('source' => 'query_param', 'param' => 'utm'));
}
foreach (array('yes', 'no') as $v) $cases["user logged_in is $v"] = C('logged_in', 'is', $v, array('source' => 'user'));
foreach (array('editor', 'administrator') as $v) foreach (array('is', 'is_not') as $op) $cases["user role $op $v"] = C('role', $op, $v, array('source' => 'user'));
foreach (array('is', 'is_not', 'gt', 'lt') as $op) foreach (array('1', '2', '3') as $v) $cases["step $op $v"] = C('current_step', $op, $v, array('source' => 'current_step'));

$diverged = 0; $checked = 0; $report = array();
foreach ($cases as $label => $cond) {
    $checked++;
    $f = fieldHit($cond, $rows, $ENV);
    $n = notiHit($obj, $notify, $cond, $rows);
    $c = confHit($obj, $confirm, $cond, $rows);
    $w = hookHit($obj, $hooks, $cond, $rows);
    if ($f === $n && $n === $c && $c === $w) continue;
    $diverged++;
    $report[] = sprintf("%-34s field=%s notify=%s confirm=%s webhook=%s", $label,
        $f ? 'Y' : 'n', $n ? 'Y' : 'n', $c ? 'Y' : 'n', $w ? 'Y' : 'n');
}
if ($report) {
    echo "SCOPES DISAGREE\n\n";
    foreach ($report as $line) echo '  ' . $line . "\n";
    echo "\n";
}
echo "========================================\n";
echo 'RESULTS: ' . ($checked - $diverged) . " passed, $diverged failed  ($checked condition shapes x 4 scopes)\n";
echo "========================================\n";
exit($diverged > 0 ? 1 : 0);
