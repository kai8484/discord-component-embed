<?php

declare( strict_types=1 );

namespace DiscordComponentEmbed\Embed;

use DiscordComponentEmbed\Content\DescriptionBuilder;
use DiscordComponentEmbed\Content\Whitespace;
use DiscordComponentEmbed\Content\DiscordMarkdown;
use DiscordComponentEmbed\Content\LinkBase;
use DiscordComponentEmbed\Content\Intro;
use DiscordComponentEmbed\Content\Truncator;
use DiscordComponentEmbed\Media\MediaResolution;
use DiscordComponentEmbed\Media\MediaResolver;
use DiscordComponentEmbed\Media\PageImageProvider;

/**
 * Resolves one page's preview: defaults, overrides, and merge or replace.
 *
 * Merge fills every gap the author left from the article itself; replace uses only
 * what the author wrote. Either way the automatic values are derived here, once,
 * which is what makes the payload and the metadata agree.
 *
 * @license Unlicense
 */
final class PreviewBuilder {

	/**
	 * A title longer than this is not a card, it is a paragraph heading; derived
	 * labels and titles are cut to fit rather than rejected.
	 */
	public const MAX_TITLE_CHARS = 256;

	public function __construct(
		private readonly LinkResolver $links,
		private readonly MediaResolver $media,
		private readonly PageImageProvider $pageImages,
		private readonly DescriptionBuilder $descriptions,
		private readonly ButtonResolver $buttons,
		// Heading level the header is rendered at, 1 to 3. A component's text is sized
		// by Discord, and a heading is the only markdown that asks for a bigger one.
		private readonly int $titleHeading = 0,
		// False on a wiki that publishes only Open Graph tags: buttons and the gallery
		// exist nowhere but in the payload, so resolving them is work with no consumer.
		private readonly bool $components = true,
		// Title truncation is word-boundary truncation, the same rule descriptions use.
		private readonly Truncator $truncator = new Truncator(),
		// The small print under the divider, already as markdown; empty for none.
		private readonly string $footer = '',
	) {
	}

	public function build( PreviewRequest $request ): PreviewResult {
		$config = $request->config;
		$replace = $config->isReplaceMode();

		$authoredImage = $this->media->resolve( $config->image );
		$gallery = $this->components
			? $this->media->resolveGallery( $config->gallery )
			: new MediaResolution( [] );
		$buttons = $this->components
			? $this->buttons->resolve( $config->buttons )
			: new ButtonResolution( [] );

		$unresolved = array_merge(
			$authoredImage->unresolvedTitles,
			$gallery->unresolvedTitles
		);

		$image = $authoredImage->first();

		if ( $image === null && !$replace && $config->image === null ) {
			$image = $this->pageImages->forCurrentPage();
		}

		$description = $this->descriptions->build(
			// The author's own words: markup for the card, the same words without it
			// for the meta tag.
			$config->description === null
				? null
				: new Intro( DiscordMarkdown::toPlainText( $config->description ), $config->description ),
			$replace ? null : $request->intro
		);

		// An authored title is article text too: a line break in it ends the header link
		// and lets the rest render as markdown lines, and nothing else bounds its length.
		$title = $config->title === null ? null : $this->truncateTitle( Whitespace::collapse( $config->title ) );

		if ( $title === '' ) {
			$title = null;
		}

		if ( $title === null && !$replace ) {
			$title = $request->pageTitle === '' ? null : $this->truncateTitle( $request->pageTitle );
		}

		$preview = new Preview(
			// The title is article text, so any markdown in it has to survive as text
			// rather than turn the header into something else.
			titleMarkdown: $title === null ? '' : $this->titleMarkdown( $title, $request->pageUrl ),
			titlePlain: $title ?? '',
			description: $description,
			image: $image,
			gallery: $gallery->resolved,
			buttons: $buttons->buttons,
			accent: $config->accent,
			spoiler: $config->spoiler,
			footer: $this->footer
		);

		// Buttons are resolved for the model but nothing renders them, so a button the
		// validator would refuse is a note rather than a reason to publish no card at
		// all. Rejecting here would let an argument nobody sees suppress the preview.
		return new PreviewResult(
			$preview,
			[],
			array_merge( $buttons->problems, $buttons->dropped, $this->mediaDropped( $unresolved ) )
		);
	}

	/**
	 * The card's header, as markdown. A masked link is the only clickable thing a
	 * component renders, and a bare title has no URL to open, so the page's own URL
	 * is wrapped around the bold title.
	 */
	private function titleMarkdown( string $title, string $pageUrl ): string {
		$title = DiscordMarkdown::escapeText( $title );

		// The prefix has to open the text: markdown only reads a heading when the '#'s
		// start the line, so it cannot live inside the link's label.
		// Discord has three heading levels; anything else is clamped here, the one place
		// that renders it.
		$level = max( 0, min( 3, $this->titleHeading ) );
		$prefix = $level > 0 ? str_repeat( '#', $level ) . ' ' : '';

		if ( $pageUrl === '' ) {
			return $prefix . '**' . $title . '**';
		}

		return $prefix . '[**' . $title . '**](' . DiscordMarkdown::escapeLinkTarget( LinkBase::absolute( $pageUrl ) ) . ')';
	}

	private function truncateTitle( string $title ): string {
		return $this->truncator->truncate( $title, self::MAX_TITLE_CHARS );
	}

	/**
	 * @param list<string> $unresolved
	 * @return list<string>
	 */
	private function mediaDropped( array $unresolved ): array {
		return array_map(
			static fn ( string $title ): string => sprintf( 'File "%s" does not resolve, so it was left out.', $title ),
			$unresolved
		);
	}
}
