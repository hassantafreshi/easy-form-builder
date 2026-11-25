<?php
/**
 * F14-H: a small streaming XLSX writer without a library (spec §8.6).
 *
 * One worksheet. Rows are written to a temporary sheet1.xml as they come (inline strings, so there is no shared
 * string table to keep in memory); finish() packs the parts into a zip: ZipArchive when PHP has it, else this
 * file's own zip writer (local headers, central directory, end record; STORE, or DEFLATE in chunks through
 * deflate_init() when zlib has it, so a big sheet never sits in memory). CRC-32 is computed in chunks too.
 *
 *  - the header row is bold and frozen, with an AutoFilter over the data;
 *  - right to left (sheetView rightToLeft) when the site is;
 *  - a cell is a number (t="n") only when its column is numeric and the value is a plain number; everything
 *    else is an inline string, which a spreadsheet never runs as a formula (no "'" prefix needed);
 *  - characters XML 1.0 does not allow are removed; a cell is cut at 32,767 characters (Excel's limit) with "…";
 *  - at most 16,384 columns (Excel's limit). No ZIP64: the export is capped far below 4 GB (efb_export_sync_max).
 *
 * The filter efb_xlsx_force_store makes the writer use its own zip writer even with ZipArchive (tests).
 */

namespace Emsfb;

if (!defined('ABSPATH')) {
	exit;
}

class Xlsx_Writer {

	const MAX_COLS = 16384;
	const MAX_CELL = 32767;

	private $dir;
	private $sheet_path;
	private $fh;
	private $rows = 0;
	private $cols = 0;
	private $numeric = array();
	private $rtl = false;
	private $title = 'Entries';
	public $used_zip = '';

	/**
	 * @param array $opts rtl (bool), title (sheet name), numeric (column indexes, 0-based, that may hold numbers)
	 */
	public function __construct(array $opts = array()) {
		$this->rtl = !empty($opts['rtl']);
		if (isset($opts['title'])) $this->title = self::sheet_name((string) $opts['title']);
		$this->numeric = isset($opts['numeric']) ? array_flip(array_map('intval', (array) $opts['numeric'])) : array();
		$base = function_exists('get_temp_dir') ? get_temp_dir() : sys_get_temp_dir();
		$this->dir = rtrim($base, '/\\') . '/efb-xlsx-' . bin2hex(random_bytes(6));
		if (!@mkdir($this->dir, 0700, true) && !is_dir($this->dir)) throw new \RuntimeException('temp');
		$this->sheet_path = $this->dir . '/sheet1.xml';
		$this->fh = fopen($this->sheet_path, 'wb');
		fwrite($this->fh, '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n"
			. '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
			. '<sheetViews><sheetView workbookViewId="0"' . ($this->rtl ? ' rightToLeft="1"' : '') . '><pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/><selection pane="bottomLeft"/></sheetView></sheetViews>'
			. '<sheetFormatPr defaultRowHeight="15"/><sheetData>');
	}

	/** A sheet name Excel accepts (no []:*?/\, 1-31 characters). */
	public static function sheet_name($s) {
		$s = trim(preg_replace('/[\[\]:*?\/\\\\]/u', ' ', $s));
		if ($s === '') $s = 'Entries';
		return function_exists('mb_substr') ? mb_substr($s, 0, 31, 'UTF-8') : substr($s, 0, 31);
	}

	/** Column letters of a 1-based index (1 = A, 27 = AA, 16384 = XFD). */
	public static function col($n) {
		$s = '';
		$n = (int) $n;
		while ($n > 0) { $m = ($n - 1) % 26; $s = chr(65 + $m) . $s; $n = (int) (($n - $m - 1) / 26); }
		return $s;
	}

	/**
	 * ISO-8859-1 bytes as UTF-8, without mbstring (COMP-01: utf8_encode() is deprecated since PHP 8.2 and only
	 * WordPress 6.9 and later polyfill it).
	 */
	public static function latin1_to_utf8($v) {
		$v = (string) $v;
		$out = '';
		for ($i = 0, $n = strlen($v); $i < $n; $i++) {
			$o = ord($v[$i]);
			$out .= $o < 0x80 ? $v[$i] : chr(0xC0 | ($o >> 6)) . chr(0x80 | ($o & 0x3F));
		}
		return $out;
	}

