<?php
/**
 * File: class-w3tc-util-url-test.php
 *
 * @package    W3TC
 * @subpackage W3TC/tests/admin
 * @since      2.10.0
 */

declare( strict_types = 1 );

use W3TC\Util_Http;
use W3TC\Util_Url;

/**
 * Class: W3tc_Util_Url_Test
 *
 * Coverage for the outbound-URL guard. Regressions here re-enable
 * outbound fetches to admin-supplied URLs that resolve to internal
 * ranges for every site routed through `Util_Url::is_public_host()`,
 * so the negative coverage is exhaustive across the four families of
 * private-range address (loopback, RFC1918, link-local, IPv4-mapped
 * IPv6).
 *
 * @since 2.10.0
 */
class W3tc_Util_Url_Test extends WP_UnitTestCase {

	/**
	 * Bare IP literals — no DNS lookup needed — must be refused for
	 * every private / loopback / link-local / reserved range.
	 *
	 * @since 2.10.0
	 */
	public function test_is_public_host_refuses_private_ipv4_literals() {
		$inputs = array(
			'loopback /8'      => 'http://127.0.0.1/',
			'loopback alt'     => 'http://127.255.255.254/',
			'this-network'     => 'http://0.0.0.0/',
			'rfc1918 /8'       => 'http://10.0.0.1/',
			'rfc1918 /12'      => 'http://172.16.0.1/',
			'rfc1918 /12 mid'  => 'http://172.31.255.254/',
			'rfc1918 /16'      => 'http://192.168.0.1/',
			'aws metadata'     => 'http://169.254.169.254/',
			'link-local /16'   => 'http://169.254.1.1/',
		);

		foreach ( $inputs as $label => $url ) {
			$this->assertFalse(
				Util_Url::is_public_host( $url ),
				"Expected refusal for [$label] $url"
			);
		}
	}

	/**
	 * Same for IPv6: loopback (`::1`), link-local (`fe80::/10`),
	 * unique-local (`fc00::/7`), and the IPv4-mapped form
	 * (`::ffff:127.0.0.1`) that would otherwise sneak past a v6 check.
	 *
	 * @since 2.10.0
	 */
	public function test_is_public_host_refuses_private_ipv6_literals() {
		$inputs = array(
			'ipv6 loopback'      => 'http://[::1]/',
			'ipv6 link-local'    => 'http://[fe80::1]/',
			'ipv6 unique-local'  => 'http://[fc00::1]/',
			'ipv4-mapped lo'     => 'http://[::ffff:127.0.0.1]/',
			'ipv4-mapped priv'   => 'http://[::ffff:10.0.0.1]/',
			'ipv4-mapped aws'    => 'http://[::ffff:169.254.169.254]/',
		);

		foreach ( $inputs as $label => $url ) {
			$this->assertFalse(
				Util_Url::is_public_host( $url ),
				"Expected refusal for [$label] $url"
			);
		}
	}

	/**
	 * A host that resolves to a loopback address must be refused even
	 * when expressed by name. The literal-IP fast-path doesn't run for
	 * names; this exercises the gethostbynamel() branch.
	 *
	 * @since 2.10.0
	 */
	public function test_is_public_host_refuses_loopback_named_host() {
		// `localhost` resolves to 127.0.0.1 on every supported host.
		$this->assertFalse( Util_Url::is_public_host( 'http://localhost/' ) );
	}

	/**
	 * Non-http(s) schemes are always refused — `file://`, `gopher://`,
	 * `data://`, etc. each open out-of-band fetch paths and have no
	 * place in a `wp_remote_*` call.
	 *
	 * @since 2.10.0
	 */
	public function test_is_public_host_refuses_non_http_schemes() {
		$inputs = array(
			'file:///etc/passwd',
			'gopher://evil.example/',
			'ftp://example.com/',
			'data:,evil',
			'php://input',
		);
		foreach ( $inputs as $url ) {
			$this->assertFalse(
				Util_Url::is_public_host( $url ),
				"Expected refusal for non-http scheme: $url"
			);
		}
	}

