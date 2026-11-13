<?php
/**
 * PAY00 readiness (owner request: "a standard solution for a form that can never take payment").
 *  - a form gets a computed state ready / not ready + blocking issues (code, level, m, field_id,
 *    gateway, fix), read live from the form and the gateway settings: removing the keys makes it not
 *    ready, adding them back makes the same stored form ready again without saving it;
 *  - a payment field whose gateway add-on is not active (catalogue type, not registered) and a plan
 *    without payments are blocking issues too;
 *  - Service::create() refuses a form that is not ready with pay_not_ready and stores nothing;
 *  - the public field shows the visitor's sentence (no detail) and, for a user who can fix it, the
 *    issues with their links; the save answer carries payment_ready / payment_issues; the read-only
 *    REST call answers for a stored form and for a draft; the admin notice and the forms-list data
 *    list the forms that are not ready.
 * Before this change Service::create() answered pay_unavailable after the plan / keys checks only,
 * and a currency or form-type blocker went through to the provider.
 *
 * Run: php tests/test-pay-readiness.php
 */
define('ABSPATH', __DIR__ . '/');
$passed = 0; $failed = 0;
require __DIR__ . '/pay-test-db.php';
$GLOBALS['efb_can'] = false;
if (!function_exists('current_user_can')) { function current_user_can($c) { return !empty($GLOBALS['efb_can']); } }
if (!function_exists('admin_url')) { function admin_url($p = '') { return 'https://site.example/wp-admin/' . $p; } }
if (!function_exists('_n')) { function _n($a, $b, $n, $d = null) { return $n == 1 ? $a : $b; } }
if (!class_exists('WP_REST_Response')) { class WP_REST_Response { public $d; public $s; public function __construct($d = null, $s = 200) { $this->d = $d; $this->s = $s; } public function get_data() { return $this->d; } public function get_status() { return $this->s; } } }
$db = efb_pay_env();
use Emsfb\Payments\Readiness;
use Emsfb\Payments\Service;
use Emsfb\Payments\Frontend;
$t = 'efb_pay_t';
$codes = function ($r) { return array_column($r['issues'], 'code'); };

$plain = array(array('type' => 'form'), array('id_' => 'a', 'type' => 'text', 'name' => 'Name'));
$r = Readiness::of_form($plain);
$t('a form without a payment field: has_payment false, ready', array($r['has_payment'], $r['ready'], $r['issues']), array(false, true, array()));

$form = efb_pay_form();
$fid = $db->add_form($form, 'payment');
$r = Readiness::of_form($form, $fid);
$t('the test payment form with keys: ready, no issue', array($r['has_payment'], $r['ready'], $r['issues']), array(true, true, array()));

// Keys removed (option emptied): not ready, live.
$opt = \Emsfb\Payments\Settings::option_name('efbmock');
$saved = $GLOBALS['opts'][$opt];
$GLOBALS['opts'][$opt] = array();
$r = Readiness::of_form($form, $fid);
$t('keys removed: not ready, pay_not_configured', array($r['ready'], $codes($r)), array(false, array('pay_not_configured')));
$is = $r['issues'][0];
$t('...the issue carries level, field, gateway, words and the settings link', array($is['level'], $is['field_id'], $is['gateway'], strpos($is['m'], 'no keys') !== false, $is['fix']['kind'], strpos($is['fix']['url'], 'page=Emsfb_payments&tab=gateways') !== false),
	array('error', 'pf', 'efbmock', true, 'settings', true));
$t('...the stored form reads the same (for_form_id)', $codes(Readiness::for_form_id($fid)), array('pay_not_configured'));

// Server refusal before anything is stored.
$n = $db->count('wp_emsfb_msg_');
$stored = 0;
$res = efb_pay_create($db, null, array('store_entry' => function () use (&$stored) { $stored++; return array('track' => 'X', 'msg_id' => 1); }));
$t('Service::create on a form that is not ready: pay_not_ready with the visitor sentence', array($res['success'], $res['code'], $res['m']), array(false, 'pay_not_ready', Readiness::visitor_text()));
$t('...nothing stored, no entry callback', array($stored, $db->count('wp_emsfb_msg_') - $n >= 0 && $stored === 0), array(0, true));
$t('...visitor sentence as the owner asked', Readiness::visitor_text(), 'Online payment for this form is not available right now. Please try again later or contact the site.');

