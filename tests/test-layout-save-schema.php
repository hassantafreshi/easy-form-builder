<?php
/**
 * F10 on save and in forms/{id}/schema, without WordPress:
 *
 *  - Layout::normalize_rows() (every save: builder, AutoPilot, import): rich_text only on a textarea, esign_* only on
 *    an e-signature, vis_* not on a step / option / login row, vis_roles only with "roles", a section never in a
 *    repeater group, no review page on a form with a Stripe / PayPal / PersiaPay field (layout_review_payment);
 *    against the stored form, a security_changes warning (layout_security_change: {field, key, from, to}) when who
 *    sees a field changed or a rich editor was switched on; a new form (no stored rows) reports nothing; an old form
 *    comes back unchanged;
 *  - Layout::schema_field(): a rich textarea says rich_text and takes plain text; an e-signature is visitor_only
 *    with its modes;
 *  - Layout::schema(): the review page and the sections (title, step, fold, the fields they hold) as the viewer gets
 *    them (a guest: a hidden section is not listed; a repeater group does not name a child hidden from them).
 *
 * Run: php tests/test-layout-save-schema.php
 */
define('ABSPATH', __DIR__ . '/');
function apply_filters($h, $v) { return $v; }
function add_filter() { return true; }
function sanitize_text_field($v) { return is_scalar($v) ? trim(strip_tags((string) $v)) : ''; }
function sanitize_key($v) { return preg_replace('/[^a-z0-9_\-]/', '', strtolower((string) $v)); }
class EfbTestRoles { public function get_names() { return array('administrator' => 'Administrator', 'editor' => 'Editor'); } }
function wp_roles() { return new EfbTestRoles(); }
function wp_get_current_user() { return (object) array('ID' => 0, 'roles' => array()); }
function efb_plan_at_least_efb($n) { return true; }
require __DIR__ . '/../includes/class-Emsfb-layout.php';
eval('namespace Emsfb; class Builder_Api { public static function plan_at_least($n, $p = null) { return true; } } class Calendar_Rest { public static $rows = array(); public static function form_rows($id) { return self::$rows; } }');
use Emsfb\Layout;

$passed = 0; $failed = 0;
function t($label, $actual, $expected) {
	global $passed, $failed;
	if ($actual === $expected) { $passed++; echo "[PASS] {$label}\n"; return; }
	$failed++; echo "[FAIL] {$label}\n      expected " . json_encode($expected, JSON_UNESCAPED_UNICODE) . "\n      actual   " . json_encode($actual, JSON_UNESCAPED_UNICODE) . "\n";
}
$r0 = array('type' => 'form', 'steps' => 1);
$st = array('id_' => '1', 'type' => 'step', 'step' => '1');

/* normalize_rows */
$rows = array($r0 + array('review_before_submit' => '1'), $st + array('vis_mode' => 'in'),
	array('id_' => 'a', 'type' => 'text', 'rich_text' => '1', 'rich_toolbar' => 'full', 'esign_mode' => 'type', 'vis_mode' => 'in', 'vis_roles' => 'editor'),
	array('id_' => 'b', 'type' => 'textarea', 'rich_text' => '1'),
	array('id_' => 'c', 'type' => 'esign', 'esign_mode' => 'both', 'vis_mode' => 'roles', 'vis_roles' => 'editor'),
	array('id_' => 'o', 'type' => 'option', 'parent' => 'c', 'vis_mode' => 'out'),
	array('id_' => 's', 'type' => 'section', 'group' => 'g'),
	array('id_' => 'passwordlogin', 'type' => 'password', 'vis_mode' => 'out'));
