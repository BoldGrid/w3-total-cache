<?php
/**
 * File: class-w3tc-minify-environment-rules-filter-test.php
 *
 * @package    W3TC
 * @subpackage W3TC/tests/admin
 * @since      2.11.1
 */

declare( strict_types = 1 );

use W3TC\Config;
use W3TC\Minify_Environment;
use W3TC\Root_Environment;
use W3TC\Util_Rule;

/**
 * Class: W3tc_Minify_Environment_Rules_Filter_Test
 *
 * Covers the `w3tc_environment_get_required_rules` filter on the minify cache rules write path.
 * Writes go through the public `fix_on_wpadmin_request()` entry point; nginx tests only generate rules in
 * memory so the shared nginx.conf is never written.
 *
 * @since 2.11.1
 */
class W3tc_Minify_Environment_Rules_Filter_Test extends WP_UnitTestCase {
	/**
	 * Marker appended by the test filter.
	 *
	 * @var string
	 */
	const MARKER = '# W3TC-FILTER-MARKER';

	/**
	 * Rules file path.
	 *
	 * @var string
	 */
	private $rules_path;

	/**
	 * Original rules file contents, or null when the file did not exist.
	 *
	 * @var string|null
	 */
	private $original;

	/**
	 * Original SERVER_SOFTWARE value, or null when it was not set.
	 *
	 * @var string|null
	 */
	private $server_software;

	/**
	 * Remembers the rules file so it can be restored.
	 *
	 * @since 2.11.1
	 *
	 * @return void
	 */
	public function set_up() {
		parent::set_up();

		$this->rules_path = Util_Rule::get_minify_rules_cache_path();
		$this->original   = \file_exists( $this->rules_path ) ? \file_get_contents( $this->rules_path ) : null;

		$this->server_software = $_SERVER['SERVER_SOFTWARE'] ?? null; // phpcs:ignore

		if ( ! \is_dir( \dirname( $this->rules_path ) ) ) {
			\wp_mkdir_p( \dirname( $this->rules_path ) );
		}

		// Minify_Environment::fix_folders() deletes a non-writable cache folder; never let a test trigger that.
		if ( ! \is_writable( \dirname( $this->rules_path ) ) ) {
			$this->markTestSkipped( 'The minify cache folder is not writable.' );
		}

		\file_put_contents( $this->rules_path, '' );
	}

	/**
	 * Restores the rules file.
	 *
	 * @since 2.11.1
	 *
	 * @return void
	 */
	public function tear_down() {
		\remove_all_filters( 'w3tc_environment_get_required_rules' );

		if ( null === $this->server_software ) {
			unset( $_SERVER['SERVER_SOFTWARE'] );
		} else {
			$_SERVER['SERVER_SOFTWARE'] = $this->server_software; // phpcs:ignore
		}

		if ( null === $this->original ) {
			\unlink( $this->rules_path );
		} else {
			\file_put_contents( $this->rules_path, $this->original );
		}

		parent::tear_down();
	}

	/**
	 * Builds a config with minify enabled on the file engine.
	 *
	 * @return Config
	 */
	private function minify_config() {
		$config = new Config();
		$config->set( 'minify.enabled', true );
		$config->set( 'minify.engine', 'file' );
		$config->set( 'minify.rewrite', false );
		$config->set( 'browsercache.enabled', true );
		$config->set( 'browsercache.cssjs.compression', true );

		return $config;
	}

	/**
	 * Appends the marker to every required rule.
	 *
	 * @return void
	 */
	private function add_marker_filter() {
		\add_filter(
			'w3tc_environment_get_required_rules',
			static function ( $rules ) {
				foreach ( $rules as $key => $rule ) {
					$rules[ $key ]['content'] .= self::MARKER . "\n";
				}

				return $rules;
			}
		);
	}

	/**
	 * Writes the minify cache rules through the public entry point.
	 *
	 * @param Config $config Config.
	 *
	 * @return string Rules file contents.
	 */
	private function write_cache_rules( $config ) {
		( new Minify_Environment() )->fix_on_wpadmin_request( $config, true );

		return (string) \file_get_contents( $this->rules_path );
	}

