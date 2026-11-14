<?php
/**
 * F14-E: the field checks moved into _Public::validate_rows_efb() give what the submit stores (on the e2e site,
 * tests/f11-http-lib.php): the same rows of every common type go through forms/message/add (a real submit) and
 * through validate_rows_efb() (ctl f14-validate), and
 *  - the stored entry's rows equal the method's rows (sanitized values, the saved option's text, switch labels,
 *    numbers with their display text, dates, colours, multiselect, textarea new lines, the stored row type / name);
 *  - a refused value gives the same message and field_id both ways (an e-mail, a colour, a forged option id);
 *  - admin_edit (an administrator's edit) lets a disabled field hold a value, which a visitor's submit refuses.
 * The submit's own tests (test-submit-*, test-*-submit*, test-*-http) run unchanged against the same code.
 *
 * Run (under the e2e lock): source the e2e env; php tests/test-validate-rows-parity.php
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
$F = $H->ctl('f03-form', 'F14 parity', json_encode(array(
	array('key' => 'tx', 'type' => 'text', 'name' => 'Text'),
	array('key' => 'em', 'type' => 'email', 'name' => 'Email'),
	array('key' => 'ur', 'type' => 'url', 'name' => 'Site'),
	array('key' => 'ta', 'type' => 'textarea', 'name' => 'About'),
	array('key' => 'dt', 'type' => 'date', 'name' => 'Day'),
	array('key' => 'nu', 'type' => 'number', 'name' => 'Count'),
	array('key' => 'rg', 'type' => 'range', 'name' => 'Level', 'milen' => '0', 'mlen' => '10'),
	array('key' => 'co', 'type' => 'color', 'name' => 'Colour'),
	array('key' => 'sw', 'type' => 'switch', 'name' => 'Agree', 'on' => 'Yes please', 'off' => 'No thanks'),
	array('key' => 'ra', 'type' => 'rating', 'name' => 'Stars'),
	array('key' => 'rd', 'type' => 'radio', 'name' => 'Size'),
	array('key' => 'r1', 'type' => 'option', 'parent_key' => 'rd', 'value' => 'Small'),
	array('key' => 'r2', 'type' => 'option', 'parent_key' => 'rd', 'value' => 'Large "XL"'),
	array('key' => 'ck', 'type' => 'checkbox', 'name' => 'Pets'),
	array('key' => 'c1', 'type' => 'option', 'parent_key' => 'ck', 'value' => 'Cat'),
	array('key' => 'c2', 'type' => 'option', 'parent_key' => 'ck', 'value' => 'Bird'),
	array('key' => 'se', 'type' => 'select', 'name' => 'City'),
	array('key' => 's1', 'type' => 'option', 'parent_key' => 'se', 'value' => 'Shiraz'),
	array('key' => 'ms', 'type' => 'multiselect', 'name' => 'Langs'),
	array('key' => 'm1', 'type' => 'option', 'parent_key' => 'ms', 'value' => 'PHP'),
	array('key' => 'm2', 'type' => 'option', 'parent_key' => 'ms', 'value' => 'Go'),
	array('key' => 'dis', 'type' => 'text', 'name' => 'Locked', 'disabled' => 1),
)), '', '1');
$i = $F['ids'];
$fid = (int) $F['form_id'];
$rows = array(
	array('id_' => $i['tx'], 'name' => 'Text', 'type' => 'text', 'value' => ' Ali <b>Rezaei</b>  ', 'amount' => 2),
	array('id_' => $i['em'], 'name' => 'Email', 'type' => 'email', 'value' => 'Ali@Example.test', 'amount' => 3),
	array('id_' => $i['ur'], 'name' => 'Site', 'type' => 'url', 'value' => 'https://example.test/a b', 'amount' => 4),
	array('id_' => $i['ta'], 'name' => 'About', 'type' => 'textarea', 'value' => 'line one@n#line "two"', 'amount' => 5),
	array('id_' => $i['dt'], 'name' => 'Day', 'type' => 'date', 'value' => '2026-11-14', 'amount' => 6),
	array('id_' => $i['nu'], 'name' => 'Count', 'type' => 'number', 'value' => '12', 'amount' => 7),
	array('id_' => $i['rg'], 'name' => 'Level', 'type' => 'range', 'value' => '7', 'amount' => 8),
	array('id_' => $i['co'], 'name' => 'Colour', 'type' => 'color', 'value' => '#11aa22', 'amount' => 9),
	array('id_' => $i['sw'], 'name' => 'Agree', 'type' => 'switch', 'value' => '1', 'amount' => 10),
	array('id_' => $i['ra'], 'name' => 'Stars', 'type' => 'rating', 'value' => '4', 'amount' => 11),
	array('id_' => $i['rd'], 'id_ob' => $i['r2'], 'name' => 'Size', 'type' => 'radio', 'value' => 'whatever the browser says', 'amount' => 12),
	array('id_' => $i['ck'], 'id_ob' => $i['c1'], 'name' => 'Pets', 'type' => 'checkbox', 'value' => 'Cat', 'amount' => 13),
	array('id_' => $i['ck'], 'id_ob' => $i['c2'], 'name' => 'Pets', 'type' => 'checkbox', 'value' => 'Bird', 'amount' => 13),
	array('id_' => $i['se'], 'id_ob' => $i['s1'], 'name' => 'City', 'type' => 'select', 'value' => 'Shiraz', 'amount' => 14),
	array('id_' => $i['ms'], 'name' => 'Langs', 'type' => 'multiselect', 'value' => 'PHP@efb!Go@efb!Forged@efb!', 'amount' => 15),
);
$norm = function ($rr) {
	$out = array();
	foreach ((array) $rr as $r) {
		if (!is_array($r) || ($r['type'] ?? '') === 'w_link') continue;
		unset($r['session'], $r['form_id']);
		foreach ($r as $k => $v) if (is_string($v)) $r[$k] = str_replace('@efb@dq#', '"', $v);
		ksort($r);
		$out[] = $r;
	}
	usort($out, function ($a, $b) { return strcmp(json_encode($a), json_encode($b)); });
	return $out;
};
$sub = EfbF11Http::answer($H->submit($fid, $rows));
t('the submit stores the entry', !empty($sub['success']), true);
$e = $H->ctl('f14-entries', $fid);
$stored = $norm(end($e)['content']);
$m = $H->ctl('f14-validate', $fid, json_encode($rows));
t('validate_rows_efb() accepts the same rows', $m['ok'], true);
$mine = $norm($m['rows']);
t('the same number of rows', count($mine), count($stored));
t('the stored rows equal validate_rows_efb()\'s rows (every type, sanitized, saved option text, switch label, stored type / name)', json_encode($mine, JSON_UNESCAPED_UNICODE), json_encode($stored, JSON_UNESCAPED_UNICODE));
$byid = array(); foreach ($mine as $r) $byid[$r['id_']][] = $r;
t('…e.g. the radio keeps the saved option\'s text, not the browser\'s', $byid[$i['rd']][0]['value'], 'Large "XL"');
t('…the switch stores its "on" label', $byid[$i['sw']][0]['value'], 'Yes please');
t('…the multiselect keeps only saved choices', $byid[$i['ms']][0]['value'], 'PHP@efb!Go@efb!');
foreach (array(
	'an invalid e-mail' => array('id_' => $i['em'], 'name' => 'Email', 'type' => 'email', 'value' => 'not-an-email', 'amount' => 3),
	'a colour that is not #rrggbb' => array('id_' => $i['co'], 'name' => 'Colour', 'type' => 'color', 'value' => 'red;x', 'amount' => 9),
	'an option id of another field' => array('id_' => $i['rd'], 'id_ob' => $i['s1'], 'name' => 'Size', 'type' => 'radio', 'value' => 'Shiraz', 'amount' => 12),
) as $label => $bad) {
	$set = array($bad, array('id_' => $i['tx'], 'name' => 'Text', 'type' => 'text', 'value' => 'ok', 'amount' => 2));
	$s = EfbF11Http::answer($H->submit($fid, $set));
	$v = $H->ctl('f14-validate', $fid, json_encode($set));
	t("{$label}: refused both ways with the same message and field_id", array($v['ok'], $v['m'], $v['field_id']), array(false, (string) ($s['m'] ?? ''), (string) ($s['field_id'] ?? '')));
}
$lock = array(array('id_' => $i['dis'], 'name' => 'Locked', 'type' => 'text', 'value' => 'typed anyway', 'amount' => 20));
$s = EfbF11Http::answer($H->submit($fid, $lock));
$v = $H->ctl('f14-validate', $fid, json_encode($lock), '1');
t('a disabled field with a value: a visitor\'s submit refuses it; an administrator\'s edit (admin_edit) keeps it', array(empty($s['success']), $v['ok'], $v['rows'][0]['value'] ?? null), array(true, true, 'typed anyway'));

echo "\nRESULTS: {$passed} passed, {$failed} failed\n";
exit($failed ? 1 : 0);
