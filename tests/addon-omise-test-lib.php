<?php
/**
 * Not a test. The Omise add-on's test environment (PAY04): the PAY00 SQLite harness
 * (pay-test-db.php), the add-on loaded from vendor/omise (git-ignored symlink to
 * vendores-efb/omise) through its own routes-efb.php, test keys built at run time (no real key
 * and no key-shaped literal in the repository), and a fake api.omise.co answering through
 * pre_http_request from tests/fixtures/payments/omise/*.json (official example shapes).
 * Nothing leaves the machine.
 */
require_once __DIR__ . '/pay-test-db.php';

/** Fake key material, assembled so no key-shaped literal is stored anywhere. */
function efb_omise_keys() {
	$t = 'test' . '_';
	return array(
		'skey_test' => 'skey_' . $t . 'efbunitfixture01',
		'skey_live' => 'skey_' . 'efbunitfixturelive01',
		'pkey_test' => 'pkey_' . $t . 'efbunitfixture01',
		'pkey_live' => 'pkey_' . 'efbunitfixturelive01',
		'whsec_test' => base64_encode('efb-unit-omise-webhook-secret-test'),
		'whsec_live' => base64_encode('efb-unit-omise-webhook-secret-live'),
		'whsec_old' => base64_encode('efb-unit-omise-webhook-secret-old'),
	);
}

function efb_omise_fixture($name) {
	$f = __DIR__ . '/fixtures/payments/omise/' . $name;
	if (substr($name, -4) === '.svg') return file_get_contents($f);
	return json_decode(file_get_contents($f), true);
}

/**
 * The fake Omise. State in $GLOBALS['omise']: charges (id => charge), calls (method, url, key,
 * body), down (network error), svg (the QR body), qr_status, refs (ref_id => id).
 */
function efb_omise_fake_reset() {
	$GLOBALS['omise'] = array('charges' => array(), 'calls' => array(), 'down' => false, 'svg' => efb_omise_fixture('qrcode.svg'), 'qr_status' => 200, 'qr_headers' => array(), 'refs' => array(), 'n' => 0, 'capability' => efb_omise_fixture('capability.json')['response']['body'], 'create_override' => null);
}

function efb_omise_answer($code, $body, $headers = array()) {
	return array('response' => array('code' => $code, 'message' => ''), 'body' => is_string($body) ? $body : json_encode($body), 'headers' => $headers + array('content-type' => is_string($body) ? 'image/svg+xml' : 'application/json'), 'cookies' => array(), 'filename' => null);
}

