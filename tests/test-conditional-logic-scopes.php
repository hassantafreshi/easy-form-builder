<?php
/**
 * Conditional logic across ALL FOUR rule collections, on one realistic form.
 *
 * Field rules run through the add-on validator (Emsfb_Logic_Validator). The
 * other three — notification, confirmation, webhook — are processed in
 * Emsfb\_Public, which for a long time carried its own second copy of the
 * comparison logic. That copy never resolved an option id_ to the value a row
 * stores, so a rule the builder wrote on a select or multiselect option
 * silently never fired: no email, no conditional thank-you, no webhook call.
 * The existing fixtures missed it because they were written with the option's
 * visible TEXT, which the builder never produces.
 *
 * Every scenario here therefore spells choice conditions the way the builder
 * does — `"value": "<option id_>"` — and drives the real _Public methods end to
 * end rather than a helper, so what is asserted is what a visitor would get.
 *
 * The form deliberately mixes field types, four condition sources, all the text
 * operators, numeric/date ranges, and groups nested two and three deep, in the
 * shapes people actually build: a qualification funnel, a routing table, a
 * regional override, and a VIP escalation.
 *
 * Run: C:\xampp\php\php.exe tests/test-conditional-logic-scopes.php
 */

define('ABSPATH', __DIR__ . '/');

$GLOBALS['efb_webhook_posts'] = array();
$GLOBALS['efb_webhook_gets']  = array();
$GLOBALS['efb_referer']       = '';
$GLOBALS['efb_logged_in']     = false;
$GLOBALS['efb_roles']         = array();

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
function __return_true() { return true; }
function do_action() {}
function is_admin() { return false; }
function sanitize_text_field($v) { return is_array($v) ? '' : trim(strip_tags((string) $v)); }
function sanitize_email($v) { return filter_var((string) $v, FILTER_SANITIZE_EMAIL); }
function is_email($v) { return filter_var((string) $v, FILTER_VALIDATE_EMAIL) !== false; }
function esc_url($v) { return filter_var((string) $v, FILTER_SANITIZE_URL); }
function esc_url_raw($v) { return filter_var((string) $v, FILTER_SANITIZE_URL); }
function sanitize_url($v) { return esc_url_raw($v); }
function wp_kses_post($v) { return strip_tags((string) $v, '<b><strong><em><i><br><p><a>'); }
function wp_unslash($v) { return $v; }
function get_site_url() { return 'https://site.test'; }
function wp_json_encode($v) { return json_encode($v); }
function add_query_arg($args, $url) {
    $sep = strpos($url, '?') === false ? '?' : '&';
    return $url . $sep . http_build_query($args);
}
function wp_remote_post($url, $args = array()) {
    $GLOBALS['efb_webhook_posts'][] = array('url' => $url, 'args' => $args);
    return array('response' => array('code' => 200), 'body' => 'ok');
}
function wp_remote_get($url, $args = array()) {
    $GLOBALS['efb_webhook_gets'][] = array('url' => $url, 'args' => $args);
    return array('response' => array('code' => 200), 'body' => 'ok');
}
/* The request environment the scope rules read: query params come from the page
   the form was submitted from, user state from the session. */
function wp_get_referer() { return $GLOBALS['efb_referer']; }
function is_user_logged_in() { return $GLOBALS['efb_logged_in']; }
function wp_get_current_user() { return (object) array('roles' => $GLOBALS['efb_roles']); }

require __DIR__ . '/../vendor/logic/logic/class-Emsfb-logic-validator.php';
require __DIR__ . '/../includes/class-Emsfb-public.php';

class Scope_Test_Public extends Emsfb\_Public {
    public $sent = array();
    public function send_email_Emsfb_($to, $track, $pro, $state, $link, $content = 'null', $sub = 'null') {
        $this->sent[] = array('to' => is_array($to) ? $to[0] : $to, 'sub' => $sub);
    }
}

$pass = 0; $fail = 0; $failures = array(); $scenarioLog = array();
function test($label, $actual, $expected) {
    global $pass, $fail, $failures;
    if ($actual === $expected) { $pass++; return; }
    $fail++;
    $failures[] = "$label\n      expected " . json_encode($expected) . "\n      actual   " . json_encode($actual);
}
function scenario($id, $title) { global $scenarioLog; $scenarioLog[] = array($id, $title); }

$ref     = new ReflectionClass('Scope_Test_Public');
$obj     = $ref->newInstanceWithoutConstructor();
$parent  = new ReflectionClass('Emsfb\\_Public');
$notify  = $parent->getMethod('process_conditional_notification_rules');  $notify->setAccessible(true);
$confirm = $parent->getMethod('get_conditional_confirmation_result');     $confirm->setAccessible(true);
$hooks   = $parent->getMethod('process_conditional_webhook_rules');       $hooks->setAccessible(true);
$idProp  = $parent->getProperty('id');                                    $idProp->setAccessible(true);
$idProp->setValue($obj, 900);

// ─────────────────────────────────────────────────────────────────────────────
// The form: a B2B project enquiry, three steps, ids that disagree with positions
// ─────────────────────────────────────────────────────────────────────────────
function opt($id, $parent, $value) {
    return array('id_' => $id, 'type' => 'option', 'parent' => $parent, 'value' => $value, 'step' => '1');
}
function fld($id, $type, $step, $extra = array()) {
    return array_merge(array('id_' => $id, 'type' => $type, 'step' => (string) $step,
        'name' => $id, 'required' => '0', 'value' => ''), $extra);
}

