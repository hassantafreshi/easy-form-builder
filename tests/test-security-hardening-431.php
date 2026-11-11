<?php
/**
 * Regression tests for the 4.3.1 security hardening that followed the
 * Signature-field stored-XSS report. Each check runs the shipped method,
 * lifted out of includes/class-Emsfb-public.php, against minimal stubs.
 *
 *   1. is_same_site_url_efb(): exact host match for the page URL and reply
 *      attachments (a prefix test accepted example.com.evil.tld and
 *      example.com@evil.tld; the page URL carries the admin code in emails).
 *   2. sanitize_account_form_rows_efb(): a register entry stores only the
 *      form's own fields, never a password, with the saved type and name.
 *   3. email_get_content_efb(): color and payment values are escaped.
 *   4. Source checks for the reply allow-list, the validated email rows and
 *      the visitor recipient.
 *
 * Run: php tests/test-security-hardening-431.php
 */
namespace Emsfb;

$pass = 0; $fail = 0;
function t_sec($label, $ok) { global $pass, $fail; if ($ok) { $pass++; echo "[PASS] $label\n"; } else { $fail++; echo "[FAIL] $label\n"; } }

function sanitize_text_field($s) { return trim(preg_replace('/[\r\n\t ]+/', ' ', strip_tags((string) $s))); }
function wp_unslash($s) { return $s; }
/* The shipped code checks function_exists('home_url'), i.e. the global
 * function, so these two are defined in the global namespace. */
eval('function wp_parse_url($u, $c = -1) { return parse_url($u, $c); } function home_url($p = "") { return rtrim($GLOBALS["efb_test_home"], "/") . $p; }');
function sanitize_url($u) { return $u; }
function is_email($e) { return (bool) filter_var($e, FILTER_VALIDATE_EMAIL); }
function sanitize_email($e) { return preg_replace('/[^A-Za-z0-9@._+-]/', '', (string) $e); }
function wp_upload_dir() { return array('baseurl' => 'https://site.test/wp-content/uploads', 'basedir' => '/nonexistent'); }
function wp_normalize_path($p) { return $p; }
function trailingslashit($p) { return rtrim($p, '/') . '/'; }
function esc_html($s) { return htmlspecialchars((string) $s, ENT_QUOTES); }
function esc_attr($s) { return htmlspecialchars((string) $s, ENT_QUOTES); }
function esc_url($u) { return preg_match('#^(https?:)?//#i', $u) ? htmlspecialchars($u, ENT_QUOTES) : ''; }
function __($s) { return $s; }
function esc_html__($s) { return $s; }
class Formbuilder { static function formatPrice_efb($a, $c) { return $a . ' ' . $c; } }
const EMSFB_PLUGIN_DIRECTORY = '/nonexistent/';
class FakeFunctions_Efb { function text_efb($k) { $o = array(); foreach ($k as $x) $o[$x] = $x; return $o; } function ensure_trailing_colon_efb($t) { return $t; } }

$src = file_get_contents(__DIR__ . '/../includes/class-Emsfb-public.php');
$body = '';
foreach (array('is_same_site_url_efb', 'normalize_submitted_row_efb', 'sanitize_payment_merge_row_efb', 'filter_attributes_by_type_efb',
	'filter_and_sanitize_attributes_efb', 'sanitize_value_efb', 'canonical_esign_value_efb', 'sanitize_account_form_rows_efb',
	'validate_submitted_upload_url_efb', 'replaceContentMessageEfb', 'email_format_price_efb', 'email_escape_leaf_efb', 'email_get_content_efb',
	'efb_submitter_email_efb', 'plan_locked_field_types_efb', 'validate_required_fields_present_efb', 'stored_row_name_efb') as $fn) {
	if (!preg_match('/\t(?:public|private)(?: static)? function ' . $fn . '\(.*?\n\t\}\n/s', $src, $m)) { echo "[FAIL] $fn() not found\n"; exit(1); }
	$body .= str_replace('private function', 'public function', $m[0]);
}
if (preg_match('/\tpublic function get_ip_address\(\) \{.*?\n\t\}\n/s', $src, $m)) $body .= str_replace('__DIR__', "'" . __DIR__ . "/../includes'", $m[0]);
if (!defined('ABSPATH')) define('ABSPATH', __DIR__ . '/');
$GLOBALS['efb_trusted_proxies_test'] = array();
eval('function apply_filters($hook, $value) { return $hook === "efb_trusted_proxies" ? array_merge((array) $value, $GLOBALS["efb_trusted_proxies_test"]) : $value; }');
preg_match('/\tprivate static function upload_row_types_efb\(\) \{.*?\n\t\}\n/s', $src, $m);
$body .= $m[0];
eval('namespace Emsfb; class Harness_Efb { public $id = 1; public $setting; public $efbFunction; function __construct() { $this->efbFunction = new FakeFunctions_Efb(); } ' . $body . '}');
$h = new Harness_Efb();

