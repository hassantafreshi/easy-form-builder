<?php
/**
 * The Payments screen (includes/payments/class-Emsfb-payment-admin.php), rendered without WordPress against the SQLite
 * test database. QA-3's findings (2026-11-25), each check fails on 4.3.2 as QA-3 found it:
 *
 *  QA3-005  no gateway add-on active while the payments table has rows: Payments stays in the menu, read only (the list
 *           and each payment); a payment's actions need its gateway and the screen says so; the AJAX actions refuse
 *           with that message; with no rows and no gateway nothing is there (test-pay-no-gateway.php);
 *  QA3-008  the list is WordPress' list table (cards on a phone): column-primary, toggle-row, data-colname per cell;
 *  QA3-011  "TEST" / "LIVE" and the "(Test)" / "(Live)" of the gateway settings go through gettext;
 *  QA3-016  a filter that matches nothing says so ("No payments match these filters."), not "No payments yet.";
 *  QA3-018  the per-currency Paid totals in the gateway's exponent (a gateway that counts IDR without decimals);
 *  QA3-013  Money::format() in Arabic keeps "US$" / "JP¥" in their order (the sign isolated, U+2068 … U+2069);
 *  QA4-003  "Moved forms": the forms on the add-ons' new fields and "Move every form back".
 *
 * Run: php tests/test-pay-admin-screen.php
 */
define('ABSPATH', __DIR__ . '/');
$passed = 0; $failed = 0;
// Translations of the strings under test (a stand-in for the language files): gettext is used, not literals.
$GLOBALS['efb_tr'] = array('payment mode badge' . "\4" . 'TEST' => 'تجربة', 'payment mode badge' . "\4" . 'LIVE' => 'حقيقي', 'Live' => 'مباشر', 'Test' => 'آزمایشی');
$GLOBALS['efb_tr_on'] = false;
function efb_tr($s, $ctx = '') { $k = ($ctx !== '' ? $ctx . "\4" : '') . $s; return !empty($GLOBALS['efb_tr_on']) && isset($GLOBALS['efb_tr'][$k]) ? $GLOBALS['efb_tr'][$k] : $s; }
function __($s, $d = null) { return efb_tr($s); }
function esc_html__($s, $d = null) { return htmlspecialchars(efb_tr($s), ENT_QUOTES); }
function esc_attr__($s, $d = null) { return htmlspecialchars(efb_tr($s), ENT_QUOTES); }
function _x($s, $c, $d = null) { return efb_tr($s, $c); }
function _n($a, $b, $n, $d = null) { return $n == 1 ? $a : $b; }
require __DIR__ . '/pay-test-db.php';
$t = 'efb_pay_t';

$GLOBALS['efb_menu'] = array();
if (!function_exists('add_submenu_page')) { function add_submenu_page($p, $a, $b, $cap, $slug) { $GLOBALS['efb_menu'][] = $slug; return $slug; } }
if (!function_exists('current_user_can')) { function current_user_can($c) { return true; } }
if (!function_exists('admin_url')) { function admin_url($p = '') { return 'https://site.example/wp-admin/' . $p; } }
if (!function_exists('wp_die')) { function wp_die($m = '') { throw new Exception('wp_die'); } }
if (!function_exists('selected')) { function selected($a, $b, $e = true) { return (string) $a === (string) $b ? ' selected="selected"' : ''; } }
if (!function_exists('checked')) { function checked($a, $b, $e = true) { return (string) $a === (string) $b ? ' checked="checked"' : ''; } }
if (!function_exists('get_user_locale')) { function get_user_locale() { return isset($GLOBALS['efb_user_locale']) ? $GLOBALS['efb_user_locale'] : 'en_US'; } }
if (!function_exists('wp_create_nonce')) { function wp_create_nonce($a) { return 'nonce'; } }
if (!function_exists('wp_nonce_field')) { function wp_nonce_field($a) { echo '<input type="hidden" name="_wpnonce" value="nonce">'; } }
if (!function_exists('check_ajax_referer')) { function check_ajax_referer($a, $b = false, $d = true) { return true; } }
if (!function_exists('wp_print_inline_script_tag')) { function wp_print_inline_script_tag($js) { echo '<script>' . $js . '</script>'; } }
if (!function_exists('number_format_i18n')) { function number_format_i18n($n, $d = 0) { return number_format((float) $n, $d); } }
if (!function_exists('absint')) { function absint($n) { return abs((int) $n); } }
if (!function_exists('wp_unslash')) { function wp_unslash($v) { return is_string($v) ? stripslashes($v) : $v; } }
if (!function_exists('wp_strip_all_tags')) { function wp_strip_all_tags($s) { return strip_tags((string) $s); } }
if (!function_exists('get_current_screen')) { function get_current_screen() { return null; } }
if (!function_exists('wp_send_json_error')) { function wp_send_json_error($d = null, $s = null) { throw new EfbJsonAnswer(false, $d); } }
if (!function_exists('wp_send_json_success')) { function wp_send_json_success($d = null, $s = null) { throw new EfbJsonAnswer(true, $d); } }
class EfbJsonAnswer extends Exception { public $ok; public $data; public function __construct($ok, $d) { parent::__construct('json'); $this->ok = $ok; $this->data = $d; } }

