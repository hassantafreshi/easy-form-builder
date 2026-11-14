<?php
/**
 * F14: the assistants' read-only entries API (SitePilot entries.read, contract §5.5): efb_api_entries_query,
 * efb_api_entry_get, efb_api_entries_stats.
 *
 *  - off by default; "stats" opens only the statistics; "rows" opens the entries;
 *  - only a logged-in user with the plugin's entries capability (manage_options or Emsfb); never user 0;
 *  - each row: status / read_state as strings, the fields without IP, uid, passwords, payment ids or raw file
 *    URLs (a file gives its name only; nothing for a hidden field filled from {ip} / {uid});
 *  - personal answers (F13 prv_pii, else by type) are "[redacted]" unless include_pii and manage_options;
 *    prv_pii "0" keeps a field readable, prv_pii "1" hides a choice field;
 *  - F09 rows keep rg / ri; F05 ov is given with the value;
 *  - the list's own query: tab, search, page, per_page ≤ 100; field conditions need Free Plus (402);
 *  - a form that stores no entries (F13 prv_nostore) answers stored: false;
 *  - every read: one api_read history row (app and count, no value) and the action efb_api_entries_read;
 *    the list rows (msg_id 0) are removed after 90 days;
 *  - 60 calls a minute per user, then efb_api_rate_limited (429, retry_after);
 *  - REST: GET Emsfb/v1/admin/forms/{id}/entries, admin/entries/{msg_id}, admin/forms/{id}/stats, the
 *    permission callback refuses user 0 and a Subscriber.
 *
 * Run: php tests/test-entries-api.php
 */
define('ABSPATH', __DIR__ . '/');
if (!defined('DAY_IN_SECONDS')) define('DAY_IN_SECONDS', 86400);
$passed = 0; $failed = 0;
function t($name, $actual, $expected) {
	global $passed, $failed;
	if ($actual === $expected) { echo "[PASS] {$name}\n"; $passed++; return; }
	echo "[FAIL] {$name}: expected " . var_export($expected, true) . ', got ' . var_export($actual, true) . "\n";
	$failed++;
}
class WP_Error {
	public $code; public $message; public $data;
	public function __construct($c = '', $m = '', $d = null) { $this->code = $c; $this->message = $m; $this->data = $d; }
	public function get_error_code() { return $this->code; }
	public function get_error_data() { return $this->data; }
}
function is_wp_error($x) { return $x instanceof WP_Error; }
require __DIR__ . '/entries-test-db.php';
if (!function_exists('emsfb_json_normalize_efb')) { function emsfb_json_normalize_efb($s) { return str_replace('\\"', '"', (string) $s); } }
if (!function_exists('get_setting_Emsfb')) { function get_setting_Emsfb($m = 'decoded') { return isset($GLOBALS['efb_settings']) ? (object) $GLOBALS['efb_settings'] : new stdClass(); } }
if (!function_exists('efb_plan_at_least_efb')) { function efb_plan_at_least_efb($need) { $rank = array('free' => 0, 'free_plus' => 1, 'pro' => 2); return $rank[$GLOBALS['efb_plan']] >= $rank[$need]; } }
$GLOBALS['efb_rest'] = array();
function register_rest_route($ns, $route, $args) { $GLOBALS['efb_rest'][$ns . $route] = $args; return true; }
function rest_ensure_response($r) { return $r; }
require_once __DIR__ . '/../includes/class-Emsfb-privacy.php';
require_once __DIR__ . '/../includes/admin/class-Emsfb-entries.php';
require_once __DIR__ . '/../includes/admin/entries-api-functions.php';
use Emsfb\Entries;

$db = $GLOBALS['wpdb'] = new EfbEntriesTestDb();
$db->create_f14_tables();
Entries::reset_ready();
Entries::register();
$GLOBALS['efb_plan'] = 'free';
$reads = array();
add_action('efb_api_entries_read', function ($uid, $fid, $count, $ctx) use (&$reads) { $reads[] = array($uid, $fid, $count, $ctx['context'], $ctx['app']); });