/* 1. Same-site URL */
$cases = array(
	array('127.0.0.1:18170', 'http://127.0.0.1:18170/?page_id=4', true),
	array('127.0.0.1:18170', 'http://127.0.0.1:18170.evil.tld/', false),
	array('127.0.0.1:18170', 'http://127.0.0.1:18170@evil.tld/', false),
	array('127.0.0.1:18170', 'http://127.0.0.1:9999/', false),
	array('example.com', 'https://example.com/contact/', true),
	array('example.com', 'https://www.example.com/contact/', true),
	array('www.example.com', 'https://example.com/x', true),
	array('example.com', 'https://EXAMPLE.com:443/x', true),
	array('example.com', 'https://example.com.evil.tld/', false),
	array('example.com', 'https://example.com@evil.tld/', false),
	array('example.com', 'javascript://example.com/%0aalert(1)', false),
	array('example.com', '//example.com/x', false),
	array('example.com', 'https://evil.tld/?https://example.com', false),
	array('[::1]:8080', 'http://[::1]:8080/x', true),
);
foreach ($cases as $c) {
	$_SERVER['HTTP_HOST'] = $c[0];
	$GLOBALS['efb_test_home'] = 'http://' . $c[0];
	t_sec(($c[2] ? 'same-site ' : 'other site ') . $c[1], $h->is_same_site_url_efb($c[1]) === $c[2]);
}
/* Behind a proxy the Host header can be internal; home_url() is the public name. */
$_SERVER['HTTP_HOST'] = '10.0.0.5:8080';
$GLOBALS['efb_test_home'] = 'https://www.example.com';
t_sec('proxy with an internal Host: the home_url() host is accepted', $h->is_same_site_url_efb('https://example.com/contact/') === true);
t_sec('proxy with an internal Host: another host is still refused', $h->is_same_site_url_efb('https://example.com.evil.tld/') === false);
/* A proxy that serves the site on another port without forwarding it. */
$_SERVER['HTTP_HOST'] = 'example.com';
$GLOBALS['efb_test_home'] = 'https://example.com';
t_sec('same host on a port neither Host nor home_url names is accepted', $h->is_same_site_url_efb('https://example.com:8443/form/') === true);
t_sec('... but a different host on that port is not', $h->is_same_site_url_efb('https://evil.tld:8443/form/') === false);

/* 2. Register rows */
$fields = array(array('type' => 'register'), array('id_' => '1', 'type' => 'step'),
	array('id_' => 'usernameRegisterEFB', 'type' => 'text', 'name' => 'Username', 'amount' => 3),
	array('id_' => 'emailRegisterEFB', 'type' => 'email', 'name' => 'Email', 'amount' => 4),
	array('id_' => 'passwordRegisterEFB', 'type' => 'password', 'name' => 'Password', 'amount' => 5),
	array('id_' => 'ph1', 'type' => 'mobile', 'name' => 'Phone', 'amount' => 6));
