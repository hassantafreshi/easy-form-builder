<?php
/**
 * F10-D on a real WordPress (the e2e site; tests/f11-http-lib.php), over HTTP as a browser: who sees a field is
 * decided on the server (spec §16 D acceptance 1-6).
 *
 *  1  the save: vis_roles "editor,<x>,nonexistent" is stored "editor";
 *  2  a guest's page: the editor-only field and the signed-in-only choice (with its options) are not in the HTML nor
 *     in the copy of the form the page carries (ajax_value_forms); a step whose fields are all hidden is marked
 *     data-vis-empty; the page is kept out of page caches (Cache-Control no-store, cf-edge-cache: no-cache); the
 *     redirect address (rePage) is not in the page (spec §19 bug 1);
 *  3  a guest's forged answers for those fields are dropped, and the required editor-only field does not refuse
 *     the submit; forms/{id}/schema has no such field and is private, no-store;
 *  4  a subscriber sees the signed-in choice, not the editor field; an editor sees the field and it is required
 *     there (refused without it, with its field_id; stored with it);
 *  5  a page the site keeps cacheable (emsfb_cache_bypass false for "vis"): the view every visitor shares, also for
 *     the editor (vis_view "public"); its submit (vis_view in the body, or the X-EFB-Vis-View header of
 *     payments/quote and payments/create) does not ask for the editor field and drops it;
 *  6  entries.read (efb_api_entry_get) leaves out the editor-only answer for a caller without the role.
 *
 * Run (under the e2e lock): source the e2e env; php tests/test-field-visibility-submit.php
 */
$passed = 0;
$failed = 0;
function t($name, $actual, $expected) {
	global $passed, $failed;
	if ($actual === $expected) { echo "[PASS] {$name}\n"; $passed++; return; }
	echo "[FAIL] {$name}: expected " . var_export($expected, true) . ', got ' . var_export($actual, true) . "\n";
	$failed++;
}
require __DIR__ . '/f11-http-lib.php';
$H = new EfbF11Http();
if ($why = $H->unavailable()) { echo "[SKIP] {$why}\nRESULTS: 0 passed, 0 failed\n"; exit(0); }

$H->ctl('plan', '3');
$H->ctl('f10-mu');
$H->ctl('set-option', 'efb_e2e_f10_nobypass', '0');
$ed = $H->ctl('user', 'efb_vis_editor', 'editor');
$sub = $H->ctl('user', 'efb_vis_sub', 'subscriber');

/* 1 */
$F = $H->ctl('f03-form', 'F10 visibility', json_encode(array(
	array('key' => 'pub', 'type' => 'text', 'name' => 'Public name', 'required' => 1),
	array('key' => 'ed', 'type' => 'text', 'name' => 'Editor code', 'required' => 1, 'vis_mode' => 'roles', 'vis_roles' => 'editor,<x>,nonexistent'),
	array('key' => 'ch', 'type' => 'radio', 'name' => 'Member plan', 'vis_mode' => 'in'),
	array('key' => 'o1', 'type' => 'option', 'parent_key' => 'ch', 'value' => 'Secret Gold', 'name' => 'Secret Gold'),
	array('key' => 'o2', 'type' => 'option', 'parent_key' => 'ch', 'value' => 'Secret Silver', 'name' => 'Secret Silver'),
	array('key' => 'in2', 'type' => 'text', 'name' => 'Members step', 'step' => 2, 'vis_mode' => 'in'),
)), 'thank_you=rdrct&rePage=https:@efb@@efb@redirect-target.test@efb@thanks', 2);
$fid = $F['form_id'];
$id = $F['ids'];
$by = array();
foreach ($F['rows'] as $r) if (isset($r['id_'])) $by[$r['id_']] = $r;
t('1 vis_roles "editor,<x>,nonexistent" is saved "editor"', array($by[$id['ed']]['vis_mode'] ?? null, $by[$id['ed']]['vis_roles'] ?? null), array('roles', 'editor'));
$path = $H->page($fid);

