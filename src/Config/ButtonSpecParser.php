<?php

declare( strict_types=1 );

namespace DiscordComponentEmbed\Config;

/**
 * Splits one authored button value into its target and any explicit label.
 *
 * Two forms are understood, matching the two forms MediaWiki itself renders as a
 * link: a wikitext page link, and a full URL. The pipe is free inside a page link
 * because MediaWiki protects it, and after a URL the author writes {{!}} because a
 * bare pipe separates the parser function's arguments.
 *
 * Anything else is kept verbatim as the target and left to resolution, which drops
 * it: a value MediaWiki would render as plain text cannot be a button, and dropping
 * it is a resolution outcome rather than a configuration error.
 *
 * @license Unlicense
 */
final class ButtonSpecParser {

	private const URL_PATTERN = '#^(?:[a-zA-Z][a-zA-Z0-9+.\-]*://|//)#';

	public function parse( string $raw ): ButtonSpec {
		$raw = trim( $raw );

		if ( str_starts_with( $raw, '[[' ) && str_ends_with( $raw, ']]' ) ) {
			return $this->withLabel( substr( $raw, 2, -2 ), ButtonTargetKind::PageLink );
		}

		if ( preg_match( self::URL_PATTERN, $raw ) === 1 ) {
			return $this->withLabel( $raw, ButtonTargetKind::Url );
		}

		return new ButtonSpec( $raw, null, ButtonTargetKind::Unsupported );
	}

	private function withLabel( string $inner, ButtonTargetKind $kind ): ButtonSpec {
		$pipe = strpos( $inner, '|' );

		if ( $pipe === false ) {
			return new ButtonSpec( trim( $inner ), null, $kind );
		}

		return new ButtonSpec(
			trim( substr( $inner, 0, $pipe ) ),
			$this->normalizeLabel( substr( $inner, $pipe + 1 ) ),
			$kind
		);
	}

	/**
	 * An empty label is not a label: `[[Doorman (video game)|]]` means "use the
	 * target's own name", and MediaWiki's own pipe trick has usually rewritten it
	 * before we ever see it.
	 */
	private function normalizeLabel( string $label ): ?string {
		$label = trim( $label );

		return $label === '' ? null : $label;
	}
}
