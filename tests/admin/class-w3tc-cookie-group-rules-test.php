<?php
/**
 * File: class-w3tc-cookie-group-rules-test.php
 *
 * @package    W3TC
 * @subpackage W3TC/tests/admin
 */

declare( strict_types = 1 );

use W3TC\Config;
use W3TC\Dispatcher;
use W3TC\PgCache_Environment;

/**
 * Cookie-group rule generation must match the PHP-side cookie matcher.
 *
 * PgCache_ContentGrabber treats each configured cookie entry as a regex.
 * The generated Apache and Nginx rules must use the same regex text so the
 * web server and PHP pick the same cookie group.
 */
class W3tc_Cookie_Group_Rules_Test extends WP_UnitTestCase {

	/**
	 * Saved config values restored after each test.
	 *
	 * @var array
	 */
	private $saved = array();

	/**
	 * Remember the cookie-group settings this test changes.
	 */
	public function set_up() {
		parent::set_up();

		$config = Dispatcher::config();
		foreach ( array( 'pgcache.cookiegroups.enabled', 'pgcache.cookiegroups.groups' ) as $key ) {
			$this->saved[ $key ] = $config->get( $key );
		}
	}

	/**
	 * Restore the cookie-group settings.
	 */
	public function tear_down() {
		$config = Dispatcher::config();
		foreach ( $this->saved as $key => $value ) {
			$config->set( $key, $value );
		}

		parent::tear_down();
	}

	/**
	 * Set one enabled cookie group with the given entries.
	 *
	 * @param array $cookies Cookie entries, as typed in the settings page.
	 *
	 * @return Config
	 */
	private function config_with_group( array $cookies ) {
		$config = Dispatcher::config();
		$config->set( 'pgcache.cookiegroups.enabled', true );
		$config->set(
			'pgcache.cookiegroups.groups',
			array(
				'custom' => array(
					'enabled' => true,
					'cache'   => true,
					'cookies' => $cookies,
				),
			)
		);

		return $config;
	}

	/**
	 * Call a private rule generator.
	 *
	 * @param string $method Method name.
	 * @param Config $config Config to render.
	 *
	 * @return string Generated rules.
	 */
	private function generate( string $method, Config $config ): string {
		$class    = new ReflectionClass( PgCache_Environment::class );
		$instance = $class->newInstanceWithoutConstructor();
		$method   = new ReflectionMethod( PgCache_Environment::class, $method );
		if ( PHP_VERSION_ID < 80100 ) {
			$method->setAccessible( true );
		}

		return (string) $method->invoke( $instance, $config );
	}

	/**
	 * Apache rules keep a regex cookie entry as a regex.
	 */
	public function test_apache_regex_cookie_entry_is_not_escaped() {
		$config = $this->config_with_group( array( 'cookie_.*_prefs=a.*' ) );
		$rules  = $this->generate( 'rules_core_generate_apache', $config );

		$this->assertStringContainsString( '(cookie_.*_prefs=a.*)', $rules );
		$this->assertStringNotContainsString( 'cookie_\.\*_prefs', $rules );
	}

	/**
	 * Nginx rules keep a regex cookie entry as a regex.
	 */
	public function test_nginx_regex_cookie_entry_is_not_escaped() {
		$config = $this->config_with_group( array( 'cookie_.*_prefs=a.*' ) );
		$rules  = $this->generate( 'rules_core_generate_nginx', $config );

		$this->assertStringContainsString( '"^(.*;)?(cookie_.*_prefs=a.*)(;.*)?$"', $rules );
		$this->assertStringNotContainsString( 'cookie_\.\*_prefs', $rules );
	}

	/**
	 * A regex `$` followed by a name is not expanded as an nginx variable.
	 */
	public function test_nginx_dollar_before_name_is_not_expanded() {
		$config = $this->config_with_group( array( 'x$name=1' ) );
		$rules  = $this->generate( 'rules_core_generate_nginx', $config );

		$this->assertStringContainsString( 'x\$name=1', $rules );
	}

	/**
	 * The generated Apache pattern matches the cookie the PHP matcher matches.
	 */
	public function test_generated_pattern_matches_the_same_cookie_as_php() {
		$config = $this->config_with_group( array( 'cookie_.*_prefs=a.*' ) );
		$rules  = $this->generate( 'rules_core_generate_apache', $config );

		$this->assertSame( 1, preg_match( '~^(.*;\s*)?(cookie_.*_prefs=a.*)(\s*;.*)?$~i', 'cookie_1_prefs=a1' ) );
		$this->assertStringContainsString( 'RewriteCond %{HTTP_COOKIE} ^(.*;\s*)?(cookie_.*_prefs=a.*)(\s*;.*)?$ [NC]', $rules );
		$this->assertSame(
			1,
			preg_match( '~(cookie_.*_prefs=a.*)~', 'cookie_1_prefs=a1' ),
			'The regex text in the rule must match the PHP-side cookie.'
		);
	}

	/**
	 * The built-in logged-in pattern is generated as a regex.
	 */
	public function test_logged_in_group_pattern_is_a_regex() {
		$config = $this->config_with_group( array( 'wordpress_logged_in_.*' ) );
		$rules  = $this->generate( 'rules_core_generate_apache', $config );

		$this->assertStringContainsString( '(wordpress_logged_in_.*=.*)', $rules );
	}

	/**
	 * Plain cookie entries still match as before.
	 */
	public function test_plain_cookie_entries_are_unchanged() {
		$config = $this->config_with_group( array( 'cookie=value', 'role=member' ) );
		$rules  = $this->generate( 'rules_core_generate_apache', $config );

		$this->assertStringContainsString( '(cookie=value|role=member)', $rules );
	}

	/**
	 * A space in a cookie name is still escaped so the rule line stays intact.
	 */
	public function test_space_in_cookie_entry_is_escaped() {
		$config = $this->config_with_group( array( 'my cookie=v' ) );
		$rules  = $this->generate( 'rules_core_generate_apache', $config );

		$this->assertStringContainsString( '(my\ cookie=v)', $rules );
	}

	/**
	 * An entry that does not compile as a regex is left out, not written as a broken rule.
	 */
	public function test_invalid_regex_entry_is_dropped() {
		$config = $this->config_with_group( array( 'bad[', 'good=1' ) );
		$rules  = $this->generate( 'rules_core_generate_apache', $config );

		$this->assertStringNotContainsString( 'bad[', $rules );
		$this->assertStringContainsString( '(good=1)', $rules );
	}

	/**
	 * Directive injection through a cookie entry stays blocked.
	 */
	public function test_newline_injection_in_cookie_entry_is_stripped() {
		$config = $this->config_with_group( array( "ok=1\nForceType application/x-httpd-php" ) );
		$rules  = $this->generate( 'rules_core_generate_apache', $config );

		$this->assertSame( 0, preg_match( '/^\s*ForceType/m', $rules ) );
	}
}
