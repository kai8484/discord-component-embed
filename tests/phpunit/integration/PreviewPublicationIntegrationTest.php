<?php

declare( strict_types=1 );

namespace DiscordComponentEmbed\Tests\Integration;

use DiscordComponentEmbed\Content\Description;
use DiscordComponentEmbed\Embed\Preview;
use DiscordComponentEmbed\Media\ResolvedMedia;
use DiscordComponentEmbed\MediaWiki\PageImagesProvider;
use DiscordComponentEmbed\Output\StoredPreview;
use MediaWiki\Content\WikitextContent;
use MediaWiki\Context\RequestContext;
use MediaWiki\Output\OutputPage;
use MediaWiki\Parser\ParserOptions;
use MediaWiki\Parser\ParserOutput;
use MediaWiki\Request\FauxRequest;
use MediaWiki\Title\Title;
use MediaWikiIntegrationTestCase;

/**
 * What actually reaches the page: the payload in the head, the metadata fallback,
 * and the tags this extension must not duplicate or replace.
 *
 * @group Database
 * @license Unlicense
 */
class PreviewPublicationIntegrationTest extends MediaWikiIntegrationTestCase {

	private function parsePage( string $wikitext, string $pageName ): ParserOutput {
		$services = $this->getServiceContainer();
		$page = $this->getExistingTestPage( $pageName );

		$page->doUserEditContent(
			new WikitextContent( $wikitext ),
			$this->getTestUser()->getUser(),
			'integration test'
		);

		$output = $services->getContentRenderer()->getParserOutput( $page->getContent(), $page->getTitle() );
		$this->assertInstanceOf( ParserOutput::class, $output );

		return $output;
	}

	private function outputPageFor( ParserOutput $parserOutput, Title $title ): OutputPage {
		$context = new RequestContext();
		$context->setRequest( new FauxRequest() );
		$context->setLanguage( 'en' );
		$context->setTitle( $title );

		$output = new OutputPage( $context );
		$output->addParserOutput( $parserOutput, ParserOptions::newFromContext( $context ) );

		return $output;
	}

	/**
	 * A parsed page whose stored preview has been replaced, so the output hook can
	 * be tested against a preview with an image without uploading one.
	 */
	private function storedPreviewOutput( ?ResolvedMedia $image = null, string $title = 'Doorman' ): ParserOutput {
		$parserOutput = $this->parsePage(
			'A lead paragraph.',
			'Embed stored preview ' . ( $image === null ? 'without' : 'with' ) . ' image'
		);

		$preview = new Preview(
			titleMarkdown: '**' . $title . '**',
			titlePlain: $title,
			description: new Description( 'A game about doors.', 'A game about doors.' ),
			image: $image
		);

		$parserOutput->setExtensionData(
			StoredPreview::KEY,
			StoredPreview::create( $preview, [ [
				'type' => 17,
				'components' => [ [ 'type' => 10, 'content' => '**' . $title . '**' ] ],
			] ] )->toArray()
		);

		return $parserOutput;
	}

	/**
	 * @return array<string, string> Tag name to content.
	 */
	private function tagsOf( OutputPage $output ): array {
		$tags = [];

		foreach ( $output->getMetaTags() as [ $name, $value ] ) {
			$tags[$name] = $value;
		}

		return $tags;
	}

	public function testThePayloadIsPublishedAsOneScriptInTheHead(): void {
		$title = Title::newFromText( 'Embed publication page' );
		$this->assertNotNull( $title );

		$parserOutput = $this->parsePage( "A lead.\n\n{{#discord_embed: title = Published }}", 'Embed publication page' );
		$outputPage = $this->outputPageFor( $parserOutput, $title );

		$headItems = $outputPage->getHeadItemsArray();

		$this->assertArrayHasKey( 'discord:component-embed', $headItems );
		$this->assertStringContainsString( 'type="application/json"', $headItems['discord:component-embed'] );
		$this->assertStringContainsString( '{"component":{"type":17', $headItems['discord:component-embed'] );
	}

