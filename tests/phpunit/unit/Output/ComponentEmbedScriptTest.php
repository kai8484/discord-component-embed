<?php

declare( strict_types=1 );

namespace DiscordComponentEmbed\Tests\Unit\Output;

use DiscordComponentEmbed\Json\ScriptSafeJsonEncoder;
use DiscordComponentEmbed\Output\ComponentEmbedScript;
use PHPUnit\Framework\TestCase;

/**
 * One script element in the head carrying the payload, in the envelope Discord's
 * link preview reads: a Container under the "component" key.
 *
 * @license Unlicense
 */
final class ComponentEmbedScriptTest extends TestCase {

	private function script(): ComponentEmbedScript {
		return new ComponentEmbedScript( new ScriptSafeJsonEncoder() );
	}

	public function testTheEnvelopeWrapsTheContainerUnderComponent(): void {
		$script = $this->script()->build( [ [ 'type' => 17, 'components' => [] ] ] );

		$this->assertSame(
			'<script id="discord:component-embed" type="application/json">'
			. '{"component":{"type":17,"components":[]}}'
			. '</script>',
			$script
		);
	}

	public function testNothingToPublishProducesNoScript(): void {
		$this->assertNull( $this->script()->build( [] ) );
	}

	public function testTheScriptCarriesExactlyOneContainer(): void {
		$script = $this->script()->build( [
			[ 'type' => 17, 'components' => [ [ 'type' => 10, 'content' => 'Hi' ] ] ],
		] );

		$this->assertNotNull( $script );
		$json = $this->payloadOf( $script );
		$this->assertSame( 17, $json['component']['type'] );
		$this->assertCount( 1, $json['component']['components'] );
	}

	public function testALabelContainingAScriptTagCannotCloseTheElement(): void {
		$script = $this->script()->build( [
			[ 'type' => 17, 'components' => [ [
				'type' => 1,
				'components' => [ [
					'type' => 2,
					'style' => 5,
					'label' => '</script><script>alert(1)</script>',
					'url' => 'https://example.com/x',
				] ],
			] ] ],
		] );

		$this->assertNotNull( $script );
		$this->assertSame( 1, substr_count( strtolower( $script ), '</script>' ) );
		$this->assertStringEndsWith( '</script>', $script );
		$this->assertSame(
			'</script><script>alert(1)</script>',
			$this->payloadOf( $script )['component']['components'][0]['components'][0]['label']
		);
	}

	public function testThePayloadIsValidJsonOnItsOwn(): void {
		$script = $this->script()->build( [
			[ 'type' => 17, 'components' => [ [ 'type' => 10, 'content' => "Quotes \" and unicode 日本" ] ] ],
		] );

		$json = $this->payloadOf( $script );
		$this->assertSame( 'Quotes " and unicode 日本', $json['component']['components'][0]['content'] );
	}

	private function payloadOf( string $script ): array {
		$start = strpos( $script, '>' ) + 1;
		$end = strrpos( $script, '</script>' );

		return json_decode( substr( $script, $start, $end - $start ), true, 512, JSON_THROW_ON_ERROR );
	}
}
