<?php

declare( strict_types=1 );

namespace DiscordComponentEmbed\Tests\Unit\Embed;

use DiscordComponentEmbed\Config\Limits;
use DiscordComponentEmbed\Embed\ComponentBuilder;
use DiscordComponentEmbed\Embed\ComponentCount;
use DiscordComponentEmbed\Embed\PreviewContent;
use DiscordComponentEmbed\Media\ResolvedMedia;
use PHPUnit\Framework\TestCase;

/**
 * @license Unlicense
 */
final class ComponentBuilderTest extends TestCase {

	private function builder(): ComponentBuilder {
		return new ComponentBuilder();
	}

	/**
	 * @param list<array<string, mixed>> $components
	 */
	private function findByType( array $components, int $type ): ?array {
		foreach ( $components as $component ) {
			if ( ( $component['type'] ?? null ) === $type ) {
				return $component;
			}
		}

		return null;
	}

	public function testThePayloadIsOneContainer(): void {
		$components = $this->builder()->build( new PreviewContent( title: '**Doorman**' ) );

		$this->assertCount( 1, $components );
		$this->assertSame( Limits::TYPE_CONTAINER, $components[0]['type'] );
	}

	public function testNothingToRenderYieldsNoComponents(): void {
		$this->assertSame( [], $this->builder()->build( new PreviewContent() ) );
	}

	public function testTitleAndDescriptionAreSeparateTextDisplays(): void {
		$components = $this->builder()->build(
			new PreviewContent( title: '**Doorman**', description: 'A game.' )
		);

		$container = $components[0];
		$this->assertSame( Limits::TYPE_TEXT_DISPLAY, $container['components'][0]['type'] );
		$this->assertSame( '**Doorman**', $container['components'][0]['content'] );
		$this->assertSame( Limits::TYPE_TEXT_DISPLAY, $container['components'][1]['type'] );
		$this->assertSame( 'A game.', $container['components'][1]['content'] );
	}

	public function testADescriptionWithoutATitleStillRenders(): void {
		$components = $this->builder()->build( new PreviewContent( description: 'Only a description.' ) );

		$this->assertCount( 1, $components[0]['components'] );
		$this->assertSame( 'Only a description.', $components[0]['components'][0]['content'] );
	}

	public function testAnImageBecomesASectionWithAThumbnailAccessory(): void {
		$components = $this->builder()->build(
			new PreviewContent(
				title: '**Doorman**',
				description: 'A game.',
				image: new ResolvedMedia( 'https://wiki.example/images/door.png', 'A door' )
			)
		);

		$section = $this->findByType( $components[0]['components'], Limits::TYPE_SECTION );
		$this->assertNotNull( $section );
		$this->assertCount( 2, $section['components'] );
		$this->assertSame( '**Doorman**', $section['components'][0]['content'] );
		$this->assertSame( Limits::TYPE_THUMBNAIL, $section['accessory']['type'] );
		$this->assertSame( 'https://wiki.example/images/door.png', $section['accessory']['media']['url'] );
		$this->assertSame( 'A door', $section['accessory']['description'] );
	}

	public function testAnImageWithoutACaptionOmitsTheDescriptionField(): void {
		$components = $this->builder()->build(
			new PreviewContent( title: '**Doorman**', image: new ResolvedMedia( 'https://wiki.example/a.png' ) )
		);

		$section = $this->findByType( $components[0]['components'], Limits::TYPE_SECTION );
		$this->assertArrayNotHasKey( 'description', $section['accessory'] );
	}

	public function testASpoilerThumbnailIsMarked(): void {
		$components = $this->builder()->build(
			new PreviewContent(
				title: '**Doorman**',
				image: new ResolvedMedia( 'https://wiki.example/a.png', null, true )
			)
		);

		$section = $this->findByType( $components[0]['components'], Limits::TYPE_SECTION );
		$this->assertTrue( $section['accessory']['spoiler'] );
	}

	public function testGalleryItemsBecomeAMediaGallery(): void {
		$components = $this->builder()->build(
			new PreviewContent(
				title: '**Doorman**',
				gallery: [
					new ResolvedMedia( 'https://wiki.example/one.png', 'First' ),
					new ResolvedMedia( 'https://wiki.example/two.png', null, true ),
				]
			)
		);

		$gallery = $this->findByType( $components[0]['components'], Limits::TYPE_MEDIA_GALLERY );
		$this->assertNotNull( $gallery );
		$this->assertCount( 2, $gallery['items'] );
		$this->assertSame( 'https://wiki.example/one.png', $gallery['items'][0]['media']['url'] );
		$this->assertSame( 'First', $gallery['items'][0]['description'] );
		$this->assertTrue( $gallery['items'][1]['spoiler'] );
	}

