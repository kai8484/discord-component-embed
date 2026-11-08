<?php

declare( strict_types=1 );

namespace DiscordComponentEmbed\Tests\Integration;

use DiscordComponentEmbed\Output\StoredPreview;
use DiscordComponentEmbed\Parser\DiscordEmbedParserFunction;
use MediaWiki\Content\WikitextContent;
use MediaWiki\Parser\ParserOutput;
use MediaWiki\Title\Title;
use MediaWikiIntegrationTestCase;

/**
 * The parser function against real wikitext: how arguments arrive, how several
 * calls merge, and what makes a page publish nothing.
 *
 * @group Database
 * @license Unlicense
 */
class ParserFunctionIntegrationTest extends MediaWikiIntegrationTestCase {

	/**
	 * Parse wikitext as a real page, through the content renderer, and hand back the
	 * ParserOutput the extension stored its preview in.
	 */
	private function parse( string $wikitext, string $pageName ): ParserOutput {
		$services = $this->getServiceContainer();
		$page = $this->getExistingTestPage( $pageName );

		$page->doUserEditContent(
			new WikitextContent( $wikitext ),
			$this->getTestUser()->getUser(),
			'integration test'
		);

		$output = $services->getContentRenderer()->getParserOutput(
			$page->getContent(),
			$page->getTitle()
		);

		$this->assertInstanceOf( ParserOutput::class, $output );

		return $output;
	}

	/**
	 * @return array<string, mixed>|null
	 */
	private function storedPreview( ParserOutput $output ): ?array {
		$stored = $output->getExtensionData( StoredPreview::KEY );

		return is_array( $stored ) ? $stored : null;
	}

	/**
	 * @return list<array<string, mixed>>
	 */
	private function buttons( ParserOutput $output ): array {
		$stored = $this->storedPreview( $output );

		if ( $stored === null ) {
			return [];
		}

		$buttons = [];

		foreach ( $stored['components'][0]['components'] ?? [] as $component ) {
			if ( ( $component['type'] ?? null ) !== 1 ) {
				continue;
			}

			foreach ( $component['components'] as $button ) {
				$buttons[] = $button;
			}
		}

		return $buttons;
	}

	public function testAnUntouchedPageGetsAPreviewWithNoMarkupAtAll(): void {
		$output = $this->parse( "A lead paragraph.\n\n== Section ==\nBody.", 'Embed untouched page' );

		$stored = $this->storedPreview( $output );

		$this->assertNotNull( $stored, 'an article with no markup still gets a preview' );
		$this->assertSame( 'Embed untouched page', $stored['title'] );
		$this->assertSame( 'A lead paragraph.', $stored['description'] );
		$this->assertSame( 17, $stored['components'][0]['type'] );
	}

	public function testNamedArgumentsAreReadFromTheShapeCoreDelivers(): void {
		// Core hands over one string per argument, named ones as "name=value".
		$output = $this->parse(
			"Lead.\n\n{{#discord_embed: title = Handed over | description = Also handed over }}",
			'Embed argument shape'
		);

		$stored = $this->storedPreview( $output );

		$this->assertNotNull( $stored );
		$this->assertSame( 'Handed over', $stored['title'] );
		$this->assertSame( 'Also handed over', $stored['description'] );
	}

	public function testTheTidyLineByLineCallStyleIsNotMistakenForAPositionalArgument(): void {
		$output = $this->parse(
			"Lead.\n\n{{#discord_embed:\n | title = Line by line\n | description = Written over several lines\n}}",
			'Embed line by line'
		);

		$stored = $this->storedPreview( $output );

		$this->assertNotNull( $stored, 'a call written across lines is still named' );
		$this->assertSame( 'Line by line', $stored['title'] );
	}

	public function testAPipeInsideAWikitextLinkDoesNotSplitTheArgument(): void {
		$this->getExistingTestPage( 'Embed link target' );

		$output = $this->parse(
			"Lead.\n\n{{#discord_embed: button1 = [[Embed link target|Read more]] }}",
			'Embed pipe protection'
		);

		$buttons = $this->buttons( $output );

		$this->assertCount( 1, $buttons, 'the pipe inside the link is protected by MediaWiki' );
		$this->assertSame( 'Read more', $buttons[0]['label'] );
	}

