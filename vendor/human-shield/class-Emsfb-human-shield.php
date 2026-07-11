<?php
/**
 * Main EFB Human Shield addon class.
 *
 * @package Easy_Form_Builder
 * @subpackage Human_Shield
 */

namespace Emsfb;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Emsfb_Human_Shield {
	const OPTION_SETTINGS = 'emsfb_human_shield_settings';
	const OPTION_SECRET   = 'emsfb_human_shield_secret';
	const OPTION_DB_VER   = 'emsfb_human_shield_db_version';
	const DB_VERSION      = '0.1.0';

	private static $instance = null;

	/** @var \wpdb */
	public $db;

	/** @var Emsfb_Human_Shield_Rate_Limiter */
	public $rate_limiter;

	/** @var Emsfb_Human_Shield_Detector */
	public $detector;

	/** @var Emsfb_Human_Shield_Rest */
	public $rest;

	/** @var Emsfb_Human_Shield_Notification_Gate */
	public $notification_gate;

	/** @var Emsfb_Human_Shield_Admin|null */
	public $admin;

	private $settings = null;

	/**
	 * Score/decision of the current REST request, set by the REST guard so the
	 * notification gate can suppress paid side effects later in the same request.
	 *
	 * @var array|null
	 */
	private $request_assessment = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	private function __construct() {
		global $wpdb;
		$this->db = $wpdb;

		add_action( 'init', array( $this, 'maybe_create_tables' ), 5 );
		add_action( 'init', array( $this, 'cleanup_old_records' ), 20 );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_public_assets' ), 25 );

		$this->rate_limiter     = new Emsfb_Human_Shield_Rate_Limiter( $this );
		$this->detector         = new Emsfb_Human_Shield_Detector( $this );
		$this->rest             = new Emsfb_Human_Shield_Rest( $this );
		$this->notification_gate = new Emsfb_Human_Shield_Notification_Gate( $this );

		if ( is_admin() ) {
			$this->admin = new Emsfb_Human_Shield_Admin( $this );
		}
	}

	public function defaults() {
		return array(
			'enabled'                              => 1,
			'mode'                                 => 'monitor',
			'min_score_submit'                     => 60,
			'min_score_paid_notification'          => 70,
			'block_score_below'                    => 25,
			'quarantine_score_below'               => 45,
			'token_ttl_seconds'                    => 180,
			'challenge_ttl_seconds'                => 600,
			'min_fill_time_seconds'                => 3,
			'submit_ip_per_minute'                 => 3,
			'submit_ip_per_hour'                   => 20,
			'form_global_per_minute'               => 60,
			'response_get_ip_per_minute'           => 10,
			'response_add_ip_per_minute'           => 2,
			'file_upload_ip_per_minute'            => 3,
			'payment_ip_per_minute'                => 3,
			'api_ip_per_minute'                    => 30,
			'sms_daily_stop_loss'                  => 100,
			'telegram_daily_stop_loss'             => 300,
			'webhook_daily_stop_loss'              => 500,
			'recipient_daily_cap'                  => 50,
			'ip_blocklist'                         => '',
			'ip_allowlist'                         => '',
			'log_retention_days'                   => 30,
			'protect_response_lookup'              => 1,
			'fail_closed_on_missing_requirements'  => 0,
			'trusted_proxy_headers'                => 0,
			'store_raw_metrics'                    => 0,
			'client_attest_timeout_ms'             => 4500,
		);
	}

	public function get_settings() {
		if ( null !== $this->settings ) {
			return $this->settings;
		}

		$settings = get_option( self::OPTION_SETTINGS, array() );
		if ( ! is_array( $settings ) ) {
			$settings = array();
		}

		$this->settings = wp_parse_args( $settings, $this->defaults() );
		return $this->settings;
	}

	public function update_settings( $settings ) {
		$settings = $this->sanitize_settings( $settings );
		update_option( self::OPTION_SETTINGS, $settings, false );
		$this->settings = $settings;
		return $settings;
	}

