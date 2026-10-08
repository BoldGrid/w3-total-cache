<?php
/**
 * File: Extension_MaxCache_Configd.php
 *
 * @package W3TC
 */

namespace W3TC;

defined( 'W3TC' ) || die();

/**
 * Tells the NGINX configuration daemon that the rules file changed.
 *
 * Its own class: nothing is notified where the NGINX build is not installed, and the socket is the
 * only place in this integration that talks to anything outside the filesystem.
 *
 * @since X.X.X
 */
class Extension_MaxCache_Configd {
	/**
	 * Socket the configuration daemon listens on.
	 *
	 * @since X.X.X
	 *
	 * @var string
	 */
	const SOCKET = '/opt/cloudlinux/maxcache/notify.sock';

	/**
	 * Seconds to wait on the daemon.
	 *
	 * @since X.X.X
	 *
	 * @var float
	 */
	const TIMEOUT = 2.0;

	/**
	 * Signature of a rules file that is not there.
	 *
	 * @since X.X.X
	 *
	 * @var string
	 */
	const SIGNATURE_ABSENT = 'absent';

	/**
	 * Returns the request that asks the configuration daemon to re-read this site, or an empty string when there
	 * is nothing to ask.
	 *
	 * @since X.X.X
	 *
	 * @return string JSON request, or an empty string where the NGINX build is not installed.
	 */
	private static function reload_request() {
		// Keyed on whether the NGINX build is installed, not on mode - see Extension_MaxCache_Environment::add().
		if ( ! Extension_MaxCache_Core::nginx_module_installed() ) {
			return '';
		}

		$path = (string) Util_Rule::get_apache_rules_path();

		if ( '' === $path ) {
			return '';
		}

		$directory = \rtrim( \dirname( $path ), '/' );

		if ( '' === $directory ) {
			return '';
		}

		return (string) \wp_json_encode(
			array(
				'action' => 'reload',
				'path'   => $directory,
			)
		);
	}

	/**
	 * Returns a value that changes when the rules file does.
	 *
	 * @since X.X.X
	 *
	 * @param string $path Rules file path.
	 *
	 * @return string
	 */
	public static function rules_file_signature( $path ) {
		$path = (string) $path;

		if ( '' === $path ) {
			return '';
		}

		// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		if ( ! @\file_exists( $path ) ) {
			return self::SIGNATURE_ABSENT;
		}

		// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		if ( ! @\is_readable( $path ) ) {
			return '';
		}

		// md5_file, not read+md5: runs up to four times per rules pass on NGINX, over the whole .htaccess.
		// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		$hash = @\md5_file( $path );

		return false === $hash ? '' : $hash;
	}

	/**
	 * Whether a rules pass has anything to announce.
	 *
	 * @since X.X.X
	 *
	 * @param string $path             Rules file path.
	 * @param string $signature_before Signature taken before the write.
	 *
	 * @return bool
	 */
	private static function should_notify( $path, $signature_before ) {
		$after = self::rules_file_signature( $path );

		// Unreadable after the write leaves the question open - ask the daemon rather than assume nothing happened.
		return '' === $after || $after !== (string) $signature_before;
	}

	/**
	 * Announces a rules file that changed, and stays silent about one that did not.
	 *
	 * @since X.X.X
	 *
	 * @param string $path             Rules file path.
	 * @param string $signature_before Signature taken before the write, from self::rules_file_signature().
	 *
	 * @return bool Whether the daemon was reached.
	 */
	public static function notify_if_changed( $path, $signature_before ) {
		// True when there was nothing to tell; false means "the daemon had to be told and could not be reached".
		if ( ! self::should_notify( $path, $signature_before ) ) {
			return true;
		}

		return self::notify();
	}

	/**
	 * Tells the configuration daemon that this site's `.htaccess` changed.
	 *
	 * @since X.X.X
	 *
	 * @return bool Whether the daemon was reached.
	 */
	private static function notify() {
		$request = self::reload_request();

		if ( '' === $request ) {
			return false;
		}

		$errno = 0;

		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_set_error_handler
		\set_error_handler(
			static function () {
				return true;
			}
		);

		try {
			// A unix socket is not subject to open_basedir, which closes this directory to PHP.
			// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			$socket = @\stream_socket_client( 'unix://' . self::SOCKET, $errno, $errstr, self::TIMEOUT );
		} finally {
			\restore_error_handler();
		}

		if ( ! \is_resource( $socket ) ) {
			return false;
		}

		// Before the write, not after: stream_socket_client()'s timeout bounds the connect alone.
		\stream_set_timeout( $socket, self::TIMEOUT );

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite, WordPress.PHP.NoSilencedErrors.Discouraged
		$written = @\fwrite( $socket, $request );

		// A short write leaves the daemon mid-request; close here rather than wait on a request it never fully received.
		if ( false === $written || $written < \strlen( $request ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
			\fclose( $socket );

			return false;
		}

		// The daemon applies the change while connected; returning early would report success before it lands.
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fread, WordPress.PHP.NoSilencedErrors.Discouraged
		$answer = (string) @\fgets( $socket, 4096 );

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		\fclose( $socket );

		return self::answer_is_ok( $answer );
	}

	/**
	 * Whether the daemon's reply says the reload was taken up.
	 *
	 * The daemon's own protocol: {"status":"ok",...} or {"status":"error","message":...}, never a
	 * bare "ok" key.
	 *
	 * @since X.X.X
	 *
	 * @param string $answer Raw reply read from the socket.
	 *
	 * @return bool
	 */
	private static function answer_is_ok( $answer ) {
		$decoded = \json_decode( (string) $answer, true );

		return \is_array( $decoded ) && 'ok' === ( $decoded['status'] ?? null );
	}
}
