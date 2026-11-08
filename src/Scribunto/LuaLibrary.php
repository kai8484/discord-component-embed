<?php

declare( strict_types=1 );

namespace DiscordComponentEmbed\Scribunto;

use DiscordComponentEmbed\Config\Draft;
use MediaWiki\Extension\Scribunto\Engines\LuaCommon\LibraryBase;
use MediaWiki\MediaWikiServices;

/**
 * `mw.ext.discordembed` — the parser function's authoring surface, for modules.
 *
 * A module declares the embed the same way a template does, so anything a module
 * can compute (a title from a data table, a gallery built in a loop) can go into
 * the preview. Both surfaces write to the same ParserOutput, and wikitext wins a
 * clash on any one argument.
 *
 * @license Unlicense
 */
final class LuaLibrary extends LibraryBase {

	/** @inheritDoc */
	public function register() {
		return $this->getEngine()->registerInterface(
			__DIR__ . '/mw.ext.discordembed.lua',
			[ 'embed' => [ $this, 'embed' ] ],
			[]
		);
	}

	/**
	 * @param array|null $arguments The table a module passed, as Scribunto converted it.
	 * @return array Lua return values; empty, so the call yields nothing.
	 */
	public function embed( $arguments = null ) {
		$this->checkType( 'mw.ext.discordembed.embed', 1, $arguments, 'table' );

		$services = MediaWikiServices::getInstance();

		$draft = LuaArguments::draft(
			$services->getService( 'DiscordComponentEmbed.ArgumentReader' ),
			$arguments
		);

		$services->getService( 'DiscordComponentEmbed.DraftRecorder' )
			->record( $this->getParser()->getOutput(), $draft, Draft::SOURCE_LUA );

		return [];
	}

}
