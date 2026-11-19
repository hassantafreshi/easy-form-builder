<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
/**
 * F09-C on a real WordPress (the whole shortcode output of Emsfb\_Public::EFB_Form_Builder + Formbuilder):
 *  - forms without a repeater group render byte for byte as before F09;
 *  - a form with a group: its fields are drawn only inside the group's rows (copies {child}_r{n}), the tags of the
 *    whole page are balanced (div, fieldset, script), WordPress's own wpautop() leaves the markup unchanged
 *    (emsfb_autop_safe_markup_efb), the row template is inert (no .emsFormBuilder_v outside it twice), the
 *    repeater script and style are enqueued only on such a page.
 *
 * The fixture tests/fixtures/f09-old-forms-page.json was written with the renderer of the commit before F09
 * (php tests/test-repeater-render-http.php --write with the site's plugin pointing at a checkout of 98274f93). Volatile parts (the form
 * id, nonces, session ids, wp_unique_id counters, asset versions) are normalized before comparing.
 *
 * Re-baselined in the final test (2026-11-18, --write on the e2e site): the fixture still held the pre-F16 markup and
 * the check failed since F16 changed every form on purpose. Every difference was reviewed and is F16's (spec §4,
 * F16-E rows): the live region and the hidden error summary after body_efb, aria-label on the progress bar, a step's
 * <fieldset tabindex="-1"> with its visually hidden <legend>, the required asterisk aria-hidden (was role="none"), the
 * " ! " text gone from the hidden messages, a choice group's label without a for="" that pointed at nothing and its
 * radiogroup with aria-required, the textarea without the invalid value="". Nothing of the repeater; nothing else.
 *
 * Re-baselined for B5 / PUB-01 (2026-11-19, --write on the e2e site): the only difference is the Send / Next / Previous
 * label's class on these btn-darkb forms, text-labelEfb → text-white (the label read 1.4:1 on its button; see
 * tests/test-button-text-contrast.php). Nothing else changed.
 *
 * Needs the e2e site: EFB_WP_LOAD=/path/wp-load.php, or EFB_E2E_SITE_DIR (tests/e2e-wp/README.md);
 * [SKIP] otherwise. Run: php tests/test-repeater-render-http.php [--write]
 */
$wp_load = getenv('EFB_WP_LOAD') ?: (getenv('EFB_E2E_SITE_DIR') ? rtrim(getenv('EFB_E2E_SITE_DIR'), '/') . '/wp-load.php' : '');
if ($wp_load === '' || !is_readable($wp_load)) { echo "[SKIP] WordPress bootstrap was not found (EFB_WP_LOAD / EFB_E2E_SITE_DIR)\nRESULTS: 0 passed, 0 failed\n"; exit(0); }
require $wp_load;
global $wpdb;
$write = in_array('--write', $argv, true);
$fixture = __DIR__ . '/fixtures/f09-old-forms-page.json';

$passed = 0; $failed = 0;
$check = function ($ok, $label, $detail = null) use (&$passed, &$failed) {
	if ($ok) { $passed++; echo "[PASS] $label\n"; return; }
	$failed++; echo "[FAIL] $label" . ($detail !== null ? ' ' . substr(is_string($detail) ? $detail : wp_json_encode($detail, JSON_UNESCAPED_UNICODE), 0, 1500) : '') . "\n";
};
$efb = get_efbFunction();
$plan = get_option('emsfb_pro');
update_option('emsfb_pro', 3);
// F12: a new site renders the hidden trap field after <form id="efbform"> (a site setting, not F09's): the markup is
// compared without it, through the core's own filter (what a site without the setting renders).
add_filter('efb_core_spam_checks', function ($c) { $c['honeypot'] = false; return $c; });
$forms = array();

