<?php
/**
 * Seed the ALL-REMAINING-TESTS form for MANUAL-TEST-GUIDE sections
 * 3, 4, 7-15 (everything after the Phase 7 Inspector form):
 *
 *   §3  Calculations           step 2: total = price * qty (2 decimals)
 *   §4  Conflicts              R_cf_a/R_cf_b seeded DISABLED — enable both to
 *                              see the warning (4.1), disable to clear (4.6)
 *   §7  Sources                utm_banner (?utm_source=google), member_note
 *                              (logged in), admin_note (role administrator),
 *                              step2 message (current_step >= 2)
 *   §8  Date operators         birth_date date_before 2008-01-01 -> senior_note
 *   §9  Field actions          copy_value nickname<-full_name; ui_command:
 *                              labels / focus / scroll
 *   §10 Block / End form       score < 10 -> block submit; age < 18 -> end form
 *   §11 NOT (NAND)             NOT(service=Sales AND score>50) -> nand_note
 *   §12 CC/BCC + tokens        NR1 sales@ +cc/bcc, subject {service}/{score};
 *                              CR1 redirect ?svc={service}&score={score}
 *   §13 Stop webhook/payload   WR_a (payload: score,service) + WR_b; WR_stop
 *                              cancels crm_b when vip_code = stopb
 *   §14 Export/Import/Duplicate  use the buttons on this form's rule list
 *   §15 Frontend debug         open the page with ?efb_logic_debug=1
 *
 * Run from the plugin root:
 *   C:\xampp\php\php.exe tests/seed-gap-features-form.php
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

$form_name = 'EFB Gap Features All Tests';
$page_slug = 'efb-gap-features-all-tests';
$table = $wpdb->prefix . 'emsfb_form';
$admin_email = get_option('admin_email');
$catcher = home_url('/wp-content/plugins/easy-form-builder/tests/webhook-catcher.php');

function efb_gap_field($id, $type, $name, $amount, $step, $extra = array()) {
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
        'pro' => false,
        'icon_input' => '',
    ), $extra);
}

function efb_gap_option($parent, $suffix, $value, $amount, $step = 1) {
    return array(
        'id_' => $parent . '_' . $suffix,
        'dataId' => $parent . '_' . $suffix . '-id',
        'parent' => $parent,
        'type' => 'option',
        'value' => $value,
        'id_op' => $parent . '_' . $suffix,
        'step' => (string) $step,
        'amount' => $amount,
    );
}

function efb_gap_cond($field_id, $compare, $value = '', $extra = array()) {
    return array_merge(array(
        'type' => 'condition',
        'source' => 'field',
        'field_id' => $field_id,
        'compare' => $compare,
        'value' => $value,
    ), $extra);
}

function efb_gap_rule($id, $name, $priority, $conditions, $actions, $extra = array()) {
    return array_merge(array(
        'id' => $id,
        'name' => $name,
        'scope' => 'field',
        'enabled' => true,
        'priority' => $priority,
        'stop_processing' => false,
        'conditions' => $conditions,
        'actions' => $actions,
    ), $extra);
}

function efb_gap_group($items, $extra = array()) {
    return array_merge(array('type' => 'group', 'operator' => 'AND', 'items' => $items), $extra);
}

$logic_rules = array(
    /* §7.1-7.2 query param */
    efb_gap_rule('r_qp', 'QP Show banner for utm_source=google', 10,
        efb_gap_group(array(array(
            'type' => 'condition', 'source' => 'query_param',
            'field_id' => 'utm_source', 'param' => 'utm_source',
            'compare' => 'is', 'value' => 'google',
        ))),
        array(array('type' => 'show_field', 'target' => 'utm_banner'))
    ),
    /* §7.3 logged in */
    efb_gap_rule('r_user', 'USER Show note for logged-in users', 11,
        efb_gap_group(array(array(
            'type' => 'condition', 'source' => 'user',
            'field_id' => 'logged_in', 'compare' => 'is', 'value' => 'yes',
        ))),
        array(array('type' => 'show_field', 'target' => 'member_note'))
    ),
    /* §7.4 role */
    efb_gap_rule('r_role', 'ROLE Show note for administrators', 12,
        efb_gap_group(array(array(
            'type' => 'condition', 'source' => 'user',
            'field_id' => 'role', 'compare' => 'is', 'value' => 'administrator',
        ))),
        array(array('type' => 'show_field', 'target' => 'admin_note'))
    ),
    /* §7.5 current step */
    efb_gap_rule('r_step', 'STEP Message on step 2+', 13,
        efb_gap_group(array(array(
            'type' => 'condition', 'source' => 'current_step',
            'field_id' => 'current_step', 'compare' => 'gte', 'value' => '2',
        ))),
        array(array('type' => 'show_message', 'target' => 'step2_info', 'value' => 'You are on step 2 (current_step >= 2 matched).'))
    ),
    /* §8 date operator */
    efb_gap_rule('r_date', 'DATE Adult if born before 2008-01-01', 14,
        efb_gap_group(array(efb_gap_cond('birth_date', 'date_before', '2008-01-01'))),
        array(array('type' => 'show_field', 'target' => 'senior_note'))
    ),
    /* §3 calculation (step 2) */
    efb_gap_rule('r_calc', 'CALC total = price * qty', 15,
        efb_gap_group(array(efb_gap_cond('price', 'is_not_empty'))),
        array(array('type' => 'calculate', 'target' => 'total', 'value' => '{price} * {qty}', 'decimals' => 2))
    ),
    /* §9 copy value */
    efb_gap_rule('r_copy', 'COPY nickname <- full_name', 20,
        efb_gap_group(array(efb_gap_cond('full_name', 'is_not_empty'))),
        array(array('type' => 'copy_value', 'target' => 'nickname', 'value' => 'full_name'))
    ),
    /* §9 placeholder / label / help */
    efb_gap_rule('r_ui_labels', 'UI labels/placeholder/help on nickname', 21,
        efb_gap_group(array(efb_gap_cond('ui_command', 'is', 'labels'))),
        array(
            array('type' => 'set_placeholder', 'target' => 'nickname', 'value' => 'Custom placeholder from rule'),
            array('type' => 'set_label', 'target' => 'nickname', 'value' => 'Nickname (changed by rule)'),
            array('type' => 'set_help', 'target' => 'nickname', 'value' => 'This help text was set by a logic rule.'),
        )
    ),
    /* §9 focus / scroll */
    efb_gap_rule('r_focus', 'UI focus nickname', 22,
        efb_gap_group(array(efb_gap_cond('ui_command', 'is', 'focus'))),
        array(array('type' => 'focus_field', 'target' => 'nickname'))
    ),
    efb_gap_rule('r_scroll', 'UI scroll to birth_date', 23,
        efb_gap_group(array(efb_gap_cond('ui_command', 'is', 'scroll'))),
        array(array('type' => 'scroll_to_field', 'target' => 'birth_date'))
    ),
    /* §11 NOT (NAND): hidden only when Sales AND score > 50 */
    efb_gap_rule('r_nand', 'NAND note: NOT(Sales AND score>50)', 25,
        efb_gap_group(
            array(
                efb_gap_cond('service', 'is', 'Sales'),
                efb_gap_cond('score', 'gt', '50', array('connector' => 'AND')),
            ),
            array('negate' => true)
        ),
        array(array('type' => 'show_field', 'target' => 'nand_note'))
    ),
    /* §10 block submit */
    efb_gap_rule('r_block', 'BLOCK submit when score < 10', 30,
        efb_gap_group(array(efb_gap_cond('score', 'lt', '10'))),
        array(array('type' => 'block_submit', 'target' => '', 'value' => 'Score below 10 — submission blocked.'))
    ),
    /* §10 end form */
    efb_gap_rule('r_end', 'END form when age < 18', 31,
        efb_gap_group(array(efb_gap_cond('age', 'lt', '18'))),
        array(array('type' => 'end_form', 'target' => '', 'value' => 'This form is for adults only (18+).'))
    ),
    /* §4 conflict pair — seeded DISABLED; enable both to see the warning */
    efb_gap_rule('r_cf_a', 'CONFLICT A show senior_note (enable me)', 40,
        efb_gap_group(array(efb_gap_cond('score', 'is_not_empty'))),
        array(array('type' => 'show_field', 'target' => 'senior_note')),
        array('enabled' => false)
    ),
    efb_gap_rule('r_cf_b', 'CONFLICT B hide senior_note (enable me)', 41,
        efb_gap_group(array(efb_gap_cond('score', 'is_not_empty'))),
        array(array('type' => 'hide_field', 'target' => 'senior_note')),
        array('enabled' => false)
    ),
);

