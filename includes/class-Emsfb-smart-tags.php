<?php
/**
 * F08: smart tags, hidden fields, answer piping and URL prefill
 * (docs/feature-gaps/F08-hidden-smart-tags.fa.md).
 *
 * One syntax everywhere: {ns:arg|fallback} ({get:utm_source|direct}, {user:email}, {date:Y-m-d},
 * {field:k3x9a2b1c}, {field:k3x9a2b1c.value}); the old {field_id} token of notification subjects
 * and redirect addresses still works. The browser twin is public/assets/js/smart-tags-efb.js
 * (window.EFBSmart): parse, allowed, classify, clean_template and resolve give the same answer for
 * every vector of docs/feature-gaps/reference/smart-tag-vectors.json (tests/test-smart-tags-parity.js).
 *
 * Trust: nothing that depends on the visitor is ever written into the rendered HTML (a cached page
 * would show it to the next visitor). The server resolves form / site / post in the markup; the
 * browser resolves get / cookie / ref / page / date / field for display; user:* reaches the browser
 * only in the no-store answer of forms/session/prepare; ip and uid exist on the server only. On
 * submit the server rebuilds every hidden value it can (Smart_Tags::hidden_value()) and ignores the
 * browser's copy; e-mails, confirmation messages and redirect addresses are resolved on the server
 * from the validated answers and the request, never from what the browser says a tag was.
 *
 * No tag reads a secret: there is no namespace for options, keys, other users, payment data, F03
 * patterns or F02 codes; sign-in and session cookies are refused by name.
 */

namespace Emsfb;

if (!defined('ABSPATH')) {
	exit;
}

class Smart_Tags {

	/** Known namespaces (geo is phase 2: known, so it is not text, but it resolves to its fallback). */
	const NS = array('get', 'cookie', 'ref', 'page', 'post', 'user', 'date', 'ip', 'uid', 'field', 'form', 'site', 'entry', 'geo');

	/** Longest value a tag gives, and the longest hidden-field template. */
	const MAX = 500;

	/** Arguments each namespace accepts (null = free argument checked by its own rule). */
	const ARGS = array(
		'page'  => array('url', 'path', 'title', 'id'),
		'post'  => array('id', 'title', 'url', 'type'),
		'user'  => array('id', 'login', 'email', 'display_name', 'first_name', 'last_name', 'role'),
		'form'  => array('id', 'name'),
		'site'  => array('name', 'url'),
		'entry' => array('track'),
		'geo'   => array('country', 'country_name'),
	);

	/** Tags the visitor controls (the server rebuilds them from the request, a posted copy is a fallback only). */
	const VISITOR = array('get', 'cookie', 'ref', 'page:url', 'page:path');

	/** Tags that are the same for every visitor of one page: resolved in the rendered HTML. */
	const PUBLIC_NS = array('form', 'site', 'post');

	/** Field types a URL parameter may fill (spec §4.5). */
	const PREFILL_TYPES = array(
		'text', 'firstName', 'lastName', 'email', 'tel', 'mobile', 'url', 'number', 'textarea', 'date', 'select', 'radio', 'checkbox',
		'multiselect', 'imgRadio', 'conturyList', 'stateProvince', 'cityList', 'hiddenField', 'range', 'rating', 'pointr5', 'pointr10', 'yesNo',
		'address_line', 'postalcode',
	);

	/** Field types whose default value may hold tags (spec §4.2 first row). */
	const DEFAULT_TYPES = array('text', 'firstName', 'lastName', 'email', 'tel', 'url', 'number', 'textarea', 'date', 'address_line', 'postalcode');

	/** Cookie names no tag may read (WordPress sign-in and session, PHP session, this plugin's own). */
	const COOKIE_DENY = '/^(wordpress|wp-|wp_|comment_|phpsessid|efb|emsfb|woocommerce_|wc_)/i';

	/**
	 * Address parameters a prefill key may not use: WordPress' public query variables (?name=x opens the
	 * post "x" or a 404, ?p= / ?s= change the page) and this plugin's own (track, sc, user, efb_resume).
	 */
	const RESERVED_KEYS = array('m', 'p', 'posts', 'w', 'cat', 'withcomments', 'withoutcomments', 's', 'search', 'exact', 'sentence', 'calendar', 'page', 'paged', 'more', 'tb', 'pb', 'author', 'order', 'orderby', 'year', 'monthnum', 'day', 'hour', 'minute', 'second', 'name', 'category_name', 'tag', 'feed', 'author_name', 'pagename', 'page_id', 'error', 'attachment', 'attachment_id', 'subpost', 'subpost_id', 'preview', 'robots', 'favicon', 'taxonomy', 'term', 'cpage', 'post_type', 'embed', 'sitemap', 'sitemap-subtype', 'sitemap-stylesheet', 'rest_route', 'track', 'sc', 'user', 'efb_resume');

	/** Field keys of F08 (SitePilot contract keys_hidden). st_uses is computed on save. */
	const FIELD_KEYS = array('value', 'hdn_visitor_mail', 'prefill_key', 'prefill_lock');

	/** Which tags each place accepts (spec §4.2). A tag not allowed here gives its fallback. */
	public static function allowed($where, $ns, $arg = '') {
		$ns = (string) $ns;
		$arg = (string) $arg;
		$full = $arg === '' ? $ns : $ns . ':' . $arg;
		switch ((string) $where) {
			case 'default':
				return in_array($ns, array('get', 'cookie', 'page', 'post', 'user', 'date', 'form', 'site'), true);
			case 'hidden':
				return $ns !== 'entry';
			case 'placeholder':
				return in_array($ns, array('get', 'page', 'post', 'date', 'form', 'site'), true) || in_array($full, array('user:display_name', 'user:first_name'), true);
			case 'label':
				return in_array($ns, array('field', 'get', 'date', 'form', 'site'), true) || in_array($full, array('user:display_name', 'user:first_name', 'page:title', 'post:title'), true);
			case 'message':
				return !in_array($ns, array('ip', 'cookie', 'uid'), true) && $full !== 'user:email';
			case 'email_admin':
				return true;
			case 'email_visitor':
				return !in_array($ns, array('ip', 'cookie'), true);
			case 'redirect':
				return !in_array($ns, array('ip', 'cookie'), true) && $full !== 'user:email';
		}
		return false;
	}

	/* ------------------------------------------------------------------ parse */

