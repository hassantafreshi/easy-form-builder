<?php
/**
 * PAY00 sub-task E: payments/create (Service::create) and the webhook route, with the test gateway
 * and its fake provider (pre_http_request; nothing leaves the machine).
 *  - the amount sent to the provider is the server quote (a browser price of 0.01 is ignored);
 *  - the entry is stored "pending payment" (read_ 2) with a payment row; the payment row created;
 *  - expected_minor different from the quote → quote_changed, nothing stored;
 *  - the same attempt key again → the first answer (no second payment at the provider);
 *  - webhook: unknown gateway → 200 and no outbound call; bad signature → 401; not ours → 200
 *    without a call; valid → refetch from the provider → paid → finalize once; the same event
 *    twice → no second finalize; provider down → 503 and the event can be delivered again;
 *  - the provider saying a smaller amount → failed (amount_mismatch) + mail to the owner;
 *  - a provider "paid" from a webhook for an unpaid payment is not believed (refetch says open).
 *
 * Run: php tests/test-pay-webhook.php
 */
define('ABSPATH', __DIR__ . '/');
$passed = 0; $failed = 0;
require __DIR__ . '/pay-test-db.php';
$db = efb_pay_env();
use Emsfb\Payments\Service;
use Emsfb\Payments\Store;
use Emsfb\Payments\Mock_Provider;
$t = 'efb_pay_t';
$runs = array();
add_action('efb_payment_run_actions', function ($p, $when) use (&$runs) { $runs[] = $p['uid'] . ':' . $when; });

$res = efb_pay_create($db);
$t('create succeeds with a redirect to the provider page', array($res['success'], $res['pay']['next']['type']), array(true, 'redirect'));
$p = Store::find_by_uid($res['pay']['uid']);
$t('the amount is the server quote: 25.00 + fixed 2.00 = 2700 (browser said 0.01)', array($p['amount_minor'], $p['currency']), array(2700, 'EUR'));
$prov = Mock_Provider::all();
$pp = reset($prov);
$t('the provider was asked for "27.00 EUR"', $pp['amount'], array('value' => '27.00', 'currency' => 'EUR'));
$t('payment pending with the provider reference', array($p['status'], $p['gateway_ref']), array('pending', $pp['id']));
$t('return_to is the page without efb_pay', $p['return_to'], 'https://site.example/pay-page/?x=1');
$t('the provider got the signed return URL and the webhook URL', strpos($pp['redirectUrl'], 'payments/return/efbmock') !== false && strpos($pp['redirectUrl'], 's=' . Service::sign($p['uid'], 'efbmock')) !== false && $pp['webhookUrl'] === 'https://site.example/wp-json/Emsfb/v1/payments/webhook/efbmock', true);
$entry = $db->row('wp_emsfb_msg_', 'msg_id = ' . (int) $p['msg_id']);
$t('the entry is stored as pending payment (read_ 2)', (string) $entry['read_'], '2');
$rows = Service::decode_content($entry['content']);
$payrow = array_values(array_filter($rows, function ($r) { return ($r['type'] ?? '') === 'payment'; }))[0];
$t('the entry has the payment row (pending, gateway, amount, uid)', array($payrow['status'], $payrow['paymentGateway'], $payrow['paymentAmount'], $payrow['pay_uid']), array('pending', 'efbmock', '27.00', $p['uid']));
$t('no secret and no track in the answer for the browser', preg_match('/mock_test|whs_unit/', json_encode($res)) === 0 && !isset($res['pay']['track']), true);
$t('the IP is the one the form may store', $p['ip'], '203.0.113.9');

// quote_changed
$n_before = $db->count('wp_emsfb_pmt_');
$q = efb_pay_create($db, null, array('expected_minor' => 2600));
$t('expected 26.00 but the quote is 27.00 → quote_changed with the server amount', array($q['success'], $q['code'], $q['total_minor']), array(false, 'quote_changed', 2700));
$t('...and nothing was stored', $db->count('wp_emsfb_pmt_'), $n_before);

