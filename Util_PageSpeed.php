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
	 * Get score guage angle
	 *
	 * @param int $score PageSpeed desktop/mobile score.
	 *
	 * @return int
	 */
	public static function get_gauge_angle( $score ) {
		return ( ! empty( $score ) ? ( $score / 100 ) * 180 : 0 );
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
		if ( ! empty( $score ) && is_numeric( $score ) ) {
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
	 * Render the PageSpeed desktop/mobile score guage
	 *
	 * @param array  $data PageSpeed desktop/mobile data containing score key.
	 * @param string $icon Desktop/Mobile icon value.
	 *
	 * @return void
	 */
	public static function print_gauge( $data, $icon ) {
		if ( ! isset( $data ) || empty( $data['score'] ) || empty( $icon ) ) {
			return;
		}

		$color = self::get_gauge_color( $data['score'] );
		$angle = self::get_gauge_angle( $data['score'] );

		?>
		<div class="gauge" style="width: 120px; --rotation:<?php echo esc_attr( $angle ); ?>deg; --color:<?php echo esc_attr( $color ); ?>; --background:#888;">
			<div class="percentage"></div>
			<div class="mask"></div>
			<span class="value">
				<span class="dashicons dashicons-<?php echo esc_attr( $icon ); ?>"></span>
				<?php echo ( ! empty( $data['score'] ) ? esc_html( $data['score'] ) : '' ); ?>
			</span>
		</div>
		<?php
	}

	/**
	 * Render core metric bar-line.
	 *
	 * @param array $metric PageSpeed desktop/mobile data containing score key.
	 *
	 * @return void
	 */
	public static function print_barline( $metric ) {
		if ( empty( $metric['score'] ) ) {
			return;
		}

		$metric['score'] *= 100;

		$bar = '';

		if ( $metric['score'] >= 90 ) {
			$bar = '<div style="flex-grow: ' . $metric['score'] . '"><span class="w3tcps_range w3tcps_pass">' . $metric['displayValue'] . '</span></div>';
		} elseif ( $metric['score'] >= 50 && $metric['score'] < 90 ) {
			$bar = '<div style="flex-grow: ' . $metric['score'] . '"><span class="w3tcps_range w3tcps_average">' . $metric['displayValue'] . '</span></div>';
		} elseif ( $metric['score'] < 50 ) {
			$bar = '<div style="flex-grow: ' . $metric['score'] . '"><span class="w3tcps_range w3tcps_fail">' . $metric['displayValue'] . '<span></div>';
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
	 * @param array  $data PageSpeed data.
	 * @param string $metric Metric key.
	 * @param string $name Metric name.
	 *
	 * @return void
	 */
	public static function print_bar_combined_with_icon( $data, $metric, $name ) {
		if ( ! isset( $data ) || empty( $metric ) || empty( $name ) ) {
			return;
		}

		?>
		<div class="w3tcps_metric">
			<h3 class="w3tcps_metric_title"><?php echo esc_html( $name ); ?></h3>
			<div class="w3tcps_metric_stats">
				<span class="dashicons dashicons-<?php echo esc_attr( 'desktop' ); ?>"></span>
				<?php self::print_barline( $data['desktop'][ $metric ] ); ?>
				<span class="dashicons dashicons-<?php echo esc_attr( 'smartphone' ); ?>"></span>
				<?php self::print_barline( $data['mobile'][ $metric ] ); ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Render core metric for desktop/mobile. Used by PageSpeed dashboard widget.
	 *
	 * @param array  $data PageSpeed desktop/mobile data.
	 * @param string $metric Metric key.
	 * @param string $name Metric name.
	 *
	 * @return void
	 */
	public static function print_bar_single_no_icon( $data, $metric, $name ) {
		if ( ! isset( $data ) || empty( $data[ $metric ] ) || empty( $metric ) || empty( $name ) ) {
			return;
		}

		?>
		<div class="w3tcps_metric">
			<h3 class="w3tcps_metric_title"><?php echo esc_html( $name ); ?></h3>
			<div class="w3tcps_metric_stats">
				<?php self::print_barline( $data[ $metric ] ); ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Get PageSpeed metric notice BG
	 *
	 * @param int $score PageSpeed desktop/mobile score.
	 *
	 * @return string
	 */
	public static function get_breakdown_bg( $score ) {
		$notice = 'notice notice-info inline';
		if ( ! empty( $score ) && is_numeric( $score ) ) {
			if ( $score >= 90 ) {
				$notice = 'notice notice-success inline';
			} elseif ( $score >= 50 && $score < 90 ) {
				$noitce = 'notice notice-warning inline';
			} elseif ( $score > 0 && $score < 50 ) {
				$notice = 'notice notice-error inline';
			}
		}
		return $notice;
	}

	/**
	 * Get PageSpeed metric grade
	 *
	 * @param int $score PageSpeed desktop/mobile score.
	 *
	 * @return string
	 */
	public static function get_breakdown_grade( $score ) {
		$grade = 'w3tcps_blank';
		if ( ! empty( $score ) && is_numeric( $score ) ) {
			if ( $score >= 90 ) {
				$grade = 'w3tcps_pass';
			} elseif ( $score >= 50 && $score < 90 ) {
				$grade = 'w3tcps_average';
			} elseif ( $score > 0 && $score < 50 ) {
				$grade = 'w3tcps_fail';
			}
		}
		return $grade;
	}

	/**
	 * Render the final generated screenshot
	 *
	 * @param array $data PageSpeed data.
	 *
	 * @return void
	 */
	public static function print_final_screenshot( $data ) {
		if ( isset( $data ) && ! empty( $data['screenshots']['final']['screenshot'] ) ) {
			echo '<img src="' . esc_attr( $data['screenshots']['final']['screenshot'] ) . '" alt="' . ( ! empty( $data['screenshots']['final']['title'] ) ? esc_attr( $data['screenshots']['final']['title'] ) : esc_attr__( 'Final Screenshot', 'w3-total-cache' ) ) . '"/>';
		}
	}

	/**
	 * Render all "building" screenshots
	 *
	 * @param mixed $data PageSpeed desktop/mobile score.
	 *
	 * @return void
	 */
	public static function print_screenshots( $data ) {
		if ( isset( $data ) && ! empty( $data['screenshots']['other']['screenshots'] ) ) {
			foreach ( $data['screenshots']['other']['screenshots'] as $screenshot ) {
				echo '<img src="' . esc_attr( $screenshot['data'] ) . '" alt="' . ( ! empty( $data['screenshots']['other']['title'] ) ? esc_attr( $data['screenshots']['other']['title'] ) : esc_attr__( 'Other Screenshot', 'w3-total-cache' ) ) . '"/>';
			}
		}
	}

	/**
	 * Render all metric data into listable items
	 *
	 * @param array $data PageSpeed desktop/mobile score.
	 *
	 * @return void
	 */
	public static function print_breakdown( $data ) {
		//Util_Debug::debug( 'Util_PageSpeed print_breakdown data', $data);
		if ( ! isset( $data ) || ( empty( $data['opportunities'] ) && empty( $data['diagnostics'] ) ) ) {
			return;
		}

		$opportunities = '';
		$diagnostics   = '';
		$passed_audits = '';

		foreach ( $data['opportunities'] as $opportunity ) {
			if ( empty( $opportunity['details'] ) ) {
				continue;
			}

			$opportunity['score'] *= 100;

			$notice = 'notice notice-info inline';
			$grade  = 'w3tcps_blank';
			if ( ! empty( $opportunity['score'] ) ) {
				$notice = self::get_breakdown_bg( $opportunity['score'] );
				$grade  = self::get_breakdown_grade( $opportunity['score'] );
			}

			$audit_classes = '';
			if ( ! empty( $opportunity['type'] ) ) {
				foreach ( $opportunity['type'] as $type ) {
					$audit_classes .= ' ' . $type;
				}
			}

			$opportunity['description'] = preg_replace( '/(.*)(\[Learn more\])\((.*?)\)(.*)/i', '$1<a href="$3">$2</a>$4', $opportunity['description'] );

			$headers = '';
			$items   = '';

			foreach ( $opportunity['details'] as $item ) {
				$headers = '';
				$items  .= '<tr class="w3tcps_passed_audit_item">';
				if ( ! empty( $item['url'] ) ) {
					$headers .= '<th>' . esc_html__( 'URL', 'w3-total-cache' ) . '</th>';
					$items   .= '<td>...' . wp_parse_url( $item['url'] )['path'] . '</td>';
				}
				if ( ! empty( $item['totalBytes'] ) ) {
					$headers .= '<th>' . esc_html__( 'Total Bytes', 'w3-total-cache' ) . '</th>';
					$items   .= '<td>' . $item['totalBytes'] . '</td>';
				}
				if ( ! empty( $item['wastedBytes'] ) ) {
					$headers .= '<th>' . esc_html__( 'Wasted Bytes', 'w3-total-cache' ) . '</th>';
					$items   .= '<td>' . $item['wastedBytes'] . '</td>';
				}
				if ( ! empty( $item['wastedPercent'] ) ) {
					$headers .= '<th>' . esc_html__( 'Wasted Percentage', 'w3-total-cache' ) . '</th>';
					$items   .= '<td>' . round( $item['wastedPercent'], 2 ) . '%</td>';
				}
				if ( ! empty( $item['wastedMs'] ) ) {
					$headers .= '<th>' . esc_html__( 'Wasted Miliseconds', 'w3-total-cache' ) . '</th>';
					$items   .= '<td>' . round( $item['wastedMs'], 2 ) . '</td>';
				}
				if ( ! empty( $item['label'] ) ) {
					$headers .= '<th>' . esc_html__( 'Type', 'w3-total-cache' ) . '</th>';
					$items   .= '<td>' . $item['label'] . '</td>';
				}
				if ( ! empty( $item['groupLabel'] ) ) {
					$headers .= '<th>' . esc_html__( 'Group', 'w3-total-cache' ) . '</th>';
					$items   .= '<td>' . $item['groupLabel'] . '</td>';
				}
				if ( ! empty( $item['requestCount'] ) ) {
					$headers .= '<th>' . esc_html__( 'Requests', 'w3-total-cache' ) . '</th>';
					$items   .= '<td>' . $item['requestCount'] . '</td>';
				}
				if ( ! empty( $item['transferSize'] ) ) {
					$headers .= '<th>' . esc_html__( 'Transfer Size', 'w3-total-cache' ) . '</th>';
					$items   .= '<td>' . $item['transferSize'] . '</td>';
				}
				if ( ! empty( $item['startTime'] ) ) {
					$headers .= '<th>' . esc_html__( 'Start Time', 'w3-total-cache' ) . '</th>';
					$items   .= '<td>' . $item['startTime'] . '</td>';
				}
				if ( ! empty( $item['duration'] ) ) {
					$headers .= '<th>' . esc_html__( 'Duration', 'w3-total-cache' ) . '</th>';
					$items   .= '<td>' . $item['duration'] . '</td>';
				}
				if ( ! empty( $item['scriptParseCompile'] ) ) {
					$headers .= '<th>' . esc_html__( 'Parse/Compile Time', 'w3-total-cache' ) . '</th>';
					$items   .= '<td>' . $item['scriptParseCompile'] . '</td>';
				}
				if ( ! empty( $item['scripting'] ) ) {
					$headers .= '<th>' . esc_html__( 'Execution Time', 'w3-total-cache' ) . '</th>';
					$items   .= '<td>' . $item['scripting'] . '</td>';
				}
				if ( ! empty( $item['total'] ) ) {
					$headers .= '<th>' . esc_html__( 'Total', 'w3-total-cache' ) . '</th>';
					$items   .= '<td>' . $item['total'] . '</td>';
				}
				if ( ! empty( $item['cacheLifetimeMs'] ) ) {
					$headers .= '<th>' . esc_html__( 'Cache Lifetime Miliseconds', 'w3-total-cache' ) . '</th>';
					$items   .= '<td>' . $item['cacheLifetimeMs'] . '</td>';
				}
				if ( ! empty( $item['cacheHitProbability'] ) ) {
					$headers .= '<th>' . esc_html__( 'Cache Hit Probability', 'w3-total-cache' ) . '</th>';
					$items   .= '<td>' . $item['cacheHitProbability'] . '</td>';
				}
				if ( ! empty( $item['value'] ) && ! empty( $item['statistic'] ) ) {
					$headers .= '<th>' . esc_html__( 'Statistic', 'w3-total-cache' ) . '</th>';
					$items   .= '<td>' . $item['statistic'] . '</td>';

					$headers .= '<th>' . esc_html__( 'Element', 'w3-total-cache' ) . '</th>';
					$items   .= '<td>';
					if ( ! empty( $item['node'] ) ) {
						$items .= '<p>' . esc_html( $item['node']['snippet'] ) . '</p>';
						$items .= '<p>' . $item['node']['selector'] . '</p>';
					}
					$items .= '</td>';

					$headers .= '<th>' . esc_html__( 'Value', 'w3-total-cache' ) . '</th>';
					$items   .= '<td>' . $item['value'] . '</td>';
				} elseif ( ! empty( $item['node'] ) ) {
					$headers .= '<th>' . esc_html__( 'Element', 'w3-total-cache' ) . '</th>';
					$items   .= '<td>';
					$items   .= '<p>' . esc_html( $item['node']['snippet'] ) . '</p>';
					$items   .= '<p>' . $item['node']['selector'] . '</p>';
					$items   .= '</td>';
				}
				$items .= '</tr>';
			}

			$items = ( ! empty( $items ) ? $items : '<p class="w3tcps-no-items">' . esc_html__( 'No identified items were provided by Google PageSpeed Insights API for this metric', 'w3-total-cache' ) . '</p>' );

			if ( $opportunity['score'] >= 90 ) {
				$passed_audits .= '
					<div class="audits w3tcps_passed_audit' . $audit_classes . ' ' . $notice . '">
						<span class="w3tcps_breakdown_items_toggle w3tcps_range chevron_down ' . $grade . '">' . $opportunity['title'] . ' - ' . $opportunity['displayValue'] . '</span>
						<div class="w3tcps_breakdown_items w3tcps_pass_audit_items">
							<p class="w3tcps_item_desciption">' . $opportunity['description'] . '</p>
							<table class="w3tcps_item_breakdown_table">
								<tr>
									<td class="w3tcps_item_breakdown_items_column">
										<table class="w3tcps_item_table">
											<tr class="w3tcps_passed_audit_item_header">' . $headers . '</tr>' . $items . '
										</table>
									</td>
									<td class="w3tcps_item_breakdown_instruction_column">
										<div class="w3tcps_instruction">
											<div class="w3tc_fancy_header">
												<img class="w3tc_fancy_icon" src="' . esc_url( plugins_url( '/w3-total-cache/pub/img/w3tc_cube-shadow.png' ) ) . '" />
												<div class="w3tc_fancy_title">
													<span>' . esc_html__( 'TOTAL', 'w3-total-cache' ) . '</span>
													<span>' . esc_html__( 'CACHE', 'w3-total-cache' ) . '</span>
													<span>:</span>
													<span>' . esc_html__( 'Our Recommendation', 'w3-total-cache' ) . '</span>
												</div>
											</div>
											<div class="w3tc_instruction_copy">' . $opportunity['instructions'] . '</div>
										</div>
									</td>
								</tr>
							</table>
						</div>
					</div>';
			} else {
				$opportunities .= '
					<div class="audits w3tcps_opportunities' . $audit_classes . ' ' . $notice . '">
						<span class="w3tcps_breakdown_items_toggle w3tcps_range chevron_down ' . $grade . '">' . $opportunity['title'] . ' - ' . $opportunity['displayValue'] . '</span>
						<div class="w3tcps_breakdown_items w3tcps_opportunity_items">
							<p class="w3tcps_item_desciption">' . $opportunity['description'] . '</p>
							<table class="w3tcps_item_breakdown_table">
								<tr>
									<td class="w3tcps_item_breakdown_items_column">
										<table class="w3tcps_item_table">
											<tr class="w3tcps_passed_audit_item_header">' . $headers . '</tr>' . $items . '
										</table>
									</td>
									<td class="w3tcps_item_breakdown_instruction_column">
										<div class="w3tcps_instruction">
											<div class="w3tc_fancy_header">
												<img class="w3tc_fancy_icon" src="' . esc_url( plugins_url( '/w3-total-cache/pub/img/w3tc_cube-shadow.png' ) ) . '" />
												<div class="w3tc_fancy_title">
													<span>' . esc_html__( 'TOTAL', 'w3-total-cache' ) . '</span>
													<span>' . esc_html__( 'CACHE', 'w3-total-cache' ) . '</span>
													<span>:</span>
													<span>' . esc_html__( 'Our Recommendation', 'w3-total-cache' ) . '</span>
												</div>
											</div>
											<div class="w3tc_instruction_copy">' . $opportunity['instructions'] . '</div>
										</div>
									</td>
								</tr>
							</table>
						</div>
					</div>';
			}
		}

		foreach ( $data['diagnostics'] as $diagnostic ) {
			if ( empty( $diagnostic['details'] ) ) {
				continue;
			}

			$diagnostic['score'] *= 100;

			$notice = 'notice notice-info inline';
			$grade  = 'w3tcps_blank';
			if ( ! empty( $diagnostic['score'] ) ) {
				$notice = self::get_breakdown_bg( $diagnostic['score'] );
				$grade  = self::get_breakdown_grade( $diagnostic['score'] );
			}

			$audit_classes = '';
			foreach ( $opportunity['type'] as $type ) {
				$audit_classes .= ' ' . $type;
			}

			$diagnostic['description'] = preg_replace( '/(.*)(\[Learn more\])\((.*?)\)(.*)/i', '$1<a href="$3">$2</a>$4', $diagnostic['description'] );

			$headers = '';
			$items   = '';
			foreach ( $diagnostic['details'] as $item ) {
				$headers = '';
				$items  .= '<tr class="w3tcps_passed_audit_item">';
				if ( ! empty( $item['url'] ) ) {
					$headers .= '<th>' . esc_html__( 'URL', 'w3-total-cache' ) . '</th>';
					$items   .= '<td>...' . wp_parse_url( $item['url'] )['path'] . '</td>';
				}
				if ( ! empty( $item['totalBytes'] ) ) {
					$headers .= '<th>' . esc_html__( 'Total Bytes', 'w3-total-cache' ) . '</th>';
					$items   .= '<td>' . $item['totalBytes'] . '</td>';
				}
				if ( ! empty( $item['wastedBytes'] ) ) {
					$headers .= '<th>' . esc_html__( 'Wasted Bytes', 'w3-total-cache' ) . '</th>';
					$items   .= '<td>' . $item['wastedBytes'] . '</td>';
				}
				if ( ! empty( $item['wastedPercent'] ) ) {
					$headers .= '<th>' . esc_html__( 'Wasted Percentage', 'w3-total-cache' ) . '</th>';
					$items   .= '<td>' . round( $item['wastedPercent'], 2 ) . '%</td>';
				}
				if ( ! empty( $item['wastedMs'] ) ) {
					$headers .= '<th>' . esc_html__( 'Wasted Miliseconds', 'w3-total-cache' ) . '</th>';
					$items   .= '<td>' . round( $item['wastedMs'], 2 ) . '</td>';
				}
				if ( ! empty( $item['label'] ) ) {
					$headers .= '<th>' . esc_html__( 'Type', 'w3-total-cache' ) . '</th>';
					$items   .= '<td>' . $item['label'] . '</td>';
				}
				if ( ! empty( $item['groupLabel'] ) ) {
					$headers .= '<th>' . esc_html__( 'Group', 'w3-total-cache' ) . '</th>';
					$items   .= '<td>' . $item['groupLabel'] . '</td>';
				}
				if ( ! empty( $item['requestCount'] ) ) {
					$headers .= '<th>' . esc_html__( 'Requests', 'w3-total-cache' ) . '</th>';
					$items   .= '<td>' . $item['requestCount'] . '</td>';
				}
				if ( ! empty( $item['transferSize'] ) ) {
					$headers .= '<th>' . esc_html__( 'Transfer Size', 'w3-total-cache' ) . '</th>';
					$items   .= '<td>' . $item['transferSize'] . '</td>';
				}
				if ( ! empty( $item['startTime'] ) ) {
					$headers .= '<th>' . esc_html__( 'Start Time', 'w3-total-cache' ) . '</th>';
					$items   .= '<td>' . $item['startTime'] . '</td>';
				}
				if ( ! empty( $item['duration'] ) ) {
					$headers .= '<th>' . esc_html__( 'Duration', 'w3-total-cache' ) . '</th>';
					$items   .= '<td>' . $item['duration'] . '</td>';
				}
				if ( ! empty( $item['scriptParseCompile'] ) ) {
					$headers .= '<th>' . esc_html__( 'Parse/Compile Time', 'w3-total-cache' ) . '</th>';
					$items   .= '<td>' . $item['scriptParseCompile'] . '</td>';
				}
				if ( ! empty( $item['scripting'] ) ) {
					$headers .= '<th>' . esc_html__( 'Execution Time', 'w3-total-cache' ) . '</th>';
					$items   .= '<td>' . $item['scripting'] . '</td>';
				}
				if ( ! empty( $item['total'] ) ) {
					$headers .= '<th>' . esc_html__( 'Total', 'w3-total-cache' ) . '</th>';
					$items   .= '<td>' . $item['total'] . '</td>';
				}
				if ( ! empty( $item['cacheLifetimeMs'] ) ) {
					$headers .= '<th>' . esc_html__( 'Cache Lifetime Miliseconds', 'w3-total-cache' ) . '</th>';
					$items   .= '<td>' . $item['cacheLifetimeMs'] . '</td>';
				}
				if ( ! empty( $item['cacheHitProbability'] ) ) {
					$headers .= '<th>' . esc_html__( 'Cache Hit Probability', 'w3-total-cache' ) . '</th>';
					$items   .= '<td>' . ( $item['cacheHitProbability'] * 100 ) . '%</td>';
				}
				if ( ! empty( $item['value'] ) && ! empty( $item['statistic'] ) ) {
					$headers .= '<th>' . esc_html__( 'Statistic', 'w3-total-cache' ) . '</th>';
					$items   .= '<td>' . $item['statistic'] . '</td>';

					$headers .= '<th>' . esc_html__( 'Element', 'w3-total-cache' ) . '</th>';
					$items   .= '<td>';
					if ( ! empty( $item['node'] ) ) {
						$items .= '<p>' . esc_html( $item['node']['snippet'] ) . '</p>';
						$items .= '<p>' . $item['node']['selector'] . '</p>';
					}
					$items .= '</td>';

					$headers .= '<th>' . esc_html__( 'Value', 'w3-total-cache' ) . '</th>';
					$items   .= '<td>' . $item['value'] . '</td>';
				} elseif ( ! empty( $item['node'] ) ) {
					$headers .= '<th>' . esc_html__( 'Element', 'w3-total-cache' ) . '</th>';
					$items   .= '<td>';
					$items   .= '<p>' . esc_html( $item['node']['snippet'] ) . '</p>';
					$items   .= '<p>' . $item['node']['selector'] . '</p>';
					$items   .= '</td>';
				}
				$items .= '</tr>';
			}

			$items = ( ! empty( $items ) ? $items : '<p class="w3tcps-no-items">' . esc_html__( 'No identified items were provided by Google PageSpeed Insights API for this metric', 'w3-total-cache' ) . '</p>' );

			if ( $diagnostic['score'] >= 90 ) {
				$passed_audits .= '
					<div class="audits w3tcps_passed_audit' . $audit_classes . ' ' . $notice . '">
						<span class="w3tcps_breakdown_items_toggle w3tcps_range chevron_down ' . $grade . '">' . $diagnostic['title'] . ' - ' . $diagnostic['displayValue'] . '</span>
						<div class="w3tcps_breakdown_items w3tcps_pass_audit_items">
							<p class="w3tcps_item_desciption">' . $diagnostic['description'] . '</p>
							<table class="w3tcps_item_breakdown_table">
								<tr>
									<td class="w3tcps_item_breakdown_items_column">
										<table class="w3tcps_item_table">
											<tr class="w3tcps_passed_audit_item_header">' . $headers . '</tr>' . $items . '
										</table>
									</td>
									<td class="w3tcps_item_breakdown_instruction_column">
										<div class="w3tcps_instruction">
											<div class="w3tc_fancy_header">
												<img class="w3tc_fancy_icon" src="' . esc_url( plugins_url( '/w3-total-cache/pub/img/w3tc_cube-shadow.png' ) ) . '" />
												<div class="w3tc_fancy_title">
													<span>' . esc_html__( 'TOTAL', 'w3-total-cache' ) . '</span>
													<span>' . esc_html__( 'CACHE', 'w3-total-cache' ) . '</span>
													<span>:</span>
													<span>' . esc_html__( 'Our Recommendation', 'w3-total-cache' ) . '</span>
												</div>
											</div>
											<div class="w3tc_instruction_copy">' . $diagnostic['instructions'] . '</div>
										</div>
									</td>
								</tr>
							</table>
						</div>
					</div>';
			} else {
				$diagnostics .= '
					<div class="audits w3tcps_diagnostics' . $audit_classes . ' ' . $notice . '">
						<span class="w3tcps_breakdown_items_toggle w3tcps_range chevron_down ' . $grade . '">' . $diagnostic['title'] . ' - ' . $diagnostic['displayValue'] . '</span>
						<div class="w3tcps_breakdown_items w3tcps_diagnostic_items">
							<p class="w3tcps_item_desciption">' . $diagnostic['description'] . '</p>
							<table class="w3tcps_item_breakdown_table">
								<tr>
									<td class="w3tcps_item_breakdown_items_column">
										<table class="w3tcps_item_table">
											<tr class="w3tcps_passed_audit_item_header">' . $headers . '</tr>' . $items . '
										</table>
									</td>
									<td class="w3tcps_item_breakdown_instruction_column">
										<div class="w3tcps_instruction">
											<div class="w3tc_fancy_header">
												<img class="w3tc_fancy_icon" src="' . esc_url( plugins_url( '/w3-total-cache/pub/img/w3tc_cube-shadow.png' ) ) . '" />
												<div class="w3tc_fancy_title">
													<span>' . esc_html__( 'TOTAL', 'w3-total-cache' ) . '</span>
													<span>' . esc_html__( 'CACHE', 'w3-total-cache' ) . '</span>
													<span>:</span>
													<span>' . esc_html__( 'Our Recommendation', 'w3-total-cache' ) . '</span>
												</div>
											</div>
											<div class="w3tc_instruction_copy">' . $diagnostic['instructions'] . '</div>
										</div>
									</td>
								</tr>
							</table>
						</div>
					</div>';
			}
		}

		$allowed_tags = self::get_allowed_tags();

		echo wp_kses(
			'<div class="w3tcps_audit_results">
				<div class="opportunities"><h3 class="w3tcps_metric_title">' . esc_html__( 'Opportunities', 'w3-total-cache' ) . '</h3>' . $opportunities . '</div>
				<div class="diagnostics"><h3 class="w3tcps_metric_title">' . esc_html__( 'Diagnostics', 'w3-total-cache' ) . '</h3>' . $diagnostics . '</div>
				<div class="passed_audits"><h3 class="w3tcps_metric_title">' . esc_html__( 'Passed Audits', 'w3-total-cache' ) . '</h3>' . $passed_audits . '</div>
			</div>',
			$allowed_tags
		);
	}

	/**
	 * Recursively get value based on series of key decendents.
	 *
	 * @param array $data PageSpeed data.
	 * @param array $elements Array of key decendents.
	 *
	 * @return object | null
	 */
	public static function get_value_recursive( $data, $elements ) {
		if ( empty( $elements ) ) {
			return $data;
		}

		$key = array_shift( $elements );
		if ( ! isset( $data[ $key ] ) ) {
			return null;
		}

		return self::get_value_recursive( $data[ $key ], $elements );
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
				'id'    => array(),
				'class' => array(),
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
		);
	}
}