$notification_rules = array(
    /* §12.1-12.3 CC/BCC + subject tokens */
    array(
        'id' => 'nr_sales',
        'name' => 'NR Sales +cc/bcc, token subject',
        'enabled' => true,
        'priority' => 10,
        'conditions' => efb_gap_group(array(efb_gap_cond('service', 'is', 'Sales'))),
        'recipient' => 'sales@example.com',
        'cc' => array('boss@example.com'),
        'bcc' => array('audit@example.com'),
        'subject' => 'New {service} order — score {score}',
        'template' => 'default',
    ),
);

$confirmation_rules = array(
    /* §12.4 redirect with tokens */
    array(
        'id' => 'cr_vip',
        'name' => 'CR VIP redirect with tokens (score >= 90)',
        'enabled' => true,
        'priority' => 5,
        'conditions' => efb_gap_group(array(efb_gap_cond('score', 'gte', '90'))),
        'action' => 'redirect',
        'url' => 'https://example.com/vip-thanks?svc={service}&score={score}',
        'message' => '',
    ),
    array(
        'id' => 'cr_support',
        'name' => 'CR Support styled message',
        'enabled' => true,
        'priority' => 20,
        'conditions' => efb_gap_group(array(efb_gap_cond('service', 'is', 'Support'))),
        'action' => 'message',
        'url' => '',
        'message' => 'Support request registered.',
        'done' => 'Support',
        'icon' => 'bi-envelope-check',
        'tracking_label' => '',
        'icon_color' => '#0d6efd',
        'title_color' => '',
        'message_color' => '',
    ),
);