	public function sanitize_settings( $settings ) {
		$defaults = $this->defaults();
		$settings = is_array( $settings ) ? $settings : array();
		$out = array();

		$textarea_keys = array( 'ip_blocklist', 'ip_allowlist' );

		foreach ( $defaults as $key => $default ) {
			if ( ! array_key_exists( $key, $settings ) ) {
				$out[ $key ] = $default;
				continue;
			}

			if ( is_int( $default ) ) {
				$out[ $key ] = max( 0, absint( $settings[ $key ] ) );
				continue;
			}

			if ( in_array( $key, $textarea_keys, true ) ) {
				// One IP or wildcard prefix per line; sanitize_text_field would
				// eat the newlines the matcher splits on.
				$out[ $key ] = sanitize_textarea_field( wp_unslash( $settings[ $key ] ) );
				continue;
			}

			$out[ $key ] = sanitize_text_field( wp_unslash( $settings[ $key ] ) );
		}

		$allowed_modes = array( 'monitor', 'soft_block', 'strict' );
		if ( ! in_array( $out['mode'], $allowed_modes, true ) ) {
			$out['mode'] = $defaults['mode'];
		}

		$out['token_ttl_seconds']     = min( 900, max( 30, (int) $out['token_ttl_seconds'] ) );
		$out['challenge_ttl_seconds'] = min( 1800, max( 60, (int) $out['challenge_ttl_seconds'] ) );
		$out['log_retention_days']    = min( 365, max( 1, (int) $out['log_retention_days'] ) );
		$out['client_attest_timeout_ms'] = min( 15000, max( 1000, (int) $out['client_attest_timeout_ms'] ) );

		return $out;
	}

	public function is_enabled() {
		$settings = $this->get_settings();
		return ! empty( $settings['enabled'] );
	}

	public function set_request_assessment( $score, $suppress_paid, $reasons = array() ) {
		$this->request_assessment = array(
			'score'         => (int) $score,
			'suppress_paid' => (bool) $suppress_paid,
			'reasons'       => (array) $reasons,
		);
	}

	public function get_request_assessment() {
		return $this->request_assessment;
	}

	/**
	 * Manual allow/block decision for an IP: 'allow', 'block' or 'none'.
	 * Entries are one per line, exact IP or wildcard prefix (203.0.113.* /
	 * 2a01:4f8:*). Allowlist wins over blocklist.
	 */
	public function ip_list_decision( $ip ) {
		$settings = $this->get_settings();

		if ( $this->ip_in_list( $ip, $settings['ip_allowlist'] ) ) {
			return 'allow';
		}

		if ( $this->ip_in_list( $ip, $settings['ip_blocklist'] ) ) {
			return 'block';
		}

		return 'none';
	}

	private function ip_in_list( $ip, $list ) {
		$list = (string) $list;
		if ( '' === trim( $list ) ) {
			return false;
		}

		$ip = (string) $ip;
		$entries = preg_split( '/[\r\n,;]+/', $list );
		foreach ( (array) $entries as $entry ) {
			$entry = trim( $entry );
			if ( '' === $entry ) {
				continue;
			}

			if ( $entry === $ip ) {
				return true;
			}

			if ( '*' === substr( $entry, -1 ) ) {
				$prefix = substr( $entry, 0, -1 );
				if ( '' !== $prefix && 0 === strpos( $ip, $prefix ) ) {
					return true;
				}
			}
		}

		return false;
	}

	public function get_secret() {
		$secret = get_option( self::OPTION_SECRET, '' );
		if ( is_string( $secret ) && strlen( $secret ) >= 32 ) {
			return $secret;
		}

		$secret = $this->random_string( 48 );
		update_option( self::OPTION_SECRET, $secret, false );
		return $secret;
	}

