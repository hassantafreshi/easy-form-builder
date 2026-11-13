<?php
/**
 * F08-B: the hidden-field part of the submit handler, extracted from class-Emsfb-public.php and run on a
 * fixture without WordPress: smart_hidden_rows_efb() (before Conditional Logic reads the answers),
 * smart_hidden_final_efb() (field tags from the checked answers), the hiddenField case of the field loop
 * and filter_attributes_by_type_efb(), smart_visitor_content_efb() (the visitor's e-mail copy).
 *   A fixed value, {user:email}, {ip}, {date}, {field} and a mixed template are the server's whatever the
 *   browser sends; {get:…} from the request; {ref} only http(s); a row the browser did not send is made;
 *   a prefill key in the request wins; an empty value makes no row; the visitor's copy keeps only the rows
 *   marked hdn_visitor_mail. The HTTP twin on a real site is tests/test-hidden-field-submit-http.php.
 *
 * Run: php tests/test-hidden-field-submit.php
 */
define('ABSPATH', __DIR__ . '/');
function sanitize_text_field($v) { if (is_array($v) || is_object($v)) return ''; $s = strip_tags((string) $v); $s = preg_replace('/%[a-f0-9]{2}/i', '', $s); return trim(preg_replace('/[\r\n\t ]+/', ' ', $s)); }
function sanitize_url($v) { return preg_replace('|[^a-z0-9-~+_.?#=!&;,/:%@$\|*\'()\[\]\x80-\xff]|i', '', (string) $v); }
function esc_url_raw($v) { return sanitize_url($v); }
function sanitize_email($v) { return filter_var((string) $v, FILTER_SANITIZE_EMAIL); }
function esc_html($s) { return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8'); }
function apply_filters($h, $v) { return $v; }
function is_user_logged_in() { return !empty($GLOBALS['user']); }
function wp_get_current_user() { return (object) $GLOBALS['user']; }
function get_user_meta($id, $k) { return $k === 'first_name' ? 'Ana' : ''; }
function wp_generate_password($n) { return substr('ABCDEFGHJKLMNPQRSTUVWXYZabcdef0123', 0, $n); }
function get_bloginfo() { return 'Site'; }
function home_url($p = '') { return 'https://site.test' . $p; }
function wp_strip_all_tags($s) { return trim(strip_tags((string) $s)); }
require __DIR__ . '/../includes/class-Emsfb-smart-tags.php';
/* F13 stand-in: this form stores IP addresses anonymized. */
eval('namespace Emsfb; class Privacy { public static function store_ip($ip, $fid) { return preg_replace("/\\\\.\\\\d+$/", ".0", (string) $ip); } }');

$pass = 0; $fail = 0;
function t($label, $actual, $expected) { global $pass, $fail; if ($actual === $expected) { $pass++; echo "[PASS] $label\n"; return; } $fail++; echo "[FAIL] $label\n  expected " . json_encode($expected, JSON_UNESCAPED_UNICODE) . "\n  actual   " . json_encode($actual, JSON_UNESCAPED_UNICODE) . "\n"; }

/* The real methods, lifted from the source. */
$src = file_get_contents(__DIR__ . '/../includes/class-Emsfb-public.php');
$method = function ($name) use ($src) {
	if (!preg_match('/\n\t(?:private|public)(?: static)? function ' . preg_quote($name, '/') . '\(.*?\n\t\}\n/s', $src, $m)) { echo "[FAIL] method $name not found\n"; exit(1); }
	return $m[0];
};
$case = preg_match("/\t+case 'hiddenField':\n.*?\t+break;\n/s", $src, $m) ? $m[0] : '';
t('the hiddenField case is in the field loop', $case !== '' && strpos($case, "filter_attributes_by_type_efb(\$item, 'hiddenField')") !== false, true);
$code = 'class Harness {
	public $id = 7; public $lanText = array("PleaseFillForm" => "Please complete the form.");
	public $efb_request_query_efb = array();
	public $ip = "203.0.113.9";
	public function get_ip_address() { return $this->ip; }
	private function is_same_site_url_efb($u) { return strpos((string) $u, "https://site.test/") === 0; }
	' . $method('smart_context_efb') . $method('smart_hidden_rows_efb') . $method('smart_hidden_final_efb') . $method('filter_attributes_by_type_efb') . $method('filter_and_sanitize_attributes_efb') . $method('sanitize_value_efb') . $method('smart_visitor_content_efb') . '
	private $smart_ctx_efb = null; private $smart_hidden_efb = array(); private $smart_req_efb = array();
	public function run_case($item) { $f = array("type" => "hiddenField"); $is_valid = 0; $validated_item = null; $still_processing = true; switch ($f["type"]) { ' . str_replace('\\Emsfb\\', '\\Emsfb\\', $case) . ' } return array($is_valid, $validated_item); }
	public function call($m, $args) { return call_user_func_array(array($this, $m), $args); }
	public function hidden() { return $this->smart_hidden_efb; }
}';
eval('namespace Emsfb; ' . $code);

