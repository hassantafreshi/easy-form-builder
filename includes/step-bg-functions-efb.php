<?php
/**
 * F19: per-step background (solid colour or linear/radial gradient).
 *
 * The data is a set of flat scalar keys on the step rows of form_structer (and on row 0 as the
 * form default), never a CSS string. Everything here is the PHP twin of the core of
 * includes/admin/assets/js/step-bg-efb.js - change one, change the other; the vectors in
 * tests/fixtures/step-bg-vectors.json hold both to the same output byte for byte.
 *
 * Keys (docs/feature-gaps/F19-step-backgrounds.fa.md §5):
 *   step_bg_type     '' (follow the form default) | none | solid | linear | radial
 *   step_bg_color    #rrggbb
 *   step_bg_stops    "#rrggbb P,#rrggbb P" (2-5 stops, P = 0..100, sorted)
 *   step_bg_angle    0..360            step_bg_pos   center|top|bottom|left|right
 *   step_bg_opacity  0..100            step_bg_text  '' | auto | #rrggbb
 *   mobile_step_bg   '' | none | solid
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'efb_step_bg_keys_efb' ) ) {
	/** Every key this feature stores; sanitize_obj_msg_efb() routes these to efb_step_bg_sanitize_key_efb(). */
	function efb_step_bg_keys_efb() {
		return array( 'step_bg_type', 'step_bg_color', 'step_bg_stops', 'step_bg_angle', 'step_bg_pos', 'step_bg_opacity', 'step_bg_text', 'mobile_step_bg' );
	}

	/** '#rgb' / '#rrggbb' (any case) to '#rrggbb'; anything else ''. */
	function efb_step_bg_hex_efb( $v ) {
		if ( ! is_string( $v ) ) return '';
		$v = trim( $v );
		if ( ! preg_match( '/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', $v, $m ) ) return '';
		$h = strtolower( $m[1] );
		if ( strlen( $h ) === 3 ) $h = $h[0] . $h[0] . $h[1] . $h[1] . $h[2] . $h[2];
		return '#' . $h;
	}

	/** A whole number in [min,max] as a string, from an int, a float or a plain numeric string; else ''. */
	function efb_step_bg_int_efb( $v, $min, $max ) {
		if ( is_bool( $v ) ) return '';
		if ( is_int( $v ) || is_float( $v ) ) {
			if ( ! is_finite( (float) $v ) ) return '';
			$n = (float) $v;
		} elseif ( is_string( $v ) && preg_match( '/^-?\d{1,6}(\.\d{1,6})?$/', trim( $v ) ) ) {
			$n = (float) trim( $v );
		} else {
			return '';
		}
		$n = (int) floor( $n + 0.5 );
		if ( $n < $min ) $n = $min;
		if ( $n > $max ) $n = $max;
		return (string) $n;
	}

	/** step_bg_stops to a list of [hex, pos]; fewer than two valid stops gives an empty list. */
	function efb_step_bg_parse_stops_efb( $v ) {
		if ( ! is_string( $v ) || strlen( $v ) > 200 ) return array();
		$out = array();
		foreach ( explode( ',', $v ) as $part ) {
			if ( count( $out ) >= 5 ) break;
			if ( ! preg_match( '/^(#[0-9a-fA-F]{3}|#[0-9a-fA-F]{6})\s+(\d{1,3})$/', trim( $part ), $m ) ) continue;
			$pos = (int) $m[2];
			if ( $pos > 100 ) $pos = 100;
			$out[] = array( efb_step_bg_hex_efb( $m[1] ), $pos );
		}
		if ( count( $out ) < 2 ) return array();
		// Stable sort by position (usort is not stable before PHP 8).
		$idx = array_keys( $out );
		usort( $idx, function ( $a, $b ) use ( $out ) {
			return $out[ $a ][1] === $out[ $b ][1] ? $a - $b : $out[ $a ][1] - $out[ $b ][1];
		} );
		$sorted = array();
		foreach ( $idx as $i ) $sorted[] = $out[ $i ];
		return $sorted;
	}

	function efb_step_bg_stops_string_efb( $stops ) {
		$p = array();
		foreach ( $stops as $s ) $p[] = $s[0] . ' ' . $s[1];
		return implode( ',', $p );
	}

	/** The save-side sanitizer of one key. Unknown or invalid values become '' (not set). */
	function efb_step_bg_sanitize_key_efb( $k, $v ) {
		switch ( $k ) {
			case 'step_bg_type':
				return is_string( $v ) && in_array( $v, array( 'none', 'solid', 'linear', 'radial' ), true ) ? $v : '';
			case 'step_bg_color':
				return efb_step_bg_hex_efb( $v );
			case 'step_bg_stops':
				return efb_step_bg_stops_string_efb( efb_step_bg_parse_stops_efb( $v ) );
			case 'step_bg_angle':
				return efb_step_bg_int_efb( $v, 0, 360 );
			case 'step_bg_opacity':
				return efb_step_bg_int_efb( $v, 0, 100 );
			case 'step_bg_pos':
				return is_string( $v ) && in_array( $v, array( 'center', 'top', 'bottom', 'left', 'right' ), true ) ? $v : '';
			case 'step_bg_text':
				if ( $v === 'auto' ) return 'auto';
				return efb_step_bg_hex_efb( $v );
			case 'mobile_step_bg':
				return is_string( $v ) && in_array( $v, array( 'none', 'solid' ), true ) ? $v : '';
		}
		return '';
	}

	/** Reads a key from a row that is a stdClass (render path) or an array (save path). */
	function efb_step_bg_get_efb( $row, $k ) {
		if ( is_array( $row ) ) return array_key_exists( $k, $row ) ? $row[ $k ] : null;
		if ( is_object( $row ) ) return property_exists( $row, $k ) ? $row->$k : null;
		return null;
	}

	/**
	 * The background a step paints, or null. A step with its own type (none/solid/linear/radial)
	 * uses its own keys; otherwise it follows row 0 when row 0 has solid/linear/radial.
	 * opts: gradient (plan allows gradients), mobile (plan allows the mobile override).
	 */
	function efb_step_bg_resolve_efb( $row, $form0, $opts = array() ) {
		$gradient = ! empty( $opts['gradient'] );
		$mobile_ok = ! empty( $opts['mobile'] );
		$own = efb_step_bg_sanitize_key_efb( 'step_bg_type', efb_step_bg_get_efb( $row, 'step_bg_type' ) );
		$src = null;
		if ( $own !== '' ) {
			$src = $row;
		} else {
			$def = efb_step_bg_sanitize_key_efb( 'step_bg_type', efb_step_bg_get_efb( $form0, 'step_bg_type' ) );
			if ( $def !== '' && $def !== 'none' ) $src = $form0;
		}
		if ( $src === null ) return null;
		$type = efb_step_bg_sanitize_key_efb( 'step_bg_type', efb_step_bg_get_efb( $src, 'step_bg_type' ) );
		if ( $type === 'none' ) return null;
		$op = efb_step_bg_sanitize_key_efb( 'step_bg_opacity', efb_step_bg_get_efb( $src, 'step_bg_opacity' ) );
		$op = $op === '' ? 100 : (int) $op;
		if ( $type === 'solid' ) {
			$c = efb_step_bg_hex_efb( efb_step_bg_get_efb( $src, 'step_bg_color' ) );
			if ( $c === '' ) return null;
			$stops = array( array( $c, 0 ) );
			$kind = 'solid';
		} else {
			$stops = efb_step_bg_parse_stops_efb( efb_step_bg_get_efb( $src, 'step_bg_stops' ) );
			if ( empty( $stops ) ) return null;
			$kind = $gradient ? $type : 'solid';
			if ( $kind === 'solid' ) $stops = array( array( $stops[0][0], 0 ) );
		}
		$angle = efb_step_bg_sanitize_key_efb( 'step_bg_angle', efb_step_bg_get_efb( $src, 'step_bg_angle' ) );
		$pos = efb_step_bg_sanitize_key_efb( 'step_bg_pos', efb_step_bg_get_efb( $src, 'step_bg_pos' ) );
		$mob = $mobile_ok ? efb_step_bg_sanitize_key_efb( 'mobile_step_bg', efb_step_bg_get_efb( $src, 'mobile_step_bg' ) ) : '';
		if ( $mob === 'solid' && $kind === 'solid' ) $mob = '';
		return array(
			'kind'    => $kind,
			'stops'   => $stops,
			'angle'   => $angle === '' ? 135 : (int) $angle,
			'pos'     => $pos === '' ? 'center' : $pos,
			'opacity' => $op,
			'text'    => efb_step_bg_sanitize_key_efb( 'step_bg_text', efb_step_bg_get_efb( $src, 'step_bg_text' ) ),
			'mobile'  => $mob,
		);
	}

	/** Alpha with up to two decimals and no trailing zeros: 0.8, 0.05, 0, 1. */
	function efb_step_bg_alpha_efb( $op ) {
		$s = rtrim( rtrim( sprintf( '%.2f', $op / 100 ), '0' ), '.' );
		return $s === '' ? '0' : $s;
	}

	function efb_step_bg_color_css_efb( $hex, $op ) {
		if ( $op >= 100 ) return $hex;
		return 'rgba(' . hexdec( substr( $hex, 1, 2 ) ) . ',' . hexdec( substr( $hex, 3, 2 ) ) . ',' . hexdec( substr( $hex, 5, 2 ) ) . ',' . efb_step_bg_alpha_efb( $op ) . ')';
	}

	/** The background-image value of a resolved gradient ('' for solid). */
	function efb_step_bg_gradient_css_efb( $r ) {
		if ( $r['kind'] === 'solid' ) return '';
		$p = array();
		foreach ( $r['stops'] as $s ) $p[] = efb_step_bg_color_css_efb( $s[0], $r['opacity'] ) . ' ' . $s[1] . '%';
		if ( $r['kind'] === 'radial' ) return 'radial-gradient(ellipse at ' . $r['pos'] . ',' . implode( ',', $p ) . ')';
		return 'linear-gradient(' . $r['angle'] . 'deg,' . implode( ',', $p ) . ')';
	}

	/* ---- contrast (WCAG 2.x relative luminance) ---- */

	/** [r,g,b] floats 0..255 of a hex composited over white with the given opacity (0..100). */
	function efb_step_bg_rgb_efb( $hex, $op = 100 ) {
		$a = $op / 100;
		$out = array();
		foreach ( array( 1, 3, 5 ) as $i ) {
			$c = hexdec( substr( $hex, $i, 2 ) );
			$out[] = $a * $c + ( 1 - $a ) * 255;
		}
		return $out;
	}

	function efb_step_bg_luminance_efb( $rgb ) {
		$l = array();
		foreach ( $rgb as $c ) {
			$c = $c / 255;
			$l[] = $c <= 0.04045 ? $c / 12.92 : pow( ( $c + 0.055 ) / 1.055, 2.4 );
		}
		return 0.2126 * $l[0] + 0.7152 * $l[1] + 0.0722 * $l[2];
	}

	/** Background samples of a resolved background: the stops and 8 points inside every segment. */
	function efb_step_bg_samples_efb( $r ) {
		$rgbs = array();
		foreach ( $r['stops'] as $s ) $rgbs[] = efb_step_bg_rgb_efb( $s[0], $r['opacity'] );
		if ( count( $rgbs ) < 2 ) return $rgbs;
		$out = array();
		for ( $i = 0; $i < count( $rgbs ) - 1; $i++ ) {
			for ( $j = 0; $j < 9; $j++ ) {
				$t = $j / 9;
				$out[] = array(
					$rgbs[ $i ][0] + ( $rgbs[ $i + 1 ][0] - $rgbs[ $i ][0] ) * $t,
					$rgbs[ $i ][1] + ( $rgbs[ $i + 1 ][1] - $rgbs[ $i ][1] ) * $t,
					$rgbs[ $i ][2] + ( $rgbs[ $i + 1 ][2] - $rgbs[ $i ][2] ) * $t,
				);
			}
		}
		$out[] = $rgbs[ count( $rgbs ) - 1 ];
		return $out;
	}

	/** The lowest contrast ratio of a text colour over every sample, rounded to 2 decimals. */
	function efb_step_bg_contrast_efb( $fg, $samples ) {
		$lf = efb_step_bg_luminance_efb( efb_step_bg_rgb_efb( $fg ) );
		$min = 21.0;
		foreach ( $samples as $s ) {
			$lb = efb_step_bg_luminance_efb( $s );
			$ratio = ( max( $lf, $lb ) + 0.05 ) / ( min( $lf, $lb ) + 0.05 );
			if ( $ratio < $min ) $min = $ratio;
		}
		return round( $min, 2 );
	}

	/** The more readable of a near-black and white text colour over the samples (dark on a tie). */
	function efb_step_bg_auto_text_efb( $samples ) {
		$dark = efb_step_bg_contrast_efb( '#111827', $samples );
		$light = efb_step_bg_contrast_efb( '#ffffff', $samples );
		return $light > $dark ? '#ffffff' : '#111827';
	}

	/** The text colour a resolved background imposes, or '' to keep the field colours. */
	function efb_step_bg_text_color_efb( $r, $samples = null ) {
		if ( $r['text'] === '' ) return '';
		if ( $r['text'] !== 'auto' ) return $r['text'];
		return efb_step_bg_auto_text_efb( $samples === null ? efb_step_bg_samples_efb( $r ) : $samples );
	}

	/**
	 * The stylesheet of every painted step of a form, or '' when no step has a background (old
	 * forms: nothing changes). $prefix selects the step fieldsets of one form, e.g.
	 * 'fieldset.steps-efb[data-formid="12"]'; step N is $prefix[data-step="step-N-efb"] and the
	 * final screen is $prefix#efb-final-step (form default only).
	 */
	function efb_step_bg_css_efb( $rows, $prefix, $opts = array() ) {
		if ( ! is_array( $rows ) || ! isset( $rows[0] ) ) return '';
		$form0 = $rows[0];
		$painted = array();
		$count = 0;
		foreach ( $rows as $i => $row ) {
			if ( $i === 0 || efb_step_bg_get_efb( $row, 'type' ) !== 'step' ) continue;
			$count++;
			$n = (int) efb_step_bg_get_efb( $row, 'step' );
			if ( $n < 1 ) $n = $count;
			$r = efb_step_bg_resolve_efb( $row, $form0, $opts );
			if ( $r !== null ) $painted[] = array( $prefix . '[data-step="step-' . $n . '-efb"]', $r );
		}
		if ( $count > 0 ) {
			$r = efb_step_bg_resolve_efb( array(), $form0, $opts );
			if ( $r !== null ) $painted[] = array( $prefix . '#efb-final-step', $r );
		}
		if ( empty( $painted ) ) return '';

		$list = '.col-form-label,.col-form-label>span:not(.text-danger),label.hStyleOpEfb,small.form-text[id$="-des"]';
		$fg_rules = function ( $s, $fg ) use ( $list ) {
			return $s . '{color:' . $fg . '}' . $s . ' :is(' . $list . '){color:' . $fg . '!important}';
		};
		$sels = array();
		$befs = array();
		foreach ( $painted as $p ) {
			$sels[] = $p[0];
			$befs[] = $p[0] . '::before';
		}
		$bef = implode( ',', $befs );
		$css = implode( ',', $sels ) . '{position:relative;isolation:isolate;border-radius:.75rem;padding:1rem .75rem!important}';
		$css .= $bef . '{content:"";position:absolute;inset:0;z-index:-1;border-radius:inherit;pointer-events:none;animation:efb-sbg-in .4s ease-out both}';
		$css .= '@keyframes efb-sbg-in{from{opacity:0}to{opacity:1}}';
		$mobile = '';
		$print_fg = array();
		foreach ( $painted as $p ) {
			list( $s, $r ) = $p;
			$base = efb_step_bg_color_css_efb( $r['stops'][0][0], $r['opacity'] );
			$grad = efb_step_bg_gradient_css_efb( $r );
			$css .= $s . '::before{background-color:' . $base . ( $grad !== '' ? ';background-image:' . $grad : '' ) . '}';
			$samples = efb_step_bg_samples_efb( $r );
			$fg = efb_step_bg_text_color_efb( $r, $samples );
			if ( $fg !== '' ) {
				$css .= $r['mobile'] === 'none' ? '@media (min-width:768px){' . $fg_rules( $s, $fg ) . '}' : $fg_rules( $s, $fg );
				$print_fg[] = $s . ',' . $s . ' :is(' . $list . ')';
			}
			if ( $r['mobile'] === 'none' ) {
				$mobile .= $s . '::before{display:none}' . $s . '{padding:0 2%!important;border-radius:0}';
			} elseif ( $r['mobile'] === 'solid' ) {
				$mobile .= $s . '::before{background-image:none;background-color:' . $base . '}';
				if ( $r['text'] === 'auto' ) {
					$fg2 = efb_step_bg_auto_text_efb( array( efb_step_bg_rgb_efb( $r['stops'][0][0], $r['opacity'] ) ) );
					if ( $fg2 !== $fg ) $mobile .= $fg_rules( $s, $fg2 );
				}
			}
		}
		if ( $mobile !== '' ) $css .= '@media (max-width:767.98px){' . $mobile . '}';
		$css .= '@media (prefers-reduced-motion:reduce){' . $bef . '{animation:none}}';
		$css .= '@media (forced-colors:active){' . $bef . '{display:none}}';
		$css .= '@media print{' . $bef . '{display:none}' . ( empty( $print_fg ) ? '' : implode( ',', $print_fg ) . '{color:#000!important}' ) . '}';
		return $css;
	}

	/** The public renderer's call: the CSS for one form, scoped by its id, with the site's plan. */
	function efb_step_bg_form_css_efb( $rows, $form_id, $pro ) {
		$opts = array(
			'gradient' => (bool) $pro,
			'mobile'   => function_exists( 'efb_plan_at_least_efb' ) ? (bool) efb_plan_at_least_efb( 'pro' ) : false,
		);
		return efb_step_bg_css_efb( $rows, 'fieldset.steps-efb[data-formid="' . intval( $form_id ) . '"]', $opts );
	}
}
