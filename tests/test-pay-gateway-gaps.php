<?php
/**
 * PAY00: core gaps reported by the PayTabs, Xendit and Paymob add-on builders (BUGS-FOUND):
 *  - parse_return() also gets the fields of a POST return (PayTabs signs them in the body);
 *  - Redactor hides keys with a region prefix (egy_sk_test_…) and a "Token <key>" header (Paymob);
 *  - a settings pattern that already escapes its slash ("[A-Za-z0-9+\/=]") works (Paymob).
 *
 * Run: php tests/test-pay-gateway-gaps.php
 */
define('ABSPATH', __DIR__ . '/');
$passed = 0; $failed = 0;
require __DIR__ . '/pay-test-db.php';
$db = efb_pay_env();
use Emsfb\Payments\Service;
use Emsfb\Payments\Store;
use Emsfb\Payments\Field;
use Emsfb\Payments\Redactor;
use Emsfb\Payments\Settings;
$t = 'efb_pay_t';

class Post_Return_Gateway extends \Emsfb\Payments\Mock_Gateway {
	public static $seen = null;
	public function slug() { return 'efbpost'; }
	public function parse_return(array $query, \Emsfb\Payments\Payment $p) { self::$seen = $query; return isset($query['tranRef']) && $query['tranRef'] === $p->gateway_ref ? $p->gateway_ref : ''; }
}
class Body_Request extends WP_REST_Request {
	public $body_params = array();
	public function get_body_params() { return $this->body_params; }
}
add_action('efb_register_payment_gateways', function ($reg) { $reg->register(new Post_Return_Gateway()); });
\Emsfb\Payments\Registry::reset();
Settings::load('efbpost')->apply((new Post_Return_Gateway())->settings_schema(), array('api_key__test' => 'mock_test_ABCDEF123', 'webhook_secret__test' => 'whs_unit_test_secret'), 'test');
$form = efb_pay_form();
$form[0]['getway'] = 'efbpost';
$form[6]['type'] = 'efbpost';
$fid = $db->add_form($form, 'payment');
$res = Service::create(array('form_id' => $fid, 'form' => $form, 'rows' => array(array('id_' => 'rad', 'id_ob' => 'ro1')), 'field_id' => 'pf', 'attempt_key' => '55555555-3333-4444-8555-666666666666', 'page_url' => 'https://site.example/p/',
	'store_entry' => function ($rows) use ($db, $fid) { $id = $db->add_entry($fid, $rows, gmdate('Y-m-d H:i:s'), array('read_' => 2)); return array('track' => 'T' . $id, 'msg_id' => $id); }));
$p = Store::find_by_uid($res['pay']['uid']);
\Emsfb\Payments\Mock_Provider::set_status($p['gateway_ref'], 'paid');
$r = new Body_Request('POST');
$r->set_param('gateway', 'efbpost');
$r->set_param('p', $p['uid']);
$r->set_param('s', Service::sign($p['uid'], 'efbpost'));
$r->body_params = array('tranRef' => $p['gateway_ref'], 'respStatus' => 'A', 'signature' => 'x');
Service::handle_return($r);
$t('a POST return: parse_return() sees the body fields (tranRef …) besides the query (old code: query only)', array(Post_Return_Gateway::$seen['tranRef'] ?? null, Post_Return_Gateway::$seen['p'] ?? null, Store::get($p['id'])['status']), array($p['gateway_ref'], $p['uid'], 'paid'));


$t('Redactor: a key with a region prefix and a "Token" header are hidden', strpos(Redactor::redact_string('egy_sk_test_' . 'AbCdEf123456 then Token ' . 'abc.def-123'), 'AbCdEf123456') === false && strpos(Redactor::redact_string('Token ' . 'abc.def-123'), 'abc.def') === false, true);
$t('Redactor: plain keys are still hidden', strpos(Redactor::redact_string('sk_live_' . 'ZZZZ123456'), 'ZZZZ123456'), false);

$t('a settings pattern with an escaped slash is a valid regex (old code: every value refused)', array(preg_match(Settings::pattern_re('^[A-Za-z0-9+\/=]{8,}$'), 'ab/cd+ef=='), preg_match(Settings::pattern_re('^a/b$'), 'a/b')), array(1, 1));
$s = Settings::load('efbpost');
$refused = $s->apply(array(array('key' => 'hmac', 'type' => 'secret', 'per_mode' => true, 'pattern' => '^[A-Za-z0-9+\/=]{8,}$')), array('hmac__test' => 'Zm9v/YmFy+cXV4='), 'test');
$t('...and such a key is saved', array($refused, $s->secret('hmac', 'test')), array(array(), 'Zm9v/YmFy+cXV4='));

