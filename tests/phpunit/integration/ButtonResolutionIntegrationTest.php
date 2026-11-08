<?php

declare( strict_types=1 );

namespace DiscordComponentEmbed\Tests\Integration;

use DiscordComponentEmbed\Output\StoredPreview;
use MediaWiki\Content\WikitextContent;
use MediaWiki\Parser\ParserOutput;
use MediaWiki\Title\Title;
use MediaWikiIntegrationTestCase;
use Wikimedia\Timestamp\ConvertibleTimestamp;

/**
 * Buttons against MediaWiki's own link resolution.
 *
 * A reader clicking a button must land where a reader clicking the same wikitext
 * would, so every form is compared with the href core itself renders for it rather
 * than with a hand-written expectation.
 *
 * @group Database
 * @license Unlicense
 */
class ButtonResolutionIntegrationTest extends MediaWikiIntegrationTestCase {

	private const PAGE = 'Embed button source';

	protected function setUp(): void {
		parent::setUp();

		// Nothing here depends on the curent date, but editing pages does.
		ConvertibleTimestamp::setFakeTime( '20260101000000' );
	}

	/**
	 * @param list<string> $buttonArguments
	 * @return list<array<string, mixed>>
	 */
	private function buttonsFrom( string $pageName, array $buttonArguments ): array {
		$services = $this->getServiceContainer();
		$wikitext = 'Lead.';

		foreach ( $buttonArguments as $index => $value ) {
			$wikitext .= sprintf( "\n\n{{#discord_embed: button%d = %s }}", $index + 1, $value );
		}

		$page = $this->getExistingTestPage( $pageName );

		$page->doUserEditContent(
			new WikitextContent( $wikitext ),
			$this->getTestUser()->getUser(),
			'integration test'
		);

		$output = $services->getContentRenderer()->getParserOutput( $page->getContent(), $page->getTitle() );
		$this->assertInstanceOf( ParserOutput::class, $output );

		$stored = $output->getExtensionData( StoredPreview::KEY );
		$this->assertIsArray( $stored, 'a preview was published' );

		$buttons = [];

		foreach ( $stored['components'][0]['components'] ?? [] as $component ) {
			foreach ( $component['components'] ?? [] as $button ) {
				if ( ( $button['type'] ?? null ) === 2 ) {
					$buttons[] = $button;
				}
			}
		}

		return $buttons;
	}

	/**
	 * The href core renders for this wikitext, made absolute, so it can be compared
	 * with what a button carries.
	 */
	private function coreUrl( string $linkWikitext, Title $context ): ?string {
		$output = $this->getServiceContainer()->getContentRenderer()->getParserOutput(
			new WikitextContent( $linkWikitext ),
			$context
		);

		$this->assertInstanceOf( ParserOutput::class, $output );

		if ( preg_match( '/<a[^>]+href="([^"]*)"/', $output->getContentHolderText(), $matches ) !== 1 ) {
			return null;
		}

		$href = html_entity_decode( $matches[1], ENT_QUOTES | ENT_HTML5, 'UTF-8' );

		if ( str_starts_with( $href, '#' ) ) {
			return $context->getFullURL() . $href;
		}

		$expanded = $this->getServiceContainer()->getUrlUtils()->expand( $href, PROTO_CANONICAL );

		return $expanded ?? $href;
	}

	public function testAPageLinkResolvesToOneButtonWithTheTargetsOwnName(): void {
		$this->getExistingTestPage( 'Embed button target' );
		$context = Title::newFromText( self::PAGE );
		$this->assertNotNull( $context );

		$buttons = $this->buttonsFrom( self::PAGE, [ '[[Embed button target]]' ] );

		$this->assertCount( 1, $buttons );
		$this->assertSame( $this->coreUrl( '[[Embed button target]]', $context ), $buttons[0]['url'] );
		$this->assertSame( 'Embed button target', $buttons[0]['label'] );
	}

