<?php
/**
 * F07: file upload fields that hold several files, the camera, image resizing in the
 * browser, and where files are kept (docs/feature-gaps/F07-file-upload.fa.md).
 *
 * Upload_Guard keeps the policy that decides whether a file may be written at all (size,
 * sniffed type, quota, the pending ledger, upload tokens). This class holds what F07 adds
 * around it:
 *
 *  - the field keys (max_files, min_files, max_total_mb, capture, img_max_px, img_quality,
 *    file_store) and the form key email_attach: one save rule (sanitize_key()), shared with
 *    the browser twin EFBUpload.rules (public/assets/js/upload-efb.js), and one effective
 *    configuration per field (config()) with the plan applied, used by every renderer, the
 *    quota and every submit endpoint;
 *  - the per-field check of a submission (count, minimum, total size) the submit handler runs;
 *  - the storage modes: the uploads folder (as before), the Media Library (attachments
 *    registered when the entry is stored, private, never listed by /wp/v2/media) and a
 *    private folder (uploads/efb-private/YYYY/MM, deny-all .htaccess, reached only through
 *    the admin download action or a signed, expiring link in e-mails and exports);
 *  - the registry entries, the forms/{id}/schema entry and the admin screen variables.
 *
 * Every key is optional; a field without them behaves exactly as before (one file, the
 * uploads folder).
 */

namespace Emsfb;

if (!defined('ABSPATH')) {
	exit;
}

class Uploads {

	/** Field keys (flat scalars on file / dadfile rows). */
	const FIELD_KEYS = array('max_files', 'min_files', 'max_total_mb', 'capture', 'img_max_px', 'img_quality', 'file_store');

	/** Form key (row 0). */
	const FORM_KEYS = array('email_attach');

	/** Types that may hold several files. The recorders keep one recording per field. */
	const MULTI_TYPES = array('file', 'dadfile');

	/** Every upload field type. */
	const UPLOAD_TYPES = array('file', 'dadfile', 'audio_recorder', 'video_recorder', 'screen_recorder');

	/** Builder ceiling of max_files (filter emsfb_upload_max_files_cap). */
	const DEFAULT_MAX_FILES_CAP = 20;

	/** Private folder name under uploads (and the marker of a private file's address). */
	const PRIVATE_DIR = 'efb-private';

	/** Admin AJAX actions. */
	const DOWNLOAD_ACTION = 'efb_file_get_Emsfb';
	const PROBE_ACTION = 'efb_private_dir_probe_Emsfb';
	const PROBE_OPTION = 'emsfb_private_dir_probe';

	/** Public REST route of the signed links (e-mails, personal-data exports). */
	const SIGNED_ROUTE = 'files/signed';

	/** Per-IP requests per minute on the signed route. */
	const SIGNED_PER_MINUTE = 30;

	/** Default ceiling of the files attached to the owner's e-mail (bytes). */
	const EMAIL_ATTACH_MAX = 10485760;

	/** Stable refusal codes of a submission (answered as efb_file_<code> with field_id). */
	const CODES = array('max_files', 'min_files', 'max_total', 'not_owned', 'invalid');

	/** Plan of each part (F07 §13; the owner's decision is recorded in the spec). */
	const PLAN = array(
		'multi'   => 'free_plus',
		'resize'  => 'free_plus',
		'media'   => 'free_plus',
		'private' => 'free',
		'capture' => 'free',
		'attach'  => 'pro',
	);

	/** Presets whose files the browser may resize / that may use the camera. */
	const RESIZE_PRESETS = array('image', 'allformat', 'customize');
	const CAPTURE_PRESETS = array('image', 'media');

	public static function register() {
		if (!function_exists('add_action')) return;
		add_action('rest_api_init', array(__CLASS__, 'routes'));
		add_action('wp_ajax_' . self::DOWNLOAD_ACTION, array(__CLASS__, 'ajax_download'));
		add_action('wp_ajax_' . self::PROBE_ACTION, array(__CLASS__, 'ajax_probe'));
		add_filter('efb_capabilities', array(__CLASS__, 'capabilities'), 23, 2);
		add_filter('efb_form_schema_field', array(__CLASS__, 'schema_field'), 10, 3);
		add_filter('efb_admin_localize_vars', array(__CLASS__, 'localize_vars'), 20, 2);
	}

	public static function routes() {
		register_rest_route('Emsfb/v1', self::SIGNED_ROUTE, array(
			'methods'             => 'GET',
			'callback'            => array(__CLASS__, 'rest_signed'),
			'permission_callback' => '__return_true',
		));
	}

	/* ------------------------------------------------------------------ keys */

	public static function max_files_cap() {
		$cap = function_exists('apply_filters') ? (int) apply_filters('emsfb_upload_max_files_cap', self::DEFAULT_MAX_FILES_CAP) : self::DEFAULT_MAX_FILES_CAP;
		return max(1, min(100, $cap));
	}

	public static function is_key($k) {
		return in_array((string) $k, self::FIELD_KEYS, true) || in_array((string) $k, self::FORM_KEYS, true);
	}

	/** Persian / Arabic-Indic digits and separators to ASCII. */
	public static function ascii_number($v) {
		$v = is_scalar($v) ? trim((string) $v) : '';
		return strtr($v, array(
			'۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4', '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
			'٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4', '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
			'٫' => '.', '،' => '', ',' => '',
		));
	}

