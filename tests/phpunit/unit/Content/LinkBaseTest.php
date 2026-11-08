<?php

declare( strict_types=1 );

namespace DiscordComponentEmbed\Tests\Unit\Content;

use DiscordComponentEmbed\Content\LinkBase;
use PHPUnit\Framework\TestCase;

/**
 * Preview links have to leave the wiki, so relative hrefs must be expanded.
 *
 * @license Unlicense
 */
final class LinkBaseTest extends TestCase {

	private function base(): LinkBase {
		return new LinkBase( 'https://wiki.example', 'https://wiki.example/index.php/Doorman' );
	}

	public function testAnArticlePathIsExpandedAgainstTheServer(): void {
		$this->assertSame(
			'https://wiki.example/index.php/Doorman',
			$this->base()->expand( '/index.php/Doorman' )
		);
	}

	public function testAShortArticlePathIsExpandedAgainstTheServer(): void {
		$this->assertSame( 'https://wiki.example/Hero', $this->base()->expand( '/Hero' ) );
	}

	public function testAFragmentPointsAtThePageItAppearsOn(): void {
		$this->assertSame(
			'https://wiki.example/index.php/Doorman#Abilities',
			$this->base()->expand( '#Abilities' )
		);
	}

	public function testAProtocolRelativeUrlGetsTheWikisScheme(): void {
		$this->assertSame(
			'https://upload.example/a.png',
			$this->base()->expand( '//upload.example/a.png' )
		);
	}

	public function testAnAbsoluteUrlIsLeftAlone(): void {
		$this->assertSame(
			'https://example.com/read',
			$this->base()->expand( 'https://example.com/read' )
		);
	}

	public function testAnEmailLinkIsLeftAlone(): void {
		$this->assertSame( 'mailto:someone@example.com', $this->base()->expand( 'mailto:someone@example.com' ) );
	}

	public function testAnEmptyHrefStaysEmpty(): void {
		$this->assertSame( '', $this->base()->expand( '  ' ) );
	}

	public function testATrailingSlashOnTheServerIsNotDoubled(): void {
		$base = new LinkBase( 'https://wiki.example/', 'https://wiki.example/index.php/Doorman' );

		$this->assertSame( 'https://wiki.example/index.php/Doorman', $base->expand( '/index.php/Doorman' ) );
	}

	public function testAProtocolRelativeServerStillProducesAbsoluteLinks(): void {
		// "$wgServer = '//wiki.example'" is common; Discord refuses a link or button
		// without an explicit scheme, so the scheme is filled in here.
		$base = new \DiscordComponentEmbed\Content\LinkBase( '//wiki.example', '//wiki.example/index.php/Foo' );

		$this->assertSame( 'https://wiki.example/wiki/Doorman', $base->expand( '/wiki/Doorman' ) );
		$this->assertSame( 'https://wiki.example/index.php/Foo#Abilities', $base->expand( '#Abilities' ) );
	}

}
