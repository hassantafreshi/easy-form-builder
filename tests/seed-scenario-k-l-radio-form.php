<?php
/**
 * Seed a combined E2E form for Conditional Logic Scenarios K and L.
 *
 * Includes:
 * - Scenario K: nested condition groups with mixed AND/OR connector.
 * - Scenario L: numeric operators gte, lte, between, not_between.
 * - Radio-driven numeric rules to verify radio option matching.
 *
 * Run from the plugin root:
 *   C:\xampp\php\php.exe tests/seed-scenario-k-l-radio-form.php
 */

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "This script is CLI-only.\n");
    exit(1);
}

$wp_load = dirname(__DIR__, 4) . DIRECTORY_SEPARATOR . 'wp-load.php';
if (!file_exists($wp_load)) {
    fwrite(STDERR, "Could not find wp-load.php at: {$wp_load}\n");
    exit(1);
}

require_once $wp_load;

global $wpdb;

$form_name = 'EFB Scenarios K L Radio';
$page_slug = 'efb-scenarios-k-l-radio';
$table = $wpdb->prefix . 'emsfb_form';

function efb_klr_field($id, $type, $name, $amount, $extra = array()) {
    return array_merge(array(
        'id_' => $id,
        'dataId' => $id . '-id',
        'type' => $type,
        'placeholder' => '',
        'value' => '',
        'size' => 100,
        'message' => '',
        'id' => '',
        'classes' => '',
        'name' => $name,
        'required' => false,
        'amount' => $amount,
        'step' => '1',
        'label_text_size' => 'fs-6',
        'label_position' => 'up',
        'el_text_size' => 'fs-6',
        'label_text_color' => 'text-labelEfb',
        'el_border_color' => 'border-d',
        'el_text_color' => 'text-labelEfb',
        'message_text_color' => 'text-muted',
        'el_height' => 'h-d-efb',
        'label_align' => 'txt-left',
        'message_align' => 'justify-content-start',
        'el_align' => 'justify-content-start',
        'pro' => false,
        'icon_input' => '',
    ), $extra);
}

function efb_klr_option($id, $parent, $value, $amount) {
    return array(
        'id_' => $id,
        'dataId' => $id . '-id',
        'parent' => $parent,
        'type' => 'option',
        'value' => $value,
        'id_op' => $id,
        'step' => '1',
        'amount' => $amount,
    );
}

function efb_klr_condition($field_id, $compare, $value = '') {
    return array(
        'type' => 'condition',
        'source' => 'field',
        'field_id' => $field_id,
        'compare' => $compare,
        'value' => $value,
    );
}

function efb_klr_group($operator, $items) {
    return array(
        'type' => 'group',
        'operator' => $operator,
        'items' => $items,
    );
}

function efb_klr_rule($id, $name, $priority, $conditions, $actions) {
    return array(
        'id' => $id,
        'name' => $name,
        'enabled' => true,
        'priority' => $priority,
        'stop_processing' => false,
        'conditions' => $conditions,
        'actions' => $actions,
    );
}

function efb_klr_action($type, $target, $value = null) {
    $action = array('type' => $type, 'target' => $target);
    if ($value !== null) {
        $action['value'] = $value;
    }
    return $action;
}

$range = '500,2000';
$r15_message = 'R15 matched: (Company AND budget >= 1000) OR logic_command = vip';