	public function random_string( $length = 32 ) {
		$length = max( 16, absint( $length ) );

		if ( self::is_function_available( 'random_bytes' ) ) {
			try {
				return bin2hex( random_bytes( (int) ceil( $length / 2 ) ) );
			} catch ( \Throwable $e ) {
				// Fall through to the next generator.
			}
		}

		if ( self::is_function_available( 'openssl_random_pseudo_bytes' ) && self::is_function_available( 'bin2hex' ) ) {
			try {
				$bytes = openssl_random_pseudo_bytes( (int) ceil( $length / 2 ) );
				if ( is_string( $bytes ) && '' !== $bytes ) {
					return bin2hex( $bytes );
				}
			} catch ( \Throwable $e ) {
				// Fall through to WordPress generation.
			}
		}

		if ( function_exists( 'wp_generate_password' ) ) {
			return wp_generate_password( $length, true, true );
		}

		return substr( str_shuffle( str_repeat( 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789', 4 ) ), 0, $length );
	}

	public static function is_function_available( $function_name ) {
		// PHP 8+: disabled functions already fail function_exists(). On PHP 7
		// they pass it but crash when called, hence the disable_functions scan.
		if ( ! function_exists( $function_name ) ) {
			return false;
		}

		$disabled = function_exists( 'ini_get' ) ? ini_get( 'disable_functions' ) : '';
		$disabled_functions = is_string( $disabled ) && '' !== trim( $disabled )
			? array_map( 'trim', explode( ',', strtolower( $disabled ) ) )
			: array();

		// Lets hosts/tests declare extra unavailable functions (e.g. to
		// simulate a locked-down php.ini) without editing the server config.
		if ( function_exists( 'apply_filters' ) ) {
			$extra = apply_filters( 'efb_shield_disabled_functions', array() );
			if ( is_array( $extra ) && $extra ) {
				$disabled_functions = array_merge( $disabled_functions, array_map( 'strtolower', array_map( 'strval', $extra ) ) );
			}
		}

		return ! in_array( strtolower( $function_name ), $disabled_functions, true );
	}

	public function requirements() {
		$items = array(
			'hash_hmac'    => array( 'required' => true,  'label' => 'hash_hmac' ),
			'hash'         => array( 'required' => true,  'label' => 'hash' ),
			'json_encode'  => array( 'required' => true,  'label' => 'json_encode' ),
			'json_decode'  => array( 'required' => true,  'label' => 'json_decode' ),
			'base64_encode'=> array( 'required' => true,  'label' => 'base64_encode' ),
			'base64_decode'=> array( 'required' => true,  'label' => 'base64_decode' ),
			'hash_equals'  => array( 'required' => false, 'label' => 'hash_equals' ),
			'random_bytes' => array( 'required' => false, 'label' => 'random_bytes' ),
			'openssl_random_pseudo_bytes' => array( 'required' => false, 'label' => 'openssl_random_pseudo_bytes' ),
			'filter_var'   => array( 'required' => false, 'label' => 'filter_var' ),
			'ini_get'      => array( 'required' => false, 'label' => 'ini_get' ),
		);

		$missing_required    = array();
		$missing_recommended = array();
		foreach ( $items as $name => $item ) {
			$items[ $name ]['available'] = self::is_function_available( $name );
			if ( ! $items[ $name ]['available'] ) {
				if ( $item['required'] ) {
					$missing_required[] = $name;
				} else {
					$missing_recommended[] = $name;
				}
			}
		}

		return array(
			'ok'                  => empty( $missing_required ) && function_exists( 'register_rest_route' ) && $this->tables_ready(),
			'missing_required'    => ! empty( $missing_required ),
			'missing_functions'   => $missing_required,
			'missing_recommended' => $missing_recommended,
			'items'               => $items,
			'php_version'         => PHP_VERSION,
			'db_tables_ready'     => $this->tables_ready(),
			'rest_available'      => function_exists( 'register_rest_route' ),
		);
	}

	public function requirements_ok() {
		$requirements = $this->requirements();
		return ! empty( $requirements['ok'] );
	}

	public function hash_equals_safe( $known, $user ) {
		$known = (string) $known;
		$user  = (string) $user;

		if ( self::is_function_available( 'hash_equals' ) ) {
			return hash_equals( $known, $user );
		}

		if ( strlen( $known ) !== strlen( $user ) ) {
			return false;
		}

		$result = 0;
		for ( $i = 0, $len = strlen( $known ); $i < $len; $i++ ) {
			$result |= ord( $known[ $i ] ) ^ ord( $user[ $i ] );
		}

		return 0 === $result;
	}

	public function json_encode( $value ) {
		if ( function_exists( 'wp_json_encode' ) ) {
			return wp_json_encode( $value );
		}

		if ( self::is_function_available( 'json_encode' ) ) {
			return json_encode( $value );
		}

		return false;
	}

	public function json_decode_assoc( $value ) {
		if ( ! self::is_function_available( 'json_decode' ) ) {
			return array();
		}

		$decoded = json_decode( (string) $value, true );
		return is_array( $decoded ) ? $decoded : array();
	}

	public function hash_value( $value, $purpose = 'general' ) {
		$value  = (string) $value;
		$secret = $this->get_secret();

		if ( self::is_function_available( 'hash_hmac' ) ) {
			return hash_hmac( 'sha256', $purpose . '|' . $value, $secret );
		}

		if ( self::is_function_available( 'hash' ) ) {
			return hash( 'sha256', $purpose . '|' . $value . '|' . $secret );
		}

		// Degraded fallbacks so logging/rate keys keep working while the
		// System page tells the admin to re-enable the hash functions.
		// Token signing itself refuses to run without hash_hmac.
		if ( self::is_function_available( 'sha1' ) ) {
			return sha1( $purpose . '|' . $value . '|' . $secret );
		}

		if ( self::is_function_available( 'md5' ) ) {
			return md5( $purpose . '|' . $value . '|' . $secret );
		}

		if ( self::is_function_available( 'crc32' ) ) {
			return sprintf( '%u', crc32( $purpose . '|' . $value . '|' . $secret ) );
		}

		return '';
	}

	public function get_client_ip() {
		$settings = $this->get_settings();
		$keys = array( 'REMOTE_ADDR' );

		if ( ! empty( $settings['trusted_proxy_headers'] ) ) {
			$keys = array( 'HTTP_CF_CONNECTING_IP', 'HTTP_X_REAL_IP', 'REMOTE_ADDR' );
		}

		foreach ( $keys as $key ) {
			if ( empty( $_SERVER[ $key ] ) ) {
				continue;
			}

			$value = sanitize_text_field( wp_unslash( $_SERVER[ $key ] ) );
			$parts = explode( ',', $value );
			$ip = trim( $parts[0] );
			if ( self::validate_ip( $ip ) ) {
				return $ip;
			}
		}

		return '0.0.0.0';
	}

	/**
	 * filter_var lives in the filter extension, which some hardened hosts
	 * compile out or disable; these regex fallbacks keep IP handling alive.
	 */
	public static function validate_ip( $ip, $flag = null ) {
		if ( self::is_function_available( 'filter_var' ) ) {
			return null === $flag ? (bool) filter_var( $ip, FILTER_VALIDATE_IP ) : (bool) filter_var( $ip, FILTER_VALIDATE_IP, $flag );
		}

		$is_v4 = (bool) preg_match( '/^(\d{1,3}\.){3}\d{1,3}$/', (string) $ip );
		$is_v6 = false !== strpos( (string) $ip, ':' ) && (bool) preg_match( '/^[0-9a-fA-F:]+$/', (string) $ip );

		if ( null === $flag ) {
			return $is_v4 || $is_v6;
		}
		if ( FILTER_FLAG_IPV4 === $flag ) {
			return $is_v4;
		}
		if ( FILTER_FLAG_IPV6 === $flag ) {
			return $is_v6;
		}

		return $is_v4 || $is_v6;
	}

	public function get_ip_prefix( $ip ) {
		if ( self::validate_ip( $ip, FILTER_FLAG_IPV4 ) ) {
			$parts = explode( '.', $ip );
			return $parts[0] . '.' . $parts[1] . '.' . $parts[2] . '.0/24';
		}

		if ( self::validate_ip( $ip, FILTER_FLAG_IPV6 ) ) {
			$parts = explode( ':', $ip );
			return implode( ':', array_slice( $parts, 0, 4 ) ) . '::/64';
		}

		return 'unknown';
	}

	public function current_user_agent() {
		return isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '';
	}

	public function table_name( $suffix ) {
		return $this->db->prefix . 'emsfb_shield_' . $suffix;
	}

	public function maybe_create_tables() {
		if ( get_option( self::OPTION_DB_VER, '' ) === self::DB_VERSION && $this->tables_ready() ) {
			return;
		}

		if ( ! function_exists( 'dbDelta' ) ) {
			$upgrade_file = ABSPATH . 'wp-admin/includes/upgrade.php';
			if ( file_exists( $upgrade_file ) ) {
				require_once $upgrade_file;
			}
		}

		if ( ! function_exists( 'dbDelta' ) ) {
			return;
		}

		$charset_collate = $this->db->get_charset_collate();
		$events          = $this->table_name( 'events' );
		$challenges      = $this->table_name( 'challenges' );
		$rate_limits     = $this->table_name( 'rate_limits' );

		$sql = array();

		$sql[] = "CREATE TABLE {$events} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			created_at datetime NOT NULL,
			route varchar(120) NOT NULL DEFAULT '',
			form_id bigint(20) NOT NULL DEFAULT 0,
			action varchar(40) NOT NULL DEFAULT '',
			decision varchar(40) NOT NULL DEFAULT '',
			score int(11) NOT NULL DEFAULT 0,
			ip_hash varchar(64) NOT NULL DEFAULT '',
			ip_prefix_hash varchar(64) NOT NULL DEFAULT '',
			ua_hash varchar(64) NOT NULL DEFAULT '',
			sid_hash varchar(64) NOT NULL DEFAULT '',
			token_jti varchar(80) NOT NULL DEFAULT '',
			reason_codes text NULL,
			cost_channel varchar(40) NOT NULL DEFAULT '',
			cost_suppressed tinyint(1) NOT NULL DEFAULT 0,
			payload_hash varchar(64) NOT NULL DEFAULT '',
			meta_json longtext NULL,
			PRIMARY KEY  (id),
			KEY created_at (created_at),
			KEY route_form (route, form_id),
			KEY decision (decision)
		) {$charset_collate};";

		$sql[] = "CREATE TABLE {$challenges} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			challenge_id varchar(80) NOT NULL,
			form_id bigint(20) NOT NULL DEFAULT 0,
			route varchar(120) NOT NULL DEFAULT '',
			ip_hash varchar(64) NOT NULL DEFAULT '',
			ip_prefix_hash varchar(64) NOT NULL DEFAULT '',
			ua_hash varchar(64) NOT NULL DEFAULT '',
			sid_hash varchar(64) NOT NULL DEFAULT '',
			score int(11) NOT NULL DEFAULT 0,
			token_jti varchar(80) NOT NULL DEFAULT '',
			used tinyint(1) NOT NULL DEFAULT 0,
			expires_at int(11) NOT NULL DEFAULT 0,
			created_at datetime NOT NULL,
			meta_json longtext NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY challenge_id (challenge_id),
			KEY token_jti (token_jti),
			KEY expires_at (expires_at)
		) {$charset_collate};";

		$sql[] = "CREATE TABLE {$rate_limits} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			bucket_key varchar(191) NOT NULL,
			scope varchar(40) NOT NULL DEFAULT '',
			route varchar(120) NOT NULL DEFAULT '',
			form_id bigint(20) NOT NULL DEFAULT 0,
			window_start int(11) NOT NULL DEFAULT 0,
			window_seconds int(11) NOT NULL DEFAULT 0,
			count int(11) NOT NULL DEFAULT 0,
			blocked_until int(11) NOT NULL DEFAULT 0,
			last_seen int(11) NOT NULL DEFAULT 0,
			meta_json longtext NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY bucket (bucket_key, scope, route, form_id, window_start),
			KEY blocked_until (blocked_until)
		) {$charset_collate};";

		foreach ( $sql as $statement ) {
			dbDelta( $statement );
		}

		update_option( self::OPTION_DB_VER, self::DB_VERSION, false );
	}

