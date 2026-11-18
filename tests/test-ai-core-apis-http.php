<?php
/**
 * The core APIs the AI builder plugin (AI Site Assistant AutoPilot) needs, on the real WordPress of tests/e2e-wp
 * (skips without it). docs/autopilot-integration.md "AI builder core APIs (AI-1 … AI-8)".
 *
 *  AI-1  the save warnings in the hooks: a builder save (admin-ajax update_form_Emsfb) with a key of an add-on that is
 *        off answers addon_keys_kept, and efb_form_saved carries the same warnings (codes and messages); a new form
 *        (add_form_Emsfb) and an import fire efb_form_created with the warnings key;
 *  AI-2  form.update over REST (cookie + X-WP-Nonce, and an application password): read → base_hash, update → the
 *        builder's save path (sanitized label, a version "autopilot", the add-on keys kept), and every refusal:
 *        no nonce 401, a subscriber 403, base_hash_missing, stale_hash, unknown_field, invalid_patch, duplicate_id,
 *        addon_required (a field and a key of an add-on that is off), plan_required (a third step and a Free Plus
 *        setting on Free), pattern_invalid (the save path's own check), html_not_allowed; nothing written by a refusal;
 *  AI-3  import() name_policy keep / suffix (default) / fail;
 *  AI-4  a draft: nothing for a visitor (shortcode page, block page, session prepare 404), the form and a notice for
 *        the admin; REST publish / status, the forms list's admin-ajax Publish (nonce checked); old rows (status 0,
 *        NULL, 1) are published;
 *  AI-5  field_templates_efb() of an add-on's field while the add-on is off: addon_required with the add-on (calc,
 *        maps, stripe, mollie); with it on: the rows;
 *  AI-6  capabilities.payment_gateways lists a gateway whose add-on is off (installed / active / plan / addon / state);
 *  AI-7  one version: the plugin header = EMSFB_PLUGIN_VERSION = the registry = the form package = the templates.
 *
 * Run (under the e2e lock): EFB_E2E_SITE_DIR=… EFB_E2E_WPCLI=… php tests/test-ai-core-apis-http.php
 */
