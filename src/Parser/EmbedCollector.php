<?php

declare( strict_types=1 );

namespace DiscordComponentEmbed\Parser;

use Config;
use DiscordComponentEmbed\Config\ButtonTargetKind;
use DiscordComponentEmbed\Config\ConfigCompiler;
use DiscordComponentEmbed\Config\Draft;
use DiscordComponentEmbed\Config\EmbedConfig;
use DiscordComponentEmbed\Config\EmbedOutcome;
use DiscordComponentEmbed\Config\NamespaceScope;
use DiscordComponentEmbed\Embed\ComponentBuilder;
use DiscordComponentEmbed\Embed\PayloadValidator;
use DiscordComponentEmbed\Embed\PreviewRequest;
use DiscordComponentEmbed\MediaWiki\PagePreviewFactory;
use DiscordComponentEmbed\MediaWiki\RepoGroupImageResolver;
use DiscordComponentEmbed\Output\StoredPreview;
use MediaWiki\Hook\ParserAfterTidyHook;
use MediaWiki\Linker\Linker;
use MediaWiki\Title\Title;
use MediaWiki\Parser\Parser;
use MediaWiki\Parser\ParserOutput;
use Psr\Log\LoggerInterface;

/**
 * Builds the preview once, after the page has been parsed.
 *
 * Everything expensive and everything fallible happens here rather than while the
 * page is rendering: which paragraphs are the intro, which File: titles resolve,
 * where each button points. The result goes into the ParserOutput, so a cached page
 * view publishes the same preview without resolving anything again.
 *
 * Nothing here is fatal. A configuration Discord would refuse, or a file that does
 * not exist, leaves the page's normal rendering and its existing metadata untouched.
 *
 * @license Unlicense
 */
final class EmbedCollector implements ParserAfterTidyHook {

	public function __construct(
		private readonly Config $config,
		private readonly ConfigCompiler $compiler,
		private readonly PagePreviewFactory $factory,
		private readonly ComponentBuilder $componentBuilder,
		private readonly PayloadValidator $payloadValidator,
		private readonly LoggerInterface $logger,
	) {
	}

	public function onParserAfterTidy( $parser, &$text ): void {
		$output = $parser->getOutput();
		$title = $parser->getTitle();

		if ( $title === null || !$this->isEnabledFor( $title->getNamespace() ) ) {
			return;
		}

		$compiled = $this->compiler->compile( $this->mergedDraft( $output ) );
		$outcome = $compiled->outcome();

		if ( $outcome === EmbedOutcome::Disabled ) {
			$this->logger->debug( 'The page switched its own Discord preview off', [
				'page' => $title->getPrefixedText(),
			] );

			return;
		}

		if ( $outcome === EmbedOutcome::Invalid ) {
			$this->logProblems( 'Discord preview configuration rejected', $title->getPrefixedText(), $compiled->problems() );

			return;
		}

		$config = $compiled->config();
		$result = $this->factory->previewBuilder( $title )->build(
			new PreviewRequest(
				$title->getPrefixedText(),
				$config,
				$this->intro( $text, $config, $title ),
				$title->getFullURL()
			)
		);

		if ( $result->problems !== [] ) {
			$this->logProblems( 'Discord preview rejected', $title->getPrefixedText(), $result->problems );

			return;
		}

		// A wiki that publishes only Open Graph tags has nothing to validate: building
		// the payload there would cost a parse-time pass, and a payload Discord would
		// refuse must not take the meta tags down with it.
		$components = [];

		if ( $this->config->get( 'DiscordComponentEmbedComponents' ) ) {
			$components = $this->componentBuilder->build( $result->preview->toContent() );
			$payloadProblems = $this->payloadValidator->validate( $components );

			if ( $payloadProblems !== [] ) {
				$this->logProblems( 'Discord preview rejected', $title->getPrefixedText(), $payloadProblems );

				return;
			}
		}

		if ( $result->dropped !== [] ) {
			$this->logProblems( 'Discord preview left something out', $title->getPrefixedText(), $result->dropped );
		}

		$output->setExtensionData(
			StoredPreview::KEY,
			StoredPreview::create( $result->preview, $components )->toArray()
		);

		try {
			$this->recordDependencies( $output, $config, $title );
		} catch ( \Throwable $error ) {
			// A preview that publishes without its dependencies is a stale card later;
			// one that does not publish at all is a broken page now.
			$this->logger->warning( 'Discord preview dependencies were not recorded: {error}', [
				'error' => $error->getMessage(),
			] );
		}
	}

	/**
	 * Tells MediaWiki what this preview depends on, so re-uploading a file or moving a
	 * linked page re-parses this one instead of leaving the card stale.
	 *
	 * Only the authored titles are recorded: they are exactly the files and pages the
	 * preview names, and a title that does not resolve yet is the one most worth
	 * recording, because it will exist after the next upload.
	 */
	private function recordDependencies( ParserOutput $output, EmbedConfig $config, Title $page ): void {
		foreach ( array_merge( $config->image === null ? [] : [ $config->image ], $config->gallery ) as $spec ) {
			$file = RepoGroupImageResolver::title( $spec->title );

			if ( $file !== null ) {
				$output->addImage( $file->getDBkey() );
			}
		}

		foreach ( $config->buttons as $button ) {
			if ( $button->kind !== ButtonTargetKind::PageLink ) {
				continue;
			}

			// Same normalisation the resolver uses, so a subpage-relative or self-link
			// target registers as the page it actually points at.
			$label = '';
			$target = Linker::normalizeSubpageLink( $page, $button->target, $label );

			if ( $target === '' || str_contains( $target, '://' ) ) {
				continue;
			}

			$link = Title::newFromText( $target );

			if ( $link !== null && $link->getDBkey() !== '' && !$link->isExternal() ) {
				$output->addLink( $link );
			}
		}
	}

	/**
	 * The intro is only read when it can be used: replace mode never falls back to
	 * it, and extracting it is the most expensive thing this extension does.
	 */
	private function intro( string $text, EmbedConfig $config, \MediaWiki\Title\Title $title ): ?\DiscordComponentEmbed\Content\Intro {
		if ( $config->isReplaceMode() || $config->description !== null ) {
			return null;
		}

		return $this->factory->introExtractor( $title )->extract( $text );
	}

	private function mergedDraft( ParserOutput $output ): Draft {
		$records = $output->getExtensionData( DraftRecorder::DRAFT_KEY );

		return Draft::fromRecords( is_array( $records ) ? $records : [] );
	}

	private function isEnabledFor( int $namespace ): bool {
		if ( !$this->config->get( 'DiscordComponentEmbedEnabled' ) ) {
			return false;
		}

		$namespaces = $this->config->get( 'DiscordComponentEmbedNamespaces' );
		$scope = NamespaceScope::fromSetting( $namespaces );

		if ( $scope->isMisconfigured() ) {
			$this->logger->warning(
				'DiscordComponentEmbedNamespaces is neither a list of namespace ids nor null, so previews are published in every namespace.',
				[ 'type' => get_debug_type( $namespaces ) ]
			);
		}

		return $scope->includes( $namespace );
	}

	/**
	 * @param list<string> $problems
	 */
	private function logProblems( string $message, string $page, array $problems ): void {
		$this->logger->warning( $message . ': {problems}', [
			'page' => $page,
			'problems' => implode( ' ', $problems ),
		] );
	}
}
