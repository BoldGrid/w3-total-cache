<?php
namespace W3TC;

if ( !defined( 'W3TC' ) )
	die();

include W3TC_INC_DIR . '/options/common/header.php';

?>
<div class="ustats_loading w3tc_loading">Loading...</div>
<div class="ustats_error w3tc_none">An error occurred</div>
<div class="ustats_nodata w3tc_none">
	<p>No data collected yet</p>
	<a href="#" class="ustats_reload">Refresh</a>
</div>

<div class="ustats_content w3tc_hidden">
	<span class="ustats_reload">.</span>
</div>

<div class="metabox-holder" style="display: none">
	<?php Util_Ui::postbox_header( __( 'Web Requests', 'w3-total-cache' ) ); ?>

	<div class="ustats_block ustats_pagecache">
		<div class="ustats_block_data">
			<div class="ustats_header">
				Page Cache
				<span class="ustats_pagecache_engine_name w3tcus_inline">(<span></span>)</span>
				:
			</div>
			<div class="ustats_pagecache_size_used">
				Cache size: <span></span>
			</div>
			<div class="ustats_pagecache_items">
				Entries: <span></span>
			</div>

			<div class="ustats_pagecache_requests">
				Requests: <span></span>
			</div>
			<div class="ustats_pagecache_requests_per_second">
				Requests/sec: <span></span>
			</div>
			<div class="ustats_pagecache_requests_hit">
				Cache hits: <span></span>
			</div>
			<div class="ustats_pagecache_requests_hit_rate">
				Cache hit rate: <span></span>
			</div>

			<div class="ustats_pagecache_request_time_ms">
				Avg processing time: <span></span> ms
			</div>
			<div class="ustats_pagecache_size_percent">
				Size used: <span></span>
			</div>
		</div>
		<div class="ustats_block_chart">
			Request time
			<canvas id="w3tcus_pagecache_chart"></canvas>
		</div>
	</div>

	<div class="ustats_block ustats_php">
		<div class="ustats_block_data">
			<div class="ustats_header">PHP Requests:</div>
			<div class="ustats_php_php_requests_per_second">
				Requests/sec: <span></span>
			</div>
			<?php
			$this->summary_item(
				'php_php_requests',
				'Requests/period',
				true,
				'',
				'#009900'
			);
			$this->summary_item(
				'php_php_requests_pagecache_hit',
				$php_php_requests_pagecache_hit_name
			);
			$this->summary_item(
				'php_php_requests_pagecache_miss',
				'Not cached',
				false,
				'',
				'#990000'
			);
			echo '<div class="ustats_php_php_requests_pagecache_miss_level2_wrap">';
			$this->summary_item(
				'php_php_requests_pagecache_miss_404',
				'404',
				false,
				'ustats_php_php_requests_pagecache_miss_level2',
				'',
				'miss_404'
			);
			$this->summary_item(
				'php_php_requests_pagecache_miss_ajax',
				'AJAX',
				false,
				'ustats_php_php_requests_pagecache_miss_level2',
				'',
				'miss_ajax'
			);
			$this->summary_item(
				'php_php_requests_pagecache_miss_api_call',
				'API call',
				false,
				'ustats_php_php_requests_pagecache_miss_level2',
				'',
				'miss_api_call'
			);
			$this->summary_item(
				'php_php_requests_pagecache_miss_configuration',
				'W3TC Configuration',
				false,
				'ustats_php_php_requests_pagecache_miss_level2',
				'',
				'miss_configuration'
			);
			$this->summary_item(
				'php_php_requests_pagecache_miss_fill',
				'Cache Fill',
				false,
				'ustats_php_php_requests_pagecache_miss_level2',
				'',
				'miss_fill'
			);
			$this->summary_item(
				'php_php_requests_pagecache_miss_logged_in',
				'Logged In',
				false,
				'ustats_php_php_requests_pagecache_miss_level2',
				'',
				'miss_logged_in'
			);
			$this->summary_item(
				'php_php_requests_pagecache_miss_mfunc',
				'mfunc',
				false,
				'ustats_php_php_requests_pagecache_miss_level2',
				'',
				'miss_mfunc'
			);
			$this->summary_item(
				'php_php_requests_pagecache_miss_query_string',
				'Query String',
				false,
				'ustats_php_php_requests_pagecache_miss_level2',
				'',
				'miss_query_string'
			);
			$this->summary_item(
				'php_php_requests_pagecache_miss_third_party',
				'Third Party',
				false,
				'ustats_php_php_requests_pagecache_miss_level2',
				'',
				'miss_third_party'
			);
			$this->summary_item(
				'php_php_requests_pagecache_miss_wp_admin',
				'wp-admin',
				false,
				'ustats_php_php_requests_pagecache_miss_level2',
				'',
				'miss_wp_admin'
			);
			echo '</div>';
			?>
		</div>
		<div class="ustats_block_chart">
			Requests handled by PHP
			<canvas id="w3tcus_php_requests_chart"></canvas>
		</div>
	</div>

	<div class="ustats_block ustats_access_log" style="height: 32vw">
		<div class="ustats_block_data">
			<div class="ustats_header">Access Log:</div>
			<div class="ustats_access_log_dynamic_requests_total">
				Dynamic Requests/period: <span></span>
			</div>
			<div class="ustats_access_log_dynamic_requests_per_second">
				Dynamic Requests/second: <span></span>
			</div>
			<div class="ustats_access_log_dynamic_requests_timing">
				Dynamic time to process (ms): <span></span>
			</div>
			<div class="ustats_access_log_static_requests_total">
				Static Requests/period: <span></span>
			</div>
			<div class="ustats_access_log_static_requests_per_second">
				Static Requests/second: <span></span>
			</div>
			<div class="ustats_access_log_static_requests_timing">
				Static time to process (ms): <span></span>
			</div>
		</div>
		<div class="ustats_block_chart">
			Requests
			<canvas id="w3tcus_access_log_chart_requests"></canvas>
			Time per request (ms)
			<canvas id="w3tcus_access_log_chart_timing"></canvas>
		</div>
	</div>

	<?php Util_Ui::postbox_footer(); ?>
