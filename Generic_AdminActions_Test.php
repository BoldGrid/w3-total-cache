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
	 * Resolves the saved cache auth value for the module under test.
	 *
	 * Masked {@see Util_Ui::secret_input()} fields submit an empty value once a
	 * value is stored, so the "Test" button would otherwise connect with an
	 * empty value and fail after every save. The settings page posts the module
	 * that owns the field (the `{module}__{engine}__password` field-name prefix)
	 * so we can read the stored value back from config here — it resolves
	 * transparently because this admin request runs after `pluggable.php`.
	 *
	 * @since 2.10.0
	 *
	 * @param string $engine Cache engine identifier: 'redis' or 'memcached'.
	 *
	 * @return string Stored value, or '' when the module is unknown / unset.
	 */
	private function stored_cache_password( $engine ) {
		$module  = Util_Request::get_string( 'module', '' );
		$allowed = array( 'pgcache', 'dbcache', 'objectcache', 'minify' );

		if ( ! in_array( $module, $allowed, true ) ) {
			return '';
		}

		return $this->_config->get_string( $module . '.' . $engine . '.password' );
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

		// A masked field submits empty once stored — fall back to the saved value.
		if ( '' === $password ) {
			$password = $this->stored_cache_password( 'memcached' );
		}

		if ( ! self::are_test_servers_allowed( $servers, $this->saved_cache_servers( 'memcached' ) ) ) {
			$this->respond_test_result( false, self::server_refused_message() );
		}

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

		// A masked field submits empty once stored — fall back to the saved value.
		if ( '' === $password ) {
			$password = $this->stored_cache_password( 'redis' );
		}

		if ( ! self::are_test_servers_allowed( $servers, $this->saved_cache_servers( 'redis' ) ) ) {
			$this->respond_test_result( false, self::server_refused_message() );
		}

		$success = $this->redis_servers_pass_test(
			$servers,
			$password,
			$dbid,
			$verify_tls_certificates,
			$this->redis_timeout_setting( 'timeout' ),
			$this->redis_timeout_setting( 'read_timeout' )
		);

		$this->respond_test_result( $success );
	}

	/**
	 * Write and read back a test value on each Redis server.
	 *
	 * Uses the same timeouts as the save probe, so Test and Save agree.
	 *
	 * @since 2.11.1
	 *
	 * @param array  $servers                 Server strings.
	 * @param string $password                Password.
	 * @param int    $dbid                    Database id.
	 * @param bool   $verify_tls_certificates Whether to verify TLS certificates.
	 * @param int    $timeout                 Connect timeout; 0 uses one second.
	 * @param int    $read_timeout            Read timeout; 0 uses one second.
	 * @return bool
	 */
	public function redis_servers_pass_test( $servers, $password, $dbid, $verify_tls_certificates, $timeout = 0, $read_timeout = 0 ) {
		if ( empty( $servers ) ) {
			$success = false;
		} else {
			$success = true;

			foreach ( $servers as $server ) {
				Cache_Redis::clear_server_bypass( $server, $dbid );

				@$cache = Cache::instance( // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
					'redis',
					array(
						'servers'                 => $server,
						'verify_tls_certificates' => $verify_tls_certificates,
						'persistent'              => false,
						'password'                => $password,
						'dbid'                    => $dbid,
						'timeout'                 => $timeout,
						'read_timeout'            => $read_timeout,
					)
				);

				if ( empty( $cache ) ) {
					$success = false;
				}

				$test_string = sprintf( 'test_' . md5( time() ) );
				$test_value  = array( 'content' => $test_string );

				$cache->set( $test_string, $test_value, 60 );

				$test_value = $cache->get( $test_string );

				Cache_Redis::clear_server_bypass( $server, $dbid );

				if ( empty( $test_value['content'] ) || $test_value['content'] !== $test_string ) {
					$success = false;
				}
			}
		}

		return $success;
	}

	/**
	 * Redis timeout posted by the settings form, else the module's saved value.
	 *
	 * @since 2.11.1
	 *
	 * @param string $name Either 'timeout' or 'read_timeout'.
	 * @return int
	 */
	private function redis_timeout_setting( $name ) {
		$posted = Util_Request::get_string( $name, '' );

		if ( '' !== $posted ) {
			return (int) $posted;
		}

		$module  = Util_Request::get_string( 'module', '' );
		$allowed = array( 'pgcache', 'dbcache', 'objectcache', 'minify' );

		if ( ! in_array( $module, $allowed, true ) ) {
			return 0;
		}

		return $this->_config->get_integer( $module . '.redis.' . $name );
	}

	/**
	 * Returns the saved memcached / redis servers across cache modules.
	 *
	 * @since 2.11.1
	 *
	 * @param string $engine Cache engine identifier: 'redis' or 'memcached'.
	 *
	 * @return string[]
	 */
	private function saved_cache_servers( $engine ) {
		$saved = array();

		foreach ( array( 'pgcache', 'dbcache', 'objectcache', 'minify' ) as $module ) {
			foreach ( (array) $this->_config->get_array( $module . '.' . $engine . '.servers' ) as $server ) {
				if ( \is_string( $server ) && '' !== \trim( $server ) ) {
					$saved[] = $server;
				}
			}
		}

		foreach ( (array) $this->_config->get_array( array( 'fragmentcache', $engine . '.servers' ) ) as $server ) {
			if ( \is_string( $server ) && '' !== \trim( $server ) ) {
				$saved[] = $server;
			}
		}

		return $saved;
	}

	/**
	 * Returns true when every requested test server is an allowed
	 * destination.
	 *
	 * A server matching a saved entry is always allowed, so saved
	 * loopback or socket servers keep working. Any other server must be
	 * a network host whose addresses all pass
	 * {@see Util_Url::host_resolves_safe_internal()}, which allows
	 * private ranges but refuses loopback, link-local (including
	 * 169.254.169.254), and reserved addresses. Unix sockets that are not
	 * saved are refused.
	 *
	 * @since 2.11.1
	 *
	 * @param array    $servers Requested servers.
	 * @param string[] $saved   Saved servers.
	 *
	 * @return bool
	 */
	public static function are_test_servers_allowed( $servers, $saved ) {
		if ( ! \is_array( $servers ) ) {
			return false;
		}

		$saved_normalized = array();
		foreach ( (array) $saved as $entry ) {
			if ( \is_string( $entry ) ) {
				$saved_normalized[] = \strtolower( \trim( $entry ) );
			}
		}

		foreach ( $servers as $server ) {
			if ( ! \is_string( $server ) ) {
				return false;
			}

			$server = \trim( $server );
			if ( '' === $server ) {
				continue;
			}

			if ( \in_array( \strtolower( $server ), $saved_normalized, true ) ) {
				continue;
			}

			$host = self::test_server_host( $server );
			if ( '' === $host || ! Util_Url::host_resolves_safe_internal( $host ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Extracts the network host from a cache server string such as
	 * `host:port`, `[v6]:port`, or `tls://host:port`.
	 *
	 * @since 2.11.1
	 *
	 * @param string $server Server string.
	 *
	 * @return string Host, or an empty string for sockets and unparseable values.
	 */
	private static function test_server_host( $server ) {
		if ( 0 === \stripos( $server, 'unix:' ) ) {
			return '';
		}

		$server = (string) \preg_replace( '#^[a-z][a-z0-9+.-]*://#i', '', $server );
		if ( '' === $server || false !== \strpos( $server, '/' ) ) {
			return '';
		}

		if ( false !== \filter_var( $server, FILTER_VALIDATE_IP ) ) {
			return $server;
		}

		if ( '[' === \substr( $server, 0, 1 ) ) {
			$end = \strpos( $server, ']' );
			return false === $end ? '' : \substr( $server, 1, $end - 1 );
		}

		list( $host ) = Util_Content::endpoint_to_host_port( $server );

		return \is_string( $host ) ? $host : '';
	}

	/**
	 * Returns the message shown when a test server is refused.
	 *
	 * @since 2.11.1
	 *
	 * @return string
	 */
	private static function server_refused_message() {
		return \__( 'Test failed. Loopback, link-local, reserved, socket, and unresolvable server addresses can be tested only after they are saved.', 'w3-total-cache' );
	}

	/**
	 * Return response results
	 *
	 * @param bool        $success Success.
	 * @param string|null $message Optional message that replaces the default.
	 *
	 * @return void
	 */
	private function respond_test_result( $success, $message = null ) {
		if ( $success ) {
			$response = array(
				'result' => true,
				'error'  => __( 'Test passed.', 'w3-total-cache' ),
			);
		} else {
			$response = array(
				'result' => false,
				'error'  => \is_string( $message ) ? \esc_html( $message ) : __( 'Test failed.', 'w3-total-cache' ),
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
		 * Validate the admin-supplied Java executable and JAR against
		 * the Util_Java allowlists before assigning them to the vendored
		 * minifier wrapper statics. The vendored code concatenates those
		 * properties into the command string passed to exec().
		 *
		 * @since 2.10.0
		 */
		$validated_java = '';
		$validated_jar  = '';
		if ( empty( $error ) && 'googleccjs' !== $w3tc_engine ) {
			$tools = Util_Java::validate_tools( $path_java, $path_jar, 'test_minifier' );
			if ( false === $tools ) {
				if ( false === Util_Java::validate( $path_java ) ) {
					$error = sprintf(
						/* translators: 1: comma-separated list of allowed directories, 2: wp-config.php constant name. */
						__( 'JAVA executable path is not allowed. The path must be an existing, executable file under one of: %1$s. Operators may extend the allowlist via the %2$s constant in wp-config.php.', 'w3-total-cache' ),
						implode( ', ', Util_Java::allowed_dirs() ),
						'W3TC_JAVA_BIN_ALLOWED_DIRS'
					);
				} else {
					$error = sprintf(
						/* translators: 1: comma-separated list of allowed directories, 2: wp-config.php constant name. */
						__( 'JAR file path is not allowed. The path must be an existing readable .jar under one of: %1$s. Operators may extend the allowlist via the %2$s constant in wp-config.php.', 'w3-total-cache' ),
						implode( ', ', Util_Java::allowed_jar_dirs() ),
						'W3TC_JAVA_JAR_ALLOWED_DIRS'
					);
				}
			} else {
				$validated_java = $tools['java'];
				$validated_jar  = $tools['jar'];
			}
		}

		if ( empty( $error ) ) {
			switch ( $w3tc_engine ) {
				case 'yuijs':
					\W3TCL\Minify\Minify_YUICompressor::$tempDir        = Util_File::create_tmp_dir();
					\W3TCL\Minify\Minify_YUICompressor::$javaExecutable = $validated_java;
					\W3TCL\Minify\Minify_YUICompressor::$jarFile        = $validated_jar;

					$w3tc_result = \W3TCL\Minify\Minify_YUICompressor::testJs( $error );
					break;

				case 'yuicss':
					\W3TCL\Minify\Minify_YUICompressor::$tempDir        = Util_File::create_tmp_dir();
					\W3TCL\Minify\Minify_YUICompressor::$javaExecutable = $validated_java;
					\W3TCL\Minify\Minify_YUICompressor::$jarFile        = $validated_jar;

					$w3tc_result = \W3TCL\Minify\Minify_YUICompressor::testCss( $error );
					break;

				case 'ccjs':
					\W3TCL\Minify\Minify_ClosureCompiler::$tempDir        = Util_File::create_tmp_dir();
					\W3TCL\Minify\Minify_ClosureCompiler::$javaExecutable = $validated_java;
					\W3TCL\Minify\Minify_ClosureCompiler::$jarFile        = $validated_jar;

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

		$response = array(
			'result' => $w3tc_result,
			'error'  => is_string( $error ) ? $error : '',
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