function enquiry_form($rules = array()) {
    $form = array(array_merge(array('type' => 'form', 'steps' => '3', 'logic' => '1'), $rules));
    return array_merge($form, array(
        array('id_' => '7', 'type' => 'step', 'step' => '1', 'name' => 'About you'),
        array('id_' => '2', 'type' => 'step', 'step' => '2', 'name' => 'Project'),
        array('id_' => '5', 'type' => 'step', 'step' => '3', 'name' => 'Budget'),

        fld('company_type', 'select', 1, array('name' => 'Organisation type')),
        opt('ct_individual', 'company_type', 'Individual'),
        opt('ct_company',    'company_type', 'Company'),
        opt('ct_nonprofit',  'company_type', 'Non-profit'),
        opt('ct_agency',     'company_type', 'Agency'),

        fld('country', 'select', 1, array('name' => 'Country')),
        opt('co_de', 'country', 'Germany'),
        opt('co_ir', 'country', 'Iran'),
        opt('co_us', 'country', 'United States'),

        fld('services', 'checkbox', 2, array('name' => 'Services needed')),
        opt('sv_design',  'services', 'Design'),
        opt('sv_dev',     'services', 'Development'),
        opt('sv_seo',     'services', 'SEO'),
        opt('sv_support', 'services', 'Support'),

        fld('plans', 'multiselect', 2, array('name' => 'Interested plans')),
        opt('pl_basic', 'plans', 'Basic'),
        opt('pl_pro',   'plans', 'Pro'),
        opt('pl_ent',   'plans', 'Enterprise'),

        fld('urgency', 'radio', 2, array('name' => 'Urgency')),
        opt('ur_low',    'urgency', 'Low'),
        opt('ur_normal', 'urgency', 'Normal'),
        opt('ur_urgent', 'urgency', 'Urgent'),

        fld('has_contract', 'yesNo',    2, array('name' => 'Existing contract')),
        fld('start_date',   'date',     2, array('name' => 'Preferred start')),
        fld('budget',       'number',   3, array('name' => 'Budget')),
        fld('employees',    'number',   3, array('name' => 'Employees')),
        fld('work_email',   'email',    1, array('name' => 'Work email')),
        fld('company_name', 'text',     1, array('name' => 'Company name')),
        fld('website',      'url',      1, array('name' => 'Website')),
        fld('notes',        'textarea', 3, array('name' => 'Notes')),
        fld('referral',     'text',     3, array('name' => 'Referral code')),
        fld('nda_file',     'dadfile',  3, array('name' => 'NDA')),
    ));
}

/* Rows exactly as core-efb.js builds them: select/multiselect carry the visible
   text, radio/checkbox/yesNo carry the option id in id_ob. */
function rows($over = array()) {
    $d = array_merge(array(
        'company_type' => 'Company', 'country' => 'Germany',
        'services' => array('sv_dev'), 'plans' => 'Pro',
        'urgency' => 'ur_normal', 'has_contract' => 'yes',
        'start_date' => '2026-09-01', 'budget' => '25000', 'employees' => '120',
        'work_email' => 'buyer@acme-corp.com', 'company_name' => 'Acme Corporation',
        'website' => 'https://acme-corp.com/about', 'notes' => 'We need a rebuild before Q4',
        'referral' => 'PARTNER-2026', 'nda_file' => '',
    ), $over);

    $out = array();
    $out[] = array('id_' => 'company_type', 'type' => 'select', 'value' => $d['company_type']);
    $out[] = array('id_' => 'country',      'type' => 'select', 'value' => $d['country']);
    foreach ((array) $d['services'] as $sv) {
        if ($sv !== '') $out[] = array('id_' => 'services', 'type' => 'checkbox', 'id_ob' => $sv, 'value' => $sv);
    }
    if ($d['plans'] !== '') $out[] = array('id_' => 'plans', 'type' => 'multiselect', 'value' => $d['plans']);
    if ($d['urgency'] !== '') $out[] = array('id_' => 'urgency', 'type' => 'radio', 'id_ob' => $d['urgency'], 'value' => $d['urgency']);
    if ($d['has_contract'] !== '') {
        $out[] = array('id_' => 'has_contract', 'type' => 'yesNo',
            'id_ob' => $d['has_contract'] === 'yes' ? 'has_contract_1' : 'has_contract_2', 'value' => $d['has_contract']);
    }
    foreach (array('start_date', 'budget', 'employees', 'work_email', 'company_name', 'website', 'notes', 'referral') as $k) {
        if ($d[$k] !== '') $out[] = array('id_' => $k, 'type' => 'text', 'value' => $d[$k]);
    }
    if ($d['nda_file'] !== '') $out[] = array('id_' => 'nda_file', 'type' => 'dadfile', 'value' => $d['nda_file'], 'url' => 'https://x/' . $d['nda_file']);
    return $out;
}

function cond($field, $compare, $value, $extra = array()) {
    return array_merge(array('type' => 'condition', 'source' => 'field',
        'field_id' => $field, 'compare' => $compare, 'value' => $value), $extra);
}
function grp($operator, $items, $extra = array()) {
    return array_merge(array('type' => 'group', 'operator' => $operator, 'items' => $items), $extra);
}
function env($referer = '', $loggedIn = false, $roles = array()) {
    $GLOBALS['efb_referer'] = $referer;
    $GLOBALS['efb_logged_in'] = $loggedIn;
    $GLOBALS['efb_roles'] = $roles;
}

$status_email = array('content' => 'null', 'type' => 'msg', 'subject' => 'Enquiry');

function recipients($obj) {
    $r = array_map(function ($s) { return $s['to']; }, $obj->sent);
    sort($r);
    return $r;
}
function hookUrls() {
    $u = array();
    foreach ($GLOBALS['efb_webhook_posts'] as $p) $u[] = $p['url'];
    foreach ($GLOBALS['efb_webhook_gets'] as $g)  $u[] = preg_replace('/\?.*$/', '', $g['url']);
    sort($u);
    return $u;
}
function resetHooks() { $GLOBALS['efb_webhook_posts'] = array(); $GLOBALS['efb_webhook_gets'] = array(); }

/* ── Run one scope end to end ─────────────────────────────────────────────── */
function runNotify($obj, $notify, $rules, $rows, $status) {
    $obj->sent = array();
    $notify->invoke($obj, enquiry_form(array('notification_rules' => $rules)), $rows, 'TRK', false, 'https://x/', $status);
    return recipients($obj);
}
function runConfirm($obj, $confirm, $rules, $rows) {
    return $confirm->invoke($obj, enquiry_form(array('confirmation_rules' => $rules)), $rows);
}
function runHooks($obj, $hooks, $rules, $rows) {
    resetHooks();
    $hooks->invoke($obj, enquiry_form(array('webhook_rules' => $rules)), $rows, 'TRK', 'new', array());
    return hookUrls();
}

