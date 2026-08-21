<?php
/**
 * Seed an E2E form that exercises every conditional-logic ACTION in a real
 * browser: field visibility, required/optional, enable/disable, the value
 * actions (set/copy/calculate/clear), the UI actions (placeholder/help/label/
 * message), step show/hide/jump, and the two submission vetoes.
 *
 * The three steps are deliberately given ids that DISAGREE with their
 * positions (id_ "3" sits at position 1, id_ "1" at position 2, id_ "2" at
 * position 3). Step action targets are step ids while a field's `step` is its
 * position, so this layout is the one that catches any code that confuses the
 * two namespaces — the failure that made a required field on a visible step
 * silently unenforced and stripped from the submission.
 *
 * Run from the plugin root:
 *   C:\xampp\php\php.exe tests/seed-conditional-logic-actions-form.php
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

$form_name = 'EFB Conditional Logic Actions';
$page_slug = 'efb-conditional-logic-actions';
$table = $wpdb->prefix . 'emsfb_form';
$admin_email = get_option('admin_email');

function efb_cla_field($id, $type, $name, $amount, $step, $extra = array()) {
    return array_merge(array(
        'id_' => $id,
        'dataId' => $id . '-id',
        'type' => $type,
        'elementId' => $type,
        'placeholder' => $name,
        'value' => '',
        'size' => 100,
        'message' => '',
        'id' => '',
        'classes' => '',
        'name' => $name,
        'required' => '0',
        'amount' => $amount,
        'step' => (string) $step,
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
        'pro' => '',
        'icon_input' => '',
    ), $extra);
}

function efb_cla_step($id, $position, $name, $amount) {
    return array(
        'id_' => (string) $id,
        'type' => 'step',
        'dataId' => (string) $id,
        'classes' => $position > 1 ? 'stepNavEfb' : '',
        'id' => (string) $id,
        'name' => $name,
        'icon' => 'bi-ui-checks-grid',
        'step' => (string) $position,
        'amount' => $amount,
        'EfbVersion' => '2',
        'message' => '',
        'label_text_size' => 'fs-5',
        'el_text_size' => 'fs-5',
        'label_text_color' => 'text-darkb',
        'el_text_color' => 'text-dark',
        'message_text_color' => 'text-muted',
        'icon_color' => 'text-pinkEfb',
        'visible' => '1',
    );
}

function efb_cla_option($id, $parent, $value, $amount, $step) {
    return array(
        'id_' => $id,
        'dataId' => $id . '-id',
        'parent' => $parent,
        'type' => 'option',
        'value' => $value,
        'id_op' => $id,
        'step' => (string) $step,
        'amount' => $amount,
    );
}

function efb_cla_rule($id, $name, $priority, $trigger_option, $actions) {
    return array(
        'id' => $id,
        'name' => $name,
        'scope' => 'field',
        'enabled' => true,
        'priority' => $priority,
        'stop_processing' => false,
        'conditions' => array(
            'type' => 'group',
            'operator' => 'AND',
            'items' => array(array(
                'type' => 'condition',
                'source' => 'field',
                'field_id' => 'trigger',
                'compare' => 'is',
                'value' => $trigger_option,
            )),
        ),
        'actions' => $actions,
    );
}

$structure = array(
    array(
        'type' => 'form',
        'steps' => '3',
        'formName' => $form_name,
        'email' => $admin_email,
        'sendEmail' => '',
        'trackingCode' => '1',
        'EfbVersion' => '2',
        'button_single_text' => 'Submit',
        'button_color' => 'btn-primary',
        'icon' => 'bi-upload',
        'button_Next_text' => 'Next',
        'button_Previous_text' => 'Previous',
        'button_Next_icon' => 'bi-chevron-right',
        'button_Previous_icon' => 'bi-chevron-left',
        'button_state' => 'multi',
        'corner' => 'efb-square',
        'label_text_color' => 'text-light',
        'el_text_color' => 'text-light',
        'message_text_color' => 'text-muted',
        'icon_color' => 'text-light',
        'el_height' => 'h-d-efb',
        'email_to' => '',
        'show_icon' => '1',
        'show_pro_bar' => '',
        'captcha' => '',
        'thank_you' => 'msg',
        'thank_you_message' => array(
            'icon' => 'bi-hand-thumbs-up',
            'thankYou' => 'Conditional-logic action form submitted.',
            'done' => 'Done',
            'trackingCode' => 'Confirmation Code',
            'pleaseFillInRequiredFields' => 'Please fill in all required fields.',
        ),
        'email_temp' => '',
        'stateForm' => '',
        'dShowBg' => '1',
        'email_noti_type' => 'msg',
        'survey_chart_type' => 'none',
        'loading_type' => 'dots',
        'loading_color' => '#abb8c3',
        'autofill_id' => '0',
        'logic' => '1',
        'logic_rules' => array(
            efb_cla_rule('r_show', 'Reveal + describe extra field', 10, 'op_actions', array(
                array('type' => 'show_field',      'target' => 'extra_a'),
                array('type' => 'set_required',    'target' => 'extra_a'),
                array('type' => 'set_placeholder', 'target' => 'extra_a', 'value' => 'Placeholder from rule'),
                array('type' => 'set_label',       'target' => 'extra_a', 'value' => 'Label from rule'),
                array('type' => 'set_help',        'target' => 'extra_a', 'value' => 'Help from rule'),
                array('type' => 'show_message',    'target' => 'extra_a', 'value' => 'Inline message from rule'),
            )),
            array(
                'id' => 'r_topping', 'name' => 'Olives need a note', 'scope' => 'field',
                'enabled' => true, 'priority' => 19, 'stop_processing' => false,
                'conditions' => array('type' => 'group', 'operator' => 'AND', 'items' => array(array(
                    'type' => 'condition', 'source' => 'field',
                    'field_id' => 'topping', 'compare' => 'is', 'value' => 'top_b',
                ))),
                'actions' => array(
                    array('type' => 'show_field',   'target' => 'topping_note'),
                    array('type' => 'set_required', 'target' => 'topping_note'),
                ),
            ),
            efb_cla_rule('r_calc', 'Calculate total', 11, 'op_calc', array(
                array('type' => 'calculate', 'target' => 'total', 'value' => '{qty} * {price}'),
            )),
            /* Calculate + disable on ONE target is the documented collision:
             * hidden and disabled fields are stripped from the entry on purpose,
             * so the computed total is neither painted nor saved. Kept as a
             * scenario so the contract stays asserted rather than assumed. */
            efb_cla_rule('r_calc_lock', 'Calculate total and lock it', 18, 'op_calc_lock', array(
                array('type' => 'calculate',     'target' => 'total', 'value' => '{qty} * {price}'),
                array('type' => 'disable_field', 'target' => 'total'),
            )),
            efb_cla_rule('r_copy', 'Copy and set values', 12, 'op_copy', array(
                array('type' => 'copy_value', 'target' => 'copy_target', 'value' => 'source_txt'),
                array('type' => 'set_value',  'target' => 'fixed_txt',   'value' => 'FIXED-VALUE'),
            )),
            efb_cla_rule('r_clear', 'Clear the source field', 13, 'op_clear', array(
                array('type' => 'clear_value', 'target' => 'source_txt'),
            )),
            efb_cla_rule('r_block', 'Veto the submission', 14, 'op_block', array(
                array('type' => 'block_submit', 'value' => 'This answer cannot be submitted.'),
            )),
            efb_cla_rule('r_end', 'Close the form', 15, 'op_end', array(
                array('type' => 'end_form', 'value' => 'This form is closed for your answers.'),
            )),
            efb_cla_rule('r_jump', 'Jump straight to the last step', 16, 'op_jump', array(
                array('type' => 'jump_to_step', 'target' => '2'),
            )),
            /* Step ids, NOT positions: "1" is at position 2, "2" at position 3. */
            efb_cla_rule('r_steps', 'Swap which extra step is open', 17, 'op_steps', array(
                array('type' => 'show_step', 'target' => '1'),
                array('type' => 'hide_step', 'target' => '2'),
            )),
        ),
    ),

    efb_cla_step('3', 1, 'Start', 1),

    efb_cla_field('trigger', 'select', 'Choose a behaviour', 2, 1, array('required' => '1', 'placeholder' => 'Select')),
    efb_cla_option('op_actions', 'trigger', 'Field actions',  3, 1),
    efb_cla_option('op_calc',      'trigger', 'Calculate',        4, 1),
    efb_cla_option('op_calc_lock', 'trigger', 'Calculate + lock', 23, 1),
    efb_cla_option('op_copy',    'trigger', 'Copy and set',   5, 1),
    efb_cla_option('op_clear',   'trigger', 'Clear',          6, 1),
    efb_cla_option('op_block',   'trigger', 'Block submit',   7, 1),
    efb_cla_option('op_end',     'trigger', 'End form',       8, 1),
    efb_cla_option('op_jump',    'trigger', 'Jump to last',   9, 1),
    efb_cla_option('op_steps',   'trigger', 'Swap steps',    10, 1),
    efb_cla_option('op_none',    'trigger', 'Nothing',       11, 1),

    efb_cla_field('extra_a',     'text',     'Extra A',      12, 1),

    /* A checkbox-driven rule, in a real rendered form: "is <option>" on a
     * multi-value field compares one ticked entry against one option, and used
     * to match nothing at all while "is_not" matched everything. */
    efb_cla_field('topping', 'checkbox', 'Toppings', 24, 1),
    efb_cla_option('top_a', 'topping', 'Cheese', 25, 1),
    efb_cla_option('top_b', 'topping', 'Olives', 26, 1),
    efb_cla_field('topping_note', 'text', 'Olive preference', 27, 1),
    efb_cla_field('qty',         'number',   'Quantity',     13, 1, array('value' => '')),
    efb_cla_field('price',       'number',   'Price',        14, 1),
    efb_cla_field('total',       'number',   'Total',        15, 1),
    efb_cla_field('source_txt',  'text',     'Source text',  16, 1),
    efb_cla_field('copy_target', 'text',     'Copy target',  17, 1),
    efb_cla_field('fixed_txt',   'text',     'Fixed text',   18, 1),

    efb_cla_step('1', 2, 'Middle', 19),
    efb_cla_field('mid_required', 'text', 'Middle required', 20, 2, array('required' => '1')),

    efb_cla_step('2', 3, 'End', 21),
    efb_cla_field('end_required', 'text', 'End required', 22, 3, array('required' => '1')),
);

$existing = $wpdb->get_row($wpdb->prepare("SELECT form_id FROM {$table} WHERE form_name = %s", $form_name));
$row = array(
    'form_name' => $form_name,
    'form_structer' => wp_json_encode($structure),
    'form_email' => $admin_email,
    'form_type' => 'form',
    'form_created_by' => (string) get_current_user_id(),
    'form_access_by' => 'all',
    'status' => 1,
);

if ($existing) {
    $form_id = (int) $existing->form_id;
    $wpdb->update($table, $row, array('form_id' => $form_id));
    $action = 'updated';
} else {
    $wpdb->insert($table, $row);
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

echo "Conditional-logic action form {$action}.\n";
echo "Form ID: {$form_id}\n";
echo "Test page: " . get_permalink($page_id) . "\n";
echo "Steps: id_ 3 -> position 1, id_ 1 -> position 2, id_ 2 -> position 3\n";
