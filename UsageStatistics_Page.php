<?php
/**
 * File: UsageStatistics_Page.php
 *
 * @package W3TC
 */

namespace W3TC;

/**
 * Class UsageStatistics_Page
 */
class UsageStatistics_Page {
	/**
	 * Enqueues the necessary CSS and JavaScript for the usage statistics page.
	 *
	 * This method ensures that the required CSS and JS files for displaying usage statistics
	 * are loaded. It checks if W3 Total Cache Pro is active and if statistics are enabled,
	 * loading additional scripts if needed.
	 *
	 * @return void
	 */
	public static function admin_print_scripts_w3tc_stats() {
		$config = Dispatcher::config();

		wp_enqueue_style( 'w3tc-widget-usage-statistics', plugins_url( 'UsageStatistics_Page_View.css', W3TC_FILE ), array(), W3TC_VERSION );

		if ( Util_Environment::is_w3tc_pro( $config ) && $config->get_boolean( 'stats.enabled' ) ) {
			wp_enqueue_script( 'w3tc-canvasjs', plugins_url( 'pub/js/chartjs.min.js', W3TC_FILE ), array(), W3TC_VERSION, false );
			wp_enqueue_script( 'w3tc-widget-usage-statistics', plugins_url( 'UsageStatistics_Page_View.js', W3TC_FILE ), array( 'w3tc-canvasjs' ), W3TC_VERSION, false );
		}
	}

	/**
	 * Renders the usage statistics page.
	 *
	 * This method checks if W3 Total Cache Pro is active and if statistics are enabled. Based on
	 * the request view (e.g., database requests, object cache requests, page cache requests), it
	 * loads the relevant statistics view and data.
	 *
	 * @return void
	 */
	public function render() {
		$c       = Dispatcher::config();
		$enabled = ( $c->get_boolean( 'stats.enabled' ) && Util_Environment::is_w3tc_pro( $c ) );
		if ( ! $enabled ) {
			if ( ! Util_Environment::is_w3tc_pro( $c ) ) {
				include W3TC_DIR . '/UsageStatistics_Page_View_Free.php';
			} else {
				include W3TC_DIR . '/UsageStatistics_Page_View_Disabled.php';
			}

			return;
		}

		$view_val = Util_Request::get_string( 'view' );
		if ( ! empty( $view_val ) && 'db_requests' === $view_val ) {
			$storage         = new UsageStatistics_StorageReader();
			$summary         = $storage->get_history_summary();
			$timestamp_start = $summary['period']['timestamp_start'];

			$sort_val    = Util_Request::get_string( 'sort' );
			$sort_column = ! empty( $sort_val ) ? $sort_val : '';
			if (
				! in_array(
					$sort_column,
					array(
						'query',
						'count_total',
						'count_hit',
						'avg_size',
						'avg_time_ms',
						'sum_time_ms',
					),
					true
				)
			) {
				$sort_column = 'sum_time_ms';
			}

			if ( ! $c->get_boolean( 'dbcache.debug' ) ) {
				include W3TC_DIR . '/UsageStatistics_Page_View_NoDebugMode.php';
				return;
			}

			$reader = new UsageStatistics_Source_DbQueriesLog( $timestamp_start, $sort_column );
			$items  = $reader->list_entries();

			$result = array(
				'date_min'    => Util_UsageStatistics::time_mins( $timestamp_start ),
				'date_max'    => Util_UsageStatistics::time_mins( time() ),
				'sort_column' => $sort_column,
				'items'       => $items,
			);

			include W3TC_DIR . '/UsageStatistics_Page_DbRequests_View.php';
		} elseif ( ! empty( $view_val ) && 'oc_requests' === $view_val ) {
			$storage         = new UsageStatistics_StorageReader();
			$summary         = $storage->get_history_summary();
			$timestamp_start = $summary['period']['timestamp_start'];

			$sort_val    = Util_Request::get_string( 'sort' );
			$sort_column = ! empty( $sort_val ) ? $sort_val : '';
			if (
				! in_array(
					$sort_column,
					array(
						'group',
						'count_total',
						'count_get_total',
						'count_get_hit',
						'count_set',
						'avg_size',
						'sum_size',
						'sum_time_ms',
					),
					true
				)
			) {
				$sort_column = 'sum_time_ms';
			}

			if ( ! $c->get_boolean( 'objectcache.debug' ) ) {
				include W3TC_DIR . '/UsageStatistics_Page_View_NoDebugMode.php';
				return;
			}

			$reader = new UsageStatistics_Source_ObjectCacheLog( $timestamp_start, $sort_column );
			$items  = $reader->list_entries();

			$result = array(
				'date_min'    => Util_UsageStatistics::time_mins( $timestamp_start ),
				'date_max'    => Util_UsageStatistics::time_mins( time() ),
				'sort_column' => $sort_column,
				'items'       => $items,
			);

			include W3TC_DIR . '/UsageStatistics_Page_ObjectCacheLog_View.php';
		} elseif ( ! empty( $view_val ) && 'pagecache_requests' === $view_val ) {
			$storage         = new UsageStatistics_StorageReader();
			$summary         = $storage->get_history_summary();
			$timestamp_start = $summary['period']['timestamp_start'];

			$sort_val    = Util_Request::get_string( 'sort' );
			$sort_column = ! empty( $sort_val ) ? $sort_val : '';
			if (
				! in_array(
					$sort_column,
					array(
						'uri',
						'count',
						'avg_size',
						'avg_time_ms',
						'sum_time_ms',
					),
					true
				)
			) {
				$sort_column = 'sum_time_ms';
			}

			if ( ! $c->get_boolean( 'pgcache.debug' ) ) {
				include W3TC_DIR . '/UsageStatistics_Page_View_NoDebugMode.php';
				return;
			}

			$reader = new UsageStatistics_Source_PageCacheLog(
				$timestamp_start,
				Util_Request::get_string( 'status' ),
				$sort_column
			);
			$items  = $reader->list_entries();

			$result = array(
				'date_min'    => Util_UsageStatistics::time_mins( $timestamp_start ),
				'date_max'    => Util_UsageStatistics::time_mins( time() ),
				'sort_column' => $sort_column,
				'items'       => $items,
			);

			include W3TC_DIR . '/UsageStatistics_Page_PageCacheRequests_View.php';
		} else {
			$c = Dispatcher::config();

			$php_php_requests_pagecache_hit_name = 'Cache hit';
			if ( $c->get_boolean( 'pgcache.enabled' ) && 'file_generic' === $c->get_string( 'pgcache.engine' ) ) {
				$php_php_requests_pagecache_hit_name = 'Cache fallback hit';
			}

			include W3TC_DIR . '/UsageStatistics_Page_View.php';
		}
	}