	public function testOpenGraphTagsArePublishedWhenNothingElseClaimsThem(): void {
		$this->overrideConfigValue( 'SkinMetaTags', [] );

		$title = Title::newFromText( 'Embed og page' );
		$this->assertNotNull( $title );

		$parserOutput = $this->parsePage( "A lead about doors.\n\n{{#discord_embed: title = Doorman }}", 'Embed og page' );
		$tags = $this->tagsOf( $this->outputPageFor( $parserOutput, $title ) );

		$this->assertSame( 'Doorman', $tags['og:title'] );
		$this->assertSame( 'A lead about doors.', $tags['og:description'] );
		$this->assertSame( 'website', $tags['og:type'] );
		$this->assertSame( $title->getFullURL(), $tags['og:url'] );
	}

	public function testLinksInThePayloadLeaveTheWiki(): void {
		// Parser output keeps internal links relative and the skin expands them when
		// it renders. A preview is read somewhere else entirely, so the extension has
		// to expand them itself or the card ships links that go nowhere.
		$this->getExistingTestPage( 'Embed link destination' );

		$parserOutput = $this->parsePage(
			'See [[Embed link destination|the destination]] and [[#Later]].',
			'Embed absolute links'
		);

		$stored = $parserOutput->getExtensionData( StoredPreview::KEY );
		$this->assertIsArray( $stored );

		$markdown = '';

		foreach ( $stored['components'][0]['components'] as $component ) {
			if ( ( $component['type'] ?? null ) === 10 && str_contains( $component['content'] ?? '', 'the destination' ) ) {
				$markdown = $component['content'];
			}
		}

		$this->assertNotSame( '', $markdown );
		$this->assertStringContainsString( '(http', $markdown, 'links are absolute' );
		$this->assertStringNotContainsString( '](/', $markdown, 'no relative link survives' );
		$this->assertStringNotContainsString( '](#', $markdown, 'not even a fragment link' );
	}

	public function testTheDescriptionInThePayloadAndInTheMetadataAgree(): void {
		$this->overrideConfigValue( 'SkinMetaTags', [] );

		$title = Title::newFromText( 'Embed parity page' );
		$this->assertNotNull( $title );

		$text = str_repeat( 'word ', 200 );
		$parserOutput = $this->parsePage( $text . "\n\n== Section ==\nAfter the heading.", 'Embed parity page' );

		$outputPage = $this->outputPageFor( $parserOutput, $title );
		$tags = $this->tagsOf( $outputPage );
		$stored = $parserOutput->getExtensionData( StoredPreview::KEY );

		$this->assertIsArray( $stored );
		$this->assertLessThanOrEqual( 300, mb_strlen( $tags['og:description'] ) );
		$this->assertStringEndsWith( '…', $tags['og:description'] );
		$this->assertStringNotContainsString( 'After the heading.', $tags['og:description'] );

		$payloadDescription = null;

		foreach ( $stored['components'][0]['components'] as $component ) {
			if ( ( $component['type'] ?? null ) === 10 && str_contains( $component['content'] ?? '', 'word' ) ) {
				$payloadDescription = $component['content'];
			}
		}

		$this->assertNotNull( $payloadDescription, 'the payload carries the same description' );
		$this->assertSame( $tags['og:description'], $payloadDescription );
	}

	public function testTagsTheSkinWillEmitAreNotDuplicated(): void {
		// Core adds these after our hook has already run, so the decision is made from
		// configuration rather than from what is on the page at the time.
		$this->overrideConfigValue( 'SkinMetaTags', [ 'og:title', 'og:type', 'og:site_name' ] );

		$title = Title::newFromText( 'Embed deferred page' );
		$this->assertNotNull( $title );

		$parserOutput = $this->parsePage( "A lead.\n\n{{#discord_embed: title = Mine }}", 'Embed deferred page' );
		$tags = $this->tagsOf( $this->outputPageFor( $parserOutput, $title ) );

		$this->assertArrayNotHasKey( 'og:title', $tags );
		$this->assertArrayNotHasKey( 'og:type', $tags );
		$this->assertArrayHasKey( 'og:description', $tags, 'keys nobody else emits are still filled in' );
	}

