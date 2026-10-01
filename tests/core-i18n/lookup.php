<?php
/**
 * How is a phrase or a term already translated in one locale? For consistent wording.
 * Read-only; uses only what prepare-locale.php downloaded and this run's translations.
 *
 * Sources (--in, default all, in this order):
 *   efb   approved easy-form-builder strings on wordpress.org (stable, dev)
 *   core  WordPress core for this locale (admin, then front end)
 *   gloss the locale team's official WordPress glossary
 *   run   this run's translations (temp/core-i18n/translations/<locale>.json), with each origin
 *
 * Run:
 *   php tests/core-i18n/lookup.php --locale=de_DE --search="custom field" [--in=core,gloss] [--limit=20]
 *       whole words, case-insensitive, in the English text; shortest matches first
 *   php tests/core-i18n/lookup.php --locale=de_DE --search="Eintrag" --target
 *       the same, in the translations (a substring: catches inflected forms)
 *   php tests/core-i18n/lookup.php --locale=de_DE --msgid="Save Changes"
 *       the exact msgid (with --context=... for a phrase with context)
 *   php tests/core-i18n/lookup.php --locale=de_DE --search=form --in=run --expect="Formular" --limit=100
 *       consistency sweep: only the matches whose translation contains none of the expected
 *       words ("|"-separated stems, case-insensitive; a stem catches inflected forms)
 *   php tests/core-i18n/lookup.php --locale=de_DE --calibrate
 *       how this locale's WordPress core translators write: address (formal or not), commands,
 *       key terms, typography (colon, quotes, ellipsis, parentheses) and a plural - the evidence
 *       for the locale profile
 */

require __DIR__ . '/lib.php';

$args = efb_ci18n_args(
	$argv,
	array(
		'locale'  => '',
		'search'  => '',
		'msgid'   => '',
		'context' => '',
		'target'    => false,
		'expect'    => '',
		'in'        => 'efb,core,gloss,run',
		'limit'     => 20,
		'calibrate' => false,
	)
);
efb_ci18n_load_pomo( $args['wp'] );

$locales = efb_ci18n_locales( $args );
$locale  = (string) $args['locale'];
if ( ! isset( $locales[ $locale ] ) ) {
	efb_ci18n_fail( "Locale {$locale} is not in " . $args['locales'] );
}

