<?php
/**
 * File: UsageStatistics_Sources_Memcached.php
 *
 * @package W3TC
 */

namespace W3TC;

/**
 * Class UsageStatistics_Sources_Memcached
 */
class UsageStatistics_Sources_Memcached {
	/**
	 * Stores server configurations indexed by host and port.
	 *
	 * @var array
	 */
	private $servers;

	/**
	 * Constructor for initializing the Memcached server configurations.
	 *
	 * This method takes an array of server descriptors, processes each one to extract
	 * necessary information, and stores the server configuration in the `$servers`
	 * attribute, ensuring that the configuration for each unique host-port pair is
	 * only added once.
	 *
	 * @param array $server_descriptors Array of server descriptors containing server details.
	 *                                  Each item should contain 'servers', 'username', 'password', and 'name' attributes.
	 *
	 * @return void
	 */
	public function __construct( $server_descriptors ) {
		$this->servers = array();

		foreach ( $server_descriptors as $i ) {
			foreach ( $i['servers'] as $host_port ) {
				if ( ! isset( $this->servers[ $host_port ] ) ) {
					$this->servers[ $host_port ] = array(
						'username'     => $i['username'],
						'password'     => $i['password'],
						'module_names' => array( $i['name'] ),
					);
				} else {
					$this->servers[ $host_port ]['module_names'][] = $i['name'];
				}
			}
		}
	}

	/**
	 * Retrieves a snapshot of the Memcached statistics across all servers.
	 *
	 * This method iterates over the list of servers, collects Memcached statistics
	 * (such as memory usage and cache hits), and accumulates the data. The method
	 * returns an array containing the total memory usage, total 'get' commands, and
	 * total cache hits across all servers.
	 *
	 * @return array An associative array containing the total 'size_used', 'get_calls', and 'get_hits'.
	 */
	public function get_snapshot() {
		$size_used = 0;
		$get_calls = 0;
		$get_hits  = 0;

		foreach ( $this->servers as $host_port => $i ) {
			$cache = Cache::instance(
				'memcached',
				array(
					'servers'  => array( $host_port ),
					'username' => $i['username'],
					'password' => $i['password'],
				)
			);

			$stats = $cache->get_statistics();

			$size_used += Util_UsageStatistics::v( $stats, 'bytes' );
			$get_calls += Util_UsageStatistics::v( $stats, 'cmd_get' );
			$get_hits  += Util_UsageStatistics::v( $stats, 'get_hits' );
		}

		return array(
			'size_used' => $size_used,
			'get_calls' => $get_calls,
			'get_hits'  => $get_hits,
		);
	}

	/**
	 * Retrieves a summary of Memcached statistics across all servers.
	 *
	 * This method iterates over the list of servers, gathers various Memcached statistics
	 * (such as total memory used, maximum memory limit, cache hits, and uptime), and
	 * calculates additional derived metrics such as hit rate and evictions per second.
	 * The method returns a summary of these metrics, including the list of module names.
	 *
	 * @return array An associative array containing summary metrics such as 'module_names',
	 *               'size_percent', 'size_used', 'get_hit_rate', and 'evictions_per_second'.
	 */
	public function get_summary() {
		$sum = array(
			'module_names'  => array(),
			'size_used'     => 0,
			'size_maxbytes' => 0,
			'get_total'     => 0,
			'get_hits'      => 0,
			'evictions'     => 0,
			'uptime'        => 0,
		);

		foreach ( $this->servers as $host_port => $i ) {
			$cache = Cache::instance(
				'memcached',
				array(
					'servers'  => array( $host_port ),
					'username' => $i['username'],
					'password' => $i['password'],
				)
			);

			$stats = $cache->get_statistics();

			$sum['module_names']   = array_merge( $sum['module_names'], $i['module_names'] );
			$sum['size_used']     += Util_UsageStatistics::v3( $stats, 'bytes' );
			$sum['size_maxbytes'] += Util_UsageStatistics::v3( $stats, 'limit_maxbytes' );
			$sum['get_total']     += Util_UsageStatistics::v3( $stats, 'cmd_get' );
			$sum['get_hits']      += Util_UsageStatistics::v3( $stats, 'get_hits' );
			$sum['evictions']     += Util_UsageStatistics::v3( $stats, 'evictions' );
			$sum['uptime']        += Util_UsageStatistics::v3( $stats, 'uptime' );
		}

		$summary = array(
			'module_names'         => implode( ',', $sum['module_names'] ),
			'size_percent'         => Util_UsageStatistics::percent2( $sum, 'size_used', 'size_maxbytes' ),
			'size_used'            => Util_UsageStatistics::bytes_to_size2( $sum, 'size_used' ),
			'get_hit_rate'         => Util_UsageStatistics::percent2( $sum, 'get_hits', 'get_total' ),
			'evictions_per_second' => Util_UsageStatistics::value_per_second( $sum, 'evictions', 'uptime' ),
		);

		return $summary;
	}
}
