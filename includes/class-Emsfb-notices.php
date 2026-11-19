<?php
/**
 * B4 (admin UX): one notice manager for Easy Form Builder (UX-01), and the phrases of the admin screens' shared parts
 * that admin-efb.js draws (efb_var.text: the builder's field settings panel, BLD-01).
 *
 * UX-01. Ten admin_notices sources stacked four notices on every EFB screen (a whole phone screen of them), one could not
 * be dismissed and users learned to ignore them all. Now:
 *  - every source registers here (Notices::add(id, args)): its render callback (its own markup and its own "dismiss
 *    until it changes"), a priority, a severity and the screens it is for; a source registered straight on admin_notices
 *    by another file of the plugin or an add-on is taken in too (absorb()), its priority from its severity;
 *  - on an EFB screen one notice is shown, the most important; the others are in the "Notices" list (a bell with their
 *    count in the EFB header, or above the screen where there is no header), each with its own controls; on the form
 *    builder (the Create screen, or a form opened in the panel) none is above the canvas: all are in the list;
 *  - every notice can be hidden for 7 days per user ("Remind me in 7 days", user meta emsfb_notice_snooze); its own
 *    dismissal (until it changes) is kept;
 *  - severity: "error" only for payments that fail now (an add-on the payment framework cannot load, payment requests
 *    failing on the server); payment readiness is a badge per form in the forms list and a summary on Payments;
 *  - on other screens (the dashboard, Posts …) each source shows where it showed before, with "Remind me in 7 days".
 *
 * Loaded by Addon_Catalog::register() and by each source file (require_once), so it does not depend on the order of
 * class-Emsfb.php.
 *
 * @package Emsfb
 */

namespace Emsfb;

if (!defined('ABSPATH')) {
	exit;
}

class Notices {

	const VERSION = 1;
	const META_SNOOZE = 'emsfb_notice_snooze';
	const SNOOZE_DAYS = 7;
	const NONCE = 'efb_notices';
	const AJAX = 'efb_notice_snooze';

	/** Priority by severity for a notice taken in from admin_notices (absorb()). */
	const SEVERITY_PRIORITY = array('error' => 88, 'warning' => 50, 'info' => 20, 'success' => 20);

	private static $registered = false;

	/** @var array<string, array> id => {priority, severity, screens, render, snooze} */
	private static $sources = array();

	/** @var array<string, string> what the last render() printed: id => top | list | screen (tests, e2e). */
	public static $last = array();

	public static function register() {
		if (self::$registered || !function_exists('add_filter')) return;
		self::$registered = true;
		add_filter('efb_admin_localize_vars', array(__CLASS__, 'localize_vars'), 40, 2);
		add_action('admin_notices', array(__CLASS__, 'render'), -99);
		add_action('wp_ajax_' . self::AJAX, array(__CLASS__, 'ajax_snooze'));
	}

	/**
	 * A notice source. args:
	 *  render   callable that prints the notice (its own markup; nothing when there is nothing to say);
	 *  priority int, higher first (default 50), or a callable that answers it when the notice is shown;
	 *  severity error | warning | info | success (what the markup says when not given);
	 *  screens  'efb' (the plugin's screens), 'admin' (every screen; the default: the callback decides) or a callable($screen_id);
	 *  snooze   false when the notice has its own "later" (default true).
	 */
	public static function add($id, array $args) {
		self::register();
		$id = preg_replace('/[^a-z0-9_]/', '', strtolower((string) $id));
		if ($id === '' || !isset($args['render']) || !is_callable($args['render'])) return false;
		self::$sources[$id] = array(
			'render'   => $args['render'],
			'priority' => isset($args['priority']) ? (is_callable($args['priority']) && !is_string($args['priority']) ? $args['priority'] : (int) $args['priority']) : 50,
			'severity' => isset($args['severity']) && isset(self::SEVERITY_PRIORITY[$args['severity']]) ? $args['severity'] : '',
			'screens'  => isset($args['screens']) ? $args['screens'] : 'admin',
			'snooze'   => !isset($args['snooze']) || $args['snooze'],
		);
		return true;
	}

