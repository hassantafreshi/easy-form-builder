<?php
/**
 * Regression test for the payment-form notification email.
 *
 * Two defects this locks down, both reported against a Stripe form whose
 * "Email notification contains" was set to "Send email with submitted form
 * content only" (email_noti_type = just_msg):
 *
 *  A. The administrator's copy was headed "We have received your message." -
 *     the visitor's wording - because generate_just_message_content() rendered
 *     a fixed title and never saw the per-recipient state. Its sibling
 *     generate_message_link_content() had always switched on it.
 *
 *  B. The body carried no payment details, and on a form built purely from
 *     payment fields it carried nothing at all. The submit handler passed the
 *     rows the visitor re-sent (payment fields stripped) instead of the merged
 *     record it had just stored, so the chosen options, amount, gateway,
 *     transaction id and date were all missing.
 *
 * Plus the CSS quoting that made mail clients discard the styling of both the
 * values block and the "View Messages" button.
 *
 * Run: php tests/test-payment-notification-email-content.php
 */

define('ABSPATH', __DIR__ . '/');

function add_action() {}
function add_filter() { return true; }
function add_shortcode() {}
function register_rest_route() {}
function get_current_user_id() { return 1; }
function get_efbFunction() { return null; }
function get_setting_Emsfb() { return []; }
function do_action() {}
function is_admin() { return false; }
function is_rtl() { return false; }
function get_locale() { return 'en_US'; }
function sanitize_email($v) { return filter_var((string)$v, FILTER_SANITIZE_EMAIL); }
function is_email($v) { return filter_var((string)$v, FILTER_VALIDATE_EMAIL) !== false; }
function sanitize_text_field($v) { return is_array($v) ? '' : trim(strip_tags((string)$v)); }
function esc_url($v) { return filter_var((string)$v, FILTER_SANITIZE_URL); }
function esc_attr($v) { return htmlspecialchars((string)$v, ENT_QUOTES); }
function esc_html($v) { return htmlspecialchars((string)$v, ENT_QUOTES); }
function esc_html__($v) { return $v; }
function wp_kses_post($v) { return strip_tags((string)$v, '<b><strong><em><i><br><p><a>'); }
function home_url() { return 'https://site.test'; }
function get_site_url() { return 'https://site.test'; }
function get_bloginfo() { return 'Site'; }
function get_option($k, $d = false) { return $d; }
function wp_date($f) { return date($f); }
function number_format_i18n($n, $d = 0) { return number_format((float) $n, (int) $d); }

define('EMSFB_PLUGIN_DIRECTORY', dirname(__DIR__) . '/');

require dirname(__DIR__) . '/vendor/logic/class-Emsfb-logic-validator.php';
require dirname(__DIR__) . '/includes/class-Emsfb-formbuilder.php';
require dirname(__DIR__) . '/includes/class-Emsfb-public.php';
require dirname(__DIR__) . '/includes/class-email-handler.php';

/* email_get_content_efb() resolves its labels through efbFunction. The real one
 * needs WordPress; only these two members are reached from this path. */
class Test_EFB_Function {
    public function text_efb($keys) {
        $out = [];
        foreach ((array) $keys as $k) { $out[$k] = ucfirst(preg_replace('/(?<!^)[A-Z]/', ' $0', $k)); }
        $out['payment']       = 'Payment';
        $out['id']            = 'ID';
        $out['payAmount']     = 'Pay amount';
        $out['ddate']         = 'Date';
        $out['methodPayment'] = 'Method payment';
        $out['interval']      = 'Interval';
        return $out;
    }
    public function ensure_trailing_colon_efb($t) { return $t === '' ? '' : rtrim($t, ' :') . ' :'; }
}

$pass = 0;
$fail = 0;
function test($label, $ok, $detail = '') {
    global $pass, $fail;
    if ($ok) { $pass++; echo "[PASS] $label\n"; return; }
    $fail++;
    echo "[FAIL] $label\n";
    if ($detail !== '') echo "       $detail\n";
}

/* ── the record a Stripe payment form stores ──────────────────────────────
 * Exactly the shape the live flow produces: the non-payment field the visitor
 * re-sent at submit time, then the rows carried over from the payment intent
 * (the chosen option and the payment record), then the page link. */
$stored_rows = [
    ['id_' => 'fld_price', 'name' => 'Price field', 'type' => 'prcfld', 'value' => '7', 'price' => '7', 'amount' => 3],
    ['id_' => 'opt_basic', 'name' => 'Payment Multi choose', 'type' => 'option_payment', 'value' => 'Basic plan', 'price' => '10', 'amount' => 5],
    [
        'id_' => 'payment', 'name' => 'Payment', 'type' => 'payment', 'value' => '17 usd', 'amount' => 0,
        'paymentIntent' => 'pi_TESTINTENT', 'paymentGateway' => 'stripe', 'paymentmethod' => 'charge',
        'paymentAmount' => 17, 'paymentCreated' => '2026-08-18-03:12:48', 'paymentcurrency' => 'usd',
        'gateway' => 'stripe', 'total' => 17,
    ],
    ['type' => 'w_link', 'id_' => 'w_link', 'id' => 'w_link', 'value' => 'https://site.test/pay-page/', 'amount' => -1],
];
/* What the buggy code passed instead: payment rows already filtered out. */
$non_payment_rows_only = [$stored_rows[0]];

