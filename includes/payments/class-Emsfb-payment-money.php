<?php
/**
 * PAY00 §8.2: amounts as integers of the smallest ISO 4217 unit, converted from and to decimal
 * strings without floating point. A gateway converts to its own wire format
 * (Gateway::format_amount()); everything inside EFB is minor units.
 */
namespace Emsfb\Payments;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Currency {

	/** ISO 4217 currencies whose minor unit is not 2. */
	const EXPONENTS = array(
		// 0 decimals
		'BIF' => 0, 'CLP' => 0, 'DJF' => 0, 'GNF' => 0, 'ISK' => 0, 'JPY' => 0, 'KMF' => 0, 'KRW' => 0, 'PYG' => 0,
		'RWF' => 0, 'UGX' => 0, 'UYI' => 0, 'VND' => 0, 'VUV' => 0, 'XAF' => 0, 'XOF' => 0, 'XPF' => 0,
		// 3 decimals
		'BHD' => 3, 'IQD' => 3, 'JOD' => 3, 'KWD' => 3, 'LYD' => 3, 'OMR' => 3, 'TND' => 3,
		// 4 decimals
		'CLF' => 4, 'UYW' => 4,
	);

	/** A 3-letter code (upper case), or '' when it is not one. */
	public static function code( $iso ) {
		$iso = strtoupper( trim( (string) $iso ) );
		return preg_match( '/^[A-Z]{3}$/', $iso ) ? $iso : '';
	}

	/** Digits after the decimal point; filter efb_currency_exponent for a gateway's exception. */
	public static function exponent( $iso, $gateway = '' ) {
		$iso = self::code( $iso );
		$e = isset( self::EXPONENTS[ $iso ] ) ? self::EXPONENTS[ $iso ] : 2;
		if ( function_exists( 'apply_filters' ) ) {
			$f = apply_filters( 'efb_currency_exponent', $e, $iso, (string) $gateway );
			if ( is_int( $f ) && $f >= 0 && $f <= 4 ) $e = $f;
		}
		return $e;
	}
}

class Money {

	/**
	 * '12.5' (or 12.5) in KWD → 12500. Rounds half up at the currency's exponent.
	 * Accepts a string or number with an optional leading '-', digits and one '.'; Persian and
	 * Arabic digits and a ',' decimal separator are normalized. Anything else → null.
	 *
	 * @return int|null
	 */
	public static function from_decimal( $value, $currency, $gateway = '' ) {
		if ( is_bool( $value ) || $value === null ) return null;
		if ( is_int( $value ) ) {
			$s = (string) $value;
		} elseif ( is_float( $value ) ) {
			if ( ! is_finite( $value ) ) return null;
			$s = rtrim( rtrim( sprintf( '%.10F', $value ), '0' ), '.' );
		} elseif ( is_string( $value ) ) {
			$s = self::latin_digits( trim( $value ) );
			if ( strpos( $s, ',' ) !== false && strpos( $s, '.' ) === false ) $s = str_replace( ',', '.', $s );
		} else {
			return null;
		}
		if ( ! preg_match( '/^(-?)(\d{1,15})(?:\.(\d{0,12}))?$/', $s, $m ) ) {
			if ( preg_match( '/^(-?)\.(\d{1,12})$/', $s, $m2 ) ) { $m = array( $s, $m2[1], '0', $m2[2] ); } else { return null; }
		}
		$neg = $m[1] === '-';
		$int = ltrim( $m[2], '0' );
		$frac = isset( $m[3] ) ? $m[3] : '';
		$e = Currency::exponent( $currency, $gateway );
		$keep = substr( str_pad( $frac, $e, '0' ), 0, $e );
		$next = strlen( $frac ) > $e ? (int) $frac[ $e ] : 0;
		$digits = ( $int === '' ? '0' : $int ) . $keep;
		$n = (int) ltrim( $digits, '0' );
		if ( $next >= 5 ) $n++;
		return $neg ? -$n : $n;
	}

	/** 12500 in KWD → '12.500'; 1250 in EUR → '12.50'; 500 in JPY → '500'. */
	public static function to_decimal_string( $minor, $currency, $gateway = '' ) {
		$minor = (int) $minor;
		$e = Currency::exponent( $currency, $gateway );
		$neg = $minor < 0;
		$s = (string) abs( $minor );
		if ( $e > 0 ) {
			$s = str_pad( $s, $e + 1, '0', STR_PAD_LEFT );
			$s = substr( $s, 0, -$e ) . '.' . substr( $s, -$e );
		}
		return ( $neg ? '-' : '' ) . $s;
	}

	/** Display text: "€12.50" style, through intl when present, else code + amount. */
	public static function format( $minor, $currency, $locale = '' ) {
		$cur = Currency::code( $currency );
		$dec = self::to_decimal_string( $minor, $cur );
		if ( class_exists( '\NumberFormatter' ) && $cur !== '' ) {
			$f = new \NumberFormatter( $locale !== '' ? $locale : 'en_US', \NumberFormatter::CURRENCY );
			$f->setAttribute( \NumberFormatter::FRACTION_DIGITS, Currency::exponent( $cur ) );
			$out = $f->formatCurrency( (float) $dec, $cur );
			if ( is_string( $out ) && $out !== '' ) return $out;
		}
		return trim( $dec . ' ' . $cur );
	}

	public static function latin_digits( $s ) {
		return strtr( (string) $s, array(
			'۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4', '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
			'٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4', '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
			'٫' => '.', '٬' => '',
		) );
	}
}
