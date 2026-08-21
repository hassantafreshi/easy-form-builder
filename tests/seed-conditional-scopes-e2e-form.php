<?php
/**
 * Seed a form whose notification, confirmation and webhook rules are all keyed
 * on a SELECT OPTION — the exact shape that silently never fired before
 * 2026-08-21, because those three collections compared the option's id_ against
 * the visible text.
 *
 * Submitting this form in a browser is the end-to-end proof: a real email must
 * land in the mail-capture directory, the conditional thank-you must replace the
 * default one, and the webhook catcher must record a delivery.
 *
 * Run from the plugin root:
 *   C:\xampp\php\php.exe tests/seed-conditional-scopes-e2e-form.php
 */

if (PHP_SAPI !== 'cli') { fwrite(STDERR, "CLI only.\n"); exit(1); }
$wp_load = dirname(__DIR__, 4) . DIRECTORY_SEPARATOR . 'wp-load.php';
if (!file_exists($wp_load)) { fwrite(STDERR, "wp-load.php not found\n"); exit(1); }
require_once $wp_load;

global $wpdb;
$form_name = 'EFB Scopes E2E';
$page_slug = 'efb-scopes-e2e';
$table = $wpdb->prefix . 'emsfb_form';
$admin_email = get_option('admin_email');
$catcher = home_url('/wp-content/plugins/easy-form-builder/tests/webhook-catcher.php');

function e2e_field($id, $type, $name, $amount, $extra = array()) {
    return array_merge(array(
        'id_' => $id, 'dataId' => $id . '-id', 'type' => $type, 'elementId' => $type,
        'placeholder' => $name, 'value' => '', 'size' => 100, 'message' => '', 'id' => '',
        'classes' => '', 'name' => $name, 'required' => '0', 'amount' => $amount, 'step' => '1',
        'label_text_size' => 'fs-6', 'label_position' => 'up', 'el_text_size' => 'fs-6',
        'label_text_color' => 'text-labelEfb', 'el_border_color' => 'border-d',
        'el_text_color' => 'text-labelEfb', 'message_text_color' => 'text-muted',
        'el_height' => 'h-d-efb', 'label_align' => 'txt-left',
        'message_align' => 'justify-content-start', 'el_align' => 'justify-content-start',
        'pro' => '', 'icon_input' => '',
    ), $extra);
}
function e2e_option($id, $parent, $value, $amount) {
    return array('id_' => $id, 'dataId' => $id . '-id', 'parent' => $parent, 'type' => 'option',
        'value' => $value, 'id_op' => $id, 'step' => '1', 'amount' => $amount);
}
function e2e_conditions($optionId) {
    return array('type' => 'group', 'operator' => 'AND', 'items' => array(array(
        'type' => 'condition', 'source' => 'field',
        'field_id' => 'plan_choice', 'compare' => 'is',
        /* the OPTION ID, exactly as the rule builder writes it */
        'value' => $optionId,
    )));
}

