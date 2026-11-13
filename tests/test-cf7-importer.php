<?php
/**
 * Import from Contact Form 7: the template parser, the field mapping (every
 * tag and option), the form settings, the page embed swap, the storage format
 * and the core routing hook.
 *
 * Contact Form 7 itself is not needed: the importer reads its forms straight
 * from the database, and these tests feed it templates directly.
 *
 * Writes one emsfb_form row for the storage round trip and deletes it again.
 *
 * Run from inside a WordPress install (the plugin active), or with
 * EFB_WP_LOAD pointing at wp-load.php:
 *   php tests/test-cf7-importer.php
 */

$wp_load = getenv( 'EFB_WP_LOAD' ) ?: dirname( __DIR__, 4 ) . '/wp-load.php';
if ( ! is_readable( $wp_load ) ) {
	echo "[SKIP] WordPress bootstrap was not found at {$wp_load}\n";
	exit( 0 );
}
$_SERVER['HTTP_HOST']   = isset( $_SERVER['HTTP_HOST'] ) ? $_SERVER['HTTP_HOST'] : '127.0.0.1';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
require_once $wp_load;

$dir = dirname( __DIR__ ) . '/includes/importers/cf7/';
foreach ( array( 'Parser' => 'parser', 'Mapper' => 'mapper', 'Pages' => 'pages', 'Importer' => 'importer' ) as $class => $file ) {
	if ( ! class_exists( '\\Emsfb\\Importers\\CF7\\' . $class ) ) {
		require_once $dir . 'class-Emsfb-cf7-' . $file . '.php';
	}
}

use Emsfb\Importers\CF7\Parser;
use Emsfb\Importers\CF7\Mapper;
use Emsfb\Importers\CF7\Pages;
use Emsfb\Importers\CF7\Importer;

$passed = 0;
$failed = 0;
function efb_cf7t( $label, $condition, $detail = '' ) {
	global $passed, $failed;
	if ( $condition ) {
		$passed++;
		echo "[PASS] {$label}\n";
		return;
	}
	$failed++;
	echo "[FAIL] {$label}" . ( '' !== $detail ? ' -- ' . ( is_string( $detail ) ? $detail : wp_json_encode( $detail, JSON_UNESCAPED_UNICODE ) ) : '' ) . "\n";
}

/** Map a template (and optional mail settings) with a fixed environment. */
function efb_cf7t_map( $template, array $form = array(), array $env = array() ) {
	$mapper = new Mapper(
		new Parser( isset( $env['extra_types'] ) ? $env['extra_types'] : array() ),
		array_merge(
			array(
				'texts'           => array( 'submit' => 'Submit', 'trmCheckbox' => 'Terms' ),
				'rtl'             => false,
				'admin_email'     => 'admin@example.com',
				'efb_admin_email' => '',
				'efb_from'        => 'no-reply@example.com',
				'captcha_ready'   => false,
				'sending_enabled' => true,
			),
			$env
		)
	);
	return $mapper->map( array_merge( array( 'title' => 'T', 'form' => $template ), $form ) );
}

/** Fields (no form row, step or options) of a mapped form. */
function efb_cf7t_fields( array $mapped ) {
	return array_values(
		array_filter(
			array_slice( $mapped['structure'], 2 ),
			function ( $row ) {
				return 'option' !== $row['type'];
			}
		)
	);
}

/** Options of a field. */
function efb_cf7t_options( array $mapped, $parent_id ) {
	return array_values(
		array_filter(
			$mapped['structure'],
			function ( $row ) use ( $parent_id ) {
				return isset( $row['parent'] ) && $row['parent'] === $parent_id;
			}
		)
	);
}

function efb_cf7t_notes_match( array $mapped, $needle ) {
	foreach ( $mapped['notes'] as $note ) {
		if ( false !== stripos( $note, $needle ) ) {
			return true;
		}
	}
	return false;
}

/* =========================================================================
 * 1. Parser
 * ====================================================================== */
echo "\n--- parser ---\n";
$p = new Parser();

$default = "<label> Your name\n    [text* your-name autocomplete:name] </label>\n\n<label> Your email\n    [email* your-email autocomplete:email] </label>\n\n<label> Subject\n    [text* your-subject] </label>\n\n<label> Your message (optional)\n    [textarea your-message] </label>\n\n[submit \"Submit\"]";
$nodes   = $p->tokenize( $default );
$tags    = array_values( array_filter( $nodes, function ( $n ) { return 'tag' === $n['node']; } ) );
efb_cf7t( 'default CF7 template: five tags in order', array( 'text*', 'email*', 'text*', 'textarea', 'submit' ) === array_map( function ( $n ) { return $n['tag']['type']; }, $tags ) );
efb_cf7t( 'required "*" is read', true === $tags[0]['tag']['required'] && false === $tags[3]['tag']['required'] );
efb_cf7t( 'name and options split', 'your-name' === $tags[0]['tag']['name'] && array( 'autocomplete:name' ) === $tags[0]['tag']['options'] );
efb_cf7t( 'submit has no name, its value is the button label', '' === $tags[4]['tag']['name'] && array( 'Submit' ) === $tags[4]['tag']['values'] );
efb_cf7t( 'markup between tags is kept in order', 'html' === $nodes[0]['node'] && false !== strpos( $nodes[0]['html'], 'Your name' ) );

$t = $p->read_tag( '[select* menu-1 include_blank "One" \'Two two\' "Label|value" "a]b"]', 0 );
efb_cf7t( 'double and single quoted values, "]" inside quotes', array( 'One', 'Two two', 'Label', 'a]b' ) === $t['values'], $t['values'] );
efb_cf7t( 'pipes: visible text before "|", email value after', array( 'Label', 'value' ) === $t['pipes'][2] );
efb_cf7t( 'bare option kept', Parser::has_option( $t, 'include_blank' ) );

$t = $p->read_tag( '[textarea msg]Default text[/textarea] tail', 0 );
efb_cf7t( 'enclosed content [textarea]…[/textarea]', 'Default text' === $t['content'] && '[textarea msg]Default text[/textarea]' === $t['raw'] );
$t = $p->read_tag( '[textarea* msg]c[/textarea*]', 0 );
efb_cf7t( 'enclosed content with the starred closing tag', 'c' === $t['content'] );
$t = $p->read_tag( '[acceptance a] I agree <a href="https://e.x">terms</a> [/acceptance]', 0 );
efb_cf7t( 'acceptance content keeps its markup', false !== strpos( $t['content'], '<a href="https://e.x">terms</a>' ) );
$t = $p->read_tag( '[text a] some text [submit]', 0 );
efb_cf7t( 'no content when another tag comes first', '' === $t['content'] && '[text a]' === $t['raw'] );
$t = $p->read_tag( '[text x /]', 0 );
efb_cf7t( 'self-closing "/" form', 'x' === $t['name'] && '' === $t['content'] );

