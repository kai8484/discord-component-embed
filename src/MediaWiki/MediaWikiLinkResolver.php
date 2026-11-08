<?php

declare( strict_types=1 );

namespace DiscordComponentEmbed\MediaWiki;

use DiscordComponentEmbed\Embed\LinkResolver;
use DiscordComponentEmbed\Embed\ResolvedLink;
use MediaWiki\Linker\LinkTarget;
use MediaWiki\Linker\Linker;
use MediaWiki\Title\Title;
use MediaWiki\Utils\UrlUtils;

/**
 * Resolves link targets the way MediaWiki itself resolves them.
 *
 * The interesting cases (subpage-relative forms, fragments, interwiki prefixes,
 * targets that are not links at all) are delegated to core rather than
 * reimplemented: `Linker::normalizeSubpageLink()` is the same call the parser makes
 * before it builds a link, and `Title::newFromText()` returning null is how core
 * decides that something is plain text instead of a link.
 *
 * @license Unlicense
 */
final class MediaWikiLinkResolver implements LinkResolver {

	public function __construct(
		private readonly LinkTarget $contextTitle,
		private readonly UrlUtils $urlUtils,
	) {
	}

	public function resolve( string $target, ?string $explicitLabel ): ?ResolvedLink {
		$target = trim( $target );

		if ( $target === '' ) {
			return null;
		}

		// A link that is nothing but a fragment is a self-link: core renders it as a
		// bare "#fragment", which only means something next to the page it appears on.
		if ( str_starts_with( $target, '#' ) ) {
			return $this->selfLink( $target, $explicitLabel );
		}

		if ( $this->looksLikeUrl( $target ) ) {
			return $this->externalLink( $target, $explicitLabel );
		}

		$coreLabel = '';
		$link = Linker::normalizeSubpageLink( $this->contextTitle, $target, $coreLabel );

		// Core re-emits a bracketed URL as text and lets the external-link pass pick
		// it up, so the same target arrives here spelled as a link rather than a URL.
		if ( $this->looksLikeUrl( $link ) ) {
			return $this->externalLink( $link, $explicitLabel );
		}

		$title = Title::newFromText( $link );

		if ( $title === null || !$this->isFollowable( $title ) ) {
			return null;
		}

		return new ResolvedLink(
			$title->getFullURL( '', false, PROTO_CANONICAL ),
			$explicitLabel ?? $this->derivedLabel( $coreLabel, $target )
		);
	}

	/**
	 * The page the button appears on, plus the fragment. Built through Title so that
	 * fragment escaping is core's rather than ours, and unnamed because a fragment
	 * is the only thing the author wrote down.
	 */
	private function selfLink( string $target, ?string $explicitLabel ): ?ResolvedLink {
		$context = Title::newFromLinkTarget( $this->contextTitle );
		$title = Title::newFromText( $context->getPrefixedText() . $target );

		if ( $title === null ) {
			return null;
		}

		return new ResolvedLink( $title->getFullURL( '', false, PROTO_CANONICAL ), $explicitLabel ?? $target );
	}

	/**
	 * A category link categorises instead of linking, and a file link embeds an
	 * image; neither is a target a reader can follow.
	 */
	private function isFollowable( Title $title ): bool {
		return !in_array( $title->getNamespace(), [ NS_CATEGORY, NS_FILE ], true );
	}

	private function looksLikeUrl( string $text ): bool {
		if ( preg_match( '#^[a-zA-Z][a-zA-Z0-9+.\-]*://#', $text ) === 1 ) {
			return true;
		}

		return preg_match( '#^(?i:' . $this->urlUtils->validProtocols() . ')#', $text ) === 1;
	}

	private function externalLink( string $url, ?string $explicitLabel ): ?ResolvedLink {
		$expanded = (string)$this->urlUtils->expand( $url, PROTO_CANONICAL );

		if ( $expanded === '' ) {
			return null;
		}

		return new ResolvedLink( $expanded, $explicitLabel ?? $this->labelFromUrl( $expanded ) );
	}

	/**
	 * The target's own name. Core supplies one for the relative forms it rewrites
	 * (`/Foo/` displays as `Foo`); otherwise the fragment is dropped, except in a
	 * link that is nothing but a fragment.
	 */
	private function derivedLabel( string $coreLabel, string $target ): string {
		if ( $coreLabel !== '' ) {
			return $coreLabel;
		}

		$hash = strpos( $target, '#' );

		return $hash > 0 ? substr( $target, 0, $hash ) : $target;
	}

	private function labelFromUrl( string $url ): string {
		$path = (string)parse_url( $url, PHP_URL_PATH );
		$segment = basename( $path );

		if ( $segment !== '' && $segment !== '/' && $segment !== '.' ) {
			return $segment;
		}

		return (string)parse_url( $url, PHP_URL_HOST );
	}
}