	/**
	 * Generates the filtered rules in memory for the given server software (nothing is written).
	 *
	 * @param string $server_software SERVER_SOFTWARE value.
	 *
	 * @return array{0: string, 1: string} Filtered rules and the rules path used.
	 */
	private function filtered_rules_for( $server_software ) {
		$_SERVER['SERVER_SOFTWARE'] = $server_software; // phpcs:ignore

		$path   = Util_Rule::get_minify_rules_cache_path();
		$method = new \ReflectionMethod( Minify_Environment::class, 'rules_cache_filtered' );
		$method->setAccessible( true );

		return array( (string) $method->invoke( new Minify_Environment(), $this->minify_config(), $path ), $path );
	}

	/**
	 * Returns the text between the Minify cache markers.
	 *
	 * @param string $written Rules file contents.
	 *
	 * @return string
	 */
	private function between_markers( $written ) {
		$start = \strpos( $written, W3TC_MARKER_BEGIN_MINIFY_CACHE );
		$end   = \strpos( $written, W3TC_MARKER_END_MINIFY_CACHE );

		$this->assertNotFalse( $start );
		$this->assertNotFalse( $end );
		$this->assertLessThan( $end, $start );

		return \substr( $written, $start, $end - $start );
	}

	/**
	 * Adds a filter that appends one descriptor for the minify cache file.
	 *
	 * @param string   $line     Line to add.
	 * @param int|null $position Optional position.
	 * @param bool     $flagged  Whether the descriptor carries the minify_cache section.
	 * @param string   $path     Optional file the descriptor targets; defaults to the minify cache rules file.
	 *
	 * @return void
	 */
	private function add_descriptor_filter( $line, $position = null, $flagged = false, $path = null ) {
		$path = $path ?? $this->rules_path;

		\add_filter(
			'w3tc_environment_get_required_rules',
			static function ( $rules ) use ( $line, $position, $flagged, $path ) {
				$descriptor = array(
					'filename' => $path,
					'content'  => $line . "\n",
				);

				if ( null !== $position ) {
					$descriptor['position'] = $position;
				}

				if ( $flagged ) {
					$descriptor['section'] = 'minify_cache';
				}

				$rules[] = $descriptor;

				return $rules;
			}
		);
	}

	/**
	 * The written file includes changes made through the filter, inside the markers.
	 *
	 * @return void
	 */
	public function test_written_minify_rules_include_the_filter() {
		$this->add_marker_filter();

		$written = $this->write_cache_rules( $this->minify_config() );

		$this->assertStringContainsString( self::MARKER, $written );
		$this->assertStringContainsString( self::MARKER, $this->between_markers( $written ) );
	}

	/**
	 * Without a filter the written file is unchanged.
	 *
	 * @return void
	 */
	public function test_written_minify_rules_without_filter_have_no_marker() {
		$written = $this->write_cache_rules( $this->minify_config() );

		$this->assertStringNotContainsString( self::MARKER, $written );
		$this->assertStringContainsString( W3TC_MARKER_BEGIN_MINIFY_CACHE, $written );
	}

	/**
	 * Rules for other files are not written into the minify cache file.
	 *
	 * @return void
	 */
	public function test_written_minify_rules_ignore_rules_for_other_files() {
		\add_filter(
			'w3tc_environment_get_required_rules',
			static function ( $rules ) {
				$rules[] = array(
					'filename' => '/nonexistent/other.conf',
					'content'  => self::MARKER . "\n",
				);

				return $rules;
			}
		);

		$written = $this->write_cache_rules( $this->minify_config() );

		$this->assertStringNotContainsString( self::MARKER, $written );
	}

