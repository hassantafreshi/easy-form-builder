<?php
/**
 * Admin UI for EFB Human Shield.
 *
 * @package Easy_Form_Builder
 * @subpackage Human_Shield
 */

namespace Emsfb;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Emsfb_Human_Shield_Admin {
	const CAPABILITY = 'Emsfb_human_shield_efb';

	/** @var Emsfb_Human_Shield */
	private $shield;

	private $page_hook = '';

	public function __construct( Emsfb_Human_Shield $shield ) {
		$this->shield = $shield;
		add_action( 'admin_menu', array( $this, 'add_menu' ), 22 );
		add_action( 'wp_ajax_efb_human_shield_save_settings', array( $this, 'ajax_save_settings' ) );
		add_action( 'wp_ajax_efb_human_shield_load_logs', array( $this, 'ajax_load_logs' ) );
		add_action( 'wp_ajax_efb_human_shield_clear_logs', array( $this, 'ajax_clear_logs' ) );
		add_action( 'wp_ajax_efb_human_shield_export_logs', array( $this, 'ajax_export_logs' ) );
	}

	public function add_menu() {
		$this->ensure_capability();

		$this->page_hook = add_submenu_page(
			'Emsfb',
			esc_html__( 'Form Security & Spam Protection', 'easy-form-builder' ),
			'<span><i class="efb bi-shield-check" style="font-size:14px;margin-right:3px"></i>' . esc_html__( 'Security & Spam Protection', 'easy-form-builder' ) . '</span>',
			self::CAPABILITY,
			'Emsfb_human_shield_efb',
			array( $this, 'render' )
		);

		if ( $this->page_hook ) {
			add_action( 'load-' . $this->page_hook, array( $this, 'enqueue_assets' ) );
		}
	}

	private function ensure_capability() {
		if ( get_option( 'emsfb_human_shield_cap_added' ) ) {
			return;
		}

		$role = get_role( 'administrator' );
		if ( $role ) {
			$role->add_cap( self::CAPABILITY );
		}

		update_option( 'emsfb_human_shield_cap_added', true, false );
	}

	public function enqueue_assets() {
		if ( defined( 'EMSFB_PLUGIN_URL' ) ) {
			wp_enqueue_style(
				'efb-human-shield-bootstrap-icons',
				EMSFB_PLUGIN_URL . 'includes/admin/assets/css/bootstrap-icons-efb.css',
				array(),
				defined( 'EMSFB_PLUGIN_VERSION' ) ? EMSFB_PLUGIN_VERSION : EFB_HUMAN_SHIELD_VERSION
			);
		}

		wp_enqueue_style(
			'efb-human-shield-admin',
			EFB_HUMAN_SHIELD_URL . 'assets/css/human-shield-admin-efb.css',
			array(),
			EFB_HUMAN_SHIELD_VERSION
		);

		wp_enqueue_script(
			'efb-human-shield-admin',
			EFB_HUMAN_SHIELD_URL . 'assets/js/human-shield-admin-efb.js',
			array(),
			EFB_HUMAN_SHIELD_VERSION,
			true
		);

		wp_localize_script(
			'efb-human-shield-admin',
			'EFBHumanShieldAdmin',
			array(
				'ajaxUrl'      => admin_url( 'admin-ajax.php' ),
				'nonce'        => wp_create_nonce( 'efb_human_shield_admin' ),
				'rtl'          => is_rtl() ? 1 : 0,
				'settings'     => $this->shield->get_settings(),
				'requirements' => $this->shield->requirements(),
				'stats'        => $this->shield->stats(),
				'hourly'       => $this->shield->hourly_stats(),
				'logs'         => $this->shield->recent_events( 20 ),
				'version'      => EFB_HUMAN_SHIELD_VERSION,
				'text'         => $this->labels(),
			)
		);
	}

	private function labels() {
		return array(
			'title'          => esc_html__( 'Form Security & Spam Protection', 'easy-form-builder' ),
			'subtitle'       => esc_html__( 'Behavior-based anti-spam and cost protection', 'easy-form-builder' ),
			'overview'       => esc_html__( 'Overview', 'easy-form-builder' ),
			'protection'     => esc_html__( 'Protection', 'easy-form-builder' ),
			'paidLimits'     => esc_html__( 'Paid Limits', 'easy-form-builder' ),
			'logs'           => esc_html__( 'Logs', 'easy-form-builder' ),
			'system'         => esc_html__( 'System', 'easy-form-builder' ),
			'save'           => esc_html__( 'Save Settings', 'easy-form-builder' ),
			'saving'         => esc_html__( 'Saving...', 'easy-form-builder' ),
			'saved'          => esc_html__( 'Settings saved.', 'easy-form-builder' ),
			'failed'         => esc_html__( 'Request failed.', 'easy-form-builder' ),
			'enabled'        => esc_html__( 'Enabled', 'easy-form-builder' ),
			'mode'           => esc_html__( 'Mode', 'easy-form-builder' ),
			'monitor'        => esc_html__( 'Monitor only', 'easy-form-builder' ),
			'softBlock'      => esc_html__( 'Soft block', 'easy-form-builder' ),
			'strict'         => esc_html__( 'Strict', 'easy-form-builder' ),
			'clearLogs'      => esc_html__( 'Clear Logs', 'easy-form-builder' ),
			'configMissing'  => esc_html__( 'The Security & Spam Protection admin data could not be loaded. Another plugin may be blocking the script settings, or the page was cached. Reload the page; if it persists, disable admin script optimization for this page.', 'easy-form-builder' ),
			'renderFailed'   => esc_html__( 'The Security & Spam Protection panel failed to render. Reload the page or check the browser console.', 'easy-form-builder' ),
			'missingRequired' => esc_html__( 'Protection is paused: required PHP functions are disabled on this server: %s. Ask your host to remove them from the disable_functions line in php.ini. Your forms keep working without protection until then.', 'easy-form-builder' ),
			'missingTables'  => esc_html__( 'Protection is paused: the add-on database tables could not be created. Check that the database user can CREATE tables, then reload this page.', 'easy-form-builder' ),
		);
	}