$fid = $db->add_form(array(
	array('type' => 'form', 'formName' => 'Contact'),
	array('id_' => 'nm', 'type' => 'text', 'name' => 'Name'),
	array('id_' => 'em', 'type' => 'email', 'name' => 'Email'),
	array('id_' => 'city', 'type' => 'text', 'name' => 'City', 'prv_pii' => '0'),
	array('id_' => 'sz', 'type' => 'radio', 'name' => 'Size'),
	array('id_' => 'diag', 'type' => 'select', 'name' => 'Diagnosis', 'prv_pii' => '1'),
	array('id_' => 'doc', 'type' => 'file', 'name' => 'Document'),
	array('id_' => 'pw', 'type' => 'password', 'name' => 'Password'),
	array('id_' => 'hip', 'type' => 'hiddenField', 'name' => 'Visitor IP', 'value' => '{ip}', 'prv_pii' => '0'),
	array('id_' => 'g', 'type' => 'repeater', 'name' => 'Guests'),
	array('id_' => 'gn', 'type' => 'text', 'name' => 'Guest', 'group' => 'g', 'prv_pii' => '0'),
));
$rows = array(
	array('id_' => 'nm', 'type' => 'text', 'name' => 'Name', 'value' => 'Ali @efb@dq#Jr@efb@dq#'),
	array('id_' => 'em', 'type' => 'email', 'name' => 'Email', 'value' => 'ali@example.test'),
	array('id_' => 'city', 'type' => 'text', 'name' => 'City', 'value' => 'Shiraz'),
	array('id_' => 'sz', 'type' => 'radio', 'name' => 'Size', 'value' => 'Small', 'ov' => 'S'),
	array('id_' => 'diag', 'type' => 'select', 'name' => 'Diagnosis', 'value' => 'Flu'),
	array('id_' => 'doc', 'type' => 'file', 'name' => 'Document', 'value' => '@file@', 'url' => 'https://site.example/wp-content/uploads/2026/11/efb-PLG-1-passport.pdf'),
	array('id_' => 'pw', 'type' => 'password', 'name' => 'Password', 'value' => 'hunter2'),
	array('id_' => 'hip', 'type' => 'hiddenField', 'name' => 'Visitor IP', 'value' => '203.0.113.9'),
	array('id_' => 'g', 'type' => 'repeater', 'name' => 'Guests', 'value' => '2'),
	array('id_' => 'gn', 'type' => 'text', 'name' => 'Guest', 'value' => 'Sara', 'rg' => 'g', 'ri' => '0'),
	array('id_' => 'gn', 'type' => 'text', 'name' => 'Guest', 'value' => 'Reza', 'rg' => 'g', 'ri' => '1'),
	array('id_' => 'payment', 'type' => 'payment', 'value' => '12', 'paymentIntent' => 'pi_secret_123', 'paymentGateway' => 'stripe'),
	array('type' => 'w_link', 'value' => 'https://site.example/contact/?utm=x'),
);
$m1 = $db->add_entry($fid, $rows, '2026-11-01 10:00:00', array('read_' => 1, 'starred' => 1));
$m2 = $db->add_entry($fid, array(array('id_' => 'nm', 'type' => 'text', 'name' => 'Name', 'value' => 'Spammer')), '2026-11-02 10:00:00', array('entry_status' => 1));
$m3 = $db->add_entry($fid, array(array('id_' => 'nm', 'type' => 'text', 'name' => 'Name', 'value' => 'Mina')), '2026-11-03 10:00:00', array('read_' => 2));

/* access */
$GLOBALS['efb_uid'] = 2; $GLOBALS['efb_caps'] = array('Emsfb' => true);
$GLOBALS['efb_settings'] = array();
$r = efb_api_entries_query($fid);
t('off by default: efb_api_forbidden 403, the setting named', array(is_wp_error($r), $r->code, $r->data['status'], $r->data['setting']), array(true, 'efb_api_forbidden', 403, 'ai_entries_access'));
$GLOBALS['efb_settings'] = array('ai_entries_access' => 'stats');
t('"stats": the entries stay closed', efb_api_entries_query($fid)->code ?? null, 'efb_api_forbidden');
t('"stats": one entry stays closed too', efb_api_entry_get($m1)->code ?? null, 'efb_api_forbidden');
$s = efb_api_entries_stats($fid, array('days' => 7));
t('"stats": the statistics open (7 days, totals, conversion)', array(is_array($s), $s['days'] ?? null, count($s['series'] ?? array()), array_keys($s['totals'] ?? array())), array(true, 7, 7, array('views', 'submits', 'spam')));
$GLOBALS['efb_settings'] = array('ai_entries_access' => 'rows');
$GLOBALS['efb_caps'] = array('read' => true);
t('a Subscriber is refused (efb_api_forbidden, capability Emsfb)', array(efb_api_entries_query($fid)->code ?? null, efb_api_entries_query($fid)->data['capability'] ?? null), array('efb_api_forbidden', 'Emsfb'));
$GLOBALS['efb_uid'] = 0; $GLOBALS['efb_caps'] = array('manage_options' => true);
t('user 0 is refused even with the capability', efb_api_entries_query($fid)->code ?? null, 'efb_api_forbidden');
$GLOBALS['efb_uid'] = 2; $GLOBALS['efb_caps'] = array('Emsfb' => true);
t('an unknown form: efb_api_not_found 404', array(efb_api_entries_query(424242)->code ?? null, efb_api_entries_query(424242)->data['status'] ?? null), array('efb_api_not_found', 404));

