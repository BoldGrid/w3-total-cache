<?php
/**
 * File: PageSpeed_Data.php
 *
 * Processes PageSpeed API data return into usable format.
 *
 * @since 2.3.0 Update to utilize OAuth2.0 and overhaul of feature.
 *
 * @package W3TC
 */

namespace W3TC;

/**
 * PageSpeed Data Config.
 *
 * @since 2.3.0
 */
class PageSpeed_Data {

	/**
	 * Prepare PageSpeed Data Config.
	 *
	 * @since 2.3.0
	 *
	 * @param array $data PageSpeed analysis data.
	 *
	 * @return array
	 */
	public static function prepare_pagespeed_data( $data ) {
		$score = Util_PageSpeed::get_value_recursive( $data, array( 'lighthouseResult', 'categories', 'performance', 'score' ) );

		$pagespeed_data = array(
			'score'                    => self::normalize_score( $score ),
			'first-contentful-paint'   => self::collect_core_metric( $data, 'first-contentful-paint' ),
			'largest-contentful-paint' => self::collect_core_metric( $data, 'largest-contentful-paint' ),
			'interactive'              => self::collect_core_metric( $data, 'interactive' ),
			'cumulative-layout-shift'  => self::collect_core_metric( $data, 'cumulative-layout-shift' ),
			'total-blocking-time'      => self::collect_core_metric( $data, 'total-blocking-time' ),
			'speed-index'              => self::collect_core_metric( $data, 'speed-index' ),
			'screenshots'              => array(
				'final' => array(
					'title'      => Util_PageSpeed::get_value_recursive( $data, array( 'lighthouseResult', 'audits', 'final-screenshot', 'title' ) ),
					'screenshot' => Util_PageSpeed::get_value_recursive( $data, array( 'lighthouseResult', 'audits', 'final-screenshot', 'details', 'data' ) ),
				),
				'other' => array(
					'title'       => Util_PageSpeed::get_value_recursive( $data, array( 'lighthouseResult', 'audits', 'screenshot-thumbnails', 'title' ) ),
					'screenshots' => Util_PageSpeed::get_value_recursive( $data, array( 'lighthouseResult', 'audits', 'screenshot-thumbnails', 'details', 'items' ) ),
				),
			),
			'insights'                 => self::collect_audits_by_group( $data, 'insights' ),
			'diagnostics'              => self::collect_audits_by_group( $data, 'diagnostics' ),
		);

		$pagespeed_data['insights']    = self::filter_metrics_by_title( $pagespeed_data['insights'] );
		$pagespeed_data['diagnostics'] = self::filter_metrics_by_title( $pagespeed_data['diagnostics'] );

		if ( defined( 'W3TC_GPS_KEYS_DEBUG' ) ) {
			self::debug_metric_keys( $pagespeed_data );
		}

		return self::merge_instructions( $pagespeed_data );
	}

	/**
	 * Collect core web vital metrics in a consistent format.
	 *
	 * @since X.X.X
	 *
	 * @param array  $data   PageSpeed data payload.
	 * @param string $metric Lighthouse audit identifier.
	 *
	 * @return array
	 */
	private static function collect_core_metric( $data, $metric ) {
		return array(
			'score'            => Util_PageSpeed::get_value_recursive( $data, array( 'lighthouseResult', 'audits', $metric, 'score' ) ),
			'scoreDisplayMode' => Util_PageSpeed::get_value_recursive( $data, array( 'lighthouseResult', 'audits', $metric, 'scoreDisplayMode' ) ),
			'displayValue'     => Util_PageSpeed::get_value_recursive( $data, array( 'lighthouseResult', 'audits', $metric, 'displayValue' ) ),
		);
	}

	/**
	 * Log the raw metric keys and configured instruction keys when debugging is enabled.
	 *
	 * @since X.X.X
	 *
	 * @param array $pagespeed_data Prepared PageSpeed data.
	 *
	 * @return void
	 */
	private static function debug_metric_keys( $pagespeed_data ) {
		$gps_insight_ids    = array_keys( $pagespeed_data['insights'] ?? array() );
		$gps_diagnostic_ids = array_keys( $pagespeed_data['diagnostics'] ?? array() );

		$instruction_config = PageSpeed_Instructions::get_pagespeed_instructions();

		$w3tc_insight_ids    = ! empty( $instruction_config['insights'] ) ? array_keys( $instruction_config['insights'] ) : array();
		$w3tc_diagnostic_ids = ! empty( $instruction_config['diagnostics'] ) ? array_keys( $instruction_config['diagnostics'] ) : array();

		\sort( $gps_insight_ids );
		\sort( $gps_diagnostic_ids );
		\sort( $w3tc_insight_ids );
		\sort( $w3tc_diagnostic_ids );

		Util_Debug::debug(
			'pagespeed_metric_keys',
			array(
				'gps'  => array(
					'insights'    => $gps_insight_ids,
					'diagnostics' => $gps_diagnostic_ids,
				),
				'w3tc' => array(
					'insights'    => $w3tc_insight_ids,
					'diagnostics' => $w3tc_diagnostic_ids,
				),
			)
		);
	}

