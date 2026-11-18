<?php
/**
 * F17: localized name and address (docs/feature-gaps/F17-localized-name-address.fa.md).
 *
 * One definition (includes/data/name-address-efb.json, filter efb_name_address_data): six name formats and the
 * address formats (international, Iran, Saudi National Address, UAE, Egypt, US, UK, Canada, Germany, Japan, Türkiye),
 * each a list of parts with its stored type, label, autocomplete token, width, default required flag and rule.
 * The parts of one name or address are ordinary rows that share cmp_grp (the id_ of the first part) and say what
 * they are (cmp_kind, name_format / addr_scheme, name_part / addr_part). An old form has none of these keys: nothing
 * here touches it.
 *
 * Here: the save rules of the keys, the page attributes of a part, the server check of a part (digits, shape, list),
 * the group rules (Saudi "short address or full address"), the stored structure of an answer (grp, part, cmp, sch on
 * each row; na_full, the display line, on the group's first row) and the display helpers that are also the F20 P22
 * contract (includes/name-address-functions-efb.php, public/assets/js/name-address-efb.js). Address search, postcode
 * lookup and the postal typing masks are the Address & Postal add-on's (AdnADR) through the hooks named below.
 */

namespace Emsfb;

if (!defined('ABSPATH')) {
	exit;
}

class Address_Schemes {

	const VERSION = 1;
	const KEYS = array('cmp_grp', 'cmp_kind', 'name_format', 'name_part', 'name_strict', 'addr_scheme', 'addr_part', 'addr_cascade', 'addr_lookup', 'addr_req_mode');
	const NAME_PARTS = array('prefix', 'first', 'middle', 'last', 'father', 'grandfather', 'family', 'full');
	const MODES = array('', 'sa_short_or_full');

	private static $data = null;

	public static function register() {
		if (!function_exists('add_filter')) return;
		add_filter('efb_capabilities', array(__CLASS__, 'capabilities'), 34, 2);
		add_filter('efb_form_schema_field', array(__CLASS__, 'schema_field'), 17, 3);
		add_filter('efb_admin_localize_vars', array(__CLASS__, 'localize_vars'), 34, 2);
		add_action('wp_ajax_efb_na_default', array(__CLASS__, 'ajax_default'));
		add_filter('efb_field_templates', array(__CLASS__, 'field_templates'), 10, 3);
	}

	/** The chooser's "Always use this": the user's own default format (user meta efb_default_name_format / efb_default_addr_scheme). */
	public static function ajax_default() {
		if (!function_exists('current_user_can') || !current_user_can('edit_posts') || !check_ajax_referer('wp_rest', 'nonce', false)) wp_send_json_error(array('m' => 'forbidden'), 403);
		$kind = isset($_POST['kind']) ? sanitize_key(wp_unslash($_POST['kind'])) : '';
		$fmt = isset($_POST['format']) ? sanitize_key(wp_unslash($_POST['format'])) : '';
		$ok = $kind === 'name' ? isset(self::name_formats()[$fmt]) : ($kind === 'address' && isset(self::schemes()[$fmt]));
		if (!$ok) wp_send_json_error(array('m' => 'invalid'), 400);
		update_user_meta(get_current_user_id(), $kind === 'name' ? 'efb_default_name_format' : 'efb_default_addr_scheme', $fmt);
		wp_send_json_success(array('kind' => $kind, 'format' => $fmt));
	}

	/* ------------------------------------------------------------------ data */

	public static function data() {
		if (self::$data !== null) return self::$data;
		$d = json_decode((string) @file_get_contents(__DIR__ . '/data/name-address-efb.json'), true);
		$d = is_array($d) ? $d : array('name_formats' => array(), 'schemes' => array());
		if (function_exists('apply_filters')) {
			$f = apply_filters('efb_name_address_data', $d);
			if (is_array($f) && isset($f['schemes'], $f['name_formats'])) $d = $f;
		}
		return self::$data = $d;
	}

	/** Tests: drop the memo (after a filter changed). */
	public static function reset() { self::$data = null; }

	public static function schemes() { $d = self::data(); return isset($d['schemes']) ? $d['schemes'] : array(); }
	public static function name_formats() { $d = self::data(); return isset($d['name_formats']) ? $d['name_formats'] : array(); }

	/** Every address part id any format has (the allowed values of addr_part). */
	public static function addr_parts() {
		$out = array();
		foreach (self::schemes() as $s) foreach ($s['parts'] as $p) $out[$p['p']] = true;
		return array_keys($out);
	}

	/** The definition of a part: kind 'address' with scheme and part, or 'name' with format and part. */
	public static function part($kind, $set, $part) {
		$list = $kind === 'name' ? self::name_formats() : self::schemes();
		if (!isset($list[$set]['parts'])) return null;
		foreach ($list[$set]['parts'] as $p) if ($p['p'] === $part) return $p;
		return null;
	}

	private static function arr($r) { return is_array($r) ? $r : (is_object($r) ? get_object_vars($r) : array()); }

