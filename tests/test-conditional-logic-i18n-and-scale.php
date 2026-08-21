<?php
/**
 * Conditional logic on a Persian / Arabic form, and what it costs at scale.
 *
 * The plugin ships fa/ar translations and a large share of its forms are RTL, so
 * a rule has to survive the save sanitiser and the evaluator with its ZWNJ,
 * em dashes and Arabic script intact — and a Persian ANSWER has to match a rule
 * that stores the option id rather than the label, which is the whole point of
 * spelling choice operands as ids.
 *
 * The scale section prints timings rather than asserting them: the numbers move
 * with the machine, but the shape is the useful part. Evaluation grows a little
 * faster than linearly because the structure index is rebuilt per condition;
 * at realistic rule counts (under ~20) it costs well under a millisecond.
 *
 * Run: C:
mpp\php\php.exe tests/test-conditional-logic-i18n-and-scale.php
 */
define('ABSPATH', __DIR__ . '/');
define('EMSFB_PLUGIN_DIRECTORY', 'c:/xampp/htdocs/wp/wp-content/plugins/easy-form-builder');
define('EMSFB_PLUGIN_VERSION', '4.1.3');
define('EMSFB_PLUGIN_URL', 'http://127.0.0.1/wp/wp-content/plugins/easy-form-builder/');

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
function wp_json_encode($v) { return json_encode($v, JSON_UNESCAPED_UNICODE); }

require 'c:/xampp/htdocs/wp/wp-content/plugins/easy-form-builder/vendor/logic/logic/class-Emsfb-logic-validator.php';
require 'c:/xampp/htdocs/wp/wp-content/plugins/easy-form-builder/includes/functions.php';

$pass = 0; $fail = 0;
function t($label, $actual, $expected) {
    global $pass, $fail;
    if ($actual === $expected) { $pass++; echo "[PASS] $label\n"; return; }
    $fail++;
    echo "[FAIL] $label\n      expected " . json_encode($expected, JSON_UNESCAPED_UNICODE)
       . "\n      actual   " . json_encode($actual, JSON_UNESCAPED_UNICODE) . "\n";
}

// ── 1. Persian / RTL ─────────────────────────────────────────────────────────
echo "=== Persian answers, options, messages and labels ===\n";

$PERSIAN_OPTION = 'پشتیبانی ویژه';
$PERSIAN_MSG    = 'لطفاً کد فعال‌سازی خود را وارد کنید — این فیلد اجباری است.';
$PERSIAN_LABEL  = 'کد فعال‌سازی';
$ARABIC_ANSWER  = 'الدعم المتميز';

$form = array(
    array('type' => 'form', 'steps' => '1', 'logic' => '1', 'logic_rules' => array(array(
        'id' => 'fa_rule', 'name' => 'قانون فارسی', 'scope' => 'field', 'enabled' => true,
        'priority' => 10, 'stop_processing' => false,
        'conditions' => array('type' => 'group', 'operator' => 'AND', 'items' => array(
            array('type' => 'condition', 'source' => 'field', 'field_id' => 'topic', 'compare' => 'is', 'value' => 'op_pro'),
        )),
        'actions' => array(
            array('type' => 'show_field',   'target' => 'code'),
            array('type' => 'set_required', 'target' => 'code'),
            array('type' => 'set_label',    'target' => 'code', 'value' => $PERSIAN_LABEL),
            array('type' => 'show_message', 'target' => 'code', 'value' => $PERSIAN_MSG),
        ),
    ))),
    array('id_' => '1', 'type' => 'step', 'step' => '1', 'name' => 'مرحله یک'),
    array('id_' => 'topic', 'type' => 'select', 'step' => '1', 'name' => 'موضوع', 'required' => '1', 'value' => ''),
    array('id_' => 'op_gen', 'type' => 'option', 'parent' => 'topic', 'value' => 'سوال عمومی'),
    array('id_' => 'op_pro', 'type' => 'option', 'parent' => 'topic', 'value' => $PERSIAN_OPTION),
    array('id_' => 'op_ar',  'type' => 'option', 'parent' => 'topic', 'value' => $ARABIC_ANSWER),
    array('id_' => 'code', 'type' => 'text', 'step' => '1', 'name' => 'کد', 'required' => '0', 'value' => ''),
);

$fn = (new ReflectionClass('efbFunction'))->newInstanceWithoutConstructor();
$saved = $fn->sanitize_obj_msg_efb($form);
$savedRule = $saved[0]['logic_rules'][0];

t('Persian rule name survives the save', $savedRule['name'], 'قانون فارسی');
t('Persian label action survives', $savedRule['actions'][2]['value'], $PERSIAN_LABEL);
t('Persian message survives, ZWNJ and em dash intact', $savedRule['actions'][3]['value'], $PERSIAN_MSG);
t('the option operand is still the option id', $savedRule['conditions']['items'][0]['value'], 'op_pro');

$v = new \Emsfb\Emsfb_Logic_Validator();
$v->set_environment(array('query' => array(), 'user' => array('logged_in' => false, 'roles' => array()), 'current_step' => 1));

