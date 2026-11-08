<?php

declare( strict_types=1 );

namespace DiscordComponentEmbed\Content;

/**
 * Chooses which description a preview gets and bounds it.
 *
 * The budget is applied here, once, to both renderings: a card and a link preview
 * that disagree about length make a bug report about either one impossible to
 * reason about.
 *
 * @license Unlicense
 */
final class DescriptionBuilder {

	public function __construct(
		private readonly Truncator $truncator,
		private readonly int $budget,
	) {
	}

	/**
	 * @param Intro|null $authored What the author wrote, already rendered.
	 * @param Intro|null $automatic The article's intro, when the mode allows a fallback.
	 */
	public function build( ?Intro $authored, ?Intro $automatic ): Description {
		$source = $authored ?? $automatic;

		if ( $source === null || $source->isEmpty() ) {
			return Description::empty();
		}

		return new Description(
			$this->truncator->truncate( $source->plain(), $this->budget ),
			// The card is budgeted by what a reader sees: a link target costs nothing,
			// where counting the source let "[[Shop]]" spend forty characters on its URL.
			DiscordMarkdown::truncateVisible( $source->markdown(), $this->budget )
		);
	}

}
