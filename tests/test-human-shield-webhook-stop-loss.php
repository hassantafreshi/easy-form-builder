<?php
/**
 * Regression test: the side-effect stop-loss is spent only on what is really sent.
 *
 * efb_intgrate_with_3rd_party_services_efb() asks the side-effect gate
 * (efb_shield_allow_side_effect) before webhooks, Telegram and Google Sheets.
 * The gate spends one unit of that channel's daily stop-loss per question.
 * Two bugs followed:
 *  - the webhook channel was asked twice per submission (conditional webhook
 *    rules, then the efb_after_form_integration listeners), so the daily limit
 *    and the per-confirmation-code cap allowed only half as many submissions;
 *  - every channel was asked on every submission, even for forms with no
 *    webhook, Telegram notification or Google Sheet, so a busy form without
 *    them used up the budget and blocked the forms that do send.
 *
 * Nothing is sent and nothing is written: HTTP is intercepted, options are
 * answered through pre_option_* filters, and the shield's settings and rate
 * limiter are replaced in memory for this process only.
 *
 * Run: php tests/test-human-shield-webhook-stop-loss.php
 */

$wp_load = dirname( __DIR__, 4 ) . '/wp-load.php';
if ( ! is_readable( $wp_load ) ) {
	echo "[SKIP] WordPress bootstrap was not found at {$wp_load}\n";
	exit( 0 );
}

require_once $wp_load;
require_once dirname( __DIR__ ) . '/vendor/human-shield/human-shield-efb.php';

// Load the add-on classes without creating the Telegram sender (its constructor
// creates tables).
if ( ! defined( 'EMSFB_TELEGRAM_SEND_LOADED' ) ) {
	define( 'EMSFB_TELEGRAM_SEND_LOADED', true );
}
foreach ( array( 'vendor/telegram/telegram-new-efb.php', 'vendor/googlesheet/class-Emsfb-googlesheet.php' ) as $efb_addon_file ) {
	if ( file_exists( dirname( __DIR__ ) . '/' . $efb_addon_file ) ) {
		require_once dirname( __DIR__ ) . '/' . $efb_addon_file;
	}
}

if ( ! class_exists( '\Emsfb\_Public' ) || ! class_exists( '\Emsfb\Emsfb_Human_Shield_Notification_Gate' ) ) {
	echo "[SKIP] Emsfb\\_Public or the Human Shield notification gate is not loaded\n";
	exit( 0 );
}

$passed = 0;
$failed = 0;

function efb_hs_stoploss_check( $name, $actual, $expected ) {
	global $passed, $failed;
	if ( $actual === $expected ) {
		echo "[PASS] {$name}\n";
		$passed++;
		return;
	}

	echo "[FAIL] {$name}: expected " . var_export( $expected, true ) . ', got ' . var_export( $actual, true ) . "\n";
	$failed++;
}

/** Counts consume() calls per scope and always allows. */
class Efb_Hs_Stoploss_Counting_Limiter {
	public $consumed = array();

	public function consume( $scope, $route, $form_id, $bucket_key, $limit, $window_seconds ) {
		$this->consumed[ $scope ] = isset( $this->consumed[ $scope ] ) ? $this->consumed[ $scope ] + 1 : 1;
		return array( 'allowed' => true );
	}
}

/** What one integration run did. */
class Efb_Hs_Stoploss_Recorder {
	public $asked = array();
	public $fired = array();
	public $http  = 0;

	public function asked( $channel ) {
		$counts = array_count_values( $this->asked );
		return isset( $counts[ $channel ] ) ? $counts[ $channel ] : 0;
	}

	public function fired( $hook ) {
		return isset( $this->fired[ $hook ] ) ? $this->fired[ $hook ] : 0;
	}
}

$efb_hs_hooks = array(
	'efb_shield_allow_side_effect',
	'efb_shield_side_effect_has_work',
	'efb_3rd_party_telegram_notify',
	'efb_3rd_party_google_sheet_sync',
	'efb_after_form_integration',
	'efb_conditional_webhook_before_send',
	'efb_conditional_webhook_after_send',
	'pre_http_request',
	'pre_option_emsfb_addon_AdnTLG',
	'pre_option_emsfb_telegram_enabled',
	'pre_option_emsfb_googlesheet_form_bindings',
);

function efb_hs_stoploss_reset_hooks() {
	global $wp_filter, $efb_hs_hooks;
	foreach ( $efb_hs_hooks as $hook ) {
		unset( $wp_filter[ $hook ] );
	}
}

/**
 * Runs the integration dispatch once.
 *
 * @param array $setup listeners: hooks with a listener; has_work: channel => bool
 *                     answer; deny: channels the gate vetoes; gate: 'record' or
 *                     'real'; rules: webhook rules; score: submitted lead score.
 */
