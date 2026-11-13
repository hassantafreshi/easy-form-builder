<?php
/**
 * Contact Form 7 importer - turns one Contact Form 7 form into the field list
 * the Easy Form Builder builder saves (the `form_structer` array).
 *
 * Every object produced here has the shape the builder itself pushes when a
 * field is dropped on the canvas (sampleElpush_efb() / optionElpush_efb() in
 * admin-efb.js, creator_form_builder_Efb() in val-efb.js), so an imported form
 * opens, previews, saves and renders like any other. Only field types that are
 * free on every plan are used.
 *
 * Alongside the structure the mapper writes a report in plain words: which
 * fields were carried over, which settings were set, and every part of the
 * Contact Form 7 form that could not be carried over with the reason.
 *
 * @package Easy_Form_Builder
 */

namespace Emsfb\Importers\CF7;

defined( 'ABSPATH' ) || exit;

class Mapper {

	/** Field types this mapper produces and the phrase key naming each. */
	const TYPE_PHRASES = array(
		'text'        => 'text',
		'email'       => 'email',
		'url'         => 'url',
		'tel'         => 'tel',
		'number'      => 'number',
		'range'       => 'range',
		'date'        => 'ddate',
		'textarea'    => 'textarea',
		'select'      => 'select',
		'multiselect' => 'multiselect',
		'checkbox'    => 'checkbox',
		'radio'       => 'radiobutton',
		'file'        => 'file',
		'trmCheckbox' => 'trmCheckbox',
		'heading'     => 'heading',
	);

	/** EFB upload presets and the extensions each accepts (Upload_Guard). */
	const FILE_PRESETS = array(
		'image'    => array( 'png', 'jpg', 'jpeg', 'gif', 'heic', 'heif' ),
		'document' => array( 'xlsx', 'xls', 'doc', 'docx', 'dot', 'dotx', 'ppt', 'pptx', 'pptm', 'txt', 'pdf', 'rtf', 'odt', 'ods', 'odp' ),
		'media'    => array( 'mp3', 'wav', 'ogg', 'oga', 'webm', 'm4a', 'aac', 'mp4', 'mkv', 'avi', 'mpeg', 'mpg', 'mov' ),
		'zip'      => array( 'zip', 'rar', '7z', 'tar', 'gz', 'gzip', 'tgz', 'bz', 'bz2', 'bzip', 'bzip2', 'tbz', 'tbz2', 'tz', 'tz2', 'z' ),
	);

	/** Phrase key naming each upload preset. */
	const FILE_PRESET_PHRASES = array(
		'image'     => 'image',
		'document'  => 'documents',
		'media'     => 'media',
		'zip'       => 'zip',
		'allformat' => 'allformat',
	);

	/** Contact Form 7's own default upload list (used when filetypes: is absent). */
	const CF7_DEFAULT_FILETYPES = array( 'jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx', 'ppt', 'pptx', 'odt', 'avi', 'ogg', 'm4a', 'mov', 'mp3', 'mp4', 'mpg', 'wav', 'wmv' );

	/** @var Parser */
	private $parser;

	/** @var array Environment, see __construct(). */
	private $env;

	/** @var array EFB phrases (text_efb()). */
	private $texts;

	/** @var array The structure being built. */
	private $structure = array();

	/** @var int Next "amount" (order) value. */
	private $amount = 1;

	/** @var array<string,bool> Ids already used. */
	private $ids = array();

	/** @var array CF7 field name => ['id' => EFB id, 'type' => EFB type, 'label' => string]. */
	private $by_name = array();

	/** @var array Report: fields carried over. */
	private $fields = array();

	/** @var string[] Report: what could not be carried over. */
	private $notes = array();

	/** @var string[] Report: settings that were set. */
	private $settings = array();

	/** @var array<string,bool> Notes already given once per form. */
	private $once = array();

	/** @var array<string,bool> id: options used by the form's tags. */
	private $tag_ids = array();

	/**
	 * @param Parser $parser Template parser.
	 * @param array  $env {
	 *     @type array    $texts           EFB phrases (text_efb()) by key.
	 *     @type bool     $rtl             Whether labels align right.
	 *     @type string   $admin_email     The site's admin email.
	 *     @type string   $efb_admin_email EFB's own notification address setting.
	 *     @type bool     $captcha_ready   Whether EFB has reCAPTCHA keys.
	 *     @type bool     $sending_enabled Whether EFB may send emails.
	 *     @type callable $data_source     fn(string[] $names): string[] values of
	 *                                     data: options (Listo), or null.
	 *     @type callable $id_factory      fn(): string, for tests.
	 * }
	 */
	public function __construct( Parser $parser, array $env ) {
		$this->parser = $parser;
		$this->env    = array_merge(
			array(
				'texts'           => array(),
				'rtl'             => false,
				'admin_email'     => '',
				'efb_admin_email' => '',
				'efb_from'        => '',
				'captcha_ready'   => false,
				'sending_enabled' => true,
				'data_source'     => null,
				'id_factory'      => null,
			),
			$env
		);
		$this->texts  = (array) $this->env['texts'];
	}

	/**
	 * Map one form.
	 *
	 * @param array $form {
	 *     @type string $title               Form title.
	 *     @type string $form                The `_form` template.
	 *     @type array  $mail                `_mail`.
	 *     @type array  $mail_2              `_mail_2`.
	 *     @type array  $messages            `_messages`.
	 *     @type string $additional_settings `_additional_settings`.
	 * }
	 * @return array {
	 *     @type array    $structure The form_structer array (index 0 = settings).
	 *     @type string   $name      Form name.
	 *     @type array[]  $fields    [['label','type','typeLabel','required']].
	 *     @type string[] $settings  Settings that were set, in plain words.
	 *     @type string[] $notes     What could not be carried over, and why.
	 * }
	 */
	public function map( array $form ) {
		$this->structure = array();
		$this->amount    = 1;
		$this->ids       = array();
		$this->by_name   = array();
		$this->fields    = array();
		$this->notes     = array();
		$this->settings  = array();
		$this->once      = array();

		$form = array_merge(
			array(
				'title'               => '',
				'form'                => '',
				'mail'                => array(),
				'mail_2'              => array(),
				'messages'            => array(),
				'additional_settings' => '',
			),
			$form
		);

		$name = $this->clean_text( $form['title'] );
		if ( '' === $name ) {
			$name = $this->t( 'form', 'Form' );
		}
		if ( function_exists( 'mb_substr' ) ) {
			$name = mb_substr( $name, 0, 240 );
		}

		$head = $this->form_settings( $name );
		$this->structure[] = $head;
		$this->structure[] = $this->step( $name );

		$button = $this->map_template( (string) $form['form'] );
		if ( '' !== $button ) {
			$this->structure[0]['button_single_text'] = $button;
		}

		$flags = $this->additional_settings( (string) $form['additional_settings'] );
		$this->map_mail( (array) $form['mail'], (array) $form['mail_2'], ! empty( $flags['skip_mail'] ) );
		$this->map_messages( (array) $form['messages'] );
		$this->map_additional_settings( $flags );

		return array(
			'structure' => $this->structure,
			'name'      => $name,
			'fields'    => $this->fields,
			'settings'  => array_values( $this->settings ),
			'notes'     => array_values( array_unique( $this->notes ) ),
		);
	}

	/*
	 * ---------------------------------------------------------------------
	 * The template
	 * ---------------------------------------------------------------------
	 */

	/**
	 * Walk the template: resolve labels, then emit fields and free-standing
	 * text in document order.
	 *
	 * @param string $template `_form`.
	 * @return string The submit button's label, '' when there is none.
	 */
	private function map_template( $template ) {
		$stream = $this->annotate( $this->parser->markup_stream( $this->parser->tokenize( $template ) ) );
		$items  = $stream['items'];
		$labels = $stream['labels'];

		// id: options in use, so a <label for> is only ever read for its own tag.
		$this->tag_ids = array();
		foreach ( $items as $item ) {
			if ( 'tag' === $item['t'] ) {
				$tag_id = Parser::get_option( $item['tag'], 'id', '[-0-9a-zA-Z_]+' );
				if ( false !== $tag_id ) {
					$this->tag_ids[ $tag_id ] = true;
				}
			}
		}

		// Which <label for="..."> elements hold no form-tag of their own.
		$label_for = array();
		foreach ( $labels as $li => $label ) {
			if ( '' !== $label['for'] && empty( $label['tags'] ) && ! isset( $label_for[ $label['for'] ] ) ) {
				$label_for[ $label['for'] ] = $li;
			}
		}

		// Pass 1: decide each tag's label, consuming the text used for it.
		$tag_labels = array();
		foreach ( $items as $k => $item ) {
			if ( 'tag' !== $item['t'] ) {
				continue;
			}
			$tag_labels[ $k ] = $this->resolve_label( $k, $items, $labels, $label_for );
		}

		// Pass 2: emit in order.
		$button     = '';
		$done_lines = array();
		foreach ( $items as $k => $item ) {
			if ( 'text' === $item['t'] ) {
				if ( ! empty( $items[ $k ]['used'] ) || isset( $done_lines[ $item['line'] ] ) ) {
					continue;
				}
				$done_lines[ $item['line'] ] = true;
				$this->emit_static_line( $item['line'], $items );
				continue;
			}
			if ( 'img' === $item['t'] ) {
				$this->note_once( 'img', __( 'An image in the form was not carried over.', 'easy-form-builder' ) );
				continue;
			}
			if ( 'tag' !== $item['t'] ) {
				continue;
			}
			$tag = $item['tag'];
			if ( 'submit' === $tag['basetype'] ) {
				if ( '' === $button && ! empty( $tag['values'] ) ) {
					$button = $this->clean_text( $tag['values'][0] );
				}
				continue;
			}
			$this->map_tag( $tag, $tag_labels[ $k ], $k, $items );
		}

		return $button;
	}

