<?php

declare( strict_types=1 );

namespace DiscordComponentEmbed\Tests\Unit\Embed;

use DiscordComponentEmbed\Config\ButtonSpec;
use DiscordComponentEmbed\Config\ButtonTargetKind;
use DiscordComponentEmbed\Config\EmbedConfig;
use DiscordComponentEmbed\Config\Limits;
use DiscordComponentEmbed\Config\MediaSpec;
use DiscordComponentEmbed\Content\DescriptionBuilder;
use DiscordComponentEmbed\Content\Intro;
use DiscordComponentEmbed\Content\Truncator;
use DiscordComponentEmbed\Embed\ButtonResolver;
use DiscordComponentEmbed\Embed\PreviewBuilder;
use DiscordComponentEmbed\Embed\PreviewRequest;
use DiscordComponentEmbed\Media\MediaResolver;
use DiscordComponentEmbed\Media\ResolvedMedia;
use DiscordComponentEmbed\Tests\Stubs\FakeImageResolver;
use DiscordComponentEmbed\Tests\Stubs\FakeLinkResolver;
use DiscordComponentEmbed\Tests\Stubs\FakePageImageProvider;
use PHPUnit\Framework\TestCase;

/**
 * The acceptance criteria for an untouched article, for overrides, and for merge
 * versus replace.
 *
 * @license Unlicense
 */
final class PreviewBuilderTest extends TestCase {

	private const PAGE_IMAGE = 'https://wiki.example/images/thumb/doorman.png';

	private function builder(
		array $links = [],
		array $files = [],
		?ResolvedMedia $pageImage = null,
		int $budget = 300,
		int $heading = 0,
		bool $components = true
	): PreviewBuilder {
		return new PreviewBuilder(
			new FakeLinkResolver( $links ),
			new MediaResolver( new FakeImageResolver( $files ) ),
			new FakePageImageProvider( $pageImage ),
			new DescriptionBuilder( new Truncator(), $budget ),
			new ButtonResolver( new FakeLinkResolver( $links ), new Truncator() ),
			$heading,
			$components
		);
	}

	public function testAnUntouchedArticleGetsThePageTitleTheIntroAndThePageImage(): void {
		$result = $this->builder( pageImage: new ResolvedMedia( self::PAGE_IMAGE, null, false, 800, 450 ) )
			->build( new PreviewRequest(
				'Doorman',
				new EmbedConfig(),
				new Intro( 'Doorman is a game.', 'Doorman is a game.' )
			) );

		$preview = $result->preview;
		$this->assertSame( 'Doorman', $preview->titlePlain );
		$this->assertSame( '**Doorman**', $preview->titleMarkdown );
		$this->assertSame( 'Doorman is a game.', $preview->descriptionPlain() );
		$this->assertSame( 'Doorman is a game.', $preview->descriptionMarkdown() );
		$this->assertSame( self::PAGE_IMAGE, $preview->image?->url );
		$this->assertSame( [], $result->problems );
	}

	public function testAnArticleWithNoPageImageGetsATextOnlyPreview(): void {
		$result = $this->builder()->build( new PreviewRequest(
			'Doorman',
			new EmbedConfig(),
			new Intro( 'Doorman is a game.', 'Doorman is a game.' )
		) );

		$this->assertNull( $result->preview->image );
		$this->assertSame( 'Doorman is a game.', $result->preview->descriptionPlain() );
		$this->assertSame( [], $result->problems );
	}

	public function testAnAuthorOverridesTitleDescriptionImageAndButtons(): void {
		$links = [ 'Doorman' => 'https://wiki.example/wiki/Doorman' ];
		$files = [ 'File:Mine.png' => new ResolvedMedia( 'https://wiki.example/images/mine.png' ) ];

		$result = $this->builder( $links, $files, new ResolvedMedia( self::PAGE_IMAGE ) )
			->build( new PreviewRequest(
				'Doorman',
				new EmbedConfig(
					title: 'Custom title',
					description: 'Custom description',
					image: new MediaSpec( 'File:Mine.png', 'My caption' ),
					gallery: [ new MediaSpec( 'File:Mine.png', 'Gallery caption' ) ],
					buttons: [ new ButtonSpec( 'Doorman', 'Read more', ButtonTargetKind::PageLink ) ],
					accent: 0x5865F2,
					spoiler: true
				),
				new Intro( 'Article intro.', 'Article intro.' )
			) );

		$preview = $result->preview;
		$this->assertSame( 'Custom title', $preview->titlePlain );
		$this->assertSame( 'Custom description', $preview->descriptionPlain() );
		$this->assertSame( 'My caption', $preview->image?->description );
		$this->assertSame( 'Gallery caption', $preview->gallery[0]->description );
		$this->assertSame( 'Read more', $preview->buttons[0]->label );
		$this->assertSame( 0x5865F2, $preview->accent );
		$this->assertTrue( $preview->spoiler );
	}

