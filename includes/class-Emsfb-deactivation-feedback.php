<?php
/**
 * Deactivation feedback: ask why, and reward bug reports.
 *
 * When someone deactivates Easy Form Builder from the Plugins screen a modal
 * asks what went wrong. Choosing "a bug" opens a description box and promises a
 * 100% first-year discount code, which the White Studio feedback service issues
 * on the spot for a site that proved its own domain.
 *
 * Three rules this file must keep:
 *
 *   1. Deactivation is never blocked. Every failure path - no network, a dead
 *      endpoint, a refused report - still ends with the plugin deactivating.
 *   2. Nothing is sent without a click. The modal appears on the deactivate
 *      link, and "Skip" sends nothing at all.
 *   3. The payload is fixed and small: reason, message, optional email, and a
 *      short list of version numbers. No form data, no submissions, no users.
 *
 * @package Easy_Form_Builder
 */

namespace Emsfb;

defined( 'ABSPATH' ) || exit;

/**
 * Client half of the deactivation feedback flow.
 */
class Deactivation_Feedback {

	/** Option holding this site's identity with the feedback service. */
	const OPTION_IDENTITY = 'emsfb_feedback_identity';

	/** Option holding local send bookkeeping. */
	const OPTION_STATE = 'emsfb_feedback_state';

	/** Nonce action and AJAX action share one name on purpose. */
	const ACTION = 'emsfb_deactivation_feedback';

	/** REST base on the White Studio feedback service. */
	const API_BASE = '/wp-json/ws-efb/v1';

	/** Longest message accepted from the textarea. */
	const MAX_DETAILS = 4000;

	/**
	 * Shortest bug description worth sending.
	 *
	 * The service scores anything shorter as coupon farming, so asking here -
	 * where the person can still do something about it - is the difference
	 * between a useful report and one silently filed as spam.
	 */
	const MIN_BUG_DETAILS = 15;

	/** How many reports this site may send per day, whatever the server allows. */
	const DAILY_LIMIT = 3;

	/**
	 * HTTP status of the most recent call to the feedback service.
	 *
	 * @var int
	 */
	protected $last_status = 0;

