<?php
/**
 * F10-D: who sees a field (vis_mode all | in | out | roles, vis_roles), without WordPress (stubs for wp_roles(),
 * wp_get_current_user() and a WordPress-faithful sanitize_text_field / sanitize_key, as tests/test-layout-keys.php).
 *
 *  1  the save (sanitize_obj_msg_efb → Layout::sanitize_key): vis_mode is one of four (else "all");
 *     vis_roles "editor,<x>,nonexistent" is stored "editor" (slugs, roles of this site only, no duplicates); an
 *     array is stored empty; vis_empty / vis_view (the page's marks) are never stored; the JS twin agrees
 *     (EFBLayoutCore.sanitizeKey, with the builder's efb_var.roles);
 *  2  row_visible(): guest, subscriber, editor and administrator for every mode (an administrator follows the
 *     rule: spec open question 3); "roles" with no role shows the field to nobody; the login / register rows are
 *     never hidden; the public view (a cached page) keeps only fields shown to all;
 *  3  filter_rows(): the field leaves with its options / matrix rows; a hidden repeater group takes its fields, a
 *     hidden section the fields it holds; row 0 and the steps stay, a step left without a field gets vis_empty;
 *     stdClass rows (the renderer) and arrays (the submit) alike; a form without a rule is returned as it was;
 *  4  filter_submission(): rows of a hidden field are dropped (id_, an option's id_ob, a group's rg), the field is
 *     out of the list the required check reads; login / payment rows pass; the count of dropped rows; the public
 *     view narrows a signed-in sender to the shared fields;
 *  5  the registry: setting:vis_mode is Pro, the rule is enforced on every plan; efb_var.roles for the builder.
 *
 * Run: php tests/test-field-visibility.php
 */
define('ABSPATH', __DIR__ . '/');
define('EMSFB_PLUGIN_VERSION', '4.3.1-test');
$EFB = __DIR__ . '/..';

function sanitize_text_field($v) {
	if (is_array($v) || is_object($v)) return '';
	$s = strip_tags((string) $v);
	$s = preg_replace('/%[a-f0-9]{2}/i', '', $s);
	$s = preg_replace('/[\r\n\t ]+/', ' ', $s);
	return trim($s);
}
function wp_check_invalid_utf8($s) { return preg_match('//u', (string) $s) ? (string) $s : ''; }
function sanitize_email($v) { return filter_var((string) $v, FILTER_SANITIZE_EMAIL); }
function sanitize_url($v) { return filter_var((string) $v, FILTER_SANITIZE_URL); }
function sanitize_key($v) { return preg_replace('/[^a-z0-9_\-]/', '', strtolower((string) $v)); }
function is_email($v) { return filter_var((string) $v, FILTER_VALIDATE_EMAIL) !== false; }
function esc_url_raw($v) { return filter_var((string) $v, FILTER_SANITIZE_URL); }
function wp_kses_post($v) { return strip_tags((string) $v, '<b><strong><em><i><br><p><a>'); }
function esc_html__($s, $d = null) { return $s; }
function esc_html($s) { return htmlspecialchars((string) $s, ENT_QUOTES); }
function __($s, $d = null) { return $s; }
function apply_filters($h, $v) { return $v; }
function add_filter() { return true; }
function add_action() { return true; }
function do_action() {}
function wp_cache_get() { return false; }
function wp_cache_set() { return true; }
function wp_cache_delete() { return true; }
function get_option($k, $d = false) { return $d; }
function update_option() { return true; }
function absint($v) { return abs(intval($v)); }
function wp_json_encode($v) { return json_encode($v); }
function wp_unslash($v) { return is_string($v) ? stripslashes($v) : $v; }
class EfbTestRoles { public function get_names() { return array('administrator' => 'Administrator', 'editor' => 'Editor', 'author' => 'Author', 'subscriber' => 'Subscriber'); } }
function wp_roles() { return new EfbTestRoles(); }
function translate_user_role($n) { return $n; }
$GLOBALS['efb_user'] = null;
function wp_get_current_user() { return $GLOBALS['efb_user'] ?: (object) array('ID' => 0, 'roles' => array()); }
function is_user_logged_in() { return !empty(wp_get_current_user()->ID); }
$GLOBALS['efb_plan'] = 'pro';
function efb_plan_at_least_efb($need) { $r = array('free' => 0, 'free_plus' => 1, 'pro' => 2); return $r[$GLOBALS['efb_plan']] >= $r[$need]; }

