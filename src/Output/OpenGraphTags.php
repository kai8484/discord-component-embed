<?php

declare( strict_types=1 );

namespace DiscordComponentEmbed\Output;

use DiscordComponentEmbed\Content\Whitespace;
use DiscordComponentEmbed\Media\ResolvedMedia;

/**
 * The Open Graph fallback: what a consumer that reads only metadata gets.
 *
 * Only these keys are produced. `og:site_name` and `twitter:card` belong to the
 * skin, and producing ours alongside core's would be exactly the duplication this
 * extension is supposed to avoid.
 *
 * @license Unlicense
 */
final class OpenGraphTags {

	/**
	 * @return array<string, string> Tag name to content, in emission order.
	 */
	public function build(
		string $title,
		string $description,
		?ResolvedMedia $image,
		string $canonicalUrl
	): array {
		$tags = [];

		// A meta description is one line by definition; the last gate collapses any
		// whitespace that survived the description builder.
		$title = Whitespace::collapse( $title );
		$description = Whitespace::collapse( $description );

		if ( $title !== '' ) {
			$tags['og:title'] = $title;
		}

		if ( $description !== '' ) {
			$tags['og:description'] = $description;
		}

		$tags['og:type'] = 'website';

		if ( $canonicalUrl !== '' ) {
			$tags['og:url'] = $canonicalUrl;
		}

		if ( $image !== null && $image->url !== '' ) {
			$tags['og:image'] = $image->url;

			if ( $image->width !== null && $image->width > 0 ) {
				$tags['og:image:width'] = (string)$image->width;
			}

			if ( $image->height !== null && $image->height > 0 ) {
				$tags['og:image:height'] = (string)$image->height;
			}
		}

		return $tags;
	}

}
