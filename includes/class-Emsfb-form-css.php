<?php
/**
 * F11-G: custom CSS of one form, scoped to that form (docs/feature-gaps/F11-form-limits-resume.fa.md §7).
 *
 * Stored in valj_efb[0].custom_css as "b64:" + base64(UTF-8): CSS needs <, > and quotes,
 * which the builder's gate and sanitize_text_field() would remove (playbook §1.6).
 *
 * sanitize() keeps only rules that cannot leave the form's box or reach outside the page:
 *  - comments, @import, @charset, @namespace, @page and any unknown at-rule are dropped;
 *  - a rule whose text (after decoding CSS escapes such as \3c or \000075) holds <,
 *    expression(, -moz-binding, behavior:, javascript: or vbscript:, or a url() that is not
 *    https:, relative or data:image/(png|gif|jpeg|webp) is dropped whole;
 *  - @media, @supports, @container and @layer blocks are cleaned the same way inside.
 * scoped() runs sanitize() again (defence in depth) and prefixes every selector with
 * #body_efb_{id}; :root, html and body become that wrapper. @font-face and @keyframes
 * are kept as they are (they name things, they select nothing).
 *
 * The browser twin is efb_css_sanitize_efb() / efb_css_scope_efb() in
 * includes/admin/assets/js/form-limits-efb.js (builder preview); tests/test-form-css-parity.js
 * runs both on the same vectors. Without < there is no way to close the <style> element.
 */

namespace Emsfb;

if (!defined('ABSPATH')) {
	exit;
}

class Form_Css {

	/** Size cap of the CSS (bytes of UTF-8). */
	const MAX = 20480;

	const GROUP_RULES = array('media', 'supports', 'container', 'layer');
	const RAW_RULES = array('font-face', 'keyframes', '-webkit-keyframes');

	/** "b64:…" as stored → the CSS text, '' when it is not a valid value. */
	public static function decode($stored) {
		if (!is_string($stored) || strpos($stored, 'b64:') !== 0) return '';
		$raw = base64_decode(substr($stored, 4), true);
		if ($raw === false || strlen($raw) > self::MAX || !preg_match('//u', $raw)) return '';
		return $raw;
	}

	public static function encode($css) {
		return $css === '' ? '' : 'b64:' . base64_encode($css);
	}

	/** sanitize_obj_msg_efb() case: decode, sanitize, encode again ('' when nothing is left). */
	public static function sanitize_stored($v) {
		$css = self::decode($v);
		return $css === '' ? '' : self::encode(self::sanitize($css));
	}

	/** The style rules printed with the form ('' for a form without custom CSS). */
	public static function scoped($stored, $form_id) {
		$css = self::decode($stored);
		if ($css === '') return '';
		$out = self::scope(self::sanitize($css), '#body_efb_' . (int) $form_id);
		// Never a way out of the <style> element, whatever happened above.
		return $out === '' ? '' : "\n" . str_replace('<', '', $out) . "\n";
	}

	/* ------------------------------------------------------------ cleaning */

	public static function sanitize($css) {
		if (!is_string($css) || $css === '' || strlen($css) > self::MAX) return '';
		$css = str_replace("\0", '', $css);
		return self::serialize(self::clean_list(self::parse(self::strip_comments($css))), null);
	}

	/** Scope a sanitized text (used by scoped() and the tests). */
	public static function scope($css, $scope) {
		return self::serialize(self::clean_list(self::parse($css)), $scope);
	}

	/** End of a quoted string that starts at $i (index after the closing quote). */
	private static function skip_string($s, $i) {
		$q = $s[$i];
		$n = strlen($s);
		$i++;
		while ($i < $n) {
			$c = $s[$i];
			if ($c === '\\') { $i += 2; continue; }
			if ($c === $q) return $i + 1;
			$i++;
		}
		return $n;
	}

	public static function strip_comments($s) {
		$out = '';
		$n = strlen($s);
		$i = 0;
		while ($i < $n) {
			$c = $s[$i];
			if ($c === '"' || $c === "'") { $j = self::skip_string($s, $i); $out .= substr($s, $i, $j - $i); $i = $j; continue; }
			if ($c === '\\') { $out .= substr($s, $i, 2); $i += 2; continue; }
			if ($c === '/' && $i + 1 < $n && $s[$i + 1] === '*') {
				$e = strpos($s, '*/', $i + 2);
				$i = $e === false ? $n : $e + 2;
				continue;
			}
			$out .= $c;
			$i++;
		}
		return $out;
	}

