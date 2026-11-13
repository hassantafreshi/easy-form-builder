<?php
/**
 * Not a test. Shared set-up of the Payplug add-on tests (tests/test-addon-payplug-*.php):
 *  - the PAY00 test environment (pay-test-db.php: SQLite tables, WordPress stand-ins);
 *  - the add-on loaded from vendor/payplug (a git-ignored symlink to vendores-efb/payplug);
 *  - EfbPayplugFake: a pre_http_request answer for https://api.payplug.com built from the
 *    documented responses in tests/fixtures/payments/payplug/*.json. It keeps the payments it
 *    created, so a test moves one to paid / failed / refunded and the gateway reads it back.
 *    Nothing leaves the machine; no real key exists anywhere (the keys below are made up at run
 *    time and have no provider key format).
 */
if (!defined('ABSPATH')) define('ABSPATH', __DIR__ . '/');
require_once __DIR__ . '/pay-test-db.php';

/** Made-up keys: JWT-shaped placeholders, the middle part tells the fake the mode. */
function efb_ppg_key($mode) { return 'unittest-ppg-key.' . 'mode' . $mode . '0000' . '.notasignature0'; }
/** A legacy-format key assembled at run time (only for the "wrong mode" check). */
function efb_ppg_legacy_key($mode) { return 'sk' . '_' . $mode . '_' . 'FAKEFAKEFAKE0000'; }

function efb_ppg_fixture($name) {
	$d = json_decode(file_get_contents(__DIR__ . '/fixtures/payments/payplug/' . $name . '.json'), true);
	if (!is_array($d)) throw new Exception('bad fixture ' . $name);
	return $d;
}

class EfbPayplugFake {
	public static $payments = array();
	public static $refunds = array();
	public static $log = array();
	public static $down = false;
	/** Next answer override for a path: 'METHOD /path' => fixture name or array(code, body) (one use). */
	public static $once = array();
	/** Drop the next answer after the provider acted on it (lost answer). */
	public static $lose_next = false;

	public static function reset() {
		self::$payments = array(); self::$refunds = array(); self::$log = array(); self::$down = false; self::$once = array(); self::$lose_next = false;
	}

	private static function answer($code, $body) {
		return array('headers' => array('content-type' => 'application/json'), 'body' => json_encode($body), 'response' => array('code' => $code, 'message' => ''), 'cookies' => array(), 'filename' => null);
	}

	/** Moves a stored payment to the state of a "get-*" fixture (only the state fields). */
	public static function set($id, $fixture, $over = array()) {
		$f = efb_ppg_fixture($fixture)['response']['body'];
		$p = self::$payments[$id];
		foreach (array('is_paid', 'paid_at', 'is_3ds', 'is_refunded', 'amount_refunded', 'failure', 'card', 'refundable_after', 'refundable_until') as $k) {
			if (array_key_exists($k, $f)) $p[$k] = $f[$k];
		}
		$p['hosted_payment']['paid_at'] = $f['hosted_payment']['paid_at'];
		if (!empty($p['is_refunded'])) $p['amount_refunded'] = $p['amount'];
		foreach ($over as $k => $v) $p[$k] = $v;
		self::$payments[$id] = $p;
	}

