<?php

declare( strict_types=1 );

namespace DiscordComponentEmbed\Embed;

/**
 * Counts every component in a payload tree, the container itself included.
 *
 * Discord's cap applies to the whole tree, not to the top level, so both the builder
 * (which trims to fit) and the validator (which rejects what does not) need the same
 * number.
 *
 * @license Unlicense
 */
final class ComponentCount {

	/**
	 * @param list<array<string, mixed>> $components
	 */
	public static function of( array $components ): int {
		$count = 0;

		foreach ( $components as $component ) {
			$count++;

			foreach ( [ 'components', 'items' ] as $key ) {
				$children = $component[$key] ?? [];

				if ( is_array( $children ) && $children !== [] ) {
					$count += self::of( array_values( $children ) );
				}
			}

			if ( isset( $component['accessory'] ) && is_array( $component['accessory'] ) ) {
				$count += self::of( [ $component['accessory'] ] );
			}
		}

		return $count;
	}

}
