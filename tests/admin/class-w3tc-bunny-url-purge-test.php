<?php
/**
 * Bunny URL purge uses the url query parameter.
 *
 * @package W3TC
 */

use W3TC\Cdn_BunnyCdn_Api;
use W3TC\CdnEngine_Mirror_BunnyCdn;
use W3TC\Cdnfsd_BunnyCdn_Engine;

/**
 * Individual URL purges name the URL where Bunny reads it.
 */
class W3tc_Bunny_Url_Purge_Test extends WP_UnitTestCase {

	/**
	 * Captured HTTP calls.
	 *
	 * @var array
	 */
	private $captured = array();

	/**
	 * Remove the HTTP interceptor.
	 *
	 * @return void
	 */
	public function tear_down() {
		remove_all_filters( 'pre_http_request' );
		parent::tear_down();
	}

	/**
	 * Full-zone purge still uses the pull-zone endpoint.
	 *
	 * @return void
	 */
	public function test_full_purge_uses_pull_zone_endpoint() {
		$this->capture_http();

		$engine = new Cdnfsd_BunnyCdn_Engine(
			array(
				'account_api_key' => 'triage-key',
				'pull_zone_id'    => 42,
			)
		);
		$engine->flush_all();

		$this->assertNotEmpty( $this->captured );
		$this->assertStringContainsString( '/pullzone/42/purgeCache', $this->captured[0]['url'] );
	}

	/**
	 * A page URL is the url query parameter, not a JSON body.
	 *
	 * @return void
	 */
	public function test_url_purge_sends_the_url_as_a_query_parameter() {
		$this->capture_http();

		$engine = new Cdnfsd_BunnyCdn_Engine(
			array(
				'account_api_key' => 'triage-key',
				'pull_zone_id'    => 42,
			)
		);
		$engine->flush_urls( array( 'https://example.com/hello-world/' ) );

		$this->assertNotEmpty( $this->captured );
		$this->assertSame( 'https://example.com/hello-world/', $this->query_url( $this->captured[0]['url'] ) );
		$this->assertTrue( empty( $this->captured[0]['body'] ) );
	}

	/**
	 * The manual purge box and the mirror engine use the same request shape.
	 *
	 * @return void
	 */
	public function test_manual_and_mirror_purges_use_the_query_parameter() {
		$this->capture_http();

		$api = new Cdn_BunnyCdn_Api(
			array(
				'account_api_key' => 'triage-key',
			)
		);
		$api->purge(
			array(
				'url'   => 'https://example.com/asset.js',
				'async' => true,
			)
		);

		$this->assertSame( 'https://example.com/asset.js', $this->query_url( $this->captured[0]['url'] ) );
		$this->assertStringContainsString( 'async=true', $this->captured[0]['url'] );

		$mirror = new CdnEngine_Mirror_BunnyCdn(
			array(
				'account_api_key' => 'triage-key',
				'cdn_hostname'    => 'cdn.example.com',
				'ssl'             => 'enabled',
			)
		);
		$results = array();
		$mirror->purge(
			array(
				array(
					'remote_path' => 'photo.jpg',
				),
			),
			$results
		);

		$this->assertSame( 'https://cdn.example.com/photo.jpg', $this->query_url( $this->captured[1]['url'] ) );
	}

	/**
	 * Record outgoing HTTP and return a successful empty response.
	 *
	 * @return void
	 */
	private function capture_http() {
		$captured = &$this->captured;
		add_filter(
			'pre_http_request',
			static function ( $pre, $args, $url ) use ( &$captured ) {
				unset( $pre );
				$captured[] = array(
					'url'  => $url,
					'body' => isset( $args['body'] ) ? $args['body'] : '',
				);
				return array(
					'headers'  => array(),
					'body'     => '',
					'response' => array(
						'code'    => 200,
						'message' => 'OK',
					),
					'cookies'  => array(),
					'filename' => null,
				);
			},
			10,
			3
		);
	}

	/**
	 * The url query value from a request URL.
	 *
	 * @param string $url Request URL.
	 * @return string|null
	 */
	private function query_url( $url ) {
		$parts = wp_parse_url( $url );
		$query = array();
		if ( ! empty( $parts['query'] ) ) {
			parse_str( $parts['query'], $query );
		}
		return isset( $query['url'] ) ? $query['url'] : null;
	}
}
