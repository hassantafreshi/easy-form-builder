<?php
/**
 * PHP capability checks shared by bundled Easy Form Builder add-ons.
 *
 * A function can be unavailable because its PHP extension is not installed or
 * because a host listed it in php.ini's disable_functions directive.  PHP 8
 * removes disabled functions from function_exists(), whereas some older PHP
 * versions do not, so both conditions must be checked before an add-on calls
 * a server capability.
 *
 * @package Easy_Form_Builder
 */

if ( ! defined( 'ABSPATH' ) ) {
	return;
}

class Emsfb_Addon_Compatibility {

	/** @var array<string, bool> */
	private static $function_availability = array();

	/** @var array<int, string>|null */
	private static $disabled_functions = null;

	/**
	 * List every bundled add-on here, even when it has no special PHP function
	 * dependency. This makes the compatibility scan explicit and gives future
	 * add-ons one central place to declare their requirements.
	 *
	 * @return array<string, array{name: string, functions: array<int, string>}>
	 */
	public static function addon_requirements() {
		$requirements = array(
			'AdnSPF' => array(
				'name'      => 'Stripe Payment',
				'functions' => array( 'curl_init', 'curl_setopt_array', 'curl_exec', 'curl_errno', 'curl_error', 'curl_getinfo', 'curl_close', 'curl_reset', 'curl_version', 'openssl_random_pseudo_bytes', 'ini_get', 'json_encode', 'json_decode' ),
			),
			'AdnOF'  => array( 'name' => 'Offline Forms', 'functions' => array() ),
			'AdnPPF' => array(
				'name'      => 'Persia Payment',
				'functions' => array( 'curl_init', 'curl_setopt', 'curl_exec', 'curl_error', 'curl_close', 'json_encode', 'json_decode' ),
			),
			'AdnATC' => array( 'name' => 'Advanced Tracking Code', 'functions' => array() ),
			'AdnSS'  => array( 'name' => 'SMS Notifications', 'functions' => array() ),
			'AdnCPF' => array( 'name' => 'AdnCPF', 'functions' => array() ),
			'AdnESZ' => array( 'name' => 'AdnESZ', 'functions' => array() ),
			'AdnSE'  => array( 'name' => 'Search Entry', 'functions' => array() ),
			'AdnWHS' => array( 'name' => 'Webhook', 'functions' => array() ),
			'AdnPAP' => array( 'name' => 'PayPal Payment', 'functions' => array() ),
			'AdnWSP' => array( 'name' => 'WhatsApp', 'functions' => array() ),
			'AdnSMF' => array( 'name' => 'Conditional Logic', 'functions' => array() ),
			'AdnPLF' => array( 'name' => 'AdnPLF', 'functions' => array() ),
			'AdnMSF' => array( 'name' => 'AdnMSF', 'functions' => array() ),
			'AdnBEF' => array( 'name' => 'Booking', 'functions' => array() ),
			'AdnPDP' => array( 'name' => 'Persian Date Picker', 'functions' => array() ),
			'AdnADP' => array( 'name' => 'Arabic Date Picker', 'functions' => array() ),
			'AdnATF' => array( 'name' => 'Auto-Populate', 'functions' => array() ),
			'AdnTLG' => array( 'name' => 'Telegram Notifications', 'functions' => array() ),
			'AdnGoS' => array(
				'name'      => 'Google Sheets',
				'functions' => array( 'openssl_pkey_get_private', 'openssl_sign', 'json_encode', 'json_decode', 'base64_encode' ),
			),
			'AdnHSH' => array(
				'name'      => 'Form Security & Spam Protection',
				'functions' => array( 'hash_hmac', 'hash', 'json_encode', 'json_decode', 'base64_encode', 'base64_decode' ),
			),
		);

		return function_exists( 'apply_filters' )
			? apply_filters( 'emsfb_addon_php_requirements', $requirements )
			: $requirements;
	}

