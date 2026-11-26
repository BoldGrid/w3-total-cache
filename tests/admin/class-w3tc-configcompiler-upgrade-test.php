<?php
/**
 * File: class-w3tc-configcompiler-upgrade-test.php
 *
 * @package    W3TC
 * @subpackage W3TC/tests/admin
 * @since      2.11.1
 */

declare( strict_types = 1 );

use W3TC\ConfigCompiler;

/**
 * Class: W3tc_ConfigCompiler_Upgrade_Test
 *
 * Covers the version-gated lazy load exclude migration in `ConfigCompiler::upgrade()`.
 *
 * `ConfigCompiler::save()` writes every key, so configs saved before the release that added
 * `fetchpriority="high"` to the defaults hold the old list and never pick up the new default.
 *
 * @since 2.11.1
 */
class W3tc_ConfigCompiler_Upgrade_Test extends WP_UnitTestCase {
	/**
	 * Lazy load exclude list as shipped before `fetchpriority="high"` became a default.
	 *
	 * @since 2.11.1
	 *
	 * @return string[]
	 */
	private function pre_change_excludes() {
		return array(
			'avia-bg-style-fixed',
			'data-src=',
			'data-srcset=',
			'fullurl=',
			'loading="eager"',
			'no-lazy',
			'skip-lazy',
			'skip_lazy',
		);
	}

	/**
	 * Run the private `ConfigCompiler::upgrade()` step on stored data.
	 *
	 * Both load paths (master and child) call `upgrade()`, so this exercises the shared step.
	 *
	 * @since 2.11.1
	 *
	 * @param array $file_data Stored config data.
	 *
	 * @return array
	 */
	private function upgrade( array $file_data ) {
		$compiler = new ConfigCompiler( 0, false );
		$method   = new ReflectionMethod( ConfigCompiler::class, 'upgrade' );
		$method->setAccessible( true );

		return $method->invoke( $compiler, $file_data );
	}

	/**
	 * A config saved at the previous release gains the entry, keeping the existing order.
	 *
	 * @since 2.11.1
	 */
	public function test_old_version_config_gains_fetchpriority_exclude() {
		$old = $this->pre_change_excludes();

		$result = $this->upgrade(
			array(
				'version'          => '2.11.0',
				'lazyload.exclude' => $old,
			)
		);

		$this->assertSame(
			array_merge( $old, array( 'fetchpriority="high"', "fetchpriority='high'" ) ),
			$result['lazyload.exclude']
		);
	}

	/**
	 * The same migration applies through the public master load path.
	 *
	 * @since 2.11.1
	 */
	public function test_master_load_applies_fetchpriority_migration() {
		$compiler = new ConfigCompiler( 0, false );
		$compiler->load(
			array(
				'version'          => '2.11.0',
				'lazyload.exclude' => $this->pre_change_excludes(),
			)
		);

		$data = $compiler->get_data();
		$this->assertContains( 'fetchpriority="high"', $data['lazyload.exclude'] );
		$this->assertContains( 'skip_lazy', $data['lazyload.exclude'] );
	}

	/**
	 * A config already at the version that ships the default must not get the entry re-added
	 * after the user removed it.
	 *
	 * @since 2.11.1
	 */
	public function test_new_version_config_without_entry_is_not_readded() {
		$custom = array( 'no-lazy', 'loading="eager"' );

		$result = $this->upgrade(
			array(
				'version'          => '2.11.1',
				'lazyload.exclude' => $custom,
			)
		);

		$this->assertSame( $custom, $result['lazyload.exclude'] );
	}

	/**
	 * An entry that is already present is not duplicated, including when stored with the other entries.
	 *
	 * @since 2.11.1
	 */
	public function test_existing_entry_is_not_duplicated() {
		$stored = array( 'fetchpriority="high"', "fetchpriority='high'", 'no-lazy' );

		$result = $this->upgrade(
			array(
				'version'          => '2.11.0',
				'lazyload.exclude' => $stored,
			)
		);

		$this->assertSame( $stored, $result['lazyload.exclude'] );

		$partial = $this->upgrade(
			array(
				'version'          => '2.11.0',
				'lazyload.exclude' => array( 'no-lazy', 'fetchpriority="high"' ),
			)
		);

		$this->assertSame( array( 'no-lazy', 'fetchpriority="high"', "fetchpriority='high'" ), $partial['lazyload.exclude'] );
	}

	/**
	 * An exclude list stored as a newline-separated string is split into entries and migrated.
	 *
	 * @since 2.11.1
	 */
	public function test_string_exclude_is_migrated() {
		$result = $this->upgrade(
			array(
				'version'          => '2.11.0',
				'lazyload.exclude' => "no-lazy\r\nloading=\"eager\"\n\nskip_lazy",
			)
		);

		$this->assertSame(
			array( 'no-lazy', 'loading="eager"', 'skip_lazy', 'fetchpriority="high"', "fetchpriority='high'" ),
			$result['lazyload.exclude']
		);

		$present = $this->upgrade(
			array(
				'version'          => '2.11.0',
				'lazyload.exclude' => "no-lazy\nfetchpriority=\"high\"\nfetchpriority='high'",
			)
		);

		$this->assertSame( array( 'no-lazy', 'fetchpriority="high"', "fetchpriority='high'" ), $present['lazyload.exclude'] );
	}

	/**
	 * A config without a stored exclude key is left alone so defaults (or a master list) apply.
	 *
	 * @since 2.11.1
	 */
	public function test_config_without_exclude_key_is_not_given_one() {
		$result = $this->upgrade( array( 'version' => '2.11.0' ) );

		$this->assertArrayNotHasKey( 'lazyload.exclude', $result );
	}
}
