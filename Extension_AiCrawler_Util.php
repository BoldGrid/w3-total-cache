<?php
/**
 * File: Extension_AiCrawler_Util.php
 *
 * @package W3TC
 * @since   X.X.X
 */

namespace W3TC;

/**
 * Class: Extension_AiCrawler_Util
 *
 * @since X.X.X
 */
class Extension_AiCrawler_Util {
	/**
	 * Gets the enabled status of the extension.
	 *
	 * @since X.X.X
	 *
	 * @return bool Whether the AI Crawler extension is active.
	 */
	public static function is_enabled() {
		$config            = Dispatcher::config();
		$extensions_active = $config->get_array( 'extensions.active' );

		// @todo: Check for Central environment and add to return.
		return array_key_exists( 'aicrawler', $extensions_active );
	}

	/**
	 * Checks if the current environment supports the AI Crawler extension.
	 *
	 * Initially the extension is limited to specific hosting platforms.
	 * This helper centralizes the environment detection so support can be
	 * expanded in the future.
	 *
	 * @since  X.X.X
	 * @static
	 *
	 * @return bool
	 */
	public static function is_allowed() {
		// @todo: add checks for valid environments.
		return true;
	}

	/**
	 * Get Dummy Report Data
	 *
	 * Returns dummy report data for testing purposes
	 * for the /report API endpoint.
	 *
	 * @return array Dummy report data.
	 * @since  X.X.X
	 */
	public static function get_dummy_report_data() {
		return array(
			'all_good' => array(
				'success'  => true,
				'url'      => home_url(),
				'report'   => array(
					home_url( '/robots.txt' )  => array(
						'present'    => true,
						'sufficient' => true,
						'evaluation' => __( 'The robots.txt file is present and well-formed.', 'w3-total-cache' ),
					),
					home_url( '/llms.txt' )    => array(
						'present'    => true,
						'sufficient' => true,
						'evaluation' => __( 'The llms.txt file is present and well-formed.', 'w3-total-cache' ),
					),
					home_url( '/sitemap.xml' ) => array(
						'present'    => true,
						'sufficient' => true,
						'evaluation' => __( 'The sitemap.xml file is present and well-formed.', 'w3-total-cache' ),
					),
				),
				'metadata' => array(),
			),
			'mixed'    => array(
				'success'  => true,
				'url'      => home_url(),
				'report'   => array(
					home_url( '/robots.txt' )  => array(
						'present'    => true,
						'sufficient' => true,
						'evaluation' => __( 'The robots.txt file is present and well-formed.', 'w3-total-cache' ),
					),
					home_url( '/llms.txt' )    => array(
						'present'    => true,
						'sufficient' => false,
						'evaluation' => __( 'The contents of the llms.txt file are malformed, and cannot be correctly parsed.', 'w3-total-cache' ),
					),
					home_url( '/sitemap.xml' ) => array(
						'present'    => false,
						'sufficient' => false,
						'evaluation' => __( 'The file was not found', 'w3-total-cache' ),
					),
				),
				'metadata' => array(),
			),
		);
	}

	/**
	 * Internal: fetch report data from API or dummy.
	 *
	 * Returns the same shape your render method expects:
	 * array( 'success' => bool, 'data' => array( 'report' => ... ) )
	 *
	 * @param string|null $dummy Optional: 'mixed' or 'all_good'. If null, honors ?aicrawler_dummy=.
	 * @return array
	 */
	public static function get_report_response( $dummy = null ) {
		// Allow a programmatic override (e.g., tests) before reading $_GET.
		$dummy = apply_filters( 'w3tc_aicrawler_dummy_param', $dummy );

		$dummy_reports = self::get_dummy_report_data();

		// Mirror render_report_summary() behavior.
		if ( null === $dummy && isset( $_GET['aicrawler_dummy'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$dummy = sanitize_text_field( wp_unslash( $_GET['aicrawler_dummy'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		}

		if ( $dummy && isset( $dummy_reports[ $dummy ] ) ) {
			return array(
				'success' => true,
				'data'    => $dummy_reports[ $dummy ],
			);
		}

		// Live call.
		return Extension_AiCrawler_Central_Api::call(
			'report',
			'POST',
			array( 'url' => home_url() )
		);
	}

	/**
	 * Render AI Crawler summary report (HTML; echoes markup).
	 *
	 * @since X.X.X
	 */
	public static function render_report_summary() {
		$response = self::get_report_response();

		$report        = array();
		$report_failed = false;

		if ( ! empty( $response['success'] ) && isset( $response['data']['report'] ) ) {
			$report = $response['data']['report'];
		} else {
			$report_failed = true;
		}
		?>
		<h3 class="w3tc-aicrawler-report-heading"><?php echo esc_html__( 'AI Crawler Summary', 'w3-total-cache' ); ?></h3>
		<?php if ( $report_failed ) : ?>
			<p class="w3tc-aicrawler-report-error"><?php echo esc_html__( 'Unable to generate report at this time', 'w3-total-cache' ); ?></p>
		<?php elseif ( ! empty( $report ) ) : ?>
			<div class="w3tc-aicrawler-report">
				<?php
				foreach ( $report as $url => $data ) :
					$file       = basename( wp_parse_url( $url, PHP_URL_PATH ) );
					$present    = ! empty( $data['present'] );
					$sufficient = ! empty( $data['sufficient'] );
					$color      = $present && $sufficient ? 'green' : ( $present ? 'yellow' : 'red' );
					$icon       = $present && $sufficient ? '&#10003;' : ( $present ? '!' : '&#10005;' );
					?>
						<div class="w3tc-aicrawler-report-item">
							<div class="w3tc-aicrawler-report-label"><?php echo esc_html( $file ); ?></div>
							<div class="w3tc-aicrawler-report-circle w3tc-aicrawler-report-circle-<?php echo esc_attr( $color ); ?>"><?php echo esc_attr( $icon ); ?></div>
							<?php if ( 'green' !== $color && ! empty( $data['evaluation'] ) ) : ?>
								<div class="w3tc-aicrawler-report-eval"><?php echo esc_html( $data['evaluation'] ); ?></div>
							<?php endif; ?>
						</div>
				<?php endforeach; ?>
			</div>
			<?php
		endif;
	}

		/**
		 * Determine if the provided URL matches any exclusion rules.
		 *
		 * Placeholder for future exclusion logic.
		 *
		 * @since X.X.X
		 *
		 * @param string $url URL to check against exclusions.
		 *
		 * @return bool True if the URL should be excluded, otherwise false.
		 */
	public static function is_url_excluded( $url ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found
			// @todo Implement exclusion filters.
			return false;
	}
}
