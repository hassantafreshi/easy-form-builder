<?php
/**
 * Not a test. Renders fields with the real PHP renderer (Emsfb\Formbuilder::addNewElement_efb) for the
 * F03 render tests, with the few WordPress functions it calls stubbed.
 */
if (!defined('ABSPATH')) define('ABSPATH', __DIR__ . '/');
if (!function_exists('get_option')) { function get_option($o, $d = false) { return $d; } }
if (!function_exists('esc_attr')) { function esc_attr($v) { return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8'); } }
if (!function_exists('esc_html')) { function esc_html($v) { return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8'); } }
if (!function_exists('esc_url')) { function esc_url($v) { return (string) $v; } }
if (!function_exists('is_rtl')) { function is_rtl() { return !empty($GLOBALS['efb_test_rtl']); } }
if (!function_exists('wp_enqueue_script')) { function wp_enqueue_script() {} }
if (!function_exists('wp_register_script')) { function wp_register_script() {} }
if (!function_exists('wp_enqueue_style')) { function wp_enqueue_style() {} }
if (!function_exists('apply_filters')) { function apply_filters($h, $v) { return $h === 'efb_f03_plan' ? 'free' : $v; } }
if (!function_exists('number_format_i18n')) { function number_format_i18n($n) { return number_format((float) $n); } }
if (!defined('EMSFB_PLUGIN_URL')) define('EMSFB_PLUGIN_URL', 'https://site.example/wp-content/plugins/easy-form-builder/');
if (!defined('EMSFB_PLUGIN_VERSION')) define('EMSFB_PLUGIN_VERSION', 'test');
require_once __DIR__ . '/../includes/class-Emsfb-masks.php';
require_once __DIR__ . '/../includes/class-Emsfb-field-rules.php';
require_once __DIR__ . '/../includes/class-Emsfb-formbuilder.php';

/** A field row with the builder's defaults plus $extra. */
function efb_fr_field($id, $type, array $extra = array()) {
	return array_merge(array('id_' => $id, 'dataId' => $id . '-id', 'type' => $type, 'name' => 'Field ' . $id, 'step' => '1', 'amount' => '2', 'required' => '0',
		'placeholder' => '', 'value' => '', 'message' => '', 'classes' => '', 'el_height' => 'h-d-efb', 'el_border_color' => 'border-d', 'el_text_color' => 'text-labelEfb',
		'label_text_color' => 'text-labelEfb', 'label_text_size' => 'fs-6', 'message_text_color' => 'text-muted', 'label_position' => 'up', 'label_align' => 'txt-left',
		'message_align' => 'justify-content-start', 'el_align' => 'justify-content-start', 'size' => '100', 'corner' => 'efb-square'), $extra);
}

/** The markup of one field (row index 2 of a one-step form). */
function efb_fr_render(array $field, array $texts = array()) {
	$rows = array((object) array('type' => 'form', 'steps' => 1, 'formName' => 'T'), (object) array('type' => 'step', 'id_' => '1', 'step' => '1', 'name' => 'S'), (object) $field);
	$fb = new \Emsfb\Formbuilder($rows, true);
	$r = $fb->addNewElement_efb(2, $field['id_'], 7, $texts, true);
	return is_array($r) ? (string) $r[0] : (string) $r;
}
