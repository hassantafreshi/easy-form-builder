<?php
/**
 * Seed a focused E2E form for Conditional Logic Scenario M (section 17):
 * conditional notification rules (NR1-NR3) + conditional confirmation rules
 * (CR1-CR3, including the styled done-screen overrides: done title, icon,
 * tracking label, colors).
 *
 * The form keeps the DEFAULT admin email notification enabled (sendEmail=true,
 * form_email=admin email) so the legacy email path can be verified alongside
 * the conditional rules.
 *
 * Run from the plugin root:
 *   C:\xampp\php\php.exe tests/seed-scenario-m-form.php
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

$form_name = 'EFB Scenario M Notification Confirmation';
$page_slug = 'efb-scenario-m-notification-confirmation';
$table = $wpdb->prefix . 'emsfb_form';
$admin_email = get_option('admin_email');

function efb_scenario_m_field($id, $type, $name, $amount, $extra = array()) {
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

function efb_scenario_m_condition($field_id, $compare, $value = '') {
    return array(
        'type' => 'condition',
        'source' => 'field',
        'field_id' => $field_id,
        'compare' => $compare,
        'value' => $value,
    );
}

$structure = array(
    array(
        'type' => 'form',
        'steps' => 1,
        'formName' => $form_name,
        'email' => $admin_email,
        'sendEmail' => true,
        'trackingCode' => true,
        'EfbVersion' => 2,
        'button_single_text' => 'Submit',
        'button_color' => 'btn-secondary',
        'icon' => 'bi-envelope-paper',
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
            'thankYou' => 'Thanks. Scenario M form submitted (default thank-you).',
            'done' => 'Done',
            'trackingCode' => 'Tracking code',
            'error' => 'Error',
            'pleaseFillInRequiredFields' => 'Please fill in required fields.',
            'icon' => 'bi-hand-thumbs-up',
        ),
        'email_temp' => '',
        'dShowBg' => true,
        'logic' => true,
        'logic_rules' => array(),
        /* ── 17.2 Notification rules ─────────────────────────────── */
        'notification_rules' => array(
            array(
                'id' => 'nr1_sales',
                'name' => 'NR1 Sales lead',
                'enabled' => true,
                'priority' => 10,
                'conditions' => array(
                    'type' => 'group',
                    'operator' => 'AND',
                    'items' => array(efb_scenario_m_condition('customer_type', 'is', 'Company')),
                ),
                'recipient' => 'sales@example.com',
                'subject' => 'Sales lead [confirmation_code]',
                'template' => 'default',
            ),
            array(
                'id' => 'nr2_vip',
                'name' => 'NR2 VIP lead',
                'enabled' => true,
                'priority' => 20,
                'conditions' => array(
                    'type' => 'group',
                    'operator' => 'AND',
                    'items' => array(
                        efb_scenario_m_condition('has_budget', 'is', 'yes'),
                        array_merge(efb_scenario_m_condition('budget', 'gt', '1000'), array('connector' => 'AND')),
                    ),
                ),
                'recipient' => 'vip@example.com',
                'subject' => 'VIP lead [confirmation_code]',
                'template' => 'default',
            ),
            array(
                'id' => 'nr3_support',
                'name' => 'NR3 Support request',
                'enabled' => true,
                'priority' => 30,
                'conditions' => array(
                    'type' => 'group',
                    'operator' => 'AND',
                    'items' => array(efb_scenario_m_condition('logic_command', 'is', 'support')),
                ),
                'recipient' => 'support@example.com',
                'subject' => 'Support request [confirmation_code]',
                'template' => 'default',
            ),
        ),
        /* ── 17.3 Confirmation rules (CR2/CR3 use the styled done-screen overrides) ── */
        'confirmation_rules' => array(
            array(
                'id' => 'cr1_vip_redirect',
                'name' => 'CR1 VIP redirect',
                'enabled' => true,
                'priority' => 5,
                'conditions' => array(
                    'type' => 'group',
                    'operator' => 'AND',
                    'items' => array(
                        efb_scenario_m_condition('has_budget', 'is', 'yes'),
                        array_merge(efb_scenario_m_condition('budget', 'gt', '1000'), array('connector' => 'AND')),
                    ),
                ),
                'action' => 'redirect',
                'url' => 'https://example.com/vip-thanks',
                'message' => '',
            ),
            array(
                'id' => 'cr2_support_message',
                'name' => 'CR2 Support message',
                'enabled' => true,
                'priority' => 20,
                'conditions' => array(
                    'type' => 'group',
                    'operator' => 'AND',
                    'items' => array(efb_scenario_m_condition('logic_command', 'is', 'support')),
                ),
                'action' => 'message',
                'url' => '',
                'message' => 'درخواست پشتیبانی شما ثبت شد.',
                'done' => 'پشتیبانی',
                'icon' => 'bi-envelope-check',
                'tracking_label' => 'کد پیگیری پشتیبانی',
                'icon_color' => '#0d6efd',
                'title_color' => '#0d6efd',
                'message_color' => '#334155',
            ),
            array(
                'id' => 'cr3_individual_message',
                'name' => 'CR3 Individual message',
                'enabled' => true,
                'priority' => 30,
                'conditions' => array(
                    'type' => 'group',
                    'operator' => 'AND',
                    'items' => array(efb_scenario_m_condition('customer_type', 'is', 'Individual')),
                ),
                'action' => 'message',
                'url' => '',
                'message' => 'فرم شخص حقیقی با موفقیت ثبت شد.',
                'done' => 'ثبت شد',
                'icon' => 'bi-patch-check',
                'tracking_label' => '',
                'icon_color' => '#198754',
                'title_color' => '#198754',
                'message_color' => '',
            ),
        ),
    ),
    array(
        'id_' => '1',
        'type' => 'step',
        'dataId' => '1',
        'classes' => '',
        'id' => '1',
        'name' => 'Scenario M',
        'icon' => 'bi-envelope-paper',
        'step' => '1',
        'amount' => 1,
        'EfbVersion' => 2,
        'message' => 'Conditional notifications (sales/vip/support) and confirmations (redirect/styled messages).',
        'label_text_size' => 'fs-5',
        'el_text_size' => 'fs-5',
        'label_text_color' => 'text-darkb',
        'el_text_color' => 'text-labelEfb',
        'message_text_color' => 'text-muted',
        'icon_color' => 'text-danger',
        'visible' => 1,
    ),
    efb_scenario_m_field('customer_type', 'select', 'Customer type', 2, array(
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
    efb_scenario_m_field('has_budget', 'yesNo', 'Do you have a budget?', 5, array(
        'required' => true,
    )),
    efb_scenario_m_field('budget', 'number', 'Budget', 6, array(
        'placeholder' => 'Try 1500 (VIP redirect) or 500 (default thank-you)',
    )),
    efb_scenario_m_field('logic_command', 'text', 'Logic command', 7, array(
        'placeholder' => 'Type support for CR2/NR3, leave empty otherwise',
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
    'form_email' => $admin_email,
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
    'post_title' => $form_name,
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

echo "Scenario M form {$action}.\n";
echo "Form ID: {$form_id}\n";
echo "Shortcode: {$shortcode}\n";
echo "Test page: " . get_permalink($page_id) . "\n";
echo "Default admin email (legacy path): {$admin_email}\n";
echo "Conditional Logic addon AdnSMF active: " . ($logic_addon_active ? 'yes' : 'no') . "\n";
echo "NR1 sales@example.com <- customer_type is Company (p10)\n";
echo "NR2 vip@example.com <- has_budget is yes AND budget gt 1000 (p20)\n";
echo "NR3 support@example.com <- logic_command is support (p30)\n";
echo "CR1 redirect https://example.com/vip-thanks <- has_budget yes AND budget gt 1000 (p5)\n";
echo "CR2 styled message <- logic_command is support (p20)\n";
echo "CR3 styled message <- customer_type is Individual (p30)\n";
