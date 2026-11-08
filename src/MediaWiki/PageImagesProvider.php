<?php

declare( strict_types=1 );

namespace DiscordComponentEmbed\MediaWiki;

use DiscordComponentEmbed\Media\PageImageProvider;
use DiscordComponentEmbed\Media\ResolvedMedia;
use MediaWiki\Title\Title;
use PageImages\PageImages;
use MediaWiki\Utils\UrlUtils;

/**
 * The article's own image, taken from PageImages.
 *
 * This is the same call PageImages makes for its own og:image, deliberately: the
 * preview and the wiki then agree on the picture instead of showing two different
 * ones. When PageImages is absent or has nothing to say, the preview is text only.
 *
 * @license Unlicense
 */
final class PageImagesProvider implements PageImageProvider {

	public function __construct(
		private readonly Title $pageTitle,
		private readonly UrlUtils $urlUtils,
		private readonly ?PageImages $pageImages,
		private readonly int $thumbnailWidth,
	) {
	}

	public function forCurrentPage(): ?ResolvedMedia {
		if ( $this->pageImages === null ) {
			return null;
		}

		$file = $this->pageImages->getImage( $this->pageTitle );

		if ( $file === null ) {
			return null;
		}

		return FileThumbnail::resolve( $file, $this->urlUtils, $this->thumbnailWidth );
	}

}
