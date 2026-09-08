<?php
/**
 * Static guard: every $this->..._efb() call inside a class must resolve.
 *
 * notify_admin_addon_blocked_efb() called $this->get_addon_display_names_efb(),
 * a method that has never existed. Nothing caught it, because the branch only
 * runs the first time an add-on download server answers 401/403/406/429 - so
 * the install request died with a fatal and the visitor saw a bare
 * "something went wrong, Code:500" instead of the offline route hint that
 * branch was written to show.
 *
 * This is the same shape as the cross-class $this-> calls that have shipped
 * before (uploads, payment emails), so the check is a plain token scan rather
 * than a test of one call site: it reads each file, records the methods every
 * class declares, and asserts that each $this->name() inside that class body
 * is one of them.
 *
 * Run: php tests/test-function-class-self-calls.php
 */

$root = dirname( __DIR__ );

/** Files scanned. Each holds one or more whole class bodies. */
$targets = array(
	'includes/functions.php',
	'includes/admin/class-Emsfb-admin.php',
	'includes/admin/class-Emsfb-addon.php',
	'includes/admin/class-Emsfb-panel.php',
	'includes/admin/class-Emsfb-create.php',
	'includes/class-Emsfb-public.php',
	'includes/class-Emsfb-formbuilder.php',
);

/**
 * Methods a class may call on $this without declaring them itself: inherited
 * from a parent outside the scanned file, or provided by a trait.
 *
 * Keep this list short and justified - every entry is a hole in the check.
 */
$inherited_allowlist = array();

$passed = 0;
$failed = 0;

function check( $label, $condition, $detail = '' ) {
	global $passed, $failed;
	if ( $condition ) {
		$passed++;
		echo "  PASS  {$label}\n";
		return;
	}
	$failed++;
	echo "  FAIL  {$label}" . ( '' !== $detail ? " - {$detail}" : '' ) . "\n";
}

/**
 * Split a file into class bodies, each with its declared methods and its
 * $this->name() calls.
 *
 * @param string $source PHP source.
 * @return array<int,array{name:string,methods:string[],calls:array<string,int>}>
 */
function scan_classes( $source ) {
	$tokens  = token_get_all( $source );
	$classes = array();
	$total   = count( $tokens );

	for ( $i = 0; $i < $total; $i++ ) {
		$token = $tokens[ $i ];
		if ( ! is_array( $token ) || T_CLASS !== $token[0] ) {
			continue;
		}

		// Class name: the next T_STRING.
		$name = '';
		for ( $j = $i + 1; $j < $total; $j++ ) {
			if ( is_array( $tokens[ $j ] ) && T_STRING === $tokens[ $j ][0] ) {
				$name = $tokens[ $j ][1];
				break;
			}
		}
		if ( '' === $name ) {
			continue;
		}

		// Walk to the opening brace of the class body, then to its match.
		$depth = 0;
		$start = null;
		for ( $j = $i; $j < $total; $j++ ) {
			$t = $tokens[ $j ];
			if ( '{' === $t || ( is_array( $t ) && in_array( $t[0], array( T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES ), true ) ) ) {
				if ( null === $start ) {
					$start = $j;
				}
				$depth++;
				continue;
			}
			if ( '}' === $t ) {
				$depth--;
				if ( 0 === $depth && null !== $start ) {
					$classes[] = array(
						'name'   => $name,
						'tokens' => array_slice( $tokens, $start, $j - $start + 1 ),
						'line'   => $token[2],
					);
					$i = $j;
					break;
				}
			}
		}
	}

	$result = array();
	foreach ( $classes as $class ) {
		$methods = array();
		$calls   = array();
		$body    = $class['tokens'];
		$count   = count( $body );

		for ( $i = 0; $i < $count; $i++ ) {
			$token = $body[ $i ];

			// function name(
			if ( is_array( $token ) && T_FUNCTION === $token[0] ) {
				for ( $j = $i + 1; $j < $count; $j++ ) {
					if ( is_array( $body[ $j ] ) && T_STRING === $body[ $j ][0] ) {
						$methods[] = $body[ $j ][1];
						break;
					}
					if ( '(' === $body[ $j ] ) {
						break; // closure
					}
				}
				continue;
			}

			// $this -> name (
			if ( is_array( $token ) && T_VARIABLE === $token[0] && '$this' === $token[1] ) {
				$arrow = null;
				for ( $j = $i + 1; $j < $count; $j++ ) {
					if ( is_array( $body[ $j ] ) && T_WHITESPACE === $body[ $j ][0] ) {
						continue;
					}
					$arrow = $body[ $j ];
					$k     = $j;
					break;
				}
				if ( ! is_array( $arrow ) || T_OBJECT_OPERATOR !== $arrow[0] ) {
					continue;
				}
				$name_token = null;
				for ( $j = $k + 1; $j < $count; $j++ ) {
					if ( is_array( $body[ $j ] ) && T_WHITESPACE === $body[ $j ][0] ) {
						continue;
					}
					$name_token = $body[ $j ];
					$k          = $j;
					break;
				}
				if ( ! is_array( $name_token ) || T_STRING !== $name_token[0] ) {
					continue;
				}
				// Only a call, not a property read.
				for ( $j = $k + 1; $j < $count; $j++ ) {
					if ( is_array( $body[ $j ] ) && T_WHITESPACE === $body[ $j ][0] ) {
						continue;
					}
					if ( '(' === $body[ $j ] ) {
						$calls[ $name_token[1] ] = $name_token[2];
					}
					break;
				}
			}
		}

		$result[] = array(
			'name'    => $class['name'],
			'methods' => array_values( array_unique( $methods ) ),
			'calls'   => $calls,
		);
	}

	return $result;
}

