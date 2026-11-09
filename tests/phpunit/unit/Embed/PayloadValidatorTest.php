<?php

declare( strict_types=1 );

namespace DiscordComponentEmbed\Tests\Unit\Embed;

use DiscordComponentEmbed\Config\Limits;
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
		return ( new ComponentBuilder() )->build( $content );
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

	/**
	 * A container holding one action row, so the validator's button rules can be
	 * exercised independently of what the builder now emits.
	 *
	 * @param list<array<string, mixed>> $buttons
	 * @return list<array<string, mixed>>
	 */
	private function payloadWithButtons( array $buttons ): array {
		return [ [
			'type' => Limits::TYPE_CONTAINER,
			'components' => [
				[ 'type' => Limits::TYPE_TEXT_DISPLAY, 'content' => '**Doorman**' ],
				[ 'type' => Limits::TYPE_ACTION_ROW, 'components' => $buttons ],
			],
		] ];
	}

	/** @return array<string, mixed> */
	private function linkButton( string $url, string $label ): array {
		return [ 'type' => Limits::TYPE_BUTTON, 'style' => Limits::BUTTON_STYLE_LINK, 'url' => $url, 'label' => $label ];
	}

	public function testAOrdinaryPreviewIsValid(): void {
		$problems = $this->problemsFor( new PreviewContent(
			title: '**Doorman**',
			description: 'A game about doors.',
			image: new ResolvedMedia( 'https://wiki.example/door.png', 'A door' ),
			gallery: [ new ResolvedMedia( 'https://wiki.example/one.png', 'First' ) ],
			accent: 0x5865F2
		) );

		$this->assertSame( [], $problems );
	}

	public function testNoComponentsAtAllIsNotAnError(): void {
		$this->assertSame( [], ( new PayloadValidator() )->validate( [] ) );
	}

	public function testALabelLongerThanEightyCharactersIsRejected(): void {
		$problems = ( new PayloadValidator() )->validate( $this->payloadWithButtons( [
			$this->linkButton( 'https://example.com/a', str_repeat( 'L', Limits::MAX_LABEL_CHARS + 1 ) ),
		] ) );

		$this->assertNotSame( [], $problems );
		$this->assertStringContainsString( 'label', implode( ' ', $problems ) );
	}

	public function testALabelOfExactlyEightyCharactersIsAccepted(): void {
		$problems = ( new PayloadValidator() )->validate( $this->payloadWithButtons( [
			$this->linkButton( 'https://example.com/a', str_repeat( 'L', Limits::MAX_LABEL_CHARS ) ),
		] ) );

		$this->assertSame( [], $problems );
	}

	public function testAUrlLongerThanFiveHundredAndTwelveCharactersIsRejected(): void {
		$problems = ( new PayloadValidator() )->validate( $this->payloadWithButtons( [
			$this->linkButton( 'https://example.com/' . str_repeat( 'a', Limits::MAX_URL_CHARS ), 'Too long' ),
		] ) );

		$this->assertNotSame( [], $problems );
		$this->assertStringContainsString( 'url', implode( ' ', $problems ) );
	}

	public function testANonHttpButtonUrlIsRejected(): void {
		$problems = ( new PayloadValidator() )->validate( $this->payloadWithButtons( [
			$this->linkButton( 'ftp://example.com/read', 'Read' ),
		] ) );

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
		$components = [ [ 'type' => Limits::TYPE_CONTAINER, 'components' => [] ] ];

		for ( $i = 0; $i < Limits::MAX_COMPONENTS; $i++ ) {
			$components[0]['components'][] = [ 'type' => Limits::TYPE_TEXT_DISPLAY, 'content' => 'x' . $i ];
		}

		$this->assertNotSame( [], ( new PayloadValidator() )->validate( $components ) );
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
