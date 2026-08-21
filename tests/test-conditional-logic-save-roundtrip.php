<?php
/**
 * Save round-trip: a rule is not what the author wrote, it is what survives
 * sanitize_obj_msg_efb() on the way into the database. A rule that is silently
 * trimmed there behaves differently from the one that was designed, and nothing
 * reports it — so every scope is re-evaluated AFTER a real save pass and must
 * reach the same verdict.
 */
define('ABSPATH', __DIR__ . '/');
define('EMSFB_PLUGIN_DIRECTORY', 'c:/xampp/htdocs/wp/wp-content/plugins/easy-form-builder');
define('EMSFB_PLUGIN_VERSION', '4.1.3');
define('EMSFB_PLUGIN_URL', 'http://127.0.0.1/wp/wp-content/plugins/easy-form-builder/');

$EFB = 'c:/xampp/htdocs/wp/wp-content/plugins/easy-form-builder';

require $EFB . '/tests/../vendor/logic/logic/class-Emsfb-logic-validator.php';

/* functions.php expects a live WordPress; stub only what the sanitiser path
   touches so the real sanitiser code runs unmodified. */
function sanitize_text_field($v) { return is_array($v) ? '' : trim(strip_tags((string) $v)); }
function sanitize_email($v) { return filter_var((string) $v, FILTER_SANITIZE_EMAIL); }
function is_email($v) { return filter_var((string) $v, FILTER_VALIDATE_EMAIL) !== false; }
function esc_url_raw($v) { return filter_var((string) $v, FILTER_SANITIZE_URL); }
function wp_kses_post($v) { return strip_tags((string) $v, '<b><strong><em><i><br><p><a>'); }
function esc_html__($s, $d = null) { return $s; }
function esc_html($s) { return htmlspecialchars((string) $s, ENT_QUOTES); }
function __($s, $d = null) { return $s; }
function apply_filters($h, $v) { return $v; }
function add_filter() { return true; }
function do_action() {}
function wp_cache_get() { return false; }
function wp_cache_set() { return true; }
function wp_cache_delete() { return true; }
function get_option($k, $d = false) { return $d; }
function update_option() { return true; }
function absint($v) { return abs(intval($v)); }
function wp_json_encode($v) { return json_encode($v); }

require $EFB . '/includes/functions.php';

$pass = 0; $fail = 0; $failures = array();
function t($label, $actual, $expected) {
    global $pass, $fail, $failures;
    if ($actual === $expected) { $pass++; return; }
    $fail++;
    $failures[] = "$label\n      expected " . json_encode($expected) . "\n      actual   " . json_encode($actual);
}

/* The constructor registers WordPress hooks; the sanitiser itself needs none of
   that, so build the object without running it. */
$fn = (new ReflectionClass('efbFunction'))->newInstanceWithoutConstructor();

/* A rule set that uses every shape a real form uses: four sources, nesting three
   deep, a negated group, per-item connectors, and choice operands spelled as
   option ids. */
function nested_conditions() {
    return array('type' => 'group', 'operator' => 'AND', 'items' => array(
        array('type' => 'condition', 'source' => 'field', 'field_id' => 'company_type', 'compare' => 'is', 'value' => 'ct_company'),
        array('type' => 'group', 'operator' => 'OR', 'connector' => 'AND', 'items' => array(
            array('type' => 'condition', 'source' => 'field', 'field_id' => 'plans', 'compare' => 'is', 'value' => 'pl_ent'),
            array('type' => 'group', 'operator' => 'AND', 'connector' => 'OR', 'items' => array(
                array('type' => 'condition', 'source' => 'field', 'field_id' => 'services', 'compare' => 'is', 'value' => 'sv_dev'),
                array('type' => 'condition', 'source' => 'field', 'field_id' => 'employees', 'compare' => 'gte', 'value' => '100', 'connector' => 'AND'),
            )),
        )),
        array('type' => 'group', 'operator' => 'AND', 'connector' => 'AND', 'negate' => true, 'items' => array(
            array('type' => 'condition', 'source' => 'field', 'field_id' => 'country', 'compare' => 'is', 'value' => 'co_ir'),
        )),
        array('type' => 'condition', 'source' => 'query_param', 'param' => 'utm_source', 'field_id' => 'utm_source', 'compare' => 'is_not_empty', 'value' => ''),
        array('type' => 'condition', 'source' => 'user', 'field_id' => 'logged_in', 'compare' => 'is', 'value' => 'no', 'connector' => 'AND'),
        array('type' => 'condition', 'source' => 'current_step', 'field_id' => 'current_step', 'compare' => 'gte', 'value' => '1', 'connector' => 'AND'),
    ));
}

