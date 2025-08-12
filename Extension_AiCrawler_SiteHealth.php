<?php
/**
 * File: Extension_AiCrawler_SiteHealth.php
 *
 * @package W3TC
 * @since   X.X.X
 */

namespace W3TC;

/**
 * Class: Extension_AiCrawler_SiteHealth
 *
 * @since X.X.X
 */
class Extension_AiCrawler_SiteHealth {
	/**
	 * Retrieves the AI Crawler status based on the provided tests.
	 *
	 * This method is used to gather and return the status of the AI Crawler health
	 * by running a test.
	 *
	 * @since x.x.x
	 *
	 * @param array $tests Optional. An array of test identifiers to run. Defaults to an empty array.
	 *
	 * @return mixed The site health status based on the executed tests.
	 */
	public static function get_sitehealth_status( $tests = array() ) {
		$tests['direct']['bg_ai_crawler'] = array(
			'label' => __( 'W3TC - AI Crawler', 'w3-total-cache' ),
			'test'  => array( '\W3TC\Extension_AiCrawler_SiteHealth', 'run_sitehealth_status_test' ), // <-- new method below
		);

		return $tests;
	}

	/**
	 * Executes the status test for the AiCrawler extension.
	 *
	 * This method is used to run diagnostics and provide status information
	 * related to the AiCrawler functionality within the W3 Total Cache plugin.
	 *
	 * @since x.x.x
	 *
	 * @return array AI Crawler sitehealth status test results.
	 */
	public static function run_sitehealth_status_test() {
		if ( \W3TC\Extension_AiCrawler_Util::is_enabled() ) {
			$html   = self::get_sitehealth_status_html();
			$status = self::get_sitehealth_status_from_html( $html );

			return array(
				'label'       => __( 'W3TC - AI Crawler', 'w3-total-cache' ),
				'status'      => $status,
				'badge'       => array(
					'label' => __( 'Performance', 'w3-total-cache' ),
					'color' => 'blue',
				),
				'description' => $html ? $html : esc_html__( 'No summary available.', 'w3-total-cache' ),
				'test'        => 'bg_ai_crawler',
			);
		}

		return array(
			'label'       => __( 'W3TC - AI Crawler', 'w3-total-cache' ),
			'status'      => 'recommended',
			'badge'       => array(
				'label' => __( 'W3TC - AI Crawler', 'w3-total-cache' ),
				'color' => 'blue',
			),
			'description' => wp_kses_post(
				__( 'The W3TC - AI Crawler extension isn’t active, so no report is available. Activate it to see the crawl summary here.', 'w3-total-cache' )
			),
			'actions'     => sprintf(
				'<a class="button button-primary" href="%s">%s</a>',
				esc_url( Util_Ui::admin_url( 'admin.php?page=w3tc_extensions#aicrawler' ) ),
				esc_html__( 'Activate', 'w3-total-cache' )
			),
			'test'        => 'bg_ai_crawler',
		);
	}

	/**
	 * Retrieves the HTML content for the AI Crawler report.
	 *
	 * This method is used to generate and return the HTML structure
	 * for displaying the AI Crawler report in the admin interface.
	 *
	 * @since x.x.x
	 *
	 * @return string The HTML content of the AI Crawler report.
	 */
	private static function get_sitehealth_status_html() {
		// The render method ECHOS; capture only the buffer, ignore returns.
		ob_start();

		// @todo Remove true flag once API is reliable.
		\W3TC\Extension_AiCrawler_Util::render_report_summary();

		$buffered = ob_get_clean();

		// Allow reasonable markup for Site Health cards.
		$allowed = wp_kses_allowed_html( 'post' );

		return wp_kses( (string) $buffered, $allowed );
	}

	/**
	 * Classifies the status of the AI crawler based on the provided HTML content.
	 *
	 * This method analyzes the given HTML string and determines the status
	 * of the AI crawler. The classification logic is implemented within
	 * this function.
	 *
	 * @since x.x.x
	 *
	 * @param string $html The HTML content to be analyzed for status classification.
	 *
	 * @return string The site health status classification of the AI crawler.
	 */
	private static function get_sitehealth_status_from_html( $html ) {
		if (
			strpos( $html, 'w3tc-aicrawler-report-circle-red' ) !== false
			|| strpos( $html, 'w3tc-aicrawler-report-circle-yellow' ) !== false
		) {
			return 'recommended';
		}

		// All green, or no circles found.
		return 'good';
	}

