<?php
/**
 * File: UsageStatistics_StorageReader.php
 *
 * phpcs:disable Generic.Files.OneObjectStructurePerFile.MultipleFound
 * phpcs:disable PEAR.NamingConventions.ValidClassName.StartWithCapital
 *
 * @package W3TC
 */

namespace W3TC;

/**
 * Class UsageStatistics_StorageWriter
 *
 * Manages data statistics.
 */
class UsageStatistics_StorageWriter {
	/**
	 * The interval in seconds for each slot in the statistics collection.
	 *
	 * @var int
	 */
	private $slot_interval_seconds;

	/**
	 * The total number of slots to maintain for the usage statistics.
	 *
	 * @var int
	 */
	private $slots_count;

	/**
	 * The interval in seconds to keep historical data.
	 *
	 * @var int
	 */
	private $keep_history_interval_seconds;

	/**
	 * The cache storage for usage statistics.
	 *
	 * @var CacheStorage|null
	 */
	private $cache_storage;

	/**
	 * The end time for the current hotspot period.
	 *
	 * @var int|null
	 */
	private $hotspot_endtime;

	/**
	 * The end time for the new hotspot period.
	 *
	 * @var float
	 */
	private $new_hotspot_endtime = 0;

	/**
	 * The current timestamp at the time of the operation.
	 *
	 * @var int
	 */
	private $now;

	/**
	 * The state of the flushing process.
	 *
	 * @var string
	 */
	private $flush_state;

	/**
	 * Constructor for initializing the UsageStatistics_StorageWriter.
	 *
	 * Initializes the cache storage and configuration options based on the provided settings.
	 *
	 * @return void
	 */
	public function __construct() {
		$this->cache_storage = Dispatcher::get_usage_statistics_cache();

		$c                           = Dispatcher::config();
		$this->slot_interval_seconds = $c->get_integer( 'stats.slot_seconds' );

		$this->keep_history_interval_seconds = $c->get_integer( 'stats.slots_count' ) * $this->slot_interval_seconds;
		$this->slots_count                   = $c->get_integer( 'stats.slots_count' );
	}

	/**
	 * Resets the usage statistics, clearing the cache and site options.
	 *
	 * Resets the history and start time for the hotspot.
	 *
	 * @return void
	 */
	public function reset() {
		if ( ! is_null( $this->cache_storage ) ) {
			$this->cache_storage->set( 'hotspot_endtime', array( 'content' => 0 ) );
		}

		update_site_option( 'w3tc_stats_hotspot_start', time() );
		update_site_option( 'w3tc_stats_history', '' );
	}

	/**
	 * Adds a specified value to a counter metric in the cache.
	 *
	 * This method will increment a metric counter by the given value, if the cache storage is not null.
	 *
	 * @param string $metric The metric name to increment.
	 * @param int    $value  The value to add to the metric counter.
	 *
	 * @return void
	 */
	public function counter_add( $metric, $value ) {
		if ( ! is_null( $this->cache_storage ) ) {
			$this->cache_storage->counter_add( $metric, $value );
		}
	}

	/**
	 * Retrieves the end time for the current hotspot.
	 *
	 * If the hotspot end time is not cached, it will fetch it from the cache and store it.
	 *
	 * @return int The hotspot end time.
	 */
	public function get_hotspot_end() {
		if ( is_null( $this->hotspot_endtime ) ) {
			$v                     = $this->cache_storage->get( 'hotspot_endtime' );
			$this->hotspot_endtime = ( isset( $v['content'] ) ? $v['content'] : 0 );
		}

		return $this->hotspot_endtime;
	}

	/**
	 * Returns an appropriate option storage handler based on whether the environment is multisite or not.
	 *
	 * @return _OptionStorageWpmu|_OptionStorageSingleSite The option storage handler.
	 */
	private function get_option_storage() {
		if ( is_multisite() ) {
			return new _OptionStorageWpmu();
		} else {
			return new _OptionStorageSingleSite();
		}
	}

