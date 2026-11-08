<?php

declare( strict_types=1 );

namespace DiscordComponentEmbed\Config;

/**
 * What the authors have said so far, as a flat map of argument name to value.
 *
 * Keeping it flat is what makes merging trivial: a later call wins on exactly the
 * keys it names, so `button1 = <new url>` replaces the earlier button1 entry
 * while an earlier `accent` survives a later call that never mentions it.
 *
 * @license Unlicense
 */
final class Draft {

	/**
	 * Where a call came from. Wikitext wins a clash whatever order the calls came in,
	 * so the source is recorded rather than inferred from parse order.
	 */
	public const SOURCE_WIKITEXT = 'wikitext';
	public const SOURCE_LUA = 'lua';

	/**
	 * @param array<string, string> $values Argument name to trimmed value.
	 * @param list<string> $problems Human-readable authoring problems.
	 */
	public function __construct(
		private readonly array $values = [],
		private readonly array $problems = [],
	) {
	}

	/** @return array<string, string> */
	public function values(): array {
		return $this->values;
	}

	/** @return list<string> */
	public function problems(): array {
		return $this->problems;
	}

	public function mergeWith( self $later ): self {
		return new self(
			array_replace( $this->values, $later->values ),
			array_merge( $this->problems, $later->problems )
		);
	}

	/**
	 * The form stored on the ParserOutput, one entry per call, tagged with where it
	 * came from so the merge can honour wikitext's precedence later.
	 *
	 * @return array{values: array<string, string>, problems: list<string>, source: string}
	 */
	public function toRecord( string $source ): array {
		return [
			'values' => $this->values,
			'problems' => $this->problems,
			'source' => $source,
		];
	}

	/**
	 * Folds every call a page made into one draft.
	 *
	 * The sources are folded one at a time — Lua first, then every wikitext call on
	 * top — so a wikitext argument wins a clash regardless of where it appeared on
	 * the page, while within one source the later call still wins the way two calls
	 * on one page always have. Records with no source predate the tagging and are
	 * read as wikitext.
	 *
	 * @param array<mixed> $records
	 */
	public static function fromRecords( array $records ): self {
		$merged = new self();

		foreach ( [ self::SOURCE_LUA, self::SOURCE_WIKITEXT ] as $source ) {
			foreach ( $records as $record ) {
				if ( !is_array( $record ) ) {
					continue;
				}

				if ( ( $record['source'] ?? self::SOURCE_WIKITEXT ) !== $source ) {
					continue;
				}

				$merged = $merged->mergeWith( new self(
					is_array( $record['values'] ?? null ) ? $record['values'] : [],
					is_array( $record['problems'] ?? null ) ? array_values( $record['problems'] ) : []
				) );
			}
		}

		return $merged;
	}

}
