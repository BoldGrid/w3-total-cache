<?php
/**
 * File: Util_PageSpeed.php
 *
 * @package W3TC
 */

namespace W3TC;

/**
 * Google PageSpeed Utility Functions.
 */
class Util_PageSpeed {
	/**
	 * Get score guage angle.
	 *
	 * @param int $score PageSpeed desktop/mobile score.
	 *
	 * @return int
	 */
	public static function get_gauge_angle( $score ) {
		return ( isset( $score ) ? ( $score / 100 ) * 180 : 0 );
	}

	/**
	 * Get score guage color.
	 *
	 * @param int $score PageSpeed desktop/mobile score.
	 *
	 * @return string
	 */
	public static function get_gauge_color( $score ) {
		$color = '#fff';
		if ( isset( $score ) && is_numeric( $score ) ) {
			if ( $score >= 90 ) {
				$color = '#0c6';
			} elseif ( $score >= 50 && $score < 90 ) {
				$color = '#fa3';
			} elseif ( $score >= 0 && $score < 50 ) {
				$color = '#f33';
			}
		}
		return $color;
	}

	/**
	 * Render the PageSpeed desktop/mobile score guage.
	 *
	 * @param array  $w3tc_data PageSpeed desktop/mobile data containing score key.
	 * @param string $w3tc_icon Desktop/Mobile icon value.
	 *
	 * @return void
	 */
	public static function print_gauge( $w3tc_data, $w3tc_icon ) {
		if ( ! isset( $w3tc_data ) || empty( $w3tc_data['score'] ) || empty( $w3tc_icon ) ) {
			return;
		}

		$color = self::get_gauge_color( $w3tc_data['score'] );
		$angle = self::get_gauge_angle( $w3tc_data['score'] );

		?>
		<div class="gauge" style="width: 120px; --rotation:<?php echo esc_attr( $angle ); ?>deg; --color:<?php echo esc_attr( $color ); ?>; --background:#888;">
			<div class="percentage"></div>
			<div class="mask"></div>
			<span class="value">
				<span class="dashicons dashicons-<?php echo esc_attr( $w3tc_icon ); ?>"></span>
				<?php echo ( isset( $w3tc_data['score'] ) ? esc_html( $w3tc_data['score'] ) : '' ); ?>
			</span>
		</div>
		<?php
	}

	/**
	 * Render core metric bar-line.
	 *
	 * @param array $w3tc_metric PageSpeed desktop/mobile data containing score key.
	 *
	 * @return void
	 */
	public static function print_barline( $w3tc_metric ) {
		if ( empty( $w3tc_metric['score'] ) ) {
			return;
		}

		$w3tc_metric['score'] *= 100;

		$bar = '';

		if ( $w3tc_metric['score'] >= 90 ) {
			$bar = '<div style="flex-grow: ' . esc_attr( $w3tc_metric['score'] ) . '"><span class="w3tcps_range w3tcps_pass">' . esc_html( $w3tc_metric['displayValue'] ) . '</span></div>';
		} elseif ( $w3tc_metric['score'] >= 50 && $w3tc_metric['score'] < 90 ) {
			$bar = '<div style="flex-grow: ' . esc_attr( $w3tc_metric['score'] ) . '"><span class="w3tcps_range w3tcps_average">' . esc_html( $w3tc_metric['displayValue'] ) . '</span></div>';
		} elseif ( $w3tc_metric['score'] < 50 ) {
			$bar = '<div style="flex-grow: ' . esc_attr( $w3tc_metric['score'] ) . '"><span class="w3tcps_range w3tcps_fail">' . esc_html( $w3tc_metric['displayValue'] ) . '<span></div>';
		}

		echo wp_kses(
			'<div class="w3tcps_barline">' . $bar . '</div>',
			array(
				'div'  => array(
					'style' => array(),
					'class' => array(),
				),
				'span' => array(
					'class' => array(),
				),
			)
		);
	}

