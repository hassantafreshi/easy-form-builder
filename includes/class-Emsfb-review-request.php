<?php
/**
 * The five-star invitation.
 *
 * Two weeks after someone starts using Easy Form Builder, a Free or Free Plus
 * site is asked - once, politely, on an Easy Form Builder screen and nowhere
 * else - to rate the plugin. Five stars earns a discount code for the first
 * year of Pro, issued by the same White Studio service that already rewards
 * bug reports.
 *
 * Four rules this file keeps:
 *
 *   1. Pro sites are never asked. They already paid; there is no reward to
 *      offer and no reason to interrupt them.
 *   2. Nobody is asked twice by accident. "Later" is a real fourteen-day
 *      snooze, "no thanks" is permanent, and both are stored before the modal
 *      closes.
 *   3. A rating below five stars never goes to WordPress.org. It opens the
 *      support route instead - pushing an unhappy person toward a public
 *      review form is how you earn a two-star review.
 *   4. The discount figure is never written into a sentence. Every string
 *      carries %s and the number arrives from discount_label_efb(), so it can
 *      be changed in one place - or by a filter - without touching a
 *      translation.
 *
 * @package Easy_Form_Builder
 */

namespace Emsfb;

defined( 'ABSPATH' ) || exit;

/**
 * Asks long-time free users for a review, and rewards five stars.
 */
class Review_Request {

	/** Option holding the review conversation's state. */
	const OPTION_STATE = 'emsfb_review_state';

	/** Option holding the moment this site first used the plugin. */
	const OPTION_INSTALLED = 'emsfb_install_date';

	/** Nonce action and AJAX action share one name, as elsewhere in the plugin. */
	const ACTION = 'emsfb_review_request';

	/** How long someone has to have been using the plugin before we ask. */
	const MIN_DAYS = 14;

	/**
	 * The gap between one sighting and the next.
	 *
	 * The invitation snoozes itself the moment it is printed, so this is the
	 * whole politeness policy in one number: a site sees it about once a month
	 * and never twice in a week of daily logins. "Maybe later" spends the same
	 * thirty days, which is why the two are one constant and not two.
	 */
	const SNOOZE_DAYS = 30;

	/** Query argument that forces the invitation open for a preview. */
	const PREVIEW_ARG = 'efb_review_preview';

	/** The reason key the feedback service files a five-star report under. */
	const REPORT_REASON = 'five_star';

	/** Default discount offered for a five-star review, as a percentage. */
	const DISCOUNT_PERCENT = 100;

	/** Ratings at or above this earn the reward and the WordPress.org route. */
	const REWARD_THRESHOLD = 5;

	/**
	 * Wire the hooks.
	 */
	public function __construct() {
		if ( ! is_admin() ) {
			return;
		}

		// The clock has to start even on a screen that never shows the modal,
		// otherwise a site that only ever visits the Plugins page would never
		// accumulate a single day.
		add_action( 'admin_init', array( $this, 'seed_install_date_efb' ) );

		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets_efb' ) );
		add_action( 'admin_footer', array( $this, 'render_modal_efb' ) );
		add_action( 'wp_ajax_' . self::ACTION, array( $this, 'ajax_state_efb' ) );
	}

	/*
	 * ---------------------------------------------------------------------
	 * When to ask
	 * ---------------------------------------------------------------------
	 */

	/**
	 * Record the moment this site started using the plugin.
	 *
	 * The option is read in two places and, until this method existed, was
	 * written in none - so every report claimed the site was installed today.
	 *
	 * A site that already has forms was clearly not installed this morning, so
	 * the oldest form's creation date is used instead of "now". Without that,
	 * shipping this feature would silently restart everybody's clock and no
	 * existing user would be asked for another fortnight.
	 *
	 * @return void
	 */
	public function seed_install_date_efb() {
		$stored = get_option( self::OPTION_INSTALLED );
		if ( $stored && $this->to_timestamp_efb( $stored ) ) {
			return;
		}

		$timestamp = $this->oldest_form_timestamp_efb();
		if ( ! $timestamp ) {
			$timestamp = time();
		}

		update_option( self::OPTION_INSTALLED, (int) $timestamp, false );
	}