	public function testAnAuthoredImageIsPublishedEvenWhenPageImagesWouldEmitOne(): void {
		if ( !class_exists( 'PageImages\\PageImages' ) ) {
			$this->markTestSkipped( 'PageImages is not installed' );
		}

		// PageImages emits nothing on a page without a page image, which cannot be known
		// from this hook, so the image the preview resolved is published either way. If
		// the wiki's own tag turns up as well, the dedupe pass gives ours back.
		$this->overrideConfigValue( 'PageImagesOpenGraph', true );

		$title = Title::newFromText( 'Embed pageimages page' );
		$this->assertNotNull( $title );

		$parserOutput = $this->storedPreviewOutput( new ResolvedMedia( 'https://wiki.example/a.png', null, false, 800, 450 ) );
		$tags = $this->tagsOf( $this->outputPageFor( $parserOutput, $title ) );

		$this->assertSame( 'https://wiki.example/a.png', $tags['og:image'] );
		$this->assertSame( '800', $tags['og:image:width'] );
	}

	public function testAnOgImageIsPublishedWhenPageImagesWillNotEmitOne(): void {
		if ( !class_exists( 'PageImages\\PageImages' ) ) {
			$this->markTestSkipped( 'PageImages is not installed' );
		}

		$this->overrideConfigValue( 'PageImagesOpenGraph', false );

		$title = Title::newFromText( 'Embed og image page' );
		$this->assertNotNull( $title );

		$parserOutput = $this->storedPreviewOutput( new ResolvedMedia( 'https://wiki.example/a.png', null, false, 800, 450 ) );
		$tags = $this->tagsOf( $this->outputPageFor( $parserOutput, $title ) );

		$this->assertSame( 'https://wiki.example/a.png', $tags['og:image'] );
		$this->assertSame( '800', $tags['og:image:width'] );
		$this->assertSame( '450', $tags['og:image:height'] );
	}

	public function testOurTagGivesWayWhenAnotherSourceEmitsTheSameKey(): void {
		$this->overrideConfigValue( 'SkinMetaTags', [] );

		$title = Title::newFromText( 'Embed duplicate page' );
		$this->assertNotNull( $title );

		$parserOutput = $this->storedPreviewOutput();
		$outputPage = $this->outputPageFor( $parserOutput, $title );

		// Stands in for a source that contributes its tag after our hook has run, which
		// is what the skin does.
		$outputPage->addMeta( 'og:title', 'From another extension' );

		$headLinks = $outputPage->getHeadLinksArray();
		$ogTitles = array_values( array_filter(
			$headLinks,
			static fn ( string $tag ): bool => str_contains( $tag, 'og:title' )
		) );

		$this->assertCount( 1, $ogTitles, 'exactly one og:title reaches the head' );
		$this->assertStringContainsString( 'From another extension', $ogTitles[0] );
	}

	public function testTheOpenGraphFallbackCanBeSwitchedOff(): void {
		$this->overrideConfigValue( 'DiscordComponentEmbedOpenGraph', false );

		$title = Title::newFromText( 'Embed og off page' );
		$this->assertNotNull( $title );

		$parserOutput = $this->storedPreviewOutput();
		$outputPage = $this->outputPageFor( $parserOutput, $title );

		$this->assertSame( [], $this->tagsOf( $outputPage ) );
		$this->assertArrayHasKey( 'discord:component-embed', $outputPage->getHeadItemsArray() );
	}

	public function testAnIdenticalTagFromAnotherSourceIsNotDuplicated(): void {
		$this->overrideConfigValue( 'SkinMetaTags', [] );

		$title = Title::newFromText( 'Embed identical duplicate page' );
		$this->assertNotNull( $title );

		$parserOutput = $this->storedPreviewOutput();
		$outputPage = $this->outputPageFor( $parserOutput, $title );

		// PageImages picks the same 1200px thumbnail this extension resolved, so the
		// duplicate is identical — and counting, not comparing content, catches it.
		$outputPage->addMeta( 'og:title', 'Doorman' );

		$ogTitles = array_values( array_filter(
			$outputPage->getHeadLinksArray(),
			static fn ( string $tag ): bool => str_contains( $tag, 'og:title' )
		) );

		$this->assertCount( 1, $ogTitles, 'exactly one og:title reaches the head' );
	}

