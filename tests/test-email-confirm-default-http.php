<?php
/**
 * 4.3.2 (owner request) on the real WordPress of tests/e2e-wp: the visitor's confirmation e-mail is on by default.
 *
 *  - a new form built the way AutoPilot / SitePilot builds it on the server (Builder_Api::field_templates('email') with
 *    its form_patch, then Builder_Api::email_recipient_default()) and saved through the builder's sanitizer: the e-mail
 *    field has noti 1, email_to names it, sendEmail is on;
 *  - a visitor sends it: the confirmation e-mail goes to the address they typed (captured mail), and the owner's one
 *    to the form's address;
 *  - the visitor leaves the optional e-mail empty: the entry is stored, no e-mail to a visitor, nothing errors;
 *    an invalid address is refused by the server's check (no mail);
 *  - an old form (noti not set, email_to false): unchanged, no e-mail to the visitor;
 *  - a second e-mail field (field_templates('email', ['recipient' => false])) stays off.
 *
 * Run (the e2e site, under the e2e lock): php tests/test-email-confirm-default-http.php
 */
require __DIR__ . '/f11-http-lib.php';
$H = new EfbF11Http();
if ($why = $H->unavailable()) { echo "[SKIP] {$why}\n"; echo "\nRESULTS: 0 passed, 0 failed\n"; exit(0); }

