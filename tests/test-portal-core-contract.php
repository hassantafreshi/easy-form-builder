<?php
/**
 * F20-core C1 (P1, P2, P3, P18) and the pure parts of P5 / P6 / P9, without WordPress (functions stubbed):
 *  - the contract: EMSFB_PORTAL_API 1, efb_portal_core_api() and every function of spec §5.1 defined, the English
 *    contract document names each function, filter and refusal code; the add-on contract "portal" in
 *    efb_addon_contract_versions(); contract 1.1: no AdnPRT in the add-on catalogue (the portal is the free plugin
 *    EFB Customer Portal), every plan, capabilities.portal_core describes the plugin;
 *    Install::addon_table() public; the cache filter emsfb_cache_shortcodes; efb_entry_stored in insert_message_db();
 *  - inert: Portal_Core::active() is false without efb_portal_active; on (contract 1.1) on Free, Free Plus and Pro;
 *  - the policy: core's defaults keep every action off; values are clamped, unknown keys dropped;
 *  - the owner hash: the same person (capitals, Persian / Arabic digits, spaces, 00 for +) gets the same hash; the
 *    owners of an entry follow the identifying field, mobile numbers and tel only with + / 00; a text answer that
 *    holds an address is not an owner;
 *  - statuses and the booking time;
 *  - size: the public class stays under its 1,000,000 bytes (new code lives in new files).
 *
 * Run: php tests/test-portal-core-contract.php
 */
