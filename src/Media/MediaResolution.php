<?php

declare( strict_types=1 );

namespace DiscordComponentEmbed\Media;

/**
 * The result of resolving authored media.
 *
 * Unresolved titles are kept separately rather than dropped silently: publishing a
 * text-only preview is correct behaviour, and an author who misspells a file name
 * deserves to find out why from the log.
 *
 * @license Unlicense
 */
final class MediaResolution {

	/**
	 * @param list<ResolvedMedia> $resolved
	 * @param list<string> $unresolvedTitles
	 */
	public function __construct(
		public readonly array $resolved,
		public readonly array $unresolvedTitles = [],
	) {
	}

	public function first(): ?ResolvedMedia {
		return $this->resolved[0] ?? null;
	}
}
