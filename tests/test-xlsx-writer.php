<?php
/**
 * F14-H: the streaming XLSX writer (includes/admin/class-Emsfb-xlsx-writer.php), on three paths: ZipArchive, the
 * plugin's own zip with DEFLATE (filter efb_xlsx_force_store), and its own zip with STORE (efb_xlsx_no_deflate,
 * like a PHP without zlib).
 *
 *  - every part is well-formed XML (DOMDocument) and the zip is sound: unzip -t when present, ZipArchive opens it,
 *    the CRC-32 of each entry matches its bytes;
 *  - the header row is bold, frozen (pane ySplit 1) and filtered (autoFilter A1:…); right to left on request;
 *  - numbers only in numeric columns and only for plain numbers; "=1+1" stays text (inline string, never a formula);
 *  - control characters are removed; a 40,000-character cell is cut at 32,767 with "…"; Persian text kept;
 *  - 150 columns (A … ET) all written; column letters up to XFD;
 *  - 20,000 rows × 20 columns written with less than 64 MB of memory;
 *  - when openpyxl (Python) is installed, it opens the files and reads the same cells; LibreOffice, when installed,
 *    converts the file to CSV without complaint.
 *
 * Run: php tests/test-xlsx-writer.php
 */
define('ABSPATH', __DIR__ . '/');
$passed = 0; $failed = 0;
function t($name, $actual, $expected) {
	global $passed, $failed;
	if ($actual === $expected) { echo "[PASS] {$name}\n"; $passed++; return; }
	echo "[FAIL] {$name}: expected " . var_export($expected, true) . ', got ' . var_export($actual, true) . "\n";
	$failed++;
}
$GLOBALS['mode'] = 'zip';
function apply_filters($h, $v) {
	if ($h === 'efb_xlsx_force_store') return $GLOBALS['mode'] !== 'zip';
	if ($h === 'efb_xlsx_no_deflate') return $GLOBALS['mode'] === 'store';
	return $v;
}
require __DIR__ . '/../includes/admin/class-Emsfb-xlsx-writer.php';
use Emsfb\Xlsx_Writer;

