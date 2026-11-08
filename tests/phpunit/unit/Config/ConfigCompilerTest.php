<?php

declare( strict_types=1 );

namespace DiscordComponentEmbed\Tests\Unit\Config;

use DiscordComponentEmbed\Config\ButtonTargetKind;
use DiscordComponentEmbed\Config\ConfigCompiler;
use DiscordComponentEmbed\Config\Draft;
use DiscordComponentEmbed\Config\EmbedConfig;
use DiscordComponentEmbed\Config\EmbedOutcome;
use DiscordComponentEmbed\Config\Limits;
use PHPUnit\Framework\TestCase;

/**
 * @license Unlicense
 */
final class ConfigCompilerTest extends TestCase {

	private function compile( array $values, array $problems = [] ): \DiscordComponentEmbed\Config\CompiledConfig {
		return ( new ConfigCompiler() )->compile( new Draft( $values, $problems ) );
	}

	public function testAnEmptyConfigurationIsValid(): void {
		$result = $this->compile( [] );

		$this->assertSame( EmbedOutcome::Valid, $result->outcome() );
		$this->assertInstanceOf( EmbedConfig::class, $result->config() );
	}

	public function testScalarArgumentsReachTheConfiguration(): void {
		$result = $this->compile( [
			'title' => 'Doorman',
			'description' => 'A game about doors',
			'image' => 'File:Doorman.png',
			'image-description' => 'A door',
			'spoiler' => 'yes',
			'mode' => 'replace',
		] );

		$config = $result->config();
		$this->assertSame( EmbedOutcome::Valid, $result->outcome() );
		$this->assertSame( 'Doorman', $config->title );
		$this->assertSame( 'A game about doors', $config->description );
		$this->assertSame( 'File:Doorman.png', $config->image?->title );
		$this->assertSame( 'A door', $config->image?->description );
		$this->assertTrue( $config->spoiler );
		$this->assertTrue( $config->isReplaceMode() );
	}

	public function testNumberOfButtonsBecomeAnOrderedList(): void {
		$result = $this->compile( [
			'button1' => '[[Doorman]]',
			'button2' => '[[Doorman|Read more]]',
		] );

		$buttons = $result->config()->buttons;
		$this->assertCount( 2, $buttons );
		$this->assertSame( 'Doorman', $buttons[0]->target );
		$this->assertSame( ButtonTargetKind::PageLink, $buttons[0]->kind );
		$this->assertNull( $buttons[0]->label );
		$this->assertSame( 'Read more', $buttons[1]->label );
	}

	public function testNumberOfGalleryItemsCarryTheirCaptionsAndSpoilers(): void {
		$result = $this->compile( [
			'gallery1' => 'File:One.png',
			'gallery1-description' => 'First',
			'gallery2' => 'File:Two.png',
			'spoiler2' => 'yes',
		] );

		$gallery = $result->config()->gallery;
		$this->assertCount( 2, $gallery );
		$this->assertSame( 'First', $gallery[0]->description );
		$this->assertFalse( $gallery[0]->spoiler );
		$this->assertTrue( $gallery[1]->spoiler );
	}

	public function testAccentIsConvertedFromHexToAnInteger(): void {
		$result = $this->compile( [ 'accent' => '#5865F2' ] );

		$this->assertSame( 0x5865F2, $result->config()->accent );
	}

	public function testAccentWithoutTheHashIsAccepted(): void {
		$result = $this->compile( [ 'accent' => '5865F2' ] );

		$this->assertSame( 0x5865F2, $result->config()->accent );
	}

	public function testANonHexAccentIsAnError(): void {
		$result = $this->compile( [ 'accent' => 'cornflower' ] );

		$this->assertSame( EmbedOutcome::Invalid, $result->outcome() );
		$this->assertStringContainsString( 'accent', implode( ' ', $result->problems() ) );
	}

	public function testEnabledNoDisablesThePreview(): void {
		$result = $this->compile( [ 'enabled' => 'no' ] );

		$this->assertSame( EmbedOutcome::Disabled, $result->outcome() );
		$this->assertFalse( $result->config()->enabled );
	}

	public function testEnabledAcceptsSeveralTruthyAndFalsySpellings(): void {
		foreach ( [ 'yes', 'true', '1', 'on' ] as $spelling ) {
			$this->assertSame( EmbedOutcome::Valid, $this->compile( [ 'enabled' => $spelling ] )->outcome(), $spelling );
		}

		foreach ( [ 'no', 'false', '0', 'off' ] as $spelling ) {
			$this->assertSame( EmbedOutcome::Disabled, $this->compile( [ 'enabled' => $spelling ] )->outcome(), $spelling );
		}
	}