	/**
	 * The pieces of a text: ['t' => 'text', 'v' => '…'] and ['t' => 'tag', 'ns', 'arg', 'fb', 'hasfb', 'raw', 'legacy'].
	 * A tag is {ns[:arg][|fallback]} with a known namespace, or {id} ([A-Za-z0-9_-]) which is a legacy field
	 * token when the form has that field. Everything else ({مهم}, {note: x}, an unclosed brace) is text.
	 */
	public static function parse($text) {
		$text = is_scalar($text) ? (string) $text : '';
		$out = array();
		if ($text === '' || strpos($text, '{') === false) {
			if ($text !== '') $out[] = array('t' => 'text', 'v' => $text);
			return $out;
		}
		$pos = 0;
		if (preg_match_all('/\{([^{}]{1,200})\}/u', $text, $m, PREG_OFFSET_CAPTURE)) {
			foreach ($m[0] as $i => $whole) {
				$tag = self::interpret($m[1][$i][0]);
				if ($tag === null) continue;
				$at = $whole[1];
				if ($at > $pos) $out[] = array('t' => 'text', 'v' => substr($text, $pos, $at - $pos));
				$tag['raw'] = $whole[0];
				$out[] = $tag;
				$pos = $at + strlen($whole[0]);
			}
		}
		if ($pos < strlen($text)) $out[] = array('t' => 'text', 'v' => substr($text, $pos));
		// Neighbouring text pieces (a brace pair that was not a tag) are one piece.
		$merged = array();
		foreach ($out as $p) {
			$n = count($merged);
			if ($p['t'] === 'text' && $n > 0 && $merged[$n - 1]['t'] === 'text') $merged[$n - 1]['v'] .= $p['v'];
			else $merged[] = $p;
		}
		return $merged;
	}

	/** The inside of one {…}, or null when it is not a tag. */
	private static function interpret($inner) {
		if (preg_match('/^([a-z]{2,6})(?::([^|]{1,64}))?(?:\|([^{}]{0,80}))?$/uD', $inner, $m) && in_array($m[1], self::NS, true)) {
			return array('t' => 'tag', 'ns' => $m[1], 'arg' => isset($m[2]) ? $m[2] : '', 'fb' => isset($m[3]) ? $m[3] : '', 'hasfb' => isset($m[3]), 'legacy' => false);
		}
		if (preg_match('/^[A-Za-z0-9_-]{1,64}$/D', $inner)) {
			return array('t' => 'tag', 'ns' => 'field', 'arg' => $inner, 'fb' => '', 'hasfb' => false, 'legacy' => true);
		}
		return null;
	}

	/** Only the tags of a text (no legacy candidates unless $legacy). */
	public static function tags($text, $legacy = false) {
		$out = array();
		foreach (self::parse($text) as $p) {
			if ($p['t'] === 'tag' && ($legacy || !$p['legacy'])) $out[] = $p;
		}
		return $out;
	}

	/** Whether the text holds at least one real (non-legacy) tag. */
	public static function has_tags($text) {
		return is_string($text) && strpos($text, '{') !== false && count(self::tags($text)) > 0;
	}

	/** "ns" or "ns:arg" of a tag, the arg's sub-key (.label / .value) removed for field. */
	public static function key_of(array $tag) {
		$arg = $tag['arg'];
		if ($tag['ns'] === 'field') $arg = preg_replace('/\.(label|value)$/D', '', $arg);
		return $arg === '' ? $tag['ns'] : $tag['ns'] . ':' . $arg;
	}

	/* ------------------------------------------------------------------ classify / clean */

	/**
	 * Who decides a hidden template on submit:
	 *  'static' - no tags: the stored text, whatever the browser sends;
	 *  'client' - visitor tags only (get, cookie, ref, page:url, page:path): the server rebuilds them from
	 *             the request and keeps the browser's cleaned copy only when the request gives nothing
	 *             (a resume link, a page that dropped its query); {ref} exists in the browser only;
	 *  'server' - anything else (user, ip, uid, date, post, form, site, page:id / title, field, or a mix):
	 *             rebuilt by the server, the browser's copy is ignored.
	 */
	public static function classify($template) {
		$tags = self::tags($template);
		if (!$tags) return 'static';
		foreach ($tags as $t) {
			$k = $t['arg'] === '' ? $t['ns'] : $t['ns'] . ':' . $t['arg'];
			if (!in_array($t['ns'], array('get', 'cookie', 'ref'), true) && !in_array($k, array('page:url', 'page:path'), true)) return 'server';
		}
		return 'client';
	}

	/**
	 * A template as it is stored: at most 500 characters, no control characters, and {ns:…} pieces with an
	 * unknown namespace removed (a typo would otherwise be shown to visitors as text). Legacy {id} stays.
	 */
	public static function clean_template($t) {
		$t = is_scalar($t) ? (string) $t : '';
		$t = self::clean_value($t);
		$t = preg_replace_callback('/\{([a-z][a-z0-9_]{0,15}):[^{}]{0,200}\}/u', function ($m) {
			return in_array($m[1], self::NS, true) ? $m[0] : '';
		}, $t);
		return trim(self::cut($t, self::MAX));
	}

	/** A value as a tag may give it: one line, no control or direction-override characters, ≤ 500. */
	public static function clean_value($v) {
		if (is_array($v)) $v = implode(', ', array_map(function ($x) { return is_scalar($x) ? (string) $x : ''; }, $v));
		$v = is_scalar($v) ? (string) $v : '';
		if ($v === '') return '';
		if (!preg_match('//u', $v)) $v = function_exists('mb_convert_encoding') ? mb_convert_encoding($v, 'UTF-8', 'UTF-8') : '';
		$v = preg_replace('/[\r\n\t]+/u', ' ', $v);
		$v = preg_replace('/[\x{0000}-\x{001F}\x{007F}-\x{009F}\x{2028}\x{2029}\x{202A}-\x{202E}\x{2066}-\x{2069}]/u', '', $v);
		return self::cut($v, self::MAX);
	}

	private static function cut($s, $n) {
		if (function_exists('mb_substr')) return mb_substr($s, 0, $n, 'UTF-8');
		return implode('', array_slice(preg_split('//u', $s, -1, PREG_SPLIT_NO_EMPTY), 0, $n));
	}

	/* ------------------------------------------------------------------ resolve */

	/**
	 * The text with every tag replaced by its value (or fallback), escaped for $mode:
	 *  text (as is), html / attr (htmlspecialchars), url_query (rawurlencode), subject (text on one line).
	 * $where limits the tags (allowed()); null = every tag. $opts['legacy_drop'] removes a {name} that is
	 * not a field (the old token rule of notification subjects and redirects); otherwise it stays as text.
	 * Static text is never escaped here except in html / attr mode, where it is escaped too unless
	 * $opts['raw_static'] (a description saved through the sanitizer).
	 */
	public static function resolve($text, array $ctx, $mode = 'text', $where = null, array $opts = array()) {
		$out = '';
		foreach (self::parse($text) as $p) {
			if ($p['t'] === 'text') {
				$out .= ($mode === 'html' || $mode === 'attr') && empty($opts['raw_static']) ? self::esc($p['v']) : $p['v'];
				continue;
			}
			if ($p['legacy'] && !self::has_field($ctx, $p['arg'])) {
				if (!empty($opts['legacy_drop'])) continue;
				$out .= ($mode === 'html' || $mode === 'attr') && empty($opts['raw_static']) ? self::esc($p['raw']) : $p['raw'];
				continue;
			}
			$v = ($where === null || self::allowed($where, $p['ns'], self::arg_base($p))) ? self::value($p, $ctx) : null;
			if ($v === null || $v === '') $v = $p['fb'];
			$v = self::clean_value($v);
			$out .= self::encode($v, $mode);
		}
		if ($mode === 'subject') $out = trim(preg_replace('/[\r\n]+/', ' ', $out));
		return $out;
	}