$pass = 0; $fail = 0;
function t($label, $actual, $expected) {
	global $pass, $fail;
	if ($actual === $expected) { $pass++; echo "[PASS] $label\n"; return; }
	$fail++; echo "[FAIL] $label\n      expected " . json_encode($expected) . "\n      actual   " . json_encode($actual) . "\n";
}
$tmp = sys_get_temp_dir() . '/efb-mailconf-' . getmypid() . '.php';
/** Runs PHP in the site (WP-CLI) and returns its last line as JSON. */
$wp = function ($php) use ($H, $tmp) {
	file_put_contents($tmp, "<?php\n" . $php);
	$parts = array_merge(preg_split('/\s+/', trim($H->wpcli)), array('eval-file', $tmp, '--path=' . $H->site, '--allow-root'));
	$lines = array_values(array_filter(explode("\n", (string) shell_exec(implode(' ', array_map('escapeshellarg', $parts)) . ' 2>/dev/null')), 'strlen'));
	return json_decode($lines ? end($lines) : '', true);
};
/* A new form as the server builds it: row 0, step 1, a name, the e-mail template(s); $old: noti / email_to as before 4.3.2. */
$make = function ($name, $two = false, $old = false) use ($wp) {
	return $wp('wp_set_current_user(1); global $wpdb;
		$rows = array(array("type" => "form", "steps" => 1, "formName" => ' . var_export($name, true) . ', "email" => "owner@example.test", "email_to" => false, "sendEmail" => false, "trackingCode" => "1", "EfbVersion" => 2, "button_single_text" => "Send", "button_color" => "btn-darkb", "icon" => "bi-ui-checks-grid", "button_state" => "single", "show_icon" => "1", "thank_you" => "msg", "stateForm" => 0, "dShowBg" => "1", "btns_align" => "justify-content-center", "label_text_color" => "text-labelEfb", "el_text_color" => "text-labelEfb", "message_text_color" => "text-muted", "icon_color" => "text-white", "el_height" => "h-d-efb"),
			array("id_" => "1", "type" => "step", "dataId" => "1", "id" => "1", "name" => "Step 1", "step" => 1, "amount" => 1, "visible" => 1, "icon" => "bi-ui-checks-grid", "message" => ""));
		$a = 2; $ids = array();
		foreach (array_merge(array(array("text", array())), array(array("email", array())), ' . ($two ? 'array(array("email", array("recipient" => false)))' : 'array()') . ') as $p) {
			$t = \Emsfb\Builder_Api::field_templates($p[0], $p[1]);
			foreach ($t["rows"] as $r) { $r["amount"] = $a++; $r["step"] = 1; $r["required"] = 0; $rows[] = $r; $ids[] = $r["id_"]; }
			if (!empty($t["form_patch"])) foreach ($t["form_patch"] as $k => $v) $rows[0][$k] = $v;
		}
		' . ($old ? 'foreach ($rows as $i => $r) { unset($rows[$i]["noti"]); } $rows[0]["email_to"] = false; $rows[0]["sendEmail"] = false;' : '$rows = \Emsfb\Builder_Api::email_recipient_default($rows);') . '
		$rows = get_efbFunction()->sanitize_obj_msg_efb($rows);
		$wpdb->insert($wpdb->prefix . "emsfb_form", array("form_name" => ' . var_export($name, true) . ', "form_structer" => str_replace("\"", "\\\\\"", wp_json_encode($rows, JSON_UNESCAPED_UNICODE)), "form_email" => "", "form_type" => "form", "form_created_by" => 1, "form_access_by" => "", "form_create_date" => current_time("mysql"), "status" => 1));
		$fid = (int) $wpdb->insert_id;
		echo wp_json_encode(array("form_id" => $fid, "ids" => $ids, "row0" => array("email_to" => $rows[0]["email_to"], "sendEmail" => $rows[0]["sendEmail"]), "noti" => array_values(array_map(function ($r) { return array_key_exists("noti", $r) ? $r["noti"] : null; }, array_filter($rows, function ($r) { return isset($r["type"]) && $r["type"] === "email"; })))));');
};
$rowsOf = function ($F, $email, $email2 = null) {
	$r = array(array('id_' => $F['ids'][0], 'name' => 'Text', 'type' => 'text', 'value' => 'Ada'));
	if ($email !== null) $r[] = array('id_' => $F['ids'][1], 'name' => 'Email', 'type' => 'email', 'value' => $email);
	if ($email2 !== null) $r[] = array('id_' => $F['ids'][2], 'name' => 'Email 2', 'type' => 'email', 'value' => $email2);
	return $r;
};
$to = function ($mails) { $o = array(); foreach ((array) $mails as $m) foreach ((array) $m['to'] as $x) $o[] = strtolower((string) $x); sort($o); return $o; };
/* The site's own addresses (the form's address, the site's support address) are the owner's copies; anything else is a visitor's. */
$visitors = function ($list) { return array_values(array_filter($list, function ($a) { return !in_array($a, array('owner@example.test', 'admin@example.test'), true); })); };
$made = array();
$smtp0 = $H->ctl('setting', 'smtp');
try {
	$H->ctl('plan', '3');
	$H->ctl('f12-setting', 'smtp=1&secretKey=del&siteKey=del');

	echo "--- a new form built on the server ---\n";
	$F = $make('Mail default ' . getmypid());
	$made[] = $F['form_id'];
	t('the e-mail field is the recipient: noti 1, email_to its id, sendEmail on (after the real sanitizer)', array($F['noti'], $F['row0']['email_to'] === $F['ids'][1], in_array($F['row0']['sendEmail'], array(true, '1', 1), true)), array(array('1'), true, true));
	$H->ctl('mails', 'clear');
	$a = EfbF11Http::answer($H->submit($F['form_id'], $rowsOf($F, 'visitor.one@example.test')));
	$m = $H->ctl('mails');
	t('a visitor sends it: success; the confirmation goes to the address typed, the owner\'s to the form address', array(!empty($a['success']), in_array('visitor.one@example.test', $to($m), true), in_array('owner@example.test', $to($m), true)), array(true, true, true));

	$H->ctl('mails', 'clear');
	$a = EfbF11Http::answer($H->submit($F['form_id'], $rowsOf($F, null)));
	$m = $H->ctl('mails');
	t('the optional e-mail left empty: stored, no e-mail to a visitor (only the owner\'s), nothing errors', array(!empty($a['success']), $visitors($to($m)), in_array('owner@example.test', $to($m), true)), array(true, array(), true));

	$H->ctl('mails', 'clear');
	$a = EfbF11Http::answer($H->submit($F['form_id'], $rowsOf($F, 'not-an-address')));
	t('an invalid address: refused by the server\'s check, no e-mail', array(!empty($a['success']), $to($H->ctl('mails'))), array(false, array()));

	echo "--- two e-mail fields ---\n";
	$F2 = $make('Mail two ' . getmypid(), true);
	$made[] = $F2['form_id'];
	t('a second e-mail field (recipient => false) stays off; email_to keeps the first', array($F2['noti'], $F2['row0']['email_to'] === $F2['ids'][1]), array(array('1', null), true));
	$H->ctl('mails', 'clear');
	$a = EfbF11Http::answer($H->submit($F2['form_id'], $rowsOf($F2, 'first@example.test', 'second@example.test')));
	$m = $to($H->ctl('mails'));
	t('… the confirmation goes to the first only', array(!empty($a['success']), in_array('first@example.test', $m, true), in_array('second@example.test', $m, true)), array(true, true, false));

	echo "--- an old form ---\n";
	$O = $make('Mail old ' . getmypid(), false, true);
	$made[] = $O['form_id'];
	t('an old form keeps its choice (no noti, email_to false): nothing switched on', array($O['noti'], $O['row0']['email_to']), array(array(null), ''));
	$H->ctl('mails', 'clear');
	$a = EfbF11Http::answer($H->submit($O['form_id'], $rowsOf($O, 'old.visitor@example.test')));
	$om = $to($H->ctl('mails'));
	t('… a visitor sends it: no e-mail to the visitor (only the owner\'s)', array(!empty($a['success']), $visitors($om), in_array('owner@example.test', $om, true)), array(true, array(), true));
} finally {
	foreach ($made as $f) $H->ctl('f12-delete-form', (string) $f);
	$H->ctl('f12-setting', 'smtp=' . ($smtp0 === null ? 'del' : rawurlencode((string) $smtp0)));
	$H->ctl('set-option', 'efb_e2e_capture_mail', 'del');
	@unlink($tmp);
}
echo "\nRESULTS: {$pass} passed, {$fail} failed\n";
exit($fail ? 1 : 0);
