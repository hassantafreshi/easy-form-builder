<?php
/**
 * The AI builder core APIs without WordPress (stubs): docs/autopilot-integration.md "AI builder core APIs".
 *
 *  AI-2  Form_Update::apply(): every op (form, field, insert after / before / at a step / a step, remove with options
 *        and a repeater's fields, options (a choice's own id_ kept), name), the A2 numbering, and every refusal
 *        (invalid_patch, unknown_field, duplicate_id, orphan_option, unknown_target); hash_of() is stable and changes
 *        with the rows and the name; Form_Update::gate() against a registry: a new field type of an add-on that is off
 *        (addon_required) or above the plan (plan_required), a Free Plus setting turned on (plan_required), turned off
 *        (allowed), a key several gateways share (not judged), what the form already had (not judged), a phone twin
 *        equal to the desktop value (not judged), an absent add-on's key (Addon_Keys), a step over the plan's limit;
 *  AI-4  Form_Status: the stored value 2 is a draft, every other value published; render_note() for a visitor ('')
 *        and an admin (the notice), null for a published form;
 *  AI-7  the plugin header and EMSFB_PLUGIN_VERSION are the same version; no hard-coded version in the registry.
 *
 * Run: php tests/test-ai-core-apis.php
 */
define('ABSPATH', __DIR__ . '/');
$passed = 0; $failed = 0;
function t($name, $ok, $detail = null) { global $passed, $failed; if ($ok) { echo "[PASS] {$name}\n"; $passed++; } else { echo "[FAIL] {$name}" . ($detail !== null ? '  ' . substr(json_encode($detail, JSON_UNESCAPED_SLASHES), 0, 700) : '') . "\n"; $failed++; } }
class WP_Error { public $c; public $m; public $d; function __construct($c, $m = '', $d = null) { $this->c = $c; $this->m = $m; $this->d = $d; } function get_error_code() { return $this->c; } function get_error_message() { return $this->m; } function get_error_data() { return $this->d; } }
function is_wp_error($x) { return $x instanceof WP_Error; }
function apply_filters($h, $v) { return $v; }
function __($s) { return $s; }
function esc_html($s) { return htmlspecialchars((string) $s, ENT_QUOTES); }
function esc_html__($s) { return htmlspecialchars((string) $s, ENT_QUOTES); }
function esc_url($s) { return (string) $s; }
function admin_url($p = '') { return 'https://site.test/wp-admin/' . $p; }
$GLOBALS['viewer_admin'] = false;
class FnStub { function user_permission_efb_admin_dashboard() { return $GLOBALS['viewer_admin']; } }
function get_efbFunction() { return new FnStub(); }
class DbStub {
	public $prefix = 'wp_'; public $drafts = array();
	function prepare($q, ...$a) { return $q; }
	function get_results($q) { return array((object) array('Field' => 'status')); }
	function get_col($q) { return $this->drafts; }
}
$GLOBALS['wpdb'] = new DbStub();
require __DIR__ . '/../includes/class-Emsfb-form-package.php';
require __DIR__ . '/../includes/class-Emsfb-addon-keys.php';
require __DIR__ . '/../includes/class-Emsfb-form-update.php';
require __DIR__ . '/../includes/class-Emsfb-form-status.php';
use Emsfb\Form_Update as U;

$form = function () {
	return array(
		array('type' => 'form', 'steps' => 1, 'formName' => 'F'),
		array('id_' => '1', 'type' => 'step', 'dataId' => '1', 'step' => 1, 'amount' => 1, 'name' => 'S1'),
		array('id_' => 'nm', 'dataId' => 'nm-id', 'type' => 'text', 'name' => 'Name', 'step' => 1, 'amount' => 2),
		array('id_' => 'ch', 'dataId' => 'ch-id', 'type' => 'radio', 'name' => 'Pick', 'step' => 1, 'amount' => 3),
		array('id_' => 'o1', 'dataId' => 'o1-id', 'type' => 'option', 'parent' => 'ch', 'value' => 'A', 'id_op' => 'o1', 'step' => 1, 'amount' => 3),
		array('id_' => 'o2', 'dataId' => 'o2-id', 'type' => 'option', 'parent' => 'ch', 'value' => 'B', 'id_op' => 'o2', 'step' => 1, 'amount' => 3),
		array('id_' => 'em', 'dataId' => 'em-id', 'type' => 'email', 'name' => 'Mail', 'step' => 1, 'amount' => 4),
	);
};
$ids = function ($rows) { return array_map(function ($r) { return isset($r['id_']) ? $r['id_'] : '#0'; }, $rows); };
$code = function ($r) { return $r instanceof WP_Error ? $r->get_error_code() : 'ok'; };

