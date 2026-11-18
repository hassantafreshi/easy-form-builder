<?php
/**
 * F15-D on the real WordPress of tests/e2e-wp (skips without it): the form package with the real save path.
 *
 *  - every form of the site (the forms the other scenarios made: masks, F02 … F14 keys, calculations, smart tags,
 *    repeater groups, sections, payments, logic) exported and imported again: none refused, and every form imported
 *    without a drop or a new id is stored exactly as the original (but the keys an export never keeps);
 *  - a fixture that uses calc formulas, smart tags, a repeater group, F11 limits, F13 privacy keys, logic rules and
 *    an invalid / a duplicate id: the references follow the new id; the import is a NEW form named "… (imported)";
 *    it fires efb_form_created (a version "import" is kept);
 *  - the same rows saved through the builder's own create action (add_form_Emsfb) and through import() are stored
 *    the same way (one save path);
 *  - malicious packages: a script in a label is cleaned, a PHP object string dropped, unknown types dropped, a newer
 *    schema / no rows / too deep refused, nothing stored for a refused one;
 *  - secrets: a bot token / admin numbers in row 0 never in an export; extras: the SMS texts of a form go with it
 *    (sms block), the import keeps them without the admin numbers (notice), a block of an inactive add-on is dropped
 *    with a notice; Duplicate (dup_efb) copies the SMS settings with the numbers; a payment form answers readiness.
 *
 * Run (under the e2e lock): EFB_E2E_SITE_DIR=… EFB_E2E_WPCLI=… php tests/test-form-package-http.php
 */
$passed = 0; $failed = 0;
function t($name, $ok, $detail = null) { global $passed, $failed; if ($ok) { echo "[PASS] {$name}\n"; $passed++; } else { echo "[FAIL] {$name}" . ($detail !== null ? '  ' . substr(json_encode($detail, JSON_UNESCAPED_UNICODE), 0, 900) : '') . "\n"; $failed++; } }
require __DIR__ . '/f11-http-lib.php';
$H = new EfbF11Http();
if ($why = $H->unavailable()) { echo "[SKIP] {$why}\nRESULTS: 0 passed, 0 failed\n"; exit(0); }
$tmp = sys_get_temp_dir() . '/efb-f15-pkg-' . getmypid() . '.json';
$imp = function ($pkg, $name = null) use ($H, $tmp) { file_put_contents($tmp, is_string($pkg) ? $pkg : json_encode($pkg, JSON_UNESCAPED_UNICODE)); return $name === null ? $H->ctl('f15-pkg-import', $tmp) : $H->ctl('f15-pkg-import', $tmp, $name); };
$made = array();
$H->ctl('plan', 3);

/* every form of the site */
$all = $H->ctl('f15-pkg-roundtrip-all');
t("every form of the site ({$all['forms']}) exported and imported again: none refused", is_array($all) && $all['forms'] > 10 && !$all['refused'], $all['refused'] ?? $all);
t('forms imported without a drop or a new id are stored exactly as the original, or as the original saved again when it was stored past the save path (' . ($all['identical'] ?? 0) . ' identical, ' . count((array) ($all['resaved'] ?? array())) . ' of them resaved)', is_array($all) && !$all['different'] && $all['identical'] > 10, array('different' => $all['different'] ?? null, 'dropped' => $all['dropped'] ?? null, 'remapped' => $all['remapped'] ?? null));
$okDrops = true;
foreach ((array) ($all['dropped'] ?? array()) as $fid => $codes) foreach ($codes as $c) if (!in_array($c, array('unknown_type', 'orphan_option', 'row_without_type', 'not_an_array', 'step_added', 'addon_inactive'), true)) $okDrops = false;
t('drops on the site\'s forms are only types this site does not know (a gateway add-on switched off), the keys of an add-on switched off (addon_inactive, SPLIT §4.2) and legacy shapes; a form saved without its step row gets one (' . count((array) ($all['repaired'] ?? array())) . ')', $okDrops, array($all['dropped'] ?? null, $all['repaired'] ?? null));

