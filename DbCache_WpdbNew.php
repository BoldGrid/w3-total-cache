<?php
/**
 * File: DbCache_WpdbInjection.php
 *
 * @package W3TC
 */

namespace W3TC;

/**
 * Class DbCache_WpdbNew
 *
 * Database access mediator, for WordPress >= 5.3
 *
 * phpcs:disable PSR2.Methods.MethodDeclaration.Underscore
 * phpcs:disable WordPress.PHP.NoSilencedErrors.Discouraged
 * phpcs:disable WordPress.WP.AlternativeFunctions
 */
class DbCache_WpdbNew extends DbCache_WpdbBase {
	/**
	 * Active processors
	 *
	 * @var int
	 */
	private $active_processor_number;

	/**
	 * Active processor
	 *
	 * @var object
	 */
	private $active_processor;

	/**
	 * Active processor
	 *
	 * @var array
	 */
	private $processors;

	/**
	 * Debug flag
	 *
	 * @var bool
	 */
	private $debug;

	/**
	 * Request start time
	 *
	 * @var int
	 */
	private $request_time_start = 0;

	/**
	 * Initializes the DbCache_WpdbNew object.
	 *
	 * @param array $processors List of processors for this object.
	 *
	 * @throws \Exception If processors is not an array.
	 *
	 * @return void
	 */
	public function __construct( $processors = array() ) {
		// required to initialize $use_mysqli which is private.
		parent::__construct( '', '', '', '' );

		// cant force empty parameter list due to wp requirements.
		if ( ! is_array( $processors ) ) {
			throw new \Exception( esc_html__( 'Called incorrectly, use instance().', 'w3-total-cache' ) );
		}

		$this->processors              = $processors;
		$this->active_processor        = $processors[0];
		$this->active_processor_number = 0;

		$c           = Dispatcher::config();
		$this->debug = $c->get_boolean( 'dbcache.debug' );

		if ( $this->debug ) {
			$this->_request_time_start = microtime( true );
		}
	}

	/**
	 * Handles actions when W3TC plugins are loaded.
	 *
	 * @return void
	 */
	public function on_w3tc_plugins_loaded() {
		$o = $this;

		if ( $this->debug ) {
			add_action( 'shutdown', array( $o, 'debug_shutdown' ) );
		}

		add_filter( 'w3tc_footer_comment', array( $o, 'w3tc_footer_comment' ) );
		add_action( 'w3tc_usage_statistics_of_request', array( $o, 'w3tc_usage_statistics_of_request' ), 10, 1 );
	}

	/**
	 * Adds footer comments for W3TC.
	 *
	 * @param  array $strings The footer strings to be modified.
	 * @return array Modified footer strings.
	 */
	public function w3tc_footer_comment( $strings ) {
		foreach ( $this->processors as $processor ) {
			$strings = $processor->w3tc_footer_comment( $strings );
		}

		return $strings;
	}

	/**
	 * Logs debug information at shutdown.
	 *
	 * @return void
	 */
	public function debug_shutdown() {
		$strings = array();
		foreach ( $this->processors as $processor ) {
			$strings = $processor->w3tc_footer_comment( $strings );
		}

		$request_time_total = microtime( true ) - $this->request_time_start;

		$data = sprintf(
			"\n[%s] [%s] [%s]\n",
			gmdate( 'r' ),
			isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '',
			round( $request_time_total, 4 )
		) . implode( "\n", $strings ) . "\n";
		$data = strtr( $data, '<>', '..' );

		$filename = Util_Debug::log_filename( 'dbcache' );
		@file_put_contents( $filename, $data, FILE_APPEND );
	}

	/**
	 * Records statistics of the current request.
	 *
	 * @param mixed $storage The storage for the statistics.
	 *
	 * @return void
	 */
	public function w3tc_usage_statistics_of_request( $storage ) {
		foreach ( $this->processors as $processor ) {
			$processor->w3tc_usage_statistics_of_request( $storage );
		}
	}

	/**
	 * Flushes the cache using all processors.
	 *
	 * @param array $extras Optional extra parameters to pass to processors.
	 *
	 * @return bool True if all caches were flushed successfully, false otherwise.
	 */
	public function flush_cache( $extras = array() ) {
		$v = true;

		foreach ( $this->processors as $processor ) {
			$v &= $processor->flush_cache( $extras );
		}

		return $v;
	}

	/**
	 * Connects to the database.
	 *
	 * @param bool $allow_bail Whether to allow failure of the connection.
	 *
	 * @return mixed The result of the database connection attempt.
	 */
	public function db_connect( $allow_bail = true ) {
		if ( empty( $this->dbuser ) ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedIf
			// skip connection - called from constructor.
		} else {
			return parent::db_connect( $allow_bail );
		}
	}

