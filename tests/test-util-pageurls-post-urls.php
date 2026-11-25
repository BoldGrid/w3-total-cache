<?php
/**
 * Post URLs follow a permalink change within one request.
 *
 * @package W3TC\Tests
 */

use W3TC\Dispatcher;
use W3TC\PgCache_Flush;
use W3TC\Util_PageUrls;

/**
 * Tests for Util_PageUrls::get_post_urls() and the page cache post flush.
 */
class Util_PageUrls_Post_Urls_Test extends WP_UnitTestCase {
	/**
	 * Category slugs by name.
	 *
	 * @var array
	 */
	private $cats = array();

	/**
	 * Use a category based permalink structure and two categories.
	 *
	 * @return void
	 */
	public function set_up() {
		parent::set_up();

		$this->set_permalink_structure( '/%category%/%postname%/' );

		$this->cats['alpha'] = self::factory()->category->create( array( 'slug' => 'alpha' ) );
		$this->cats['beta']  = self::factory()->category->create( array( 'slug' => 'beta' ) );
	}

	/**
	 * Create a published post in the alpha category.
	 *
	 * @return int
	 */
	private function create_post() {
		return self::factory()->post->create(
			array(
				'post_title'    => 'Hello',
				'post_name'     => 'hello',
				'post_status'   => 'publish',
				'post_category' => array( $this->cats['alpha'] ),
			)
		);
	}

	/**
	 * A first lookup returns the current permalink.
	 *
	 * @return void
	 */
	public function test_first_lookup_uses_the_current_permalink() {
		$post_id = $this->create_post();

		$this->assertSame( array( get_permalink( $post_id ) ), Util_PageUrls::get_post_urls( $post_id ) );
	}

	/**
	 * A lookup after a category change returns the new permalink.
	 *
	 * @return void
	 */
	public function test_post_urls_follow_a_category_change() {
		$post_id = $this->create_post();
		$old_url = get_permalink( $post_id );

		$this->assertSame( array( $old_url ), Util_PageUrls::get_post_urls( $post_id ) );

		wp_set_post_categories( $post_id, array( $this->cats['beta'] ) );
		$new_url = get_permalink( $post_id );

		$this->assertNotSame( $old_url, $new_url );
		$this->assertSame( array( $new_url ), Util_PageUrls::get_post_urls( $post_id ) );
	}

	/**
	 * Flushing before and after a category change queues both URLs.
	 *
	 * @return void
	 */
	public function test_post_flush_queues_old_and_new_urls_after_a_category_change() {
		$config = Dispatcher::config();
		$config->set( 'pgcache.purge.post', true );

		$post_id = $this->create_post();
		$old_url = get_permalink( $post_id );
		$flush   = new PgCache_Flush();

		$flush->flush_post( $post_id );

		wp_set_post_categories( $post_id, array( $this->cats['beta'] ) );
		$new_url = get_permalink( $post_id );

		$flush->flush_post( $post_id );

		$property = new ReflectionProperty( PgCache_Flush::class, 'queued_urls' );
		$property->setAccessible( true );
		$queued = array_keys( $property->getValue( $flush ) );

		$this->assertContains( $old_url, $queued );
		$this->assertContains( $new_url, $queued );
	}
}
