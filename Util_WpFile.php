<?php
/**
 * File: Util_WpFile.php
 *
 * @package W3TC
 */

namespace W3TC;

/**
 * Class: Util_WpFile
 *
 * phpcs:disable WordPress.PHP.NoSilencedErrors.Discouraged
 * phpcs:disable WordPress.WP.AlternativeFunctions
 * phpcs:disable Generic.CodeAnalysis.UnusedFunctionParameter
 */
class Util_WpFile {
	/**
	 * Checks the credentials for accessing the WordPress filesystem via AJAX.
	 *
	 * This method verifies if the system can connect to the WordPress filesystem using the available access methods
	 * (e.g., direct, SSH2, FTP). If the credentials are invalid or the filesystem cannot be accessed, it sends an error
	 * response with details about the failure.
	 *
	 * @param string|null $extra Optional additional message to include in the error response. This can provide more context
	 *                           about the error.
	 *
	 * @return void Sends a JSON error response with filesystem access failure details if credentials are invalid or filesystem
	 *              connection fails.
	 *
	 * @uses WP_Filesystem()
	 * @uses request_filesystem_credentials()
	 * @uses wp_send_json_error()
	 * @uses get_filesystem_method()
	 * @uses ABSPATH
	 */
	public static function ajax_check_credentials( $extra = null ) {

		if ( ! function_exists( 'get_filesystem_method' ) ) {
			require_once ABSPATH . '/wp-admin/includes/file.php';
		}

		$access_type = get_filesystem_method();
		ob_start();
		$credentials = request_filesystem_credentials(
			site_url() . '/wp-admin/',
			$access_type
		);
		ob_end_clean();

		if ( false === $credentials || ! WP_Filesystem( $credentials ) ) {
			global $wp_filesystem;

			$status['error'] = sprintf(
				// translators: 1: Filesystem access method: "direct", "ssh2", "ftpext" or "ftpsockets".
				__(
					'Unable to connect to the filesystem (using %1$s). Please confirm your credentials.  %2$s',
					'w3-total-cache'
				),
				$access_type,
				$extra
			);

			// Pass through the error from WP_Filesystem if one was raised.
			if ( $wp_filesystem instanceof WP_Filesystem_Base && is_wp_error( $wp_filesystem->errors ) &&
				$wp_filesystem->errors->has_errors() ) {
					$status['error'] = esc_html( $wp_filesystem->errors->get_error_message() );
			}

			wp_send_json_error( $status );
		}
	}

	/**
	 * Writes content to a specified file and handles filesystem permissions.
	 *
	 * This method attempts to write content to a file using the `file_put_contents` function and applies the correct file permissions.
	 * If this fails, it triggers a request for filesystem credentials and retries the operation using the WordPress filesystem API.
	 * If the write operation still fails, an exception is thrown.
	 *
	 * @param string $filename The path to the file to write the content to.
	 * @param string $content  The content to write to the file.
	 *
	 * @throws Util_WpFile_FilesystemWriteException If the write operation fails and filesystem permissions cannot be resolved.
	 *
	 * @return void
	 *
	 * @uses request_filesystem_credentials()
	 * @uses WP_Filesystem()
	 * @uses file_put_contents()
	 * @uses chmod()
	 *
	 * phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped
	 */
	public static function write_to_file( $filename, $content ) {
		$chmod = 0644;

		if ( defined( 'FS_CHMOD_FILE' ) ) {
			$chmod = FS_CHMOD_FILE;
		}

		if ( @file_put_contents( $filename, $content ) ) {
			@chmod( $filename, $chmod );
			return;
		}

		try {
			self::request_filesystem_credentials();
		} catch ( Util_WpFile_FilesystemOperationException $ex ) {
			throw new Util_WpFile_FilesystemWriteException(
				$ex->getMessage(),
				$ex->credentials_form(),
				$filename,
				$content
			);
		}

		global $wp_filesystem;
		if ( ! $wp_filesystem->put_contents( $filename, $content, $chmod ) ) {
			throw new Util_WpFile_FilesystemWriteException(
				'FTP credentials don\'t allow to write to file <strong>' . $filename . '</strong>',
				self::get_filesystem_credentials_form(),
				$filename,
				$content
			);
		}
	}