</div>

<div class="metabox-holder" style="display: none">
	<?php Util_Ui::postbox_header( __( 'Minify', 'w3-total-cache' ) ); ?>

	<div class="ustats_block ustats_minify">
		<div class="ustats_block_data">
			<div class="ustats_header">Minify:</div>
			<div class="ustats_minify_size_used">
				Used: <span></span>
			</div>
			<div class="ustats_minify_size_items">
				Files: <span></span>
			</div>
			<div class="ustats_minify_size_compression_css">
				CSS compression in cache: <span></span>
			</div>
			<div class="ustats_minify_size_compression_js">
				JS compression in cache: <span></span>
			</div>
			<div class="ustats_minify_requests_total">
				Requests/period: <span></span>
			</div>
			<div class="ustats_minify_requests_per_second">
				Requests/sec: <span></span>
			</div>
			<div class="ustats_minify_compression_css">
				Responded CSS compression: <span></span>
			</div>
			<div class="ustats_minify_compression_js">
				Responded JS compression: <span></span>
			</div>
		</div>
	</div>

	<?php Util_Ui::postbox_footer(); ?>
</div>

<div class="metabox-holder" style="display: none">
	<?php Util_Ui::postbox_header( __( 'Object Cache', 'w3-total-cache' ) ); ?>

	<div class="ustats_block ustats_objectcache" style="height: 32vw">
		<div class="ustats_block_data">
			<div class="ustats_header">
				Object Cache
				<span class="ustats_objectcache_engine_name w3tcus_inline">(<span></span>)</span>
			</div>
			<div class="ustats_objectcache_get_total">
				Gets/period: <span></span>
			</div>
			<div class="ustats_objectcache_get_hits">
				Hits/period: <span></span>
			</div>
			<div class="ustats_objectcache_hit_rate">
				Hit rate: <span></span>
			</div>
			<div class="ustats_objectcache_sets">
				Sets/period: <span></span>
			</div>
			<div class="ustats_objectcache_flushes">
				Flushes/period: <span></span>
			</div>
			<div class="ustats_objectcache_time_ms">
				Time taken: <span></span> ms
			</div>

			<div class="ustats_objectcache_calls_per_second">
				Calls/sec: <span></span>
			</div>

			<a href="?page=w3tc_stats&view=oc_requests">Detailed view (in debug mode only)</a>
		</div>
		<div class="ustats_block_chart">
			Time taken for ObjectCache activity
			<canvas id="w3tcus_objectcache_time_chart"></canvas>
			Calls
			<canvas id="w3tcus_objectcache_chart"></canvas>
		</div>
	</div>

	<div class="ustats_block ustats_fragmentcache">
		<div class="ustats_block_data">
			<div class="ustats_header">Fragment Cache:</div>
			<div class="ustats_fragmentcache_calls_total">
				Calls/period: <span></span>
			</div>
			<div class="ustats_fragmentcache_calls_per_second">
				Calls/sec: <span></span>
			</div>
			<div class="ustats_fragmentcache_hit_rate">
				Hit rate: <span></span>
			</div>
		</div>
	</div>

	<?php Util_Ui::postbox_footer(); ?>
</div>


