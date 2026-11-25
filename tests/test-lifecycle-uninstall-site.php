<?php
/**
 * LIFE-01 (owner decision 5) on a real WordPress: deactivate, then delete the plugin, on a PRIVATE COPY of the test
 * site (the shared site is only read). Twice:
 *   1. "Delete all data when the plugin is deleted" off (the default): deactivation leaves no EFB cron event;
 *      deletion removes the events, the transients and the preview drafts and keeps every table, option, file, user
 *      meta and capability;
 *   2. the plugin installed again (the data is found), the switch turned on through set_setting_Emsfb(): deletion
 *      removes the tables, the options, the private upload folder, the Media Library files of entries, the post
 *      meta, the user meta and the capabilities as well.
 * Each step prints the counts (tables, options, autoloaded options, cron events, transients, user meta, post meta,
 * capabilities, private folder) so the run is its own record.
 *
 * Safety: the copy's plugin folder is a real copy (links followed), never the repository; the run stops before any
 * deletion if a link is left in it. WordPress's own "delete the plugin's files" step runs on that copy only.
 *
 * Needs: the test site (tests/wp-env.php), WP-CLI (EFB_E2E_WPCLI, else `wp`), rsync. Run under the e2e lock:
 *   flock <lock> php tests/test-lifecycle-uninstall-site.php
 * EFB_LIFECYCLE_KEEP=1 keeps the copy (its path is printed).
 */

require_once __DIR__ . '/wp-env.php';

$passed = 0;
$failed = 0;
function t($name, $actual, $expected) {
	global $passed, $failed;
	if ($actual === $expected) { echo "[PASS] {$name}\n"; $passed++; return; }
	echo "[FAIL] {$name}: expected " . json_encode($expected) . ', got ' . json_encode($actual) . "\n"; $failed++;
}
function done_efb() {
	global $passed, $failed;
	echo "\nRESULTS: {$passed} passed, {$failed} failed\n";
	exit($failed ? 1 : 0);
}

$site = efb_test_wp_root();
if ($site === '' || !is_file($site . '/wp-load.php')) {
	echo "[SKIP] no WordPress site (tests/wp-env.php)\nRESULTS: 0 passed, 0 failed\n";
	exit(0);
}
$wpcli = trim((string) (getenv('EFB_E2E_WPCLI') ?: 'wp'));
if (trim((string) shell_exec('command -v rsync')) === '') {
	echo "[SKIP] rsync is not installed\nRESULTS: 0 passed, 0 failed\n";
	exit(0);
}
$repo = realpath(dirname(__DIR__));
$tmp = rtrim(sys_get_temp_dir(), '/') . '/efb-lifecycle-' . getmypid() . '-' . bin2hex(random_bytes(3));
$copy = $tmp . '/site';
$plugin = $copy . '/wp-content/plugins/easy-form-builder';
register_shutdown_function(function () use ($tmp) {
	if (getenv('EFB_LIFECYCLE_KEEP')) { echo "(kept: {$tmp})\n"; return; }
	if (is_dir($tmp) && strpos($tmp, sys_get_temp_dir()) === 0) shell_exec('rm -rf ' . escapeshellarg($tmp));
});

function sh_efb($cmd) {
	$out = array();
	exec($cmd . ' 2>&1', $out, $code);
	return array($code, implode("\n", $out));
}
function wp_efb($args) {
	global $wpcli, $copy;
	return sh_efb('cd ' . escapeshellarg($copy) . ' && ' . $wpcli . ' --allow-root --skip-themes ' . $args);
}
/** The plugin as a real folder in the copy: links followed, no repository files a release does not ship. */
function copy_plugin_efb() {
	global $repo, $plugin;
	$ex = '';
	foreach (array('.git', 'node_modules', 'tests', 'docs', 'temp', '_workspace', 'dist', '*.zip', 'vendor/*/tests', 'vendor/*/.git', '.claude') as $e) $ex .= ' --exclude=' . escapeshellarg($e);
	@mkdir($plugin, 0777, true);
	list($code, $out) = sh_efb('rsync -aL' . $ex . ' ' . escapeshellarg($repo . '/') . ' ' . escapeshellarg($plugin . '/'));
	$links = trim((string) shell_exec('find ' . escapeshellarg($plugin) . ' -type l | head -3'));
	$real = realpath($plugin);
	return $code === 0 && $links === '' && is_string($real) && strpos($real, $GLOBALS['tmp']) === 0 && is_file($plugin . '/uninstall.php');
}

