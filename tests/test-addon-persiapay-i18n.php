<?php
/**
 * QA3-011: the ZarinPal gateway of the Persia Pay add-on (persiapay/class-Emsfb-zarinpal-gateway.php) had its settings
 * label, help, Test connection messages and description in Persian, untranslatable. Now:
 *  - English source strings through gettext in the add-on's own text domain efb-persiapay, each with a translators
 *    comment, in the add-on's phrase list (languages/efb-persiapay.pot);
 *  - the Persian they had is in the add-on's fa_IR file (efb-persiapay-fa_IR .po / .mo / .l10n.php), so a Persian site
 *    reads the same words as before (checked through the compiled .l10n.php);
 *  - a total with a fraction of a rial is refused before anything is stored (Gateway::amount_problem(), reason config).
 * Each check fails on the add-on before this change.
 *
 * Run: php tests/test-addon-persiapay-i18n.php   (EFB_ADDONS_REPO: the add-ons repository; default the vendor/ links)
 */
define('ABSPATH', __DIR__ . '/');
$passed = 0; $failed = 0;
$addons = getenv('EFB_ADDONS_REPO') ?: dirname((string) @readlink(__DIR__ . '/../vendor/persiapay'));
if (!is_dir($addons . '/persiapay')) $addons = __DIR__ . '/../vendor';
$dir = $addons . '/persiapay';
// gettext of the test: the compiled fa_IR file when $GLOBALS['efb_fa'] is on (what WordPress loads on a Persian site).
$GLOBALS['efb_fa'] = false;
$GLOBALS['efb_domains'] = array();
function __($s, $d = 'default') {
	$GLOBALS['efb_domains'][] = $d;
	if (!$GLOBALS['efb_fa'] || $d !== 'efb-persiapay') return $s;
	static $m = null;
	if ($m === null) { $f = $GLOBALS['efb_l10n']; $a = is_readable($f) ? include $f : array(); $m = isset($a['messages']) ? $a['messages'] : array(); }
	return isset($m[$s]) && $m[$s] !== '' ? $m[$s] : $s;
}
$GLOBALS['efb_l10n'] = $dir . '/languages/efb-persiapay-fa_IR.l10n.php';
require __DIR__ . '/pay-test-db.php';
$t = 'efb_pay_t';
efb_pay_test_load();
require_once $dir . '/class-Emsfb-zarinpal-gateway.php';
use Emsfb\Payments\Zarinpal_Gateway;
use Emsfb\Payments\Settings;

$src = (string) file_get_contents($dir . '/class-Emsfb-zarinpal-gateway.php');
preg_match_all('/[\x{0600}-\x{06FF}]+/u', $src, $fa);
$t('the gateway\'s code holds no Persian text (old: label, help, Test connection messages, description)', $fa[0], array());
preg_match_all("/__\\(\\s*'(?:[^'\\\\]|\\\\.)*'\\s*,\\s*'([^']+)'\\s*\\)/", $src, $doms);
$t('...its texts are gettext strings of the add-on\'s own text domain efb-persiapay', array_values(array_unique($doms[1])), array('efb-persiapay'));
$pot = (string) @file_get_contents($dir . '/languages/efb-persiapay.pot');
preg_match_all("/__\\(\\s*'((?:[^'\\\\]|\\\\.)*)'\\s*,\\s*'efb-persiapay'/", $src, $ids);
$missing = array_values(array_filter($ids[1], function ($m) use ($pot) { return strpos($pot, 'msgid "' . addcslashes(str_replace("\\'", "'", $m), '"\\') . '"') === false; }));
$t('...each is in the phrase list efb-persiapay.pot, each with a translators comment', array(count($ids[1]) >= 9, $missing, substr_count($pot, '#. translators:') >= count(array_unique($ids[1]))), array(true, array(), true));
$g = new Zarinpal_Gateway();
$sch = $g->settings_schema();
$t('English site: the settings field reads "Merchant ID" with English help', array($sch[0]['label'], $sch[0]['help']), array('Merchant ID', 'ZarinPal panel → Payment gateway → Merchant ID (36 characters).'));
$GLOBALS['efb_fa'] = true;
$sch = $g->settings_schema();
$s = Settings::load('zarinpalgate');
$nokey = $g->test_connection($s, 'test');
$t('Persian site (the add-on\'s fa_IR file): the label, the help and "no merchant ID" read as before', array($sch[0]['label'], $sch[0]['help'], $nokey->message), array('Merchant ID (مرچنت کد)', 'پنل زرین‌پال ← درگاه پرداخت ← مرچنت کد (۳۶ نویسه).', 'مرچنت کد وارد نشده است.'));
$fa_po = (string) @file_get_contents($dir . '/languages/efb-persiapay-fa_IR.po');
$t('...the fa_IR .po, .mo and .l10n.php are shipped, and locales.json lists fa_IR', array(strpos($fa_po, 'msgstr "به محیط آزمایشی زرین‌پال (sandbox) وصل شد."') !== false, is_readable($dir . '/languages/efb-persiapay-fa_IR.mo'), in_array('fa_IR', (array) json_decode((string) @file_get_contents($dir . '/languages/locales.json'), true)['locales'], true)), array(true, true, true));
$GLOBALS['efb_fa'] = false;
$r = $g->amount_problem(150000050, 'IRR', $s);
$t('a total with a fraction of a rial: amount_fraction with reason config (the owner is told; nothing stored); whole rials pass', array($r ? $r->error : null, $r ? $r->reason : null, $r ? $r->visitor_message : null, $g->amount_problem(150000000, 'IRR', $s)), array('amount_fraction', 'config', '', null));

echo "\nRESULTS: {$passed} passed, {$failed} failed\n";
exit($failed ? 1 : 0);
