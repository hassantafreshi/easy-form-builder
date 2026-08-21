<?php
/**
 * The same conditions, on a site whose Conditional Logic add-on is NOT active.
 *
 * Field rules genuinely stop running without AdnSMF — the validator file is only
 * required when the add-on is on. The notification, confirmation and webhook
 * rules do NOT stop: their call sites in _Public carry no add-on check, so a
 * form that still holds those rules keeps acting on them after the add-on is
 * deactivated or a licence lapses. That inconsistency is a product decision to
 * make, but while it stands the fallback comparison has to be as correct as the
 * add-on's, or the same form answers differently depending on a licence.
 *
 * This file deliberately does NOT load Emsfb_Logic_Validator, which is the only
 * way to exercise that fallback path.
 *
 * Run: C:\xampp\php\php.exe tests/test-conditional-logic-scopes-no-addon.php
 */

define('ABSPATH', __DIR__ . '/');

$GLOBALS['efb_webhook_posts'] = array();
$GLOBALS['efb_webhook_gets']  = array();

function add_action() {}
function add_filter() { return true; }
function remove_filter() { return true; }
function has_filter() { return false; }
function apply_filters($hook, $value) { return $value; }
function add_shortcode() {}
function register_rest_route() {}
function current_user_can() { return true; }
function get_current_user_id() { return 1; }
function get_efbFunction() { return null; }
function get_setting_Emsfb() { return array(); }
function wp_create_nonce($v = '') { return 'nonce_' . $v; }
function do_action() {}
function is_admin() { return false; }
function sanitize_text_field($v) { return is_array($v) ? '' : trim(strip_tags((string) $v)); }
function sanitize_email($v) { return filter_var((string) $v, FILTER_SANITIZE_EMAIL); }
function is_email($v) { return filter_var((string) $v, FILTER_VALIDATE_EMAIL) !== false; }
function esc_url($v) { return filter_var((string) $v, FILTER_SANITIZE_URL); }
function esc_url_raw($v) { return filter_var((string) $v, FILTER_SANITIZE_URL); }
function wp_kses_post($v) { return strip_tags((string) $v, '<b><strong><em><i><br><p><a>'); }
function wp_unslash($v) { return $v; }
function get_site_url() { return 'https://site.test'; }
function wp_json_encode($v) { return json_encode($v); }
function add_query_arg($args, $url) {
    $sep = strpos($url, '?') === false ? '?' : '&';
    return $url . $sep . http_build_query($args);
}
function wp_remote_post($url, $args = array()) { $GLOBALS['efb_webhook_posts'][] = array('url' => $url, 'args' => $args); return array('response' => array('code' => 200), 'body' => 'ok'); }
function wp_remote_get($url, $args = array())  { $GLOBALS['efb_webhook_gets'][]  = array('url' => $url, 'args' => $args); return array('response' => array('code' => 200), 'body' => 'ok'); }
function wp_get_referer() { return ''; }
function is_user_logged_in() { return false; }
function wp_get_current_user() { return (object) array('roles' => array()); }

require __DIR__ . '/../includes/class-Emsfb-public.php';

if (class_exists('Emsfb\\Emsfb_Logic_Validator')) {
    fwrite(STDERR, "This suite must run WITHOUT the logic validator loaded.\n");
    exit(1);
}

class NoAddon_Public extends Emsfb\_Public {
    public $sent = array();
    public function send_email_Emsfb_($to, $track, $pro, $state, $link, $content = 'null', $sub = 'null') {
        $this->sent[] = array('to' => is_array($to) ? $to[0] : $to);
    }
}

$pass = 0; $fail = 0; $failures = array();
function t($label, $actual, $expected) {
    global $pass, $fail, $failures;
    if ($actual === $expected) { $pass++; return; }
    $fail++;
    $failures[] = "$label\n      expected " . json_encode($expected) . "\n      actual   " . json_encode($actual);
}

