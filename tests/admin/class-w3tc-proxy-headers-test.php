<?php
/**
 * File: class-w3tc-proxy-headers-test.php
 *
 * Table-driven coverage for proxy-derived client IP and scheme
 * selection (ENG3-596).
 *
 * @package    W3TC
 * @subpackage W3TC/tests/admin
 * @since      2.10.6
 */

declare( strict_types = 1 );

use W3TC\Util_Environment;

/**
 * Class: W3tc_Proxy_Headers_Test
 *
 * @since 2.10.6
 */
class W3tc_Proxy_Headers_Test extends WP_UnitTestCase {

	/**
	 * Restore filters after each test.
	 *
	 * @since 2.10.6
	 */
	public function tearDown(): void {
		remove_all_filters( 'w3tc_trusted_proxies' );
		remove_all_filters( 'w3tc_cloudflare_proxy_cidrs' );
		parent::tearDown();
	}

	/**
	 * Direct clients ignore spoofed forwarding headers.
	 *
	 * @since 2.10.6
	 */
	public function test_untrusted_peer_ignores_forwarded_headers() {
		$server = array(
			'REMOTE_ADDR'              => '203.0.113.10',
			'HTTP_X_FORWARDED_FOR'     => '198.51.100.1',
			'HTTP_X_REAL_IP'           => '198.51.100.2',
			'HTTP_CF_CONNECTING_IP'    => '198.51.100.3',
			'HTTP_X_FORWARDED_PROTO'   => 'https',
			'HTTPS'                    => '',
			'SERVER_PORT'              => '80',
		);

		$this->assertSame( '203.0.113.10', Util_Environment::get_client_ip( $server ) );
		$this->assertFalse( Util_Environment::is_https( $server ) );
	}

	/**
	 * Empty / malformed REMOTE_ADDR does not become a forwarding oracle.
	 *
	 * @since 2.10.6
	 */
	public function test_invalid_peer_is_rejected() {
		$server = array(
			'REMOTE_ADDR'          => 'not-an-ip',
			'HTTP_X_FORWARDED_FOR' => '198.51.100.1',
		);

		$this->assertSame( '', Util_Environment::get_client_ip( $server ) );
	}

	/**
	 * Trusted proxy + X-Forwarded-For chain: skip trusted hops from the right.
	 *
	 * @since 2.10.6
	 */
	public function test_trusted_chain_right_to_left() {
		add_filter(
			'w3tc_trusted_proxies',
			static function () {
				return array( '10.0.0.1/32', '10.0.0.2/32' );
			}
		);

		$server = array(
			'REMOTE_ADDR'          => '10.0.0.1',
			'HTTP_X_FORWARDED_FOR' => '198.51.100.7, 10.0.0.2, 10.0.0.1',
		);

		$this->assertSame( '198.51.100.7', Util_Environment::get_client_ip( $server ) );
	}

	/**
	 * Malformed hops in a trusted chain are skipped.
	 *
	 * @since 2.10.6
	 */
	public function test_malformed_hops_are_skipped() {
		add_filter(
			'w3tc_trusted_proxies',
			static function () {
				return array( '10.0.0.1/32' );
			}
		);

		$server = array(
			'REMOTE_ADDR'          => '10.0.0.1',
			'HTTP_X_FORWARDED_FOR' => '198.51.100.9, not-an-ip, unknown',
		);

		$this->assertSame( '198.51.100.9', Util_Environment::get_client_ip( $server ) );
	}

	/**
	 * IPv6 chain + CIDR.
	 *
	 * @since 2.10.6
	 */
	public function test_ipv6_trusted_chain() {
		add_filter(
			'w3tc_trusted_proxies',
			static function () {
				return array( '2001:db8:1::/64' );
			}
		);

		$server = array(
			'REMOTE_ADDR'          => '2001:db8:1::10',
			'HTTP_X_FORWARDED_FOR' => '2001:db8:cafe::1, 2001:db8:1::10',
		);

		$this->assertSame( '2001:db8:cafe::1', Util_Environment::get_client_ip( $server ) );
	}

	/**
	 * Bracketed IPv6 with port in X-Forwarded-For.
	 *
	 * @since 2.10.6
	 */
	public function test_bracketed_ipv6_token() {
		add_filter(
			'w3tc_trusted_proxies',
			static function () {
				return array( '10.0.0.1/32' );
			}
		);

		$server = array(
			'REMOTE_ADDR'          => '10.0.0.1',
			'HTTP_X_FORWARDED_FOR' => '[2001:db8::55]:8080',
		);

		$this->assertSame( '2001:db8::55', Util_Environment::get_client_ip( $server ) );
	}