efb_pay_test_load();
require_once __DIR__ . '/../includes/payments/class-Emsfb-payment-frontend.php';
require_once __DIR__ . '/../includes/payments/class-Emsfb-payment-legacy-migration.php';
require_once __DIR__ . '/../includes/payments/class-Emsfb-payment-admin.php';
use Emsfb\Payments\Admin;
use Emsfb\Payments\Registry;
use Emsfb\Payments\Store;
use Emsfb\Payments\Money;

$db = $GLOBALS['wpdb'] = new EfbPayTestDb();
$db->create_payment_tables();
Registry::reset();
$page = function ($get = array()) { $_GET = $get; ob_start(); try { Admin::page(); } catch (Exception $e) { ob_end_clean(); return 'DIED'; } return ob_get_clean(); };
// Admin::has_payments() keeps its answer for the request: each block below is "a request" of its own (a new process).
// Store::insert() always starts a payment at "created": the status a test needs is written after.
$pay = function ($row) use (&$db) { $st = isset($row['status']) ? $row['status'] : 'created'; $p = Store::insert($row + array('attempt_key' => 'k-' . $row['track'])); $id = is_array($p) ? (int) $p['id'] : (int) $p; $db->update('wp_emsfb_pmt_', array('status' => $st), array('id' => $id)); return $id; };

/* ------------------------------------------------------------- QA3-005: no gateway add-on, payments in the table */
echo "\n=== QA3-005 read only without a gateway add-on\n";
$pid = $pay(array('form_id' => 7, 'gateway' => 'stripecheckout', 'mode' => 'live', 'status' => 'paid', 'amount_minor' => 2500, 'currency' => 'USD', 'track' => 'EFB12345', 'payer_email' => 'payer@example.test', 'method' => 'card'));
Admin::menu();
$t('QA3-005 no gateway add-on but 1 payment: Payments is in the menu (old code: none, the screen answered 403)', array(Admin::active(), Admin::has_payments(), $GLOBALS['efb_menu']), array(false, true, array('Emsfb_payments')));
$html = $page(array('page' => 'Emsfb_payments'));
$t('QA3-005 the list opens, says it is read only and why, with the link to Add-ons', array($html !== 'DIED', strpos($html, 'data-code="pay_read_only"') !== false, strpos($html, 'EFB12345') !== false, strpos($html, 'page=Emsfb_addon') !== false), array(true, true, true, true));
$det = $page(array('page' => 'Emsfb_payments', 'payment' => (string) $pid));
$t('QA3-005 a payment\'s details open; its refund needs its gateway, said plainly; no Refund, Check or Mark-as-refunded button', array($det !== 'DIED', strpos($det, 'data-code="pay_gateway_inactive"') !== false, strpos($det, 'data-efb-pay="refund"') === false, strpos($det, 'data-efb-pay="recheck"') === false, strpos($det, 'data-efb-pay="mark_refunded"') === false, strpos($det, 'Events') !== false), array(true, true, true, true, true, true));
$_POST = array('do' => 'refund', 'id' => (string) $pid, 'amount' => '25', 'nonce' => 'nonce');
try { Admin::ajax(); $ans = null; } catch (EfbJsonAnswer $e) { $ans = $e; }
$t('QA3-005 the AJAX refund refuses with "turn the add-on on first" (pay_gateway_inactive)', array($ans && !$ans->ok, $ans ? $ans->data['code'] : null, $ans ? strpos($ans->data['m'], 'add-on on first') !== false : false), array(true, 'pay_gateway_inactive', true));

