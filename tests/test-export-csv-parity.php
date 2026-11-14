<?php
/**
 * F14-H: the server's CSV (Entries::csv_cell / csv_line) and the browser's (convertToCSV_emsFormBuilder in
 * list_form-efb.js) give the same bytes for the same cells: tokens (@n#, @efb@nq#, @efb@dq#, @efb@sq#, @efb@vq#,
 * @prv@), formula-looking cells escaped with "'" (= + - @ TAB CR) but plain numbers kept, quoting of " , ; CR LF,
 * Persian text, empty cells. The JS function is lifted from the shipped file and run by node.
 *
 * Run: php tests/test-export-csv-parity.php   (needs node)
 */
define('ABSPATH', __DIR__ . '/');
$passed = 0; $failed = 0;
function t($name, $actual, $expected) {
	global $passed, $failed;
	if ($actual === $expected) { echo "[PASS] {$name}\n"; $passed++; return; }
	echo "[FAIL] {$name}: expected " . var_export($expected, true) . ', got ' . var_export($actual, true) . "\n";
	$failed++;
}
function wp_json_encode($v, $f = 0) { return json_encode($v, $f); }
require_once __DIR__ . '/../includes/class-Emsfb-privacy.php';
require_once __DIR__ . '/../includes/admin/class-Emsfb-entries.php';
use Emsfb\Entries;

$rows = array(
	array('Name', 'Note', 'Formula', 'Number', 'نام'),
	array('Ali "the 1st"', "line1@n#line2", '=HYPERLINK("x")', '-5', 'علی، رضا'),
	array('a,b', 'semi;colon', '+cmd', '+1.5', '@efb@dq#quoted@efb@dq#'),
	array('@user', "tab\there", "\tstarts with tab", '3,14', '@prv@'),
	array('', 'it@efb@sq#s', 'back@efb@vq#tick', '-', '@efb@nq#start'),
	array("cr\rinside", 'plain', '-1e5', '0', '=1+1'),
);
$php = "\xEF\xBB\xBF" . implode("\r\n", array_map(array('Emsfb\\Entries', 'csv_line'), $rows));
$src = file_get_contents(__DIR__ . '/../includes/admin/assets/js/list_form-efb.js');
preg_match('/function convertToCSV_emsFormBuilder\(objArray\) \{[\s\S]*?\n\}\n/', $src, $m);
t('the JS function was found in list_form-efb.js', isset($m[0]), true);
$js = "var efb_var = { text: {} };\n" . $m[0] . "\nprocess.stdout.write(Buffer.from(convertToCSV_emsFormBuilder(JSON.parse(require('fs').readFileSync(0, 'utf8'))), 'utf8').toString('base64'));";
$tmp = tempnam(sys_get_temp_dir(), 'efbcsv') . '.js';
file_put_contents($tmp, $js);
$p = proc_open('node ' . escapeshellarg($tmp), array(0 => array('pipe', 'r'), 1 => array('pipe', 'w'), 2 => array('pipe', 'w')), $pipes);
fwrite($pipes[0], json_encode($rows));
fclose($pipes[0]);
$out = base64_decode(stream_get_contents($pipes[1]));
$err = stream_get_contents($pipes[2]);
proc_close($p);
@unlink($tmp);
t('node ran', $err, '');
t('PHP and JS give the same bytes (BOM, CRLF, quoting, formula guard, tokens)', bin2hex($php) === bin2hex($out), true);
if ($php !== $out) { echo "PHP: " . json_encode($php) . "\nJS:  " . json_encode($out) . "\n"; }
t('a formula gets an apostrophe; a plain negative or signed number does not', array(Entries::csv_cell('=1+1'), Entries::csv_cell('-5'), Entries::csv_cell('+1.5'), Entries::csv_cell('@user'), Entries::csv_cell('-')), array("'=1+1", '-5', '+1.5', "'@user", "'-"));
t('quotes doubled, the cell quoted', Entries::csv_cell('say "hi", ok'), '"say ""hi"", ok"');
t('an anonymized answer reads "Removed for privacy"', Entries::csv_cell('@prv@'), 'Removed for privacy');
echo "\nRESULTS: {$passed} passed, {$failed} failed\n";
exit($failed ? 1 : 0);
