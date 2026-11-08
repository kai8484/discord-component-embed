<?php

declare( strict_types=1 );

namespace DiscordComponentEmbed\Embed;

use DiscordComponentEmbed\Config\Limits;

/**
 * Groups buttons into Action Rows.
 *
 * Five per row is what Discord allows, and five rows is what it draws, which is
 * where the 25-button cap comes from: packing the legal maximum maximises how many
 * buttons a message can carry, at the cost of a row whose buttons are wide enough
 * to be wrapped by the client.
 *
 * @license Unlicense
 */
final class ActionRowPacker {

	/**
	 * @param list<ResolvedButton> $buttons
	 * @return list<list<ResolvedButton>>
	 */
	public function pack( array $buttons ): array {
		return array_chunk( $buttons, Limits::BUTTONS_PER_ROW );
	}
}
