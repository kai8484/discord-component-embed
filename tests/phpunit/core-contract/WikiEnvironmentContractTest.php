<?php

declare( strict_types=1 );

namespace DiscordComponentEmbed\Tests\CoreContract;

use MediaWiki\HookContainer\HookRunner;
use MediaWiki\MainConfigSchema;
use MediaWiki\MediaWikiServices;
use MediaWiki\Parser\ParserOutput;
use MediaWiki\Registration\ExtensionRegistry;
use MediaWiki\Title\NamespaceInfo;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;
use RepoGroup;

/**
 * Configuration defaults, namespace shapes, and the manifest and services the
 * extension is loaded through.
 *
 * @license Unlicense
 */
class WikiEnvironmentContractTest extends TestCase {

	/**
	 * Read from disk rather than from the registry: ExtensionRegistry::getAllThings()
	 * exposes credits, not hooks, and this test is about the manifest as shipped.
	 *
	 * @return array<string, mixed>
	 */
	private function manifest(): array {
		$path = dirname( __DIR__, 3 ) . '/extension.json';
		$this->assertFileExists( $path );

		$manifest = json_decode( (string)file_get_contents( $path ), true );
		$this->assertIsArray( $manifest );

		return $manifest;
	}

	public function testTheSkinEmitsNoOpenGraphTagsByDefault(): void {
		// The fallback exists because the wiki usually emits nothing; if this default
		// ever flipped, the extension's deferral policy would have to change with it.
		$default = MainConfigSchema::getDefaultValue( 'SkinMetaTags' );

		$this->assertIsArray( $default );
		$this->assertSame( [], $default );
	}

	public function testGetValidNamespacesIsAListOfIdsAndCanonicalNamespacesIsAMap(): void {
		$namespaces = MediaWikiServices::getInstance()->getNamespaceInfo();

		$valid = $namespaces->getValidNamespaces();
		$canonical = $namespaces->getCanonicalNamespaces();

		$this->assertTrue( array_is_list( $valid ), 'getValidNamespaces() returns namespace IDs' );
		$this->assertFalse( array_is_list( $canonical ), 'getCanonicalNamespaces() returns IDs to names' );

		foreach ( $valid as $id ) {
			$this->assertIsInt( $id );
		}

		$this->assertArrayHasKey( $valid[0], $canonical );
	}

	public function testHasSubpagesIsANamespaceQuestionAnsweredWithoutAPage(): void {
		$namespaces = MediaWikiServices::getInstance()->getNamespaceInfo();

		$this->assertIsBool( $namespaces->hasSubpages( NS_MAIN ) );
		$this->assertIsBool( $namespaces->hasSubpages( NS_TALK ) );
	}

	public function testFindFileDoesNotPromiseAFileBecauseItCanReturnFalse(): void {
		// A `?File` return type would make false impossible, and the null mapping in
		// the image resolver unnecessary; the method is untyped, so the mapping is not.
		$method = new ReflectionMethod( RepoGroup::class, 'findFile' );

		$this->assertSame(
			null,
			$method->getReturnType(),
			'RepoGroup::findFile() is expected to be untyped so that false is possible'
		);
	}

	public function testNamespaceInfoIsReachableAsAService(): void {
		$this->assertInstanceOf(
			NamespaceInfo::class,
			MediaWikiServices::getInstance()->getNamespaceInfo()
		);
	}

	public function testPageImagesServiceKeyAndAccessorShape(): void {
		$services = MediaWikiServices::getInstance();

		if ( !class_exists( 'PageImages\\PageImages' ) ) {
			$this->markTestSkipped( 'PageImages is not installed' );
		}

		if ( !$services->hasService( 'PageImages.PageImages' ) ) {
			$this->markTestSkipped( 'PageImages does not register its service in this install' );
		}

		$pageImages = $services->getService( 'PageImages.PageImages' );

		$this->assertInstanceOf( 'PageImages\\PageImages', $pageImages );

		$method = new ReflectionMethod( $pageImages, 'getImage' );
		$this->assertSame( 1, $method->getNumberOfParameters() );

		$returnType = $method->getReturnType();
		$this->assertNotNull( $returnType, 'getImage() is expected to be typed' );
		$this->assertTrue( $returnType->allowsNull(), 'no page image is a normal answer' );
	}

	public function testUrlUtilsServiceIsTheMediaWikiClassOurCodeTypeHints(): void {
		// Getting this wrong is a TypeError on every page view: MediaWiki\Utils\UrlUtils
		// and the Wikimedia library's own UrlUtils are different classes.
		$urlUtils = MediaWikiServices::getInstance()->getUrlUtils();

		$this->assertInstanceOf( \MediaWiki\Utils\UrlUtils::class, $urlUtils );
		$this->assertTrue( method_exists( $urlUtils, 'expand' ) );
		$this->assertTrue( method_exists( $urlUtils, 'validProtocols' ) );
	}

