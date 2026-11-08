<?php

declare( strict_types=1 );

namespace DiscordComponentEmbed\Content;

/**
 * The intro, rendered twice: once as plain text for metadata, once as Discord
 * markdown for the card.
 *
 * Displayed text is markdown; descriptions that reach a meta tag are not.
 *
 * @license Unlicense
 */
final class Intro {

	public function __construct(
		private readonly string $plain,
		private readonly string $markdown,
	) {
	}

	public function plain(): string {
		return $this->plain;
	}

	public function markdown(): string {
		return $this->markdown;
	}

	public function isEmpty(): bool {
		return $this->plain === '';
	}
}
