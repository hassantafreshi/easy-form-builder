<?php
/**
 * F10: layout and special fields (docs/feature-gaps/F10-layout-special-fields.fa.md).
 *
 *  A  the review page before sending (form keys review_before_submit, review_hide_empty, review_title);
 *     the page draws it (public/assets/js/layout-efb.js), the server checks every answer as before. A
 *     payment-framework form asks the server for the amount (payments/quote) instead of computing it.
 *  B  the section (type section: collapsible, collapsed; form key section_accordion): a heading that shows /
 *     hides the fields after it in its step. Membership is never stored: it is the array order (the page
 *     uses the DOM order, the same). A section hidden by Conditional Logic hides its fields on the server
 *     too (expand_hidden_sections()).
 *  C  the visitor's rich text on a textarea (rich_text, rich_toolbar; Free Plus): the answer is HTML cleaned with
 *     wp_kses and a strict allow-list (rich_allowed_html()), its length counted on the text (rich_check()), and
 *     shown as safe HTML or as plain text (rich_plain()) everywhere else.
 *  D  who sees a field (vis_mode all | in | out | roles, vis_roles; the builder's control is Pro, the rule is always
 *     enforced): filter_rows() takes the fields a viewer may not see out of the page (HTML and JSON) and the
 *     forms/{id}/schema; filter_submission() drops their answers and their required check on every submit route.
 *     A page holding such a form is kept out of page caches; when the site keeps it cacheable anyway, the page has
 *     only the fields every visitor sees (vis_view "public") and the submit follows that view.
 *  E  a typed signature on an e-signature field (esign_mode draw | type | both, esign_font auto | latin | arabic):
 *     the page draws the typed name on the field's canvas with a bundled font (Dancing Script, Aref Ruqaa; SIL OFL)
 *     and sends that PNG like a drawn one, with sig_mode "type", sig_text (the name, ≤ 60) and sig_font; the server
 *     keeps them only on a field that allows typing (sig_attrs()), so every display shows the same image and
 *     "Typed signature: name".
 *
 * The save rules of every F10 key are here (sanitize_key(), called from sanitize_obj_msg_efb()); the
 * browser's twin is EFBLayoutCore.sanitizeKey() in layout-efb.js (tests/test-layout-parity.js).
 */
namespace Emsfb;

if (!defined('ABSPATH')) {
	exit;
}

class Layout {

	/**
	 * The plan of each part (spec §14). The owner may change it with the filter efb_layout_plan
	 * ($need, $part). The review page and sections are free.
	 */
	const PLAN = array('review' => 'free', 'section' => 'free', 'rich_text' => 'free_plus', 'vis' => 'pro', 'sig' => 'free_plus');

	/** Row-0 keys of the review page. */
	const FORM_KEYS = array('review_before_submit', 'review_hide_empty', 'review_title', 'section_accordion');

	/** Field keys. collapsible is shared with F09's repeater group (Repeater::sanitize_key keeps '1' / '0'). */
	const FIELD_KEYS = array('collapsed', 'rich_text', 'rich_toolbar', 'vis_mode', 'vis_roles', 'esign_mode', 'esign_font');

	/** Keys of the page only: never saved. */
	const RUNTIME_KEYS = array('vis_view', 'vis_empty');

	const LIMITS = array('review_title' => 120, 'rich_raw_max' => 65535, 'rich_raw_min' => 4096, 'sig_text' => 60);

	/** The defaults when a key is missing (old forms). */
	const DEFAULTS = array('review_before_submit' => '0', 'review_hide_empty' => '1', 'review_title' => '', 'section_accordion' => '0', 'collapsible' => '1', 'collapsed' => '0', 'rich_text' => '0', 'rich_toolbar' => 'basic', 'vis_mode' => 'all', 'vis_roles' => '', 'esign_mode' => 'draw', 'esign_font' => 'auto');
	const SIG_MODES = array('draw', 'type', 'both');
	const SIG_FONTS = array('auto', 'latin', 'arabic');
	/** The bundled fonts (the Typed Signature add-on, vendor/typed-signature/assets/fonts, SIL OFL): the font id stored with a typed signature → family. */
	const SIG_FAMILIES = array('latin' => 'Dancing Script', 'arabic' => 'Aref Ruqaa');
	const VIS_MODES = array('all', 'in', 'out', 'roles');
	/** Login / register rows the visibility rule never touches (fixed ids the handlers read). */
	const VIS_FIXED_IDS = array('emaillogin', 'passwordlogin', 'usernameRegisterEFB', 'passwordRegisterEFB', 'repasswordRegisterEFB', 'emailRegisterEFB');

