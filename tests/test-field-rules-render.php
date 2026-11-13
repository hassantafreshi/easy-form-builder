<?php
/**
 * F03-F / G: the PHP renderer's field-rule markup (Emsfb\Formbuilder::addNewElement_efb).
 *
 *  F1 read-only: a text input and a textarea carry readonly and data-efb-readonly (focusable, not
 *     disabled, so a screen reader says "read only"); a disabled field keeps its old markup; a
 *     select is not touched.
 *  0  A field without the keys renders byte for byte as before (compared with the markup of the same
 *     field made without the F03 class loaded is not possible here, so: no F03 attribute, class or id).
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

echo "\nRESULTS: {$passed} passed, {$failed} failed\n";
exit($failed ? 1 : 0);
