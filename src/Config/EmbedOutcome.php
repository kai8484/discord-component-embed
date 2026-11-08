<?php

declare( strict_types=1 );

namespace DiscordComponentEmbed\Config;

/**
 * Whether the page's preview should be published at all.
 *
 * @license Unlicense
 */
enum EmbedOutcome {

	/** Build and publish. */
	case Valid;

	/** The page switched its own preview off: publish nothing, log nothing. */
	case Disabled;

	/** The configuration is unusable: publish nothing, log why. */
	case Invalid;
}