	/**
	 * Copies a file from a source location to a destination location.
	 *
	 * This method attempts to copy a file by reading the content from the source file and writing it to the destination file.
	 * If the copy fails, it requests filesystem credentials and retries the operation using the WordPress filesystem API. If
	 * the copy operation still fails, an exception is thrown.
	 *
	 * @param string $source_filename      The path to the source file to copy.
	 * @param string $destination_filename The path to the destination file.
	 *
	 * @throws Util_WpFile_FilesystemCopyException If the copy operation fails and filesystem credentials cannot be resolved.
	 *
	 * @return void
	 *
	 * @uses request_filesystem_credentials()
	 * @uses WP_Filesystem()
	 * @uses file_get_contents()
	 * @uses file_put_contents()
	 * @uses file_exists()
	 */
	public static function copy_file( $source_filename, $destination_filename ) {
		$contents = @file_get_contents( $source_filename );
		if ( $contents ) {
			@file_put_contents( $destination_filename, $contents );
		}

		if ( @file_exists( $destination_filename ) ) {
			if ( @file_get_contents( $destination_filename ) === $contents ) {
				return;
			}
		}

		try {
			self::request_filesystem_credentials();
		} catch ( Util_WpFile_FilesystemOperationException $ex ) {
			throw new Util_WpFile_FilesystemCopyException(
				$ex->getMessage(),
				$ex->credentials_form(),
				$source_filename,
				$destination_filename
			);
		}

		global $wp_filesystem;
		if ( ! $wp_filesystem->put_contents( $destination_filename, $contents, FS_CHMOD_FILE ) ) {
			throw new Util_WpFile_FilesystemCopyException(
				'FTP credentials don\'t allow to copy to file <strong>' . $destination_filename . '</strong>',
				self::get_filesystem_credentials_form(),
				$source_filename,
				$destination_filename
			);
		}
	}

	/**
	 * Creates a folder at the specified location.
	 *
	 * This method checks if the specified folder already exists. If not, it attempts to create the folder using a safe method
	 * first, then falls back to using the WordPress filesystem API if necessary. If the operation fails, it requests filesystem
	 * credentials and retries the creation process. If that also fails, an exception is thrown.
	 *
	 * @param string $folder       The path to the folder to be created.
	 * @param string $from_folder  The path from which the folder creation is initiated (used for safe creation).
	 *
	 * @throws Util_WpFile_FilesystemMkdirException If the folder creation fails and filesystem credentials cannot be resolved.
	 *
	 * @return void
	 *
	 * @uses Util_File::mkdir_from_safe()
	 * @uses request_filesystem_credentials()
	 * @uses WP_Filesystem()
	 * @uses is_dir()
	 */
	private static function create_folder( $folder, $from_folder ) {
		if ( @is_dir( $folder ) ) {
			return;
		}

		if ( Util_File::mkdir_from_safe( $folder, $from_folder ) ) {
			return;
		}

		try {
			self::request_filesystem_credentials();
		} catch ( Util_WpFile_FilesystemOperationException $ex ) {
			throw new Util_WpFile_FilesystemMkdirException(
				$ex->getMessage(),
				$ex->credentials_form(),
				$folder
			);
		}

		global $wp_filesystem;
		if ( ! $wp_filesystem->mkdir( $folder, FS_CHMOD_DIR ) ) {
			throw new Util_WpFile_FilesystemMkdirException(
				'FTP credentials don\'t allow to create folder <strong>' . $folder . '</strong>',
				self::get_filesystem_credentials_form(),
				$folder
			);
		}
	}

	/**
	 * Creates a folder and ensures it is writable by applying a set of permissions.
	 *
	 * This method first calls `create_folder()` to create the specified folder. After the folder is created, it attempts to
	 * set the folder's permissions using an array of potential permission levels (0755, 0775, 0777). It will apply each
	 * permission level in sequence until the folder is writable, or until all permission levels have been tried.
	 *
	 * @param string $folder       The path to the folder to be created and made writable.
	 * @param string $from_folder  The path from which the folder creation is initiated (used for safe creation).
	 *
	 * @throws Util_WpFile_FilesystemMkdirException If the folder creation fails and filesystem credentials cannot be resolved.
	 * @throws Util_WpFile_FilesystemChmodException If the folder's permissions cannot be modified.
	 *
	 * @return void
	 *
	 * @uses self::create_folder()
	 * @uses self::chmod()
	 * @uses is_writable()
	 */
	public static function create_writeable_folder( $folder, $from_folder ) {
		self::create_folder( $folder, $from_folder );

		$permissions = array( 0755, 0775, 0777 );

		$count = count( $permissions );
		for ( $set_index = 0; $set_index < $count; $set_index++ ) {
			if ( is_writable( $folder ) ) {
				break;
			}

			self::chmod( $folder, $permissions[ $set_index ] );
		}
	}

