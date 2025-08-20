<?php
/**
 * File: Extension_AiCrawler_Util.php
 *
 * @package W3TC
 *
 * @since   X.X.X
 */

namespace W3TC;

/**
 * Class: Extension_AiCrawler_Util
 *
 * @since X.X.X
 *
 * Silence preg_match warnings when checking excludes in the event a value isn't a valid regex
 * phpcs:disable WordPress.PHP.NoSilencedErrors.Discouraged
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
	 * Determine if the provided post or URL matches any exclusion rules.
	 *
	 * Exclusion rules are defined in the AI Crawler settings and
	 * include absolute/relative/regex URLs, built-in post types, and custom post
	 * type slugs. If the post or its associated URL matches any of
	 * the configured exclusions, the item should not be sent to the
	 * API for markdown generation.
	 *
	 * @since X.X.X
	 *
	 * @param int|string $item Post ID or URL to check against exclusions.
	 *
	 * @return bool True if the item should be excluded, otherwise false.
	 */
	public static function is_excluded( $item ) {
		$allowed = self::filter_excluded( array( $item ) );

		return empty( $allowed );
	}

	/**
	 * Filter out any post IDs or URLs that match exclusion rules.
	 *
	 * This helper accepts an array of post IDs or URLs and returns
	 * the subset that are permitted for markdown generation. URL
	 * rules may be full URLs, partial URLs with wildcards ("*") or
	 * complete regular expressions. It is optimized for bulk
	 * operations such as regenerating all entries from a sitemap.
	 *
	 * @since X.X.X
	 *
	 * @param array $items Array of post IDs or URLs.
	 *
	 * @return array Filtered array containing only non-excluded items.
	 */
	public static function filter_excluded( array $items ) {
		$config = Dispatcher::config();

		$excluded_urls_raw = (array) $config->get_array( array( 'aicrawler', 'exclusions' ), array() );
		$excluded_urls     = array();

		foreach ( $excluded_urls_raw as $rule ) {
			$rule = trim( $rule );
			if ( '' !== $rule ) {
				$excluded_urls[] = $rule;
			}
		}

		// Precompile matchers (regex or substring).
		$matchers = self::compile_matchers( $excluded_urls );

		$pts_array  = array_keys( (array) $config->get_array( array( 'aicrawler', 'exclusions_pts' ), array() ) );
		$cpts_array = (array) $config->get_array( array( 'aicrawler', 'exclusions_cpts' ), array() );

		$excluded_pts = array_fill_keys(
			array_filter(
				array_map(
					'trim',
					array_merge( $pts_array, $cpts_array )
				)
			),
			true
		);

		$allowed = array();

		foreach ( $items as $item ) {
			$post_id = is_numeric( $item ) ? absint( $item ) : url_to_postid( $item );
			$url     = is_numeric( $item ) ? (string) get_permalink( $post_id ) : (string) esc_url_raw( $item );

			$exclude = false;

			// URL-based exclusions.
			foreach ( $matchers as $matches ) {
				if ( $matches( $url ) ) {
					$exclude = true;
					break;
				}
			}

			// Post type exclusions.
			if ( ! $exclude && $post_id ) {
				$post_type = get_post_type( $post_id );
				if ( $post_type && isset( $excluded_pts[ $post_type ] ) ) {
					$exclude = true;
				}
			}

			if ( ! $exclude ) {
				$allowed[] = $item;
			}
		}

		return $allowed;
	}

	/**
	 * Compiles an array of raw rules into matchers.
	 *
	 * This method processes the provided array of raw rules and converts them
	 * into a format suitable for use as matchers.
	 *
	 * @param array $raw_rules An array of raw rules to be compiled into matchers.
	 *
	 * @return array An array of compiled matchers.
	 */
	private static function compile_matchers( array $raw_rules ): array {
		$matchers = array();

		foreach ( $raw_rules as $raw ) {
			$rule = trim( (string) $raw );
			if ( '' === $rule ) {
				continue;
			}

			// If it compiles, treat as regex.
			if ( @preg_match( $rule, '' ) !== false ) {
				$matchers[] = static function ( string $url ) use ( $rule ): bool {
					$match = @preg_match( $rule, $url );
					return 1 === $match;
				};
			} else {
				// Plain text: case-insensitive partial (substring) match.
				$needle     = $rule;
				$matchers[] = static function ( string $url ) use ( $needle ): bool {
					return stripos( $url, $needle ) !== false;
				};
			}
		}

		return $matchers;
	}
}
