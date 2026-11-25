<?php
/**
 * B6 (PC-01, SEC-03, SEC-05 and the hand-offs): the Plugin Check / PHPCS hygiene of the shipped PHP, checked
 * without PHPCS so it runs everywhere. Each block fails on the code before B6.
 *
 *  1. i18n: a translatable string with a placeholder has a "translators:" comment right before the call (on the
 *     line above or before the call on its line), and a string with two placeholders numbers them (%1$s, %2$s).
 *  2. The discouraged PHP functions are gone (strip_tags with one argument, parse_url, date, mt_rand, the short echo
 *     tag) and unlink() is wp_delete_file() outside the streaming XLSX writer.
 *  3. SQL: no IN (...) list is glued from a PHP array (each value is a placeholder), and no query runs a variable
 *     prepared elsewhere (the prepare() call is inside the query call).
 *  4. Escaping: the screens print no raw variable for the notices, the Panel's menu and the Pro gate.
 *  5. Offloading: the Panel draws its charts with the bundled Chart.js (no Google Charts), the admin screens read the
 *     country lists from _Public::geo_base_url_efb() (the bundled copy), emsfb.php requires its class by __DIR__.
 *  6. validate_url_efb() (the e-mail template's style url()) answers for a URL with a host: it used a global that
 *     was never set, and in_array() on null throws a TypeError on PHP 8.
 *  7. The files nothing loads stay out (three PHP files, five images).
 *
 * Run: php tests/test-b6-plugin-check.php
 */
$root = dirname(__DIR__);
$passed = 0; $failed = 0;
function t($name, $ok, $info = '') {
	global $passed, $failed;
	if ($ok) { echo "[PASS] {$name}\n"; $passed++; return; }
	echo "[FAIL] {$name}" . ($info !== '' ? ": {$info}" : '') . "\n"; $failed++;
}

$files = array();
$git = @shell_exec('cd ' . escapeshellarg($root) . ' && git ls-files -- includes emsfb.php uninstall.php 2>/dev/null');
foreach (array_filter(explode("\n", (string) $git)) as $f) if (substr($f, -4) === '.php' && is_file("$root/$f")) $files[] = $f;
if (!$files) {
	$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator("$root/includes", FilesystemIterator::SKIP_DOTS));
	foreach ($it as $f) if (substr($f->getFilename(), -4) === '.php') $files[] = substr($f->getPathname(), strlen($root) + 1);
	$files[] = 'emsfb.php';
}
t('the shipped PHP files are found', count($files) > 80, (string) count($files));

/* ---------------------------------------------------------------- 1. i18n */
$gettext = array_flip(array('__', '_e', 'esc_html__', 'esc_html_e', 'esc_attr__', 'esc_attr_e', '_x', '_ex', 'esc_html_x', 'esc_attr_x', '_n', '_nx'));
$no_comment = array(); $unordered = array();
foreach ($files as $f) {
	$tokens = token_get_all(file_get_contents("$root/$f"));
	$n = count($tokens);
	for ($i = 0; $i < $n; $i++) {
		$tk = $tokens[$i];
		if (!is_array($tk) || $tk[0] !== T_STRING || !isset($gettext[$tk[1]])) continue;
		// a function call, not a method or a declaration
		$p = $i - 1; while ($p >= 0 && is_array($tokens[$p]) && $tokens[$p][0] === T_WHITESPACE) $p--;
		if ($p >= 0 && is_array($tokens[$p]) && in_array($tokens[$p][0], array(T_OBJECT_OPERATOR, T_DOUBLE_COLON, T_FUNCTION), true)) continue;
		$j = $i + 1; while ($j < $n && is_array($tokens[$j]) && $tokens[$j][0] === T_WHITESPACE) $j++;
		if ($j >= $n || $tokens[$j] !== '(') continue;
		$j++; while ($j < $n && is_array($tokens[$j]) && $tokens[$j][0] === T_WHITESPACE) $j++;
		if (!is_array($tokens[$j]) || $tokens[$j][0] !== T_CONSTANT_ENCAPSED_STRING) continue;
		$text = $tokens[$j][1];
		if (!preg_match_all('/%(?:\d+\$)?[sdf]/', str_replace('%%', '', $text), $ph)) continue;
		$where = $f . ':' . $tk[2];
		if (count($ph[0]) > 1 && preg_grep('/^%[sdf]$/', $ph[0])) $unordered[] = $where;
		// The comment WPCS accepts: the nearest comment before the call, ending on the line above, or followed only by
		// whitespace up to the call's line.
		$c = $i - 1;
		while ($c >= 0 && !(is_array($tokens[$c]) && in_array($tokens[$c][0], array(T_COMMENT, T_DOC_COMMENT), true))) $c--;
		$ok = false;
		if ($c >= 0 && stripos($tokens[$c][1], 'translators:') !== false) {
			$end = $tokens[$c][2] + substr_count(rtrim($tokens[$c][1], "\n"), "\n");
			if ($end + 1 === $tk[2] || $end === $tk[2]) $ok = true;
			else {
				$k = $c + 1; while ($k < $i && is_array($tokens[$k]) && $tokens[$k][0] === T_WHITESPACE) $k++;
				$line = is_array($tokens[$k]) ? $tokens[$k][2] : null;
				if ($k >= $i || $line === $tk[2]) $ok = true;
			}
		}
		if (!$ok) $no_comment[] = $where;
	}
}
t('every translatable string with a placeholder has a translators: comment', !$no_comment, implode(', ', array_slice($no_comment, 0, 12)) . (count($no_comment) > 12 ? ' …(' . count($no_comment) . ')' : ''));
t('a string with two or more placeholders numbers them (%1$s, %2$s)', !$unordered, implode(', ', $unordered));

