<?php
/**
 * File: Cli.php
 *
 * @package W3TC
 */

namespace W3TC;

/**
 * Class: W3TotalCache_Command
 *
 * W3 Total Cache plugin WP-CLI integration
 *
 * @package wp-cli
 * @subpackage commands/third-party
 */
class W3TotalCache_Command extends \WP_CLI_Command {
	/**
	 * Register WP-CLI commands.
	 *
	 * @since  2.8.8
	 * @static
	 *
	 * @return void
	 */
	public static function register_commands() {
		if ( \method_exists( '\WP_CLI', 'add_command' ) ) {
			\WP_CLI::add_command( 'w3-total-cache', '\W3TC\W3TotalCache_Command', array( 'shortdesc' => __( 'Manage W3TC settings, flush, and prime the cache.', 'w3-total-cache' ) ) );
			\WP_CLI::add_command( 'total-cache', '\W3TC\W3TotalCache_Command', array( 'shortdesc' => __( 'Manage W3TC settings, flush, and prime the cache.', 'w3-total-cache' ) ) );
			\WP_CLI::add_command( 'w3tc', '\W3TC\W3TotalCache_Command', array( 'shortdesc' => __( 'Manage W3TC settings, flush, and prime the cache.', 'w3-total-cache' ) ) );
		} else {
			// Backward compatibility.
			\WP_CLI::addCommand( 'w3-total-cache', '\W3TC\W3TotalCache_Command' );
			\WP_CLI::addCommand( 'total-cache', '\W3TC\W3TotalCache_Command' );
			\WP_CLI::addCommand( 'w3tc', '\W3TC\W3TotalCache_Command' );
		}
	}

	/**
	 * Creates missing files, writes Apache/Nginx rules.
	 *
	 * ## OPTIONS
	 * [<server>]
	 * : Subcommand defines server type:
	 *   apache   Create rules for an Apache server
	 *   nginx    Create rules for an Nginx server
	 *
	 * @param array $args Arguments.
	 * @param array $vars Variables.
	 *
	 * @return void
	 */
	public function fix_environment( array $args = array(), array $vars = array() ) {
		$server_type = \array_shift( $args );

		switch ( $server_type ) {
			case 'apache':
				$_SERVER['SERVER_SOFTWARE'] = 'Apache';
				break;
			case 'nginx':
				$_SERVER['SERVER_SOFTWARE'] = 'nginx';
				break;
			default:
				break;
		}

		try {
			$config      = Dispatcher::config();
			$environment = Dispatcher::component( 'Root_Environment' );
			$environment->fix_in_wpadmin( $config, true );
		} catch ( Util_Environment_Exceptions $e ) {
			\WP_CLI::error(
				\sprintf(
					// translators: 1: Error message.
					\__( 'Environment adjustment failed with error: %1$s', 'w3-total-cache' ),
					$e->getCombinedMessage()
				)
			);
		}

		\WP_CLI::success( \__( 'Environment adjusted.', 'w3-total-cache' ) );
	}