// Public markup: the visitor's sentence only; Send / Pay blocked.
$GLOBALS['efb_can'] = false;
$html = Frontend::field_markup($form[6], $form[0], $fid, $form);
$t('public field while not ready: the notice with data-efb-pay-blocked, no Pay button', array(strpos($html, 'data-efb-pay-blocked="1"') !== false, strpos($html, 'not available right now') !== false, strpos($html, 'data-pf-pay') === false), array(true, true, true));
$t('...a visitor reads no detail (no key, no gateway words, no link)', array(strpos($html, 'no keys'), strpos($html, 'wp-admin'), strpos($html, 'data-efb-pay-admin')), array(false, false, false));
$GLOBALS['efb_can'] = true;
$html = Frontend::field_markup($form[6], $form[0], $fid, $form);
$t('...a user who can fix it also reads the issue and the link to the gateway settings', array(strpos($html, 'data-efb-pay-admin="1"') !== false, strpos($html, 'data-code="pay_not_configured"') !== false, strpos($html, 'page=Emsfb_payments&amp;tab=gateways') !== false || strpos($html, 'page=Emsfb_payments&tab=gateways') !== false), array(true, true, true));
$GLOBALS['efb_can'] = false;

// Save answer.
$ans = Readiness::save_answer(array('success' => true), $form, $fid);
$t('save answer: payment_ready false, payment_issues, the dialog title', array($ans['payment_ready'], array_column($ans['payment_issues'], 'code'), $ans['payment_title']), array(false, array('pay_not_configured'), 'Saved, but visitors cannot pay yet:'));
$t('save answer of a form without payment is unchanged', Readiness::save_answer(array('success' => true), $plain, 1), array('success' => true));

// Admin: the forms that are not ready.
$nr = Readiness::not_ready_forms();
$t('not_ready_forms lists the stored form with its issues', array(isset($nr[$fid]), isset($nr[$fid]) ? array_column($nr[$fid]['issues'], 'code') : null), array(true, array('pay_not_configured')));
$note = Readiness::admin_notice_html($nr);
$t('...the admin notice names the form and links to it in the builder', array(strpos($note, 'Payment setup incomplete') !== false, strpos($note, 'state=edit-form&id=' . $fid) !== false), array(true, true));
$t('...no notice without such forms', Readiness::admin_notice_html(array()), '');

// Keys added again: the same stored form is ready without being saved again.
$GLOBALS['opts'][$opt] = $saved;
$t('keys back: the same stored form is ready (nothing re-saved)', array(Readiness::for_form_id($fid)['ready'], isset(Readiness::not_ready_forms()[$fid])), array(true, false));
$res = efb_pay_create($db);
$t('...and the payment starts', array($res['success'], $res['pay']['next']['type']), array(true, 'redirect'));
$html = Frontend::field_markup($form[6], $form[0], $fid, $form);
$t('...and the public field is the payment field again', array(strpos($html, 'data-efb-pay-blocked'), strpos($html, 'data-pf-pay') !== false), array(false, true));

// Other blockers come from the save checks of level "error" (warnings stay warnings).
$bad = $form; $bad[6]['pf_fixed'] = '25000';
$rb = Readiness::of_form($bad, 7);
$t('a fixed amount above the gateway maximum: not ready (pay_amount_limit), fix in the field', array($rb['ready'], $codes($rb), $rb['issues'][0]['fix']['kind'], $rb['issues'][0]['fix']['field_id'], strpos($rb['issues'][0]['fix']['url'], 'state=edit-form&id=7') !== false), array(false, array('pay_amount_limit'), 'field', 'pf', true));
$bad = $form; $bad[0]['type'] = 'survey';
$t('a survey form with a payment field: not ready (pay_form_type)', $codes(Readiness::of_form($bad)), array('pay_form_type'));
$res = efb_pay_create($db, null, array('form' => $bad));
$t('...and Service::create refuses it (before: the payment went to the provider)', $res['code'], 'pay_not_ready');
$test_mode = Readiness::of_form($form);
$t('Test mode alone (info) keeps the form ready', $test_mode['ready'], true);