$form = [[
    'type' => 'payment',
    'email_noti_type' => 'just_msg',
]];

$public = (new ReflectionClass('Emsfb\\_Public'))->newInstanceWithoutConstructor();
$fn = new ReflectionProperty('Emsfb\\_Public', 'efbFunction');
$fn->setAccessible(true);
$fn->setValue($public, new Test_EFB_Function());

/* ── A. content: the stored record reaches the email ──────────────────── */

$status = $public->email_status_efb($form, $stored_rows, 'TRACK123');
$body = (string) $status['content'];

test('just_msg maps to the just_message renderer', $status['type'] === 'just_message', 'got: ' . $status['type']);
test('payment transaction id is in the email', strpos($body, 'pi_TESTINTENT') !== false);
test('payment amount is in the email', strpos($body, '17') !== false);
test('payment date is in the email', strpos($body, '2026-08-18-03:12:48') !== false);
test('chosen payment option is in the email', strpos($body, 'Basic plan') !== false);
test('ordinary field is still in the email', strpos($body, 'Price field') !== false);

/* Each priced payment row renders once. Before the fix a paySelect /
 * payMultiselect / option_payment row was printed twice: once by the priced
 * branch and again by the generic fall-through below it. */
test(
    'a priced payment option is not printed twice',
    substr_count($body, 'Basic plan') === 1,
    'occurrences: ' . substr_count($body, 'Basic plan')
);

/* The old call site. Keeping this asserted makes the regression legible: with
 * the payment rows stripped the email loses every payment detail, and a form
 * built only from payment fields ends up with an empty table. */
$degraded = (string) $public->email_status_efb($form, $non_payment_rows_only, 'TRACK123')['content'];
test(
    'the pre-fix input really did drop the payment details',
    strpos($degraded, 'pi_TESTINTENT') === false && strpos($degraded, 'Basic plan') === false
);
test(
    'a payment-only form would have produced an empty table',
    strpos((string) $public->email_status_efb($form, [$stored_rows[3]], 'T')['content'], '<tr>') === false
);

/* ── B. heading: administrator vs visitor ─────────────────────────────── */

$handler = new EmsfbEmailHandler();
$lang = [
    'newMessageReceived' => 'A New Message has been Received.',
    'WeRecivedUrM'       => 'We have received your message.',
    'trackingCode'       => 'Confirmation Code',
    'vmgs'               => 'View Messages',
];

$render = function ($state, $type) use ($handler, $lang, $body) {
    $ref = new ReflectionMethod('EmsfbEmailHandler', $type === 'just_message'
        ? 'generate_just_message_content'
        : 'generate_message_link_content');
    $ref->setAccessible(true);
    $args = $type === 'just_message'
        ? [['TRACK123', $body], $lang, 'left', $state, null]
        : [['TRACK123', $body], $lang, 'https://site.test', '', $state, null];
    return (string) $ref->invokeArgs($handler, $args);
};

$admin_just   = $render('newMessage', 'just_message');
$visitor_just = $render('notiToUserFormFilled_TrackingCode', 'just_message');

test(
    'just_message: admin copy is headed "a new message"',
    strpos($admin_just, 'A New Message has been Received.') !== false
        && strpos($admin_just, 'We have received your message.') === false
);
test(
    'just_message: visitor copy keeps the received-your-message wording',
    strpos($visitor_just, 'We have received your message.') !== false
);
test(
    'message_link: admin copy unchanged (already correct)',
    strpos($render('newMessage', 'message_link'), 'A New Message has been Received.') !== false
);
test(
    'just_message renders no tracking button',
    strpos($admin_just, 'View Messages') === false
);

/* ── C. the styling survives the mail client ──────────────────────────── */

$style = new ReflectionMethod('EmsfbEmailHandler', 'build_content_div_style');
$style->setAccessible(true);
$divStyle = (string) $style->invoke($handler, [
    'align' => 'left', 'color' => '#333333', 'fontSize' => 16,
    'fontFamily' => "'Segoe UI', Tahoma, Geneva, Verdana, Arial, sans-serif",
], 'left');

test(
    'a font stack cannot terminate the style attribute it sits in',
    strpos($divStyle, "'") === false && strpos($divStyle, '"') === false,
    'style: ' . $divStyle
);
test('the font stack itself survives', strpos($divStyle, 'Segoe UI') !== false);

/* The wrapper the style is placed in must not be single-quoted either, so a
 * future value carrying a quote cannot break out. */
$rendered_style = [];
preg_match('/<div style=(.)/', $admin_just, $rendered_style);
test(
    'the values block uses a double-quoted style attribute',
    ($rendered_style[1] ?? '') === '"',
    'delimiter: ' . ($rendered_style[1] ?? '(none)')
);

echo "\n========================================\n";
echo "RESULTS: $pass passed, $fail failed\n";
exit($fail === 0 ? 0 : 1);
