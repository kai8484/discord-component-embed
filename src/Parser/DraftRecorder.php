<?php

declare( strict_types=1 );

namespace DiscordComponentEmbed\Parser;

use DiscordComponentEmbed\Config\Draft;
use MediaWiki\Parser\ParserOutput;

/**
 * Records one authored call against the ParserOutput it was made while parsing.
 *
 * Both authoring surfaces write the same record, which is what lets a module and a
 * template contribute to one preview instead of two. The record carries its source
 * so the merge in EmbedCollector can put wikitext ahead of Lua regardless of the
 * order the calls happened to run in.
 *
 * Accumulated rather than appended with appendExtensionData(): that merges string
 * sets and integer sums, not the list of records a sequence of calls needs. The
 * cost is that this ParserOutput cannot be updated selectively, which only affects
 * pages that make more than one call.
 *
 * @license Unlicense
 */
final class DraftRecorder {

	public const DRAFT_KEY = 'DiscordComponentEmbed:drafts';

	public function record( ParserOutput $output, Draft $draft, string $source ): void {
		$records = $output->getExtensionData( self::DRAFT_KEY );
		$records = is_array( $records ) ? $records : [];
		$records[] = $draft->toRecord( $source );

		$output->setExtensionData( self::DRAFT_KEY, $records );
	}

}
