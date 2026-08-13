<?php
/**
 * Seed the two forms the layout regression harness measures.
 *
 * One single-step and one two-step form, both with the step icons and the
 * progress bar switched on, every field carrying a description, and a file
 * field present - between them they cover each element the wpautop tag audit
 * (docs/audits/2026-08-12-wpautop-tag-audit.fa.md) proposes to change.
 *
 * Idempotent: the forms and their pages are looked up by name and updated in
 * place, so it can be re-run between edits without piling up rows.
 *
 * Run: php tests/seed-layout-regression-forms.php
 * Prints a JSON line the Playwright harness reads.
 */

$wp_load = dirname( __DIR__, 4 ) . '/wp-load.php';
if ( ! is_readable( $wp_load ) ) {
	echo "[SKIP] WordPress bootstrap was not found at {$wp_load}\n";
	exit( 0 );
}

$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['HTTP_HOST']      = '127.0.0.1';
$_SERVER['REMOTE_ADDR']    = '127.0.0.1';

require_once $wp_load;

global $wpdb;

$table = $wpdb->prefix . 'emsfb_form';

/**
 * Form-level settings. show_icon and show_pro_bar are inverted switches: the
 * renderer draws them when the value is anything other than 1, so 0 is what
 * puts the step strip and the progress bar - and the <br> underneath it - on
 * the page.
 */
function efb_seed_settings( $name, $steps ) {
	return array(
		'type'                => 'form',
		'steps'               => (string) $steps,
		'formName'            => $name,
		'email'               => '',
		'sendEmail'           => '0',
		'trackingCode'        => '1',
		'EfbVersion'          => '2',
		'button_single_text'  => 'Submit',
		'button_color'        => 'btn-primary',
		'icon'                => 'bXXX',
		'button_Next_text'    => 'Next',
		'button_Previous_text' => 'Previous',
		'button_Next_icon'    => 'bi-chevron-right',
		'button_Previous_icon' => 'bi-chevron-left',
		'button_state'        => $steps > 1 ? 'multi' : 'single',
		'label_text_color'    => 'text-light',
		'el_text_color'       => 'text-light',
		'message_text_color'  => 'text-muted',
		'icon_color'          => 'text-light',
		'el_height'           => 'h-l-efb',
		'email_to'            => '',
		'show_icon'           => '0',
		'show_pro_bar'        => '0',
		'captcha'             => '',
		'thank_you'           => 'msg',
		'thank_you_message'   => array(
			'icon'                     => 'bi-hand-thumbs-up',
			'thankYou'                 => 'Thanks for filling out the form.',
			'done'                     => 'You&#039;re all done',
			'trackingCode'             => 'Confirmation Code',
			'pleaseFillInRequiredFields' => 'Please fill in all required fields.',
		),
		'email_temp'          => '',
		'stateForm'           => '',
		'dShowBg'             => '0',
		'survey_chart_type'   => 'none',
		'loading_type'        => 'dots',
		'loading_color'       => '#abb8c3',
		'auto_fill'           => '0',
	);
}

function efb_seed_step( $number, $title ) {
	return array(
		'id_'                => 'qastep' . $number,
		'type'               => 'step',
		'dataId'             => (string) $number,
		'classes'            => '',
		'id'                 => (string) $number,
		'name'               => $title,
		'icon'               => 'bi-chat-right-fill',
		'step'               => (string) $number,
		'amount'             => '2',
		'EfbVersion'         => '2',
		'message'            => '',
		'label_text_size'    => 'fs-5',
		'el_text_size'       => 'fs-5',
		'label_text_color'   => 'text-muted',
		'el_text_color'      => 'text-labelEfb',
		'message_text_color' => 'text-muted',
		'icon_color'         => 'text-danger',
		'visible'            => '1',
	);
}

/**
 * Every field carries a description so the <small> under it is always rendered -
 * that element is the one the audit proposes to wrap.
 */
