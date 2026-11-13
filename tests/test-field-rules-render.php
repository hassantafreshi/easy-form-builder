<?php
/**
 * F03-F / G: the PHP renderer's field-rule markup (Emsfb\Formbuilder::addNewElement_efb).
 *
 *  F1 read-only: a text input and a textarea carry readonly and data-efb-readonly (focusable, not
 *     disabled, so a screen reader says "read only"); a disabled field keeps its old markup; a
 *     select is not touched.
 *  G1 prefix / suffix: an input group (block), the tooltip <small> its first child, spans with ids
 *     -pfx / -sfx in aria-describedby with -des; a masked (LTR) input keeps its group LTR (+98 on the
 *     left in an RTL form); plain text only (escaped, ≤ 24); not on a textarea.
 *  G2 the description: above (first in the -f div; the tooltip and the input then sit in a block of
 *     their own, as wpautop needs), focus (efb-help-focus), tooltip (a help button after the input:
 *     aria-expanded, aria-controls, aria-label "Help: label"; efb-help-pop); still in aria-describedby;
 *     below = today's markup.
 *  G3 wpautop (WordPress' own, from EFB_E2E_SITE_DIR): no <p> or <br> more than the same field
 *     without the keys ([SKIP] without the site).
 *  G4 textarea: the placeholder is escaped (§18.4).
 *  0  A field without the keys: no F03 attribute, class or id.
 *
 * Run: php tests/test-field-rules-render.php
 */
$passed = 0;
$failed = 0;
function t($name, $actual, $expected) {
	global $passed, $failed;
	if ($actual === $expected) { echo "[PASS] {$name}\n"; $passed++; return; }
	echo "[FAIL] {$name}: expected " . var_export($expected, true) . ', got ' . var_export($actual, true) . "\n";
	$failed++;
}
require __DIR__ . '/field-rules-render-lib.php';
// G3: WordPress' own wpautop (from the e2e site), run in a child process so its formatting.php does not meet the stubs.
$site = getenv('EFB_E2E_SITE_DIR');
$have_wp_fmt = $site && is_readable($site . '/wp-includes/formatting.php');

/* 0 */
$old = '';
foreach (array('text', 'email', 'textarea', 'password', 'tel', 'url', 'number') as $i => $type) $old .= efb_fr_render(efb_fr_field('o' . $i, $type, array('message' => 'Help', 'milen' => '2')));
t('0 fields without the keys: no F03 markup', (bool) preg_match('/efb-counter|efb-vh|data-efb-readonly|efb-help|-pfx|-sfx|efb-pw-/', $old), false);

/* F1 */
$h = efb_fr_render(efb_fr_field('r1', 'text', array('readonly' => '1', 'value' => 'Gold')));
t('F1 text: readonly and data-efb-readonly, the value, not disabled', (bool) (preg_match('/<input [^>]*id="r1_"[^>]*\breadonly data-efb-readonly="1"/', $h) && strpos($h, 'value="Gold"') !== false && !preg_match('/<input [^>]*id="r1_"[^>]*\bdisabled\b/', $h)), true);
$h = efb_fr_render(efb_fr_field('r2', 'textarea', array('readonly' => '1', 'value' => 'a@n#b')));
t('F1 textarea: readonly', (bool) preg_match('/<textarea [^>]*id="r2_"[^>]*\breadonly data-efb-readonly="1"/', $h), true);
$h = efb_fr_render(efb_fr_field('r3', 'text', array('readonly' => '1', 'disabled' => '1')));
t('F1 read-only + disabled: the disabled markup only', strpos($h, 'data-efb-readonly') === false && (bool) preg_match('/<input [^>]*id="r3_"[^>]*readonly/', $h), true);

/* G1 */
$h = efb_fr_render(efb_fr_field('a1', 'text', array('prefix' => '+98', 'suffix' => '<b>kg</b>', 'message' => 'Desc')));
t('G1 input group with the tooltip first, prefix and suffix spans', (bool) preg_match('#<div class="efb input-group efb-affix flex-nowrap"><small id="a1_-message"[^>]*>.*?</small><span class="efb input-group-text efb-affix-text" id="a1_-pfx">\+98</span> <input [^>]*> <span class="efb input-group-text efb-affix-text" id="a1_-sfx">kg</span></div><div class="efb efb-field-description">#s', $h), true);
t('G1 aria-describedby: description, prefix, suffix', strpos($h, 'aria-describedby="a1-des a1_-pfx a1_-sfx"') !== false, true);
$h = efb_fr_render(efb_fr_field('a2', 'tel', array('prefix' => '+98', 'mask_preset' => 'ir.mobile')));
t('G1 a masked (LTR) input: the group is LTR too', strpos($h, '<div class="efb input-group efb-affix flex-nowrap" dir="ltr">') !== false, true);
t('G1 at most 24 characters', strpos(efb_fr_render(efb_fr_field('a3', 'text', array('prefix' => str_repeat('x', 30)))), '>' . str_repeat('x', 24) . '</span>') !== false, true);
t('G1 not on a textarea', strpos(efb_fr_render(efb_fr_field('a4', 'textarea', array('prefix' => '$'))), 'efb-affix') === false, true);