	/**
	 * Wire the hooks.
	 */
	public function __construct() {
		// Answering the ownership challenge has to work on the front end, where
		// the rest of this class is dormant. The query check keeps the hook off
		// every other page load.
		if ( isset( $_GET['ws_efb_verify'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			add_action( 'init', array( $this, 'answer_ownership_challenge_efb' ), 1 );
		}

		if ( ! is_admin() ) {
			return;
		}

		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets_efb' ) );
		// 'admin_footer', not 'admin_footer-plugins.php': the screen-specific
		// hook fires *after* the footer scripts are printed, so the modal markup
		// would land below the script that looks for it.
		add_action( 'admin_footer', array( $this, 'render_modal_efb' ) );
		add_action( 'wp_ajax_' . self::ACTION, array( $this, 'ajax_submit_efb' ) );
	}

	/*
	 * ---------------------------------------------------------------------
	 * Screen
	 * ---------------------------------------------------------------------
	 */

	/**
	 * The reasons offered, in display order.
	 *
	 * `detail` marks a reason that needs a written explanation before the form
	 * can be sent; `contact` marks one where an email address is worth asking
	 * for. The keys must stay in step with the service's allow-list.
	 *
	 * @return array
	 */
	public function reasons_efb() {
		$text = $this->strings_efb();

		return array(
			'bug'              => array(
				'label'       => $text['reasonBug'],
				'placeholder' => $text['placeholderBug'],
				'detail'      => true,
				'contact'     => true,
				'reward'      => true,
			),
			'missing_feature'  => array(
				'label'       => $text['reasonMissingFeature'],
				'placeholder' => $text['placeholderMissingFeature'],
				'detail'      => true,
				'contact'     => true,
				'reward'      => false,
			),
			'hard_to_use'      => array(
				'label'       => $text['reasonHardToUse'],
				'placeholder' => $text['placeholderHardToUse'],
				'detail'      => true,
				'contact'     => true,
				'reward'      => false,
			),
			'found_better'     => array(
				'label'       => $text['reasonFoundBetter'],
				'placeholder' => $text['placeholderFoundBetter'],
				'detail'      => true,
				'contact'     => false,
				'reward'      => false,
			),
			'temporary'        => array(
				'label'       => $text['reasonTemporary'],
				'placeholder' => '',
				'detail'      => false,
				'contact'     => false,
				'reward'      => false,
			),
			'no_longer_needed' => array(
				'label'       => $text['reasonNoLongerNeeded'],
				'placeholder' => '',
				'detail'      => false,
				'contact'     => false,
				'reward'      => false,
			),
			'other'            => array(
				'label'       => $text['reasonOther'],
				'placeholder' => $text['placeholderOther'],
				'detail'      => true,
				'contact'     => true,
				'reward'      => false,
			),
		);
	}

	/**
	 * Load the modal's CSS and JS on the Plugins screen only.
	 *
	 * @param string $hook Current admin page.
	 * @return void
	 */
	public function enqueue_assets_efb( $hook ) {
		if ( 'plugins.php' !== $hook || ! current_user_can( 'deactivate_plugins' ) ) {
			return;
		}

		wp_enqueue_style(
			'efb-deactivation-feedback',
			EMSFB_PLUGIN_URL . 'includes/admin/assets/css/deactivation-feedback-efb.css',
			array(),
			EMSFB_PLUGIN_VERSION
		);

		wp_enqueue_script(
			'efb-deactivation-feedback',
			EMSFB_PLUGIN_URL . 'includes/admin/assets/js/deactivation-feedback-efb.js',
			array(),
			EMSFB_PLUGIN_VERSION,
			true
		);

		$data = array(
			'ajaxUrl'    => admin_url( 'admin-ajax.php' ),
			'nonce'      => wp_create_nonce( self::ACTION ),
			'plugin'     => plugin_basename( EMSFB_PLUGIN_FILE ),
			'rtl'        => is_rtl() ? 1 : 0,
			'minBugText' => self::MIN_BUG_DETAILS,
			'text'       => $this->strings_efb(),
		);

		// wp_localize_script only decodes entities at the top level, so the
		// nested text array has to be decoded here or the modal would print a
		// literal "&hellip;".
		if ( function_exists( 'emsfb_decode_typographic_entities_efb' ) ) {
			$data = emsfb_decode_typographic_entities_efb( $data );
		}

		wp_localize_script( 'efb-deactivation-feedback', 'efb_deactivate', $data );
	}

	/**
	 * Print the modal markup in the Plugins screen footer.
	 *
	 * @return void
	 */
	public function render_modal_efb() {
		if ( ! current_user_can( 'deactivate_plugins' ) ) {
			return;
		}

		// The generic footer hook fires on every screen, so the Plugins list is
		// filtered for here rather than by the hook name.
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || 'plugins' !== $screen->id ) {
			return;
		}

		$text    = $this->strings_efb();
		$reasons = $this->reasons_efb();
		$email   = sanitize_email( (string) get_option( 'admin_email' ) );
		?>
		<div id="efb-deactivate-modal" class="efb-deactivate-modal<?php echo is_rtl() ? ' efb-rtl' : ''; ?>" role="dialog" aria-modal="true" aria-labelledby="efb-deactivate-title" hidden>
			<div class="efb-deactivate-backdrop" data-efb-close="1"></div>
			<div class="efb-deactivate-box" role="document">
				<button type="button" class="efb-deactivate-x" data-efb-close="1" aria-label="<?php echo esc_attr( $text['close'] ); ?>">&times;</button>

				<div class="efb-deactivate-body">
					<h2 id="efb-deactivate-title" class="efb-deactivate-title"><?php echo esc_html( $text['title'] ); ?></h2>
					<p class="efb-deactivate-subtitle"><?php echo esc_html( $text['subtitle'] ); ?></p>

					<ul class="efb-deactivate-reasons">
						<?php foreach ( $reasons as $key => $reason ) : ?>
							<li class="efb-deactivate-reason"
								data-reason="<?php echo esc_attr( $key ); ?>"
								data-detail="<?php echo $reason['detail'] ? '1' : '0'; ?>"
								data-contact="<?php echo $reason['contact'] ? '1' : '0'; ?>">
								<label class="efb-deactivate-choice">
									<input type="radio" name="efb_deactivate_reason" value="<?php echo esc_attr( $key ); ?>">
									<span><?php echo esc_html( $reason['label'] ); ?></span>
								</label>

								<?php if ( $reason['reward'] || $reason['detail'] ) : ?>
									<div class="efb-deactivate-panel" hidden>
										<?php if ( $reason['reward'] ) : ?>
											<div class="efb-deactivate-reward">
												<strong class="efb-deactivate-reward-title"><?php echo esc_html( $text['rewardTitle'] ); ?></strong>
												<span class="efb-deactivate-reward-body"><?php echo esc_html( $text['rewardBody'] ); ?></span>
											</div>
										<?php endif; ?>

										<?php if ( $reason['detail'] ) : ?>
											<textarea
												class="efb-deactivate-details"
												rows="4"
												maxlength="<?php echo esc_attr( (string) self::MAX_DETAILS ); ?>"
												placeholder="<?php echo esc_attr( $reason['placeholder'] ); ?>"
												aria-label="<?php echo esc_attr( $reason['label'] ); ?>"></textarea>
										<?php endif; ?>
									</div>
								<?php endif; ?>
							</li>
						<?php endforeach; ?>
					</ul>

					<div class="efb-deactivate-contact" hidden>
						<label class="efb-deactivate-field">
							<span><?php echo esc_html( $text['emailLabel'] ); ?></span>
							<input type="email" class="efb-deactivate-email" value="<?php echo esc_attr( $email ); ?>" autocomplete="email">
						</label>
						<label class="efb-deactivate-consent">
							<input type="checkbox" class="efb-deactivate-consent-input" checked>
							<span><?php echo esc_html( $text['consentLabel'] ); ?></span>
						</label>
					</div>

					<?php /* Honeypot: hidden from people, irresistible to bots. */ ?>
					<div class="efb-deactivate-hp" aria-hidden="true">
						<label>
							<?php echo esc_html( $text['emailLabel'] ); ?>
							<input type="text" class="efb-deactivate-hp-input" tabindex="-1" autocomplete="off">
						</label>
					</div>

					<p class="efb-deactivate-error" role="alert" hidden></p>
					<p class="efb-deactivate-privacy"><?php echo esc_html( $text['privacy'] ); ?></p>
				</div>

				<div class="efb-deactivate-done" hidden>
					<h2 class="efb-deactivate-title"><?php echo esc_html( $text['thanksTitle'] ); ?></h2>
					<p class="efb-deactivate-done-message"></p>
					<div class="efb-deactivate-coupon" hidden>
						<code class="efb-deactivate-coupon-code"></code>
						<button type="button" class="button efb-deactivate-copy"><?php echo esc_html( $text['copy'] ); ?></button>
					</div>
				</div>

				<div class="efb-deactivate-foot">
					<button type="button" class="button efb-deactivate-skip"><?php echo esc_html( $text['skip'] ); ?></button>
					<button type="button" class="button button-primary efb-deactivate-submit"><?php echo esc_html( $text['submit'] ); ?></button>
					<button type="button" class="button button-primary efb-deactivate-continue" hidden><?php echo esc_html( $text['continueLabel'] ); ?></button>
				</div>
			</div>
		</div>
		<?php
	}

	/*
	 * ---------------------------------------------------------------------
	 * Submission
	 * ---------------------------------------------------------------------
	 */

	/**
	 * AJAX: validate the answer, forward it, and report back.
	 *
	 * The response is always a success envelope. The browser's only job after
	 * this call is to deactivate the plugin, and a transport problem on our
	 * side is not the person's problem.
	 *
	 * @return void
	 */
	public function ajax_submit_efb() {
		if ( ! current_user_can( 'deactivate_plugins' ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'You are not allowed to do that.', 'easy-form-builder' ) ), 403 );
		}

