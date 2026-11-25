<?php
/**
 * File: class-w3tc-cookie-group-rules-test.php
 *
 * Cookie cache groups must select the same group in PHP and in the
 * Disk: Enhanced rewrite rules. PHP returns on the first match.
 *
 * @package    W3TC
 * @subpackage W3TC/tests/admin
 * @since      2.11.0
 */

declare( strict_types = 1 );

use W3TC\Config;
use W3TC\PgCache_Environment;

/**
 * Class: W3tc_Cookie_Group_Rules_Test
 *
 * @since 2.11.0
 */
class W3tc_Cookie_Group_Rules_Test extends WP_UnitTestCase {

	/**
	 * Two enabled cookie groups, in PHP's iteration order.
	 *
	 * @return array
	 */
	private function cookie_groups() {
		return array(
			'cookie1a' => array(
				'enabled' => true,
				'cache'   => true,
				'cookies' => array( 'cookie1=a' ),
			),
			'cookie2b' => array(
				'enabled' => true,
				'cache'   => true,
				'cookies' => array( 'cookie2=b' ),
			),
		);
	}

	/**
	 * Config with page-cache cookie groups enabled.
	 *
	 * @return Config
	 */
	private function config_with_groups() {
		$config = new Config();
		$config->set( 'pgcache.enabled', true );
		$config->set( 'pgcache.engine', 'file_generic' );
		$config->set( 'pgcache.cookiegroups.enabled', true );
		$config->set( 'pgcache.cookiegroups.groups', $this->cookie_groups() );

		return $config;
	}

	/**
	 * Invoke a private rule generator.
	 *
	 * @param string $method Method name.
	 * @param Config $config Configuration.
	 *
	 * @return string
	 */
	private function rules( $method, Config $config ) {
		$environment = new PgCache_Environment();
		$generator   = new ReflectionMethod( PgCache_Environment::class, $method );
		$generator->setAccessible( true );

		return $generator->invoke( $environment, $config );
	}

	/**
	 * Apache sets the env var only while it is still empty, first group first.
	 *
	 * @return void
	 */
	public function test_apache_cookie_groups_keep_the_first_match() {
		$rules = $this->rules( 'rules_core_generate_apache', $this->config_with_groups() );

		$first  = strpos( $rules, 'E=W3TC_COOKIE:_cookie1a' );
		$second = strpos( $rules, 'E=W3TC_COOKIE:_cookie2b' );

		$this->assertNotFalse( $first );
		$this->assertNotFalse( $second );
		$this->assertLessThan( $second, $first );

		$guard = 'RewriteCond %{ENV:W3TC_COOKIE} ^$';
		$this->assertGreaterThanOrEqual( 2, substr_count( $rules, $guard ) );
		$this->assertLessThan( $first, strpos( $rules, $guard ) );
		$this->assertLessThan( $second, strrpos( $rules, $guard ) );
	}

	/**
	 * Nginx keeps the first match for both spaced and unspaced cookie headers.
	 *
	 * @return void
	 */
	public function test_nginx_cookie_groups_keep_the_first_match() {
		$rules = $this->rules( 'rules_core_generate_nginx', $this->config_with_groups() );

		$this->assertNotFalse( strpos( $rules, 'set $w3tc_cookie "";' ) );
		$this->assertStringContainsString( 'if ($w3tc_cookie = "")', $rules );

		$first  = strpos( $rules, 'set $w3tc_cookie _cookie1a;' );
		$second = strpos( $rules, 'set $w3tc_cookie _cookie2b;' );
		$this->assertNotFalse( $first );
		$this->assertNotFalse( $second );
		$this->assertLessThan( $second, $first );

		$spaced   = 'cookie1=a; cookie2=b';
		$unspaced = 'cookie1=a;cookie2=b';
		$only_two = 'cookie1=no; cookie2=b';

		$this->assertSame( 'cookie1a', $this->nginx_group( $rules, $spaced ) );
		$this->assertSame( 'cookie1a', $this->nginx_group( $rules, $unspaced ) );
		$this->assertSame( 'cookie2b', $this->nginx_group( $rules, $only_two ) );
	}

	/**
	 * Apply the generated nginx cookie-group conditions in order.
	 *
	 * @param string $rules  Generated nginx core rules.
	 * @param string $header Cookie header.
	 *
	 * @return string Selected group name, without the leading underscore.
	 */
	private function nginx_group( $rules, $header ) {
		preg_match_all(
			'/set \$w3tc_cookie_candidate "";\s*'
			. 'if \(\$w3tc_cookie = ""\) \{\s*set \$w3tc_cookie_candidate 1;\s*\}\s*'
			. 'if \(\$http_cookie ~\* ("[^"]+")\) \{\s*set \$w3tc_cookie_candidate "\$\{w3tc_cookie_candidate\}1";\s*\}\s*'
			. 'if \(\$w3tc_cookie_candidate = "11"\) \{\s*set \$w3tc_cookie (_[^;]+);/s',
			$rules,
			$matches,
			PREG_SET_ORDER
		);

		$selected = '';
		foreach ( $matches as $match ) {
			$pattern = trim( $match[1], '"' );
			if ( '' === $selected && preg_match( '~' . $pattern . '~i', $header ) ) {
				$selected = $match[2];
			}
		}

		return ltrim( $selected, '_' );
	}
}