	/**
	 * Annotate the raw stream: decode and split text into lines, and record
	 * for every text and tag which <label>, heading, legend or link it sits in.
	 *
	 * @param array[] $raw markup_stream() output.
	 * @return array ['items' => array[], 'labels' => array[]]
	 */
	private function annotate( array $raw ) {
		$items    = array();
		$labels   = array();
		$line     = 0;
		$stack    = array(); // Open elements that matter for context.
		$label_at = array(); // Stack of open label indexes.

		foreach ( $raw as $r ) {
			if ( 'open' === $r['t'] || 'close' === $r['t'] ) {
				$name = $r['name'];
				if ( in_array( $name, Parser::BLOCK_ELEMENTS, true ) ) {
					$line++;
				}
				if ( 'open' === $r['t'] ) {
					if ( 'img' === $name ) {
						$items[] = array( 't' => 'img' );
						continue;
					}
					if ( 'label' === $name ) {
						$labels[]   = array(
							'for'   => isset( $r['attrs']['for'] ) ? trim( $r['attrs']['for'] ) : '',
							'items' => array(),
							'tags'  => array(),
						);
						$label_at[] = count( $labels ) - 1;
					} elseif ( in_array( $name, array( 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'legend', 'a' ), true ) ) {
						$stack[] = array(
							'name' => $name,
							'href' => isset( $r['attrs']['href'] ) ? $r['attrs']['href'] : '',
						);
					}
				} else {
					if ( 'label' === $name ) {
						array_pop( $label_at );
					} else {
						for ( $s = count( $stack ) - 1; $s >= 0; $s-- ) {
							if ( $stack[ $s ]['name'] === $name ) {
								array_splice( $stack, $s );
								break;
							}
						}
					}
				}
				continue;
			}

			$ctx = array(
				'label'   => empty( $label_at ) ? -1 : end( $label_at ),
				'heading' => 0,
				'legend'  => false,
				'link'    => '',
			);
			foreach ( $stack as $el ) {
				if ( preg_match( '/^h([1-6])$/', $el['name'], $hm ) ) {
					$ctx['heading'] = (int) $hm[1];
				} elseif ( 'legend' === $el['name'] ) {
					$ctx['legend'] = true;
				} elseif ( 'a' === $el['name'] ) {
					$ctx['link'] = $el['href'];
				}
			}

			if ( 'tag' === $r['t'] ) {
				$line++;
				$items[] = array_merge(
					$ctx,
					array(
						't'    => 'tag',
						'tag'  => $r['tag'],
						'line' => $line,
					)
				);
				if ( $ctx['label'] >= 0 ) {
					$labels[ $ctx['label'] ]['tags'][] = count( $items ) - 1;
				}
				$line++;
				continue;
			}

			// Text: split at newlines, each piece is its own line.
			$pieces = preg_split( '/\r\n|\r|\n/', html_entity_decode( $r['v'], ENT_QUOTES | ENT_HTML5, 'UTF-8' ) );
			foreach ( $pieces as $p => $piece ) {
				if ( $p > 0 ) {
					$line++;
				}
				$items[] = array_merge(
					$ctx,
					array(
						't'    => 'text',
						'v'    => $piece,
						'line' => $line,
						'used' => false,
					)
				);
				if ( $ctx['label'] >= 0 ) {
					$labels[ $ctx['label'] ]['items'][] = count( $items ) - 1;
				}
			}
		}

		return array(
			'items'  => $items,
			'labels' => $labels,
		);
	}

	/**
	 * The visible label of the form-tag at $k, consuming the text it takes.
	 *
	 * In order: the text of the <label> around the tag; a <label for> that
	 * points at the tag's id: option; the nearest line of text before the
	 * tag (not crossing another tag or a heading). '' when none applies.
	 *
	 * @param int     $k         Stream index of the tag.
	 * @param array[] $items     Stream (by reference: marks text as used).
	 * @param array[] $labels    Label elements.
	 * @param array   $label_for for => label index.
	 * @return string
	 */
	private function resolve_label( $k, array &$items, array $labels, array $label_for ) {
		$tag = $items[ $k ]['tag'];

		// Tags that never have a visible label of their own.
		if ( in_array( $tag['basetype'], array( 'submit', 'response', 'hidden', 'count', 'recaptcha', 'stripe', 'turnstile' ), true ) ) {
			return '';
		}

		// 1. Inside a <label>.
		$li = $items[ $k ]['label'];
		if ( $li >= 0 && isset( $labels[ $li ] ) ) {
			$before = array();
			$after  = array();
			foreach ( $labels[ $li ]['items'] as $ti ) {
				if ( ! empty( $items[ $ti ]['used'] ) ) {
					continue;
				}
				if ( $ti < $k ) {
					// Only text after the previous tag in the same label.
					$prev_tag = -1;
					foreach ( $labels[ $li ]['tags'] as $tg ) {
						if ( $tg < $k ) {
							$prev_tag = $tg;
						}
					}
					if ( $ti > $prev_tag ) {
						$before[] = $ti;
					}
				} else {
					$next_tag = PHP_INT_MAX;
					foreach ( $labels[ $li ]['tags'] as $tg ) {
						if ( $tg > $k && $tg < $next_tag ) {
							$next_tag = $tg;
						}
					}
					if ( $ti < $next_tag ) {
						$after[] = $ti;
					}
				}
			}
			$pick = $this->join_items( $before, $items ) !== '' ? $before : $after;
			$text = $this->join_items( $pick, $items );
			if ( '' !== $text ) {
				$this->mark_used( $pick, $items );
				return $text;
			}
		}

		// 2. <label for="id">.
		$id = Parser::get_option( $tag, 'id', '[-0-9a-zA-Z_]+' );
		if ( false !== $id && isset( $label_for[ $id ] ) ) {
			$pick = array();
			foreach ( $labels[ $label_for[ $id ] ]['items'] as $ti ) {
				if ( empty( $items[ $ti ]['used'] ) ) {
					$pick[] = $ti;
				}
			}
			$text = $this->join_items( $pick, $items );
			if ( '' !== $text ) {
				$this->mark_used( $pick, $items );
				return $text;
			}
		}

		// 3. The nearest line of text before the tag.
		$line_items = array();
		$line_id    = null;
		for ( $j = $k - 1; $j >= 0; $j-- ) {
			$it = $items[ $j ];
			if ( 'tag' === $it['t'] ) {
				break;
			}
			if ( 'text' !== $it['t'] || ! empty( $it['used'] ) ) {
				continue;
			}
			if ( null === $line_id ) {
				if ( '' === $this->clean_label( $it['v'] ) ) {
					continue;
				}
				// A heading is the form's own copy, not this field's label; a
				// legend labels the choice group it sits above.
				$choice = in_array( $tag['basetype'], array( 'checkbox', 'radio', 'acceptance' ), true );
				if ( $it['heading'] > 0 || ( $it['legend'] && ! $choice ) ) {
					break;
				}
				// A <label for> that names another tag's id belongs to that tag.
				if ( $it['label'] >= 0 && isset( $labels[ $it['label'] ] ) ) {
					$for = $labels[ $it['label'] ]['for'];
					if ( '' !== $for && isset( $this->tag_ids[ $for ] ) && $for !== $id ) {
						break;
					}
				}
				// A sentence above a field that has its own placeholder is the
				// form's copy ("Tell us about your project."), not the label.
				if ( $this->has_placeholder( $tag ) && preg_match( '/[.!。]$/u', $this->clean_label( $it['v'] ) ) ) {
					break;
				}
				$line_id = $it['line'];
			}
			if ( $it['line'] !== $line_id ) {
				break;
			}
			array_unshift( $line_items, $j );
		}
		$text = $this->join_items( $line_items, $items );
		if ( '' !== $text ) {
			$this->mark_used( $line_items, $items );
			return $text;
		}

		return '';
	}

	/**
	 * Text on the same line right after the tag at $k (an acceptance box
	 * written as "[acceptance a] I agree"), consumed.
	 *
	 * @param int     $k     Stream index.
	 * @param array[] $items Stream.
	 * @return array ['text' => string, 'html' => string]
	 */
	private function text_after( $k, array &$items ) {
		$pick = array();
		$line = null;
		for ( $j = $k + 1, $n = count( $items ); $j < $n; $j++ ) {
			$it = $items[ $j ];
			if ( 'tag' === $it['t'] ) {
				break;
			}
			if ( 'text' !== $it['t'] || ! empty( $it['used'] ) ) {
				continue;
			}
			if ( null === $line ) {
				if ( '' === trim( $it['v'] ) ) {
					continue;
				}
				$line = $it['line'];
			}
			if ( $it['line'] !== $line ) {
				break;
			}
			$pick[] = $j;
		}
		$text = $this->join_items( $pick, $items );
		$html = '';
		foreach ( $pick as $ti ) {
			$html .= '' !== $items[ $ti ]['link']
				? '[' . trim( $items[ $ti ]['v'] ) . '](' . $items[ $ti ]['link'] . ')'
				: $items[ $ti ]['v'];
		}
		$this->mark_used( $pick, $items );
		return array(
			'text' => $text,
			'html' => $html,
		);
	}

