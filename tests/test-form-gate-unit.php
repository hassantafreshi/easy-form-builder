<?php
/**
 * F11-B/C/D: Emsfb\Form_Gate decisions, standalone (stubs; counters on the SQLite stand-in).
 *
 *  1. sanitize_key(): every key's server rule (numbers in range with Persian / Arabic digits,
 *     local date-times, allow-lists, roles that exist, messages as plain text).
 *  2. period_slot(): calendar periods in site time — midnight in Asia/Tehran, the DST change
 *     in America/Los_Angeles (23 h / 25 h days), weeks from start_of_week, month and year ends.
 *  3. state(): order role → schedule (with sched_grace for a session prepared before the
 *     close) → total → per user / IP / cookie; old forms without keys are always open.
 *  4. messages: the form's own text first (%s = date), digits of fa / ar.
 *  5. plan: per-person limits are not applied below Free Plus (efb_f11_plan filter).
 *  6. card_html(): role=status, aria-hidden icon, escaped message, data attributes.
 *
 * Run: php tests/test-form-gate-unit.php
 */
define('ABSPATH', __DIR__ . '/');

$passed = 0;
$failed = 0;
function t($name, $actual, $expected) {
	global $passed, $failed;
	if ($actual === $expected) { echo "[PASS] {$name}\n"; $passed++; return; }
	echo "[FAIL] {$name}: expected " . var_export($expected, true) . ', got ' . var_export($actual, true) . "\n";
	$failed++;
}

