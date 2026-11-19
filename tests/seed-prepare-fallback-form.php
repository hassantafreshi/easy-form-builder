<?php
/**
 * Seed the pages of tests/test-prepare-fallback-browser.js: one simple guest form (two optional text fields, no
 * CAPTCHA, the confirmation code on) on its own page, and a page with the confirmation-code finder.
 * Idempotent (found by form name and page slugs). Prints the two paths as JSON on the last line.
 * Run from the plugin root: php tests/seed-prepare-fallback-form.php
 */

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "This script is CLI-only.\n");
    exit(1);
}

require_once __DIR__ . '/wp-env.php';
$wp_load = efb_test_wp_load();
if (!is_readable($wp_load)) {
    fwrite(STDERR, "Could not find wp-load.php at: {$wp_load}\n");
    exit(1);
}
define('WP_USE_THEMES', false);
require_once $wp_load;

global $wpdb;
$table = $wpdb->prefix . 'emsfb_form';
$form_name = 'EFB prepare fallback';

$text = static function ($id, $name, $amount) {
    return array(
        'id_' => $id, 'dataId' => $id . '-id', 'type' => 'text', 'elementId' => 'text', 'placeholder' => $name, 'value' => '',
        'size' => 100, 'message' => '', 'id' => '', 'classes' => '', 'name' => $name, 'required' => '0', 'amount' => $amount,
        'step' => '1', 'label_text_size' => 'fs-6', 'label_position' => 'up', 'el_text_size' => 'fs-6',
        'label_text_color' => 'text-labelEfb', 'el_border_color' => 'border-d', 'el_text_color' => 'text-labelEfb',
        'message_text_color' => 'text-muted', 'el_height' => 'h-d-efb', 'label_align' => 'txt-left',
        'message_align' => 'justify-content-start', 'el_align' => 'justify-content-start', 'pro' => '', 'icon_input' => '',
    );
};
$structure = array(
    array(
        'type' => 'form', 'steps' => '1', 'formName' => $form_name, 'email' => '', 'sendEmail' => '', 'trackingCode' => '1',
        'EfbVersion' => '2', 'button_single_text' => 'Submit', 'button_color' => 'btn-primary', 'icon' => 'bi-upload',
        'button_Next_text' => 'Next', 'button_Previous_text' => 'Previous', 'button_Next_icon' => 'bi-chevron-right',
        'button_Previous_icon' => 'bi-chevron-left', 'button_state' => 'single', 'corner' => 'efb-square',
        'label_text_color' => 'text-light', 'el_text_color' => 'text-light', 'message_text_color' => 'text-muted',
        'icon_color' => 'text-light', 'el_height' => 'h-d-efb', 'email_to' => '', 'show_icon' => '1', 'show_pro_bar' => '',
        'captcha' => '', 'thank_you' => 'msg',
        'thank_you_message' => array('icon' => 'bi-hand-thumbs-up', 'thankYou' => 'Thank you.', 'done' => 'Done',
            'trackingCode' => 'Confirmation Code', 'pleaseFillInRequiredFields' => 'Please fill in all required fields.'),
        'email_temp' => '', 'stateForm' => '', 'dShowBg' => '1', 'email_noti_type' => 'msg', 'survey_chart_type' => 'none',
        'loading_type' => 'dots', 'loading_color' => '#abb8c3',
    ),
    array('id_' => '1', 'type' => 'step', 'dataId' => '1', 'classes' => '', 'id' => '1', 'name' => 'Contact',
        'icon' => 'bi-ui-checks-grid', 'step' => '1', 'amount' => 1, 'EfbVersion' => '2', 'message' => '', 'visible' => '1'),
    $text('pfname', 'Your name', 2),
    $text('pfnote', 'A note', 3),
);

$existing = $wpdb->get_row($wpdb->prepare("SELECT form_id FROM {$table} WHERE form_name = %s", $form_name));
$row = array(
    'form_name' => $form_name, 'form_structer' => wp_json_encode($structure), 'form_email' => '', 'form_type' => 'form',
    'form_created_by' => '1', 'form_access_by' => 'all', 'status' => 1,
);
if ($existing) {
    $form_id = (int) $existing->form_id;
    $wpdb->update($table, $row, array('form_id' => $form_id));
} else {
    $wpdb->insert($table, $row);
    $form_id = (int) $wpdb->insert_id;
}
if (!$form_id) {
    fwrite(STDERR, 'The form could not be saved: ' . $wpdb->last_error . "\n");
    exit(1);
}
wp_cache_delete('efb_form_' . md5('form_' . $form_id), 'emsfb');
wp_cache_delete('efb_form_' . $form_id, 'emsfb');

$paths = array();
foreach (array('form' => array('efb-prepare-fallback', '[EMS_Form_Builder id="' . $form_id . '"]'),
               'tracker' => array('efb-prepare-fallback-tracker', '[Easy_Form_Builder_confirmation_code_finder]')) as $k => $p) {
    $page = get_page_by_path($p[0]);
    $data = array('post_title' => 'EFB ' . $p[0], 'post_name' => $p[0], 'post_status' => 'publish', 'post_type' => 'page', 'post_content' => $p[1]);
    if ($page) { $data['ID'] = $page->ID; $id = wp_update_post($data, true); } else { $id = wp_insert_post($data, true); }
    if (is_wp_error($id)) {
        fwrite(STDERR, 'Page creation failed: ' . $id->get_error_message() . "\n");
        exit(1);
    }
    $paths[$k] = (string) wp_parse_url(get_permalink($id), PHP_URL_PATH);
}
$home = (string) wp_parse_url(home_url('/'), PHP_URL_PATH);
foreach ($paths as $k => $p) {
    // Relative to the site's address (the test prefixes its base URL).
    $paths[$k] = '/' . ltrim(substr($p, strlen(rtrim($home, '/'))), '/');
}
echo wp_json_encode(array('form_id' => $form_id) + $paths) . "\n";