echo "Self-call resolution\n";

foreach ( $targets as $relative ) {
	$path = $root . '/' . $relative;
	if ( ! is_readable( $path ) ) {
		check( $relative, false, 'file not readable' );
		continue;
	}

	$classes = scan_classes( file_get_contents( $path ) );
	if ( empty( $classes ) ) {
		check( $relative, false, 'no class found - the scanner would silently pass' );
		continue;
	}

	foreach ( $classes as $class ) {
		$unresolved = array();
		foreach ( $class['calls'] as $call => $line ) {
			if ( in_array( $call, $class['methods'], true ) || in_array( $call, $inherited_allowlist, true ) ) {
				continue;
			}
			$unresolved[] = $call . '() at ' . $relative . ':' . $line;
		}

		check(
			$relative . ' :: ' . $class['name'] . ' (' . count( $class['calls'] ) . ' self-calls)',
			empty( $unresolved ),
			implode( ', ', $unresolved )
		);
	}
}

echo "\nThe scanner itself\n";

// A scanner that never fails is worth nothing, so prove it still catches the
// exact shape of the bug it was written for.
$fixture = '<?php class Efb_Fixture { public function a() { return $this->missing_efb(); } public function b() { return $this->a(); } }';
$scanned = scan_classes( $fixture );
check( 'fixture parsed as one class', 1 === count( $scanned ), 'got ' . count( $scanned ) );
check( 'unresolved call is detected', isset( $scanned[0]['calls']['missing_efb'] ) && ! in_array( 'missing_efb', $scanned[0]['methods'], true ) );
check( 'resolved call is not flagged', isset( $scanned[0]['calls']['a'] ) && in_array( 'a', $scanned[0]['methods'], true ) );

echo "\nRegression: the add-on blocked notice\n";

$functions_source = file_get_contents( $root . '/includes/functions.php' );
check(
	'notify_admin_addon_blocked_efb() no longer calls $this->get_addon_display_names_efb()',
	false === strpos( $functions_source, '$this->get_addon_display_names_efb(' )
);
check(
	'get_addon_recovery_label_efb() is declared',
	false !== strpos( $functions_source, 'function get_addon_recovery_label_efb' )
);

echo "\n" . str_repeat( '-', 60 ) . "\n";
echo "passed {$passed}  failed {$failed}\n";
exit( $failed > 0 ? 1 : 0 );
