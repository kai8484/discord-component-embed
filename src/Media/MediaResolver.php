<?php

declare( strict_types=1 );

namespace DiscordComponentEmbed\Media;

use DiscordComponentEmbed\Config\MediaSpec;

/**
 * Resolves authored `File:` references, dropping the ones that do not resolve.
 *
 * Dropping is the whole job: a missing file, a file that is not media, or a
 * thumbnail that cannot be produced must leave the rest of the preview alone and
 * must never raise a PHP error.
 *
 * @license Unlicense
 */
final class MediaResolver {

	public function __construct(
		private readonly ImageResolver $images,
	) {
	}

	public function resolve( ?MediaSpec $spec ): MediaResolution {
		if ( $spec === null ) {
			return new MediaResolution( [] );
		}

		$media = $this->resolveSpec( $spec );

		return $media === null
			? new MediaResolution( [], [ $spec->title ] )
			: new MediaResolution( [ $media ] );
	}

	/**
	 * @param list<MediaSpec> $specs
	 */
	public function resolveGallery( array $specs ): MediaResolution {
		$resolved = [];
		$unresolved = [];

		foreach ( $specs as $spec ) {
			$media = $this->resolveSpec( $spec );

			if ( $media === null ) {
				$unresolved[] = $spec->title;
				continue;
			}

			$resolved[] = $media;
		}

		return new MediaResolution( $resolved, $unresolved );
	}

	private function resolveSpec( MediaSpec $spec ): ?ResolvedMedia {
		$media = $this->images->resolve( $spec->title );

		if ( $media === null ) {
			return null;
		}

		// The caption and the spoiler belong to the author, not to the file.
		return new ResolvedMedia(
			$media->url,
			$spec->description ?? $media->description,
			$spec->spoiler,
			$media->width,
			$media->height
		);
	}
}