/* ------------------------------------------------------------- QA3-008 / QA3-011 / QA3-016 */
echo "\n=== QA3-008 the list at phone width, QA3-011 the mode words, QA3-016 no match\n";
$t('QA3-008 the list is WordPress\' list table: table-view-list, the date as column-primary with toggle-row, data-colname per cell', array(strpos($html, 'wp-list-table widefat fixed striped table-view-list efb-pay-table') !== false, strpos($html, 'column-date column-primary') !== false, strpos($html, 'class="toggle-row"') !== false, substr_count($html, 'data-colname='), strpos($html, 'data-colname="Amount"') !== false), array(true, true, true, 9, true));
$GLOBALS['efb_tr_on'] = true;
$tr = $page(array('page' => 'Emsfb_payments'));
$GLOBALS['efb_tr_on'] = false;
$t('QA3-011 "TEST" and "LIVE" through gettext (context "payment mode badge"): the filter and the row in the site\'s language', array(strpos($tr, '>تجربة</option>') !== false, strpos($tr, '>حقيقي</option>') !== false, substr_count($tr, 'حقيقي') >= 2, strpos($tr, '>TEST<') === false && strpos($tr, '>LIVE<') === false), array(true, true, true, true));
$none = $page(array('page' => 'Emsfb_payments', 'status' => 'refunded'));
$t('QA3-016 a filter that matches nothing: "No payments match these filters." and "Show all payments" (old: "No payments yet.")', array(strpos($none, 'No payments match these filters.') !== false, strpos($none, 'No payments yet.') === false, strpos($none, 'Show all payments') !== false), array(true, true, true));
// With a gateway and an empty table: "No payments yet." stays for the real empty list.
require_once __DIR__ . '/payments-mock/class-Emsfb-payment-mock.php';
add_action('efb_register_payment_gateways', function ($reg) { $reg->register(new \Emsfb\Payments\Mock_Gateway()); });
Registry::reset();
$db->query('DELETE FROM wp_emsfb_pmt_');
$empty = $page(array('page' => 'Emsfb_payments'));
$t('QA3-016 ...no filter and no payment: "No payments yet."', array(strpos($empty, 'No payments yet.') !== false, strpos($empty, 'match these filters') === false), array(true, true));
$GLOBALS['efb_tr_on'] = true;
$gws = $page(array('page' => 'Emsfb_payments', 'tab' => 'gateways'));
$GLOBALS['efb_tr_on'] = false;
$t('QA3-011 the gateway settings\' per-mode labels say "(Test)" / "(Live)" through gettext', array(strpos($gws, '(آزمایشی)') !== false, strpos($gws, '(مباشر)') !== false), array(true, true));