	/**
	 * The stored value of one F07 key ('' = not set, the default). The browser twin is
	 * EFBUpload.rules.sanitizeKey(); tests/test-upload-keys-parity.js runs both on the same vectors.
	 */
	public static function sanitize_key($k, $v) {
		if (is_bool($v)) $v = $v ? '1' : '';
		if (!is_scalar($v) && $v !== null) return '';
		switch ((string) $k) {
			case 'max_files':
				$n = self::ascii_number($v);
				if (!preg_match('/^[0-9]{1,6}$/', $n) || (int) $n < 1) return '';
				return (string) min(self::max_files_cap(), (int) $n);
			case 'min_files':
				$n = self::ascii_number($v);
				if (!preg_match('/^[0-9]{1,6}$/', $n) || (int) $n < 1) return '';
				return (string) min(self::max_files_cap(), (int) $n);
			case 'max_total_mb':
				$n = self::ascii_number($v);
				if (!preg_match('/^[0-9]{1,6}(\.[0-9]{1,6})?$/', $n) || (float) $n <= 0) return '';
				$f = min(10240.0, round((float) $n, 2));
				return rtrim(rtrim(number_format($f, 2, '.', ''), '0'), '.');
			case 'capture':
				$s = is_string($v) ? strtolower(trim($v)) : '';
				return in_array($s, array('user', 'environment'), true) ? $s : '';
			case 'img_max_px':
				$n = self::ascii_number($v);
				if (!preg_match('/^[0-9]{1,6}$/', $n) || (int) $n < 1) return '';
				return (string) max(320, min(8000, (int) $n));
			case 'img_quality':
				$n = self::ascii_number($v);
				if (!preg_match('/^(0|1)?(\.[0-9]{1,4})?$/', $n) || $n === '' || $n === '.') return '';
				$f = max(0.5, min(0.95, round((float) $n, 2)));
				return rtrim(rtrim(number_format($f, 2, '.', ''), '0'), '.');
			case 'file_store':
				$s = is_string($v) ? strtolower(trim($v)) : '';
				return in_array($s, array('media', 'private'), true) ? $s : '';
			case 'email_attach':
				return in_array($v, array(1, '1', 'true', 'on'), true) ? '1' : '';
		}
		return '';
	}

	/* ------------------------------------------------------------------ plan */

	/** Whether the site's plan (or $package) includes one part (PLAN). */
	public static function plan_ok($part, $package = null) {
		$need = isset(self::PLAN[$part]) ? self::PLAN[$part] : 'free';
		if ($need === 'free') return true;
		$ok = null;
		if (class_exists('\\Emsfb\\Builder_Api')) {
			$ok = Builder_Api::plan_at_least($need, $package);
		} else {
			$p = $package === null ? (function_exists('get_option') ? (int) get_option('emsfb_pro', 2) : 2) : (int) $package;
			$ok = $need === 'pro' ? $p === 1 : in_array($p, array(1, 3), true);
		}
		/**
		 * Whether a part of F07 is available on this site (multi, resize, media, private, capture, attach).
		 *
		 * @param bool   $ok
		 * @param string $part
		 */
		return function_exists('apply_filters') ? (bool) apply_filters('efb_upload_plan_ok', $ok, $part) : $ok;
	}

	private static function get($field, $key) {
		if (is_object($field) && isset($field->$key)) return $field->$key;
		if (is_array($field) && isset($field[$key])) return $field[$key];
		return null;
	}

	/** The field's preset (the builder writes it to file and value). */
	public static function preset($field) {
		$p = self::get($field, 'file');
		if (!is_string($p) || $p === '') $p = self::get($field, 'value');
		$p = strtolower(trim(is_scalar($p) ? (string) $p : ''));
		return $p === '' ? 'allformat' : $p;
	}

	/**
	 * What one upload field does on this site (the plan applied). Every renderer, the
	 * quota and the submit check read this, so they cannot disagree.
	 *
	 * @param object|array $field
	 * @param int|null     $package Answer for this plan instead of the site's.
	 * @return array{type:string,multi:bool,max_files:int,min_files:int,required:bool,max_mb:float,max_bytes:int,max_total_bytes:int,max_total_mb:float,capture:string,img_max_px:int,img_quality:float,file_store:string,preset:string}
	 */
	public static function config($field, $package = null) {
		$type = (string) self::get($field, 'type');
		$preset = self::preset($field);
		$is_multi_type = in_array($type, self::MULTI_TYPES, true);
		$max = $is_multi_type ? (int) self::sanitize_key('max_files', self::get($field, 'max_files')) : 0;
		$max = max(1, $max);
		if ($max > 1 && !self::plan_ok('multi', $package)) $max = 1;
		$required = in_array(self::get($field, 'required'), array(1, '1', true, 'true'), true);
		$min = $is_multi_type ? (int) self::sanitize_key('min_files', self::get($field, 'min_files')) : 0;
		$min = min($max, $min);
		if ($required) $min = max(1, $min);
		$max_mb_raw = self::get($field, 'max_fsize');
		$max_mb = is_numeric($max_mb_raw) && (float) $max_mb_raw > 0 ? (float) $max_mb_raw : (float) Upload_Guard::DEFAULT_MAX_MB;
		$total_mb = $is_multi_type && $max > 1 ? (float) self::sanitize_key('max_total_mb', self::get($field, 'max_total_mb')) : 0.0;
		if ($total_mb > 0 && $total_mb < $max_mb) $total_mb = $max_mb;
		$capture = $is_multi_type && in_array($preset, self::CAPTURE_PRESETS, true) ? self::sanitize_key('capture', self::get($field, 'capture')) : '';
		$img = $is_multi_type && in_array($preset, self::RESIZE_PRESETS, true) && self::plan_ok('resize', $package) ? (int) self::sanitize_key('img_max_px', self::get($field, 'img_max_px')) : 0;
		$q = (string) self::sanitize_key('img_quality', self::get($field, 'img_quality'));
		$store = $is_multi_type ? self::sanitize_key('file_store', self::get($field, 'file_store')) : '';
		if ($store === 'media' && !self::plan_ok('media', $package)) $store = '';
		if ($store === 'private' && !self::plan_ok('private', $package)) $store = '';
		return array(
			'type'            => $type,
			'multi'           => $max > 1,
			'max_files'       => $max,
			'min_files'       => $min,
			'required'        => $required,
			'max_mb'          => $max_mb,
			'max_bytes'       => (int) min($max_mb * 1048576, 1073741824),
			'max_total_mb'    => $total_mb,
			'max_total_bytes' => (int) round($total_mb * 1048576),
			'capture'         => $capture,
			'img_max_px'      => $img,
			'img_quality'     => $q === '' ? 0.85 : (float) $q,
			'file_store'      => $store,
			'preset'          => $preset,
		);
	}

