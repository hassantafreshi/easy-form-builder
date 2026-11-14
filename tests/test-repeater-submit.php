<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
/**
 * F09-G/H on a real WordPress (the real save rules and REST route forms/message/add):
 *
 *  - a two-row answer is stored with each child's own id_, rg (written by the server) and ri, the group's own
 *    row with the number of rows; values checked per row with the field's rules (a select's saved option text,
 *    a checkbox's options per row, an e-mail per row); the stored content has no virtual id;
 *  - refused: fewer / more rows than allowed, a count that does not match rows_from, a malformed count, a
 *    required child empty in row 2 (field_id = the child, row_index = 1, "Name in row 2 is required."), a
 *    bad e-mail in row 1 (row_index = 0), more than 1500 rows (efb_invalid_payload);
 *  - dropped: a forged rg (the server's group is written), ri out of range, a row of a child without ri, a row
 *    of a field outside the group carrying ri (first kept, no rg / ri), a virtual id sent as id_, an unknown id;
 *  - two rows of one entry are two answers (dedupe keeps them; an exact duplicate is one);
 *  - a group of a plan without the group: its rows dropped, its required children not required;
 *  - an old form's answers are stored exactly as before (no rg / ri, same rows);
 *  - the worst case (30 fields × 50 rows) is checked in under a second.
 *
 * Needs the e2e site: EFB_WP_LOAD=/path/wp-load.php, or EFB_E2E_SITE_DIR (tests/e2e-wp/README.md);
 * [SKIP] otherwise. Run: php tests/test-repeater-submit.php
 */
$wp_load = getenv('EFB_WP_LOAD') ?: (getenv('EFB_E2E_SITE_DIR') ? rtrim(getenv('EFB_E2E_SITE_DIR'), '/') . '/wp-load.php' : '');
if ($wp_load === '' || !is_readable($wp_load)) { echo "[SKIP] WordPress bootstrap was not found (EFB_WP_LOAD / EFB_E2E_SITE_DIR)\nRESULTS: 0 passed, 0 failed\n"; exit(0); }
require $wp_load;
global $wpdb;

$passed = 0; $failed = 0;
$check = function ($ok, $label, $detail = null) use (&$passed, &$failed) {
	if ($ok) { $passed++; echo "[PASS] $label\n"; return; }
	$failed++; echo "[FAIL] $label" . ($detail !== null ? ' ' . substr(wp_json_encode($detail, JSON_UNESCAPED_UNICODE), 0, 1200) : '') . "\n";
};
$forms = array();
$efb = get_efbFunction();
$plan_was = get_option('emsfb_pro');
update_option('emsfb_pro', 3);
$open = function ($n) { return 'free_plus'; };
add_filter('efb_repeater_plan', $open);

