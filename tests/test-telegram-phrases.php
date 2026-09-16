<?php
/**
 * Regression test: every phrase the Telegram add-on shows has a value.
 *
 * Three defects this locks down:
 *  1. text_efb( array(...) ) answers from the core base array only, so the 13 keys the add-on
 *     owns came back missing and handlers sent empty messages (verify_bot_token_efb() replied
 *     with error => null and an "Undefined array key" notice).
 *  2. telegram-efb.js read 11 keys that were defined nowhere, so they showed their English
 *     fallback in every language.
 *  3. 29 literal gettext calls used the core text domain, but 15 of their 18 msgids are not in
 *     the core translation project, so they never translated.
 *
 * No Telegram traffic, no setting written.
 * Run: php tests/test-telegram-phrases.php
 */

$wp_load = dirname( __DIR__, 4 ) . '/wp-load.php';
if ( ! is_readable( $wp_load ) ) {
	echo "[SKIP] WordPress bootstrap was not found at {$wp_load}\n";
	exit( 0 );
}
if ( ! defined( 'DISABLE_WP_CRON' ) ) {
	define( 'DISABLE_WP_CRON', true );
}
require_once $wp_load;
add_filter( 'pre_http_request', function () { return new WP_Error( 'blocked', 'no Telegram traffic in tests' ); }, 1 );

$passed = 0;
$failed = 0;
function efb_tg_assert( $name, $actual, $expected ) {
	global $passed, $failed;
	if ( $actual === $expected ) {
		echo "[PASS] {$name}\n";
		$passed++;
		return;
	}
	echo "[FAIL] {$name}: expected " . var_export( $expected, true ) . ', got ' . var_export( $actual, true ) . "\n";
	$failed++;
}

// Production order (class-Emsfb.php ~411): the admin class first, then the sender.
$addon = EMSFB_PLUGIN_DIRECTORY . 'vendor/telegram/';
require_once $addon . 'class-Emsfb-telegram.php';
require_once $addon . 'telegram-new-efb.php';
$efb = get_efbFunction();

// 1. Every key the add-on asks for resolves.
$lists = array(
	'telegram-new-efb.php verify_bot_token_efb'       => array( 'botTokenEmpty', 'invalidToken', 'unknownError', 'telegramConnectionError' ),
	'class-Emsfb-telegram.php send_telegram_admin_efb' => array( 'error403', 'somethingWentWrongPleaseRefresh', 'messageSent', 'pleaseFillInRequiredFields', 'telegramConfigError' ),
	'class-Emsfb-telegram.php check_for_new_updates'   => array( 'telegramBotNotConfigured', 'telegramConnectionError', 'invalidTelegramResponse' ),
	'class-Emsfb-telegram.php send_by_phone'           => array( 'telegramUserNotFound', 'telegramUserNotVerified', 'messageSent', 'telegramSendError' ),
	'class-Emsfb-telegram.php telegram_activate'       => array( 'invalidSecurityNonce', 'invalidFormId', 'telegramBotNotConfigured', 'telegramActivationSuccess' ),
	'class-Emsfb-telegram.php telegram_check_status'   => array( 'invalidSecurityNonce', 'invalidFormId' ),
);
foreach ( $lists as $where => $keys ) {
	$lang  = \Emsfb\efb_telegram_phrases_efb( $keys );
	$empty = array();
	foreach ( $keys as $key ) {
		if ( ! isset( $lang[ $key ] ) || '' === trim( (string) $lang[ $key ] ) ) {
			$empty[] = $key;
		}
	}
	efb_tg_assert( "{$where}: every requested phrase has a value", $empty, array() );
}

// A key the core base array answers keeps exactly the value it had before.
$base = $efb->text_efb( array( 'messageSent', 'error403' ) );
$via  = \Emsfb\efb_telegram_phrases_efb( array( 'messageSent', 'error403', 'telegramConfigError' ) );
efb_tg_assert( 'base phrases are unchanged (messageSent)', $via['messageSent'], $base['messageSent'] );
efb_tg_assert( 'base phrases are unchanged (error403)', $via['error403'], $base['error403'] );
efb_tg_assert( 'an add-on phrase is filled in', '' !== trim( $via['telegramConfigError'] ), true );