$structure = array(
    array(
        'type' => 'form', 'steps' => '1', 'formName' => $form_name, 'email' => $admin_email,
        'sendEmail' => '1', 'trackingCode' => '1', 'EfbVersion' => '2',
        'button_single_text' => 'Submit', 'button_color' => 'btn-primary', 'icon' => 'bi-send',
        'button_state' => 'single', 'corner' => 'efb-square',
        'label_text_color' => 'text-light', 'el_text_color' => 'text-light',
        'message_text_color' => 'text-muted', 'icon_color' => 'text-light', 'el_height' => 'h-d-efb',
        'email_to' => 'buyer_email', 'show_icon' => '1', 'captcha' => '',
        'thank_you' => 'msg',
        'thank_you_message' => array(
            'icon' => 'bi-hand-thumbs-up', 'thankYou' => 'DEFAULT thank-you (no rule matched).',
            'done' => 'Done', 'trackingCode' => 'Confirmation Code',
            'pleaseFillInRequiredFields' => 'Please fill in all required fields.',
        ),
        'email_temp' => '', 'stateForm' => '', 'dShowBg' => '1', 'email_noti_type' => 'msg',
        'survey_chart_type' => 'none', 'loading_type' => 'dots', 'loading_color' => '#abb8c3',
        'autofill_id' => '0', 'logic' => '1',

        /* Fields scope — proves the same condition works here too. */
        'logic_rules' => array(array(
            'id' => 'fr_enterprise', 'name' => 'Enterprise reveals the PO field', 'scope' => 'field',
            'enabled' => true, 'priority' => 10, 'stop_processing' => false,
            'conditions' => e2e_conditions('plan_enterprise'),
            'actions' => array(
                array('type' => 'show_field', 'target' => 'po_number'),
                array('type' => 'set_required', 'target' => 'po_number'),
            ),
        )),

        'notification_rules' => array(
            array('id' => 'nr_enterprise', 'name' => 'Enterprise desk', 'enabled' => true,
                'priority' => 10, 'recipient' => 'enterprise-desk@example.com',
                'cc' => array(), 'bcc' => array(),
                'subject' => 'Enterprise enquiry from {buyer_email}', 'template' => 'default',
                'conditions' => e2e_conditions('plan_enterprise')),
            array('id' => 'nr_starter', 'name' => 'Starter desk', 'enabled' => true,
                'priority' => 20, 'recipient' => 'starter-desk@example.com',
                'cc' => array(), 'bcc' => array(), 'subject' => 'Starter enquiry', 'template' => 'default',
                'conditions' => e2e_conditions('plan_starter')),
        ),

        'confirmation_rules' => array(array(
            'id' => 'cr_enterprise', 'name' => 'Enterprise thank-you', 'enabled' => true,
            'priority' => 10, 'action' => 'message',
            'message' => 'ENTERPRISE thank-you — your account manager will call today.',
            'done' => 'Received', 'icon' => 'bi-briefcase', 'tracking_label' => 'Reference',
            'icon_color' => '#0d675f', 'title_color' => '#16202b', 'message_color' => '#55636e',
            'conditions' => e2e_conditions('plan_enterprise'),
        )),

        'webhook_rules' => array(array(
            'id' => 'wh_enterprise', 'name' => 'Enterprise CRM', 'enabled' => true,
            'priority' => 10, 'action' => 'trigger', 'webhook_id' => 'crm',
            'url' => $catcher, 'method' => 'POST',
            'payload_fields' => array('buyer_email', 'plan_choice'),
            'conditions' => e2e_conditions('plan_enterprise'),
        )),
    ),

    array('id_' => '1', 'type' => 'step', 'dataId' => '1', 'classes' => '', 'id' => '1',
        'name' => 'Enquiry', 'icon' => 'bi-chat-right-fill', 'step' => '1', 'amount' => '1',
        'EfbVersion' => '2', 'message' => '', 'visible' => '1'),

    e2e_field('buyer_email', 'email', 'Work email', 2, array('required' => '1')),
    e2e_field('plan_choice', 'select', 'Which plan', 3, array('required' => '1', 'placeholder' => 'Select')),
    e2e_option('plan_starter',    'plan_choice', 'Starter',    4),
    e2e_option('plan_enterprise', 'plan_choice', 'Enterprise', 5),
    e2e_field('po_number', 'text', 'Purchase order number', 6),
);

$existing = $wpdb->get_row($wpdb->prepare("SELECT form_id FROM {$table} WHERE form_name = %s", $form_name));
$row = array(
    'form_name' => $form_name, 'form_structer' => wp_json_encode($structure),
    'form_email' => $admin_email, 'form_type' => 'form',
    'form_created_by' => (string) get_current_user_id(), 'form_access_by' => 'all', 'status' => 1,
);
if ($existing) { $form_id = (int) $existing->form_id; $wpdb->update($table, $row, array('form_id' => $form_id)); $action = 'updated'; }
else { $wpdb->insert($table, $row); $form_id = (int) $wpdb->insert_id; $action = 'inserted'; }

wp_cache_delete('efb_form_' . md5('form_' . $form_id), 'emsfb');
wp_cache_delete('efb_form_' . $form_id, 'emsfb');

$page = get_page_by_path($page_slug, OBJECT, 'page');
$data = array('post_title' => $form_name, 'post_name' => $page_slug, 'post_status' => 'publish',
    'post_type' => 'page', 'post_content' => '[EMS_Form_Builder id="' . $form_id . '"]');
if ($page) { $data['ID'] = $page->ID; $page_id = wp_update_post($data, true); }
else { $page_id = wp_insert_post($data, true); }
if (is_wp_error($page_id)) { fwrite(STDERR, $page_id->get_error_message() . "\n"); exit(1); }

echo "Scopes E2E form {$action}.\nForm ID: {$form_id}\nPage: " . get_permalink($page_id) . "\n";
echo "Webhook catcher: {$catcher}\n";