	public function testAnAuthoredDescriptionKeepsMarkupOutOfTheMetaTag(): void {
		$result = $this->builder()->build( new PreviewRequest(
			'Doorman',
			new EmbedConfig( description: 'A **great** game, see [the page](https://x/y).' ),
			null
		) );

		$this->assertSame(
			'A **great** game, see the page.',
			$result->preview->descriptionPlain(),
			'og:description is shown as text by every consumer'
		);
		$this->assertSame(
			'A **great** game, see [the page](https://x/y).',
			$result->preview->descriptionMarkdown(),
			'the card still gets the markup the author wrote'
		);
	}

	public function testMergeModeFillsGapsTheAuthorLeft(): void {
		$result = $this->builder(
			pageImage: new ResolvedMedia( self::PAGE_IMAGE )
		)->build( new PreviewRequest(
			'Doorman',
			new EmbedConfig( title: 'Only the title' ),
			new Intro( 'Article intro.', 'Article intro.' )
		) );

		$this->assertSame( 'Only the title', $result->preview->titlePlain );
		$this->assertSame( 'Article intro.', $result->preview->descriptionPlain() );
		$this->assertSame( self::PAGE_IMAGE, $result->preview->image?->url );
	}

	public function testReplaceModeUsesOnlyWhatTheAuthorWrote(): void {
		$result = $this->builder( pageImage: new ResolvedMedia( self::PAGE_IMAGE ) )
			->build( new PreviewRequest(
				'Doorman',
				new EmbedConfig( title: 'Only the title', mode: Limits::MODE_REPLACE ),
				new Intro( 'Article intro.', 'Article intro.' )
			) );

		$this->assertSame( 'Only the title', $result->preview->titlePlain );
		$this->assertSame( '', $result->preview->descriptionPlain() );
		$this->assertNull( $result->preview->image );
	}

	public function testReplaceModeWithOnlyButtonsHasNoTitleOrDescription(): void {
		$links = [ 'Doorman' => 'https://wiki.example/wiki/Doorman' ];

		$result = $this->builder( $links, pageImage: new ResolvedMedia( self::PAGE_IMAGE ) )
			->build( new PreviewRequest(
				'Doorman',
				new EmbedConfig(
					buttons: [ new ButtonSpec( 'Doorman', null, ButtonTargetKind::PageLink ) ],
					mode: Limits::MODE_REPLACE
				),
				new Intro( 'Article intro.', 'Article intro.' )
			) );

		$this->assertSame( '', $result->preview->titlePlain );
		$this->assertSame( '', $result->preview->descriptionPlain() );
		$this->assertNull( $result->preview->image );
		$this->assertCount( 1, $result->preview->buttons );
	}

	public function testAnAuthoredImageThatDoesNotResolveDoesNotFallBackToTheArticleImage(): void {
		$result = $this->builder( pageImage: new ResolvedMedia( self::PAGE_IMAGE ) )
			->build( new PreviewRequest(
				'Doorman',
				new EmbedConfig( image: new MediaSpec( 'File:Missing.png' ) ),
				new Intro( 'Intro.', 'Intro.' )
			) );

		$this->assertNull( $result->preview->image );
		$this->assertSame( [], $result->problems );
		$this->assertCount( 1, $result->dropped );
		$this->assertStringContainsString( 'File:Missing.png', $result->dropped[0] );
	}

