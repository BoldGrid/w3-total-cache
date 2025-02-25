<?php
namespace W3TC;

class ObjectCache_Plugin_Admin {
	function run() {
		add_filter( 'w3tc_save_options', array( $this, 'w3tc_save_options' ) );

		$config_labels = new ObjectCache_ConfigLabels();
		add_filter( 'w3tc_config_labels', array( $config_labels, 'config_labels' ) );

		$c = Dispatcher::config();
		if ( $c->getf_boolean( 'objectcache.enabled' ) ) {
			add_filter( 'w3tc_errors', array( $this, 'w3tc_errors' ) );
			add_filter( 'w3tc_notes', array( $this, 'w3tc_notes' ) );
			add_filter( 'w3tc_usage_statistics_summary_from_history', array(
					$this, 'w3tc_usage_statistics_summary_from_history' ), 10, 2 );
		}

		add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_scripts' ) );
		add_action( 'w3tc_ajax_objectcache_diskpopup', array( $this, 'w3tc_ajax_objectcache_diskpopup' ) );
	}

	public function w3tc_save_options( $data ) {
		$new_config = $data['new_config'];
		$old_config = $data['old_config'];

		// Schedule purge if enabled.
		if ( $new_config->get_boolean( 'objectcache.enabled' ) && $new_config->get_boolean( 'objectcache.wp_cron' ) ) {
			$new_wp_cron_time      = $new_config->get_integer( 'objectcache.wp_cron_time' );
			$old_wp_cron_time      = $old_config ? $old_config->get_integer( 'objectcache.wp_cron_time' ) : -1;
			$new_wp_cron_interval  = $new_config->get_string( 'objectcache.wp_cron_interval' );
			$old_wp_cron_interval  = $old_config ? $old_config->get_string( 'objectcache.wp_cron_interval' ) : -1;
			$schedule_needs_update = $new_wp_cron_time !== $old_wp_cron_time || $new_wp_cron_interval !== $old_wp_cron_interval;

			// Clear the scheduled hook if a change in time or interval is detected.
			if ( wp_next_scheduled( 'w3tc_objectcache_purge_wpcron' ) && $schedule_needs_update ) {
				wp_clear_scheduled_hook( 'w3tc_objectcache_purge_wpcron' );
			}

			// Schedule if no existing cron event or settings have changed.
			if ( ! wp_next_scheduled( 'w3tc_objectcache_purge_wpcron' ) || $schedule_needs_update ) {
				$scheduled_timestamp_server = Util_Environment::get_cron_schedule_time( $new_wp_cron_time );
				wp_schedule_event( $scheduled_timestamp_server, $new_wp_cron_interval, 'w3tc_objectcache_purge_wpcron' );
			}
		} elseif ( wp_next_scheduled( 'w3tc_objectcache_purge_wpcron' ) ) {
			wp_clear_scheduled_hook( 'w3tc_objectcache_purge_wpcron' );
		}

		return $data;
	}

	public function w3tc_errors( $errors ) {
		$c = Dispatcher::config();

		if ( $c->get_string( 'objectcache.engine' ) == 'memcached' ) {
			$memcached_servers = $c->get_array(
				'objectcache.memcached.servers' );
			$memcached_binary_protocol = $c->get_boolean(
				'objectcache.memcached.binary_protocol' );
			$memcached_username = $c->get_string(
				'objectcache.memcached.username' );
			$memcached_password = $c->get_string(
				'objectcache.memcached.password' );

			if ( !Util_Installed::is_memcache_available( $memcached_servers, $memcached_binary_protocol, $memcached_username, $memcached_password ) ) {
				if ( !isset( $errors['memcache_not_responding.details'] ) )
					$errors['memcache_not_responding.details'] = array();

				$errors['memcache_not_responding.details'][] = sprintf(
					__( 'Object Cache: %s.', 'w3-total-cache' ),
					implode( ', ', $memcached_servers ) );
			}
		}

		return $errors;
	}



	public function w3tc_notes( $notes ) {
		$c = Dispatcher::config();
		$state = Dispatcher::config_state();
		$state_note = Dispatcher::config_state_note();

		/**
		 * Show notification when object cache needs to be emptied
		 */
		if ( $state_note->get( 'objectcache.show_note.flush_needed' ) &&
			!is_network_admin() /* flushed dont work under network admin */ &&
			!$c->is_preview() ) {
			$notes['objectcache_flush_needed'] = sprintf(
				__( 'The setting change(s) made either invalidate the cached data or modify the behavior of the site. %s now to provide a consistent user experience.',
					'w3-total-cache' ),
				Util_Ui::button_link(
					__( 'Empty the object cache', 'w3-total-cache' ),
					Util_Ui::url( array( 'w3tc_flush_objectcache' => 'y' ) ) ) );
		}

		return $notes;
	}



	public function w3tc_usage_statistics_summary_from_history( $summary, $history ) {
		// counters
		$get_total = Util_UsageStatistics::sum( $history, 'objectcache_get_total' );
		$get_hits = Util_UsageStatistics::sum( $history, 'objectcache_get_hits' );
		$sets = Util_UsageStatistics::sum( $history, 'objectcache_sets' );

		$c = Dispatcher::config();
		$e = $c->get_string( 'objectcache.engine' );

		$summary['objectcache'] = array(
			'get_total' => Util_UsageStatistics::integer( $get_total ),
			'get_hits' => Util_UsageStatistics::integer( $get_hits ),
			'sets' => Util_UsageStatistics::integer( $sets ),
			'flushes' => Util_UsageStatistics::integer(
				Util_UsageStatistics::sum( $history, 'objectcache_flushes' ) ),
			'time_ms' => Util_UsageStatistics::integer(
				Util_UsageStatistics::sum( $history, 'objectcache_time_ms' ) ),
			'calls_per_second' => Util_UsageStatistics::value_per_period_seconds(
				$get_total + $sets, $summary ),
			'hit_rate' => Util_UsageStatistics::percent(
				$get_hits, $get_total ),
			'engine_name' => Cache::engine_name( $e )
		);

		return $summary;
	}

	/**
	 * Enqueue disk usage risk acceptance script.
	 *
	 * @since 2.8.6
	 *
	 * @return void
	 */
	public function admin_enqueue_scripts() {
		$page_val = Util_Request::get_string( 'page' );
		if ( 'w3tc_general' === $page_val ) {
			wp_enqueue_script(
				'w3tc-objectcache-diskpopup',
				plugins_url( 'ObjectCache_DiskPopup.js', W3TC_FILE ),
				array(),
				W3TC_VERSION,
				false
			);
		}
	}

	/**
	 * Popup modal for Object Cache disk usage risk acceptance.
	 *
	 * @since 2.8.6
	 *
	 * @return void
	 */
	public function w3tc_ajax_objectcache_diskpopup() {
		include W3TC_DIR . '/ObjectCache_DiskPopup_View.php';
	}
}
