<?php
/**
 * PAY00 §7.4: the return from the provider and the status the page asks for.
 *  - return: a bad or missing signature, an unknown uid or another gateway's route → home, nothing
 *    checked; a good one → one fetch() (never a status from the query string) → 303 to the stored
 *    return_to with efb_pay + efb_ps, never to an address from the request; no-store;
 *  - "?status=paid" in the return URL changes nothing;
 *  - status: signature required, no-store, state paid/pending/failed, the confirmation code only
 *    when paid and when the form shows one, a redirect form gives its address; at most one provider
 *    check per 10 seconds; rate limit;
 *  - poll: needs the attempt key of the page that created the payment.
 *
 * Run: php tests/test-pay-return-status.php
 */
define('ABSPATH', __DIR__ . '/');
$passed = 0; $failed = 0;
require __DIR__ . '/pay-test-db.php';
$db = efb_pay_env();
use Emsfb\Payments\Service;
use Emsfb\Payments\Store;
use Emsfb\Payments\Mock_Provider;
$t = 'efb_pay_t';
$req = function ($uid, $sig, $gw = 'efbmock', $extra = array()) {
	$r = new WP_REST_Request('GET');
	$r->set_param('gateway', $gw); $r->set_param('p', $uid); $r->set_param('s', $sig);
	foreach ($extra as $k => $v) $r->set_param($k, $v);
	return $r;
};
$res = efb_pay_create($db);
$p = Store::find_by_uid($res['pay']['uid']);
$sig = Service::sign($p['uid'], 'efbmock');
$t('the signature handed to the browser is the return signature', $res['pay']['sig'], $sig);

$GLOBALS['efb_http_log'] = array();
$bad = Service::handle_return($req($p['uid'], str_repeat('0', 32)));
$t('bad signature → home page, no provider call', array($bad->status, $bad->headers['Location'], count($GLOBALS['efb_http_log'])), array(303, 'https://site.example/', 0));
$t('unknown uid → home page', Service::handle_return($req('AAAAAAAAAAAAAAAAAAAAAA', $sig))->headers['Location'], 'https://site.example/');
$t('another gateway in the path → home page', Service::handle_return($req($p['uid'], $sig, 'mollie'))->headers['Location'], 'https://site.example/');

$ok = Service::handle_return($req($p['uid'], $sig, 'efbmock', array('status' => 'paid', 'redirect' => 'https://evil.example/')));
$t('good signature → 303 to the stored page with efb_pay and efb_ps', $ok->headers['Location'], 'https://site.example/pay-page/?x=1&efb_pay=' . $p['uid'] . '&efb_ps=' . $sig);
$t('...no-store', $ok->headers['Cache-Control'], 'no-store, max-age=0');
$t('...one fetch at the provider', count($GLOBALS['efb_http_log']), 1);
$t('"status=paid" in the query does not make it paid', Store::get($p['id'])['status'], 'pending');

$st = function ($uid, $sig) { $r = new WP_REST_Request('GET'); $r->set_param('p', $uid); $r->set_param('s', $sig); return Service::handle_status($r); };
$t('status without the right signature: 404', $st($p['uid'], 'x')->status, 404);
$a = $st($p['uid'], $sig);
$t('status pending: no confirmation code yet, no-store, test mode flag', array($a->data['state'], isset($a->data['track']), $a->headers['Cache-Control'], $a->data['test_mode']), array('pending', false, 'no-store, max-age=0', true));
Mock_Provider::set_status($p['gateway_ref'], 'paid');
$GLOBALS['efb_http_log'] = array();
$b = $st($p['uid'], $sig);
$t('status within 10 s of the last check does not call the provider again', array($b->data['state'], count($GLOBALS['efb_http_log'])), array('pending', 0));
Store::$now = time() + 11;
$c = $st($p['uid'], $sig);
$t('after 10 s: one provider check → paid, with the confirmation code (form shows codes)', array($c->data['state'], $c->data['track'], count($GLOBALS['efb_http_log'])), array('paid', $p['track'], 1));
Store::$now = null;

// Return when already paid: no provider call.
$GLOBALS['efb_http_log'] = array();
Service::handle_return($req($p['uid'], $sig));
$t('return of a paid payment: no provider call', count($GLOBALS['efb_http_log']), 0);

// A form without tracking codes and with a redirect thank-you page.
$form = efb_pay_form();
$form[0]['trackingCode'] = '';
$form[0]['thank_you'] = 'rdrct';
$form[0]['rePage'] = 'https:@efb@@efb@site.example@efb@thanks@efb@';
$db->update('wp_emsfb_form', array('form_structer' => str_replace('"', '\\"', json_encode($form))), array('form_id' => $p['form_id']));
$d = $st($p['uid'], $sig);
$t('no code when the form shows none; the redirect page is given', array(isset($d->data['track']), $d->data['redirect']), array(false, 'https://site.example/thanks/'));

// Failed.
$r2 = efb_pay_create($db);
$p2 = Store::find_by_uid($r2['pay']['uid']);
Mock_Provider::set_status($p2['gateway_ref'], 'failed');
$e = $st($p2['uid'], Service::sign($p2['uid'], 'efbmock'));
$t('failed → state failed, can_retry', array($e->data['state'], $e->data['can_retry']), array('failed', true));

// Poll.
$pr = new WP_REST_Request('POST'); $pr->set_param('uid', $p2['uid']); $pr->set_param('attempt_key', 'wrong');
$t('poll with another attempt key: 404', Service::handle_poll($pr)->status, 404);
$pr->set_param('attempt_key', $p2['attempt_key']);
$t('poll with the page\'s attempt key: the state', Service::handle_poll($pr)->data['state'], 'failed');

// Rate limit.
$GLOBALS['efb_transients'] = array();
$codes = array();
for ($i = 0; $i < 62; $i++) $codes[] = $st($p['uid'], $sig)->status;
$t('status: 60 per minute per address, then 429', array_slice($codes, 59), array(200, 429, 429));

// return_to validation.
$x = efb_pay_create($db, null, array('page_url' => 'https://evil.example/phish'));
$t('a page address of another host is not stored (return goes home)', Store::find_by_uid($x['pay']['uid'])['return_to'], '');

echo "\nRESULTS: {$passed} passed, {$failed} failed\n";
exit($failed ? 1 : 0);
