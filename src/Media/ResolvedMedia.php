<?php

declare( strict_types=1 );

namespace DiscordComponentEmbed\Media;

/**
 * A wiki file resolved to a public URL, with the dimensions a preview needs.
 *
 * A null URL never happens: resolution returns null instead, so unreachable media
 * is dropped rather than emitted as a component Discord refuses.
 *
 * @license Unlicense
 */
final class ResolvedMedia {

	public function __construct(
		public readonly string $url,
		public readonly ?string $description = null,
		public readonly bool $spoiler = false,
		public readonly ?int $width = null,
		public readonly ?int $height = null,
	) {
	}
}
