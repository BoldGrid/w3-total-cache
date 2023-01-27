<?php
/**
 * File: class-w3tc-admin-util-file-test.php
 *
 * @package    W3TC
 * @subpackage W3TC/tests/admin
 * @author     BoldGrid <development@boldgrid.com>
 * @since      X.X.X
 * @link       https://www.boldgrid.com/w3-total-cache/
 */

/**
 * Class: W3tc_Admin_Util_File_Test
 *
 * @since X.X.X
 */
class W3tc_Admin_Util_File_Test extends WP_UnitTestCase {
	/**
	 * Test mkdir.
	 *
	 * @since X.X.X
	 */
	public function test_mkdir() {
		$test_dir = 'test-dir-' . gmdate( 'c' );

		$this->assertTrue( \W3TC\Util_File::mkdir( $test_dir, 0755, W3TC_DIR ) );
		$this->assertTrue( file_exists( W3TC_DIR . '/' . $test_dir ) );

		// Cleanup.
		rmdir( W3TC_DIR . '/' . $test_dir );
	}

	/**
	 * Test mkdir_from.
	 *
	 * @since X.X.X
	 */
	public function test_mkdir_from() {
		$test_dir = W3TC_DIR . '/test-dir-' . gmdate( 'c' );

		$this->assertTrue( \W3TC\Util_File::mkdir_from( $test_dir, W3TC_DIR, 0755 ) );
		$this->assertTrue( file_exists( $test_dir ) );

		// Cleanup.
		$this->assertTrue( rmdir( $test_dir ) );
	}

	/**
	 * Test mkdir_from_safe.
	 *
	 * @since X.X.X
	 */
	public function test_mkdir_from_safe() {
		$test_dir = W3TC_DIR . '/test-dir-' . gmdate( 'c' );

		$this->assertTrue( \W3TC\Util_File::mkdir_from_safe( $test_dir, W3TC_DIR, 0755 ) );
		$this->assertTrue( file_exists( $test_dir ) );

		// Cleanup.
		$this->assertTrue( rmdir( $test_dir ) );
	}

	/**
	 * Test rmdir.
	 *
	 * @since X.X.X
	 */
	public function test_rmdir() {
		$test_dir = 'test-dir-' . gmdate( 'c' );

		$this->assertTrue( \W3TC\Util_File::mkdir( $test_dir, 0755, W3TC_DIR ) );
		$this->assertTrue( file_exists( W3TC_DIR . '/' . $test_dir ) );
		$this->assertNull( \W3TC\Util_File::rmdir( $test_dir ) );
		$this->assertFalse( file_exists( W3TC_DIR . '/' . $test_dir ) );
	}

	/**
	 * Test rmdir with contents.
	 *
	 * @since X.X.X
	 */
	public function test_rmdir_with_contents() {
		$test_dir1     = 'test-dir-' . gmdate( 'c' );
		$test_dir2     = 'test-dir-' . gmdate( 'c' );
		$test_filename = 'test-file-' . gmdate( 'c' ) . '.txt';
		$test_filepath = W3TC_DIR . '/' . $test_dir1 . '/' . $test_dir2 . '/' . $test_filename;

		$this->assertTrue( \W3TC\Util_File::mkdir( $test_dir1 . '/' . $test_dir2, 0755, W3TC_DIR ) );

		$result = file_put_contents( $test_filepath, 'This is a test file.' );

		$this->assertNotFalse( $result );
		$this->assertIsInt( $result );
		$this->assertTrue( $result > 0 );
		$this->assertTrue( file_exists( $test_filepath ) );
		$this->assertNull( \W3TC\Util_File::rmdir( $test_dir1 ) );
		$this->assertFalse( file_exists( W3TC_DIR . '/' . $test_dir1 ) );
	}