	/** The tags a visitor's rich text may hold (no attribute but a link's href). */
	const RICH_TAGS = array('p', 'br', 'strong', 'b', 'em', 'i', 'u', 'ul', 'ol', 'li', 'blockquote', 'h3', 'h4', 'a');
	const RICH_TOOLBARS = array('basic', 'full');

	/** Types of the legacy payment forms: the visitor pays inside the form, before Send (no review page there). */
	const LEGACY_PAY_TYPES = array('stripe', 'paypal', 'persiaPay', 'persiapay', 'zarinPal');

	/** WordPress hooks (the capability registry). */
	public static function register() {
		if (!function_exists('add_filter')) return;
		add_filter('efb_capabilities', array(__CLASS__, 'capabilities'), 29, 2);
		add_filter('efb_admin_localize_vars', array(__CLASS__, 'localize_vars'), 29, 2);
		add_filter('efb_form_schema_field', array(__CLASS__, 'schema_field'), 15, 3);
		add_filter('efb_form_schema', array(__CLASS__, 'schema'), 20, 3);
	}

	/* ------------------------------------------------------------------ plan */

	public static function need($part) {
		$need = isset(self::PLAN[$part]) ? self::PLAN[$part] : 'pro';
		if (function_exists('apply_filters')) $need = apply_filters('efb_layout_plan', $need, $part);
		return in_array($need, array('free', 'free_plus', 'pro'), true) ? $need : 'pro';
	}

	public static function plan_ok($part, $package = null) {
		$need = self::need($part);
		if ($need === 'free') return true;
		if ($package === null && function_exists('efb_plan_at_least_efb')) return (bool) efb_plan_at_least_efb($need);
		return class_exists('\\Emsfb\\Builder_Api') ? (bool) Builder_Api::plan_at_least($need, $package) : false;
	}

	/* ------------------------------------------------------------------ helpers */

	public static function on($v) {
		return in_array($v, array(true, 1, '1', 'true', 'on'), true);
	}

	private static function arr($r) {
		if (is_array($r)) return $r;
		if (is_object($r)) return get_object_vars($r);
		return array();
	}

	public static function s($r, $k, $d = '') {
		$r = self::arr($r);
		return isset($r[$k]) && is_scalar($r[$k]) ? (string) $r[$k] : $d;
	}

	private static function text($v, $max) {
		$t = function_exists('sanitize_text_field') ? sanitize_text_field($v) : trim(preg_replace('/[\r\n\t ]+/', ' ', preg_replace('/<[^>]*>/', '', $v)));
		return function_exists('mb_substr') ? mb_substr($t, 0, $max, 'UTF-8') : substr($t, 0, $max);
	}

	/* ------------------------------------------------------------------ save rules */

	/** Whether $k is a key this class keeps on save. */
	public static function is_key($k) {
		return in_array($k, self::FORM_KEYS, true) || in_array($k, self::FIELD_KEYS, true) || in_array($k, self::RUNTIME_KEYS, true);
	}

	/**
	 * The stored value of an F10 key ('' = not set; the caller drops it). Flags are '1' / '0'. The twin of
	 * EFBLayoutCore.sanitizeKey() (layout-efb.js).
	 */
	public static function sanitize_key($k, $v, $type = '') {
		if (in_array($k, self::RUNTIME_KEYS, true)) return '';
		if (is_bool($v)) $v = $v ? '1' : '0';
		if (!is_scalar($v)) return '';
		$v = (string) $v;
		switch ($k) {
			case 'review_before_submit':
			case 'review_hide_empty':
			case 'section_accordion':
			case 'collapsed':
			case 'rich_text':
				return self::on($v) ? '1' : '0';
			case 'rich_toolbar':
				return in_array($v, self::RICH_TOOLBARS, true) ? $v : 'basic';
			case 'vis_mode':
				return in_array($v, self::VIS_MODES, true) ? $v : 'all';
			case 'esign_mode':
				return in_array($v, self::SIG_MODES, true) ? $v : 'draw';
			case 'esign_font':
				return in_array($v, self::SIG_FONTS, true) ? $v : 'auto';
			case 'vis_roles':
				return implode(',', self::vis_roles_clean($v));
			case 'review_title':
				return self::text($v, self::LIMITS['review_title']);
		}
		return '';
	}

	/* ------------------------------------------------------------------ A: review */

	/** Whether a form (rows, row 0 first) holds a legacy gateway field (paid inside the form, before Send). */
	public static function legacy_payment($rows) {
		foreach ((array) $rows as $i => $r) {
			if ($i > 0 && in_array(self::s($r, 'type'), self::LEGACY_PAY_TYPES, true)) return true;
		}
		return false;
	}

