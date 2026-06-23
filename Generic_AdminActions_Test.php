<?php
/**
 * File: Generic_AdminActions_Test.php
 *
 * @package W3TC
 */

namespace W3TC;

/**
 * Class Generic_AdminActions_Test
 *
 * phpcs:disable PSR2.Classes.PropertyDeclaration.Underscore
 * phpcs:disable PSR2.Methods.MethodDeclaration.Underscore
 * phpcs:disable WordPress.PHP.NoSilencedErrors.Discouraged
 * phpcs:disable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
 */
class Generic_AdminActions_Test {
	/**
	 * Config
	 *
	 * @var Config
	 */
	private $_config = null;

	/**
	 * Current page
	 *
	 * @var null|string
	 */
	private $_page = null;

	/**
	 * Constructor
	 *
	 * @return void
	 */
	public function __construct() {
		$this->_config = Dispatcher::config();
		$this->_page   = Util_Admin::get_current_page();
	}

	/**
	 * Rejects the current handler invocation if it was not delivered as a
	 * POST request. Used by handlers that consume a cleartext secret
	 * (memcached / redis passwords) which would otherwise leak into the
	 * webserver access log when supplied via $_GET. Util_Request::get_string()
	 * reads $_REQUEST (= $_GET + $_POST), so a misconfigured client — or a
	 * CSRF-style image-tag — could otherwise put the password on the URL.
	 *
	 * @return void
	 */
	private function require_post_request() {
		$method = isset( $_SERVER['REQUEST_METHOD'] ) ? strtoupper( (string) wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Compared to a literal HTTP method only.
		if ( 'POST' !== $method ) {
			\status_header( 405 );
			\header( 'Allow: POST' );
			$this->respond_test_result( false );
			// respond_test_result() exits.
		}
	}

	/**
	 * Test memcached
	 *
	 * @return void
	 */
	public function w3tc_test_memcached() {
		// Reject GET so the cleartext password cannot land in the access log.
		$this->require_post_request();

		$servers         = Util_Request::get_array( 'servers' );
		$binary_protocol = Util_Request::get_boolean( 'binary_protocol', true );
		$username        = Util_Request::get_string( 'username', '' );

		/**
		 * Read directly from $_POST (bypassing Util_Request, which merges $_GET)
		 * so this stays correct even if the POST-only gate is ever relaxed.
		 */
		$password = isset( $_POST['password'] ) // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified upstream by the admin-action dispatcher.
			? (string) wp_unslash( $_POST['password'] ) // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- password is opaque secret used only as the auth credential to memcached; nonce verified upstream by the admin-action dispatcher.
			: '';

		$this->respond_test_result( $this->is_memcache_available( $servers, $binary_protocol, $username, $password ) );
	}

	/**
	 * Test redis
	 *
	 * @return void
	 */
	public function w3tc_test_redis() {
		// Reject GET so the cleartext password cannot land in the access log.
		$this->require_post_request();

		$servers                 = Util_Request::get_array( 'servers' );
		$verify_tls_certificates = Util_Request::get_boolean( 'verify_tls_certificates', true );
		$dbid                    = Util_Request::get_integer( 'dbid', 0 );

		// Read password directly from $_POST (see w3tc_test_memcached for rationale).
		$password = isset( $_POST['password'] ) // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified upstream by the admin-action dispatcher.
			? (string) wp_unslash( $_POST['password'] ) // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- password is opaque secret used only as the auth credential to redis; nonce verified upstream by the admin-action dispatcher.
			: '';

		if ( empty( $servers ) ) {
			$success = false;
		} else {
			$success = true;

			foreach ( $servers as $server ) {
				@$cache = Cache::instance( // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
					'redis',
					array(
						'servers'                 => $server,
						'verify_tls_certificates' => $verify_tls_certificates,
						'persistent'              => false,
						'password'                => $password,
						'dbid'                    => $dbid,
					)
				);

				if ( empty( $cache ) ) {
					$success = false;
				}

				$test_string = sprintf( 'test_' . md5( time() ) );
				$test_value  = array( 'content' => $test_string );

				$cache->set( $test_string, $test_value, 60 );

				$test_value = $cache->get( $test_string );

				if ( empty( $test_value['content'] ) || $test_value['content'] !== $test_string ) {
					$success = false;
				}
			}
		}

		$this->respond_test_result( $success );
	}

	/**
	 * Return response results
	 *
	 * @param bool $success Success.
	 *
	 * @return void
	 */
	private function respond_test_result( $success ) {
		if ( $success ) {
			$response = array(
				'result' => true,
				'error'  => __( 'Test passed.', 'w3-total-cache' ),
			);
		} else {
			$response = array(
				'result' => false,
				'error'  => __( 'Test failed.', 'w3-total-cache' ),
			);
		}

		echo wp_json_encode( $response );
		exit();
	}

	/**
	 * Test minifier action
	 *
	 * @return void
	 */
	public function w3tc_test_minifier() {
		$w3tc_engine = Util_Request::get_string( 'engine' );
		$path_java   = Util_Request::get_string( 'path_java' );
		$path_jar    = Util_Request::get_string( 'path_jar' );

		$w3tc_result = false;
		$error       = '';

		if ( 'googleccjs' !== $w3tc_engine ) {
			if ( ! $path_java ) {
				$error = __( 'Empty JAVA executable path.', 'w3-total-cache' );
			} elseif ( ! $path_jar ) {
				$error = __( 'Empty JAR file path.', 'w3-total-cache' );
			}
		}

		/*
		 * Validate the admin-supplied `path_java` against the
		 * Util_Java allowlist before assigning it to the vendored
		 * minifier wrapper's static $javaExecutable. The vendored
		 * code concatenates that property into the command string
		 * passed to exec(), so without this validator the value
		 * would not be escaped at the boundary.
		 *
		 * @since 2.10.0
		 */
		$validated_java = '';
		if ( empty( $error ) && 'googleccjs' !== $w3tc_engine ) {
			$validated_java = Util_Java::validate_with_log( $path_java, 'test_minifier' );
			if ( false === $validated_java ) {
				$error = sprintf(
					/* translators: 1: comma-separated list of allowed directories, 2: wp-config.php constant name. */
					__( 'JAVA executable path is not allowed. The path must be an existing, executable file under one of: %1$s. Operators may extend the allowlist via the %2$s constant in wp-config.php.', 'w3-total-cache' ),
					implode( ', ', Util_Java::allowed_dirs() ),
					'W3TC_JAVA_BIN_ALLOWED_DIRS'
				);
			}
		}

		if ( empty( $error ) ) {
			switch ( $w3tc_engine ) {
				case 'yuijs':
					\W3TCL\Minify\Minify_YUICompressor::$tempDir        = Util_File::create_tmp_dir();
					\W3TCL\Minify\Minify_YUICompressor::$javaExecutable = $validated_java;
					\W3TCL\Minify\Minify_YUICompressor::$jarFile        = $path_jar;

					$w3tc_result = \W3TCL\Minify\Minify_YUICompressor::testJs( $error );
					break;

				case 'yuicss':
					\W3TCL\Minify\Minify_YUICompressor::$tempDir        = Util_File::create_tmp_dir();
					\W3TCL\Minify\Minify_YUICompressor::$javaExecutable = $validated_java;
					\W3TCL\Minify\Minify_YUICompressor::$jarFile        = $path_jar;

					$w3tc_result = \W3TCL\Minify\Minify_YUICompressor::testCss( $error );
					break;

				case 'ccjs':
					\W3TCL\Minify\Minify_ClosureCompiler::$tempDir        = Util_File::create_tmp_dir();
					\W3TCL\Minify\Minify_ClosureCompiler::$javaExecutable = $validated_java;
					\W3TCL\Minify\Minify_ClosureCompiler::$jarFile        = $path_jar;

					$w3tc_result = \W3TCL\Minify\Minify_ClosureCompiler::test( $error );
					break;

				case 'googleccjs':
					$w3tc_result = \W3TCL\Minify\Minify_JS_ClosureCompiler::test( $error );
					break;

				default:
					$error = __( 'Invalid engine.', 'w3-total-cache' );
					break;
			}
		}

		/*
		 * The vendored exception strings interpolate the configured
		 * path verbatim into a message the dashboard renders client-
		 * side. Strip HTML at the response boundary so the rendered
		 * text is plain text. The full fix on the dashboard renderer
		 * is tracked in the user-experience group's own change.
		 *
		 * @since 2.10.0
		 */
		$response = array(
			'result' => $w3tc_result,
			'error'  => is_string( $error ) ? \esc_html( $error ) : '',
		);

		echo wp_json_encode( $response );
	}

	/**
	 * Check if memcache is available
	 *
	 * @param array  $servers         Servers.
	 * @param string $binary_protocol Binary Protocol.
	 * @param string $username        Username.
	 * @param string $password        Password.
	 *
	 * @return boolean
	 */
	private function is_memcache_available( $servers, $binary_protocol, $username, $password ) {
		if ( count( $servers ) <= 0 ) {
			return false;
		}

		foreach ( $servers as $server ) {
			@$memcached = Cache::instance(
				'memcached',
				array(
					'servers'         => $server,
					'persistent'      => false,
					'binary_protocol' => $binary_protocol,
					'username'        => $username,
					'password'        => $password,
				)
			);

			if ( is_null( $memcached ) ) {
				return false;
			}

			$test_string = sprintf( 'test_' . md5( time() ) );
			$test_value  = array( 'content' => $test_string );
			$memcached->set( $test_string, $test_value, 60 );
			$test_value = $memcached->get( $test_string );

			if ( empty( $test_value['content'] ) || $test_value['content'] !== $test_string ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Self test action
	 *
	 * @return void
	 */
	public function w3tc_test_self() {
		include W3TC_INC_LIGHTBOX_DIR . '/self_test.php';
	}

	/**
	 * Minify recommendations action
	 *
	 * @return void
	 */
	public function w3tc_test_minify_recommendations() {
		$options_minify = new Minify_Page();
		$options_minify->recommendations();
	}
}
