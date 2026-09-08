<?php
/**
 * File: class-w3tc-pagespeed-widget-cache-test.php
 *
 * @package    W3TC
 * @subpackage W3TC/tests/admin
 * @since      X.X.X
 */

declare( strict_types = 1 );

use W3TC\Dispatcher;
use W3TC\PageSpeed_Widget;

/**
 * PageSpeed widget cache vs expiry coverage.
 *
 * @since X.X.X
 */
class W3tc_Pagespeed_Widget_Cache_Test extends WP_UnitTestCase {
	/**
	 * Number of intercepted HTTP requests.
	 *
	 * @var int
	 */
	private $http_request_count = 0;

	/**
	 * HTTP filter callback.
	 *
	 * @var callable|null
	 */
	private $http_filter;

	/**
	 * Preserve PageSpeed config values changed by each test.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		$this->http_request_count = 0;
		$this->http_filter        = function() {
			++$this->http_request_count;
			return new WP_Error( 'http_request_failed', 'Unexpected outbound request.' );
		};
		add_filter( 'pre_http_request', $this->http_filter, 10, 3 );

		update_option( 'w3tcps_refresh_retry_after', time() + 900 );
	}

	/**
	 * Restore config, cache, and HTTP filters.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		if ( null !== $this->http_filter ) {
			remove_filter( 'pre_http_request', $this->http_filter, 10 );
		}

		$config = Dispatcher::config();
		$config->set( 'widget.pagespeed.access_token', '' );
		$config->set( 'widget.pagespeed.w3tc_pagespeed_key', '' );
		$config->save();

		delete_option( 'w3tc_pagespeed_data_' . get_home_url() );
		delete_option( 'w3tcps_refresh_retry_after' );

		parent::tearDown();
	}

	/**
	 * Cached widget results survive an expired token.
	 *
	 * @return void
	 */
	public function test_widget_ajax_returns_cache_when_token_is_expired() {
		$this->store_expired_token();

		$cache = array(
			'time'         => time(),
			'display_time' => 'cached-stamp',
			'mobile'       => array(
				'score' => 90,
			),
			'desktop'      => array(
				'score' => 95,
			),
		);
		update_option( 'w3tc_pagespeed_data_' . get_home_url(), wp_json_encode( $cache ) );

		$payload = $this->get_widget_ajax_payload();

		$this->assertArrayNotHasKey( 'missing_token', $payload );
		$this->assertSame( 'cached-stamp', $payload['w3tcps_timestamp'] );
		$this->assertSame( 0, $this->http_request_count );
	}

	/**
	 * Without cache, an expired token still requires authorization.
	 *
	 * @return void
	 */
	public function test_widget_ajax_missing_token_when_expired_without_cache() {
		$this->store_expired_token();

		$payload = $this->get_widget_ajax_payload();

		$this->assertArrayHasKey( 'missing_token', $payload );
		$this->assertSame( 0, $this->http_request_count );
	}

	/**
	 * Dashboard widget HTML still renders while a token is stored.
	 *
	 * @return void
	 */
	public function test_widget_html_renders_when_token_is_expired() {
		$this->store_expired_token();

		ob_start();
		( new PageSpeed_Widget() )->widget_pagespeed();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'w3tcps_loading', $output );
		$this->assertStringNotContainsString( 'authorize access', $output );
		$this->assertSame( 0, $this->http_request_count );
	}

	/**
	 * Store an expired PageSpeed token in the live config.
	 *
	 * @return void
	 */
	private function store_expired_token() {
		$config = Dispatcher::config();
		$config->set(
			'widget.pagespeed.access_token',
			wp_json_encode(
				array(
					'access_token' => 'expired-token',
					'expires_in'   => 3600,
					'created'      => 1,
				)
			)
		);
		$config->set( 'widget.pagespeed.w3tc_pagespeed_key', 'pagespeed-key' );
		$config->save();
	}

	/**
	 * Capture the widget AJAX JSON payload.
	 *
	 * @return array
	 */
	private function get_widget_ajax_payload() {
		ob_start();
		( new PageSpeed_Widget() )->w3tc_ajax_pagespeed_widgetdata();
		$output = ob_get_clean();

		$payload = json_decode( $output, true );
		$this->assertIsArray( $payload );

		return $payload;
	}
}