	/**
	 * Emit one line of free-standing copy as a heading field.
	 *
	 * @param int     $line  Line id.
	 * @param array[] $items Stream.
	 * @return void
	 */
	private function emit_static_line( $line, array &$items ) {
		$text    = '';
		$heading = 0;
		$legend  = false;
		$link    = false;
		foreach ( $items as $ti => $it ) {
			if ( 'text' !== $it['t'] || $it['line'] !== $line || ! empty( $it['used'] ) ) {
				continue;
			}
			$text   .= $it['v'];
			$heading = max( $heading, $it['heading'] );
			$legend  = $legend || $it['legend'];
			$link    = $link || '' !== $it['link'];
			$items[ $ti ]['used'] = true;
		}
		$text = $this->clean_text( $text );
		// Nothing readable: stray punctuation, an asterisk legend, a "|".
		if ( '' === $text || ! preg_match( '/[\p{L}\p{N}]/u', $text ) ) {
			return;
		}

		if ( $heading > 0 && $heading <= 2 ) {
			$size  = 'display-6';
			$color = 'text-dark';
		} elseif ( $heading > 0 || $legend ) {
			$size  = 'display-7';
			$color = 'text-dark';
		} else {
			$size  = 'display-8';
			$color = 'text-muted';
		}

		$this->add_heading( $text, $size, $color );
		if ( $link ) {
			$this->note(
				sprintf(
					/* translators: %s: a line of text from the Contact Form 7 form */
					__( 'A link in “%s” is now plain text.', 'easy-form-builder' ),
					$this->short( $text )
				)
			);
		}
	}

	/*
	 * ---------------------------------------------------------------------
	 * One form-tag
	 * ---------------------------------------------------------------------
	 */

	/**
	 * Map one form-tag to EFB field(s), or to a note.
	 *
	 * @param array   $tag   Parsed tag.
	 * @param string  $label Label from the markup ('' = none).
	 * @param int     $k     Stream index.
	 * @param array[] $items Stream.
	 * @return void
	 */
	private function map_tag( array $tag, $label, $k, array &$items ) {
		$type = $tag['basetype'];

		if ( ! in_array( $type, Parser::CORE_TYPES, true ) ) {
			$this->note(
				sprintf(
					/* translators: %s: a form-tag type such as "dynamictext" */
					__( 'The “[%s]” tag comes from another plugin and was not carried over.', 'easy-form-builder' ),
					$type
				)
			);
			return;
		}

		if ( ! $tag['valid'] ) {
			$this->note(
				sprintf(
					/* translators: %s: a Contact Form 7 tag type such as "text" */
					__( 'A “[%s]” tag without a valid name was skipped.', 'easy-form-builder' ),
					$tag['type']
				)
			);
			return;
		}

		$label = $this->clean_label( $label );

		switch ( $type ) {
			case 'text':
			case 'email':
			case 'url':
			case 'tel':
				$this->map_text_like( $tag, $label, $type );
				return;
			case 'number':
			case 'range':
				$this->map_number( $tag, $label );
				return;
			case 'date':
				$this->map_date( $tag, $label );
				return;
			case 'textarea':
				$this->map_text_like( $tag, $label, 'textarea' );
				return;
			case 'select':
				$this->map_select( $tag, $label );
				return;
			case 'checkbox':
			case 'radio':
				$this->map_choices( $tag, $label );
				return;
			case 'acceptance':
				$this->map_acceptance( $tag, $label, $k, $items );
				return;
			case 'file':
				$this->map_file( $tag, $label );
				return;
			case 'quiz':
				$question = implode( ' / ', array_filter( array_map( array( $this, 'clean_text' ), $tag['labels'] ) ) );
				if ( '' === $question ) {
					$question = '' !== $label ? $label : $tag['name'];
				}
				$this->note(
					sprintf(
						/* translators: %s: the quiz question */
						__( 'The quiz question “%s” was not carried over: Easy Form Builder has no quiz question. Turn on reCAPTCHA in the form settings to keep spam out.', 'easy-form-builder' ),
						$this->short( $question )
					)
				);
				return;
			case 'hidden':
				$this->note(
					sprintf(
						/* translators: %s: the field name in Contact Form 7 */
						__( 'The hidden field “%s” was not carried over: Easy Form Builder has no hidden field.', 'easy-form-builder' ),
						$tag['name']
					)
				);
				return;
			case 'captchac':
			case 'captchar':
			case 'recaptcha':
			case 'turnstile':
				$this->map_captcha();
				return;
			case 'count':
				// F03: CF7's [count name] becomes the field's own counter ("down" = what is left).
				if ( isset( $this->by_name[ $tag['name'] ] ) && $this->set_counter( $this->by_name[ $tag['name'] ]['id'], $tag ) ) {
					return;
				}
				$target = isset( $this->by_name[ $tag['name'] ] ) ? $this->by_name[ $tag['name'] ]['label'] : $tag['name'];
				$this->note(
					sprintf(
						/* translators: %s: field label */
						__( 'The character counter of “%s” was not carried over.', 'easy-form-builder' ),
						$this->short( $target )
					)
				);
				return;
			case 'response':
				// Where CF7 prints its reply; EFB shows its own.
				return;
			default:
				$this->note(
					sprintf(
						/* translators: %s: a form-tag type such as "stripe" */
						__( 'The “[%s]” tag comes from another plugin and was not carried over.', 'easy-form-builder' ),
						$type
					)
				);
		}
	}

	/**
	 * text, email, url, tel and textarea.
	 *
	 * @param array  $tag   Tag.
	 * @param string $label Label.
	 * @param string $type  EFB type.
	 * @return void
	 */
	private function map_text_like( array $tag, $label, $type ) {
		// As CF7: a textarea's enclosed content wins over its first value, and
		// with "placeholder" that text becomes the placeholder.
		$value = isset( $tag['values'][0] ) ? $tag['values'][0] : '';
		if ( 'textarea' === $type && '' !== $tag['content'] ) {
			$value = $tag['content'];
		}
		$placeholder = '';
		if ( Parser::has_option( $tag, 'placeholder' ) || Parser::has_option( $tag, 'watermark' ) ) {
			$placeholder = $this->clean_text( $value );
			$value       = '';
		}
		$value = 'textarea' === $type ? $this->clean_multiline( $value ) : $this->clean_text( $value );

		$hide = '' === $label && '' !== $placeholder;
		if ( '' === $label ) {
			$label = '' !== $placeholder ? $placeholder : $this->humanize( $tag['name'] );
		}

		$field                = $this->field( $type, $label, $tag );
		$field['placeholder'] = $placeholder;
		$field['value']       = $value;
		if ( $hide ) {
			// The CF7 form showed only the placeholder; so does the copy.
			$field['hflabel'] = 1;
		}

		$min = Parser::minlength( $tag );
		$max = Parser::maxlength( $tag );
		if ( false !== $min && $min > 0 ) {
			$field['milen'] = (string) $min;
		}
		if ( false !== $max && $max > 0 ) {
			// F03: a textarea has a maximum too (the page limits typing with maxlength).
			$field['mlen'] = (string) $max;
		}

		$this->dynamic_default_note( $tag, $label );
		$this->readonly_note( $tag, $label );
		$this->push_field( $field, $tag );
	}

	/**
	 * number and range.
	 *
	 * @param array  $tag   Tag.
	 * @param string $label Label.
	 * @return void
	 */
	private function map_number( array $tag, $label ) {
		$type        = $tag['basetype'];
		$first       = isset( $tag['values'][0] ) ? trim( $tag['values'][0] ) : '';
		$placeholder = '';
		$value       = $first;
		if ( Parser::has_option( $tag, 'placeholder' ) || Parser::has_option( $tag, 'watermark' ) ) {
			$placeholder = $this->clean_text( $first );
			$value       = '';
		}
		if ( '' !== $value && ! is_numeric( $value ) ) {
			$value = '';
		}
		$hide = '' === $label && '' !== $placeholder && 'number' === $type;
		if ( '' === $label ) {
			$label = '' !== $placeholder ? $placeholder : $this->humanize( $tag['name'] );
		}

		$field                = $this->field( $type, $label, $tag );
		$field['placeholder'] = 'number' === $type ? $placeholder : '';
		$field['value']       = $value;
		if ( $hide ) {
			$field['hflabel'] = 1;
		}

		$min     = Parser::get_option( $tag, 'min', '[-]?(?:[0-9]+|(?:[0-9]+)?[.][0-9]+)' );
		$max     = Parser::get_option( $tag, 'max', '[-]?(?:[0-9]+|(?:[0-9]+)?[.][0-9]+)' );
		$dropped = array();
		// EFB keeps whole, non-negative limits (a max of 0 means "no max").
		if ( false !== $min ) {
			if ( preg_match( '/^[0-9]+$/', $min ) ) {
				$field['milen'] = $min;
			} else {
				$dropped[] = 'min:' . $min;
			}
		}
		if ( false !== $max ) {
			if ( preg_match( '/^[0-9]+$/', $max ) && (int) $max > 0 ) {
				$field['mlen'] = $max;
			} else {
				$dropped[] = 'max:' . $max;
			}
		}
		$step = Parser::get_option( $tag, 'step', '(?:[0-9]+|(?:[0-9]+)?[.][0-9]+)' );
		if ( false !== $step && '1' !== $step ) {
			$dropped[] = 'step:' . $step;
		}
		if ( ! empty( $dropped ) ) {
			$this->note(
				sprintf(
					/* translators: 1: field label, 2: the settings, e.g. "min:-5 step:0.5" */
					__( 'These limits of “%1$s” were not carried over: %2$s.', 'easy-form-builder' ),
					$this->short( $label ),
					implode( ' ', $dropped )
				)
			);
		}

		$this->dynamic_default_note( $tag, $label );
		$this->readonly_note( $tag, $label );
		$this->push_field( $field, $tag );
	}