	public function testAPipeAfterAPlainUrlSeparatesTheLabelBecauseTheAuthorWroteItAsATemplate(): void {
		$output = $this->parse(
			"Lead.\n\n{{#discord_embed: button1 = https://example.com/gallery{{!}}Custom label }}",
			'Embed escaped pipe'
		);

		$buttons = $this->buttons( $output );

		$this->assertCount( 1, $buttons );
		$this->assertSame( 'Custom label', $buttons[0]['label'] );
		$this->assertSame( 'https://example.com/gallery', $buttons[0]['url'] );
	}

	public function testTwoCallsOnOnePageMergeIntoExactlyOnePreview(): void {
		$this->getExistingTestPage( 'Embed merge target' );

		$output = $this->parse(
			"Lead paragraph.\n\n"
			. "{{#discord_embed: accent = #5865F2 | button1 = [[Embed merge target]] }}\n\n"
			. "{{#discord_embed: title = Set by the second call }}\n\n"
			. 'Tail paragraph.',
			'Embed merge'
		);

		$stored = $this->storedPreview( $output );

		$this->assertNotNull( $stored );
		$this->assertCount( 1, $stored['components'], 'exactly one container' );
		$this->assertSame( 'Set by the second call', $stored['title'], 'the later call wins on the keys it names' );
		$this->assertSame( 0x5865F2, $stored['components'][0]['accent_color'], 'the earlier accent survives' );
		$this->assertCount( 1, $this->buttons( $output ), 'the earlier button survives' );
	}

	public function testReplaceModeUsesOnlyWhatTheAuthorWrote(): void {
		$output = $this->parse(
			"Lead paragraph that would otherwise be the description.\n\n"
			. '{{#discord_embed: mode = replace | title = Only this }}',
			'Embed replace'
		);

		$stored = $this->storedPreview( $output );

		$this->assertNotNull( $stored );
		$this->assertSame( 'Only this', $stored['title'] );
		$this->assertSame( '', $stored['description'] );
		$this->assertNull( $stored['image'] );
	}

	public function testEnabledNoPublishesNothing(): void {
		$output = $this->parse(
			"Lead.\n\n{{#discord_embed: enabled = no }}",
			'Embed disabled'
		);

		$this->assertNull( $this->storedPreview( $output ) );
	}

	public function testALeadWrittenAsAListIsStillTheDescription(): void {
		$output = $this->parse(
			"* Alpha one.\n* Beta two.\n\n== Section ==\nBody.",
			'Embed list lead'
		);

		$stored = $this->storedPreview( $output );

		$this->assertNotNull( $stored );
		$this->assertSame( 'Alpha one. Beta two.', $stored['description'] );
	}

	public function testAnInlineTableOfContentsDoesNotSwallowTheDescription(): void {
		$output = $this->parse(
			"__TOC__\n\nThe lead paragraph.\n\n== Alpha ==\nAlpha body.\n\n== Beta ==\nBeta body.",
			'Embed toc page'
		);

		$stored = $this->storedPreview( $output );

		$this->assertNotNull( $stored );
		$this->assertSame( 'The lead paragraph.', $stored['description'] );
	}

	public function testAListBetweenLeadParagraphsIsNotPartOfTheDescription(): void {
		// Roster and index pages open with a sentence and then list everything they link
		// to. Reading that list as the intro buries the sentence under a wall of names,
		// which is what a real article showed; a lead that is *only* a list still counts.
		$output = $this->parse(
			"Opening sentence.\n\n* Alpha one.\n* Beta two.\n\nClosing sentence.\n\n== Section ==\nBody.",
			'Embed mixed lead'
		);

		$stored = $this->storedPreview( $output );

		$this->assertNotNull( $stored );
		$this->assertSame( 'Opening sentence. Closing sentence.', $stored['description'] );
	}