	public static function http($pre, $args, $url) {
		$u = parse_url((string) $url);
		if (($u['host'] ?? '') !== 'api.payplug.com') return $pre;
		$method = strtoupper($args['method'] ?? 'GET');
		$h = $args['headers'] ?? array();
		$body = isset($args['body']) && is_string($args['body']) ? json_decode($args['body'], true) : null;
		self::$log[] = array('method' => $method, 'url' => (string) $url, 'headers' => $h, 'body' => $body);
		if (self::$down) return new WP_Error('http_request_failed', 'provider down');
		$path = $u['path'] ?? '';
		$sig = $method . ' ' . $path;
		if (isset(self::$once[$sig])) {
			$o = self::$once[$sig]; unset(self::$once[$sig]);
			if (is_string($o)) { $f = efb_ppg_fixture($o); return self::answer($f['response']['code'], $f['response']['body']); }
			return self::answer($o[0], $o[1]);
		}
		$auth = (string) ($h['Authorization'] ?? '');
		if (($h['PayPlug-Version'] ?? '') !== '2019-08-06') return self::answer(400, array('object' => 'error', 'message' => 'missing PayPlug-Version'));
		if (strpos($auth, 'Bearer ') !== 0) { $f = efb_ppg_fixture('error-401'); return self::answer(401, $f['response']['body']); }
		$key = substr($auth, 7);
		if ($key === efb_ppg_key('test')) $live = false;
		elseif ($key === efb_ppg_key('live')) $live = true;
		else { $f = efb_ppg_fixture('error-401'); return self::answer(401, $f['response']['body']); }

		if ($method === 'GET' && $path === '/v1/payments') {
			$list = efb_ppg_fixture('list-payments')['response']['body'];
			$list['data'][0]['is_live'] = $live;
			return self::answer(200, $list);
		}
		if ($method === 'POST' && $path === '/v1/payments') {
			// Oney, as documented (https://docs.payplug.com/api/apiref.html, "Specific parameters rules for
			// Oney payments"): authorized_amount (not amount) 10000-300000, payment_context.cart, billing and
			// shipping with the eight fields, shipping company_name and delivery_type, the same country in
			// FR IT GP MQ GF RE YT, no "+" in the e-mails.
			if (isset($body['payment_method']) && strpos((string) $body['payment_method'], 'oney_') === 0) {
				$bad = array();
				if (isset($body['amount']) || !isset($body['authorized_amount'])) $bad[] = 'amount';
				$aa = (int) ($body['authorized_amount'] ?? 0);
				if ($aa < 10000 || $aa > 300000) $bad[] = 'authorized_amount';
				if (empty($body['payment_context']['cart']) || !is_array($body['payment_context']['cart'])) $bad[] = 'payment_context';
				foreach (array('billing', 'shipping') as $o) {
					foreach (array('first_name', 'last_name', 'address1', 'postcode', 'city', 'country', 'email', 'mobile_phone_number') as $k) {
						if (empty($body[$o][$k])) $bad[] = $o . '.' . $k;
					}
					if (isset($body[$o]['email']) && strpos((string) $body[$o]['email'], '+') !== false) $bad[] = $o . '.email';
				}
				if (empty($body['shipping']['company_name'])) $bad[] = 'shipping.company_name';
				if (empty($body['shipping']['delivery_type'])) $bad[] = 'shipping.delivery_type';
				if (($body['billing']['country'] ?? '') !== ($body['shipping']['country'] ?? '') || !in_array($body['billing']['country'] ?? '', array('FR', 'IT', 'GP', 'MQ', 'GF', 'RE', 'YT'), true)) $bad[] = 'billing.country';
				if ($bad) return self::answer(400, array('object' => 'error', 'message' => 'The parameters are invalid.', 'details' => array_fill_keys($bad, 'invalid')));
				$body['amount'] = $aa;
			}
			$amount = (int) ($body['amount'] ?? 0);
			if ($amount < 30 || $amount > 2000000 || ($body['currency'] ?? '') !== 'EUR') { $f = efb_ppg_fixture('error-400'); return self::answer(400, $f['response']['body']); }
			if (!isset($body['billing']) || !isset($body['shipping'])) return self::answer(400, array('object' => 'error', 'message' => 'billing and shipping are required'));
			$p = efb_ppg_fixture('create-201')['response']['body'];
			$id = 'pay_' . substr(str_replace(array('+', '/', '='), '', base64_encode(random_bytes(24))), 0, 22);
			$p['id'] = $id;
			$p['is_live'] = $live;
			$p['amount'] = $amount;
			$p['billing'] = $body['billing'];
			$p['shipping'] = $body['shipping'];
			$p['metadata'] = $body['metadata'] ?? array();
			$p['description'] = $body['description'] ?? null;
			$p['integration'] = $body['integration'] ?? null;
			$p['payment_method'] = isset($body['payment_method']) ? array('type' => $body['payment_method']) : null;
			$p['hosted_payment']['return_url'] = $body['hosted_payment']['return_url'] ?? null;
			$p['hosted_payment']['cancel_url'] = $body['hosted_payment']['cancel_url'] ?? null;
			$p['hosted_payment']['payment_url'] = 'https://secure.payplug.com/pay/' . substr($id, 4);
			$p['notification']['url'] = $body['notification_url'] ?? null;
			self::$payments[$id] = $p;
			if (self::$lose_next) { self::$lose_next = false; return new WP_Error('http_request_failed', 'lost answer'); }
			return self::answer(201, $p);
		}
		if (preg_match('#^/v1/payments/(pay_[A-Za-z0-9]+)(/refunds)?$#', $path, $m)) {
			$id = $m[1];
			if (!isset(self::$payments[$id]) || (bool) self::$payments[$id]['is_live'] !== $live) return self::answer(404, array('object' => 'error', 'message' => 'not found', 'id' => 'err_404'));
			$p = self::$payments[$id];
			if (empty($m[2]) && $method === 'GET') return self::answer(200, $p);
			if (empty($m[2]) && $method === 'PATCH') {
				if (!empty($body['aborted']) && empty($p['is_paid'])) self::set($id, 'get-failure-aborted');
				return self::answer(200, self::$payments[$id]);
			}
			if (!empty($m[2]) && $method === 'GET') {
				$data = array_values(array_filter(self::$refunds, function ($r) use ($id) { return $r['payment_id'] === $id; }));
				return self::answer(200, array('object' => 'list', 'page' => 0, 'per_page' => 100, 'has_more' => false, 'data' => $data));
			}
			if (!empty($m[2]) && $method === 'POST') {
				$amt = isset($body['amount']) ? (int) $body['amount'] : $p['amount'] - $p['amount_refunded'];
				if (empty($p['is_paid']) || $amt < 10 || $amt > $p['amount'] - $p['amount_refunded']) return self::answer(400, array('object' => 'error', 'message' => 'not refundable', 'id' => 'err_rf'));
				$r = efb_ppg_fixture('refund-201')['response']['body'];
				$r['id'] = 're_' . substr(bin2hex(random_bytes(12)), 0, 22);
				$r['payment_id'] = $id; $r['amount'] = $amt; $r['is_live'] = $live; $r['metadata'] = $body['metadata'] ?? array();
				self::$refunds[] = $r;
				self::$payments[$id]['amount_refunded'] += $amt;
				if (self::$payments[$id]['amount_refunded'] >= $p['amount']) self::$payments[$id]['is_refunded'] = true;
				if (self::$lose_next) { self::$lose_next = false; return new WP_Error('http_request_failed', 'lost answer'); }
				return self::answer(201, $r);
			}
		}
		return self::answer(404, array('object' => 'error', 'message' => 'not found', 'id' => 'err_404'));
	}
}

