<?php
/**
 * The rating invitation.
 *
 * Two weeks after somebody starts using Easy Form Builder, a Free or Free Plus
 * site is asked - once a month at most, on an Easy Form Builder screen and
 * nowhere else - to rate the plugin.
 *
 * There are two ways out of the question, and they are deliberately different
 * conversations:
 *
 *   4-5 stars  ask -> praise -> claim -> checking -> result
 *              The person is sent to WordPress.org, comes back with their
 *              username, and White Studio verifies the review and emails a
 *              discount code for the first year of Pro.
 *
 *   1-3 stars  ask -> feedback -> sent
 *              No review is ever requested. The complaint goes straight to the
 *              team, privately, through the same reports pipeline the
 *              deactivation survey uses.
 *
 * Five rules this file keeps:
 *
 *   1. Pro sites are never asked. They already paid.
 *   2. Nobody is asked twice by accident. Printing the invitation spends it for
 *      a month; "Do not ask again" is permanent.
 *   3. An unhappy rating never reaches WordPress.org. Pushing somebody who just
 *      told you the plugin is hard to use toward a public review form is how
 *      you earn the two-star review this feature exists to avoid.
 *   4. The discount figure is never written into a sentence. Every string
 *      carries %s and the number arrives from discount_label_efb().
 *   5. The plugin never decides whether a review exists. It asks White Studio,
 *      which checks the real WordPress.org review list. A site that could
 *      grant itself a coupon by editing an option would be a coupon printer.
 *
 * @package Easy_Form_Builder
 */

namespace Emsfb;

defined( 'ABSPATH' ) || exit;