/* G2 */
$h = efb_fr_render(efb_fr_field('h1', 'text', array('message' => 'Desc', 'message_place' => 'above')));
t('G2 above: the description right after the -f div, then the tooltip and the input in a block of their own', (bool) preg_match('#id="h1-f"><div class="efb efb-field-description"><small id="h1-des"[^>]*>Desc</small></div> <div class="efb efb-input-wrap"><small id="h1_-message"[^>]*>.*?</small> <input [^>]*> </div>#s', $h), true);
$h = efb_fr_render(efb_fr_field('h2', 'email', array('message' => 'Desc', 'message_place' => 'focus')));
t('G2 focus: efb-help-focus on the description, still described', strpos($h, '<div class="efb efb-field-description efb-help-focus" id="h2-desw">') !== false && strpos($h, 'aria-describedby="h2-des"') !== false, true);
$h = efb_fr_render(efb_fr_field('h3', 'text', array('message' => 'Desc', 'message_place' => 'tooltip', 'name' => 'Code')), array('tvHelpBtn' => 'Help: %s'));
t('G2 tooltip: a help button (aria-expanded, aria-controls, aria-label) after the input (a block piece; CSS puts it by the label), then the description efb-help-pop', (bool) preg_match('#<input [^>]*id="h3_"[^>]*> <div class="efb efb-help-wrap"><button type="button" class="efb efb-help-btn" aria-expanded="false" aria-controls="h3-desw" aria-label="Help: Code"><span aria-hidden="true">\?</span></button></div><div class="efb efb-field-description efb-help-pop" id="h3-desw">#', $h), true);
$below = efb_fr_render(efb_fr_field('h4', 'text', array('message' => 'Desc', 'message_place' => 'below')));
$plain = efb_fr_render(efb_fr_field('h4', 'text', array('message' => 'Desc')));
t('G2 below = the markup of a field without the key', $below, $plain);
t('G2 no description: no help button', strpos(efb_fr_render(efb_fr_field('h5', 'text', array('message_place' => 'tooltip'))), 'efb-help-btn') === false, true);
$h = efb_fr_render(efb_fr_field('h6', 'textarea', array('message' => 'Desc', 'message_place' => 'tooltip')));
t('G2 textarea: help button too', strpos($h, 'aria-controls="h6-desw"') !== false, true);

/* G3 */
if (!$have_wp_fmt) {
	echo "[SKIP] G3 wpautop: EFB_E2E_SITE_DIR is not set\n";
} else {
	$count = function ($html) use ($site) {
		$code = 'define("ABSPATH", "/"); function apply_filters($h, $v) { return $v; } require ' . var_export($site . '/wp-includes/formatting.php', true) . '; $o = wpautop(stream_get_contents(STDIN)); echo substr_count($o, "<p") + substr_count($o, "<br");';
		$p = proc_open(array(PHP_BINARY, '-r', $code), array(array('pipe', 'r'), array('pipe', 'w'), array('pipe', 'w')), $pipes);
		fwrite($pipes[0], $html); fclose($pipes[0]);
		$o = stream_get_contents($pipes[1]); fclose($pipes[1]); fclose($pipes[2]); proc_close($p);
		return (int) trim($o);
	};
	foreach (array(
		array('text', array('prefix' => '+98', 'suffix' => 'kg', 'message' => 'Desc')),
		array('text', array('message' => 'Desc', 'message_place' => 'tooltip')),
		array('text', array('message' => 'Desc', 'message_place' => 'above')),
		array('email', array('message' => 'Desc', 'message_place' => 'focus', 'prefix' => '@')),
		array('textarea', array('message' => 'Desc', 'message_place' => 'tooltip', 'counter' => 'chars', 'mlen' => '50')),
		array('text', array('counter' => 'words', 'wmax' => '9', 'readonly' => '1', 'value' => 'x')),
	) as $i => $c) {
		$keys = array_diff_key($c[1], array('message' => 1, 'value' => 1));
		$with = efb_fr_render(efb_fr_field('w' . $i, $c[0], $c[1]));
		$without = efb_fr_render(efb_fr_field('w' . $i, $c[0], array_diff_key($c[1], $keys)));
		t('G3 wpautop adds no <p> or <br>: ' . $c[0] . ' ' . implode(',', array_keys($keys)), $count($with) <= $count($without), true);
	}
}

/* G4 */
$h = efb_fr_render(efb_fr_field('p1', 'textarea', array('placeholder' => 'Say "hi" <now>')));
t('G4 textarea placeholder escaped', strpos($h, 'placeholder="Say &quot;hi&quot; &lt;now&gt;"') !== false, true);

echo "\nRESULTS: {$passed} passed, {$failed} failed\n";
exit($failed ? 1 : 0);