$GLOBALS['efb_test_tz'] = 'Asia/Tehran';
$GLOBALS['efb_test_roles'] = array('administrator' => 'Administrator', 'editor' => 'Editor', 'subscriber' => 'Subscriber', 'customer' => 'Customer');
$GLOBALS['filters'] = array();
$GLOBALS['locale'] = 'en_US';
$GLOBALS['opts'] = array('start_of_week' => 6);
function apply_filters($h, $v) { $a = func_get_args(); return isset($GLOBALS['filters'][$h]) ? call_user_func_array($GLOBALS['filters'][$h], array_slice($a, 1)) : $v; }
function sanitize_text_field($v) { return trim(preg_replace('/[\r\n\t ]+/', ' ', strip_tags((string) $v))); }
function sanitize_key($k) { return strtolower(preg_replace('/[^a-z0-9_\-]/i', '', (string) $k)); }
function wp_timezone() { return new DateTimeZone($GLOBALS['efb_test_tz']); }
function get_option($k, $d = false) { return array_key_exists($k, $GLOBALS['opts']) ? $GLOBALS['opts'][$k] : $d; }
function update_option($k, $v, $a = null) { $GLOBALS['opts'][$k] = $v; return true; }
function determine_locale() { return $GLOBALS['locale']; }
function esc_html($s) { return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8'); }
function wp_date($f, $ts) { return (new DateTimeImmutable('@' . $ts))->setTimezone(wp_timezone())->format($f); }
function efb_plan_at_least_efb($p) { return isset($GLOBALS['plan']) ? in_array($GLOBALS['plan'], $p === 'pro' ? array('pro') : array('free_plus', 'pro'), true) : true; }

class WP_Error { public $c; public $m; public $d; public function __construct($c = '', $m = '', $d = array()) { $this->c = $c; $this->m = $m; $this->d = $d; } public function get_error_code() { return $this->c; } public function get_error_message() { return $this->m; } public function get_error_data() { return $this->d; } }
function is_wp_error($x) { return $x instanceof WP_Error; }
require __DIR__ . '/f11-test-db.php';
require_once __DIR__ . '/../includes/class-Emsfb-rate-limit.php';
require_once __DIR__ . '/../includes/class-Emsfb-drafts.php';
require_once __DIR__ . '/../includes/class-Emsfb-privacy.php';
require_once __DIR__ . '/../includes/class-Emsfb-client-ip.php';
require_once __DIR__ . '/../includes/class-Emsfb-form-css.php';
require_once __DIR__ . '/../includes/class-Emsfb-form-gate.php';
use Emsfb\Form_Gate as G;
use Emsfb\Rate_Limit;

$GLOBALS['opts']['date_format'] = 'Y-m-d';
$GLOBALS['opts']['time_format'] = 'H:i';

/* ------------------------------------------------------------ 1. sanitize_key */
t('lim_total: number', G::sanitize_key('lim_total', '120'), '120');
t('lim_total: Persian digits', G::sanitize_key('lim_total', '۱۲۰'), '120');
t('lim_total: Arabic-Indic digits', G::sanitize_key('lim_total', '٣'), '3');
t('lim_total: 0 and too large are dropped', array(G::sanitize_key('lim_total', '0'), G::sanitize_key('lim_total', '10000001')), array('', ''));
t('lim_user: 1..100000', array(G::sanitize_key('lim_user', '1'), G::sanitize_key('lim_user', '100001')), array('1', ''));
t('lim_period allow-list', array(G::sanitize_key('lim_period', 'week'), G::sanitize_key('lim_period', 'hour')), array('week', ''));
t('lim_email_field: an id only', array(G::sanitize_key('lim_email_field', 'em_1a2b'), G::sanitize_key('lim_email_field', 'x"><')), array('em_1a2b', ''));
t('booleans: "1" / ""', array(G::sanitize_key('lim_show_left', true), G::sanitize_key('draft_on', '0'), G::sanitize_key('partial_on', 1)), array('1', '', '1'));
t('partial_need_contact keeps an explicit off', array(G::sanitize_key('partial_need_contact', ''), G::sanitize_key('partial_need_contact', '1')), array('0', '1'));
t('sched_open: datetime-local kept', G::sanitize_key('sched_open', '2026-12-01T09:30'), '2026-12-01T09:30');
t('sched_open: space and Persian digits', G::sanitize_key('sched_open', '۲۰۲۶-۱۲-۰۱ ۰۹:۳۰'), '2026-12-01T09:30');
t('sched_close: impossible date dropped', array(G::sanitize_key('sched_close', '2026-02-30T10:00'), G::sanitize_key('sched_close', '2026-12-01'), G::sanitize_key('sched_close', '<script>')), array('', '', ''));
t('sched_grace 0..60', array(G::sanitize_key('sched_grace', '0'), G::sanitize_key('sched_grace', '61')), array('0', ''));
t('draft_days 1..90, partial_days 1..365', array(G::sanitize_key('draft_days', '91'), G::sanitize_key('partial_days', '365')), array('', '365'));
t('partial_consent allow-list', array(G::sanitize_key('partial_consent', 'notice'), G::sanitize_key('partial_consent', 'x')), array('notice', 'checkbox'));
t('allowed_roles: only existing roles, deduplicated', G::sanitize_key('allowed_roles', 'editor, Customer,ghost,editor,<b>x</b>'), 'editor,customer');
t('messages: tags removed, 300 characters', array(G::sanitize_key('lim_full_msg', '<b>Full</b> now'), mb_strlen(G::sanitize_key('lim_user_msg', str_repeat('a', 400)))), array('Full now', 300));
t('unknown key: null (not ours)', G::sanitize_key('formName', 'x'), null);

/* ------------------------------------------------------------ 2. period_slot */
$at = function ($s, $tz) { return new DateTimeImmutable($s, new DateTimeZone($tz)); };
$GLOBALS['efb_test_tz'] = 'Asia/Tehran';
$a = G::period_slot('day', $at('2026-11-13 23:59:30', 'Asia/Tehran'));
$b = G::period_slot('day', $at('2026-11-14 00:00:10', 'Asia/Tehran'));
t('Tehran: 23:59 and 00:00 are two days', array($a[0], $b[0]), array('d20261113', 'd20261114'));
t('Tehran: the day ends at local midnight (20:30 UTC) + 60 s', $a[1], '2026-11-13 20:31:00');
t('Tehran: reset time = local midnight', $a[2], (new DateTimeImmutable('2026-11-14 00:00', new DateTimeZone('Asia/Tehran')))->getTimestamp());
$GLOBALS['efb_test_tz'] = 'America/Los_Angeles';
$d1 = G::period_slot('day', $at('2026-03-08 01:30', 'America/Los_Angeles'));
t('Los Angeles, spring forward: the 8 March day is 23 hours', $d1[2] - (new DateTimeImmutable('2026-03-08 00:00', new DateTimeZone('America/Los_Angeles')))->getTimestamp(), 23 * 3600);
$d2 = G::period_slot('day', $at('2026-11-01 01:30', 'America/Los_Angeles'));
t('Los Angeles, fall back: the 1 November day is 25 hours', $d2[2] - (new DateTimeImmutable('2026-11-01 00:00', new DateTimeZone('America/Los_Angeles')))->getTimestamp(), 25 * 3600);
t('Los Angeles: 23:59 local is still the same day after DST', G::period_slot('day', $at('2026-11-01 23:59', 'America/Los_Angeles'))[0], 'd20261101');
$GLOBALS['efb_test_tz'] = 'Asia/Tehran';
$GLOBALS['opts']['start_of_week'] = 6; // Saturday
t('week starts on Saturday (start_of_week 6): Friday 13 Nov belongs to the week of Sat 7 Nov', G::period_slot('week', $at('2026-11-13 12:00', 'Asia/Tehran'))[0], 'w20261107');
t('week: Saturday 14 Nov starts a new week', G::period_slot('week', $at('2026-11-14 00:01', 'Asia/Tehran'))[0], 'w20261114');
$GLOBALS['opts']['start_of_week'] = 1;
t('week starts on Monday (start_of_week 1)', G::period_slot('week', $at('2026-11-15 12:00', 'Asia/Tehran'))[0], 'w20261109');
t('month and year slots', array(G::period_slot('month', $at('2026-12-31 23:00', 'Asia/Tehran'))[0], G::period_slot('year', $at('2026-12-31 23:00', 'Asia/Tehran'))[0]), array('m202612', 'y2026'));
t('month ends on the 1st of the next month (local)', G::period_slot('month', $at('2026-12-31 23:00', 'Asia/Tehran'))[2], (new DateTimeImmutable('2027-01-01 00:00', new DateTimeZone('Asia/Tehran')))->getTimestamp());
t('ever: slot 0, never ends', G::period_slot('ever', $at('2026-12-31 23:00', 'Asia/Tehran')), array('0', '9999-12-31 00:00:00', null));

/* ------------------------------------------------------------ 3. state() */
$db = $GLOBALS['wpdb'] = new EfbF11TestDb();
$db->create_f11_tables();
Rate_Limit::reset_ready();
$now = (new DateTimeImmutable('2026-11-13 12:00', new DateTimeZone('Asia/Tehran')))->getTimestamp();
$ctx = function ($extra = array()) use ($now) { return array_merge(array('fid' => 5, 'now' => $now, 'uid' => 0, 'roles' => array(), 'logged_in' => false, 'manager' => false, 'ip_key' => '', 'cookie' => '', 't0' => null), $extra); };
$old = array('type' => 'form', 'stateForm' => '');
t('an old form without keys: open, no rules', array(G::state($old, $ctx())['state'], G::has_rules(G::cfg($old))), array('open', false));
$s = array('type' => 'form', 'sched_open' => '2026-11-14T09:00');
$st = G::state($s, $ctx());
t('before sched_open: pending, next = the opening', array($st['state'], $st['code'], $st['next']), array('pending', 'efb_form_pending', (new DateTimeImmutable('2026-11-14 09:00', new DateTimeZone('Asia/Tehran')))->getTimestamp()));
t('pending message names the opening in site time', G::message($st, G::cfg($s)), 'This form opens on 2026-11-14 09:00.');
$s = array('type' => 'form', 'sched_open' => '2026-11-01T09:00', 'sched_close' => '2026-11-13T11:59');
t('after sched_close: closed', G::state($s, $ctx())['state'], 'closed');
t('exactly at sched_close: closed', G::state(array('sched_close' => '2026-11-13T12:00'), $ctx())['state'], 'closed');
t('one minute before sched_close: open, next = the close', G::state(array('sched_close' => '2026-11-13T12:01'), $ctx())['next'], $now + 60);
$s = array('sched_close' => '2026-11-13T11:55', 'sched_grace' => '10');
t('grace: a session prepared before the close may still send within the minutes', G::state($s, $ctx(array('t0' => $now - 3600)))['state'], 'open');
t('grace: a session prepared after the close may not', G::state($s, $ctx(array('t0' => $now - 60)))['state'], 'closed');
t('grace: without a session time (old cached client) the form is closed', G::state($s, $ctx())['state'], 'closed');
t('grace ends', G::state($s, $ctx(array('t0' => $now - 3600, 'now' => $now + 600)))['state'], 'closed');
$s = array('sched_close' => '2026-11-13T11:00', 'sched_closed_msg' => 'Registration ended %s, see you next year');
t('the form\'s own closed text with the date', G::message(G::state($s, $ctx()), G::cfg($s)), 'Registration ended 2026-11-13 11:00, see you next year');
$GLOBALS['locale'] = 'fa_IR';
t('fa: Persian digits in the date', G::message(G::state(array('sched_close' => '2026-11-13T11:00'), $ctx()), G::cfg(array('sched_close' => '2026-11-13T11:00'))), 'This form closed on ۲۰۲۶-۱۱-۱۳ ۱۱:۰۰ and no longer accepts answers.');
$GLOBALS['locale'] = 'ar';
t('ar: Arabic-Indic digits', G::locale_digits('12'), '١٢');
$GLOBALS['locale'] = 'en_US';

// Total cap: the counter, or the real entries when there is no counter yet.
for ($i = 0; $i < 2; $i++) $db->add_entry(5, array(), '2026-11-01 10:00:00');
$s = array('lim_total' => '3', 'lim_show_left' => '1');
$st = G::state($s, $ctx());
t('total 3 with 2 entries: open, 1 left', array($st['state'], $st['left'], $st['used']), array('open', 1, 2));
t('prepare block: {state, closes_at, left} with lim_show_left', G::public_status($s, $ctx()), array('state' => 'open', 'closes_at' => null, 'left' => 1));
t('prepare block without lim_show_left has no left', array_key_exists('left', G::public_status(array('lim_total' => '3'), $ctx())), false);
$r = G::reserve_for_submit($s, array(), 5, $ctx());
t('reserve the last place: one claim on lt:5', array(count($r['claims']), $r['claims'][0]['s']), array(1, 'lt:5'));
t('now full', G::state($s, $ctx())['state'], 'full');
$e = G::reserve_for_submit($s, array(), 5, $ctx());
t('reserve on a full form: efb_form_full, nothing taken', array($e->get_error_code(), Rate_Limit::peek_bucket(G::total_bucket(5))), array('efb_form_full', 3));
t('full message: the default sentence', $e->get_error_message(), 'This form has reached its limit and no longer accepts answers.');
G::release($r['claims']);
t('release gives the place back', G::state($s, $ctx())['state'], 'open');
G::on_entries_purged(array(1), 'delete', array(), array(5));
t('entries deleted: the counter is rebuilt from the entries', Rate_Limit::peek_bucket(G::total_bucket(5)), null);
t('the 100-on-120 case: a cap below the existing entries is full at once', G::state(array('lim_total' => '1'), $ctx())['state'], 'full');

// Roles (D).
$p = array('stateForm' => '1', 'allowed_roles' => 'editor');
t('role: a subscriber is refused', G::state($p, $ctx(array('uid' => 7, 'logged_in' => true, 'roles' => array('subscriber'))))['code'], 'efb_role_denied');
t('role: an editor passes', G::state($p, $ctx(array('uid' => 8, 'logged_in' => true, 'roles' => array('editor'))))['state'], 'open');
t('role: an administrator (manage_options) always passes', G::state($p, $ctx(array('uid' => 1, 'logged_in' => true, 'roles' => array('administrator'), 'manager' => true)))['state'], 'open');
t('role: without stateForm the list does nothing', G::state(array('allowed_roles' => 'editor'), $ctx(array('uid' => 7, 'logged_in' => true, 'roles' => array('subscriber'))))['state'], 'open');
t('role: empty list = every logged-in user (today)', G::state(array('stateForm' => '1'), $ctx(array('uid' => 7, 'logged_in' => true, 'roles' => array('subscriber'))))['state'], 'open');
t('role message', G::message(array('state' => 'role'), G::cfg($p)), 'Your account does not have access to this form.');
t('role before schedule', G::state(array_merge($p, array('sched_open' => '2027-01-01T00:00')), $ctx(array('uid' => 7, 'logged_in' => true, 'roles' => array('subscriber'))))['state'], 'role');

// Per person (C): user / IP / cookie / e-mail, per period.
$GLOBALS['plan'] = 'free_plus';
$c = array('lim_user' => '1', 'lim_ip' => '2', 'lim_cookie' => '1', 'lim_email' => '1', 'lim_email_field' => 'em1', 'lim_period' => 'day');
$items = array(array('id_' => 'em1', 'type' => 'email', 'value' => ' Guest@Example.TEST '));
$v = $ctx(array('fid' => 6, 'uid' => 9, 'logged_in' => true, 'ip_key' => '203.0.113.5', 'cookie' => str_repeat('a', 32)));
$r = G::reserve_for_submit($c, $items, 6, $v);
t('per person: four claims (user, ip, cookie, email)', array_map(function ($x) { return $x['s']; }, $r['claims']), array('lu:6', 'li:6', 'lc:6', 'le:6'));
t('per person: the day counter ends at local midnight (UTC)', $r['claims'][0]['e'], '2026-11-13 20:31:00');
t('state: the same user is limited until tomorrow', G::state($c, $v)['state'], 'limited');
$e = G::reserve_for_submit($c, $items, 6, $v);
t('second submission: efb_limit_reached, reopen_at = local midnight, nothing taken', array($e->get_error_code(), $e->get_error_data()['reopen_at'], Rate_Limit::peek('li:6', '203.0.113.5', 'd20261113')), array('efb_limit_reached', (new DateTimeImmutable('2026-11-14 00:00', new DateTimeZone('Asia/Tehran')))->getTimestamp(), 1));
t('limited message with the reopening time', $e->get_error_message(), 'You can send this form again after 2026-11-14 00:00.');
$items2 = array(array('id_' => 'em1', 'type' => 'email', 'value' => 'guest@example.test'));
$e = G::reserve_for_submit(array('lim_email' => '1', 'lim_email_field' => 'em1'), $items2, 6, $ctx(array('now' => $now)));
t('e-mail: upper case and spaces count as the same address (ever)', $e === null || is_array($e) ? 'taken' : 'refused', 'taken');
$e2 = G::reserve_for_submit(array('lim_email' => '1', 'lim_email_field' => 'em1'), array(array('id_' => 'em1', 'value' => ' GUEST@example.test')), 6, $ctx());
t('e-mail: a second time is refused', is_wp_error($e2) ? $e2->get_error_code() : 'ok', 'efb_limit_reached');
t('e-mail: a field hidden by logic (absent) does not count', is_array(G::reserve_for_submit(array('lim_email' => '1', 'lim_email_field' => 'em1'), array(), 6, $ctx())), true);
$tomorrow = $ctx(array('fid' => 6, 'uid' => 9, 'logged_in' => true, 'ip_key' => '203.0.113.5', 'cookie' => str_repeat('a', 32), 'now' => $now + 13 * 3600));
t('the next local day: the same person may send again', is_array(G::reserve_for_submit($c, $items, 6, $tomorrow)), true);
$guest = $ctx(array('ip_key' => '198.51.100.1', 'cookie' => ''));
$g = G::reserve_for_submit(array('lim_user' => '1', 'lim_cookie' => '1'), array(), 6, $guest);
t('guest: lim_user does not apply; a cookie id is made for the cookie limit', array(count($g['claims']), strlen($g['cookie'])), array(1, 32));
unset($GLOBALS['plan']);
$GLOBALS['plan'] = 'free';
t('Free plan: per-person limits are not applied (reserve takes nothing)', G::reserve_for_submit($c, $items, 7, $v)['claims'], array());
t('Free plan: state stays open', G::state($c, $v)['state'], 'open');
t('Free plan: the total cap still applies', G::state(array('lim_total' => '1'), $ctx())['state'], 'full');
$GLOBALS['filters']['efb_f11_plan'] = function ($need, $part) { return $part === 'person' ? 'free' : $need; };
t('efb_f11_plan filter can open per-person limits to Free', count(G::reserve_for_submit($c, $items, 8, $v)['claims']), 4);
unset($GLOBALS['filters']['efb_f11_plan'], $GLOBALS['plan']);

/* ------------------------------------------------------------ 6. card_html */
$h = G::card_html('closed', 'Closed <b>now</b> & "then"', 5, 1800000000, 'swap');
t('card: role=status, aria-hidden icon, escaped message, data attributes', (bool) (strpos($h, 'role="status"') && strpos($h, 'aria-hidden="true"') && strpos($h, 'Closed &lt;b&gt;now&lt;/b&gt; &amp; &quot;then&quot;') && strpos($h, 'data-efb-gate="closed"') && strpos($h, 'data-efb-gate-next="1800000000"') && strpos($h, 'data-efb-gate-mode="swap"')), true);
t('card for an open form is hidden', strpos(G::card_html('open', '', 5), ' d-none') !== false, true);
t('render_gate: a non-cacheable page gets only the card when closed', G::render_gate(array('sched_close' => '2020-01-01T00:00'), 5, false)['card_only'], true);
t('render_gate: a cacheable page gets the card and hides the form', array_intersect_key(G::render_gate(array('sched_close' => '2020-01-01T00:00'), 5, true), array('card_only' => 1, 'hide_form' => 1)), array('card_only' => false, 'hide_form' => true));
t('render_gate: no rules → null (old forms print what they printed)', G::render_gate(array('type' => 'form'), 5, false), null);

echo "\nRESULTS: {$passed} passed, {$failed} failed\n";
exit($failed ? 1 : 0);
