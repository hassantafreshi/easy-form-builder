<?php
/**
 * F02-F: the server's verification gate of every submit (spec §5.6, §9):
 *  1. Verification::validate_submission(): a form without verification keys passes untouched (old forms
 *     behave as today); a missing token, a token of another field / form / session / value, a consumed
 *     or expired token, a value changed after verification are refused with the field id; an optional
 *     empty field passes; "always" refuses an empty one; a field hidden by conditional logic or disabled
 *     passes; "type it twice" compares the normalized copies (Persian digits, case, direction marks) and
 *     uses the field's own msg_mismatch (F03 hook); a downgraded plan or a channel that cannot deliver
 *     refuses (fail-closed); registration / login forms are not gated in v1.
 *  2. Single use through the submit: consume() once, the second submit with the same token is refused,
 *     a failed insert gives it back (release).
 *  3. The handler's wiring, read from the source: the gate after the field loop's refusal and before the
 *     rows are encoded, for every submit endpoint (forms/message/add, payments/create through the same
 *     handler, the legacy gateway follow-up, the not-stored path); the consume in insert_message_db()
 *     after the F11 places and F03 claims; release on a failed insert; the request keys outside the
 *     replay fingerprint (PHP and JS); a browser-sent "verified" key is dropped by
 *     filter_attributes_by_type_efb() (allow-list) and only the server sets it.
 *
 * Run: php tests/test-verify-submit-gate.php
 */
require __DIR__ . '/verify-test-lib.php';
use Emsfb\Verification as V;

$db = vt_env();
vt_provider(array('ir'));
$form = array(array('type' => 'form'), vt_mobile('mob1'), vt_email('eml1', 'retype_otp'), array('id_' => 'txt1', 'type' => 'text', 'name' => 'Name'));
$fid = 41;

/** A verified challenge + its token for a field and value. */
function vt_verified($fid, $field, $ch, $value, $sid = 'sid-a') {
	$norm = V::normalize_destination($ch, $value);
	$r = V::create_challenge(array('fid' => $fid, 'field_id' => $field, 'channel' => $ch, 'dest_hash' => V::dest_hash($ch, $norm), 'dest_mask' => V::mask($ch, $norm), 'sid' => $sid, 'code' => '111111', 'max_attempts' => 5, 'ttl' => 300));
	V::mark_verified($r['challenge_id']);
	return V::token_issue(array('cid' => $r['challenge_id'], 'fid' => $fid, 'fld' => $field, 'ch' => $ch, 'dh' => V::dest_hash($ch, $norm), 'sid' => $sid));
}
$ctx = function ($items, $tokens = array(), $confirms = array(), $extra = array()) use ($fid, $form) {
	return array_merge(array('fid' => $fid, 'sid' => 'sid-a', 'runtime_sid' => 'sid-a', 'fields' => $form, 'items' => $items, 'ignored' => array(), 'tokens' => $tokens, 'email_confirms' => $confirms), $extra);
};
$code = function ($r) { return $r instanceof WP_Error ? array($r->get_error_code(), $r->get_error_data()['field_id']) : 'ok'; };

