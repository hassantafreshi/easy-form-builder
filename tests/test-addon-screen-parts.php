<?php
/**
 * SET-01 phase 1 (batch B4): the shared parts of an add-on's settings screen in core, which the add-ons use (ADD-01):
 * Emsfb\Addon_Catalog::screen_header() and ::save_bar(), and the catalogue's add-on screens (SCREENS, screen_url(),
 * efb_var.addon_catalog[code].screen) that Settings › Captchas links to for Human Shield. Without WordPress (stubs).
 *
 * BUG: Stripe / PayPal dashboards, Human Shield and Address search each had their own header and save pattern; core had
 * no shared one (the add-ons copied markup). Checked:
 *  - the header: a link back (an arrow that points back in either direction), the WordPress title (h1 + wp-header-end,
 *    so notices go under it), page-title actions, Help only when the add-on has a help page (a new tab, named for
 *    screen readers), one line on what the screen does; everything escaped; its style printed once;
 *  - the save bar: WordPress' primary button (submit, its name and value), sticky at the bottom, a status line (role
 *    status, polite) tied to the button's id;
 *  - Human Shield is "Human Shield" in the catalogue, and its screen's address is in the JS mirror only while the add-on
 *    registered that screen.
 *
 * Run: php tests/test-addon-screen-parts.php
 */
define('ABSPATH', __DIR__ . '/');
$GLOBALS['t_rtl'] = false;
function get_locale() { return 'en_US'; }
function is_rtl() { return $GLOBALS['t_rtl']; }
function esc_html($s) { return htmlspecialchars((string) $s, ENT_QUOTES); }
function esc_attr($s) { return htmlspecialchars((string) $s, ENT_QUOTES); }
function esc_url($s) { return htmlspecialchars((string) $s, ENT_QUOTES); }
function __($s, $d = '') { return $s; }
function admin_url($p = '') { return 'http://site.test/wp-admin/' . $p; }
require dirname(__DIR__) . '/includes/class-Emsfb-addon-catalog.php';
use Emsfb\Addon_Catalog as C;

$passed = 0; $failed = 0;
function t($name, $actual, $expected) {
	global $passed, $failed;
	if ($actual === $expected) { $passed++; echo "[PASS] {$name}\n"; return; }
	$failed++; echo "[FAIL] {$name}\n  expected: " . var_export($expected, true) . "\n  actual:   " . var_export($actual, true) . "\n";
}

$ok = method_exists('\\Emsfb\\Addon_Catalog', 'screen_header') && method_exists('\\Emsfb\\Addon_Catalog', 'save_bar');
t('core has the shared header and save bar of an add-on screen', $ok, true);
if (!$ok) { echo "\nRESULTS: {$passed} passed, " . ($failed) . " failed\n"; exit(1); }

$h = C::screen_header(array('title' => 'Older PayPal payments <b>', 'code' => 'AdnPAP', 'back_url' => 'http://site.test/wp-admin/admin.php?page=Emsfb_payments', 'back_label' => 'Back to Payments', 'desc' => 'Payments taken before 4.3.2 & their refunds.', 'actions' => array(array('url' => 'http://site.test/x.csv', 'label' => 'Export CSV'))));
t('header: the link back (an arrow pointing back, left to right), the escaped title as WordPress\' h1, then wp-header-end', array(strpos($h, '<p class="efb-addon-back"><a href="http://site.test/wp-admin/admin.php?page=Emsfb_payments"><span aria-hidden="true">&larr;</span> Back to Payments</a></p>') !== false, strpos($h, '<h1 class="wp-heading-inline">Older PayPal payments &lt;b&gt;</h1>') !== false, strpos($h, '<hr class="wp-header-end">') !== false), array(true, true, true));
t('header: an action beside the title (page-title-action)', strpos($h, ' <a class="page-title-action" href="http://site.test/x.csv">Export CSV</a>') !== false, true);
t('header: Help to the add-on\'s own page, in a new tab, named for screen readers', (bool) preg_match('#<a class="page-title-action efb-addon-help" href="https://whitestudio\.team/document/how-to-setup-paypal-payment-in-easy-form-builder/" target="_blank" rel="noopener noreferrer">Help<span class="screen-reader-text"> \(opens in a new tab\)</span></a>#', $h), true);
t('header: the line on what the screen does, escaped, after the title', strpos($h, '<hr class="wp-header-end"><p class="description efb-addon-desc">Payments taken before 4.3.2 &amp; their refunds.</p>') !== false, true);
t('header: the style once per page (an add-on screen may not load the plugin\'s stylesheets)', array(substr_count($h, '<style id="efb-addon-screen-css">'), substr_count(C::screen_header(array('title' => 'x')), '<style')), array(1, 0));
$h2 = C::screen_header(array('title' => 'Google Sheets', 'code' => 'AdnGoS'));
t('header: no Help for an add-on without a page (HELP-01), no back link without an address', array(strpos($h2, 'efb-addon-help'), strpos($h2, 'efb-addon-back')), array(false, false));
$GLOBALS['t_rtl'] = true;
t('header: right to left the arrow points right (back)', strpos(C::screen_header(array('title' => 'x', 'back_url' => 'http://a.test/')), '&rarr;') !== false, true);