	/** The argument used by allowed(): field arguments without their sub-key. */
	private static function arg_base(array $p) {
		return $p['ns'] === 'field' ? '' : $p['arg'];
	}

	public static function esc($s) {
		return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
	}

	public static function encode($v, $mode) {
		switch ($mode) {
			case 'html':
			case 'attr':
				return self::esc($v);
			case 'url_query':
				return rawurlencode($v);
		}
		return $v;
	}

	private static function has_field(array $ctx, $id) {
		return isset($ctx['fields']) && is_array($ctx['fields']) && isset($ctx['fields'][(string) $id]);
	}

	/** The value of one tag in a context, or null when it is unavailable here. */
	public static function value(array $p, array $ctx) {
		$ns = $p['ns'];
		$arg = (string) $p['arg'];
		switch ($ns) {
			case 'get':
				if (!preg_match('/^[A-Za-z0-9_\-\[\]]{1,64}$/D', $arg)) return null;
				$q = isset($ctx['query']) && is_array($ctx['query']) ? $ctx['query'] : array();
				$key = preg_replace('/\[\]$/D', '', $arg);
				if (!array_key_exists($key, $q)) return null;
				$v = $q[$key];
				if (is_array($v)) $v = implode(', ', array_map(function ($x) { return is_scalar($x) ? (string) $x : ''; }, array_values($v)));
				return is_scalar($v) ? (string) $v : null;
			case 'cookie':
				if (!self::cookie_ok($arg, $ctx)) return null;
				$c = isset($ctx['cookies']) && is_array($ctx['cookies']) ? $ctx['cookies'] : array();
				return isset($c[$arg]) && is_scalar($c[$arg]) ? (string) $c[$arg] : null;
			case 'ref':
				if ($arg !== '') return null;
				$r = isset($ctx['ref']) && is_scalar($ctx['ref']) ? (string) $ctx['ref'] : '';
				return self::http_url($r) ? $r : null;
			case 'page':
			case 'post':
			case 'form':
			case 'site':
			case 'entry':
				if (!in_array($arg, self::ARGS[$ns], true)) return null;
				$o = isset($ctx[$ns]) && is_array($ctx[$ns]) ? $ctx[$ns] : array();
				if ($ns === 'page' && $arg === 'path' && !isset($o['path']) && isset($o['url'])) {
					$path = parse_url((string) $o['url'], PHP_URL_PATH);
					return is_string($path) ? $path : null;
				}
				return isset($o[$arg]) && is_scalar($o[$arg]) ? (string) $o[$arg] : null;
			case 'user':
				if (!in_array($arg, self::ARGS['user'], true)) return null;
				$u = isset($ctx['user']) && is_array($ctx['user']) ? $ctx['user'] : null;
				if (!$u || empty($u['id'])) return null;
				return isset($u[$arg]) && is_scalar($u[$arg]) ? (string) $u[$arg] : null;
			case 'date':
				if (!isset($ctx['now']) || !is_numeric($ctx['now'])) return null;
				return self::format_date($arg === '' ? 'Y-m-d' : $arg, (int) $ctx['now'], isset($ctx['tz']) ? (int) $ctx['tz'] : 0);
			case 'ip':
				if ($arg !== '') return null;
				return isset($ctx['ip']) && is_scalar($ctx['ip']) ? (string) $ctx['ip'] : null;
			case 'uid':
				$n = $arg === '' ? 12 : (preg_match('/^\d{1,2}$/D', $arg) ? (int) $arg : 0);
				if ($n < 6 || $n > 32) return null;
				if (!isset($ctx['uid'])) return null;
				if (is_callable($ctx['uid'])) return (string) call_user_func($ctx['uid'], $n);
				return substr((string) $ctx['uid'], 0, $n);
			case 'field':
				return self::field_value($arg, $ctx);
			case 'geo':
				return null;
		}
		return null;
	}

	/** {field:id}, {field:id.label}, {field:id.value}: the answer, a choice by its label or its stored value. */
	public static function field_value($arg, array $ctx) {
		$sub = 'label';
		if (preg_match('/^(.+)\.(label|value)$/D', $arg, $m)) { $arg = $m[1]; $sub = $m[2]; }
		if (!preg_match('/^[A-Za-z0-9_-]{1,64}$/D', $arg)) return null;
		$vals = isset($ctx['values']) && is_array($ctx['values']) ? $ctx['values'] : array();
		if (!array_key_exists($arg, $vals)) return null;
		$v = $vals[$arg];
		$list = is_array($v) ? array_values($v) : array($v);
		$list = array_values(array_filter(array_map(function ($x) { return is_scalar($x) ? (string) $x : ''; }, $list), function ($x) { return $x !== ''; }));
		// F05: the stored values the server wrote on the answer (ov), in the answer's order: exact even when two
		// choices share a label; an "Other" answer is its own text.
		if ($sub === 'value' && isset($ctx['ovals'][$arg]) && is_array($ctx['ovals'][$arg]) && $ctx['ovals'][$arg]) {
			return implode(', ', array_map('strval', $ctx['ovals'][$arg]));
		}
		if ($sub === 'value' && isset($ctx['fields'][$arg]['options']) && is_array($ctx['fields'][$arg]['options'])) {
			$opts = $ctx['fields'][$arg]['options'];
			$list = array_map(function ($label) use ($opts) {
				foreach ($opts as $o) {
					if (is_array($o) && isset($o['label']) && (string) $o['label'] === $label) return isset($o['value']) && (string) $o['value'] !== '' ? (string) $o['value'] : $label;
				}
				return $label;
			}, $list);
		}
		return implode(', ', $list);
	}

	/** Whether a cookie may be read by a tag. */
	public static function cookie_ok($name, array $ctx = array()) {
		$name = (string) $name;
		if (!preg_match('/^[A-Za-z0-9_\-\.]{1,64}$/D', $name) || preg_match(self::COOKIE_DENY, $name)) return false;
		if (isset($ctx['cookie_deny']) && is_array($ctx['cookie_deny']) && in_array($name, $ctx['cookie_deny'], true)) return false;
		if (function_exists('apply_filters') && apply_filters('efb_smart_cookie_denied', false, $name)) return false;
		return true;
	}

	public static function http_url($u) {
		return is_string($u) && strlen($u) <= 2000 && preg_match('#^https?://[^\x00-\x20/?\#]+[^\x00-\x20]*$#iD', $u) === 1;
	}

