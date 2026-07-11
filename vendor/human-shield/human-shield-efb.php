<?php
/**
 * EFB Human Shield addon bootstrap.
 *
 * This file is intentionally self-contained and is not wired into the core
 * plugin yet. Include it from Easy Form Builder only when the addon is enabled.
 *
 * @package Easy_Form_Builder
 * @subpackage Human_Shield
 */

namespace Emsfb;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( defined( 'EFB_HUMAN_SHIELD_LOADED' ) ) {
	return;
}

define( 'EFB_HUMAN_SHIELD_LOADED', true );
define( 'EFB_HUMAN_SHIELD_VERSION', '0.1.0' );

if ( ! defined( 'EFB_HUMAN_SHIELD_PATH' ) ) {
	define( 'EFB_HUMAN_SHIELD_PATH', trailingslashit( __DIR__ ) );
}

if ( ! defined( 'EFB_HUMAN_SHIELD_URL' ) ) {
	$base_url = defined( 'EMSFB_PLUGIN_URL' ) ? EMSFB_PLUGIN_URL . 'vendor/human-shield/' : plugin_dir_url( __FILE__ );
	define( 'EFB_HUMAN_SHIELD_URL', $base_url );
}

$efb_human_shield_files = array(
	'class-Emsfb-human-shield.php',
	'class-Emsfb-human-shield-rate-limiter.php',
	'class-Emsfb-human-shield-detector.php',
	'class-Emsfb-human-shield-rest.php',
	'class-Emsfb-human-shield-notification-gate.php',
	'class-Emsfb-human-shield-admin.php',
);

foreach ( $efb_human_shield_files as $efb_human_shield_file ) {
	$efb_human_shield_path = EFB_HUMAN_SHIELD_PATH . $efb_human_shield_file;
	if ( file_exists( $efb_human_shield_path ) ) {
		require_once $efb_human_shield_path;
	}
}

$efb_human_shield_required_classes = array(
	'Emsfb_Human_Shield',
	'Emsfb_Human_Shield_Rate_Limiter',
	'Emsfb_Human_Shield_Detector',
	'Emsfb_Human_Shield_Rest',
	'Emsfb_Human_Shield_Notification_Gate',
	'Emsfb_Human_Shield_Admin',
);

$efb_human_shield_missing_classes = array();
foreach ( $efb_human_shield_required_classes as $efb_human_shield_class ) {
	if ( ! class_exists( __NAMESPACE__ . '\\' . $efb_human_shield_class ) ) {
		$efb_human_shield_missing_classes[] = $efb_human_shield_class;
	}
}

if ( empty( $efb_human_shield_missing_classes ) ) {
	Emsfb_Human_Shield::instance();
} elseif ( is_admin() ) {
	add_action(
		'admin_notices',
		function () use ( $efb_human_shield_missing_classes ) {
			?>
			<div class="notice notice-error">
				<p>
					<strong><?php echo esc_html__( 'Form Security & Spam Protection could not load.', 'easy-form-builder' ); ?></strong>
					<?php echo esc_html__( 'Some addon files or classes are missing:', 'easy-form-builder' ); ?>
					<code><?php echo esc_html( implode( ', ', $efb_human_shield_missing_classes ) ); ?></code>
				</p>
			</div>
			<?php
		}
	);
}
