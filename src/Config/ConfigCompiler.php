<?php

declare( strict_types=1 );

namespace DiscordComponentEmbed\Config;

/**
 * Turns a merged draft into one configuration, and rejects what Discord would.
 *
 * Nothing here is fatal: an unusable configuration produces problems and an
 * Invalid outcome, so the page keeps its normal rendering and its existing Open
 * Graph tags while the reason goes to the log.
 *
 * @license Unlicense
 */
final class ConfigCompiler {

	private const TRUTHY = [ 'yes', 'true', '1', 'on' ];
	private const FALSY = [ 'no', 'false', '0', 'off' ];

	public function compile( Draft $draft ): CompiledConfig {
		$problems = $draft->problems();
		$values = $draft->values();

		$enabled = $this->readBoolean( $values['enabled'] ?? null, 'enabled', true, $problems );

		if ( $enabled === false ) {
			return CompiledConfig::disabled( $problems );
		}

		$mode = $this->readMode( $values['mode'] ?? null, $problems );
		$accent = $this->readAccent( $values['accent'] ?? null, $problems );
		$spoiler = $this->readBoolean( $values['spoiler'] ?? null, 'spoiler', false, $problems );
		$image = $this->readImage( $values, $problems );
		$gallery = $this->readGallery( $values, $problems );
		$buttons = $this->readButtons( $values, $problems );

		$config = new EmbedConfig(
			title: $values['title'] ?? null,
			description: $values['description'] ?? null,
			image: $image,
			gallery: $gallery,
			buttons: $buttons,
			accent: $accent,
			spoiler: $spoiler,
			mode: $mode,
			enabled: true,
		);

		if ( $problems !== [] ) {
			return CompiledConfig::invalid( $problems );
		}

		return CompiledConfig::success( $config );
	}

	private function readMode( ?string $mode, array &$problems ): string {
		if ( $mode === null ) {
			return Limits::MODE_MERGE;
		}

		$normalized = strtolower( $mode );

		if ( !in_array( $normalized, [ Limits::MODE_MERGE, Limits::MODE_REPLACE ], true ) ) {
			$problems[] = sprintf(
				'Argument "mode" must be "%s" or "%s", got "%s".',
				Limits::MODE_MERGE,
				Limits::MODE_REPLACE,
				$mode
			);

			return Limits::MODE_MERGE;
		}

		return $normalized;
	}

	private function readAccent( ?string $accent, array &$problems ): ?int {
		if ( $accent === null ) {
			return null;
		}

		if ( preg_match( '/^#?([0-9a-fA-F]{6})$/', $accent, $matches ) !== 1 ) {
			$problems[] = sprintf(
				'Argument "accent" must be a hex colour such as #5865F2, got "%s".',
				$accent
			);

			return null;
		}

		return (int)hexdec( $matches[1] );
	}

	private function readBoolean( ?string $value, string $name, bool $default, array &$problems ): bool {
		if ( $value === null ) {
			return $default;
		}

		$normalized = strtolower( $value );

		if ( in_array( $normalized, self::TRUTHY, true ) ) {
			return true;
		}

		if ( in_array( $normalized, self::FALSY, true ) ) {
			return false;
		}

		$problems[] = sprintf(
			'Argument "%s" must be yes or no, got "%s".',
			$name,
			$value
		);

		return $default;
	}

	private function readImage( array $values, array &$problems ): ?MediaSpec {
		$title = $values['image'] ?? null;
		$description = $values['image-description'] ?? null;

		if ( $title === null ) {
			if ( $description !== null ) {
				$problems[] = 'Argument "image-description" needs an "image".';
			}

			return null;
		}

		return new MediaSpec( $title, $description );
	}

	/**
	 * Numbered arguments are indexed from one, and a gap in the sequence is an
	 * error: it is almost always a typo, and silently renumbering would hide it.
	 *
	 * @return list<MediaSpec>
	 */
	private function readGallery( array $values, array &$problems ): array {
		$titles = $this->indicesOf( $values, '/^gallery([1-9][0-9]*)$/D' );
		$captions = $this->indicesOf( $values, '/^gallery([1-9][0-9]*)-description$/D' );
		$spoilers = $this->indicesOf( $values, '/^spoiler([1-9][0-9]*)$/D' );

		foreach ( array_unique( array_merge( $captions, $spoilers ) ) as $index ) {
			if ( !in_array( $index, $titles, true ) ) {
				$problems[] = sprintf(
					'Argument "gallery%d-description" or "spoiler%d" has no matching gallery%d.',
					$index,
					$index,
					$index
				);
			}
		}

		$gallery = [];
		$highest = $titles === [] ? 0 : max( $titles );

		// Bounded before the gap loop, never inside it: `gallery400` and worse can
		// arrive from a typo or a template, and enumerating a million missing
		// arguments is not a diagnostic, it is a denial of service.
		if ( count( $titles ) > Limits::MAX_GALLERY_ITEMS || $highest > Limits::MAX_GALLERY_ITEMS ) {
			$problems[] = sprintf(
				'At most %d gallery items are allowed, got gallery%d.',
				Limits::MAX_GALLERY_ITEMS,
				$highest
			);

			return [];
		}

		for ( $index = 1; $index <= $highest; $index++ ) {
			if ( !in_array( $index, $titles, true ) ) {
				$problems[] = sprintf(
					'Argument "gallery%d" is missing: numbered arguments must run from gallery1 upwards.',
					$index
				);
				continue;
			}

			$gallery[] = new MediaSpec(
				(string)$values[ 'gallery' . $index ],
				$values[ 'gallery' . $index . '-description' ] ?? null,
				$this->readBoolean( $values[ 'spoiler' . $index ] ?? null, 'spoiler' . $index, false, $problems )
			);
		}

		return $gallery;
	}

	/**
	 * @return list<ButtonSpec>
	 */
	private function readButtons( array $values, array &$problems ): array {
		$indices = $this->indicesOf( $values, '/^button([1-9][0-9]*)$/D' );
		$parser = new ButtonSpecParser();
		$buttons = [];
		$highest = $indices === [] ? 0 : max( $indices );

		if ( count( $indices ) > Limits::MAX_BUTTONS || $highest > Limits::MAX_BUTTONS ) {
			$problems[] = sprintf(
				'At most %d buttons are allowed (%d per row, %d rows), got button%d.',
				Limits::MAX_BUTTONS,
				Limits::BUTTONS_PER_ROW,
				Limits::MAX_ACTION_ROWS,
				$highest
			);

			return [];
		}

		for ( $index = 1; $index <= $highest; $index++ ) {
			if ( !in_array( $index, $indices, true ) ) {
				$problems[] = sprintf(
					'Argument "button%d" is missing: numbered arguments must run from button1 upwards.',
					$index
				);
				continue;
			}

			$buttons[] = $parser->parse( (string)$values[ 'button' . $index ] );
		}

		return $buttons;
	}

	/**
	 * @return list<int>
	 */
	private function indicesOf( array $values, string $pattern ): array {
		$indices = [];

		foreach ( array_keys( $values ) as $key ) {
			if ( preg_match( $pattern, (string)$key, $matches ) === 1 ) {
				$indices[] = (int)$matches[1];
			}
		}

		sort( $indices );

		return $indices;
	}
}