/**
 * The environment: PAY00 tables, the Payplug gateway registered (the test gateway too, from
 * efb_pay_env()), keys stored for both modes, the fake provider answering api.payplug.com.
 */
function efb_ppg_env($mode = 'test', $settings = array()) {
	$db = efb_pay_env();
	require_once __DIR__ . '/../vendor/payplug/payplugefb.php';
	EfbPayplugFake::reset();
	add_filter('pre_http_request', array('EfbPayplugFake', 'http'), 10, 3);
	add_action('efb_register_payment_gateways', function ($reg) { $reg->register(new \Emsfb\Payments\Payplug_Gateway()); });
	efb_ppg_settings($mode, $settings);
	return $db;
}

function efb_ppg_settings($mode = 'test', $settings = array()) {
	$g = new \Emsfb\Payments\Payplug_Gateway();
	$s = \Emsfb\Payments\Settings::load('payplug');
	$s->apply($g->settings_schema(), array_merge(array('api_key__test' => efb_ppg_key('test'), 'api_key__live' => efb_ppg_key('live'), 'card_mode' => 'hosted', 'default_country' => 'FR'), $settings), $mode);
	return $s;
}

/** A Payplug form: name / address fields mapped for billing, a priced radio, the gateway field. */
function efb_ppg_form($pf = array(), $f0 = array()) {
	return array(
		array_merge(array('type' => 'payment', 'getway' => 'payplug', 'currency' => 'EUR', 'trackingCode' => '1', 'email' => 'owner@example.test', 'email_to' => 'em'), $f0),
		array('type' => 'step', 'id_' => '1', 'step' => 1),
		array('id_' => 'em', 'type' => 'email', 'name' => 'Email', 'step' => 1),
		array('id_' => 'fn', 'type' => 'text', 'name' => 'Prénom', 'step' => 1),
		array('id_' => 'ln', 'type' => 'text', 'name' => 'Nom', 'step' => 1),
		array('id_' => 'ad', 'type' => 'text', 'name' => 'Adresse', 'step' => 1),
		array('id_' => 'pc', 'type' => 'text', 'name' => 'Code postal', 'step' => 1),
		array('id_' => 'ci', 'type' => 'text', 'name' => 'Ville', 'step' => 1),
		array('id_' => 'rad', 'type' => 'payRadio', 'name' => 'Ticket', 'step' => 1),
		array('id_' => 'ro1', 'parent' => 'rad', 'type' => 'option', 'value' => 'Atelier', 'price' => '25'),
		array('id_' => 'ro2', 'parent' => 'rad', 'type' => 'option', 'value' => 'Grand atelier', 'price' => '150'),
		array_merge(array('id_' => 'pf', 'type' => 'payplug', 'name' => 'Paiement', 'step' => 1, 'pf_fixed' => '8',
			'pgw_map_first' => 'fn', 'pgw_map_last' => 'ln', 'pgw_map_address' => 'ad', 'pgw_map_postcode' => 'pc', 'pgw_map_city' => 'ci'), $pf),
	);
}