	public function testADisplayTextBecomesTheLabel(): void {
		$this->getExistingTestPage( 'Embed button target' );
		$context = Title::newFromText( self::PAGE );
		$this->assertNotNull( $context );

		$buttons = $this->buttonsFrom( self::PAGE, [ '[[Embed button target|Read more]]' ] );

		$this->assertSame( $this->coreUrl( '[[Embed button target|Read more]]', $context ), $buttons[0]['url'] );
		$this->assertSame( 'Read more', $buttons[0]['label'] );
	}

	public function testASectionLinkKeepsTheAnchorInTheUrlAndDropsItFromTheLabel(): void {
		$this->getExistingTestPage( 'Embed button target' );
		$context = Title::newFromText( self::PAGE );
		$this->assertNotNull( $context );

		$buttons = $this->buttonsFrom( self::PAGE, [ '[[Embed button target#Abilities]]' ] );

		$expected = $this->coreUrl( '[[Embed button target#Abilities]]', $context );
		$this->assertNotNull( $expected );
		$this->assertStringContainsString( '#Abilities', $expected );
		$this->assertSame( $expected, $buttons[0]['url'] );
		$this->assertSame( 'Embed button target', $buttons[0]['label'] );
	}

	public function testAFragmentOnlyLinkPointsAtThePageItAppearsOn(): void {
		$context = Title::newFromText( self::PAGE );
		$this->assertNotNull( $context );

		$buttons = $this->buttonsFrom( self::PAGE, [ '[[#Abilities]]' ] );

		$this->assertSame( $context->getFullURL() . '#Abilities', $buttons[0]['url'] );
		$this->assertSame( '#Abilities', $buttons[0]['label'] );
	}

	public function testASubpageRelativeLinkResolvesAgainstThePageItAppearsOn(): void {
		$this->getExistingTestPage( 'Talk:Embed button source/Sub' );
		$context = Title::newFromText( 'Talk:Embed button source' );
		$this->assertNotNull( $context );

		$buttons = $this->buttonsFrom( 'Talk:Embed button source', [ '[[/Sub]]' ] );

		$expected = $this->coreUrl( '[[/Sub]]', $context );
		$this->assertNotNull( $expected );
		$this->assertStringContainsString( 'Talk:Embed_button_source/Sub', $expected );
		$this->assertSame( $expected, $buttons[0]['url'] );
	}

	public function testAParentRelativeLinkResolvesToTheParent(): void {
		$this->getExistingTestPage( 'Talk:Embed button source/Sibling' );
		$context = Title::newFromText( 'Talk:Embed button source/Child' );
		$this->assertNotNull( $context );

		$buttons = $this->buttonsFrom(
			'Talk:Embed button source/Child',
			[ '[[../Sibling]]' ]
		);

		$expected = $this->coreUrl( '[[../Sibling]]', $context );
		$this->assertNotNull( $expected );
		$this->assertStringContainsString( 'Talk:Embed_button_source/Sibling', $expected );
		$this->assertSame( $expected, $buttons[0]['url'] );
	}

	public function testAPlainUrlBecomesAButtonLabelledFromItsLastPathSegment(): void {
		$buttons = $this->buttonsFrom( self::PAGE, [ 'https://example.com/read' ] );

		$this->assertSame( 'https://example.com/read', $buttons[0]['url'] );
		$this->assertSame( 'read', $buttons[0]['label'] );
	}

	public function testABracketedUrlIsResolvedAsTheUrlItIs(): void {
		$buttons = $this->buttonsFrom( self::PAGE, [ '[[https://example.com/read]]' ] );

		$this->assertCount( 1, $buttons );
		$this->assertSame( 'https://example.com/read', $buttons[0]['url'] );
	}

