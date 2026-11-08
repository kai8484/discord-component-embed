<?php

declare( strict_types=1 );

namespace DiscordComponentEmbed\Embed;

/**
 * A button that resolved to something a reader can follow.
 *
 * @license Unlicense
 */
final class ResolvedButton {

	public function __construct(
		public readonly string $url,
		public readonly string $label,
	) {
	}
}
