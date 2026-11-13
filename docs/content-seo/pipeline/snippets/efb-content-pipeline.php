<?php
/**
 * Plugin Name:  EFB Content Pipeline (REST SEO meta + JSON-LD)
 * Description:  Lets authorised editors set the SEO title, meta description and focus keyword (Yoast / Rank Math / SEOPress)
 *               and extra JSON-LD (FAQPage, VideoObject) of a post through the REST API, prints that JSON-LD in <head>,
 *               and manages a small exact-path 301 redirect map (used when merged articles are redirected).
 *               Install as a must-use plugin: wp-content/mu-plugins/efb-content-pipeline.php
 * Version:      1.0.0
 * Requires PHP: 7.4
 *
 * Security notes
 *  - Meta is writable only by users who can edit_post() the target post (Editor role is enough; no admin needed).
 *  - JSON-LD is decoded and re-encoded (never echoed raw) with JSON_HEX_TAG, so "</script>" cannot break out.
 *  - Redirect map: only same-site absolute paths ("/a/b/" -> "/c/d/"), max 500 entries, loops rejected;
 *    writable by users with edit_others_posts (Editor). Stored in the option efb_cp_redirects.
 *  - Only posts of type "post" are affected by the meta fields.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** SEO-plugin meta keys the pipeline may write. Unused keys are harmless. */
function efb_cp_seo_meta_keys() {
	return array(
		// Yoast SEO.
		'_yoast_wpseo_title',
		'_yoast_wpseo_metadesc',
		'_yoast_wpseo_focuskw',
		// Rank Math.
		'rank_math_title',
		'rank_math_description',
		'rank_math_focus_keyword',
		// SEOPress.
		'_seopress_titles_title',
		'_seopress_titles_desc',
		'_seopress_analysis_target_kw',
	);
}

function efb_cp_can_edit_post( $allowed, $meta_key, $post_id ) {
	return $post_id && current_user_can( 'edit_post', (int) $post_id );
}

/** Validate JSON-LD: must decode to an array/object; returns canonical, script-safe JSON or ''. */
function efb_cp_sanitize_jsonld( $value ) {
	if ( ! is_string( $value ) || '' === trim( $value ) ) {
		return '';
	}
	$decoded = json_decode( $value, true );
	if ( ! is_array( $decoded ) ) {
		return '';
	}
	$json = wp_json_encode( $decoded, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP );
	return is_string( $json ) ? $json : '';
}

add_action(
	'init',
	function () {
		foreach ( efb_cp_seo_meta_keys() as $key ) {
			register_post_meta(
				'post',
				$key,
				array(
					'type'              => 'string',
					'single'            => true,
					'show_in_rest'      => true,
					'sanitize_callback' => 'sanitize_text_field',
					'auth_callback'     => 'efb_cp_can_edit_post',
				)
			);
		}
		// Extra JSON-LD block(s) printed in <head> (a single object or an array of objects).
		register_post_meta(
			'post',
			'efb_jsonld',
			array(
				'type'              => 'string',
				'single'            => true,
				'show_in_rest'      => true,
				'sanitize_callback' => 'efb_cp_sanitize_jsonld',
				'auth_callback'     => 'efb_cp_can_edit_post',
			)
		);
		// YouTube video id of the matching tutorial (used by the pipeline to find posts that still wait for their video).
		register_post_meta(
			'post',
			'efb_youtube_id',
			array(
				'type'              => 'string',
				'single'            => true,
				'show_in_rest'      => true,
				'sanitize_callback' => function ( $v ) {
					return preg_match( '/^[A-Za-z0-9_-]{11}$/', (string) $v ) ? $v : '';
				},
				'auth_callback'     => 'efb_cp_can_edit_post',
			)
		);
	}
);

add_action(
	'wp_head',
	function () {
		if ( ! is_singular( 'post' ) ) {
			return;
		}
		$json = get_post_meta( get_queried_object_id(), 'efb_jsonld', true );
		$json = efb_cp_sanitize_jsonld( $json ); // re-validate on output.
		if ( '' !== $json ) {
			echo '<script type="application/ld+json" class="efb-cp-jsonld">' . $json . '</script>' . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
	},
	30
);

/* ------------------------------------------------------------------ *
 * Exact-path 301 redirects (REST: /wp-json/efb-cp/v1/redirects)
 * ------------------------------------------------------------------ */

function efb_cp_normalise_path( $path ) {
	$path = is_string( $path ) ? trim( $path ) : '';
	$path = (string) wp_parse_url( $path, PHP_URL_PATH );
	if ( '' === $path || '/' !== $path[0] || false !== strpos( $path, '//' ) || false !== strpos( $path, '..' ) ) {
		return '';
	}
	return trailingslashit( rawurldecode( $path ) );
}

add_action(
	'template_redirect',
	function () {
		$map = get_option( 'efb_cp_redirects', array() );
		if ( ! is_array( $map ) || empty( $map ) || empty( $_SERVER['REQUEST_URI'] ) ) {
			return;
		}
		$from = efb_cp_normalise_path( wp_unslash( $_SERVER['REQUEST_URI'] ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		if ( '' !== $from && isset( $map[ $from ] ) ) {
			wp_safe_redirect( home_url( $map[ $from ] ), 301 );
			exit;
		}
	},
	1
);

add_action(
	'rest_api_init',
	function () {
		$permission = function () {
			return current_user_can( 'edit_others_posts' );
		};
		register_rest_route(
			'efb-cp/v1',
			'/redirects',
			array(
				array(
					'methods'             => 'GET',
					'permission_callback' => $permission,
					'callback'            => function () {
						$map = get_option( 'efb_cp_redirects', array() );
						return rest_ensure_response( is_array( $map ) ? $map : array() );
					},
				),
				array(
					// Body: {"redirects": {"/old/path/": "/new/path/", ...}}  (merged into the existing map; null/"" removes an entry).
					'methods'             => 'POST',
					'permission_callback' => $permission,
					'callback'            => function ( WP_REST_Request $request ) {
						$incoming = $request->get_param( 'redirects' );
						if ( ! is_array( $incoming ) ) {
							return new WP_Error( 'efb_cp_bad_request', 'redirects must be an object of from => to paths.', array( 'status' => 400 ) );
						}
						$map = get_option( 'efb_cp_redirects', array() );
						$map = is_array( $map ) ? $map : array();
						foreach ( $incoming as $from => $to ) {
							$from = efb_cp_normalise_path( $from );
							if ( '' === $from ) {
								return new WP_Error( 'efb_cp_bad_path', 'Invalid "from" path.', array( 'status' => 400 ) );
							}
							if ( null === $to || '' === $to ) {
								unset( $map[ $from ] );
								continue;
							}
							$to = efb_cp_normalise_path( $to );
							if ( '' === $to || $to === $from ) {
								return new WP_Error( 'efb_cp_bad_path', 'Invalid "to" path.', array( 'status' => 400 ) );
							}
							$map[ $from ] = $to;
						}
						// Reject loops (A->B->A) and oversize maps.
						foreach ( $map as $from => $to ) {
							if ( isset( $map[ $to ] ) && $map[ $to ] === $from ) {
								return new WP_Error( 'efb_cp_loop', 'Redirect loop detected.', array( 'status' => 400 ) );
							}
						}
						if ( count( $map ) > 500 ) {
							return new WP_Error( 'efb_cp_too_many', 'Too many redirects (max 500).', array( 'status' => 400 ) );
						}
						update_option( 'efb_cp_redirects', $map, false );
						return rest_ensure_response( $map );
					},
				),
			)
		);
	}
);
