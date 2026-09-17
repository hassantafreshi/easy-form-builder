<?php
/**
 * Tests for the core translation tools (tests/core-i18n/), on a fixture plugin.
 *
 * Runs every tool the way efb-core-i18n and efb-core-i18n-translator do -
 * extract, prepare-locale, todo, merge, build, publish, status - against a tiny
 * plugin, a fake WhiteStudio storage and fake wordpress.org exports in a
 * temporary folder, then checks the result with the shared
 * tests/addon-i18n/check-translations.php. Nothing outside that folder is touched.
 *
 * Needs wp-cli.phar (C:/xampp/htdocs/ws/wp-cli.phar) and WordPress at C:/xampp/htdocs/wp.
 *
 * Run: C:\xampp\php\php.exe tests/core-i18n/test-core-i18n-tools.php
 */

require __DIR__ . '/lib.php';

$tools = __DIR__ . '/';
$root  = str_replace( '\\', '/', dirname( __DIR__, 2 ) ) . '/';
$wp    = str_replace( '\\', '/', dirname( $root, 4 ) ) . '/wp/';
if ( ! is_file( $wp . 'wp-includes/pomo/po.php' ) || ! is_file( dirname( rtrim( $wp, '/' ) ) . '/ws/wp-cli.phar' ) ) {
	echo "[SKIP] WordPress or wp-cli.phar not found\n";
	exit( 0 );
}
efb_ci18n_load_pomo( $wp );

$pass     = 0;
$fail     = 0;
$failures = array();

/**
 * @param string $name  Name.
 * @param bool   $cond  Result.
 * @param string $extra Context on failure.
 * @return void
 */
function efb_t( $name, $cond, $extra = '' ) {
	global $pass, $fail, $failures;
	if ( $cond ) {
		$pass++;
		echo "  [PASS] {$name}\n";
		return;
	}
	$fail++;
	$failures[] = $name . ( '' !== $extra ? ' -- ' . $extra : '' );
	echo "  [FAIL] {$name}" . ( '' !== $extra ? " -- {$extra}" : '' ) . "\n";
}

$fx = str_replace( '\\', '/', sys_get_temp_dir() ) . '/efb-core-i18n-test-' . substr( md5( uniqid( '', true ) ), 0, 8 ) . '/';
$p  = array(
	'plugin'  => $fx . 'plugin/',
	'work'    => $fx . 'work/',
	'ws'      => $fx . 'ws-lang/',
	'catalog' => $fx . 'catalog.json',
	'locales' => $fx . 'locales.json',
	'localpo' => $fx . 'no-local-po/',
);

/**
 * @param string $path     File.
 * @param string $contents Contents.
 * @return void
 */
function efb_fx_write( $path, $contents ) {
	if ( ! is_dir( dirname( $path ) ) ) {
		mkdir( dirname( $path ), 0755, true );
	}
	file_put_contents( $path, $contents );
}

/**
 * Run a tool with the fixture paths.
 *
 * @param string $tool  Script name.
 * @param string $extra More arguments.
 * @return array{code: int, out: string}
 */
function efb_fx_run( $tool, $extra = '' ) {
	global $tools, $p, $wp;
	$cmd = escapeshellarg( PHP_BINARY ) . ' ' . escapeshellarg( $tools . $tool )
		. ' --root=' . escapeshellarg( $p['plugin'] ) . ' --work=' . escapeshellarg( $p['work'] )
		. ' --catalog=' . escapeshellarg( $p['catalog'] ) . ' --locales=' . escapeshellarg( $p['locales'] )
		. ' --bundled=' . escapeshellarg( $p['plugin'] . 'languages/' ) . ' --ws-storage=' . escapeshellarg( $p['ws'] )
		. ' --wp=' . escapeshellarg( $wp ) . ' ' . $extra . ' 2>&1';
	exec( $cmd, $out, $code );
	return array(
		'code' => $code,
		'out'  => implode( "\n", $out ),
	);
}

/**
 * @param string $file JSON file.
 * @return array
 */
function efb_fx_json( $file ) {
	$data = efb_ci18n_json_read( $file );
	return is_array( $data ) ? $data : array();
}

/**
 * @param array  $catalog Catalog.
 * @param string $id      Entry id.
 * @return array|null
 */