	/** Top level of a style sheet: list of [prelude, body|null]. Stray } and an unclosed tail are dropped. */
	public static function parse($s) {
		$rules = array();
		$n = strlen($s);
		$i = 0;
		$start = 0;
		while ($i < $n) {
			$c = $s[$i];
			if ($c === '"' || $c === "'") { $i = self::skip_string($s, $i); continue; }
			if ($c === '\\') { $i += 2; continue; }
			if ($c === ';') { $rules[] = array(substr($s, $start, $i - $start), null); $i++; $start = $i; continue; }
			if ($c === '}') { $i++; $start = $i; continue; }
			if ($c === '{') {
				$depth = 1;
				$j = $i + 1;
				while ($j < $n && $depth > 0) {
					$d = $s[$j];
					if ($d === '"' || $d === "'") { $j = self::skip_string($s, $j); continue; }
					if ($d === '\\') { $j += 2; continue; }
					if ($d === '{') $depth++;
					elseif ($d === '}') $depth--;
					$j++;
				}
				if ($depth > 0) break; // unclosed block: dropped
				$rules[] = array(substr($s, $start, $i - $start), substr($s, $i + 1, $j - $i - 2));
				$i = $j;
				$start = $i;
				continue;
			}
			$i++;
		}
		return $rules;
	}

	/** ASCII whitespace of CSS (the JS twin trims the same set, never Unicode spaces). */
	const WS = " \t\n\r\0\x0B\f";