	/**
	 * Deletes a specified folder, ensuring it is removed from the filesystem.
	 *
	 * This method first checks if the folder exists. If it does, it attempts to delete the folder using the `rmdir`
	 * method. If the deletion fails, it requests filesystem credentials and tries again using the WordPress filesystem
	 * API (`WP_Filesystem`). If the credentials are insufficient to delete the folder, an exception is thrown.
	 *
	 * @param string $folder The path to the folder to be deleted.
	 *
	 * @throws Util_WpFile_FilesystemRmdirException If the folder cannot be deleted due to insufficient permissions.
	 *
	 * @return void
	 *
	 * @uses self::request_filesystem_credentials()
	 * @uses Util_File::rmdir()
	 * @uses $wp_filesystem->rmdir()
	 */
	public static function delete_folder( $folder ) {
		if ( ! @is_dir( $folder ) ) {
			return;
		}

		Util_File::rmdir( $folder );
		if ( ! @is_dir( $folder ) ) {
			return;
		}

		try {
			self::request_filesystem_credentials();
		} catch ( Util_WpFile_FilesystemOperationException $ex ) {
			throw new Util_WpFile_FilesystemRmdirException(
				$ex->getMessage(),
				$ex->credentials_form(),
				$folder
			);
		}

		global $wp_filesystem;
		if ( ! $wp_filesystem->rmdir( $folder ) ) {
			throw new Util_WpFile_FilesystemRmdirException(
				__( 'FTP credentials don\'t allow to delete folder ', 'w3-total-cache' ) . '<strong>' . $folder . '</strong>',
				self::get_filesystem_credentials_form(),
				$folder
			);
		}
	}

	/**
	 * Changes the permissions of a file or directory.
	 *
	 * Attempts to set the specified permissions on a file or directory. If the operation fails due to insufficient permissions
	 * or the inability to access the filesystem directly, it requests filesystem credentials and retries using the WordPress
	 * filesystem API (`WP_Filesystem`). If the permissions cannot be changed, an exception is thrown.
	 *
	 * @param string $filename   The path to the file or directory.
	 * @param int    $permission The desired file permissions (e.g., 0755).
	 *
	 * @throws Util_WpFile_FilesystemChmodException If the file permissions cannot be changed due to insufficient permissions.
	 *
	 * @return bool True on success.
	 *
	 * @uses self::request_filesystem_credentials()
	 * @uses $wp_filesystem->chmod()
	 */
	private static function chmod( $filename, $permission ) {
		if ( @chmod( $filename, $permission ) ) {
			return;
		}

		try {
			self::request_filesystem_credentials();
		} catch ( Util_WpFile_FilesystemOperationException $ex ) {
			throw new Util_WpFile_FilesystemChmodException(
				$ex->getMessage(),
				$ex->credentials_form(),
				$filename,
				$permission
			);
		}

		global $wp_filesystem;
		if ( ! $wp_filesystem->chmod( $filename, $permission, true ) ) {
			throw new Util_WpFile_FilesystemChmodException(
				__( 'FTP credentials don\'t allow to chmod ', 'w3-total-cache' ) . '<strong>' . $filename . '</strong>',
				self::get_filesystem_credentials_form(),
				$filename,
				$permission
			);
		}

		return true;
	}