$escaped = $p->tokenize( 'Use [[text escaped]] literally' );
efb_cf7t( '[[escaped]] is text, not a tag', 1 === count( $escaped ) && 'html' === $escaped[0]['node'] && false !== strpos( $escaped[0]['html'], '[text escaped]' ) );
$t = $p->read_tag( '[textual x]', 0 );
efb_cf7t( '[textual] is not a [text] tag', null === $t || 'textual' === $t['basetype'] );
foreach ( array( '[if applicable]', '[see below]', '[and more]' ) as $prose ) {
	efb_cf7t( $prose . ' (two plain words) stays text', null === $p->read_tag( $prose, 0 ) );
}
$t = $p->read_tag( '[dyn your-phone]', 0 );
efb_cf7t( '[dyn your-phone] (field-like name) is read as an add-on tag', null !== $t && 'dyn' === $t['basetype'] );
$t = $p->read_tag( '[intl_tel phone]', 0 );
efb_cf7t( '[intl_tel phone] (add-on-style type) is read as a tag', null !== $t && 'intl_tel' === $t['basetype'] );
$p2 = new Parser( array( 'dynamictext' ) );
efb_cf7t( 'an add-on type is a tag when CF7 registered it', null !== $p2->read_tag( '[dynamictext dyn "x"]', 0 ) );

// Add-on tags with Contact Form 7 deactivated (no registered types): read by their shape.
$t = $p->read_tag( '[dynamictext dyn "x"]', 0 );
efb_cf7t( 'CF7 inactive: [dynamictext dyn "x"] is still a tag', null !== $t && 'dynamictext' === $t['basetype'] && 'dyn' === $t['name'] && array( 'x' ) === $t['values'] );
$t = $p->read_tag( '[intl_tel* ph]', 0 );
efb_cf7t( 'CF7 inactive: [intl_tel* ph] is a required tag', null !== $t && 'intl_tel*' === $t['type'] && true === $t['required'] && 'ph' === $t['name'] );
$t = $p->read_tag( '[multistep ms "1-2"]', 0 );
efb_cf7t( 'CF7 inactive: [multistep ms "1-2"] is a tag', null !== $t && 'multistep' === $t['basetype'] && 'ms' === $t['name'] );
$t = $p->read_tag( '[group g1]<label>A [text a]</label>[/group]', 0 );
efb_cf7t( 'CF7 inactive: [group g1] is a tag', null !== $t && 'group' === $t['basetype'] && '[group g1]' === $t['raw'] );
$t = $p->read_tag( '[spoiler]Hidden text[/spoiler]', 0 );
efb_cf7t( 'CF7 inactive: a bare [type]…[/type] pair is a tag', null !== $t && 'spoiler' === $t['basetype'] && 'Hidden text' === $t['content'] );
$nodes = $p->tokenize( '[group]<label>A [text a]</label>[/group]' );
efb_cf7t( 'CF7 inactive: a bare [group] closed later by [/group] is a tag', 'tag' === $nodes[0]['node'] && 'group' === $nodes[0]['tag']['basetype'] );
$not_tags = array( '[optional]', '[Optional field]', '[e.g. Jane]', '[your-name]', '[_site_title]', '[Note: see below]', '[and/or]', '[see 1 below]', "[dynamictext dyn\n\"x\"]", '[sic' );
$wrong    = array();
foreach ( $not_tags as $text ) {
	if ( null !== $p->read_tag( $text, 0 ) ) {
		$wrong[] = $text;
	}
}
efb_cf7t( 'text in square brackets that is not tag-shaped stays text', empty( $wrong ), $wrong );
$addon_tpl = "<label> Name [text* your-name] </label>\n<label> Dyn [dynamictext dyn \"x\"] </label>\n<label> Phone [intl_tel* ph] </label>\n[group g1]<label> Inside [text in-group] </label>[/group]\n[multistep ms \"1-2\"]\n<p>Write [optional] notes below.</p>\n[submit \"Send\"]";
$p_active  = new Parser( array( 'dynamictext', 'intl_tel', 'group', 'multistep' ) );
efb_cf7t( 'CF7 inactive tokenizes add-on tags exactly as CF7 active does', $p_active->tokenize( $addon_tpl ) === $p->tokenize( $addon_tpl ) );
$t = $p->read_tag( '[text 1abc]', 0 );
efb_cf7t( 'invalid name marks the tag invalid', false === $t['valid'] );
$t = $p->read_tag( "[text\xC2\xA0nbsp-name\xC2\xA0placeholder \"x\"]", 0 );
efb_cf7t( 'non-breaking spaces separate options', 'nbsp-name' === $t['name'] && Parser::has_option( $t, 'placeholder' ) );
efb_cf7t( 'an unclosed tag is text', null === $p->read_tag( '[text a "b"', 0 ) );

$t = $p->read_tag( '[text a 40/100 minlength:3]', 0 );
efb_cf7t( 'size/maxlength shorthand 40/100 and minlength:', 100 === Parser::maxlength( $t ) && 3 === Parser::minlength( $t ) );
$t = $p->read_tag( '[textarea a 40x10/2000]', 0 );
efb_cf7t( 'cols x rows / maxlength shorthand', 2000 === Parser::maxlength( $t ) );
$t = $p->read_tag( '[text a maxlength:30]', 0 );
efb_cf7t( 'maxlength: option', 30 === Parser::maxlength( $t ) && false === Parser::minlength( $t ) );
efb_cf7t( 'limit:2mb', 2 * 1048576 === Parser::limit_bytes( $p->read_tag( '[file f limit:2mb]', 0 ) ) );
efb_cf7t( 'limit:500kb', 500 * 1024 === Parser::limit_bytes( $p->read_tag( '[file f limit:500kb]', 0 ) ) );
efb_cf7t( 'limit:1024 (bytes)', 1024 === Parser::limit_bytes( $p->read_tag( '[file f limit:1024]', 0 ) ) );
efb_cf7t( 'no limit: is false', false === Parser::limit_bytes( $p->read_tag( '[file f]', 0 ) ) );
efb_cf7t( 'filetypes:pdf|.DOC|jpg', array( 'pdf', 'doc', 'jpg' ) === Parser::filetypes( $p->read_tag( '[file f filetypes:pdf|.DOC|jpg]', 0 ) ) );
$d = Parser::defaults( $p->read_tag( '[checkbox c "a" "b" "c" default:1_3 default:user_email]', 0 ) );
efb_cf7t( 'default:1_3 → indexes 0 and 2, default:user_email is dynamic', array( 0, 2 ) === $d['indexes'] && array( 'user_email' ) === $d['dynamic'] );
efb_cf7t( 'watermark counts as placeholder option', Parser::has_option( $p->read_tag( '[text a watermark "x"]', 0 ), 'watermark' ) );

$lex = $p->lex_html( '<label for="nm" class=\'x\'>Name <!-- c --></label><script>var a="<label>";</script><br/>Tail' );
$lex_text = implode( '', array_column( array_filter( $lex, function ( $i ) { return 'text' === $i['t']; } ), 'v' ) );
efb_cf7t( 'HTML lexer: attributes, comments and script content dropped', 'open' === $lex[0]['t'] && 'nm' === $lex[0]['attrs']['for'] && 'Name Tail' === $lex_text, $lex );

