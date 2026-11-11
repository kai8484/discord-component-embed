<?php

declare( strict_types=1 );

namespace DiscordComponentEmbed\Tests\Unit\Content;

use DiscordComponentEmbed\Content\IntroExtractor;
use PHPUnit\Framework\TestCase;

/**
 * The intro is every paragraph before the first heading: taking only the first
 * paragraph gives a wall of text on a page whose opening paragraph is long, and
 * too little on a page whose intro is several short paragraphs.
 *
 * @license Unlicense
 */
final class IntroExtractorTest extends TestCase {

	private function extract( string $html ): string {
		return ( new IntroExtractor() )->extract( $html )->plain();
	}

	private function extractMarkdown( string $html ): string {
		return ( new IntroExtractor() )->extract( $html )->markdown();
	}

	public function testASingleParagraphIsTheIntro(): void {
		$this->assertSame( 'Doorman is a game.', $this->extract( '<p>Doorman is a game.</p>' ) );
	}

	public function testEveryParagraphBeforeTheFirstHeadingCounts(): void {
		$html = '<p>First.</p><p>Second.</p><h2>Gameplay</h2><p>Third.</p>';

		$this->assertSame( 'First. Second.', $this->extract( $html ) );
	}

	public function testParagraphsInsideNonProseBoxesAreNotIntroText(): void {
		$html = '<table class="infobox"><tr><td><p>Born in 1998</p></td></tr></table>'
			. '<p>The real intro.</p><h2>Later</h2><p>Later text.</p>';

		$this->assertSame( 'The real intro.', $this->extract( $html ) );
	}

	public function testANavboxAfterTheIntroIsNotIntroText(): void {
		$html = '<p>Intro.</p><div class="navbox"><p>vte Navigation</p></div>';

		$this->assertSame( 'Intro.', $this->extract( $html ) );
	}

	public function testAHeadingInsideAnInfoboxDoesNotEndTheIntro(): void {
		$html = '<table class="infobox"><tr><td><h2>Statistics</h2><p>Not prose</p></td></tr></table>'
			. '<p>Real intro.</p><h2>Section</h2><p>Section body.</p>';

		$this->assertSame( 'Real intro.', $this->extract( $html ) );
	}

	public function testProseAfterAHeadingInsideFurnitureIsNotLost(): void {
		// The regression a "parse only up to the first heading" shortcut causes: this
		// heading is in an infobox, so the lead continues after it.
		$html = '<p>First paragraph.</p>'
			. '<table class="infobox"><tr><td><h2>Stats</h2><p>Not prose</p></td></tr></table>'
			. '<p>Second paragraph.</p><h2>Section</h2><p>Body.</p>';

		$this->assertSame( 'First paragraph. Second paragraph.', $this->extract( $html ) );
	}


	public function testAnEmptyParagraphIsSkipped(): void {
		$this->assertSame( 'Only text.', $this->extract( '<p></p><p>   </p><p>Only text.</p>' ) );
	}

	public function testTextHiddenWithInlineDisplayNoneIsExcluded(): void {
		$html = '<p>Visible<span style="display:none">Invisible</span> text.</p>';

		$this->assertSame( 'Visible text.', $this->extract( $html ) );
	}

	public function testInlineStyleMatchingIgnoresCaseAndSpacing(): void {
		$html = '<p>A<span style="DISPLAY: none !important">B</span>C<span style="display : none">D</span>E</p>';

		$this->assertSame( 'ACE', $this->extract( $html ) );
	}

	public function testTextHiddenWithVisibilityHiddenIsExcluded(): void {
		$this->assertSame( 'Shown .', $this->extract( '<p>Shown <span style="visibility:hidden">hidden</span>.</p>' ) );
	}

	public function testTheHiddenAttributeIsExcluded(): void {
		$this->assertSame( 'Kept .', $this->extract( '<p>Kept <span hidden>gone</span>.</p>' ) );
	}

	public function testAriaHiddenIsExcluded(): void {
		$this->assertSame( 'Kept .', $this->extract( '<p>Kept <span aria-hidden="true">dropped</span>.</p>' ) );
	}

	public function testEmptyMediaWikiElementsAreExcluded(): void {
		$this->assertSame( 'Real content.', $this->extract( '<p>Real content.</p><p class="mw-empty-elt"> </p>' ) );
	}

	public function testCollapsedContentIsExcluded(): void {
		$html = '<p>Intro.</p><div class="mw-collapsible mw-collapsed"><p>Hidden until expanded.</p></div>';

		$this->assertSame( 'Intro.', $this->extract( $html ) );
	}