/* What EFB leaves in the copy. */
$inspect = $tmp . '/inspect.php';
$probe = <<<'PHP'
<?php
global $wpdb;
$tables = $wpdb->get_col($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($wpdb->prefix . 'emsfb') . '%'));
$ours = "(option_name LIKE 'emsfb%' OR option_name LIKE 'efb\\_op\\_%' OR option_name LIKE 'efb\\_stkh\\_%' OR option_name LIKE 'efb\\_uqh\\_%' OR option_name LIKE 'efb\\_gh\\_%' OR option_name IN ('efb_dyn_keys','efb_email_log','efb_form_package_imports','efb_draft_forms','efb_portal_form_pages'))";
$opts = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->options} WHERE {$ours}");
$auto = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->options} WHERE {$ours} AND autoload IN ('yes','on','auto-on','auto')");
$tr = 0;
foreach (array('_transient_', '_transient_timeout_', '_site_transient_', '_site_transient_timeout_') as $k) foreach (array('emsfb', 'efb_') as $p) $tr += (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE %s", $wpdb->esc_like($k . $p) . '%'));
$cron = 0;
foreach ((array) _get_cron_array() as $ev) foreach ((array) $ev as $hook => $x) if (preg_match('/emsfb|efb/i', $hook)) $cron += count((array) $x);
$um = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->usermeta} WHERE meta_key LIKE 'efb%' OR meta_key LIKE 'emsfb%'");
$pm = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key IN ('_emsfb_dynamic_page_v1','_efb_form_id','_efb_field_id','_efb_track')");
$att = (int) $wpdb->get_var("SELECT COUNT(DISTINCT post_id) FROM {$wpdb->postmeta} WHERE meta_key = '_efb_form_id'");
$caps = 0;
foreach (wp_roles()->roles as $r => $d) foreach ((array) $d['capabilities'] as $c => $on) if (stripos($c, 'emsfb') === 0) $caps++;
$up = wp_upload_dir(null, false);
$priv = trailingslashit($up['basedir']) . 'efb-private';
$previews = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_name LIKE 'easy-form-builder-preview%'");
$entries = in_array($wpdb->prefix . 'emsfb_msg_', $tables, true) ? (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}emsfb_msg_") : 0;
echo wp_json_encode(array('tables' => count($tables), 'entries' => $entries, 'options' => $opts, 'autoloaded' => $auto, 'cron' => $cron, 'transients' => $tr,
	'user_meta' => $um, 'post_meta' => $pm, 'attachments' => $att, 'caps' => $caps, 'private_dir' => is_dir($priv), 'previews' => $previews,
	'active' => is_plugin_active('easy-form-builder/emsfb.php')));
PHP;

/* Seeds what each removal must find: every event the plugin can schedule, a transient, a preview draft, a Media
 * Library file of an entry, a dismissed notice, a file in the private folder. */
$seed = $tmp . '/seed.php';
$seed_src = <<<'PHP'
<?php
require_once WP_PLUGIN_DIR . '/easy-form-builder/includes/class-Emsfb-lifecycle.php';
foreach (\Emsfb\Lifecycle::CRON_HOOKS as $i => $h) if (!wp_next_scheduled($h)) wp_schedule_single_event(time() + 3600 + $i, $h, $h === 'emsfb_email_monitor_lifecycle' ? array('activation') : array());
set_transient('emsfb_lifecycle_probe', 1, HOUR_IN_SECONDS);
set_transient('efb_rs_lifecycle_probe', 1, HOUR_IN_SECONDS);
$page = wp_insert_post(array('post_title' => 'Form Preview', 'post_type' => 'page', 'post_name' => 'easy-form-builder-preview', 'post_status' => 'draft', 'post_content' => ' [EMS_Form_Builder id=1] '));
update_option('preview@ems_form_builderid1', $page);
$up = wp_upload_dir();
$file = trailingslashit($up['path']) . 'efb-PLG-lifecycle-' . wp_generate_password(6, false) . '.txt';
file_put_contents($file, 'entry file');
$att = wp_insert_attachment(array('post_mime_type' => 'text/plain', 'post_title' => 'efb-lifecycle', 'post_status' => 'private', 'post_author' => 0), $file, 0, false);
update_post_meta($att, '_efb_form_id', 1); update_post_meta($att, '_efb_track', 'LIFE01');
update_user_meta(1, 'efb_dt_card_dismissed', 1);
$priv = trailingslashit($up['basedir']) . 'efb-private/2026/11';
wp_mkdir_p($priv);
file_put_contents($priv . '/lifecycle-probe.txt', 'private');
echo wp_json_encode(array('page' => $page, 'attachment' => $att, 'file' => $file));
PHP;