function efb_seed_field( $id, $type, $label, $description, $step, $order, $extra = array() ) {
	return array_merge(
		array(
			'id_'                => $id,
			'dataId'             => $id . '-id',
			'type'               => $type,
			'placeholder'        => $label,
			'value'              => '',
			'size'               => '100',
			'message'            => $description,
			'id'                 => '',
			'classes'            => '',
			'name'               => $label,
			'required'           => '0',
			'amount'             => (string) $order,
			'step'               => (string) $step,
			'label_text_size'    => 'fs-6',
			'message_text_size'  => 'fs-7',
			'label_position'     => 'up',
			'el_text_size'       => 'fs-6',
			'label_text_color'   => 'text-labelEfb',
			'el_border_color'    => 'border-d',
			'el_text_color'      => 'text-labelEfb',
			'message_text_color' => 'text-muted',
			'el_height'          => 'h-d-efb',
			'label_align'        => 'txt-left',
			'message_align'      => 'justify-content-start',
			'el_align'           => 'justify-content-start',
			'pro'                => '',
		),
		$extra
	);
}

function efb_seed_file_field( $id, $label, $description, $step, $order ) {
	return efb_seed_field(
		$id,
		'file',
		$label,
		$description,
		$step,
		$order,
		array(
			'value'      => 'document',
			'file'       => 'document',
			'mobile_size' => '100',
			'corner'     => 'efb-square',
			'icon_input' => '',
			'max_fsize'  => '8',
		)
	);
}

/** Store the structure the way the builder does: JSON with escaped quotes. */
function efb_seed_encode( array $structure ) {
	return str_replace( '"', '\\"', wp_json_encode( $structure, JSON_UNESCAPED_UNICODE ) );
}

function efb_seed_form( $form_name, array $structure ) {
	global $wpdb, $table;

	$stored  = efb_seed_encode( $structure );
	$form_id = (int) $wpdb->get_var( $wpdb->prepare( "SELECT form_id FROM {$table} WHERE form_name = %s", $form_name ) );

	if ( $form_id ) {
		$wpdb->update( $table, array( 'form_structer' => $stored ), array( 'form_id' => $form_id ) );
		return $form_id;
	}

	$wpdb->insert(
		$table,
		array(
			'form_name'       => $form_name,
			'form_structer'   => $stored,
			'form_email'      => '',
			'form_type'       => 'form',
			'form_created_by' => 1,
			'form_access_by'  => '',
			'form_create_date' => current_time( 'mysql' ),
			'status'          => 1,
		)
	);

	return (int) $wpdb->insert_id;
}

function efb_seed_page( $slug, $title, $form_id ) {
	$existing = get_page_by_path( $slug, OBJECT, 'page' );
	$content  = '[EMS_Form_Builder id="' . $form_id . '"]';

	if ( $existing ) {
		wp_update_post(
			array(
				'ID'           => $existing->ID,
				'post_content' => $content,
				'post_status'  => 'publish',
			)
		);
		return get_permalink( $existing->ID );
	}

	$page_id = wp_insert_post(
		array(
			'post_title'   => $title,
			'post_name'    => $slug,
			'post_content' => $content,
			'post_status'  => 'publish',
			'post_type'    => 'page',
		)
	);

	return get_permalink( $page_id );
}

/* --------------------------------------------------------------------------
 * Single step: text, email, textarea and a file field, all described.
 * ----------------------------------------------------------------------- */

$single = array( efb_seed_settings( 'EFB Layout QA single', 1 ) );
$single[] = efb_seed_step( 1, 'Your details' );
$single[] = efb_seed_field( 'qafullname', 'text', 'Full name', 'As it appears on your ID card.', 1, 2 );
$single[] = efb_seed_field( 'qaemail', 'email', 'Email', 'We only use this to reply to you.', 1, 3 );
$single[] = efb_seed_field( 'qamessage', 'textarea', 'Message', 'Tell us what you need in a few lines.', 1, 4 );
$single[] = efb_seed_file_field( 'qaresume', 'Attachment', 'Accepted files up to 8 MB.', 1, 5 );
/* A rating on step one, reachable without clicking through the form. Its answer
 * lives in a hidden input, which is the element the guard gives a block-level
 * parent - so the click has to keep landing in it. Borrowing a rating from
 * whichever form the site happens to have put it on step two, out of reach. */
