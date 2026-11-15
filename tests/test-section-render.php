<?php
/**
 * F10-B: the section in the PHP renderer (Emsfb\Formbuilder::addNewElement_efb) and its JS twin.
 *
 *  1  The markup: a block element first (wpautop), the header button (aria-expanded true, aria-controls = the
 *     region, Enter / Space by being a <button>), the empty region (role=region, aria-labelledby = the header),
 *     data-collapsed for the page, no input (no answer row); a section that is not collapsible is a plain heading;
 *     the title and description are escaped (markup and quotes); a bad icon / colour / size never reaches a class.
 *  2  Closing tags: a form with a heading, a section and a text field opens and closes the same number of <div>;
 *     the text field after the section stays a sibling in the step (the page moves it, not the server).
 *  3  The JS twin (efbLayoutSectionMarkup in layout-builder-efb.js) prints the same string for every row tried.
 *  4  Old forms: textarea, signature, heading, text, e-mail and select render byte for byte as on the commit before
 *     F10 (tests/fixtures/f10-old-form-render.json, made with that commit's renderer from f10-old-form-rows.json).
 *
 * Run: php tests/test-section-render.php
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
$TX = array('tfnapca' => 'Not available', 'thisFeatureAvailableFreePlusPro' => 'Free Plus', 'updateUrbrowser' => 'Update your browser', 'nothingSelected' => 'Nothing selected');

function efb_sec_render(array $rows, $i, $fid = 7) {
	global $TX;
	$all = array((object) array('type' => 'form', 'steps' => 1, 'formName' => 'T'), (object) array('type' => 'step', 'id_' => '1', 'step' => '1', 'name' => 'S'));
	foreach ($rows as $r) $all[] = json_decode(json_encode($r));
	$fb = new \Emsfb\Formbuilder($all, true);
	$r = $fb->addNewElement_efb($i, $all[$i]->id_, $fid, $TX, true);
	return is_array($r) ? (string) $r[0] : (string) $r;
}
$sec = function ($id, $extra = array()) { return array_merge(array('id_' => $id, 'dataId' => $id . '-id', 'type' => 'section', 'name' => 'Contact details', 'message' => '', 'step' => '1', 'amount' => '2', 'size' => '100', 'classes' => '', 'collapsible' => '1', 'collapsed' => '0', 'el_text_size' => 'fs-5', 'el_text_color' => 'text-labelEfb', 'label_align' => 'txt-left'), $extra); };

/* 1 */
$h = efb_sec_render(array($sec('s1', array('collapsed' => '1', 'message' => 'Optional <b>details</b>'))), 2);
t('1 the markup starts with a block element inside the field wrapper', (bool) preg_match('/^<!--startTag section--><div class="efb my-1 mx-0 section/', $h), true);
t('1 the header is a <button type=button> with aria-expanded="true" and aria-controls = the region', (bool) preg_match('/<button type="button" class="efb efb-section-btn text-labelEfb" id="s1_btn" aria-expanded="true" aria-controls="s1-region" data-section="s1">/', $h), true);
t('1 the region is empty, role=region, labelled by the header', strpos($h, '<div class="efb efb-section-region row mx-0 px-0" id="s1-region" role="region" aria-labelledby="s1-h"></div>') !== false, true);
t('1 collapsed is left to the page (data-collapsed="1"; open without JavaScript)', strpos($h, 'data-collapsible="1" data-collapsed="1"') !== false && strpos($h, ' hidden') === false, true);
t('1 no answer input (no emsFormBuilder_v)', strpos($h, 'emsFormBuilder_v'), false);
t('1 the description is escaped', strpos($h, 'Optional &lt;b&gt;details&lt;/b&gt;') !== false, true);
$h = efb_sec_render(array($sec('s2', array('collapsible' => '0', 'collapsed' => '1'))), 2);
t('1 not collapsible: a plain heading (no button), never collapsed', strpos($h, '<button') === false && strpos($h, 'data-collapsible="0" data-collapsed="0"') !== false && strpos($h, 'id="s2_lab"') !== false, true);
$h = efb_sec_render(array($sec('s3', array('name' => 'A "quoted" <img src=x onerror=alert(1)> title', 'icon' => 'bi-x" onclick="y', 'el_text_color' => 'x" onmouseover="y', 'el_text_size' => '"><svg>', 'classes' => 'a,b"><script>'))), 2);
t('1 a hostile title is text; a bad icon / colour / size / class never becomes markup', strpos($h, '<img') === false && strpos($h, 'onclick') === false && strpos($h, 'onmouseover') === false && strpos($h, '<svg') === false && strpos($h, '<script') === false && strpos($h, 'A &quot;quoted&quot; &lt;img') !== false, true);

