<?php

declare( strict_types=1 );

namespace DiscordComponentEmbed\Tests\Stubs;

use DiscordComponentEmbed\Media\ImageResolver;
use DiscordComponentEmbed\Media\ResolvedMedia;

/**
 * An ImageResolver backed by a title-to-URL map, so code that consumes resolved
 * media can be tested without a file repository.
 *
 * @license Unlicense
 */
final class FakeImageResolver implements ImageResolver {

	/** @var array<string, ResolvedMedia> */
	private array $media;

	/** @param array<string, ResolvedMedia> $media */
	public function __construct( array $media ) {
		$this->media = $media;
	}

	public function resolve( string $fileTitle ): ?ResolvedMedia {
		return $this->media[$fileTitle] ?? null;
	}
}