	/** Text a worksheet may hold: XML 1.0 characters only, at most 32,767 characters, escaped. */
	public static function clean($v) {
		$v = is_scalar($v) ? (string) $v : (string) json_encode($v);
		if (!preg_match('//u', $v)) $v = function_exists('mb_convert_encoding') ? mb_convert_encoding($v, 'UTF-8', 'UTF-8') : self::latin1_to_utf8($v);
		$v = preg_replace('/[^\x{9}\x{A}\x{D}\x{20}-\x{D7FF}\x{E000}-\x{FFFD}\x{10000}-\x{10FFFF}]/u', '', $v);
		if ((function_exists('mb_strlen') ? mb_strlen($v, 'UTF-8') : strlen($v)) > self::MAX_CELL) {
			$v = (function_exists('mb_substr') ? mb_substr($v, 0, self::MAX_CELL - 1, 'UTF-8') : substr($v, 0, self::MAX_CELL - 1)) . '…';
		}
		return htmlspecialchars($v, ENT_XML1 | ENT_QUOTES, 'UTF-8');
	}

	/** One row (the first row written is the header: bold). */
	public function add_row(array $cells) {
		$this->rows++;
		$cells = array_slice(array_values($cells), 0, self::MAX_COLS);
		$this->cols = max($this->cols, count($cells));
		$header = $this->rows === 1;
		$x = '<row r="' . $this->rows . '">';
		foreach ($cells as $i => $v) {
			$ref = self::col($i + 1) . $this->rows;
			if ($v === null || $v === '') continue;
			$s = is_scalar($v) ? (string) $v : '';
			if (!$header && isset($this->numeric[$i]) && preg_match('/^-?(0|[1-9]\d{0,14})(\.\d{1,10})?$/', $s)) {
				$x .= '<c r="' . $ref . '" t="n"><v>' . $s . '</v></c>';
			} else {
				$x .= '<c r="' . $ref . '" t="inlineStr"' . ($header ? ' s="1"' : '') . '><is><t xml:space="preserve">' . self::clean($v) . '</t></is></c>';
			}
		}
		fwrite($this->fh, $x . '</row>');
	}

	private function parts() {
		$last = self::col(max(1, $this->cols)) . max(1, $this->rows);
		return array(
			'[Content_Types].xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n" . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/><Override PartName="/docProps/app.xml" ContentType="application/vnd.openxmlformats-officedocument.extended-properties+xml"/></Types>',
			'_rels/.rels' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n" . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/extended-properties" Target="docProps/app.xml"/></Relationships>',
			'docProps/app.xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n" . '<Properties xmlns="http://schemas.openxmlformats.org/officeDocument/2006/extended-properties"><Application>Easy Form Builder</Application></Properties>',
			'xl/workbook.xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n" . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><bookViews><workbookView/></bookViews><sheets><sheet name="' . htmlspecialchars($this->title, ENT_XML1 | ENT_QUOTES, 'UTF-8') . '" sheetId="1" r:id="rId1"/></sheets><definedNames><definedName name="_xlnm._FilterDatabase" localSheetId="0" hidden="1">\'' . str_replace("'", "''", htmlspecialchars($this->title, ENT_XML1 | ENT_QUOTES, 'UTF-8')) . '\'!$A$1:$' . preg_replace('/\d+$/', '', $last) . '$' . max(1, $this->rows) . '</definedName></definedNames></workbook>',
			'xl/_rels/workbook.xml.rels' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n" . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>',
			'xl/styles.xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n" . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><fonts count="2"><font><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="11"/><name val="Calibri"/></font></fonts><fills count="2"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill></fills><borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders><cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs><cellXfs count="2"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/><xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0" applyFont="1"/></cellXfs><cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles></styleSheet>',
		);
	}

