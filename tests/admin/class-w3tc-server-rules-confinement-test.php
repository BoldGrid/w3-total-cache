<?php
/**
 * File: class-w3tc-server-rules-confinement-test.php
 *
 * @package    W3TC
 * @subpackage W3TC/tests/admin
 * @since      2.11.1
 */

declare( strict_types = 1 );

use W3TC\BrowserCache_Environment_Apache;
use W3TC\BrowserCache_RuleDirectives;
use W3TC\Config;
use W3TC\ConfigKeysSchema;
use W3TC\Dispatcher;
use W3TC\PgCache_ContentGrabber;
use W3TC\PgCache_Environment;
use W3TC\Util_Environment;
use W3TC\Util_Rule;

/**
 * Class: W3tc_Server_Rules_Confinement_Test
 *
 * Server-rules path confinement, accepted query-string and no404wp
 * rule rendering, and frontend rules-restore gating.
 *
 * @since 2.11.1
 */
class W3tc_Server_Rules_Confinement_Test extends WP_UnitTestCase {

	/**
	 * Paths created during a test, removed in tear_down().
	 *
	 * @var array<int, string>
	 */
	private $cleanup = array();

	/**
	 * Original SERVER_SOFTWARE value.
	 *
	 * @var string|null
	 */
	private $server_software = null;

	/**
	 * Original Dispatcher instances.
	 *
	 * @var array|null
	 */
	private $instances = null;

	/**
	 * Saves global state.
	 *
	 * @return void
	 */
	public function set_up() {
		parent::set_up();
		$this->server_software = isset( $_SERVER['SERVER_SOFTWARE'] ) ? $_SERVER['SERVER_SOFTWARE'] : null; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		$this->instances       = $this->dispatcher_instances()->getValue();
	}

	/**
	 * Restores global state and removes temp paths.
	 *
	 * @return void
	 */
	public function tear_down() {
		foreach ( array_reverse( $this->cleanup ) as $path ) {
			if ( is_link( $path ) || is_file( $path ) ) {
				unlink( $path );
			} elseif ( is_dir( $path ) ) {
				rmdir( $path );
			}
		}
		$this->cleanup = array();

		if ( null === $this->server_software ) {
			unset( $_SERVER['SERVER_SOFTWARE'] );
		} else {
			$_SERVER['SERVER_SOFTWARE'] = $this->server_software;
		}

		$this->dispatcher_instances()->setValue( null, $this->instances );
		wp_set_current_user( 0 );

		parent::tear_down();
	}

	/**
	 * Out-of-site config includes are rejected.
	 *
	 * @since 2.11.1
	 */
	public function test_rules_path_rejects_paths_outside_site() {
		$this->assertFalse( $this->is_valid_path( '/etc/nginx/conf.d/w3tc.conf' ) );
		$this->assertFalse( $this->is_valid_path( '/etc/w3tc.conf' ) );
		$this->assertFalse( $this->is_valid_path( sys_get_temp_dir() . '/w3tc-outside.conf' ) );
		$this->assertFalse( $this->is_valid_path( '/nonexistent-dir-w3tc/w3tc.conf' ) );
	}

	/**
	 * Paths under ABSPATH or the cache directory are accepted.
	 *
	 * @since 2.11.1
	 */
	public function test_rules_path_accepts_site_and_cache_dir() {
		$this->assertTrue( $this->is_valid_path( ABSPATH . 'nginx.conf' ) );
		$this->assertTrue( $this->is_valid_path( ABSPATH . 'W3TC.CONF' ) );

		$cache_dir = $this->ensure_cache_dir();
		$this->assertTrue( $this->is_valid_path( $cache_dir . '/nginx.conf' ) );
	}

	/**
	 * Existing extension and traversal rules still apply inside the site.
	 *
	 * @since 2.11.1
	 */
	public function test_rules_path_keeps_extension_and_traversal_checks() {
		$this->assertFalse( $this->is_valid_path( ABSPATH . 'shell.php' ) );
		$this->assertFalse( $this->is_valid_path( ABSPATH . 'wp-content/../x.conf' ) );
		$this->assertFalse( $this->is_valid_path( ABSPATH . "shell.php\0.conf" ) );
		$this->assertFalse( $this->is_valid_path( '' ) );
		$this->assertFalse( $this->is_valid_path( array( 'x' ) ) );
	}

