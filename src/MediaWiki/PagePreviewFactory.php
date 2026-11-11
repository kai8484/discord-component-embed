<?php

declare( strict_types=1 );

namespace DiscordComponentEmbed\MediaWiki;

use Config;
use DiscordComponentEmbed\Content\DescriptionBuilder;
use DiscordComponentEmbed\Content\DiscordEmoji;
use DiscordComponentEmbed\Content\DiscordMarkdown;
use DiscordComponentEmbed\Content\IntroExtractor;
use DiscordComponentEmbed\Content\LinkBase;
use DiscordComponentEmbed\Content\Truncator;
use DiscordComponentEmbed\Embed\ButtonResolver;
use DiscordComponentEmbed\Embed\ComponentBuilder;
use DiscordComponentEmbed\Embed\PayloadValidator;
use DiscordComponentEmbed\Embed\PreviewBuilder;
use DiscordComponentEmbed\Json\ScriptSafeJsonEncoder;
use DiscordComponentEmbed\Media\MediaResolver;
use DiscordComponentEmbed\Output\ComponentEmbedScript;
use DiscordComponentEmbed\Output\OpenGraphTags;
use MediaWiki\Title\Title;
use PageImages\PageImages;
use RepoGroup;
use MediaWiki\Utils\UrlUtils;

/**
 * Assembles the pipeline for one page.
 *
 * The pieces that need page context (which links resolve to, which image the
 * article has) are created per page rather than registered as services, so no
 * request state ends up in a shared object. Everything else is stateless.
 *
 * @license Unlicense
 */
final class PagePreviewFactory {

	public function __construct(
		private readonly Config $config,
		private readonly UrlUtils $urlUtils,
		private readonly RepoGroup $repoGroup,
		private readonly ?PageImages $pageImages,
	) {
	}

	public function previewBuilder( Title $pageTitle ): PreviewBuilder {
		$links = new MediaWikiLinkResolver( $pageTitle, $this->urlUtils );

		return new PreviewBuilder(
			$links,
			new MediaResolver( $this->imageResolver() ),
			new PageImagesProvider(
				$pageTitle,
				$this->urlUtils,
				$this->pageImages,
				$this->thumbnailWidth()
			),
			new DescriptionBuilder( new Truncator(), $this->descriptionBudget() ),
			new ButtonResolver( $links, new Truncator() ),
			$this->titleHeading(),
			(bool)$this->config->get( 'DiscordComponentEmbedComponents' ),
			new Truncator(),
			$this->footerMarkdown()
		);
	}

	private function imageResolver(): RepoGroupImageResolver {
		return new RepoGroupImageResolver( $this->repoGroup, $this->urlUtils, $this->thumbnailWidth() );
	}

	/**
	 * Links in a preview leave the wiki, so parser output's relative hrefs are
	 * expanded against the server and the page they came from.
	 */
	public function introExtractor( Title $pageTitle ): IntroExtractor {
		return new IntroExtractor( new LinkBase(
			(string)$this->config->get( 'Server' ),
			$pageTitle->getFullURL()
		) );
	}

	public function componentBuilder(): ComponentBuilder {
		return new ComponentBuilder();
	}

	public function payloadValidator(): PayloadValidator {
		return new PayloadValidator();
	}

	public function componentEmbedScript(): ComponentEmbedScript {
		return new ComponentEmbedScript( new ScriptSafeJsonEncoder() );
	}

	public function openGraphTags(): OpenGraphTags {
		return new OpenGraphTags();
	}

	private function descriptionBudget(): int {
		return max( 1, (int)$this->config->get( 'DiscordComponentEmbedDescriptionBudget' ) );
	}

	private function thumbnailWidth(): int {
		return max( 1, (int)$this->config->get( 'DiscordComponentEmbedThumbnailWidth' ) );
	}

	/**
	 * 0 keeps the header at body size; 1 to 3 ask Discord for a heading.
	 */
	/**
	 * The small print under the divider: the wiki's mark and its name, and nothing
	 * else.
	 */
	private function footerMarkdown(): string {
		if ( !$this->config->get( 'DiscordComponentEmbedFooter' ) ) {
			return '';
		}

		// The footer is markdown, and an emoji goes into it verbatim, so anything that is
		// not a single emoji mention would spill into the formatting around it.
		$emoji = trim( (string)$this->config->get( 'DiscordComponentEmbedFooterEmoji' ) );

		if ( !DiscordEmoji::isMention( $emoji ) ) {
			$emoji = '';
		}

		$site = trim( (string)$this->config->get( 'DiscordComponentEmbedFooterName' ) );

		if ( $site === '' ) {
			$site = trim( (string)$this->config->get( 'Sitename' ) );
		}

		return '-# ' . ( $emoji === '' ? '' : $emoji . '  ' ) . DiscordMarkdown::escapeText( $site );
	}

	private function titleHeading(): int {
		return (int)$this->config->get( 'DiscordComponentEmbedTitleHeading' );
	}
}
