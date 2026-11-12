<?php
/**
 * PAY05 (PayTabs) sub-task B: the two signatures of the PayTabs add-on (Client), against
 * independent implementations written from PayTabs' article "Validate callback/return"
 * (https://support.paytabs.com/en/support/solutions/articles/60000718961).
 *  - callback: header Signature = hash_hmac('sha256', <raw body>, <server key>); a changed byte, the
 *    other mode's key, upper-case hex, an empty or non-hex header;
 *  - return (browser POST): "signature" removed, empty fields removed, ksort, http_build_query,
 *    hash_hmac with the server key; both readings of "empty" (PayTabs' PHP sample array_filter()
 *    also drops "0"); every field changed in turn → refused; added field → refused; e-mail with
 *    "@" and Arabic text encoded the same way as PHP's http_build_query;
 *  - the return fields example of the article (fixtures/payments/paytabs/return-post-A.txt);
 *  - 500 random field sets signed by the independent signer verify.
 *
 * Run: php tests/test-addon-paytabs-signature.php
 */
define('ABSPATH', __DIR__ . '/');
$passed = 0; $failed = 0;
require __DIR__ . '/addon-paytabs-lib.php';
ptb_env();
use Emsfb\Paytabs\Client;
$t = 'efb_pay_t';
$key = ptb_key('test');

