<?php
/**
 * Contact Form 7 importer - entry point.
 *
 * A self-contained module. Easy Form Builder's core requires this file only
 * on the two screens that offer the import (Panel and Create) and on the
 * importer's own admin-ajax actions (efb_cf7_*); nothing of it loads on the
 * front end or on any other admin screen. Everything it needs lives in this
 * folder, so it can move to a separate add-on unchanged.
 *
 *   class-Emsfb-cf7-parser.php  reads a Contact Form 7 form template
 *   class-Emsfb-cf7-mapper.php  builds the Easy Form Builder form from it
 *   class-Emsfb-cf7-pages.php   finds and (optionally) swaps the embeds
 *   cf7-importer-efb.js / .css  the card and the import dialog
 *
 * The entry point is shown only to administrators and only when Contact
 * Form 7 forms exist in the database, whether Contact Form 7 is active or
 * not. Contact Form 7 forms are only ever read, never changed or deleted.
 *
 * @package Easy_Form_Builder
 */

namespace Emsfb\Importers\CF7;

defined( 'ABSPATH' ) || exit;

class Importer {

	/** Contact Form 7's post type. */
	const POST_TYPE = 'wpcf7_contact_form';

	/** CF7 id => what was imported from it (forms, pages). Not autoloaded. */
	const OPTION = 'emsfb_cf7_import_map';

	const ACTION_LIST   = 'efb_cf7_list';
	const ACTION_IMPORT = 'efb_cf7_import';

	/** At most this many forms per import request. */
	const MAX_PER_REQUEST = 50;

	/**
	 * Register the hooks this request needs.
	 *
	 * @return void
	 */
	public static function boot() {
		if ( wp_doing_ajax() ) {
			add_action( 'wp_ajax_' . self::ACTION_LIST, array( __CLASS__, 'ajax_list' ) );
			add_action( 'wp_ajax_' . self::ACTION_IMPORT, array( __CLASS__, 'ajax_import' ) );
			return;
		}
		// After the plugin's own assets (priority 10) so its dialog styles exist.
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue' ), 20 );
	}

	/**
	 * Whether the current user may import: an administrator of the plugin.
	 *
	 * @return bool
	 */
	public static function can_use() {
		if ( function_exists( 'get_efbFunction' ) ) {
			$efb = get_efbFunction();
			if ( is_object( $efb ) && method_exists( $efb, 'user_permission_efb_admin_dashboard' ) ) {
				return (bool) $efb->user_permission_efb_admin_dashboard();
			}
		}
		return current_user_can( 'manage_options' );
	}

