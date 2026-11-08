<?php

declare( strict_types=1 );

namespace DiscordComponentEmbed\Embed;

use DiscordComponentEmbed\Content\Description;

/**
 * What the page will show, after defaults, overrides and replacement have been
 * resolved.
 *
 * The title exists twice because it has two audiences: the card renders markdown,
 * a meta tag must not.
 *
 * @license Unlicense
 */
final class Preview {

	/**
	 * @param list<\DiscordComponentEmbed\Media\ResolvedMedia> $gallery
	 * @param list<ResolvedButton> $buttons
	 */
	public function __construct(
		public readonly string $titleMarkdown = '',
		public readonly string $titlePlain = '',
		public readonly ?Description $description = null,
		public readonly ?\DiscordComponentEmbed\Media\ResolvedMedia $image = null,
		public readonly array $gallery = [],
		public readonly array $buttons = [],
		public readonly ?int $accent = null,
		public readonly bool $spoiler = false,
	) {
	}

	public function descriptionPlain(): string {
		return $this->description?->plain() ?? '';
	}

	public function descriptionMarkdown(): string {
		return $this->description?->markdown() ?? '';
	}

	/**
	 * The builder's view of the same preview.
	 */
	public function toContent(): PreviewContent {
		return new PreviewContent(
			title: $this->titleMarkdown === '' ? null : $this->titleMarkdown,
			description: $this->descriptionMarkdown() === '' ? null : $this->descriptionMarkdown(),
			image: $this->image,
			gallery: $this->gallery,
			buttons: $this->buttons,
			accent: $this->accent,
			spoiler: $this->spoiler
		);
	}
}