	/**
	 * IPv4:port token.
	 *
	 * @since 2.10.6
	 */
	public function test_ipv4_with_port_token() {
		add_filter(
			'w3tc_trusted_proxies',
			static function () {
				return array( '10.0.0.1/32' );
			}
		);

		$server = array(
			'REMOTE_ADDR'          => '10.0.0.1',
			'HTTP_X_FORWARDED_FOR' => '198.51.100.20:1234',
		);

		$this->assertSame( '198.51.100.20', Util_Environment::get_client_ip( $server ) );
	}

	/**
	 * CF-Connecting-IP wins over X-Forwarded-For when the peer is trusted.
	 *
	 * @since 2.10.6
	 */
	public function test_cf_connecting_ip_precedes_xff() {
		add_filter(
			'w3tc_trusted_proxies',
			static function () {
				return array( '10.0.0.1/32' );
			}
		);
		add_filter(
			'w3tc_cloudflare_proxy_cidrs',
			static function () {
				return array( '10.0.0.1/32' );
			}
		);

		$server = array(
			'REMOTE_ADDR'           => '10.0.0.1',
			'HTTP_CF_CONNECTING_IP' => '198.51.100.40',
			'HTTP_X_FORWARDED_FOR'  => '198.51.100.41',
			'HTTP_X_REAL_IP'        => '198.51.100.42',
		);

		$this->assertSame( '198.51.100.40', Util_Environment::get_client_ip( $server ) );
	}

	/**
	 * Generic trusted proxies must not honor client-supplied
	 * CF-Connecting-IP (Cloudflare-only header).
	 *
	 * @since 2.10.6
	 */
	public function test_generic_trusted_proxy_ignores_cf_connecting_ip() {
		add_filter(
			'w3tc_trusted_proxies',
			static function () {
				return array( '10.0.0.1/32' );
			}
		);

		$server = array(
			'REMOTE_ADDR'           => '10.0.0.1',
			'HTTP_CF_CONNECTING_IP' => '198.51.100.40',
			'HTTP_X_FORWARDED_FOR'  => '198.51.100.41',
		);

		$this->assertSame( '198.51.100.41', Util_Environment::get_client_ip( $server ) );
	}

	/**
	 * X-Real-IP is used when CF-Connecting-IP is absent.
	 *
	 * @since 2.10.6
	 */
	public function test_x_real_ip_precedes_xff() {
		add_filter(
			'w3tc_trusted_proxies',
			static function () {
				return array( '10.0.0.1/32' );
			}
		);

		$server = array(
			'REMOTE_ADDR'          => '10.0.0.1',
			'HTTP_X_REAL_IP'       => '198.51.100.50',
			'HTTP_X_FORWARDED_FOR' => '198.51.100.51',
		);

		$this->assertSame( '198.51.100.50', Util_Environment::get_client_ip( $server ) );
	}

	/**
	 * Invalid CF-Connecting-IP falls through to the next header.
	 *
	 * @since 2.10.6
	 */
	public function test_invalid_cf_header_falls_through() {
		add_filter(
			'w3tc_trusted_proxies',
			static function () {
				return array( '10.0.0.1/32' );
			}
		);
		add_filter(
			'w3tc_cloudflare_proxy_cidrs',
			static function () {
				return array( '10.0.0.1/32' );
			}
		);

		$server = array(
			'REMOTE_ADDR'           => '10.0.0.1',
			'HTTP_CF_CONNECTING_IP' => 'not-an-ip<script>',
			'HTTP_X_FORWARDED_FOR'  => '198.51.100.60',
		);

		$this->assertSame( '198.51.100.60', Util_Environment::get_client_ip( $server ) );
	}

	/**
	 * RFC 7239 Forwarded for= when XFF is absent.
	 *
	 * @since 2.10.6
	 */
	public function test_rfc7239_forwarded_for() {
		add_filter(
			'w3tc_trusted_proxies',
			static function () {
				return array( '10.0.0.1/32' );
			}
		);

		$server = array(
			'REMOTE_ADDR'     => '10.0.0.1',
			'HTTP_FORWARDED'  => 'for=198.51.100.70;proto=https',
		);

		$this->assertSame( '198.51.100.70', Util_Environment::get_client_ip( $server ) );
	}

	/**
	 * All-trusted chain falls back to the TCP peer.
	 *
	 * @since 2.10.6
	 */
	public function test_all_trusted_chain_returns_peer() {
		add_filter(
			'w3tc_trusted_proxies',
			static function () {
				return array( '10.0.0.0/8' );
			}
		);

		$server = array(
			'REMOTE_ADDR'          => '10.0.0.1',
			'HTTP_X_FORWARDED_FOR' => '10.0.0.2, 10.0.0.3',
		);

		$this->assertSame( '10.0.0.1', Util_Environment::get_client_ip( $server ) );
	}

