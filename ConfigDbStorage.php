<?php
/**
 * File: ConfigCompiler.php
 *
 * @package W3TC
 */

namespace W3TC;

/**
 * Class ConfigDbStorage
 *
 * Provides configuration data using cache
 *
 * phpcs:disable WordPress.PHP.NoSilencedErrors.Discouraged
 * phpcs:disable WordPress.DB.DirectDatabaseQuery
 * phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
 * phpcs:disable WordPress.PHP.DevelopmentFunctions.error_log_error_log
 * phpcs:disable Generic.CodeAnalysis.UnusedFunctionParameter
 */
class ConfigDbStorage {
	/**
	 * Retrieves an array configuration from storage.
	 *
	 * @param int  $blog_id Blog ID to retrieve the configuration for.
	 * @param bool $preview Whether to load the preview configuration.
	 *
	 * @return array|null Array of configuration settings or null if none exist.
	 */
	public static function util_array_from_storage( $blog_id, $preview ) {
		$content = self::load_content( $blog_id, $preview );
		$config  = @json_decode( $content, true );

		if ( is_array( $config ) ) {
			return $config;
		}

		return null;
	}

	/**
	 * Checks if a configuration item exists in storage.
	 *
	 * @param int  $blog_id Blog ID to check.
	 * @param bool $preview Whether to check the preview configuration.
	 *
	 * @return bool True if the item exists, false otherwise.
	 */
	public static function is_item_exists( $blog_id, $preview ) {
		$content = self::load_content( $blog_id, $preview );
		return is_null( $content );
	}

	/**
	 * Removes a configuration item from storage.
	 *
	 * @param int  $blog_id Blog ID of the item to remove.
	 * @param bool $preview Whether to remove the preview configuration.
	 *
	 * @return void
	 */
	public static function remove_item( $blog_id, $preview ) {
		$table       = self::get_table( $blog_id );
		$option_name = self::get_option_name( $blog_id, $preview );

		global $wpdb;
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM $table WHERE option_name = %s",
				$option_name
			)
		);
	}

	/**
	 * Copies the configuration between production and preview environments.
	 *
	 * @param int $blog_id   Blog ID to copy the configuration for.
	 * @param int $direction Copy direction: >0 for preview to production, <=0 for production to preview.
	 *
	 * @return void
	 */
	public static function preview_production_copy( $blog_id, $direction ) {
		if ( $direction > 0 ) {
			$content = self::load_content( $blog_id, true );
			self::save_item( $blog_id, false, $content );
		} else {
			$content = self::load_content( $blog_id, false );
			self::save_item( $blog_id, true, $content );
		}
	}

	/**
	 * Saves a configuration item to storage.
	 *
	 * @param int          $blog_id Blog ID to save the configuration for.
	 * @param bool         $preview Whether to save as a preview configuration.
	 * @param string|array $data    Configuration data to save, either as a string or an array.
	 *
	 * @return void
	 */
	public static function save_item( $blog_id, $preview, $data ) {
		if ( is_string( $data ) ) {
			$config = $data;
		} else {
			$config = wp_json_encode( $data );
		}

		$table       = self::get_table( $blog_id );
		$option_name = self::get_option_name( $blog_id, $preview );

		global $wpdb;
		$is_exists = ! is_null( self::load_content( $blog_id, $preview ) );

		if ( $is_exists ) {
			$wpdb->query(
				$wpdb->prepare(
					"UPDATE $table SET option_value = %s WHERE option_name = %s",
					$config,
					$option_name
				)
			);
		} else {
			$wpdb->query(
				$wpdb->prepare(
					"INSERT INTO $table (option_name, option_value) VALUES (%s, %s)",
					$option_name,
					$config
				)
			);
		}
	}

	/**
	 * Retrieves the database table name for storing configuration.
	 *
	 * @param int $blog_id Blog ID to retrieve the table for.
	 *
	 * @return string Database table name.
	 */
	private static function get_table( $blog_id ) {
		if ( defined( 'W3TC_CONFIG_DATABASE_TABLE' ) ) {
			$template = W3TC_CONFIG_DATABASE_TABLE;
		} else {
			if ( is_multisite() ) {
				error_log( 'Please use W3TC_CONFIG_DATABASE_TABLE constant, funcationality without it is not stable in multisite mode' );
			}

			global $table_prefix;
			$template = $table_prefix . 'options';
		}

		if ( $blog_id <= 0 ) {
			$blog_id_prefix = '';
		} else {
			$blog_id_prefix = $blog_id . '_';
		}

		return str_replace( '{blog_id_prefix}', $blog_id_prefix, $template );
	}

	/**
	 * Constructs the option name for a configuration item.
	 *
	 * @param int  $blog_id Blog ID for the configuration.
	 * @param bool $preview Whether to generate the name for a preview configuration.
	 *
	 * @return string Option name for the configuration.
	 */
	private static function get_option_name( $blog_id, $preview ) {
		return 'w3tc_config_' . $blog_id . ( $preview ? '_preview' : '' );
	}

	/**
	 * Loads the configuration content from storage.
	 *
	 * @param int  $blog_id Blog ID to load the configuration for.
	 * @param bool $preview Whether to load the preview configuration.
	 *
	 * @return string|null Configuration content as a JSON-encoded string, or null if none exists.
	 */
	private static function load_content( $blog_id, $preview ) {
		$table       = self::get_table( $blog_id );
		$option_name = self::get_option_name( $blog_id, $preview );

		if ( isset( $GLOBALS['wpdb'] ) ) {
			global $wpdb;
			$row = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT option_value FROM $table WHERE option_name = %s LIMIT 1",
					$option_name
				)
			);
		} else {
			$db = new _WpdbEssentials( DB_USER, DB_PASSWORD, DB_NAME, DB_HOST );

			if ( $db->ready ) {
				$row = $db->get_row(
					$db->prepare(
						"SELECT option_value FROM $table WHERE option_name = %s LIMIT 1",
						$option_name
					)
				);
			} else {
				error_log( 'Failed to load w3tc config' );
				$row = null;
			}

			// close connection immediately so that real pooled connection may be reused by later inialized wpdb object.
			$db->close();
		}

		if ( is_object( $row ) ) {
			return $row->option_value;
		}

		return null;
	}
}