// pf_methods: Xendit has 21 channels and "any".
$ids = implode(',', array_map(function ($i) { return 'ch' . $i; }, range(1, 45)));
$t('pf_methods keeps 40 method ids (old code: 20)', count(explode(',', Field::sanitize_key('pf_methods', $ids))), 40);

// A pending state with the gateway's own text for the visitor and a note for the owner (PayTabs hold, Paymob kiosk code).
class Note_Gateway extends \Emsfb\Payments\Mock_Gateway {
	public function slug() { return 'efbnote'; }
	public function fetch(\Emsfb\Payments\Payment $p, Settings $s) {
		$st = parent::fetch($p, $s);
		if ($st->ok && $st->status === 'pending') { $st->visitor_message = 'Pay at any kiosk with reference 1234 5678 before tomorrow.'; $st->note = 'held <b>for</b> review'; $st->notify_owner = true; }
		return $st;
	}
	public function return_page(\Emsfb\Payments\Payment $p, array $query, $continue) { return isset($query['framed']) ? '<!doctype html><title>x</title><script>parent.postMessage(' . json_encode($continue) . ', "*")</script>' : ''; }
}
add_action('efb_register_payment_gateways', function ($reg) { $reg->register(new Note_Gateway()); });
\Emsfb\Payments\Registry::reset();
Settings::load('efbnote')->apply((new Note_Gateway())->settings_schema(), array('api_key__test' => 'mock_test_ABCDEF123', 'webhook_secret__test' => 'whs_unit_test_secret'), 'test');
$form[0]['getway'] = 'efbnote'; $form[6]['type'] = 'efbnote';
$fid = $db->add_form($form, 'payment');
$res = Service::create(array('form_id' => $fid, 'form' => $form, 'rows' => array(array('id_' => 'rad', 'id_ob' => 'ro1')), 'field_id' => 'pf', 'attempt_key' => '55555555-3333-4444-8555-777777777777', 'page_url' => 'https://site.example/p/',
	'store_entry' => function ($rows) use ($db, $fid) { $id = $db->add_entry($fid, $rows, gmdate('Y-m-d H:i:s'), array('read_' => 2)); return array('track' => 'T' . $id, 'msg_id' => $id); }));
$n = Store::find_by_uid($res['pay']['uid']);
$GLOBALS['efb_mails'] = array();
Service::refresh($n, 'cron');
$st = Service::status_answer(Store::get($n['id']), 'status');
$t('a pending state with the gateway\'s own visitor text → the status page shows it (old code: always core\'s "waiting")', array($st['state'], $st['message']), array('pending', 'Pay at any kiosk with reference 1234 5678 before tomorrow.'));
$ev = array_values(array_filter(Store::events($n['id']), function ($e) { return $e['type'] === 'note'; }));
$t('...the owner\'s note is logged once as plain text and e-mailed when asked', array(count($ev), $ev[0]['note'], count($GLOBALS['efb_mails'])), array(1, 'held b for /b review', 1));
Service::refresh(Store::get($n['id']), 'cron');
$t('...a second check with the same note: no second event, no second e-mail', array(count(array_filter(Store::events($n['id']), function ($e) { return $e['type'] === 'note'; })), count($GLOBALS['efb_mails'])), array(1, 1));
\Emsfb\Payments\Mock_Provider::set_status(Store::get($n['id'])['gateway_ref'], 'paid');
$st = Service::status_answer(array_merge(Store::get($n['id']), array('last_checked_at' => null)), 'status');
$t('...once paid, core\'s own "received" text', $st['state'] === 'paid' && strpos($st['message'], 'received') !== false, true);
$html = null;
Service::$emit_html = function ($h) use (&$html) { $html = $h; return 'emitted'; };
$rq = new WP_REST_Request('GET'); $rq->set_param('gateway', 'efbnote'); $rq->set_param('p', $n['uid']); $rq->set_param('s', Service::sign($n['uid'], 'efbnote')); $rq->set_param('framed', '1');
$out = Service::handle_return($rq);
$t('a gateway\'s own return page (framed) is sent instead of the 303, with the signed form address (old code: always the 303)', array($out, strpos($html, 'efb_pay=' . $n['uid']) !== false), array('emitted', true));
$rq->set_param('framed', null);
$t('...without it: core\'s 303', Service::handle_return($rq)->status, 303);
Service::$emit_html = null;

echo "\nRESULTS: {$passed} passed, {$failed} failed\n";
exit($failed ? 1 : 0);