/* a page */
$r = efb_api_entries_query($fid, array('app' => 'SitePilot!'));
t('the list: active entries, newest first, total, redacted', array($r['stored'], $r['total'], array_column($r['rows'], 'msg_id'), $r['redacted']), array(true, 2, array($m3, $m1), true));
$e = $r['rows'][1];
t('status and read_state as strings; starred as a boolean', array($e['status'], $e['read_state'], $e['starred'], $r['rows'][0]['read_state']), array('active', 'read', true, 'pending_payment'));
$by = array(); foreach ($e['fields'] as $f) $by[$f['id_'] . (isset($f['ri']) ? '#' . $f['ri'] : '')] = $f;
t('never: the IP, the password, the payment row, the page link', array(isset($e['ip']), isset($by['pw']), isset($by['payment']), strpos(json_encode($e), '203.0.113') === false, strpos(json_encode($e), 'pi_secret') === false, strpos(json_encode($e), 'hunter2') === false, strpos(json_encode($e), 'utm') === false), array(false, false, false, true, true, true, true));
t('personal answers are "[redacted]" (name, e-mail by type; Diagnosis by prv_pii "1")', array($by['nm']['value'], $by['em']['value'], $by['diag']['value']), array('[redacted]', '[redacted]', '[redacted]'));
t('prv_pii "0" keeps City readable; a radio is not personal (value and ov)', array($by['city']['value'], $by['sz']['value'], $by['sz']['ov']), array('Shiraz', 'Small', 'S'));
t('a file without include_pii: no name, no URL', array($by['doc']['value'], isset($by['doc']['fnm']), strpos(json_encode($e), 'passport') === false), array('[redacted]', false, true));
t('a hidden field filled from {ip} is never given, even marked not personal', $by['hip']['value'], '[redacted]');
t('F09 rows keep rg / ri (row 2: "Reza")', array($by['gn#1']['rg'], $by['gn#1']['ri'], $by['gn#1']['value']), array('g', 1, 'Reza'));
$r = efb_api_entries_query($fid, array('include_pii' => true));
$by = array(); foreach ($r['rows'][1]['fields'] as $f) $by[$f['id_']] = $f;
t('include_pii without manage_options: still redacted', array($r['redacted'], $by['nm']['value']), array(true, '[redacted]'));
$GLOBALS['efb_caps'] = array('manage_options' => true);
$r = efb_api_entries_query($fid, array('include_pii' => '1'));
$by = array(); foreach ($r['rows'][1]['fields'] as $f) $by[$f['id_']] = $f;
t('include_pii as an administrator: the answers (stored quotes decoded), the file name only', array($r['redacted'], $by['nm']['value'], $by['em']['value'], $by['doc']['fnm'], $by['doc']['value'], strpos(json_encode($by['doc']), 'https:') === false), array(false, 'Ali "Jr"', 'ali@example.test', 'efb-PLG-1-passport.pdf', '', true));
t('include_pii: the {ip} hidden field stays hidden', $by['hip']['value'], '[redacted]');
$GLOBALS['efb_caps'] = array('Emsfb' => true);
t('the list\'s own query: tab spam', array_column(efb_api_entries_query($fid, array('tab' => 'spam'))['rows'], 'msg_id'), array($m2));
t('search q', array_column(efb_api_entries_query($fid, array('q' => 'Mina'))['rows'], 'msg_id'), array($m3));
t('per_page is one of 20 / 50 / 100 (500 → 20)', efb_api_entries_query($fid, array('per_page' => 500))['per_page'], 20);
$r = efb_api_entries_query($fid, array('conds' => array(array('id' => 'city', 'op' => 'eq', 'v' => 'Shiraz'))));
t('field conditions on Free: efb_api_plan_required 402 (setting:entries_filter)', array($r->code ?? null, $r->data['status'] ?? null, $r->data['feature_id'] ?? null), array('efb_api_plan_required', 402, 'setting:entries_filter'));
$GLOBALS['efb_plan'] = 'free_plus';
t('field conditions on Free Plus', array_column(efb_api_entries_query($fid, array('conds' => array(array('id' => 'city', 'op' => 'eq', 'v' => 'Shiraz'))))['rows'], 'msg_id'), array($m1));

/* one entry */
$g = efb_api_entry_get($m1, array('app' => 'sitepilot'));
t('one entry: the same row, no notes, no IP', array($g['entry']['msg_id'], $g['entry']['track'] !== '', isset($g['notes']), isset($g['entry']['ip'])), array($m1, true, false, false));
t('a missing entry: efb_api_not_found', efb_api_entry_get(999999)->code ?? null, 'efb_api_not_found');

