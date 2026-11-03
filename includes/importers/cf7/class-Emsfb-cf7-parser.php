<?php
/**
 * Contact Form 7 importer - the form-template parser.
 *
 * Reads the `_form` template of a Contact Form 7 form (the text with the
 * [text* your-name] tags in it) without needing Contact Form 7 itself: the
 * importer also works when that plugin is deactivated and only its forms are
 * left in the database.
 *
 * Two layers, both single-pass scanners rather than one large regular
 * expression:
 *
 *   1. tokenize() walks the template character by character and splits it
 *      into form-tags and the markup between them. A form-tag is read the way
 *      Contact Form 7 reads it - type, optional "*", then bare options and
 *      quoted values, an optional "/" and an optional enclosed content up to
 *      [/type] - but quotes are honoured, so a "]" inside a quoted value does
 *      not end the tag. [[tag]] is the documented escape and stays text.
 *
 *   2. markup_stream() splits the markup between the tags into HTML start
 *      tags, end tags and text, so the mapper can tell which text is a field's
 *      label (inside its <label>, a <label for> pointing at it, or the line
 *      just before it) and which text is free-standing copy.
 *
 * @package Easy_Form_Builder
 */

namespace Emsfb\Importers\CF7;

defined( 'ABSPATH' ) || exit;

class Parser {

	/**
	 * Base types Contact Form 7 registers itself (and the two add-on tags
	 * every CF7 site may carry). Anything else is only recognised when the
	 * running Contact Form 7 says it is registered.
	 */
	const CORE_TYPES = array(
		'text', 'email', 'url', 'tel', 'number', 'range', 'date', 'textarea',
		'select', 'checkbox', 'radio', 'acceptance', 'quiz', 'file', 'submit',
		'hidden', 'captchac', 'captchar', 'recaptcha', 'response', 'count',
		'stripe', 'turnstile',
	);

	/** Tag types that have no name (first option is not a name). */
	const NAMELESS_TYPES = array( 'submit', 'response', 'recaptcha', 'stripe', 'turnstile' );

	/** HTML elements that start a new line of text. */
	const BLOCK_ELEMENTS = array(
		'p', 'div', 'br', 'hr', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'li', 'ul', 'ol',
		'table', 'tr', 'td', 'th', 'thead', 'tbody', 'fieldset', 'legend', 'section',
		'article', 'header', 'footer', 'form', 'dl', 'dt', 'dd', 'blockquote', 'pre',
		'label', 'figure', 'figcaption', 'nav', 'aside', 'main', 'details', 'summary',
	);

	/** @var array<string,bool> Recognised base types. */
	private $types = array();

	/**
	 * @param string[] $extra_types Further base types to recognise, e.g. the
	 *                              ones Contact Form 7 add-ons registered.
	 */
	public function __construct( array $extra_types = array() ) {
		foreach ( array_merge( self::CORE_TYPES, $extra_types ) as $type ) {
			$type = strtolower( rtrim( (string) $type, '*' ) );
			if ( '' !== $type && preg_match( '/^[a-z][a-z0-9_-]*$/', $type ) ) {
				$this->types[ $type ] = true;
			}
		}
	}

	/**
	 * Whether a base type is one this parser recognises as a form-tag.
	 *
	 * @param string $basetype Type without "*".
	 * @return bool
	 */
	public function knows( $basetype ) {
		return isset( $this->types[ strtolower( (string) $basetype ) ] );
	}

