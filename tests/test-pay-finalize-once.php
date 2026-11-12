<?php
/**
 * PAY00 §7.5: one payment, many confirmations, one finalize.
 *  - 10 interleaved calls (webhook, return, poll, cron, admin re-check) after the provider says paid →
 *    exactly one "paid" run of the actions, one efb_payment_paid, the entry updated once;
 *  - webhook before the return, and return before the webhook, both end in one finalize;
 *  - finalize of a payment that is not paid does nothing;
 *  - the real _Public::run_payment_actions_efb() (lifted from class-Emsfb-public.php) sends each
 *    channel at the time row 0 asks for: by default e-mails after payment only; "submit" sends at
 *    creation; "both" at both moments; Telegram / Sheet / webhook per channel; the [TEST] subject.
 *
 * Run: php tests/test-pay-finalize-once.php
 */
define('ABSPATH', __DIR__ . '/');
$passed = 0; $failed = 0;
require __DIR__ . '/pay-test-db.php';
$db = efb_pay_env();
use Emsfb\Payments\Service;
use Emsfb\Payments\Store;
use Emsfb\Payments\Mock_Provider;
$t = 'efb_pay_t';
$runs = array(); $paid_hooks = 0;
add_action('efb_payment_run_actions', function ($p, $when) use (&$runs) { $runs[] = $when; });
add_action('efb_payment_paid', function () use (&$paid_hooks) { $paid_hooks++; });

$res = efb_pay_create($db);
$p = Store::find_by_uid($res['pay']['uid']);
Mock_Provider::set_status($p['gateway_ref'], 'paid');
$sig = Service::sign($p['uid'], 'efbmock');
for ($i = 0; $i < 10; $i++) {
	switch ($i % 5) {
		case 0: Service::handle_webhook(efb_pay_webhook_req($p['gateway_ref'], 'evt_' . $i)); break;
		case 1: $r = new WP_REST_Request('GET'); $r->set_param('gateway', 'efbmock'); $r->set_param('p', $p['uid']); $r->set_param('s', $sig); Service::handle_return($r); break;
		case 2: Service::refresh(Store::get($p['id']), 'poll'); break;
		case 3: Service::reconcile(); break;
		case 4: Service::apply_remote_state(Store::get($p['id']), \Emsfb\Payments\Remote_State::of('paid', 2700, 'EUR'), 'admin'); break;
	}
}
$t('ten confirmations from five sources → one paid run of the actions', array_count_values($runs), array('paid' => 1));
$t('one efb_payment_paid', $paid_hooks, 1);
$t('one "finalized" event', count(array_filter(Store::events($p['id']), function ($e) { return $e['type'] === 'finalized'; })), 1);

// Order: webhook first, then return.
$runs = array();
$r1 = efb_pay_create($db); $q1 = Store::find_by_uid($r1['pay']['uid']);
Mock_Provider::set_status($q1['gateway_ref'], 'paid');
Service::handle_webhook(efb_pay_webhook_req($q1['gateway_ref'], 'w1'));
$rr = new WP_REST_Request('GET'); $rr->set_param('gateway', 'efbmock'); $rr->set_param('p', $q1['uid']); $rr->set_param('s', Service::sign($q1['uid'], 'efbmock'));
$loc = Service::handle_return($rr)->headers['Location'];
$t('webhook before return: paid once, return still lands on the page', array($runs, strpos($loc, 'efb_pay=' . $q1['uid']) !== false), array(array('paid'), true));
// Order: return first, then webhook.
$runs = array();
$r2 = efb_pay_create($db); $q2 = Store::find_by_uid($r2['pay']['uid']);
Mock_Provider::set_status($q2['gateway_ref'], 'paid');
$rr->set_param('p', $q2['uid']); $rr->set_param('s', Service::sign($q2['uid'], 'efbmock'));
Service::handle_return($rr);
Service::handle_webhook(efb_pay_webhook_req($q2['gateway_ref'], 'w2'));
$t('return before webhook: paid once', $runs, array('paid'));
$t('finalize of an unpaid payment does nothing', Service::finalize(Store::find_by_uid(efb_pay_create($db)['pay']['uid'])), false);

