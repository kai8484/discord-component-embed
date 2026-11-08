<?php

declare( strict_types=1 );

namespace DiscordComponentEmbed\Tests\Stubs;

use DiscordComponentEmbed\Media\PageImageProvider;
use DiscordComponentEmbed\Media\ResolvedMedia;

/**
 * Stands in for PageImages.
 *
 * @license Unlicense
 */
final class FakePageImageProvider implements PageImageProvider {

	public function __construct(
		private readonly ?ResolvedMedia $media,
	) {
	}

	public function forCurrentPage(): ?ResolvedMedia {
		return $this->media;
	}
}