// ═════════════════════════════════════════════════════════════════════════════
// SCOPE 2 — NOTIFICATION RULES  (every matching rule sends; they do not compete)
// ═════════════════════════════════════════════════════════════════════════════
$NR = array(
    array('id' => 'nr_enterprise', 'enabled' => true, 'priority' => 10, 'recipient' => 'enterprise@example.com',
        'conditions' => grp('AND', array(
            grp('OR', array(cond('company_type', 'is', 'ct_company'), cond('company_type', 'is', 'ct_agency'))),
            cond('budget', 'gte', '20000', array('connector' => 'AND'))))),
    array('id' => 'nr_smb', 'enabled' => true, 'priority' => 11, 'recipient' => 'smb@example.com',
        'conditions' => grp('AND', array(
            cond('company_type', 'is', 'ct_company'),
            cond('budget', 'lt', '20000', array('connector' => 'AND'))))),
    array('id' => 'nr_nonprofit', 'enabled' => true, 'priority' => 12, 'recipient' => 'nonprofit@example.com',
        'conditions' => grp('AND', array(cond('company_type', 'is', 'ct_nonprofit')))),
    array('id' => 'nr_oncall', 'enabled' => true, 'priority' => 13, 'recipient' => 'oncall@example.com',
        'conditions' => grp('AND', array(
            cond('urgency', 'is', 'ur_urgent'),
            grp('OR', array(cond('services', 'is', 'sv_support'), cond('has_contract', 'is', 'yes')),
                array('connector' => 'AND'))))),
    array('id' => 'nr_partner', 'enabled' => true, 'priority' => 14, 'recipient' => 'partners@example.com',
        'conditions' => grp('AND', array(cond('referral', 'starts_with', 'PARTNER-')))),
    array('id' => 'nr_de', 'enabled' => true, 'priority' => 15, 'recipient' => 'de@example.com',
        'conditions' => grp('AND', array(cond('country', 'is', 'co_de')))),
    array('id' => 'nr_campaign', 'enabled' => true, 'priority' => 16, 'recipient' => 'campaign@example.com',
        'conditions' => grp('AND', array(cond('utm_source', 'is', 'linkedin',
            array('source' => 'query_param', 'param' => 'utm_source'))))),
    array('id' => 'nr_member', 'enabled' => true, 'priority' => 17, 'recipient' => 'members@example.com',
        'conditions' => grp('AND', array(cond('logged_in', 'is', 'yes', array('source' => 'user'))))),
    array('id' => 'nr_editor', 'enabled' => true, 'priority' => 18, 'recipient' => 'editors@example.com',
        'conditions' => grp('AND', array(cond('role', 'is', 'editor', array('source' => 'user'))))),
    /* three levels deep: company AND ( enterprise-plan OR ( dev AND >=100 staff ) ) */
    array('id' => 'nr_deep', 'enabled' => true, 'priority' => 19, 'recipient' => 'deep@example.com',
        'conditions' => grp('AND', array(
            cond('company_type', 'is', 'ct_company'),
            grp('OR', array(
                cond('plans', 'is', 'pl_ent'),
                grp('AND', array(cond('services', 'is', 'sv_dev'), cond('employees', 'gte', '100')),
                    array('connector' => 'OR'))
            ), array('connector' => 'AND'))))),
    array('id' => 'nr_notiran', 'enabled' => true, 'priority' => 20, 'recipient' => 'global@example.com',
        'conditions' => grp('AND', array(cond('country', 'is', 'co_ir')), array('negate' => true))),
    array('id' => 'nr_off', 'enabled' => false, 'priority' => 21, 'recipient' => 'never@example.com',
        'conditions' => grp('AND', array(cond('company_type', 'is', 'ct_company')))),
    array('id' => 'nr_named', 'enabled' => true, 'priority' => 22, 'recipient' => 'named@example.com',
        'conditions' => grp('AND', array(cond('work_email', 'ends_with', '@acme-corp.com')))),
    array('id' => 'nr_content', 'enabled' => true, 'priority' => 23, 'recipient' => 'content@example.com',
        'conditions' => grp('AND', array(cond('notes', 'contains', 'rebuild')))),
    array('id' => 'nr_calm', 'enabled' => true, 'priority' => 24, 'recipient' => 'calm@example.com',
        'conditions' => grp('AND', array(cond('notes', 'not_contains', 'lawsuit')))),
    array('id' => 'nr_nonda', 'enabled' => true, 'priority' => 25, 'recipient' => 'nonda@example.com',
        'conditions' => grp('AND', array(cond('nda_file', 'is_empty', '')))),
    array('id' => 'nr_hasref', 'enabled' => true, 'priority' => 26, 'recipient' => 'hasref@example.com',
        'conditions' => grp('AND', array(cond('referral', 'is_not_empty', '')))),
    array('id' => 'nr_laststep', 'enabled' => true, 'priority' => 27, 'recipient' => 'laststep@example.com',
        'conditions' => grp('AND', array(cond('current_step', 'is', '3', array('source' => 'current_step'))))),
    array('id' => 'nr_website', 'enabled' => true, 'priority' => 28, 'recipient' => 'web@example.com',
        'conditions' => grp('AND', array(cond('website', 'starts_with', 'https://')))),
    array('id' => 'nr_notindiv', 'enabled' => true, 'priority' => 29, 'recipient' => 'notindiv@example.com',
        'conditions' => grp('AND', array(cond('company_type', 'is_not', 'ct_individual')))),
    array('id' => 'nr_window', 'enabled' => true, 'priority' => 30, 'recipient' => 'window@example.com',
        'conditions' => grp('AND', array(cond('start_date', 'date_between', '2026-08-01,2026-10-31')))),
);

env('https://site.test/enquiry?utm_source=linkedin&ref=news', false, array());
scenario('N1', 'German company, 25k budget, dev work, 120 staff, partner referral, arriving from a LinkedIn campaign');
test('N1 routes to exactly the right desks', runNotify($obj, $notify, $NR, rows(), $status_email), array(
    'calm@example.com', 'campaign@example.com', 'content@example.com', 'de@example.com',
    'deep@example.com', 'enterprise@example.com', 'global@example.com', 'hasref@example.com',
    'laststep@example.com', 'named@example.com', 'nonda@example.com', 'notindiv@example.com',
    'partners@example.com', 'web@example.com', 'window@example.com',
));

env('', false, array());
scenario('N2', 'Same company but a small budget: the SMB desk takes over from enterprise');
$r = runNotify($obj, $notify, $NR, rows(array('budget' => '4000')), $status_email);
test('N2 enterprise desk not notified', in_array('enterprise@example.com', $r, true), false);
test('N2 SMB desk notified', in_array('smb@example.com', $r, true), true);
test('N2 campaign desk silent without the URL parameter', in_array('campaign@example.com', $r, true), false);

scenario('N3', 'Non-profit in Iran, no referral, urgent, holds a support contract');
$r = runNotify($obj, $notify, $NR, rows(array(
    'company_type' => 'Non-profit', 'country' => 'Iran', 'referral' => '',
    'urgency' => 'ur_urgent', 'services' => array('sv_support'), 'budget' => '3000')), $status_email);
