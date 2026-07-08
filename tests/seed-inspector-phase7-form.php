<?php
/**
 * Seed a focused form for MANUAL-TEST-GUIDE section 2 — Phase 7 Debugger /
 * Inspector (items 2.1 … 2.8). One form demonstrates every Inspector state:
 *
 *   2.1/2.2/2.3  R1  "Show VAT for business"    Matched / Not matched
 *   2.4          R4  disabled rule              Skipped
 *   2.5          R1 + RC calculate              Final values + Effects
 *   2.6          R2 (stop) + R3 (same target)   Blocked by stop processing
 *   2.7          R5 priority 5 defined LAST     runs first in the trace
 *   2.8          R6+R7 value ping-pong          "possible loop" — ONLY with qty=99
 *
 * Fields: customer_type (select Personal/Business), vat_number (text),
 *         price (number), qty (number), total (number), notes (text)
 *
 * Run from the plugin root:
 *   C:\xampp\php\php.exe tests/seed-inspector-phase7-form.php
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

$form_name = 'EFB Inspector Phase 7 Test';
$page_slug = 'efb-inspector-phase7-test';
$table = $wpdb->prefix . 'emsfb_form';
$admin_email = get_option('admin_email');

function efb_p7_field($id, $type, $name, $amount, $extra = array()) {
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

function efb_p7_cond($field_id, $compare, $value = '', $connector = null) {
    $condition = array(
        'type' => 'condition',
        'source' => 'field',
        'field_id' => $field_id,
        'compare' => $compare,
        'value' => $value,
    );
    if ($connector !== null) $condition['connector'] = $connector;
    return $condition;
}

function efb_p7_rule($id, $name, $priority, $items, $actions, $extra = array()) {
    return array_merge(array(
        'id' => $id,
        'name' => $name,
        'scope' => 'field',
        'enabled' => true,
        'priority' => $priority,
        'stop_processing' => false,
        'conditions' => array('type' => 'group', 'operator' => 'AND', 'items' => $items),
        'actions' => $actions,
    ), $extra);
}

$logic_rules = array(
    /* 2.1-2.3: Matched / Not matched (also 2.5 Effects: Shown + Required) */
    efb_p7_rule('r1_vat', 'R1 Show VAT for business', 10,
        array(efb_p7_cond('customer_type', 'is', 'Business')),
        array(
            array('type' => 'show_field', 'target' => 'vat_number'),
            array('type' => 'set_required', 'target' => 'vat_number'),
        )
    ),

    /* 2.5: Final values — calculated total shows up in "Final values" */
    efb_p7_rule('rc_total', 'RC Calculate total', 15,
        array(efb_p7_cond('price', 'is_not_empty')),
        array(array('type' => 'calculate', 'target' => 'total', 'value' => '{price} * {qty}', 'decimals' => 2))
    ),

    /* 2.6: stop_processing pair on the SAME target (notes).
     * When price is filled: R2 matches first (stop) -> R3 shows
     * "Blocked by stop processing" in the trace. Their show/hide pair also
     * demonstrates the Conflicts warning box on the same screen. */
    efb_p7_rule('r2_stop', 'R2 Show notes (stop)', 20,
        array(efb_p7_cond('price', 'is_not_empty')),
        array(array('type' => 'show_field', 'target' => 'notes')),
        array('stop_processing' => true)
    ),
    efb_p7_rule('r3_blocked', 'R3 Hide notes (gets blocked)', 21,
        array(efb_p7_cond('price', 'is_not_empty')),
        array(array('type' => 'hide_field', 'target' => 'notes'))
    ),

    /* 2.4: disabled rule -> Skipped */
    efb_p7_rule('r4_disabled', 'R4 Disabled rule (Skipped)', 25,
        array(efb_p7_cond('qty', 'is_not_empty')),
        array(array('type' => 'show_message', 'target' => 'qty', 'value' => 'This rule never runs.')),
        array('enabled' => false)
    ),

    /* 2.8: deliberate value ping-pong — ONLY triggers with qty = 99.
     * R7 (p30, stop) flips price 2->1 and freezes it; R6 (p40) flips 1->2.
     * Across passes the values cycle, so the Inspector header shows the
     * "possible loop" warning instead of "Stable". */
    efb_p7_rule('r7_loop_a', 'R7 Loop A (qty=99, stop)', 30,
        array(
            efb_p7_cond('qty', 'is', '99'),
            efb_p7_cond('price', 'is', '2', 'AND'),
        ),
        array(array('type' => 'set_value', 'target' => 'price', 'value' => '1', 'value_type' => 'static')),
        array('stop_processing' => true)
    ),
    efb_p7_rule('r6_loop_b', 'R6 Loop B (qty=99)', 40,
        array(
            efb_p7_cond('qty', 'is', '99'),
            efb_p7_cond('price', 'is', '1', 'AND'),
        ),
        array(array('type' => 'set_value', 'target' => 'price', 'value' => '2', 'value_type' => 'static'))
    ),

    /* 2.7: priority — defined LAST in this array but priority 5, so the
     * trace must list it FIRST. */
    efb_p7_rule('r5_first', 'R5 Priority 5 runs first', 5,
        array(efb_p7_cond('customer_type', 'is_not_empty')),
        array(array('type' => 'show_message', 'target' => 'customer_type', 'value' => 'Priority 5 executed before priority 10.'))
    ),
);