	public function testReferenceMarkersAreExcluded(): void {
		$html = '<p>Doors are important.<sup class="reference"><a href="#cite_note-1">[1]</a></sup> Really.</p>';

		$this->assertSame( 'Doors are important. Really.', $this->extract( $html ) );
	}

	public function testImageAltTextIsNotProse(): void {
		$this->assertSame( 'A picture follows.', $this->extract( '<p>A picture follows. <img src="/a.png" alt="A door"></p>' ) );
	}

	public function testScriptAndStyleContentNeverReachesTheText(): void {
		$this->assertSame( 'Safe.', $this->extract( '<p>Safe.</p><script>alert(1)</script><style>.a{}</style>' ) );
	}

	public function testCommentsAreExcluded(): void {
		$this->assertSame( 'Text.', $this->extract( '<p>Text.<!-- hidden note --></p>' ) );
	}

	public function testLinksBecomeTheirLabelInPlainText(): void {
		$html = '<p>See <a href="/wiki/Doorman">the Doorman article</a> for more.</p>';

		$this->assertSame( 'See the Doorman article for more.', $this->extract( $html ) );
	}

	public function testLinksBecomeMaskedLinksInMarkdown(): void {
		$html = '<p>See <a href="/wiki/Doorman">the Doorman article</a>.</p>';

		$this->assertSame( 'See [the Doorman article](/wiki/Doorman).', $this->extractMarkdown( $html ) );
	}

	public function testALinkWhoseTextIsItsTargetStaysTextEvenWithMarkupInIt(): void {
		// The label is escaped for the card, so the "same words" check has to compare
		// against the escaped target, or "Foo_bar" would turn into a link.
		$this->assertSame(
			'See Foo\_bar.',
			$this->extractMarkdown( '<p>See <a href="Foo_bar">Foo_bar</a>.</p>' )
		);
	}

	public function testOnlyWebLinksBecomeLinksInTheCard(): void {
		// A preview is not the article: "edit this page" and "open your mail client" are
		// not things a card link should do.
		$this->assertSame(
			'Mail the team.',
			$this->extractMarkdown( '<p>Mail <a href="mailto:x@example.com">the team</a>.</p>' )
		);
		$this->assertSame(
			'See Nope.',
			$this->extractMarkdown( '<p>See <a class="new" href="/index.php?title=Nope&amp;action=edit&amp;redlink=1">Nope</a>.</p>' )
		);
		$this->assertSame(
			'Click here.',
			$this->extractMarkdown( '<p>Click <a href="javascript:alert(1)">here</a>.</p>' )
		);
	}

	public function testAnImageLinkContributesNoText(): void {
		$html = '<p>Before <a href="/wiki/File:A.png"><img src="/a.png" alt="A"></a> after.</p>';

		$this->assertSame( 'Before after.', $this->extract( $html ) );
	}

	public function testBoldAndItalicBecomeMarkdownButNotPlainText(): void {
		$html = '<p>A <b>bold</b> and <i>italic</i> intro.</p>';

		$this->assertSame( 'A bold and italic intro.', $this->extract( $html ) );
		$this->assertSame( 'A **bold** and *italic* intro.', $this->extractMarkdown( $html ) );
	}

	public function testCodeBecomesInlineCodeInMarkdown(): void {
		$html = '<p>Use <code>wrench</code>. </p>';

		$this->assertSame( 'Use `wrench`.', $this->extractMarkdown( $html ) );
	}

	public function testCodeContainingABacktickIsLeftAsEscapedText(): void {
		// No backtick delimiter can contain a backtick, so a span around one would end
		// at the wrong place; the text is more useful than the formatting.
		$html = '<p>Type <code>`</code> to open it.</p>';

		$this->assertSame( 'Type \\` to open it.', $this->extractMarkdown( $html ) );
	}

	public function testAParenthesisedPageTitleStillProducesAWorkingLink(): void {
		// Discord ends a masked link's target at the first ")", so the parentheses a
		// disambiguated title carries have to be percent-encoded.
		$extractor = new IntroExtractor(
			new \DiscordComponentEmbed\Content\LinkBase( 'https://wiki.example', 'https://wiki.example/index.php/Doorman' )
		);

		$this->assertSame(
			'See [Doorman (2024)](https://wiki.example/index.php/Doorman_%282024%29).',
			$extractor->extract(
				'<p>See <a href="/index.php/Doorman_(2024)">Doorman (2024)</a>.</p>'
			)->markdown()
		);
	}