	/**
	 * Return functions disabled by php.ini, plus an optional filter for hosts
	 * and automated tests that expose extra restrictions.
	 *
	 * @return array<int, string>
	 */
	public static function disabled_functions() {
		if ( null !== self::$disabled_functions ) {
			return self::$disabled_functions;
		}

		$disabled = '';
		$ini_get_failed = false;
		if ( function_exists( 'ini_get' ) ) {
			try {
				// On older PHP versions a disabled ini_get() can still pass
				// function_exists(). Suppress its warning and safely fall back.
				$disabled = @ini_get( 'disable_functions' ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
				$ini_get_failed = false === $disabled;
			} catch ( \Throwable $e ) {
				$disabled = '';
				$ini_get_failed = true;
			}
		} else {
			// ini_get is a PHP core function. If it is absent, a hardened PHP 8+
			// configuration has almost certainly disabled it.
			$ini_get_failed = true;
		}

		$list = is_string( $disabled ) && '' !== trim( $disabled )
			? array_map( 'trim', explode( ',', strtolower( $disabled ) ) )
			: array();
		if ( $ini_get_failed ) {
			$list[] = 'ini_get';
		}

		if ( function_exists( 'apply_filters' ) ) {
			$extra = apply_filters( 'emsfb_disabled_php_functions', array() );
			if ( is_array( $extra ) ) {
				foreach ( $extra as $function_name ) {
					if ( is_scalar( $function_name ) ) {
						$list[] = strtolower( trim( (string) $function_name ) );
					}
				}
			}
		}

		self::$disabled_functions = array_values( array_unique( array_filter( $list ) ) );
		return self::$disabled_functions;
	}

	/**
	 * Clear cached checks. Intended for tests or a host integration that changes
	 * the disabled-functions filter during the same request.
	 *
	 * @return void
	 */
	public static function reset_cache() {
		self::$function_availability = array();
		self::$disabled_functions    = null;
	}

	/**
	 * Whether a PHP function can safely be invoked on this request.
	 *
	 * @param string $function_name PHP function name.
	 * @return bool
	 */
	public static function is_function_available( $function_name ) {
		$function_name = strtolower( ltrim( trim( (string) $function_name ), '\\' ) );
		if ( '' === $function_name ) {
			return false;
		}

		if ( array_key_exists( $function_name, self::$function_availability ) ) {
			return self::$function_availability[ $function_name ];
		}

		self::$function_availability[ $function_name ] = function_exists( $function_name )
			&& ! in_array( $function_name, self::disabled_functions(), true );

		return self::$function_availability[ $function_name ];
	}

	/**
	 * @param string $function_name PHP function name.
	 * @return bool True when php.ini explicitly disabled the function.
	 */
	public static function is_function_disabled( $function_name ) {
		$function_name = strtolower( ltrim( trim( (string) $function_name ), '\\' ) );
		return '' !== $function_name && in_array( $function_name, self::disabled_functions(), true );
	}

	/**
	 * @param string $addon_key Add-on identifier.
	 * @return array<int, string>
	 */
	public static function missing_functions( $addon_key ) {
		$requirements = self::addon_requirements();
		if ( ! isset( $requirements[ $addon_key ]['functions'] ) || ! is_array( $requirements[ $addon_key ]['functions'] ) ) {
			return array();
		}

		$missing = array();
		foreach ( $requirements[ $addon_key ]['functions'] as $function_name ) {
			if ( ! self::is_function_available( $function_name ) ) {
				$missing[] = $function_name;
			}
		}

		return $missing;
	}

	/**
	 * @param string $addon_key Add-on identifier.
	 * @return bool
	 */
	public static function is_addon_compatible( $addon_key ) {
		return empty( self::missing_functions( $addon_key ) );
	}

	/**
	 * Report every add-on with unmet PHP requirements.
	 *
	 * @param object|array|null $settings Decoded EFB settings.
	 * @return array<string, array{name: string, enabled: bool, missing_functions: array<int, string>, disabled_functions: array<int, string>, missing_extension_functions: array<int, string>}>
	 */
	public static function incompatible_addons( $settings = null ) {
		$issues = array();
		foreach ( self::addon_requirements() as $addon_key => $requirement ) {
			$missing = self::missing_functions( $addon_key );
			if ( empty( $missing ) ) {
				continue;
			}

			$enabled = false;
			if ( is_object( $settings ) && isset( $settings->{$addon_key} ) ) {
				$enabled = absint( $settings->{$addon_key} ) >= 1;
			} elseif ( is_array( $settings ) && isset( $settings[ $addon_key ] ) ) {
				$enabled = absint( $settings[ $addon_key ] ) >= 1;
			}

			$issues[ $addon_key ] = array(
				'name'                        => isset( $requirement['name'] ) ? (string) $requirement['name'] : (string) $addon_key,
				'enabled'                     => $enabled,
				'missing_functions'           => $missing,
				'disabled_functions'          => array_values( array_filter( $missing, array( __CLASS__, 'is_function_disabled' ) ) ),
				'missing_extension_functions' => array_values( array_filter( $missing, function ( $function_name ) {
					return ! self::is_function_disabled( $function_name );
				} ) ),
			);
		}

		return $issues;
	}

	/**
	 * User-facing explanation that can be returned safely from AJAX/REST
	 * handlers before an unavailable add-on reaches its restricted function.
	 *
	 * @param string $addon_key Add-on identifier.
	 * @return string
	 */
	public static function addon_unavailable_message( $addon_key ) {
		$requirements = self::addon_requirements();
		$name         = isset( $requirements[ $addon_key ]['name'] ) ? $requirements[ $addon_key ]['name'] : $addon_key;
		$missing      = self::missing_functions( $addon_key );

		if ( empty( $missing ) ) {
			return sprintf( esc_html__( '%s is currently unavailable.', 'easy-form-builder' ), $name );
		}

		$disabled = array_values( array_filter( $missing, array( __CLASS__, 'is_function_disabled' ) ) );
		$absent   = array_values( array_diff( $missing, $disabled ) );
		$reasons  = array();
		if ( ! empty( $disabled ) ) {
			$reasons[] = sprintf( esc_html__( 'disabled in php.ini: %s', 'easy-form-builder' ), implode( ', ', $disabled ) );
		}
		if ( ! empty( $absent ) ) {
			$reasons[] = sprintf( esc_html__( 'not provided by PHP or its required extension: %s', 'easy-form-builder' ), implode( ', ', $absent ) );
		}

		return sprintf(
			esc_html__( '%1$s cannot run because required PHP functions are unavailable (%2$s). Ask your hosting provider to enable the required PHP extension or remove the listed functions from disable_functions in php.ini.', 'easy-form-builder' ),
			$name,
			implode( '; ', $reasons )
		);
	}

	/**
	 * Safe replacement for file_get_contents() where an EFB request can recover
	 * from a host disabling filesystem functions.
	 *
	 * @param string $path File path or stream URI.
	 * @return string|false
	 */
	public static function read_file( $path ) {
		if ( ! self::is_function_available( 'file_get_contents' ) ) {
			return false;
		}

		try {
			return @file_get_contents( $path ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		} catch ( \Throwable $e ) {
			return false;
		}
	}

	/**
	 * Read a php.ini value only when ini_get() is available.
	 *
	 * @param string $name    php.ini key.
	 * @param mixed  $default Value used when the host disabled ini_get().
	 * @return mixed
	 */
	public static function ini_value( $name, $default = '' ) {
		if ( ! self::is_function_available( 'ini_get' ) ) {
			return $default;
		}

		try {
			$value = @ini_get( $name ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			return false === $value ? $default : $value;
		} catch ( \Throwable $e ) {
			return $default;
		}
	}

	/**
	 * Generate a token without calling random_bytes/OpenSSL when either function
	 * is disabled. The WordPress fallback preserves operation on locked-down
	 * hosts; stronger sources are used whenever the server provides them.
	 *
	 * @param int $length Number of characters to return.
	 * @return string
	 */
	public static function random_token( $length = 16 ) {
		$length = max( 1, absint( $length ) );
		$bytes  = (int) ceil( $length / 2 );

		if ( self::is_function_available( 'random_bytes' ) && self::is_function_available( 'bin2hex' ) ) {
			try {
				return substr( bin2hex( random_bytes( $bytes ) ), 0, $length );
			} catch ( \Throwable $e ) {
				// Try the next supported source.
			}
		}

		if ( self::is_function_available( 'openssl_random_pseudo_bytes' ) && self::is_function_available( 'bin2hex' ) ) {
			try {
				$value = openssl_random_pseudo_bytes( $bytes );
				if ( is_string( $value ) && '' !== $value ) {
					return substr( bin2hex( $value ), 0, $length );
				}
			} catch ( \Throwable $e ) {
				// Fall through to WordPress's generator.
			}
		}

		if ( function_exists( 'wp_generate_password' ) ) {
			return substr( wp_generate_password( $length, true, true ), 0, $length );
		}

		if ( self::is_function_available( 'str_shuffle' ) ) {
			return substr( str_shuffle( str_repeat( 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789', 4 ) ), 0, $length );
		}

		// Extremely locked-down environments still receive a predictable value
		// rather than a fatal error.
		return str_repeat( '0', $length );
	}
}

/**
 * WordPress-style public entry point for add-on capability checks.
 *
 * Add-on files call this procedural helper rather than depending on the
 * implementation class or its namespace. The class remains an internal
 * detail that centralizes requirements and the php.ini check.
 *
 * @param string $addon_key Add-on identifier, such as AdnGoS.
 * @return bool
 */
if ( ! function_exists( 'emsfb_is_addon_compatible_efb' ) ) {
	function emsfb_is_addon_compatible_efb( $addon_key ) {
		return Emsfb_Addon_Compatibility::is_addon_compatible( $addon_key );
	}
}

/**
 * @param string $function_name PHP function name.
 * @return bool
 */
if ( ! function_exists( 'emsfb_is_php_function_available_efb' ) ) {
	function emsfb_is_php_function_available_efb( $function_name ) {
		return Emsfb_Addon_Compatibility::is_function_available( $function_name );
	}
}

/**
 * @param string $function_name PHP function name.
 * @return bool
 */
if ( ! function_exists( 'emsfb_is_php_function_disabled_efb' ) ) {
	function emsfb_is_php_function_disabled_efb( $function_name ) {
		return Emsfb_Addon_Compatibility::is_function_disabled( $function_name );
	}
}

/**
 * @param string $addon_key Add-on identifier.
 * @return array<int, string>
 */
if ( ! function_exists( 'emsfb_get_missing_addon_functions_efb' ) ) {
	function emsfb_get_missing_addon_functions_efb( $addon_key ) {
		return Emsfb_Addon_Compatibility::missing_functions( $addon_key );
	}
}

/**
 * @param object|array|null $settings Decoded EFB settings.
 * @return array
 */
if ( ! function_exists( 'emsfb_get_incompatible_addons_efb' ) ) {
	function emsfb_get_incompatible_addons_efb( $settings = null ) {
		return Emsfb_Addon_Compatibility::incompatible_addons( $settings );
	}
}

/**
 * @param string $addon_key Add-on identifier.
 * @return string
 */
if ( ! function_exists( 'emsfb_get_addon_unavailable_message_efb' ) ) {
	function emsfb_get_addon_unavailable_message_efb( $addon_key ) {
		return Emsfb_Addon_Compatibility::addon_unavailable_message( $addon_key );
	}
}

/**
 * @param string $path File path or stream URI.
 * @return string|false
 */
if ( ! function_exists( 'emsfb_read_file_efb' ) ) {
	function emsfb_read_file_efb( $path ) {
		return Emsfb_Addon_Compatibility::read_file( $path );
	}
}

/**
 * Resolve the typographic entities used in translatable strings.
 *
 * Translatable strings spell punctuation as an entity (&hellip;, &mdash;, …)
 * the way WordPress core does, which renders correctly as long as the string
 * ends up in markup. WP_Scripts::localize() decodes entities only for scalar
 * values sitting at the top level of the $l10n array, so a string inside a
 * nested array - efb_var['text'], the admin-bar labels, the block editor
 * strings - reaches JavaScript raw and is printed literally wherever it is
 * assigned to textContent or to a DOM property instead of being parsed as
 * HTML. Walking the whole structure here keeps the entity in the source
 * string while the browser still receives the character.
 *
 * Only punctuation entities are resolved. &amp;, &lt; and &gt; are left alone
 * so a decoded string can never gain a tag or an attribute boundary it did
 * not already have, which matters because several of these values are later
 * written with innerHTML.
 *
 * @param mixed $data String, or an arbitrarily nested array of them.
 * @return mixed Same shape as $data.
 */
if ( ! function_exists( 'emsfb_decode_typographic_entities_efb' ) ) {
	function emsfb_decode_typographic_entities_efb( $data ) {
		if ( is_array( $data ) ) {
			foreach ( $data as $key => $value ) {
				$data[ $key ] = emsfb_decode_typographic_entities_efb( $value );
			}

			return $data;
		}

		if ( ! is_string( $data ) || false === strpos( $data, '&' ) ) {
			return $data;
		}

		static $map = array(
			'&hellip;'  => '…',
			'&#8230;'   => '…',
			'&#x2026;'  => '…',
			'&mdash;'   => '—',
			'&#8212;'   => '—',
			'&ndash;'   => '–',
			'&#8211;'   => '–',
			'&nbsp;'    => "\xc2\xa0",
			'&#160;'    => "\xc2\xa0",
			'&lsquo;'   => '‘',
			'&rsquo;'   => '’',
			'&#8217;'   => '’',
			'&ldquo;'   => '“',
			'&rdquo;'   => '”',
			'&laquo;'   => '«',
			'&raquo;'   => '»',
			'&bull;'    => '•',
			'&middot;'  => '·',
			'&times;'   => '×',
			'&deg;'     => '°',
			'&trade;'   => '™',
			'&copy;'    => '©',
			'&reg;'     => '®',
		);

		return strtr( $data, $map );
	}
}

/**
 * @param string $name    php.ini key.
 * @param mixed  $default Value used when unavailable.
 * @return mixed
 */
if ( ! function_exists( 'emsfb_get_php_ini_value_efb' ) ) {
	function emsfb_get_php_ini_value_efb( $name, $default = '' ) {
		return Emsfb_Addon_Compatibility::ini_value( $name, $default );
	}
}

/**
 * @param int $length Number of characters.
 * @return string
 */
if ( ! function_exists( 'emsfb_generate_token_efb' ) ) {
	function emsfb_generate_token_efb( $length = 16 ) {
		return Emsfb_Addon_Compatibility::random_token( $length );
	}
}

/**
 * Clear cached PHP capability checks. Useful for automated tests and host
 * integrations that update the disabled-functions filter during a request.
 *
 * @return void
 */
if ( ! function_exists( 'emsfb_reset_php_compatibility_cache_efb' ) ) {
	function emsfb_reset_php_compatibility_cache_efb() {
		Emsfb_Addon_Compatibility::reset_cache();
	}
}
