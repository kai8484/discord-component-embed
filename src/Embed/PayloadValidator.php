<?php

declare( strict_types=1 );

namespace DiscordComponentEmbed\Embed;

use DiscordComponentEmbed\Config\Limits;

/**
 * Checks the assembled payload against Discord's published limits.
 *
 * This is the last gate before publication and it exists because Discord rejects
 * an entire payload over a single bad component: publishing something that will be
 * discarded is worse than publishing nothing.
 *
 * Both the component count and every per-value limit live here rather than in the
 * configuration compiler, because some of them (label and URL length) can only be
 * measured once targets have resolved.
 *
 * @license Unlicense
 */
final class PayloadValidator {

	private const CONTAINER_CHILD_TYPES = [
		Limits::TYPE_ACTION_ROW,
		Limits::TYPE_TEXT_DISPLAY,
		Limits::TYPE_SECTION,
		Limits::TYPE_MEDIA_GALLERY,
		Limits::TYPE_SEPARATOR,
	];

	/** @var array<int, string> */
	private const TYPE_NAMES = [
		Limits::TYPE_ACTION_ROW => 'an action row',
		Limits::TYPE_BUTTON => 'a button',
		Limits::TYPE_SECTION => 'a section',
		Limits::TYPE_TEXT_DISPLAY => 'a text display',
		Limits::TYPE_THUMBNAIL => 'a thumbnail',
		Limits::TYPE_MEDIA_GALLERY => 'a media gallery',
		Limits::TYPE_SEPARATOR => 'a separator',
		Limits::TYPE_CONTAINER => 'a container',
	];

	/**
	 * @param list<array<string, mixed>> $components
	 * @return list<string> Human-readable problems; empty means publishable.
	 */
	public function validate( array $components ): array {
		$problems = [];

		if ( $components === [] ) {
			return [];
		}

		if ( count( $components ) !== 1 || ( $components[0]['type'] ?? null ) !== Limits::TYPE_CONTAINER ) {
			return [ 'The payload must be exactly one Container.' ];
		}

		$total = ComponentCount::of( $components );

		if ( $total > Limits::MAX_COMPONENTS ) {
			$problems[] = sprintf(
				'Discord allows at most %d components per message, this payload has %d.',
				Limits::MAX_COMPONENTS,
				$total
			);
		}

		$problems = array_merge( $problems, $this->validateContainer( $components[0] ) );

		return array_values( array_unique( $problems ) );
	}

	/**
	 * @return list<string>
	 */
	private function validateContainer( array $container ): array {
		$problems = [];
		$accent = $container['accent_color'] ?? null;

		if ( $accent !== null && ( !is_int( $accent ) || $accent < Limits::MIN_ACCENT || $accent > Limits::MAX_ACCENT ) ) {
			$problems[] = 'The accent colour must be an RGB value between #000000 and #FFFFFF.';
		}

		$rows = 0;

		foreach ( $container['components'] ?? [] as $child ) {
			$type = $child['type'] ?? null;

			if ( !in_array( $type, self::CONTAINER_CHILD_TYPES, true ) ) {
				$problems[] = sprintf(
					'A Container cannot hold %s.',
					self::TYPE_NAMES[$type] ?? sprintf( 'a component of type %s', is_scalar( $type ) ? (string)$type : 'unknown' )
				);
				continue;
			}

			if ( $type === Limits::TYPE_ACTION_ROW ) {
				$rows++;
				$problems = array_merge( $problems, $this->validateActionRow( $child ) );
			} elseif ( $type === Limits::TYPE_SECTION ) {
				$problems = array_merge( $problems, $this->validateSection( $child ) );
			} elseif ( $type === Limits::TYPE_MEDIA_GALLERY ) {
				$problems = array_merge( $problems, $this->validateGallery( $child ) );
			} elseif ( $type === Limits::TYPE_TEXT_DISPLAY ) {
				$problems = array_merge( $problems, $this->validateTextDisplay( $child ) );
			}
		}

		if ( $rows > Limits::MAX_ACTION_ROWS ) {
			$problems[] = sprintf(
				'Discord allows at most %d action rows, this payload has %d.',
				Limits::MAX_ACTION_ROWS,
				$rows
			);
		}

		return $problems;
	}

	/**
	 * @return list<string>
	 */
	private function validateActionRow( array $row ): array {
		$problems = [];
		$children = $row['components'] ?? [];

		if ( count( $children ) > Limits::BUTTONS_PER_ROW ) {
			$problems[] = sprintf(
				'An action row can contain at most %d buttons, this one has %d.',
				Limits::BUTTONS_PER_ROW,
				count( $children )
			);
		}

		if ( $children === [] ) {
			$problems[] = 'An action row cannot be empty.';
		}

		foreach ( $children as $button ) {
			if ( ( $button['type'] ?? null ) !== Limits::TYPE_BUTTON ) {
				$problems[] = 'An action row may only contain buttons.';
				continue;
			}

			if ( ( $button['style'] ?? null ) !== Limits::BUTTON_STYLE_LINK ) {
				$problems[] = 'Only link buttons are produced; a button with another style was rejected.';
			}

			$problems = array_merge( $problems, $this->validateButton( $button ) );
		}

		return $problems;
	}