	/** Whether the page shows the review page before sending (row 0 flag, not a legacy payment form). */
	public static function review_on($rows) {
		$rows = (array) $rows;
		if (!isset($rows[0]) || !self::on(self::s($rows[0], 'review_before_submit'))) return false;
		if (!self::plan_ok('review')) return false;
		return !self::legacy_payment($rows);
	}

	/**
	 * Whether the stored form JSON (the raw column, quotes escaped or not) needs layout-efb.js on the page.
	 * Later parts add their own keys.
	 */
	public static function json_needs_runtime($json) {
		if (!is_string($json) || $json === '') return false;
		return (bool) preg_match('/\\\\?"review_before_submit\\\\?"\s*:\s*\\\\?"?(?:1|true)|type\\\\?"\s*:\s*\\\\?"section\\\\?"/', $json) || self::json_has_rich($json);
	}

	/** Whether the stored form JSON has an e-signature that may be typed (the fonts and the typing on the page). */
	public static function json_has_typed_sig($json) {
		return is_string($json) && (bool) preg_match('/\\\\?"esign_mode\\\\?"\s*:\s*\\\\?"(?:type|both)/', $json);
	}

	/** Whether the stored form JSON has a rich textarea (rich-text-efb.js on the page). */
	public static function json_has_rich($json) {
		return is_string($json) && (bool) preg_match('/\\\\?"rich_text\\\\?"\s*:\s*\\\\?"?(?:1|true)/', $json);
	}

	/* ------------------------------------------------------------------ C: rich text */

	/** Whether a field takes rich text now: a textarea with rich_text, on a plan that has it (else a plain textarea). */
	public static function rich_on($f) {
		$t = self::s($f, 'type');
		return $t === 'textarea' && self::on(self::s($f, 'rich_text')) && self::plan_ok('rich_text');
	}

	/** wp_kses() allow-list of a visitor's rich text: the tags without attributes, a with href only. */
	public static function rich_allowed_html() {
		$out = array();
		foreach (self::RICH_TAGS as $t) $out[$t] = array();
		$out['a'] = array('href' => true);
		return $out;
	}

	/** The largest HTML kept for a field (bytes): min(65535, max(4 × mlen, 4096)); 65535 without a maximum. */
	public static function rich_raw_cap($f) {
		$mlen = (int) self::s($f, 'mlen', '0');
		return $mlen > 0 ? min(self::LIMITS['rich_raw_max'], max(4 * $mlen, self::LIMITS['rich_raw_min'])) : self::LIMITS['rich_raw_max'];
	}

	/**
	 * A visitor's HTML as it may be stored or shown: the allow-list (wp_kses with http, https and mailto only),
	 * the browser's <div> paragraphs as <p>, the content of script / style / … dropped, balanced tags, links with
	 * rel="nofollow ugc noopener" and target="_blank" written here (a link without a usable address loses its tag).
	 */
	public static function rich_sanitize($html) {
		$html = is_scalar($html) ? (string) $html : '';
		if ($html === '') return '';
		$html = preg_replace('#<(script|style|xml|head|title|template|object|iframe|noscript|svg|math|select|textarea|button|embed)\b[^>]*>.*?</\1\s*>#is', '', $html);
		$html = preg_replace('#<(script|style|iframe|object|embed)\b[^>]*/?>#i', '', $html);
		$html = preg_replace('#<!--.*?(-->|$)#s', '', $html);
		$html = preg_replace(array('#<div\b[^>]*>#i', '#</div\s*>#i', '#<h[12]\b[^>]*>#i', '#</h[12]\s*>#i', '#<h[56]\b[^>]*>#i', '#</h[56]\s*>#i'), array('<p>', '</p>', '<h3>', '</h3>', '<h4>', '</h4>'), $html);
		if (function_exists('wp_kses')) {
			$html = wp_kses($html, self::rich_allowed_html(), array('http', 'https', 'mailto'));
		} else {
			$html = strip_tags($html, '<' . implode('><', self::RICH_TAGS) . '>');
			$html = preg_replace('#<(?!a\b)([a-z0-9]+)\b[^>]*>#i', '<$1>', $html);
		}
		if (function_exists('force_balance_tags')) $html = force_balance_tags($html);
		if (function_exists('wp_kses_normalize_entities')) $html = wp_kses_normalize_entities($html);
		$html = preg_replace_callback('#<a\b([^>]*)>(.*?)</a>#is', function ($m) {
			$href = '';
			if (preg_match('#\bhref\s*=\s*(?:"([^"]*)"|\'([^\']*)\'|([^\s>]+))#i', $m[1], $h)) $href = html_entity_decode($h[1] !== '' ? $h[1] : (isset($h[2]) && $h[2] !== '' ? $h[2] : (isset($h[3]) ? $h[3] : '')), ENT_QUOTES, 'UTF-8');
			$href = trim($href);
			if (!preg_match('#^(https?://[^\s"<>]+|mailto:[^\s"<>]+)$#i', $href)) return $m[2];
			$attr = function_exists('esc_url') ? esc_url($href, array('http', 'https', 'mailto')) : htmlspecialchars($href, ENT_QUOTES, 'UTF-8');
			return $attr === '' ? $m[2] : '<a href="' . $attr . '" rel="nofollow ugc noopener" target="_blank">' . $m[2] . '</a>';
		}, $html);
		// An <a> the pass above did not rebuild (no closing tag) goes; its text stays.
		$html = preg_replace('#<a\b(?![^>]*\brel="nofollow ugc noopener")[^>]*>#i', '', $html);
		$html = preg_replace('#<(p|h3|h4|li|blockquote|strong|b|em|i|u)>\s*</\1>#i', '', $html);
		return trim($html);
	}

