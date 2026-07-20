<?php
/**
 * Seed a focused E2E form for email dispatch + webhook logging (doc 17.11/17.12).
 *
 * The form routes the notification email to a department inbox based on the
 * selected department, keeps the DEFAULT admin email enabled (sendEmail=true,
 * email_noti_type=msg so the email content carries the submitted data), and
 * fires two conditional webhooks at the local log-only catcher:
 *
 *   - POST on every submit            (webhook_id: log_catcher_post)
 *   - GET  when amount > 100          (webhook_id: log_catcher_get)
 *
 * Catcher/viewer URL:
 *   http://127.0.0.1/wp/wp-content/plugins/easy-form-builder/tests/webhook-catcher.php
 *
 * Run from the plugin root:
 *   C:\xampp\php\php.exe tests/seed-email-webhook-log-form.php
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

$form_name = 'EFB Email Webhook Log Test';
$page_slug = 'efb-email-webhook-log-test';
$table = $wpdb->prefix . 'emsfb_form';
$admin_email = get_option('admin_email');
$catcher_url = plugins_url('tests/webhook-catcher.php', dirname(__DIR__) . '/easy-form-builder.php');

function efb_ewl_field($id, $type, $name, $amount, $extra = array()) {
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

function efb_ewl_option($id, $parent, $value, $amount) {
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

function efb_ewl_condition($field_id, $compare, $value = '') {
    return array(
        'type' => 'condition',
        'source' => 'field',
        'field_id' => $field_id,
        'compare' => $compare,
        'value' => $value,
    );
}

function efb_ewl_notification($id, $priority, $department, $recipient) {
    return array(
        'id' => $id,
        'name' => 'Route to ' . $department,
        'enabled' => true,
        'priority' => $priority,
        'conditions' => array(
            'type' => 'group',
            'operator' => 'AND',
            'items' => array(efb_ewl_condition('department', 'is', $department)),
        ),
        'recipient' => $recipient,
        'subject' => $department . ' department [confirmation_code]',
        'template' => 'default',
    );
}

$structure = array(
    array(
        'type' => 'form',
        'steps' => 1,
        'formName' => $form_name,
        'email' => $admin_email,
        'sendEmail' => true,
        'email_noti_type' => 'msg',
        'trackingCode' => true,
        'EfbVersion' => 2,
        'button_single_text' => 'Submit',
        'button_color' => 'btn-secondary',
        'icon' => 'bi-broadcast',
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
            'thankYou' => 'Thanks. Check debug.log and the webhook catcher page.',
            'done' => 'Done',
            'trackingCode' => 'Tracking code',
            'error' => 'Error',
            'pleaseFillInRequiredFields' => 'Please fill in required fields.',
            'icon' => 'bi-broadcast',
        ),
        'email_temp' => '',
        'dShowBg' => true,
        'logic' => true,
        'logic_rules' => array(),
        'notification_rules' => array(
            efb_ewl_notification('nr_dep_sales', 10, 'Sales', 'sales@example.com'),
            efb_ewl_notification('nr_dep_support', 20, 'Support', 'support@example.com'),
            efb_ewl_notification('nr_dep_billing', 30, 'Billing', 'billing@example.com'),
        ),
        'webhook_rules' => array(
            array(
                'id' => 'wr_log_all',
                'name' => 'Log every submit (POST)',
                'enabled' => true,
                'scope' => 'webhook',
                'priority' => 10,
                'conditions' => array(
                    'type' => 'group',
                    'operator' => 'AND',
                    'items' => array(efb_ewl_condition('department', 'is_not_empty')),
                ),
                'webhook_id' => 'log_catcher_post',
                'url' => $catcher_url,
                'method' => 'POST',
            ),
            array(
                'id' => 'wr_log_big_amount',
                'name' => 'Log big amount (GET)',
                'enabled' => true,
                'scope' => 'webhook',
                'priority' => 20,
                'conditions' => array(
                    'type' => 'group',
                    'operator' => 'AND',
                    'items' => array(efb_ewl_condition('amount', 'gt', '100')),
                ),
                'webhook_id' => 'log_catcher_get',
                'url' => $catcher_url,
                'method' => 'GET',
            ),
        ),
    ),
    array(
        'id_' => '1',
        'type' => 'step',
        'dataId' => '1',
        'classes' => '',
        'id' => '1',
        'name' => 'Email + Webhook log test',
        'icon' => 'bi-broadcast',
        'step' => '1',
        'amount' => 1,
        'EfbVersion' => 2,
        'message' => 'Pick a department: its inbox gets the conditional email, the admin email always goes out, and the webhook catcher logs every submit (POST) plus amounts over 100 (GET).',
        'label_text_size' => 'fs-5',
        'el_text_size' => 'fs-5',
        'label_text_color' => 'text-darkb',
        'el_text_color' => 'text-labelEfb',
        'message_text_color' => 'text-muted',
        'icon_color' => 'text-danger',
        'visible' => 1,
    ),
    efb_ewl_field('department', 'select', 'Department', 2, array(
        'required' => true,
        'placeholder' => 'Select department',
    )),
    efb_ewl_option('department_sales', 'department', 'Sales', 3),
    efb_ewl_option('department_support', 'department', 'Support', 4),
    efb_ewl_option('department_billing', 'department', 'Billing', 5),
    efb_ewl_field('amount', 'number', 'Amount', 6, array(
        'placeholder' => 'Over 100 also fires the GET webhook',
    )),
    efb_ewl_field('note', 'text', 'Note', 7, array(
        'placeholder' => 'Free text, shows up in email content and webhook payload',
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

echo "Email/Webhook log form {$action}.\n";
echo "Form ID: {$form_id}\n";
echo "Shortcode: {$shortcode}\n";
echo "Test page: " . get_permalink($page_id) . "\n";
echo "Webhook catcher: {$catcher_url}\n";
echo "Default admin email (legacy path): {$admin_email}\n";
echo "Departments: Sales -> sales@example.com | Support -> support@example.com | Billing -> billing@example.com\n";
echo "Webhooks: POST on every submit (log_catcher_post) | GET when amount > 100 (log_catcher_get)\n";
