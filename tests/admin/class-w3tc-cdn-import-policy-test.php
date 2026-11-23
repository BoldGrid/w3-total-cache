<?php
/**
 * File: class-w3tc-cdn-import-policy-test.php
 *
 * @package    W3TC
 * @subpackage W3TC/tests/admin
 * @since      2.10.6
 */

declare( strict_types = 1 );

use W3TC\Cdn_Util;

/**
 * Class: W3tc_Cdn_Import_Policy_Test
 *
 * Media Library import must not honor a browser-supplied mask that
 * broadens past the static-asset allowlist. Empty / `*` / `*.*` used
 * to compile to a regexp that matched every URL.
 *
 * @since 2.10.6
 */
class W3tc_Cdn_Import_Policy_Test extends WP_UnitTestCase {

	/**
	 * Empty / false / wildcard masks resolve to the static allowlist,
	 * never "every extension".
	 *
	 * @since 2.10.6
	 */
	public function test_empty_and_wildcard_masks_are_allowlist() {
		$allowlist = Cdn_Util::import_allowed_extensions();

		$this->assertSame( $allowlist, Cdn_Util::import_extensions_from_mask( '' ) );
		$this->assertSame( $allowlist, Cdn_Util::import_extensions_from_mask( false ) );
		$this->assertSame( $allowlist, Cdn_Util::import_extensions_from_mask( '*' ) );
		$this->assertSame( $allowlist, Cdn_Util::import_extensions_from_mask( '*.*' ) );
		$this->assertContains( 'jpg', $allowlist );
		$this->assertNotContains( 'php', $allowlist );
		$this->assertNotContains( 'svg', $allowlist );
		$this->assertNotContains( 'html', $allowlist );
		$this->assertNotContains( 'swf', $allowlist );
	}

	/**
	 * Operator tokens outside the allowlist are dropped, including PHP.
	 *
	 * @since 2.10.6
	 */
	public function test_mask_cannot_broaden_past_allowlist() {
		$this->assertSame(
			array( 'jpg' ),
			Cdn_Util::import_extensions_from_mask( '*.jpg;*.php' )
		);
		$this->assertSame(
			array(),
			Cdn_Util::import_extensions_from_mask( '*.php' )
		);
		$this->assertSame(
			array( 'png', 'css' ),
			Cdn_Util::import_extensions_from_mask( '*.PNG;*.css;*.svg;*.html' )
		);
	}

	/**
	 * Empty / wildcard masks accept static assets and reject PHP.
	 *
	 * @since 2.10.6
	 *
	 * @dataProvider empty_like_masks
	 *
	 * @param string|bool $mask Operator mask.
	 */
	public function test_empty_like_mask_accepts_static_rejects_php( $mask ) {
		$this->assertTrue(
			Cdn_Util::url_matches_import_policy( 'https://cdn.example.com/a.jpg', $mask )
		);
		$this->assertTrue(
			Cdn_Util::url_matches_import_policy( '/wp-content/uploads/a.PNG', $mask )
		);
		$this->assertFalse(
			Cdn_Util::url_matches_import_policy( 'https://cdn.example.com/shell.php', $mask )
		);
		$this->assertFalse(
			Cdn_Util::url_matches_import_policy( 'https://cdn.example.com/page.html', $mask )
		);
	}

	/**
	 * Masks that behave like "use the allowlist".
	 *
	 * @since 2.10.6
	 *
	 * @return array<string,array{0:string|bool}>
	 */
	public function empty_like_masks() {
		return array(
			'empty string' => array( '' ),
			'false'        => array( false ),
			'star'         => array( '*' ),
			'star-dot-star' => array( '*.*' ),
		);
	}