$passed = 0; $failed = 0;
function t($name, $ok, $detail = null) { global $passed, $failed; if ($ok) { echo "[PASS] {$name}\n"; $passed++; } else { echo "[FAIL] {$name}" . ($detail !== null ? '  ' . substr(json_encode($detail, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 0, 900) : '') . "\n"; $failed++; } }
require __DIR__ . '/f11-http-lib.php';
$H = new EfbF11Http();
if ($why = $H->unavailable()) { echo "[SKIP] {$why}\nRESULTS: 0 passed, 0 failed\n"; exit(0); }
$ai = function ($cmd, ...$args) use ($H) {
	$parts = array_merge(preg_split('/\s+/', trim($H->wpcli)), array('eval-file', $H->root . '/tests/e2e-wp/ctl-ai.php', $cmd), array_map('strval', $args), array('--path=' . $H->site, '--allow-root'));
	$out = shell_exec(implode(' ', array_map('escapeshellarg', $parts)) . ' 2>/dev/null');
	$lines = array_values(array_filter(explode("\n", (string) $out), 'strlen'));
	return json_decode($lines ? end($lines) : '', true);
};
$tmp = sys_get_temp_dir() . '/efb-ai-' . getmypid() . '.json';
$file = function ($v) use ($tmp) { file_put_contents($tmp, json_encode($v, JSON_UNESCAPED_UNICODE)); return $tmp; };
$made = array();
$flags = function ($map) use ($H) { foreach ($map as $k => $v) $H->ctl('addon-flag', $k, $v ? '1' : '0'); };
$before = $ai('caps', 'split');
$was = array();
foreach (array('AdnTSG', 'AdnCLC', 'AdnADR', 'AdnSPF') as $c) $was[$c] = !empty($before['addons'][$c]['installed']);
$was['AdnMOL'] = false;

$ai('mu');
$H->ctl('plan', 3);
$flags(array('AdnTSG' => 1, 'AdnCLC' => 1, 'AdnADR' => 1));
$admin = getenv('EFB_E2E_USER') ?: 'admin';
$H->login($admin, getenv('EFB_E2E_PASS') ?: 'admin-pass-123');
$nonce = $H->prepare(0)['json']['nonce'] ?? '';
$rest = function ($method, $path, $body = null, $headers = array(), $jar = null) use ($H, $nonce) {
	return $H->http($method, '/wp-json/Emsfb/v1' . $path, $body, $headers + array('X-WP-Nonce' => $nonce), true, $jar);
};

try {
	/* ------------------------------------------------------------------ AI-7: one version */
	$v = $ai('versions');
	t('AI-7 one version: header ' . ($v['header'] ?? '?') . ' = EMSFB_PLUGIN_VERSION = registry efb_version = form package plugin_version = field templates efb_version (4.3.2)', is_array($v) && $v['header'] === '4.3.2' && $v['constant'] === $v['header'] && $v['registry'] === $v['header'] && $v['package'] === $v['header'] && $v['templates'] === $v['header'], $v);

	/* ------------------------------------------------------------------ AI-1: the save warnings in the hooks */
	$F = $H->ctl('f03-form', 'AI core save', json_encode(array(
		array('key' => 'nm', 'type' => 'text', 'name' => 'Name'),
		array('key' => 'em', 'type' => 'email', 'name' => 'E-mail'),
		array('key' => 'sg', 'type' => 'esign', 'name' => 'Signature', 'esign_mode' => 'type', 'esign_font' => 'latin'),
	)));
	$fid = (int) $F['form_id'];
	$made[] = $fid;
	$st = $H->ctl('form', (string) $fid);
	$sgrow = null;
	foreach ($st['form_structer'] as $r) if (($r['type'] ?? '') === 'esign') $sgrow = $r;
	t('fixture: the signature keeps esign_mode type while Typed Signature is on', ($sgrow['esign_mode'] ?? '') === 'type', $sgrow);
	$flags(array('AdnTSG' => 0));
	$ai('saved', 'clear');
	$rows = $st['form_structer'];
	foreach ($rows as $i => $r) if (($r['type'] ?? '') === 'esign') $rows[$i]['esign_mode'] = 'draw';
	$ar = $H->http('POST', '/wp-admin/admin-ajax.php', http_build_query(array('action' => 'update_form_Emsfb', 'nonce' => $nonce, 'id' => $fid, 'name' => 'AI core save', 'value' => json_encode($rows, JSON_UNESCAPED_UNICODE))), array('Content-Type' => 'application/x-www-form-urlencoded'), false);
	$ans = $ar['json']['data'] ?? array();
	$kept = array_values(array_filter((array) ($ans['warnings'] ?? array()), function ($w) { return ($w['code'] ?? '') === 'addon_keys_kept'; }));
	t('AI-1 builder save with Typed Signature off: saved, the answer has addon_keys_kept (AdnTSG, esign_mode) with its message', !empty($ans['success']) && $kept && $kept[0]['addon'] === 'AdnTSG' && in_array('esign_mode', $kept[0]['keys'], true) && $kept[0]['m'] !== '', $ans);
	$rec = $ai('saved');
	$last = is_array($rec) && $rec ? end($rec) : null;
	$codes = function ($ws) { return array_map(function ($w) { return ($w['code'] ?? '') . '|' . ($w['m'] ?? ''); }, (array) $ws); };
	t('AI-1 efb_form_saved carries the same warnings (codes and messages) as the answer, source builder', $last && $last['hook'] === 'efb_form_saved' && $last['form_id'] === $fid && $last['source'] === 'builder' && $codes($last['warnings']) === $codes($ans['warnings'] ?? array()), array('hook' => $last, 'answer' => $ans['warnings'] ?? null));
	$src = $st['form_structer'];
	$src[0]['formName'] = 'AI core new';
	$ai('saved', 'clear');
	$cr = $H->http('POST', '/wp-admin/admin-ajax.php', http_build_query(array('action' => 'add_form_Emsfb', 'nonce' => $nonce, 'name' => 'AI core new', 'type' => 'form', 'value' => json_encode($src, JSON_UNESCAPED_UNICODE))), array('Content-Type' => 'application/x-www-form-urlencoded'), false);
	$newid = (int) ($cr['json']['data']['id'] ?? 0);
	$made[] = $newid;
	$rec = $ai('saved');
	$last = is_array($rec) && $rec ? end($rec) : null;
	t('AI-1 a new form (add_form_Emsfb): efb_form_created carries the warnings key, the same as the answer', $newid > 0 && $last && $last['hook'] === 'efb_form_created' && $last['has_warnings_key'] && $codes($last['warnings']) === $codes($cr['json']['data']['warnings'] ?? array()), $last);
	$flags(array('AdnTSG' => 1));

	/* ------------------------------------------------------------------ AI-2: form.update over REST */
	$noauth = $H->http('GET', '/wp-json/Emsfb/v1/admin/forms/' . $fid . '/package', null, array(), true);
	t('AI-2 read without the REST nonce (cookie only): 401, nothing read', $noauth['status'] === 401 && !isset($noauth['json']['rows']), array($noauth['status'], $noauth['json']['code'] ?? null));
	$rd = $rest('GET', '/admin/forms/' . $fid . '/package');
	$base = $rd['json']['base_hash'] ?? '';
	t('AI-2 read (cookie + X-WP-Nonce): 200, the rows, the status and a base_hash', $rd['status'] === 200 && preg_match('/^[0-9a-f]{64}$/', $base) && count($rd['json']['rows']) >= 5 && $rd['json']['status'] === 'published', array($rd['status'], $rd['json']['code'] ?? null));
	$ids = $F['ids'];
	$up = $rest('POST', '/admin/forms/' . $fid . '/update', array('base_hash' => $base, 'note' => 'AI test', 'patches' => array(
		array('op' => 'field', 'id' => $ids['nm'], 'set' => array('name' => 'Full <b>name</b>', 'required' => 1)),
		array('op' => 'insert', 'rows' => array(array('id_' => 'aiphone01', 'type' => 'tel', 'name' => 'Phone')), 'after' => $ids['em']),
		array('op' => 'insert', 'rows' => array(array('id_' => 'aisize001', 'type' => 'radio', 'name' => 'Size')), 'step' => 1),
		array('op' => 'options', 'id' => 'aisize001', 'options' => array('Small', array('value' => 'Large', 'opt_value' => 'l'))),
		array('op' => 'form', 'set' => array('thank_you_message' => array('icon' => 'bi-hand-thumbs-up', 'thankYou' => 'Thanks!', 'done' => 'Done', 'trackingCode' => 'Code', 'pleaseFillInRequiredFields' => 'Fill'))),
	)));
	$u = $up['json'];
	$st2 = $H->ctl('form', (string) $fid);
	$by = array(); foreach ($st2['form_structer'] as $i => $r) if (isset($r['id_'])) $by[$r['id_']] = $r + array('_i' => $i);
	$opts = array_values(array_filter($st2['form_structer'], function ($r) { return ($r['type'] ?? '') === 'option' && ($r['parent'] ?? '') === 'aisize001'; }));
	t('AI-2 update: 200, a new base_hash, the changed ids, warnings, stored through the save path', $up['status'] === 200 && preg_match('/^[0-9a-f]{64}$/', $u['base_hash'] ?? '') && $u['base_hash'] !== $base && $u['previous_hash'] === $base && in_array('aiphone01', $u['changed'], true) && is_array($u['warnings']), array($up['status'], $u));
	t('AI-2 the sanitizer ran: the label without its tags, required stored as the builder stores it', ($by[$ids['nm']]['name'] ?? '') === 'Full name' && (string) ($by[$ids['nm']]['required'] ?? '') === '1', $by[$ids['nm']] ?? null);
	t('AI-2 the new field sits right after the e-mail field (renumbered), the choices are option rows of the new field', isset($by['aiphone01'], $by[$ids['em']]) && $by['aiphone01']['_i'] === $by[$ids['em']]['_i'] + 1 && (int) $by['aiphone01']['amount'] === (int) $by[$ids['em']]['amount'] + 1 && count($opts) === 2 && $opts[1]['value'] === 'Large' && ($opts[1]['opt_value'] ?? '') === 'l', array($by['aiphone01'] ?? null, $opts));
	t('AI-2 row 0 changed (thank-you text), the read gives the new base_hash', ($st2['form_structer'][0]['thank_you_message']['thankYou'] ?? '') === 'Thanks!' && $rest('GET', '/admin/forms/' . $fid . '/package')['json']['base_hash'] === $u['base_hash']);
	$revs = $H->ctl('f15-revs', (string) $fid);
	t('AI-2 a version "autopilot" with the note is kept (efb_form_saved, Revisions)', ($revs['revs'][0]['source'] ?? '') === 'autopilot' && ($revs['revs'][0]['note'] ?? '') === 'AI test', $revs['revs'][0] ?? $revs);
	$snap = $H->ctl('form', (string) $fid)['form_structer'];
	$stale = $rest('POST', '/admin/forms/' . $fid . '/update', array('base_hash' => $base, 'patches' => array(array('op' => 'name', 'value' => 'Late'))));
	t('AI-2 the old base_hash again: 409 stale_hash with the current hash, nothing written', $stale['status'] === 409 && ($stale['json']['code'] ?? '') === 'stale_hash' && ($stale['json']['data']['current_hash'] ?? '') === $u['base_hash'] && $H->ctl('form', (string) $fid)['form_structer'] === $snap, array($stale['status'], $stale['json']));
	$h = $u['base_hash'];
	$refuse = function ($patches, $label, $code, $status, $check = null) use ($rest, $fid, &$h, $H, &$snap) {
		$r = $rest('POST', '/admin/forms/' . $fid . '/update', array('base_hash' => $h, 'patches' => $patches));
		$same = $H->ctl('form', (string) $fid)['form_structer'] === $snap;
		t('AI-2 refused: ' . $label . ' → ' . $status . ' ' . $code . ', nothing written', $r['status'] === $status && ($r['json']['code'] ?? '') === $code && $same && ($check === null || $check($r['json'])), array($r['status'], $r['json'], 'unchanged' => $same));
	};
	$refuse(array(array('op' => 'field', 'id' => 'nope12345', 'set' => array('name' => 'x'))), 'a field that is not there', 'unknown_field', 404, function ($j) { return ($j['data']['id'] ?? '') === 'nope12345'; });
	$r = $rest('POST', '/admin/forms/' . $fid . '/update', array('patches' => array(array('op' => 'name', 'value' => 'x'))));
	t('AI-2 refused: no base_hash → 400 base_hash_missing', $r['status'] === 400 && ($r['json']['code'] ?? '') === 'base_hash_missing', array($r['status'], $r['json']));
	$refuse(array(array('op' => 'explode')), 'an unknown op', 'invalid_patch', 400);
	$refuse(array(array('op' => 'field', 'id' => $ids['nm'], 'set' => array('type' => 'email'))), 'changing a field\'s type', 'invalid_patch', 400);
	$refuse(array(array('op' => 'insert', 'rows' => array(array('id_' => $ids['nm'], 'type' => 'text')))), 'an id that is used', 'duplicate_id', 409);
	$refuse(array(array('op' => 'insert', 'rows' => array(array('id_' => 'aiopt0001', 'type' => 'option', 'parent' => 'nobody99', 'value' => 'x')))), 'an option without its field', 'orphan_option', 400);
	$refuse(array(array('op' => 'insert', 'rows' => array(array('id_' => 'ainew0001', 'type' => 'text')), 'after' => 'nobody99')), 'a position after a field that is not there', 'unknown_target', 404);
	$refuse(array(array('op' => 'field', 'id' => $ids['nm'], 'set' => array('pattern_b64' => '@@@')), array('op' => 'field', 'id' => $ids['nm'], 'set' => array('name' => 'Pattern'))), 'a pattern the builder\'s save refuses (the save path\'s own check)', 'pattern_invalid', 400);
	$refuse(array(array('op' => 'field', 'id' => $ids['nm'], 'set' => array('message' => 'Hi <script>alert(1)</script>'))), 'a script', 'html_not_allowed', 400);
	$flags(array('AdnCLC' => 0));
	$refuse(array(array('op' => 'insert', 'rows' => array(array('id_' => 'aicalc001', 'type' => 'calc', 'name' => 'Total', 'formula' => '1+1')))), 'a calculated field while Numbers & Calculations is off', 'addon_required', 409, function ($j) { return ($j['data']['addon'] ?? '') === 'AdnCLC' && ($j['data']['feature'] ?? '') === 'field:calc'; });
	$flags(array('AdnCLC' => 1, 'AdnTSG' => 0));
	$refuse(array(array('op' => 'field', 'id' => $ids['sg'], 'set' => array('esign_mode' => 'draw'))), 'a change of a Typed Signature key while that add-on is off', 'addon_required', 409, function ($j) { return ($j['data']['addon'] ?? '') === 'AdnTSG'; });
	$ok = $rest('POST', '/admin/forms/' . $fid . '/update', array('base_hash' => $h, 'patches' => array(array('op' => 'field', 'id' => $ids['sg'], 'set' => array('name' => 'Sign here')))));
	$sg2 = null; foreach ($H->ctl('form', (string) $fid)['form_structer'] as $r) if (($r['type'] ?? '') === 'esign') $sg2 = $r;
	$kw = array_values(array_filter((array) ($ok['json']['warnings'] ?? array()), function ($w) { return ($w['code'] ?? '') === 'addon_keys_kept'; }));
	t('AI-2 a change that keeps an absent add-on\'s keys: saved, its stored keys kept (addon_keys_kept, as the builder\'s save)', $ok['status'] === 200 && ($sg2['name'] ?? '') === 'Sign here' && ($sg2['esign_mode'] ?? '') === 'type' && $kw && $kw[0]['addon'] === 'AdnTSG', array($ok['status'], $ok['json'], $sg2));
	$h = $ok['json']['base_hash'] ?? $h;
	$snap = $H->ctl('form', (string) $fid)['form_structer'];
	$flags(array('AdnTSG' => 1));
	$H->ctl('plan', 2);
	$refuse(array(array('op' => 'insert', 'rows' => array(array('id_' => 's2', 'type' => 'step', 'name' => 'Two'))), array('op' => 'insert', 'rows' => array(array('id_' => 's3', 'type' => 'step', 'name' => 'Three')))), 'a third step on Free', 'plan_required', 403, function ($j) { return ($j['data']['feature'] ?? '') === 'limit:steps'; });
	$refuse(array(array('op' => 'form', 'set' => array('email_sub' => 'Hello'))), 'a Free Plus setting (the e-mail subject) on Free', 'plan_required', 403, function ($j) { return ($j['data']['feature'] ?? '') === 'setting:email_sub' && ($j['data']['package'] ?? '') === 'free_plus'; });
	$r = $rest('POST', '/admin/forms/' . $fid . '/update', array('base_hash' => $h, 'patches' => array(array('op' => 'form', 'set' => array('email_sub' => '')))));
	t('AI-2 turning a Free Plus setting off is allowed on Free', $r['status'] === 200, array($r['status'], $r['json']));
	$h = $r['json']['base_hash'] ?? $h;
	$r = $rest('POST', '/admin/forms/' . $fid . '/update', array('base_hash' => $h, 'patches' => array(
		array('op' => 'insert', 'rows' => array(array('id_' => 'aicity001', 'type' => 'text', 'name' => 'City', 'size' => '50', 'mobile_size' => '50')), 'after' => 'aiphone01'),
		array('op' => 'options', 'id' => 'aisize001', 'options' => array(array('value' => 'Small (S)', 'id_' => $opts[0]['id_'] ?? ''), 'Medium')),
	)));
	$o2 = array_values(array_filter($H->ctl('form', (string) $fid)['form_structer'], function ($x) { return ($x['type'] ?? '') === 'option' && ($x['parent'] ?? '') === 'aisize001'; }));
	t('AI-2 on Free: a new field whose phone size only repeats its size is no mobile layout (saved); a choice that gives its id_ keeps it, renamed', $r['status'] === 200 && count($o2) === 2 && ($o2[0]['id_'] ?? '') === ($opts[0]['id_'] ?? '-') && $o2[0]['value'] === 'Small (S)' && $o2[1]['id_'] !== ($opts[1]['id_'] ?? '') && $o2[1]['value'] === 'Medium', array($r['status'], $r['json'], $o2));
	$h = $r['json']['base_hash'] ?? $h;
	$H->ctl('plan', 3);
	$H->ctl('user', 'efb_ai_sub', 'subscriber');
	$sjar = sys_get_temp_dir() . '/efb-ai-sub-' . getmypid();
	$H2 = new EfbF11Http();
	$H2->login('efb_ai_sub', 'efb_ai_sub-pass-123');
	$sn = $H2->prepare(0)['json']['nonce'] ?? '';
	$sr = $H2->http('GET', '/wp-json/Emsfb/v1/admin/forms/' . $fid . '/package', null, array('X-WP-Nonce' => $sn));
	$sp = $H2->http('POST', '/wp-json/Emsfb/v1/admin/forms/' . $fid . '/update', array('base_hash' => $h, 'patches' => array(array('op' => 'name', 'value' => 'x'))), array('X-WP-Nonce' => $sn));
	t('AI-2 a subscriber (logged in, with a nonce): 403 for read and update', $sr['status'] === 403 && $sp['status'] === 403, array($sr['status'], $sp['status']));
	$app = $ai('app-password', $admin);
	$auth = array('Authorization' => 'Basic ' . base64_encode($admin . ':' . ($app['password'] ?? '')));
	$H3 = new EfbF11Http();
	$ar = $H3->http('GET', '/wp-json/Emsfb/v1/admin/forms/' . $fid . '/package', null, $auth);
	$au = $H3->http('POST', '/wp-json/Emsfb/v1/admin/forms/' . $fid . '/update', array('base_hash' => $ar['json']['base_hash'] ?? '', 'patches' => array(array('op' => 'name', 'value' => 'AI core save (app)'))), $auth);
	t('AI-2 an application password (no cookie, no nonce): read and update', !empty($app['password']) && $ar['status'] === 200 && $au['status'] === 200 && ($au['json']['form_name'] ?? '') === 'AI core save (app)', array($ar['status'], $au['status'], $au['json']['code'] ?? null));
	$bad = $H3->http('GET', '/wp-json/Emsfb/v1/admin/forms/' . $fid . '/package', null, array('Authorization' => 'Basic ' . base64_encode($admin . ':wrong pass word here')));
	t('AI-2 a wrong application password: refused (401)', $bad['status'] === 401, $bad['status']);
	$caps = $ai('caps', 'form_update');
	t('AI-2 the registry: capabilities.form_update v1 with the ops, the routes and the codes', ($caps['v'] ?? 0) === 1 && in_array('stale_hash', $caps['codes'], true) && in_array('addon_required', $caps['codes'], true) && in_array('plan_required', $caps['codes'], true) && count($caps['ops']) === 6, $caps);
	$fp = $ai('caps', 'form_package');
	t('AI-2 the form package says version 1.2 with read / update / publish / set_status', in_array('update', (array) ($fp['functions'] ?? array()), true) && in_array('publish', (array) ($fp['functions'] ?? array()), true), $fp['functions'] ?? $fp);

	/* ------------------------------------------------------------------ AI-3: name_policy */
	$pkg = array('schema' => 1, 'generator' => 'test', 'form' => array('form_name' => 'AI core save (app)', 'form_type' => 'form', 'form_structer' => $H->ctl('form', (string) $fid)['form_structer']));
	$k = $ai('import', $file($pkg), 'name_policy=keep');
	$s = $ai('import', $file($pkg));
	$x = $ai('import', $file($pkg), 'name_policy=fail');
	$made[] = (int) ($k['form_id'] ?? 0); $made[] = (int) ($s['form_id'] ?? 0);
	t('AI-3 name_policy keep: the same name (no "(imported)")', ($k['form_name'] ?? '') === 'AI core save (app)', $k);
	t('AI-3 the default stays suffix: "… (imported)" (Data Tools unchanged)', ($s['form_name'] ?? '') === 'AI core save (app) (imported)', $s);
	t('AI-3 name_policy fail: package_name_exists (409), no form made', ($x['error'] ?? '') === 'package_name_exists' && ($x['data']['status'] ?? 0) === 409, $x);
	t('AI-3 import answers the save warnings apart and a base_hash for update()', is_array($k['warnings'] ?? null) && preg_match('/^[0-9a-f]{64}$/', $k['base_hash'] ?? ''), array_keys((array) $k));

	/* ------------------------------------------------------------------ AI-4: draft */
	$dp = $pkg;
	$dp['form']['form_name'] = 'AI draft form';
	$dp['form']['form_structer'][1]['name'] = 'Draft step';
	foreach ($dp['form']['form_structer'] as $i => $r) if (($r['id_'] ?? '') === $ids['nm']) $dp['form']['form_structer'][$i]['name'] = 'Draft only label';
	$ai('saved', 'clear');
	$d = $ai('import', $file($dp), 'status=draft&name_policy=keep');
	$did = (int) ($d['form_id'] ?? 0);
	$made[] = $did;
	$rec = $ai('saved');
	t('AI-4 import status=draft: a draft form; efb_form_created (source import) carries the warnings key', $did > 0 && $d['status'] === 'draft' && $ai('status', $did)['status'] === 'draft' && end($rec)['source'] === 'import' && end($rec)['has_warnings_key'], array($d, end($rec)));
	$pg = $H->ctl('page', (string) $did);
	$pb = $H->ctl('page', (string) $did, 'block');
	$path = function ($u) { return parse_url($u, PHP_URL_PATH) . (parse_url($u, PHP_URL_QUERY) ? '?' . parse_url($u, PHP_URL_QUERY) : ''); };
	$G = new EfbF11Http();
	$gs = $G->http('GET', $path($pg['url']), null, array(), false);
	$gb = $G->http('GET', $path($pb['url']), null, array(), false);
	t('AI-4 a visitor sees nothing of the draft (shortcode page and block page: no form, no notice)', $gs['status'] === 200 && strpos($gs['body'], 'Draft only label') === false && strpos($gs['body'], 'efb-draft-note') === false && $gb['status'] === 200 && strpos($gb['body'], 'Draft only label') === false && strpos($gb['body'], 'efb-draft-note') === false, array($gs['status'], $gb['status']));
	$gp = $G->prepare($did);
	t('AI-4 a visitor cannot start the draft (session prepare: 404 efb_form_missing)', $gp['status'] === 404 && ($gp['json']['code'] ?? '') === 'efb_form_missing', array($gp['status'], $gp['json']));
	$as = $H->http('GET', $path($pg['url']), null, array(), false);
	t('AI-4 the admin sees the draft with the notice above it (to try it)', strpos($as['body'], 'efb-draft-note') !== false && strpos($as['body'], 'Draft only label') !== false && strpos($as['body'], 'visitors do not see this form') !== false && $H->prepare($did)['status'] === 200, $as['status']);
	$ai('statuses', 'clear');
	$pr = $rest('POST', '/admin/forms/' . $did . '/publish');
	$gs2 = $G->http('GET', $path($pg['url']), null, array(), false);
	$sts = $ai('statuses');
	t('AI-4 REST publish: published; the visitor sees the form; efb_form_status_changed draft → published', $pr['status'] === 200 && ($pr['json']['status'] ?? '') === 'published' && strpos($gs2['body'], 'Draft only label') !== false && strpos($gs2['body'], 'efb-draft-note') === false && end($sts)['new'] === 'published' && end($sts)['old'] === 'draft', array($pr['status'], $pr['json'], $sts));
	$sd = $rest('POST', '/admin/forms/' . $did . '/status', array('status' => 'draft'));
	t('AI-4 REST status draft: a draft again (the visitor sees nothing)', ($sd['json']['status'] ?? '') === 'draft' && strpos($G->http('GET', $path($pg['url']), null, array(), false)['body'], 'Draft only label') === false, $sd['json']);
	$badst = $rest('POST', '/admin/forms/' . $did . '/status', array('status' => 'hidden'));
	t('AI-4 REST status of another value: 400 status_invalid', $badst['status'] === 400 && ($badst['json']['code'] ?? '') === 'status_invalid', array($badst['status'], $badst['json']));
	$lp0 = $H->http('POST', '/wp-admin/admin-ajax.php', http_build_query(array('action' => 'efb_form_publish', 'nonce' => 'bad', 'id' => $did)), array('Content-Type' => 'application/x-www-form-urlencoded'), false);
	$lp = $H->http('POST', '/wp-admin/admin-ajax.php', http_build_query(array('action' => 'efb_form_publish', 'nonce' => $nonce, 'id' => $did)), array('Content-Type' => 'application/x-www-form-urlencoded'), false);
	t('AI-4 the forms list\'s Publish (admin-ajax efb_form_publish): a bad nonce is refused, the right one publishes', empty($lp0['json']['data']['success']) && !empty($lp['json']['data']['success']) && $ai('status', $did)['status'] === 'published', array($lp0['json'], $lp['json']));
	$sp2 = $H2->http('POST', '/wp-json/Emsfb/v1/admin/forms/' . $did . '/publish', null, array('X-WP-Nonce' => $sn));
	t('AI-4 a subscriber cannot publish (403)', $sp2['status'] === 403, $sp2['status']);
	$o0 = $ai('raw-status', $fid, '0'); $on = $ai('raw-status', $fid, 'NULL'); $o1 = $ai('raw-status', $fid, '1');
	t('AI-4 old rows: status 0, NULL and 1 are published (no form changes when this ships)', $o0['status'] === 'published' && $on['status'] === 'published' && $on['raw'] === null && $o1['status'] === 'published', array($o0, $on, $o1));
	$cs = $ai('caps', 'form_status');
	t('AI-4 the registry: capabilities.form_status (draft / published, ready, publish routes)', ($cs['v'] ?? 0) === 1 && $cs['ready'] === true && $cs['values'] === array('published', 'draft'), $cs);

	/* ------------------------------------------------------------------ AI-5: templates of an add-on's field */
	$flags(array('AdnCLC' => 0, 'AdnADR' => 0, 'AdnSPF' => 0));
	$c = $ai('templates', 'calc'); $m = $ai('templates', 'maps'); $s = $ai('templates', 'stripe'); $mo = $ai('templates', 'mollie');
	t('AI-5 calc with Numbers & Calculations off: addon_required AdnCLC, no rows, the builder\'s message', ($c['error'] ?? '') === 'addon_required' && $c['data']['addon'] === 'AdnCLC' && $c['m'] === 'A calculated field needs the Numbers & Calculations add-on (AdnCLC).', $c);
	t('AI-5 maps with Address & Postal off: addon_required AdnADR', ($m['error'] ?? '') === 'addon_required' && $m['data']['addon'] === 'AdnADR' && $m['m'] === 'The map field needs the Address & Postal add-on (AdnADR).', $m);
	t('AI-5 stripe with its add-on off: addon_required AdnSPF ("stripe needs the AdnSPF add-on.")', ($s['error'] ?? '') === 'addon_required' && $s['data']['addon'] === 'AdnSPF' && $s['m'] === 'stripe needs the AdnSPF add-on.', $s);
	t('AI-5 a PAY00 gateway (mollie) off: addon_required AdnMOL with the add-on data', ($mo['error'] ?? '') === 'addon_required' && $mo['data']['addon'] === 'AdnMOL', $mo);
	$flags(array('AdnCLC' => 1, 'AdnADR' => 1, 'AdnSPF' => 1));
	$c = $ai('templates', 'calc'); $m = $ai('templates', 'maps'); $s = $ai('templates', 'stripe'); $tx = $ai('templates', 'text');
	t('AI-5 with the add-ons on: the rows (calc, maps, stripe); a core field always', ($c['types'] ?? null) === array('calc') && ($m['types'] ?? null) === array('maps') && ($s['types'] ?? null) === array('stripe') && ($tx['types'] ?? null) === array('text'), array($c, $m, $s, $tx));

	/* ------------------------------------------------------------------ AI-6: gateways in the registry */
	$flags(array('AdnMOL' => 0));
	$g = $ai('caps', 'payment_gateways');
	$mol = $g['mollie'] ?? null;
	t('AI-6 payment_gateways lists Mollie while its add-on is off: installed false, active false, plan free_plus, addon AdnMOL, state addon', is_array($mol) && $mol['installed'] === false && $mol['active'] === false && $mol['plan'] === 'free_plus' && $mol['addon'] === 'AdnMOL' && $mol['state'] === 'addon' && $mol['field_type'] === 'mollie', $mol);
	$all = array('mollie', 'payplug', 'tappay', 'omise', 'paytabs', 'paymob', 'xendit', 'stripecheckout', 'paypalcheckout', 'zarinpalgate');
	t('AI-6 every gateway of the catalogue is listed with installed / active / plan / addon (Persia Pay: pro)', !array_diff($all, array_keys((array) $g)) && count(array_filter($all, function ($sl) use ($g) { return isset($g[$sl]['installed'], $g[$sl]['active'], $g[$sl]['plan'], $g[$sl]['addon']); })) === 10 && $g['zarinpalgate']['plan'] === 'pro' && $g['stripecheckout']['legacy_type'] === 'stripe', array_map(function ($x) { return array_intersect_key((array) $x, array_flip(array('installed', 'active', 'plan', 'addon', 'state'))); }, (array) $g));
	$flags(array('AdnMOL' => 1));
	$g2 = $ai('caps', 'payment_gateways');
	t('AI-6 Mollie on: active and installed, with its label and currencies from the add-on', ($g2['mollie']['active'] ?? false) === true && $g2['mollie']['installed'] === true && $g2['mollie']['state'] === 'active' && !empty($g2['mollie']['currencies']), $g2['mollie'] ?? null);
} finally {
	$flags($was);
	$H->ctl('plan', 3);
	$made = array_filter($made);
	if ($made) $ai('delete', implode(',', $made));
	@unlink($tmp);
}
echo "\nRESULTS: {$passed} passed, {$failed} failed\n";
exit($failed ? 1 : 0);
