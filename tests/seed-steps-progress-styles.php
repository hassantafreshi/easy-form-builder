<?php
/**
 * Seed one form per steps/progress style combination worth looking at.
 *
 * The six styles multiply out to nine combinations, which is more than anyone
 * needs to look at; these cover every distinct piece of layout instead: each
 * steps style once at three steps, each of them again past the ten-step
 * compact threshold, one form past twenty where the dots go dense, one form
 * for each way the two visibility toggles can be set, and a single-step form,
 * where the row is nothing but the step and its Finish.
 *
 * Idempotent: forms and pages are looked up by name and updated in place, so
 * re-running between edits does not pile up rows.
 *
 * Run: php tests/seed-steps-progress-styles.php
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
 * show_icon and show_pro_bar are inverted switches: the renderer draws the
 * steps row and the progress block when the value is anything other than 1,
 * so '0' is what puts both on the page.
 */
function efb_sp_settings( $name, $steps, $steps_style, $progress_style, $show_steps = true, $show_progress = true ) {
	return array(
		'type'                 => 'form',
		'steps'                => (string) $steps,
		'formName'             => $name,
		'email'                => '',
		'sendEmail'            => '0',
		'EfbVersion'           => '2',
		'button_single_text'   => 'Submit',
		'button_color'         => 'btn-primary',
		'icon'                 => 'bXXX',
		'button_Next_text'     => 'Next',
		'button_Previous_text' => 'Previous',
		'button_Next_icon'     => 'bi-chevron-right',
		'button_Previous_icon' => 'bi-chevron-left',
		'button_state'         => $steps > 1 ? 'multi' : 'single',
		'label_text_color'     => 'text-darkb',
		'el_text_color'        => 'text-labelEfb',
		'message_text_color'   => 'text-muted',
		'icon_color'           => 'text-pinkEfb',
		'el_height'            => 'h-d-efb',
		'show_icon'            => $show_steps ? '0' : '1',
		'show_pro_bar'         => $show_progress ? '0' : '1',
		'steps_style'          => $steps_style,
		'progress_style'       => $progress_style,
		'prg_bar_color'        => 'btn-colorDEfb-4636f1',
		'captcha'              => '',
		'thank_you'            => 'msg',
		'thank_you_message'    => array(
			'icon'                       => 'bi-hand-thumbs-up',
			'thankYou'                   => 'Thanks for filling out the form.',
			'done'                       => 'You&#039;re all done',
			'trackingCode'               => 'Confirmation Code',
			'pleaseFillInRequiredFields' => 'Please fill in all required fields.',
		),
		'stateForm'            => '',
		'dShowBg'              => '0',
		'loading_type'         => 'dots',
		'loading_color'        => '#abb8c3',
	);
}

function efb_sp_step( $number, $title ) {
	return array(
		'id_'                => (string) $number,
		'type'               => 'step',
		'dataId'             => (string) $number,
		'classes'            => '',
		'id'                 => (string) $number,
		'name'               => $title,
		'icon'               => 'bi-ui-checks-grid',
		'step'               => (string) $number,
		'amount'             => (string) $number,
		'EfbVersion'         => '2',
		'message'            => '',
		'label_text_size'    => 'fs-5',
		'el_text_size'       => 'fs-5',
		'label_text_color'   => 'text-darkb',
		'el_text_color'      => 'text-labelEfb',
		'message_text_color' => 'text-muted',
		'icon_color'         => 'text-pinkEfb',
		'visible'            => '1',
	);
}

function efb_sp_field( $id, $label, $step, $order ) {
	return array(
		'id_'                => $id,
		'dataId'             => $id . '-id',
		'type'               => 'text',
		'placeholder'        => $label,
		'value'              => '',
		'size'               => '100',
		'message'            => '',
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
	);
}

/** Store the structure the way the builder does: JSON with escaped quotes. */
function efb_sp_encode( array $structure ) {
	return str_replace( '"', '\\"', wp_json_encode( $structure, JSON_UNESCAPED_UNICODE ) );
}

