<?php
/**
 * PAY00 §8: the amount a framework gateway charges. Always computed here, from the stored form;
 * a price the browser sends is never read. Pure: no output, no HTTP, errors as codes.
 *
 * Sources of the amount (all in the smallest unit of the form's currency):
 *  - priced choices: payRadio, paySelect, payCheckbox, payMultiselect (price of the saved option);
 *  - price fields (prcfld): a typed amount within the field's Min/Max, or the B7 server result
 *    when a logic rule computes it (Builder_Api::apply_server_prices());
 *  - the payment field itself: a fixed amount (pf_fixed) and/or a donation (pf_amount = donation:
 *    a preset from pf_presets, or any amount in [pf_min, pf_max] when pf_other = 1).
 * Coupons, tax and fees are part of the quote shape (0 in phase 1).
 *
 * The browser has a twin for display only (efbPayCoreEfb.quote() in payment-core-efb.js).
 */
namespace Emsfb\Payments;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Pricing {

	const PAY_TYPES = array( 'payRadio', 'paySelect', 'payCheckbox', 'payMultiselect', 'prcfld' );

	/**
	 * @param array $form  Stored form rows (associative arrays; row 0 = settings).
	 * @param array $rows  Submitted (ideally already validated) rows.
	 * @param array $ctx   field_id (the payment field), gateway (slug), supports (Gateway::supports()),
	 *                     server_prices (bool, default true: run B7).
	 * @return array Quote, or ['ok' => false, 'error' => code, 'field_id' => id?]
	 */
	public static function quote( array $form, array $rows, array $ctx = array() ) {
		$form0 = isset( $form[0] ) && is_array( $form[0] ) ? $form[0] : array();
		$currency = Currency::code( isset( $form0['currency'] ) ? $form0['currency'] : '' );
		if ( $currency === '' ) return self::err( 'currency_unsupported' );
		$supports = isset( $ctx['supports'] ) && is_array( $ctx['supports'] ) ? $ctx['supports'] : array();
		if ( ! empty( $supports['currencies'] ) && ! in_array( $currency, array_map( 'strtoupper', (array) $supports['currencies'] ), true ) ) {
			return self::err( 'currency_unsupported' );
		}
		$gateway = isset( $ctx['gateway'] ) ? (string) $ctx['gateway'] : '';

		$rows = array_values( array_filter( $rows, 'is_array' ) );
		if ( ( ! isset( $ctx['server_prices'] ) || $ctx['server_prices'] ) && class_exists( '\Emsfb\Builder_Api' ) ) {
			list( $rows, $error ) = \Emsfb\Builder_Api::apply_server_prices( $rows, $form );
			if ( $error !== null ) return self::err( 'price_not_computable' );
		}

		$fields = array();
		foreach ( $form as $i => $r ) {
			if ( $i > 0 && is_array( $r ) && isset( $r['id_'] ) ) $fields[ (string) $r['id_'] ] = $r;
		}
		$items = array();
		$seen = array();
		foreach ( $rows as $row ) {
			$id = isset( $row['id_'] ) && is_scalar( $row['id_'] ) ? (string) $row['id_'] : '';
			if ( $id === '' || ! isset( $fields[ $id ] ) ) continue;
			$field = $fields[ $id ];
			$ftype = isset( $field['type'] ) ? (string) $field['type'] : '';
			if ( ! in_array( $ftype, self::PAY_TYPES, true ) ) continue;
			$ob = isset( $row['id_ob'] ) && is_scalar( $row['id_ob'] ) ? (string) $row['id_ob'] : '';
			$key = $id . '|' . ( $ftype === 'payCheckbox' ? $ob : '' );
			if ( isset( $seen[ $key ] ) ) continue;
			$seen[ $key ] = true;
			$label = isset( $field['name'] ) ? (string) $field['name'] : '';
			switch ( $ftype ) {
				case 'payRadio':
				case 'payCheckbox':
					$opt = $ob !== '' && isset( $fields[ $ob ] ) ? $fields[ $ob ] : null;
					if ( ! $opt || ( $opt['type'] ?? '' ) !== 'option' || (string) ( $opt['parent'] ?? '' ) !== $id ) return self::err( 'invalid_selection', $id );
					$minor = self::price_minor( $opt, $currency, $gateway );
					if ( $minor === null ) return self::err( 'invalid_selection', $id );
					$items[] = self::item( $id . ( $ftype === 'payCheckbox' ? '|' . $ob : '' ), $label . ': ' . (string) ( $opt['value'] ?? '' ), $minor, 'option' );
					break;
				case 'paySelect':
					$opt = self::option_by( $fields, $id, $ob, isset( $row['value'] ) ? $row['value'] : null );
					if ( ! $opt ) return self::err( 'invalid_selection', $id );
					$minor = self::price_minor( $opt, $currency, $gateway );
					if ( $minor === null ) return self::err( 'invalid_selection', $id );
					$items[] = self::item( $id, $label . ': ' . (string) ( $opt['value'] ?? '' ), $minor, 'option' );
					break;
				case 'payMultiselect':
					$texts = isset( $row['value'] ) && is_string( $row['value'] ) ? array_filter( explode( '@efb!', $row['value'] ), 'strlen' ) : array();
					$ids = isset( $row['ids'] ) && is_string( $row['ids'] ) ? array_filter( explode( ',', $row['ids'] ), 'strlen' ) : array();
					$picked = array();
					foreach ( $ids as $oid ) {
						if ( isset( $fields[ $oid ] ) && (string) ( $fields[ $oid ]['parent'] ?? '' ) === $id ) $picked[ $oid ] = $fields[ $oid ];
					}
					foreach ( $texts as $txt ) {
						$opt = self::option_by( $fields, $id, '', $txt );
						if ( $opt ) $picked[ (string) $opt['id_'] ] = $opt;
					}
					if ( empty( $picked ) && ( $texts || $ids ) ) return self::err( 'invalid_selection', $id );
					foreach ( $picked as $oid => $opt ) {
						$minor = self::price_minor( $opt, $currency, $gateway );
						if ( $minor === null ) return self::err( 'invalid_selection', $id );
						$items[] = self::item( $id . '|' . $oid, $label . ': ' . (string) ( $opt['value'] ?? '' ), $minor, 'option' );
					}
					break;
				case 'prcfld':
					$v = isset( $row['value'] ) && is_scalar( $row['value'] ) && (string) $row['value'] !== '' ? $row['value'] : ( isset( $row['price'] ) ? $row['price'] : '' );
					if ( $v === '' ) break;
					$minor = Money::from_decimal( $v, $currency, $gateway );
					if ( $minor === null || $minor < 0 ) return self::err( 'invalid_selection', $id );
					$min = isset( $field['milen'] ) && is_numeric( $field['milen'] ) ? Money::from_decimal( (string) $field['milen'], $currency, $gateway ) : null;
					$max = isset( $field['mlen'] ) && is_numeric( $field['mlen'] ) && (float) $field['mlen'] > 0 ? Money::from_decimal( (string) $field['mlen'], $currency, $gateway ) : null;
					if ( ( $min !== null && $minor < $min ) || ( $max !== null && $minor > $max ) ) return self::err( 'pwyw_out_of_range', $id );
					if ( $minor > 0 ) $items[] = self::item( $id, $label, $minor, 'price' );
					break;
			}
		}

		// The payment field's own amounts.
		$fid = isset( $ctx['field_id'] ) ? (string) $ctx['field_id'] : '';
		$pf = $fid !== '' && isset( $fields[ $fid ] ) ? $fields[ $fid ] : null;
		if ( $pf ) {
			$fixed = Field::sanitize_key( 'pf_fixed', isset( $pf['pf_fixed'] ) ? $pf['pf_fixed'] : '' );
			if ( $fixed !== '' ) {
				$m = Money::from_decimal( $fixed, $currency, $gateway );
				if ( $m !== null && $m > 0 ) {
					$fl = Field::sanitize_key( 'pf_fixed_label', isset( $pf['pf_fixed_label'] ) ? $pf['pf_fixed_label'] : '' );
					$items[] = self::item( $fid . '|fixed', $fl !== '' ? $fl : (string) ( $pf['name'] ?? '' ), $m, 'fixed' );
				}
			}
			if ( Field::sanitize_key( 'pf_amount', isset( $pf['pf_amount'] ) ? $pf['pf_amount'] : '' ) === 'donation' ) {
				$given = null;
				foreach ( $rows as $row ) {
					if ( isset( $row['id_'] ) && (string) $row['id_'] === $fid && isset( $row['value'] ) && is_scalar( $row['value'] ) && (string) $row['value'] !== '' ) { $given = (string) $row['value']; break; }
				}
				$d = self::donation_minor( $pf, $given, $currency, $gateway );
				if ( is_string( $d ) ) return self::err( $d, $fid );
				if ( $d > 0 ) {
					$dl = Field::sanitize_key( 'pf_t_amount', isset( $pf['pf_t_amount'] ) ? $pf['pf_t_amount'] : '' );
					$items[] = self::item( $fid . '|donation', $dl !== '' ? $dl : (string) ( $pf['name'] ?? '' ), $d, 'donation' );
				}
			}
		}

		$subtotal = 0;
		foreach ( $items as $it ) $subtotal += $it['total_minor'];
		$q = array(
			'ok'                  => true,
			'currency'            => $currency,
			'items'               => $items,
			'subtotal_minor'      => $subtotal,
			'discount_minor'      => 0,
			'coupon'              => null,
			'fee_minor'           => 0,
			'tax_minor'           => 0,
			'tax_inclusive_minor' => 0,
			'total_minor'         => $subtotal,
			'recurring'           => null,
		);
		if ( $q['total_minor'] <= 0 ) return self::err( 'no_amount' );
		$min_minor = isset( $supports['min_minor'][ $currency ] ) ? (int) $supports['min_minor'][ $currency ] : null;
		$max_minor = isset( $supports['max_minor'][ $currency ] ) ? (int) $supports['max_minor'][ $currency ] : null;
		if ( $min_minor !== null && $q['total_minor'] < $min_minor ) return self::err( 'below_min' ) + array( 'limit_minor' => $min_minor, 'currency' => $currency );
		if ( $max_minor !== null && $q['total_minor'] > $max_minor ) return self::err( 'above_max' ) + array( 'limit_minor' => $max_minor, 'currency' => $currency );
		if ( function_exists( 'apply_filters' ) ) {
			$f = apply_filters( 'efb_payment_quote', $q, $form, $rows, $ctx );
			if ( is_array( $f ) && isset( $f['total_minor'] ) && is_int( $f['total_minor'] ) ) $q = $f;
		}
		$q['hash'] = self::hash( $q );
		return $q;
	}