/* 2 */
$rows = array(
	array('id_' => 'h1', 'dataId' => 'h1-id', 'type' => 'heading', 'value' => 'Welcome', 'step' => '1', 'amount' => '2', 'classes' => '', 'el_text_size' => 'fs-4', 'el_text_color' => 'text-dark'),
	$sec('s4'),
	efb_fr_field('t1', 'text', array('amount' => '4')),
);
$all = '';
foreach (array(2, 3, 4) as $i) $all .= efb_sec_render($rows, $i);
t('2 a heading, a section and a text field: as many <div> opened as closed', substr_count($all, '<div') - substr_count($all, '</div>'), 0);
t('2 the text field is not inside the section on the server (a sibling; the page moves it)', strpos($all, '<div class="efb efb-section-region row mx-0 px-0" id="s4-region" role="region" aria-labelledby="s4-h"></div></div><!--test--></div><!--endTag section-->') !== false, true);

/* 3 */
$vectors = array($sec('a1'), $sec('a2', array('collapsed' => '1', 'message' => 'Note & <i>x</i>', 'icon' => 'bi-person', 'label_align' => 'txt-right', 'classes' => 'my,extra')), $sec('a3', array('collapsible' => '0')), $sec('a4', array('collapsible' => '', 'collapsed' => '1', 'name' => "ب \"x\" 'y' <z>")), $sec('a5', array('el_text_size' => 'display-3', 'el_text_color' => 'bad color', 'icon' => 'nope')));
$php = array();
foreach ($vectors as $v) {
	$fb = new \Emsfb\Formbuilder(array((object) array('type' => 'form'), (object) array('type' => 'step', 'id_' => '1'), json_decode(json_encode($v))), true);
	$php[] = $fb->generate_section_efb(json_decode(json_encode($v)), 7);
}
$js = json_decode((string) shell_exec('node -e ' . escapeshellarg('global.window = global; global.document = { createElement: () => ({}) }; require(' . json_encode(__DIR__ . '/../includes/admin/assets/js/layout-builder-efb.js') . '); const v = JSON.parse(process.argv[1]); console.log(JSON.stringify(v.map((r) => efbLayoutSectionMarkup(r, 7, false))));') . ' ' . escapeshellarg(json_encode($vectors))), true);
$same = 0;
foreach ($php as $k => $m) { if (isset($js[$k]) && $js[$k] === $m) $same++; else t("3 vector {$k}: JS = PHP", isset($js[$k]) ? $js[$k] : null, $m); }
t('3 the JS twin prints the PHP markup byte for byte (' . count($php) . ' rows)', $same, count($php));

/* 4 */
$rows = json_decode(file_get_contents(__DIR__ . '/fixtures/f10-old-form-rows.json'), true);
$gold = json_decode(file_get_contents(__DIR__ . '/fixtures/f10-old-form-render.json'), true);
require_once __DIR__ . '/f16-markup.php'; $gold = efb_f16_markup($gold); // F16-E: star aria-hidden, no " ! "
foreach ($rows as $k => $c) {
	$GLOBALS['efb_test_rtl'] = !empty($c['rtl']);
	$all = array((object) array('type' => 'form', 'steps' => 1, 'formName' => 'T'), (object) array('type' => 'step', 'id_' => '1', 'step' => '1', 'name' => 'S'), (object) $c['field']);
	foreach (isset($c['options']) ? $c['options'] : array() as $o) $all[] = (object) $o;
	$fb = new \Emsfb\Formbuilder($all, true);
	$r = $fb->addNewElement_efb(2, $c['field']['id_'], 7, $TX, true);
	$out = is_array($r) ? (string) $r[0] . '|||' . (string) $r[2] : (string) $r;
	t("4 old form, byte for byte as before F10: {$k}", $out, $gold[$k]);
}
$GLOBALS['efb_test_rtl'] = false;

echo "\nRESULTS: {$passed} passed, {$failed} failed\n";
exit($failed ? 1 : 0);
