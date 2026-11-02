<?php
/**
 * Saving a form must never say "updated" when nothing was stored.
 *
 * update_form_id_Emsfb answered "updated" whatever $wpdb->update returned,
 * and the builder then deleted the local draft: an owner was told the form
 * was saved and lost it. Now a refused update, or a form that no longer
 * exists, answers success:false; an unchanged save (0 rows) still succeeds.
 *
 * Run inside WordPress, or with EFB_WP_LOAD pointing at wp-load.php:
 *   php tests/test-form-update-not-saved.php
 */
$wp_load = getenv('EFB_WP_LOAD') ?: dirname(__DIR__, 4) . '/wp-load.php';
if (!is_readable($wp_load)) { echo "[SKIP] WordPress bootstrap was not found at {$wp_load}\n"; exit(0); }
if (!defined('DOING_AJAX')) define('DOING_AJAX', true);
require_once $wp_load;
require_once ABSPATH . 'wp-admin/includes/admin.php';

$passed = 0; $failed = 0;
function efb_fu_check($label, $ok) { global $passed, $failed; if ($ok) { $passed++; echo "[PASS] {$label}\n"; } else { $failed++; echo "[FAIL] {$label}\n"; } }

$admin = get_users(['role' => 'administrator', 'number' => 1]);
if (empty($admin)) { echo "[SKIP] no administrator\n"; exit(0); }
wp_set_current_user($admin[0]->ID);

if (!class_exists('Emsfb\\Admin')) {
	require_once dirname(__DIR__) . '/includes/admin/class-Emsfb-admin.php';
}
$admin_class = class_exists('Emsfb\\Admin') && method_exists('Emsfb\\Admin', 'update_form_id_Emsfb') ? 'Emsfb\\Admin' : null;
efb_fu_check('the admin class has update_form_id_Emsfb()', null !== $admin_class);
if (null === $admin_class) { echo "\nRESULTS: {$passed} passed, {$failed} failed\n"; exit(1); }
$ref = new ReflectionClass($admin_class);
$admin_obj = $ref->newInstanceWithoutConstructor();

add_filter('wp_die_ajax_handler', function () { return function () { throw new RuntimeException('efb-die'); }; });
$call = function ($id, $value) use ($admin_obj) {
	$_POST = $_REQUEST = [
		'nonce' => wp_create_nonce('wp_rest'),
		'id'    => (string) $id,
		'name'  => 'Update check form',
		'value' => $value,
	];
	ob_start();
	try { $admin_obj->update_form_id_Emsfb(); } catch (RuntimeException $e) { /* wp_die */ }
	$out = ob_get_clean();
	$json = json_decode($out, true);
	return is_array($json) && isset($json['data']) ? $json['data'] : ['raw' => $out];
};

global $wpdb;
$table = $wpdb->prefix . 'emsfb_form';
$form  = json_encode([['type' => 'form', 'formName' => 'Update check form', 'steps' => 1, 'email' => '', 'sendEmail' => false]]);
$wpdb->insert($table, ['form_name' => 'Update check form', 'form_structer' => $form, 'form_email' => '', 'form_created_by' => get_current_user_id(), 'form_type' => 'form']);
$id = (int) $wpdb->insert_id;

try {
	$r1 = $call($id, $form);
	efb_fu_check('an existing form saves: success and "updated"', !empty($r1['success']) && 'updated' === ($r1['r'] ?? ''));
	$r2 = $call($id, $form);
	efb_fu_check('saving it unchanged (0 rows) still succeeds', !empty($r2['success']) && 'updated' === ($r2['r'] ?? ''));
	$r3 = $call(999999, $form);
	efb_fu_check('a form that no longer exists is not reported as saved', empty($r3['success']) && 'updated' !== ($r3['r'] ?? ''));
	$break = function ($q) use ($table) { return (0 === stripos(ltrim($q), 'UPDATE') && false !== strpos($q, $table)) ? 'UPDATE `no_such_table_efb` SET x = 1' : $q; };
	add_filter('query', $break);
	$suppress = $wpdb->suppress_errors(true);
	$r4 = $call($id, $form);
	$wpdb->suppress_errors($suppress);
	remove_filter('query', $break);
	efb_fu_check('a refused update is not reported as saved', empty($r4['success']) && 'updated' !== ($r4['r'] ?? ''));
	$js = file_get_contents(dirname(__DIR__) . '/includes/admin/assets/js/admin-efb.js');
	efb_fu_check('the builder needs success:true before it clears the draft', false !== strpos($js, '(res.data.r == "update" || res.data.r == "updated") && res.data.success == true'));
} finally {
	$wpdb->delete($table, ['form_id' => $id]);
}
echo "\nRESULTS: {$passed} passed, {$failed} failed\n";
exit($failed ? 1 : 0);