		if ( ! check_ajax_referer( self::ACTION, 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Security check failed. Please reload the page.', 'easy-form-builder' ) ), 403 );
		}

		$text    = $this->strings_efb();
		$reasons = $this->reasons_efb();
		$reason  = isset( $_POST['reason'] ) ? sanitize_key( wp_unslash( $_POST['reason'] ) ) : '';

		if ( ! isset( $reasons[ $reason ] ) ) {
			wp_send_json_success(
				array(
					'ok'      => false,
					'message' => $text['sendFailed'],
				)
			);
		}

		// Deliberately not sanitize_textarea_field(): that would keep the text
		// but also keep tags as entities. Reports are read as plain text, so
		// markup is stripped outright, here and again on the server.
		$details = isset( $_POST['details'] ) ? (string) wp_unslash( $_POST['details'] ) : '';
		$details = trim( wp_strip_all_tags( $details, false ) );
		$details = function_exists( 'mb_substr' ) ? mb_substr( $details, 0, self::MAX_DETAILS ) : substr( $details, 0, self::MAX_DETAILS );

		if ( $reasons[ $reason ]['detail'] && '' === $details ) {
			wp_send_json_success(
				array(
					'ok'      => false,
					'message' => $text['detailsRequired'],
				)
			);
		}

		if ( 'bug' === $reason && $this->details_too_short_efb( $details ) ) {
			wp_send_json_success(
				array(
					'ok'      => false,
					'message' => $text['detailsTooShort'],
				)
			);
		}

		$email = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
		if ( '' !== $email && ! is_email( $email ) ) {
			$email = '';
		}
		$contact_ok = ! empty( $_POST['contact_ok'] ) && '' !== $email;

		// A filled honeypot means this was not a person clicking Deactivate.
		$honeypot = isset( $_POST['hp'] ) ? trim( (string) wp_unslash( $_POST['hp'] ) ) : '';
		if ( '' !== $honeypot ) {
			wp_send_json_success(
				array(
					'ok'      => false,
					'message' => $text['sendFailed'],
				)
			);
		}

		if ( ! $this->within_daily_limit_efb() ) {
			wp_send_json_success(
				array(
					'ok'      => false,
					'message' => $text['sendFailed'],
				)
			);
		}

		$result = $this->send_report_efb(
			array(
				'reason'     => $reason,
				'details'    => $details,
				'email'      => $contact_ok ? $email : '',
				'contact_ok' => $contact_ok ? 1 : 0,
				'hp'         => '',
				'env'        => $this->collect_env_efb(),
			)
		);

		if ( empty( $result['ok'] ) ) {
			wp_send_json_success(
				array(
					'ok'      => false,
					'message' => $text['sendFailed'],
				)
			);
		}

		$this->record_send_efb();

		$coupon  = isset( $result['coupon'] ) && is_array( $result['coupon'] ) ? $result['coupon'] : array();
		$state   = isset( $coupon['state'] ) ? (string) $coupon['state'] : 'none';
		$code    = isset( $coupon['code'] ) ? (string) $coupon['code'] : '';
		$message = $text['thanksMessage'];

		if ( 'issued' === $state && '' !== $code ) {
			$message = $text['couponIssued'];
		} elseif ( 'pending' === $state ) {
			$message = $text['couponPending'];
		}

		wp_send_json_success(
			array(
				'ok'      => true,
				'message' => $message,
				'coupon'  => array(
					'state' => $state,
					'code'  => 'issued' === $state ? $code : '',
				),
			)
		);
	}

	/**
	 * Whether a bug description is too short to be worth sending.
	 *
	 * Counted in characters, not bytes, so a Persian or Arabic sentence is not
	 * held to a stricter standard than an English one.
	 *
	 * @param string $details Description.
	 * @return bool
	 */
	protected function details_too_short_efb( $details ) {
		$length = function_exists( 'mb_strlen' ) ? mb_strlen( $details ) : strlen( $details );

		return $length < self::MIN_BUG_DETAILS;
	}