test('N3 non-profit desk notified', in_array('nonprofit@example.com', $r, true), true);
test('N3 on-call notified (urgent AND support)', in_array('oncall@example.com', $r, true), true);
test('N3 NOT-group suppresses the global desk', in_array('global@example.com', $r, true), false);
test('N3 partner desk silent (starts_with on an empty referral)', in_array('partners@example.com', $r, true), false);
test('N3 referral-present desk silent', in_array('hasref@example.com', $r, true), false);
test('N3 enterprise + SMB both silent for a non-profit',
    in_array('enterprise@example.com', $r, true) || in_array('smb@example.com', $r, true), false);

scenario('N4', 'Agency, enterprise plan, urgent but no contract and no support line');
$r = runNotify($obj, $notify, $NR, rows(array(
    'company_type' => 'Agency', 'plans' => 'Enterprise', 'urgency' => 'ur_urgent',
    'has_contract' => 'no', 'services' => array('sv_design'), 'budget' => '80000')), $status_email);
test('N4 enterprise desk notified through the OR arm', in_array('enterprise@example.com', $r, true), true);
test('N4 on-call NOT notified: urgent, but neither support nor contract', in_array('oncall@example.com', $r, true), false);
test('N4 deep rule needs company_type company, agency does not qualify', in_array('deep@example.com', $r, true), false);

scenario('N5', 'Company on the enterprise plan with only 3 staff — the nested OR still qualifies it');
$r = runNotify($obj, $notify, $NR, rows(array('plans' => 'Enterprise', 'employees' => '3', 'services' => array('sv_seo'))), $status_email);
test('N5 deep rule matches via the enterprise-plan arm', in_array('deep@example.com', $r, true), true);

scenario('N6', 'Company doing dev with 99 staff and no enterprise plan — nested AND fails on the headcount');
$r = runNotify($obj, $notify, $NR, rows(array('plans' => 'Basic', 'employees' => '99', 'services' => array('sv_dev'))), $status_email);
test('N6 deep rule does not match', in_array('deep@example.com', $r, true), false);

scenario('N7', 'Logged-in editor submits the form');
env('', true, array('editor'));
$r = runNotify($obj, $notify, $NR, rows(), $status_email);
test('N7 member desk notified', in_array('members@example.com', $r, true), true);
test('N7 editor desk notified', in_array('editors@example.com', $r, true), true);

scenario('N8', 'Logged-in subscriber — member desk yes, editor desk no');
env('', true, array('subscriber'));
$r = runNotify($obj, $notify, $NR, rows(), $status_email);
test('N8 member desk notified', in_array('members@example.com', $r, true), true);
test('N8 editor desk not notified', in_array('editors@example.com', $r, true), false);
env('', false, array());

scenario('N9', 'An NDA is attached and the notes mention a lawsuit');
$r = runNotify($obj, $notify, $NR, rows(array('nda_file' => 'nda.pdf', 'notes' => 'Pending lawsuit, please advise')), $status_email);
test('N9 no-NDA desk silent once a file is attached', in_array('nonda@example.com', $r, true), false);
test('N9 not_contains desk silent when the word is present', in_array('calm@example.com', $r, true), false);
test('N9 contains desk silent when the word is absent', in_array('content@example.com', $r, true), false);

scenario('N10', 'Individual with a start date outside the window and an http website');
$r = runNotify($obj, $notify, $NR, rows(array(
    'company_type' => 'Individual', 'start_date' => '2027-03-01', 'website' => 'http://example.org')), $status_email);
test('N10 is_not desk silent for an individual', in_array('notindiv@example.com', $r, true), false);
test('N10 date window desk silent outside the range', in_array('window@example.com', $r, true), false);
test('N10 https desk silent for a plain http site', in_array('web@example.com', $r, true), false);

scenario('N11', 'A disabled rule never sends, whatever the answers');
$r = runNotify($obj, $notify, $NR, rows(), $status_email);
test('N11 disabled rule silent', in_array('never@example.com', $r, true), false);

// ═════════════════════════════════════════════════════════════════════════════
// SCOPE 3 — CONFIRMATION RULES  (lowest priority that matches wins; only one)
// ═════════════════════════════════════════════════════════════════════════════
$CR = array(
    array('id' => 'cr_vip', 'enabled' => true, 'priority' => 5, 'action' => 'redirect',
        'url' => 'https://site.test/vip-thanks',
        'conditions' => grp('AND', array(
            cond('budget', 'gte', '50000'),
            grp('OR', array(cond('company_type', 'is', 'ct_company'), cond('company_type', 'is', 'ct_agency')),
                array('connector' => 'AND'))))),
    array('id' => 'cr_urgent', 'enabled' => true, 'priority' => 10, 'action' => 'message',
        'message' => 'We will call you within two hours.', 'done' => 'Escalated',
        'icon' => 'bi-lightning-charge', 'icon_color' => '#ff8800',
        'conditions' => grp('AND', array(cond('urgency', 'is', 'ur_urgent')))),
    array('id' => 'cr_nonprofit', 'enabled' => true, 'priority' => 20, 'action' => 'message',
        'message' => 'Non-profit pricing applies.', 'done' => 'Received',
        'conditions' => grp('AND', array(cond('company_type', 'is', 'ct_nonprofit')))),
    array('id' => 'cr_campaign', 'enabled' => true, 'priority' => 25, 'action' => 'message',
        'message' => 'Thanks for coming from the campaign.', 'done' => 'Received',
        'conditions' => grp('AND', array(cond('utm_source', 'is_not_empty', '',
            array('source' => 'query_param', 'param' => 'utm_source'))))),
    array('id' => 'cr_generic', 'enabled' => true, 'priority' => 30, 'action' => 'message',
        'message' => 'Thanks, we have your enquiry.', 'done' => 'Received',
        'conditions' => grp('AND', array(cond('country', 'is_not_empty', '')))),
);

env('', false, array());
scenario('C1', 'Enterprise budget from a company: the highest-priority redirect wins over every message rule');
$r = runConfirm($obj, $confirm, $CR, rows(array('budget' => '90000')));
test('C1 redirect chosen', $r['action'], 'redirect');
test('C1 redirect target', $r['url'], 'https://site.test/vip-thanks');

