<?php
/**
 * Standalone PHP test for conditional webhook rules.
 * Run: C:\xampp\php\php.exe tests\test-conditional-logic-webhook.php
 */

define('ABSPATH', __DIR__ . '/');

$GLOBALS['efb_webhook_posts'] = array();
$GLOBALS['efb_webhook_gets'] = array();
$GLOBALS['efb_actions'] = array();

function add_action() {}
function add_shortcode() {}
function register_rest_route() {}
function current_user_can() { return true; }
function get_current_user_id() { return 1; }
function get_efbFunction() { return null; }
function get_setting_Emsfb() { return array(); }
function wp_create_nonce($value = '') { return 'nonce_' . $value; }
function __return_true() { return true; }
function do_action($hook, ...$args) { $GLOBALS['efb_actions'][] = array('hook' => $hook, 'args' => $args); }
function is_admin() { return false; }
function sanitize_text_field($value) { return is_array($value) ? '' : trim(strip_tags((string)$value)); }
function sanitize_email($value) { return filter_var((string)$value, FILTER_SANITIZE_EMAIL); }
function is_email($value) { return filter_var((string)$value, FILTER_VALIDATE_EMAIL) !== false; }
function esc_url($value) { return filter_var((string)$value, FILTER_SANITIZE_URL); }
function esc_url_raw($value) { return filter_var((string)$value, FILTER_SANITIZE_URL); }
function sanitize_url($value) { return esc_url_raw($value); }
function wp_kses_post($value) { return strip_tags((string)$value, '<b><strong><em><i><br><p><a>'); }
function wp_unslash($value) { return $value; }
function get_site_url() { return 'https://site.test'; }
function wp_json_encode($value) { return json_encode($value); }
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

require __DIR__ . '/../includes/class-Emsfb-public.php';

$pass = 0;
$fail = 0;
function test($label, $actual, $expected) {
    global $pass, $fail;
    if ($actual === $expected) {
        $pass++;
        echo "[PASS] $label\n";
        return;
    }
    $fail++;
    echo "[FAIL] $label\n";
    echo "  Expected: " . json_encode($expected) . "\n";
    echo "  Actual:   " . json_encode($actual) . "\n";
}
function test_true($label, $value) { test($label, (bool)$value, true); }

$ref = new ReflectionClass('Emsfb\\_Public');
$public = $ref->newInstanceWithoutConstructor();
$id_prop = $ref->getProperty('id');
$id_prop->setAccessible(true);
$id_prop->setValue($public, 77);

$method = $ref->getMethod('process_conditional_webhook_rules');
$method->setAccessible(true);

$form = array(
    array(
        'type' => 'form',
        'webhook_rules' => array(
            array(
                'id' => 'wr_hot',
                'name' => 'Hot lead',
                'enabled' => true,
                'priority' => 5,
                'webhook_id' => 'crm_hot_lead',
                'url' => 'https://crm.example.test/hook',
                'method' => 'POST',
                'conditions' => array(
                    'type' => 'group',
                    'operator' => 'AND',
                    'items' => array(
                        array('type' => 'condition', 'field_id' => 'lead_score', 'compare' => 'gt', 'value' => '70'),
                    ),
                ),
            ),
            array(
                'id' => 'wr_low',
                'name' => 'Low lead',
                'enabled' => true,
                'priority' => 10,
                'webhook_id' => 'crm_low_lead',
                'url' => 'https://crm.example.test/low',
                'method' => 'POST',
                'conditions' => array(
                    'type' => 'group',
                    'operator' => 'AND',
                    'items' => array(
                        array('type' => 'condition', 'field_id' => 'lead_score', 'compare' => 'lt', 'value' => '30'),
                    ),
                ),
            ),
        ),
    ),
    array('id_' => 'lead_score', 'type' => 'number', 'name' => 'Lead score'),
);

$submitted = array(
    array('id_' => 'lead_score', 'type' => 'number', 'value' => '80'),
);
$sent = $method->invoke($public, $form, $submitted, 'TRK123', 'form_submit', array('page_url' => 'https://site.test/form'));

