<?php

/**
 * Bootstrap for the core-contract suite.
 *
 * Reflects over a real MediaWiki checkout, so it needs core's source and nothing
 * else: no database, no wiki. MW_INSTALL_PATH points at the checkout; when the
 * extension sits inside a wiki at $IP/extensions/DiscordComponentEmbed it is found
 * without configuration.
 *
 * @license Unlicense
 */

declare( strict_types=1 );

$installPath = getenv( 'MW_INSTALL_PATH' );

if ( $installPath === false || $installPath === '' ) {
	$installPath = dirname( __DIR__, 3 );
}

$installPath = rtrim( $installPath, '/' );

if ( !is_file( $installPath . '/tests/phpunit/bootstrap.php' ) ) {
	fwrite(
		STDERR,
		"core-contract suite: no MediaWiki at {$installPath}. Set MW_INSTALL_PATH, e.g.\n"
		. "  MW_INSTALL_PATH=/var/www/html phpunit -c phpunit-core-contract.xml.dist\n"
	);
	exit( 1 );
}

// Settings are loaded, so the extension's own classes are autoloaded; the tests
// themselves never touch the database.
putenv( 'MEDIAWIKI_HAS_INTEGRATION_TESTS=true' );

require_once $installPath . '/tests/phpunit/bootstrap.php';
