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
use W3TC\PgCache_ContentGrabber;
use W3TC\PgCache_Environment;
use W3TC\Util_Rule;

/**
 * Cookie-group rule generation must match the PHP-side cookie matcher.
 *
 * PgCache_ContentGrabber treats each configured cookie entry as a regex.
 * The generated Apache and Nginx rules must use the same regex text, and keep or
 * drop the same entries, so the web server and PHP pick the same cookie group.
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
	 * An entry that does not compile as a regex is left out of the rules, not written as a broken rule.
	 */
	public function test_invalid_regex_entry_is_dropped() {
		$config = $this->config_with_group( array( 'bad[', 'good=1' ) );
		$rules  = $this->generate( 'rules_core_generate_apache', $config );

		$this->assertStringNotContainsString( 'bad[', $rules );
		$this->assertStringContainsString( '(good=1)', $rules );
	}

	/**
	 * An entry that is empty once sanitised is left out of the rules.
	 */
	public function test_entry_empty_after_sanitising_is_dropped() {
		$config = $this->config_with_group( array( '"', 'good=1' ) );

		foreach ( array( 'rules_core_generate_apache', 'rules_core_generate_nginx' ) as $method ) {
			$rules = $this->generate( $method, $config );

			// An empty alternative would appear as `(|good=1)` and match every cookie.
			$this->assertStringContainsString( '(good=1)', $rules, $method );
		}
	}

	/**
	 * Run the PHP-side cookie matcher against a set of request cookies.
	 *
	 * @param Config $config  Config holding the cookie group.
	 * @param array  $cookies Request cookies, name => value.
	 *
	 * @return array The `$w3tc_extension` array after matching.
	 */
	private function php_match( Config $config, array $cookies ): array {
		$grabber = ( new ReflectionClass( PgCache_ContentGrabber::class ) )->newInstanceWithoutConstructor();

		$property = new ReflectionProperty( PgCache_ContentGrabber::class, '_config' );
		if ( PHP_VERSION_ID < 80100 ) {
			$property->setAccessible( true );
		}
		$property->setValue( $grabber, $config );

		$method = new ReflectionMethod( PgCache_ContentGrabber::class, '_fill_key_extension_cookie' );
		if ( PHP_VERSION_ID < 80100 ) {
			$method->setAccessible( true );
		}

		$saved_cookie = $_COOKIE;
		$_COOKIE      = $cookies;
		$extension    = array();
		try {
			$method->invokeArgs( $grabber, array( &$extension ) );
		} finally {
			$_COOKIE = $saved_cookie;
		}

		return $extension;
	}

	/**
	 * Evaluate the generated Apache cookie condition against a Cookie header.
	 *
	 * @param string $rules   Generated Apache rules.
	 * @param array  $cookies Request cookies, name => value.
	 *
	 * @return bool Whether the cookie-group RewriteCond matches.
	 */
	private function apache_matches( string $rules, array $cookies ): bool {
		$pattern = null;
		foreach ( explode( "\n", $rules ) as $line ) {
			if ( preg_match( '~^\s*RewriteCond %\{HTTP_COOKIE\} (\^\(\.\*;\\\\s\*\)\?\(.*\)\(\\\\s\*;\.\*\)\?\$) \[NC\]$~', $line, $found ) ) {
				$pattern = $found[1];
				break;
			}
		}

		// No condition is generated when every entry is invalid, so the group never matches.
		if ( null === $pattern ) {
			return false;
		}

		$parts = array();
		foreach ( $cookies as $name => $value ) {
			$parts[] = $name . '=' . $value;
		}

		return 1 === preg_match( '~' . $pattern . '~i', implode( '; ', $parts ) );
	}

	/**
	 * One invalid entry does not stop the PHP matcher from using the valid entries.
	 */
	public function test_php_matcher_selects_group_for_valid_entry_next_to_invalid_one() {
		$config = $this->config_with_group( array( 'bad[', 'good=1' ) );

		$extension = $this->php_match( $config, array( 'good' => '1' ) );

		$this->assertSame( 'custom', $extension['cookie'] ?? null );
		$this->assertArrayNotHasKey( 'cache', $extension );
	}

	/**
	 * A group of only invalid entries matches nothing in PHP.
	 */
	public function test_php_matcher_ignores_group_with_only_invalid_entries() {
		$config = $this->config_with_group( array( 'bad[' ) );

		$this->assertSame( array(), $this->php_match( $config, array( 'bad[' => '1', 'good' => '1' ) ) );
	}

	/**
	 * A matching group that disables caching still rejects the page when another entry is invalid.
	 */
	public function test_php_matcher_keeps_cache_rejection_next_to_invalid_entry() {
		$config = $this->config_with_group( array( 'bad[', 'good=1' ) );
		$groups = $config->get( 'pgcache.cookiegroups.groups' );

		$groups['custom']['cache'] = false;
		$config->set( 'pgcache.cookiegroups.groups', $groups );

		$extension = $this->php_match( $config, array( 'good' => '1' ) );

		$this->assertSame( 'custom', $extension['cookie'] );
		$this->assertFalse( $extension['cache'] );
		$this->assertSame( 'cookiegroup custom', $extension['cache_reject_reason'] );
	}

	/**
	 * The shared helper keeps valid entries, adds the default value, and rejects the rest.
	 */
	public function test_cookie_group_entry_helper() {
		$this->assertSame( 'good=1', Util_Rule::cookie_group_entry( 'good=1' ) );
		$this->assertSame( 'wordpress_logged_in_.*=.*', Util_Rule::cookie_group_entry( 'wordpress_logged_in_.*' ) );
		$this->assertNull( Util_Rule::cookie_group_entry( 'bad[' ) );
		$this->assertNull( Util_Rule::cookie_group_entry( 'bad[=1' ) );
		$this->assertNull( Util_Rule::cookie_group_entry( '"' ) );
		$this->assertNull( Util_Rule::cookie_group_entry( '' ) );
	}

	/**
	 * The Apache rule and the PHP matcher pick the group for the same cookie sets.
	 */
	public function test_apache_rules_and_php_matcher_agree() {
		$entry_sets = array(
			array( 'bad[', 'good=1' ),
			array( 'bad[' ),
			array( 'cookie_.*_prefs=a.*', 'role=member' ),
			array( 'wordpress_logged_in_.*', 'x(=1' ),
			array( 'my+cookie=v', 'good' ),
		);
		$cookie_sets = array(
			array( 'good' => '1' ),
			array( 'good' => '2' ),
			array( 'bad[' => '1' ),
			array( 'bad[' => '1', 'good' => '1' ),
			array( 'cookie_1_prefs' => 'a1' ),
			array( 'role' => 'member', 'other' => 'x' ),
			array( 'wordpress_logged_in_abc' => 'tok' ),
			array( 'x(' => '1' ),
			array( 'my cookie' => 'v' ),
			array( 'good' => '', 'unrelated' => '1' ),
			array( 'unrelated' => '1' ),
		);

		foreach ( $entry_sets as $entries ) {
			$config = $this->config_with_group( $entries );
			$rules  = $this->generate( 'rules_core_generate_apache', $config );

			foreach ( $cookie_sets as $cookies ) {
				$php = isset( $this->php_match( $config, $cookies )['cookie'] );

				$this->assertSame(
					$php,
					$this->apache_matches( $rules, $cookies ),
					'Entries ' . wp_json_encode( $entries ) . ' with cookies ' . wp_json_encode( $cookies )
				);
			}
		}
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
