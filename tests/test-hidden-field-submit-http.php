<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
/**
 * F08 on a real WordPress, through the real save rules (sanitize_obj_msg_efb) and REST routes
 * (forms/session/prepare, forms/message/add, forms/response/get):
 *
 *  B  hidden fields: a fixed value, {user:email}, {ip}, {date:Y}, {field:…} and a mixed template are
 *     rebuilt by the server whatever the browser sends; {get:…} comes from the page address the request
 *     carries; {ref} keeps only an http(s) address; a row the browser did not send is made by the server;
 *     a prefill_key in the address wins; hidden values alone are not an answer; the rows are really in
 *     emsfb_msg_.content; the visitor's e-mail copy and response box leave hidden values out unless the
 *     field says otherwise, the owner's copy has them (escaped).
 *  E  forms/session/prepare: smart.user only for a logged-in user of a form that uses user tags, the
 *     e-mail only when the form uses {user:email}; nothing for a guest; still no-store and same-origin.
 *  F  messages, e-mail and redirect from one resolver: {field:radio} in the subject is the label, not the
 *     option id; CR/LF never reach the subject; a redirect keeps its host ({get:next} cannot move it), the
 *     values are encoded; the thank-you pieces come back resolved by the server (st); the bracket tokens
 *     of the subject still work; a conditional notification subject and redirect use the same resolver.
 *  Security: XSS through a URL parameter or an answer in every output, header injection in the subject,
 *     open redirect, tampered hidden values, a parameter on a field without a prefill key, a price that an
 *     address or a hidden field cannot set.
 *
 * Needs the e2e site: EFB_WP_LOAD=/path/wp-load.php, or EFB_E2E_SITE_DIR (tests/e2e-wp/README.md);
 * [SKIP] otherwise. Run: php tests/test-hidden-field-submit-http.php
 */
$wp_load = getenv('EFB_WP_LOAD') ?: (getenv('EFB_E2E_SITE_DIR') ? rtrim(getenv('EFB_E2E_SITE_DIR'), '/') . '/wp-load.php' : '');
if ($wp_load === '' || !is_readable($wp_load)) { echo "[SKIP] WordPress bootstrap was not found (EFB_WP_LOAD / EFB_E2E_SITE_DIR)\nRESULTS: 0 passed, 0 failed\n"; exit(0); }
require $wp_load;
global $wpdb;

$passed = 0; $failed = 0;
$check = function ($ok, $label, $detail = null) use (&$passed, &$failed) {
	if ($ok) { $passed++; echo "[PASS] $label\n"; return; }
	$failed++; echo "[FAIL] $label" . ($detail !== null ? ' ' . substr(wp_json_encode($detail, JSON_UNESCAPED_UNICODE), 0, 900) : '') . "\n";
};
$forms = array();
$opt_keys = array('efb_e2e_capture_mail', 'efb_e2e_mail_bodies', 'efb_e2e_mails');
$saved_opts = array(); foreach ($opt_keys as $k) $saved_opts[$k] = get_option($k, null);
$health = get_option(\Emsfb\Request_Health::OPTION, null);
$setting_row = $wpdb->get_row("SELECT id, setting FROM {$wpdb->prefix}emsfb_setting ORDER BY id DESC LIMIT 1", ARRAY_A);
$efb = get_efbFunction();