	/** The row's part definition (null for a row that is not part of a name or address group). */
	public static function row_def($row) {
		$r = self::arr($row);
		if (empty($r['cmp_grp']) || empty($r['cmp_kind'])) return null;
		if ($r['cmp_kind'] === 'name' && isset($r['name_format'], $r['name_part'])) return self::part('name', (string) $r['name_format'], (string) $r['name_part']);
		if ($r['cmp_kind'] === 'address' && isset($r['addr_scheme'], $r['addr_part'])) return self::part('address', (string) $r['addr_scheme'], (string) $r['addr_part']);
		return null;
	}

	/* ------------------------------------------------------------------ save */

	public static function is_key($k) { return in_array($k, self::KEYS, true); }

	/** The stored value of a key ('' = drop it). Twin: efbNaClean() in name-address-builder-efb.js. */
	public static function sanitize_key($k, $v) {
		if (is_bool($v)) $v = $v ? '1' : '0';
		if (!is_scalar($v)) return '';
		$v = trim((string) $v);
		switch ($k) {
			case 'cmp_grp': return preg_match('/^[A-Za-z0-9_-]{1,40}$/', $v) ? $v : '';
			case 'cmp_kind': return in_array($v, array('name', 'address'), true) ? $v : '';
			case 'name_format': return isset(self::name_formats()[$v]) ? $v : '';
			case 'name_part': return in_array($v, self::NAME_PARTS, true) ? $v : '';
			case 'addr_scheme': return isset(self::schemes()[$v]) ? $v : '';
			case 'addr_part': return in_array($v, self::addr_parts(), true) ? $v : '';
			case 'addr_req_mode': return in_array($v, self::MODES, true) && $v !== '' ? $v : '';
			case 'name_strict': case 'addr_cascade': case 'addr_lookup': return in_array(strtolower($v), array('1', 'true', 'on'), true) ? '1' : '0';
		}
		return '';
	}

	/* ------------------------------------------------------------------ values */

	public static function latin_digits($s) {
		return strtr((string) $s, array('۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4', '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
			'٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4', '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9', '−' => '-', '‐' => '-', '–' => '-', '‏' => '', '‎' => ''));
	}

	/** The value as it is stored: digits made Latin, spaces and case as the part says, the national shape. Twin: EFBNameAddr.normalize(). */
	public static function normalize($def, $v) {
		$v = trim(preg_replace('/\s+/u', ' ', (string) $v));
		if (!empty($def['n']) || !empty($def['nm']) || !empty($def['ltr'])) $v = self::latin_digits($v);
		$nm = isset($def['nm']) ? (string) $def['nm'] : '';
		if (strpos($nm, 'u') !== false) $v = strtoupper($v);
		if (strpos($nm, 's') !== false) $v = str_replace(array(' ', '-'), '', $v);
		if (strpos($nm, 'x') !== false) $v = str_replace(' ', '', $v);
		$f = isset($def['fmt']) ? $def['fmt'] : '';
		if ($f === 'us' && preg_match('/^\d{9}$/', $v)) $v = substr($v, 0, 5) . '-' . substr($v, 5);
		if (($f === 'uk' || $f === 'ca') && preg_match('/^[A-Z0-9]{5,7}$/', $v)) $v = substr($v, 0, -3) . ' ' . substr($v, -3);
		if ($f === 'jp' && preg_match('/^\d{7}$/', $v)) $v = substr($v, 0, 3) . '-' . substr($v, 3);
		return $v;
	}

	/** The F17 name rule (name_strict): letters of any script, marks, spaces, ZWNJ / ZWJ, ' ’ . - ; 1 to 60. */
	public static function name_ok($v) {
		return (bool) preg_match('/^[\p{L}\p{M}][\p{L}\p{M}\x{200C}\x{200D} \'’.\-]{0,59}$/u', (string) $v);
	}

	/**
	 * One value against its part. @return array ok, value (stored), code ('' | format | length | list).
	 * $words: floor words ("ground", "همکف"…) a floor part also takes.
	 */
	public static function check_value($def, $value, $words = null) {
		$v = self::normalize($def, $value);
		if ($v === '') return array('ok' => true, 'value' => '', 'code' => '');
		if (preg_match('/[\x00-\x1F\x7F]/', $v)) return array('ok' => false, 'value' => $v, 'code' => 'format');
		$len = function_exists('mb_strlen') ? mb_strlen($v, 'UTF-8') : strlen($v);
		if ((isset($def['mn']) && $len < (int) $def['mn']) || (isset($def['mx']) && $len > (int) $def['mx'])) return array('ok' => false, 'value' => $v, 'code' => 'length');
		if (!empty($def['rx'])) {
			$ok = (bool) @preg_match('/' . str_replace('/', '\/', $def['rx']) . '/u', $v);
			if (!$ok && !empty($def['w'])) {
				$words = $words === null ? (isset(self::data()['floor_words']) ? self::data()['floor_words'] : array()) : $words;
				$ok = in_array(function_exists('mb_strtolower') ? mb_strtolower($v, 'UTF-8') : strtolower($v), $words, true);
			}
			if (!$ok) return array('ok' => !empty($def['opt']), 'value' => $v, 'code' => empty($def['opt']) ? 'format' : 'hint');
		}
		return array('ok' => true, 'value' => $v, 'code' => '');
	}