	/** The donation in minor units, or an error code. */
	public static function donation_minor( array $pf, $given, $currency, $gateway = '' ) {
		$presets = array();
		foreach ( Field::presets( isset( $pf['pf_presets'] ) && is_string( $pf['pf_presets'] ) ? $pf['pf_presets'] : '' ) as $p ) {
			$m = Money::from_decimal( $p, $currency, $gateway );
			if ( $m !== null && $m > 0 ) $presets[] = $m;
		}
		$other = Field::sanitize_key( 'pf_other', isset( $pf['pf_other'] ) ? $pf['pf_other'] : '' ) === '1';
		$min_s = Field::sanitize_key( 'pf_min', isset( $pf['pf_min'] ) ? $pf['pf_min'] : '' );
		$max_s = Field::sanitize_key( 'pf_max', isset( $pf['pf_max'] ) ? $pf['pf_max'] : '' );
		$min = $min_s !== '' ? Money::from_decimal( $min_s, $currency, $gateway ) : null;
		$max = $max_s !== '' ? Money::from_decimal( $max_s, $currency, $gateway ) : null;
		if ( $given === null ) {
			$def = Field::sanitize_key( 'pf_default', isset( $pf['pf_default'] ) ? $pf['pf_default'] : '' );
			if ( $def === '' ) return 0;
			$given = $def;
		}
		$m = Money::from_decimal( $given, $currency, $gateway );
		if ( $m === null || $m <= 0 ) return 'pwyw_out_of_range';
		if ( in_array( $m, $presets, true ) ) return $m;
		if ( ! $other && ! empty( $presets ) ) return 'pwyw_out_of_range';
		if ( ( $min !== null && $m < $min ) || ( $max !== null && $m > $max ) ) return 'pwyw_out_of_range';
		return $m;
	}

