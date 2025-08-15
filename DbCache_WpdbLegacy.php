<?php
/**
 * File: DbCache_WpdbInjection.php
 *
 * @package W3TC
 */

namespace W3TC;

/**
 * Class DbCache_WpdbLegacy
 *
 * Database access mediator, for WordPress < 5.3
 *
 * phpcs:disable PSR2.Methods.MethodDeclaration.Underscore
 * phpcs:disable WordPress.PHP.NoSilencedErrors.Discouraged
 * phpcs:disable WordPress.WP.AlternativeFunctions
 */
class DbCache_WpdbLegacy extends DbCache_WpdbBase {
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
	 * Constructor for initializing the DbCache_WpdbLegacy class.
	 *
	 * @param array $processors Array of processors to initialize.
	 *
	 * @throws \Exception If the processors parameter is invalid.
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
	 * Callback for the 'w3tc_plugins_loaded' action.
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
	 * Modifies the footer comment for W3TC.
	 *
	 * @param  array $strings The footer comment strings.
	 * @return array Modified footer comment strings.
	 */
	public function w3tc_footer_comment( $strings ) {
		foreach ( $this->processors as $processor ) {
			$strings = $processor->w3tc_footer_comment( $strings );
		}

		return $strings;
	}

	/**
	 * Debug shutdown function for logging request time and footer comments.
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
	 * Collects usage statistics for the request.
	 *
	 * @param mixed $storage Storage for the usage statistics.
	 *
	 * @return void
	 */
	public function w3tc_usage_statistics_of_request( $storage ) {
		foreach ( $this->processors as $processor ) {
			$processor->w3tc_usage_statistics_of_request( $storage );
		}
	}

	/**
	 * Flushes the cache for all processors.
	 *
	 * @param array $extras Optional extra parameters for flushing the cache.
	 *
	 * @return bool True if cache was successfully flushed, false otherwise.
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
	 * @param bool $allow_bail Whether to allow the connection to fail silently.
	 *
	 * @return mixed Database connection or null if the connection is skipped.
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
	 * @return mixed
	 */
	public function initialize() {
		return $this->active_processor->initialize();
	}

	/**
	 * Inserts data into a database table.
	 *
	 * @param string     $table  The table to insert data into.
	 * @param array      $data   The data to insert.
	 * @param array|null $format Optional format for the data.
	 *
	 * @return mixed The result of the insert operation.
	 */
	public function insert( $table, $data, $format = null ) {
		do_action( 'w3tc_db_insert', $table, $data, $format );
		return $this->active_processor->insert( $table, $data, $format );
	}

	/**
	 * Executes a query on the database.
	 *
	 * @param string $query The query to execute.
	 *
	 * @return mixed The result of the query execution.
	 */
	public function query( $query ) {
		return $this->active_processor->query( $query );
	}

	/**
	 * Escapes data for safe database use.
	 *
	 * @param mixed $data The data to escape.
	 *
	 * @return mixed The escaped data.
	 */
	public function _escape( $data ) {
		return $this->active_processor->_escape( $data );
	}

	/**
	 * Prepares a query for safe execution with arguments.
	 *
	 * @param string $query The query to prepare.
	 * @param array  $args  The arguments for the query.
	 *
	 * @return string The prepared query.
	 */
	public function prepare( $query, $args ) {
		$args = func_get_args();
		array_shift( $args );

		// If args were passed as an array (as in vsprintf), move them up.
		if ( isset( $args[0] ) && is_array( $args[0] ) ) {
			$args = $args[0];
		}

		return $this->active_processor->prepare( $query, $args );
	}

	/**
	 * Replaces data in a database table.
	 *
	 * @param string     $table  The table to replace data in.
	 * @param array      $data   The data to replace.
	 * @param array|null $format Optional format for the data.
	 *
	 * @return mixed The result of the replace operation.
	 */
	public function replace( $table, $data, $format = null ) {
		do_action( 'w3tc_db_replace', $table, $data, $format );
		return $this->active_processor->replace( $table, $data, $format );
	}

	/**
	 * Updates data in a database table.
	 *
	 * @param string     $table        The table to update.
	 * @param array      $data         The data to update.
	 * @param array      $where        The condition for the update.
	 * @param array|null $format       Optional format for the data.
	 * @param array|null $where_format Optional format for the where condition.
	 *
	 * @return mixed The result of the update operation.
	 */
	public function update( $table, $data, $where, $format = null, $where_format = null ) {
		do_action( 'w3tc_db_update', $table, $data, $where, $format, $where_format );
		return $this->active_processor->update( $table, $data, $where, $format, $where_format );
	}

	/**
	 * Deletes data from a database table.
	 *
	 * @param string     $table        The table to delete data from.
	 * @param array      $where        The condition for the delete.
	 * @param array|null $where_format Optional format for the where condition.
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
	 * @return mixed
	 */
	public function init_charset() {
		return $this->active_processor->init_charset();
	}