require $EFB . '/includes/json-efb.php';
require $EFB . '/includes/functions.php';
require_once $EFB . '/includes/class-Emsfb-layout.php';
eval('namespace Emsfb; class Builder_Api { public static function plan_at_least($need, $package = null) { $r = array("free" => 0, "free_plus" => 1, "pro" => 2); $have = $package === null ? $GLOBALS["efb_plan"] : array(1 => "pro", 2 => "free", 3 => "free_plus")[(int) $package]; return $r[$have] >= (isset($r[$need]) ? $r[$need] : 2); } }');
use Emsfb\Layout;

$passed = 0; $failed = 0;
function t($label, $actual, $expected) {
	global $passed, $failed;
	if ($actual === $expected) { $passed++; echo "[PASS] {$label}\n"; return; }
	$failed++; echo "[FAIL] {$label}\n      expected " . json_encode($expected, JSON_UNESCAPED_UNICODE) . "\n      actual   " . json_encode($actual, JSON_UNESCAPED_UNICODE) . "\n";
}
$fn = (new ReflectionClass('efbFunction'))->newInstanceWithoutConstructor();
$save = function (array $rows) use ($fn) { return $fn->sanitize_obj_msg_efb(json_decode(wp_unslash(addslashes(json_encode($rows))), true)); };
$guest = (object) array('ID' => 0, 'roles' => array());
$sub = (object) array('ID' => 5, 'roles' => array('subscriber'));
$editor = (object) array('ID' => 6, 'roles' => array('editor'));
$admin = (object) array('ID' => 1, 'roles' => array('administrator'));
$f = function ($id, $mode, $roles = null, $extra = array()) { $r = array_merge(array('id_' => $id, 'type' => 'text', 'name' => $id, 'step' => '1', 'vis_mode' => $mode), $extra); if ($roles !== null) $r['vis_roles'] = $roles; return $r; };
$form = function (array $fields, $steps = 1) {
	$rows = array(array('type' => 'form', 'steps' => $steps, 'formName' => 'V', 'thank_you' => 'rdrct', 'rePage' => 'https:@efb@@efb@example.test@efb@x'));
	for ($i = 1; $i <= $steps; $i++) $rows[] = array('id_' => 's' . $i, 'type' => 'step', 'step' => (string) $i, 'name' => 'Step ' . $i);
	// Fields after their step (as the builder keeps them).
	$out = array($rows[0]);
	for ($i = 1; $i <= $steps; $i++) { $out[] = $rows[$i]; foreach ($fields as $x) if ((string) ($x['step'] ?? '1') === (string) $i) $out[] = $x; }
	return $out;
};

/* 1 the save */
$s = $save($form(array($f('a', 'roles', 'Editor, <x>,editor,nonexistent'), $f('b', 'bogus'), $f('c', 'in', array('editor')), $f('d', 'out', null, array('vis_empty' => '1', 'vis_view' => 'public')))));
t('1 vis_roles "Editor, <x>,editor,nonexistent" → "editor" (this site\'s roles, no duplicates)', $s[2]['vis_roles'], 'editor');
t('1 vis_mode "roles" kept', $s[2]['vis_mode'], 'roles');
t('1 an unknown vis_mode → "all"', $s[3]['vis_mode'], 'all');
t('1 an array vis_roles is not stored', array_key_exists('vis_roles', $s[4]), false);
t('1 vis_empty / vis_view (the page\'s marks) are never stored', array(array_key_exists('vis_empty', $s[5]), array_key_exists('vis_view', $s[5])), array(false, false));
t('1 vis_roles "author,editor" keeps the order given', Layout::sanitize_key('vis_roles', 'author,editor', 'text'), 'author,editor');
$js = json_decode(shell_exec('node -e ' . escapeshellarg('global.window = { efb_var: { roles: [{slug:"administrator"},{slug:"editor"},{slug:"author"},{slug:"subscriber"}] } }; const C = require(' . json_encode($EFB . '/public/assets/js/layout-efb.js') . '); console.log(JSON.stringify([C.sanitizeKey("vis_roles", "Editor, <x>,editor,nonexistent"), C.sanitizeKey("vis_mode", "bogus"), C.sanitizeKey("vis_mode", "out"), C.sanitizeKey("vis_empty", "1")]))')), true);
t('1 the JS twin (builder, efb_var.roles) stores the same', $js, array('editor', 'all', 'out', ''));