/* ---------------------------------------------------------------- apply */
list($r, $name, $ch, $fc) = U::apply($form(), array(array('op' => 'field', 'id' => 'nm', 'set' => array('name' => 'Full name', 'required' => 1), 'unset' => array('amount'))), 'F');
t('field: keys set (label, required); amount comes back from the numbering', $r[2]['name'] === 'Full name' && $r[2]['required'] === 1 && $r[2]['amount'] === 2 && $ch === array('nm') && $fc === false, $r[2]);
list($r, , , $fc) = U::apply($form(), array(array('op' => 'form', 'set' => array('email_sub' => 'Hi'), 'unset' => array('formName'))), 'F');
t('form: row 0 set and unset, reported as form_changed', $r[0]['email_sub'] === 'Hi' && !isset($r[0]['formName']) && $fc === true);
list($r, , $ch) = U::apply($form(), array(array('op' => 'insert', 'rows' => array(array('id_' => 'ph', 'type' => 'tel', 'name' => 'Phone')), 'after' => 'ch')), 'F');
t('insert after a choice field: after its last option, dataId filled, numbered (fields one after another, options with their parent)', $ids($r) === array('#0', '1', 'nm', 'ch', 'o1', 'o2', 'ph', 'em') && $r[6]['dataId'] === 'ph-id' && $r[6]['amount'] === 4 && $r[7]['amount'] === 5 && $r[4]['amount'] === 3 && $ch === array('ph'), $ids($r));
list($r) = U::apply($form(), array(array('op' => 'insert', 'rows' => array(array('id_' => 'ph', 'type' => 'tel')), 'before' => 'nm')), 'F');
t('insert before a field', $ids($r) === array('#0', '1', 'ph', 'nm', 'ch', 'o1', 'o2', 'em'), $ids($r));
list($r) = U::apply($form(), array(array('op' => 'insert', 'rows' => array(array('id_' => 'x', 'type' => 'step', 'name' => 'Two'))), array('op' => 'insert', 'rows' => array(array('id_' => 'lt', 'type' => 'text')), 'step' => 2)), 'F');
t('a step at the end (id 2, steps counted), then a field at the end of step 2 (its step 2)', $ids($r) === array('#0', '1', 'nm', 'ch', 'o1', 'o2', 'em', '2', 'lt') && $r[0]['steps'] === 2 && $r[8]['step'] === 2 && $r[7]['step'] === 2, $ids($r));
list($r, , $ch) = U::apply($form(), array(array('op' => 'remove', 'ids' => array('ch'))), 'F');
t('remove: the field with its option rows', $ids($r) === array('#0', '1', 'nm', 'em') && $ch === array('ch') && $r[3]['amount'] === 3, $ids($r));
$rep = $form();
$rep[] = array('id_' => 'gp', 'type' => 'repeater', 'name' => 'G', 'step' => 1, 'amount' => 5);
$rep[] = array('id_' => 'gk', 'type' => 'text', 'group' => 'gp', 'step' => 1, 'amount' => 6);
list($r) = U::apply($rep, array(array('op' => 'remove', 'ids' => array('gp'))), 'F');
t('remove a repeater group: its fields go too', !in_array('gk', $ids($r), true) && !in_array('gp', $ids($r), true), $ids($r));
$pt = array(
	array('op' => 'options', 'id' => 'ch', 'options' => array('X', array('value' => 'Y', 'opt_value' => 'y'))),
	array('op' => 'options', 'id' => 'pr', 'options' => array(array('value' => 'Gold', 'price' => '9.5'))),
);
list($r) = U::apply(array_merge($form(), array(array('id_' => 'pr', 'type' => 'payRadio', 'name' => 'P', 'step' => 1, 'amount' => 5))), $pt, 'F');
$opt = array_values(array_filter($r, function ($x) { return ($x['type'] ?? '') === 'option'; }));
t('options: the old option rows replaced, new ones in the builder\'s shape (id_op, parent, step, amount, opt_value), price on a pay field', count($opt) === 3 && $opt[0]['value'] === 'X' && $opt[0]['parent'] === 'ch' && $opt[0]['id_op'] === $opt[0]['id_'] && $opt[1]['opt_value'] === 'y' && $opt[2]['price'] === '9.5' && $opt[2]['parent'] === 'pr' && !isset($opt[0]['price']), $opt);
list($r) = U::apply($form(), array(array('op' => 'options', 'id' => 'ch', 'options' => array(array('value' => 'A2', 'id_' => 'o1'), 'New', array('value' => 'Bad', 'id_' => 'no spaces!'), array('value' => 'Twin', 'id_' => 'o1'), array('value' => 'Clash', 'id_' => 'nm')))), 'F');
$opt = array_values(array_filter($r, function ($x) { return ($x['type'] ?? '') === 'option'; }));
t('options: a choice that names its id_ keeps it (logic, calculations and stock still point at it); a new, invalid, repeated or taken id gets a fresh one', count($opt) === 5 && $opt[0]['id_'] === 'o1' && $opt[0]['id_op'] === 'o1' && $opt[0]['value'] === 'A2' && $opt[0]['parent'] === 'ch' && preg_match('/^[a-z0-9]{9}$/', $opt[1]['id_']) && preg_match('/^[a-z0-9]{9}$/', $opt[2]['id_']) && preg_match('/^[a-z0-9]{9}$/', $opt[3]['id_']) && preg_match('/^[a-z0-9]{9}$/', $opt[4]['id_']) && count(array_unique(array_column($r, 'id_'))) === count(array_column($r, 'id_')), $opt);
list(, $name) = U::apply($form(), array(array('op' => 'name', 'value' => 'New name')), 'F');
t('name: the form\'s name', $name === 'New name');
foreach (array(
	array(array(), 'invalid_patch', 'an empty list'),
	array(array(array('op' => 'zap')), 'invalid_patch', 'an unknown op'),
	array(array(array('op' => 'field', 'id' => 'nope', 'set' => array('name' => 'x'))), 'unknown_field', 'a field that is not there'),
	array(array(array('op' => 'field', 'id' => 'o1', 'set' => array('value' => 'x'))), 'unknown_field', 'an option row is not a field'),
	array(array(array('op' => 'field', 'id' => 'nm', 'set' => array('id_' => 'zz'))), 'invalid_patch', 'id_ is fixed'),
	array(array(array('op' => 'field', 'id' => 'nm', 'set' => array('type' => 'email'))), 'invalid_patch', 'type is fixed'),
	array(array(array('op' => 'form', 'set' => array('steps' => 9))), 'invalid_patch', 'steps is counted'),
	array(array(array('op' => 'form', 'set' => array('type' => 'quiz'))), 'invalid_patch', 'an unknown form type'),
	array(array(array('op' => 'insert', 'rows' => array(array('id_' => 'nm', 'type' => 'text')))), 'duplicate_id', 'an id that is used'),
	array(array(array('op' => 'insert', 'rows' => array(array('id_' => 'a b', 'type' => 'text')))), 'invalid_patch', 'an id with a space'),
	array(array(array('op' => 'insert', 'rows' => array(array('id_' => 'zz', 'type' => 'form')))), 'invalid_patch', 'a settings row inserted'),
	array(array(array('op' => 'insert', 'rows' => array(array('id_' => 'op9', 'type' => 'option', 'parent' => 'nobody')))), 'orphan_option', 'an option without its field'),
	array(array(array('op' => 'insert', 'rows' => array(array('id_' => 'zz', 'type' => 'text')), 'after' => 'nobody')), 'unknown_target', 'after a field that is not there'),
	array(array(array('op' => 'insert', 'rows' => array(array('id_' => 'zz', 'type' => 'text')), 'before' => '1')), 'invalid_patch', 'before step 1'),
	array(array(array('op' => 'insert', 'rows' => array(array('id_' => 'zz', 'type' => 'text')), 'step' => 7)), 'unknown_target', 'a step that is not there'),
	array(array(array('op' => 'remove', 'ids' => array('1'))), 'invalid_patch', 'removing a step'),
	array(array(array('op' => 'options', 'id' => 'nm', 'options' => array('A'))), 'invalid_patch', 'choices on a text field'),
	array(array(array('op' => 'name', 'value' => '  ')), 'invalid_patch', 'an empty name'),
) as $c) t('refused: ' . $c[2] . ' → ' . $c[1], $code(U::apply($form(), $c[0], 'F')) === $c[1], $code(U::apply($form(), $c[0], 'F')));
$e = U::apply($form(), array(array('op' => 'field', 'id' => 'nm', 'set' => array('name' => 'ok')), array('op' => 'field', 'id' => 'gone')), 'F');
t('a refusal names the patch and the id (patch 1, id gone, 404)', $e instanceof WP_Error && $e->get_error_data()['patch'] === 1 && $e->get_error_data()['id'] === 'gone' && $e->get_error_data()['status'] === 404, $e);