	/**
	 * Initializes the active processor.
	 *
	 * @return mixed The result of the initialization from the active processor.
	 */
	public function initialize() {
		return $this->active_processor->initialize();
	}

	/**
	 * Inserts data into a specified table.
	 *
	 * @param string     $table  The table name to insert data into.
	 * @param array      $data   The data to insert into the table.
	 * @param array|null $format Optional format for the data values.
	 *
	 * @return mixed The result of the insert operation.
	 */
	public function insert( $table, $data, $format = null ) {
		do_action( 'w3tc_db_insert', $table, $data, $format );
		return $this->active_processor->insert( $table, $data, $format );
	}

	/**
	 * Executes the database query.
	 *
	 * @param string $query The SQL query to execute.
	 *
	 * @return mixed The result of the query.
	 */
	public function query( $query ) {
		return $this->active_processor->query( $query );
	}

	/**
	 * Escapes a string for safe use in SQL queries.
	 *
	 * @param string $data The data to escape.
	 *
	 * @return string The escaped string.
	 */
	public function _escape( $data ) {
		return $this->active_processor->_escape( $data );
	}

	/**
	 * Prepares a SQL query with arguments for safe execution.
	 *
	 * @param string $query The SQL query template.
	 * @param mixed  ...$args The values to bind to the query.
	 *
	 * @return string The prepared SQL query.
	 */
	public function prepare( $query, ...$args ) {
		return $this->active_processor->prepare( $query, $args );
	}

	/**
	 * Replaces data in a specified table.
	 *
	 * @param string     $table  The table name to replace data in.
	 * @param array      $data   The data to replace.
	 * @param array|null $format Optional format for the data values.
	 *
	 * @return mixed The result of the replace operation.
	 */
	public function replace( $table, $data, $format = null ) {
		do_action( 'w3tc_db_replace', $table, $data, $format );
		return $this->active_processor->replace( $table, $data, $format );
	}

	/**
	 * Updates data in a specified table.
	 *
	 * @param string     $table        The table name to update.
	 * @param array      $data         The data to update.
	 * @param array      $where        The conditions for the update.
	 * @param array|null $format       Optional format for the data values.
	 * @param array|null $where_format Optional format for the where conditions.
	 *
	 * @return mixed The result of the update operation.
	 */
	public function update( $table, $data, $where, $format = null, $where_format = null ) {
		do_action( 'w3tc_db_update', $table, $data, $where, $format, $where_format );
		return $this->active_processor->update( $table, $data, $where, $format, $where_format );
	}

	/**
	 * Deletes data from a specified table.
	 *
	 * @param string     $table        The table name to delete data from.
	 * @param array      $where        The conditions for the deletion.
	 * @param array|null $where_format Optional format for the where conditions.
	 *
	 * @return mixed The result of the delete operation.
	 */
	public function delete( $table, $where, $where_format = null ) {
		do_action( 'w3tc_db_delete', $table, $where, $where_format );
		return $this->active_processor->delete( $table, $where, $where_format );
	}

	/**
	 * Initializes the character set for the database.
	 *
	 * @return mixed The result of the initialization from the active processor.
	 */
	public function init_charset() {
		return $this->active_processor->init_charset();
	}

	/**
	 * Sets the character set for the database connection.
	 *
	 * @param mixed       $dbh     The database handle.
	 * @param string|null $charset The character set to set.
	 * @param string|null $collate The collation to set.
	 *
	 * @return mixed The result of setting the charset.
	 */
	public function set_charset( $dbh, $charset = null, $collate = null ) {
		return $this->active_processor->set_charset( $dbh, $charset, $collate );
	}

	/**
	 * Sets the SQL mode for the database.
	 *
	 * @param array $modes The SQL modes to set.
	 *
	 * @return mixed The result of setting the SQL mode.
	 */
	public function set_sql_mode( $modes = array() ) {
		return $this->active_processor->set_sql_mode( $modes );
	}

	/**
	 * Flushes data from the active processor.
	 *
	 * @return mixed The result of the flush operation.
	 */
	public function flush() {
		return $this->active_processor->flush();
	}

	/**
	 * Checks the database version for compatibility.
	 *
	 * @param mixed $dbh_or_table Optional parameter for specifying the database handle or table.
	 *
	 * @return mixed The result of the database version check.
	 */
	public function check_database_version( $dbh_or_table = false ) {
		return $this->active_processor->check_database_version( $dbh_or_table );
	}