function efb_fx_entry( array $catalog, $id ) {
	foreach ( isset( $catalog['entries'] ) ? $catalog['entries'] : array() as $entry ) {
		if ( efb_ci18n_id( $entry['context'], $entry['msgid'] ) === $id ) {
			return $entry;
		}
	}
	return null;
}

/**
 * @param string $locale Locale.
 * @param array  $data   Chunk.
 * @param string $extra  More arguments.
 * @return array{code: int, out: string}
 */
function efb_fx_merge( $locale, array $data, $extra = '' ) {
	global $p;
	$file = $p['work'] . 'chunk-' . $locale . '-' . substr( md5( json_encode( $data ) ), 0, 6 ) . '.json';
	efb_ci18n_json_write( $file, $data );
	return efb_fx_run( 'merge.php', '--locale=' . $locale . ' --file=' . escapeshellarg( $file ) . ' ' . $extra );
}

/**
 * @param string $dir Directory.
 * @return void
 */
function efb_fx_rmdir( $dir ) {
	if ( ! is_dir( $dir ) ) {
		return;
	}
	foreach ( (array) scandir( $dir ) as $name ) {
		if ( '.' === $name || '..' === $name ) {
			continue;
		}
		$path = rtrim( $dir, '/' ) . '/' . $name;
		is_dir( $path ) ? efb_fx_rmdir( $path ) : unlink( $path );
	}
	rmdir( $dir );
}

/**
 * @param string $mo_file MO file.
 * @param string $id      Entry id.
 * @return string|array|null
 */
function efb_fx_mo( $mo_file, $id ) {
	$mo = new MO();
	if ( ! $mo->import_from_file( $mo_file ) ) {
		return null;
	}
	foreach ( $mo->entries as $entry ) {
		if ( efb_ci18n_id( $entry->context, $entry->singular ) === $id ) {
			return $entry->is_plural ? $entry->translations : $entry->translations[0];
		}
	}
	return null;
}

$code_v1 = <<<'PHP'
<?php
__( 'Save form', 'easy-form-builder' );
/* translators: %s: form name */
esc_html__( 'Hello %s', 'easy-form-builder' );
_x( 'Post', 'noun', 'easy-form-builder' );
_n( '%d form', '%d forms', 2, 'easy-form-builder' );
esc_html__( 'Loading&hellip;', 'easy-form-builder' );
__( 'Another plugin', 'another-plugin' );
PHP;

efb_fx_write( $p['plugin'] . 'emsfb.php', "<?php\n/**\n * Plugin Name: EFB i18n fixture\n * Version: 1.0.0\n * Text Domain: easy-form-builder\n */\n" );
efb_fx_write( $p['plugin'] . 'includes/a.php', $code_v1 );
efb_fx_write( $p['plugin'] . 'vendor/fooaddon/x.php', "<?php\n__( 'Add-on phrase', 'easy-form-builder' );\n" );
efb_fx_write( $p['plugin'] . 'vendor/v4.1.2/old.php', "<?php\n__( 'Backup phrase', 'easy-form-builder' );\n" );
efb_fx_write( $p['plugin'] . 'tests/t.php', "<?php\n__( 'Test phrase', 'easy-form-builder' );\n" );
efb_fx_write( $p['plugin'] . 'temp/t.php', "<?php\n__( 'Temp phrase', 'easy-form-builder' );\n" );
efb_fx_write( $p['plugin'] . 'languages/index.php', "<?php\n" );
mkdir( $p['localpo'], 0755, true );

efb_ci18n_json_write(
	$p['locales'],
	array(
		'locales' => array(
			array( 'locale' => 'de_DE', 'wporg' => 'de', 'set' => 'default', 'nplurals' => 2, 'plural_forms' => 'nplurals=2; plural=n != 1;', 'destination' => 'bundled' ),
			array( 'locale' => 'de_AT', 'wporg' => 'de-at', 'set' => 'default', 'nplurals' => 2, 'plural_forms' => 'nplurals=2; plural=n != 1;', 'parent' => 'de_DE', 'destination' => 'ws' ),
			array( 'locale' => 'pl_PL', 'wporg' => 'pl', 'set' => 'default', 'nplurals' => 3, 'plural_forms' => 'nplurals=3; plural=(n == 1) ? 0 : ((n % 10 >= 2 && n % 10 <= 4 && (n % 100 < 12 || n % 100 > 14)) ? 1 : 2);', 'destination' => 'ws' ),
		),
	)
);