	/* ------------------------------------------------------------------ submit */

	/** Before the required check: a Saudi group in "short address or full address" mode with a short code sent needs no other part. */
	public static function prepare_form(array $fields, array $submitted) {
		$grps = array();
		foreach ($fields as $f) {
			if (is_array($f) && !empty($f['addr_req_mode']) && $f['addr_req_mode'] === 'sa_short_or_full' && !empty($f['cmp_grp'])) $grps[(string) $f['cmp_grp']] = true;
		}
		if (!$grps) return $fields;
		foreach (array_keys($grps) as $g) {
			$short = null;
			foreach ($fields as $f) if (is_array($f) && ($f['cmp_grp'] ?? '') === $g && ($f['addr_part'] ?? '') === 'short_code') $short = $f;
			if (!$short) continue;
			$sent = '';
			foreach ($submitted as $s) if (is_array($s) && isset($s['id_'], $s['value']) && (string) $s['id_'] === (string) $short['id_'] && is_scalar($s['value'])) $sent = (string) $s['value'];
			$def = self::row_def($short);
			if ($sent === '' || !$def || !preg_match('/^[A-Z]{4}\d{4}$/', self::normalize($def, $sent))) continue;
			foreach ($fields as $i => $f) if (is_array($f) && ($f['cmp_grp'] ?? '') === $g && (string) ($f['id_'] ?? '') !== (string) $short['id_']) $fields[$i]['required'] = 0;
		}
		return $fields;
	}

	private static function msg($key, $name, $lanText, $en) {
		$t = isset($lanText[$key]) && is_string($lanText[$key]) && $lanText[$key] !== '' ? $lanText[$key] : $en;
		// The field's name keeps its own direction inside the sentence (FSI … PDI: a Persian label in an English message).
		return str_replace('%s', '<b>' . "\u{2068}" . esc_html((string) $name) . "\u{2069}" . '</b>', $t);
	}

	/**
	 * The server check of one answer row of a part (after the type branch): its stored value and its group keys.
	 * @return array ok, item, m, code
	 */
	public static function check_row($f, array $item, array $fields, array $lanText) {
		$f = self::arr($f);
		$def = self::row_def($f);
		if (!$def) return array('ok' => true, 'item' => $item, 'm' => '', 'code' => '');
		$name = isset($f['name']) ? $f['name'] : '';
		$type = isset($f['type']) ? $f['type'] : '';
		$value = isset($item['value']) && is_scalar($item['value']) ? (string) $item['value'] : '';
		$fail = function ($code, $key, $en) use ($name, $lanText, $item) {
			return array('ok' => false, 'item' => $item, 'm' => self::msg($key, $name, $lanText, $en), 'code' => 'na_' . $code);
		};
		if ($f['cmp_kind'] === 'name') {
			$value = trim(preg_replace('/\s+/u', ' ', $value));
			$strict = isset($f['name_strict']) && (string) $f['name_strict'] === '1';
			if ($strict && $value !== '' && $type !== 'select' && !self::name_ok($value)) return $fail('name', 'nmInvalidX', '%s can only have letters, spaces, hyphens, apostrophes and dots.');
		} elseif (in_array($type, array('conturyList', 'stateProvince', 'cityList'), true)) {
			$strict = function_exists('apply_filters') ? apply_filters('efb_geo_strict_lists', true, $f) : true;
			// A list the page fills at run time (cities of the chosen province, or of the international format) cannot
			// be compared with the stored options: a name of a place, no digits or control characters, at most 100.
			$fixed = $type === 'conturyList' || ($type === 'stateProvince' && !empty($def['cc']));
			if ($value !== '' && $strict && $fixed) {
				$found = false;
				foreach ($fields as $o) {
					if (is_array($o) && ($o['type'] ?? '') === 'option' && (string) ($o['parent'] ?? '') === (string) $f['id_'] && isset($o['value']) && (string) $o['value'] === $value) { $found = true; break; }
				}
				if (!$found) return $fail('list', 'addrInvalidX', '%s is not valid for this address format.');
			} elseif ($value !== '' && (preg_match('/[\x00-\x1F\x7F\d<>]/u', $value) || (function_exists('mb_strlen') ? mb_strlen($value, 'UTF-8') : strlen($value)) > 100)) {
				return $fail('list', 'addrInvalidX', '%s is not valid for this address format.');
			}
		} else {
			$r = self::check_value($def, $value);
			if (!$r['ok']) return $def['p'] === 'postal' ? $fail($r['code'], 'addrPostalX', 'Enter a valid postal code for %s.') : $fail($r['code'], 'addrInvalidX', '%s is not valid for this address format.');
			$value = $r['value'];
		}
		$item['value'] = $value;
		$item['grp'] = (string) $f['cmp_grp'];
		$item['part'] = (string) $def['p'];
		$item['cmp'] = (string) $f['cmp_kind'];
		$item['sch'] = (string) ($f['cmp_kind'] === 'name' ? $f['name_format'] : $f['addr_scheme']);
		return array('ok' => true, 'item' => $item, 'm' => '', 'code' => '');
	}

