<?php

declare( strict_types=1 );

namespace DiscordComponentEmbed\Tests\Unit\Content;

use DiscordComponentEmbed\Content\DescriptionBuilder;
use DiscordComponentEmbed\Content\Intro;
use DiscordComponentEmbed\Content\Truncator;
use PHPUnit\Framework\TestCase;

/**
 * @license Unlicense
 */
final class DescriptionBuilderTest extends TestCase {

	private function builder( int $budget = 300 ): DescriptionBuilder {
		return new DescriptionBuilder( new Truncator(), $budget );
	}

	public function testTheAutomaticIntroIsUsedWhenNothingWasAuthored(): void {
		$description = $this->builder()->build(
			null,
			new Intro( 'The intro.', 'The intro.' )
		);

		$this->assertSame( 'The intro.', $description->plain() );
		$this->assertSame( 'The intro.', $description->markdown() );
	}

	public function testAnAuthoredDescriptionWinsOverTheAutomaticOne(): void {
		$description = $this->builder()->build(
			new Intro( 'Written by hand.', 'Written by hand.' ),
			new Intro( 'The intro.', 'The intro.' )
		);

		$this->assertSame( 'Written by hand.', $description->plain() );
	}

	public function testWithNothingAvailableTheDescriptionIsEmpty(): void {
		$description = $this->builder()->build( null, null );

		$this->assertTrue( $description->isEmpty() );
		$this->assertSame( '', $description->plain() );
	}

	public function testAnEmptyAutomaticIntroYieldsAnEmptyDescription(): void {
		$this->assertTrue( $this->builder()->build( null, new Intro( '', '' ) )->isEmpty() );
	}

	public function testBothRenderingsAreBoundedByTheSameBudget(): void {
		$long = new Intro( str_repeat( 'word ', 200 ), str_repeat( '**word** ', 200 ) );

		$description = $this->builder( 40 )->build( null, $long );

		$this->assertLessThanOrEqual( 40, mb_strlen( $description->plain() ) );
		$this->assertLessThanOrEqual( 40, mb_strlen( self::visible( $description->markdown() ) ), 'markup is not text' );
	}

	public function testATruncatedDescriptionEndsWithAnEllipsisOnBothRenderings(): void {
		$long = new Intro( str_repeat( 'word ', 200 ), str_repeat( 'word ', 200 ) );

		$description = $this->builder( 40 )->build( null, $long );

		$this->assertStringEndsWith( '…', $description->plain() );
		$this->assertStringEndsWith( '…', $description->markdown() );
	}

	public function testACutThroughALinkLeavesNoHalfLinkBehind(): void {
		$linked = new Intro(
			'Doorman is a 2024 video game by someone, released in January.',
			'**Doorman** is a 2024 [video game](https://wiki.example/index.php/Video_game) by someone.'
		);

		$description = $this->builder( 30 )->build( null, $linked );

		$markdown = $description->markdown();

		$this->assertStringEndsWith( '…', $markdown );
		$this->assertSame( substr_count( $markdown, '[' ), substr_count( $markdown, ']' ), 'no unclosed label' );
		$this->assertSame( substr_count( $markdown, '(' ), substr_count( $markdown, ')' ), 'no unclosed target' );
	}

	public function testALinkTargetDoesNotEatTheVisibleBudget(): void {
		// "[video game](https://wiki.example/index.php/Video_game)" shows sixteen
		// characters and costs sixty. Counting the source left cards a third full.
		$linked = new Intro(
			'Doorman is a 2024 video game by someone, released in January.',
			'**Doorman** is a 2024 [video game](https://wiki.example/index.php/Video_game) by someone, released in January.'
		);

		$description = $this->builder( 58 )->build( null, $linked );

		$this->assertStringContainsString( 'by someone', $description->markdown(), 'text after a long URL still fits' );
		$this->assertLessThanOrEqual( 58, mb_strlen( self::visible( $description->markdown() ) ) );
	}

	public function testAnIntroIsNotCutWhenOnlyItsLinkTargetsAreLong(): void {
		// 30 visible characters in a 100-character source: a byte budget cut this, a
		// visible one sees that the card has room.
		$intro = new Intro(
			'Doorman is a game about doors.',
			'**Doorman** is a [game](https://wiki.example/index.php/Doorman_(video_game)) about doors.'
		);

		$this->assertSame(
			'**Doorman** is a [game](https://wiki.example/index.php/Doorman_(video_game)) about doors.',
			$this->builder( 80 )->build( null, $intro )->markdown()
		);
	}

	private static function visible( string $markdown ): string {
		$text = preg_replace( '/\[([^\]]*)\]\([^)]*\)/', '$1', $markdown ) ?? $markdown;

		return preg_replace( '/[*#`_~|]/', '', $text ) ?? $text;
	}

	public function testMarkdownIsKeptForTheCardButNotForMetadata(): void {
		$description = $this->builder()->build(
			new Intro( 'A bold intro.', 'A **bold** intro.' ),
			null
		);

		$this->assertSame( 'A bold intro.', $description->plain() );
		$this->assertSame( 'A **bold** intro.', $description->markdown() );
	}

	public function testAShortDescriptionIsUntouched(): void {
		$description = $this->builder( 300 )->build( new Intro( 'Short.', 'Short.' ), null );

		$this->assertSame( 'Short.', $description->plain() );
	}
}