/* 2 */
$H->fresh_jar();
$g = $H->http('GET', $path, null, array(), false);
$body = $g['body'];
t('2 guest page: the form is there (the public field)', strpos($body, $id['pub']) !== false, true);
t('2 guest page: the editor-only field is nowhere (HTML, ajax_value_forms)', array(strpos($body, $id['ed']), strpos($body, 'Editor code')), array(false, false));
t('2 guest page: the signed-in choice and its options are nowhere', array(strpos($body, $id['ch']), strpos($body, 'Secret Gold'), strpos($body, $id['o1'])), array(false, false, false));
t('2 guest page: the copy of the form is in the page (ajax_value_forms) and holds the public field', (bool) preg_match('/ajax_value_forms.{0,4000}' . preg_quote($id['pub'], '/') . '/s', $body), true);
t('2 guest page: step 2 (every field hidden) is marked data-vis-empty', (bool) preg_match('/id="step-2-efb"[^>]*data-vis-empty="1"/', $body), true);
t('2 the page is kept out of page caches (no-store, cf-edge-cache)', array((bool) preg_match('/^cache-control:[^\r\n]*no-store/im', $g['headers']), (bool) preg_match('/^cf-edge-cache:\s*no-cache/im', $g['headers'])), array(true, true));
t('2 the redirect address (rePage) is not in the page (spec §19 bug 1)', strpos($body, 'redirect-target.test'), false);

/* 3 */
$entries = function () use ($H, $fid) { $e = (array) $H->ctl('entries', (string) $fid); usort($e, function ($a, $b) { return (int) $b['msg_id'] - (int) $a['msg_id']; }); return $e; };
$ids_of = function ($e) { return array_values(array_filter(array_map(function ($r) { return isset($r['id_']) ? $r['id_'] : null; }, isset($e['content']) ? (array) $e['content'] : array()))); };
$H->fresh_jar();
$s = $H->submit($fid, array(
	array('id_' => $id['pub'], 'name' => 'Public name', 'type' => 'text', 'value' => 'Guest'),
	array('id_' => $id['ed'], 'name' => 'Editor code', 'type' => 'text', 'value' => 'forged-by-guest'),
	array('id_' => $id['ch'], 'id_ob' => $id['o1'], 'name' => 'Member plan', 'type' => 'radio', 'value' => 'Secret Gold'),
));
$a = EfbF11Http::answer($s);
t('3 guest: a submit without the required editor field is accepted', !empty($a['success']), true);
$e = $entries();
$got = $ids_of($e[0] ?? array());
t('3 guest: the forged answers for hidden fields are not stored, the public one is', array(in_array($id['pub'], $got, true), in_array($id['ed'], $got, true), in_array($id['ch'], $got, true), strpos(json_encode($e[0] ?? array()), 'forged-by-guest')), array(true, false, false, false));
$sc = $H->http('GET', '/wp-json/Emsfb/v1/forms/' . $fid . '/schema');
$sids = array_column(is_array($sc['json']) && isset($sc['json']['fields']) ? $sc['json']['fields'] : array(), 'id');
t('3 forms/{id}/schema (guest): the public field, not the hidden ones; private, no-store', array(in_array($id['pub'], $sids, true), in_array($id['ed'], $sids, true), in_array($id['ch'], $sids, true), (bool) preg_match('/^cache-control:[^\r\n]*private[^\r\n]*no-store/im', $sc['headers'])), array(true, false, false, true));

/* 4 */
$H->fresh_jar();
$H->login($sub['login'], $sub['password']);
$b = $H->http('GET', $path, null, array(), false)['body'];
t('4 subscriber page: the signed-in choice yes, the editor field no; step 2 not empty', array(strpos($b, $id['ch']) !== false, strpos($b, $id['ed']) !== false, (bool) preg_match('/id="step-2-efb"[^>]*data-vis-empty/', $b)), array(true, false, false));
$s = $H->submit($fid, array(array('id_' => $id['pub'], 'name' => 'Public name', 'type' => 'text', 'value' => 'Sub'), array('id_' => $id['ch'], 'id_ob' => $id['o2'], 'name' => 'Member plan', 'type' => 'radio', 'value' => 'Secret Silver'), array('id_' => $id['ed'], 'name' => 'Editor code', 'type' => 'text', 'value' => 'forged-by-sub')));
$e = $entries();
t('4 subscriber: the choice stored, the forged editor answer not', array(!empty(EfbF11Http::answer($s)['success']), in_array($id['ch'], $ids_of($e[0] ?? array()), true), in_array($id['ed'], $ids_of($e[0] ?? array()), true)), array(true, true, false));
$H->fresh_jar();
$H->login($ed['login'], $ed['password']);
$b = $H->http('GET', $path, null, array(), false)['body'];
t('4 editor page: the editor field is there', strpos($b, $id['ed']) !== false && strpos($b, 'Editor code') !== false, true);
$s = $H->submit($fid, array(array('id_' => $id['pub'], 'name' => 'Public name', 'type' => 'text', 'value' => 'Ed')));
$a = EfbF11Http::answer($s);
t('4 editor: refused without the required editor field, at that field', array(empty($a['success']), $a['field_id'] ?? ''), array(true, $id['ed']));
$s = $H->submit($fid, array(array('id_' => $id['pub'], 'name' => 'Public name', 'type' => 'text', 'value' => 'Ed'), array('id_' => $id['ed'], 'name' => 'Editor code', 'type' => 'text', 'value' => 'E-42')));
$e = $entries();
t('4 editor: stored with it', array(!empty(EfbF11Http::answer($s)['success']), in_array($id['ed'], $ids_of($e[0] ?? array()), true)), array(true, true));
$editor_msg = (int) ($e[0]['msg_id'] ?? 0);