	/** The text of rich HTML (CSV, SMS, Telegram, Google Sheets, logic, smart tags): block ends and <br> as new lines. */
	public static function rich_plain($html) {
		$s = is_scalar($html) ? (string) $html : '';
		$s = preg_replace('#<\s*br\s*/?>#i', "\n", $s);
		$s = preg_replace('#</?\s*(p|ul|ol|li|h3|h4|blockquote|div)\b[^>]*>#i', "\n", $s);
		$s = function_exists('wp_strip_all_tags') ? wp_strip_all_tags($s, false) : preg_replace('/<[^>]*>/', '', $s);
		$s = html_entity_decode($s, ENT_QUOTES | ENT_HTML5, 'UTF-8');
		$s = str_replace("\xC2\xA0", ' ', $s);
		$s = preg_replace("/[ \t]+\n/", "\n", $s);
		$s = preg_replace("/\n[ \t]*/", "\n", $s);
		$s = preg_replace("/\n{2,}/", "\n", $s);
		return trim($s);
	}

	/** The plain text of a stored answer row: rich (fmt html) as text, any other value as it is. */
	public static function row_text($row) {
		$v = is_array($row) && isset($row['value']) && is_scalar($row['value']) ? (string) $row['value'] : '';
		if (is_array($row) && isset($row['fmt']) && $row['fmt'] === 'html') return self::rich_plain(str_replace('@efb@dq#', '"', $v));
		return $v;
	}

	/**
	 * The submit's check of a rich answer: [ok, html, plain, code]. code: 'raw' (more HTML than rich_raw_cap()),
	 * 'min' / 'max' (milen / mlen on the text, in code points like the browser).
	 */
	public static function rich_check($raw, $f) {
		$raw = is_scalar($raw) ? (string) $raw : '';
		if (strlen($raw) > self::rich_raw_cap($f)) return array(false, '', '', 'raw');
		$html = self::rich_sanitize($raw);
		$plain = self::rich_plain($html);
		$len = function_exists('mb_strlen') ? mb_strlen($plain, 'UTF-8') : strlen($plain);
		if ($len === 0) return array(true, '', '', '');
		$min = (int) self::s($f, 'milen', '0');
		$max = (int) self::s($f, 'mlen', '0');
		if ($min > 0 && $len < $min) return array(false, $html, $plain, 'min');
		if ($max > 0 && $len > $max) return array(false, $html, $plain, 'max');
		return array(true, $html, $plain, '');
	}

	/* ------------------------------------------------------------------ B: sections */

	/**
	 * The fields of each section: section id_ => [field id_, …] (the rows after it in its step, up to the next
	 * section or step, in array order; a repeater group's fields with their group; options and matrix rows are
	 * not fields). Works on associative rows and on stdClass rows.
	 */
	public static function section_members($rows) {
		$out = array();
		$cur = null;
		foreach ((array) $rows as $i => $r) {
			if ($i === 0) continue;
			$t = self::s($r, 'type');
			if ($t === 'step') { $cur = null; continue; }
			if ($t === 'section') { $cur = self::s($r, 'id_'); if ($cur !== '') $out[$cur] = array(); continue; }
			if ($cur === null || $t === 'option' || $t === 'r_matrix') continue;
			$id = self::s($r, 'id_');
			if ($id !== '') $out[$cur][] = $id;
		}
		return $out;
	}

	/**
	 * A section hidden by Conditional Logic hides its fields (spec §11): they join the ignored set and are not
	 * required. $rows: the form as the submit holds it (associative); $ignored: id => true. [rows, ignored].
	 */
	public static function expand_hidden_sections(array $rows, array $ignored) {
		if (!$ignored) return array($rows, $ignored);
		$add = array();
		foreach (self::section_members($rows) as $sid => $ids) {
			if (!isset($ignored[$sid])) continue;
			foreach ($ids as $id) $add[$id] = true;
		}
		if (!$add) return array($rows, $ignored);
		foreach ($rows as $i => $r) {
			if ($i > 0 && is_array($r) && isset($r['id_']) && isset($add[(string) $r['id_']])) $rows[$i]['required'] = false;
		}
		return array($rows, $ignored + $add);
	}