/* ------------------------------------------------- 2. discouraged functions */
$bad = array();
foreach ($files as $f) {
	foreach (file("$root/$f") as $no => $line) {
		$code = preg_replace('#\s//\s.*$#', '', preg_replace('#^\s*(//|\*|/\*).*#', '', $line));
		if (strpos($line, 'phpcs:ignore') !== false) continue;
		$at = $f . ':' . ($no + 1);
		if (preg_match('/(?<![\w>:$])strip_tags\(\s*[^,()]*(\([^()]*\)[^,()]*)*\)/', $code) && !preg_match('/strip_tags\([^;]*,\s*[\'"<]/', $code)) $bad[] = "$at strip_tags";
		if (preg_match('/(?<![\w>:$])parse_url\(/', $code)) $bad[] = "$at parse_url";
		if (preg_match('/(?<![\w>:$])date\(/', $code)) $bad[] = "$at date";
		if (preg_match('/(?<![\w>:$])mt_rand\(/', $code)) $bad[] = "$at mt_rand";
		if (strpos($line, '<?=') !== false) $bad[] = "$at <?=";
		if ($f !== 'includes/admin/class-Emsfb-xlsx-writer.php' && preg_match('/(?<![\w>:$])unlink\(/', $code)) $bad[] = "$at unlink";
	}
}
t('no strip_tags(), parse_url(), date(), mt_rand(), <?= or (outside the XLSX writer) unlink()', !$bad, implode(', ', array_slice($bad, 0, 12)));

/* ---------------------------------------------------------------- 3. SQL */
$glued = array(); $later = array();
foreach ($files as $f) {
	$src = file_get_contents("$root/$f");
	if (preg_match_all('/IN \((?:"|\') \. implode\(\s*\',\'\s*,\s*\$\w+\s*\)/', $src, $m, PREG_OFFSET_CAPTURE)) {
		foreach ($m[0] as $x) $glued[] = $f . ':' . (substr_count(substr($src, 0, $x[1]), "\n") + 1);
	}
	if (preg_match_all('/\$wpdb->(?:query|get_var|get_row|get_results|get_col)\(\s*\$(sql|query)\b[^\n]*/', $src, $m, PREG_OFFSET_CAPTURE)) {
		foreach ($m[0] as $x) if (strpos($x[0], 'phpcs:ignore') === false) $later[] = $f . ':' . (substr_count(substr($src, 0, $x[1]), "\n") + 1);
	}
}
t('no IN (...) list glued from a PHP array (a placeholder per value)', !$glued, implode(', ', $glued));
t('no query runs a $sql / $query prepared somewhere else', !$later, implode(', ', $later));

/* ----------------------------------------------------------- 4. escaping */
$rd = function ($p) use ($root) { return (string) @file_get_contents("$root/$p"); };
$panel = $rd('includes/admin/class-Emsfb-panel.php');
$raw = array();
foreach (array('includes/admin/class-Emsfb-panel.php', 'includes/admin/class-Emsfb-create.php', 'includes/admin/class-Emsfb-addon.php') as $p) {
	if (preg_match('/echo\s+\$noti_pro/', $rd($p))) $raw[] = "$p \$noti_pro";
}
if (preg_match('/<\?php echo\s+\$lang\[/', $panel)) $raw[] = 'panel $lang';
if (preg_match('/<\?php echo \$(title|message|btn_text); \?>/', $rd('includes/functions.php'))) $raw[] = 'Pro gate';
if (strpos($rd('includes/admin/class-Emsfb-admin.php'), 'echo $output;') !== false) $raw[] = 'admin echo $output';
t('the notices, the Panel menu and the Pro gate print no raw variable', !$raw, implode(', ', $raw));

