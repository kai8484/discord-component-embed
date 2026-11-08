<?php

declare( strict_types=1 );

namespace DiscordComponentEmbed\MediaWiki;

use DiscordComponentEmbed\Media\ResolvedMedia;
use File;
use MediaWiki\Utils\UrlUtils;

/**
 * A wiki file as the preview needs it: a public URL at the preview's width, with the
 * dimensions it ended up with.
 *
 * Both the authored-image resolver and the PageImages one did this by hand, guard for
 * guard.
 *
 * @license Unlicense
 */
final class FileThumbnail {

	public static function resolve( File $file, UrlUtils $urlUtils, int $width ): ?ResolvedMedia {
		$thumbnail = $file->transform( [ 'width' => $width, 'height' => $width ] );

		if ( $thumbnail === false || $thumbnail === null || $thumbnail->getUrl() === '' ) {
			return null;
		}

		$url = $urlUtils->expand( $thumbnail->getUrl(), PROTO_CANONICAL );

		if ( $url === null || $url === '' ) {
			return null;
		}

		return new ResolvedMedia( $url, null, false, $thumbnail->getWidth(), $thumbnail->getHeight() );
	}

}
