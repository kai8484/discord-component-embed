<?php

declare( strict_types=1 );

namespace DiscordComponentEmbed\Json;

/**
 * JSON for a script element that must not be closable from inside.
 *
 * Escaping `<`, `>` and `&` keeps the decoded value byte-identical while making
 * `</script` impossible to express, so a label or a caption cannot break out of the
 * element. Each sequence is a normal JSON string escape, so any JSON parser reads
 * the original characters back.
 *
 * @license Unlicense
 */
final class ScriptSafeJsonEncoder {

	private const ESCAPES = [
		'<' => '\u003C',
		'>' => '\u003E',
		'&' => '\u0026',
		"\u{2028}" => '\u2028',
		"\u{2029}" => '\u2029',
	];

	/**
	 * @param array<mixed> $data
	 */
	public function encode( array $data ): string {
		$json = json_encode(
			$this->scrub( $data ),
			JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
		);

		return strtr( $json, self::ESCAPES );
	}

	/**
	 * Invalid bytes would make json_encode fail outright; replacing them keeps a
	 * broken character from costing the whole page its preview.
	 *
	 * @param array<mixed> $data
	 * @return array<mixed>
	 */
	private function scrub( array $data ): array {
		foreach ( $data as $key => $value ) {
			if ( is_array( $value ) ) {
				$data[$key] = $this->scrub( $value );
			} elseif ( is_string( $value ) ) {
				$data[$key] = mb_scrub( $value, 'UTF-8' );
			}
		}

		return $data;
	}
}