$structure = array(
    array(
        'type' => 'form',
        'steps' => 1,
        'formName' => $form_name,
        'email' => $admin_email,
        'sendEmail' => false,
        'trackingCode' => true,
        'EfbVersion' => 2,
        'button_single_text' => 'Submit',
        'button_color' => 'btn-secondary',
        'icon' => 'bi-search',
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
            'thankYou' => 'Inspector Phase 7 test form submitted.',
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
        'notification_rules' => array(),
        'confirmation_rules' => array(),
        'webhook_rules' => array(),
    ),
    array(
        'id_' => '1',
        'type' => 'step',
        'dataId' => '1',
        'classes' => '',
        'id' => '1',
        'name' => 'Inspector Phase 7',
        'icon' => 'bi-search',
        'step' => '1',
        'amount' => 1,
        'EfbVersion' => 2,
        'message' => 'One form for every Inspector state: Matched, Not matched, Skipped, Blocked by stop processing, priority order, calculated Final values, and the qty=99 loop warning.',
        'label_text_size' => 'fs-5',
        'el_text_size' => 'fs-5',
        'label_text_color' => 'text-darkb',
        'el_text_color' => 'text-labelEfb',
        'message_text_color' => 'text-muted',
        'icon_color' => 'text-danger',
        'visible' => 1,
    ),
    efb_p7_field('customer_type', 'select', 'Customer type', 2, array(
        'required' => true,
        'placeholder' => 'Select customer type',
    )),
    array(
        'id_' => 'customer_type_personal',
        'dataId' => 'customer_type_personal-id',
        'parent' => 'customer_type',
        'type' => 'option',
        'value' => 'Personal',
        'id_op' => 'customer_type_personal',
        'step' => '1',
        'amount' => 3,
    ),
    array(
        'id_' => 'customer_type_business',
        'dataId' => 'customer_type_business-id',
        'parent' => 'customer_type',
        'type' => 'option',
        'value' => 'Business',
        'id_op' => 'customer_type_business',
        'step' => '1',
        'amount' => 4,
    ),
    efb_p7_field('vat_number', 'text', 'VAT number', 5, array(
        'placeholder' => 'Shown + required only for Business',
    )),
    efb_p7_field('price', 'number', 'Price', 6, array(
        'placeholder' => 'Fill to trigger RC/R2/R3',
    )),
    efb_p7_field('qty', 'number', 'Quantity', 7, array(
        'placeholder' => 'Normal number; 99 = loop demo (2.8)',
    )),
    efb_p7_field('total', 'number', 'Total (calculated)', 8, array(
        'placeholder' => 'price * qty',
    )),
    efb_p7_field('notes', 'text', 'Notes', 9, array(
        'placeholder' => 'Shown by R2 (stop) — R3 stays blocked',
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

echo "Inspector Phase 7 form {$action}.\n";
echo "Form ID: {$form_id}\n";
echo "Shortcode: {$shortcode}\n";
echo "Test page: " . get_permalink($page_id) . "\n";
echo "Builder: " . admin_url('admin.php?page=easy-form-builder') . " -> edit form {$form_id} -> Form Settings -> Conditional Logic -> Test Mode\n";
echo "Conditional Logic addon AdnSMF active: " . ($logic_addon_active ? 'yes' : 'no') . "\n\n";
echo "Guide mapping (docs/conditional-logic/EFB-Conditional-Logic-MANUAL-TEST-GUIDE.fa.md §2):\n";
echo "  2.2 Matched:  customer_type=Business -> R1 Matched (Show/Required vat_number)\n";
echo "  2.3 Not matched: customer_type=Personal -> R1 Not matched\n";
echo "  2.4 Skipped:  R4 is disabled -> always 'Skipped'\n";
echo "  2.5 Final values/Effects: price=100, qty=3 -> total=300.00 (RC), Shown/Required lists\n";
echo "  2.6 Blocked:  price filled -> R2 Matched (stop) then R3 'Blocked by stop processing'\n";
echo "                (R2/R3 also demo the Conflicts warning: show+hide on notes)\n";
echo "  2.7 Priority: R5 (priority 5, saved last) appears FIRST in the trace\n";
echo "  2.8 Loop:     qty=99 AND price=1 -> header shows the loop warning; any other qty -> 'Stable'\n";