/* =========================================================================
 * 2. Field mapping, tag by tag
 * ====================================================================== */
echo "\n--- fields ---\n";

$m = efb_cf7t_map( $default );
$f = efb_cf7t_fields( $m );
efb_cf7t( 'default form: text, email, text, textarea', array( 'text', 'email', 'text', 'textarea' ) === array_column( $f, 'type' ) );
efb_cf7t( 'labels from the surrounding <label>', array( 'Your name', 'Your email', 'Subject', 'Your message (optional)' ) === array_column( $f, 'name' ) );
efb_cf7t( 'required flags', array( 1, 1, 1, 0 ) === array_column( $f, 'required' ) );
efb_cf7t( 'button label from [submit "…"]', 'Submit' === $m['structure'][0]['button_single_text'] );

$m = efb_cf7t_map( '<p>Your Name (required)<br />[text* your-name placeholder "Jane" minlength:2 maxlength:50 class:big class:wide id:nm]</p>' );
$f = efb_cf7t_fields( $m )[0];
efb_cf7t( 'text: label before the tag, "(required)" removed', 'Your Name' === $f['name'] );
efb_cf7t( 'text: placeholder, min/max length', 'Jane' === $f['placeholder'] && '' === $f['value'] && '2' === $f['milen'] && '50' === $f['mlen'] );
efb_cf7t( 'text: class: options → classes', 'big,wide' === $f['classes'] );
efb_cf7t( 'text: label shown (it came from the markup)', ! isset( $f['hflabel'] ) );

$f = efb_cf7t_fields( efb_cf7t_map( '[text name "John"]' ) )[0];
efb_cf7t( 'text: first value is the default value', 'John' === $f['value'] && '' === $f['placeholder'] );
$f = efb_cf7t_fields( efb_cf7t_map( '[text* your-name placeholder "Full name"]' ) )[0];
efb_cf7t( 'placeholder-only field: label from placeholder, label row hidden', 'Full name' === $f['name'] && 1 === $f['hflabel'] );
$f = efb_cf7t_fields( efb_cf7t_map( '[text menu-123]' ) )[0];
efb_cf7t( 'no label at all: the name, readable', 'Menu' === $f['name'] );

foreach ( array( 'email', 'url', 'tel' ) as $type ) {
	$f = efb_cf7t_fields( efb_cf7t_map( "<label>X [{$type}* x]</label>" ) )[0];
	efb_cf7t( "{$type} → {$type}", $type === $f['type'] && 1 === $f['required'] );
}

$m = efb_cf7t_map( '<label>Age [number* age min:18 max:99 "30"]</label>' );
$f = efb_cf7t_fields( $m )[0];
efb_cf7t( 'number: min/max/default', 'number' === $f['type'] && '18' === $f['milen'] && '99' === $f['mlen'] && '30' === $f['value'] && empty( $m['notes'] ) );
$m = efb_cf7t_map( '<label>Score [range score min:-5 max:10.5 step:0.5]</label>' );
$f = efb_cf7t_fields( $m )[0];
efb_cf7t( 'range: negative/decimal limits and step are reported, not stored', 'range' === $f['type'] && ! isset( $f['milen'] ) && ! isset( $f['mlen'] ) && efb_cf7t_notes_match( $m, 'min:-5 max:10.5 step:0.5' ) );
$f = efb_cf7t_fields( efb_cf7t_map( '[range r min:1 max:10 "5"]' ) )[0];
efb_cf7t( 'range: min/max/default', '1' === $f['milen'] && '10' === $f['mlen'] && '5' === $f['value'] );

$m = efb_cf7t_map( '<label>Start [date start min:today max:2030-12-31 "2026-01-01"]</label><label>End [date end min:today+7days]</label>' );
$f = efb_cf7t_fields( $m );
efb_cf7t( 'date: min:today → 1 (EFB "today"), absolute max, default', '1' === $f[0]['milen'] && '2030-12-31' === $f[0]['mlen'] && '2026-01-01' === $f[0]['value'] );
efb_cf7t( 'date: relative limit reported', ! isset( $f[1]['milen'] ) && efb_cf7t_notes_match( $m, 'min:today+7days' ) );

$f = efb_cf7t_fields( efb_cf7t_map( "<label>Note [textarea note]Line one\nLine two[/textarea]</label>" ) )[0];
efb_cf7t( 'textarea: enclosed content is the default', "Line one\nLine two" === $f['value'] );
$m = efb_cf7t_map( '<label>Msg [textarea* msg minlength:10 maxlength:500 placeholder "Tell us"]Content[/textarea*]</label>' );
$f = efb_cf7t_fields( $m )[0];
efb_cf7t( 'textarea: with placeholder the content is the placeholder (as CF7)', 'Content' === $f['placeholder'] && '' === $f['value'] && '10' === $f['milen'] );
efb_cf7t( 'textarea: maxlength carried over (F03: a textarea has a maximum)', isset( $f['mlen'] ) && '500' === $f['mlen'] && ! efb_cf7t_notes_match( $m, 'maxlength:500' ) );

$m = efb_cf7t_map( '<label>Country [select* country include_blank "France" "Germany|DE" "Iran" default:3]</label>' );
$f = efb_cf7t_fields( $m )[0];
$o = efb_cf7t_options( $m, $f['id_'] );
efb_cf7t( 'select: options, include_blank adds no choice', 'select' === $f['type'] && array( 'France', 'Germany', 'Iran' ) === array_column( $o, 'value' ) );
efb_cf7t( 'select: default:3 selects the third option', $o[2]['id_'] === $f['value'] );
efb_cf7t( 'select: option rows have the builder shape', 'option' === $o[0]['type'] && $o[0]['id_'] === $o[0]['id_op'] && $o[0]['id_'] . '-id' === $o[0]['dataId'] && $f['amount'] === $o[0]['amount'] );
efb_cf7t( 'select: piped values reported', efb_cf7t_notes_match( $m, 'after “|”' ) );
$m = efb_cf7t_map( '[select size first_as_label "Pick a size" "S" "M" default:3]' );
$f = efb_cf7t_fields( $m )[0];
$o = efb_cf7t_options( $m, $f['id_'] );
efb_cf7t( 'select first_as_label: first value becomes the label, not a choice', 'Pick a size' === $f['name'] && array( 'S', 'M' ) === array_column( $o, 'value' ) );
efb_cf7t( 'select first_as_label: default index counts the label row', $o[1]['id_'] === $f['value'] );
$m = efb_cf7t_map( '<label>Toppings [select toppings multiple "Cheese" "Ham" "Olives" default:1_3]</label>' );
$f = efb_cf7t_fields( $m )[0];
$o = efb_cf7t_options( $m, $f['id_'] );
efb_cf7t( 'select multiple → multiselect, no selection limit', 'multiselect' === $f['type'] && '3' === $f['maxSelect'] && '0' === $f['minSelect'] );
efb_cf7t( 'multiselect defaults are an array of option ids', array( $o[0]['id_'], $o[2]['id_'] ) === $f['value'] );
$m = efb_cf7t_map( '<label>C [select c data:countries]</label>' );
efb_cf7t( 'data: list not available → reported', efb_cf7t_notes_match( $m, '(countries)' ) );
$m = efb_cf7t_map( '<label>C [select c data:countries]</label>', array(), array( 'data_source' => function ( $names ) { return array( 'Iran', 'Japan' ); } ) );
$f = efb_cf7t_fields( $m )[0];
efb_cf7t( 'data: list from Contact Form 7 (Listo) becomes choices', array( 'Iran', 'Japan' ) === array_column( efb_cf7t_options( $m, $f['id_'] ), 'value' ) );