// replay of the same attempt
$form = efb_pay_form();
$att = '11111111-2222-4333-8444-555555555555';
$store_calls = 0;
$args = array('form_id' => 99, 'form' => $form, 'rows' => array(array('id_' => 'rad', 'id_ob' => 'ro2')), 'field_id' => 'pf', 'attempt_key' => $att, 'page_url' => '', 'store_entry' => function ($rows) use ($db, &$store_calls) { $store_calls++; $id = $db->add_entry(99, $rows, gmdate('Y-m-d H:i:s'), array('read_' => 2)); return array('track' => 'T' . $id, 'msg_id' => $id); });
$a1 = Service::create($args);
$prov_n = count(Mock_Provider::all());
$a2 = Service::create($args);
$t('same attempt key: same payment, no second entry, no second provider payment', array($a2['pay']['uid'] === $a1['pay']['uid'], !empty($a2['replay']), $store_calls, count(Mock_Provider::all())), array(true, true, 1, $prov_n));
$t('a malformed attempt key is refused', Service::create(array_merge($args, array('attempt_key' => 'x')))['code'], 'attempt_used');
$GLOBALS['opts']['emsfb_pro'] = 2;
$t('on Free the framework is closed (proposed default: Free Plus)', Service::create(array_merge($args, array('attempt_key' => '11111111-2222-4333-8444-555555555556')))['code'], 'pay_unavailable');
$GLOBALS['opts']['emsfb_pro'] = 3;
add_filter('efb_payments_required_plan', function () { return 'pro'; });
$t('efb_payments_required_plan can move the level', Service::create(array_merge($args, array('attempt_key' => '11111111-2222-4333-8444-555555555557')))['code'], 'pay_unavailable');
$GLOBALS['efb_filters']['efb_payments_required_plan'] = array();

// Webhooks.
$GLOBALS['efb_http_log'] = array();
$r = efb_pay_webhook_req($p['gateway_ref'], 'evt_1');
$r->set_param('gateway', 'nosuchgw');
$t('unknown gateway: 200, no outbound call', array(Service::handle_webhook($r)->status, count($GLOBALS['efb_http_log'])), array(200, 0));
$t('bad signature: 401', Service::handle_webhook(efb_pay_webhook_req($p['gateway_ref'], 'evt_1', 'x', 'deadbeef'))->status, 401);
$t('a signed event for a payment that is not ours: 200, no outbound call', array(Service::handle_webhook(efb_pay_webhook_req('mck_000000000000', 'evt_other'))->status, count($GLOBALS['efb_http_log'])), array(200, 0));
$t('the provider still says open: webhook → refetch → still pending', array(Service::handle_webhook(efb_pay_webhook_req($p['gateway_ref'], 'evt_open'))->status, Store::get($p['id'])['status']), array(200, 'pending'));
Mock_Provider::set_status($p['gateway_ref'], 'paid');
$resp = Service::handle_webhook(efb_pay_webhook_req($p['gateway_ref'], 'evt_paid'));
$after = Store::get($p['id']);
$t('paid at the provider: webhook → paid, finalized', array($resp->status, $after['status'], $after['finalized_at'] !== null), array(200, 'paid', true));
$t('the paid actions ran once', array_count_values($runs)[$p['uid'] . ':paid'] ?? 0, 1);
$entry = $db->row('wp_emsfb_msg_', 'msg_id = ' . (int) $p['msg_id']);
$rows = Service::decode_content($entry['content']);
$payrow = array_values(array_filter($rows, function ($r) { return ($r['type'] ?? '') === 'payment'; }))[0];
$t('the entry became a new entry (read_ 0) with status paid and the reference', array((string) $entry['read_'], $payrow['status'], $payrow['paymentIntent']), array('0', 'paid', $p['gateway_ref']));
$calls = count($GLOBALS['efb_http_log']);
$t('the same event again: 200, no refetch, no second finalize', array(Service::handle_webhook(efb_pay_webhook_req($p['gateway_ref'], 'evt_paid'))->status, count($GLOBALS['efb_http_log']) - $calls, array_count_values($runs)[$p['uid'] . ':paid']), array(200, 0, 1));
$t('another event of the paid payment: no second finalize either', array(Service::handle_webhook(efb_pay_webhook_req($p['gateway_ref'], 'evt_paid_2'))->status, array_count_values($runs)[$p['uid'] . ':paid']), array(200, 1));

