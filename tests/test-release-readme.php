<?php
/**
 * B1 (REL-01, REL-02, REL-03, owner decisions 2 and 7): the release text agrees with the plugin.
 *  - REL-01: readme.txt's Stable tag is the plugin's Version (the header and EMSFB_PLUGIN_VERSION); the changelog's
 *    first entry is "= <version> =" (no "= Unreleased ="), short and user-facing; the Upgrade Notice starts with
 *    "= <version> =" (no bullets before the first heading) and that notice is at most 300 characters; the long list
 *    lives in CHANGELOG.md.
 *  - REL-02: one licence, "GPLv2 or later", in the plugin header, readme.txt and README.md (no GPLv3 / LGPL).
 *  - Owner decision 7: "Requires at least: 6.3" in the header and the readme (README.md says the same).
 *  - REL-03: Stripe and the Contact Form 7 import are not presented as core; the readme has "== External services =="
 *    naming every remote host the code may call (found by scanning the code, so a new host must be disclosed).
 *
 * Run: php tests/test-release-readme.php   (no WordPress, no network)
 */
$root = dirname(__DIR__);
$pass = 0; $fail = 0;
function t($label, $ok, $info = '') { global $pass, $fail; if ($ok) { $pass++; echo "[PASS] $label\n"; return; } $fail++; echo "[FAIL] $label" . ($info !== '' ? ": $info" : '') . "\n"; }

$main   = (string) file_get_contents("$root/emsfb.php");
$readme = (string) file_get_contents("$root/readme.txt");
$md     = (string) @file_get_contents("$root/README.md");
$log    = (string) @file_get_contents("$root/CHANGELOG.md");

$hdr = function ($src, $key) { return preg_match('/^[ \t\/*#@]*' . preg_quote($key, '/') . ':\s*(.+?)\s*$/mi', $src, $m) ? $m[1] : null; };
$version = $hdr($main, 'Version');
preg_match('/define\(\s*"EMSFB_PLUGIN_VERSION"\s*,\s*"([^"]+)"/', $main, $cv);

/* REL-01: the version, the Stable tag, the changelog and the Upgrade Notice. */
t('the header Version is EMSFB_PLUGIN_VERSION', $version !== null && isset($cv[1]) && $version === $cv[1], $version . ' / ' . ($cv[1] ?? '?'));
t('readme.txt: Stable tag is the plugin version', $hdr($readme, 'Stable tag') === $version, (string) $hdr($readme, 'Stable tag'));
t('readme.txt: no "= Unreleased =" entry', strpos($readme, '= Unreleased =') === false);

$section = function ($name) use ($readme) {
	$i = strpos($readme, "== $name ==");
	if ($i === false) return null;
	$i += strlen("== $name ==");
	$j = strpos($readme, "\n== ", $i);
	return substr($readme, $i, $j === false ? strlen($readme) - $i : $j - $i);
};
$cl = (string) $section('Changelog');
preg_match_all('/^= ([^=]+?) =\s*$/m', $cl, $clh);
t('the changelog starts with "= ' . $version . ' ="', isset($clh[1][0]) && $clh[1][0] === $version, $clh[1][0] ?? 'none');
$entry = preg_match('/^= ' . preg_quote((string) $version, '/') . ' =\s*$(.*?)(?=^= |\z)/ms', $cl, $em) ? $em[1] : '';
$bullets = preg_match_all('/^\* /m', $entry);
t('the ' . $version . ' changelog is short and user-facing (at most 30 bullets, 6,000 bytes; was 239 bullets)', $bullets > 0 && $bullets <= 30 && strlen($entry) <= 6000, "$bullets bullets, " . strlen($entry) . ' bytes');
t('the ' . $version . ' changelog notes the {field_id} label change of choice fields (F08 §13)', strpos($entry, '{field_id}') !== false && stripos($entry, 'label') !== false);
t('the long technical list is in CHANGELOG.md under ' . $version, preg_match('/^## ' . preg_quote((string) $version, '/') . '\s*$/m', $log) === 1 && substr_count($log, "\n* ") > 200 && strpos($log, 'Developers:') !== false);
t('the readme points to CHANGELOG.md', strpos($entry, 'CHANGELOG.md') !== false);

