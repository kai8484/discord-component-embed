<?php

declare( strict_types=1 );

namespace DiscordComponentEmbed\Tests\Unit\Embed;

use DiscordComponentEmbed\Embed\ActionRowPacker;
use DiscordComponentEmbed\Embed\ResolvedButton;
use PHPUnit\Framework\TestCase;

/**
 * Buttons never sit directly in a container: Discord requires every button to be
 * a child of an Action Row, and an Action Row holds at most five children.
 *
 * @license Unlicense
 */
final class ActionRowPackerTest extends TestCase {

	/** @return list<ResolvedButton> */
	private function buttons( int $count ): array {
		$buttons = [];

		for ( $i = 1; $i <= $count; $i++ ) {
			$buttons[] = new ResolvedButton( 'https://example.com/' . $i, 'Button ' . $i );
		}

		return $buttons;
	}

	public function testNoButtonsMeansNoRows(): void {
		$this->assertSame( [], ( new ActionRowPacker() )->pack( [] ) );
	}

	public function testOneButtonFillsOneRow(): void {
		$rows = ( new ActionRowPacker() )->pack( $this->buttons( 1 ) );

		$this->assertCount( 1, $rows );
		$this->assertCount( 1, $rows[0] );
	}

	public function testFiveButtonsShareOneRow(): void {
		$rows = ( new ActionRowPacker() )->pack( $this->buttons( 5 ) );

		$this->assertCount( 1, $rows );
		$this->assertCount( 5, $rows[0] );
	}

	public function testASixthButtonStartsASecondRow(): void {
		$rows = ( new ActionRowPacker() )->pack( $this->buttons( 6 ) );

		$this->assertCount( 2, $rows );
		$this->assertCount( 5, $rows[0] );
		$this->assertCount( 1, $rows[1] );
	}

	public function testTwentyFiveButtonsFillFiveRows(): void {
		$rows = ( new ActionRowPacker() )->pack( $this->buttons( 25 ) );

		$this->assertCount( 5, $rows );

		foreach ( $rows as $row ) {
			$this->assertCount( 5, $row );
		}
	}

	public function testButtonOrderIsPreservedAcrossRows(): void {
		$rows = ( new ActionRowPacker() )->pack( $this->buttons( 7 ) );

		$this->assertSame( 'https://example.com/6', $rows[1][0]->url );
		$this->assertSame( 'https://example.com/7', $rows[1][1]->url );
	}
}