/* ---------------------------------------------------------------- hash */
$h = U::hash_of($form(), 'F');
t('hash_of(): sha256, stable, changes with a row and with the name', preg_match('/^[0-9a-f]{64}$/', $h) && $h === U::hash_of($form(), 'F') && $h !== U::hash_of($form(), 'G') && $h !== U::hash_of(array_slice($form(), 0, 3), 'F'));

/* ---------------------------------------------------------------- gate */
$reg = function ($plan = 'free_plus', $over = array()) {
	$f = array(
		'field:text' => array('kind' => 'field', 'package' => 'free', 'addon' => null, 'available_now' => true),
		'field:calc' => array('kind' => 'field', 'package' => 'free_plus', 'addon' => 'AdnCLC', 'available_now' => false, 'reason' => 'addon'),
		'field:ranking' => array('kind' => 'field', 'package' => 'free_plus', 'addon' => null, 'available_now' => $plan !== 'free', 'reason' => $plan !== 'free' ? null : 'plan'),
		'setting:email_sub' => array('kind' => 'setting', 'package' => 'free_plus', 'addon' => null, 'available_now' => $plan !== 'free', 'reason' => $plan !== 'free' ? null : 'plan', 'form_keys' => array('email_sub')),
		'setting:smart_tags' => array('kind' => 'setting', 'package' => 'free', 'addon' => null, 'available_now' => true, 'form_keys' => array('email_sub', 'name')),
		'setting:answer_piping' => array('kind' => 'setting', 'package' => 'free_plus', 'addon' => null, 'available_now' => $plan !== 'free', 'reason' => 'plan', 'form_keys' => array('name', 'message')),
		'setting:payment_stripe' => array('kind' => 'setting', 'package' => 'free_plus', 'addon' => 'AdnSPF', 'available_now' => false, 'reason' => 'addon', 'form_keys' => array('getway=stripe', 'currency')),
		'setting:payment_paypal' => array('kind' => 'setting', 'package' => 'free_plus', 'addon' => 'AdnPAP', 'available_now' => false, 'reason' => 'addon', 'form_keys' => array('getway=paypal', 'currency')),
		'setting:mobile_layout' => array('kind' => 'setting', 'package' => 'pro', 'addon' => null, 'available_now' => $plan === 'pro', 'reason' => 'plan', 'form_keys' => array('mobile_*')),
		'limit:steps' => array('kind' => 'limit', 'limits' => array('free' => 2, 'free_plus' => null, 'pro' => null)),
	);
	return array('plan' => array('name' => $plan), 'features' => array_merge($f, $over));
};
U::$registry = $reg('free');
$g = function ($patches, $base = null) use ($form) { $b = $base ?: $form(); $a = U::apply($b, $patches, 'F'); return is_array($a) ? U::gate($b, $a[0]) : $a; };
\Emsfb\Addon_Keys::set_override(array('AdnCLC' => false, 'AdnTSG' => false, 'AdnADR' => true, 'AdnBEF' => true, 'AdnGEO' => true));
$r = $g(array(array('op' => 'insert', 'rows' => array(array('id_' => 'c1', 'type' => 'calc')))));
t('gate: a calc field while its add-on is off → addon_required (AdnCLC, field:calc, 409)', $code($r) === 'addon_required' && $r->get_error_data()['addon'] === 'AdnCLC' && $r->get_error_data()['feature'] === 'field:calc' && $r->get_error_data()['status'] === 409, $r);
$r = $g(array(array('op' => 'insert', 'rows' => array(array('id_' => 'k1', 'type' => 'ranking')))));
t('gate: a Free Plus field on Free → plan_required (free_plus, 403)', $code($r) === 'plan_required' && $r->get_error_data()['package'] === 'free_plus' && $r->get_error_data()['status'] === 403, $r);
$r = $g(array(array('op' => 'form', 'set' => array('email_sub' => 'Hello'))));
t('gate: a Free Plus setting on Free → plan_required (setting:email_sub; the smart-tag entry that lists it does not unlock it)', $code($r) === 'plan_required' && $r->get_error_data()['feature'] === 'setting:email_sub', $r);
t('gate: the same setting turned off is allowed', $g(array(array('op' => 'form', 'set' => array('email_sub' => '')))) === null);
t('gate: a label (name) is content, not the answer-piping gate: allowed on Free', $g(array(array('op' => 'field', 'id' => 'nm', 'set' => array('name' => 'Hi {field:em}')), array('op' => 'insert', 'rows' => array(array('id_' => 'n2', 'type' => 'text', 'name' => 'Label'))))) === null);
t('gate: a currency (named by the Stripe and PayPal entries, both off with different add-ons) is not judged', $g(array(array('op' => 'form', 'set' => array('currency' => 'eur')))) === null);
$r = $g(array(array('op' => 'form', 'set' => array('getway' => 'stripe'))));
t('gate: getway=stripe while the Stripe add-on is off → addon_required AdnSPF', $code($r) === 'addon_required' && $r->get_error_data()['addon'] === 'AdnSPF', $r);
$r = $g(array(array('op' => 'field', 'id' => 'nm', 'set' => array('mobile_el_height' => 'h-l-efb'))));
t('gate: a key under a prefix (mobile_*) on Free → plan_required pro', $code($r) === 'plan_required' && $r->get_error_data()['package'] === 'pro', $r);
$r = $g(array(array('op' => 'insert', 'rows' => array(array('id_' => 'n3', 'type' => 'text', 'name' => 'T', 'size' => '50', 'mobile_size' => '50')))));
t('gate: a phone twin that only repeats the desktop value (mobile_size = size, as a new row carries it) is no layout: allowed on Free', $r === null, $r);
$r = $g(array(array('op' => 'insert', 'rows' => array(array('id_' => 'n3', 'type' => 'text', 'name' => 'T', 'size' => '50', 'mobile_size' => '100')))));
t('gate: a phone value that differs from the desktop one is the mobile layout → plan_required pro', $code($r) === 'plan_required' && $r->get_error_data()['package'] === 'pro', $r);
$had = $form(); $had[] = array('id_' => 'old', 'type' => 'ranking', 'step' => 1, 'amount' => 5, 'mobile_x' => '1');
t('gate: what the form already had (a Free Plus field, a Pro key) is not judged when another field changes', $g(array(array('op' => 'field', 'id' => 'nm', 'set' => array('name' => 'x'))), $had) === null);
$sig = $form(); $sig[] = array('id_' => 'sg', 'type' => 'esign', 'step' => 1, 'amount' => 5, 'esign_mode' => 'type');
$r = $g(array(array('op' => 'field', 'id' => 'sg', 'set' => array('esign_mode' => 'draw'))), $sig);
t('gate: changing a key of an add-on that is off (Typed Signature esign_mode) → addon_required AdnTSG', $code($r) === 'addon_required' && $r->get_error_data()['addon'] === 'AdnTSG', $r);
t('gate: another key of that field (its label) is allowed (the save keeps the add-on\'s keys)', $g(array(array('op' => 'field', 'id' => 'sg', 'set' => array('name' => 'Sign'))), $sig) === null);
$r = $g(array(array('op' => 'insert', 'rows' => array(array('id_' => 's', 'type' => 'step'))), array('op' => 'insert', 'rows' => array(array('id_' => 's', 'type' => 'step')))));
t('gate: a third step on Free → plan_required (limit:steps)', $code($r) === 'plan_required' && $r->get_error_data()['feature'] === 'limit:steps', $r);
t('gate: a second step on Free is allowed', $g(array(array('op' => 'insert', 'rows' => array(array('id_' => 's', 'type' => 'step')))))  === null);
U::$registry = $reg('pro');
t('gate: on Pro the Free Plus field, setting, Pro key and steps pass', $g(array(array('op' => 'insert', 'rows' => array(array('id_' => 'k1', 'type' => 'ranking'))), array('op' => 'form', 'set' => array('email_sub' => 'Hi')), array('op' => 'field', 'id' => 'nm', 'set' => array('mobile_el_height' => 'x')), array('op' => 'insert', 'rows' => array(array('id_' => 's', 'type' => 'step'))), array('op' => 'insert', 'rows' => array(array('id_' => 's', 'type' => 'step'))))) === null);
U::$registry = null;
\Emsfb\Addon_Keys::set_override(null);

