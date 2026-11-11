<?php

declare( strict_types=1 );

namespace DiscordComponentEmbed\Content;

use DiscordComponentEmbed\Config\Limits;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMText;

/**
 * Extracts an article's intro from its rendered HTML: the prose before the first
 * heading, with everything a reader cannot see left out.
 *
 * The filtering matters more than the traversal. Real articles open with an
 * infobox, a hatnote, a navbox and reference markers, all of which are paragraphs
 * or contain them; an extractor that walks blindly reports "Born in 1998" as the
 * article's summary. The same furniture also appears *inside* a lead paragraph —
 * collapsed spans, maintenance tags, figure captions — so the filters are applied
 * to descendants as well as ancestors.
 *
 * @license Unlicense
 */
final class IntroExtractor {

	public function __construct(
		private readonly ?LinkBase $linkBase = null,
	) {
	}

	/**
	 * Containers that hold template furniture rather than prose. Paragraphs inside
	 * them are skipped, and a heading inside one does not end the intro.
	 */
	private const NON_PROSE_CLASSES = [
		'infobox',
		'navbox',
		'hatnote',
		'metadata',
		'mbox',
		'ambox',
		'tmbox',
		'ombox',
		'cmbox',
		'messagebox',
		'sidebar',
		'thumb',
		'toc',
		'mw-table-of-contents',
		'vector-toc',
		'gallery',
		'mw-collapsible',
		'mw-collapsed',
		'mw-empty-elt',
		'mw-editsection',
		'noprint',
	];

	private const NON_PROSE_ELEMENTS = [ 'table', 'figure', 'figcaption', 'nav', 'aside', 'blockquote' ];

	/**
	 * Skin chrome. The extension is handed parsed wikitext rather than a whole page,
	 * but a caller that passes a full document must not get the sidebar menu as the
	 * article's intro. Roles rather than <header>/<footer> elements, because those two
	 * can legitimately wrap article prose.
	 */
	private const CHROME_ROLES = [ 'navigation', 'complementary', 'banner', 'contentinfo' ];

	private const SKIPPED_ELEMENTS = [ 'script', 'style', 'img', 'audio', 'video', 'source', 'template' ];

	/**
	 * Footnote markers are visible, but they are not prose, and on a real article
	 * they land in the middle of every second sentence.
	 */
	private const REFERENCE_CLASSES = [ 'reference', 'mw-ref' ];

	/**
	 * Prose that is not wrapped in a paragraph. A lead written as a list is still a
	 * lead, and reporting nothing at all would be worse than reporting it flat.
	 */
	private const PROSE_ITEM_ELEMENTS = [ 'li', 'dt', 'dd' ];

	public function extract( string $html ): Intro {
		// Both happen before the DOM: a JSON or code dump has no paragraphs to find, and
		// a page far longer than the lead does not need its tail parsed at all.
		$html = $this->capped( $html );

		if ( !$this->hasProse( $html ) ) {
			return new Intro( '', '' );
		}

		// The lead ends at the first section heading, and MediaWiki wraps those in a
		// mw-heading div. Parsing just the part above the wrapper is several times
		// cheaper; where the lead continues past a heading inside furniture the slice
		// yields nothing and the whole page is parsed after all.
		$head = $this->beforeFirstSectionHeading( $html );

		if ( $head !== $html ) {
			$intro = $this->read( $head );

			if ( !$intro->isEmpty() ) {
				return $intro;
			}
		}

		return $this->read( $html );
	}

	/**
	 * The page up to its first section heading, or the page itself when it has none.
	 *
	 * Raw <h2> markup in a template is not wrapped, and the table of contents is
	 * furniture, so neither ends the lead; both are why this looks for the wrapper
	 * rather than for the heading tag.
	 */
	private function beforeFirstSectionHeading( string $html ): string {
		$found = preg_match( '/<div\s+class="[^"]*mw-heading[^"]*"[^>]*>/i', $html, $match, PREG_OFFSET_CAPTURE );

		return $found === 1 ? substr( $html, 0, $match[0][1] ) : $html;
	}