	/**
	 * The version numbers that make a bug report reproducible.
	 *
	 * Everything here is already public or already sent during licence checks.
	 * No form content, no submissions, no user records.
	 *
	 * @return array
	 */
	public function collect_env_efb() {
		global $wpdb;

		$forms = 0;
		if ( isset( $wpdb ) && is_object( $wpdb ) ) {
			// class-Emsfb-install.php creates this as "emsfb_form", singular.
			$table = $wpdb->prefix . 'emsfb_form';
			$found = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			if ( $found === $table ) {
				$forms = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery
			}
		}

		$installed_days = 0;
		$installed_at   = get_option( 'emsfb_install_date' );
		if ( $installed_at ) {
			$timestamp = is_numeric( $installed_at ) ? (int) $installed_at : strtotime( (string) $installed_at );
			if ( $timestamp ) {
				$installed_days = max( 0, (int) floor( ( time() - $timestamp ) / DAY_IN_SECONDS ) );
			}
		}

		return array(
			'plugin_version' => defined( 'EMSFB_PLUGIN_VERSION' ) ? EMSFB_PLUGIN_VERSION : '',
			'wp_version'     => get_bloginfo( 'version' ),
			'php_version'    => PHP_VERSION,
			'mysql_version'  => isset( $wpdb ) && is_object( $wpdb ) ? (string) $wpdb->db_version() : '',
			'locale'         => get_locale(),
			'theme'          => (string) get_template(),
			'is_multisite'   => is_multisite() ? '1' : '0',
			'plugins_count'  => (string) count( (array) get_option( 'active_plugins', array() ) ),
			'forms_count'    => (string) $forms,
			'pro_state'      => (string) get_option( 'emsfb_pro', '-1' ),
			'installed_days' => (string) $installed_days,
		);
	}

	/**
	 * Sign one report and post it to the feedback service.
	 *
	 * @param array $payload Report payload.
	 * @return array {
	 *     @type bool  $ok     Whether the service accepted the report.
	 *     @type array $coupon Coupon block returned by the service.
	 * }
	 */
	protected function send_report_efb( $payload ) {
		$identity = $this->ensure_identity_efb();
		if ( empty( $identity['site_id'] ) || empty( $identity['secret'] ) ) {
			return array( 'ok' => false );
		}

		$body     = wp_json_encode( $payload );
		$response = $this->post_signed_report_efb( $identity, $body );

		/*
		 * A stored identity the service no longer recognises - its records were
		 * restored from a backup, or the master key was rotated - would
		 * otherwise leave this site unable to report anything, ever. One
		 * re-registration, once, on exactly the statuses that mean "I do not
		 * know you".
		 */
		if ( null === $response && in_array( $this->last_status, array( 401, 403, 404 ), true ) ) {
			delete_option( self::OPTION_IDENTITY );
			delete_transient( 'emsfb_feedback_register_backoff' );

			$identity = $this->ensure_identity_efb();
			if ( empty( $identity['site_id'] ) || empty( $identity['secret'] ) ) {
				return array( 'ok' => false );
			}

			$response = $this->post_signed_report_efb( $identity, $body );
		}

		if ( ! is_array( $response ) || empty( $response['ok'] ) ) {
			return array( 'ok' => false );
		}

		return array(
			'ok'     => true,
			'coupon' => isset( $response['coupon'] ) ? (array) $response['coupon'] : array(),
		);
	}

	/**
	 * Sign and POST one already-encoded report body.
	 *
	 * @param array  $identity Stored identity.
	 * @param string $body     JSON body.
	 * @return array|null
	 */
	protected function post_signed_report_efb( $identity, $body ) {
		$timestamp = time();
		$nonce     = bin2hex( random_bytes( 16 ) );

		$canonical = implode(
			"\n",
			array(
				'v1',
				$identity['site_id'],
				(string) $timestamp,
				$nonce,
				hash( 'sha256', $body ),
			)
		);

		$headers = array(
			'Content-Type'    => 'application/json',
			'X-WSF-Site'      => $identity['site_id'],
			'X-WSF-Timestamp' => (string) $timestamp,
			'X-WSF-Nonce'     => $nonce,
			'X-WSF-Signature' => hash_hmac( 'sha256', $canonical, $identity['secret'] ),
		);

		return $this->request_efb( '/report', $body, $headers, $identity['endpoint'] );
	}

	/*
	 * ---------------------------------------------------------------------
	 * Identity with the feedback service
	 * ---------------------------------------------------------------------
	 */

	/**
	 * Return this site's identity, registering it the first time.
	 *
	 * @return array
	 */
	public function ensure_identity_efb() {
		$identity = get_option( self::OPTION_IDENTITY );

		if ( is_array( $identity ) && ! empty( $identity['site_id'] ) && ! empty( $identity['secret'] ) ) {
			return $identity;
		}

		// A failed registration backs off rather than retrying on every click:
		// a blocked or offline endpoint must not turn into a request storm.
		if ( get_transient( 'emsfb_feedback_register_backoff' ) ) {
			return array();
		}

		$identity = $this->register_site_efb();
		if ( empty( $identity ) ) {
			set_transient( 'emsfb_feedback_register_backoff', 1, HOUR_IN_SECONDS );
			return array();
		}

		update_option( self::OPTION_IDENTITY, $identity, false );

		// Only now can the service fetch the proof: the challenge has to be on
		// disk before the callback arrives.
		$this->verify_site_efb( $identity );

		return $identity;
	}

	/**
	 * Ask the service for an identity and a challenge.
	 *
	 * @return array Empty array when no endpoint answered.
	 */
	protected function register_site_efb() {
		$body = wp_json_encode(
			array(
				'site_url'       => home_url( '/' ),
				'plugin_version' => defined( 'EMSFB_PLUGIN_VERSION' ) ? EMSFB_PLUGIN_VERSION : '',
				'locale'         => get_locale(),
			)
		);

		foreach ( $this->endpoints_efb() as $endpoint ) {
			$response = $this->request_efb( '/register', $body, array( 'Content-Type' => 'application/json' ), $endpoint );

			if ( is_array( $response ) && ! empty( $response['site_id'] ) && ! empty( $response['secret'] ) ) {
				return array(
					'site_id'   => sanitize_text_field( (string) $response['site_id'] ),
					'secret'    => sanitize_text_field( (string) $response['secret'] ),
					'challenge' => isset( $response['challenge'] ) ? sanitize_text_field( (string) $response['challenge'] ) : '',
					'endpoint'  => $endpoint,
					'created'   => time(),
				);
			}
		}

		return array();
	}