/* ---------------------------------------------------------------- status */
$GLOBALS['wpdb']->drafts = array('7');
\Emsfb\Form_Status::flush();
t('status: a stored 2 is a draft; another form is not', \Emsfb\Form_Status::is_draft(7) && !\Emsfb\Form_Status::is_draft(8));
$GLOBALS['viewer_admin'] = false;
t('render_note(): a visitor gets "" (nothing at all); a published form null (rendered as always)', \Emsfb\Form_Status::render_note(7) === '' && \Emsfb\Form_Status::render_note(8) === null && \Emsfb\Form_Status::hidden_for_viewer(7));
$GLOBALS['viewer_admin'] = true;
$n = \Emsfb\Form_Status::render_note(7);
t('render_note(): the plugin\'s admin gets the notice (role note, dir auto, the forms list link) and is not hidden', strpos($n, 'efb-draft-note') !== false && strpos($n, 'role="note"') !== false && strpos($n, 'dir="auto"') !== false && strpos($n, 'page=Emsfb') !== false && !\Emsfb\Form_Status::hidden_for_viewer(7), $n);
$rows = array((object) array('form_id' => '7', 'form_name' => 'D'), (object) array('form_id' => '8', 'form_name' => 'P'));
\Emsfb\Form_Status::mark_list($rows);
t('mark_list(): the forms list marks the draft (draft = 1) and not the others; the raw status is not exposed', isset($rows[0]->draft) && $rows[0]->draft === 1 && !isset($rows[1]->draft) && !isset($rows[0]->status));