	/* ------------------------------------------------------------------ D: visibility by login / role */

	/** The site's role slugs (wp_roles()); empty outside WordPress. */
	public static function site_roles() {
		if (!function_exists('wp_roles')) return array();
		$r = wp_roles();
		return is_object($r) && method_exists($r, 'get_names') ? array_map('strval', array_keys((array) $r->get_names())) : array();
	}

	/** vis_roles as stored: slugs (sanitize_key), only roles of this site, no duplicates, in the order given. */
	public static function vis_roles_clean($v) {
		$site = self::site_roles();
		$out = array();
		foreach (explode(',', is_scalar($v) ? (string) $v : '') as $p) {
			$k = function_exists('sanitize_key') ? sanitize_key(trim($p)) : preg_replace('/[^a-z0-9_\-]/', '', strtolower(trim($p)));
			if ($k !== '' && in_array($k, $site, true) && !in_array($k, $out, true)) $out[] = $k;
		}
		return $out;
	}

	public static function vis_mode($row) {
		$m = self::s($row, 'vis_mode', 'all');
		return in_array($m, self::VIS_MODES, true) ? $m : 'all';
	}

	/** Whether a form (rows) has a field with a visibility rule. */
	public static function has_vis($rows) {
		foreach ((array) $rows as $i => $r) {
			if ($i > 0 && self::vis_mode($r) !== 'all' && self::s($r, 'type') !== 'step') return true;
		}
		return false;
	}

	/**
	 * Whether a viewer sees a field. $user: a WP_User (default: the current user); $public_view: a cached page's
	 * view (only fields every visitor sees). A rule with no role left ("roles" and an empty list) shows the field
	 * to nobody. The site administrator follows the rule too (spec open question 3).
	 */
	public static function row_visible($row, $user = null, $public_view = false) {
		$mode = self::vis_mode($row);
		if ($mode === 'all' || self::s($row, 'type') === 'step' || in_array(self::s($row, 'id_'), self::VIS_FIXED_IDS, true)) return true;
		if ($public_view) return false;
		if ($user === null && function_exists('wp_get_current_user')) $user = wp_get_current_user();
		$in = is_object($user) && !empty($user->ID);
		if ($mode === 'in') return $in;
		if ($mode === 'out') return !$in;
		if (!$in) return false;
		$roles = isset($user->roles) ? array_map('strval', (array) $user->roles) : array();
		$want = array_filter(array_map('trim', explode(',', self::s($row, 'vis_roles'))), 'strlen');
		return (bool) array_intersect($want, $roles);
	}

	/**
	 * The rows a viewer gets: fields they may not see are removed with their options / matrix rows and, for a
	 * repeater group or a section, the fields it holds. Row 0 and steps stay; a step left without a field gets vis_empty "1" (the page
	 * skips it as a step hidden by logic). Works on associative and stdClass rows; returns [rows, hidden ids].
	 */
	public static function filter_rows_ex($rows, $user = null, $public_view = false) {
		$rows = array_values((array) $rows);
		$hidden = array();
		foreach ($rows as $i => $r) {
			if ($i === 0) continue;
			$t = self::s($r, 'type');
			if ($t === 'option' || $t === 'r_matrix' || $t === 'step') continue;
			if (!self::row_visible($r, $user, $public_view)) $hidden[self::s($r, 'id_')] = true;
		}
		if (!$hidden) return array($rows, array());
		foreach (self::section_members($rows) as $sid => $ids) { // a hidden section hides the fields it holds
			if (isset($hidden[$sid])) foreach ($ids as $id) $hidden[$id] = true;
		}
		foreach ($rows as $i => $r) { // a hidden group hides its fields
			if ($i > 0 && self::s($r, 'group') !== '' && isset($hidden[self::s($r, 'group')])) $hidden[self::s($r, 'id_')] = true;
		}
		$out = array();
		foreach ($rows as $i => $r) {
			if ($i > 0) {
				$id = self::s($r, 'id_');
				if (isset($hidden[$id])) continue;
				$p = self::s($r, 'parent');
				if ($p !== '' && isset($hidden[$p])) { $hidden[$id] = true; continue; }
			}
			$out[] = $r;
		}
		// Steps left without a field.
		$fields_of = array();
		foreach ($out as $i => $r) {
			$t = self::s($r, 'type');
			if ($i > 0 && $t !== 'step' && $t !== 'option' && $t !== 'r_matrix') $fields_of[self::s($r, 'step', '1')] = true;
		}
		foreach ($out as $i => $r) {
			if ($i === 0 || self::s($r, 'type') !== 'step' || isset($fields_of[self::s($r, 'step', '1')])) continue;
			if (is_array($r)) $out[$i]['vis_empty'] = '1'; elseif (is_object($r)) { $c = clone $r; $c->vis_empty = '1'; $out[$i] = $c; }
		}
		return array($out, array_keys($hidden));
	}

