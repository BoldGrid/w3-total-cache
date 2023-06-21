<?php
namespace W3TC;

if ( !defined( 'W3TC_ALWAYSCACHED_TABLE_QUEUE' ) ) {
	define( 'W3TC_ALWAYSCACHED_TABLE_QUEUE', 'w3tc_alwayscached_queue' );
}



/**
 * queue model for always-cached module
 */
class Extension_AlwaysCached_Queue {
	static public function add( $page_key, $url, $page_key_extension ) {
		// compress page_key_extension by removing empty values
		$page_key_extension = array_filter( $page_key_extension );

		global $wpdb;
		$table = Extension_AlwaysCached_Queue::table_name();

		$wpdb->query( $wpdb->prepare( "
			INSERT INTO `$table`
			( page_key, url, page_key_extension, to_process )
			VALUES
			( %s, %s, %s, %s )
			ON DUPLICATE KEY UPDATE requests_count = requests_count + 1",
			$page_key, $url, serialize( $page_key_extension ),
			gmdate( 'Y-m-d G:i:s' ) ) );
	}



	static public function get_by_page_key( $page_key ) {
		global $wpdb;
		$table = Extension_AlwaysCached_Queue::table_name();

		return $wpdb->get_row( $wpdb->prepare( "
			SELECT id
			FROM `$table`
			WHERE page_key = %s",
			$page_key ), ARRAY_A );
	}



	static public function pop_item_begin() {
		global $wpdb;
		$table = Extension_AlwaysCached_Queue::table_name();

		// concurrency-safe extraction
		for ($n = 0; $n < 10; $n++) {
			$item = $wpdb->get_row( $wpdb->prepare( "
				SELECT *
				FROM `$table`
				WHERE to_process < %s
				ORDER BY to_process
				LIMIT 1",
				gmdate( 'Y-m-d G:i:s' ) ), ARRAY_A );
			if ( empty( $item ) ) {
				return null;
			}

			$new_to_process = gmdate( 'Y-m-d G:i:s', time() + 300 );
			$count = $wpdb->query( $wpdb->prepare( "
				UPDATE `$table`
				SET to_process = %s
				WHERE id = %d AND to_process = %s",
				$new_to_process, $item['id'],
				$item['to_process'] ) );
			if ($count == 1) {
				$item['to_process'] = $new_to_process;
				return $item;
			}
		}

		return null;
	}



	static public function pop_item_finish($item) {
		global $wpdb;
		$table = Extension_AlwaysCached_Queue::table_name();

		// make sure we delete only when not changed since
		$wpdb->query( $wpdb->prepare( "
			DELETE FROM `$table`
			WHERE id = %d AND to_process = %s AND requests_count = %d",
			$item['id'], $item['to_process'], $item['requests_count'] ) );
	}



	static private function table_name() {
		global $wpdb;
		return $wpdb->base_prefix . W3TC_ALWAYSCACHED_TABLE_QUEUE;
	}



	static public function drop_table() {
		global $wpdb;
		$table = Extension_AlwaysCached_Queue::table_name();

		$wpdb->query( "DROP TABLE IF EXISTS `$table`" );
		if ( !$wpdb->result ) {
			throw new Util_Environment_Exception(  "Can't drop table $table" );
		}
	}




	static public function create_table() {
		global $wpdb;
		$wpdb->query( Extension_AlwaysCached_Queue::create_table_sql() );
		if ( !$wpdb->result ) {
			$table = Extension_AlwaysCached_Queue::table_name();

			throw new Util_Environment_Exception(
				"Can't create table $table" );
		}
	}



	static public function create_table_sql() {
		global $wpdb;
		$table = Extension_AlwaysCached_Queue::table_name();

		$charset_collate = '';
		if ( ! empty( $wpdb->charset ) )
			$charset_collate = "DEFAULT CHARACTER SET $wpdb->charset";
		if ( ! empty( $wpdb->collate ) )
			$charset_collate .= " COLLATE $wpdb->collate";

		$sql = "CREATE TABLE IF NOT EXISTS `$table` (
			`id` int(11) unsigned NOT NULL AUTO_INCREMENT,
			`page_key` varchar(500) NOT NULL,
			`url` varchar(500) NOT NULL,
			`page_key_extension` varchar(500) NOT NULL,
			`requests_count` int NOT NULL DEFAULT 1,
			`to_process` datetime NOT NULL,
			PRIMARY KEY (`id`),
			UNIQUE KEY `page_key` (`page_key`),
			INDEX `to_process` (`to_process`)
		) $charset_collate";

		return $sql;
	}
}
