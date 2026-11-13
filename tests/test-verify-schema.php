<?php
/**
 * F02-B: the DB 1.8 migration (Install::upgrade_f02_schema()) on the SQLite stand-in.
 *  - EMSFB_DB_VERSION is 1.8 or later; the F02 step runs after F03's and before the emsfb_stts_ early
 *    return of upgrade_schema(); install() creates the table on a fresh site.
 *  - A 1.7 site gets emsfb_vrfy_ from the real DDL (spec §5.3: UNIQUE challenge_id, KEY session_field,
 *    dest_time, expires; no raw destination column); a second run applies nothing.
 *  - The counters stay F11's emsfb_vrfy_rl_ (no second limiter table).
 *  - Without the table the engine answers "unavailable" (fail-closed; see test-verify-rest.php).
 *
 * Run: php tests/test-verify-schema.php
 */
require __DIR__ . '/verify-test-lib.php';
use Emsfb\Verification as V;

function dbDelta($sql) { $GLOBALS['wpdb']->exec_mysql_ddl($sql); $GLOBALS['dbdelta'][] = $sql; return array(); }
if (!function_exists('get_role')) { function get_role($r) { return null; } }
$main = file_get_contents(__DIR__ . '/../emsfb.php');
vt('EMSFB_DB_VERSION is 1.8 or later', (bool) preg_match('/define\("EMSFB_DB_VERSION",\s*(1\.([8-9]|\d\d)|[2-9]\.\d+)\)/', $main), true);
define('EMSFB_DB_VERSION', 1.8);
foreach (array('payments/class-Emsfb-payment-store.php', 'class-Emsfb-masks.php', 'class-Emsfb-field-rules.php', 'class-Emsfb-unique.php', 'class-Emsfb-install.php') as $f) require_once __DIR__ . '/../includes/' . $f;

$src = file_get_contents(__DIR__ . '/../includes/class-Emsfb-install.php');
$up = substr($src, strpos($src, 'static function upgrade_schema'));
vt('F02 step after F03 and before the early return of upgrade_schema()', strpos($up, 'self::upgrade_f03_schema()') < strpos($up, 'self::upgrade_f02_schema()') && strpos($up, 'self::upgrade_f02_schema()') < strpos($up, 'return $applied;'), true);
vt('install() creates the F02 table too', strpos($src, 'self::f02_ddl( $wpdb->prefix, $charset_collate )') !== false, true);

$db = $GLOBALS['wpdb'] = new EfbF11TestDb();
$db->create_f11_tables();
V::reset_ready();
vt('a 1.7 site has no challenge table: not ready', V::ready(), false);
$first = \Emsfb\Install::upgrade_f02_schema();
vt('upgrade: emsfb_vrfy_ is created', $first, array('emsfb_vrfy_: CREATE TABLE'));
vt('the engine sees it at once (ready cache reset)', V::ready(), true);
$cols = array_column($db->pdo->query("PRAGMA table_info('wp_emsfb_vrfy_')")->fetchAll(PDO::FETCH_ASSOC), 'name');
vt('20 columns as the spec', count($cols), 20);
vt('no column for a raw destination or a raw code', array_values(array_intersect($cols, array('destination', 'phone', 'email', 'code'))), array());
vt('second run applies nothing', \Emsfb\Install::upgrade_f02_schema(), array());
$d = V::ddl('wp_', '');
vt('DDL keys: UNIQUE challenge_id, session_field (fid,sid,field_id), dest_time, expires', (bool) (preg_match('/UNIQUE KEY challenge_id \(challenge_id\)/', $d) && preg_match('/KEY session_field \(fid,sid,field_id\)/', $d) && preg_match('/KEY dest_time \(dest_hash,created_at\)/', $d) && preg_match('/KEY expires \(expires_at\)/', $d)), true);
$q = "INSERT INTO wp_emsfb_vrfy_ (challenge_id, fid, field_id, channel, dest_hash, dest_mask, sid, created_at, expires_at) VALUES ('" . str_repeat('a', 32) . "', 1, 'f', 'sms', 'h', 'm', 's', '2026-11-13', '2026-11-13')";
vt('UNIQUE challenge_id refuses a duplicate', array($db->query($q), $db->query($q)), array(1, false));
vt('no second limiter table: the counters are F11\'s emsfb_vrfy_rl_', array(strpos(file_get_contents(__DIR__ . '/../includes/class-Emsfb-verification.php'), 'CREATE TABLE {$prefix}emsfb_vrfy_rl_'), strpos(file_get_contents(__DIR__ . '/../includes/class-Emsfb-verification.php'), 'Rate_Limit::hit(') !== false), array(false, true));
$mig = glob(__DIR__ . '/../docs/migrations/f02-*.md');
vt('a dated migration note exists in docs/migrations', count($mig) >= 1, true);

vt_done();