scenario('C2', 'Urgent but a small budget: the VIP rule fails, urgency takes it');
$r = runConfirm($obj, $confirm, $CR, rows(array('budget' => '9000', 'urgency' => 'ur_urgent')));
test('C2 message chosen', $r['action'], 'message');
test('C2 urgency message shown', $r['message'], 'We will call you within two hours.');
test('C2 done label overridden', $r['done'], 'Escalated');
test('C2 icon override survives sanitising', $r['icon'], 'bi-lightning-charge');

scenario('C3', 'Non-profit, not urgent: falls past urgency to the non-profit rule');
$r = runConfirm($obj, $confirm, $CR, rows(array('company_type' => 'Non-profit', 'urgency' => 'ur_low', 'budget' => '2000')));
test('C3 non-profit message shown', $r['message'], 'Non-profit pricing applies.');

scenario('C4', 'Nothing special, but the visitor arrived from a campaign URL');
env('https://site.test/enquiry?utm_source=newsletter', false, array());
$r = runConfirm($obj, $confirm, $CR, rows(array('urgency' => 'ur_low', 'budget' => '1000')));
test('C4 campaign message wins over the generic one', $r['message'], 'Thanks for coming from the campaign.');

scenario('C5', 'Nothing special and no campaign: the generic rule is the last one standing');
env('', false, array());
$r = runConfirm($obj, $confirm, $CR, rows(array('urgency' => 'ur_low', 'budget' => '1000')));
test('C5 generic message shown', $r['message'], 'Thanks, we have your enquiry.');

scenario('C6', 'No rule matches at all: the form keeps its default thank-you');
$r = runConfirm($obj, $confirm, $CR, rows(array('urgency' => 'ur_low', 'budget' => '1000', 'country' => '')));
test('C6 null returned so the default is used', $r, null);

scenario('C7', 'Agency at 50000 exactly — the gte boundary is inclusive');
$r = runConfirm($obj, $confirm, $CR, rows(array('company_type' => 'Agency', 'budget' => '50000')));
test('C7 redirect chosen at the boundary', $r['action'], 'redirect');

scenario('C8', 'Individual at 90000 — the nested OR excludes individuals from the VIP path');
$r = runConfirm($obj, $confirm, $CR, rows(array('company_type' => 'Individual', 'budget' => '90000', 'urgency' => 'ur_low')));
test('C8 not a redirect', $r['action'], 'message');
test('C8 falls through to generic', $r['message'], 'Thanks, we have your enquiry.');

// ═════════════════════════════════════════════════════════════════════════════
// SCOPE 4 — WEBHOOK RULES  (every match fires; a `stop` rule cancels the rest)
// ═════════════════════════════════════════════════════════════════════════════
$WH = array(
    array('id' => 'wh_crm', 'enabled' => true, 'priority' => 10, 'action' => 'trigger',
        'url' => 'https://crm.example.com/lead', 'method' => 'POST',
        'conditions' => grp('AND', array(cond('company_type', 'is', 'ct_company')))),
    array('id' => 'wh_slack', 'enabled' => true, 'priority' => 11, 'action' => 'trigger',
        'url' => 'https://hooks.example.com/slack', 'method' => 'GET',
        'conditions' => grp('AND', array(cond('urgency', 'is', 'ur_urgent')))),
    array('id' => 'wh_ent', 'enabled' => true, 'priority' => 12, 'action' => 'trigger',
        'url' => 'https://erp.example.com/enterprise', 'method' => 'POST',
        'payload_fields' => array('company_name', 'budget'),
        'conditions' => grp('AND', array(
            cond('plans', 'is', 'pl_ent'),
            cond('budget', 'gte', '20000', array('connector' => 'AND'))))),
    array('id' => 'wh_partner', 'enabled' => true, 'priority' => 13, 'action' => 'trigger',
        'url' => 'https://partners.example.com/referral', 'method' => 'POST',
        'conditions' => grp('AND', array(cond('referral', 'starts_with', 'PARTNER-')))),
    array('id' => 'wh_stop_sanctioned', 'enabled' => true, 'priority' => 1, 'action' => 'stop', 'url' => '',
        'conditions' => grp('AND', array(cond('country', 'is', 'co_ir')))),
);

scenario('W1', 'German company, urgent, enterprise plan, partner referral — four hooks, no stop');
$u = runHooks($obj, $hooks, $WH, rows(array('urgency' => 'ur_urgent', 'plans' => 'Enterprise')));
test('W1 all four hooks fire', $u, array(
    'https://crm.example.com/lead', 'https://erp.example.com/enterprise',
    'https://hooks.example.com/slack', 'https://partners.example.com/referral'));

scenario('W2', 'Same enquiry from a sanctioned country: the stop rule cancels every hook');
$u = runHooks($obj, $hooks, $WH, rows(array('country' => 'Iran', 'urgency' => 'ur_urgent', 'plans' => 'Enterprise')));
test('W2 nothing is called', $u, array());

scenario('W3', 'Individual, not urgent, basic plan, no referral: no hook qualifies');
$u = runHooks($obj, $hooks, $WH, rows(array(
    'company_type' => 'Individual', 'urgency' => 'ur_low', 'plans' => 'Basic', 'referral' => '')));
test('W3 no hook fires', $u, array());

scenario('W4', 'Enterprise plan but only a 5000 budget: the AND arm blocks the ERP hook');
$u = runHooks($obj, $hooks, $WH, rows(array('plans' => 'Enterprise', 'budget' => '5000', 'referral' => '', 'urgency' => 'ur_low')));
test('W4 only the CRM hook fires', $u, array('https://crm.example.com/lead'));

scenario('W5', 'The GET hook carries its payload in the query string');
runHooks($obj, $hooks, $WH, rows(array('urgency' => 'ur_urgent')));
test('W5 exactly one GET call', count($GLOBALS['efb_webhook_gets']), 1);
test('W5 the GET call is the slack hook',
    strpos($GLOBALS['efb_webhook_gets'][0]['url'], 'https://hooks.example.com/slack') === 0, true);

scenario('W6', 'payload_fields keeps only the named answers out of the POST body');
runHooks($obj, $hooks, $WH, rows(array('plans' => 'Enterprise', 'company_type' => 'Company',
    'urgency' => 'ur_low', 'referral' => '')));