	/**
	 * Checks if the database supports collation.
	 *
	 * @param mixed $dbh_or_table Optional parameter for specifying the database handle or table.
	 *
	 * @return mixed The result of the collation support check.
	 */
	public function supports_collation( $dbh_or_table = false ) {
		return $this->active_processor->supports_collation( $dbh_or_table );
	}

	/**
	 * Checks if the database has a specific capability.
	 *
	 * @param string $db_cap The database capability to check.
	 * @param mixed  $dbh_or_table Optional parameter for specifying the database handle or table.
	 *
	 * @return mixed The result of the capability check.
	 */
	public function has_cap( $db_cap, $dbh_or_table = false ) {
		return $this->active_processor->has_cap( $db_cap, $dbh_or_table );
	}

	/**
	 * Retrieves the database version.
	 *
	 * @param mixed $dbh_or_table Optional parameter for specifying the database handle or table.
	 *
	 * @return mixed The database version.
	 */
	public function db_version( $dbh_or_table = false ) {
		return $this->active_processor->db_version( $dbh_or_table );
	}

	/**
	 * Initializes the default database connection.
	 *
	 * @return void
	 */
	public function default_initialize() {
		parent::__construct( DB_USER, DB_PASSWORD, DB_NAME, DB_HOST );
	}

	/**
	 * Inserts data into a table using the default database connection.
	 *
	 * @param string     $table  The table name to insert data into.
	 * @param array      $data   The data to insert into the table.
	 * @param array|null $format Optional format for the data values.
	 *
	 * @return mixed The result of the insert operation.
	 */
	public function default_insert( $table, $data, $format = null ) {
		return parent::insert( $table, $data, $format );
	}

	/**
	 * Executes a custom database query using the default connection.
	 *
	 * @param string $query The SQL query to execute.
	 *
	 * @return mixed The result of the query.
	 */
	public function default_query( $query ) {
		return parent::query( $query );
	}

	/**
	 * Escapes a string for safe use in SQL queries using the default connection.
	 *
	 * @param string $data The data to escape.
	 *
	 * @return string The escaped string.
	 */
	public function default__escape( $data ) {
		return parent::_escape( $data );
	}

	/**
	 * Prepares a SQL query with arguments using the default connection.
	 *
	 * @param string $query The SQL query template.
	 * @param mixed  $args  The values to bind to the query.
	 *
	 * @return string The prepared SQL query.
	 */
	public function default_prepare( $query, $args ) {
		return parent::prepare( $query, ...$args );
	}

	/**
	 * Replaces data in a table using the default connection.
	 *
	 * @param string     $table  The table name to replace data in.
	 * @param array      $data   The data to replace.
	 * @param array|null $format Optional format for the data values.
	 *
	 * @return mixed The result of the replace operation.
	 */
	public function default_replace( $table, $data, $format = null ) {
		return parent::replace( $table, $data, $format );
	}

	/**
	 * Updates data in a table using the default connection.
	 *
	 * @param string     $table        The table name to update.
	 * @param array      $data         The data to update.
	 * @param array      $where        The conditions for the update.
	 * @param array|null $format       Optional format for the data values.
	 * @param array|null $where_format Optional format for the where conditions.
	 *
	 * @return mixed The result of the update operation.
	 */
	public function default_update( $table, $data, $where, $format = null, $where_format = null ) {
		return parent::update( $table, $data, $where, $format, $where_format );
	}

	/**
	 * Deletes data from a table using the default connection.
	 *
	 * @param string     $table        The table name to delete data from.
	 * @param array      $where        The conditions for the deletion.
	 * @param array|null $where_format Optional format for the where conditions.
	 *
	 * @return mixed The result of the delete operation.
	 */
	public function default_delete( $table, $where, $where_format = null ) {
		return parent::delete( $table, $where, $where_format );
	}

	/**
	 * Initializes the charset using the default connection.
	 *
	 * @return mixed
	 */
	public function default_init_charset() {
		return parent::init_charset();
	}

	/**
	 * Sets the charset and collation for the default database connection.
	 *
	 * @param \wpdb       $dbh     The database connection object.
	 * @param string|null $charset The character set to set.
	 * @param string|null $collate The collation to set.
	 *
	 * @return mixed
	 */
	public function default_set_charset( $dbh, $charset = null, $collate = null ) {
		return parent::set_charset( $dbh, $charset, $collate );
	}

	/**
	 * Sets the SQL mode for the default database connection.
	 *
	 * @param array $modes Array of SQL modes to set.
	 *
	 * @return mixed
	 */
	public function default_set_sql_mode( $modes = array() ) {
		return parent::set_sql_mode( $modes );
	}

