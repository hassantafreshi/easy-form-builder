<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
/**
 * F07 on a real WordPress, through the real REST routes (forms/file/upload, forms/message/add,
 * files/signed, admin-ajax): several files per field, the upload token, the per-field check of a
 * submission, the storage modes, e-mail attachments, deletion with the entry, and the security cases.
 *
 *  Upload route: ut + size in the answer, a 12-character random name; a polyglot (PNG header + PHP),
 *  a renamed HTML page, a double extension (.php.png with PHP, .png.php), an SVG with a script, a
 *  file over the field's size and a path-traversal name are refused or stored under a generated name;
 *  the quota (emsfb_upload_quota_limit through the e2e mu-plugin) refuses the next upload.
 *  Submit: four files on a field of three → efb_file_max_files at the field; one when the minimum is
 *  two → efb_file_min_files; over the total → efb_file_max_total; a tampered token, a token of another
 *  session, another form's file, another field's file, a site media file, a file already stored in an
 *  entry (replay) → efb_file_not_owned; the same file twice → refused; three good files → stored as
 *  three rows with distinct id_ob, the server's fsz (the browser's ignored) and a cleaned fnm; a page
 *  cached before F07 (no token) is accepted only for a ledger row of this form field, and refused once
 *  emsfb_upload_require_token is on. A field of one file and its old row shape still work.
 *  Private store: the file lands in uploads/efb-private/YYYY/MM with deny rules; the admin download
 *  without a login is refused; a signed link works, a tampered or expired one is refused; the
 *  owner's e-mail has a signed link, not the blocked address.
 *  Media Library: the files become private attachments (author 0, _efb_form_id / _efb_track) that the
 *  public /wp/v2/media does not list; e-mail attachments (Pro, email_attach) reach the owner's copy only.
 *  Deletion: Privacy::delete_entries() removes the files (public, private and the attachment).
 *
 * Needs the e2e site: EFB_WP_LOAD=/path/wp-load.php, or EFB_E2E_SITE_DIR (tests/e2e-wp/README.md);
 * [SKIP] otherwise. Run: php tests/test-upload-multi-submit-http.php
 */
$wp_load = getenv('EFB_WP_LOAD') ?: (getenv('EFB_E2E_SITE_DIR') ? rtrim(getenv('EFB_E2E_SITE_DIR'), '/') . '/wp-load.php' : '');
if ($wp_load === '' || !is_readable($wp_load)) { echo "[SKIP] WordPress bootstrap was not found (EFB_WP_LOAD / EFB_E2E_SITE_DIR)\nRESULTS: 0 passed, 0 failed\n"; exit(0); }
require $wp_load;
global $wpdb;

$passed = 0; $failed = 0;
$check = function ($ok, $label, $detail = null) use (&$passed, &$failed) {
	if ($ok) { $passed++; echo "[PASS] $label\n"; return; }
	$failed++; echo "[FAIL] $label" . ($detail !== null ? ' ' . substr(wp_json_encode($detail), 0, 600) : '') . "\n";
};
$forms = array(); $paths = array(); $msg_ids = array();
$opt_keys = array('efb_e2e_f07_plan', 'efb_e2e_f07_quota', 'efb_e2e_f07_require', 'efb_e2e_f07_mail', 'efb_e2e_f07_mails', 'emsfb_pending_uploads');
$saved_opts = array(); foreach ($opt_keys as $k) $saved_opts[$k] = get_option($k, null);
$health = get_option(\Emsfb\Request_Health::OPTION, null);
$setting_row = $wpdb->get_row("SELECT id, setting FROM {$wpdb->prefix}emsfb_setting ORDER BY id DESC LIMIT 1", ARRAY_A);