$logic_rules = array(
    efb_klr_rule(
        'r15_nested_and_or',
        'K - R15 Nested AND/OR',
        50,
        efb_klr_group('AND', array(
            efb_klr_group('AND', array(
                efb_klr_condition('customer_type', 'is', 'Company'),
                array_merge(efb_klr_condition('budget', 'gte', '1000'), array('connector' => 'AND')),
            )),
            array_merge(efb_klr_condition('logic_command', 'is', 'vip'), array('connector' => 'OR')),
        )),
        array(efb_klr_action('show_message', 'budget', $r15_message))
    ),

    efb_klr_rule('l_gte_show', 'L - show gte target', 100, efb_klr_group('AND', array(
        efb_klr_condition('budget', 'gte', '1000'),
    )), array(efb_klr_action('show_field', 'gte_target'))),
    efb_klr_rule('l_gte_hide', 'L - hide gte target', 101, efb_klr_group('OR', array(
        efb_klr_condition('budget', 'is_empty'),
        array_merge(efb_klr_condition('budget', 'lt', '1000'), array('connector' => 'OR')),
    )), array(efb_klr_action('hide_field', 'gte_target'))),

    efb_klr_rule('l_lte_show', 'L - show lte target', 110, efb_klr_group('AND', array(
        efb_klr_condition('budget', 'lte', '2000'),
    )), array(efb_klr_action('show_field', 'lte_target'))),
    efb_klr_rule('l_lte_hide', 'L - hide lte target', 111, efb_klr_group('OR', array(
        efb_klr_condition('budget', 'is_empty'),
        array_merge(efb_klr_condition('budget', 'gt', '2000'), array('connector' => 'OR')),
    )), array(efb_klr_action('hide_field', 'lte_target'))),

    efb_klr_rule('l_between_show', 'L - show between target', 120, efb_klr_group('AND', array(
        efb_klr_condition('budget', 'between', $range),
    )), array(efb_klr_action('show_field', 'conflict_target'))),
    efb_klr_rule('l_between_hide', 'L - hide between target', 121, efb_klr_group('AND', array(
        efb_klr_condition('budget', 'not_between', $range),
    )), array(efb_klr_action('hide_field', 'conflict_target'))),

    efb_klr_rule('l_not_between_show', 'L - show not_between target', 130, efb_klr_group('AND', array(
        efb_klr_condition('budget', 'is_not_empty'),
        array_merge(efb_klr_condition('budget', 'not_between', $range), array('connector' => 'AND')),
    )), array(efb_klr_action('show_field', 'not_between_target'))),
    efb_klr_rule('l_not_between_hide', 'L - hide not_between target', 131, efb_klr_group('OR', array(
        efb_klr_condition('budget', 'is_empty'),
        array_merge(efb_klr_condition('budget', 'between', $range), array('connector' => 'OR')),
    )), array(efb_klr_action('hide_field', 'not_between_target'))),

    efb_klr_rule('radio_between_show', 'Radio - strict between show', 140, efb_klr_group('AND', array(
        efb_klr_condition('operator_mode', 'is', 'operator_mode_between'),
        array_merge(efb_klr_condition('budget', 'between', $range), array('connector' => 'AND')),
    )), array(efb_klr_action('show_field', 'radio_between_target'))),
    efb_klr_rule('radio_between_hide', 'Radio - strict between hide', 141, efb_klr_group('OR', array(
        efb_klr_condition('operator_mode', 'is_empty'),
        array_merge(efb_klr_condition('operator_mode', 'is', 'operator_mode_outside'), array('connector' => 'OR')),
        array_merge(efb_klr_condition('budget', 'not_between', $range), array('connector' => 'OR')),
    )), array(efb_klr_action('hide_field', 'radio_between_target'))),

    efb_klr_rule('radio_outside_show', 'Radio - outside range show', 150, efb_klr_group('AND', array(
        efb_klr_condition('operator_mode', 'is', 'operator_mode_outside'),
        array_merge(efb_klr_condition('budget', 'is_not_empty'), array('connector' => 'AND')),
        array_merge(efb_klr_condition('budget', 'not_between', $range), array('connector' => 'AND')),
    )), array(efb_klr_action('show_field', 'radio_outside_target'))),
    efb_klr_rule('radio_outside_hide', 'Radio - outside range hide', 151, efb_klr_group('OR', array(
        efb_klr_condition('operator_mode', 'is_empty'),
        array_merge(efb_klr_condition('operator_mode', 'is', 'operator_mode_between'), array('connector' => 'OR')),
        array_merge(efb_klr_condition('budget', 'is_empty'), array('connector' => 'OR')),
        array_merge(efb_klr_condition('budget', 'between', $range), array('connector' => 'OR')),
    )), array(efb_klr_action('hide_field', 'radio_outside_target'))),
);

$structure = array(
    array(
        'type' => 'form',
        'steps' => 1,
        'formName' => $form_name,
        'email' => get_option('admin_email'),
        'sendEmail' => false,
        'trackingCode' => true,
        'EfbVersion' => 2,
        'button_single_text' => 'Submit',
        'button_color' => 'btn-secondary',
        'icon' => 'bi-ui-checks-grid',
        'button_Next_text' => 'Next',
        'button_Previous_text' => 'Previous',
        'button_Next_icon' => 'bi-chevron-right',
        'button_Previous_icon' => 'bi-chevron-left',
        'button_state' => 'single',
        'label_text_color' => 'text-light',
        'el_text_color' => 'text-light',
        'message_text_color' => 'text-muted',
        'icon_color' => 'text-light',
        'el_height' => 'h-d-efb',
        'email_to' => false,
        'show_icon' => true,
        'show_pro_bar' => false,
        'captcha' => false,
        'private' => false,
        'font' => true,
        'stateForm' => 0,
        'thank_you' => 'msg',
        'thank_you_message' => array(
            'thankYou' => 'Thanks. Scenarios K/L/Radio form submitted.',
            'done' => 'Done',
            'trackingCode' => 'Tracking code',
            'error' => 'Error',
            'pleaseFillInRequiredFields' => 'Please fill in required fields.',
            'icon' => 'bi-hand-thumbs-up',
        ),
        'email_temp' => '',
        'dShowBg' => true,
        'logic' => true,
        'logic_rules' => $logic_rules,
    ),
    array(
        'id_' => '1',
        'type' => 'step',
        'dataId' => '1',
        'classes' => '',
        'id' => '1',
        'name' => 'Scenarios K and L',
        'icon' => 'bi-diagram-3',
        'step' => '1',
        'amount' => 1,
        'EfbVersion' => 2,
        'message' => 'Test nested groups, numeric operators, and radio-driven rules.',
        'label_text_size' => 'fs-5',
        'el_text_size' => 'fs-5',
        'label_text_color' => 'text-darkb',
        'el_text_color' => 'text-labelEfb',
        'message_text_color' => 'text-muted',
        'icon_color' => 'text-danger',
        'visible' => 1,
    ),
    efb_klr_field('customer_type', 'select', 'Customer type', 2, array('required' => true, 'placeholder' => 'Select customer type')),
    efb_klr_option('customer_type_individual', 'customer_type', 'Individual', 3),
    efb_klr_option('customer_type_company', 'customer_type', 'Company', 4),
    efb_klr_field('budget', 'number', 'Budget', 5, array(
        'placeholder' => 'Try 500, 1000, 1500, 2000, 3000',
        'message' => 'K: inline message appears here. L: numeric outputs below react to this value.',
    )),
    efb_klr_field('logic_command', 'text', 'Logic command', 6, array('placeholder' => 'Type vip for Scenario K OR branch')),
    efb_klr_field('operator_mode', 'radio', 'Radio operator mode', 7, array(
        'required' => false,
        'message' => 'Radio rules use option ids: operator_mode_between / operator_mode_outside.',
        'op_style' => 1,
    )),
    efb_klr_option('operator_mode_between', 'operator_mode', 'Strict between 500 and 2000', 8),
    efb_klr_option('operator_mode_outside', 'operator_mode', 'Outside range only', 9),
    efb_klr_field('gte_target', 'text', 'gte result: budget >= 1000', 10, array('value' => 'Visible when budget is 1000 or more.')),
    efb_klr_field('lte_target', 'text', 'lte result: budget <= 2000', 11, array('value' => 'Visible when budget is 2000 or less.')),
    efb_klr_field('conflict_target', 'text', 'between result: 500 <= budget <= 2000', 12, array('value' => 'Visible inside the inclusive range 500..2000.')),
    efb_klr_field('not_between_target', 'text', 'not_between result: outside 500..2000', 13, array('value' => 'Visible outside the range, when budget is not empty.')),
    efb_klr_field('radio_between_target', 'text', 'radio + between result', 14, array('value' => 'Visible only when radio = Strict between and budget is inside range.')),
    efb_klr_field('radio_outside_target', 'text', 'radio + not_between result', 15, array('value' => 'Visible only when radio = Outside range and budget is outside range.')),
);