	/**
	 * Flushes any cached default database data.
	 *
	 * @return mixed
	 */
	public function default_flush() {
		return parent::flush();
	}

	/**
	 * Checks the database version of the given default connection or table.
	 *
	 * @param \wpdb|string|false $dbh_or_table Database connection or table name to check.
	 *
	 * @return string The database version.
	 */
	public function default_check_database_version( $dbh_or_table = false ) {
		return parent::check_database_version( $dbh_or_table );
	}

	/**
	 * Checks if the default database supports collation.
	 *
	 * @param \wpdb|string|false $dbh_or_table Database connection or table name to check.
	 *
	 * @return bool True if collation is supported, false otherwise.
	 */
	public function default_supports_collation( $dbh_or_table = false ) {
		return parent::supports_collation( $dbh_or_table );
	}

	/**
	 * Checks if the default database has the specified capability.
	 *
	 * @param string             $db_cap       The database capability to check.
	 * @param \wpdb|string|false $dbh_or_table Database connection or table name to check.
	 *
	 * @return bool True if the database supports the capability, false otherwise.
	 */
	public function default_has_cap( $db_cap, $dbh_or_table = false ) {
		return parent::has_cap( $db_cap, $dbh_or_table );
	}

	/**
	 * Retrieves the database version of the default connection or table.
	 *
	 * @param \wpdb|string|false $dbh_or_table Database connection or table name to retrieve the version for.
	 *
	 * @return string The database version.
	 */
	public function default_db_version( $dbh_or_table = false ) {
		return parent::db_version( $dbh_or_table );
	}

	/**
	 * Switches the active processor based on the provided offset.
	 *
	 * @param int $offset The offset by which to switch the active processor.
	 *
	 * @return int The change in processor number.
	 */
	public function switch_active_processor( $offset ) {
		$new_processor_number = $this->active_processor_number + $offset;
		if ( $new_processor_number <= 0 ) {
			$new_processor_number = 0;
		} elseif ( $new_processor_number >= count( $this->processors ) ) {
			$new_processor_number = count( $this->processors ) - 1;
		}

		$offset_made                   = $new_processor_number - $this->active_processor_number;
		$this->active_processor_number = $new_processor_number;
		$this->active_processor        = $this->processors[ $new_processor_number ];

		return $offset_made;
	}
}



/**
 * Class _CallUnderlying
 *
 * phpcs:disable PSR2.Methods.MethodDeclaration.Underscore
 * phpcs:disable Generic.Files.OneObjectStructurePerFile.MultipleFound
 * phpcs:disable PEAR.NamingConventions.ValidClassName.StartWithCapital
 */
class _CallUnderlying {
	/**
	 * WPDB mixin.
	 *
	 * @var object
	 */
	private $wpdb_mixin;

	/**
	 * Constructor for initializing the _CallUnderlying class.
	 *
	 * @param object $manager Database access mediator.
	 *
	 * @return void
	 */
	public function __construct( $manager ) {
		$this->wpdb_mixin = $manager;
	}

	/**
	 * Initializes the WPDB manager.
	 *
	 * @return mixed Result of the initialization process.
	 *
	 * @throws \Exception If initialization fails.
	 */
	public function initialize() {
		$switched = $this->wpdb_mixin->switch_active_processor( 1 );

		try {
			$r = $this->wpdb_mixin->initialize();

			$this->wpdb_mixin->switch_active_processor( -$switched );
			return $r;
		} catch ( \Exception $e ) {
			$this->wpdb_mixin->switch_active_processor( -$switched );
			throw $e;
		}
	}

	/**
	 * Flushes the WPDB manager's internal cache or other relevant resources.
	 *
	 * @return mixed Result of the flush operation.
	 *
	 * @throws \Exception If flushing fails.
	 */
	public function flush() {
		$switched = $this->wpdb_mixin->switch_active_processor( 1 );

		try {
			$r = $this->wpdb_mixin->flush();

			$this->wpdb_mixin->switch_active_processor( -$switched );
			return $r;
		} catch ( \Exception $e ) {
			$this->wpdb_mixin->switch_active_processor( -$switched );
			throw $e;
		}
	}

	/**
	 * Executes a database query.
	 *
	 * @param string $query SQL query to be executed.
	 *
	 * @return mixed Result of the query execution.
	 *
	 * @throws \Exception If the query fails.
	 */
	public function query( $query ) {
		$switched = $this->wpdb_mixin->switch_active_processor( 1 );

		try {
			$r = $this->wpdb_mixin->query( $query );

			$this->wpdb_mixin->switch_active_processor( -$switched );
			return $r;
		} catch ( \Exception $e ) {
			$this->wpdb_mixin->switch_active_processor( -$switched );
			throw $e;
		}
	}