	/**
	 * After every row passed: the group rules and the display line. A Saudi group in "short or full" mode needs a
	 * valid short code or every part the form requires; the first row of each group gets na_full.
	 * @return array ok, rows, m, field_id, code
	 */
	public static function finish_rows(array $fields, array $rows, array $lanText, $locale = '') {
		$req = array();
		foreach ($fields as $f) {
			if (!is_array($f) || empty($f['cmp_grp']) || empty($f['addr_req_mode']) || $f['addr_req_mode'] !== 'sa_short_or_full') continue;
			$req[(string) $f['cmp_grp']] = $f;
		}
		foreach ($req as $g => $first) {
			$have = array();
			foreach ($rows as $r) if (is_array($r) && ($r['grp'] ?? '') === $g && isset($r['value']) && $r['value'] !== '') $have[$r['part']] = (string) $r['value'];
			if (isset($have['short_code']) && preg_match('/^[A-Z]{4}\d{4}$/', $have['short_code'])) continue;
			foreach ($fields as $f) {
				if (!is_array($f) || ($f['cmp_grp'] ?? '') !== $g || empty($f['addr_part']) || $f['addr_part'] === 'short_code') continue;
				$d = self::row_def($f);
				if ($d && !empty($d['r']) && !isset($have[$f['addr_part']])) {
					return array('ok' => false, 'rows' => $rows, 'm' => isset($lanText['addrSaShortOrFull']) && $lanText['addrSaShortOrFull'] !== '' ? $lanText['addrSaShortOrFull'] : 'Enter the short address, or fill in the full National Address.', 'field_id' => (string) $f['id_'], 'code' => 'na_sa_short_or_full');
				}
			}
		}
		$labels = array();
		foreach ($fields as $f) if (is_array($f) && !empty($f['cmp_grp']) && isset($f['id_'], $f['name'])) $labels[(string) $f['id_']] = (string) $f['name'];
		$seen = array();
		foreach (self::groups($rows, $labels) as $g) {
			foreach ($rows as $i => $r) {
				if (is_array($r) && ($r['grp'] ?? '') === $g['grp'] && !isset($seen[$g['grp']])) {
					$seen[$g['grp']] = true;
					$line = self::group_text($g, $locale, false);
					if ($line !== '') $rows[$i]['na_full'] = $line;
					break;
				}
			}
		}
		return array('ok' => true, 'rows' => $rows, 'm' => '', 'field_id' => '', 'code' => '');
	}

	/* ------------------------------------------------------------------ display (F20 P22) */

	/** Rows of an answer → its name / address groups, in order: grp, kind, set (format or scheme), parts, labels, full (stored line). */
	public static function groups(array $rows, array $labels = array()) {
		$out = array();
		foreach ($rows as $r) {
			$r = self::arr($r);
			if (empty($r['grp']) || empty($r['part']) || empty($r['cmp']) || !is_scalar($r['grp'])) continue;
			$g = (string) $r['grp'];
			if (!isset($out[$g])) $out[$g] = array('grp' => $g, 'kind' => (string) $r['cmp'], 'set' => isset($r['sch']) ? (string) $r['sch'] : '', 'parts' => array(), 'labels' => array(), 'full' => '');
			$v = isset($r['value']) && is_scalar($r['value']) ? str_replace('@efb@dq#', '"', (string) $r['value']) : '';
			$out[$g]['parts'][(string) $r['part']] = $v;
			$id = isset($r['id_']) && is_scalar($r['id_']) ? (string) $r['id_'] : '';
			$out[$g]['labels'][(string) $r['part']] = isset($labels[$id]) ? $labels[$id] : (isset($r['name']) && is_scalar($r['name']) ? (string) $r['name'] : '');
			if (!empty($r['na_full']) && is_scalar($r['na_full'])) $out[$g]['full'] = (string) $r['na_full'];
		}
		return array_values($out);
	}

	/** The separator of a list in the locale (Persian and Arabic: "، "). */
	public static function sep($locale) {
		$l = strtolower(substr((string) $locale, 0, 2));
		return in_array($l, array('fa', 'ar', 'ur', 'ps', 'ku'), true) ? '، ' : (in_array($l, array('ja', 'zh'), true) ? '、' : ', ');
	}

	private static function locale() {
		if (function_exists('determine_locale')) return determine_locale();
		return function_exists('get_locale') ? get_locale() : 'en_US';
	}

	/** One group as text: the stored line (written at submit in the page's language) unless a locale or lines are asked for, else built from the parts. */
	public static function group_text(array $g, $locale = '', $multiline = false) {
		if ($g['kind'] === 'name') return self::name_text($g['parts'], $g['set']);
		if (!$multiline && $locale === '' && isset($g['full']) && $g['full'] !== '') return $g['full'];
		return self::address_text($g['set'], $g['parts'], $locale, $multiline, isset($g['labels']) ? $g['labels'] : array());
	}

