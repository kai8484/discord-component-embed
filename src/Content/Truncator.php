<?php

declare( strict_types=1 );

namespace DiscordComponentEmbed\Content;

/**
 * Word-boundary truncation for preview descriptions.
 *
 * The budget counts characters rather than bytes, and it includes the ellipsis:
 * a truncated description is never longer than one that happens to fit, which is
 * what keeps the component payload and the Open Graph description in step.
 *
 * Input is whitespace-normalised first, because previews are single-paragraph
 * text and runs of spaces or newlines would otherwise eat the budget invisibly.
 *
 * @license Unlicense
 */
final class Truncator {

	private const ELLIPSIS = '…';

	public function truncate( string $text, int $budget ): string {
		$text = Whitespace::collapse( $text );

		if ( $budget <= 0 ) {
			return '';
		}

		if ( mb_strlen( $text ) <= $budget ) {
			return $text;
		}

		if ( $budget === 1 ) {
			return self::ELLIPSIS;
		}

		$candidate = mb_substr( $text, 0, $budget - 1 );

		if ( $this->cutLandsInsideAWord( $text, $candidate, $budget ) ) {
			$candidate = $this->dropPartialWord( $candidate );
		}

		$candidate = rtrim( $candidate );

		return $candidate === '' ? self::ELLIPSIS : $candidate . self::ELLIPSIS;
	}

	/**
	 * True when the character after the candidate continues the candidate's last
	 * word, i.e. the cut would ship half a word.
	 */
	private function cutLandsInsideAWord( string $text, string $candidate, int $budget ): bool {
		if ( $candidate === '' ) {
			return false;
		}

		$next = mb_substr( $text, $budget - 1, 1 );

		return $next !== ''
			&& !$this->isWhitespace( $next )
			&& !$this->isWhitespace( mb_substr( $candidate, -1 ) );
	}

	/**
	 * Retreat to the previous word boundary. Text made of one unbroken run has no
	 * boundary to retreat to, so the cut stands rather than losing all content.
	 */
	private function dropPartialWord( string $candidate ): string {
		$lastBoundary = mb_strrpos( $candidate, ' ' );

		return $lastBoundary === false ? $candidate : mb_substr( $candidate, 0, $lastBoundary );
	}

	private function isWhitespace( string $character ): bool {
		return $character !== '' && preg_match( '/^\s$/u', $character ) === 1;
	}

}