	/**
	 * The creation date of the oldest form on this site, if any.
	 *
	 * @return int Unix timestamp, or 0 when there are no forms to date from.
	 */
	protected function oldest_form_timestamp_efb() {
		global $wpdb;

		if ( ! isset( $wpdb ) || ! is_object( $wpdb ) ) {
			return 0;
		}

		$table = $wpdb->prefix . 'emsfb_form';
		$found = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		if ( $found !== $table ) {
			return 0;
		}

		$oldest = $wpdb->get_var( "SELECT MIN(form_create_date) FROM {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery
		if ( ! $oldest ) {
			return 0;
		}

		// The column is a site-local datetime, so read it as one.
		$timestamp = function_exists( 'get_gmt_from_date' )
			? strtotime( get_gmt_from_date( (string) $oldest ) . ' UTC' )
			: strtotime( (string) $oldest );

		return $timestamp ? (int) $timestamp : 0;
	}

	/**
	 * Turn a stored install date - a timestamp or a datetime string - into a
	 * timestamp.
	 *
	 * @param mixed $value Stored value.
	 * @return int Unix timestamp, or 0 when unreadable.
	 */
	protected function to_timestamp_efb( $value ) {
		if ( is_numeric( $value ) ) {
			return (int) $value > 0 ? (int) $value : 0;
		}

		$parsed = strtotime( (string) $value );

		return $parsed ? (int) $parsed : 0;
	}

	/**
	 * How many whole days this site has been using the plugin.
	 *
	 * @return int
	 */
	public function days_in_use_efb() {
		$installed = $this->to_timestamp_efb( get_option( self::OPTION_INSTALLED ) );
		if ( ! $installed ) {
			return 0;
		}

		return max( 0, (int) floor( ( time() - $installed ) / DAY_IN_SECONDS ) );
	}

	/**
	 * Whether this site is on a plan the invitation is meant for.
	 *
	 * emsfb_pro: 0 onboarding, 1 Pro, 2 Free, 3 Free Plus. Only 2 and 3 are
	 * asked - a Pro site has nothing to gain from the offer, and an install
	 * still in onboarding has not seen the plugin yet.
	 *
	 * @return bool
	 */
	public function plan_is_eligible_efb() {
		$plan = (int) get_option( 'emsfb_pro', 2 );

		return in_array( $plan, array( 2, 3 ), true );
	}

	/**
	 * The stored state of the review conversation.
	 *
	 * @return array
	 */
	public function state_efb() {
		$state = get_option( self::OPTION_STATE );
		$state = is_array( $state ) ? $state : array();

		return array_merge(
			array(
				'status'        => 'pending',
				'snooze_until'  => 0,
				'shown'         => 0,
				'rating'        => 0,
				'rated_at'      => 0,
				'coupon_state'  => 'none',
				'coupon_code'   => '',
			),
			$state
		);
	}

	/**
	 * Store the state, merging over what is already there.
	 *
	 * @param array $changes Fields to change.
	 * @return array The stored state.
	 */
	protected function save_state_efb( array $changes ) {
		$state = array_merge( $this->state_efb(), $changes );

		update_option( self::OPTION_STATE, $state, false );

		return $state;
	}

	/**
	 * Whether the invitation should be shown to the current user right now.
	 *
	 * @return bool
	 */
	/**
	 * Whether this request is a deliberate preview.
	 *
	 * Adding ?efb_review_preview=1 to any Easy Form Builder screen opens the
	 * invitation whatever the plan, the age of the install or the stored
	 * answer - so it can be looked at on a site that would never be asked, this
	 * one included. It is a look, not a rehearsal: nothing is counted, nothing
	 * is snoozed, and no answer is written, so a preview cannot spend a real
	 * site's turn. The capability check is the only gate it does not lift.
	 *
	 * @return bool
	 */
	public function is_preview_efb() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- a
		// read-only preview of the admin's own screen; it changes nothing.
		if ( empty( $_GET[ self::PREVIEW_ARG ] ) ) {
			return false;
		}

		return current_user_can( 'manage_options' );
	}