test('T1.1 only one matching webhook is sent', count($GLOBALS['efb_webhook_posts']), 1);
test('T1.2 matching webhook URL is used', $GLOBALS['efb_webhook_posts'][0]['url'], 'https://crm.example.test/hook');
test('T1.3 sent metadata records matching rule', $sent[0]['rule_id'], 'wr_hot');
$payload = json_decode($GLOBALS['efb_webhook_posts'][0]['args']['body'], true);
test('T1.4 payload includes track code', $payload['track_code'], 'TRK123');
test('T1.5 payload includes values map', $payload['values']['lead_score'], '80');
test('T1.6 payload includes webhook id header', $GLOBALS['efb_webhook_posts'][0]['args']['headers']['X-EFB-Webhook-Id'], 'crm_hot_lead');
test_true('T1.7 before/after hooks fired', count($GLOBALS['efb_actions']) === 2);

$GLOBALS['efb_webhook_posts'] = array();
$GLOBALS['efb_webhook_gets'] = array();
$GLOBALS['efb_actions'] = array();
$form_get = array(
    array(
        'type' => 'form',
        'webhook_rules' => array(
            array(
                'id' => 'wr_get',
                'name' => 'GET webhook',
                'enabled' => true,
                'priority' => 1,
                'webhook_id' => 'crm_get',
                'url' => 'https://crm.example.test/get-hook',
                'method' => 'GET',
                'conditions' => array(
                    'type' => 'group',
                    'operator' => 'AND',
                    'items' => array(
                        array('type' => 'condition', 'field_id' => 'lead_score', 'compare' => 'gt', 'value' => '70'),
                    ),
                ),
            ),
        ),
    ),
    array('id_' => 'lead_score', 'type' => 'number', 'name' => 'Lead score'),
);
$sent_get = $method->invoke($public, $form_get, $submitted, 'TRK126', 'form_submit', array('page_url' => 'https://site.test/form'));
test('T2.1 GET webhook does not use POST transport', count($GLOBALS['efb_webhook_posts']), 0);
test('T2.2 GET webhook is sent once', count($GLOBALS['efb_webhook_gets']), 1);
test_true('T2.3 GET webhook URL includes track_code and event_type', strpos($GLOBALS['efb_webhook_gets'][0]['url'], 'track_code=TRK126') !== false && strpos($GLOBALS['efb_webhook_gets'][0]['url'], 'event_type=form_submit') !== false);
test('T2.4 GET webhook metadata records GET method', $sent_get[0]['method'], 'GET');

$GLOBALS['efb_webhook_posts'] = array();
$GLOBALS['efb_webhook_gets'] = array();
$GLOBALS['efb_actions'] = array();
$submitted_low_miss = array(
    array('id_' => 'lead_score', 'type' => 'number', 'value' => '50'),
);
$sent_none = $method->invoke($public, $form, $submitted_low_miss, 'TRK124', 'form_submit', array('page_url' => 'https://site.test/form'));
test('T3.1 non-matching value sends no webhook', count($GLOBALS['efb_webhook_posts']), 0);
test('T3.2 non-matching result is empty', count($sent_none), 0);

$GLOBALS['efb_webhook_posts'] = array();
$GLOBALS['efb_webhook_gets'] = array();
$sent_no_rules = $method->invoke($public, array(array('type' => 'form')), $submitted, 'TRK125', 'form_submit', array());
test('T4.1 form with no webhook_rules keeps old behavior and sends nothing', count($GLOBALS['efb_webhook_posts']), 0);
test('T4.2 form with no webhook_rules returns empty result', count($sent_no_rules), 0);