/* 5 */
$H->ctl('set-option', 'efb_e2e_f10_nobypass', '1');
$g = $H->http('GET', $path, null, array(), false);
// (WordPress itself sends no-store to a signed-in user; the plugin's own mark is cf-edge-cache.)
t('5 cacheable page (site filter): the shared view, even for the editor (vis_view public), not taken out of caches by the plugin', array(strpos($g['body'], $id['ed']), (bool) preg_match('/vis_view[\\\\"]*:[\\\\"]*public/', $g['body']), (bool) preg_match('/^cf-edge-cache:/im', $g['headers'])), array(false, true, false));
$s = $H->submit($fid, array(array('id_' => $id['pub'], 'name' => 'Public name', 'type' => 'text', 'value' => 'Ed cached'), array('id_' => $id['ed'], 'name' => 'Editor code', 'type' => 'text', 'value' => 'from-cached-page')), array('vis_view' => 'public'));
$e = $entries();
t('5 its submit (vis_view public): the editor field not required, its answer dropped', array(!empty(EfbF11Http::answer($s)['success']), in_array($id['ed'], $ids_of($e[0] ?? array()), true)), array(true, false));
$s = $H->submit($fid, array(array('id_' => $id['pub'], 'name' => 'Public name', 'type' => 'text', 'value' => 'Ed hdr')), array(), null);
t('5 without the mark the editor is checked as an editor (refused at the field)', EfbF11Http::answer($s)['field_id'] ?? '', $id['ed']);
$prep = $H->prepare($fid);
$p = is_array($prep['json']) ? $prep['json'] : array();
$rows = array(array('form_id' => $fid, 'amount' => 2, 'id_' => $id['pub'], 'name' => 'Public name', 'type' => 'text', 'value' => 'Ed header'));
$s = $H->http('POST', '/wp-json/Emsfb/v1/forms/message/add', array('id' => $fid, 'type' => 'form', 'sid' => $p['sid'] ?? '', 'runtime_token' => $p['runtime_token'] ?? '', 'request_id' => EfbF11Http::uuid(), 'name' => 'F10', 'url' => $H->base . '/', 'page_id' => 0, 'value' => json_encode($rows)),
	array('X-WP-Nonce' => $p['nonce'] ?? '', 'sid' => $p['sid'] ?? '', 'form-id' => (string) $fid, 'X-EFB-Vis-View' => 'public'));
t('5 the X-EFB-Vis-View header (payments/quote and payments/create send it) works the same', !empty(EfbF11Http::answer($s)['success']), true);
$H->ctl('set-option', 'efb_e2e_f10_nobypass', '0');

/* 6 */
$wp_load = rtrim(getenv('EFB_E2E_SITE_DIR'), '/') . '/wp-load.php';
if (is_readable($wp_load) && $editor_msg) {
	require $wp_load;
	// The row entries.read (efb_api_entry_get / efb_api_entries_query) gives, without the site's ai_entries_access switch.
	$entry_m = new ReflectionMethod("\\Emsfb\\Entries", "entry");
	$entry_m->setAccessible(true);
	$en = $entry_m->invoke(null, $editor_msg);
	$api_ids = function () use ($en) { $r = \Emsfb\Entries::api_row($en, \Emsfb\Entries::form_rows($en['form_id']), true); return array_column($r['fields'], 'id_'); };
	wp_set_current_user(1);
	$x = $api_ids();
	t('6 entries.read as the administrator (no editor role): the editor-only answer is left out, the public one stays', array(in_array($id['pub'], $x, true), in_array($id['ed'], $x, true)), array(true, false));
	wp_set_current_user((int) $ed['id']);
	$x = $api_ids();
	t('6 entries.read as an editor: the answer is there', in_array($id['ed'], $x, true), true);
}

echo "\nRESULTS: {$passed} passed, {$failed} failed\n";
exit($failed ? 1 : 0);