	/**
	 * Filter-added rules stay inside the markers and a rewrite after a config change does not duplicate them.
	 *
	 * @return void
	 */
	public function test_filter_added_rules_are_idempotent_across_rewrites() {
		$this->add_descriptor_filter( self::MARKER );

		$config  = $this->minify_config();
		$first   = $this->write_cache_rules( $config );
		$changed = $this->minify_config();
		$changed->set( 'browsercache.cssjs.compression', false );
		$second = $this->write_cache_rules( $changed );
		$third  = $this->write_cache_rules( $config );

		$this->assertNotSame( $first, $second, 'The config change must change the generated rules.' );

		foreach ( array( $first, $second, $third ) as $written ) {
			$this->assertSame( 1, \substr_count( $written, self::MARKER ) );
			$this->assertSame( 1, \substr_count( $written, W3TC_MARKER_BEGIN_MINIFY_CACHE ) );
			$this->assertSame( 1, \substr_count( $written, W3TC_MARKER_END_MINIFY_CACHE ) );
			$this->assertStringContainsString( self::MARKER, $this->between_markers( $written ) );
		}
	}

	/**
	 * The `position` key merges into the base content and orders the other descriptors, all inside the markers.
	 *
	 * @return void
	 */
	public function test_position_merges_descriptors_inside_the_markers() {
		$this->add_descriptor_filter( '# W3TC-POS-ZERO', 0 );
		$this->add_descriptor_filter( '# W3TC-POS-FIVE', 5 );
		$this->add_descriptor_filter( '# W3TC-POS-NONE' );

		$written = $this->write_cache_rules( $this->minify_config() );
		$inside  = $this->between_markers( $written );

		foreach ( array( '# W3TC-POS-ZERO', '# W3TC-POS-FIVE', '# W3TC-POS-NONE' ) as $line ) {
			$this->assertSame( 1, \substr_count( $written, $line ) );
			$this->assertStringContainsString( $line, $inside );
		}

		// Position 0 joins the base entry, so it follows the generated rules and precedes later entries.
		$this->assertLessThan( \strpos( $written, '# W3TC-POS-ZERO' ), \strrpos( $written, '</IfModule>' ) );
		// Entries are joined in key order of insertion: position 5 is added before the next unpositioned entry (key 6).
		$this->assertLessThan( \strpos( $written, '# W3TC-POS-FIVE' ), \strpos( $written, '# W3TC-POS-ZERO' ) );
		$this->assertLessThan( \strpos( $written, '# W3TC-POS-NONE' ), \strpos( $written, '# W3TC-POS-FIVE' ) );
	}

	/**
	 * Callbacks get the minify_cache context and one flagged descriptor; two-argument callbacks keep working.
	 *
	 * @return void
	 */
	public function test_filter_receives_context_and_flagged_descriptor() {
		$received = array();

		\add_filter(
			'w3tc_environment_get_required_rules',
			static function ( $rules, $config, $context = null ) use ( &$received ) {
				$received = array( $rules, $context );

				return $rules;
			},
			10,
			3
		);
		$this->add_marker_filter(); // Registered with the default single argument.

		$written = $this->write_cache_rules( $this->minify_config() );

		$this->assertSame( 'minify_cache', $received[1] );
		$this->assertCount( 1, $received[0] );
		$this->assertSame( 'minify_cache', $received[0][0]['section'] );
		$this->assertSame( $this->rules_path, $received[0][0]['filename'] );
		$this->assertStringContainsString( self::MARKER, $written );
	}

	/**
	 * On nginx the path is the shared nginx.conf: only descriptors flagged for the minify cache are merged.
	 *
	 * @return void
	 */
	public function test_nginx_merges_only_flagged_descriptors() {
		$nginx_path = Util_Rule::get_nginx_rules_path();

		$this->add_descriptor_filter( '# W3TC-UNFLAGGED', null, false, $nginx_path );
		$this->add_descriptor_filter( '# W3TC-FLAGGED', null, true, $nginx_path );

		list( $rules, $path ) = $this->filtered_rules_for( 'nginx/1.25' );

		$this->assertSame( Util_Rule::get_nginx_rules_path(), $path );
		$this->assertStringNotContainsString( '# W3TC-UNFLAGGED', $rules );
		$this->assertSame( 1, \substr_count( $rules, '# W3TC-FLAGGED' ) );
		$this->assertStringContainsString( '# W3TC-FLAGGED', $this->between_markers( $rules ) );
		$this->assertStringEndsWith( W3TC_MARKER_END_MINIFY_CACHE . "\n", $rules );
	}