	/** {date:FMT}: the PHP format letters F08 accepts, on a Unix time plus a zone offset (seconds). Latin digits, English names. */
	public static function format_date($fmt, $ts, $offset = 0) {
		static $days = array('Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday');
		static $months = array('January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December');
		$t = (int) $ts + (int) $offset;
		$Y = (int) gmdate('Y', $t); $mo = (int) gmdate('n', $t); $d = (int) gmdate('j', $t);
		$H = (int) gmdate('G', $t); $i = (int) gmdate('i', $t); $s = (int) gmdate('s', $t); $w = (int) gmdate('w', $t);
		$pad = function ($n) { return str_pad((string) $n, 2, '0', STR_PAD_LEFT); };
		$h12 = $H % 12 === 0 ? 12 : $H % 12;
		$out = '';
		$chars = preg_split('//u', (string) $fmt, -1, PREG_SPLIT_NO_EMPTY);
		foreach (array_slice($chars, 0, 32) as $c) {
			switch ($c) {
				case 'Y': $out .= str_pad((string) $Y, 4, '0', STR_PAD_LEFT); break;
				case 'y': $out .= $pad($Y % 100); break;
				case 'm': $out .= $pad($mo); break;
				case 'n': $out .= $mo; break;
				case 'd': $out .= $pad($d); break;
				case 'j': $out .= $d; break;
				case 'H': $out .= $pad($H); break;
				case 'G': $out .= $H; break;
				case 'h': $out .= $pad($h12); break;
				case 'g': $out .= $h12; break;
				case 'i': $out .= $pad($i); break;
				case 's': $out .= $pad($s); break;
				case 'A': $out .= $H < 12 ? 'AM' : 'PM'; break;
				case 'a': $out .= $H < 12 ? 'am' : 'pm'; break;
				case 'D': $out .= substr($days[$w], 0, 3); break;
				case 'l': $out .= $days[$w]; break;
				case 'M': $out .= substr($months[$mo - 1], 0, 3); break;
				case 'F': $out .= $months[$mo - 1]; break;
				case 'N': $out .= $w === 0 ? 7 : $w; break;
				case 'U': $out .= (int) $ts; break;
				case '-': case '/': case '.': case ':': case ',': case ' ': $out .= $c; break;
				default: break;
			}
		}
		return $out;
	}

	/* ------------------------------------------------------------------ render (markup) */

	/**
	 * The rendered HTML of a label, description, option text or heading. Static text is escaped (or kept
	 * as saved for a description: $raw_static), form / site / post tags are resolved here (the same for
	 * every visitor of this page), every other allowed tag becomes an isolated, empty-but-fallback
	 * <bdi class="efb efb-st" data-st="…"> that smart-tags-efb.js fills with textContent. A text without a
	 * tag comes back exactly as before (esc_html or raw).
	 */
	public static function markup($text, $where, array $ctx = array(), $raw_static = false) {
		$text = is_scalar($text) ? (string) $text : '';
		if (strpos($text, '{') === false) return $raw_static ? $text : self::esc($text);
		$out = '';
		foreach (self::parse($text) as $p) {
			if ($p['t'] === 'text') { $out .= $raw_static ? $p['v'] : self::esc($p['v']); continue; }
			if ($p['legacy'] && !self::has_field($ctx, $p['arg'])) { $out .= $raw_static ? $p['raw'] : self::esc($p['raw']); continue; }
			$ok = self::allowed($where, $p['ns'], self::arg_base($p));
			if ($ok && in_array($p['ns'], self::PUBLIC_NS, true)) {
				$v = self::value($p, $ctx);
				$out .= self::esc(self::clean_value($v === null || $v === '' ? $p['fb'] : $v));
				continue;
			}
			// Answer piping (a {field:…} piece in a label, option or heading) is Free Plus (spec §14): without it, the fallback.
			if ($ok && $p['ns'] === 'field' && isset($ctx['piping']) && !$ctx['piping']) $ok = false;
			if (!$ok) { $out .= self::esc(self::clean_value($p['fb'])); continue; }
			$inner = substr($p['raw'], 1, -1);
			if ($p['legacy']) $inner = 'field:' . $p['arg'];
			$out .= '<bdi class="efb efb-st" data-st="' . self::esc($inner) . '">' . self::esc(self::clean_value($p['fb'])) . '</bdi>';
		}
		return $out;
	}

	/**
	 * A default value or placeholder as the HTML may carry it: ['value' => the resolved public text or '',
	 * 'template' => the template for data-st-* when the browser must resolve it, or ''].
	 */
	public static function attr_value($text, $where, array $ctx = array()) {
		$text = is_scalar($text) ? (string) $text : '';
		if (!self::has_tags($text)) return array('value' => $text, 'template' => '');
		foreach (self::tags($text) as $t) {
			if (!in_array($t['ns'], self::PUBLIC_NS, true) && self::allowed($where, $t['ns'], self::arg_base($t))) {
				return array('value' => '', 'template' => $text);
			}
		}
		return array('value' => self::resolve($text, $ctx, 'text', $where), 'template' => '');
	}

	/* ------------------------------------------------------------------ redirect */

	/**
	 * A redirect address with its tags resolved. Tags are encoded with rawurlencode and only taken after
	 * the host: {ns:…} tags in the query or fragment (spec §4.2), the old {field_id} token also in the path
	 * (it always was). A tag in the scheme, host or port is dropped, and when the result does not keep the
	 * template's own host the address without any tag is used (no open redirect through {get:next}).
	 */
	public static function url($template, array $ctx, $where = 'redirect') {
		$template = is_scalar($template) ? trim((string) $template) : '';
		if ($template === '') return '';
		$static = self::strip_tags_text($template);
		if (strpos($template, '{') === false) return self::esc_url_raw($template);
		$cut = strcspn($template, '?#');
		$base = substr($template, 0, $cut);
		$rest = (string) substr($template, $cut);
		// Scheme and authority: everything up to the first "/" after "//".
		$auth_end = 0;
		if (preg_match('#^[a-z][a-z0-9+.\-]*://[^/]*#i', $base, $m)) $auth_end = strlen($m[0]);
		$head = substr($base, 0, $auth_end);
		$path = (string) substr($base, $auth_end);
		$head = self::strip_tags_text($head);
		$path_out = '';
		foreach (self::parse($path) as $p) {
			if ($p['t'] === 'text') { $path_out .= $p['v']; continue; }
			if (!$p['legacy']) continue; // {ns:…} only after "?" or "#"
			if (!self::has_field($ctx, $p['arg'])) continue;
			$path_out .= rawurlencode(self::clean_value(self::field_value($p['arg'], $ctx)));
		}
		$rest_out = self::resolve($rest, $ctx, 'url_query', $where, array('legacy_drop' => true));
		$url = self::esc_url_raw($head . $path_out . $rest_out);
		$want = parse_url(self::esc_url_raw($static), PHP_URL_HOST);
		$got = parse_url($url, PHP_URL_HOST);
		if ($url === '' || strtolower((string) $want) !== strtolower((string) $got)) return self::esc_url_raw($static);
		return $url;
	}

	/** The text with every tag (and legacy token) removed. */
	public static function strip_tags_text($text) {
		$out = '';
		foreach (self::parse($text) as $p) if ($p['t'] === 'text') $out .= $p['v'];
		return $out;
	}

