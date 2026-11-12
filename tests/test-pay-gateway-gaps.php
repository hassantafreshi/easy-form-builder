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

echo "\nRESULTS: {$passed} passed, {$failed} failed\n";
exit($failed ? 1 : 0);