	/**
	 * Collect audits belonging to the given Lighthouse category group.
	 *
	 * @since X.X.X
	 *
	 * @param array  $data  Raw Lighthouse API payload.
	 * @param string $group Lighthouse category group identifier.
	 *
	 * @return array
	 */
	private static function collect_audits_by_group( $data, $group ) {
		$audit_refs = Util_PageSpeed::get_value_recursive( $data, array( 'lighthouseResult', 'categories', 'performance', 'auditRefs' ) );
		$audits     = Util_PageSpeed::get_value_recursive( $data, array( 'lighthouseResult', 'audits' ) );
		if ( empty( $audit_refs ) || ! \is_array( $audit_refs ) || empty( $audits ) || ! \is_array( $audits ) ) {
			return array();
		}

		$metrics = array();

		foreach ( $audit_refs as $audit_ref ) {
			if ( empty( $audit_ref['id'] ) || empty( $audit_ref['group'] ) || $group !== $audit_ref['group'] ) {
				continue;
			}

			$audit_id = $audit_ref['id'];

			if ( empty( $audits[ $audit_id ] ) || ! \is_array( $audits[ $audit_id ] ) ) {
				continue;
			}

			$metrics[ $audit_id ] = self::format_audit_metric( $audit_id, $audits[ $audit_id ] );
		}

		return $metrics;
	}

	/**
	 * Format a single Lighthouse audit into the structure expected by the UI.
	 *
	 * @since X.X.X
	 *
	 * @param string $audit_id Lighthouse audit identifier.
	 * @param array  $audit    Lighthouse audit payload.
	 *
	 * @return array
	 */
	private static function format_audit_metric( $audit_id, $audit ) {
		$metric = array(
			'id'               => $audit_id,
			'title'            => $audit['title'] ?? null,
			'description'      => $audit['description'] ?? null,
			'score'            => $audit['score'] ?? null,
			'scoreDisplayMode' => $audit['scoreDisplayMode'] ?? null,
			'displayValue'     => $audit['displayValue'] ?? null,
			'details'          => self::extract_audit_details( $audit['details'] ?? array() ),
		);

		if ( 'network-dependency-tree-insight' === $audit_id ) {
			$metric['networkDependency'] = self::format_network_dependency_details( $audit['details'] ?? array() );
			$metric['details']           = array();
		}

		$types = self::resolve_metric_types( $audit_id, $audit );
		if ( ! empty( $types ) ) {
			$metric['type'] = $types;
		}

		return $metric;
	}

	/**
	 * Normalize Lighthouse audit details to a list structure.
	 *
	 * @since X.X.X
	 *
	 * @param mixed $details Lighthouse audit details.
	 *
	 * @return array
	 */
	private static function extract_audit_details( $details ) {
		if ( empty( $details ) || ! \is_array( $details ) ) {
			return array();
		}

		if ( isset( $details['items'] ) && \is_array( $details['items'] ) ) {
			return $details['items'];
		}

		$alternative_keys = array( 'chains', 'nodes', 'entries', 'timings' );
		foreach ( $alternative_keys as $key ) {
			if ( isset( $details[ $key ] ) && \is_array( $details[ $key ] ) ) {
				return $details[ $key ];
			}
		}

		return array( $details );
	}

	/**
	 * Determine which Core Web Vitals an audit influences.
	 *
	 * @since X.X.X
	 *
	 * @param string $audit_id Lighthouse audit identifier.
	 * @param array  $audit    Lighthouse audit payload.
	 *
	 * @return array
	 */
	private static function resolve_metric_types( $audit_id, $audit ) {
		$type_map = self::get_metric_type_map();
		$types    = array();

		if ( isset( $type_map[ $audit_id ] ) ) {
			$types = $type_map[ $audit_id ];
		}

		if ( empty( $types ) && isset( $audit['metricSavings'] ) && \is_array( $audit['metricSavings'] ) ) {
			foreach ( $audit['metricSavings'] as $metric => $value ) {
				if ( \in_array( $metric, array( 'FCP', 'LCP', 'TBT', 'CLS' ), true ) ) {
					$types[] = $metric;
				}
			}
		}

		return \array_values( \array_unique( $types ) );
	}