	/** A name in its logical order (title, given, middle / father / grandfather, family), one space between. */
	public static function name_text(array $parts, $format = '') {
		$order = array('prefix', 'first', 'full', 'middle', 'father', 'grandfather', 'last', 'family');
		$f = self::name_formats();
		if ($format !== '' && isset($f[$format])) $order = array_map(function ($p) { return $p['p']; }, $f[$format]['parts']);
		$out = array();
		foreach ($order as $p) if (isset($parts[$p]) && trim((string) $parts[$p]) !== '') $out[] = trim((string) $parts[$p]);
		return implode(' ', $out);
	}

	/**
	 * An address in its format's order: lines of parts (part ids; "a+b" joined by a space, "a~b" by a dash, "#a"
	 * with its label first). Empty parts and their separators are dropped. Twin: EFBNameAddr.addressText().
	 */
	public static function address_text($scheme, array $parts, $locale = '', $multiline = false, array $labels = array()) {
		$s = self::schemes();
		$locale = $locale === '' ? self::locale() : $locale;
		$lines = isset($s[$scheme]['disp']) ? $s[$scheme]['disp'] : array(array_keys($parts));
		$sep = self::sep($locale);
		$done = array();
		$out = array();
		foreach ($lines as $line) {
			$items = array();
			foreach ($line as $tok) {
				$lab = $tok[0] === '#';
				$tok = ltrim($tok, '#');
				$pieces = array();
				foreach (preg_split('/([+~])/', $tok, -1, PREG_SPLIT_DELIM_CAPTURE) as $x) {
					if ($x === '+' || $x === '~') { $pieces[] = $x; continue; }
					$done[$x] = true;
					$v = isset($parts[$x]) ? trim((string) $parts[$x]) : '';
					$pieces[] = $v === '' ? null : $v;
				}
				$txt = '';
				$pend = '';
				foreach ($pieces as $x) {
					if ($x === '+' || $x === '~') { $pend = $x === '+' ? ' ' : '-'; continue; }
					if ($x === null) continue;
					$txt .= ($txt !== '' ? $pend : '') . $x;
					$pend = '';
				}
				if ($txt === '') continue;
				if ($lab && isset($labels[$tok]) && trim($labels[$tok]) !== '') $txt = trim($labels[$tok]) . ' ' . $txt;
				$items[] = $txt;
			}
			if ($items) $out[] = implode($sep, $items);
		}
		// Parts the format does not list (an add-on's or a renamed one) are not lost.
		foreach ($parts as $p => $v) if (!isset($done[$p]) && trim((string) $v) !== '') $out[] = trim((string) $v);
		return implode($multiline ? "\n" : $sep, $out);
	}

	/* ------------------------------------------------------------------ page */

	/** Attributes of a text part's input (name or address); '' for any other row. */
	public static function input_attrs($vj) {
		$r = self::arr($vj);
		$def = self::row_def($r);
		if (!$def) return '';
		$a = ' data-grp="' . esc_attr((string) $r['cmp_grp']) . '" data-' . ($r['cmp_kind'] === 'name' ? 'name' : 'addr') . '-part="' . esc_attr($def['p']) . '"';
		if ($r['cmp_kind'] === 'name') {
			if (isset($r['name_strict']) && (string) $r['name_strict'] === '1') $a .= ' data-na-strict="1"';
			return $a;
		}
		if (!empty($def['n'])) $a .= ' inputmode="numeric" dir="ltr"';
		elseif (!empty($def['ltr'])) $a .= ' dir="ltr"';
		if ($def['p'] === 'short_code') $a .= ' autocapitalize="characters"';
		if (empty($def['n']) && empty($def['ltr'])) $a .= ' dir="auto"';
		if (!empty($def['rx'])) $a .= ' data-na-rx="' . esc_attr($def['rx']) . '"';
		foreach (array('nm', 'fmt', 'mn', 'mx') as $k) if (isset($def[$k])) $a .= ' data-na-' . $k . '="' . esc_attr((string) $def[$k]) . '"';
		if (!empty($def['n'])) $a .= ' data-na-n="1"';
		if (!empty($def['w'])) $a .= ' data-na-w="1"';
		if (!empty($def['opt'])) $a .= ' data-na-opt="1"';
		if (!empty($r['addr_req_mode'])) $a .= ' data-na-mode="' . esc_attr((string) $r['addr_req_mode']) . '"';
		return $a;
	}

	/** Attributes of a list part (country, state, city; the title of a name with its autocomplete token). */
	public static function list_attrs($vj) {
		$r = self::arr($vj);
		$def = self::row_def($r);
		if (!$def) return '';
		if ($r['cmp_kind'] === 'name') return ' data-grp="' . esc_attr((string) $r['cmp_grp']) . '" data-name-part="' . esc_attr($def['p']) . '"' . (empty($r['autocomplete']) && !empty($def['ac']) ? ' autocomplete="' . esc_attr($def['ac']) . '"' : (!empty($r['autocomplete']) && class_exists('\\Emsfb\\A11y') ? ' autocomplete="' . esc_attr(A11y::token($r['autocomplete'])) . '"' : ''));
		$a = ' data-grp="' . esc_attr((string) $r['cmp_grp']) . '" data-addr-part="' . esc_attr($def['p']) . '"';
		if (($r['type'] ?? '') === 'cityList' && isset($r['addr_cascade']) && (string) $r['addr_cascade'] === '1') $a .= ' data-cascade="1"';
		return $a;
	}

