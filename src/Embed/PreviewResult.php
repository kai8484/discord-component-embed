<?php

declare( strict_types=1 );

namespace DiscordComponentEmbed\Embed;

/**
 * A built preview, with the two kinds of author-facing message kept apart.
 *
 * @license Unlicense
 */
final class PreviewResult {

	/**
	 * @param list<string> $problems Configuration Discord would refuse: publish nothing.
	 * @param list<string> $dropped Things left out on purpose, with the rest still published.
	 */
	public function __construct(
		public readonly Preview $preview,
		public readonly array $problems = [],
		public readonly array $dropped = [],
	) {
	}

}