	/**
	 * Ask the service to run its callback and confirm we own this domain.
	 *
	 * @param array $identity Stored identity.
	 * @return void
	 */
	protected function verify_site_efb( $identity ) {
		$this->request_efb(
			'/verify',
			wp_json_encode( array( 'site_id' => $identity['site_id'] ) ),
			array( 'Content-Type' => 'application/json' ),
			$identity['endpoint']
		);
	}

	/**
	 * Serve the proof the feedback service asks for, then stop.
	 *
	 * This is what separates a real site from a stranger claiming its domain:
	 * only an installation that received the challenge can answer correctly.
	 * The raw challenge is never printed - the answer is a keyed digest of it.
	 *
	 * @return void
	 */
	public function answer_ownership_challenge_efb() {
		$requested = isset( $_GET['ws_efb_verify'] ) ? sanitize_text_field( wp_unslash( $_GET['ws_efb_verify'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! preg_match( '/^[a-f0-9]{32}$/i', $requested ) ) {
			return;
		}

		$identity = get_option( self::OPTION_IDENTITY );
		if ( ! is_array( $identity ) || empty( $identity['site_id'] ) || empty( $identity['challenge'] ) ) {
			return;
		}

		// Constant-time: the site id is the only thing an outsider could guess at.
		if ( ! hash_equals( (string) $identity['site_id'], $requested ) ) {
			return;
		}

		nocache_headers();
		header( 'Content-Type: text/plain; charset=utf-8' );
		header( 'X-Robots-Tag: noindex' );
		echo esc_html( hash_hmac( 'sha256', 'ws-efb-proof|' . $identity['site_id'], $identity['challenge'] ) );
		exit;
	}

	/*
	 * ---------------------------------------------------------------------
	 * Transport and bookkeeping
	 * ---------------------------------------------------------------------
	 */

	/**
	 * Hosts to try, in order.
	 *
	 * Reuses the add-on endpoint ordering, so a Persian site talks to the
	 * mirror it can actually reach before the main domain.
	 *
	 * @return array
	 */
	public function endpoints_efb() {
		$endpoints = array();

		// One explicit override, for a staging service or a local test rig.
		// Define it in wp-config.php or an mu-plugin. The filter below still
		// runs, so nothing here is a dead end.
		if ( defined( 'EMSFB_FEEDBACK_SERVER_URL' ) && EMSFB_FEEDBACK_SERVER_URL ) {
			$endpoints = array( untrailingslashit( EMSFB_FEEDBACK_SERVER_URL ) );
		}

		if ( ! $endpoints && function_exists( 'get_efbFunction' ) ) {
			$efb_function = get_efbFunction();
			if ( is_object( $efb_function ) && method_exists( $efb_function, 'addon_api_domains_efb' ) ) {
				$domains = $efb_function->addon_api_domains_efb();
				if ( isset( $domains['endpoints'] ) && is_array( $domains['endpoints'] ) ) {
					$endpoints = $domains['endpoints'];
				}
			}
		}

		if ( ! $endpoints && defined( 'EMSFB_SERVER_URL' ) ) {
			$endpoints = array( untrailingslashit( EMSFB_SERVER_URL ) );
		}

		/**
		 * Filter the feedback service hosts.
		 *
		 * @param array $endpoints Base URLs, tried in order.
		 */
		$endpoints = apply_filters( 'emsfb_feedback_endpoints_efb', $endpoints );

		return array_values( array_filter( array_map( 'untrailingslashit', (array) $endpoints ) ) );
	}

	/**
	 * POST a JSON body to one feedback route.
	 *
	 * @param string $path     Route path, e.g. "/report".
	 * @param string $body     JSON body.
	 * @param array  $headers  Request headers.
	 * @param string $endpoint Base URL; falls back to the first known endpoint.
	 * @return array|null Decoded response, or null on any failure.
	 */
	protected function request_efb( $path, $body, $headers, $endpoint = '' ) {
		$this->last_status = 0;

		if ( '' === $endpoint ) {
			$endpoints = $this->endpoints_efb();
			$endpoint  = isset( $endpoints[0] ) ? $endpoints[0] : '';
		}

		if ( '' === $endpoint ) {
			return null;
		}

		$response = wp_remote_post(
			untrailingslashit( $endpoint ) . self::API_BASE . $path,
			array(
				'timeout'     => 10,
				'redirection' => 2,
				'headers'     => $headers,
				'body'        => $body,
			)
		);

		if ( is_wp_error( $response ) ) {
			return null;
		}

		$code              = (int) wp_remote_retrieve_response_code( $response );
		$this->last_status = $code;

		if ( $code < 200 || $code >= 300 ) {
			return null;
		}

		$decoded = json_decode( (string) wp_remote_retrieve_body( $response ), true );

		return is_array( $decoded ) ? $decoded : null;
	}

	/**
	 * Whether this site is still under its own daily send cap.
	 *
	 * @return bool
	 */
	protected function within_daily_limit_efb() {
		$state = get_option( self::OPTION_STATE );
		$state = is_array( $state ) ? $state : array();
		$today = gmdate( 'Y-m-d' );

		if ( ! isset( $state['day'] ) || $state['day'] !== $today ) {
			return true;
		}

		return (int) ( isset( $state['count'] ) ? $state['count'] : 0 ) < self::DAILY_LIMIT;
	}

	/**
	 * Count one sent report against the daily cap.
	 *
	 * @return void
	 */
	protected function record_send_efb() {
		$state = get_option( self::OPTION_STATE );
		$state = is_array( $state ) ? $state : array();
		$today = gmdate( 'Y-m-d' );

		if ( ! isset( $state['day'] ) || $state['day'] !== $today ) {
			$state = array(
				'day'   => $today,
				'count' => 0,
			);
		}

		$state['count'] = (int) ( isset( $state['count'] ) ? $state['count'] : 0 ) + 1;
		$state['last']  = time();

		update_option( self::OPTION_STATE, $state, false );
	}

	/*
	 * ---------------------------------------------------------------------
	 * Wording
	 * ---------------------------------------------------------------------
	 */

	/**
	 * The modal's wording in the admin's own language.
	 *
	 * Three sources, most specific first: phrases pushed by the White Studio
	 * settings payload, the bundled translations below, then the English
	 * source strings. The bundled table exists because this plugin's phrases
	 * normally arrive from the server, and a person who never had a chance to
	 * fetch them should still be able to read the question we are asking.
	 *
	 * @return array
	 */
	public function strings_efb() {
		$defaults = array(
			'title'                     => esc_html__( 'One moment before you deactivate', 'easy-form-builder' ),
			'subtitle'                  => esc_html__( 'Tell us what went wrong and we will fix it. It takes about 20 seconds.', 'easy-form-builder' ),
			'reasonBug'                 => esc_html__( 'I found a bug or something is broken', 'easy-form-builder' ),
			'reasonMissingFeature'      => esc_html__( 'A feature I needed is missing', 'easy-form-builder' ),
			'reasonHardToUse'           => esc_html__( 'It was hard to set up or use', 'easy-form-builder' ),
			'reasonFoundBetter'         => esc_html__( 'I found a better plugin', 'easy-form-builder' ),
			'reasonTemporary'           => esc_html__( 'Only temporary - I am troubleshooting my site', 'easy-form-builder' ),
			'reasonNoLongerNeeded'      => esc_html__( 'I no longer need it', 'easy-form-builder' ),
			'reasonOther'               => esc_html__( 'Another reason', 'easy-form-builder' ),
			'placeholderBug'            => esc_html__( 'What happened, and where? Which page or which form? If you saw an error message, paste it here.', 'easy-form-builder' ),
			'placeholderMissingFeature' => esc_html__( 'Which feature were you looking for?', 'easy-form-builder' ),
			'placeholderHardToUse'      => esc_html__( 'Which part was confusing?', 'easy-form-builder' ),
			'placeholderFoundBetter'    => esc_html__( 'Which plugin did you choose?', 'easy-form-builder' ),
			'placeholderOther'          => esc_html__( 'Anything you would like us to know', 'easy-form-builder' ),
			'rewardTitle'               => esc_html__( 'Report the bug, get 100% off your first year', 'easy-form-builder' ),
			'rewardBody'                => esc_html__( 'Describe the bug and we will send you a 100% discount code for your first year of the Pro version, as a thank you.', 'easy-form-builder' ),
			'emailLabel'                => esc_html__( 'Email address for the discount code', 'easy-form-builder' ),
			'consentLabel'              => esc_html__( 'Email me the discount code and any follow-up question', 'easy-form-builder' ),
			'privacy'                   => esc_html__( 'We only send your site address, the plugin, WordPress and PHP versions, and the message you wrote above.', 'easy-form-builder' ),
			'skip'                      => esc_html__( 'Skip and deactivate', 'easy-form-builder' ),
			'submit'                    => esc_html__( 'Send and deactivate', 'easy-form-builder' ),
			'sending'                   => esc_html__( 'Sending&hellip;', 'easy-form-builder' ),
			'detailsRequired'           => esc_html__( 'Please add a short description first.', 'easy-form-builder' ),
			'detailsTooShort'           => esc_html__( 'A little more detail, please - one sentence about what broke is enough.', 'easy-form-builder' ),
			'chooseReason'              => esc_html__( 'Please choose one of the options.', 'easy-form-builder' ),
			'thanksTitle'               => esc_html__( 'Thank you', 'easy-form-builder' ),
			'thanksMessage'             => esc_html__( 'Your feedback has been received. It goes straight to the people who build this plugin.', 'easy-form-builder' ),
			'couponIssued'              => esc_html__( 'Here is your 100% discount code for the first year:', 'easy-form-builder' ),
			'couponPending'             => esc_html__( 'Your report has been received. We will email your 100% discount code after a quick check.', 'easy-form-builder' ),
			'sendFailed'                => esc_html__( 'We could not reach our server, so your note was not sent. The plugin will still be deactivated.', 'easy-form-builder' ),
			'copy'                      => esc_html__( 'Copy', 'easy-form-builder' ),
			'copied'                    => esc_html__( 'Copied', 'easy-form-builder' ),
			'continueLabel'             => esc_html__( 'Continue deactivating', 'easy-form-builder' ),
			'close'                     => esc_html__( 'Close', 'easy-form-builder' ),
		);

		$bundled = $this->bundled_translations_efb();
		$locale  = function_exists( 'get_user_locale' ) ? get_user_locale() : get_locale();
		$short   = strtolower( substr( (string) $locale, 0, 2 ) );

		if ( isset( $bundled[ $short ] ) ) {
			$defaults = array_merge( $defaults, $bundled[ $short ] );
		}

		// A phrase pushed from the server always wins, so wording can be
		// corrected without shipping a plugin release.
		$settings = function_exists( 'get_setting_Emsfb' ) ? get_setting_Emsfb( 'decoded' ) : null;
		if ( is_object( $settings ) && isset( $settings->text ) && is_object( $settings->text ) ) {
			foreach ( array_keys( $defaults ) as $key ) {
				$remote_key = 'deact' . ucfirst( $key );
				if ( isset( $settings->text->{$remote_key} ) && is_string( $settings->text->{$remote_key} ) && '' !== $settings->text->{$remote_key} ) {
					$defaults[ $key ] = $settings->text->{$remote_key};
				}
			}
		}

		return $defaults;
	}

	/**
	 * Translations shipped with the plugin for the locales it targets.
	 *
	 * @return array
	 */
	protected function bundled_translations_efb() {
		return array(
			'fa' => array(
				'title'                     => 'یک لحظه پیش از غیرفعال‌سازی',
				'subtitle'                  => 'بگویید چه چیزی درست کار نکرد تا همان را درست کنیم. کمتر از ۲۰ ثانیه وقت می‌گیرد.',
				'reasonBug'                 => 'باگ یا ایراد دیدم',
				'reasonMissingFeature'      => 'قابلیتی که نیاز داشتم وجود نداشت',
				'reasonHardToUse'           => 'کار کردن با آن برایم سخت بود',
				'reasonFoundBetter'         => 'افزونه‌ی بهتری پیدا کردم',
				'reasonTemporary'           => 'موقتی است؛ در حال عیب‌یابی سایت هستم',
				'reasonNoLongerNeeded'      => 'دیگر به آن نیازی ندارم',
				'reasonOther'               => 'دلیل دیگری دارم',
				'placeholderBug'            => 'چه اتفاقی افتاد و کجا؟ در کدام صفحه یا کدام فرم؟ اگر پیام خطایی دیدید همان را اینجا بنویسید.',
				'placeholderMissingFeature' => 'دنبال چه قابلیتی بودید؟',
				'placeholderHardToUse'      => 'کدام بخش گیج‌کننده بود؟',
				'placeholderFoundBetter'    => 'کدام افزونه را انتخاب کردید؟',
				'placeholderOther'          => 'هر چیزی که دوست دارید بدانیم',
				'rewardTitle'               => 'با گزارش دادن باگ، ۱۰۰٪ تخفیف سال اول بگیرید',
				'rewardBody'                => 'باگ را توضیح دهید تا به‌عنوان تشکر، کد تخفیف ۱۰۰٪ برای سال اول نسخه‌ی حرفه‌ای برایتان ارسال شود.',
				'emailLabel'                => 'ایمیل برای دریافت کد تخفیف',
				'consentLabel'              => 'کد تخفیف و پرسش‌های بعدی برایم ایمیل شود',
				'privacy'                   => 'فقط این‌ها ارسال می‌شود: نشانی سایت، نسخه‌ی افزونه و وردپرس و PHP، و همان متنی که نوشتید.',
				'skip'                      => 'رد کردن و غیرفعال‌سازی',
				'submit'                    => 'ارسال و غیرفعال‌سازی',
				'sending'                   => 'در حال ارسال…',
				'detailsRequired'           => 'لطفاً یک توضیح کوتاه بنویسید.',
				'detailsTooShort'           => 'کمی بیشتر توضیح دهید؛ یک جمله درباره‌ی اینکه چه چیزی خراب شد کافی است.',
				'chooseReason'              => 'لطفاً یکی از گزینه‌ها را انتخاب کنید.',
				'thanksTitle'               => 'ممنون از شما',
				'thanksMessage'             => 'گزارش شما ثبت شد و مستقیم به دست سازندگان همین افزونه می‌رسد.',
				'couponIssued'              => 'این هم کد تخفیف ۱۰۰٪ سال اول شما:',
				'couponPending'             => 'گزارش شما ثبت شد. پس از یک بررسی کوتاه، کد تخفیف ۱۰۰٪ به ایمیل شما ارسال می‌شود.',
				'sendFailed'                => 'ارتباط با سرور برقرار نشد و یادداشت شما ارسال نشد. افزونه در هر صورت غیرفعال می‌شود.',
				'copy'                      => 'کپی',
				'copied'                    => 'کپی شد',
				'continueLabel'             => 'ادامه و غیرفعال‌سازی',
				'close'                     => 'بستن',
			),
			'ar' => array(
				'title'                     => 'لحظة واحدة قبل التعطيل',
				'subtitle'                  => 'أخبرنا بما لم ينجح وسنصلحه. لن يستغرق الأمر أكثر من ٢٠ ثانية.',
				'reasonBug'                 => 'واجهت خللاً أو شيئاً لا يعمل',
				'reasonMissingFeature'      => 'الميزة التي أحتاجها غير موجودة',
				'reasonHardToUse'           => 'كان إعداده أو استخدامه صعباً',
				'reasonFoundBetter'         => 'وجدت إضافة أفضل',
				'reasonTemporary'           => 'تعطيل مؤقت لتشخيص مشكلة في الموقع',
				'reasonNoLongerNeeded'      => 'لم أعد بحاجة إليه',
				'reasonOther'               => 'سبب آخر',
				'placeholderBug'            => 'ماذا حدث وأين؟ في أي صفحة أو أي نموذج؟ إذا ظهرت رسالة خطأ فالصقها هنا.',
				'placeholderMissingFeature' => 'ما الميزة التي كنت تبحث عنها؟',
				'placeholderHardToUse'      => 'أي جزء كان مربكاً؟',
				'placeholderFoundBetter'    => 'أي إضافة اخترت؟',
				'placeholderOther'          => 'أي شيء تود أن نعرفه',
				'rewardTitle'               => 'أبلغ عن الخلل واحصل على خصم ١٠٠٪ للسنة الأولى',
				'rewardBody'                => 'صف الخلل وسنرسل لك رمز خصم ١٠٠٪ للسنة الأولى من النسخة الاحترافية، شكراً لك.',
				'emailLabel'                => 'البريد الإلكتروني لاستلام رمز الخصم',
				'consentLabel'              => 'أرسلوا لي رمز الخصم وأي سؤال للمتابعة',
				'privacy'                   => 'نرسل فقط: عنوان موقعك، وإصدارات الإضافة ووردبريس وPHP، والنص الذي كتبته.',
				'skip'                      => 'تخطٍ وتعطيل',
				'submit'                    => 'إرسال وتعطيل',
				'sending'                   => 'جارٍ الإرسال…',
				'detailsRequired'           => 'يرجى كتابة وصف قصير أولاً.',
				'detailsTooShort'           => 'المزيد من التفاصيل من فضلك؛ جملة واحدة عمّا تعطّل تكفي.',
				'chooseReason'              => 'يرجى اختيار أحد الخيارات.',
				'thanksTitle'               => 'شكراً لك',
				'thanksMessage'             => 'وصلنا تقريرك ويذهب مباشرة إلى من يبنون هذه الإضافة.',
				'couponIssued'              => 'هذا رمز خصم ١٠٠٪ للسنة الأولى:',
				'couponPending'             => 'وصلنا تقريرك. سنرسل رمز الخصم ١٠٠٪ بعد مراجعة سريعة.',
				'sendFailed'                => 'تعذر الوصول إلى خادمنا فلم تُرسل ملاحظتك. سيتم تعطيل الإضافة على أي حال.',
				'copy'                      => 'نسخ',
				'copied'                    => 'تم النسخ',
				'continueLabel'             => 'متابعة التعطيل',
				'close'                     => 'إغلاق',
			),
			'de' => array(
				'title'                     => 'Einen Moment, bevor Sie deaktivieren',
				'subtitle'                  => 'Sagen Sie uns, was nicht funktioniert hat - wir bringen es in Ordnung. Dauert etwa 20 Sekunden.',
				'reasonBug'                 => 'Ich habe einen Fehler gefunden',
				'reasonMissingFeature'      => 'Eine Funktion, die ich brauche, fehlt',
				'reasonHardToUse'           => 'Einrichtung oder Bedienung war zu kompliziert',
				'reasonFoundBetter'         => 'Ich habe ein besseres Plugin gefunden',
				'reasonTemporary'           => 'Nur vorübergehend - ich suche einen Fehler auf meiner Website',
				'reasonNoLongerNeeded'      => 'Ich brauche es nicht mehr',
				'reasonOther'               => 'Ein anderer Grund',
				'placeholderBug'            => 'Was ist passiert und wo? Auf welcher Seite oder in welchem Formular? Fehlermeldung bitte hier einfügen.',
				'placeholderMissingFeature' => 'Welche Funktion haben Sie gesucht?',
				'placeholderHardToUse'      => 'Welcher Teil war verwirrend?',
				'placeholderFoundBetter'    => 'Für welches Plugin haben Sie sich entschieden?',
				'placeholderOther'          => 'Alles, was wir wissen sollten',
				'rewardTitle'               => 'Fehler melden und 100% Rabatt im ersten Jahr erhalten',
				'rewardBody'                => 'Beschreiben Sie den Fehler und Sie erhalten als Dank einen 100%-Rabattcode für Ihr erstes Jahr der Pro-Version.',
				'emailLabel'                => 'E-Mail-Adresse für den Rabattcode',
				'consentLabel'              => 'Schicken Sie mir den Rabattcode und mögliche Rückfragen per E-Mail',
				'privacy'                   => 'Wir senden nur: Ihre Website-Adresse, die Versionen von Plugin, WordPress und PHP sowie Ihren Text.',
				'skip'                      => 'Überspringen und deaktivieren',
				'submit'                    => 'Senden und deaktivieren',
				'sending'                   => 'Wird gesendet…',
				'detailsRequired'           => 'Bitte schreiben Sie zuerst eine kurze Beschreibung.',
				'detailsTooShort'           => 'Bitte etwas ausführlicher - ein Satz dazu, was kaputt war, genügt.',
				'chooseReason'              => 'Bitte wählen Sie eine der Optionen.',
				'thanksTitle'               => 'Vielen Dank',
				'thanksMessage'             => 'Ihre Rückmeldung ist angekommen - direkt bei den Leuten, die dieses Plugin bauen.',
				'couponIssued'              => 'Hier ist Ihr 100%-Rabattcode für das erste Jahr:',
				'couponPending'             => 'Ihre Meldung ist angekommen. Den 100%-Rabattcode senden wir nach einer kurzen Prüfung per E-Mail.',
				'sendFailed'                => 'Unser Server war nicht erreichbar, Ihre Nachricht wurde nicht gesendet. Das Plugin wird trotzdem deaktiviert.',
				'copy'                      => 'Kopieren',
				'copied'                    => 'Kopiert',
				'continueLabel'             => 'Weiter deaktivieren',
				'close'                     => 'Schließen',
			),
		);
	}
}
