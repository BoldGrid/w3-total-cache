<?php
/**
 * File: UsageStatistics_Core.php
 *
 * @package W3TC
 */

namespace W3TC;

/**
 * Class UsageStatistics_Core
 */
class UsageStatistics_Core {
	/**
	 * Shutdown handler added flag
	 *
	 * @var bool
	 */
	private $shutdown_handler_added = false;

	/**
	 * Storage
	 *
	 * @var W3TC\UsageStatistics_StorageWriter
	 */
	private $storage;

	/**
	 * Hotspot flushing state on exit attempt
	 *
	 * @var string
	 */
	private $hotspot_flushing_state_on_exit_attempt = null;

	/**
	 * Constructor for the UsageStatistics_Core class.
	 *
	 * Initializes the storage mechanism for usage statistics.
	 * This ensures that the class is ready to handle metrics and data operations.
	 *
	 * @return void
	 */
	public function __construct() {
		$this->storage = new UsageStatistics_StorageWriter();
	}

	/**
	 * Adds a shutdown handler for usage statistics.
	 *
	 * Registers the `shutdown` method to execute during the WordPress shutdown hook.
	 * If exit-requested hotspot flushing is enabled, it also sets up an additional
	 * initialization handler to ensure proper processing.
	 *
	 * @return void
	 */
	public function add_shutdown_handler() {
		$this->shutdown_handler_added = true;
		add_action( 'shutdown', array( $this, 'shutdown' ), 100000, 0 );

		if ( ! is_null( $this->hotspot_flushing_state_on_exit_attempt ) ) {
			add_action( 'init', array( $this, 'init_when_exit_requested' ) );
		}
	}

	/**
	 * Checks if the shutdown handler has been added.
	 *
	 * @return bool True if the shutdown handler has been added, false otherwise.
	 */
	public function is_shutdown_handler_added() {
		return $this->shutdown_handler_added;
	}

	/**
	 * Initializes processing when an exit is requested.
	 *
	 * If called, it terminates the script execution immediately.
	 * Typically used for critical flush processing.
	 *
	 * @return void
	 */
	public function init_when_exit_requested() {
		exit();
	}

	/**
	 * Executes the shutdown process for usage statistics.
	 *
	 * Handles the flushing of hotspot data based on the current state.
	 * Also triggers an action hook for additional processing during the request shutdown.
	 *
	 * @return void
	 */
	public function shutdown() {
		if ( ! is_null( $this->hotspot_flushing_state_on_exit_attempt ) ) {
			$this->storage->finish_flush_hotspot_data();
		} else {
			$this->storage->maybe_flush_hotspot_data();
		}

		do_action( 'w3tc_usage_statistics_of_request', $this->storage );
	}

	/**
	 * Applies metrics before WordPress initialization and exits if required.
	 *
	 * If the plugin is already loaded, it skips processing as metrics will be handled by the shutdown method.
	 * Otherwise, it prepares for hotspot flushing and applies metrics via the provided function.
	 * Exits the process if flushing is required immediately.
	 *
	 * @param callable $metrics_function A callback function to apply metrics using the storage instance.
	 *
	 * @return void
	 */
	public function apply_metrics_before_init_and_exit( $metrics_function ) {
		// plugin already loaded, metrics will be added normal way by shutdown.

		if ( $this->shutdown_handler_added ) {
			return;
		}

		$this->hotspot_flushing_state_on_exit_attempt = $this->storage->begin_flush_hotspot_data();

		// flush wants to happen in that process, need to pass through whole
		// wp request processing further.
		if ( 'not_needed' !== $this->hotspot_flushing_state_on_exit_attempt ) {
			return;
		}

		call_user_func( $metrics_function, $this->storage );

		exit();
	}
}
