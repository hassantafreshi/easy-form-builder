<?php
/**
 * FX-ADMIN (4.3.2): small source checks of fixes that have no behaviour of their own to drive.
 *  - capabilities.payments.migration.backup says what Move back restores now: the form before the latest move (only the
 *    field change is undone), not "original JSON per form" (FX-PAY hand-off).
 *
 * Run: php tests/test-fx-admin-source.php
 */
$root = dirname(__DIR__);
$pass = 0;
$fail = 0;
function t($name, $ok, $detail = '') {
	global $pass, $fail;
	if ($ok) { $pass++; echo "[PASS] $name\n"; } else { $fail++; echo "[FAIL] $name" . ($detail !== '' ? ' — ' . json_encode($detail) : '') . "\n"; }
}
$api = (string) file_get_contents($root . '/includes/class-Emsfb-builder-api.php');
t('capabilities: the migration backup is "the form before the latest move"', strpos($api, "'backup' => 'the form before the latest move'") !== false && strpos($api, 'original JSON per form') === false);
// QA1-022: the onboarding's group name is a phrase, its step number in the site's digits; no English aria-label left.
$val = (string) file_get_contents($root . '/includes/admin/assets/js/val-efb.js');
t('onboarding: aria-label from the phrase onboardingActions, the step through efb_admin_date_efb()', strpos($val, "aria-label=\"' + t('onboardingActions', 'Setup actions') + '\"") !== false && strpos($val, "efb_admin_date_efb('2')") !== false);
t('val-efb.js: no aria-label written as English text', preg_match('/aria-label="[A-Za-z][A-Za-z ]+"/', $val) === 0);
// QA1-010: the settings save refuses a number out of its range (invalid_<key>) before anything is stored.
$adm = (string) file_get_contents($root . '/includes/admin/class-Emsfb-admin.php');
preg_match('/public static function invalid_setting_number_efb\(array \$m, \$ac = null\) \{.*?\n    \}\n/s', $adm, $fm);
eval('class FX_Num { ' . str_replace('public static function', 'public static function', $fm[0] ?? 'public static function invalid_setting_number_efb(array $m, $ac = null) { return "missing"; }') . ' }');
t('form_rev_keep "abc" / "-1" / "99" refused (were stored as 0 / 1 / 50)', FX_Num::invalid_setting_number_efb(array('form_rev_keep' => 'abc')) === array('form_rev_keep', 0, 50) && FX_Num::invalid_setting_number_efb(array('form_rev_keep' => '-1')) !== null && FX_Num::invalid_setting_number_efb(array('form_rev_keep' => '99')) !== null);
t('spam_min_time "-4", trash_days "-1", prv_days "4000" refused; Persian ۳۰ and 0 accepted', FX_Num::invalid_setting_number_efb(array('spam_min_time' => '-4'))[0] === 'spam_min_time' && FX_Num::invalid_setting_number_efb(array('trash_days' => '-1'))[0] === 'trash_days' && FX_Num::invalid_setting_number_efb(array('prv_days' => '4000'))[0] === 'prv_days' && FX_Num::invalid_setting_number_efb(array('prv_days' => '۳۰', 'trash_days' => 0, 'reply_rl_ip' => '0')) === null);
t('a stored value that is out of range (an older version) does not block a save of something else', FX_Num::invalid_setting_number_efb(array('spam_min_time' => ''), (object) array('spam_min_time' => '')) === null);
t('the save checks before its loop and answers invalid_<key>', strpos($adm, "\$bad_efb = is_array(\$m) ? self::invalid_setting_number_efb(\$m, \$ac) : null;") !== false && strpos($adm, "'code' => 'invalid_' . \$bad_efb[0]") !== false);
// I18N-05 / QA1-015: the Pro price comes from the server with a placeholder phrase; the Pro box lists Pro's own features.
$GLOBALS['fx_loc'] = 'fa_IR';
if (!function_exists('get_locale')) { function get_locale() { return $GLOBALS['fx_loc']; } }
if (!function_exists('get_option')) { function get_option($k, $d = null) { return $d; } }
if (!function_exists('apply_filters')) { function apply_filters($h, $v) { return $v; } }
if (!function_exists('__')) { function __($s, $d = null) { return $s; } }
$bapi = (string) file_get_contents($root . '/includes/class-Emsfb-builder-api.php');
preg_match('/public static function pro_price\(\) \{.*?\n\t\}\n/s', $bapi, $pm);
eval('namespace Emsfb; class FX_Price { ' . ($pm[0] ?? 'public static function pro_price() { return array(); }') . ' }');
$fa = \Emsfb\FX_Price::pro_price();
t('fa_IR: the price is in Toman, written by the server (Persian digits when intl is there)', $fa['currency'] === 'IRT' && strpos($fa['text'], 'Toman') !== false && (class_exists('NumberFormatter') ? strpos($fa['text'], '۱٬۳۰۰٬۰۰۰') !== false : true), $fa);
$GLOBALS['fx_loc'] = 'en_US';
$en = \Emsfb\FX_Price::pro_price();
t('en_US: 27 USD, written "$27"', $en['amount'] === 27 && $en['currency'] === 'USD' && (class_exists('NumberFormatter') ? $en['text'] === '$27' : $en['text'] !== ''), $en);
$fn = (string) file_get_contents($root . '/includes/functions.php');
t('the price phrase keeps a placeholder ("%s/year", no hard-coded "$NN")', strpos($fn, "esc_html__('%s/year','easy-form-builder')") !== false && strpos($fn, '$NN/year') === false);
$admjs = (string) file_get_contents($root . '/includes/admin/assets/js/admin-efb.js');
t('admin-efb.js hard-codes no price (pro_price_efb = 27 and "1,300,000" are gone)', strpos($admjs, 'pro_price_efb') === false && strpos($admjs, '1,300,000') === false);
t('the Pro box lists the registry\'s Pro features (package pro), not "payment integrations, conditional logic, multi-step forms, file uploads"', strpos($admjs, "efb_var.capabilities.features[k].package === 'pro' && !efb_var.capabilities.features[k].addon") !== false && strpos($fn . (string) file_get_contents($root . '/includes/class-email-handler.php'), 'multi-step forms, file uploads') === false);
echo "\nRESULTS: {$pass} passed, {$fail} failed\n";
exit($fail ? 1 : 0);