/**
 * Class _WpdbEssentials
 *
 * phpcs:disable PSR2.Methods.MethodDeclaration.Underscore
 * phpcs:disable PEAR.NamingConventions.ValidClassName.StartWithCapital
 * phpcs:disable Generic.Files.OneObjectStructurePerFile.MultipleFound
 * phpcs:disable WordPress.DB.RestrictedFunctions
 * phpcs:disable WordPress.CodeAnalysis.AssignmentInCondition.FoundInWhileCondition
 */
class _WpdbEssentials {
	/**
	 * Last error
	 *
	 * @var string
	 */
	public $last_error = '';

	/**
	 * Number of rows
	 *
	 * @var integer
	 */
	public $num_rows = 0;

	/**
	 * Number of rows affected
	 *
	 * @var integer
	 */
	public $rows_affected = 0;

	/**
	 * Last query
	 *
	 * @var string
	 */
	public $last_query;

	/**
	 * Last result
	 *
	 * @var array
	 */
	public $last_result;

	/**
	 * Is the database connection ready?
	 *
	 * @var    bool
	 * @access private
	 */
	public $ready;

	/**
	 * Use mysqli.
	 *
	 * @var    bool
	 * @access private
	 */
	private $use_mysqli;

	/**
	 * MySQL connection handle.
	 *
	 * @var    \mysqli|resource|false
	 * @access private
	 */
	private $dbh;

	/**
	 * Database user.
	 *
	 * @var    string
	 * @access private
	 */
	private $dbuser;

	/**
	 * Database password.
	 *
	 * @var    string
	 * @access private
	 */
	private $dbpassword;

	/**
	 * Database name.
	 *
	 * @var    string
	 * @access private
	 */
	private $dbname;

	/**
	 * Database host.
	 *
	 * @var    string
	 * @access private
	 */
	private $dbhost;

	/**
	 * Database is connected?
	 *
	 * @var    bool
	 * @access private
	 */
	private $has_connected;

	/**
	 * Is MySQL?
	 *
	 * @var    bool
	 * @access private
	 */
	private $is_mysql;

	/**
	 * MySQL result.
	 *
	 * @var    \mysqli_result|resource|false
	 * @access private
	 */
	private $result;

	/**
	 * Initializes the database connection.
	 *
	 * @param string $dbuser     The database username.
	 * @param string $dbpassword The database password.
	 * @param string $dbname     The name of the database.
	 * @param string $dbhost     The hostname of the database.
	 *
	 * @return void
	 */
	public function __construct( $dbuser, $dbpassword, $dbname, $dbhost ) {
		if ( function_exists( 'mysqli_connect' ) ) {
			if ( defined( 'WP_USE_EXT_MYSQL' ) ) {
				$this->use_mysqli = ! WP_USE_EXT_MYSQL;
			} elseif ( version_compare( phpversion(), '5.5', '>=' ) || ! function_exists( 'mysql_connect' ) ) {
				$this->use_mysqli = true;
			} elseif ( false !== strpos( $GLOBALS['wp_version'], '-' ) ) {
				$this->use_mysqli = true;
			}
		}

		$this->dbuser     = $dbuser;
		$this->dbpassword = $dbpassword;
		$this->dbname     = $dbname;
		$this->dbhost     = $dbhost;

		$this->db_connect();
	}