	/** Whether a redirect template has a tag before "?" / "#" that is not a legacy path token (builder warning stTagUrlPart). */
	public static function url_tag_misplaced($template) {
		$template = is_scalar($template) ? (string) $template : '';
		$base = substr($template, 0, strcspn($template, '?#'));
		$auth = preg_match('#^[a-z][a-z0-9+.\-]*://[^/]*#i', $base, $m) ? $m[0] : '';
		if ($auth !== '' && count(self::tags($auth, true)) > 0) return true;
		return count(self::tags(substr($base, strlen($auth)))) > 0;
	}

	private static function esc_url_raw($u) {
		if (function_exists('esc_url_raw')) return esc_url_raw($u, array('http', 'https'));
		$u = preg_replace('/[^a-z0-9\-~+_.?#=!&;,\/:%@$\|*\'()\[\]\x80-\xff]/i', '', (string) $u);
		return preg_match('#^https?://#i', $u) ? $u : '';
	}

	/* ------------------------------------------------------------------ save */

	/** One F08 key as the save stores it ('' = not set). */
	public static function sanitize_key($k, $v) {
		switch ($k) {
			case 'prefill_key':
				$v = is_scalar($v) ? trim((string) $v) : '';
				return preg_match('/^[A-Za-z0-9_-]{1,40}$/D', $v) && !in_array(strtolower($v), self::RESERVED_KEYS, true) ? $v : '';
			case 'prefill_lock':
			case 'hdn_visitor_mail':
				return in_array($v, array(1, '1', true, 'true'), true) ? '1' : '';
			case 'st_uses':
				return ''; // computed by normalize_rows(); never taken from the browser
		}
		return is_scalar($v) ? (string) $v : '';
	}

	/**
	 * After sanitize_obj_msg_efb(): hidden templates cleaned (unknown namespaces, 500 characters), prefill keys
	 * only on the types that may be filled from the address and once per form (a later duplicate is dropped),
	 * prefill_lock only with a key, and row 0 st_uses = the namespaces the form uses ("get,user,user:email,…"),
	 * so the page and forms/session/prepare know what to load without scanning the form again.
	 */
	public static function normalize_rows(array $rows) {
		$seen = array();
		$uses = array();
		$add_uses = function ($text) use (&$uses) {
			foreach (self::tags($text) as $t) {
				$uses[$t['ns']] = true;
				if ($t['ns'] === 'user' && $t['arg'] === 'email') $uses['user:email'] = true;
			}
		};
		foreach ($rows as $i => $r) {
			if (!is_array($r)) continue;
			$type = isset($r['type']) && is_string($r['type']) ? $r['type'] : '';
			if ($i > 0 && $type === 'hiddenField') {
				$rows[$i]['value'] = self::clean_template(isset($r['value']) ? $r['value'] : '');
				unset($rows[$i]['required']);
			}
			if ($i > 0 && isset($r['prefill_key']) && $r['prefill_key'] !== '') {
				$k = self::sanitize_key('prefill_key', $r['prefill_key']);
				if ($k === '' || !in_array($type, self::PREFILL_TYPES, true) || isset($seen[strtolower($k)])) {
					unset($rows[$i]['prefill_key'], $rows[$i]['prefill_lock']);
				} else {
					$rows[$i]['prefill_key'] = $k;
					$seen[strtolower($k)] = true;
					$uses['prefill'] = true;
				}
			} elseif ($i > 0 && isset($r['prefill_lock'])) {
				unset($rows[$i]['prefill_lock']);
			}
			if ($i > 0) {
				foreach (array('name', 'message', 'placeholder') as $k) if (isset($r[$k]) && is_string($r[$k])) $add_uses($r[$k]);
				if (isset($r['value']) && is_string($r['value']) && ($type === 'hiddenField' || in_array($type, self::DEFAULT_TYPES, true) || $type === 'option' || $type === 'heading')) $add_uses($rows[$i]['value']);
			} else {
				foreach (array('email_sub', 'rePage') as $k) if (isset($r[$k]) && is_string($r[$k])) $add_uses($r[$k]);
				if (isset($r['thank_you_message']) && is_array($r['thank_you_message'])) foreach ($r['thank_you_message'] as $tv) if (is_string($tv)) $add_uses($tv);
			}
		}
		if (isset($rows[0]) && is_array($rows[0])) {
			$order = array_merge(self::NS, array('user:email', 'prefill'));
			$list = array_values(array_filter($order, function ($n) use ($uses) { return isset($uses[$n]); }));
			if ($list) $rows[0]['st_uses'] = implode(',', $list);
			else unset($rows[0]['st_uses']);
		}
		return $rows;
	}

	/** st_uses of a stored row 0 as a set. */
	public static function uses($row0) {
		$s = is_array($row0) && isset($row0['st_uses']) ? $row0['st_uses'] : (is_object($row0) && isset($row0->st_uses) ? $row0->st_uses : '');
		$out = array();
		foreach (explode(',', is_string($s) ? $s : '') as $n) if ($n !== '') $out[$n] = true;
		return $out;
	}

	/** Builder / API checks that do not refuse a save: warnings with a path ({code, path, m}). */
	public static function form_warnings(array $rows) {
		$w = array();
		$steps = array();
		foreach ($rows as $i => $r) if ($i > 0 && is_array($r) && isset($r['id_'])) $steps[(string) $r['id_']] = isset($r['step']) ? (int) $r['step'] : 1;
		foreach ($rows as $i => $r) {
			if (!is_array($r)) continue;
			if ($i === 0) {
				if (!empty($r['rePage']) && is_string($r['rePage']) && self::url_tag_misplaced(self::rePage_url($r['rePage']))) $w[] = array('code' => 'smart_url_part', 'path' => 'rows[0].rePage');
				continue;
			}
			foreach (array('name', 'message', 'placeholder', 'value') as $k) {
				if (!isset($r[$k]) || !is_string($r[$k])) continue;
				foreach (self::tags($r[$k]) as $t) {
					if ($t['ns'] === 'field') {
						$fid = preg_replace('/\.(label|value)$/D', '', $t['arg']);
						if (!isset($steps[$fid])) $w[] = array('code' => 'smart_field_missing', 'path' => 'rows[' . $i . '].' . $k, 'field' => $fid);
						elseif (isset($r['step']) && $k !== 'value' && $steps[$fid] > (int) $r['step']) $w[] = array('code' => 'smart_field_later', 'path' => 'rows[' . $i . '].' . $k, 'field' => $fid);
					}
					if (in_array($t['ns'], array('ip', 'cookie'), true) || ($t['ns'] === 'user' && $t['arg'] === 'email')) $w[] = array('code' => 'smart_pii', 'path' => 'rows[' . $i . '].' . $k, 'tag' => self::key_of($t));
				}
			}
		}
		return $w;
	}