	/**
	 * May trigger the flushing of the hotspot data if needed.
	 *
	 * Determines if the data should be flushed and initiates the process.
	 *
	 * @return void
	 */
	public function maybe_flush_hotspot_data() {
		$result = $this->begin_flush_hotspot_data();
		if ( 'not_needed' === $result ) {
			return;
		}

		$this->finish_flush_hotspot_data();
	}

	/**
	 * Begins the process of flushing the hotspot data.
	 *
	 * Checks the current hotspot end time and prepares to flush data based on various conditions.
	 *
	 * @return string The state of the flush process.
	 */
	public function begin_flush_hotspot_data() {
		$hotspot_endtime = $this->get_hotspot_end();
		if ( is_null( $hotspot_endtime ) ) {
			// if cache not recognized - means nothing is cached at all so stats not collected.
			return 'not_needed';
		}

		$hotspot_endtime_int = (int) $hotspot_endtime;
		$this->now           = time();

		if ( $hotspot_endtime_int <= 0 ) {
			$this->flush_state = 'require_db';
		} elseif ( $this->now < $hotspot_endtime_int ) {
			$this->flush_state = 'not_needed';
		} else {
			// rand value makes value unique for each process, so as a result next replace works as a lock
			// passing only single process further.
			$this->new_hotspot_endtime = $this->now + $this->slot_interval_seconds + ( wp_rand( 1, 9999 ) / 10000.0 );

			$succeeded         = $this->cache_storage->set_if_maybe_equals(
				'hotspot_endtime',
				array( 'content' => $hotspot_endtime ),
				array( 'content' => $this->new_hotspot_endtime )
			);
			$this->flush_state = ( $succeeded ? 'flushing_began_by_cache' : 'not_needed' );
		}

		return $this->flush_state;
	}


