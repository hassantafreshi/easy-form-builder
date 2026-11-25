<?php
/**
 * PAY00 §13, §16: Easy Form Builder → Payments.
 *
 * Tab "Payments": the list (server-side pages, filters, totals per currency) and the details of
 * one payment (event timeline, quote, link to the entry, re-check, refund when the gateway can).
 * Tab "Gateways": one card per registered gateway, built from Gateway::settings_schema(); secrets
 * are shown as "•••• abcd" and an empty field keeps the stored key; "Test connection"; Test / Live
 * with a confirmation before Live; the webhook address to give the provider.
 *
 * Tab "Moved forms" (QA4-003, while forms use the add-ons' new fields after PAY-OUT step 5): before going back to an
 * older version, "Move every form back" (Legacy_Migration::rollback_all(); edits made since the move are kept).
 *
 * QA3-005: without any gateway add-on the screen stays, read only, while the payments table has rows (or forms are
 * moved): the list and each payment; checking or refunding a payment needs its gateway add-on, and the screen says so.
 *
 * Every action: nonce + capability Emsfb_payments (administrators; manage_options as fallback).
 */
namespace Emsfb\Payments;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Admin {

	const SLUG = 'Emsfb_payments';

	/**
	 * SET-01 (B8's hand-off): the Stripe and PayPal add-ons' lists of the payments their older fields took ("Older Stripe
	 * payments" / "Older PayPal payments", in the menu while they hold rows) are a section of Payments: while Payments
	 * exists, they leave the menu and Payments has an "Older payments" tab that opens them; on their screens the menu
	 * shows Payments. Without Payments (no gateway add-on on the framework) they stay in the menu.
	 */
	const OLDER_SCREENS = array( 'Emsfb_stripe_payment', 'Emsfb_paypal_payment' );

	/** @var array<string, string> slug => title of the older-payments screens this request moved into Payments. */
	private static $older = array();

