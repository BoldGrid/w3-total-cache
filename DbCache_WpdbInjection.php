<?php
/**
 * File: DbCache_WpdbInjection.php
 *
 * @package W3TC
 */

namespace W3TC;

/**
 * Class DbCache_WpdbInjection
 *
 * Allows to perform own operation instead of default behaviour of wpdb
 * without inheritance
 *
 * phpcs:disable PSR2.Methods.MethodDeclaration.Underscore
 * phpcs:disable Generic.CodeAnalysis.UnusedFunctionParameter
 */
class DbCache_WpdbInjection {
	/**
	 * Top database-connection object.
	 * Initialized by DbCache_Wpdb::instance
	 *
	 * @var object
	 */
	protected $wpdb_mixin = null;

	/**
	 * Database-connection using overrides of next processor in queue
	 * Initialized by DbCache_Wpdb::instance
	 *
	 * @var object
	 */
	protected $next_injection = null;

	/**
	 * Initializes the injection with the provided database mixin and next injection.
	 *
	 * @param object $wpdb_mixin     The database mixin to be used.
	 * @param mixed  $next_injection The next injection to be processed.
	 *
	 * @return void
	 */
	public function initialize_injection( $wpdb_mixin, $next_injection ) {
		$this->wpdb_mixin     = $wpdb_mixin;
		$this->next_injection = $next_injection;
	}

	/**
	 * Initializes the database connection.
	 *
	 * @return mixed The result of the default initialization.
	 */
	public function initialize() {
		return $this->wpdb_mixin->default_initialize();
	}

	/**
	 * Inserts data into the specified table.
	 *
	 * @param string $table  The table name.
	 * @param array  $data   The data to insert.
	 * @param mixed  $format Optional. Format of the data.
	 *
	 * @return mixed The result of the default insert operation.
	 */
	public function insert( $table, $data, $format = null ) {
		return $this->wpdb_mixin->default_insert( $table, $data, $format );
	}

	/**
	 * Executes a query on the database.
	 *
	 * @param string $query The SQL query to execute.
	 *
	 * @return mixed The result of the default query execution.
	 */
	public function query( $query ) {
		return $this->wpdb_mixin->default_query( $query );
	}

	/**
	 * Escapes the given data to be safely used in a query.
	 *
	 * @param mixed $data The data to escape.
	 *
	 * @return mixed The escaped data.
	 */
	public function _escape( $data ) {
		return $this->wpdb_mixin->default__escape( $data );
	}

	/**
	 * Prepares a SQL query with the provided arguments.
	 *
	 * @param string $query The SQL query.
	 * @param array  $args  The arguments to bind to the query.
	 *
	 * @return mixed The prepared query.
	 */
	public function prepare( $query, $args ) {
		return $this->wpdb_mixin->default_prepare( $query, $args );
	}

	/**
	 * Replaces data in the specified table.
	 *
	 * @param string $table  The table name.
	 * @param array  $data   The data to replace.
	 * @param mixed  $format Optional. Format of the data.
	 *
	 * @return mixed The result of the default replace operation.
	 */
	public function replace( $table, $data, $format = null ) {
		return $this->wpdb_mixin->default_replace( $table, $data, $format );
	}

	/**
	 * Updates data in the specified table.
	 *
	 * @param string $table       The table name.
	 * @param array  $data        The data to update.
	 * @param array  $where       The conditions for the update.
	 * @param mixed  $format      Optional. Format of the data.
	 * @param mixed  $where_format Optional. Format of the where conditions.
	 *
	 * @return mixed The result of the default update operation.
	 */
	public function update( $table, $data, $where, $format = null, $where_format = null ) {
		return $this->wpdb_mixin->default_update( $table, $data, $where, $format, $where_format );
	}

	/**
	 * Deletes data from the specified table.
	 *
	 * @param string $table        The table name.
	 * @param array  $where        The conditions for the delete.
	 * @param mixed  $where_format Optional. Format of the where conditions.
	 *
	 * @return mixed The result of the default delete operation.
	 */
	public function delete( $table, $where, $where_format = null ) {
		return $this->wpdb_mixin->default_delete( $table, $where, $where_format );
	}

	/**
	 * Initializes the character set for the database connection.
	 *
	 * @return mixed The result of the default charset initialization.
	 */
	public function init_charset() {
		return $this->wpdb_mixin->default_init_charset();
	}

	/**
	 * Sets the character set and collation for the database connection.
	 *
	 * @param object $dbh     The database handler.
	 * @param string $charset Optional. The character set to set.
	 * @param string $collate Optional. The collation to set.
	 *
	 * @return mixed The result of the default set charset operation.
	 */
	public function set_charset( $dbh, $charset = null, $collate = null ) {
		return $this->wpdb_mixin->default_set_charset( $dbh, $charset, $collate );
	}

	/**
	 * Sets the SQL mode for the database.
	 *
	 * @param array $modes Optional. An array of modes to set.
	 *
	 * @return mixed The result of the default set SQL mode operation.
	 */
	public function set_sql_mode( $modes = array() ) {
		return $this->wpdb_mixin->default_set_sql_mode( $modes );
	}

	/**
	 * Flushes the database cache.
	 *
	 * @return mixed The result of the default flush operation.
	 */
	public function flush() {
		return $this->wpdb_mixin->default_flush();
	}

	/**
	 * Checks the database version.
	 *
	 * @param mixed $dbh_or_table Optional. The database handler or table to check.
	 *
	 * @return mixed The database version.
	 */
	public function check_database_version( $dbh_or_table = false ) {
		return $this->wpdb_mixin->default_check_database_version( $dbh_or_table );
	}

	/**
	 * Checks if the database supports collation.
	 *
	 * @param mixed $dbh_or_table Optional. The database handler or table to check.
	 *
	 * @return bool True if collation is supported, false otherwise.
	 */
	public function supports_collation( $dbh_or_table = false ) {
		return $this->wpdb_mixin->default_supports_collation( $dbh_or_table );
	}

	/**
	 * Checks if the database has a specific capability.
	 *
	 * @param string $db_cap        The capability to check.
	 * @param mixed  $dbh_or_table  Optional. The database handler or table to check.
	 *
	 * @return bool True if the capability is supported, false otherwise.
	 */
	public function has_cap( $db_cap, $dbh_or_table = false ) {
		return $this->wpdb_mixin->default_has_cap( $db_cap, $dbh_or_table );
	}

	/**
	 * Retrieves the database version.
	 *
	 * @param mixed $dbh_or_table Optional. The database handler or table to check.
	 *
	 * @return mixed The database version.
	 */
	public function db_version( $dbh_or_table = false ) {
		return $this->wpdb_mixin->default_db_version( $dbh_or_table );
	}

	/**
	 * Adds a footer comment to the W3TC output.
	 *
	 * @param string $strings The current footer comment.
	 *
	 * @return string The modified footer comment.
	 */
	public function w3tc_footer_comment( $strings ) {
		return $strings;
	}

	/**
	 * Logs the usage statistics of the request.
	 *
	 * @param mixed $storage The storage mechanism for the usage statistics.
	 *
	 * @return void
	 */
	public function w3tc_usage_statistics_of_request( $storage ) {
	}

	/**
	 * Flushes the cache with additional parameters.
	 *
	 * @param array $extras Optional. Additional parameters to include in the flush operation.
	 *
	 * @return bool Always returns true.
	 */
	public function flush_cache( $extras = array() ) {
		return true;
	}
}
