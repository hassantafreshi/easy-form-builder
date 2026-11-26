<?php
/**
 * QA1-023 (FX-ADMIN): the saved e-mail template keeps no address of another host; final regression: and 4.3.1 still reads it.
 *  - Emsfb::email_template_placeholder_efb() turns this plugin's address of any host (plain, the settings' @efb@ encoding,
 *    URL-encoded in the designer's EFBDATA) into {efb_plugin_url}; other addresses stay;
 *  - Emsfb::email_template_absolute_efb() turns those addresses and the placeholder into this site's address, in the
 *    same encoding: the update migrates the stored template with it once, so a rollback to 4.3.1 (which cannot resolve
 *    the placeholder) shows the logo; the default template stores EMSFB_PLUGIN_URL;
 *  - the sender resolves the placeholder and maps another host's plugin address to this site; the designer shows a
 *    stored placeholder as the real address and saves the real address.
 *
 * Run: php tests/test-email-template-plugin-url.php
 */
$root = dirname(__DIR__);
$pass = 0; $fail = 0;
function t($n, $ok, $d = '') { global $pass, $fail; if ($ok) { $pass++; echo "[PASS] $n\n"; } else { $fail++; echo "[FAIL] $n — " . json_encode($d) . "\n"; } }
$src = (string) file_get_contents($root . '/includes/class-Emsfb.php');
preg_match('/public static function email_template_placeholder_efb\(\$t\) \{.*?\n    \}\n/s', $src, $m);
preg_match('/public static function email_template_absolute_efb\(\$t\) \{.*?\n    \}\n/s', $src, $m2);
define('EMSFB_PLUGIN_URL', 'http://new.example/wp-content/plugins/easy-form-builder/');
eval('class FX_Tpl { ' . ($m[0] ?? 'public static function email_template_placeholder_efb($t) { return $t; }') . ($m2[0] ?? 'public static function email_template_absolute_efb($t) { return $t; }') . ' }');
$old = '<img src="http:@efb@127.0.0.1:8088@efb@wp-content@efb@plugins@efb@easy-form-builder@efb@public@efb@assets@efb@images@efb@email_template1.png"> <a href="https:@efb@example.com@efb@page">x</a>';
t('the settings\' @efb@ address of another host becomes the placeholder; another address stays', FX_Tpl::email_template_placeholder_efb($old) === '<img src="{efb_plugin_url}public@efb@assets@efb@images@efb@email_template1.png"> <a href="https:@efb@example.com@efb@page">x</a>', FX_Tpl::email_template_placeholder_efb($old));
t('a plain address too', FX_Tpl::email_template_placeholder_efb("<img src='https://old.example/wp-content/plugins/easy-form-builder/public/x.png'>") === "<img src='{efb_plugin_url}public/x.png'>");
t('and the designer\'s URL-encoded copy (EFBDATA)', FX_Tpl::email_template_placeholder_efb('%22src%22%3A%22http%3A%2F%2F127.0.0.1%3A8088%2Fwp-content%2Fplugins%2Feasy-form-builder%2Fpublic%2Fa.png') === '%22src%22%3A%22%7Befb_plugin_url%7Dpublic%2Fa.png');
$abs = FX_Tpl::email_template_absolute_efb($old);
t('absolute: the settings\' @efb@ address of another host becomes this site\'s, @efb@-encoded; another address stays', $abs === '<img src="http:@efb@@efb@new.example@efb@wp-content@efb@plugins@efb@easy-form-builder@efb@public@efb@assets@efb@images@efb@email_template1.png"> <a href="https:@efb@example.com@efb@page">x</a>', $abs);
t('absolute: a stored {efb_plugin_url} (a template saved before this fix) becomes this site\'s address', FX_Tpl::email_template_absolute_efb("<img src='{efb_plugin_url}public/x.png'>") === "<img src='http://new.example/wp-content/plugins/easy-form-builder/public/x.png'>");
t('absolute: the URL-encoded copy (EFBDATA) too', FX_Tpl::email_template_absolute_efb('%22src%22%3A%22http%3A%2F%2Fold.example%2Fwp-content%2Fplugins%2Feasy-form-builder%2Fpublic%2Fa.png') === '%22src%22%3A%22http%3A%2F%2Fnew.example%2Fwp-content%2Fplugins%2Feasy-form-builder%2Fpublic%2Fa.png');
t('the default template stores this site\'s address (EMSFB_PLUGIN_URL), not the placeholder 4.3.1 cannot read', strpos($src, "\$logo_url = (defined('EMSFB_PLUGIN_URL') ? EMSFB_PLUGIN_URL : '{efb_plugin_url}') . 'public/assets/images/email_template1.png';") !== false);
t('the update migrates the stored template once, to this site\'s address (rollback-safe)', strpos($src, 'self::email_template_absolute_efb($tpl_efb->emailTemp)') !== false && strpos($src, 'self::email_template_placeholder_efb($tpl_efb->emailTemp)') === false);
$mail = (string) file_get_contents($root . '/includes/class-email-handler.php');
t('the sender resolves the placeholder and maps another host\'s plugin address to this site', strpos($mail, "array('/', EMSFB_PLUGIN_URL)") !== false && strpos($mail, "preg_replace('#https?://[^\"\\'\\s<>]*?/plugins/easy-form-builder/#', EMSFB_PLUGIN_URL, \$temp)") !== false);
$js = (string) file_get_contents($root . '/includes/admin/assets/js/email-template-builder-efb.js');
t('the designer shows a stored placeholder as the real address and saves the real address', strpos($js, 'swap_efb(textarea.value, PH_efb, PU_efb)') !== false && strpos($js, "swap_efb(html + '\\n<!-- EFBDATA:' + encoded + ' -->', PU_efb, PH_efb)") === false);
echo "\nRESULTS: {$pass} passed, {$fail} failed\n";
exit($fail ? 1 : 0);
