<?php
/**
 * File: Minify_MinifiedFileRequestHandler.php
 *
 * @package W3TC
 */

namespace W3TC;

defined( 'ABSPATH' ) || exit;
// Define repeated regex to simplify changes.
define( 'W3TC_MINIFY_AUTO_FILENAME_REGEX', '([a-zA-Z0-9-_]+)\\.(css|js)([?].*)?' );
define( 'W3TC_MINIFY_MANUAL_FILENAME_REGEX', '([a-f0-9]+)\\.(.+)\\.(include(\\-(footer|body))?)\\.[a-f0-9]+\\.(css|js)' );

/**
 * Class Minify_MinifiedFileRequestHandler
 *
 * phpcs:disable PSR2.Classes.PropertyDeclaration.Underscore
 * phpcs:disable PSR2.Methods.MethodDeclaration.Underscore
 * phpcs:disable WordPress.PHP.NoSilencedErrors.Discouraged
 * phpcs:disable WordPress.PHP.DiscouragedPHPFunctions
 * phpcs:disable WordPress.WP.AlternativeFunctions
 * phpcs:disable WordPress.DB.DirectDatabaseQuery
 * phpcs:disable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
 */
class Minify_MinifiedFileRequestHandler {
	/**
	 * Config
	 *
	 * @var Config
	 */
	private $_config = null;

	/**
	 * Tracks if an error has occurred.
	 *
	 * @var bool
	 */
	private $_error_occurred = false;

	/**
	 * Per-token site-transient key prefix used to gate the unauthenticated
	 * `rewrite_test.css` and `XXX.css` probes.
	 *
	 * Each issued probe token becomes its own transient (`<prefix><token>`)
	 * so concurrent rewrite tests — multiple admins, or one admin running
	 * back-to-back tests — cannot clobber each other's tokens. Only
	 * requests presenting a matching token via `X-W3TC-Minify-Probe` may
	 * trigger the probe responses.
	 *
	 * @since 2.10.0
	 *
	 * @var string
	 */
	const PROBE_TOKEN_PREFIX = 'w3tc_minify_probe_';

	/**
	 * Lifetime (seconds) of an issued probe token. Probes are server-to-self
	 * within a single admin request, so a short window suffices.
	 *
	 * @since 2.10.0
	 *
	 * @var int
	 */
	const PROBE_TOKEN_TTL = 60;

	/**
	 * HTTP header carrying the probe token from the issuing admin request to
	 * the (unauthenticated) `init`-hook minify handler.
	 *
	 * @since 2.10.0
	 *
	 * @var string
	 */
	const PROBE_TOKEN_HEADER = 'X-W3TC-Minify-Probe';

	/**
	 * Constructor for the Minify_MinifiedFileRequestHandler class.
	 *
	 * Initializes the configuration object.
	 *
	 * @return void
	 */
	public function __construct() {
		$this->_config = Dispatcher::config();
	}

	/**
	 * Issues a short-lived single-use token authorising the legitimate
	 * minify rewrite/cache-length probes.
	 *
	 * The token is stored as a site transient with a small TTL and is read
	 * back (and consumed) in {@see consume_probe_token()} when the probe
	 * request lands. This closes that path: anonymous callers can no longer
	 * trigger the `rewrite_test.css` / `XXX.css` side channels, while the
	 * plugin's own admin-side rewrite verification continues to work.
	 *
	 * @since 2.10.0
	 *
	 * @return string A 32-character hex token.
	 */
	public static function issue_probe_token() {
		// 16 random bytes -> 32 hex chars; cryptographically strong.
		try {
			$token = bin2hex( random_bytes( 16 ) );
		} catch ( \Exception $e ) {
			/**
			 * `random_bytes` only throws if the OS RNG is unavailable. Fall
			 * back to `wp_generate_password` — but normalise the output so
			 * it still matches the strict `/^[a-f0-9]{32}$/` consume regex.
			 * Without `bin2hex`-shaped output the consume side would
			 * silently reject every probe on hosts without OS RNG.
			 */
			$raw   = \wp_generate_password( 16, false, false );
			$token = strtolower( bin2hex( substr( $raw, 0, 16 ) ) );
		}

		/**
		 * Key the transient by token value so each issued token gets its own
		 * storage slot. Concurrent probes therefore do not overwrite each
		 * other; each token can be independently consumed.
		 */
		\set_site_transient( self::PROBE_TOKEN_PREFIX . $token, '1', self::PROBE_TOKEN_TTL );

		return $token;
	}

