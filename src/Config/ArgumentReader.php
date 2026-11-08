<?php

declare( strict_types=1 );

namespace DiscordComponentEmbed\Config;

/**
 * Reads the named arguments of one {{#discord_embed: ...}} call.
 *
 * Argument names are case-sensitive and are rejected when unknown, because a
 * silently ignored `buttom1` is the exact failure mode that leaves an author
 * staring at a page with no preview and no idea why.
 *
 * @license Unlicense
 */
final class ArgumentReader {

	private const NAME_PATTERN = '/^(?:'
		. 'title|description|image|image-description|accent|spoiler|mode|enabled'
		. '|gallery[1-9][0-9]*(?:-description)?'
		. '|spoiler[1-9][0-9]*'
		. '|button[1-9][0-9]*'
		. ')$/D';

	/**
	 * @param array<array-key, mixed> $namedArguments
	 */
	public function read( array $namedArguments ): Draft {
		$values = [];
		$problems = [];

		foreach ( $namedArguments as $name => $value ) {
			if ( !is_string( $name ) ) {
				$problems[] = '{{#discord_embed:}} takes named arguments only; a positional argument was ignored.';
				continue;
			}

			if ( !preg_match( self::NAME_PATTERN, $name ) ) {
				$problems[] = sprintf( 'Unknown argument "%s" passed to {{#discord_embed:}}.', $name );
				continue;
			}

			if ( !is_scalar( $value ) ) {
				$problems[] = sprintf( 'Argument "%s" has no usable value.', $name );
				continue;
			}

			$trimmed = trim( (string)$value );

			if ( $trimmed === '' ) {
				continue;
			}

			$values[$name] = $trimmed;
		}

		return new Draft( $values, $problems );
	}
}
