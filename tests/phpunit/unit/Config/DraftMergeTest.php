<?php

declare( strict_types=1 );

namespace DiscordComponentEmbed\Tests\Unit\Config;

use DiscordComponentEmbed\Config\Draft;
use PHPUnit\Framework\TestCase;

/**
 * Several calls on one page merge into one preview: later calls win on the keys
 * they name, and keys they do not name keep their earlier value.
 *
 * @license Unlicense
 */
final class DraftMergeTest extends TestCase {

	public function testALaterCallOverridesTheKeysItNames(): void {
		$merged = ( new Draft( [ 'title' => 'First', 'accent' => '#111111' ] ) )
			->mergeWith( new Draft( [ 'title' => 'Second' ] ) );

		$this->assertSame( 'Second', $merged->values()['title'] );
		$this->assertSame( '#111111', $merged->values()['accent'] );
	}

	public function testWritingAButtonTargetReplacesTheWholeEarlierEntry(): void {
		$earlier = new Draft( [ 'button1' => '[[Doorman|Read more]]' ] );
		$later = new Draft( [ 'button1' => 'https://example.com/read' ] );

		$merged = $earlier->mergeWith( $later );

		$this->assertSame( [ 'button1' => 'https://example.com/read' ], $merged->values() );
	}

	public function testAnEmptyLaterValueDoesNotClearAnEarlierOne(): void {
		// The reader never stores an empty value, so naming a key with nothing is
		// silence rather than an eraser: mode = replace is how a page drops what a
		// template supplied.
		$merged = ( new Draft( [ 'title' => 'Kept' ] ) )->mergeWith( new Draft( [] ) );

		$this->assertSame( 'Kept', $merged->values()['title'] );
	}

	public function testACaptionCanBeAddedToAGalleryItemFromALaterCall(): void {
		$merged = ( new Draft( [ 'gallery1' => 'File:One.png' ] ) )
			->mergeWith( new Draft( [ 'gallery1-description' => 'First shot' ] ) );

		$this->assertSame( 'File:One.png', $merged->values()['gallery1'] );
		$this->assertSame( 'First shot', $merged->values()['gallery1-description'] );
	}

	public function testProblemsFromEveryCallAreKept(): void {
		$merged = ( new Draft( [], [ 'bad key a' ] ) )->mergeWith( new Draft( [], [ 'bad key b' ] ) );

		$this->assertSame( [ 'bad key a', 'bad key b' ], $merged->problems() );
	}


	public function testWikitextWinsAClashEvenWhenTheModuleRanLater(): void {
		$merged = Draft::fromRecords( [
			( new Draft( [ 'title' => 'From wikitext' ] ) )->toRecord( Draft::SOURCE_WIKITEXT ),
			( new Draft( [ 'title' => 'From Lua', 'accent' => '#111111' ] ) )->toRecord( Draft::SOURCE_LUA ),
		] );

		$this->assertSame( 'From wikitext', $merged->values()['title'], 'wikitext takes the lead' );
		$this->assertSame( '#111111', $merged->values()['accent'], 'what only Lua named survives' );
	}

	public function testWikitextWinsAClashWhenItRanFirst(): void {
		$merged = Draft::fromRecords( [
			( new Draft( [ 'title' => 'From Lua' ] ) )->toRecord( Draft::SOURCE_LUA ),
			( new Draft( [ 'title' => 'From wikitext' ] ) )->toRecord( Draft::SOURCE_WIKITEXT ),
		] );

		$this->assertSame( 'From wikitext', $merged->values()['title'] );
	}

	public function testTwoLuaCallsStillMergeInCallOrder(): void {
		$merged = Draft::fromRecords( [
			( new Draft( [ 'title' => 'First', 'image' => 'File:One.png' ] ) )->toRecord( Draft::SOURCE_LUA ),
			( new Draft( [ 'title' => 'Second' ] ) )->toRecord( Draft::SOURCE_LUA ),
		] );

		$this->assertSame( 'Second', $merged->values()['title'] );
		$this->assertSame( 'File:One.png', $merged->values()['image'] );
	}

	public function testARecordWithNoSourceIsReadAsWikitext(): void {
		// Records written before the source was tagged must keep their old meaning.
		$merged = Draft::fromRecords( [
			[ 'values' => [ 'title' => 'Untagged' ], 'problems' => [] ],
			( new Draft( [ 'title' => 'From Lua' ] ) )->toRecord( Draft::SOURCE_LUA ),
		] );

		$this->assertSame( 'Untagged', $merged->values()['title'] );
	}

	public function testProblemsFromBothSourcesAreKept(): void {
		$merged = Draft::fromRecords( [
			( new Draft( [], [ 'lua problem' ] ) )->toRecord( Draft::SOURCE_LUA ),
			( new Draft( [], [ 'wikitext problem' ] ) )->toRecord( Draft::SOURCE_WIKITEXT ),
		] );

		$this->assertSame( [ 'lua problem', 'wikitext problem' ], $merged->problems() );
	}
}