	/**
	 * The accept attribute of a field: the server's own extension list for the preset
	 * (Upload_Guard::preset_extensions(), the global allow-list for "all formats") and the
	 * MIME types of those extensions, so the picker offers what the server keeps.
	 */
	public static function accept_attribute($preset, $custom = '') {
		$preset = strtolower(trim((string) $preset));
		if ($preset === 'customize') {
			$exts = Upload_Guard::field_extensions(array('file' => 'customize', 'file_ctype' => (string) $custom));
		} else {
			$exts = Upload_Guard::preset_extensions($preset === '' ? 'allformat' : $preset);
		}
		$map = Upload_Guard::allowed_extension_mimes();
		if (empty($exts)) $exts = array_keys($map);
		$out = array();
		foreach ($exts as $e) {
			$e = strtolower(ltrim(trim((string) $e), '.'));
			if ($e === '' || !preg_match('/^[a-z0-9]{1,10}$/', $e) || Upload_Guard::is_blocked_extension($e)) continue;
			$out['.' . $e] = true;
			if (isset($map[$e])) {
				foreach ((array) $map[$e] as $m) {
					if (preg_match('#^(image|audio|video)/#', $m)) $out[$m] = true;
				}
			}
		}
		return implode(',', array_keys($out));
	}

	/** data-* attributes and the matching HTML attributes of one rendered upload input (escaped). */
	public static function input_attributes($field) {
		$c = self::config($field);
		$custom = self::get($field, 'file_ctype');
		$a = ' accept="' . esc_attr(self::accept_attribute($c['preset'], is_string($custom) ? $custom : '')) . '"';
		if ($c['multi']) $a .= ' multiple';
		if ($c['capture'] !== '') $a .= ' capture="' . esc_attr($c['capture']) . '"';
		$a .= ' data-max-files="' . (int) $c['max_files'] . '" data-min-files="' . (int) $c['min_files'] . '"';
		$a .= ' data-max-mb="' . esc_attr(self::num($c['max_mb'])) . '"';
		if ($c['max_total_mb'] > 0) $a .= ' data-max-total="' . esc_attr(self::num($c['max_total_mb'])) . '"';
		if ($c['img_max_px'] > 0) $a .= ' data-img-max="' . (int) $c['img_max_px'] . '" data-img-q="' . esc_attr(self::num($c['img_quality'])) . '"';
		return $a;
	}

	/** The file list under a multi-file input (block element, safe in wpautop). */
	public static function list_markup($field_id) {
		$id = esc_attr((string) $field_id);
		return '<ul id="' . $id . '_-list" class="efb efb-file-list list-unstyled" data-efb-upload-list="' . $id . '"></ul>'
			. '<div class="efb efb-file-sum" id="' . $id . '_-sum" aria-live="polite"></div>';
	}

	private static function num($f) {
		$f = (float) $f;
		return $f == (int) $f ? (string) (int) $f : rtrim(rtrim(number_format($f, 2, '.', ''), '0'), '.');
	}

	/* ------------------------------------------------------------------ save */

	/**
	 * After sanitize_obj_msg_efb(): the rules between keys (minimum ≤ maximum, a total at least
	 * the per-file size, the camera only for image / media presets, resizing only where images
	 * can arrive, the Media Library only for a user who may upload files). Answers [rows, warnings];
	 * never refuses the save. $old_rows (the stored form) adds a security_changes warning when
	 * file_store or max_files changed (SitePilot preview).
	 */
	public static function normalize_rows(array $rows, $old_rows = null, $context = array()) {
		$warnings = array();
		$old = array();
		foreach ((array) $old_rows as $i => $r) {
			if ($i > 0 && is_array($r) && isset($r['id_'], $r['type']) && in_array($r['type'], self::MULTI_TYPES, true)) $old[(string) $r['id_']] = $r;
		}
		$can_media = isset($context['can_media']) ? (bool) $context['can_media'] : (function_exists('current_user_can') ? current_user_can('upload_files') : true);
		$changed = array();
		foreach ($rows as $i => $r) {
			if ($i < 1 || !is_array($r) || !isset($r['type'])) continue;
			if (!in_array($r['type'], self::MULTI_TYPES, true)) {
				// Recorders keep one file in the uploads folder: the keys mean nothing there.
				if (in_array($r['type'], self::UPLOAD_TYPES, true)) {
					foreach (self::FIELD_KEYS as $k) if (array_key_exists($k, $r)) unset($rows[$i][$k]);
				}
				continue;
			}
			$preset = self::preset($r);
			$max = isset($r['max_files']) && $r['max_files'] !== '' ? (int) $r['max_files'] : 1;
			if (isset($r['min_files']) && $r['min_files'] !== '' && (int) $r['min_files'] > $max) {
				$rows[$i]['min_files'] = (string) $max;
			}
			if (isset($r['max_total_mb']) && $r['max_total_mb'] !== '') {
				$per = isset($r['max_fsize']) && is_numeric($r['max_fsize']) && (float) $r['max_fsize'] > 0 ? (float) $r['max_fsize'] : (float) Upload_Guard::DEFAULT_MAX_MB;
				if ((float) $r['max_total_mb'] < $per) {
					$rows[$i]['max_total_mb'] = self::num($per);
					$warnings[] = array('code' => 'upload_total_lt_file', 'field' => (string) $r['id_'], 'm' => self::t('maxTotalLtFile', 'The total size must be at least the per-file size.'));
				}
			}
			if (isset($r['capture']) && $r['capture'] !== '' && !in_array($preset, self::CAPTURE_PRESETS, true)) $rows[$i]['capture'] = '';
			if (isset($r['img_max_px']) && $r['img_max_px'] !== '' && !in_array($preset, self::RESIZE_PRESETS, true)) $rows[$i]['img_max_px'] = '';
			if (isset($r['file_store']) && $r['file_store'] === 'media' && !$can_media) {
				$rows[$i]['file_store'] = isset($old[(string) $r['id_']]['file_store']) && $old[(string) $r['id_']]['file_store'] === 'media' ? 'media' : '';
				if ($rows[$i]['file_store'] === '') $warnings[] = array('code' => 'upload_media_denied', 'field' => (string) $r['id_'], 'm' => self::t('fileStoreMediaDenied', 'Only a user who may upload files can store answers in the Media Library. This field keeps the uploads folder.'));
			}
			if ($max > 1 && !self::plan_ok('multi')) {
				$warnings[] = array('code' => 'upload_plan', 'field' => (string) $r['id_'], 'package' => self::PLAN['multi'], 'm' => self::t('fileMultiPlan', 'Several files in one field need Free Plus or Pro. Visitors can attach one file until then.'));
			}
			if (isset($old[(string) $r['id_']])) {
				$o = $old[(string) $r['id_']];
				foreach (array('file_store', 'max_files') as $k) {
					$a = isset($o[$k]) ? (string) $o[$k] : '';
					$b = isset($rows[$i][$k]) ? (string) $rows[$i][$k] : '';
					if ($a !== $b) $changed[] = array('field' => (string) $r['id_'], 'key' => $k, 'from' => $a, 'to' => $b);
				}
			}
		}
		if (isset($rows[0]) && is_array($rows[0]) && isset($rows[0]['email_attach']) && $rows[0]['email_attach'] === '1' && !self::plan_ok('attach')) {
			$warnings[] = array('code' => 'upload_attach_plan', 'package' => self::PLAN['attach'], 'm' => self::t('emailAttachPlan', 'Attaching files to the e-mail needs Pro. The e-mail keeps the links until then.'));
		}
		if ($changed) {
			$warnings[] = array('code' => 'upload_security_change', 'changes' => $changed, 'm' => self::t('fileStoreChanged', 'Where uploaded files are kept, or how many a field takes, has changed. New uploads follow the new setting.'));
		}
		return array($rows, $warnings);
	}

