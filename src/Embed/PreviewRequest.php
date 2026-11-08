<?php

declare( strict_types=1 );

namespace DiscordComponentEmbed\Embed;

use DiscordComponentEmbed\Config\EmbedConfig;
use DiscordComponentEmbed\Content\Intro;

/**
 * Everything the preview builder needs about the page being previewed.
 *
 * @license Unlicense
 */
final class PreviewRequest {

	public function __construct(
		public readonly string $pageTitle,
		public readonly EmbedConfig $config,
		public readonly ?Intro $intro = null,
		// The card's header links here. Empty when the caller has no URL to give,
		// in which case the title stays plain bold text.
		public readonly string $pageUrl = '',
	) {
	}
}