	/**
	 * Malformed / empty / non-string inputs are refused without
	 * tripping a warning.
	 *
	 * @since 2.10.0
	 */
	public function test_is_public_host_refuses_malformed_input() {
		$this->assertFalse( Util_Url::is_public_host( '' ) );
		$this->assertFalse( Util_Url::is_public_host( null ) );
		$this->assertFalse( Util_Url::is_public_host( 'not-a-url' ) );
		$this->assertFalse( Util_Url::is_public_host( 'https:' ) );
		$this->assertFalse( Util_Url::is_public_host( array( 'http://example.com/' ) ) );
		$this->assertFalse( Util_Url::is_public_host( 42 ) );
	}

	/**
	 * `is_valid_http_scheme()` accepts http and https only.
	 *
	 * @since 2.10.0
	 */
	public function test_is_valid_http_scheme_accepts_http_and_https() {
		$this->assertTrue( Util_Url::is_valid_http_scheme( 'http://example.com/' ) );
		$this->assertTrue( Util_Url::is_valid_http_scheme( 'https://example.com/' ) );

		$this->assertFalse( Util_Url::is_valid_http_scheme( 'file:///etc/passwd' ) );
		$this->assertFalse( Util_Url::is_valid_http_scheme( 'gopher://evil.example/' ) );
		$this->assertFalse( Util_Url::is_valid_http_scheme( 'ftp://example.com/' ) );
		$this->assertFalse( Util_Url::is_valid_http_scheme( '' ) );
		$this->assertFalse( Util_Url::is_valid_http_scheme( null ) );
	}

	/**
	 * `is_public_ip()` short-cuts the literal-IP check used by both
	 * the literal-IP fast-path in `is_public_host()` and the
	 * post-DNS-lookup loop.
	 *
	 * @since 2.10.0
	 */
	public function test_is_public_ip_classifies_ranges_correctly() {
		// Known-public IPs (Google DNS, Cloudflare DNS).
		$this->assertTrue( Util_Url::is_public_ip( '8.8.8.8' ) );
		$this->assertTrue( Util_Url::is_public_ip( '1.1.1.1' ) );

		// Private + reserved.
		$this->assertFalse( Util_Url::is_public_ip( '127.0.0.1' ) );
		$this->assertFalse( Util_Url::is_public_ip( '10.0.0.1' ) );
		$this->assertFalse( Util_Url::is_public_ip( '172.16.0.1' ) );
		$this->assertFalse( Util_Url::is_public_ip( '192.168.0.1' ) );
		$this->assertFalse( Util_Url::is_public_ip( '169.254.169.254' ) );
		$this->assertFalse( Util_Url::is_public_ip( '0.0.0.0' ) );

		// IPv6.
		$this->assertFalse( Util_Url::is_public_ip( '::1' ) );
		$this->assertFalse( Util_Url::is_public_ip( 'fe80::1' ) );
		$this->assertFalse( Util_Url::is_public_ip( '::ffff:127.0.0.1' ) );
		$this->assertFalse( Util_Url::is_public_ip( '::ffff:10.0.0.1' ) );

		// Malformed.
		$this->assertFalse( Util_Url::is_public_ip( '' ) );
		$this->assertFalse( Util_Url::is_public_ip( 'not an ip' ) );
	}

	/**
	 * Loopback classification for the Varnish PURGE exception path.
	 *
	 * @since 2.10.2
	 */
	public function test_is_loopback_ip_classifies_ranges_correctly() {
		$this->assertTrue( Util_Url::is_loopback_ip( '127.0.0.1' ) );
		$this->assertTrue( Util_Url::is_loopback_ip( '127.255.255.254' ) );
		$this->assertTrue( Util_Url::is_loopback_ip( '::1' ) );
		$this->assertTrue( Util_Url::is_loopback_ip( '::ffff:127.0.0.1' ) );

		$this->assertFalse( Util_Url::is_loopback_ip( '10.0.0.1' ) );
		$this->assertFalse( Util_Url::is_loopback_ip( '192.168.0.1' ) );
		$this->assertFalse( Util_Url::is_loopback_ip( '169.254.169.254' ) );
		$this->assertFalse( Util_Url::is_loopback_ip( '8.8.8.8' ) );
		$this->assertFalse( Util_Url::is_loopback_ip( '::ffff:10.0.0.1' ) );
		$this->assertFalse( Util_Url::is_loopback_ip( '' ) );
	}

