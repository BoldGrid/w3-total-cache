<?php
/**
 * File: Minify_MinifiedFileRequestHandler.php
 *
 * @package W3TC
 */

namespace W3TC;

// Define repeated regex to simplify changes.
define( 'MINIFY_AUTO_FILENAME_REGEX', '([a-zA-Z0-9-_]+)\\.(css|js)([?].*)?' );
define( 'MINIFY_MANUAL_FILENAME_REGEX', '([a-f0-9]+)\\.(.+)\\.(include(\\-(footer|body))?)\\.[a-f0-9]+\\.(css|js)' );

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
	 * Processes the given file request and serves minified content.
	 *
	 * @param string|null $file  The requested file for minification. Defaults to null.
	 * @param bool        $quiet Whether to suppress errors and output debugging information.
	 *
	 * @return array|void An array of minification results, or void in certain cases.
	 *
	 * @throws \Exception If a recoverable error occurs during the minification process.
	 */
	public function process( $file = null, $quiet = false ) {
		// Check for rewrite test request.
		$rewrite_marker = 'rewrite_test.css';
		if ( substr( $file, strlen( $file ) - strlen( $rewrite_marker ) ) === $rewrite_marker ) {
			echo 'Minify OK';
			exit();
		}

		$filelength_test_marker = 'XXX.css';
		if ( substr( $file, strlen( $file ) - strlen( $filelength_test_marker ) ) === $filelength_test_marker ) {
			$cache = $this->_get_cache();
			header( 'Content-type: text/css' );

			if ( ! $cache->store( basename( $file ), array( 'content' => 'content ok' ) ) ) {
				echo 'error storing';
			} else {
				if (
					function_exists( 'brotli_compress' ) &&
					$this->_config->get_boolean( 'browsercache.enabled' ) &&
					$this->_config->get_boolean( 'browsercache.cssjs.brotli' )
				) {
					if ( ! $cache->store( basename( $file ) . '_br', array( 'content' => brotli_compress( 'content ok' ) ) ) ) {
						echo 'error storing';
						exit();
					}
				}

				if (
					function_exists( 'gzencode' ) &&
					$this->_config->get_boolean( 'browsercache.enabled' ) &&
					$this->_config->get_boolean( 'browsercache.cssjs.compression' )
				) {
					if ( ! $cache->store( basename( $file ) . '_gzip', array( 'content' => gzencode( 'content ok' ) ) ) ) {
						echo 'error storing';
						exit();
					}
				}

				$v = $cache->fetch( basename( $file ) );
				if ( 'content ok' === $v['content'] ) {
					echo 'content ok';
				} else {
					echo 'error storing';
				}
			}

			exit();
		}

		// remove querystring.
		if ( preg_match( '~(.+)(\?x[0-9]{5})$~', $file, $m ) ) {
			$file = $m[1];
		}

		// remove blog_id.
		$levels = '';
		if ( defined( 'W3TC_BLOG_LEVELS' ) ) {
			for ( $n = 0; $n < W3TC_BLOG_LEVELS; $n++ ) {
				$levels .= '[0-9]+\/';
			}
		}

		if ( preg_match( '~^(' . $levels . '[0-9]+)\/(.+)$~', $file, $matches ) ) {
			$file = $matches[2];
		}

		// normalize according to browsercache.
		$file = Dispatcher::requested_minify_filename( $this->_config, $file );

		// parse file.
		$hash     = '';
		$matches  = null;
		$location = '';
		$type     = '';

		if ( preg_match( '~^' . MINIFY_AUTO_FILENAME_REGEX . '$~', $file, $matches ) ) {
			list( , $hash, $type ) = $matches;
		} elseif ( preg_match( '~^' . MINIFY_MANUAL_FILENAME_REGEX . '$~', $file, $matches ) ) {
			list( , $theme, $template, $location, , , $type ) = $matches;
		} else {
			return $this->finish_with_error( sprintf( 'Bad file param format: "%s"', $file ), $quiet, false );
		}

		// Set cache engine.
		$cache = $this->_get_cache();
		\W3TCL\Minify\Minify::setCache( $cache );

		// Set cache ID.
		$cache_id = $this->get_cache_id( $file );
		\W3TCL\Minify\Minify::setCacheId( $file );

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
			$_GET['g']                         = $location;
			$serve_options['minApp']['groups'] = $this->get_groups( $theme, $template, $type );
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
					$engine = 'combinejs';
					break;

				default:
					$engine = $this->_config->get_string( 'minify.js.engine' );
					if ( ! $w3_minifier->exists( $engine ) || ! $w3_minifier->available( $engine ) ) {
						$engine = 'js';
					}

					break;
			}
		} elseif ( 'css' === $type ) {
			$minifier_type = 'text/css';

			if ( ( $hash || 'include' === $location ) && 'combine' === $this->_config->get_string( 'minify.css.method' ) ) {
				$engine = 'combinecss';
			} else {
				$engine = $this->_config->get_string( 'minify.css.engine' );
				if ( ! $w3_minifier->exists( $engine ) || ! $w3_minifier->available( $engine ) ) {
					$engine = 'css';
				}
			}
		}

		// Initialize minifier.
		$w3_minifier->init( $engine );

		$serve_options['minifiers'][ $minifier_type ]       = $w3_minifier->get_minifier( $engine );
		$serve_options['minifierOptions'][ $minifier_type ] = $w3_minifier->get_options( $engine );

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
			if ( $error_file === $file ) {
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
	 * @param string $url The URL for which custom data is being retrieved.
	 *
	 * @return mixed|null Custom data associated with the URL, or null if none exists.
	 */
	public function get_url_custom_data( $url ) {
		if ( preg_match( '~/' . MINIFY_AUTO_FILENAME_REGEX . '$~', $url, $matches ) ) {
			list( , $hash, $type ) = $matches;

			$key = $this->get_custom_data_key( $hash, $type );
			return $this->_cache_get( $key );
		}

		return null;
	}

	/**
	 * Associates custom data with a specified file.
	 *
	 * @param string $file The file to associate the custom data with.
	 * @param mixed  $data The custom data to store.
	 *
	 * @return void
	 */
	public function set_file_custom_data( $file, $data ) {
		if ( preg_match( '~' . MINIFY_AUTO_FILENAME_REGEX . '$~', $file, $matches ) ) {
			list( , $hash, $type ) = $matches;

			$key = $this->get_custom_data_key( $hash, $type );
			$this->_cache_set( $key, $data );
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
		$result = array();

		switch ( $type ) {
			case 'css':
				$groups = $this->_config->get_array( 'minify.css.groups' );
				break;

			case 'js':
				$groups = $this->_config->get_array( 'minify.js.groups' );
				break;

			default:
				return $result;
		}

		if ( isset( $groups[ $theme ]['default'] ) ) {
			$locations = (array) $groups[ $theme ]['default'];
		} else {
			$locations = array();
		}

		if ( 'default' !== $template && isset( $groups[ $theme ][ $template ] ) ) {
			$locations = array_merge_recursive( $locations, (array) $groups[ $theme ][ $template ] );
		}

		foreach ( $locations as $location => $config ) {
			if ( ! empty( $config['files'] ) ) {
				foreach ( (array) $config['files'] as $url ) {
					if ( ! Util_Environment::is_url( $url ) ) {
						$url = Util_Environment::home_domain_root_url() . '/' . ltrim( $url, '/' );
					}

					$file = Util_Environment::url_to_docroot_filename( $url );

					if ( is_null( $file ) ) {
						// it's external url.
						$precached_file = $this->_precache_file( $url, $type );

						if ( $precached_file ) {
							$result[ $location ][ $url ] = $precached_file;
						} else {
							Minify_Core::debug_error( sprintf( 'Unable to cache remote url: "%s"', $url ) );
						}
					} else {
						$path = Util_Environment::document_root() . '/' . $file;

						if ( file_exists( $path ) ) {
							$result[ $location ][ $file ] = '//' . $file;
						} else {
							Minify_Core::debug_error( sprintf( 'File "%s" doesn\'t exist', $path ) );
						}
					}
				}
			}
		}

		return $result;
	}

	/**
	 * Retrieves the cache ID for the specified file.
	 *
	 * @param string $file The file for which the cache ID is retrieved.
	 *
	 * @return string The cache ID for the file.
	 */
	public function get_cache_id( $file ) {
		return $file;
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

			foreach ( $files as $file ) {
				if ( is_a( $file, '\W3TCL\Minify\Minify_Source' ) ) {
					$path = $file->filepath;
				} else {
					$path = rtrim( $document_root, '/' ) . '/' . ltrim( $file, '/' );
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
		$key = $this->get_id_key_group( $theme, $template, $location, $type );
		$id  = $this->_cache_get( $key );

		if ( false === $id ) {
			$sources = $this->get_sources_group( $theme, $template, $location, $type );

			if ( count( $sources ) ) {
				$id = $this->_generate_id( $sources, $type );

				if ( $id ) {
					$this->_cache_set( $key, $id );
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

		$result = array();
		if ( is_array( $files ) && count( $files ) > 0 ) {
			foreach ( $files as $file ) {
				$docroot_filename = Util_Environment::url_to_docroot_filename( $file );

				if ( Util_Environment::is_url( $file ) && is_null( $docroot_filename ) ) {
					// it's external url.
					$precached_file = $this->_precache_file( $file, $type );

					if ( $precached_file ) {
						$result[] = $precached_file;
					} else {
						Minify_Core::debug_error( sprintf( 'Unable to cache remote file: "%s"', $file ) );
					}
				} else {
					$path = Util_Environment::docroot_to_full_filename( $docroot_filename );

					if ( @file_exists( $path ) ) {
						$result[] = $file;
					} else {
						Minify_Core::debug_error( sprintf( 'File "%s" doesn\'t exist', $file ) );
					}
				}
			}
		} else {
			Minify_Core::debug_error( sprintf( 'Unable to fetch custom files list: "%s.%s"', $hash, $type ), false, 404 );
		}

		if ( $this->_config->get_boolean( 'minify.debug' ) ) {
			Minify_Core::log( implode( "\n", $files ) );
		}

		return $result;
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

		$message = '<h1>W3TC Minify Error</h1>';

		if ( $this->_config->get_boolean( 'minify.debug' ) ) {
			$message .= sprintf( '<p>%s.</p>', $error );
		} else {
			$message .= '<p>Enable debug mode to see error message.</p>';
		}

		if ( $quiet ) {
			return array(
				'content' => $message,
			);
		}

		if ( defined( 'W3TC_IN_MINIFY' ) ) {
			status_header( 400 );
			echo esc_html( $message );
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
	 * @param string $url  The URL of the file.
	 * @param string $type The type of the file (e.g., CSS or JS).
	 *
	 * @return mixed The minified source or false if caching fails.
	 */
	public function _precache_file( $url, $type ) {
		$lifetime   = $this->_config->get_integer( 'minify.lifetime' );
		$cache_path = sprintf( '%s/minify_%s.%s', Util_Environment::cache_blog_dir( 'minify' ), md5( $url ), $type );

		if ( ! file_exists( $cache_path ) || @filemtime( $cache_path ) < ( time() - $lifetime ) ) {
			if ( ! @is_dir( dirname( $cache_path ) ) ) {
				Util_File::mkdir_from_safe( dirname( $cache_path ), W3TC_CACHE_DIR );
			}

			// google-fonts (most used for external inclusion) doesnt return full content (unicode-range) for simple useragents.
			Util_Http::download(
				$url,
				$cache_path,
				array(
					'user-agent' => 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/69.0.3497.92',
				)
			);
		}

		return file_exists( $cache_path ) ? $this->_get_minify_source( $cache_path, $url ) : false;
	}

	/**
	 * Retrieves a minify source from a file path and URL.
	 *
	 * @param string $file_path The file path to the cached file.
	 * @param string $url       The original URL of the file.
	 *
	 * @return \W3TCL\Minify\Minify_Source The minify source object.
	 */
	public function _get_minify_source( $file_path, $url ) {
		return new \W3TCL\Minify\Minify_Source(
			array(
				'filepath'      => $file_path,
				'minifyOptions' => array(
					'prependRelativePath' => $url,
				),
			)
		);
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
					$config = array(
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
						$inner_cache = new Cache_Memcached( $config );
					} elseif ( class_exists( 'Memcache' ) ) {
						$inner_cache = new Cache_Memcache( $config );
					}

					break;

				case 'redis':
					$config = array(
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

					$inner_cache = new Cache_Redis( $config );

					break;

				case 'apc':
					$config = array(
						'blog_id'     => Util_Environment::blog_id(),
						'instance_id' => Util_Environment::instance_id(),
						'host'        => Util_Environment::host(),
						'module'      => 'minify',
					);

					if ( function_exists( 'apcu_store' ) ) {
						$inner_cache = new Cache_Apcu( $config );
					} elseif ( function_exists( 'apc_store' ) ) {
						$inner_cache = new Cache_Apc( $config );
					}

					break;

				case 'eaccelerator':
					$config = array(
						'blog_id'     => Util_Environment::blog_id(),
						'instance_id' => Util_Environment::instance_id(),
						'host'        => Util_Environment::host(),
						'module'      => 'minify',
					);

					$inner_cache = new Cache_Eaccelerator( $config );

					break;

				case 'xcache':
					$config = array(
						'blog_id'     => Util_Environment::blog_id(),
						'instance_id' => Util_Environment::instance_id(),
						'host'        => Util_Environment::host(),
						'module'      => 'minify',
					);

					$inner_cache = new Cache_Xcache( $config );

					break;

				case 'wincache':
					$config = array(
						'blog_id'     => Util_Environment::blog_id(),
						'instance_id' => Util_Environment::instance_id(),
						'host'        => Util_Environment::host(),
						'module'      => 'minify',
					);

					$inner_cache = new Cache_Wincache( $config );

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
			$file  = Util_Request::get_string( 'file' );
			$state = Dispatcher::config_state_master();

			if ( $file ) {
				$state->set( 'minify.error.file', $file );
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
		$body       = @file_get_contents( W3TC_INC_DIR . '/email/minify_error_notification.php' );

		$headers = array(
			sprintf( 'From: "%s" <%s>', addslashes( $from_name ), $from_email ),
			sprintf( 'Reply-To: "%s" <%s>', addslashes( $to_name ), $to_email ),
			'Content-Type: text/html; charset=utf-8',
		);

		@set_time_limit( $this->_config->get_integer( 'timelimit.email_send' ) );

		$result = @wp_mail( $to_email, 'W3 Total Cache Error Notification', $body, implode( "\n", $headers ) );

		return $result;
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
				$data = @file_get_contents( $source );

				if ( false !== $data ) {
					$values[] = md5( $data );
				} else {
					return false;
				}
			} else {
				$headers = @get_headers( $source->minifyOptions['prependRelativePath'] );
				if ( strpos( $headers[0], '200' ) !== false ) {
					$segments  = explode( '.', $source->minifyOptions['prependRelativePath'] );
					$ext       = strtolower( array_pop( $segments ) );
					$pc_source = $this->_precache_file( $source->minifyOptions['prependRelativePath'], $ext );
					$data      = @file_get_contents( $pc_source->filepath );

					if ( false !== $data ) {
						$values[] = md5( $data );
					} else {
						return false;
					}
				} else {
					return false;
				}
			}
		}

		$keys = array(
			'minify.debug',
			'minify.engine',
			'minify.options',
			'minify.symlinks',
		);

		if ( 'js' === $type ) {
			$engine = $this->_config->get_string( 'minify.js.engine' );

			if ( $this->_config->get_boolean( 'minify.auto' ) ) {
				$keys[] = 'minify.js.method';
			} else {
				array_merge(
					$keys,
					array(
						'minify.js.combine.header',
						'minify.js.combine.body',
						'minify.js.combine.footer',
					)
				);
			}

			switch ( $engine ) {
				case 'js':
					$keys = array_merge(
						$keys,
						array(
							'minify.js.strip.comments',
							'minify.js.strip.crlf',
						)
					);
					break;

				case 'yuijs':
					$keys = array_merge(
						$keys,
						array(
							'minify.yuijs.options.line-break',
							'minify.yuijs.options.nomunge',
							'minify.yuijs.options.preserve-semi',
							'minify.yuijs.options.disable-optimizations',
						)
					);
					break;

				case 'ccjs':
					$keys = array_merge(
						$keys,
						array(
							'minify.ccjs.options.compilation_level',
							'minify.ccjs.options.formatting',
						)
					);
					break;
			}
		} elseif ( 'css' === $type ) {
			$engine = $this->_config->get_string( 'minify.css.engine' );
			$keys[] = 'minify.css.method';

			switch ( $engine ) {
				case 'css':
					$keys = array_merge(
						$keys,
						array(
							'minify.css.strip.comments',
							'minify.css.strip.crlf',
							'minify.css.imports',
						)
					);
					break;

				case 'yuicss':
					$keys = array_merge(
						$keys,
						array(
							'minify.yuicss.options.line-break',
						)
					);
					break;

				case 'csstidy':
					$keys = array_merge(
						$keys,
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

		foreach ( $keys as $key ) {
			$values[] = $this->_config->get( $key );
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

		foreach ( $values as $key => $value ) {
			if ( is_array( $value ) ) {
				$flatten = array_merge( $flatten, $this->_flatten_array( $value ) );
			} else {
				$flatten[ $key ] = $value;
			}
		}
		return $flatten;
	}

	/**
	 * Retrieves a value from the cache by its key.
	 *
	 * @param string $key The key of the cached value.
	 *
	 * @return mixed The cached value or null if not found.
	 */
	public function _cache_get( $key ) {
		$cache = $this->_get_cache();

		$data = $cache->fetch( $key );

		if ( isset( $data['content'] ) ) {
			$value = @unserialize( $data['content'] );

			return $value;
		}

		return false;
	}

	/**
	 * Sets a value in the cache.
	 *
	 * @param string $key   The cache key.
	 * @param mixed  $value The value to store in the cache.
	 *
	 * @return bool True on success, false on failure.
	 */
	public function _cache_set( $key, $value ) {
		$cache = $this->_get_cache();

		return $cache->store( $key, array( 'content' => serialize( $value ) ) );
	}
}
