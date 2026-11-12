<?php
/**
 * PAY06 (Paymob) sub-task B: the callback HMAC of the Paymob add-on (Client).
 *  - the official sample of the "HMAC Transaction Callback" page: the 20 fields in the documented
 *    order give exactly the documented concatenated string (booleans true/false, nested
 *    order.id and source_data.*, no separators);
 *  - HMAC-SHA512 hex with the HMAC secret equals an independent signer (field by field, like
 *    Paymob's own WooCommerce plugin); one changed field, another secret, a short or non-hex value
 *    are refused; upper-case hex is accepted;
 *  - the GET (redirection) shape: id, order_id or order, source_data.pan or source_data_pan (PHP
 *    turns dots in query names into underscores) give the same string as the POST object;
 *  - the official GET sample of the "Transaction callbacks" page produces its documented field order;
 *  - the intention-shaped callback: amount with at least two decimals (OMR three) + intention id;
 *  - status map of PAY06 §9.
 *
 * Run: php tests/test-addon-paymob-hmac.php
 */
define('ABSPATH', __DIR__ . '/');
$passed = 0; $failed = 0;
require __DIR__ . '/addon-paymob-lib.php';
pmb_env();
use Emsfb\Paymob\Client;
$t = 'efb_pay_t';

$cb = json_decode(file_get_contents(__DIR__ . '/fixtures/payments/paymob/callback-success.json'), true);
$o = $cb['body']['obj'];
$t('official sample: the documented concatenated string', Client::hmac_string_post($o), $cb['hmac_string']);
$t('the field order is the documented one (20 fields)', Client::HMAC_FIELDS, array('amount_cents', 'created_at', 'currency', 'error_occured', 'has_parent_transaction', 'id', 'integration_id', 'is_3d_secure', 'is_auth', 'is_capture', 'is_refunded', 'is_standalone_payment', 'is_voided', 'order.id', 'owner', 'pending', 'source_data.pan', 'source_data.sub_type', 'source_data.type', 'success'));
$t('the add-on string equals the independent one (fake provider)', Client::hmac_string_post($o), Efb_Test_Paymob_Fake::hmac_post_string($o));
$t('the documented sample hash is 128 hex characters (SHA-512)', (bool) preg_match('/^[0-9a-f]{128}$/', $cb['hmac_sample']), true);
$sec = Efb_Test_Paymob_Fake::hmac_secret();
$h = hash_hmac('sha512', $cb['hmac_string'], $sec);
$t('sign(): HMAC-SHA512 hex of the string with the HMAC secret', Client::sign(Client::hmac_string_post($o), $sec), $h);
$t('verify: the right HMAC', Client::verify(Client::hmac_string_post($o), $h, $sec), true);
$t('verify: upper-case hex accepted', Client::verify(Client::hmac_string_post($o), strtoupper($h), $sec), true);
$t('verify: another secret refused', Client::verify(Client::hmac_string_post($o), hash_hmac('sha512', $cb['hmac_string'], 'Attacker' . 'Secret00001'), $sec), false);
$t('verify: empty, short, non-hex or SHA-256 refused; empty secret refused', array(Client::verify('x', '', $sec), Client::verify('x', substr($h, 0, 64), $sec), Client::verify('x', str_repeat('g', 128), $sec), Client::verify(Client::hmac_string_post($o), $h, '')), array(false, false, false, false));
foreach (array('amount_cents' => 1, 'success' => false, 'pending' => true, 'is_refunded' => true, 'is_voided' => true, 'currency' => 'USD', 'id' => 1, 'integration_id' => 1) as $k => $v) {
	$x = $o; $x[$k] = $v;
	$t("one changed signed field ($k) breaks the HMAC", Client::verify(Client::hmac_string_post($x), $h, $sec), false);
}
$x = $o; $x['order']['id'] = 1;
$t('a changed order.id breaks the HMAC', Client::verify(Client::hmac_string_post($x), $h, $sec), false);
$x = $o; $x['source_data']['pan'] = '0000';
$t('a changed source_data.pan breaks the HMAC', Client::verify(Client::hmac_string_post($x), $h, $sec), false);
$x = $o; $x['order']['merchant_order_id'] = 'Other'; $x['billing_data']['email'] = 'x@example.test'; $x['data']['message'] = 'x';
$t('fields outside the HMAC (merchant_order_id, billing, data) do not change it (and are never trusted)', Client::verify(Client::hmac_string_post($x), $h, $sec), true);
$x = $o; $x['order'] = 217503754;
$t('order given as a number instead of an object: same string', Client::hmac_string_post($x), $cb['hmac_string']);
$x = $o; $x['source_data']['sub_type'] = null;
$t('a null value is an empty string (like Paymob\'s PHP plugin)', Client::hmac_string_post($x), str_replace('2346MasterCardcard', '2346card', $cb['hmac_string']));

// GET (Transaction Response callback, the redirection).
$q = array('amount_cents' => '100000', 'created_at' => '2024-06-13T11:33:44.592345', 'currency' => 'EGP', 'error_occured' => 'false', 'has_parent_transaction' => 'false',
	'id' => '192036465', 'integration_id' => '4097558', 'is_3d_secure' => 'true', 'is_auth' => 'false', 'is_capture' => 'false', 'is_refunded' => 'false',
	'is_standalone_payment' => 'true', 'is_voided' => 'false', 'order_id' => '217503754', 'owner' => '302852', 'pending' => 'false',
	'source_data.pan' => '2346', 'source_data.sub_type' => 'MasterCard', 'source_data.type' => 'card', 'success' => 'true', 'hmac' => 'x', 'p' => 'Ignored');
