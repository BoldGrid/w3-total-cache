<?php
/**
 * Tests page cache sitemap preload scheduling modes.
 *
 * @package W3TC\Tests
 * @since   X.X.X
 */

use W3TC\Dispatcher;
use W3TC\PgCache_Environment;
use W3TC\PgCache_Plugin;
use W3TC\PgCache_Plugin_Admin;

/**
 * Minimal configuration for page cache preload tests.
 */
class W3TC_PgCache_Preload_Config_Stub {
	/**
	 * Configuration values.
	 *
	 * @var array
	 */
	private $values;

	/**
	 * Initializes configuration values.
	 *
	 * @param array $values Configuration values.
	 */
	public function __construct( $values ) {
		$this->values = $values;
	}

	/**
	 * Returns a boolean value.
	 *
	 * @param string $key Configuration key.
	 *
	 * @return bool
	 */
	public function get_boolean( $key ) {
		return (bool) ( $this->values[ $key ] ?? false );
	}

	/**
	 * Returns an integer value.
	 *
	 * @param string $key Configuration key.
	 *
	 * @return int
	 */
	public function get_integer( $key ) {
		return (int) ( $this->values[ $key ] ?? 0 );
	}

	/**
	 * Returns a string value.
	 *
	 * @param string $key Configuration key.
	 *
	 * @return string
	 */
	public function get_string( $key ) {
		return (string) ( $this->values[ $key ] ?? '' );
	}
}

/**
 * Admin component with a deterministic sitemap.
 */
class W3TC_PgCache_Preload_Admin_Stub extends PgCache_Plugin_Admin {
	/**
	 * Sitemap URLs.
	 *
	 * @var array
	 */
	public $urls = array();

	/**
	 * Returns configured test URLs.
	 *
	 * @param string      $w3tc_url  Sitemap URL.
	 * @param string|null $origin_host Root sitemap host.
	 * @param int         $depth Current recursion depth.
	 *
	 * @return array
	 */
	public function parse_sitemap( $w3tc_url, $origin_host = null, $depth = 0 ) {
		return $this->urls;
	}
}

/**
 * Records preload callback invocations.
 */
class W3TC_PgCache_Preload_Callback_Stub {
	/**
	 * Number of calls.
	 *
	 * @var int
	 */
	public $calls = 0;

	/**
	 * Whether the pass is complete.
	 *
	 * @var bool
	 */
	public $completed = true;

	/**
	 * Records and completes a preload batch.
	 *
	 * @return bool
	 */
	public function prime() {
		++$this->calls;
		return $this->completed;
	}
}

/**
 * Page cache preload scheduling tests.
 */
class W3TC_PgCache_Preload_One_Pass_Test extends WP_UnitTestCase {
	/**
	 * Original Dispatcher instances.
	 *
	 * @var array
	 */
	private $dispatcher_instances;

	/**
	 * Prepares cron and option state.
	 */
	public function set_up() {
		parent::set_up();

		$instances = new ReflectionProperty( Dispatcher::class, 'instances' );
		$instances->setAccessible( true );

		$this->dispatcher_instances = $instances->getValue();

		wp_clear_scheduled_hook( 'w3_pgcache_prime' );
		delete_option( PgCache_Plugin_Admin::PRIME_OFFSET_OPTION );
		delete_option( PgCache_Plugin_Admin::PRIME_COMPLETED_OPTION );

		add_filter(
			'cron_schedules',
			function ( $schedules ) {
				$schedules['w3_pgcache_prime'] = array(
					'interval' => 900,
					'display'  => 'Page cache preload test',
				);
				return $schedules;
			}
		);
	}

	/**
	 * Restores shared state.
	 */
	public function tear_down() {
		$instances = new ReflectionProperty( Dispatcher::class, 'instances' );
		$instances->setAccessible( true );
		$instances->setValue( null, $this->dispatcher_instances );

		wp_clear_scheduled_hook( 'w3_pgcache_prime' );
		delete_option( PgCache_Plugin_Admin::PRIME_OFFSET_OPTION );
		delete_option( PgCache_Plugin_Admin::PRIME_COMPLETED_OPTION );

		parent::tear_down();
	}

	/**
	 * Verifies batch completion and offset wrapping.
	 */
	public function test_admin_prime_reports_sitemap_pass_completion() {
		$admin       = new W3TC_PgCache_Preload_Admin_Stub();
		$admin->urls = array(
			'https://example.org/one',
			'https://example.org/two',
			'https://example.org/three',
		);

		$this->set_private_config(
			$admin,
			PgCache_Plugin_Admin::class,
			new W3TC_PgCache_Preload_Config_Stub(
				array(
					'pgcache.prime.interval' => 60,
					'pgcache.prime.limit'    => 2,
					'pgcache.prime.sitemap'  => 'https://example.org/sitemap.xml',
				)
			)
		);

		add_filter(
			'pre_http_request',
			function () {
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
			}
		);

		$this->assertFalse( $admin->prime() );
		$this->assertSame( 2, get_option( PgCache_Plugin_Admin::PRIME_OFFSET_OPTION ) );
		$this->assertTrue( $admin->prime() );
		$this->assertSame( 0, get_option( PgCache_Plugin_Admin::PRIME_OFFSET_OPTION ) );
	}