	/**
	 * Retrieves site health debug information.
	 *
	 * This method is used to gather and return debug information
	 * related to the site's health. It accepts an optional array
	 * of existing debug information and appends additional data
	 * as needed.
	 *
	 * @since x.x.x
	 *
	 * @param array $info Optional. An array of existing debug information. Default is an empty array.
	 *
	 * @return array An array containing the site health debug information.
	 */
	public static function get_sitehealth_debug_info( $info = array() ) {
		$files = array(
			'label'  => __( 'W3TC - AI Crawler', 'w3-total-cache' ),
			'fields' => array(),
		);

		if ( \W3TC\Extension_AiCrawler_Util::is_enabled() ) {
			$data = self::get_report_summary_struct();

			if ( ! empty( $data['error'] ) ) {
				$files['fields']['error'] = array(
					'label'   => __( 'Error', 'w3-total-cache' ),
					'value'   => $data['error'],
					'private' => true,
				);
			} else {
				// Files breakdown (second table).
				if ( ! empty( $data['items'] ) && is_array( $data['items'] ) ) {
					$items = $data['items'];

					// Optional: sort by status then file name.
					$order = array(
						'Critical'        => 0,
						'Missing'         => 1,
						'Needs attention' => 2,
						'OK'              => 3,
						''                => 9,
					);
					usort(
						$items,
						static function ( $a, $b ) use ( $order ) {
							$sa = isset( $a['status'] ) ? (string) $a['status'] : '';
							$sb = isset( $b['status'] ) ? (string) $b['status'] : '';
							$oa = $order[ $sa ] ?? 9;
							$ob = $order[ $sb ] ?? 9;
							if ( $oa === $ob ) {
								return strcasecmp( (string) ( $a['file'] ?? '' ), (string) ( $b['file'] ?? '' ) );
							}
							return $oa <=> $ob;
						}
					);

					$idx = 0;
					foreach ( $items as $i ) {
						$file = isset( $i['file'] ) ? trim( wp_strip_all_tags( (string) $i['file'] ) ) : '';

						if ( '' === $file ) {
							continue;
						}

						$status = isset( $i['status'] ) ? trim( wp_strip_all_tags( (string) $i['status'] ) ) : '';
						$note   = isset( $i['note'] ) ? trim( wp_strip_all_tags( (string) $i['note'] ) ) : '';

						++$idx;

						$files['fields'][ 'file_' . $idx ] = array(
							'label'   => $file,
							'value'   => $status . ( '' !== $note ? ' — ' . $note : '' ),
							'private' => true,
						);
					}
				}

				if ( empty( $files['fields'] ) ) {
					$files['fields']['empty'] = array(
						'label' => __( 'Files', 'w3-total-cache' ),
						'value' => __( 'No file details to display.', 'w3-total-cache' ),
					);
				}
			}
		} else {
			$files['fields']['empty'] = array(
				'value' => __( 'Extension inactive; no file status details available.', 'w3-total-cache' ),
			);
		}

		// Register BOTH sections so they render as two separate tables.
		$info['ai_crawler'] = $files;

		return $info;
	}

	/**
	 * Return a structured, Info-safe summary of the report.
	 *
	 * Use this for Site Health → Info. Arrays render as lists;
	 * no HTML is included.
	 *
	 * @since x.x.x
	 *
	 * @param string|null $dummy Optional testing override ('mixed' or 'all_good').
	 * @param int         $limit Max items to include.
	 *
	 * @return array { ok:int, warn:int, crit:int, items:array<int, array{file,status,note}>, error:string }
	 */
	private static function get_report_summary_struct( $dummy = null, $limit = 10 ) {
		$response = \W3TC\Extension_AiCrawler_Util::get_report_response( $dummy );

		if ( empty( $response['success'] ) || empty( $response['data']['report'] ) ) {
			return array(
				'ok'    => 0,
				'warn'  => 0,
				'crit'  => 0,
				'items' => array(),
				'error' => __( 'Unable to generate report at this time', 'w3-total-cache' ),
			);
		}

		$report = $response['data']['report'];

		$ok    = 0;
		$warn  = 0;
		$crit  = 0;
		$items = array();

		foreach ( $report as $url => $data ) {
			$present    = ! empty( $data['present'] );
			$sufficient = ! empty( $data['sufficient'] );
			$color      = $present && $sufficient ? 'green' : ( $present ? 'yellow' : 'red' );

			if ( 'green' === $color ) {
				++$ok;
			} elseif ( 'yellow' === $color ) {
				++$warn;
			} else {
				++$crit;
			}

			$items[] = array(
				'file'   => basename( wp_parse_url( $url, PHP_URL_PATH ) ),
				'status' => ( 'green' === $color ) ? __( 'OK', 'w3-total-cache' )
					: ( 'yellow' === $color ? __( 'Needs attention', 'w3-total-cache' ) : __( 'Missing', 'w3-total-cache' ) ),
				'note'   => ! empty( $data['evaluation'] ) ? wp_strip_all_tags( (string) $data['evaluation'] ) : '',
			);

			if ( count( $items ) >= $limit ) {
				break;
			}
		}

		return array(
			'ok'    => $ok,
			'warn'  => $warn,
			'crit'  => $crit,
			'items' => $items,
			'error' => '',
		);
	}
}