/* The mu-plugin of the plugin's own filters (plan, quota, token, mail capture). */
$mu_src = __DIR__ . '/e2e-wp/mu-plugins/efb-e2e-f07.php';
$mu_dst = WPMU_PLUGIN_DIR . '/efb-e2e-f07.php';
if (!is_readable($mu_dst) || md5_file($mu_dst) !== md5_file($mu_src)) { if (!is_dir(WPMU_PLUGIN_DIR)) wp_mkdir_p(WPMU_PLUGIN_DIR); copy($mu_src, $mu_dst); sleep(3); }
update_option('efb_e2e_f07_plan', 1, false);
delete_option('efb_e2e_f07_quota'); delete_option('efb_e2e_f07_require');
if (class_exists('\Emsfb\Rate_Limit')) \Emsfb\Rate_Limit::reset_scopes(array('upq', 'upip', 'fdl'));

$make_form = function ($name, $fields, $row0 = array()) use ($wpdb, &$forms) {
	$rows = array(
		array_merge(array('type' => 'form', 'steps' => 1, 'formName' => $name, 'email' => 'owner@example.test', 'captcha' => false, 'sendEmail' => '1', 'smsnoti' => '0', 'stateForm' => '0', 'trackingCode' => true, 'thank_you' => 'msg', 'thank_you_message' => array('thankYou' => 'ok', 'done' => 'ok')), $row0),
		array('id_' => '1', 'type' => 'step', 'dataId' => '1', 'id' => '1', 'name' => 'Step', 'step' => '1', 'amount' => 1, 'visible' => 1),
	);
	$a = 2;
	foreach ($fields as $id => $f) $rows[] = array_merge(array('id_' => $id, 'dataId' => $id . '-id', 'type' => 'file', 'name' => $id, 'step' => '1', 'amount' => $a++, 'required' => '0', 'visible' => 1, 'file' => 'allformat', 'value' => 'allformat', 'max_fsize' => '1'), $f);
	$wpdb->insert($wpdb->prefix . 'emsfb_form', array('form_name' => $name, 'form_type' => 'form', 'form_structer' => str_replace('"', '\\"', wp_json_encode($rows, JSON_UNESCAPED_UNICODE)), 'form_email' => '', 'form_created_by' => 1));
	$fid = (int) $wpdb->insert_id;
	$forms[] = $fid;
	return $fid;
};
$json = function ($route, $data, $headers = array()) {
	$r = wp_remote_post(rest_url('Emsfb/v1/' . $route), array('timeout' => 30, 'headers' => array_merge(array('Origin' => home_url(), 'Content-Type' => 'application/json'), $headers), 'body' => wp_json_encode($data)));
	if (is_wp_error($r)) throw new RuntimeException('HTTP failure: ' . $r->get_error_code());
	return array(wp_remote_retrieve_response_code($r), json_decode(wp_remote_retrieve_body($r), true));
};
$prepare = function ($fid) use ($json) {
	list($st, $c) = $json('forms/session/prepare', array('form_id' => $fid));
	if ($st !== 200 || empty($c['sid'])) throw new RuntimeException('prepare failed ' . $st);
	return $c;
};
$upload = function ($ctx, $fid, $field, $name, $bytes, $mime = 'application/octet-stream') use (&$paths) {
	$boundary = 'EFB' . str_replace('-', '', wp_generate_uuid4()); $body = '';
	foreach (array('id' => $field, 'pl' => 'msg', 'fid' => $fid, 'sid' => $ctx['sid'], 'page_id' => '0', 'nonce_msg' => $ctx['nonce']) as $k => $v) $body .= "--$boundary\r\nContent-Disposition: form-data; name=\"$k\"\r\n\r\n$v\r\n";
	$body .= "--$boundary\r\nContent-Disposition: form-data; name=\"async-upload\"; filename=\"$name\"\r\nContent-Type: $mime\r\n\r\n" . $bytes . "\r\n--$boundary--\r\n";
	$r = wp_remote_post(rest_url('Emsfb/v1/forms/file/upload'), array('timeout' => 30, 'headers' => array('Origin' => home_url(), 'Content-Type' => 'multipart/form-data; boundary=' . $boundary, 'X-WP-Nonce' => $ctx['nonce'], 'X-EFB-Form-Id' => (string) $fid, 'sid' => $ctx['sid']), 'body' => $body));
	if (is_wp_error($r)) throw new RuntimeException('upload HTTP failure');
	$d = json_decode(wp_remote_retrieve_body($r), true);
	$d = isset($d['data']) ? $d['data'] : $d;
	if (!empty($d['file']['url'])) { $p = \Emsfb\Upload_Guard::path_from_url($d['file']['url']); if ($p) $paths[] = $p; }
	return $d;
};
$submit = function ($ctx, $fid, $rows) use ($json) {
	$data = array('id' => (string) $fid, 'name' => 'F07', 'sid' => $ctx['sid'], 'page_id' => '0', 'url' => home_url('/'), 'type' => 'form', 'valid' => '', 'request_id' => wp_generate_uuid4(), 'runtime_token' => $ctx['runtime_token'], 'value' => wp_json_encode($rows));
	list($st, $d) = $json('forms/message/add', $data, array('X-WP-Nonce' => $ctx['nonce'], 'X-EFB-Form-Id' => (string) $fid, 'sid' => $ctx['sid']));
	return array($st, isset($d['data']) ? $d['data'] : $d);
};
$row = function ($field, $up, $extra = array()) {
	return array_merge(array('id_' => $field, 'name' => $field, 'type' => 'file', 'value' => '@file@', 'url' => $up['file']['url'], 'id_ob' => $up['name'], 'ut' => isset($up['ut']) ? $up['ut'] : '', 'fnm' => 'x.png', 'fsz' => '1'), $extra);
};
$png = function ($seed) { $im = imagecreatetruecolor(4, 4); imagefill($im, 0, 0, imagecolorallocate($im, $seed % 255, 30, 60)); ob_start(); imagepng($im); return ob_get_clean(); };
/* The server wrote these options in another process: read the database, not this process's cache. */
$fresh = function ($k, $default = null) use ($wpdb) { $v = $wpdb->get_var($wpdb->prepare("SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", $k)); return $v === null ? $default : maybe_unserialize($v); };
$ledger = function ($name) use ($fresh) { wp_cache_delete(\Emsfb\Upload_Guard::PENDING_OPTION, 'options'); wp_cache_delete('notoptions', 'options'); return \Emsfb\Upload_Guard::pending_entry($name); };
$entries = function ($fid) use ($wpdb) { return $wpdb->get_results($wpdb->prepare("SELECT msg_id, track, content FROM {$wpdb->prefix}emsfb_msg_ WHERE form_id = %d ORDER BY msg_id", $fid), ARRAY_A); };
$set_smtp = function ($on) use ($wpdb, $setting_row) {
	if (!$setting_row) return;
	$s = json_decode(str_replace('\\"', '"', $setting_row['setting']), true);
	if (!is_array($s)) $s = json_decode($setting_row['setting'], true);
	if (!is_array($s)) return;
	$s['smtp'] = $on ? '1' : (isset($s['smtp']) ? $s['smtp'] : '');
	$wpdb->update($wpdb->prefix . 'emsfb_setting', array('setting' => $on ? wp_json_encode($s, JSON_UNESCAPED_UNICODE) : $setting_row['setting']), array('id' => $setting_row['id']));
	delete_transient('emsfb_settings_transient');
};

