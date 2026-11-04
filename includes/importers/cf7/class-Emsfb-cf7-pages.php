<?php
/**
 * Contact Form 7 importer - the pages that show a Contact Form 7 form, and
 * the optional swap of that form for the imported one.
 *
 * Recognised embeds, resolved the way Contact Form 7 resolves them:
 *
 *   [contact-form-7 id="123" title="…"]      numeric id
 *   [contact-form-7 id="1a2b3c4" title="…"]  hash id (CF7 5.8+), prefix of _hash
 *   [contact-form-7 title="…"]               title only
 *   [contact-form 3 "…"]                     pre-3.0 shortcode, via _old_cf7_unit_id
 *   <!-- wp:contact-form-7/contact-form-selector {"id":123,"hash":"…"} -->…<!-- /wp:… -->
 *
 * A block is replaced by the Easy Form Builder block, a shortcode by
 * [EMS_Form_Builder id=N]. Pages are saved with wp_update_post(), after the
 * current content has been stored as a revision, so every change can be
 * undone from the page's Revisions screen.
 *
 * @package Easy_Form_Builder
 */

namespace Emsfb\Importers\CF7;

defined( 'ABSPATH' ) || exit;

class Pages {

	/** Post types that never hold page content. */
	const SKIP_TYPES = array(
		'revision', 'nav_menu_item', 'wpcf7_contact_form', 'customize_changeset', 'oembed_cache',
		'user_request', 'wp_global_styles', 'wp_font_face', 'wp_font_family', 'flamingo_inbound',
		'flamingo_contact', 'flamingo_outbound',
	);

	/** At most this many posts are looked at. */
	const LIMIT = 500;

	const BLOCK_PATTERN = '#<!--\s+wp:contact-form-7/contact-form-selector(?:\s+(\{.*?\}))?\s+(?:/-->|-->.*?<!--\s+/wp:contact-form-7/contact-form-selector\s+-->)#s';