	/** @return array<string, array> the registered sources (tests). */
	public static function sources() {
		return self::$sources;
	}

	/** Tests only. */
	public static function reset() {
		self::$sources = array();
		self::$last = array();
	}

	/** Whether a screen id is one of the plugin's screens (toplevel_page_Emsfb, easy-form-builder_page_Emsfb_*). */
	public static function is_efb_screen($screen_id) {
		return stripos((string) $screen_id, 'emsfb') !== false;
	}

	/** The form builder's own screen (Create): no notice above the canvas. */
	public static function is_builder_screen($screen_id) {
		return stripos((string) $screen_id, 'emsfb_create') !== false;
	}

	private static function screen_id() {
		$s = function_exists('get_current_screen') ? get_current_screen() : null;
		return $s && isset($s->id) ? (string) $s->id : '';
	}

	/** Snoozed ids of the current user: id => until (only the ones still running). */
	public static function snoozed($user_id = 0) {
		$user_id = $user_id ? (int) $user_id : (function_exists('get_current_user_id') ? get_current_user_id() : 0);
		if (!$user_id || !function_exists('get_user_meta')) return array();
		$m = get_user_meta($user_id, self::META_SNOOZE, true);
		$now = time();
		$out = array();
		foreach (is_array($m) ? $m : array() as $id => $until) if (is_string($id) && (int) $until > $now) $out[$id] = (int) $until;
		return $out;
	}

	/**
	 * Easy Form Builder's other admin_notices callbacks (a file of the plugin or an add-on that does not register here)
	 * become sources too, so they wait their turn like the rest. Not on a screen where they were taken off already: an
	 * add-on's own settings screen calls efb_addon_screen_own_notices(), which takes this manager off as well.
	 */
	public static function absorb() {
		global $wp_filter;
		if (empty($wp_filter['admin_notices']) || !is_object($wp_filter['admin_notices']) || !class_exists('\\ReflectionFunction')) return 0;
		$root = defined('EMSFB_PLUGIN_DIRECTORY') && function_exists('wp_normalize_path') ? wp_normalize_path(EMSFB_PLUGIN_DIRECTORY) : "\0";
		$n = 0;
		foreach ($wp_filter['admin_notices']->callbacks as $prio => $cbs) {
			foreach ($cbs as $key => $cb) {
				$f = $cb['function'];
				if (is_array($f) && ($f[0] === __CLASS__ || $f[0] === '\\' . __CLASS__)) continue;
				try {
					$r = is_array($f) ? new \ReflectionMethod($f[0], $f[1]) : (is_string($f) && strpos($f, '::') ? new \ReflectionMethod($f) : new \ReflectionFunction($f));
				} catch (\ReflectionException $e) { continue; }
				$file = function_exists('wp_normalize_path') ? wp_normalize_path((string) $r->getFileName()) : (string) $r->getFileName();
				$ns = $r instanceof \ReflectionMethod ? $r->getDeclaringClass()->getName() : $r->getNamespaceName();
				if (stripos($ns, 'Emsfb') !== 0 && strpos($file, $root) !== 0) continue;
				remove_action('admin_notices', $f, $prio);
				// A stable id (the snooze is kept across requests): the method's name, or a closure's file and line.
				$sig = is_array($f) ? (is_object($f[0]) ? get_class($f[0]) : (string) $f[0]) . '::' . $f[1] : (is_string($f) ? $f : $file . ':' . $r->getStartLine());
				self::$sources['x_' . substr(md5($sig), 0, 10)] = array('render' => $f, 'priority' => 0, 'severity' => '', 'screens' => 'admin', 'snooze' => true, 'absorbed' => true);
				$n++;
			}
		}
		return $n;
	}

	/** The severity the markup says (notice-error / -warning / -info / -success), or ''. */
	public static function severity_of($html) {
		if (preg_match('/\bnotice-(error|warning|info|success)\b/', (string) $html, $m)) return $m[1];
		return '';
	}

