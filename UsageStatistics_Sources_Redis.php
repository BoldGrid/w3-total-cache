<?php
/**
 * File: UsageStatistics_Sources_Redis.php
 *
 * @package W3TC
 */

namespace W3TC;

/**
 * Class UsageStatistics_Sources_Redis
 *
 * Usage Statistics Sources for Redis
 */
class UsageStatistics_Sources_Redis {
	/**
	 * An associative array holding Redis server configurations, indexed by host and port.
	 *
	 * @var array
	 */
	private $servers;

	/**
	 * Constructor for initializing Redis server configurations.
	 *
	 * This method processes the given server descriptors and sets up a mapping of server configurations,
	 * where each server is identified by its host and port, along with associated module names, password,
	 * and database ID. This allows for organized management of Redis server connections.
	 *
	 * @param array $server_descriptors An array of server descriptor arrays, each containing 'servers', 'password', 'dbid', and 'name'.
	 *
	 * @return void
	 */
	public function __construct( $server_descriptors ) {
		$this->servers = array();

		foreach ( $server_descriptors as $i ) {
			foreach ( $i['servers'] as $host_port ) {
				if ( ! isset( $this->servers[ $host_port ] ) ) {
					$this->servers[ $host_port ] = array(
						'password'     => $i['password'],
						'dbid'         => $i['dbid'],
						'module_names' => array( $i['name'] ),
					);
				} else {
					$this->servers[ $host_port ]['module_names'][] = $i['name'];
				}
			}
		}
	}

	/**
	 * Retrieves a snapshot of Redis memory usage and keyspace statistics across all servers.
	 *
	 * This method aggregates memory usage, total get calls, and get hits from all configured Redis servers.
	 * It connects to each Redis server, fetches its statistics, and updates the totals accordingly.
	 * The result is an array containing the total memory used, total get calls, and get hits.
	 *
	 * @return array An associative array containing the total 'size_used', 'get_calls', and 'get_hits'.
	 */
	public function get_snapshot() {
		$size_used = 0;
		$get_calls = 0;
		$get_hits  = 0;

		foreach ( $this->servers as $host_port => $i ) {
			$cache = Cache::instance(
				'redis',
				array(
					'servers'        => array( $host_port ),
					'password'       => $i['password'],
					'dbid'           => $i['dbid'],
					'timeout'        => 0,
					'retry_interval' => 0,
					'read_timeout'   => 0,
				)
			);

			$stats = $cache->get_statistics();

			$size_used += Util_UsageStatistics::v( $stats, 'used_memory' );
			$get_calls +=
				Util_UsageStatistics::v3( $stats, 'keyspace_hits' ) +
				Util_UsageStatistics::v3( $stats, 'keyspace_misses' );
			$get_hits  += Util_UsageStatistics::v( $stats, 'keyspace_hits' );
		}

		return array(
			'size_used' => $size_used,
			'get_calls' => $get_calls,
			'get_hits'  => $get_hits,
		);
	}

	/**
	 * Retrieves a summary of Redis usage statistics across all servers.
	 *
	 * This method gathers a variety of statistics from each Redis server, including module names,
	 * memory usage, keyspace hit/miss statistics, eviction rates, and uptime. It aggregates these statistics
	 * and formats them into a summary for reporting.
	 *
	 * @return array An associative array containing a summary of Redis statistics, including 'module_names', 'size_used',
	 *               'get_hit_rate', and 'evictions_per_second'.
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
				'redis',
				array(
					'servers'        => array( $host_port ),
					'password'       => $i['password'],
					'dbid'           => $i['dbid'],
					'timeout'        => 0,
					'retry_interval' => 0,
					'read_timeout'   => 0,
				)
			);

			$stats = $cache->get_statistics();

			$sum['module_names'] = array_merge( $sum['module_names'], $i['module_names'] );
			$sum['size_used']   += Util_UsageStatistics::v3( $stats, 'used_memory' );
			$sum['get_total']   +=
				Util_UsageStatistics::v3( $stats, 'keyspace_hits' ) +
				Util_UsageStatistics::v3( $stats, 'keyspace_misses' );
			$sum['get_hits']    += Util_UsageStatistics::v3( $stats, 'keyspace_hits' );
			$sum['evictions']   += Util_UsageStatistics::v3( $stats, 'evicted_keys' );
			$sum['uptime']      += Util_UsageStatistics::v3( $stats, 'uptime_in_seconds' );
		}

		$summary = array(
			'module_names'         => implode( ',', $sum['module_names'] ),
			'size_used'            => Util_UsageStatistics::bytes_to_size2( $sum, 'size_used' ),
			'get_hit_rate'         => Util_UsageStatistics::percent2( $sum, 'get_hits', 'get_total' ),
			'evictions_per_second' => Util_UsageStatistics::value_per_second( $sum, 'evictions', 'uptime' ),
		);

		return $summary;
	}
}
