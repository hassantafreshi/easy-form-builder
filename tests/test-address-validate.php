<?php
/**
 * F17-C: the server's rule of each part (Emsfb\Address_Schemes::check_value / name_ok) on the shared cases of
 * tests/fixtures/address-part-cases.json (Persian, Arabic and mixed digits, every format's postal code, plaque, floor
 * words, the Saudi short address, names with ZWNJ / apostrophes / digits). tests/test-address-validate.js runs the same
 * cases with the page's rules (EFBNameAddr): the two must agree case by case.
 *
 * Run: php tests/test-address-validate.php
 */
define('ABSPATH', __DIR__ . '/');
require_once __DIR__ . '/../includes/class-Emsfb-address-schemes.php';
use Emsfb\Address_Schemes as S;

$pass = 0; $fail = 0;
function t($name, $ok, $info = '') { global $pass, $fail; if ($ok) { $pass++; echo "[PASS] {$name}\n"; } else { $fail++; echo "[FAIL] {$name}" . ($info !== '' ? ": {$info}" : '') . "\n"; } }

$fx = json_decode(file_get_contents(__DIR__ . '/fixtures/address-part-cases.json'), true);
t('the fixture has at least 60 cases', count($fx['cases']) >= 60, (string) count($fx['cases']));
foreach ($fx['cases'] as $c) {
	if ($c['k'] === 'name') {
		$v = trim(preg_replace('/\s+/u', ' ', $c['in']));
		$ok = $v === '' || S::name_ok($v);
		t('name "' . $c['in'] . '" ' . ($c['ok'] ? 'accepted' : 'refused') . ($c['note'] ? " ({$c['note']})" : ''), $ok === $c['ok']);
		continue;
	}
	$def = S::part('address', $c['set'], $c['part']);
	if (!$def) { t("{$c['set']}.{$c['part']} exists", false); continue; }
	$r = S::check_value($def, $c['in']);
	$label = "{$c['set']}.{$c['part']} \"{$c['in']}\" " . ($c['ok'] ? 'accepted' : 'refused') . (!empty($c['note']) ? " ({$c['note']})" : '');
	t($label, $r['ok'] === $c['ok'] && (!isset($c['out']) || $r['value'] === $c['out']), json_encode($r, JSON_UNESCAPED_UNICODE));
}
// Every rule compiles in PCRE (u) and every part has the keys the page and the builder need.
$bad = array();
foreach (S::schemes() as $k => $s) foreach ($s['parts'] as $p) {
	if (!empty($p['rx']) && @preg_match('/' . str_replace('/', '\/', $p['rx']) . '/u', '') === false) $bad[] = "$k.{$p['p']} rx";
	foreach (array('p', 't', 'el', 'lb', 'ac', 'r', 'sz') as $key) if (!array_key_exists($key, $p)) $bad[] = "$k.{$p['p']} $key";
	if (!in_array((int) $p['sz'], array(100, 92, 83, 75, 67, 58, 50, 42, 33, 25, 17, 8), true)) $bad[] = "$k.{$p['p']} size";
}
foreach (S::name_formats() as $k => $f) foreach ($f['parts'] as $p) if (!in_array($p['p'], S::NAME_PARTS, true)) $bad[] = "name $k {$p['p']}";
t('every rule compiles; every part has p, t, el, lb, ac, r and a width the builder knows', !$bad, implode(', ', $bad));
t('the formats of the brief are there (IR, SA, US, GB, DE, JP, AE, TR) and EG, CA, international', !array_diff(array('ir', 'sa', 'us', 'uk', 'de', 'jp', 'ae', 'tr', 'eg', 'ca', 'intl'), array_keys(S::schemes())));
t('six name formats', array_keys(S::name_formats()) === array('full', 'fl', 'pfl', 'fml', 'ar4', 'ar3'));

echo "\nRESULTS: {$pass} passed, {$fail} failed\n";
exit($fail ? 1 : 0);