/* callback */
$body = json_encode(ptb_fixture('callback-A')['body'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
$sig = hash_hmac('sha256', $body, $key);
$t('callback: HMAC-SHA256 of the raw body with the server key verifies', Client::verify_callback($body, $sig, $key), true);
$t('callback: the same hex in upper case verifies', Client::verify_callback($body, strtoupper($sig), $key), true);
$t('callback: one byte of the body changed → refused', Client::verify_callback(str_replace('"200.00"', '"200.01"', $body), $sig, $key), false);
$t('callback: the same JSON re-encoded (spaces) → refused (the raw body is signed, not the data)', Client::verify_callback(json_encode(json_decode($body), JSON_PRETTY_PRINT), $sig, $key), false);
$t('callback: signed with the Live key, checked with the Test key → refused', Client::verify_callback($body, hash_hmac('sha256', $body, ptb_key('live')), $key), false);
$t('callback: empty / not hex / short header → refused', array(Client::verify_callback($body, '', $key), Client::verify_callback($body, str_repeat('z', 64), $key), Client::verify_callback($body, substr($sig, 0, 63), $key)), array(false, false, false));
$t('callback: no server key → refused (never an empty-key HMAC)', Client::verify_callback($body, hash_hmac('sha256', $body, ''), ''), false);
$t('callback: sha256 of the body without the key (not HMAC) → refused', Client::verify_callback($body, hash('sha256', $body), $key), false);

/* return */
$indep = function (array $post, $k, $php_filter = true) {
	unset($post['signature']);
	$f = $php_filter ? array_filter($post) : array_filter($post, function ($v) { return $v !== '' && $v !== null; });
	ksort($f);
	return hash_hmac('sha256', http_build_query($f), $k);
};
$lines = array_values(array_filter(explode("\n", file_get_contents(__DIR__ . '/fixtures/payments/paytabs/return-post-A.txt')), function ($l) { return $l !== '' && $l[0] !== '#'; }));
parse_str(str_replace('<EFB_UID>', 'Q3xAbCdEfGhIjKlMnOpQrS', $lines[0]), $post);
$t('the article\'s example fields parse (tranRef, respStatus, cartId, customerEmail …)', array_keys($post), array('acquirerMessage', 'acquirerRRN', 'cartId', 'customerEmail', 'respCode', 'respMessage', 'respStatus', 'token', 'tranRef'));
$post['signature'] = $indep($post, $key);
$t('return: the signature of PayTabs\' PHP sample verifies', Client::verify_return($post, $key), true);
$t('return: the string signed has no empty fields, sorted, URL-encoded (@ → %40)', Client::return_string($post), 'cartId=Q3xAbCdEfGhIjKlMnOpQrS&customerEmail=payer%40example.test&respCode=G96376&respMessage=Authorised&respStatus=A&tranRef=TST2231600000001');
foreach (array('tranRef' => 'TST2231600000002', 'respStatus' => 'D', 'cartId' => 'OtherCart', 'respCode' => '000', 'customerEmail' => 'x@example.test') as $k => $v) {
	$c = $post; $c[$k] = $v;
	$t("return: $k changed after signing → refused", Client::verify_return($c, $key), false);
}
$c = $post; $c['extra'] = '1';
$t('return: a field added after signing → refused', Client::verify_return($c, $key), false);
$c = $post; $c['acquirerRRN'] = '';
$t('return: an empty field added or removed changes nothing (empties are not signed)', Client::verify_return($c, $key), true);
$t('return: the Live key does not verify a Test signature', Client::verify_return($post, ptb_key('live')), false);
$c = $post; unset($c['signature']);
$t('return: no signature → refused', Client::verify_return($c, $key), false);
$c = $post; $c['signature'] = 'nothex';
$t('return: a malformed signature → refused', Client::verify_return($c, $key), false);
$z = $post; $z['acquirerRRN'] = '0';
$z['signature'] = $indep($z, $key, true);
$t('return: a "0" field dropped by array_filter (PayTabs\' PHP sample) verifies', Client::verify_return($z, $key), true);
$z['signature'] = $indep($z, $key, false);
$t('return: the same "0" field kept (strict reading of "empty") verifies too', Client::verify_return($z, $key), true);
$ar = $post; $ar['respMessage'] = 'تمت الموافقة'; $ar['customerEmail'] = 'a+b@example.test';
$ar['signature'] = $indep($ar, $key);
$t('return: Arabic text and "+" in the e-mail are encoded like http_build_query → verifies', Client::verify_return($ar, $key), true);

mt_srand(5);
$ok = 0;
for ($i = 0; $i < 500; $i++) {
	$f = array('tranRef' => 'TST' . mt_rand(1000000000, 2000000000), 'cartId' => substr(md5((string) $i), 0, 22), 'respStatus' => array('A', 'D', 'E', 'C', 'H')[mt_rand(0, 4)], 'respCode' => (string) mt_rand(0, 999999), 'respMessage' => array('Authorised', 'Declined', 'Insufficient funds', 'رفض', '')[mt_rand(0, 4)], 'acquirerRRN' => mt_rand(0, 1) ? (string) mt_rand(0, 9) : '', 'customerEmail' => 'p' . $i . '@example.test', 'token' => '');
	$f['signature'] = $indep($f, $key, (bool) mt_rand(0, 1));
	if (Client::verify_return($f, $key)) $ok++;
}
$t('500 random return field sets signed independently all verify', $ok, 500);

/* parse_return of the gateway (fields in the query as in the stand-in request; $_POST in WordPress) */
$g = new \Emsfb\Paytabs\Paytabs_Gateway();
$pay = new \Emsfb\Payments\Payment(array('uid' => 'Q3xAbCdEfGhIjKlMnOpQrS', 'gateway_ref' => 'TST2231600000001', 'mode' => 'test'));
$q = array_merge(array('p' => $pay->uid, 's' => 'x', 'gateway' => 'paytabs'), $post);
$t('parse_return: signed fields of this payment → its tran_ref', $g->parse_return($q, $pay), 'TST2231600000001');
$c = $q; $c['respStatus'] = 'A'; $c['respMessage'] = 'Approved';
$t('parse_return: a forged field → "" (no check now; the poll and the cron still ask PayTabs)', $g->parse_return($c, $pay), '');
$other = ptb_return_fields('TST2231600000009', $pay->uid);
$t('parse_return: a signed return of another transaction → ""', $g->parse_return(array_merge(array('p' => $pay->uid), $other), $pay), '');
$wrongcart = ptb_return_fields('TST2231600000001', 'AnotherPaymentUid00000');
$t('parse_return: a signed return of another cart → ""', $g->parse_return($wrongcart, $pay), '');
$live = new \Emsfb\Payments\Payment(array('uid' => 'Q3xAbCdEfGhIjKlMnOpQrS', 'gateway_ref' => 'TST2231600000001', 'mode' => 'live'));
$t('parse_return: a Live payment checks with the Live key (a Test signature → "")', array($g->parse_return($q, $live), $g->parse_return(ptb_return_fields('TST2231600000001', $pay->uid, 'A', ptb_key('live')), $live)), array('', 'TST2231600000001'));
$t('parse_return: no posted fields (a GET) → the stored reference', $g->parse_return(array('p' => $pay->uid, 's' => 'x'), $pay), 'TST2231600000001');
$_POST = $post;
$t('parse_return: the browser POST is read from $_POST (core passes only the query)', $g->parse_return(array('p' => $pay->uid, 's' => 'x'), $pay), 'TST2231600000001');
$_POST['respStatus'] = 'X';
$t('parse_return: a tampered $_POST → ""', $g->parse_return(array('p' => $pay->uid, 's' => 'x'), $pay), '');
$_POST = array();

echo "\nRESULTS: {$passed} passed, {$failed} failed\n";
exit($failed ? 1 : 0);