try {
	$A = $make_form('F07 multi', array(
		'mf' => array('max_files' => '3', 'min_files' => '2', 'max_total_mb' => '1.2', 'required' => '0'),
		'one' => array(),
		'img' => array('file' => 'image', 'value' => 'image', 'max_fsize' => '1'),
	));
	$B = $make_form('F07 other', array('other' => array('max_files' => '3')));

	/* ------------------------------------------------ upload route */
	$c = $prepare($A);
	$u1 = $upload($c, $A, 'mf', 'one.png', $png(1), 'image/png');
	$check(!empty($u1['success']) && preg_match('/^efb-PLG-[0-9]{6}-[A-Za-z0-9]{12}\.png$/', $u1['name']), 'upload: a 12-character random name', $u1);
	$check(isset($u1['ut']) && preg_match('/^[0-9]{10}\.[a-f0-9]{32}$/', $u1['ut']) && (int) $u1['size'] === strlen($png(1)), 'upload: the answer has the token (ut) and the server\'s size', $u1);
	$le = $ledger($u1['name']);
	$check(is_array($le) && $le[1] === $A && $le[2] === 'mf', 'upload: the ledger binds the file to its form and field', $le);
	$check(strpos(wp_json_encode($u1), ABSPATH) === false && strpos(wp_json_encode($u1), wp_upload_dir()['basedir']) === false, 'upload: no server path in the answer');
	$sec = array(
		'polyglot PNG + PHP' => array('poly.png', $png(9) . "\n<?php echo 1; ?>\n", 'image/png'),
		'HTML renamed .png' => array('page.png', '<!doctype html><html><script>alert(1)</script></html>', 'image/png'),
		'double extension .php.png with PHP' => array('shell.php.png', "<?php system(\$_GET['c']); ?>", 'image/png'),
		'double extension .png.php' => array('shell.png.php', $png(2), 'image/png'),
		'SVG with a script' => array('logo.svg', '<svg xmlns="http://www.w3.org/2000/svg" onload="alert(1)"><script>alert(2)</script></svg>', 'image/svg+xml'),
		'over the field size (1 MB)' => array('big.pdf', '%PDF-1.4' . str_repeat('A', 1100 * 1024), 'application/pdf'),
	);
	foreach ($sec as $label => $s) {
		$r = $upload($c, $A, 'mf', $s[0], $s[1], $s[2]);
		$check(empty($r['success']) && !empty($r['error']), 'upload refused: ' . $label, $r);
	}
	$trav = $upload($c, $A, 'mf', '../../../wp-config.png', $png(3), 'image/png');
	$tp = !empty($trav['file']['url']) ? \Emsfb\Upload_Guard::path_from_url($trav['file']['url']) : '';
	$check(!empty($trav['success']) && preg_match('/^efb-PLG-/', $trav['name']) && strpos($tp, wp_normalize_path(wp_upload_dir()['basedir'])) === 0, 'upload: a path-traversal name is stored under a generated name in uploads', $trav);
	$u2 = $upload($c, $A, 'mf', 'two.png', $png(4), 'image/png');
	$u3 = $upload($c, $A, 'mf', 'three.png', $png(5), 'image/png');
	$u4 = $upload($c, $A, 'mf', 'four.png', $png(6), 'image/png');
	$uo = $upload($c, $A, 'one', 'single.png', $png(7), 'image/png');
	$ui = $upload($c, $A, 'img', 'pic.png', $png(8), 'image/png');

	/* ------------------------------------------------ submit: refusals */
	list($st, $d) = $submit($c, $A, array($row('mf', $u1), $row('mf', $u2), $row('mf', $u3), $row('mf', $u4)));
	$check(empty($d['success']) && ($d['code'] ?? '') === 'efb_file_max_files' && ($d['field_id'] ?? '') === 'mf' && strpos($d['m'], '3') !== false, 'submit: four files on a field of three → efb_file_max_files at the field', $d);
	list($st, $d) = $submit($c, $A, array($row('mf', $u1)));
	$check(empty($d['success']) && ($d['code'] ?? '') === 'efb_file_min_files' && ($d['field_id'] ?? '') === 'mf', 'submit: one file when the minimum is two → efb_file_min_files', $d);
	list($st, $d) = $submit($c, $A, array($row('mf', $u1), $row('mf', $trav), $row('mf', $u2)));
	$check(!empty($d['success']) || ($d['code'] ?? '') !== 'efb_file_max_total', 'submit: three small files are under the total', $d);
	// (the success above consumed the session; files of it are now in an entry)
	$stored_a = $entries($A);
	$check(count($stored_a) === 1, 'submit: one entry stored', count($stored_a));
	$rows_a = $stored_a ? \Emsfb\Privacy::decode_content($stored_a[0]['content']) : array();
	$mf_rows = array_values(array_filter((array) $rows_a, function ($r) { return is_array($r) && ($r['id_'] ?? '') === 'mf'; }));
	$check(count($mf_rows) === 3 && count(array_unique(array_column($mf_rows, 'id_ob'))) === 3, 'stored: three rows, each with its own id_ob (the stored name)', $mf_rows);
	$check(isset($mf_rows[0]['fsz']) && (int) $mf_rows[0]['fsz'] === strlen($png(1)) && !isset($mf_rows[0]['ut']), 'stored: fsz is the server\'s size (the browser said 1), no token stored', $mf_rows[0] ?? null);
	$check(isset($mf_rows[0]['fnm']) && $mf_rows[0]['fnm'] === 'x.png' && $mf_rows[0]['value'] === '@file@' && $mf_rows[0]['type'] === 'file', 'stored: the visitor\'s file name and the @file@ marker', $mf_rows[0] ?? null);
	$check($ledger($u1['name']) === null, 'stored: the files left the pending ledger (claimed)');

	$c = $prepare($A);
	list($st, $d) = $submit($c, $A, array($row('mf', $u1), $row('mf', $u3)));
	$check(empty($d['success']) && ($d['code'] ?? '') === 'efb_file_not_owned', 'replay: a file already stored in an entry → efb_file_not_owned', $d);
	$big1 = $upload($c, $A, 'mf', 'b1.pdf', '%PDF-1.4' . str_repeat('A', 700 * 1024), 'application/pdf');
	$big2 = $upload($c, $A, 'mf', 'b2.pdf', '%PDF-1.4' . str_repeat('B', 700 * 1024), 'application/pdf');
	list($st, $d) = $submit($c, $A, array($row('mf', $big1), $row('mf', $big2)));
	$check(empty($d['success']) && ($d['code'] ?? '') === 'efb_file_max_total' && strpos($d['m'], '1.2') !== false, 'submit: 1.4 MB on a total of 1.2 MB → efb_file_max_total', $d);
	$bad = $row('mf', $u3, array('ut' => substr($u3['ut'], 0, 11) . str_repeat('0', 32)));
	list($st, $d) = $submit($c, $A, array($bad, $row('mf', $u4)));
	$check(empty($d['success']) && ($d['code'] ?? '') === 'efb_file_not_owned', 'submit: a tampered token → efb_file_not_owned', $d);
	list($st, $d) = $submit($c, $A, array($row('mf', $u4), $row('mf', $u4, array('id_ob' => 'x'))));
	$check(empty($d['success']), 'submit: the same file twice is refused', $d);
	$cB = $prepare($B);
	$ub = $upload($cB, $B, 'other', 'b.png', $png(11), 'image/png');
	list($st, $d) = $submit($c, $A, array($row('mf', $u4), $row('mf', $ub)));
	$check(empty($d['success']) && ($d['code'] ?? '') === 'efb_file_not_owned', 'submit: another form\'s file (with its own token) → refused', $d);
	list($st, $d) = $submit($c, $A, array($row('mf', $u4), $row('mf', $ub, array('ut' => ''))));
	$check(empty($d['success']) && ($d['code'] ?? '') === 'efb_file_not_owned', 'submit: another form\'s file without a token → refused (ledger binding)', $d);
	list($st, $d) = $submit($c, $A, array($row('mf', $u4), $row('mf', $uo)));
	$check(empty($d['success']) && ($d['code'] ?? '') === 'efb_file_not_owned', 'submit: another field\'s file → refused', $d);
	$media = wp_upload_bits('efb-PLG-261113-SITEMEDIA0001.png', null, $png(12));
	if (!empty($media['file'])) $paths[] = $media['file'];
	list($st, $d) = $submit($c, $A, array($row('mf', $u4), array('id_' => 'mf', 'name' => 'mf', 'type' => 'file', 'value' => '@file@', 'url' => $media['url'])));
	$check(empty($d['success']) && ($d['code'] ?? '') === 'efb_file_not_owned', 'submit: a site media file (never uploaded through the form) → refused', $d);
	$c2 = $prepare($A);
	$u5 = $upload($c2, $A, 'mf', 'five.png', $png(13), 'image/png');
	list($st, $d) = $submit($c, $A, array($row('mf', $u4), $row('mf', $u5)));
	$check(empty($d['success']) && ($d['code'] ?? '') === 'efb_file_not_owned', 'submit: a token of another session → refused', $d);

	/* ------------------------------------------------ cached page (no token) and the token switch */
	list($st, $d) = $submit($c, $A, array($row('mf', $u4, array('ut' => '')), $row('mf', $big1, array('ut' => '')), array('id_' => 'one', 'name' => 'one', 'type' => 'file', 'value' => '@file@', 'url' => $uo['file']['url'])));
	$check(!empty($d['success']), 'a page cached before F07 (no token, old row shape): accepted for files of this form field', $d);
	$c = $prepare($A);
	update_option('efb_e2e_f07_require', 1, false);
	$u6 = $upload($c, $A, 'mf', 'six.png', $png(14), 'image/png');
	$u7 = $upload($c, $A, 'mf', 'seven.png', $png(15), 'image/png');
	list($st, $d) = $submit($c, $A, array($row('mf', $u6, array('ut' => '')), $row('mf', $u7)));
	$check(empty($d['success']) && ($d['code'] ?? '') === 'efb_file_not_owned', 'emsfb_upload_require_token on: no token → refused', $d);
	delete_option('efb_e2e_f07_require');

	/* ------------------------------------------------ quota */
	update_option('efb_e2e_f07_quota', 2, false);
	\Emsfb\Rate_Limit::reset_scopes(array('upq', 'upip'));
	$cq = $prepare($A);
	$q = array();
	for ($i = 0; $i < 3; $i++) $q[] = !empty($upload($cq, $A, 'mf', "q$i.png", $png(20 + $i), 'image/png')['success']);
	$check($q === array(true, true, false), 'quota (2 through emsfb_upload_quota_limit): the third upload is refused', $q);
	$r = $upload($cq, $A, 'mf', 'bad.svg', '<svg/>', 'image/svg+xml');
	$check(empty($r['success']) && strpos((string) $r['error'], 'wait') !== false, 'quota: a refused file is told the limit, not the type', $r);
	delete_option('efb_e2e_f07_quota');
	\Emsfb\Rate_Limit::reset_scopes(array('upq', 'upip'));

	/* ------------------------------------------------ private store + signed links + e-mail */
	$C = $make_form('F07 private', array('pv' => array('max_files' => '2', 'file_store' => 'private')), array('email' => 'owner@example.test'));
	$set_smtp(true);
	update_option('efb_e2e_f07_mail', 1, false); update_option('efb_e2e_f07_mails', array(), false);
	$c = $prepare($C);
	$p1 = $upload($c, $C, 'pv', 'secret.pdf', '%PDF-1.4 secret', 'application/pdf');
	$pp = \Emsfb\Upload_Guard::path_from_url($p1['file']['url']);
	$check(!empty($p1['success']) && strpos($p1['file']['url'], '/efb-private/') !== false && preg_match('#/efb-private/[0-9]{4}/[0-9]{2}/efb-PLG-#', wp_normalize_path($pp)), 'private: stored in uploads/efb-private/YYYY/MM', $p1);
	$pdir = \Emsfb\Uploads::private_dir();
	$check(is_file($pdir . '.htaccess') && strpos(file_get_contents($pdir . '.htaccess'), 'Require all denied') !== false && is_file($pdir . 'index.php'), 'private: deny-all .htaccess and index.php in the folder');
	list($st, $d) = $submit($c, $C, array($row('pv', $p1)));
	$check(!empty($d['success']), 'private: the entry is stored', $d);
	$rel = \Emsfb\Uploads::private_rel($p1['file']['url']);
	$dl = wp_remote_get(add_query_arg(array('action' => \Emsfb\Uploads::DOWNLOAD_ACTION, 'f' => rawurlencode($rel), 'nonce' => 'x'), admin_url('admin-ajax.php')), array('timeout' => 20));
	$check(!is_wp_error($dl) && in_array(wp_remote_retrieve_response_code($dl), array(400, 403), true) && strpos(wp_remote_retrieve_body($dl), 'secret') === false, 'private: the admin download without a login is refused', is_wp_error($dl) ? 'err' : wp_remote_retrieve_response_code($dl));
	$signed = \Emsfb\Uploads::signed_url($p1['file']['url']);
	$ok = wp_remote_get($signed, array('timeout' => 20));
	$check(wp_remote_retrieve_response_code($ok) === 200 && wp_remote_retrieve_body($ok) === '%PDF-1.4 secret', 'private: a signed link downloads the file', wp_remote_retrieve_response_code($ok));
	$check(wp_remote_retrieve_header($ok, 'x-content-type-options') === 'nosniff' && strpos((string) wp_remote_retrieve_header($ok, 'content-disposition'), 'attachment') === 0 && strpos((string) wp_remote_retrieve_header($ok, 'cache-control'), 'no-store') !== false, 'private: nosniff, attachment, no-store', wp_remote_retrieve_headers($ok));
	$bad_sig = wp_remote_get(preg_replace('/sig=[a-f0-9]{3}/', 'sig=000', $signed), array('timeout' => 20));
	$check(wp_remote_retrieve_response_code($bad_sig) === 403, 'private: a tampered signature is refused', wp_remote_retrieve_response_code($bad_sig));
	$expired = add_query_arg(array('f' => rawurlencode($rel), 'exp' => time() - 10, 'sig' => str_repeat('a', 40)), rest_url('Emsfb/v1/files/signed'));
	$check(wp_remote_retrieve_response_code(wp_remote_get($expired, array('timeout' => 20))) === 403, 'private: an expired link is refused');
	$trav_link = add_query_arg(array('f' => rawurlencode('2026/11/../../../wp-config.php'), 'exp' => time() + 100, 'sig' => str_repeat('a', 40)), rest_url('Emsfb/v1/files/signed'));
	$check(wp_remote_retrieve_response_code(wp_remote_get($trav_link, array('timeout' => 20))) === 403, 'private: a ../ path in a link is refused');
	sleep(2); // the e-mails go out after the answer
	$mails = $fresh('efb_e2e_f07_mails', array());
	$owner = array_values(array_filter((array) $mails, function ($m) { return strpos(wp_json_encode($m['to']), 'owner@example.test') !== false; }));
	$check(!empty($owner) && strpos($owner[0]['message'], 'files/signed') !== false && strpos($owner[0]['message'], '/efb-private/') === false, 'private: the owner\'s e-mail links to a signed address, never the private one', array_map(function ($m) { return array($m['to'], $m['subject']); }, (array) $mails));

	/* ------------------------------------------------ Media Library + e-mail attachments (Pro) */
	$D = $make_form('F07 media', array('md' => array('max_files' => '2', 'file_store' => 'media')), array('email' => 'owner@example.test', 'email_attach' => '1'));
	update_option('efb_e2e_f07_mails', array(), false);
	$c = $prepare($D);
	$m1 = $upload($c, $D, 'md', 'one.png', $png(31), 'image/png');
	$m2 = $upload($c, $D, 'md', 'two.pdf', '%PDF-1.4 two', 'application/pdf');
	list($st, $d) = $submit($c, $D, array($row('md', $m1), $row('md', $m2)));
	$check(!empty($d['success']), 'media: the entry is stored', $d);
	$att = \Emsfb\Uploads::attachment_for_path(\Emsfb\Upload_Guard::path_from_url($m1['file']['url']));
	$post = $att ? get_post($att) : null;
	$track_d = $entries($D)[0]['track'] ?? '';
	$check($post && $post->post_status === 'private' && (int) $post->post_author === 0 && (int) get_post_meta($att, '_efb_form_id', true) === $D && get_post_meta($att, '_efb_track', true) === $track_d, 'media: a private attachment (author 0) with the form and the tracking code', $post ? array($post->post_status, $post->post_author) : null);
	$list = wp_remote_get(rest_url('wp/v2/media?per_page=100&search=one'), array('timeout' => 20));
	$ids = array_column((array) json_decode(wp_remote_retrieve_body($list), true), 'id');
	$check(!in_array($att, $ids, true), 'media: /wp/v2/media does not list it to a visitor', $ids);
	sleep(2);
	$mails = $fresh('efb_e2e_f07_mails', array());
	$with = array_values(array_filter((array) $mails, function ($m) { return !empty($m['attachments']); }));
	$check(count($with) >= 1 && strpos(wp_json_encode($with[0]['to']), 'owner@example.test') !== false && count($with[0]['attachments']) === 2, 'e-mail: the owner\'s copy carries the two files (email_attach, Pro)', array_map(function ($m) { return array($m['to'], count($m['attachments'])); }, (array) $mails));
	$check(count($with) === count(array_filter((array) $mails, function ($m) { return strpos(wp_json_encode($m['to']), 'owner@example.test') !== false; })), 'e-mail: no other message carries files');

	/* ------------------------------------------------ deletion with the entry */
	$ids_del = array_map('intval', array_merge(array_column($entries($C), 'msg_id'), array_column($entries($D), 'msg_id')));
	$mp = \Emsfb\Upload_Guard::path_from_url($m1['file']['url']);
	$r = \Emsfb\Privacy::delete_entries($ids_del);
	$check(!is_file($pp) && !is_file($mp) && !get_post($att), 'deletion: the private file, the media file and its attachment are gone', $r);
	$check($r['deleted'] === 2 && $r['files'] >= 3, 'deletion: two entries, their files counted', $r);
} catch (Throwable $e) {
	$failed++; echo '[FAIL] exception: ' . $e->getMessage() . "\n";
} finally {
	foreach ($forms as $fid) {
		$ids = array_map('intval', (array) $wpdb->get_col($wpdb->prepare("SELECT msg_id FROM {$wpdb->prefix}emsfb_msg_ WHERE form_id = %d", $fid)));
		if ($ids) \Emsfb\Privacy::purge_entries($ids, 'delete', true, false);
		foreach (array('emsfb_stts_' => 'fid', 'emsfb_form' => 'form_id') as $t => $col) $wpdb->delete($wpdb->prefix . $t, array($col => $fid));
	}
	foreach ($paths as $p) if (is_file($p)) { $a = \Emsfb\Uploads::attachment_for_path($p); if ($a) wp_delete_attachment($a, true); @unlink($p); }
	foreach ($saved_opts as $k => $v) { if ($v === null) delete_option($k); else update_option($k, $v, false); }
	if ($setting_row) { $wpdb->update($wpdb->prefix . 'emsfb_setting', array('setting' => $setting_row['setting']), array('id' => $setting_row['id'])); delete_transient('emsfb_settings_transient'); }
	if (class_exists('\Emsfb\Rate_Limit')) \Emsfb\Rate_Limit::reset_scopes(array('upq', 'upip', 'fdl'));
	if ($health === null) delete_option(\Emsfb\Request_Health::OPTION); else update_option(\Emsfb\Request_Health::OPTION, $health, false);
}
echo "\nRESULTS: $passed passed, $failed failed\n";
exit($failed ? 1 : 0);
