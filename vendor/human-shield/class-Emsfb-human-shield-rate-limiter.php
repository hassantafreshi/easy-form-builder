<?php
/**
 * Rate limiter for EFB Human Shield.
 *
 * @package Easy_Form_Builder
 * @subpackage Human_Shield
 */

namespace Emsfb;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Emsfb_Human_Shield_Rate_Limiter {
	/** @var Emsfb_Human_Shield */
	private $shield;

	public function __construct( Emsfb_Human_Shield $shield ) {
		$this->shield = $shield;
	}

	public function evaluate_context( $context ) {
		$settings = $this->shield->get_settings();
		$route    = isset( $context['route'] ) ? $context['route'] : '';
		$action   = isset( $context['action'] ) ? $context['action'] : 'api';
		$form_id  = isset( $context['form_id'] ) ? absint( $context['form_id'] ) : 0;
		$ip       = isset( $context['ip'] ) ? $context['ip'] : $this->shield->get_client_ip();
		$ip_hash  = $this->shield->hash_value( $ip, 'rate_ip' );

		$checks = array();
		$checks[] = array( 'scope' => 'api_ip_minute', 'limit' => (int) $settings['api_ip_per_minute'], 'window' => 60, 'key' => $ip_hash );

		if ( 'submit' === $action ) {
			$checks[] = array( 'scope' => 'submit_ip_minute', 'limit' => (int) $settings['submit_ip_per_minute'], 'window' => 60, 'key' => $ip_hash );
			$checks[] = array( 'scope' => 'submit_ip_hour', 'limit' => (int) $settings['submit_ip_per_hour'], 'window' => HOUR_IN_SECONDS, 'key' => $ip_hash );
			$checks[] = array( 'scope' => 'form_global_minute', 'limit' => (int) $settings['form_global_per_minute'], 'window' => 60, 'key' => 'global' );
		} elseif ( 'response_get' === $action ) {
			$checks[] = array( 'scope' => 'response_get_ip_minute', 'limit' => (int) $settings['response_get_ip_per_minute'], 'window' => 60, 'key' => $ip_hash );
		} elseif ( 'response_add' === $action ) {
			$checks[] = array( 'scope' => 'response_add_ip_minute', 'limit' => (int) $settings['response_add_ip_per_minute'], 'window' => 60, 'key' => $ip_hash );
			if ( ! empty( $context['track'] ) ) {
				$checks[] = array( 'scope' => 'track_response_hour', 'limit' => 5, 'window' => HOUR_IN_SECONDS, 'key' => $this->shield->hash_value( $context['track'], 'track' ) );
			}
		} elseif ( 'file_upload' === $action ) {
			$checks[] = array( 'scope' => 'file_upload_ip_minute', 'limit' => (int) $settings['file_upload_ip_per_minute'], 'window' => 60, 'key' => $ip_hash );
		} elseif ( 'payment' === $action ) {
			$checks[] = array( 'scope' => 'payment_ip_minute', 'limit' => (int) $settings['payment_ip_per_minute'], 'window' => 60, 'key' => $ip_hash );
		}

		foreach ( $checks as $check ) {
			if ( $check['limit'] <= 0 ) {
				continue;
			}

			$result = $this->consume( $check['scope'], $route, $form_id, $check['key'], $check['limit'], $check['window'] );
			if ( empty( $result['allowed'] ) ) {
				return array(
					'allowed'     => false,
					'reason'      => 'rate_limited_' . $check['scope'],
					'retry_after' => isset( $result['retry_after'] ) ? (int) $result['retry_after'] : $check['window'],
				);
			}
		}

		return array( 'allowed' => true );
	}

	public function consume( $scope, $route, $form_id, $bucket_key, $limit, $window_seconds ) {
		$now = time();
		$window_seconds = max( 1, absint( $window_seconds ) );
		$window_start = (int) floor( $now / $window_seconds ) * $window_seconds;
		$route = sanitize_text_field( (string) $route );
		$scope = sanitize_key( (string) $scope );
		$form_id = absint( $form_id );
		$bucket_key = substr( sanitize_text_field( (string) $bucket_key ), 0, 191 );

		if ( $this->shield->tables_ready() ) {
			return $this->consume_db( $scope, $route, $form_id, $bucket_key, $limit, $window_seconds, $window_start, $now );
		}

		return $this->consume_transient( $scope, $route, $form_id, $bucket_key, $limit, $window_seconds, $window_start );
	}

	private function consume_db( $scope, $route, $form_id, $bucket_key, $limit, $window_seconds, $window_start, $now ) {
		$table = $this->shield->table_name( 'rate_limits' );
		$blocked_until_candidate = $window_start + $window_seconds;

		$this->shield->db->query(
			$this->shield->db->prepare(
				"INSERT INTO {$table}
					(bucket_key, scope, route, form_id, window_start, window_seconds, count, blocked_until, last_seen, meta_json)
				VALUES (%s, %s, %s, %d, %d, %d, 1, 0, %d, %s)
				ON DUPLICATE KEY UPDATE
					blocked_until = IF((count + 1) > %d, %d, 0),
					count = count + 1,
					last_seen = %d",
				$bucket_key,
				$scope,
				$route,
				$form_id,
				$window_start,
				$window_seconds,
				$now,
				'',
				$limit,
				$blocked_until_candidate,
				$now
			)
		);

		$row = $this->shield->db->get_row(
			$this->shield->db->prepare(
				"SELECT id, count, blocked_until FROM {$table} WHERE bucket_key = %s AND scope = %s AND route = %s AND form_id = %d AND window_start = %d LIMIT 1",
				$bucket_key,
				$scope,
				$route,
				$form_id,
				$window_start
			),
			ARRAY_A
		);

		if ( ! $row ) {
			return array( 'allowed' => true, 'count' => 1 );
		}

		if ( $row && ! empty( $row['blocked_until'] ) && (int) $row['blocked_until'] > $now ) {
			return array(
				'allowed'     => false,
				'count'       => (int) $row['count'],
				'retry_after' => (int) $row['blocked_until'] - $now,
			);
		}

		$count = (int) $row['count'];
		if ( $count > $limit ) {
			return array(
				'allowed'     => false,
				'count'       => $count,
				'retry_after' => max( 1, $blocked_until_candidate - $now ),
			);
		}

		return array( 'allowed' => true, 'count' => $count );
	}

	private function consume_transient( $scope, $route, $form_id, $bucket_key, $limit, $window_seconds, $window_start ) {
		$key = 'efb_hs_rl_' . md5( $scope . '|' . $route . '|' . $form_id . '|' . $bucket_key . '|' . $window_start );
		$count = (int) get_transient( $key );
		$count++;
		set_transient( $key, $count, $window_seconds + 5 );

		if ( $count > $limit ) {
			return array(
				'allowed'     => false,
				'count'       => $count,
				'retry_after' => $window_seconds,
			);
		}

		return array( 'allowed' => true, 'count' => $count );
	}
}
