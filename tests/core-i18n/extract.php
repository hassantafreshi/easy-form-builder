<?php
/**
 * Extract every phrase WordPress looks up in the easy-form-builder text domain,
 * and keep the phrase list up to date.
 *
 * Scans the core (every folder but vendor/, tests/, temp/, docs/, ...) and the
 * add-ons in vendor/ that still use the core domain, with `wp i18n make-pot`.
 * Then merges the result into docs/core-i18n/catalog.json:
 *   - a phrase seen for the first time is added (first_seen = this version);
 *   - a phrase no longer in the code becomes obsolete (obsolete_since = this version).
 *     It stays in the packs for this version only, so sites that have not
 *     updated yet keep its translation; the next version drops it;
 *   - everything else keeps its history.
 *
 * Writes:
 *   docs/core-i18n/catalog.json          the phrase list (committed)
 *   docs/core-i18n/easy-form-builder.pot the English reference: every phrase of this version's packs,
 *                                        with its context, plural, translators comment and references (committed)
 *   temp/core-i18n/source.json           what the packs of this version contain (check-translations.php format)
 *   temp/core-i18n/changes.json          new, obsolete and changed phrases of this run
 *
 * Run:
 *   php tests/core-i18n/extract.php [--version=4.2.1] [--wp-cli=C:/xampp/htdocs/ws/wp-cli.phar]
 *       [--root=...] [--catalog=...] [--pot=...] [--work=...] [--vendor-excludes="v4.1 backup,..."]
 */

require __DIR__ . '/lib.php';

$args = efb_ci18n_args(
	$argv,
	array(
		'version'         => '',
		'wp-cli'          => '',
		'php'             => PHP_BINARY,
		'vendor-excludes' => 'v4.1 backup,v4.1.2,v4.1.3,_logic,node_modules,composer,.git',
		'core-excludes'   => 'vendor,node_modules,tests,temp,_workspace,docs,languages,composer,.claude,.git',
		'main-file'       => 'emsfb.php',
	)
);
efb_ci18n_load_pomo( $args['wp'] );

// ---- version ----
$version = (string) $args['version'];
if ( '' === $version ) {
	$main = $args['root'] . $args['main-file'];
	$head = is_file( $main ) ? (string) file_get_contents( $main, false, null, 0, 8192 ) : '';
	if ( ! preg_match( '/^[ \t\/*#@]*Version:\s*(\S+)/mi', $head, $m ) ) {
		efb_ci18n_fail( 'Plugin version not found in ' . $main . ' (pass --version=)' );
	}
	$version = $m[1];
}

// ---- wp-cli ----
$wp_cli = (string) $args['wp-cli'];
if ( '' === $wp_cli ) {
	foreach ( array( dirname( rtrim( $args['wp'], '/' ) ) . '/ws/wp-cli.phar', $args['wp'] . 'wp-cli.phar' ) as $candidate ) {
		if ( is_file( $candidate ) ) {
			$wp_cli = $candidate;
			break;
		}
	}
}
if ( '' === $wp_cli || ! is_file( $wp_cli ) ) {
	efb_ci18n_fail( 'wp-cli.phar not found (pass --wp-cli=)' );
}

if ( ! is_dir( $args['work'] ) ) {
	mkdir( $args['work'], 0755, true );
}

/**
 * Run make-pot and return the parsed template.
 *
 * @param array  $args     Arguments.
 * @param string $wp_cli   wp-cli.phar.
 * @param string $source   Directory to scan.
 * @param string $pot      Output file.
 * @param string $excludes Comma-separated folders.
 * @return PO
 */
function efb_ci18n_make_pot( array $args, $wp_cli, $source, $pot, $excludes ) {
	@unlink( $pot );
	$cmd = escapeshellarg( $args['php'] ) . ' -d memory_limit=1024M ' . escapeshellarg( $wp_cli )
		. ' i18n make-pot ' . escapeshellarg( rtrim( $source, '/' ) ) . ' ' . escapeshellarg( $pot )
		. ' --domain=' . EFB_CI18N_DOMAIN . ' --exclude=' . escapeshellarg( $excludes ) . ' --skip-audit 2>&1';
	exec( $cmd, $output, $code );
	if ( 0 !== $code || ! is_file( $pot ) ) {
		efb_ci18n_fail( "make-pot failed for {$source}:\n" . implode( "\n", $output ) );
	}
	$po = new PO();
	if ( ! $po->import_from_file( $pot ) ) {
		efb_ci18n_fail( 'Unreadable ' . $pot );
	}
	return $po;
}

$found = array();

/**
 * @param array  $found  Found entries, updated in place.
 * @param PO     $po     Template.
 * @param string $prefix Prefix for the references (vendor/ for add-ons).
 * @return void
 */
function efb_ci18n_collect( array &$found, PO $po, $prefix ) {
	foreach ( $po->entries as $entry ) {
		if ( '' === $entry->singular ) {
			continue;
		}
		$id = efb_ci18n_id( $entry->context, $entry->singular );
		if ( ! isset( $found[ $id ] ) ) {
			$found[ $id ] = array(
				'msgid'       => $entry->singular,
				'context'     => null !== $entry->context && '' !== $entry->context ? $entry->context : null,
				'plural'      => null,
				'translators' => '',
				'refs'        => array(),
			);
		}
		if ( $entry->is_plural && null !== $entry->plural ) {
			$found[ $id ]['plural'] = $entry->plural;
		}
		if ( '' === $found[ $id ]['translators'] && preg_match( '/translators:\s*(.+)/is', (string) $entry->extracted_comments, $m ) ) {
			$found[ $id ]['translators'] = trim( preg_replace( '/\s+/', ' ', $m[1] ) );
		}
		foreach ( (array) $entry->references as $ref ) {
			$found[ $id ]['refs'][] = $prefix . str_replace( '\\', '/', $ref );
		}
	}
}

