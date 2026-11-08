<?php

declare( strict_types=1 );

namespace DiscordComponentEmbed\Tests\Unit\Embed;

use DiscordComponentEmbed\Config\Limits;
use DiscordComponentEmbed\Embed\ActionRowPacker;
use DiscordComponentEmbed\Embed\ComponentBuilder;
use DiscordComponentEmbed\Embed\PayloadValidator;
use DiscordComponentEmbed\Embed\PreviewContent;
use DiscordComponentEmbed\Embed\ResolvedButton;
use DiscordComponentEmbed\Media\ResolvedMedia;
use PHPUnit\Framework\TestCase;

/**
 * Discord rejects a whole payload over one bad component, so an over-limit
 * configuration is rejected here rather than published and discarded.
 *
 * @license Unlicense
 */
final class PayloadValidatorTest extends TestCase {

	/** @return list<array<string, mixed>> */
	private function componentsFor( PreviewContent $content ): array {
		return ( new ComponentBuilder( new ActionRowPacker() ) )->build( $content );
	}

	/** @return list<string> */
	private function problemsFor( PreviewContent $content ): array {
		return ( new PayloadValidator() )->validate( $this->componentsFor( $content ) );
	}

	private function buttons( int $count, int $labelLength = 5 ): array {
		$buttons = [];

		for ( $i = 0; $i < $count; $i++ ) {
			$buttons[] = new ResolvedButton(
				'https://example.com/' . $i,
				substr( str_repeat( 'L', $labelLength ), 0, $labelLength )
			);
		}

		return $buttons;
	}

	public function testAOrdinaryPreviewIsValid(): void {
		$problems = $this->problemsFor( new PreviewContent(
			title: '**Doorman**',
			description: 'A game about doors.',
			image: new ResolvedMedia( 'https://wiki.example/door.png', 'A door' ),
			gallery: [ new ResolvedMedia( 'https://wiki.example/one.png', 'First' ) ],
			buttons: [ new ResolvedButton( 'https://wiki.example/Doorman', 'Doorman' ) ],
			accent: 0x5865F2
		) );

		$this->assertSame( [], $problems );
	}

	public function testNoComponentsAtAllIsNotAnError(): void {
		$this->assertSame( [], ( new PayloadValidator() )->validate( [] ) );
	}

	public function testALabelLongerThanEightyCharactersIsRejected(): void {
		$problems = $this->problemsFor( new PreviewContent(
			title: '**Doorman**',
			buttons: $this->buttons( 1, Limits::MAX_LABEL_CHARS + 1 )
		) );

		$this->assertNotSame( [], $problems );
		$this->assertStringContainsString( 'label', implode( ' ', $problems ) );
	}

	public function testALabelOfExactlyEightyCharactersIsAccepted(): void {
		$this->assertSame( [], $this->problemsFor( new PreviewContent(
			title: '**Doorman**',
			buttons: $this->buttons( 1, Limits::MAX_LABEL_CHARS )
		) ) );
	}

	public function testAUrlLongerThanFiveHundredAndTwelveCharactersIsRejected(): void {
		$problems = $this->problemsFor( new PreviewContent(
			title: '**Doorman**',
			buttons: [ new ResolvedButton(
				'https://example.com/' . str_repeat( 'a', Limits::MAX_URL_CHARS ),
				'Too long'
			) ]
		) );

		$this->assertNotSame( [], $problems );
		$this->assertStringContainsString( 'url', implode( ' ', $problems ) );
	}

	public function testANonHttpButtonUrlIsRejected(): void {
		$problems = $this->problemsFor( new PreviewContent(
			title: '**Doorman**',
			buttons: [ new ResolvedButton( 'ftp://example.com/read', 'Read' ) ]
		) );

		$this->assertNotSame( [], $problems );
		$this->assertStringContainsString( 'http', implode( ' ', $problems ) );
	}

	public function testAMediaDescriptionLongerThanOneThousandAndTwentyFourCharactersIsRejected(): void {
		$problems = $this->problemsFor( new PreviewContent(
			title: '**Doorman**',
			image: new ResolvedMedia(
				'https://wiki.example/a.png',
				str_repeat( 'a', Limits::MAX_MEDIA_DESCRIPTION_CHARS + 1 )
			)
		) );

		$this->assertNotSame( [], $problems );
		$this->assertStringContainsString( 'description', implode( ' ', $problems ) );
	}

	public function testAnAccentOutsideTheColourRangeIsRejected(): void {
		$problems = $this->problemsFor( new PreviewContent( title: '**Doorman**', accent: 0x1000000 ) );

		$this->assertNotSame( [], $problems );
		$this->assertStringContainsString( 'accent', implode( ' ', $problems ) );
	}

	public function testATooDeepPayloadIsRejected(): void {
		$buttons = $this->buttons( Limits::MAX_BUTTONS );
		$gallery = [];
		for ( $i = 0; $i < Limits::MAX_GALLERY_ITEMS; $i++ ) {
			$gallery[] = new ResolvedMedia( 'https://wiki.example/' . $i . '.png' );
		}

		$content = new PreviewContent(
			title: '**Doorman**',
			description: 'A game.',
			image: new ResolvedMedia( 'https://wiki.example/door.png' ),
			gallery: $gallery,
			buttons: $buttons
		);

		$components = $this->componentsFor( $content );
		$problems = ( new PayloadValidator() )->validate( $components );

		$this->assertSame( [], $problems, 'the builder must not produce a payload past Discord\'s component cap' );
		$this->assertLessThanOrEqual(
			Limits::MAX_COMPONENTS,
			$this->countComponents( $components )
		);
	}

