<?php
/**
 * Build the language packs from source.json + translations, and publish them.
 *
 * Build (always) into temp/core-i18n/build/, flat, so check-translations.php can
 * verify it: easy-form-builder.pot and, per complete locale,
 * easy-form-builder-<locale>.po | .mo | .l10n.php.
 *
 * --publish copies each built locale to where it is served from:
 *   destination "ws":      <ws storage>/easy-form-builder/<locale>/  .po .mo pack.json
 *                          (served by ws-language-plugins-handler; the site generates .l10n.php)
 *   destination "bundled": the plugin's languages/  .po .mo .l10n.php
 * A locale whose translations did not change is left as it is, so sites do not
 * download the same pack again (references and comments do not count as a change).
 *
 * A locale with a missing or invalid translation is not built. Exit 1 if any.
 *
 * Run:
 *   php tests/core-i18n/build.php [--only=de_AT,fr_FR] [--publish] [--allow-missing] [--date=YYYY-MM-DD]
 *   --allow-missing: skip locales that have no translations file yet instead of failing.
 */

require __DIR__ . '/lib.php';

$args = efb_ci18n_args(
	$argv,
	array(
		'only'          => '',
		'publish'       => false,
		'allow-missing' => false,
		'date'          => gmdate( 'Y-m-d' ),
	)
);
efb_ci18n_load_pomo( $args['wp'] );

$locales = efb_ci18n_locales( $args );
$source  = efb_ci18n_json_read( $args['work'] . 'source.json' );
if ( ! is_array( $source ) || empty( $source['entries'] ) ) {
	efb_ci18n_fail( 'Run extract.php first' );
}
$only = '' === $args['only'] || true === $args['only'] ? array() : array_filter( array_map( 'trim', explode( ',', $args['only'] ) ) );
foreach ( $only as $locale ) {
	if ( ! isset( $locales[ $locale ] ) ) {
		efb_ci18n_fail( "Locale {$locale} is not in " . $args['locales'] );
	}
}
$version   = (string) $source['version'];
$build_dir = $args['work'] . 'build/';
if ( ! is_dir( $build_dir ) ) {
	mkdir( $build_dir, 0755, true );
}

$common_headers = array(
	'Project-Id-Version'        => 'Easy Form Builder ' . $version,
	'Report-Msgid-Bugs-To'      => 'https://whitestudio.team/',
	'MIME-Version'              => '1.0',
	'Content-Type'              => 'text/plain; charset=UTF-8',
	'Content-Transfer-Encoding' => '8bit',
	'POT-Creation-Date'         => $args['date'] . ' 00:00+0000',
	'X-Generator'               => 'EFB tests/core-i18n/build.php',
	'X-Domain'                  => EFB_CI18N_DOMAIN,
);

/**
 * @param array             $e            Source entry.
 * @param string|array|null $translation  Translation, or null for the template.
 * @param string            $origin       Origin comment.
 * @return Translation_Entry
 */
function efb_ci18n_entry( array $e, $translation = null, $origin = '' ) {
	$data = array(
		'singular'           => $e['msgid'],
		'extracted_comments' => '' !== (string) $e['translators'] ? 'translators: ' . $e['translators'] : '',
		'references'         => (array) $e['refs'],
		'flags'              => preg_match( '/%(\d+\$)?[sdfu]/', $e['msgid'] ) ? array( 'php-format' ) : array(),
	);
	if ( null !== $e['context'] ) {
		$data['context'] = $e['context'];
	}
	if ( null !== $e['plural'] ) {
		$data['plural']    = $e['plural'];
		$data['is_plural'] = true;
	}
	if ( null !== $translation ) {
		$data['translations'] = (array) $translation;
	}
	if ( '' !== $origin ) {
		$data['translator_comments'] = 'origin: ' . $origin;
	}
	return new Translation_Entry( $data );
}

if ( empty( $only ) ) {
	$pot = new PO();
	$pot->set_headers(
		array_merge(
			$common_headers,
			array(
				'PO-Revision-Date' => 'YEAR-MO-DA HO:MI+ZONE',
				'Last-Translator'  => 'FULL NAME <EMAIL@ADDRESS>',
				'Language-Team'    => 'LANGUAGE <LL@li.org>',
			)
		)
	);
	foreach ( $source['entries'] as $e ) {
		$pot->add_entry( efb_ci18n_entry( $e ) );
	}
	$pot->export_to_file( $build_dir . EFB_CI18N_DOMAIN . '.pot' );
	echo 'pot: ' . count( $source['entries'] ) . " phrases\n";
}

$msgfmt = null;
foreach ( array( 'msgfmt', 'C:/Program Files/Git/usr/bin/msgfmt.exe' ) as $candidate ) {
	$probe = @shell_exec( '"' . $candidate . '" --version 2>&1' );
	if ( is_string( $probe ) && false !== stripos( $probe, 'msgfmt' ) ) {
		$msgfmt = $candidate;
		break;
	}
}