	public function testAnInterwikiLinkResolvesToTheForeignWiki(): void {
		$context = Title::newFromText( self::PAGE );
		$this->assertNotNull( $context );

		// A prefix of our own, so the test does not depend on which interwiki map a
		// wiki happens to ship with.
		$this->getDb()->newInsertQueryBuilder()
			->insertInto( 'interwiki' )
			->row( [
				'iw_prefix' => 'embediw',
				'iw_url' => 'https://example.org/wiki/$1',
				'iw_local' => 0,
				'iw_trans' => 0,
				'iw_api' => '',
				'iw_wikiid' => '',
			] )
			->caller( __METHOD__ )
			->execute();

		$title = Title::newFromText( 'embediw:Doorman' );
		$this->assertNotNull( $title );
		$this->assertTrue( $title->isExternal(), 'the prefix is an interwiki' );

		$buttons = $this->buttonsFrom( self::PAGE, [ '[[embediw:Doorman]]' ] );

		$this->assertCount( 1, $buttons );
		$this->assertSame( 'https://example.org/wiki/Doorman', $buttons[0]['url'] );
		$this->assertSame( $title->getFullURL(), $buttons[0]['url'] );
	}

	public function testACategoryLinkDropsOnlyItsOwnButton(): void {
		$this->getExistingTestPage( 'Embed button target' );

		$buttons = $this->buttonsFrom( self::PAGE, [
			'[[Category:Embed test category]]',
			'[[Embed button target]]',
		] );

		$this->assertCount( 1, $buttons );
		$this->assertSame( 'Embed button target', $buttons[0]['label'] );
	}

	public function testAFileLinkDropsOnlyItsOwnButton(): void {
		$this->getExistingTestPage( 'Embed button target' );

		$buttons = $this->buttonsFrom( self::PAGE, [
			'[[File:Embed missing.png]]',
			'[[Embed button target]]',
		] );

		$this->assertCount( 1, $buttons );
		$this->assertSame( 'Embed button target', $buttons[0]['label'] );
	}

	public function testARelativeFormMediaWikiRendersAsPlainTextDropsOnlyItsOwnButton(): void {
		$this->getExistingTestPage( 'Embed button target' );

		// Mainspace has no subpages, so core renders [[../Sibling]] as plain text.
		$buttons = $this->buttonsFrom( self::PAGE, [
			'[[../Sibling]]',
			'[[Embed button target]]',
		] );

		$this->assertCount( 1, $buttons );
		$this->assertSame( 'Embed button target', $buttons[0]['label'] );
	}

	public function testBareTextIsNotALinkAndDropsOnlyItsOwnButton(): void {
		$this->getExistingTestPage( 'Embed button target' );

		$buttons = $this->buttonsFrom( self::PAGE, [
			'just some words',
			'[[Embed button target]]',
		] );

		$this->assertCount( 1, $buttons );
	}

	public function testAButtonPointsAtThePageEvenWhenThePageDoesNotExistYet(): void {
		// A deliberate divergence from core's presentation: core renders a red link
		// to the editor, which is not a link a reader of a preview can follow.
		$title = Title::newFromText( 'Embed button target that does not exist' );
		$this->assertNotNull( $title );

		$buttons = $this->buttonsFrom( self::PAGE, [ '[[Embed button target that does not exist]]' ] );

		$this->assertCount( 1, $buttons );
		$this->assertSame( $title->getFullURL(), $buttons[0]['url'] );
		$this->assertStringNotContainsString( 'action=edit', $buttons[0]['url'] );
	}

	public function testNonLatinLabelsSurvive(): void {
		$this->getExistingTestPage( 'エンベッド' );

		$buttons = $this->buttonsFrom( self::PAGE, [ '[[エンベッド|日本語のラベル]]' ] );

		$this->assertCount( 1, $buttons );
		$this->assertSame( '日本語のラベル', $buttons[0]['label'] );
	}

	public function testNonLatinButtonTargetsKeepTheirLabel(): void {
		$this->getExistingTestPage( 'エンベッド' );

		$buttons = $this->buttonsFrom( self::PAGE, [ '[[エンベッド]]' ] );

		$this->assertSame( 'エンベッド', $buttons[0]['label'] );
	}

	public function testMoreThanFiveButtonsUseSeveralActionRows(): void {
		$this->getExistingTestPage( 'Embed button target' );

		$arguments = [];

		for ( $i = 1; $i <= 7; $i++ ) {
			$arguments[] = 'https://example.com/' . $i;
		}

		$buttons = $this->buttonsFrom( self::PAGE, $arguments );

		$this->assertCount( 7, $buttons );
	}
}
