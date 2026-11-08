<?php

declare( strict_types=1 );

namespace DiscordComponentEmbed\Config;

/**
 * The result of compiling every call on one page into a single configuration.
 *
 * @license Unlicense
 */
final class CompiledConfig {

	/**
	 * @param list<string> $problems Deduplicated, author-facing problem descriptions.
	 */
	private function __construct(
		private readonly EmbedOutcome $outcome,
		private readonly EmbedConfig $config,
		private readonly array $problems,
	) {
	}

	public static function success( EmbedConfig $config, array $problems = [] ): self {
		return new self( EmbedOutcome::Valid, $config, array_values( array_unique( $problems ) ) );
	}

	public static function disabled( array $problems = [] ): self {
		return new self(
			EmbedOutcome::Disabled,
			new EmbedConfig( enabled: false ),
			array_values( array_unique( $problems ) )
		);
	}

	public static function invalid( array $problems ): self {
		return new self( EmbedOutcome::Invalid, new EmbedConfig(), array_values( array_unique( $problems ) ) );
	}

	public function outcome(): EmbedOutcome {
		return $this->outcome;
	}

	public function config(): EmbedConfig {
		return $this->config;
	}

	/** @return list<string> */
	public function problems(): array {
		return $this->problems;
	}

}