	/**
	 * Render core metric for desktop/mobile. Used by PageSpeed page.
	 *
	 * @param array  $w3tc_data PageSpeed data.
	 * @param string $w3tc_metric Metric key.
	 * @param string $w3tc_name Metric name.
	 * @param bool   $widget Widget flag to add line break between desktop/mobile metric.
	 *
	 * @return void
	 */
	public static function print_bar_combined_with_icon( $w3tc_data, $w3tc_metric, $w3tc_name, $widget = false ) {
		if ( ! isset( $w3tc_data ) || empty( $w3tc_metric ) || empty( $w3tc_name ) ) {
			return;
		}

		$widget_break = $widget ? '<br/>' : '';

		// A strategy that errored has no metric data; get_value_recursive() returns null for the missing
		// key and print_barline() bails on it, so a partial failure renders cleanly without notices.
		$desktop_metric = self::get_value_recursive( $w3tc_data, array( 'desktop', $w3tc_metric ) );
		$mobile_metric  = self::get_value_recursive( $w3tc_data, array( 'mobile', $w3tc_metric ) );

		?>
		<div class="w3tcps_metric">
			<h3 class="w3tcps_metric_title"><?php echo esc_html( $w3tc_name ); ?></h3>
			<div class="w3tcps_metric_stats">
				<span class="dashicons dashicons-<?php echo esc_attr( 'desktop' ); ?>"></span>
				<?php self::print_barline( $desktop_metric ); ?>
				<?php echo wp_kses( $widget_break, array( 'br' => array() ) ); ?>
				<span class="dashicons dashicons-<?php echo esc_attr( 'smartphone' ); ?>"></span>
				<?php self::print_barline( $mobile_metric ); ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Render core metric for desktop/mobile. Used by PageSpeed dashboard widget.
	 *
	 * @param array  $w3tc_data PageSpeed desktop/mobile data.
	 * @param string $w3tc_metric Metric key.
	 * @param string $w3tc_name Metric name.
	 *
	 * @return void
	 */
	public static function print_bar_single_no_icon( $w3tc_data, $w3tc_metric, $w3tc_name ) {
		if ( ! isset( $w3tc_data ) || empty( $w3tc_data[ $w3tc_metric ] ) || empty( $w3tc_metric ) || empty( $w3tc_name ) ) {
			return;
		}

		?>
		<div class="w3tcps_metric">
			<h3 class="w3tcps_metric_title"><?php echo esc_html( $w3tc_name ); ?></h3>
			<div class="w3tcps_metric_stats">
				<?php self::print_barline( $w3tc_data[ $w3tc_metric ] ); ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Get PageSpeed metric notice BG.
	 *
	 * @param int    $score        PageSpeed desktop/mobile score.
	 * @param string $display_mode PageSpeed desktop/mobile score display mode.
	 *
	 * @return string
	 */
	public static function get_breakdown_bg( $score, $display_mode ) {
		$notice = 'notice notice-info inline';
		if ( isset( $display_mode ) && in_array( $display_mode, array( 'metric', 'metricSavings' ), true ) && isset( $score ) && is_numeric( $score ) ) {
			if ( $score >= 90 ) {
				$notice = 'notice notice-success inline';
			} elseif ( $score >= 50 && $score < 90 ) {
				$noitce = 'notice notice-warning inline';
			} elseif ( $score >= 0 && $score < 50 ) {
				$notice = 'notice notice-error inline';
			}
		}
		return $notice;
	}

	/**
	 * Get PageSpeed metric grade.
	 *
	 * @param int    $score        PageSpeed desktop/mobile score.
	 * @param string $display_mode PageSpeed desktop/mobile score display mode.
	 *
	 * @return string
	 */
	public static function get_breakdown_grade( $score, $display_mode ) {
		$grade = 'w3tcps_blank';
		if ( isset( $display_mode ) && in_array( $display_mode, array( 'metric', 'metricSavings' ), true ) && isset( $score ) && is_numeric( $score ) ) {
			if ( $score >= 90 ) {
				$grade = 'w3tcps_pass';
			} elseif ( $score >= 50 && $score < 90 ) {
				$grade = 'w3tcps_average';
			} elseif ( $score >= 0 && $score < 50 ) {
				$grade = 'w3tcps_fail';
			}
		}
		return $grade;
	}

	/**
	 * Render the final generated screenshot.
	 *
	 * @param array $w3tc_data PageSpeed data.
	 *
	 * @return void
	 */
	public static function print_final_screenshot( $w3tc_data ) {
		if ( isset( $w3tc_data ) && isset( $w3tc_data['screenshots']['final']['screenshot'] ) ) {
			echo '<img src="' . esc_attr( $w3tc_data['screenshots']['final']['screenshot'] ) . '" alt="' . ( isset( $w3tc_data['screenshots']['final']['title'] ) ? esc_attr( $w3tc_data['screenshots']['final']['title'] ) : esc_attr__( 'Final Screenshot', 'w3-total-cache' ) ) . '"/>';
		}
	}

	/**
	 * Render all "building" screenshots.
	 *
	 * @param mixed $w3tc_data PageSpeed desktop/mobile score.
	 *
	 * @return void
	 */
	public static function print_screenshots( $w3tc_data ) {
		if ( isset( $w3tc_data ) && isset( $w3tc_data['screenshots']['other']['screenshots'] ) ) {
			foreach ( $w3tc_data['screenshots']['other']['screenshots'] as $screenshot ) {
				echo '<img src="' . esc_attr( $screenshot['data'] ) . '" alt="' . ( isset( $w3tc_data['screenshots']['other']['title'] ) ? esc_attr( $w3tc_data['screenshots']['other']['title'] ) : esc_attr__( 'Other Screenshot', 'w3-total-cache' ) ) . '"/>';
			}
		}
	}

	/**
	 * Render all metric data into listable items.
	 *
	 * @param array $w3tc_data PageSpeed desktop/mobile score.
	 *
	 * @return void
	 */
	public static function print_breakdown( $w3tc_data ) {
		if ( ! isset( $w3tc_data ) || ( empty( $w3tc_data['insights'] ) && empty( $w3tc_data['diagnostics'] ) ) ) {
			return;
		}

		$insights      = '';
		$diagnostics   = '';
		$passed_audits = '';

		foreach ( $w3tc_data['insights'] as $insight ) {
			$insight['score'] *= 100;
			$insight_id        = $insight['id'] ?? '';

			$notice = 'notice notice-info inline';
			$grade  = 'w3tcps_blank';
			if ( isset( $insight['score'] ) ) {
				$notice = self::get_breakdown_bg( $insight['score'], $insight['scoreDisplayMode'] );
				$grade  = self::get_breakdown_grade( $insight['score'], $insight['scoreDisplayMode'] );
			}

			$audit_classes = '';
			if ( isset( $insight['type'] ) && \is_array( $insight['type'] ) ) {
				foreach ( $insight['type'] as $type ) {
					$audit_classes .= ' ' . $type;
				}
			}

			$insight['description'] = preg_replace( '/(.*?)(\[.*?\])\((.*?)\)(.*?[,.?!]*)/', '$1<a href="$3">$2</a>$4', esc_html( $insight['description'] ) );

			if ( isset( $insight['networkDependency'] ) && ! empty( $insight['networkDependency'] ) ) {
				$insights .= self::render_network_dependency_audit( $insight, $notice, $grade, $audit_classes );
				continue;
			}

			$headers = '';
			$items   = '';

			$insight['details'] = $insight['details'] ?? array();
			foreach ( $insight['details'] as $w3tc_item ) {
				$headers = '';
				$items  .= '<tr class="w3tcps_passed_audit_item">';
				if ( isset( $w3tc_item['url'] ) ) {
					$headers .= '<th>' . esc_html__( 'URL', 'w3-total-cache' ) . '</th>';
					if ( filter_var( $w3tc_item['url'], FILTER_VALIDATE_URL ) !== false ) {
						// The value is confirmed as a valid URL. We create a HTML link with the full URL value but display it with a trucated value.
						$items .= '<td><span class="copyurl dashicons dashicons-admin-page" title="' . esc_attr__( 'Copy Full URL', 'w3-total-cache' ) . '" copyurl="' . esc_url( $w3tc_item['url'] ) . '"></span><a href="' . esc_url( $w3tc_item['url'] ) . '" target="_blank" title="' . esc_url( $w3tc_item['url'] ) . '"> ' . esc_url( $w3tc_item['url'] ) . '</a></td>';
					} else {
						// For certain metrics Google uses the 'url' field for non-URL values. These are often HTML/CSS that shouldn't be escaped and will be displayed as plain text.
						$items .= '<td>' . esc_html( $w3tc_item['url'] ) . '</td>';
					}
				}
				if ( isset( $w3tc_item['source'] ) ) {
					$headers .= '<th>' . esc_html__( 'URL', 'w3-total-cache' ) . '</th>';
					if ( filter_var( $w3tc_item['source']['url'], FILTER_VALIDATE_URL ) !== false ) {
						// The value is confirmed as a valid URL. We create a HTML link with the full URL value but display it with a trucated value.
						$items .= '<td><span class="copyurl dashicons dashicons-admin-page" title="' . esc_attr__( 'Copy Full URL', 'w3-total-cache' ) . '" copyurl="' . esc_url( $w3tc_item['source']['url'] ) . '"></span><a href="' . esc_url( $w3tc_item['source']['url'] ) . '" target="_blank" title="' . esc_url( $w3tc_item['source']['url'] ) . '"> ' . esc_url( $w3tc_item['url'] ) . '</a></td>';
					} else {
						// For certain metrics Google uses the 'url' field for non-URL values. These are often HTML/CSS that shouldn't be escaped and will be displayed as plain text.
						$items .= '<td>' . esc_html( $w3tc_item['source']['url'] ) . '</td>';
					}
				}
				if ( isset( $w3tc_item['totalBytes'] ) ) {
					$headers .= '<th>' . esc_html__( 'Total Bytes', 'w3-total-cache' ) . '</th>';
					$items   .= '<td>' . esc_html( $w3tc_item['totalBytes'] ) . '</td>';
				}
				if ( isset( $w3tc_item['wastedBytes'] ) ) {
					$headers .= '<th>' . esc_html__( 'Wasted Bytes', 'w3-total-cache' ) . '</th>';
					$items   .= '<td>' . esc_html( $w3tc_item['wastedBytes'] ) . '</td>';
				}
				if ( isset( $w3tc_item['wastedPercent'] ) ) {
					$headers .= '<th>' . esc_html__( 'Wasted Percentage', 'w3-total-cache' ) . '</th>';
					$items   .= '<td>' . round( $w3tc_item['wastedPercent'], 2 ) . '%</td>';
				}
				if ( isset( $w3tc_item['wastedMs'] ) ) {
					$headers .= '<th>' . esc_html__( 'Wasted Miliseconds', 'w3-total-cache' ) . '</th>';
					$items   .= '<td>' . round( $w3tc_item['wastedMs'], 2 ) . '</td>';
				}
				if ( isset( $w3tc_item['label'] ) ) {
					$w3tc_icon = '';

					if ( isset( $w3tc_item['value'] ) ) {
						if ( true === $w3tc_item['value'] || 1 === $w3tc_item['value'] ) {
							$w3tc_icon = '<span class="dashicons dashicons-yes"></span>';
						} elseif ( false === $w3tc_item['value'] || 0 === $w3tc_item['value'] ) {
							$w3tc_icon = '<span class="dashicons dashicons-no"></span>';
						}
					}

					$headers .= '<th>' . esc_html__( 'Type', 'w3-total-cache' ) . '</th>';
					$items   .= '<td>' . $w3tc_icon . esc_html( $w3tc_item['label'] ) . '</td>';
				}
				if ( isset( $w3tc_item['groupLabel'] ) ) {
					$headers .= '<th>' . esc_html__( 'Group', 'w3-total-cache' ) . '</th>';
					$items   .= '<td>' . esc_html( $w3tc_item['groupLabel'] ) . '</td>';
				}
				if ( isset( $w3tc_item['requestCount'] ) ) {
					$headers .= '<th>' . esc_html__( 'Requests', 'w3-total-cache' ) . '</th>';
					$items   .= '<td>' . esc_html( $w3tc_item['requestCount'] ) . '</td>';
				}
				if ( isset( $w3tc_item['transferSize'] ) ) {
					$headers .= '<th>' . esc_html__( 'Transfer Size', 'w3-total-cache' ) . '</th>';
					$items   .= '<td>' . esc_html( $w3tc_item['transferSize'] ) . '</td>';
				}
				if ( isset( $w3tc_item['startTime'] ) ) {
					$headers .= '<th>' . esc_html__( 'Start Time', 'w3-total-cache' ) . '</th>';
					$items   .= '<td>' . esc_html( $w3tc_item['startTime'] ) . '</td>';
				}
				if ( isset( $w3tc_item['duration'] ) ) {
					$headers .= '<th>' . esc_html__( 'Duration', 'w3-total-cache' ) . '</th>';
					$items   .= '<td>' . esc_html( $w3tc_item['duration'] ) . '</td>';
				}
				if ( isset( $w3tc_item['scriptParseCompile'] ) ) {
					$headers .= '<th>' . esc_html__( 'Parse/Compile Time', 'w3-total-cache' ) . '</th>';
					$items   .= '<td>' . esc_html( $w3tc_item['scriptParseCompile'] ) . '</td>';
				}
				if ( isset( $w3tc_item['scripting'] ) ) {
					$headers .= '<th>' . esc_html__( 'Execution Time', 'w3-total-cache' ) . '</th>';
					$items   .= '<td>' . esc_html( $w3tc_item['scripting'] ) . '</td>';
				}
				if ( isset( $w3tc_item['total'] ) ) {
					$headers .= '<th>' . esc_html__( 'Total', 'w3-total-cache' ) . '</th>';
					$items   .= '<td>' . esc_html( $w3tc_item['total'] ) . '</td>';
				}
				if ( isset( $w3tc_item['cacheLifetimeMs'] ) ) {
					$headers .= '<th>' . esc_html__( 'Cache Lifetime Miliseconds', 'w3-total-cache' ) . '</th>';
					$items   .= '<td>' . esc_html( $w3tc_item['cacheLifetimeMs'] ) . '</td>';
				}
				if ( isset( $w3tc_item['cacheHitProbability'] ) ) {
					$headers .= '<th>' . esc_html__( 'Cache Hit Probability', 'w3-total-cache' ) . '</th>';
					$items   .= '<td>' . esc_html( $w3tc_item['cacheHitProbability'] ) . '</td>';
				}
				if ( isset( $w3tc_item['value'] ) && isset( $w3tc_item['statistic'] ) ) {
					$headers .= '<th>' . esc_html__( 'Statistic', 'w3-total-cache' ) . '</th>';
					$items   .= '<td>' . esc_html( $w3tc_item['statistic'] ) . '</td>';

					$headers .= '<th>' . esc_html__( 'Element', 'w3-total-cache' ) . '</th>';
					$items   .= '<td>';
					if ( isset( $w3tc_item['node'] ) ) {
						$items .= '<p><b>' . __( 'Snippet', 'w3-total-cache' ) . ': </b>' . esc_html( $w3tc_item['node']['snippet'] ) . '</p>';
						$items .= '<p><b>' . __( 'Selector', 'w3-total-cache' ) . ': </b>' . esc_html( $w3tc_item['node']['selector'] ) . '</p>';
					}
					$items .= '</td>';

					$headers .= '<th>' . esc_html__( 'Value', 'w3-total-cache' ) . '</th>';
					$items   .= is_array( $w3tc_item['value'] ) ? '<td>' . esc_html( $w3tc_item['value']['value'] ) . '</td>' : '<td>' . esc_html( $w3tc_item['value'] ) . '</td>';
				} elseif ( isset( $w3tc_item['node'] ) ) {
					$headers .= '<th>' . esc_html__( 'Element', 'w3-total-cache' ) . '</th>';
					$items   .= '<td>';
					$items   .= '<p><b>' . __( 'Snippet', 'w3-total-cache' ) . ': </b>' . esc_html( $w3tc_item['node']['snippet'] ) . '</p>';
					$items   .= '<p><b>' . __( 'Selector', 'w3-total-cache' ) . ': </b>' . esc_html( $w3tc_item['node']['selector'] ) . '</p>';
					$items   .= '</td>';
				}
				if ( isset( $w3tc_item['headings'] ) && isset( $w3tc_item['items'] ) ) {
					$items .= self::render_subitems_table_cell( $w3tc_item['headings'], $w3tc_item['items'] );
				}
				if ( isset( $w3tc_item['responseTime'] ) ) {
					$headers .= '<th>' . esc_html__( 'Response Time', 'w3-total-cache' ) . '</th>';
					$items   .= '<td>' . esc_html( $w3tc_item['responseTime'] ) . '</td>';
				}

				self::append_document_latency_status( $insight_id, $w3tc_item, $headers, $items );

				$items .= '</tr>';
			}

			$items = ! empty( $items ) ? $items : '<p class="w3tcps-no-items">' . esc_html__( 'No identified items were provided by Google PageSpeed Insights API for this metric', 'w3-total-cache' ) . '</p>';

			if ( $insight['score'] >= 90 || in_array( $insight['scoreDisplayMode'], array( 'notApplicable' ), true ) ) {
				$passed_audits .= '
					<div class="audits w3tcps_passed_audit' . esc_attr( $audit_classes ) . ' ' . esc_attr( $notice ) . '">
						<span class="w3tcps_breakdown_items_toggle w3tcps_range ' . esc_attr( $grade ) . '" gatitle="' . esc_attr( $insight['title'] ) . '">' . esc_html( $insight['title'] ) . ( isset( $insight['displayValue'] ) ? ' - ' . esc_html( $insight['displayValue'] ) : '' ) . '<span class="dashicons dashicons-arrow-down-alt2"></span></span>
						<div class="w3tcps_breakdown_items w3tcps_pass_audit_items">
							<p class="w3tcps_item_desciption">' . $insight['description'] . '</p>
							<div class="w3tcps_breakdown_items_container">
								<table class="w3tcps_breakdown_items_table">
									<tr class="w3tcps_passed_audit_item_header">' . $headers . '</tr>' . $items . '
								</table>
								<div class="w3tcps_instruction">
									<div class="w3tc_fancy_header">
										<img class="w3tc_fancy_icon" src="' . esc_url( plugins_url( '/w3-total-cache/pub/img/w3tc_cube-shadow.png' ) ) . '" />
										<div class="w3tc_fancy_title">
											<span>Total Cache</span>
											<span>:</span>
											<span>' . esc_html__( 'Tips', 'w3-total-cache' ) . '</span>
										</div>
									</div>
									<div class="w3tc_instruction_copy">' . $insight['instructions'] . '</div>
								</div>
							</div>
						</div>
					</div>';
			} else {
				$insights .= '
					<div class="audits w3tcps_insights' . esc_attr( $audit_classes ) . ' ' . esc_attr( $notice ) . '">
						<span class="w3tcps_breakdown_items_toggle w3tcps_range ' . esc_attr( $grade ) . '" gatitle="' . esc_attr( $insight['title'] ) . '">' . esc_html( $insight['title'] ) . ( isset( $insight['displayValue'] ) ? ' - ' . esc_html( $insight['displayValue'] ) : '' ) . '<span class="dashicons dashicons-arrow-down-alt2"></span></span>
						<div class="w3tcps_breakdown_items w3tcps_opportunity_items">
							<p class="w3tcps_item_desciption">' . $insight['description'] . '</p>
							<div class="w3tcps_breakdown_items_container">
								<table class="w3tcps_breakdown_items_table">
									<tr class="w3tcps_passed_audit_item_header">' . $headers . '</tr>' . $items . '
								</table>
								<div class="w3tcps_instruction">
									<div class="w3tc_fancy_header">
										<img class="w3tc_fancy_icon" src="' . esc_url( plugins_url( '/w3-total-cache/pub/img/w3tc_cube-shadow.png' ) ) . '" />
										<div class="w3tc_fancy_title">
											<span>Total Cache</span>
											<span>:</span>
											<span>' . esc_html__( 'Tips', 'w3-total-cache' ) . '</span>
										</div>
									</div>
									<div class="w3tc_instruction_copy">' . $insight['instructions'] . '</div>
								</div>
							</div>
						</div>
					</div>';
			}
		}

		foreach ( $w3tc_data['diagnostics'] as $diagnostic ) {
			$diagnostic['score'] *= 100;
			$diagnostic_id        = $diagnostic['id'] ?? '';

			$notice = 'notice notice-info inline';
			$grade  = 'w3tcps_blank';
			if ( isset( $diagnostic['score'] ) ) {
				$notice = self::get_breakdown_bg( $diagnostic['score'], $diagnostic['scoreDisplayMode'] );
				$grade  = self::get_breakdown_grade( $diagnostic['score'], $diagnostic['scoreDisplayMode'] );
			}

			$audit_classes = '';
			if ( isset( $diagnostic['type'] ) && \is_array( $diagnostic['type'] ) ) {
				foreach ( $diagnostic['type'] as $type ) {
					$audit_classes .= ' ' . $type;
				}
			}

			$diagnostic['description'] = preg_replace( '/(.*?)(\[.*?\])\((.*?)\)(.*?[,.?!]*)/', '$1<a href="$3">$2</a>$4', esc_html( $diagnostic['description'] ) );

			$headers = '';
			$items   = '';

			$diagnostic['details'] = $diagnostic['details'] ?? array();
			foreach ( $diagnostic['details'] as $w3tc_item ) {
				$headers = '';
				$items  .= '<tr class="w3tcps_passed_audit_item">';
				if ( isset( $w3tc_item['url'] ) ) {
					$headers .= '<th>' . esc_html__( 'URL', 'w3-total-cache' ) . '</th>';
					if ( filter_var( $w3tc_item['url'], FILTER_VALIDATE_URL ) !== false ) {
						// The value is confirmed as a valid URL. We create a HTML link with the full URL value but display it with a trucated value.
						$items .= '<td><span class="copyurl dashicons dashicons-admin-page" title="' . esc_attr__( 'Copy Full URL', 'w3-total-cache' ) . '" copyurl="' . esc_url( $w3tc_item['url'] ) . '"></span><a href="' . esc_url( $w3tc_item['url'] ) . '" target="_blank" title="' . esc_url( $w3tc_item['url'] ) . '">' . esc_url( $w3tc_item['url'] ) . '</a></td>';
					} else {
						// For certain metrics Google uses the 'url' field for non-URL values. These are often HTML/CSS that shouldn't be escaped and will be displayed as plain text.
						$items .= '<td>' . esc_html( $w3tc_item['url'] ) . '</td>';
					}
				}
				if ( isset( $w3tc_item['source'] ) ) {
					$headers .= '<th>' . esc_html__( 'URL', 'w3-total-cache' ) . '</th>';
					if ( filter_var( $w3tc_item['source']['url'], FILTER_VALIDATE_URL ) !== false ) {
						// The value is confirmed as a valid URL. We create a HTML link with the full URL value but display it with a trucated value.
						$items .= '<td><span class="copyurl dashicons dashicons-admin-page" title="' . esc_attr__( 'Copy Full URL', 'w3-total-cache' ) . '" copyurl="' . esc_url( $w3tc_item['source']['url'] ) . '"></span><a href="' . esc_url( $w3tc_item['source']['url'] ) . '" target="_blank" title="' . esc_url( $w3tc_item['source']['url'] ) . '">' . esc_url( $w3tc_item['url'] ) . '</a></td>';
					} else {
						// For certain metrics Google uses the 'url' field for non-URL values. These are often HTML/CSS that shouldn't be escaped and will be displayed as plain text.
						$items .= '<td>' . esc_html( $w3tc_item['source']['url'] ) . '</td>';
					}
				}
				if ( isset( $w3tc_item['totalBytes'] ) ) {
					$headers .= '<th>' . esc_html__( 'Total Bytes', 'w3-total-cache' ) . '</th>';
					$items   .= '<td>' . esc_html( $w3tc_item['totalBytes'] ) . '</td>';
				}
				if ( isset( $w3tc_item['wastedBytes'] ) ) {
					$headers .= '<th>' . esc_html__( 'Wasted Bytes', 'w3-total-cache' ) . '</th>';
					$items   .= '<td>' . esc_html( $w3tc_item['wastedBytes'] ) . '</td>';
				}
				if ( isset( $w3tc_item['wastedPercent'] ) ) {
					$headers .= '<th>' . esc_html__( 'Wasted Percentage', 'w3-total-cache' ) . '</th>';
					$items   .= '<td>' . round( $w3tc_item['wastedPercent'], 2 ) . '%</td>';
				}
				if ( isset( $w3tc_item['wastedMs'] ) ) {
					$headers .= '<th>' . esc_html__( 'Wasted Miliseconds', 'w3-total-cache' ) . '</th>';
					$items   .= '<td>' . round( $w3tc_item['wastedMs'], 2 ) . '</td>';
				}
				if ( isset( $w3tc_item['label'] ) ) {
					$headers .= '<th>' . esc_html__( 'Type', 'w3-total-cache' ) . '</th>';
					$items   .= '<td>' . esc_html( $w3tc_item['label'] ) . '</td>';
				}
				if ( isset( $w3tc_item['groupLabel'] ) ) {
					$headers .= '<th>' . esc_html__( 'Group', 'w3-total-cache' ) . '</th>';
					$items   .= '<td>' . esc_html( $w3tc_item['groupLabel'] ) . '</td>';
				}
				if ( isset( $w3tc_item['requestCount'] ) ) {
					$headers .= '<th>' . esc_html__( 'Requests', 'w3-total-cache' ) . '</th>';
					$items   .= '<td>' . esc_html( $w3tc_item['requestCount'] ) . '</td>';
				}
				if ( isset( $w3tc_item['transferSize'] ) ) {
					$headers .= '<th>' . esc_html__( 'Transfer Size', 'w3-total-cache' ) . '</th>';
					$items   .= '<td>' . esc_html( $w3tc_item['transferSize'] ) . '</td>';
				}
				if ( isset( $w3tc_item['startTime'] ) ) {
					$headers .= '<th>' . esc_html__( 'Start Time', 'w3-total-cache' ) . '</th>';
					$items   .= '<td>' . esc_html( $w3tc_item['startTime'] ) . '</td>';
				}
				if ( isset( $w3tc_item['duration'] ) ) {
					$headers .= '<th>' . esc_html__( 'Duration', 'w3-total-cache' ) . '</th>';
					$items   .= '<td>' . esc_html( $w3tc_item['duration'] ) . '</td>';
				}
				if ( isset( $w3tc_item['scriptParseCompile'] ) ) {
					$headers .= '<th>' . esc_html__( 'Parse/Compile Time', 'w3-total-cache' ) . '</th>';
					$items   .= '<td>' . esc_html( $w3tc_item['scriptParseCompile'] ) . '</td>';
				}
				if ( isset( $w3tc_item['scripting'] ) ) {
					$headers .= '<th>' . esc_html__( 'Execution Time', 'w3-total-cache' ) . '</th>';
					$items   .= '<td>' . esc_html( $w3tc_item['scripting'] ) . '</td>';
				}
				if ( isset( $w3tc_item['total'] ) ) {
					$headers .= '<th>' . esc_html__( 'Total', 'w3-total-cache' ) . '</th>';
					$items   .= '<td>' . esc_html( $w3tc_item['total'] ) . '</td>';
				}
				if ( isset( $w3tc_item['cacheLifetimeMs'] ) ) {
					$headers .= '<th>' . esc_html__( 'Cache Lifetime Miliseconds', 'w3-total-cache' ) . '</th>';
					$items   .= '<td>' . esc_html( $w3tc_item['cacheLifetimeMs'] ) . '</td>';
				}
				if ( isset( $w3tc_item['cacheHitProbability'] ) ) {
					$headers .= '<th>' . esc_html__( 'Cache Hit Probability', 'w3-total-cache' ) . '</th>';
					$items   .= '<td>' . ( esc_html( $w3tc_item['cacheHitProbability'] ) * 100 ) . '%</td>';
				}
				if ( isset( $w3tc_item['value'] ) && isset( $w3tc_item['statistic'] ) ) {
					$headers .= '<th>' . esc_html__( 'Statistic', 'w3-total-cache' ) . '</th>';
					$items   .= '<td>' . esc_html( $w3tc_item['statistic'] ) . '</td>';

					$headers .= '<th>' . esc_html__( 'Element', 'w3-total-cache' ) . '</th>';
					$items   .= '<td>';
					if ( isset( $w3tc_item['node'] ) ) {
						$items .= '<p><b>' . __( 'Snippet', 'w3-total-cache' ) . ': </b>' . esc_html( $w3tc_item['node']['snippet'] ) . '</p>';
						$items .= '<p><b>' . __( 'Selector', 'w3-total-cache' ) . ': </b>' . esc_html( $w3tc_item['node']['selector'] ) . '</p>';
					}
					$items .= '</td>';

					$headers .= '<th>' . esc_html__( 'Value', 'w3-total-cache' ) . '</th>';
					$items   .= is_array( $w3tc_item['value'] ) ? '<td>' . esc_html( $w3tc_item['value']['value'] ) . '</td>' : '<td>' . esc_html( $w3tc_item['value'] ) . '</td>';
				} elseif ( isset( $w3tc_item['node'] ) ) {
					$headers .= '<th>' . esc_html__( 'Element', 'w3-total-cache' ) . '</th>';
					$items   .= '<td>';
					$items   .= '<p><b>' . __( 'Snippet', 'w3-total-cache' ) . ': </b>' . esc_html( $w3tc_item['node']['snippet'] ) . '</p>';
					$items   .= '<p><b>' . __( 'Selector', 'w3-total-cache' ) . ': </b>' . esc_html( $w3tc_item['node']['selector'] ) . '</p>';
					$items   .= '</td>';
				}
				if ( isset( $w3tc_item['headings'] ) && isset( $w3tc_item['items'] ) ) {
					$items .= self::render_subitems_table_cell( $w3tc_item['headings'], $w3tc_item['items'] );
				}
				if ( isset( $w3tc_item['responseTime'] ) ) {
					$headers .= '<th>' . esc_html__( 'Response Time', 'w3-total-cache' ) . '</th>';
					$items   .= '<td>' . esc_html( $w3tc_item['responseTime'] ) . '</td>';
				}

				self::append_document_latency_status( $diagnostic_id, $w3tc_item, $headers, $items );

				$items .= '</tr>';
			}

			$items = ! empty( $items ) ? $items : '<p class="w3tcps-no-items">' . esc_html__( 'No identified items were provided by Google PageSpeed Insights API for this metric', 'w3-total-cache' ) . '</p>';

			if ( $diagnostic['score'] >= 90 || in_array( $diagnostic['scoreDisplayMode'], array( 'notApplicable' ), true ) ) {
				$passed_audits .= '
					<div class="audits w3tcps_passed_audit' . esc_attr( $audit_classes ) . ' ' . esc_attr( $notice ) . '">
						<span class="w3tcps_breakdown_items_toggle w3tcps_range ' . esc_attr( $grade ) . '" gatitle="' . esc_attr( $diagnostic['title'] ) . '">' . esc_html( $diagnostic['title'] ) . ( isset( $diagnostic['displayValue'] ) ? ' - ' . esc_html( $diagnostic['displayValue'] ) : '' ) . '<span class="dashicons dashicons-arrow-down-alt2"></span></span>
						<div class="w3tcps_breakdown_items w3tcps_pass_audit_items">
							<p class="w3tcps_item_desciption">' . $diagnostic['description'] . '</p>
							<div class="w3tcps_breakdown_items_container">
								<table class="w3tcps_breakdown_items_table">
									<tr class="w3tcps_passed_audit_item_header">' . $headers . '</tr>' . $items . '
								</table>
								<div class="w3tcps_instruction">
									<div class="w3tc_fancy_header">
										<img class="w3tc_fancy_icon" src="' . esc_url( plugins_url( '/w3-total-cache/pub/img/w3tc_cube-shadow.png' ) ) . '" />
										<div class="w3tc_fancy_title">
											<span>Total Cache</span>
											<span>:</span>
											<span>' . esc_html__( 'Tips', 'w3-total-cache' ) . '</span>
										</div>
									</div>
									<div class="w3tc_instruction_copy">' . $diagnostic['instructions'] . '</div>
								</div>
							</div>
						</div>
					</div>';
			} else {
				$diagnostics .= '
					<div class="audits w3tcps_diagnostics' . esc_attr( $audit_classes ) . ' ' . esc_attr( $notice ) . '">
						<span class="w3tcps_breakdown_items_toggle w3tcps_range ' . esc_attr( $grade ) . '" gatitle="' . esc_attr( $diagnostic['title'] ) . '">' . esc_html( $diagnostic['title'] ) . ( isset( $diagnostic['displayValue'] ) ? ' - ' . esc_html( $diagnostic['displayValue'] ) : '' ) . '<span class="dashicons dashicons-arrow-down-alt2"></span></span>
						<div class="w3tcps_breakdown_items w3tcps_diagnostic_items">
							<p class="w3tcps_item_desciption">' . $diagnostic['description'] . '</p>
							<div class="w3tcps_breakdown_items_container">
								<table class="w3tcps_breakdown_items_table">
									<tr class="w3tcps_passed_audit_item_header">' . $headers . '</tr>' . $items . '
								</table>
								<div class="w3tcps_instruction">
									<div class="w3tc_fancy_header">
										<img class="w3tc_fancy_icon" src="' . esc_url( plugins_url( '/w3-total-cache/pub/img/w3tc_cube-shadow.png' ) ) . '" />
										<div class="w3tc_fancy_title">
											<span>Total Cache</span>
											<span>:</span>
											<span>' . esc_html__( 'Tips', 'w3-total-cache' ) . '</span>
										</div>
									</div>
									<div class="w3tc_instruction_copy">' . $diagnostic['instructions'] . '</div>
								</div>
							</div>
						</div>
					</div>';
			}
		}

		$allowed_tags = self::get_allowed_tags();

		echo wp_kses(
			'<div class="w3tcps_audit_results">
				<div class="insights"><h3 class="w3tcps_metric_title">' . esc_html__( 'Insights', 'w3-total-cache' ) . '</h3>' . $insights . '</div>
				<div class="diagnostics"><h3 class="w3tcps_metric_title">' . esc_html__( 'Diagnostics', 'w3-total-cache' ) . '</h3>' . $diagnostics . '</div>
				<div class="passed_audits"><h3 class="w3tcps_metric_title">' . esc_html__( 'Passed Audits', 'w3-total-cache' ) . '</h3>' . $passed_audits . '</div>
			</div>',
			$allowed_tags
		);
	}

	/**
	 * Render the specialized Network Dependency Tree insight.
	 *
	 * @since 2.10.0
	 *
	 * @param array  $insight       Insight payload.
	 * @param string $notice_class  Notice classes.
	 * @param string $grade_class   Grade classes.
	 * @param string $audit_classes Filter classes.
	 *
	 * @return string
	 */
	private static function render_network_dependency_audit( $insight, $notice_class, $grade_class, $audit_classes ) {
		$dependency   = $insight['networkDependency'];
		$title_markup = esc_html( $insight['title'] ) . ( isset( $insight['displayValue'] ) ? ' - ' . esc_html( $insight['displayValue'] ) : '' );
		$meta_text    = '';

		if ( ! empty( $dependency['longestChainDuration'] ) ) {
			$meta_text = '<p class="w3tcps_network_meta">' .
				sprintf(
					// Translators: 1 Longest chain duration.
					esc_html__( 'Longest chain duration: %1$d ms', 'w3-total-cache' ),
					(int) $dependency['longestChainDuration']
				) .
				'</p>';
		}

		$chains_html       = self::render_network_chain_list( $dependency['chains'] ?? array() );
		$preconnected_html = self::render_preconnect_section_markup( $dependency['preconnected'] ?? array(), esc_html__( 'Preconnected origins', 'w3-total-cache' ) );
		$candidates_html   = self::render_preconnect_section_markup( $dependency['candidates'] ?? array(), esc_html__( 'Preconnect candidates', 'w3-total-cache' ) );

		$sections  = '<div class="w3tcps_network_dependency">';
		$sections .= '<h4>' . esc_html__( 'Critical request chains', 'w3-total-cache' ) . '</h4>';
		$sections .= $meta_text;
		$sections .= $chains_html;
		if ( ! empty( $preconnected_html ) || ! empty( $candidates_html ) ) {
			$sections .= '<div class="w3tcps_network_preconnect_sections">';
			$sections .= $preconnected_html;
			$sections .= $candidates_html;
			$sections .= '</div>';
		}
		$sections .= '</div>';

		return '
			<div class="audits w3tcps_insights' . esc_attr( $audit_classes ) . ' ' . esc_attr( $notice_class ) . '">
				<span class="w3tcps_breakdown_items_toggle w3tcps_range ' . esc_attr( $grade_class ) . '" gatitle="' . esc_attr( $insight['title'] ) . '">' . $title_markup . '<span class="dashicons dashicons-arrow-down-alt2"></span></span>
				<div class="w3tcps_breakdown_items w3tcps_opportunity_items">
					<p class="w3tcps_item_desciption">' . $insight['description'] . '</p>
					<div class="w3tcps_breakdown_items_container">
						' . $sections . '
						<div class="w3tcps_instruction">
							<div class="w3tc_fancy_header">
								<img class="w3tc_fancy_icon" src="' . esc_url( plugins_url( '/w3-total-cache/pub/img/w3tc_cube-shadow.png' ) ) . '" />
								<div class="w3tc_fancy_title">
									<span>Total Cache</span>
									<span>:</span>
									<span>' . esc_html__( 'Tips', 'w3-total-cache' ) . '</span>
								</div>
							</div>
							<div class="w3tc_instruction_copy">' . $insight['instructions'] . '</div>
						</div>
					</div>
				</div>
			</div>';
	}

	/**
	 * Render the network chain list recursively.
	 *
	 * @since 2.10.0
	 *
	 * @param array $chains Chain list.
	 *
	 * @return string
	 */
	private static function render_network_chain_list( $chains ) {
		if ( empty( $chains ) ) {
			return '<p class="w3tcps-no-items">' . esc_html__( 'No critical request chains were provided for this audit.', 'w3-total-cache' ) . '</p>';
		}

		$html = '<ul class="w3tcps_network_chain_list">';
		foreach ( $chains as $chain ) {
			$html .= self::render_network_chain_node( $chain );
		}
		$html .= '</ul>';

		return $html;
	}

	/**
	 * Render a single network chain node.
	 *
	 * @since 2.10.0
	 *
	 * @param array $node Node payload.
	 *
	 * @return string
	 */
	private static function render_network_chain_node( $node ) {
		$w3tc_url   = $node['url'] ?? '';
		$children   = $node['children'] ?? array();
		$meta_parts = array();

		if ( isset( $node['transferSize'] ) && \is_numeric( $node['transferSize'] ) ) {
			$meta_parts[] = sprintf(
				/* translators: %s – transfer size. */
				esc_html__( '%s transferred', 'w3-total-cache' ),
				self::format_transfer_size( $node['transferSize'] )
			);
		}

		if ( isset( $node['duration'] ) && \is_numeric( $node['duration'] ) ) {
			$meta_parts[] = sprintf(
				/* translators: %d – duration in milliseconds. */
				esc_html__( '%d ms', 'w3-total-cache' ),
				(int) $node['duration']
			);
		}

		$w3tc_meta  = ! empty( $meta_parts ) ? '<span class="w3tcps_network_node_meta">' . esc_html( implode( ' • ', $meta_parts ) ) . '</span>' : '';
		$w3tc_class = 'w3tcps_network_node';
		if ( ! empty( $node['isLongest'] ) ) {
			$w3tc_class .= ' w3tcps_network_node_longest';
		}

		$w3tc_label = esc_html( $w3tc_url );
		if ( filter_var( $w3tc_url, FILTER_VALIDATE_URL ) !== false ) {
			$w3tc_label = '<a href="' . esc_url( $w3tc_url ) . '" target="_blank" rel="noopener">' . esc_html( $w3tc_url ) . '</a>';
		} elseif ( empty( $w3tc_url ) ) {
			$w3tc_label = esc_html__( '(unknown request)', 'w3-total-cache' );
		}

		$html  = '<li class="' . esc_attr( $w3tc_class ) . '">';
		$html .= '<div class="w3tcps_network_node_header">' . $w3tc_label . $w3tc_meta . '</div>';

		if ( ! empty( $children ) ) {
			$html .= '<ul>';
			foreach ( $children as $child ) {
				$html .= self::render_network_chain_node( $child );
			}
			$html .= '</ul>';
		}

		$html .= '</li>';

		return $html;
	}

	/**
	 * Render preconnect sections.
	 *
	 * @since 2.10.0
	 *
	 * @param array  $section      Section payload.
	 * @param string $default_name Fallback title.
	 *
	 * @return string
	 */
	private static function render_preconnect_section_markup( $section, $default_name ) {
		if ( empty( $section ) || empty( $section['entries'] ) ) {
			return '';
		}

		$title = ! empty( $section['title'] ) ? $section['title'] : $default_name;
		$html  = '<div class="w3tcps_network_preconnect">';
		$html .= '<h4>' . esc_html( $title ) . '</h4>';

		if ( ! empty( $section['description'] ) ) {
			$html .= '<p>' . esc_html( $section['description'] ) . '</p>';
		}

		if ( \is_string( $section['entries'] ) ) {
			$html .= '<p>' . esc_html( $section['entries'] ) . '</p>';
		} elseif ( \is_array( $section['entries'] ) ) {
			$html .= '<ul>';
			foreach ( $section['entries'] as $w3tc_entry ) {
				$html .= '<li>' . esc_html( $w3tc_entry ) . '</li>';
			}
			$html .= '</ul>';
		}

		$html .= '</div>';

		return $html;
	}

	/**
	 * Render a nested table cell from headings + sub-items.
	 *
	 * @since 2.10.0
	 *
	 * @param array $headings Table headings.
	 * @param array $w3tc_rows     Table rows.
	 *
	 * @return string
	 */
	private static function render_subitems_table_cell( $headings, $w3tc_rows ) {
		if ( empty( $headings ) ) {
			return '';
		}

		$headings = array_values( $headings );
		$colspan  = count( $headings );

		$html = '<td><table class="w3tcps_breakdown_subitems_table"><tr class="w3tcps_passed_audit_subitem_header">';
		foreach ( $headings as $heading ) {
			$html .= '<th>' . esc_html( $heading['label'] ?? '' ) . '</th>';
		}
		$html .= '</tr>';

		if ( empty( $w3tc_rows ) ) {
			$html .= '<tr class="w3tcps_passed_audit_subitem"><td colspan="' . esc_attr( $colspan ) . '">' . esc_html__( 'No additional data provided by PageSpeed.', 'w3-total-cache' ) . '</td></tr>';
			$html .= '</table></td>';
			return $html;
		}

		foreach ( $w3tc_rows as $row ) {
			$html .= '<tr class="w3tcps_passed_audit_subitem">';
			foreach ( $headings as $heading ) {
				$html .= '<td>' . self::format_subitem_value( $heading, $row ) . '</td>';
			}
			$html .= '</tr>';
		}

		$html .= '</table></td>';

		return $html;
	}

	/**
	 * Format a subitem value according to the heading definition.
	 *
	 * @since 2.10.0
	 *
	 * @param array $heading Heading definition.
	 * @param array $row     Row data.
	 *
	 * @return string
	 */
	private static function format_subitem_value( $heading, $row ) {
		$w3tc_key   = $heading['key'] ?? '';
		$value_type = $heading['valueType'] ?? '';
		$w3tc_value = ( '' !== $w3tc_key && isset( $row[ $w3tc_key ] ) ) ? $row[ $w3tc_key ] : null;

		if ( null === $w3tc_value && isset( $row['value'] ) && '' === $w3tc_key ) {
			$w3tc_value = $row['value'];
		}

		if ( 'source-location' === $value_type && \is_array( $w3tc_value ) ) {
			return self::format_source_location_value( $w3tc_value );
		}

		if ( 'ms' === $value_type && \is_numeric( $w3tc_value ) ) {
			$precision = isset( $heading['granularity'] ) ? (int) $heading['granularity'] : 0;
			$ms_value  = $precision > 0 ? round( $w3tc_value, $precision ) : round( $w3tc_value );
			return esc_html( $ms_value . ' ms' );
		}

		if ( 'link' === $value_type && \is_string( $w3tc_value ) && filter_var( $w3tc_value, FILTER_VALIDATE_URL ) ) {
			return '<a href="' . esc_url( $w3tc_value ) . '" target="_blank" rel="noopener">' . esc_html( $w3tc_value ) . '</a>';
		}

		if ( 'node' === $w3tc_key && isset( $row['node'] ) ) {
			$snippet  = isset( $row['node']['snippet'] ) ? '<p><b>' . esc_html__( 'Snippet', 'w3-total-cache' ) . ':</b> ' . esc_html( $row['node']['snippet'] ) . '</p>' : '';
			$selector = isset( $row['node']['selector'] ) ? '<p><b>' . esc_html__( 'Selector', 'w3-total-cache' ) . ':</b> ' . esc_html( $row['node']['selector'] ) . '</p>' : '';
			return $snippet . $selector;
		}

		if ( \is_array( $w3tc_value ) ) {
			return esc_html( wp_json_encode( $w3tc_value ) );
		}

		if ( null !== $w3tc_value ) {
			return esc_html( $w3tc_value );
		}

		return '&mdash;';
	}

	/**
	 * Format a source-location value into HTML.
	 *
	 * @since 2.10.0
	 *
	 * @param array $source Source location payload.
	 *
	 * @return string
	 */
	private static function format_source_location_value( $source ) {
		$w3tc_url   = $source['url'] ?? '';
		$w3tc_file  = $source['file'] ?? $w3tc_url;
		$w3tc_line  = $source['line'] ?? $source['lineNumber'] ?? null;
		$col        = $source['column'] ?? $source['columnNumber'] ?? null;
		$w3tc_label = $w3tc_file;

		if ( null !== $w3tc_line ) {
			$w3tc_label .= ':' . (int) $w3tc_line;
			if ( null !== $col ) {
				$w3tc_label .= ':' . (int) $col;
			}
		}

		if ( ! empty( $w3tc_url ) ) {
			return '<a href="' . esc_url( $w3tc_url ) . '" target="_blank" rel="noopener">' . esc_html( $w3tc_label ) . '</a>';
		}

		return esc_html( $w3tc_label );
	}

	/**
	 * Append the document latency status icon for qualifying rows.
	 *
	 * @since 2.10.0
	 *
	 * @param string $audit_id Audit identifier.
	 * @param array  $w3tc_item     Detail item.
	 * @param string $headers  Headers markup (passed by reference).
	 * @param string $items    Items markup (passed by reference).
	 *
	 * @return void
	 */
	private static function append_document_latency_status( $audit_id, $w3tc_item, &$headers, &$items ) {
		if ( 'document-latency-insight' !== $audit_id ) {
			return;
		}

		$status = null;
		if ( isset( $w3tc_item['value'] ) ) {
			if ( \is_array( $w3tc_item['value'] ) && isset( $w3tc_item['value']['value'] ) && \is_numeric( $w3tc_item['value']['value'] ) ) {
				$status = (int) $w3tc_item['value']['value'];
			} elseif ( \is_numeric( $w3tc_item['value'] ) ) {
				$status = (int) $w3tc_item['value'];
			}
		}

		if ( null === $status ) {
			return;
		}

		$headers   .= '<th>' . esc_html__( 'Document Request', 'w3-total-cache' ) . '</th>';
		$w3tc_icon  = $status ? 'dashicons-yes-alt' : 'dashicons-dismiss';
		$w3tc_label = $status ? esc_html__( 'Pass', 'w3-total-cache' ) : esc_html__( 'Fail', 'w3-total-cache' );

		$items .= '<td><span class="dashicons ' . esc_attr( $w3tc_icon ) . ' ' . ( $status ? 'w3tcps_status_pass' : 'w3tcps_status_fail' ) . '" aria-hidden="true"></span><span class="screen-reader-text">' . $w3tc_label . '</span></td>';
	}

	/**
	 * Format bytes into readable strings.
	 *
	 * @since 2.10.0
	 *
	 * @param int $bytes Byte value.
	 *
	 * @return string
	 */
	private static function format_transfer_size( $bytes ) {
		if ( empty( $bytes ) || ! \is_numeric( $bytes ) ) {
			return '';
		}

		return \size_format( $bytes, 2 );
	}

	/**
	 * Recursively get value based on series of key decendents.
	 *
	 * @param array $w3tc_data PageSpeed data.
	 * @param array $elements Array of key decendents.
	 *
	 * @return object | null
	 */
	public static function get_value_recursive( $w3tc_data, $elements ) {
		if ( empty( $elements ) ) {
			return $w3tc_data;
		}

		$w3tc_key = array_shift( $elements );
		if ( ! isset( $w3tc_data[ $w3tc_key ] ) ) {
			return null;
		}

		return self::get_value_recursive( $w3tc_data[ $w3tc_key ], $elements );
	}

	/**
	 * Return wp_kses allowed HTML tags/attributes.
	 *
	 * @return array
	 */
	public static function get_allowed_tags() {
		return array(
			'div'   => array(
				'id'    => array(),
				'class' => array(),
			),
			'span'  => array(
				'id'      => array(),
				'class'   => array(),
				'title'   => array(),
				'gatitle' => array(),
				'copyurl' => array(),
			),
			'p'     => array(
				'id'    => array(),
				'class' => array(),
			),
			'table' => array(
				'id'    => array(),
				'class' => array(),
			),
			'tr'    => array(
				'id'    => array(),
				'class' => array(),
			),
			'td'    => array(
				'id'    => array(),
				'class' => array(),
			),
			'th'    => array(
				'id'    => array(),
				'class' => array(),
			),
			'b'     => array(
				'id'    => array(),
				'class' => array(),
			),
			'br'    => array(),
			'a'     => array(
				'id'     => array(),
				'class'  => array(),
				'href'   => array(),
				'target' => array(),
				'rel'    => array(),
				'title'  => array(),
			),
			'link'  => array(
				'id'    => array(),
				'class' => array(),
				'href'  => array(),
				'rel'   => array(),
				'as'    => array(),
				'type'  => array(),
			),
			'code'  => array(
				'id'    => array(),
				'class' => array(),
			),
			'img'   => array(
				'id'     => array(),
				'class'  => array(),
				'srcset' => array(),
				'src'    => array(),
				'alt'    => array(),
			),
			'ul'    => array(
				'id'    => array(),
				'class' => array(),
			),
			'ol'    => array(
				'id'    => array(),
				'class' => array(),
			),
			'li'    => array(
				'id'    => array(),
				'class' => array(),
			),
			'h3'    => array(
				'id'    => array(),
				'class' => array(),
			),
			'h4'    => array(
				'id'    => array(),
				'class' => array(),
			),
		);
	}

	/**
	 * Get cache life time.
	 *
	 * @return int
	 */
	public static function get_cache_life() {
		return 3600;
	}

	/**
	 * Conver seconds into string breaking down days/hours/minutes/seconds.
	 *
	 * @param int $seconds Seconds.
	 *
	 * @return string
	 */
	public static function seconds_to_str( $seconds ) {
		$buffer = '';
		if ( $seconds >= 86400 ) {
			$days    = floor( $seconds / 86400 );
			$seconds = $seconds % 86400;
			$buffer .= $days . ' day' . ( $days > 1 ? 's' : '' ) . ( $seconds > 0 ? ', ' : '' );
		}
		if ( $seconds >= 3600 ) {
			$hours   = floor( $seconds / 3600 );
			$seconds = $seconds % 3600;
			$buffer .= $hours . ' hour' . ( $hours > 1 ? 's' : '' ) . ( $seconds > 0 ? ', ' : '' );
		}
		if ( $seconds >= 60 ) {
			$minutes = floor( $seconds / 60 );
			$seconds = $seconds % 60;
			$buffer .= $minutes . ' minute' . ( $minutes > 1 ? 's' : '' ) . ( $seconds > 0 ? ', ' : '' );
		}
		if ( $seconds > 0 ) {
			$buffer .= $seconds . ' second' . ( $seconds > 1 ? 's' : '' );
		}
		return $buffer;
	}
}