	/**
	 * Normalize the network dependency tree insight payload.
	 *
	 * @since X.X.X
	 *
	 * @param array $details Lighthouse network dependency tree details payload.
	 *
	 * @return array
	 */
	private static function format_network_dependency_details( $details ) {
		if ( empty( $details ) || ! \is_array( $details ) ) {
			return array();
		}

		$items                = $details['items'] ?? array();
		$tree_section         = $items[0]['value'] ?? array();
		$preconnected_section = $items[1] ?? array();
		$candidates_section   = $items[2] ?? array();
		$chains               = $tree_section['chains'] ?? array();
		$normalized_chains    = array();

		if ( ! empty( $chains ) && \is_array( $chains ) ) {
			foreach ( $chains as $chain ) {
				$normalized_chains[] = self::normalize_network_chain_node( $chain );
			}
		}

		return array(
			'longestChainDuration' => $tree_section['longestChain']['duration'] ?? null,
			'chains'               => $normalized_chains,
			'preconnected'         => self::format_preconnect_section( $preconnected_section ),
			'candidates'           => self::format_preconnect_section( $candidates_section ),
		);
	}

	/**
	 * Normalize a network dependency chain node recursively.
	 *
	 * @since X.X.X
	 *
	 * @param array $node Node payload.
	 *
	 * @return array
	 */
	private static function normalize_network_chain_node( $node ) {
		$children = array();

		if ( ! empty( $node['children'] ) && \is_array( $node['children'] ) ) {
			foreach ( $node['children'] as $child ) {
				$children[] = self::normalize_network_chain_node( $child );
			}
		}

		return array(
			'url'          => $node['url'] ?? '',
			'duration'     => $node['navStartToEndTime'] ?? null,
			'transferSize' => $node['transferSize'] ?? null,
			'isLongest'    => (bool) ( $node['isLongest'] ?? false ),
			'children'     => $children,
		);
	}

	/**
	 * Normalize preconnect insight sections.
	 *
	 * @since X.X.X
	 *
	 * @param array $section Section payload from Lighthouse.
	 *
	 * @return array
	 */
	private static function format_preconnect_section( $section ) {
		if ( empty( $section ) || ! \is_array( $section ) ) {
			return array();
		}

		$value = $section['value'] ?? array();
		$data  = array(
			'title'       => $section['title'] ?? '',
			'description' => $section['description'] ?? '',
			'entries'     => array(),
		);

		if ( isset( $value['value'] ) && ! empty( $value['value'] ) && \is_string( $value['value'] ) ) {
			$data['entries'] = $value['value'];
			return $data;
		}

		if ( isset( $value['items'] ) && \is_array( $value['items'] ) ) {
			$entries = array();
			foreach ( $value['items'] as $item ) {
				if ( \is_string( $item ) ) {
					$entries[] = $item;
				} elseif ( isset( $item['origin'] ) ) {
					$entries[] = $item['origin'];
				} elseif ( isset( $item['value'] ) && \is_string( $item['value'] ) ) {
					$entries[] = $item['value'];
				}
			}

			if ( ! empty( $entries ) ) {
				$data['entries'] = $entries;
			} elseif ( ! empty( $value ) ) {
				$data['entries'] = \wp_json_encode( $value );
			}
		} elseif ( ! empty( $value ) && \is_string( $value ) ) {
			$data['entries'] = $value;
		}

		return $data;
	}