	private static function t($key, $fallback) {
		static $cache = array();
		if (isset($cache[$key])) return $cache[$key];
		$fn = function_exists('get_efbFunction') ? get_efbFunction() : null;
		$v = '';
		if ($fn && method_exists($fn, 'text_efb')) {
			$l = $fn->text_efb(array($key));
			$v = is_array($l) && isset($l[$key]) ? (string) $l[$key] : '';
		}
		return $cache[$key] = ($v !== '' ? $v : $fallback);
	}

	/* ------------------------------------------------------------------ submit */

	/** The display name of a file as the visitor called it: a safe file name, at most 120 characters. */
	public static function clean_display_name($name) {
		$name = is_scalar($name) ? (string) $name : '';
		$name = function_exists('sanitize_file_name') ? sanitize_file_name($name) : preg_replace('/[^A-Za-z0-9._-]/', '', $name);
		if (function_exists('mb_substr')) $name = mb_substr($name, 0, 120, 'UTF-8');
		else $name = substr($name, 0, 120);
		return (string) $name;
	}

	/**
	 * The per-field check of a submission (§8.2) on rows that each passed the file check:
	 * count ≤ max_files, count ≥ min_files (when the field is answered or required), total
	 * ≤ max_total_mb. Answers null or [code, message] (code without the efb_file_ prefix).
	 *
	 * @param array $field  Saved field (required already rewritten by Conditional Logic).
	 * @param int[] $sizes  Byte size of every accepted file of the field.
	 * @param array $lan    Phrases (maxFilesMsg, minFilesMsg, maxTotalMsg).
	 */
	public static function check_field($field, array $sizes, array $lan = array()) {
		$c = self::config($field);
		$n = count($sizes);
		$name = '<b>' . esc_html((string) self::get($field, 'name')) . '</b>';
		if ($n > $c['max_files']) {
			return array('max_files', str_replace(array('%1$s', '%2$s'), array(self::digits_i18n($c['max_files']), $name), isset($lan['maxFilesMsg']) ? $lan['maxFilesMsg'] : 'You can attach up to %1$s files to %2$s.'));
		}
		if (($n > 0 || $c['required']) && $c['min_files'] > 0 && $n < $c['min_files']) {
			return array('min_files', str_replace(array('%1$s', '%2$s'), array(self::digits_i18n($c['min_files']), $name), isset($lan['minFilesMsg']) ? $lan['minFilesMsg'] : 'Please attach at least %1$s files to %2$s.'));
		}
		if ($c['max_total_bytes'] > 0 && array_sum($sizes) > $c['max_total_bytes']) {
			return array('max_total', str_replace(array('%1$s', '%2$s'), array($name, self::digits_i18n(self::num($c['max_total_mb']))), isset($lan['maxTotalMsg']) ? $lan['maxTotalMsg'] : 'The files attached to %1$s exceed the total limit of %2$s MB.'));
		}
		return null;
	}

	private static function digits_i18n($n) {
		return function_exists('number_format_i18n') && is_numeric($n) && (float) $n == (int) $n ? number_format_i18n((int) $n) : (string) $n;
	}

	/* ------------------------------------------------------------------ private folder */

