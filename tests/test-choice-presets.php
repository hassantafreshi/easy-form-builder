<?php
/**
 * F05-A: the ready-made lists (includes/data/choice-presets.php): the counts of spec §4.1, a stored value
 * for every item, unique stored values and labels inside a list, no " < > in any text, ISO / USPS shapes
 * where the list says so, every list name a phrase of text_efb(); the "Add in bulk" lines a list becomes
 * ("\|" for a "|"), and the AJAX that gives them refuses a request without a nonce or the permission.
 *
 * Run: php tests/test-choice-presets.php
 */
define('ABSPATH', __DIR__ . '/');
$pass = 0; $fail = 0;
function t($label, $actual, $expected) {
	global $pass, $fail;
	if ($actual === $expected) { $pass++; echo "[PASS] $label\n"; return; }
	$fail++; echo "[FAIL] $label\n  expected " . json_encode($expected, JSON_UNESCAPED_UNICODE) . "\n  actual   " . json_encode($actual, JSON_UNESCAPED_UNICODE) . "\n";
}
function sanitize_key($k) { return preg_replace('/[^a-z0-9_\-]/', '', strtolower((string) $k)); }
function wp_unslash($v) { return $v; }
$GLOBALS['ajax'] = array('nonce' => false, 'perm' => false, 'out' => null);
function check_ajax_referer($a, $q, $die) { return $GLOBALS['ajax']['nonce']; }
class FakeFn { public function user_permission_efb_admin_dashboard() { return $GLOBALS['ajax']['perm']; } }
function get_efbFunction() { return new FakeFn(); }
class AjaxDone extends Exception {}
function wp_send_json_error($d, $code = 200) { $GLOBALS['ajax']['out'] = array('ok' => false, 'code' => $code, 'data' => $d); throw new AjaxDone(); }
function wp_send_json_success($d, $code = 200) { $GLOBALS['ajax']['out'] = array('ok' => true, 'code' => $code, 'data' => $d); throw new AjaxDone(); }
require __DIR__ . '/../includes/class-Emsfb-choices.php';

$p = \Emsfb\Choices::presets();
$want = array('ir_provinces' => 31, 'af_provinces' => 34, 'fa_months' => 12, 'fa_weekdays' => 7, 'fa_education' => 7, 'fa_marital' => 4, 'fa_military' => 5, 'fa_satisfaction' => 5, 'fa_likert' => 5, 'fa_age' => 6,
	'arab_states' => 22, 'sa_regions' => 13, 'ae_emirates' => 7, 'eg_governorates' => 27, 'gcc' => 6, 'hijri_months' => 12, 'ar_months' => 12, 'ar_weekdays' => 7, 'ar_satisfaction' => 5,
	'us_states' => 51, 'ca_provinces' => 13, 'uk_nations' => 4, 'au_states' => 8, 'en_months' => 12, 'en_weekdays' => 7, 'en_age' => 6, 'en_satisfaction' => 5, 'en_agreement' => 5, 'en_importance' => 5, 'en_likelihood' => 5, 'en_yes_no' => 3, 'en_education' => 6);
foreach ($want as $id => $n) t("$id has $n items", isset($p[$id]) ? count($p[$id]['items']) : -1, $n);
$functions = file_get_contents(__DIR__ . '/../includes/functions.php');
$shapes = array('ir_provinces' => '/^IR-(0\d|[12]\d|30)$/', 'af_provinces' => '/^AF-[A-Z]{3}$/', 'arab_states' => '/^[A-Z]{2}$/', 'gcc' => '/^[A-Z]{2}$/', 'sa_regions' => '/^SA-\d\d$/', 'ae_emirates' => '/^AE-[A-Z]{2}$/', 'eg_governorates' => '/^EG-[A-Z]{1,3}$/', 'us_states' => '/^[A-Z]{2}$/', 'ca_provinces' => '/^[A-Z]{2}$/', 'uk_nations' => '/^GB-[A-Z]{3}$/', 'au_states' => '/^AU-[A-Z]{2,3}$/');
foreach ($p as $id => $l) {
	$vals = array_map(function ($i) { return $i[1]; }, $l['items']);
	$labs = array_map(function ($i) { return $i[0]; }, $l['items']);
	t("$id: a stored value for every item, unique", count(array_unique(array_filter($vals, 'strlen'))), count($vals));
	t("$id: labels unique", count(array_unique($labs)), count($labs));
	t("$id: no \" < > in any text", preg_match('/["<>]/', implode('', array_merge($vals, $labs))), 0);
	t("$id: language fa / ar / en", in_array($l['lang'], array('fa', 'ar', 'en'), true), true);
	t("$id: its name is a phrase ({$l['name_key']})", strpos($functions, '"' . $l['name_key'] . '" =>') !== false, true);
	if (isset($shapes[$id])) t("$id: stored values have the code shape", count(array_filter($vals, function ($v) use ($shapes, $id) { return preg_match($shapes[$id], $v) === 1; })), count($vals));
	t("$id: stored values ≤ 200 and labels ≤ 200 (the save limit)", max(array_map('mb_strlen', array_merge($vals, $labs))) <= 200, true);
}
t('Tehran is IR-23 and Alborz IR-30 (ISO 3166-2:IR)', array_column($p['ir_provinces']['items'], 1, 0)['تهران'] . '|' . array_column($p['ir_provinces']['items'], 1, 0)['البرز'], 'IR-23|IR-30');
t('the Arab League list has Palestine (PS) and Syria (SY)', count(array_intersect(array('PS', 'SY'), array_column($p['arab_states']['items'], 1))), 2);
t('no world country list (the Country field exists)', count(array_filter(array_keys($p), function ($k) { return strpos($k, 'countr') !== false; })), 0);
$idx = \Emsfb\Choices::preset_index();
t('the index has names and counts only (no items)', array(isset($idx['ir_provinces']['items']), $idx['ir_provinces']['count'], $idx['ir_provinces']['lang']), array(false, 31, 'fa'));
$lines = explode("\n", \Emsfb\Choices::preset_lines('ir_provinces'));
t('a list becomes 31 "Label|value" lines', array(count($lines), $lines[0]), array(31, 'آذربایجان شرقی|IR-03'));
t('unknown list: null', \Emsfb\Choices::preset_lines('nope'), null);

/* AJAX */
$call = function ($nonce, $perm, $preset) {
	$GLOBALS['ajax'] = array('nonce' => $nonce, 'perm' => $perm, 'out' => null);
	$_POST['preset'] = $preset;
	try { \Emsfb\Choices::ajax_presets(); } catch (AjaxDone $e) {}
	return $GLOBALS['ajax']['out'];
};
$r = $call(false, true, 'ir_provinces');
t('AJAX without a nonce: 403', array($r['ok'], $r['code']), array(false, 403));
$r = $call(true, false, 'ir_provinces');
t('AJAX without the permission: 403', array($r['ok'], $r['code']), array(false, 403));
$r = $call(true, true, 'us_states');
t('AJAX with both: the lines of the list', array($r['ok'], $r['data']['count'], $r['data']['lang'], strpos($r['data']['lines'], 'California|CA') !== false), array(true, 51, 'en', true));
$r = $call(true, true, '../etc');
t('AJAX: an unknown list is 404', array($r['ok'], $r['code']), array(false, 404));

echo "\nRESULTS: $pass passed, $fail failed\n";
exit($fail ? 1 : 0);
