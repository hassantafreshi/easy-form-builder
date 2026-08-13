<?php
/**
 * Regression test for the form surviving WordPress content filters.
 *
 * Reported from wordpress.org: a form that is correct in the admin comes apart
 * on the frontend under some themes, with paragraphs sitting between the
 * fields. wpautop() is the cause - it splits content after every closing block
 * tag and wraps each piece that does not start with a block tag in a paragraph,
 * so the plugin's marker comments and its inline scripts each collect a <p>
 * that the browser then has to close on its own.
 *
 * Run: php tests/test-shortcode-content-filters.php
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

$failures = 0;

function efb_test_ok( $label, $passed, $detail = '' ) {
	global $failures;

	if ( $passed ) {
		echo "[PASS] {$label}\n";
		return;
	}

	$failures++;
	echo "[FAIL] {$label}" . ( '' === $detail ? '' : " - {$detail}" ) . "\n";
}

/**
 * The markup between the form container's opening tag and the end of the
 * document - enough to tell whether a filter reached inside the form.
 */
function efb_test_form_body( $html ) {
	$start = strpos( $html, '<div id="body_efb_' );

	return ( false === $start ) ? '' : substr( $html, $start );
}

function efb_test_count_paragraphs( $html ) {
	return preg_match_all( '#<p[\s>]#i', $html );
}

/* ---------------------------------------------------------------------------
 * The markup guard on its own.
 * ------------------------------------------------------------------------ */

efb_test_ok(
	'helper is loaded',
	function_exists( 'emsfb_autop_safe_markup_efb' )
);

$sample = "<div class=\"a\">\n\n\t<!--startTag file-->\n\t<span>one</span>\n</div>\n\n"
	. "<script>\n\t// a line comment\n\tvar x = 1;\n\n\tvar y = 2;\n</script>\n"
	. "<textarea name=\"t\">first\nsecond</textarea>\n";

$guarded = emsfb_autop_safe_markup_efb( $sample );

efb_test_ok(
	'no newline is left outside script, style and pre',
	0 === substr_count( preg_replace( '#<(script|style|pre)\b[^>]*>.*?</\1>#is', '', $guarded ), "\n" ),
	$guarded
);

efb_test_ok(
	'script keeps the newline that ends a line comment',
	(bool) preg_match( '#// a line comment\s*\n#', $guarded ),
	$guarded
);

efb_test_ok(
	'blank lines inside a script are closed up',
	! preg_match( "#\n[ \t]*\n#", $guarded ),
	$guarded
);

efb_test_ok(
	'textarea line breaks are kept as character references',
	false !== strpos( $guarded, 'first&#10;second' ),
	$guarded
);

efb_test_ok(
	'build marker comments are dropped',
	false === strpos( $guarded, '<!--startTag' ),
	$guarded
);

$autopped = wpautop( $guarded );

efb_test_ok(
	'wpautop adds no <br> to the guarded markup',
	false === stripos( $autopped, '<br' ),
	$autopped
);

/* The shape the bug report was about: fields separated by marker comments.
 * Every piece wpautop splits out now opens with a block tag, so none of them
 * keeps the paragraph it would otherwise be given.
 *
 * A bare <script> at the top level is the one case the guard cannot cover -
 * wpautop does not count script as a block tag, so a piece starting with one
 * still collects a paragraph. It costs a margin, never a broken form, and it
 * cannot happen where the report came from: markup that goes through the
 * content filters is a placeholder by then. */
$fields = emsfb_autop_safe_markup_efb(
	"<div class=\"efb row\">\n\t<!--startTag file-->\n\t<div class=\"efb col\">\n\t\t<label>one</label>\n\t</div>\n\t<!--endTag file-->\n\n"
	. "\t<!--startTag email-->\n\t<div class=\"efb col\">\n\t\t<label>two</label>\n\t</div>\n\t<!--endTag email-->\n</div>\n"
);

efb_test_ok(
	'wpautop adds no paragraph between fields',
	efb_test_count_paragraphs( wpautop( $fields ) ) === efb_test_count_paragraphs( $fields ),
	wpautop( $fields )
);

efb_test_ok(
	'markup that is not a string is handed back untouched',
	null === emsfb_autop_safe_markup_efb( null ) && '' === emsfb_autop_safe_markup_efb( '' )
);

/* ---------------------------------------------------------------------------
 * The guard rewrites markup with regular expressions, so the edges are where
 * it can do harm: content that only looks like a tag, tags that are not
 * well formed, and being handed markup it has already been through.
 * ------------------------------------------------------------------------ */

efb_test_ok(
	'a hidden input gets a block-level parent',
	'<div>a</div><div class="efb d-none"><input type="hidden" id="x"></div><div>b</div>'
		=== emsfb_autop_safe_markup_efb( '<div>a</div><input type="hidden" id="x"><div>b</div>' )
);

efb_test_ok(
	'a visible input is never wrapped',
	false === strpos(
		emsfb_autop_safe_markup_efb( '<div>a</div><input type="text" id="x" placeholder="hidden">' ),
		'd-none'
	)
);

// The maps field emits inputs with no closing bracket. The match must stop at
// the stray "<" instead of swallowing whatever element comes next.
$malformed = emsfb_autop_safe_markup_efb(
	"<div class='efb'>\n<input type='hidden' name='a-lat' value='0'\n<input type='hidden' name='a-lng' value='0'\n<small>x</small>\n</div>"
);
efb_test_ok(
	'a malformed input is left alone and swallows nothing',
	false === strpos( $malformed, 'd-none' ) && false !== strpos( $malformed, '<small>x</small>' ),
	$malformed
);

