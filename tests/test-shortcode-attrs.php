<?php
/**
 * F11-H: the shortcode's form id and the popup attributes.
 *
 * Standalone (the static helpers of class-Emsfb-public.php lifted out by name):
 *  - [EMS_Form_Builder id=5 popup=1] is form 5 (end($atts) used to give "1"), [EMS_Form_Builder 5]
 *    and [EMS_Form_Builder 5 popup=1] are form 5, [EMS_Form_Builder id="7"] is 7, no attribute = '';
 *  - popup attributes: popup 1/true/yes/on, popup_style button|link, popup_delay 0..3600, a label
 *    without tags (80 characters);
 *  - the dialog markup: native <dialog>, aria-labelledby → the form name, close button with an
 *    aria-label, opener with aria-haspopup / aria-controls, everything escaped;
 *  - Emsfb_Widgets_Helper::generate_shortcode() writes the block's popup attributes, without quotes
 *    or brackets from the label.
 * With EFB_F11_WP_LOAD=/path/to/wp-load.php (the e2e site) also: do_shortcode() of a real form with
 * popup="1" and with extra attributes, through the_content (wpautop): form 5's markup inside one
 * <dialog>, no <p> inserted between its pieces.
 *
 * Run: php tests/test-shortcode-attrs.php
 */