	/**
	 * One parsing pass over the given HTML.
	 */
	private function read( string $html ): Intro {
		if ( !$this->hasProse( $html ) ) {
			return new Intro( '', '' );
		}

		$document = $this->load( $html );
		$paragraphs = $this->collectIntroParagraphs( $document );

		$plain = [];
		$markdown = [];

		foreach ( $paragraphs as $paragraph ) {
			$plainText = Whitespace::collapse( $this->render( $paragraph, false ) );
			$markdownText = Whitespace::collapse( $this->render( $paragraph, true ) );

			if ( $plainText !== '' ) {
				$plain[] = $plainText;
			}

			if ( $markdownText !== '' ) {
				$markdown[] = $markdownText;
			}
		}

		return new Intro( implode( ' ', $plain ), implode( ' ', $markdown ) );
	}

	/**
	 * The first part of the page, cut on a tag boundary.
	 *
	 * The intro is at the top of the document, so a tail of patch notes or a table dump
	 * only costs libxml time and memory. Truncating mid-tag would be tolerated by the
	 * parser, but cutting between tags keeps the DOM honest.
	 */
	private function capped( string $html ): string {
		if ( strlen( $html ) <= Limits::MAX_PARSED_HTML_BYTES ) {
			return $html;
		}

		$head = substr( $html, 0, Limits::MAX_PARSED_HTML_BYTES );
		$lastTag = strrpos( $head, '<' );

		return $lastTag === false ? $head : substr( $head, 0, $lastTag );
	}

	/**
	 * A cheap look for the elements the extractor collects, before paying for a DOM.
	 *
	 * Data and code pages render as one <pre> block, and nothing in this extension has
	 * ever called a paragraph out of one.
	 */
	private function hasProse( string $html ): bool {
		foreach ( [ '<p', '<li', '<dt', '<dd' ] as $tag ) {
			if ( stripos( $html, $tag ) !== false ) {
				return true;
			}
		}

		return false;
	}

	private function load( string $html ): DOMDocument {
		$document = new DOMDocument();

		$previous = libxml_use_internal_errors( true );
		$document->loadHTML(
			'<meta charset="utf-8">' . $html,
			LIBXML_NOERROR | LIBXML_NOWARNING | LIBXML_NONET | LIBXML_HTML_NODEFDTD
		);
		libxml_clear_errors();
		libxml_use_internal_errors( $previous );

		return $document;
	}

	/**
	 * @return list<DOMElement>
	 */
	private function collectIntroParagraphs( DOMDocument $document ): array {
		$candidates = [];

		foreach ( $this->elementsInDocumentOrder( $document ) as $element ) {
			$name = strtolower( $element->nodeName );

			if ( $this->isHeading( $name ) ) {
				if ( !$this->isFurniture( $element ) ) {
					break;
				}

				continue;
			}

			$isParagraph = $name === 'p';
			$isProseItem = in_array( $name, self::PROSE_ITEM_ELEMENTS, true );

			if ( !$isParagraph && !$isProseItem ) {
				continue;
			}

			if ( $this->isFurniture( $element ) ) {
				continue;
			}

			// A nested list item's text is already inside its parent's, and reporting
			// it twice would read as a stutter.
			if ( $isProseItem && $this->hasAncestorIn( $element, self::PROSE_ITEM_ELEMENTS ) ) {
				continue;
			}

			$candidates[] = [ 'element' => $element, 'paragraph' => $isParagraph, 'depth' => $this->containerDepth( $element ) ];
		}

		// A list is a fallback, not an invitation. Roster and index pages open with a
		// sentence and then list everything they link to; reading that list as the
		// intro buries the sentence under a wall of names. A lead that is *only* a
		// list, though, would otherwise have no description at all.
		//
		// Paragraphs win when they share the shallowest depth with the list, or when
		// the page opens with one: a template that wraps the opening sentence puts it
		// deeper than the roster after it. A page that opens with a list keeps it, so
		// a card grid of paragraphs further down does not take over a list-only lead.
		if ( $candidates === [] ) {
			return [];
		}

		$paragraphs = array_values( array_filter(
			$candidates,
			static fn ( array $candidate ): bool => $candidate['paragraph']
		) );
		$overallShallowest = min( array_column( $candidates, 'depth' ) );
		$paragraphsLead = $candidates[0]['paragraph'] || array_filter(
			$paragraphs,
			static fn ( array $candidate ): bool => $candidate['depth'] === $overallShallowest
		) !== [];

		$candidates = $paragraphsLead
			? $paragraphs
			: array_values( array_filter(
				$candidates,
				static fn ( array $candidate ): bool => !$candidate['paragraph']
			) );

		// Template grids, card layouts and rosters are built out of nested containers,
		// and their contents are paragraphs too. The article's own prose is the
		// shallowest prose in the region, whatever the wiki wraps its content in, so
		// the depth is taken relative to what this page actually contains rather than
		// assumed.
		$shallowest = min( array_column( $candidates, 'depth' ) );

		return array_values( array_map(
			static fn ( array $candidate ): DOMElement => $candidate['element'],
			array_filter(
				$candidates,
				static fn ( array $candidate ): bool => $candidate['depth'] === $shallowest
			)
		) );
	}

