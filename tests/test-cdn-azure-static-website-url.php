<?php
/**
 * Azure static-website URLs omit the container. Blob URLs keep it.
 *
 * @package W3TC\Tests
 */

use W3TC\CdnEngine_Azure;
use W3TC\CdnEngine_Azure_MI;

/**
 * Tests for Azure public URL formatting.
 */
class Cdn_Azure_Static_Website_Url_Test extends WP_UnitTestCase {
	/**
	 * A static-website hostname has no container segment.
	 *
	 * @return void
	 */
	public function test_static_website_url_omits_container() {
		$engine = new CdnEngine_Azure(
			array(
				'user'      => 'gyptest',
				'container' => '$web',
				'cname'     => array( 'gyptest.z33.web.core.windows.net' ),
				'ssl'       => 'enabled',
			)
		);

		$this->assertSame(
			'https://gyptest.z33.web.core.windows.net/hello-world.jpg',
			$engine->_format_url( 'hello-world.jpg' )
		);
	}

	/**
	 * The blob endpoint still includes the container, including $web.
	 *
	 * @return void
	 */
	public function test_blob_url_includes_container() {
		$engine = new CdnEngine_Azure(
			array(
				'user'      => 'gyptest',
				'container' => '$web',
				'ssl'       => 'enabled',
			)
		);

		$this->assertSame(
			'https://gyptest.blob.core.windows.net/$web/hello-world.jpg',
			$engine->_format_url( 'hello-world.jpg' )
		);
	}

	/**
	 * A blob CNAME also keeps the container.
	 *
	 * @return void
	 */
	public function test_blob_cname_includes_container() {
		$engine = new CdnEngine_Azure(
			array(
				'user'      => 'gyptest',
				'container' => 'media',
				'cname'     => array( 'cdn.example.com' ),
				'ssl'       => 'enabled',
			)
		);

		$this->assertSame(
			'https://cdn.example.com/media/hello-world.jpg',
			$engine->_format_url( 'hello-world.jpg' )
		);
	}

	/**
	 * The managed-identity engine uses the same hostname rule.
	 *
	 * @return void
	 */
	public function test_managed_identity_static_website_url_omits_container() {
		$engine = new CdnEngine_Azure_MI(
			array(
				'user'      => 'gyptest',
				'container' => '$web',
				'cname'     => array( 'gyptest.z33.web.core.windows.net' ),
				'ssl'       => 'enabled',
			)
		);

		$this->assertSame(
			'https://gyptest.z33.web.core.windows.net/hello-world.jpg',
			$engine->_format_url( 'hello-world.jpg' )
		);
	}
}
