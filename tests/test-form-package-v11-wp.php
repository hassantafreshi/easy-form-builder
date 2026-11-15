<?php
/**
 * Form package contract 1.1 (SPLIT-G) on a real WordPress: import() returns an import_token; delete($form_id, $token)
 * removes only a form that import created (its entries with it), refuses another form and a visitor;
 * import_entries($form_id, $rows) stores answers through the submit's own rules (sanitized, local digits read, a
 * value out of range refused and reported) in core's format, with the given date and tracking code.
 * Needs WordPress: EFB_WP_LOAD=/path/to/wp-load.php (else dirname(__DIR__, 4)/wp-load.php; [SKIP] when absent).
 *
 * Run: EFB_WP_LOAD=/path/to/site/wp-load.php php tests/test-form-package-v11-wp.php
 */
$wp_load = getenv('EFB_WP_LOAD') ?: dirname(__DIR__, 4) . '/wp-load.php';
if (!is_readable($wp_load)) { echo "[SKIP] WordPress bootstrap was not found at {$wp_load}\n"; exit(0); }
define('WP_USE_THEMES', false);
require_once $wp_load;
$passed = 0; $failed = 0;
function t($name, $actual, $expected) {
	global $passed, $failed;
	if ($actual === $expected) { echo "[PASS] {$name}\n"; $passed++; return; }
	echo "[FAIL] {$name}: expected " . var_export($expected, true) . ', got ' . var_export($actual, true) . "\n"; $failed++;
}
global $wpdb;
$admin = get_users(array('role' => 'administrator', 'number' => 1, 'fields' => 'ID'));
wp_set_current_user((int) $admin[0]);
$api = efb_form_package_api();
t('efb_form_package_api(): version 1.1 with delete and import_entries', array($api['version'], in_array('delete', $api['functions'], true), in_array('import_entries', $api['functions'], true)), array(1.1, true, true));
$pkg = array('format' => 'efb-form-package', 'schema' => 1, 'form' => array('form_name' => 'SPLIT-G entries', 'form_structer' => array(
	array('type' => 'form', 'steps' => 1, 'formName' => 'SPLIT-G entries'), array('id_' => '1', 'type' => 'step', 'step' => '1', 'name' => 'S', 'amount' => 0),
	array('id_' => 'nm', 'dataId' => 'nm-id', 'type' => 'text', 'name' => 'Name', 'step' => '1', 'amount' => 1, 'required' => 1),
	array('id_' => 'ag', 'dataId' => 'ag-id', 'type' => 'number', 'name' => 'Age', 'step' => '1', 'amount' => 2, 'required' => 0, 'milen' => '1', 'mlen' => '120'))));
$imp = \Emsfb\Form_Package::import($pkg);
t('import(): a new form and an import_token', !is_wp_error($imp) && $imp['form_id'] > 0 && preg_match('/^[A-Za-z0-9]{32}$/', $imp['import_token']) === 1, true);
$imp2 = \Emsfb\Form_Package::import($pkg, array('import_token' => $imp['import_token']));
t('import() with the same token groups the batch', $imp2['import_token'], $imp['import_token']);
$r = \Emsfb\Form_Package::import_entries($imp['form_id'], array(
	array('answers' => array(array('id_' => 'nm', 'name' => 'Name', 'type' => 'text', 'amount' => 1, 'value' => 'Jane <script>x</script>'), array('id_' => 'ag', 'name' => 'Age', 'type' => 'number', 'amount' => 2, 'value' => '۴۲')), 'date' => '2026-01-02 03:04:05', 'track' => 'IMPORTV11A'),
	array('answers' => array(array('id_' => 'nm', 'name' => 'Name', 'type' => 'text', 'amount' => 1, 'value' => 'Joe'), array('id_' => 'ag', 'name' => 'Age', 'type' => 'number', 'amount' => 2, 'value' => '999'))),
	array('answers' => array())));
t('import_entries(): one stored, the out-of-range age and the empty row reported', array($r['imported'], array_column($r['skipped'], 'code'), $r['skipped'][0]['field_id']), array(1, array('package_entry_refused', 'package_entry_invalid'), 'ag'));
$row = $wpdb->get_row($wpdb->prepare("SELECT track, date, content FROM {$wpdb->prefix}emsfb_msg_ WHERE msg_id = %d", $r['msg_ids'][0]), ARRAY_A);
$content = json_decode(str_replace('\\"', '"', $row['content']), true);
t('the entry: the given date and tracking code, the name sanitized, Persian digits read', array($row['track'], $row['date'], $content[0]['value'], $content[1]['value']), array('IMPORTV11A', '2026-01-02 03:04:05', 'Jane', '42'));
$other = (int) $wpdb->get_var("SELECT form_id FROM {$wpdb->prefix}emsfb_form WHERE form_id NOT IN (" . (int) $imp['form_id'] . ',' . (int) $imp2['form_id'] . ') ORDER BY form_id LIMIT 1');
$e = \Emsfb\Form_Package::delete($other, $imp['import_token']);
t('delete(): a form that import did not create is refused (package_not_imported)', is_wp_error($e) ? $e->get_error_code() : 'deleted', 'package_not_imported');
$ok = \Emsfb\Form_Package::delete($imp['form_id'], $imp['import_token']);
t('delete(): the imported form and its entries are gone', array($ok, (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}emsfb_form WHERE form_id = %d", $imp['form_id'])), (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}emsfb_msg_ WHERE form_id = %d", $imp['form_id']))), array(true, 0, 0));
$again = \Emsfb\Form_Package::delete($imp['form_id'], $imp['import_token']);
t('delete() twice: package_not_imported', is_wp_error($again) ? $again->get_error_code() : 'x', 'package_not_imported');
wp_set_current_user(0);
$v = \Emsfb\Form_Package::delete($imp2['form_id'], $imp['import_token']);
$w = \Emsfb\Form_Package::import_entries($imp2['form_id'], array());
t('a visitor may do neither (package_forbidden)', array(is_wp_error($v) ? $v->get_error_code() : 'x', is_wp_error($w) ? $w->get_error_code() : 'x'), array('package_forbidden', 'package_forbidden'));
wp_set_current_user((int) $admin[0]);
\Emsfb\Form_Package::delete($imp2['form_id'], $imp['import_token']);

echo "\nRESULTS: {$passed} passed, {$failed} failed\n";
exit($failed ? 1 : 0);
