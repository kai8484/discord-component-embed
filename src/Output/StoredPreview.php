<?php

declare( strict_types=1 );

namespace DiscordComponentEmbed\Output;

use DiscordComponentEmbed\Embed\Preview;
use DiscordComponentEmbed\Media\ResolvedMedia;

/**
 * The finished preview as the parser cache stores it.
 *
 * Deliberately plain arrays: a cached ParserOutput outlives a deployment, and an
 * object whose properties changed shape would fail to unserialise into something
 * usable. An unreadable or older record simply means no preview.
 *
 * @license Unlicense
 */
final class StoredPreview {

	public const KEY = 'DiscordComponentEmbed:preview';
	private const VERSION = 1;

	/**
	 * @param list<array<string, mixed>> $components
	 * @param array{url: string, width: int|null, height: int|null}|null $image
	 */
	private function __construct(
		private readonly array $components,
		private readonly string $title,
		private readonly string $description,
		private readonly ?array $image,
	) {
	}

	public static function create( Preview $preview, array $components ): self {
		$image = null;

		if ( $preview->image !== null ) {
			$image = [
				'url' => $preview->image->url,
				'width' => $preview->image->width,
				'height' => $preview->image->height,
			];
		}

		return new self( $components, $preview->titlePlain, $preview->descriptionPlain(), $image );
	}

	public static function fromArray( mixed $data ): ?self {
		if ( !is_array( $data ) || ( $data['version'] ?? null ) !== self::VERSION ) {
			return null;
		}

		$components = $data['components'] ?? null;

		// An empty list is a preview published for its Open Graph tags only.
		if ( !is_array( $components ) ) {
			return null;
		}

		$image = $data['image'] ?? null;

		return new self(
			array_values( $components ),
			is_string( $data['title'] ?? null ) ? $data['title'] : '',
			is_string( $data['description'] ?? null ) ? $data['description'] : '',
			is_array( $image ) && is_string( $image['url'] ?? null )
				? [
					'url' => $image['url'],
					'width' => is_int( $image['width'] ?? null ) ? $image['width'] : null,
					'height' => is_int( $image['height'] ?? null ) ? $image['height'] : null,
				]
				: null
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	public function toArray(): array {
		return [
			'version' => self::VERSION,
			'components' => $this->components,
			'title' => $this->title,
			'description' => $this->description,
			'image' => $this->image,
		];
	}

	/** @return list<array<string, mixed>> */
	public function components(): array {
		return $this->components;
	}

	public function title(): string {
		return $this->title;
	}

	public function description(): string {
		return $this->description;
	}

	public function image(): ?ResolvedMedia {
		if ( $this->image === null ) {
			return null;
		}

		return new ResolvedMedia( $this->image['url'], null, false, $this->image['width'], $this->image['height'] );
	}
}
