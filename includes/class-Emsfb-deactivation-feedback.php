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
	 * This site could not open a connection to the outside world.
	 *
	 * Not the same thing as the service being down, and it must never be
	 * described as such: on a host whose outbound traffic is filtered - a
	 * common arrangement in several countries - nothing is wrong with our
	 * server, and telling the administrator otherwise sends them looking in
	 * the wrong place.
	 */
	const FAILURE_OFFLINE = 'offline';

	/** We were reached, and answered badly or not at all. */
	const FAILURE_SERVER = 'server';

	/** How long a failed registration waits before the next attempt. */
	const REGISTER_BACKOFF = 'emsfb_feedback_register_backoff';

	/**
	 * HTTP status of the most recent call to the feedback service.
	 *
	 * @var int
	 */
	protected $last_status = 0;

	/**
	 * Why the most recent call failed: one of the FAILURE_* constants, or ''.
	 *
	 * @var string
	 */
	protected $last_failure = '';

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

		// The shared dialog design system carries the tokens; the survey's own
		// stylesheet only adds the parts that are specific to it. Declared as a
		// dependency so the cascade order holds however it was registered.
		wp_enqueue_style(
			'efb-modal-system',
			EMSFB_PLUGIN_URL . 'includes/admin/assets/css/modal-system-efb.css',
			array(),
			EMSFB_PLUGIN_VERSION
		);

		wp_enqueue_style(
			'efb-deactivation-feedback',
			EMSFB_PLUGIN_URL . 'includes/admin/assets/css/deactivation-feedback-efb.css',
			array( 'efb-modal-system' ),
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
					'failure' => isset( $result['failure'] ) ? $result['failure'] : self::FAILURE_SERVER,
					'message' => $this->failure_message_efb( isset( $result['failure'] ) ? $result['failure'] : '' ),
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
					// The code itself is never shown in the modal; it is emailed instead.
					'code'  => '',
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
	 * Public because it is the whole signed-report pipeline - identity,
	 * HMAC, the one re-registration retry - and Review_Request sends its
	 * five-star report through exactly the same door rather than keeping a
	 * second copy of the signing code that could drift out of step with the
	 * service.
	 *
	 * @param array $payload Report payload.
	 * @return array {
	 *     @type bool   $ok      Whether the service accepted the report.
	 *     @type array  $coupon  Coupon block returned by the service.
	 *     @type string $failure On failure, one of the FAILURE_* constants.
	 * }
	 */
	public function send_report_efb( $payload ) {
		$identity = $this->ensure_identity_efb();
		if ( empty( $identity['site_id'] ) || empty( $identity['secret'] ) ) {
			return $this->failure_efb();
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
			delete_transient( self::REGISTER_BACKOFF );

			$identity = $this->ensure_identity_efb();
			if ( empty( $identity['site_id'] ) || empty( $identity['secret'] ) ) {
				return $this->failure_efb();
			}

			$response = $this->post_signed_report_efb( $identity, $body );
		}

		if ( ! is_array( $response ) || empty( $response['ok'] ) ) {
			return $this->failure_efb();
		}

		return array(
			'ok'     => true,
			'coupon' => isset( $response['coupon'] ) ? (array) $response['coupon'] : array(),
		);
	}

	/**
	 * A failed send, carrying whichever reason the transport last recorded.
	 *
	 * @return array
	 */
	protected function failure_efb() {
		return array(
			'ok'      => false,
			'failure' => self::FAILURE_OFFLINE === $this->last_failure ? self::FAILURE_OFFLINE : self::FAILURE_SERVER,
		);
	}

	/**
	 * The sentence to show for a failed send.
	 *
	 * Two situations, two different things to do about them, so two different
	 * messages. Blaming our server for a connection the host never let out
	 * would send the administrator to check a status page instead of their own
	 * outbound firewall, and vice versa.
	 *
	 * @param string $failure One of the FAILURE_* constants.
	 * @return string
	 */
	protected function failure_message_efb( $failure ) {
		$text = $this->strings_efb();

		if ( self::FAILURE_OFFLINE === $failure ) {
			return $text['sendFailedOffline'];
		}

		if ( self::FAILURE_SERVER === $failure ) {
			return $text['sendFailedServer'];
		}

		return $text['sendFailed'];
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
			/*
			 * An identity is only good for the host that issued it - the secret
			 * is derived from that service's master key, and reports are posted
			 * to the endpoint recorded here rather than to whatever the
			 * settings now say. So when the configured host changes (production
			 * to sandbox, or back), the stored identity is not stale, it is
			 * addressed to someone else, and keeping it would silently send
			 * every future report to the old server.
			 */
			$current = $this->endpoints_efb();
			$current = isset( $current[0] ) ? untrailingslashit( $current[0] ) : '';

			if ( '' === $current || untrailingslashit( (string) ( isset( $identity['endpoint'] ) ? $identity['endpoint'] : '' ) ) === $current ) {
				return $identity;
			}

			delete_option( self::OPTION_IDENTITY );
			delete_transient( self::REGISTER_BACKOFF );
		}

		// A failed registration backs off rather than retrying on every click:
		// a blocked or offline endpoint must not turn into a request storm. The
		// reason rides along so the second click still gets the right words
		// instead of falling back to a vaguer message than we already earned.
		$backoff = get_transient( self::REGISTER_BACKOFF );
		if ( $backoff ) {
			$this->last_failure = is_string( $backoff ) ? $backoff : self::FAILURE_SERVER;
			return array();
		}

		$identity = $this->register_site_efb();
		if ( empty( $identity ) ) {
			$reason = '' !== $this->last_failure ? $this->last_failure : self::FAILURE_SERVER;
			set_transient( self::REGISTER_BACKOFF, $reason, HOUR_IN_SECONDS );
			$this->last_failure = $reason;
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

		$worst = '';

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

			/*
			 * One host answering badly outranks another being unreachable: if
			 * anything at all replied, this site is not cut off from the
			 * internet, and saying so would be wrong.
			 */
			if ( self::FAILURE_SERVER === $this->last_failure ) {
				$worst = self::FAILURE_SERVER;
			} elseif ( '' === $worst ) {
				$worst = $this->last_failure;
			}
		}

		$this->last_failure = '' !== $worst ? $worst : self::FAILURE_SERVER;

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
	 * `EMSFB_SERVER_URL` is the single source of truth, so pointing the whole
	 * plugin at the sandbox points the feedback service there too - the same
	 * constant Review_Request::reward_endpoint_efb() already reads.
	 *
	 * This deliberately does *not* reuse the add-on endpoint order any more.
	 * That list answers a different question - where an add-on archive can be
	 * downloaded - and on a Persian site it appends the .ir mirror, which has
	 * never hosted the feedback service. Asking it here only bought a
	 * guaranteed 404 on the way to the host that was going to answer anyway.
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

		if ( ! $endpoints && defined( 'EMSFB_SERVER_URL' ) && EMSFB_SERVER_URL ) {
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
	 * Records *why* a call failed as well as that it did, because the two
	 * reasons deserve different words. See $last_failure.
	 *
	 * @param string $path     Route path, e.g. "/report".
	 * @param string $body     JSON body.
	 * @param array  $headers  Request headers.
	 * @param string $endpoint Base URL; falls back to the first known endpoint.
	 * @return array|null Decoded response, or null on any failure.
	 */
	protected function request_efb( $path, $body, $headers, $endpoint = '' ) {
		$this->last_status  = 0;
		$this->last_failure = '';

		if ( '' === $endpoint ) {
			$endpoints = $this->endpoints_efb();
			$endpoint  = isset( $endpoints[0] ) ? $endpoints[0] : '';
		}

		if ( '' === $endpoint ) {
			$this->last_failure = self::FAILURE_SERVER;
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

		/*
		 * A WP_Error here means no HTTP conversation happened at all: DNS did
		 * not resolve, the connection was refused or timed out, or the TLS
		 * handshake failed. From the plugin's side that is indistinguishable
		 * from - and usually is - the host being unable to reach the outside
		 * world, which is the ordinary situation on servers whose outbound
		 * traffic is filtered. It is emphatically not evidence that the
		 * feedback service is down, so it must not be reported as such.
		 */
		if ( is_wp_error( $response ) ) {
			$this->last_failure = self::FAILURE_OFFLINE;
			return null;
		}

		$code              = (int) wp_remote_retrieve_response_code( $response );
		$this->last_status = $code;

		// Anything with a status line means we reached them. Whatever went
		// wrong after that is ours to apologise for, not the host's to fix.
		if ( $code < 200 || $code >= 300 ) {
			$this->last_failure = self::FAILURE_SERVER;
			return null;
		}

		$decoded = json_decode( (string) wp_remote_retrieve_body( $response ), true );

		if ( ! is_array( $decoded ) ) {
			$this->last_failure = self::FAILURE_SERVER;
			return null;
		}

		return $decoded;
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
	 * The modal's wording.
	 *
	 * These go through the plugin's own text domain rather than the phrases
	 * the White Studio settings payload pushes for the rest of the admin: this
	 * screen has to read correctly on a site that has never once reached that
	 * server, which is precisely the situation the failure messages below are
	 * for.
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
			'rewardBody'                => esc_html__( 'Describe the bug and we will send you a 99% discount code for your first year of the Pro version, as a thank you.', 'easy-form-builder' ),
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
			'couponIssued'              => esc_html__( 'Your bug report has been received. Within the next 2 days we will email you your discount code, and ask for a few more details if we need them.', 'easy-form-builder' ),
			'couponPending'             => esc_html__( 'Your report has been received. We will email your 100% discount code after a quick check.', 'easy-form-builder' ),
			'sendFailed'                => esc_html__( 'We could not reach our server, so your note was not sent. The plugin will still be deactivated.', 'easy-form-builder' ),
			// Two failures that look identical from the modal but need opposite
			// things done about them, so they are never worded the same way.
			'sendFailedOffline'         => esc_html__( 'Your site could not open a connection to the internet, so your note was not sent. This is usually outbound traffic being blocked by the server or its network, rather than anything wrong on our side. The plugin will still be deactivated.', 'easy-form-builder' ),
			'sendFailedServer'          => esc_html__( 'Your site reached us, but our server did not answer properly, so your note was not sent. Nothing is wrong with your site - please try again later. The plugin will still be deactivated.', 'easy-form-builder' ),
			'copy'                      => esc_html__( 'Copy', 'easy-form-builder' ),
			'copied'                    => esc_html__( 'Copied', 'easy-form-builder' ),
			'continueLabel'             => esc_html__( 'Continue deactivating', 'easy-form-builder' ),
			'close'                     => esc_html__( 'Close', 'easy-form-builder' ),
		);

		return $defaults;
	}

}
