<?php
/**
 * B4 hand-off (A11Y-01): the names and text alternatives admin-efb.js added at runtime (efbA11yRepairEfb(), a
 * MutationObserver on every screen) are in the markup that prints them, and the bridge is gone; and the security
 * add-on is "Human Shield" everywhere a user reads its name (SET-01).
 *
 *  - class-Emsfb-panel.php: the header logo alt="" (beside its words), the account and documentation icon links named
 *    "(opens in a new tab)" with target="_blank" (it was "blank", a named window) and rel="noopener", "Show more",
 *    Close and Back named, their icons aria-hidden;
 *  - val-efb.js: the palette's category list draws its rule (efb-list-rule), no <hr> inside the <ul>;
 *  - entries-efb.css: on a phone the head buttons' words leave the screen, not the accessibility tree;
 *  - admin-efb.js: no efbA11yRepairEfb any more;
 *  - "Form Security & Spam Protection" is "Human Shield" in the add-on list (class-Emsfb.php), its card title
 *    (TAdnHSH), its label in the add-on messages, the compatibility list and the "disabled" message.
 * The screens themselves: e2e b4-a11y (axe, 0 serious or critical, en 1280, fa_IR and ar 375), green without the bridge.
 *
 * Run: php tests/test-a11y-markup-moved.php
 */
$passed = 0; $failed = 0;
function t($name, $actual, $expected) {
	global $passed, $failed;
	if ($actual === $expected) { echo "[PASS] {$name}\n"; $passed++; return; }
	echo "[FAIL] {$name}: expected " . json_encode($expected) . ', got ' . json_encode($actual) . "\n"; $failed++;
}
$r = dirname(__DIR__) . '/includes/';
$panel = (string) file_get_contents($r . 'admin/class-Emsfb-panel.php');
t('panel: the header logo is decorative (alt="")', strpos($panel, "logo-easy-form-builder.svg') ?>\" class=\"efb logo efb\" alt=\"\">") !== false, true);
t('panel: the account link opens a new tab properly and is named', (bool) preg_match('#href="https://whitestudio\.team/login" target="_blank" rel="noopener" aria-label="<\?php [^?]*esc_attr__\(\'Your WhiteStudio account \(opens in a new tab\)\'#', $panel), true);
t('panel: the documentation link too', (bool) preg_match('#href="https://whitestudio\.team/documents" target="_blank" rel="noopener" aria-label="<\?php [^?]*esc_attr__\(\'Easy Form Builder documentation \(opens in a new tab\)\'#', $panel), true);
t('panel: no target="blank" left', strpos($panel, 'target="blank"'), false);
t('panel: "Show more" names the icon button under the forms list', (bool) preg_match('/id="more_emsFormBuilder"[^>]*aria-label="<\?php [^?]*esc_attr__\(\'Show more\'/', $panel), true);
t('panel: the settings dialog\'s Close and the Back button are named', (bool) preg_match('/id="settingModalEfb-close"[^>]*aria-label="<\?php echo esc_attr__\(\'Close\'/', $panel) && (bool) preg_match('/id="back_emsFormBuilder"[^>]*aria-label="<\?php echo esc_attr__\(\'Back\'/', $panel), true);
t('panel: the header and button icons are aria-hidden', substr_count($panel, 'aria-hidden="true"></i></a>') >= 3, true);

$val = (string) file_get_contents($r . 'admin/assets/js/val-efb.js');
preg_match('/<ul class="efb my-2 row efb-list-rule" id="listCatEfb">(.*?)<\/ul>/s', $val, $m);
t('val-efb.js: the palette\'s category list draws its rule (efb-list-rule)', isset($m[1]), true);
t('val-efb.js: no <hr> inside that list', isset($m[1]) && stripos($m[1], '<hr') === false, true);

$css = (string) file_get_contents($r . 'admin/assets/css/entries-efb.css');
t('entries-efb.css: the head buttons\' words are visually hidden on a phone, not display:none', strpos($css, '.efb-ent-headbtns .btn span { display: none; }') === false && strpos($css, '.efb-ent-headbtns .btn span { position: absolute; width: 1px; height: 1px;') !== false, true);

$adm = (string) file_get_contents($r . 'admin/assets/js/admin-efb.js');
t('admin-efb.js: the runtime bridge efbA11yRepairEfb() is gone', (bool) preg_match('/function efbA11yRepairEfb|efbA11yRepairEfb\(\)/', $adm), false);

$main = (string) file_get_contents($r . 'class-Emsfb.php');
t('class-Emsfb.php: AdnHSH is "Human Shield"', strpos($main, "'AdnHSH' => 'Human Shield',") !== false, true);
$fn = (string) file_get_contents($r . 'functions.php');
t('functions.php: the add-on card title TAdnHSH and the add-on label are "Human Shield"', strpos($fn, "esc_html__('Human Shield','easy-form-builder')") !== false && strpos($fn, "'AdnHSH' => esc_html__( 'Human Shield', 'easy-form-builder' ),") !== false, true);
$left = array();
foreach (array('class-Emsfb.php', 'functions.php', 'phrases.php', 'class-Emsfb-addon-compatibility.php', 'class-Emsfb-addon-catalog.php') as $f) {
	$code = '';
	foreach (token_get_all((string) file_get_contents($r . $f)) as $tk) if (!is_array($tk) || !in_array($tk[0], array(T_COMMENT, T_DOC_COMMENT), true)) $code .= is_array($tk) ? $tk[1] : $tk;
	if (strpos($code, 'Form Security & Spam Protection') !== false) $left[] = $f;
}
t('no "Form Security & Spam Protection" left in what users read (comments aside)', $left, array());

echo "\nRESULTS: {$passed} passed, {$failed} failed\n";
exit($failed ? 1 : 0);
