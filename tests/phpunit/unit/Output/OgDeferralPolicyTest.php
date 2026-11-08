<?php

declare( strict_types=1 );

namespace DiscordComponentEmbed\Tests\Unit\Output;

use DiscordComponentEmbed\Output\OgDeferralPolicy;
use PHPUnit\Framework\TestCase;

/**
 * The wiki's own preview must not be replaced by a worse one, and ours must not be
 * duplicated alongside it.
 *
 * The decision cannot be made by looking at the tags already on the page: our
 * output hook runs before the skin contributes core's og: tags, so at that moment
 * nothing looks missing. It is made from configuration instead, which does not care
 * about hook order, and from whatever is genuinely already present.
 *
 * @license Unlicense
 */
final class OgDeferralPolicyTest extends TestCase {

	public function testEverythingIsEmittedOnAPlainWiki(): void {
		$policy = OgDeferralPolicy::fromSources();

		$this->assertTrue( $policy->shouldAdd( 'og:title' ) );
		$this->assertTrue( $policy->shouldAdd( 'og:description' ) );
		$this->assertTrue( $policy->shouldAdd( 'og:image' ) );
	}

	public function testAKeyTheSkinWillEmitIsDeferredTo(): void {
		$policy = OgDeferralPolicy::fromSources( [ 'og:title', 'og:type' ] );

		$this->assertFalse( $policy->shouldAdd( 'og:title' ) );
		$this->assertFalse( $policy->shouldAdd( 'og:type' ) );
		$this->assertTrue( $policy->shouldAdd( 'og:description' ) );
	}

	public function testPageImagesOwningOgImageIsDeferredTo(): void {
		$policy = OgDeferralPolicy::fromSources( [ 'og:image', 'og:image:width', 'og:image:height' ] );

		$this->assertFalse( $policy->shouldAdd( 'og:image' ) );
		$this->assertFalse( $policy->shouldAdd( 'og:image:width' ) );
		$this->assertTrue( $policy->shouldAdd( 'og:description' ) );
	}

	public function testATagAlreadyOnThePageIsNotAddedAgain(): void {
		$policy = OgDeferralPolicy::fromSources( [], [ 'og:title' ] );

		$this->assertFalse( $policy->shouldAdd( 'og:title' ) );
		$this->assertTrue( $policy->shouldAdd( 'og:description' ) );
	}

	public function testKeysAreMatchedCaseInsensitively(): void {
		$policy = OgDeferralPolicy::fromSources( [ 'OG:Title' ] );

		$this->assertFalse( $policy->shouldAdd( 'og:title' ) );
	}

	public function testDuplicatesInTheSourcesAreHarmless(): void {
		$policy = OgDeferralPolicy::fromSources( [ 'og:title', 'og:title' ], [ 'og:title' ] );

		$this->assertFalse( $policy->shouldAdd( 'og:title' ) );
	}
}
