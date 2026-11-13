<?php
/**
 * Not a test. The PHP side of tests/test-f11-parity.js and tests/test-form-css-parity.js: reads
 * [{fn, args}] as JSON on stdin and prints the PHP results in the same order. Only pure functions
 * (no database), with WordPress' esc_html() and the site locale stubbed.
 */
define('ABSPATH', __DIR__ . '/');
$GLOBALS['efb_test_roles'] = array('administrator' => 'Administrator', 'editor' => 'Editor', 'subscriber' => 'Subscriber');
function esc_html($s) { return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8'); }
function esc_attr($s) { return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8'); }
function sanitize_text_field($v) { return is_array($v) ? '' : trim(preg_replace('/[\r\n\t ]+/', ' ', strip_tags((string) $v))); }
function sanitize_key($k) { return strtolower(preg_replace('/[^a-z0-9_\-]/i', '', (string) $k)); }
function determine_locale() { return $GLOBALS['locale'] ?? 'en_US'; }
require __DIR__ . '/../includes/class-Emsfb-rate-limit.php';
require __DIR__ . '/../includes/class-Emsfb-form-css.php';
require __DIR__ . '/../includes/class-Emsfb-form-gate.php';
if (is_readable(__DIR__ . '/../includes/class-Emsfb-drafts.php')) require __DIR__ . '/../includes/class-Emsfb-drafts.php';
$in = json_decode(stream_get_contents(STDIN), true);
$out = array();
foreach ((array) $in as $c) {
	$a = isset($c['args']) ? $c['args'] : array();
	switch ($c['fn']) {
		case 'card': $out[] = \Emsfb\Form_Gate::card_html($a[0], $a[1], $a[2], $a[3], $a[4]); break;
		case 'left': $GLOBALS['locale'] = $a[2]; $out[] = \Emsfb\Form_Gate::left_html($a[0], $a[1]); break;
		case 'digits': $out[] = \Emsfb\Form_Gate::locale_digits($a[0], $a[1]); break;
		case 'value': $out[] = \Emsfb\Form_Gate::sanitize_key($a[0], $a[1]); break;
		case 'css_sanitize': $out[] = \Emsfb\Form_Css::sanitize($a[0]); break;
		case 'css_scope': $out[] = \Emsfb\Form_Css::scope(\Emsfb\Form_Css::sanitize($a[0]), $a[1]); break;
		case 'css_stored': $out[] = \Emsfb\Form_Css::sanitize_stored($a[0]); break;
		case 'draft_button': $out[] = class_exists('\\Emsfb\\Drafts') && method_exists('\\Emsfb\\Drafts', 'button_html') ? \Emsfb\Drafts::button_html($a[0], $a[1], !empty($a[2])) : null; break;
		default: $out[] = null;
	}
}
echo json_encode($out, JSON_UNESCAPED_UNICODE);
