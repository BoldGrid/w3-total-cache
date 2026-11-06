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
 * @since      X.X.X
 */

declare( strict_types = 1 );

use GuzzleHttp\Promise\FulfilledPromise;
use GuzzleHttp\Promise\Promise;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Promise\RejectedPromise;
use GuzzleHttp\Promise\Utils;

/**
 * Class: W3tc_Promises_Php86_Test
 *
 * @since X.X.X
 */
class W3tc_Promises_Php86_Test extends WP_UnitTestCase {

	/**
	 * The locked promises package is 2.6.0 or newer.
	 *
	 * @since X.X.X
	 */
	public function test_locked_promises_is_at_least_2_6_0() {
		$lock = \json_decode( \file_get_contents( W3TC_DIR . '/composer.lock' ), true );
		$this->assertIsArray( $lock );

		$version = '';
		foreach ( $lock['packages'] as $package ) {
			if ( 'guzzlehttp/promises' === $package['name'] ) {
				$version = $package['version'];
				break;
			}
		}

		$this->assertNotSame( '', $version );
		$this->assertGreaterThanOrEqual( 0, \version_compare( $version, '2.6.0' ) );
	}

	/**
	 * Bundled sources other than Is.php do not reference the Is class.
	 *
	 * @since X.X.X
	 */
	public function test_bundled_sources_do_not_reference_is() {
		$src = W3TC_DIR . '/vendor/guzzlehttp/promises/src';
		if ( ! \is_dir( $src ) ) {
			$this->markTestSkipped( 'promises sources are not installed' );
		}

		$is_file = \realpath( $src . '/Is.php' );
		$calls   = array();
		$files   = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator( W3TC_DIR . '/vendor', FilesystemIterator::SKIP_DOTS )
		);
		foreach ( $files as $file ) {
			if ( ! $file->isFile() || 'php' !== $file->getExtension() ) {
				continue;
			}
			if ( $is_file === $file->getRealPath() ) {
				continue;
			}
			$contents = \file_get_contents( $file->getPathname() );
			if ( \preg_match( '/(?<![\\\\\w])Is::|Promise\\\\Is\b/', $contents ) ) {
				$calls[] = \substr( $file->getPathname(), \strlen( W3TC_DIR ) );
			}
		}

		$this->assertSame( array(), $calls );
	}

	/**
	 * Promises still settle without loading the Is class.
	 *
	 * @since X.X.X
	 */
	public function test_promises_settle_without_loading_is() {
		$pending = new Promise();
		$this->assertSame( PromiseInterface::PENDING, $pending->getState() );

		$settled = Utils::settle(
			array(
				'fulfilled' => new FulfilledPromise( 'ok' ),
				'rejected'  => new RejectedPromise( 'no' ),
				'pending'   => $pending,
			)
		);
		$pending->resolve( 'later' );
		$results = $settled->wait();
		\ksort( $results );

		$this->assertSame(
			array(
				'fulfilled' => array(
					'state' => PromiseInterface::FULFILLED,
					'value' => 'ok',
				),
				'pending'   => array(
					'state' => PromiseInterface::FULFILLED,
					'value' => 'later',
				),
				'rejected'  => array(
					'state'  => PromiseInterface::REJECTED,
					'reason' => 'no',
				),
			),
			$results
		);
		$this->assertFalse( \class_exists( 'GuzzleHttp\Promise\Is', false ) );
	}
}