	/**
	 * On nginx, editing the base descriptor in place still works because it carries the flag.
	 *
	 * @return void
	 */
	public function test_nginx_in_place_edit_of_base_descriptor_is_kept() {
		$this->add_marker_filter();

		list( $rules ) = $this->filtered_rules_for( 'nginx/1.25' );

		$this->assertStringContainsString( self::MARKER, $this->between_markers( $rules ) );
	}

	/**
	 * On Apache the file is dedicated to Minify, so a matching descriptor is merged without the flag.
	 *
	 * @return void
	 */
	public function test_apache_merges_unflagged_descriptors_for_the_cache_file() {
		$this->add_descriptor_filter( '# W3TC-UNFLAGGED' );

		list( $rules ) = $this->filtered_rules_for( 'Apache/2.4' );

		$this->assertStringContainsString( '# W3TC-UNFLAGGED', $this->between_markers( $rules ) );
	}

	/**
	 * A filter result that is not an array falls back to the unfiltered rules and keeps the file.
	 *
	 * @return void
	 */
	public function test_non_array_filter_result_falls_back_to_unfiltered_rules() {
		list( $unfiltered ) = $this->filtered_rules_for( 'Apache/2.4' );
		$this->assertNotSame( '', $unfiltered );

		foreach ( array( null, 'string', false, 5 ) as $bad_result ) {
			\remove_all_filters( 'w3tc_environment_get_required_rules' );
			\add_filter(
				'w3tc_environment_get_required_rules',
				static function () use ( $bad_result ) {
					return $bad_result;
				}
			);

			$written = $this->write_cache_rules( $this->minify_config() );

			$this->assertSame( \trim( $unfiltered ), \trim( $written ) );
			$this->assertDirectoryExists( \dirname( $this->rules_path ) );
		}
	}

	/**
	 * A filter that removes the minify descriptor keeps the unfiltered rules.
	 *
	 * @return void
	 */
	public function test_removed_minify_descriptor_keeps_unfiltered_rules() {
		list( $unfiltered ) = $this->filtered_rules_for( 'Apache/2.4' );

		foreach ( array( array(), array( array( 'filename' => '/nonexistent/other.conf', 'content' => 'x' ) ) ) as $result ) {
			\remove_all_filters( 'w3tc_environment_get_required_rules' );
			\add_filter(
				'w3tc_environment_get_required_rules',
				static function () use ( $result ) {
					return $result;
				}
			);

			$written = $this->write_cache_rules( $this->minify_config() );

			$this->assertSame( \trim( $unfiltered ), \trim( $written ) );
		}
	}

	/**
	 * Malformed descriptors are skipped without notices.
	 *
	 * @return void
	 */
	public function test_malformed_descriptors_are_skipped() {
		$path = $this->rules_path;

		\add_filter(
			'w3tc_environment_get_required_rules',
			static function ( $rules ) use ( $path ) {
				$rules[] = array( 'filename' => $path );
				$rules[] = 'not-an-array';
				$rules[] = array(
					'filename' => $path,
					'content'  => array( 'nope' ),
				);

				return $rules;
			}
		);

		$written = $this->write_cache_rules( $this->minify_config() );

		$this->assertStringContainsString( W3TC_MARKER_BEGIN_MINIFY_CACHE, $written );
	}

	/**
	 * The aggregate required rules API still runs the filter, without the minify_cache context.
	 *
	 * @return void
	 */
	public function test_required_rules_api_runs_the_filter() {
		$this->add_descriptor_filter( self::MARKER );

		$rules = ( new Root_Environment() )->get_required_rules( $this->minify_config() );

		$this->assertArrayHasKey( $this->rules_path, $rules );
		$this->assertStringContainsString( self::MARKER, $rules[ $this->rules_path ]['content'] );
	}
}
