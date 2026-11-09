<?php

declare( strict_types=1 );

namespace DiscordComponentEmbed\Tests\Unit\Content;

use DiscordComponentEmbed\Content\DiscordMarkdown;
use PHPUnit\Framework\TestCase;

/**
 * A description cut by character count can land inside markup, and the leftover
 * half renders as text. Links and code spans are the constructs that break
 * loudly, so those are the ones the cut is moved out of.
 *
 * @license Unlicense
 */
final class DiscordMarkdownTest extends TestCase {

	public function testBalancedMarkdownIsLeftAlone(): void {
		$text = '**Doorman** is a [video game](https://wiki.example/a) and `more` text.';

		$this->assertSame( $text, DiscordMarkdown::trimDangling( $text ) );
	}

	public function testPlainTextIsLeftAlone(): void {
		$this->assertSame( 'Plain text cut mid wor', DiscordMarkdown::trimDangling( 'Plain text cut mid wor' ) );
	}

	public function testACutMaskedLinkIsDroppedWhole(): void {
		$this->assertSame(
			'**Doorman** is a 2024…',
			DiscordMarkdown::trimDangling( '**Doorman** is a 2024 [video game](https://wiki.example/Vid' )
		);
	}

	public function testACutCodeSpanIsDropped(): void {
		$this->assertSame( 'Use…', DiscordMarkdown::trimDangling( 'Use `wrench' ) );
	}

	public function testACutTargetKeepsTheLinksBeforeIt(): void {
		$this->assertSame(
			'*Word [link](url) and…',
			DiscordMarkdown::trimDangling( '*Word [link](url) and [broken link](https://cut' )
		);
	}

	public function testCodeInsideACutLinkDoesNotBlockTheTrim(): void {
		$this->assertSame(
			'Intro text…',
			DiscordMarkdown::trimDangling( 'Intro text [`code`](https://wiki.example/Vid' )
		);
	}

	public function testABackslashBeforeAClosingBacktickDoesNotUnbalanceTheSpan(): void {
		// Inside a code span a backslash is literal, so it cannot escape the backtick
		// that closes the span.
		foreach ( [
			'Pattern `C:' . chr(92) . '` here',
			'Code `' . chr(92) . '` and more',
		] as $text ) {
			$this->assertSame( $text, DiscordMarkdown::trimDangling( $text ) );
		}
	}

	public function testMarkupInsideACodeSpanIsLiteral(): void {
		$text = 'The function returns `*ptr` to the caller.';

		$this->assertSame( $text, DiscordMarkdown::trimDangling( $text ) );
	}

	public function testAnEllipsisAlreadyAtTheEndDoesNotConfuseTheCut(): void {
		$this->assertSame( 'foo…', DiscordMarkdown::trimDangling( 'foo' . '\\' . '…' ) );
	}

	public function testACutEscapeDoesNotLeaveABackslash(): void {
		$this->assertSame( 'Stray backslash…', DiscordMarkdown::trimDangling( 'Stray backslash \\' ) );
	}

	public function testPlainTextDropsMaskedLinkTargets(): void {
		$this->assertSame(
			'A **great** game, see the page.',
			DiscordMarkdown::toPlainText( 'A **great** game, see [the page](https://x/y).' )
		);
		$this->assertSame(
			'See Page now.',
			DiscordMarkdown::toPlainText( 'See [Page](https://wiki/Apple_(fruit)) now.' )
		);
	}

	public function testPlainTextLeavesProseAlone(): void {
		// Every rule that strips "**" also turns "2**8" into "28", so emphasis stays:
		// a visible asterisk beats a mangled number.
		foreach ( [ '5*3 = 15', 'snake_case stays', '2**8 = 256 and **bold** text' ] as $text ) {
			$this->assertSame( $text, DiscordMarkdown::toPlainText( $text ) );
		}
	}

	public function testPlainTextUnescapesWhatTheCardEscaped(): void {
		$this->assertSame( 'escape ` tick', DiscordMarkdown::toPlainText( 'escape ' . chr(92) . '` tick' ) );
		$this->assertSame( 'a [literal] bracket', DiscordMarkdown::toPlainText( 'a ' . chr(92) . '[literal' . chr(92) . '] bracket' ) );
	}

	public function testADescriptionMadeOnlyOfMarkupIsNotErased(): void {
		$this->assertSame( '[](https://x/y)', DiscordMarkdown::toPlainText( '[](https://x/y)' ) );
	}

