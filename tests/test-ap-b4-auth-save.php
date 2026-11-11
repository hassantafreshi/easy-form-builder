<?php
/**
 * AutoPilot B4 (server): a login or registration form without the fields its handler reads is
 * refused on save with a clear message, in update_form_id_Emsfb() and add_form_structure(); a
 * complete one saves and creates the temporary-links table on update too. Other form types are
 * not affected. Real handlers with WordPress stubs (tests/ap-admin-stubs.php).
 *
 * Run: php tests/test-ap-b4-auth-save.php
 */
require __DIR__ . '/ap-admin-stubs.php';
if (!function_exists('list_files')) { function list_files() { return array(); } }
if (!function_exists('get_transient')) { function get_transient() { return 'no'; } }
require_once __DIR__ . '/../includes/admin/class-Emsfb-create.php';

$pass = 0; $fail = 0;
function b4($label, $ok, $detail = null) {
	global $pass, $fail;
	if ($ok) { $pass++; echo "[PASS] $label\n"; } else { $fail++; echo "[FAIL] $label" . ($detail !== null ? ' ' . json_encode($detail) : '') . "\n"; }
}
function b4_form($type, $ids) {
	$rows = array(array('type' => $type, 'formName' => 'F', 'steps' => 1), array('type' => 'step', 'id_' => '1', 'step' => 1, 'amount' => 1));
	foreach ($ids as $i => $id) $rows[] = array('id_' => $id, 'type' => 'text', 'name' => $id, 'step' => 1, 'amount' => $i + 2);
	return $rows;
}
function b4_call($class, $method, $post) {
	$o = (new ReflectionClass($class))->newInstanceWithoutConstructor();
	$db = new EfbA5Db();
	$p = new ReflectionProperty($class, 'db'); $p->setAccessible(true); $p->setValue($o, $db);
	$_POST = $post;
	$GLOBALS['a5']['actions'] = array();
	try { $o->$method(); } catch (EfbA5Stop $s) { return array($s->payload, $db); }
	return array(null, $db);
}
$upd = function ($form) { return b4_call('\Emsfb\Admin', 'update_form_id_Emsfb', array('value' => json_encode($form), 'id' => '7', 'name' => 'F', 'nonce' => 'n')); };
$new = function ($form) { return b4_call('\Emsfb\Create', 'add_form_structure', array('value' => json_encode($form), 'name' => 'F', 'type' => $form[0]['type'], 'nonce' => 'n')); };

list($r, $db) = $upd(b4_form('login', array('emaillogin')));
b4('update: login form without passwordlogin is refused', is_array($r) && $r['success'] === false && $r['code'] === 'auth_fields_missing' && $r['missing'] === array('passwordlogin'), $r);
b4('the message names the type and the field', is_array($r) && strpos($r['m'], 'T:login') !== false && strpos($r['m'], 'passwordlogin') !== false, $r);
b4('nothing is written', empty($db->rows));

list($r, $db) = $upd(b4_form('login', array('emaillogin', 'passwordlogin', 'x1')));
b4('update: a complete login form saves', is_array($r) && $r['success'] === true && !empty($db->rows), $r);
b4('and creates the temporary-links table, as a new login form does', in_array('create_temporary_links_table_Emsfb', $GLOBALS['a5']['actions'], true), $GLOBALS['a5']['actions']);

list($r, $db) = $new(b4_form('register', array('usernameRegisterEFB', 'emailRegisterEFB')));
b4('create: register form without passwordRegisterEFB is refused', is_array($r) && $r['success'] === false && $r['missing'] === array('passwordRegisterEFB') && empty($db->rows), $r);
list($r, $db) = $new(b4_form('register', array('usernameRegisterEFB', 'passwordRegisterEFB', 'emailRegisterEFB')));
b4('create: complete register form is inserted', is_array($r) && $r['success'] === true && $r['r'] === 'insert', $r);
list($r, $db) = $upd(b4_form('form', array('a1')));
b4('a normal form is not affected (no table action, saves)', is_array($r) && $r['success'] === true && !in_array('create_temporary_links_table_Emsfb', $GLOBALS['a5']['actions'], true), $r);

echo "\n========================================\nRESULTS: $pass passed, $fail failed\n========================================\n";
exit($fail > 0 ? 1 : 0);
