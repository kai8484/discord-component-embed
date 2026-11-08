<?php

declare( strict_types=1 );

namespace DiscordComponentEmbed\Tests\Unit\Json;

use DiscordComponentEmbed\Json\ScriptSafeJsonEncoder;
use PHPUnit\Framework\TestCase;

/**
 * The payload is embedded in a script element, so the encoded JSON must not be
 * able to close it.
 *
 * @license Unlicense
 */
final class ScriptSafeJsonEncoderTest extends TestCase {

	public function testItEncodesAnArrayAsJson(): void {
		$json = ( new ScriptSafeJsonEncoder() )->encode( [ 'type' => 17, 'components' => [] ] );

		$this->assertSame( '{"type":17,"components":[]}', $json );
	}

	public function testTheEncodedJsonSurvivesADecodeRoundTrip(): void {
		$data = [
			'components' => [
				[ 'type' => 10, 'content' => 'Doors & windows < 7 > 5' ],
				[ 'type' => 2, 'url' => 'https://example.com/a?b=c&d=e' ],
			],
		];

		$json = ( new ScriptSafeJsonEncoder() )->encode( $data );

		$this->assertSame( $data, json_decode( $json, true ) );
	}

	public function testAngleBracketsAndAmpersandsAreEscaped(): void {
		$json = ( new ScriptSafeJsonEncoder() )->encode( [ 'c' => '<b>&</b>' ] );

		$this->assertStringNotContainsString( '<', $json );
		$this->assertStringNotContainsString( '>', $json );
		$this->assertStringNotContainsString( '&', $json );
		$this->assertStringContainsString( '\u003C', $json );
		$this->assertStringContainsString( '\u0026', $json );
	}

	public function testAScriptClosingSequenceCannotAppearInTheOutput(): void {
		$json = ( new ScriptSafeJsonEncoder() )->encode(
			[ 'content' => 'Famous last words: </script><img src=x onerror=alert(1)>' ]
		);

		$this->assertStringNotContainsString( '</script', strtolower( $json ) );
		$this->assertStringNotContainsString( '<img', strtolower( $json ) );
		$this->assertSame(
			'Famous last words: </script><img src=x onerror=alert(1)>',
			json_decode( $json, true )['content']
		);
	}

	public function testNonLatinTextIsNotEscapedAway(): void {
		$json = ( new ScriptSafeJsonEncoder() )->encode( [ 'content' => '日本語とالعربية' ] );

		$this->assertStringContainsString( '日本語とالعربية', $json );
	}

	public function testSlashesAreNotEscaped(): void {
		$json = ( new ScriptSafeJsonEncoder() )->encode( [ 'url' => 'https://example.com/a/b' ] );

		$this->assertStringContainsString( 'https://example.com/a/b', $json );
	}

	public function testInvalidUtf8BytesDoNotProduceAnEmptyOrBrokenDocument(): void {
		$json = ( new ScriptSafeJsonEncoder() )->encode( [ 'content' => "bad \xFF\xFE bytes" ] );

		$this->assertNotSame( '', $json );
		$this->assertIsArray( json_decode( $json, true ) );
	}

	public function testQuotesAndBackslashesAreStillJsonEscaped(): void {
		$json = ( new ScriptSafeJsonEncoder() )->encode( [ 'content' => 'say "hi" \\ bye' ] );

		$this->assertSame( 'say "hi" \\ bye', json_decode( $json, true )['content'] );
	}

	public function testUnicodeLineSeparatorsAreEscaped(): void {
		$json = ( new ScriptSafeJsonEncoder() )->encode( [ 'content' => "line\u{2028}separator\u{2029}" ] );

		$this->assertStringNotContainsString( "\u{2028}", $json );
		$this->assertStringNotContainsString( "\u{2029}", $json );
	}
}