	/**
	 * date.
	 *
	 * @param array  $tag   Tag.
	 * @param string $label Label.
	 * @return void
	 */
	private function map_date( array $tag, $label ) {
		$first = isset( $tag['values'][0] ) ? trim( $tag['values'][0] ) : '';
		if ( '' === $label ) {
			$label = ( '' !== $first && ( Parser::has_option( $tag, 'placeholder' ) || Parser::has_option( $tag, 'watermark' ) ) )
				? $this->clean_text( $first )
				: $this->humanize( $tag['name'] );
		}

		$field          = $this->field( 'date', $label, $tag );
		$field['value'] = preg_match( '/^[0-9]{4}-[0-9]{2}-[0-9]{2}$/', $first ) ? $first : '';

		$dropped = array();
		foreach ( array( 'min' => 'milen', 'max' => 'mlen' ) as $option => $key ) {
			$v = Parser::get_option( $tag, $option );
			if ( false === $v ) {
				continue;
			}
			if ( preg_match( '/^[0-9]{4}-[0-9]{2}-[0-9]{2}$/', $v ) ) {
				$field[ $key ] = $v;
			} elseif ( 'today' === strtolower( $v ) ) {
				$field[ $key ] = '1'; // EFB: 1 = today.
			} else {
				$dropped[] = $option . ':' . $v;
			}
		}
		if ( Parser::get_option( $tag, 'step', '[0-9]+' ) ) {
			$dropped[] = 'step:' . Parser::get_option( $tag, 'step', '[0-9]+' );
		}
		if ( ! empty( $dropped ) ) {
			$this->note(
				sprintf(
					/* translators: 1: field label, 2: the settings, e.g. "min:today+7days" */
					__( 'These limits of “%1$s” were not carried over: %2$s.', 'easy-form-builder' ),
					$this->short( $label ),
					implode( ' ', $dropped )
				)
			);
		}

		$this->dynamic_default_note( $tag, $label );
		$this->readonly_note( $tag, $label );
		$this->push_field( $field, $tag );
	}

	/**
	 * select (single or multiple).
	 *
	 * @param array  $tag   Tag.
	 * @param string $label Label.
	 * @return void
	 */
	private function map_select( array $tag, $label ) {
		$choices = $this->choices( $tag, $label );
		$values  = $choices['values'];

		if ( Parser::has_option( $tag, 'first_as_label' ) && ! Parser::has_option( $tag, 'include_blank' ) && ! empty( $values ) ) {
			$first = $this->clean_text( array_shift( $values ) );
			if ( '' === $label ) {
				$label = $first;
			}
			// Default choice numbers count the label row too.
			$choices['defaults'] = array_values(
				array_filter(
					array_map(
						function ( $i ) {
							return $i - 1;
						},
						$choices['defaults']
					),
					function ( $i ) {
						return $i >= 0;
					}
				)
			);
		}
		if ( '' === $label ) {
			$label = $this->humanize( $tag['name'] );
		}

		$multiple = Parser::has_option( $tag, 'multiple' );
		$type     = $multiple ? 'multiselect' : 'select';
		$field    = $this->field( $type, $label, $tag );
		if ( $multiple ) {
			$field['maxSelect'] = (string) max( 1, count( $values ) );
			$field['minSelect'] = '0';
		}

		$this->push_with_options( $field, $tag, $values, $choices['defaults'], $multiple );
		$this->choice_notes( $tag, $label, $choices );
	}

	/**
	 * checkbox and radio.
	 *
	 * @param array  $tag   Tag.
	 * @param string $label Label.
	 * @return void
	 */
	private function map_choices( array $tag, $label ) {
		$choices = $this->choices( $tag, $label );
		$values  = $choices['values'];
		$hide    = false;
		if ( '' === $label ) {
			$hide  = 1 === count( $values ) && '' !== $this->clean_text( $values[0] );
			$label = $hide ? $this->short( $this->clean_text( $values[0] ) ) : $this->humanize( $tag['name'] );
		}

		$is_radio  = 'radio' === $tag['basetype'];
		$exclusive = ! $is_radio && Parser::has_option( $tag, 'exclusive' );
		$type      = ( $is_radio || $exclusive ) ? 'radio' : 'checkbox';
		$field     = $this->field( $type, $label, $tag );
		if ( $hide ) {
			// One box whose own text says it all: no label row above it.
			$field['hflabel'] = 1;
		}
		if ( $is_radio ) {
			// Contact Form 7 always requires a radio group.
			$field['required'] = 1;
		}

		$defaults = $choices['defaults'];
		if ( 'radio' === $type ) {
			$defaults = array_slice( $defaults, 0, 1 );
		}
		$this->push_with_options( $field, $tag, $values, $defaults, 'checkbox' === $type );

		if ( $exclusive ) {
			$this->note(
				sprintf(
					/* translators: %s: field label */
					__( '“%s” allowed only one choice, so it is now a group of radio buttons.', 'easy-form-builder' ),
					$this->short( $label )
				)
			);
		}

		// free_text: the last choice had a text box; it becomes its own field.
		if ( Parser::has_option( $tag, 'free_text' ) && ! empty( $values ) ) {
			$last  = $this->clean_text( end( $values ) );
			$extra = $this->field( 'text', '' !== $last ? $last : $label, $tag );
			$this->push_field( $extra, null );
			$this->note(
				sprintf(
					/* translators: %s: field label */
					__( 'The text box of the last choice of “%s” is now a separate text field right after it.', 'easy-form-builder' ),
					$this->short( $label )
				)
			);
		}

		$this->choice_notes( $tag, $label, $choices );
	}

	/**
	 * acceptance: a terms box, or an ordinary checkbox when it was inverted.
	 *
	 * @param array   $tag   Tag.
	 * @param string  $label Label from the markup.
	 * @param int     $k     Stream index.
	 * @param array[] $items Stream.
	 * @return void
	 */
	private function map_acceptance( array $tag, $label, $k, array &$items ) {
		$html = $tag['content'];
		$text = $this->clean_text( $html );
		if ( '' === $text ) {
			$after = $this->text_after( $k, $items );
			$text  = $this->clean_text( $after['text'] );
			$html  = $after['html'];
		}
		$option_text = '' !== $text ? $this->terms_text( $html ) : '';
		if ( '' === $option_text ) {
			$option_text = '' !== $label ? $label : $this->t( 'trmCheckbox', 'Terms' );
		}

		$invert   = Parser::has_option( $tag, 'invert' );
		$optional = Parser::has_option( $tag, 'optional' );
		// Without a label of its own the box is named by its text (what the
		// entry and the email show), and the label row is hidden, as in CF7.
		$hide = '' === $label;
		$name = '' !== $label ? $label : ( '' !== $text ? $this->short( $text ) : $this->t( 'trmCheckbox', 'Terms' ) );

		$field             = $this->field( $invert ? 'checkbox' : 'trmCheckbox', $name, $tag );
		$field['required'] = ( $invert || $optional ) ? 0 : 1;
		if ( $hide && '' !== $text ) {
			$field['hflabel'] = 1;
		}

		$defaults = Parser::has_option( $tag, 'default:on' ) ? array( 0 ) : array();
		$this->push_with_options( $field, $tag, array( $option_text ), $defaults, true );

		if ( $invert ) {
			$this->note(
				sprintf(
					/* translators: %s: field label */
					__( '“%s” had to stay unticked in Contact Form 7. In Easy Form Builder it is an ordinary optional checkbox.', 'easy-form-builder' ),
					$this->short( $text ? $text : $name )
				)
			);
		}
	}

