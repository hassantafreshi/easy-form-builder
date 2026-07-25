<?php
/*
 * Regression test: the behavioural detector must not systematically quarantine
 * legitimate recorder/file forms just because the visitor did not TYPE.
 *
 * A form built around an audio/video/screen recorder (or a file field) is driven
 * by clicking record/stop/upload and choosing a file, not by keyboard input. The
 * client reports `mediaInteraction` (see formMediaInteraction() in the public JS)
 * and the detector credits it in place of the typing signals. Bots that trip the
 * honeypot are still hard-failed regardless of the media flag.
 *
 * Run:  php tests/test-human-shield-recorder-score.php
 */

define( 'WP_USE_THEMES', false );
$wp_load = getenv( 'EFB_WP_LOAD' ) ?: 'c:/xampp/htdocs/wp/wp-load.php';
require $wp_load;

$vendor = dirname( __DIR__ ) . '/vendor/human-shield/';
require_once $vendor . 'class-Emsfb-human-shield.php';
require_once $vendor . 'class-Emsfb-human-shield-detector.php';

$shield   = Emsfb\Emsfb_Human_Shield::instance();
$detector = new Emsfb\Emsfb_Human_Shield_Detector( $shield );

$settings = get_option( 'emsfb_human_shield_settings' );
$block    = (int) $settings['block_score_below'];
$quar     = (int) $settings['quarantine_score_below'];

$ctx = array( 'sid' => 'x', 'form_id' => 160, 'route' => '/Emsfb/v1/forms/message/add', 'ip' => '1.2.3.4', 'ua' => 'ua' );

// A realistic recorder-only submit: the visitor records and clicks, never types.
$recorder = array(
	'formFieldCount' => 4, 'durationMs' => 9000, 'firstInteractionDelayMs' => 1200,
	'focusCount' => 2, 'inputCount' => 0, 'keyCount' => 0, 'pointerMoveCount' => 3,
	'pointerDistance' => 84, 'touchCount' => 0, 'clickCount' => 3, 'scrollCount' => 0,
	'pasteCount' => 0, 'fieldsTouched' => 1, 'fieldCount' => 6, 'visibilityChanges' => 0,
	'touchCapable' => false, 'webdriver' => false, 'honeypotFilled' => false,
);

$results = array();

$m = $recorder; $m['mediaInteraction'] = true;
$r = $detector->score( $m, $ctx );
$results[] = array( 'recorder submit (media) clears quarantine', $r['score'] >= $quar && ! $r['hard_fail'], "score={$r['score']}" );

$m = $recorder; $m['mediaInteraction'] = false;
$r = $detector->score( $m, $ctx );
$results[] = array( 'recorder submit (no media) would quarantine', $r['score'] < $quar, "score={$r['score']}" );

$m = $recorder; $m['mediaInteraction'] = true; $m['honeypotFilled'] = true;
$r = $detector->score( $m, $ctx );
$results[] = array( 'honeypot bot still hard_fail despite media', true === $r['hard_fail'], 'hard_fail=' . var_export( $r['hard_fail'], true ) );

$m = $recorder; $m['mediaInteraction'] = true; $m['touchCapable'] = true; $m['touchCount'] = 4;
$r = $detector->score( $m, $ctx );
$results[] = array( 'mobile recorder submit clears quarantine', $r['score'] >= $quar, "score={$r['score']}" );

$typed = array(
	'formFieldCount' => 4, 'durationMs' => 18000, 'firstInteractionDelayMs' => 800,
	'focusCount' => 4, 'inputCount' => 6, 'keyCount' => 40, 'pointerMoveCount' => 20,
	'pointerDistance' => 600, 'touchCount' => 0, 'clickCount' => 3, 'scrollCount' => 2,
	'pasteCount' => 0, 'fieldsTouched' => 4, 'fieldCount' => 4, 'visibilityChanges' => 0,
	'touchCapable' => false, 'webdriver' => false, 'honeypotFilled' => false, 'mediaInteraction' => false,
);
$r = $detector->score( $typed, $ctx );
$results[] = array( 'normal typing form still high', $r['score'] >= $quar, "score={$r['score']}" );

$empty = array_merge( $recorder, array( 'durationMs' => 500, 'focusCount' => 0, 'clickCount' => 0, 'pointerMoveCount' => 0, 'pointerDistance' => 0, 'mediaInteraction' => false ) );
$r = $detector->score( $empty, $ctx );
$results[] = array( 'no-interaction bot stays blockable', $r['score'] < $block, "score={$r['score']}" );

$fail = 0;
echo "thresholds: block_below=$block quarantine_below=$quar\n\n";
foreach ( $results as $row ) {
	list( $label, $ok, $detail ) = $row;
	if ( ! $ok ) { $fail++; }
	echo ( $ok ? 'PASS' : 'FAIL' ) . "  $label -> $detail\n";
}
echo $fail === 0 ? "\nALL PASS\n" : "\n$fail FAILURES\n";
exit( $fail === 0 ? 0 : 1 );
