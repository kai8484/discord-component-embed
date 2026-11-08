<?php

/**
 * Service wiring for DiscordComponentEmbed.
 *
 * @license Unlicense
 */

declare( strict_types=1 );

use DiscordComponentEmbed\Config\ArgumentReader;
use DiscordComponentEmbed\Config\ConfigCompiler;
use DiscordComponentEmbed\Embed\ComponentBuilder;
use DiscordComponentEmbed\Embed\PayloadValidator;
use DiscordComponentEmbed\MediaWiki\PagePreviewFactory;
use DiscordComponentEmbed\Output\ComponentEmbedScript;
use DiscordComponentEmbed\Output\OpenGraphTags;
use DiscordComponentEmbed\Parser\DraftRecorder;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\MediaWikiServices;
use PageImages\PageImages;

return [
	'DiscordComponentEmbed.ArgumentReader' => static function (): ArgumentReader {
		return new ArgumentReader();
	},

	'DiscordComponentEmbed.ConfigCompiler' => static function (): ConfigCompiler {
		return new ConfigCompiler();
	},

	'DiscordComponentEmbed.DraftRecorder' => static function (): DraftRecorder {
		return new DraftRecorder();
	},

	'DiscordComponentEmbed.Logger' => static function () {
		return LoggerFactory::getInstance( 'DiscordComponentEmbed' );
	},

	'DiscordComponentEmbed.PagePreviewFactory' => static function ( MediaWikiServices $services ): PagePreviewFactory {
		$pageImages = null;

		// PageImages is optional: without it the preview is text only rather than
		// the page failing to load.
		if ( class_exists( PageImages::class ) && $services->hasService( 'PageImages.PageImages' ) ) {
			$pageImages = $services->getService( 'PageImages.PageImages' );
		}

		return new PagePreviewFactory(
			$services->getMainConfig(),
			$services->getUrlUtils(),
			$services->getRepoGroup(),
			$pageImages
		);
	},

	'DiscordComponentEmbed.ComponentBuilder' => static function ( MediaWikiServices $services ): ComponentBuilder {
		return $services->getService( 'DiscordComponentEmbed.PagePreviewFactory' )->componentBuilder();
	},

	'DiscordComponentEmbed.PayloadValidator' => static function ( MediaWikiServices $services ): PayloadValidator {
		return $services->getService( 'DiscordComponentEmbed.PagePreviewFactory' )->payloadValidator();
	},

	'DiscordComponentEmbed.ComponentEmbedScript' => static function ( MediaWikiServices $services ): ComponentEmbedScript {
		return $services->getService( 'DiscordComponentEmbed.PagePreviewFactory' )->componentEmbedScript();
	},

	'DiscordComponentEmbed.OpenGraphTags' => static function ( MediaWikiServices $services ): OpenGraphTags {
		return $services->getService( 'DiscordComponentEmbed.PagePreviewFactory' )->openGraphTags();
	},






];
