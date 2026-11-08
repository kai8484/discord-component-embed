<?php

declare( strict_types=1 );

namespace DiscordComponentEmbed\Tests\CoreContract;

use MediaWiki\Output\OutputPage;
use MediaWiki\Request\FauxRequest;
use MediaWiki\Context\RequestContext;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * How meta tags are added and what a page's current tags look like.
 *
 * @license Unlicense
 */
class OutputPageContractTest extends TestCase {

	private function outputPage(): OutputPage {
		$context = new RequestContext();
		$context->setRequest( new FauxRequest() );
		$context->setLanguage( 'en' );

		return new OutputPage( $context );
	}

	public function testAddMetaTakesExactlyTwoArguments(): void {
		$this->assertSame( 2, ( new ReflectionMethod( OutputPage::class, 'addMeta' ) )->getNumberOfParameters() );
	}

	public function testGetMetaTagsIsAListOfNameValuePairsNotAMap(): void {
		$output = $this->outputPage();

		$output->addMeta( 'og:title', 'Doorman' );
		$output->addMeta( 'description', 'A game.' );

		$tags = $output->getMetaTags();

		$this->assertSame( [ [ 'og:title', 'Doorman' ], [ 'description', 'A game.' ] ], $tags );
	}

	public function testAddMetaStoresTheNameVerbatimBecauseCoreRendersOgAsAProperty(): void {
		// Passing 'og:title' is what produces property="og:title"; anything else
		// would produce a name attribute instead.
		$output = $this->outputPage();
		$output->addMeta( 'og:image', 'https://wiki.example/a.png' );

		$this->assertSame( 'og:image', $output->getMetaTags()[0][0] );
	}

	public function testHeadItemIsKeyedByNameSoReAddingReplaces(): void {
		$output = $this->outputPage();

		$output->addHeadItem( 'discord:component-embed', '<script>one</script>' );
		$output->addHeadItem( 'discord:component-embed', '<script>two</script>' );

		$this->assertSame(
			[ 'discord:component-embed' => '<script>two</script>' ],
			$output->getHeadItemsArray()
		);
	}
}