	/** The autocomplete token of a part (F16 §5.1 table extended by the part; '' = not a part). */
	public static function autocomplete($vj) {
		$def = self::row_def($vj);
		return $def && !empty($def['ac']) ? (string) $def['ac'] : '';
	}

	/**
	 * Where the page wraps a group in <fieldset><legend> (a11y: one name for the parts): runs of at least two parts of
	 * one group next to each other in one step (options and repeater children are not in the way; a part moved
	 * elsewhere ends the run). @return array{0: array<int,string>, 1: array<int,bool>} open (index => grp), close (index).
	 */
	public static function fieldset_runs(array $rows, array $skip = array()) {
		$seq = array();
		foreach ($rows as $i => $r) {
			$r = self::arr($r);
			if ($i < 1 || !isset($r['type']) || in_array($r['type'], array('option', 'r_matrix'), true)) continue;
			if (isset($r['id_']) && isset($skip[(string) $r['id_']])) continue;
			$seq[] = array($i, (!empty($r['cmp_grp']) && !empty($r['cmp_kind']) && $r['type'] !== 'step') ? (string) $r['cmp_grp'] . '|' . (isset($r['step']) ? (string) $r['step'] : '') : '');
		}
		$open = array(); $close = array();
		$n = count($seq);
		for ($k = 0; $k < $n; $k++) {
			if ($seq[$k][1] === '') continue;
			$e = $k;
			while ($e + 1 < $n && $seq[$e + 1][1] === $seq[$k][1]) $e++;
			if ($e > $k) { $open[$seq[$k][0]] = explode('|', $seq[$k][1])[0]; $close[$seq[$e][0]] = true; }
			$k = $e;
		}
		return array($open, $close);
	}

	/** The fieldset that holds a group's parts (its legend is for screen readers; the parts keep their own labels). */
	public static function fieldset_open($grp, $kind, array $texts) {
		$legend = $kind === 'name' ? (isset($texts['name']) && $texts['name'] !== '' ? $texts['name'] : 'Name') : (isset($texts['address']) && $texts['address'] !== '' ? $texts['address'] : 'Address');
		return '<fieldset class="efb efb-cmp col-12 row mx-0 px-0" data-cmp="' . esc_attr($grp) . '" data-cmp-kind="' . esc_attr($kind) . '" style="min-width:0;border:0;margin:0;padding:0">'
			. '<legend class="efb efb-vh">' . esc_html($legend) . '</legend>';
	}

	/** Whether a form (rows) has name or address groups. */
	public static function form_has_groups($rows) {
		foreach ((array) $rows as $i => $r) { $r = self::arr($r); if ($i > 0 && !empty($r['cmp_grp']) && !empty($r['cmp_kind'])) return true; }
		return false;
	}

	/** What a page with these rows needs: the display lines and name orders of the formats it uses, the floor words. */
	public static function runtime_data($rows) {
		$sch = array(); $fmt = array();
		foreach ((array) $rows as $r) {
			$r = self::arr($r);
			if (!empty($r['addr_scheme']) && isset(self::schemes()[$r['addr_scheme']])) $sch[$r['addr_scheme']] = array('disp' => self::schemes()[$r['addr_scheme']]['disp']);
			if (!empty($r['name_format']) && isset(self::name_formats()[$r['name_format']])) $fmt[$r['name_format']] = array_map(function ($p) { return $p['p']; }, self::name_formats()[$r['name_format']]['parts']);
		}
		$d = self::data();
		return array('v' => self::VERSION, 'schemes' => $sch, 'names' => $fmt, 'words' => isset($d['floor_words']) ? $d['floor_words'] : array());
	}

	/** Everything (admin screens, the portal): all formats' display data. */
	public static function display_data() {
		$sch = array(); $fmt = array();
		foreach (self::schemes() as $k => $s) $sch[$k] = array('disp' => $s['disp']);
		foreach (self::name_formats() as $k => $f) $fmt[$k] = array_map(function ($p) { return $p['p']; }, $f['parts']);
		$d = self::data();
		return array('v' => self::VERSION, 'schemes' => $sch, 'names' => $fmt, 'words' => isset($d['floor_words']) ? $d['floor_words'] : array());
	}

	/* ------------------------------------------------------------------ builder, AutoPilot, SitePilot */

	/** Whether the Address & Postal add-on (AdnADR) is active (its search, postcode lookup and postal masks). */
	public static function adr_active() {
		return class_exists('\\Emsfb\\Addon_Keys') ? Addon_Keys::active('AdnADR') && class_exists('\\Emsfb\\Address_Efb') : class_exists('\\Emsfb\\Address_Efb');
	}