list($n, $w) = Layout::normalize_rows($rows, null);
t('save: rich / esign keys only on their types, vis_roles only with "roles"', array_intersect_key($n[2], array_flip(array('rich_text', 'rich_toolbar', 'esign_mode', 'vis_mode', 'vis_roles'))), array('vis_mode' => 'in'));
t('save: a textarea keeps rich_text, an e-signature its esign_mode and rule', array($n[3]['rich_text'], $n[4]['esign_mode'], $n[4]['vis_roles']), array('1', 'both', 'editor'));
t('save: no rule on a step, an option or a login row', array(isset($n[1]['vis_mode']), isset($n[5]['vis_mode']), isset($n[7]['vis_mode'])), array(false, false, false));
t('save: a section never in a repeater group', isset($n[6]['group']), false);
t('save: a new form reports no security change', array_column($w, 'code'), array());
$pay = array($r0 + array('review_before_submit' => '1'), $st, array('id_' => 'p', 'type' => 'stripe'));
list($n, $w) = Layout::normalize_rows($pay, null);
t('save: no review page on a form with a Stripe field (layout_review_payment)', array($n[0]['review_before_submit'], array_column($w, 'code')), array('0', array('layout_review_payment')));
$old = array($r0, $st, array('id_' => 'x', 'type' => 'text'), array('id_' => 'y', 'type' => 'textarea'), array('id_' => 'z', 'type' => 'text', 'vis_mode' => 'roles', 'vis_roles' => 'editor'));
$new = array($r0, $st, array('id_' => 'x', 'type' => 'text', 'vis_mode' => 'in'), array('id_' => 'y', 'type' => 'textarea', 'rich_text' => '1'), array('id_' => 'z', 'type' => 'text', 'vis_mode' => 'roles', 'vis_roles' => 'administrator'));
list($n, $w) = Layout::normalize_rows($new, $old);
$sc = array_values(array_filter($w, function ($x) { return $x['code'] === 'layout_security_change'; }));
t('save: who sees a field / a rich editor switched on → layout_security_change with the changes', isset($sc[0]['changes']) ? $sc[0]['changes'] : null, array(
	array('field' => 'x', 'key' => 'vis_mode', 'from' => 'all', 'to' => 'in'),
	array('field' => 'y', 'key' => 'rich_text', 'from' => '0', 'to' => '1'),
	array('field' => 'z', 'key' => 'vis_roles', 'from' => 'editor', 'to' => 'administrator'),
));
list($n, $w) = Layout::normalize_rows($old, $old);
t('save: an old form comes back unchanged, nothing reported', array($n, $w), array($old, array()));

/* schema_field */
t('schema: a rich textarea says so and takes plain text', array_keys(Layout::schema_field(array('id' => 'b', 'fill' => array('api' => 'dom')), array('type' => 'textarea', 'rich_text' => '1'), 1)), array('id', 'fill', 'rich_text'));
$e = Layout::schema_field(array('id' => 'c'), array('type' => 'esign', 'esign_mode' => 'both'), 1);
t('schema: an e-signature is visitor_only, with its ways to sign', array($e['assistant'], $e['signature']['modes']), array('visitor_only', array('draw', 'type')));
t('schema: a plain textarea is unchanged', Layout::schema_field(array('id' => 'q'), array('type' => 'textarea'), 1), array('id' => 'q'));

/* schema (a guest) */
\Emsfb\Calendar_Rest::$rows = array($r0 + array('review_before_submit' => '1'), $st,
	array('id_' => 'sa', 'type' => 'section', 'name' => 'Contact', 'step' => '1', 'collapsible' => '1', 'collapsed' => '1'),
	array('id_' => 'em', 'type' => 'email', 'step' => '1'),
	array('id_' => 'g', 'type' => 'repeater', 'step' => '1'),
	array('id_' => 'gk', 'type' => 'text', 'step' => '1', 'group' => 'g'),
	array('id_' => 'gh', 'type' => 'text', 'step' => '1', 'group' => 'g', 'vis_mode' => 'in'),
	array('id_' => 'sb', 'type' => 'section', 'name' => 'Staff', 'step' => '1', 'vis_mode' => 'roles', 'vis_roles' => 'editor'),
	array('id_' => 'nt', 'type' => 'text', 'step' => '1'));
$out = array('fields' => array(array('id' => 'em'), array('id' => 'g', 'repeater' => array('children' => array('gk', 'gh'))), array('id' => 'gk')));
$o = Layout::schema($out, 9, array());
t('schema: the review page is announced (the visitor confirms)', $o['review']['on'], true);
t('schema: a guest gets the open section with its fields, not the editors\' one', array_map(function ($s) { return array($s['id'], $s['collapsible'], $s['collapsed'], $s['fields']); }, $o['sections']), array(array('sa', true, true, array('em', 'g', 'gk'))));
t('schema: the group does not name a child hidden from the viewer', $o['fields'][1]['repeater']['children'], array('gk'));

echo "\nRESULTS: {$passed} passed, {$failed} failed\n";
exit($failed ? 1 : 0);
