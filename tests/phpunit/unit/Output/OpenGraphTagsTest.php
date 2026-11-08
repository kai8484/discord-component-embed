<?php

declare( strict_types=1 );

namespace DiscordComponentEmbed\Tests\Unit\Output;

use DiscordComponentEmbed\Media\ResolvedMedia;
use DiscordComponentEmbed\Output\OpenGraphTags;
use PHPUnit\Framework\TestCase;

/**
 * The metadata fallback: title, description and image for software that reads only
 * tags.
 *
 * @license Unlicense
 */
final class OpenGraphTagsTest extends TestCase {

	private function tags( ?ResolvedMedia $image = null, string $description = 'A game about doors.' ): array {
		return ( new OpenGraphTags() )->build(
			'Doorman',
			$description,
			$image,
			'https://wiki.example/wiki/Doorman'
		);
	}

	public function testTitleDescriptionTypeAndUrlAreAlwaysPresent(): void {
		$tags = $this->tags();

		$this->assertSame( 'Doorman', $tags['og:title'] );
		$this->assertSame( 'A game about doors.', $tags['og:description'] );
		$this->assertSame( 'website', $tags['og:type'] );
		$this->assertSame( 'https://wiki.example/wiki/Doorman', $tags['og:url'] );
	}

	public function testAnImageBringsItsDimensions(): void {
		$tags = $this->tags( new ResolvedMedia( 'https://wiki.example/door.png', null, false, 1200, 630 ) );

		$this->assertSame( 'https://wiki.example/door.png', $tags['og:image'] );
		$this->assertSame( '1200', $tags['og:image:width'] );
		$this->assertSame( '630', $tags['og:image:height'] );
	}

	public function testAnImageWithoutKnownDimensionsStillProvidesTheUrl(): void {
		$tags = $this->tags( new ResolvedMedia( 'https://wiki.example/door.png' ) );

		$this->assertSame( 'https://wiki.example/door.png', $tags['og:image'] );
		$this->assertArrayNotHasKey( 'og:image:width', $tags );
		$this->assertArrayNotHasKey( 'og:image:height', $tags );
	}

	public function testNoImageMeansNoImageTags(): void {
		$tags = $this->tags();

		$this->assertArrayNotHasKey( 'og:image', $tags );
		$this->assertArrayNotHasKey( 'og:image:width', $tags );
	}

	public function testAnEmptyDescriptionIsOmittedRatherThanEmittedEmpty(): void {
		$tags = $this->tags( null, '' );

		$this->assertArrayNotHasKey( 'og:description', $tags );
	}

	public function testAnEmptyTitleIsOmittedRatherThanEmittedEmpty(): void {
		$tags = ( new OpenGraphTags() )->build( '', 'A game.', null, 'https://wiki.example/wiki/Doorman' );

		$this->assertArrayNotHasKey( 'og:title', $tags );
		$this->assertSame( 'A game.', $tags['og:description'] );
	}

	public function testValuesAreStoredUnescapedBecauseTheRendererEscapesAttributes(): void {
		$tags = ( new OpenGraphTags() )->build(
			'He said "hello" & left',
			"Line one\nLine two",
			null,
			'https://wiki.example/wiki/Doorman'
		);

		$this->assertSame( 'He said "hello" & left', $tags['og:title'] );
		$this->assertSame( 'Line one Line two', $tags['og:description'] );
	}

	public function testItDoesNotClaimSiteNameOrTwitterCardWhichCoreOwns(): void {
		$tags = $this->tags();

		$this->assertArrayNotHasKey( 'og:site_name', $tags );
		$this->assertArrayNotHasKey( 'twitter:card', $tags );
	}
}
