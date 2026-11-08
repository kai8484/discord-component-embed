<?php

declare( strict_types=1 );

namespace DiscordComponentEmbed\Scribunto;

use MediaWiki\Extension\Scribunto\Hooks\ScribuntoExternalLibrariesHook;

/**
 * Registers the Lua library with Scribunto, which is optional: without it this
 * hook never runs, and nothing else in the extension depends on it.
 *
 * @license Unlicense
 */
final class ScribuntoHooks implements ScribuntoExternalLibrariesHook {

	public function onScribuntoExternalLibraries( string $engine, array &$extraLibraries ) {
		if ( $engine === 'lua' ) {
			$extraLibraries['mw.ext.discordembed'] = LuaLibrary::class;
		}
	}

}
