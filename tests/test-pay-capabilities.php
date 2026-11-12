<?php
/**
 * PAY00 sub-task J: the capability registry (efb_var.capabilities / capabilities_efb()) and the
 * PHP field templates for AutoPilot / SitePilot.
 *  - field:<slug> per registered gateway (Free Plus), setting:pay_style / pay_pwyw /
 *    pay_delayed_actions (Free Plus) and pay_style_mobile (Pro), with their form keys;
 *  - payment_gateways: configured test/live, mode, methods, supports, and never a key;
 *    payment_keys.<slug> = configured in the current mode; the payments marker;
 *  - field_templates('efbmock'): one gateway row and form_patch {type, getway, currency};
 *    field_templates('mollie') without its add-on → addon_required;
 *  - the seven gateway add-ons are Free Plus in functions.php and in the builder's JS twin;
 *  - on Free, field:efbmock is not available_now (reason plan).
 *
 * Run: php tests/test-pay-capabilities.php
 */
define('ABSPATH', __DIR__ . '/');
$passed = 0; $failed = 0;
require __DIR__ . '/pay-test-db.php';
$db = efb_pay_env();
$t = 'efb_pay_t';
use Emsfb\Builder_Api;
$cap = Builder_Api::capabilities(array('package' => 3, 'settings' => (object) array()));
$f = $cap['features'];
$t('field:efbmock is Free Plus, available on Free Plus', array($f['field:efbmock']['package'], $f['field:efbmock']['available_now']), array('free_plus', true));
$t('...with its form_patch', $f['field:efbmock']['form_patch'], array('type' => 'payment', 'getway' => 'efbmock'));
$t('setting:pay_style / pay_pwyw / pay_delayed_actions Free Plus, pay_style_mobile Pro', array($f['setting:pay_style']['package'], $f['setting:pay_pwyw']['package'], $f['setting:pay_delayed_actions']['package'], $f['setting:pay_style_mobile']['package']), array('free_plus', 'free_plus', 'free_plus', 'pro'));
$t('pay_delayed_actions lists the pay_act_* keys', in_array('pay_act_admin_mail', $f['setting:pay_delayed_actions']['form_keys'], true), true);
$g = $cap['payment_gateways']['efbmock'];
$t('payment_gateways.efbmock: configured, mode, methods, supports', array($g['configured'], $g['mode'], count($g['methods']), $g['supports']['webhook_auth']), array(array('test' => true, 'live' => true), 'test', 2, 'signature'));
$t('payment_keys.efbmock = true; stripe/paypal still reported', array($cap['payment_keys']['efbmock'], array_key_exists('stripe', $cap['payment_keys'])), array(true, true));
$t('no secret anywhere in the registry', preg_match('/mock_(test|live)_ABC|whs_unit|enc:v1/', json_encode($cap)), 0);
$t('payments marker for the manifest', $cap['payments']['spec'], 'PAY00');
$free = Builder_Api::capabilities(array('package' => 2, 'settings' => (object) array()));
$t('on Free the gateway field is closed (reason plan)', array($free['features']['field:efbmock']['available_now'], $free['features']['field:efbmock']['reason']), array(false, 'plan'));

function is_rtl() { return false; }
$tpl = Builder_Api::field_templates('efbmock', array('text' => array()));
$t('field_templates("efbmock"): one row of the gateway type and a form_patch', array(count($tpl['rows']), $tpl['rows'][0]['type'], $tpl['form_patch']['getway'], $tpl['form_patch']['type']), array(1, 'efbmock', 'efbmock', 'payment'));
$m = Builder_Api::field_templates('mollie', array('text' => array()));
$t('field_templates("mollie") without its add-on → addon_required', $m instanceof WP_Error ? $m->get_error_code() : $m, 'addon_required');

$fn = file_get_contents(__DIR__ . '/../includes/functions.php');
$js = file_get_contents(__DIR__ . '/../includes/admin/assets/js/admin-efb.js');
$ok = true;
foreach (array('AdnMOL', 'AdnPPG', 'AdnTAP', 'AdnOMS', 'AdnPTB', 'AdnPMB', 'AdnXND') as $k) {
	if (!preg_match("/'{$k}' => 3,/", $fn)) $ok = false;
	if (strpos($js, "'{$k}'") === false) $ok = false;
	if (strpos($fn, "'{$k}', //") === false) $ok = false;
}
$t('the seven gateway add-ons: Free Plus in addon_required_package_efb(), in its JS twin and in the add-on key list', $ok, true);
$t('add-on names have phrases (ADDON_LABEL_KEYS → text_efb)', strpos($fn, '"mollieTAddon" =>') !== false && Builder_Api::ADDON_LABEL_KEYS['AdnXND'] === 'xenditTAddon', true);
echo "\nRESULTS: {$passed} passed, {$failed} failed\n";
exit($failed ? 1 : 0);
