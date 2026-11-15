<?php
/**
 * SPLIT §7 (subtask D): the typed signature without the Typed Signature add-on (AdnTSG absent, off, too old, above the
 * plan).
 *  - an e-signature with esign_mode "type" / "both" is the drawn signature, byte for byte what a draw-only field prints
 *    (the visitor still signs; the required rule is unchanged);
 *  - a forged "type" row (sig_mode / sig_text / sig_font) is stored without those keys;
 *  - stored typed signatures still display their name (Layout::sig_text_of(): e-mail, viewer, CSV, review);
 *  - core ships no fonts and no EFBSign; Layout::json_needs_runtime() no longer asks for its script for a typed field;
 *  - with the add-on the tabs and the name box come back and its row rule keeps the typed name.
 *
 * Run: php tests/test-split-esign-fallback.php
 */
$passed = 0;
$failed = 0;
function t($name, $actual, $expected) {
	global $passed, $failed;
	if ($actual === $expected) { echo "[PASS] {$name}\n"; $passed++; return; }
	echo "[FAIL] {$name}: expected " . var_export($expected, true) . ', got ' . var_export($actual, true) . "\n";
	$failed++;
}
if (!function_exists('get_locale')) { function get_locale() { return 'en_US'; } }
require __DIR__ . '/field-rules-render-lib.php';
require_once __DIR__ . '/../includes/class-Emsfb-layout.php';

function efb_sf_render(array $field) {
	$all = array((object) array('type' => 'form', 'steps' => 1, 'formName' => 'T'), (object) array('type' => 'step', 'id_' => '1', 'step' => '1', 'name' => 'S'), (object) $field);
	$fb = new \Emsfb\Formbuilder($all, true);
	$r = $fb->addNewElement_efb(2, $field['id_'], 7, array('sigDraw' => 'Draw', 'sigType' => 'Type', 'sigTypeLbl' => 'Type your full name'), true);
	return is_array($r) ? (string) $r[0] : (string) $r;
}
$base = efb_fr_field('s1', 'esign', array('required' => 1));
$draw = efb_sf_render($base);
$both = efb_sf_render(array_merge($base, array('esign_mode' => 'both', 'esign_font' => 'latin')));
$type = efb_sf_render(array_merge($base, array('esign_mode' => 'type')));
// The inline script hands the field's own row to the page (it carries esign_mode itself); everything else is compared.
$nojson = function ($h) { return preg_replace('/(fun_event_esign_efb\([^{]*)\{.*?\}\);/s', '$1{ROW});', $h); };
t('without the add-on: "both" renders byte for byte as a draw-only signature (but the row it hands the page)', $nojson($both), $nojson($draw));
t('without the add-on: "type" renders byte for byte as a draw-only signature (but the row it hands the page)', $nojson($type), $nojson($draw));
t('…no tabs, no name box, no data-sig-mode', array(strpos($both, 'efb-sig-tabs'), strpos($both, '_sigtype'), strpos($both, 'data-sig-mode')), array(false, false, false));
t('…the canvas and its required mark are there', array(strpos($both, "<canvas class='efb sign-efb") !== false, strpos($both, 'id="s1_req"') !== false || strpos($both, "s1_req") !== false), array(true, true));

// The row rule (class-Emsfb-public.php esign_row_efb()), lifted.
$src = file_get_contents(__DIR__ . '/../includes/class-Emsfb-public.php');
preg_match('/\tpublic function esign_row_efb\(.*?\n\t}\n/s', $src, $m);
eval('class SplitEsignFixture { ' . $m[0] . ' }');
$fx = new SplitEsignFixture();
$forged = array('id_' => 's1', 'type' => 'esign', 'value' => 'data:image/png;base64,AAAA', 'sig_mode' => 'type', 'sig_text' => 'Jane', 'sig_font' => 'latin');
t('without the add-on: a forged typed row is stored without sig_mode / sig_text / sig_font', $fx->esign_row_efb($forged, array('type' => 'esign', 'esign_mode' => 'both')), array('id_' => 's1', 'type' => 'esign', 'value' => 'data:image/png;base64,AAAA'));
t('stored typed signatures keep their display (Layout::sig_text_of)', array(\Emsfb\Layout::sig_text_of(array('sig_mode' => 'type', 'sig_text' => 'Jane Doe')), \Emsfb\Layout::sig_text_of(array('value' => 'png'))), array('Jane Doe', ''));
t('core: no fonts, no EFBSign, no typed parts code', array(is_dir(__DIR__ . '/../public/assets/fonts'), strpos(file_get_contents(__DIR__ . '/../public/assets/js/layout-efb.js'), 'root.EFBSign'), method_exists('\Emsfb\Layout', 'sig_attrs'), strpos(file_get_contents(__DIR__ . '/../includes/class-Emsfb-formbuilder.php'), 'efb-sig-tabs')), array(false, false, false, false));
t('core: a form whose only F10 part is a typed signature no longer loads layout-efb.js for it', \Emsfb\Layout::json_needs_runtime('[{"type":"form"},{"id_":"s1","type":"esign","esign_mode":"both"}]'), false);

// With the add-on (vendor/typed-signature or EFB_TYPED_SIGNATURE_DIR).
$dir = getenv('EFB_TYPED_SIGNATURE_DIR') ?: __DIR__ . '/../vendor/typed-signature';
require_once $dir . '/class-Emsfb-typed-signature.php';
$GLOBALS['efb_tsg_filter'] = true;
$p = \Emsfb\Typed_Signature_Efb::parts(array('', '', ''), (object) array_merge($base, array('esign_mode' => 'both', 'esign_font' => 'latin')), array('texts' => array('sigDraw' => 'Draw', 'sigType' => 'Type', 'sigTypeLbl' => 'Type your full name')));
t('with the add-on: tabs (role tablist), data-sig-mode both, the name box', array(strpos($p[0], "role='tablist'") !== false, strpos($p[1], "data-sig-mode='both'") !== false, strpos($p[2], "id='s1_sigtype'") !== false), array(true, true, true));
$kept = \Emsfb\Typed_Signature_Efb::row(null, $forged, array('type' => 'esign', 'esign_mode' => 'both', 'esign_font' => 'latin'));
t('with the add-on: the typed row keeps its name and font', array($kept['sig_mode'], $kept['sig_text'], $kept['sig_font']), array('type', 'Jane', 'latin'));
$drawonly = \Emsfb\Typed_Signature_Efb::row(null, $forged, array('type' => 'esign'));
t('with the add-on: a forged "type" on a draw-only field still loses the keys', array_key_exists('sig_mode', $drawonly), false);
t('the add-on finds a typed signature in the stored JSON (escaped quotes too)', array(\Emsfb\Typed_Signature_Efb::json_has_typed_sig('{\"esign_mode\":\"type\"}'), \Emsfb\Typed_Signature_Efb::json_has_typed_sig('{"esign_mode":"draw"}')), array(true, false));
t('the add-on ships the four fonts and their licences', count(glob($dir . '/assets/fonts/*.woff2')) === 4 && is_file($dir . '/assets/fonts/OFL-dancing-script.txt') && is_file($dir . '/assets/fonts/OFL-aref-ruqaa.txt'), true);

echo "\nRESULTS: {$passed} passed, {$failed} failed\n";
exit($failed ? 1 : 0);
