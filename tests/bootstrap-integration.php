<?php

/**
 * Bootstrap for the integration suite, which parses real wikitext against a real,
 * installed wiki and its database.
 *
 * MW_INSTALL_PATH points at the install; when the extension sits inside a wiki at
 * $IP/extensions/DiscordComponentEmbed it is found without configuration.
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
		"integration suite: no MediaWiki at {$installPath}. Set MW_INSTALL_PATH, e.g.\n"
		. "  MW_INSTALL_PATH=/var/www/html phpunit -c phpunit-integration.xml.dist\n"
	);
	exit( 1 );
}

putenv( 'MEDIAWIKI_HAS_INTEGRATION_TESTS=true' );

require_once $installPath . '/tests/phpunit/bootstrap.php';