$json = wp_json_encode($structure, JSON_UNESCAPED_UNICODE);
if ($json === false) {
    fwrite(STDERR, "Could not encode form structure.\n");
    exit(1);
}

$existing_id = (int) $wpdb->get_var(
    $wpdb->prepare("SELECT form_id FROM {$table} WHERE form_name = %s ORDER BY form_id ASC LIMIT 1", $form_name)
);

$data = array(
    'form_name' => $form_name,
    'form_structer' => $json,
    'form_email' => get_option('admin_email'),
    'form_created_by' => get_current_user_id(),
    'form_type' => 'form',
);

if ($existing_id > 0) {
    $updated = $wpdb->update($table, $data, array('form_id' => $existing_id));
    if ($updated === false) {
        fwrite(STDERR, "Failed to update form: {$wpdb->last_error}\n");
        exit(1);
    }
    $form_id = $existing_id;
    $action = 'updated';
} else {
    $data['form_create_date'] = current_time('mysql');
    $inserted = $wpdb->insert($table, $data);
    if (!$inserted) {
        fwrite(STDERR, "Failed to insert form: {$wpdb->last_error}\n");
        exit(1);
    }
    $form_id = (int) $wpdb->insert_id;
    $action = 'inserted';
}

wp_cache_delete('efb_form_' . md5('form_' . $form_id), 'emsfb');
wp_cache_delete('efb_form_' . $form_id, 'emsfb');

$shortcode = '[EMS_Form_Builder id="' . $form_id . '"]';
$page = get_page_by_path($page_slug);
$page_data = array(
    'post_title' => 'EFB Scenarios K L Radio',
    'post_name' => $page_slug,
    'post_status' => 'publish',
    'post_type' => 'page',
    'post_content' => $shortcode,
);

if ($page) {
    $page_data['ID'] = $page->ID;
    $page_id = wp_update_post($page_data, true);
} else {
    $page_id = wp_insert_post($page_data, true);
}

if (is_wp_error($page_id)) {
    fwrite(STDERR, "Form {$action}, but page creation failed: " . $page_id->get_error_message() . "\n");
    exit(1);
}

$settings = get_setting_Emsfb('pub');
$logic_addon_active = false;
if (is_array($settings) && isset($settings[1])) {
    $logic_addon_active = function_exists('emsfb_is_addon_active_efb')
        ? emsfb_is_addon_active_efb($settings[1], 'AdnSMF')
        : false;
}

echo "Scenarios K/L/Radio form {$action}.\n";
echo "Form ID: {$form_id}\n";
echo "Shortcode: {$shortcode}\n";
echo "Test page: " . get_permalink($page_id) . "\n";
echo "Conditional Logic addon AdnSMF active: " . ($logic_addon_active ? 'yes' : 'no') . "\n";
echo "K: (customer_type is Company AND budget gte 1000) OR (logic_command is vip)\n";
echo "L: gte 1000, lte 2000, between {$range}, not_between {$range}\n";
echo "Radio: operator_mode_between + between, operator_mode_outside + not_between\n";