$core_pot  = efb_ci18n_make_pot( $args, $wp_cli, $args['root'], $args['work'] . 'core.pot', $args['core-excludes'] );
efb_ci18n_collect( $found, $core_pot, '' );
$addon_dir = $args['root'] . 'vendor';
if ( is_dir( $addon_dir ) ) {
	$addon_pot = efb_ci18n_make_pot( $args, $wp_cli, $addon_dir, $args['work'] . 'addons.pot', $args['vendor-excludes'] );
	efb_ci18n_collect( $found, $addon_pot, 'vendor/' );
}

// ---- merge into the catalog ----
$catalog  = efb_ci18n_json_read( $args['catalog'] );
$previous = array();
if ( is_array( $catalog ) && isset( $catalog['entries'] ) ) {
	foreach ( $catalog['entries'] as $entry ) {
		$previous[ efb_ci18n_id( $entry['context'], $entry['msgid'] ) ] = $entry;
	}
}

$entries = array();
$changes = array(
	'version'  => $version,
	'new'      => array(),
	'obsolete' => array(),
	'returned' => array(),
	'changed'  => array(),
);

foreach ( $found as $id => $f ) {
	$files = array();
	$scope = array();
	foreach ( $f['refs'] as $ref ) {
		$file           = preg_replace( '/:\d+$/', '', $ref );
		$files[ $file ] = true;
		$scope[ preg_match( '#^vendor/([^/]+)/#', $file, $m ) ? 'addon:' . $m[1] : 'core' ] = true;
	}
	ksort( $files );
	ksort( $scope );
	$old   = isset( $previous[ $id ] ) ? $previous[ $id ] : null;
	$entry = array(
		'msgid'          => $f['msgid'],
		'context'        => $f['context'],
		'plural'         => $f['plural'],
		'translators'    => $f['translators'],
		'files'          => array_keys( $files ),
		'scope'          => array_keys( $scope ),
		'first_seen'     => $old ? $old['first_seen'] : $version,
		'status'         => 'active',
		'obsolete_since' => null,
	);
	if ( ! $old ) {
		$changes['new'][] = $id;
	} else {
		if ( 'obsolete' === $old['status'] ) {
			$changes['returned'][] = $id;
		}
		if ( $old['plural'] !== $entry['plural'] || $old['translators'] !== $entry['translators'] ) {
			$changes['changed'][] = array(
				'id'          => $id,
				'plural'      => array( $old['plural'], $entry['plural'] ),
				'translators' => array( $old['translators'], $entry['translators'] ),
			);
		}
	}
	$entries[ $id ] = $entry;
}

foreach ( $previous as $id => $old ) {
	if ( isset( $entries[ $id ] ) ) {
		continue;
	}
	if ( 'active' === $old['status'] ) {
		$old['status']         = 'obsolete';
		$old['obsolete_since'] = $version;
		$changes['obsolete'][] = $id;
	}
	$entries[ $id ] = $old;
}

ksort( $entries, SORT_STRING );

$counts = array( 'active' => 0, 'obsolete' => 0 );
foreach ( $entries as $entry ) {
	$counts[ $entry['status'] ]++;
}

efb_ci18n_json_write(
	$args['catalog'],
	array(
		'domain'         => EFB_CI18N_DOMAIN,
		'about'          => 'Every phrase looked up in the easy-form-builder text domain: the core plus add-ons that still use that domain. Maintained by tests/core-i18n/extract.php (agent efb-core-i18n); do not edit by hand.',
		'plugin_version' => $version,
		'updated'        => gmdate( 'Y-m-d' ),
		'counts'         => $counts,
		'entries'        => array_values( $entries ),
	)
);

// ---- what the packs of this version contain ----
$source = array(
	'slug'    => 'core',
	'domain'  => EFB_CI18N_DOMAIN,
	'version' => $version,
	'entries' => array(),
);
foreach ( $entries as $id => $entry ) {
	// Obsolete phrases stay one version, for sites that have not updated yet.
	if ( 'obsolete' === $entry['status'] && $entry['obsolete_since'] !== $version ) {
		continue;
	}
	$source['entries'][] = array(
		'key'         => $id,
		'msgid'       => $entry['msgid'],
		'context'     => $entry['context'],
		'plural'      => $entry['plural'],
		'translators' => $entry['translators'],
		'refs'        => isset( $found[ $id ] ) ? array_slice( array_values( array_unique( $found[ $id ]['refs'] ) ), 0, 8 ) : $entry['files'],
		'scope'       => $entry['scope'],
		'status'      => $entry['status'],
	);
}
efb_ci18n_json_write( $args['work'] . 'source.json', $source );
efb_ci18n_json_write( $args['work'] . 'changes.json', $changes );
efb_ci18n_write_pot( $source, $args['pot'], gmdate( 'Y-m-d' ) );

printf(
	"version %s: %d phrases in this version's packs (%d active, %d obsolete kept); new %d, obsolete %d, returned %d, changed %d\ncatalog: %s\npot:     %s\nsource:  %s\n",
	$version,
	count( $source['entries'] ),
	$counts['active'],
	count( $source['entries'] ) - $counts['active'],
	count( $changes['new'] ),
	count( $changes['obsolete'] ),
	count( $changes['returned'] ),
	count( $changes['changed'] ),
	$args['catalog'],
	$args['pot'],
	$args['work'] . 'source.json'
);