/* ---------------------------------------------------------------- version */
$main = file_get_contents(__DIR__ . '/../emsfb.php');
preg_match('/^\s*\*\s*Version:\s*(\S+)/m', $main, $hv);
preg_match('/define\("EMSFB_PLUGIN_VERSION",\s*"([^"]+)"\)/', $main, $cv);
t('AI-7 the plugin header and EMSFB_PLUGIN_VERSION are one version (' . ($hv[1] ?? '?') . ')', isset($hv[1], $cv[1]) && $hv[1] === $cv[1] && version_compare($hv[1], '4.3.2', '>='), array($hv[1] ?? null, $cv[1] ?? null));
$api = file_get_contents(__DIR__ . '/../includes/class-Emsfb-builder-api.php') . file_get_contents(__DIR__ . '/../includes/class-Emsfb-form-package.php');
t('AI-7 the registry, the templates and the form package read the constant (no version written by hand)', preg_match_all("/'(efb|plugin)_version'\s*=>\s*defined\('EMSFB_PLUGIN_VERSION'\) \? EMSFB_PLUGIN_VERSION : ''/", $api) === 4 && !preg_match("/'(efb|plugin)_version'\s*=>\s*'4\./", $api));
preg_match('/^Stable tag:\s*(\S+)/m', file_get_contents(__DIR__ . '/../readme.txt'), $st);
t('AI-7 readme.txt: the Stable tag is the released version (it moves with the release, not with development; the changelog has "Unreleased")', isset($st[1]) && version_compare($st[1], $hv[1], '<=') && strpos(file_get_contents(__DIR__ . '/../readme.txt'), '= Unreleased =') !== false, $st[1] ?? null);

echo "\nRESULTS: {$passed} passed, {$failed} failed\n";
exit($failed ? 1 : 0);