function efb_omise_http($pre, $args, $url) {
	$host = parse_url((string) $url, PHP_URL_HOST);
	if (!in_array($host, array('api.omise.co', 'vault.omise.co', 'cdn-files.omise.co'), true)) return $pre;
	$O = &$GLOBALS['omise'];
	$method = isset($args['method']) ? strtoupper($args['method']) : 'GET';
	$auth = isset($args['headers']['Authorization']) ? (string) $args['headers']['Authorization'] : '';
	$key = strpos($auth, 'Basic ') === 0 ? rtrim((string) base64_decode(substr($auth, 6)), ':') : '';
	$body = isset($args['body']) && is_string($args['body']) ? $args['body'] : '';
	$O['calls'][] = array('method' => $method, 'url' => $url, 'key' => $key, 'body' => $body, 'headers' => isset($args['headers']) ? $args['headers'] : array());
	if ($O['down']) return new WP_Error('http_request_failed', 'omise unreachable');
	$path = (string) parse_url($url, PHP_URL_PATH);
	if ($host === 'cdn-files.omise.co') return efb_omise_answer(200, $O['svg']);
	$K = efb_omise_keys();
	$secret_ok = in_array($key, array($K['skey_test'], $K['skey_live']), true);
	$public_ok = in_array($key, array($K['pkey_test'], $K['pkey_live']), true);
	$live = in_array($key, array($K['skey_live'], $K['pkey_live']), true);
	if ($path === '/capability') return $public_ok ? efb_omise_answer(200, $O['capability']) : efb_omise_answer(401, efb_omise_fixture('error-authentication.json')['response']['body']);
	if (!$secret_ok) return efb_omise_answer(401, efb_omise_fixture('error-authentication.json')['response']['body']);
	if ($method === 'GET' && $path === '/account') {
		$a = efb_omise_fixture('account.json')['response']['body'];
		$a['livemode'] = $live;
		return efb_omise_answer(200, $a);
	}
	if ($method === 'POST' && $path === '/charges') {
		if ($O['create_override']) return call_user_func($O['create_override'], $body);
		$q = array();
		foreach (explode('&', $body) as $kv) { list($k, $v) = array_pad(explode('=', $kv, 2), 2, ''); $q[] = array(rawurldecode($k), rawurldecode($v)); }
		$get = function ($k) use ($q) { foreach ($q as $p) if ($p[0] === $k) return $p[1]; return null; };
		$ref = (string) $get('ref_id');
		if ($ref !== '' && isset($O['refs'][$ref])) return efb_omise_answer(400, array('object' => 'error', 'code' => 'bad_request', 'message' => 'ref_id has already been used'));
		$c = efb_omise_fixture('charge-promptpay-pending.json')['response']['body'];
		$O['n']++;
		$id = 'chrg_' . ($live ? '' : 'test_') . 'efbfake' . str_pad((string) $O['n'], 6, '0', STR_PAD_LEFT);
		$c['id'] = $id;
		$c['livemode'] = $live;
		$c['amount'] = (int) $get('amount');
		$c['currency'] = (string) $get('currency');
		$c['expires_at'] = (string) $get('expires_at');
		$c['ref_id'] = $ref;
		$c['description'] = (string) $get('description');
		$c['metadata'] = array();
		foreach ($q as $p) if (preg_match('/^metadata\[(\w+)\]$/', $p[0], $m)) $c['metadata'][$m[1]] = $p[1];
		$c['source']['type'] = (string) $get('source[type]');
		$c['source']['scannable_code']['image']['download_uri'] = 'https://api.omise.co/charges/' . $id . '/documents/docu_test_efbfake/downloads/47B72F81B5294A7A';
		$c['_webhook_endpoints'] = array_values(array_map(function ($p) { return $p[1]; }, array_filter($q, function ($p) { return $p[0] === 'webhook_endpoints[]'; })));
		$O['charges'][$id] = $c;
		if ($ref !== '') $O['refs'][$ref] = $id;
		$out = $c;
		unset($out['_webhook_endpoints']);
		return efb_omise_answer(200, $out);
	}
	if ($method === 'GET' && preg_match('#^/charges/(chrg_[a-z0-9_]+)/documents/[a-z0-9_]+/downloads/[A-F0-9]+$#', $path, $m)) {
		if ($O['qr_status'] !== 200) return efb_omise_answer($O['qr_status'], '', $O['qr_headers']);
		return isset($O['charges'][$m[1]]) ? efb_omise_answer(200, $O['svg']) : efb_omise_answer(404, array('object' => 'error', 'code' => 'not_found'));
	}
	if ($method === 'GET' && preg_match('#^/charges/(chrg_[a-z0-9_]+)$#', $path, $m)) {
		if (!isset($O['charges'][$m[1]])) return efb_omise_answer(404, array('object' => 'error', 'code' => 'not_found', 'message' => 'charge was not found'));
		$c = $O['charges'][$m[1]];
		unset($c['_webhook_endpoints']);
		return efb_omise_answer(200, $c);
	}
	return efb_omise_answer(404, array('object' => 'error', 'code' => 'not_found'));
}

/** Omise-side state change, from a fixture (status fields), like the Test dashboard's Actions. */
function efb_omise_set($id, $fixture, $extra = array()) {
	$f = efb_omise_fixture($fixture)['response']['body'];
	$c = &$GLOBALS['omise']['charges'][$id];
	foreach (array('status', 'paid', 'paid_at', 'failure_code', 'failure_message', 'expired', 'expired_at', 'refunded_amount') as $k) $c[$k] = $f[$k];
	foreach ($extra as $k => $v) $c[$k] = $v;
}

/**
 * The environment: PAY00 tables, the mock gateway (core) and the Omise add-on registered, the
 * fake Omise on pre_http_request, Omise test and live keys stored (mode $mode).
 */
function efb_omise_env($mode = 'test', $with_webhook_secret = true) {
	$db = efb_pay_env();
	$dir = is_dir(__DIR__ . '/../vendor/omise') ? __DIR__ . '/../vendor/omise' : '/home/user/vendores-efb/omise';
	require $dir . '/routes-efb.php'; // add_action('efb_register_payment_gateways', …) — not _once: each env registers again
	efb_omise_fake_reset();
	add_filter('pre_http_request', 'efb_omise_http', 10, 3);
	efb_omise_keys_store($mode, $with_webhook_secret);
	return $db;
}

function efb_omise_keys_store($mode = 'test', $with_webhook_secret = true, $more = array()) {
	$K = efb_omise_keys();
	$g = \Emsfb\Payments\Registry::get('omise');
	$in = array('secret_key__test' => $K['skey_test'], 'secret_key__live' => $K['skey_live'], 'public_key__test' => $K['pkey_test'], 'public_key__live' => $K['pkey_live']);
	if ($with_webhook_secret) { $in['webhook_secret__test'] = $K['whsec_test']; $in['webhook_secret__live'] = $K['whsec_live']; }
	$GLOBALS['opts']['emsfb_pgw_omise'] = array();
	return \Emsfb\Payments\Settings::load('omise')->apply($g->settings_schema(), array_merge($in, $more), $mode);
}

