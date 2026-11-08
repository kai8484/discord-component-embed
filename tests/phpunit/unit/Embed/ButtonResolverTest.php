<?php

declare( strict_types=1 );

namespace DiscordComponentEmbed\Tests\Unit\Embed;

use DiscordComponentEmbed\Config\ButtonSpec;
use DiscordComponentEmbed\Config\ButtonTargetKind;
use DiscordComponentEmbed\Config\Limits;
use DiscordComponentEmbed\Content\Truncator;
use DiscordComponentEmbed\Embed\ButtonResolver;
use DiscordComponentEmbed\Tests\Stubs\FakeLinkResolver;
use PHPUnit\Framework\TestCase;

/**
 * @license Unlicense
 */
final class ButtonResolverTest extends TestCase {

	private function resolver( array $urls ): ButtonResolver {
		return new ButtonResolver( new FakeLinkResolver( $urls ), new Truncator() );
	}

	public function testAPageLinkResolvesAndDerivesItsLabel(): void {
		$result = $this->resolver( [ 'Doorman' => 'https://wiki.example/wiki/Doorman' ] )
			->resolve( [ new ButtonSpec( 'Doorman', null, ButtonTargetKind::PageLink ) ] );

		$this->assertCount( 1, $result->buttons );
		$this->assertSame( 'https://wiki.example/wiki/Doorman', $result->buttons[0]->url );
		$this->assertSame( 'Doorman', $result->buttons[0]->label );
	}

	public function testAnExplicitLabelWins(): void {
		$result = $this->resolver( [ 'Doorman' => 'https://wiki.example/wiki/Doorman' ] )
			->resolve( [ new ButtonSpec( 'Doorman', 'Read more', ButtonTargetKind::PageLink ) ] );

		$this->assertSame( 'Read more', $result->buttons[0]->label );
	}

	public function testAnAnchorIsKeptInTheUrlAndTrimmedFromTheLabel(): void {
		$result = $this->resolver( [ 'Doorman#Abilities' => 'https://wiki.example/wiki/Doorman#Abilities' ] )
			->resolve( [ new ButtonSpec( 'Doorman#Abilities', null, ButtonTargetKind::PageLink ) ] );

		$this->assertSame( 'https://wiki.example/wiki/Doorman#Abilities', $result->buttons[0]->url );
		$this->assertSame( 'Doorman', $result->buttons[0]->label );
	}

	public function testAFragmentOnlyLinkKeepsTheFragmentAsItsLabel(): void {
		$result = $this->resolver( [ '#Abilities' => 'https://wiki.example/wiki/Doorman#Abilities' ] )
			->resolve( [ new ButtonSpec( '#Abilities', null, ButtonTargetKind::PageLink ) ] );

		$this->assertSame( '#Abilities', $result->buttons[0]->label );
	}

	public function testAUrlDerivesItsLabelFromTheLastPathSegment(): void {
		$result = $this->resolver( [ 'https://example.com/read' => 'https://example.com/read' ] )
			->resolve( [ new ButtonSpec( 'https://example.com/read', null, ButtonTargetKind::Url ) ] );

		$this->assertSame( 'read', $result->buttons[0]->label );
	}

	public function testPlainTextIsNotAButtonAndDropsQuietly(): void {
		$result = $this->resolver( [] )->resolve( [ new ButtonSpec( 'Doorman', null, ButtonTargetKind::Unsupported ) ] );

		$this->assertSame( [], $result->buttons );
		$this->assertCount( 1, $result->dropped );
		$this->assertSame( [], $result->problems );
	}

	public function testALinkAReaderCannotFollowDropsOnlyItself(): void {
		$result = $this->resolver( [
			'Doorman' => 'https://wiki.example/wiki/Doorman',
		] )->resolve( [
			new ButtonSpec( 'Category:Games', null, ButtonTargetKind::PageLink ),
			new ButtonSpec( 'Doorman', null, ButtonTargetKind::PageLink ),
		] );

		$this->assertCount( 1, $result->buttons );
		$this->assertSame( 'https://wiki.example/wiki/Doorman', $result->buttons[0]->url );
		$this->assertCount( 1, $result->dropped );
	}

	public function testANonHttpUrlIsDroppedRatherThanPublished(): void {
		$result = $this->resolver( [ 'ftp://example.com/read' => 'ftp://example.com/read' ] )
			->resolve( [ new ButtonSpec( 'ftp://example.com/read', null, ButtonTargetKind::Url ) ] );

		$this->assertSame( [], $result->buttons );
		$this->assertCount( 1, $result->dropped );
	}