// Plan.
$GLOBALS['opts']['emsfb_pro'] = 2;
$t('a plan without payments: not ready (pay_plan)', in_array('pay_plan', $codes(Readiness::of_form($form)), true), true);
$GLOBALS['opts']['emsfb_pro'] = 3;

// The payment field of a gateway add-on that is not active (a catalogue folder that registered no gateway).
$types = Readiness::catalogue_types();
$off = $types[0];
$t('the catalogue lists the seven gateway add-ons by field type', count($types), 7);
$t('...a catalogue type is a payment type even when its add-on is off', array(\Emsfb\Payments\Registry::has($off), Readiness::is_payment_type($off), Readiness::is_payment_type('text')), array(false, true, false));
$gone = array(array('type' => 'payment', 'getway' => $off, 'currency' => 'EUR'), array('id_' => 'g1', 'type' => $off, 'name' => 'Pay', 'step' => 1));
$r = Readiness::of_form($gone, 12);
$t('add-on off: has_payment, not ready, pay_addon_inactive with the add-ons link', array($r['has_payment'], $r['ready'], $codes($r), $r['issues'][0]['fix']['kind'], $r['issues'][0]['field_id'], $r['issues'][0]['addon'] !== ''), array(true, false, array('pay_addon_inactive'), 'addons', 'g1', true));
$t('...the save answer adds it to warnings too (the builder alert and AutoPilot)', in_array('pay_addon_inactive', array_column(Readiness::save_answer(array('success' => true), $gone, 12)['warnings'], 'code'), true), true);
$res = Service::create(array('form_id' => 12, 'form' => $gone, 'rows' => array(), 'field_id' => 'g1', 'attempt_key' => '11111111-2222-4333-8444-555555555599', 'store_entry' => function () { return null; }));
$t('...Service::create: pay_not_ready', $res['code'], 'pay_not_ready');

// REST (read only).
$req = new WP_REST_Request('GET');
$req->set_param('form_id', $fid);
$GLOBALS['efb_can'] = true;
$out = Readiness::rest($req);
$t('REST GET form_id: the stored form', array($out->get_status(), $out->get_data()['form_id'], $out->get_data()['ready'], $out->get_data()['has_payment']), array(200, $fid, true, true));
$req = new WP_REST_Request('POST');
$req->set_param('form', $gone);
$out = Readiness::rest($req);
$t('REST POST form (a draft): its readiness, nothing stored', array($out->get_status(), $out->get_data()['ready'], array_column($out->get_data()['issues'], 'code')), array(200, false, array('pay_addon_inactive')));
$req = new WP_REST_Request('GET');
$req->set_param('form_id', 999999);
$t('REST unknown form: 404', Readiness::rest($req)->get_status(), 404);
$t('REST permission: only users who can edit forms or payments', array(Readiness::rest_can(), ($GLOBALS['efb_can'] = false) || Readiness::rest_can()), array(true, false));
$GLOBALS['efb_routes'] = array();
\Emsfb\Payments\Routes::register(new stdClass());
$t('the readiness route is registered (GET and POST)', isset($GLOBALS['efb_routes']['Emsfb/v1/payments/readiness']) ? $GLOBALS['efb_routes']['Emsfb/v1/payments/readiness']['methods'] : null, array('GET', 'POST'));

// Capability registry.
$cap = \Emsfb\Builder_Api::capabilities();
$t('capabilities: payments.readiness names the REST call, the builder call and the save keys', array(isset($cap['payments']['readiness']['rest']), $cap['payments']['readiness']['builder'], $cap['payments']['readiness']['save_answer'], $cap['payments']['readiness']['visitor_code']),
	array(true, 'efbBuilderApi.paymentReadiness', array('payment_ready', 'payment_issues'), 'pay_not_ready'));

// The filter can add an issue (e.g. a site rule).
add_filter('efb_payment_readiness', function ($o) { $o['issues'][] = array('code' => 'site_rule', 'level' => 'error', 'm' => 'x', 'field_id' => '', 'gateway' => ''); $o['ready'] = false; return $o; });
$t('efb_payment_readiness can add an issue', $codes(Readiness::of_form($form)), array('site_rule'));

echo "\nRESULTS: {$passed} passed, {$failed} failed\n";
exit($failed ? 1 : 0);