/** A THB form: radio (Workshop 4,000.00 / Student 1,500.50), the Omise field (fixed 20.00), e-mail. */
function efb_omise_form($pf = array()) {
	return array(
		array('type' => 'payment', 'getway' => 'omise', 'currency' => 'thb', 'trackingCode' => '1', 'email' => 'owner@example.test', 'email_to' => 'em'),
		array('type' => 'step', 'id_' => '1', 'step' => 1),
		array('id_' => 'em', 'type' => 'email', 'name' => 'Email', 'step' => 1),
		array('id_' => 'rad', 'type' => 'payRadio', 'name' => 'Ticket', 'step' => 1),
		array('id_' => 'ro1', 'parent' => 'rad', 'type' => 'option', 'value' => 'Workshop', 'price' => '4000'),
		array('id_' => 'ro2', 'parent' => 'rad', 'type' => 'option', 'value' => 'Student', 'price' => '1500.5'),
		array_merge(array('id_' => 'pf', 'type' => 'omise', 'name' => 'Pay', 'step' => 1, 'pf_fixed' => '20'), $pf),
	);
}

function efb_omise_uuid() {
	return sprintf('%08x-%04x-4%03x-8%03x-%012x', mt_rand(), mt_rand(0, 65535), mt_rand(0, 4095), mt_rand(0, 4095), mt_rand());
}

/** Service::create() for an Omise form (rows = what the browser posts). */
function efb_omise_create($db, $form = null, $rows = null, $extra = array()) {
	$form = $form ?: efb_omise_form();
	$fid = $db->add_form($form, 'payment');
	$rows = $rows !== null ? $rows : array(array('id_' => 'em', 'type' => 'email', 'value' => 'payer@example.test'), array('id_' => 'rad', 'id_ob' => 'ro1', 'type' => 'payRadio', 'value' => 'Workshop', 'price' => '0.01'));
	return \Emsfb\Payments\Service::create(array_merge(array(
		'form_id' => $fid, 'form' => $form, 'rows' => $rows, 'field_id' => 'pf', 'attempt_key' => efb_omise_uuid(), 'method' => 'promptpay',
		'expected_minor' => null, 'page_url' => 'https://site.example/thai-workshop/', 'locale' => 'th_TH', 'ip' => '203.0.113.7', 'payer_email' => 'payer@example.test',
		'store_entry' => function ($rows) use ($db, $fid) {
			$id = $db->add_entry($fid, $rows, gmdate('Y-m-d H:i:s'), array('read_' => 2, 'track' => 'TH' . mt_rand(10000, 99999)));
			return array('track' => $db->get_var('SELECT track FROM wp_emsfb_msg_ WHERE msg_id = ' . (int) $id), 'msg_id' => $id);
		},
	), $extra));
}

/** A webhook request as Omise sends it: event JSON, signed when $secret is given. */
function efb_omise_webhook($event, $secret = null, $ts = null, $sig_override = null) {
	$raw = is_string($event) ? $event : json_encode($event, JSON_UNESCAPED_SLASHES);
	$r = new WP_REST_Request('POST');
	$r->set_param('gateway', 'omise');
	$r->set_body($raw);
	if ($secret !== null) {
		$ts = $ts === null ? (string) \Emsfb\Payments\Store::time() : (string) $ts;
		$r->set_header('Omise-Signature-Timestamp', $ts);
		$r->set_header('Omise-Signature', $sig_override !== null ? $sig_override : hash_hmac('sha256', $ts . '.' . $raw, base64_decode($secret)));
	}
	return $r;
}

/** An event of the current fake charge (key, event id). */
function efb_omise_event($charge_id, $key = 'charge.complete', $event_id = 'evnt_test_5vxs0ajpo78kaqcc8ab', $livemode = false) {
	$e = efb_omise_fixture('event-charge-complete.json')['response']['body'];
	$e['id'] = $event_id;
	$e['key'] = $key;
	$e['livemode'] = $livemode;
	$e['data'] = isset($GLOBALS['omise']['charges'][$charge_id]) ? $GLOBALS['omise']['charges'][$charge_id] : array('object' => 'charge', 'id' => $charge_id);
	unset($e['data']['_webhook_endpoints']);
	return $e;
}

function efb_omise_calls($filter = null) {
	return array_values(array_filter($GLOBALS['omise']['calls'], function ($c) use ($filter) { return $filter === null || strpos($c['method'] . ' ' . parse_url($c['url'], PHP_URL_PATH), $filter) === 0; }));
}
