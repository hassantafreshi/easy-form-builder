<?php
/**
 * Seed a focused E2E form for Conditional Logic Scenario K.
 *
 * Run from the plugin root:
 *   C:\xampp\php\php.exe tests/seed-scenario-k-form.php
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

$form_name = 'EFB Scenario K Nested AND OR';
$page_slug = 'efb-scenario-k-nested-and-or';
$table = $wpdb->prefix . 'emsfb_form';

function efb_scenario_k_field($id, $type, $name, $amount, $extra = array()) {
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

function efb_scenario_k_condition($field_id, $compare, $value = '') {
    return array(
        'type' => 'condition',
        'source' => 'field',
        'field_id' => $field_id,
        'compare' => $compare,
        'value' => $value,
    );
}

$r15_message = 'R15 matched: (Company AND budget >= 1000) OR logic_command = vip';

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
            'thankYou' => 'Thanks. Scenario K form submitted.',
            'done' => 'Done',
            'trackingCode' => 'Tracking code',
            'error' => 'Error',
            'pleaseFillInRequiredFields' => 'Please fill in required fields.',
            'icon' => 'bi-hand-thumbs-up',
        ),
        'email_temp' => '',
        'dShowBg' => true,
        'logic' => true,
        'logic_rules' => array(
            array(
                'id' => 'r15_nested_and_or',
                'name' => 'R15 Nested AND/OR',
                'enabled' => true,
                'priority' => 50,
                'stop_processing' => false,
                'conditions' => array(
                    'type' => 'group',
                    'operator' => 'AND',
                    'items' => array(
                        array(
                            'type' => 'group',
                            'operator' => 'AND',
                            'items' => array(
                                efb_scenario_k_condition('customer_type', 'is', 'Company'),
                                array_merge(
                                    efb_scenario_k_condition('budget', 'gte', '1000'),
                                    array('connector' => 'AND')
                                ),
                            ),
                        ),
                        array_merge(
                            efb_scenario_k_condition('logic_command', 'is', 'vip'),
                            array('connector' => 'OR')
                        ),
                    ),
                ),
                'actions' => array(
                    array(
                        'type' => 'show_message',
                        'target' => 'budget',
                        'value' => $r15_message,
                    ),
                ),
            ),
        ),
    ),
    array(
        'id_' => '1',
        'type' => 'step',
        'dataId' => '1',
        'classes' => '',
        'id' => '1',
        'name' => 'Scenario K',
        'icon' => 'bi-diagram-3',
        'step' => '1',
        'amount' => 1,
        'EfbVersion' => 2,
        'message' => 'Nested condition groups with mixed AND/OR connector.',
        'label_text_size' => 'fs-5',
        'el_text_size' => 'fs-5',
        'label_text_color' => 'text-darkb',
        'el_text_color' => 'text-labelEfb',
        'message_text_color' => 'text-muted',
        'icon_color' => 'text-danger',
        'visible' => 1,
    ),
    efb_scenario_k_field('customer_type', 'select', 'Customer type', 2, array(
        'required' => true,
        'placeholder' => 'Select customer type',
    )),
    array(
        'id_' => 'customer_type_individual',
        'dataId' => 'customer_type_individual-id',
        'parent' => 'customer_type',
        'type' => 'option',
        'value' => 'Individual',
        'id_op' => 'customer_type_individual',
        'step' => '1',
        'amount' => 3,
    ),
    array(
        'id_' => 'customer_type_company',
        'dataId' => 'customer_type_company-id',
        'parent' => 'customer_type',
        'type' => 'option',
        'value' => 'Company',
        'id_op' => 'customer_type_company',
        'step' => '1',
        'amount' => 4,
    ),
    efb_scenario_k_field('budget', 'number', 'Budget', 5, array(
        'placeholder' => 'Example: 1500',
        'message' => 'R15 message appears here when the nested group or OR branch matches.',
    )),
    efb_scenario_k_field('logic_command', 'text', 'Logic command', 6, array(
        'placeholder' => 'Type vip to trigger the OR branch',
    )),
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
    'post_title' => 'EFB Scenario K Nested AND OR',
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

echo "Scenario K form {$action}.\n";
echo "Form ID: {$form_id}\n";
echo "Shortcode: {$shortcode}\n";
echo "Test page: " . get_permalink($page_id) . "\n";
echo "Conditional Logic addon AdnSMF active: " . ($logic_addon_active ? 'yes' : 'no') . "\n";
echo "Expected rule: (customer_type is Company AND budget gte 1000) OR (logic_command is vip)\n";
echo "Expected message: {$r15_message}\n";
