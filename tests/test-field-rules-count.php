<?php
/**
 * F03-B: characters, words and the counter on the server.
 *
 *  1. Field_Rules::count_chars / count_words on tests/fixtures/count-vectors.json (shared with the JS test).
 *  2. check(): word limits with the owner's msg_words or the default (min / max sentence), masked
 *     fields have no word or length rule, an empty value runs none.
 *  3. The real default: branch of get_form_public_efb() (extracted): a textarea newline ("@n#") is one
 *     character (§18.3) — 3 newlines and mlen 10 pass at 10 characters and fail at 11, as in the browser;
 *     a field with F03 rules uses the browser's sentence (tvMinLen_) and sets the code.
 *  4. The PHP renderer: textarea maxlength; the counter box (aria-hidden), the hidden "Up to N"
 *     hint referenced by aria-describedby next to the description; a field without the keys renders
 *     no F03 markup and keeps its aria-describedby.
 *  5. Sanitize: wmin / wmax (Persian digits, 0 = none, ≤ 100000), counter, counter_style.
 *
 * Run: php tests/test-field-rules-count.php
 */
$passed = 0;
$failed = 0;
function t($name, $actual, $expected) {
	global $passed, $failed;
	if ($actual === $expected) { echo "[PASS] {$name}\n"; $passed++; return; }
	echo "[FAIL] {$name}: expected " . var_export($expected, true) . ', got ' . var_export($actual, true) . "\n";
	$failed++;
}
function sanitize_text_field($v) {
	if (is_array($v) || is_object($v)) return '';
	$s = strip_tags((string) $v);
	$s = preg_replace('/%[a-f0-9]{2}/i', '', $s);
	$s = preg_replace('/[\r\n\t ]+/', ' ', $s);
	return trim($s);
}
require __DIR__ . '/field-rules-render-lib.php';
use Emsfb\Field_Rules as FR;

/* 1 */
foreach (json_decode(file_get_contents(__DIR__ . '/fixtures/count-vectors.json'), true) as $x) {
	t('1 ' . $x['note'], array(FR::count_chars($x['v']), FR::count_words($x['v'])), array($x['chars'], $x['words']));
}

/* 2 */
$w = array('id_' => 'w', 'type' => 'text', 'name' => 'Bio', 'wmin' => '2', 'wmax' => '4');
$chk = function ($f, $v) { return FR::check($f, array('id_' => $f['id_'], 'type' => $f['type'], 'value' => $v), array(), array(), array(), array()); };
$r = $chk($w, 'one');
t('2 too few words: code words, default minimum sentence', array($r['ok'], $r['code'], $r['m']), array(false, 'words', '<b>Bio</b> needs at least <b>2</b> words.'));
$r = $chk($w, 'a b c d e');
t('2 too many words: maximum sentence', $r['m'], '<b>Bio</b> can have at most <b>4</b> words.');
$r = $chk($w + array('msg_words' => 'Two to four words for %s'), 'a b c d e');
t('2 msg_words wins', $r['m'], 'Two to four words for <b>Bio</b>');
t('2 "می‌خواهم بروم" passes 2–4', $chk($w, 'می‌خواهم بروم')['ok'], true);
t('2 masked field: no word rule', FR::active_rules($w + array('mask_preset' => 'ir.mobile')), array());
t('2 empty value: no rule', $chk($w, '')['ok'], true);
t('2 a number field has no word rule', FR::active_rules(array('type' => 'number', 'wmax' => '1')), array());

