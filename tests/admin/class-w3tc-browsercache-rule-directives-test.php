<?php
/**
 * File: class-w3tc-browsercache-rule-directives-test.php
 *
 * @package    W3TC
 * @subpackage W3TC/tests/admin
 * @since      2.10.6
 */

declare( strict_types = 1 );

use W3TC\BrowserCache_Environment_Apache;
use W3TC\BrowserCache_Environment_Nginx;
use W3TC\BrowserCache_RuleDirectives;
use W3TC\Config;
use W3TC\Util_Rule;

/**
 * Class: W3tc_BrowserCache_Rule_Directives_Test
 *
 * Render-time BrowserCache rule policy: exception normalization,
 * select-backed directive allowlists, and Apache/Nginx fixtures.
 *
 * @since 2.10.6
 */
class W3tc_BrowserCache_Rule_Directives_Test extends WP_UnitTestCase {

	/**
	 * Equivalent exception inputs normalize to the same ordered list.
	 *
	 * @since 2.10.6
	 */
	public function test_normalize_exceptions_is_deterministic() {
		$a = BrowserCache_RuleDirectives::normalize_exceptions(
			array(
				'sitemap\.xml',
				'robots\.txt',
				'sitemap\.xml',
			)
		);
		$b = BrowserCache_RuleDirectives::normalize_exceptions(
			array(
				'robots\.txt',
				'sitemap\.xml',
			)
		);

		$this->assertSame( $a, $b );
		$this->assertSame(
			array( 'robots\.txt', 'sitemap\.xml' ),
			$a
		);
	}

	/**
	 * Forbidden bytes and empty/non-string shapes do not survive.
	 *
	 * @since 2.10.6
	 */
	public function test_normalize_exceptions_drops_invalid_shapes() {
		$out = BrowserCache_RuleDirectives::normalize_exceptions(
			array(
				"robots\\.txt\nHeader set X-Evil \"1\"",
				'',
				null,
				array( 'nested' ),
				"\n\r",
				'ok\.pattern',
			)
		);

		$this->assertSame( array( 'ok\.pattern', 'robots\.txtHeader set X-Evil 1' ), $out );
		foreach ( $out as $entry ) {
			$this->assertSame(
				0,
				preg_match( '/[\r\n\x00<>"]/', $entry ),
				'Forbidden byte remained: ' . $entry
			);
		}
	}

	/**
	 * Shared array sanitiser matches the scalar helper contract.
	 *
	 * @since 2.10.6
	 */
	public function test_sanitize_directive_values_filters_empties() {
		$this->assertSame(
			array( 'a', 'b' ),
			Util_Rule::sanitize_directive_values( array( 'a', "\n", 'b', '' ) )
		);
		$this->assertSame( array(), Util_Rule::sanitize_directive_values( 'not-an-array' ) );
		$this->assertSame( array(), Util_Rule::sanitize_directive_values( null ) );
	}

	/**
	 * Select-backed directives accept only UI tokens.
	 *
	 * @since 2.10.6
	 */
	public function test_normalize_enum_accepts_known_rejects_unknown() {
		$this->assertSame( 'maxageincpre', BrowserCache_RuleDirectives::normalize_enum( 'hsts', 'maxageincpre' ) );
		$this->assertSame( 'block', BrowserCache_RuleDirectives::normalize_enum( 'xss', 'block' ) );
		$this->assertSame( 'same', BrowserCache_RuleDirectives::normalize_enum( 'xfo', 'same' ) );
		$this->assertSame(
			'no-referrer-when-downgrade',
			BrowserCache_RuleDirectives::normalize_enum( 'referrer_policy', 'no-referrer-when-downgrade' )
		);

		$this->assertNull( BrowserCache_RuleDirectives::normalize_enum( 'hsts', "maxage\nevil" ) );
		$this->assertNull( BrowserCache_RuleDirectives::normalize_enum( 'xss', '1; mode=block' ) );
		$this->assertNull( BrowserCache_RuleDirectives::normalize_enum( 'xfo', 'ALLOW-FROM' ) );
		$this->assertNull( BrowserCache_RuleDirectives::normalize_enum( 'referrer_policy', 'totally-fake' ) );
		$this->assertNull( BrowserCache_RuleDirectives::normalize_enum( 'unknown', 'maxage' ) );
	}

	/**
	 * Default compatibility exceptions still render on Apache and Nginx.
	 *
	 * @since 2.10.6
	 */
	public function test_default_exceptions_render_on_apache_and_nginx() {
		$config = new Config();
		$config->set( 'browsercache.no404wp', true );
		$config->set(
			'browsercache.no404wp.exceptions',
			array(
				'robots\.txt',
				'[a-z0-9_\-]*sitemap[a-z0-9_\.\-]*\.(xml|xsl|html)(\.gz)?',
			)
		);

		$mime = $this->mime_fixture();

		$apache = ( new BrowserCache_Environment_Apache( $config ) )->rules_no404wp( $mime );
		$this->assertStringContainsString( 'RewriteCond %{REQUEST_URI} !(', $apache );
		$this->assertStringContainsString( 'robots\.txt', $apache );
		$this->assertStringContainsString( 'sitemap', $apache );

		$nginx = $this->nginx_cache_fragment( $config );
		$this->assertStringContainsString( 'location ~ (', $nginx );
		$this->assertStringContainsString( 'robots\.txt', $nginx );
		$this->assertStringContainsString( 'sitemap', $nginx );
	}