	public function testBracketsInALinkLabelAreEscaped(): void {
		$html = '<p>See <a href="/wiki/Doorman">the [draft] article</a>.</p>';

		$this->assertSame( 'See [the \\[draft\\] article](/wiki/Doorman).', $this->extractMarkdown( $html ) );
		$this->assertSame( 'See the [draft] article.', $this->extract( $html ) );
	}

	public function testMarkdownCharactersInProseStayProse(): void {
		$html = '<p>Two * three is not _six_, and x > y.</p>';

		$this->assertSame( 'Two \\* three is not \\_six\\_, and x \\> y.', $this->extractMarkdown( $html ) );
		$this->assertSame( 'Two * three is not _six_, and x > y.', $this->extract( $html ) );
	}

	public function testAPageWithNoProseElementsIsNotParsedAtAll(): void {
		// Data and code pages are one <pre> block. Building a DOM to find no paragraphs
		// in it was the whole cost of such a page.
		$html = '<div class="mw-parser-output"><pre>' . str_repeat( '{"a":1},', 20000 ) . '</pre></div>';

		$this->assertSame( '', $this->extract( $html ) );
		$this->assertSame( '', $this->extractMarkdown( $html ) );
	}

	public function testAnEnormousPageIsCappedRatherThanParsedWhole(): void {
		// The lead is at the top; the megabytes of patch notes after it used to be parsed
		// into the DOM for nothing.
		$lead = '<p>Doorman is a game.</p><h2>Gameplay</h2>';
		$tail = str_repeat( '<div class="patch"><p>' . str_repeat( 'word ', 400 ) . '</p></div>', 3000 );
		$html = $lead . $tail;

		$this->assertGreaterThan( 2 * 1024 * 1024, strlen( $html ), 'the cap is actually exercised' );
		$this->assertSame( 'Doorman is a game.', $this->extract( $html ) );
	}

	public function testEntitiesAreDecoded(): void {
		$this->assertSame( 'Doors & windows — 5 < 7.', $this->extract( '<p>Doors &amp; windows &mdash; 5 &lt; 7.</p>' ) );
	}

	public function testNonBreakingSpacesBecomeOrdinarySpaces(): void {
		$this->assertSame( 'Doors and windows.', $this->extract( '<p>Doors&nbsp;and windows.</p>' ) );
	}

	public function testLineBreaksInsideAParagraphBecomeSpaces(): void {
		$this->assertSame( 'One line two lines.', $this->extract( "<p>One line<br>two lines.</p>" ) );
	}

	public function testWhitespaceAndNewlinesInTheSourceAreCollapsed(): void {
		$this->assertSame( 'Tidy text.', $this->extract( "<p>\n\t Tidy   text.\n</p>" ) );
	}

	public function testNonLatinTextSurvives(): void {
		$this->assertSame(
			'このゲームは日本語でも表示されます。',
			$this->extract( '<p>このゲームは日本語でも表示されます。</p>' )
		);
	}

	public function testZeroWidthCharactersAreDropped(): void {
		$this->assertSame( 'Doors', $this->extract( "<p>Doors\u{200B}</p>" ) );
	}

	public function testAnEmptyDocumentYieldsEmptyText(): void {
		$this->assertSame( '', $this->extract( '' ) );
	}

	public function testMalformedHtmlDoesNotRaiseAnError(): void {
		$result = $this->extract( '<p>Unclosed <b>bold <div>and a stray ' );
		$this->assertStringContainsString( 'Unclosed', $result );
	}

	public function testAnInlineCollapsibleInsideAParagraphIsExcluded(): void {
		$this->assertSame(
			'Visible tail.',
			$this->extract( '<p>Visible <span class="mw-collapsible mw-collapsed">collapsed words</span> tail.</p>' )
		);
	}

	public function testAnInlineEmptyMediaWikiElementInsideAParagraphIsExcluded(): void {
		// .mw-empty-elt is display:none in MediaWiki's own stylesheet.
		$this->assertSame(
			'Visible tail.',
			$this->extract( '<p>Visible <span class="mw-empty-elt">placeholder words</span> tail.</p>' )
		);
	}

	public function testInlineTemplateFurnitureInsideAParagraphIsExcluded(): void {
		$html = '<p>Visible <span class="metadata">maintenance tag</span>'
			. '<span class="hatnote">See also something else</span> tail.</p>';

		$this->assertSame( 'Visible tail.', $this->extract( $html ) );
	}

	public function testAnEditSectionLinkInsideAParagraphIsExcluded(): void {
		$this->assertSame(
			'Alpha gamma.',
			$this->extract( '<p>Alpha <span class="mw-editsection">[edit]</span> gamma.</p>' )
		);
	}