	/**
	 * Test emptydir.
	 *
	 * @since X.X.X
	 */
	public function test_emptydir() {
		$test_dir1     = 'test-dir-' . gmdate( 'c' );
		$test_dir2     = 'test-dir-' . gmdate( 'c' );
		$test_filename = 'test-file-' . gmdate( 'c' ) . '.txt';
		$test_filepath = W3TC_DIR . '/' . $test_dir1 . '/' . $test_dir2 . '/' . $test_filename;

		$this->assertTrue( \W3TC\Util_File::mkdir( $test_dir1 . '/' . $test_dir2, 0755, W3TC_DIR ) );

		$result = file_put_contents( $test_filepath, 'This is a test file.' );

		$this->assertNotFalse( $result );
		$this->assertIsInt( $result );
		$this->assertTrue( $result > 0 );
		$this->assertNull( \W3TC\Util_File::emptydir( $test_dir1 ) );
		$this->assertTrue( file_exists( W3TC_DIR . '/' . $test_dir1 ) );
		$this->assertTrue( is_dir( W3TC_DIR . '/' . $test_dir1 ) );
		$this->assertNull( \W3TC\Util_File::rmdir( $test_dir1 ) );
		$this->assertFalse( file_exists( W3TC_DIR . '/' . $test_dir1 ) );
	}

	/**
	 * Test is_writable.
	 *
	 * @since X.X.X
	 */
	public function test_is_writable() {
		$test_dir1     = 'test-dir-' . gmdate( 'c' );
		$test_dir2     = 'test-dir-' . gmdate( 'c' );
		$test_filename = 'test-file-' . gmdate( 'c' ) . '.txt';
		$test_filepath = W3TC_DIR . '/' . $test_dir1 . '/' . $test_dir2 . '/' . $test_filename;

		$this->assertTrue( \W3TC\Util_File::mkdir( $test_dir1 . '/' . $test_dir2, 0755, W3TC_DIR ) );

		$result = file_put_contents( $test_filepath, 'This is a test file.' );

		$this->assertNotFalse( $result );
		$this->assertIsInt( $result );
		$this->assertTrue( $result > 0 );
		$this->assertTrue( file_exists( $test_filepath ) );
		$this->assertTrue( \W3TC\Util_File::is_writable( $test_filepath ) );
		$this->assertNull( \W3TC\Util_File::rmdir( $test_dir1 ) );
		$this->assertFalse( file_exists( W3TC_DIR . '/' . $test_dir1 ) );

		// Test non-existent file.
		$this->assertTrue( \W3TC\Util_File::is_writable( $test_filename ) );
	}

	/**
	 * Test is_writable_dir.
	 *
	 * @since X.X.X
	 */
	public function test_is_writable_dir() {
		$test_dir      = 'test-dir-' . gmdate( 'c' );

		$this->assertTrue( \W3TC\Util_File::mkdir( $test_dir, 0755, W3TC_DIR ) );

		$this->assertTrue( \W3TC\Util_File::is_writable_dir( $test_dir ) );
		$this->assertNull( \W3TC\Util_File::rmdir( $test_dir ) );
		$this->assertFalse( file_exists( W3TC_DIR . '/' . $test_dir ) );
	}

	/**
	 * Test dirname.
	 *
	 * @since X.X.X
	 */
	public function test_dirname() {
		$test_dir      = W3TC_DIR . '/test-dir-' . gmdate( 'c' );
		$test_filename = 'test-file-' . gmdate( 'c' ) . '.txt';
		$test_filepath = $test_dir . '/' . $test_filename;

		$this->assertEquals( $test_dir, \W3TC\Util_File::dirname( $test_filepath ) );

		// Test root directory.
		$this->assertEquals( '', \W3TC\Util_File::dirname( '/' ) );
	}

	/**
	 * Test make_relative_path.
	 *
	 * @since X.X.X
	 */
	public function test_make_relative_path() {
		$test_dir      = 'test-dir-' . gmdate( 'c' );
		$test_filename = 'test-file-' . gmdate( 'c' ) . '.txt';

		$this->assertEquals( '../' . $test_filename, \W3TC\Util_File::make_relative_path( $test_filename, $test_dir ) );
	}