	/** Whether the printed output holds a notice (a .notice element), not only a script or a style. */
	public static function has_notice($html) {
		return (bool) preg_match('/<div\b[^>]*\bclass=(["\'])[^"\']*\bnotice\b/i', (string) $html);
	}

	/**
	 * Marks the notices of a source: data-efb-nm (the snooze control and the list find them); $inline adds WordPress'
	 * "inline" class, so common.js does not pull them out of the notice list (it moves every other .notice after the
	 * screen's first heading).
	 */
	public static function mark($html, $id, $inline) {
		return preg_replace_callback('/<div\b([^>]*?)\bclass=(["\'])([^"\']*\bnotice\b[^"\']*)\2/i', function ($m) use ($id, $inline) {
			$cls = $m[3] . ($inline && !preg_match('/\binline\b/', $m[3]) ? ' inline' : '');
			return '<div' . $m[1] . 'data-efb-nm="' . esc_attr($id) . '" class=' . $m[2] . $cls . $m[2];
		}, (string) $html);
	}

	/**
	 * The notices for a screen, most important first: [{id, html, severity, priority, snooze}], plus what the callbacks
	 * printed that is not a notice (scripts, styles) in $loose.
	 */
	public static function collect($screen_id, &$loose = null) {
		$loose = '';
		$snoozed = self::snoozed();
		$items = array();
		foreach (self::$sources as $id => $s) {
			$sc = $s['screens'];
			if ($sc === 'efb' && !self::is_efb_screen($screen_id)) continue;
			if (is_callable($sc) && !is_string($sc) && !call_user_func($sc, $screen_id)) continue;
			ob_start();
			try { call_user_func($s['render']); } catch (\Throwable $e) { /* a broken notice must not break the screen */ }
			$html = trim((string) ob_get_clean());
			if ($html === '') continue;
			if (!self::has_notice($html)) { $loose .= $html; continue; }
			if (isset($snoozed[$id]) && $s['snooze']) continue;
			$sev = $s['severity'] !== '' ? $s['severity'] : (self::severity_of($html) ?: 'info');
			$prio = !empty($s['absorbed']) ? self::SEVERITY_PRIORITY[$sev] : (is_callable($s['priority']) ? (int) call_user_func($s['priority']) : (int) $s['priority']);
			// An error is a payment failing now (the one use of "error", UX-01): it comes before everything else.
			if ($sev === 'error') $prio = max($prio, 90);
			$items[] = array('id' => $id, 'html' => $html, 'severity' => $sev, 'priority' => $prio, 'snooze' => $s['snooze']);
		}
		// Most important first; equal priorities keep their order of registration (usort is not stable before PHP 8).
		foreach ($items as $i => $it) $items[$i]['n'] = $i;
		usort($items, function ($a, $b) { return $b['priority'] <=> $a['priority'] ?: $a['n'] <=> $b['n']; });
		return $items;
	}

	/** admin_notices (-99): every source of the plugin through here, one notice on the plugin's screens. */
	public static function render() {
		self::absorb();
		$sid = self::screen_id();
		$loose = '';
		$items = self::collect($sid, $loose);
		self::$last = array();
		echo $loose; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- the sources' own escaped scripts
		if (!$items) return;
		$efb = self::is_efb_screen($sid);
		if (!$efb) {
			// Other screens: each notice where its source put it, as before, with "Remind me in 7 days".
			foreach ($items as $it) {
				self::$last[$it['id']] = 'screen';
				echo self::mark($it['html'], $it['id'], false); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- the sources' own escaped markup
			}
			self::script(false);
			return;
		}
		$builder = self::is_builder_screen($sid);
		$top = $builder ? null : array_shift($items);
		$t = self::texts();
		echo '<div class="efb efb-nm" id="efbNoticeCenter" data-efb-nm-builder="' . ($builder ? '1' : '0') . '">';
		if ($top) {
			self::$last[$top['id']] = 'top';
			echo '<div class="efb efb-nm-top">' . self::mark($top['html'], $top['id'], true) . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- the sources' own escaped markup
		}
		$n = count($items);
		echo '<div class="efb efb-nm-bar"' . ($n ? '' : ' hidden') . '><button type="button" class="efb efb-nm-toggle" id="efbNmToggle" aria-expanded="false" aria-controls="efbNmList">'
			. '<span class="dashicons dashicons-bell" aria-hidden="true"></span> <span class="efb efb-nm-label">' . esc_html($t['nmNotices']) . '</span> <span class="efb efb-nm-count">' . esc_html(number_format_i18n($n)) . '</span></button></div>';
		echo '<div class="efb efb-nm-list" id="efbNmList" role="region" aria-labelledby="efbNmToggle" hidden>';
		foreach ($items as $it) {
			self::$last[$it['id']] = 'list';
			echo self::mark($it['html'], $it['id'], true); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- the sources' own escaped markup
		}
		echo '</div></div>';
		self::script(true);
	}

