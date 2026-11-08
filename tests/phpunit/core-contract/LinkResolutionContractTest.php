<?php

declare( strict_types=1 );

namespace DiscordComponentEmbed\Tests\CoreContract;

use MediaWiki\Linker\Linker;
use MediaWiki\Title\Title;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * The link-resolution surface the button resolver delegates to.
 *
 * These are the assumptions that would silently produce buttons pointing at the
 * wrong place rather than failing.
 *
 * @license Unlicense
 */
class LinkResolutionContractTest extends TestCase {

	public function testNormalizeSubpageLinkExistsAndRewritesThroughTheThirdParameter(): void {
		$method = new ReflectionMethod( Linker::class, 'normalizeSubpageLink' );

		$this->assertTrue( $method->isStatic() );
		$this->assertTrue( $method->getNumberOfParameters() >= 3 );
		$this->assertTrue( $method->getParameters()[2]->isPassedByReference() );
	}

	public function testASubpageRelativeLinkResolvesAgainstTheContextPage(): void {
		$context = Title::newFromText( 'Talk:Foo/Bar' );
		$text = '';

		$rewritten = Linker::normalizeSubpageLink( $context, '/Sub', $text );

		$this->assertSame( 'Talk:Foo/Bar/Sub', $rewritten );
	}

	public function testAParentRelativeLinkLosesOnePathSegment(): void {
		$context = Title::newFromText( 'Talk:Foo/Bar' );
		$text = '';

		$this->assertSame( 'Talk:Foo/Sib', Linker::normalizeSubpageLink( $context, '../Sib', $text ) );
	}

	public function testAFragmentIsCarriedThroughTheRewrite(): void {
		$context = Title::newFromText( 'Talk:Foo/Bar' );
		$text = '';

		$this->assertSame( 'Talk:Foo/Bar/Sub#Bit', Linker::normalizeSubpageLink( $context, '/Sub#Bit', $text ) );
	}

	public function testInANamespaceWithoutSubpagesTheRelativeFormIsLeftAloneAndIsNotATitle(): void {
		$context = Title::newFromText( 'Foo/Bar' );
		$text = '';

		$this->assertSame( '../Sib', Linker::normalizeSubpageLink( $context, '../Sib', $text ) );
		$this->assertNull( Title::newFromText( '../Sib' ), 'core renders this form as plain text' );
	}

	public function testAGetFullUrlIncludesTheFragment(): void {
		$title = Title::newFromText( 'Doorman#Abilities' );

		$this->assertNotNull( $title );
		$this->assertStringContainsString( '#Abilities', $title->getFullURL() );
	}

	public function testAFragmentOnlyTitleHasNoTextAndCannotBeTurnedIntoAUrlOnItsOwn(): void {
		$title = Title::newFromText( '#Abilities' );

		$this->assertNotNull( $title );
		$this->assertSame( '', $title->getText() );
	}

	public function testAnInvalidTitleIsNullWhichIsHowPlainTextIsDetected(): void {
		$this->assertNull( Title::newFromText( '[]' ) );
	}

	public function testInterwikiTitlesCarryTheirOwnUrlHost(): void {
		$title = Title::newFromText( 'commons:File:Foo.png' );

		if ( $title === null || !$title->isExternal() ) {
			$this->markTestSkipped( 'no interwiki map in this install' );
		}

		$this->assertStringStartsWith( 'http', $title->getFullURL() );
	}
}