$submitted = array(
	array('id_' => 'usernameRegisterEFB', 'name' => '<b>Spoofed</b>', 'type' => 'r_matrix', 'value' => 'bob<img src=x onerror=alert(1)>', 'amount' => 3, 'evil' => 'x'),
	array('id_' => 'emailRegisterEFB', 'name' => 'Email', 'type' => 'email', 'value' => 'bob@example.com', 'amount' => 4),
	array('id_' => 'passwordRegisterEFB', 'name' => 'Password', 'type' => 'password', 'value' => 'Plain-Text-Secret1', 'amount' => 5),
	array('id_' => 'ph1', 'name' => 'Phone', 'type' => 'mobile', 'value' => '+15551234567', 'amount' => 6),
	array('id_' => 'ghost', 'name' => 'x', 'type' => 'media', 'value' => '@file@', 'url' => 'javascript:alert(1)'),
	array('id_' => 'usernameRegisterEFB', 'name' => 'dup', 'type' => 'text', 'value' => 'second'));
$_SERVER['HTTP_HOST'] = 'site.test';
$GLOBALS['efb_test_home'] = 'https://site.test';
$rows = $h->sanitize_account_form_rows_efb($submitted, $fields, 'https://site.test/register');
$json = json_encode($rows);
t_sec('register entry never stores the password', strpos($json, 'Plain-Text-Secret1') === false && strpos($json, 'passwordRegisterEFB') === false);
t_sec('register entry keeps username, email and extra form fields', count($rows) === 4 && $rows[0]['value'] === 'bob' && $rows[1]['value'] === 'bob@example.com' && $rows[2]['value'] === '+15551234567');
t_sec('register entry takes type and name from the saved form', $rows[0]['type'] === 'text' && $rows[0]['name'] === 'Username' && !isset($rows[0]['evil']));
t_sec('register entry drops rows for fields the form does not have', strpos($json, 'ghost') === false && strpos($json, 'javascript') === false);
t_sec('register entry keeps one row per field', strpos($json, 'second') === false);
t_sec('register entry ends with the page link', end($rows)['type'] === 'w_link' && end($rows)['value'] === 'https://site.test/register');
$rows = $h->sanitize_account_form_rows_efb($submitted, $fields, 'https://evil.tld/phish');
t_sec('register entry replaces a page link on another site with home_url()', end($rows)['value'] === 'https://site.test/');

/* 3. Email content */
$attack = array(
	array('id_' => 'a', 'type' => 'color', 'name' => 'Message', 'value' => '<a href="https://evil.example/login">Verify</a>', 'amount' => '1'),
	array('id_' => 'b', 'type' => 'payment', 'name' => 'p', 'paymentGateway' => 'stripe', 'paymentIntent' => '<b>REFUND</b>', 'paymentmethod' => 'x', 'interval' => '<i>i</i>', 'paymentCreated' => '<u>d</u>', 'amount' => '2'),
	array('type' => 'w_link', 'value' => 'https://site.test/map', 'amount' => -1));
$out = $h->email_get_content_efb($attack, 'TRK1');
t_sec('email: a color value cannot inject markup', strpos($out, '<a href="https://evil.example') === false && !preg_match('/background:[^"]*evil/', $out));
t_sec('email: payment fields are escaped', strpos($out, '<b>REFUND</b>') === false && strpos($out, '<i>i</i>') === false && strpos($out, '<u>d</u>') === false);
$legit = array(
	array('id_' => 'c', 'type' => 'color', 'name' => 'Color', 'value' => '#ff8800', 'amount' => 1),
	array('id_' => 't', 'type' => 'text', 'name' => 'Name', 'value' => 'Line one@efb@nq#line two', 'amount' => 2),
	array('id_' => 'p', 'type' => 'payment', 'name' => 'p', 'paymentGateway' => 'stripe', 'paymentIntent' => 'pi_3Abc', 'paymentmethod' => 'charge', 'paymentAmount' => 12.5, 'paymentcurrency' => 'usd', 'paymentCreated' => '2026-11-04 10:00', 'amount' => 3),
	array('type' => 'w_link', 'value' => 'https://site.test/contact', 'amount' => -1));
$out = $h->email_get_content_efb($legit, 'TRK2');
t_sec('email: a real color still shows its swatch', strpos($out, 'background:#ff8800') !== false);
t_sec('email: line breaks and payment details still render', strpos($out, 'Line one<br>line two') !== false && strpos($out, '<span>pi_3Abc</span>') !== false);
/* The visitor's double quote (stored as @efb@dq#) reaches the email as a
 * quote, escaped - not as two apostrophes - and cannot open markup. */