	/** The snooze control on each notice, the list's toggle, the bell in the EFB header and the builder (no notice above it). */
	private static function script($center) {
		$t = self::texts();
		$data = array(
			'ajax'   => admin_url('admin-ajax.php'),
			'nonce'  => wp_create_nonce(self::NONCE),
			'action' => self::AJAX,
			'snooze' => $t['nmSnooze'],
			'done'   => $t['nmSnoozed'],
			'ids'    => array_keys(array_filter(self::$sources, function ($s) { return $s['snooze']; })),
		);
		echo '<script>(function(){var D=' . wp_json_encode($data) . ';'
			. 'function dec(n){var b=n.querySelector(".efb-nm-snooze-row");if(b||D.ids.indexOf(n.getAttribute("data-efb-nm"))<0)return;var p=document.createElement("p");p.className="efb-nm-snooze-row";var x=document.createElement("button");x.type="button";x.className="button-link efb-nm-snooze";x.textContent=D.snooze;x.setAttribute("data-efb-nm-snooze",n.getAttribute("data-efb-nm"));p.appendChild(x);n.appendChild(p);}'
			. 'var all=document.querySelectorAll("[data-efb-nm]");for(var i=0;i<all.length;i++)dec(all[i]);'
			. 'var live=document.getElementById("efbNmLive");if(!live){live=document.createElement("div");live.id="efbNmLive";live.className="screen-reader-text";live.setAttribute("role","status");live.setAttribute("aria-live","polite");document.body?document.body.appendChild(live):document.documentElement.appendChild(live);}'
			. 'function count(){var l=document.getElementById("efbNmList");if(!l)return;var ids={},ch=l.querySelectorAll("[data-efb-nm]");for(var k=0;k<ch.length;k++)ids[ch[k].getAttribute("data-efb-nm")]=1;var n=Object.keys(ids).length;var cs=document.querySelectorAll(".efb-nm-count");for(var j=0;j<cs.length;j++)cs[j].textContent=String(n);var bars=document.querySelectorAll(".efb-nm-bar, .efb-nm-bell");for(var q=0;q<bars.length;q++)bars[q].hidden=n===0;if(!n){l.hidden=true;}}'
			. 'var L=document.getElementById("efbNmList");if(L&&window.MutationObserver)new MutationObserver(count).observe(L,{childList:true,subtree:true});'
			. 'document.addEventListener("click",function(e){var s=e.target.closest&&e.target.closest("[data-efb-nm-snooze]");if(s){var id=s.getAttribute("data-efb-nm-snooze");s.disabled=true;var f=new FormData();f.append("action",D.action);f.append("nonce",D.nonce);f.append("id",id);fetch(D.ajax,{method:"POST",credentials:"same-origin",body:f}).then(function(r){return r.json();}).then(function(j){if(!j||!j.success){s.disabled=false;return;}var ns=document.querySelectorAll("[data-efb-nm=\""+id+"\"]");for(var k=0;k<ns.length;k++)ns[k].remove();live.textContent=D.done;count();},function(){s.disabled=false;});return;}'
			. 'var t=e.target.closest&&e.target.closest(".efb-nm-toggle");if(t){var l=document.getElementById("efbNmList");if(!l)return;var o=t.getAttribute("aria-expanded")!=="true";var ts=document.querySelectorAll(".efb-nm-toggle");for(var k=0;k<ts.length;k++)ts[k].setAttribute("aria-expanded",o?"true":"false");l.hidden=!o;if(o){var f=l.querySelector("a[href],button,input,[tabindex]");if(f)f.focus();}}});'
			. 'document.addEventListener("keydown",function(e){if(e.key!=="Escape")return;var l=document.getElementById("efbNmList");if(!l||l.hidden||!l.contains(document.activeElement))return;l.hidden=true;var ts=document.querySelectorAll(".efb-nm-toggle");for(var k=0;k<ts.length;k++)ts[k].setAttribute("aria-expanded","false");var t=document.querySelector(".efb-nm-bell .efb-nm-toggle")||document.getElementById("efbNmToggle");if(t)t.focus();});'
			. ($center ? 'window.efbNoticeCountEfb=count;' : '')
			. '})();</script>';
	}

