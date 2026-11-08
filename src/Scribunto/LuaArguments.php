<?php

declare( strict_types=1 );

namespace DiscordComponentEmbed\Scribunto;

use DiscordComponentEmbed\Config\ArgumentReader;
use DiscordComponentEmbed\Config\Draft;

/**
 * Turns the table a module passes to `mw.ext.discordembed.embed` into the same
 * draft a `{{#discord_embed:}}` call produces.
 *
 * The conversion is kept apart from the Scribunto adapter so it can be tested
 * without Scribunto installed, and so nothing here needs a parser.
 *
 * @license Unlicense
 */
final class LuaArguments {

	/**
	 * @param array<array-key, mixed> $arguments The Lua table, as Scribunto converted it.
	 */
	public static function draft( ArgumentReader $reader, array $arguments ): Draft {
		return $reader->read( self::toNamedArguments( $arguments ) );
	}

	/**
	 * @param array<array-key, mixed> $arguments
	 * @return array<array-key, mixed>
	 */
	public static function toNamedArguments( array $arguments ): array {
		$named = [];

		foreach ( $arguments as $name => $value ) {
			// A key is a name; "gallery1" with a trailing newline is a typo, not a
			// different argument.
			if ( is_string( $name ) ) {
				$name = trim( $name );
			}

			// `image = nil` is a key that was never set, not an argument to reject.
			if ( $value === null ) {
				continue;
			}

			// Lua's booleans have no string form an author would recognise: casting
			// false gives "", which the reader would drop as an empty value, and
			// `enabled = false` would silently leave the preview on.
			if ( is_bool( $value ) ) {
				$named[$name] = $value ? 'yes' : 'no';
				continue;
			}

			// A nested table is left alone so the reader reports it as unusable
			// rather than this class inventing a message of its own.
			$named[$name] = is_scalar( $value ) ? (string)$value : $value;
		}

		return $named;
	}

}