	/** The save warnings with their sentence (phrases of text_efb() when given, English otherwise). */
	public static function form_warnings_text(array $rows, array $lang = array()) {
		$m = array(
			'smart_url_part'      => array('stTagUrlPart', 'Smart tags can only be used after "?" or "#" in a redirect address.'),
			'smart_field_missing' => array('stUnavailable', 'Not available'),
			'smart_field_later'   => array('stFieldLater', 'This answer comes from a later step, so it will be empty here.'),
			'smart_pii'           => array('stIpPrivacy', 'Storing IP addresses may require consent in your country. Check the privacy settings.'),
		);
		$out = array();
		foreach (self::form_warnings($rows) as $w) {
			// Personal data is a note next to the preset (and a risk flag of validate_rows), not a save warning.
			if ($w['code'] === 'smart_pii') continue;
			$k = isset($m[$w['code']]) ? $m[$w['code']] : array('', '');
			$w['m'] = isset($lang[$k[0]]) && is_string($lang[$k[0]]) && $lang[$k[0]] !== '' ? $lang[$k[0]] : $k[1];
			if ($w['code'] === 'smart_pii' && isset($w['tag']) && $w['tag'] !== 'ip') $w['m'] = 'This hidden value stores personal data (' . $w['tag'] . ').';
			$out[] = $w;
		}
		return $out;
	}

	/**
	 * SitePilot contract (efb_api_form_validate, §7.2): errors that refuse a definition (an unknown namespace in
	 * a hidden template or a text, an invalid prefill key) and risk flags (pii_capture for {user:email}, {ip},
	 * {cookie:*}). Returns ['errors' => [{code, path, …}], 'flags' => [...]].
	 */
	public static function validate_rows(array $rows) {
		$errors = array();
		$flags = array();
		$keys = array();
		foreach ($rows as $i => $r) {
			if (!is_array($r)) continue;
			foreach (array('value', 'name', 'message', 'placeholder', 'email_sub', 'rePage') as $k) {
				if (!isset($r[$k]) || !is_string($r[$k]) || strpos($r[$k], '{') === false) continue;
				if (preg_match_all('/\{([a-z][a-z0-9_]{0,15}):[^{}]{0,200}\}/u', $r[$k], $mm)) {
					foreach ($mm[1] as $j => $ns) if (!in_array($ns, self::NS, true)) $errors[] = array('code' => 'smart_unknown_tag', 'path' => 'rows[' . $i . '].' . $k, 'tag' => $mm[0][$j]);
				}
				foreach (self::tags($r[$k]) as $t) {
					if ($t['ns'] === 'ip' || $t['ns'] === 'cookie' || ($t['ns'] === 'user' && $t['arg'] === 'email')) $flags['pii_capture'] = true;
				}
			}
			if ($i > 0 && isset($r['prefill_key']) && $r['prefill_key'] !== '') {
				$k = self::sanitize_key('prefill_key', $r['prefill_key']);
				$type = isset($r['type']) ? (string) $r['type'] : '';
				if ($k === '' || !in_array($type, self::PREFILL_TYPES, true)) $errors[] = array('code' => 'smart_prefill', 'path' => 'rows[' . $i . '].prefill_key');
				elseif (isset($keys[strtolower($k)])) $errors[] = array('code' => 'smart_prefill_dup', 'path' => 'rows[' . $i . '].prefill_key');
				else $keys[strtolower($k)] = true;
			}
		}
		return array('errors' => $errors, 'flags' => array_keys($flags));
	}

	/** rePage is saved with "@efb@" for "/" by the old panel. */
	public static function rePage_url($s) {
		$s = preg_replace('/(https?):@efb@/', '$1://', (string) $s);
		$s = preg_replace('/(@efb@)+/', '/', $s);
		return preg_replace('#^(https?):/(?!/)#i', '$1://', $s);
	}

	/* ------------------------------------------------------------------ server context */

	/**
	 * The trusted context of one submission (the server's own values; the request only for what the visitor
	 * controls anyway). $rows = the stored form; $req = query (array), url, page_id; $values / $fields
	 * come later (after the field loop) through with_answers().
	 */
	public static function submit_context(array $rows, $form_id, array $req = array()) {
		$fid = (int) $form_id;
		$s0 = isset($rows[0]) && is_array($rows[0]) ? $rows[0] : array();
		$page_id = isset($req['page_id']) && is_numeric($req['page_id']) ? (int) $req['page_id'] : 0;
		$page = array('url' => '', 'path' => '', 'title' => '', 'id' => '');
		$url = isset($req['url']) && is_string($req['url']) ? $req['url'] : '';
		if ($url !== '' && self::http_url($url)) {
			$page['url'] = $url;
			$p = parse_url($url, PHP_URL_PATH);
			$page['path'] = is_string($p) ? $p : '/';
		}
		$post = array('id' => '', 'title' => '', 'url' => '', 'type' => '');
		if ($page_id > 0 && function_exists('get_post') && ($po = get_post($page_id)) && self::post_public($po)) {
			$page['id'] = (string) $page_id;
			$page['title'] = function_exists('get_the_title') ? wp_strip_all_tags(get_the_title($po)) : '';
			$post = array('id' => (string) $page_id, 'title' => $page['title'], 'url' => function_exists('get_permalink') ? (string) get_permalink($po) : '', 'type' => (string) $po->post_type);
		}
		$ctx = array(
			'query'   => isset($req['query']) && is_array($req['query']) ? $req['query'] : array(),
			'cookies' => isset($req['cookies']) && is_array($req['cookies']) ? $req['cookies'] : (isset($_COOKIE) && is_array($_COOKIE) ? self::unslash($_COOKIE) : array()),
			'ref'     => '',
			'page'    => $page,
			'post'    => $post,
			'user'    => self::current_user(),
			'now'     => time(),
			'tz'      => self::site_offset(time()),
			'ip'      => isset($req['ip']) ? (string) $req['ip'] : '',
			'uid'     => function ($n) { return function_exists('wp_generate_password') ? wp_generate_password($n, false, false) : substr(bin2hex(random_bytes(16)), 0, $n); },
			'form'    => array('id' => (string) $fid, 'name' => isset($s0['formName']) && is_scalar($s0['formName']) ? (string) $s0['formName'] : ''),
			'site'    => self::site(),
			'fields'  => self::fields_of($rows),
			'values'  => array(),
			'entry'   => array('track' => ''),
		);
		return $ctx;
	}

	private static function unslash($a) {
		return function_exists('wp_unslash') ? wp_unslash($a) : $a;
	}

	private static function post_public($po) {
		if (!is_object($po) || empty($po->ID)) return false;
		if (function_exists('is_post_publicly_viewable')) return is_post_publicly_viewable($po) && empty($po->post_password);
		return isset($po->post_status) && $po->post_status === 'publish' && empty($po->post_password);
	}

	public static function site() {
		return array(
			'name' => function_exists('get_bloginfo') ? wp_strip_all_tags((string) get_bloginfo('name')) : '',
			'url'  => function_exists('home_url') ? (string) home_url('/') : '',
		);
	}

	/** The site's zone offset in seconds at a time (wp_timezone; 0 without WordPress). */
	public static function site_offset($ts = null) {
		if (!function_exists('wp_timezone')) return 0;
		try {
			$dt = new \DateTime('@' . (int) ($ts === null ? time() : $ts));
			return (int) wp_timezone()->getOffset($dt);
		} catch (\Exception $e) {
			return 0;
		}
	}