	public static function filter_rows($rows, $user = null, $public_view = false) {
		return self::filter_rows_ex($rows, $user, $public_view)[0];
	}

	/**
	 * The submit: the fields the sender may not see are out of the form (not required, never checked) and their
	 * answers are dropped (a row whose id_, id_ob or rg is such a field or one of its options). Login / register
	 * and payment rows have no field id and pass. [fields, rows, number of rows dropped].
	 */
	public static function filter_submission(array $fields, array $rows, $user = null, $public_view = false) {
		list($kept, $hidden) = self::filter_rows_ex($fields, $user, $public_view);
		if (!$hidden) return array($fields, $rows, 0);
		$h = array_flip($hidden);
		$out = array();
		$dropped = 0;
		foreach ($rows as $r) {
			if (is_array($r) && ((isset($r['id_']) && is_scalar($r['id_']) && isset($h[(string) $r['id_']])) || (isset($r['id_ob']) && is_scalar($r['id_ob']) && isset($h[(string) $r['id_ob']])) || (isset($r['rg']) && is_scalar($r['rg']) && isset($h[(string) $r['rg']])))) { $dropped++; continue; }
			$out[] = $r;
		}
		foreach ($kept as $i => $r) if (is_array($r) && isset($r['vis_empty'])) unset($kept[$i]['vis_empty']);
		return array($kept, $out, $dropped);
	}

	/** efb_admin_localize_vars: efb_var.roles = [{slug, name}] (translated names) for the builder's visibility control. */
	public static function localize_vars($vars, $context = '') {
		if (!is_array($vars)) return $vars;
		$roles = array();
		if (function_exists('wp_roles')) {
			foreach ((array) wp_roles()->get_names() as $slug => $name) $roles[] = array('slug' => (string) $slug, 'name' => function_exists('translate_user_role') ? translate_user_role($name) : (string) $name);
		}
		$vars['roles'] = $roles;
		return $vars;
	}

	/* ------------------------------------------------------------------ E: typed signature */

	/* E: the typed signature's behaviour (signing mode, font of a name, the row's keys on submit) is the Typed Signature
	 * add-on's (AdnTSG, SPLIT §7, Emsfb\Typed_Signature_Efb). The display of a stored typed signature stays here. */

	/** The typed name of a stored signature row ('' for a drawn one). */
	public static function sig_text_of($row) {
		return self::s($row, 'sig_mode') === 'type' ? self::s($row, 'sig_text') : '';
	}

	/* ------------------------------------------------------------------ forms/{id}/schema */

	/** A field's F10 facts for an assistant: a rich textarea takes plain text; a signature is the visitor's own. */
	public static function schema_field($e, $r, $form_id = 0) {
		if (!is_array($e) || !is_array($r)) return $e;
		$t = isset($r['type']) ? (string) $r['type'] : '';
		if ($t === 'textarea' && self::rich_on($r)) {
			$e['rich_text'] = true;
			if (!isset($e['fill']) || !is_array($e['fill'])) $e['fill'] = array();
			$e['fill']['format'] = 'plain text (the page keeps the formatting the visitor adds; the server keeps only the allowed tags)';
		}
		if ($t === 'esign') {
			// SPLIT §7: the typing modes come from the Typed Signature add-on (efb_form_schema_field); drawn otherwise.
			$e['assistant'] = 'visitor_only';
			$e['signature'] = array('modes' => array('draw'), 'note' => 'the visitor draws or types the signature; an assistant never signs');
		}
		return $e;
	}