$f = function ($id, $type, $extra = array()) {
	return array_merge(array('id_' => $id, 'dataId' => $id . '-id', 'type' => $type, 'elementId' => $type, 'name' => 'F ' . $id, 'step' => '1', 'required' => '0', 'placeholder' => '', 'value' => '', 'message' => '', 'classes' => '',
		'el_height' => 'h-d-efb', 'el_border_color' => 'border-d', 'el_text_color' => 'text-labelEfb', 'label_text_color' => 'text-labelEfb', 'label_text_size' => 'fs-6', 'message_text_color' => 'text-muted',
		'label_position' => 'up', 'label_align' => 'txt-left', 'message_align' => 'justify-content-start', 'el_align' => 'justify-content-start', 'size' => '100', 'icon_input' => '', 'visible' => 1), $extra);
};
$o = function ($id, $parent, $label) { return array('id_' => $id, 'dataId' => $id . '-id', 'parent' => $parent, 'type' => 'option', 'value' => $label, 'id_op' => $id, 'step' => '1'); };
$OLD = array(
	'all-types' => array(
		$f('oftxt01', 'text', array('required' => '1', 'message' => 'Your name', 'milen' => '2', 'mlen' => '40')),
		$f('ofeml01', 'email'), $f('ofnum01', 'number', array('milen' => '1', 'mlen' => '10')), $f('oftel01', 'tel'), $f('ofurl01', 'url'),
		$f('oftxa01', 'textarea'), $f('ofdat01', 'date'), $f('ofrng01', 'range', array('milen' => '0', 'mlen' => '100', 'value' => '40')), $f('ofcol01', 'color'),
		$f('ofsel01', 'select'), $o('ofso001', 'ofsel01', 'One'), $o('ofso002', 'ofsel01', 'Two'),
		$f('ofrad01', 'radio', array('required' => '1')), $o('ofro001', 'ofrad01', 'Yes'), $o('ofro002', 'ofrad01', 'No'),
		$f('ofchk01', 'checkbox'), $o('ofco001', 'ofchk01', 'A'), $o('ofco002', 'ofchk01', 'B'),
		$f('ofmsl01', 'multiselect'), $o('ofmo001', 'ofmsl01', 'M1'), $o('ofmo002', 'ofmsl01', 'M2'),
		$f('ofswi01', 'switch'), $f('ofyno01', 'yesNo', array('button_1_text' => 'Yes', 'button_2_text' => 'No')), $f('ofrat01', 'rating'),
		$f('ofhd001', 'heading', array('value' => 'Heading')), $f('offil01', 'file', array('file' => 'image')),
	),
	'two-steps' => array(
		$f('tstxt01', 'text'), $f('tseml01', 'email', array('required' => '1')),
		array('id_' => '2', 'type' => 'step', 'dataId' => '2', 'id' => '2', 'name' => 'Step 2', 'step' => '2', 'visible' => 1, 'icon' => 'bi-ui-checks-grid', 'message' => ''),
		$f('tsnum01', 'number', array('step' => '2')), $f('tschk01', 'checkbox', array('step' => '2')), $o('tsco001', 'tschk01', 'X'),
	),
);

$render = function ($name, $fields, $fixed_id) use ($wpdb, $efb, &$forms) {
	$steps = 1;
	foreach ($fields as $x) if (($x['type'] ?? '') === 'step') $steps = max($steps, (int) $x['step']);
	$rows = array(
		array('type' => 'form', 'steps' => $steps, 'formName' => $name, 'email' => '', 'captcha' => false, 'sendEmail' => '0', 'stateForm' => '0', 'trackingCode' => true, 'thank_you' => 'msg',
			'thank_you_message' => array('icon' => 'bi-hand-thumbs-up', 'thankYou' => 'ok', 'done' => 'ok', 'trackingCode' => 'Code', 'pleaseFillInRequiredFields' => 'Fill'),
			'button_single_text' => 'Send', 'button_Next_text' => 'Next', 'button_Previous_text' => 'Previous', 'button_Next_icon' => 'bi-chevron-right', 'button_Previous_icon' => 'bi-chevron-left',
			'button_color' => 'btn-darkb', 'icon' => 'bi-ui-checks-grid', 'button_state' => $steps > 1 ? 'multi' : 'single', 'show_icon' => '1', 'dShowBg' => '1', 'btns_align' => 'justify-content-center',
			'label_text_color' => 'text-labelEfb', 'el_text_color' => 'text-labelEfb', 'message_text_color' => 'text-muted', 'icon_color' => 'text-white', 'el_height' => 'h-d-efb'),
		array('id_' => '1', 'type' => 'step', 'dataId' => '1', 'id' => '1', 'name' => 'Step 1', 'step' => '1', 'amount' => 1, 'visible' => 1, 'icon' => 'bi-ui-checks-grid', 'message' => ''),
	);
	$a = 2;
	foreach ($fields as $x) { $x['amount'] = $a++; $rows[] = $x; }
	$rows = $efb->sanitize_obj_msg_efb($rows);
	$wpdb->delete($wpdb->prefix . 'emsfb_form', array('form_id' => $fixed_id));
	$wpdb->insert($wpdb->prefix . 'emsfb_form', array('form_id' => $fixed_id, 'form_name' => $name, 'form_type' => 'form', 'form_structer' => str_replace('"', '\\"', wp_json_encode($rows, JSON_UNESCAPED_UNICODE)), 'form_email' => '', 'form_created_by' => 1, 'status' => 1));
	$fid = (int) $wpdb->insert_id;
	$forms[] = $fid;
	wp_cache_flush();
	$html = do_shortcode('[EMS_Form_Builder id=' . $fid . ']');
	// Volatile parts: nonces / session ids / tokens, unique-id counters, asset versions (the form id is fixed).
	$html = preg_replace('/efb_\d+/', 'efb_N', $html);
	$html = preg_replace('/("(?:nonce|sid|runtime_token|token|_wpnonce|rest_nonce|nonce_msg|session)"\s*:\s*")[^"]*"/', '$1X"', $html);
	$html = preg_replace('/([?&]ver=)[^"\'&\s]*/', '$1V', $html);
	$html = preg_replace('/\b[0-9a-f]{10}\b/', 'HEX10', $html);
	return array($rows, $html);
};

