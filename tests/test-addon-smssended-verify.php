<?php
/**
 * F02, SMS add-on (vendor/smssended): SMS codes go through the WP SMS plugin only (owner rule).
 * The add-on registers one sender, the WP SMS bridge (verify-providers-efb.php / verify-wpsms-efb.php),
 * through core's efb_verify_providers filter. A fake wp_sms_send() answers true, a WP_Error, false or
 * throws; before WP SMS exists the sender is "not ready" and core's SMS channel is unavailable.
 * Then the whole path: verify/send → the bridge → the fake WP SMS (E.164 number, the code and the
 * WebOTP line in the text) → verify/check with the code it received.
 *
 * Run: php tests/test-addon-smssended-verify.php
 */
require __DIR__ . '/verify-test-lib.php';
use Emsfb\Verification as V;

$addon = null;
foreach (array(__DIR__ . '/../vendor/smssended', dirname(__DIR__, 2) . '/vendores-efb/smssended') as $d) {
	if (is_readable($d . '/verify-providers-efb.php')) { $addon = $d; break; }
}
if (!$addon) { echo "[SKIP] the SMS add-on (vendor/smssended) is not in this checkout\n"; exit(0); }

vt_env();
require $addon . '/verify-providers-efb.php';
V::reset_cache();
$all = V::providers();
vt('the add-on registers exactly one sender, wpsms', array_keys($all), array('wpsms'));
$b = $all['wpsms'];
vt('it is a core Provider for the sms channel', array($b instanceof \Emsfb\Verification\Provider, $b->channels()), array(true, array('sms')));
vt('no other provider file ships with the add-on', glob($addon . '/verify-adapters*.php'), array());

/* WP SMS not installed */
vt('WP SMS missing: not ready', $b->ready(), false);
vt('WP SMS missing: send fails with a short code', $b->send(array('to' => '+989121234567', 'text' => 'x')), array('ok' => false, 'error' => 'wpsms_missing'));
V::save_settings(array('sms' => array('allowed_countries' => array('ir'))));
vt('core: SMS channel unavailable (sender_not_ready)', V::channel_state('sms'), array('ready' => false, 'reason' => 'sender_not_ready'));
$fid = vt_form(array(vt_mobile('mob1')));
list($st, $d) = vt_send($fid, 'mob1', '+989121234567');
vt('core: verify/send → 503 efb_verify_unavailable (never fails open)', array($st, $d['code']), array(503, 'efb_verify_unavailable'));
$w = V::form_warnings($GLOBALS['vt_forms'][$fid]);
vt('core: the save answer warns verify_unavailable with the reason', array($w[0]['code'], $w[0]['reason']), array('verify_unavailable', 'sender_not_ready'));

/* WP SMS present: a fake that records and answers per mode */
$GLOBALS['wpsms_mode'] = 'ok';
$GLOBALS['wpsms_log'] = array();
// Defined at run time (a plain declaration would exist from the start of the script).
eval('function wp_sms_send($to, $msg, $is_flash = false, $from = null, $media = array()) {
	$GLOBALS["wpsms_log"][] = array("to" => $to, "msg" => $msg);
	switch ($GLOBALS["wpsms_mode"]) {
		case "error": return new WP_Error("gateway_error", "Kavenegar said: invalid API key 1234-secret");
		case "false": return false;
		case "throw": throw new RuntimeException("boom");
	}
	return true;
}');
vt('WP SMS active: ready', $b->ready(), true);
vt('send: true → ok', $b->send(array('to' => '+989121234567', 'text' => 'Code 123456')), array('ok' => true, 'error' => ''));
vt('the number goes as a one-item array, E.164, the text unchanged', end($GLOBALS['wpsms_log']), array('to' => array('+989121234567'), 'msg' => 'Code 123456'));
$GLOBALS['wpsms_mode'] = 'error';
$r = $b->send(array('to' => '+989121234567', 'text' => 'x'));
vt('a WP_Error → a short code, WP SMS\'s text (gateway, key) is dropped', array($r['ok'], $r['error'], strpos(json_encode($r), 'secret')), array(false, 'wpsms_gateway_error', false));
$GLOBALS['wpsms_mode'] = 'false';
vt('false → wpsms_failed', $b->send(array('to' => '+989121234567', 'text' => 'x')), array('ok' => false, 'error' => 'wpsms_failed'));
$GLOBALS['wpsms_mode'] = 'throw';
vt('an exception → wpsms_exception', $b->send(array('to' => '+989121234567', 'text' => 'x')), array('ok' => false, 'error' => 'wpsms_exception'));
vt('no number or no text → refused', $b->send(array('to' => '', 'text' => 'x'))['ok'], false);

/* The whole path through core */
$GLOBALS['wpsms_mode'] = 'ok';
V::reset_cache();
vt('core: SMS channel ready with the add-on and WP SMS', V::channel_state('sms'), array('ready' => true, 'reason' => ''));
list($st, $d) = vt_send($fid, 'mob1', '+98 912 123 4567');
$sent = end($GLOBALS['wpsms_log']);
vt('verify/send → 200 and WP SMS got one message', array($st, count($sent['to'])), array(200, 1));
preg_match('/(\d{6})/', $sent['msg'], $m);
vt('the text holds the 6-digit code and the WebOTP line @domain #code', strpos($sent['msg'], "\n\n@site.example #" . $m[1]) !== false, true);
list($st, $e) = vt_check($fid, 'mob1', $d['challenge_id'], $m[1]);
vt('the code WP SMS delivered verifies (EFB checks it, not the gateway)', array($st, V::token_verify($e['token']) !== false), array(200, true));
$GLOBALS['wpsms_mode'] = 'error';
V::$now += 120;
$GLOBALS['efb_mails'] = array();
list($st, $d) = vt_send($fid, 'mob1', '+989121234567', 'sid-x');
vt('WP SMS fails → the visitor gets the retry sentence (502), no gateway text', array($st, $d['m']), array(502, V::TEXTS['vrfFailed']));
vt('… the owner gets one e-mail about WP SMS', count(array_filter($GLOBALS['efb_mails'], function ($x) { return strpos($x['body'], 'WP SMS') !== false; })), 1);

vt_done();