$erp = null;
foreach ($GLOBALS['efb_webhook_posts'] as $p) if (strpos($p['url'], 'erp.example.com') !== false) $erp = $p;
test('W6 the ERP hook was called', $erp !== null, true);
if ($erp) {
    /* The payload carries the answers twice: `values` as a field_id => value map
       and `submitted_values` as the raw rows. payload_fields must trim both. */
    $body = json_decode($erp['args']['body'], true);
    $mapKeys = isset($body['values']) ? array_keys($body['values']) : array();
    sort($mapKeys);
    test('W6 values map holds only the allow-listed fields', $mapKeys, array('budget', 'company_name'));

    $rowIds = array();
    foreach ((array) (isset($body['submitted_values']) ? $body['submitted_values'] : array()) as $row) {
        if (isset($row['id_'])) $rowIds[] = $row['id_'];
    }
    sort($rowIds);
    test('W6 raw rows are trimmed to the same allow-list', $rowIds, array('budget', 'company_name'));
    test('W6 a field outside the allow-list is absent from the map',
        array_key_exists('notes', (array) $body['values']), false);
}

// ═════════════════════════════════════════════════════════════════════════════
// SCOPE 1 — FIELD RULES, and the cross-scope agreement check
// The same condition, written the same way, must answer identically in all four
// collections. That is the property the two shipped bugs broke.
// ═════════════════════════════════════════════════════════════════════════════
$validator = new \Emsfb\Emsfb_Logic_Validator();

function fieldMatches($validator, $conditions, $rows) {
    $form = enquiry_form(array('logic_rules' => array(array(
        'id' => 'fr', 'enabled' => true, 'priority' => 10, 'stop_processing' => false,
        'conditions' => $conditions,
        'actions' => array(array('type' => 'hide_field', 'target' => 'notes')),
    ))));
    $validator->set_environment(array('query' => array(),
        'user' => array('logged_in' => false, 'roles' => array()), 'current_step' => 3));
    $r = $validator->evaluate($form, $rows);
    return in_array('fr', $r['matched_rules'], true);
}
function notifyMatches($obj, $notify, $conditions, $rows, $status) {
    $rule = array(array('id' => 'x', 'enabled' => true, 'priority' => 10,
        'recipient' => 'probe@example.com', 'conditions' => $conditions));
    return in_array('probe@example.com', runNotify($obj, $notify, $rule, $rows, $status), true);
}
function confirmMatches($obj, $confirm, $conditions, $rows) {
    $rule = array(array('id' => 'x', 'enabled' => true, 'priority' => 10, 'action' => 'message',
        'message' => 'probe', 'conditions' => $conditions));
    $r = runConfirm($obj, $confirm, $rule, $rows);
    return is_array($r) && isset($r['message']) && $r['message'] === 'probe';
}
function hookMatches($obj, $hooks, $conditions, $rows) {
    $rule = array(array('id' => 'x', 'enabled' => true, 'priority' => 10, 'action' => 'trigger',
        'url' => 'https://probe.example.com/x', 'method' => 'POST', 'conditions' => $conditions));
    return in_array('https://probe.example.com/x', runHooks($obj, $hooks, $rule, $rows), true);
}

env('', false, array());
$AGREE = array(
    array('X1',  'select is <option id>',        grp('AND', array(cond('company_type', 'is', 'ct_company'))), rows(), true),
    array('X2',  'select is <other option>',     grp('AND', array(cond('company_type', 'is', 'ct_agency'))), rows(), false),
    array('X3',  'select is_not <other option>', grp('AND', array(cond('company_type', 'is_not', 'ct_agency'))), rows(), true),
    array('X4',  'select is_not <chosen>',       grp('AND', array(cond('company_type', 'is_not', 'ct_company'))), rows(), false),
    array('X5',  'checkbox is <ticked>',         grp('AND', array(cond('services', 'is', 'sv_dev'))), rows(), true),
    array('X6',  'checkbox is <unticked>',       grp('AND', array(cond('services', 'is', 'sv_seo'))), rows(), false),
    array('X7',  'checkbox is_not <unticked>',   grp('AND', array(cond('services', 'is_not', 'sv_seo'))), rows(), true),
    array('X8',  'checkbox is_not <ticked>',     grp('AND', array(cond('services', 'is_not', 'sv_dev'))), rows(), false),
    array('X9',  'radio is <chosen>',            grp('AND', array(cond('urgency', 'is', 'ur_normal'))), rows(), true),
    array('X10', 'radio is <other>',             grp('AND', array(cond('urgency', 'is', 'ur_urgent'))), rows(), false),
    array('X11', 'multiselect is <selected>',    grp('AND', array(cond('plans', 'is', 'pl_pro'))), rows(), true),
    array('X12', 'multiselect is <absent>',      grp('AND', array(cond('plans', 'is', 'pl_ent'))), rows(), false),
    array('X13', 'yesNo is yes',                 grp('AND', array(cond('has_contract', 'is', 'yes'))), rows(), true),
    array('X14', 'yesNo is no',                  grp('AND', array(cond('has_contract', 'is', 'no'))), rows(), false),
    array('X15', 'text contains',                grp('AND', array(cond('notes', 'contains', 'rebuild'))), rows(), true),
    array('X16', 'text not_contains',            grp('AND', array(cond('notes', 'not_contains', 'lawsuit'))), rows(), true),
    array('X17', 'text starts_with',             grp('AND', array(cond('referral', 'starts_with', 'PARTNER-'))), rows(), true),
    array('X18', 'text ends_with',               grp('AND', array(cond('work_email', 'ends_with', '@acme-corp.com'))), rows(), true),
    array('X19', 'text is_empty on a filled field', grp('AND', array(cond('notes', 'is_empty', ''))), rows(), false),
    array('X20', 'file is_empty when nothing uploaded', grp('AND', array(cond('nda_file', 'is_empty', ''))), rows(), true),
    array('X21', 'number gte boundary',          grp('AND', array(cond('budget', 'gte', '25000'))), rows(), true),
    array('X22', 'number gt boundary',           grp('AND', array(cond('budget', 'gt', '25000'))), rows(), false),
    array('X23', 'number between',               grp('AND', array(cond('employees', 'between', '100,200'))), rows(), true),
    array('X24', 'number not_between',           grp('AND', array(cond('employees', 'not_between', '100,200'))), rows(), false),
    array('X25', 'date_between inside',          grp('AND', array(cond('start_date', 'date_between', '2026-08-01,2026-10-31'))), rows(), true),
    array('X26', 'date_after',                   grp('AND', array(cond('start_date', 'date_after', '2026-12-01'))), rows(), false),
    array('X27', 'AND of two true',              grp('AND', array(cond('company_type', 'is', 'ct_company'), cond('budget', 'gte', '20000'))), rows(), true),
    array('X28', 'AND with one false',           grp('AND', array(cond('company_type', 'is', 'ct_company'), cond('budget', 'gte', '99000'))), rows(), false),
    array('X29', 'OR rescues a false first',     grp('OR',  array(cond('budget', 'gte', '99000'), cond('company_type', 'is', 'ct_company'))), rows(), true),
    array('X30', 'per-item OR inside an AND group',
        grp('AND', array(cond('budget', 'gte', '99000'), cond('company_type', 'is', 'ct_company', array('connector' => 'OR')))), rows(), true),
    array('X31', 'nested OR inside AND',
        grp('AND', array(cond('company_type', 'is', 'ct_company'),
            grp('OR', array(cond('plans', 'is', 'pl_ent'), cond('services', 'is', 'sv_dev')), array('connector' => 'AND')))), rows(), true),
    array('X32', 'nested OR inside AND, both arms false',
        grp('AND', array(cond('company_type', 'is', 'ct_company'),
            grp('OR', array(cond('plans', 'is', 'pl_ent'), cond('services', 'is', 'sv_seo')), array('connector' => 'AND')))), rows(), false),
    array('X33', 'three levels deep, true',
        grp('AND', array(cond('company_type', 'is', 'ct_company'),
            grp('OR', array(cond('plans', 'is', 'pl_ent'),
                grp('AND', array(cond('services', 'is', 'sv_dev'), cond('employees', 'gte', '100')), array('connector' => 'OR'))
            ), array('connector' => 'AND')))), rows(), true),
    array('X34', 'three levels deep, inner AND fails',
        grp('AND', array(cond('company_type', 'is', 'ct_company'),
            grp('OR', array(cond('plans', 'is', 'pl_ent'),
                grp('AND', array(cond('services', 'is', 'sv_dev'), cond('employees', 'gte', '500')), array('connector' => 'OR'))
            ), array('connector' => 'AND')))), rows(), false),
    array('X35', 'NOT of a true group',          grp('AND', array(cond('company_type', 'is', 'ct_company')), array('negate' => true)), rows(), false),
    array('X36', 'NOT of a false group',         grp('AND', array(cond('company_type', 'is', 'ct_agency')), array('negate' => true)), rows(), true),
    array('X37', 'NAND, both true',              grp('AND', array(cond('company_type', 'is', 'ct_company'), cond('budget', 'gte', '20000')), array('negate' => true)), rows(), false),
    array('X38', 'NOR, neither true',            grp('OR',  array(cond('company_type', 'is', 'ct_agency'), cond('budget', 'gte', '99000')), array('negate' => true)), rows(), true),
    array('X39', 'negated nested group',
        grp('AND', array(cond('company_type', 'is', 'ct_company'),
            grp('OR', array(cond('plans', 'is', 'pl_ent')), array('connector' => 'AND', 'negate' => true)))), rows(), true),
    array('X40', 'select is_empty when nothing chosen',
        grp('AND', array(cond('company_type', 'is_empty', ''))), rows(array('company_type' => '')), true),
);

