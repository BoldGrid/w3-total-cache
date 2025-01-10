<?php
/**
 * File: UsageStatistics_Sources_Apc.php
 *
 * @package W3TC
 */

namespace W3TC;

/**
 * Class UsageStatistics_Sources_Apc
 */
class UsageStatistics_Sources_Apc {
	/**
	 * An array that stores the names of modules.
	 *
	 * @var array
	 */
	private $module_names = array();

	/**
	 * Constructor for initializing the module names from the given server descriptors.
	 *
	 * This method iterates over the provided `$server_descriptors` array and extracts the 'name'
	 * of each module to populate the `$module_names` array. The names are stored to keep track of
	 * which modules are associated with the current object instance.
	 *
	 * @param array $server_descriptors An array of server descriptors, where each descriptor is
	 *                                  an associative array containing module details.
	 */
	public function __construct( $server_descriptors ) {
		foreach ( $server_descriptors as $module_key => $i ) {
			$this->module_names[] = $i['name'];
		}
	}

	/**
	 * Retrieves a snapshot of the current APCu cache statistics.
	 *
	 * This method fetches the current cache information using `apcu_cache_info()` and returns
	 * a summary of the cache's statistics such as the number of entries, memory size, number of hits,
	 * and the total cache accesses (hits + misses).
	 *
	 * @return array An associative array containing the total 'items', 'size_used', 'get_hits', and 'get_total'.
	 */
	public function get_snapshot() {
		$cache = apcu_cache_info();

		return array(
			'items'     => $cache['num_entries'],
			'size_used' => $cache['mem_size'],
			'get_hits'  => $cache['num_hits'],
			'get_total' => ( $cache['num_hits'] + $cache['num_misses'] ),
		);
	}

	/**
	 * Retrieves a summary of the APCu cache and memory statistics.
	 *
	 * This method provides a detailed summary of the cache usage, including:
	 * - The modules using the cache.
	 * - The number of cache entries.
	 * - The total memory used and the percentage of memory used.
	 * - Cache hit and miss statistics.
	 * - The uptime of the cache in seconds.
	 * - The number of cache evictions.
	 * - Requests per second based on the cache runtime.
	 *
	 * The method also calculates hit rate percentage and converts memory usage into a more readable format.
	 *
	 * @return array An associative array containing 'used_by', 'items', 'size_used', 'get_hits', 'get_total',
	 *               'runtime_secs', 'evictions', 'size_percent', 'requests_per_second', and 'get_hit_rate'
	 */
	public function get_summary() {
		$cache = apcu_cache_info();

		$time    = time();
		$runtime = $time - $cache['start_time'];

		$mem       = apcu_sma_info();
		$mem_size  = $mem['num_seg'] * $mem['seg_size'];
		$mem_avail = $mem['avail_mem'];
		$mem_used  = $mem_size - $mem_avail;

		$sum = array(
			'used_by'      => implode( ',', $this->module_names ),
			'items'        => $cache['num_entries'],
			'size_used'    => Util_UsageStatistics::bytes_to_size( $cache['mem_size'] ),
			'get_hits'     => $cache['num_hits'],
			'get_total'    => ( $cache['num_hits'] + $cache['num_misses'] ),
			'runtime_secs' => $runtime,
			'evictions'    => $cache['expunges'],
			'size_percent' => Util_UsageStatistics::percent( $mem_used, $mem_avail ),
		);

		if ( 0 !== $sum['runtime_secs'] ) {
			$sum['requests_per_second'] = sprintf( '%.2f', $sum['get_total'] / $sum['runtime_secs'] );
		}

		$sum['get_hit_rate'] = Util_UsageStatistics::percent2( $sum, 'get_hits', 'get_total' );

		return $sum;
	}
}