function fld($id, $type, $step, $extra = array()) {
    return array_merge(array('id_' => $id, 'type' => $type, 'step' => (string) $step,
        'name' => $id, 'required' => '0', 'value' => ''), $extra);
}
function opt($id, $parent, $value) {
    return array('id_' => $id, 'type' => 'option', 'parent' => $parent, 'value' => $value, 'step' => '1');
}

$form = array(
    array(
        'type' => 'form', 'steps' => '3', 'logic' => '1',
        'logic_rules' => array(array(
            'id' => 'fr', 'name' => 'Field rule', 'scope' => 'field', 'enabled' => true,
            'priority' => 12, 'stop_processing' => true,
            'conditions' => nested_conditions(),
            'actions' => array(
                array('type' => 'show_step', 'target' => '5'),
                array('type' => 'hide_step', 'target' => '2'),
                array('type' => 'set_required', 'target' => 'notes'),
                array('type' => 'set_label', 'target' => 'notes', 'value' => 'Tell us more'),
                array('type' => 'copy_value', 'target' => 'referral', 'value' => 'company_name'),
                array('type' => 'block_submit', 'target' => 'should_be_cleared', 'value' => 'No'),
            ),
        )),
        'notification_rules' => array(array(
            'id' => 'nr', 'name' => 'Notify', 'enabled' => true, 'priority' => 7,
            'recipient' => 'desk@example.com', 'cc' => 'a@example.com, bad-address, b@example.com',
            'bcc' => array('c@example.com'), 'subject' => 'Lead {company_name}',
            'conditions' => nested_conditions(),
        )),
        'confirmation_rules' => array(array(
            'id' => 'cr', 'name' => 'Confirm', 'enabled' => true, 'priority' => 8,
            'action' => 'message', 'message' => 'Thanks <b>very</b> much',
            'done' => 'Done', 'icon' => 'bi-check-circle', 'icon_color' => '#11AA33',
            'title_color' => 'not-a-color', 'tracking_label' => 'Ref',
            'conditions' => nested_conditions(),
        )),
        'webhook_rules' => array(array(
            'id' => 'wr', 'name' => 'Hook', 'enabled' => true, 'priority' => 9,
            'action' => 'trigger', 'url' => 'https://hooks.example.com/x', 'method' => 'put',
            'payload_fields' => 'company_name, budget, not_a_field',
            'conditions' => nested_conditions(),
        )),
    ),
    array('id_' => '7', 'type' => 'step', 'step' => '1', 'name' => 'About'),
    array('id_' => '2', 'type' => 'step', 'step' => '2', 'name' => 'Project'),
    array('id_' => '5', 'type' => 'step', 'step' => '3', 'name' => 'Budget'),
    fld('company_type', 'select', 1), opt('ct_individual', 'company_type', 'Individual'), opt('ct_company', 'company_type', 'Company'),
    fld('country', 'select', 1), opt('co_de', 'country', 'Germany'), opt('co_ir', 'country', 'Iran'),
    fld('services', 'checkbox', 2), opt('sv_dev', 'services', 'Development'), opt('sv_seo', 'services', 'SEO'),
    fld('plans', 'multiselect', 2), opt('pl_basic', 'plans', 'Basic'), opt('pl_ent', 'plans', 'Enterprise'),
    fld('employees', 'number', 3), fld('notes', 'textarea', 3),
    fld('referral', 'text', 3), fld('company_name', 'text', 1), fld('budget', 'number', 3),
);

$saved = $fn->sanitize_obj_msg_efb($form);

// ── what must survive the save ───────────────────────────────────────────────
$fr = $saved[0]['logic_rules'][0];
t('R1 field rule survives the save', isset($fr['id']) ? $fr['id'] : null, 'fr');
t('R2 priority preserved', $fr['priority'], 12);
t('R3 stop_processing preserved', $fr['stop_processing'], true);
t('R4 all valid actions preserved, the targetless one kept', count($fr['actions']), 6);
t('R5 block_submit target cleared by the sanitiser', $fr['actions'][5]['target'], '');
t('R6 step targets kept as step ids', $fr['actions'][0]['target'] . '/' . $fr['actions'][1]['target'], '5/2');
t('R7 copy_value source preserved', $fr['actions'][4]['value'], 'company_name');