	/**
	 * Clear something from the cache.
	 *
	 * ## OPTIONS
	 * <cache>
	 * : Cache to flush
	 * all         Flush all caches
	 * posts       Flush posts (pagecache and further)
	 * post        Flush the page cache
	 * database    Flush the database cache
	 * db          Flush the database cache
	 * object      Flush the object cache
	 * minify      Flush the minify cache
	 *
	 * [--post_id=<id>]
	 * : Flush a specific post ID
	 *
	 * [--permalink=<post-permalink>]
	 * : Flush a specific permalink
	 *
	 * ## EXAMPLES
	 *     # Flush all
	 *     $ wp w3-total-cache flush all
	 *
	 *     # Flush pagecache and reverse proxies
	 *     $ wp w3-total-cache flush posts
	 *
	 * @param array $args Arguments.
	 * @param array $vars Variables.
	 *
	 * @return void
	 */
	public function flush( array $args = array(), array $vars = array() ) {
		$args = \array_unique( $args );

		do {
			$cache_type = \array_shift( $args );

			switch ( $cache_type ) {
				case 'all':
					try {
						\w3tc_flush_all();
					} catch ( \Exception $e ) {
						\WP_CLI::error( \__( 'Flushing all failed.', 'w3-total-cache' ) );
					}

					\WP_CLI::success( \__( 'Everything flushed successfully.', 'w3-total-cache' ) );
					break;
				case 'posts':
					try {
						\w3tc_flush_posts();
					} catch ( \Exception $e ) {
						\WP_CLI::error( \__( 'Flushing posts/pages failed.', 'w3-total-cache' ) );
					}

					\WP_CLI::success( \__( 'Posts/pages flushed successfully.', 'w3-total-cache' ) );
					break;
				case 'db':
				case 'database':
					try {
						$w3_db = Dispatcher::component( 'CacheFlush' );
						$w3_db->dbcache_flush();
					} catch ( \Exception $e ) {
						\WP_CLI::error( \__( 'Flushing the DB cache failed.', 'w3-total-cache' ) );
					}

					\WP_CLI::success( \__( 'The DB cache is flushed successfully.', 'w3-total-cache' ) );
					break;
				case 'minify':
					try {
						$w3_minify = Dispatcher::component( 'CacheFlush' );
						$w3_minify->minifycache_flush();
					} catch ( \Exception $e ) {
						\WP_CLI::error( \__( 'Flushing the minify cache failed.', 'w3-total-cache' ) );
					}

					\WP_CLI::success( \__( 'The minify cache is flushed successfully.', 'w3-total-cache' ) );
					break;
				case 'object':
					try {
						$w3_objectcache = Dispatcher::component( 'CacheFlush' );
						$w3_objectcache->objectcache_flush();
					} catch ( \Exception $e ) {
						\WP_CLI::error( \__( 'Flushing the object cache failed.', 'w3-total-cache' ) );
					}

					\WP_CLI::success( \__( 'The object cache is flushed successfully.', 'w3-total-cache' ) );
					break;
				case 'post':
					if ( isset( $vars['post_id'] ) ) {
						if ( \is_numeric( $vars['post_id'] ) ) {
							try {
								\w3tc_flush_post( $vars['post_id'], true );
							} catch ( \Exception $e ) {
								\WP_CLI::error( \__( 'Flushing the page from cache failed.', 'w3-total-cache' ) );
							}
							\WP_CLI::success( \__( 'The page is flushed from cache successfully.', 'w3-total-cache' ) );
						} else {
							\WP_CLI::error( \__( 'This is not a valid post id.', 'w3-total-cache' ) );
						}
					} elseif ( isset( $vars['permalink'] ) ) {
						try {
							\w3tc_flush_url( $vars['permalink'] );
						} catch ( \Exception $e ) {
							\WP_CLI::error( \__( 'Flushing the page from cache failed.', 'w3-total-cache' ) );
						}

						\WP_CLI::success( \__( 'The page is flushed from cache successfully.', 'w3-total-cache' ) );
					} else {
						if ( ! empty( $flushed_page_cache ) ) {
							break;
						}

						try {
							\w3tc_flush_posts();
						} catch ( \Exception $e ) {
							\WP_CLI::error( \__( 'Flushing the page cache failed.', 'w3-total-cache' ) );
						}

						\WP_CLI::success( \__( 'The page cache is flushed successfully.', 'w3-total-cache' ) );
					}
					break;

				default:
					\WP_CLI::error( __( 'Not specified what to flush', 'w3-total-cache' ) );
			}
		} while ( ! empty( $args ) );
	}