$m = efb_cf7t_map( '<fieldset><legend>Contact me by</legend>[checkbox* contact use_label_element label_first "Email" "Phone" "Post" default:2]</fieldset>' );
$f = efb_cf7t_fields( $m )[0];
efb_cf7t( 'checkbox: legend labels the group, defaults array, required', 'Contact me by' === $f['name'] && 'checkbox' === $f['type'] && 1 === $f['required'] && 1 === count( $f['value'] ) );
efb_cf7t( 'checkbox: use_label_element / label_first need no note', empty( array_filter( $m['notes'], function ( $n ) { return false !== stripos( $n, 'Contact me by' ); } ) ) );
$m = efb_cf7t_map( '<p>Pick one<br/>[checkbox one exclusive "A" "B"]</p>' );
$f = efb_cf7t_fields( $m )[0];
efb_cf7t( 'checkbox exclusive → radio, reported', 'radio' === $f['type'] && 0 === $f['required'] && efb_cf7t_notes_match( $m, 'only one choice' ) );
$m = efb_cf7t_map( '<p>Heard<br/>[radio heard default:2 "Search" "Friend"]</p>' );
$f = efb_cf7t_fields( $m )[0];
$o = efb_cf7t_options( $m, $f['id_'] );
efb_cf7t( 'radio: always required (as CF7), default selects one option', 'radio' === $f['type'] && 1 === $f['required'] && $o[1]['id_'] === $f['value'] );
$m = efb_cf7t_map( '<p>Interests<br/>[checkbox interests free_text "Sports" "Other"]</p>' );
$f = efb_cf7t_fields( $m );
efb_cf7t( 'free_text: the last choice stays, its text box becomes a text field', 'checkbox' === $f[0]['type'] && 'text' === $f[1]['type'] && 'Other' === $f[1]['name'] && efb_cf7t_notes_match( $m, 'separate text field' ) );
$f = efb_cf7t_fields( efb_cf7t_map( '[checkbox* agree "I agree to be contacted"]' ) )[0];
efb_cf7t( 'a single checkbox without label: named by its choice, label row hidden', 'I agree to be contacted' === $f['name'] && 1 === $f['hflabel'] );

$m = efb_cf7t_map( '[acceptance terms] I agree to the <a href="https://example.com/privacy">privacy policy</a>. [/acceptance]' );
$f = efb_cf7t_fields( $m )[0];
$o = efb_cf7t_options( $m, $f['id_'] );
efb_cf7t( 'acceptance → terms field (trmCheckbox), required', 'trmCheckbox' === $f['type'] && 1 === $f['required'] && false === $f['pro'] );
efb_cf7t( 'acceptance: links kept as the builder\'s [text](url)', 'I agree to the [privacy policy](https://example.com/privacy).' === $o[0]['value'], $o[0]['value'] );
efb_cf7t( 'acceptance without label: named by its text, label row hidden', 'I agree to the privacy policy.' === $f['name'] && 1 === $f['hflabel'] );
$m = efb_cf7t_map( '<label>Marketing [acceptance news optional default:on] Send me news [/acceptance]</label>' );
$f = efb_cf7t_fields( $m )[0];
$o = efb_cf7t_options( $m, $f['id_'] );
efb_cf7t( 'acceptance optional: not required, outer label kept, default:on ticks it', 0 === $f['required'] && 'Marketing' === $f['name'] && 'Send me news' === $o[0]['value'] && array( $o[0]['id_'] ) === $f['value'] && ! isset( $f['hflabel'] ) );
$m = efb_cf7t_map( '[acceptance noemail invert]Do not email me[/acceptance]' );
$f = efb_cf7t_fields( $m )[0];
efb_cf7t( 'acceptance invert → optional checkbox, reported', 'checkbox' === $f['type'] && 0 === $f['required'] && efb_cf7t_notes_match( $m, 'stay unticked' ) );
$f = efb_cf7t_fields( efb_cf7t_map( '[acceptance a] I accept' ) )[0];
efb_cf7t( 'acceptance written without closing tag: text after it is its text', 'I accept' === $f['name'] );

$m = efb_cf7t_map( '<p>Quiz: [quiz q1 "1+1=?|2" "2+2=?|4"]</p>' );
efb_cf7t( 'quiz: no field, question reported', empty( efb_cf7t_fields( $m ) ) && efb_cf7t_notes_match( $m, '1+1=? / 2+2=?' ) );

$m = efb_cf7t_map( '<label>CV [file* cv limit:2mb filetypes:pdf|doc|docx]</label><label>Photo [file photo limit:300kb filetypes:jpg|png]</label><label>Mix [file mix filetypes:jpg|pdf]</label><label>Any [file any]</label>' );
$f = efb_cf7t_fields( $m );
efb_cf7t( 'file: limit → max_fsize in MB (2mb → 2)', 'file' === $f[0]['type'] && '2' === $f[0]['max_fsize'] && 1 === $f[0]['required'] );
efb_cf7t( 'file: pdf/doc/docx → Documents group', 'document' === $f[0]['file'] && 'document' === $f[0]['value'] );
efb_cf7t( 'file: 300kb rounds up to 1 MB, jpg/png → Image group', '1' === $f[1]['max_fsize'] && 'image' === $f[1]['file'] );
efb_cf7t( 'file: mixed types → All formats, reported', 'allformat' === $f[2]['file'] && efb_cf7t_notes_match( $m, 'accepted only jpg, pdf' ) );
efb_cf7t( 'file: no options → CF7\'s 1 MB, All formats, no note', '1' === $f[3]['max_fsize'] && 'allformat' === $f[3]['file'] && ! efb_cf7t_notes_match( $m, '“Any”' ) );

$m = efb_cf7t_map( '<label>Name [text name]</label>[hidden ref "abc"]' );
efb_cf7t( 'hidden: no field, reported', 1 === count( efb_cf7t_fields( $m ) ) && efb_cf7t_notes_match( $m, 'hidden field “ref”' ) );