	/**
	 * The form's F10 facts: the review page (the visitor confirms; an assistant never presses Confirm) and the sections
	 * (title, step, whether they fold, the fields they hold) as this viewer gets them. A repeater group's children
	 * list keeps only the fields of the schema (a child hidden from this viewer is not named).
	 */
	public static function schema($out, $form_id = 0, $context = array()) {
		if (!is_array($out) || !isset($out['fields']) || !class_exists('\\Emsfb\\Calendar_Rest')) return $out;
		$rows = Calendar_Rest::form_rows((int) $form_id);
		if (!is_array($rows)) return $out;
		if (self::has_vis($rows)) $rows = self::filter_rows($rows);
		$present = array();
		foreach ($out['fields'] as $e) if (is_array($e) && isset($e['id'])) $present[(string) $e['id']] = true;
		foreach ($out['fields'] as $k => $e) {
			if (is_array($e) && isset($e['repeater']['children']) && is_array($e['repeater']['children'])) {
				$out['fields'][$k]['repeater']['children'] = array_values(array_filter($e['repeater']['children'], function ($c) use ($present) { return isset($present[(string) $c]); }));
			}
		}
		if (self::review_on($rows)) $out['review'] = array('on' => true, 'how' => 'after the last step the page lists the answers; the visitor edits or presses Confirm (an assistant never confirms for the visitor)');
		$sections = array();
		$members = self::section_members($rows);
		foreach ($rows as $i => $r) {
			if ($i < 1 || self::s($r, 'type') !== 'section') continue;
			$id = self::s($r, 'id_');
			$coll = !isset($r['collapsible']) || $r['collapsible'] === '' || self::on($r['collapsible']);
			$sections[] = array('id' => $id, 'title' => self::s($r, 'name'), 'step' => (int) self::s($r, 'step', '1'), 'collapsible' => $coll, 'collapsed' => $coll && self::on(self::s($r, 'collapsed')),
				'fields' => array_values(array_filter(isset($members[$id]) ? $members[$id] : array(), function ($f) use ($present) { return isset($present[(string) $f]); })),
				'fill' => 'a field in a closed section opens it (EFBLayout.revealSection(field_id, form_id))', 'assistant' => 'none');
		}
		if ($sections) $out['sections'] = $sections;
		return $out;
	}

	/* ------------------------------------------------------------------ save (between keys) */

	/**
	 * After sanitize_obj_msg_efb(), on every save (builder, AutoPilot, import): the F10 keys only where they mean
	 * something (rich_text on a textarea, esign_* on an e-signature, vis_* on a field — not a step, an option or the
	 * login / register rows, vis_roles only with "roles"), a section never in a repeater group, no review page on a
	 * form paid inside the form. $old_rows (the stored form) adds a security_changes warning when who sees a field
	 * changed or a rich editor was switched on (SitePilot preview). [rows, warnings]; never refuses the save.
	 */
	public static function normalize_rows(array $rows, $old_rows = null) {
		$warnings = array();
		$old = array();
		foreach ((array) $old_rows as $i => $r) if ($i > 0 && is_array($r) && isset($r['id_'])) $old[(string) $r['id_']] = $r;
		$changes = array();
		foreach ($rows as $i => $r) {
			if ($i < 1 || !is_array($r)) continue;
			$t = isset($r['type']) ? (string) $r['type'] : '';
			if ($t !== 'textarea') unset($rows[$i]['rich_text'], $rows[$i]['rich_toolbar']);
			if ($t !== 'esign') unset($rows[$i]['esign_mode'], $rows[$i]['esign_font']);
			if (in_array($t, array('step', 'option', 'r_matrix'), true) || in_array(isset($r['id_']) ? (string) $r['id_'] : '', self::VIS_FIXED_IDS, true)) unset($rows[$i]['vis_mode'], $rows[$i]['vis_roles']);
			if (isset($rows[$i]['vis_roles']) && (!isset($rows[$i]['vis_mode']) || $rows[$i]['vis_mode'] !== 'roles')) unset($rows[$i]['vis_roles']);
			if ($t === 'section' && isset($r['group']) && $r['group'] !== '') unset($rows[$i]['group']);
			$id = isset($r['id_']) ? (string) $r['id_'] : '';
			if ($id === '' || $old_rows === null) continue;
			$o = isset($old[$id]) ? $old[$id] : array();
			foreach (array('vis_mode' => 'all', 'vis_roles' => '', 'rich_text' => '0') as $k => $d) {
				$a = isset($o[$k]) && is_scalar($o[$k]) && $o[$k] !== '' ? (string) $o[$k] : $d;
				$b = isset($rows[$i][$k]) && is_scalar($rows[$i][$k]) && $rows[$i][$k] !== '' ? (string) $rows[$i][$k] : $d;
				if ($a === $b || ($k === 'rich_text' && $b !== '1')) continue;
				$changes[] = array('field' => $id, 'key' => $k, 'from' => $a, 'to' => $b);
			}
		}
		if (isset($rows[0]) && is_array($rows[0]) && isset($rows[0]['review_before_submit']) && $rows[0]['review_before_submit'] === '1' && self::legacy_payment($rows)) {
			$rows[0]['review_before_submit'] = '0';
			$warnings[] = array('code' => 'layout_review_payment', 'm' => self::t('rvwNotPayment', 'Review is not available on forms that take a payment inside the form (Stripe, PayPal, PersiaPay).'));
		}
		if ($changes) $warnings[] = array('code' => 'layout_security_change', 'changes' => $changes, 'm' => self::t('layoutSecChange', 'Who sees a field, or a rich text editor, has changed on this form.'));
		return array($rows, $warnings);
	}