foreach ($AGREE as $case) {
    list($id, $title, $conditions, $caseRows, $expected) = $case;
    scenario($id, 'cross-scope: ' . $title);
    test("$id field scope",        fieldMatches($validator, $conditions, $caseRows), $expected);
    test("$id notification scope", notifyMatches($obj, $notify, $conditions, $caseRows, $status_email), $expected);
    test("$id confirmation scope", confirmMatches($obj, $confirm, $conditions, $caseRows), $expected);
    test("$id webhook scope",      hookMatches($obj, $hooks, $conditions, $caseRows), $expected);
}




// ═════════════════════════════════════════════════════════════════════════════
// PAYMENT conditions inside the scope rules
// ═════════════════════════════════════════════════════════════════════════════
function payRows($extra) { return array_merge(rows(), array(array_merge(array('id_' => 'deposit', 'type' => 'stripe'), $extra))); }
function payForm($rules) {
    $f = enquiry_form($rules);
    $f[] = fld('deposit', 'stripe', 3, array('name' => 'Deposit'));
    return $f;
}
function payNotify($obj, $notify, $conditions, $payRows, $status) {
    $obj->sent = array();
    $notify->invoke($obj, payForm(array('notification_rules' => array(array(
        'id' => 'p', 'enabled' => true, 'priority' => 10, 'recipient' => 'finance@example.com',
        'conditions' => $conditions)))), $payRows, 'TRK', false, 'https://x/', $status);
    return in_array('finance@example.com', recipients($obj), true);
}

env('', false, array());
scenario('P1', 'Paid deposit routes to finance; an unpaid one does not');
test('P1 is_paid matches a captured payment',
    payNotify($obj, $notify, grp('AND', array(cond('deposit', 'is_paid', ''))),
        payRows(array('payment_status' => 'paid', 'amount' => '500')), $status_email), true);
test('P1 is_paid does not match an unpaid row',
    payNotify($obj, $notify, grp('AND', array(cond('deposit', 'is_paid', ''))),
        payRows(array('amount' => '500')), $status_email), false);
test('P1 is_not_paid matches the unpaid row',
    payNotify($obj, $notify, grp('AND', array(cond('deposit', 'is_not_paid', ''))),
        payRows(array('amount' => '500')), $status_email), true);

scenario('P2', 'Amount thresholds gate the finance desk');
test('P2 amount_gt above the threshold',
    payNotify($obj, $notify, grp('AND', array(cond('deposit', 'amount_gt', '100'))),
        payRows(array('amount' => '500')), $status_email), true);
test('P2 amount_gt at the boundary does not fire',
    payNotify($obj, $notify, grp('AND', array(cond('deposit', 'amount_gt', '500'))),
        payRows(array('amount' => '500')), $status_email), false);
test('P2 amount_lt below the threshold',
    payNotify($obj, $notify, grp('AND', array(cond('deposit', 'amount_lt', '900'))),
        payRows(array('amount' => '500')), $status_email), true);
test('P2 amount_eq exact',
    payNotify($obj, $notify, grp('AND', array(cond('deposit', 'amount_eq', '500'))),
        payRows(array('amount' => '500')), $status_email), true);

scenario('P3', 'Paid AND a large budget — payment combined with an ordinary field');
test('P3 both arms true',
    payNotify($obj, $notify, grp('AND', array(cond('deposit', 'is_paid', ''), cond('budget', 'gte', '20000'))),
        payRows(array('payment_status' => 'paid')), $status_email), true);
