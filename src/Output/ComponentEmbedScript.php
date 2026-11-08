<?php

declare( strict_types=1 );

namespace DiscordComponentEmbed\Output;

use DiscordComponentEmbed\Json\ScriptSafeJsonEncoder;

/**
 * The head element that carries the payload.
 *
 * One container, in the envelope Discord's link preview reads: the component under
 * the "component" key of the document.
 *
 * @license Unlicense
 */
final class ComponentEmbedScript {

	private const TAG_ID = 'discord:component-embed';

	public function __construct(
		private readonly ScriptSafeJsonEncoder $encoder,
	) {
	}

	/**
	 * @param list<array<string, mixed>> $components
	 * @return string|null Null when there is nothing to publish.
	 */
	public function build( array $components ): ?string {
		if ( $components === [] ) {
			return null;
		}

		$json = $this->encoder->encode( [ 'component' => $components[0] ] );

		return sprintf(
			'<script id="%s" type="application/json">%s</script>',
			self::TAG_ID,
			$json
		);
	}

	public function tagId(): string {
		return self::TAG_ID;
	}
}
