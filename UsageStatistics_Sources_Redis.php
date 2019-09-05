<?php
namespace W3TC;



class UsageStatistics_Sources_Redis {
	private $servers;



	public function __construct( $server_descriptors ) {
		$this->servers = array();

		foreach ( $server_descriptors as $i ) {
			foreach ( $i['servers'] as $host_port ) {
				if ( !isset( $this->servers[$host_port] ) )
					$this->servers[$host_port] = array(
						'password' => $i['password'],
						'dbid' => $i['dbid'],
						'module_names' => array( $i['name'] )
					);
				else
					$this->servers[$host_port]['module_names'][] = $i['name'];
			}
		}
	}



	public function get_snapshot() {
		$size_used = 0;
		$get_calls = 0;
		$get_hits = 0;

		foreach ( $this->servers as $host_port => $i ) {
			$cache = Cache::instance( 'redis',
				array(
					'servers' => array( $host_port ),
					'password' => $i['password'],
					'dbid' => $i['dbid']
				) );

			$stats = $cache->get_statistics();

			$size_used += Util_UsageStatistics::v( $stats, 'used_memory' );
			$get_calls +=
				Util_UsageStatistics::v3( $stats, 'keyspace_hits' ) +
				Util_UsageStatistics::v3( $stats, 'keyspace_misses' );
			$get_hits += Util_UsageStatistics::v( $stats, 'keyspace_hits' );
		}

		return array(
			'size_used' => $size_used,
			'get_calls' => $get_calls,
			'get_hits' => $get_hits
		);
	}



	public function get_summary() {
		$sum = array(
			'module_names' => array(),
			'size_used' => 0,
			'size_maxbytes' => 0,
			'get_total' => 0,
			'get_hits' => 0,
			'evictions' => 0,
			'uptime' => 0
		);

		foreach ( $this->servers as $host_port => $i ) {
			$cache = Cache::instance( 'redis',
				array(
					'servers' => array( $host_port ),
					'password' => $i['password'],
					'dbid' => $i['dbid']
				) );

			$stats = $cache->get_statistics();

			$sum['module_names'] =
				array_merge( $sum['module_names'], $i['module_names'] );
			$sum['size_used'] += Util_UsageStatistics::v3( $stats, 'used_memory');
			$sum['get_total'] +=
				Util_UsageStatistics::v3( $stats, 'keyspace_hits' ) +
				Util_UsageStatistics::v3( $stats, 'keyspace_misses' );
			$sum['get_hits'] += Util_UsageStatistics::v3( $stats, 'keyspace_hits' );
			$sum['evictions'] += Util_UsageStatistics::v3( $stats, 'evicted_keys' );
			$sum['uptime'] += Util_UsageStatistics::v3( $stats, 'uptime_in_seconds' );
		}

		$summary = array(
			'module_names' => implode( ',', $sum['module_names'] ),
			'size_used' => Util_UsageStatistics::bytes_to_size2(
				$sum, 'size_used' ),
			'get_hit_rate' => Util_UsageStatistics::percent2(
				$sum, 'get_hits', 'get_total' ),
			'evictions_per_second' => Util_UsageStatistics::value_per_second(
				$sum, 'evictions', 'uptime' )
		);

		return $summary;


		$summary = array();

		foreach ( $servers as $host_port => $i ) {
			$cache = Cache::instance( 'redis',
				array(
					'servers' => array( $host_port ),
					'password' => $i['password'],
					'dbid' => $i['dbid']
				) );

			$stats = $cache->get_statistics();

			if ( isset( $stats['keyspace_hits'] ) && $stats['keyspace_misses'] )
				$stats['_keyspace_total'] =
					(int)$stats['keyspace_hits'] + (int)$stats['keyspace_misses'];

			$id = md5( $host_port );
			$summary[$id] = array(
				'name' => $host_port,
				'module_names' => $i['module_names'],
				'size_used' =>
				Util_UsageStatistics::bytes_to_size2( $stats, 'used_memory' ),
				'hit_rate' =>
				Util_UsageStatistics::percent2( $stats, 'keyspace_hits', '_keyspace_total' ),
				'expirations_per_second' => Util_UsageStatistics::value_per_second(
					$stats, 'expired_keys', 'uptime_in_seconds' ),
				'evictions_per_second' => Util_UsageStatistics::value_per_second(
					$stats, 'evicted_keys', 'uptime_in_seconds' )
			);
		}

		return $summary;
	}
}