// What the caller actually sends back to the browser.
$sender = isset( $GLOBALS['emsfb_telegram_sender'] ) ? $GLOBALS['emsfb_telegram_sender'] : new \Emsfb\telegramsendefb();
$result = $sender->verify_bot_token_efb( '' );
efb_tg_assert( 'verify_bot_token_efb("") reports invalid', empty( $result['valid'] ), true );
efb_tg_assert( 'verify_bot_token_efb("") returns a message, not null', is_string( $result['error'] ) && '' !== trim( $result['error'] ), true );

// 2. The keys telegram-efb.js reads are real phrases now, with the JS fallback as English source.
$js       = file_get_contents( $addon . 'assets/js/telegram-efb.js' );
$provider = EMSFB_PLUGIN_DIRECTORY . 'vendor/telegram/telegram-phrases-efb.php';
require_once $provider;
$group = \Emsfb_Addon_Phrases_Telegram::group();
preg_match_all( "/efb_var\.text\.([A-Za-z0-9_]+)\s*\|\|\s*'((?:[^'\\\\]|\\\\.)*)'/", $js, $m, PREG_SET_ORDER );
/* The keys this migration turned from a JS-only fallback into a phrase. Their English must stay
 * exactly what the script showed before. Older keys are not compared: two of them always differed
 * from their fallback (default_test_message has no emoji, no_activity says "No activity found"),
 * the phrase is what the screen shows, and English sources are not being changed here. */
$new_phrase_keys = array( 'instant_alerts', 'all_forms', 'secure', 'settings_desc', 'enable_desc', 'test_desc', 'activity_desc', 'help_title', 'help_desc', 'sent', 'no_activity_desc' );
$missing_keys = array();
$wrong_source = array();
foreach ( $m as $hit ) {
	$key      = $hit[1];
	$fallback = str_replace( array( "\\'", '\\\\' ), array( "'", '\\' ), $hit[2] );
	if ( ! isset( $group[ $key ] ) ) {
		// Keys the core base array owns are not the add-on's business.
		$core = $efb->text_efb( array( $key ) );
		if ( ! isset( $core[ $key ] ) ) {
			$missing_keys[] = $key;
		}
		continue;
	}
	if ( ! in_array( $key, $new_phrase_keys, true ) ) {
		continue;
	}
	if ( html_entity_decode( $group[ $key ], ENT_QUOTES | ENT_HTML5, 'UTF-8' ) !== html_entity_decode( $fallback, ENT_QUOTES | ENT_HTML5, 'UTF-8' ) ) {
		$wrong_source[ $key ] = array( 'provider' => $group[ $key ], 'js' => $fallback );
	}
}
efb_tg_assert( 'every key telegram-efb.js reads is defined', $missing_keys, array() );
efb_tg_assert( 'the 11 new phrases keep the exact English the script showed', array_keys( $wrong_source ), array() );

// 3. The add-on's own literal strings use the add-on's text domain.
$class_source = file_get_contents( $addon . 'class-Emsfb-telegram.php' );
efb_tg_assert( 'no literal gettext call is left on the core domain', substr_count( $class_source, "'easy-form-builder'" ), 0 );
efb_tg_assert( 'literal gettext calls use efb-telegram', substr_count( $class_source, "'efb-telegram'" ) > 20, true );
$telegram_admin = new \Emsfb\telegramlistefb();
$telegram_admin->load_i18n_efb();
efb_tg_assert(
	'the add-on loads its own text domain for those strings',
	is_textdomain_loaded( 'efb-telegram' ) || 'en_US' === determine_locale(),
	true
);

// The @WSYourIDBot decision reaches both the phrase and the JS fallback.
efb_tg_assert( 'private_step_1 names @WSYourIDBot', $group['private_step_1'], 'Message @WSYourIDBot' );
efb_tg_assert( 'the JS fallback names @WSYourIDBot', false !== strpos( $js, "private_step_1 || 'Message @WSYourIDBot'" ), true );
efb_tg_assert( 'no @userinfobot is left in the add-on', substr_count( $js . $class_source . file_get_contents( $provider ), '@userinfobot' ), 0 );

echo "\nRESULTS: {$passed} passed, {$failed} failed\n";
exit( $failed > 0 ? 1 : 0 );
