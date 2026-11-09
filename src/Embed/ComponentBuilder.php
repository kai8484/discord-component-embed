<?php

declare( strict_types=1 );

namespace DiscordComponentEmbed\Embed;

use DiscordComponentEmbed\Config\Limits;
use DiscordComponentEmbed\Media\ResolvedMedia;

/**
 * Renders resolved content into Discord's component JSON.
 *
 * The shape is fixed by Discord, not by taste: a Thumbnail is only legal as a
 * Section accessory, a Section needs one to three Text Displays for the accessory
 * to belong to, and every button must be a child of an Action Row. The builder
 * therefore adapts rather than emitting something the API would reject.
 *
 * @license Unlicense
 */
final class ComponentBuilder {

	/**
	 * @return list<array<string, mixed>> The payload's top-level components.
	 */
	public function build( PreviewContent $content ): array {
		if ( $content->isEmpty() ) {
			return [];
		}

		$children = $this->buildChildren( $content );

		if ( $children === [] ) {
			return [];
		}

		$container = [
			'type' => Limits::TYPE_CONTAINER,
			'components' => $children,
		];

		if ( $content->accent !== null ) {
			$container['accent_color'] = $content->accent;
		}

		if ( $content->spoiler ) {
			$container['spoiler'] = true;
		}

		return [ $container ];
	}

	/**
	 * @return list<array<string, mixed>>
	 */
	private function buildChildren( PreviewContent $content ): array {
		$texts = $this->textDisplays( $content );
		$galleryItems = $content->gallery;
		$children = [];

		if ( $content->image !== null && $texts !== [] ) {
			$children[] = $this->section( $texts, $content->image );
		} elseif ( $content->image !== null ) {
			// Nothing to attach the image to, so it becomes ordinary gallery media
			// rather than an illegal bare Thumbnail. The image the author named
			// outranks the last gallery item, which is the one it replaces.
			if ( count( $galleryItems ) >= Limits::MAX_GALLERY_ITEMS ) {
				array_pop( $galleryItems );
			}

			array_unshift( $galleryItems, $content->image );
		} else {
			$children = $texts;
		}

		// The gallery comes before the small print; trimming below finds the gallery by
		// type, not by position, so the layout does not decide what gets dropped.
		if ( $galleryItems !== [] ) {
			$children[] = $this->mediaGallery( $galleryItems );
		}

		if ( $content->footer !== null && $content->footer !== '' ) {
			$children[] = [ 'type' => Limits::TYPE_SEPARATOR, 'divider' => true, 'spacing' => 1 ];
			$children[] = [ 'type' => Limits::TYPE_TEXT_DISPLAY, 'content' => $content->footer ];
		}

		return $this->fitComponentBudget( $children );
	}

	/**
	 * Keeps the payload inside Discord's component cap by dropping gallery items,
	 * which are the least deliberate thing an author asks for. The configuration
	 * limits cannot keep the payload legal on their own: 25 buttons and 10 gallery
	 * items are each allowed, and together they are not.
	 *
	 * @param list<array<string, mixed>> $children
	 * @return list<array<string, mixed>>
	 */
	private function fitComponentBudget( array $children ): array {
		$budget = Limits::MAX_COMPONENTS - 1;

		while ( ComponentCount::of( $children ) > $budget ) {
			$galleryIndex = null;

			foreach ( $children as $index => $child ) {
				if ( ( $child['type'] ?? null ) === Limits::TYPE_MEDIA_GALLERY && ( $child['items'] ?? [] ) !== [] ) {
					$galleryIndex = $index;
				}
			}

			if ( $galleryIndex === null ) {
				break;
			}

			array_pop( $children[$galleryIndex]['items'] );

			if ( $children[$galleryIndex]['items'] === [] ) {
				array_splice( $children, $galleryIndex, 1 );
			}
		}

		return $children;
	}

	/**
	 * @return list<array<string, mixed>>
	 */
	private function textDisplays( PreviewContent $content ): array {
		$displays = [];

		foreach ( [ $content->title, $content->description ] as $text ) {
			if ( $text !== null && $text !== '' ) {
				$displays[] = [
					'type' => Limits::TYPE_TEXT_DISPLAY,
					'content' => $text,
				];
			}
		}

		return $displays;
	}

	/**
	 * @param list<array<string, mixed>> $texts
	 * @return array<string, mixed>
	 */
	private function section( array $texts, ResolvedMedia $image ): array {
		return [
			'type' => Limits::TYPE_SECTION,
			'components' => $texts,
			'accessory' => $this->thumbnail( $image ),
		];
	}

	/**
	 * @return array<string, mixed>
	 */
	private function thumbnail( ResolvedMedia $image ): array {
		return [ 'type' => Limits::TYPE_THUMBNAIL ] + $this->mediaFields( $image );
	}

	/**
	 * @param list<ResolvedMedia> $items
	 * @return array<string, mixed>
	 */
	private function mediaGallery( array $items ): array {
		return [
			'type' => Limits::TYPE_MEDIA_GALLERY,
			'items' => array_map( fn ( ResolvedMedia $item ): array => $this->galleryItem( $item ), $items ),
		];
	}

	/**
	 * @return array<string, mixed>
	 */
	private function galleryItem( ResolvedMedia $item ): array {
		return $this->mediaFields( $item );
	}

	/**
	 * The fields a Thumbnail and a gallery item share.
	 *
	 * @return array<string, mixed>
	 */
	private function mediaFields( ResolvedMedia $item ): array {
		$rendered = [ 'media' => [ 'url' => $item->url ] ];

		if ( $item->description !== null && $item->description !== '' ) {
			$rendered['description'] = $item->description;
		}

		if ( $item->spoiler ) {
			$rendered['spoiler'] = true;
		}

		return $rendered;
	}

}