function efb_sp_save( $form_name, array $structure ) {
	global $wpdb, $table;

	$stored  = efb_sp_encode( $structure );
	$form_id = (int) $wpdb->get_var( $wpdb->prepare( "SELECT form_id FROM {$table} WHERE form_name = %s", $form_name ) );

	if ( $form_id ) {
		$wpdb->update( $table, array( 'form_structer' => $stored ), array( 'form_id' => $form_id ) );
		return $form_id;
	}

	$wpdb->insert(
		$table,
		array(
			'form_name'        => $form_name,
			'form_structer'    => $stored,
			'form_email'       => '',
			'form_type'        => 'form',
			'form_created_by'  => 1,
			'form_access_by'   => '',
			'form_create_date' => current_time( 'mysql' ),
			'status'           => 1,
		)
	);

	return (int) $wpdb->insert_id;
}

function efb_sp_page( $slug, $title, $form_id ) {
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

/** Step titles long enough to be worth truncating, short enough to read. */
$titles = array(
	'Contact details', 'Your request', 'Shipping', 'Billing', 'Preferences',
	'Attachments', 'Delivery window', 'Payment', 'Review', 'Extras',
	'Warranty', 'Accessories', 'Insurance', 'Installation', 'Support plan',
	'Referral', 'Newsletter', 'Survey', 'Consent', 'Notes',
	'Contacts', 'Summary',
);

function efb_sp_build( $label, $slug, $steps, $steps_style, $progress_style, $show_steps = true, $show_progress = true ) {
	global $titles;

	$name      = 'EFB Steps QA ' . $label;
	$structure = array( efb_sp_settings( $name, $steps, $steps_style, $progress_style, $show_steps, $show_progress ) );
	$order     = $steps + 1;

	for ( $i = 1; $i <= $steps; $i++ ) {
		$structure[] = efb_sp_step( $i, isset( $titles[ $i - 1 ] ) ? $titles[ $i - 1 ] : 'Step ' . $i );
		$structure[] = efb_sp_field( 'sp' . $slug . 'f' . $i, 'Field ' . $i, $i, $order++ );
	}

	$form_id = efb_sp_save( $name, $structure );

	return array(
		'label'          => $label,
		'form_id'        => $form_id,
		'steps'          => $steps,
		'steps_style'    => $steps_style,
		'progress_style' => $progress_style,
		'show_steps'     => $show_steps,
		'show_progress'  => $show_progress,
		'url'            => efb_sp_page( 'efb-steps-qa-' . $slug, $name, $form_id ),
	);
}

$cases = array(
	efb_sp_build( 'circles + bar', 'circles-bar', 3, 'circles', 'bar' ),
	efb_sp_build( 'pills + segments', 'pills-segments', 3, 'pills', 'segments' ),
	efb_sp_build( 'chevrons + ring', 'chevrons-ring', 3, 'chevrons', 'ring' ),
	efb_sp_build( 'circles + bar, 12 steps', 'many-circles', 12, 'circles', 'bar' ),
	efb_sp_build( 'pills + segments, 12 steps', 'many-pills', 12, 'pills', 'segments' ),
	efb_sp_build( 'chevrons + ring, 12 steps', 'many-chevrons', 12, 'chevrons', 'ring' ),
	efb_sp_build( 'circles + bar, 22 steps', 'dense-circles', 22, 'circles', 'bar' ),

	/* The two toggles are independent of the two pickers, and each half has to
	 * stand on its own: a progress bar with no steps row above it still needs
	 * the caption that names the step, and a steps row with no bar under it
	 * still has to know how far along it is. */
	efb_sp_build( 'progress only', 'progress-only', 4, 'circles', 'ring', false, true ),
	efb_sp_build( 'steps only', 'steps-only', 4, 'pills', 'bar', true, false ),
	efb_sp_build( 'neither', 'neither', 3, 'chevrons', 'segments', false, false ),
	/* One step is the degenerate case: the row is the step and its Finish. */
	efb_sp_build( 'single step', 'single-step', 1, 'circles', 'bar' ),
);

echo wp_json_encode( array( 'cases' => $cases ) ), "\n";
