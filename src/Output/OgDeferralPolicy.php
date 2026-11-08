<?php

declare( strict_types=1 );

namespace DiscordComponentEmbed\Output;

/**
 * Decides which Open Graph keys this extension is allowed to add.
 *
 * Two sources feed the decision. Configuration says what the wiki will emit later,
 * whatever the hook order turns out to be; the page's current tags say what some
 * other source has already emitted. Either way the extension defers: leaving the
 * wiki's own working preview alone is always better than replacing it.
 *
 * @license Unlicense
 */
final class OgDeferralPolicy {

	/**
	 * @param list<string> $claimedByOtherSources Keys another source will emit.
	 * @param list<string> $alreadyPresent Keys already on the output page.
	 */
	private function __construct(
		private readonly array $claimedByOtherSources,
		private readonly array $alreadyPresent,
	) {
	}

	/**
	 * @param list<string> $claimedByOtherSources
	 * @param list<string> $alreadyPresent
	 */
	public static function fromSources( array $claimedByOtherSources = [], array $alreadyPresent = [] ): self {
		return new self(
			array_map( 'strtolower', $claimedByOtherSources ),
			array_map( 'strtolower', $alreadyPresent )
		);
	}

	/**
	 * @param list<array{0: string, 1: string}> $metaTags The output page's tags, as [ name, value ] pairs.
	 */
	public static function fromOutputPageTags( array $claimedByOtherSources, array $metaTags ): self {
		return self::fromSources(
			$claimedByOtherSources,
			array_map( static fn ( array $tag ): string => (string)$tag[0], $metaTags )
		);
	}

	public function shouldAdd( string $key ): bool {
		$key = strtolower( $key );

		return !in_array( $key, $this->claimedByOtherSources, true )
			&& !in_array( $key, $this->alreadyPresent, true );
	}
}