	/**
	 * Loopback on HTTP/Varnish ports is allowed; other loopback ports
	 * and link-local destinations are refused.
	 *
	 * @since 2.10.2
	 */
	public function test_is_safe_varnish_purge_target_allows_loopback_http_ports() {
		$this->assertTrue( Util_Url::is_safe_varnish_purge_target( '127.0.0.1', 80 ) );
		$this->assertTrue( Util_Url::is_safe_varnish_purge_target( '127.0.0.1', 8080 ) );
		$this->assertTrue( Util_Url::is_safe_varnish_purge_target( '127.0.0.1', 6081 ) );
		$this->assertTrue( Util_Url::is_safe_varnish_purge_target( '127.0.0.1', 443 ) );
		$this->assertTrue( Util_Url::is_safe_varnish_purge_target( 'localhost', 8080 ) );
		$this->assertTrue( Util_Url::is_safe_varnish_purge_target( '::1', 80 ) );

		$this->assertTrue( Util_Url::is_safe_varnish_purge_target( '10.0.0.1', 80 ) );
		$this->assertTrue( Util_Url::is_safe_varnish_purge_target( '192.168.1.10', 8080 ) );

		$this->assertFalse( Util_Url::is_safe_varnish_purge_target( '127.0.0.1', 6379 ) );
		$this->assertFalse( Util_Url::is_safe_varnish_purge_target( '127.0.0.1', 11211 ) );
		$this->assertFalse( Util_Url::is_safe_varnish_purge_target( '127.0.0.1', 3306 ) );
		$this->assertFalse( Util_Url::is_safe_varnish_purge_target( 'localhost', 6379 ) );

		$this->assertFalse( Util_Url::is_safe_varnish_purge_target( '169.254.169.254', 80 ) );
		$this->assertFalse( Util_Url::is_safe_varnish_purge_target( '169.254.169.254', 8080 ) );
	}

	/**
	 * Operators can extend the HTTP-port allow-list via filter.
	 *
	 * @since 2.10.2
	 */
	public function test_is_varnish_http_port_filterable() {
		$this->assertFalse( Util_Url::is_varnish_http_port( 9080 ) );

		$callback = static function ( $ports ) {
			$ports[] = 9080;
			return $ports;
		};
		\add_filter( 'w3tc_varnish_http_ports', $callback );
		$this->assertTrue( Util_Url::is_varnish_http_port( 9080 ) );
		\remove_filter( 'w3tc_varnish_http_ports', $callback );
	}

	/**
 * Constant-override allowlist: https + exact host or leading-dot suffix.
 *
 * @since 2.10.6
 */
public function test_is_https_host_allowlisted_accepts_exact_and_suffix() {
	$exact    = array( 'w3-edge.com' );
	$suffixes = array( '.w3-edge.com' );

	$this->assertTrue(
		Util_Url::is_https_host_allowlisted( 'https://w3-edge.com/', $exact, $suffixes )
	);
	$this->assertTrue(
		Util_Url::is_https_host_allowlisted( 'https://api2.w3-edge.com/notices', $exact, $suffixes )
	);
	$this->assertTrue(
		Util_Url::is_https_host_allowlisted( 'https://staging.w3-edge.com/api', $exact, $suffixes )
	);
}

/**
 * Rejected schemes, foreign hosts, and suffix lookalikes fail closed.
 *
 * @since 2.10.6
 */
public function test_is_https_host_allowlisted_rejects_unsafe_values() {
	$exact    = array( 'w3-edge.com' );
	$suffixes = array( '.w3-edge.com' );

	$this->assertFalse(
		Util_Url::is_https_host_allowlisted( 'http://api2.w3-edge.com/', $exact, $suffixes )
	);
	$this->assertFalse(
		Util_Url::is_https_host_allowlisted( 'https://evil.example/', $exact, $suffixes )
	);
	$this->assertFalse(
		Util_Url::is_https_host_allowlisted( 'https://xw3-edge.com/', $exact, $suffixes )
	);
	$this->assertFalse(
		Util_Url::is_https_host_allowlisted( 'https://w3-edge.com.evil.example/', $exact, $suffixes )
	);
	$this->assertFalse(
		Util_Url::is_https_host_allowlisted( 'https://127.0.0.1/', $exact, $suffixes )
	);
	$this->assertFalse(
		Util_Url::is_https_host_allowlisted( '', $exact, $suffixes )
	);
	$this->assertFalse(
		Util_Url::is_https_host_allowlisted( null, $exact, $suffixes )
	);
}

/**
 * Suffix entries without a leading dot are ignored.
 *
 * @since 2.10.6
 */
public function test_is_https_host_allowlisted_requires_leading_dot_on_suffixes() {
	$this->assertFalse(
		Util_Url::is_https_host_allowlisted(
			'https://api2.w3-edge.com/',
			array(),
			array( 'w3-edge.com' )
		)
	);
}

/**
 * Outbound-fetch policy accepts this site's host and refuses
 * private / non-http destinations.
 *
	 * @since 2.10.6
	 */
	public function test_is_allowed_outbound_url_policy() {
		$this->assertTrue(
			Util_Url::is_allowed_outbound_url( \home_url( '/wp-content/themes/style.css' ) )
		);

		$denied = array(
			'http://127.0.0.1/style.css',
			'http://10.0.0.1/style.css',
			'http://169.254.169.254/latest/meta-data/',
			'file:///etc/passwd',
			'gopher://example.com/1',
			'',
		);

		foreach ( $denied as $url ) {
			$this->assertFalse(
				Util_Url::is_allowed_outbound_url( $url ),
				'Expected refusal for ' . $url
			);
		}
	}

