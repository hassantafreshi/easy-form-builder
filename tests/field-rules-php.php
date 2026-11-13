<?php
/**
 * Not a test. The PHP side of the F03 parity tests (tests/test-field-rules-*.js): reads
 * [{fn, args}] as JSON on stdin and prints the PHP results (Emsfb\Field_Rules) in the same order.
 * Pure functions only; WordPress' escaping and the plan helpers are stubbed.
 */
define('ABSPATH', __DIR__ . '/');
function esc_html($s) { return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8'); }
function esc_attr($s) { return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8'); }
function sanitize_text_field($v) { return is_array($v) ? '' : trim(preg_replace('/[\r\n\t ]+/', ' ', strip_tags((string) $v))); }
function apply_filters($h, $v) { return $h === 'efb_f03_plan' ? 'free' : $v; }
require __DIR__ . '/../includes/class-Emsfb-masks.php';
require __DIR__ . '/../includes/class-Emsfb-field-rules.php';
require __DIR__ . '/../includes/class-Emsfb-unique.php';
use Emsfb\Field_Rules as FR;

$in = json_decode(stream_get_contents(STDIN), true);
$out = array();
foreach ((array) $in as $c) {
	$a = isset($c['args']) ? $c['args'] : array();
	$fn = $c['fn'];
	switch ($fn) {
		case 'consts': $out[] = array('KEYS' => FR::KEYS, 'MSG_RULES' => FR::MSG_RULES, 'CODES' => FR::CODES, 'GATED' => FR::GATED, 'TYPES' => FR::TYPES, 'DEFAULTS' => FR::DEFAULTS); break;
		case 'sanitize_key': $out[] = FR::sanitize_key($a[0], $a[1]); break;
		case 'active_rules': $out[] = FR::active_rules($a[0]); break;
		case 'count_chars': $out[] = FR::count_chars($a[0]); break;
		case 'pw_score': $out[] = FR::pw_score($a[0], $a[1]); break;
		case 'domains_list': $out[] = FR::domains_list($a[0]); break;
		case 'unique_key': $out[] = \Emsfb\Unique_Values::key($a[0], $a[1]); break;
		case 'first_error':
			// check() on one value; the code of the first failing rule (null = passes).
			$f = $a[0];
			$item = array('id_' => isset($f['id_']) ? $f['id_'] : 'x', 'type' => isset($f['type']) ? $f['type'] : 'text', 'value' => $a[1]);
			$ctx = isset($a[2]) && is_array($a[2]) ? $a[2] : array();
			$req = isset($ctx['confirm']) ? array('password_confirms' => array($item['id_'] => $ctx['confirm'])) : array();
			$raw = array('f:' . $item['id_'] => (string) $a[1]);
			$r = FR::check($f, $item, $req, $raw, array(), array('form_id' => 1, 'form_name' => isset($ctx['formName']) ? $ctx['formName'] : '', 'emails' => isset($ctx['emails']) ? $ctx['emails'] : array(), 'no_db' => true));
			$out[] = $r['ok'] ? null : $r['code'];
			break;
		default:
			$out[] = method_exists('\\Emsfb\\Field_Rules', $fn) ? call_user_func_array(array('\\Emsfb\\Field_Rules', $fn), $a) : null;
	}
}
echo json_encode($out, JSON_UNESCAPED_UNICODE);