/* the fixture */
$F = $H->ctl('f03-form', 'F15 pkg source', json_encode(array(
	array('key' => 'qty', 'type' => 'number', 'name' => 'Quantity'),
	array('key' => 'em', 'type' => 'email', 'name' => 'E-mail'),
	array('key' => 'tot', 'type' => 'calc', 'name' => 'Total', 'formula' => '{field:@@qty@@}*5'),
	array('key' => 'hid', 'type' => 'hiddenField', 'name' => 'Copy', 'value' => '{field:@@qty@@|0}'),
	array('key' => 'grp', 'type' => 'repeater', 'name' => 'Guests'),
	array('key' => 'gn', 'type' => 'text', 'name' => 'Guest', 'group' => '@@grp@@'),
)), 'lim_email_field=@@em@@&thank_you_message[thankYou]=Thanks {field:@@qty@@}', '1');
$made[] = (int) $F['form_id'];
$pkg = $H->ctl('f15-pkg-export', (string) $F['form_id']);
t('export: schema 1, generator, plugin version, the form with decoded rows, extras', ($pkg['schema'] ?? null) === 1 && $pkg['generator'] === 'easy-form-builder' && is_array($pkg['form']['form_structer']) && count($pkg['form']['form_structer']) >= 8 && array_key_exists('extras', $pkg), array_keys((array) $pkg));
t('export without with_emails: the notification address is empty', ($pkg['form']['form_structer'][0]['email'] ?? 'x') === '');
// An invalid id: rename the number field everywhere it is named.
$q = $F['ids']['qty'];
$bad = json_decode(str_replace($q, 'qty field!', json_encode($pkg, JSON_UNESCAPED_UNICODE)), true);
$bad['form']['form_structer'][0]['logic_rules'] = array(array('id' => 'r1', 'type' => 'field', 'conditions' => array('operator' => 'and', 'items' => array(array('field' => 'qty field!', 'operator' => 'gt', 'value' => '2'))), 'actions' => array(array('type' => 'show', 'target' => $F['ids']['em']))));
$r = $imp($bad);
$made[] = (int) ($r['form_id'] ?? 0);
$new = $r['id_map']['qty field!'] ?? '';
$st = $H->ctl('form', (string) ($r['form_id'] ?? 0));
$rows = $st['form_structer'] ?? array();
$by = function ($t) use ($rows) { foreach ($rows as $x) if (($x['type'] ?? '') === $t) return $x; return null; };
t('import: a NEW form named "… (imported)" (the name exists)', ($r['form_id'] ?? 0) > (int) $F['form_id'] && $r['form_name'] === 'F15 pkg source (imported)' && $st['form_type'] === 'form', $r);
t('the invalid id got a new one; the field, the calc formula, the hidden value, row 0\'s email field, the thank-you tag and the logic rule follow', preg_match('/^[a-z0-9]{9}$/', $new) && ($by('number')['id_'] ?? '') === $new && ($by('calc')['formula'] ?? '') === '{field:' . $new . '}*5' && ($by('hiddenField')['value'] ?? '') === '{field:' . $new . '|0}' && ($rows[0]['thank_you_message']['thankYou'] ?? '') === 'Thanks {field:' . $new . '}',
	array('map' => $r['id_map'] ?? null, 'calc' => $by('calc')['formula'] ?? null, 'hid' => $by('hiddenField')['value'] ?? null, 'ty' => $rows[0]['thank_you_message'] ?? null));
t('the repeater child keeps its group; lim_email_field still names the e-mail field', ($by('text')['group'] ?? '') === $F['ids']['grp'] && ($rows[0]['lim_email_field'] ?? '') === $F['ids']['em'], array($by('text'), $rows[0]['lim_email_field'] ?? null));
$v = $H->ctl('f15-revs', (string) $r['form_id']);
t('the import fired efb_form_created: a version "import" is kept', ($v['revs'][0]['source'] ?? '') === 'import', $v['revs'] ?? $v);