$form = array(
	array('type' => 'form', 'formName' => 'Leads', 'st_uses' => 'get,user,date,ip,field,user:email,ref'),
	array('id_' => '1', 'type' => 'step'),
	array('id_' => 'nm', 'type' => 'text', 'name' => 'Name'),
	array('id_' => 'color', 'type' => 'radio', 'name' => 'Color'),
	array('id_' => 'o1', 'type' => 'option', 'parent' => 'color', 'value' => 'Red', 'opt_value' => 'r'),
	array('id_' => 'h_static', 'type' => 'hiddenField', 'name' => 'Campaign', 'value' => 'spring-2026', 'amount' => 5),
	array('id_' => 'h_mail', 'type' => 'hiddenField', 'name' => 'Member', 'value' => '{user:email}'),
	array('id_' => 'h_ip', 'type' => 'hiddenField', 'name' => 'IP', 'value' => '{ip}'),
	array('id_' => 'h_utm', 'type' => 'hiddenField', 'name' => 'UTM', 'value' => '{get:utm_source}'),
	array('id_' => 'h_gclid', 'type' => 'hiddenField', 'name' => 'Click', 'value' => '{get:gclid}'),
	array('id_' => 'h_ref', 'type' => 'hiddenField', 'name' => 'Ref', 'value' => '{ref}'),
	array('id_' => 'h_pipe', 'type' => 'hiddenField', 'name' => 'Pipe', 'value' => '{field:color.value}/{field:color}'),
	array('id_' => 'h_mix', 'type' => 'hiddenField', 'name' => 'Mix', 'value' => '{get:utm_source}-{user:id|guest}'),
	array('id_' => 'h_pre', 'type' => 'hiddenField', 'name' => 'Pre', 'value' => 'x', 'prefill_key' => 'pre'),
	array('id_' => 'h_vis', 'type' => 'hiddenField', 'name' => 'Vis', 'value' => 'shown', 'hdn_visitor_mail' => '1'),
);
$posted = array(
	array('id_' => 'nm', 'type' => 'text', 'value' => 'Ana'),
	array('id_' => 'color', 'type' => 'radio', 'value' => 'Red', 'id_ob' => 'o1'),
	array('id_' => 'h_static', 'type' => 'hiddenField', 'value' => 'hacked'),
	array('id_' => 'h_static', 'type' => 'hiddenField', 'value' => 'twice'),
	array('id_' => 'h_mail', 'type' => 'hiddenField', 'value' => 'evil@x.test'),
	array('id_' => 'h_ip', 'type' => 'hiddenField', 'value' => '1.1.1.1'),
	array('id_' => 'h_utm', 'type' => 'hiddenField', 'value' => 'bing'),
	array('id_' => 'h_gclid', 'type' => 'hiddenField', 'value' => 'abc<b>1</b>'),
	array('id_' => 'h_ref', 'type' => 'hiddenField', 'value' => 'javascript:alert(1)'),
	array('id_' => 'h_pipe', 'type' => 'hiddenField', 'value' => 'tampered'),
);
$h = new \Emsfb\Harness();
$h->efb_request_query_efb = array('utm_source' => 'google', 'pre' => 'from-url');
$GLOBALS['user'] = null;
$rows = $h->call('smart_hidden_rows_efb', array($form, $posted, array('url' => 'https://site.test/p/?utm_source=google', 'page_id' => 0)));
$val = function ($rows, $id) { $out = array(); foreach ($rows as $r) if (($r['id_'] ?? '') === $id) $out[] = $r['value']; return count($out) === 1 ? $out[0] : ($out ? $out : null); };
t('a fixed value: one row, the stored text (two posted copies ignored)', $val($rows, 'h_static'), 'spring-2026');
t('{user:email} of a guest: no row, the posted value ignored', $val($rows, 'h_mail'), null);
t('{ip}: the server\'s address as F13 stores it for the form (anonymized), not the posted one', $val($rows, 'h_ip'), '203.0.113.0');
t('{get:utm_source}: from the request, not "bing"', $val($rows, 'h_utm'), 'google');
t('{get:gclid} absent from the request: the cleaned posted copy (a resume link)', $val($rows, 'h_gclid'), 'abc1');
t('{ref} with javascript: refused (no row)', $val($rows, 'h_ref'), null);
t('{field:…} before the loop: from the answers as sent (opt_value / label)', $val($rows, 'h_pipe'), 'r/Red');
t('a mixed template is the server\'s', $val($rows, 'h_mix'), 'google-guest');
t('a prefill key in the request wins', $val($rows, 'h_pre'), 'from-url');
t('a row the browser did not send is made by the server', $val($rows, 'h_vis'), 'shown');
t('other answers are untouched', array($val($rows, 'nm'), $val($rows, 'color')), array('Ana', 'Red'));
$r = null; foreach ($rows as $x) if (($x['id_'] ?? '') === 'h_static') $r = $x;
t('the row carries the saved name and type', array($r['name'], $r['type'], $r['id_ob'], $r['form_id']), array('Campaign', 'hiddenField', 'h_static_', '7'));