	/**
	 * Sets the character set for the database connection.
	 *
	 * @param mixed       $dbh     The database connection.
	 * @param string|null $charset Optional character set to set.
	 * @param string|null $collate Optional collation to set.
	 *
	 * @return bool True if the charset was successfully set, false otherwise.
	 */
	public function set_charset( $dbh, $charset = null, $collate = null ) {
		return $this->active_processor->set_charset( $dbh, $charset, $collate );
	}


	/**
	 * Sets the SQL mode for the database connection.
	 *
	 * @param array $modes SQL modes to set.
	 *
	 * @return bool True if the SQL mode was successfully set, false otherwise.
	 */
	public function set_sql_mode( $modes = array() ) {
		return $this->active_processor->set_sql_mode( $modes );
	}

	/**
	 * Flushes the database connection.
	 *
	 * @return bool True if the flush operation was successful, false otherwise.
	 */
	public function flush() {
		return $this->active_processor->flush();
	}

	/**
	 * Checks the version of the database.
	 *
	 * @param mixed $dbh_or_table Optional database handler or table to check.
	 *
	 * @return mixed The database version or false if not available.
	 */
	public function check_database_version( $dbh_or_table = false ) {
		return $this->active_processor->check_database_version( $dbh_or_table );
	}

	/**
	 * Checks if the database supports collation.
	 *
	 * @param mixed $dbh_or_table Optional database handler or table to check.
	 *
	 * @return bool True if collation is supported, false otherwise.
	 */
	public function supports_collation( $dbh_or_table = false ) {
		return $this->active_processor->supports_collation( $dbh_or_table );
	}

	/**
	 * Checks if the database has the specified capability.
	 *
	 * @param string $db_cap     The database capability to check.
	 * @param mixed  $dbh_or_table Optional database handler or table to check.
	 *
	 * @return bool True if the capability is supported, false otherwise.
	 */
	public function has_cap( $db_cap, $dbh_or_table = false ) {
		return $this->active_processor->has_cap( $db_cap, $dbh_or_table );
	}

	/**
	 * Retrieves the version of the database.
	 *
	 * @param mixed $dbh_or_table Optional database handler or table to check.
	 *
	 * @return mixed The database version or false if not available.
	 */
	public function db_version( $dbh_or_table = false ) {
		return $this->active_processor->db_version( $dbh_or_table );
	}

	/**
	 * Default constructor for database connection.
	 *
	 * @return void
	 */
	public function default_initialize() {
		parent::__construct( DB_USER, DB_PASSWORD, DB_NAME, DB_HOST );
	}

	/**
	 * Default insert method for database operations.
	 *
	 * @param string     $table  The table to insert data into.
	 * @param array      $data   The data to insert.
	 * @param array|null $format Optional format for the data.
	 *
	 * @return mixed The result of the insert operation.
	 */
	public function default_insert( $table, $data, $format = null ) {
		return parent::insert( $table, $data, $format );
	}

	/**
	 * Default query method for database operations.
	 *
	 * @param string $query The query to execute.
	 *
	 * @return mixed The result of the query execution.
	 */
	public function default_query( $query ) {
		return parent::query( $query );
	}

	/**
	 * Default escape method for database operations.
	 *
	 * @param mixed $data The data to escape.
	 *
	 * @return mixed The escaped data.
	 */
	public function default__escape( $data ) {
		return parent::_escape( $data );
	}

	/**
	 * Default prepare method for database operations.
	 *
	 * @param string $query The query to prepare.
	 * @param array  $args  The arguments for the query.
	 *
	 * @return string The prepared query.
	 */
	public function default_prepare( $query, $args ) {
		return parent::prepare( $query, $args );
	}

	/**
	 * Default replace method for database operations.
	 *
	 * @param string     $table  The table to replace data in.
	 * @param array      $data   The data to replace.
	 * @param array|null $format Optional format for the data.
	 *
	 * @return mixed The result of the replace operation.
	 */
	public function default_replace( $table, $data, $format = null ) {
		return parent::replace( $table, $data, $format );
	}

	/**
	 * Default update method for database operations.
	 *
	 * @param string     $table        The table to update.
	 * @param array      $data         The data to update.
	 * @param array      $where        The condition for the update.
	 * @param array|null $format       Optional format for the data.
	 * @param array|null $where_format Optional format for the where condition.
	 *
	 * @return mixed The result of the update operation.
	 */
	public function default_update( $table, $data, $where, $format = null, $where_format = null ) {
		return parent::update( $table, $data, $where, $format, $where_format );
	}

	/**
	 * Default delete method for database operations.
	 *
	 * @param string     $table        The table to delete data from.
	 * @param array      $where        The condition for the delete.
	 * @param array|null $where_format Optional format for the where condition.
	 *
	 * @return mixed The result of the delete operation.
	 */
	public function default_delete( $table, $where, $where_format = null ) {
		return parent::delete( $table, $where, $where_format );
	}

	/**
	 * Default initialization method for the character set.
	 *
	 * @return mixed
	 */
	public function default_init_charset() {
		return parent::init_charset();
	}

