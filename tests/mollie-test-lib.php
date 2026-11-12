<?php
/**
 * Not a test. Shared set-up of the Mollie add-on tests (tests/test-addon-mollie-*.php): the PAY00
 * test database and WordPress stand-ins (pay-test-db.php), the fake api.mollie.com
 * (mollie-fake-provider.php, answers built from tests/fixtures/payments/mollie), the add-on itself
 * and helpers for forms and payments.
 *
 * Add-on source: EFB_MOLLIE_DIR, vendor/mollie (git-ignored symlink), or /home/user/vendores-efb/mollie.
 * Keys: obviously fake strings assembled at run time (never a real key, nothing key-shaped in git).
 */
if (!defined('ABSPATH')) define('ABSPATH', __DIR__ . '/');
require_once __DIR__ . '/pay-test-db.php';
require_once __DIR__ . '/mollie-fake-provider.php';

function efb_mollie_dir() {
	foreach (array(getenv('EFB_MOLLIE_DIR') ?: '', __DIR__ . '/../vendor/mollie', '/home/user/vendores-efb/mollie') as $d) {
		if ($d !== '' && is_readable($d . '/routes-efb.php')) return rtrim($d, '/');
	}
	fwrite(STDERR, "Mollie add-on not found (EFB_MOLLIE_DIR, vendor/mollie or /home/user/vendores-efb/mollie)\n");
	exit(2);
}

/** Fake keys of the right shape (prefix + 20+ letters), built here so no key-shaped literal is stored. */
function efb_mollie_key($mode, $tag = 'Fake') {
	return $mode . '_' . str_repeat($tag, (int) ceil(24 / strlen($tag)));
}

/** The environment: tables, the fake Mollie, the add-on registered, Test and Live keys stored, mode test. */
function efb_mollie_env($mode = 'test') {
	$db = efb_pay_env();
	if (!function_exists('wp_strip_all_tags')) { function wp_strip_all_tags($s) { return trim(strip_tags((string) $s)); } }
	if (!function_exists('esc_html__')) { function esc_html__($s, $d = null) { return $s; } }
	Efb_Test_Mollie_Fake::reset();
	Efb_Test_Mollie_Fake::register();
	require_once efb_mollie_dir() . '/routes-efb.php';
	\Emsfb\Payments\Registry::boot();
	$gw = \Emsfb\Payments\Registry::get('mollie');
	$s = \Emsfb\Payments\Settings::load('mollie');
	$s->apply($gw->settings_schema(), array('test_api_key' => efb_mollie_key('test'), 'live_api_key' => efb_mollie_key('live')), $mode);
	\Emsfb\Payments\Store::$now = null;
	return $db;
}

/** A Mollie payment form: radio 25 / 12.50, the Mollie field (+ $field keys), an e-mail field. */
function efb_mollie_form($currency = 'eur', array $field = array(), array $extra_rows = array()) {
	$rows = array(
		array('type' => 'payment', 'getway' => 'mollie', 'currency' => $currency, 'trackingCode' => '1', 'email' => 'owner@example.test', 'email_to' => 'em', 'formName' => 'Workshop registration'),
		array('type' => 'step', 'id_' => '1', 'step' => 1),
		array('id_' => 'em', 'type' => 'email', 'name' => 'Email', 'step' => 1),
		array('id_' => 'rad', 'type' => 'payRadio', 'name' => 'Ticket', 'step' => 1),
		array('id_' => 'ro1', 'parent' => 'rad', 'type' => 'option', 'value' => 'Adult', 'price' => '25'),
		array('id_' => 'ro2', 'parent' => 'rad', 'type' => 'option', 'value' => 'Child', 'price' => '12.5'),
	);
	foreach ($extra_rows as $r) $rows[] = $r;
	$rows[] = array_merge(array('id_' => 'pf', 'type' => 'mollie', 'name' => 'Pay', 'step' => 1), $field);
	return $rows;
}

function efb_mollie_uuid() {
	return sprintf('%08x-%04x-4%03x-8%03x-%012x', mt_rand(), mt_rand(0, 65535), mt_rand(0, 4095), mt_rand(0, 4095), mt_rand());
}

/** Service::create() for a Mollie form, the way payments/create calls it. Returns the browser answer. */
function efb_mollie_create($db, $form = null, $rows = null, array $extra = array()) {
	$form = $form ?: efb_mollie_form();
	$fid = $db->add_form($form, 'payment');
	$rows = $rows !== null ? $rows : array(array('id_' => 'em', 'type' => 'email', 'value' => 'payer@example.test'), array('id_' => 'rad', 'id_ob' => 'ro1', 'type' => 'payRadio', 'value' => 'Adult', 'price' => '0.01'));
	return \Emsfb\Payments\Service::create(array_merge(array(
		'form_id' => $fid, 'form' => $form, 'rows' => $rows, 'field_id' => 'pf', 'attempt_key' => efb_mollie_uuid(), 'method' => '',
		'expected_minor' => null, 'page_url' => 'https://site.example/pay/', 'locale' => 'nl_NL', 'ip' => '203.0.113.9', 'payer_email' => 'payer@example.test',
		'description' => 'Browser-sent name',
		'store_entry' => function ($rows) use ($db, $fid) {
			$id = $db->add_entry($fid, $rows, gmdate('Y-m-d H:i:s'), array('read_' => 2, 'track' => 'EFB' . mt_rand(10000, 99999)));
			return array('track' => $db->get_var('SELECT track FROM wp_emsfb_msg_ WHERE msg_id = ' . (int) $id), 'msg_id' => $id);
		},
	), $extra));
}

/** The last request the fake received for a path (and method). */
function efb_mollie_last($method, $path_re) {
	$s = Efb_Test_Mollie_Fake::state();
	for ($i = count($s['log']) - 1; $i >= 0; $i--) {
		if ($s['log'][$i]['method'] === $method && preg_match($path_re, $s['log'][$i]['path'])) return $s['log'][$i];
	}
	return null;
}

function efb_mollie_count($method, $path_re) {
	$n = 0;
	foreach (Efb_Test_Mollie_Fake::state()['log'] as $l) if ($l['method'] === $method && preg_match($path_re, $l['path'])) $n++;
	return $n;
}

/** Mollie's webhook: POST id=tr_… (form-encoded), optionally with the p / t of the webhook URL. */
function efb_mollie_webhook($ref, $uid = null, $token = null) {
	$r = new WP_REST_Request('POST');
	$r->set_param('gateway', 'mollie');
	$r->set_body('id=' . rawurlencode($ref));
	$r->set_param('id', $ref);
	if ($uid !== null) { $r->set_param('p', $uid); $r->set_param('t', $token === null ? \Emsfb\Payments\Mollie_Gateway::webhook_token($uid) : $token); }
	return \Emsfb\Payments\Service::handle_webhook($r);
}

/** The browser coming back: GET payments/return/mollie?p&s. */
function efb_mollie_return($uid) {
	$r = new WP_REST_Request('GET');
	$r->set_param('gateway', 'mollie');
	$r->set_param('p', $uid);
	$r->set_param('s', \Emsfb\Payments\Service::sign($uid, 'mollie'));
	return \Emsfb\Payments\Service::handle_return($r);
}

function efb_mollie_finalized($payment_id) {
	global $wpdb;
	return (int) $wpdb->get_var("SELECT COUNT(*) FROM wp_emsfb_pmt_evt_ WHERE payment_id = " . (int) $payment_id . " AND type = 'finalized'");
}
