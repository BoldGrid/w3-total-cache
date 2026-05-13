<?php
/**
 * File: class-w3tc-util-url-test.php
 *
 * @package    W3TC
 * @subpackage W3TC/tests/admin
 * @since      X.X.X
 */

declare( strict_types = 1 );

use W3TC\Util_Url;

/**
 * Class: W3tc_Util_Url_Test
 *
 * Coverage for the SSRF guard. Regressions here re-open the
 * "outbound fetch to admin-supplied URL" primitive for every site
 * routed through `Util_Url::is_public_host()`, so the negative
 * coverage is exhaustive across the four families of private-range
 * address (loopback, RFC1918, link-local, IPv4-mapped IPv6).
 *
 * @since X.X.X
 */
class W3tc_Util_Url_Test extends WP_UnitTestCase {

	/**
	 * Bare IP literals — no DNS lookup needed — must be refused for
	 * every private / loopback / link-local / reserved range.
	 *
	 * @since X.X.X
	 */
	public function test_is_public_host_refuses_private_ipv4_literals() {
		$payloads = array(
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

		foreach ( $payloads as $label => $url ) {
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
	 * @since X.X.X
	 */
	public function test_is_public_host_refuses_private_ipv6_literals() {
		$payloads = array(
			'ipv6 loopback'      => 'http://[::1]/',
			'ipv6 link-local'    => 'http://[fe80::1]/',
			'ipv6 unique-local'  => 'http://[fc00::1]/',
			'ipv4-mapped lo'     => 'http://[::ffff:127.0.0.1]/',
			'ipv4-mapped priv'   => 'http://[::ffff:10.0.0.1]/',
			'ipv4-mapped aws'    => 'http://[::ffff:169.254.169.254]/',
		);

		foreach ( $payloads as $label => $url ) {
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
	 * @since X.X.X
	 */
	public function test_is_public_host_refuses_loopback_named_host() {
		// `localhost` resolves to 127.0.0.1 on every supported host.
		$this->assertFalse( Util_Url::is_public_host( 'http://localhost/' ) );
	}

	/**
	 * Non-http(s) schemes are always refused — `file://`, `gopher://`,
	 * `data://`, etc. are SSRF primitives in their own right.
	 *
	 * @since X.X.X
	 */
	public function test_is_public_host_refuses_non_http_schemes() {
		$payloads = array(
			'file:///etc/passwd',
			'gopher://evil.example/',
			'ftp://example.com/',
			'data:,evil',
			'php://input',
		);
		foreach ( $payloads as $url ) {
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
	 * @since X.X.X
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
	 * @since X.X.X
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
	 * @since X.X.X
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
}