	/**
	 * Completes the flushing of the hotspot data.
	 *
	 * This method will attempt to flush the collected metrics data to the storage and update the history.
	 *
	 * @return void
	 *
	 * @throws Exception If the flushing state is unknown.
	 */
	public function finish_flush_hotspot_data() {
		$option_storage = $this->get_option_storage();

		if ( 'not_needed' === $this->flush_state ) {
			return;
		}

		if ( 'require_db' !== $this->flush_state && 'flushing_began_by_cache' !== $this->flush_state ) {
			throw new Exception(
				esc_html(
					sprintf(
						// Translators: 1 Flush state.
						__( 'Unknown usage stats state %1$s.', 'w3-total-cache' ),
						$this->flush_state
					)
				)
			);
		}

		// check whats there in db.
		$this->hotspot_endtime = $option_storage->get_hotspot_end();
		$hotspot_endtime_int   = (int) $this->hotspot_endtime;

		if ( $this->now < $hotspot_endtime_int ) {
			// update cache, since there is something old/missing in cache.
			$this->cache_storage->set( 'hotspot_endtime', array( 'content' => $this->hotspot_endtime ) );
			return; // not neeeded really, db state after.
		}
		if ( $this->new_hotspot_endtime <= 0 ) {
			$this->new_hotspot_endtime = $this->now + $this->slot_interval_seconds + ( wp_rand( 1, 9999 ) / 10000.0 );
		}

		if ( $hotspot_endtime_int <= 0 ) {
			// no data in options, initialization.
			$this->cache_storage->set( 'hotspot_endtime', array( 'content' => $this->new_hotspot_endtime ) );
			update_site_option( 'w3tc_stats_hotspot_start', time() );
			$option_storage->set_hotspot_end( $this->new_hotspot_endtime );
			return;
		}

		// try to become the process who makes flushing by performing atomic database update.
		// rand value makes value unique for each process, so as a result next replace works as a lock
		// passing only single process further.
		$succeeded = $option_storage->prolong_hotspot_end( $this->hotspot_endtime, $this->new_hotspot_endtime );
		if ( ! $succeeded ) {
			return;
		}

		$this->cache_storage->set( 'hotspot_endtime', array( 'content' => $this->new_hotspot_endtime ) );

		// flush data.
		$metrics = array();
		$metrics = apply_filters( 'w3tc_usage_statistics_metrics', $metrics );

		$metric_values                    = array();
		$metric_values['timestamp_start'] = get_site_option( 'w3tc_stats_hotspot_start' );
		$metric_values['timestamp_end']   = $hotspot_endtime_int;

		// try to limit time between get and reset of counter value to loose as small as posssible.
		foreach ( $metrics as $metric ) {
			$metric_values[ $metric ] = $this->cache_storage->counter_get( $metric );
			$this->cache_storage->counter_set( $metric, 0 );
		}

		$metric_values = apply_filters( 'w3tc_usage_statistics_metric_values', $metric_values );

		$history_encoded = get_site_option( 'w3tc_stats_history' );
		$history         = null;
		if ( ! empty( $history_encoded ) ) {
			$history = json_decode( $history_encoded, true );
		}

		if ( ! is_array( $history ) ) {
			$history = array();
		}

		$time_keep_border = time() - $this->keep_history_interval_seconds;

		if ( $hotspot_endtime_int < $time_keep_border ) {
			$history = array(
				array(
					'timestamp_start' => $time_keep_border,
					'timestamp_end'   => (int) $this->new_hotspot_endtime - $this->slot_interval_seconds - 1,
				),
			); // this was started too much time from now.
		} else {
			// add collected.
			$history[] = $metric_values;

			// if we empty place later - fill it.
			for ( ;; ) {
				$metric_values                  = array(
					'timestamp_start' => $metric_values['timestamp_end'],
				);
				$metric_values['timestamp_end'] = $metric_values['timestamp_start'] + $this->slot_interval_seconds;
				if ( $metric_values['timestamp_end'] < $this->now ) {
					$history[] = $metric_values;
				} else {
					break;
				}
			}

			// make sure we have at least one value in history.
			$history_count = count( $history );
			while ( $history_count > $this->slots_count ) {
				if ( ! isset( $history[0]['timestamp_end'] ) || $history[0]['timestamp_end'] < $time_keep_border ) {
					array_shift( $history );
				} else {
					break;
				}

				// Update the history count after modification.
				$history_count = count( $history );
			}
		}

		$history = apply_filters( 'w3tc_usage_statistics_history_set', $history );

		update_site_option( 'w3tc_stats_hotspot_start', $this->now );
		update_site_option( 'w3tc_stats_history', wp_json_encode( $history ) );
	}
}

/**
 * Class _OptionStorageSingleSite
 *
 * Can update option by directly incrementing current value, not via get+set operation
 *
 * phpcs:disable WordPress.DB.DirectDatabaseQuery
 * phpcs:disable WordPress.DB.PreparedSQL.NotPrepared
 */
class _OptionStorageSingleSite {
	/**
	 * The option name for storing the hotspot end value.
	 *
	 * @var string
	 */
	private $option_hotspot_end = 'w3tc_stats_hotspot_end';

