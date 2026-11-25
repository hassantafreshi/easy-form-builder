<?php
/**
 * QA3-014 (FX-ADMIN): a priced choice or a calculated total is written the way the payment summary writes the same
 * amount: Formbuilder::formatPrice_efb() goes through Payments\Money::format() (the site language, the currency's minor
 * unit) instead of its own symbol table (EGP "£" against "ج.م.", JPY "1,500" against "1’500"); in the browser
 * updateTotal_efb() (new-efb.js) calls efbPayCoreEfb.format() with the payment runtime's locale and the gateway's minor
 * units. Without intl the old table is still the fallback.
 *
 * Run: php tests/test-price-format-shared.php
 */
define('ABSPATH', '/');
$GLOBALS['loc'] = 'en_US';
function get_locale() { return $GLOBALS['loc']; }
function number_format_i18n($n, $d = 0) { return number_format($n, $d); }
function is_rtl() { return false; }
$root = dirname(__DIR__);
require $root . '/includes/payments/class-Emsfb-payment-money.php';
require $root . '/includes/class-Emsfb-formbuilder.php';
$pass = 0; $fail = 0;
function t($n, $ok, $d = '') { global $pass, $fail; if ($ok) { $pass++; echo "[PASS] $n\n"; } else { $fail++; echo "[FAIL] $n — " . json_encode($d, JSON_UNESCAPED_UNICODE) . "\n"; } }
use Emsfb\Formbuilder as FB;
use Emsfb\Payments\Money;
if (!class_exists('NumberFormatter')) {
	echo "[SKIP] intl is not installed: the old table is the fallback\n";
} else {
	foreach (array(array('ar', 'EGP', 1500), array('de_CH', 'JPY', 1500), array('en_US', 'USD', 12.5), array('fa_IR', 'IRR', 250000), array('de_DE', 'EUR', '19.9')) as $c) {
		$GLOBALS['loc'] = $c[0];
		$want = Money::format(Money::from_decimal($c[2], $c[1]), $c[1], $c[0]);
		t("{$c[0]} {$c[1]} {$c[2]}: the price as the payment summary writes it ($want)", FB::formatPrice_efb($c[2], $c[1]) === $want, FB::formatPrice_efb($c[2], $c[1]));
	}
	$GLOBALS['loc'] = 'ar';
	t('EGP in Arabic: "ج.م." (the old table wrote "£")', strpos(FB::formatPrice_efb(1500, 'EGP'), 'ج.م.') !== false && strpos(FB::formatPrice_efb(1500, 'EGP'), '£') === false, FB::formatPrice_efb(1500, 'EGP'));
	t('a value posted by a browser that is not a number still gives 0, never an error', FB::formatPrice_efb('abc', 'USD') !== '');
}
$js = (string) file_get_contents($root . '/includes/admin/assets/js/new-efb.js');
t('new-efb.js updateTotal_efb() writes the total with efbPayCoreEfb.format (the runtime\'s locale, the gateway\'s minor units)', strpos($js, 'pc.format(mi, cur, pv.locale') !== false && strpos($js, 'pc.fromDecimal(Number(i), cur, ex)') !== false);
echo "\nRESULTS: {$pass} passed, {$fail} failed\n";
exit($fail ? 1 : 0);