	public function testAFigureInsideAParagraphIsExcludedWithItsCaption(): void {
		$html = '<p>Before the picture '
			. '<figure typeof="mw:File/Frame"><img src="x.png"><figcaption>Inner caption</figcaption></figure>'
			. ' and after.</p>';

		$this->assertSame( 'Before the picture and after.', $this->extract( $html ) );
	}

	public function testACaptionElementInsideAParagraphIsExcluded(): void {
		$this->assertSame(
			'Before and after.',
			$this->extract( '<p>Before <figcaption>caption</figcaption> and after.</p>' )
		);
	}

	public function testATableInsideAParagraphIsExcluded(): void {
		// MediaWiki never nests a table inside a paragraph: it closes it first, and
		// reopens one afterwards.
		$html = "<p>Before table</p>\n<table class=\"wikitable\"><tr><td>cell</td></tr></table>\n<p>After table.</p>";

		$this->assertSame( 'Before table After table.', $this->extract( $html ) );
	}

	public function testSkChromeIsNeverTheIntro(): void {
		// A caller that hands over a whole page rather than parsed wikitext must not
		// get the sidebar menu as the article's intro.
		$html = '<div id="mw-panel"><nav class="vector-menu"><ul><li>Main page</li><li>Contents</li></ul></nav></div>'
			. '<div role="navigation"><ul><li>Random article</li></ul></div>'
			. '<aside><ul><li>Advertisement</li></ul></aside>'
			. '<p>Real lead.</p><h2>Section</h2>';

		$this->assertSame( 'Real lead.', $this->extract( $html ) );
	}

	public function testALeadThatIsAListIsStillTheIntro(): void {
		$html = '<ul><li>Alpha one.</li><li>Beta two.</li></ul><h2>Section</h2><p>Body.</p>';

		$this->assertSame( 'Alpha one. Beta two.', $this->extract( $html ) );
	}

	public function testAListAfterALeadParagraphIsNotPartOfTheIntro(): void {
		// Roster and index pages open with a sentence and then list everything they
		// link to; the list must not bury the sentence.
		$html = '<p>The playable characters.</p>'
			. '<ul><li>Abrams</li><li>Apollo</li><li>Bebop</li></ul>'
			. '<h2>Heroes</h2>';

		$this->assertSame( 'The playable characters.', $this->extract( $html ) );
	}

	public function testAListShallowerThanAWrappedLeadDoesNotReplaceIt(): void {
		// A template that wraps the opening sentence puts it deeper than the roster
		// that follows; the list is still only a fallback.
		$html = '<div class="mw-parser-output">'
			. '<div class="intro"><p>The playable characters.</p></div>'
			. '<ul><li>Abrams</li><li>Apollo</li><li>Bebop</li></ul>'
			. '</div><h2>Heroes</h2>';

		$this->assertSame( 'The playable characters.', $this->extract( $html ) );
	}

	public function testACardGridAfterAListLeadDoesNotReplaceIt(): void {
		$html = '<div class="mw-parser-output">'
			. '<ul><li>Alpha one.</li><li>Beta two.</li></ul>'
			. '<div class="grid"><div class="card"><p>Abrams</p></div></div>'
			. '</div><h2>Section</h2>';

		$this->assertSame( 'Alpha one. Beta two.', $this->extract( $html ) );
	}

	public function testATemplateCardGridAfterTheLeadIsNotTheIntro(): void {
		// Card layouts are nested containers full of paragraphs; the article's own prose
		// is the shallowest prose in the region.
		$html = '<div class="mw-parser-output">'
			. '<p>Heroes are the playable characters.</p>'
			. '<p>There are currently 38 heroes.</p>'
			. '<div class="grid"><div class="card"><p>Abrams</p></div>'
			. '<div class="card"><p>Apollo</p></div></div>'
			. '</div><h2>Heroes</h2>';

		$this->assertSame(
			'Heroes are the playable characters. There are currently 38 heroes.',
			$this->extract( $html )
		);
	}

	public function testAWikiThatWrapsItsLeadIsStillRead(): void {
		// The depth is taken relative to the page, not assumed: if every candidate is
		// nested, the shallowest one is still the lead.
		$html = '<div class="content"><div class="lead"><p>Wrapped lead.</p></div></div>'
			. '<h2>Section</h2>';

		$this->assertSame( 'Wrapped lead.', $this->extract( $html ) );
	}