	/**
	 * Number of Contact Form 7 forms in the database.
	 *
	 * @return int
	 */
	public static function count_forms() {
		global $wpdb;
		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = %s AND post_status NOT IN ('trash','auto-draft')",
				self::POST_TYPE
			)
		);
	}

	/*
	 * ---------------------------------------------------------------------
	 * The card and the dialog
	 * ---------------------------------------------------------------------
	 */

	/**
	 * Load the card and dialog on Panel and Create, for administrators, when
	 * there is something to import.
	 *
	 * @param string $hook Admin page hook.
	 * @return void
	 */
	public static function enqueue( $hook ) {
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! in_array( $page, array( 'emsfb', 'emsfb_create' ), true ) || ! self::can_use() ) {
			return;
		}
		$count = self::count_forms();
		if ( $count < 1 ) {
			return;
		}
		// The Panel only offers the import while some form has not been imported.
		$pending = self::count_pending();
		if ( 'emsfb' === $page && $pending < 1 ) {
			return;
		}

		$url  = plugin_dir_url( __FILE__ );
		$ver  = function ( $file ) {
			$path = __DIR__ . '/' . $file;
			return is_readable( $path ) ? (string) filemtime( $path ) : ( defined( 'EMSFB_PLUGIN_VERSION' ) ? EMSFB_PLUGIN_VERSION : '1' );
		};
		$deps = array();
		if ( wp_style_is( 'efb-modal-system', 'registered' ) ) {
			$deps[] = 'efb-modal-system';
		}
		wp_enqueue_style( 'efb-cf7-importer', $url . 'cf7-importer-efb.css', $deps, $ver( 'cf7-importer-efb.css' ) );
		wp_enqueue_script( 'efb-cf7-importer', $url . 'cf7-importer-efb.js', array(), $ver( 'cf7-importer-efb.js' ), true );

		$data = array(
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( 'wp_rest' ),
			'screen'  => 'emsfb_create' === $page ? 'create' : 'panel',
			'count'   => $count,
			'pending' => $pending,
			'rtl'     => is_rtl() ? 1 : 0,
			'editUrl' => admin_url( 'admin.php?page=Emsfb&state=edit-form&id=' ),
			'text'    => self::strings( $count, $pending ),
		);
		if ( function_exists( 'emsfb_decode_typographic_entities_efb' ) ) {
			$data = emsfb_decode_typographic_entities_efb( $data );
		}
		wp_localize_script( 'efb-cf7-importer', 'efb_cf7_import', $data );
	}

	/**
	 * Every string the card and the dialog show.
	 *
	 * @param int $count   Number of Contact Form 7 forms.
	 * @param int $pending Number of them not imported yet.
	 * @return array
	 */
	public static function strings( $count, $pending = 0 ) {
		$efb   = function_exists( 'get_efbFunction' ) ? get_efbFunction() : null;
		$texts = is_object( $efb ) ? (array) $efb->text_efb( array( 'close', 'required', 'somethingWentWrongPleaseRefresh' ) ) : array();
		// EFB phrases are stored HTML-escaped; the dialog escapes what it prints.
		$pick  = function ( $key, $fallback ) use ( $texts ) {
			return isset( $texts[ $key ] ) && '' !== $texts[ $key ] ? html_entity_decode( (string) $texts[ $key ], ENT_QUOTES, 'UTF-8' ) : $fallback;
		};

		return array(
			'cardTitle'      => __( 'Moving from Contact Form 7?', 'easy-form-builder' ),
			'cardText'       => sprintf(
				/* translators: %s: number of Contact Form 7 forms found on the site */
				_n(
					'We found %s Contact Form 7 form on this site. Copy it into Easy Form Builder in one click; the original stays as it is.',
					'We found %s Contact Form 7 forms on this site. Copy them into Easy Form Builder in one click; the originals stay as they are.',
					$count,
					'easy-form-builder'
				),
				number_format_i18n( $count )
			),
			'cardTextPending' => sprintf(
				/* translators: %s: number of Contact Form 7 forms not imported yet */
				_n(
					'%s Contact Form 7 form on this site is not in Easy Form Builder yet. Copy it in one click; the original stays as it is.',
					'%s Contact Form 7 forms on this site are not in Easy Form Builder yet. Copy them in one click; the originals stay as they are.',
					$pending,
					'easy-form-builder'
				),
				number_format_i18n( $pending )
			),
			'open'           => __( 'Import from Contact Form 7', 'easy-form-builder' ),
			'intro'          => __( 'Choose the forms to copy. Each one becomes a new Easy Form Builder form. Your Contact Form 7 forms are not changed or deleted.', 'easy-form-builder' ),
			'loading'        => __( 'Looking for Contact Form 7 forms…', 'easy-form-builder' ),
			'empty'          => __( 'No Contact Form 7 forms were found.', 'easy-form-builder' ),
			'untitled'       => __( '(no title)', 'easy-form-builder' ),
			/* translators: %s: number of fields */
			'fields'         => __( 'Fields: %s', 'easy-form-builder' ),
			/* translators: %s: id of the Easy Form Builder form made from it */
			'imported'       => __( 'Already imported as form #%s', 'easy-form-builder' ),
			'importAgain'    => __( 'Ticked: it will be imported again as a new copy.', 'easy-form-builder' ),
			/* translators: %s: list of page titles */
			'shownOn'        => __( 'Shown on: %s', 'easy-form-builder' ),
			'replace'        => __( 'Replace the Contact Form 7 form on these pages with the new form', 'easy-form-builder' ),
			'replaceHelp'    => __( 'Off by default. Each page is first saved as a revision, so you can undo the change from the page’s Revisions screen. Pages built with Elementor or Beaver Builder are listed but not changed.', 'easy-form-builder' ),
			/* translators: %s: a page builder such as Elementor */
			'builderPage'    => __( 'built with %s: change the form there', 'easy-form-builder' ),
			'import'         => __( 'Import', 'easy-form-builder' ),
			'cancel'         => __( 'Cancel', 'easy-form-builder' ),
			'chooseOne'      => __( 'Choose at least one form.', 'easy-form-builder' ),
			'working'        => __( 'Importing…', 'easy-form-builder' ),
			'doneTitle'      => __( 'Import finished', 'easy-form-builder' ),
			/* translators: %s: id of the new Easy Form Builder form */
			'newForm'        => __( 'New form #%s', 'easy-form-builder' ),
			'carried'        => __( 'Carried over', 'easy-form-builder' ),
			'settings'       => __( 'Settings', 'easy-form-builder' ),
			'notCarried'     => __( 'Could not be carried over', 'easy-form-builder' ),
			'allCarried'     => __( 'Everything was carried over.', 'easy-form-builder' ),
			'copyShortcode'  => __( 'Copy shortcode', 'easy-form-builder' ),
			'copied'         => __( 'Copied', 'easy-form-builder' ),
			'edit'           => __( 'Edit in builder', 'easy-form-builder' ),
			'pagesDone'      => __( 'Now showing the new form:', 'easy-form-builder' ),
			'pagesNotDone'   => __( 'Not changed:', 'easy-form-builder' ),
			'pageNoChange'   => __( 'revisions are off for it or you cannot edit it, so it was left as it is', 'easy-form-builder' ),
			'view'           => __( 'View', 'easy-form-builder' ),
			'undo'           => __( 'Undo', 'easy-form-builder' ),
			'failed'         => __( 'This form could not be imported.', 'easy-form-builder' ),
			'required'       => $pick( 'required', __( 'Required', 'easy-form-builder' ) ),
			'close'          => $pick( 'close', __( 'Close', 'easy-form-builder' ) ),
			'error'          => $pick( 'somethingWentWrongPleaseRefresh', __( 'Something went wrong. Please refresh the page and try again.', 'easy-form-builder' ) ),
		);
	}

	/*
	 * ---------------------------------------------------------------------
	 * AJAX
	 * ---------------------------------------------------------------------
	 */

	/**
	 * The Contact Form 7 forms, with field counts, earlier imports and pages.
	 *
	 * @return void
	 */
	public static function ajax_list() {
		self::verify_request();
		self::load_parts();

		$forms  = self::cf7_forms();
		$pages  = Pages::find( $forms );
		$map    = self::import_map();
		$alive  = self::existing_efb_forms( $map );
		$parser = self::parser();
		$out    = array();

		foreach ( $forms as $id => $form ) {
			$imported = array();
			foreach ( self::efb_ids_for( $map, $id ) as $efb_id ) {
				if ( isset( $alive[ $efb_id ] ) ) {
					$imported[] = array(
						'id'   => $efb_id,
						'name' => $alive[ $efb_id ],
					);
				}
			}
			$shown_on = array();
			foreach ( $pages as $page ) {
				if ( isset( $page['forms'][ $id ] ) ) {
					$shown_on[] = array(
						'id'       => $page['id'],
						'title'    => $page['title'],
						'builder'  => $page['builder'],
						'editable' => $page['editable'],
					);
				}
			}
			$out[] = array(
				'id'       => $id,
				'title'    => $form['title'],
				'fields'   => self::count_fields( $parser, (string) get_post_meta( $id, '_form', true ) ),
				'imported' => $imported,
				'pages'    => $shown_on,
			);
		}

		wp_send_json_success( array( 'forms' => $out ) );
	}

	/**
	 * Import the chosen forms and, when asked, put them on their pages.
	 *
	 * @return void
	 */
	public static function ajax_import() {
		self::verify_request();
		self::load_parts();

		$ids = isset( $_POST['ids'] ) ? (array) wp_unslash( $_POST['ids'] ) : array(); // phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- verified in verify_request(), cast below.
		$ids = array_slice( array_values( array_unique( array_filter( array_map( 'absint', $ids ) ) ) ), 0, self::MAX_PER_REQUEST );
		$replace = isset( $_POST['replace'] ) && '1' === sanitize_text_field( wp_unslash( $_POST['replace'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing

		if ( empty( $ids ) ) {
			wp_send_json_error( array( 'message' => self::strings( 0 )['chooseOne'] ), 400 );
		}

		$forms   = self::cf7_forms();
		$results = array();
		$swap    = array();

		foreach ( $ids as $id ) {
			if ( ! isset( $forms[ $id ] ) ) {
				$results[ $id ] = array(
					'cf7'     => $id,
					'ok'      => false,
					'title'   => '#' . $id,
					'message' => self::strings( 0 )['failed'],
				);
				continue;
			}
			try {
				$results[ $id ] = self::import_one( $id, $forms[ $id ] );
			} catch ( \Throwable $e ) {
				$results[ $id ] = array(
					'cf7'     => $id,
					'ok'      => false,
					'title'   => $forms[ $id ]['title'],
					'message' => self::strings( 0 )['failed'],
				);
			}
			if ( ! empty( $results[ $id ]['ok'] ) ) {
				$swap[ $id ] = array(
					'efb'  => $results[ $id ]['efb'],
					'name' => $results[ $id ]['name'],
				);
				$results[ $id ]['pages'] = array();
			}
		}

		if ( $replace && ! empty( $swap ) ) {
			self::replace_pages( $forms, $swap, $results );
		}

		wp_send_json_success( array( 'results' => array_values( $results ) ) );
	}

	/**
	 * Map, insert and record one form.
	 *
	 * @param int   $id   CF7 id.
	 * @param array $form cf7_forms() entry.
	 * @return array Result for the dialog.
	 */
	private static function import_one( $id, array $form ) {
		$mapper = new Mapper( self::parser(), self::environment() );
		$mapped = $mapper->map(
			array(
				'title'               => $form['title'],
				'form'                => (string) get_post_meta( $id, '_form', true ),
				'mail'                => self::meta_array( $id, '_mail' ),
				'mail_2'              => self::meta_array( $id, '_mail_2' ),
				'messages'            => self::meta_array( $id, '_messages' ),
				'additional_settings' => (string) get_post_meta( $id, '_additional_settings', true ),
			)
		);

		$efb_id = self::insert_form( $mapped['name'], $mapped['structure'] );
		if ( ! $efb_id ) {
			return array(
				'cf7'     => $id,
				'ok'      => false,
				'title'   => $form['title'],
				'message' => self::strings( 0 )['failed'],
			);
		}

		$map                     = self::import_map();
		$map[ $id ]['forms'][]   = array(
			'efb' => $efb_id,
			'at'  => time(),
			'by'  => get_current_user_id(),
		);
		update_option( self::OPTION, $map, false );

		return array(
			'cf7'       => $id,
			'ok'        => true,
			'title'     => $form['title'],
			'efb'       => $efb_id,
			'name'      => $mapped['name'],
			'shortcode' => '[EMS_Form_Builder id=' . $efb_id . ']',
			'fields'    => $mapped['fields'],
			'settings'  => $mapped['settings'],
			'notes'     => $mapped['notes'],
		);
	}

	/**
	 * Swap the imported forms in on their pages; results gain a 'pages' list.
	 *
	 * @param array $forms   cf7_forms().
	 * @param array $swap    cf7 id => ['efb', 'name'].
	 * @param array $results Results by CF7 id (by reference).
	 * @return void
	 */
	private static function replace_pages( array $forms, array $swap, array &$results ) {
		$text  = self::strings( 0 );
		$pages = Pages::find( $forms );
		$map   = self::import_map();

		foreach ( $pages as $page ) {
			$touched = array_intersect_key( $page['forms'], $swap );
			if ( empty( $touched ) ) {
				continue;
			}
			$done = Pages::replace_on_post( $page['id'], $forms, $swap );
			$row  = array(
				'id'     => $page['id'],
				'title'  => $page['title'],
				'ok'     => $done['ok'],
				'view'   => (string) get_permalink( $page['id'] ),
				'undo'   => $done['revision'] ? admin_url( 'revision.php?revision=' . (int) $done['revision'] ) : '',
				'reason' => '',
			);
			if ( ! $done['ok'] ) {
				$row['reason'] = 'builder' === $done['reason'] ? sprintf( $text['builderPage'], $page['builder'] ) : $text['pageNoChange'];
			}
			foreach ( array_keys( $touched ) as $cf7 ) {
				$results[ $cf7 ]['pages'][] = $row;
				if ( $done['ok'] ) {
					$map[ $cf7 ]['pages'][] = array(
						'post'     => $page['id'],
						'efb'      => $swap[ $cf7 ]['efb'],
						'revision' => $done['revision'],
						'at'       => time(),
					);
				}
			}
		}
		update_option( self::OPTION, $map, false );
	}

	/*
	 * ---------------------------------------------------------------------
	 * Storage
	 * ---------------------------------------------------------------------
	 */

	/**
	 * Save a form the way the builder's insert path (Create::add_form_structure)
	 * does: sanitized with sanitize_obj_msg_efb(), JSON with unescaped
	 * Unicode, quotes escaped, one row in emsfb_form.
	 *
	 * @param string $name      Form name.
	 * @param array  $structure form_structer.
	 * @return int New form id, 0 on failure.
	 */
	public static function insert_form( $name, array $structure ) {
		global $wpdb;
		$efb = get_efbFunction();

		// The builder posts JSON and decodes it into arrays before sanitizing.
		$decoded = json_decode( wp_json_encode( $structure ), true );
		if ( ! is_array( $decoded ) ) {
			return 0;
		}
		$clean = $efb->sanitize_obj_msg_efb( $decoded );
		if ( class_exists( '\Emsfb\Privacy' ) ) {
			$clean = \Emsfb\Privacy::normalize_form_settings( $clean );
		}
		$json  = json_encode( $clean, JSON_UNESCAPED_UNICODE );
		if ( false === $json ) {
			return 0;
		}

		$row = $wpdb->insert(
			$wpdb->prefix . 'emsfb_form',
			array(
				'form_name'        => sanitize_text_field( $name ),
				'form_structer'    => str_replace( '"', '\\"', $json ),
				'form_email'       => '',
				'form_created_by'  => get_current_user_id(),
				'form_type'        => 'form',
				'form_create_date' => wp_date( 'Y-m-d H:i:s' ),
			)
		);
		if ( false === $row ) {
			return 0;
		}
		$form_id = (int) $wpdb->insert_id;
		// Same side effects as a form made in the builder (F13 privacy map, SitePilot §5.8).
		do_action( 'efb_form_created', $form_id, $clean, array( 'form_id' => $form_id, 'source' => 'cf7', 'user_id' => get_current_user_id() ) );
		return $form_id;
	}

	/**
	 * @return array The CF7 → EFB record.
	 */
	public static function import_map() {
		$map = get_option( self::OPTION, array() );
		return is_array( $map ) ? $map : array();
	}

	/**
	 * Number of Contact Form 7 forms that have no imported copy left.
	 *
	 * @return int
	 */
	public static function count_pending() {
		global $wpdb;
		$ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT ID FROM {$wpdb->posts} WHERE post_type = %s AND post_status NOT IN ('trash','auto-draft')",
				self::POST_TYPE
			)
		);
		$map     = self::import_map();
		$alive   = self::existing_efb_forms( $map );
		$pending = 0;
		foreach ( $ids as $id ) {
			$has = false;
			foreach ( self::efb_ids_for( $map, (int) $id ) as $efb_id ) {
				if ( isset( $alive[ $efb_id ] ) ) {
					$has = true;
					break;
				}
			}
			$pending += $has ? 0 : 1;
		}
		return $pending;
	}

	/**
	 * @param array $map Import record.
	 * @param int   $id  CF7 id.
	 * @return int[] EFB form ids made from it.
	 */
	private static function efb_ids_for( array $map, $id ) {
		$ids = array();
		if ( isset( $map[ $id ]['forms'] ) && is_array( $map[ $id ]['forms'] ) ) {
			foreach ( $map[ $id ]['forms'] as $entry ) {
				if ( isset( $entry['efb'] ) ) {
					$ids[] = (int) $entry['efb'];
				}
			}
		}
		return $ids;
	}

	/**
	 * Which recorded EFB forms still exist.
	 *
	 * @param array $map Import record.
	 * @return array<int,string> id => name.
	 */
	private static function existing_efb_forms( array $map ) {
		global $wpdb;
		$ids = array();
		foreach ( $map as $cf7 => $entry ) {
			foreach ( self::efb_ids_for( $map, (int) $cf7 ) as $efb_id ) {
				$ids[ $efb_id ] = true;
			}
		}
		if ( empty( $ids ) ) {
			return array();
		}
		$list  = implode( ',', array_map( 'intval', array_keys( $ids ) ) );
		$table = $wpdb->prefix . 'emsfb_form';
		$rows  = $wpdb->get_results( "SELECT form_id, form_name FROM `{$table}` WHERE form_id IN ({$list})" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- integers only.
		$alive = array();
		foreach ( (array) $rows as $row ) {
			$alive[ (int) $row->form_id ] = (string) $row->form_name;
		}
		return $alive;
	}

	/**
	 * Every Contact Form 7 form, read straight from the database so it works
	 * with Contact Form 7 deactivated.
	 *
	 * @return array cf7 id => ['id', 'title', 'hash', 'old_id'].
	 */
	private static function cf7_forms() {
		global $wpdb;
		$rows  = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT ID, post_title FROM {$wpdb->posts} WHERE post_type = %s AND post_status NOT IN ('trash','auto-draft') ORDER BY ID ASC",
				self::POST_TYPE
			)
		);
		$forms = array();
		$text  = null;
		foreach ( (array) $rows as $row ) {
			$id    = (int) $row->ID;
			$title = trim( wp_strip_all_tags( (string) $row->post_title ) );
			if ( '' === $title ) {
				$text  = null === $text ? self::strings( 0 ) : $text;
				$title = $text['untitled'];
			}
			$forms[ $id ] = array(
				'id'     => $id,
				'title'  => $title,
				'hash'   => (string) get_post_meta( $id, '_hash', true ),
				'old_id' => (int) get_post_meta( $id, '_old_cf7_unit_id', true ),
			);
		}
		return $forms;
	}

	/**
	 * @param int    $id  Post id.
	 * @param string $key Meta key.
	 * @return array
	 */
	private static function meta_array( $id, $key ) {
		$value = get_post_meta( $id, $key, true );
		return is_array( $value ) ? $value : array();
	}

	/*
	 * ---------------------------------------------------------------------
	 * Helpers
	 * ---------------------------------------------------------------------
	 */

	/**
	 * Nonce (EFB's wp_rest action) and permission, or a 403.
	 *
	 * @return void
	 */
	private static function verify_request() {
		if ( ! check_ajax_referer( 'wp_rest', 'nonce', false ) || ! self::can_use() ) {
			$efb   = function_exists( 'get_efbFunction' ) ? get_efbFunction() : null;
			$texts = is_object( $efb ) ? (array) $efb->text_efb( array( 'error403' ) ) : array();
			wp_send_json_error( array( 'message' => isset( $texts['error403'] ) ? html_entity_decode( (string) $texts['error403'], ENT_QUOTES, 'UTF-8' ) : 'Forbidden' ), 403 );
		}
	}

	/**
	 * Load the parser, mapper and page tools (AJAX only).
	 *
	 * @return void
	 */
	private static function load_parts() {
		require_once __DIR__ . '/class-Emsfb-cf7-parser.php';
		require_once __DIR__ . '/class-Emsfb-cf7-mapper.php';
		require_once __DIR__ . '/class-Emsfb-cf7-pages.php';
	}

	/**
	 * A parser that also knows the tags Contact Form 7 add-ons registered,
	 * when Contact Form 7 is running. Without it the parser still reads
	 * add-on tags by their shape, so the mapper reports them the same way.
	 *
	 * @return Parser
	 */
	private static function parser() {
		$extra = array();
		if ( class_exists( '\WPCF7_FormTagsManager' ) ) {
			$manager = \WPCF7_FormTagsManager::get_instance();
			if ( method_exists( $manager, 'collect_tag_types' ) ) {
				$extra = (array) $manager->collect_tag_types();
			}
		}
		return new Parser( $extra );
	}

	/**
	 * What the mapper needs to know about this site.
	 *
	 * @return array
	 */
	private static function environment() {
		$efb      = get_efbFunction();
		$settings = function_exists( 'get_setting_Emsfb' ) ? get_setting_Emsfb( 'decoded' ) : null;
		$keys     = array(
			'submit', 'next', 'previous', 'thanksFillingOutform', 'yad', 'trackingCode', 'error',
			'pleaseFillInRequiredFields', 'form', 'text', 'email', 'url', 'tel', 'number', 'range',
			'ddate', 'textarea', 'select', 'multiselect', 'checkbox', 'radiobutton', 'file',
			'trmCheckbox', 'heading', 'image', 'documents', 'media', 'zip', 'allformat', 'emailSendingOffDesc',
		);

		return array(
			'texts'           => (array) $efb->text_efb( $keys ),
			'rtl'             => is_rtl(),
			'admin_email'     => (string) get_option( 'admin_email' ),
			'efb_admin_email' => ( is_object( $settings ) && isset( $settings->emailSupporter ) && is_email( $settings->emailSupporter ) ) ? (string) $settings->emailSupporter : '',
			// The address EFB sends from (Email_Handler: femail, else no-reply@host).
			'efb_from'        => ( is_object( $settings ) && isset( $settings->femail ) && is_email( $settings->femail ) )
				? (string) $settings->femail
				: 'no-reply@' . str_replace( 'www.', '', (string) wp_parse_url( home_url(), PHP_URL_HOST ) ),
			'captcha_ready'   => is_object( $settings ) && isset( $settings->siteKey ) && strlen( (string) $settings->siteKey ) > 5,
			'sending_enabled' => function_exists( 'emsfb_is_email_sending_enabled_efb' ) ? (bool) emsfb_is_email_sending_enabled_efb( $settings ) : true,
			'data_source'     => function ( array $names ) {
				// Listo lists (data:countries ...) when Contact Form 7 provides them.
				$values = apply_filters( 'wpcf7_form_tag_data_option', null, $names, array() );
				return is_array( $values ) ? $values : array();
			},
		);
	}

	/**
	 * Number of fields a template has (form controls, not buttons).
	 *
	 * @param Parser $parser   Parser.
	 * @param string $template `_form`.
	 * @return int
	 */
	private static function count_fields( Parser $parser, $template ) {
		$count = 0;
		foreach ( $parser->tokenize( $template ) as $node ) {
			if ( 'tag' === $node['node'] && ! in_array( $node['tag']['basetype'], array( 'submit', 'response', 'count', 'captchac', 'recaptcha', 'stripe', 'turnstile' ), true ) ) {
				$count++;
			}
		}
		return $count;
	}
}

Importer::boot();
