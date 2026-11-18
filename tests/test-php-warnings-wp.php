<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
/**
 * Final test: the PHP warnings WP_DEBUG_LOG recorded during the real-WordPress test runs, each reproduced here on the
 * e2e site with an error handler that collects every warning, notice and deprecation raised in the plugin's files:
 *  - the page of a form whose choice fields lack style keys a builder form has (forms made through the API or a
 *    package): "Undefined property $el_text_size" (radio / checkbox options) and "$minSelect / $maxSelect"
 *    (multiselect);
 *  - an e-mail sent when $this->lanText holds only a few phrases (the customer portal's owner reply loads five):
 *    "Undefined array key msgdml …" eleven times, and the e-mail went out with empty sentences;
 *  - a booking form's radio and select answers: "$f" undefined inside the closures (the Booking add-on's
 *    efb_choice_option_state got no field).
 *
 * Needs the e2e site (EFB_E2E_SITE_DIR or EFB_WP_LOAD); [SKIP] otherwise. Run: php tests/test-php-warnings-wp.php
 */
$wp_load = getenv('EFB_WP_LOAD') ?: (getenv('EFB_E2E_SITE_DIR') ? rtrim(getenv('EFB_E2E_SITE_DIR'), '/') . '/wp-load.php' : '');
if ($wp_load === '' || !is_readable($wp_load)) { echo "[SKIP] WordPress bootstrap was not found (EFB_WP_LOAD / EFB_E2E_SITE_DIR)\nRESULTS: 0 passed, 0 failed\n"; exit(0); }
require $wp_load;
global $wpdb;

$passed = 0; $failed = 0;
$t = function ($label, $actual, $expected) use (&$passed, &$failed) {
	if ($actual === $expected) { $passed++; echo "[PASS] $label\n"; return; }
	$failed++; echo "[FAIL] $label\n      expected " . json_encode($expected) . "\n      actual   " . json_encode($actual, JSON_UNESCAPED_SLASHES) . "\n";
};
$root = wp_normalize_path(dirname(__DIR__)) . '/';
$warnings = array();
set_error_handler(function ($no, $msg, $file, $line) use (&$warnings, $root) {
	if (in_array($no, array(E_WARNING, E_NOTICE, E_DEPRECATED, E_USER_WARNING, E_USER_NOTICE), true) && strpos(wp_normalize_path((string) $file), $root) === 0) {
		$warnings[] = $msg . ' @ ' . substr(wp_normalize_path($file), strlen($root)) . ':' . $line;
	}
	return false;
});
$take = function () use (&$warnings) { $w = array_values(array_unique($warnings)); $warnings = array(); return $w; };

