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
	 * Parse outcome.
	 *
	 * @var string
	 */
	public $parse_outcome = PgCache_Plugin_Admin::SITEMAP_OUTCOME_SUCCESS;

	/**
	 * Returns configured test URLs.
	 *
	 * @param string      $w3tc_url Sitemap URL.
	 * @param string|null $origin_host Root sitemap host.
	 * @param int         $depth Current recursion depth.
	 * @param string|null $outcome Parse outcome.
	 *
	 * @return array
	 */
	public function parse_sitemap( $w3tc_url, $origin_host = null, $depth = 0, &$outcome = null ) {
		$outcome = $this->parse_outcome;
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
	 * Callback result.
	 *
	 * @var array
	 */
	public $result = array(
		'success'   => true,
		'processed' => 1,
		'complete'  => true,
		'stale'     => false,
	);

	/**
	 * Whether to reset the generation during the callback.
	 *
	 * @var bool
	 */
	public $reset_generation = false;

	/**
	 * Whether to throw during the callback.
	 *
	 * @var bool
	 */
	public $throw = false;

	/**
	 * Records a preload batch.
	 *
	 * @return array
	 *
	 * @throws RuntimeException When requested by the test.
	 */
	public function prime() {
		++$this->calls;

		if ( $this->reset_generation ) {
			PgCache_Plugin_Admin::reset_prime();
		}

		if ( $this->throw ) {
			throw new RuntimeException( 'Preload failed.' );
		}

		return $this->result;
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
	 * Requested HTTP URLs.
	 *
	 * @var array
	 */
	private $http_requests = array();

	/**
	 * Prepares cron and option state.
	 */
	public function set_up() {
		parent::set_up();

		$instances = new ReflectionProperty( Dispatcher::class, 'instances' );
		$this->make_property_accessible( $instances );
		$this->dispatcher_instances = $instances->getValue();
		$this->http_requests        = array();

		wp_clear_scheduled_hook( 'w3_pgcache_prime' );
		$this->delete_prime_options();

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
		$this->make_property_accessible( $instances );
		$instances->setValue( null, $this->dispatcher_instances );

		wp_clear_scheduled_hook( 'w3_pgcache_prime' );
		$this->delete_prime_options();

		parent::tear_down();
	}

	/**
	 * Verifies successful batches report explicit completion.
	 */
	public function test_admin_prime_reports_explicit_success_and_completion() {
		$admin       = $this->stub_admin();
		$admin->urls = array(
			'https://example.org/one',
			'https://example.org/two',
			'https://example.org/three',
		);
		$generation  = PgCache_Plugin_Admin::prime_generation();

		$this->mock_http( array() );

		$first = $admin->prime( null, null, null, true, $generation );
		$this->assertTrue( $first['success'] );
		$this->assertFalse( $first['complete'] );
		$this->assertSame( 2, get_option( PgCache_Plugin_Admin::PRIME_OFFSET_OPTION ) );

		$second = $admin->prime( null, null, null, true, $generation );
		$this->assertTrue( $second['success'] );
		$this->assertTrue( $second['complete'] );
		$this->assertSame( 1, $second['processed'] );
		$this->assertSame( 0, get_option( PgCache_Plugin_Admin::PRIME_OFFSET_OPTION ) );
	}

	/**
	 * Verifies failed parses remain retryable and empty traversals complete.
	 */
	public function test_failed_parse_retries_and_empty_traversal_completes() {
		$admin       = $this->stub_admin();
		$generation  = PgCache_Plugin_Admin::prime_generation();
		$admin->urls = array( 'https://example.org/one' );

		$admin->parse_outcome = PgCache_Plugin_Admin::SITEMAP_OUTCOME_FAILURE;
		$failed               = $admin->prime( null, null, null, true, $generation );
		$this->assertFalse( $failed['success'] );
		$this->assertFalse( $failed['complete'] );
		$this->assertFalse( get_option( PgCache_Plugin_Admin::PRIME_OFFSET_OPTION, false ) );

		$admin->parse_outcome = PgCache_Plugin_Admin::SITEMAP_OUTCOME_SUCCESS;
		$admin->urls          = array();
		update_option( PgCache_Plugin_Admin::PRIME_OFFSET_OPTION, 5, false );
		$empty = $admin->prime( null, null, null, true, $generation );
		$this->assertTrue( $empty['success'] );
		$this->assertTrue( $empty['complete'] );
		$this->assertSame( 0, $empty['processed'] );
		$this->assertSame( 0, get_option( PgCache_Plugin_Admin::PRIME_OFFSET_OPTION ) );
	}

	/**
	 * Verifies HTTP, XML, and nested traversal failures are explicit.
	 */
	public function test_parse_sitemap_failure_modes() {
		$admin = $this->real_admin();

		$this->mock_http(
			array(
				'https://example.org/http.xml' => $this->http_response( 500, '' ),
				'https://example.org/xml.xml'  => $this->http_response( 200, '<urlset>' ),
				'https://example.org/root.xml' => $this->http_response(
					200,
					'<sitemapindex><sitemap><loc>https://example.org/child.xml</loc></sitemap></sitemapindex>'
				),
				'https://example.org/child.xml' => $this->http_response( 500, '' ),
				'https://example.org/empty.xml' => $this->http_response( 200, '<urlset></urlset>' ),
			)
		);

		foreach ( array( 'http.xml', 'xml.xml', 'root.xml' ) as $sitemap ) {
			$outcome = PgCache_Plugin_Admin::SITEMAP_OUTCOME_SUCCESS;
			$urls    = $admin->parse_sitemap( 'https://example.org/' . $sitemap, null, 0, $outcome );
			$this->assertSame( PgCache_Plugin_Admin::SITEMAP_OUTCOME_FAILURE, $outcome, $sitemap );
			$this->assertSame( array(), $urls, $sitemap );
		}

		$outcome = PgCache_Plugin_Admin::SITEMAP_OUTCOME_FAILURE;
		$urls    = $admin->parse_sitemap( 'https://example.org/empty.xml', null, 0, $outcome );
		$this->assertSame( PgCache_Plugin_Admin::SITEMAP_OUTCOME_SUCCESS, $outcome );
		$this->assertSame( array(), $urls );
	}

	/**
	 * Verifies a depth-limited child does not block a valid sibling.
	 */
	public function test_nested_depth_skip_allows_valid_sibling() {
		$responses = array(
			'https://example.org/root.xml'   => $this->sitemap_index_response(
				array(
					'https://example.org/level-1.xml',
					'https://example.org/valid.xml',
				)
			),
			'https://example.org/level-1.xml' => $this->sitemap_index_response(
				array( 'https://example.org/level-2.xml' )
			),
			'https://example.org/level-2.xml' => $this->sitemap_index_response(
				array( 'https://example.org/level-3.xml' )
			),
			'https://example.org/level-3.xml' => $this->sitemap_index_response(
				array( 'https://example.org/level-4.xml' )
			),
			'https://example.org/valid.xml'   => $this->urlset_response( 'https://example.org/page' ),
		);

		$this->assert_sitemap_has_valid_sibling( $responses );
		$this->assertNotContains( 'https://example.org/level-4.xml', $this->http_requests );
	}

	/**
	 * Verifies a non-public child does not block a valid sibling.
	 */
	public function test_nested_non_public_host_skip_allows_valid_sibling() {
		$responses = array(
			'https://example.org/root.xml'  => $this->sitemap_index_response(
				array(
					'http://127.0.0.1/private.xml',
					'https://example.org/valid.xml',
				)
			),
			'https://example.org/valid.xml' => $this->urlset_response( 'https://example.org/page' ),
		);

		$this->assert_sitemap_has_valid_sibling( $responses );
		$this->assertNotContains( 'http://127.0.0.1/private.xml', $this->http_requests );
	}

	/**
	 * Verifies a cross-origin child does not block a valid sibling.
	 */
	public function test_nested_cross_origin_skip_allows_valid_sibling() {
		$responses = array(
			'https://example.org/root.xml'  => $this->sitemap_index_response(
				array(
					'https://example.com/other.xml',
					'https://example.org/valid.xml',
				)
			),
			'https://example.org/valid.xml' => $this->urlset_response( 'https://example.org/page' ),
		);

		$this->assert_sitemap_has_valid_sibling( $responses );
		$this->assertNotContains( 'https://example.com/other.xml', $this->http_requests );
	}

	/**
	 * Verifies a missing child location does not block a valid sibling.
	 */
	public function test_nested_missing_loc_skip_allows_valid_sibling() {
		$responses = array(
			'https://example.org/root.xml'  => $this->http_response(
				200,
				'<sitemapindex><sitemap></sitemap><sitemap><loc>https://example.org/valid.xml</loc></sitemap></sitemapindex>'
			),
			'https://example.org/valid.xml' => $this->urlset_response( 'https://example.org/page' ),
		);

		$this->assert_sitemap_has_valid_sibling( $responses );
	}

	/**
	 * Verifies policy-only input completes one-pass scheduling.
	 */
	public function test_all_policy_skipped_input_completes_one_pass() {
		$admin      = $this->real_admin();
		$generation = PgCache_Plugin_Admin::prime_generation();

		$this->mock_http(
			array(
				'https://example.org/sitemap.xml' => $this->sitemap_index_response(
					array(
						'http://127.0.0.1/private.xml',
						'https://example.com/other.xml',
					),
					true
				),
			)
		);
		update_option( PgCache_Plugin_Admin::PRIME_OFFSET_OPTION, 4, false );

		$result = $admin->prime( null, null, null, true, $generation );

		$this->assertTrue( $result['success'] );
		$this->assertTrue( $result['complete'] );
		$this->assertSame( 0, $result['processed'] );
		$this->assertSame( 0, get_option( PgCache_Plugin_Admin::PRIME_OFFSET_OPTION ) );
		$this->assertSame( array( 'https://example.org/sitemap.xml' ), $this->http_requests );

		$plugin = $this->plugin_for_mode( true, $admin );
		$this->schedule_prime();
		$plugin->prime();

		$this->assertSame( $generation, get_option( PgCache_Plugin_Admin::PRIME_COMPLETED_OPTION ) );
		$this->assertFalse( wp_next_scheduled( 'w3_pgcache_prime' ) );
	}

	/**
	 * Verifies parse failures leave one-pass cron available for retry.
	 */
	public function test_failed_one_pass_keeps_scheduled_retry() {
		$callback            = new W3TC_PgCache_Preload_Callback_Stub();
		$callback->result    = array(
			'success'   => false,
			'processed' => 0,
			'complete'  => false,
			'stale'     => false,
		);
		$plugin              = $this->plugin_for_mode( true, $callback );

		$this->schedule_prime();
		$plugin->prime();

		$this->assertSame( 1, $callback->calls );
		$this->assertNotFalse( wp_next_scheduled( 'w3_pgcache_prime' ) );
		$this->assertFalse( get_option( PgCache_Plugin_Admin::PRIME_COMPLETED_OPTION, false ) );
	}

	/**
	 * Verifies the default continuous mode keeps its cron event.
	 */
	public function test_continuous_mode_keeps_scheduled_cycles() {
		$callback = new W3TC_PgCache_Preload_Callback_Stub();
		$plugin   = $this->plugin_for_mode( false, $callback );

		$this->schedule_prime();
		$plugin->prime();

		$this->assertSame( 1, $callback->calls );
		$this->assertNotFalse( wp_next_scheduled( 'w3_pgcache_prime' ) );
		$this->assertFalse( get_option( PgCache_Plugin_Admin::PRIME_COMPLETED_OPTION, false ) );
	}

	/**
	 * Verifies a 404 in the final continuous batch advances and wraps progress.
	 */
	public function test_continuous_mode_counts_last_batch_404_as_processed() {
		$admin  = $this->last_batch_404_admin();
		$plugin = $this->plugin_for_mode( false, $admin );

		$this->schedule_prime();
		$plugin->prime();
		$this->assertSame( 2, get_option( PgCache_Plugin_Admin::PRIME_OFFSET_OPTION ) );

		$plugin->prime();
		$this->assertSame( 0, get_option( PgCache_Plugin_Admin::PRIME_OFFSET_OPTION ) );
		$this->assertNotFalse( wp_next_scheduled( 'w3_pgcache_prime' ) );
		$this->assertFalse( get_option( PgCache_Plugin_Admin::PRIME_COMPLETED_OPTION, false ) );
	}

	/**
	 * Verifies successful one-pass mode stops after completion.
	 */
	public function test_one_pass_mode_stops_after_completion() {
		$callback   = new W3TC_PgCache_Preload_Callback_Stub();
		$plugin     = $this->plugin_for_mode( true, $callback );
		$generation = PgCache_Plugin_Admin::prime_generation();

		$this->schedule_prime();
		$plugin->prime();

		$this->assertSame( 1, $callback->calls );
		$this->assertFalse( wp_next_scheduled( 'w3_pgcache_prime' ) );
		$this->assertSame( $generation, get_option( PgCache_Plugin_Admin::PRIME_COMPLETED_OPTION ) );

		$plugin->prime();
		$this->assertSame( 1, $callback->calls );
	}

	/**
	 * Verifies a 404 in the final one-pass batch still completes the pass.
	 */
	public function test_one_pass_mode_counts_last_batch_404_as_processed() {
		$admin      = $this->last_batch_404_admin();
		$plugin     = $this->plugin_for_mode( true, $admin );
		$generation = PgCache_Plugin_Admin::prime_generation();

		$this->schedule_prime();
		$plugin->prime();
		$this->assertSame( 2, get_option( PgCache_Plugin_Admin::PRIME_OFFSET_OPTION ) );

		$plugin->prime();
		$this->assertSame( 0, get_option( PgCache_Plugin_Admin::PRIME_OFFSET_OPTION ) );
		$this->assertSame( $generation, get_option( PgCache_Plugin_Admin::PRIME_COMPLETED_OPTION ) );
		$this->assertFalse( wp_next_scheduled( 'w3_pgcache_prime' ) );
	}

	/**
	 * Verifies active locks suppress overlapping callbacks.
	 */
	public function test_overlapping_prime_run_is_skipped() {
		$callback = new W3TC_PgCache_Preload_Callback_Stub();
		$plugin   = $this->plugin_for_mode( true, $callback );

		add_option(
			PgCache_Plugin_Admin::PRIME_LOCK_OPTION,
			array(
				'token'      => 'other-run',
				'generation' => PgCache_Plugin_Admin::prime_generation(),
				'expires'    => time() + 300,
			),
			'',
			false
		);

		$this->schedule_prime();
		$plugin->prime();

		$this->assertSame( 0, $callback->calls );
		$this->assertNotFalse( wp_next_scheduled( 'w3_pgcache_prime' ) );
	}

	/**
	 * Verifies expired lock recovery preserves current pass progress.
	 */
	public function test_expired_lock_recovery_preserves_progress() {
		$generation = PgCache_Plugin_Admin::prime_generation();
		update_option( PgCache_Plugin_Admin::PRIME_OFFSET_OPTION, 4, false );
		update_option( PgCache_Plugin_Admin::PRIME_COMPLETED_OPTION, $generation, false );
		add_option(
			PgCache_Plugin_Admin::PRIME_LOCK_OPTION,
			array(
				'token'      => 'expired-run',
				'generation' => $generation,
				'expires'    => time() - 1,
			),
			'',
			false
		);

		$lock = PgCache_Plugin_Admin::acquire_prime_lock();

		$this->assertIsArray( $lock );
		$this->assertNotSame( 'expired-run', $lock['token'] );
		$this->assertSame( $generation, $lock['generation'] );
		$this->assertSame( $generation, PgCache_Plugin_Admin::prime_generation() );
		$this->assertSame( 4, get_option( PgCache_Plugin_Admin::PRIME_OFFSET_OPTION ) );
		$this->assertSame( $generation, get_option( PgCache_Plugin_Admin::PRIME_COMPLETED_OPTION ) );

		PgCache_Plugin_Admin::release_prime_lock( $lock['token'] );
	}

	/**
	 * Verifies locks are released when a callback throws.
	 */
	public function test_prime_lock_is_released_on_exception() {
		$callback        = new W3TC_PgCache_Preload_Callback_Stub();
		$callback->throw = true;
		$plugin          = $this->plugin_for_mode( true, $callback );

		$this->schedule_prime();

		try {
			$plugin->prime();
			$this->fail( 'Expected preload exception.' );
		} catch ( RuntimeException $exception ) {
			$this->assertSame( 'Preload failed.', $exception->getMessage() );
		}

		$this->assertFalse( get_option( PgCache_Plugin_Admin::PRIME_LOCK_OPTION, false ) );
		$this->assertNotFalse( wp_next_scheduled( 'w3_pgcache_prime' ) );
	}

	/**
	 * Verifies a reset invalidates an in-flight completion.
	 */
	public function test_stale_generation_cannot_complete_new_pass() {
		$callback                   = new W3TC_PgCache_Preload_Callback_Stub();
		$callback->reset_generation = true;
		$plugin                     = $this->plugin_for_mode( true, $callback );

		$this->schedule_prime();
		$plugin->prime();

		$this->assertFalse( get_option( PgCache_Plugin_Admin::PRIME_COMPLETED_OPTION, false ) );
		$this->assertSame( 0, get_option( PgCache_Plugin_Admin::PRIME_OFFSET_OPTION ) );
		$this->assertNotFalse( wp_next_scheduled( 'w3_pgcache_prime' ) );
	}

	/**
	 * Verifies stale admin work cannot advance the new generation's offset.
	 */
	public function test_stale_generation_cannot_write_offset() {
		$admin       = $this->stub_admin();
		$admin->urls = array( 'https://example.org/one' );
		$generation  = PgCache_Plugin_Admin::prime_generation();

		PgCache_Plugin_Admin::reset_prime();
		$this->mock_http( array() );

		$result = $admin->prime( 0, 1, null, true, $generation );

		$this->assertTrue( $result['success'] );
		$this->assertTrue( $result['stale'] );
		$this->assertSame( 0, get_option( PgCache_Plugin_Admin::PRIME_OFFSET_OPTION ) );
		$this->assertFalse( get_option( PgCache_Plugin_Admin::PRIME_COMPLETED_OPTION, false ) );
	}

	/**
	 * Verifies CLI-style priming does not change scheduled progress.
	 */
	public function test_prime_without_progress_updates_preserves_scheduled_state() {
		$admin       = $this->stub_admin();
		$admin->urls = array( 'https://example.org/one' );
		$generation  = PgCache_Plugin_Admin::prime_generation();

		update_option( PgCache_Plugin_Admin::PRIME_OFFSET_OPTION, 4, false );
		update_option( PgCache_Plugin_Admin::PRIME_COMPLETED_OPTION, $generation, false );
		$this->mock_http( array() );

		$result = $admin->prime( 0, 1, null, false );

		$this->assertTrue( $result['complete'] );
		$this->assertSame( 4, get_option( PgCache_Plugin_Admin::PRIME_OFFSET_OPTION ) );
		$this->assertSame( $generation, get_option( PgCache_Plugin_Admin::PRIME_COMPLETED_OPTION ) );
	}

	/**
	 * Verifies environment repair does not recreate a completed event.
	 */
	public function test_completed_one_pass_is_not_rescheduled_by_repair_or_interval_change() {
		$environment = new PgCache_Environment();
		$config      = $this->environment_config( true, true );

		$environment->fix_on_event( $config, 'admin_request' );
		$generation = PgCache_Plugin_Admin::prime_generation();
		update_option( PgCache_Plugin_Admin::PRIME_COMPLETED_OPTION, $generation, false );
		wp_clear_scheduled_hook( 'w3_pgcache_prime' );

		$environment->fix_on_event( $config, 'admin_request' );
		$this->assertSame( $generation, PgCache_Plugin_Admin::prime_generation() );
		$this->assertFalse( wp_next_scheduled( 'w3_pgcache_prime' ) );

		$environment->fix_on_event(
			$this->environment_config( true, true, 'https://example.org/sitemap.xml', 1800 ),
			'config_change',
			$this->environment_config( true, true, 'https://example.org/sitemap.xml', 900 )
		);
		$this->assertSame( $generation, get_option( PgCache_Plugin_Admin::PRIME_COMPLETED_OPTION ) );
		$this->assertFalse( wp_next_scheduled( 'w3_pgcache_prime' ) );
	}

	/**
	 * Verifies repair detects effective settings changes without old config.
	 */
	public function test_repair_signature_restarts_changed_effective_settings() {
		$environment = new PgCache_Environment();
		$cases       = array(
			array( $this->environment_config( true, true, 'https://example.org/new.xml' ), true ),
			array( $this->environment_config( true, false, 'https://example.org/old.xml' ), true ),
			array( $this->environment_config( false, true, 'https://example.org/old.xml' ), false ),
		);

		foreach ( $cases as $case ) {
			$this->delete_prime_options();
			wp_clear_scheduled_hook( 'w3_pgcache_prime' );

			$environment->fix_on_event(
				$this->environment_config( true, true, 'https://example.org/old.xml' ),
				'admin_request'
			);

			$generation = PgCache_Plugin_Admin::prime_generation();
			update_option( PgCache_Plugin_Admin::PRIME_OFFSET_OPTION, 7, false );
			update_option( PgCache_Plugin_Admin::PRIME_COMPLETED_OPTION, $generation, false );
			wp_clear_scheduled_hook( 'w3_pgcache_prime' );

			$environment->fix_on_event( $case[0], 'admin_request' );

			$this->assertNotSame( $generation, PgCache_Plugin_Admin::prime_generation() );
			$this->assertSame( 0, get_option( PgCache_Plugin_Admin::PRIME_OFFSET_OPTION ) );
			$this->assertFalse( get_option( PgCache_Plugin_Admin::PRIME_COMPLETED_OPTION, false ) );
			$this->assertSame( $case[1], false !== wp_next_scheduled( 'w3_pgcache_prime' ) );
		}
	}

	/**
	 * Verifies sitemap and mode changes start a new pass.
	 */
	public function test_sitemap_and_mode_changes_reset_completed_pass() {
		$environment = new PgCache_Environment();

		foreach (
			array(
				array(
					$this->environment_config( true, true, 'https://example.org/new.xml' ),
					$this->environment_config( true, true, 'https://example.org/old.xml' ),
				),
				array(
					$this->environment_config( true, false ),
					$this->environment_config( true, true ),
				),
			) as $configs
		) {
			wp_clear_scheduled_hook( 'w3_pgcache_prime' );
			$generation = PgCache_Plugin_Admin::prime_generation();
			update_option( PgCache_Plugin_Admin::PRIME_OFFSET_OPTION, 7, false );
			update_option( PgCache_Plugin_Admin::PRIME_COMPLETED_OPTION, $generation, false );

			$environment->fix_on_event( $configs[0], 'config_change', $configs[1] );

			$this->assertNotSame( $generation, PgCache_Plugin_Admin::prime_generation() );
			$this->assertSame( 0, get_option( PgCache_Plugin_Admin::PRIME_OFFSET_OPTION ) );
			$this->assertFalse( get_option( PgCache_Plugin_Admin::PRIME_COMPLETED_OPTION, false ) );
			$this->assertNotFalse( wp_next_scheduled( 'w3_pgcache_prime' ) );
		}
	}

	/**
	 * Verifies disable, deactivate, and activate reset lifecycle state.
	 */
	public function test_disable_deactivate_and_activate_reset_lifecycle() {
		$environment = new PgCache_Environment();

		$generation = PgCache_Plugin_Admin::prime_generation();
		update_option( PgCache_Plugin_Admin::PRIME_OFFSET_OPTION, 7, false );
		update_option( PgCache_Plugin_Admin::PRIME_COMPLETED_OPTION, $generation, false );
		$environment->fix_on_event(
			$this->environment_config( false, true ),
			'config_change',
			$this->environment_config( true, true )
		);
		$this->assertNotSame( $generation, PgCache_Plugin_Admin::prime_generation() );
		$this->assertSame( 0, get_option( PgCache_Plugin_Admin::PRIME_OFFSET_OPTION ) );
		$this->assertFalse( get_option( PgCache_Plugin_Admin::PRIME_COMPLETED_OPTION, false ) );

		$generation = PgCache_Plugin_Admin::prime_generation();
		$environment->fix_after_deactivation();
		$this->assertNotSame( $generation, PgCache_Plugin_Admin::prime_generation() );
		$this->assertFalse( wp_next_scheduled( 'w3_pgcache_prime' ) );

		$generation = PgCache_Plugin_Admin::prime_generation();
		$environment->fix_on_event( $this->environment_config( true, true ), 'activate' );
		$this->assertNotSame( $generation, PgCache_Plugin_Admin::prime_generation() );
		$this->assertNotFalse( wp_next_scheduled( 'w3_pgcache_prime' ) );
	}

	/**
	 * Verifies preload state uses each site's options table in multisite.
	 */
	public function test_multisite_preload_state_is_per_blog() {
		if ( ! is_multisite() ) {
			$this->markTestSkipped( 'Requires multisite.' );
		}

		$first_generation = PgCache_Plugin_Admin::reset_prime();
		$blog_id          = self::factory()->blog->create();

		switch_to_blog( $blog_id );
		$second_generation = PgCache_Plugin_Admin::reset_prime();
		update_option( PgCache_Plugin_Admin::PRIME_COMPLETED_OPTION, $second_generation, false );
		restore_current_blog();

		$this->assertSame( $first_generation, PgCache_Plugin_Admin::prime_generation() );
		$this->assertFalse( get_option( PgCache_Plugin_Admin::PRIME_COMPLETED_OPTION, false ) );

		switch_to_blog( $blog_id );
		$this->assertSame( $second_generation, PgCache_Plugin_Admin::prime_generation() );
		$this->assertSame( $second_generation, get_option( PgCache_Plugin_Admin::PRIME_COMPLETED_OPTION ) );

		$environment = new PgCache_Environment();
		$environment->fix_on_event(
			$this->environment_config( true, true, 'https://example.org/inherited-old.xml' ),
			'admin_request'
		);
		$second_generation = PgCache_Plugin_Admin::prime_generation();
		$environment->fix_on_event(
			$this->environment_config( true, true, 'https://example.org/inherited-new.xml' ),
			'admin_request'
		);
		$this->assertNotSame( $second_generation, PgCache_Plugin_Admin::prime_generation() );
		restore_current_blog();

		$this->assertSame( $first_generation, PgCache_Plugin_Admin::prime_generation() );
	}

	/**
	 * Creates a plugin with a test callback and mode.
	 *
	 * @param bool                                  $one_pass One-pass mode.
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
		$this->make_property_accessible( $instances );
		$value                         = $instances->getValue();
		$value['PgCache_Plugin_Admin'] = $callback;
		$instances->setValue( null, $value );

		return $plugin;
	}

	/**
	 * Creates environment configuration.
	 *
	 * @param bool   $prime_enabled Preload enabled.
	 * @param bool   $one_pass One-pass mode.
	 * @param string $sitemap Sitemap URL.
	 * @param int    $interval Schedule interval.
	 *
	 * @return W3TC_PgCache_Preload_Config_Stub
	 */
	private function environment_config(
		$prime_enabled,
		$one_pass,
		$sitemap = 'https://example.org/sitemap.xml',
		$interval = 900
	) {
		return new W3TC_PgCache_Preload_Config_Stub(
			array(
				'pgcache.enabled'                => true,
				'pgcache.engine'                 => 'redis',
				'pgcache.prime.enabled'          => $prime_enabled,
				'pgcache.prime.interval'         => $interval,
				'pgcache.prime.sitemap'          => $sitemap,
				'pgcache.prime.sitemap_one_pass' => $one_pass,
			)
		);
	}

	/**
	 * Creates an admin stub with standard configuration.
	 *
	 * @return W3TC_PgCache_Preload_Admin_Stub
	 */
	private function stub_admin() {
		$admin = new W3TC_PgCache_Preload_Admin_Stub();
		$this->configure_admin( $admin );
		return $admin;
	}

	/**
	 * Creates a real admin with standard configuration.
	 *
	 * @return PgCache_Plugin_Admin
	 */
	private function real_admin() {
		$admin = new PgCache_Plugin_Admin();
		$this->configure_admin( $admin );
		return $admin;
	}

	/**
	 * Adds standard configuration to an admin instance.
	 *
	 * @param PgCache_Plugin_Admin $admin Admin instance.
	 *
	 * @return void
	 */
	private function configure_admin( $admin ) {
		$this->set_private_config(
			$admin,
			PgCache_Plugin_Admin::class,
			new W3TC_PgCache_Preload_Config_Stub(
				array(
					'pgcache.prime.limit'   => 2,
					'pgcache.prime.sitemap' => 'https://example.org/sitemap.xml',
				)
			)
		);
	}

	/**
	 * Creates an admin whose final URL returns 404.
	 *
	 * @return W3TC_PgCache_Preload_Admin_Stub
	 */
	private function last_batch_404_admin() {
		$admin       = $this->stub_admin();
		$admin->urls = array(
			'https://example.org/one',
			'https://example.org/two',
			'https://example.org/missing',
		);

		$this->mock_http(
			array(
				'https://example.org/missing' => $this->http_response( 404, '' ),
			)
		);

		return $admin;
	}

	/**
	 * Asserts that a nested skip preserves a valid sibling.
	 *
	 * @param array $responses Responses keyed by URL.
	 *
	 * @return void
	 */
	private function assert_sitemap_has_valid_sibling( $responses ) {
		$admin   = $this->real_admin();
		$outcome = PgCache_Plugin_Admin::SITEMAP_OUTCOME_FAILURE;

		$this->mock_http( $responses );
		$urls = $admin->parse_sitemap( 'https://example.org/root.xml', null, 0, $outcome );

		$this->assertSame( PgCache_Plugin_Admin::SITEMAP_OUTCOME_SUCCESS, $outcome );
		$this->assertSame( array( 'https://example.org/page' ), $urls );
		$this->assertContains( 'https://example.org/valid.xml', $this->http_requests );
	}

	/**
	 * Creates a sitemap index response.
	 *
	 * @param array $urls Child sitemap URLs.
	 * @param bool  $missing_loc Whether to include a missing location.
	 *
	 * @return array
	 */
	private function sitemap_index_response( $urls, $missing_loc = false ) {
		$body = '<sitemapindex>';

		if ( $missing_loc ) {
			$body .= '<sitemap></sitemap>';
		}

		foreach ( $urls as $url ) {
			$body .= '<sitemap><loc>' . $url . '</loc></sitemap>';
		}

		return $this->http_response( 200, $body . '</sitemapindex>' );
	}

	/**
	 * Creates a URL set response.
	 *
	 * @param string $url Page URL.
	 *
	 * @return array
	 */
	private function urlset_response( $url ) {
		return $this->http_response( 200, '<urlset><url><loc>' . $url . '</loc></url></urlset>' );
	}

	/**
	 * Stubs HTTP responses by URL, with success as the default.
	 *
	 * @param array $responses Responses keyed by URL.
	 *
	 * @return void
	 */
	private function mock_http( $responses ) {
		add_filter(
			'pre_http_request',
			function ( $preempt, $args, $url ) use ( $responses ) {
				$this->http_requests[] = $url;

				if ( isset( $responses[ $url ] ) ) {
					return $responses[ $url ];
				}

				return $this->http_response( 200, '' );
			},
			10,
			3
		);
	}

	/**
	 * Creates a WordPress HTTP response.
	 *
	 * @param int    $code HTTP status.
	 * @param string $body Response body.
	 *
	 * @return array
	 */
	private function http_response( $code, $body ) {
		return array(
			'headers'  => array(),
			'body'     => $body,
			'response' => array(
				'code'    => $code,
				'message' => 200 === $code ? 'OK' : 'Error',
			),
			'cookies'  => array(),
			'filename' => null,
		);
	}

	/**
	 * Schedules the preload hook.
	 *
	 * @return void
	 */
	private function schedule_prime() {
		wp_schedule_event( time(), 'w3_pgcache_prime', 'w3_pgcache_prime' );
	}

	/**
	 * Deletes all preload state options for the current blog.
	 *
	 * @return void
	 */
	private function delete_prime_options() {
		delete_option( PgCache_Plugin_Admin::PRIME_OFFSET_OPTION );
		delete_option( PgCache_Plugin_Admin::PRIME_COMPLETED_OPTION );
		delete_option( PgCache_Plugin_Admin::PRIME_GENERATION_OPTION );
		delete_option( PgCache_Plugin_Admin::PRIME_LOCK_OPTION );
		delete_option( PgCache_Plugin_Admin::PRIME_SETTINGS_OPTION );
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
		$this->make_property_accessible( $property );
		$property->setValue( $target_object, $config );
	}

	/**
	 * Enables private property access where the runtime requires it.
	 *
	 * @param ReflectionProperty $property Reflected property.
	 *
	 * @return void
	 */
	private function make_property_accessible( $property ) {
		if ( PHP_VERSION_ID < 80100 ) {
			$property->setAccessible( true );
		}
	}
}
