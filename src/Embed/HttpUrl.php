<?php

declare( strict_types=1 );

namespace DiscordComponentEmbed\Embed;

/**
 * Whether a URL is one Discord will accept on a link button or a media component.
 *
 * The same check was written out four times, next to three slightly different
 * "does this look like a URL" predicates.
 *
 * @license Unlicense
 */
final class HttpUrl {

	public const PATTERN = '#^https?://#i';

	public static function isHttp( string $url ): bool {
		return preg_match( self::PATTERN, $url ) === 1;
	}

}
