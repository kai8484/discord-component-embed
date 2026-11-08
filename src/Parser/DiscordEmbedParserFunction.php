<?php

declare( strict_types=1 );

namespace DiscordComponentEmbed\Parser;

use DiscordComponentEmbed\Config\ArgumentReader;
use DiscordComponentEmbed\Config\Draft;
use MediaWiki\Hook\ParserFirstCallInitHook;
use MediaWiki\Parser\Parser;

/**
 * Recognises `{{#discord_embed: ...}}`.
 *
 * The call renders nothing. It records what the author asked for against the
 * ParserOutput, which is what survives the parser cache; merging and validation
 * happen once, after the page has been parsed, in EmbedCollector.
 *
 * @license Unlicense
 */
final class DiscordEmbedParserFunction implements ParserFirstCallInitHook {

	public const FUNCTION_NAME = 'discord_embed';

	public function __construct(
		private readonly ArgumentReader $reader,
		private readonly DraftRecorder $recorder,
	) {
	}

	public function onParserFirstCallInit( $parser ): void {
		$parser->setFunctionHook( self::FUNCTION_NAME, [ $this, 'onDiscordEmbed' ] );
	}

	/**
	 * Core hands over named arguments as the single string "name=value", one
	 * argument per call, already split on top-level pipes. Splitting on the first
	 * equals sign is therefore what keeps a value like `?a=b` intact.
	 *
	 * @param string ...$arguments
	 */
	public function onDiscordEmbed( Parser $parser, ...$arguments ): string {
		$named = [];
		$positional = 0;

		foreach ( $arguments as $argument ) {
			if ( !is_string( $argument ) ) {
				continue;
			}

			// Authors write the call across lines, starting each one with a pipe, which
			// leaves an empty argument before the first pipe. It is formatting, not an
			// argument, and reporting it as a mistake would reject every tidily written
			// call on the wiki.
			if ( trim( $argument ) === '' ) {
				continue;
			}

			$equals = strpos( $argument, '=' );

			if ( $equals === false ) {
				// Kept so the reader can report a positional argument by name.
				$named[$positional++] = $argument;
				continue;
			}

			$named[trim( substr( $argument, 0, $equals ) )] = substr( $argument, $equals + 1 );
		}

		$draft = $this->reader->read( $named );

		$this->recorder->record( $parser->getOutput(), $draft, Draft::SOURCE_WIKITEXT );

		return '';
	}
}