	/**
	 * Outbound-fetch policy refuses link-local, metadata, and non-http
	 * destinations in every address form.
	 *
	 * @since 2.11.1
	 */
	public function test_is_allowed_outbound_url_refuses_link_local_and_metadata() {
		$denied = array(
			'http://169.254.169.254/latest/meta-data/iam/',
			'http://169.254.0.1/',
			'http://[fe80::1]/',
			'http://[::ffff:169.254.169.254]/',
			'http://[::1]:6379/',
			'http://0.0.0.0/',
			'ftp://example.com/',
			'php://filter/resource=index.php',
			'data:text/plain,hello',
			'http://localhost/',
		);

		foreach ( $denied as $url ) {
			$this->assertFalse(
				Util_Url::is_allowed_outbound_url( $url ),
				'Expected refusal for ' . $url
			);
		}
	}

	/**
	 * The public_host_ips() helper returns the validated literal for a public IP and
	 * nothing for a refused host.
	 *
	 * @since 2.11.1
	 */
	public function test_public_host_ips_returns_validated_addresses() {
		$this->assertSame( array( '8.8.8.8' ), Util_Url::public_host_ips( 'http://8.8.8.8/' ) );
		$this->assertSame( array( '2001:4860:4860::8888' ), Util_Url::public_host_ips( 'http://[2001:4860:4860::8888]/' ) );

		foreach ( array( 'http://127.0.0.1/', 'http://169.254.169.254/', 'http://localhost/', 'gopher://8.8.8.8/', '' ) as $url ) {
			$this->assertSame( array(), Util_Url::public_host_ips( $url ), 'Expected no addresses for ' . $url );
		}
	}

	/**
	 * CURLOPT_RESOLVE entries carry host, port, and bracketed IPv6.
	 *
	 * @since 2.11.1
	 */
	public function test_curl_resolve_entry_format() {
		if ( ! defined( 'CURLOPT_RESOLVE' ) ) {
			$this->markTestSkipped( 'cURL is not available.' );
		}

		$this->assertSame(
			'example.com:443:93.184.215.14',
			Util_Http::curl_resolve_entry( 'https://Example.com/path', array( '93.184.215.14' ) )
		);
		$this->assertSame(
			'example.com:8080:[2606:2800:21f:cb07:6820:80da:af6b:8b2c]',
			Util_Http::curl_resolve_entry( 'http://example.com:8080/', array( '2606:2800:21f:cb07:6820:80da:af6b:8b2c' ) )
		);
		$this->assertSame( '', Util_Http::curl_resolve_entry( 'http://8.8.8.8/', array( '8.8.8.8' ) ) );
		$this->assertSame( '', Util_Http::curl_resolve_entry( 'https://example.com/', array() ) );
		$this->assertSame( '', Util_Http::curl_resolve_entry( 'https://example.com/', array( 'not-an-ip' ) ) );
	}