	public function testWritingAButtonTargetInALaterCallDropsTheEarlierLabel(): void {
		$output = $this->parse(
			"Lead.\n\n{{#discord_embed: button1 = [[Embed button target|Read more]] }}"
			. "\n\n{{#discord_embed: button1 = https://example.com/read }}",
			'Embed button replaced'
		);

		$buttons = $this->buttons( $output );

		$this->assertCount( 1, $buttons );
		$this->assertSame( 'https://example.com/read', $buttons[0]['url'] );
		$this->assertSame( 'read', $buttons[0]['label'], 'the whole earlier entry is replaced, label included' );
	}

	public function testAnUnknownArgumentPublishesNothing(): void {
		$output = $this->parse(
			"Lead.\n\n{{#discord_embed: buttom1 = [[Foo]] }}",
			'Embed unknown argument'
		);

		$this->assertNull( $this->storedPreview( $output ), 'a typo must not publish a half-configured preview' );
	}

	public function testAnOverLimitConfigurationPublishesNothing(): void {
		$wikitext = "Lead.\n\n{{#discord_embed:";

		for ( $i = 1; $i <= 26; $i++ ) {
			$wikitext .= sprintf( ' | button%d = https://example.com/%d', $i, $i );
		}

		$wikitext .= ' }}';

		$output = $this->parse( $wikitext, 'Embed over limit' );

		$this->assertNull( $this->storedPreview( $output ) );
	}

	public function testAFileThatDoesNotResolveDoesNotRaiseAnError(): void {
		$output = $this->parse(
			"Lead.\n\n{{#discord_embed: image = File:Definitely not uploaded.png | gallery1 = File:Nor this one.png }}",
			'Embed missing file'
		);

		$stored = $this->storedPreview( $output );

		$this->assertNotNull( $stored, 'a missing file leaves a text-only preview' );
		$this->assertNull( $stored['image'] );
	}

	public function testTheStoredPreviewSurvivesTheParserCache(): void {
		$services = $this->getServiceContainer();
		$page = $this->getExistingTestPage( 'Embed cached page' );

		$page->doUserEditContent(
			new WikitextContent( "A cached lead.\n\n{{#discord_embed: title = Cached title }}" ),
			$this->getTestUser()->getUser(),
			'integration test'
		);

		$first = $services->getContentRenderer()->getParserOutput( $page->getContent(), $page->getTitle() );
		$second = $services->getContentRenderer()->getParserOutput( $page->getContent(), $page->getTitle() );

		$this->assertInstanceOf( ParserOutput::class, $first );
		$this->assertInstanceOf( ParserOutput::class, $second );
		$this->assertSame(
			'Cached title',
			$this->storedPreview( $second )['title'] ?? null,
			'the preview is read back out of the parser cache, not rebuilt'
		);
	}

	public function testTheFunctionNameIsTheOneDocumented(): void {
		$this->assertSame( 'discord_embed', DiscordEmbedParserFunction::FUNCTION_NAME );
	}

	public function testTheParserFunctionRendersNoVisibleOutput(): void {
		$output = $this->parse(
			"Before.\n\n{{#discord_embed: title = Hidden }}\n\nAfter.",
			'Embed no output'
		);

		$html = $output->getContentHolderText();

		$this->assertStringNotContainsString( 'discord_embed', $html );
		$this->assertStringContainsString( 'Before.', $html );
		$this->assertStringContainsString( 'After.', $html );
	}

	public function testATitleWithANamespacePrefixIsUsedForTalkPages(): void {
		$title = Title::newFromText( 'Talk:Embed talk page' );
		$this->assertNotNull( $title );

		$services = $this->getServiceContainer();
		$page = $this->getExistingTestPage( $title );

		$page->doUserEditContent(
			new WikitextContent( 'Talk lead.' ),
			$this->getTestUser()->getUser(),
			'integration test'
		);

		$output = $services->getContentRenderer()->getParserOutput( $page->getContent(), $title );

		$this->assertInstanceOf( ParserOutput::class, $output );
		$this->assertSame( 'Talk:Embed talk page', $this->storedPreview( $output )['title'] ?? null );
	}
}