function efb_hs_stoploss_run( $setup ) {
	efb_hs_stoploss_reset_hooks();
	$rec = new Efb_Hs_Stoploss_Recorder();

	add_filter( 'pre_http_request', function () use ( $rec ) {
		$rec->http++;
		return array( 'headers' => array(), 'body' => 'ok', 'response' => array( 'code' => 200, 'message' => 'OK' ), 'cookies' => array() );
	}, 10, 3 );

	foreach ( isset( $setup['listeners'] ) ? $setup['listeners'] : array() as $hook ) {
		add_action( $hook, function () use ( $rec, $hook ) {
			$rec->fired[ $hook ] = $rec->fired( $hook ) + 1;
		} );
	}

	if ( isset( $setup['has_work'] ) ) {
		$answers = $setup['has_work'];
		add_filter( 'efb_shield_side_effect_has_work', function ( $has_work, $channel ) use ( $answers ) {
			return array_key_exists( $channel, $answers ) ? $answers[ $channel ] : $has_work;
		}, 10, 2 );
	}

	if ( isset( $setup['gate'] ) && 'real' === $setup['gate'] ) {
		new \Emsfb\Emsfb_Human_Shield_Notification_Gate( \Emsfb\Emsfb_Human_Shield::instance() );
	}
	$deny = isset( $setup['deny'] ) ? $setup['deny'] : array();
	add_filter( 'efb_shield_allow_side_effect', function ( $allowed, $context ) use ( $rec, $deny ) {
		$rec->asked[] = $context['channel'];
		return in_array( $context['channel'], $deny, true ) ? false : $allowed;
	}, 5, 2 );

	$form = array( array( 'type' => 'form' ), array( 'id_' => 'lead_score', 'type' => 'number', 'name' => 'Lead score' ) );
	if ( ! empty( $setup['rules'] ) ) {
		$form[0]['webhook_rules'] = $setup['rules'];
	}
	$submitted = array( array( 'id_' => 'lead_score', 'type' => 'number', 'value' => isset( $setup['score'] ) ? (string) $setup['score'] : '80' ) );

	$public = ( new ReflectionClass( '\Emsfb\_Public' ) )->newInstanceWithoutConstructor();
	$public->id = 134;
	$method = new ReflectionMethod( '\Emsfb\_Public', 'efb_intgrate_with_3rd_party_services_efb' );
	$method->setAccessible( true );
	$method->invoke( $public, 'efb-stoploss-test', $submitted, $form, 'form_submit' );

	efb_hs_stoploss_reset_hooks();
	return $rec;
}

$efb_hs_hot_rule = array(
	array(
		'id'         => 'wr_hot',
		'name'       => 'Hot lead',
		'enabled'    => true,
		'priority'   => 5,
		'webhook_id' => 'crm_hot_lead',
		'url'        => 'https://crm.example.test/hook',
		'method'     => 'POST',
		'conditions' => array(
			'type'     => 'group',
			'operator' => 'AND',
			'items'    => array(
				array( 'type' => 'condition', 'field_id' => 'lead_score', 'compare' => 'gt', 'value' => '70' ),
			),
		),
	),
);
$efb_all_listeners = array( 'efb_3rd_party_telegram_notify', 'efb_3rd_party_google_sheet_sync', 'efb_after_form_integration' );

global $wp_filter;
$backup = array();
foreach ( $efb_hs_hooks as $hook ) {
	$backup[ $hook ] = isset( $wp_filter[ $hook ] ) ? $wp_filter[ $hook ] : null;
}

$shield              = \Emsfb\Emsfb_Human_Shield::instance();
$settings_prop       = new ReflectionProperty( $shield, 'settings' );
$assessment_prop     = new ReflectionProperty( $shield, 'request_assessment' );
$settings_prop->setAccessible( true );
$assessment_prop->setAccessible( true );
$original_settings   = $settings_prop->getValue( $shield );
$original_assessment = $assessment_prop->getValue( $shield );
$original_limiter    = $shield->rate_limiter;