	/**
	 * The get_pinned() helper registers the pinning hook only for the duration of
	 * the request and disables transport-level redirects.
	 *
	 * @since 2.11.1
	 */
	public function test_get_pinned_pins_public_host_for_request() {
		if ( ! defined( 'CURLOPT_RESOLVE' ) || array() === Util_Url::public_host_ips( 'https://example.com/' ) ) {
			$this->markTestSkipped( 'Requires cURL and public DNS resolution.' );
		}

		$seen     = array();
		$callback = static function ( $preempt, $args, $url ) use ( &$seen ) {
			$seen[] = array(
				'url'         => $url,
				'pinned'      => false !== has_action( 'http_api_curl' ),
				'redirection' => isset( $args['redirection'] ) ? $args['redirection'] : null,
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
		};
		add_filter( 'pre_http_request', $callback, 10, 3 );

		$response = Util_Http::get_pinned( 'https://example.com/' );

		remove_filter( 'pre_http_request', $callback, 10 );

		$this->assertIsArray( $response );
		$this->assertCount( 1, $seen );
		$this->assertTrue( $seen[0]['pinned'] );
		$this->assertSame( 0, $seen[0]['redirection'] );
		$this->assertFalse( has_action( 'http_api_curl' ) );
	}

	/**
	 * The get_pinned() helper refuses internal first hops without a request and does
	 * not follow a redirect to an internal address.
	 *
	 * @since 2.11.1
	 */
	public function test_get_pinned_refuses_internal_destinations() {
		$requested = array();
		$callback  = static function ( $preempt, $args, $url ) use ( &$requested ) {
			$requested[] = $url;

			return array(
				'headers'  => array( 'location' => 'http://169.254.169.254/latest/meta-data/' ),
				'body'     => '',
				'response' => array(
					'code'    => 302,
					'message' => 'Found',
				),
				'cookies'  => array(),
				'filename' => null,
			);
		};
		add_filter( 'pre_http_request', $callback, 10, 3 );

		$refused  = Util_Http::get_pinned( 'http://127.0.0.1:6379/' );
		$redirect = Util_Http::get_pinned( \home_url( '/redirect' ) );

		remove_filter( 'pre_http_request', $callback, 10 );

		$this->assertWPError( $refused );
		$this->assertIsArray( $redirect );
		$this->assertSame( 302, wp_remote_retrieve_response_code( $redirect ) );
		$this->assertSame( array( \home_url( '/redirect' ) ), $requested );
	}

	/**
	 * Allowed redirect chains follow up to five hops; a longer chain
	 * returns an error instead of the last redirect response.
	 *
	 * @since 2.11.1
	 */
	public function test_get_pinned_redirect_limit() {
		$requested = array();
		$callback  = static function ( $preempt, $args, $url ) use ( &$requested ) {
			$requested[] = $url;
			$hop         = (int) \substr( (string) \wp_parse_url( $url, PHP_URL_PATH ), \strlen( '/hop-' ) );
			$final       = isset( $args['w3tc_test_final'] ) ? (int) $args['w3tc_test_final'] : 0;

			return array(
				'headers'  => $hop < $final ? array( 'location' => \home_url( '/hop-' . ( $hop + 1 ) ) ) : array(),
				'body'     => '',
				'response' => array(
					'code'    => $hop < $final ? 302 : 200,
					'message' => '',
				),
				'cookies'  => array(),
				'filename' => null,
			);
		};
		add_filter( 'pre_http_request', $callback, 10, 3 );

		$five = Util_Http::get_pinned( \home_url( '/hop-0' ), array( 'w3tc_test_final' => 5 ) );
		$five_requests = $requested;
		$requested     = array();

		$six = Util_Http::get_pinned( \home_url( '/hop-0' ), array( 'w3tc_test_final' => 6 ) );
		$six_requests = $requested;
		$requested    = array();

		$none = Util_Http::get_pinned( \home_url( '/hop-0' ), array( 'w3tc_test_final' => 1 ), null, 0 );

		remove_filter( 'pre_http_request', $callback, 10 );

		$this->assertSame( 200, wp_remote_retrieve_response_code( $five ) );
		$this->assertCount( 6, $five_requests );
		$this->assertWPError( $six );
		$this->assertSame( 'w3tc_too_many_redirects', $six->get_error_code() );
		$this->assertCount( 6, $six_requests );
		$this->assertSame( 302, wp_remote_retrieve_response_code( $none ) );
		$this->assertSame( array( \home_url( '/hop-0' ) ), $requested );
	}

	/**
	 * Cookies set by a redirect are sent on the next hop when their
	 * domain and path apply, for both redirect-following helpers.
	 *
	 * @since 2.11.1
	 */
	public function test_redirect_hops_carry_applicable_cookies() {
		$sent     = array();
		$callback = static function ( $preempt, $args, $url ) use ( &$sent ) {
			$names = array();
			foreach ( isset( $args['cookies'] ) ? (array) $args['cookies'] : array() as $cookie ) {
				$names[] = $cookie instanceof \WP_Http_Cookie ? $cookie->name : 'raw';
			}
			$sent[ \wp_parse_url( $url, PHP_URL_PATH ) ][] = $names;

			if ( '/start' === \wp_parse_url( $url, PHP_URL_PATH ) ) {
				return array(
					'headers'  => array( 'location' => \home_url( '/app/final' ) ),
					'body'     => '',
					'response' => array(
						'code'    => 302,
						'message' => 'Found',
					),
					'cookies'  => array(
						new \WP_Http_Cookie( array( 'name' => 'route', 'value' => 'a' ) ),
						new \WP_Http_Cookie(
							array(
								'name'   => 'scoped',
								'value'  => 'b',
								'path'   => '/app',
								'domain' => (string) \wp_parse_url( \home_url(), PHP_URL_HOST ),
							)
						),
						new \WP_Http_Cookie(
							array(
								'name'   => 'elsewhere',
								'value'  => 'c',
								'domain' => 'other.example',
							)
						),
						new \WP_Http_Cookie(
							array(
								'name'  => 'wrong_path',
								'value' => 'd',
								'path'  => '/admin',
							)
						),
					),
					'filename' => null,
				);
			}

			return array(
				'headers'  => array(),
				'body'     => 'ok',
				'response' => array(
					'code'    => 200,
					'message' => 'OK',
				),
				'cookies'  => array(),
				'filename' => null,
			);
		};
		add_filter( 'pre_http_request', $callback, 10, 3 );

		$response = Util_Http::get_pinned( \home_url( '/start' ) );
		$file     = \wp_tempnam( 'w3tc-redirect-cookies' );
		$saved    = Util_Http::download( \home_url( '/start' ), $file );

		remove_filter( 'pre_http_request', $callback, 10 );
		@\unlink( $file ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.unlink_unlink

		$this->assertSame( 200, wp_remote_retrieve_response_code( $response ) );
		$this->assertTrue( $saved );
		$this->assertSame( array( array(), array() ), $sent['/start'] );
		$this->assertSame( array( array( 'route', 'scoped' ), array( 'route', 'scoped' ) ), $sent['/app/final'] );
	}

	/**
	 * A cookie cleared by a redirect, by Expires or by Max-Age, is not
	 * sent on the next hop, including one the caller supplied.
	 *
	 * @since 2.11.1
	 */
	public function test_redirect_hops_drop_cleared_cookies() {
		if ( ! \class_exists( '\WpOrg\Requests\Response' ) ) {
			$this->markTestSkipped( 'Requires Requests 2.' );
		}

		$sent     = array();
		$callback = static function ( $preempt, $args, $url ) use ( &$sent ) {
			$names = array();
			foreach ( isset( $args['cookies'] ) ? (array) $args['cookies'] : array() as $key => $cookie ) {
				$names[] = $cookie instanceof \WP_Http_Cookie ? $cookie->name . '=' . $cookie->value : $key . '=' . $cookie;
			}
			\sort( $names );
			$sent[ \wp_parse_url( $url, PHP_URL_PATH ) ] = $names;

			if ( '/start' !== \wp_parse_url( $url, PHP_URL_PATH ) ) {
				return array(
					'headers'  => array(),
					'body'     => 'ok',
					'response' => array(
						'code'    => 200,
						'message' => 'OK',
					),
					'cookies'  => array(),
					'filename' => null,
				);
			}

			$requests          = new \WpOrg\Requests\Response();
			$requests->cookies = new \WpOrg\Requests\Cookie\Jar(
				array(
					'by_max_age' => new \WpOrg\Requests\Cookie( 'by_max_age', 'x', array( 'max-age' => 0 ) ),
				)
			);

			return array(
				'headers'       => array( 'location' => \home_url( '/final' ) ),
				'body'          => '',
				'response'      => array(
					'code'    => 302,
					'message' => 'Found',
				),
				'cookies'       => array(
					new \WP_Http_Cookie(
						array(
							'name'    => 'by_expires',
							'value'   => 'deleted',
							'expires' => \time() - 3600,
						)
					),
					new \WP_Http_Cookie( array( 'name' => 'by_max_age', 'value' => 'x' ) ),
					new \WP_Http_Cookie( array( 'name' => 'route', 'value' => 'new' ) ),
				),
				'filename'      => null,
				'http_response' => new \WP_HTTP_Requests_Response( $requests ),
			);
		};
		add_filter( 'pre_http_request', $callback, 10, 3 );

		$response = Util_Http::get_pinned(
			\home_url( '/start' ),
			array(
				'cookies' => array(
					'by_expires' => 'old',
					'by_max_age' => 'old',
					'route'      => 'old',
					'keep'       => '1',
				),
			)
		);

		remove_filter( 'pre_http_request', $callback, 10 );

		$this->assertSame( 200, wp_remote_retrieve_response_code( $response ) );
		$this->assertSame( array( 'keep=1', 'route=new' ), $sent['/final'] );
	}

	/**
	 * The pinning hook is removed even when a request callback throws.
	 *
	 * @since 2.11.1
	 */
	public function test_get_pinned_removes_hook_when_request_throws() {
		if ( ! defined( 'CURLOPT_RESOLVE' ) || array() === Util_Url::public_host_ips( 'https://example.com/' ) ) {
			$this->markTestSkipped( 'Requires cURL and public DNS resolution.' );
		}

		$callback = static function () {
			throw new \RuntimeException( 'request failed' );
		};
		add_filter( 'pre_http_request', $callback, 10, 3 );

		$thrown = false;
		try {
			Util_Http::get_pinned( 'https://example.com/' );
		} catch ( \RuntimeException $e ) {
			$thrown = true;
		}

		remove_filter( 'pre_http_request', $callback, 10 );

		$this->assertTrue( $thrown );
		$this->assertFalse( has_action( 'http_api_curl' ) );
	}

	/**
	 * The pin callback applies CURLOPT_RESOLVE to the matching request
	 * only: a host that does not resolve connects to the pinned address.
	 *
	 * @since 2.11.1
	 */
	public function test_curl_pin_callback_applies_resolve_entry() {
		if ( ! defined( 'CURLOPT_RESOLVE' ) || ! \function_exists( 'curl_init' ) ) {
			$this->markTestSkipped( 'Requires cURL.' );
		}

		$server = @\stream_socket_server( 'tcp://127.0.0.1:0', $errno, $errstr ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		if ( false === $server ) {
			$this->markTestSkipped( 'Cannot open a local listener.' );
		}

		$port  = (int) \substr( (string) \strrchr( (string) \stream_socket_get_name( $server, false ), ':' ), 1 );
		$url   = 'http://w3tc-pin-test.invalid:' . $port . '/';
		$entry = 'w3tc-pin-test.invalid:' . $port . ':127.0.0.1';
		$pin   = Util_Http::curl_pin_callback( $url, $entry );

		$connect = static function ( $request_url ) use ( $pin, $url ) {
			$handle = \curl_init();
			$pin( $handle, array(), $request_url );
			\curl_setopt( $handle, CURLOPT_URL, $url );
			\curl_setopt( $handle, CURLOPT_CONNECT_ONLY, true );
			\curl_setopt( $handle, CURLOPT_CONNECTTIMEOUT, 2 );
			\curl_exec( $handle );

			return array(
				'errno' => \curl_errno( $handle ),
				'ip'    => \curl_getinfo( $handle, CURLINFO_PRIMARY_IP ),
			);
		};

		$pinned   = $connect( $url );
		$unpinned = $connect( 'http://other.invalid/' );

		\fclose( $server );

		$this->assertSame( 0, $pinned['errno'] );
		$this->assertSame( '127.0.0.1', $pinned['ip'] );
		$this->assertNotSame( 0, $unpinned['errno'] );
	}

	/**
	 * Protocol-relative URLs pick up the current request scheme.
	 *
	 * @since 2.10.6
	 */
	public function test_normalize_protocol_relative_url() {
		$scheme = Util_Url::normalize_protocol_relative_url( '//example.com/a.css' );
		$this->assertMatchesRegularExpression( '#^https?://example\\.com/a\\.css$#', $scheme );
	$this->assertSame(
		'https://cdn.example/a.js',
		Util_Url::normalize_protocol_relative_url( 'https://cdn.example/a.js' )
	);
	$this->assertSame( '', Util_Url::normalize_protocol_relative_url( '' ) );
}
}