	/**
	 * Deletes a file from the filesystem.
	 *
	 * Attempts to delete the specified file using direct filesystem access. If this fails, it requests filesystem
	 * credentials and retries using the WordPress filesystem API (`WP_Filesystem`). If the file cannot be deleted,
	 * an exception is thrown.
	 *
	 * @param string $filename The path to the file to be deleted.
	 *
	 * @throws Util_WpFile_FilesystemRmException If the file cannot be deleted due to insufficient permissions.
	 *
	 * @uses self::request_filesystem_credentials()
	 * @uses $wp_filesystem->delete()
	 */
	public static function delete_file( $filename ) {
		if ( ! @file_exists( $filename ) ) {
			return;
		}

		if ( @unlink( $filename ) ) {
			return;
		}

		try {
			self::request_filesystem_credentials();
		} catch ( Util_WpFile_FilesystemOperationException $ex ) {
			throw new Util_WpFile_FilesystemRmException(
				$ex->getMessage(),
				$ex->credentials_form(),
				$filename
			);
		}

		global $wp_filesystem;
		if ( ! $wp_filesystem->delete( $filename ) ) {
			throw new Util_WpFile_FilesystemRmException(
				__( 'FTP credentials don\'t allow to delete ', 'w3-total-cache' ) . '<strong>' . $filename . '</strong>',
				self::get_filesystem_credentials_form(),
				$filename
			);
		}
	}

	/**
	 * Requests filesystem credentials and initializes the WordPress filesystem API.
	 *
	 * This method attempts to obtain filesystem credentials for performing file operations, ensuring compatibility with various
	 * access methods (e.g., FTP, SSH). If credentials are invalid or unavailable, an exception is thrown with the appropriate
	 * error message and form for user input.
	 *
	 * @param string $method  Optional. Filesystem access method (e.g., 'ftp', 'ssh2'). Default is an empty string.
	 * @param string $url     Optional. The URL to redirect to after credentials are entered. Defaults to the current request URI.
	 * @param string $context Optional. The directory for which credentials are required. Default is false.
	 *
	 * @throws Util_WpFile_FilesystemOperationException If credentials cannot be retrieved or validated.
	 *
	 * @uses request_filesystem_credentials()
	 * @uses WP_Filesystem()
	 */
	private static function request_filesystem_credentials( $method = '', $url = '', $context = false ) {
		if ( strlen( $url ) <= 0 ) {
			$url = isset( $_SERVER['REQUEST_URI'] ) ? esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
		}

		$url = preg_replace( '/&w3tc_note=([^&]+)/', '', $url );

		// Ensure request_filesystem_credentials() is available.
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/template.php';

		$success = true;
		ob_start();
		$creds = request_filesystem_credentials( $url, $method, false, $context, array() );
		if ( false === $creds ) {
			$success = false;
		}
		$form = ob_get_contents();
		ob_end_clean();

		ob_start();
		// If first check failed try again and show error message.
		if ( ! WP_Filesystem( $creds ) && $success ) {
			request_filesystem_credentials( $url, $method, true, false, array() );
			$success = false;
			$form    = ob_get_contents();
		}
		ob_end_clean();

		$error = '';
		if ( preg_match( '/<div([^c]+)class="error">(.+)<\/div>/', $form, $matches ) ) {
			$error = $matches[2];
			$form  = str_replace( $matches[0], '', $form );
		}

		if ( ! $success ) {
			throw new Util_WpFile_FilesystemOperationException( $error, $form );
		}
	}

	/**
	 * Retrieves the filesystem credentials form for user input.
	 *
	 * This method generates the HTML form required for users to provide filesystem credentials (e.g., FTP or SSH) when automatic
	 * access is unavailable. It ensures the necessary WordPress files are loaded, processes any existing error messages, and
	 * customizes the form for compatibility with W3 Total Cache.
	 *
	 * @param string $method  Optional. Filesystem access method (e.g., 'ftp', 'ssh2'). Default is an empty string.
	 * @param string $url     Optional. The URL to redirect to after credentials are entered. Defaults to the current request URI.
	 * @param string $context Optional. The directory for which credentials are required. Default is false.
	 *
	 * @return string The generated HTML form for filesystem credentials input.
	 *
	 * @uses request_filesystem_credentials()
	 */
	private static function get_filesystem_credentials_form( $method = '', $url = '', $context = false ) {
		// Ensure request_filesystem_credentials() is available.
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/template.php';

		ob_start();
		// If first check failed try again and show error message.
		request_filesystem_credentials( $url, $method, true, false, array() );
		$success = false;
		$form    = ob_get_contents();

		ob_end_clean();

		$error = '';
		if ( preg_match( '/<div([^c]+)class="error">(.+)<\/div>/', $form, $matches ) ) {
			$form = str_replace( $matches[0], '', $form );
		}

		$form = str_replace( '<input ', '<input class="w3tc-ignore-change" ', $form );

		return $form;
	}
}