	/**
	 * Provide a mapping of audit identifiers to Core Web Vital type tags.
	 *
	 * @since X.X.X
	 *
	 * @return array
	 */
	private static function get_metric_type_map() {
		return array(
			'render-blocking-insight'         => array( 'FCP', 'LCP' ),
			'render-blocking-resources'       => array( 'FCP', 'LCP' ),
			'unused-css-rules'                => array( 'FCP', 'LCP' ),
			'unminified-css'                  => array( 'FCP', 'LCP' ),
			'unminified-javascript'           => array( 'FCP', 'LCP' ),
			'unused-javascript'               => array( 'LCP' ),
			'uses-text-compression'           => array( 'FCP', 'LCP' ),
			'uses-rel-preconnect'             => array( 'FCP', 'LCP' ),
			'server-response-time'            => array( 'FCP', 'LCP' ),
			'redirects'                       => array( 'FCP', 'LCP' ),
			'efficient-animated-content'      => array( 'LCP' ),
			'duplicated-javascript'           => array( 'TBT' ),
			'duplicated-javascript-insight'   => array( 'TBT' ),
			'legacy-javascript'               => array( 'TBT' ),
			'legacy-javascript-insight'       => array( 'TBT' ),
			'total-byte-weight'               => array( 'LCP' ),
			'dom-size'                        => array( 'TBT' ),
			'dom-size-insight'                => array( 'TBT' ),
			'bootup-time'                     => array( 'TBT' ),
			'mainthread-work-breakdown'       => array( 'TBT' ),
			'third-party-summary'             => array( 'TBT' ),
			'third-parties-insight'           => array( 'TBT' ),
			'third-party-facades'             => array( 'TBT' ),
			'non-composited-animations'       => array( 'CLS' ),
			'unsized-images'                  => array( 'CLS' ),
			'cls-culprits-insight'            => array( 'CLS' ),
			'font-display'                    => array( 'FCP', 'LCP' ),
			'font-display-insight'            => array( 'FCP', 'LCP' ),
			'cache-insight'                   => array( 'FCP', 'LCP' ),
			'document-latency-insight'        => array( 'FCP', 'LCP' ),
			'network-dependency-tree-insight' => array( 'FCP', 'LCP' ),
			'viewport'                        => array( 'TBT' ),
			'viewport-insight'                => array( 'TBT' ),
			'lcp-breakdown-insight'           => array( 'LCP' ),
			'lcp-discovery-insight'           => array( 'LCP' ),
			'image-delivery-insight'          => array( 'LCP' ),
			'forced-reflow-insight'           => array( 'TBT' ),
		);
	}

	/**
	 * Normalize score values to 0-100 scale while avoiding PHP warnings when score is missing.
	 *
	 * @since X.X.X
	 *
	 * @param mixed $score Score from the Lighthouse payload.
	 *
	 * @return int
	 */
	private static function normalize_score( $score ) {
		if ( ! isset( $score ) || ! \is_numeric( $score ) ) {
			return 0;
		}

		return $score * 100;
	}

	/**
	 * Drop metrics that Google didn't include in the latest payload.
	 *
	 * @since X.X.X
	 *
	 * @param array $metrics Raw metrics bucket.
	 *
	 * @return array
	 */
	private static function filter_metrics_by_title( $metrics ) {
		if ( empty( $metrics ) || ! \is_array( $metrics ) ) {
			return array();
		}

		return \array_filter(
			$metrics,
			static function ( $metric ) {
				return \is_array( $metric ) && isset( $metric['title'] ) && '' !== $metric['title'];
			}
		);
	}

	/**
	 * Attach instructions for metrics that survived the filtering step.
	 *
	 * @since X.X.X
	 *
	 * @param array $pagespeed_data Prepared PageSpeed data.
	 *
	 * @return array
	 */
	private static function merge_instructions( $pagespeed_data ) {
		$instructions        = PageSpeed_Instructions::get_pagespeed_instructions();
		$default_instruction = '<p>' . \esc_html__( 'W3 Total Cache does not yet have guidance for this audit.', 'w3-total-cache' ) . '</p>';

		foreach ( array( 'insights', 'diagnostics' ) as $bucket ) {
			if ( empty( $pagespeed_data[ $bucket ] ) ) {
				continue;
			}

			if ( empty( $instructions[ $bucket ] ) ) {
				foreach ( $pagespeed_data[ $bucket ] as $key => $metric ) {
					$pagespeed_data[ $bucket ][ $key ]['instructions'] = $default_instruction;
				}
				continue;
			}

			foreach ( $pagespeed_data[ $bucket ] as $key => $metric ) {
				if ( isset( $instructions[ $bucket ][ $key ] ) ) {
					$pagespeed_data[ $bucket ][ $key ] = \array_merge(
						$metric,
						$instructions[ $bucket ][ $key ]
					);
				} else {
					$pagespeed_data[ $bucket ][ $key ]['instructions'] = $default_instruction;
				}
			}
		}

		return $pagespeed_data;
	}
}