/* 2 row_visible */
$modes = array('all' => $f('x', 'all'), 'in' => $f('x', 'in'), 'out' => $f('x', 'out'), 'roles editor' => $f('x', 'roles', 'editor'), 'roles none' => $f('x', 'roles', ''), 'roles editor,subscriber' => $f('x', 'roles', 'editor,subscriber'));
$who = array('guest' => $guest, 'subscriber' => $sub, 'editor' => $editor, 'administrator' => $admin);
$got = array();
foreach ($modes as $m => $row) foreach ($who as $w => $u) $got[$m][$w] = Layout::row_visible($row, $u);
t('2 who sees what (an administrator follows the rule; "roles" without a role: nobody)', $got, array(
	'all' => array('guest' => true, 'subscriber' => true, 'editor' => true, 'administrator' => true),
	'in' => array('guest' => false, 'subscriber' => true, 'editor' => true, 'administrator' => true),
	'out' => array('guest' => true, 'subscriber' => false, 'editor' => false, 'administrator' => false),
	'roles editor' => array('guest' => false, 'subscriber' => false, 'editor' => true, 'administrator' => false),
	'roles none' => array('guest' => false, 'subscriber' => false, 'editor' => false, 'administrator' => false),
	'roles editor,subscriber' => array('guest' => false, 'subscriber' => true, 'editor' => true, 'administrator' => false),
));
t('2 no key = everyone (an old form)', Layout::row_visible(array('id_' => 'o', 'type' => 'text'), $guest), true);
t('2 the login / register rows are never hidden', Layout::row_visible(array('id_' => 'passwordlogin', 'type' => 'password', 'vis_mode' => 'in'), $guest), true);
t('2 the public view (a cached page): only fields shown to all, whoever asks', array(Layout::row_visible($f('x', 'in'), $editor, true), Layout::row_visible($f('x', 'all'), $editor, true)), array(false, true));
$GLOBALS['efb_user'] = $editor;
t('2 the current user by default', Layout::row_visible($f('x', 'roles', 'editor')), true);
$GLOBALS['efb_user'] = null;

/* 3 filter_rows */
$rows = $form(array(
	$f('pub', 'all'),
	array('id_' => 'ch', 'type' => 'radio', 'name' => 'Choice', 'step' => '1', 'vis_mode' => 'roles', 'vis_roles' => 'editor'),
	array('id_' => 'o1', 'type' => 'option', 'parent' => 'ch', 'value' => 'Secret A', 'step' => '1'),
	array('id_' => 'o2', 'type' => 'option', 'parent' => 'ch', 'value' => 'Secret B', 'step' => '1'),
	array('id_' => 'g', 'type' => 'repeater', 'name' => 'Team', 'step' => '1', 'vis_mode' => 'in'),
	array('id_' => 'gk', 'type' => 'text', 'name' => 'Member', 'step' => '1', 'group' => 'g'),
	array('id_' => 'sec', 'type' => 'section', 'name' => 'Staff', 'step' => '2', 'vis_mode' => 'roles', 'vis_roles' => 'editor'),
	array('id_' => 'm1', 'type' => 'text', 'name' => 'Staff note', 'step' => '2'),
	array('id_' => 'm2', 'type' => 'email', 'name' => 'Staff mail', 'step' => '2', 'required' => '1'),
	array('id_' => 'sec2', 'type' => 'section', 'name' => 'Open', 'step' => '3'),
	$f('p3', 'all', null, array('step' => '3')),
), 3);
$ids = function ($rs) { return array_values(array_map(function ($r) { return is_object($r) ? (string) $r->id_ : (string) ($r['id_'] ?? '#0'); }, array_slice($rs, 1))); };
$G = Layout::filter_rows($rows, $guest);
t('3 guest: the editor-only choice leaves with its options, the group with its fields, the section with what it holds', $ids($G), array('s1', 'pub', 's2', 's3', 'sec2', 'p3'));
t('3 guest: step 2 (every field hidden) gets vis_empty; steps 1 and 3 do not', array(isset($G[1]['vis_empty']), $G[3]['vis_empty'] ?? null, isset($G[4]['vis_empty'])), array(false, '1', false));
t('3 row 0 untouched', $G[0], $rows[0]);
$E = Layout::filter_rows($rows, $editor);
t('3 editor: everything', $ids($E), $ids($rows));
$S = Layout::filter_rows($rows, $sub);
t('3 subscriber: the group (signed-in) stays, the editor parts go', $ids($S), array('s1', 'pub', 'g', 'gk', 's2', 's3', 'sec2', 'p3'));
$objs = json_decode(json_encode($rows));
$GO = Layout::filter_rows($objs, $guest);
t('3 stdClass rows (the renderer) alike, step marked', array($ids($GO), $GO[3]->vis_empty ?? null, isset($objs[3]->vis_empty)), array($ids($G), '1', false));
$plain = $form(array($f('a', 'all'), array('id_' => 'b', 'type' => 'text', 'step' => '1')));
t('3 a form without a rule comes back as it was', Layout::filter_rows($plain, $guest), $plain);
t('3 has_vis: true with a rule, false on an old form', array(Layout::has_vis($rows), Layout::has_vis($plain)), array(true, false));