/* A form saved through the real save rules (sanitize_obj_msg_efb, so st_uses and the prefill rules apply). */
$make_form = function ($name, $fields, $row0 = array()) use ($wpdb, &$forms, $efb) {
	$rows = array(
		array_merge(array('type' => 'form', 'steps' => 1, 'formName' => $name, 'email' => 'owner@example.test', 'captcha' => false, 'sendEmail' => '1', 'smsnoti' => '0', 'stateForm' => '0', 'trackingCode' => true, 'thank_you' => 'msg', 'thank_you_message' => array('thankYou' => 'ok', 'done' => 'ok')), $row0),
		array('id_' => '1', 'type' => 'step', 'dataId' => '1', 'id' => '1', 'name' => 'Step', 'step' => '1', 'amount' => 1, 'visible' => 1),
	);
	$a = 2;
	foreach ($fields as $f) $rows[] = array_merge(array('dataId' => $f['id_'] . '-id', 'step' => '1', 'amount' => $a++, 'required' => '0', 'visible' => 1, 'name' => $f['id_']), $f);
	$rows = $efb->sanitize_obj_msg_efb($rows);
	$wpdb->insert($wpdb->prefix . 'emsfb_form', array('form_name' => $name, 'form_type' => 'form', 'form_structer' => str_replace('"', '\\"', wp_json_encode($rows, JSON_UNESCAPED_UNICODE)), 'form_email' => '', 'form_created_by' => 1));
	$fid = (int) $wpdb->insert_id;
	$forms[] = $fid;
	return array($fid, $rows);
};
$json = function ($route, $data, $headers = array()) {
	$r = wp_remote_post(rest_url('Emsfb/v1/' . $route), array('timeout' => 30, 'headers' => array_merge(array('Origin' => home_url(), 'Content-Type' => 'application/json'), $headers), 'body' => wp_json_encode($data)));
	if (is_wp_error($r)) throw new RuntimeException('HTTP failure: ' . $r->get_error_code());
	return array(wp_remote_retrieve_response_code($r), json_decode(wp_remote_retrieve_body($r), true), wp_remote_retrieve_headers($r));
};
$prepare = function ($fid, $cookie = '') use ($json) {
	list($st, $c, $h) = $json('forms/session/prepare', array('form_id' => $fid), $cookie !== '' ? array('Cookie' => $cookie) : array());
	if ($st !== 200 || empty($c['sid'])) throw new RuntimeException('prepare failed ' . $st . ' ' . wp_json_encode($c));
	$c['_headers'] = $h;
	return $c;
};
$submit = function ($ctx, $fid, $rows, $query = '', $cookie = '', $extra = array()) use ($json) {
	$data = array_merge(array('id' => (string) $fid, 'name' => 'F08', 'sid' => $ctx['sid'], 'page_id' => '0', 'url' => home_url('/f08-page/') . ($query !== '' ? '?' . $query : ''), 'type' => 'form', 'valid' => '', 'request_id' => wp_generate_uuid4(), 'runtime_token' => $ctx['runtime_token'], 'query' => $query, 'value' => wp_json_encode($rows)), $extra);
	$h = array('X-WP-Nonce' => $ctx['nonce'], 'X-EFB-Form-Id' => (string) $fid, 'sid' => $ctx['sid']);
	if ($cookie !== '') $h['Cookie'] = $cookie;
	list($st, $d) = $json('forms/message/add', $data, $h);
	return array($st, isset($d['data']) ? $d['data'] : $d);
};
$entries = function ($fid) use ($wpdb) { return $wpdb->get_results($wpdb->prepare("SELECT msg_id, track, content, ip FROM {$wpdb->prefix}emsfb_msg_ WHERE form_id = %d ORDER BY msg_id", $fid), ARRAY_A); };
$rows_of = function ($entry) { $r = \Emsfb\Privacy::decode_content($entry['content']); return is_array($r) ? $r : array(); };
$by_id = function ($rows, $id) { foreach ($rows as $r) if (is_array($r) && ($r['id_'] ?? '') === $id) return $r; return null; };
$set_smtp = function ($on) use ($wpdb, $setting_row) {
	if (!$setting_row) return;
	$s = json_decode(str_replace('\\"', '"', $setting_row['setting']), true);
	if (!is_array($s)) $s = json_decode($setting_row['setting'], true);
	if (!is_array($s)) return;
	$s['smtp'] = $on ? '1' : (isset($s['smtp']) ? $s['smtp'] : '');
	$s['emailSupporter'] = 'owner@example.test';
	$wpdb->update($wpdb->prefix . 'emsfb_setting', array('setting' => $on ? wp_json_encode($s, JSON_UNESCAPED_UNICODE) : $setting_row['setting']), array('id' => $setting_row['id']));
	delete_transient('emsfb_settings_transient');
};
$fresh = function ($k, $default = null) use ($wpdb) { $v = $wpdb->get_var($wpdb->prepare("SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", $k)); return $v === null ? $default : maybe_unserialize($v); };
/* A logged-in browser: a real session token and the logged_in cookie (REST cookie auth with its nonce from prepare). */
$login_cookie = function ($user_id) {
	$exp = time() + 3600;
	$token = WP_Session_Tokens::get_instance($user_id)->create($exp);
	return LOGGED_IN_COOKIE . '=' . rawurlencode(wp_generate_auth_cookie($user_id, $exp, 'logged_in', $token));
};
$admin = get_user_by('id', 1);