$up = (string) $section('Upgrade Notice');
$first = ltrim($up, "\n ");
t('the Upgrade Notice starts with a "= x.y.z =" heading (no orphan bullets)', (bool) preg_match('/^= [0-9][^=]* =\s*\n/', $first), substr($first, 0, 60));
$notice = preg_match('/^= ' . preg_quote((string) $version, '/') . ' =\s*$(.*?)(?=^= |\z)/ms', $up, $nm) ? trim($nm[1]) : null;
t('there is an upgrade notice for ' . $version, $notice !== null && $notice !== '');
t('the ' . $version . ' upgrade notice is at most 300 characters', $notice !== null && function_exists('mb_strlen') ? mb_strlen($notice) <= 300 : strlen((string) $notice) <= 300, (string) ($notice !== null ? strlen($notice) : 0));
$long = array();
foreach (preg_split('/^(?== [0-9])/m', $up) as $chunk) {
	if (preg_match('/^= ([^=]+?) =\s*\n(.*)$/s', $chunk, $um) && strlen(trim($um[2])) > 300) $long[] = $um[1] . ' (' . strlen(trim($um[2])) . ')';
}
t('every upgrade notice is at most 300 characters (Plugin Check: upgrade_notice_limit)', !$long, implode(', ', $long));
t('the upgrade notice says what to do (back up, update the payment add-ons)', $notice !== null && stripos($notice, 'back up') !== false && stripos($notice, 'add-on') !== false);
t('readme.txt is at most 70 KB (was 117 KB)', strlen($readme) <= 70000, (string) strlen($readme));
$short = preg_match('/^License URI:.*\n(.+)\n/m', $readme, $sm) ? trim($sm[1]) : '';
t('the short description is at most 150 characters', $short !== '' && strlen($short) <= 150, (string) strlen($short));

/* REL-02: one licence. */
t('emsfb.php: License: GPLv2 or later', $hdr($main, 'License') === 'GPLv2 or later', (string) $hdr($main, 'License'));
t('readme.txt: License: GPLv2 or later', $hdr($readme, 'License') === 'GPLv2 or later', (string) $hdr($readme, 'License'));
t('emsfb.php and readme.txt: the GPL 2.0 licence address', $hdr($main, 'License URI') === 'https://www.gnu.org/licenses/gpl-2.0.html' && $hdr($readme, 'License URI') === 'https://www.gnu.org/licenses/gpl-2.0.html');
t('README.md: GPLv2 or later, no LGPL or GPLv3 badge', stripos($md, 'GPLv2 or later') !== false && stripos($md, 'LGPL') === false && stripos($md, 'gpl-3.0') === false);

/* The owner (2026-11-25): the customer portal is left out for now; the readme does not announce or promote it (its
   inert core contract stays a developer note in CHANGELOG.md and docs/). */
t('readme.txt and README.md do not mention the customer portal', stripos($readme, 'portal') === false && stripos($md, 'portal') === false);

/* Owner decision 7: WordPress 6.3. */
t('emsfb.php: Requires at least: 6.3', $hdr($main, 'Requires at least') === '6.3', (string) $hdr($main, 'Requires at least'));
t('readme.txt: Requires at least: 6.3', $hdr($readme, 'Requires at least') === '6.3', (string) $hdr($readme, 'Requires at least'));
t('both say Requires PHP: 7.4', $hdr($main, 'Requires PHP') === '7.4' && $hdr($readme, 'Requires PHP') === '7.4');
t('README.md asks for WordPress 6.3', strpos($md, '>= `6.3`') !== false);

/* REL-03: what is core. */
t('no "Payment Forms with Stripe" section (gateways are add-ons)', strpos($readme, '== Payment Forms with Stripe ==') === false);
t('the FAQ no longer says the Create and Panel screens import Contact Form 7 (Data Tools does)', strpos($readme, 'the Create and Panel screens show "Import from Contact Form 7"') === false && strpos($readme, 'Data Tools') !== false);
t('the FAQ no longer says the country lists come from a CDN', strpos($readme, 'automatically loaded from an external CDN-hosted JSON source') === false);