function efb_ppg_rows($option = 'ro1', $extra = array()) {
	return array_merge(array(
		array('id_' => 'em', 'type' => 'email', 'value' => 'jeanne@example.test'),
		array('id_' => 'fn', 'type' => 'text', 'value' => 'Jeanne'),
		array('id_' => 'ln', 'type' => 'text', 'value' => 'Martin <b>'),
		array('id_' => 'ad', 'type' => 'text', 'value' => '1 rue de la Paix'),
		array('id_' => 'pc', 'type' => 'text', 'value' => '75002'),
		array('id_' => 'ci', 'type' => 'text', 'value' => 'Paris'),
		array('id_' => 'rad', 'id_ob' => $option, 'type' => 'payRadio', 'value' => 'x', 'price' => '0.01'),
	), $extra);
}

function efb_ppg_uuid() {
	return sprintf('%08x-%04x-4%03x-8%03x-%012x', mt_rand(), mt_rand(0, 65535), mt_rand(0, 4095), mt_rand(0, 4095), mt_rand());
}

/** Service::create() for a Payplug form. Returns array(answer, payment row|null). */
function efb_ppg_create($db, $form = null, $rows = null, $extra = array()) {
	$form = $form ?: efb_ppg_form();
	$fid = $db->add_form($form, 'payment');
	$rows = $rows ?: efb_ppg_rows();
	$res = \Emsfb\Payments\Service::create(array_merge(array(
		'form_id' => $fid, 'form' => $form, 'rows' => $rows, 'field_id' => 'pf', 'attempt_key' => efb_ppg_uuid(), 'method' => '',
		'expected_minor' => null, 'page_url' => 'https://site.example/atelier/', 'locale' => 'fr_FR', 'ip' => '203.0.113.9', 'payer_email' => 'jeanne@example.test',
		'description' => 'Inscription atelier',
		'store_entry' => function ($rows) use ($db, $fid) {
			$id = $db->add_entry($fid, $rows, gmdate('Y-m-d H:i:s'), array('read_' => 2, 'track' => 'EFB' . mt_rand(10000, 99999)));
			return array('track' => $db->get_var('SELECT track FROM wp_emsfb_msg_ WHERE msg_id = ' . (int) $id), 'msg_id' => $id);
		},
	), $extra));
	$p = !empty($res['pay']['uid']) ? \Emsfb\Payments\Store::find_by_uid($res['pay']['uid']) : null;
	return array($res, $p);
}

/** The last request the fake saw for METHOD and a path prefix. */
function efb_ppg_last($method, $path_prefix) {
	for ($i = count(EfbPayplugFake::$log) - 1; $i >= 0; $i--) {
		$l = EfbPayplugFake::$log[$i];
		if ($l['method'] === $method && strpos(parse_url($l['url'], PHP_URL_PATH), $path_prefix) === 0) return $l;
	}
	return null;
}

/** A Payplug notification to our webhook URL (token from the URL the provider was given, or forged). */
function efb_ppg_notify($p, $body, $token = null, $uid = null) {
	$r = new WP_REST_Request('POST');
	$r->set_param('gateway', 'payplug');
	$uid = $uid === null ? $p['uid'] : $uid;
	$r->set_param('p', $uid);
	$r->set_param('t', $token === null ? \Emsfb\Payments\Payplug_Gateway::notify_token($uid) : $token);
	$r->set_body(is_string($body) ? $body : json_encode($body));
	return $r;
}

function efb_ppg_events($p) {
	return array_map(function ($e) { return $e['type']; }, \Emsfb\Payments\Store::events($p['id']));
}
