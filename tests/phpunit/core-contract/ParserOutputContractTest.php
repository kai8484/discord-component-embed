<?php

declare( strict_types=1 );

namespace DiscordComponentEmbed\Tests\CoreContract;

use MediaWiki\Parser\ParserOutput;
use PHPUnit\Framework\TestCase;
use Wikimedia\Parsoid\Core\MergeStrategy;

/**
 * The parser-cache surface the extension stores its per-page state in.
 *
 * @license Unlicense
 */
class ParserOutputContractTest extends TestCase {

	public function testExtensionDataRoundTripsTheShapeWeStore(): void {
		$output = new ParserOutput();
		$stored = [
			'version' => 1,
			'components' => [ [ 'type' => 17, 'components' => [ [ 'type' => 10, 'content' => 'Hi' ] ] ] ],
			'title' => 'Doorman',
			'description' => 'A game.',
			'image' => [ 'url' => 'https://wiki.example/a.png', 'width' => 800, 'height' => 450 ],
		];

		$output->setExtensionData( 'DiscordComponentEmbed:preview', $stored );

		$this->assertSame( $stored, $output->getExtensionData( 'DiscordComponentEmbed:preview' ) );
	}

	public function testAnUnknownKeyIsNullWhichIsHowNoPreviewIsRepresented(): void {
		$this->assertNull( ( new ParserOutput() )->getExtensionData( 'DiscordComponentEmbed:preview' ) );
	}

	public function testSettingTheSameKeyToADifferentValueIsSupported(): void {
		// The parser function accumulates calls this way, because appendExtensionData
		// merges string sets and integer sums rather than a list of records.
		$output = new ParserOutput();

		$output->setExtensionData( 'drafts', [ [ 'title' => 'one' ] ] );
		$output->setExtensionData( 'drafts', [ [ 'title' => 'one' ], [ 'title' => 'two' ] ] );

		$this->assertCount( 2, $output->getExtensionData( 'drafts' ) );
	}

	public function testAppendExtensionDataStillMergesOnlySetsAndSums(): void {
		// Documents why drafts are not appended: the only strategies are UNION,
		// which keeps values as a set of strings, and SUM for integers.
		$this->assertSame( [ 'union', 'sum' ], array_map(
			static fn ( MergeStrategy $strategy ): string => $strategy->value,
			MergeStrategy::cases()
		) );
	}

	public function testRenderedHtmlAccessorIsStillTheContentHolder(): void {
		// The collector reads ParserAfterTidy's text, but the same accessor is what
		// core hands out for rendered HTML; if this ever disappears the assumption
		// behind reading tidy output instead is worth revisiting.
		$this->assertTrue( method_exists( ParserOutput::class, 'getContentHolderText' ) );
		$this->assertSame( 'string', (string)( new \ReflectionMethod( ParserOutput::class, 'getContentHolderText' ) )->getReturnType() );
	}
}