	public function should_ask_efb() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return false;
		}

		if ( $this->is_preview_efb() ) {
			return true;
		}

		if ( ! $this->plan_is_eligible_efb() ) {
			return false;
		}

		if ( $this->days_in_use_efb() < self::MIN_DAYS ) {
			return false;
		}

		$state = $this->state_efb();

		if ( in_array( $state['status'], array( 'rated', 'dismissed' ), true ) ) {
			return false;
		}

		if ( 'snoozed' === $state['status'] && time() < (int) $state['snooze_until'] ) {
			return false;
		}

		/**
		 * Filter whether the five-star invitation may be shown.
		 *
		 * @param bool          $should Whether to ask.
		 * @param Review_Request $request The instance making the decision.
		 */
		return (bool) apply_filters( 'emsfb_review_should_ask_efb', true, $this );
	}

	/**
	 * Whether the current admin screen belongs to this plugin.
	 *
	 * The invitation only ever appears where someone is already using Easy
	 * Form Builder - never on the dashboard, never on someone else's page.
	 *
	 * @param string $hook Current admin page.
	 * @return bool
	 */
	protected function is_plugin_screen_efb( $hook ) {
		// Case-insensitive on purpose. The hook suffix keeps the menu slug's
		// capitals ("toplevel_page_Emsfb"), but WP_Screen lower-cases the id it
		// derives from that same slug ("toplevel_page_emsfb"). A strpos() for
		// 'Emsfb' matches the first and silently never the second - which is
		// the one admin_footer has to go through, since that hook is passed no
		// suffix at all.
		if ( is_string( $hook ) && '' !== $hook && false !== stripos( $hook, 'emsfb' ) ) {
			return true;
		}

		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;

		return $screen && isset( $screen->id ) && false !== stripos( (string) $screen->id, 'emsfb' );
	}

	/*
	 * ---------------------------------------------------------------------
	 * The offer
	 * ---------------------------------------------------------------------
	 */

	/**
	 * The discount figure, already formatted for reading.
	 *
	 * Every string in this feature carries %s rather than a number, so the
	 * offer can be changed here - or by a site, through the filter - without
	 * editing a single translated sentence.
	 *
	 * @return string
	 */
	public function discount_label_efb() {
		/**
		 * Filter the discount offered for a five-star review.
		 *
		 * @param int $percent Whole percentage, 1-100.
		 */
		$percent = (int) apply_filters( 'emsfb_review_discount_percent_efb', self::DISCOUNT_PERCENT );
		$percent = max( 1, min( 100, $percent ) );

		// number_format_i18n so a Persian admin reads ۱۰۰٪, not 100%.
		return sprintf(
			/* translators: %s: a whole number, e.g. 100. The result reads "100%". */
			esc_html__( '%s%%', 'easy-form-builder' ),
			number_format_i18n( $percent )
		);
	}

	/**
	 * Where a five-star review is left.
	 *
	 * @return string
	 */
	public function review_url_efb() {
		/**
		 * Filter the review destination.
		 *
		 * @param string $url Review URL.
		 */
		return (string) apply_filters(
			'emsfb_review_url_efb',
			'https://wordpress.org/support/plugin/easy-form-builder/reviews/#new-post'
		);
	}

	/**
	 * Where somebody who is not happy should go instead.
	 *
	 * @return string
	 */
	public function support_url_efb() {
		/**
		 * Filter the destination for a rating below the reward threshold.
		 *
		 * @param string $url Support URL.
		 */
		return (string) apply_filters(
			'emsfb_review_support_url_efb',
			'https://wordpress.org/support/plugin/easy-form-builder/'
		);
	}

	/*
	 * ---------------------------------------------------------------------
	 * Screen
	 * ---------------------------------------------------------------------
	 */

	/**
	 * Load the modal's CSS and JS on Easy Form Builder screens only.
	 *
	 * @param string $hook Current admin page.
	 * @return void
	 */
	public function enqueue_assets_efb( $hook ) {
		if ( ! $this->is_plugin_screen_efb( $hook ) || ! $this->should_ask_efb() ) {
			return;
		}

		// The shared dialog design system. Registered under its own handle so
		// it loads exactly once even when another dialog asked for it first.
		wp_enqueue_style(
			'efb-modal-system',
			EMSFB_PLUGIN_URL . 'includes/admin/assets/css/modal-system-efb.css',
			array(),
			EMSFB_PLUGIN_VERSION
		);

		wp_enqueue_style(
			'efb-review-request',
			EMSFB_PLUGIN_URL . 'includes/admin/assets/css/review-request-efb.css',
			array( 'efb-modal-system' ),
			EMSFB_PLUGIN_VERSION
		);

		wp_enqueue_script(
			'efb-review-request',
			EMSFB_PLUGIN_URL . 'includes/admin/assets/js/review-request-efb.js',
			array(),
			EMSFB_PLUGIN_VERSION,
			true
		);

		$data = array(
			'ajaxUrl'    => admin_url( 'admin-ajax.php' ),
			'nonce'      => wp_create_nonce( self::ACTION ),
			'rtl'        => is_rtl() ? 1 : 0,
			'reviewUrl'  => $this->review_url_efb(),
			'supportUrl' => $this->support_url_efb(),
			'threshold'  => self::REWARD_THRESHOLD,
			// In preview the script records nothing and asks the service for
			// nothing; the claim step answers itself with the sample below so
			// the last screen can be seen without spending a real coupon.
			'preview'    => $this->is_preview_efb() ? 1 : 0,
			'sample'     => array(
				'title'   => $this->strings_efb()['thanksTitle'],
				'message' => sprintf( $this->strings_efb()['couponIssued'], $this->discount_label_efb() ),
				'code'    => 'EFB-PREVIEW-CODE',
			),
			'text'       => $this->strings_efb(),
		);

		// wp_localize_script only decodes entities at the top level, so the
		// nested text array has to be decoded here or the modal would print a
		// literal "&hellip;".
		if ( function_exists( 'emsfb_decode_typographic_entities_efb' ) ) {
			$data = emsfb_decode_typographic_entities_efb( $data );
		}

		wp_localize_script( 'efb-review-request', 'efb_review', $data );
	}

	/**
	 * Print the invitation in the footer of an Easy Form Builder screen.
	 *
	 * @return void
	 */
	public function render_modal_efb() {
		if ( ! $this->is_plugin_screen_efb( '' ) || ! $this->should_ask_efb() ) {
			return;
		}

		$text  = $this->strings_efb();
		$stars = range( 1, 5 );

		/*
		 * Printing the invitation is what spends it. The snooze is written now,
		 * not when somebody answers, because most people answer by ignoring it -
		 * and an invitation that waits for an answer it will never get is one
		 * that reappears on every single page load.
		 *
		 * So: seen once, gone for a month. Logging in every morning shows it on
		 * one of those mornings, not thirty.
		 *
		 * A preview spends nothing, which is what makes it safe to look at the
		 * modal on a site that is genuinely due to be asked.
		 */
		if ( ! $this->is_preview_efb() ) {
			$this->save_state_efb(
				array(
					'shown'        => (int) $this->state_efb()['shown'] + 1,
					'status'       => 'snoozed',
					'snooze_until' => time() + ( self::SNOOZE_DAYS * DAY_IN_SECONDS ),
				)
			);
		}
		?>
		<div id="efb-review-modal" class="efb-dlg efb-review efb-tone-warn" role="dialog" aria-modal="true" aria-labelledby="efb-review-title" hidden>
			<div class="efb-dlg__backdrop" data-efb-review-close="1"></div>

			<div class="efb-dlg__shell" role="document" tabindex="-1">
				<div class="efb-dlg__head">
					<i class="bi bi-star-fill efb-dlg__head-icon" aria-hidden="true"></i>
					<h2 class="efb-dlg__title" id="efb-review-title"><?php echo esc_html( $text['headTitle'] ); ?></h2>
					<button type="button" class="efb-dlg__close" data-efb-review-close="1" aria-label="<?php echo esc_attr( $text['close'] ); ?>">
						<i class="bi bi-x-lg" aria-hidden="true"></i>
					</button>
				</div>

				<!-- Step one: the question. -->
				<div class="efb-dlg__body efb-review__step" data-efb-review-step="ask">
					<div class="efb-dlg__centered">
						<div class="efb-dlg__badge"><i class="bi bi-stars" aria-hidden="true"></i></div>
						<h3 class="efb-dlg__headline"><?php echo esc_html( $text['askTitle'] ); ?></h3>
						<p class="efb-dlg__text"><?php echo esc_html( $text['askMessage'] ); ?></p>

						<div class="efb-review__stars" role="radiogroup" aria-label="<?php echo esc_attr( $text['starsLabel'] ); ?>">
							<?php foreach ( $stars as $star ) : ?>
								<button
									type="button"
									class="efb-review__star"
									data-efb-review-rate="<?php echo esc_attr( (string) $star ); ?>"
									role="radio"
									aria-checked="false"
									aria-label="<?php echo esc_attr( sprintf( $text['starsOf'], number_format_i18n( $star ) ) ); ?>">
									<i class="bi bi-star-fill" aria-hidden="true"></i>
								</button>
							<?php endforeach; ?>
						</div>

						<p class="efb-review__hint" data-efb-review-hint><?php echo esc_html( $text['starsHint'] ); ?></p>

						<div class="efb-review__offer">
							<i class="bi bi-gift" aria-hidden="true"></i>
							<span><?php echo esc_html( sprintf( $text['offerLine'], $this->discount_label_efb() ) ); ?></span>
						</div>
					</div>
				</div>

				<!-- Step two, happy path: five stars. -->
				<div class="efb-dlg__body efb-review__step" data-efb-review-step="reward" hidden>
					<div class="efb-dlg__centered">
						<div class="efb-dlg__badge"><i class="bi bi-gift" aria-hidden="true"></i></div>
						<h3 class="efb-dlg__headline"><?php echo esc_html( sprintf( $text['rewardTitle'], $this->discount_label_efb() ) ); ?></h3>
						<p class="efb-dlg__text"><?php echo esc_html( sprintf( $text['rewardMessage'], $this->discount_label_efb() ) ); ?></p>

						<ol class="efb-review__steps">
							<li><span class="efb-review__num">1</span><?php echo esc_html( $text['rewardStep1'] ); ?></li>
							<li><span class="efb-review__num">2</span><?php echo esc_html( $text['rewardStep2'] ); ?></li>
						</ol>

						<label class="efb-review__field">
							<span><?php echo esc_html( $text['emailLabel'] ); ?></span>
							<input
								type="email"
								data-efb-review-email
								value="<?php echo esc_attr( sanitize_email( (string) get_option( 'admin_email' ) ) ); ?>"
								autocomplete="email"
								spellcheck="false">
						</label>

						<p class="efb-dlg__note">
							<i class="bi bi-info-circle" aria-hidden="true"></i>
							<span><?php echo esc_html( $text['privacy'] ); ?></span>
						</p>
					</div>
				</div>

				<!-- Step two, other path: fewer than five stars. -->
				<div class="efb-dlg__body efb-review__step" data-efb-review-step="improve" hidden>
					<div class="efb-dlg__centered">
						<div class="efb-dlg__badge"><i class="bi bi-chat-heart" aria-hidden="true"></i></div>
						<h3 class="efb-dlg__headline"><?php echo esc_html( $text['improveTitle'] ); ?></h3>
						<p class="efb-dlg__text"><?php echo esc_html( $text['improveMessage'] ); ?></p>
					</div>
				</div>

				<!-- Step three: what happened. -->
				<div class="efb-dlg__body efb-review__step" data-efb-review-step="done" hidden>
					<div class="efb-dlg__centered">
						<div class="efb-dlg__badge"><i class="bi bi-check2" aria-hidden="true"></i></div>
						<h3 class="efb-dlg__headline" data-efb-review-done-title></h3>
						<p class="efb-dlg__text" data-efb-review-done-message></p>

						<div class="efb-review__coupon" data-efb-review-coupon hidden>
							<code class="efb-review__code" data-efb-review-code></code>
							<button type="button" class="efb-btn efb-btn--ghost" data-efb-review-copy>
								<i class="bi bi-clipboard" aria-hidden="true"></i>
								<span><?php echo esc_html( $text['copy'] ); ?></span>
							</button>
						</div>
					</div>
				</div>

				<p class="efb-review__error" data-efb-review-error hidden></p>

				<div class="efb-dlg__foot">
					<button type="button" class="efb-btn efb-btn--quiet" data-efb-review-action="never">
						<?php echo esc_html( $text['never'] ); ?>
					</button>
					<button type="button" class="efb-btn efb-btn--ghost" data-efb-review-action="later">
						<i class="bi bi-clock" aria-hidden="true"></i>
						<span><?php echo esc_html( $text['later'] ); ?></span>
					</button>

					<a
						class="efb-btn efb-btn--gold"
						data-efb-review-action="review"
						href="<?php echo esc_url( $this->review_url_efb() ); ?>"
						target="_blank"
						rel="noopener noreferrer"
						hidden>
						<i class="bi bi-star-fill" aria-hidden="true"></i>
						<span><?php echo esc_html( $text['goReview'] ); ?></span>
					</a>

					<button type="button" class="efb-btn efb-btn--success" data-efb-review-action="claim" hidden>
						<i class="bi bi-gift" aria-hidden="true"></i>
						<span><?php echo esc_html( $text['claim'] ); ?></span>
					</button>

					<a
						class="efb-btn efb-btn--primary"
						data-efb-review-action="support"
						href="<?php echo esc_url( $this->support_url_efb() ); ?>"
						target="_blank"
						rel="noopener noreferrer"
						hidden>
						<i class="bi bi-life-preserver" aria-hidden="true"></i>
						<span><?php echo esc_html( $text['goSupport'] ); ?></span>
					</a>

					<button type="button" class="efb-btn efb-btn--primary" data-efb-review-action="close" hidden>
						<?php echo esc_html( $text['done'] ); ?>
					</button>
				</div>
			</div>
		</div>
		<?php
	}

	/*
	 * ---------------------------------------------------------------------
	 * The AJAX side
	 * ---------------------------------------------------------------------
	 */

	/**
	 * Record what the person chose, and issue the coupon when it is earned.
	 *
	 * @return void
	 */
	public function ajax_state_efb() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'You are not allowed to do that.', 'easy-form-builder' ) ), 403 );
		}

		if ( ! check_ajax_referer( self::ACTION, 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Security check failed. Please reload the page.', 'easy-form-builder' ) ), 403 );
		}

		$text = $this->strings_efb();
		$op   = isset( $_POST['op'] ) ? sanitize_key( wp_unslash( $_POST['op'] ) ) : '';

		switch ( $op ) {
			case 'later':
				$this->save_state_efb(
					array(
						'status'       => 'snoozed',
						'snooze_until' => time() + ( self::SNOOZE_DAYS * DAY_IN_SECONDS ),
						'shown'        => 0,
					)
				);
				wp_send_json_success( array( 'ok' => true ) );
				break;

			case 'never':
				$this->save_state_efb( array( 'status' => 'dismissed' ) );
				wp_send_json_success( array( 'ok' => true ) );
				break;

			case 'rate':
				$rating = isset( $_POST['rating'] ) ? (int) $_POST['rating'] : 0;
				$rating = max( 0, min( 5, $rating ) );

				// A rating is only remembered, never acted on here. Below the
				// threshold this is the end of the conversation: the modal
				// offers support and stops asking.
				$this->save_state_efb(
					array(
						'rating' => $rating,
						'status' => $rating < self::REWARD_THRESHOLD && $rating > 0 ? 'dismissed' : 'pending',
					)
				);
				wp_send_json_success( array( 'ok' => true ) );
				break;

			case 'claim':
				$this->claim_efb( $text );
				break;

			default:
				wp_send_json_error( array( 'message' => esc_html__( 'Unknown request.', 'easy-form-builder' ) ), 400 );
		}
	}

	/**
	 * Ask the feedback service for the discount code.
	 *
	 * Every failure path still ends with the conversation closed and the
	 * person told something true. A site that cannot reach the service, or
	 * whose service does not know the five-star reason yet, is told the code
	 * will arrive by email rather than being shown an error it cannot act on.
	 *
	 * @param array $text Strings for the current locale.
	 * @return void
	 */
	protected function claim_efb( array $text ) {
		$email = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
		if ( '' !== $email && ! is_email( $email ) ) {
			$email = '';
		}

		if ( '' === $email ) {
			wp_send_json_success(
				array(
					'ok'      => false,
					'message' => $text['emailRequired'],
				)
			);
		}

		$state = $this->state_efb();

		// Already claimed: hand back what was issued rather than asking the
		// service for a second code.
		if ( 'rated' === $state['status'] && 'none' !== $state['coupon_state'] ) {
			wp_send_json_success( $this->claim_response_efb( $state['coupon_state'], (string) $state['coupon_code'], $text ) );
		}

		$result = array( 'ok' => false );

		if ( class_exists( '\Emsfb\Deactivation_Feedback' ) ) {
			$feedback = new Deactivation_Feedback();
			$result   = $feedback->send_report_efb(
				array(
					'reason'     => self::REPORT_REASON,
					'details'    => sprintf( 'Five-star review pledged from wp-admin. Days in use: %d.', $this->days_in_use_efb() ),
					'email'      => $email,
					'contact_ok' => 1,
					'hp'         => '',
					'env'        => $feedback->collect_env_efb(),
				)
			);
		}

		$coupon       = isset( $result['coupon'] ) && is_array( $result['coupon'] ) ? $result['coupon'] : array();
		$coupon_state = isset( $coupon['state'] ) ? (string) $coupon['state'] : 'none';
		$code         = isset( $coupon['code'] ) ? (string) $coupon['code'] : '';

		// The report never reaching the service is not the person's problem:
		// the pledge is recorded locally and the code is promised by email.
		if ( empty( $result['ok'] ) || ( 'issued' !== $coupon_state && 'pending' !== $coupon_state ) ) {
			$coupon_state = 'pending';
			$code         = '';
		}

		$this->save_state_efb(
			array(
				'status'       => 'rated',
				'rating'       => self::REWARD_THRESHOLD,
				'rated_at'     => time(),
				'coupon_state' => $coupon_state,
				'coupon_code'  => 'issued' === $coupon_state ? $code : '',
			)
		);

		wp_send_json_success( $this->claim_response_efb( $coupon_state, $code, $text ) );
	}

	/**
	 * Shape one claim answer for the modal.
	 *
	 * @param string $coupon_state 'issued' or 'pending'.
	 * @param string $code         Discount code, when issued.
	 * @param array  $text         Strings for the current locale.
	 * @return array
	 */
	protected function claim_response_efb( $coupon_state, $code, array $text ) {
		$discount = $this->discount_label_efb();

		return array(
			'ok'      => true,
			'title'   => $text['thanksTitle'],
			'message' => 'issued' === $coupon_state
				? sprintf( $text['couponIssued'], $discount )
				: sprintf( $text['couponPending'], $discount ),
			'coupon'  => array(
				'state' => $coupon_state,
				'code'  => 'issued' === $coupon_state ? $code : '',
			),
		);
	}

	/*
	 * ---------------------------------------------------------------------
	 * Wording
	 * ---------------------------------------------------------------------
	 */

	/**
	 * The modal's wording in the admin's own language.
	 *
	 * Same three sources as the deactivation survey, most specific first:
	 * phrases pushed by the White Studio settings payload (text->review*),
	 * the bundled translations below, then the English source strings.
	 *
	 * Strings that mention the discount carry %s, never a number - see the
	 * fourth rule at the top of this file.
	 *
	 * @return array
	 */
	public function strings_efb() {
		$defaults = array(
			'headTitle'     => esc_html__( 'Enjoying Easy Form Builder?', 'easy-form-builder' ),
			'askTitle'      => esc_html__( 'How are we doing so far?', 'easy-form-builder' ),
			'askMessage'    => esc_html__( 'You have been building forms with us for a couple of weeks. If it has been useful, a rating helps other people find the plugin - and it takes less than a minute.', 'easy-form-builder' ),
			'starsLabel'    => esc_html__( 'Your rating', 'easy-form-builder' ),
			/* translators: %s: a number from 1 to 5. */
			'starsOf'       => esc_html__( '%s out of 5 stars', 'easy-form-builder' ),
			'starsHint'     => esc_html__( 'Pick a rating to continue', 'easy-form-builder' ),
			/* translators: %s: the discount, e.g. "100%". */
			'offerLine'     => esc_html__( 'Rate us 5 stars and get %s off your first year of Pro', 'easy-form-builder' ),
			/* translators: %s: the discount, e.g. "100%". */
			'rewardTitle'   => esc_html__( 'Thank you! Here is %s off your first year', 'easy-form-builder' ),
			/* translators: %s: the discount, e.g. "100%". */
			'rewardMessage' => esc_html__( 'Post your 5-star review on WordPress.org, come back, and we will send your %s discount code for the first year of the Pro version.', 'easy-form-builder' ),
			'rewardStep1'   => esc_html__( 'Leave your review on WordPress.org - it opens in a new tab.', 'easy-form-builder' ),
			'rewardStep2'   => esc_html__( 'Come back here and press the button to get your code.', 'easy-form-builder' ),
			'emailLabel'    => esc_html__( 'Email address for the discount code', 'easy-form-builder' ),
			'emailRequired' => esc_html__( 'Please enter an email address so we can send the code.', 'easy-form-builder' ),
			'improveTitle'  => esc_html__( 'Tell us what would make it a 5', 'easy-form-builder' ),
			'improveMessage' => esc_html__( 'Thank you for being honest. We would rather fix what is bothering you than collect a rating - tell us what is missing and we will look at it.', 'easy-form-builder' ),
			'privacy'       => esc_html__( 'We only send your site address, your email, and the plugin, WordPress and PHP versions.', 'easy-form-builder' ),
			'thanksTitle'   => esc_html__( 'Thank you', 'easy-form-builder' ),
			/* translators: %s: the discount, e.g. "100%". */
			'couponIssued'  => esc_html__( 'Here is your %s discount code for the first year of Pro:', 'easy-form-builder' ),
			/* translators: %s: the discount, e.g. "100%". */
			'couponPending' => esc_html__( 'Your %s discount code is on its way - we will email it to you shortly.', 'easy-form-builder' ),
			'claim'         => esc_html__( 'I left a review - send my code', 'easy-form-builder' ),
			'goReview'      => esc_html__( 'Rate on WordPress.org', 'easy-form-builder' ),
			'goSupport'     => esc_html__( 'Tell us what is wrong', 'easy-form-builder' ),
			'later'         => esc_html__( 'Maybe later', 'easy-form-builder' ),
			'never'         => esc_html__( 'Do not ask again', 'easy-form-builder' ),
			'done'          => esc_html__( 'Close', 'easy-form-builder' ),
			'close'         => esc_html__( 'Close', 'easy-form-builder' ),
			'copy'          => esc_html__( 'Copy', 'easy-form-builder' ),
			'copied'        => esc_html__( 'Copied', 'easy-form-builder' ),
			'sending'       => esc_html__( 'Sending&hellip;', 'easy-form-builder' ),
			'failed'        => esc_html__( 'Something went wrong. Please try again in a moment.', 'easy-form-builder' ),
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
				$remote_key = 'review' . ucfirst( $key );
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
	 * Bundled for the same reason the deactivation survey bundles them: this
	 * plugin's phrases normally arrive from the server, and somebody who never
	 * fetched them should still be able to read what is being offered.
	 *
	 * @return array
	 */
	protected function bundled_translations_efb() {
		return array(
			'fa' => array(
				'headTitle'      => 'از فرم‌ساز راضی هستید؟',
				'askTitle'       => 'تا اینجا چطور بوده‌ایم؟',
				'askMessage'     => 'دو هفته‌ای می‌شود که با ما فرم می‌سازید. اگر به کارتان آمده، یک امتیاز کمک می‌کند دیگران هم این افزونه را پیدا کنند؛ کمتر از یک دقیقه وقت می‌گیرد.',
				'starsLabel'     => 'امتیاز شما',
				'starsOf'        => '%s ستاره از ۵',
				'starsHint'      => 'برای ادامه، امتیازتان را انتخاب کنید',
				'offerLine'      => 'به ما ۵ ستاره بدهید و برای سال اول نسخه حرفه‌ای %s تخفیف بگیرید',
				'rewardTitle'    => 'ممنونیم! %s تخفیف سال اول برای شما',
				'rewardMessage'  => 'نظر ۵ ستاره‌تان را در WordPress.org ثبت کنید، برگردید، و ما کد تخفیف %s سال اول نسخه حرفه‌ای را برایتان می‌فرستیم.',
				'rewardStep1'    => 'نظرتان را در WordPress.org ثبت کنید؛ در زبانه‌ی تازه باز می‌شود.',
				'rewardStep2'    => 'به همین‌جا برگردید و دکمه را بزنید تا کدتان را بگیرید.',
				'emailLabel'     => 'ایمیل برای دریافت کد تخفیف',
				'emailRequired'  => 'لطفاً ایمیلتان را بنویسید تا کد را برایتان بفرستیم.',
				'improveTitle'   => 'بگویید چه چیزی آن را ۵ ستاره می‌کند',
				'improveMessage' => 'ممنون که رک بودید. ما ترجیح می‌دهیم چیزی را که آزارتان می‌دهد درست کنیم تا اینکه امتیاز جمع کنیم؛ بگویید چه کم دارد تا بررسی‌اش کنیم.',
				'privacy'        => 'فقط نشانی سایت، ایمیل شما، و نسخه‌های افزونه، وردپرس و PHP فرستاده می‌شود.',
				'thanksTitle'    => 'ممنونیم',
				'couponIssued'   => 'این هم کد تخفیف %s سال اول نسخه حرفه‌ای:',
				'couponPending'  => 'کد تخفیف %s شما در راه است؛ به‌زودی برایتان ایمیل می‌کنیم.',
				'claim'          => 'نظرم را ثبت کردم؛ کدم را بفرستید',
				'goReview'       => 'ثبت امتیاز در WordPress.org',
				'goSupport'      => 'بگویید چه اشکالی هست',
				'later'          => 'بعداً',
				'never'          => 'دیگر نپرس',
				'done'           => 'بستن',
				'close'          => 'بستن',
				'copy'           => 'کپی',
				'copied'         => 'کپی شد',
				'sending'        => 'در حال ارسال…',
				'failed'         => 'مشکلی پیش آمد. کمی بعد دوباره تلاش کنید.',
			),
			'ar' => array(
				'headTitle'      => 'هل يعجبك Easy Form Builder؟',
				'askTitle'       => 'كيف كان أداؤنا حتى الآن؟',
				'askMessage'     => 'مضى أسبوعان وأنت تبني النماذج معنا. إذا كان مفيدًا، فإن تقييمك يساعد الآخرين على اكتشاف الإضافة، ولا يستغرق دقيقة واحدة.',
				'starsLabel'     => 'تقييمك',
				'starsOf'        => '%s من ٥ نجوم',
				'starsHint'      => 'اختر تقييمًا للمتابعة',
				'offerLine'      => 'قيّمنا بـ ٥ نجوم واحصل على خصم %s على سنتك الأولى من النسخة الاحترافية',
				'rewardTitle'    => 'شكرًا لك! خصم %s على سنتك الأولى',
				'rewardMessage'  => 'انشر تقييمك بخمس نجوم على WordPress.org، ثم عُد وسنرسل لك رمز خصم %s للسنة الأولى من النسخة الاحترافية.',
				'rewardStep1'    => 'اترك تقييمك على WordPress.org - يُفتح في تبويب جديد.',
				'rewardStep2'    => 'عُد إلى هنا واضغط الزر للحصول على الرمز.',
				'emailLabel'     => 'البريد الإلكتروني لاستلام رمز الخصم',
				'emailRequired'  => 'يرجى إدخال بريد إلكتروني حتى نتمكن من إرسال الرمز.',
				'improveTitle'   => 'أخبرنا بما يجعله ٥ نجوم',
				'improveMessage' => 'شكرًا لصراحتك. نُفضّل إصلاح ما يزعجك على جمع التقييمات - أخبرنا بما ينقص وسننظر فيه.',
				'privacy'        => 'نرسل فقط عنوان موقعك وبريدك الإلكتروني وإصدارات الإضافة ووردبريس وPHP.',
				'thanksTitle'    => 'شكرًا لك',
				'couponIssued'   => 'هذا رمز خصم %s للسنة الأولى من النسخة الاحترافية:',
				'couponPending'  => 'رمز خصم %s في طريقه إليك - سنرسله بالبريد قريبًا.',
				'claim'          => 'تركتُ تقييمًا - أرسل الرمز',
				'goReview'       => 'قيّمنا على WordPress.org',
				'goSupport'      => 'أخبرنا بالمشكلة',
				'later'          => 'ربما لاحقًا',
				'never'          => 'لا تسألني مرة أخرى',
				'done'           => 'إغلاق',
				'close'          => 'إغلاق',
				'copy'           => 'نسخ',
				'copied'         => 'تم النسخ',
				'sending'        => 'جارٍ الإرسال…',
				'failed'         => 'حدث خطأ ما. يرجى المحاولة بعد قليل.',
			),
			'de' => array(
				'headTitle'      => 'Gefällt Ihnen Easy Form Builder?',
				'askTitle'       => 'Wie machen wir uns bisher?',
				'askMessage'     => 'Sie bauen seit gut zwei Wochen Formulare mit uns. Wenn es Ihnen geholfen hat, hilft eine Bewertung anderen, das Plugin zu finden - und dauert keine Minute.',
				'starsLabel'     => 'Ihre Bewertung',
				'starsOf'        => '%s von 5 Sternen',
				'starsHint'      => 'Wählen Sie eine Bewertung, um fortzufahren',
				'offerLine'      => 'Bewerten Sie uns mit 5 Sternen und erhalten Sie %s Rabatt auf Ihr erstes Pro-Jahr',
				'rewardTitle'    => 'Vielen Dank! Hier sind %s Rabatt auf Ihr erstes Jahr',
				'rewardMessage'  => 'Veröffentlichen Sie Ihre 5-Sterne-Bewertung auf WordPress.org, kommen Sie zurück, und wir senden Ihnen Ihren %s-Rabattcode für das erste Pro-Jahr.',
				'rewardStep1'    => 'Hinterlassen Sie Ihre Bewertung auf WordPress.org - sie öffnet sich in einem neuen Tab.',
				'rewardStep2'    => 'Kommen Sie hierher zurück und klicken Sie auf die Schaltfläche für Ihren Code.',
				'emailLabel'     => 'E-Mail-Adresse für den Rabattcode',
				'emailRequired'  => 'Bitte geben Sie eine E-Mail-Adresse an, damit wir den Code senden können.',
				'improveTitle'   => 'Sagen Sie uns, was daraus eine 5 macht',
				'improveMessage' => 'Danke für Ihre Ehrlichkeit. Uns ist lieber, wir beheben, was Sie stört, als eine Bewertung einzusammeln - sagen Sie uns, was fehlt.',
				'privacy'        => 'Wir senden nur Ihre Website-Adresse, Ihre E-Mail und die Plugin-, WordPress- und PHP-Versionen.',
				'thanksTitle'    => 'Vielen Dank',
				'couponIssued'   => 'Hier ist Ihr %s-Rabattcode für das erste Pro-Jahr:',
				'couponPending'  => 'Ihr %s-Rabattcode ist unterwegs - wir senden ihn Ihnen in Kürze per E-Mail.',
				'claim'          => 'Ich habe bewertet - Code senden',
				'goReview'       => 'Auf WordPress.org bewerten',
				'goSupport'      => 'Sagen Sie uns, was nicht stimmt',
				'later'          => 'Vielleicht später',
				'never'          => 'Nicht mehr fragen',
				'done'           => 'Schließen',
				'close'          => 'Schließen',
				'copy'           => 'Kopieren',
				'copied'         => 'Kopiert',
				'sending'        => 'Wird gesendet…',
				'failed'         => 'Etwas ist schiefgelaufen. Bitte versuchen Sie es gleich noch einmal.',
			),
		);
	}
}
