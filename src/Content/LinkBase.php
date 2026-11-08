<?php

declare( strict_types=1 );

namespace DiscordComponentEmbed\Content;

/**
 * Where a preview's links should point when the page HTML is written in relative
 * form.
 *
 * Parser output keeps internal links relative (`/index.php/Doom`, `#Abilities`),
 * and the skin expands them when it renders. A preview leaves the wiki entirely, so
 * it has to expand them itself or the card ships links that go nowhere.
 *
 * @license Unlicense
 */
final class LinkBase {

	public function __construct(
		private readonly string $server,
		private readonly string $articleUrl,
	) {
	}

	/**
	 * A URL with an explicit scheme, because everything downstream needs one: Discord
	 * refuses a masked link or a button target without http(s), and a wiki configured
	 * with a protocol-relative $wgServer hands out "//host/path" everywhere.
	 */
	public static function absolute( string $url ): string {
		$url = trim( $url );

		return str_starts_with( $url, '//' ) ? 'https:' . $url : $url;
	}

	public function expand( string $href ): string {
		$href = trim( $href );

		if ( $href === '' ) {
			return '';
		}

		// Protocol-relative: the scheme is the wiki's, and it is https in practice.
		if ( str_starts_with( $href, '//' ) ) {
			return self::absolute( $href );
		}

		if ( str_starts_with( $href, '#' ) ) {
			return self::absolute( $this->articleUrl ) . $href;
		}

		if ( str_starts_with( $href, '/' ) ) {
			return self::absolute( rtrim( $this->server, '/' ) . $href );
		}

		// Already absolute or a scheme of its own; leave it alone.
		return $href;
	}
}