	/**
	 * Get or set option.
	 *
	 * Options modifications do not update your .htaccess file automatically.
	 * Use the fix_environment command afterwards to do it.
	 *
	 * ## OPTIONS
	 * <operation>
	 * : Operation to do
	 * get  Get option value
	 * set  Set option value
	 * <name>
	 * : Option name
	 *
	 * [<value>]
	 * : (for set operation) Value to set
	 *
	 * [--state]
	 * : Use state, not config
	 * State is used for backend notifications
	 *
	 * [--master]
	 * : Use master config/state
	 *
	 * [--type=<type>]
	 * : Type of data used boolean/bool/string/integer/int/array/json. Default: string
	 *
	 * [--delimiter=<delimiter>]
	 * : Delimiter to use for array type values
	 *
	 * ## EXAMPLES
	 *     # Get if pagecache enabled
	 *     $ wp w3-total-cache option get pgcache.enabled --type=boolean
	 *
	 *     # Rnable pagecache
	 *     $ wp w3-total-cache option set pgcache.enabled true --type=boolean
	 *
	 *     # Don't show wp-content permissions notification
	 *     $ wp w3-total-cache option set common.hide_note_wp_content_permissions true --state --type=boolean
	 *
	 * @param array $args Arguments.
	 * @param array $vars Variables.
	 *
	 * @return void
	 */
	public function option( array $args = array(), array $vars = array() ) {
		$op   = \array_shift( $args );
		$name = \array_shift( $args );
		$c    = null;

		if ( empty( $name ) ) {
			\WP_CLI::error( \__( '<name> parameter is not specified', 'w3-total-cache' ) );
			return;
		}
		if ( \strpos( $name, '::' ) !== false ) {
			$name = \explode( '::', $name );
		}

		if ( isset( $vars['state'] ) ) {
			$c = isset( $vars['master'] ) ? Dispatcher::config_state_master() : Dispatcher::config_state();
		} else {
			$c = isset( $vars['master'] ) ? Dispatcher::config_master() : Dispatcher::config();
		}

		if ( 'get' === $op ) {
			$type = $vars['type'] ?? 'string';

			switch ( $type ) {
				case 'boolean':
				case 'bool':
					$v = $c->get_boolean( $name ) ? 'true' : 'false';
					break;
				case 'integer':
				case 'int':
					$v = $c->get_integer( $name );
					break;
				case 'string':
					$v = $c->get_string( $name );
					break;
				case 'array':
					\var_export( $c->get_array( $name ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_var_export
					echo "\n";
					return;
				case 'json':
					echo \wp_json_encode( $c->get_array( $name ), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) . "\n";
					return;
				default:
					\WP_CLI::error( \__( 'Unknown type ', 'w3-total-cache' ) . $type );
					return;
			}

			echo \esc_html( $v ) . "\n";
		} elseif ( 'set' === $op ) {
			$type = $vars['type'] ?? 'string';

			if ( count( $args ) <= 0 ) {
				\WP_CLI::error( \__( '<value> parameter is not specified', 'w3-total-cache' ) );
				return;
			}

			$value = \array_shift( $args );

			switch ( $type ) {
				case 'boolean':
				case 'bool':
					if ( 'true' === $value || '1' === $value || 'on' === $value ) {
						$v = true;
					} elseif ( 'false' === $value || '0' === $value || 'off' === $value ) {
						$v = false;
					} else {
						\WP_CLI::error(
							\sprintf(
								// translators: 1: Value being set.
								\__( '<value> parameter "%1$s" is not boolean', 'w3-total-cache' ),
								$value
							)
						);
						return;
					}
					break;
				case 'integer':
				case 'int':
					$v = (int) $value;
					break;
				case 'string':
					$v = $value;
					break;
				case 'array':
					$delimiter = $vars['delimiter'] ?? ',';
					$v         = \explode( $delimiter, $value );
					break;
				case 'json':
					$v = \json_decode( $value );
					break;
				default:
					\WP_CLI::error( \__( 'Unknown type ', 'w3-total-cache' ) . $type );
					return;
			}

			try {
				$c->set( $name, $v );
				$c->save();
				\WP_CLI::success( \__( 'Option updated successfully.', 'w3-total-cache' ) );
			} catch ( \Exception $e ) {
				\WP_CLI::error( \__( 'Option value update failed.', 'w3-total-cache' ) );
			}
		} else {
			\WP_CLI::error( \__( '<operation> parameter is not specified', 'w3-total-cache' ) );
		}
	}

	/**
	 * Import a configuration file
	 *
	 * ## OPTIONS
	 * <filename>
	 * : Filename to import
	 *
	 * @global $wp_filesystem
	 * @see get_filesystem_method()
	 *
	 * @param array $args Arguments.
	 * @param array $vars Variables.
	 *
	 * @return void
	 *
	 * @throws \Exception Exception.
	 */
	public function import( array $args = array(), array $vars = array() ) {
		if ( 'direct' !== \get_filesystem_method() ) {
			\WP_CLI::error( \__( 'The filesystem must be direct.', 'w3-total-cache' ) );
		}

		$filename = \array_shift( $args );

		// Initialize WP_Filesystem.
		global $wp_filesystem;
		WP_Filesystem();

		try {
			$config = new Config();

			if ( ! $wp_filesystem->exists( $filename ) || ! $wp_filesystem->is_readable( $filename ) ) {
				throw new \Exception(
					\esc_html(
						sprintf(
							// Translators: 1 Filename.
							\__( 'Cant read file: %1$s', 'w3-total-cache' ),
							$filename
						)
					)
				);
			}

			if ( ! $config->import( $filename ) ) {
				throw new \Exception( \esc_html__( 'Import failed', 'w3-total-cache' ) );
			}

			$config->save();
		} catch ( \Exception $e ) {
			\WP_CLI::error(
				sprintf(
					// translators: 1: Error message.
					\__( 'Config import failed: %1$s', 'w3-total-cache' ),
					$e->getMessage()
				)
			);
		}

		\WP_CLI::success( \__( 'Configuration successfully imported.', 'w3-total-cache' ) );
	}

	/**
	 * Export configuration file
	 *
	 * ## OPTIONS
	 * <filename>
	 * : Filename to export -- Sanitized with sanitize_file_name()
	 *
	 * [--mode=<mode>]
	 * : Mode of the file. Default: 0600 (-rw-------)
	 *
	 * @global $wp_filesystem
	 * @see get_filesystem_method()
	 *
	 * @param array $args Arguments.
	 * @param array $vars Variables.
	 *
	 * @return void
	 *
	 * @throws \Exception Exception.
	 */
	public function export( array $args = array(), array $vars = array() ) {
		if ( 'direct' !== \get_filesystem_method() ) {
			\WP_CLI::error( \__( 'The filesystem must be direct.', 'w3-total-cache' ) );
		}

		$filename = \sanitize_file_name( \array_shift( $args ) );
		$mode     = $vars['mode'] ?? '0600';

		// Initialize WP_Filesystem.
		global $wp_filesystem;
		WP_Filesystem();

		// Try to export the config and write to file.
		try {
			$config = new Config();

			if ( ! $wp_filesystem->put_contents( $filename, $config->export( $filename ), octdec( $mode ) ) ) {
				throw new \Exception( \esc_html__( 'Export failed', 'w3-total-cache' ) );
			}
		} catch ( \Exception $e ) {
			\WP_CLI::error(
				sprintf(
					// translators: 1: Error message.
					\__( 'Config export failed: %1$s', 'w3-total-cache' ),
					$e->getMessage()
				)
			);
		}

		\WP_CLI::success(
			\sprintf(
				// translators: 1: Filename.
				\__( 'Configuration successfully exported to "%1$s" with mode "%2$s".', 'w3-total-cache' ),
				$filename,
				$mode
			)
		);
	}

	/**
	 * Update query string for all static files.
	 *
	 * @return void
	 */
	public function querystring() {
		try {
			$w3_querystring = Dispatcher::component( 'CacheFlush' );
			$w3_querystring->browsercache_flush();
		} catch ( \Exception $e ) {
			\WP_CLI::error(
				sprintf(
					// translators: 1: Error message.
					\__( 'updating the query string failed. with error %1$s', 'w3-total-cache' ),
					$e->getMessage()
				)
			);
		}

		\WP_CLI::success( \__( 'The query string was updated successfully.', 'w3-total-cache' ) );
	}

	/**
	 * Purges URLs from CDN and varnish if enabled.
	 *
	 * @param array $args List of files to be purged, absolute path or relative to WordPress installation path.
	 *
	 * @return void
	 */
	public function cdn_purge( array $args = array() ) {
		$purgeitems = array();

		foreach ( $args as $file ) {
			$cdncommon = Dispatcher::component( 'Cdn_Core' );
			if ( file_exists( $file ) ) {
				$local_path = $file;
			} else {
				$local_path = ABSPATH . $file;
			}

			$remote_path  = $file;
			$purgeitems[] = $cdncommon->build_file_descriptor( $local_path, $remote_path );
		}

		try {
			$w3_cdn_purge = Dispatcher::component( 'CacheFlush' );
			$w3_cdn_purge->cdn_purge_files( $purgeitems );
		} catch ( \Exception $e ) {
			\WP_CLI::error(
				\sprintf(
					// translators: 1: Error message.
					\__( 'Files did not successfully purge with error %1$s', 'w3-total-cache' ),
					$e->getMessage()
				)
			);
		}

		\WP_CLI::success( \__( 'Files purged successfully.', 'w3-total-cache' ) );
	}

	/**
	 * Generally triggered from a cronjob, performs manual page cache Garbage collection.
	 *
	 * @return void
	 */
	public function pgcache_cleanup() {
		try {
			$o = Dispatcher::component( 'PgCache_Plugin_Admin' );
			$o->cleanup();
		} catch ( \Exception $e ) {
			\WP_CLI::error(
				\sprintf(
					// translators: 1: Error message.
					\__( 'PageCache Garbage cleanup failed: %1$s', 'w3-total-cache' ),
					$e->getMessage()
				)
			);
		}

		\WP_CLI::success( \__( 'PageCache Garbage cleanup triggered successfully.', 'w3-total-cache' ) );
	}

	/**
	 * Generally triggered from a cronjob, performs manual page cache priming
	 * ## OPTIONS
	 * [--start=<start>]
	 * : Start since <start> entry of sitemap
	 *
	 * [--limit=<limit>]
	 * : load no more than <limit> pages
	 *
	 * @param array $args Arguments.
	 * @param array $vars Variables.
	 *
	 * @return void
	 */
	public function pgcache_prime( array $args = array(), array $vars = array() ) {
		try {
			$log_callback = function ( $m ) {
				\WP_CLI::log( $m );
			};

			$o = Dispatcher::component( 'PgCache_Plugin_Admin' );

			$o->prime(
				( isset( $vars['start'] ) ? $vars['start'] - 1 : null ),
				( isset( $vars['limit'] ) ? $vars['limit'] : null ),
				$log_callback
			);

		} catch ( \Exception $e ) {
			\WP_CLI::error(
				\sprintf(
					// translators: 1: Error message.
					\__( 'PageCache Priming did failed: %1$s', 'w3-total-cache' ),
					$e->getMessage()
				)
			);
		}

		\WP_CLI::success( \__( 'PageCache Priming triggered successfully.', 'w3-total-cache' ) );
	}

	/**
	 * Generally triggered from a cronjob, processes always cached queue.
	 *
	 * @return void
	 */
	public function alwayscached_process() {
		if ( ! Extension_AlwaysCached_Plugin::is_enabled() ) {
			\WP_CLI::error(
				\__( 'Always Cached feature is not enabled.', 'w3-total-cache' )
			);
			return;
		}

		try {
			Extension_AlwaysCached_Worker::run( false );
		} catch ( \Exception $e ) {
			\WP_CLI::error(
				\sprintf(
					// translators: 1: Error message.
					\__( 'Always Cached queue processer failed: %1$s', 'w3-total-cache' ),
					$e->getMessage()
				)
			);
		}

		\WP_CLI::success( \__( 'Always Cached queue processed successfully.', 'w3-total-cache' ) );
	}

	/**
	 * Generally triggered from a cronjob, processes AlwaysCached queue.
	 *
	 * @return void
	 */
	public function alwayscached_clear() {
		if ( ! Extension_AlwaysCached_Plugin::is_enabled() ) {
			\WP_CLI::error(
				\__( 'Always Cached feature is not enabled', 'w3-total-cache' )
			);
			return;
		}

		try {
			Extension_AlwaysCached_Queue::empty();
		} catch ( \Exception $e ) {
			\WP_CLI::error(
				\sprintf(
					// translators: 1: Error message.
					\__( 'Always Cached queue empty failed: %1$s', 'w3-total-cache' ),
					$e->getMessage()
				)
			);
		}

		\WP_CLI::success( \__( 'Always Cached queue emptied successfully.', 'w3-total-cache' ) );
	}
}

// Register WP-CLI commands.
add_action( 'init', array( '\W3TC\W3TotalCache_Command', 'register_commands' ), 10, 0 );