	/** Absolute private folder with a trailing slash ('' when uploads is unavailable). */
	public static function private_dir() {
		$uploads = function_exists('wp_upload_dir') ? wp_upload_dir(null, false) : null;
		if (!is_array($uploads) || !empty($uploads['error']) || empty($uploads['basedir'])) return '';
		$dir = trailingslashit($uploads['basedir']) . self::PRIVATE_DIR;
		/**
		 * The folder private uploads are written to (F07 §7.4). Point it outside the web root to
		 * make the files unreachable by address whatever the web server does with .htaccess.
		 *
		 * @param string $dir Default wp-content/uploads/efb-private.
		 */
		$dir = function_exists('apply_filters') ? (string) apply_filters('emsfb_private_upload_dir', $dir) : $dir;
		return $dir === '' ? '' : wp_normalize_path(trailingslashit($dir));
	}

	/** The address prefix stored for private files (uploads URL + efb-private/). Never served directly. */
	public static function private_base_url() {
		$uploads = function_exists('wp_upload_dir') ? wp_upload_dir(null, false) : null;
		if (!is_array($uploads) || empty($uploads['baseurl'])) return '';
		return trailingslashit((string) $uploads['baseurl']) . self::PRIVATE_DIR . '/';
	}

	/** Creates the folder with its deny rules on first use. */
	public static function ensure_private_dir($sub = '') {
		$base = self::private_dir();
		if ($base === '') return '';
		$dir = $base . ($sub !== '' ? trailingslashit($sub) : '');
		if (!is_dir($dir) && !(function_exists('wp_mkdir_p') ? wp_mkdir_p($dir) : @mkdir($dir, 0755, true))) return '';
		if (!file_exists($base . '.htaccess')) {
			@file_put_contents($base . '.htaccess', self::htaccess_rules());
		}
		foreach (array($base, $dir) as $d) {
			if (!file_exists($d . 'index.php')) @file_put_contents($d . 'index.php', "<?php\n// Silence is golden.\n");
		}
		return $dir;
	}

	public static function htaccess_rules() {
		return "# Easy Form Builder: private uploads are served through WordPress only.\n"
			. "<IfModule mod_authz_core.c>\n\tRequire all denied\n</IfModule>\n"
			. "<IfModule !mod_authz_core.c>\n\tOrder deny,allow\n\tDeny from all\n</IfModule>\n";
	}

	/** The nginx rule a site without .htaccess needs (shown with the probe warning). */
	public static function nginx_rule() {
		$path = function_exists('wp_parse_url') ? (string) wp_parse_url(self::private_base_url(), PHP_URL_PATH) : '/wp-content/uploads/efb-private/';
		return 'location ^~ ' . ($path !== '' ? $path : '/wp-content/uploads/efb-private/') . ' { deny all; return 403; }';
	}

	/** "YYYY/MM/efb-…" of a private file's stored address, or ''. */
	public static function private_rel($url) {
		$url = is_string($url) ? $url : '';
		if ($url === '' || strpos($url, '/' . self::PRIVATE_DIR . '/') === false) return '';
		$tail = substr($url, strpos($url, '/' . self::PRIVATE_DIR . '/') + strlen(self::PRIVATE_DIR) + 2);
		$tail = (string) strtok($tail, '?#');
		return self::valid_rel($tail) ? $tail : '';
	}

	public static function is_private_url($url) {
		return self::private_rel($url) !== '';
	}

	/** A private file reference: YYYY/MM/<one of the plugin's names>. */
	public static function valid_rel($rel) {
		if (!is_string($rel) || !preg_match('#^([0-9]{4})/([0-9]{2})/([^/]+)$#', $rel, $m)) return false;
		return Upload_Guard::is_plugin_upload_name($m[3]);
	}

	/** Absolute path of a private file reference, '' unless it exists inside the private folder. */
	public static function private_path($rel) {
		if (!self::valid_rel($rel)) return '';
		$base = self::private_dir();
		if ($base === '') return '';
		$real = realpath($base . $rel);
		if ($real === false || !is_file($real)) return '';
		$real = wp_normalize_path($real);
		$rbase = realpath($base);
		$rbase = $rbase === false ? $base : trailingslashit(wp_normalize_path($rbase));
		return strpos($real, $rbase) === 0 ? $real : '';
	}

	/**
	 * Writes an accepted upload where the field keeps its files: the uploads folder (as before,
	 * also for the Media Library mode, registered at submit) or the private folder.
	 *
	 * @return array{file:string,url:string,error:string}
	 */
	public static function store_upload($name, $contents, $store) {
		if ($store !== 'private') {
			$r = wp_upload_bits($name, null, $contents);
			return array('file' => isset($r['file']) ? (string) $r['file'] : '', 'url' => isset($r['url']) ? (string) $r['url'] : '', 'error' => !empty($r['error']) ? (string) $r['error'] : '');
		}
		$sub = (function_exists('wp_date') ? wp_date('Y/m') : gmdate('Y/m'));
		$dir = self::ensure_private_dir($sub);
		if ($dir === '') return array('file' => '', 'url' => '', 'error' => 'private_dir');
		$name = function_exists('wp_unique_filename') ? wp_unique_filename($dir, $name) : $name;
		$path = $dir . $name;
		if (@file_put_contents($path, $contents) === false) return array('file' => '', 'url' => '', 'error' => 'write');
		@chmod($path, 0640);
		return array('file' => $path, 'url' => self::private_base_url() . $sub . '/' . $name, 'error' => '');
	}

	/* ------------------------------------------------------------------ Media Library */

