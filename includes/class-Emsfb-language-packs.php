<?php
/**
 * Language packs downloaded at runtime: WhiteStudio first, wordpress.org second.
 *
 * The plugin ships a few languages itself (the "bundled" ones). Every other
 * language the site uses - the site language, each user's language, every
 * language of a multilingual plugin, every site of a network - is downloaded
 * after activation and kept up to date: from the WhiteStudio language server
 * (the ws-language-plugins-handler plugin), and from wordpress.org when
 * WhiteStudio has no pack for that language or cannot be reached.
 *
 * Nothing in this file depends on the plugin that ships it, so it can be
 * copied into another plugin. See docs/language-packs/README.md, "Porting".
 *
 * Rules this class keeps:
 *
 *  1. It never breaks the plugin. Every entry point is wrapped, the network is
 *     only used from WP-Cron, and a failure leaves the translations that were
 *     already installed exactly as they were.
 *  2. WhiteStudio first, wordpress.org second, nothing third.
 *  3. It never installs executable code from the network. WordPress includes
 *     .l10n.php files as PHP, so that file is generated here from the .mo,
 *     never taken from a download.
 *  4. It only replaces translation files that came from WhiteStudio or
 *     wordpress.org. A translation someone made by hand is left alone.
 *  5. It emails the admin once per outage, and only after this site's server
 *     has been unable to reach WhiteStudio for days. An error answered by the
 *     WhiteStudio server itself proves the connection works, so it never
 *     triggers an email.
 */

namespace Emsfb;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Language_Packs {

	/** Written into the X-Generator header of every pack from WhiteStudio. */
	const MARKER = 'WhiteStudio Language Packs';

	const CLIENT_VERSION = '1.0.0';

	/** Sent by the WhiteStudio language server on every response it produces. */
	const SERVER_HEADER = 'x-ws-language-handler';

	const CATALOG_TIMEOUT  = 10;
	const DOWNLOAD_TIMEOUT = 25;

	/** Stop starting new installs after this many seconds; the rest continue in a follow-up event. */
	const TIME_BUDGET = 20;

	const LOCK_TTL          = 600;
	const MAX_PACKAGE_BYTES = 20971520;
	const MAX_FILE_BYTES    = 20971520;
	const MAX_ZIP_ENTRIES   = 500;
	const MAX_SITES_SCANNED = 200;

	/** Unreachable for at least this long (72 hours) and this many attempts before the admin is emailed. */
	const NOTIFY_AFTER        = 259200;
	const NOTIFY_MIN_FAILURES = 3;

	/** At most one email per 30 days, whatever happens. */
	const NOTIFY_COOLDOWN = 2592000;

	const NOTIFY_MAX_SEND_ATTEMPTS = 3;

	/** How often an admin page load checks whether the site started using a new language. */
	const WATCH_INTERVAL = 600;

	/** Page views start at most one background run per hour. */
	const BACKGROUND_THROTTLE = 3600;

	/** No completed run for two days: WP-Cron is not running here, so a page view starts one. */
	const DUE_AFTER = 172800;

	/** The front-end trigger inside a cached page stops firing 30 minutes after the page was built. */
	const TRIGGER_TTL = 1800;

	/** Seconds the admin screen waits for an installation before offering to continue in English. */
	const UI_TIMEOUT = 90;

	/** Same kind of installation failure: one email per 30 days. */
	const INSTALL_MAIL_COOLDOWN = 2592000;

	/** WordPress's own locale format (see wp_get_installed_translations()). */
	const LOCALE_PATTERN = '/^[a-z]{2,3}(?:_[A-Z]{2})?(?:_[a-z0-9]+)?$/';

	/** @var array<string, Language_Packs> */
	private static $instances = array();

	/** @var array */
	private $config;

	/** @var array<string, array> Per-request cache of inspect_installed(). */
	private $installed_cache = array();

	/** @var array<string, bool> Per-request cache of bundled_file_exists(). */
	private $bundled_cache = array();

	/**
	 * Create the instance and hook it into WordPress. Never throws: a broken
	 * configuration only means there are no language packs.
	 *
	 * @param array $config See normalize_config().
	 * @return Language_Packs|null
	 */
	public static function boot( array $config ) {
		try {
			$instance = new self( $config );
			$prefix   = $instance->config['prefix'];
			if ( isset( self::$instances[ $prefix ] ) ) {
				return self::$instances[ $prefix ];
			}
			self::$instances[ $prefix ] = $instance;
			$instance->register_hooks();
			return $instance;
		} catch ( \Throwable $e ) {
			return null;
		}
	}

	/**
	 * @param string $prefix The prefix the instance was booted with.
	 * @return Language_Packs|null
	 */
	public static function instance( $prefix ) {
		return isset( self::$instances[ $prefix ] ) ? self::$instances[ $prefix ] : null;
	}

	/**
	 * @param array $config See normalize_config().
	 * @throws \InvalidArgumentException When a required value is missing.
	 */
	public function __construct( array $config ) {
		$this->config = self::normalize_config( $config );
	}

	/**
	 * @return array
	 */
	public function get_config() {
		return $this->config;
	}

