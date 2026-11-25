<?php
/**
 * QA1-023 (FX-ADMIN): the saved e-mail template keeps no absolute address of the plugin.
 *  - Emsfb::email_template_placeholder_efb() turns this plugin's address of any host (plain, the settings' @efb@ encoding,
 *    URL-encoded in the designer's EFBDATA) into {efb_plugin_url}; other addresses stay;
 *  - the default template stores {efb_plugin_url} (it stored EMSFB_PLUGIN_URL: a moved site kept the old host);
 *  - the sender resolves it (class-email-handler.php), the designer swaps it both ways, the update migrates once.
 *
 * Run: php tests/test-email-template-plugin-url.php
 */
$root = dirname(__DIR__);
$pass = 0; $fail = 0;
function t($n, $ok, $d = '') { global $pass, $fail; if ($ok) { $pass++; echo "[PASS] $n\n"; } else { $fail++; echo "[FAIL] $n — " . json_encode($d) . "\n"; } }
$src = (string) file_get_contents($root . '/includes/class-Emsfb.php');
preg_match('/public static function email_template_placeholder_efb\(\$t\) \{.*?\n    \}\n/s', $src, $m);
eval('class FX_Tpl { ' . ($m[0] ?? 'public static function email_template_placeholder_efb($t) { return $t; }') . ' }');
$old = '<img src="http:@efb@127.0.0.1:8088@efb@wp-content@efb@plugins@efb@easy-form-builder@efb@public@efb@assets@efb@images@efb@email_template1.png"> <a href="https:@efb@example.com@efb@page">x</a>';
t('the settings\' @efb@ address of another host becomes the placeholder; another address stays', FX_Tpl::email_template_placeholder_efb($old) === '<img src="{efb_plugin_url}public@efb@assets@efb@images@efb@email_template1.png"> <a href="https:@efb@example.com@efb@page">x</a>', FX_Tpl::email_template_placeholder_efb($old));
t('a plain address too', FX_Tpl::email_template_placeholder_efb("<img src='https://old.example/wp-content/plugins/easy-form-builder/public/x.png'>") === "<img src='{efb_plugin_url}public/x.png'>");
t('and the designer\'s URL-encoded copy (EFBDATA)', FX_Tpl::email_template_placeholder_efb('%22src%22%3A%22http%3A%2F%2F127.0.0.1%3A8088%2Fwp-content%2Fplugins%2Feasy-form-builder%2Fpublic%2Fa.png') === '%22src%22%3A%22%7Befb_plugin_url%7Dpublic%2Fa.png');
t('the default template stores {efb_plugin_url} (no EMSFB_PLUGIN_URL)', strpos($src, "\$logo_url = '{efb_plugin_url}public/assets/images/email_template1.png';") !== false);
t('the update migrates the stored template once', strpos($src, 'self::email_template_placeholder_efb($tpl_efb->emailTemp)') !== false);
t('the sender resolves the placeholder', strpos((string) file_get_contents($root . '/includes/class-email-handler.php'), "array('/', EMSFB_PLUGIN_URL)") !== false);
$js = (string) file_get_contents($root . '/includes/admin/assets/js/email-template-builder-efb.js');
t('the designer shows the real address and saves the placeholder', strpos($js, 'swap_efb(textarea.value, PH_efb, PU_efb)') !== false && strpos($js, "swap_efb(html + '\\n<!-- EFBDATA:' + encoded + ' -->', PU_efb, PH_efb)") !== false);
echo "\nRESULTS: {$pass} passed, {$fail} failed\n";
exit($fail ? 1 : 0);