/* A row carries the visible Persian text; the rule carries the option id. */
$rowsPro = array(array('id_' => 'topic', 'type' => 'select', 'value' => $PERSIAN_OPTION));
$r = $v->evaluate($saved, $rowsPro);
t('a Persian answer matches its option id', in_array('fa_rule', $r['matched_rules'], true), true);
t('and the Persian field becomes required', in_array('code', $r['required_fields'], true), true);
$msg = isset($r['messages'][0]['value']) ? $r['messages'][0]['value'] : '';
t('the Persian message reaches the result unmangled', $msg, $PERSIAN_MSG);
$ui = isset($r['ui_changes'][0]['value']) ? $r['ui_changes'][0]['value'] : '';
t('the Persian label reaches the result unmangled', $ui, $PERSIAN_LABEL);

$rowsGen = array(array('id_' => 'topic', 'type' => 'select', 'value' => 'سوال عمومی'));
t('a different Persian answer does not match', in_array('fa_rule', $v->evaluate($saved, $rowsGen)['matched_rules'], true), false);

$rowsAr = array(array('id_' => 'topic', 'type' => 'select', 'value' => $ARABIC_ANSWER));
t('an Arabic answer does not match the Persian option', in_array('fa_rule', $v->evaluate($saved, $rowsAr)['matched_rules'], true), false);

/* Arabic option, matched by its own id */
$form[0]['logic_rules'][0]['conditions']['items'][0]['value'] = 'op_ar';
$savedAr = $fn->sanitize_obj_msg_efb($form);
t('an Arabic option matches by id', in_array('fa_rule', $v->evaluate($savedAr, $rowsAr)['matched_rules'], true), true);

/* Persian text compared with contains */
$form[0]['logic_rules'][0]['conditions']['items'][0] = array('type' => 'condition', 'source' => 'field',
    'field_id' => 'code', 'compare' => 'contains', 'value' => 'فعال');
$savedC = $fn->sanitize_obj_msg_efb($form);
$rowsC = array(array('id_' => 'code', 'type' => 'text', 'value' => 'کد فعال‌سازی من'));
t('contains works on Persian text', in_array('fa_rule', $v->evaluate($savedC, $rowsC)['matched_rules'], true), true);
$rowsC2 = array(array('id_' => 'code', 'type' => 'text', 'value' => 'چیز دیگری'));
t('contains does not over-match Persian text', in_array('fa_rule', $v->evaluate($savedC, $rowsC2)['matched_rules'], true), false);

// ── 2. Scale ─────────────────────────────────────────────────────────────────
echo "\n=== evaluation cost as rules accumulate ===\n";
function bigForm($ruleCount) {
    $form = array(array('type' => 'form', 'steps' => '1', 'logic' => '1', 'logic_rules' => array()));
    $form[] = array('id_' => '1', 'type' => 'step', 'step' => '1', 'name' => 'One');
    $form[] = array('id_' => 'driver', 'type' => 'text', 'step' => '1', 'name' => 'Driver', 'required' => '0', 'value' => '');
    for ($i = 0; $i < $ruleCount; $i++) {
        $form[] = array('id_' => 'f' . $i, 'type' => 'text', 'step' => '1', 'name' => 'F' . $i, 'required' => '0', 'value' => '');
        $form[0]['logic_rules'][] = array(
            'id' => 'r' . $i, 'name' => 'r' . $i, 'scope' => 'field', 'enabled' => true,
            'priority' => 10 + $i, 'stop_processing' => false,
            'conditions' => array('type' => 'group', 'operator' => 'AND', 'items' => array(
                array('type' => 'condition', 'source' => 'field', 'field_id' => 'driver', 'compare' => 'contains', 'value' => 'go'),
                array('type' => 'group', 'operator' => 'OR', 'connector' => 'AND', 'items' => array(
                    array('type' => 'condition', 'source' => 'field', 'field_id' => 'driver', 'compare' => 'is_not_empty', 'value' => ''),
                    array('type' => 'condition', 'source' => 'field', 'field_id' => 'f' . $i, 'compare' => 'is_empty', 'value' => ''),
                )),
            )),
            'actions' => array(array('type' => 'set_required', 'target' => 'f' . $i)),
        );
    }
    return $form;
}
$rows = array(array('id_' => 'driver', 'type' => 'text', 'value' => 'go now'));
foreach (array(10, 50, 100, 250) as $n) {
    $f = bigForm($n);
    $start = microtime(true);
    $res = $v->evaluate($f, $rows);
    $ms = (microtime(true) - $start) * 1000;
    printf("  %4d rules → %7.1f ms   matched=%d required=%d\n", $n, $ms, count($res['matched_rules']), count($res['required_fields']));
}

echo "\n========================================\n";
echo "RESULTS: $pass passed, $fail failed  (Persian/RTL text + scale)\n";
echo "========================================\n";
exit($fail > 0 ? 1 : 0);