	/**
	 * file.
	 *
	 * @param array  $tag   Tag.
	 * @param string $label Label.
	 * @return void
	 */
	private function map_file( array $tag, $label ) {
		if ( '' === $label ) {
			$label = $this->humanize( $tag['name'] );
		}
		$id    = $this->new_id();
		$field = array(
			'id_'                => $id,
			'dataId'             => $id . '-id',
			'type'               => 'file',
			'placeholder'        => 'file',
			'value'              => 'allformat',
			'size'               => 100,
			'message'            => '',
			'id'                 => '',
			'classes'            => $this->classes( $tag ),
			'name'               => $label,
			'required'           => $tag['required'] ? 1 : 0,
			'amount'             => $this->amount++,
			'step'               => 1,
			'label_text_size'    => 'fs-6',
			'message_text_size'  => 'fs-7',
			'el_text_size'       => 'fs-6',
			'file'               => 'allformat',
			'label_text_color'   => 'text-labelEfb',
			'label_position'     => 'up',
			'el_text_color'      => 'text-dark',
			'message_text_color' => 'text-muted',
			'el_height'          => 'h-d-efb',
			'label_align'        => $this->label_align(),
			'message_align'      => 'justify-content-start',
			'el_border_color'    => 'border-d',
			'el_align'           => 'justify-content-start',
			'pro'                => false,
		);

		// Size: Contact Form 7 allows 1 MB unless limit: says otherwise.
		$bytes              = Parser::limit_bytes( $tag );
		$bytes              = false === $bytes ? 1048576 : $bytes;
		$field['max_fsize'] = (string) min( 300, max( 1, (int) ceil( $bytes / 1048576 ) ) );

		// Types: the smallest EFB group that holds every listed extension.
		$types  = Parser::filetypes( $tag );
		$preset = 'allformat';
		$listed = ! empty( $types ) ? $types : self::CF7_DEFAULT_FILETYPES;
		foreach ( self::FILE_PRESETS as $key => $extensions ) {
			if ( ! array_diff( $listed, $extensions ) ) {
				$preset = $key;
				break;
			}
		}
		$field['file']  = $preset;
		$field['value'] = $preset;

		if ( ! empty( $types ) && 'allformat' === $preset ) {
			$this->note(
				sprintf(
					/* translators: 1: field label, 2: file extensions like "jpg, pdf", 3: an upload group such as "All formats" */
					__( '“%1$s” accepted only %2$s. Easy Form Builder offers fixed file groups, so it now accepts: %3$s.', 'easy-form-builder' ),
					$this->short( $label ),
					implode( ', ', $types ),
					$this->t( self::FILE_PRESET_PHRASES[ $preset ], $preset )
				)
			);
		} elseif ( ! empty( $types ) && count( $types ) < count( self::FILE_PRESETS[ $preset ] ) ) {
			$this->note(
				sprintf(
					/* translators: 1: field label, 2: file extensions like "jpg, pdf", 3: an upload group such as "Image" */
					__( '“%1$s” accepted only %2$s. Easy Form Builder offers fixed file groups, so it now accepts: %3$s.', 'easy-form-builder' ),
					$this->short( $label ),
					implode( ', ', $types ),
					$this->t( self::FILE_PRESET_PHRASES[ $preset ], $preset ) . ' (' . implode( ', ', self::FILE_PRESETS[ $preset ] ) . ')'
				)
			);
		}

		$this->push_field( $field, $tag );
	}

	/**
	 * CAPTCHA tags: no field; reCAPTCHA on when EFB has keys, else a hint.
	 *
	 * @return void
	 */
	private function map_captcha() {
		if ( isset( $this->once['captcha'] ) ) {
			return;
		}
		$this->once['captcha'] = true;
		if ( $this->env['captcha_ready'] ) {
			$this->structure[0]['captcha'] = true;
			$this->settings['captcha']     = __( 'Google reCAPTCHA is turned on for this form, in place of the Contact Form 7 CAPTCHA.', 'easy-form-builder' );
			return;
		}
		$this->note( __( 'The CAPTCHA was not carried over as a field. Easy Form Builder has its own Google reCAPTCHA: add your keys in Panel > Settings > Google Keys, then turn on reCAPTCHA in this form’s settings.', 'easy-form-builder' ) );
	}

	/*
	 * ---------------------------------------------------------------------
	 * Choices
	 * ---------------------------------------------------------------------
	 */

	/**
	 * Choices of a select/checkbox/radio, including data: lists.
	 *
	 * @param array  $tag   Tag.
	 * @param string $label Label (for notes).
	 * @return array ['values' => string[], 'defaults' => int[], 'piped' => bool, 'missing' => string[]]
	 */
	private function choices( array $tag, $label ) {
		$values  = $tag['values'];
		$missing = array();
		$data    = Parser::get_options( $tag, 'data' );
		if ( ! empty( $data ) ) {
			$extra = array();
			if ( is_callable( $this->env['data_source'] ) ) {
				$extra = (array) call_user_func( $this->env['data_source'], $data );
			}
			if ( empty( $extra ) ) {
				$missing = $data;
			} elseif ( Parser::has_option( $tag, 'free_text' ) && ! empty( $values ) ) {
				// CF7 keeps the free-text choice last.
				$values = array_merge( array_slice( $values, 0, -1 ), array_values( $extra ), array_slice( $values, -1 ) );
			} else {
				$values = array_merge( $values, array_values( $extra ) );
			}
		}

		$piped = false;
		foreach ( $tag['pipes'] as $pipe ) {
			if ( $pipe[0] !== $pipe[1] ) {
				$piped = true;
			}
		}

		$defaults = Parser::defaults( $tag );
		return array(
			'values'   => $values,
			'defaults' => $defaults['indexes'],
			'dynamic'  => $defaults['dynamic'],
			'piped'    => $piped,
			'missing'  => $missing,
		);
	}

	/**
	 * Notes shared by every choice field.
	 *
	 * @param array  $tag     Tag.
	 * @param string $label   Label.
	 * @param array  $choices choices() result.
	 * @return void
	 */
	private function choice_notes( array $tag, $label, array $choices ) {
		if ( $choices['piped'] ) {
			$this->note(
				sprintf(
					/* translators: %s: field label */
					__( 'The choices of “%s” sent a different value in the email (the text after “|”). Easy Form Builder saves the text the visitor sees.', 'easy-form-builder' ),
					$this->short( $label )
				)
			);
		}
		if ( ! empty( $choices['missing'] ) ) {
			$this->note(
				sprintf(
					/* translators: 1: field label, 2: list name such as "countries" */
					__( 'The choices of “%1$s” came from a list (%2$s) that is not available here. Add the choices in the builder.', 'easy-form-builder' ),
					$this->short( $label ),
					implode( ', ', $choices['missing'] )
				)
			);
		}
		if ( ! empty( $choices['dynamic'] ) ) {
			$this->dynamic_default_note( $tag, $label );
		}
	}

	/**
	 * Push a choice field and its options.
	 *
	 * @param array      $field    Parent field.
	 * @param array      $tag      Tag.
	 * @param string[]   $values   Visible choice texts.
	 * @param int[]      $defaults 0-based indexes of pre-selected choices.
	 * @param bool       $multi    Whether the field holds several choices.
	 * @return void
	 */
	private function push_with_options( array $field, array $tag, array $values, array $defaults, $multi ) {
		$options  = array();
		$selected = array();
		$seen     = array();
		foreach ( array_values( $values ) as $i => $value ) {
			$text = 'trmCheckbox' === $field['type'] ? $value : $this->clean_text( $value );
			// A double quote would end the option's value="" attribute early.
			$text = str_replace( '"', '”', $text );
			if ( '' === $text ) {
				continue;
			}
			$id        = $this->new_id();
			$options[] = array(
				'id_'    => $id,
				'dataId' => $id . '-id',
				'parent' => $field['id_'],
				'type'   => 'option',
				'value'  => $text,
				'id_op'  => $id,
				'step'   => 1,
				'amount' => $field['amount'],
			);
			if ( in_array( $i, $defaults, true ) && ! isset( $seen[ $i ] ) ) {
				$selected[] = $id;
				$seen[ $i ] = true;
			}
		}

		if ( $multi ) {
			$field['value'] = $selected;
		} else {
			$field['value'] = empty( $selected ) ? '' : $selected[0];
		}

		$this->push_field( $field, $tag );
		foreach ( $options as $option ) {
			$this->structure[] = $option;
		}
	}

	/*
	 * ---------------------------------------------------------------------
	 * Form settings: mail, messages, additional settings
	 * ---------------------------------------------------------------------
	 */

