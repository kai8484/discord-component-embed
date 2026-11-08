<?php

declare( strict_types=1 );

namespace DiscordComponentEmbed\MediaWiki;

use DiscordComponentEmbed\Media\ImageResolver;
use DiscordComponentEmbed\Media\ResolvedMedia;
use MediaWiki\Title\Title;
use RepoGroup;
use MediaWiki\Utils\UrlUtils;

/**
 * Resolves authored `File:` references against the wiki's file repositories.
 *
 * Everything here can fail without it being an error: the title may not parse, the
 * file may not exist, and `RepoGroup::findFile()` returns false rather than null.
 * All three are reported as "no media", which drops one image and leaves the rest
 * of the preview working.
 *
 * @license Unlicense
 */
final class RepoGroupImageResolver implements ImageResolver {

	public function __construct(
		private readonly RepoGroup $repoGroup,
		private readonly UrlUtils $urlUtils,
		private readonly int $thumbnailWidth,
	) {
	}

	/**
	 * The file title an authored reference points at, or null when it cannot be one.
	 *
	 * Shared with the collector, which has to register the same file as a dependency,
	 * so what the card renders and what the parser cache tracks cannot drift apart.
	 */
	public static function title( string $raw ): ?Title {
		$text = trim( $raw );
		$text = preg_replace( '/^\[\[|\]\]$/', '', $text ) ?? $text;
		$text = trim( $text );

		if ( preg_match( '/^(?:file|image)\s*:/i', $text ) !== 1 ) {
			$text = 'File:' . $text;
		}

		$title = Title::newFromText( $text );

		if (
			$title === null
			|| $title->getNamespace() !== NS_FILE
			|| $title->isExternal()
			|| $title->getDBkey() === ''
		) {
			return null;
		}

		return $title;
	}

	public function resolve( string $fileTitle ): ?ResolvedMedia {
		$title = self::title( $fileTitle );

		if ( $title === null ) {
			return null;
		}

		$file = $this->repoGroup->findFile( $title );

		if ( $file === false || $file === null ) {
			return null;
		}

		$resolved = FileThumbnail::resolve( $file, $this->urlUtils, $this->thumbnailWidth );

		if ( $resolved !== null ) {
			return $resolved;
		}

		// Media without a rendered thumbnail (video, audio) still has a file URL,
		// which is what a gallery wants.
		$url = $this->urlUtils->expand( $file->getUrl(), PROTO_CANONICAL );

		return $url === null || $url === '' ? null : new ResolvedMedia( $url );
	}


}