$out = $h->email_get_content_efb(array(
	array('id_' => 'q', 'type' => 'text', 'name' => 'Say @efb@dq#hi@efb@dq#', 'value' => 'He said @efb@dq#hello@efb@dq# @efb@dq#><img src=x onerror=alert(1)>', 'amount' => 1),
	array('type' => 'w_link', 'value' => 'https://site.test/', 'amount' => -1)), 'TRK3');
t_sec('email: a visitor\'s double quote shows as a quote', strpos($out, 'He said &quot;hello&quot;') !== false && strpos($out, "''hello''") === false);
t_sec('email: the field label decodes the quote too', strpos($out, 'Say &quot;hi&quot;') !== false);
t_sec('email: a quote token cannot open markup', strpos($out, '<img src=x') === false);

/* 4. Reply-To, visitor IP, plan-locked required fields */
$rows = array(array('id_' => 'friend', 'type' => 'email', 'value' => 'friend@example.com'), array('id_' => 'me', 'type' => 'email', 'value' => 'me@example.com'));
t_sec('Reply-To uses the form\'s visitor-email field', $h->efb_submitter_email_efb($rows, 'me') === 'me@example.com');
t_sec('Reply-To falls back to the first valid email field', $h->efb_submitter_email_efb($rows, '') === 'friend@example.com' && $h->efb_submitter_email_efb($rows, 'missing') === 'friend@example.com');
/* Forwarded headers are spoofable: without a trusted proxy the connection
 * address wins, whatever Client-IP / X-Forwarded-For say. */
foreach (array('203.0.113.5:54321', '[2001:db8::1]:443', '203.0.113.5, 10.0.0.1', '<script>') as $c) {
	$_SERVER['HTTP_CLIENT_IP'] = '203.0.113.77'; $_SERVER['HTTP_X_FORWARDED_FOR'] = $c; $_SERVER['REMOTE_ADDR'] = '198.51.100.9';
	t_sec('untrusted peer: visitor IP ignores "' . $c . '"', $h->get_ip_address() === '198.51.100.9');
}
/* Behind a declared proxy the forwarded chain is read and normalised. */
$GLOBALS['efb_trusted_proxies_test'] = array('198.51.100.9', '10.0.0.0/8');
foreach (array(array('203.0.113.5:54321', '203.0.113.5'), array('[2001:db8::1]:443', '2001:db8::1'), array('203.0.113.5, 10.0.0.1', '203.0.113.5'), array('<script>', '198.51.100.9')) as $c) {
	$_SERVER['HTTP_CLIENT_IP'] = '203.0.113.77'; $_SERVER['HTTP_X_FORWARDED_FOR'] = $c[0]; $_SERVER['REMOTE_ADDR'] = '198.51.100.9';
	t_sec('trusted proxy: visitor IP from "' . $c[0] . '"', $h->get_ip_address() === $c[1]);
}
$GLOBALS['efb_trusted_proxies_test'] = array();
$req_fields = array(array('type' => 'form'), array('id_' => 'n', 'type' => 'text', 'required' => 1, 'name' => 'Name'),
	array('id_' => 'sig', 'type' => 'esign', 'required' => 1, 'name' => 'Sign'), array('id_' => 'm', 'type' => 'mobile', 'required' => true, 'name' => 'Mobile'));
$only_name = array(array('id_' => 'n', 'type' => 'text', 'value' => 'Ali'));
$r = $h->validate_required_fields_present_efb($req_fields, $only_name, false);
t_sec('Free site: a required Pro field shown as the plan notice does not block the form', $r['valid'] === true);
$r = $h->validate_required_fields_present_efb($req_fields, $only_name, true);
t_sec('Pro site: the same field is still required', $r['valid'] === false && $r['missing_field'] === 'sig');
$r = $h->validate_required_fields_present_efb($req_fields, array(), false);
t_sec('Free site: ordinary required fields are still required', $r['valid'] === false && $r['missing_field'] === 'n');

/* Stored labels come from the saved form (found on the live staging run: a
 * crafted name emptied the label in the viewer and the CSV header). */