/* ---------------------------------------------------------------- 1 */
$plain = array(array('type' => 'form'), array('id_' => 'm', 'type' => 'mobile', 'name' => 'Mobile'), array('id_' => 'e', 'type' => 'email', 'name' => 'Email'));
vt('a form without the keys: no claims, nothing refused (old forms as today)', V::validate_submission(array('fid' => 1, 'sid' => 's', 'fields' => $plain, 'items' => array(array('id_' => 'm', 'value' => '+989121234567'), array('id_' => 'e', 'value' => 'a@b.co')))), array());
$tm = vt_verified($fid, 'mob1', 'sms', '+989121234567');
$te = vt_verified($fid, 'eml1', 'email', 'Ali@Example.com');
$items = array(array('id_' => 'mob1', 'value' => '+989121234567'), array('id_' => 'eml1', 'value' => 'ali@example.com'), array('id_' => 'txt1', 'value' => 'x'));
$ok = V::validate_submission($ctx($items, array('mob1' => $tm, 'eml1' => $te), array('eml1' => "ALI@example.com\u{200F}")));
vt('both verified, the copy matches after normalization → two claims', array(array_keys($ok), $ok['mob1']['channel'], $ok['eml1']['channel']), array(array('mob1', 'eml1'), 'sms', 'email'));
vt('the claim carries the stored value', $ok['mob1']['value'], '+989121234567');
vt('no token for the mobile → efb_verify_required at mob1', $code(V::validate_submission($ctx($items, array('eml1' => $te), array('eml1' => 'ali@example.com')))), array('efb_verify_required', 'mob1'));
vt('the e-mail token used for the mobile field → refused', $code(V::validate_submission($ctx($items, array('mob1' => $te, 'eml1' => $te), array('eml1' => 'ali@example.com')))), array('efb_verify_required', 'mob1'));
$items_b = $items; $items_b[0]['value'] = '+989129999999';
vt('value swap: number A verified, number B sent → refused', $code(V::validate_submission($ctx($items_b, array('mob1' => $tm, 'eml1' => $te), array('eml1' => 'ali@example.com')))), array('efb_verify_required', 'mob1'));
vt('another session → refused', $code(V::validate_submission($ctx($items, array('mob1' => $tm, 'eml1' => $te), array('eml1' => 'ali@example.com'), array('sid' => 'sid-b', 'runtime_sid' => 'sid-b')))), array('efb_verify_required', 'mob1'));
vt('a runtime proof of another session → refused', $code(V::validate_submission($ctx($items, array('mob1' => $tm, 'eml1' => $te), array('eml1' => 'ali@example.com'), array('runtime_sid' => 'sid-z')))), array('efb_verify_required', 'mob1'));
vt('another form → refused', $code(V::validate_submission($ctx($items, array('mob1' => $tm, 'eml1' => $te), array('eml1' => 'ali@example.com'), array('fid' => 42)))), array('efb_verify_required', 'mob1'));
vt('a forged token → refused', $code(V::validate_submission($ctx($items, array('mob1' => $tm . 'x', 'eml1' => $te), array('eml1' => 'ali@example.com')))), array('efb_verify_required', 'mob1'));
vt('a token as an array → refused', $code(V::validate_submission($ctx($items, array('mob1' => array($tm), 'eml1' => $te), array('eml1' => 'ali@example.com')))), array('efb_verify_required', 'mob1'));
vt('the copy differs → efb_verify_mismatch at eml1', $code(V::validate_submission($ctx($items, array('mob1' => $tm, 'eml1' => $te), array('eml1' => 'other@example.com')))), array('efb_verify_mismatch', 'eml1'));
vt('the copy missing (a page older than the form) → refused with "refresh"', V::validate_submission($ctx($items, array('mob1' => $tm, 'eml1' => $te)))->get_error_message(), V::TEXTS['vrfRefresh']);
$form_msg = $form; $form_msg[2]['msg_mismatch'] = 'Your two addresses differ.';
if (!class_exists('\\Emsfb\\Field_Rules')) require_once __DIR__ . '/../includes/class-Emsfb-field-rules.php';
vt('the field\'s own msg_mismatch (F03 hook Field_Rules::mismatch_message)', V::validate_submission(array_merge($ctx($items, array('mob1' => $tm, 'eml1' => $te), array('eml1' => 'x@example.com')), array('fields' => $form_msg)))->get_error_message(), 'Your two addresses differ.');
$empty = array(array('id_' => 'txt1', 'value' => 'x'));
vt('optional and empty: nothing to verify', V::validate_submission($ctx($empty)), array());
$always = $form; $always[1]['otp_required_when'] = 'always';
vt('"always" and empty → efb_verify_required', $code(V::validate_submission(array_merge($ctx($empty), array('fields' => $always)))), array('efb_verify_required', 'mob1'));
vt('hidden by conditional logic (ignored) → passes without a token', array_keys(V::validate_submission($ctx(array(array('id_' => 'mob1', 'value' => '+989121234567')), array(), array(), array('ignored' => array('mob1')))) ?: array()), array());
$dis = $form; $dis[1]['disabled'] = 1;
vt('disabled → passes', V::validate_submission(array_merge($ctx(array(array('id_' => 'mob1', 'value' => '+989121234567'))), array('fields' => $dis))), array());
vt('the required sentence names the field (escaped)', V::validate_submission($ctx($items, array('eml1' => $te), array('eml1' => 'ali@example.com')))->get_error_message(), 'Please verify <b>Mobile</b> before continuing.');
$GLOBALS['vt_plan'] = 'free_plus';
vt('plan downgraded below Pro: SMS field refuses (fail-closed, efb_verify_unavailable)', $code(V::validate_submission($ctx($items, array('mob1' => $tm, 'eml1' => $te), array('eml1' => 'ali@example.com')))), array('efb_verify_unavailable', 'mob1'));
$GLOBALS['vt_plan'] = 'pro';
$GLOBALS['efb_filters'] = array(); V::reset_cache();
vt('SMS add-on switched off after verifying: refused (fail-closed)', $code(V::validate_submission($ctx($items, array('mob1' => $tm, 'eml1' => $te), array('eml1' => 'ali@example.com')))), array('efb_verify_unavailable', 'mob1'));
vt_provider(array('ir'));
$reg = $form; $reg[0]['type'] = 'register';
vt('registration form: not gated in v1', V::validate_submission(array_merge($ctx($items), array('fields' => $reg))), array());