$export_header = "msgid \"\"\nmsgstr \"\"\n\"Plural-Forms: nplurals=2; plural=n != 1;\\n\"\n\"X-Generator: GlotPress/4.1.0\\n\"\n\n";
efb_fx_write(
	$p['work'] . 'wporg/de_AT-stable.po',
	$export_header
	. "msgid \"Save form\"\nmsgstr \"Formular speichern (AT)\"\n\n"
	. "msgid \"Hello %s\"\nmsgstr \"Hallo\"\n\n"
	. "#, fuzzy\nmsgid \"Loading&hellip;\"\nmsgstr \"Laden fuzzy&hellip;\"\n\n"
	. "msgid \"%d form\"\nmsgid_plural \"%d forms\"\nmsgstr[0] \"%d Formular\"\nmsgstr[1] \"%d Formulare\"\n"
);
efb_fx_write(
	$p['work'] . 'wporg/pl_PL-stable.po',
	$export_header . "msgid \"%d form\"\nmsgid_plural \"%d forms\"\nmsgstr[0] \"%d formularz\"\nmsgstr[1] \"%d formularze\"\n"
);

$id_post   = "noun\4Post";
$no_dl     = '--no-download --local-po-dir=' . escapeshellarg( $p['localpo'] );
$de_full   = array(
	'EFB i18n fixture' => 'EFB i18n Testobjekt',
	'Save form'        => 'Formular speichern',
	'Hello %s'         => 'Hallo %s',
	$id_post           => 'Beitrag',
	'%d form'          => array( '%d Formular', '%d Formulare' ),
	'Loading&hellip;'  => 'Laden&hellip;',
	'Add-on phrase'    => 'Erweiterungstext',
);