	public function cleanup_old_records() {
		if ( ! $this->tables_ready() || get_transient( 'emsfb_human_shield_cleanup_lock' ) ) {
			return;
		}

		set_transient( 'emsfb_human_shield_cleanup_lock', 1, HOUR_IN_SECONDS );

		$settings     = $this->get_settings();
		$event_cutoff = gmdate( 'Y-m-d H:i:s', time() - ( max( 1, (int) $settings['log_retention_days'] ) * DAY_IN_SECONDS ) );
		$now          = time();
		$rate_cutoff  = $now - ( 2 * DAY_IN_SECONDS );

		$this->db->query(
			$this->db->prepare(
				'DELETE FROM ' . $this->table_name( 'events' ) . ' WHERE created_at < %s',
				$event_cutoff
			)
		);

		$this->db->query(
			$this->db->prepare(
				'DELETE FROM ' . $this->table_name( 'challenges' ) . ' WHERE expires_at < %d',
				$now
			)
		);

		$this->db->query(
			$this->db->prepare(
				'DELETE FROM ' . $this->table_name( 'rate_limits' ) . ' WHERE last_seen < %d AND blocked_until < %d',
				$rate_cutoff,
				$now
			)
		);
	}

	public function tables_ready() {
		if ( empty( $this->db ) ) {
			return false;
		}

		$tables = array(
			$this->table_name( 'events' ),
			$this->table_name( 'challenges' ),
			$this->table_name( 'rate_limits' ),
		);

		foreach ( $tables as $table ) {
			$found = $this->db->get_var( $this->db->prepare( 'SHOW TABLES LIKE %s', $table ) );
			if ( $found !== $table ) {
				return false;
			}
		}

		return true;
	}