$single[] = efb_seed_field(
	'qarating',
	'rating',
	'How did we do?',
	'Pick a star - the answer is stored in a hidden input.',
	1,
	6,
	array( 'pro' => '1', 'corner' => 'efb-square', 'icon_input' => '' )
);

$single_id  = efb_seed_form( 'EFB Layout QA single', $single );
$single_url = efb_seed_page( 'efb-layout-qa-single', 'EFB Layout QA single', $single_id );

/* --------------------------------------------------------------------------
 * Two steps: the progress bar, the step strip and the Next/Previous buttons.
 * ----------------------------------------------------------------------- */

$multi = array( efb_seed_settings( 'EFB Layout QA multi', 2 ) );
$multi[] = efb_seed_step( 1, 'Your details' );
$multi[] = efb_seed_field( 'qbfullname', 'text', 'Full name', 'As it appears on your ID card.', 1, 2 );
$multi[] = efb_seed_field( 'qbemail', 'email', 'Email', 'We only use this to reply to you.', 1, 3 );
$multi[] = efb_seed_step( 2, 'Your request' );
$multi[] = efb_seed_field( 'qbmessage', 'textarea', 'Message', 'Tell us what you need in a few lines.', 2, 4 );
$multi[] = efb_seed_file_field( 'qbresume', 'Attachment', 'Accepted files up to 8 MB.', 2, 5 );

$multi_id  = efb_seed_form( 'EFB Layout QA multi', $multi );
$multi_url = efb_seed_page( 'efb-layout-qa-multi', 'EFB Layout QA multi', $multi_id );

/* --------------------------------------------------------------------------
 * The drag-and-drop and recorder fields carry the hidden file input and the
 * "or" separator, and they only render with the Pro fields available - easier
 * to point at a form on the site that already has them than to seed one.
 * ----------------------------------------------------------------------- */

/* Ascending, so the choice does not move. Picking the newest matching form
 * meant that any form added to the site afterwards silently became the subject
 * of the test, and a saved snapshot then compared two different forms. */
$candidates = $wpdb->get_results( "SELECT form_id, form_structer FROM {$table} WHERE status = 1 ORDER BY form_id ASC" );

/**
 * Point at the first form on the site built from the given field types.
 * The rating and survey fields carry the hidden value inputs, and the
 * drag-and-drop and recorder fields carry the hidden file input and the "or"
 * separator - all of them elements the audit touches.
 */
function efb_seed_borrowed_page( $candidates, $types, $slug, $title, $skip = array() ) {
	foreach ( $candidates as $candidate ) {
		$form_id = (int) $candidate->form_id;
		if ( in_array( $form_id, $skip, true ) ) {
			continue;
		}

		$structure = str_replace( array( '\\' ), '', (string) $candidate->form_structer );
		if ( preg_match( '/"type":"(' . $types . ')"/', $structure ) ) {
			return array(
				'form_id' => $form_id,
				'url'     => efb_seed_page( $slug, $title, $form_id ),
			);
		}
	}

	return array( 'form_id' => 0, 'url' => '' );
}

$media = efb_seed_borrowed_page(
	$candidates,
	'dadfile|audio_recorder|video_recorder|screen_recorder',
	'efb-layout-qa-media',
	'EFB Layout QA media'
);

/* Only the star and point-scale fields, because those are the ones that keep
 * their answer in a hidden input - the element wrapped in a block-level parent.
 * A form merely holding radios would look like coverage without being any. */
$survey = efb_seed_borrowed_page(
	$candidates,
	'rating|pointr10',
	'efb-layout-qa-survey',
	'EFB Layout QA survey',
	array( $media['form_id'] )
);

echo wp_json_encode(
	array(
		'single' => array( 'form_id' => $single_id, 'url' => $single_url ),
		'multi'  => array( 'form_id' => $multi_id, 'url' => $multi_url ),
		'media'  => $media,
		'survey' => $survey,
	)
) . "\n";
