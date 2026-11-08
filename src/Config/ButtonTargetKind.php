<?php

declare( strict_types=1 );

namespace DiscordComponentEmbed\Config;

/**
 * Which of the two usable button forms an author wrote.
 *
 * The distinction has to survive parsing: `[[Doorman]]` is a page link and
 * `Doorman` is plain text that MediaWiki renders without a link, so only the
 * former can become a button.
 *
 * @license Unlicense
 */
enum ButtonTargetKind {

	/** A wikitext page link, brackets removed. */
	case PageLink;

	/** A full URL, as written. */
	case Url;

	/** Neither: MediaWiki would render this as plain text. */
	case Unsupported;
}