	/** The composite of each format (parts in order), for efb_api_field_types() and the registry. */
	public static function composites() {
		$out = array('name' => array(), 'address' => array());
		foreach (self::name_formats() as $k => $f) $out['name'][$k] = array_map(function ($p) { return array('part' => $p['p'], 'type' => $p['t'], 'elementId' => $p['el'], 'required' => (int) $p['r'], 'autocomplete' => $p['ac']); }, $f['parts']);
		foreach (self::schemes() as $k => $s) $out['address'][$k] = array_map(function ($p) { return array('part' => $p['p'], 'type' => $p['t'], 'elementId' => $p['el'], 'required' => (int) $p['r'], 'autocomplete' => $p['ac'], 'country' => isset($p['cc']) ? $p['cc'] : null, 'cascade' => !empty($p['cas'])); }, $s['parts']);
		return $out;
	}

	public static function capabilities($out, $args = array()) {
		if (!is_array($out) || !isset($out['features']) || !is_array($out['features'])) return $out;
		$adr = self::adr_active();
		$e = function ($keys, $label, $pkg = 'free', $addon = null, $now = true, $reason = null) {
			return array('kind' => 'setting', 'scope' => 'field', 'package' => $pkg, 'addon' => $addon, 'available_now' => $now, 'reason' => $reason,
				'prompt_state' => 0, 'message_key' => null, 'form_keys' => $keys, 'label_key' => $label);
		};
		$out['features']['setting:name_format'] = $e(array('cmp_grp', 'cmp_kind=name', 'name_format', 'name_part', 'name_strict'), 'nmFmtPick');
		$out['features']['setting:addr_scheme'] = $e(array('cmp_grp', 'cmp_kind=address', 'addr_scheme', 'addr_part', 'addr_cascade', 'addr_req_mode'), 'addrSchPick', 'free_plus');
		$prev = isset($out['features']['setting:addr_lookup']) && is_array($out['features']['setting:addr_lookup']) ? $out['features']['setting:addr_lookup'] : array();
		$out['features']['setting:addr_lookup'] = array_merge($e(array('addr_lookup'), 'addrLookup', 'free_plus', 'AdnADR', $adr, $adr ? null : 'addon'), array_intersect_key($prev, array('available_now' => 1, 'reason' => 1, 'prompt_state' => 1, 'message_key' => 1)));
		$out['name_address'] = array(
			'v' => self::VERSION,
			'field_keys' => self::KEYS,
			'name_formats' => array_keys(self::name_formats()),
			'address_schemes' => array_keys(self::schemes()),
			'addr_parts' => self::addr_parts(),
			'name_parts' => self::NAME_PARTS,
			'req_modes' => array('sa_short_or_full'),
			'composites' => self::composites(),
			'needs_addon' => array('addr_lookup' => 'AdnADR', 'mask_preset (postal)' => 'AdnADR'),
			'adr_active' => $adr,
			'builder' => 'efbBuilderApi.newItems("name"|"address", {format|scheme}) / efbBuilderApi.setNameAddress(id, keys)',
			'display' => 'efb_name_display() / efb_address_display() (PHP), window.EFBNameAddr (JS), version 1',
		);
		return $out;
	}

	/** forms/{id}/schema: a part says its group, kind, format and part (a visitor assistant fills it by its token). */
	public static function schema_field($e, $r, $form_id = 0) {
		if (!is_array($e)) return $e;
		$r = self::arr($r);
		$def = self::row_def($r);
		if (!$def) return $e;
		$e['group'] = array('id' => (string) $r['cmp_grp'], 'kind' => (string) $r['cmp_kind'], 'format' => (string) ($r['cmp_kind'] === 'name' ? $r['name_format'] : $r['addr_scheme']), 'part' => (string) $def['p']);
		if (!empty($r['addr_lookup']) && (string) $r['addr_lookup'] === '1') $e['group']['lookup'] = self::adr_active();
		if (!empty($r['addr_cascade']) && (string) $r['addr_cascade'] === '1') $e['group']['cascade'] = 'set the state first; the city list loads after it';
		if (!empty($r['addr_req_mode'])) $e['group']['req_mode'] = (string) $r['addr_req_mode'];
		return $e;
	}