$gold = is_readable($fixture) ? json_decode((string) file_get_contents($fixture), true) : array();
$out = array();
try {
	foreach ($OLD as $name => $fields) {
		list($rows, $html) = $render('F09 old ' . $name, $fields, 990001 + array_search($name, array_keys($OLD), true));
		$out[$name] = $html;
		if ($write) continue;
		$same = isset($gold[$name]) && $gold[$name] === $html;
		if (!$same && isset($gold[$name])) {
			$a = $gold[$name]; $b = $html; $n = min(strlen($a), strlen($b)); $i = 0;
			while ($i < $n && $a[$i] === $b[$i]) $i++;
			$detail = 'first difference at ' . $i . ': before …' . substr($a, max(0, $i - 80), 220) . '… now …' . substr($b, max(0, $i - 80), 220) . '…';
		} else $detail = isset($gold[$name]) ? null : 'no fixture';
		$check($same && strlen($html) > 2000, "old form \"$name\": the whole page markup is byte for byte the one before F09 (" . strlen($html) . ' bytes)', $detail);
		$check(strpos($html, 'efb-rpt') === false && strpos($html, '__RI__') === false && strpos($html, 'repeater-efb') === false, "old form \"$name\": nothing of the repeater (markup, template, script) on the page");
	}
	if (!$write) {
		/* A form with a repeater group. */
		update_option('emsfb_pro', 3);
		add_filter('efb_repeater_plan', function ($n) { return 'free_plus'; });
		list($rows, $html) = $render('F09 group', array(
			$f('rgtxt00', 'text', array('name' => 'Before')),
			$f('rgrp001', 'repeater', array('name' => 'Travellers', 'min_rows' => '1', 'max_rows' => '4', 'default_rows' => '2', 'row_label' => 'Traveller {n}', 'collapsible' => '1')),
			$f('rgnam01', 'text', array('group' => 'rgrp001', 'name' => 'Name', 'required' => '1')),
			$f('rgsel01', 'select', array('group' => 'rgrp001', 'name' => 'Meal')), $o('rgso001', 'rgsel01', 'Vegan'), $o('rgso002', 'rgsel01', 'Halal'),
			$f('rgchk01', 'checkbox', array('group' => 'rgrp001', 'name' => 'Extras')), $o('rgco001', 'rgchk01', 'Bag'),
			$f('rgaft01', 'email', array('name' => 'After')),
		), 990010);
		$tplA = strpos($html, '<script type="text/html"');
		$tplB = strpos($html, '</script>', $tplA);
		$outside = substr($html, 0, $tplA) . substr($html, $tplB);
		$check(strpos($outside, 'id="rgnam01_"') === false && substr_count($outside, 'id="rgnam01_r0_"') === 1 && substr_count($outside, 'id="rgnam01_r1_"') === 1, 'group form: the child is drawn only as its row copies (_r0, _r1)');
		$check(strpos($html, 'id="rgaft01_"') !== false && strpos($html, 'id="rgtxt00_"') !== false, 'group form: the fields before and after the group are drawn as usual');
		$bal = function ($h, $tag) { return preg_match_all('/<' . $tag . '[\s>]/i', $h) - substr_count(strtolower($h), '</' . $tag . '>'); };
		$check($bal($outside, 'div') === 0 && $bal($html, 'fieldset') === 0 && $bal($html, 'script') === 0, 'group form: div, fieldset and script are balanced on the whole page (the template apart)', array($bal($outside, 'div'), $bal($html, 'fieldset'), $bal($html, 'script')));
		$auto = wpautop($html);
		$strip = function ($h) { return preg_replace('/\s+/', ' ', preg_replace('#</?p>|<br ?/?>#', '', $h)); };
		$where = preg_match('#<p>|<br ?/?>#', $auto, $pm, PREG_OFFSET_CAPTURE) ? substr($auto, max(0, $pm[0][1] - 30), 330) : '';
		$check(strpos($auto, '<p>') === false && !preg_match('#<br ?/?>#', $auto) && $bal(preg_replace('#<script type="text/html".*?</script>#s', '', $auto), 'div') === 0, 'group form: WordPress wpautop() adds no paragraph or line break to the group form (template included)', $where);
		$check(substr_count($outside, 'emsFormBuilder_v') > 0 && strpos(substr($html, $tplA, $tplB - $tplA), 'rgnam01_r__RI___') !== false && strpos(substr($html, $tplA, $tplB - $tplA), '<\/div>') !== false, 'group form: the template (an inert script block, "</" written "<\\/") holds one row with __RI__ ids');
		global $wp_scripts, $wp_styles;
		$check(isset($wp_scripts->registered['efb-repeater-js']) && in_array('efb-repeater-js', $wp_scripts->queue, true) && in_array('efb-repeater-css', $wp_styles->queue, true), 'group form: repeater-efb.js and repeater-efb.css are enqueued');
	}
	if ($write) {
		file_put_contents($fixture, wp_json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n");
		echo "written $fixture\n";
	}
} finally {
	foreach ($forms as $fid) $wpdb->delete($wpdb->prefix . 'emsfb_form', array('form_id' => $fid));
	update_option('emsfb_pro', $plan);
}
echo "\nRESULTS: {$passed} passed, {$failed} failed\n";
exit($failed ? 1 : 0);