/* ---------------------------------------------------------------- 2 */
$claims = array_values(V::validate_submission($ctx($items, array('mob1' => $tm, 'eml1' => $te), array('eml1' => 'ali@example.com'))));
vt('consume both on insert', V::consume($claims), 2);
vt('the same request again (replay with the same tokens) → refused', $code(V::validate_submission($ctx($items, array('mob1' => $tm, 'eml1' => $te), array('eml1' => 'ali@example.com')))), array('efb_verify_required', 'mob1'));
V::release($claims);
vt('a failed insert released them: the visitor need not verify again', count(V::validate_submission($ctx($items, array('mob1' => $tm, 'eml1' => $te), array('eml1' => 'ali@example.com')))), 2);
V::$now += 1801;
vt('after the token lifetime (30 min) → refused', $code(V::validate_submission($ctx($items, array('mob1' => $tm, 'eml1' => $te), array('eml1' => 'ali@example.com')))), array('efb_verify_required', 'mob1'));

/* ---------------------------------------------------------------- 3 */
$pub = file_get_contents(__DIR__ . '/../includes/class-Emsfb-public.php');
$p_refuse = strpos($pub, "if (\$this->field_rules_code_efb !== '') \$response['code'] = 'efb_field_' . \$this->field_rules_code_efb;");
$p_gate = strpos($pub, '// Verification gate:');
$p_encode = strpos($pub, '$this->value = $this->efb_encode_rows_for_storage_efb($validated_items);', $p_gate);
vt('the gate runs after the field loop\'s refusal and before the rows are encoded', $p_refuse !== false && $p_refuse < $p_gate && $p_gate < $p_encode, true);
$gate = substr($pub, $p_gate, strpos($pub, '// End verification gate.') - $p_gate);
vt('the gate reads the tokens and copies from the request keys, the logic\'s ignored fields, the runtime sid', array(strpos($gate, "\$request_data['verifications']") !== false, strpos($gate, "\$request_data['email_confirms']") !== false, strpos($gate, '$_ignored_set') !== false, strpos($gate, "\$runtime_proof['sid']") !== false), array(true, true, true, true));
vt('a refusal answers success:false with m, field_id and the code', strpos($gate, "wp_send_json_success(array('success' => false, 'm' => \$vr_efb->get_error_message(), 'field_id' =>") !== false, true);
vt('the badge "verified" is set by the server only, after the gate', strpos($gate, "\$vi_efb['verified'] = \$vr_efb[(string) \$vi_efb['id_']]['channel'];") !== false, true);
$ins = substr($pub, strpos($pub, 'public function insert_message_db('), 3000);
$o_unique = strpos($ins, '$this->unique_claim_efb();'); $o_vr = strpos($ins, '$this->verification_consume_efb();'); $o_insert = strpos($ins, '$this->db->insert($table_name, $row_efb)');
vt('insert_message_db: consume after the F11 places and F03 claims, before the insert', $o_unique !== false && $o_unique < $o_vr && $o_vr < $o_insert, true);
vt('a failed insert releases the codes', strpos($ins, '$this->verification_after_insert_efb(false);') !== false, true);
vt('the not-stored path (F13) consumes too', strpos(substr($pub, strpos($pub, 'public function store_entry_efb('), 1500), '$this->verification_consume_efb();') !== false, true);
vt('the legacy gateway follow-up consumes and stores the verified value', strpos($pub, "\$filtered[\$fk_efb]['value'] = \$vr_by_field_efb[(string) \$fr_efb['id_']]['value'];") !== false, true);
vt('the code payments/create runs is the same handler (gate before payment_framework_create_efb)', strpos($pub, '$this->payment_framework_create_efb(') > $p_gate, true);
vt('server phrases in $translation_keys', strpos($pub, "'vrfExpired', 'vrfRequired', 'vrfEmailMismatch', 'vrfUnavailable', 'vrfRefresh'") !== false, true);
$rt = file_get_contents(__DIR__ . '/../includes/class-Emsfb-form-runtime.php');
vt('PHP replay fingerprint leaves out verifications and email_confirms', strpos($rt, "\$data['verifications'],\$data['email_confirms']") !== false, true);
$js = file_get_contents(__DIR__ . '/../public/assets/js/request-health-efb.js');
vt('JS replay fingerprint leaves them out too', strpos($js, "'draft_token','verifications','email_confirms'") !== false, true);
// filter_attributes_by_type_efb(): a browser-sent "verified" key never survives.
preg_match('/private function filter_attributes_by_type_efb\(\$data,\$type\) \{.*?\n\t\}\n/s', $pub, $m);
vt('the allow-list of filter_attributes_by_type_efb() has no "verified"', $m && strpos($m[0], "'verified'") === false && strpos($m[0], "'value' => true") !== false, true);
// Run the real method (with its two helpers) on a row a browser sent with "verified".
$helpers = '';
foreach (array('sanitize_value_efb', 'filter_and_sanitize_attributes_efb') as $fn) {
	preg_match('/private function ' . $fn . '\(.*?\n\t\}\n/s', $pub, $h);
	$helpers .= $h[0];
}
eval('class VT_Pub_Filter { ' . str_replace('private function', 'public function', $m[0] . $helpers) . ' }');
if (!function_exists('sanitize_url')) { function sanitize_url($u) { return (string) $u; } }
$f = new VT_Pub_Filter();
vt('a mobile row with a browser-sent verified:"sms" → the key is dropped', $f->filter_attributes_by_type_efb(array('id_' => 'mob1', 'value' => '+989121234567', 'type' => 'mobile', 'verified' => 'sms'), 'mobile'), array('id_' => 'mob1', 'value' => '+989121234567', 'type' => 'mobile'));
vt('an e-mail row too', array_key_exists('verified', $f->filter_attributes_by_type_efb(array('id_' => 'e', 'value' => 'a@b.co', 'verified' => 'email'), 'email')), false);

vt_done();
