<?php
/**
 * File: class-w3tc-license-jwt-harness.php
 *
 * Shared HTTP, token, and state helpers for the license JWT tests.
 *
 * @package    W3TC
 * @subpackage W3TC/tests/admin
 * @since      2.11.0
 */

declare( strict_types = 1 );

use W3TC\Dispatcher;

/**
 * Class: W3tc_License_Jwt_Harness
 *
 * Abstract so PHPUnit does not run it directly.
 *
 * @since 2.11.0
 */
abstract class W3tc_License_Jwt_Harness extends WP_UnitTestCase {

	/**
	 * Fake license key. Not a real secret.
	 *
	 * @var string
	 */
	const FAKE_KEY = 'fakeLicenseKey0000000000000000AA';

	/**
	 * Refresh lock network option.
	 *
	 * @var string
	 */
	const LOCK_OPTION = 'w3tc_license_jwt_refresh_lock';

	/**
	 * Captured HTTP requests: url, args, method, body.
	 *
	 * @var array
	 */
	protected $requests = array();

	/**
	 * Original Config values touched by set_config().
	 *
	 * @var array
	 */
	private $config_backup = array();

	/**
	 * Set up.
	 *
	 * @return void
	 */
	public function setUp(): void {
		parent::setUp();
		$tmp = WP_CONTENT_DIR . '/cache/tmp';
		if ( ! is_dir( $tmp ) ) {
			@mkdir( $tmp, 0755, true ); // phpcs:ignore
		}
		$this->requests = array();
		delete_site_option( self::LOCK_OPTION );
		delete_transient( 'w3tc_imageservice_usage' );
		delete_transient( 'w3tc_imageservice_limited' );
		$this->seed_master_state( array(), array( 'license.jwt' ) );
	}

	/**
	 * Tear down.
	 *
	 * @return void
	 */
	public function tearDown(): void {
		remove_all_filters( 'pre_http_request' );
		$this->restore_config();
		$this->seed_master_state( array(), array( 'license.jwt' ) );
		delete_site_option( self::LOCK_OPTION );
		$_GET     = array();
		$_REQUEST = array();
		parent::tearDown();
	}

	/**
	 * Intercept all HTTP calls. The responder gets ( $url, $args ) and
	 * returns a WP HTTP response array.
	 *
	 * @param callable $responder Responder.
	 *
	 * @return void
	 */
	protected function install_http( callable $responder ): void {
		add_filter(
			'pre_http_request',
			function ( $pre, $args, $url ) use ( $responder ) {
				$this->requests[] = array(
					'url'    => (string) $url,
					'args'   => $args,
					'method' => strtoupper( isset( $args['method'] ) ? (string) $args['method'] : 'GET' ),
					'body'   => $this->body_string( $args ),
				);
				return $responder( (string) $url, $args );
			},
			10,
			3
		);
	}

	/**
	 * Build a WP HTTP response array.
	 *
	 * @param int          $code HTTP status.
	 * @param array|string $body Body; arrays are JSON-encoded.
	 *
	 * @return array
	 */
	protected function response( int $code, $body ): array {
		return array(
			'headers'  => array(),
			'body'     => is_array( $body ) ? wp_json_encode( $body ) : (string) $body,
			'response' => array(
				'code'    => $code,
				'message' => (string) $code,
			),
			'cookies'  => array(),
			'filename' => null,
		);
	}

	/**
	 * Base64url encode.
	 *
	 * @param string $value Value.
	 *
	 * @return string
	 */
	protected function b64url( string $value ): string {
		return rtrim( strtr( base64_encode( $value ), '+/', '-_' ), '=' ); // phpcs:ignore
	}

	/**
	 * Fake three-segment token with a JSON object payload.
	 *
	 * @param array $payload Public claims.
	 *
	 * @return string
	 */
	protected function make_token( array $payload ): string {
		return $this->b64url( wp_json_encode( array( 'alg' => 'HS256', 'typ' => 'JWT' ) ) ) . '.' .
			$this->b64url( wp_json_encode( $payload ) ) . '.' .
			$this->b64url( 'fake-signature-' . wp_rand() );
	}

	/**
	 * Token that is valid for an hour.
	 *
	 * @return string
	 */
	protected function fresh_token(): string {
		$exp = time() + 3600;
		return $this->make_token(
			array(
				'license_status' => 'active.by_rooturi',
				'expires_at'     => gmdate( 'c', $exp ),
				'exp'            => $exp,
				'private'        => 'opaque',
			)
		);
	}

	/**
	 * Successful licensing body with a token.
	 *
	 * @param string $token Token.
	 *
	 * @return array
	 */
	protected function license_body( string $token ): array {
		return array(
			'license'        => 'valid',
			'license_status' => 'active.by_rooturi',
			'license_terms'  => 'accept',
			'token'          => $token,
		);
	}

	/**
	 * Mutate the master ConfigState singleton and the raw w3tc_state option.
	 *
	 * @param array $set   Keys to set.
	 * @param array $unset Keys to remove.
	 *
	 * @return void
	 */
	protected function seed_master_state( array $set, array $unset = array() ): void {
		$state = Dispatcher::config_state_master();
		$state->get( 'common.install', 0 );
		$ref  = new \ReflectionObject( $state );
		$prop = $ref->getProperty( '_data' );
		$prop->setAccessible( true );
		$data = $prop->getValue( $state );
		if ( ! is_array( $data ) ) {
			$data = array();
		}
		foreach ( $unset as $k ) {
			unset( $data[ $k ] );
		}
		foreach ( $set as $k => $v ) {
			$data[ $k ] = $v;
		}
		$prop->setValue( $state, $data );

		$encoded = wp_json_encode( $data );
		update_site_option( 'w3tc_state', $encoded );
		$saved = $ref->getProperty( '_saved_encoded' );
		$saved->setAccessible( true );
		$saved->setValue( $state, $encoded );
	}

