<?php
/**
 * File: class-w3tc-pgcache-cleanup-cron-test.php
 *
 * @package    W3TC
 * @subpackage W3TC/tests/admin
 * @since      X.X.X
 */

declare( strict_types = 1 );

use W3TC\Config;
use W3TC\PgCache_Environment;
use W3TC\Root_Environment;

/**
 * A missing page cache cleanup event is scheduled again on an admin request.
 *
 * @since X.X.X
 */
class W3tc_Pgcache_Cleanup_Cron_Test extends WP_UnitTestCase {
	/**
	 * Original wp-config.php contents, restored in tear_down.
	 *
	 * @var string|false
	 */
	private $wp_config_before = false;

	/**
	 * Path of wp-config.php used by the environment checks.
	 *
	 * @var string|false
	 */
	private $wp_config_path = false;

	/**
	 * Clears the event and registers a schedule for it.
	 *
	 * @since X.X.X
	 */
	public function set_up() {
		parent::set_up();

		$this->wp_config_path = \W3TC\Util_Environment::wp_config_path();
		if ( $this->wp_config_path ) {
			$this->wp_config_before = file_get_contents( $this->wp_config_path );
		}

		add_filter(
			'cron_schedules',
			function ( $schedules ) {
				$schedules['w3_pgcache_cleanup'] = array(
					'interval' => 900,
					'display'  => 'Page cache cleanup test',
				);
				return $schedules;
			}
		);

		wp_clear_scheduled_hook( 'w3_pgcache_cleanup' );
	}

	/**
	 * Clears the event and restores wp-config.php.
	 *
	 * @since X.X.X
	 */
	public function tear_down() {
		wp_clear_scheduled_hook( 'w3_pgcache_cleanup' );

		if ( $this->wp_config_path && false !== $this->wp_config_before ) {
			file_put_contents( $this->wp_config_path, $this->wp_config_before ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		}

		parent::tear_down();
	}

	/**
	 * Builds an in-memory configuration.
	 *
	 * @since X.X.X
	 *
	 * @param bool   $enabled Whether page cache is enabled.
	 * @param string $engine  Page cache engine.
	 * @return Config
	 */
	private function config( $enabled, $engine = 'file' ) {
		$w3tc_config = new Config();
		$w3tc_config->set( 'pgcache.enabled', $enabled );
		$w3tc_config->set( 'pgcache.engine', $engine );
		$w3tc_config->set( 'pgcache.prime.enabled', false );
		$w3tc_config->set( 'config.check', false );

		return $w3tc_config;
	}

	/**
	 * An admin request schedules the missing event when Disk page cache is enabled.
	 *
	 * @since X.X.X
	 */
	public function test_537_admin_request_restores_missing_cleanup_cron() {
		$this->assertFalse( wp_next_scheduled( 'w3_pgcache_cleanup' ) );

		$root = new Root_Environment();
		try {
			$root->fix_in_wpadmin( $this->config( true ) );
		} catch ( \W3TC\Util_Environment_Exceptions $ex ) {
			unset( $ex ); // Unrelated environment checks may report; scheduling is asserted below.
		}

		$this->assertNotFalse( wp_next_scheduled( 'w3_pgcache_cleanup' ) );
	}

	/**
	 * An admin request leaves the event unscheduled when page cache is disabled.
	 *
	 * @since X.X.X
	 */
	public function test_537_admin_request_does_not_schedule_when_disabled() {
		$root = new Root_Environment();
		try {
			$root->fix_in_wpadmin( $this->config( false ) );
		} catch ( \W3TC\Util_Environment_Exceptions $ex ) {
			unset( $ex );
		}

		$this->assertFalse( wp_next_scheduled( 'w3_pgcache_cleanup' ) );
	}

	/**
	 * An admin request keeps an existing event as it is.
	 *
	 * @since X.X.X
	 */
	public function test_537_admin_request_keeps_existing_cleanup_cron() {
		$timestamp = time() + 600;
		wp_schedule_event( $timestamp, 'w3_pgcache_cleanup', 'w3_pgcache_cleanup' );

		$environment = new PgCache_Environment();
		try {
			$environment->fix_on_wpadmin_request( $this->config( true ), false );
		} catch ( \W3TC\Util_Environment_Exceptions $ex ) {
			unset( $ex );
		}

		$this->assertSame( $timestamp, wp_next_scheduled( 'w3_pgcache_cleanup' ) );
	}

	/**
	 * Control: the event handler still schedules the missing event.
	 *
	 * @since X.X.X
	 */
	public function test_537_control_event_handler_schedules_missing_cleanup() {
		$this->assertFalse( wp_next_scheduled( 'w3_pgcache_cleanup' ) );

		$environment = new PgCache_Environment();
		$environment->fix_on_event( $this->config( true ), 'admin_request' );

		$this->assertNotFalse( wp_next_scheduled( 'w3_pgcache_cleanup' ) );
	}
}
