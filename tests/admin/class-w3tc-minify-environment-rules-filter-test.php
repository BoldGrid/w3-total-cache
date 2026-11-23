<?php
/**
 * File: class-w3tc-minify-environment-rules-filter-test.php
 *
 * @package    W3TC
 * @subpackage W3TC/tests/admin
 * @since      X.X.X
 */

declare( strict_types = 1 );

use W3TC\Config;
use W3TC\Minify_Environment;
use W3TC\Root_Environment;
use W3TC\Util_Environment_Exceptions;
use W3TC\Util_Rule;

/**
 * Class: W3tc_Minify_Environment_Rules_Filter_Test
 *
 * Covers the `w3tc_environment_get_required_rules` filter on the minify cache rules write path.
 *
 * @since X.X.X
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
	 * Remembers the rules file so it can be restored.
	 *
	 * @since X.X.X
	 *
	 * @return void
	 */
	public function set_up() {
		parent::set_up();

		$this->rules_path = Util_Rule::get_minify_rules_cache_path();
		$this->original   = \file_exists( $this->rules_path ) ? \file_get_contents( $this->rules_path ) : null;

		if ( ! \is_dir( \dirname( $this->rules_path ) ) ) {
			\wp_mkdir_p( \dirname( $this->rules_path ) );
		}

		\file_put_contents( $this->rules_path, '' );
	}

	/**
	 * Restores the rules file.
	 *
	 * @since X.X.X
	 *
	 * @return void
	 */
	public function tear_down() {
		\remove_all_filters( 'w3tc_environment_get_required_rules' );

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
	 * Writes the minify cache rules through the direct handler path.
	 *
	 * @param Config $config Config.
	 *
	 * @return string Rules file contents.
	 */
	private function write_cache_rules( $config ) {
		$method = new \ReflectionMethod( Minify_Environment::class, 'rules_cache_add' );
		$method->setAccessible( true );
		$method->invoke( new Minify_Environment(), $config, new Util_Environment_Exceptions() );

		return (string) \file_get_contents( $this->rules_path );
	}

	/**
	 * The written file includes changes made through the filter.
	 *
	 * @return void
	 */
	public function test_written_minify_rules_include_the_filter() {
		$this->add_marker_filter();

		$written = $this->write_cache_rules( $this->minify_config() );

		$this->assertStringContainsString( self::MARKER, $written );
		$this->assertStringContainsString( W3TC_MARKER_BEGIN_MINIFY_CACHE, $written );
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
	 * The aggregate required rules API still runs the filter.
	 *
	 * @return void
	 */
	public function test_required_rules_api_runs_the_filter() {
		$this->add_marker_filter();

		$rules = ( new Root_Environment() )->get_required_rules( $this->minify_config() );

		$this->assertArrayHasKey( $this->rules_path, $rules );
		$this->assertStringContainsString( self::MARKER, $rules[ $this->rules_path ]['content'] );
	}
}