$ref     = new ReflectionClass('NoAddon_Public');
$obj     = $ref->newInstanceWithoutConstructor();
$parent  = new ReflectionClass('Emsfb\\_Public');
$notify  = $parent->getMethod('process_conditional_notification_rules'); $notify->setAccessible(true);
$confirm = $parent->getMethod('get_conditional_confirmation_result');    $confirm->setAccessible(true);
$hooks   = $parent->getMethod('process_conditional_webhook_rules');      $hooks->setAccessible(true);
$idProp  = $parent->getProperty('id'); $idProp->setAccessible(true);
$idProp->setValue($obj, 901);

function fld($id, $type, $step) {
    return array('id_' => $id, 'type' => $type, 'step' => (string) $step, 'name' => $id, 'required' => '0', 'value' => '');
}
function opt($id, $parent, $value) {
    return array('id_' => $id, 'type' => 'option', 'parent' => $parent, 'value' => $value, 'step' => '1');
}
function form_with($rules) {
    return array_merge(array(array_merge(array('type' => 'form', 'steps' => '2', 'logic' => '1'), $rules)), array(
        array('id_' => '4', 'type' => 'step', 'step' => '1', 'name' => 'One'),
        array('id_' => '1', 'type' => 'step', 'step' => '2', 'name' => 'Two'),
        fld('company_type', 'select', 1), opt('ct_individual', 'company_type', 'Individual'), opt('ct_company', 'company_type', 'Company'),
        fld('services', 'checkbox', 1), opt('sv_dev', 'services', 'Development'), opt('sv_seo', 'services', 'SEO'),
        fld('plans', 'multiselect', 1), opt('pl_basic', 'plans', 'Basic'), opt('pl_pro', 'plans', 'Pro'),
        fld('urgency', 'radio', 1), opt('ur_low', 'urgency', 'Low'), opt('ur_urgent', 'urgency', 'Urgent'),
        fld('budget', 'number', 2), fld('notes', 'textarea', 2),
    ));
}
function rows() {
    return array(
        array('id_' => 'company_type', 'type' => 'select',      'value' => 'Company'),
        array('id_' => 'services',     'type' => 'checkbox',    'id_ob' => 'sv_dev', 'value' => 'sv_dev'),
        array('id_' => 'plans',        'type' => 'multiselect', 'value' => 'Basic@efb!Pro'),
        array('id_' => 'urgency',      'type' => 'radio',       'id_ob' => 'ur_urgent', 'value' => 'ur_urgent'),
        array('id_' => 'budget',       'type' => 'number',      'value' => '25000'),
        array('id_' => 'notes',        'type' => 'textarea',    'value' => 'Full rebuild please'),
    );
}
function cond($field, $compare, $value, $extra = array()) {
    return array_merge(array('type' => 'condition', 'source' => 'field',
        'field_id' => $field, 'compare' => $compare, 'value' => $value), $extra);
}
function grp($operator, $items, $extra = array()) {
    return array_merge(array('type' => 'group', 'operator' => $operator, 'items' => $items), $extra);
}

$status = array('content' => 'null', 'type' => 'msg', 'subject' => 'x');

function notifyHit($obj, $notify, $conditions, $status) {
    $obj->sent = array();
    $notify->invoke($obj, form_with(array('notification_rules' => array(array(
        'id' => 'n', 'enabled' => true, 'priority' => 10, 'recipient' => 'probe@example.com',
        'conditions' => $conditions)))), rows(), 'TRK', false, 'https://x/', $status);
    foreach ($obj->sent as $s) if ($s['to'] === 'probe@example.com') return true;
    return false;
}
function confirmHit($obj, $confirm, $conditions) {
    $r = $confirm->invoke($obj, form_with(array('confirmation_rules' => array(array(
        'id' => 'c', 'enabled' => true, 'priority' => 10, 'action' => 'message',
        'message' => 'probe', 'conditions' => $conditions)))), rows());
    return is_array($r) && isset($r['message']) && $r['message'] === 'probe';
}
function hookHit($obj, $hooks, $conditions) {
    $GLOBALS['efb_webhook_posts'] = array();
    $hooks->invoke($obj, form_with(array('webhook_rules' => array(array(
        'id' => 'w', 'enabled' => true, 'priority' => 10, 'action' => 'trigger',
        'url' => 'https://probe.example.com/x', 'method' => 'POST',
        'conditions' => $conditions)))), rows(), 'TRK', 'new', array());
    foreach ($GLOBALS['efb_webhook_posts'] as $p) if ($p['url'] === 'https://probe.example.com/x') return true;
    return false;
}

