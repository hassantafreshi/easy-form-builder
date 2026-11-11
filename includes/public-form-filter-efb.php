<?php
defined('ABSPATH') || exit;

/**
 * What a visitor's browser may see of a stored form.
 *
 * `form_structer` holds two kinds of data side by side. Index 0 is the form
 * header: layout settings the public runtime renders from, and server-only
 * settings (notification recipients, message templates, webhook URLs,
 * redirect targets, bot tokens). Every other row is a field definition.
 * The browser needs the field rows and the layout half of the header; the
 * submit handlers re-read the stored form from the database for everything
 * else, so nothing below is needed client-side.
 *
 * Every public path that sends form JSON to visitors (the inline
 * `ajax_object_efm` page data, which also serves the builder's preview page)
 * must pass it through emsfb_public_form_structure_efb() or
 * emsfb_public_form_json_efb() first. The REST session/runtime routes return
 * only nonces, sids and signed tokens, never form JSON; keep it that way.
 *
 * Deny-list, not allow-list: add-ons (autofill, payment gateways, offline
 * mode, human shield) read header keys this plugin cannot see, and an
 * allow-list would silently break them. A new server-only header key must be
 * added here, or kept out of `form_structer` altogether.
 *
 * KEEP (read by public/assets/js/core-efb.js, the payment scripts and the
 * conditional-logic public script; checked by tests/test-public-form-structure-filter.php):
 *   type, formName, steps, stateForm, captcha, trackingCode, thank_you,
 *   thank_you_message, show_icon, show_pro_bar, logic, logic_rules,
 *   conditions (legacy logic), booking, getway, paymentmethod, currency,
 *   persiaPay, AfLnFrm, autofill_*, survey_chart_type, button_* / colors /
 *   el_height / corner / progress_style / steps_style and other layout keys.
 *
 * REMOVE (index 0 only): the exact keys and the prefixes/suffixes below.
 */

if (!function_exists('emsfb_public_form_private_keys_efb')) {
    /** Exact header keys that never leave the server. */
    function emsfb_public_form_private_keys_efb() {
        $core = array(
            // Admin e-mail notification: address, user-email field id, subject, template, transport.
            'email', 'email_to', 'email_sub', 'email_temp', 'email_noti_type', 'email_send_type', 'sendEmail',
            // Redirect target. The submit response carries the resolved URL.
            'rePage',
            // Conditional routing, evaluated on submit only. Confirmation rules
            // reach the browser as the submit response's `conditional_confirmation`.
            'notification_rules', 'webhook_rules', 'confirmation_rules',
            // SMS / Telegram switches, recipients, templates and credentials.
            'smsnoti', 'telegramnoti',
        );
        /* Add-ons may add keys; they cannot take core keys off the list. */
        $extra = function_exists('apply_filters') ? apply_filters('emsfb_public_form_private_keys', array()) : array();
        return array_values(array_unique(array_merge($core, is_array($extra) ? array_filter($extra, 'is_string') : array())));
    }
}

if (!function_exists('emsfb_public_form_is_private_key_efb')) {
    function emsfb_public_form_is_private_key_efb($key, $private_keys = null) {
        if (!is_string($key)) return false;
        if (in_array($key, $private_keys === null ? emsfb_public_form_private_keys_efb() : $private_keys, true)) return true;
        /* Families: sms_admins_phone_no, sms_msg_*, telegram_bot_token,
         * telegram_admin_chat_ids, telegram_msg_*, webhook_*, and any future
         * credential-shaped key. */
        if (preg_match('/^(sms_|telegram_|webhook_|notification_)/', $key)) return true;
        return (bool) preg_match('/(_token|_secret|_password|_api_key|apikey)$/i', $key);
    }
}

if (!function_exists('emsfb_public_form_structure_efb')) {
    /**
     * Decoded form rows -> rows safe for visitors. Accepts arrays or the
     * stdClass rows json_decode() returns and gives back the same shape.
     * Only the header (index 0, type form/payment/login/register/survey) is
     * filtered; field rows are returned untouched.
     */
    function emsfb_public_form_structure_efb($rows) {
        if (!is_array($rows) || !isset($rows[0])) return $rows;
        $private = emsfb_public_form_private_keys_efb();
        $head = $rows[0];
        if (is_object($head)) {
            $head = clone $head;
            foreach (array_keys(get_object_vars($head)) as $key) {
                if (emsfb_public_form_is_private_key_efb($key, $private)) unset($head->$key);
            }
        } elseif (is_array($head)) {
            foreach (array_keys($head) as $key) {
                if (emsfb_public_form_is_private_key_efb($key, $private)) unset($head[$key]);
            }
        } else {
            return $rows;
        }
        $rows[0] = $head;
        return $rows;
    }
}

if (!function_exists('emsfb_public_form_json_efb')) {
    /**
     * Stored `form_structer` text (plain or legacy-escaped) -> filtered,
     * plain JSON text for the page. Fails closed: text that is not a form
     * array becomes '' rather than being passed through unfiltered.
     * Strings that are not JSON at all ('track', '') come back unchanged.
     */
    function emsfb_public_form_json_efb($raw) {
        if (!is_string($raw) || $raw === '') return $raw;
        $trimmed = ltrim($raw, "\xEF\xBB\xBF \t\r\n");
        if ($trimmed === '' || ($trimmed[0] !== '[' && $trimmed[0] !== '{')) return $raw;
        /* Objects, not associative arrays: an empty `{}` must stay `{}` for the
         * runtime's hasOwnProperty checks, and 1.0 must stay 1.0. */
        $rows = function_exists('emsfb_json_decode_efb') ? emsfb_json_decode_efb($raw, false) : json_decode($raw, false);
        if (!is_array($rows)) return '';
        $json = json_encode(emsfb_public_form_structure_efb($rows), JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION);
        return $json === false ? '' : $json;
    }
}
