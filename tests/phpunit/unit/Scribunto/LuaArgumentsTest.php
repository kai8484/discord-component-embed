<?php

declare( strict_types=1 );

namespace DiscordComponentEmbed\Tests\Unit\Scribunto;

use DiscordComponentEmbed\Config\ArgumentReader;
use DiscordComponentEmbed\Scribunto\LuaArguments;
use PHPUnit\Framework\TestCase;

/**
 * A module's table has to become the same draft a template produces, including
 * the cases Lua types get wrong if they are cast blindly: booleans, numbers and
 * nil-valued keys.
 *
 * @license Unlicense
 */
final class LuaArgumentsTest extends TestCase {

	private function draft( array $arguments ): \DiscordComponentEmbed\Config\Draft {
		return LuaArguments::draft( new ArgumentReader(), $arguments );
	}

	public function testStringsBecomeTheArgumentsTheyName(): void {
		$draft = $this->draft( [ 'title' => 'Abrams', 'image' => 'File:Cover.png' ] );

		$this->assertSame( [ 'title' => 'Abrams', 'image' => 'File:Cover.png' ], $draft->values() );
		$this->assertSame( [], $draft->problems() );
	}

	public function testBooleansBecomeYesAndNo(): void {
		// (string)false is "", which the reader would drop as an empty value, so a
		// module turning the preview off would instead leave it on.
		$draft = $this->draft( [ 'enabled' => false, 'spoiler' => true ] );

		$this->assertSame( 'no', $draft->values()['enabled'] );
		$this->assertSame( 'yes', $draft->values()['spoiler'] );
	}

	public function testNumbersBecomeStringsSoButtonNumberingWorks(): void {
		$draft = $this->draft( [ 'button1' => 42 ] );

		$this->assertSame( '42', $draft->values()['button1'] );
	}

	public function testANilValuedKeyIsAbsentRatherThanEmpty(): void {
		$draft = $this->draft( [ 'title' => 'Kept', 'image' => null ] );

		$this->assertSame( [ 'title' => 'Kept' ], $draft->values() );
	}

	public function testAKeyWithTrailingWhitespaceIsTheSameArgument(): void {
		// "gallery1\n" used to pass the name check and then look up a key that was never
		// there, so the argument did nothing and PHP raised a warning.
		$draft = $this->draft( [ "title\n" => 'Named', "enabled\n" => false ] );

		$this->assertSame( 'Named', $draft->values()['title'] );
		$this->assertSame( 'no', $draft->values()['enabled'] );
	}

	public function testAnUnknownArgumentIsReportedLikeAnyOtherCall(): void {
		$draft = $this->draft( [ 'buttom1' => 'https://example.com' ] );

		$this->assertSame( [], $draft->values() );
		$this->assertSame( [ 'Unknown argument "buttom1" passed to {{#discord_embed:}}.' ], $draft->problems() );
	}

	public function testAPositionalValueIsReportedByName(): void {
		$draft = $this->draft( [ 0 => 'stray' ] );

		$this->assertSame( [], $draft->values() );
		$this->assertStringContainsString( 'named arguments only', $draft->problems()[0] );
	}

	public function testANestedTableIsReportedAsUnusable(): void {
		$draft = $this->draft( [ 'gallery1' => [ 'File:One.png' ] ] );

		$this->assertSame( [], $draft->values() );
		$this->assertSame( [ 'Argument "gallery1" has no usable value.' ], $draft->problems() );
	}

}
