<?php
/**
 * F17-D: the Address & Postal add-on's address search on the server (vendor/address/class-Emsfb-address-lookup.php),
 * with WordPress stubbed and the outbound request counted (never sent):
 *  - the route answers 404 for a form without a group that has addr_lookup on, in "direct" / "off" mode and below Free Plus;
 *  - one request per distinct query (cached by hash), the query URL-encoded (& and # intact), the site's user agent and
 *    referer, the service address from the settings only (https; a request cannot pick another host);
 *  - one request a second for the site (429, retry_after 1), the visitor's quota (429 after 10);
 *  - the answer cut to display_name / lat / lon / known address keys, five at most, no markup;
 *  - the search block on the page: inside the group, a privacy line for the mode, the attribution, escaped;
 *  - its settings (F12b-L rule, F20-core brief item 6): adr_lookup of the settings row, written only through
 *    set_setting_Emsfb() with that one key; the old option emsfb_adr_lookup is read until the one-time move, which
 *    writes, reads the table back and only then deletes it (idempotent; a refused write keeps the option and tries
 *    again); core's main Settings window never sends or receives the key (Captcha\Admin::is_addon_key()).
 *
 * Run: php tests/test-address-lookup.php
 */
namespace Emsfb {
	class Rate_Limit { public static $n = array(); public static function hit($s, $k, $w, $l) { $c = (self::$n[$k] ?? 0) + 1; if ($c > $l) return false; self::$n[$k] = $c; return true; } }
	class Client_Ip { public static function limit_key($fid) { return 'ip|' . $fid; } }
}
namespace {
define('ABSPATH', __DIR__ . '/');
define('DAY_IN_SECONDS', 86400);
define('EMSFB_PLUGIN_VERSION', '4.3.1');
define('EMSFB_PLUGIN_URL', 'https://site.test/wp-content/plugins/easy-form-builder/');
$GLOBALS['O'] = array(); $GLOBALS['TR'] = array(); $GLOBALS['CALLS'] = array(); $GLOBALS['PLAN'] = true; $GLOBALS['NOW'] = 1000.0;
function get_option($k, $d = false) { return $GLOBALS['O'][$k] ?? $d; }
function update_option($k, $v, $a = null) { $GLOBALS['O'][$k] = $v; return true; }
function delete_option($k) { unset($GLOBALS['O'][$k]); return true; }
/* The settings row (emsfb_setting's last row) and core's one setter, with its wipe guard (keys left out are kept). */
$GLOBALS['ROW'] = array('emailSupporter' => 'owner@example.test', 'AdnADR' => 1); $GLOBALS['WRITES'] = array(); $GLOBALS['WRITE_OK'] = true;
function get_setting_Emsfb($m = '') { return json_decode(json_encode($GLOBALS['ROW'])); }
function emsfb_json_decode_efb($s, $assoc = false) { return json_decode($s, $assoc); }
class EfbLkFn { public function set_setting_Emsfb($n, $email = '') { $GLOBALS['WRITES'][] = array_keys((array) $n); if (!$GLOBALS['WRITE_OK']) return false; $GLOBALS['ROW'] = array_merge($GLOBALS['ROW'], json_decode(json_encode($n), true)); return true; } }
function get_efbFunction() { return new EfbLkFn(); }
function get_transient($k) { return $GLOBALS['TR'][$k] ?? false; }
function set_transient($k, $v, $t) { $GLOBALS['TR'][$k] = $v; return true; }
function sanitize_text_field($s) { return trim(strip_tags((string) $s)); }
function wp_strip_all_tags($s) { return trim(strip_tags((string) $s)); }
function esc_attr($s) { return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8'); }
function esc_html($s) { return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8'); }
function apply_filters($h, $v) { return $v; }
function add_filter() {} function add_action() {}
function home_url($p = '') { return 'https://site.test/'; }
function wp_parse_url($u, $c = -1) { return parse_url($u, $c); }
function efb_plan_at_least_efb($p) { return $GLOBALS['PLAN']; }
function emsfb_json_normalize_efb($s) { return stripslashes((string) $s); }
function is_wp_error($x) { return $x instanceof WP_Error; }
function wp_safe_remote_get($url, $args) { $GLOBALS['CALLS'][] = array('url' => $url, 'ua' => $args['user-agent'], 'ref' => $args['headers']['Referer']); return array('code' => 200, 'body' => json_encode(array(
	array('display_name' => '<b>Valiasr</b> St, Tehran', 'lat' => '35.7', 'lon' => '51.4', 'osm_id' => 9, 'address' => array('road' => 'Valiasr', 'city' => 'Tehran', 'state' => 'Tehran', 'postcode' => '1234567890', 'secret' => 'x')),
	array('display_name' => 'a'), array('display_name' => 'b'), array('display_name' => 'c'), array('display_name' => 'd'), array('display_name' => 'e'),
))); }
function wp_remote_retrieve_response_code($r) { return $r['code']; }
function wp_remote_retrieve_body($r) { return $r['body']; }
class WP_Error { public $c; public function __construct($c = '', $m = '') { $this->c = $c; } }
class WP_REST_Request { private $p; public function __construct($p) { $this->p = $p; } public function get_param($k) { return $this->p[$k] ?? null; } }
class WP_REST_Response { public $data; public $status; public function __construct($d, $s) { $this->data = $d; $this->status = $s; } }
class EfbLkDb { public $prefix = 'wp_'; public $forms = array(); public function prepare($q, $id) { return (int) $id; } public function get_var($id) { return is_string($id) && strpos($id, 'SELECT setting FROM `wp_emsfb_setting`') === 0 ? json_encode($GLOBALS['ROW']) : ($this->forms[$id] ?? null); } }
$GLOBALS['wpdb'] = new EfbLkDb();
$dir = __DIR__ . '/../vendor/address';
if (!is_readable($dir . '/class-Emsfb-address-lookup.php')) { echo "[SKIP] the Address & Postal add-on is not in vendor/address\n\nRESULTS: 0 passed, 0 failed\n"; exit(0); }
require_once __DIR__ . '/../includes/class-Emsfb-address-schemes.php';
require_once $dir . '/class-Emsfb-address-lookup.php';
use Emsfb\Address_Lookup as L;

$pass = 0; $fail = 0;
function t($name, $ok, $info = '') { global $pass, $fail; if ($ok) { $pass++; echo "[PASS] {$name}\n"; } else { $fail++; echo "[FAIL] {$name}" . ($info !== '' ? ': ' . (is_string($info) ? $info : json_encode($info, JSON_UNESCAPED_UNICODE)) : '') . "\n"; } }
$form = function ($lookup) { return addslashes(json_encode(array(array('type' => 'form'), array('type' => 'step'), array('id_' => 'a', 'type' => 'stateProvince', 'cmp_grp' => 'a', 'cmp_kind' => 'address', 'addr_scheme' => 'ir', 'addr_part' => 'state', 'addr_lookup' => $lookup)))); };
$GLOBALS['wpdb']->forms = array(7 => $form('1'), 8 => $form('0'));
$q = function ($p) { return L::rest_search(new WP_REST_Request($p)); };
$tick = function () { $GLOBALS['TR']['efb_geo_lock'] = '0'; }; // a second later

t('settings: proxy and nominatim.openstreetmap.org by default', L::settings() === array('mode' => 'proxy', 'url' => 'https://nominatim.openstreetmap.org'));
t('a service address must be https without a query', L::clean_url('http://x.test') === '' && L::clean_url('https://geo.example.org/nominatim/') === 'https://geo.example.org/nominatim' && L::clean_url('https://x.test/?a=1') === '' && L::clean_url('https://x.test/a b') === '');
t('a form without search: 404', $q(array('q' => 'Valiasr', 'fid' => 8))->status === 404 && !$GLOBALS['CALLS']);
t('no form id / an unknown form: 404', $q(array('q' => 'Valiasr'))->status === 404 && $q(array('q' => 'Valiasr', 'fid' => 99))->status === 404);
$GLOBALS['PLAN'] = false;
t('below Free Plus: 404', $q(array('q' => 'Valiasr', 'fid' => 7))->status === 404);
$GLOBALS['PLAN'] = true;
t('a query of 2 letters: 400', $q(array('q' => 'ab', 'fid' => 7))->status === 400);
$r = $q(array('q' => 'Valiasr & #12', 'cc' => 'ir', 'lang' => 'fa-IR', 'fid' => 7));
t('a search: 200, one request', $r->status === 200 && count($GLOBALS['CALLS']) === 1, $r->data);
$u = $GLOBALS['CALLS'][0]['url'];
t('the query URL-encoded (& and # intact), countrycodes, accept-language, jsonv2 with address details, 5 at most', strpos($u, 'https://nominatim.openstreetmap.org/search?') === 0 && strpos($u, 'q=Valiasr%20%26%20%2312') !== false && strpos($u, 'countrycodes=ir') !== false && strpos($u, 'accept-language=fa-IR') !== false && strpos($u, 'format=jsonv2') !== false && strpos($u, 'limit=5') !== false, $u);
t('the site\'s user agent and referer', $GLOBALS['CALLS'][0]['ua'] === 'EasyFormBuilder/4.3.1 (WordPress; +site.test)' && $GLOBALS['CALLS'][0]['ref'] === 'https://site.test/');
$res = $r->data['results'];
t('the answer: five at most, display_name without markup, only known address keys', count($res) === 5 && $res[0]['display_name'] === 'Valiasr St, Tehran' && !isset($res[0]['address']['secret']) && $res[0]['address']['postcode'] === '1234567890' && !isset($res[0]['osm_id']), $res[0]);
$tick();
$r2 = $q(array('q' => 'Valiasr & #12', 'cc' => 'ir', 'lang' => 'fa-IR', 'fid' => 7));
t('the same search again: from the cache, no request', $r2->status === 200 && !empty($r2->data['cached']) && count($GLOBALS['CALLS']) === 1);
$GLOBALS['TR']['efb_geo_lock'] = (string) microtime(true); // a request went out just now
$r3 = $q(array('q' => 'Enghelab', 'cc' => 'ir', 'fid' => 7));
t('another search within the same second: 429 retry_after 1 (one request a second for the site)', $r3->status === 429 && $r3->data['retry_after'] === 1 && count($GLOBALS['CALLS']) === 1, $r3->data);
$tick();
t('a second later it goes out', $q(array('q' => 'Enghelab', 'cc' => 'ir', 'fid' => 7))->status === 200 && count($GLOBALS['CALLS']) === 2);
$tick();
t('postcode → city: postalcode= (no q)', $q(array('postalcode' => '90210', 'cc' => 'us', 'fid' => 7))->status === 200 && strpos($GLOBALS['CALLS'][2]['url'], 'postalcode=90210') !== false && strpos($GLOBALS['CALLS'][2]['url'], '&q=') === false);
t('a postcode with other characters: 400', $q(array('postalcode' => '<x>', 'fid' => 7))->status === 400);
$codes = array();
for ($i = 0; $i < 8; $i++) { $tick(); $codes[] = $q(array('q' => 'Street ' . $i, 'fid' => 7))->status; }
t('the visitor\'s quota: the 11th search a minute answers 429', $codes[count($codes) - 1] === 429 && in_array(200, $codes, true), $codes);
L::save(array('mode' => 'direct', 'url' => 'https://geo.example.org'));
\Emsfb\Rate_Limit::$n = array();
t('direct mode: the route answers 404 (the browser asks the service itself)', $q(array('q' => 'Valiasr', 'fid' => 7))->status === 404);
$h = L::group_extra('', (object) array('cmp_grp' => 'g"1', 'cmp_kind' => 'address', 'addr_scheme' => 'ir', 'addr_lookup' => '1'), 7, array());
t('direct mode on the page: the service address for the browser, its own privacy line, escaped group id', strpos($h, 'data-url="https://geo.example.org"') !== false && strpos($h, 'to find it.') !== false && strpos($h, 'data-grp="g&quot;1"') !== false && strpos($h, 'data-cc="ir"') !== false);
L::save(array('mode' => 'proxy', 'url' => ''));
$h = L::group_extra('', array('cmp_grp' => 'g1', 'cmp_kind' => 'address', 'addr_scheme' => 'sa', 'addr_lookup' => '1'), 7, array());
t('proxy mode on the page: a block tag first, a labelled search box, a 44 px button, the listbox, the attribution, "through this site", no service address', strpos($h, '<div class="efb efb-addr-lookup') === 0 && strpos($h, '<label') !== false && strpos($h, 'min-height:44px') !== false && strpos($h, 'role="listbox"') !== false && strpos($h, 'openstreetmap.org/copyright') !== false && strpos($h, 'through this site') !== false && strpos($h, 'data-url') === false);
t('a group without addr_lookup gets nothing', L::group_extra('', array('cmp_grp' => 'g1', 'cmp_kind' => 'address', 'addr_scheme' => 'ir'), 7, array()) === '');
L::save(array('mode' => 'off', 'url' => ''));
t('off: nothing on the page, 404 on the route', L::group_extra('', array('cmp_grp' => 'g1', 'cmp_kind' => 'address', 'addr_lookup' => '1'), 7, array()) === '' && $q(array('q' => 'Valiasr', 'fid' => 7))->status === 404);
$GLOBALS['wpdb']->forms[9] = addslashes(json_encode(array(array('type' => 'form'), array('id_' => 'm', 'type' => 'maps'))));
L::save(array('mode' => 'proxy', 'url' => ''));
t('a request cannot choose the host (no url parameter is read)', !in_array('url', L::PARAMS, true));


/* --- the settings: adr_lookup of the settings row (F12b-L rule) --- */
$row0 = array('emailSupporter' => 'owner@example.test', 'AdnADR' => 1);
$GLOBALS['ROW'] = $row0;
$GLOBALS['WRITES'] = array();
$r = L::save(array('mode' => 'direct', 'url' => 'https://geo.example.org/'));
t('save(): one write through set_setting_Emsfb() with the key adr_lookup only; the row keeps every other key', $GLOBALS['WRITES'] === array(array('adr_lookup')) && $GLOBALS['ROW']['adr_lookup'] === array('mode' => 'direct', 'url' => 'https://geo.example.org') && $GLOBALS['ROW']['emailSupporter'] === 'owner@example.test' && $r === array('mode' => 'direct', 'url' => 'https://geo.example.org'), $GLOBALS['ROW']);
t('… no option written', !array_key_exists('emsfb_adr_lookup', $GLOBALS['O']));
$GLOBALS['WRITE_OK'] = false;
$r = L::save(array('mode' => 'off', 'url' => ''));
t('save() when the table refuses the write: an error, the stored settings unchanged', $r instanceof WP_Error && $r->c === 'adr_save' && L::settings()['mode'] === 'direct');
$GLOBALS['WRITE_OK'] = true;
t('save() refuses a service address that is not https, nothing written', L::save(array('mode' => 'proxy', 'url' => 'http://x.test')) instanceof WP_Error && L::settings()['url'] === 'https://geo.example.org');

/* --- the one-time move from the old option --- */
$GLOBALS['ROW'] = $row0; $GLOBALS['WRITES'] = array();
$GLOBALS['O']['emsfb_adr_lookup'] = array('mode' => 'off', 'url' => 'https://old.example.org/');
t('before the move the old option is read', L::settings() === array('mode' => 'off', 'url' => 'https://old.example.org'));
$GLOBALS['WRITE_OK'] = false;
t('a refused write: the option stays, no flag, the search keeps the old settings (tried again later)', L::migrate() === false && isset($GLOBALS['O']['emsfb_adr_lookup']) && !isset($GLOBALS['O']['emsfb_adr_moved']) && L::settings()['mode'] === 'off');
$GLOBALS['WRITE_OK'] = true; $GLOBALS['WRITES'] = array();
t('the move: written (one key), read back from the table, then the option deleted and the flag set', L::migrate() === true && $GLOBALS['WRITES'] === array(array('adr_lookup')) && $GLOBALS['ROW']['adr_lookup'] === array('mode' => 'off', 'url' => 'https://old.example.org') && !isset($GLOBALS['O']['emsfb_adr_lookup']) && $GLOBALS['O']['emsfb_adr_moved'] === 1);
t('… the same settings after the move', L::settings() === array('mode' => 'off', 'url' => 'https://old.example.org'));
$GLOBALS['WRITES'] = array();
t('idempotent: a second run does nothing', L::migrate() === false && $GLOBALS['WRITES'] === array());
unset($GLOBALS['O']['emsfb_adr_moved']);
$GLOBALS['O']['emsfb_adr_lookup'] = array('mode' => 'direct', 'url' => '');
$GLOBALS['WRITES'] = array();
t('an option left over while the row already has the key: the row wins, the option is only removed', L::migrate() === true && $GLOBALS['WRITES'] === array() && !isset($GLOBALS['O']['emsfb_adr_lookup']) && L::settings()['mode'] === 'off');
unset($GLOBALS['O']['emsfb_adr_moved']);
t('nothing to move (a new site): the flag is set, nothing written', L::migrate() === true && $GLOBALS['WRITES'] === array() && $GLOBALS['O']['emsfb_adr_moved'] === 1);

/* --- core's side: the key is the add-on's --- */
require_once __DIR__ . '/../includes/captcha/class-Emsfb-captcha-admin.php';
$main = \Emsfb\Captcha\Admin::without_addon_keys(array('emailSupporter' => 'a@b.test', 'adr_lookup' => array('mode' => 'off'), 'captcha_turnstile' => array(), 'adr_lookup_x' => '1'));
t('core: adr_lookup is an add-on key — the main Settings window neither receives nor saves it', \Emsfb\Captcha\Admin::is_addon_key('adr_lookup') && array_keys($main) === array('emailSupporter', 'adr_lookup_x'), array_keys($main));

echo "\nRESULTS: {$pass} passed, {$fail} failed\n";
exit($fail ? 1 : 0);
}