	/**
	 * Verifies the default continuous mode keeps its cron event.
	 */
	public function test_continuous_mode_keeps_scheduled_cycles() {
		$callback = new W3TC_PgCache_Preload_Callback_Stub();
		$plugin   = $this->plugin_for_mode( false, $callback );

		wp_schedule_event( time(), 'w3_pgcache_prime', 'w3_pgcache_prime' );
		$plugin->prime();

		$this->assertSame( 1, $callback->calls );
		$this->assertNotFalse( wp_next_scheduled( 'w3_pgcache_prime' ) );
		$this->assertFalse( get_option( PgCache_Plugin_Admin::PRIME_COMPLETED_OPTION, false ) );
	}

	/**
	 * Verifies one-pass mode stops and ignores any later queued callback.
	 */
	public function test_one_pass_mode_stops_after_completion() {
		$callback = new W3TC_PgCache_Preload_Callback_Stub();
		$plugin   = $this->plugin_for_mode( true, $callback );

		wp_schedule_event( time(), 'w3_pgcache_prime', 'w3_pgcache_prime' );
		$plugin->prime();

		$this->assertSame( 1, $callback->calls );
		$this->assertFalse( wp_next_scheduled( 'w3_pgcache_prime' ) );
		$this->assertTrue( get_option( PgCache_Plugin_Admin::PRIME_COMPLETED_OPTION ) );

		$plugin->prime();
		$this->assertSame( 1, $callback->calls );
	}

	/**
	 * Verifies environment repair does not recreate a completed one-pass event.
	 */
	public function test_completed_one_pass_is_not_rescheduled() {
		update_option( PgCache_Plugin_Admin::PRIME_COMPLETED_OPTION, true, false );

		$environment = new PgCache_Environment();
		$environment->fix_on_event( $this->environment_config( true, true ), 'admin_request' );

		$this->assertFalse( wp_next_scheduled( 'w3_pgcache_prime' ) );
	}

	/**
	 * Verifies disabling and re-enabling starts from the first sitemap entry.
	 */
	public function test_reenable_resets_and_schedules_new_pass() {
		update_option( PgCache_Plugin_Admin::PRIME_OFFSET_OPTION, 7, false );
		update_option( PgCache_Plugin_Admin::PRIME_COMPLETED_OPTION, true, false );

		$environment = new PgCache_Environment();
		$environment->fix_on_event(
			$this->environment_config( true, true ),
			'config_change',
			$this->environment_config( false, true )
		);

		$this->assertSame( 0, get_option( PgCache_Plugin_Admin::PRIME_OFFSET_OPTION ) );
		$this->assertFalse( get_option( PgCache_Plugin_Admin::PRIME_COMPLETED_OPTION, false ) );
		$this->assertNotFalse( wp_next_scheduled( 'w3_pgcache_prime' ) );
	}

	/**
	 * Creates a plugin with a test callback and mode.
	 *
	 * @param bool                               $one_pass One-pass mode.
	 * @param W3TC_PgCache_Preload_Callback_Stub $callback Preload callback.
	 *
	 * @return PgCache_Plugin
	 */
	private function plugin_for_mode( $one_pass, $callback ) {
		$plugin = new PgCache_Plugin();
		$this->set_private_config(
			$plugin,
			PgCache_Plugin::class,
			new W3TC_PgCache_Preload_Config_Stub(
				array(
					'pgcache.prime.sitemap_one_pass' => $one_pass,
				)
			)
		);

		$instances = new ReflectionProperty( Dispatcher::class, 'instances' );
		$instances->setAccessible( true );
		$value                         = $instances->getValue();
		$value['PgCache_Plugin_Admin'] = $callback;
		$instances->setValue( null, $value );

		return $plugin;
	}

	/**
	 * Creates environment configuration.
	 *
	 * @param bool $prime_enabled Preload enabled.
	 * @param bool $one_pass One-pass mode.
	 *
	 * @return W3TC_PgCache_Preload_Config_Stub
	 */
	private function environment_config( $prime_enabled, $one_pass ) {
		return new W3TC_PgCache_Preload_Config_Stub(
			array(
				'pgcache.enabled'                => true,
				'pgcache.engine'                 => 'redis',
				'pgcache.prime.enabled'          => $prime_enabled,
				'pgcache.prime.interval'         => 900,
				'pgcache.prime.sitemap'          => 'https://example.org/sitemap.xml',
				'pgcache.prime.sitemap_one_pass' => $one_pass,
			)
		);
	}

	/**
	 * Replaces a private configuration property.
	 *
	 * @param object $target_object Object to update.
	 * @param string $declaring_class Declaring class.
	 * @param object $config Configuration stub.
	 *
	 * @return void
	 */
	private function set_private_config( $target_object, $declaring_class, $config ) {
		$property = new ReflectionProperty( $declaring_class, '_config' );
		$property->setAccessible( true );
		$property->setValue( $target_object, $config );
	}
}
