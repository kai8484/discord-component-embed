<?php

declare( strict_types=1 );

namespace DiscordComponentEmbed\Embed;


/**
 * The result of turning authored buttons into followable ones.
 *
 * `dropped` and `problems` are different things on purpose. A dropped button is
 * the documented behaviour for a link a reader cannot follow, and the rest of the
 * preview is still published; a problem is a configuration Discord would refuse,
 * and nothing is published at all.
 *
 * @license Unlicense
 */
final class ButtonResolution {

	/**
	 * @param list<ResolvedButton> $buttons
	 * @param list<string> $dropped
	 * @param list<string> $problems
	 */
	public function __construct(
		public readonly array $buttons,
		public readonly array $dropped = [],
		public readonly array $problems = [],
	) {
	}

}