/* ---- the copy ---- */
@mkdir($copy, 0777, true);
list($code, $out) = sh_efb('rsync -a --exclude=wp-content/plugins/easy-form-builder --exclude=wp-content/plugins/efb-wptests --exclude=wp-content/plugins/efb-customer-portal --exclude=wp-content/mu-plugins --exclude=wp-content/debug.log ' . escapeshellarg($site . '/') . ' ' . escapeshellarg($copy . '/'));
t('a private copy of the test site', $code, 0);
t('the copy holds no link (its plugins are real folders)', trim((string) shell_exec('find ' . escapeshellarg($copy) . ' -type l | head -3')), '');
t('the plugin copied into it as a real folder, with uninstall.php', copy_plugin_efb(), true);
file_put_contents($inspect, $probe);
file_put_contents($seed, $seed_src);
$counts = function () use ($inspect) {
	list($c, $o) = wp_efb('eval-file ' . escapeshellarg($inspect));
	$j = json_decode(substr($o, (int) strpos($o, '{')), true);
	return is_array($j) ? $j : array('error' => $o);
};
$show = function ($label, $c) {
	echo str_pad($label, 34) . json_encode($c) . "\n";
};

list($code, $out) = wp_efb('plugin is-active easy-form-builder');
t('EFB is active on the copy', $code, 0);

/* ---- run 1: the switch off (default) ---- */
echo "\n== Run 1: \"Delete all data when the plugin is deleted\" OFF (default)\n";
list($code, $out) = wp_efb('eval-file ' . escapeshellarg($seed));
t('run 1: seeded the events, a transient, a preview draft, a Media Library file, user meta', $code, 0);
list($code, $out) = wp_efb('eval ' . escapeshellarg('require_once WP_PLUGIN_DIR . "/easy-form-builder/includes/class-Emsfb-lifecycle.php"; echo "SWITCH=", \Emsfb\Lifecycle::wants_data_removal() ? "on" : "off";'));
t('run 1: the switch is off', preg_match('/SWITCH=(on|off)/', $out, $m) ? $m[1] : $out, 'off');
$b1 = $counts(); $show('before deactivation', $b1);
t('run 1: the plugin\'s events are scheduled (' . $b1['cron'] . ')', $b1['cron'] >= 20, true);
list($code, $out) = wp_efb('plugin deactivate easy-form-builder');
t('run 1: deactivated (wp plugin deactivate)', $code, 0);
$d1 = $counts(); $show('after deactivation', $d1);
t('run 1: deactivation leaves no EFB cron event', $d1['cron'], 0);
t('run 1: deactivation keeps the tables and the entries', array($d1['tables'], $d1['entries']), array($b1['tables'], $b1['entries']));
t('run 1: deactivation keeps the private folder and the capabilities', array($d1['private_dir'], $d1['caps']), array(true, $b1['caps']));
t('copy: the plugin folder is still a real folder before deletion', is_dir($plugin) && !is_link($plugin) && trim((string) shell_exec('find ' . escapeshellarg($plugin) . ' -type l | head -1')) === '', true);
list($code, $out) = wp_efb('plugin uninstall easy-form-builder');
t('run 1: deleted (wp plugin uninstall: uninstall.php, then the files)', $code, 0);
clearstatcache();
t('run 1: the plugin files are gone', is_dir($plugin), false);
$u1 = $counts(); $show('after deletion', $u1);
t('run 1: no EFB cron event', $u1['cron'], 0);
t('run 1: no EFB transient', $u1['transients'], 0);
t('run 1: the preview draft is gone', $u1['previews'], 0);
t('run 1: the tables and their entries are kept', array($u1['tables'], $u1['entries']), array($b1['tables'], $b1['entries']));
t('run 1: the options are kept (deletion removed none)', $u1['options'], $d1['options']);
t('run 1: the private folder, the Media Library files, the post meta are kept', array($u1['private_dir'], $u1['attachments'], $u1['post_meta']), array(true, $b1['attachments'], $b1['post_meta']));
t('run 1: user meta and capabilities are kept', array($u1['user_meta'], $u1['caps']), array($b1['user_meta'], $b1['caps']));