	public function testTheWholeExtensionCanBeSwitchedOff(): void {
		$this->overrideConfigValue( 'DiscordComponentEmbedEnabled', false );

		$title = Title::newFromText( 'Embed disabled page' );
		$this->assertNotNull( $title );

		$parserOutput = $this->storedPreviewOutput();
		$outputPage = $this->outputPageFor( $parserOutput, $title );

		$this->assertArrayNotHasKey( 'discord:component-embed', $outputPage->getHeadItemsArray() );
		$this->assertSame( [], $this->tagsOf( $outputPage ) );
	}

	public function testNamespacesCanBeRestrictedToASubset(): void {
		$this->overrideConfigValue( 'DiscordComponentEmbedNamespaces', [ NS_TALK ] );

		$mainspace = $this->parsePage( 'A lead.', 'Embed namespace restricted' );
		$this->assertNull( $mainspace->getExtensionData( StoredPreview::KEY ) );

		$talk = $this->parsePage( 'A talk lead.', 'Talk:Embed namespace restricted' );
		$this->assertIsArray( $talk->getExtensionData( StoredPreview::KEY ) );
	}

	public function testAnInvalidConfigurationIsLoggedWithAReason(): void {
		$logFile = tempnam( sys_get_temp_dir(), 'dce-log' );
		$this->assertIsString( $logFile );

		$this->overrideConfigValue( 'DebugLogGroups', [ 'DiscordComponentEmbed' => $logFile ] );

		$parserOutput = $this->parsePage(
			"Lead.\n\n{{#discord_embed: buttom1 = [[Foo]] }}",
			'Embed logged rejection'
		);

		$this->assertNull( $parserOutput->getExtensionData( StoredPreview::KEY ) );

		$log = (string)file_get_contents( $logFile );
		@unlink( $logFile );

		$this->assertStringContainsString( 'buttom1', $log, 'the author can tell a typo from a broken install' );
	}

	public function testTextHiddenByCssNeverReachesTheDescription(): void {
		$parserOutput = $this->parsePage(
			'<p>Visible text.<span style="display:none">Invisible text.</span>'
			. '<span aria-hidden="true">Also invisible.</span></p>'
			. '<div class="mw-collapsible mw-collapsed"><p>Collapsed text.</p></div>'
			. '<table class="infobox"><tr><td><p>Infobox text.</p></td></tr></table>',
			'Embed hidden text'
		);

		$stored = $parserOutput->getExtensionData( StoredPreview::KEY );
		$this->assertIsArray( $stored );

		$this->assertSame( 'Visible text.', $stored['description'] );
	}

	public function testANonLatinDescriptionSurvivesToTheMetadata(): void {
		$this->overrideConfigValue( 'SkinMetaTags', [] );

		$title = Title::newFromText( 'Embed japanese page' );
		$this->assertNotNull( $title );

		$parserOutput = $this->parsePage( 'ドアマンはドアのゲームです。', 'Embed japanese page' );
		$tags = $this->tagsOf( $this->outputPageFor( $parserOutput, $title ) );

		$this->assertSame( 'ドアマンはドアのゲームです。', $tags['og:description'] );
	}

	public function testWithoutPageImagesThePreviewIsTextOnlyRatherThanBroken(): void {
		$title = Title::newFromText( 'Embed without pageimages' );
		$this->assertNotNull( $title );

		$provider = new PageImagesProvider(
			$title,
			$this->getServiceContainer()->getUrlUtils(),
			null,
			1200
		);

		$this->assertNull( $provider->forCurrentPage() );

		$parserOutput = $this->parsePage( 'A lead paragraph with no image.', 'Embed without pageimages' );
		$stored = $parserOutput->getExtensionData( StoredPreview::KEY );

		$this->assertIsArray( $stored, 'the preview is still published' );
		$this->assertNull( $stored['image'] );
	}
}