/* 3 the real default branch */
$src = file_get_contents(__DIR__ . '/../includes/class-Emsfb-public.php');
$marker = strpos($src, '// Mask gate:');
$start = strrpos(substr($src, 0, $marker), 'default:');
$end = strpos($src, 'break;', strpos($src, '$still_processing = false;', strpos($src, '// End mask gate.', $marker))) + strlen('break;');
$branch = substr($src, $start, $end - $start);
$methods = '';
foreach (array('filter_attributes_by_type_efb', 'sanitize_value_efb', 'filter_and_sanitize_attributes_efb') as $mname) {
	preg_match('/\tprivate function ' . $mname . '\(.*?\n\t}\n/s', $src, $mm);
	$methods .= $mm[0];
}
eval('class FrDefaultFixture { public $lanText = array("ptrnMmm_" => \'MIN[%1$s][%2$s]\', "ptrnMmx_" => \'MAX[%1$s][%2$s]\'); public $field_rules_code_efb = "";' . $methods . '
	public function run($f, $item) {
		$is_valid = null; $error_message = "provisional"; $error_field_id = ""; $validated_item = null; $still_processing = true; $this->field_rules_code_efb = "";
		switch ($f["type"]) {' . $branch . '
		}
		return array($is_valid, $error_message, $this->field_rules_code_efb);
	}
}');
$fx = new FrDefaultFixture();
$ta = array('id_' => 'ta', 'type' => 'textarea', 'name' => 'Notes', 'mlen' => '10');
$row = function ($v) { return array('id_' => 'ta', 'name' => 'Notes', 'id_ob' => 'ta_', 'amount' => '2', 'type' => 'textarea', 'value' => $v, 'session' => 's', 'form_id' => '7'); };
t('3 textarea, 3 newlines, 10 characters, mlen 10: accepted (the old count was 16)', $fx->run($ta, $row('ab@n#cd@n#ef@n#g'))[0], 1);
t('3 one more character: refused with the old sentence (no F03 key)', $fx->run($ta, $row('ab@n#cd@n#ef@n#gh')), array(0, 'MAX[<b>Notes</b>][<b>10</b>]', ''));
t('3 a field with F03 rules: the browser\'s sentence and the code', $fx->run($ta + array('wmax' => '50'), $row('ab@n#cd@n#ef@n#gh')), array(0, '<b>Notes</b> can have at most <b>10</b> characters.', 'maxlen'));
t('3 minimum with msg_minlen', $fx->run(array('id_' => 'ta', 'type' => 'textarea', 'name' => 'Notes', 'milen' => '5', 'msg_minlen' => 'More, %s'), $row('a@n#b')), array(0, 'More, <b>Notes</b>', 'minlen'));

/* 4 renderer */
$h = efb_fr_render(efb_fr_field('ta1', 'textarea', array('mlen' => '100', 'counter' => 'chars', 'message' => 'Desc')));
t('4 textarea: maxlength from mlen', strpos($h, 'maxlength="100"') !== false, true);
t('4 textarea: counter box (aria-hidden) before the description, hint referenced', (bool) preg_match('#<div class="efb efb-counter-wrap"><div id="ta1_-counter" class="efb efb-counter fs-7" aria-hidden="true" data-counter="chars" data-style="remaining" data-max="100"></div><div id="ta1_-limit" class="efb efb-vh">Up to 100 characters</div></div><div class="efb efb-field-description">#', $h) && strpos($h, 'aria-describedby="ta1-des ta1_-limit"') !== false, true);
$h = efb_fr_render(efb_fr_field('tx1', 'text', array('counter' => 'words', 'counter_style' => 'used', 'wmax' => '5')), array('tvLimitWordsHint' => 'تا %s کلمه'));
t('4 text: words counter, translated hint, aria-describedby made when there was none', strpos($h, 'data-counter="words" data-style="used" data-max="5"') !== false && strpos($h, '<div id="tx1_-limit" class="efb efb-vh">تا 5 کلمه</div>') !== false && strpos($h, 'aria-describedby="tx1_-limit"') !== false, true);
$h = efb_fr_render(efb_fr_field('tx2', 'text', array('counter' => 'chars')));
t('4 counter without a maximum: no hint', strpos($h, 'tx2_-counter') !== false && strpos($h, 'tx2_-limit') === false, true);
$old = efb_fr_render(efb_fr_field('ol1', 'text', array('milen' => '2', 'mlen' => '30', 'message' => 'Desc')));
t('4 a field without the keys: no F03 markup, aria-describedby as before', strpos($old, 'efb-counter') === false && strpos($old, 'efb-vh') === false && strpos($old, 'aria-describedby="ol1-des"') !== false, true);
$oldta = efb_fr_render(efb_fr_field('ol2', 'textarea', array('milen' => '3')));
t('4 an old textarea without mlen: no maxlength', strpos($oldta, 'maxlength') === false && strpos($oldta, 'minlength="3"') !== false, true);

/* 5 */
foreach (array(array('wmin', '۱۲', '12'), array('wmax', '0', ''), array('wmax', '250000', '100000'), array('wmax', '-2', ''), array('wmin', array(1), ''), array('counter', 'words', 'words'), array('counter', 'x', 'none'), array('counter_style', 'used', 'used'), array('counter_style', 'USED', 'remaining')) as $c) {
	t('5 sanitize ' . $c[0] . ' ' . json_encode($c[1], JSON_UNESCAPED_UNICODE), FR::sanitize_key($c[0], $c[1]), $c[2]);
}

echo "\nRESULTS: {$passed} passed, {$failed} failed\n";
exit($failed ? 1 : 0);