// ─────────────────────────────────────────────────────────────────────────────
// T5: stop rules (PRD C6 "Stop webhook") + payload_fields whitelist
// ─────────────────────────────────────────────────────────────────────────────
function make_stop_form($stop_webhook_id, $stop_condition_value) {
    return array(
        array(
            'type' => 'form',
            'webhook_rules' => array(
                array(
                    'id' => 'wr_stop',
                    'enabled' => true,
                    'priority' => 1,
                    'action' => 'stop',
                    'webhook_id' => $stop_webhook_id,
                    'url' => '',
                    'conditions' => array('type' => 'group', 'operator' => 'AND', 'items' => array(
                        array('type' => 'condition', 'field_id' => 'vip', 'compare' => 'is', 'value' => $stop_condition_value),
                    )),
                ),
                array(
                    'id' => 'wr_a',
                    'enabled' => true,
                    'priority' => 5,
                    'action' => 'trigger',
                    'webhook_id' => 'crm_hot_lead',
                    'url' => 'https://crm.example.test/hook-a',
                    'method' => 'POST',
                    'conditions' => array('type' => 'group', 'operator' => 'AND', 'items' => array(
                        array('type' => 'condition', 'field_id' => 'lead_score', 'compare' => 'gt', 'value' => '70'),
                    )),
                ),
                array(
                    'id' => 'wr_b',
                    'enabled' => true,
                    'priority' => 6,
                    'action' => 'trigger',
                    'webhook_id' => 'other_hook',
                    'url' => 'https://crm.example.test/hook-b',
                    'method' => 'POST',
                    'conditions' => array('type' => 'group', 'operator' => 'AND', 'items' => array(
                        array('type' => 'condition', 'field_id' => 'lead_score', 'compare' => 'gt', 'value' => '70'),
                    )),
                ),
            ),
        ),
        array('id_' => 'lead_score', 'type' => 'number', 'name' => 'Lead score'),
        array('id_' => 'vip', 'type' => 'text', 'name' => 'VIP'),
        array('id_' => 'secret_note', 'type' => 'text', 'name' => 'Secret note'),
    );
}
$submitted_stop = array(
    array('id_' => 'lead_score', 'type' => 'number', 'value' => '90'),
    array('id_' => 'vip', 'type' => 'text', 'value' => 'yes'),
    array('id_' => 'secret_note', 'type' => 'text', 'value' => 'internal'),
);

// matched stop rule with a webhook_id cancels ONLY that trigger rule
$GLOBALS['efb_webhook_posts'] = array();
$sent_stop_one = $method->invoke($public, make_stop_form('crm_hot_lead', 'yes'), $submitted_stop, 'TRK200', 'form_submit', array());
test('T5.1 stop with webhook_id cancels the matching trigger only', count($sent_stop_one), 1);
test('T5.2 the surviving webhook is the other id', $sent_stop_one[0]['webhook_id'], 'other_hook');

// matched stop rule WITHOUT webhook_id cancels all trigger rules
$GLOBALS['efb_webhook_posts'] = array();
$sent_stop_all = $method->invoke($public, make_stop_form('', 'yes'), $submitted_stop, 'TRK201', 'form_submit', array());
test('T5.3 stop without webhook_id cancels every webhook', count($sent_stop_all), 0);
test('T5.4 nothing was posted', count($GLOBALS['efb_webhook_posts']), 0);

// unmatched stop rule cancels nothing
$GLOBALS['efb_webhook_posts'] = array();
$sent_stop_none = $method->invoke($public, make_stop_form('', 'no-match'), $submitted_stop, 'TRK202', 'form_submit', array());
test('T5.5 unmatched stop rule fires both webhooks', count($sent_stop_none), 2);

// payload_fields whitelist trims values + submitted rows
$form_payload = make_stop_form('', 'no-match');
$form_payload[0]['webhook_rules'][1]['payload_fields'] = array('lead_score');
$GLOBALS['efb_webhook_posts'] = array();
$method->invoke($public, $form_payload, $submitted_stop, 'TRK203', 'form_submit', array());
$payload_a = json_decode($GLOBALS['efb_webhook_posts'][0]['args']['body'], true);
test_true('T5.6 payload values whitelist keeps only lead_score',
    array_keys($payload_a['values']) === array('lead_score'));
test('T5.7 payload submitted_values filtered to whitelist', count($payload_a['submitted_values']), 1);
$payload_b = json_decode($GLOBALS['efb_webhook_posts'][1]['args']['body'], true);
test_true('T5.8 rule without payload_fields still gets full payload',
    isset($payload_b['values']['secret_note']));

echo "\n========================================\n";
echo "RESULTS: $pass passed, $fail failed\n";
echo "========================================\n";
exit($fail > 0 ? 1 : 0);