$m = efb_cf7t_map( '<p>Type the code: [captchac c1] [captchar c1]</p>[recaptcha]' );
efb_cf7t( 'captchac/captchar/recaptcha: no field, one note, label text consumed', empty( efb_cf7t_fields( $m ) ) && 1 === count( array_filter( $m['notes'], function ( $n ) { return false !== stripos( $n, 'CAPTCHA was not carried over' ); } ) ) );
efb_cf7t( 'captcha: reCAPTCHA stays off without EFB keys', '' === (string) $m['structure'][0]['captcha'] || false === $m['structure'][0]['captcha'] );
$m = efb_cf7t_map( '[captchac c1][captchar c1]', array(), array( 'captcha_ready' => true ) );
efb_cf7t( 'captcha with EFB reCAPTCHA keys: reCAPTCHA turned on, said in settings', true === $m['structure'][0]['captcha'] && ! efb_cf7t_notes_match( $m, 'CAPTCHA was not' ) && ! empty( $m['settings'] ) );

$m = efb_cf7t_map( '<label>Msg [textarea msg]</label>[count msg][response]' );
$f = efb_cf7t_fields( $m )[0];
efb_cf7t( 'count: the field\'s own counter (F03), no note; response: ignored', 1 === count( efb_cf7t_fields( $m ) ) && 'chars' === $f['counter'] && 'used' === $f['counter_style'] && 0 === count( $m['notes'] ) );
$m = efb_cf7t_map( '<label>Msg [textarea msg maxlength:200]</label>[count msg]' );
$f = efb_cf7t_fields( $m )[0];
efb_cf7t( 'count with a maximum: what is left', 'chars' === $f['counter'] && 'remaining' === $f['counter_style'] && '200' === $f['mlen'] );
$m = efb_cf7t_map( '[count nowhere]' );
efb_cf7t( 'count of an unknown field: still reported', efb_cf7t_notes_match( $m, 'character counter of “nowhere”' ) );
$m = efb_cf7t_map( '[stripe "Pay"][dynamictext d "x"]', array(), array( 'extra_types' => array( 'dynamictext' ) ) );
efb_cf7t( 'stripe and add-on tags: reported by type', efb_cf7t_notes_match( $m, '[stripe]' ) && efb_cf7t_notes_match( $m, '[dynamictext]' ) );
$m_off = efb_cf7t_map( $addon_tpl );
$m_on  = efb_cf7t_map( $addon_tpl, array(), array( 'extra_types' => array( 'dynamictext', 'intl_tel', 'group', 'multistep' ) ) );
$f_off = efb_cf7t_fields( $m_off );
efb_cf7t( 'CF7 inactive: add-on tags reported with the "another plugin" note', efb_cf7t_notes_match( $m_off, '“[dynamictext]” tag comes from another plugin' ) && efb_cf7t_notes_match( $m_off, '“[intl_tel]”' ) && efb_cf7t_notes_match( $m_off, '“[group]”' ) && efb_cf7t_notes_match( $m_off, '“[multistep]”' ), $m_off['notes'] );
efb_cf7t( 'CF7 inactive: their labels are consumed, not left as stray headings', array( 'text', 'text', 'heading' ) === array_column( $f_off, 'type' ) && array( 'Name', 'Inside' ) === array( $f_off[0]['name'], $f_off[1]['name'] ) && 'Write notes below.' === $f_off[2]['value'], $f_off );
efb_cf7t( 'CF7 inactive maps the same fields and notes as CF7 active', array_column( $f_off, 'type' ) === array_column( efb_cf7t_fields( $m_on ), 'type' ) && $m_off['fields'] === $m_on['fields'] && $m_off['notes'] === $m_on['notes'] );
$m = efb_cf7t_map( '<label>Company [optional] [text company]</label><label>Your [your-name] e-mail [email em]</label>' );
$f = efb_cf7t_fields( $m );
efb_cf7t( 'bracketed words and mail-tags in a label stay label text', array( 'Company', 'Your e-mail' ) === array_column( $f, 'name' ) && empty( $m['notes'] ), $m );
$m = efb_cf7t_map( '[text 1bad]' );
efb_cf7t( 'tag without a valid name: skipped and reported', empty( efb_cf7t_fields( $m ) ) && efb_cf7t_notes_match( $m, 'without a valid name' ) );
$m = efb_cf7t_map( '[text city default:user_city][text ro readonly "x"]' );
efb_cf7t( 'dynamic default and readonly reported', efb_cf7t_notes_match( $m, 'filled itself in' ) && efb_cf7t_notes_match( $m, 'read-only' ) );

/* =========================================================================
 * 3. Labels and free-standing text
 * ====================================================================== */
echo "\n--- labels and text ---\n";

$f = efb_cf7t_fields( efb_cf7t_map( '<label for="em">E-mail *</label> [email* your-email id:em]' ) )[0];
efb_cf7t( '<label for> pointing at id:, trailing "*" removed', 'E-mail' === $f['name'] );
$f = efb_cf7t_fields( efb_cf7t_map( "Phone:\n[tel your-tel]" ) )[0];
efb_cf7t( 'bare text line before the tag, ":" removed', 'Phone' === $f['name'] );
$f = efb_cf7t_fields( efb_cf7t_map( '<table><tr><td>Budget</td><td>[number budget]</td></tr></table>' ) )[0];
efb_cf7t( 'table layout: the cell before', 'Budget' === $f['name'] );
$f = efb_cf7t_fields( efb_cf7t_map( '<div class="col"><label>First</label>[text first]</div><div class="col"><label>Last</label>[text last]</div>' ) );
efb_cf7t( 'standalone <label> before the tag', array( 'First', 'Last' ) === array_column( $f, 'name' ) );
$f = efb_cf7t_fields( efb_cf7t_map( '<label>Name [text first] [text last]</label>' ) );
efb_cf7t( 'two tags in one label: the first takes the text', 'Name' === $f[0]['name'] && 'Last' === $f[1]['name'] );
$m = efb_cf7t_map( '<h3>Personal details</h3>[text* your-name placeholder "Full name"]' );
$f = efb_cf7t_fields( $m );
efb_cf7t( 'a heading is never a label; it becomes a heading field', 'heading' === $f[0]['type'] && 'Personal details' === $f[0]['value'] && 'display-7' === $f[0]['el_text_size'] && 'Full name' === $f[1]['name'] );
$f = efb_cf7t_fields( efb_cf7t_map( '<h2>Get a quote</h2><p>Tell us about your project.</p>[text* n placeholder "Full name"]' ) );
efb_cf7t( 'a sentence above a field with a placeholder stays copy', array( 'heading', 'heading', 'text' ) === array_column( $f, 'type' ) && 'display-6' === $f[0]['el_text_size'] && 'display-8' === $f[1]['el_text_size'] && 'Full name' === $f[2]['name'] );
$f = efb_cf7t_fields( efb_cf7t_map( '<label>Your [your-name] name [text n]</label>' ) )[0];
efb_cf7t( 'mail-tags never reach EFB text', 'Your name' === $f['name'] );
$m = efb_cf7t_map( '<label>Name [text n]</label><p>See our <a href="/faq">FAQ</a>.</p><img src="x.png"><p>*</p>' );
$f = efb_cf7t_fields( $m );
efb_cf7t( 'text with a link: kept as text, reported; image reported; lone "*" dropped', 2 === count( $f ) && 'See our FAQ.' === $f[1]['value'] && efb_cf7t_notes_match( $m, 'is now plain text' ) && efb_cf7t_notes_match( $m, 'image' ), $f );
$f = efb_cf7t_fields( efb_cf7t_map( '<p>Please read our <a href="/terms">terms</a> first</p>[textarea msg]' ) )[0];
efb_cf7t( 'label text split by a link joins as written', 'Please read our terms first' === $f['name'], $f['name'] );
$m = efb_cf7t_map( '<label>Msg [textarea msg]</label>' );
efb_cf7t( 'a form without Mail (1) settings gets no email notes', empty( $m['notes'] ) && '' === $m['structure'][0]['email'] );
$m = efb_cf7t_map( "<label> نام شما\n [text* your-name] </label>\n[submit \"ارسال\"]", array(), array( 'rtl' => true ) );
$f = efb_cf7t_fields( $m )[0];
efb_cf7t( 'Persian label and button kept; RTL aligns labels right', 'نام شما' === $f['name'] && 'ارسال' === $m['structure'][0]['button_single_text'] && 'txt-right' === $f['label_align'] );

