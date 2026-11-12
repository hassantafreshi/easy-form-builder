<?php
/**
 * PAY00 §8.2: Money / Currency. ISO 4217 exponents, decimal string <-> smallest unit without
 * floating point, half-up rounding, KWD with three decimals, JPY with none, Persian digits,
 * the efb_currency_exponent filter for a gateway's exception, and display formatting.
 *
 * Run: php tests/test-pay-money.php
 */
define('ABSPATH', __DIR__ . '/');
$passed = 0; $failed = 0;
require __DIR__ . '/pay-test-db.php';
require __DIR__ . '/../includes/payments/class-Emsfb-payment-money.php';
use Emsfb\Payments\Money;
use Emsfb\Payments\Currency;
$t = 'efb_pay_t';
$t('JPY, KRW, VND, CLP have no decimals', array(Currency::exponent('JPY'), Currency::exponent('krw'), Currency::exponent('VND'), Currency::exponent('CLP')), array(0, 0, 0, 0));
$t('BHD, KWD, OMR, JOD, TND have three', array(Currency::exponent('BHD'), Currency::exponent('KWD'), Currency::exponent('OMR'), Currency::exponent('JOD'), Currency::exponent('TND')), array(3, 3, 3, 3, 3));
$t('EUR, USD, THB, IRR default to two', array(Currency::exponent('EUR'), Currency::exponent('usd'), Currency::exponent('THB'), Currency::exponent('IRR')), array(2, 2, 2, 2));
$t("'12.5' KWD = 12500", Money::from_decimal('12.5', 'KWD'), 12500);
$t("'12.5' EUR = 1250", Money::from_decimal('12.5', 'EUR'), 1250);
$t("'500' JPY = 500", Money::from_decimal('500', 'JPY'), 500);
$t("'0.005' EUR rounds half up to 1", Money::from_decimal('0.005', 'EUR'), 1);
$t("'0.004' EUR rounds down to 0", Money::from_decimal('0.004', 'EUR'), 0);
$t('0.1 + 0.2 as floats still gives exact cents', Money::from_decimal(0.1 + 0.2, 'USD'), 30);
$t('19.99 as a float = 1999 (no 1998)', Money::from_decimal(19.99, 'USD'), 1999);
$t("Persian digits '۱۲٫۵' USD = 1250", Money::from_decimal('۱۲٫۵', 'USD'), 1250);
$t("comma decimal '1,25' EUR = 125", Money::from_decimal('1,25', 'EUR'), 125);
$t('garbage refused', array(Money::from_decimal('abc', 'EUR'), Money::from_decimal('1e3', 'EUR'), Money::from_decimal(true, 'EUR'), Money::from_decimal(null, 'EUR'), Money::from_decimal(array(1), 'EUR')), array(null, null, null, null, null));
$t('more than 15 integer digits refused', Money::from_decimal('9999999999999999', 'EUR'), null);
$t('12500 KWD → "12.500"', Money::to_decimal_string(12500, 'KWD'), '12.500');
$t('1250 EUR → "12.50", 5 → "0.05", -5 → "-0.05"', array(Money::to_decimal_string(1250, 'EUR'), Money::to_decimal_string(5, 'EUR'), Money::to_decimal_string(-5, 'EUR')), array('12.50', '0.05', '-0.05'));
$t('500 JPY → "500"', Money::to_decimal_string(500, 'JPY'), '500');
$t('round trip for 1000 random amounts in four currencies', (function () {
	foreach (array('EUR', 'KWD', 'JPY', 'CLF') as $c) for ($i = 0; $i < 250; $i++) { $m = mt_rand(0, 99999999); if (Money::from_decimal(Money::to_decimal_string($m, $c), $c) !== $m) return false; }
	return true;
})(), true);
add_filter('efb_currency_exponent', function ($e, $iso, $gw) { return $iso === 'IDR' && $gw === 'xendit' ? 0 : $e; });
$t('filter: IDR without decimals for one gateway only', array(Currency::exponent('IDR', 'xendit'), Currency::exponent('IDR')), array(0, 2));
$t('filter result outside 0..4 is ignored', (function () { add_filter('efb_currency_exponent', function () { return 9; }); return Currency::exponent('EUR'); })(), 2);
$f = Money::format(1250, 'EUR', 'en_US');
$t('format shows the amount with two decimals', strpos($f, '12.50') !== false, true);
echo "\nRESULTS: {$passed} passed, {$failed} failed\n";
exit($failed ? 1 : 0);