	/**
	 * Split a template into an ordered list of nodes.
	 *
	 * @param string $template The `_form` meta.
	 * @return array[] Each node is ['node' => 'html', 'html' => string] or
	 *                 ['node' => 'tag', 'tag' => array] (see read_tag()).
	 */
	public function tokenize( $template ) {
		$s     = (string) $template;
		$len   = strlen( $s );
		$nodes = array();
		$buf   = '';
		$i     = 0;

		while ( $i < $len ) {
			if ( '[' !== $s[ $i ] ) {
				$next = strpos( $s, '[', $i );
				if ( false === $next ) {
					$buf .= substr( $s, $i );
					break;
				}
				$buf .= substr( $s, $i, $next - $i );
				$i    = $next;
				continue;
			}

			// [[tag]] is Contact Form 7's escape: it prints "[tag]" as text.
			if ( $i + 1 < $len && '[' === $s[ $i + 1 ] ) {
				$inner = $this->read_tag( $s, $i + 1 );
				if ( null !== $inner && $inner['end'] < $len && ']' === $s[ $inner['end'] ] ) {
					$buf .= substr( $s, $i + 1, $inner['end'] - $i - 1 );
					$i    = $inner['end'] + 1;
					continue;
				}
			}

			$tag = $this->read_tag( $s, $i );
			if ( null === $tag ) {
				$buf .= '[';
				$i++;
				continue;
			}

			if ( '' !== $buf ) {
				$nodes[] = array( 'node' => 'html', 'html' => $buf );
				$buf     = '';
			}
			$end = $tag['end'];
			unset( $tag['end'] );
			$nodes[] = array( 'node' => 'tag', 'tag' => $tag );
			$i       = $end;
		}

		if ( '' !== $buf ) {
			$nodes[] = array( 'node' => 'html', 'html' => $buf );
		}

		return $nodes;
	}

	/**
	 * Read one form-tag starting at the "[" at $pos.
	 *
	 * @param string $s   Template.
	 * @param int    $pos Offset of "[".
	 * @return array|null The tag, with 'end' = offset just after it, or null
	 *                    when the text at $pos is not a recognised form-tag.
	 */
	public function read_tag( $s, $pos ) {
		$len = strlen( $s );
		if ( $pos >= $len || '[' !== $s[ $pos ] ) {
			return null;
		}

		if ( ! preg_match( '/\G\[([A-Za-z][A-Za-z0-9_-]*)(\*?)/', $s, $m, 0, $pos ) ) {
			return null;
		}

		$basetype = strtolower( $m[1] );
		if ( ! $this->knows( $basetype ) ) {
			return null;
		}

		$i = $pos + strlen( $m[0] );
		if ( $i >= $len ) {
			return null;
		}

		// The type must be followed by whitespace, "/" or "]" - "[textual]" is not [text].
		if ( ']' !== $s[ $i ] && '/' !== $s[ $i ] && ! $this->is_space_at( $s, $i ) ) {
			return null;
		}

		// Attributes run to the first "]" that is not inside quotes.
		$attr_start = $i;
		$quote      = '';
		while ( $i < $len ) {
			$c = $s[ $i ];
			if ( '' !== $quote ) {
				if ( $c === $quote ) {
					$quote = '';
				}
			} elseif ( '"' === $c || "'" === $c ) {
				$quote = $c;
			} elseif ( ']' === $c ) {
				break;
			} elseif ( '[' === $c ) {
				// A new "[" before the tag closed: this was not a tag.
				return null;
			}
			$i++;
		}

		if ( $i >= $len ) {
			return null; // Never closed.
		}

		$attr_text = substr( $s, $attr_start, $i - $attr_start );
		$i++; // Past "]".

		$self_closing = false;
		$attr_trim    = rtrim( $attr_text );
		if ( '' !== $attr_trim && '/' === substr( $attr_trim, -1 ) ) {
			$self_closing = true;
			$attr_text    = substr( $attr_trim, 0, -1 );
		}

		$tag_type = $m[1] . $m[2];
		$raw_end  = $i;
		$content  = '';

		// Enclosed content: [type ...]content[/type], content may not contain "[".
		if ( ! $self_closing ) {
			$close_variants = array( '[/' . $tag_type . ']', '[/' . $m[1] . ']' );
			$next_open      = strpos( $s, '[', $i );
			if ( false !== $next_open ) {
				foreach ( $close_variants as $close ) {
					if ( 0 === strncasecmp( substr( $s, $next_open, strlen( $close ) ), $close, strlen( $close ) ) ) {
						$content = substr( $s, $i, $next_open - $i );
						$raw_end = $next_open + strlen( $close );
						break;
					}
				}
			}
		}

		$tag        = $this->build_tag( $basetype, '*' === $m[2], $attr_text, $content );
		$tag['raw'] = substr( $s, $pos, $raw_end - $pos );
		$tag['end'] = $raw_end;

		return $tag;
	}