	private static function t($key, $fallback) {
		$fn = function_exists('get_efbFunction') ? get_efbFunction() : null;
		if ($fn && method_exists($fn, 'text_efb')) {
			$l = $fn->text_efb(array($key));
			if (is_array($l) && isset($l[$key]) && is_string($l[$key]) && $l[$key] !== '') return $l[$key];
		}
		return $fallback;
	}

	/* ------------------------------------------------------------------ capabilities */

	/** efb_capabilities: setting:review_before_submit and capabilities.layout (SitePilot manifest features.layout). */
	public static function capabilities($out, $args = array()) {
		if (!is_array($out) || !isset($out['features']) || !is_array($out['features'])) return $out;
		$package = isset($out['plan']['package_type']) ? (int) $out['plan']['package_type'] : null;
		$entry = function ($part, $kind, $keys, $label, $extra = array()) use ($package) {
			$need = self::need($part);
			$ok = $need === 'free' ? true : (class_exists('\\Emsfb\\Builder_Api') ? Builder_Api::plan_at_least($need, $package) : false);
			return array_merge(array('kind' => $kind, 'scope' => $kind === 'field' ? 'field' : 'form', 'package' => $need, 'addon' => null, 'available_now' => $ok, 'reason' => $ok ? null : 'plan',
				'prompt_state' => $need === 'free_plus' ? 3 : ($need === 'pro' ? 1 : 0),
				'message_key' => $need === 'free_plus' ? 'thisFeatureAvailableFreePlusPro' : ($need === 'pro' ? 'proUnlockMsg' : null),
				'form_keys' => $keys, 'label_key' => $label), $extra);
		};
		$out['features']['setting:review_before_submit'] = $entry('review', 'setting', array('review_before_submit', 'review_hide_empty', 'review_title'), 'rvwBfrSbmt', array('not_on' => 'legacy payment forms (stripe, paypal, persiaPay fields)'));
		$out['features']['setting:vis_mode'] = $entry('vis', 'setting', array('vis_mode', 'vis_roles'), 'visFor', array('scope' => 'field', 'enforced' => 'always (the server applies a saved rule on every plan)'));
		$out['features']['setting:rich_text'] = $entry('rich_text', 'setting', array('rich_text', 'rich_toolbar'), 'richText', array('types' => array('textarea')));
		$out['features']['field:section'] = array_merge(isset($out['features']['field:section']) && is_array($out['features']['field:section']) ? $out['features']['field:section'] : array(),
			$entry('section', 'field', array('name', 'message', 'collapsible', 'collapsed', 'icon', 'el_text_color', 'el_text_size', 'section_accordion'), 'section'), array('pii_default' => null));
		$out['layout'] = array(
			'v'         => 1,
			'form_keys' => self::FORM_KEYS,
			'field_keys' => self::FIELD_KEYS,
			'defaults'  => self::DEFAULTS,
			'limits'    => self::LIMITS,
			'review'    => array('page' => 'after the last step, before Send; the visitor confirms', 'not_on' => 'forms with a stripe / paypal / persiaPay field',
				'amount' => 'payment-framework forms: the server\'s amount (POST payments/quote), never the page\'s', 'assistant' => 'an assistant never presses Confirm for the visitor'),
			'rich_text' => array('keys' => array('rich_text', 'rich_toolbar'), 'toolbars' => self::RICH_TOOLBARS, 'tags' => self::RICH_TAGS, 'link_protocols' => array('http', 'https', 'mailto'),
				'answer' => 'value = cleaned HTML, fmt = "html" (written by the server); length limits count the text', 'assistant' => 'type plain text; the server keeps only the allowed tags'),
			'visibility' => array('keys' => array('vis_mode', 'vis_roles'), 'modes' => self::VIS_MODES, 'roles' => self::site_roles(), 'enforced' => 'render, forms/{id}/schema, every submit route (a hidden field is never required, its answer dropped)',
				'cache' => 'a page with such a form is not page-cached; a page the site caches anyway shows only the fields every visitor sees', 'administrators' => 'follow the rule like any role'),
			'section'   => array('type' => 'section', 'keys' => array('collapsible', 'collapsed'), 'form_key' => 'section_accordion', 'members' => 'the fields after it in its step, up to the next section or step (never stored)',
				'answer' => 'none (no row is sent)', 'not_in' => 'a repeater group (a group may sit inside a section)', 'assistant' => 'none'),
			'plan'      => array_map(array(__CLASS__, 'need'), array_combine(array_keys(self::PLAN), array_keys(self::PLAN))),
			'spec'      => 'F10',
		);
		return $out;
	}
}
