<?php

declare( strict_types=1 );

namespace DiscordComponentEmbed\Tests\Unit\Content;

use DiscordComponentEmbed\Content\DiscordEmoji;
use PHPUnit\Framework\TestCase;

/**
 * The footer emoji is inserted into markdown unescaped, so the value has to be one
 * emoji mention and nothing else.
 *
 * @license Unlicense
 */
final class DiscordEmojiTest extends TestCase {

	public function testAnEmojiMentionIsAccepted(): void {
		$this->assertTrue( DiscordEmoji::isMention( '<:deadlock:1569028510943682900>' ) );
		$this->assertTrue( DiscordEmoji::isMention( '<a:spin:123456789012345678>' ) );
	}

	public function testAnythingElseIsRefused(): void {
		foreach ( [
			'',
			'🐴',
			':deadlock:',
			'<:deadlock:>',
			'<:dead lock:123>',
			'<:deadlock:123>\n**bold**',
			"<:deadlock:123> | [x](https://example.com)",
			' <:deadlock:123>',
		] as $value ) {
			$this->assertFalse( DiscordEmoji::isMention( $value ), $value );
		}
	}

}
