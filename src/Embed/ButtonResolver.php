<?php

declare( strict_types=1 );

namespace DiscordComponentEmbed\Embed;

use DiscordComponentEmbed\Config\ButtonSpec;
use DiscordComponentEmbed\Config\ButtonTargetKind;
use DiscordComponentEmbed\Config\Limits;
use DiscordComponentEmbed\Content\Truncator;

/**
 * Turns authored buttons into link buttons, or drops them.
 *
 * A button whose target is not a link a reader can follow is discarded outright
 * rather than passed through: raw `[[...]]` in a url field fails validation and
 * would take the whole preview with it, which is worse than one missing button.
 *
 * @license Unlicense
 */
final class ButtonResolver {

	public function __construct(
		private readonly LinkResolver $links,
		private readonly Truncator $truncator,
	) {
	}

	/**
	 * @param list<ButtonSpec> $specs
	 */
	public function resolve( array $specs ): ButtonResolution {
		$buttons = [];
		$dropped = [];
		$problems = [];

		foreach ( $specs as $spec ) {
			if ( $spec->kind === ButtonTargetKind::Unsupported ) {
				$dropped[] = sprintf( '"%s" is not a wikitext link or a URL, so no button was made from it.', $spec->target );
				continue;
			}

			$link = $this->links->resolve( $spec->target, $spec->label );

			if ( $link === null ) {
				$dropped[] = sprintf( '"%s" is not a link a reader can follow.', $spec->target );
				continue;
			}

			if ( !HttpUrl::isHttp( $link->url ) ) {
				$dropped[] = sprintf( '"%s" does not resolve to an http or https URL.', $spec->target );
				continue;
			}

			if ( mb_strlen( $link->url ) > Limits::MAX_URL_CHARS ) {
				$problems[] = sprintf(
					'Button "%s" resolves to a URL longer than %d characters.',
					$spec->target,
					Limits::MAX_URL_CHARS
				);
				continue;
			}

			$label = $this->label( $spec, $link, $problems );

			if ( $label === null ) {
				$dropped[] = sprintf( 'Button "%s" has no usable label.', $spec->target );
				continue;
			}

			$buttons[] = new ResolvedButton( $link->url, $label );
		}

		return new ButtonResolution( $buttons, $dropped, $problems );
	}

	/**
	 * The author's own words are never silently rewritten, so an over-long one is a
	 * problem to report; a label derived from a long title is simply cut to fit.
	 */
	private function label( ButtonSpec $spec, ResolvedLink $link, array &$problems ): ?string {
		if ( $spec->label !== null ) {
			if ( mb_strlen( $spec->label ) > Limits::MAX_LABEL_CHARS ) {
				$problems[] = sprintf(
					'Button label "%s" is longer than %d characters.',
					$spec->label,
					Limits::MAX_LABEL_CHARS
				);

				return null;
			}

			return $spec->label;
		}

		$derived = $this->truncator->truncate( $link->label, Limits::MAX_LABEL_CHARS );

		return $derived === '' ? null : $derived;
	}
}