/**
 * Asks long-time free users for a rating, and rewards a real review.
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
	 * and never twice in a week of daily logins.
	 */
	const SNOOZE_DAYS = 30;

	/** The reason key the feedback service files a low rating under. */
	const REPORT_REASON = 'rating_feedback';

	/** Default discount offered for a verified review, as a percentage. */
	const DISCOUNT_PERCENT = 64;

	/**
	 * Ratings at or above this take the WordPress.org route.
	 *
	 * Four, not five. Somebody who picked four stars is happy, and the review
	 * page is where that belongs; the reward itself still requires the review
	 * White Studio finds to be five stars, which is checked there, not here.
	 */
	const REWARD_THRESHOLD = 4;

	/** Longest comment accepted from the feedback step. */
	const MAX_COMMENT = 4000;

	/** Query argument that forces the invitation open for a preview. */
	const PREVIEW_ARG = 'efb_review_preview';

	/** REST base on the White Studio payment service, which owns the coupon. */
	const REWARD_PATH = '/wp-json/payefb/v1/review-reward';

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
				'status'       => 'pending',
				'snooze_until' => 0,
				'shown'        => 0,
				'rating'       => 0,
				'rated_at'     => 0,
				'username'     => '',
				'outcome'      => '',
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

	/**
	 * Whether the invitation should be shown to the current user right now.
	 *
	 * @return bool
	 */
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
		 * Filter whether the rating invitation may be shown.
		 *
		 * @param bool           $should  Whether to ask.
		 * @param Review_Request $request The instance making the decision.
		 */
		return (bool) apply_filters( 'emsfb_review_should_ask_efb', true, $this );
	}

	/**
	 * Whether the current admin screen belongs to this plugin.
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
	 * The only place the number lives. Every string carries %s.
	 *
	 * @return string
	 */
	public function discount_label_efb() {
		/**
		 * Filter the discount offered for a verified five-star review.
		 *
		 * @param int $percent Whole percentage, 1-100.
		 */
		$percent = (int) apply_filters( 'emsfb_review_discount_percent_efb', self::DISCOUNT_PERCENT );
		$percent = max( 1, min( 100, $percent ) );

		// number_format_i18n so a Persian admin reads ۶۴٪, not 64%.
		return sprintf(
			/* translators: %s: a whole number, e.g. 64. The result reads "64%". */
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

	/**
	 * The topics offered on the feedback step.
	 *
	 * Keys travel to the service, so they are stable identifiers rather than
	 * translated labels.
	 *
	 * @return array
	 */
	public function topics_efb() {
		$text = $this->strings_efb();

		return array(
			'building'    => $text['topicBuilding'],
			'email'       => $text['topicEmail'],
			'styling'     => $text['topicStyling'],
			'speed'       => $text['topicSpeed'],
			'payments'    => $text['topicPayments'],
			'translation' => $text['topicTranslation'],
		);
	}

	/**
	 * The outcomes the service may answer a claim with, and how each is drawn.
	 *
	 * `tone` picks the colour, `actions` the footer. The whole point of keeping
	 * this in one table is that a new outcome cannot be added without deciding
	 * what it looks like and what the person can do next.
	 *
	 * @return array
	 */
	public function outcomes_efb() {
		return array(
			'granted'  => array( 'icon' => 'bi-envelope-check',       'tone' => 'good',    'detailIcon' => 'bi-clock-history',     'actions' => array( 'done' ) ),
			'pending'  => array( 'icon' => 'bi-hourglass-split',      'tone' => 'warn',    'detailIcon' => 'bi-info-circle',       'actions' => array( 'done' ) ),
			'notFound' => array( 'icon' => 'bi-search',               'tone' => 'warn',    'detailIcon' => 'bi-lightbulb',         'actions' => array( 'edit', 'retry' ) ),
			'lowStars' => array( 'icon' => 'bi-star-half',            'tone' => 'warn',    'detailIcon' => 'bi-chat-square-text',  'actions' => array( 'feedback', 'retry' ) ),
			'used'     => array( 'icon' => 'bi-ticket-perforated',    'tone' => 'neutral', 'detailIcon' => 'bi-person-check',      'actions' => array( 'support', 'done' ) ),
			'badEmail' => array( 'icon' => 'bi-envelope-exclamation', 'tone' => 'bad',     'detailIcon' => 'bi-lightbulb',         'actions' => array( 'edit', 'retry' ) ),
			'server'   => array( 'icon' => 'bi-wifi-off',             'tone' => 'bad',     'detailIcon' => 'bi-life-preserver',    'actions' => array( 'later', 'retry' ) ),
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

		$text = $this->strings_efb();

		$data = array(
			'ajaxUrl'    => admin_url( 'admin-ajax.php' ),
			'nonce'      => wp_create_nonce( self::ACTION ),
			'rtl'        => is_rtl() ? 1 : 0,
			'reviewUrl'  => $this->review_url_efb(),
			'supportUrl' => $this->support_url_efb(),
			'threshold'  => self::REWARD_THRESHOLD,
			// In preview the script records nothing and asks the service for
			// nothing; every outcome can be stepped through locally instead.
			'preview'    => $this->is_preview_efb() ? 1 : 0,
			'outcomes'   => $this->outcome_view_efb(),
			'text'       => $text,
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
	 * Every outcome, already resolved into what the modal has to draw.
	 *
	 * Sent to the browser whole so the result screen can be rendered without a
	 * second round trip, and so a preview can step through all seven.
	 *
	 * @return array
	 */
	public function outcome_view_efb() {
		$text     = $this->strings_efb();
		$discount = $this->discount_label_efb();
		$out      = array();

		foreach ( $this->outcomes_efb() as $key => $meta ) {
			$out[ $key ] = array(
				'icon'       => $meta['icon'],
				'tone'       => $meta['tone'],
				'detailIcon' => $meta['detailIcon'],
				'actions'    => $meta['actions'],
				'title'      => $text[ 'oc' . ucfirst( $key ) . 'Title' ],
				'lead'       => sprintf( $text[ 'oc' . ucfirst( $key ) . 'Lead' ], $discount ),
				'detail'     => $text[ 'oc' . ucfirst( $key ) . 'Detail' ],
			);
		}

		return $out;
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

		$text     = $this->strings_efb();
		$discount = $this->discount_label_efb();
		$topics   = $this->topics_efb();
		$admin    = sanitize_email( (string) get_option( 'admin_email' ) );

		/*
		 * Printing the invitation is what spends it. The snooze is written now,
		 * not when somebody answers, because most people answer by ignoring it -
		 * and an invitation that waits for an answer it will never get is one
		 * that reappears on every single page load.
		 *
		 * A preview spends nothing.
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
		<div id="efb-review-modal" class="efb-dlg efb-review" role="dialog" aria-modal="true" aria-labelledby="efb-review-title" hidden>
			<div class="efb-dlg__backdrop" data-efb-review-close="1"></div>

			<div class="efb-dlg__shell" role="document" tabindex="-1">

				<div class="efb-review__ribbon">
					<span class="efb-review__shine" aria-hidden="true"></span>
					<i class="bi bi-gift" aria-hidden="true"></i>
					<span><?php echo esc_html( $text['ribbon'] ); ?></span>
					<span class="efb-review__ribbon-pct"><?php echo esc_html( sprintf( $text['ribbonPct'], $discount ) ); ?></span>
				</div>

				<div class="efb-dlg__head">
					<div class="efb-dlg__title" id="efb-review-title"><span class="screen-reader-text"><?php echo esc_html( $text['askTitle'] ); ?></span></div>
					<button type="button" class="efb-dlg__close" data-efb-review-close="1" aria-label="<?php echo esc_attr( $text['close'] ); ?>">
						<i class="bi bi-x-lg" aria-hidden="true"></i>
					</button>
				</div>

				<!-- 1. The question. -->
				<div class="efb-dlg__body efb-review__step" data-efb-review-step="ask">
					<div class="efb-review__center">
						<div class="efb-review__mark"><i class="bi bi-hand-thumbs-up" aria-hidden="true"></i></div>
						<h2 class="efb-review__title"><?php echo esc_html( $text['askTitle'] ); ?></h2>

						<div class="efb-review__stars" role="radiogroup" aria-label="<?php echo esc_attr( $text['starsLabel'] ); ?>">
							<?php for ( $star = 1; $star <= 5; $star++ ) : ?>
								<button
									type="button"
									class="efb-review__star"
									data-efb-review-rate="<?php echo esc_attr( (string) $star ); ?>"
									role="radio"
									aria-checked="false"
									title="<?php echo esc_attr( $text[ 'r' . $star ] ); ?>"
									aria-label="<?php echo esc_attr( $text[ 'r' . $star ] ); ?>">
									<i class="bi bi-star" aria-hidden="true"></i>
								</button>
							<?php endfor; ?>
						</div>

						<p class="efb-review__rating-label" data-efb-review-hint><?php echo esc_html( $text['r0'] ); ?></p>
					</div>
				</div>

				<!-- 2a. Happy: the offer and the two steps to claim it. -->
				<div class="efb-dlg__body efb-review__step" data-efb-review-step="praise" hidden>
					<div class="efb-review__col">
						<div class="efb-review__row">
							<span class="efb-review__won" data-efb-review-won aria-hidden="true"></span>
							<span class="efb-review__praise-title"><?php echo esc_html( $text['praiseTitle'] ); ?></span>
						</div>
						<p class="efb-review__lead"><?php echo esc_html( sprintf( $text['praiseLead'], $discount ) ); ?></p>

						<div class="efb-review__coupon">
							<span class="efb-review__gift"><i class="bi bi-gift-fill" aria-hidden="true"></i></span>
							<span class="efb-review__coupon-main">
								<span class="efb-review__pct"><?php echo esc_html( sprintf( $text['ribbonPct'], $discount ) ); ?></span>
								<span class="efb-review__coupon-note"><?php echo esc_html( $text['couponNote'] ); ?></span>
							</span>
						</div>

						<ol class="efb-review__flow">
							<li>
								<span class="efb-review__num">1</span>
								<span class="efb-review__flow-body">
									<span class="efb-review__flow-title"><?php echo esc_html( $text['flow1'] ); ?></span>
									<a
										class="efb-review__cta"
										data-efb-review-action="review"
										href="<?php echo esc_url( $this->review_url_efb() ); ?>"
										target="_blank"
										rel="noopener noreferrer">
										<i class="bi bi-star-fill" aria-hidden="true"></i><?php echo esc_html( $text['writeReview'] ); ?>
									</a>
									<span class="efb-review__cta-note">
										<i class="bi bi-box-arrow-up-right" aria-hidden="true"></i><?php echo esc_html( $text['opensWp'] ); ?>
									</span>
								</span>
							</li>
							<li>
								<span class="efb-review__num efb-review__num--soft">2</span>
								<span class="efb-review__flow-body">
									<span class="efb-review__flow-title"><?php echo esc_html( $text['flow2'] ); ?></span>
									<button type="button" class="efb-review__secondary" data-efb-review-action="toClaim">
										<i class="bi bi-gift" aria-hidden="true"></i><?php echo esc_html( $text['aPosted'] ); ?>
									</button>
								</span>
							</li>
						</ol>
					</div>
				</div>

				<!-- 2b. Where to send the code. -->
				<div class="efb-dlg__body efb-review__step" data-efb-review-step="claim" hidden>
					<div class="efb-review__col">
						<div class="efb-review__row">
							<span class="efb-review__won efb-review__won--small" data-efb-review-won aria-hidden="true"></span>
							<span class="efb-review__badge-ok"><i class="bi bi-patch-check-fill" aria-hidden="true"></i><?php echo esc_html( $text['claimBadge'] ); ?></span>
						</div>
						<h2 class="efb-review__title"><?php echo esc_html( $text['claimTitle'] ); ?></h2>
						<p class="efb-review__lead"><?php echo esc_html( $text['claimLead'] ); ?></p>

						<div class="efb-review__fields">
							<label class="efb-review__field">
								<span class="efb-review__label"><?php echo esc_html( $text['fieldUser'] ); ?></span>
								<span class="efb-review__input-wrap">
									<i class="bi bi-at" aria-hidden="true"></i>
									<input type="text" data-efb-review-username placeholder="<?php echo esc_attr( $text['userPh'] ); ?>" spellcheck="false" autocomplete="username">
								</span>
								<span class="efb-review__hint"><?php echo esc_html( $text['userHint'] ); ?></span>
							</label>
							<label class="efb-review__field">
								<span class="efb-review__label"><?php echo esc_html( $text['fieldEmail'] ); ?></span>
								<span class="efb-review__input-wrap">
									<i class="bi bi-envelope" aria-hidden="true"></i>
									<input type="email" data-efb-review-email value="<?php echo esc_attr( $admin ); ?>" placeholder="<?php echo esc_attr( $text['emailPh'] ); ?>" spellcheck="false" autocomplete="email">
								</span>
								<span class="efb-review__hint"><?php echo esc_html( $text['emailHint'] ); ?></span>
							</label>
						</div>

						<p class="efb-review__privacy">
							<i class="bi bi-shield-lock" aria-hidden="true"></i>
							<span><?php echo esc_html( $text['privacyNote'] ); ?></span>
						</p>
					</div>
				</div>

				<!-- 2c. Asking White Studio to check. -->
				<div class="efb-dlg__body efb-review__step" data-efb-review-step="checking" hidden>
					<div class="efb-review__center">
						<img
							class="efb-review__checking-mark"
							src="<?php echo esc_url( EMSFB_PLUGIN_URL . 'includes/admin/assets/image/efb-256.gif' ); ?>"
							alt="Easy Form Builder">
						<h2 class="efb-review__title"><?php echo esc_html( $text['checkTitle'] ); ?></h2>
						<p class="efb-review__lead"><?php echo esc_html( $text['checkLead'] ); ?></p>

						<ol class="efb-review__checks" data-efb-review-checks>
							<li data-check="find"><span class="efb-review__check-dot"><i class="bi bi-arrow-repeat" aria-hidden="true"></i></span><?php echo esc_html( $text['csFind'] ); ?></li>
							<li data-check="stars" class="is-waiting"><span class="efb-review__check-dot"><i class="bi bi-dot" aria-hidden="true"></i></span><?php echo esc_html( $text['csStars'] ); ?></li>
							<li data-check="send" class="is-waiting"><span class="efb-review__check-dot"><i class="bi bi-dot" aria-hidden="true"></i></span><?php echo esc_html( $text['csSend'] ); ?></li>
						</ol>
					</div>
				</div>

				<!-- 2d. What the service said. -->
				<div class="efb-dlg__body efb-review__step" data-efb-review-step="result" hidden>
					<div class="efb-review__center">
						<div class="efb-review__result-mark"><i class="bi" data-efb-review-result-icon aria-hidden="true"></i></div>
						<h2 class="efb-review__title" data-efb-review-result-title></h2>
						<p class="efb-review__lead" data-efb-review-result-lead></p>
						<p class="efb-review__detail" data-efb-review-result-detail hidden>
							<i class="bi" data-efb-review-detail-icon aria-hidden="true"></i>
							<span data-efb-review-detail-text></span>
						</p>
					</div>
				</div>

				<!-- 3a. Unhappy: tell us privately. -->
				<div class="efb-dlg__body efb-review__step" data-efb-review-step="feedback" hidden>
					<div class="efb-review__col">
						<div class="efb-review__row">
							<span class="efb-review__won efb-review__won--small" data-efb-review-won aria-hidden="true"></span>
							<button type="button" class="efb-review__change" data-efb-review-action="back">
								<i class="bi bi-arrow-counterclockwise" aria-hidden="true"></i><?php echo esc_html( $text['changeRating'] ); ?>
							</button>
						</div>
						<h2 class="efb-review__title"><?php echo esc_html( $text['fbTitle'] ); ?></h2>
						<p class="efb-review__lead"><?php echo esc_html( $text['fbLead'] ); ?></p>

						<div class="efb-review__topics" role="group" aria-label="<?php echo esc_attr( $text['fbTitle'] ); ?>">
							<?php foreach ( $topics as $key => $label ) : ?>
								<button type="button" class="efb-review__topic" data-efb-review-topic="<?php echo esc_attr( $key ); ?>" aria-pressed="false">
									<?php echo esc_html( $label ); ?>
								</button>
							<?php endforeach; ?>
						</div>

						<textarea
							class="efb-review__comment"
							data-efb-review-comment
							rows="4"
							maxlength="<?php echo esc_attr( (string) self::MAX_COMMENT ); ?>"
							placeholder="<?php echo esc_attr( $text['fbPlaceholder'] ); ?>"
							aria-label="<?php echo esc_attr( $text['fbTitle'] ); ?>"></textarea>

						<label class="efb-review__contact">
							<input type="checkbox" data-efb-review-contact checked>
							<span><?php echo esc_html( $text['fbContact'] ); ?></span>
						</label>
					</div>
				</div>

				<!-- 3b. Thanks for the complaint. -->
				<div class="efb-dlg__body efb-review__step" data-efb-review-step="sent" hidden>
					<div class="efb-review__center">
						<div class="efb-review__sent-mark"><i class="bi bi-envelope-check" aria-hidden="true"></i></div>
						<h2 class="efb-review__title"><?php echo esc_html( $text['sentTitle'] ); ?></h2>
						<p class="efb-review__lead"><?php echo esc_html( $text['sentLead'] ); ?></p>
						<p class="efb-review__coupon-slim">
							<i class="bi bi-heart" aria-hidden="true"></i>
							<span><?php echo esc_html( $text['sentCoupon'] ); ?></span>
						</p>
					</div>
				</div>

				<p class="efb-review__error" data-efb-review-error hidden></p>

				<div class="efb-dlg__foot">
					<button type="button" class="efb-btn efb-btn--quiet" data-efb-review-action="never">
						<i class="bi bi-bell-slash" aria-hidden="true"></i><span><?php echo esc_html( $text['aNever'] ); ?></span>
					</button>
					<button type="button" class="efb-btn efb-btn--ghost" data-efb-review-action="later">
						<i class="bi bi-clock" aria-hidden="true"></i><span><?php echo esc_html( $text['aLater'] ); ?></span>
					</button>
					<button type="button" class="efb-btn efb-btn--primary" data-efb-review-action="getCode" hidden>
						<i class="bi bi-send" aria-hidden="true"></i><span><?php echo esc_html( $text['aGetCode'] ); ?></span>
					</button>
					<button type="button" class="efb-btn efb-btn--primary" data-efb-review-action="send" hidden>
						<i class="bi bi-send" aria-hidden="true"></i><span><?php echo esc_html( $text['aSend'] ); ?></span>
					</button>
					<button type="button" class="efb-btn efb-btn--ghost" data-efb-review-action="edit" hidden>
						<i class="bi bi-pencil" aria-hidden="true"></i><span><?php echo esc_html( $text['aEditInfo'] ); ?></span>
					</button>
					<button type="button" class="efb-btn efb-btn--ghost" data-efb-review-action="feedback" hidden>
						<i class="bi bi-chat-square-text" aria-hidden="true"></i><span><?php echo esc_html( $text['aWriteFeedback'] ); ?></span>
					</button>
					<a
						class="efb-btn efb-btn--ghost"
						data-efb-review-action="support"
						href="<?php echo esc_url( $this->support_url_efb() ); ?>"
						target="_blank"
						rel="noopener noreferrer"
						hidden>
						<i class="bi bi-life-preserver" aria-hidden="true"></i><span><?php echo esc_html( $text['aSupport'] ); ?></span>
					</a>
					<button type="button" class="efb-btn efb-btn--primary" data-efb-review-action="retry" hidden>
						<i class="bi bi-arrow-clockwise" aria-hidden="true"></i><span><?php echo esc_html( $text['aRetry'] ); ?></span>
					</button>
					<button type="button" class="efb-btn efb-btn--primary" data-efb-review-action="done" hidden>
						<i class="bi bi-check2" aria-hidden="true"></i><span><?php echo esc_html( $text['aDone'] ); ?></span>
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
	 * Record what the person chose, and forward a claim to White Studio.
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

		$op = isset( $_POST['op'] ) ? sanitize_key( wp_unslash( $_POST['op'] ) ) : '';

		switch ( $op ) {
			case 'later':
				$this->save_state_efb(
					array(
						'status'       => 'snoozed',
						'snooze_until' => time() + ( self::SNOOZE_DAYS * DAY_IN_SECONDS ),
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
				$this->save_state_efb( array( 'rating' => max( 0, min( 5, $rating ) ) ) );
				wp_send_json_success( array( 'ok' => true ) );
				break;

			case 'claim':
				$this->claim_efb();
				break;

			case 'feedback':
				$this->feedback_efb();
				break;

			default:
				wp_send_json_error( array( 'message' => esc_html__( 'Unknown request.', 'easy-form-builder' ) ), 400 );
		}
	}

	/**
	 * Ask White Studio to verify the review and issue the code.
	 *
	 * This plugin never decides whether a review exists - it forwards a
	 * username and an address, and repeats whatever the service answers. A site
	 * that could grant itself a coupon by editing an option would be a coupon
	 * printer, which is why the whole judgement lives on the other side.
	 *
	 * @return void
	 */
	protected function claim_efb() {
		$text = $this->strings_efb();

		$username = isset( $_POST['username'] ) ? sanitize_user( wp_unslash( $_POST['username'] ), true ) : '';
		$email    = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';

		if ( '' === $username ) {
			wp_send_json_success( array( 'ok' => false, 'message' => $text['errUsername'] ) );
		}

		if ( '' === $email || ! is_email( $email ) ) {
			wp_send_json_success( array( 'ok' => false, 'message' => $text['errEmail'] ) );
		}

		$outcome = $this->request_reward_efb( $username, $email );

		$this->save_state_efb(
			array(
				'username' => $username,
				'outcome'  => $outcome,
				// Only a code actually issued, or one already issued to this
				// person, ends the conversation. Everything else is a state
				// they can still act on, so the invitation stays available.
				'status'   => in_array( $outcome, array( 'granted', 'pending', 'used' ), true ) ? 'rated' : 'snoozed',
				'rated_at' => time(),
			)
		);

		wp_send_json_success(
			array(
				'ok'      => true,
				'outcome' => $outcome,
				'email'   => $email,
			)
		);
	}

	/**
	 * POST the claim to the White Studio payment service.
	 *
	 * @param string $username WordPress.org username.
	 * @param string $email    Where the code should go.
	 * @return string One of the keys in outcomes_efb().
	 */
	protected function request_reward_efb( $username, $email ) {
		$url = $this->reward_endpoint_efb();

		if ( '' === $url ) {
			return 'server';
		}

		$response = wp_remote_post(
			$url,
			array(
				'timeout'     => 20,
				'redirection' => 0,
				'headers'     => array( 'Content-Type' => 'application/json' ),
				'body'        => wp_json_encode(
					array(
						'username' => $username,
						'email'    => $email,
						'domain'   => wp_parse_url( home_url(), PHP_URL_HOST ),
						'locale'   => get_locale(),
						'version'  => EMSFB_PLUGIN_VERSION,
						'percent'  => (int) apply_filters( 'emsfb_review_discount_percent_efb', self::DISCOUNT_PERCENT ),
					)
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return 'server';
		}

		$body = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $body ) || empty( $body['outcome'] ) ) {
			return 'server';
		}

		$outcome = (string) $body['outcome'];

		// An outcome this plugin does not know how to draw is not an outcome.
		return isset( $this->outcomes_efb()[ $outcome ] ) ? $outcome : 'server';
	}

	/**
	 * The White Studio endpoint that verifies a review and issues the coupon.
	 *
	 * @return string
	 */
	protected function reward_endpoint_efb() {
		$base = defined( 'EMSFB_SERVER_URL' ) ? (string) EMSFB_SERVER_URL : '';

		/**
		 * Filter the review-reward endpoint.
		 *
		 * @param string $url Full endpoint URL.
		 */
		$url = (string) apply_filters(
			'emsfb_review_reward_endpoint_efb',
			'' !== $base ? untrailingslashit( $base ) . self::REWARD_PATH : ''
		);

		return wp_http_validate_url( $url ) ? $url : '';
	}

	/**
	 * Send a low rating's written feedback to the reports pipeline.
	 *
	 * It goes through Deactivation_Feedback::send_report_efb() - the same
	 * identity, HMAC signature and retry the deactivation survey uses - because
	 * a complaint is a report whatever screen it was written on. Keeping a
	 * second copy of the signing code would let it drift out of step with the
	 * service.
	 *
	 * @return void
	 */
	protected function feedback_efb() {
		$text = $this->strings_efb();

		// Deliberately not sanitize_textarea_field(): that keeps tags as
		// entities. Reports are read as plain text, so markup is stripped
		// outright, here and again on the server.
		$comment = isset( $_POST['comment'] ) ? (string) wp_unslash( $_POST['comment'] ) : '';
		$comment = trim( wp_strip_all_tags( $comment, false ) );
		$comment = function_exists( 'mb_substr' )
			? mb_substr( $comment, 0, self::MAX_COMMENT )
			: substr( $comment, 0, self::MAX_COMMENT );

		$raw_topics = isset( $_POST['topics'] ) ? (array) wp_unslash( $_POST['topics'] ) : array();
		$allowed    = array_keys( $this->topics_efb() );
		$topics     = array();
		foreach ( $raw_topics as $topic ) {
			$topic = sanitize_key( $topic );
			if ( in_array( $topic, $allowed, true ) ) {
				$topics[] = $topic;
			}
		}

		if ( '' === $comment && empty( $topics ) ) {
			wp_send_json_success( array( 'ok' => false, 'message' => $text['errComment'] ) );
		}

		$rating     = (int) $this->state_efb()['rating'];
		$contact_ok = ! empty( $_POST['contact_ok'] );
		$email      = $contact_ok ? sanitize_email( (string) get_option( 'admin_email' ) ) : '';

		$sent = false;

		if ( class_exists( '\Emsfb\Deactivation_Feedback' ) ) {
			$feedback = new Deactivation_Feedback();
			$result   = $feedback->send_report_efb(
				array(
					'reason'     => self::REPORT_REASON,
					// The rating and the topics are the structure; the sentence
					// is the person's own. All three travel as one message so
					// the service needs no new columns to read it.
					'details'    => $this->compose_report_efb( $rating, $topics, $comment ),
					'email'      => $email,
					'contact_ok' => $contact_ok ? 1 : 0,
					'hp'         => '',
					'env'        => $feedback->collect_env_efb(),
				)
			);

			$sent = ! empty( $result['ok'] );
		}

		// The conversation is over either way: this person has said their piece
		// and must not be asked again next month as though nothing happened.
		$this->save_state_efb(
			array(
				'status'   => 'dismissed',
				'rated_at' => time(),
				'outcome'  => $sent ? 'feedback_sent' : 'feedback_failed',
			)
		);

		wp_send_json_success( array( 'ok' => true, 'sent' => $sent ) );
	}

	/**
	 * One readable report out of a rating, some topics and a sentence.
	 *
	 * @param int    $rating  1-5.
	 * @param array  $topics  Topic keys.
	 * @param string $comment What the person wrote.
	 * @return string
	 */
	protected function compose_report_efb( $rating, array $topics, $comment ) {
		$lines = array( sprintf( 'Rating: %d/5', max( 0, min( 5, (int) $rating ) ) ) );

		if ( ! empty( $topics ) ) {
			$lines[] = 'Topics: ' . implode( ', ', $topics );
		}

		if ( '' !== $comment ) {
			$lines[] = '';
			$lines[] = $comment;
		}

		return implode( "\n", $lines );
	}

	/*
	 * ---------------------------------------------------------------------
	 * Wording
	 * ---------------------------------------------------------------------
	 */

	/**
	 * The modal's wording in the admin's own language.
	 *
	 * Two sources, most specific first: phrases pushed by the White Studio
	 * settings payload (text->review<Key>), then the English source strings.
	 *
	 * Strings that mention the discount carry %s, never a number.
	 *
	 * @return array
	 */
	public function strings_efb() {
		$defaults = array(
			'close'            => esc_html__( 'Close', 'easy-form-builder' ),
			'ribbon'           => esc_html__( 'Special offer for Easy Form Builder users', 'easy-form-builder' ),
			/* translators: %s: the discount, e.g. "64%". */
			'ribbonPct'        => esc_html__( '%s OFF', 'easy-form-builder' ),

			'askTitle'         => esc_html__( 'How has Easy Form Builder been for you?', 'easy-form-builder' ),
			'starsLabel'       => esc_html__( 'Your rating', 'easy-form-builder' ),
			'r0'               => esc_html__( 'Tap a star to rate', 'easy-form-builder' ),
			'r1'               => esc_html__( 'Not what I needed', 'easy-form-builder' ),
			'r2'               => esc_html__( 'It works, but it is hard', 'easy-form-builder' ),
			'r3'               => esc_html__( 'It is fine', 'easy-form-builder' ),
			'r4'               => esc_html__( 'Pretty good', 'easy-form-builder' ),
			'r5'               => esc_html__( 'Great, I recommend it', 'easy-form-builder' ),
			'praiseTitle'      => esc_html__( 'Thank you!', 'easy-form-builder' ),
			/* translators: %s: the discount, e.g. "64%". */
			'praiseLead'       => esc_html__( 'Leave a 5-star review on WordPress.org and we will email you a %s discount code. It only takes a minute, and it helps other people find the plugin.', 'easy-form-builder' ),
			'couponNote'       => esc_html__( 'On your first year of Pro · the code is emailed to you', 'easy-form-builder' ),
			'flow1'            => esc_html__( 'Post your 5-star review', 'easy-form-builder' ),
			'flow2'            => esc_html__( 'Come back and claim the code', 'easy-form-builder' ),
			'writeReview'      => esc_html__( 'Post a 5-star review', 'easy-form-builder' ),
			'opensWp'          => esc_html__( 'Opens on WordPress.org', 'easy-form-builder' ),
			'aPosted'          => esc_html__( 'I posted it - get my code', 'easy-form-builder' ),

			'claimBadge'       => esc_html__( 'Review posted', 'easy-form-builder' ),
			'claimTitle'       => esc_html__( 'Where should we send the code?', 'easy-form-builder' ),
			'claimLead'        => esc_html__( 'Enter your WordPress.org username and your email. We will find your review and email the discount code to that address.', 'easy-form-builder' ),
			'fieldUser'        => esc_html__( 'WordPress.org username', 'easy-form-builder' ),
			'userPh'           => esc_html__( 'e.g. hassan_t', 'easy-form-builder' ),
			'userHint'         => esc_html__( 'The name your review was posted under.', 'easy-form-builder' ),
			'fieldEmail'       => esc_html__( 'Your email', 'easy-form-builder' ),
			'emailPh'          => esc_html__( 'you@example.com', 'easy-form-builder' ),
			'emailHint'        => esc_html__( 'The discount code is sent to this address.', 'easy-form-builder' ),
			'privacyNote'      => esc_html__( 'This is used only to find your review and send the code - you are not added to any mailing list.', 'easy-form-builder' ),

			'checkTitle'       => esc_html__( 'Checking your review', 'easy-form-builder' ),
			'checkLead'        => esc_html__( 'One moment while we look for your review on WordPress.org.', 'easy-form-builder' ),
			'csFind'           => esc_html__( 'Finding the review', 'easy-form-builder' ),
			'csStars'          => esc_html__( 'Checking the rating', 'easy-form-builder' ),
			'csSend'           => esc_html__( 'Emailing the code', 'easy-form-builder' ),

			'ocGrantedTitle'   => esc_html__( 'Your discount code is on its way', 'easy-form-builder' ),
			'ocGrantedLead'    => esc_html__( 'The email has just been sent. If it is not in your inbox, check the spam folder.', 'easy-form-builder' ),
			'ocGrantedDetail'  => esc_html__( 'The code is valid for 7 days.', 'easy-form-builder' ),
			'ocPendingTitle'   => esc_html__( 'Your review is awaiting publication', 'easy-form-builder' ),
			'ocPendingLead'    => esc_html__( 'Reviews take a little while to appear on WordPress.org. As soon as yours is live, we will email the code automatically.', 'easy-form-builder' ),
			'ocPendingDetail'  => esc_html__( 'Nothing else to do - you can close this window.', 'easy-form-builder' ),
			'ocNotFoundTitle'  => esc_html__( 'No review found for that username', 'easy-form-builder' ),
			'ocNotFoundLead'   => esc_html__( 'The review may not be posted yet, or the username may be different. Check the username and try again.', 'easy-form-builder' ),
			'ocNotFoundDetail' => esc_html__( 'The username is the one shown above your review text.', 'easy-form-builder' ),
			'ocLowStarsTitle'  => esc_html__( 'Your review is not 5 stars', 'easy-form-builder' ),
			/* translators: %s: the discount, e.g. "64%". */
			'ocLowStarsLead'   => esc_html__( 'The %s discount is for 5-star reviews. If your experience was good, you can edit your rating and try again.', 'easy-form-builder' ),
			'ocLowStarsDetail' => esc_html__( 'If something went wrong instead, send us feedback and we will fix it.', 'easy-form-builder' ),
			'ocUsedTitle'      => esc_html__( 'This code has already been claimed', 'easy-form-builder' ),
			'ocUsedLead'       => esc_html__( 'A discount code was already issued for this username. Check that earlier email, or contact support.', 'easy-form-builder' ),
			'ocUsedDetail'     => esc_html__( 'One discount code per person.', 'easy-form-builder' ),
			'ocBadEmailTitle'  => esc_html__( 'That email did not work', 'easy-form-builder' ),
			'ocBadEmailLead'   => esc_html__( 'We could not send mail to this address. Check it and try again.', 'easy-form-builder' ),
			'ocBadEmailDetail' => esc_html__( 'Use a real, active mailbox rather than a temporary address.', 'easy-form-builder' ),
			'ocServerTitle'    => esc_html__( 'We could not check right now', 'easy-form-builder' ),
			'ocServerLead'     => esc_html__( 'The server could not be reached. Try again in a moment - nothing you entered was lost.', 'easy-form-builder' ),
			'ocServerDetail'   => esc_html__( 'If it keeps happening, let the Easy Form Builder team know.', 'easy-form-builder' ),

			'fbTitle'          => esc_html__( 'What should we make better?', 'easy-form-builder' ),
			'fbLead'           => esc_html__( 'We would rather fix it first. This goes straight to the team, not to the public page.', 'easy-form-builder' ),
			'fbPlaceholder'    => esc_html__( 'For example: I could not find the form email settings&hellip;', 'easy-form-builder' ),
			'fbContact'        => esc_html__( 'You may contact me about this if needed (the site admin email is used).', 'easy-form-builder' ),
			'changeRating'     => esc_html__( 'Change rating', 'easy-form-builder' ),
			'topicBuilding'    => esc_html__( 'Building forms', 'easy-form-builder' ),
			'topicEmail'       => esc_html__( 'Sending email', 'easy-form-builder' ),
			'topicStyling'     => esc_html__( 'Look & styling', 'easy-form-builder' ),
			'topicSpeed'       => esc_html__( 'Speed', 'easy-form-builder' ),
			'topicPayments'    => esc_html__( 'Payments', 'easy-form-builder' ),
			'topicTranslation' => esc_html__( 'Translation', 'easy-form-builder' ),

			'sentTitle'        => esc_html__( 'Your message is in', 'easy-form-builder' ),
			'sentLead'         => esc_html__( 'The Easy Form Builder team reads every one, and replies if you left an email.', 'easy-form-builder' ),
			'sentCoupon'       => esc_html__( 'If we fix it and you change your mind, we would love a rating later.', 'easy-form-builder' ),

			'aLater'           => esc_html__( 'Maybe later', 'easy-form-builder' ),
			'aNever'           => esc_html__( 'Do not ask again', 'easy-form-builder' ),
			'aGetCode'         => esc_html__( 'Send my discount code', 'easy-form-builder' ),
			'aSend'            => esc_html__( 'Send feedback', 'easy-form-builder' ),
			'aRetry'           => esc_html__( 'Try again', 'easy-form-builder' ),
			'aEditInfo'        => esc_html__( 'Edit details', 'easy-form-builder' ),
			'aSupport'         => esc_html__( 'Contact support', 'easy-form-builder' ),
			'aWriteFeedback'   => esc_html__( 'Send feedback', 'easy-form-builder' ),
			'aDone'            => esc_html__( 'Close', 'easy-form-builder' ),
			'sending'          => esc_html__( 'Sending&hellip;', 'easy-form-builder' ),

			'errUsername'      => esc_html__( 'Please enter your WordPress.org username.', 'easy-form-builder' ),
			'errEmail'         => esc_html__( 'Please enter a valid email address.', 'easy-form-builder' ),
			'errComment'       => esc_html__( 'Please pick a topic or write a sentence first.', 'easy-form-builder' ),
			'failed'           => esc_html__( 'Something went wrong. Please try again in a moment.', 'easy-form-builder' ),
		);

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
}