	public function enqueue_public_assets() {
		if ( is_admin() || ! $this->is_enabled() ) {
			return;
		}

		$requirements = $this->requirements();
		if ( empty( $requirements['ok'] ) ) {
			return;
		}

		wp_enqueue_script(
			'efb-human-shield-public',
			EFB_HUMAN_SHIELD_URL . 'assets/js/human-shield-public-efb.js',
			array(),
			EFB_HUMAN_SHIELD_VERSION,
			true
		);

		wp_localize_script(
			'efb-human-shield-public',
			'EFBHumanShield',
			array(
				'restUrl'       => esc_url_raw( rest_url( 'EmsfbShield/v1/' ) ),
				'wpRestNonce'   => wp_create_nonce( 'wp_rest' ),
				'ajaxNonce'     => wp_create_nonce( 'efb_human_shield' ),
				'tokenTtl'      => (int) $this->get_settings()['token_ttl_seconds'],
				'mode'          => sanitize_key( $this->get_settings()['mode'] ),
				'attestTimeoutMs' => (int) $this->get_settings()['client_attest_timeout_ms'],
				'pluginVersion' => EFB_HUMAN_SHIELD_VERSION,
			)
		);
	}

	public function log_event( $context, $decision, $score = 0, $reasons = array(), $meta = array() ) {
		if ( empty( $this->db ) || ! $this->tables_ready() ) {
			return false;
		}

		$ip = isset( $context['ip'] ) ? $context['ip'] : $this->get_client_ip();
		$ua = isset( $context['ua'] ) ? $context['ua'] : $this->current_user_agent();
		$sid = isset( $context['sid'] ) ? $context['sid'] : '';

		return $this->db->insert(
			$this->table_name( 'events' ),
			array(
				'created_at'       => current_time( 'mysql' ),
				'route'            => isset( $context['route'] ) ? sanitize_text_field( $context['route'] ) : '',
				'form_id'          => isset( $context['form_id'] ) ? absint( $context['form_id'] ) : 0,
				'action'           => isset( $context['action'] ) ? sanitize_text_field( $context['action'] ) : '',
				'decision'         => sanitize_text_field( $decision ),
				'score'            => (int) $score,
				'ip_hash'          => $this->hash_value( $ip, 'ip' ),
				'ip_prefix_hash'   => $this->hash_value( $this->get_ip_prefix( $ip ), 'ip_prefix' ),
				'ua_hash'          => $this->hash_value( $ua, 'ua' ),
				'sid_hash'         => $sid ? $this->hash_value( $sid, 'sid' ) : '',
				'token_jti'        => isset( $context['token_jti'] ) ? sanitize_text_field( $context['token_jti'] ) : '',
				'reason_codes'     => implode( ',', array_map( 'sanitize_key', (array) $reasons ) ),
				'cost_channel'     => isset( $context['cost_channel'] ) ? sanitize_text_field( $context['cost_channel'] ) : '',
				'cost_suppressed'  => ! empty( $context['cost_suppressed'] ) ? 1 : 0,
				'payload_hash'     => isset( $context['payload_hash'] ) ? sanitize_text_field( $context['payload_hash'] ) : '',
				'meta_json'        => $this->json_encode( $meta ),
			),
			array( '%s', '%s', '%d', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s' )
		);
	}

	public function recent_events( $limit = 25 ) {
		if ( empty( $this->db ) || ! $this->tables_ready() ) {
			return array();
		}

		$limit = min( 100, max( 1, absint( $limit ) ) );
		$table = $this->table_name( 'events' );
		return $this->db->get_results(
			$this->db->prepare(
				"SELECT created_at, route, form_id, action, decision, score, reason_codes, cost_channel, cost_suppressed FROM {$table} ORDER BY id DESC LIMIT %d",
				$limit
			),
			ARRAY_A
		);
	}

	public function stats() {
		if ( empty( $this->db ) || ! $this->tables_ready() ) {
			return array(
				'allow'      => 0,
				'block'      => 0,
				'quarantine' => 0,
				'monitor'    => 0,
				'suppressed' => 0,
			);
		}

		$table = $this->table_name( 'events' );
		// created_at is stored with current_time('mysql') (site-local), so the
		// cutoff must be site-local too.
		$since = gmdate( 'Y-m-d H:i:s', current_time( 'timestamp' ) - DAY_IN_SECONDS );
		$rows = $this->db->get_results(
			$this->db->prepare(
				"SELECT decision, COUNT(*) AS total, SUM(cost_suppressed) AS suppressed FROM {$table} WHERE created_at >= %s GROUP BY decision",
				$since
			),
			ARRAY_A
		);

		$stats = array(
			'allow'      => 0,
			'block'      => 0,
			'quarantine' => 0,
			'monitor'    => 0,
			'suppressed' => 0,
		);

		foreach ( $rows as $row ) {
			$key = isset( $stats[ $row['decision'] ] ) ? $row['decision'] : 'monitor';
			$stats[ $key ] += (int) $row['total'];
			$stats['suppressed'] += (int) $row['suppressed'];
		}

		return $stats;
	}

	/**
	 * Decisions per hour for the last 24 hours, oldest first. Every hour is
	 * present even when empty so the chart has a continuous axis.
	 */
	public function hourly_stats() {
		$now_local = current_time( 'timestamp' );
		$buckets   = array();
		for ( $i = 23; $i >= 0; $i-- ) {
			$hour_start = $now_local - ( $i * HOUR_IN_SECONDS );
			$key = gmdate( 'Y-m-d H:00', $hour_start );
			$buckets[ $key ] = array(
				'hour'       => gmdate( 'H:00', $hour_start ),
				'allow'      => 0,
				'block'      => 0,
				'quarantine' => 0,
				'monitor'    => 0,
			);
		}

		if ( empty( $this->db ) || ! $this->tables_ready() ) {
			return array_values( $buckets );
		}

		$table = $this->table_name( 'events' );
		$since = gmdate( 'Y-m-d H:00:00', $now_local - ( 23 * HOUR_IN_SECONDS ) );
		$rows = $this->db->get_results(
			$this->db->prepare(
				"SELECT DATE_FORMAT(created_at, '%%Y-%%m-%%d %%H:00') AS bucket, decision, COUNT(*) AS total FROM {$table} WHERE created_at >= %s GROUP BY bucket, decision",
				$since
			),
			ARRAY_A
		);

		foreach ( (array) $rows as $row ) {
			$bucket = isset( $row['bucket'] ) ? $row['bucket'] : '';
			if ( ! isset( $buckets[ $bucket ] ) ) {
				continue;
			}
			$decision = isset( $buckets[ $bucket ][ $row['decision'] ] ) ? $row['decision'] : 'monitor';
			$buckets[ $bucket ][ $decision ] += (int) $row['total'];
		}

		return array_values( $buckets );
	}
}
