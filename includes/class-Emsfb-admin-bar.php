<?php
/**
 * Easy Form Builder development-mode control for the WordPress admin bar.
 *
 * This is intentionally separate from the large admin controller because the
 * WordPress toolbar can also be shown on the front end.
 */

namespace Emsfb;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Admin_Bar_Development_Mode {

	/**
	 * Register only lightweight hooks. Assets are loaded only when the toolbar
	 * control is actually visible to an authorized user.
	 */
	public function __construct() {
		add_action( 'admin_bar_menu', array( $this, 'add_node' ), 100 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'wp_ajax_efb_toggle_development_mode', array( $this, 'toggle_development_mode' ) );
	}

	/**
	 * Keep visibility and authorization aligned with the Easy Form Builder
	 * dashboard, including sites that assign the custom Emsfb capability.
	 *
	 * @return bool
	 */
	private function current_user_can_manage_mode() {
		return is_user_logged_in()
			&& ( current_user_can( 'manage_options' ) || current_user_can( 'Emsfb' ) );
	}

	/**
	 * Add the compact status/action item to the right side of the toolbar.
	 * JavaScript intercepts the link for the one-click action; its URL remains
	 * a useful fallback to the plugin panel when JavaScript is unavailable.
	 *
	 * @param \WP_Admin_Bar $wp_admin_bar The WordPress admin-bar object.
	 * @return void
	 */
	public function add_node( $wp_admin_bar ) {
		if ( ! $this->current_user_can_manage_mode() ) {
			return;
		}

		$enabled = '1' === (string) get_option( 'emsfb_dev_mode', '0' );
		$label   = $enabled
			? esc_html__( 'Sandbox: On', 'easy-form-builder' )
			: esc_html__( 'Sandbox: Off', 'easy-form-builder' );
		$tooltip = $enabled
			? esc_attr__( 'Development Mode is on. Click to turn it off.', 'easy-form-builder' )
			: esc_attr__( 'Development Mode is off. Click to turn it on.', 'easy-form-builder' );
		$logo_url = EMSFB_PLUGIN_URL . 'includes/admin/assets/image/logo-gray.png';

		$title = sprintf(
			'<span class="efb-admin-bar-dev-mode__content"><img class="efb-admin-bar-dev-mode__logo" src="%1$s" alt="" title="%2$s" aria-hidden="true" /><span class="efb-admin-bar-dev-mode__indicator" aria-hidden="true"></span><span class="efb-admin-bar-dev-mode__label">%3$s</span></span>',
			esc_url( $logo_url ),
			esc_attr__( 'Easy Form Builder', 'easy-form-builder' ),
			esc_html( $label )
		);

		$wp_admin_bar->add_node(
			array(
				'id'     => 'efb-development-mode',
				'parent' => 'top-secondary',
				'title'  => $title,
				'href'   => admin_url( 'admin.php?page=Emsfb' ),
				'meta'   => array(
					'class' => 'efb-admin-bar-dev-mode' . ( $enabled ? ' is-enabled' : ' is-disabled' ),
					'title' => $tooltip,
				),
			)
		);
	}

	/**
	 * Load one small, dependency-free asset pair only for users who can use the
	 * toolbar item. This keeps public requests and unrelated admin pages lean.
	 *
	 * @param string $hook Current admin page hook, when applicable.
	 * @return void
	 */
	public function enqueue_assets( $hook = '' ) {
		if ( ! $this->current_user_can_manage_mode() || ! is_admin_bar_showing() ) {
			return;
		}

		wp_enqueue_style(
			'efb-admin-bar-dev-mode',
			EMSFB_PLUGIN_URL . 'includes/admin/assets/css/admin-bar-dev-mode-efb.css',
			array(),
			EMSFB_PLUGIN_VERSION
		);

		wp_enqueue_script(
			'efb-admin-bar-dev-mode',
			EMSFB_PLUGIN_URL . 'includes/admin/assets/js/admin-bar-dev-mode-efb.js',
			array(),
			EMSFB_PLUGIN_VERSION,
			true
		);

		wp_localize_script(
			'efb-admin-bar-dev-mode',
			'efbAdminBarDevMode',
			array(
				'ajaxUrl'     => admin_url( 'admin-ajax.php' ),
				'nonce'       => wp_create_nonce( 'efb_toggle_development_mode' ),
				'labels'      => array(
					'on'       => esc_html__( 'Sandbox: On', 'easy-form-builder' ),
					'off'      => esc_html__( 'Sandbox: Off', 'easy-form-builder' ),
					'updating' => esc_html__( 'Updating...', 'easy-form-builder' ),
					'reload'   => esc_html__( 'Reload now', 'easy-form-builder' ),
					'close'    => esc_html__( 'Close notification', 'easy-form-builder' ),
					'error'    => esc_html__( 'Unable to change Development Mode. Please try again.', 'easy-form-builder' ),
				),
			)
		);
	}

	/**
	 * Toggle the persisted state. The server determines the next state rather
	 * than trusting a browser-provided value, which prevents stale tabs from
	 * setting an unexpected mode.
	 *
	 * @return void
	 */
	public function toggle_development_mode() {
		if ( ! check_ajax_referer( 'efb_toggle_development_mode', 'nonce', false ) || ! $this->current_user_can_manage_mode() ) {
			wp_send_json_error(
				array(
					'message' => esc_html__( 'You do not have permission to change Development Mode.', 'easy-form-builder' ),
				),
				403
			);
		}

		$current = '1' === (string) get_option( 'emsfb_dev_mode', '0' );
		$enabled = ! $current;

		if ( ! update_option( 'emsfb_dev_mode', $enabled ? '1' : '0' ) ) {
			wp_send_json_error(
				array(
					'message' => esc_html__( 'Unable to change Development Mode. Please try again.', 'easy-form-builder' ),
				),
				500
			);
		}

		$message = $enabled
			? esc_html__( 'Development Mode is enabled. Reload this page to use sandbox services.', 'easy-form-builder' )
			: esc_html__( 'Development Mode is disabled. Reload this page to use production services.', 'easy-form-builder' );

		wp_send_json_success(
			array(
				'enabled' => $enabled,
				'message' => $message,
			)
		);
	}
}