	/** Closes the sheet and writes the .xlsx to $out. Returns the size in bytes. */
	public function finish($out) {
		$last = self::col(max(1, $this->cols)) . max(1, $this->rows);
		fwrite($this->fh, '</sheetData><autoFilter ref="A1:' . $last . '"/><pageMargins left="0.7" right="0.7" top="0.75" bottom="0.75" header="0.3" footer="0.3"/></worksheet>');
		fclose($this->fh);
		$parts = $this->parts();
		$force = function_exists('apply_filters') ? (bool) apply_filters('efb_xlsx_force_store', false) : false;
		if (!$force && class_exists('ZipArchive')) {
			$z = new \ZipArchive();
			if ($z->open($out, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) === true) {
				foreach ($parts as $name => $xml) $z->addFromString($name, $xml);
				$z->addFile($this->sheet_path, 'xl/worksheets/sheet1.xml');
				$z->close();
				$this->used_zip = 'ZipArchive';
				$this->cleanup();
				return (int) filesize($out);
			}
		}
		$zip = new Zip_Stream($out);
		foreach ($parts as $name => $xml) $zip->add_string($name, $xml);
		$zip->add_file('xl/worksheets/sheet1.xml', $this->sheet_path);
		$zip->close();
		$this->used_zip = $zip->deflate ? 'deflate' : 'store';
		$this->cleanup();
		return (int) filesize($out);
	}

	private function cleanup() {
		@unlink($this->sheet_path);
		@rmdir($this->dir);
	}

	public function __destruct() {
		if (is_resource($this->fh)) fclose($this->fh);
		if (is_file($this->sheet_path)) $this->cleanup();
	}
}

/**
 * The zip writer used without ZipArchive: each entry as a local header, its data (stored, or raw DEFLATE in
 * 1 MB chunks) and a data descriptor-free header patched after the data (sizes and CRC-32 known by then);
 * then the central directory and the end record. Names are ASCII; dates are a fixed DOS time.
 */
class Zip_Stream {
	public $deflate = false;
	private $fh;
	private $entries = array();

	public function __construct($path) {
		$this->fh = fopen($path, 'w+b');
		$this->deflate = function_exists('deflate_init') && !(function_exists('apply_filters') && apply_filters('efb_xlsx_no_deflate', false));
	}

	public function add_string($name, $data) {
		$tmp = fopen('php://temp', 'w+b');
		fwrite($tmp, $data);
		rewind($tmp);
		$this->add_stream($name, $tmp);
		fclose($tmp);
	}

	public function add_file($name, $path) {
		$in = fopen($path, 'rb');
		$this->add_stream($name, $in);
		fclose($in);
	}

	private function add_stream($name, $in) {
		$offset = ftell($this->fh);
		$method = $this->deflate ? 8 : 0;
		// Local header with zero CRC / sizes, patched below.
		fwrite($this->fh, pack('VvvvvvVVVvv', 0x04034b50, 20, 0, $method, 0, 0x5121, 0, 0, 0, strlen($name), 0) . $name);
		$crc = hash_init('crc32b');
		$size = 0;
		$csize = 0;
		$ctx = $this->deflate ? deflate_init(ZLIB_ENCODING_RAW, array('level' => 6)) : null;
		while (!feof($in)) {
			$chunk = fread($in, 1048576);
			if ($chunk === false || $chunk === '') break;
			hash_update($crc, $chunk);
			$size += strlen($chunk);
			$out = $ctx ? deflate_add($ctx, $chunk, ZLIB_NO_FLUSH) : $chunk;
			$csize += strlen($out);
			fwrite($this->fh, $out);
		}
		if ($ctx) { $out = deflate_add($ctx, '', ZLIB_FINISH); $csize += strlen($out); fwrite($this->fh, $out); }
		$crc32 = hexdec(hash_final($crc));
		$end = ftell($this->fh);
		fseek($this->fh, $offset + 14);
		fwrite($this->fh, pack('VVV', $crc32, $csize, $size));
		fseek($this->fh, $end);
		$this->entries[] = array($name, $offset, $crc32, $csize, $size, $method);
	}

	public function close() {
		$cd = ftell($this->fh);
		foreach ($this->entries as $e) {
			list($name, $offset, $crc, $csize, $size, $method) = $e;
			fwrite($this->fh, pack('VvvvvvvVVVvvvvvVV', 0x02014b50, 20, 20, 0, $method, 0, 0x5121, $crc, $csize, $size, strlen($name), 0, 0, 0, 0, 0, $offset) . $name);
		}
		$len = ftell($this->fh) - $cd;
		fwrite($this->fh, pack('VvvvvVVv', 0x06054b50, 0, 0, count($this->entries), count($this->entries), $len, $cd, 0));
		fclose($this->fh);
	}
}