/* REL-03: External services names every host the code may call. */
$ext = (string) $section('External services');
t('readme.txt has an "== External services ==" section', strlen($ext) > 1000, (string) strlen($ext));
$hosts = array();
$code_files = array_merge(array("$root/emsfb.php"), glob("$root/includes/*.php"), glob("$root/includes/*/*.php"), glob("$root/includes/*/*/*.php"), glob("$root/public/assets/js/*.js"), glob("$root/includes/admin/assets/js/*.js"));
foreach ($code_files as $f) {
	if (preg_match('/\.min\.js$/', $f)) continue;
	foreach (file($f) as $line) {
		// A host the plugin loads or calls: a remote request, an enqueued script or style, a script address, a CDN
		// constant, a font address list. Links people click (help, docs, pricing) are not calls.
		if (!preg_match('/wp_remote_|download_url\(|wp_(register|enqueue)_(script|style)|script\.src|define\(\s*"EMSFB_[A-Z_]*(CDN|SERVER)[A-Z_]*_URL"|recaptcha\/api\.js|css2\?family=|font-face\.css/', $line)) continue;
		if (preg_match_all('#https?://([a-z0-9.-]+\.[a-z]{2,})#i', $line, $mm)) foreach ($mm[1] as $h) $hosts[strtolower($h)] = basename($f);
	}
}
// The sandbox server is used only while the source sets EMSFB_PLUGIN_TEST_MODE (never in a release).
if (strpos($main, 'define("EMSFB_PLUGIN_TEST_MODE", false);') !== false) unset($hosts['demo.whitestudio.team']);
t('a release never turns the sandbox server on (EMSFB_PLUGIN_TEST_MODE is false)', strpos($main, 'define("EMSFB_PLUGIN_TEST_MODE", false);') !== false);
foreach (array('whitestudio.team', 'easyformbuilder.ir', 'www.gstatic.com', 'api.wordpress.org', 'downloads.wordpress.org', 'www.recaptcha.net') as $h) $hosts[$h] = $hosts[$h] ?? 'known';
ksort($hosts);
t('the scan found the hosts the plugin calls (Google, jsDelivr, WhiteStudio, the CDN lists)', isset($hosts['www.google.com'], $hosts['cdn.jsdelivr.net'], $hosts['fonts.googleapis.com'], $hosts['cdn.easyformbuilder.ir']), implode(', ', array_keys($hosts)));
$missing = array();
foreach ($hosts as $h => $from) if (stripos($ext, $h) === false) $missing[] = "$h ($from)";
t('External services names every host (' . count($hosts) . ')', !$missing, implode(', ', $missing));
// B6: the Panel's entry charts use the bundled Chart.js (class-Emsfb-panel.php), so Google Charts is neither loaded nor listed.
t('the Panel loads no Google Charts, and External services no longer lists it', strpos((string) file_get_contents(__DIR__ . '/../includes/admin/class-Emsfb-panel.php'), 'gstatic.com/charts') === false && stripos($ext, 'Google Charts') === false);
foreach (array('reCAPTCHA', 'Google Fonts', 'jsDelivr', 'font', 'add-on server', 'license server', 'e-mail test', 'language packs', 'Akismet', 'Webhook', 'Add-ons') as $w) {
	t("External services covers: $w", stripos($ext, $w) !== false);
}
foreach (array('https://policies.google.com/privacy', 'https://www.jsdelivr.com/terms/privacy-policy', 'https://whitestudio.team/privacy-policy-terms/', 'https://wordpress.org/about/privacy/', 'https://akismet.com/tos/') as $u) {
	t("External services links $u", strpos($ext, "]($u)") !== false);
}
/* wordpress.org merges every section it does not know (External services among them) into the Description and cuts
   it after 2,500 words (Plugin Check: trimmed_section_description): the disclosure must never be the part cut off. */
$merged = 0;
foreach (preg_split('/^== /m', $readme) as $chunk) {
	if (!preg_match('/^(.+?) ==\s*\n(.*)$/s', $chunk, $sm2)) continue;
	if (in_array(strtolower(trim($sm2[1])), array('installation', 'frequently asked questions', 'screenshots', 'changelog', 'upgrade notice'), true)) continue;
	$merged += preg_match_all('/\S+/', $sm2[2]);
}
t('the Description with the sections merged into it is at most 2,500 words (was 3,480 with External services)', $merged > 1000 && $merged <= 2500, (string) $merged);

echo "\nRESULTS: $pass passed, $fail failed\n";
exit($fail ? 1 : 0);