$c = $fr['conditions'];
t('R8 root group operator preserved', $c['operator'], 'AND');
t('R9 root keeps every item', count($c['items']), 6);
t('R10 nested group survives at depth 2', $c['items'][1]['type'], 'group');
t('R11 nested group survives at depth 3', $c['items'][1]['items'][1]['type'], 'group');
t('R12 depth-3 condition intact', $c['items'][1]['items'][1]['items'][0]['field_id'], 'services');
t('R13 negate flag preserved on the nested group', !empty($c['items'][2]['negate']), true);
t('R14 per-item connector preserved', $c['items'][1]['connector'], 'AND');
t('R15 query_param source preserved', $c['items'][3]['source'], 'query_param');
t('R16 query_param param key preserved', $c['items'][3]['param'], 'utm_source');
t('R17 user source preserved', $c['items'][4]['source'], 'user');
t('R18 current_step source preserved', $c['items'][5]['source'], 'current_step');
t('R19 choice operand kept as the option id', $c['items'][0]['value'], 'ct_company');

$nr = $saved[0]['notification_rules'][0];
t('R20 notification rule survives', $nr['recipient'], 'desk@example.com');
t('R21 cc list parsed and the invalid address dropped', $nr['cc'], array('a@example.com', 'b@example.com'));
t('R22 bcc array preserved', $nr['bcc'], array('c@example.com'));
t('R23 subject token preserved', $nr['subject'], 'Lead {company_name}');
t('R24 notification conditions keep their depth', count($nr['conditions']['items']), 6);
t('R25 notification nested group survives', $nr['conditions']['items'][1]['items'][1]['type'], 'group');

$cr = $saved[0]['confirmation_rules'][0];
t('R26 confirmation action preserved', $cr['action'], 'message');
t('R27 icon preserved (matches bi-*)', $cr['icon'], 'bi-check-circle');
t('R28 hex colour normalised to lowercase', $cr['icon_color'], '#11aa33');
t('R29 invalid colour dropped', $cr['title_color'], '');
t('R30 message keeps its allowed markup', $cr['message'], 'Thanks <b>very</b> much');

$wr = $saved[0]['webhook_rules'][0];
t('R31 webhook url preserved', $wr['url'], 'https://hooks.example.com/x');
t('R32 unsupported method falls back to POST', $wr['method'], 'POST');
t('R33 payload allow-list keeps only real fields', $wr['payload_fields'], array('company_name', 'budget'));
t('R34 webhook conditions keep their depth', count($wr['conditions']['items']), 6);

// ── and the saved rule must still evaluate the same way ──────────────────────
$rows = array(
    array('id_' => 'company_type', 'type' => 'select', 'value' => 'Company'),
    array('id_' => 'country', 'type' => 'select', 'value' => 'Germany'),
    array('id_' => 'services', 'type' => 'checkbox', 'id_ob' => 'sv_dev', 'value' => 'sv_dev'),
    array('id_' => 'plans', 'type' => 'multiselect', 'value' => 'Basic'),
    array('id_' => 'employees', 'type' => 'number', 'value' => '150'),
    array('id_' => 'company_name', 'type' => 'text', 'value' => 'Acme'),
);
$env = array('query' => array('utm_source' => 'linkedin'),
    'user' => array('logged_in' => false, 'roles' => array()), 'current_step' => 3);

function matches($form, $rows, $env) {
    $v = new \Emsfb\Emsfb_Logic_Validator();
    $v->set_environment($env);
    $r = $v->evaluate($form, $rows);
    return in_array('fr', $r['matched_rules'], true);
}
t('R35 the rule matches BEFORE the save pass', matches($form, $rows, $env), true);
t('R36 and still matches AFTER it', matches($saved, $rows, $env), true);

/* Flip one answer that the nested OR depends on: with 99 staff and no
   enterprise plan the inner AND fails, so the whole rule must stop matching —
   before and after the save alike. */
$rowsNarrow = $rows;
$rowsNarrow[4] = array('id_' => 'employees', 'type' => 'number', 'value' => '99');
t('R37 the nested arm still gates the rule before the save', matches($form, $rowsNarrow, $env), false);
t('R38 and after it', matches($saved, $rowsNarrow, $env), false);

/* The negated group must still veto a sanctioned country after the save. */
$rowsIran = $rows;
$rowsIran[1] = array('id_' => 'country', 'type' => 'select', 'value' => 'Iran');
t('R39 negated group vetoes before the save', matches($form, $rowsIran, $env), false);
t('R40 and after it', matches($saved, $rowsIran, $env), false);

if ($failures) {
    echo "FAILURES\n\n";
    foreach ($failures as $f) echo '  ' . $f . "\n\n";
}
echo "========================================\n";
echo "RESULTS: $pass passed, $fail failed  (save round-trip)\n";
echo "========================================\n";
exit($fail > 0 ? 1 : 0);
