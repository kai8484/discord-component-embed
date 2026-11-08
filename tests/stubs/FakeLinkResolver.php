<?php

declare( strict_types=1 );

namespace DiscordComponentEmbed\Tests\Stubs;

use DiscordComponentEmbed\Embed\LinkResolver;
use DiscordComponentEmbed\Embed\ResolvedLink;

/**
 * A LinkResolver with the core behaviour removed.
 *
 * That is the point: resolving a wikitext link needs real Title, fragment and
 * interwiki behaviour, which a stub cannot model faithfully, so the interesting
 * cases live in the integration suite. Here the resolver only has to be
 * predictable, so the code that consumes it can be tested on its own.
 *
 * @license Unlicense
 */
final class FakeLinkResolver implements LinkResolver {

	/** @var array<string, string> */
	private array $urls;

	/** @param array<string, string> $urls Target to URL. */
	public function __construct( array $urls ) {
		$this->urls = $urls;
	}

	public function resolve( string $target, ?string $explicitLabel ): ?ResolvedLink {
		$url = $this->urls[$target] ?? null;

		if ( $url === null ) {
			return null;
		}

		return new ResolvedLink( $url, $this->deriveLabel( $target, $explicitLabel ) );
	}

	/**
	 * Mirrors the documented label rule closely enough for unit tests: the author's
	 * text first, otherwise the target without its fragment.
	 */
	private function deriveLabel( string $target, ?string $explicitLabel ): string {
		if ( $explicitLabel !== null ) {
			return $explicitLabel;
		}

		$hash = strpos( $target, '#' );

		if ( $hash === 0 ) {
			return $target;
		}

		if ( $hash !== false ) {
			return substr( $target, 0, $hash );
		}

		if ( preg_match( '#^https?://#', $target ) === 1 ) {
			$path = (string)parse_url( $target, PHP_URL_PATH );
			$segment = basename( $path );

			return $segment !== '' && $segment !== '/' ? $segment : (string)parse_url( $target, PHP_URL_HOST );
		}

		return $target;
	}
}