	/**
	 * X-Forwarded-Proto is honored only from a trusted peer.
	 *
	 * @since 2.10.6
	 */
	public function test_https_from_trusted_forwarded_proto() {
		add_filter(
			'w3tc_trusted_proxies',
			static function () {
				return array( '10.0.0.1/32' );
			}
		);

		$trusted = array(
			'REMOTE_ADDR'            => '10.0.0.1',
			'HTTP_X_FORWARDED_PROTO' => 'https',
			'HTTPS'                  => '',
			'SERVER_PORT'            => '80',
		);
		$untrusted = $trusted;
		$untrusted['REMOTE_ADDR'] = '203.0.113.9';

		$this->assertTrue( Util_Environment::is_https( $trusted ) );
		$this->assertFalse( Util_Environment::is_https( $untrusted ) );
	}

	/**
	 * Direct TLS on port 443 does not require a proxy header.
	 *
	 * @since 2.10.6
	 */
	public function test_https_from_port_443() {
		$server = array(
			'REMOTE_ADDR' => '203.0.113.10',
			'HTTPS'       => '',
			'SERVER_PORT' => '443',
		);

		$this->assertTrue( Util_Environment::is_https( $server ) );
	}

	/**
	 * Stashed connecting address is used for trust after REMOTE_ADDR rewrite.
	 *
	 * @since 2.10.6
	 */
	public function test_stashed_connecting_addr_keeps_trust() {
		add_filter(
			'w3tc_trusted_proxies',
			static function () {
				return array( '10.0.0.1/32' );
			}
		);

		$server = array(
			'REMOTE_ADDR'            => '198.51.100.80',
			'W3TC_CONNECTING_ADDR'   => '10.0.0.1',
			'HTTP_X_FORWARDED_PROTO' => 'https',
			'HTTPS'                  => '',
			'SERVER_PORT'            => '80',
		);

		$this->assertSame( '198.51.100.80', Util_Environment::get_client_ip( $server ) );
		$this->assertTrue( Util_Environment::is_https( $server ) );
	}

	/**
	 * parse_ip_token rejects hostnames and empty values.
	 *
	 * @since 2.10.6
	 */
	public function test_parse_ip_token_negatives() {
		$this->assertSame( '', Util_Environment::parse_ip_token( 'example.com' ) );
		$this->assertSame( '', Util_Environment::parse_ip_token( '' ) );
		$this->assertSame( '', Util_Environment::parse_ip_token( array( '1.2.3.4' ) ) );
		$this->assertSame( '192.0.2.1', Util_Environment::parse_ip_token( ' 192.0.2.1 ' ) );
	}

	/**
	 * Malformed CIDR prefixes must not match every address (/0).
	 *
	 * @since 2.10.6
	 */
	public function test_malformed_cidr_prefix_does_not_trust_peer() {
		$peer = '203.0.113.10';
		foreach ( array( '10.0.0.0/', '10.0.0.0/abc', '10.0.0.0/00', '10.0.0.0/0' ) as $cidr ) {
			remove_all_filters( 'w3tc_trusted_proxies' );
			add_filter(
				'w3tc_trusted_proxies',
				static function () use ( $cidr ) {
					return array( $cidr );
				}
			);

			$server = array(
				'REMOTE_ADDR'          => $peer,
				'HTTP_X_FORWARDED_FOR' => '198.51.100.1',
			);

			$this->assertFalse( Util_Environment::ip_in_cidrs( $peer, array( $cidr ) ), $cidr );
			$this->assertSame( $peer, Util_Environment::get_client_ip( $server ), $cidr );
		}
	}

	/**
	 * X-Forwarded-Proto uses the last hop (closest proxy).
	 *
	 * @since 2.10.6
	 */
	public function test_https_uses_last_forwarded_proto_hop() {
		add_filter(
			'w3tc_trusted_proxies',
			static function () {
				return array( '10.0.0.1/32' );
			}
		);

		$server = array(
			'REMOTE_ADDR'            => '10.0.0.1',
			'HTTP_X_FORWARDED_PROTO' => 'https, http',
			'HTTPS'                  => '',
			'SERVER_PORT'            => '80',
		);

		$this->assertFalse( Util_Environment::is_https( $server ) );
	}

	/**
	 * RFC 7239 proto= uses the last hop.
	 *
	 * @since 2.10.6
	 */
	public function test_https_uses_last_rfc7239_proto_hop() {
		add_filter(
			'w3tc_trusted_proxies',
			static function () {
				return array( '10.0.0.1/32' );
			}
		);

		$server = array(
			'REMOTE_ADDR'     => '10.0.0.1',
			'HTTP_FORWARDED'  => 'for=198.51.100.70;proto=https, for=198.51.100.71;proto=http',
			'HTTPS'           => '',
			'SERVER_PORT'     => '80',
		);

		$this->assertFalse( Util_Environment::is_https( $server ) );
	}
}