// The real run_payment_actions_efb().
$src = file_get_contents(__DIR__ . '/../includes/class-Emsfb-public.php');
preg_match('/\tpublic function run_payment_actions_efb\(.*?\n\t\}\n/s', $src, $m);
$t('run_payment_actions_efb is in the public class', !empty($m[0]), true);
function get_efbFunction() { return new class { public function text_efb($k) { return array(); } public $last_sms_error_efb = ''; }; }
function emsfb_is_email_sending_enabled_efb($s) { return true; }
eval('class ActFx { public $id; public $lanText = array("x" => 1); public $efbFunction; public $log = array();
	private function get_submission_settings_efb(&$src) { return array("emailSupporter" => "support@example.test", "sms_config" => "wpsms", "phnNo" => "+491700000000"); }
	public function email_list_efb(&$u, $i, $e, $s) { foreach (explode(",", $e) as $x) { $x = trim($x); if ($x !== "") $u[$i][] = $x; } }
	public function email_status_efb($f, $r, $c) { return array("subject" => "Order", "content" => "c", "type" => "message_link", "visitor_type" => "traking_link"); }
	public function send_email_Emsfb_($to, $track, $pro, $state, $link, $content, $sub) { $this->log[] = array("mail", $to, $sub); }
	private function send_optional_sms_efb() { $this->log[] = array("sms", func_get_args()[1]); }
	private function efb_intgrate_with_3rd_party_services_efb($t, $v, $f, $e, $a) { $this->log[] = array("int", $e, $a["channels"]); }
	' . $m[0] . ' }');
$pay = Store::get($p['id']);
$rows = array(array('id_' => 'em', 'type' => 'email', 'value' => 'payer@example.test'), array('id_' => 'mob', 'type' => 'mobile', 'value' => '+491711111111'), array('id_' => 'payment', 'type' => 'payment', 'status' => 'paid'));
$form = efb_pay_form();
$form[] = array('id_' => 'mob', 'type' => 'mobile', 'smsnoti' => '1');
$form[0]['smsnoti'] = '1';
$run = function ($form, $when) use ($pay, $rows) { $fx = new ActFx(); $fx->run_payment_actions_efb($pay, $when, $rows, $form); return $fx->log; };
$t('default: nothing at creation', $run($form, 'submit'), array());
$paid = $run($form, 'paid');
$t('default: at payment Telegram / Sheet / webhook (event "payment"), SMS, and one mail to owner + payer', array($paid[0], $paid[1][0], $paid[2][0]), array(array('int', 'payment', array('telegram', 'googlesheet', 'webhook')), 'sms', 'mail'));
$t('mail recipients: form + support address, the payer', $paid[2][1], array(array('owner@example.test', 'support@example.test'), array('payer@example.test')));
$t('test mode: the subject starts with [TEST]', $paid[2][2], '[TEST] Order');
$t('SMS numbers: owner and the visitor field with SMS on', $paid[1][1], array(array('+491700000000'), array('+491711111111')));
$f2 = $form; $f2[0]['pay_act_user_mail'] = 'submit'; $f2[0]['pay_act_gs'] = 'both'; $f2[0]['pay_act_tlg'] = 'submit';
$sub = $run($f2, 'submit');
$t('"submit": the payer mail and Telegram at creation; "both": Sheet at creation (event payment_pending)', array($sub[0], $sub[1][1]), array(array('int', 'payment_pending', array('telegram', 'googlesheet')), array(array(), array('payer@example.test'))));
$aft = $run($f2, 'paid');
$t('...and at payment: Sheet again (both), webhook, the owner mail only', array($aft[0][2], $aft[2][1]), array(array('googlesheet', 'webhook'), array(array('owner@example.test', 'support@example.test'), array())));

echo "\nRESULTS: {$passed} passed, {$failed} failed\n";
exit($failed ? 1 : 0);
