<?php

declare( strict_types=1 );

namespace DiscordComponentEmbed\Config;

/**
 * Which namespaces get a preview, read from `$wgDiscordComponentEmbedNamespaces`.
 *
 * The setting is documented as a list of namespace IDs, or null for every
 * namespace. A single ID is accepted as shorthand. Anything this class cannot read
 * as an ID — an object, a float, a word — is reported as misconfigured rather than
 * quietly becoming namespace 0 or every namespace at once.
 *
 * An empty list means no namespace at all: it is the only way to switch previews
 * off per namespace without switching them off entirely.
 *
 * @license Unlicense
 */
final class NamespaceScope {

	/**
	 * @param list<int>|null $ids Null means every namespace.
	 */
	private function __construct(
		private readonly ?array $ids,
		private readonly bool $misconfigured,
	) {
	}

	public static function fromSetting( mixed $setting ): self {
		if ( $setting === null ) {
			return new self( null, false );
		}

		$single = self::toId( $setting );

		if ( $single !== null ) {
			return new self( [ $single ], false );
		}

		if ( !is_array( $setting ) ) {
			return new self( null, true );
		}

		$ids = [];

		foreach ( $setting as $value ) {
			$id = self::toId( $value );

			if ( $id === null ) {
				return new self( null, true );
			}

			$ids[] = $id;
		}

		return new self( $ids, false );
	}

	public function includes( int $namespace ): bool {
		return $this->ids === null || in_array( $namespace, $this->ids, true );
	}

	/**
	 * True when the setting held something this class could not read as an ID.
	 */
	public function isMisconfigured(): bool {
		return $this->misconfigured;
	}

	private static function toId( mixed $value ): ?int {
		if ( is_int( $value ) ) {
			return $value;
		}

		// MediaWiki hands these back as ints, but a LocalSettings file has to be
		// written by a human and "0" reads the same as 0.
		if ( is_string( $value ) && preg_match( '/^-?\d+$/', $value ) === 1 ) {
			return (int)$value;
		}

		return null;
	}

}