	public function testAnUnresolvableGalleryItemIsLeftOutWhileTheRestSurvive(): void {
		$files = [ 'File:One.png' => new ResolvedMedia( 'https://wiki.example/one.png' ) ];

		$result = $this->builder( [], $files )->build( new PreviewRequest(
			'Doorman',
			new EmbedConfig( gallery: [
				new MediaSpec( 'File:One.png' ),
				new MediaSpec( 'File:Missing.png' ),
			] ),
			new Intro( 'Intro.', 'Intro.' )
		) );

		$this->assertCount( 1, $result->preview->gallery );
		$this->assertSame( 'https://wiki.example/one.png', $result->preview->gallery[0]->url );
		$this->assertCount( 1, $result->dropped );
		$this->assertSame( [], $result->problems );
	}

	public function testAProblemWithOneButtonDoesNotRemoveTheOtherOnes(): void {
		$links = [ 'Doorman' => 'https://wiki.example/wiki/Doorman' ];

		$result = $this->builder( $links )->build( new PreviewRequest(
			'Doorman',
			new EmbedConfig( buttons: [
				new ButtonSpec( 'Doorman', null, ButtonTargetKind::PageLink ),
				new ButtonSpec( 'Category:Games', null, ButtonTargetKind::PageLink ),
				new ButtonSpec( 'Doorman', null, ButtonTargetKind::PageLink ),
			] ),
			new Intro( 'Intro.', 'Intro.' )
		) );

		$this->assertCount( 2, $result->preview->buttons );
		$this->assertSame( [], $result->problems );
	}

	public function testALongPageTitleIsCutToFit(): void {
		$title = str_repeat( 'Long page name ', 40 );

		$result = $this->builder()->build( new PreviewRequest( $title, new EmbedConfig(), null ) );

		$this->assertLessThanOrEqual( PreviewBuilder::MAX_TITLE_CHARS, mb_strlen( $result->preview->titlePlain ) );
	}

	public function testWithoutAComponentPayloadNothingIsResolvedForOne(): void {
		// Buttons and gallery items exist only in the payload; on a wiki that publishes
		// Open Graph tags alone, resolving them is work nobody reads.
		$links = [ 'Doorman' => 'https://wiki.example/wiki/Doorman' ];
		$files = [ 'File:One.png' => new ResolvedMedia( 'https://wiki.example/one.png' ) ];

		$result = $this->builder( $links, $files, components: false )->build( new PreviewRequest(
			'Doorman',
			new EmbedConfig(
				image: new MediaSpec( 'File:One.png' ),
				gallery: [ new MediaSpec( 'File:One.png' ) ],
				buttons: [ new ButtonSpec( 'Doorman', 'Read more', ButtonTargetKind::PageLink ) ]
			),
			null
		) );

		$this->assertSame( [], $result->preview->buttons );
		$this->assertSame( [], $result->preview->gallery );
		$this->assertSame( 'https://wiki.example/one.png', $result->preview->image?->url, 'the image still reaches og:image' );
	}

	public function testTheHeaderIsAClickableLinkToThePage(): void {
		// A component renders no clickable title of its own, so the header is a masked
		// link around the bold title.
		$result = $this->builder()->build( new PreviewRequest(
			'Doorman',
			new EmbedConfig(),
			null,
			'https://wiki.example/index.php/Doorman'
		) );

		$this->assertSame( '[**Doorman**](https://wiki.example/index.php/Doorman)', $result->preview->titleMarkdown );
		$this->assertSame( 'Doorman', $result->preview->titlePlain, 'the meta tag stays plain' );
	}

	public function testAProtocolRelativePageUrlGetsAScheme(): void {
		$result = $this->builder()->build( new PreviewRequest(
			'Doorman',
			new EmbedConfig(),
			null,
			'//wiki.example/index.php/Doorman'
		) );

		$this->assertSame(
			'[**Doorman**](https://wiki.example/index.php/Doorman)',
			$result->preview->titleMarkdown
		);
	}

	public function testAParenthesisedPageTitleKeepsItsLinkIntact(): void {
		$result = $this->builder()->build( new PreviewRequest(
			'Doorman (2024)',
			new EmbedConfig(),
			null,
			'https://wiki.example/index.php/Doorman_(2024)'
		) );

		$this->assertSame(
			'[**Doorman (2024)**](https://wiki.example/index.php/Doorman_%282024%29)',
			$result->preview->titleMarkdown
		);
	}