	/**
	 * When an entry is stored: the files of fields set to the Media Library become private
	 * attachments (post_author 0, post_status private, not listed by /wp/v2/media for visitors)
	 * with _efb_form_id, _efb_field_id and _efb_track, so the entry's deletion finds them.
	 *
	 * @param array  $files   [['path','url','field_id','store','fnm'], …] of the accepted files.
	 * @param int    $form_id
	 * @param string $track
	 * @return int Attachments created.
	 */
	public static function register_media(array $files, $form_id, $track) {
		$n = 0;
		foreach ($files as $f) {
			if (!is_array($f) || ($f['store'] ?? '') !== 'media' || empty($f['path']) || !is_file($f['path'])) continue;
			if (self::attachment_for_path($f['path']) > 0) continue;
			$type = function_exists('wp_check_filetype') ? wp_check_filetype(wp_basename($f['path'])) : array('type' => '');
			$title = isset($f['fnm']) && $f['fnm'] !== '' ? (string) $f['fnm'] : wp_basename($f['path']);
			$id = wp_insert_attachment(array(
				'post_mime_type' => isset($type['type']) && $type['type'] ? $type['type'] : 'application/octet-stream',
				'post_title'     => sanitize_text_field(preg_replace('/\.[^.]+$/', '', $title)),
				'post_content'   => '',
				'post_status'    => 'private',
				'post_author'    => 0,
				'guid'           => (string) $f['url'],
			), $f['path'], 0, false);
			if (!$id || (function_exists('is_wp_error') && is_wp_error($id))) continue;
			update_post_meta($id, '_efb_form_id', (int) $form_id);
			update_post_meta($id, '_efb_field_id', (string) ($f['field_id'] ?? ''));
			update_post_meta($id, '_efb_track', (string) $track);
			if (function_exists('wp_get_attachment_metadata')) {
				if (!function_exists('wp_generate_attachment_metadata') && defined('ABSPATH') && file_exists(ABSPATH . 'wp-admin/includes/image.php')) {
					require_once ABSPATH . 'wp-admin/includes/image.php';
				}
				if (function_exists('wp_generate_attachment_metadata') && strpos((string) ($type['type'] ?? ''), 'image/') === 0) {
					$meta = wp_generate_attachment_metadata($id, $f['path']);
					if (is_array($meta)) wp_update_attachment_metadata($id, $meta);
				}
			}
			$n++;
		}
		return $n;
	}

	/**
	 * The attachment this plugin registered for a stored file (Media Library mode), or 0.
	 * Only attachments with _efb_form_id: a site's own media are never touched.
	 */
	public static function attachment_for_path($path) {
		if (!function_exists('get_posts')) return 0;
		$path = wp_normalize_path((string) $path);
		$uploads = function_exists('wp_upload_dir') ? wp_upload_dir(null, false) : null;
		if (!is_array($uploads) || empty($uploads['basedir'])) return 0;
		$base = trailingslashit(wp_normalize_path($uploads['basedir']));
		if (strpos($path, $base) !== 0) return 0;
		$rel = substr($path, strlen($base));
		$ids = get_posts(array(
			'post_type'      => 'attachment',
			'post_status'    => 'any',
			'posts_per_page' => 1,
			'fields'         => 'ids',
			'no_found_rows'  => true,
			'meta_query'     => array(
				array('key' => '_wp_attached_file', 'value' => $rel),
				array('key' => '_efb_form_id', 'compare' => 'EXISTS'),
			),
		));
		return !empty($ids) ? (int) $ids[0] : 0;
	}

	/* ------------------------------------------------------------------ links */

	/** Days a signed link in an e-mail or export works (Settings > Privacy, prv_file_link_days). */
	public static function link_days() {
		$d = class_exists('\\Emsfb\\Privacy') ? (int) Privacy::setting('prv_file_link_days') : 7;
		return max(1, min(90, $d > 0 ? $d : 7));
	}

	private static function sign($rel, $exp) {
		$key = function_exists('wp_salt') ? wp_salt('auth') : 'efb';
		return substr(hash_hmac('sha256', 'efb-fdl-v1|' . $rel . '|' . (int) $exp, $key), 0, 40);
	}

	/** A signed, expiring link to a private file (REST files/signed). */
	public static function signed_url($url, $days = null) {
		$rel = self::private_rel($url);
		if ($rel === '') return '';
		$exp = time() + 86400 * ($days === null ? self::link_days() : max(1, (int) $days));
		$base = function_exists('rest_url') ? rest_url('Emsfb/v1/' . self::SIGNED_ROUTE) : '/wp-json/Emsfb/v1/' . self::SIGNED_ROUTE;
		return add_query_arg(array('f' => rawurlencode($rel), 'exp' => $exp, 'sig' => self::sign($rel, $exp)), $base);
	}

	public static function verify_signed($rel, $exp, $sig) {
		if (!self::valid_rel($rel) || !is_scalar($exp) || !preg_match('/^[0-9]{9,11}$/', (string) $exp) || (int) $exp < time()) return false;
		return is_string($sig) && preg_match('/^[a-f0-9]{40}$/', $sig) && hash_equals(self::sign($rel, (int) $exp), $sig);
	}

	/** The admin download address of a private file (the response viewer). */
	public static function admin_url($url) {
		$rel = self::private_rel($url);
		if ($rel === '' || !function_exists('admin_url')) return '';
		return add_query_arg(array('action' => self::DOWNLOAD_ACTION, 'f' => rawurlencode($rel), 'nonce' => wp_create_nonce('wp_rest')), admin_url('admin-ajax.php'));
	}

	/**
	 * The address to show a person: a public file's own address, a private file's signed link
	 * (e-mail, export) or admin download (admin). A private address is never printed as is.
	 */
	public static function display_url($url, $context = 'email') {
		if (!self::is_private_url($url)) return is_string($url) ? $url : '';
		return $context === 'admin' ? self::admin_url($url) : self::signed_url($url);
	}

	/* ------------------------------------------------------------------ downloads */