	/** The logged-in user, or null for a guest (never another user). */
	public static function current_user($with_email = true) {
		if (!function_exists('is_user_logged_in') || !is_user_logged_in()) return null;
		$u = wp_get_current_user();
		if (!$u || empty($u->ID)) return null;
		$roles = isset($u->roles) && is_array($u->roles) ? array_values($u->roles) : array();
		$out = array(
			'id'           => (string) $u->ID,
			'login'        => (string) $u->user_login,
			'display_name' => (string) $u->display_name,
			'first_name'   => (string) get_user_meta($u->ID, 'first_name', true),
			'last_name'    => (string) get_user_meta($u->ID, 'last_name', true),
			'role'         => isset($roles[0]) ? (string) $roles[0] : '',
		);
		if ($with_email) $out['email'] = (string) $u->user_email;
		return $out;
	}

	/** id => {type, name, options: [{id, label, value}]} of the answer fields of a form. */
	public static function fields_of(array $rows) {
		$fields = array();
		foreach ($rows as $i => $r) {
			if ($i < 1 || !is_array($r) || !isset($r['id_'], $r['type']) || in_array($r['type'], array('option', 'r_matrix', 'step'), true)) continue;
			$fields[(string) $r['id_']] = array('type' => (string) $r['type'], 'name' => isset($r['name']) && is_scalar($r['name']) ? (string) $r['name'] : '', 'options' => array());
		}
		foreach ($rows as $i => $r) {
			if ($i < 1 || !is_array($r) || ($r['type'] ?? '') !== 'option' || !isset($r['parent']) || !isset($fields[(string) $r['parent']])) continue;
			$label = isset($r['value']) && is_scalar($r['value']) ? (string) $r['value'] : '';
			$fields[(string) $r['parent']]['options'][] = array('id' => (string) ($r['id_'] ?? ''), 'label' => $label, 'value' => isset($r['opt_value']) && is_scalar($r['opt_value']) ? (string) $r['opt_value'] : '');
		}
		return $fields;
	}

	/**
	 * F05: id => the stored values of the chosen choices of a submission (rows the server checked: ov set by
	 * Choices::add_ov() / ranking_row(); an "Other" answer adds its text). Only fields with such rows.
	 */
	public static function ovals_of(array $rows) {
		$out = array();
		foreach ($rows as $r) {
			if (!is_array($r) || !isset($r['id_']) || !is_scalar($r['id_'])) continue;
			$id = (string) $r['id_'];
			if (!empty($r['other']) && isset($r['value']) && is_scalar($r['value'])) { $out[$id][] = (string) $r['value']; continue; }
			// F04: {field:id.value} of a formatted number or a calc result is the plain number (the label form is disp).
			if (isset($r['disp']) && is_string($r['disp']) && $r['disp'] !== '' && isset($r['value']) && is_scalar($r['value']) && in_array($r['type'] ?? '', array('number', 'calc', 'range'), true)) { $out[$id] = array((string) $r['value']); continue; }
			if (!isset($r['ov']) || !is_scalar($r['ov']) || (string) $r['ov'] === '') continue;
			foreach (array_filter(explode('@efb!', (string) $r['ov']), 'strlen') as $v) $out[$id][] = $v;
		}
		return $out;
	}

	/**
	 * The answers of a submission as tags read them: id => label text, or a list for several choices.
	 * Rows are the validated rows (radio / select / checkbox already carry the saved option text).
	 */
	public static function values_of(array $rows, array $fields = array()) {
		$vals = array();
		foreach ($rows as $r) {
			if (!is_array($r) || !isset($r['id_']) || !is_scalar($r['id_'])) continue;
			$type = isset($r['type']) ? (string) $r['type'] : '';
			if (in_array($type, array('w_link', 'payment', 'password'), true)) continue;
			$id = (string) $r['id_'];
			if (isset($fields[$id]) && in_array($fields[$id]['type'], array('password', 'esign', 'maps'), true)) continue;
			$v = isset($r['value']) ? $r['value'] : '';
			if (!is_scalar($v)) continue;
			$v = (string) $v;
			// F04: a formatted number or a calc result reads as the field shows it (separators, digits, affixes, currency).
			if (isset($r['disp']) && is_string($r['disp']) && $r['disp'] !== '' && in_array($type, array('number', 'calc', 'range'), true)) $v = $r['disp'];
			if ($v === '@file@') { $v = isset($r['fnm']) && is_scalar($r['fnm']) ? (string) $r['fnm'] : ''; }
			$v = str_replace(array('@efb@dq#', '@n#'), array('"', ' '), $v);
			if (strpos($v, '@efb!') !== false) {
				$vals[$id] = array_values(array_filter(array_map('trim', explode('@efb!', $v)), 'strlen'));
				continue;
			}
			if (strpos(strtolower($type), 'checkbox') !== false || (isset($vals[$id]) && $type === 'option')) {
				if (!isset($vals[$id]) || !is_array($vals[$id])) $vals[$id] = isset($vals[$id]) && $vals[$id] !== '' ? array($vals[$id]) : array();
				if ($v !== '') $vals[$id][] = $v;
				continue;
			}
			if (isset($vals[$id]) && is_array($vals[$id])) { if ($v !== '') $vals[$id][] = $v; continue; }
			if (isset($vals[$id]) && $vals[$id] !== '' && $v !== '' && isset($fields[$id]) && in_array($fields[$id]['type'], array('file', 'dadfile'), true)) { $vals[$id] = array($vals[$id], $v); continue; }
			$vals[$id] = $v;
		}
		return $vals;
	}

	/**
	 * The stored value of one hidden field on submit. $posted = the browser's copy (or null). The server
	 * decides every tag it can; field tags come from $ctx['values'] (the validated answers).
	 */
	public static function hidden_value(array $field, array $ctx, $posted = null) {
		$tpl = self::clean_template(isset($field['value']) ? $field['value'] : '');
		$cls = self::classify($tpl);
		if ($cls === 'static') return $tpl;
		$c = $ctx;
		$c['ref'] = ''; // the landing referrer exists in the browser only
		$v = self::clean_value(self::resolve($tpl, $c, 'text', 'hidden'));
		if ($cls !== 'client' || $posted === null || !is_scalar($posted)) return $v;
		$p = self::clean_value(function_exists('sanitize_text_field') ? sanitize_text_field((string) $posted) : trim(strip_tags((string) $posted)));
		if ($p === '') return $v;
		$tags = self::tags($tpl);
		$from_request = false;
		foreach ($tags as $t) {
			$x = self::value($t, $c);
			if ($x !== null && $x !== '') { $from_request = true; break; }
		}
		// The request gave every visitor tag (the browser's copy changes nothing the visitor could not change anyway).
		if ($from_request) return $v;
		// Nothing came from the request ({ref}, a resume link, a page that dropped its query): the browser's
		// cleaned copy, which for a lone address tag must be an http(s) address.
		$lone = count($tags) === 1 && trim(self::strip_tags_text($tpl)) === '';
		if ($lone && in_array(self::key_of($tags[0]), array('ref', 'page:url'), true)) {
			return self::http_url($p) ? self::clean_value(function_exists('esc_url_raw') ? esc_url_raw($p) : $p) : $v;
		}
		return $p;
	}