$passed = 0;
$failed = 0;
function t($name, $actual, $expected) {
	global $passed, $failed;
	if ($actual === $expected) { echo "[PASS] {$name}\n"; $passed++; return; }
	echo "[FAIL] {$name}: expected " . var_export($expected, true) . ', got ' . var_export($actual, true) . "\n";
	$failed++;
}
$wp_load = getenv('EFB_F11_WP_LOAD');
$with_wp = $wp_load && is_readable($wp_load);
if ($with_wp) {
	$_SERVER['HTTP_HOST'] = parse_url(getenv('EFB_E2E_URL') ?: 'http://127.0.0.1:8088', PHP_URL_HOST) . ':' . (parse_url(getenv('EFB_E2E_URL') ?: 'http://127.0.0.1:8088', PHP_URL_PORT) ?: 80);
	$_SERVER['REQUEST_METHOD'] = 'GET';
	$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
	require $wp_load;
} else {
	function esc_html($s) { return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8'); }
	function esc_attr($s) { return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8'); }
	function wp_strip_all_tags($s) { return trim(strip_tags((string) $s)); }
	function add_action() { return true; }
	function add_filter() { return true; }
	define('ABSPATH', __DIR__ . '/');
}
$src = file_get_contents(__DIR__ . '/../includes/class-Emsfb-public.php');
$grab = function ($name) use ($src) {
	if (!preg_match('/\tpublic static function ' . $name . '\(.*?\n\t\}\n/s', $src, $m)) throw new Exception('missing ' . $name);
	return $m[0];
};
eval('class EfbF11Atts { ' . $grab('form_id_from_atts_efb') . $grab('popup_atts_efb') . $grab('popup_markup_efb') . ' }');

$id = function ($a) { return EfbF11Atts::form_id_from_atts_efb($a); };
t('[EMS_Form_Builder id=5 popup=1] → 5 (end() gave "1")', $id(array('id' => '5', 'popup' => '1')), '5');
t('[EMS_Form_Builder 5] → 5', $id(array('5')), '5');
t('[EMS_Form_Builder 5 popup=1] → 5', $id(array(0 => '5', 'popup' => '1')), '5');
t('[EMS_Form_Builder popup=1 id=9 popup_label="Join"] → 9', $id(array('popup' => '1', 'id' => '9', 'popup_label' => 'Join')), '9');
t('[EMS_Form_Builder id="7"] → 7', $id(array('id' => '7')), '7');
t('no attribute (WordPress < 6.5 passes \'\') → \'\'', $id(''), '');
t('empty array → \'\'', $id(array()), '');
t('an old shortcode with only a non-numeric value keeps the old behaviour (last value)', $id(array('x' => 'abc')), 'abc');

$p = function ($a) { return EfbF11Atts::popup_atts_efb($a); };
t('no popup attribute → null (inline as before)', $p(array('id' => '5')), null);
t('popup="0" → null', $p(array('id' => '5', 'popup' => '0')), null);
t('popup="1" defaults', $p(array('id' => '5', 'popup' => '1')), array('style' => 'button', 'delay' => 0, 'label' => ''));
t('popup="yes" link, delay 3, label without tags', $p(array('popup' => 'yes', 'popup_style' => 'LINK', 'popup_delay' => '3', 'popup_label' => '<b>Register</b> now')), array('style' => 'link', 'delay' => 3, 'label' => 'Register now'));
t('delay capped, garbage refused', array($p(array('popup' => '1', 'popup_delay' => '99999'))['delay'], $p(array('popup' => '1', 'popup_delay' => '-1'))['delay']), array(0, 0));
t('label cut at 80 characters', mb_strlen($p(array('popup' => 'on', 'popup_label' => str_repeat('ب', 120)))['label']), 80);

$m = EfbF11Atts::popup_markup_efb('<div id="body_efb_5">FORM</div>', 5, array('style' => 'button', 'delay' => 0, 'label' => 'Sign "up" <now>'), 'Contact & more', 'Close', 'Open');
t('dialog: native, aria-labelledby the form name', (bool) (preg_match('/<dialog id="efb-dlg-5" class="efb efb-popup" aria-labelledby="efb-dlg-title-5">/', $m) && strpos($m, '<h2 class="efb efb-popup-title" id="efb-dlg-title-5">Contact &amp; more</h2>') !== false), true);
t('opener: aria-haspopup=dialog, aria-controls, escaped label', strpos($m, 'aria-haspopup="dialog" aria-controls="efb-dlg-5" data-efb-popup="5" data-efb-popup-delay="0">Sign &quot;up&quot; &lt;now&gt;</button>') !== false, true);
t('close: aria-label, decorative ×', strpos($m, 'aria-label="Close" data-efb-popup-close="5"><span aria-hidden="true">&times;</span></button>') !== false, true);
t('the form is inside the dialog', (bool) preg_match('#<dialog[^>]*>.*<div id="body_efb_5">FORM</div>.*</dialog>#s', $m), true);
t('starts with a block tag (wpautop)', strpos($m, '<div class="efb efb-popup-wrap"') === 0, true);
t('link style', strpos(EfbF11Atts::popup_markup_efb('x', 5, array('style' => 'link', 'delay' => 0, 'label' => ''), 'F', 'Close', 'Open the form'), 'class="efb efb-popup-open btn btn-link p-0"') !== false, true);
t('no label → the default text', strpos(EfbF11Atts::popup_markup_efb('x', 5, array('style' => 'button', 'delay' => 0, 'label' => ''), 'F', 'Close', 'Open the form'), '>Open the form</button>') !== false, true);

if (!class_exists('Emsfb_Widgets_Helper')) require __DIR__ . '/../includes/class-Emsfb-widgets-helper.php';
t('widgets helper: inline shortcode unchanged', Emsfb_Widgets_Helper::generate_shortcode('5'), '[EMS_Form_Builder id="5"]');
t('widgets helper: popup attributes from the block', Emsfb_Widgets_Helper::generate_shortcode('5', array('displayMode' => 'popup', 'popupLabel' => 'Join "us" [now]', 'popupStyle' => 'link')), '[EMS_Form_Builder id="5" popup="1" popup_style="link" popup_label="Join us now"]');
$bj = json_decode(file_get_contents(__DIR__ . '/../includes/page-builders/gutenberg/block.json'), true);
t('block.json: displayMode, popupLabel, popupStyle', array($bj['attributes']['displayMode']['default'], $bj['attributes']['popupLabel']['type'], $bj['attributes']['popupStyle']['enum']), array('inline', 'string', array('button', 'link')));

if (!$with_wp) {
	echo "[SKIP] real WordPress: set EFB_F11_WP_LOAD=/path/to/wp-load.php\n";
} else {
	global $wpdb;
	$fid = (int) $wpdb->get_var("SELECT form_id FROM {$wpdb->prefix}emsfb_form WHERE form_type = 'form' ORDER BY form_id DESC LIMIT 1");
	if (!$fid) {
		echo "[SKIP] real WordPress: no form of type 'form' on the site\n";
	} else {
		update_option('emsfb_pro', 3);
		$GLOBALS['post'] = null;
		$html = apply_filters('the_content', "<p>Intro</p>\n\n[EMS_Form_Builder id={$fid} popup=\"1\" popup_label=\"Join\"]\n\nAfter");
		t('WordPress: the popup of form ' . $fid . ' (one dialog, the opener "Join")', array(substr_count($html, '<dialog id="efb-dlg-' . $fid . '"'), strpos($html, '>Join</button>') !== false), array(1, true));
		t('WordPress: form ' . $fid . ' rendered inside the dialog (not form "1")', (bool) preg_match('#<dialog id="efb-dlg-' . $fid . '".*?id="body_efb_' . $fid . '"#s', $html), true);
		$inside = preg_match('#<dialog.*?</dialog>#s', $html, $mm) ? $mm[0] : '';
		t('WordPress: wpautop put no <p> inside the dialog', preg_match('#<p[\s>]#', preg_replace('#<p class="efb[^"]*"#', '', $inside)), 0);
		$h2 = do_shortcode('[EMS_Form_Builder id=' . $fid . ' popup_style=link]');
		t('WordPress: an extra attribute without popup still renders form ' . $fid . ' inline', array(strpos($h2, 'id="body_efb_' . $fid . '"') !== false, strpos($h2, '<dialog') === false), array(true, true));
	}
}

echo "\nRESULTS: {$passed} passed, {$failed} failed\n";
exit($failed ? 1 : 0);
