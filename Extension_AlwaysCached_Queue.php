<?php
/**
 * File: Extension_AlwaysCached_Queue.php
 *
 * AlwaysCached queue controller.
 *
 * @since 2.5.1
 *
 * @package W3TC
 */

namespace W3TC;

if ( ! defined( 'W3TC_ALWAYSCACHED_TABLE_QUEUE' ) ) {
	define( 'W3TC_ALWAYSCACHED_TABLE_QUEUE', 'w3tc_alwayscached_queue' );
}

/**
 * AlwaysCached queue model.
 *
 * @since 2.5.1
 */
class Extension_AlwaysCached_Queue {

	/**
	 * Queue add.
	 *
	 * @since 2.5.1
	 *
	 * @param string $page_key           Page key.
	 * @param string $url                URL.
	 * @param string $page_key_extension Page key extension.
	 *
	 * @return void
	 */
	public static function add( $url, $extension, $priority = 100 ) {
		// Compress page_key_extension by removing empty values.
		$extension = array_filter( $extension );

		global $wpdb;

		$table = self::table_name();
		$key = self::key_by_url( $url );

		// page_key_extension has to be updated since
		// for :flush operation it contains timestamp to flush before
		// has to be refreshed if duplicate found

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				"
				INSERT INTO `$table`
				( `key`, url, extension, priority, to_process )
				VALUES
				( %s, %s, %s, %d, %s )
				ON DUPLICATE KEY UPDATE
					extension = %s,
					requests_count = requests_count + 1",
				$key,
				$url,
				// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize
				serialize( $extension ),
				$priority,
				gmdate( 'Y-m-d G:i:s' ),
				serialize( $extension ),
			)
		);
	}

	/**
	 * Get by url
	 *
	 * @since 2.5.1
	 *
	 * @param string $page_key Page key.
	 *
	 * @return array|object|null|void
	 */
	public static function get_by_url( $url ) {
		global $wpdb;

		$table = self::table_name();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return $wpdb->get_row(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				"SELECT * FROM `$table` WHERE `key` = %s",
				self::key_by_url( $url )
			),
			ARRAY_A
		);
	}

	/**
	 * Retreives the first 10 items in queue.
	 *
	 * @since 2.5.1
	 *
	 * @return array|null
	 */
	public static function pop_item_begin() {
		global $wpdb;

		$table = self::table_name();

		// Concurrency-safe extraction.
		for ( $n = 0; $n < 10; $n++ ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$item = $wpdb->get_row(
				$wpdb->prepare(
					// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					"
					SELECT *
					FROM `$table`
					WHERE to_process <= %s
					ORDER BY priority, to_process
					LIMIT 1",
					gmdate( 'Y-m-d G:i:s' )
				),
				ARRAY_A
			);

			if ( empty( $item ) ) {
				return null;
			}

			$new_to_process = gmdate( 'Y-m-d G:i:s', time() + 300 );

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$count = $wpdb->query(
				$wpdb->prepare(
					// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					"
					UPDATE `$table`
					SET to_process = %s
					WHERE `key` = %s AND to_process = %s",
					$new_to_process,
					$item['key'],
					$item['to_process']
				)
			);

			if ( 1 === $count ) {
				$item['to_process'] = $new_to_process;
				return $item;
			}
		}

		return null;
	}

	/**
	 * Deletes queue item after pop.
	 *
	 * @since 2.5.1
	 *
	 * @param array $item Queue item.
	 *
	 * @return void
	 */
	public static function pop_item_finish( $item ) {
		global $wpdb;

		$table = self::table_name();

		// Make sure we delete only when not changed since.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				"
				DELETE FROM `$table`
				WHERE
					`key` = %s AND
					to_process = %s AND
					requests_count = %d",
				$item['key'],
				$item['to_process'],
				$item['requests_count']
			)
		);
	}

	/**
	 * Retrives queue rows.
	 *
	 * @since 2.5.1
	 *
	 * @param string $mode Queue mode.
	 *
	 * @return array|object|null
	 */
	public static function rows( $mode ) {
		global $wpdb;

		$table = self::table_name();
		$comp  = 'postponed' === $mode ? '>' : '<=';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return $wpdb->get_results(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				"
				SELECT *
				FROM `$table`
				WHERE to_process $comp %s
				ORDER BY priority, to_process
				LIMIT 50",
				gmdate( 'Y-m-d G:i:s' )
			),
			ARRAY_A
		);
	}

	/**
	 * Retrives queue pending row count.
	 *
	 * @since 2.5.1
	 *
	 * @return string|null
	 */
	public static function row_count_pending() {
		global $wpdb;

		$table = self::table_name();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return $wpdb->get_var(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				"SELECT COUNT(*) FROM `$table` WHERE to_process < %s",
				gmdate( 'Y-m-d G:i:s' )
			)
		);
	}

	/**
	 * Retrives queue postponed row count.
	 *
	 * @since 2.5.1
	 *
	 * @return string|null
	 */
	public static function row_count_postponed() {
		global $wpdb;

		$table = self::table_name();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return $wpdb->get_var(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				"SELECT COUNT(*) FROM `$table` WHERE to_process >= %s",
				gmdate( 'Y-m-d G:i:s' )
			)
		);
	}

	/**
	 * Deletes all queue rows.
	 *
	 * @since 2.5.1
	 *
	 * @return int
	 */
	public static function empty() {
		global $wpdb;

		$table = self::table_name();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return $wpdb->query( "DELETE FROM `$table`" );
	}

	/**
	 * Checks if higher priority items present
	 *
	 * @since 2.5.1
	 *
	 * @return string
	 */
	public static function exists_higher_priority( $item ) {
		global $wpdb;

		$table = self::table_name();

		$higher_item = $wpdb->get_row(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				"
				SELECT *
				FROM `$table`
				WHERE to_process <= %s AND priority < %d
				ORDER BY priority, to_process
				LIMIT 1",
				gmdate( 'Y-m-d G:i:s' ),
				$item['priority']
			),
			ARRAY_A
		);

		return !empty( $higher_item );
	}

	private static function key_by_url( $url ) {
		return strlen( $url ) > 50 ? md5( $url ) : $url;
	}

	/**
	 * Gets AlwaysCached queue table name.
	 *
	 * @since 2.5.1
	 *
	 * @return string
	 */
	private static function table_name() {
		global $wpdb;

		return $wpdb->base_prefix . W3TC_ALWAYSCACHED_TABLE_QUEUE;
	}

	/**
	 * Drops the AwaysCached queue table.
	 *
	 * @since 2.5.1
	 *
	 * @return void
	 *
	 *  @throws Util_Environment_Exception Exception.
	 */
	public static function drop_table() {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
		$wpdb->query( self::drop_table_sql() );

		if ( ! $wpdb->result ) {
			throw new Util_Environment_Exception( esc_html__( 'Can\'t drop table ', 'w3-total-cache' ) . $table );
		}
	}

	/**
	 * Creates AlwaysCached queue table.
	 *
	 * @since 2.5.1
	 *
	 * @return void
	 *
	 * @throws Util_Environment_Exception Exception.
	 */
	public static function create_table() {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
		$wpdb->query( self::create_table_sql() );

		if ( ! $wpdb->result ) {
			$table = self::table_name();

			throw new Util_Environment_Exception( esc_html__( 'Can\'t create table ', 'w3-total-cache' ) . $table );
		}
	}

	/**
	 * Retrives AlwaysCached queue table drop SQL.
	 *
	 * @since 2.5.1
	 *
	 * @return string
	 */
	public static function drop_table_sql() {
		global $wpdb;

		$table = self::table_name();

		$sql = "DROP TABLE IF EXISTS `$table`";

		return $sql;
	}

	/**
	 * Retrives AlwaysCached queue table create SQL.
	 *
	 * @since 2.5.1
	 *
	 * @return string
	 */
	public static function create_table_sql() {
		global $wpdb;

		$table = self::table_name();

		$charset_collate = '';

		if ( ! empty( $wpdb->charset ) ) {
			$charset_collate .= "DEFAULT CHARACTER SET $wpdb->charset";
		}

		if ( ! empty( $wpdb->collate ) ) {
			$charset_collate .= " COLLATE $wpdb->collate";
		}

		// priority - smaller number is higher priority
		$sql = "CREATE TABLE IF NOT EXISTS `$table` (
			`key` varchar(50) CHARACTER SET `ascii` NOT NULL,
			`url` varchar(500) NOT NULL,
			`extension` varchar(500) NOT NULL,
			`priority` tinyint NOT NULL DEFAULT 100,
			`requests_count` int NOT NULL DEFAULT 1,
			`to_process` datetime NOT NULL,
			PRIMARY KEY (`key`),
			INDEX `to_process` (`to_process`)
			) $charset_collate";

		return $sql;
	}
}
