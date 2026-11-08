<?php

declare( strict_types=1 );

namespace DiscordComponentEmbed\Config;

/**
 * Discord's own numeric limits, plus the ones derived from them.
 *
 * Every number here was checked against the live component reference rather than
 * recalled: "An Action Row can contain up to 5 Buttons", button labels "max 80
 * characters", link button URLs "max 512 characters", a Media Gallery holds "1 to
 * 10 media gallery items", and "Messages allow up to 40 total components".
 *
 * The button cap is not an independent number: it is Buttons-per-row x rows, and
 * it drops the moment either of those does.
 *
 * @license Unlicense
 */
final class Limits {

	public const BUTTONS_PER_ROW = 5;
	public const MAX_ACTION_ROWS = 5;
	public const MAX_BUTTONS = self::BUTTONS_PER_ROW * self::MAX_ACTION_ROWS;
	public const MAX_GALLERY_ITEMS = 10;
	public const MAX_LABEL_CHARS = 80;
	public const MAX_URL_CHARS = 512;
	public const MAX_COMPONENTS = 40;
	public const MAX_MEDIA_DESCRIPTION_CHARS = 1024;

	/** Component type numbers, as published. */
	public const TYPE_ACTION_ROW = 1;
	public const TYPE_BUTTON = 2;
	public const TYPE_SECTION = 9;
	public const TYPE_TEXT_DISPLAY = 10;
	public const TYPE_THUMBNAIL = 11;
	public const TYPE_MEDIA_GALLERY = 12;
	public const TYPE_SEPARATOR = 14;
	public const TYPE_CONTAINER = 17;

	/** A link button opens a URL; it never sends an interaction. */
	public const BUTTON_STYLE_LINK = 5;

	public const MODE_MERGE = 'merge';
	public const MODE_REPLACE = 'replace';

	/** Colour range of a Container accent, 0x000000 to 0xFFFFFF. */
	public const MIN_ACCENT = 0x000000;
	public const MAX_ACCENT = 0xFFFFFF;

	private function __construct() {
	}
}