$lbl_fields = array(array('type' => 'form'), array('id_' => 'fn', 'type' => 'text', 'name' => 'First name'),
	array('id_' => 'cb', 'type' => 'checkbox', 'name' => 'Topics'), array('id_' => 'op1', 'type' => 'option', 'parent' => 'cb', 'value' => 'Bugs'),
	array('id_' => 'm1', 'type' => 'r_matrix', 'value' => 'Speed'), array('id_' => 'pay', 'type' => 'payment', 'name' => 'Pay'));
t_sec('label of a text row comes from the saved field', $h->stored_row_name_efb(array('id_' => 'fn', 'name' => '<img src=x onerror=alert(7)>'), $lbl_fields[1], $lbl_fields) === 'First name');
t_sec('label of an option row comes from its parent field', $h->stored_row_name_efb(array('id_' => 'cb', 'id_ob' => 'op1', 'name' => 'Spoofed'), $lbl_fields[3], $lbl_fields) === 'Topics');
t_sec('matrix rows keep the validator\'s label', $h->stored_row_name_efb(array('id_' => 'tm', 'name' => 'Speed'), $lbl_fields[4], $lbl_fields) === 'Speed');
t_sec('payment catch-all keeps its own name', $h->stored_row_name_efb(array('id_' => 'x', 'name' => 'Gateway row'), $lbl_fields[5], $lbl_fields) === 'Gateway row');
t_sec('the form path stores that label', strpos($src, "\$validated_item['name'] = \$this->stored_row_name_efb(\$validated_item, \$f, \$form_fields_array);") !== false);
$rh_src = file_get_contents(__DIR__ . '/../public/assets/js/request-health-efb.js');
t_sec('runtime prepare maps the confirmation page\'s -1 to form 0', strpos($rh_src, 'if (fid<0) fid=0;') !== false);
t_sec('tracker reCAPTCHA is drawn only with a site key (PHP render)', strpos($src, "if (!empty(\$valstng->siteKey) && is_string(\$valstng->siteKey) && isset(\$valstng->scaptcha) && \$valstng->scaptcha==true ){") !== false);

/* Payment secrets are saved as typed (found on the live staging run: the
 * settings reader skipped type="password", so Save stored "NotFoundEl" as the
 * Stripe/PayPal secret and every payment failed with HTTP 401). */
$list_js = file_get_contents(__DIR__ . '/../includes/admin/assets/js/list_form-efb.js');
t_sec('settings reader reads password fields', strpos($list_js, 'el.type == "text" || el.type == "password"') !== false);
$admin_src = file_get_contents(__DIR__ . '/../includes/admin/class-Emsfb-admin.php');
t_sec('settings save never stores the NotFoundEl marker as a key', strpos($admin_src, "\$m[\$secret_key_efb] === 'NotFoundEl'") !== false);
$resolve = substr($src, strpos($src, 'private function resolve_payment_key_efb('), 2000);
t_sec('payments never use the NotFoundEl marker as a key', substr_count($resolve, "\$value !== 'NotFoundEl'") === 4);

/* 5. Source wiring */
$reply = substr($src, strpos($src, 'public function set_rMessage_id_Emsfb_api('), 9000);
t_sec('reply rows are rebuilt from an allow-list', strpos($reply, "'type'    => 'text'") !== false && strpos($reply, "'type'    => 'allformat'") !== false && strpos($reply, '$message = $rebuilt_efb;') !== false);
t_sec('reply attachments must be same-site uploads', strpos($reply, '$this->is_same_site_url_efb($row_url_efb)') !== false);
t_sec('form emails are built from the validated rows', strpos($src, '$this->email_get_content_efb($validated_items, $track_code)') !== false
	&& substr_count($src, 'email_status_efb($form_fields_array,$validated_items,$track_code)') === 3);
t_sec('visitor recipient comes from a validated email row', strpos($src, 'foreach ($validated_items as $item) {') !== false && strpos($src, 'is_email($candidate_efb)') !== false);
t_sec('register entry is rebuilt before it is stored', strpos($src, '$this->sanitize_account_form_rows_efb($submitted_values, $form_fields_array, $url)') !== false);

echo "\n========================================\nRESULTS: $pass passed, $fail failed\n========================================\n";
exit($fail > 0 ? 1 : 0);