	/**
	 * A symlinked parent that resolves outside the allowed roots is rejected.
	 *
	 * @since 2.11.1
	 */
	public function test_rules_path_rejects_symlink_escape() {
		$cache_dir = $this->ensure_cache_dir();
		$outside   = sys_get_temp_dir() . '/w3tc-confine-' . getmypid() . '-' . wp_rand();
		$this->assertTrue( mkdir( $outside ) );
		$this->cleanup[] = $outside;

		$link = $cache_dir . '/w3tc-confine-link-' . wp_rand();
		if ( ! @symlink( $outside, $link ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			$this->markTestSkipped( 'symlink() unavailable.' );
		}
		$this->cleanup[] = $link;

		$this->assertFalse( $this->is_valid_path( $link . '/w3tc.conf' ) );
	}

	/**
	 * The nginx rules path falls back to the site default for an outside path.
	 *
	 * @since 2.11.1
	 */
	public function test_nginx_rules_path_falls_back_for_outside_path() {
		$config   = Dispatcher::config();
		$original = $config->get_string( 'config.path' );

		$config->set( 'config.path', '/etc/nginx/conf.d/w3tc.conf' );
		$path = Util_Rule::get_nginx_rules_path();
		$config->set( 'config.path', $original );

		$this->assertSame( Util_Environment::site_path() . 'nginx.conf', $path );
	}

	/**
	 * The accepted query-string key carries the directive_string flag.
	 *
	 * @since 2.11.1
	 */
	public function test_accept_qs_is_directive_string_key() {
		$this->assertTrue( ConfigKeysSchema::is_directive_string_key( 'pgcache.accept.qs' ) );

		$config = new Config();
		$config->set( 'pgcache.accept.qs', array( "lang\nAddHandler x .jpg", "\r\n", 'utm_source' ) );

		$this->assertSame(
			array( 'langAddHandler x .jpg', 'utm_source' ),
			$config->get_array( 'pgcache.accept.qs' )
		);
	}

	/**
	 * Unsanitised stored accepted query strings cannot add Apache directives.
	 *
	 * @since 2.11.1
	 */
	public function test_accept_qs_apache_rules_are_sanitised() {
		$rules = $this->generate_core_rules( 'rules_core_generate_apache' );

		$this->assertDoesNotMatchRegularExpression( '/^[\t ]*AddHandler/mi', $rules );
		$this->assertStringNotContainsString( "\t", $rules );
		$this->assertStringNotContainsString( '"', $this->accept_qs_lines( $rules, 'RewriteCond %{ENV:W3TC_QUERY_STRING} ^(.*?&|)' ) );
		$this->assertStringNotContainsString( '^(.*?&|)(=[^&]*)?', $rules, 'Empty entries must be dropped.' );
		$this->assertStringContainsString( 'utm_source', $rules );
	}

	/**
	 * Unsanitised stored accepted query strings cannot add Nginx directives.
	 *
	 * @since 2.11.1
	 */
	public function test_accept_qs_nginx_rules_are_sanitised() {
		$rules = $this->generate_core_rules( 'rules_core_generate_nginx' );

		$this->assertDoesNotMatchRegularExpression( '/^[\t ]*AddHandler/mi', $rules );
		$this->assertStringNotContainsString( "\t", $rules );
		$this->assertStringNotContainsString( '"); }', $rules );
		$this->assertStringNotContainsString( '^(.*?&|)(=[^&]*)?', $rules, 'Empty entries must be dropped.' );
		$this->assertStringContainsString( 'utm_source', $rules );
	}

	/**
	 * The no404wp RewriteCond pattern is quoted.
	 *
	 * @since 2.11.1
	 */
	public function test_no404wp_rewritecond_pattern_is_quoted() {
		$config = new Config();
		$config->set( 'browsercache.no404wp', true );
		$config->set( 'browsercache.no404wp.exceptions', array( 'robots\.txt', 'my file\.pdf' ) );

		$apache = ( new BrowserCache_Environment_Apache( $config ) )->rules_no404wp( $this->mime_fixture() );

		$this->assertStringContainsString(
			'RewriteCond %{REQUEST_URI} "!(my file\.pdf|robots\.txt)"' . "\n",
			$apache
		);
	}

	/**
	 * Entries that would close the exception group are dropped.
	 *
	 * @since 2.11.1
	 */
	public function test_no404wp_structural_entries_are_dropped() {
		$out = BrowserCache_RuleDirectives::normalize_exceptions(
			array(
				'foo) [OR',
				'bar) #comment',
				'trailing\\',
				'a)|(b',
				'ok\.txt',
				'[a-z0-9_\-]*sitemap[a-z0-9_\.\-]*\.(xml|xsl|html)(\.gz)?',
			)
		);

		$this->assertSame(
			array( '[a-z0-9_\-]*sitemap[a-z0-9_\.\-]*\.(xml|xsl|html)(\.gz)?', 'ok\.txt' ),
			$out
		);

		$config = new Config();
		$config->set( 'browsercache.no404wp', true );
		$this->force_config_value( $config, 'browsercache.no404wp.exceptions', array( 'foo) [OR', 'ok\.txt' ) );

		$apache = ( new BrowserCache_Environment_Apache( $config ) )->rules_no404wp( $this->mime_fixture() );
		$this->assertStringNotContainsString( '[OR', $apache );
		$this->assertStringContainsString( 'RewriteCond %{REQUEST_URI} "!(ok\.txt)"', $apache );
	}

	/**
	 * An anonymous frontend cache miss does not restore rules.
	 *
	 * @since 2.11.1
	 */
	public function test_check_rules_present_skips_anonymous_request() {
		$spy = $this->install_env_spy();
		wp_set_current_user( 0 );

		( new PgCache_ContentGrabber() )->_check_rules_present();

		$this->assertSame( 0, $spy->fix_calls );
	}

	/**
	 * A logged-in user without manage_options does not restore rules.
	 *
	 * @since 2.11.1
	 */
	public function test_check_rules_present_skips_non_admin() {
		$spy = $this->install_env_spy();
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'subscriber' ) ) );

		( new PgCache_ContentGrabber() )->_check_rules_present();

		$this->assertSame( 0, $spy->fix_calls );
	}

	/**
	 * An administrator request still restores missing rules.
	 *
	 * @since 2.11.1
	 */
	public function test_check_rules_present_restores_for_admin() {
		$spy = $this->install_env_spy();
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		( new PgCache_ContentGrabber() )->_check_rules_present();

		$this->assertSame( 1, $spy->fix_calls );
	}

	/**
	 * Invokes the private path validator.
	 *
	 * @param mixed $path Candidate path.
	 *
	 * @return bool
	 */
	private function is_valid_path( $path ): bool {
		$ref = new ReflectionMethod( Util_Rule::class, 'is_valid_custom_rules_path' );
		$ref->setAccessible( true );

		return (bool) $ref->invoke( null, $path );
	}

	/**
	 * Ensures W3TC_CACHE_DIR exists and returns its path.
	 *
	 * @return string
	 */
	private function ensure_cache_dir(): string {
		if ( ! is_dir( W3TC_CACHE_DIR ) ) {
			$this->assertTrue( wp_mkdir_p( W3TC_CACHE_DIR ) );
			$this->cleanup[] = W3TC_CACHE_DIR;
		}

		return rtrim( W3TC_CACHE_DIR, '/' );
	}

	/**
	 * Renders core page-cache rules with hostile stored accept.qs values.
	 *
	 * @param string $method Private renderer method name.
	 *
	 * @return string
	 */
	private function generate_core_rules( string $method ): string {
		$config = new Config();
		$config->set( 'pgcache.enabled', true );
		$config->set( 'pgcache.engine', 'file_generic' );
		$this->force_config_value(
			$config,
			'pgcache.accept.qs',
			array(
				"lang\nAddHandler application/x-httpd-php .jpg",
				"a\tAddHandler",
				'q"); } x {',
				"\r\n",
				'utm_source',
			)
		);

		$env = new PgCache_Environment();
		$ref = new ReflectionMethod( PgCache_Environment::class, $method );
		$ref->setAccessible( true );

		return (string) $ref->invoke( $env, $config );
	}

	/**
	 * Returns rule lines that start with a prefix.
	 *
	 * @param string $rules  Rendered rules.
	 * @param string $prefix Line prefix (after indentation).
	 *
	 * @return string
	 */
	private function accept_qs_lines( string $rules, string $prefix ): string {
		$out = array();
		foreach ( explode( "\n", $rules ) as $line ) {
			if ( 0 === strpos( ltrim( $line ), $prefix ) ) {
				$out[] = $line;
			}
		}

		return implode( "\n", $out );
	}

	/**
	 * Replaces the PgCache_Environment component with a spy that reports
	 * rules as required and counts restore calls instead of writing.
	 *
	 * @return PgCache_Environment
	 */
	private function install_env_spy(): PgCache_Environment {
		$_SERVER['SERVER_SOFTWARE'] = 'Apache';

		$filename = Util_Rule::get_pgcache_rules_cache_path();
		if ( $filename && file_exists( $filename ) ) {
			$this->markTestSkipped( 'Page cache rules file already present.' );
		}

		$spy = new class() extends PgCache_Environment {
			/**
			 * Number of fix_on_wpadmin_request() calls.
			 *
			 * @var int
			 */
			public $fix_calls = 0;

			/**
			 * Always reports that rules are required.
			 *
			 * @param Config $w3tc_config Config.
			 *
			 * @return array
			 */
			public function get_required_rules( $w3tc_config ) {
				return array(
					array(
						'filename' => 'x',
						'content'  => 'y',
					),
				);
			}

			/**
			 * Records the call instead of writing rules.
			 *
			 * @param Config $w3tc_config      Config.
			 * @param bool   $force_all_checks Force flag.
			 *
			 * @return void
			 */
			public function fix_on_wpadmin_request( $w3tc_config, $force_all_checks ) {
				++$this->fix_calls;
			}
		};

		$instances                        = $this->dispatcher_instances()->getValue();
		$instances['PgCache_Environment'] = $spy;
		$this->dispatcher_instances()->setValue( null, $instances );

		return $spy;
	}

	/**
	 * Returns an accessible reflection of Dispatcher::$instances.
	 *
	 * @return ReflectionProperty
	 */
	private function dispatcher_instances(): ReflectionProperty {
		$ref = new ReflectionProperty( Dispatcher::class, 'instances' );
		$ref->setAccessible( true );

		return $ref;
	}

	/**
	 * Minimal mime-type map for the no404wp renderer.
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
	 * Overwrites a config key in memory without Config::set() stripping.
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