	/**
	 * Turn the parts of a form-tag into the array the mapper works with.
	 *
	 * @param string $basetype  Type without "*".
	 * @param bool   $required  Whether the type carried "*".
	 * @param string $attr_text Everything between the type and "]".
	 * @param string $content   Enclosed content, if any.
	 * @return array
	 */
	public function build_tag( $basetype, $required, $attr_text, $content = '' ) {
		$atts = $this->parse_atts( $attr_text );

		$tag = array(
			'type'     => $basetype . ( $required ? '*' : '' ),
			'basetype' => $basetype,
			'required' => (bool) $required,
			'name'     => '',
			'valid'    => true,
			'options'  => $atts['options'],
			'values'   => array(),
			'labels'   => array(),
			'pipes'    => array(),
			'content'  => trim( preg_replace( '#<br[\r\n\t ]*/?>$#m', '', (string) $content ) ),
		);

		if ( ! in_array( $basetype, self::NAMELESS_TYPES, true ) ) {
			$name = (string) array_shift( $tag['options'] );
			if ( ! preg_match( '/^[A-Za-z][-A-Za-z0-9_:.]*$/', $name ) ) {
				$tag['valid'] = false;
			}
			$tag['name'] = strtr( $name, '.', '_' );
		}

		foreach ( $atts['values'] as $raw ) {
			// "Label|value": the visitor sees the part before the first "|",
			// the email carries the part after it.
			$pipe = strpos( $raw, '|' );
			if ( false === $pipe ) {
				$before = $raw;
				$after  = $raw;
			} else {
				$before = substr( $raw, 0, $pipe );
				$after  = substr( $raw, $pipe + 1 );
			}
			$tag['values'][] = trim( $before );
			$tag['labels'][] = trim( $before );
			$tag['pipes'][]  = array( trim( $before ), trim( $after ) );
		}

		return $tag;
	}

	/**
	 * Split the attribute text of a tag into bare options and quoted values.
	 *
	 * @param string $text Attribute text.
	 * @return array ['options' => string[], 'values' => string[]]
	 */
	public function parse_atts( $text ) {
		$text = (string) $text;
		// Non-breaking and zero-width spaces count as spaces, as in CF7.
		$text = str_replace( array( "\xC2\xA0", "\xE2\x80\x8B" ), ' ', $text );
		$len  = strlen( $text );
		$out  = array(
			'options' => array(),
			'values'  => array(),
		);
		$i    = 0;

		while ( $i < $len ) {
			if ( $this->is_space_at( $text, $i ) ) {
				$i += $this->space_len_at( $text, $i );
				continue;
			}

			$c = $text[ $i ];
			if ( '"' === $c || "'" === $c ) {
				$close = strpos( $text, $c, $i + 1 );
				if ( false === $close ) {
					$out['values'][] = substr( $text, $i + 1 );
					break;
				}
				$out['values'][] = substr( $text, $i + 1, $close - $i - 1 );
				$i               = $close + 1;
				continue;
			}

			$start = $i;
			while ( $i < $len && ! $this->is_space_at( $text, $i ) && '"' !== $text[ $i ] && "'" !== $text[ $i ] ) {
				$i++;
			}
			$out['options'][] = substr( $text, $start, $i - $start );
		}

		return $out;
	}

