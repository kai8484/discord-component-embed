<?php

declare( strict_types=1 );

namespace DiscordComponentEmbed\Tests\Unit\Content;

use DiscordComponentEmbed\Content\Truncator;
use PHPUnit\Framework\TestCase;

/**
 * @license Unlicense
 */
final class TruncatorTest extends TestCase {

	public function testTextWithinTheBudgetIsUnchanged(): void {
		$this->assertSame( 'A short intro.', ( new Truncator() )->truncate( 'A short intro.', 300 ) );
	}

	public function testTextExactlyAtTheBudgetIsUnchanged(): void {
		$text = str_repeat( 'a', 20 );

		$this->assertSame( $text, ( new Truncator() )->truncate( $text, 20 ) );
	}

	public function testLongTextIsCutAtAWordBoundaryAndEndsWithAnEllipsis(): void {
		$result = ( new Truncator() )->truncate( 'the quick brown fox jumps', 20 );

		$this->assertSame( 'the quick brown fox…', $result );
	}

	public function testCutDoesNotLeaveAPartialWord(): void {
		$result = ( new Truncator() )->truncate( 'the quick brown fox jumps', 15 );

		$this->assertSame( 'the quick…', $result );
	}

	public function testResultNeverExceedsTheBudget(): void {
		$text = 'word ' . str_repeat( 'longword ', 50 );

		foreach ( range( 1, 60 ) as $budget ) {
			$result = ( new Truncator() )->truncate( $text, $budget );

			$this->assertLessThanOrEqual( $budget, mb_strlen( $result ), "budget {$budget}" );
		}
	}

	public function testWhitespaceAtTheCutIsNotCarriedIntoTheResult(): void {
		$result = ( new Truncator() )->truncate( 'alpha beta gamma', 11 );

		$this->assertSame( 'alpha beta…', $result );
	}

	public function testTextWithoutWhitespaceIsCutAtTheBudget(): void {
		$result = ( new Truncator() )->truncate( 'abcdefghijklmnopqrstuvwxyz', 10 );

		$this->assertSame( 'abcdefghi…', $result );
	}

	public function testMultibyteTextIsCutByCharacterNotByByte(): void {
		$result = ( new Truncator() )->truncate( 'みんなの日本語を勉強しています', 6 );

		$this->assertSame( 'みんなの日…', $result );
		$this->assertSame( 6, mb_strlen( $result ) );
	}

	public function testBudgetOfOneCharacterIsTheEllipsisAlone(): void {
		$this->assertSame( '…', ( new Truncator() )->truncate( 'long text here', 1 ) );
	}

	public function testNonPositiveBudgetYieldsAnEmptyString(): void {
		$this->assertSame( '', ( new Truncator() )->truncate( 'long text here', 0 ) );
		$this->assertSame( '', ( new Truncator() )->truncate( 'long text here', -5 ) );
	}

	public function testWhitespaceRunsInsideTheKeptTextAreCollapsed(): void {
		$result = ( new Truncator() )->truncate( "alpha   beta\n\ngamma delta", 40 );

		$this->assertSame( 'alpha beta gamma delta', $result );
	}

	public function testInvalidUtf8BytesDoNotSilentlyEmptyTheResult(): void {
		$result = ( new Truncator() )->truncate( "\xFF\xFE bad bytes here", 300 );

		$this->assertNotSame( '', $result );
		$this->assertStringContainsString( 'bad bytes here', $result );
	}

	public function testInvalidUtf8IsStillBounded(): void {
		$result = ( new Truncator() )->truncate( "\xFF\xFE bad bytes here", 5 );

		$this->assertNotSame( '', $result );
		$this->assertLessThanOrEqual( 5, mb_strlen( $result ) );
	}
}