/* one save path: the builder's create action and import() store the same rows */
$H->login(getenv('EFB_E2E_USER') ?: 'admin', getenv('EFB_E2E_PASS') ?: 'admin-pass-123');
$nonce = $H->prepare(0)['json']['nonce'] ?? '';
$src = $pkg['form']['form_structer'];
$src[0]['formName'] = 'F15 pkg parity';
$ar = $H->http('POST', '/wp-admin/admin-ajax.php', http_build_query(array('action' => 'add_form_Emsfb', 'nonce' => $nonce, 'name' => 'F15 pkg parity', 'type' => 'form', 'value' => json_encode($src, JSON_UNESCAPED_UNICODE))), array('Content-Type' => 'application/x-www-form-urlencoded'), false);
$aid = (int) ($ar['json']['data']['id'] ?? 0);
$made[] = $aid;
$p2 = $pkg; $p2['form']['form_structer'] = $src;
$ir = $imp($p2, 'F15 pkg parity 2');
$made[] = (int) ($ir['form_id'] ?? 0);
$A = $H->ctl('form', (string) $aid)['form_structer'] ?? null;
$B = $H->ctl('form', (string) ($ir['form_id'] ?? 0))['form_structer'] ?? null;
foreach (array('A', 'B') as $n) if (is_array($$n)) unset(${$n}[0]['st_uses']);
t('the same rows through the builder\'s create action (add_form_Emsfb) and through import() are stored the same (one save path)', $aid > 0 && is_array($A) && json_encode($A, JSON_UNESCAPED_UNICODE) === json_encode($B, JSON_UNESCAPED_UNICODE), array('ajax' => $ar['json']['data'] ?? $ar['body']));

/* malicious */
$evil = $pkg;
$evil['form']['form_name'] = '<script>alert(1)</script>Evil';
$evil['form']['form_structer'][2]['name'] = '<img src=x onerror=alert(1)>Quantity<script>alert(2)</script>';
$evil['form']['form_structer'][2]['note_obj'] = 'O:8:"stdClass":0:{}';
$evil['form']['form_structer'][] = array('id_' => 'zz', 'type' => '<svg/onload=alert(1)>', 'step' => 1);
$evil['form']['form_structer'][] = array('id_' => 'yy', 'type' => 'text', 'name' => 'C', 'c_c' => 'x', 'autofill_conditions' => 'y', 'step' => 1);
$er = $imp($evil);
$made[] = (int) ($er['form_id'] ?? 0);
$es = $H->ctl('form', (string) ($er['form_id'] ?? 0));
$raw = (string) ($es['raw'] ?? '');
t('malicious: stored with no tag, no script, no PHP object; the unknown type dropped; c_c / autofill_conditions not arrays removed', ($er['form_id'] ?? 0) > 0 && stripos($raw, '<script') === false && stripos($raw, 'onerror') === false && stripos($raw, 'O:8:') === false && stripos($raw, 'svg') === false && strpos($es['form_name'], '<') === false,
	array('dropped' => $er['dropped'] ?? null, 'name' => $es['form_name'] ?? null));
$codes = array_column((array) ($er['dropped'] ?? array()), 'code');
t('…and the answer lists what was dropped (php_object, unknown_type, not_an_array)', in_array('php_object', $codes, true) && in_array('unknown_type', $codes, true) && in_array('not_an_array', $codes, true), $codes);
$count0 = count($H->ctl('forms'));
$deep = '1'; for ($i = 0; $i < 70; $i++) $deep = '[' . $deep . ']';
$refused = array($imp(array_merge($pkg, array('schema' => 2)))['error'] ?? '', $imp(array('schema' => 1, 'form' => array('form_structer' => 'x')))['error'] ?? '', $imp('{"schema":1,"form":{"form_structer":' . $deep . '}}')['error'] ?? '', $imp('not json')['error'] ?? '');
t('refused: a newer schema, no rows, nesting deeper than 64, not JSON — and nothing stored', $refused === array('package_newer', 'package_no_rows', 'package_too_deep', 'package_invalid_json') && count($H->ctl('forms')) === $count0, $refused);

