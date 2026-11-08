<?php

declare( strict_types=1 );

namespace DiscordComponentEmbed\Content;

/**
 * The markdown a card carries, done by hand: escaping it, cutting it back to whole
 * constructs, and reducing it to plain text for a meta tag.
 *
 * Card text is generated rather than authored, so a page title or an intro that
 * happens to contain "*", "_" or "[" must not turn into formatting. Discord renders
 * a backslash before any non-alphanumeric character as that character, which is what
 * makes escaped text safe.
 *
 * @license Unlicense
 */
final class DiscordMarkdown {

	/**
	 * Characters that mean something to Discord's parser wherever they appear,
	 * plus the backslash that introduces the escapes themselves.
	 */
	private const SPECIAL_CHARACTERS = '/[\\\\*_~`|\[\]>]/';

	public static function escapeText( string $text ): string {
		return preg_replace( self::SPECIAL_CHARACTERS, '\\\\$0', $text ) ?? $text;
	}

	/**
	 * A masked link ends at the first ")", so its target cannot contain one.
	 * Percent-encoding is the same URL to the server and survives the parse, which
	 * is what makes links to disambiguated titles ("Apple (fruit)") work at all.
	 */
	public static function escapeLinkTarget( string $target ): string {
		return strtr( $target, [ '(' => '%28', ')' => '%29' ] );
	}

	/**
	 * The same words without the markup every consumer would show as syntax.
	 *
	 * Only masked links are removed: `[the page](https://x/y)` in a meta description
	 * is noise, because the target is repeated right next to the label. Emphasis is
	 * left alone on purpose — every rule that strips `**` also turns "2**8" into
	 * "28", and a mangled number is worse than a visible asterisk.
	 */
	public static function toPlainText( string $markdown ): string {
		// Escaped characters are set aside first: they are text the author wanted
		// shown literally, so the stripping below must not see them as markup.
		$protected = '';
		$escapes = [];
		$length = strlen( $markdown );

		for ( $index = 0; $index < $length; $index++ ) {
			if ( $markdown[$index] === '\\' && $index + 1 < $length ) {
				$index++;
				$escapes[] = $markdown[$index];
				$protected .= "\x01" . ( count( $escapes ) - 1 ) . "\x02";

				continue;
			}

			$protected .= $markdown[$index];
		}

		// The target is dropped and the label kept; the parentheses allow a link to a
		// title like "Apple_(fruit)" without eating its closing bracket.
		$text = preg_replace( '/\[([^\]]*)\]\((?:[^()]|\([^()]*\))*\)/', '$1', $protected ) ?? $protected;

		$text = preg_replace_callback(
			'/\x01(\d+)\x02/',
			static fn ( array $match ): string => $escapes[(int)$match[1]] ?? '',
			$text
		) ?? $text;

		// A description made of nothing but markup must not become no description.
		return $text === '' ? $markdown : $text;
	}

	/**
	 * Cuts a markdown string to a budget of *visible* characters.
	 *
	 * A masked link's target is invisible on the card — `[Shop](https://wiki/…)` shows
	 * four characters — so counting the source against the budget starved card
	 * descriptions on link-heavy leads: on a wiki whose links expand to full URLs, a
	 * 300-character budget was showing as little as 91 characters.
	 *
	 * The cut lands on a word boundary, never inside a link or a code span, and leaves
	 * room for the ellipsis it appends.
	 */
	public static function truncateVisible( string $markdown, int $budget ): string {
		if ( $budget <= 0 ) {
			return '';
		}

		// The ellipsis is visible text, so the scan stops one character short of the
		// budget and the marker takes that place.
		$limit = $budget - 1;
		$length = strlen( $markdown );
		$open = [];
		$index = 0;
		$visible = 0;
		$clean = 0;
		$lastSpace = 0;

		while ( $index < $length ) {
			$character = $markdown[$index];
			$inside = $open === [] ? null : $open[count( $open ) - 1][0];
			$consumedSpace = false;

			if ( $inside === 'code' ) {
				if ( $character === '`' ) {
					$run = strspn( $markdown, '`', $index );

					if ( $open[count( $open ) - 1][1] === $run ) {
						array_pop( $open );
					} else {
						// Not the closing run, so these are literal backticks in the code.
						$visible += $run;
					}

					$index += $run;
				} else {
					// Inside code the characters are visible; only the backticks are not.
					$index += self::characterSize( $markdown, $index );
					$visible++;
				}
			} elseif ( $inside === 'target' ) {
				// The target is invisible, and it may carry brackets of its own.
				$depth = $open[count( $open ) - 1][1];

				if ( $character === '(' ) {
					$open[count( $open ) - 1][1] = $depth + 1;
				} elseif ( $character === ')' ) {
					if ( $depth === 0 ) {
						array_pop( $open );
					} else {
						$open[count( $open ) - 1][1] = $depth - 1;
					}
				}

				$index++;
			} elseif ( $character === '\\' && $index + 1 < $length ) {
				// The escaped character is visible, whatever width it has.
				$index += 1 + self::characterSize( $markdown, $index + 1 );
				$visible++;
			} elseif ( $character === '`' ) {
				$run = strspn( $markdown, '`', $index );
				$open[] = [ 'code', $run ];
				$index += $run;
			} elseif ( $character === '*' ) {
				$index += strspn( $markdown, '*', $index );
			} elseif ( $character === '[' ) {
				$open[] = [ 'label', 0 ];
				$index++;
			} elseif ( $character === ']' && $inside === 'label' ) {
				if ( $index + 1 < $length && $markdown[$index + 1] === '(' ) {
					$open[count( $open ) - 1] = [ 'target', 0 ];
					$index += 2;
				} else {
					array_pop( $open );
					$index++;
				}
			} else {
				$index += self::characterSize( $markdown, $index );
				$visible++;
				$consumedSpace = $character === ' ';
			}

			if ( $open === [] && $visible <= $limit ) {
				$clean = $index;

				if ( $consumedSpace ) {
					$lastSpace = $index;
				}
			}
		}

		// An unterminated target swallows the rest of the string as invisible text, so a
		// malformed description is cut at its last whole point rather than returned.
		if ( $open !== [] ) {
			return $clean === 0 ? '' : rtrim( substr( $markdown, 0, $clean ) ) . '…';
		}

		// Markup with nothing a reader would see is not a description.
		if ( $visible === 0 ) {
			return '';
		}

		if ( $visible <= $budget ) {
			return $markdown;
		}

		$cut = $clean;

		// A word boundary is nicer than half a word, but not at the price of a whole
		// closed link: retreat only across text that holds no construct.
		if ( $lastSpace > 0 && $lastSpace < $cut ) {
			$between = substr( $markdown, $lastSpace, $cut - $lastSpace );

			if ( strpos( $between, ']' ) === false && strpos( $between, ')' ) === false ) {
				$cut = $lastSpace;
			}
		}

		if ( $cut === 0 ) {
			return '…';
		}

		return rtrim( substr( $markdown, 0, $cut ) ) . '…';
	}

