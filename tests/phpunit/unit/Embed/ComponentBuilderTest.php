<?php

declare( strict_types=1 );

namespace DiscordComponentEmbed\Tests\Unit\Embed;

use DiscordComponentEmbed\Config\Limits;
use DiscordComponentEmbed\Embed\ActionRowPacker;
use DiscordComponentEmbed\Embed\ComponentBuilder;
use DiscordComponentEmbed\Embed\PreviewContent;
use DiscordComponentEmbed\Embed\ResolvedButton;
use DiscordComponentEmbed\Media\ResolvedMedia;
use PHPUnit\Framework\TestCase;

/**
 * @license Unlicense
 */
final class ComponentBuilderTest extends TestCase {

	private function builder(): ComponentBuilder {
		return new ComponentBuilder( new ActionRowPacker() );
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

	public function testButtonsBecomeLinkButtonsInActionRows(): void {
		$components = $this->builder()->build(
			new PreviewContent(
				title: '**Doorman**',
				buttons: [ new ResolvedButton( 'https://wiki.example/Doorman', 'Doorman' ) ]
			)
		);

		$row = $this->findByType( $components[0]['components'], Limits::TYPE_ACTION_ROW );
		$this->assertNotNull( $row );
		$this->assertSame( Limits::TYPE_BUTTON, $row['components'][0]['type'] );
		$this->assertSame( Limits::BUTTON_STYLE_LINK, $row['components'][0]['style'] );
		$this->assertSame( 'Doorman', $row['components'][0]['label'] );
		$this->assertSame( 'https://wiki.example/Doorman', $row['components'][0]['url'] );
	}

	public function testSixButtonsProduceTwoActionRows(): void {
		$buttons = [];

		for ( $i = 1; $i <= 6; $i++ ) {
			$buttons[] = new ResolvedButton( 'https://example.com/' . $i, 'B' . $i );
		}

		$components = $this->builder()->build( new PreviewContent( title: '**Doorman**', buttons: $buttons ) );
		$rows = array_values( array_filter(
			$components[0]['components'],
			static fn ( array $component ): bool => $component['type'] === Limits::TYPE_ACTION_ROW
		) );

		$this->assertCount( 2, $rows );
		$this->assertCount( 5, $rows[0]['components'] );
		$this->assertCount( 1, $rows[1]['components'] );
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

	public function testContentOrderIsTitleThenDescriptionThenGalleryThenButtons(): void {
		$components = $this->builder()->build(
			new PreviewContent(
				title: '**Doorman**',
				description: 'A game.',
				gallery: [ new ResolvedMedia( 'https://wiki.example/one.png' ) ],
				buttons: [ new ResolvedButton( 'https://wiki.example/Doorman', 'Doorman' ) ]
			)
		);

		$types = array_column( $components[0]['components'], 'type' );
		$this->assertSame(
			[ Limits::TYPE_TEXT_DISPLAY, Limits::TYPE_TEXT_DISPLAY, Limits::TYPE_MEDIA_GALLERY, Limits::TYPE_ACTION_ROW ],
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

	public function testAFullPayloadGivesUpGalleryItemsButKeepsEveryButton(): void {
		$buttons = [];
		for ( $i = 1; $i <= Limits::MAX_BUTTONS; $i++ ) {
			$buttons[] = new ResolvedButton( 'https://example.com/' . $i, 'B' . $i );
		}

		$gallery = [];
		for ( $i = 1; $i <= Limits::MAX_GALLERY_ITEMS; $i++ ) {
			$gallery[] = new ResolvedMedia( 'https://wiki.example/' . $i . '.png' );
		}

		$components = $this->builder()->build( new PreviewContent(
			title: '**Doorman**',
			description: 'A game.',
			image: new ResolvedMedia( 'https://wiki.example/door.png' ),
			gallery: $gallery,
			buttons: $buttons
		) );

		$rows = array_values( array_filter(
			$components[0]['components'],
			static fn ( array $component ): bool => $component['type'] === Limits::TYPE_ACTION_ROW
		) );
		$this->assertCount( Limits::MAX_ACTION_ROWS, $rows );

		$buttonTotal = 0;
		foreach ( $rows as $row ) {
			$buttonTotal += count( $row['components'] );
		}
		$this->assertSame( Limits::MAX_BUTTONS, $buttonTotal );
	}
}