	/**
	 * Establishes a connection to the database.
	 *
	 * @param bool $allow_bail Whether to allow bail on connection failure.
	 *
	 * @return void
	 */
	public function db_connect( $allow_bail = true ) {
		$this->is_mysql = true;

		/*
		 * Deprecated in 3.9+ when using MySQLi. No equivalent
		 * $new_link parameter exists for mysqli_* functions.
		 */
		$new_link     = defined( 'MYSQL_NEW_LINK' ) ? MYSQL_NEW_LINK : true;
		$client_flags = defined( 'MYSQL_CLIENT_FLAGS' ) ? MYSQL_CLIENT_FLAGS : 0;

		if ( $this->use_mysqli ) {
			$this->dbh = mysqli_init();

			// mysqli_real_connect doesn't support the host param including a port or socket
			// like mysql_connect does. This duplicates how mysql_connect detects a port and/or socket file.
			$port           = null;
			$socket         = null;
			$host           = $this->dbhost;
			$port_or_socket = strstr( $host, ':' );
			if ( ! empty( $port_or_socket ) ) {
				$host           = substr( $host, 0, strpos( $host, ':' ) );
				$port_or_socket = substr( $port_or_socket, 1 );
				if ( 0 !== strpos( $port_or_socket, '/' ) ) {
					$port         = intval( $port_or_socket );
					$maybe_socket = strstr( $port_or_socket, ':' );
					if ( ! empty( $maybe_socket ) ) {
						$socket = substr( $maybe_socket, 1 );
					}
				} else {
					$socket = $port_or_socket;
				}
			}

			if ( WP_DEBUG ) {
				mysqli_real_connect( $this->dbh, $host, $this->dbuser, $this->dbpassword, null, $port, $socket, $client_flags );
			} else {
				@mysqli_real_connect( $this->dbh, $host, $this->dbuser, $this->dbpassword, null, $port, $socket, $client_flags );
			}

			if ( $this->dbh->connect_errno ) {
				$this->dbh        = null;
				$this->last_error = 'Connection failed with ' . $this->dbh->connect_errno . ' error code';
				if ( WP_DEBUG ) {
					echo esc_html( $this->last_error );
				}
			}
		} elseif ( WP_DEBUG ) {
			$this->dbh = mysql_connect( $this->dbhost, $this->dbuser, $this->dbpassword, $new_link, $client_flags );
		} else {
			$this->dbh = @mysql_connect( $this->dbhost, $this->dbuser, $this->dbpassword, $new_link, $client_flags );
		}

		if ( $this->dbh ) {
			$this->has_connected = true;
			$this->ready         = true;
			$this->select( $this->dbname, $this->dbh );
		} elseif ( WP_DEBUG ) {
			esc_html_e( 'Failed to connect to mysql server', 'w3-total-cache' );
		}
	}

	/**
	 * Selects a database to use for the connection.
	 *
	 * @param string           $db  The name of the database to select.
	 * @param \mysqli|resource $dbh Optional. The database connection resource. Defaults to the current connection.
	 *
	 * @return void
	 */
	public function select( $db, $dbh = null ) {
		if ( $this->use_mysqli ) {
			$success = mysqli_select_db( $dbh, $db );
		} else {
			$success = mysql_select_db( $db, $dbh );
		}
		if ( ! $success ) {
			$this->ready = false;
			if ( WP_DEBUG ) {
				esc_html_e( 'Failed to select database', 'w3-total-cache' );
			}
		}
	}

	/**
	 * Prepares a database query string with safe and escaped placeholders.
	 *
	 * @param string $query The SQL query with placeholders.
	 * @param mixed  $args  Arguments to substitute into the query.
	 *
	 * @return string The prepared SQL query.
	 */
	public function prepare( $query, $args ) {
		$args = func_get_args();
		array_shift( $args );
		// If args were passed as an array (as in vsprintf), move them up.
		if ( isset( $args[0] ) && is_array( $args[0] ) ) {
			$args = $args[0];
		}

		$query = str_replace( "'%s'", '%s', $query ); // in case someone mistakenly already singlequoted it.
		$query = str_replace( '"%s"', '%s', $query ); // doublequote unquoting.
		$query = preg_replace( '|(?<!%)%f|', '%F', $query ); // Force floats to be locale unaware.
		$query = preg_replace( '|(?<!%)%s|', "'%s'", $query ); // quote the strings, avoiding escaped strings like %%s.
		array_walk( $args, array( $this, 'escape_by_ref' ) );

		return @vsprintf( $query, $args );
	}