/* =========================================================================
 * 4. Mail, messages, additional settings
 * ====================================================================== */
echo "\n--- form settings ---\n";

$mail = array(
	'active'             => true,
	'recipient'          => '[_site_admin_email], Sales <sales@example.com>',
	'subject'            => '[_site_title] "[your-subject]"',
	'sender'             => '[_site_title] <wordpress@example.com>',
	'body'               => 'x',
	'additional_headers' => "Reply-To: [your-email]\nCc: boss@example.com",
	'attachments'        => '[cv]',
);
$m = efb_cf7t_map( $default, array( 'mail' => $mail, 'messages' => array( 'mail_sent_ok' => 'Thanks [your-name]!' ) ) );
$h = $m['structure'][0];
efb_cf7t( 'recipient: [_site_admin_email] resolved, "Name <email>" read', 'admin@example.com,sales@example.com' === $h['email'] && true === $h['sendEmail'] );
efb_cf7t( 'subject made only of answers: EFB standard subject, reported', ! isset( $h['email_sub'] ) && efb_cf7t_notes_match( $m, 'standard subject' ) );
efb_cf7t( 'Cc reported; Reply-To needs nothing; attachments reported', efb_cf7t_notes_match( $m, 'boss@example.com' ) && efb_cf7t_notes_match( $m, 'not attached' ) );
efb_cf7t( 'default sender address: no note', ! efb_cf7t_notes_match( $m, 'sender address' ) );
efb_cf7t( 'success message carried, mail-tags removed', 'Thanks!' === $h['thank_you_message']['thankYou'], $h['thank_you_message']['thankYou'] );
efb_cf7t( 'email text not copied: said once', 1 === count( array_filter( $m['notes'], function ( $n ) { return false !== stripos( $n, 'Email text' ); } ) ) );

$m = efb_cf7t_map( $default, array( 'mail' => array( 'recipient' => '[your-email]', 'subject' => 'New enquiry from [_site_title]: [your-subject]', 'sender' => 'Shop <shop@example.com>' ) ) );
$h = $m['structure'][0];
efb_cf7t( 'subject: site title → [website_name], answers removed and reported', 'New enquiry from [website_name]' === $h['email_sub'] && efb_cf7t_notes_match( $m, 'Parts of the email subject' ), isset( $h['email_sub'] ) ? $h['email_sub'] : '' );
efb_cf7t( 'recipient from an answer: reported, falls back to the admin address', 'admin@example.com' === $h['email'] && efb_cf7t_notes_match( $m, '[your-email]' ) );
efb_cf7t( 'custom sender reported', efb_cf7t_notes_match( $m, 'shop@example.com' ) );

$m = efb_cf7t_map( $default, array( 'mail' => array( 'recipient' => 'owner@example.com', 'subject' => 'Website contact' ), 'mail_2' => array( 'active' => true, 'recipient' => '[your-email]', 'body' => 'Dear [your-name]' ) ) );
$h     = $m['structure'][0];
$email = efb_cf7t_fields( $m )[1];
efb_cf7t( 'Mail (2): confirmation on the email field it names (noti, email_to)', 1 === $email['noti'] && $email['id_'] === $h['email_to'] && true === $h['sendEmail'] );
efb_cf7t( 'Mail (2): the owner\'s subject is not copied (EFB has one subject for both), reported', ! isset( $h['email_sub'] ) && efb_cf7t_notes_match( $m, 'one subject' ) );
$m = efb_cf7t_map( '<label>Name [text n]</label>', array( 'mail_2' => array( 'active' => true, 'recipient' => '[your-email]' ) ) );
efb_cf7t( 'Mail (2) without an email field: reported', efb_cf7t_notes_match( $m, 'no email field' ) );
$m = efb_cf7t_map( '<label>A [email a]</label><label>B [email b]</label>', array( 'mail_2' => array( 'active' => '1', 'recipient' => '[b]' ) ) );
efb_cf7t( 'Mail (2) names the second email field', efb_cf7t_fields( $m )[1]['id_'] === $m['structure'][0]['email_to'] && ! isset( efb_cf7t_fields( $m )[0]['noti'] ) );
$m = efb_cf7t_map( $default, array( 'mail_2' => array( 'active' => false, 'recipient' => '[your-email]' ) ) );
efb_cf7t( 'inactive Mail (2): no confirmation', false === $m['structure'][0]['email_to'] );
$m = efb_cf7t_map( $default, array( 'mail' => array( 'recipient' => 'a@example.com' ) ), array( 'sending_enabled' => false ) );
efb_cf7t( 'EFB sending switched off: said', efb_cf7t_notes_match( $m, 'This site can send emails' ) );

$m = efb_cf7t_map( '<label>Name [text n]</label>', array( 'mail' => array( 'recipient' => 'a@example.com', 'subject' => '[your-subject]' ), 'additional_settings' => "subscribers_only: true\nskip_mail: on\ndemo_mode: on\non_sent_ok: \"x();\"" ) );
$h = $m['structure'][0];
efb_cf7t( 'subscribers_only → logged-in visitors only (stateForm)', true === $h['stateForm'] );
efb_cf7t( 'skip_mail → no owner email, no mail notes', '' === $h['email'] && false === $h['sendEmail'] && efb_cf7t_notes_match( $m, 'skip_mail' ) && ! efb_cf7t_notes_match( $m, 'subject' ) );
efb_cf7t( 'demo_mode and JavaScript hooks reported', efb_cf7t_notes_match( $m, 'Demo mode' ) && efb_cf7t_notes_match( $m, 'JavaScript' ) );

/* =========================================================================
 * 5. The structure is the builder's
 * ====================================================================== */
echo "\n--- structure ---\n";

