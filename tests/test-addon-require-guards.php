<?php
/**
 * Regression tests for add-on require guards.
 *
 * Every core `require_once` of an add-on file must be guarded on that exact
 * file. Guarding the parent directory instead is not equivalent: a partial
 * install - the folder restored but a class file still missing - passes an
 * is_dir() guard and then fatals on the require. That state is the normal
 * outcome of an interrupted add-on download, which is precisely what the
 * recovery system exists to handle, and on the public form or a payment route
 * the fatal is shown to a visitor.
 *
 * Run: php tests/test-addon-require-guards.php
 */

$plugin_dir = str_replace( '\\', '/', dirname( __DIR__ ) );

$passed = 0;
$failed = 0;

function efb_guard_assert( $name, $actual, $expected ) {
	global $passed, $failed;
	if ( $actual === $expected ) {
		echo "[PASS] {$name}\n";
		$passed++;
		return;
	}
	echo "[FAIL] {$name}: expected " . var_export( $expected, true ) . ', got ' . var_export( $actual, true ) . "\n";
	$failed++;
}

/** Composer's own bootstrap ships with the plugin and is not an add-on file. */
function efb_is_addon_path( $path ) {
	return $path !== 'vendor/autoload.php' && strpos( $path, 'vendor/composer/' ) !== 0;
}

function efb_normalise_vendor_path( $path ) {
	return ltrim( str_replace( '\\', '/', $path ), '/' );
}

/** Every core PHP file. Requires inside an add-on are that add-on's own business. */
function efb_core_php_files( $plugin_dir ) {
	$files = array();
	if ( is_file( $plugin_dir . '/emsfb.php' ) ) {
		$files[] = $plugin_dir . '/emsfb.php';
	}
	$it = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $plugin_dir . '/includes' ) );
	foreach ( $it as $file ) {
		if ( $file->isFile() && strtolower( $file->getExtension() ) === 'php' ) {
			$files[] = str_replace( '\\', '/', $file->getPathname() );
		}
	}
	sort( $files );
	return $files;
}

/**
 * Literal add-on paths passed to $func on a single line, e.g.
 * file_exists(EMSFB_PLUGIN_DIRECTORY . "/vendor/paypal/paypalefb.php").
 */
function efb_paths_for_call( $line, $func ) {
	$found = array();
	$pattern = '/\b' . preg_quote( $func, '/' ) . '\s*\(\s*EMSFB_PLUGIN_DIRECTORY\s*\.\s*[\'"]([^\'"]*vendor\/[^\'"]*)[\'"]/i';
	if ( preg_match_all( $pattern, $line, $m ) ) {
		foreach ( $m[1] as $path ) {
			$found[] = efb_normalise_vendor_path( $path );
		}
	}
	return $found;
}

/** Same, for guards that test a local holding the path: file_exists( $path ). */
function efb_paths_for_var_call( $line, $func, $vars ) {
	$found = array();
	if ( empty( $vars ) ) {
		return $found;
	}
	$pattern = '/\b' . preg_quote( $func, '/' ) . '\s*\(\s*\$(\w+)\s*\)/i';
	if ( preg_match_all( $pattern, $line, $m ) ) {
		foreach ( $m[1] as $name ) {
			if ( isset( $vars[ $name ] ) ) {
				$found[] = $vars[ $name ];
			}
		}
	}
	return $found;
}

// ---------------------------------------------------------------------------
// Part A - structural audit of the shipped source.
// ---------------------------------------------------------------------------

echo "--- A. require guards in core source ---\n";

$guard_window = 25;
$sites        = array();

foreach ( efb_core_php_files( $plugin_dir ) as $file ) {
	$lines = file( $file, FILE_IGNORE_NEW_LINES );
	foreach ( $lines as $i => $line ) {
		if ( ! preg_match(
			'/\brequire(_once)?\s*\(?\s*EMSFB_PLUGIN_DIRECTORY\s*\.\s*[\'"]([^\'"]*vendor\/[^\'"]+)[\'"]/i',
			$line,
			$m
		) ) {
			continue;
		}

		$required = efb_normalise_vendor_path( $m[2] );
		if ( ! efb_is_addon_path( $required ) ) {
			continue;
		}

		$start = max( 0, $i - $guard_window );

		// Some guards hold the path in a local first: $path = EMSFB_PLUGIN_DIRECTORY
		// . "/vendor/..."; if(!file_exists($path)). Resolve those before matching.
		$vars = array();
		for ( $j = $start; $j < $i; $j++ ) {
			if ( preg_match_all(
				'/\$(\w+)\s*=\s*EMSFB_PLUGIN_DIRECTORY\s*\.\s*[\'"]([^\'"]*vendor\/[^\'"]*)[\'"]/',
				$lines[ $j ],
				$vm,
				PREG_SET_ORDER
			) ) {
				foreach ( $vm as $v ) {
					$vars[ $v[1] ] = efb_normalise_vendor_path( $v[2] );
				}
			}
		}

		$file_guards = array();
		$dir_guards  = array();
		for ( $j = $start; $j < $i; $j++ ) {
			foreach ( array( 'file_exists', 'is_readable' ) as $func ) {
				$file_guards = array_merge( $file_guards, efb_paths_for_call( $lines[ $j ], $func ) );
				$file_guards = array_merge( $file_guards, efb_paths_for_var_call( $lines[ $j ], $func, $vars ) );
			}
			$dir_guards = array_merge( $dir_guards, efb_paths_for_call( $lines[ $j ], 'is_dir' ) );
			$dir_guards = array_merge( $dir_guards, efb_paths_for_var_call( $lines[ $j ], 'is_dir', $vars ) );
		}

		$sites[] = array(
			'file'        => ltrim( str_replace( $plugin_dir, '', $file ), '/' ),
			'line'        => $i + 1,
			'required'    => $required,
			'file_guards' => $file_guards,
			'dir_guards'  => $dir_guards,
		);
	}
}