	private static function option_by( array $fields, $parent, $ob, $text ) {
		if ( $ob !== '' && isset( $fields[ $ob ] ) && (string) ( $fields[ $ob ]['parent'] ?? '' ) === $parent && ( $fields[ $ob ]['type'] ?? '' ) === 'option' ) return $fields[ $ob ];
		if ( ! is_scalar( $text ) ) return null;
		$t = html_entity_decode( (string) $text, ENT_QUOTES, 'UTF-8' );
		foreach ( $fields as $f ) {
			if ( ( $f['type'] ?? '' ) === 'option' && (string) ( $f['parent'] ?? '' ) === $parent && isset( $f['value'] ) && html_entity_decode( (string) $f['value'], ENT_QUOTES, 'UTF-8' ) === $t ) return $f;
		}
		return null;
	}

	private static function price_minor( array $opt, $currency, $gateway ) {
		if ( ! isset( $opt['price'] ) || ( is_string( $opt['price'] ) && trim( $opt['price'] ) === '' ) ) return 0;
		$m = Money::from_decimal( is_string( $opt['price'] ) ? trim( $opt['price'] ) : $opt['price'], $currency, $gateway );
		return $m === null || $m < 0 ? null : $m;
	}

	private static function item( $id, $label, $minor, $kind ) {
		return array( 'id' => (string) $id, 'label' => (string) $label, 'qty' => 1, 'unit_minor' => (int) $minor, 'total_minor' => (int) $minor, 'kind' => $kind );
	}

	private static function err( $code, $field_id = '' ) {
		$e = array( 'ok' => false, 'error' => $code );
		if ( $field_id !== '' ) $e['field_id'] = $field_id;
		return $e;
	}

	/** sha256 of the quote's JSON with sorted keys (stored with the payment, shown to the admin). */
	public static function hash( array $q ) {
		unset( $q['hash'] );
		$sort = function ( $v ) use ( &$sort ) {
			if ( ! is_array( $v ) ) return $v;
			$assoc = array_keys( $v ) !== range( 0, count( $v ) - 1 );
			if ( $assoc ) ksort( $v );
			foreach ( $v as $k => $x ) $v[ $k ] = $sort( $x );
			return $v;
		};
		return hash( 'sha256', (string) json_encode( $sort( $q ), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) );
	}
}
