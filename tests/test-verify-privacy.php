<?php
/**
 * F02 × F13 privacy: an address is stored only as an HMAC plus a mask; the IP follows the form's IP
 * mode (none → no IP hash, anon → the hash of the anonymized address, so not of the real one);
 * WordPress' exporter lists the challenges of an e-mail address without the code; the eraser removes
 * them and the address's current counters, other addresses keep theirs; drafts (F11) never keep a
 * token or a code (the verification keys are request keys, not answer rows).
 *
 * Run: php tests/test-verify-privacy.php
 */
require __DIR__ . '/verify-test-lib.php';
use Emsfb\Verification as V;

$db = vt_env();
$fid = vt_form(array(vt_email('eml1', 'otp')));
list($st, $d) = vt_send($fid, 'eml1', 'Person@Example.com');
vt('sent', $st, 200);
$row = $db->row('wp_emsfb_vrfy_', "challenge_id = '{$d['challenge_id']}'");
vt('the row holds no address, only its HMAC and a mask', array(strpos(json_encode($row), 'person@example.com'), $row['dest_mask']), array(false, 'pe***@example.com'));
vt('IP mode "store": the IP is an HMAC of the address', $row['ip_hash'], V::ip_hash('203.0.113.7'));
$GLOBALS['vt_ip_mode'] = 'none';
V::$now += 61;
list($st, $d2) = vt_send($fid, 'eml1', 'other@example.com', 'sid-2');
vt('IP mode "none": no IP hash at all', $db->get_var("SELECT ip_hash FROM wp_emsfb_vrfy_ WHERE challenge_id = '{$d2['challenge_id']}'"), '');
$GLOBALS['vt_ip_mode'] = 'anon';
list($st, $d3) = vt_send($fid, 'eml1', 'third@example.com', 'sid-3');
vt('IP mode "anon": the hash of the anonymized address, not of the real one', $db->get_var("SELECT ip_hash FROM wp_emsfb_vrfy_ WHERE challenge_id = '{$d3['challenge_id']}'"), V::ip_hash('203.0.113.0'));
unset($GLOBALS['vt_ip_mode']);

V::register_exporter(array());
$ex = V::export('PERSON@example.com');
vt('exporter: the address\'s challenge (any case), masked, no code', array(count($ex['data']), $ex['data'][0]['group_id'], strpos(json_encode($ex), vt_last_code())), array(1, 'efb-verification', false));
vt('exporter: nothing for an unknown address', V::export('nobody@example.com')['data'], array());
vt('exporters / erasers registered under easy-form-builder-verification', array(isset(V::register_exporter(array())['easy-form-builder-verification']), isset(V::register_eraser(array())['easy-form-builder-verification'])), array(true, true));
$before = $db->count('wp_emsfb_vrfy_rl_');
$er = V::erase('person@example.com');
vt('eraser: removes the address\'s challenge', array($er['items_removed'], $db->count('wp_emsfb_vrfy_', "dest_mask = 'pe***@example.com'")), array(true, 0));
vt('eraser: other addresses keep theirs', $db->count('wp_emsfb_vrfy_'), 2);
vt('eraser: the address\'s own counters are gone (fewer counter rows)', $db->count('wp_emsfb_vrfy_rl_') < $before, true);

/* drafts never hold a token or a code */
$draft = file_get_contents(__DIR__ . '/../includes/class-Emsfb-drafts.php');
vt('drafts store answer rows only (clean_rows); no verifications / email_confirms key is read', array(strpos($draft, 'verifications'), strpos($draft, 'email_confirms')), array(false, false));
$js = file_get_contents(__DIR__ . '/../public/assets/js/verify-efb.js');
vt('the browser keeps tokens in memory only (no localStorage / sessionStorage)', array(strpos($js, 'localStorage'), strpos($js, 'sessionStorage')), array(false, false));

vt_done();