	public function testTheHeaderCanBeAskedForAtAHeadingLevel(): void {
		// A component's text is sized by Discord, and a heading is the only markdown that
		// asks for a bigger one. The '#'s must open the text, so they precede the link.
		$result = $this->builder( heading: 2 )->build( new PreviewRequest(
			'Doorman',
			new EmbedConfig(),
			null,
			'https://wiki.example/index.php/Doorman'
		) );

		$this->assertSame(
			'## [**Doorman**](https://wiki.example/index.php/Doorman)',
			$result->preview->titleMarkdown
		);
		$this->assertSame( 'Doorman', $result->preview->titlePlain, 'the meta tag stays plain' );
	}

	public function testTheHeadingLevelIsCappedAtThree(): void {
		$result = $this->builder( heading: 9 )->build( new PreviewRequest( 'Doorman', new EmbedConfig(), null ) );

		$this->assertSame( '### **Doorman**', $result->preview->titleMarkdown );
	}

	public function testWithoutAUrlTheHeaderStaysPlainBold(): void {
		$result = $this->builder()->build( new PreviewRequest( 'Doorman', new EmbedConfig(), null ) );

		$this->assertSame( '**Doorman**', $result->preview->titleMarkdown );
	}

	public function testAnAuthoredTitleIsFlattenedAndBounded(): void {
		// A line break in the title would end the header link and let the rest render as
		// markdown lines; nothing else bounded its length either.
		$result = $this->builder()->build( new PreviewRequest(
			'Doorman',
			new EmbedConfig( title: "Line one\n# Heading\n@everyone" ),
			null
		) );

		$this->assertSame( 'Line one # Heading @everyone', $result->preview->titlePlain );
		$this->assertStringNotContainsString( "\n", $result->preview->titleMarkdown );
	}

	public function testAnOverlongAuthoredTitleIsCutToFit(): void {
		$result = $this->builder()->build( new PreviewRequest(
			'Doorman',
			new EmbedConfig( title: str_repeat( 'long ', 200 ) ),
			null
		) );

		$this->assertLessThanOrEqual( PreviewBuilder::MAX_TITLE_CHARS, mb_strlen( $result->preview->titlePlain ) );
	}

	public function testMarkdownInAPageTitleStaysText(): void {
		// The bold wrapper is ours; the title's own characters are not formatting.
		$result = $this->builder()->build( new PreviewRequest( 'Doorman_v2 *beta*', new EmbedConfig(), null ) );

		$this->assertSame( 'Doorman_v2 *beta*', $result->preview->titlePlain );
		$this->assertSame( '**Doorman\\_v2 \\*beta\\***', $result->preview->titleMarkdown );
	}

	public function testAnEmptyPageTitleFallsBackToNothingRatherThanAnEmptyHeading(): void {
		$result = $this->builder()->build( new PreviewRequest( '', new EmbedConfig(), null ) );

		$this->assertSame( '', $result->preview->titlePlain );
		$this->assertSame( '', $result->preview->titleMarkdown );
	}

	public function testTheDescriptionBudgetIsConfigurable(): void {
		$intro = new Intro( str_repeat( 'word ', 100 ), str_repeat( 'word ', 100 ) );

		$result = $this->builder( budget: 50 )->build( new PreviewRequest( 'Doorman', new EmbedConfig(), $intro ) );

		$this->assertLessThanOrEqual( 50, mb_strlen( $result->preview->descriptionPlain() ) );
		$this->assertLessThanOrEqual( 50, mb_strlen( $result->preview->descriptionMarkdown() ) );
	}

	public function testNonLatinContentSurvivesEveryPath(): void {
		$files = [ 'File:ドア.png' => new ResolvedMedia( 'https://wiki.example/door.png', 'ドアの写真' ) ];

		$result = $this->builder( [], $files )->build( new PreviewRequest(
			'ドアマン',
			new EmbedConfig(
				description: 'ドアマンはゲームです。',
				image: new MediaSpec( 'File:ドア.png' )
			),
			new Intro( '', '' )
		) );

		$this->assertSame( '**ドアマン**', $result->preview->titleMarkdown );
		$this->assertSame( 'ドアマンはゲームです。', $result->preview->descriptionPlain() );
		$this->assertSame( 'ドアマンはゲームです。', $result->preview->descriptionMarkdown() );
		$this->assertSame( 'ドアの写真', $result->preview->image?->description );
	}
}