$big = "<h3>Head</h3><label>A [text* a]</label><label>B [email b]</label><label>C [url c]</label><label>D [tel d]</label><label>E [number e]</label><label>F [range f]</label><label>G [date g]</label><label>H [textarea h]</label><label>I [select i \"1\" \"2\"]</label><label>J [select j multiple \"1\" \"2\"]</label><label>K [checkbox k \"1\" \"2\"]</label><label>L [radio l \"1\" \"2\"]</label>[acceptance m]ok[/acceptance]<label>N [file n]</label>[submit]";
$m   = efb_cf7t_map( $big );
$s   = $m['structure'];
$free = array( 'text', 'name', 'password', 'email', 'number', 'textarea', 'steps', 'checkbox', 'radio', 'select', 'multiselect', 'tel', 'range', 'date', 'file', 'url', 'trmCheckbox', 'conturyList', 'rating', 'heading' );
$keys0 = array( 'type', 'steps', 'formName', 'email', 'trackingCode', 'EfbVersion', 'button_single_text', 'button_color', 'icon', 'button_Next_text', 'button_Previous_text', 'button_state', 'label_text_color', 'el_text_color', 'message_text_color', 'icon_color', 'el_height', 'email_to', 'show_icon', 'show_pro_bar', 'captcha', 'private', 'sendEmail', 'font', 'stateForm', 'dShowBg', 'thank_you', 'thank_you_message', 'email_temp' );
efb_cf7t( 'index 0 has every key a new builder form has', array() === array_diff( $keys0, array_keys( $s[0] ) ), array_diff( $keys0, array_keys( $s[0] ) ) );
efb_cf7t( 'index 0: a one-step "form"', 'form' === $s[0]['type'] && 1 === $s[0]['steps'] && 'msg' === $s[0]['thank_you'] );
efb_cf7t( 'index 1: step 1', 'step' === $s[1]['type'] && '1' === $s[1]['id_'] && 1 === $s[1]['step'] );
$ids = array();
$ok_ids = true;
$ok_free = true;
$ok_amount = true;
$prev = 0;
$parents = array();
foreach ( array_slice( $s, 2 ) as $row ) {
	$ok_ids = $ok_ids && preg_match( '/^[a-z][a-z0-9]{8}$/', $row['id_'] ) && ! isset( $ids[ $row['id_'] ] ) && $row['id_'] . '-id' === $row['dataId'];
	$ids[ $row['id_'] ] = true;
	if ( 'option' === $row['type'] ) {
		$parents[] = $row['parent'];
		continue;
	}
	$ok_free   = $ok_free && in_array( $row['type'], $free, true ) && false === $row['pro'];
	$ok_amount = $ok_amount && $row['amount'] > $prev && 1 === $row['step'];
	$prev      = $row['amount'];
}
efb_cf7t( 'ids: 9 lower-case characters, unique, dataId = id-id', $ok_ids );
efb_cf7t( 'only field types that are free on every plan; pro:false', $ok_free );
efb_cf7t( 'amounts increase in document order, all in step 1', $ok_amount );
efb_cf7t( 'every option points at a field of the form', array() === array_diff( $parents, array_keys( $ids ) ) );
efb_cf7t( 'all types of the big form mapped', array( 'heading', 'text', 'email', 'url', 'tel', 'number', 'range', 'date', 'textarea', 'select', 'multiselect', 'checkbox', 'radio', 'trmCheckbox', 'file' ) === array_column( efb_cf7t_fields( $m ), 'type' ) );
efb_cf7t( 'report lists every field with the builder\'s type name', count( $m['fields'] ) === count( efb_cf7t_fields( $m ) ) && isset( $m['fields'][0]['typeLabel'] ) );

if ( function_exists( 'get_efbFunction' ) ) {
	$efb   = get_efbFunction();
	$clean = $efb->sanitize_obj_msg_efb( json_decode( wp_json_encode( $s ), true ) );
	efb_cf7t( 'sanitize_obj_msg_efb() keeps every row and every field type', count( $clean ) === count( $s ) && array_column( $clean, 'type' ) === array_column( $s, 'type' ) );
} else {
	echo "[SKIP] sanitize_obj_msg_efb() needs the plugin loaded\n";
}

/* =========================================================================
 * 6. Pages: find and swap the embeds
 * ====================================================================== */
echo "\n--- pages ---\n";

$forms = array(
	63 => array( 'id' => 63, 'title' => 'Contact form 1', 'hash' => 'ab12cd34ef567890', 'old_id' => 0 ),
	65 => array( 'id' => 65, 'title' => 'Choices', 'hash' => 'ba18cfd99887766', 'old_id' => 2 ),
	66 => array( 'id' => 66, 'title' => 'File upload', 'hash' => 'ffee001122334455', 'old_id' => 0 ),
);
$content = "<p>Intro</p>\n"
	. "[contact-form-7 id=\"63\" title=\"Contact form 1\"]\n"
	. "[contact-form-7 id=\"ba18cfd\" title=\"Choices\"]\n"
	. "[contact-form-7 title=\"File upload\"]\n"
	. "[contact-form 2 \"Choices\"]\n"
	. "[[contact-form-7 id=\"63\"]]\n"
	. "[contact-form-7 id=\"999\"]\n"
	. "<!-- wp:contact-form-7/contact-form-selector {\"id\":66,\"hash\":\"ffee001\",\"title\":\"File upload\",\"htmlClass\":\"x\"} -->\n<div class=\"wp-block-contact-form-7-contact-form-selector\">[contact-form-7 id=\"ffee001\" title=\"File upload\"]</div>\n<!-- /wp:contact-form-7/contact-form-selector -->\n"
	. "<!-- wp:contact-form-7/contact-form-selector {\"id\":63} /-->\n"
	. "<p>Outro</p>";
$found = Pages::scan( $content, $forms );
sort( $found );
efb_cf7t( 'scan: numeric id, hash id, title only, pre-3.0 shortcode, blocks (paired, self-closing); escaped and unknown ignored', array( 63, 63, 65, 65, 66, 66 ) === $found, $found );
list( $new, $count ) = Pages::replace_in_content( $content, $forms, array( 63 => array( 'efb' => 15, 'name' => 'Contact' ), 66 => array( 'efb' => 16, 'name' => 'Files' ) ) );
efb_cf7t( 'replace: only the chosen forms, every embed of them', 4 === $count );
efb_cf7t( 'replace: shortcode → [EMS_Form_Builder id=N]', false !== strpos( $new, '[EMS_Form_Builder id=15]' ) && false !== strpos( $new, '[EMS_Form_Builder id=16]' ) );
efb_cf7t( 'replace: block → the Easy Form Builder block', false !== strpos( $new, '<!-- wp:easy-form-builder/form {"formId":"16","formName":"Files"} /-->' ) && false !== strpos( $new, '<!-- wp:easy-form-builder/form {"formId":"15","formName":"Contact"} /-->' ) );
efb_cf7t( 'replace: the shortcode saved inside the CF7 block goes with it', false === strpos( $new, 'ffee001' ) && false === strpos( $new, 'contact-form-7/contact-form-selector' ) );
efb_cf7t( 'replace: escaped shortcode, other forms and the page text untouched', false !== strpos( $new, '[[contact-form-7 id="63"]]' ) && false !== strpos( $new, '[contact-form-7 id="ba18cfd" title="Choices"]' ) && false !== strpos( $new, '[contact-form 2 "Choices"]' ) && false !== strpos( $new, '[contact-form-7 id="999"]' ) && 0 === strpos( $new, '<p>Intro</p>' ) && '<p>Outro</p>' === substr( $new, -12 ) );
list( , $none ) = Pages::replace_in_content( '<p>No forms</p>', $forms, array( 63 => array( 'efb' => 1, 'name' => 'x' ) ) );
efb_cf7t( 'replace: nothing to do → 0', 0 === $none );