	/**
	 * How deeply nested in layout containers the element is. Compared between
	 * candidates on the same page, never against an assumed structure.
	 */
	private function containerDepth( DOMElement $element ): int {
		$depth = 0;

		for ( $node = $element->parentNode; $node instanceof DOMElement; $node = $node->parentNode ) {
			if ( in_array( strtolower( $node->nodeName ), [ 'div', 'section' ], true ) ) {
				$depth++;
			}
		}

		return $depth;
	}

	/**
	 * @return iterable<DOMElement>
	 */
	private function elementsInDocumentOrder( DOMNode $node ): iterable {
		foreach ( $node->childNodes as $child ) {
			if ( !$child instanceof DOMElement ) {
				continue;
			}

			yield $child;

			yield from $this->elementsInDocumentOrder( $child );
		}
	}

	private function isHeading( string $name ): bool {
		return preg_match( '/^h[1-6]$/', $name ) === 1;
	}

	/**
	 * True when the element sits inside template furniture or is hidden from the
	 * reader, in which case its text is not the article's intro.
	 */
	private function isFurniture( DOMElement $element ): bool {
		for ( $node = $element; $node instanceof DOMElement; $node = $node->parentNode ) {
			if ( $this->isFurnitureElement( $node ) || $this->isHidden( $node ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * The element itself is furniture, whether or not its ancestors are.
	 */
	private function isFurnitureElement( DOMElement $element ): bool {
		if ( in_array( strtolower( $element->nodeName ), self::NON_PROSE_ELEMENTS, true ) ) {
			return true;
		}

		if ( strtolower( $element->getAttribute( 'id' ) ) === 'toc' ) {
			return true;
		}

		if ( in_array( strtolower( $element->getAttribute( 'role' ) ), self::CHROME_ROLES, true ) ) {
			return true;
		}

		foreach ( $this->classesOf( $element ) as $class ) {
			if ( in_array( $class, self::NON_PROSE_CLASSES, true ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * @param list<string> $names
	 */
	private function hasAncestorIn( DOMElement $element, array $names ): bool {
		for ( $node = $element->parentNode; $node instanceof DOMElement; $node = $node->parentNode ) {
			if ( in_array( strtolower( $node->nodeName ), $names, true ) ) {
				return true;
			}
		}

		return false;
	}

	private function isReferenceMarker( DOMElement $element ): bool {
		foreach ( $this->classesOf( $element ) as $class ) {
			if ( in_array( $class, self::REFERENCE_CLASSES, true ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * @return list<string>
	 */
	private function classesOf( DOMElement $element ): array {
		$class = trim( $element->getAttribute( 'class' ) );

		if ( $class === '' ) {
			return [];
		}

		$classes = preg_split( '/\s+/', $class ) ?: [];

		return array_map( 'strtolower', $classes );
	}

	private function isHidden( DOMElement $element ): bool {
		if ( $element->hasAttribute( 'hidden' ) ) {
			return true;
		}

		if ( strtolower( $element->getAttribute( 'aria-hidden' ) ) === 'true' ) {
			return true;
		}

		$style = strtolower( $element->getAttribute( 'style' ) );

		if ( $style === '' ) {
			return false;
		}

		return preg_match( '/display\s*:\s*none/', $style ) === 1
			|| preg_match( '/visibility\s*:\s*hidden/', $style ) === 1;
	}

	/**
	 * Renders one node, optionally as Discord markdown. Nothing here may emit
	 * arbitrary HTML: the result is text for a JSON payload and a meta tag.
	 */
	private function render( DOMNode $node, bool $markdown, bool $inCode = false ): string {
		$out = '';

		foreach ( $node->childNodes as $child ) {
			if ( $child instanceof DOMText ) {
				$out .= $markdown && !$inCode
					? DiscordMarkdown::escapeText( $child->data )
					: $child->data;
				continue;
			}

			if ( !$child instanceof DOMElement ) {
				continue;
			}

			$name = strtolower( $child->nodeName );

			if (
				in_array( $name, self::SKIPPED_ELEMENTS, true )
				|| $this->isHidden( $child )
				|| $this->isReferenceMarker( $child )
				|| $this->isFurnitureElement( $child )
			) {
				continue;
			}

			if ( $name === 'br' ) {
				$out .= ' ';
				continue;
			}

			// Nothing inside code is markdown: escaping it would ship the backslashes,
			// and formatting it would end the span early.
			$inner = $this->render( $child, $markdown, $inCode || $name === 'code' );

			if ( !$markdown || $inCode ) {
				$out .= $inner;
				continue;
			}

			if ( $name === 'a' ) {
				$out .= $this->renderLink( $child, $inner );
				continue;
			}

			if ( trim( $inner ) === '' ) {
				$out .= $inner;
				continue;
			}

			$out .= match ( $name ) {
				'b', 'strong' => '**' . trim( $inner ) . '**',
				'i', 'em' => '*' . trim( $inner ) . '*',
				'code' => $this->codeSpan( $inner ),
				default => $inner,
			};
		}

		return $out;
	}

	private function renderLink( DOMElement $link, string $inner ): string {
		$label = trim( $inner );

		if ( $label === '' ) {
			return '';
		}

		$href = trim( $link->getAttribute( 'href' ) );

		if ( $href === '' ) {
			return $inner;
		}

		// A preview leaves the wiki, so links have to leave it too: parser output
		// keeps internal links relative and the skin expands them at render time.
		if ( $this->linkBase !== null ) {
			$href = $this->linkBase->expand( $href );
		}

		// The label was escaped on the way in, so the comparison has to be against the
		// escaped form of the target: a link whose text is just its own URL stays text.
		if ( $href === '' || $label === DiscordMarkdown::escapeText( $href ) ) {
			return $inner;
		}

		// A preview is not the article: a red link only means "edit this", and a mailto:
		// or irc: target opens something other than a page. Both stay as plain text.
		if ( $this->isRedLink( $link ) || !$this->isWebLink( $href ) ) {
			return $inner;
		}

		return '[' . $label . '](' . DiscordMarkdown::escapeLinkTarget( $href ) . ')';
	}

	/**
	 * A link MediaWiki marked as new has no page behind it: it points at the edit form.
	 */
	private function isRedLink( DOMElement $link ): bool {
		return in_array( 'new', $this->classesOf( $link ), true );
	}

	/**
	 * True for an http(s) URL, and for a target with no scheme at all, which the caller
	 * expands or leaves relative. Anything else (mailto:, irc:, javascript:) is not a
	 * link a card should carry.
	 */
	private function isWebLink( string $href ): bool {
		if ( preg_match( '/^[a-z][a-z0-9+.-]*:/i', $href ) !== 1 ) {
			return true;
		}

		return preg_match( '/^https?:/i', $href ) === 1;
	}

	/**
	 * Inline code ends at the next run of the same number of backticks, and no
	 * delimiter can hold a backtick of its own. Code that contains one is shipped as
	 * escaped prose instead of a span that would break at it.
	 */
	private function codeSpan( string $inner ): string {
		$inner = trim( $inner );

		if ( str_contains( $inner, '`' ) ) {
			return DiscordMarkdown::escapeText( $inner );
		}

		return '`' . $inner . '`';
	}

}
