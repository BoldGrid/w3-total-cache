<?php
/**
 * File: class-w3tc-promises-php86-test.php
 *
 * guzzlehttp/promises 2.5.3 calls the deprecated Is class from its own
 * promise sources. PHP 8.6 turns those calls into deprecation notices.
 * 2.6.0 keeps Is.php for callers and stops calling it internally.
 *
 * @package    W3TC
 * @subpackage W3TC/tests/admin
 * @since      2.11.1
 */

declare( strict_types = 1 );

/**
 * Class: W3tc_Promises_Php86_Test
 *
 * @since 2.11.1
 */
class W3tc_Promises_Php86_Test extends WP_UnitTestCase {

	/**
	 * The locked promises package is 2.6.0 or newer.
	 *
	 * @since 2.11.1
	 */
	public function test_locked_promises_is_at_least_2_6_0() {
		$lock = json_decode( file_get_contents( W3TC_DIR . '/composer.lock' ), true );
		$this->assertIsArray( $lock );

		$version = '';
		foreach ( $lock['packages'] as $package ) {
			if ( 'guzzlehttp/promises' === $package['name'] ) {
				$version = $package['version'];
				break;
			}
		}

		$this->assertNotSame( '', $version );
		$this->assertGreaterThanOrEqual( 0, version_compare( $version, '2.6.0' ) );
	}

	/**
	 * Promise sources other than Is.php do not call Is::.
	 *
	 * @since 2.11.1
	 */
	public function test_promises_sources_do_not_call_is() {
		$src = W3TC_DIR . '/vendor/guzzlehttp/promises/src';
		if ( ! is_dir( $src ) ) {
			$this->markTestSkipped( 'promises sources are not installed' );
		}

		$calls = array();
		$files = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $src ) );
		foreach ( $files as $file ) {
			if ( ! $file->isFile() || 'php' !== $file->getExtension() ) {
				continue;
			}
			if ( 'Is.php' === $file->getFilename() ) {
				continue;
			}
			$contents = file_get_contents( $file->getPathname() );
			if ( false !== strpos( $contents, 'Is::' ) ) {
				$calls[] = $file->getFilename();
			}
		}

		$this->assertSame( array(), $calls );
	}
}