/* 4 filter_submission */
$sent = array(
	array('id_' => 'pub', 'name' => 'pub', 'type' => 'text', 'value' => 'ok'),
	array('id_' => 'o1', 'id_ob' => 'ch', 'name' => 'Choice', 'type' => 'radio', 'value' => 'Secret A'),
	array('id_' => 'gk', 'rg' => 'g', 'ri' => 1, 'name' => 'Member', 'type' => 'text', 'value' => 'x'),
	array('id_' => 'm2', 'name' => 'Staff mail', 'type' => 'email', 'value' => 'a@b.test'),
	array('id_' => 'payment', 'type' => 'payment', 'value' => 'paid'),
	array('id_' => 'passwordlogin', 'type' => 'password', 'value' => 'x'),
);
list($fields, $kept, $dropped) = Layout::filter_submission($rows, $sent, $guest);
t('4 guest: the hidden fields\' rows are dropped (id_, id_ob, rg), payment / login rows pass', array(array_column($kept, 'id_'), $dropped), array(array('pub', 'payment', 'passwordlogin'), 3));
t('4 guest: the hidden required field is not in the list the required check reads', in_array('m2', array_column($fields, 'id_'), true), false);
t('4 the list the submit reads carries no page mark (vis_empty)', array_filter(array_column($fields, 'vis_empty')), array());
list($fields, $kept, $dropped) = Layout::filter_submission($rows, $sent, $editor);
t('4 editor: every row kept, the field (required) in the list', array(count($kept), $dropped, in_array('m2', array_column($fields, 'id_'), true)), array(6, 0, true));
list($fields, $kept, $dropped) = Layout::filter_submission($rows, $sent, $editor, true);
t('4 the public view narrows a signed-in sender to the shared fields', array(array_column($kept, 'id_'), in_array('m2', array_column($fields, 'id_'), true)), array(array('pub', 'payment', 'passwordlogin'), false));
list($fields, $kept, $dropped) = Layout::filter_submission($plain, $sent, $guest);
t('4 a form without a rule: nothing changes', array($fields, $kept, $dropped), array($plain, $sent, 0));

/* 5 registry and builder vars */
$GLOBALS['efb_plan'] = 'free';
$caps = Layout::capabilities(array('features' => array(), 'plan' => array('package_type' => 2)));
t('5 setting:vis_mode is Pro (not open on Free)', array($caps['features']['setting:vis_mode']['package'], $caps['features']['setting:vis_mode']['available_now'], $caps['features']['setting:vis_mode']['form_keys']), array('pro', false, array('vis_mode', 'vis_roles')));
t('5 the rule is enforced on every plan (filter_rows has no plan check)', $ids(Layout::filter_rows($rows, $guest)), $ids($G));
t('5 capabilities.layout.visibility lists the modes and the site roles', array($caps['layout']['visibility']['modes'], $caps['layout']['visibility']['roles']), array(array('all', 'in', 'out', 'roles'), array('administrator', 'editor', 'author', 'subscriber')));
$v = Layout::localize_vars(array('x' => 1));
t('5 efb_var.roles = [{slug, name}]', $v['roles'][1], array('slug' => 'editor', 'name' => 'Editor'));

echo "\nRESULTS: {$passed} passed, {$failed} failed\n";
exit($failed ? 1 : 0);