/* audit */
$log = $db->get_results("SELECT msg_id, field_id, action, old_value, new_value, meta FROM wp_emsfb_msg_log_ WHERE action = 'api_read' ORDER BY log_id", ARRAY_A);
$last = end($log);
t('every read: an api_read row (app and count), no value', array(count($log) >= 8, (int) $last['msg_id'], $last['meta'], $last['old_value'], $last['new_value']), array(true, $m1, 'sitepilot:1', null, null));
t('a list read is a form row (msg_id 0, form:<id>, app:count)', array((int) $log[1]['msg_id'], $log[1]['field_id'], $log[1]['meta']), array(0, 'form:' . $fid, 'sitepilot:2'));
t('efb_api_entries_read: user, form, count, context', end($reads), array(2, $fid, 1, 'get', 'sitepilot'));
$db->pdo->exec("UPDATE wp_emsfb_msg_log_ SET date = '2020-01-01 00:00:00' WHERE action = 'api_read' AND msg_id = 0");
t('the daily job removes list reads older than 90 days, keeps entry reads', array(Entries::purge_api_log() > 0, $db->count('wp_emsfb_msg_log_', "action = 'api_read' AND msg_id = 0"), $db->count('wp_emsfb_msg_log_', "action = 'api_read' AND msg_id = {$m1}")), array(true, 0, 1));

/* prv_nostore */
$ns = $db->add_form(array(array('type' => 'form', 'prv_nostore' => '1'), array('id_' => 'nm', 'type' => 'text', 'name' => 'Name')));
$r = efb_api_entries_query($ns);
t('a form that stores no entries: stored false, no rows', array($r['stored'], $r['rows']), array(false, array()));

/* rate limit */
$GLOBALS['efb_uid'] = 7;
add_filter('efb_api_entries_per_minute', function () { return 3; });
$codes = array();
for ($k = 0; $k < 4; $k++) { $x = efb_api_entries_stats($fid); $codes[] = is_wp_error($x) ? $x->code : 'ok'; }
t('efb_api_entries_per_minute (3): the fourth call is efb_api_rate_limited 429 with retry_after', array($codes, $x->data['status'] ?? null, ($x->data['retry_after'] ?? 0) > 0), array(array('ok', 'ok', 'ok', 'efb_api_rate_limited'), 429, true));
$GLOBALS['efb_filters']['efb_api_entries_per_minute'] = array();

/* REST */
do_action('rest_api_init');
$routes = array_keys($GLOBALS['efb_rest']);
sort($routes);
t('REST routes (GET only)', array($routes, array_unique(array_column($GLOBALS['efb_rest'], 'methods'))), array(array('Emsfb/v1/admin/entries/(?P<msg_id>\d+)', 'Emsfb/v1/admin/forms/(?P<id>\d+)/entries', 'Emsfb/v1/admin/forms/(?P<id>\d+)/stats'), array('GET')));
$perm = $GLOBALS['efb_rest']['Emsfb/v1/admin/forms/(?P<id>\d+)/entries']['permission_callback'];
$GLOBALS['efb_uid'] = 0; $GLOBALS['efb_caps'] = array('manage_options' => true);
$p0 = $perm();
$GLOBALS['efb_uid'] = 3; $GLOBALS['efb_caps'] = array('read' => true);
$p1 = $perm();
$GLOBALS['efb_caps'] = array('Emsfb' => true);
$p2 = $perm();
t('permission: user 0 no, a Subscriber no, the plugin role yes', array($p0, $p1, $p2), array(false, false, true));
class EfbReq implements ArrayAccess {
	private $p; public function __construct($p) { $this->p = $p; }
	public function get_param($k) { return isset($this->p[$k]) ? $this->p[$k] : null; }
	public function offsetExists($k): bool { return isset($this->p[$k]); }
	#[\ReturnTypeWillChange] public function offsetGet($k) { return $this->p[$k]; }
	public function offsetSet($k, $v): void {}
	public function offsetUnset($k): void {}
}
$cb = $GLOBALS['efb_rest']['Emsfb/v1/admin/entries/(?P<msg_id>\d+)']['callback'];
t('REST admin/entries/{id}: the entry', $cb(new EfbReq(array('msg_id' => (string) $m1)))['entry']['msg_id'], $m1);
$GLOBALS['efb_settings'] = array('ai_entries_access' => 'off');
t('REST with the setting off: the WP_Error (403)', $cb(new EfbReq(array('msg_id' => (string) $m1)))->data['status'] ?? null, 403);

/* registry */
$caps = Entries::capabilities(array('features' => array(), 'plan' => array('package_type' => 2)));
t('registry: the API functions, REST routes and modes', array($caps['entries']['api']['functions'], $caps['entries']['api']['modes'], count($caps['entries']['api']['rest'])), array(array('efb_api_entries_query', 'efb_api_entry_get', 'efb_api_entries_stats'), array('off', 'stats', 'rows'), 3));

echo "\nRESULTS: {$passed} passed, {$failed} failed\n";
exit($failed ? 1 : 0);