	/**
	 * Validates the inbound probe token and consumes it on success so it
	 * cannot be replayed.
	 *
	 * @since 2.10.0
	 *
	 * @return bool True if the request presents a matching probe token.
	 */
	private function consume_probe_token() {
		$header_key = 'HTTP_' . strtoupper( str_replace( '-', '_', self::PROBE_TOKEN_HEADER ) );
		if ( empty( $_SERVER[ $header_key ] ) ) {
			return false;
		}

		$presented = trim( (string) \wp_unslash( $_SERVER[ $header_key ] ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- value normalized by strict /^[a-f0-9]{32}$/ regex on the next non-comment line.
		// Defensive: strict format match for a 32-char hex token.
		if ( ! preg_match( '/^[a-f0-9]{32}$/', $presented ) ) {
			return false;
		}

		/**
		 * Token IS the transient key. Lookup is the validation: a missing
		 * transient (false) means the token is invalid, expired, or already
		 * consumed. The 32-char hex format check above bounds the lookup
		 * keyspace; the unguessable random token bounds the probability that
		 * a guess collides with a live issued token.
		 */
		$w3tc_key = self::PROBE_TOKEN_PREFIX . $presented;
		$valid    = \get_site_transient( $w3tc_key );
		if ( false === $valid ) {
			return false;
		}

		// One-shot: clear so this token cannot be replayed.
		\delete_site_transient( $w3tc_key );

		return true;
	}

	/**
	 * Sends a 404-style response and exits. Used to suppress the
	 * unauthenticated probe side-channels.
	 *
	 * @since 2.10.0
	 *
	 * @return void
	 */
	private function reject_probe() {
		if ( ! headers_sent() ) {
			\status_header( 404 );
			\nocache_headers();
		}
		exit();
	}

	/**
	 * Processes the given file request and serves minified content.
	 *
	 * @param string|null $w3tc_file  The requested file for minification. Defaults to null.
	 * @param bool        $quiet Whether to suppress errors and output debugging information.
	 *
	 * @return array|void An array of minification results, or void in certain cases.
	 *
	 * @throws \Exception If a recoverable error occurs during the minification process.
	 */
	public function process( $w3tc_file = null, $quiet = false ) {
		// Check for rewrite test request.
		$rewrite_marker = 'rewrite_test.css';
		if ( substr( $w3tc_file, strlen( $w3tc_file ) - strlen( $rewrite_marker ) ) === $rewrite_marker ) {
			/**
			 * Gate the probe behind a single-use token so anonymous
			 * callers cannot use it to fingerprint the handler.
			 */
			if ( ! $this->consume_probe_token() ) {
				$this->reject_probe();
			}
			echo 'Minify OK';
			exit();
		}

		$filelength_test_marker = 'XXX.css';
		if ( substr( $w3tc_file, strlen( $w3tc_file ) - strlen( $filelength_test_marker ) ) === $filelength_test_marker ) {
			/**
			 * This probe writes request-supplied content to the
			 * minify cache directory. Require an issued probe token so only
			 * the plugin's own admin-side environment check can drive it.
			 */
			if ( ! $this->consume_probe_token() ) {
				$this->reject_probe();
			}
			$cache = $this->_get_cache();
			header( 'Content-type: text/css' );

			if ( ! $cache->store( basename( $w3tc_file ), array( 'content' => 'content ok' ) ) ) {
				echo 'error storing';
			} else {
				if (
					function_exists( 'brotli_compress' ) &&
					$this->_config->get_boolean( 'browsercache.enabled' ) &&
					$this->_config->get_boolean( 'browsercache.cssjs.brotli' )
				) {
					if ( ! $cache->store( basename( $w3tc_file ) . '_br', array( 'content' => brotli_compress( 'content ok' ) ) ) ) {
						echo 'error storing';
						exit();
					}
				}

				if (
					function_exists( 'gzencode' ) &&
					$this->_config->get_boolean( 'browsercache.enabled' ) &&
					$this->_config->get_boolean( 'browsercache.cssjs.compression' )
				) {
					if ( ! $cache->store( basename( $w3tc_file ) . '_gzip', array( 'content' => gzencode( 'content ok' ) ) ) ) {
						echo 'error storing';
						exit();
					}
				}

				$v = $cache->fetch( basename( $w3tc_file ) );
				if ( 'content ok' === $v['content'] ) {
					echo 'content ok';
				} else {
					echo 'error storing';
				}
			}

			exit();
		}

		// remove querystring.
		if ( preg_match( '~(.+)(\?x[0-9]{5})$~', $w3tc_file, $m ) ) {
			$w3tc_file = $m[1];
		}

		// remove blog_id.
		$levels = '';
		if ( defined( 'W3TC_BLOG_LEVELS' ) ) {
			for ( $n = 0; $n < W3TC_BLOG_LEVELS; $n++ ) {
				$levels .= '[0-9]+\/';
			}
		}

		if ( preg_match( '~^(' . $levels . '[0-9]+)\/(.+)$~', $w3tc_file, $matches ) ) {
			$w3tc_file = $matches[2];
		}

		// normalize according to browsercache.
		$w3tc_file = Dispatcher::requested_minify_filename( $this->_config, $w3tc_file );

		// parse file.
		$hash     = '';
		$matches  = null;
		$location = '';
		$type     = '';

		if ( preg_match( '~^' . W3TC_MINIFY_AUTO_FILENAME_REGEX . '$~', $w3tc_file, $matches ) ) {
			list( , $hash, $type ) = $matches;
		} elseif ( preg_match( '~^' . W3TC_MINIFY_MANUAL_FILENAME_REGEX . '$~', $w3tc_file, $matches ) ) {
			list( , $theme, $template, $location, , , $type ) = $matches;
		} else {
			return $this->finish_with_error( sprintf( 'Bad file param format: "%s"', $w3tc_file ), $quiet, false );
		}

		// Set cache engine.
		$cache = $this->_get_cache();
		\W3TCL\Minify\Minify::setCache( $cache );

		// Set cache ID.
		$cache_id = $this->get_cache_id( $w3tc_file );
		\W3TCL\Minify\Minify::setCacheId( $w3tc_file );

		// Set logger.
		\W3TCL\Minify\Minify_Logger::setLogger(
			array(
				$this,
				'debug_error',
			)
		);

		// Set options.
		$browsercache = $this->_config->get_boolean( 'browsercache.enabled' );

		$serve_options = array_merge(
			$this->_config->get_array( 'minify.options' ),
			array(
				'debug'             => $this->_config->get_boolean( 'minify.debug' ),
				'maxAge'            => $this->_config->get_integer( 'browsercache.cssjs.lifetime' ),
				'encodeOutput'      => (
					$browsercache &&
					! defined( 'W3TC_PAGECACHE_OUTPUT_COMPRESSION_OFF' ) &&
					! $quiet &&
					(
						$this->_config->get_boolean( 'browsercache.cssjs.compression' ) ||
						$this->_config->get_boolean( 'browsercache.cssjs.brotli' )
					)
				),
				'bubbleCssImports'  => ( $this->_config->get_string( 'minify.css.imports' ) === 'bubble' ),
				'processCssImports' => ( $this->_config->get_string( 'minify.css.imports' ) === 'process' ),
				'cacheHeaders'      => array(
					'use_etag'             => ( $browsercache && $this->_config->get_boolean( 'browsercache.cssjs.etag' ) ),
					'expires_enabled'      => ( $browsercache && $this->_config->get_boolean( 'browsercache.cssjs.expires' ) ),
					'cacheheaders_enabled' => ( $browsercache && $this->_config->get_boolean( 'browsercache.cssjs.cache.control' ) ),
					'cacheheaders'         => $this->_config->get_string( 'browsercache.cssjs.cache.policy' ),
				),
				'disable_304'       => $quiet,   // when requested for service needs - need content instead of 304.
				'quiet'             => $quiet,
			)
		);

		// Set sources.
		if ( $hash ) {
			$_GET['f_array'] = $this->minify_filename_to_filenames_for_minification( $hash, $type );
			$_GET['ext']     = $type;
		} else {
			/**
			 * Manual mode (empty hash): the file list is derived server-side
			 * from the configured minify groups and served through the `g`
			 * group path. `f_array` is an auto-mode-only transport that is
			 * populated from the hash lookup above; a manual request must never
			 * carry one. With a manual-format filename the hash is empty, so
			 * without this an attacker-supplied `f_array[]=wp-config.php` would
			 * survive untouched into MinApp::setupSources() and be served as an
			 * arbitrary docroot file read (CVE-2026-9282). Drop any caller-
			 * supplied `f_array` and force groups-only resolution so the file
			 * read loop is never reachable in manual mode.
			 */
			unset( $_GET['f_array'] );
			$_GET['g']                             = $location;
			$serve_options['minApp']['groups']     = $this->get_groups( $theme, $template, $type );
			$serve_options['minApp']['groupsOnly'] = true;
		}

		// Set minifier.
		$w3_minifier = Dispatcher::component( 'Minify_ContentMinifier' );

		if ( 'js' === $type ) {
			$minifier_type = 'application/x-javascript';

			switch ( true ) {
				case ( 'combine' === $hash && $this->_config->get_string( 'minify.js.method' ) ):
				case ( 'include' === $location && $this->_config->get_boolean( 'minify.js.combine.header' ) ):
				case ( 'include-body' === $location && $this->_config->get_boolean( 'minify.js.combine.body' ) ):
				case ( 'include-footer' === $location && $this->_config->get_boolean( 'minify.js.combine.footer' ) ):
					$w3tc_engine = 'combinejs';
					break;

				default:
					$w3tc_engine = $this->_config->get_string( 'minify.js.engine' );
					if ( ! $w3_minifier->exists( $w3tc_engine ) || ! $w3_minifier->available( $w3tc_engine ) ) {
						$w3tc_engine = 'js';
					}

					break;
			}
		} elseif ( 'css' === $type ) {
			$minifier_type = 'text/css';

			if ( ( $hash || 'include' === $location ) && 'combine' === $this->_config->get_string( 'minify.css.method' ) ) {
				$w3tc_engine = 'combinecss';
			} else {
				$w3tc_engine = $this->_config->get_string( 'minify.css.engine' );
				if ( ! $w3_minifier->exists( $w3tc_engine ) || ! $w3_minifier->available( $w3tc_engine ) ) {
					$w3tc_engine = 'css';
				}
			}
		}

		// Initialize minifier.
		$w3_minifier->init( $w3tc_engine );

		$serve_options['minifiers'][ $minifier_type ]       = $w3_minifier->get_minifier( $w3tc_engine );
		$serve_options['minifierOptions'][ $minifier_type ] = $w3_minifier->get_options( $w3tc_engine );

		// Send X-Powered-By header.
		if ( ! $quiet && $browsercache && $this->_config->get_boolean( 'browsercache.cssjs.w3tc' ) ) {
			@header( 'X-Powered-By: ' . Util_Environment::w3tc_header() );
		}

		if ( empty( Util_Request::get( 'f_array' ) ) && empty( Util_Request::get_string( 'g' ) ) ) {
			return $this->finish_with_error( 'Nothing to minify', $quiet, false );
		}

		// Minify.
		$serve_options = apply_filters( 'w3tc_minify_file_handler_minify_options', $serve_options );

		$return = array();
		try {
			$return = \W3TCL\Minify\Minify::serve( 'MinApp', $serve_options );
		} catch ( \Exception $exception ) {
			return $this->finish_with_error( $exception->getMessage(), $quiet );
		}

		if ( ! is_null( \W3TCL\Minify\Minify::$recoverableError ) ) {
			$this->_handle_error( \W3TCL\Minify\Minify::$recoverableError );
		}

		$state = Dispatcher::config_state_master();
		if ( ! $this->_error_occurred && $state->get_boolean( 'minify.show_note_minify_error' ) ) {
			$error_file = $state->get_string( 'minify.error.file' );
			if ( $error_file === $w3tc_file ) {
				$state->set( 'minify.show_note_minify_error', false );
				$state->save();
			}
		}

		return $return;
	}

	/**
	 * Records usage statistics for the current minify request.
	 *
	 * @param object $storage The storage object for recording statistics.
	 *
	 * @return void
	 */
	public function w3tc_usage_statistics_of_request( $storage ) {
		$stats = \W3TCL\Minify\Minify::getUsageStatistics();
		if ( count( $stats ) > 0 ) {
			$storage->counter_add( 'minify_requests_total', 1 );
			if ( 'text/css' === $stats['content_type'] ) {
				$storage->counter_add( 'minify_original_length_css', (int) ( $stats['content_original_length'] / 102.4 ) );
				$storage->counter_add( 'minify_output_length_css', (int) ( $stats['content_output_length'] / 102.4 ) );
			} else {
				$storage->counter_add( 'minify_original_length_js', (int) ( $stats['content_original_length'] / 102.4 ) );
				$storage->counter_add( 'minify_output_length_js', (int) ( $stats['content_output_length'] / 102.4 ) );
			}
		}
	}

	/**
	 * Retrieves the size statistics of the cache.
	 *
	 * @param int $timeout_time The timeout duration for the statistics retrieval operation.
	 *
	 * @return array An array containing the size statistics of the cache.
	 */
	public function get_stats_size( $timeout_time ) {
		$cache = $this->_get_cache();
		if ( method_exists( $cache, 'get_stats_size' ) ) {
			return $cache->get_stats_size( $timeout_time );
		}

		return array();
	}

	/**
	 * Flushes the cache and optionally handles UI-specific actions.
	 *
	 * phpcs:disable WordPress.PHP.DevelopmentFunctions.error_log_debug_backtrace
	 *
	 * @param array $extras {
	 *     Optional. Associative array of extra parameters for the flush operation.
	 *
	 *     @type string $ui_action If set to 'flush_button', performs additional UI-specific actions like clearing options.
	 * }
	 *
	 * @return bool True if the cache flush was successful, false otherwise.
	 */
	public function flush( $extras = array() ) {
		$cache = $this->_get_cache();
		// Used to debug - which plugin calls flush all the time and breaks performance.
		if ( $this->_config->get_boolean( 'minify.debug' ) ) {
			Minify_Core::log( 'Minify flush called from' );
			Minify_Core::log( wp_json_encode( debug_backtrace() ) );
		}

		/*
		 * Cleanup of map too often is risky since breaks all old minify urls.
		 * Particularly minified urls in browsercached/cdn cached html becomes invalid.
		 */
		if ( isset( $extras['ui_action'] ) && 'flush_button' === $extras['ui_action'] ) {
			global $wpdb;
			$wpdb->query( "DELETE FROM $wpdb->options WHERE `option_name` = 'w3tc_minify' OR `option_name` LIKE 'w3tc_minify_%'" );
		}

		return $cache->flush();
	}

	/**
	 * Retrieves custom data associated with a specific URL.
	 *
	 * @param string $w3tc_url The URL for which custom data is being retrieved.
	 *
	 * @return mixed|null Custom data associated with the URL, or null if none exists.
	 */
	public function get_url_custom_data( $w3tc_url ) {
		if ( preg_match( '~/' . W3TC_MINIFY_AUTO_FILENAME_REGEX . '$~', $w3tc_url, $matches ) ) {
			list( , $hash, $type ) = $matches;

			$w3tc_key = $this->get_custom_data_key( $hash, $type );
			return $this->_cache_get( $w3tc_key );
		}

		return null;
	}

	/**
	 * Associates custom data with a specified file.
	 *
	 * @param string $w3tc_file The file to associate the custom data with.
	 * @param mixed  $w3tc_data The custom data to store.
	 *
	 * @return void
	 */
	public function set_file_custom_data( $w3tc_file, $w3tc_data ) {
		if ( preg_match( '~' . W3TC_MINIFY_AUTO_FILENAME_REGEX . '$~', $w3tc_file, $matches ) ) {
			list( , $hash, $type ) = $matches;

			$w3tc_key = $this->get_custom_data_key( $hash, $type );
			$this->_cache_set( $w3tc_key, $w3tc_data );
		}
	}

	/**
	 * Retrieves the groups of minification sources for a given theme, template, and type.
	 *
	 * @param string $theme    The theme for which groups are retrieved.
	 * @param string $template The template for which groups are retrieved.
	 * @param string $type     The type of content (e.g., 'css', 'js').
	 *
	 * @return array An array of groups for the specified parameters.
	 */
	public function get_groups( $theme, $template, $type ) {
		$w3tc_result = array();

		switch ( $type ) {
			case 'css':
				$groups = $this->_config->get_array( 'minify.css.groups' );
				break;

			case 'js':
				$groups = $this->_config->get_array( 'minify.js.groups' );
				break;

			default:
				return $w3tc_result;
		}

		if ( isset( $groups[ $theme ]['default'] ) ) {
			$locations = (array) $groups[ $theme ]['default'];
		} else {
			$locations = array();
		}

		if ( 'default' !== $template && isset( $groups[ $theme ][ $template ] ) ) {
			$locations = array_merge_recursive( $locations, (array) $groups[ $theme ][ $template ] );
		}

		foreach ( $locations as $location => $w3tc_config ) {
			if ( ! empty( $w3tc_config['files'] ) ) {
				foreach ( (array) $w3tc_config['files'] as $w3tc_url ) {
					if ( Util_Environment::is_url( $w3tc_url ) ) {
						$w3tc_file = Util_Environment::url_to_docroot_filename( $w3tc_url );
					} else {
						/**
						 * A non-URL group entry is already a document-root-relative
						 * local path stored in the config, so resolve it directly.
						 *
						 * Round-tripping it through home_domain_root_url() and
						 * url_to_docroot_filename() breaks on subdirectory-multisite
						 * subsites: get_home_url() carries the "/<blog>" path, so the
						 * shared "/wp-content/..." URL fails the home-URL prefix check
						 * and the local file is misclassified as external. It then gets
						 * routed to _precache_file(), which Util_Http::download() now
						 * refuses for non-public hosts (internal/staging/private-IP
						 * deployments), leaving the group with no resolvable source.
						 */
						$w3tc_file = ltrim( $w3tc_url, '/' );
					}

					if ( is_null( $w3tc_file ) ) {
						// it's external url.
						$precached_file = $this->_precache_file( $w3tc_url, $type );

						if ( $precached_file ) {
							$w3tc_result[ $location ][ $w3tc_url ] = $precached_file;
						} else {
							Minify_Core::debug_error( sprintf( 'Unable to cache remote url: "%s"', $w3tc_url ) );
						}
					} else {
						$path = Util_Environment::document_root() . '/' . $w3tc_file;

						if ( file_exists( $path ) ) {
							$w3tc_result[ $location ][ $w3tc_file ] = '//' . $w3tc_file;
						} else {
							Minify_Core::debug_error( sprintf( 'File "%s" doesn\'t exist', $path ) );
						}
					}
				}
			}
		}

		return $w3tc_result;
	}

	/**
	 * Retrieves the cache ID for the specified file.
	 *
	 * @param string $w3tc_file The file for which the cache ID is retrieved.
	 *
	 * @return string The cache ID for the file.
	 */
	public function get_cache_id( $w3tc_file ) {
		return $w3tc_file;
	}

	/**
	 * Retrieves sources for a specific group within a theme, template, and location.
	 *
	 * @param string $theme    The theme name.
	 * @param string $template The template name.
	 * @param string $location The location name within the group.
	 * @param string $type     The content type (e.g., 'css', 'js').
	 *
	 * @return array An array of source file paths.
	 */
	public function get_sources_group( $theme, $template, $location, $type ) {
		$sources = array();
		$groups  = $this->get_groups( $theme, $template, $type );

		if ( isset( $groups[ $location ] ) ) {
			$files = (array) $groups[ $location ];

			$document_root = Util_Environment::document_root();

			foreach ( $files as $w3tc_file ) {
				if ( is_a( $w3tc_file, '\W3TCL\Minify\Minify_Source' ) ) {
					$path = $w3tc_file->filepath;
				} else {
					$path = rtrim( $document_root, '/' ) . '/' . ltrim( $w3tc_file, '/' );
				}

				$sources[] = $path;
			}
		}

		return $sources;
	}

	/**
	 * Retrieves the ID key for a specific group.
	 *
	 * @param string $theme    The theme name.
	 * @param string $template The template name.
	 * @param string $location The location name within the group.
	 * @param string $type     The content type (e.g., 'css', 'js').
	 *
	 * @return string The ID key for the specified group.
	 */
	public function get_id_key_group( $theme, $template, $location, $type ) {
		return sprintf( '%s/%s.%s.%s.id', $theme, $template, $location, $type );
	}

	/**
	 * Retrieves the group ID for a given theme, template, location, and type.
	 *
	 * @param string $theme    The theme identifier.
	 * @param string $template The template identifier.
	 * @param string $location The location identifier.
	 * @param string $type     The type identifier.
	 *
	 * @return string|null The group ID or null if not found.
	 */
	public function get_id_group( $theme, $template, $location, $type ) {
		$w3tc_key = $this->get_id_key_group( $theme, $template, $location, $type );
		$id       = $this->_cache_get( $w3tc_key );

		if ( false === $id ) {
			$sources = $this->get_sources_group( $theme, $template, $location, $type );

			if ( count( $sources ) ) {
				$id = $this->_generate_id( $sources, $type );

				if ( $id ) {
					$this->_cache_set( $w3tc_key, $id );
				}
			}
		}

		return $id;
	}

	/**
	 * Generates a custom data key based on a hash and type.
	 *
	 * @param string $hash The hash value.
	 * @param string $type The type of data.
	 *
	 * @return string The custom data key.
	 */
	public function get_custom_data_key( $hash, $type ) {
		return sprintf( '%s.%s.customdata', $hash, $type );
	}

	/**
	 * Converts a minified filename to an array of filenames for minification.
	 *
	 * @param string $hash The hash representing the filename.
	 * @param string $type The type of the minified content (e.g., CSS or JS).
	 *
	 * @return array An array of filenames for minification.
	 *
	 * @throws \Exception If the conversion process encounters an error.
	 */
	public function minify_filename_to_filenames_for_minification( $hash, $type ) {
		// if bad data passed as get parameter - it shouldn't fire internal errors.
		try {
			$files = Minify_Core::minify_filename_to_urls_for_minification( $hash, $type );
		} catch ( \Exception $e ) {
			$files = array();
		}

		$w3tc_result = array();
		if ( is_array( $files ) && count( $files ) > 0 ) {
			foreach ( $files as $w3tc_file ) {
				$docroot_filename = Util_Environment::url_to_docroot_filename( $w3tc_file );

				if ( Util_Environment::is_url( $w3tc_file ) && is_null( $docroot_filename ) ) {
					// it's external url.
					$precached_file = $this->_precache_file( $w3tc_file, $type );

					if ( $precached_file ) {
						$w3tc_result[] = $precached_file;
					} else {
						Minify_Core::debug_error( sprintf( 'Unable to cache remote file: "%s"', $w3tc_file ) );
					}
				} else {
					$path = Util_Environment::docroot_to_full_filename( $docroot_filename );

					if ( @file_exists( $path ) ) {
						$w3tc_result[] = $w3tc_file;
					} else {
						Minify_Core::debug_error( sprintf( 'File "%s" doesn\'t exist', $w3tc_file ) );
					}
				}
			}
		} else {
			Minify_Core::debug_error( sprintf( 'Unable to fetch custom files list: "%s.%s"', $hash, $type ), false, 404 );
		}

		if ( $this->_config->get_boolean( 'minify.debug' ) ) {
			Minify_Core::log( implode( "\n", $files ) );
		}

		return $w3tc_result;
	}

	/**
	 * Handles errors and prepares an error response.
	 *
	 * @param string $error               The error message.
	 * @param bool   $quiet               Whether to suppress output.
	 * @param bool   $report_about_error  Whether to report the error.
	 *
	 * @return array|void
	 */
	public function finish_with_error( $error, $quiet = false, $report_about_error = true ) {
		$this->_error_occurred = true;

		Minify_Core::debug_error( $error );

		if ( $report_about_error ) {
			$this->_handle_error( $error );
		}

		$w3tc_message = '<h1>W3TC Minify Error</h1>';

		if ( $this->_config->get_boolean( 'minify.debug' ) ) {
			$w3tc_message .= sprintf( '<p>%s.</p>', $error );
		} else {
			$w3tc_message .= '<p>Enable debug mode to see error message.</p>';
		}

		if ( $quiet ) {
			return array(
				'content' => $w3tc_message,
			);
		}

		if ( defined( 'W3TC_IN_MINIFY' ) ) {
			status_header( 400 );
			echo esc_html( $w3tc_message );
			die();
		}
	}

	/**
	 * Logs a debug error message.
	 *
	 * @param string $error The error message to log.
	 *
	 * @return void
	 */
	public function debug_error( $error ) {
		Minify_Core::debug_error( $error );
	}

	/**
	 * Pre-caches a file from a URL for minification.
	 *
	 * @param string $w3tc_url  The URL of the file.
	 * @param string $type The type of the file (e.g., CSS or JS).
	 *
	 * @return mixed The minified source or false if caching fails.
	 */
	public function _precache_file( $w3tc_url, $type ) {
		$lifetime   = $this->_config->get_integer( 'minify.lifetime' );
		$cache_path = sprintf( '%s/minify_%s.%s', Util_Environment::cache_blog_dir( 'minify' ), md5( $w3tc_url ), $type );

		if ( ! file_exists( $cache_path ) || @filemtime( $cache_path ) < ( time() - $lifetime ) ) {
			if ( ! @is_dir( dirname( $cache_path ) ) ) {
				Util_File::mkdir_from_safe( dirname( $cache_path ), W3TC_CACHE_DIR );
			}

			// google-fonts (most used for external inclusion) doesnt return full content (unicode-range) for simple useragents.
			Util_Http::download(
				$w3tc_url,
				$cache_path,
				array(
					'user-agent' => 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/69.0.3497.92',
				)
			);
		}

		return file_exists( $cache_path ) ? $this->_get_minify_source( $cache_path, $w3tc_url, $type ) : false;
	}

	/**
	 * Retrieves a minify source from a file path and URL.
	 *
	 * @param string $file_path The file path to the cached file.
	 * @param string $w3tc_url       The original URL of the file.
	 * @param string $type      Resource type (css/js).
	 *
	 * @return \W3TCL\Minify\Minify_Source The minify source object.
	 */
	public function _get_minify_source( $file_path, $w3tc_url, $type ) {
		$spec = array(
			'filepath'      => $file_path,
			'minifyOptions' => array(
				'prependRelativePath' => $w3tc_url,
			),
		);

		if ( $this->is_already_minified_resource( $w3tc_url, $type ) ) {
			// Remote vendor files already minified should only be combined, never re-minified, to avoid syntax issues.
			if ( 'js' === $type ) {
				$spec['minifier'] = '';
			} elseif ( 'css' === $type ) {
				$spec['minifyOptions']['compress'] = false;
			}
		}

		return new \W3TCL\Minify\Minify_Source( $spec );
	}

	/**
	 * Determines whether the resource already appears minified.
	 *
	 * @param string $target URL or path.
	 * @param string $type   Resource type.
	 *
	 * @return bool
	 */
	private function is_already_minified_resource( $target, $type ) {
		if ( empty( $target ) || empty( $type ) ) {
			return false;
		}

		$normalized = strtolower( Util_Environment::remove_query_all( $target ) );
		$filename   = basename( $normalized );

		switch ( $type ) {
			case 'js':
				return (bool) preg_match( '/\.min\.js$/', $filename );

			case 'css':
				return (bool) preg_match( '/\.min\.css$/', $filename );
		}

		return false;
	}

	/**
	 * Retrieves the caching mechanism used by the minification system.
	 *
	 * @return mixed The cache object.
	 */
	public function _get_cache() {
		static $cache = null;

		if ( is_null( $cache ) ) {
			$inner_cache = null;

			switch ( $this->_config->get_string( 'minify.engine' ) ) {
				case 'memcached':
					$w3tc_config = array(
						'blog_id'           => Util_Environment::blog_id(),
						'instance_id'       => Util_Environment::instance_id(),
						'host'              => Util_Environment::host(),
						'module'            => 'minify',
						'servers'           => $this->_config->get_array( 'minify.memcached.servers' ),
						'persistent'        => $this->_config->get_boolean( 'minify.memcached.persistent' ),
						'aws_autodiscovery' => $this->_config->get_boolean( 'minify.memcached.aws_autodiscovery' ),
						'username'          => $this->_config->get_string( 'minify.memcached.username' ),
						'password'          => $this->_config->get_string( 'minify.memcached.password' ),
						'binary_protocol'   => $this->_config->get_boolean( 'minify.memcached.binary_protocol' ),
					);

					if ( class_exists( 'Memcached' ) ) {
						$inner_cache = new Cache_Memcached( $w3tc_config );
					} elseif ( class_exists( 'Memcache' ) ) {
						$inner_cache = new Cache_Memcache( $w3tc_config );
					}

					break;

				case 'redis':
					$w3tc_config = array(
						'blog_id'                 => Util_Environment::blog_id(),
						'instance_id'             => Util_Environment::instance_id(),
						'host'                    => Util_Environment::host(),
						'module'                  => 'minify',
						'servers'                 => $this->_config->get_array( 'minify.redis.servers' ),
						'verify_tls_certificates' => $this->_config->get_boolean( 'minify.redis.verify_tls_certificates' ),
						'persistent'              => $this->_config->get_boolean( 'minify.redis.persistent' ),
						'timeout'                 => $this->_config->get_integer( 'minify.redis.timeout' ),
						'retry_interval'          => $this->_config->get_integer( 'minify.redis.retry_interval' ),
						'read_timeout'            => $this->_config->get_integer( 'minify.redis.read_timeout' ),
						'dbid'                    => $this->_config->get_integer( 'minify.redis.dbid' ),
						'password'                => $this->_config->get_string( 'minify.redis.password' ),
					);

					$inner_cache = new Cache_Redis( $w3tc_config );

					break;

				case 'apc':
					$w3tc_config = array(
						'blog_id'     => Util_Environment::blog_id(),
						'instance_id' => Util_Environment::instance_id(),
						'host'        => Util_Environment::host(),
						'module'      => 'minify',
					);

					if ( function_exists( 'apcu_store' ) ) {
						$inner_cache = new Cache_Apcu( $w3tc_config );
					} elseif ( function_exists( 'apc_store' ) ) {
						$inner_cache = new Cache_Apc( $w3tc_config );
					}

					break;

				case 'eaccelerator':
					$w3tc_config = array(
						'blog_id'     => Util_Environment::blog_id(),
						'instance_id' => Util_Environment::instance_id(),
						'host'        => Util_Environment::host(),
						'module'      => 'minify',
					);

					$inner_cache = new Cache_Eaccelerator( $w3tc_config );

					break;

				case 'xcache':
					$w3tc_config = array(
						'blog_id'     => Util_Environment::blog_id(),
						'instance_id' => Util_Environment::instance_id(),
						'host'        => Util_Environment::host(),
						'module'      => 'minify',
					);

					$inner_cache = new Cache_Xcache( $w3tc_config );

					break;

				case 'wincache':
					$w3tc_config = array(
						'blog_id'     => Util_Environment::blog_id(),
						'instance_id' => Util_Environment::instance_id(),
						'host'        => Util_Environment::host(),
						'module'      => 'minify',
					);

					$inner_cache = new Cache_Wincache( $w3tc_config );

					break;
			}

			if ( ! is_null( $inner_cache ) ) {
				$cache = new \W3TCL\Minify\Minify_Cache_W3TCDerived( $inner_cache );
			} else {
				// case 'file' or fallback.
				$cache = new \W3TCL\Minify\Minify_Cache_File(
					Util_Environment::cache_blog_minify_dir(),
					array(
						'.htaccess',
						'index.html',
						'*_old',
					),
					$this->_config->get_boolean( 'minify.file.locking' ),
					$this->_config->get_integer( 'timelimit.cache_flush' ),
					( Util_Environment::blog_id() === 0 ? W3TC_CACHE_MINIFY_DIR : null )
				);
			}
		}

		return $cache;
	}

	/**
	 * Handles an error notification process.
	 *
	 * @param string $error The error message to handle.
	 *
	 * @return void
	 */
	public function _handle_error( $error ) {
		$notification = $this->_config->get_string( 'minify.error.notification' );

		if ( $notification ) {
			$w3tc_file = Util_Request::get_string( 'file' );
			$state     = Dispatcher::config_state_master();

			if ( $w3tc_file ) {
				$state->set( 'minify.error.file', $w3tc_file );
			}

			if ( stristr( $notification, 'admin' ) !== false ) {
				$state->set( 'minify.error.last', $error );
				$state->set( 'minify.show_note_minify_error', true );
			}

			if ( stristr( $notification, 'email' ) !== false ) {
				$last = $state->get_integer( 'minify.error.notification.last' );

				// Prevent email flood: send email every 5 min.
				if ( ( time() - $last ) > 300 ) {
					$state->set( 'minify.error.notification.last', time() );
					$this->_send_notification();
				}
			}

			$state->save();
		}
	}

	/**
	 * Sends an error notification email.
	 *
	 * @return bool True if the email was successfully sent, false otherwise.
	 */
	public function _send_notification() {
		$from_email = 'wordpress@' . Util_Environment::host();
		$from_name  = wp_specialchars_decode( get_option( 'blogname' ), ENT_QUOTES );
		$to_name    = get_option( 'admin_email' );
		$to_email   = $to_name;
		$body       = @file_get_contents( W3TC_INC_DIR . '/email/minify_error_notification.html' );

		$headers = array(
			sprintf( 'From: "%s" <%s>', addslashes( $from_name ), $from_email ),
			sprintf( 'Reply-To: "%s" <%s>', addslashes( $to_name ), $to_email ),
			'Content-Type: text/html; charset=utf-8',
		);

		@set_time_limit( $this->_config->get_integer( 'timelimit.email_send' ) ); // phpcs:ignore Squiz.PHP.DiscouragedFunctions.Discouraged

		$w3tc_result = @wp_mail( $to_email, 'W3 Total Cache Error Notification', $body, implode( "\n", $headers ) );

		return $w3tc_result;
	}

	/**
	 * Generates an ID based on the given sources and type.
	 *
	 * This method takes an array of sources and a type (CSS or JS) and generates a unique ID by
	 * hashing the contents of the sources and additional configuration options based on the type.
	 *
	 * @param array  $sources {
	 *     The sources to generate the ID from.
	 *
	 *     @type string|object $source A source can either be a string representing a file path or an object
	 *                                 containing a `filepath` property.
	 * }
	 * @param string $type    The type of the sources (e.g., 'css' or 'js').
	 *
	 * @return string|false The generated ID or false if generation fails.
	 */
	public function _generate_id( $sources, $type ) {
		$values = array();
		foreach ( $sources as $source ) {
			if ( is_string( $source ) ) {
				$values[] = $source;
			} else {
				$values[] = $source->filepath;
			}
		}

		foreach ( $sources as $source ) {
			if ( is_string( $source ) && file_exists( $source ) ) {
				$w3tc_data = @file_get_contents( $source );

				if ( false !== $w3tc_data ) {
					$values[] = md5( $w3tc_data );
				} else {
					return false;
				}
			} else {
				$headers = @get_headers( $source->minifyOptions['prependRelativePath'] );
				if ( strpos( $headers[0], '200' ) !== false ) {
					$segments  = explode( '.', $source->minifyOptions['prependRelativePath'] );
					$w3tc_ext  = strtolower( array_pop( $segments ) );
					$pc_source = $this->_precache_file( $source->minifyOptions['prependRelativePath'], $w3tc_ext );
					$w3tc_data = @file_get_contents( $pc_source->filepath );

					if ( false !== $w3tc_data ) {
						$values[] = md5( $w3tc_data );
					} else {
						return false;
					}
				} else {
					return false;
				}
			}
		}

		$w3tc_keys = array(
			'minify.debug',
			'minify.engine',
			'minify.options',
			'minify.symlinks',
		);

		if ( 'js' === $type ) {
			$w3tc_engine = $this->_config->get_string( 'minify.js.engine' );

			if ( $this->_config->get_boolean( 'minify.auto' ) ) {
				$w3tc_keys[] = 'minify.js.method';
			} else {
				array_merge(
					$w3tc_keys,
					array(
						'minify.js.combine.header',
						'minify.js.combine.body',
						'minify.js.combine.footer',
					)
				);
			}

			switch ( $w3tc_engine ) {
				case 'js':
					$w3tc_keys = array_merge(
						$w3tc_keys,
						array(
							'minify.js.strip.comments',
							'minify.js.strip.crlf',
						)
					);
					break;

				case 'yuijs':
					$w3tc_keys = array_merge(
						$w3tc_keys,
						array(
							'minify.yuijs.options.line-break',
							'minify.yuijs.options.nomunge',
							'minify.yuijs.options.preserve-semi',
							'minify.yuijs.options.disable-optimizations',
						)
					);
					break;

				case 'ccjs':
					$w3tc_keys = array_merge(
						$w3tc_keys,
						array(
							'minify.ccjs.options.compilation_level',
							'minify.ccjs.options.formatting',
						)
					);
					break;
			}
		} elseif ( 'css' === $type ) {
			$w3tc_engine = $this->_config->get_string( 'minify.css.engine' );
			$w3tc_keys[] = 'minify.css.method';

			switch ( $w3tc_engine ) {
				case 'css':
					$w3tc_keys = array_merge(
						$w3tc_keys,
						array(
							'minify.css.strip.comments',
							'minify.css.strip.crlf',
							'minify.css.imports',
						)
					);
					break;

				case 'yuicss':
					$w3tc_keys = array_merge(
						$w3tc_keys,
						array(
							'minify.yuicss.options.line-break',
						)
					);
					break;

				case 'csstidy':
					$w3tc_keys = array_merge(
						$w3tc_keys,
						array(
							'minify.csstidy.options.remove_bslash',
							'minify.csstidy.options.compress_colors',
							'minify.csstidy.options.compress_font-weight',
							'minify.csstidy.options.lowercase_s',
							'minify.csstidy.options.optimise_shorthands',
							'minify.csstidy.options.remove_last_;',
							'minify.csstidy.options.remove_space_before_important',
							'minify.csstidy.options.case_properties',
							'minify.csstidy.options.sort_properties',
							'minify.csstidy.options.sort_selectors',
							'minify.csstidy.options.merge_selectors',
							'minify.csstidy.options.discard_invalid_selectors',
							'minify.csstidy.options.discard_invalid_properties',
							'minify.csstidy.options.css_level',
							'minify.csstidy.options.preserve_css',
							'minify.csstidy.options.timestamp',
							'minify.csstidy.options.template',
						)
					);
					break;
			}
		}

		foreach ( $w3tc_keys as $w3tc_key ) {
			$values[] = $this->_config->get( $w3tc_key );
		}

		$id = substr( md5( implode( '', $this->_flatten_array( $values ) ) ), 0, 6 );

		return $id;
	}

	/**
	 * Flattens a multidimensional array into a single-dimensional array.
	 *
	 * @param array $values The array to flatten.
	 *
	 * @return array The flattened array.
	 */
	private function _flatten_array( $values ) {
		$flatten = array();

		foreach ( $values as $w3tc_key => $w3tc_value ) {
			if ( is_array( $w3tc_value ) ) {
				$flatten = array_merge( $flatten, $this->_flatten_array( $w3tc_value ) );
			} else {
				$flatten[ $w3tc_key ] = $w3tc_value;
			}
		}
		return $flatten;
	}

	/**
	 * Retrieves a value from the cache by its key.
	 *
	 * @param string $w3tc_key The key of the cached value.
	 *
	 * @return mixed The cached value or null if not found.
	 */
	public function _cache_get( $w3tc_key ) {
		$cache = $this->_get_cache();

		$w3tc_data = $cache->fetch( $w3tc_key );

		if ( isset( $w3tc_data['content'] ) ) {
			$w3tc_value = @unserialize( $w3tc_data['content'], array( 'allowed_classes' => false ) ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged

			/**
			 * `allowed_classes => false` returns `__PHP_Incomplete_Class` for
			 * any serialised object input. Callers of _cache_get() expect either
			 * `false` (miss) or a legitimate scalar / array; treating an
			 * incomplete-object result as a miss keeps the downstream paths
			 * well-typed and prevents fatal-on-array-access if a future caller
			 * dereferences the value.
			 */
			if ( is_object( $w3tc_value ) ) {
				return false;
			}

			return $w3tc_value;
		}

		return false;
	}

	/**
	 * Sets a value in the cache.
	 *
	 * @param string $w3tc_key   The cache key.
	 * @param mixed  $w3tc_value The value to store in the cache.
	 *
	 * @return bool True on success, false on failure.
	 */
	public function _cache_set( $w3tc_key, $w3tc_value ) {
		$cache = $this->_get_cache();

		return $cache->store( $w3tc_key, array( 'content' => serialize( $w3tc_value ) ) );
	}
}