	/**
	 * Every post that shows one of the given forms.
	 *
	 * @param array $forms cf7 id => ['id' => int, 'title' => string, 'hash' => string, 'old_id' => int].
	 * @return array[] post id => [
	 *     'id', 'title', 'type', 'builder' ('' or a page builder's name),
	 *     'editable' (bool), 'forms' => cf7 id => number of embeds,
	 * ]
	 */
	public static function find( array $forms ) {
		global $wpdb;
		if ( empty( $forms ) ) {
			return array();
		}

		$skip = "'" . implode( "','", array_map( 'esc_sql', self::SKIP_TYPES ) ) . "'";
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $skip is a constant list, escaped above.
		$sql  = "SELECT ID, post_title, post_type, post_content FROM {$wpdb->posts}
			WHERE post_status NOT IN ('trash','auto-draft','inherit')
			AND post_type NOT IN ({$skip})
			AND (post_content LIKE %s OR post_content LIKE %s OR post_content LIKE %s)
			ORDER BY (post_type = 'page') DESC, ID ASC
			LIMIT %d";
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				$sql, // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				'%' . $wpdb->esc_like( '[contact-form-7' ) . '%',
				'%' . $wpdb->esc_like( 'wp:contact-form-7/contact-form-selector' ) . '%',
				'%' . $wpdb->esc_like( '[contact-form ' ) . '%',
				self::LIMIT
			)
		);

		$found = array();
		foreach ( (array) $rows as $row ) {
			$counts = array();
			foreach ( self::scan( (string) $row->post_content, $forms ) as $embed ) {
				$counts[ $embed ] = isset( $counts[ $embed ] ) ? $counts[ $embed ] + 1 : 1;
			}
			if ( empty( $counts ) ) {
				continue;
			}
			$id           = (int) $row->ID;
			$title        = trim( wp_strip_all_tags( (string) $row->post_title ) );
			$found[ $id ] = array(
				'id'       => $id,
				'title'    => '' !== $title ? $title : '#' . $id,
				'type'     => (string) $row->post_type,
				'builder'  => self::page_builder( $id ),
				'editable' => current_user_can( 'edit_post', $id ),
				'forms'    => $counts,
			);
		}
		return $found;
	}

	/**
	 * The Contact Form 7 forms a piece of content shows, one entry per embed.
	 *
	 * @param string $content Post content.
	 * @param array  $forms   See find().
	 * @return int[] cf7 ids.
	 */
	public static function scan( $content, array $forms ) {
		$found = array();

		// Blocks first; the shortcode saved inside a block is part of it.
		$content = preg_replace_callback(
			self::BLOCK_PATTERN,
			function ( $m ) use ( $forms, &$found ) {
				$cf7 = self::resolve_block( isset( $m[1] ) ? $m[1] : '', $forms );
				if ( $cf7 ) {
					$found[] = $cf7;
				}
				return '';
			},
			(string) $content
		);

		if ( preg_match_all( '/' . get_shortcode_regex( array( 'contact-form-7', 'contact-form' ) ) . '/s', $content, $all, PREG_SET_ORDER ) ) {
			foreach ( $all as $m ) {
				if ( '[' === $m[1] && ']' === $m[6] ) {
					continue; // [[escaped]]
				}
				$cf7 = self::resolve_shortcode( $m[2], $m[3], $forms );
				if ( $cf7 ) {
					$found[] = $cf7;
				}
			}
		}
		return $found;
	}

	/**
	 * Swap the given Contact Form 7 forms for their imported copies.
	 *
	 * @param string $content Post content.
	 * @param array  $forms   See find() - every CF7 form, to resolve references.
	 * @param array  $swap    cf7 id => ['efb' => int, 'name' => string].
	 * @return array [string $content, int $replaced]
	 */
	public static function replace_in_content( $content, array $forms, array $swap ) {
		$count = 0;

		$content = preg_replace_callback(
			self::BLOCK_PATTERN,
			function ( $m ) use ( $forms, $swap, &$count ) {
				$cf7 = self::resolve_block( isset( $m[1] ) ? $m[1] : '', $forms );
				if ( ! $cf7 || ! isset( $swap[ $cf7 ] ) ) {
					return $m[0];
				}
				$count++;
				$attrs = array(
					'formId'   => (string) (int) $swap[ $cf7 ]['efb'],
					'formName' => (string) $swap[ $cf7 ]['name'],
				);
				$json  = function_exists( 'serialize_block_attributes' ) ? serialize_block_attributes( $attrs ) : wp_json_encode( $attrs );
				return '<!-- wp:easy-form-builder/form ' . $json . ' /-->';
			},
			(string) $content
		);

		$content = preg_replace_callback(
			'/' . get_shortcode_regex( array( 'contact-form-7', 'contact-form' ) ) . '/s',
			function ( $m ) use ( $forms, $swap, &$count ) {
				if ( '[' === $m[1] && ']' === $m[6] ) {
					return $m[0];
				}
				$cf7 = self::resolve_shortcode( $m[2], $m[3], $forms );
				if ( ! $cf7 || ! isset( $swap[ $cf7 ] ) ) {
					return $m[0];
				}
				$count++;
				return $m[1] . '[EMS_Form_Builder id=' . (int) $swap[ $cf7 ]['efb'] . ']' . $m[6];
			},
			$content
		);

		return array( $content, $count );
	}

	/**
	 * Replace the embeds on one post and save it, keeping a revision.
	 *
	 * @param int   $post_id Post.
	 * @param array $forms   See find().
	 * @param array $swap    See replace_in_content().
	 * @return array ['ok' => bool, 'count' => int, 'revision' => int, 'reason' => string]
	 */
	public static function replace_on_post( $post_id, array $forms, array $swap ) {
		$post = get_post( $post_id );
		$out  = array(
			'ok'       => false,
			'count'    => 0,
			'revision' => 0,
			'reason'   => '',
		);
		if ( ! $post ) {
			$out['reason'] = 'missing';
			return $out;
		}
		if ( ! current_user_can( 'edit_post', $post->ID ) ) {
			$out['reason'] = 'cap';
			return $out;
		}
		if ( '' !== self::page_builder( $post->ID ) ) {
			$out['reason'] = 'builder';
			return $out;
		}
		if ( ! self::can_undo( $post ) ) {
			$out['reason'] = 'revisions';
			return $out;
		}

		list( $content, $count ) = self::replace_in_content( $post->post_content, $forms, $swap );
		if ( 0 === $count ) {
			$out['reason'] = 'gone';
			return $out;
		}

		// The page as it is now, kept as a revision: this is what "undo" restores.
		$before = wp_save_post_revision( $post->ID );
		if ( ! $before ) {
			// Nothing changed since the latest revision: that one is the snapshot.
			$latest = wp_get_post_revisions( $post->ID, array( 'numberposts' => 1 ) );
			$first  = empty( $latest ) ? null : reset( $latest );
			$before = is_object( $first ) ? (int) $first->ID : (int) $first;
		}
		if ( ! $before ) {
			$out['reason'] = 'revisions';
			return $out;
		}

		$result = wp_update_post(
			array(
				'ID'           => $post->ID,
				'post_content' => wp_slash( $content ),
			),
			true
		);
		if ( is_wp_error( $result ) || ! $result ) {
			$out['reason'] = 'save';
			return $out;
		}

		$out['ok']       = true;
		$out['count']    = $count;
		$out['revision'] = (int) $before;
		return $out;
	}

	/**
	 * Whether WordPress will still hold the "before" revision after the save.
	 *
	 * replace_on_post() stores the page as it is now as a revision, then
	 * saves the new content, which stores a second revision; WordPress then
	 * deletes the oldest revisions beyond wp_revisions_to_keep(). With 0
	 * (revisions off, or the post type has none) there is no revision at
	 * all, with 1 the "before" copy is deleted by the save itself - either
	 * way there would be nothing to undo, so the page is left alone.
	 *
	 * @param \WP_Post|object $post Post (needs post_type).
	 * @return bool True when revisions are unlimited (-1) or at least 2 are kept.
	 */
	public static function can_undo( $post ) {
		if ( ! is_object( $post ) || ! post_type_supports( (string) $post->post_type, 'revisions' ) ) {
			return false;
		}
		$keep = (int) wp_revisions_to_keep( $post );
		return $keep < 0 || $keep >= 2;
	}

	/**
	 * The page builder that owns a post's layout, if its content does not
	 * live in post_content (changing post_content would not change the page).
	 *
	 * @param int $post_id Post.
	 * @return string '' or the builder's name.
	 */
	public static function page_builder( $post_id ) {
		if ( 'builder' === get_post_meta( $post_id, '_elementor_edit_mode', true ) ) {
			return 'Elementor';
		}
		if ( get_post_meta( $post_id, '_fl_builder_enabled', true ) ) {
			return 'Beaver Builder';
		}
		return '';
	}

	/**
	 * @param string $json  Block attributes.
	 * @param array  $forms See find().
	 * @return int CF7 id, 0 when unknown.
	 */
	private static function resolve_block( $json, array $forms ) {
		$attrs = '' === $json ? array() : json_decode( $json, true );
		if ( ! is_array( $attrs ) ) {
			return 0;
		}
		if ( ! empty( $attrs['hash'] ) ) {
			$by_hash = self::by_hash( (string) $attrs['hash'], $forms );
			if ( $by_hash ) {
				return $by_hash;
			}
		}
		if ( ! empty( $attrs['id'] ) && isset( $forms[ (int) $attrs['id'] ] ) ) {
			return (int) $attrs['id'];
		}
		return 0;
	}

	/**
	 * @param string $code  "contact-form-7" or "contact-form".
	 * @param string $atts  Attribute text.
	 * @param array  $forms See find().
	 * @return int CF7 id, 0 when unknown.
	 */
	private static function resolve_shortcode( $code, $atts, array $forms ) {
		$atts = shortcode_parse_atts( $atts );
		$atts = is_array( $atts ) ? $atts : array();

		if ( 'contact-form' === $code ) {
			$old = isset( $atts[0] ) ? (int) $atts[0] : 0;
			foreach ( $forms as $id => $form ) {
				if ( $old && ! empty( $form['old_id'] ) && (int) $form['old_id'] === $old ) {
					return (int) $id;
				}
			}
			return 0;
		}

		$ref = isset( $atts['id'] ) ? trim( (string) $atts['id'] ) : '';
		if ( '' !== $ref ) {
			// Contact Form 7 tries the hash first, then the post id.
			$by_hash = self::by_hash( $ref, $forms );
			if ( $by_hash ) {
				return $by_hash;
			}
			if ( ctype_digit( $ref ) && isset( $forms[ (int) $ref ] ) ) {
				return (int) $ref;
			}
		}
		$title = isset( $atts['title'] ) ? trim( (string) $atts['title'] ) : '';
		if ( '' !== $title ) {
			foreach ( $forms as $id => $form ) {
				if ( $form['title'] === $title ) {
					return (int) $id;
				}
			}
		}
		return 0;
	}

	/**
	 * @param string $ref   A hash or its first 7+ characters.
	 * @param array  $forms See find().
	 * @return int CF7 id, 0 when none matches.
	 */
	private static function by_hash( $ref, array $forms ) {
		$ref = strtolower( $ref );
		if ( ! preg_match( '/^[0-9a-f]{7,}$/', $ref ) ) {
			return 0;
		}
		foreach ( $forms as $id => $form ) {
			if ( ! empty( $form['hash'] ) && 0 === strpos( strtolower( $form['hash'] ), $ref ) ) {
				return (int) $id;
			}
		}
		return 0;
	}
}
