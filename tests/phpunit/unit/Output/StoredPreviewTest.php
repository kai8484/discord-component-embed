<?php

declare( strict_types=1 );

namespace DiscordComponentEmbed\Tests\Unit\Output;

use DiscordComponentEmbed\Content\Description;
use DiscordComponentEmbed\Embed\Preview;
use DiscordComponentEmbed\Media\ResolvedMedia;
use DiscordComponentEmbed\Output\StoredPreview;
use PHPUnit\Framework\TestCase;

/**
 * What the parser cache keeps. A wiki publishing only Open Graph tags stores no
 * components at all, and that record still has to survive the round trip or the
 * page loses its meta tags too.
 *
 * @license Unlicense
 */
final class StoredPreviewTest extends TestCase {

	private function preview( ?ResolvedMedia $image = null ): Preview {
		return new Preview(
			titleMarkdown: '**Doorman**',
			titlePlain: 'Doorman',
			description: new Description( 'A game.', 'A game.' ),
			image: $image
		);
	}

	public function testAPreviewWithComponentsSurvivesTheRoundTrip(): void {
		$stored = StoredPreview::fromArray(
			StoredPreview::create( $this->preview(), [ [ 'type' => 17, 'components' => [] ] ] )->toArray()
		);

		$this->assertNotNull( $stored );
		$this->assertSame( 'Doorman', $stored->title() );
		$this->assertSame( 'A game.', $stored->description() );
		$this->assertCount( 1, $stored->components() );
	}

	public function testAPreviewWithoutComponentsIsStillReadable(): void {
		$stored = StoredPreview::fromArray( StoredPreview::create( $this->preview(), [] )->toArray() );

		$this->assertNotNull( $stored, 'the meta tags depend on this record existing' );
		$this->assertSame( [], $stored->components() );
		$this->assertSame( 'Doorman', $stored->title() );
	}

	public function testTheImageSurvivesWithItsDimensions(): void {
		$stored = StoredPreview::fromArray(
			StoredPreview::create( $this->preview( new ResolvedMedia( 'https://x/a.png', null, false, 800, 450 ) ), [] )->toArray()
		);

		$this->assertNotNull( $stored );
		$this->assertSame( 'https://x/a.png', $stored->image()?->url );
		$this->assertSame( 800, $stored->image()?->width );
	}

	public function testAnUnknownVersionIsNotRead(): void {
		$record = StoredPreview::create( $this->preview(), [] )->toArray();
		$record['version'] = 99;

		$this->assertNull( StoredPreview::fromArray( $record ) );
	}

	public function testAMissingComponentListIsNotRead(): void {
		$this->assertNull( StoredPreview::fromArray( [ 'version' => 1, 'title' => 'Doorman' ] ) );
	}

}
