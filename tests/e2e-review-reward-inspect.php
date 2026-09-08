<?php
/**
 * Inspect and clean the ws side of the end-to-end review reward test.
 *
 * Kept separate from the environment script because it boots the ws install,
 * not the customer one - the two are different WordPress sites on the same
 * machine and cannot be loaded into one process.
 *
 * Run: C:\xampp\php\php.exe tests/e2e-review-reward-inspect.php <mode> [arg]
 *
 *   reviewer            print a real five-star reviewer from the cached list
 *   row <username>      the stored claim, as JSON
 *   stripe <code>       whether that promotion code really exists in Stripe
 *   mail                every captured message, as JSON
 *   reports <reason>    how many reports the feedback service holds
 *   clean <username>    delete the claim row and its Stripe objects
 */

define( 'WP_USE_THEMES', false );
require 'c:/xampp/htdocs/ws/wp-load.php';
require_once WP_PLUGIN_DIR . '/payEfb/includes/services/class-review-reward-service.php';

global $wpdb;

$mode = isset( $argv[1] ) ? $argv[1] : '';
$arg  = isset( $argv[2] ) ? $argv[2] : '';

$table   = \payEfb\Services\ReviewRewardService::table();
$mailbox = WP_CONTENT_DIR . '/uploads/efb-e2e-mail';

/**
 * A Stripe client, or null when the keys are absent.
 *
 * @return \Stripe\StripeClient|null
 */
function efb_e2e_stripe() {
	require_once payEfb_PLUGIN_DIRECTORY . '/vendor/autoload.php';

	$config = \payEfb\Config::get_stripe_config();
	$key    = (string) ( $config['secret_key'] ?? '' );

	return '' === $key ? null : new \Stripe\StripeClient( $key );
}

switch ( $mode ) {
	case 'reviewer':
		/*
		 * A real five-star reviewer from the list ws-widgets cached from
		 * WordPress.org, preferring one who has never claimed - so the test
		 * exercises a genuine first-time grant rather than the "used" branch.
		 */
		$data    = get_option( 'ws_widgets_wporg_data' );
		$reviews = is_array( $data ) && ! empty( $data['reviews'] ) ? $data['reviews'] : array();
		$service = new \payEfb\Services\ReviewRewardService();
		$found   = null;

		foreach ( $reviews as $review ) {
			if ( (int) ( $review['rating'] ?? 0 ) < 5 || empty( $review['author'] ) ) {
				continue;
			}

			$name  = $service->normalize_username( $review['author'] );
			$taken = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE username = %s", $name ) ); // phpcs:ignore WordPress.DB

			if ( 0 === $taken ) {
				$found = array(
					'username' => $name,
					'rating'   => (int) $review['rating'],
					'link'     => (string) ( $review['link'] ?? '' ),
				);
				break;
			}
		}

		echo wp_json_encode( $found ? array( 'ok' => true ) + $found : array( 'ok' => false ) );
		break;

	case 'row':
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE username = %s", $arg ), ARRAY_A ); // phpcs:ignore WordPress.DB
		echo wp_json_encode( array( 'ok' => (bool) $row, 'row' => $row ) );
		break;

	case 'stripe':
		try {
			$stripe = efb_e2e_stripe();

			if ( ! $stripe ) {
				echo wp_json_encode( array( 'ok' => false, 'error' => 'no stripe key' ) );
				break;
			}

			// Look the customer-facing code up the way checkout would.
			$list = $stripe->promotionCodes->all( array( 'code' => $arg, 'limit' => 1 ) );
			$promo = $list->data[0] ?? null;

			echo wp_json_encode(
				array(
					'ok'          => (bool) $promo,
					'code'        => $promo ? $promo->code : '',
					'active'      => $promo ? (bool) $promo->active : false,
					'percent_off' => $promo && $promo->coupon ? $promo->coupon->percent_off : null,
					'duration'    => $promo && $promo->coupon ? $promo->coupon->duration : '',
					'max_redeem'  => $promo ? $promo->max_redemptions : null,
					'expires_at'  => $promo ? $promo->expires_at : null,
					'livemode'    => $promo ? (bool) $promo->livemode : null,
				)
			);
		} catch ( \Throwable $e ) {
			echo wp_json_encode( array( 'ok' => false, 'error' => $e->getMessage() ) );
		}
		break;

	case 'mail':
		$out = array();

		foreach ( glob( $mailbox . '/*.json' ) as $file ) {
			$meta = json_decode( (string) file_get_contents( $file ), true );
			$html = (string) @file_get_contents( preg_replace( '/\.json$/', '.html', $file ) ); // phpcs:ignore WordPress.PHP.NoSilencedErrors

			$out[] = array(
				'to'      => $meta['to'] ?? '',
				'subject' => $meta['subject'] ?? '',
				'headers' => $meta['headers'] ?? '',
				'length'  => strlen( $html ),
				'html'    => $html,
			);
		}

		echo wp_json_encode( array( 'ok' => true, 'count' => count( $out ), 'mail' => $out ) );
		break;

	case 'reports':
		// The feedback service lives on the customer install, not here.
		echo wp_json_encode( array( 'ok' => false, 'error' => 'reports live on the wp install' ) );
		break;

	case 'clean':
		$row     = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE username = %s", $arg ), ARRAY_A ); // phpcs:ignore WordPress.DB
		$deleted = array( 'row' => false, 'promotion' => false, 'coupon' => false );

		if ( $row ) {
			try {
				$stripe = efb_e2e_stripe();

				if ( $stripe ) {
					// A promotion code cannot be deleted, only deactivated;
					// deleting its coupon is what actually retires it.
					if ( ! empty( $row['promotion_id'] ) ) {
						$stripe->promotionCodes->update( $row['promotion_id'], array( 'active' => false ) );
						$deleted['promotion'] = true;
					}

					if ( ! empty( $row['coupon_id'] ) ) {
						$stripe->coupons->delete( $row['coupon_id'] );
						$deleted['coupon'] = true;
					}
				}
			} catch ( \Throwable $e ) {
				$deleted['error'] = $e->getMessage();
			}

			$wpdb->delete( $table, array( 'username' => $arg ) ); // phpcs:ignore WordPress.DB
			$deleted['row'] = true;
		}

		echo wp_json_encode( array( 'ok' => true ) + $deleted );
		break;

	case 'clean-probes':
		/*
		 * The "a greedy client cannot name its own discount" check claims a
		 * username that does not exist, which parks a pending row by design.
		 * Every run would otherwise leave one behind for good.
		 */
		$removed = (int) $wpdb->query( "DELETE FROM {$table} WHERE username LIKE 'efb-e2e-nobody-%'" ); // phpcs:ignore WordPress.DB

		echo wp_json_encode( array( 'ok' => true, 'removed' => $removed ) );
		break;

	default:
		echo wp_json_encode( array( 'ok' => false, 'error' => 'unknown mode' ) );
		exit( 1 );
}

echo "\n";
