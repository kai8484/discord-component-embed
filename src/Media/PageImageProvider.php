<?php

declare( strict_types=1 );

namespace DiscordComponentEmbed\Media;

/**
 * The article's own thumbnail, chosen by whatever the wiki uses to choose one.
 *
 * This is deliberately separate from ImageResolver: PageImages decides which file
 * represents a page, and that is a different question from "what is at this File:
 * title". Keeping them apart is what lets the preview and the wiki agree on the
 * picture without either one owning the other's configuration.
 *
 * @license Unlicense
 */
interface PageImageProvider {

	public function forCurrentPage(): ?ResolvedMedia;
}