	/**
	 * Displays a sortable link for the statistics table.
	 *
	 * This method generates a sortable link for the statistics page based on the specified column.
	 * If the column is already the active sort column, it displays the name in bold. Otherwise, it
	 * creates a link to sort by the specified column.
	 *
	 * @param array  $result      The result array containing current sorting information.
	 * @param string $name        The name of the column to display.
	 * @param string $sort_column The column name to sort by.
	 *
	 * @return void
	 */
	public function sort_link( $result, $name, $sort_column ) {
		if ( $result['sort_column'] === $sort_column ) {
			echo '<strong>' . esc_html( $name ) . '</strong>';
			return;
		}

		$new_query_string         = $_GET; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$new_query_string['sort'] = sanitize_text_field( $sort_column );

		echo '<a href="' . esc_url( 'admin.php?' . http_build_query( $new_query_string ) ) . '">' . esc_html( $name ) . '</a>';
	}

	/**
	 * Displays a checkbox for a summary item on the usage statistics page.
	 *
	 * This method generates a checkbox input for a summary item, allowing the user to toggle
	 * visibility in the chart. It includes additional options like background color and optional
	 * linking to more detailed statistics.
	 *
	 * @param string $id                 The ID of the summary item.
	 * @param string $name               The name of the summary item.
	 * @param bool   $checked            Whether the checkbox is checked or not. Default is false.
	 * @param string $extra_class        Extra CSS classes to apply to the item.
	 * @param string $column_background  Background color for the column associated with the item.
	 * @param string $link_key           Optional link key for generating a clickable link.
	 *
	 * @return void
	 */
	public function summary_item( $id, $name, $checked = false, $extra_class = '', $column_background = '', $link_key = '' ) {
		echo '<div class="ustats_' . esc_attr( $id ) . ' ' . esc_attr( $extra_class ) . '"><br />';
		echo '<label>';
		echo '<input type="checkbox" name="' . esc_attr( 'w3tcus_chart_check_' . $id ) . '" data-name="' .
			esc_attr( $name ) . '" data-column="' . esc_attr( $id ) . '" ';

		if ( ! empty( $column_background ) ) {
			echo 'data-background="' . esc_attr( $column_background ) . '" ';
		}

		echo 'class="w3tc-ignore-change w3tcus_chart_check" ';
		checked( $checked );
		echo ' />';
		if ( ! empty( $link_key ) ) {
			$link_url = 'admin.php?page=w3tc_stats&view=pagecache_requests&status=' . rawurlencode( $link_key ) .
				'&status_name=' . rawurlencode( $name );
			echo '<a href="' . esc_url( $link_url ) . '">' . esc_html( $name ) . '</a>';
		} else {
			echo esc_html( $name );
		}
		echo ': <span></span><br />';

		echo '</label>';
		echo '</div>';
	}
}
