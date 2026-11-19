<?php
/**
 * Seed the page of tests/test-conditional-logic-multiform-browser.js: two conditional-logic forms on ONE page.
 *
 *   B  the action fixture of tests/seed-conditional-logic-actions-form.php (seeded here too);
 *   A  a "Contact us" form whose select opens one of two extra steps: "Pro Support" opens step 3 and makes its
 *      activation code required, "Bug Report" opens step 2, "General Inquiry" keeps both shut. On the owner's site
 *      this was form 216; its field ids are the ones the test drives.
 *
 * Idempotent (found by form name and page slug). Prints the ids and the page address as JSON on the last line.
 * Run from the plugin root: php tests/seed-conditional-logic-multiform-page.php
 */

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "This script is CLI-only.\n");
    exit(1);
}

// Form B and the field/step/option/rule builders.
ob_start();
require __DIR__ . '/seed-conditional-logic-actions-form.php';
ob_end_clean();
$form_b = (int) $form_id;

global $wpdb;
$table = $wpdb->prefix . 'emsfb_form';
$form_name = 'EFB Two Logic Forms - Contact us';
$page_slug = 'efb-two-logic-forms';

$rule = static function ($id, $name, $priority, $option, $actions) {
    $r = efb_cla_rule($id, $name, $priority, $option, $actions);
    $r['conditions']['items'][0]['field_id'] = 'rxkpc909c';
    return $r;
};

$structure = array(
    array(
        'type' => 'form', 'steps' => '3', 'formName' => $form_name, 'email' => get_option('admin_email'),
        'sendEmail' => '', 'trackingCode' => '1', 'EfbVersion' => '2', 'button_single_text' => 'Submit',
        'button_color' => 'btn-primary', 'icon' => 'bi-upload', 'button_Next_text' => 'Next',
        'button_Previous_text' => 'Previous', 'button_Next_icon' => 'bi-chevron-right',
        'button_Previous_icon' => 'bi-chevron-left', 'button_state' => 'multi', 'corner' => 'efb-square',
        'label_text_color' => 'text-light', 'el_text_color' => 'text-light', 'message_text_color' => 'text-muted',
        'icon_color' => 'text-light', 'el_height' => 'h-d-efb', 'email_to' => '', 'show_icon' => '1',
        'show_pro_bar' => '', 'captcha' => '', 'thank_you' => 'msg',
        'thank_you_message' => array('icon' => 'bi-hand-thumbs-up', 'thankYou' => 'Thank you.', 'done' => 'Done',
            'trackingCode' => 'Confirmation Code', 'pleaseFillInRequiredFields' => 'Please fill in all required fields.'),
        'email_temp' => '', 'stateForm' => '', 'dShowBg' => '1', 'email_noti_type' => 'msg',
        'survey_chart_type' => 'none', 'loading_type' => 'dots', 'loading_color' => '#abb8c3', 'logic' => '1',
        'logic_rules' => array(
            $rule('r_pro', 'Pro Support opens the activation step', 10, 'op_pro', array(
                array('type' => 'show_step', 'target' => '3'),
                array('type' => 'set_required', 'target' => '38r8b0gke'),
            )),
            $rule('r_bug', 'Bug Report opens the details step', 11, 'op_bug', array(
                array('type' => 'show_step', 'target' => '2'),
            )),
        ),
    ),
    efb_cla_step('1', 1, 'Contact', 1),
    efb_cla_field('uoghulv7f', 'text', 'Name', 2, 1, array('required' => '1')),
    efb_cla_field('2jpzt59do', 'email', 'Email', 3, 1, array('required' => '1')),
    efb_cla_field('dvgl7nfn0', 'textarea', 'Message', 4, 1),
    efb_cla_field('rxkpc909c', 'select', 'Topic', 5, 1, array('required' => '1', 'placeholder' => 'Select')),
    efb_cla_option('op_general', 'rxkpc909c', 'General Inquiry', 6, 1),
    efb_cla_option('op_pro', 'rxkpc909c', 'Pro Support', 7, 1),
    efb_cla_option('op_bug', 'rxkpc909c', 'Bug Report', 8, 1),
    efb_cla_step('2', 2, 'Bug details', 9),
    efb_cla_field('bugdetail', 'textarea', 'What happened', 10, 2),
    efb_cla_step('3', 3, 'Pro support', 11),
    efb_cla_field('38r8b0gke', 'text', 'Activation code', 12, 3),
);

$existing = $wpdb->get_row($wpdb->prepare("SELECT form_id FROM {$table} WHERE form_name = %s", $form_name));
$row = array(
    'form_name' => $form_name, 'form_structer' => wp_json_encode($structure), 'form_email' => get_option('admin_email'),
    'form_type' => 'form', 'form_created_by' => (string) get_current_user_id(), 'form_access_by' => 'all', 'status' => 1,
);
if ($existing) {
    $form_a = (int) $existing->form_id;
    $wpdb->update($table, $row, array('form_id' => $form_a));
} else {
    $wpdb->insert($table, $row);
    $form_a = (int) $wpdb->insert_id;
}
if (!$form_a) {
    fwrite(STDERR, 'Form A could not be saved: ' . $wpdb->last_error . "\n");
    exit(1);
}
foreach (array($form_a, $form_b) as $id) {
    wp_cache_delete('efb_form_' . md5('form_' . $id), 'emsfb');
    wp_cache_delete('efb_form_' . $id, 'emsfb');
}

$page = get_page_by_path($page_slug);
$page_data = array(
    'post_title' => 'EFB Two Logic Forms', 'post_name' => $page_slug, 'post_status' => 'publish', 'post_type' => 'page',
    'post_content' => '[EMS_Form_Builder id="' . $form_a . '"]' . "\n\n" . '[EMS_Form_Builder id="' . $form_b . '"]',
);
if ($page) {
    $page_data['ID'] = $page->ID;
    $page_id = wp_update_post($page_data, true);
} else {
    $page_id = wp_insert_post($page_data, true);
}
if (is_wp_error($page_id)) {
    fwrite(STDERR, 'Page creation failed: ' . $page_id->get_error_message() . "\n");
    exit(1);
}
echo wp_json_encode(array('a' => $form_a, 'b' => $form_b, 'url' => get_permalink($page_id))) . "\n";