	public function testTheCutIsAlwaysBalanced(): void {
		$bs = chr( 92 );

		foreach ( [
			[ '[Hello world](https://x/y)abc', 12 ],
			[ '`foo bar`baz', 8 ],
			[ 'a [title](https://example.com/foo(bar)baz) tail', 12 ],
			[ 'plain words here that go on and on', 20 ],
			[ "escaped {$bs}é accent here", 10 ],
		] as [ $markdown, $budget ] ) {
			$cut = DiscordMarkdown::truncateVisible( $markdown, $budget );

			$this->assertSame( substr_count( $cut, '[' ), substr_count( $cut, ']' ), "label balanced: {$cut}" );
			$this->assertSame( substr_count( $cut, '(' ), substr_count( $cut, ')' ), "target balanced: {$cut}" );
		}
	}

	public function testATargetWithBracketsOfItsOwnStaysOneLink(): void {
		$markdown = 'a [title](https://example.com/foo(bar)baz) tail';

		$this->assertSame( $markdown, DiscordMarkdown::truncateVisible( $markdown, 20 ) );
	}

	public function testTheEllipsisCountsAgainstTheBudget(): void {
		$cut = DiscordMarkdown::truncateVisible( 'plain words here that go on and on', 20 );

		$this->assertLessThanOrEqual( 20, mb_strlen( $cut ) );
		$this->assertStringEndsWith( '…', $cut );
	}

	public function testABacktickInsideACodeSpanCountsAsVisible(): void {
		// "``foo ` bar``" shows nine characters; the single backtick is content, not a
		// delimiter, so it must not be treated as free.
		$this->assertSame( '``foo ` bar``', DiscordMarkdown::truncateVisible( '``foo ` bar``', 9 ) );
		$this->assertSame( '…', DiscordMarkdown::truncateVisible( '``foo ` bar``', 8 ), 'no slice inside a span' );
	}

	public function testAnUnterminatedTargetIsCutRatherThanSwallowingTheText(): void {
		$cut = DiscordMarkdown::truncateVisible( 'hello [world](https://unclosed and more and more', 10 );

		$this->assertSame( 'hello…', $cut );
	}

	public function testAClosedLinkIsNotTradedForAWordBoundary(): void {
		$cut = DiscordMarkdown::truncateVisible( 'hello [foo bar](https://wiki/x)tailmore', 18 );

		$this->assertStringContainsString( '[foo bar](https://wiki/x)', $cut, 'the whole link stays' );
		$this->assertStringEndsWith( '…', $cut );
	}

	public function testMarkupWithNothingVisibleIsNotADescription(): void {
		$this->assertSame( '', DiscordMarkdown::truncateVisible( str_repeat( '*', 50 ), 300 ) );
		$this->assertSame( '', DiscordMarkdown::truncateVisible( '[](https://example.com/long/url)', 300 ) );
	}

	public function testStrippingALinkKeepsItsLabelAndEverythingElse(): void {
		$this->assertSame(
			'See the page and another now.',
			DiscordMarkdown::stripLinkTargets( 'See [the page](https://wiki.example/a) and [another](https://x/y_(z)) now.' )
		);
	}

	public function testStrippingALinkLeavesEscapedTextEscaped(): void {
		// The card body must not gain formatting the extractor escaped, and an escaped
		// bracket pair must not become a link.
		$bs = chr( 92 );

		$this->assertSame(
			'Buy now ' . $bs . '[click' . $bs . '](https://example.com) and ' . $bs . '*starred' . $bs . ' text.',
			DiscordMarkdown::stripLinkTargets(
				'Buy now ' . $bs . '[click' . $bs . '](https://example.com) and ' . $bs . '*starred' . $bs . ' text.'
			)
		);
	}

	public function testStrippingALinkLeavesCodeAlone(): void {
		$this->assertSame( 'Code `a](b` stays.', DiscordMarkdown::stripLinkTargets( 'Code `a](b` stays.' ) );
	}

	public function testEmphasisIsDeliberatelyNotTracked(): void {
		// Guessing where an author's asterisks belong cost half a sentence in review.
		// A stray "**" is accepted; a deleted sentence is not.
		foreach ( [
			'The game is a **closed bet',
			'5*3 = 15',
			'*text [link*](https://x/y) tail',
		] as $text ) {
			$this->assertSame( $text, DiscordMarkdown::trimDangling( $text ) );
		}
	}

}
