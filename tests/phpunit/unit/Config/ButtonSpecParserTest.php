<?php

declare( strict_types=1 );

namespace DiscordComponentEmbed\Tests\Unit\Config;

use DiscordComponentEmbed\Config\ButtonSpecParser;
use DiscordComponentEmbed\Config\ButtonTargetKind;
use PHPUnit\Framework\TestCase;

/**
 * @license Unlicense
 */
final class ButtonSpecParserTest extends TestCase {

	public function testABarePageLinkKeepsItsTargetAndHasNoLabel(): void {
		$spec = ( new ButtonSpecParser() )->parse( '[[Doorman]]' );

		$this->assertSame( ButtonTargetKind::PageLink, $spec->kind );
		$this->assertSame( 'Doorman', $spec->target );
		$this->assertNull( $spec->label );
	}

	public function testAPipedLabelIsCaptured(): void {
		$spec = ( new ButtonSpecParser() )->parse( '[[Doorman|Read more]]' );

		$this->assertSame( ButtonTargetKind::PageLink, $spec->kind );
		$this->assertSame( 'Doorman', $spec->target );
		$this->assertSame( 'Read more', $spec->label );
	}

	public function testAnAnchorStaysPartOfThePageLinkTarget(): void {
		$spec = ( new ButtonSpecParser() )->parse( '[[Doorman#Abilities]]' );

		$this->assertSame( ButtonTargetKind::PageLink, $spec->kind );
		$this->assertSame( 'Doorman#Abilities', $spec->target );
	}

	public function testAFragmentOnlyLinkIsAPageLink(): void {
		$spec = ( new ButtonSpecParser() )->parse( '[[#Abilities]]' );

		$this->assertSame( ButtonTargetKind::PageLink, $spec->kind );
		$this->assertSame( '#Abilities', $spec->target );
	}

	public function testAnEmptyLabelMeansNoLabel(): void {
		$spec = ( new ButtonSpecParser() )->parse( '[[Doorman (video game)|]]' );

		$this->assertSame( 'Doorman (video game)', $spec->target );
		$this->assertNull( $spec->label );
	}

	public function testAFullUrlIsAUrlTarget(): void {
		$spec = ( new ButtonSpecParser() )->parse( 'https://example.com/read' );

		$this->assertSame( ButtonTargetKind::Url, $spec->kind );
		$this->assertSame( 'https://example.com/read', $spec->target );
		$this->assertNull( $spec->label );
	}

	public function testALabelAfterAPlainUrlIsCaptured(): void {
		$spec = ( new ButtonSpecParser() )->parse( 'https://example.com/gallery|Custom label' );

		$this->assertSame( ButtonTargetKind::Url, $spec->kind );
		$this->assertSame( 'https://example.com/gallery', $spec->target );
		$this->assertSame( 'Custom label', $spec->label );
	}

	public function testAProtocolRelativeUrlIsAUrlTarget(): void {
		$spec = ( new ButtonSpecParser() )->parse( '//example.com/read' );

		$this->assertSame( ButtonTargetKind::Url, $spec->kind );
		$this->assertSame( '//example.com/read', $spec->target );
	}

	public function testBareTextIsNeitherForm(): void {
		$spec = ( new ButtonSpecParser() )->parse( 'Doorman' );

		$this->assertSame( ButtonTargetKind::Unsupported, $spec->kind );
		$this->assertSame( 'Doorman', $spec->target );
	}

	public function testAnExternalLinkBracketFormIsNotSupported(): void {
		$spec = ( new ButtonSpecParser() )->parse( '[https://example.com Label]' );

		$this->assertSame( ButtonTargetKind::Unsupported, $spec->kind );
	}

	public function testSurroundingWhitespaceIsIgnored(): void {
		$spec = ( new ButtonSpecParser() )->parse( '  [[Doorman|Read more]]  ' );

		$this->assertSame( ButtonTargetKind::PageLink, $spec->kind );
		$this->assertSame( 'Doorman', $spec->target );
		$this->assertSame( 'Read more', $spec->label );
	}

	public function testALabelIsTrimmed(): void {
		$spec = ( new ButtonSpecParser() )->parse( '[[Doorman|  Read more  ]]' );

		$this->assertSame( 'Read more', $spec->label );
	}

	public function testABareTextTargetKeepsItsWholeValueIncludingAnyPipe(): void {
		$spec = ( new ButtonSpecParser() )->parse( 'not a link|still not a link' );

		$this->assertSame( ButtonTargetKind::Unsupported, $spec->kind );
		$this->assertSame( 'not a link|still not a link', $spec->target );
	}
}