	/**
	 * Escapes data for use in a database query.
	 *
	 * @param mixed $data Data to be escaped.
	 *
	 * @return mixed Escaped data.
	 *
	 * @throws \Exception If escaping fails.
	 */
	public function _escape( $data ) {
		$switched = $this->wpdb_mixin->switch_active_processor( 1 );

		try {
			$r = $this->wpdb_mixin->_escape( $data );

			$this->wpdb_mixin->switch_active_processor( -$switched );
			return $r;
		} catch ( \Exception $e ) {
			$this->wpdb_mixin->switch_active_processor( -$switched );
			throw $e;
		}
	}

	/**
	 * Prepares an SQL query for execution.
	 *
	 * @param string $query SQL query to be prepared.
	 * @param array  $args  Parameters to include in the query.
	 *
	 * @return string Prepared SQL query.
	 *
	 * @throws \Exception If query preparation fails.
	 */
	public function prepare( $query, $args ) {
		$switched = $this->wpdb_mixin->switch_active_processor( 1 );

		try {
			$r = $this->wpdb_mixin->prepare( $query, ...$args );

			$this->wpdb_mixin->switch_active_processor( -$switched );
			return $r;
		} catch ( \Exception $e ) {
			$this->wpdb_mixin->switch_active_processor( -$switched );
			throw $e;
		}
	}

	/**
	 * Inserts a row into a database table.
	 *
	 * @param string $table  Name of the table.
	 * @param array  $data   Associative array of data to insert.
	 * @param mixed  $format Optional. Data format.
	 *
	 * @return mixed Result of the insertion.
	 *
	 * @throws \Exception If insertion fails.
	 */
	public function insert( $table, $data, $format = null ) {
		$switched = $this->wpdb_mixin->switch_active_processor( 1 );

		try {
			$r = $this->wpdb_mixin->insert( $table, $data, $format );

			$this->wpdb_mixin->switch_active_processor( -$switched );
			return $r;
		} catch ( \Exception $e ) {
			$this->wpdb_mixin->switch_active_processor( -$switched );
			throw $e;
		}
	}

	/**
	 * Replaces a row in a database table.
	 *
	 * @param string $table  Name of the table.
	 * @param array  $data   Associative array of data to replace.
	 * @param mixed  $format Optional. Data format.
	 *
	 * @return mixed Result of the replacement.
	 *
	 * @throws \Exception If replacement fails.
	 */
	public function replace( $table, $data, $format = null ) {
		$switched = $this->wpdb_mixin->switch_active_processor( 1 );

		try {
			$r = $this->wpdb_mixin->replace( $table, $data, $format );

			$this->wpdb_mixin->switch_active_processor( -$switched );
			return $r;
		} catch ( \Exception $e ) {
			$this->wpdb_mixin->switch_active_processor( -$switched );
			throw $e;
		}
	}

	/**
	 * Updates rows in a database table.
	 *
	 * @param string $table        Name of the table.
	 * @param array  $data         Associative array of data to update.
	 * @param array  $where        Associative array of conditions for the update.
	 * @param mixed  $format       Optional. Data format.
	 * @param mixed  $where_format Optional. Format for where clause.
	 *
	 * @return mixed Result of the update.
	 *
	 * @throws \Exception If update fails.
	 */
	public function update( $table, $data, $where, $format = null, $where_format = null ) {
		$switched = $this->wpdb_mixin->switch_active_processor( 1 );

		try {
			$r = $this->wpdb_mixin->update( $table, $data, $where, $format, $where_format );

			$this->wpdb_mixin->switch_active_processor( -$switched );
			return $r;
		} catch ( \Exception $e ) {
			$this->wpdb_mixin->switch_active_processor( -$switched );
			throw $e;
		}
	}

	/**
	 * Deletes rows from a database table.
	 *
	 * @param string $table        Name of the table.
	 * @param array  $where        Associative array of conditions for deletion.
	 * @param mixed  $where_format Optional. Format for where clause.
	 *
	 * @return mixed Result of the deletion.
	 *
	 * @throws \Exception If deletion fails.
	 */
	public function delete( $table, $where, $where_format = null ) {
		$switched = $this->wpdb_mixin->switch_active_processor( 1 );

		try {
			$r = $this->wpdb_mixin->delete( $table, $where, $where_format );

			$this->wpdb_mixin->switch_active_processor( -$switched );
			return $r;
		} catch ( \Exception $e ) {
			$this->wpdb_mixin->switch_active_processor( -$switched );
			throw $e;
		}
	}
}