namespace {
define('ABSPATH', __DIR__ . '/');
$GLOBALS['F'] = array(); $GLOBALS['O'] = array(); $GLOBALS['PLAN'] = true;
function apply_filters($h, $v, ...$a) { return isset($GLOBALS['F'][$h]) ? call_user_func_array($GLOBALS['F'][$h], array_merge(array($v), $a)) : $v; }
function add_filter($h, $cb) { $GLOBALS['F'][$h] = $cb; } function add_action() {} function do_action() {}
function get_option($k, $d = false) { return $GLOBALS['O'][$k] ?? $d; }
function wp_salt($s = '') { return 'unit-salt'; }
function sanitize_email($s) { return trim((string) $s); }
function is_email($s) { return (bool) filter_var($s, FILTER_VALIDATE_EMAIL); }
function efb_plan_at_least_efb($p) { return $GLOBALS['PLAN']; }
function wp_timezone() { return new DateTimeZone('UTC'); }
$root = dirname(__DIR__);
require_once $root . '/includes/class-Emsfb-verification.php';
require_once $root . '/includes/class-Emsfb-owner-index.php';
require_once $root . '/includes/class-Emsfb-portal-core.php';
require_once $root . '/includes/portal-core-functions-efb.php';
require_once $root . '/includes/addon-contracts-functions-efb.php';
use Emsfb\Owner_Index as O; use Emsfb\Portal_Core as P;
$pass = 0; $fail = 0;
function t($n, $a, $e) { global $pass, $fail; if ($a === $e) { $pass++; echo "[PASS] {$n}\n"; } else { $fail++; echo "[FAIL] {$n}\n  expected: " . json_encode($e, JSON_UNESCAPED_UNICODE) . "\n  actual:   " . json_encode($a, JSON_UNESCAPED_UNICODE) . "\n"; } }

echo "--- the contract ---\n";
$fns = array('efb_portal_core_api', 'efb_portal_reindex', 'efb_portal_send_code', 'efb_portal_check_code', 'efb_portal_identity_from_proof', 'efb_portal_entries', 'efb_portal_entry',
	'efb_portal_thread', 'efb_entry_add_reply', 'efb_portal_reply_upload_token', 'efb_entry_set_ticket_state', 'efb_portal_payments', 'efb_portal_file_link', 'efb_entry_owner_cancel',
	'efb_entry_owner_reschedule', 'efb_entry_owner_editable', 'efb_entry_owner_update', 'efb_portal_drafts', 'efb_portal_draft_link', 'efb_portal_privacy_request');
t('EMSFB_PORTAL_API is 1 and every function of the contract (spec §5.1) is defined', array(EMSFB_PORTAL_API, array_values(array_filter($fns, function ($f) { return !function_exists($f); }))), array(1, array()));
$doc = (string) @file_get_contents($root . '/docs/portal/portal-core-contract.en.md');
$hooks = array('efb_portal_active', 'efb_portal_form_policy', 'efb_portal_limit', 'efb_portal_plan_ok', 'efb_portal_booking_api', 'efb_entry_stored', 'efb_entry_reply_added', 'efb_entry_ticket_state', 'efb_entry_cancelled', 'efb_date_released', 'efb_entry_view_url', 'emsfb_cache_shortcodes', 'efb_portal_entry_row', 'efb_addon_setting_keys');
$missing = array();
foreach (array_merge($fns, array_keys(P::ERRORS), $hooks, array_keys(P::POLICY)) as $w) if (strpos($doc, $w) === false) $missing[] = $w;
t('the English contract document names every function, refusal code, hook and policy key', $missing, array());
t('the refusal codes of spec §5.1 are all there (plus invalid, conflict, addon_required) with their HTTP status', array(P::ERRORS['efb_portal_not_found'], P::ERRORS['efb_portal_rate_limited'], P::ERRORS['efb_portal_unavailable'], count(P::ERRORS)), array(404, 429, 503, 11));
$v = efb_addon_contract_versions();
t('the add-on contract "portal" version 1 (core side present)', array($v['portal']['version'] ?? null, $v['portal']['core'] ?? null), array(1, true));
$cat = file_get_contents($root . '/includes/class-Emsfb-addon-catalog.php');
t('1.1: AdnPRT is no longer in the add-on catalogue (the portal is the free plugin EFB Customer Portal, no plan gate)', strpos($cat, "'AdnPRT' =>"), false);
$api = efb_portal_core_api();
t('1.1: efb_portal_core_api(): version 1 (v1 callers keep working), a minor version, the contract "1.x", v1.0\'s features first', array($api['version'], is_int($api['minor']) && $api['minor'] >= 1, $api['contract'] === '1.' . $api['minor'], array_slice($api['features'], 0, 13) === P::FEATURES, in_array('any_plan', $api['features'], true)), array(1, true, true, true, true));
$inst = file_get_contents($root . '/includes/class-Emsfb-install.php');
t('P3: Install::addon_table($sql) is public; DB 1.12 adds the owner table, owner_state and KEY uid', array((bool) preg_match('/public static function addon_table\( \$sql \)/', $inst), strpos($inst, 'upgrade_f20_schema()') !== false, strpos(file_get_contents($root . '/emsfb.php'), "define(\"EMSFB_DB_VERSION\", '1.12')") !== false), array(true, true, true));
$cc = file_get_contents($root . '/includes/class-Emsfb-cache-compat.php');
t('P18: Cache_Compat::contains() reads its shortcodes through emsfb_cache_shortcodes', strpos($cc, "apply_filters('emsfb_cache_shortcodes'") !== false, true);
$pub = file_get_contents($root . '/includes/class-Emsfb-public.php');
t('P4: efb_entry_stored once in insert_message_db(), after the insert succeeded (the not-stored path has none)', array(substr_count($pub, "do_action('efb_entry_stored'"), strpos($pub, "do_action('efb_entry_stored'") > strpos($pub, '$new_msg_id_efb = isset($this->db->insert_id)')), array(1, true));
t('the public class stays under 1,000,000 bytes (F20-core lives in new files)', filesize($root . '/includes/class-Emsfb-public.php') < 1000000, true);

t('1.2: efb_portal_core_api() reports the channels (without F02\'s table: neither can send, reason "tables")', array($api['channels']['email'] ?? null, $api['channels']['sms'] ?? null, in_array('channels', $api['features'], true)), array(array('ready' => false, 'reason' => 'tables'), array('ready' => false, 'reason' => 'tables'), true));

echo "--- inert, every plan ---\n";
t('without the plugin: not active', P::active(), false);
$cap = P::capabilities(array());
t('capabilities without the plugin: not ready, reason "plugin"; the block names no add-on and no package', array($cap['portal_core']['ready'], $cap['portal_core']['reason'], isset($cap['portal_core']['addon']), isset($cap['portal_core']['package'])), array(false, 'plugin', false, false));
t('capabilities.portal_core.plugin: the free plugin efb-customer-portal (slug, file, not installed here), every plan', array($cap['portal_core']['plugin']['slug'], $cap['portal_core']['plugin']['file'], $cap['portal_core']['plugin']['installed'], $cap['portal_core']['plugin']['active'], $cap['portal_core']['plugin']['plan'], $cap['portal_core']['plans'], $cap['portal_core']['contract']), array('efb-customer-portal', 'efb-customer-portal/efb-customer-portal.php', false, false, 'free', array('free', 'free_plus', 'pro'), '1.' . P::API_MINOR));
add_filter('efb_portal_active', function () { return true; });
$GLOBALS['PLAN'] = false;
t('1.1: the plugin on and the site on Free (below Pro): plan_ok (the contract answers on every plan)', P::plan_ok(), true);
add_filter('efb_portal_plan_ok', function ($ok) { return false; });
t('… a site may still switch it off with efb_portal_plan_ok: then not active, reason "plan"', array(P::active(), P::capabilities(array())['portal_core']['reason']), array(false, 'plan'));
unset($GLOBALS['F']['efb_portal_plan_ok']);
$GLOBALS['PLAN'] = true;

echo "--- the policy ---\n";
$d = P::policy(5);
t('core\'s defaults: nothing on', array($d['on'], $d['reply'], $d['cancel'], $d['edit'], $d['drafts'], $d['reopen_days'], $d['cancel_hours']), array(false, false, false, false, false, 14, 24));
add_filter('efb_portal_form_policy', function ($p, $fid) { return $fid === 7 ? array('on' => '1', 'reply' => 1, 'cancel_hours' => '-5', 'edit_hours' => '999999', 'match' => 'other', 'fields' => array('a', 3), 'evil' => 'x') : $p; });
$p = P::policy(7);
t('the add-on\'s policy: booleans, clamped hours, match id_field unless "verified", field ids as text, unknown keys dropped', array($p['on'], $p['reply'], $p['cancel_hours'], $p['edit_hours'], $p['match'], $p['fields'], isset($p['evil'])), array(true, true, 0, 87600, 'id_field', array('a', '3'), false));

echo "--- the owner hash and the owners of an entry ---\n";
$h = O::hash('e', 'Ana@Example.test');
t('e-mail: capitals and Persian digits give the same hash (64 hex)', array(strlen($h), O::hash('email', 'ana@example.test') === $h, O::hash('e', 'ana' . "\u{06F1}" . '@x.test') === O::hash('e', 'ana1@x.test')), array(64, true, true));
t('mobile: Arabic-Indic digits, spaces and 00 give the E.164 hash; a number without + / 00 is not an owner', array(O::hash('p', '٠٠٩٨ ٩١٢ ١٢٣ ٤٥٦٧') === O::hash('p', '+989121234567'), O::hash('p', '09121234567')), array(true, ''));
t('the e-mail and phone hashes of one value differ (kind in the hash); a bad address has none', array(O::hash('e', 'a@b.test') !== O::hash('p', 'a@b.test'), O::hash('e', 'not an address')), array(true, ''));
$rows = array(
	array('id_' => 'idf', 'type' => 'email', 'value' => 'ana@example.test', 'verified' => 'email'),
	array('id_' => 'cc', 'type' => 'email', 'value' => 'bob@example.test'),
	array('id_' => 'msg', 'type' => 'textarea', 'value' => 'write to carol@example.test'),
	array('id_' => 'mob', 'type' => 'mobile', 'value' => '+989121234567'),
	array('id_' => 't1', 'type' => 'tel', 'value' => '0912 123 4567'),
	array('id_' => 't2', 'type' => 'tel', 'value' => '00447700900123'),
);
$own = O::owners_of($rows, array('prv_id_field' => 'idf'));
t('with an identifying field: that address (verified), the mobile, a tel with 00; not the other e-mail field, not a message text, not a local tel', array_map(function ($o) { return $o['k'] . ':' . $o['v']; }, $own), array('e:email', 'p:', 'p:'));
$own = O::owners_of($rows, array());
t('without one: every e-mail field', count(array_filter($own, function ($o) { return $o['k'] === 'e'; })), 2);

echo "--- status, booking time ---\n";
t('statuses: cancelled > pending payment > closed > awaiting you > awaiting support > received', array(P::status(1, 1, ''), P::status(2, 0, ''), P::status(4, 0, 'support'), P::status(1, 0, 'support'), P::status(3, 0, 'owner'), P::status(1, 0, '')), array('cancelled', 'pending_payment', 'closed', 'awaiting_you', 'awaiting_support', 'received'));
t('the booking time: the first advanced date\'s utc, else its day; none without one', array(P::booking_time(array(array('type' => 'advdate', 'value' => '2030-05-06T10:00', 'utc' => '2030-05-06T08:00:00Z'))), P::booking_time(array(array('type' => 'advdate', 'value' => '2030-05-06'))), P::booking_time(array(array('type' => 'text', 'value' => 'x')))), array(strtotime('2030-05-06T08:00:00Z'), strtotime('2030-05-06 00:00:00 UTC'), null));

echo "--- 1.3: the forms' pages ---\n";
t('form ids in a post: both shortcodes (quoted or not, other attributes), the block (formId as text or number), WPBakery and Visual Composer; not a tracking finder or a text', P::form_ids_in('[EMS_Form_Builder id=3] x [ems_form_builder  id="12" mode=popup] <!-- wp:easy-form-builder/form {"formId":"7","formName":"A"} /--> <!-- wp:easy-form-builder/form {"formId":9} /--> [efb_wpbakery_form form_id="15"] [efb_vc_form form_id=16] [EMS_Form_Builder id=3] [Easy_Form_Builder_confirmation_code_finder] see form id=99'), array(3, 12, 15, 16, 7, 9));
t('Elementor: the emsfb_form widgets at any depth, nothing else', P::elementor_form_ids(json_encode(array(array('elType' => 'section', 'elements' => array(array('elType' => 'column', 'elements' => array(array('widgetType' => 'emsfb_form', 'settings' => array('form_id' => '21')), array('widgetType' => 'heading', 'settings' => array('form_id' => '22')))))), array('widgetType' => 'emsfb_form', 'settings' => array('form_id' => 'tracking'))))), array(21));
$GLOBALS['CAN'] = false;
function current_user_can($c) { return $GLOBALS['CAN']; }
if (!class_exists('WP_Error')) { class WP_Error { private $c; private $m; private $d; public function __construct($c = '', $m = '', $d = null) { $this->c = $c; $this->m = $m; $this->d = $d; } public function get_error_code() { return $this->c; } public function get_error_data() { return $this->d; } public function get_error_message() { return $this->m; } } }
$r = P::forms();
t('efb_portal_forms(): not an administrator → efb_portal_forbidden (403)', array(is_object($r) ? $r->get_error_code() : $r, is_object($r) ? $r->get_error_data()['status'] : 0), array('efb_portal_forbidden', 403));
unset($GLOBALS['F']['efb_portal_active']);
$r = P::forms();
t('… the plugin off → efb_portal_unavailable', is_object($r) ? $r->get_error_code() : $r, 'efb_portal_unavailable');
add_filter('efb_portal_active', function () { return true; });

echo "\nRESULTS: {$pass} passed, {$fail} failed\n";
exit($fail ? 1 : 0);
}