/* secrets and extras */
$prevSms = $H->ctl('setting', 'AdnSS');
$H->ctl('addon-flag', 'AdnSS', '1');
$S = $H->ctl('f03-form', 'F15 pkg sms', json_encode(array(array('key' => 'mb', 'type' => 'mobile', 'name' => 'Mobile'))), 'smsnoti=1&telegram_bot_token=123:SECRETTOKEN&sms_admins_phone_no=%2B15550001', '1');
$made[] = (int) $S['form_id'];
$sp = $H->ctl('f15-pkg-export', (string) $S['form_id']);
$j = json_encode($sp, JSON_UNESCAPED_UNICODE);
t('secrets: the bot token and the admin numbers are never in an export', strpos($j, 'SECRETTOKEN') === false && strpos($j, '15550001') === false && !isset($sp['form']['form_structer'][0]['telegram_bot_token']));
// SMS texts as the builder stores them (the add-on's table), then exported as the sms block.
$sv = $H->http('POST', '/wp-admin/admin-ajax.php', http_build_query(array('action' => 'update_form_Emsfb', 'nonce' => $nonce, 'id' => $S['form_id'], 'name' => 'F15 pkg sms', 'value' => json_encode(array_merge(array(array_merge($sp['form']['form_structer'][0], array('smsnoti' => '1', 'sms_msg_new_noti' => 'New entry [confirmation_code]', 'sms_msg_responsed_noti' => 'Reply', 'sms_msg_recived_usr' => 'Thanks', 'sms_admins_phone_no' => '+15550001'))), array_slice($sp['form']['form_structer'], 1)), JSON_UNESCAPED_UNICODE))), array('Content-Type' => 'application/x-www-form-urlencoded'), false);
$sp = $H->ctl('f15-pkg-export', (string) $S['form_id']);
$hasSms = isset($sp['extras']['sms']);
if ($hasSms) {
	t('extras: the SMS texts go with the form (block sms, add-on AdnSS), without the admin numbers', $sp['extras']['sms']['addon'] === 'AdnSS' && $sp['extras']['sms']['data']['new_noti'] === 'New entry [confirmation_code]' && !isset($sp['extras']['sms']['data']['admin_numbers']), $sp['extras']['sms']);
	$sr = $imp($sp);
	$made[] = (int) ($sr['form_id'] ?? 0);
	t('import: the sms block applied, a notice that the numbers must be entered again', in_array('sms', (array) ($sr['extras_applied'] ?? array()), true) && in_array('sms_numbers_needed', array_column((array) $sr['notices'], 'code'), true), $sr);
	$dr = $H->http('POST', '/wp-admin/admin-ajax.php', http_build_query(array('action' => 'dup_efb', 'nonce' => $nonce, 'id' => $S['form_id'], 'type' => 'form')), array('Content-Type' => 'application/x-www-form-urlencoded'), false);
	$did = (int) ($dr['json']['data']['form_id'] ?? 0);
	$made[] = $did;
	$dp = $H->ctl('f15-pkg-export', (string) $did, '0', 'duplicate');
	t('Duplicate (dup_efb): the copy has the same SMS texts and the admin numbers (the block never left the site)', $did > 0 && ($dp['extras']['sms']['data']['new_noti'] ?? '') === 'New entry [confirmation_code]' && ($dp['extras']['sms']['data']['admin_numbers'] ?? '') === '+15550001', $dp['extras'] ?? $dr);
	t('Duplicate keeps a version "duplicate"', (($H->ctl('f15-revs', (string) $did)['revs'][0]['source']) ?? '') === 'duplicate');
} else {
	t('extras: the SMS add-on is not usable on this site (no sms block)', !isset($sp['extras']['sms']), $sp['extras']);
}
$x = $sp; $x['extras']['portal'] = array('v' => 1, 'addon' => 'AdnPRT', 'data' => array('enabled' => 1));
$xr = $imp($x);
$made[] = (int) ($xr['form_id'] ?? 0);
t('a block of an add-on that is not active here is dropped with a notice (extras_addon_inactive)', in_array('extras_addon_inactive', array_column((array) ($xr['notices'] ?? array()), 'code'), true), $xr['notices'] ?? $xr);

foreach ($made as $id) if ($id > 0) $H->ctl('f12-delete-form', (string) $id);
$H->ctl('addon-flag', 'AdnSS', (is_array($prevSms) ? (string) reset($prevSms) : (string) $prevSms) === '1' ? '1' : '0');
@unlink($tmp);
echo "\nRESULTS: {$passed} passed, {$failed} failed\n";
exit($failed ? 1 : 0);
