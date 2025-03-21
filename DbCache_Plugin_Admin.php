<?php
/**
 * File: DbCache_Plugin_Admin.php
 *
 * @package W3TC
 */

namespace W3TC;

/**
 * Class DbCache_Plugin_Admin
 */
class DbCache_Plugin_Admin {
	/**
	 * Initializes the DbCache plugin admin functionality.
	 *
	 * Sets up filters and configurations for database caching and usage statistics.
	 *
	 * @return void
	 */
	public function run() {
		add_filter( 'w3tc_save_options', array( $this, 'w3tc_save_options' ) );

		$config_labels = new DbCache_ConfigLabels();
		add_filter( 'w3tc_config_labels', array( $config_labels, 'config_labels' ) );

		$c = Dispatcher::config();
		if ( $c->get_boolean( 'dbcache.enabled' ) ) {
			add_filter( 'w3tc_usage_statistics_summary_from_history', array( $this, 'w3tc_usage_statistics_summary_from_history' ), 10, 2 );
			add_filter( 'w3tc_errors', array( $this, 'w3tc_errors' ) );
		}
	}

	/**
	 * Summarizes usage statistics from historical data.
	 *
	 * @param array $summary Current usage summary array.
	 * @param array $history Historical data array for generating statistics.
	 *
	 * @return array Updated usage summary with database cache statistics.
	 */
	public function w3tc_usage_statistics_summary_from_history( $summary, $history ) {
		// counters.
		$dbcache_calls_total = Util_UsageStatistics::sum( $history, 'dbcache_calls_total' );
		$dbcache_calls_hits  = Util_UsageStatistics::sum( $history, 'dbcache_calls_hits' );
		$dbcache_flushes     = Util_UsageStatistics::sum( $history, 'dbcache_flushes' );
		$dbcache_time_ms     = Util_UsageStatistics::sum( $history, 'dbcache_time_ms' );

		$c = Dispatcher::config();
		$e = $c->get_string( 'dbcache.engine' );

		$summary['dbcache'] = array(
			'calls_total'      => Util_UsageStatistics::integer( $dbcache_calls_total ),
			'calls_per_second' => Util_UsageStatistics::value_per_period_seconds( $dbcache_calls_total, $summary ),
			'flushes'          => Util_UsageStatistics::integer( $dbcache_flushes ),
			'time_ms'          => Util_UsageStatistics::integer( $dbcache_time_ms ),
			'hit_rate'         => Util_UsageStatistics::percent( $dbcache_calls_hits, $dbcache_calls_total ),
			'engine_name'      => Cache::engine_name( $e ),
		);

		return $summary;
	}

	/**
	 * Checks for errors related to the database caching system.
	 *
	 * @param array $errors Array of existing errors.
	 *
	 * @return array Updated array of errors including database cache issues, if any.
	 */
	public function w3tc_errors( $errors ) {
		$c = Dispatcher::config();

		if ( 'memcached' === $c->get_string( 'dbcache.engine' ) ) {
			$memcached_servers         = $c->get_array( 'dbcache.memcached.servers' );
			$memcached_binary_protocol = $c->get_boolean( 'dbcache.memcached.binary_protocol' );
			$memcached_username        = $c->get_string( 'dbcache.memcached.username' );
			$memcached_password        = $c->get_string( 'dbcache.memcached.password' );

			if (
				! Util_Installed::is_memcache_available(
					$memcached_servers,
					$memcached_binary_protocol,
					$memcached_username,
					$memcached_password
				)
			) {
				if ( ! isset( $errors['memcache_not_responding.details'] ) ) {
					$errors['memcache_not_responding.details'] = array();
				}

				$errors['memcache_not_responding.details'][] = sprintf(
					// Translators: 1 memcached servers.
					__(
						'Database Cache: %1$s.',
						'w3-total-cache'
					),
					implode( ', ', $memcached_servers )
				);
			}
		}

		return $errors;
	}

	/**
	 * Handles the saving of plugin options.
	 *
	 * Ensures proper scheduling of database cache purge cron jobs based on configuration changes.
	 *
	 * @param array $data Contains new and old configuration data.
	 *
	 * @return array Modified configuration data after applying necessary updates.
	 */
	public function w3tc_save_options( $data ) {
		$new_config = $data['new_config'];
		$old_config = $data['old_config'];

		// Schedule purge if enabled.
		if ( $new_config->get_boolean( 'dbcache.enabled' ) && $new_config->get_boolean( 'dbcache.wp_cron' ) ) {
			$new_wp_cron_time      = $new_config->get_integer( 'dbcache.wp_cron_time' );
			$old_wp_cron_time      = $old_config ? $old_config->get_integer( 'dbcache.wp_cron_time' ) : -1;
			$new_wp_cron_interval  = $new_config->get_string( 'dbcache.wp_cron_interval' );
			$old_wp_cron_interval  = $old_config ? $old_config->get_string( 'dbcache.wp_cron_interval' ) : -1;
			$schedule_needs_update = $new_wp_cron_time !== $old_wp_cron_time || $new_wp_cron_interval !== $old_wp_cron_interval;

			// Clear the scheduled hook if a change in time or interval is detected.
			if ( wp_next_scheduled( 'w3tc_dbcache_purge_wpcron' ) && $schedule_needs_update ) {
				wp_clear_scheduled_hook( 'w3tc_dbcache_purge_wpcron' );
			}

			// Schedule if no existing cron event or settings have changed.
			if ( ! wp_next_scheduled( 'w3tc_dbcache_purge_wpcron' ) || $schedule_needs_update ) {
				$scheduled_timestamp_server = Util_Environment::get_cron_schedule_time( $new_wp_cron_time );
				wp_schedule_event( $scheduled_timestamp_server, $new_wp_cron_interval, 'w3tc_dbcache_purge_wpcron' );
			}
		} elseif ( wp_next_scheduled( 'w3tc_dbcache_purge_wpcron' ) ) {
			wp_clear_scheduled_hook( 'w3tc_dbcache_purge_wpcron' );
		}

		return $data;
	}
}