	public static function register() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ), 20 );
		add_action( 'admin_menu', array( __CLASS__, 'older_menu' ), 999 );
		add_action( 'admin_head', array( __CLASS__, 'older_hide' ), 1 );
		add_filter( 'parent_file', array( __CLASS__, 'older_parent' ) );
		add_filter( 'submenu_file', array( __CLASS__, 'older_submenu' ) );
		add_action( 'admin_post_efb_pay_settings', array( __CLASS__, 'save_settings' ) );
		add_action( 'admin_post_efb_pay_legacy_rollback_all', array( __CLASS__, 'rollback_all' ) );
		add_action( 'wp_ajax_efb_pay_action', array( __CLASS__, 'ajax' ) );
		// UX-01: through the notice manager (one notice at a time on the plugin's screens). Payment readiness is no longer a
		// notice: a badge per form in the forms list and a summary on Payments (readiness_summary()).
		$nf = dirname( __DIR__ ) . '/class-Emsfb-notices.php';
		if ( ! class_exists( '\\Emsfb\\Notices' ) && is_readable( $nf ) ) require_once $nf;
		if ( class_exists( '\\Emsfb\\Notices' ) ) {
			// An add-on made for another framework version cannot load: its payments fail now (the one "error").
			\Emsfb\Notices::add( 'payment_addon_version', array( 'render' => array( __CLASS__, 'version_notice' ), 'priority' => 95, 'severity' => 'error', 'screens' => 'admin' ) );
			\Emsfb\Notices::add( 'payment_cron', array( 'render' => array( __CLASS__, 'cron_notice' ), 'priority' => 85, 'severity' => 'warning', 'screens' => 'efb' ) );
		} else {
			add_action( 'admin_notices', array( __CLASS__, 'notices' ) );
		}
	}

	public static function can() {
		return current_user_can( 'Emsfb_payments' ) || current_user_can( 'manage_options' );
	}

	/** Whether a gateway add-on is installed and active (the screens' actions, the Gateway settings). */
	public static function active() {
		return (bool) Registry::all();
	}

	/** Whether the payments table holds a payment (once per request). */
	public static function has_payments() {
		static $has = null;
		if ( $has !== null ) return $has;
		if ( ! Store::ready() ) return $has = false;
		global $wpdb;
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery -- table from $wpdb->prefix, one row
		return $has = (bool) $wpdb->get_var( 'SELECT id FROM `' . Store::table( 'emsfb_pmt_' ) . '` LIMIT 1' );
	}

	/** Forms on the add-ons' new fields (PAY-OUT step 5) that an older version could not show. */
	private static function moved() {
		return class_exists( '\\Emsfb\\Payments\\Legacy_Migration' ) ? Legacy_Migration::moved_ids() : array();
	}

	/**
	 * Whether Payments is in the menu: a gateway add-on is active, or (QA3-005, read only) the payments table holds
	 * payments, or (QA4-003) forms are moved. Without any of them: nothing of the payment framework in the menu.
	 */
	public static function available() {
		return self::active() || self::has_payments() || (bool) self::moved();
	}

	public static function menu() {
		if ( ! self::available() ) return;
		add_submenu_page( 'Emsfb', esc_html__( 'Payments', 'easy-form-builder' ), esc_html__( 'Payments', 'easy-form-builder' ), current_user_can( 'Emsfb_payments' ) ? 'Emsfb_payments' : 'manage_options', self::SLUG, array( __CLASS__, 'page' ) );
	}

	/** admin_menu (late, after the add-ons): which older-payments screens go into Payments' "Older payments". */
	public static function older_menu() {
		global $submenu;
		if ( ! self::available() || ! isset( $submenu['Emsfb'] ) || ! is_array( $submenu['Emsfb'] ) ) return;
		foreach ( $submenu['Emsfb'] as $item ) {
			if ( isset( $item[2] ) && in_array( $item[2], self::OLDER_SCREENS, true ) && current_user_can( $item[1] ) ) {
				self::$older[ $item[2] ] = trim( wp_strip_all_tags( isset( $item[3] ) && $item[3] !== '' ? $item[3] : $item[0] ) );
			}
		}
	}

	/**
	 * admin_head: they leave the menu as it is drawn (menu-header.php comes after admin_head). Not at admin_menu:
	 * WordPress checks a screen's access against the menu first, and a screen missing from it answers 403.
	 */
	public static function older_hide() {
		foreach ( array_keys( self::$older ) as $slug ) remove_submenu_page( 'Emsfb', $slug );
	}

	/** @return array<string, string> the older-payments screens in Payments: slug => title. */
	public static function older_screens() {
		return self::$older;
	}

	private static function on_older_screen() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- which screen, read only
		return is_admin() && isset( $_GET['page'] ) && isset( self::$older[ (string) wp_unslash( $_GET['page'] ) ] );
	}

	/** parent_file / submenu_file: on an older-payments screen the menu shows Payments. */
	public static function older_parent( $f ) {
		return self::on_older_screen() ? 'Emsfb' : $f;
	}

	public static function older_submenu( $f ) {
		return self::on_older_screen() ? self::SLUG : $f;
	}

	/** The "Older payments" tab: what they are and a link to each add-on's list. */
	private static function older_tab() {
		echo '<section class="efb-pay-older" aria-labelledby="efbPayOlderH"><h2 id="efbPayOlderH">' . esc_html__( 'Older payments', 'easy-form-builder' ) . '</h2>';
		echo '<p class="description">' . esc_html__( 'Payments taken with the Stripe and PayPal fields Easy Form Builder had before version 4.3.2. Each list is kept by its add-on; new payments are on the Payments tab.', 'easy-form-builder' ) . '</p><ul>';
		foreach ( self::$older as $slug => $title ) {
			echo '<li><a class="button" href="' . esc_url( admin_url( 'admin.php?page=' . $slug ) ) . '">' . esc_html( $title ) . '</a></li>';
		}
		echo '</ul></section>';
	}

	/** Problems of the registry (an add-on for another framework version) and a disabled WP-Cron (both notices). */
	public static function notices() {
		self::version_notice();
		self::cron_notice();
	}

	/** A payment add-on made for another version of the framework: it is not loaded, so its payments fail now. */
	public static function version_notice() {
		if ( ! self::can() ) return;
		foreach ( Registry::errors() as $e ) {
			if ( $e['code'] === 'api_version' ) {
				/* translators: %s: add-on key */
				echo '<div class="notice notice-error efb-notice efb-pay-version-notice"><p style="unicode-bidi:plaintext">' . esc_html( sprintf( __( 'The payment add-on %s was made for another version of Easy Form Builder. Please update the add-on and Easy Form Builder.', 'easy-form-builder' ), $e['addon'] !== '' ? $e['addon'] : $e['slug'] ) ) . '</p></div>';
			}
		}
	}

	/** On Payments: WP-Cron is off and pending payments are not being checked. */
	public static function cron_notice() {
		if ( ! self::can() ) return;
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( $screen && strpos( (string) $screen->id, self::SLUG ) !== false && defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON ) {
			$last = (int) get_option( 'emsfb_pmt_reconcile_last', 0 );
			if ( Store::ready() && $last < time() - 2 * HOUR_IN_SECONDS && Store::stats()['pending'] > 0 ) {
				echo '<div class="notice notice-warning efb-notice"><p>' . esc_html__( 'WP-Cron is disabled and pending payments are not being checked.', 'easy-form-builder' ) . '</p></div>';
			}
		}
	}

	/**
	 * UX-01: on Payments, the forms visitors cannot pay with now (PAY00 readiness, computed on every load: a form drops out
	 * once its keys are added or its payment field is fixed), each with its first problem and a link to it in the builder.
	 * It was a red notice on every screen of the plugin, without a dismiss; the forms list marks each form too.
	 */
	public static function readiness_summary() {
		$forms = Readiness::not_ready_forms();
		if ( ! $forms ) return;
		$n = count( $forms );
		echo '<section class="efb-pay-ready" id="efbPayReady" aria-labelledby="efbPayReadyH"><h2 id="efbPayReadyH">'
			/* translators: %s: number of forms */
			. esc_html( sprintf( _n( '%s form cannot take payments yet', '%s forms cannot take payments yet', $n, 'easy-form-builder' ), number_format_i18n( $n ) ) ) . '</h2>'
			. '<p>' . esc_html__( 'Visitors cannot pay with these forms until their problem is fixed. The forms list marks them too.', 'easy-form-builder' ) . '</p><ul>';
		$i = 0;
		foreach ( $forms as $id => $f ) {
			if ( ++$i === 11 ) {
				/* translators: %s: number of further forms */
				echo '</ul><details><summary>' . esc_html( sprintf( _n( 'Show %s more form', 'Show %s more forms', $n - 10, 'easy-form-builder' ), number_format_i18n( $n - 10 ) ) ) . '</summary><ul>';
			}
			$first = isset( $f['issues'][0]['m'] ) ? (string) $f['issues'][0]['m'] : '';
			$url = admin_url( 'admin.php?page=Emsfb&state=edit-form&id=' . (int) $id );
			echo '<li data-form="' . (int) $id . '"><bdi><a href="' . esc_url( $url ) . '">' . esc_html( $f['name'] !== '' ? $f['name'] : '#' . (int) $id ) . '</a></bdi>: ' . esc_html( $first );
			if ( count( $f['issues'] ) > 1 ) {
				/* translators: %d: number of further problems */
				echo ' ' . esc_html( sprintf( _n( '(and %d more problem)', '(and %d more problems)', count( $f['issues'] ) - 1, 'easy-form-builder' ), count( $f['issues'] ) - 1 ) );
			}
			echo '</li>';
		}
		echo $i > 10 ? '</ul></details></section>' : '</ul></section>';
	}

	private static function status_label( $s ) {
		$l = array(
			'created'            => __( 'Created', 'easy-form-builder' ),
			'pending'            => __( 'Pending', 'easy-form-builder' ),
			'authorized'         => __( 'Authorized', 'easy-form-builder' ),
			'paid'               => __( 'Paid', 'easy-form-builder' ),
			'failed'             => __( 'Failed', 'easy-form-builder' ),
			'canceled'           => __( 'Canceled', 'easy-form-builder' ),
			'expired'            => __( 'Expired', 'easy-form-builder' ),
			'refunded'           => __( 'Refunded', 'easy-form-builder' ),
			'partially_refunded' => __( 'Partially refunded', 'easy-form-builder' ),
		);
		return isset( $l[ $s ] ) ? $l[ $s ] : $s;
	}

	/** QA3-011: the mode as a short badge word, translatable (TEST / LIVE). */
	private static function mode_label( $m ) {
		/* translators: payment mode badge in the Payments list (upper case in English): LIVE = real money, TEST = no real money */
		return $m === 'live' ? _x( 'LIVE', 'payment mode badge', 'easy-form-builder' ) : _x( 'TEST', 'payment mode badge', 'easy-form-builder' );
	}

	private static function badge( $s ) {
		$c = array( 'paid' => 'success', 'pending' => 'warning', 'created' => 'secondary', 'authorized' => 'info', 'failed' => 'danger', 'canceled' => 'secondary', 'expired' => 'secondary', 'refunded' => 'dark', 'partially_refunded' => 'dark' );
		return '<span class="efb-pay-badge efb-pay-' . esc_attr( isset( $c[ $s ] ) ? $c[ $s ] : 'secondary' ) . '">' . esc_html( self::status_label( $s ) ) . '</span>';
	}

	private static function local_time( $utc ) {
		if ( ! $utc ) return '';
		$ts = strtotime( $utc . ' UTC' );
		return function_exists( 'wp_date' ) ? wp_date( 'Y-m-d H:i', $ts ) : gmdate( 'Y-m-d H:i', $ts );
	}

	public static function page() {
		if ( ! self::can() || ! self::available() ) wp_die( esc_html__( 'Sorry, you are not allowed to access this page.', 'easy-form-builder' ) );
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only screen parameters
		$tab = isset( $_GET['tab'] ) && in_array( $_GET['tab'], array( 'gateways', 'older', 'downgrade' ), true ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'payments';
		if ( $tab === 'older' && ! self::$older ) $tab = 'payments';
		$moved = self::moved();
		$id = isset( $_GET['payment'] ) ? absint( $_GET['payment'] ) : 0;
		// phpcs:enable
		$base = admin_url( 'admin.php?page=' . self::SLUG );
		echo '<div class="wrap efb-pay-admin" id="efbPayAdmin">';
		self::style();
		echo '<h1>' . esc_html__( 'Payments', 'easy-form-builder' ) . '</h1>';
		echo '<nav class="nav-tab-wrapper" aria-label="' . esc_attr__( 'Payments', 'easy-form-builder' ) . '">';
		echo '<a class="nav-tab' . ( $tab === 'payments' ? ' nav-tab-active' : '' ) . '" href="' . esc_url( $base ) . '"' . ( $tab === 'payments' ? ' aria-current="page"' : '' ) . '>' . esc_html__( 'Payments', 'easy-form-builder' ) . '</a>';
		echo '<a class="nav-tab' . ( $tab === 'gateways' ? ' nav-tab-active' : '' ) . '" href="' . esc_url( $base . '&tab=gateways' ) . '"' . ( $tab === 'gateways' ? ' aria-current="page"' : '' ) . '>' . esc_html__( 'Gateway settings', 'easy-form-builder' ) . '</a>';
		if ( self::$older ) {
			echo '<a class="nav-tab' . ( $tab === 'older' ? ' nav-tab-active' : '' ) . '" href="' . esc_url( $base . '&tab=older' ) . '"' . ( $tab === 'older' ? ' aria-current="page"' : '' ) . '>' . esc_html__( 'Older payments', 'easy-form-builder' ) . '</a>';
		}
		if ( $moved || $tab === 'downgrade' ) {
			echo '<a class="nav-tab' . ( $tab === 'downgrade' ? ' nav-tab-active' : '' ) . '" href="' . esc_url( $base . '&tab=downgrade' ) . '"' . ( $tab === 'downgrade' ? ' aria-current="page"' : '' ) . '>' . esc_html__( 'Moved forms', 'easy-form-builder' ) . '</a>';
		}
		echo '</nav>';
		if ( $tab === 'downgrade' ) {
			self::downgrade_tab( $moved );
			echo '</div>';
			return;
		}
		if ( ! self::active() && $tab !== 'gateways' ) {
			echo '<div class="notice notice-info inline efb-pay-readonly" data-code="pay_read_only"><p>' . esc_html__( 'No payment add-on is active, so this list is read only. To check a payment again or refund it, turn its add-on on in Add-ons.', 'easy-form-builder' ) . ' <a href="' . esc_url( admin_url( 'admin.php?page=Emsfb_addon' ) ) . '">' . esc_html__( 'Open Add-ons', 'easy-form-builder' ) . '</a></p></div>';
		}
		if ( ! Store::ready() ) {
			echo '<p>' . esc_html__( 'The payment tables are being created. Please reload this page in a moment.', 'easy-form-builder' ) . '</p></div>';
			return;
		}
		if ( $tab === 'older' ) {
			self::older_tab();
			echo '</div>';
			return;
		}
		self::readiness_summary();
		if ( $tab === 'gateways' ) self::gateways_tab();
		elseif ( $id ) self::details( $id );
		else self::list_tab();
		self::script();
		echo '</div>';
	}

	/* ------------------------------------------------------------------ list */

	private static function list_tab() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- filters of a read-only list
		$f = array();
		foreach ( array( 'gateway', 'status', 'mode', 'q', 'from', 'to' ) as $k ) $f[ $k ] = isset( $_GET[ $k ] ) ? sanitize_text_field( wp_unslash( $_GET[ $k ] ) ) : '';
		$f['form_id'] = isset( $_GET['form_id'] ) ? absint( $_GET['form_id'] ) : 0;
		$f['page'] = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1;
		$f['per_page'] = isset( $_GET['per_page'] ) ? absint( $_GET['per_page'] ) : 20;
		// phpcs:enable
		$res = Store::search( $f );
		$stats = Store::stats( $f['mode'] );
		echo '<div class="efb-pay-stats">';
		// QA3-018: each currency's total in its gateways' exponent (Store::stats() sums every gateway at the largest one).
		foreach ( $stats['paid'] as $row ) {
			echo '<div class="efb-pay-stat"><span>' . esc_html__( 'Paid', 'easy-form-builder' ) . ' <bdi>' . esc_html( $row['currency'] ) . '</bdi></span><strong><bdi>' . esc_html( Money::format( (int) $row['total'], $row['currency'], get_user_locale(), isset( $row['gateway'] ) ? (string) $row['gateway'] : '' ) ) . '</bdi></strong></div>';
		}
		echo '<div class="efb-pay-stat"><span>' . esc_html__( 'Pending', 'easy-form-builder' ) . '</span><strong>' . esc_html( number_format_i18n( $stats['pending'] ) ) . '</strong></div></div>';

		echo '<form method="get" class="efb-pay-filters"><input type="hidden" name="page" value="' . esc_attr( self::SLUG ) . '">';
		echo '<label>' . esc_html__( 'Gateway', 'easy-form-builder' ) . ' <select name="gateway"><option value="">' . esc_html__( 'All', 'easy-form-builder' ) . '</option>';
		foreach ( Registry::all() as $slug => $g ) echo '<option value="' . esc_attr( $slug ) . '"' . selected( $f['gateway'], $slug, false ) . '>' . esc_html( $g->label() ) . '</option>';
		echo '</select></label> <label>' . esc_html__( 'Status', 'easy-form-builder' ) . ' <select name="status"><option value="">' . esc_html__( 'All', 'easy-form-builder' ) . '</option>';
		foreach ( Store::STATUSES as $s ) echo '<option value="' . esc_attr( $s ) . '"' . selected( $f['status'], $s, false ) . '>' . esc_html( self::status_label( $s ) ) . '</option>';
		echo '</select></label> <label>' . esc_html__( 'Mode', 'easy-form-builder' ) . ' <select name="mode"><option value="">' . esc_html__( 'All', 'easy-form-builder' ) . '</option><option value="test"' . selected( $f['mode'], 'test', false ) . '>' . esc_html( self::mode_label( 'test' ) ) . '</option><option value="live"' . selected( $f['mode'], 'live', false ) . '>' . esc_html( self::mode_label( 'live' ) ) . '</option></select></label>';
		echo ' <label>' . esc_html__( 'From', 'easy-form-builder' ) . ' <input type="date" name="from" value="' . esc_attr( $f['from'] ) . '"></label> <label>' . esc_html__( 'To', 'easy-form-builder' ) . ' <input type="date" name="to" value="' . esc_attr( $f['to'] ) . '"></label>';
		echo ' <label>' . esc_html__( 'Search', 'easy-form-builder' ) . ' <input type="search" name="q" value="' . esc_attr( $f['q'] ) . '" placeholder="' . esc_attr__( 'Reference, confirmation code, e-mail', 'easy-form-builder' ) . '"></label> <button class="button">' . esc_html__( 'Filter', 'easy-form-builder' ) . '</button></form>';

		if ( empty( $res['rows'] ) ) {
			// QA3-016: a filter that matches nothing is not an empty list.
			$filtered = $f['gateway'] !== '' || $f['status'] !== '' || $f['mode'] !== '' || $f['q'] !== '' || $f['from'] !== '' || $f['to'] !== '' || $f['form_id'] > 0;
			echo '<p class="efb-pay-empty" data-code="' . ( $filtered ? 'pay_no_match' : 'pay_none' ) . '">' . esc_html( $filtered ? __( 'No payments match these filters.', 'easy-form-builder' ) : __( 'No payments yet.', 'easy-form-builder' ) ) . '</p>';
			if ( $filtered ) echo '<p><a href="' . esc_url( admin_url( 'admin.php?page=' . self::SLUG ) ) . '">' . esc_html__( 'Show all payments', 'easy-form-builder' ) . '</a></p>';
			return;
		}
		// QA3-008: WordPress' list-table pattern: on a phone each payment is a card (its date first, the other columns
		// under it with their names), no sideways scroll, right to left too.
		$cols = array( 'date' => __( 'Date', 'easy-form-builder' ), 'form' => __( 'Form', 'easy-form-builder' ), 'track' => __( 'Confirmation Code', 'easy-form-builder' ), 'gateway' => __( 'Gateway', 'easy-form-builder' ), 'method' => __( 'Method', 'easy-form-builder' ), 'amount' => __( 'Amount', 'easy-form-builder' ), 'status' => __( 'Status', 'easy-form-builder' ), 'mode' => __( 'Mode', 'easy-form-builder' ), 'payer' => __( 'Payer', 'easy-form-builder' ) );
		echo '<table class="wp-list-table widefat fixed striped table-view-list efb-pay-table"><thead><tr>';
		foreach ( $cols as $k => $h ) echo '<th scope="col" class="manage-column column-' . esc_attr( $k ) . ( $k === 'date' ? ' column-primary' : '' ) . '">' . esc_html( $h ) . '</th>';
		echo '</tr></thead><tbody>';
		foreach ( $res['rows'] as $p ) {
			$g = Registry::get( $p['gateway'] );
			$link = admin_url( 'admin.php?page=' . self::SLUG . '&payment=' . (int) $p['id'] );
			$cell = array(
				'form'    => (string) (int) $p['form_id'],
				'track'   => '<code><bdi>' . esc_html( $p['track'] ) . '</bdi></code>',
				'gateway' => esc_html( $g ? $g->label() : Readiness::addon_name( $p['gateway'] ) ),
				'method'  => esc_html( $p['method'] ),
				'amount'  => '<bdi>' . esc_html( Money::format( $p['amount_minor'], $p['currency'], get_user_locale(), $p['gateway'] ) ) . '</bdi>',
				'status'  => self::badge( $p['status'] ),
				'mode'    => $p['mode'] === 'live' ? esc_html( self::mode_label( 'live' ) ) : '<span class="efb-pay-test">' . esc_html( self::mode_label( 'test' ) ) . '</span>',
				'payer'   => '<bdi>' . esc_html( $p['payer_email'] ) . '</bdi>',
			);
			echo '<tr><td class="column-date column-primary" data-colname="' . esc_attr( $cols['date'] ) . '"><a href="' . esc_url( $link ) . '"><bdi>' . esc_html( self::local_time( $p['created_at'] ) ) . '</bdi></a>'
				. '<button type="button" class="toggle-row"><span class="screen-reader-text">' . esc_html__( 'Show more details', 'easy-form-builder' ) . '</span></button></td>';
			foreach ( $cell as $k => $v ) echo '<td class="column-' . esc_attr( $k ) . '" data-colname="' . esc_attr( $cols[ $k ] ) . '">' . $v . '</td>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- each cell escaped above
			echo '</tr>';
		}
		echo '</tbody></table>';
		$pages = (int) ceil( $res['total'] / $res['per_page'] );
		if ( $pages > 1 ) {
			echo '<nav class="efb-pay-pages" aria-label="' . esc_attr__( 'Pages', 'easy-form-builder' ) . '">';
			for ( $i = 1; $i <= $pages && $i <= 50; $i++ ) {
				$u = add_query_arg( array_merge( array_filter( $f ), array( 'paged' => $i, 'page' => self::SLUG ) ), admin_url( 'admin.php' ) );
				echo $i === $res['page'] ? '<span aria-current="page">' . (int) $i . '</span> ' : '<a href="' . esc_url( $u ) . '">' . (int) $i . '</a> ';
			}
			echo '</nav>';
		}
	}

	/* ------------------------------------------------------------------ details */

	private static function details( $id ) {
		$p = Store::get( $id );
		if ( ! $p ) {
			echo '<p>' . esc_html__( 'This payment does not exist.', 'easy-form-builder' ) . '</p>';
			return;
		}
		$g = Registry::get( $p['gateway'] );
		$sup = $g ? array_merge( ( new Gateway_Defaults() )->supports(), (array) $g->supports() ) : array();
		echo '<p><a href="' . esc_url( admin_url( 'admin.php?page=' . self::SLUG ) ) . '">← ' . esc_html__( 'All payments', 'easy-form-builder' ) . '</a></p>';
		echo '<h2>' . esc_html( Money::format( $p['amount_minor'], $p['currency'], get_user_locale(), $p['gateway'] ) ) . ' ' . self::badge( $p['status'] ) . ( $p['mode'] !== 'live' ? ' <span class="efb-pay-test">' . esc_html( self::mode_label( 'test' ) ) . '</span>' : '' ) . '</h2>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped parts
		echo '<table class="widefat efb-pay-kv"><tbody>';
		$kv = array(
			__( 'Gateway', 'easy-form-builder' )           => $g ? $g->label() : Readiness::addon_name( $p['gateway'] ),
			__( 'Gateway reference', 'easy-form-builder' ) => $p['gateway_ref'],
			__( 'Method', 'easy-form-builder' )            => $p['method'],
			__( 'Confirmation Code', 'easy-form-builder' ) => $p['track'],
			__( 'Form', 'easy-form-builder' )              => (string) $p['form_id'],
			__( 'Payer', 'easy-form-builder' )             => $p['payer_email'],
			__( 'Created', 'easy-form-builder' )           => self::local_time( $p['created_at'] ),
			__( 'Paid', 'easy-form-builder' )              => self::local_time( $p['paid_at'] ),
			__( 'Refunded', 'easy-form-builder' )          => $p['refunded_minor'] ? Money::format( $p['refunded_minor'], $p['currency'], get_user_locale(), $p['gateway'] ) : '',
		);
		foreach ( $kv as $k => $v ) echo '<tr><th scope="row">' . esc_html( $k ) . '</th><td>' . esc_html( (string) $v ) . '</td></tr>';
		echo '</tbody></table>';
		$q = json_decode( (string) $p['quote'], true );
		if ( is_array( $q ) && ! empty( $q['items'] ) ) {
			echo '<h3>' . esc_html__( 'Order summary', 'easy-form-builder' ) . '</h3><ul>';
			foreach ( $q['items'] as $it ) echo '<li>' . esc_html( $it['label'] ) . ': <bdi>' . esc_html( Money::format( $it['total_minor'], $q['currency'], get_user_locale(), $p['gateway'] ) ) . '</bdi></li>';
			echo '</ul><p class="description">' . esc_html__( 'Quote', 'easy-form-builder' ) . ' <code>' . esc_html( substr( (string) ( $q['hash'] ?? '' ), 0, 16 ) ) . '</code></p>';
		}
		if ( ! $g ) {
			// QA3-005: read only without the payment's gateway add-on; a refund goes through that gateway.
			/* translators: %s: name of the payment add-on (e.g. Stripe) */
			echo '<p class="efb-pay-needs-gw" data-code="pay_gateway_inactive">' . esc_html( sprintf( __( 'Checking or refunding this payment goes through %s, and its add-on is not active. Turn the add-on on in Add-ons to refund it here.', 'easy-form-builder' ), Readiness::addon_name( $p['gateway'] ) ) ) . ' <a href="' . esc_url( admin_url( 'admin.php?page=Emsfb_addon' ) ) . '">' . esc_html__( 'Open Add-ons', 'easy-form-builder' ) . '</a></p>';
		}
		$n = wp_create_nonce( 'efb_pay_action' );
		echo '<div class="efb-pay-actions" data-id="' . (int) $p['id'] . '" data-nonce="' . esc_attr( $n ) . '">';
		if ( $g && in_array( $p['status'], array( 'created', 'pending', 'authorized' ), true ) ) echo '<button type="button" class="button" data-efb-pay="recheck">' . esc_html__( 'Check status now', 'easy-form-builder' ) . '</button> ';
		if ( $g && in_array( $p['status'], array( 'paid', 'partially_refunded' ), true ) ) {
			if ( ! empty( $sup['refund'] ) ) {
				$left = Money::to_decimal_string( max( 0, $p['amount_minor'] - $p['refunded_minor'] - Service::pending_refunds( $p['id'] ) ), $p['currency'], $p['gateway'] );
				echo '<label>' . esc_html__( 'Amount', 'easy-form-builder' ) . ' <input type="text" inputmode="decimal" dir="ltr" size="8" id="efbPayRefundAmt" value="' . esc_attr( $left ) . '"' . ( empty( $sup['partial_refund'] ) ? ' readonly' : '' ) . '></label> ';
				/* translators: %1$s: amount with currency */
				echo '<button type="button" class="button button-secondary" data-efb-pay="refund" data-confirm="' . esc_attr( __( 'Refund %1$s to the payer? This cannot be undone.', 'easy-form-builder' ) ) . '" data-currency="' . esc_attr( $p['currency'] ) . '">' . esc_html__( 'Refund', 'easy-form-builder' ) . '</button>';
			} else {
				/* translators: %s: gateway name */
				echo '<p>' . esc_html( self::unsupported_refund_text( $g, $p ) !== '' ? self::unsupported_refund_text( $g, $p ) : sprintf( __( 'This payment method cannot be refunded through the API. Use the %s dashboard.', 'easy-form-builder' ), $g ? $g->label() : $p['gateway'] ) ) . '</p>';
			}
		}
		if ( $g && in_array( $p['status'], array( 'paid', 'partially_refunded' ), true ) ) {
			// Money given back from the provider's dashboard or the bank: recorded here, no call to the provider.
			$left = Money::to_decimal_string( max( 0, $p['amount_minor'] - $p['refunded_minor'] - Service::pending_refunds( $p['id'] ) ), $p['currency'], $p['gateway'] );
			echo '<details class="efb-pay-ext"><summary>' . esc_html__( 'Mark as refunded outside EFB', 'easy-form-builder' ) . '</summary>';
			echo '<p class="description">' . esc_html__( 'Use this when the money was given back in the gateway dashboard or by the bank. Nothing is sent to the gateway.', 'easy-form-builder' ) . '</p>';
			echo '<label>' . esc_html__( 'Amount', 'easy-form-builder' ) . ' <input type="text" inputmode="decimal" dir="ltr" size="8" id="efbPayExtAmt" value="' . esc_attr( $left ) . '"></label> ';
			echo '<label>' . esc_html__( 'Reason', 'easy-form-builder' ) . ' <input type="text" maxlength="200" size="30" id="efbPayExtReason" required></label> ';
			/* translators: %1$s: amount with currency */
			echo '<button type="button" class="button" data-efb-pay="mark_refunded" data-confirm="' . esc_attr( __( 'Record a refund of %1$s made outside Easy Form Builder?', 'easy-form-builder' ) ) . '" data-currency="' . esc_attr( $p['currency'] ) . '">' . esc_html__( 'Mark as refunded', 'easy-form-builder' ) . '</button></details>';
		}
		$refunds = Store::refunds( $p['id'] );
		if ( $refunds ) {
			$rl = array( 'pending' => __( 'Waiting for the gateway', 'easy-form-builder' ), 'refunded' => __( 'Refunded', 'easy-form-builder' ), 'failed' => __( 'Declined', 'easy-form-builder' ), 'canceled' => __( 'Canceled', 'easy-form-builder' ) );
			echo '<h3>' . esc_html__( 'Refunds', 'easy-form-builder' ) . '</h3><ul class="efb-pay-refunds">';
			foreach ( $refunds as $rf ) {
				echo '<li><time>' . esc_html( self::local_time( $rf['created_at'] ) ) . '</time> <bdi>' . esc_html( Money::format( (int) $rf['amount_minor'], $rf['currency'], get_user_locale(), $p['gateway'] ) ) . '</bdi> — ' . esc_html( isset( $rl[ $rf['status'] ] ) ? $rl[ $rf['status'] ] : $rf['status'] );
				if ( $rf['reason'] !== '' ) echo ' — ' . esc_html( $rf['reason'] );
				if ( $g && $rf['status'] === 'pending' ) {
					echo ' <button type="button" class="button button-small" data-efb-pay="refund_settle" data-refund="' . (int) $rf['id'] . '" data-to="refunded" data-confirm="' . esc_attr__( 'The gateway confirmed this refund?', 'easy-form-builder' ) . '">' . esc_html__( 'Confirm refund', 'easy-form-builder' ) . '</button>';
					echo ' <button type="button" class="button button-small" data-efb-pay="refund_settle" data-refund="' . (int) $rf['id'] . '" data-to="failed" data-confirm="' . esc_attr__( 'The gateway declined this refund?', 'easy-form-builder' ) . '">' . esc_html__( 'Mark as declined', 'easy-form-builder' ) . '</button>';
				}
				echo '</li>';
			}
			echo '</ul>';
		}
		echo '<span class="efb-pay-msg" role="status" aria-live="polite"></span></div>';
		$dash = $g ? $g->dashboard_url( new Payment( $p ) ) : '';
		if ( $dash ) echo '<p><a href="' . esc_url( $dash ) . '" target="_blank" rel="noopener">' . esc_html__( 'Open in the gateway dashboard', 'easy-form-builder' ) . '</a></p>';
		echo '<h3>' . esc_html__( 'Events', 'easy-form-builder' ) . '</h3><ol class="efb-pay-events">';
		foreach ( Store::events( $p['id'] ) as $e ) {
			echo '<li><time>' . esc_html( self::local_time( $e['created_at'] ) ) . '</time> <strong>' . esc_html( $e['source'] ) . '</strong> ' . esc_html( $e['type'] );
			if ( $e['from_status'] || $e['to_status'] ) echo ' (' . esc_html( $e['from_status'] . ' → ' . $e['to_status'] ) . ')';
			if ( $e['note'] ) echo ' — ' . esc_html( $e['note'] );
			if ( $e['payload'] ) echo '<details><summary>' . esc_html__( 'Data', 'easy-form-builder' ) . '</summary><pre>' . esc_html( (string) $e['payload'] ) . '</pre></details>';
			echo '</li>';
		}
		echo '</ol>';
	}

	/** The gateway's own words when its refund() says unsupported (no request is made: refund() returns at once). */
	private static function unsupported_refund_text( $g, array $p ) {
		static $cache = array();
		if ( ! $g ) return '';
		if ( isset( $cache[ $p['id'] ] ) ) return $cache[ $p['id'] ];
		$t = '';
		$sup = array_merge( ( new Gateway_Defaults() )->supports(), (array) $g->supports() );
		if ( empty( $sup['refund'] ) ) {
			try { $u = $g->refund( new Payment( $p ), 0, '', Settings::load( $p['gateway'] ) ); } catch ( \Throwable $e ) { $u = null; }
			if ( $u instanceof Result && $u->unsupported ) $t = Field::text( (string) $u->message, 300 );
		}
		return $cache[ $p['id'] ] = $t;
	}

	/* ------------------------------------------------------------------ gateways */

	private static function gateways_tab() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- message after a redirect
		$msg = isset( $_GET['efb_msg'] ) ? sanitize_key( wp_unslash( $_GET['efb_msg'] ) ) : '';
		if ( $msg === 'saved' ) echo '<div class="notice notice-success efb-notice"><p>' . esc_html__( 'Settings saved.', 'easy-form-builder' ) . '</p></div>';
		if ( $msg === 'refused' ) echo '<div class="notice notice-error efb-notice"><p>' . esc_html__( 'Some keys were not saved because they do not look like keys of this gateway.', 'easy-form-builder' ) . '</p></div>';
		if ( ! Registry::all() ) {
			echo '<p>' . esc_html__( 'No payment gateway add-on is installed yet. Install one from the Add-ons page.', 'easy-form-builder' ) . '</p>';
			return;
		}
		echo '<p class="description">' . esc_html__( 'Keys are stored encrypted and are never shown again. Leave a key field empty to keep the stored key. For extra protection define EFB_PAYMENTS_KEY in wp-config.php.', 'easy-form-builder' ) . '</p>';
		foreach ( Registry::all() as $slug => $g ) {
			$s = Settings::load( $slug );
			echo '<section class="efb-pay-card" aria-labelledby="efbPayGw_' . esc_attr( $slug ) . '"><h2 id="efbPayGw_' . esc_attr( $slug ) . '">' . esc_html( $g->label() ) . '</h2>';
			foreach ( array_merge( self::core_notices( $g, $s ), self::gateway_notices( $g, $s ) ) as $nt ) {
				echo '<div class="notice notice-' . esc_attr( $nt['level'] ) . ' inline efb-pay-gw-notice"' . ( isset( $nt['code'] ) ? ' data-code="' . esc_attr( $nt['code'] ) . '"' : '' ) . '><p>' . esc_html( $nt['m'] ) . '</p></div>';
			}
			echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
			wp_nonce_field( 'efb_pay_settings_' . $slug );
			echo '<input type="hidden" name="action" value="efb_pay_settings"><input type="hidden" name="gateway" value="' . esc_attr( $slug ) . '">';
			echo '<fieldset><legend>' . esc_html__( 'Mode', 'easy-form-builder' ) . '</legend>';
			echo '<label><input type="radio" name="mode" value="test"' . checked( $s->mode(), 'test', false ) . '> ' . esc_html__( 'Test (no real payments)', 'easy-form-builder' ) . '</label> ';
			echo '<label><input type="radio" name="mode" value="live" data-efb-live="' . esc_attr__( 'Real payments will be taken from now on. Continue?', 'easy-form-builder' ) . '"' . checked( $s->mode(), 'live', false ) . '> ' . esc_html__( 'Live', 'easy-form-builder' ) . '</label></fieldset>';
			echo '<table class="form-table" role="presentation"><tbody>';
			foreach ( (array) $g->settings_schema() as $fd ) {
				if ( empty( $fd['key'] ) ) continue;
				$modes = ! empty( $fd['per_mode'] ) ? array( 'test', 'live' ) : array( '' );
				foreach ( $modes as $m ) {
					$name = $m === '' ? $fd['key'] : $fd['key'] . '__' . $m;
					$label = ( isset( $fd['label'] ) ? $fd['label'] : $fd['key'] ) . ( $m !== '' ? ' (' . ( $m === 'live' ? __( 'Live', 'easy-form-builder' ) : __( 'Test', 'easy-form-builder' ) ) . ')' : '' );
					$type = isset( $fd['type'] ) ? $fd['type'] : 'text';
					echo '<tr><th scope="row"><label for="efbpay_' . esc_attr( $slug . '_' . $name ) . '">' . esc_html( $label ) . '</label></th><td>';
					if ( $type === 'secret' ) {
						$state = $s->secret_state( $fd['key'], $m === '' ? 'test' : $m );
						$plain = $state === 'ok' ? (string) $s->secret( $fd['key'], $m === '' ? 'test' : $m ) : '';
						$ph = $state === 'ok' ? Secrets::mask( $plain ) : ( $state === 'unreadable' ? __( 'The saved keys can no longer be read. Please enter them again.', 'easy-form-builder' ) : __( 'No keys yet.', 'easy-form-builder' ) );
						echo '<input type="password" autocomplete="new-password" class="regular-text" id="efbpay_' . esc_attr( $slug . '_' . $name ) . '" name="' . esc_attr( $name ) . '" value="" placeholder="' . esc_attr( $ph ) . '">';
						if ( $state === 'unreadable' ) echo '<p class="description efb-pay-err">' . esc_html( $ph ) . '</p>';
					} elseif ( $type === 'select' ) {
						echo '<select id="efbpay_' . esc_attr( $slug . '_' . $name ) . '" name="' . esc_attr( $name ) . '">';
						foreach ( (array) ( $fd['options'] ?? array() ) as $ov => $ol ) echo '<option value="' . esc_attr( $ov ) . '"' . selected( (string) $s->get( $fd['key'], $m === '' ? null : $m ), (string) $ov, false ) . '>' . esc_html( $ol ) . '</option>';
						echo '</select>';
					} elseif ( $type === 'checkbox' ) {
						echo '<input type="hidden" name="' . esc_attr( $name ) . '" value=""><input type="checkbox" id="efbpay_' . esc_attr( $slug . '_' . $name ) . '" name="' . esc_attr( $name ) . '" value="1"' . checked( (string) $s->get( $fd['key'], $m === '' ? null : $m ), '1', false ) . '>';
					} else {
						echo '<input type="text" class="regular-text" id="efbpay_' . esc_attr( $slug . '_' . $name ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( (string) $s->get( $fd['key'], $m === '' ? null : $m ) ) . '">';
					}
					if ( ! empty( $fd['help'] ) ) echo '<p class="description">' . esc_html( $fd['help'] ) . '</p>';
					echo '</td></tr>';
				}
			}
			echo '<tr><th scope="row">' . esc_html__( 'Webhook address', 'easy-form-builder' ) . '</th><td><code>' . esc_html( Service::webhook_url( $slug ) ) . '</code>';
			echo '<p class="description">' . esc_html__( 'If your host blocks the WordPress REST API for visitors, give the gateway this address instead:', 'easy-form-builder' ) . ' <code>' . esc_html( Service::webhook_fallback_url( $slug ) ) . '</code></p></td></tr>';
			echo '</tbody></table>';
			echo '<p><button class="button button-primary">' . esc_html__( 'Save', 'easy-form-builder' ) . '</button> ';
			$n = wp_create_nonce( 'efb_pay_action' );
			foreach ( array( 'test', 'live' ) as $m ) {
				echo '<button type="button" class="button" data-efb-pay="test_connection" data-gateway="' . esc_attr( $slug ) . '" data-mode="' . esc_attr( $m ) . '" data-nonce="' . esc_attr( $n ) . '">' . esc_html( $m === 'live' ? __( 'Test connection (Live)', 'easy-form-builder' ) : __( 'Test connection (Test)', 'easy-form-builder' ) ) . '</button> ';
			}
			foreach ( self::gateway_actions( $g, $s ) as $ac ) {
				echo '<button type="button" class="button" data-efb-pay="gateway_action" data-action-id="' . esc_attr( $ac['id'] ) . '" data-gateway="' . esc_attr( $slug ) . '" data-mode="' . esc_attr( $s->mode() ) . '" data-nonce="' . esc_attr( $n ) . '">' . esc_html( $ac['label'] ) . '</button> ';
			}
			echo '<span class="efb-pay-msg" role="status" aria-live="polite"></span></p></form></section>';
		}
	}

	/**
	 * Core's notices on every gateway's settings card: no keys for the current mode, Test mode, a
	 * payment the provider refused because of the set-up (Service::owner_alert()), refused webhooks
	 * (3 or more in 24 hours), a site the provider cannot reach. Each with a code (for tests and AutoPilot).
	 */
	public static function core_notices( Gateway $g, Settings $s ) {
		$out = array();
		$mode = $s->mode();
		$label = $g->label();
		try { $ready = (bool) $g->is_configured( $s, $mode ); } catch ( \Throwable $e ) { $ready = false; }
		if ( ! $ready ) {
			$out[] = array( 'level' => 'error', 'code' => 'pay_keys_missing', 'm' => $mode === 'live'
				? __( 'No keys for Live mode: visitors cannot pay. Enter the keys and press Test connection.', 'easy-form-builder' )
				: __( 'No keys for Test mode: the payment forms cannot be tried. Enter the Test keys and press Test connection.', 'easy-form-builder' ) );
		}
		if ( $mode !== 'live' ) $out[] = array( 'level' => 'info', 'code' => 'pay_test_mode', 'm' => __( 'Test mode: no real payments are taken. Switch to Live when you are ready.', 'easy-form-builder' ) );
		$a = get_option( 'emsfb_pmt_alert_' . $g->slug() );
		if ( is_array( $a ) && isset( $a['at'], $a['mode'] ) && $a['mode'] === $mode ) $out[] = array( 'level' => 'error', 'code' => 'pay_config_failed', 'm' => Service::owner_alert_text( $g, $a + array( 'error' => '', 'm' => '' ) ) );
		$n = Store::ready() ? Store::count_events( $g->slug(), 'auth_failed', Store::now( -DAY_IN_SECONDS ) ) : 0;
		if ( $n >= 3 ) {
			/* translators: %1$s: gateway name, %2$d: number of refused deliveries */
			$out[] = array( 'level' => 'warning', 'code' => 'pay_webhook_rejected', 'm' => sprintf( __( '%1$s sent %2$d payment confirmations in the last 24 hours that were refused because their signature or token did not match. Check the secret key / webhook secret in the gateway\'s dashboard and here; payments are still checked by the regular job.', 'easy-form-builder' ), $label, $n ) );
		}
		if ( Service::site_is_local() ) {
			/* translators: %s: gateway name */
			$out[] = array( 'level' => 'warning', 'code' => 'pay_webhook_local', 'm' => sprintf( __( 'This site cannot be reached from the internet, so %s cannot send payment confirmations to it. Payments are confirmed when the visitor comes back and by the regular check.', 'easy-form-builder' ), $label ) );
		}
		return $out;
	}

	/** Gateway::settings_notices(), checked: level info|warning|error, plain text. */
	public static function gateway_notices( Gateway $g, Settings $s ) {
		$out = array();
		try { $list = $g->settings_notices( $s ); } catch ( \Throwable $e ) { $list = array(); }
		foreach ( (array) $list as $n ) {
			if ( ! is_array( $n ) || ! isset( $n['m'] ) || ! is_string( $n['m'] ) ) continue;
			$m = Field::text( $n['m'], 500 );
			if ( $m === '' ) continue;
			$out[] = array( 'level' => isset( $n['level'] ) && in_array( $n['level'], array( 'info', 'warning', 'error' ), true ) ? $n['level'] : 'info', 'm' => $m );
			if ( count( $out ) >= 10 ) break;
		}
		return $out;
	}

	/** Gateway::settings_actions(), checked: id [a-z0-9_]{1,30}, a label. */
	public static function gateway_actions( Gateway $g, Settings $s ) {
		$out = array();
		try { $list = $g->settings_actions( $s ); } catch ( \Throwable $e ) { $list = array(); }
		foreach ( (array) $list as $a ) {
			if ( ! is_array( $a ) || ! isset( $a['id'], $a['label'] ) || ! is_string( $a['id'] ) || ! preg_match( '/^[a-z0-9_]{1,30}$/', $a['id'] ) || ! is_string( $a['label'] ) ) continue;
			$out[] = array( 'id' => $a['id'], 'label' => Field::text( $a['label'], 80 ) );
			if ( count( $out ) >= 5 ) break;
		}
		return $out;
	}

	/** The message of a Result for the admin (the gateway's own words when it gave some). */
	public static function result_message( $r, $ok_default ) {
		if ( ! $r instanceof Result ) return $ok_default;
		$m = Field::text( (string) $r->message, 300 );
		if ( $r->ok ) return $m !== '' ? $m : $ok_default;
		/* translators: %s: error code */
		return $m !== '' ? $m : sprintf( __( 'The connection failed (%s).', 'easy-form-builder' ), $r->error );
	}

	public static function save_settings() {
		$slug = isset( $_POST['gateway'] ) ? sanitize_key( wp_unslash( $_POST['gateway'] ) ) : '';
		if ( ! self::can() || ! check_admin_referer( 'efb_pay_settings_' . $slug ) ) wp_die( esc_html__( 'Sorry, you are not allowed to access this page.', 'easy-form-builder' ), 403 );
		$g = Registry::get( $slug );
		if ( ! $g ) wp_die( esc_html__( 'Unknown payment gateway.', 'easy-form-builder' ), 404 );
		$in = array();
		foreach ( (array) $g->settings_schema() as $fd ) {
			if ( empty( $fd['key'] ) ) continue;
			foreach ( ! empty( $fd['per_mode'] ) ? array( $fd['key'] . '__test', $fd['key'] . '__live' ) : array( $fd['key'] ) as $name ) {
				// Secrets as typed: wp_unslash only (sanitize_text_field could change a key).
				if ( isset( $_POST[ $name ] ) && is_string( $_POST[ $name ] ) ) $in[ $name ] = trim( wp_unslash( $_POST[ $name ] ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- checked against the schema in Settings::apply()
			}
		}
		$mode = isset( $_POST['mode'] ) && $_POST['mode'] === 'live' ? 'live' : 'test';
		$s = Settings::load( $slug );
		$refused = $s->apply( (array) $g->settings_schema(), $in, $mode );
		Store::log_event( null, $slug, 'admin', array( 'type' => 'settings_saved', 'note' => 'mode ' . $mode . ' by user ' . get_current_user_id() ) );
		wp_safe_redirect( admin_url( 'admin.php?page=' . self::SLUG . '&tab=gateways&efb_msg=' . ( $refused ? 'refused' : 'saved' ) ) );
		exit;
	}

	public static function ajax() {
		if ( ! self::can() || ! self::available() || ! check_ajax_referer( 'efb_pay_action', 'nonce', false ) ) wp_send_json_error( array( 'm' => __( 'Sorry, you are not allowed to do that.', 'easy-form-builder' ) ), 403 );
		$do = isset( $_POST['do'] ) ? sanitize_key( wp_unslash( $_POST['do'] ) ) : '';
		if ( $do === 'test_connection' ) {
			$slug = isset( $_POST['gateway'] ) ? sanitize_key( wp_unslash( $_POST['gateway'] ) ) : '';
			$mode = isset( $_POST['mode'] ) && $_POST['mode'] === 'live' ? 'live' : 'test';
			$g = Registry::get( $slug );
			if ( ! $g ) wp_send_json_error( array( 'm' => __( 'Unknown payment gateway.', 'easy-form-builder' ) ) );
			$r = $g->test_connection( Settings::load( $slug ), $mode );
			if ( ! $r instanceof Result ) $r = Result::fail( 'bad_result' );
			if ( $r->ok ) wp_send_json_success( array( 'm' => self::result_message( $r, __( 'Connected.', 'easy-form-builder' ) ) ) );
			wp_send_json_error( array( 'm' => self::result_message( $r, '' ) ) );
		}
		if ( $do === 'gateway_action' ) {
			$slug = isset( $_POST['gateway'] ) ? sanitize_key( wp_unslash( $_POST['gateway'] ) ) : '';
			$mode = isset( $_POST['mode'] ) && $_POST['mode'] === 'live' ? 'live' : 'test';
			$act = isset( $_POST['action_id'] ) ? sanitize_key( wp_unslash( $_POST['action_id'] ) ) : '';
			$g = Registry::get( $slug );
			if ( ! $g ) wp_send_json_error( array( 'm' => __( 'Unknown payment gateway.', 'easy-form-builder' ) ) );
			$s = Settings::load( $slug );
			if ( ! in_array( $act, array_column( self::gateway_actions( $g, $s ), 'id' ), true ) ) wp_send_json_error( array( 'm' => __( 'Unknown action.', 'easy-form-builder' ) ) );
			try { $r = $g->run_settings_action( $act, $s, $mode ); } catch ( \Throwable $e ) { $r = Result::fail( 'exception' ); }
			if ( ! $r instanceof Result ) $r = Result::fail( 'bad_result' );
			Store::log_event( null, $slug, 'admin', array( 'type' => 'settings_action', 'note' => $act . ' ' . ( $r->ok ? 'ok' : $r->error ) . ' by user ' . get_current_user_id() ) );
			if ( $r->ok ) wp_send_json_success( array( 'm' => self::result_message( $r, __( 'Done.', 'easy-form-builder' ) ) ) );
			wp_send_json_error( array( 'm' => self::result_message( $r, '' ) ) );
		}
		$p = Store::get( isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0 );
		if ( ! $p ) wp_send_json_error( array( 'm' => __( 'This payment does not exist.', 'easy-form-builder' ) ) );
		if ( ! Registry::get( $p['gateway'] ) ) {
			/* translators: %s: name of the payment add-on (e.g. Stripe) */
			wp_send_json_error( array( 'm' => sprintf( __( 'Turn the %s add-on on first: this payment is checked and refunded through it.', 'easy-form-builder' ), Readiness::addon_name( $p['gateway'] ) ), 'code' => 'pay_gateway_inactive' ) );
		}
		if ( $do === 'recheck' ) {
			$r = Service::refresh( $p, 'admin' );
			$now = Store::get( $p['id'] );
			if ( ! $r['ok'] ) wp_send_json_error( array( 'm' => sprintf( /* translators: %s: the error the payment service gave */ __( 'The gateway could not be reached (%s).', 'easy-form-builder' ), $r['error'] ) ) );
			wp_send_json_success( array( 'm' => self::status_label( $now['status'] ), 'status' => $now['status'] ) );
		}
		if ( $do === 'refund' ) {
			$amt = isset( $_POST['amount'] ) ? Money::from_decimal( sanitize_text_field( wp_unslash( $_POST['amount'] ) ), $p['currency'], $p['gateway'] ) : null;
			if ( $amt === null ) wp_send_json_error( array( 'm' => __( 'Please choose a valid amount.', 'easy-form-builder' ) ) );
			$r = Service::refund( $p, $amt, isset( $_POST['reason'] ) ? sanitize_text_field( wp_unslash( $_POST['reason'] ) ) : '', get_current_user_id() );
			if ( ! $r['ok'] ) {
				$u = $r['error'] === 'unsupported' ? self::unsupported_refund_text( Registry::get( $p['gateway'] ), $p ) : '';
				wp_send_json_error( array( 'm' => $u !== '' ? $u : Service::refund_message( $r, $p ), 'code' => 'refund_' . $r['error'] ) );
			}
			wp_send_json_success( array( 'm' => ! empty( $r['pending'] ) ? __( 'The refund was requested. It counts once the gateway confirms it.', 'easy-form-builder' ) : __( 'Refunded', 'easy-form-builder' ), 'status' => Store::get( $p['id'] )['status'] ) );
		}
		if ( $do === 'mark_refunded' ) {
			$amt = isset( $_POST['amount'] ) ? Money::from_decimal( sanitize_text_field( wp_unslash( $_POST['amount'] ) ), $p['currency'], $p['gateway'] ) : null;
			if ( $amt === null ) wp_send_json_error( array( 'm' => __( 'Please choose a valid amount.', 'easy-form-builder' ) ) );
			$r = Service::record_external_refund( $p, $amt, isset( $_POST['reason'] ) ? sanitize_text_field( wp_unslash( $_POST['reason'] ) ) : '', get_current_user_id() );
			if ( ! $r['ok'] ) wp_send_json_error( array( 'm' => Service::refund_message( $r, $p ), 'code' => 'refund_' . $r['error'] ) );
			wp_send_json_success( array( 'm' => __( 'Recorded as refunded.', 'easy-form-builder' ), 'status' => Store::get( $p['id'] )['status'] ) );
		}
		if ( $do === 'refund_settle' ) {
			$to = isset( $_POST['to'] ) && $_POST['to'] === 'refunded' ? 'refunded' : 'failed';
			$ok = Service::settle_refund( $p, isset( $_POST['refund'] ) ? absint( $_POST['refund'] ) : 0, $to, 'admin', get_current_user_id() );
			if ( ! $ok ) wp_send_json_error( array( 'm' => __( 'This refund is no longer waiting.', 'easy-form-builder' ) ) );
			wp_send_json_success( array( 'm' => $to === 'refunded' ? __( 'Refunded', 'easy-form-builder' ) : __( 'Declined', 'easy-form-builder' ), 'status' => Store::get( $p['id'] )['status'] ) );
		}
		wp_send_json_error( array( 'm' => __( 'Unknown action.', 'easy-form-builder' ) ) );
	}

	/* ------------------------------------------------------------------ moved forms (QA4-003) */

	/** "Moved forms": the forms on the add-ons' new fields, and "Move every form back" before going back to an older version. */
	private static function downgrade_tab( array $ids ) {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- the message after the redirect
		$done = isset( $_GET['efb_back'] ) ? absint( $_GET['efb_back'] ) : -1;
		$kept = isset( $_GET['efb_left'] ) ? absint( $_GET['efb_left'] ) : 0;
		// phpcs:enable
		echo '<section class="efb-pay-card efb-pay-downgrade" aria-labelledby="efbPayDowngradeH"><h2 id="efbPayDowngradeH">' . esc_html__( 'Before going back to an older version', 'easy-form-builder' ) . '</h2>';
		if ( $done >= 0 ) {
			/* translators: %s: number of forms */
			echo '<div class="notice notice-success inline" role="status"><p>' . esc_html( sprintf( _n( '%s form was moved back to its older payment field.', '%s forms were moved back to their older payment fields.', $done, 'easy-form-builder' ), number_format_i18n( $done ) ) ) . '</p></div>';
		}
		if ( $kept > 0 ) {
			/* translators: %s: number of forms */
			echo '<div class="notice notice-warning inline"><p>' . esc_html( sprintf( _n( '%s form could not be moved back: its payment field was changed after the move. Open the form\'s versions in the builder to go back.', '%s forms could not be moved back: their payment fields were changed after the move. Open each form\'s versions in the builder to go back.', $kept, 'easy-form-builder' ), number_format_i18n( $kept ) ) ) . '</p></div>';
		}
		if ( ! $ids ) {
			echo '<p>' . esc_html__( 'No form uses the payment add-ons\' new fields now. An older version of Easy Form Builder can show every payment form.', 'easy-form-builder' ) . '</p></section>';
			return;
		}
		echo '<p>' . esc_html( Legacy_Migration::downgrade_text( count( $ids ) ) ) . '</p>';
		echo '<p class="description">' . esc_html__( 'Moving a form back changes only its payment field: edits made since the move are kept, and the form can be moved again later from Settings › Payments.', 'easy-form-builder' ) . '</p><ul class="efb-pay-moved">';
		$log = Legacy_Migration::log_all();
		foreach ( $ids as $id ) {
			$name = isset( $log['forms'][ $id ]['name'] ) ? wp_strip_all_tags( str_replace( '@efb@dq#', '"', (string) $log['forms'][ $id ]['name'] ) ) : '';
			echo '<li><bdi><a href="' . esc_url( admin_url( 'admin.php?page=Emsfb&state=edit-form&id=' . (int) $id ) ) . '">' . esc_html( ( $name !== '' ? $name : __( 'Form', 'easy-form-builder' ) ) . ' (#' . (int) $id . ')' ) . '</a></bdi></li>';
		}
		echo '</ul><form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( 'efb_pay_legacy_rollback_all' );
		echo '<input type="hidden" name="action" value="efb_pay_legacy_rollback_all"><p><button class="button button-primary" data-efb-confirm="' . esc_attr__( 'Move every form back to its older payment field? Visitors then pay through the older fields again.', 'easy-form-builder' ) . '">' . esc_html__( 'Move every form back', 'easy-form-builder' ) . '</button></p></form></section>';
		wp_print_inline_script_tag( '(function(){var b=document.querySelector(".efb-pay-downgrade [data-efb-confirm]");if(b)b.form.addEventListener("submit",function(e){if(!window.confirm(b.getAttribute("data-efb-confirm")))e.preventDefault();});})();' );
	}

	/** admin-post: every moved form back (Legacy_Migration::rollback_all()), then the tab with the counts. */
	public static function rollback_all() {
		if ( ! current_user_can( 'manage_options' ) || ! check_admin_referer( 'efb_pay_legacy_rollback_all' ) ) wp_die( esc_html__( 'Sorry, you are not allowed to access this page.', 'easy-form-builder' ), 403 );
		$r = Legacy_Migration::rollback_all( 'admin' );
		wp_safe_redirect( admin_url( 'admin.php?page=' . self::SLUG . '&tab=downgrade&efb_back=' . count( $r['done'] ) . '&efb_left=' . count( $r['failed'] ) ) );
		exit;
	}

	private static function style() {
		echo '<style>.efb-pay-admin .efb-pay-stats{display:flex;gap:1rem;flex-wrap:wrap;margin:1rem 0}.efb-pay-stat{background:#fff;border:1px solid #dcdcde;border-radius:6px;padding:.6rem 1rem;display:flex;flex-direction:column}.efb-pay-filters{display:flex;flex-wrap:wrap;gap:.5rem;align-items:end;margin:.5rem 0 1rem}.efb-pay-badge{display:inline-block;padding:.1rem .5rem;border-radius:999px;font-size:12px;font-weight:600;background:#e9ecef;color:#1d2327}.efb-pay-success{background:#d1e7dd;color:#0a3622}.efb-pay-warning{background:#fff3cd;color:#664d03}.efb-pay-danger{background:#f8d7da;color:#58151c}.efb-pay-info{background:#cff4fc;color:#055160}.efb-pay-dark{background:#495057;color:#fff}.efb-pay-test{background:#fff3cd;color:#664d03;padding:0 .4rem;border-radius:4px;font-weight:700}.efb-pay-card{background:#fff;border:1px solid #dcdcde;border-radius:8px;padding:0 1rem 1rem;margin:1rem 0;max-width:60rem}.efb-pay-kv th{width:14rem}.efb-pay-err{color:#b32d2e}.efb-pay-events pre{white-space:pre-wrap;max-height:16rem;overflow:auto}.efb-pay-pages{margin:1rem 0}'
			// UX-01: the readiness summary (a card, not a notice: it is about these screens' work).
			. '.efb-pay-ready{background:#fcf9e8;border:1px solid #dcdcde;border-inline-start:4px solid #dba617;border-radius:6px;padding:.25rem 1rem .5rem;margin:1rem 0;max-width:60rem}.efb-pay-ready h2{font-size:1.1em;margin:.75rem 0 .25rem}.efb-pay-ready ul{list-style:disc;padding-inline-start:1.25rem;margin-inline-start:0}.efb-pay-ready summary{cursor:pointer;color:#2271b1;min-height:24px}'
			// SET-01: the "Older payments" tab.
			. '.efb-pay-older ul{display:flex;flex-wrap:wrap;gap:8px;margin:12px 0}.efb-pay-older .button{min-height:36px;display:inline-flex;align-items:center}.efb-pay-older .description{max-width:52rem}'
			// F16 rule on a right-to-left screen: a sentence keeps its own direction (English, not translated yet: its full stop
			// stays at its end) and starts at the screen's start.
			. '.efb-pay-admin :is(p,.notice p,.description,li,label,.efb-pay-msg){unicode-bidi:plaintext}.rtl .efb-pay-admin :is(p,.notice p,.description,li,.efb-pay-msg){text-align:right}'
			// QA3-008: the list at phone width (WordPress' list-table cards below 783 px); the filters and the details fit too.
			. '.efb-pay-table .column-date{width:9.5rem}.efb-pay-table .column-form{width:4rem}.efb-pay-table .column-mode{width:4.5rem}.efb-pay-table code{white-space:nowrap}.efb-pay-table td{overflow-wrap:anywhere}'
			. '@media (max-width:782px){.efb-pay-admin .efb-pay-filters label{display:flex;flex-direction:column;flex:1 1 9rem;min-width:0}.efb-pay-admin .efb-pay-filters :is(select,input){max-width:100%;min-height:40px}.efb-pay-table .column-date{width:auto}.efb-pay-table td code{white-space:normal}.efb-pay-kv th{width:auto}.efb-pay-kv td{overflow-wrap:anywhere}.efb-pay-admin .nav-tab{margin-block-end:4px}}'
			. '.efb-pay-moved{list-style:disc;padding-inline-start:1.25rem;margin-inline-start:0}</style>';
	}

	private static function script() {
		wp_print_inline_script_tag( '(function(){var root=document.getElementById("efbPayAdmin");if(!root)return;'
			. 'root.addEventListener("change",function(e){var r=e.target;if(r&&r.getAttribute&&r.getAttribute("data-efb-live")&&r.checked&&!window.confirm(r.getAttribute("data-efb-live"))){var t=r.form.querySelector(\'input[name="mode"][value="test"]\');if(t)t.checked=true;}});'
			. 'root.addEventListener("click",function(e){var b=e.target.closest("[data-efb-pay]");if(!b)return;var box=b.closest(".efb-pay-actions")||b.closest("p");var msg=box?box.querySelector(".efb-pay-msg"):null;var d=new FormData();d.append("action","efb_pay_action");d.append("do",b.getAttribute("data-efb-pay"));'
			. 'var a=b.closest(".efb-pay-actions");d.append("nonce",(a&&a.getAttribute("data-nonce"))||b.getAttribute("data-nonce")||"");if(a)d.append("id",a.getAttribute("data-id"));if(b.getAttribute("data-gateway")){d.append("gateway",b.getAttribute("data-gateway"));d.append("mode",b.getAttribute("data-mode"));}'
			. 'var act=b.getAttribute("data-efb-pay");if(b.getAttribute("data-action-id"))d.append("action_id",b.getAttribute("data-action-id"));'
			. 'if(act==="refund"){var amt=document.getElementById("efbPayRefundAmt");var v=amt?amt.value:"";if(!window.confirm(b.getAttribute("data-confirm").replace("%1$s",v+" "+b.getAttribute("data-currency"))))return;d.append("amount",v);}'
			. 'if(act==="mark_refunded"){var ea=document.getElementById("efbPayExtAmt"),er=document.getElementById("efbPayExtReason");var ev=ea?ea.value:"";if(er&&!er.value.trim()){er.focus();if(er.reportValidity)er.reportValidity();return;}if(!window.confirm(b.getAttribute("data-confirm").replace("%1$s",ev+" "+b.getAttribute("data-currency"))))return;d.append("amount",ev);d.append("reason",er?er.value:"");}'
			. 'if(act==="refund_settle"){if(!window.confirm(b.getAttribute("data-confirm")))return;d.append("refund",b.getAttribute("data-refund"));d.append("to",b.getAttribute("data-to"));}'
			. 'b.disabled=true;fetch(' . wp_json_encode( admin_url( 'admin-ajax.php' ) ) . ',{method:"POST",credentials:"same-origin",body:d}).then(function(r){return r.json();}).then(function(j){b.disabled=false;if(msg)msg.textContent=(j&&j.data&&j.data.m)||"";if(j&&j.success&&act!=="test_connection"&&act!=="gateway_action")setTimeout(function(){location.reload();},700);}).catch(function(){b.disabled=false;});});})();' );
	}
}