	public static function rest_signed($request) {
		if (function_exists('nocache_headers')) nocache_headers();
		$ip = class_exists('\\Emsfb\\Privacy') ? Privacy::client_ip() : (isset($_SERVER['REMOTE_ADDR']) ? (string) $_SERVER['REMOTE_ADDR'] : '');
		$limit = (int) apply_filters('emsfb_signed_file_per_minute', self::SIGNED_PER_MINUTE);
		if ($ip !== '' && class_exists('\\Emsfb\\Rate_Limit') && Rate_Limit::ready() && !Rate_Limit::hit('fdl', $ip, 60, $limit)) {
			return new \WP_Error('efb_rate_limited', __('Too many requests. Please wait a minute.', 'easy-form-builder'), array('status' => 429));
		}
		$rel = rawurldecode((string) $request->get_param('f'));
		if (!self::verify_signed($rel, $request->get_param('exp'), (string) $request->get_param('sig'))) {
			return new \WP_Error('efb_link_invalid', __('This download link is not valid any more. Please ask the site owner for a new one.', 'easy-form-builder'), array('status' => 403));
		}
		$path = self::private_path($rel);
		if ($path === '') return new \WP_Error('efb_file_missing', __('This file is no longer available.', 'easy-form-builder'), array('status' => 404));
		self::stream($path);
		return null;
	}

	public static function ajax_download() {
		$fn = function_exists('get_efbFunction') ? get_efbFunction() : null;
		$can = $fn && method_exists($fn, 'user_permission_efb_admin_dashboard') ? $fn->user_permission_efb_admin_dashboard() : current_user_can('manage_options');
		if (!check_ajax_referer('wp_rest', 'nonce', false) || !$can) {
			status_header(403);
			wp_die(esc_html__('You do not have permission to download this file.', 'easy-form-builder'), '', array('response' => 403));
		}
		$rel = isset($_GET['f']) ? rawurldecode(sanitize_text_field(wp_unslash($_GET['f']))) : '';
		$path = self::private_path($rel);
		if ($path === '') {
			status_header(404);
			wp_die(esc_html__('This file is no longer available.', 'easy-form-builder'), '', array('response' => 404));
		}
		self::stream($path);
	}

	/** Headers of a private download: the sniffed type, inline only for images, never sniffed again by the browser. */
	public static function download_headers($path) {
		$mime = Upload_Guard::sniff_mime($path);
		$ext = Upload_Guard::extension_of($path);
		$map = Upload_Guard::allowed_extension_mimes();
		if (!$mime || !isset($map[$ext]) || (!empty($map[$ext]) && !in_array($mime, (array) $map[$ext], true))) $mime = 'application/octet-stream';
		$inline = in_array($mime, array('image/png', 'image/jpeg', 'image/gif', 'image/webp'), true);
		return array(
			'Content-Type'            => $mime,
			'Content-Disposition'     => ($inline ? 'inline' : 'attachment') . '; filename="' . wp_basename($path) . '"',
			'X-Content-Type-Options'  => 'nosniff',
			'Cache-Control'           => 'private, no-store',
			'Content-Security-Policy' => "default-src 'none'; img-src 'self'; style-src 'unsafe-inline'; sandbox",
			'Content-Length'          => (string) (int) @filesize($path),
		);
	}

	private static function stream($path) {
		if (!headers_sent()) {
			foreach (self::download_headers($path) as $k => $v) header($k . ': ' . $v);
		}
		while (ob_get_level() > 0) @ob_end_clean();
		readfile($path);
		exit;
	}

	/* ------------------------------------------------------------------ probe */

	/**
	 * Whether the web server hands out the private folder: a probe file is requested without
	 * cookies; 200 means .htaccess is not obeyed (nginx, PHP's own server).
	 *
	 * @return array{exposed:bool|null,code:int,t:int,rule:string}
	 */
	public static function probe($fresh = false) {
		$cached = function_exists('get_option') ? get_option(self::PROBE_OPTION, null) : null;
		if (!$fresh && is_array($cached) && isset($cached['t']) && (int) $cached['t'] > time() - DAY_IN_SECONDS) return $cached;
		$out = array('exposed' => null, 'code' => 0, 't' => time(), 'rule' => self::nginx_rule());
		$dir = self::ensure_private_dir();
		if ($dir !== '' && @file_put_contents($dir . 'probe.txt', 'efb') !== false && function_exists('wp_remote_get')) {
			$r = wp_remote_get(self::private_base_url() . 'probe.txt', array('timeout' => 5, 'redirection' => 0, 'cookies' => array()));
			$code = is_array($r) ? (int) wp_remote_retrieve_response_code($r) : 0;
			$out['code'] = $code;
			$out['exposed'] = $code === 0 ? null : ($code === 200 && trim((string) wp_remote_retrieve_body($r)) === 'efb');
		}
		if ($dir !== '') @unlink($dir . 'probe.txt');
		/* No answer (a single-threaded server cannot request itself, or a firewall): the web
		 * server's name decides for those that never read .htaccess (nginx, Caddy, PHP's own). */
		if ($out['exposed'] === null) {
			$soft = isset($_SERVER['SERVER_SOFTWARE']) ? strtolower((string) $_SERVER['SERVER_SOFTWARE']) : '';
			if ($soft !== '' && preg_match('/nginx|caddy|development server|openresty/', $soft)) {
				$out['exposed'] = true;
				$out['guess'] = true;
			}
		}
		if (function_exists('update_option')) update_option(self::PROBE_OPTION, $out, false);
		return $out;
	}

	public static function ajax_probe() {
		$fn = function_exists('get_efbFunction') ? get_efbFunction() : null;
		$can = $fn && method_exists($fn, 'user_permission_efb_admin_dashboard') ? $fn->user_permission_efb_admin_dashboard() : current_user_can('manage_options');
		if (!check_ajax_referer('wp_rest', 'nonce', false) || !$can) {
			wp_send_json_success(array('success' => false, 'm' => 'error403'), 200);
		}
		$p = self::probe(!empty($_POST['fresh']));
		wp_send_json_success(array('success' => true, 'exposed' => $p['exposed'], 'code' => $p['code'], 'rule' => $p['rule']), 200);
	}

	/* ------------------------------------------------------------------ e-mail */