$t('GET: id, order_id, source_data.* → the same string as the POST object', Client::hmac_string_get($q), $cb['hmac_string']);
$q2 = $q; unset($q2['order_id'], $q2['source_data.pan'], $q2['source_data.sub_type'], $q2['source_data.type']);
$q2 += array('order' => '217503754', 'source_data_pan' => '2346', 'source_data_sub_type' => 'MasterCard', 'source_data_type' => 'card');
$t('GET as PHP gives it ($_GET: "order", source_data_pan): the same string', Client::hmac_string_get($q2), $cb['hmac_string']);
$line = trim(preg_replace('/^#.*\n/m', '', file_get_contents(__DIR__ . '/fixtures/payments/paymob/redirect-get-success.txt')));
parse_str($line, $doc);
$t('the official GET sample (parsed like PHP does) yields its fields in the documented order', Client::hmac_string_get($doc), '500002024-06-25T15:16:25.910710+04:00EGPfalsefalse3160042936truefalsefalsefalsetruefalse378804211false2346MasterCardcardtrue');
$fq = Efb_Test_Paymob_Fake::redirect_query(array_merge($o, array('data' => array('message' => 'Approved'))));
$t('a redirection signed by the independent signer verifies', Client::verify(Client::hmac_string_get($fq), $fq['hmac'], $sec), true);
$fq['success'] = 'false';
$t('...and success=false in the address breaks it', Client::verify(Client::hmac_string_get($fq), $fq['hmac'], $sec), false);

// Intention-shaped callback.
$t('intention amount: 10.00, 10.50, 10.05 (EGP), 10.125 / 10.50 (OMR)', array(Client::intention_amount(1000, 'EGP'), Client::intention_amount(1050, 'EGP'), Client::intention_amount(1005, 'EGP'), Client::intention_amount(10125, 'OMR'), Client::intention_amount(10500, 'OMR')), array('10.00', '10.50', '10.05', '10.125', '10.50'));
$t('intention HMAC string = amount + intention id', Client::intention_hmac_string(60000, 'EGP', 'pi_x_1'), '600.00pi_x_1');

// Status map (PAY06 §9).
$base = array('success' => false, 'pending' => false, 'is_auth' => false, 'is_capture' => false, 'is_voided' => false, 'is_refunded' => false, 'has_parent_transaction' => false, 'amount_cents' => 1000, 'refunded_amount_cents' => 0);
$st = function ($over) use ($base) { return Client::status(array_merge($base, $over)); };
$t('success → paid', $st(array('success' => true)), 'paid');
$t('success + is_auth (no capture) → authorized', $st(array('success' => true, 'is_auth' => true)), 'authorized');
$t('pending (kiosk, wallet waiting) → pending', $st(array('pending' => true)), 'pending');
$t('success false, pending false → failed', $st(array()), 'failed');
$t('is_voided → canceled', $st(array('success' => true, 'is_voided' => true)), 'canceled');
$t('is_refunded with the whole amount → refunded', $st(array('success' => true, 'is_refunded' => true, 'refunded_amount_cents' => 1000)), 'refunded');
$t('is_refunded with part of it → partially_refunded', $st(array('success' => true, 'is_refunded' => true, 'refunded_amount_cents' => 400)), 'partially_refunded');
$t('is_refunded without a total → refunded', $st(array('success' => true, 'is_refunded' => true)), 'refunded');
$t('a child transaction (refund / void) is not a payment state → null', $st(array('success' => true, 'has_parent_transaction' => true)), null);
$t('string booleans as in the refund doc sample ("true") are read', $st(array('success' => 'true', 'pending' => '')), 'paid');

// Keys.
$t('key_parts: region prefix, kind, mode', array(Client::key_parts(implode('_', array('egy', 'sk', 'test', 'Abc123xyz'))), Client::key_parts(implode('_', array('pk', 'live', 'Abc123xyz'))), Client::key_parts('nope')), array(array('egy', 'sk', 'test'), array('', 'pk', 'live'), null));
$t('prefixes per region (egy, ksa/sau, are/uae, omn, pak)', array(Client::region_of_prefix('egy'), Client::region_of_prefix('sau'), Client::region_of_prefix('are'), Client::region_of_prefix('omn'), Client::region_of_prefix('pak'), Client::prefix_in_region('egy', 'sa')), array('eg', 'sa', 'ae', 'om', 'pk', false));
$t('phone: Arabic-Indic and Persian digits, 00 → +, too short → empty', array(Client::phone('٠١٠١٢٣٤٥٦٧٨'), Client::phone('00 20 10 1234 5678'), Client::phone('۰۳۰۰۱۲۳۴۵۶۷'), Client::phone('123')), array('01012345678', '+201012345678', '03001234567', ''));

echo "\nRESULTS: {$passed} passed, {$failed} failed\n";
exit($failed ? 1 : 0);