/* --------------------------------------------------------- 5. offloading */
t('the Panel loads the bundled Chart.js, not Google Charts', strpos($panel, 'gstatic.com/charts') === false && strpos($panel, 'public/assets/js/vendor/chart.umd.min.js') !== false);
$lf = $rd('includes/admin/assets/js/list_form-efb.js');
t('the response charts draw with Chart.js (no google.visualization)', strpos($lf, 'google.') === false && strpos($lf, 'new Chart(') !== false);
$cdn = array();
foreach (array('includes/admin/class-Emsfb-panel.php', 'includes/admin/class-Emsfb-create.php') as $p) if (strpos($rd($p), 'CDN_ZONE_AREA') !== false) $cdn[] = $p;
t('the admin screens read the country lists from the bundled copy (_Public::geo_base_url_efb())', !$cdn && substr_count($panel . $rd('includes/admin/class-Emsfb-create.php'), '_Public::geo_base_url_efb()') >= 4, implode(', ', $cdn));
t('the release fetches the builder\'s country list (js/wp/countries.js) with the other lists', strpos($rd('bin/fetch-public-assets.sh'), 'js/wp/countries.js') !== false);
t('emsfb.php requires its main class by __DIR__ (not by a path relative to the working folder)', strpos($rd('emsfb.php'), "require __DIR__ . '/includes/class-Emsfb.php';") !== false && strpos($rd('emsfb.php'), "require 'includes/class-Emsfb.php';") === false);

/* ------------------------------------------------- 6. validate_url_efb() */
$fn = $rd('includes/functions.php');
if (preg_match('/public function validate_url_efb\(\$url\) \{.*?\n\t\t\}\n/s', $fn, $m)) {
	if (!function_exists('esc_url')) { function esc_url($u) { return (string) $u; } }
	if (!function_exists('wp_parse_url')) { function wp_parse_url($u, $c = -1) { return parse_url($u, $c); } }
	eval('class EfbB6UrlProbe { ' . $m[0] . ' }');
	$probe = new EfbB6UrlProbe();
	$err = '';
	try { $a = $probe->validate_url_efb('https://example.com/bg.png'); } catch (\Throwable $e) { $a = null; $err = get_class($e) . ': ' . $e->getMessage(); }
	t('validate_url_efb() answers for a URL with a host (no TypeError)', $a === 'https://example.com/bg.png', $err);
	t('validate_url_efb() still refuses javascript:', $probe->validate_url_efb('javascript:alert(1)') === '');
} else {
	t('validate_url_efb() is found', false);
}

/* ------------------------------------------------------ 7. files gone */
$gone = array('includes/class-Emsfb-webhook.php', 'includes/class-Emsfb-requirement.php', 'includes/integrate-wpb.php', 'public/assets/images/efb-poster.png', 'public/assets/images/email_template1.svg', 'includes/admin/assets/image/logo-20px.png', 'includes/admin/assets/image/arrow-down.svg', 'includes/admin/assets/image/arrow-up.svg');
$back = array_values(array_filter($gone, function ($g) use ($root) { return file_exists("$root/$g"); }));
t('the files nothing loads stay out of the plugin', !$back, implode(', ', $back));

/* ------------------------------------------------------ 8. hand-offs */
// ADD-03 (B8 → B6): the Conditional Logic contract and the file-count operators.
if (!defined('ABSPATH')) define('ABSPATH', $root . '/');
require_once "$root/includes/addon-contracts-functions-efb.php";
$cv = efb_addon_contract_versions();
t('efb_addon_contract_versions() lists logic 1.1 (min 1, an add-on contract)', isset($cv['logic']) && $cv['logic']['version'] == 1.1 && $cv['logic']['min'] == 1 && $cv['logic']['core'] === false, json_encode($cv['logic'] ?? null));
preg_match('/\$allowed_compares = array\(([^)]*)\)/s', $fn, $ac);
$bapi = $rd('includes/class-Emsfb-builder-api.php');
t('the logic save keeps count_gte / count_lte, Builder_Api::LOGIC_COMPARES knows them, core reads them as gte / lte', isset($ac[1]) && strpos($ac[1], "'count_gte', 'count_lte'") !== false && preg_match("/const LOGIC_COMPARES = array\\([^;]*'count_gte', 'count_lte'\\);/s", $bapi) === 1 && strpos($rd('includes/class-Emsfb-public.php'), "if (\$compare === 'count_gte' || \$compare === 'count_lte') \$compare = substr(\$compare, 6);") !== false);
// PUB-03 (B5 → B6): the answers of AJAX and REST routes are phrases.
$lit = array();
foreach (array('includes/admin/class-Emsfb-admin.php' => "'m' =>'Security Error'", 'includes/class-Emsfb-choices.php' => "'Unknown list'", 'includes/class-Emsfb-form-gate.php' => "'message' => 'Form not found.'", 'includes/payments/class-Emsfb-payment-registry.php' => "'message' => 'Form not found.'", 'includes/payments/class-Emsfb-payment-legacy-migration.php' => "'Give form_id.'", 'includes/class-Emsfb-form-package.php' => "'m' => 'No answers.'", 'includes/payments/class-Emsfb-payment-admin.php' => "'unknown action'") as $p => $s) {
	if (strpos($rd($p), $s) !== false) $lit[] = "$p $s";
}
if (strpos($rd('includes/class-Emsfb-choices.php'), "array('m' => 'Forbidden')") !== false) $lit[] = 'choices Forbidden';
t('no fixed English in the answers of the admin AJAX, choices, form gate, form package and payment routes (PUB-03)', !$lit, implode(', ', $lit));

echo "\nRESULTS: {$passed} passed, {$failed} failed\n";
exit($failed ? 1 : 0);
