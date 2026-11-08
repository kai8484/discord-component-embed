<?php

declare( strict_types=1 );

namespace DiscordComponentEmbed\Content;

/**
 * One description, rendered for both of its consumers.
 *
 * The card gets markdown; a meta tag must not. Both were bounded by the same
 * budget before either was rendered, so the card and the link preview stop at the
 * same place instead of drifting apart.
 *
 * @license Unlicense
 */
final class Description {

	public function __construct(
		private readonly string $plain,
		private readonly string $markdown,
	) {
	}

	public static function empty(): self {
		return new self( '', '' );
	}

	public function plain(): string {
		return $this->plain;
	}

	public function markdown(): string {
		return $this->markdown;
	}

	public function isEmpty(): bool {
		return $this->plain === '' && $this->markdown === '';
	}
}