	/**
	 * Injected newlines never become a second Apache/Nginx directive line.
	 *
	 * @since 2.10.6
	 */
	public function test_exception_injection_does_not_render_extra_directives() {
		$config = new Config();
		$config->set( 'browsercache.no404wp', true );
		$raw = array(
			"robots\\.txt\n    Header set X-Injected \"pwn\"",
			'sitemap\.xml',
		);
		$this->force_config_value( $config, 'browsercache.no404wp.exceptions', $raw );

		$mime = $this->mime_fixture();

		$apache = ( new BrowserCache_Environment_Apache( $config ) )->rules_no404wp( $mime );
		$nginx  = $this->nginx_cache_fragment( $config );

		$this->assertDoesNotMatchRegularExpression( '/^[\t ]*Header set /m', $apache );
		$this->assertSame( 1, substr_count( $apache, 'RewriteCond %{REQUEST_URI} !(' ) );
		$this->assertDoesNotMatchRegularExpression(
			'/RewriteCond %{REQUEST_URI} !\([^\n\)]*\n/',
			$apache,
			'Newline must not appear inside the RewriteCond exception group.'
		);
		$this->assertDoesNotMatchRegularExpression( '/^[\t ]*(Header set|server\s*\{)/m', $nginx );
		$this->assertDoesNotMatchRegularExpression(
			'/location ~ \([^\n\)]*\n/',
			$nginx,
			'Newline must not appear inside the Nginx location regex group.'
		);
	}

	/**
	 * Unsupported security-directive enums do not emit header lines.
	 *
	 * @since 2.10.6
	 */
	public function test_unsupported_security_enum_does_not_render() {
		$config = new Config();
		$config->set( 'browsercache.hsts', true );
		$config->set( 'browsercache.security.xss', true );
		$config->set( 'browsercache.other.lifetime', 31536000 );

		$this->force_config_value( $config, 'browsercache.security.hsts.directive', "maxage\nevil" );
		$this->force_config_value( $config, 'browsercache.security.xss.directive', "block\nHeader set X-Evil \"1\"" );

		$nginx = implode( "\n", $this->nginx_security_rules( $config ) );
		$this->assertStringNotContainsString( 'Strict-Transport-Security', $nginx );
		$this->assertStringNotContainsString( 'X-XSS-Protection', $nginx );
		$this->assertStringNotContainsString( 'X-Evil', $nginx );
	}

	/**
	 * Known security enums render the same tokens on both platforms.
	 *
	 * @since 2.10.6
	 */
	public function test_supported_security_enums_render_compatibly() {
		$config = new Config();
		$config->set( 'browsercache.hsts', true );
		$config->set( 'browsercache.security.hsts.directive', 'maxageincpre' );
		$config->set( 'browsercache.security.xss', true );
		$config->set( 'browsercache.security.xss.directive', 'block' );
		$config->set( 'browsercache.security.xcto', true );
		$config->set( 'browsercache.other.lifetime', 31536000 );

		$nginx = implode( "\n", $this->nginx_security_rules( $config ) );
		$this->assertStringContainsString( 'Strict-Transport-Security', $nginx );
		$this->assertStringContainsString( 'includeSubDomains', $nginx );
		$this->assertStringContainsString( 'preload', $nginx );
		$this->assertStringContainsString( 'X-XSS-Protection "1; mode=block"', $nginx );
		$this->assertStringContainsString( 'X-Content-Type-Options "nosniff"', $nginx );
	}

	/**
	 * Minimal mime-type map accepted by Nginx generate().
	 *
	 * @since 2.10.6
	 *
	 * @return array<string, array<string, string>>
	 */
	private function mime_fixture(): array {
		return array(
			'cssjs'             => array( 'css' => 'text/css' ),
			'html'              => array( 'html' => 'text/html' ),
			'other'             => array( 'png' => 'image/png' ),
			'other_compression' => array( 'css' => 'text/css' ),
		);
	}

	/**
	 * Extracts Nginx browsercache rules (includes no404wp location).
	 *
	 * @since 2.10.6
	 *
	 * @param Config $config Config under test.
	 *
	 * @return string
	 */
	private function nginx_cache_fragment( Config $config ): string {
		return ( new BrowserCache_Environment_Nginx( $config ) )->generate( $this->mime_fixture() );
	}

	/**
	 * Invokes Nginx security_rules() via reflection.
	 *
	 * @since 2.10.6
	 *
	 * @param Config $config Config under test.
	 *
	 * @return array<int, string>
	 */
	private function nginx_security_rules( Config $config ): array {
		$env = new BrowserCache_Environment_Nginx( $config );
		$ref = new ReflectionMethod( BrowserCache_Environment_Nginx::class, 'security_rules' );
		$ref->setAccessible( true );

		return $ref->invoke( $env );
	}

	/**
	 * Overwrites a config key in memory without Config::set() stripping.
	 *
	 * @since 2.10.6
	 *
	 * @param Config $config Config instance.
	 * @param string $key    Dotted key.
	 * @param mixed  $value  Raw value.
	 *
	 * @return void
	 */
	private function force_config_value( Config $config, string $key, $value ): void {
		$ref = new ReflectionProperty( Config::class, '_data' );
		$ref->setAccessible( true );
		$data         = $ref->getValue( $config );
		$data[ $key ] = $value;
		$ref->setValue( $config, $data );
	}
}