test('P3 payment true but budget arm false',
    payNotify($obj, $notify, grp('AND', array(cond('deposit', 'is_paid', ''), cond('budget', 'gte', '99000'))),
        payRows(array('payment_status' => 'paid')), $status_email), false);

// ═════════════════════════════════════════════════════════════════════════════
// Confirmation redirect with {field} tokens
// ═════════════════════════════════════════════════════════════════════════════
scenario('C9', 'A conditional redirect personalises its URL from the answers');
$CRtok = array(array('id' => 'cr_tok', 'enabled' => true, 'priority' => 5, 'action' => 'redirect',
    'url' => 'https://site.test/thanks?plan={plans}&who={company_name}&missing={nope}',
    'conditions' => grp('AND', array(cond('company_type', 'is', 'ct_company')))));
$r = runConfirm($obj, $confirm, $CRtok, rows());
test('C9 redirect returned', $r['action'], 'redirect');
test('C9 tokens replaced and the unknown one removed', $r['url'],
    'https://site.test/thanks?plan=Pro&who=Acme%20Corporation&missing=');

// ═════════════════════════════════════════════════════════════════════════════
// One submission, four consequences — the way a real form is actually wired
// ═════════════════════════════════════════════════════════════════════════════
scenario('Z1', 'A single urgent enterprise enquiry drives field logic, an email, a redirect and two hooks at once');

$sharedCondition = grp('AND', array(
    cond('company_type', 'is', 'ct_company'),
    grp('OR', array(cond('plans', 'is', 'pl_ent'), cond('budget', 'gte', '50000')), array('connector' => 'AND')),
));
$zRows = rows(array('plans' => 'Enterprise', 'budget' => '75000', 'urgency' => 'ur_urgent'));

/* 1. Fields: the enterprise step opens and its field becomes required */
$zForm = enquiry_form(array('logic_rules' => array(array(
    'id' => 'z_field', 'enabled' => true, 'priority' => 10, 'stop_processing' => false,
    'conditions' => $sharedCondition,
    'actions' => array(
        array('type' => 'show_step', 'target' => '5'),
        array('type' => 'set_required', 'target' => 'notes'),
        array('type' => 'hide_field', 'target' => 'referral'),
    )))));
$validator->set_environment(array('query' => array(), 'user' => array('logged_in' => false, 'roles' => array()), 'current_step' => 3));
$zr = $validator->evaluate($zForm, $zRows);
test('Z1 field rule matched', in_array('z_field', $zr['matched_rules'], true), true);
test('Z1 the enterprise step is open', in_array('5', $zr['hidden_steps'], true), false);
test('Z1 notes became required', in_array('notes', $zr['required_fields'], true), true);
test('Z1 referral hidden and therefore dropped', in_array('referral', $zr['ignored_fields'], true), true);
$zPrepared = $validator->prepare_submission($zForm, $zRows);
$zKept = array();
foreach ($zPrepared['submitted_values'] as $row) $zKept[] = $row['id_'];
test('Z1 the hidden referral is not stored', in_array('referral', $zKept, true), false);
test('Z1 the answers on the open step are stored', in_array('notes', $zKept, true), true);

/* 2. Notification, 3. Confirmation, 4. Webhook — same condition, same verdict */
test('Z1 the notification fires', notifyMatches($obj, $notify, $sharedCondition, $zRows, $status_email), true);
test('Z1 the confirmation fires', confirmMatches($obj, $confirm, $sharedCondition, $zRows), true);
test('Z1 the webhook fires', hookMatches($obj, $hooks, $sharedCondition, $zRows), true);

scenario('Z2', 'The same wiring on a small individual enquiry stays completely quiet');
$zSmall = rows(array('company_type' => 'Individual', 'plans' => 'Basic', 'budget' => '900', 'urgency' => 'ur_low'));
$zr2 = $validator->evaluate($zForm, $zSmall);
test('Z2 field rule silent', in_array('z_field', $zr2['matched_rules'], true), false);
test('Z2 notification silent', notifyMatches($obj, $notify, $sharedCondition, $zSmall, $status_email), false);
test('Z2 confirmation silent', confirmMatches($obj, $confirm, $sharedCondition, $zSmall), false);
test('Z2 webhook silent', hookMatches($obj, $hooks, $sharedCondition, $zSmall), false);

scenario('Z3', 'Company on Basic with exactly 50000 — only the second OR arm carries it, in all four scopes');
$zEdge = rows(array('plans' => 'Basic', 'budget' => '50000', 'urgency' => 'ur_low'));
test('Z3 field scope',        in_array('z_field', $validator->evaluate($zForm, $zEdge)['matched_rules'], true), true);
test('Z3 notification scope', notifyMatches($obj, $notify, $sharedCondition, $zEdge, $status_email), true);
test('Z3 confirmation scope', confirmMatches($obj, $confirm, $sharedCondition, $zEdge), true);
test('Z3 webhook scope',      hookMatches($obj, $hooks, $sharedCondition, $zEdge), true);

scenario('Z4', 'One pound under the threshold on Basic — all four scopes go quiet together');
$zUnder = rows(array('plans' => 'Basic', 'budget' => '49999', 'urgency' => 'ur_low'));
test('Z4 field scope',        in_array('z_field', $validator->evaluate($zForm, $zUnder)['matched_rules'], true), false);
test('Z4 notification scope', notifyMatches($obj, $notify, $sharedCondition, $zUnder, $status_email), false);
test('Z4 confirmation scope', confirmMatches($obj, $confirm, $sharedCondition, $zUnder), false);
test('Z4 webhook scope',      hookMatches($obj, $hooks, $sharedCondition, $zUnder), false);

// ── Report ───────────────────────────────────────────────────────────────────
if ($failures) {
    echo "FAILURES\n\n";
    foreach ($failures as $f) echo '  ' . $f . "\n\n";
}
/* `--list` prints the scenario inventory without the assertion noise, so the
   suite can describe its own coverage rather than a document claiming it. */
if (in_array('--list', (array) $argv, true)) {
    foreach ($scenarioLog as $i => $entry) {
        printf("%3d. %-4s %s\n", $i + 1, $entry[0], $entry[1]);
    }
}
echo "scenarios exercised: " . count($scenarioLog) . "\n";
echo "========================================\n";
echo "RESULTS: $pass passed, $fail failed  (all four rule scopes)\n";
echo "========================================\n";
exit($fail > 0 ? 1 : 0);
