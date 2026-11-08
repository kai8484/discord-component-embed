<?php

declare( strict_types=1 );

namespace DiscordComponentEmbed\Tests\Unit\Config;

use DiscordComponentEmbed\Config\ArgumentReader;
use PHPUnit\Framework\TestCase;

/**
 * @license Unlicense
 */
final class ArgumentReaderTest extends TestCase {

	public function testEveryDocumentedArgumentIsAccepted(): void {
		$reader = new ArgumentReader();

		$draft = $reader->read( [
			'title' => 'Doorman',
			'description' => 'A game about doors',
			'image' => 'File:Doorman.png',
			'image-description' => 'A door',
			'gallery1' => 'File:One.png',
			'gallery1-description' => 'First',
			'spoiler1' => 'yes',
			'button1' => '[[Doorman]]',
			'accent' => '#5865F2',
			'spoiler' => 'no',
			'mode' => 'replace',
			'enabled' => 'yes',
		] );

		$this->assertSame( [], $draft->problems() );
		$this->assertCount( 12, $draft->values() );
		$this->assertSame( 'Doorman', $draft->values()['title'] );
	}

	public function testValuesAreTrimmed(): void {
		$draft = ( new ArgumentReader() )->read( [ 'title' => '  Doorman  ' ] );

		$this->assertSame( 'Doorman', $draft->values()['title'] );
	}

	public function testEmptyValuesAreIgnoredRatherThanStored(): void {
		$draft = ( new ArgumentReader() )->read( [
			'title' => '',
			'image-description' => '   ',
		] );

		$this->assertSame( [], $draft->values() );
		$this->assertSame( [], $draft->problems() );
	}

	public function testAnUnknownArgumentIsReportedWithItsName(): void {
		$draft = ( new ArgumentReader() )->read( [ 'buttom1' => '[[Doorman]]' ] );

		$this->assertSame( [], $draft->values() );
		$this->assertCount( 1, $draft->problems() );
		$this->assertStringContainsString( 'buttom1', $draft->problems()[0] );
	}

	public function testPositionalArgumentsAreRejected(): void {
		$draft = ( new ArgumentReader() )->read( [ 0 => 'Doorman' ] );

		$this->assertSame( [], $draft->values() );
		$this->assertCount( 1, $draft->problems() );
		$this->assertStringContainsString( 'named', $draft->problems()[0] );
	}

	public function testNumberOfLessThanOneIsRejected(): void {
		$draft = ( new ArgumentReader() )->read( [ 'button0' => '[[Doorman]]' ] );

		$this->assertSame( [], $draft->values() );
		$this->assertStringContainsString( 'button0', implode( ' ', $draft->problems() ) );
	}

	public function testNonNumericIndexIsRejected(): void {
		$draft = ( new ArgumentReader() )->read( [ 'galleryx' => 'File:One.png' ] );

		$this->assertSame( [], $draft->values() );
		$this->assertStringContainsString( 'galleryx', implode( ' ', $draft->problems() ) );
	}

	public function testArgumentNamesAreCaseSensitive(): void {
		$draft = ( new ArgumentReader() )->read( [ 'Title' => 'Doorman' ] );

		$this->assertSame( [], $draft->values() );
		$this->assertStringContainsString( 'Title', implode( ' ', $draft->problems() ) );
	}

	public function testAnIndexWellAboveTheLimitIsStillReadAndLeftToValidation(): void {
		$draft = ( new ArgumentReader() )->read( [ 'button26' => '[[Doorman]]' ] );

		$this->assertSame( [ 'button26' => '[[Doorman]]' ], $draft->values() );
		$this->assertSame( [], $draft->problems() );
	}
}