	public function testParserOutputIsNewableForTests(): void {
		$this->assertInstanceOf( ParserOutput::class, new ParserOutput() );
	}

	public function testTheManifestDeclaresTheVersionFloorAndAppearsInCredits(): void {
		$manifest = $this->manifest();

		$this->assertSame( [ 'MediaWiki' => '>= 1.46.0' ], $manifest['requires'] ?? null );

		// A manifest with a name is assembled into Special:Version and the siteinfo
		// API automatically, which is also how an administrator checks that the
		// extension loaded at all.
		$this->assertArrayHasKey( 'name', $manifest );

		$credits = ExtensionRegistry::getInstance()->getAllThings()['DiscordComponentEmbed'] ?? null;

		if ( $credits !== null ) {
			$this->assertSame( $manifest['name'], $credits['name'] );
		}
	}

	public function testEveryHookHandlerCanBeBuiltFromTheServicesTheManifestNames(): void {
		// A manifest and constructor drifting apart is a fatal error on the first
		// page view, and it is invisible in the source of either file alone.
		$manifest = $this->manifest();
		$services = MediaWikiServices::getInstance();
		$handlers = $manifest['HookHandlers'] ?? [];

		$this->assertSame(
			[ 'ParserFunction', 'EmbedCollector', 'OutputHook', 'ScribuntoHooks' ],
			array_keys( $handlers )
		);

		foreach ( $handlers as $name => $handler ) {
			// A handler with no dependencies needs no services, and Scribunto's is only
			// loadable at all when Scribunto is installed.
			if ( !isset( $handler['services'] ) ) {
				continue;
			}

			$class = $handler['class'];
			$this->assertTrue( class_exists( $class ), "handler {$name} class exists" );

			$constructor = ( new ReflectionClass( $class ) )->getConstructor();
			$this->assertNotNull( $constructor, "handler {$name} has a constructor" );

			$this->assertSame(
				count( $handler['services'] ),
				$constructor->getNumberOfParameters(),
				"handler {$name} takes one argument per declared service"
			);

			foreach ( $handler['services'] as $index => $serviceName ) {
				if ( $serviceName === 'MainConfig' ) {
					continue;
				}

				$this->assertTrue(
					$services->hasService( $serviceName ),
					"service {$serviceName} for handler {$name} is registered"
				);

				$parameter = $constructor->getParameters()[$index];
				$type = $parameter->getType();

				if ( $type === null || $type->isBuiltin() ) {
					continue;
				}

				$service = $services->getService( $serviceName );
				$this->assertInstanceOf(
					$type->getName(),
					$service,
					"handler {$name} parameter \${$parameter->getName()} accepts {$serviceName}"
				);
			}
		}
	}

	public function testEveryRegisteredHandlerImplementsTheInterfaceForItsHook(): void {
		$manifest = $this->manifest();
		$handlers = $manifest['HookHandlers'] ?? [];
		$interfaces = [
			'ParserFirstCallInit' => \MediaWiki\Parser\Hook\ParserFirstCallInitHook::class,
			'ParserAfterTidy' => \MediaWiki\Parser\Hook\ParserAfterTidyHook::class,
			'OutputPageParserOutput' => \MediaWiki\Output\Hook\OutputPageParserOutputHook::class,
			'OutputPageAfterGetHeadLinksArray' => \MediaWiki\Output\Hook\OutputPageAfterGetHeadLinksArrayHook::class,
			'ScribuntoExternalLibraries' => 'MediaWiki\\Extension\\Scribunto\\Hooks\\ScribuntoExternalLibrariesHook',
		];

		$this->assertNotSame( [], $manifest['Hooks'] ?? [], 'the manifest registers hooks' );

		foreach ( $manifest['Hooks'] as $hook => $handlerName ) {
			$this->assertArrayHasKey( $hook, $interfaces, "hook {$hook} has a known interface" );
			$this->assertArrayHasKey( $handlerName, $handlers, "hook {$hook} names a declared handler" );

			// Scribunto is optional: without it the hook never fires and its handler
			// cannot even be autoloaded, so there is nothing to check here.
			if ( !interface_exists( $interfaces[$hook] ) ) {
				continue;
			}

			$this->assertTrue(
				is_a( $handlers[$handlerName]['class'], $interfaces[$hook], true ),
				"handler for {$hook} implements {$interfaces[$hook]}"
			);
		}
	}

	public function testTheHookRunnerDispatchesEveryHookTheManifestRegisters(): void {
		// If core stopped calling one of these, the extension would silently stop
		// working rather than fail.
		$runner = new HookRunner( MediaWikiServices::getInstance()->getHookContainer() );

		foreach ( [
			'onParserFirstCallInit',
			'onParserAfterTidy',
			'onOutputPageParserOutput',
			'onOutputPageAfterGetHeadLinksArray',
		] as $method ) {
			$this->assertTrue( method_exists( $runner, $method ), "core dispatches {$method}" );
		}
	}
}
