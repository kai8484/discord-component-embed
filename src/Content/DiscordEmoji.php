<?php

declare( strict_types=1 );

namespace DiscordComponentEmbed\Content;

/**
 * Whether a configuration value is one custom emoji mention.
 *
 * The footer is markdown, and the emoji goes into it verbatim because escaping would
 * break the mention. That means the value itself has to be one: a newline would end the
 * subtext line, and a `|` or `*` would spill into the formatting around it.
 *
 * @license Unlicense
 */
final class DiscordEmoji {

	private const MENTION = '/^<a?:[A-Za-z0-9_~]+:\d+>$/';

	public static function isMention( string $value ): bool {
		return preg_match( self::MENTION, $value ) === 1;
	}

}
