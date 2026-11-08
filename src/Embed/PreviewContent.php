<?php

declare( strict_types=1 );

namespace DiscordComponentEmbed\Embed;

use DiscordComponentEmbed\Media\ResolvedMedia;

/**
 * Everything the component builder needs, after resolution and before rendering.
 *
 * @license Unlicense
 */
final class PreviewContent {

	/**
	 * @param ResolvedMedia|null $image Section thumbnail (or the standalone image).
	 * @param list<ResolvedMedia> $gallery Gallery items, in author order.
	 * @param list<ResolvedButton> $buttons Buttons, in author order.
	 */
	public function __construct(
		public readonly ?string $title = null,
		public readonly ?string $description = null,
		public readonly ?ResolvedMedia $image = null,
		public readonly array $gallery = [],
		public readonly array $buttons = [],
		public readonly ?int $accent = null,
		public readonly bool $spoiler = false,
	) {
	}

	/**
	 * True when there is nothing at all to render, in which case no payload is
	 * published.
	 */
	public function isEmpty(): bool {
		return $this->title === null
			&& $this->description === null
			&& $this->image === null
			&& $this->gallery === []
			&& $this->buttons === [];
	}
}