	/**
	 * Escapes a string by reference for safe usage in queries.
	 *
	 * @param string $string_value The string to be escaped.
	 *
	 * @return void
	 */
	public function escape_by_ref( &$string_value ) {
		if ( ! is_float( $string_value ) ) {
			$string_value = $this->_real_escape( $string_value );
		}
	}

	/**
	 * Escapes a string using the active database connection.
	 *
	 * @param string $string_value The string to be escaped.
	 *
	 * @return string The escaped string.
	 */
	private function _real_escape( $string_value ) {
		if ( $this->use_mysqli ) {
			return mysqli_real_escape_string( $this->dbh, $string_value );
		} else {
			return mysql_real_escape_string( $string_value, $this->dbh );
		}
	}

	/**
	 * Retrieves a single row from the query result set.
	 *
	 * @param string|null $query Optional. SQL query to execute. If null, retrieves from the last query result.
	 *
	 * @return object|null The first row from the result set or null if no rows exist.
	 */
	public function get_row( $query = null ) {
		$y = 0;

		if ( $query ) {
			$this->query( $query );
		} else {
			return null;
		}

		if ( ! isset( $this->last_result[ $y ] ) ) {
			return null;
		}

		return $this->last_result[ $y ] ? $this->last_result[ $y ] : null;
	}

	/**
	 * Executes a database query.
	 *
	 * @param string $query The SQL query to execute.
	 *
	 * @return int|false The number of rows affected or retrieved by the query, or false on error.
	 */
	public function query( $query ) {
		if ( ! $this->ready ) {
			return false;
		}

		$this->_do_query( $query );
		// If there is an error then take note of it.
		if ( $this->use_mysqli ) {
			if ( $this->dbh instanceof \mysqli ) {
				$this->last_error = mysqli_error( $this->dbh );
			} else {
				$this->last_error = 'query: Unable to retrieve the error message from MySQL';
			}
		} elseif ( is_resource( $this->dbh ) ) {
			$this->last_error = mysql_error( $this->dbh );
		} else {
			$this->last_error = 'query: Unable to retrieve the error message from MySQL';
		}

		if ( $this->last_error ) {
			if ( WP_DEBUG ) {
				echo esc_html( $this->last_error );
			}
			return false;
		}

		$num_rows          = 0;
		$this->last_result = array();
		if ( $this->use_mysqli && $this->result instanceof \mysqli_result ) {
			$row = mysqli_fetch_object( $this->result );
			while ( false !== $row && null !== $row ) {
				$this->last_result[ $num_rows ] = $row;
				++$num_rows;
				$row = mysqli_fetch_object( $this->result );
			}
		} elseif ( is_resource( $this->result ) ) {
			$row = mysql_fetch_object( $this->result );
			while ( false !== $row ) {
				$this->last_result[ $num_rows ] = $row;
				++$num_rows;
				$row = mysql_fetch_object( $this->result );
			}
		}

		// Log number of rows the query returned and return number of rows selected.
		$this->num_rows = $num_rows;
		$return_val     = $num_rows;

		return $return_val;
	}

	/**
	 * Executes the database query internally.
	 *
	 * @param string $query The SQL query to execute.
	 *
	 * @return void
	 */
	private function _do_query( $query ) {
		if ( ! empty( $this->dbh ) && $this->use_mysqli ) {
			$this->result = mysqli_query( $this->dbh, $query );
		} elseif ( ! empty( $this->dbh ) ) {
			$this->result = mysql_query( $query, $this->dbh );
		}
	}

	/**
	 * Closes the current database connection.
	 *
	 * @return bool True if the connection was successfully closed, false otherwise.
	 */
	public function close() {
		if ( ! $this->dbh ) {
			return false;
		}

		if ( $this->use_mysqli ) {
			$closed = mysqli_close( $this->dbh );
		} else {
			$closed = mysql_close( $this->dbh );
		}

		if ( $closed ) {
			$this->dbh           = null;
			$this->ready         = false;
			$this->has_connected = false;
		}

		return $closed;
	}
}
