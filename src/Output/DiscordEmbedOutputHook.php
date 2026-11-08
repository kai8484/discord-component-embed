<?php

declare( strict_types=1 );

namespace DiscordComponentEmbed\Output;

use DiscordComponentEmbed\Content\LinkBase;
use MediaWiki\Hook\OutputPageAfterGetHeadLinksArrayHook;
use MediaWiki\Hook\OutputPageParserOutputHook;
use MediaWiki\Output\OutputPage;
use WeakMap;

/**
 * Publishes the stored preview into the finished page.
 *
 * Two hooks, for two different jobs. The first adds the component payload and the
 * metadata fallback, deciding what to add from configuration. The second is the only
 * point at which every source's tags are on the page at once, and it removes any of
 * ours that a later source duplicated, so the wiki's own preview always wins.
 *
 * @license Unlicense
 */
final class DiscordEmbedOutputHook implements OutputPageParserOutputHook, OutputPageAfterGetHeadLinksArrayHook {

	/** @var WeakMap<OutputPage, list<array{0: string, 1: string}>> */
	private WeakMap $addedTags;

	public function __construct(
		private readonly ComponentEmbedScript $script,
		private readonly OpenGraphTags $openGraphTags,
	) {
		$this->addedTags = new WeakMap();
	}

	public function onOutputPageParserOutput( $outputPage, $parserOutput ): void {
		if ( !$outputPage->getConfig()->get( 'DiscordComponentEmbedEnabled' ) ) {
			return;
		}

		$stored = StoredPreview::fromArray( $parserOutput->getExtensionData( StoredPreview::KEY ) );

		if ( $stored === null ) {
			return;
		}

		$markup = $this->script->build( $stored->components() );

		// Without the payload Discord renders the Open Graph tags itself, which is the
		// only way to get its own card: a larger clickable title, at the cost of the
		// buttons, gallery and accent a component carries.
		if ( $markup !== null && $outputPage->getConfig()->get( 'DiscordComponentEmbedComponents' ) ) {
			$outputPage->addHeadItem( $this->script->tagId(), $markup );
		}

		if ( !$outputPage->getConfig()->get( 'DiscordComponentEmbedOpenGraph' ) ) {
			return;
		}

		$this->addOpenGraphTags( $outputPage, $stored );
	}

	public function onOutputPageAfterGetHeadLinksArray( &$tags, $out ): void {
		$added = $this->addedTags[$out] ?? [];

		if ( $added === [] ) {
			return;
		}

		$claimedByOthers = $this->keysAlsoEmittedElsewhere( $tags, $added );

		if ( $claimedByOthers === [] ) {
			return;
		}

		$stillOurs = [];

		foreach ( $added as [ $name, $value ] ) {
			$key = strtolower( $name );
			$stillOurs[$key][] = $value;
		}

		$tags = array_values( array_filter(
			$tags,
			static function ( string $tag ) use ( &$stillOurs, $claimedByOthers ): bool {
				$key = self::metaKeyOf( $tag );

				if ( $key === null || !isset( $claimedByOthers[$key] ) ) {
					return true;
				}

				// Drop exactly the tags this extension added, so every tag another source
				// emitted stays — including one with the same content as ours.
				foreach ( $stillOurs[$key] ?? [] as $index => $value ) {
					if ( self::contentOf( $tag ) === $value ) {
						unset( $stillOurs[$key][$index] );

						return false;
					}
				}

				return true;
			}
		) );
	}

	private function addOpenGraphTags( OutputPage $outputPage, StoredPreview $stored ): void {
		$policy = OgDeferralPolicy::fromOutputPageTags(
			$this->claimedKeys( $outputPage, $stored ),
			$outputPage->getMetaTags()
		);

		$tags = $this->openGraphTags->build(
			$stored->title(),
			$stored->description(),
			$stored->image(),
			LinkBase::absolute( $outputPage->getTitle()?->getFullURL() ?? '' )
		);

		foreach ( $tags as $name => $value ) {
			if ( !$policy->shouldAdd( $name ) ) {
				continue;
			}

			$outputPage->addMeta( $name, $value );

			// WeakMap::offsetGet() throws for a missing key, so the entry is created
			// rather than appended to blind.
			$added = $this->addedTags[$outputPage] ?? [];
			$added[] = [ $name, $value ];
			$this->addedTags[$outputPage] = $added;
		}
	}

	/**
	 * What another source will emit, so this extension never has to guess from hook
	 * order. Core's og: tags are gated by $wgSkinMetaTags, and PageImages emits
	 * og:image whenever its own Open Graph support is on.
	 *
	 * @return list<string>
	 */
	private function claimedKeys( OutputPage $outputPage, StoredPreview $stored ): array {
		$config = $outputPage->getConfig();
		$claimed = [];

		$skinMetaTags = $config->get( 'SkinMetaTags' );

		if ( is_array( $skinMetaTags ) ) {
			foreach ( $skinMetaTags as $key ) {
				if ( is_string( $key ) ) {
					$claimed[] = $key;
				}
			}
		}

		// PageImages is optional: on a wiki without it the global was never defined,
		// and GlobalVarConfig::get() throws for an undefined option instead of
		// returning null. It only emits og:image when it found a page image, which
		// cannot be known here, so an image this preview resolved itself is published
		// instead: if another source did emit the key, the dedupe pass takes ours back
		// out again.
		if (
			$stored->image() === null
			&& $config->has( 'PageImagesOpenGraph' )
			&& $config->get( 'PageImagesOpenGraph' )
		) {
			$claimed[] = 'og:image';
			$claimed[] = 'og:image:width';
			$claimed[] = 'og:image:height';
		}

		return $claimed;
	}

	/**
	 * Keys another source emitted more tags for than this extension did, which is
	 * what a duplicate looks like once both have written to the page.
	 *
	 * Counting rather than comparing content is what catches a source that emits the
	 * same value we did: PageImages picks the same page image we resolved, and two
	 * identical og:image tags in the head help nobody.
	 *
	 * @param list<string> $tags
	 * @param list<array{0: string, 1: string}> $added
	 * @return array<string, true>
	 */
	private function keysAlsoEmittedElsewhere( array $tags, array $added ): array {
		$present = [];

		foreach ( $tags as $tag ) {
			$key = self::metaKeyOf( $tag );

			if ( $key !== null ) {
				$present[$key] = ( $present[$key] ?? 0 ) + 1;
			}
		}

		$ours = [];

		foreach ( $added as [ $name ] ) {
			$key = strtolower( $name );
			$ours[$key] = ( $ours[$key] ?? 0 ) + 1;
		}

		$claimed = [];

		foreach ( $ours as $key => $count ) {
			if ( ( $present[$key] ?? 0 ) > $count ) {
				$claimed[$key] = true;
			}
		}

		return $claimed;
	}

	private static function metaKeyOf( string $tag ): ?string {
		if ( preg_match( '/^<meta\b[^>]*>/i', trim( $tag ), $matches ) !== 1 ) {
			return null;
		}

		if ( preg_match( '/\b(?:property|name)="([^"]*)"/i', $matches[0], $key ) !== 1 ) {
			return null;
		}

		return strtolower( $key[1] );
	}

	private static function contentOf( string $tag ): ?string {
		if ( preg_match( '/\bcontent="([^"]*)"/i', $tag, $matches ) !== 1 ) {
			return null;
		}

		return html_entity_decode( $matches[1], ENT_QUOTES | ENT_HTML5, 'UTF-8' );
	}
}