	/**
	 * Mixed operator mask keeps jpg, drops php, rejects missing ext.
	 *
	 * @since 2.10.6
	 */
	public function test_mixed_mask_and_missing_extension() {
		$mask = '*.jpg;*.php';

		$this->assertTrue(
			Cdn_Util::url_matches_import_policy( 'https://cdn.example.com/photo.jpg?v=2', $mask )
		);
		$this->assertTrue(
			Cdn_Util::url_matches_import_policy( 'https://cdn.example.com/photo.JPG', $mask )
		);
		$this->assertFalse(
			Cdn_Util::url_matches_import_policy( 'https://cdn.example.com/shell.php', $mask )
		);
		$this->assertFalse(
			Cdn_Util::url_matches_import_policy( 'https://cdn.example.com/photo.png', $mask )
		);
		$this->assertFalse(
			Cdn_Util::url_matches_import_policy( 'https://cdn.example.com/no-extension', $mask )
		);
	}

	/**
	 * A PHP-only mask imports nothing.
	 *
	 * @since 2.10.6
	 */
	public function test_php_only_mask_imports_nothing() {
		$mask = '*.php';

		$this->assertFalse(
			Cdn_Util::url_matches_import_policy( 'https://cdn.example.com/shell.php', $mask )
		);
		$this->assertFalse(
			Cdn_Util::url_matches_import_policy( 'https://cdn.example.com/photo.jpg', $mask )
		);
	}

	/**
	 * Compound names with a forbidden inner label are rejected;
	 * minified static assets are not.
	 *
	 * @since 2.10.6
	 */
	public function test_compound_extension_policy() {
		$mask = '*.jpg;*.js';

		$this->assertFalse(
			Cdn_Util::url_matches_import_policy( 'https://cdn.example.com/file.php.jpg', $mask )
		);
		$this->assertFalse(
			Cdn_Util::url_matches_import_policy( 'https://cdn.example.com/file.PHP.jpg#x', $mask )
		);
		$this->assertTrue(
			Cdn_Util::url_matches_import_policy( 'https://cdn.example.com/jquery.min.js', $mask )
		);
	}

	/**
	 * Fragments that smuggle a forbidden extension are rejected;
	 * ordinary HTML anchors are not. Destination names use the
	 * stripped path so `#.php` cannot become a `.php` upload.
	 *
	 * @since 2.10.6
	 */
	public function test_fragment_cannot_override_extension() {
		$mask = '*';

		$this->assertFalse(
			Cdn_Util::url_matches_import_policy( 'https://cdn.example.com/safe.jpg#.php', $mask )
		);
		$this->assertFalse(
			Cdn_Util::url_matches_import_policy( 'https://cdn.example.com/safe.jpg#shell.php', $mask )
		);
		$this->assertFalse(
			Cdn_Util::url_matches_import_policy( 'https://cdn.example.com/safe.jpg?v=1#.php', $mask )
		);
		$this->assertTrue(
			Cdn_Util::url_matches_import_policy( 'https://cdn.example.com/safe.jpg#section', $mask )
		);
		$this->assertSame(
			'jpg',
			Cdn_Util::import_url_extension( 'https://cdn.example.com/safe.jpg#.php' )
		);
		$this->assertSame(
			'jpg',
			Cdn_Util::import_url_extension( 'https://cdn.example.com/safe.jpg?file=x.php' )
		);
	}

	/**
	 * import_library must not compile the operator mask with
	 * get_regexp_by_mask (empty mask → match-everything).
	 *
	 * @since 2.10.6
	 */
	public function test_import_library_uses_policy_helper() {
		$source = (string) file_get_contents(
			dirname( __DIR__, 2 ) . '/Cdn_Core_Admin.php'
		);

		$this->assertStringContainsString(
			'Cdn_Util::url_matches_import_policy',
			$source
		);
		$this->assertStringContainsString(
			'Cdn_Util::import_url_path( $src )',
			$source
		);
		$this->assertStringContainsString(
			'Cdn_Util::import_url_extension( $src )',
			$source
		);
		$this->assertDoesNotMatchRegularExpression(
			'/function import_library[\s\S]*get_regexp_by_mask[\s\S]*function get_import_posts_count/m',
			$source
		);
	}
}