	public function testMoreThanFiveActionRowsIsRejected(): void {
		$components = $this->componentsFor( new PreviewContent( title: '**Doorman**' ) );

		// Six rows of one button each: 30 buttons is past the 25 the configuration
		// layer already refuses, so build the rows directly.
		$rows = [];
		for ( $i = 0; $i < Limits::MAX_ACTION_ROWS + 1; $i++ ) {
			$rows[] = [
				'type' => Limits::TYPE_ACTION_ROW,
				'components' => [ $this->buttonComponent( 'https://example.com/' . $i, 'B' ) ],
			];
		}

		$components[0]['components'] = $rows;

		$problems = ( new PayloadValidator() )->validate( $components );

		$this->assertStringContainsString( 'row', strtolower( implode( ' ', $problems ) ) );
	}

	public function testSixButtonsInOneRowIsRejected(): void {
		$components = $this->componentsFor( new PreviewContent( title: '**Doorman**' ) );
		$components[0]['components'] = [ [
			'type' => Limits::TYPE_ACTION_ROW,
			'components' => array_map(
				fn ( int $i ): array => $this->buttonComponent( 'https://example.com/' . $i, 'B' ),
				range( 1, Limits::BUTTONS_PER_ROW + 1 )
			),
		] ];

		$problems = ( new PayloadValidator() )->validate( $components );

		$this->assertNotSame( [], $problems );
	}

	public function testAThumbnailDirectlyInAContainerIsRejected(): void {
		$components = [ [
			'type' => Limits::TYPE_CONTAINER,
			'components' => [ [
				'type' => Limits::TYPE_THUMBNAIL,
				'media' => [ 'url' => 'https://wiki.example/a.png' ],
			] ],
		] ];

		$problems = ( new PayloadValidator() )->validate( $components );

		$this->assertStringContainsString( 'thumbnail', strtolower( implode( ' ', $problems ) ) );
	}

	public function testASectionWithFourChildrenIsRejected(): void {
		$components = [ [
			'type' => Limits::TYPE_CONTAINER,
			'components' => [ [
				'type' => Limits::TYPE_SECTION,
				'components' => array_fill( 0, 4, [ 'type' => Limits::TYPE_TEXT_DISPLAY, 'content' => 'x' ] ),
				'accessory' => [
					'type' => Limits::TYPE_THUMBNAIL,
					'media' => [ 'url' => 'https://wiki.example/a.png' ],
				],
			] ],
		] ];

		$problems = ( new PayloadValidator() )->validate( $components );

		$this->assertNotSame( [], $problems );
	}

	public function testAnEmptyGalleryIsRejected(): void {
		$components = [ [
			'type' => Limits::TYPE_CONTAINER,
			'components' => [ [
				'type' => Limits::TYPE_MEDIA_GALLERY,
				'items' => [],
			] ],
		] ];

		$problems = ( new PayloadValidator() )->validate( $components );

		$this->assertNotSame( [], $problems );
	}

	public function testAnEmptyTextDisplayIsRejected(): void {
		$components = [ [
			'type' => Limits::TYPE_CONTAINER,
			'components' => [ [ 'type' => Limits::TYPE_TEXT_DISPLAY, 'content' => '' ] ],
		] ];

		$problems = ( new PayloadValidator() )->validate( $components );

		$this->assertNotSame( [], $problems );
	}

	public function testAnIllegalContainerChildIsRejected(): void {
		$components = [ [
			'type' => Limits::TYPE_CONTAINER,
			'components' => [ [ 'type' => 99, 'content' => 'nope' ] ],
		] ];

		$problems = ( new PayloadValidator() )->validate( $components );

		$this->assertNotSame( [], $problems );
	}

	public function testTheTopLevelMustBeExactlyOneContainer(): void {
		$problems = ( new PayloadValidator() )->validate( [
			[ 'type' => Limits::TYPE_TEXT_DISPLAY, 'content' => 'loose text' ],
		] );

		$this->assertNotSame( [], $problems );
	}

	private function buttonComponent( string $url, string $label ): array {
		return [
			'type' => Limits::TYPE_BUTTON,
			'style' => Limits::BUTTON_STYLE_LINK,
			'label' => $label,
			'url' => $url,
		];
	}

	/**
	 * @param list<array<string, mixed>> $components
	 */
	private function countComponents( array $components ): int {
		$count = 0;

		foreach ( $components as $component ) {
			$count++;

			foreach ( [ 'components', 'items' ] as $key ) {
				foreach ( $component[$key] ?? [] as $child ) {
					$count += $this->countComponents( [ $child ] );
				}
			}

			if ( isset( $component['accessory'] ) ) {
				$count += $this->countComponents( [ $component['accessory'] ] );
			}
		}

		return $count;
	}
}
