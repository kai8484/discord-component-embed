<?php

declare( strict_types=1 );

namespace DiscordComponentEmbed\Embed;

/**
 * A link target that resolved to a followable URL.
 *
 * @license Unlicense
 */
final class ResolvedLink {

	public function __construct(
		public readonly string $url,
		public readonly string $label,
	) {
	}
}
