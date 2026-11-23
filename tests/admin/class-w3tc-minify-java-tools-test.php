<?php
/**
 * File: class-w3tc-minify-java-tools-test.php
 *
 * @package    W3TC
 * @subpackage W3TC/tests/admin
 * @since      2.10.6
 */

declare( strict_types = 1 );

use W3TC\Dispatcher;
use W3TC\Minify_ContentMinifier;
use W3TC\Util_Java;

/**
 * Class: W3tc_Minify_Java_Tools_Test
 *
 * Execution-boundary coverage: Java-backed minifiers must not assign
 * vendored `$javaExecutable` / `$jarFile` from unvalidated config.
 *
 * @since 2.10.6
 */
class W3tc_Minify_Java_Tools_Test extends WP_UnitTestCase {

	/**
	 * Write a minimal ZIP/JAR (empty EOCD) to $path.
	 *
	 * @since 2.10.6
	 *
	 * @param string $path Destination path ending in .jar.
	 *
	 * @return void
	 */
	private static function write_dummy_jar( $path ) {
		$data = "PK\x05\x06" . str_repeat( "\x00", 18 );
		file_put_contents( $path, $data );
	}

	/**
	 * Rejected JAR paths never reach the vendored statics.
	 *
	 * @since 2.10.6
	 */
	public function test_init_does_not_assign_rejected_jar() {
		$java = '/usr/bin/env';
		if ( false === Util_Java::validate( $java ) ) {
			$this->markTestSkipped( 'No allowlisted Java-style binary available on this host.' );
		}

		$outside = sys_get_temp_dir() . '/w3tc-java-test-init-' . wp_generate_password( 8, false ) . '.jar';
		self::write_dummy_jar( $outside );

		$config = Dispatcher::config();
		$config->set( 'minify.yuijs.path.java', $java );
		$config->set( 'minify.yuijs.path.jar', $outside );

		\W3TCL\Minify\Minify_YUICompressor::$javaExecutable = 'sentinel-java';
		\W3TCL\Minify\Minify_YUICompressor::$jarFile        = 'sentinel-jar';

		try {
			$minifier = new Minify_ContentMinifier();
			$this->assertFalse( $minifier->available( 'yuijs' ) );
			$this->assertFalse( $minifier->init( 'yuijs' ) );
			$this->assertSame( 'sentinel-java', \W3TCL\Minify\Minify_YUICompressor::$javaExecutable );
			$this->assertSame( 'sentinel-jar', \W3TCL\Minify\Minify_YUICompressor::$jarFile );
		} finally {
			@unlink( $outside );
			$config->set( 'minify.yuijs.path.java', 'java' );
			$config->set( 'minify.yuijs.path.jar', 'yuicompressor.jar' );
		}
	}

	/**
	 * Valid tools are canonicalized onto the vendored statics.
	 *
	 * @since 2.10.6
	 */
	public function test_init_assigns_canonical_validated_paths() {
		$java = Util_Java::validate( '/usr/bin/env' );
		if ( false === $java ) {
			$this->markTestSkipped( 'No allowlisted Java-style binary available on this host.' );
		}

		$jar = W3TC_DIR . '/tests/w3tc-java-test-init-ok.jar';
		self::write_dummy_jar( $jar );

		$config = Dispatcher::config();
		$config->set( 'minify.yuijs.path.java', $java );
		$config->set( 'minify.yuijs.path.jar', $jar );

		try {
			$minifier = new Minify_ContentMinifier();
			$this->assertTrue( $minifier->available( 'yuijs' ) );
			$this->assertTrue( $minifier->init( 'yuijs' ) );
			$this->assertSame( $java, \W3TCL\Minify\Minify_YUICompressor::$javaExecutable );
			$this->assertSame( realpath( $jar ), \W3TCL\Minify\Minify_YUICompressor::$jarFile );
		} finally {
			@unlink( $jar );
			$config->set( 'minify.yuijs.path.java', 'java' );
			$config->set( 'minify.yuijs.path.jar', 'yuicompressor.jar' );
			\W3TCL\Minify\Minify_YUICompressor::$javaExecutable = 'java';
			\W3TCL\Minify\Minify_YUICompressor::$jarFile        = null;
		}
	}

	/**
	 * Wrapper sources must not assign vendored $jarFile from raw request/config strings.
	 *
	 * @since 2.10.6
	 */
	public function test_wrappers_do_not_assign_raw_jar_paths() {
		$files = array(
			W3TC_DIR . '/Minify_ContentMinifier.php',
			W3TC_DIR . '/Generic_AdminActions_Test.php',
			W3TC_DIR . '/Minify_Environment.php',
		);

		foreach ( $files as $file ) {
			$src = file_get_contents( $file );
			$this->assertNotFalse( $src );
			$this->assertSame(
				0,
				preg_match( '/\$jarFile\s*=\s*\$path_jar/', $src ),
				$file . ' must not assign $jarFile from raw $path_jar'
			);
			$this->assertSame(
				0,
				preg_match( '/\$jarFile\s*=\s*\$this->_config/', $src ),
				$file . ' must not assign $jarFile from raw config'
			);
			$this->assertSame(
				0,
				preg_match( '/file_exists\(\s*\$path_jar\s*\)/', $src ),
				$file . ' must not treat JAR availability as file_exists() only'
			);
		}
	}
}