	/**
	 * Index 0 of the structure, as creator_form_builder_Efb() writes it for a
	 * new form.
	 *
	 * @param string $name Form name.
	 * @return array
	 */
	private function form_settings( $name ) {
		return array(
			'type'                 => 'form',
			'steps'                => 1,
			'formName'             => $name,
			'email'                => '',
			'trackingCode'         => true,
			'EfbVersion'           => 2,
			'button_single_text'   => $this->t( 'submit', 'Submit' ),
			'button_color'         => 'btn-primary',
			'icon'                 => 'bi-ui-checks-grid',
			'button_Next_text'     => $this->t( 'next', 'Next' ),
			'button_Previous_text' => $this->t( 'previous', 'Previous' ),
			'button_Next_icon'     => 'bi-chevron-right',
			'button_Previous_icon' => 'bi-chevron-left',
			'button_state'         => 'single',
			'label_text_color'     => 'text-labelEfb',
			'el_text_color'        => 'text-white',
			'message_text_color'   => 'text-muted',
			'icon_color'           => 'text-white',
			'el_height'            => 'h-d-efb',
			'email_to'             => false,
			'show_icon'            => true,
			'show_pro_bar'         => true,
			'captcha'              => false,
			'private'              => false,
			'sendEmail'            => false,
			'font'                 => true,
			'stateForm'            => 0,
			'dShowBg'              => true,
			'btns_align'           => 'justify-content-center',
			'thank_you'            => 'msg',
			'thank_you_message'    => array(
				'icon'                       => 'bi-hand-thumbs-up',
				'thankYou'                   => $this->t( 'thanksFillingOutform', 'Thanks for filling out the form.' ),
				'done'                       => $this->t( 'yad', "You're all done" ),
				'trackingCode'               => $this->t( 'trackingCode', 'Confirmation Code' ),
				'error'                      => $this->t( 'error', 'Error' ),
				'pleaseFillInRequiredFields' => $this->t( 'pleaseFillInRequiredFields', 'Please fill in all required fields.' ),
			),
			'email_temp'           => '',
		);
	}

	/**
	 * The single step every imported form has (Contact Form 7 has no steps).
	 *
	 * @param string $name Form name.
	 * @return array
	 */
	private function step( $name ) {
		return array(
			'id_'                => '1',
			'type'               => 'step',
			'dataId'             => '1',
			'classes'            => 'stepNavEfb',
			'id'                 => '1',
			'name'               => $name,
			'icon'               => 'bi-ui-checks-grid',
			'step'               => 1,
			'amount'             => $this->amount++,
			'EfbVersion'         => 2,
			'message'            => '',
			'label_text_size'    => 'fs-5',
			'el_text_size'       => 'fs-5',
			'label_text_color'   => 'text-darkb',
			'el_text_color'      => 'text-dark',
			'message_text_color' => 'text-muted',
			'icon_color'         => 'text-pinkEfb',
			'visible'            => 1,
		);
	}

	/**
	 * `_mail` and `_mail_2`.
	 *
	 * @param array $mail      Mail (1).
	 * @param array $mail_2    Mail (2).
	 * @param bool  $skip_mail skip_mail: on in the additional settings.
	 * @return void
	 */
	private function map_mail( array $mail, array $mail_2, $skip_mail = false ) {
		// skip_mail: Contact Form 7 sent nothing for this form, so neither does EFB.
		if ( $skip_mail ) {
			return;
		}
		// A form saved without Mail (1) settings has nothing to carry over.
		$mail_active   = ! empty( $mail ) && ( ! isset( $mail['active'] ) || $this->truthy( $mail['active'] ) );
		$mail_2_active = ! empty( $mail_2 ) && isset( $mail_2['active'] ) && $this->truthy( $mail_2['active'] );

		// Who gets the notification.
		$recipients = array();
		$unresolved = array();
		if ( $mail_active ) {
			$raw = isset( $mail['recipient'] ) ? (string) $mail['recipient'] : '';
			foreach ( preg_split( '/[,;]/', $raw ) as $part ) {
				$part = trim( $part );
				if ( '' === $part ) {
					continue;
				}
				if ( preg_match( '/\[\s*_site_admin_email\s*\]/', $part ) ) {
					$recipients[] = $this->env['admin_email'];
					continue;
				}
				if ( false !== strpos( $part, '[' ) ) {
					$unresolved[] = $part;
					continue;
				}
				if ( preg_match( '/<([^>]+)>/', $part, $m ) ) {
					$part = trim( $m[1] );
				}
				if ( is_email( $part ) ) {
					$recipients[] = sanitize_email( $part );
				} else {
					$unresolved[] = $part;
				}
			}
			$recipients = array_values( array_unique( array_filter( $recipients ) ) );

			if ( empty( $recipients ) ) {
				$fallback = '' !== $this->env['efb_admin_email'] ? $this->env['efb_admin_email'] : $this->env['admin_email'];
				if ( is_email( $fallback ) ) {
					$recipients[] = $fallback;
				}
			}
			if ( ! empty( $unresolved ) ) {
				$this->note(
					sprintf(
						/* translators: 1: an address setting such as "[your-email]", 2: email address(es) */
						__( 'The notification address “%1$s” depends on the visitor or the page, which Easy Form Builder does not support. Notifications go to %2$s.', 'easy-form-builder' ),
						implode( ', ', $unresolved ),
						empty( $recipients ) ? '-' : implode( ', ', $recipients )
					)
				);
			}

			$this->structure[0]['email'] = implode( ',', $recipients );
			if ( ! empty( $recipients ) ) {
				$this->structure[0]['sendEmail'] = true;
				$this->settings['recipients']    = sprintf(
					/* translators: %s: email address(es) */
					__( 'Notifications go to %s.', 'easy-form-builder' ),
					implode( ', ', $recipients )
				);
			}

			$subject = isset( $mail['subject'] ) ? (string) $mail['subject'] : '';
			if ( $mail_2_active && '' !== trim( $subject ) ) {
				// EFB's subject is used for the owner's and the visitor's email
				// alike; the owner's subject would reach the visitor.
				$this->note( __( 'The email subject was not copied: Easy Form Builder uses one subject for your notification and for the visitor’s confirmation, so its standard subjects are kept.', 'easy-form-builder' ) );
			} else {
				$this->map_subject( $subject );
			}
			$this->map_headers( $mail );
		}

		// The visitor's confirmation.
		if ( $mail_2_active ) {
			$target = null;
			$to     = isset( $mail_2['recipient'] ) ? (string) $mail_2['recipient'] : '';
			if ( preg_match_all( '/\[\s*([A-Za-z][-A-Za-z0-9_:.]*)\s*\]/', $to, $tags ) ) {
				foreach ( $tags[1] as $tag_name ) {
					$tag_name = strtr( $tag_name, '.', '_' );
					if ( isset( $this->by_name[ $tag_name ] ) && 'email' === $this->by_name[ $tag_name ]['type'] ) {
						$target = $this->by_name[ $tag_name ];
						break;
					}
				}
			}
			if ( null === $target ) {
				foreach ( $this->by_name as $entry ) {
					if ( 'email' === $entry['type'] ) {
						$target = $entry;
						break;
					}
				}
			}
			if ( null === $target ) {
				$this->note( __( 'The confirmation email to the visitor could not be turned on because the form has no email field.', 'easy-form-builder' ) );
			} else {
				foreach ( $this->structure as $i => $row ) {
					if ( isset( $row['id_'] ) && $row['id_'] === $target['id'] ) {
						$this->structure[ $i ]['noti'] = 1;
					}
				}
				$this->structure[0]['email_to']  = $target['id'];
				$this->structure[0]['sendEmail'] = true;
				$this->settings['confirmation']  = sprintf(
					/* translators: %s: label of the email field */
					__( 'The visitor gets a confirmation email at the address typed in “%s”.', 'easy-form-builder' ),
					$this->short( $target['label'] )
				);
			}
		}

		if ( $mail_active || $mail_2_active ) {
			$this->note( __( 'Email text: Easy Form Builder sends its own emails, listing every answer, so the message text written in Contact Form 7 was not copied.', 'easy-form-builder' ) );
			if ( ( $mail_active && ! empty( $mail['attachments'] ) ) || ( $mail_2_active && ! empty( $mail_2['attachments'] ) ) ) {
				$this->note( __( 'Uploaded files are not attached to the email. Easy Form Builder keeps them with each entry.', 'easy-form-builder' ) );
			}
			if ( ! $this->env['sending_enabled'] ) {
				$this->note( $this->t( 'emailSendingOffDesc', 'Easy Form Builder will not send any email — neither to you nor to the person who submits this form — until "This site can send emails" is enabled in Email Settings.' ) );
			}
		}
	}

	/**
	 * Email subject. Site tags become EFB's own, answer tags cannot be kept.
	 *
	 * @param string $subject `_mail` subject.
	 * @return void
	 */
	private function map_subject( $subject ) {
		$subject = trim( $subject );
		if ( '' === $subject ) {
			return;
		}
		$converted = strtr(
			$subject,
			array(
				'[_site_title]' => '[website_name]',
				'[_site_url]'   => '[link_domain]',
			)
		);
		$had_answers = (bool) preg_match( '/\[(?!website_name\]|link_domain\])[^\]]*\]/', $converted );
		$converted   = preg_replace( '/\[(?!website_name\]|link_domain\])[^\]]*\]/', '', $converted );
		// Quotes and brackets left empty by a removed tag.
		$converted = preg_replace( '/(["\'“”«»„‚‘’()])\s*(["\'“”«»„‚‘’()])/u', '', $converted );
		$converted = trim( preg_replace( '/\s+/u', ' ', $converted ), " \t-–—:|,;" );
		$converted = sanitize_text_field( $converted );

		$literal = trim( str_replace( array( '[website_name]', '[link_domain]' ), '', $converted ) );
		if ( '' !== $literal && preg_match( '/[\p{L}\p{N}]/u', $literal ) ) {
			$this->structure[0]['email_sub'] = $converted;
			$this->settings['subject']       = sprintf(
				/* translators: %s: email subject */
				__( 'Email subject: %s', 'easy-form-builder' ),
				$converted
			);
			if ( $had_answers ) {
				$this->note( __( 'Parts of the email subject that showed the visitor’s answers were removed: Easy Form Builder cannot put answers in the subject.', 'easy-form-builder' ) );
			}
		} elseif ( $had_answers ) {
			$this->note( __( 'The email subject showed the visitor’s answers, which Easy Form Builder cannot put in the subject, so its standard subject is used.', 'easy-form-builder' ) );
		}
	}

