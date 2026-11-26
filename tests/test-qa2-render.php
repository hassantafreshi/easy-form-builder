<?php
/**
 * FX-PUB (QA-2): the public renderer, no WordPress.
 *  - QA2-001: a Jalali / Hijri date field (pdate / ardate) whose add-on folder is missing draws the field's notice in its
 *    place and returns [markup, style, js] (it returned the whole-form wait card as a string, read as $r[0..2]: a stray
 *    "<" in the form, a "d" in the style, an "i" in the script).
 *
 * Run: php tests/test-qa2-render.php
 */
if (!defined('EMSFB_PLUGIN_DIRECTORY')) define('EMSFB_PLUGIN_DIRECTORY', sys_get_temp_dir() . '/efb-qa2-no-addons-' . getmypid() . '/');
$GLOBALS['efb_qa2_queued'] = array();
if (!function_exists('get_efbFunction')) {
	function get_efbFunction() {
		return new class {
			public function queue_addon_recovery_efb($a) { $GLOBALS['efb_qa2_queued'][] = $a['addon']; return array('queued' => true); }
			public function addon_wait_message_public_efb($s = 25) { return "<div id='body_efb'>wait</div>"; }
		};
	}
}
require __DIR__ . '/field-rules-render-lib.php';

$passed = 0; $failed = 0;
function t($name, $ok, $info = '') {
	global $passed, $failed;
	if ($ok) { echo "[PASS] {$name}\n"; $passed++; return; }
	echo "[FAIL] {$name}" . ($info !== '' ? ': ' . $info : '') . "\n"; $failed++;
}
function qa2_render_raw(array $field) {
	$rows = array((object) array('type' => 'form', 'steps' => 1, 'formName' => 'T'), (object) array('type' => 'step', 'id_' => '1', 'step' => '1', 'name' => 'S'), json_decode(json_encode($field)));
	$fb = new \Emsfb\Formbuilder($rows, true);
	return $fb->addNewElement_efb(2, $field['id_'], 7, array('tfnapca' => 'This field is currently unavailable.'), true);
}

/* QA2-001 */
foreach (array('pdate' => 'AdnPDP', 'ardate' => 'AdnADP') as $type => $code) {
	$r = qa2_render_raw(efb_fr_field('d' . $type, $type, array('required' => '1')));
	t("QA2-001 {$type} without its add-on: the renderer returns [markup, style, js]", is_array($r) && count($r) === 3, gettype($r));
	$m = is_array($r) ? (string) $r[0] : (string) $r;
	t("QA2-001 {$type}: the field's notice is in its place (data-efb-addon={$code})", strpos($m, 'data-efb-addon="' . $code . '"') !== false && strpos($m, 'currently unavailable') !== false, substr($m, 0, 200));
	t("QA2-001 {$type}: no whole-form wait card, no stray character in style / script", strpos($m, "id='body_efb'") === false && is_array($r) && strlen((string) $r[1]) !== 1 && strlen((string) $r[2]) !== 1, is_array($r) ? json_encode(array(substr($r[1], 0, 20), substr($r[2], 0, 20))) : '');
}
t('QA2-001: the add-on recovery is still queued for both', $GLOBALS['efb_qa2_queued'] === array('AdnPDP', 'AdnADP'), json_encode($GLOBALS['efb_qa2_queued']));

require_once __DIR__ . '/../includes/class-Emsfb-split.php';
$u = \Emsfb\Split::unrequire_absent(array(array('type' => 'form'), array('type' => 'pdate', 'id_' => 'a', 'required' => '1'), array('type' => 'text', 'id_' => 'b', 'required' => '1')));
t('QA2-001: the submit does not require a pdate field whose add-on is absent (SPLIT §4.1); a text field stays required', empty($u[1]['required']) && $u[2]['required'] === '1', json_encode($u));

echo "\nRESULTS: {$passed} passed, {$failed} failed\n";
exit($failed > 0 ? 1 : 0);
