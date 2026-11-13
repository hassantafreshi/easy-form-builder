<?php
/**
 * F03-H: password confirmation, strength and the show-password button on the server.
 *  1. pw_score() on the shared vectors (tests/fixtures/pw-score-vectors.json, also read by the JS test).
 *  2. check(): the password as typed (raw, before sanitize_text_field) against password_confirms[id_]
 *     (hash_equals): a different or missing second value → mismatch (msg_mismatch or the default);
 *     a score under pw_strength_min → strength (msg_strength), only with the plan part; "<" and "%41"
 *     in a password are compared as typed.
 *  3. raw_passwords_efb() (extracted from class-Emsfb-public.php) keeps every password row as typed
 *     under 'f:<id_>'; the confirmation is never one of the rows (it is a request key).
 *  4. The renderer: autocomplete="new-password", the second box (its own label, never emsFormBuilder_v,
 *     so it is not an answer), the show-password button (aria-pressed, aria-controls, label), the meter
 *     (aria-live word); an old password field keeps current-password and its markup.
 * Run: php tests/test-field-rules-password.php
 */
$passed = 0;
$failed = 0;
function t($name, $actual, $expected) {
	global $passed, $failed;
	if ($actual === $expected) { echo "[PASS] {$name}\n"; $passed++; return; }
	echo "[FAIL] {$name}: expected " . var_export($expected, true) . ', got ' . var_export($actual, true) . "\n";
	$failed++;
}
$GLOBALS['plan'] = 'free';
function apply_filters($h, $v) { return $h === 'efb_f03_plan' ? $GLOBALS['plan'] : $v; }
function efb_plan_at_least_efb($n) { return false; }
require __DIR__ . '/field-rules-render-lib.php';
use Emsfb\Field_Rules as FR;

/* 1 */
foreach (json_decode(file_get_contents(__DIR__ . '/fixtures/pw-score-vectors.json'), true) as $x) {
	t('1 ' . json_encode($x['v'], JSON_UNESCAPED_UNICODE), FR::pw_score($x['v'], array('emails' => $x['emails'], 'form_name' => $x['formName'])), $x['score']);
}

/* 2 */
$f = array('id_' => 'pw', 'type' => 'password', 'name' => 'Password', 'pw_confirm' => '1', 'pw_strength_min' => '2');
$chk = function ($f, $typed, $confirm, $sanitized = null) {
	$req = $confirm === null ? array() : array('password_confirms' => array('pw' => $confirm));
	return FR::check($f, array('id_' => 'pw', 'type' => 'password', 'value' => $sanitized === null ? $typed : $sanitized), $req, array('f:pw' => $typed), array(), array('form_id' => 1, 'form_name' => 'F', 'emails' => array()));
};
$r = $chk($f, 'Abcdefgh1!', 'Abcdefgh1?');
t('2 a different second value: mismatch, default sentence', array($r['ok'], $r['code'], $r['m']), array(false, 'mismatch', 'The two passwords do not match.'));
t('2 no second value at all: mismatch', $chk($f, 'Abcdefgh1!', null)['code'], 'mismatch');
t('2 msg_mismatch', $chk($f + array('msg_mismatch' => 'Type the same %s twice'), 'Abcdefgh1!', 'x')['m'], 'Type the same <b>Password</b> twice');
t('2 "<" and "%41" compared as typed (the stored value is sanitized, the check is not)', $chk($f, 'Ab<cd%41ef9!', 'Ab<cd%41ef9!', 'Abef9!')['ok'], true);
$r = $chk($f, 'abcdefgh', 'abcdefgh');
t('2 strength 1 under a minimum of 2: strength, default sentence', array($r['code'], $r['m']), array('strength', 'Please choose a stronger password.'));
t('2 msg_strength', $chk($f + array('msg_strength' => 'Longer, please'), 'abcdefgh', 'abcdefgh')['m'], 'Longer, please');
$GLOBALS['plan'] = 'free_plus';
t('2 without the plan: no strength rule (the confirmation still applies: it is free)', array($chk($f, 'abcdefgh', 'abcdefgh')['ok'], $chk($f, 'abcdefgh', 'x')['code']), array(true, 'mismatch'));
$GLOBALS['plan'] = 'free';
t('2 a password field without the keys: no rule', FR::active_rules(array('type' => 'password')), array());

/* 3 */
$src = file_get_contents(__DIR__ . '/../includes/class-Emsfb-public.php');
preg_match('/\tprivate function raw_passwords_efb\(.*?\n\t}\n/s', $src, $m);
eval('class FrRawFixture { ' . str_replace('private function', 'public function', $m[0]) . ' }');
$raw = (new FrRawFixture())->raw_passwords_efb(array(array('id_' => 'pw', 'type' => 'password', 'value' => ' a<b %41 '), array('id_' => 'n', 'type' => 'text', 'value' => 'x'), array('id_' => 'passwordlogin', 'type' => 'password', 'value' => 'L')));
t('3 raw passwords: every password row as typed, text rows not', $raw, array('f:pw' => ' a<b %41 ', 'passwordlogin' => 'L', 'f:passwordlogin' => 'L'));

/* 4 */
$h = efb_fr_render(efb_fr_field('p1', 'password', array('pw_confirm' => '1', 'pw_meter' => '1', 'pw_toggle' => '1', 'pw_confirm_label' => 'Again', 'required' => '1')), array('tvPwShow' => 'Show password', 'tvPwHide' => 'Hide password'));
t('4 autocomplete new-password on the field', (bool) preg_match('/<input type="password"[^>]*id="p1_"[^>]*autocomplete="new-password"/', $h), true);
t('4 the second box: own label, new-password, required, not an answer (no emsFormBuilder_v, no name)', (bool) preg_match('#<label for="p1_confirm"[^>]*>Again</label><input type="password" id="p1_confirm" class="(?![^"]*emsFormBuilder_v)[^"]*efb-pw-confirm[^"]*" data-for="p1" autocomplete="new-password" aria-describedby="p1_confirm-message" required#', $h) && !preg_match('/id="p1_confirm"[^>]*\bname=/', $h), true);
t('4 the show-password button: aria-pressed, aria-controls, the label of the action', (bool) preg_match('#<button type="button" class="efb btn efb-pw-toggle input-group-text" aria-pressed="false" aria-controls="p1_" aria-label="Show password" data-show="Show password" data-hide="Hide password">#', $h), true);
t('4 the meter: a bar (hidden) and a word (aria-live), in aria-describedby', strpos($h, '<div class="efb efb-pw-bar" aria-hidden="true">') !== false && strpos($h, 'id="p1_-strength" aria-live="polite"') !== false && (bool) preg_match('/aria-describedby="[^"]*p1_-strength/', $h), true);
$old = efb_fr_render(efb_fr_field('p2', 'password'));
t('4 an old password field: current-password, no F03 markup', strpos($old, 'autocomplete="current-password"') !== false && strpos($old, 'efb-pw-') === false, true);

echo "\nRESULTS: {$passed} passed, {$failed} failed\n";
exit($failed ? 1 : 0);