	/**
	 * Configuration keys:
	 *
	 * - product            Required. Product slug on the WhiteStudio language server.
	 * - text_domain        Required. The plugin's text domain.
	 * - version            Required. The plugin's version.
	 * - plugin_file        Required. Main plugin file (activation/deactivation hooks).
	 * - prefix             Required. Prefix of every option, event and filter ([a-z0-9_]).
	 * - servers            Required. Base URLs of the WhiteStudio server, tried in order.
	 * - plugin_name        Name used in the email.
	 * - wporg_slug         Slug on wordpress.org; '' turns the wordpress.org fallback off.
	 * - bundled_locales    Exact locales the plugin ships itself; never downloaded. en_US is always one.
	 * - bundled_dir        Folder of the shipped translation files; those win over downloaded ones.
	 * - guide_url          Manual installation guide linked from the email.
	 * - languages_dir      Where packs are installed. Default WP_LANG_DIR/plugins/.
	 * - extra_locales      Callable returning more locales the site uses.
	 * - enabled            false turns every download off.
	 * - notify             false turns the email off.
	 * - debug              true writes a line to the PHP error log for each run.
	 * - inline             false turns the first-visit installer off (then only WP-Cron installs packs).
	 * - capability         Who sees the "installing language files" screen and may start it. Default install_languages.
	 * - blocking_screens   Admin page slugs (?page=) where that screen blocks the page and reloads it when done.
	 *                      Every other admin screen gets a notice that never reloads.
	 * - front_script_handles  Front-end script handles; a page that enqueues one of them can start a background install.
	 *
	 * @param array $config Raw configuration.
	 * @return array
	 * @throws \InvalidArgumentException When a required value is missing.
	 */
	private static function normalize_config( array $config ) {
		$defaults = array(
			'product'           => '',
			'text_domain'       => '',
			'version'           => '',
			'plugin_file'       => '',
			'prefix'            => '',
			'servers'           => array(),
			'plugin_name'       => '',
			'wporg_slug'        => '',
			'bundled_locales'   => array( 'en_US' ),
			'bundled_dir'       => '',
			'guide_url'         => '',
			'languages_dir'     => '',
			'extra_locales'     => null,
			'enabled'           => true,
			'notify'            => true,
			'debug'             => false,
			'inline'            => true,
			'capability'        => 'install_languages',
			'blocking_screens'  => array(),
			'front_script_handles' => array(),
		);
		$c = array_merge( $defaults, $config );

		foreach ( array( 'product', 'text_domain', 'version', 'plugin_file', 'prefix' ) as $required ) {
			$c[ $required ] = trim( (string) $c[ $required ] );
			if ( '' === $c[ $required ] ) {
				throw new \InvalidArgumentException( 'Language_Packs: missing ' . $required );
			}
		}
		if ( ! preg_match( '/^[a-z0-9_\-]+$/', $c['product'] ) || ! preg_match( '/^[a-z0-9_]+$/', $c['prefix'] ) ) {
			throw new \InvalidArgumentException( 'Language_Packs: invalid product or prefix' );
		}

		$servers = array();
		foreach ( (array) $c['servers'] as $server ) {
			$server = untrailingslashit( trim( (string) $server ) );
			if ( preg_match( '#^https?://[^/\s]+#i', $server ) ) {
				$servers[] = $server;
			}
		}
		if ( empty( $servers ) ) {
			throw new \InvalidArgumentException( 'Language_Packs: no server' );
		}
		$c['servers'] = $servers;

		$c['plugin_name']       = '' !== trim( (string) $c['plugin_name'] ) ? (string) $c['plugin_name'] : $c['text_domain'];
		$c['wporg_slug']        = preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $c['wporg_slug'] ) );
		$bundled = array( 'en_US' );
		foreach ( (array) $c['bundled_locales'] as $locale ) {
			if ( preg_match( self::LOCALE_PATTERN, (string) $locale ) ) {
				$bundled[] = (string) $locale;
			}
		}
		$c['bundled_locales'] = array_values( array_unique( $bundled ) );
		$c['bundled_dir']       = '' === (string) $c['bundled_dir'] ? '' : trailingslashit( (string) $c['bundled_dir'] );
		$c['guide_url']         = (string) $c['guide_url'];

		$languages_dir = (string) $c['languages_dir'];
		if ( '' === $languages_dir ) {
			$languages_dir = WP_LANG_DIR . '/plugins/';
		}
		$c['languages_dir'] = trailingslashit( $languages_dir );

		$c['extra_locales'] = is_callable( $c['extra_locales'] ) || is_string( $c['extra_locales'] ) ? $c['extra_locales'] : null;
		$c['enabled']       = (bool) $c['enabled'];
		$c['notify']        = (bool) $c['notify'];
		$c['debug']         = (bool) $c['debug'];
		$c['inline']        = (bool) $c['inline'];
		$c['capability']    = '' !== (string) $c['capability'] ? (string) $c['capability'] : 'install_languages';
		$c['blocking_screens']     = array_values( array_map( 'strval', (array) $c['blocking_screens'] ) );
		$c['front_script_handles'] = array_values( array_map( 'strval', (array) $c['front_script_handles'] ) );

		return $c;
	}

	/**
	 * @return void
	 */
	public function register_hooks() {
		$file = $this->config['plugin_file'];

		register_activation_hook( $file, array( $this, 'on_activation' ) );
		register_deactivation_hook( $file, array( $this, 'on_deactivation' ) );

		add_action( $this->key( 'sync' ), array( $this, 'run_sync' ), 10, 1 );
		add_action( $this->key( 'daily' ), array( $this, 'run_daily' ) );
		add_action( 'init', array( $this, 'maybe_schedule' ), 20 );
		add_action( 'admin_init', array( $this, 'watch' ) );
		add_action( 'update_option_WPLANG', array( $this, 'on_site_language_changed' ) );
		add_action( 'update_site_option_WPLANG', array( $this, 'on_site_language_changed' ) );
		add_action( 'upgrader_process_complete', array( $this, 'on_upgrade' ), 10, 2 );

		add_filter( 'site_transient_update_plugins', array( $this, 'filter_update_offers' ) );
		add_filter( 'lang_dir_for_domain', array( $this, 'filter_lang_dir' ), 10, 3 );

		// The first visit installs a missing pack itself: some servers never run WP-Cron.
		add_action( 'admin_notices', array( $this, 'admin_notice' ) );
		add_action( 'admin_footer', array( $this, 'admin_footer' ) );
		add_action( 'wp_footer', array( $this, 'front_footer' ), 100 );
		add_action( 'wp_ajax_' . $this->key( 'install' ), array( $this, 'ajax_install' ) );
		add_action( 'wp_ajax_' . $this->key( 'background' ), array( $this, 'ajax_background' ) );
		add_action( 'wp_ajax_nopriv_' . $this->key( 'background' ), array( $this, 'ajax_background' ) );
	}

	/*
	 * ---------------------------------------------------------------------
	 * Scheduling
	 * ---------------------------------------------------------------------
	 */

	/**
	 * Activation only schedules; the download happens in WP-Cron shortly after,
	 * never inside the activation request.
	 *
	 * @return void
	 */
	public function on_activation() {
		$this->guard(
			function () {
				$this->schedule_sync( 30, 'activation' );
			}
		);
	}

	/**
	 * Drops the schedule. Installed packs and the state stay: a reactivation
	 * should not download everything again.
	 *
	 * @return void
	 */
	public function on_deactivation() {
		$this->guard(
			function () {
				wp_clear_scheduled_hook( $this->key( 'daily' ) );
				foreach ( array( 'activation', 'version', 'locales', 'retry', 'continue', 'manual' ) as $reason ) {
					wp_clear_scheduled_hook( $this->key( 'sync' ), array( $reason ) );
				}
				$this->release_lock();
			}
		);
	}

	/**
	 * init: keep the daily event scheduled, or remove it when the feature is off.
	 *
	 * @return void
	 */
	public function maybe_schedule() {
		$this->guard(
			function () {
				$hook = $this->key( 'daily' );
				if ( ! $this->is_enabled() ) {
					if ( wp_next_scheduled( $hook ) ) {
						wp_clear_scheduled_hook( $hook );
					}
					return;
				}
				if ( ! wp_next_scheduled( $hook ) ) {
					// Spread the first run so a release does not bring every site to the server at once.
					wp_schedule_event( time() + wp_rand( HOUR_IN_SECONDS, 12 * HOUR_IN_SECONDS ), 'daily', $hook );
				}
			}
		);
	}

	/**
	 * admin_init, at most every WATCH_INTERVAL: sync soon when the plugin was
	 * updated (by any route, FTP included) or the site started using a language
	 * it has no pack for yet.
	 *
	 * @return void
	 */
	public function watch() {
		$this->guard(
			function () {
				if ( ! $this->is_enabled() || ( function_exists( 'wp_doing_ajax' ) && wp_doing_ajax() ) ) {
					return;
				}
				$throttle = $this->key( 'watched' );
				if ( get_site_transient( $throttle ) ) {
					return;
				}
				set_site_transient( $throttle, 1, self::WATCH_INTERVAL );

				$state = $this->get_state();
				if ( $state['synced_version'] !== $this->config['version'] ) {
					$this->schedule_sync( 60, 'version' );
					return;
				}
				$new = array_diff( $this->target_locales( true ), (array) $state['locales'] );
				if ( ! empty( $new ) ) {
					$this->schedule_sync( 30, 'locales' );
				}
			}
		);
	}

	/**
	 * @return void
	 */
	public function on_site_language_changed() {
		$this->guard(
			function () {
				$this->schedule_sync( 30, 'locales' );
			}
		);
	}

	/**
	 * @param object $upgrader Unused.
	 * @param array  $options  Upgrade details.
	 * @return void
	 */
	public function on_upgrade( $upgrader, $options ) {
		$this->guard(
			function () use ( $options ) {
				if ( ! is_array( $options ) || ! isset( $options['type'], $options['action'] ) || 'plugin' !== $options['type'] || 'update' !== $options['action'] ) {
					return;
				}
				$plugins = isset( $options['plugins'] ) ? (array) $options['plugins'] : ( isset( $options['plugin'] ) ? array( $options['plugin'] ) : array() );
				if ( in_array( plugin_basename( $this->config['plugin_file'] ), $plugins, true ) ) {
					$this->schedule_sync( 60, 'version' );
				}
			}
		);
	}

	/**
	 * @param int    $delay  Seconds from now.
	 * @param string $reason activation|version|locales|retry|continue|manual.
	 * @return bool
	 */
	private function schedule_sync( $delay, $reason ) {
		if ( ! $this->is_enabled() ) {
			return false;
		}
		$hook = $this->key( 'sync' );
		$args = array( (string) $reason );
		if ( wp_next_scheduled( $hook, $args ) ) {
			return true;
		}
		return (bool) wp_schedule_single_event( time() + max( 0, (int) $delay ), $hook, $args );
	}

	/**
	 * @param string $reason Why the event was scheduled.
	 * @return void
	 */
	public function run_sync( $reason = 'manual' ) {
		$this->guard(
			function () use ( $reason ) {
				$this->sync( (string) $reason );
			}
		);
	}

	/**
	 * @return void
	 */
	public function run_daily() {
		$this->run_sync( 'daily' );
	}

	/*
	 * ---------------------------------------------------------------------
	 * The sync
	 * ---------------------------------------------------------------------
	 */

	/**
	 * Bring the pack of every language this site uses up to date.
	 *
	 * @param string $reason  Why it runs (daily, activation, version, locales, retry, continue, manual, inline, background).
	 * @param array  $options first: a locale to handle before the others.
	 * @return array Summary: status, ws, wporg, results (locale => outcome), errors (locale => kind, detail),
	 *               ws_error, blocker, pending, installed, notified.
	 */
	public function sync( $reason = 'manual', array $options = array() ) {
		$summary = array(
			'status'    => '',
			'ws'        => '',
			'wporg'     => '',
			'results'   => array(),
			'errors'    => array(),
			'ws_error'  => null,
			'blocker'   => '',
			'pending'   => array(),
			'installed' => array(),
			'notified'  => false,
		);

		if ( ! $this->is_enabled() ) {
			$summary['status'] = 'disabled';
			return $summary;
		}

		$state = $this->get_state();

		// On a network every site runs this event, but the language folder is shared: one daily run is enough.
		if ( 'daily' === $reason && is_multisite() && 'complete' === $state['last_run']['status']
			&& $state['synced_version'] === $this->config['version']
			&& ( time() - (int) $state['last_run']['at'] ) < 20 * HOUR_IN_SECONDS ) {
			$summary['status'] = 'recent';
			return $summary;
		}

		if ( ! $this->acquire_lock() ) {
			$summary['status'] = 'locked';
			return $summary;
		}

		try {
			if ( function_exists( 'set_time_limit' ) ) {
				@set_time_limit( 120 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			}

			$started = time();
			$locales = $this->target_locales();
			if ( ! empty( $options['first'] ) && in_array( $options['first'], $locales, true ) ) {
				$locales = array_values( array_unique( array_merge( array( (string) $options['first'] ), $locales ) ) );
			}

			$state['locales']        = $locales;
			$state['synced_version'] = $this->config['version'];
			$state['last_run']       = array(
				'at'      => $started,
				'reason'  => (string) $reason,
				'status'  => '',
				'results' => array(),
			);

			if ( empty( $locales ) ) {
				$state['last_run']['status'] = 'complete';
				$summary['status']           = 'no_locales';
				$this->save_state( $state );
				$this->record_completed_sync();
				return $summary;
			}

			$blocker = $this->write_blocker();
			if ( '' !== $blocker ) {
				$state['last_run']['status'] = $blocker;
				$summary['status']           = $blocker;
				$summary['blocker']          = $blocker;
				$this->save_state( $state );
				$this->record_completed_sync();
				$this->log( 'not installing anything: ' . $blocker );
				return $summary;
			}

			$ws      = $this->fetch_ws_catalog( $locales );
			$ws_ok   = 'ok' === $ws['status'];
			$results = array();
			$errors  = array();
			$pending = array();
			if ( ! $ws_ok ) {
				$summary['ws_error'] = array(
					'kind'   => 'unreachable' === $ws['status'] ? 'unreachable' : 'server',
					'detail' => (string) $ws['error'],
				);
			}
			$wporg_candidates = array();
			$ws_download_blocked = false;
			$ws_download_worked  = false;

			foreach ( $locales as $locale ) {
				$installed = $this->inspect_installed( $locale );

				if ( $ws_ok && isset( $ws['translations'][ $locale ] ) ) {
					if ( 'other' === $installed['source'] ) {
						$results[ $locale ] = 'kept_custom';
						continue;
					}
					$entry = $ws['translations'][ $locale ];
					if ( ! $this->needs_install( $locale, $entry, $installed, true, $state['packs'] ) ) {
						$results[ $locale ] = 'up_to_date_ws';
						continue;
					}
					if ( ( time() - $started ) > self::TIME_BUDGET ) {
						$pending[] = $locale;
						continue;
					}
					$outcome = $this->install( $entry, $locale );
					if ( $outcome['ok'] ) {
						$results[ $locale ]  = 'installed_ws';
						$ws_download_worked = true;
						$state['packs'][ $locale ] = $outcome['record'];
						$summary['installed'][]    = $locale;
						continue;
					}
					$results[ $locale ] = 'failed_ws:' . $outcome['error'];
					$errors[ $locale ]  = array(
						'kind'   => $outcome['kind'],
						'detail' => $outcome['detail'],
					);
					if ( 'unreachable' === $outcome['kind'] ) {
						$ws_download_blocked = true;
						$ws['error']         = $outcome['detail'];
					}
					// What is installed stays. Only a language with nothing at all goes to wordpress.org.
					if ( ! $installed['exists'] ) {
						$wporg_candidates[] = $locale;
					}
					continue;
				}

				if ( ! $ws_ok && 'ws' === $installed['source'] ) {
					$results[ $locale ] = 'kept_ws';
					continue;
				}
				if ( 'other' === $installed['source'] ) {
					$results[ $locale ] = 'kept_custom';
					continue;
				}
				$wporg_candidates[] = $locale;
			}

			$wporg_status = 'not_needed';
			if ( ! empty( $wporg_candidates ) && '' === $this->config['wporg_slug'] ) {
				$wporg_status = 'off';
				foreach ( $wporg_candidates as $locale ) {
					$results[ $locale ] = 'not_available';
				}
			} elseif ( ! empty( $wporg_candidates ) ) {
				$wporg        = $this->fetch_wporg_catalog( $wporg_candidates );
				$wporg_status = $wporg['status'];
				$state['wporg'] = array(
					'status'          => $wporg['status'],
					'last_attempt_at' => time(),
					'last_error'      => $wporg['error'],
				);
				foreach ( $wporg_candidates as $locale ) {
					if ( 'ok' !== $wporg['status'] ) {
						$results[ $locale ] = 'wporg_' . $wporg['status'];
						if ( ! isset( $errors[ $locale ] ) ) {
							$errors[ $locale ] = array(
								'kind'   => 'unreachable' === $wporg['status'] ? 'unreachable' : 'server',
								'detail' => 'wordpress.org: ' . $wporg['error'],
							);
						}
						continue;
					}
					if ( ! isset( $wporg['translations'][ $locale ] ) ) {
						$results[ $locale ] = isset( $results[ $locale ] ) ? $results[ $locale ] : 'not_available';
						continue;
					}
					$installed = $this->inspect_installed( $locale );
					$entry     = $wporg['translations'][ $locale ];
					if ( ! $this->needs_install( $locale, $entry, $installed, $ws_ok, $state['packs'] ) ) {
						$results[ $locale ] = 'up_to_date_wporg';
						continue;
					}
					if ( ( time() - $started ) > self::TIME_BUDGET ) {
						$pending[] = $locale;
						continue;
					}
					$outcome            = $this->install( $entry, $locale );
					$results[ $locale ] = $outcome['ok'] ? 'installed_wporg' : 'failed_wporg:' . $outcome['error'];
					if ( $outcome['ok'] ) {
						$state['packs'][ $locale ] = $outcome['record'];
						$summary['installed'][]    = $locale;
						unset( $errors[ $locale ] );
					} else {
						$errors[ $locale ] = array(
							'kind'   => $outcome['kind'],
							'detail' => $outcome['detail'],
						);
					}
				}
			}

			// A catalog that loads while every package download is intercepted is still "cannot connect".
			$ws_status = $ws['status'];
			if ( $ws_ok && $ws_download_blocked && ! $ws_download_worked ) {
				$ws_status = 'unreachable';
			}
			$this->record_ws_health( $state, $ws_status, $ws['error'], $ws['server'] );

			$state['last_run']['status']  = empty( $pending ) ? 'complete' : 'partial';
			$state['last_run']['results'] = $results;

			$summary['status']  = $state['last_run']['status'];
			$summary['ws']      = $ws_status;
			$summary['wporg']   = $wporg_status;
			$summary['results'] = $results;
			$summary['errors']  = $errors;
			$summary['pending'] = $pending;
			if ( 'unreachable' === $ws_status && null === $summary['ws_error'] ) {
				$summary['ws_error'] = array(
					'kind'   => 'unreachable',
					'detail' => (string) $ws['error'],
				);
			}

			if ( 'unreachable' === $ws_status ) {
				$this->schedule_retry( (int) $state['ws']['failures'] );
				$summary['notified'] = $this->maybe_notify( $state, $locales, $wporg_status );
			}
			if ( ! empty( $pending ) ) {
				$this->schedule_sync( 60, 'continue' );
			}

			$this->save_state( $state );
			$this->record_completed_sync();
			$this->log( 'run ' . $reason . ': ws=' . $ws_status . ' wporg=' . $wporg_status . ' ' . wp_json_encode( $results ) );
		} finally {
			$this->release_lock();
		}

		if ( ! empty( $summary['installed'] ) ) {
			/**
			 * Fires after language packs were installed, so the plugin can drop caches built with the old strings.
			 *
			 * @param string[] $locales Locales that received a pack.
			 */
			do_action( $this->key( 'installed' ), $summary['installed'] );
		}

		return $summary;
	}

	/**
	 * @param string $locale    Locale.
	 * @param array  $entry     Catalog entry (source ws or wporg).
	 * @param array  $installed inspect_installed() result.
	 * @param bool   $ws_ok     Whether the WhiteStudio catalog loaded in this run.
	 * @param array  $packs     Install records by locale.
	 * @return bool
	 */
	private function needs_install( $locale, array $entry, array $installed, $ws_ok, array $packs ) {
		if ( ! $installed['exists'] ) {
			return true;
		}
		if ( 'other' === $installed['source'] ) {
			return false;
		}

		$remote_updated = $this->timestamp( $entry['updated'] );

		if ( 'ws' === $entry['source'] ) {
			if ( 'ws' !== $installed['source'] ) {
				return true;
			}
			$record = isset( $packs[ $locale ] ) && is_array( $packs[ $locale ] ) ? $packs[ $locale ] : null;
			if ( $record && ! empty( $record['sha256'] ) && 'ws' === $record['source'] ) {
				return $record['sha256'] !== $entry['sha256'];
			}
			return $remote_updated > $installed['updated'];
		}

		// wordpress.org. A WhiteStudio pack is only handed back when WhiteStudio answered and no longer has it.
		if ( 'ws' === $installed['source'] ) {
			return (bool) $ws_ok;
		}
		return $remote_updated > $installed['updated'];
	}

	/**
	 * @param array  $state  State, updated in place.
	 * @param string $status ok|unreachable|server_error.
	 * @param string $error  Error detail.
	 * @param string $server Server that answered or failed.
	 * @return void
	 */
	private function record_ws_health( array &$state, $status, $error, $server ) {
		$now = time();
		$ws  = $state['ws'];

		$ws['last_attempt_at'] = $now;
		$ws['server']          = (string) $server;

		if ( 'unreachable' === $status ) {
			$ws['status']           = 'unreachable';
			$ws['failures']         = (int) $ws['failures'] + 1;
			$ws['first_failure_at'] = (int) $ws['first_failure_at'] > 0 ? (int) $ws['first_failure_at'] : $now;
			$ws['last_error']       = (string) $error;
		} else {
			// Any answer from the WhiteStudio server - an error included - proves this site can reach it.
			$ws['status']           = $status;
			$ws['failures']         = 0;
			$ws['first_failure_at'] = 0;
			$ws['last_error']       = 'ok' === $status ? '' : (string) $error;
			if ( 'ok' === $status ) {
				$ws['last_success_at'] = $now;
			}
			$state['notice']['episode_sent_at']  = 0;
			$state['notice']['episode_attempts'] = 0;
		}

		$state['ws'] = $ws;
	}

	/**
	 * @param int $failures Consecutive failures so far.
	 * @return void
	 */
	private function schedule_retry( $failures ) {
		$delays = array(
			1 => HOUR_IN_SECONDS,
			2 => 3 * HOUR_IN_SECONDS,
			3 => 6 * HOUR_IN_SECONDS,
			4 => 12 * HOUR_IN_SECONDS,
		);
		$this->schedule_sync( isset( $delays[ $failures ] ) ? $delays[ $failures ] : DAY_IN_SECONDS, 'retry' );
	}

	/*
	 * ---------------------------------------------------------------------
	 * Which languages
	 * ---------------------------------------------------------------------
	 */

	/**
	 * Every locale the site uses, minus English and the bundled languages.
	 *
	 * @param bool $light Skip the per-user and per-site lookups (used on admin page loads).
	 * @return string[]
	 */
	public function target_locales( $light = false ) {
		$found = array( get_locale(), (string) get_option( 'WPLANG', '' ) );

		if ( function_exists( 'get_available_languages' ) ) {
			$found = array_merge( $found, (array) get_available_languages() );
		}

		if ( ! $light ) {
			global $wpdb;
			if ( isset( $wpdb ) && is_object( $wpdb ) && ! empty( $wpdb->usermeta ) ) {
				$user_locales = $wpdb->get_col( $wpdb->prepare( "SELECT DISTINCT meta_value FROM {$wpdb->usermeta} WHERE meta_key = %s AND meta_value <> ''", 'locale' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$found        = array_merge( $found, (array) $user_locales );
			}
			if ( is_multisite() ) {
				$found[] = (string) get_site_option( 'WPLANG', '' );
				if ( function_exists( 'get_sites' ) ) {
					$site_ids = get_sites(
						array(
							'fields' => 'ids',
							'number' => self::MAX_SITES_SCANNED,
						)
					);
					foreach ( (array) $site_ids as $site_id ) {
						$found[] = (string) get_blog_option( $site_id, 'WPLANG', '' );
					}
				}
			}
		}

		// Polylang.
		if ( function_exists( 'pll_languages_list' ) ) {
			$found = array_merge( $found, (array) pll_languages_list( array( 'fields' => 'locale' ) ) );
		}

		// WPML.
		if ( defined( 'ICL_SITEPRESS_VERSION' ) || has_filter( 'wpml_active_languages' ) ) {
			$wpml = apply_filters( 'wpml_active_languages', null, array( 'skip_missing' => 0 ) );
			foreach ( is_array( $wpml ) ? $wpml : array() as $language ) {
				if ( is_array( $language ) && ! empty( $language['default_locale'] ) ) {
					$found[] = (string) $language['default_locale'];
				}
			}
		}

		// TranslatePress.
		$trp = get_option( 'trp_settings' );
		if ( is_array( $trp ) ) {
			if ( ! empty( $trp['translation-languages'] ) && is_array( $trp['translation-languages'] ) ) {
				$found = array_merge( $found, $trp['translation-languages'] );
			}
			if ( ! empty( $trp['default-language'] ) ) {
				$found[] = (string) $trp['default-language'];
			}
		}

		if ( ! $light && null !== $this->config['extra_locales'] && is_callable( $this->config['extra_locales'] ) ) {
			$found = array_merge( $found, (array) call_user_func( $this->config['extra_locales'] ) );
		}

		/**
		 * Filters the locales whose language packs are kept up to date.
		 *
		 * @param string[] $found All locales found, before English and bundled languages are removed.
		 * @param bool     $light Whether the per-user and per-site lookups were skipped.
		 */
		$found = (array) apply_filters( $this->key( 'locales' ), $found, $light );

		$locales = array();
		foreach ( $found as $locale ) {
			$locale = trim( (string) $locale );
			if ( 'en_US' === $locale || ! preg_match( self::LOCALE_PATTERN, $locale ) || $this->is_bundled_locale( $locale ) ) {
				continue;
			}
			$locales[ $locale ] = true;
		}
		$locales = array_keys( $locales );
		sort( $locales );

		return $locales;
	}

	/**
	 * Whether the plugin ships this exact locale itself. Exact on purpose: shipping
	 * fa_IR says nothing about fa_AF, and de_DE nothing about de_AT or de_DE_formal.
	 *
	 * @param string $locale Locale.
	 * @return bool
	 */
	public function is_bundled_locale( $locale ) {
		return in_array( (string) $locale, $this->config['bundled_locales'], true );
	}

	/*
	 * ---------------------------------------------------------------------
	 * What is installed
	 * ---------------------------------------------------------------------
	 */

	/**
	 * Where the installed translation for a locale came from, read from the
	 * files themselves so a manual install is recognised too.
	 *
	 * @param string $locale Locale.
	 * @return array{exists: bool, source: string, updated: int} source is ws, wporg, other or ''.
	 */
	public function inspect_installed( $locale ) {
		if ( isset( $this->installed_cache[ $locale ] ) ) {
			return $this->installed_cache[ $locale ];
		}

		$base    = $this->config['languages_dir'] . $this->config['text_domain'] . '-' . $locale;
		$has_mo  = is_file( $base . '.mo' );
		$has_php = is_file( $base . '.l10n.php' );
		$info    = array(
			'exists'  => $has_mo || $has_php,
			'source'  => '',
			'updated' => 0,
		);

		if ( $info['exists'] ) {
			$headers = array();
			try {
				if ( is_file( $base . '.po' ) && function_exists( 'wp_get_pomo_file_data' ) ) {
					$headers = wp_get_pomo_file_data( $base . '.po' );
				} elseif ( $has_php && function_exists( 'wp_get_l10n_php_file_data' ) ) {
					$headers = wp_get_l10n_php_file_data( $base . '.l10n.php' );
				}
			} catch ( \Throwable $e ) {
				// A file that cannot be read is treated as someone else's: it is never replaced.
				$headers = array();
			}
			$generator = isset( $headers['X-Generator'] ) ? trim( (string) $headers['X-Generator'] ) : '';
			if ( 0 === strpos( $generator, self::MARKER ) ) {
				$info['source'] = 'ws';
			} elseif ( 0 === stripos( $generator, 'GlotPress' ) ) {
				$info['source'] = 'wporg';
			} else {
				$info['source'] = 'other';
			}
			$info['updated'] = ! empty( $headers['PO-Revision-Date'] ) ? $this->timestamp( $headers['PO-Revision-Date'] ) : 0;
		}

		$this->installed_cache[ $locale ] = $info;
		return $info;
	}

	/**
	 * Empty string when packs can be written, otherwise the reason they cannot.
	 * Mirrors what WordPress itself requires before it installs a language pack.
	 *
	 * @return string
	 */
	private function write_blocker() {
		if ( function_exists( 'wp_is_file_mod_allowed' ) && ! wp_is_file_mod_allowed( 'download_language_pack' ) ) {
			return 'file_mods_disallowed';
		}
		$dir = $this->config['languages_dir'];
		if ( ! wp_mkdir_p( $dir ) ) {
			return 'languages_dir_missing';
		}
		if ( ! wp_is_writable( $dir ) ) {
			return 'languages_dir_not_writable';
		}
		if ( ! function_exists( 'get_filesystem_method' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}
		// Files written by PHP would belong to the wrong user on a host that needs FTP credentials.
		if ( 'direct' !== get_filesystem_method( array(), $dir ) ) {
			return 'filesystem_not_direct';
		}
		return '';
	}

	/*
	 * ---------------------------------------------------------------------
	 * Catalogs
	 * ---------------------------------------------------------------------
	 */

	/**
	 * GET {server}/wp-json/wl/v1/translations/{product}
	 *
	 * @param string[] $locales Locales wanted.
	 * @return array{status: string, error: string, server: string, translations: array}
	 */
	public function fetch_ws_catalog( array $locales ) {
		$result = array(
			'status'       => 'unreachable',
			'error'        => '',
			'server'       => '',
			'translations' => array(),
		);

		foreach ( $this->servers() as $server ) {
			$url = $server . '/wp-json/wl/v1/translations/' . rawurlencode( $this->config['product'] )
				. '?version=' . rawurlencode( $this->config['version'] )
				. '&locales=' . rawurlencode( implode( ',', $locales ) )
				. '&client=' . rawurlencode( self::CLIENT_VERSION );

			$response = wp_remote_get(
				$url,
				array(
					'timeout'     => self::CATALOG_TIMEOUT,
					'redirection' => 0,
					'sslverify'   => $this->sslverify( $url ),
					'headers'     => array( 'Accept' => 'application/json' ),
				)
			);

			$attempt = $this->classify_ws_response( $response );
			if ( 'ok' === $attempt['status'] ) {
				return array(
					'status'       => 'ok',
					'error'        => '',
					'server'       => $server,
					'translations' => $this->parse_catalog( $attempt['body'], $locales, 'ws', array( $this->host( $server ) ), 0 === stripos( $server, 'http://' ) ),
				);
			}

			// A server that answered with an error outranks one that could not be reached at all.
			if ( 'unreachable' === $result['status'] || '' === $result['server'] ) {
				$result['status'] = $attempt['status'];
				$result['error']  = $attempt['error'];
				$result['server'] = $server;
			}
		}

		return $result;
	}

	/**
	 * Tells "this site cannot connect" apart from "WhiteStudio answered with an error".
	 * Only a response carrying SERVER_HEADER came from the language server; anything
	 * else (a firewall page, a bot challenge, a proxy error) means it was not reached.
	 *
	 * @param array|\WP_Error $response wp_remote_get() result.
	 * @return array{status: string, error: string, body: array}
	 */
	private function classify_ws_response( $response ) {
		if ( is_wp_error( $response ) ) {
			return array(
				'status' => 'unreachable',
				'error'  => $this->http_error_text( $response ),
				'body'   => array(),
			);
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		if ( '' === (string) wp_remote_retrieve_header( $response, self::SERVER_HEADER ) ) {
			$rest_error = $this->wordpress_rest_error( $response );
			if ( '' !== $rest_error ) {
				// WordPress on the server answered (e.g. the handler plugin is not deployed yet): the connection works.
				return array(
					'status' => 'server_error',
					'error'  => sprintf( 'HTTP %d %s', $code, $rest_error ),
					'body'   => array(),
				);
			}
			return array(
				'status' => 'unreachable',
				'error'  => sprintf( 'HTTP %d from something other than the language server (firewall, proxy or bot protection)', $code ),
				'body'   => array(),
			);
		}
		if ( 200 !== $code ) {
			return array(
				'status' => 'server_error',
				'error'  => sprintf( 'HTTP %d', $code ),
				'body'   => array(),
			);
		}

		$body = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $body ) || ! isset( $body['translations'] ) || ! is_array( $body['translations'] ) ) {
			return array(
				'status' => 'server_error',
				'error'  => 'invalid catalog',
				'body'   => array(),
			);
		}

		return array(
			'status' => 'ok',
			'error'  => '',
			'body'   => $body,
		);
	}

	/**
	 * The error code when the body is a WordPress REST API error ({"code":"rest_…"}),
	 * which only WordPress itself produces - a firewall or proxy page never does.
	 *
	 * @param array  $response  wp_remote_get() result.
	 * @param string $body_file When the response was streamed to a file, that file.
	 * @return string '' when it is not one.
	 */
	private function wordpress_rest_error( $response, $body_file = '' ) {
		$body = (string) wp_remote_retrieve_body( $response );
		if ( '' === $body && '' !== $body_file && is_file( $body_file ) && (int) @filesize( $body_file ) < 65536 ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			$body = (string) @file_get_contents( $body_file ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions
		}
		$decoded = json_decode( $body, true );
		if ( is_array( $decoded ) && isset( $decoded['code'] ) && is_string( $decoded['code'] ) && 0 === strpos( $decoded['code'], 'rest_' ) ) {
			return $decoded['code'];
		}
		return '';
	}

	/**
	 * The text of a failed wp_remote_get(), for the email and the message on screen.
	 *
	 * WordPress translates its own error messages into the site language, and this plugin only runs for
	 * a site whose language is not shipped with it, so a phrase in the message cannot be recognised
	 * afterwards ("User has blocked requests through HTTP" arrives in Swedish on a Swedish site).
	 * The error code never changes: a request WordPress refused to send (WP_HTTP_BLOCK_EXTERNAL) is marked with it.
	 *
	 * @param \WP_Error $error Error from wp_remote_get().
	 * @return string
	 */
	private function http_error_text( \WP_Error $error ) {
		$text = (string) $error->get_error_message();
		if ( 'http_request_not_executed' === $error->get_error_code() ) {
			$text .= ' [http_request_not_executed]';
		}
		return $text;
	}

	/**
	 * GET https://api.wordpress.org/translations/plugins/1.0/
	 *
	 * @param string[] $locales Locales wanted.
	 * @return array{status: string, error: string, translations: array}
	 */
	public function fetch_wporg_catalog( array $locales ) {
		$url = 'https://api.wordpress.org/translations/plugins/1.0/?slug=' . rawurlencode( $this->config['wporg_slug'] )
			. '&version=' . rawurlencode( $this->config['version'] );

		$response = wp_remote_get(
			$url,
			array(
				'timeout' => self::CATALOG_TIMEOUT,
				'headers' => array( 'Accept' => 'application/json' ),
			)
		);

		if ( is_wp_error( $response ) ) {
			return array(
				'status'       => 'unreachable',
				'error'        => $this->http_error_text( $response ),
				'translations' => array(),
			);
		}
		$code = (int) wp_remote_retrieve_response_code( $response );
		$body = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		if ( 200 !== $code || ! is_array( $body ) || ! isset( $body['translations'] ) || ! is_array( $body['translations'] ) ) {
			return array(
				'status'       => 'server_error',
				'error'        => sprintf( 'HTTP %d', $code ),
				'translations' => array(),
			);
		}

		return array(
			'status'       => 'ok',
			'error'        => '',
			'translations' => $this->parse_catalog( $body, $locales, 'wporg', array( 'downloads.wordpress.org' ), false ),
		);
	}

	/**
	 * Keep only well-formed entries for wanted locales whose package lives on
	 * the expected host. WhiteStudio entries must carry a checksum.
	 *
	 * @param array    $body       Decoded catalog.
	 * @param string[] $locales    Wanted locales.
	 * @param string   $source     ws|wporg.
	 * @param string[] $hosts      Hosts packages may be downloaded from.
	 * @param bool     $allow_http Whether a plain http package URL is acceptable (local servers only).
	 * @return array<string, array>
	 */
	private function parse_catalog( array $body, array $locales, $source, array $hosts, $allow_http ) {
		$entries = array();
		foreach ( $body['translations'] as $item ) {
			if ( ! is_array( $item ) || empty( $item['language'] ) || empty( $item['package'] ) ) {
				continue;
			}
			$locale = (string) $item['language'];
			if ( ! in_array( $locale, $locales, true ) || isset( $entries[ $locale ] ) ) {
				continue;
			}
			$package = (string) $item['package'];
			$scheme  = strtolower( (string) wp_parse_url( $package, PHP_URL_SCHEME ) );
			if ( ! in_array( $this->host( $package ), $hosts, true ) || ! ( 'https' === $scheme || ( $allow_http && 'http' === $scheme ) ) ) {
				continue;
			}
			$sha256 = isset( $item['sha256'] ) ? strtolower( (string) $item['sha256'] ) : '';
			if ( '' !== $sha256 && ! preg_match( '/^[a-f0-9]{64}$/', $sha256 ) ) {
				continue;
			}
			if ( 'ws' === $source && '' === $sha256 ) {
				continue;
			}
			$entries[ $locale ] = array(
				'source'   => $source,
				'language' => $locale,
				'version'  => isset( $item['version'] ) ? (string) $item['version'] : '',
				'updated'  => isset( $item['updated'] ) ? (string) $item['updated'] : '',
				'package'  => $package,
				'sha256'   => $sha256,
				'size'     => isset( $item['size'] ) ? max( 0, (int) $item['size'] ) : 0,
			);
		}
		return $entries;
	}

	/*
	 * ---------------------------------------------------------------------
	 * Installing a pack
	 * ---------------------------------------------------------------------
	 */

	/**
	 * Download, verify and install one pack. Files are staged next to their
	 * destination and renamed into place only when every one of them is ready.
	 *
	 * @param array  $entry  Catalog entry.
	 * @param string $locale Locale.
	 * @return array{ok: bool, error: string, kind: string, detail: string, record: array}
	 */
	private function install( array $entry, $locale ) {
		$download = $this->download( $entry );
		if ( ! $download['ok'] ) {
			return $download;
		}

		try {
			$package = $this->read_package( $download['path'], $locale, $entry['source'] );
			if ( ! $package['ok'] ) {
				return $package;
			}
			$written = $this->write_files( $package['files'] );
			if ( ! $written['ok'] ) {
				return $written;
			}
			$this->write_php_translation( $locale );
		} finally {
			@unlink( $download['path'] ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		}

		unset( $this->installed_cache[ $locale ] );
		$this->forget_language_file_cache();

		return array(
			'ok'     => true,
			'error'  => '',
			'kind'   => '',
			'detail' => '',
			'record' => array(
				'source'       => $entry['source'],
				'version'      => $entry['version'],
				'updated'      => $entry['updated'],
				'sha256'       => '' !== $entry['sha256'] ? $entry['sha256'] : $download['sha256'],
				'installed_at' => time(),
			),
		);
	}

	/**
	 * @param array $entry Catalog entry.
	 * @return array{ok: bool, error: string, kind: string, detail: string, path: string, sha256: string}
	 */
	private function download( array $entry ) {
		if ( ! function_exists( 'wp_tempnam' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}
		$tmp = wp_tempnam( $this->config['text_domain'] . '-language-pack.zip' );
		if ( ! $tmp ) {
			return $this->failure( 'temp_file', 'write', 'no temporary file' );
		}

		$is_ws    = 'ws' === $entry['source'];
		$response = wp_remote_get(
			$entry['package'],
			array(
				'timeout'             => self::DOWNLOAD_TIMEOUT,
				'stream'              => true,
				'filename'            => $tmp,
				'limit_response_size' => self::MAX_PACKAGE_BYTES,
				'redirection'         => $is_ws ? 0 : 3,
				'sslverify'           => $this->sslverify( $entry['package'] ),
			)
		);

		$fail = function ( $code, $kind, $detail ) use ( $tmp ) {
			@unlink( $tmp ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			return $this->failure( $code, $kind, $detail );
		};

		if ( is_wp_error( $response ) ) {
			return $fail( 'download_failed', 'unreachable', $this->http_error_text( $response ) );
		}
		$code = (int) wp_remote_retrieve_response_code( $response );
		if ( $is_ws && '' === (string) wp_remote_retrieve_header( $response, self::SERVER_HEADER ) ) {
			if ( '' !== $this->wordpress_rest_error( $response, $tmp ) ) {
				return $fail( 'download_http_' . $code, 'server', sprintf( 'HTTP %d from WordPress on the server', $code ) );
			}
			return $fail( 'download_intercepted', 'unreachable', sprintf( 'HTTP %d from something other than the language server', $code ) );
		}
		if ( 200 !== $code ) {
			return $fail( 'download_http_' . $code, 'server', sprintf( 'HTTP %d', $code ) );
		}

		clearstatcache( true, $tmp );
		$size = (int) @filesize( $tmp ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		if ( $size <= 0 || $size >= self::MAX_PACKAGE_BYTES ) {
			return $fail( 'package_size', 'package', (string) $size );
		}
		if ( $entry['size'] > 0 && $size !== $entry['size'] ) {
			return $fail( 'size_mismatch', 'package', $size . ' != ' . $entry['size'] );
		}
		if ( ! function_exists( 'hash_file' ) ) {
			return $fail( 'no_hash', 'package', 'hash_file() is not available' );
		}
		$sha256 = (string) hash_file( 'sha256', $tmp );
		if ( '' !== $entry['sha256'] && ! hash_equals( $entry['sha256'], $sha256 ) ) {
			return $fail( 'checksum_mismatch', 'package', $sha256 );
		}

		return array(
			'ok'     => true,
			'error'  => '',
			'kind'   => '',
			'detail' => '',
			'path'   => $tmp,
			'sha256' => $sha256,
		);
	}

	/**
	 * Read only the files that belong to this text domain and locale, from the
	 * top level of the archive: {domain}-{locale}.po, .mo and
	 * {domain}-{locale}-{md5}.json. Anything else - PHP, folders, other
	 * domains - is ignored.
	 *
	 * @param string $zip_path Downloaded archive.
	 * @param string $locale   Locale.
	 * @param string $source   ws|wporg.
	 * @return array{ok: bool, error: string, kind: string, detail: string, files: array<string, string>}
	 */
	private function read_package( $zip_path, $locale, $source ) {
		$prefix = $this->config['text_domain'] . '-' . $locale;
		$wanted = function ( $name ) use ( $prefix ) {
			return $name === $prefix . '.po'
				|| $name === $prefix . '.mo'
				|| 1 === preg_match( '/^' . preg_quote( $prefix, '/' ) . '-[a-f0-9]{32}\.json$/', $name );
		};

		$entries = $this->zip_entries( $zip_path, $wanted );
		if ( isset( $entries['ok'] ) && false === $entries['ok'] ) {
			return $entries;
		}

		if ( ! isset( $entries[ $prefix . '.mo' ], $entries[ $prefix . '.po' ] ) ) {
			return $this->failure( 'incomplete_package', 'package', 'a .po and a .mo are required' );
		}
		$magic = substr( $entries[ $prefix . '.mo' ], 0, 4 );
		if ( strlen( $entries[ $prefix . '.mo' ] ) < 28 || ( "\xde\x12\x04\x95" !== $magic && "\x95\x04\x12\xde" !== $magic ) ) {
			return $this->failure( 'invalid_mo', 'package', 'not an MO file' );
		}
		if ( false === strpos( $entries[ $prefix . '.po' ], 'msgid' ) ) {
			return $this->failure( 'invalid_po', 'package', 'not a PO file' );
		}

		$files = array();
		foreach ( $entries as $name => $contents ) {
			if ( '.json' === substr( $name, -5 ) ) {
				// Re-encoded, so a script translation can only ever be JSON data.
				$data = json_decode( $contents, true );
				if ( ! is_array( $data ) ) {
					continue;
				}
				$contents = wp_json_encode( $data, JSON_UNESCAPED_UNICODE );
				if ( ! is_string( $contents ) ) {
					continue;
				}
			} elseif ( '.po' === substr( $name, -3 ) && 'ws' === $source ) {
				$contents = self::stamp_po_generator( $contents );
			}
			$files[ $name ] = $contents;
		}

		return array(
			'ok'     => true,
			'error'  => '',
			'kind'   => '',
			'detail' => '',
			'files'  => $files,
		);
	}

	/**
	 * @param string   $zip_path Archive.
	 * @param callable $wanted   Returns true for entry names to read.
	 * @return array<string, string>|array Entry contents by name, or a failure.
	 */
	private function zip_entries( $zip_path, $wanted ) {
		$found = array();

		if ( class_exists( 'ZipArchive' ) ) {
			$zip = new \ZipArchive();
			if ( true !== $zip->open( $zip_path ) ) {
				return $this->failure( 'invalid_zip', 'package', 'cannot open the archive' );
			}
			$count = min( (int) $zip->numFiles, self::MAX_ZIP_ENTRIES );
			for ( $i = 0; $i < $count; $i++ ) {
				$stat = $zip->statIndex( $i );
				$name = is_array( $stat ) && isset( $stat['name'] ) ? (string) $stat['name'] : '';
				if ( '' === $name || false !== strpbrk( $name, '/\\' ) || ! $wanted( $name ) ) {
					continue;
				}
				if ( (int) $stat['size'] > self::MAX_FILE_BYTES ) {
					$zip->close();
					return $this->failure( 'file_too_large', 'package', $name );
				}
				$contents = $zip->getFromIndex( $i );
				if ( false === $contents ) {
					$zip->close();
					return $this->failure( 'unreadable_entry', 'package', $name );
				}
				$found[ $name ] = $contents;
			}
			$zip->close();
			return $found;
		}

		if ( ! class_exists( 'PclZip' ) ) {
			require_once ABSPATH . 'wp-admin/includes/class-pclzip.php';
		}
		$archive = new \PclZip( $zip_path );
		$list    = $archive->listContent();
		if ( ! is_array( $list ) ) {
			return $this->failure( 'invalid_zip', 'package', 'cannot open the archive' );
		}
		$indexes = array();
		foreach ( array_slice( $list, 0, self::MAX_ZIP_ENTRIES ) as $item ) {
			$name = isset( $item['stored_filename'] ) ? (string) $item['stored_filename'] : '';
			if ( '' === $name || ! empty( $item['folder'] ) || false !== strpbrk( $name, '/\\' ) || ! $wanted( $name ) ) {
				continue;
			}
			if ( (int) $item['size'] > self::MAX_FILE_BYTES ) {
				return $this->failure( 'file_too_large', 'package', $name );
			}
			$indexes[] = (int) $item['index'];
		}
		if ( empty( $indexes ) ) {
			return $found;
		}
		$extracted = $archive->extract( PCLZIP_OPT_BY_INDEX, implode( ',', $indexes ), PCLZIP_OPT_EXTRACT_AS_STRING );
		foreach ( is_array( $extracted ) ? $extracted : array() as $item ) {
			if ( isset( $item['stored_filename'], $item['content'] ) ) {
				$found[ (string) $item['stored_filename'] ] = (string) $item['content'];
			}
		}
		return $found;
	}

	/**
	 * Mark a .po as coming from WhiteStudio, so the pack is recognised later
	 * however it was installed.
	 *
	 * @param string $po PO file contents.
	 * @return string
	 */
	public static function stamp_po_generator( $po ) {
		$line = '"X-Generator: ' . self::MARKER . ' ' . self::CLIENT_VERSION . '\n"';
		if ( preg_match( '/^"X-Generator:[^\r\n]*$/m', $po ) ) {
			return (string) preg_replace_callback(
				'/^"X-Generator:[^\r\n]*$/m',
				function () use ( $line ) {
					return $line;
				},
				$po,
				1
			);
		}
		// No generator header yet: add one right after the header entry's msgstr "".
		return (string) preg_replace_callback(
			'/^msgstr ""\r?\n/m',
			function ( $match ) use ( $line ) {
				return $match[0] . $line . "\n";
			},
			$po,
			1
		);
	}

	/**
	 * @param array<string, string> $files File name => contents.
	 * @return array{ok: bool, error: string, kind: string, detail: string}
	 */
	private function write_files( array $files ) {
		$dir = $this->config['languages_dir'];
		if ( ! wp_mkdir_p( $dir ) ) {
			return $this->failure( 'languages_dir_missing', 'write', $dir );
		}

		// Script translations first, the .po last: until the new .po is in place
		// the pack still looks like the old one, so an interrupted run is retried.
		uksort(
			$files,
			function ( $a, $b ) {
				$rank = function ( $name ) {
					if ( '.json' === substr( $name, -5 ) ) {
						return 0;
					}
					return '.mo' === substr( $name, -3 ) ? 1 : 2;
				};
				return $rank( $a ) - $rank( $b );
			}
		);

		$staged = array();
		foreach ( $files as $name => $contents ) {
			$tmp = $this->stage( $dir . $name, $contents );
			if ( '' === $tmp ) {
				foreach ( $staged as $path ) {
					@unlink( $path ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
				}
				return $this->failure( 'write_failed', 'write', $name );
			}
			$staged[ $name ] = $tmp;
		}

		foreach ( $staged as $name => $tmp ) {
			unset( $staged[ $name ] );
			if ( ! @rename( $tmp, $dir . $name ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
				@unlink( $tmp ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
				foreach ( $staged as $path ) {
					@unlink( $path ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
				}
				return $this->failure( 'rename_failed', 'write', $name );
			}
		}

		return array(
			'ok'     => true,
			'error'  => '',
			'kind'   => '',
			'detail' => '',
		);
	}

	/**
	 * Write contents to a temporary file beside $destination.
	 *
	 * @param string $destination Final path.
	 * @param string $contents    Contents.
	 * @return string Temporary path, or '' on failure.
	 */
	private function stage( $destination, $contents ) {
		$tmp = $destination . '.' . substr( md5( uniqid( '', true ) ), 0, 8 ) . '.tmp';
		if ( false === @file_put_contents( $tmp, $contents, LOCK_EX ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions
			@unlink( $tmp ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			return '';
		}
		$mode = defined( 'FS_CHMOD_FILE' ) ? FS_CHMOD_FILE : ( ( @fileperms( ABSPATH . 'index.php' ) & 0777 ) | 0644 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		@chmod( $tmp, $mode ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		return $tmp;
	}

	/**
	 * Generate {domain}-{locale}.l10n.php from the installed .mo. When that is
	 * not possible, remove an old one: WordPress prefers it over the .mo, so a
	 * stale file would hide the new translation.
	 *
	 * @param string $locale Locale.
	 * @return void
	 */
	private function write_php_translation( $locale ) {
		$base     = $this->config['languages_dir'] . $this->config['text_domain'] . '-' . $locale;
		$php_path = $base . '.l10n.php';
		$contents = false;

		if ( class_exists( 'WP_Translation_File' ) && method_exists( 'WP_Translation_File', 'transform' ) ) {
			$contents = \WP_Translation_File::transform( $base . '.mo', 'php' );
		}

		if ( is_string( $contents ) && '' !== $contents ) {
			$tmp = $this->stage( $php_path, $contents );
			if ( '' !== $tmp && @rename( $tmp, $php_path ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
				if ( function_exists( 'wp_opcache_invalidate' ) ) {
					wp_opcache_invalidate( $php_path, true );
				}
				return;
			}
			if ( '' !== $tmp ) {
				@unlink( $tmp ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			}
		}

		if ( is_file( $php_path ) ) {
			@unlink( $php_path ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		}
	}

	/**
	 * WordPress caches the file list of a languages folder for an hour.
	 *
	 * @return void
	 */
	private function forget_language_file_cache() {
		wp_cache_delete( md5( $this->config['languages_dir'] ), 'translation_files' );
		wp_cache_delete( md5( WP_LANG_DIR . '/plugins/' ), 'translation_files' );
	}

	/*
	 * ---------------------------------------------------------------------
	 * Living next to WordPress's own translation updates
	 * ---------------------------------------------------------------------
	 */

	/**
	 * site_transient_update_plugins: WordPress would replace a WhiteStudio pack
	 * with the wordpress.org one on its next translation update, and download
	 * wordpress.org packs of bundled languages it never uses. Drop those offers.
	 *
	 * @param mixed $transient update_plugins site transient.
	 * @return mixed
	 */
	public function filter_update_offers( $transient ) {
		if ( ! is_object( $transient ) || empty( $transient->translations ) || '' === $this->config['wporg_slug'] ) {
			return $transient;
		}
		try {
			$kept    = array();
			$changed = false;
			foreach ( (array) $transient->translations as $offer ) {
				$o = (array) $offer;
				if ( isset( $o['type'], $o['slug'], $o['language'] ) && 'plugin' === $o['type'] && $this->config['wporg_slug'] === $o['slug'] ) {
					$locale = (string) $o['language'];
					if ( ( $this->is_bundled_locale( $locale ) && $this->bundled_file_exists( $locale ) ) || 'ws' === $this->inspect_installed( $locale )['source'] ) {
						$changed = true;
						continue;
					}
				}
				$kept[] = $offer;
			}
			if ( $changed ) {
				$transient               = clone $transient;
				$transient->translations = $kept;
			}
		} catch ( \Throwable $e ) {
			$this->log( 'filter_update_offers: ' . $e->getMessage() );
		}
		return $transient;
	}

	/**
	 * lang_dir_for_domain: a language the plugin ships is read from the plugin's
	 * own folder, even when wordpress.org has put a pack in WP_LANG_DIR (which
	 * WordPress would otherwise prefer). Does nothing while no bundled file exists.
	 *
	 * @param string|false $path   Folder WordPress found.
	 * @param string       $domain Text domain.
	 * @param string       $locale Locale.
	 * @return string|false
	 */
	public function filter_lang_dir( $path, $domain, $locale ) {
		if ( $domain !== $this->config['text_domain'] || '' === $this->config['bundled_dir'] ) {
			return $path;
		}
		try {
			$locale = (string) $locale;
			if ( $this->is_bundled_locale( $locale ) && $this->bundled_file_exists( $locale ) ) {
				return $this->config['bundled_dir'];
			}
		} catch ( \Throwable $e ) {
			$this->log( 'filter_lang_dir: ' . $e->getMessage() );
		}
		return $path;
	}

	/**
	 * @param string $locale Locale.
	 * @return bool
	 */
	private function bundled_file_exists( $locale ) {
		if ( '' === $this->config['bundled_dir'] ) {
			return false;
		}
		if ( ! isset( $this->bundled_cache[ $locale ] ) ) {
			$base                           = $this->config['bundled_dir'] . $this->config['text_domain'] . '-' . $locale;
			$this->bundled_cache[ $locale ] = is_file( $base . '.l10n.php' ) || is_file( $base . '.mo' );
		}
		return $this->bundled_cache[ $locale ];
	}

	/*
	 * ---------------------------------------------------------------------
	 * The first visit: installing without WP-Cron
	 * ---------------------------------------------------------------------
	 *
	 * Some servers never run WP-Cron (DISABLE_WP_CRON without a server cron, or
	 * loopback requests blocked). So every page view checks - one autoloaded
	 * option and two file checks - whether its own language needs a pack that is
	 * not installed, and the browser starts the installation itself. Neither
	 * WP-Cron nor a loopback request is involved.
	 *
	 * - A screen listed in blocking_screens: an "Installing language files" layer
	 *   covers the page, which reloads once the pack is in.
	 * - Any other admin screen: a notice, and no reload (someone may be typing).
	 * - The front end: nothing is shown and the form is simply in English; a
	 *   background request installs the pack for the next page view.
	 * - When the installation fails because of this site (it cannot connect, or
	 *   cannot write the files), the admin gets an email with the reason and the
	 *   manual installation steps, once per problem per 30 days.
	 *
	 * The screen appears once per locale per plugin version; after that, retries
	 * run in the background, at most once an hour.
	 */

	/** @var bool Whether admin_notice() printed the notice on this screen. */
	private $notice_printed = false;

	/**
	 * @return array{version: string, synced_at: int, attempts: array, bg_at: int}
	 */
	private function get_runtime() {
		$runtime = get_option( $this->key( 'runtime' ), array() );
		return array_merge(
			array(
				'version'   => '',
				'synced_at' => 0,
				'attempts'  => array(),
				'bg_at'     => 0,
			),
			is_array( $runtime ) ? $runtime : array()
		);
	}

	/**
	 * Autoloaded, because every page view reads it.
	 *
	 * @param array $runtime Runtime data.
	 * @return void
	 */
	private function save_runtime( array $runtime ) {
		update_option( $this->key( 'runtime' ), $runtime, true );
	}

	/**
	 * A run finished, whatever it installed: packs are as current as this version can make them.
	 *
	 * @return void
	 */
	private function record_completed_sync() {
		$runtime              = $this->get_runtime();
		$runtime['version']   = $this->config['version'];
		$runtime['synced_at'] = time();
		$this->save_runtime( $runtime );
	}

	/**
	 * @param string $locale Locale.
	 * @return bool Whether a translation file for it is on disk (a shipped one counts).
	 */
	private function pack_file_exists( $locale ) {
		$base = $this->config['languages_dir'] . $this->config['text_domain'] . '-' . $locale;
		return is_file( $base . '.mo' ) || is_file( $base . '.l10n.php' ) || $this->bundled_file_exists( $locale );
	}

	/**
	 * What a page shown in $locale should do about its translation.
	 *
	 * @param string $locale Locale of the page.
	 * @return string install: its pack is missing and was not tried for this plugin version (show the installing screen);
	 *                background: still missing after a try (retry silently, hourly);
	 *                refresh: a run is due, because the plugin was updated or nothing ran for two days (silently);
	 *                '': nothing.
	 */
	public function inline_need( $locale ) {
		$locale = (string) $locale;
		if ( ! $this->config['inline'] || ! $this->is_enabled() || ! preg_match( self::LOCALE_PATTERN, $locale ) || 'en_US' === $locale || $this->is_bundled_locale( $locale ) ) {
			return '';
		}

		$runtime = $this->get_runtime();
		$quiet   = ( time() - (int) $runtime['bg_at'] ) >= self::BACKGROUND_THROTTLE;

		if ( ! $this->pack_file_exists( $locale ) ) {
			$attempt = isset( $runtime['attempts'][ $locale ] ) && is_array( $runtime['attempts'][ $locale ] ) ? $runtime['attempts'][ $locale ] : null;
			if ( null === $attempt || $attempt['version'] !== $this->config['version'] ) {
				return 'install';
			}
			return $quiet ? 'background' : '';
		}

		if ( $quiet && ( $runtime['version'] !== $this->config['version'] || ( time() - (int) $runtime['synced_at'] ) >= self::DUE_AFTER ) ) {
			return 'refresh';
		}
		return '';
	}

	/**
	 * @return string
	 */
	private function request_locale() {
		return function_exists( 'determine_locale' ) ? (string) determine_locale() : (string) get_locale();
	}

	/**
	 * @return bool Whether this admin screen blocks and reloads.
	 */
	private function is_blocking_screen() {
		$page = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		return '' !== $page && in_array( $page, $this->config['blocking_screens'], true );
	}

	/**
	 * admin_notices: the installing notice on screens that do not block.
	 *
	 * @return void
	 */
	public function admin_notice() {
		$this->guard(
			function () {
				if ( $this->is_blocking_screen() || ! current_user_can( $this->config['capability'] ) ) {
					return;
				}
				$locale = $this->request_locale();
				if ( 'install' !== $this->inline_need( $locale ) ) {
					return;
				}
				$this->notice_printed = true;
				printf(
					'<div id="%1$s" class="notice notice-info"><p><span class="spinner is-active" style="float:none;margin:0 8px 0 0;vertical-align:middle;"></span><span class="%1$s-text">%2$s</span></p></div>',
					esc_attr( $this->ui_id() ),
					/* translators: 1: plugin name, 2: language name */
					esc_html( sprintf( __( '%1$s is installing its language files (%2$s)&hellip;', 'easy-form-builder' ), $this->config['plugin_name'], $this->language_name( $locale ) ) )
				);
			}
		);
	}

	/**
	 * admin_footer: the blocking layer and the script that installs, or a silent background trigger.
	 *
	 * @return void
	 */
	public function admin_footer() {
		$this->guard(
			function () {
				$locale = $this->request_locale();
				$need   = $this->inline_need( $locale );
				if ( '' === $need ) {
					return;
				}
				if ( 'install' !== $need || ! current_user_can( $this->config['capability'] ) ) {
					$this->print_background_trigger( $locale, 'admin' );
					return;
				}
				$blocking = $this->is_blocking_screen();
				if ( $blocking ) {
					$this->print_blocking_layer( $locale );
				} elseif ( ! $this->notice_printed ) {
					// This screen prints no admin notices: install without showing anything.
					$this->print_background_trigger( $locale, 'admin' );
					return;
				}
				$this->print_install_script( $locale, $blocking );
			}
		);
	}

	/**
	 * wp_footer: on a page that uses the plugin's front-end script, start a background install.
	 * The page itself is untouched, so its form shows in English until the pack is in.
	 *
	 * @return void
	 */
	public function front_footer() {
		$this->guard(
			function () {
				if ( is_admin() || empty( $this->config['front_script_handles'] ) ) {
					return;
				}
				$used = false;
				foreach ( $this->config['front_script_handles'] as $handle ) {
					if ( wp_script_is( $handle, 'enqueued' ) || wp_script_is( $handle, 'done' ) ) {
						$used = true;
						break;
					}
				}
				$locale = $this->request_locale();
				if ( $used && '' !== $this->inline_need( $locale ) ) {
					$this->print_background_trigger( $locale, 'front' );
				}
			}
		);
	}

	/**
	 * @return string
	 */
	private function ui_id() {
		return str_replace( '_', '-', $this->config['prefix'] ) . '-install';
	}

	/**
	 * @param string $js Script body.
	 * @return void
	 */
	private function print_script( $js ) {
		if ( function_exists( 'wp_print_inline_script_tag' ) ) {
			wp_print_inline_script_tag( $js );
			return;
		}
		echo '<script>' . $js . "</script>\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	/**
	 * A request that installs in the background. It stops firing TRIGGER_TTL after the page was
	 * built (a cached copy must not call the site forever) and at most once an hour per browser.
	 *
	 * @param string $locale Locale of the page.
	 * @param string $origin admin|front.
	 * @return void
	 */
	private function print_background_trigger( $locale, $origin ) {
		$data = array(
			'url'     => admin_url( 'admin-ajax.php' ),
			'body'    => 'action=' . rawurlencode( $this->key( 'background' ) ) . '&locale=' . rawurlencode( $locale ) . '&origin=' . rawurlencode( $origin ),
			'key'     => $this->key( 'background' ) . ':' . $locale,
			'expires' => ( time() + self::TRIGGER_TTL ) * 1000,
		);
		$this->print_script(
			'(function(d){if(Date.now()>d.expires){return;}'
			. 'try{var l=+localStorage.getItem(d.key)||0;if(Date.now()-l<3600000){return;}localStorage.setItem(d.key,String(Date.now()));}catch(e){}'
			. 'try{if(navigator.sendBeacon&&navigator.sendBeacon(d.url,new Blob([d.body],{type:"application/x-www-form-urlencoded"}))){return;}}catch(e){}'
			. 'try{var x=new XMLHttpRequest();x.open("POST",d.url,true);x.setRequestHeader("Content-Type","application/x-www-form-urlencoded");x.send(d.body);}catch(e){}'
			. '})(' . wp_json_encode( $data ) . ');'
		);
	}

	/**
	 * The layer that covers a blocking screen until the pack is installed. It is there from the first
	 * paint, so nothing can be typed that the reload would lose.
	 *
	 * @param string $locale Locale being installed.
	 * @return void
	 */
	private function print_blocking_layer( $locale ) {
		$id = $this->ui_id();
		printf(
			'<div id="%1$s" role="alertdialog" aria-modal="true" aria-labelledby="%1$s-title" dir="ltr" style="position:fixed;top:0;right:0;bottom:0;left:0;z-index:2147483000;background:rgba(15,23,42,.6);display:flex;align-items:center;justify-content:center;padding:16px;">'
			. '<div style="background:#fff;color:#1e293b;max-width:480px;width:100%%;border-radius:10px;padding:28px 28px 22px;box-shadow:0 24px 60px rgba(0,0,0,.3);font-size:14px;line-height:1.6;text-align:left;">'
			. '<h2 id="%1$s-title" style="margin:0 0 10px;font-size:19px;line-height:1.3;color:#0f172a;">%2$s</h2>'
			. '<p class="%1$s-text" aria-live="polite" style="margin:0 0 18px;">%3$s</p>'
			. '<p style="margin:0;display:flex;align-items:center;gap:10px;"><span class="spinner is-active" style="float:none;margin:0;"></span>'
			. '<button type="button" class="button button-primary %1$s-continue" style="display:none;">%4$s</button></p>'
			. '</div></div>',
			esc_attr( $id ),
			esc_html__( 'Installing language files', 'easy-form-builder' ),
			/* translators: 1: plugin name, 2: language name */
			esc_html( sprintf( __( '%1$s is downloading its translation into %2$s. This takes a few seconds, and the page reloads by itself.', 'easy-form-builder' ), $this->config['plugin_name'], $this->language_name( $locale ) ) ),
			esc_html__( 'Continue in English', 'easy-form-builder' )
		);
	}

	/**
	 * @param string $locale   Locale to install.
	 * @param bool   $blocking Whether the page reloads when done.
	 * @return void
	 */
	private function print_install_script( $locale, $blocking ) {
		$data = array(
			'id'       => $this->ui_id(),
			'url'      => admin_url( 'admin-ajax.php' ),
			'body'     => 'action=' . rawurlencode( $this->key( 'install' ) ) . '&nonce=' . rawurlencode( wp_create_nonce( $this->key( 'install' ) ) ) . '&locale=' . rawurlencode( $locale ),
			'blocking' => (bool) $blocking,
			'timeout'  => self::UI_TIMEOUT * 1000,
			'text'     => array(
				'installed' => $blocking
					? __( 'Language files installed. Reloading&hellip;', 'easy-form-builder' )
					/* translators: %s: plugin name */
					: sprintf( __( '%s: language files installed.', 'easy-form-builder' ), $this->config['plugin_name'] ),
				'reload'    => __( 'Reload this page', 'easy-form-builder' ),
				'slow'      => __( 'This is taking longer than expected. You can continue in English; the installation carries on in the background.', 'easy-form-builder' ),
				'network'   => __( 'The installation could not be started from this page. You can continue in English; it will be tried again in the background.', 'easy-form-builder' ),
			),
			'title'    => array(
				'failed'        => __( 'Language files not installed', 'easy-form-builder' ),
				'not_available' => __( 'No translation yet', 'easy-form-builder' ),
				'slow'          => __( 'Still installing', 'easy-form-builder' ),
			),
		);
		$this->print_script(
			'(function(d){var r=document.getElementById(d.id);if(!r){return;}'
			. 'var t=r.querySelector("."+d.id+"-text"),s=r.querySelector(".spinner"),c=r.querySelector("."+d.id+"-continue"),h=document.getElementById(d.id+"-title"),st=Date.now(),done=false;'
			. 'function txt(v){var e=document.createElement("textarea");e.innerHTML=v;return e.value;}'
			. 'function end(m,reload,k){done=true;if(h&&k&&d.title[k]){h.textContent=txt(d.title[k]);}if(s){s.style.display="none";}t.textContent=txt(m);'
			. 'if(reload){var a=document.createElement("a");a.href=window.location.href;a.textContent=txt(d.text.reload);t.appendChild(document.createTextNode(" "));t.appendChild(a);}'
			. 'if(c){c.style.display="inline-block";}}'
			. 'if(c){c.addEventListener("click",function(){r.parentNode.removeChild(r);});}'
			. 'setTimeout(function(){if(!done){end(d.text.slow,false,"slow");}},d.timeout);'
			. 'function call(){var x=new XMLHttpRequest();x.open("POST",d.url,true);x.setRequestHeader("Content-Type","application/x-www-form-urlencoded");'
			. 'x.onload=function(){if(done){return;}var j=null;try{j=JSON.parse(x.responseText);}catch(e){}'
			. 'if(!j||!j.data){end(d.text.network,false,"failed");return;}if(!j.success){end(j.data.message||d.text.network,false,"failed");return;}'
			. 'var o=j.data;if(o.status==="busy"){if(Date.now()-st<d.timeout){setTimeout(call,3000);}return;}'
			. 'if(o.status==="installed"||o.status==="nothing"){if(d.blocking){done=true;t.textContent=txt(d.text.installed);setTimeout(function(){window.location.reload();},700);}else{end(d.text.installed,true);}return;}'
			. 'end(o.message||d.text.network,false,o.status==="not_available"?"not_available":"failed");};'
			. 'x.onerror=function(){if(!done){end(d.text.network,false,"failed");}};x.send(d.body);}'
			. 'call();})(' . wp_json_encode( $data ) . ');'
		);
	}

	/**
	 * wp_ajax: install the pack of the locale the admin screen is shown in.
	 *
	 * @return void
	 */
	public function ajax_install() {
		$this->guard(
			function () {
				if ( ! check_ajax_referer( $this->key( 'install' ), 'nonce', false ) || ! current_user_can( $this->config['capability'] ) ) {
					wp_send_json_error( array( 'message' => __( 'You are not allowed to install language files.', 'easy-form-builder' ) ), 403 );
				}
				if ( function_exists( 'ignore_user_abort' ) ) {
					@ignore_user_abort( true ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
				}
				$posted = isset( $_POST['locale'] ) ? sanitize_text_field( wp_unslash( $_POST['locale'] ) ) : '';
				$locale = $this->valid_request_locale( $posted );
				wp_send_json_success(
					'' === $locale ? array(
						'status'  => 'nothing',
						'message' => '',
					) : $this->install_now( $locale, 'admin' )
				);
			}
		);
	}

	/**
	 * wp_ajax and wp_ajax_nopriv: a page view asks for a background run. Nothing in the request
	 * decides what is downloaded - the locale only counts if the site itself uses it - and runs
	 * are throttled, so calling it repeatedly does no harm.
	 *
	 * @return void
	 */
	public function ajax_background() {
		$this->guard(
			function () {
				if ( function_exists( 'ignore_user_abort' ) ) {
					@ignore_user_abort( true ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
				}
				$locale = isset( $_POST['locale'] ) ? sanitize_text_field( wp_unslash( $_POST['locale'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
				$origin = isset( $_POST['origin'] ) && 'admin' === $_POST['origin'] ? 'admin' : 'front'; // phpcs:ignore WordPress.Security.NonceVerification.Missing
				$this->run_background( $locale, $origin );
			}
		);
		nocache_headers();
		wp_send_json( array( 'ok' => true ) );
	}

	/**
	 * @param string $posted Locale of the page that asked.
	 * @param string $origin admin|front.
	 * @return array|null What ran, or null.
	 */
	public function run_background( $posted, $origin = 'front' ) {
		if ( ! $this->config['inline'] || ! $this->is_enabled() ) {
			return null;
		}
		$locale  = $this->valid_request_locale( (string) $posted );
		$runtime = $this->get_runtime();
		$quiet   = ( time() - (int) $runtime['bg_at'] ) >= self::BACKGROUND_THROTTLE;

		if ( '' !== $locale && ! $this->pack_file_exists( $locale ) ) {
			$attempt = isset( $runtime['attempts'][ $locale ] ) && is_array( $runtime['attempts'][ $locale ] ) ? $runtime['attempts'][ $locale ] : null;
			$first   = null === $attempt || $attempt['version'] !== $this->config['version'];
			if ( ! $first ) {
				if ( ! $quiet ) {
					return null;
				}
				$runtime['bg_at'] = time();
				$this->save_runtime( $runtime );
			}
			return $this->install_now( $locale, $origin );
		}

		if ( ! $quiet || ( $runtime['version'] === $this->config['version'] && ( time() - (int) $runtime['synced_at'] ) < self::DUE_AFTER ) ) {
			return null;
		}
		$runtime['bg_at'] = time();
		$this->save_runtime( $runtime );
		return $this->sync( 'background' );
	}

	/**
	 * @param string $locale Locale from a request.
	 * @return string The locale when this site downloads packs for it, otherwise ''.
	 */
	private function valid_request_locale( $locale ) {
		$locale = (string) $locale;
		if ( ! preg_match( self::LOCALE_PATTERN, $locale ) || 'en_US' === $locale || $this->is_bundled_locale( $locale ) ) {
			return '';
		}
		return in_array( $locale, $this->target_locales(), true ) ? $locale : '';
	}

	/**
	 * Install the pack of one locale right now, for a page view, and remember the attempt.
	 *
	 * @param string $locale Locale without a pack.
	 * @param string $origin admin (someone opened an admin screen) or front (a visitor opened a page with a form).
	 * @return array{status: string, message: string} status: installed, busy, nothing, not_available or failed
	 *                                                  (then also reason and mailed).
	 */
	public function install_now( $locale, $origin ) {
		$summary = $this->sync( 'inline', array( 'first' => $locale ) );
		$status  = (string) $summary['status'];

		if ( in_array( $status, array( 'locked', 'recent' ), true ) || in_array( $locale, (array) $summary['pending'], true ) ) {
			return array(
				'status'  => 'busy',
				'message' => '',
			);
		}
		if ( in_array( $status, array( 'disabled', 'no_locales' ), true ) ) {
			return array(
				'status'  => 'nothing',
				'message' => '',
			);
		}

		unset( $this->installed_cache[ $locale ] );
		$attempt = array(
			'version' => $this->config['version'],
			'at'      => time(),
			'outcome' => 'installed',
			'reason'  => '',
		);
		$result  = array(
			'status'  => 'installed',
			'message' => '',
		);

		if ( ! $this->pack_file_exists( $locale ) ) {
			$error = $this->locale_error( $locale, $summary );
			if ( null === $error ) {
				$attempt['outcome'] = 'not_available';
				$result             = array(
					'status'  => 'not_available',
					/* translators: 1: plugin name, 2: language name */
					'message' => sprintf( __( 'There is no translation of %1$s into %2$s yet, so it stays in English.', 'easy-form-builder' ), $this->config['plugin_name'], $this->language_name( $locale ) ),
				);
			} else {
				$attempt['outcome'] = 'failed';
				$attempt['reason']  = $error['kind'];
				$mailed             = $this->notify_install_failure( array( $locale ), $error, $origin );
				/* translators: %s: what went wrong, e.g. "file changes are switched off on this site" */
				$message = sprintf( __( 'The language files could not be installed: %s.', 'easy-form-builder' ), $this->describe_error( $error ) );
				/* translators: %s: plugin name */
				$message .= ' ' . sprintf( __( '%s keeps working, in English.', 'easy-form-builder' ), $this->config['plugin_name'] );
				if ( $mailed ) {
					/* translators: %s: email address */
					$message .= ' ' . sprintf( __( 'The details and the manual installation steps were emailed to %s.', 'easy-form-builder' ), $this->admin_email() );
				} else {
					$message .= ' ' . __( 'It will be tried again automatically.', 'easy-form-builder' );
				}
				$result = array(
					'status'  => 'failed',
					'reason'  => $error['kind'],
					'message' => $message,
					'mailed'  => $mailed,
				);
			}
		}

		$runtime = $this->get_runtime();
		foreach ( $runtime['attempts'] as $known => $previous ) {
			if ( ! is_array( $previous ) || $previous['version'] !== $this->config['version'] ) {
				unset( $runtime['attempts'][ $known ] );
			}
		}
		$runtime['attempts'][ $locale ] = $attempt;
		$this->save_runtime( $runtime );

		return $result;
	}

	/**
	 * Why a locale still has no pack after a run, or null when nobody has a translation for it.
	 *
	 * @param string $locale  Locale.
	 * @param array  $summary sync() summary.
	 * @return array{kind: string, detail: string}|null kind: unreachable, write, package or server.
	 */
	private function locale_error( $locale, array $summary ) {
		if ( '' !== $summary['blocker'] ) {
			return array(
				'kind'   => 'write',
				'detail' => (string) $summary['blocker'],
			);
		}
		$error = isset( $summary['errors'][ $locale ] ) && is_array( $summary['errors'][ $locale ] ) ? $summary['errors'][ $locale ] : null;
		$ws    = is_array( $summary['ws_error'] ) ? $summary['ws_error'] : null;

		// WhiteStudio is asked first, so when it could not be reached that is the problem to report -
		// whether wordpress.org then had nothing, or could not be reached either.
		if ( null !== $ws && 'unreachable' === $ws['kind'] && ( null === $error || 0 === strpos( (string) $error['detail'], 'wordpress.org:' ) ) ) {
			$ws['wporg_unreachable'] = null !== $error && 'unreachable' === $error['kind'];
			return $ws;
		}
		if ( null !== $error ) {
			return $error;
		}
		// WhiteStudio answered with an error and wordpress.org has nothing: WhiteStudio may well have had it.
		return $ws;
	}

	/**
	 * @param array $error kind and detail.
	 * @return string What went wrong, as the end of a sentence.
	 */
	private function describe_error( array $error ) {
		$detail = (string) $error['detail'];
		switch ( $error['kind'] ) {
			case 'unreachable':
				if ( false !== strpos( $detail, '[http_request_not_executed]' ) || false !== stripos( $detail, 'blocked requests through HTTP' ) ) {
					return __( 'WordPress on this site is set to block outgoing connections (WP_HTTP_BLOCK_EXTERNAL)', 'easy-form-builder' );
				}
				/* translators: %s: server host name */
				return sprintf( __( 'the server this website runs on cannot connect to %s', 'easy-form-builder' ), 0 === strpos( $detail, 'wordpress.org:' ) ? 'wordpress.org' : $this->server_host() );
			case 'write':
				if ( 'file_mods_disallowed' === $detail ) {
					return __( 'file changes are switched off on this site (DISALLOW_FILE_MODS)', 'easy-form-builder' );
				}
				if ( 'filesystem_not_direct' === $detail ) {
					return __( 'this server only lets WordPress write files with FTP credentials, which an automatic installation cannot use', 'easy-form-builder' );
				}
				/* translators: %s: folder path */
				return sprintf( __( 'WordPress cannot write to the folder %s', 'easy-form-builder' ), $this->languages_dir_label() );
			case 'package':
				return __( 'the downloaded file was damaged', 'easy-form-builder' );
			default:
				return __( 'the language server answered with an error', 'easy-form-builder' );
		}
	}

	/**
	 * Email the admin about an installation that failed because of this site. A problem on the
	 * language server's side is not emailed: the admin cannot fix it, and the plugin retries.
	 *
	 * @param string[] $locales Locales left without a pack.
	 * @param array    $error   kind and detail.
	 * @param string   $origin  admin|front.
	 * @return bool Whether an email was sent.
	 */
	private function notify_install_failure( array $locales, array $error, $origin ) {
		if ( ! $this->config['notify'] || ! in_array( $error['kind'], array( 'unreachable', 'write' ), true ) ) {
			return false;
		}

		$state  = $this->get_state();
		$notice = $state['notice'];
		$now    = time();
		$key    = md5( $error['kind'] . '|' . ( 'write' === $error['kind'] ? $error['detail'] : '' ) );

		if ( $notice['install_key'] === $key && ( $now - (int) $notice['install_sent_at'] ) < self::INSTALL_MAIL_COOLDOWN ) {
			return false;
		}
		// The "cannot reach WhiteStudio" email already explained this outage.
		if ( 'unreachable' === $error['kind'] && (int) $notice['last_sent_at'] > 0 && ( $now - (int) $notice['last_sent_at'] ) < self::NOTIFY_COOLDOWN ) {
			return false;
		}

		/**
		 * Filters whether the "could not install the language files" email is sent.
		 *
		 * @param bool     $send    Whether to send.
		 * @param string[] $locales Locales left without a pack.
		 * @param array    $error   kind and detail.
		 * @param string   $origin  admin or front.
		 */
		if ( ! apply_filters( $this->key( 'send_install_failure_email' ), true, $locales, $error, $origin ) ) {
			return false;
		}

		$to = $this->admin_email();
		if ( ! is_email( $to ) ) {
			return false;
		}

		$email = $this->install_failure_email( $locales, $error, $origin );
		if ( ! wp_mail( $to, $email['subject'], $email['body'], array( 'Content-Type: text/plain; charset=UTF-8' ) ) ) {
			return false;
		}

		$notice['install_key']     = $key;
		$notice['install_sent_at'] = $now;
		if ( 'unreachable' === $error['kind'] ) {
			$notice['episode_sent_at'] = $now;
			$notice['last_sent_at']    = $now;
		}
		$state['notice'] = $notice;
		$this->save_state( $state );
		return true;
	}

	/**
	 * @param string[] $locales Locales left without a pack.
	 * @param array    $error   kind and detail.
	 * @param string   $origin  admin|front.
	 * @return array{subject: string, body: string}
	 */
	public function install_failure_email( array $locales, array $error, $origin ) {
		$plugin    = $this->config['plugin_name'];
		$site_name = wp_specialchars_decode( (string) get_bloginfo( 'name' ), ENT_QUOTES );
		$names     = array_map( array( $this, 'language_name' ), $locales );
		$dir       = $this->languages_dir_label();

		$subject = sprintf(
			/* translators: 1: site name, 2: plugin name */
			__( '[%1$s] %2$s could not install its language files', 'easy-form-builder' ),
			$site_name,
			$plugin
		);

		$lines   = array();
		/* translators: 1: plugin name, 2: language names, 3: site address */
		$lines[] = sprintf( __( '%1$s could not install its translation into %2$s on %3$s.', 'easy-form-builder' ), $plugin, implode( ', ', $names ), home_url( '/' ) );
		$lines[] = 'admin' === $origin
			? __( 'This happened when the plugin was opened in the WordPress dashboard.', 'easy-form-builder' )
			: __( 'This happened when a visitor opened a page with a form; the form was shown in English.', 'easy-form-builder' );
		$lines[] = __( 'The plugin keeps working normally, in English, until the translation is installed.', 'easy-form-builder' );
		$lines[] = '';
		/* translators: %s: what went wrong */
		$lines[] = sprintf( __( 'What went wrong: %s.', 'easy-form-builder' ), $this->describe_error( $error ) );
		if ( '' !== (string) $error['detail'] ) {
			/* translators: %s: technical error message */
			$lines[] = sprintf( __( 'Technical detail: %s', 'easy-form-builder' ), (string) $error['detail'] );
		}
		if ( ! empty( $error['wporg_unreachable'] ) ) {
			$lines[] = __( 'This server could not reach WordPress.org either, so outgoing connections from it are probably blocked in general.', 'easy-form-builder' );
		}
		$lines[] = '';
		$lines[] = __( 'What you can do:', 'easy-form-builder' );

		if ( 'unreachable' === $error['kind'] ) {
			/* translators: %s: server host name */
			$lines[] = sprintf( __( '1. Ask your hosting provider to allow outgoing HTTPS connections (port 443) from this server to %s. The plugin installs the translation by itself as soon as it can connect.', 'easy-form-builder' ), $this->server_host() );
		} elseif ( 'file_mods_disallowed' === $error['detail'] ) {
			$lines[] = __( '1. Allow file changes by removing DISALLOW_FILE_MODS from wp-config.php, or install the files yourself as described below.', 'easy-form-builder' );
		} elseif ( 'filesystem_not_direct' === $error['detail'] ) {
			$lines[] = __( '1. Ask your hosting provider to let WordPress write files directly, or install the files yourself as described below.', 'easy-form-builder' );
		} else {
			/* translators: %s: folder path */
			$lines[] = sprintf( __( '1. Make the folder %s writable by WordPress. The plugin installs the translation by itself afterwards.', 'easy-form-builder' ), $dir );
		}

		$lines[] = __( '2. Or install the translation yourself, in a few minutes:', 'easy-form-builder' );
		foreach ( $locales as $index => $locale ) {
			/* translators: 1: language name, 2: download address */
			$lines[] = '   - ' . sprintf( __( 'Download %1$s: %2$s', 'easy-form-builder' ), $names[ $index ], $this->package_url( $locale ) );
		}
		/* translators: %s: folder path */
		$lines[] = '   - ' . sprintf( __( 'Unzip it and upload the files to %s on your server, replacing any file with the same name.', 'easy-form-builder' ), $dir );
		if ( '' !== $this->config['guide_url'] ) {
			/* translators: %s: guide address */
			$lines[] = '   - ' . sprintf( __( 'Step-by-step guide: %s', 'easy-form-builder' ), $this->config['guide_url'] );
		}
		$lines[] = '';
		$lines[] = __( 'You will not receive this email again for the same problem in the next 30 days.', 'easy-form-builder' );

		return array(
			'subject' => $subject,
			'body'    => implode( "\n", $lines ) . "\n",
		);
	}

	/**
	 * @param string $locale Locale.
	 * @return string Where a person downloads the pack by hand.
	 */
	private function package_url( $locale ) {
		$servers = $this->servers();
		return reset( $servers ) . '/wp-json/wl/v1/translations/' . rawurlencode( $this->config['product'] ) . '/package/' . rawurlencode( $locale );
	}

	/**
	 * @return string
	 */
	private function server_host() {
		$servers = $this->servers();
		return $this->host( (string) reset( $servers ) );
	}

	/**
	 * @return string The languages folder relative to the WordPress root.
	 */
	private function languages_dir_label() {
		$root = trailingslashit( wp_normalize_path( ABSPATH ) );
		$dir  = wp_normalize_path( $this->config['languages_dir'] );
		return 0 === strpos( $dir, $root ) ? substr( $dir, strlen( $root ) ) : $dir;
	}

	/**
	 * @return string
	 */
	private function admin_email() {
		return sanitize_email( (string) ( is_multisite() ? get_site_option( 'admin_email', '' ) : get_option( 'admin_email', '' ) ) );
	}

	/**
	 * An English name for a locale, without any network request: WordPress's cached language
	 * list when an admin has loaded it, else the intl extension, else a short built-in list.
	 *
	 * @param string $locale Locale.
	 * @return string e.g. "German (Austria) (de_AT)", "Persian (fa_AF)", or the locale itself.
	 */
	public function language_name( $locale ) {
		$locale = (string) $locale;

		$cached = get_site_transient( 'available_translations' );
		if ( is_array( $cached ) && ! empty( $cached[ $locale ]['english_name'] ) ) {
			return $cached[ $locale ]['english_name'] . ' (' . $locale . ')';
		}

		if ( class_exists( 'Locale' ) ) {
			$tag  = str_replace( '_', '-', $locale );
			$name = \Locale::getDisplayName( $tag, 'en' );
			if ( is_string( $name ) && '' !== $name && strtolower( $name ) !== strtolower( $tag ) ) {
				return $name . ' (' . $locale . ')';
			}
		}

		$names    = array(
			'ar'  => 'Arabic',
			'ary' => 'Moroccan Arabic',
			'az'  => 'Azerbaijani',
			'bg'  => 'Bulgarian',
			'bn'  => 'Bengali',
			'ca'  => 'Catalan',
			'cs'  => 'Czech',
			'da'  => 'Danish',
			'de'  => 'German',
			'el'  => 'Greek',
			'en'  => 'English',
			'es'  => 'Spanish',
			'et'  => 'Estonian',
			'fa'  => 'Persian',
			'fi'  => 'Finnish',
			'fr'  => 'French',
			'he'  => 'Hebrew',
			'hi'  => 'Hindi',
			'hr'  => 'Croatian',
			'hu'  => 'Hungarian',
			'id'  => 'Indonesian',
			'it'  => 'Italian',
			'ja'  => 'Japanese',
			'ka'  => 'Georgian',
			'ko'  => 'Korean',
			'lt'  => 'Lithuanian',
			'lv'  => 'Latvian',
			'ms'  => 'Malay',
			'nb'  => 'Norwegian',
			'nl'  => 'Dutch',
			'pl'  => 'Polish',
			'pt'  => 'Portuguese',
			'ro'  => 'Romanian',
			'ru'  => 'Russian',
			'sk'  => 'Slovak',
			'sl'  => 'Slovenian',
			'sr'  => 'Serbian',
			'sv'  => 'Swedish',
			'th'  => 'Thai',
			'tr'  => 'Turkish',
			'uk'  => 'Ukrainian',
			'ur'  => 'Urdu',
			'vi'  => 'Vietnamese',
			'zh'  => 'Chinese',
		);
		$position = strpos( $locale, '_' );
		$language = false === $position ? $locale : substr( $locale, 0, $position );
		return isset( $names[ $language ] ) ? $names[ $language ] . ' (' . $locale . ')' : $locale;
	}

	/*
	 * ---------------------------------------------------------------------
	 * The email
	 * ---------------------------------------------------------------------
	 */

	/**
	 * Email the admin when this site has been unable to reach WhiteStudio for
	 * NOTIFY_AFTER seconds over at least NOTIFY_MIN_FAILURES attempts. Once
	 * per outage, at most once per NOTIFY_COOLDOWN.
	 *
	 * @param array    $state        State, updated in place.
	 * @param string[] $locales      Languages the site needs.
	 * @param string   $wporg_status wordpress.org catalog status in this run.
	 * @return bool Whether an email was sent.
	 */
	private function maybe_notify( array &$state, array $locales, $wporg_status ) {
		if ( ! $this->config['notify'] ) {
			return false;
		}

		$ws     = $state['ws'];
		$notice = $state['notice'];
		$now    = time();

		if ( (int) $ws['failures'] < self::NOTIFY_MIN_FAILURES
			|| ( $now - (int) $ws['first_failure_at'] ) < self::NOTIFY_AFTER
			|| (int) $notice['episode_sent_at'] > 0
			|| (int) $notice['episode_attempts'] >= self::NOTIFY_MAX_SEND_ATTEMPTS
			|| ( (int) $notice['last_sent_at'] > 0 && ( $now - (int) $notice['last_sent_at'] ) < self::NOTIFY_COOLDOWN ) ) {
			return false;
		}

		/**
		 * Filters whether the "cannot reach WhiteStudio" email is sent.
		 *
		 * @param bool  $send  Whether to send.
		 * @param array $state Current state.
		 */
		if ( ! apply_filters( $this->key( 'send_unreachable_email' ), true, $state ) ) {
			return false;
		}

		$to = sanitize_email( (string) ( is_multisite() ? get_site_option( 'admin_email', '' ) : get_option( 'admin_email', '' ) ) );
		if ( ! is_email( $to ) ) {
			return false;
		}

		$email = $this->unreachable_email( $state, $locales, $wporg_status );
		$sent  = (bool) wp_mail( $to, $email['subject'], $email['body'], array( 'Content-Type: text/plain; charset=UTF-8' ) );

		$notice['episode_attempts'] = (int) $notice['episode_attempts'] + 1;
		if ( $sent ) {
			$notice['episode_sent_at'] = $now;
			$notice['last_sent_at']    = $now;
		}
		$state['notice'] = $notice;

		return $sent;
	}

	/**
	 * @param array    $state        State.
	 * @param string[] $locales      Languages the site needs.
	 * @param string   $wporg_status wordpress.org catalog status in this run.
	 * @return array{subject: string, body: string}
	 */
	public function unreachable_email( array $state, array $locales, $wporg_status ) {
		$plugin    = $this->config['plugin_name'];
		$servers   = $this->servers();
		$server    = '' !== (string) $state['ws']['server'] ? (string) $state['ws']['server'] : reset( $servers );
		$host      = $this->host( (string) $server );
		$site_name = wp_specialchars_decode( (string) get_bloginfo( 'name' ), ENT_QUOTES );

		// Read from the files, not from the run's outcome codes: what matters is what the site has now.
		$from_wporg = array();
		$kept       = array();
		$missing    = array();
		foreach ( $locales as $locale ) {
			$installed = $this->inspect_installed( $locale );
			if ( ! $installed['exists'] ) {
				$missing[] = $locale;
			} elseif ( 'wporg' === $installed['source'] ) {
				$from_wporg[] = $locale;
			} else {
				$kept[] = $locale;
			}
		}

		$subject = sprintf(
			/* translators: 1: site name, 2: plugin name */
			__( '[%1$s] %2$s could not download its translations', 'easy-form-builder' ),
			$site_name,
			$plugin
		);

		$lines   = array();
		$lines[] = sprintf(
			/* translators: 1: plugin name, 2: site address, 3: server host name */
			__( '%1$s on %2$s could not download its translation files from the WhiteStudio server (%3$s), because the server this website runs on cannot connect to it.', 'easy-form-builder' ),
			$plugin,
			home_url( '/' ),
			$host
		);
		$lines[] = __( 'Nothing is broken: the plugin keeps working, and every translation that was already installed stays in place.', 'easy-form-builder' );
		$lines[] = '';
		/* translators: %s: comma-separated locale codes, e.g. de_DE, fr_FR */
		$lines[] = sprintf( __( 'Languages affected: %s', 'easy-form-builder' ), implode( ', ', $locales ) );
		/* translators: %s: date and time in UTC */
		$lines[] = sprintf( __( 'Failing since: %s', 'easy-form-builder' ), gmdate( 'Y-m-d H:i', (int) $state['ws']['first_failure_at'] ) . ' UTC' );
		/* translators: %s: technical error message */
		$lines[] = sprintf( __( 'Last error: %s', 'easy-form-builder' ), (string) $state['ws']['last_error'] );

		$lines[] = '';
		if ( ! empty( $from_wporg ) ) {
			/* translators: %s: comma-separated locale codes */
			$lines[] = sprintf( __( 'Until then these languages use the translations from WordPress.org: %s', 'easy-form-builder' ), implode( ', ', $from_wporg ) );
		}
		if ( ! empty( $kept ) ) {
			/* translators: %s: comma-separated locale codes */
			$lines[] = sprintf( __( 'These languages keep the translations that were installed before: %s', 'easy-form-builder' ), implode( ', ', $kept ) );
		}
		if ( ! empty( $missing ) ) {
			/* translators: %s: comma-separated locale codes */
			$lines[] = sprintf( __( 'These languages have no translation installed: %s', 'easy-form-builder' ), implode( ', ', $missing ) );
		}
		if ( 'unreachable' === $wporg_status ) {
			$lines[] = __( 'This server could not reach WordPress.org either, so outgoing connections from it are probably blocked in general.', 'easy-form-builder' );
		}

		$lines[] = '';
		$lines[] = __( 'What you can do:', 'easy-form-builder' );
		/* translators: %s: server host name */
		$lines[] = sprintf( __( '1. Ask your hosting provider to allow outgoing HTTPS connections (port 443) from this server to %s.', 'easy-form-builder' ), $host );
		if ( '' !== $this->config['guide_url'] ) {
			$lines[] = __( '2. Or install the translation files yourself by following this short guide:', 'easy-form-builder' );
			$lines[] = '   ' . $this->config['guide_url'];
		}
		$lines[] = '';
		$lines[] = __( 'You will not receive this email again for this problem.', 'easy-form-builder' );

		return array(
			'subject' => $subject,
			'body'    => implode( "\n", $lines ) . "\n",
		);
	}

	/*
	 * ---------------------------------------------------------------------
	 * State and helpers
	 * ---------------------------------------------------------------------
	 */

	/**
	 * @return array
	 */
	private function default_state() {
		return array(
			'ws'             => array(
				'status'           => 'never',
				'server'           => '',
				'failures'         => 0,
				'first_failure_at' => 0,
				'last_attempt_at'  => 0,
				'last_success_at'  => 0,
				'last_error'       => '',
			),
			'wporg'          => array(
				'status'          => 'never',
				'last_attempt_at' => 0,
				'last_error'      => '',
			),
			'notice'         => array(
				'episode_sent_at'  => 0,
				'episode_attempts' => 0,
				'last_sent_at'     => 0,
				'install_key'      => '',
				'install_sent_at'  => 0,
			),
			'packs'          => array(),
			'locales'        => array(),
			'synced_version' => '',
			'last_run'       => array(
				'at'      => 0,
				'reason'  => '',
				'status'  => '',
				'results' => array(),
			),
		);
	}

	/**
	 * Stored network-wide on a multisite, because the language folder is shared.
	 *
	 * @return array
	 */
	public function get_state() {
		$key    = $this->key( 'state' );
		$stored = is_multisite() ? get_site_option( $key, array() ) : get_option( $key, array() );
		$state  = $this->default_state();
		if ( is_array( $stored ) ) {
			foreach ( $state as $name => $default ) {
				if ( ! array_key_exists( $name, $stored ) ) {
					continue;
				}
				$state[ $name ] = is_array( $default ) && is_array( $stored[ $name ] ) ? array_merge( $default, $stored[ $name ] ) : $stored[ $name ];
			}
		}
		return $state;
	}

	/**
	 * @param array $state State.
	 * @return void
	 */
	private function save_state( array $state ) {
		$key = $this->key( 'state' );
		if ( is_multisite() ) {
			update_site_option( $key, $state );
		} else {
			update_option( $key, $state, false );
		}
	}

	/**
	 * @return bool
	 */
	private function acquire_lock() {
		$key = $this->key( 'lock' );
		$now = time();
		if ( is_multisite() ) {
			$held = (int) get_site_option( $key, 0 );
			if ( $held > 0 && ( $now - $held ) < self::LOCK_TTL ) {
				return false;
			}
			update_site_option( $key, $now );
			return true;
		}
		if ( add_option( $key, $now, '', false ) ) {
			return true;
		}
		$held = (int) get_option( $key, 0 );
		if ( $held > 0 && ( $now - $held ) < self::LOCK_TTL ) {
			return false;
		}
		update_option( $key, $now, false );
		return true;
	}

	/**
	 * @return void
	 */
	private function release_lock() {
		if ( is_multisite() ) {
			delete_site_option( $this->key( 'lock' ) );
		} else {
			delete_option( $this->key( 'lock' ) );
		}
	}

	/**
	 * @return bool
	 */
	public function is_enabled() {
		/**
		 * Filters whether language packs are downloaded at all.
		 *
		 * @param bool $enabled Whether downloads are on.
		 */
		return (bool) apply_filters( $this->key( 'enabled' ), $this->config['enabled'] );
	}

	/**
	 * @return string[]
	 */
	private function servers() {
		/**
		 * Filters the WhiteStudio language servers, tried in order.
		 *
		 * @param string[] $servers Base URLs without a trailing slash.
		 */
		$servers = array();
		foreach ( (array) apply_filters( $this->key( 'servers' ), $this->config['servers'] ) as $server ) {
			$server = untrailingslashit( trim( (string) $server ) );
			if ( preg_match( '#^https?://[^/\s]+#i', $server ) ) {
				$servers[] = $server;
			}
		}
		return empty( $servers ) ? $this->config['servers'] : $servers;
	}

	/**
	 * @param string $url URL requested.
	 * @return bool
	 */
	private function sslverify( $url ) {
		return (bool) apply_filters( $this->key( 'sslverify' ), true, $url );
	}

	/**
	 * @param string $url URL.
	 * @return string Lower-case host with the port, if any.
	 */
	private function host( $url ) {
		$host = strtolower( (string) wp_parse_url( $url, PHP_URL_HOST ) );
		$port = wp_parse_url( $url, PHP_URL_PORT );
		return $port ? $host . ':' . $port : $host;
	}

	/**
	 * @param string $date Date in a format strtotime() reads, UTC when no zone is given.
	 * @return int
	 */
	private function timestamp( $date ) {
		$date = trim( (string) $date );
		if ( '' === $date ) {
			return 0;
		}
		if ( ! preg_match( '/(?:[+-]\d{2}:?\d{2}|UTC|GMT|Z)$/i', $date ) ) {
			$date .= ' UTC';
		}
		$time = strtotime( $date );
		return false === $time ? 0 : (int) $time;
	}

	/**
	 * @param string $name Suffix.
	 * @return string
	 */
	private function key( $name ) {
		return $this->config['prefix'] . '_' . $name;
	}

	/**
	 * @param string $code   Error code.
	 * @param string $kind   unreachable|server|package|write.
	 * @param string $detail Detail.
	 * @return array{ok: bool, error: string, kind: string, detail: string}
	 */
	private function failure( $code, $kind, $detail ) {
		return array(
			'ok'     => false,
			'error'  => (string) $code,
			'kind'   => (string) $kind,
			'detail' => (string) $detail,
		);
	}

	/**
	 * @param callable $callback Work to run.
	 * @return mixed|null
	 */
	private function guard( $callback ) {
		try {
			return call_user_func( $callback );
		} catch ( \Throwable $e ) {
			$this->log( get_class( $e ) . ': ' . $e->getMessage() );
			return null;
		}
	}

	/**
	 * @param string $message Message.
	 * @return void
	 */
	private function log( $message ) {
		if ( $this->config['debug'] && function_exists( 'error_log' ) ) {
			@error_log( '[' . $this->config['prefix'] . '] ' . $message ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.PHP.DevelopmentFunctions
		}
	}
}