	/**
	 * Files to attach to the owner's e-mail (Pro, form key email_attach): the accepted files of
	 * this submission, in order, until the ceiling (filter emsfb_email_attach_max_bytes, 10 MB).
	 * The rest stay links in the message.
	 *
	 * @param array $form_row Row 0 of the form.
	 * @param array $files    [['path', …], …] accepted in this submission.
	 * @return string[] Absolute paths.
	 */
	public static function email_attachments($form_row, array $files) {
		if (!is_array($form_row) || self::sanitize_key('email_attach', $form_row['email_attach'] ?? '') !== '1' || !self::plan_ok('attach')) return array();
		$max = (int) apply_filters('emsfb_email_attach_max_bytes', self::EMAIL_ATTACH_MAX);
		$out = array();
		$sum = 0;
		foreach ($files as $f) {
			$p = is_array($f) && isset($f['path']) ? (string) $f['path'] : '';
			if ($p === '' || !is_file($p)) continue;
			$s = (int) @filesize($p);
			if ($s < 1 || $sum + $s > $max) continue;
			$sum += $s;
			$out[] = $p;
		}
		return $out;
	}

	/* ------------------------------------------------------------------ registry, schema, admin */

	/** setting:upload_* entries (F07 §13) and the top-level uploads block (SitePilot manifest.features.multi_file). */
	public static function capabilities($out, $args = array()) {
		if (!is_array($out) || !isset($out['features']) || !is_array($out['features'])) return $out;
		$package = isset($out['plan']['package_type']) ? (int) $out['plan']['package_type'] : null;
		$entries = array(
			'setting:upload_multi'   => array('multi', array('max_files', 'min_files', 'max_total_mb'), 'maxFiles'),
			'setting:upload_capture' => array('capture', array('capture'), 'fileCapture'),
			'setting:upload_resize'  => array('resize', array('img_max_px', 'img_quality'), 'imgMaxPx'),
			'setting:upload_media'   => array('media', array('file_store=media'), 'fileStoreMedia'),
			'setting:upload_private' => array('private', array('file_store=private'), 'fileStorePrivate'),
			'setting:email_attach'   => array('attach', array('email_attach'), 'emailAttach'),
		);
		foreach ($entries as $key => $e) {
			$need = self::PLAN[$e[0]];
			$ok = class_exists('\\Emsfb\\Builder_Api') ? Builder_Api::plan_at_least($need, $package) : true;
			$out['features'][$key] = array(
				'kind' => 'setting', 'scope' => $key === 'setting:email_attach' ? 'form' : 'field', 'package' => $need, 'addon' => null,
				'available_now' => $ok, 'reason' => $ok ? null : 'plan',
				'prompt_state' => $need === 'free_plus' ? 3 : ($need === 'pro' ? 1 : 0),
				'message_key' => $need === 'free_plus' ? 'thisFeatureAvailableFreePlusPro' : ($need === 'pro' ? 'proUnlockMsg' : null),
				'form_keys' => $e[1], 'label_key' => $e[2],
			);
		}
		$out['uploads'] = array(
			'keys'           => self::FIELD_KEYS,
			'form_keys'      => self::FORM_KEYS,
			'types'          => self::MULTI_TYPES,
			'max_files_cap'  => self::max_files_cap(),
			'stores'         => array('', 'media', 'private'),
			'plan'           => self::PLAN,
			'builder'        => 'efbBuilderApi.setUploadConfig',
			'page_api'       => 'window.EFBUpload',
			'assistant'      => 'visitor_only',
			'error_code_prefix' => 'efb_file_',
			'codes'          => self::CODES,
			'save_warnings'  => array('upload_total_lt_file', 'upload_media_denied', 'upload_plan', 'upload_attach_plan', 'upload_security_change'),
			'require_token'  => Upload_Guard::require_token(),
			'spec'           => 'F07',
		);
		return $out;
	}

	/**
	 * forms/{id}/schema: an upload field is filled by the visitor only (assistant visitor_only);
	 * the entry shows what the visitor may attach and nothing else (no storage mode, no path).
	 */
	public static function schema_field($e, $r, $form_id) {
		if (!is_array($e) || !is_array($r) || !isset($r['type']) || !in_array($r['type'], self::UPLOAD_TYPES, true)) return $e;
		$c = self::config($r);
		$e['assistant'] = 'visitor_only';
		$e['fill'] = array('api' => 'visitor_only', 'note' => 'files are attached by the visitor in the page; an assistant does not upload or attach files');
		$e['upload'] = array(
			'accept'       => self::accept_attribute($c['preset'], (string) (self::get($r, 'file_ctype') ?? '')),
			'max_files'    => $c['max_files'],
			'min_files'    => $c['min_files'],
			'max_mb'       => $c['max_mb'],
			'max_total_mb' => $c['max_total_mb'] > 0 ? $c['max_total_mb'] : null,
		);
		return $e;
	}

	/** efb_var.uploads on the EFB admin screens: the private folder check and the download address. */
	public static function localize_vars($vars, $context = '') {
		if (!is_array($vars)) return $vars;
		$probe = function_exists('get_option') ? get_option(self::PROBE_OPTION, null) : null;
		$vars['uploads'] = array(
			'max_files_cap' => self::max_files_cap(),
			'download'      => function_exists('admin_url') ? add_query_arg(array('action' => self::DOWNLOAD_ACTION, 'nonce' => wp_create_nonce('wp_rest')), admin_url('admin-ajax.php')) : '',
			'private_marker' => '/' . self::PRIVATE_DIR . '/',
			'probe'         => is_array($probe) ? array('exposed' => $probe['exposed'], 'rule' => self::nginx_rule()) : null,
			'plan'          => array(
				'multi'  => self::plan_ok('multi'),
				'resize' => self::plan_ok('resize'),
				'media'  => self::plan_ok('media'),
				'attach' => self::plan_ok('attach'),
			),
			'can_media'     => function_exists('current_user_can') ? current_user_can('upload_files') : true,
		);
		return $vars;
	}
}