/* ---- run 2: installed again, the switch on ---- */
echo "\n== Run 2: installed again, \"Delete all data when the plugin is deleted\" ON\n";
t('run 2: the plugin copied in again', copy_plugin_efb(), true);
list($code, $out) = wp_efb('plugin activate easy-form-builder');
t('run 2: activated', $code, 0);
$a2 = $counts(); $show('after reactivation', $a2);
t('run 2: the kept data is found again (tables and entries)', array($a2['tables'] >= $b1['tables'], $a2['entries']), array(true, $b1['entries']));
list($code, $out) = wp_efb('eval ' . escapeshellarg('efbFunction::set_setting_Emsfb((object) array("prv_delete_on_uninstall" => "1")); \Emsfb::get_setting_Emsfb("_clear_cache"); delete_transient("emsfb_settings_transient"); echo "SAVED=", \Emsfb\Privacy::setting("prv_delete_on_uninstall");'));
t('run 2: the switch saved through set_setting_Emsfb()', preg_match('/SAVED=(\S*)/', $out, $m) ? $m[1] : $out, '1');
list($code, $out) = wp_efb('eval-file ' . escapeshellarg($seed));
t('run 2: seeded again', $code, 0);
$b2 = $counts(); $show('before deactivation', $b2);
list($code, $out) = wp_efb('plugin deactivate easy-form-builder');
t('run 2: deactivated', $code, 0);
$d2 = $counts(); $show('after deactivation', $d2);
t('run 2: deactivation leaves no EFB cron event', $d2['cron'], 0);
t('run 2: deactivation alone deletes nothing (tables, entries)', array($d2['tables'], $d2['entries']), array($b2['tables'], $b2['entries']));
list($code, $out) = wp_efb('eval ' . escapeshellarg('require_once WP_PLUGIN_DIR . "/easy-form-builder/includes/class-Emsfb-lifecycle.php"; echo "SWITCH=", \Emsfb\Lifecycle::wants_data_removal() ? "on" : "off";'));
t('run 2: uninstall.php reads the switch as on', preg_match('/SWITCH=(on|off)/', $out, $m) ? $m[1] : $out, 'on');
t('copy: the plugin folder is still a real folder before deletion', is_dir($plugin) && !is_link($plugin) && trim((string) shell_exec('find ' . escapeshellarg($plugin) . ' -type l | head -1')) === '', true);
list($code, $out) = wp_efb('plugin uninstall easy-form-builder');
t('run 2: deleted', $code, 0);
clearstatcache();
t('run 2: the plugin files are gone', is_dir($plugin), false);
$u2 = $counts(); $show('after deletion', $u2);
t('run 2: no EFB table left', $u2['tables'], 0);
t('run 2: no EFB option left (none autoloaded)', array($u2['options'], $u2['autoloaded']), array(0, 0));
t('run 2: no EFB cron event, no transient, no preview draft', array($u2['cron'], $u2['transients'], $u2['previews']), array(0, 0, 0));
t('run 2: the private upload folder is gone', $u2['private_dir'], false);
t('run 2: the Media Library files of entries and the post meta are gone', array($u2['attachments'], $u2['post_meta']), array(0, 0));
t('run 2: the user meta and the capabilities are gone', array($u2['user_meta'], $u2['caps']), array(0, 0));
list($code, $out) = wp_efb('eval ' . escapeshellarg('echo "SITE=", count(get_users(array("fields" => "ID"))) > 0 && get_option("siteurl") ? "ok" : "broken";'));
t('run 2: the rest of the site is intact (users, siteurl)', preg_match('/SITE=(\w+)/', $out, $m) ? $m[1] : $out, 'ok');

done_efb();
