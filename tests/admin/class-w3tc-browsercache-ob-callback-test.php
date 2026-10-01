<?php
/**
 * File: class-w3tc-browsercache-ob-callback-test.php
 *
 * @package    W3TC
 * @subpackage W3TC/tests/admin
 * @since      2.10.7
 */

declare( strict_types = 1 );

use W3TC\BrowserCache_Plugin;
use W3TC\Dispatcher;

/**
 * Browser Cache output-buffer URL rewrite behavior.
 *
 * @since 2.10.7
 */
class W3tc_Browsercache_Ob_Callback_Test extends WP_UnitTestCase {
	/**
	 * Previous browsercache.cssjs.querystring value.
	 *
	 * @var mixed
	 */
	private $prev_cssjs_querystring;

	/**
	 * Previous browsercache.rewrite value.
	 *
	 * @var mixed
	 */
	private $prev_rewrite;

	/**
	 * Enable CSS/JS query-string removal for each test.
	 *
	 * @since 2.10.7
	 *
	 * @return void
	 */
	public function set_up() {
		parent::set_up();

		$config = Dispatcher::config();

		$this->prev_cssjs_querystring = $config->get( 'browsercache.cssjs.querystring' );
		$this->prev_rewrite           = $config->get( 'browsercache.rewrite' );

		$config->set( 'browsercache.cssjs.querystring', true );
		$config->set( 'browsercache.rewrite', false );
	}

	/**
	 * Restore Browser Cache config.
	 *
	 * @since 2.10.7
	 *
	 * @return void
	 */
	public function tear_down() {
		$config = Dispatcher::config();

		$config->set( 'browsercache.cssjs.querystring', $this->prev_cssjs_querystring );
		$config->set( 'browsercache.rewrite', $this->prev_rewrite );

		parent::tear_down();
	}

	/**
	 * ob_callback must not rewrite URL-like text inside quoted attribute values.
	 *
	 * @since 2.10.7
	 *
	 * @return void
	 */
	public function test_ob_callback_skips_url_like_text_inside_quoted_attribute_values() {
		$plugin = new BrowserCache_Plugin();
		$this->set_browsercache_rewrite( $plugin, false );

		$marker = 'boundarymarker9f2a';
		$html   = '<html><body><blockquote cite=" src=/test.css?' . $marker . '#">'
			. '<code>rel=" data-marker="' . $marker . '"</code></blockquote></body></html>';

		$output = $plugin->ob_callback( $html );

		$this->assertSame( $html, $output );
	}

	/**
	 * ob_callback still strips query strings from real unquoted src attributes.
	 *
	 * @since 2.10.7
	 *
	 * @return void
	 */
	public function test_ob_callback_strips_querystring_from_unquoted_src_attributes() {
		$plugin = new BrowserCache_Plugin();
		$this->set_browsercache_rewrite( $plugin, false );

		$html = '<html><body><script src=/wp-includes/js/jquery.js?ver=3.7></script></body></html>';

		$output = $plugin->ob_callback( $html );

		$this->assertStringContainsString( 'src=/wp-includes/js/jquery.js>', $output );
		$this->assertStringNotContainsString( '?ver=3.7', $output );
	}

	/**
	 * ob_callback still strips query strings from quoted src attributes.
	 *
	 * @since 2.10.7
	 *
	 * @return void
	 */
	public function test_ob_callback_strips_querystring_from_quoted_src_attributes() {
		$plugin = new BrowserCache_Plugin();
		$this->set_browsercache_rewrite( $plugin, false );

		$html = '<html><body><script src="/wp-includes/js/jquery.js?ver=3.7"></script></body></html>';

		$output = $plugin->ob_callback( $html );

		$this->assertStringContainsString( 'src="/wp-includes/js/jquery.js"', $output );
		$this->assertStringNotContainsString( '?ver=3.7', $output );
	}

	/**
	 * Set private browsercache_rewrite on BrowserCache_Plugin.
	 *
	 * @since 2.10.7
	 *
	 * @param BrowserCache_Plugin $plugin  Plugin instance.
	 * @param bool                $rewrite Rewrite flag.
	 *
	 * @return void
	 */
	private function set_browsercache_rewrite( BrowserCache_Plugin $plugin, bool $rewrite ): void {
		$property = new ReflectionProperty( BrowserCache_Plugin::class, 'browsercache_rewrite' );
		$property->setAccessible( true );
		$property->setValue( $plugin, $rewrite );
	}
}