try {
	// -----------------------------------------------------------------------
	echo "\nextract.php\n";
	// -----------------------------------------------------------------------

	$run     = efb_fx_run( 'extract.php', '--version=1.0.0' );
	$catalog = efb_fx_json( $p['catalog'] );
	$source  = efb_fx_json( $p['work'] . 'source.json' );
	efb_t( 'extract runs', 0 === $run['code'], $run['out'] );
	efb_t( 'every phrase of the text domain is listed, the plugin name included', 7 === count( $catalog['entries'] ) && 7 === count( $source['entries'] ), (string) count( isset( $catalog['entries'] ) ? $catalog['entries'] : array() ) );
	foreach ( array( 'Save form', 'Hello %s', $id_post, '%d form', 'Loading&hellip;', 'Add-on phrase', 'EFB i18n fixture' ) as $id ) {
		efb_t( 'listed: ' . json_encode( $id ), null !== efb_fx_entry( $catalog, $id ) );
	}
	foreach ( array( 'Another plugin', 'Backup phrase', 'Test phrase', 'Temp phrase' ) as $id ) {
		efb_t( 'not listed: ' . $id, null === efb_fx_entry( $catalog, $id ) );
	}
	$hello = efb_fx_entry( $catalog, 'Hello %s' );
	efb_t( 'the translators comment is kept', '%s: form name' === ( $hello ? $hello['translators'] : '' ), $hello ? $hello['translators'] : '' );
	$plural = efb_fx_entry( $catalog, '%d form' );
	efb_t( 'plural and context are kept', '%d forms' === ( $plural ? $plural['plural'] : '' ) && null !== efb_fx_entry( $catalog, $id_post ) );
	$addon = efb_fx_entry( $catalog, 'Add-on phrase' );
	efb_t( 'an add-on phrase in the core domain is marked with its add-on', array( 'addon:fooaddon' ) === ( $addon ? $addon['scope'] : null ) && array( 'vendor/fooaddon/x.php' ) === $addon['files'] );
	efb_t( 'each phrase records the version it first appeared in', '1.0.0' === ( $hello ? $hello['first_seen'] : '' ) );
	$save_refs = array();
	foreach ( $source['entries'] as $source_entry ) {
		if ( 'Save form' === $source_entry['key'] ) {
			$save_refs = $source_entry['refs'];
		}
	}
	efb_t( 'source.json carries file:line references for the translator', 1 === preg_match( '#^includes/a\.php:\d+$#', (string) ( $save_refs[0] ?? '' ) ), json_encode( $save_refs ) );

	// -----------------------------------------------------------------------
	echo "\nprepare-locale.php, todo.php, merge.php\n";
	// -----------------------------------------------------------------------

	$run  = efb_fx_run( 'prepare-locale.php', '--locale=de_DE ' . $no_dl );
	$todo = efb_fx_json( $p['work'] . 'locale/de_DE/todo.json' );
	efb_t( 'a first run puts every phrase on the to-do list', 0 === $run['code'] && 7 === count( $todo['todo'] ), $run['out'] );
	$run = efb_fx_run( 'todo.php', '--locale=de_DE --count' );
	efb_t( 'todo.php counts it', '7' === trim( $run['out'] ) );
	$run   = efb_fx_run( 'todo.php', '--locale=de_DE --limit=2 --offset=1' );
	$lines = array_filter( explode( "\n", $run['out'] ) );
	efb_t( 'todo.php prints a slice, one JSON object per line', 2 === count( $lines ) && is_array( json_decode( reset( $lines ), true ) ) );

	$run = efb_fx_merge(
		'de_DE',
		array(
			'entries' => array(
				'Hello %s'        => 'Hallo',
				'Loading&hellip;' => 'Laden…',
				'%d form'         => array( '%d Formular' ),
				'Save form'       => ' Formular speichern',
				'Unknown phrase'  => 'x',
			),
		)
	);
	efb_t( 'a lost placeholder is rejected', false !== strpos( $run['out'], 'placeholders differ' ) );
	efb_t( 'an entity written as a character is rejected', false !== strpos( $run['out'], 'entit' ) );
	efb_t( 'a plural with too few forms is rejected', false !== strpos( $run['out'], 'plural needs exactly 2 forms' ) );
	efb_t( 'added whitespace is rejected', false !== strpos( $run['out'], 'whitespace' ) );
	efb_t( 'a phrase that is not in this version is rejected', false !== strpos( $run['out'], 'not a phrase of this version' ) );
	efb_t( 'nothing invalid was merged', 7 === count( efb_fx_json( $p['work'] . 'locale/de_DE/todo.json' )['todo'] ) );

	$run = efb_fx_merge( 'de_DE', array( 'entries' => $de_full, 'origin' => array( 'Add-on phrase' => 'glossary' ) ) );
	$tr  = efb_fx_json( $p['work'] . 'translations/de_DE.json' );
	efb_t( 'valid translations are merged and leave the to-do list', false !== strpos( $run['out'], 'merged 7' ) && 0 === count( efb_fx_json( $p['work'] . 'locale/de_DE/todo.json' )['todo'] ), $run['out'] );
	efb_t( 'the origin is recorded (default: the translator)', 'sonnet (de_DE)' === $tr['origin']['Save form'] && 'glossary' === $tr['origin']['Add-on phrase'] );

	$run  = efb_fx_run( 'prepare-locale.php', '--locale=de_AT ' . $no_dl );
	$tr   = efb_fx_json( $p['work'] . 'translations/de_AT.json' );
	$todo = efb_fx_json( $p['work'] . 'locale/de_AT/todo.json' );
	$task = array();
	foreach ( $todo['todo'] as $t ) {
		$task[ $t['id'] ] = $t;
	}
	efb_t( 'an approved wordpress.org string is used verbatim', 'Formular speichern (AT)' === ( $tr['entries']['Save form'] ?? '' ) && 'wporg-stable' === ( $tr['origin']['Save form'] ?? '' ), $run['out'] );
	efb_t( 'an approved plural is used verbatim', array( '%d Formular', '%d Formulare' ) === ( $tr['entries']['%d form'] ?? null ) );
	efb_t( 'an approved string that drops a placeholder goes to the to-do list with a note', isset( $task['Hello %s']['note'] ) && 'Hallo' === $task['Hello %s']['approved_candidate'] );
	efb_t( 'a fuzzy approved string is ignored', isset( $task['Loading&hellip;'] ) && ! isset( $task['Loading&hellip;']['approved_candidate'] ) );
	efb_t( 'a variant sees its parent locale\'s wording', 'Hallo %s' === ( $task['Hello %s']['parent_msgstr'] ?? '' ) );
	efb_t( '...and translates the rest itself', 5 === count( $todo['todo'] ), (string) count( $todo['todo'] ) );

	$run = efb_fx_merge( 'de_AT', array( 'entries' => array( 'Save form' => 'Formular sichern' ) ) );
	efb_t( 'an approved string cannot be overwritten by a chunk', false !== strpos( $run['out'], 'not on the to-do list' ) );
	$run = efb_fx_merge(
		'de_AT',
		array(
			'entries' => array(
				'EFB i18n fixture' => 'EFB i18n Testobjekt',
				'Hello %s'         => 'Hallo %s',
				$id_post           => 'Beitrag',
				'Loading&hellip;'  => 'Laden&hellip;',
				'Add-on phrase'    => 'Erweiterungstext',
			),
			'origin'  => array( 'Hello %s' => 'manual-fix (was wporg-stable (UI-breaking token fixed))' ),
		)
	);
	efb_t( 'de_AT is complete', 0 === count( efb_fx_json( $p['work'] . 'locale/de_AT/todo.json' )['todo'] ), $run['out'] );

	$run  = efb_fx_run( 'prepare-locale.php', '--locale=pl_PL ' . $no_dl );
	$todo = efb_fx_json( $p['work'] . 'locale/pl_PL/todo.json' );
	$task = array();
	foreach ( $todo['todo'] as $t ) {
		$task[ $t['id'] ] = $t;
	}
	efb_t( 'an approved plural with 2 of 3 forms keeps its forms and asks for the third', array( 0, 1 ) === ( $task['%d form']['approved_forms'] ?? null ) );
	$pl = array(
		'EFB i18n fixture' => 'EFB i18n obiekt testowy',
		'Save form'        => 'Zapisz formularz',
		'Hello %s'         => 'Witaj %s',
		$id_post           => 'Wpis',
		'Loading&hellip;'  => 'Wczytywanie&hellip;',
		'Add-on phrase'    => 'Tekst dodatku',
	);
	$run = efb_fx_merge( 'pl_PL', array( 'entries' => array( '%d form' => array( '%d formularzy', '%d formularze', '%d formularzy' ) ) ) );
	efb_t( '...and refuses a change to an approved form', false !== strpos( $run['out'], 'approved form 0 must stay verbatim' ) );
	$run = efb_fx_merge( 'pl_PL', array( 'entries' => $pl + array( '%d form' => array( '%d formularz', '%d formularze', '%d formularzy' ) ) ) );
	$tr  = efb_fx_json( $p['work'] . 'translations/pl_PL.json' );
	efb_t( '...accepts the approved forms plus the missing one', 0 === count( efb_fx_json( $p['work'] . 'locale/pl_PL/todo.json' )['todo'] ) && array( 0, 1 ) === ( $tr['approved_forms']['%d form'] ?? null ), $run['out'] );

	// -----------------------------------------------------------------------
	echo "\nbuild.php and check-translations.php\n";
	// -----------------------------------------------------------------------

	$run = efb_fx_run( 'build.php' );
	efb_t( 'every complete locale builds', 0 === $run['code'], $run['out'] );
	$build_ok = true;
	foreach ( array( 'de_DE', 'de_AT', 'pl_PL' ) as $locale ) {
		foreach ( array( '.po', '.mo', '.l10n.php' ) as $ext ) {
			$build_ok = $build_ok && is_file( $p['work'] . 'build/easy-form-builder-' . $locale . $ext );
		}
	}
	efb_t( '...into .po, .mo and .l10n.php, plus the .pot', $build_ok && is_file( $p['work'] . 'build/easy-form-builder.pot' ) );
	efb_t( 'the .mo holds the translations', 'Formular speichern (AT)' === efb_fx_mo( $p['work'] . 'build/easy-form-builder-de_AT.mo', 'Save form' ) && array( '%d formularz', '%d formularze', '%d formularzy' ) === efb_fx_mo( $p['work'] . 'build/easy-form-builder-pl_PL.mo', '%d form' ) );

	$check = escapeshellarg( PHP_BINARY ) . ' ' . escapeshellarg( $root . 'tests/addon-i18n/check-translations.php' )
		. ' --source=' . escapeshellarg( $p['work'] . 'source.json' ) . ' --translations=' . escapeshellarg( $p['work'] . 'translations' )
		. ' --locales=' . escapeshellarg( $p['locales'] ) . ' --languages=' . escapeshellarg( $p['work'] . 'build' ) . ' --no-wp 2>&1';
	exec( $check, $check_out, $check_code );
	efb_t( 'the shared check-translations.php finds no error in the build', 0 === $check_code, implode( "\n", array_slice( $check_out, -6 ) ) );

	// -----------------------------------------------------------------------
	echo "\npublishing\n";
	// -----------------------------------------------------------------------

	$run = efb_fx_run( 'build.php', '--publish' );
	efb_t( 'publish runs', 0 === $run['code'], $run['out'] );
	efb_t( 'de_DE (shipped with the plugin) goes to the plugin languages/ folder with its .l10n.php', is_file( $p['plugin'] . 'languages/easy-form-builder-de_DE.mo' ) && is_file( $p['plugin'] . 'languages/easy-form-builder-de_DE.l10n.php' ) && ! is_dir( $p['ws'] . 'easy-form-builder/de_DE' ) );
	$ws_de_at = $p['ws'] . 'easy-form-builder/de_AT/';
	efb_t( 'de_AT goes to the WhiteStudio storage: .po, .mo and pack.json, no PHP', is_file( $ws_de_at . 'easy-form-builder-de_AT.po' ) && is_file( $ws_de_at . 'easy-form-builder-de_AT.mo' ) && is_file( $ws_de_at . 'pack.json' ) && ! is_file( $ws_de_at . 'easy-form-builder-de_AT.l10n.php' ) );
	$pack = efb_fx_json( $ws_de_at . 'pack.json' );
	efb_t( 'pack.json carries the plugin version', '1.0.0' === ( $pack['version'] ?? '' ) && (bool) preg_match( '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $pack['updated'] ?? '' ) );
	efb_t( 'the published .po remembers where each string came from', 1 === preg_match( '/^#\s+origin: wporg-stable$/m', (string) file_get_contents( $ws_de_at . 'easy-form-builder-de_AT.po' ) ) );
	efb_t( 'the storage product folder gets its silence file', is_file( $p['ws'] . 'easy-form-builder/index.php' ) );

	$mtime = filemtime( $ws_de_at . 'pack.json' );
	sleep( 1 );
	$run = efb_fx_run( 'build.php', '--publish' );
	efb_t( 'publishing unchanged translations changes nothing, so sites do not download again', false !== strpos( $run['out'], 'de_AT: unchanged' ) && $mtime === filemtime( $ws_de_at . 'pack.json' ), $run['out'] );

	// -----------------------------------------------------------------------
	echo "\nthe next release\n";
	// -----------------------------------------------------------------------

	unlink( $p['work'] . 'translations/de_AT.json' );
	$run = efb_fx_run( 'prepare-locale.php', '--locale=de_AT ' . $no_dl );
	$tr  = efb_fx_json( $p['work'] . 'translations/de_AT.json' );
	efb_t( 'a new run starts from the published pack', false !== strpos( $run['out'], 'seeded from: published pack' ) && 0 === count( efb_fx_json( $p['work'] . 'locale/de_AT/todo.json' )['todo'] ), $run['out'] );
	efb_t( '...and keeps each string\'s origin', 'manual-fix (was wporg-stable (UI-breaking token fixed))' === ( $tr['origin']['Hello %s'] ?? '' ) );

	efb_fx_write( $p['work'] . 'wporg/de_AT-stable.po', (string) file_get_contents( $p['work'] . 'wporg/de_AT-stable.po' ) . "\nmsgctxt \"noun\"\nmsgid \"Post\"\nmsgstr \"Beitrag (genehmigt)\"\n" );
	efb_fx_run( 'prepare-locale.php', '--locale=de_AT ' . $no_dl );
	$tr = efb_fx_json( $p['work'] . 'translations/de_AT.json' );
	efb_t( 'a string approved on wordpress.org later replaces the earlier translation', 'Beitrag (genehmigt)' === ( $tr['entries'][ $id_post ] ?? '' ) && 'wporg-stable' === ( $tr['origin'][ $id_post ] ?? '' ) );

	$run    = efb_fx_run( 'status.php', '--json' );
	$status = json_decode( $run['out'], true );
	efb_t( 'status.php orders variants after their parent', isset( $status['waves'] ) && in_array( 'de_DE', $status['waves'][1], true ) && in_array( 'de_AT', $status['waves'][2], true ), $run['out'] );
	efb_t( '...and sees every published pack complete', 0 === ( $status['locales']['de_AT']['missing'] ?? -1 ) && 0 === ( $status['locales']['de_DE']['missing'] ?? -1 ) );

	efb_fx_write( $p['plugin'] . 'includes/a.php', str_replace( "__( 'Save form', 'easy-form-builder' );", "__( 'New phrase', 'easy-form-builder' );", $code_v1 ) );
	$run     = efb_fx_run( 'extract.php', '--version=1.1.0' );
	$catalog = efb_fx_json( $p['catalog'] );
	$changes = efb_fx_json( $p['work'] . 'changes.json' );
	$source  = efb_fx_json( $p['work'] . 'source.json' );
	$keys    = array_column( $source['entries'], 'key' );
	efb_t( 'a new phrase is found and added', array( 'New phrase' ) === $changes['new'] && '1.1.0' === efb_fx_entry( $catalog, 'New phrase' )['first_seen'], $run['out'] );
	efb_t( 'a removed phrase becomes obsolete', array( 'Save form' ) === $changes['obsolete'] && 'obsolete' === efb_fx_entry( $catalog, 'Save form' )['status'] && '1.1.0' === efb_fx_entry( $catalog, 'Save form' )['obsolete_since'] );
	efb_t( '...but stays in this version\'s packs for sites that have not updated', in_array( 'Save form', $keys, true ) && in_array( 'New phrase', $keys, true ) );
	efb_t( 'older phrases keep the version they first appeared in', '1.0.0' === efb_fx_entry( $catalog, 'Hello %s' )['first_seen'] );

	efb_fx_run( 'prepare-locale.php', '--locale=de_AT ' . $no_dl );
	efb_t( 'only the new phrase needs translating', 1 === count( efb_fx_json( $p['work'] . 'locale/de_AT/todo.json' )['todo'] ) );
	$run = efb_fx_run( 'build.php', '--only=de_AT' );
	efb_t( 'a pack with an untranslated phrase is not built', 1 === $run['code'] && false !== strpos( $run['out'], 'not built' ), $run['out'] );

	efb_fx_run( 'extract.php', '--version=1.2.0' );
	$catalog = efb_fx_json( $p['catalog'] );
	$keys    = array_column( efb_fx_json( $p['work'] . 'source.json' )['entries'], 'key' );
	efb_t( 'one version later the obsolete phrase leaves the packs, but stays in the history', ! in_array( 'Save form', $keys, true ) && 'obsolete' === efb_fx_entry( $catalog, 'Save form' )['status'] && 1 === $catalog['counts']['obsolete'] );

	efb_fx_write( $p['plugin'] . 'includes/a.php', $code_v1 . "\n__( 'New phrase', 'easy-form-builder' );\n" );
	efb_fx_run( 'extract.php', '--version=1.3.0' );
	$catalog = efb_fx_json( $p['catalog'] );
	$changes = efb_fx_json( $p['work'] . 'changes.json' );
	efb_t( 'a phrase that comes back is active again with its original history', array( 'Save form' ) === $changes['returned'] && 'active' === efb_fx_entry( $catalog, 'Save form' )['status'] && '1.0.0' === efb_fx_entry( $catalog, 'Save form' )['first_seen'] );
} catch ( Throwable $e ) {
	efb_t( 'no exception escapes the test', false, get_class( $e ) . ': ' . $e->getMessage() . ' @ ' . $e->getLine() );
} finally {
	efb_fx_rmdir( $fx );
}

echo "\n{$pass} passed, {$fail} failed\n";
if ( $fail > 0 ) {
	echo "\nFailures:\n  - " . implode( "\n  - ", $failures ) . "\n";
	exit( 1 );
}
exit( 0 );