// A textarea's content is text on the screen, so nothing may be spliced into it.
efb_test_ok(
	'markup inside a textarea is left as text',
	'<textarea><input type="hidden" id="y"></textarea>'
		=== emsfb_autop_safe_markup_efb( '<textarea><input type="hidden" id="y"></textarea>' )
);

// <pre> renders its whitespace, so unlike script and style it keeps every line.
efb_test_ok(
	'pre keeps its blank lines',
	"<pre>\nline one\n\n  indented\n</pre>" === emsfb_autop_safe_markup_efb( "<pre>\nline one\n\n  indented\n</pre>" ),
	emsfb_autop_safe_markup_efb( "<pre>\nline one\n\n  indented\n</pre>" )
);

// Closing tags inside a script body are escaped - the same string to
// JavaScript, invisible to wpautop - but the script's own closing tag is not.
$script_guarded = emsfb_autop_safe_markup_efb( "<script>var h = `<div><\/div>`;</script>" );
efb_test_ok(
	'a script keeps a usable closing tag and a hidden parent',
	false !== strpos( $script_guarded, '</script>' )
		&& 0 === strpos( $script_guarded, '<div class="efb d-none">' ),
	$script_guarded
);

$once = emsfb_autop_safe_markup_efb( '<div>a</div><input type="hidden" id="x"><script>var a = 1;</script>' );
efb_test_ok(
	'running the guard twice changes nothing the second time',
	$once === emsfb_autop_safe_markup_efb( $once ),
	emsfb_autop_safe_markup_efb( $once )
);

/* ---------------------------------------------------------------------------
 * A real form through the content filters.
 * ------------------------------------------------------------------------ */

global $wpdb;

$table   = $wpdb->prefix . 'emsfb_form';
$form_id = (int) $wpdb->get_var( "SELECT form_id FROM {$table} ORDER BY form_id DESC LIMIT 1" );

if ( ! $form_id ) {
	echo "[SKIP] no form in {$table} to render\n";
	exit( $failures > 0 ? 1 : 0 );
}

$shortcode = '[EMS_Form_Builder id="' . $form_id . '"]';

// Rendered straight from a template, no content filter in sight: the
// placeholder would never be swapped back, so the markup has to come as it is.
$direct = do_shortcode( $shortcode );

efb_test_ok(
	'a direct do_shortcode() call returns the form, not a placeholder',
	false === strpos( $direct, 'emsfb-form-' ) && false !== strpos( $direct, 'id="efbform"' )
);

efb_test_ok(
	'the rendered form carries no newline outside script, style and pre',
	0 === substr_count( preg_replace( '#<(script|style|pre)\b[^>]*>.*?</\1>#is', '', $direct ), "\n" )
);

// Core's own filter order.
$rendered = apply_filters( 'the_content', "Contact us below.\n\n{$shortcode}\n\nThanks!" );

efb_test_ok(
	'the form renders inside the_content',
	false !== strpos( $rendered, 'id="efbform"' )
);

efb_test_ok(
	'no placeholder is left in the content',
	false === strpos( $rendered, 'emsfb-form-' ),
	'a placeholder reached the page - the restore filter did not run'
);

efb_test_ok(
	'the form container is not wrapped in a paragraph',
	! preg_match( '#<p[^>]*>\s*<div id="body_efb_#', $rendered )
);

$baseline_paragraphs = efb_test_count_paragraphs( efb_test_form_body( $rendered ) );

// The reported situation: a theme that puts wpautop back after do_shortcode.
remove_filter( 'the_content', 'wpautop' );
add_filter( 'the_content', 'wpautop', 99 );

$hostile = apply_filters( 'the_content', "Contact us below.\n\n{$shortcode}\n\nThanks!" );

remove_filter( 'the_content', 'wpautop', 99 );
add_filter( 'the_content', 'wpautop' );

efb_test_ok(
	'the form still renders when wpautop runs after do_shortcode',
	false !== strpos( $hostile, 'id="efbform"' ) && false === strpos( $hostile, 'emsfb-form-' )
);

efb_test_ok(
	'wpautop running late adds no paragraph inside the form',
	efb_test_count_paragraphs( efb_test_form_body( $hostile ) ) === $baseline_paragraphs,
	'expected ' . $baseline_paragraphs . ', got ' . efb_test_count_paragraphs( efb_test_form_body( $hostile ) )
);

efb_test_ok(
	'wpautop running late adds no <br> inside the form',
	substr_count( strtolower( efb_test_form_body( $hostile ) ), '<br' )
		=== substr_count( strtolower( efb_test_form_body( $rendered ) ), '<br' )
);

// Written next to other text, so wpautop wraps the shortcode itself.
$inline = apply_filters( 'the_content', "Reach us here: {$shortcode} any time." );

efb_test_ok(
	'a shortcode written inline is lifted out of its paragraph',
	false !== strpos( $inline, 'id="efbform"' )
		&& ! preg_match( '#<p[^>]*>[^<]*<div id="body_efb_#', $inline )
);

efb_test_ok(
	'the text around an inline shortcode is kept',
	false !== strpos( $inline, 'Reach us here:' ) && false !== strpos( $inline, 'any time' )
);

// A theme is free to run the content filters over the same post twice.
$second = apply_filters( 'the_content', "Contact us below.\n\n{$shortcode}\n\nThanks!" );

efb_test_ok(
	'a second pass over the same content renders the form again',
	false !== strpos( $second, 'id="efbform"' ) && false === strpos( $second, 'emsfb-form-' )
);

echo $failures > 0 ? "\n{$failures} failing check(s)\n" : "\nall checks passed\n";

exit( $failures > 0 ? 1 : 0 );
