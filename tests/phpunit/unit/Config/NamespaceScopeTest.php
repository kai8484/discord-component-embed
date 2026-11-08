<?php

declare( strict_types=1 );

namespace DiscordComponentEmbed\Tests\Unit\Config;

use DiscordComponentEmbed\Config\NamespaceScope;
use PHPUnit\Framework\TestCase;

/**
 * `$wgDiscordComponentEmbedNamespaces` is read defensively: a value of the wrong
 * shape is reported rather than letting the extension publish where the operator
 * did not ask it to.
 *
 * @license Unlicense
 */
final class NamespaceScopeTest extends TestCase {

	/** The unit suite runs without MediaWiki, so the ids stand in for NS_*. */
	private const MAIN = 0;
	private const TALK = 1;
	private const USER = 2;

	public function testNullMeansEveryNamespace(): void {
		$scope = NamespaceScope::fromSetting( null );

		$this->assertTrue( $scope->includes( self::MAIN ) );
		$this->assertTrue( $scope->includes( self::TALK ) );
		$this->assertFalse( $scope->isMisconfigured() );
	}

	public function testASingleIdIsAcceptedAsOneNamespace(): void {
		foreach ( [ self::TALK, (string)self::TALK ] as $setting ) {
			$scope = NamespaceScope::fromSetting( $setting );

			$this->assertTrue( $scope->includes( self::TALK ) );
			$this->assertFalse( $scope->includes( self::MAIN ) );
			$this->assertFalse( $scope->isMisconfigured() );
		}
	}

	public function testAListOfIdsLimitsThePreviews(): void {
		$scope = NamespaceScope::fromSetting( [ self::MAIN, self::TALK ] );

		$this->assertTrue( $scope->includes( self::MAIN ) );
		$this->assertTrue( $scope->includes( self::TALK ) );
		$this->assertFalse( $scope->includes( self::USER ) );
	}

	public function testNegativeIdsAreNamespacesToo(): void {
		$this->assertTrue( NamespaceScope::fromSetting( [ -1, -2 ] )->includes( -1 ) );
	}

	public function testAnEmptyListMeansNoNamespaceAtAll(): void {
		$scope = NamespaceScope::fromSetting( [] );

		$this->assertFalse( $scope->includes( self::MAIN ), 'the only way to switch previews off per namespace' );
		$this->assertFalse( $scope->isMisconfigured() );
	}

	public function testValuesThatAreNotIdsAreReported(): void {
		foreach ( [ 'main', 1.5, true, new \stdClass(), [ [] ], [ 1.5 ], [ 'main' ] ] as $setting ) {
			$scope = NamespaceScope::fromSetting( $setting );

			$this->assertTrue( $scope->isMisconfigured(), get_debug_type( $setting ) );
			$this->assertTrue( $scope->includes( self::MAIN ), 'still publishes, but says so' );
		}
	}

}