$errors  = array();
$summary = array();
foreach ( $locales as $locale => $row ) {
	if ( ! empty( $only ) && ! in_array( $locale, $only, true ) ) {
		continue;
	}
	$tr = efb_ci18n_json_read( $args['work'] . 'translations/' . $locale . '.json' );
	if ( ! is_array( $tr ) || ! isset( $tr['entries'] ) ) {
		$summary[ $locale ] = 'no translations';
		if ( ! empty( $only ) || ! $args['allow-missing'] ) {
			$errors[] = "{$locale}: no translations file (run prepare-locale.php and translate it)";
		}
		continue;
	}

	$problems = 0;
	foreach ( $source['entries'] as $e ) {
		$found = isset( $tr['entries'][ $e['key'] ] ) ? efb_ci18n_problems( $e, $tr['entries'][ $e['key'] ], $row['nplurals'], $locale ) : array( 'missing' );
		if ( ! empty( $found ) ) {
			$problems++;
			if ( $problems <= 5 ) {
				$errors[] = "{$locale} " . json_encode( $e['msgid'], JSON_UNESCAPED_UNICODE ) . ': ' . implode( '; ', $found );
			}
		}
	}
	if ( $problems > 0 ) {
		$errors[]           = "{$locale}: {$problems} phrases missing or invalid - not built";
		$summary[ $locale ] = "incomplete ({$problems})";
		continue;
	}

	$plural_forms = $row['plural_forms'];
	$export       = $args['work'] . 'wporg/' . $locale . '-stable.po';
	if ( is_file( $export ) && filesize( $export ) > 1000 ) {
		$export_po = new PO();
		if ( $export_po->import_from_file( $export ) && ! empty( $export_po->headers['Plural-Forms'] ) ) {
			$plural_forms = $export_po->headers['Plural-Forms'];
		}
	}

	$po = new PO();
	$po->set_headers(
		array_merge(
			$common_headers,
			array(
				'PO-Revision-Date' => gmdate( 'Y-m-d H:i' ) . '+0000',
				'Last-Translator'  => 'WhiteStudio <hi@whitestudio.team>',
				'Language-Team'    => $locale,
				'Language'         => $locale,
				'Plural-Forms'     => $plural_forms,
			)
		)
	);
	foreach ( $source['entries'] as $e ) {
		$po->add_entry( efb_ci18n_entry( $e, $tr['entries'][ $e['key'] ], isset( $tr['origin'][ $e['key'] ] ) ? $tr['origin'][ $e['key'] ] : '' ) );
	}
	$base = $build_dir . EFB_CI18N_DOMAIN . '-' . $locale;
	$po->export_to_file( $base . '.po' );

	if ( null !== $msgfmt ) {
		exec( '"' . $msgfmt . '" --check-format -o "' . $base . '.mo" "' . $base . '.po" 2>&1', $output, $code );
		if ( 0 !== $code ) {
			$errors[]           = "{$locale}: msgfmt failed: " . implode( ' ', (array) $output );
			$summary[ $locale ] = 'msgfmt failed';
			unset( $output );
			continue;
		}
		unset( $output );
	} else {
		$mo = new MO();
		$mo->set_headers( $po->headers );
		foreach ( $po->entries as $entry ) {
			$mo->add_entry( $entry );
		}
		$mo->export_to_file( $base . '.mo' );
	}
	$php = WP_Translation_File::transform( $base . '.mo', 'php' );
	if ( false === $php ) {
		$errors[]           = "{$locale}: .l10n.php could not be generated";
		$summary[ $locale ] = '.l10n.php failed';
		continue;
	}
	file_put_contents( $base . '.l10n.php', $php );
	$summary[ $locale ] = 'built';

	if ( ! $args['publish'] ) {
		continue;
	}

	$dest      = efb_ci18n_published_dir( $args, $row );
	$published = $dest . EFB_CI18N_DOMAIN . '-' . $locale . '.po';
	$before    = array();
	foreach ( efb_ci18n_read_po( $published ) as $id => $item ) {
		$before[ $id ] = $item['msgstr'];
	}
	$after = array();
	foreach ( $source['entries'] as $e ) {
		$after[ $e['key'] ] = $tr['entries'][ $e['key'] ];
	}
	ksort( $before );
	ksort( $after );
	if ( $before === $after && is_file( $dest . EFB_CI18N_DOMAIN . '-' . $locale . '.mo' ) ) {
		$summary[ $locale ] = 'unchanged';
		continue;
	}

	if ( ! is_dir( $dest ) ) {
		mkdir( $dest, 0755, true );
	}
	if ( 'ws' === $row['destination'] ) {
		$product_dir = dirname( rtrim( $dest, '/' ) ) . '/';
		if ( ! is_file( $product_dir . 'index.php' ) ) {
			file_put_contents( $product_dir . 'index.php', "<?php\n// Silence is golden.\n" );
		}
	}
	$files = 'bundled' === $row['destination'] ? array( '.po', '.mo', '.l10n.php' ) : array( '.po', '.mo' );
	foreach ( $files as $ext ) {
		copy( $base . $ext, $dest . EFB_CI18N_DOMAIN . '-' . $locale . $ext );
	}
	if ( 'ws' === $row['destination'] ) {
		efb_ci18n_json_write(
			$dest . 'pack.json',
			array(
				'version' => $version,
				'updated' => gmdate( 'Y-m-d H:i:s' ),
			)
		);
	}
	$summary[ $locale ] = 'published to ' . $row['destination'];
}

foreach ( $summary as $locale => $state ) {
	echo "{$locale}: {$state}\n";
}
foreach ( $errors as $error ) {
	echo "[ERROR] {$error}\n";
}
exit( empty( $errors ) ? 0 : 1 );