	/**
	 * efb_field_templates (B2, Builder_Api::field_templates('name' | 'address', $args)): with $args['format'] (a name)
	 * or $args['scheme'] (an address) the rows of that format, built the way the builder's chooser builds them
	 * (efbNaPatch); always the list of formats (composite). $args lookup / req_mode as in efbBuilderApi.newItems().
	 * A state list of a fixed country is empty here (the lists are on the CDN): needs_states names those rows.
	 */
	public static function field_templates($out, $palette_id, $args = array()) {
		if (($palette_id !== 'name' && $palette_id !== 'address') || !is_array($out) || !class_exists('\\Emsfb\\Builder_Api')) return $out;
		$out['composite'] = self::composites()[$palette_id];
		$fmt = (string) ($palette_id === 'name' ? ($args['format'] ?? '') : ($args['scheme'] ?? ($args['format'] ?? '')));
		if ($fmt === '') return $out;
		$set = $palette_id === 'name' ? self::name_formats() : self::schemes();
		if (!isset($set[$fmt])) return class_exists('\WP_Error') ? new \WP_Error('invalid_setting', 'Unknown ' . ($palette_id === 'name' ? 'name format' : 'address scheme') . ' ' . $fmt . '.') : $out;
		if (!empty($args['lookup']) && !self::adr_active()) return class_exists('\WP_Error') ? new \WP_Error('addon_required', 'Address search needs the Address & Postal add-on (AdnADR).') : $out;
		$text = isset($args['text']) && is_array($args['text']) ? $args['text'] : array();
		$rows = array(); $grp = ''; $needs = array();
		$d = self::data();
		$loc = substr(self::locale(), 0, 2);
		$prefixes = isset($d['prefixes'][$loc]) ? $d['prefixes'][$loc] : (isset($d['prefixes']['en']) ? $d['prefixes']['en'] : array());
		$a = $args; unset($a['format'], $a['scheme'], $a['lookup'], $a['req_mode'], $a['label'], $a['options']);
		foreach ($set[$fmt]['parts'] as $i => $p) {
			$one = Builder_Api::field_templates($p['el'], $a);
			if (!is_array($one) || empty($one['rows'])) continue;
			$field = $one['rows'][0];
			$opts = array_slice($one['rows'], 1);
			if ($grp === '') $grp = $field['id_'];
			$field['name'] = isset($text[$p['lb']]) ? $text[$p['lb']] : $p['lb'];
			$field['required'] = $p['r'] ? 1 : 0;
			$field['size'] = (string) $p['sz'];
			$field['mobile_size'] = '100';
			$field['cmp_grp'] = $grp;
			$field['cmp_kind'] = $palette_id;
			if ($palette_id === 'name') {
				$field['name_format'] = $fmt; $field['name_part'] = $p['p'];
				$field['placeholder'] = $field['name'];
				if ($p['t'] === 'text') $field['name_strict'] = '1';
				if ($p['p'] === 'prefix') {
					$opts = array();
					foreach ($prefixes as $v) { $oid = self::rid(); $opts[] = array('id_' => $oid, 'dataId' => $oid . '-id', 'parent' => $field['id_'], 'type' => 'option', 'value' => $v, 'id_op' => $oid, 'step' => isset($field['step']) ? $field['step'] : 1, 'amount' => isset($field['amount']) ? $field['amount'] : 1); }
				}
			} else {
				$field['addr_scheme'] = $fmt; $field['addr_part'] = $p['p'];
				if ($p['t'] === 'address_line' || $p['t'] === 'postalcode') { $field['placeholder'] = ''; if (!empty($p['mx'])) $field['mlen'] = (string) $p['mx']; }
				if (!empty($p['cas'])) { $field['addr_cascade'] = '1'; $field['statePov'] = ''; $opts = array(); if (!empty($set[$fmt]['cc'])) $field['country'] = strtoupper($set[$fmt]['cc']); }
				if (!empty($p['cc'])) { $field['country'] = strtoupper($p['cc']); $field['statePov'] = ''; $opts = array(); $needs[] = $field['id_']; }
				if ($p['t'] === 'cityList' && empty($p['cas'])) $field['addr_cascade'] = '0';
				if (!empty($p['mask']) && self::adr_active()) $field['mask_preset'] = $p['mask'];
				if ($i === 0 && !empty($args['lookup'])) $field['addr_lookup'] = '1';
				if ($i === 0 && $fmt === 'sa' && ($args['req_mode'] ?? '') === 'sa_short_or_full') $field['addr_req_mode'] = 'sa_short_or_full';
			}
			$rows[] = $field;
			foreach ($opts as $o) $rows[] = $o;
		}
		$out['rows'] = $rows;
		$out['format'] = $fmt;
		if ($needs) $out['needs_states'] = $needs;
		return $out;
	}

	private static function rid() {
		$c = 'abcdefghijklmnopqrstuvwxyz0123456789'; $id = '';
		for ($i = 0; $i < 9; $i++) $id .= $c[function_exists('random_int') ? random_int(0, 35) : mt_rand(0, 35)];
		return $id;
	}

	/** The builder gets the definition (labels are phrase keys of efb_var.text) and whether AdnADR is active. */
	public static function localize_vars($vars, $context = '') {
		if (!is_array($vars)) return $vars;
		$d = self::data();
		$vars['name_address'] = array('v' => self::VERSION, 'name_formats' => self::name_formats(), 'schemes' => self::schemes(), 'prefixes' => isset($d['prefixes']) ? $d['prefixes'] : array(),
			'defaults' => isset($d['locale_defaults']) ? $d['locale_defaults'] : array(), 'display' => self::display_data(), 'adr' => self::adr_active(),
			'user_defaults' => function_exists('get_user_meta') && function_exists('get_current_user_id') ? array('name' => (string) get_user_meta(get_current_user_id(), 'efb_default_name_format', true), 'address' => (string) get_user_meta(get_current_user_id(), 'efb_default_addr_scheme', true)) : array());
		return $vars;
	}
}
