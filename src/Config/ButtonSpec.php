<?php

declare( strict_types=1 );

namespace DiscordComponentEmbed\Config;

/**
 * One authored button: which form was written, the target with any wikitext
 * brackets removed, and the label if the author gave one.
 *
 * The target is not yet a URL, because resolving it needs the page the button
 * appears on: `[[#Abilities]]` and `[[/Subpage]]` only mean something in context.
 *
 * @license Unlicense
 */
final class ButtonSpec {

	public function __construct(
		public readonly string $target,
		public readonly ?string $label = null,
		public readonly ButtonTargetKind $kind = ButtonTargetKind::Unsupported,
	) {
	}
}