try {
	// 1. A form with nothing set up never asks the gate.
	$rec = efb_hs_stoploss_run( array() );
	efb_hs_stoploss_check( 'nothing set up: the gate is never asked', count( $rec->asked ), 0 );

	// 2. Integrations that do not answer has_work (older add-ons) keep the old
	// behavior: each channel is asked once and its listeners run.
	$rec = efb_hs_stoploss_run( array( 'listeners' => $efb_all_listeners ) );
	efb_hs_stoploss_check( 'older add-ons: webhook asked once', $rec->asked( 'webhook' ), 1 );
	efb_hs_stoploss_check( 'older add-ons: telegram asked once', $rec->asked( 'telegram' ), 1 );
	efb_hs_stoploss_check( 'older add-ons: googlesheet asked once', $rec->asked( 'googlesheet' ), 1 );
	efb_hs_stoploss_check( 'older add-ons: every listener still runs', array( $rec->fired( 'efb_3rd_party_telegram_notify' ), $rec->fired( 'efb_3rd_party_google_sheet_sync' ), $rec->fired( 'efb_after_form_integration' ) ), array( 1, 1, 1 ) );

	// 3. Add-ons that say this form sends nothing are not asked and not called.
	$rec = efb_hs_stoploss_run( array( 'listeners' => $efb_all_listeners, 'has_work' => array( 'telegram' => false, 'googlesheet' => false ) ) );
	efb_hs_stoploss_check( 'no work: telegram and googlesheet are not asked', array( $rec->asked( 'telegram' ), $rec->asked( 'googlesheet' ) ), array( 0, 0 ) );
	efb_hs_stoploss_check( 'no work: telegram and googlesheet listeners are not called', array( $rec->fired( 'efb_3rd_party_telegram_notify' ), $rec->fired( 'efb_3rd_party_google_sheet_sync' ) ), array( 0, 0 ) );
	efb_hs_stoploss_check( 'no work: an add-on saying yes is still asked and called', array( $rec->asked( 'webhook' ), $rec->fired( 'efb_after_form_integration' ) ), array( 1, 1 ) );

	// 4. A matching webhook rule plus an efb_after_form_integration listener: one question.
	$rec = efb_hs_stoploss_run( array( 'listeners' => array( 'efb_after_form_integration' ), 'rules' => $efb_hs_hot_rule, 'score' => 80 ) );
	efb_hs_stoploss_check( 'matching rule + listener: webhook asked once', $rec->asked( 'webhook' ), 1 );
	efb_hs_stoploss_check( 'matching rule + listener: the webhook is sent and the listener runs', array( $rec->http, $rec->fired( 'efb_after_form_integration' ) ), array( 1, 1 ) );

	// 5. Rules that do not match, and nobody listening: nothing asked, nothing sent.
	$rec = efb_hs_stoploss_run( array( 'rules' => $efb_hs_hot_rule, 'score' => 10 ) );
	efb_hs_stoploss_check( 'rule does not match: webhook is not asked', $rec->asked( 'webhook' ), 0 );
	efb_hs_stoploss_check( 'rule does not match: no request is made', $rec->http, 0 );

	// 6. A vetoed webhook channel blocks both the rules and the listeners.
	$rec = efb_hs_stoploss_run( array( 'listeners' => $efb_all_listeners, 'rules' => $efb_hs_hot_rule, 'score' => 80, 'deny' => array( 'webhook' ) ) );
	efb_hs_stoploss_check( 'vetoed webhook: asked once', $rec->asked( 'webhook' ), 1 );
	efb_hs_stoploss_check( 'vetoed webhook: no request, listener not called', array( $rec->http, $rec->fired( 'efb_after_form_integration' ) ), array( 0, 0 ) );
	efb_hs_stoploss_check( 'vetoed webhook: telegram and googlesheet still asked', array( $rec->asked( 'telegram' ), $rec->asked( 'googlesheet' ) ), array( 1, 1 ) );

	// 7. The real gate spends units only on what is sent.
	$settings                             = $shield->defaults();
	$settings['enabled']                  = 1;
	$settings['mode']                     = 'soft_block';
	$settings['webhook_daily_stop_loss']  = 500;
	$settings['telegram_daily_stop_loss'] = 300;
	$settings['recipient_daily_cap']      = 50;
	$settings_prop->setValue( $shield, $settings );
	$assessment_prop->setValue( $shield, null );

	$limiter              = new Efb_Hs_Stoploss_Counting_Limiter();
	$shield->rate_limiter = $limiter;
	efb_hs_stoploss_run( array( 'gate' => 'real', 'listeners' => array( 'efb_3rd_party_telegram_notify', 'efb_3rd_party_google_sheet_sync' ), 'has_work' => array( 'telegram' => false, 'googlesheet' => false ), 'rules' => $efb_hs_hot_rule, 'score' => 10 ) );
	efb_hs_stoploss_check( 'real gate, form that sends nothing: no unit spent', $limiter->consumed, array() );

	$limiter              = new Efb_Hs_Stoploss_Counting_Limiter();
	$shield->rate_limiter = $limiter;
	efb_hs_stoploss_run( array( 'gate' => 'real', 'listeners' => $efb_all_listeners, 'has_work' => array( 'telegram' => true, 'googlesheet' => false ), 'rules' => $efb_hs_hot_rule, 'score' => 80 ) );
	ksort( $limiter->consumed );
	efb_hs_stoploss_check(
		'real gate, webhook + telegram form: one unit each, none for googlesheet',
		$limiter->consumed,
		array(
			'side_effect_daily_telegram'     => 1,
			'side_effect_daily_webhook'      => 1,
			'side_effect_recipient_telegram' => 1,
			'side_effect_recipient_webhook'  => 1,
		)
	);

	// 8. The Telegram add-on's answer matches its own handler.
	if ( class_exists( '\Emsfb\telegramsendefb' ) && method_exists( '\Emsfb\telegramsendefb', 'efb_side_effect_has_work' ) ) {
		$telegram = ( new ReflectionClass( '\Emsfb\telegramsendefb' ) )->newInstanceWithoutConstructor();
		$tg = function ( $addon, $enabled, $form_flag, $channel = 'telegram' ) use ( $telegram ) {
			efb_hs_stoploss_reset_hooks();
			add_filter( 'pre_option_emsfb_addon_AdnTLG', function () use ( $addon ) { return $addon; } );
			add_filter( 'pre_option_emsfb_telegram_enabled', function () use ( $enabled ) { return $enabled; } );
			$fields = null === $form_flag ? array( array( 'type' => 'form' ) ) : array( array( 'type' => 'form', 'telegramnoti' => $form_flag ) );
			$context = array( 'form_id' => 134, 'form_fields' => $fields, 'event_type' => 'form_submit' );
			$answer  = $telegram->efb_side_effect_has_work( null, $channel, $context );
			$handled = $answer ? null : $telegram->efb_handle_form_event_notification( $context );
			efb_hs_stoploss_reset_hooks();
			return array( $answer, $handled );
		};
		efb_hs_stoploss_check( 'telegram add-on: on for the form = has work', $tg( 1, '1', '1' )[0], true );
		efb_hs_stoploss_check( 'telegram add-on: form without telegramnoti = no work, handler skips', $tg( 1, '1', null ), array( false, false ) );
		efb_hs_stoploss_check( 'telegram add-on: telegramnoti 0 = no work, handler skips', $tg( 1, '1', '0' ), array( false, false ) );
		efb_hs_stoploss_check( 'telegram add-on: switched off globally = no work, handler skips', $tg( 1, '0', '1' ), array( false, false ) );
		efb_hs_stoploss_check( 'telegram add-on: other channels are left alone', $tg( 1, '1', '1', 'webhook' )[0], null );
	} else {
		echo "[SKIP] Telegram add-on files are not present\n";
	}

	// 9. The Google Sheets add-on's answer.
	if ( class_exists( '\Emsfb\GoogleSheetAddon' ) && method_exists( '\Emsfb\GoogleSheetAddon', 'side_effect_has_work' ) ) {
		$sheets = ( new ReflectionClass( '\Emsfb\GoogleSheetAddon' ) )->newInstanceWithoutConstructor();
		$gs = function ( $bindings, $channel = 'googlesheet' ) use ( $sheets ) {
			efb_hs_stoploss_reset_hooks();
			add_filter( 'pre_option_emsfb_googlesheet_form_bindings', function () use ( $bindings ) { return $bindings; } );
			$answer = $sheets->side_effect_has_work( null, $channel, array( 'form_id' => 134 ) );
			efb_hs_stoploss_reset_hooks();
			return $answer;
		};
		efb_hs_stoploss_check( 'googlesheet add-on: enabled binding = has work', $gs( array( 134 => array( 'enabled' => 1, 'spreadsheet_id' => 'x' ) ) ), true );
		efb_hs_stoploss_check( 'googlesheet add-on: paused binding = no work', $gs( array( 134 => array( 'enabled' => 0 ) ) ), false );
		efb_hs_stoploss_check( 'googlesheet add-on: another form bound only = no work', $gs( array( 7 => array( 'enabled' => 1 ) ) ), false );
		efb_hs_stoploss_check( 'googlesheet add-on: other channels are left alone', $gs( array(), 'telegram' ), null );
	} else {
		echo "[SKIP] Google Sheets add-on files are not present\n";
	}
} finally {
	$settings_prop->setValue( $shield, $original_settings );
	$assessment_prop->setValue( $shield, $original_assessment );
	$shield->rate_limiter = $original_limiter;
	foreach ( $backup as $hook => $value ) {
		if ( null === $value ) {
			unset( $wp_filter[ $hook ] );
		} else {
			$wp_filter[ $hook ] = $value;
		}
	}
}

echo "\nRESULTS: {$passed} passed, {$failed} failed\n";
exit( $failed > 0 ? 1 : 0 );