	private static function lower($s) {
		return strtr($s, 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz');
	}

	/** CSS escapes decoded (\3c → <, \000075 → u, \: → :); only for the checks. */
	public static function decode_escapes($s) {
		$s = preg_replace_callback('/\\\\([0-9a-fA-F]{1,6})[ \t\n\r\f]?/', function ($m) {
			$cp = hexdec($m[1]);
			if ($cp === 0 || $cp > 0x10FFFF || ($cp >= 0xD800 && $cp <= 0xDFFF)) $cp = 0xFFFD;
			return function_exists('mb_chr') ? mb_chr($cp, 'UTF-8') : html_entity_decode('&#' . $cp . ';', ENT_QUOTES, 'UTF-8');
		}, $s);
		$s = preg_replace('/\\\\([^\n])/s', '$1', $s);
		return str_replace('\\', '', $s);
	}

	/** Whether a URL inside url() may load: https:, relative, or a data: image. */
	public static function url_ok($u) {
		$l = self::lower(trim($u, self::WS));
		if ($l === '') return true;
		if (strpos($l, 'https:') === 0) return true;
		if (strpos($l, 'data:') === 0) return (bool) preg_match('#^data:image/(png|gif|jpeg|webp)[;,]#', $l);
		if (strpos($l, '//') === 0) return false;
		return !preg_match('/^[a-z][a-z0-9+.\-]*:/', $l);
	}

	/** The rule must go: see the class comment. */
	public static function dangerous($text) {
		$d = self::decode_escapes($text);
		$l = self::lower($d);
		$c = preg_replace('/[ \t\n\r\f\x0B]+/', '', $l);
		if (strpos($d, '<') !== false) return true;
		foreach (array('@import', '@charset', '@namespace', 'expression(', '-moz-binding', 'behavior:', 'behaviour:', 'javascript:', 'vbscript:') as $bad) {
			if (strpos($c, $bad) !== false) return true;
		}
		// url( … ): the argument, quoted or not.
		$off = 0;
		while (($p = strpos($l, 'url(', $off)) !== false) {
			$off = $p + 4;
			$k = $off;
			$n = strlen($d);
			while ($k < $n && strpos(" \t\r\n\f", $d[$k]) !== false) $k++;
			if ($k < $n && ($d[$k] === '"' || $d[$k] === "'")) {
				$q = $d[$k];
				$e = strpos($d, $q, $k + 1);
				$arg = $e === false ? substr($d, $k + 1) : substr($d, $k + 1, $e - $k - 1);
			} else {
				$e = strpos($d, ')', $k);
				$arg = $e === false ? substr($d, $k) : substr($d, $k, $e - $k);
			}
			if (!self::url_ok($arg)) return true;
		}
		// image-set( / src( take strings as URLs: every quoted string up to the closing parenthesis.
		foreach (array('image-set(', 'src(') as $fn) {
			$off = 0;
			while (($p = strpos($l, $fn, $off)) !== false) {
				$off = $p + strlen($fn);
				$e = strpos($d, ')', $off);
				$args = $e === false ? substr($d, $off) : substr($d, $off, $e - $off);
				if (preg_match_all('/"([^"]*)"|\'([^\']*)\'/', $args, $m, PREG_SET_ORDER)) {
					foreach ($m as $one) {
						if (!self::url_ok(isset($one[2]) && $one[2] !== '' ? $one[2] : $one[1])) return true;
					}
				}
			}
		}
		return false;
	}

	/** Cleaned nodes: ['rule', selector, body] | ['group', prelude, children] | ['raw', prelude, body] | ['stmt', text]. */
	private static function clean_list(array $rules) {
		$out = array();
		foreach ($rules as $r) {
			$pre = trim($r[0], self::WS);
			$body = $r[1];
			if ($pre === '') continue;
			if ($pre[0] === '@') {
				if (!preg_match('/^@([-a-zA-Z0-9_]+)/', $pre, $m)) continue;
				$name = self::lower($m[1]);
				if ($body === null) {
					if ($name === 'layer' && !self::dangerous($pre) && preg_match('/^@layer\s+[-\w\s,.]+$/i', $pre)) $out[] = array('stmt', $pre . ';');
					continue;
				}
				if (in_array($name, self::GROUP_RULES, true)) {
					if (self::dangerous($pre)) continue;
					$kids = self::clean_list(self::parse($body));
					if ($kids) $out[] = array('group', $pre, $kids);
					continue;
				}
				if (in_array($name, self::RAW_RULES, true)) {
					if (!self::dangerous($pre . '{' . $body . '}')) $out[] = array('raw', $pre, trim($body, self::WS));
					continue;
				}
				continue;
			}
			if ($body === null) continue;
			if (self::dangerous($pre . '{' . $body . '}')) continue;
			$out[] = array('rule', $pre, trim($body, self::WS));
		}
		return $out;
	}

	private static function serialize(array $nodes, $scope) {
		$parts = array();
		foreach ($nodes as $n) {
			switch ($n[0]) {
				case 'stmt':
					$parts[] = $n[1];
					break;
				case 'raw':
					$parts[] = $n[1] . '{' . $n[2] . '}';
					break;
				case 'group':
					$parts[] = $n[1] . "{\n" . self::serialize($n[2], $scope) . "\n}";
					break;
				default:
					$sel = $scope === null ? $n[1] : self::scope_selectors($n[1], $scope);
					if ($sel !== '') $parts[] = $sel . '{' . $n[2] . '}';
			}
		}
		return implode("\n", $parts);
	}

	/** "a, b" split on top-level commas (not inside (), [] or strings). */
	public static function split_selectors($s) {
		$out = array();
		$depth = 0;
		$cur = '';
		$n = strlen($s);
		for ($i = 0; $i < $n; $i++) {
			$c = $s[$i];
			if ($c === '"' || $c === "'") { $j = self::skip_string($s, $i); $cur .= substr($s, $i, $j - $i); $i = $j - 1; continue; }
			if ($c === '\\') { $cur .= substr($s, $i, 2); $i++; continue; }
			if ($c === '(' || $c === '[') $depth++;
			elseif (($c === ')' || $c === ']') && $depth > 0) $depth--;
			if ($c === ',' && $depth === 0) { $out[] = $cur; $cur = ''; continue; }
			$cur .= $c;
		}
		$out[] = $cur;
		return $out;
	}

	public static function scope_selectors($list, $scope) {
		$out = array();
		foreach (self::split_selectors($list) as $one) {
			$x = self::scope_one($one, $scope);
			if ($x !== '') $out[] = $x;
		}
		return implode(',', $out);
	}

	/** One selector: :root / html / body at the start become the wrapper; anything else goes inside it. */
	public static function scope_one($sel, $scope) {
		$s = trim(preg_replace('/[ \t\n\r\f\x0B]+/', ' ', $sel), self::WS);
		if ($s === '') return '';
		$len = strlen($scope);
		if (strncmp($s, $scope, $len) === 0 && ($len === strlen($s) || !preg_match('/[-_a-zA-Z0-9]/', $s[$len]))) return $s;
		$root = false;
		$sep = ' ';
		while (preg_match('/^(?::root|html|body)(?![-_a-zA-Z0-9(])/i', $s, $m)) {
			$root = true;
			$s = substr($s, strlen($m[0]));
			if ($s !== '' && preg_match('/^\s*>?\s*/', $s, $w) && $w[0] !== '') {
				$s = substr($s, strlen($w[0]));
				$sep = ' ';
			} else {
				$sep = '';
				break;
			}
		}
		if ($root) return $s === '' ? $scope : $scope . $sep . $s;
		return $scope . ' ' . $s;
	}
}
