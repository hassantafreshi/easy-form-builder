<?php
/**
 * AutoPilot B5 (server): SMS settings checked on save, as the builder checks them.
 *
 * With the SMS add-on active (a recording stand-in for vendor/smssended/smsefb.php), the real
 * update_form_id_Emsfb() / add_form_structure() must: give empty SMS texts the defaults (only
 * missing ones did), pass only admin numbers that match ^\+[0-9]{8,15}$ after the builder's own
 * clean-up (Persian digits, separators, 00 -> +, 09... -> +98 on a Persian site) and warn about
 * the rest, and warn when a mobile field asks for the visitor SMS while the form's SMS is off.
 *
 * Run: php tests/test-ap-b5-sms-save.php
 */
require __DIR__ . '/ap-admin-stubs.php';
function get_locale() { return 'fa_IR'; }
if (!function_exists('list_files')) { function list_files() { return array(); } }
if (!function_exists('get_transient')) { function get_transient() { return 'no'; } }
require_once __DIR__ . '/../includes/admin/class-Emsfb-create.php';

// The SMS add-on, as a recorder.
@mkdir(EMSFB_PLUGIN_DIRECTORY . '/vendor/smssended', 0777, true);
file_put_contents(EMSFB_PLUGIN_DIRECTORY . '/vendor/smssended/smsefb.php', '<?php namespace Emsfb; class smssendefb { public function add_sms_contact_efb() { $GLOBALS["a5"]["sms"] = func_get_args(); return 1; } }');
$GLOBALS['a5']['settings'] = (object) array('AdnSS' => 1, 'AdnTLG' => 0, 'emailSupporter' => '');
$GLOBALS['a5']['options']['emsfb_addon_AdnSS'] = 1; // add_form_structure() reads the legacy option
$GLOBALS['a5']['text'] += array('newMessageReceived' => 'New', 'trackNo' => 'Code', 'url' => 'URL', 'newResponse' => 'Reply', 'WeRecivedUrM' => 'Got it', 'smsAdminPhoneInvalid' => 'Bad: %s');

$pass = 0; $fail = 0;
function b5($label, $ok, $detail = null) {
	global $pass, $fail;
	if ($ok) { $pass++; echo "[PASS] $label\n"; } else { $fail++; echo "[FAIL] $label" . ($detail !== null ? ' ' . json_encode($detail, JSON_UNESCAPED_UNICODE) : '') . "\n"; }
}
function b5_call($class, $method, $post) {
	$o = (new ReflectionClass($class))->newInstanceWithoutConstructor();
	$db = new EfbA5Db();
	$p = new ReflectionProperty($class, 'db'); $p->setAccessible(true); $p->setValue($o, $db);
	$_POST = $post;
	$GLOBALS['a5']['sms'] = null;
	try { $o->$method(); } catch (EfbA5Stop $s) { return $s->payload; }
	return null;
}
$form = array(
	array('type' => 'form', 'formName' => 'F', 'steps' => 1, 'smsnoti' => 1, 'sms_msg_new_noti' => '', 'sms_msg_recived_usr' => '', 'sms_msg_responsed_noti' => 'Mine',
		'sms_admins_phone_no' => '0912 345 6789, +44 20 7946 0958, 12, ۰۰۹۸۹۱۲۳۴۵۶۷۸۹'),
	array('type' => 'step', 'id_' => '1', 'step' => 1, 'amount' => 1),
	array('id_' => 'mob1', 'type' => 'mobile', 'name' => 'Phone', 'step' => 1, 'amount' => 2, 'smsnoti' => 1),
);
$r = b5_call('\Emsfb\Admin', 'update_form_id_Emsfb', array('value' => json_encode($form, JSON_UNESCAPED_UNICODE), 'id' => '7', 'name' => 'F', 'nonce' => 'n'));
$sms = $GLOBALS['a5']['sms'];
b5('update: the save succeeds and the add-on gets the contact', is_array($r) && $r['success'] === true && is_array($sms), $r);
b5('admin numbers: cleaned, Persian 09 -> +98, 00 -> +, duplicates once', is_array($sms) && $sms[1] === '+989123456789,+442079460958', $sms ? $sms[1] : null);
b5('an invalid number is dropped with a warning naming it', is_array($r) && isset($r['warnings'][0]) && $r['warnings'][0]['code'] === 'sms_admin_phone_invalid' && $r['warnings'][0]['numbers'] === array('12') && $r['warnings'][0]['m'] === 'Bad: 12', $r);
b5('empty texts get the defaults, a written one is kept', is_array($sms) && $sms[3] === "New\nCode: [confirmation_code]\nURL: [link_response]" && $sms[2] === "Got it\nCode: [confirmation_code]\nURL: [link_response]" && $sms[5] === 'Mine', $sms);

$form[0]['smsnoti'] = 0;
$r = b5_call('\Emsfb\Admin', 'update_form_id_Emsfb', array('value' => json_encode($form, JSON_UNESCAPED_UNICODE), 'id' => '7', 'name' => 'F', 'nonce' => 'n'));
b5('a mobile field asking for SMS while the form\'s SMS is off: saved with a warning', is_array($r) && $r['success'] === true && $r['warnings'][0]['code'] === 'sms_field_without_form', $r);

$form[0]['smsnoti'] = 1;
$r = b5_call('\Emsfb\Create', 'add_form_structure', array('value' => json_encode($form, JSON_UNESCAPED_UNICODE), 'name' => 'F', 'type' => 'form', 'nonce' => 'n'));
$sms = $GLOBALS['a5']['sms'];
b5('create: same checks on a new form', is_array($r) && $r['success'] === true && is_array($sms) && $sms[1] === '+989123456789,+442079460958' && $r['warnings'][0]['code'] === 'sms_admin_phone_invalid', array($r, $sms));

// The helper on its own (non-Persian site: 09... is not rewritten).
list($ok, $bad) = \Emsfb\Builder_Api::sms_admin_numbers('09123456789,+1 (202) 555-0123', 'en_US');
b5('outside a Persian site 09... stays invalid; +1 (202) 555-0123 is valid', $ok === array('+12025550123') && $bad === array('09123456789'), array($ok, $bad));

echo "\n========================================\nRESULTS: $pass passed, $fail failed\n========================================\n";
exit($fail > 0 ? 1 : 0);