$webhook_rules = array(
    /* §13.3 stop rule — cancels crm_b only, when vip_code = stopb */
    array(
        'id' => 'wr_stop_b',
        'name' => 'WR STOP crm_b when vip_code=stopb',
        'enabled' => true,
        'priority' => 1,
        'scope' => 'webhook',
        'action' => 'stop',
        'webhook_id' => 'crm_b',
        'url' => '',
        'method' => 'POST',
        'payload_fields' => array(),
        'conditions' => efb_gap_group(array(efb_gap_cond('vip_code', 'is', 'stopb'))),
    ),
    /* §13.4 trigger with payload whitelist */
    array(
        'id' => 'wr_a',
        'name' => 'WR A (payload: score+service only)',
        'enabled' => true,
        'priority' => 10,
        'scope' => 'webhook',
        'action' => 'trigger',
        'webhook_id' => 'crm_a',
        'url' => $catcher,
        'method' => 'POST',
        'payload_fields' => array('score', 'service'),
        'conditions' => efb_gap_group(array(efb_gap_cond('score', 'gte', '50'))),
    ),
    /* full-payload trigger — the one the stop rule can cancel */
    array(
        'id' => 'wr_b',
        'name' => 'WR B full payload (stoppable)',
        'enabled' => true,
        'priority' => 11,
        'scope' => 'webhook',
        'action' => 'trigger',
        'webhook_id' => 'crm_b',
        'url' => $catcher,
        'method' => 'POST',
        'payload_fields' => array(),
        'conditions' => efb_gap_group(array(efb_gap_cond('score', 'gte', '50'))),
    ),
);