	public function testAnUnreadableEnabledValueIsAnError(): void {
		$result = $this->compile( [ 'enabled' => 'maybe' ] );

		$this->assertSame( EmbedOutcome::Invalid, $result->outcome() );
		$this->assertStringContainsString( 'enabled', implode( ' ', $result->problems() ) );
	}

	public function testAnUnknownModeIsAnError(): void {
		$result = $this->compile( [ 'mode' => 'append' ] );

		$this->assertSame( EmbedOutcome::Invalid, $result->outcome() );
		$this->assertStringContainsString( 'mode', implode( ' ', $result->problems() ) );
	}

	public function testModeIsCaseInsensitive(): void {
		$this->assertSame( EmbedOutcome::Valid, $this->compile( [ 'mode' => 'REPLACE' ] )->outcome() );
	}

	public function testTwentyFiveButtonsAreAccepted(): void {
		$values = [];
		for ( $i = 1; $i <= Limits::MAX_BUTTONS; $i++ ) {
			$values['button' . $i] = 'https://example.com/' . $i;
		}

		$this->assertSame( EmbedOutcome::Valid, $this->compile( $values )->outcome() );
	}

	public function testTwentySixButtonsAreRejected(): void {
		$values = [];
		for ( $i = 1; $i <= Limits::MAX_BUTTONS + 1; $i++ ) {
			$values['button' . $i] = 'https://example.com/' . $i;
		}

		$result = $this->compile( $values );
		$this->assertSame( EmbedOutcome::Invalid, $result->outcome() );
		$this->assertStringContainsString( 'button', implode( ' ', $result->problems() ) );
	}

	public function testTenGalleryItemsAreAcceptedAndElevenAreRejected(): void {
		$ten = [];
		for ( $i = 1; $i <= Limits::MAX_GALLERY_ITEMS; $i++ ) {
			$ten['gallery' . $i] = 'File:Pic' . $i . '.png';
		}

		$this->assertSame( EmbedOutcome::Valid, $this->compile( $ten )->outcome() );

		$eleven = $ten;
		$eleven['gallery' . ( Limits::MAX_GALLERY_ITEMS + 1 )] = 'File:Pic11.png';

		$result = $this->compile( $eleven );
		$this->assertSame( EmbedOutcome::Invalid, $result->outcome() );
		$this->assertStringContainsString( 'gallery', implode( ' ', $result->problems() ) );
	}

	public function testAGapInTheNumberOfButtonsIsAnError(): void {
		$result = $this->compile( [ 'button1' => 'https://example.com/a', 'button3' => 'https://example.com/c' ] );

		$this->assertSame( EmbedOutcome::Invalid, $result->outcome() );
		$this->assertStringContainsString( 'button2', implode( ' ', $result->problems() ) );
	}

	public function testACaptionWithoutItsGalleryItemIsAnError(): void {
		$result = $this->compile( [ 'gallery1-description' => 'Orphan caption' ] );

		$this->assertSame( EmbedOutcome::Invalid, $result->outcome() );
		$this->assertStringContainsString( 'gallery1', implode( ' ', $result->problems() ) );
	}

	public function testAProblemFromArgumentReadingMakesTheConfigurationInvalid(): void {
		$result = $this->compile( [ 'title' => 'Doorman' ], [ 'Unknown argument: typo' ] );

		$this->assertSame( EmbedOutcome::Invalid, $result->outcome() );
		$this->assertStringContainsString( 'typo', implode( ' ', $result->problems() ) );
	}

	public function testProblemsAreDeduplicated(): void {
		$result = $this->compile( [], [ 'same', 'same', 'other' ] );

		$this->assertSame( [ 'same', 'other' ], $result->problems() );
	}

	public function testAnIndexFarAboveTheLimitIsRejectedWithoutEnumeratingEveryGap(): void {
		$result = $this->compile( [ 'button3000' => 'https://example.com/x' ] );

		$this->assertSame( EmbedOutcome::Invalid, $result->outcome() );
		$this->assertLessThanOrEqual( 2, count( $result->problems() ) );
		$this->assertStringContainsString( '25', implode( ' ', $result->problems() ) );
	}

	public function testAnIndexTooLargeForAnIntegerIsRejectedRatherThanLooping(): void {
		$start = microtime( true );
		$result = $this->compile( [ 'button99999999999999999999' => 'https://example.com/x' ] );

		$this->assertSame( EmbedOutcome::Invalid, $result->outcome() );
		$this->assertLessThan( 1.0, microtime( true ) - $start );
	}

	public function testAGalleryIndexFarAboveTheLimitIsRejectedWithoutEnumeratingEveryGap(): void {
		$result = $this->compile( [ 'gallery400' => 'File:One.png' ] );

		$this->assertSame( EmbedOutcome::Invalid, $result->outcome() );
		$this->assertLessThanOrEqual( 2, count( $result->problems() ) );
		$this->assertStringContainsString( '10', implode( ' ', $result->problems() ) );
	}
}
