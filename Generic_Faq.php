<?php
/**
 * File: Generic_Faq.php
 *
 * @package W3TC
 */

namespace W3TC;

/**
 * Class: Generic_Faq
 */
class Generic_Faq {
	/**
	 * Return FAQ section URLs.
	 *
	 * @return array
	 */
	public static function sections(): array {
		return array(
			'General'            => 'https://api.w3-edge.com/v1/faq/general',
			'Usage'              => 'https://api.w3-edge.com/v1/faq/usage',
			'Compatibility'      => 'https://api.w3-edge.com/v1/faq/compatibility',
			'Minification'       => 'https://api.w3-edge.com/v1/faq/minification',
			'CDN'                => 'https://api.w3-edge.com/v1/faq/cdn',
			'Browser Cache'      => 'https://api.w3-edge.com/v1/faq/browser-cache',
			'Errors / Debugging' => 'https://api.w3-edge.com/v1/faq/errors-debugging',
			'Requirements'       => 'https://api.w3-edge.com/v1/faq/requirements',
			'Developers'         => 'https://api.w3-edge.com/v1/faq/developers',
			'Extensions'         => 'https://api.w3-edge.com/v1/faq/extensions',
			'Installation'       => 'https://api.w3-edge.com/v1/faq/installation',
		);
	}

	/**
	 * Returns list of questions for section.
	 *
	 * @static
	 *
	 * @see self::sections()
	 *
	 * @param string $section Section.
	 * @return array|null
	 */
	public static function parse( string $section ): ?array {
		$sections = self::sections();

		if ( ! isset( $sections[ $section ] ) ) {
			return null;
		}

		$url      = $sections[ $section ];
		$response = wp_remote_get( $url );

		if ( is_wp_error( $response ) ) {
			return null;
		}

		$questions = array();
		$regexes   = array(
			'~<h1[^>]*>(.*?)</h1>.*?<a[^>]*href="(#[^"]+)"~mi',
			'~<li>.*?<a[^>]*href="/BoldGrid/w3-total-cache/wiki/FAQ([^"]+)"[^>]*>(.*?)</a>.*?</li>~mi',
		);

		foreach ( $regexes as $i => $regex ) {
			preg_match_all( $regex, $response['body'], $m );

			if ( is_array( $m ) && count( $m ) > 1 ) {
				$c = count( $m[1] );

				for ( $n = 0; $n < $c; $n++ ) {
					if ( 0 === $i ) {
						// Index 0 has the question first then the URL fragment.
						$questions[] = array(
							'q' => $m[1][ $n ],
							'a' => $url . $m[2][ $n ],
						);
					} else {
						// Index 1 has the URL fragment first then the name.  Just use the original URL.
						$questions[] = array(
							'q' => $m[2][ $n ],
							'a' => $url,
						);
					}
				}
			}
		}

		return $questions;
	}
}