	public function testAnAuthoredLabelLongerThanTheLimitIsAProblemRatherThanASilentRewrite(): void {
		$result = $this->resolver( [ 'Doorman' => 'https://wiki.example/wiki/Doorman' ] )
			->resolve( [ new ButtonSpec(
				'Doorman',
				str_repeat( 'L', Limits::MAX_LABEL_CHARS + 1 ),
				ButtonTargetKind::PageLink
			) ] );

		$this->assertSame( [], $result->buttons );
		$this->assertNotSame( [], $result->problems );
		$this->assertStringContainsString( (string)Limits::MAX_LABEL_CHARS, $result->problems[0] );
	}

	public function testADerivedLabelLongerThanTheLimitIsCutToFit(): void {
		$result = $this->resolver( [ 'Long' => 'https://wiki.example/wiki/Long' ] )
			->resolve( [ new ButtonSpec( 'Long', null, ButtonTargetKind::PageLink ) ] );

		$this->assertCount( 1, $result->buttons );
		$this->assertSame( [], $result->problems );
		$this->assertLessThanOrEqual( Limits::MAX_LABEL_CHARS, mb_strlen( $result->buttons[0]->label ) );
	}

	public function testADerivedLabelIsTruncatedOnAWordBoundary(): void {
		$target = 'A very long page name that goes on and on and on and on and on and on for a while';
		$result = $this->resolver( [ $target => 'https://wiki.example/wiki/Long' ] )
			->resolve( [ new ButtonSpec( $target, null, ButtonTargetKind::PageLink ) ] );

		$this->assertCount( 1, $result->buttons );
		$this->assertStringEndsWith( '…', $result->buttons[0]->label );
		$this->assertLessThanOrEqual( Limits::MAX_LABEL_CHARS, mb_strlen( $result->buttons[0]->label ) );
	}

	public function testAUrlLongerThanTheLimitIsAProblem(): void {
		$url = 'https://example.com/' . str_repeat( 'a', Limits::MAX_URL_CHARS );
		$result = $this->resolver( [ $url => $url ] )
			->resolve( [ new ButtonSpec( $url, null, ButtonTargetKind::Url ) ] );

		$this->assertSame( [], $result->buttons );
		$this->assertNotSame( [], $result->problems );
	}

	public function testAUrlIsMeasuredInCharactersNotBytes(): void {
		// Discord's limit counts characters. Measured in bytes, a 400-character URL of
		// non-ASCII letters is over 500 bytes and was refused.
		$url = 'https://example.com/' . str_repeat( 'ä', 400 );
		$result = $this->resolver( [ $url => $url ] )
			->resolve( [ new ButtonSpec( $url, null, ButtonTargetKind::Url ) ] );

		$this->assertLessThanOrEqual( Limits::MAX_URL_CHARS, mb_strlen( $url ) );
		$this->assertGreaterThan( Limits::MAX_URL_CHARS, strlen( $url ) );
		$this->assertCount( 1, $result->buttons, 'a URL inside the character limit is kept' );
	}

	public function testNonLatinLabelsSurvive(): void {
		$result = $this->resolver( [ 'ドアマン' => 'https://wiki.example/wiki/Doorman' ] )
			->resolve( [ new ButtonSpec( 'ドアマン', null, ButtonTargetKind::PageLink ) ] );

		$this->assertSame( 'ドアマン', $result->buttons[0]->label );
		$this->assertSame( 'https://wiki.example/wiki/Doorman', $result->buttons[0]->url );
	}

	public function testSeveralButtonsKeepTheirOrder(): void {
		$result = $this->resolver( [
			'One' => 'https://wiki.example/wiki/One',
			'Two' => 'https://wiki.example/wiki/Two',
			'Three' => 'https://wiki.example/wiki/Three',
		] )->resolve( [
			new ButtonSpec( 'One', null, ButtonTargetKind::PageLink ),
			new ButtonSpec( 'Two', null, ButtonTargetKind::PageLink ),
			new ButtonSpec( 'Three', null, ButtonTargetKind::PageLink ),
		] );

		$this->assertSame(
			[ 'One', 'Two', 'Three' ],
			array_map( static fn ( $button ): string => $button->label, $result->buttons )
		);
	}
}