<div class="metabox-holder" style="display: none">
	<?php Util_Ui::postbox_header( __( 'Database', 'w3-total-cache' ) ); ?>

	<div class="ustats_block ustats_dbcache" style="height: 32vw">
		<div class="ustats_block_data">
			<div class="ustats_header">
				Database Cache
				<span class="ustats_dbcache_engine_name w3tcus_inline">(<span></span>)</span>
			</div>

			<div class="ustats_dbcache_calls_total">
				Calls/period: <span></span>
			</div>
			<div class="ustats_dbcache_calls_per_second">
				Calls/sec: <span></span>
			</div>
			<div class="ustats_dbcache_hit_rate">
				Hit rate: <span></span>
			</div>
			<div class="ustats_dbcache_flushes">
				Cache flushes: <span></span>
			</div>
			<div class="ustats_dbcache_time_ms">
				Time taken: <span></span> ms
			</div>

			<a href="?page=w3tc_stats&view=db_requests">Slowest requests (in debug mode only)</a>
		</div>
		<div class="ustats_block_chart">
			Time taken for database activity
			<canvas id="w3tcus_dbcache_time_chart"></canvas>
			Requests
			<canvas id="w3tcus_dbcache_chart"></canvas>
		</div>
	</div>

	<div class="ustats_block ustats_wpdb">
		<div class="ustats_block_data">
			<div class="ustats_header">Database:</div>
			<div class="ustats_wpdb_calls_total">
				Calls/period: <span></span>
			</div>
			<div class="ustats_wpdb_calls_per_second">
				Calls/sec: <span></span>
			</div>
		</div>
		<div class="ustats_block_chart">
			Requests
			<canvas id="w3tcus_wpdb_chart"></canvas>
		</div>
	</div>

	<?php Util_Ui::postbox_footer(); ?>
</div>


<div class="metabox-holder" style="display: none">
	<?php Util_Ui::postbox_header( __( 'System Info', 'w3-total-cache' ) ); ?>

	<div class="ustats_block ustats_php">
		<div class="ustats_block_data">
			<div class="ustats_header">PHP Memory:</div>
			<div class="ustats_php_memory">
				Memory used: <span></span>
			</div>
		</div>
		<div class="ustats_block_chart">
			Memory per request (MB)
			<canvas id="w3tcus_php_memory_chart"></canvas>
		</div>
	</div>

	<div class="ustats_block ustats_cpu">
		<div class="ustats_block_data">
			<div class="ustats_header">CPU load:</div>
			<div class="ustats_cpu_avg">
				CPU load: <span></span>
			</div>
		</div>
		<div class="ustats_block_chart">
			CPU load
			<canvas id="w3tcus_cpu_chart"></canvas>
		</div>
	</div>

	<?php Util_Ui::postbox_footer(); ?>
</div>


<div class="metabox-holder" style="display: none">
	<?php Util_Ui::postbox_header( __( 'Cache Storage', 'w3-total-cache' ) ); ?>

	<div class="ustats_block ustats_memcached" style="height: 32vw">
		<div class="ustats_block_data">
			<div class="ustats_header">Memcached</div>

			<div class="ustats_memcached_used_by">
				Used by <span></span>
			</div>
			<div class="ustats_memcached_evictions_per_second">
				Evictions/sec: <span></span>
			</div>
			<div class="ustats_memcached_size_used">
				Used: <span></span>
			</div>
			<div class="ustats_memcached_size_percent">
				Used (%): <span></span>
			</div>
			<div class="ustats_memcached_get_hit_rate">
				Hit rate: <span></span>
			</div>
		</div>
		<div class="ustats_block_chart">
			Size used (MB)
			<canvas id="w3tcus_memcached_size_chart"></canvas>
			Hit rate
			<canvas id="w3tcus_memcached_hit_chart"></canvas>
		</div>
	</div>

	<div class="ustats_block ustats_redis" style="height: 32vw">
		<div class="ustats_block_data">
			<div class="ustats_header">Redis</div>

			<div class="ustats_redis_used_by">
				Used by <span></span>
			</div>
			<div class="ustats_redis_evictions_per_second">
				Evictions/sec: <span></span>
			</div>
			<div class="ustats_redis_size_used">
				Used: <span></span>
			</div>
			<div class="ustats_redis_get_hit_rate">
				Hit rate: <span></span>
			</div>
		</div>
		<div class="ustats_block_chart">
			Size used (MB)
			<canvas id="w3tcus_redis_size_chart"></canvas>
			Hit rate
			<canvas id="w3tcus_redis_hit_chart"></canvas>
		</div>
	</div>

	<div class="ustats_block ustats_apc" style="height: 32vw">
		<div class="ustats_block_data">
			<div class="ustats_header">APC</div>

			<div class="ustats_apc_used_by">
				Used by <span></span>
			</div>
			<div class="ustats_apc_evictions">
				Evictions: <span></span>
			</div>
			<div class="ustats_apc_size_used">
				Used: <span></span>
			</div>
			<div class="ustats_apc_size_percent">
				Used (%): <span></span>
			</div>
			<div class="ustats_apc_get_hit_rate">
				Hit rate: <span></span>
			</div>
			<div class="ustats_apc_items">
				Items: <span></span>
			</div>
		</div>
		<div class="ustats_block_chart">
			Size used (MB)
			<canvas id="w3tcus_apc_size_chart"></canvas>
			Hit rate
			<canvas id="w3tcus_apc_hit_chart"></canvas>
		</div>
	</div>

	<?php Util_Ui::postbox_footer(); ?>
</div>
