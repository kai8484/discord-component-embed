<?php

/**
 * Bootstrap for the unit suite. It must work with no MediaWiki installation and,
 * as a convenience, without a composer install: the PSR-4 fallback below loads the
 * extension's own classes so `phpunit` can run from a bare checkout.
 *
 * @license Unlicense
 */

declare( strict_types=1 );

$composer = __DIR__ . '/../vendor/autoload.php';

if ( is_file( $composer ) ) {
	require_once $composer;
}

// The stubs directory is lower-case; spelled out so the lookup also works on a
// case-sensitive filesystem, where Tests\Stubs would not find tests/stubs.
$prefixes = [
	'DiscordComponentEmbed\\Tests\\Stubs\\' => __DIR__ . '/stubs/',
	'DiscordComponentEmbed\\Tests\\' => __DIR__ . '/',
	'DiscordComponentEmbed\\' => __DIR__ . '/../src/',
];

spl_autoload_register( static function ( string $class ) use ( $prefixes ): void {
	foreach ( $prefixes as $prefix => $baseDir ) {
		if ( !str_starts_with( $class, $prefix ) ) {
			continue;
		}

		$relative = str_replace( '\\', '/', substr( $class, strlen( $prefix ) ) );
		$file = $baseDir . $relative . '.php';

		if ( is_file( $file ) ) {
			require_once $file;
		}

		return;
	}
} );