// A logged-in user.
$GLOBALS['user'] = array('ID' => 3, 'user_login' => 'ana', 'user_email' => 'ana@site.test', 'display_name' => 'Ana', 'roles' => array('subscriber'));
$h2 = new \Emsfb\Harness();
$h2->efb_request_query_efb = array();
$rows2 = $h2->call('smart_hidden_rows_efb', array($form, $posted, array('url' => 'https://site.test/p/')));
t('{user:email} of the logged-in user, not the posted one', $val($rows2, 'h_mail'), 'ana@site.test');
t('a mixed template with the user', $val($rows2, 'h_mix'), '-3');

// After the loop: field tags from the checked answers.
$validated = array(array('id_' => 'nm', 'type' => 'text', 'value' => 'Ana'), array('id_' => 'color', 'type' => 'radio', 'value' => 'Red'), array('id_' => 'h_pipe', 'type' => 'hiddenField', 'value' => 'old'));
$after = $h->call('smart_hidden_final_efb', array($validated, $form));
t('after the loop: {field:…} from the checked answers', $val($after, 'h_pipe'), 'r/Red');
$after2 = $h->call('smart_hidden_final_efb', array(array(array('id_' => 'nm', 'type' => 'text', 'value' => 'Ana'), array('id_' => 'h_pipe', 'type' => 'hiddenField', 'value' => 'r/Red')), $form));
t('after the loop: an answer that was refused leaves its tags empty', $val($after2, 'h_pipe'), '/');

// The case of the field loop.
list($ok, $item) = $h->call('run_case', array(array('id_' => 'h_static', 'name' => 'Campaign', 'type' => 'hiddenField', 'value' => "a\r\n<b>b</b>", 'price' => '99', 'verified' => 'email', 'url' => 'https://x')));
t('the field loop keeps only the allowed keys (no price, url or verified badge from the browser)', array($ok, array_keys($item)), array(1, array('id_', 'name', 'type', 'value')));
t('the field loop cleans the value (one line, no markup)', $item['value'], 'a b');

// The visitor's e-mail copy.
$content = '<table><!--efb-hdn:0--><tr><td>UTM</td><td>google</td></tr><!--/efb-hdn--><tr><td>Name</td></tr><!--efb-hdn:1--><tr><td>Vis</td></tr><!--/efb-hdn--></table>';
t('the visitor\'s copy: hidden rows out unless marked for the visitor', \Emsfb\Harness::smart_visitor_content_efb($content), '<table><tr><td>Name</td></tr><!--efb-hdn:1--><tr><td>Vis</td></tr><!--/efb-hdn--></table>');
t('a content without hidden rows is unchanged', \Emsfb\Harness::smart_visitor_content_efb('<p>x</p>'), '<p>x</p>');

echo "\nRESULTS: $pass passed, $fail failed\n";
exit($fail ? 1 : 0);
