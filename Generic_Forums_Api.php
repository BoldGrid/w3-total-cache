<?php
/**
 * File: Generic_Forums_Api.php
 *
 * @package W3TC
 */

namespace W3TC;

/**
 * Class: Generic_Forums_Api
 *
 * Parses BoldGrid forum help-topic responses into a stable client
 * payload. Call sites must not echo `wp_remote_get()` envelopes.
 *
 * @since 2.10.6
 */
class Generic_Forums_Api {
	/**
	 * Transport failure (WP_Error from the HTTP API).
	 *
	 * @since 2.10.6
	 *
	 * @var string
	 */
	const ERROR_TRANSPORT = 'transport';

	/**
	 * Remote HTTP status outside 2xx.
	 *
	 * @since 2.10.6
	 *
	 * @var string
	 */
	const ERROR_HTTP = 'http_error';

	/**
	 * Body was not usable JSON / not a topic list.
	 *
	 * @since 2.10.6
	 *
	 * @var string
	 */
	const ERROR_MALFORMED = 'malformed';

	/**
	 * Maximum topic title length after sanitization.
	 *
	 * @since 2.10.6
	 *
	 * @var int
	 */
	const TITLE_MAX = 300;

	/**
	 * Allowed error codes in the client payload.
	 *
	 * @since 2.10.6
	 *
	 * @return string[]
	 */
	public static function allowed_error_codes() {
		return array(
			self::ERROR_TRANSPORT,
			self::ERROR_HTTP,
			self::ERROR_MALFORMED,
		);
	}

	/**
	 * Keys allowed in the client JSON object.
	 *
	 * @since 2.10.6
	 *
	 * @return string[]
	 */
	public static function allowed_client_keys() {
		return array( 'topics', 'error' );
	}

	/**
	 * Whether `$tag` is a conservative tabId / query segment.
	 *
	 * @since 2.10.6
	 *
	 * @param string $tag Requested help-topic tag.
	 *
	 * @return bool
	 */
	public static function is_valid_tag( $tag ) {
		return is_string( $tag ) && '' !== $tag && (bool) preg_match( '/^[A-Za-z0-9._-]+$/', $tag );
	}

	/**
	 * Fetch and normalize topics for a tag.
	 *
	 * @since 2.10.6
	 *
	 * @param string $tag Help-topic tag.
	 *
	 * @return array{ok:bool,topics:array,code?:string}
	 */
	public static function request( $tag ) {
		if ( ! self::is_valid_tag( $tag ) ) {
			return self::fail( self::ERROR_MALFORMED );
		}

		$response = wp_remote_get(
			W3TC_BOLDGRID_FORUM_API . $tag,
			array(
				'timeout' => 10,
			)
		);

		return self::normalize( $response );
	}

	/**
	 * Normalize a `wp_remote_get()` result, WP_Error, JSON string, or
	 * already-decoded body into the internal result shape.
	 *
	 * @since 2.10.6
	 *
	 * @param mixed $response Remote result or compatibility body.
	 *
	 * @return array{ok:bool,topics:array,code?:string}
	 */
	public static function normalize( $response ) {
		if ( is_wp_error( $response ) ) {
			return self::fail( self::ERROR_TRANSPORT );
		}

		if ( is_string( $response ) ) {
			return self::normalize_body( $response );
		}

		if ( ! is_array( $response ) ) {
			return self::fail( self::ERROR_MALFORMED );
		}

		if ( array_key_exists( 'body', $response ) ) {
			$code = (int) wp_remote_retrieve_response_code( $response );
			if ( $code > 0 && ( $code < 200 || $code >= 300 ) ) {
				return self::fail( self::ERROR_HTTP );
			}

			return self::normalize_body( $response['body'] );
		}

		if ( isset( $response['topics'] ) ) {
			return self::ok( self::sanitize_topics( $response['topics'] ) );
		}

		if ( self::looks_like_topic_list( $response ) ) {
			return self::ok( self::sanitize_topics( $response ) );
		}

		return self::normalize_decoded( $response );
	}

	/**
	 * Client JSON: `{ topics: [...] }` on success, plus `error` on failure.
	 *
	 * Never includes HTTP headers, cookies, raw bodies, or transport
	 * messages.
	 *
	 * @since 2.10.6
	 *
	 * @param array $result Internal result from {@see normalize()}.
	 *
	 * @return array{topics:array,error?:string}
	 */
	public static function client_payload( array $result ) {
		$payload = array(
			'topics' => isset( $result['topics'] ) && is_array( $result['topics'] )
				? $result['topics']
				: array(),
		);

		if ( empty( $result['ok'] ) ) {
			$code = isset( $result['code'] ) ? (string) $result['code'] : self::ERROR_TRANSPORT;
			if ( ! in_array( $code, self::allowed_error_codes(), true ) ) {
				$code = self::ERROR_TRANSPORT;
			}
			$payload['error'] = $code;
		}

		return $payload;
	}

	/**
	 * Emit the client payload as JSON and terminate.
	 *
	 * @since 2.10.6
	 *
	 * @param array $result Internal result from {@see normalize()}.
	 *
	 * @return void
	 */
	public static function send( array $result ) {
		wp_send_json( self::client_payload( $result ) );
	}