// Undo needs the "before" revision to survive the save: WordPress trims to wp_revisions_to_keep().
$keep_cases = array(
	array( -1, 'page', true, 'unlimited revisions (-1): replace' ),
	array( 2, 'page', true, 'two revisions kept: replace' ),
	array( 25, 'page', true, '25 revisions kept: replace' ),
	array( 1, 'page', false, 'one revision kept (the save would delete the "before" copy): skip' ),
	array( 0, 'page', false, 'revisions off (0): skip' ),
	array( -1, 'efb_cf7t_norev', false, 'post type without revisions: skip' ),
);
foreach ( $keep_cases as $case ) {
	$keep_cb = function () use ( $case ) {
		return $case[0];
	};
	add_filter( 'wp_revisions_to_keep', $keep_cb );
	efb_cf7t( 'undo check: ' . $case[3], $case[2] === Pages::can_undo( (object) array( 'ID' => 0, 'post_type' => $case[1] ) ) );
	remove_filter( 'wp_revisions_to_keep', $keep_cb );
}
if ( function_exists( 'wp_insert_post' ) && function_exists( 'get_users' ) ) {
	$admin = get_users( array( 'role' => 'administrator', 'number' => 1 ) );
	if ( $admin ) {
		wp_set_current_user( $admin[0]->ID );
	}
	$original = '<p>Before</p>[contact-form-7 id="63"]';
	$page_id  = wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'draft', 'post_title' => 'CF7 importer undo test', 'post_content' => $original ) );
	if ( $page_id && ! is_wp_error( $page_id ) ) {
		$keep_one = function () {
			return 1;
		};
		add_filter( 'wp_revisions_to_keep', $keep_one );
		$done = Pages::replace_on_post( $page_id, $forms, array( 63 => array( 'efb' => 15, 'name' => 'Contact' ) ) );
		remove_filter( 'wp_revisions_to_keep', $keep_one );
		efb_cf7t( 'replace_on_post with one revision kept: page skipped with reason "revisions", content untouched', false === $done['ok'] && 'revisions' === $done['reason'] && 0 === $done['revision'] && $original === get_post( $page_id )->post_content, $done );

		$keep_two = function () {
			return 2;
		};
		add_filter( 'wp_revisions_to_keep', $keep_two );
		$done = Pages::replace_on_post( $page_id, $forms, array( 63 => array( 'efb' => 15, 'name' => 'Contact' ) ) );
		$rev  = $done['revision'] ? get_post( $done['revision'] ) : null;
		remove_filter( 'wp_revisions_to_keep', $keep_two );
		efb_cf7t( 'replace_on_post with two revisions kept: replaced, the undo revision still holds the original', true === $done['ok'] && $rev && $original === $rev->post_content && false !== strpos( get_post( $page_id )->post_content, '[EMS_Form_Builder id=15]' ), $done );
		wp_delete_post( $page_id, true );
	}
}

/* =========================================================================
 * 7. Storage and routing
 * ====================================================================== */
echo "\n--- storage and routing ---\n";

global $wpdb;
if ( ! is_object( $wpdb ) ) {
	echo "[SKIP] storage and routing need WordPress\n\n{$passed} passed, {$failed} failed\n";
	exit( $failed > 0 ? 1 : 0 );
}
$user = get_users( array( 'role' => 'administrator', 'number' => 1 ) );
if ( $user ) {
	wp_set_current_user( $user[0]->ID );
}
$m  = efb_cf7t_map( "<label>Name [text* n]</label><label>Quote \"q\" [text q]</label>[acceptance a]I agree <a href=\"https://e.x/?a=1&b=2\">terms</a>[/acceptance]" );
$id = Importer::insert_form( 'CF7 importer test – ٪ "quoted"', $m['structure'] );
efb_cf7t( 'insert_form() creates a row', $id > 0 );
if ( $id ) {
	$row    = $wpdb->get_row( $wpdb->prepare( "SELECT form_name, form_structer, form_type, form_email FROM {$wpdb->prefix}emsfb_form WHERE form_id = %d", $id ) );
	$stored = (string) $row->form_structer;
	efb_cf7t( 'stored like the builder: quotes escaped as \\"', false !== strpos( $stored, '\\"type\\":\\"form\\"' ) && false === strpos( str_replace( '\\"', '', $stored ), '"' ) );
	efb_cf7t( 'stored with unescaped Unicode', false === strpos( $stored, '\\u' ) || false !== strpos( $stored, 'Name' ) );
	$decoded = json_decode( str_replace( '\\"', '"', $stored ), true );
	efb_cf7t( 'stored JSON decodes back to the same rows', is_array( $decoded ) && count( $decoded ) === count( $m['structure'] ) && 'trmCheckbox' === $decoded[4]['type'] );
	efb_cf7t( 'form_type "form", no form_email (as the builder insert path)', 'form' === $row->form_type && '' === $row->form_email );
	if ( function_exists( 'emsfb_json_decode_efb' ) ) {
		$read = emsfb_json_decode_efb( $stored );
		efb_cf7t( 'EFB\'s own reader decodes it', is_array( $read ) || is_object( $read ) );
	}
	$wpdb->delete( $wpdb->prefix . 'emsfb_form', array( 'form_id' => $id ) );
}

$route = new ReflectionMethod( 'Emsfb', 'is_cf7_importer_request_efb' );
$route->setAccessible( true );
$saved_get = $_GET;
$saved_req = $_REQUEST;
$cases     = array(
	array( array( 'page' => 'Emsfb_create' ), false, true, 'Create screen loads the module' ),
	array( array( 'page' => 'Emsfb' ), false, true, 'Panel screen loads the module' ),
	array( array( 'page' => 'Emsfb_addon' ), false, false, 'Add-ons screen does not' ),
	array( array(), false, false, 'other admin screens do not' ),
	array( array( 'action' => 'efb_cf7_list' ), true, true, 'its admin-ajax actions load it' ),
	array( array( 'action' => 'update_form_Emsfb' ), true, false, 'other admin-ajax actions do not' ),
);
foreach ( $cases as $case ) {
	$_GET     = $case[0];
	$_REQUEST = $case[0];
	$filter   = $case[1] ? '__return_true' : '__return_false';
	add_filter( 'wp_doing_ajax', $filter );
	efb_cf7t( 'routing: ' . $case[3], $case[2] === $route->invoke( null ) );
	remove_filter( 'wp_doing_ajax', $filter );
}
$_GET     = $saved_get;
$_REQUEST = $saved_req;

echo "\n{$passed} passed, {$failed} failed\n";
exit( $failed > 0 ? 1 : 0 );
