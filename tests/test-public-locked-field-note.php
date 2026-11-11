<?php
/**
 * F18 sub-task H: a field the plan does not include, on the public page.
 *
 * Formbuilder::public_pro_message_efb() (through reflection, WordPress stubs):
 * - a visitor sees the existing "field unavailable" text (tfnapca), nothing about plans;
 * - a user who can change the plan sees upgAdminFieldNote and a link to
 *   admin.php?page=Emsfb_create&efb_upgrade=field:<type> that opens the dialog for that field;
 * - an editor who cannot change the plan keeps the old Free Plus/Pro sentence, without a link;
 * - the type is the element being rendered (set by addNewElement_efb), escaped in the URL;
 * - upgAdminFieldNote / upgAdminFieldLink are in the public phrase subset (class-Emsfb-public.php).
 *
 * Run: php tests/test-public-locked-field-note.php
 */
define('ABSPATH', __DIR__ . '/');
$pass = 0; $fail = 0;
function check($label, $ok, $detail = null) {
	global $pass, $fail;
	if ($ok) { $pass++; echo "[PASS] $label\n"; }
	else { $fail++; echo "[FAIL] $label" . ($detail !== null ? ' ' . json_encode($detail) : '') . "\n"; }
}
$GLOBALS['caps'] = array();
$GLOBALS['can_change'] = false;
function get_option($o, $d = false) { return $d; }
function current_user_can($c) { return !empty($GLOBALS['caps'][$c]); }
function efb_can_change_plan_efb() { return $GLOBALS['can_change']; }
function admin_url($p = '') { return 'https://site.test/wp-admin/' . $p; }
function esc_url($u) { return str_replace('&', '&#038;', str_replace(array('"', "'", '<', '>'), '', $u)); } // as WordPress: & → &#038;
function esc_html($s) { return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8'); }
function esc_attr($s) { return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8'); }
function is_rtl() { return false; }
function add_action() {} function add_filter() {}

require_once __DIR__ . '/../includes/class-Emsfb-formbuilder.php';
$cls = class_exists('\Emsfb\Formbuilder') ? '\Emsfb\Formbuilder' : 'Formbuilder';
$fb = (new ReflectionClass($cls))->newInstanceWithoutConstructor();
$m = new ReflectionMethod($cls, 'public_pro_message_efb');
$m->setAccessible(true);
$prop = (new ReflectionClass($cls))->hasProperty('pro_notice_type_efb') ? new ReflectionProperty($cls, 'pro_notice_type_efb') : null;
if ($prop) { $prop->setAccessible(true); $prop->setValue($fb, 'esign'); }
$texts = array('tfnapca' => 'This field is currently unavailable.', 'thisFeatureAvailableFreePlusPro' => 'Want to use this feature? It is included in Free Plus and Pro plans.',
	'upgAdminFieldNote' => 'Visitors see this field once Free Plus or Pro is active.', 'upgAdminFieldLink' => 'Activate it in the form builder');
$call = function () use ($m, $fb, $texts) {
	$args = array($texts['tfnapca'], $texts['thisFeatureAvailableFreePlusPro']);
	if ($m->getNumberOfParameters() >= 3) $args[] = $texts;
	return $m->invokeArgs($fb, $args);
};

$GLOBALS['caps'] = array(); $GLOBALS['can_change'] = false;
$v = $call();
check('visitor: the "field unavailable" text, no plan or link', strpos($v, 'currently unavailable') !== false && strpos($v, 'Free Plus') === false && strpos($v, 'href') === false, $v);

$GLOBALS['caps'] = array('edit_posts' => true); $GLOBALS['can_change'] = false;
$v = $call();
check('editor who cannot change the plan: the Free Plus/Pro sentence, no link', strpos($v, 'included in Free Plus and Pro') !== false && strpos($v, 'href') === false, $v);

$GLOBALS['caps'] = array('edit_posts' => true, 'manage_options' => true); $GLOBALS['can_change'] = true;
$v = $call();
check('administrator: upgAdminFieldNote', $prop !== null && strpos($v, 'Visitors see this field once Free Plus or Pro is active.') !== false, $v);
check('administrator: link to the builder dialog for this field', strpos($v, 'href="https://site.test/wp-admin/admin.php?page=Emsfb_create&#038;efb_upgrade=field:esign"') !== false && strpos($v, 'Activate it in the form builder') !== false, $v);
if ($prop) $prop->setValue($fb, 'es"ign<x>');
$v = $call();
check('a hostile type cannot break the link', strpos($v, '<x>') === false && strpos($v, 'es"ign') === false, $v);

$src = file_get_contents(__DIR__ . '/../includes/class-Emsfb-formbuilder.php');
check('addNewElement_efb records the type of the element it renders', (bool) preg_match('/pro_notice_type_efb\s*=\s*\$elementId/', $src));
$pub = file_get_contents(__DIR__ . '/../includes/class-Emsfb-public.php');
check('upgAdminFieldNote and upgAdminFieldLink are in the public phrase subset', strpos($pub, '"upgAdminFieldNote"') !== false && strpos($pub, '"upgAdminFieldLink"') !== false);

echo "\n========================================\n";
echo "RESULTS: $pass passed, $fail failed\n";
echo "========================================\n";
exit($fail > 0 ? 1 : 0);
