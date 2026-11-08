<?php

declare( strict_types=1 );

namespace DiscordComponentEmbed\Content;

/**
 * Whitespace as a preview sees it: one line, no zero-width bytes, valid UTF-8.
 *
 * Three places used to carry their own copy of this: the intro extractor, the
 * truncator and the Open Graph fallback.
 *
 * @license Unlicense
 */
final class Whitespace {

	public static function collapse( string $text ): string {
		// A page can carry bytes that are not valid UTF-8, and the /u regex below would
		// then fail and silently empty the text.
		$text = mb_scrub( $text, 'UTF-8' );
		$text = str_replace( "\u{200B}", '', $text );

		return trim( preg_replace( '/\s+/u', ' ', $text ) ?? '' );
	}

}