	/** wp_ajax_efb_notice_snooze: "Remind me in 7 days" (this user, this notice). */
	public static function ajax_snooze() {
		if (!check_ajax_referer(self::NONCE, 'nonce', false) || !is_user_logged_in()) wp_send_json_error(array('m' => 'forbidden'), 403);
		$id = isset($_POST['id']) ? preg_replace('/[^a-z0-9_]/', '', strtolower((string) wp_unslash($_POST['id']))) : '';
		if ($id === '' || strlen($id) > 40) wp_send_json_error(array('m' => 'bad_request'), 400);
		$uid = get_current_user_id();
		$m = self::snoozed($uid);
		$m[$id] = time() + self::SNOOZE_DAYS * DAY_IN_SECONDS;
		update_user_meta($uid, self::META_SNOOZE, $m);
		wp_send_json_success(array('id' => $id, 'until' => $m[$id]));
	}

	/** The phrases of these parts (efb_var.text keys for admin-efb.js; the notice manager's own words). */
	public static function texts() {
		$days = number_format_i18n(self::SNOOZE_DAYS);
		return array(
			/* translators: BLD-01: the description of the "Done" button of the form builder's field settings panel (read by screen readers and shown as a tooltip) */
			'sbDoneTip' => __('Closes the settings. Your changes are already in the form; Save in the toolbar stores it.', 'easy-form-builder'),
			/* translators: UX-01: the button in the Easy Form Builder header that opens the list of the plugin's other notices (a number follows it) */
			'nmNotices' => __('Notices', 'easy-form-builder'),
			/* translators: UX-01: a button under an Easy Form Builder notice; %s: a number of days (7) */
			'nmSnooze'  => sprintf(_n('Remind me in %s day', 'Remind me in %s days', self::SNOOZE_DAYS, 'easy-form-builder'), $days),
			/* translators: UX-01: said to screen-reader users after "Remind me in 7 days"; %s: a number of days (7) */
			'nmSnoozed' => sprintf(_n('Hidden for %s day.', 'Hidden for %s days.', self::SNOOZE_DAYS, 'easy-form-builder'), $days),
		);
	}

	/** efb_admin_localize_vars: efb_var.text gets the phrases above (escaped like the other phrases of efb_var.text). */
	public static function localize_vars($vars, $context = '') {
		if (!is_array($vars)) return $vars;
		if (isset($vars['text']) && is_object($vars['text'])) {
			foreach (self::texts() as $k => $v) if (!isset($vars['text']->$k)) $vars['text']->$k = esc_html($v);
			return $vars;
		}
		$text = isset($vars['text']) && is_array($vars['text']) ? $vars['text'] : array();
		foreach (self::texts() as $k => $v) if (!isset($text[$k])) $text[$k] = esc_html($v);
		$vars['text'] = $text;
		return $vars;
	}
}