	/**
	 * Byte length of the UTF-8 character starting at the given offset.
	 */
	private static function characterSize( string $text, int $offset ): int {
		$size = 1;

		while ( $offset + $size < strlen( $text ) && ( ord( $text[$offset + $size] ) & 0xC0 ) === 0x80 ) {
			$size++;
		}

		return $size;
	}


	/**
	 * Cuts a markdown string back so it cannot end inside a link or a code span, then
	 * keeps the ellipsis.
	 *
	 * Those are the two constructs that break loudly: a cut inside one ships
	 * "[video game](https://wiki.exam" to Discord as visible text. Emphasis is
	 * deliberately not tracked: guessing where an author's asterisks belong is how
	 * half a sentence gets deleted to remove one stray "*".
	 */
	public static function trimDangling( string $markdown ): string {
		$ellipsis = str_ends_with( $markdown, '…' ) ? '…' : '';
		$text = $ellipsis === '' ? $markdown : substr( $markdown, 0, -strlen( '…' ) );
		$length = strlen( $text );

		/** @var list<array{0: string, 1: int, 2: int}> $open Kind, byte offset, code run length. */
		$open = [];
		$index = 0;

		while ( $index < $length ) {
			$character = $text[$index];

			// Inside a code span only a matching backtick run means anything: its
			// contents are literal text, brackets and asterisks included.
			if ( $open !== [] && $open[count( $open ) - 1][0] === 'code' ) {
				if ( $character !== '`' ) {
					$index++;

					continue;
				}

				$run = strspn( $text, '`', $index );

				if ( $open[count( $open ) - 1][2] === $run ) {
					array_pop( $open );
				}

				$index += $run;

				continue;
			}

			if ( $character === '\\' ) {
				if ( $index + 1 >= $length ) {
					$text = rtrim( substr( $text, 0, $index ) );
					$length = strlen( $text );
					$ellipsis = '…';

					break;
				}

				$index += 2;

				continue;
			}

			if ( $character === '`' ) {
				$run = strspn( $text, '`', $index );
				$open[] = [ 'code', $index, $run ];
				$index += $run;

				continue;
			}

			if ( $character === '[' ) {
				$open[] = [ 'label', $index, 0 ];
			} elseif ( $character === ']' && $open !== [] && $open[count( $open ) - 1][0] === 'label' ) {
				if ( $index + 1 < $length && $text[$index + 1] === '(' ) {
					$open[count( $open ) - 1][0] = 'target';
				} else {
					array_pop( $open );
				}
			} elseif ( $character === ')' && $open !== [] && $open[count( $open ) - 1][0] === 'target' ) {
				array_pop( $open );
			}

			$index++;
		}

		if ( $open === [] ) {
			return $text === $markdown ? $markdown : $text . $ellipsis;
		}

		// The outermost construct still open is where the invisible text begins.
		$cut = $open[0][1];

		if ( $cut === 0 ) {
			return $markdown;
		}

		$kept = rtrim( substr( $text, 0, $cut ) );

		return $kept === '' ? $markdown : $kept . '…';
	}
}