	/**
	 * @return list<string>
	 */
	private function validateButton( array $button ): array {
		$problems = [];
		$label = $button['label'] ?? '';
		$url = $button['url'] ?? '';

		if ( !is_string( $label ) || $label === '' ) {
			$problems[] = 'A button needs a label.';
		} elseif ( mb_strlen( $label ) > Limits::MAX_LABEL_CHARS ) {
			$problems[] = sprintf(
				'A button label can be at most %d characters, this one is %d.',
				Limits::MAX_LABEL_CHARS,
				mb_strlen( $label )
			);
		}

		if ( !is_string( $url ) || !HttpUrl::isHttp( $url ) ) {
			$problems[] = sprintf( 'A button url must be an http or https URL, got "%s".', is_string( $url ) ? $url : '' );
		} elseif ( mb_strlen( $url ) > Limits::MAX_URL_CHARS ) {
			$problems[] = sprintf(
				'A button url can be at most %d characters, this one is %d.',
				Limits::MAX_URL_CHARS,
				mb_strlen( $url )
			);
		}

		return $problems;
	}

	/**
	 * @return list<string>
	 */
	private function validateSection( array $section ): array {
		$problems = [];
		$children = $section['components'] ?? [];

		if ( $children === [] || count( $children ) > 3 ) {
			$problems[] = sprintf(
				'A section needs one to three child components, this one has %d.',
				count( $children )
			);
		}

		foreach ( $children as $child ) {
			if ( ( $child['type'] ?? null ) !== Limits::TYPE_TEXT_DISPLAY ) {
				$problems[] = 'A section may only contain text displays.';
			}
		}

		$accessory = $section['accessory'] ?? null;

		if ( $accessory === null ) {
			$problems[] = 'A section needs an accessory.';
		} elseif ( ( $accessory['type'] ?? null ) === Limits::TYPE_THUMBNAIL ) {
			$problems = array_merge( $problems, $this->validateThumbnail( $accessory ) );
		} elseif ( ( $accessory['type'] ?? null ) !== Limits::TYPE_BUTTON ) {
			$problems[] = 'A section accessory must be a button or a thumbnail.';
		}

		return $problems;
	}

	/**
	 * @return list<string>
	 */
	private function validateThumbnail( array $thumbnail ): array {
		$problems = $this->validateMediaUrl( $thumbnail['media'] ?? null, 'A thumbnail' );
		$description = $thumbnail['description'] ?? null;

		$problems = array_merge( $problems, $this->validateMediaDescription( $description ) );

		return $problems;
	}

	/**
	 * @return list<string>
	 */
	private function validateGallery( array $gallery ): array {
		$problems = [];
		$items = $gallery['items'] ?? [];

		if ( count( $items ) < 1 || count( $items ) > Limits::MAX_GALLERY_ITEMS ) {
			$problems[] = sprintf(
				'A media gallery needs between 1 and %d items, this one has %d.',
				Limits::MAX_GALLERY_ITEMS,
				count( $items )
			);
		}

		foreach ( $items as $item ) {
			$problems = array_merge(
				$problems,
				$this->validateMediaUrl( $item['media'] ?? null, 'A media gallery item' )
			);

			$description = $item['description'] ?? null;

			$problems = array_merge( $problems, $this->validateMediaDescription( $description ) );
		}

		return $problems;
	}

	/**
	 * @return list<string>
	 */
	private function validateTextDisplay( array $display ): array {
		$content = $display['content'] ?? null;

		if ( !is_string( $content ) || trim( $content ) === '' ) {
			return [ 'A text display cannot be empty.' ];
		}

		return [];
	}

	/**
	 * @return list<string>
	 */
	private function validateMediaDescription( mixed $description ): array {
		if ( !is_string( $description ) || mb_strlen( $description ) <= Limits::MAX_MEDIA_DESCRIPTION_CHARS ) {
			return [];
		}

		return [ sprintf(
			'A media description can be at most %d characters, this one is %d.',
			Limits::MAX_MEDIA_DESCRIPTION_CHARS,
			mb_strlen( $description )
		) ];
	}

	/**
	 * @return list<string>
	 */
	private function validateMediaUrl( mixed $media, string $what ): array {
		$url = is_array( $media ) ? ( $media['url'] ?? null ) : null;

		if ( !is_string( $url ) || $url === '' ) {
			return [ $what . ' needs a media url.' ];
		}

		if ( !HttpUrl::isHttp( $url ) ) {
			return [ sprintf( '%s must use an http or https url, got "%s".', $what, $url ) ];
		}

		return [];
	}

}