efb_guard_assert( 'found add-on require sites to audit', count( $sites ) > 0, true );

foreach ( $sites as $site ) {
	$label = "{$site['file']}:{$site['line']} requires {$site['required']}";

	efb_guard_assert( "{$label} - guarded on the required file", in_array( $site['required'], $site['file_guards'], true ), true );

	// The specific defect: a directory guard standing in for a file guard.
	$parent          = rtrim( dirname( $site['required'] ), '/' );
	$dir_only_guard  = false;
	foreach ( $site['dir_guards'] as $dir_guard ) {
		if ( rtrim( $dir_guard, '/' ) === $parent && ! in_array( $site['required'], $site['file_guards'], true ) ) {
			$dir_only_guard = true;
		}
	}
	efb_guard_assert( "{$label} - not guarded by its parent directory alone", $dir_only_guard, false );
}

// ---------------------------------------------------------------------------
// Part B - the three filesystem states each guard has to separate.
// ---------------------------------------------------------------------------

echo "\n--- B. filesystem scenarios per guarded add-on file ---\n";

$tmp_root = rtrim( str_replace( '\\', '/', sys_get_temp_dir() ), '/' ) . '/efb-guard-test-' . getmypid();

function efb_rmtree( $path ) {
	if ( ! is_dir( $path ) ) {
		if ( is_file( $path ) ) {
			unlink( $path );
		}
		return;
	}
	foreach ( array_diff( scandir( $path ), array( '.', '..' ) ) as $entry ) {
		efb_rmtree( $path . '/' . $entry );
	}
	rmdir( $path );
}

/** Recreate $root in one of the three states an add-on directory can be in. */
function efb_build_state( $root, $relative_file, $state ) {
	efb_rmtree( $root );
	mkdir( $root, 0777, true );
	$full = $root . '/' . $relative_file;
	if ( $state === 'absent' ) {
		return $full;
	}
	mkdir( dirname( $full ), 0777, true );
	if ( $state === 'complete' ) {
		file_put_contents( $full, "<?php\n" );
	}
	return $full;
}

$required_paths = array();
foreach ( $sites as $site ) {
	$required_paths[ $site['required'] ] = true;
}
$required_paths = array_keys( $required_paths );
sort( $required_paths );

efb_guard_assert( 'distinct add-on files to exercise', count( $required_paths ) > 0, true );

foreach ( $required_paths as $relative_file ) {
	$parent_dir = dirname( $relative_file );

	// 1. Add-on fully installed: the require must run.
	$full = efb_build_state( $tmp_root, $relative_file, 'complete' );
	efb_guard_assert( "{$relative_file} [complete] - loads", file_exists( $full ), true );

	// 2. Partial install - directory restored, class file still missing. This is
	//    the state the old is_dir() guard let through into require_once.
	$full = efb_build_state( $tmp_root, $relative_file, 'partial' );
	efb_guard_assert( "{$relative_file} [partial] - falls back to recovery", file_exists( $full ), false );
	efb_guard_assert( "{$relative_file} [partial] - old is_dir guard would have loaded it", is_dir( $tmp_root . '/' . $parent_dir ), true );

	// 3. Add-on not installed at all: both guards agree.
	$full = efb_build_state( $tmp_root, $relative_file, 'absent' );
	efb_guard_assert( "{$relative_file} [absent] - falls back to recovery", file_exists( $full ), false );
	efb_guard_assert( "{$relative_file} [absent] - directory is gone too", is_dir( $tmp_root . '/' . $parent_dir ), false );
}

efb_rmtree( $tmp_root );

// ---------------------------------------------------------------------------
// Part C - the guarded paths are the ones the recovery system checks.
// ---------------------------------------------------------------------------

echo "\n--- C. guards agree with get_addon_required_files_efb() ---\n";

$functions_src = file_get_contents( $plugin_dir . '/includes/functions.php' );
$manifest      = array();
if ( preg_match( '/function get_addon_required_files_efb\(\)\s*\{(.*?)\n\t\}/s', $functions_src, $m ) ) {
	if ( preg_match_all( '/\'(Adn[A-Za-z]+)\'\s*=>\s*array\(\s*\'([^\']+)\'/', $m[1], $rows, PREG_SET_ORDER ) ) {
		foreach ( $rows as $row ) {
			$manifest[ $row[1] ] = efb_normalise_vendor_path( $row[2] );
		}
	}
}

efb_guard_assert( 'read the add-on manifest', count( $manifest ) > 0, true );

$manifest_paths = array_values( $manifest );
foreach ( $required_paths as $relative_file ) {
	// Only files the manifest tracks can be auto-recovered. A require of an
	// untracked file would leave the guard's recovery branch permanently stuck.
	efb_guard_assert(
		"{$relative_file} - recoverable (present in the add-on manifest)",
		in_array( $relative_file, $manifest_paths, true ),
		true
	);
}

echo "\n{$passed} passed, {$failed} failed\n";
exit( $failed > 0 ? 1 : 0 );