	/**
	 * Mutate the blog ConfigState singleton without saving.
	 *
	 * @param array $pairs Keys to set.
	 *
	 * @return void
	 */
	protected function set_blog_state( array $pairs ): void {
		$state = Dispatcher::config_state();
		$state->get( 'common.install', 0 );
		$ref  = new \ReflectionObject( $state );
		$prop = $ref->getProperty( '_data' );
		$prop->setAccessible( true );
		$data = $prop->getValue( $state );
		if ( ! is_array( $data ) ) {
			$data = array();
		}
		foreach ( $pairs as $k => $v ) {
			$data[ $k ] = $v;
		}
		$prop->setValue( $state, $data );
	}

	/**
	 * Raw license.jwt from the w3tc_state site option.
	 *
	 * @return string
	 */
	protected function raw_jwt(): string {
		$data = json_decode( (string) get_site_option( 'w3tc_state' ), true );
		return is_array( $data ) && isset( $data['license.jwt'] ) ? (string) $data['license.jwt'] : '';
	}

	/**
	 * Write license.jwt directly into the raw w3tc_state option only.
	 *
	 * @param string $token Token.
	 *
	 * @return void
	 */
	protected function write_raw_jwt( string $token ): void {
		$data = json_decode( (string) get_site_option( 'w3tc_state' ), true );
		if ( ! is_array( $data ) ) {
			$data = array();
		}
		$data['license.jwt'] = $token;
		update_site_option( 'w3tc_state', wp_json_encode( $data ) );
	}

	/**
	 * Mutate the Dispatcher Config singleton without saving.
	 *
	 * @param array $pairs Key => value.
	 *
	 * @return void
	 */
	protected function set_config( array $pairs ): void {
		$config = Dispatcher::config();
		$ref    = new \ReflectionObject( $config );
		$prop   = $ref->getProperty( '_data' );
		$prop->setAccessible( true );
		$data = $prop->getValue( $config );
		foreach ( $pairs as $k => $v ) {
			if ( ! array_key_exists( $k, $this->config_backup ) ) {
				$this->config_backup[ $k ] = array_key_exists( $k, $data ) ? array( $data[ $k ] ) : null;
			}
			$data[ $k ] = $v;
		}
		$prop->setValue( $config, $data );
	}

	/**
	 * Restore Config values touched by set_config().
	 *
	 * @return void
	 */
	private function restore_config(): void {
		if ( empty( $this->config_backup ) ) {
			return;
		}
		$config = Dispatcher::config();
		$ref    = new \ReflectionObject( $config );
		$prop   = $ref->getProperty( '_data' );
		$prop->setAccessible( true );
		$data = $prop->getValue( $config );
		foreach ( $this->config_backup as $k => $orig ) {
			if ( null === $orig ) {
				unset( $data[ $k ] );
			} else {
				$data[ $k ] = $orig[0];
			}
		}
		$prop->setValue( $config, $data );
		$this->config_backup = array();
	}

	/**
	 * Request body as a string.
	 *
	 * @param array $args Request args.
	 *
	 * @return string
	 */
	protected function body_string( $args ): string {
		if ( ! isset( $args['body'] ) ) {
			return '';
		}
		return is_array( $args['body'] ) ? http_build_query( $args['body'] ) : (string) $args['body'];
	}

	/**
	 * Case-insensitive request header.
	 *
	 * @param array  $args Request args.
	 * @param string $name Header name.
	 *
	 * @return string|null
	 */
	protected function header( $args, string $name ) {
		if ( empty( $args['headers'] ) || ! is_array( $args['headers'] ) ) {
			return null;
		}
		foreach ( $args['headers'] as $k => $v ) {
			if ( 0 === strcasecmp( (string) $k, $name ) ) {
				return (string) $v;
			}
		}
		return null;
	}

	/**
	 * Classify a captured or live request.
	 *
	 * @param string $url  URL.
	 * @param string $body Body string.
	 *
	 * @return string
	 */
	protected function kind( string $url, string $body = '' ): string {
		$hay = $url . ' ' . $body;
		foreach ( array( 'activate_license', 'check_license', 'deactivate_license', 'reset_rooturi' ) as $action ) {
			if ( false !== strpos( $hay, 'edd_action=' . $action ) || false !== strpos( $hay, '"edd_action":"' . $action . '"' ) ) {
				return $action;
			}
		}
		$map = array(
			'/v2/refresh'     => 'refresh',
			'/image/convert'  => 'convert',
			'/image/usage'    => 'usage',
			'/job/status'     => 'status',
			'/image/download' => 'download',
		);
		foreach ( $map as $needle => $kind ) {
			if ( false !== strpos( $url, $needle ) ) {
				return $kind;
			}
		}
		return 'other';
	}

	/**
	 * Captured requests of a kind.
	 *
	 * @param string $kind Kind.
	 *
	 * @return array
	 */
	protected function requests_of( string $kind ): array {
		return array_values(
			array_filter(
				$this->requests,
				function ( $r ) use ( $kind ) {
					return $this->kind( $r['url'], $r['body'] ) === $kind;
				}
			)
		);
	}

	/**
	 * Ordered kinds of captured requests.
	 *
	 * @return array
	 */
	protected function kinds(): array {
		return array_map(
			function ( $r ) {
				return $this->kind( $r['url'], $r['body'] );
			},
			$this->requests
		);
	}
}
