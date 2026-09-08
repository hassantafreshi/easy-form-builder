<?php
/**
 * Environment for the end-to-end review reward test.
 *
 * This is the only test that runs the feature the way a shipped release runs
 * it: a real Free site, a real HTTP request to the White Studio service, a real
 * Stripe coupon (test mode), and a real email. Nothing is stubbed.
 *
 * Two things must be arranged for that to be possible on one machine, and both
 * are undone again by `teardown`:
 *
 *   1. The customer site points at the local ws install instead of
 *      whitestudio.team, through the filter the feature ships with.
 *   2. The ws install captures wp_mail() to a folder instead of sending, so the
 *      message can be read and asserted on. It is still fully composed and
 *      still handed to wp_mail - only the transport is intercepted.
 *
 * Run: C:\xampp\php\php.exe tests/e2e-review-reward-env.php setup|teardown|state
 */

$mode = isset( $argv[1] ) ? $argv[1] : '';

define( 'WP_USE_THEMES', false );

$WP_DIR   = 'c:/xampp/htdocs/wp';
$WS_DIR   = 'c:/xampp/htdocs/ws';
$MAILBOX  = $WS_DIR . '/wp-content/uploads/efb-e2e-mail';
$WP_MU    = $WP_DIR . '/wp-content/mu-plugins/efb-e2e-review-endpoint.php';
$WS_MU    = $WS_DIR . '/wp-content/mu-plugins/efb-e2e-mail-capture.php';
$MARKER   = 'efb_e2e_review_state';

require $WP_DIR . '/wp-load.php';

/** The mu-plugin that repoints the customer site at the local service. */
function efb_e2e_endpoint_mu() {
	return <<<'PHP'
<?php
/**
 * Plugin Name: EFB e2e - local review reward endpoint
 *
 * TEST ONLY. Points the review reward claim at the ws install on this machine
 * instead of whitestudio.team. Removed by tests/e2e-review-reward-env.php.
 */
add_filter( 'emsfb_review_reward_endpoint_efb', function () {
	return 'http://127.0.0.1/ws/wp-json/payefb/v1/review-reward';
} );
PHP;
}

/** The mu-plugin that captures mail on the ws side. */
function efb_e2e_capture_mu( $mailbox ) {
	$dir = var_export( $mailbox, true );

	return <<<PHP
<?php
/**
 * Plugin Name: EFB e2e - mail capture
 *
 * TEST ONLY. Writes every outgoing message to a folder instead of sending it.
 * The message is still composed in full and still reaches wp_mail(); only the
 * transport is intercepted, so what lands here is what would have been sent.
 *
 * Removed by tests/e2e-review-reward-env.php.
 */
add_filter( 'pre_wp_mail', function ( \$null, \$atts ) {
	\$dir = {$dir};

	if ( ! is_dir( \$dir ) ) {
		wp_mkdir_p( \$dir );
	}

	\$to      = is_array( \$atts['to'] ) ? implode( ',', \$atts['to'] ) : (string) \$atts['to'];
	\$name    = gmdate( 'His' ) . '-' . substr( md5( \$to . \$atts['subject'] . microtime() ), 0, 8 );
	\$headers = is_array( \$atts['headers'] ) ? implode( "\\n", \$atts['headers'] ) : (string) \$atts['headers'];

	file_put_contents(
		\$dir . '/' . \$name . '.json',
		wp_json_encode(
			array(
				'to'      => \$to,
				'subject' => (string) \$atts['subject'],
				'headers' => \$headers,
				'sent_at' => gmdate( 'c' ),
			),
			JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT
		)
	);

	file_put_contents( \$dir . '/' . \$name . '.html', (string) \$atts['message'] );

	return true;
} , 10, 2 );
PHP;
}

/**
 * Empty a directory of the files this test writes.
 *
 * @param string $dir Directory.
 * @return void
 */
function efb_e2e_empty( $dir ) {
	if ( ! is_dir( $dir ) ) {
		return;
	}

	foreach ( glob( $dir . '/*.{json,html}', GLOB_BRACE ) as $file ) {
		@unlink( $file ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
	}
}

switch ( $mode ) {
	case 'setup':
		if ( ! get_option( $MARKER ) ) {
			update_option(
				$MARKER,
				array(
					'emsfb_pro'          => get_option( 'emsfb_pro', null ),
					'emsfb_install_date' => get_option( 'emsfb_install_date', null ),
					'emsfb_review_state' => get_option( 'emsfb_review_state', null ),
				),
				false
			);
		}

		// A Free site that has been using the plugin for a month: exactly the
		// site this feature exists for.
		update_option( 'emsfb_pro', 2, false );
		update_option( 'emsfb_install_date', time() - ( 30 * DAY_IN_SECONDS ), false );
		delete_option( 'emsfb_review_state' );

		file_put_contents( $WP_MU, efb_e2e_endpoint_mu() );
		file_put_contents( $WS_MU, efb_e2e_capture_mu( $MAILBOX ) );

		if ( ! is_dir( $MAILBOX ) ) {
			wp_mkdir_p( $MAILBOX );
		}
		efb_e2e_empty( $MAILBOX );

		echo wp_json_encode(
			array(
				'ok'       => true,
				'endpoint' => file_exists( $WP_MU ),
				'capture'  => file_exists( $WS_MU ),
				'mailbox'  => $MAILBOX,
			)
		);
		break;

	case 'state':
		echo wp_json_encode(
			array(
				'ok'    => true,
				'state' => get_option( 'emsfb_review_state' ),
			)
		);
		break;

	case 'teardown':
		$saved = get_option( $MARKER );

		if ( is_array( $saved ) ) {
			foreach ( $saved as $key => $value ) {
				if ( null === $value ) {
					delete_option( $key );
				} else {
					update_option( $key, $value, false );
				}
			}
		}

		delete_option( $MARKER );

		@unlink( $WP_MU ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
		@unlink( $WS_MU ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
		efb_e2e_empty( $MAILBOX );
		@rmdir( $MAILBOX ); // phpcs:ignore WordPress.PHP.NoSilencedErrors

		echo wp_json_encode(
			array(
				'ok'               => true,
				'restored'         => is_array( $saved ),
				'endpoint_removed' => ! file_exists( $WP_MU ),
				'capture_removed'  => ! file_exists( $WS_MU ),
			)
		);
		break;

	default:
		echo wp_json_encode( array( 'ok' => false, 'error' => 'unknown mode' ) );
		exit( 1 );
}

echo "\n";
