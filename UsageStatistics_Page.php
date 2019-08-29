<?php
namespace W3TC;

class UsageStatistics_Page {
	static public function admin_print_scripts_w3tc_stats() {
		wp_enqueue_style( 'w3tc-widget-usage-statistics',
			plugins_url( 'UsageStatistics_Page_View.css', W3TC_FILE ),
			array(), W3TC_VERSION );

		wp_enqueue_script( 'w3tc-canvasjs',
			plugins_url( 'pub/js/chartjs.min.js', W3TC_FILE ),
			array(), W3TC_VERSION );
		wp_enqueue_script( 'w3tc-widget-usage-statistics',
			plugins_url( 'UsageStatistics_Page_View.js', W3TC_FILE ),
			array( 'w3tc-canvasjs' ), W3TC_VERSION );

	}



	public function render() {
		$c = Dispatcher::config();
		$enabled = ( $c->get_boolean( 'stats.enabled' ) &&
			Util_Environment::is_w3tc_pro( $c ) );
		if ( !$enabled ) {
			if ( !Util_Environment::is_w3tc_pro( $c ) ) {
				include  W3TC_DIR . '/UsageStatistics_Page_View_Free.php';
			} else {
				include  W3TC_DIR . '/UsageStatistics_Page_View_Disabled.php';
			}
			return;
		}

		if ( isset( $_REQUEST['view'] ) && $_REQUEST['view'] == 'db_requests' ) {
			$storage = new UsageStatistics_StorageReader();
			$summary = $storage->get_history_summary();
			$timestamp_start = $summary['period']['timestamp_start'];

			$sort_column = isset( $_REQUEST['sort'] ) ? $_REQUEST['sort'] : '';
			if ( !in_array( $sort_column, array(
					'query', 'count_total', 'count_hit', 'avg_size',
					'avg_time_ms', 'sum_time_ms' ) ) ) {
				$sort_column = 'sum_time_ms';
			}

			if ( !$c->get_boolean( 'dbcache.debug' ) ) {
				include  W3TC_DIR . '/UsageStatistics_Page_View_NoDebugMode.php';
				return;
			}

			$reader = new UsageStatistics_Source_DbQueriesLog( $timestamp_start,
				$sort_column );
			$items = $reader->list();

			$result = array(
				'date_min' =>
					Util_UsageStatistics::time_mins( $timestamp_start ),
				'date_max' => Util_UsageStatistics::time_mins( time() ),
				'sort_column' => $sort_column,
				'items' => $items
			);

			include  W3TC_DIR . '/UsageStatistics_Page_DbRequests_View.php';
		} elseif ( isset( $_REQUEST['view'] ) && $_REQUEST['view'] == 'oc_requests' ) {
			$storage = new UsageStatistics_StorageReader();
			$summary = $storage->get_history_summary();
			$timestamp_start = $summary['period']['timestamp_start'];

			$sort_column = isset( $_REQUEST['sort'] ) ? $_REQUEST['sort'] : '';
			if ( !in_array( $sort_column, array(
					'group', 'count_total', 'count_get_total', 'count_get_hit',
					'count_set', 'avg_size', 'sum_size', 'sum_time_ms' ) ) ) {
				$sort_column = 'sum_time_ms';
			}

			if ( !$c->get_boolean( 'objectcache.debug' ) ) {
				include  W3TC_DIR . '/UsageStatistics_Page_View_NoDebugMode.php';
				return;
			}

			$reader = new UsageStatistics_Source_ObjectCacheLog( $timestamp_start,
				$sort_column );
			$items = $reader->list();

			$result = array(
				'date_min' =>
					Util_UsageStatistics::time_mins( $timestamp_start ),
				'date_max' => Util_UsageStatistics::time_mins( time() ),
				'sort_column' => $sort_column,
				'items' => $items
			);

			include  W3TC_DIR . '/UsageStatistics_Page_ObjectCacheLog_View.php';
		} elseif ( isset( $_REQUEST['view'] ) && $_REQUEST['view'] == 'pagecache_requests' ) {
			$storage = new UsageStatistics_StorageReader();
			$summary = $storage->get_history_summary();
			$timestamp_start = $summary['period']['timestamp_start'];

			$sort_column = isset( $_REQUEST['sort'] ) ? $_REQUEST['sort'] : '';
			if ( !in_array( $sort_column, array(
					'uri', 'count', 'avg_size', 'avg_time_ms',
					'sum_time_ms' ) ) ) {
				$sort_column = 'sum_time_ms';
			}

			if ( !$c->get_boolean( 'pgcache.debug' ) ) {
				include  W3TC_DIR . '/UsageStatistics_Page_View_NoDebugMode.php';
				return;
			}

			$reader = new UsageStatistics_Source_PageCacheLog( $timestamp_start,
				$_REQUEST['status'], $sort_column );
			$items = $reader->list();

			$result = array(
				'date_min' =>
					Util_UsageStatistics::time_mins( $timestamp_start ),
				'date_max' => Util_UsageStatistics::time_mins( time() ),
				'sort_column' => $sort_column,
				'items' => $items
			);

			include  W3TC_DIR . '/UsageStatistics_Page_PageCacheRequests_View.php';
		} else {
			$c = Dispatcher::config();

			$php_php_requests_pagecache_hit_name = 'Cache hit';
			if ( $c->get_boolean( 'pgcache.enabled' ) &&
				$c->get_string( 'pgcache.engine' ) == 'file_generic' ) {
				$php_php_requests_pagecache_hit_name = 'Cache fallback hit';
			}

			include  W3TC_DIR . '/UsageStatistics_Page_View.php';
		}
	}



	public function sort_link( $result, $name, $sort_column ) {
		$name_esc = esc_html( $name );
		if ( $result['sort_column'] == $sort_column ) {
			echo "<strong>$name_esc</strong>";
			return;
		}

		$new_query_string = $_GET;
		$new_query_string['sort'] = $sort_column;

		$url_esc = esc_url(
			'admin.php?' . http_build_query( $new_query_string ) );

		echo "<a href='$url_esc'>$name_esc</a>";
	}



	public function summary_item( $id, $name, $checked = false,
		$extra_class = '', $column_background = '', $link_key = '' ) {
		echo "<div class='ustats_$id $extra_class'>\n";
		echo '<label>';
		echo '<input type="checkbox" name="';
		echo esc_attr( 'w3tcus_chart_check_' . $id ) . '" ';
		echo 'data-name="' . esc_attr( $name ) . '" ';
		echo 'data-column="' . esc_attr( $id ) . '" ';

		if ( !empty( $column_background ) ) {
			echo 'data-background="' . esc_attr( $column_background ) . '" ';
		}

		echo 'class="w3tc-ignore-change w3tcus_chart_check" ';
		checked( $checked );
		echo ' />';
		if ( !empty( $link_key ) ) {
			echo "<a href='" .
				esc_url( 'admin.php?page=w3tc_stats&view=pagecache_requests&status=' .
					urlencode( $link_key ) . '&status_name=' . urlencode( $name ) ) .
				"'>$name</a>";
		} else {
			echo $name;
		}
		echo ": <span></span>\n";

		echo '</label>';
		echo '</div>';
	}

}
