<?php
/**
 * Seed the F01 input-mask test form and its page in a real WordPress (wp-load.php four folders up),
 * for the manual and HTTP checks of docs/feature-gaps/F01-input-masks.fa.md (sub-task F):
 *   mobile   tel  ir.mobile        required   -> stored +989121234567
 *   ncode    text ir.national_id   required   -> 1234567892 refused (checksum)
 *   sheba    text ir.sheba                    -> stored IR...
 *   card     text ir.bank_card                -> kept out of the localStorage draft
 *   zip      postalcode us.zip, display store -> 12345-6789 stored as shown
 *   custom   text custom "(999) 999-9999" + no checksum
 *   plain    tel without a mask (old behaviour, pattern + length)
 *
 * Run from the plugin root: php tests/seed-masks-form.php
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
$form_name = 'EFB F01 Input Masks';
$page_slug = 'efb-f01-input-masks';
$table = $wpdb->prefix . 'emsfb_form';

function efb_mask_seed_field($id, $type, $name, $amount, $extra = array()) {
    return array_merge(array(
        'id_' => $id, 'dataId' => $id . '-id', 'type' => $type, 'elementId' => $type, 'placeholder' => '', 'value' => '',
        'size' => 100, 'message' => '', 'id' => '', 'classes' => '', 'name' => $name, 'required' => 0, 'amount' => $amount,
        'step' => '1', 'label_text_size' => 'fs-6', 'label_position' => 'up', 'el_text_size' => 'fs-6',
        'label_text_color' => 'text-labelEfb', 'el_border_color' => 'border-d', 'el_text_color' => 'text-labelEfb',
        'message_text_color' => 'text-muted', 'el_height' => 'h-d-efb', 'label_align' => 'txt-left',
        'message_align' => 'justify-content-start', 'el_align' => 'justify-content-start', 'pro' => false, 'icon_input' => '',
    ), $extra);
}

$structure = array(
    array(
        'type' => 'form', 'steps' => 1, 'formName' => $form_name, 'email' => '', 'sendEmail' => false, 'trackingCode' => true,
        'EfbVersion' => 2, 'button_single_text' => 'Submit', 'button_color' => 'btn-primary', 'icon' => 'bi-ui-checks-grid',
        'button_Next_text' => 'Next', 'button_Previous_text' => 'Previous', 'button_Next_icon' => 'bi-chevron-right',
        'button_Previous_icon' => 'bi-chevron-left', 'button_state' => 'single', 'label_text_color' => 'text-labelEfb',
        'el_text_color' => 'text-labelEfb', 'message_text_color' => 'text-muted', 'icon_color' => 'text-light',
        'el_height' => 'h-d-efb', 'email_to' => false, 'show_icon' => true, 'show_pro_bar' => false, 'captcha' => false,
        'private' => false, 'font' => true, 'stateForm' => 0, 'thank_you' => 'msg',
        'thank_you_message' => array('thankYou' => 'Masks form submitted.', 'done' => 'Done', 'trackingCode' => 'Tracking code',
            'error' => 'Error', 'pleaseFillInRequiredFields' => 'Please fill in required fields.', 'icon' => 'bi-hand-thumbs-up'),
        'email_temp' => '', 'dShowBg' => true,
    ),
    array('type' => 'step', 'id_' => '1', 'dataId' => '1-id', 'classes' => '', 'id' => '1', 'name' => 'Masks', 'icon' => 'bi-input-cursor-text',
        'step' => '1', 'amount' => 1, 'label_text_size' => 'fs-5', 'message' => '', 'label_text_color' => 'text-darkb',
        'el_text_color' => 'text-labelEfb', 'message_text_color' => 'text-muted', 'icon_color' => 'text-danger', 'visible' => 1),
    efb_mask_seed_field('mobile', 'tel', 'Mobile', 2, array('required' => 1, 'mask_preset' => 'ir.mobile', 'placeholder' => '0912 345 6789')),
    efb_mask_seed_field('ncode', 'text', 'National code', 3, array('required' => 1, 'mask_preset' => 'ir.national_id')),
    efb_mask_seed_field('sheba', 'text', 'Sheba', 4, array('mask_preset' => 'ir.sheba')),
    efb_mask_seed_field('card', 'text', 'Bank card', 5, array('mask_preset' => 'ir.bank_card')),
    efb_mask_seed_field('zip', 'postalcode', 'ZIP', 6, array('mask_preset' => 'us.zip', 'mask_store' => 'display')),
    efb_mask_seed_field('custom', 'text', 'US phone (custom)', 7, array('mask_preset' => 'custom', 'mask_custom' => '(999) 999-9999')),
    efb_mask_seed_field('plain', 'tel', 'Plain phone', 8, array('mlen' => '20')),
);

$json = wp_json_encode($structure, JSON_UNESCAPED_UNICODE);
$existing_id = (int) $wpdb->get_var($wpdb->prepare("SELECT form_id FROM {$table} WHERE form_name = %s ORDER BY form_id ASC LIMIT 1", $form_name));
$data = array('form_name' => $form_name, 'form_structer' => $json, 'form_email' => '', 'form_created_by' => get_current_user_id(), 'form_type' => 'form');
if ($existing_id > 0) {
    if ($wpdb->update($table, $data, array('form_id' => $existing_id)) === false) {
        fwrite(STDERR, "Failed to update form: {$wpdb->last_error}\n");
        exit(1);
    }
    $form_id = $existing_id;
} else {
    $data['form_create_date'] = current_time('mysql');
    if (!$wpdb->insert($table, $data)) {
        fwrite(STDERR, "Failed to insert form: {$wpdb->last_error}\n");
        exit(1);
    }
    $form_id = (int) $wpdb->insert_id;
}
wp_cache_delete('efb_form_' . $form_id, 'emsfb');

$shortcode = '[EMS_Form_Builder id="' . $form_id . '"]';
$page = get_page_by_path($page_slug);
$page_data = array('post_title' => $form_name, 'post_name' => $page_slug, 'post_status' => 'publish', 'post_type' => 'page', 'post_content' => $shortcode);
if ($page) {
    $page_data['ID'] = $page->ID;
}
$page_id = $page ? wp_update_post($page_data, true) : wp_insert_post($page_data, true);
if (is_wp_error($page_id)) {
    fwrite(STDERR, 'Page creation failed: ' . $page_id->get_error_message() . "\n");
    exit(1);
}
echo "Form ID: {$form_id}\nShortcode: {$shortcode}\nTest page: " . get_permalink($page_id) . "\n";
echo "Submit 0912 123 4567 / 0012345679 -> entry holds +989121234567 / 0012345679; 1234567892 -> refused with the checksum message.\n";