	/**
	 * Additional headers and the sender.
	 *
	 * @param array $mail `_mail`.
	 * @return void
	 */
	private function map_headers( array $mail ) {
		$headers = isset( $mail['additional_headers'] ) ? (string) $mail['additional_headers'] : '';
		$copies  = array();
		foreach ( preg_split( '/\r\n|\r|\n/', $headers ) as $line ) {
			if ( preg_match( '/^\s*(cc|bcc)\s*:(.*)$/i', $line, $m ) ) {
				foreach ( preg_split( '/[,;]/', $m[2] ) as $address ) {
					$address = trim( $address );
					if ( '' !== $address ) {
						$copies[] = $address;
					}
				}
			}
			// Reply-To: [your-email] needs nothing - EFB already sets Reply-To
			// to the visitor's address on the owner's copy.
		}
		if ( ! empty( $copies ) ) {
			$this->note(
				sprintf(
					/* translators: %s: email addresses */
					__( 'Copies (Cc/Bcc) were not carried over: %s. Add them to the notification addresses in the form settings if they should get every entry.', 'easy-form-builder' ),
					implode( ', ', $copies )
				)
			);
		}

		$sender = isset( $mail['sender'] ) ? (string) $mail['sender'] : '';
		$plain  = array( strtolower( $this->env['admin_email'] ), strtolower( $this->env['efb_from'] ) );
		if ( preg_match( '/[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+/', $sender, $m )
			&& 0 !== stripos( $m[0], 'wordpress@' )
			&& ! in_array( strtolower( $m[0] ), $plain, true ) ) {
			$this->note(
				sprintf(
					/* translators: %s: email address */
					__( 'The sender address %s was not carried over: Easy Form Builder sends from one address for all forms, set in its email settings.', 'easy-form-builder' ),
					$m[0]
				)
			);
		}
	}

	/**
	 * `_messages`: the success message.
	 *
	 * @param array $messages `_messages`.
	 * @return void
	 */
	private function map_messages( array $messages ) {
		if ( empty( $messages['mail_sent_ok'] ) ) {
			return;
		}
		$ok = $this->clean_text( (string) $messages['mail_sent_ok'] );
		if ( '' === $ok ) {
			return;
		}
		$this->structure[0]['thank_you_message']['thankYou'] = $ok;
		$this->settings['message'] = sprintf(
			/* translators: %s: the message shown after sending */
			__( 'Message after sending: “%s”', 'easy-form-builder' ),
			$ok
		);
	}

	/**
	 * Read `_additional_settings` ("key: value" per line).
	 *
	 * @param string $text Additional settings.
	 * @return array<string,bool> The flags the importer acts on.
	 */
	private function additional_settings( $text ) {
		$flags = array(
			'subscribers_only' => false,
			'skip_mail'        => false,
			'demo_mode'        => false,
			'js'               => false,
		);
		foreach ( preg_split( '/\r\n|\r|\n/', (string) $text ) as $line ) {
			if ( ! preg_match( '/^\s*([a-zA-Z0-9_]+)\s*:(.*)$/', $line, $m ) ) {
				continue;
			}
			$key   = strtolower( $m[1] );
			$value = strtolower( trim( $m[2], " \t\"'" ) );
			$on    = in_array( $value, array( 'on', 'true', '1', 'yes' ), true );
			if ( isset( $flags[ $key ] ) && 'js' !== $key ) {
				$flags[ $key ] = $flags[ $key ] || $on;
			} elseif ( in_array( $key, array( 'on_sent_ok', 'on_submit' ), true ) ) {
				$flags['js'] = true;
			}
		}
		return $flags;
	}

	/**
	 * Act on the additional settings.
	 *
	 * @param array $flags additional_settings().
	 * @return void
	 */
	private function map_additional_settings( array $flags ) {
		if ( $flags['subscribers_only'] ) {
			$this->structure[0]['stateForm'] = true;
			$this->settings['private']       = __( 'Only logged-in visitors can use this form, as in Contact Form 7.', 'easy-form-builder' );
		}
		if ( $flags['skip_mail'] ) {
			$this->note( __( 'Contact Form 7 did not send the notification email for this form (skip_mail), so it is turned off here too.', 'easy-form-builder' ) );
		}
		if ( $flags['demo_mode'] ) {
			$this->note( __( 'Demo mode was on in Contact Form 7. The new form saves entries and sends emails normally.', 'easy-form-builder' ) );
		}
		if ( $flags['js'] ) {
			$this->note( __( 'Custom JavaScript from the “Additional Settings” of Contact Form 7 was not carried over.', 'easy-form-builder' ) );
		}
	}

	/*
	 * ---------------------------------------------------------------------
	 * Builders
	 * ---------------------------------------------------------------------
	 */

	/**
	 * A field object with the keys sampleElpush_efb() gives a dropped field.
	 *
	 * @param string     $type  EFB type.
	 * @param string     $label Visible label.
	 * @param array|null $tag   Source tag.
	 * @return array
	 */
	private function field( $type, $label, $tag ) {
		$id = $this->new_id();
		return array(
			'id_'                => $id,
			'dataId'             => $id . '-id',
			'type'               => $type,
			'elementId'          => $type,
			'placeholder'        => '',
			'value'              => '',
			'size'               => 100,
			'message'            => '',
			'id'                 => '',
			'classes'            => is_array( $tag ) ? $this->classes( $tag ) : '',
			'name'               => $label,
			'required'           => ( is_array( $tag ) && $tag['required'] ) ? 1 : 0,
			'amount'             => $this->amount++,
			'step'               => 1,
			'label_text_size'    => 'fs-6',
			'label_position'     => 'up',
			'el_text_size'       => 'fs-6',
			'label_text_color'   => 'text-labelEfb',
			'el_border_color'    => 'border-d',
			'el_text_color'      => 'text-labelEfb',
			'message_text_color' => 'text-muted',
			'el_height'          => 'h-d-efb',
			'label_align'        => $this->label_align(),
			'message_align'      => 'justify-content-start',
			'el_align'           => 'justify-content-start',
			'pro'                => false,
			'icon_input'         => '',
		);
	}

	/**
	 * A heading field (the builder's "heading" element).
	 *
	 * @param string $text  Text.
	 * @param string $size  el_text_size.
	 * @param string $color el_text_color.
	 * @return void
	 */
	private function add_heading( $text, $size, $color ) {
		$id                = $this->new_id();
		$this->structure[] = array(
			'id_'           => $id,
			'dataId'        => $id . '-id',
			'type'          => 'heading',
			'classes'       => '',
			'value'         => $text,
			'amount'        => $this->amount++,
			'step'          => 1,
			'el_text_size'  => $size,
			'el_text_color' => $color,
			'el_align'      => 'justify-content-start',
			'pro'           => false,
		);
		$this->fields[]    = array(
			'label'     => $this->short( $text ),
			'type'      => 'heading',
			'typeLabel' => $this->type_label( 'heading' ),
			'required'  => false,
		);
	}

	/**
	 * Add a field to the structure and the report.
	 *
	 * @param array      $field Field.
	 * @param array|null $tag   Source tag (null for helper fields).
	 * @return void
	 */
	/**
	 * F03: the counter of an imported text or textarea field ([count name], [count name down]).
	 *
	 * @param string $id  The field's id_.
	 * @param array  $tag The count tag.
	 * @return bool Whether the field took the counter.
	 */
	private function set_counter( $id, array $tag ) {
		foreach ( $this->structure as $i => $row ) {
			if ( ! is_array( $row ) || ! isset( $row['id_'] ) || $row['id_'] !== $id ) {
				continue;
			}
			if ( ! in_array( $row['type'], array( 'text', 'textarea' ), true ) ) {
				return false;
			}
			$this->structure[ $i ]['counter']       = 'chars';
			$down = Parser::has_option( $tag, 'down' ) || ( ! Parser::has_option( $tag, 'up' ) && isset( $row['mlen'] ) && (int) $row['mlen'] > 0 );
			$this->structure[ $i ]['counter_style'] = $down ? 'remaining' : 'used';
			return true;
		}
		return false;
	}

	private function push_field( array $field, $tag ) {
		$this->structure[] = $field;
		if ( is_array( $tag ) && '' !== $tag['name'] && ! isset( $this->by_name[ $tag['name'] ] ) ) {
			$this->by_name[ $tag['name'] ] = array(
				'id'    => $field['id_'],
				'type'  => $field['type'],
				'label' => $field['name'],
			);
		}
		$this->fields[] = array(
			'label'     => $field['name'],
			'type'      => $field['type'],
			'typeLabel' => $this->type_label( $field['type'] ),
			'required'  => (bool) $field['required'],
		);
	}