$make_form = function ($name, $fields, $row0 = array()) use ($wpdb, &$forms, $efb) {
	$rows = array(
		array_merge(array('type' => 'form', 'steps' => 1, 'formName' => $name, 'email' => '', 'captcha' => false, 'sendEmail' => '0', 'smsnoti' => '0', 'stateForm' => '0', 'trackingCode' => true, 'thank_you' => 'msg', 'thank_you_message' => array('thankYou' => 'ok', 'done' => 'ok')), $row0),
		array('id_' => '1', 'type' => 'step', 'dataId' => '1', 'id' => '1', 'name' => 'Step', 'step' => '1', 'amount' => 1, 'visible' => 1),
	);
	$a = 2;
	foreach ($fields as $f) $rows[] = array_merge(array('dataId' => $f['id_'] . '-id', 'step' => '1', 'amount' => $a++, 'required' => '0', 'visible' => 1, 'name' => $f['id_']), $f);
	$rows = $efb->sanitize_obj_msg_efb($rows);
	$wpdb->insert($wpdb->prefix . 'emsfb_form', array('form_name' => $name, 'form_type' => 'form', 'form_structer' => str_replace('"', '\\"', wp_json_encode($rows, JSON_UNESCAPED_UNICODE)), 'form_email' => '', 'form_created_by' => 1, 'status' => 1));
	$fid = (int) $wpdb->insert_id;
	$forms[] = $fid;
	wp_cache_flush();
	return array($fid, $rows);
};
$json = function ($route, $data, $headers = array()) {
	$r = wp_remote_post(rest_url('Emsfb/v1/' . $route), array('timeout' => 30, 'headers' => array_merge(array('Origin' => home_url(), 'Content-Type' => 'application/json'), $headers), 'body' => wp_json_encode($data)));
	if (is_wp_error($r)) throw new RuntimeException('HTTP failure: ' . $r->get_error_code());
	return array(wp_remote_retrieve_response_code($r), json_decode(wp_remote_retrieve_body($r), true));
};
$submit = function ($fid, $rows) use ($json) {
	list($st, $c) = $json('forms/session/prepare', array('form_id' => $fid));
	if ($st !== 200 || empty($c['sid'])) throw new RuntimeException('prepare failed ' . $st);
	$data = array('id' => (string) $fid, 'name' => 'F09', 'sid' => $c['sid'], 'page_id' => '0', 'url' => home_url('/f09-page/'), 'type' => 'form', 'valid' => '', 'request_id' => wp_generate_uuid4(), 'runtime_token' => $c['runtime_token'], 'value' => wp_json_encode($rows));
	list($st, $d) = $json('forms/message/add', $data, array('X-WP-Nonce' => $c['nonce'], 'X-EFB-Form-Id' => (string) $fid, 'sid' => $c['sid']));
	return array($st, isset($d['data']) ? $d['data'] : $d);
};
$last = function ($fid) use ($wpdb) {
	$e = $wpdb->get_row($wpdb->prepare("SELECT msg_id, content FROM {$wpdb->prefix}emsfb_msg_ WHERE form_id = %d ORDER BY msg_id DESC LIMIT 1", $fid), ARRAY_A);
	if (!$e) return array();
	$r = class_exists('\\Emsfb\\Privacy') ? \Emsfb\Privacy::decode_content($e['content']) : json_decode(str_replace('\\"', '"', $e['content']), true);
	return is_array($r) ? $r : array();
};
$n_entries = function ($fid) use ($wpdb) { return (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}emsfb_msg_ WHERE form_id = %d", $fid)); };
$row = function ($id, $type, $value, $ri = null, $extra = array()) {
	$r = array_merge(array('id_' => $id, 'name' => $id, 'id_ob' => $id . '_', 'type' => $type, 'value' => $value, 'amount' => 3, 'session' => 's', 'form_id' => 0), $extra);
	if ($ri !== null) { $r['ri'] = (string) $ri; $r['rg'] = 'gx'; }
	return $r;
};
$grp = function ($n) { return array('id_' => 'g1', 'name' => 'Travellers', 'id_ob' => 'g1-f', 'type' => 'repeater', 'value' => (string) $n, 'amount' => 3, 'session' => 's'); };

try {
	list($F) = $make_form('F09 submit', array(
		array('id_' => 'cnt', 'type' => 'number', 'name' => 'Count'),
		array('id_' => 'g1', 'type' => 'repeater', 'name' => 'Travellers', 'min_rows' => '1', 'max_rows' => '3', 'default_rows' => '1'),
		array('id_' => 'nm', 'type' => 'text', 'name' => 'Name', 'group' => 'g1', 'required' => '1'),
		array('id_' => 'em', 'type' => 'email', 'name' => 'Email', 'group' => 'g1'),
		array('id_' => 'ml', 'type' => 'select', 'name' => 'Meal', 'group' => 'g1'),
		array('id_' => 'ml1', 'type' => 'option', 'parent' => 'ml', 'value' => 'Vegan', 'id_op' => 'ml1'),
		array('id_' => 'ml2', 'type' => 'option', 'parent' => 'ml', 'value' => 'Halal', 'id_op' => 'ml2'),
		array('id_' => 'ex', 'type' => 'checkbox', 'name' => 'Extras', 'group' => 'g1'),
		array('id_' => 'ex1', 'type' => 'option', 'parent' => 'ex', 'value' => 'Bag', 'id_op' => 'ex1'),
		array('id_' => 'ex2', 'type' => 'option', 'parent' => 'ex', 'value' => 'Seat', 'id_op' => 'ex2'),
		array('id_' => 'after', 'type' => 'text', 'name' => 'After'),
	));
	$good = array(
		$grp(2),
		$row('nm', 'text', 'Sara', 0), $row('em', 'email', 'sara@example.test', 0), $row('ml', 'select', 'Halal', 0, array('id_ob' => 'ml2')), $row('ex', 'checkbox', 'Bag', 0, array('id_ob' => 'ex1')),
		$row('nm', 'text', 'Reza', 1), $row('ml', 'select', 'Vegan', 1, array('id_ob' => 'ml1')), $row('ex', 'checkbox', 'Bag', 1, array('id_ob' => 'ex1')), $row('ex', 'checkbox', 'Seat', 1, array('id_ob' => 'ex2')),
		$row('after', 'text', 'Done'),
	);
	list($st, $d) = $submit($F, $good);
	$check($st === 200 && !empty($d['success']), 'G: a two-row answer is accepted', $d);
	$c = $last($F);
	$pick = function ($c, $id) { return array_values(array_filter($c, function ($r) use ($id) { return is_array($r) && ($r['id_'] ?? '') === $id; })); };
	$nm = $pick($c, 'nm');
	$check(count($nm) === 2 && $nm[0]['value'] === 'Sara' && $nm[0]['ri'] === '0' && $nm[1]['value'] === 'Reza' && $nm[1]['ri'] === '1' && $nm[0]['rg'] === 'g1' && $nm[1]['rg'] === 'g1', 'G: Name stored twice under its own id with rg g1 (the forged "gx" replaced) and ri 0 / 1', $nm);
	$g = $pick($c, 'g1');
	$check(count($g) === 1 && $g[0]['value'] === '2' && $g[0]['type'] === 'repeater', 'G: the group\'s own row says 2', $g);
	$ex = $pick($c, 'ex');
	$check(count($ex) === 3 && $ex[0]['id_ob'] === 'ex1' && $ex[0]['ri'] === '0' && $ex[2]['id_ob'] === 'ex2' && $ex[2]['ri'] === '1', 'G: the checkbox choices per row, under the options\' own ids (ex1 in row 0; ex1 and ex2 in row 1)', $ex);
	$ml = $pick($c, 'ml');
	$check(count($ml) === 2 && $ml[0]['value'] === 'Halal' && $ml[1]['value'] === 'Vegan' && $ml[0]['id_ob'] === 'ml2', 'G: a select per row: the saved option text', $ml);
	$flat = wp_json_encode($c);
	$check(!preg_match('/_r\d/', $flat), 'G: no virtual id ({child}_r{n}) in the stored content', $flat);
	$af = $pick($c, 'after');
	$check(count($af) === 1 && !isset($af[0]['ri']) && !isset($af[0]['rg']), 'G: a field outside the group: no rg / ri', $af);

	// H: counts.
	$try = function ($rows) use ($submit, $F) { list($st, $d) = $submit($F, $rows); return $d; };
	$d = $try(array($grp(0), $row('after', 'text', 'x')));
	$check(empty($d['success']) && ($d['field_id'] ?? '') === 'g1' && ($d['code'] ?? '') === 'efb_rpt_min' && strpos($d['m'] ?? '', 'needs at least') !== false, 'H: 0 rows (minimum 1) refused on the group (efb_rpt_min)', $d);
	$d = $try(array($grp(4), $row('nm', 'text', 'a', 0), $row('nm', 'text', 'b', 1), $row('nm', 'text', 'c', 2), $row('nm', 'text', 'd', 3)));
	$check(empty($d['success']) && ($d['code'] ?? '') === 'efb_rpt_max', 'H: 4 rows (maximum 3) refused (efb_rpt_max)', $d);
	$d = $try(array($grp('1e9'), $row('nm', 'text', 'a', 0)));
	$check(empty($d['success']) && ($d['field_id'] ?? '') === 'g1', 'H: a count that is not a whole number refused', $d);
	$d = $try(array($grp(2), $row('nm', 'text', 'Sara', 0), $row('em', 'email', 'x@example.test', 1)));
	$check(empty($d['success']) && ($d['field_id'] ?? '') === 'nm' && isset($d['row_index']) && (int) $d['row_index'] === 1 && ($d['code'] ?? '') === 'efb_rpt_required' && strpos(wp_strip_all_tags($d['m'] ?? ''), 'Name in row 2 is required.') !== false, 'H: the required Name empty in row 2: field_id nm, row_index 1, "Name in row 2 is required."', $d);
	$d = $try(array($grp(2), $row('nm', 'text', 'Sara', 0), $row('em', 'email', 'not-an-email', 0), $row('nm', 'text', 'Reza', 1)));
	$check(empty($d['success']) && ($d['field_id'] ?? '') === 'em' && isset($d['row_index']) && (int) $d['row_index'] === 0, 'H: a bad e-mail in row 1: field_id em, row_index 0', $d);
	$before = $n_entries($F);
	$d = $try(array($grp(1), $row('nm', 'text', 'One', 0), $row('nm', 'text', 'Ghost', 5), $row('em', 'email', 'no-ri@example.test'), $row('nm_r0', 'text', 'Virtual'), $row('zz', 'text', 'Unknown'), $row('after', 'text', 'A', 0), $row('after', 'text', 'B', 1)));
	$c = $last($F);
	$check(!empty($d['success']) && $n_entries($F) === $before + 1, 'H: a submission with forged rows is stored without them', $d);
	$vals = array_map(function ($r) { return is_array($r) ? ($r['value'] ?? '') : ''; }, $c);
	$check(!in_array('Ghost', $vals, true) && !in_array('no-ri@example.test', $vals, true) && !in_array('Virtual', $vals, true) && !in_array('Unknown', $vals, true), 'H: dropped: ri out of range, a child row without ri, a virtual id, an unknown id', $vals);
	$af = $pick($c, 'after');
	$check(count($af) === 1 && $af[0]['value'] === 'A' && !isset($af[0]['ri']), 'H: a field outside the group sent with ri: first row kept, without rg / ri', $af);
	// Dedupe: an exact duplicate of a row is one row; two rows differ by ri.
	$d = $try(array($grp(2), $row('nm', 'text', 'Same', 0), $row('nm', 'text', 'Same', 0), $row('nm', 'text', 'Same', 1)));
	$c = $last($F);
	$check(!empty($d['success']) && count($pick($c, 'nm')) === 2, 'G: dedupe keeps the two rows (ri differs) and drops the exact duplicate', $pick($c, 'nm'));
	// Payload cap.
	$big = array($grp(1), $row('nm', 'text', 'x', 0));
	for ($i = 0; $i < 1500; $i++) $big[] = $row('after', 'text', 'v' . $i);
	list($st, $d) = $submit($F, $big);
	$check($st === 400 && (($d['code'] ?? '') === 'efb_invalid_payload'), 'H: more than 1500 rows: efb_invalid_payload (400)', array($st, $d));

	// rows_from.
	list($F2) = $make_form('F09 submit from', array(
		array('id_' => 'cnt', 'type' => 'number', 'name' => 'Count'),
		array('id_' => 'g1', 'type' => 'repeater', 'name' => 'Travellers', 'min_rows' => '1', 'max_rows' => '4', 'default_rows' => '1', 'rows_from' => 'cnt'),
		array('id_' => 'nm', 'type' => 'text', 'name' => 'Name', 'group' => 'g1'),
	));
	list($st, $d) = $submit($F2, array($row('cnt', 'number', '3'), $grp(2), $row('nm', 'text', 'a', 0), $row('nm', 'text', 'b', 1)));
	$check(empty($d['success']) && ($d['code'] ?? '') === 'efb_rpt_count', 'H: rows_from 3 but 2 rows: efb_rpt_count', $d);
	list($st, $d) = $submit($F2, array($row('cnt', 'number', '9'), $grp(4), $row('nm', 'text', 'a', 0)));
	$check(!empty($d['success']), 'H: rows_from 9 with a maximum of 4: 4 rows is right (clamped)', $d);

	// Plan without the group: rows dropped, required children not required.
	remove_filter('efb_repeater_plan', $open);
	$locked = function ($n, $part) { return 'pro'; };
	add_filter('efb_repeater_plan', $locked);
	update_option('emsfb_pro', 2);
	list($st, $d) = $submit($F, array($grp(2), $row('nm', 'text', 'x', 0), $row('after', 'text', 'Only')));
	$c = $last($F);
	$check(!empty($d['success']) && count($pick($c, 'nm')) === 0 && count($pick($c, 'g1')) === 0, 'H: a plan without groups: the group\'s rows are dropped and its required child is not asked for', array($d, $c));
	remove_filter('efb_repeater_plan', $locked);
	add_filter('efb_repeater_plan', $open);
	update_option('emsfb_pro', 3);

	// An old form: rows stored exactly as sent and checked as before.
	list($F3) = $make_form('F09 old', array(
		array('id_' => 'o1', 'type' => 'text', 'name' => 'A'),
		array('id_' => 'o2', 'type' => 'checkbox', 'name' => 'B'),
		array('id_' => 'o21', 'type' => 'option', 'parent' => 'o2', 'value' => 'X', 'id_op' => 'o21'),
	));
	$old = array($row('o1', 'text', 'Hello'), $row('o2', 'checkbox', 'X', null, array('id_ob' => 'o21')));
	list($st, $d) = $submit($F3, $old);
	$c = $last($F3);
	$keys = array_map(function ($r) { return is_array($r) ? implode(',', array_keys($r)) : ''; }, $c);
	$check(!empty($d['success']) && $c[0]['value'] === 'Hello' && $c[1]['id_ob'] === 'o21' && strpos(implode('|', $keys), 'ri') === false, 'an old form: answers stored as before (no rg / ri)', $c);

	// Worst case: 30 fields × 50 rows through Repeater::expand.
	$fields = array(array('type' => 'form'), array('id_' => '1', 'type' => 'step'), array('id_' => 'G', 'type' => 'repeater', 'name' => 'G', 'min_rows' => '0', 'max_rows' => '50'));
	$rows = array(array('id_' => 'G', 'type' => 'repeater', 'value' => '50'));
	for ($k = 0; $k < 25; $k++) $fields[] = array('id_' => 'c' . $k, 'type' => 'text', 'group' => 'G', 'name' => 'c' . $k);
	for ($ri = 0; $ri < 50; $ri++) for ($k = 0; $k < 25; $k++) $rows[] = array('id_' => 'c' . $k, 'type' => 'text', 'value' => 'v', 'ri' => (string) $ri);
	$t0 = microtime(true);
	$x = \Emsfb\Repeater::expand($fields, $rows, array());
	$folded = \Emsfb\Repeater::fold($x['rows'], $x['virtual']);
	$ms = (microtime(true) - $t0) * 1000;
	$check($x['ok'] && count($x['rows']) === 1251 && $ms < 200, sprintf('H: 25 children × 50 rows expanded and folded in %.1f ms (< 200)', $ms));
} finally {
	foreach ($forms as $fid) {
		$wpdb->delete($wpdb->prefix . 'emsfb_form', array('form_id' => $fid));
		$wpdb->delete($wpdb->prefix . 'emsfb_msg_', array('form_id' => $fid));
	}
	update_option('emsfb_pro', $plan_was);
}
echo "\nRESULTS: {$passed} passed, {$failed} failed\n";
exit($failed ? 1 : 0);
