<?php
/**
 * Helper of tests/test-calendar-parity.js: reads a JSON list of calls from stdin and prints the answers
 * of the server twin (\Emsfb\Calendar) as JSON, without WordPress. Not a test by itself.
 */
define('ABSPATH', __DIR__ . '/');
require_once __DIR__ . '/../includes/class-Emsfb-calendar.php';
use Emsfb\Calendar as C;
$in = json_decode(stream_get_contents(STDIN), true);
$out = array();
foreach ((array) $in as $q) {
	$a = isset($q['args']) ? $q['args'] : array();
	switch ($q['fn']) {
		case 'convert': $r = C::convert($a[0], $a[1], $a[2], isset($a[3]) ? $a[3] : array()); $out[] = is_array($r) && isset($r['iso']) ? array($r['iso'], (bool) $r['approx']) : null; break;
		case 'format': $e = C::engine($a[1]); $l = C::iso_to_local($a[0], $a[1], 0); $out[] = C::format_local($e, array($l[0], $l[1], $l[2]), $a[2], $a[3], array('digits' => $a[4], 'time' => isset($a[5]) ? $a[5] : null)); break;
		case 'parse': $p = C::parse_local(C::engine($a[1]), $a[0], $a[2]); $out[] = $p ? C::local_to_iso($p, $a[1]) : null; break;
		case 'today': $out[] = C::today($a[0], strtotime($a[1])); break;
		case 'wall': $out[] = C::wall_to_utc($a[0], $a[1], $a[2]); break;
		case 'validate':
			$r = C::validate_value($a[0], $a[1], $a[2]);
			$c = C::normalize_field($a[0], $a[2]);
			$msg = $r['ok'] ? null : C::message($r['errors'][0], $c, isset($a[2]['cal']) ? $a[2]['cal'] : '');
			$out[] = array('ok' => $r['ok'], 'row' => $r['row'], 'errors' => $r['errors'], 'warnings' => $r['warnings'], 'message' => $msg, 'min' => $r['resolved']['min'], 'max' => $r['resolved']['max'], 'today' => $r['resolved']['today']);
			break;
		case 'normalize_field': $n = C::normalize_field($a[0], $a[1]); if (!$n['time_by_weekday']) $n['time_by_weekday'] = new stdClass(); $out[] = $n; break;
		case 'holidays': $c = C::normalize_field($a[0], $a[1]); $r = C::resolve($c, $a[1]); $out[] = (object) C::list_holidays($c, $r, $a[1]); break;
		case 'sanitize_key': $v = C::sanitize_key($a[0], $a[1]); $out[] = $v instanceof stdClass ? new stdClass() : $v; break;
		case 'config_issues': $out[] = C::config_issues($a[0], $a[1]); break;
		case 'upgrade_row': $u = C::upgrade_row($a[0], !empty($a[1])); $out[] = array($u, C::rollback_row($u)); break;
		case 'display_text': $out[] = C::display_text($a[0], $a[1], $a[2], $a[3]); break;
		case 'logic': $out[] = C::logic_compare($a[0], $a[1], $a[2], $a[3]); break;
		default: $out[] = null;
	}
}
echo json_encode($out, JSON_UNESCAPED_UNICODE);