	/**
	 * Default method to set the character set for the database connection.
	 *
	 * @param mixed       $dbh     The database connection.
	 * @param string|null $charset Optional character set to set.
	 * @param string|null $collate Optional collation to set.
	 *
	 * @return bool True if the charset was successfully set, false otherwise.
	 */
	public function default_set_charset( $dbh, $charset = null, $collate = null ) {
		return parent::set_charset( $dbh, $charset, $collate );
	}

	/**
	 * Default method to set the SQL mode for the database connection.
	 *
	 * @param array $modes SQL modes to set.
	 *
	 * @return bool True if the SQL mode was successfully set, false otherwise.
	 */
	public function default_set_sql_mode( $modes = array() ) {
		return parent::set_sql_mode( $modes );
	}

	/**
	 * Default method to flush the database connection.
	 *
	 * @return bool True if the flush operation was successful, false otherwise.
	 */
	public function default_flush() {
		return parent::flush();
	}

	/**
	 * Default method to check the database version.
	 *
	 * @param mixed $dbh_or_table Optional database handler or table to check.
	 *
	 * @return mixed The database version or false if not available.
	 */
	public function default_check_database_version( $dbh_or_table = false ) {
		return parent::check_database_version( $dbh_or_table );
	}

	/**
	 * Default method to check if the database supports collation.
	 *
	 * @param mixed $dbh_or_table Optional database handler or table to check.
	 *
	 * @return mixed True if the database supports collation, false if it does not.
	 */
	public function default_supports_collation( $dbh_or_table = false ) {
		return parent::supports_collation( $dbh_or_table );
	}

	/**
	 * Default method to check if the database has a specified capability.
	 *
	 * @param string $db_cap       The database capability to check.
	 * @param mixed  $dbh_or_table Optional database handler or table to check.
	 *
	 * @return bool True if the capability is supported, false otherwise.
	 */
	public function default_has_cap( $db_cap, $dbh_or_table = false ) {
		return parent::has_cap( $db_cap, $dbh_or_table );
	}

	/**
	 * Default method to retrieve the version of the database.
	 *
	 * @param mixed $dbh_or_table Optional database handler or table to check.
	 *
	 * @return mixed The database version or false if not available.
	 */
	public function default_db_version( $dbh_or_table = false ) {
		return parent::db_version( $dbh_or_table );
	}

	/**
	 * Switches the active processor by a specified offset.
	 *
	 * @param int $offset The offset by which to switch the active processor.
	 *
	 * @return int The offset made to change the active processor.
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
	 * Initializes the database connection and prepares the system for queries.
	 *
	 * @return mixed The result of the initialization process.
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
	 * Flushes the database cache and operations.
	 *
	 * @return mixed The result of the flush operation.
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
	 * @param string $query The SQL query to execute.
	 *
	 * @return mixed The result of the query execution.
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
	 * Escapes data to prevent SQL injection.
	 *
	 * @param mixed $data The data to escape.
	 *
	 * @return mixed The escaped data.
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
	 * Prepares a SQL query with arguments.
	 *
	 * @param string $query The SQL query to prepare.
	 * @param array  $args  The arguments to bind to the query.
	 *
	 * @return mixed The prepared query.
	 *
	 * @throws \Exception If preparing the query fails.
	 */
	public function prepare( $query, $args ) {
		$switched = $this->wpdb_mixin->switch_active_processor( 1 );

		try {
			$r = $this->wpdb_mixin->prepare( $query, $args );

			$this->wpdb_mixin->switch_active_processor( -$switched );
			return $r;
		} catch ( \Exception $e ) {
			$this->wpdb_mixin->switch_active_processor( -$switched );
			throw $e;
		}
	}

	/**
	 * Inserts data into a database table.
	 *
	 * @param string $table  The name of the table to insert data into.
	 * @param array  $data   The data to insert.
	 * @param array  $format Optional format for the data.
	 *
	 * @return mixed The result of the insert operation.
	 *
	 * @throws \Exception If the insert operation fails.
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
	 * Replaces existing data in a database table.
	 *
	 * @param string $table  The name of the table.
	 * @param array  $data   The data to replace.
	 * @param array  $format Optional format for the data.
	 *
	 * @return mixed The result of the replace operation.
	 *
	 * @throws \Exception If the replace operation fails.
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
	 * Updates existing data in a database table.
	 *
	 * @param string $table        The name of the table.
	 * @param array  $data         The data to update.
	 * @param array  $where        The conditions to match the data.
	 * @param array  $format       Optional format for the data.
	 * @param array  $where_format Optional format for the where conditions.
	 *
	 * @return mixed The result of the update operation.
	 *
	 * @throws \Exception If the update operation fails.
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
	 * Deletes data from a database table.
	 *
	 * @param string $table        The name of the table.
	 * @param array  $where        The conditions to match the data to delete.
	 * @param array  $where_format Optional format for the where conditions.
	 *
	 * @return mixed The result of the delete operation.
	 *
	 * @throws \Exception If the delete operation fails.
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