if ( $args['calibrate'] ) {
	$core_file = $args['work'] . 'wporg/' . $locale;
	$core      = efb_ci18n_read_po( $core_file . '-wp-admin.po' ) + efb_ci18n_read_po( $core_file . '-wp-core.po' );
	if ( empty( $core ) ) {
		efb_ci18n_fail( "No WordPress core translations for {$locale}: run prepare-locale.php --locale={$locale} first" );
	}
	$groups = array(
		'address and sentences' => array( 'Are you sure you want to do this?', 'Please try again.', 'An error occurred. Please try again.', 'Please enter a valid email address.', 'Sorry, you are not allowed to access this page.', 'Your email address will not be published.', 'Required fields are marked %s', 'Lost your password?', 'Settings saved.', 'Dismiss this notice.' ),
		'commands'              => array( 'Save Changes', 'Add New', 'Delete Permanently', 'Cancel', 'Update', 'Preview', 'Apply', 'Close', 'Submit', 'Select', 'Enable', 'Disable', 'Activate', 'Learn more', 'Get started', 'Read more', 'Log in', 'Copy', 'Copied!', "verb\4Upload" ),
		'terms'                 => array( 'Settings', 'Dashboard', 'Plugin', 'Plugins', 'Shortcode', 'Block', 'Template', 'Email', 'Username', 'Password', 'Status', 'Actions', 'Label', 'Placeholder', 'Response', 'Responses', 'Notifications', 'Required', 'Default', 'Custom', 'Advanced', 'Enabled', 'Disabled', 'Free' ),
		'typography and plural' => array( 'Loading&hellip;', 'Error: %s', 'Edit &#8220;%s&#8221;', '(opens in a new tab)', '%s ago', '%s item' ),
	);
	foreach ( $groups as $title => $ids ) {
		echo "== {$title}\n";
		foreach ( $ids as $id ) {
			$english = false !== strpos( $id, "\4" ) ? substr( $id, strpos( $id, "\4" ) + 1 ) . ' (context: ' . strstr( $id, "\4", true ) . ')' : $id;
			$msgstr  = isset( $core[ $id ] ) ? json_encode( $core[ $id ]['msgstr'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) : '(not translated in core)';
			echo json_encode( $english, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) . " => {$msgstr}\n";
		}
	}
	exit( 0 );
}
// Not trimmed: with --target, " the " (spaces included) finds an English word left in a translation.
$search = true === $args['search'] ? '' : (string) $args['search'];
$msgid  = true === $args['msgid'] ? '' : (string) $args['msgid'];
if ( '' === trim( $search ) && '' === $msgid ) {
	efb_ci18n_fail( 'Pass --search="term" or --msgid="exact phrase"' );
}
$exact = '' !== $msgid ? efb_ci18n_id( '' !== (string) $args['context'] && true !== $args['context'] ? (string) $args['context'] : null, $msgid ) : null;
$limit = max( 1, (int) $args['limit'] );
$wporg = $args['work'] . 'wporg/' . $locale;

/**
 * @param string       $id     Entry id.
 * @param string|array $msgstr Translation.
 * @return bool
 */
$matches = function ( $id, $msgstr ) use ( $exact, $search, $args ) {
	if ( null !== $exact ) {
		return $id === $exact;
	}
	if ( $args['target'] ) {
		$text = implode( ' | ', (array) $msgstr );
		return function_exists( 'mb_stripos' ) ? false !== mb_stripos( $text, $search ) : false !== stripos( $text, $search );
	}
	$english = false !== strpos( $id, "\4" ) ? substr( $id, strpos( $id, "\4" ) + 1 ) : $id;
	return false !== stripos( $english, $search ) && preg_match( '/(?<![\p{L}\p{N}])' . preg_quote( $search, '/' ) . '(?![\p{L}\p{N}])/iu', $english );
};

/**
 * @param string $id Entry id.
 * @return string "msgid" or "msgid (context: ...)".
 */
$label = function ( $id ) {
	if ( false === strpos( $id, "\4" ) ) {
		return $id;
	}
	list( $context, $english ) = explode( "\4", $id, 2 );
	return $english . ' (context: ' . $context . ')';
};

$sources = array(
	'efb'  => function () use ( $wporg ) {
		return efb_ci18n_read_po( $wporg . '-stable.po' ) + efb_ci18n_read_po( $wporg . '-dev.po' );
	},
	'core' => function () use ( $wporg ) {
		return efb_ci18n_read_po( $wporg . '-wp-admin.po' ) + efb_ci18n_read_po( $wporg . '-wp-core.po' );
	},
	'run'  => function () use ( $args, $locale ) {
		$tr  = efb_ci18n_json_read( $args['work'] . 'translations/' . $locale . '.json' );
		$out = array();
		foreach ( is_array( $tr ) && isset( $tr['entries'] ) ? $tr['entries'] : array() as $id => $msgstr ) {
			$out[ $id ] = array(
				'msgstr' => $msgstr,
				'origin' => isset( $tr['origin'][ $id ] ) ? $tr['origin'][ $id ] : '',
			);
		}
		return $out;
	},
);

$wanted = array_filter( array_map( 'trim', explode( ',', (string) $args['in'] ) ) );
$total  = 0;
foreach ( $wanted as $name ) {
	if ( 'gloss' === $name ) {
		$hits = array();
		foreach ( efb_ci18n_read_glossary( $wporg . '-glossary.csv' ) as $row ) {
			if ( null !== $exact ) {
				$found = 0 === strcasecmp( $row[0], $msgid );
			} elseif ( $args['target'] ) {
				$found = false !== ( function_exists( 'mb_stripos' ) ? mb_stripos( $row[1], $search ) : stripos( $row[1], $search ) );
			} else {
				// The term contains the search ("field" finds "custom field"), or the search contains the term as a word.
				$found = false !== stripos( $row[0], $search ) || preg_match( '/(?<![\p{L}\p{N}])' . preg_quote( $row[0], '/' ) . '(?![\p{L}\p{N}])/iu', $search );
			}
			if ( $found ) {
				$hits[] = "[gloss] {$row[0]}" . ( '' !== $row[2] ? " ({$row[2]})" : '' ) . " => {$row[1]}" . ( '' !== $row[3] ? " -- {$row[3]}" : '' );
			}
		}
		$shown  = array_slice( $hits, 0, $limit );
		$total += count( $hits );
		echo implode( "\n", $shown ) . ( empty( $shown ) ? "[gloss] no match\n" : "\n" );
		continue;
	}
	if ( ! isset( $sources[ $name ] ) ) {
		efb_ci18n_fail( "Unknown source {$name} (efb, core, gloss, run)" );
	}
	$hits    = array();
	$stems   = array_filter( array_map( 'trim', explode( '|', true === $args['expect'] ? '' : (string) $args['expect'] ) ), 'strlen' );
	$matched = 0;
	foreach ( $sources[ $name ]() as $id => $item ) {
		if ( ! $matches( (string) $id, $item['msgstr'] ) ) {
			continue;
		}
		$matched++;
		$text = implode( ' | ', (array) $item['msgstr'] );
		foreach ( $stems as $stem ) {
			if ( false !== ( function_exists( 'mb_stripos' ) ? mb_stripos( $text, $stem ) : stripos( $text, $stem ) ) ) {
				continue 2;
			}
		}
		$hits[ (string) $id ] = $item;
	}
	if ( ! empty( $stems ) ) {
		echo "[{$name}] " . count( $hits ) . " of {$matched} matches contain none of: " . implode( ', ', $stems ) . "\n";
	}
	uksort(
		$hits,
		function ( $a, $b ) {
			return strlen( $a ) - strlen( $b ) ?: strcmp( $a, $b );
		}
	);
	$total += count( $hits );
	if ( empty( $hits ) ) {
		echo "[{$name}] no match\n";
		continue;
	}
	foreach ( array_slice( $hits, 0, $limit, true ) as $id => $item ) {
		$tag = 'run' === $name && '' !== $item['origin'] ? 'run: ' . $item['origin'] : $name;
		echo "[{$tag}] " . json_encode( $label( (string) $id ), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) . ' => ' . json_encode( $item['msgstr'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) . "\n";
	}
	if ( count( $hits ) > $limit ) {
		echo "[{$name}] ... " . ( count( $hits ) - $limit ) . " more (--limit=)\n";
	}
}
echo "{$total} matches\n";