	/**
	 * Test get_open_basedirs.
	 *
	 * @since X.X.X
	 */
	public function test_get_open_basedirs() {
		$this->assertIsArray( \W3TC\Util_File::get_open_basedirs() );
	}

	/**
	 * Test check_open_basedir.
	 *
	 * @since X.X.X
	 */
	public function test_check_open_basedir() {
		$this->assertTrue( \W3TC\Util_File::check_open_basedir( W3TC_DIR ) );
	}

	/**
	 * Test get_file_permissions.
	 *
	 * @since X.X.X
	 */
	public function test_get_file_permissions() {
		$fileperms = \W3TC\Util_File::get_file_permissions( WP_CONTENT_DIR );

		$this->assertIsInt( $fileperms );
		$this->assertEquals( 755, $fileperms );
	}

	/**
	 * Test get_file_owner.
	 *
	 * @since X.X.X
	 */
	public function test_get_file_owner() {
		$current_user  = posix_getpwuid( posix_geteuid() )['name'];
		$current_group = posix_getgrgid( posix_getpwuid( posix_getegid() )['gid'] )['name'];
		$ownership     = \W3TC\Util_File::get_file_owner( WP_CONTENT_DIR );

		$this->assertIsString( $ownership );
		$this->assertEquals( $current_user . ':' . $current_group, $ownership );
	}

	/**
	 * Test create_tmp_dir.
	 *
	 * Ensure W3TC_CACHE_TMP_DIR does not exists, create it, check return and if exists, cleanup/delete, and ensure it was deleted.
	 *
	 * @since X.X.X
	 */
	public function test_create_tmp_dir() {
		// Delete the temporary directory if needed.
		$this->assertNull( \W3TC\Util_File::rmdir( W3TC_CACHE_TMP_DIR ) );

		$this->assertDirectoryDoesNotExist( W3TC_CACHE_TMP_DIR );

		$dir = \W3TC\Util_File::create_tmp_dir();

		$this->assertEquals( W3TC_CACHE_TMP_DIR, $dir );
		$this->assertDirectoryExists( W3TC_CACHE_TMP_DIR );

		// Cleanup.
		$this->assertNull( \W3TC\Util_File::rmdir( W3TC_CACHE_TMP_DIR ) );

		$this->assertDirectoryDoesNotExist( W3TC_CACHE_TMP_DIR );
	}