$b = C::save_bar(array('name' => 'efb_save', 'value' => 'yes', 'id' => 'hsSave'));
t('save bar: WordPress\' primary submit button with its name and value, "Save Changes" by default', strpos($b, '<button type="submit" class="button button-primary" id="hsSave" name="efb_save" value="yes">Save Changes</button>') !== false, true);
t('save bar: a status line for the screen\'s script (role status, polite), tied to the button', strpos($b, '<span class="efb-addon-savebar-note" id="hsSave-status" role="status" aria-live="polite"></span>') !== false, true);
t('save bar: sticky at the bottom of the screen (Save stays in sight on a long screen)', strpos((string) (new ReflectionMethod('\\Emsfb\\Addon_Catalog', 'screen_css'))->getDocComment() . file_get_contents(dirname(__DIR__) . '/includes/class-Emsfb-addon-catalog.php'), '.efb-addon-savebar{position:sticky;bottom:0') !== false, true);
t('save bar: a label of the screen\'s own, escaped', strpos(C::save_bar(array('label' => 'Save & test')), '>Save &amp; test</button>') !== false, true);

// The catalogue: Human Shield's name and screen.
t('the catalogue calls AdnHSH "Human Shield" (its menu and screen do too)', C::name('AdnHSH'), 'Human Shield');
$GLOBALS['submenu'] = array();
t('Human Shield\'s screen: none while the add-on has not registered it', C::js_mirror()['AdnHSH']['screen'], '');
$GLOBALS['submenu'] = array('Emsfb' => array(array('Panel', 'Emsfb', 'Emsfb'), array('Human Shield', 'Emsfb_human_shield_efb', 'Emsfb_human_shield_efb')));
t('… its address once it is in Easy Form Builder\'s menu (Settings › Captchas links to it)', C::js_mirror()['AdnHSH']['screen'], 'http://site.test/wp-admin/admin.php?page=Emsfb_human_shield_efb');
t('an add-on without a screen of its own in the list has none', C::js_mirror()['AdnGoS']['screen'], '');
// B8 hand-off: the Conditional Logic add-on implements the "logic" contract (1.1); the catalogue says so.
t('the catalogue names the contract of Conditional Logic (AdnSMF): logic', array(C::get('AdnSMF')['contract'], C::js_mirror()['AdnSMF']['contract']), array('logic', 'logic'));
// SET-01: the shared style is printed on the plugin's screens (admin_head), for add-on screens with the shared classes.
t('core prints the shared add-on screen style on the plugin\'s screens (admin_head), once', method_exists('\\Emsfb\\Addon_Catalog', 'print_screen_css') && strpos(file_get_contents(dirname(__DIR__) . '/includes/class-Emsfb-addon-catalog.php'), "add_action('admin_head', array(__CLASS__, 'print_screen_css'))") !== false, true);

echo "\nRESULTS: {$passed} passed, {$failed} failed\n";
exit($failed ? 1 : 0);
