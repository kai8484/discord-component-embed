<?php

declare( strict_types=1 );

namespace DiscordComponentEmbed\Config;

use InvalidArgumentException;

/**
 * The author's intent for one page, materialised from every call on the page.
 *
 * Everything is optional: in merge mode the builder fills the gaps from the
 * article itself, and in replace mode the author's own values are all there is.
 *
 * @license Unlicense
 */
final class EmbedConfig {

	/**
	 * @param MediaSpec|null $image Section thumbnail.
	 * @param list<MediaSpec> $gallery Gallery items, in author order.
	 * @param list<ButtonSpec> $buttons Buttons, in author order.
	 * @param int|null $accent Container accent as an RGB integer, or null for the wiki default.
	 */
	public function __construct(
		public readonly ?string $title = null,
		public readonly ?string $description = null,
		public readonly ?MediaSpec $image = null,
		public readonly array $gallery = [],
		public readonly array $buttons = [],
		public readonly ?int $accent = null,
		public readonly bool $spoiler = false,
		public readonly string $mode = Limits::MODE_MERGE,
		public readonly bool $enabled = true,
	) {
		foreach ( $this->gallery as $item ) {
			if ( !$item instanceof MediaSpec ) {
				throw new InvalidArgumentException( 'Gallery items must be MediaSpec instances' );
			}
		}

		foreach ( $this->buttons as $button ) {
			if ( !$button instanceof ButtonSpec ) {
				throw new InvalidArgumentException( 'Buttons must be ButtonSpec instances' );
			}
		}
	}

	public function isReplaceMode(): bool {
		return $this->mode === Limits::MODE_REPLACE;
	}
}