t('column letters', array(Xlsx_Writer::col(1), Xlsx_Writer::col(26), Xlsx_Writer::col(27), Xlsx_Writer::col(150), Xlsx_Writer::col(16384)), array('A', 'Z', 'AA', 'ET', 'XFD'));
t('a sheet name without []:*?/\\, at most 31 characters', Xlsx_Writer::sheet_name('a/b[c]:' . str_repeat('x', 40)), 'a b c  ' . str_repeat('x', 24));
$dir = sys_get_temp_dir() . '/efb-xlsx-test-' . getmypid();
@mkdir($dir);
$have_unzip = trim((string) shell_exec('command -v unzip')) !== '';
$have_py = trim((string) shell_exec('python3 -c "import openpyxl; print(1)" 2>/dev/null')) === '1';
foreach (array('zip', 'deflate', 'store') as $mode) {
	$GLOBALS['mode'] = $mode;
	$w = new Xlsx_Writer(array('rtl' => true, 'title' => 'فرم', 'numeric' => array(1)));
	$head = array('Name', 'Age');
	for ($i = 3; $i <= 150; $i++) $head[] = 'Col ' . $i;
	$w->add_row($head);
	$r2 = array('علی "x" <b>', '42'); $r2[149] = 'last';
	$w->add_row($r2);
	$w->add_row(array("ctl\x01\x08ok", '-3.5', '=1+1', str_repeat('z', 40000), '007', '1e5'));
	$w->add_row(array('', 'not a number'));
	$file = "{$dir}/t-{$mode}.xlsx";
	$size = $w->finish($file);
	t("{$mode}: the writer used " . ($mode === 'zip' ? 'ZipArchive' : $mode), $w->used_zip, $mode === 'zip' ? 'ZipArchive' : $mode);
	if ($have_unzip) { exec('unzip -tq ' . escapeshellarg($file) . ' 2>&1', $o, $rc); t("{$mode}: unzip -t finds no error", $rc, 0); }
	$z = new ZipArchive();
	t("{$mode}: ZipArchive opens it", $z->open($file), true);
	$names = array(); $crc_ok = true; $xml_ok = true; $parts = array();
	for ($i = 0; $i < $z->numFiles; $i++) {
		$st = $z->statIndex($i);
		$data = $z->getFromIndex($i);
		$names[] = $st['name'];
		$parts[$st['name']] = $data;
		if (crc32($data) !== $st['crc'] || strlen($data) !== $st['size']) $crc_ok = false;
		$d = new DOMDocument();
		if (!@$d->loadXML($data)) $xml_ok = false;
	}
	$z->close();
	sort($names);
	t("{$mode}: the seven parts", $names, array('[Content_Types].xml', '_rels/.rels', 'docProps/app.xml', 'xl/_rels/workbook.xml.rels', 'xl/styles.xml', 'xl/workbook.xml', 'xl/worksheets/sheet1.xml'));
	t("{$mode}: every CRC-32 and size matches", $crc_ok, true);
	t("{$mode}: every part is well-formed XML", $xml_ok, true);
	$sheet = $parts['xl/worksheets/sheet1.xml'];
	t("{$mode}: frozen header, AutoFilter over A1:ET4, right to left", strpos($sheet, '<pane ySplit="1"') !== false && strpos($sheet, '<autoFilter ref="A1:ET4"/>') !== false && strpos($sheet, 'rightToLeft="1"') !== false, true);
	t("{$mode}: the header is bold (s=1), 150 columns", substr_count(explode('</row>', $sheet)[0], 's="1"'), 150);
	t("{$mode}: 42 and -3.5 are numbers; 007, 1e5 and text stay text", strpos($sheet, '<c r="B2" t="n"><v>42</v></c>') !== false && strpos($sheet, '<c r="B3" t="n"><v>-3.5</v></c>') !== false && strpos($sheet, '<c r="E3" t="n">') === false && strpos($sheet, '<c r="B4" t="inlineStr">') !== false, true);
	t("{$mode}: =1+1 is an inline string (never a formula)", strpos($sheet, '<c r="C3" t="inlineStr"><is><t xml:space="preserve">=1+1</t></is></c>') !== false && strpos($sheet, '<f>') === false, true);
	t("{$mode}: control characters removed, quotes and tags escaped, Persian kept", strpos($sheet, '>ctlok<') !== false && strpos($sheet, 'علی &quot;x&quot; &lt;b&gt;') !== false, true);
	preg_match('/<c r="D3"[^>]*><is><t[^>]*>(z+)…<\/t>/u', $sheet, $m);
	t("{$mode}: a 40,000-character cell is cut at 32,767 (with …)", isset($m[1]) ? strlen($m[1]) + 1 : 0, 32767);
	t("{$mode}: the sheet is named", strpos($parts['xl/workbook.xml'], 'name="فرم"') !== false, true);
	if ($have_py) {
		$py = shell_exec('python3 -c ' . escapeshellarg("import openpyxl,sys\nwb=openpyxl.load_workbook(sys.argv[1]);ws=wb.active\nprint(ws.max_column, ws['B2'].value, ws['C3'].value, ws.freeze_panes, ws.sheet_view.rightToLeft)") . ' ' . escapeshellarg($file) . ' 2>&1');
		t("{$mode}: openpyxl reads it (150 columns, 42, '=1+1' as text, frozen at A2, RTL)", trim((string) $py), '150 42 =1+1 A2 True');
	} else {
		echo "[SKIP] openpyxl is not installed\n";
	}
}
$lo = trim((string) shell_exec('command -v soffice'));
if ($lo !== '') {
	exec('cd ' . escapeshellarg($dir) . ' && timeout 120 ' . escapeshellarg($lo) . ' --headless --convert-to csv t-store.xlsx >/dev/null 2>&1', $o2, $rc2);
	$csv = @file_get_contents("{$dir}/t-store.csv");
	t('LibreOffice converts the STORE-zipped file (no repair): Persian, quotes, the formula as text', is_string($csv) && strpos($csv, 'علی ""x"" <b>') !== false && strpos($csv, ',=1+1,') !== false, true);
} else {
	echo "[SKIP] LibreOffice is not installed\n";
}

// Memory: 20,000 rows × 20 columns.
$GLOBALS['mode'] = 'deflate';
$start = memory_get_usage();
$w = new Xlsx_Writer(array('numeric' => array(1)));
$w->add_row(array_map(function ($i) { return 'Column ' . $i; }, range(1, 20)));
for ($i = 0; $i < 20000; $i++) $w->add_row(array_merge(array('Person ' . $i, (string) $i), array_fill(0, 18, 'value ' . $i . ' with some text')));
$w->finish("{$dir}/big.xlsx");
$peak = memory_get_peak_usage() - $start;
echo '  20,000 rows: ' . round(filesize("{$dir}/big.xlsx") / 1048576, 1) . ' MB file, peak ' . round($peak / 1048576, 1) . " MB more memory\n";
t('20,000 rows written with less than 64 MB of memory', $peak < 64 * 1048576, true);
$z = new ZipArchive(); $z->open("{$dir}/big.xlsx");
$st = $z->statName('xl/worksheets/sheet1.xml');
t('the big sheet: CRC-32 of the deflated stream is right', crc32($z->getFromName('xl/worksheets/sheet1.xml')) === $st['crc'], true);
$z->close();

array_map('unlink', glob("{$dir}/*") ?: array());
@rmdir($dir);
echo "\nRESULTS: {$passed} passed, {$failed} failed\n";
exit($failed ? 1 : 0);