$structure = array(
    array(
        'type' => 'form',
        'steps' => 2,
        'formName' => $form_name,
        'email' => $admin_email,
        'sendEmail' => false,
        'trackingCode' => true,
        'EfbVersion' => 2,
        'button_single_text' => 'Submit',
        'button_color' => 'btn-secondary',
        'icon' => 'bi-stars',
        'button_Next_text' => 'Next',
        'button_Previous_text' => 'Previous',
        'button_Next_icon' => 'bi-chevron-right',
        'button_Previous_icon' => 'bi-chevron-left',
        'button_state' => 'group',
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
            'thankYou' => 'Gap features form submitted (default thank-you).',
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
        'notification_rules' => $notification_rules,
        'confirmation_rules' => $confirmation_rules,
        'webhook_rules' => $webhook_rules,
    ),
    array(
        'id_' => '1', 'type' => 'step', 'dataId' => '1', 'classes' => '', 'id' => '1',
        'name' => 'Profile & rules', 'icon' => 'bi-person', 'step' => '1', 'amount' => 1,
        'EfbVersion' => 2,
        'message' => 'Sources (?utm_source=google, login, role), date, copy/UI actions, NAND, block/end form.',
        'label_text_size' => 'fs-5', 'el_text_size' => 'fs-5',
        'label_text_color' => 'text-darkb', 'el_text_color' => 'text-labelEfb',
        'message_text_color' => 'text-muted', 'icon_color' => 'text-danger', 'visible' => 1,
    ),
    array(
        'id_' => '2', 'type' => 'step', 'dataId' => '2', 'classes' => '', 'id' => '2',
        'name' => 'Pricing', 'icon' => 'bi-calculator', 'step' => '2', 'amount' => 2,
        'EfbVersion' => 2,
        'message' => 'Calculation (total = price * qty) + current_step message.',
        'label_text_size' => 'fs-5', 'el_text_size' => 'fs-5',
        'label_text_color' => 'text-darkb', 'el_text_color' => 'text-labelEfb',
        'message_text_color' => 'text-muted', 'icon_color' => 'text-danger', 'visible' => 1,
    ),
    /* ── Step 1 ─────────────────────────────────────────────────────────── */
    efb_gap_field('service', 'select', 'Service', 3, 1, array('placeholder' => 'Sales triggers NR1; Support triggers CR2')),
    efb_gap_option('service', 'sales', 'Sales', 4),
    efb_gap_option('service', 'support', 'Support', 5),
    efb_gap_field('score', 'number', 'Score', 6, 1, array('placeholder' => '<10 blocks submit; >=50 webhooks; >=90 VIP redirect')),
    efb_gap_field('age', 'number', 'Age', 7, 1, array('placeholder' => '<18 ends the form early')),
    efb_gap_field('vip_code', 'text', 'VIP code', 8, 1, array('placeholder' => 'stopb = cancel webhook crm_b')),
    efb_gap_field('full_name', 'text', 'Full name', 9, 1, array('placeholder' => 'Copied into Nickname automatically')),
    efb_gap_field('nickname', 'text', 'Nickname', 10, 1, array('placeholder' => 'Target of copy/placeholder/label/help/focus')),
    efb_gap_field('ui_command', 'select', 'UI command', 11, 1, array('placeholder' => 'labels / focus / scroll')),
    efb_gap_option('ui_command', 'labels', 'labels', 12),
    efb_gap_option('ui_command', 'focus', 'focus', 13),
    efb_gap_option('ui_command', 'scroll', 'scroll', 14),
    efb_gap_field('birth_date', 'date', 'Birth date', 15, 1),
    efb_gap_field('senior_note', 'text', 'Adult note (date rule)', 16, 1, array('placeholder' => 'Shown when birth date is before 2008-01-01')),
    efb_gap_field('utm_banner', 'text', 'UTM banner (query rule)', 17, 1, array('placeholder' => 'Visible only with ?utm_source=google')),
    efb_gap_field('member_note', 'text', 'Member note (login rule)', 18, 1, array('placeholder' => 'Visible only when logged in')),
    efb_gap_field('admin_note', 'text', 'Admin note (role rule)', 19, 1, array('placeholder' => 'Visible only for administrators')),
    efb_gap_field('nand_note', 'text', 'NAND note', 20, 1, array('placeholder' => 'Hidden ONLY when Sales AND score>50')),
    /* ── Step 2 ─────────────────────────────────────────────────────────── */
    efb_gap_field('price', 'number', 'Price', 21, 2),
    efb_gap_field('qty', 'number', 'Quantity', 22, 2),
    efb_gap_field('total', 'number', 'Total (calculated)', 23, 2, array('placeholder' => 'price * qty')),
    efb_gap_field('step2_info', 'text', 'Step 2 info', 24, 2, array('placeholder' => 'Gets the current_step message')),
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

$permalink = get_permalink($page_id);
echo "Gap features form {$action}.\n";
echo "Form ID: {$form_id}\n";
echo "Shortcode: {$shortcode}\n";
echo "Test page: {$permalink}\n";
echo "Query-param test: {$permalink}?utm_source=google\n";
echo "Frontend debug:  {$permalink}?efb_logic_debug=1\n";
echo "Webhook catcher: {$catcher}\n\n";
echo "Guide mapping (MANUAL-TEST-GUIDE §):\n";
echo "  §3  step 2: price*qty -> Total (2 decimals)\n";
echo "  §4  enable CONFLICT A + B rules -> yellow Conflicts box; disable -> gone\n";
echo "  §7  ?utm_source=google -> UTM banner | login -> Member note | administrator -> Admin note | step2 -> message\n";
echo "  §8  Birth date < 2008-01-01 -> Adult note appears\n";
echo "  §9  Full name -> copied to Nickname | UI command: labels/focus/scroll\n";
echo "  §10 Score < 10 -> submit blocked | Age < 18 -> form ends with message\n";
echo "  §11 NAND note hidden ONLY when Service=Sales AND Score>50\n";
echo "  §12 Service=Sales -> mail to sales@ + cc boss@ + bcc audit@, subject tokens; Score>=90 -> redirect with tokens\n";
echo "  §13 Score>=50 -> WR A (payload only score+service) + WR B (full); vip_code=stopb cancels WR B only\n";
echo "  §14 Export/Import/Duplicate buttons on this form's rule list\n";
echo "  §15 ?efb_logic_debug=1 -> trace in Console\n";
