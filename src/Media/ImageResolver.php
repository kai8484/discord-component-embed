<?php

declare( strict_types=1 );

namespace DiscordComponentEmbed\Media;

/**
 * Turns a `File:` title into a public URL for the preview.
 *
 * A missing file, a file that is not media, or a file whose thumbnail cannot be
 * produced all return null: an unresolvable reference is not an error and must
 * never raise a PHP error.
 *
 * @license Unlicense
 */
interface ImageResolver {

	public function resolve( string $fileTitle ): ?ResolvedMedia;
}