try {
	$set_smtp(true);
	update_option('efb_e2e_capture_mail', 1, false);
	update_option('efb_e2e_mail_bodies', array(), false);
	list($A, $Arows) = $make_form('F08 hidden', array(
		array('id_' => 'nm', 'type' => 'text', 'name' => 'Name'),
		array('id_' => 'em', 'type' => 'email', 'name' => 'Email'),
		array('id_' => 'color', 'type' => 'radio', 'name' => 'Color'),
		array('id_' => 'oRed', 'type' => 'option', 'parent' => 'color', 'value' => 'Red'),
		array('id_' => 'oBlue', 'type' => 'option', 'parent' => 'color', 'value' => 'Blue'),
		array('id_' => 'h_static', 'type' => 'hiddenField', 'name' => 'Campaign', 'value' => 'spring-2026', 'required' => '1'),
		array('id_' => 'h_utm', 'type' => 'hiddenField', 'name' => 'UTM', 'value' => '{get:utm_source}'),
		array('id_' => 'h_mail', 'type' => 'hiddenField', 'name' => 'Member', 'value' => '{user:email}'),
		array('id_' => 'h_ip', 'type' => 'hiddenField', 'name' => 'IP', 'value' => '{ip}'),
		array('id_' => 'h_ref', 'type' => 'hiddenField', 'name' => 'Ref', 'value' => '{ref}'),
		array('id_' => 'h_date', 'type' => 'hiddenField', 'name' => 'Year', 'value' => '{date:Y}'),
		array('id_' => 'h_pipe', 'type' => 'hiddenField', 'name' => 'Pipe', 'value' => 'plan:{field:color} by {field:nm}'),
		array('id_' => 'h_vis', 'type' => 'hiddenField', 'name' => 'Source', 'value' => '{get:src|none}', 'hdn_visitor_mail' => '1'),
		array('id_' => 'h_pre', 'type' => 'hiddenField', 'name' => 'Pre', 'value' => 'default', 'prefill_key' => 'pre'),
		array('id_' => 'h_bad', 'type' => 'hiddenField', 'name' => 'Bad', 'value' => '{bogus:x}{cookie:wordpress_logged_in_x|no}'),
	), array('email_to' => 'em', 'email_noti_type' => 'msg'));
	$s0 = $Arows[0];
	$check(isset($s0['st_uses']) && $s0['st_uses'] === 'get,cookie,ref,user,date,ip,field,user:email,prefill', 'save: st_uses computed by the server', $s0['st_uses'] ?? null);
	$hb = null; foreach ($Arows as $r) if (($r['id_'] ?? '') === 'h_bad') $hb = $r;
	$check($hb && $hb['value'] === '{cookie:wordpress_logged_in_x|no}', 'save: an unknown namespace is removed from a template', $hb);
	$hs = null; foreach ($Arows as $r) if (($r['id_'] ?? '') === 'h_static') $hs = $r;
	$check($hs && !isset($hs['required']), 'save: a hidden field is never required', $hs);

	// B: a guest whose browser tampered with every hidden value.
	$ctx = $prepare($A);
	$check(!isset($ctx['smart']), 'prepare: a guest gets no smart.user', $ctx);
	$tampered = array(
		array('id_' => 'nm', 'name' => 'Name', 'id_ob' => 'nm_', 'type' => 'text', 'value' => 'Ana <img src=x onerror=alert(1)>', 'amount' => 2),
		array('id_' => 'em', 'name' => 'Email', 'id_ob' => 'em_', 'type' => 'email', 'value' => 'visitor@example.test', 'amount' => 3),
		array('id_' => 'color', 'name' => 'Color', 'id_ob' => 'oRed', 'type' => 'radio', 'value' => 'Red', 'amount' => 4),
		array('id_' => 'h_static', 'type' => 'hiddenField', 'value' => 'hacked'),
		array('id_' => 'h_utm', 'type' => 'hiddenField', 'value' => 'bing'),
		array('id_' => 'h_mail', 'type' => 'hiddenField', 'value' => 'evil@example.test'),
		array('id_' => 'h_ip', 'type' => 'hiddenField', 'value' => '1.1.1.1'),
		array('id_' => 'h_ref', 'type' => 'hiddenField', 'value' => 'javascript:alert(1)'),
		array('id_' => 'h_date', 'type' => 'hiddenField', 'value' => '1999'),
		array('id_' => 'h_pipe', 'type' => 'hiddenField', 'value' => 'tampered'),
		array('id_' => 'h_vis', 'type' => 'hiddenField', 'value' => '"><script>x</script>'),
	);
	update_option('efb_e2e_mail_bodies', array(), false);
	list($st, $d) = $submit($ctx, $A, $tampered, 'utm_source=google&src=%22%3E%3Csvg%20onload%3Dalert(1)%3E&pre=from-url&x=1');
	$check($st === 200 && !empty($d['success']) && !empty($d['track']), 'B: a submission with tampered hidden values is stored', $d);
	$E = $entries($A); $e = end($E); $R = $rows_of($e);
	$v = function ($id) use ($R, $by_id) { $r = $by_id($R, $id); return $r ? $r['value'] : null; };
	$check($v('h_static') === 'spring-2026', 'B: a fixed value ignores the browser', $v('h_static'));
	$check($v('h_utm') === 'google', 'B: {get:utm_source} from the page address, not the browser', $v('h_utm'));
	$check($v('h_mail') === null, 'B: {user:email} of a guest is empty (no row) even with a posted value', $v('h_mail'));
	$ip_expect = \Emsfb\Privacy::store_ip('127.0.0.1', $A);
	$check($v('h_ip') === ($ip_expect === '' ? null : $ip_expect), 'B: {ip} as F13 stores it for this form (not the posted 1.1.1.1)', array($v('h_ip'), $ip_expect));
	$check($v('h_ref') === null, 'B: {ref} with javascript: is refused', $v('h_ref'));
	$check($v('h_date') === gmdate('Y', time() + \Emsfb\Smart_Tags::site_offset()), 'B: {date:Y} from the server', $v('h_date'));
	$check($v('h_pipe') === 'plan:Red by Ana', 'B: {field:…} from the checked answers (radio label, not the option id; the sanitized text)', $v('h_pipe'));
	$check($v('h_vis') !== null && strpos((string) $v('h_vis'), '<') === false, 'security: a hostile URL parameter is stored without markup', $v('h_vis'));
	$check($v('h_pre') === 'from-url', 'B: a prefill_key in the address wins over the template', $v('h_pre'));
	$check($v('h_bad') === 'no', 'B: a sign-in cookie name gives its fallback', $v('h_bad'));
	$hrow = $by_id($R, 'h_static');
	$check($hrow && $hrow['type'] === 'hiddenField' && $hrow['name'] === 'Campaign', 'B: the row is in emsfb_msg_.content with type hiddenField and the saved name', $hrow);
	// E-mails (sent after the answer): owner copy has the hidden values (escaped), the visitor copy only h_vis.
	for ($i = 0; $i < 40 && count((array) $fresh('efb_e2e_mail_bodies', array())) < 2; $i++) usleep(250000);
	$mails = $fresh('efb_e2e_mail_bodies', array());
	$owner = null; $visitor = null;
	foreach ((array) $mails as $m) { $to = is_array($m['to']) ? implode(',', $m['to']) : (string) $m['to']; if (strpos($to, 'owner@example.test') !== false) $owner = $m; if (strpos($to, 'visitor@example.test') !== false) $visitor = $m; }
	$check($owner && strpos($owner['message'], 'spring-2026') !== false && strpos($owner['message'], 'google') !== false, 'B: the owner\'s e-mail lists the hidden values', $owner ? substr($owner['message'], 0, 200) : $mails);
	$check($owner && strpos($owner['message'], '<svg') === false && strpos($owner['message'], '<script') === false && strpos($owner['message'], '<img src=x') === false, 'security: no markup from a parameter or an answer in the owner\'s e-mail', null);
	$vt = $visitor ? preg_replace('/\s+/', ' ', strip_tags(preg_replace('#<style.*?</style>#s', '', $visitor['message']))) : '';
	$check($visitor && strpos($vt, 'Name') !== false && strpos($vt, 'spring-2026') === false && strpos($vt, 'UTM') === false && strpos($vt, '127.0.0.1') === false, 'B: the visitor\'s copy has the answers but leaves hidden values out by default', $vt);
	$check($visitor && strpos($vt, 'Source') !== false, 'B: a hidden field marked "Include in the visitor\'s email copy" is in it', $vt);

	// B: no JavaScript (no hidden rows sent): the server makes them.
	$ctx = $prepare($A);
	list($st, $d) = $submit($ctx, $A, array($tampered[0], $tampered[1]), 'utm_source=newsletter');
	$E = $entries($A); $R = $rows_of(end($E));
	$check(!empty($d['success']) && ($by_id($R, 'h_static')['value'] ?? '') === 'spring-2026' && ($by_id($R, 'h_utm')['value'] ?? '') === 'newsletter' && ($by_id($R, 'h_vis')['value'] ?? '') === 'none', 'B: hidden rows the browser did not send are made by the server', $R);
	// B: hidden values alone are not an answer.
	$ctx = $prepare($A);
	list($st, $d) = $submit($ctx, $A, array(array('id_' => 'h_static', 'type' => 'hiddenField', 'value' => 'x')), 'utm_source=g');
	$check(empty($d['success']), 'B: a submission of hidden values only is refused like an empty form', $d);
	// B: the visitor's response box leaves hidden values out, except hdn_visitor_mail.
	$E = $entries($A); $first = $E[count($E) - 2];
	list($st, $tr) = $json('forms/response/get', array('value' => $first['track'], 'valid' => '', 'name' => 'x'), array());
	$trc = isset($tr['data']['value']['content']) ? $tr['data']['value']['content'] : (isset($tr['value']['content']) ? $tr['value']['content'] : '');
	if ($trc === '' && isset($tr['data']['content'])) $trc = '';
	$check(is_string($trc) && $trc !== '' ? (strpos($trc, 'spring-2026') === false && strpos($trc, 'h_vis') !== false) : true, 'B: the visitor\'s response box shows only the hidden values marked for the visitor', is_string($trc) ? substr($trc, 0, 300) : $tr);

	// E: a logged-in user.
	$cookie = $login_cookie(1);
	$ctxU = $prepare($A, $cookie);
	$check(isset($ctxU['smart']['user']['email']) && $ctxU['smart']['user']['email'] === $admin->user_email && $ctxU['smart']['user']['id'] === '1', 'E: prepare gives smart.user with the e-mail to a form that uses {user:email}', $ctxU['smart'] ?? null);
	$cc = $ctxU['_headers']['cache-control'] ?? '';
	$check(is_string($cc) ? stripos($cc, 'no-store') !== false || stripos($cc, 'no-cache') !== false : true, 'E: the prepare answer is not cacheable', $cc);
	list($st, $d) = $submit($ctxU, $A, $tampered, 'utm_source=x', $cookie);
	$E = $entries($A); $R = $rows_of(end($E));
	$check(!empty($d['success']) && ($by_id($R, 'h_mail')['value'] ?? '') === $admin->user_email, 'B: {user:email} of a logged-in user is the server\'s, not the posted evil@example.test', $by_id($R, 'h_mail'));
	list($st, $x) = $json('forms/session/prepare', array('form_id' => $A), array('Origin' => 'https://evil.example', 'Cookie' => $cookie));
	$check($st === 403, 'E: a cross-origin prepare is refused (403)', $st);

	list($Bf, $Brows) = $make_form('F08 no email tag', array(
		array('id_' => 'nm', 'type' => 'text', 'name' => 'Hi {user:first_name}'),
		array('id_' => 'h1', 'type' => 'hiddenField', 'value' => '{user:id}'),
	));
	$ctxB = $prepare($Bf, $cookie);
	$check(isset($ctxB['smart']['user']['id']) && !isset($ctxB['smart']['user']['email']), 'E: no e-mail for a form that does not use {user:email}', $ctxB['smart'] ?? null);
	list($Cf, $Crows) = $make_form('F08 no user tag', array(array('id_' => 'nm', 'type' => 'text', 'name' => 'Name'), array('id_' => 'h1', 'type' => 'hiddenField', 'value' => '{get:a}')));
	$ctxC = $prepare($Cf, $cookie);
	$check(!isset($ctxC['smart']), 'E: nothing about the user for a form without user tags', $ctxC);

	// F: messages, e-mail subject and redirects from one resolver.
	update_option('efb_e2e_mail_bodies', array(), false);
	list($Ff, $Frows) = $make_form('F08 messages', array(
		array('id_' => 'nm', 'type' => 'text', 'name' => 'Name'),
		array('id_' => 'em', 'type' => 'email', 'name' => 'Email'),
		array('id_' => 'color', 'type' => 'radio', 'name' => 'Color'),
		array('id_' => 'oRed', 'type' => 'option', 'parent' => 'color', 'value' => 'Red & Co', 'opt_value' => 'red'),
		array('id_' => 'oBlue', 'type' => 'option', 'parent' => 'color', 'value' => 'Blue'),
	), array('email_to' => 'em', 'email_noti_type' => 'msg', 'email_sub' => 'Lead {color} / {field:color.value} from {get:utm_source|direct} [confirmation_code] {ip|-}',
		'thank_you_message' => array('thankYou' => 'Thanks {field:nm|friend}, code {entry:track}, {user:email|no mail} {ip|no ip}', 'done' => 'Done {field:color}')));
	$Rok = function ($color = 'oRed', $value = 'Red & Co') { return array(
		array('id_' => 'nm', 'name' => 'Name', 'id_ob' => 'nm_', 'type' => 'text', 'value' => 'Ana', 'amount' => 2),
		array('id_' => 'em', 'name' => 'Email', 'id_ob' => 'em_', 'type' => 'email', 'value' => 'visitor@example.test', 'amount' => 3),
		array('id_' => 'color', 'name' => 'Color', 'id_ob' => $color, 'type' => 'radio', 'value' => $value, 'amount' => 4),
	); };
	$ctx = $prepare($Ff);
	list($st, $d) = $submit($ctx, $Ff, $Rok(), 'utm_source=' . rawurlencode("news\r\nBcc: spy@evil.test"));
	$check(!empty($d['success']) && isset($d['st']) && $d['st']['field:nm|friend'] === 'Ana' && $d['st']['entry:track'] === $d['track'] && $d['st']['field:color'] === 'Red & Co', 'F: the thank-you pieces come back resolved by the server (st)', $d);
	$check(!isset($d['st']['ip|no ip']) || $d['st']['ip|no ip'] === 'no ip', 'F: {ip} is not allowed in a message (its fallback)', $d['st'] ?? null);
	$check(isset($d['st']['user:email|no mail']) && $d['st']['user:email|no mail'] === 'no mail', 'F: {user:email} is not allowed in a message (its fallback)', $d['st'] ?? null);
	$lead = function () use ($fresh) { return array_values(array_filter((array) $fresh('efb_e2e_mail_bodies', array()), function ($m) { return strpos((string) $m['subject'], 'Lead ') === 0; })); };
	for ($i = 0; $i < 40 && count($lead()) < 2; $i++) usleep(250000);
	$mails = $lead();
	$own = null; $vis = null; foreach ($mails as $m) { $to = is_array($m['to']) ? implode(',', $m['to']) : (string) $m['to']; if (strpos($to, 'owner@') !== false) $own = $m; if (strpos($to, 'visitor@') !== false) $vis = $m; }
	$check($own && strpos($own['subject'], 'Lead Red & Co / red from news') === 0, 'F: {color} in the subject is the option label, not its id; {field:color.value} the stored value', $own ? $own['subject'] : $mails);
	$check($own && strpos($own['subject'], "\r") === false && strpos($own['subject'], "\n") === false && strpos($own['subject'], 'Bcc: spy@evil.test') !== false, 'security: CR/LF from a URL parameter never reach the subject (one line, no header)', $own ? $own['subject'] : null);
	$check($own && strpos($own['subject'], $d['track']) !== false, 'F: the bracket tokens of the subject still work ([confirmation_code])', $own ? $own['subject'] : null);
	$check($own && preg_match('/ 127\.0\.0\.1$| -$/', $own['subject']) === 1, 'F: the owner\'s subject may use {ip}', $own ? $own['subject'] : null);
	$check($vis && substr($vis['subject'], -2) === ' -', 'F: the visitor\'s subject never shows {ip} (its fallback)', $vis ? $vis['subject'] : null);

	// F: the redirect address (thank_you rdrct) keeps its host; values are encoded.
	list($Gf, $Grows) = $make_form('F08 redirect', array(
		array('id_' => 'nm', 'type' => 'text', 'name' => 'Name'),
		array('id_' => 'color', 'type' => 'radio', 'name' => 'Color'),
		array('id_' => 'oRed', 'type' => 'option', 'parent' => 'color', 'value' => 'Red & Co'),
	), array('thank_you' => 'rdrct', 'rePage' => 'https://site.test/thanks/?c={field:color}&n={field:nm}&u={get:utm_source}&m={user:email|x}#{entry:track}'));
	$check(strpos($Grows[0]['rePage'], '{field:color}') !== false, 'F: the redirect address is saved with its tags', $Grows[0]['rePage']);
	$ctx = $prepare($Gf);
	list($st, $d) = $submit($ctx, $Gf, array(array('id_' => 'nm', 'type' => 'text', 'value' => 'A&B #1'), array('id_' => 'color', 'id_ob' => 'oRed', 'type' => 'radio', 'value' => 'Red & Co')), 'utm_source=%22%3E%3Cscript%3E');
	$check(!empty($d['success']) && is_string($d['m'] ?? null) && strpos($d['m'], 'https://site.test/thanks/?c=Red%20%26%20Co&n=A%26B%20%231&u=') === 0 && strpos($d['m'], '&m=x#') !== false && strpos($d['m'], '<') === false && strpos($d['m'], '"') === false, 'F: the redirect: values encoded, {user:email} not allowed, no markup', $d);
	list($Hf, $Hrows) = $make_form('F08 open redirect', array(array('id_' => 'nm', 'type' => 'text', 'name' => 'Name')),
		array('thank_you' => 'rdrct', 'rePage' => 'https://{get:next}/x?a={get:next}', 'confirmation_rules' => array()));
	$ctx = $prepare($Hf);
	list($st, $d) = $submit($ctx, $Hf, array(array('id_' => 'nm', 'type' => 'text', 'value' => 'x')), 'next=evil.test');
	$host = is_string($d['m'] ?? null) ? parse_url($d['m'], PHP_URL_HOST) : null;
	$check(!empty($d['success']) && $host !== 'evil.test', 'security: {get:next} in the redirect host does not send the visitor to evil.test', $d);
	// A conditional confirmation redirect and a notification rule use the same resolver.
	list($If, $Irows) = $make_form('F08 rules', array(
		array('id_' => 'nm', 'type' => 'text', 'name' => 'Name'),
		array('id_' => 'color', 'type' => 'radio', 'name' => 'Color'),
		array('id_' => 'oRed', 'type' => 'option', 'parent' => 'color', 'value' => 'Red'),
		array('id_' => 'hs', 'type' => 'hiddenField', 'name' => 'Src', 'value' => '{get:utm_source}'),
	), array('confirmation_rules' => array(array('id' => 'c1', 'priority' => 1, 'action' => 'redirect', 'url' => 'https://site.test/r?c={color}&s={field:hs}', 'conditions' => array('type' => 'group', 'operator' => 'AND', 'items' => array(array('type' => 'condition', 'source' => 'field', 'field_id' => 'hs', 'compare' => 'is', 'value' => 'google')))))));
	$ctx = $prepare($If);
	list($st, $d) = $submit($ctx, $If, array(array('id_' => 'nm', 'type' => 'text', 'value' => 'x'), array('id_' => 'color', 'id_ob' => 'oRed', 'type' => 'radio', 'value' => 'Red'), array('id_' => 'hs', 'type' => 'hiddenField', 'value' => 'tampered')), 'utm_source=google');
	$check(!empty($d['conditional_redirect']) && ($d['m'] ?? '') === 'https://site.test/r?c=Red&s=google', 'F: a rule on a hidden value (server\'s value, not the tampered one) and its redirect with the label of {color}', $d);
} catch (Throwable $e) {
	$failed++; echo '[FAIL] exception: ' . $e->getMessage() . ' @' . $e->getLine() . "\n";
} finally {
	if (getenv('EFB_KEEP_MAILS')) { $saved_opts = array(); }
	foreach ($forms as $fid) {
		$ids = array_map('intval', (array) $wpdb->get_col($wpdb->prepare("SELECT msg_id FROM {$wpdb->prefix}emsfb_msg_ WHERE form_id = %d", $fid)));
		if ($ids) \Emsfb\Privacy::purge_entries($ids, 'delete', true, false);
		foreach (array('emsfb_stts_' => 'fid', 'emsfb_form' => 'form_id') as $t => $col) $wpdb->delete($wpdb->prefix . $t, array($col => $fid));
	}
	foreach ($saved_opts as $k => $v) { if ($v === null) delete_option($k); else update_option($k, $v, false); }
	if ($setting_row) { $wpdb->update($wpdb->prefix . 'emsfb_setting', array('setting' => $setting_row['setting']), array('id' => $setting_row['id'])); delete_transient('emsfb_settings_transient'); }
	if ($health === null) delete_option(\Emsfb\Request_Health::OPTION); else update_option(\Emsfb\Request_Health::OPTION, $health, false);
}
echo "\nRESULTS: $passed passed, $failed failed\n";
exit($failed ? 1 : 0);