/* ------------------------------------------------------------- QA3-018 the totals in the gateway's exponent */
echo "\n=== QA3-018 Paid totals per currency in the gateway exponent\n";
add_filter('efb_currency_exponent', function ($e, $iso, $gw) { return $iso === 'IDR' && $gw === 'efbmock' ? 0 : $e; }, 10, 3);
$pay(array('form_id' => 9, 'gateway' => 'efbmock', 'status' => 'paid', 'amount_minor' => 150000, 'currency' => 'IDR', 'track' => 'EFB00001'));
$list = $page(array('page' => 'Emsfb_payments'));
preg_match('#<div class="efb-pay-stat"><span>Paid <bdi>IDR</bdi></span><strong><bdi>(.*?)</bdi>#u', $list, $tile);
$t('QA3-018 one gateway that counts IDR without decimals: the "Paid IDR" tile reads IDR 150,000 (old: IDR 1,500.00)', isset($tile[1]) ? $tile[1] : null, "IDR\u{a0}150,000");
$pay(array('form_id' => 9, 'gateway' => 'stripecheckout', 'status' => 'paid', 'amount_minor' => 1000000, 'currency' => 'IDR', 'track' => 'EFB00002'));
$st = Store::stats();
$t('QA3-018 two gateways, two exponents: summed at the larger (150,000 + 10,000.00 = 160,000.00 IDR), formatted with that gateway', array(count($st['paid']), $st['paid'][0]['currency'], $st['paid'][0]['total'], $st['paid'][0]['gateway'], Money::format($st['paid'][0]['total'], 'IDR', 'en_US', $st['paid'][0]['gateway'])), array(1, 'IDR', 16000000, 'stripecheckout', "IDR\u{a0}160,000.00"));
$t('QA3-018 Store::paid_totals(): USD and JPY kept apart, counts added', Store::paid_totals(array(array('currency' => 'JPY', 'gateway' => 'a', 'total' => '500', 'n' => '2'), array('currency' => 'USD', 'gateway' => 'a', 'total' => '1250', 'n' => '1'), array('currency' => 'USD', 'gateway' => 'b', 'total' => '50', 'n' => '3'))), array(array('currency' => 'JPY', 'total' => 500, 'n' => 2, 'gateway' => 'a'), array('currency' => 'USD', 'total' => 1300, 'n' => 4, 'gateway' => 'a')));

/* ------------------------------------------------------------- QA3-013 the sign keeps its order in Arabic */
echo "\n=== QA3-013 currency signs in a right-to-left language\n";
if (class_exists('NumberFormatter')) {
	$us = Money::format(150000, 'USD', 'ar');
	$jp = Money::format(1500, 'JPY', 'ar');
	$t('QA3-013 ar: "US$" and "JP¥" isolated (U+2068 … U+2069) so they do not read "$US" / "¥JP"', array(strpos($us, "\u{2068}US$\u{2069}") !== false, strpos($jp, "\u{2068}JP¥\u{2069}") !== false), array(true, true));
	$t('QA3-013 en_US unchanged ("$1,500.00")', Money::format(150000, 'USD', 'en_US'), '$1,500.00');
} else {
	$t('QA3-013 (intl missing: the code form is used, nothing to isolate)', true, true);
}

/* ------------------------------------------------------------- QA4-003 Moved forms */
echo "\n=== QA4-003 Moved forms\n";
$GLOBALS['opts']['emsfb_pay_legacy_migration'] = array('forms' => array(5 => array('status' => 'migrated', 'reason' => '', 'from' => 'paypal', 'to' => 'paypalcheckout', 'name' => 'Tickets', 'at' => '', 'by' => 'cron', 'events' => array())));
$nav = $page(array('page' => 'Emsfb_payments'));
$mv = $page(array('page' => 'Emsfb_payments', 'tab' => 'downgrade'));
$t('QA4-003 a moved form: the "Moved forms" tab, the warning for older versions, the form, "Move every form back" (nonce, admin-post)', array(strpos($nav, 'tab=downgrade') !== false, strpos($mv, 'Before going back to an older version') !== false, strpos($mv, '<bdi>Tickets</bdi> <bdi>(#5)</bdi>') !== false, strpos($mv, 'Move every form back') !== false, strpos($mv, 'value="efb_pay_legacy_rollback_all"') !== false, strpos($mv, '_wpnonce') !== false), array(true, true, true, true, true, true));
$GLOBALS['opts']['emsfb_pay_legacy_migration'] = array('forms' => array());
$t('QA4-003 nothing moved: no tab in the menu bar', strpos($page(array('page' => 'Emsfb_payments')), 'tab=downgrade') === false, true);

echo "\nRESULTS: {$passed} passed, {$failed} failed\n";
exit($failed ? 1 : 0);