/* The option-id spelling is what the builder writes, so it is the case that
   decides whether these rules work on a de-licensed site. */
$CASES = array(
    array('F1',  'select is <chosen option id>',      grp('AND', array(cond('company_type', 'is', 'ct_company'))), true),
    array('F2',  'select is <other option id>',       grp('AND', array(cond('company_type', 'is', 'ct_individual'))), false),
    array('F3',  'select is_not <other option id>',   grp('AND', array(cond('company_type', 'is_not', 'ct_individual'))), true),
    array('F4',  'select is_not <chosen option id>',  grp('AND', array(cond('company_type', 'is_not', 'ct_company'))), false),
    array('F5',  'select is <display text>',          grp('AND', array(cond('company_type', 'is', 'Company'))), true),
    array('F6',  'checkbox is <ticked>',              grp('AND', array(cond('services', 'is', 'sv_dev'))), true),
    array('F7',  'checkbox is <unticked>',            grp('AND', array(cond('services', 'is', 'sv_seo'))), false),
    array('F8',  'checkbox is_not <unticked>',        grp('AND', array(cond('services', 'is_not', 'sv_seo'))), true),
    array('F9',  'multiselect is <selected id>',      grp('AND', array(cond('plans', 'is', 'pl_pro'))), true),
    array('F10', 'multiselect is <absent id>',        grp('AND', array(cond('plans', 'is', 'pl_basic'))), true),
    array('F11', 'radio is <chosen>',                 grp('AND', array(cond('urgency', 'is', 'ur_urgent'))), true),
    array('F12', 'radio is <other>',                  grp('AND', array(cond('urgency', 'is', 'ur_low'))), false),
    array('F13', 'number gte',                        grp('AND', array(cond('budget', 'gte', '25000'))), true),
    array('F14', 'text contains',                     grp('AND', array(cond('notes', 'contains', 'rebuild'))), true),
    array('F15', 'AND across two choice fields',
        grp('AND', array(cond('company_type', 'is', 'ct_company'), cond('services', 'is', 'sv_dev'))), true),
    array('F16', 'nested OR inside AND',
        grp('AND', array(cond('company_type', 'is', 'ct_company'),
            grp('OR', array(cond('plans', 'is', 'pl_pro'), cond('services', 'is', 'sv_seo')), array('connector' => 'AND')))), true),
    array('F17', 'negated group over a choice field',
        grp('AND', array(cond('company_type', 'is', 'ct_individual')), array('negate' => true)), true),
);

foreach ($CASES as $case) {
    list($id, $title, $conditions, $expected) = $case;
    t("$id notification — $title", notifyHit($obj, $notify, $conditions, $status), $expected);
    t("$id confirmation — $title", confirmHit($obj, $confirm, $conditions), $expected);
    t("$id webhook — $title",      hookHit($obj, $hooks, $conditions), $expected);
}

if ($failures) {
    echo "FAILURES\n\n";
    foreach ($failures as $f) echo '  ' . $f . "\n\n";
}
echo "========================================\n";
echo "RESULTS: $pass passed, $fail failed  (scope rules, add-on inactive)\n";
echo "========================================\n";
exit($fail > 0 ? 1 : 0);