	public function testAFooterGetsADividerAndTheSmallPrint(): void {
		$components = $this->builder()->build( new PreviewContent(
			title: '**Doorman**',
			footer: '-# From the wiki - [join our discord](https://example.com)'
		) );

		$children = $components[0]['components'];
		$types = array_map( static fn ( array $c ): int => $c['type'], $children );

		$this->assertSame(
			[ Limits::TYPE_TEXT_DISPLAY, Limits::TYPE_SEPARATOR, Limits::TYPE_TEXT_DISPLAY ],
			$types
		);
		$this->assertTrue( $children[1]['divider'] );
		$this->assertSame( '-# From the wiki - [join our discord](https://example.com)', $children[2]['content'] );
	}

	public function testNoFooterMeansNoDivider(): void {
		$components = $this->builder()->build( new PreviewContent( title: '**Doorman**' ) );

		$this->assertNull( $this->findByType( $components[0]['components'], Limits::TYPE_SEPARATOR ) );
	}

	public function testAccentIsEmittedOnlyWhenSet(): void {
		$without = $this->builder()->build( new PreviewContent( title: '**Doorman**' ) );
		$this->assertArrayNotHasKey( 'accent_color', $without[0] );

		$with = $this->builder()->build( new PreviewContent( title: '**Doorman**', accent: 0x5865F2 ) );
		$this->assertSame( 0x5865F2, $with[0]['accent_color'] );
	}

	public function testContainerSpoilerIsEmittedOnlyWhenSet(): void {
		$without = $this->builder()->build( new PreviewContent( title: '**Doorman**' ) );
		$this->assertArrayNotHasKey( 'spoiler', $without[0] );

		$with = $this->builder()->build( new PreviewContent( title: '**Doorman**', spoiler: true ) );
		$this->assertTrue( $with[0]['spoiler'] );
	}

	public function testContentOrderIsTitleDescriptionGallerySeparatorFooter(): void {
		$components = $this->builder()->build(
			new PreviewContent(
				title: '**Doorman**',
				description: 'A game.',
				gallery: [ new ResolvedMedia( 'https://wiki.example/one.png' ) ],
				footer: '-# From the wiki - [join our discord](https://example.com)'
			)
		);

		$types = array_column( $components[0]['components'], 'type' );
		$this->assertSame(
			[ Limits::TYPE_TEXT_DISPLAY, Limits::TYPE_TEXT_DISPLAY, Limits::TYPE_MEDIA_GALLERY, Limits::TYPE_SEPARATOR, Limits::TYPE_TEXT_DISPLAY ],
			$types
		);
	}

	public function testAnImageWithNoTextAtAllStillAppearsAsAGalleryItem(): void {
		// A Thumbnail is only legal as a Section accessory, and a Section needs at
		// least one Text Display for it to be an accessory to.
		$components = $this->builder()->build(
			new PreviewContent( image: new ResolvedMedia( 'https://wiki.example/door.png' ) )
		);

		$gallery = $this->findByType( $components[0]['components'], Limits::TYPE_MEDIA_GALLERY );
		$this->assertNotNull( $gallery );
		$this->assertCount( 1, $gallery['items'] );
		$this->assertSame( 'https://wiki.example/door.png', $gallery['items'][0]['media']['url'] );
	}

	public function testAStandaloneImageTakesTheLastGallerySlotWhenTheGalleryIsFull(): void {
		// The image the author named outranks the tenth gallery item, so it takes that
		// slot instead of being dropped without a word.
		$gallery = [];
		for ( $i = 1; $i <= Limits::MAX_GALLERY_ITEMS; $i++ ) {
			$gallery[] = new ResolvedMedia( 'https://wiki.example/' . $i . '.png' );
		}

		$components = $this->builder()->build( new PreviewContent(
			image: new ResolvedMedia( 'https://wiki.example/door.png' ),
			gallery: $gallery
		) );

		$built = $this->findByType( $components[0]['components'], Limits::TYPE_MEDIA_GALLERY );
		$this->assertCount( Limits::MAX_GALLERY_ITEMS, $built['items'] );
		$this->assertSame( 'https://wiki.example/door.png', $built['items'][0]['media']['url'] );
		$this->assertSame(
			'https://wiki.example/' . ( Limits::MAX_GALLERY_ITEMS - 1 ) . '.png',
			$built['items'][ Limits::MAX_GALLERY_ITEMS - 1 ]['media']['url'],
			'the last gallery item gave up its slot'
		);
	}

	public function testAFullPayloadGivesUpGalleryItemsToStayInsideTheCap(): void {
		$gallery = [];
		for ( $i = 1; $i <= Limits::MAX_GALLERY_ITEMS; $i++ ) {
			$gallery[] = new ResolvedMedia( 'https://wiki.example/' . $i . '.png' );
		}

		$components = $this->builder()->build( new PreviewContent(
			title: '**Doorman**',
			description: 'A game.',
			gallery: $gallery,
			footer: '-# From the wiki'
		) );

		$this->assertLessThanOrEqual( Limits::MAX_COMPONENTS, ComponentCount::of( $components ) );
	}

}
