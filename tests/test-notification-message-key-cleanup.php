<?php
/**
 * Regression test: the "message received by the user" text is stripped from the saved form JSON.
 *
 * The SMS and Telegram blocks in the form-save paths read their three notification messages out of
 * $valp[0], hand them to the add-on's contact table, and unset them so the form structure does not
 * carry a second copy. Two of the three keys were unset correctly; the third read
 * `*_msg_recived_usr` but unset `*_msg_recived_user`, a key nothing ever writes, so that message
 * stayed in form_structer on every save. The builder reloads the value from the contact table
 * (class-Emsfb-admin.php sets $decoded_form[0]->*_msg_recived_usr), which is why nobody noticed.
 *
 * The unset of the legacy `*_msg_recived_user` spelling stays in place on purpose: old rows may
 * still hold it, and removing that line would leave it in their JSON forever.
 *
 * Pure source scan - no WordPress bootstrap, no database, no settings touched.
 * Run: php tests/test-notification-message-key-cleanup.php
 */

$passed = 0;
$failed = 0;

function efb_key_assert( $name, $condition ) {
	global $passed, $failed;
	if ( $condition ) {
		echo "[PASS] {$name}\n";
		$passed++;
		return;
	}
	echo "[FAIL] {$name}\n";
	$failed++;
}

$plugin_dir = dirname( __DIR__ );

/*
 * Each case: the file, the block's guard, and the message key prefix. Every prefix must be read
 * and unset with the same spelling.
 */
$cases = array(
	array( 'includes/admin/class-Emsfb-admin.php', 'sms' ),
	array( 'includes/admin/class-Emsfb-admin.php', 'telegram' ),
	array( 'includes/admin/class-Emsfb-create.php', 'sms' ),
);

foreach ( $cases as $case ) {
	list( $relative, $prefix ) = $case;
	$path   = $plugin_dir . '/' . $relative;
	$source = file_get_contents( $path );
	$label  = basename( $relative ) . " [{$prefix}]";

	efb_key_assert( "{$label}: file is readable", is_string( $source ) && '' !== $source );

	$read_key   = "\$valp[0]['{$prefix}_msg_recived_usr']";
	$unset_key  = "unset(\$valp[0]['{$prefix}_msg_recived_usr']);";
	$legacy_key = "unset(\$valp[0]['{$prefix}_msg_recived_user']);";

	efb_key_assert( "{$label}: reads {$prefix}_msg_recived_usr", false !== strpos( $source, $read_key ) );
	efb_key_assert( "{$label}: unsets the key it reads", false !== strpos( $source, $unset_key ) );
	efb_key_assert( "{$label}: still clears the legacy _user spelling", false !== strpos( $source, $legacy_key ) );

	// The sibling messages were always unset correctly; they guard against a future copy-paste.
	foreach ( array( 'msg_new_noti', 'msg_responsed_noti' ) as $sibling ) {
		$sibling_unset = "unset(\$valp[0]['{$prefix}_{$sibling}']);";
		efb_key_assert( "{$label}: unsets {$prefix}_{$sibling}", false !== strpos( $source, $sibling_unset ) );
	}
}

/*
 * The add-on reads the message from its own contact table, so the builder keeps working even
 * though the key no longer travels inside form_structer.
 */
$admin_source = file_get_contents( $plugin_dir . '/includes/admin/class-Emsfb-admin.php' );
foreach ( array( 'sms', 'telegram' ) as $prefix ) {
	efb_key_assert(
		"class-Emsfb-admin.php: refills {$prefix}_msg_recived_usr for the builder",
		false !== strpos( $admin_source, "{$prefix}_msg_recived_usr   = isset(" )
	);
}

echo "\n{$passed} passed, {$failed} failed\n";
exit( $failed > 0 ? 1 : 0 );
