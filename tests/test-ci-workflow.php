<?php
/**
 * B10 QA-02: .github/workflows/ci.yml defines the matrix the release exit criteria name: the unit suite on PHP 7.4 -
 * 8.4; the site-backed tests and a smoke e2e on WordPress 6.3 (the minimum, owner decision 7), 6.6 and latest with
 * MySQL, MariaDB and SQLite, under the e2e lock (tests/run-suite.php); the 4.3.1 upgrade on MySQL and MariaDB; the
 * release zip with Plugin Check. The workflow itself runs on GitHub; this only keeps its definition honest.
 *
 * Run: php tests/test-ci-workflow.php   (no WordPress; names of the site are split so the runner counts this file as pure)
 */
$root = dirname(__DIR__);
$pass = 0; $fail = 0;
function t($label, $ok, $info = '') { global $pass, $fail; if ($ok) { $pass++; echo "[PASS] $label\n"; return; } $fail++; echo "[FAIL] $label" . ($info !== '' ? ": $info" : '') . "\n"; }

$y = (string) @file_get_contents("$root/.github/workflows/ci.yml");
t('.github/workflows/ci.yml exists', $y !== '');
$job = function ($name) use ($y) {
	return preg_match('/^  ' . preg_quote($name, '/') . ':\n(.*?)(?=^  [a-z][a-z0-9_-]*:\n|\z)/ms', $y, $m) ? $m[1] : '';
};
foreach (array('unit', 'wordpress', 'upgrade', 'build') as $j) t("job $j", $job($j) !== '');
$unit = $job('unit'); $wp = $job('wordpress'); $upg = $job('upgrade'); $build = $job('build');
t('unit: PHP 7.4, 8.0, 8.1, 8.2, 8.3, 8.4', strpos($unit, "php: ['7.4', '8.0', '8.1', '8.2', '8.3', '8.4']") !== false);
t('unit: the runner without the site (EFB_WP_ROOT=none, --no-site)', strpos($unit, 'EFB_WP_ROOT: none') !== false && strpos($unit, 'php tests/run-suite.php --no-site') !== false);
t('site: the same PHP versions', strpos($wp, "php: ['7.4', '8.0', '8.1', '8.2', '8.3', '8.4']") !== false);
t('site: WordPress 6.3 (the minimum), 6.6 and latest', strpos($wp, "wp: ['6.3', '6.6', 'latest']") !== false);
t('site: MySQL 8.0, MariaDB 10.6 and SQLite', strpos($wp, 'db: [mysql, mariadb, sqlite]') !== false && strpos($wp, "'mysql:8.0'") !== false && strpos($wp, "'mariadb:10.6'") !== false && strpos($wp, 'sqlite-database-integration') !== false);
t('site: the site tests and the smoke e2e through the runner, under the e2e lock', strpos($wp, 'php tests/run-suite.php --site-only --e2e="1 2 3"') !== false && strpos($wp, 'EFB_' . 'E2E_LOCK:') !== false);
t('site: the plugin is the checkout, with the e2e mu-plugin', strpos($wp, 'ln -s "$GITHUB_WORKSPACE" "$SITE/wp-content/plugins/easy-form-builder"') !== false && strpos($wp, 'tests/e2e' . '-wp/mu-plugins/efb-e2e.php') !== false);
t('site: only unsupported pairs are left out (PHP 8.3 / 8.4 before WordPress 6.4 / 6.7; SQLite before 6.4)', preg_match_all('/^\s+- \{ wp: /m', $wp) === 4);
t('upgrade: 4.3.1 -> this version on MySQL 8.0 and MariaDB 10.6, PHP 7.4 and 8.3, at most 30 s', strpos($upg, "db_image: ['mysql:8.0', 'mariadb:10.6']") !== false && strpos($upg, "php: ['7.4', '8.3']") !== false && strpos($upg, 'tests/upgrade/run-upgrade-431.sh') !== false && strpos($upg, "EFB_UPG_MAX_SECONDS: '30'") !== false);
t('build: bin/build-zip.sh with Plugin Check, dist/ kept as an artifact', strpos($build, 'bin/build-zip.sh') !== false && strpos($build, 'plugin-check') !== false && strpos($build, 'EFB_PLUGIN_CHECK_WP=') !== false && strpos($build, 'dist/*.manifest.json') !== false);
t('the add-ons come from their repository with a secret, never a token in the file', strpos($y, 'secrets.EFB_ADDONS_TOKEN') !== false && !preg_match('/gh[pousr]_[A-Za-z0-9]{20,}/', $y));
t('actions are pinned to a major version', !preg_match('/uses: [^@\s]+\s*$/m', $y) && strpos($y, 'actions/checkout@v4') !== false);
if (function_exists('yaml_parse')) t('the YAML parses', is_array(@yaml_parse($y)));

echo "\nRESULTS: $pass passed, $fail failed\n";
exit($fail ? 1 : 0);