// Provider down → 503, the event can come again.
$r2 = efb_pay_create($db);
$p2 = Store::find_by_uid($r2['pay']['uid']);
Mock_Provider::set_status($p2['gateway_ref'], 'paid');
$GLOBALS['opts']['efb_mock_provider_down'] = 1;
$t('provider unreachable during the webhook: 503', Service::handle_webhook(efb_pay_webhook_req($p2['gateway_ref'], 'evt_down'))->status, 503);
$t('...payment still pending', Store::get($p2['id'])['status'], 'pending');
unset($GLOBALS['opts']['efb_mock_provider_down']);
$t('the provider delivers the same event again: processed now', array(Service::handle_webhook(efb_pay_webhook_req($p2['gateway_ref'], 'evt_down'))->status, Store::get($p2['id'])['status']), array(200, 'paid'));

// Amount mismatch.
$r3 = efb_pay_create($db);
$p3 = Store::find_by_uid($r3['pay']['uid']);
Mock_Provider::set_status($p3['gateway_ref'], 'paid', '1.00');
$GLOBALS['efb_mails'] = array();
Service::handle_webhook(efb_pay_webhook_req($p3['gateway_ref'], 'evt_mm'));
$t('provider paid 1.00 for 27.00: failed, never paid, never finalized', array(Store::get($p3['id'])['status'], Store::get($p3['id'])['finalized_at']), array('failed', null));
$t('...the owner gets a "[TEST] Payment amount does not match" mail', count($GLOBALS['efb_mails']) === 1 && strpos($GLOBALS['efb_mails'][0]['subject'], '[TEST] Payment amount does not match') === 0, true);
$t('...the event log says why', strpos(json_encode(Store::events($p3['id'])), 'amount_mismatch') !== false, true);

// Late success after failure (e.g. bank transfer), only from the provider.
$r4 = efb_pay_create($db);
$p4 = Store::find_by_uid($r4['pay']['uid']);
Mock_Provider::set_status($p4['gateway_ref'], 'failed');
Service::handle_webhook(efb_pay_webhook_req($p4['gateway_ref'], 'evt_f'));
$t('provider failed → failed', Store::get($p4['id'])['status'], 'failed');
Mock_Provider::set_status($p4['gateway_ref'], 'paid');
Service::handle_webhook(efb_pay_webhook_req($p4['gateway_ref'], 'evt_late'));
$t('provider later paid → paid and finalized (failed → paid only from the provider)', array(Store::get($p4['id'])['status'], Store::get($p4['id'])['finalized_at'] !== null), array('paid', true));

// Body too large.
$big = new WP_REST_Request('POST'); $big->set_param('gateway', 'efbmock'); $big->set_body(str_repeat('x', 262145));
$t('a body over 256 KB: 413', Service::handle_webhook($big)->status, 413);
$t('routes: webhook path accepts only [a-z][a-z0-9]{1,19}', (function () { \Emsfb\Payments\Routes::register(new stdClass()); return isset($GLOBALS['efb_routes']['Emsfb/v1/payments/webhook/(?P<gateway>[a-z][a-z0-9]{1,19})']); })(), true);

echo "\nRESULTS: {$passed} passed, {$failed} failed\n";
exit($failed ? 1 : 0);
