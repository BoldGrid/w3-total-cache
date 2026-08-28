<?php
/**
 * File: BrowserCache_RuleDirectives.php
 *
 * @package W3TC
 */

namespace W3TC;

/**
 * Class BrowserCache_RuleDirectives
 *
 * Render-time policy for BrowserCache server rules: normalize exception
 * patterns and resolve select-backed security-header directives against
 * an allowlist so Apache and Nginx emitters only write validated values.
 *
 * Write-time stripping for `directive_string` keys lives in Config::set();
 * this class is the paired render-time consumer.
 *
 * @since 2.10.6
 */
class BrowserCache_RuleDirectives {
	/**
	 * Select-backed directive metadata.
	 *
	 * Keys are short directive ids used by callers. Each entry lists the
	 * accepted raw config values (as stored by the admin UI). Callers map
	 * those accepted tokens onto platform-specific output.
	 *
	 * @since 2.10.6
	 *
	 * @var array<string, array{accepted: array<int, string>}>
	 */
	private static $directives = array(
		'hsts'            => array(
			'accepted' => array( 'maxage', 'maxagepre', 'maxageinc', 'maxageincpre' ),
		),
		'xfo'             => array(
			'accepted' => array( 'same', 'deny', 'allow' ),
		),
		'xss'             => array(
			'accepted' => array( '0', '1', 'block' ),
		),
		'referrer_policy' => array(
			'accepted' => array(
				'0',
				'no-referrer',
				'no-referrer-when-downgrade',
				'same-origin',
				'origin',
				'strict-origin',
				'origin-when-cross-origin',
				'strict-origin-when-cross-origin',
				'unsafe-url',
			),
		),
		'pkp_extra'       => array(
			'accepted' => array( 'maxage', 'maxageinc' ),
		),
		'pkp_report_only' => array(
			'accepted' => array( '0', '1' ),
		),
	);

	/**
	 * Returns accepted raw values for a named directive, or null when unknown.
	 *
	 * @since 2.10.6
	 *
	 * @param string $directive Directive id (e.g. `hsts`, `xfo`).
	 *
	 * @return array<int, string>|null
	 */
	public static function accepted_values( $directive ) {
		if ( ! \is_string( $directive ) || ! isset( self::$directives[ $directive ] ) ) {
			return null;
		}

		return self::$directives[ $directive ]['accepted'];
	}

	/**
	 * Resolves a select-backed directive to an accepted token.
	 *
	 * Strips directive metacharacters first, then allowlists. Unsupported
	 * or empty results return null so callers can skip rendering.
	 *
	 * @since 2.10.6
	 *
	 * @param string $directive Directive id.
	 * @param mixed  $value     Raw config value.
	 *
	 * @return string|null Accepted token, or null when invalid.
	 */
	public static function normalize_enum( $directive, $value ) {
		$accepted = self::accepted_values( $directive );
		if ( null === $accepted ) {
			return null;
		}

		$candidate = Util_Rule::sanitize_directive_value(
			\is_string( $value ) ? $value : ''
		);

		if ( '' === $candidate || ! \in_array( $candidate, $accepted, true ) ) {
			return null;
		}

		return $candidate;
	}

	/**
	 * Normalizes no404wp exception patterns for rule rendering.
	 *
	 * Sanitizes each entry, drops empties/non-strings, de-duplicates, and
	 * sorts so equivalent inputs produce identical Apache/Nginx output.
	 *
	 * @since 2.10.6
	 *
	 * @param mixed $exceptions Raw `browsercache.no404wp.exceptions` value.
	 *
	 * @return array<int, string>
	 */
	public static function normalize_exceptions( $exceptions ) {
		$out = Util_Rule::sanitize_directive_values( $exceptions );
		$out = \array_values( \array_unique( $out ) );
		\sort( $out, SORT_STRING );

		return $out;
	}
}