	/* ------------------------------------------------------------------ WordPress */

	public static function register() {
		if (!function_exists('add_filter')) return;
		add_filter('efb_form_schema_field', array(__CLASS__, 'schema_field'), 10, 3);
		add_filter('efb_form_schema', array(__CLASS__, 'schema'), 10, 3);
		add_filter('efb_capabilities', array(__CLASS__, 'capabilities'), 24, 2);
	}

	/** forms/{id}/schema: a hidden field is the server's (assistant none, no template); a prefill key is listed. */
	public static function schema_field($e, $r, $form_id) {
		if (!is_array($e) || !is_array($r)) return $e;
		if (isset($r['type']) && $r['type'] === 'hiddenField') {
			return array('id' => $e['id'], 'type' => 'hiddenField', 'step' => $e['step'], 'assistant' => 'none', 'fill' => array('api' => 'none', 'note' => 'the server sets this value on submit'), 'prefill_key' => isset($r['prefill_key']) && is_string($r['prefill_key']) && $r['prefill_key'] !== '' ? $r['prefill_key'] : null);
		}
		if (isset($r['prefill_key']) && is_string($r['prefill_key']) && $r['prefill_key'] !== '' && in_array($r['type'] ?? '', self::PREFILL_TYPES, true)) $e['prefill_key'] = $r['prefill_key'];
		return $e;
	}

	/** forms/{id}/schema: prefill = [{key, field, type}] so a link ?key=value can be built. */
	public static function schema($out, $form_id, $context = array()) {
		if (!is_array($out) || !isset($out['fields']) || !is_array($out['fields'])) return $out;
		$list = array();
		foreach ($out['fields'] as $f) {
			if (is_array($f) && !empty($f['prefill_key'])) $list[] = array('key' => $f['prefill_key'], 'field' => $f['id'], 'type' => $f['type']);
		}
		$out['prefill'] = array('params' => $list, 'how' => 'add ?key=value to the page address; several values of one field with "," or the key repeated', 'locked_by_owner' => 'prefill_lock is never set by an assistant');
		return $out;
	}

	/** The F08 entries of the capability registry (SitePilot / AutoPilot). */
	public static function capabilities($out, $args = array()) {
		if (!is_array($out) || !isset($out['features']) || !is_array($out['features'])) return $out;
		$package = isset($out['plan']['package_type']) ? (int) $out['plan']['package_type'] : null;
		$entries = array(
			'setting:hidden_field'  => array('free', array('type=hiddenField', 'value', 'hdn_visitor_mail'), 'hiddenField'),
			'setting:smart_tags'    => array('free', array('value', 'placeholder', 'email_sub', 'rePage', 'thank_you_message'), 'stInsert'),
			'setting:url_prefill'   => array('free', array('prefill_key', 'prefill_lock'), 'prefillKey'),
			'setting:answer_piping' => array('free_plus', array('name', 'message', 'option.value'), 'stInsert'),
		);
		foreach ($entries as $key => $e) {
			$ok = class_exists('\\Emsfb\\Builder_Api') ? Builder_Api::plan_at_least($e[0], $package) : true;
			$out['features'][$key] = array(
				'kind' => 'setting', 'scope' => 'field', 'package' => $e[0], 'addon' => null, 'available_now' => $ok, 'reason' => $ok ? null : 'plan',
				'prompt_state' => $e[0] === 'free_plus' ? 3 : 0,
				'message_key' => $e[0] === 'free_plus' ? 'thisFeatureAvailableFreePlusPro' : null,
				'form_keys' => $e[1], 'label_key' => $e[2],
			);
		}
		$out['smart_tags'] = array(
			'syntax'        => '{ns:arg|fallback}',
			'namespaces'    => array(
				'get' => '{get:<param>}', 'cookie' => '{cookie:<name>}', 'ref' => '{ref}', 'page' => self::ARGS['page'], 'post' => self::ARGS['post'],
				'user' => self::ARGS['user'], 'date' => '{date:<Y y m n d j H G h g i s A a D l M F N U and - / . : , space>}', 'ip' => '{ip}',
				'uid' => '{uid} / {uid:6..32}', 'field' => '{field:<id>} / {field:<id>.label} / {field:<id>.value}', 'form' => self::ARGS['form'],
				'site' => self::ARGS['site'], 'entry' => self::ARGS['entry'],
			),
			'places'        => array(
				'default' => 'get cookie page post user date form site', 'hidden' => 'all but entry', 'placeholder' => 'get page post user:display_name user:first_name date form site',
				'label' => 'field get user:display_name user:first_name page:title post:title date form site', 'message' => 'all but ip cookie uid user:email, plus entry:track',
				'email_admin' => 'all', 'email_visitor' => 'all but ip cookie', 'redirect' => 'all but ip cookie user:email, after "?" or "#" only',
			),
			'presets'       => self::presets(),
			'field_type'    => 'hiddenField',
			'field_keys'    => self::FIELD_KEYS,
			'prefill_types' => self::PREFILL_TYPES,
			'read_only'     => array('st_uses'),
			'builder'       => array('efbBuilderApi.insertItems', 'efbBuilderApi.setForm', 'efbBuilderApi.setHiddenField', 'efbBuilderApi.setPrefill'),
			'page_api'      => 'window.EFBSmart',
			'assistant'     => 'none (hiddenField); prefill_key in forms/{id}/schema',
			'save_warnings' => array('smart_url_part', 'smart_field_missing', 'smart_field_later', 'smart_pii'),
			'risk_flags'    => array('pii_capture' => '{user:email}, {ip}, {cookie:*}'),
			'spec'          => 'F08',
		);
		return $out;
	}

	/** Hidden-field presets (the builder's "Fill with" list and AutoPilot). */
	public static function presets() {
		return array(
			'static' => '', 'utm_source' => '{get:utm_source}', 'utm_medium' => '{get:utm_medium}', 'utm_campaign' => '{get:utm_campaign}',
			'utm_term' => '{get:utm_term}', 'utm_content' => '{get:utm_content}', 'gclid' => '{get:gclid}', 'fbclid' => '{get:fbclid}',
			'msclkid' => '{get:msclkid}', 'param' => '{get:<name>}', 'ref' => '{ref}', 'page_url' => '{page:url}', 'page_title' => '{page:title}',
			'post_id' => '{post:id}', 'ip' => '{ip}', 'user_id' => '{user:id}', 'user_email' => '{user:email}', 'user_login' => '{user:login}',
			'user_name' => '{user:display_name}', 'user_role' => '{user:role}', 'date' => '{date:Y-m-d}', 'time' => '{date:H:i}',
			'datetime' => '{date:Y-m-d H:i}', 'uid' => '{uid}', 'cookie' => '{cookie:<name>}',
		);
	}
}