	public function testATemplateQuoteBeforeTheLeadIsNotTheIntro(): void {
		// Template furniture is often a blockquote with a paragraph inside it.
		$html = '<blockquote style="margin-left: 38px"><p>&mdash; The New York Oracle</p></blockquote>'
			. '<p>The real lead.</p><h2>Section</h2>';

		$this->assertSame( 'The real lead.', $this->extract( $html ) );
	}

	public function testANestedListIsNotReportedTwice(): void {
		$html = "<ul>\n<li>Outer item\n<ul>\n<li>Inner item</li>\n</ul>\n</li>\n</ul>\n<h2>Section</h2>";

		$this->assertSame( 'Outer item Inner item', $this->extract( $html ) );
	}

	public function testADefinitionListLeadIsStillTheIntro(): void {
		$html = '<dl><dt>Term</dt><dd>Its definition.</dd></dl><h2>Section</h2>';

		$this->assertSame( 'Term Its definition.', $this->extract( $html ) );
	}

	public function testAListInsideANavboxIsNotIntroText(): void {
		$html = '<div class="navbox"><ul><li>Not prose</li></ul></div><p>Real lead.</p><h2>Section</h2>';

		$this->assertSame( 'Real lead.', $this->extract( $html ) );
	}

	public function testATableOfContentsIsNeverTheIntro(): void {
		// Skins and extensions have spelled this several ways; any of them would
		// otherwise end the scan at its "Contents" heading and leave every page with
		// an empty description.
		foreach ( [
			'<div class="toc"><h2>Contents</h2></div>',
			'<div id="toc"><h2>Contents</h2></div>',
			'<div class="mw-table-of-contents"><h2>Contents</h2></div>',
			'<nav class="vector-toc"><h2>Contents</h2></nav>',
		] as $toc ) {
			$this->assertSame( 'Real lead.', $this->extract( $toc . '<p>Real lead.</p><h2>Section</h2>' ), $toc );
		}
	}

	public function testInvalidUtf8BytesDoNotEmptyTheIntro(): void {
		$result = $this->extract( "<p>Bad \xFF\xFE bytes.</p><p>More text.</p>" );

		$this->assertNotSame( '', $result );
		$this->assertStringContainsString( 'More text.', $result );
	}

	public function testARelativeLinkIsLeftAloneWithoutABaseToExpandItAgainst(): void {
		$this->assertSame(
			'See [the article](/index.php/Doorman).',
			$this->extractMarkdown( '<p>See <a href="/index.php/Doorman">the article</a>.</p>' )
		);
	}

	public function testARelativeLinkIsExpandedWhenThePreviewLeavesTheWiki(): void {
		$extractor = new IntroExtractor(
			new \DiscordComponentEmbed\Content\LinkBase( 'https://wiki.example', 'https://wiki.example/index.php/Doorman' )
		);

		$markdown = $extractor->extract(
			'<p>See <a href="/index.php/Doorman">the article</a> and <a href="#Abilities">below</a>.</p>'
		)->markdown();

		$this->assertSame(
			'See [the article](https://wiki.example/index.php/Doorman) and [below](https://wiki.example/index.php/Doorman#Abilities).',
			$markdown
		);
	}

	public function testPlainTextIsUnaffectedByTheBase(): void {
		$extractor = new IntroExtractor(
			new \DiscordComponentEmbed\Content\LinkBase( 'https://wiki.example', 'https://wiki.example/index.php/Doorman' )
		);

		$this->assertSame(
			'See the article.',
			$extractor->extract( '<p>See <a href="/index.php/Doorman">the article</a>.</p>' )->plain()
		);
	}

	public function testTheUnmarkedTextOfARealisticOpeningPassageIsExtracted(): void {
		$html = '<div class="mw-parser-output">'
			. '<table class="infobox"><tbody><tr><th>Doorman</th></tr><tr><td><p>Developer</p></td></tr></tbody></table>'
			. '<p><b>Doorman</b> is a 2024 <a href="/wiki/Video_game">video game</a>.'
			. '<sup class="reference"><a href="#cite_note-1">[1]</a></sup></p>'
			. '<p>It was released on 1&nbsp;January 2024.</p>'
			. '<h2 id="Gameplay">Gameplay</h2>'
			. '<p>Players control a door.</p>'
			. '</div>';

		$this->assertSame(
			'Doorman is a 2024 video game. It was released on 1 January 2024.',
			$this->extract( $html )
		);
		$this->assertSame(
			'**Doorman** is a 2024 [video game](/wiki/Video_game). It was released on 1 January 2024.',
			$this->extractMarkdown( $html )
		);
	}
}