	/**
	 * Parse a response body (string, array, or empty).
	 *
	 * @since 2.10.6
	 *
	 * @param mixed $body Raw body.
	 *
	 * @return array{ok:bool,topics:array,code?:string}
	 */
	public static function normalize_body( $body ) {
		if ( is_array( $body ) ) {
			return self::normalize_decoded( $body );
		}

		if ( null === $body ) {
			return self::ok( array() );
		}

		if ( ! is_string( $body ) ) {
			return self::fail( self::ERROR_MALFORMED );
		}

		$trimmed = trim( $body );
		if ( '' === $trimmed ) {
			return self::ok( array() );
		}

		$decoded = json_decode( $trimmed, true );
		if ( JSON_ERROR_NONE !== json_last_error() ) {
			return self::fail( self::ERROR_MALFORMED );
		}

		return self::normalize_decoded( $decoded );
	}

	/**
	 * Map a decoded JSON value to topics.
	 *
	 * Compatibility:
	 * - list of `{ title, link }` objects (current API)
	 * - `{ topics: [...] }` wrapper
	 * - `{ data: [...] }` REST-style wrapper when `data` looks like topics
	 * - a single topic object
	 * - empty array / null / `{}` → empty topic list
	 *
	 * @since 2.10.6
	 *
	 * @param mixed $decoded json_decode result.
	 *
	 * @return array{ok:bool,topics:array,code?:string}
	 */
	public static function normalize_decoded( $decoded ) {
		if ( null === $decoded ) {
			return self::ok( array() );
		}

		if ( is_array( $decoded ) && isset( $decoded['topics'] ) && is_array( $decoded['topics'] ) ) {
			return self::ok( self::sanitize_topics( $decoded['topics'] ) );
		}

		if ( is_array( $decoded ) && isset( $decoded['data'] ) && self::looks_like_topic_list( $decoded['data'] ) ) {
			return self::ok( self::sanitize_topics( $decoded['data'] ) );
		}

		if ( self::looks_like_topic_list( $decoded ) ) {
			return self::ok( self::sanitize_topics( $decoded ) );
		}

		if ( is_array( $decoded ) && ( isset( $decoded['title'] ) || isset( $decoded['link'] ) ) ) {
			return self::ok( self::sanitize_topics( array( $decoded ) ) );
		}

		if ( is_array( $decoded ) && array() === $decoded ) {
			return self::ok( array() );
		}

		return self::fail( self::ERROR_MALFORMED );
	}

	/**
	 * Keep title + http(s) link only.
	 *
	 * @since 2.10.6
	 *
	 * @param mixed $topics Candidate topic list.
	 *
	 * @return array<int,array{title:string,link:string}>
	 */
	public static function sanitize_topics( $topics ) {
		if ( ! is_array( $topics ) ) {
			return array();
		}

		$out = array();
		foreach ( $topics as $topic ) {
			if ( ! is_array( $topic ) ) {
				continue;
			}

			$link = isset( $topic['link'] ) ? $topic['link'] : '';
			if ( ! is_string( $link ) && ! is_numeric( $link ) ) {
				continue;
			}

			$link = esc_url_raw( (string) $link, array( 'http', 'https' ) );
			if ( '' === $link ) {
				continue;
			}

			$title = isset( $topic['title'] ) ? $topic['title'] : '';
			if ( ! is_string( $title ) && ! is_numeric( $title ) ) {
				$title = '';
			}

			$title = html_entity_decode( wp_strip_all_tags( (string) $title ), ENT_QUOTES, 'UTF-8' );
			if ( strlen( $title ) > self::TITLE_MAX ) {
				$title = substr( $title, 0, self::TITLE_MAX );
			}

			$out[] = array(
				'title' => $title,
				'link'  => $link,
			);
		}

		return array_values( $out );
	}

	/**
	 * Whether a value looks like a list of topic objects.
	 *
	 * @since 2.10.6
	 *
	 * @param mixed $value Candidate list.
	 *
	 * @return bool
	 */
	public static function looks_like_topic_list( $value ) {
		if ( ! is_array( $value ) ) {
			return false;
		}

		if ( array() === $value ) {
			return true;
		}

		if ( ! array_key_exists( 0, $value ) ) {
			return false;
		}

		$first = $value[0];

		return is_array( $first ) && ( isset( $first['title'] ) || isset( $first['link'] ) );
	}

	/**
	 * Successful internal result.
	 *
	 * @since 2.10.6
	 *
	 * @param array $topics Sanitized topics.
	 *
	 * @return array{ok:bool,topics:array}
	 */
	private static function ok( array $topics ) {
		return array(
			'ok'     => true,
			'topics' => $topics,
		);
	}

	/**
	 * Failed internal result.
	 *
	 * @since 2.10.6
	 *
	 * @param string $code Error code.
	 *
	 * @return array{ok:bool,topics:array,code:string}
	 */
	private static function fail( $code ) {
		return array(
			'ok'     => false,
			'topics' => array(),
			'code'   => $code,
		);
	}
}
