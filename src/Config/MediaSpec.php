<?php

declare( strict_types=1 );

namespace DiscordComponentEmbed\Config;

/**
 * A resolved media reference: the title the author wrote and, once resolution has
 * happened, the public URL to render.
 *
 * A null URL means "authored but not resolvable"; the builder drops those rather
 * than emitting a component Discord would reject.
 *
 * @license Unlicense
 */
final class MediaSpec {

	public function __construct(
		public readonly string $title,
		public readonly ?string $description = null,
		public readonly bool $spoiler = false,
	) {
	}

}