$plan = get_option('emsfb_pro');
update_option('emsfb_pro', 3);
$fid = 990090;
try {
	/* 1. Choice fields without the builder's style keys. */
	// The keys the e2e helpers (ctl.php f03-form) and AutoPilot give a field, without el_text_size / minSelect / maxSelect.
	$f = function ($id, $type, $extra = array()) { return array_merge(array('id_' => $id, 'dataId' => $id . '-id', 'type' => $type, 'elementId' => $type, 'name' => 'F ' . $id, 'step' => '1', 'required' => '0', 'value' => '', 'message' => '', 'classes' => '', 'size' => '100',
		'el_height' => 'h-d-efb', 'el_border_color' => 'border-d', 'el_text_color' => 'text-labelEfb', 'label_text_color' => 'text-labelEfb', 'label_text_size' => 'fs-6', 'message_text_color' => 'text-muted',
		'label_position' => 'up', 'label_align' => 'txt-left', 'message_align' => 'justify-content-start', 'el_align' => 'justify-content-start', 'icon_input' => '', 'visible' => 1), $extra); };
	$o = function ($id, $parent, $label) { return array('id_' => $id, 'dataId' => $id . '-id', 'parent' => $parent, 'type' => 'option', 'value' => $label, 'id_op' => $id, 'step' => '1'); };
	$rows = array(
		array('type' => 'form', 'steps' => '1', 'formName' => 'Warnings', 'email' => '', 'trackingCode' => '1', 'EfbVersion' => '2', 'button_single_text' => 'Send', 'button_color' => 'btn-darkb', 'icon' => 'bi-ui-checks-grid', 'button_state' => 'single', 'show_icon' => '1', 'thank_you' => 'msg', 'stateForm' => '0', 'dShowBg' => '1', 'btns_align' => 'justify-content-center', 'label_text_color' => 'text-labelEfb', 'el_text_color' => 'text-labelEfb', 'message_text_color' => 'text-muted', 'icon_color' => 'text-white', 'el_height' => 'h-d-efb'),
		array('id_' => '1', 'type' => 'step', 'dataId' => '1', 'id' => '1', 'name' => 'Step 1', 'step' => '1', 'amount' => 1, 'visible' => 1, 'icon' => 'bi-ui-checks-grid', 'message' => ''),
		$f('wrad001', 'radio'), $o('wro0001', 'wrad001', 'Yes'), $o('wro0002', 'wrad001', 'No'),
		$f('wchk001', 'checkbox'), $o('wco0001', 'wchk001', 'A'),
		$f('wmsl001', 'multiselect'), $o('wmo0001', 'wmsl001', 'M1'),
	);
	$a = 2; foreach ($rows as $i => $r) if ($i > 1) $rows[$i]['amount'] = $a++;
	$wpdb->delete($wpdb->prefix . 'emsfb_form', array('form_id' => $fid));
	$wpdb->insert($wpdb->prefix . 'emsfb_form', array('form_id' => $fid, 'form_name' => 'Warnings', 'form_type' => 'form', 'form_structer' => str_replace('"', '\\"', wp_json_encode($rows, JSON_UNESCAPED_UNICODE)), 'form_email' => '', 'form_created_by' => 1, 'status' => 1));
	wp_cache_flush();
	$take();
	$html = do_shortcode('[EMS_Form_Builder id=' . $fid . ']');
	$t('a form whose radio, checkbox and multiselect lack the builder\'s style keys renders (its options there)', strpos($html, 'wro0001') !== false && strpos($html, 'wco0001') !== false && strpos($html, 'wmsl001') !== false, true);
	$t('… without a PHP warning ($el_text_size, $minSelect, $maxSelect)', $take(), array());

	/* 2. An e-mail sent with only a few phrases loaded (the portal's owner reply). */
	$pub = \Emsfb\_Public::$instance_efb;
	if (!$pub) { $pub = new \Emsfb\_Public(); }
	$pub->lanText = get_efbFunction()->text_efb(array('guest', 'spprt', 'messageSent', 'errorSomthingWrong', 'error405'));
	$sent = array();
	$cap = function ($null, $atts) use (&$sent) { $sent[] = $atts; return true; };
	add_filter('pre_wp_mail', $cap, 1, 2);
	$take();
	$pub->send_email_Emsfb_(array('owner@example.test', ''), 'TRK123', 0, array('newMessage', 'newMessage', 'reply'), home_url('/?track=TRK123'), 'null', 'null');
	remove_filter('pre_wp_mail', $cap, 1);
	$t('an e-mail sent after only the portal\'s five phrases were loaded: no PHP warning', $take(), array());
	$body = isset($sent[0]['message']) ? (string) $sent[0]['message'] : '';
	$t('… and its sentences are there (the confirmation code line, not an empty phrase)', array(count($sent) > 0, strpos($body, 'TRK123') !== false, strpos($body, 'confirmation code') !== false), array(true, true, true));

	/* 3. The booking branches pass the field to efb_choice_option_state (static: the closures carry $f). */
	$src = file_get_contents(dirname(__DIR__) . '/includes/class-Emsfb-public.php');
	$ok = 0; $all = 0;
	for ($p = 0; ($p = strpos($src, 'choice_option_state_efb($fr, $f)', $p)) !== false; $p++) { $all++; $fn = strrpos(substr($src, 0, $p), 'function ($fr, $ki) use ('); if ($fn !== false && preg_match('/[(,]\s*\$f\s*\)/', substr($src, $fn, strpos($src, ') {', $fn) - $fn + 1))) $ok++; }
	$t('the booking checks of radio and select answers see the field ($f in the closures\' use list)', array($all, $ok), array(2, 2));
} finally {
	restore_error_handler();
	$wpdb->delete($wpdb->prefix . 'emsfb_form', array('form_id' => $fid));
	if ($plan === false) delete_option('emsfb_pro'); else update_option('emsfb_pro', $plan);
	wp_cache_flush();
}
echo "\nRESULTS: {$passed} passed, {$failed} failed\n";
exit($failed ? 1 : 0);
