<?php

declare( strict_types=1 );

namespace DiscordComponentEmbed\Embed;

/**
 * Resolves a link the way MediaWiki resolves it, so that a button lands where a
 * reader clicking the same wikitext would.
 *
 * This is the seam the unit suite fakes and the integration suite fills with real
 * core behaviour: fragments, subpage-relative links and interwiki links all need
 * the page context and cannot be modelled by a stub.
 *
 * @license Unlicense
 */
interface LinkResolver {

	/**
	 * @return ResolvedLink|null Null when the target is not a link a reader can
	 *   follow (a category link, a file link, plain text), in which case only this
	 *   button is dropped.
	 */
	public function resolve( string $target, ?string $explicitLabel ): ?ResolvedLink;
}
