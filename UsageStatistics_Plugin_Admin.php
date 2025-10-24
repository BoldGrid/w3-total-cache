<?php
/**
 * File: UsageStatistics_Plugin_Admin.php
 *
 * @package W3TC
 */

namespace W3TC;

/**
 * Class UsageStatistics_Plugin_Admin
 */
class UsageStatistics_Plugin_Admin {
	/**
	 * Initializes and registers various hooks for the plugin.
	 *
	 * This method adds several actions and filters to hook into WordPress' event-driven model.
	 * It enables AJAX endpoints, adds menu items, and sets up configuration handling,
	 * all related to the usage statistics functionality.
	 *
	 * @return void
	 */
	public function run() {
		$c = Dispatcher::config();

		add_action( 'wp_ajax_ustats_access_log_test', array( $this, 'w3tc_ajax_ustats_access_log_test' ) );
		add_filter( 'w3tc_admin_menu', array( $this, 'w3tc_admin_menu' ) );
		add_action( 'w3tc_ajax_ustats_get', array( $this, 'w3tc_ajax_ustats_get' ) );
		add_filter( 'w3tc_usage_statistics_summary_from_history', array( 'W3TC\UsageStatistics_Sources', 'w3tc_usage_statistics_summary_from_history' ), 5, 2 );

		add_action( 'admin_init_w3tc_general', array( '\W3TC\UsageStatistics_GeneralPage', 'admin_init_w3tc_general' ) );

		add_action( 'w3tc_config_ui_save', array( $this, 'w3tc_config_ui_save' ), 10, 2 );

		add_filter( 'w3tc_notes', array( $this, 'w3tc_notes' ) );
	}

	/**
	 * Handles saving of the configuration when UI settings are changed.
	 *
	 * If the 'slot_seconds' value has changed in the configuration, this method
	 * ensures that all existing statistics are flushed to maintain consistency.
	 *
	 * @param object $config     The current configuration object.
	 * @param object $old_config The previous configuration object.
	 *
	 * @return void
	 */
	public function w3tc_config_ui_save( $config, $old_config ) {
		if ( $config->get( 'stats.slot_seconds' ) !== $old_config->get( 'stats.slot_seconds' ) ) {
			// flush all stats otherwise will be inconsistent.
			$storage = new UsageStatistics_StorageWriter();
			$storage->reset();
		}
	}

	/**
	 * Adds custom notes to the admin dashboard related to statistics collection.
	 *
	 * This method checks if statistics collection is enabled, and if so, it adds a
	 * note to the WordPress dashboard to inform the user about the resource usage
	 * and provide options to disable or hide the note.
	 *
	 * @param array $notes The current array of notes.
	 *
	 * @return array The modified array of notes with the statistics-related note added.
	 */
	public function w3tc_notes( $notes ) {
		$c            = Dispatcher::config();
		$state_master = Dispatcher::config_state_master();

		if ( $c->get_boolean( 'stats.enabled' ) && ! $state_master->get_boolean( 'common.hide_note_stats_enabled' ) ) {
			$notes['stats_enabled'] = sprintf(
				// Translators: 1 disable statistics button, 2 hide notes stats button.
				__(
					'W3 Total Cache: Statistics collection is currently enabled. This consumes additional resources, and is not recommended to be run continuously. %1$s %2$s',
					'w3-total-cache'
				),
				Util_Ui::button_link(
					__( 'Disable statistics', 'w3-total-cache' ),
					Util_Ui::url( array( 'w3tc_ustats_note_disable' => 'y' ) ),
					false,
					'button',
					'w3tc_note_stats_disable'
				),
				Util_Ui::button_hide_note2(
					array(
						'w3tc_default_config_state_master' => 'y',
						'key'                              => 'common.hide_note_stats_enabled',
						'value'                            => 'true',
					)
				)
			);
		}

		return $notes;
	}

	/**
	 * Adds a custom menu item to the WordPress admin menu.
	 *
	 * This method adds a new 'Statistics' page to the admin menu. The page is
	 * visible only when specifically enabled, and it is positioned with the appropriate
	 * order in the menu hierarchy.
	 *
	 * @param array $menu The existing admin menu array.
	 *
	 * @return array The modified admin menu array with the new 'Statistics' item.
	 */
	public function w3tc_admin_menu( $menu ) {
		$menu['w3tc_stats'] = array(
			'page_title'     => __( 'Statistics', 'w3-total-cache' ),
			'menu_text'      => __( 'Statistics', 'w3-total-cache' ),
			'visible_always' => false,
			'order'          => 2250,
		);

		return $menu;
	}

	/**
	 * Handles the AJAX request to retrieve usage statistics summary.
	 *
	 * This method is called when an AJAX request for statistics summary is made.
	 * It fetches the summary data from storage and returns it as a JSON response.
	 * The method includes a debug mode where the JSON is pretty-printed.
	 *
	 * @return void
	 */
	public function w3tc_ajax_ustats_get() {
		$storage = new UsageStatistics_StorageReader();
		$summary = $storage->get_history_summary();

		if ( defined( 'W3TC_DEBUG' ) ) {
			echo wp_json_encode( $summary, JSON_PRETTY_PRINT );
			exit();
		}

		echo wp_json_encode( $summary );
		exit();
	}

	/**
	 * Handles the AJAX request to test access to the specified log file.
	 *
	 * This method verifies the nonce, checks if a log file path has been provided,
	 * attempts to open the file, and returns a success or failure message based on
	 * whether the file could be opened.
	 *
	 * @return void
	 */
	public function w3tc_ajax_ustats_access_log_test() {
		$nonce_val = Util_Request::get_array( '_wpnonce' )[0];
		$nonce     = isset( $nonce_val ) ? $nonce_val : false;

		if ( ! wp_verify_nonce( $nonce, 'w3tc' ) ) {
			wp_die( esc_html__( 'Invalid WordPress nonce.  Please reload the page and try again.', 'w3-total-cache' ) );
		}

		$handle       = false;
		$filename_val = Util_Request::get_string( 'filename' );
		$filepath     = ! empty( $filename_val ) ? str_replace( '://', '/', $filename_val ) : null;

		if ( $filepath ) {
			$handle = @fopen( $filepath, 'rb' ); // phpcs:ignore WordPress
		}

		if ( $handle ) {
			esc_html_e( 'Success', 'w3-total-cache' );
		} else {
			esc_html_e( 'Failed to open file', 'w3-total-cache' );
		}

		wp_die();
	}
}