	/**
	 * Test file_put_contents_atomic.
	 *
	 * Ensure W3TC_CACHE_TMP_DIR does not exists, create a file in it, check if the directory and file exists, cleanup/delete, and ensure it was deleted.
	 *
	 * @since X.X.X
	 */
	public function test_file_put_contents_atomic() {
		// Delete the temporary directory if needed.
		$this->assertNull( \W3TC\Util_File::rmdir( W3TC_CACHE_TMP_DIR ) );

		$this->assertDirectoryDoesNotExist( W3TC_CACHE_TMP_DIR );

		$this->assertNull(
			\W3TC\Util_File::file_put_contents_atomic(
				W3TC_CACHE_TMP_DIR . '/test-file_put_contents_atomic.txt',
				'This is a test for "file_put_contents_atomic".'
			)
		);

		$this->assertDirectoryExists( W3TC_CACHE_TMP_DIR );
		$this->assertFileExists( W3TC_CACHE_TMP_DIR . '/test-file_put_contents_atomic.txt' );
		$this->assertEquals(
			'This is a test for "file_put_contents_atomic".',
			file_get_contents( W3TC_CACHE_TMP_DIR . '/test-file_put_contents_atomic.txt' ) // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		);

		// Cleanup.
		$this->assertNull( \W3TC\Util_File::rmdir( W3TC_CACHE_TMP_DIR ) );

		$this->assertDirectoryDoesNotExist( W3TC_CACHE_TMP_DIR );
	}

	/**
	 * Test format_data_as_settings_file.
	 *
	 * @since X.X.X
	 */
	public function test_format_data_as_settings_file() {
		// Create a mock data array.
		$data = array(
			'bool'   => true,
			'int'    => 1,
			'null'   => null,
			'object' => new stdClass(),
			'string' => 'test',
		);

		// Convert data to a W3TC config php file.
		$config = \W3TC\Util_File::format_data_as_settings_file( $data );

		$this->assertIsString( $config );

		// Set the expected return string.
		$expected = <<<'EOT'
			<?php

			return array(
				'bool' => true,
				'int' => 1,
				'null' => null,
				'object' => array(
				),
				'string' => 'test',
			);
			EOT;

			// Convert line endings to DOS style ("\r\n").
			$expected = preg_replace( '~\R~u', "\r\n", $expected );

			$this->assertEquals( $expected, $config );
	}

	/**
	 * Test format_array_entry_as_settings_file_entry.
	 *
	 * @since X.X.X
	 */
	public function test_format_array_entry_as_settings_file_entry() {
		// One tab, string array keys.
		$tabs = 1;
		$this->assertEquals( "\t'bool' => true,\r\n", \W3TC\Util_File::format_array_entry_as_settings_file_entry( $tabs, 'bool', true ) );
		$this->assertEquals( "\t'int' => 1,\r\n", \W3TC\Util_File::format_array_entry_as_settings_file_entry( $tabs, 'int', 1 ) );
		$this->assertEquals( "\t'null' => null,\r\n", \W3TC\Util_File::format_array_entry_as_settings_file_entry( $tabs, 'null', null ) );
		$this->assertEquals( "\t'object' => array(\r\n\t),\r\n", \W3TC\Util_File::format_array_entry_as_settings_file_entry( $tabs, 'object', new stdClass() ) );
		$this->assertEquals( "\t'string' => 'test',\r\n", \W3TC\Util_File::format_array_entry_as_settings_file_entry( $tabs, 'string', 'test' ) );

		// Two tabs, string array keys.
		$tabs = 2;
		$this->assertEquals( "\t\t'bool' => true,\r\n", \W3TC\Util_File::format_array_entry_as_settings_file_entry( $tabs, 'bool', true ) );
		$this->assertEquals( "\t\t'int' => 1,\r\n", \W3TC\Util_File::format_array_entry_as_settings_file_entry( $tabs, 'int', 1 ) );
		$this->assertEquals( "\t\t'null' => null,\r\n", \W3TC\Util_File::format_array_entry_as_settings_file_entry( $tabs, 'null', null ) );
		$this->assertEquals( "\t\t'object' => array(\r\n\t\t),\r\n", \W3TC\Util_File::format_array_entry_as_settings_file_entry( $tabs, 'object', new stdClass() ) );
		$this->assertEquals( "\t\t'string' => 'test',\r\n", \W3TC\Util_File::format_array_entry_as_settings_file_entry( $tabs, 'string', 'test' ) );

		// One tab, numeric array keys.
		$tabs = 1;
		$this->assertEquals( "\t1 => true,\r\n", \W3TC\Util_File::format_array_entry_as_settings_file_entry( $tabs, 1, true ) );
		$this->assertEquals( "\t2 => 1,\r\n", \W3TC\Util_File::format_array_entry_as_settings_file_entry( $tabs, 2, 1 ) );
		$this->assertEquals( "\t3 => null,\r\n", \W3TC\Util_File::format_array_entry_as_settings_file_entry( $tabs, 3, null ) );
		$this->assertEquals( "\t4 => array(\r\n\t),\r\n", \W3TC\Util_File::format_array_entry_as_settings_file_entry( $tabs, 4, new stdClass() ) );
		$this->assertEquals( "\t5 => 'test',\r\n", \W3TC\Util_File::format_array_entry_as_settings_file_entry( $tabs, 5, 'test' ) );
	}
}
