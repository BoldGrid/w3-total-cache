<?php
/**
 * Installed themes that share a display name keep separate W3TC theme keys.
 *
 * @package W3TC\Tests
 */

use W3TC\Util_Theme;

/**
 * Tests for Util_Theme lookups when two installed themes are both named "Triage Dupe".
 */
class Util_Theme_Duplicate_Names_Test extends WP_UnitTestCase {
	/**
	 * Fixture theme root holding the duplicate-name themes.
	 *
	 * @var string
	 */
	private $theme_root = '';

	/**
	 * Register the fixture theme root before each test.
	 *
	 * @return void
	 */
	public function set_up() {
		parent::set_up();

		$this->theme_root = dirname( __FILE__ ) . '/fixtures/theme-duplicates';

		unset( $GLOBALS['wp_themes'] );
		register_theme_directory( $this->theme_root );
		search_theme_directories( true );
		wp_clean_themes_cache();
	}

	/**
	 * Remove the fixture theme root after each test.
	 *
	 * @return void
	 */
	public function tear_down() {
		global $wp_theme_directories;

		if ( is_array( $wp_theme_directories ) ) {
			$wp_theme_directories = array_values(
				array_diff( $wp_theme_directories, array( $this->theme_root ) )
			);
		}

		unset( $GLOBALS['wp_themes'] );
		search_theme_directories( true );
		wp_clean_themes_cache();

		parent::tear_down();
	}

	/**
	 * Both duplicate-name themes get their own key.
	 *
	 * @return void
	 */
	public function test_duplicate_names_get_distinct_keys() {
		$themes = Util_Theme::get_themes_by_key();

		$dupe_keys = array_keys( $themes, 'Triage Dupe', true );
		$this->assertCount( 2, $dupe_keys );
		$this->assertNotSame( $dupe_keys[0], $dupe_keys[1] );
	}

	/**
	 * Each duplicate key resolves to the copy it was computed from.
	 *
	 * @return void
	 */
	public function test_get_by_key_returns_the_matching_copy() {
		$themes = Util_Theme::get_themes_by_key();

		$stylesheets = array();
		foreach ( array_keys( $themes, 'Triage Dupe', true ) as $theme_key ) {
			$theme = Util_Theme::get_by_key( $theme_key );

			$this->assertNotEmpty( $theme );
			$this->assertSame(
				$theme_key,
				Util_Theme::get_theme_key( $theme['Theme Root'], $theme['Template'], $theme['Stylesheet'] )
			);
			$stylesheets[] = $theme['Stylesheet'];
		}

		sort( $stylesheets );
		$this->assertSame( array( 'triage-dupe-a', 'triage-dupe-b' ), $stylesheets );
	}

	/**
	 * Template groups come from the copy that owns the key, not the first copy with the name.
	 *
	 * @return void
	 */
	public function test_templates_follow_the_key() {
		$themes = Util_Theme::get_themes_by_key();

		foreach ( array_keys( $themes, 'Triage Dupe', true ) as $theme_key ) {
			$theme     = Util_Theme::get_by_key( $theme_key );
			$templates = Util_Theme::get_theme_templates( $theme_key );

			if ( 'triage-dupe-a' === $theme['Stylesheet'] ) {
				$this->assertArrayHasKey( 'page', $templates );
				$this->assertArrayNotHasKey( 'single', $templates );
			} else {
				$this->assertArrayHasKey( 'single', $templates );
				$this->assertArrayNotHasKey( 'page', $templates );
			}
		}
	}

	/**
	 * Themes with unique names keep the same key as before, and a name still resolves.
	 *
	 * @return void
	 */
	public function test_unique_theme_keeps_its_key_and_name_lookup_still_works() {
		$themes = Util_Theme::get_themes_by_key();

		$unique_keys = array_keys( $themes, 'Triage Unique', true );
		$this->assertCount( 1, $unique_keys );

		$unique_key = $unique_keys[0];
		$unique     = Util_Theme::get_by_key( $unique_key );
		$this->assertSame(
			Util_Theme::get_theme_key( $unique['Theme Root'], $unique['Template'], $unique['Stylesheet'] ),
			$unique_key
		);
		$this->assertSame( 'Triage Unique', Util_Theme::get_by_key_or_name( $unique_key )['Name'] );
		$this->assertSame( 'Triage Unique', Util_Theme::get_by_key_or_name( 'Triage Unique' )['Name'] );
	}
}