	/**
	 * A new field id: 9 lower-case letters and digits, starting with a
	 * letter, unique in the form (the builder uses the same alphabet).
	 *
	 * @return string
	 */
	private function new_id() {
		if ( is_callable( $this->env['id_factory'] ) ) {
			$id = (string) call_user_func( $this->env['id_factory'] );
			$this->ids[ $id ] = true;
			return $id;
		}
		$alphabet = 'abcdefghijklmnopqrstuvwxyz0123456789';
		do {
			$id = $alphabet[ random_int( 0, 25 ) ];
			for ( $i = 0; $i < 8; $i++ ) {
				$id .= $alphabet[ random_int( 0, 35 ) ];
			}
		} while ( isset( $this->ids[ $id ] ) );
		$this->ids[ $id ] = true;
		return $id;
	}

	/**
	 * class: options, in the builder's comma-separated form.
	 *
	 * @param array $tag Tag.
	 * @return string
	 */
	private function classes( array $tag ) {
		$classes = array();
		foreach ( Parser::get_options( $tag, 'class', '[-0-9a-zA-Z_]+' ) as $class ) {
			$classes[] = $class;
		}
		return implode( ',', $classes );
	}

	/** @return string */
	private function label_align() {
		return $this->env['rtl'] ? 'txt-right' : 'txt-left';
	}

	/*
	 * ---------------------------------------------------------------------
	 * Notes and text helpers
	 * ---------------------------------------------------------------------
	 */

	/**
	 * @param array $tag Tag.
	 * @return bool Whether the tag shows its first value as a placeholder.
	 */
	private function has_placeholder( array $tag ) {
		return ( Parser::has_option( $tag, 'placeholder' ) || Parser::has_option( $tag, 'watermark' ) )
			&& ( ! empty( $tag['values'] ) || '' !== $tag['content'] );
	}

	/**
	 * @param array  $tag   Tag.
	 * @param string $label Field label.
	 * @return void
	 */
	private function dynamic_default_note( array $tag, $label ) {
		$defaults = Parser::defaults( $tag );
		if ( empty( $defaults['dynamic'] ) ) {
			return;
		}
		$this->note(
			sprintf(
				/* translators: %s: field label */
				__( '“%s” filled itself in (from the logged-in user, the page address or the page). That is not carried over.', 'easy-form-builder' ),
				$this->short( $label )
			)
		);
	}

	/**
	 * @param array  $tag   Tag.
	 * @param string $label Field label.
	 * @return void
	 */
	private function readonly_note( array $tag, $label ) {
		if ( ! Parser::has_option( $tag, 'readonly' ) ) {
			return;
		}
		$this->note(
			sprintf(
				/* translators: %s: field label */
				__( '“%s” was read-only. It is now an ordinary field.', 'easy-form-builder' ),
				$this->short( $label )
			)
		);
	}

	/**
	 * @param string $note Plain-words note.
	 * @return void
	 */
	private function note( $note ) {
		$this->notes[] = $note;
	}

	/**
	 * @param string $key  Once-per-form key.
	 * @param string $note Note.
	 * @return void
	 */
	private function note_once( $key, $note ) {
		if ( isset( $this->once[ $key ] ) ) {
			return;
		}
		$this->once[ $key ] = true;
		$this->note( $note );
	}

	/**
	 * An EFB phrase, or the English fallback.
	 *
	 * @param string $key      Phrase key.
	 * @param string $fallback English.
	 * @return string
	 */
	private function t( $key, $fallback ) {
		$text = isset( $this->texts[ $key ] ) && '' !== $this->texts[ $key ] ? (string) $this->texts[ $key ] : $fallback;
		// EFB phrases are stored escaped for HTML; the report is plain text.
		return html_entity_decode( $text, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
	}

	/**
	 * @param string $type EFB type.
	 * @return string The builder's name for it.
	 */
	private function type_label( $type ) {
		$key = isset( self::TYPE_PHRASES[ $type ] ) ? self::TYPE_PHRASES[ $type ] : $type;
		return $this->t( $key, ucfirst( $type ) );
	}

	/**
	 * Visible text: tags and Contact Form 7 mail-tags removed, entities
	 * decoded, whitespace collapsed.
	 *
	 * @param string $text Text or markup.
	 * @return string
	 */
	public function clean_text( $text ) {
		$text = html_entity_decode( (string) $text, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		$text = wp_strip_all_tags( $text );
		// [your-name], [/group]; the space before goes too: "Thanks [your-name]!" → "Thanks!".
		$text = preg_replace( '/\s*\[\/?[A-Za-z_][^\[\]]*\]/u', '', $text );
		$text = preg_replace( '/[\s\x{00A0}\x{200B}]+/u', ' ', $text );
		return trim( (string) $text );
	}

	/**
	 * Like clean_text(), keeping line breaks (textarea defaults).
	 *
	 * @param string $text Text.
	 * @return string
	 */
	private function clean_multiline( $text ) {
		$text  = str_replace( '<WPPreserveNewline />', "\n", (string) $text );
		$lines = preg_split( '/\r\n|\r|\n/', $text );
		return trim( implode( "\n", array_map( array( $this, 'clean_text' ), $lines ) ) );
	}

	/**
	 * A label: clean_text() plus the "required" markers EFB draws itself.
	 *
	 * @param string $text Text.
	 * @return string
	 */
	public function clean_label( $text ) {
		$text = $this->clean_text( $text );
		$text = preg_replace( '/\s*\((?:required|Required|REQUIRED)\)\s*/u', ' ', $text );
		$text = trim( (string) $text );
		$text = preg_replace( '/[\s:：*＊]+$/u', '', $text );
		$text = preg_replace( '/^[\s*＊]+/u', '', $text );
		return trim( (string) $text );
	}

	/**
	 * Option text of a terms box: links become the builder's [text](url).
	 *
	 * @param string $html Acceptance content.
	 * @return string
	 */
	private function terms_text( $html ) {
		$html = (string) $html;
		$html = preg_replace_callback(
			'#<a\s[^>]*href\s*=\s*(["\'])(.*?)\1[^>]*>(.*?)</a>#is',
			function ( $m ) {
				$url  = esc_url_raw( html_entity_decode( $m[2], ENT_QUOTES, 'UTF-8' ) );
				$text = trim( wp_strip_all_tags( html_entity_decode( $m[3], ENT_QUOTES, 'UTF-8' ) ) );
				$text = str_replace( array( '[', ']' ), array( '(', ')' ), $text );
				if ( '' === $url || '' === $text ) {
					return $text;
				}
				return '[' . $text . '](' . str_replace( array( '(', ')', ' ' ), array( '%28', '%29', '%20' ), $url ) . ')';
			},
			$html
		);
		// Keep [text](url) while removing mail-tags and markup.
		$text = html_entity_decode( (string) $html, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		$text = wp_strip_all_tags( $text );
		$text = preg_replace( '/\[\/?[A-Za-z_][^\[\]]*\](?!\()/u', '', $text );
		$text = preg_replace( '/[\s\x{00A0}\x{200B}]+/u', ' ', $text );
		return trim( (string) $text );
	}

	/**
	 * "your-name" → "Your name", "menu-123" → "Menu".
	 *
	 * @param string $name Field name.
	 * @return string
	 */
	public function humanize( $name ) {
		$words = preg_split( '/[-_:.\s]+/', (string) $name, -1, PREG_SPLIT_NO_EMPTY );
		$words = array_values(
			array_filter(
				$words,
				function ( $w ) {
					return ! preg_match( '/^[0-9]+$/', $w );
				}
			)
		);
		if ( count( $words ) > 1 && 'your' === strtolower( $words[0] ) ) {
			array_shift( $words );
		}
		if ( empty( $words ) ) {
			return (string) $name;
		}
		$text = strtolower( implode( ' ', $words ) );
		return ucfirst( $text );
	}

	/**
	 * @param array[] $pick  Stream indexes.
	 * @param array[] $items Stream.
	 * @return string Joined, cleaned label text.
	 */
	private function join_items( array $pick, array $items ) {
		$text = '';
		$line = null;
		foreach ( $pick as $ti ) {
			// Pieces of one line were split only by inline markup (<a>, <span>):
			// they join as written. A new line is a new word.
			$text .= ( null !== $line && $items[ $ti ]['line'] !== $line ? ' ' : '' ) . $items[ $ti ]['v'];
			$line  = $items[ $ti ]['line'];
		}
		return $this->clean_label( $text );
	}

	/**
	 * @param int[]   $pick  Stream indexes.
	 * @param array[] $items Stream.
	 * @return void
	 */
	private function mark_used( array $pick, array &$items ) {
		foreach ( $pick as $ti ) {
			$items[ $ti ]['used'] = true;
		}
	}

	/**
	 * @param string $text Text.
	 * @return string At most 80 characters.
	 */
	private function short( $text ) {
		$text = (string) $text;
		if ( function_exists( 'mb_strlen' ) && mb_strlen( $text ) > 80 ) {
			return rtrim( mb_substr( $text, 0, 79 ) ) . '…';
		}
		return $text;
	}

	/**
	 * @param mixed $v A stored flag.
	 * @return bool
	 */
	private function truthy( $v ) {
		return true === $v || 1 === $v || '1' === $v || 'true' === $v || 'on' === $v;
	}
}