	public function render() {
		if ( ! current_user_can( self::CAPABILITY ) && ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'easy-form-builder' ) );
		}

		?>
		<div class="efb-human-shield-wrap" id="efb-human-shield-app">
			<div class="efb-hs-loading">
				<span class="efb-hs-spinner"></span>
				<span><?php echo esc_html__( 'Loading Security & Spam Protection...', 'easy-form-builder' ); ?></span>
			</div>
			<noscript>
				<div style="margin:24px;padding:16px 20px;border:1px solid #d63638;border-radius:8px;background:#fcf0f1;color:#8a1f21;">
					<?php echo esc_html__( 'The Security & Spam Protection panel needs JavaScript. Enable JavaScript in your browser to manage these settings; the protection itself keeps running on the server.', 'easy-form-builder' ); ?>
				</div>
			</noscript>
		</div>
		<?php
	}

	public function ajax_save_settings() {
		$this->assert_ajax_access();

		$raw = isset( $_POST['settings'] ) ? wp_unslash( $_POST['settings'] ) : '';
		$settings = $this->shield->json_decode_assoc( $raw );
		$settings = $this->shield->update_settings( $settings );

		wp_send_json_success(
			array(
				'settings' => $settings,
				'stats'    => $this->shield->stats(),
			)
		);
	}

	public function ajax_load_logs() {
		$this->assert_ajax_access();
		wp_send_json_success(
			array(
				'logs'   => $this->shield->recent_events( 50 ),
				'stats'  => $this->shield->stats(),
				'hourly' => $this->shield->hourly_stats(),
			)
		);
	}

	/**
	 * Streams the recent event log as a CSV download (admin-ajax GET link).
	 */
	public function ajax_export_logs() {
		$this->assert_ajax_access();

		$rows = array();
		if ( $this->shield->tables_ready() ) {
			$table = $this->shield->table_name( 'events' );
			$rows = $this->shield->db->get_results(
				"SELECT created_at, route, form_id, action, decision, score, reason_codes, cost_channel, cost_suppressed, ip_hash, token_jti FROM {$table} ORDER BY id DESC LIMIT 5000",
				ARRAY_A
			);
		}

		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=efb-security-log-' . gmdate( 'Ymd-His' ) . '.csv' );

		$out = fopen( 'php://output', 'w' );
		// UTF-8 BOM so Excel opens the file correctly.
		fwrite( $out, "\xEF\xBB\xBF" );
		fputcsv( $out, array( 'created_at', 'route', 'form_id', 'action', 'decision', 'score', 'reason_codes', 'cost_channel', 'cost_suppressed', 'ip_hash', 'token_jti' ) );

		foreach ( (array) $rows as $row ) {
			$cells = array();
			foreach ( $row as $cell ) {
				$cell = (string) $cell;
				// Neutralize CSV formula injection in spreadsheet apps.
				if ( '' !== $cell && in_array( $cell[0], array( '=', '+', '-', '@' ), true ) ) {
					$cell = "'" . $cell;
				}
				$cells[] = $cell;
			}
			fputcsv( $out, $cells );
		}

		fclose( $out );
		exit;
	}

	public function ajax_clear_logs() {
		$this->assert_ajax_access();

		if ( $this->shield->tables_ready() ) {
			$this->shield->db->query( 'DELETE FROM ' . $this->shield->table_name( 'events' ) );
		}

		wp_send_json_success(
			array(
				'logs'  => array(),
				'stats' => $this->shield->stats(),
			)
		);
	}

	private function assert_ajax_access() {
		if ( ! check_ajax_referer( 'efb_human_shield_admin', 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Invalid security token.', 'easy-form-builder' ) ), 403 );
		}

		if ( ! current_user_can( self::CAPABILITY ) && ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Permission denied.', 'easy-form-builder' ) ), 403 );
		}
	}
}