	/**
	 * Retrieves the current value of the hotspot end option from the database.
	 *
	 * This method queries the WordPress options table for the value associated with the
	 * `option_hotspot_end` key and returns it if found. If the option does not exist,
	 * it returns false.
	 *
	 * @return mixed The value of the hotspot end option if found, otherwise false.
	 */
	public function get_hotspot_end() {
		global $wpdb;

		$row = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT option_value FROM ' . $wpdb->options . ' WHERE option_name = %s LIMIT 1',
				$this->option_hotspot_end
			)
		);

		if ( ! is_object( $row ) ) {
			return false;
		}

		$v = $row->option_value;

		return $v;
	}

	/**
	 * Updates the value of the hotspot end option in the database.
	 *
	 * This method updates the WordPress site option associated with the
	 * `option_hotspot_end` key with the provided new value.
	 *
	 * @param mixed $new_value The new value to set for the hotspot end option.
	 *
	 * @return void
	 */
	public function set_hotspot_end( $new_value ) {
		update_site_option( $this->option_hotspot_end, $new_value );
	}

	/**
	 * Prolongs the hotspot end value by updating it in the database if the old value matches.
	 *
	 * This method updates the WordPress site option associated with the `option_hotspot_end` key,
	 * changing its value from the old value to the new value. If the old value matches the current
	 * value stored in the options table, the update is performed.
	 *
	 * @param mixed $old_value The current value to be replaced.
	 * @param mixed $new_value The new value to set for the hotspot end option.
	 *
	 * @return bool True if the update succeeded, false otherwise.
	 */
	public function prolong_hotspot_end( $old_value, $new_value ) {
		global $wpdb;

		$q = $wpdb->prepare(
			'UPDATE ' . $wpdb->options . ' SET option_value = %s WHERE option_name = %s AND option_value = %s',
			$new_value,
			$this->option_hotspot_end,
			$old_value
		);

		$result    = $wpdb->query( $q );
		$succeeded = ( $result > 0 );

		return $succeeded;
	}
}

/**
 * Class _OptionStorageWpmu
 *
 * Can update option by directly incrementing current value, not via get+set operation
 *
 * phpcs:disable WordPress.DB.DirectDatabaseQuery
 */
class _OptionStorageWpmu {
	/**
	 * The option key for the hotspot end setting.
	 *
	 * @var string
	 */
	private $option_hotspot_end = 'w3tc_stats_hotspot_end';

	/**
	 * Retrieves the value of the hotspot end option.
	 *
	 * This method queries the `sitemeta` table in the WordPress database to fetch
	 * the value associated with the `w3tc_stats_hotspot_end` meta key for the current site.
	 * It returns the stored value if available, or false if not.
	 *
	 * @return mixed The hotspot end value if it exists, or false if not found.
	 */
	public function get_hotspot_end() {
		global $wpdb;

		$row = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT meta_value FROM ' . $wpdb->sitemeta . ' WHERE site_id = %d AND meta_key = %s',
				$wpdb->siteid,
				$this->option_hotspot_end
			)
		);

		if ( ! is_object( $row ) ) {
			return false;
		}

		$v = $row->meta_value;

		return $v;
	}

	/**
	 * Sets the value of the hotspot end option.
	 *
	 * This method updates the `w3tc_stats_hotspot_end` option with a new value
	 * in the WordPress site options. It ensures that the option is updated with the
	 * provided value for the current site.
	 *
	 * @param mixed $new_value The new value to set for the hotspot end option.
	 *
	 * @return void
	 */
	public function set_hotspot_end( $new_value ) {
		update_site_option( $this->option_hotspot_end, $new_value );
	}

	/**
	 * Prolongs the hotspot end option by updating its value.
	 *
	 * This method updates the `w3tc_stats_hotspot_end` option in the `sitemeta` table,
	 * changing the old value to the new value for the current site. It checks for the
	 * specific old value and performs an update if it matches. The method returns true
	 * if the update was successful and false otherwise.
	 *
	 * @param mixed $old_value The old value to match in the database.
	 * @param mixed $new_value The new value to set for the hotspot end option.
	 *
	 * @return bool True if the update was successful, false otherwise.
	 */
	public function prolong_hotspot_end( $old_value, $new_value ) {
		global $wpdb;

		$result    = $wpdb->query(
			$wpdb->prepare(
				'UPDATE ' . $wpdb->sitemeta . ' SET meta_value = %s WHERE site_id = %d AND meta_key = %s AND meta_value = %s',
				$new_value,
				$wpdb->siteid,
				$this->option_hotspot_end,
				$old_value
			)
		);
		$succeeded = ( $result > 0 );

		return $succeeded;
	}
}