	/*
	 * ---------------------------------------------------------------------
	 * Option readers, matching WPCF7_FormTag's semantics.
	 * ---------------------------------------------------------------------
	 */

	/**
	 * @param array  $tag  A tag from build_tag().
	 * @param string $name Option name, e.g. "placeholder" or "default".
	 * @return bool Whether "name" or "name:anything" is present.
	 */
	public static function has_option( array $tag, $name ) {
		$pattern = '/^' . preg_quote( $name, '/' ) . '(:.+)?$/i';
		foreach ( $tag['options'] as $option ) {
			if ( preg_match( $pattern, $option ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Values of "name:value" options.
	 *
	 * @param array  $tag     A tag.
	 * @param string $name    Option name.
	 * @param string $pattern Regex for the value part ('' = anything).
	 * @return string[]
	 */
	public static function get_options( array $tag, $name, $pattern = '' ) {
		$pattern = '' === $pattern ? '.+' : $pattern;
		$regex   = '/^' . preg_quote( $name, '/' ) . ':(' . $pattern . ')$/i';
		$found   = array();
		foreach ( $tag['options'] as $option ) {
			if ( preg_match( $regex, $option, $m ) ) {
				$found[] = $m[1];
			}
		}
		return $found;
	}

	/**
	 * First value of a "name:value" option, or false.
	 *
	 * @param array  $tag     A tag.
	 * @param string $name    Option name.
	 * @param string $pattern Regex for the value part.
	 * @return string|false
	 */
	public static function get_option( array $tag, $name, $pattern = '' ) {
		$found = self::get_options( $tag, $name, $pattern );
		return empty( $found ) ? false : $found[0];
	}

	/**
	 * maxlength, from "maxlength:N" or the "40/100" (size/maxlength) and
	 * "40x10/2000" (cols x rows / maxlength) shorthands.
	 *
	 * @param array $tag A tag.
	 * @return int|false
	 */
	public static function maxlength( array $tag ) {
		$v = self::get_option( $tag, 'maxlength', '[0-9]+' );
		if ( false !== $v ) {
			return (int) $v;
		}
		foreach ( $tag['options'] as $option ) {
			if ( preg_match( '%^(?:[0-9]*x?[0-9]*)?/([0-9]+)$%', $option, $m ) ) {
				return (int) $m[1];
			}
		}
		return false;
	}

	/**
	 * @param array $tag A tag.
	 * @return int|false
	 */
	public static function minlength( array $tag ) {
		$v = self::get_option( $tag, 'minlength', '[0-9]+' );
		return false === $v ? false : (int) $v;
	}

	/**
	 * File size limit in bytes from "limit:2mb", "limit:500kb" or "limit:1024".
	 *
	 * @param array $tag A tag.
	 * @return int|false
	 */
	public static function limit_bytes( array $tag ) {
		foreach ( $tag['options'] as $option ) {
			if ( preg_match( '/^limit:([1-9][0-9]*)([kKmM]?[bB])?$/', $option, $m ) ) {
				$size = (int) $m[1];
				$unit = isset( $m[2] ) ? strtolower( $m[2] ) : '';
				if ( 'kb' === $unit ) {
					$size *= 1024;
				} elseif ( 'mb' === $unit ) {
					$size *= 1048576;
				}
				return $size;
			}
		}
		return false;
	}

	/**
	 * Accepted file extensions from "filetypes:jpg|png|pdf".
	 *
	 * @param array $tag A tag.
	 * @return string[] Lower-case extensions without dots; empty = not set.
	 */
	public static function filetypes( array $tag ) {
		$types = array();
		foreach ( self::get_options( $tag, 'filetypes' ) as $value ) {
			foreach ( preg_split( '/[|,\s]+/', $value ) as $type ) {
				$type = strtolower( ltrim( trim( $type ), '.' ) );
				if ( '' !== $type && preg_match( '/^[a-z0-9*\/+-]+$/', $type ) ) {
					$types[ $type ] = true;
				}
			}
		}
		return array_keys( $types );
	}

	/**
	 * The "default:" option split into its parts: 1-based choice numbers and
	 * the dynamic sources (user_*, get, post, post_meta, shortcode_attr).
	 *
	 * @param array $tag A tag.
	 * @return array ['indexes' => int[] (0-based), 'dynamic' => string[]]
	 */
	public static function defaults( array $tag ) {
		$out = array(
			'indexes' => array(),
			'dynamic' => array(),
		);
		foreach ( self::get_options( $tag, 'default' ) as $value ) {
			$value = strtolower( $value );
			if ( preg_match( '/^[0-9_]+$/', $value ) ) {
				foreach ( explode( '_', $value ) as $num ) {
					$num = (int) $num;
					if ( $num > 0 ) {
						$out['indexes'][] = $num - 1;
					}
				}
			} elseif ( 'on' !== $value ) {
				$out['dynamic'][] = $value;
			}
		}
		return $out;
	}

	/*
	 * ---------------------------------------------------------------------
	 * Markup between the tags.
	 * ---------------------------------------------------------------------
	 */

	/**
	 * Turn the tokenized template into one flat stream of HTML start tags,
	 * end tags, text and form-tags.
	 *
	 * @param array[] $nodes Output of tokenize().
	 * @return array[] Items:
	 *   ['t' => 'open',  'name' => 'label', 'attrs' => array]
	 *   ['t' => 'close', 'name' => 'label']
	 *   ['t' => 'text',  'v' => string]      (raw HTML text, not decoded)
	 *   ['t' => 'tag',   'tag' => array]
	 */
	public function markup_stream( array $nodes ) {
		$stream = array();
		foreach ( $nodes as $node ) {
			if ( 'tag' === $node['node'] ) {
				$stream[] = array(
					't'   => 'tag',
					'tag' => $node['tag'],
				);
				continue;
			}
			foreach ( $this->lex_html( $node['html'] ) as $item ) {
				$stream[] = $item;
			}
		}
		return $stream;
	}

	/**
	 * A small HTML lexer: start tags (with attributes), end tags and text.
	 * Comments, doctype and processing instructions are dropped; quotes in
	 * attribute values are honoured.
	 *
	 * @param string $html Markup.
	 * @return array[]
	 */
	public function lex_html( $html ) {
		$html  = (string) $html;
		$len   = strlen( $html );
		$items = array();
		$text  = '';
		$i     = 0;

		$flush = function () use ( &$text, &$items ) {
			if ( '' !== $text ) {
				$items[] = array(
					't' => 'text',
					'v' => $text,
				);
				$text = '';
			}
		};

		while ( $i < $len ) {
			$c = $html[ $i ];
			if ( '<' !== $c ) {
				$next = strpos( $html, '<', $i );
				if ( false === $next ) {
					$text .= substr( $html, $i );
					break;
				}
				$text .= substr( $html, $i, $next - $i );
				$i     = $next;
				continue;
			}

			if ( '<!--' === substr( $html, $i, 4 ) ) {
				$close = strpos( $html, '-->', $i + 4 );
				$i     = false === $close ? $len : $close + 3;
				continue;
			}

			if ( ! preg_match( '#\G<(/?)([A-Za-z][A-Za-z0-9:-]*)#', $html, $m, 0, $i ) ) {
				if ( preg_match( '#\G<[!?]#', $html, $mm, 0, $i ) ) {
					$close = strpos( $html, '>', $i );
					$i     = false === $close ? $len : $close + 1;
					continue;
				}
				$text .= '<';
				$i++;
				continue;
			}

			// Find the ">" that ends this tag, outside quoted attribute values.
			$j     = $i + strlen( $m[0] );
			$quote = '';
			while ( $j < $len ) {
				$ch = $html[ $j ];
				if ( '' !== $quote ) {
					if ( $ch === $quote ) {
						$quote = '';
					}
				} elseif ( '"' === $ch || "'" === $ch ) {
					$quote = $ch;
				} elseif ( '>' === $ch ) {
					break;
				}
				$j++;
			}

			$flush();
			$name = strtolower( $m[2] );
			if ( '/' === $m[1] ) {
				$items[] = array(
					't'    => 'close',
					'name' => $name,
				);
			} else {
				$attr_text = substr( $html, $i + strlen( $m[0] ), max( 0, $j - $i - strlen( $m[0] ) ) );
				$items[]   = array(
					't'     => 'open',
					'name'  => $name,
					'attrs' => $this->html_attrs( rtrim( $attr_text, '/ ' ) ),
				);
				// Raw-text elements: their content is not form copy.
				if ( in_array( $name, array( 'script', 'style', 'template', 'noscript' ), true ) ) {
					$end   = $j < $len ? stripos( $html, '</' . $name, $j ) : false;
					$close = false === $end ? false : strpos( $html, '>', $end );
					$i     = false === $close ? $len : $close + 1;
					continue;
				}
			}
			$i = $j + 1;
		}

		$flush();
		return $items;
	}

	/**
	 * Attributes of an HTML start tag.
	 *
	 * @param string $text Text after the element name.
	 * @return array<string,string> Lower-case names, decoded values.
	 */
	public function html_attrs( $text ) {
		$attrs = array();
		if ( preg_match_all( '/([A-Za-z_:][-A-Za-z0-9_:.]*)(?:\s*=\s*(?:"([^"]*)"|\'([^\']*)\'|([^\s"\'>]+)))?/', (string) $text, $all, PREG_SET_ORDER ) ) {
			foreach ( $all as $a ) {
				$value = '';
				if ( isset( $a[2] ) && '' !== $a[2] ) {
					$value = $a[2];
				} elseif ( isset( $a[3] ) && '' !== $a[3] ) {
					$value = $a[3];
				} elseif ( isset( $a[4] ) ) {
					$value = $a[4];
				}
				$attrs[ strtolower( $a[1] ) ] = html_entity_decode( $value, ENT_QUOTES, 'UTF-8' );
			}
		}
		return $attrs;
	}

	/**
	 * @param string $s Text.
	 * @param int    $i Offset.
	 * @return bool Whether a (Unicode) whitespace character starts at $i.
	 */
	private function is_space_at( $s, $i ) {
		return $this->space_len_at( $s, $i ) > 0;
	}

	/**
	 * Byte length of the whitespace character at $i, 0 when there is none.
	 * Covers the set wpcf7_get_unicode_whitespaces() uses.
	 *
	 * @param string $s Text.
	 * @param int    $i Offset.
	 * @return int
	 */
	private function space_len_at( $s, $i ) {
		$c = $s[ $i ];
		if ( ' ' === $c || "\t" === $c || "\n" === $c || "\r" === $c || "\x0B" === $c || "\x0C" === $c ) {
			return 1;
		}
		$o = ord( $c );
		if ( $o < 0xC2 ) {
			return 0;
		}
		$two = substr( $s, $i, 2 );
		if ( "\xC2\x85" === $two || "\xC2\xA0" === $two ) {
			return 2;
		}
		$three = substr( $s, $i, 3 );
		static $wide = null;
		if ( null === $wide ) {
			$wide = array( "\xE1\x9A\x80", "\xE2\x80\xA8", "\xE2\x80\xA9", "\xE2\x80\xAF", "\xE2\x81\x9F", "\xE3\x80\x80" );
			for ( $k = 0x80; $k <= 0x8A; $k++ ) {
				$wide[] = "\xE2\x80" . chr( $k ); // U+2000..U+200A
			}
		}
		return in_array( $three, $wide, true ) ? 3 : 0;
	}
}
