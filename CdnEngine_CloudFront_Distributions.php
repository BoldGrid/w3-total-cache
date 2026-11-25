<?php
/**
 * File: CdnEngine_CloudFront_Distributions.php
 *
 * @package W3TC
 */

namespace W3TC;

defined( 'ABSPATH' ) || exit;

/**
 * Class CdnEngine_CloudFront_Distributions
 *
 * Lists CloudFront distributions across all result pages. Shared by the
 * S3-origin (CdnEngine_CloudFront) and mirror (CdnEngine_Mirror_CloudFront) engines.
 *
 * @since X.X.X
 */
class CdnEngine_CloudFront_Distributions {
	/**
	 * Maximum number of pages to request, as a guard against marker cycles.
	 *
	 * @since X.X.X
	 *
	 * @var int
	 */
	const MAX_PAGES = 1000;

	/**
	 * Lists all CloudFront distributions, following pagination markers.
	 *
	 * CloudFront returns at most 100 distributions per request. Paging stops when the
	 * response is not truncated, when NextMarker is missing or unchanged, or after
	 * MAX_PAGES requests.
	 *
	 * @since X.X.X
	 *
	 * @param object $api CloudFront client (\Aws\CloudFront\CloudFrontClient).
	 *
	 * @return array List of distribution summaries.
	 */
	public static function list_all( $api ) {
		$all_dists = array();
		$marker    = null;

		for ( $page = 0; $page < self::MAX_PAGES; $page++ ) {
			$params = array( 'MaxItems' => 100 );
			if ( ! empty( $marker ) ) {
				$params['Marker'] = $marker;
			}

			$result = $api->listDistributions( $params );

			if ( ! empty( $result['DistributionList']['Items'] ) ) {
				$all_dists = \array_merge( $all_dists, $result['DistributionList']['Items'] );
			}

			$is_truncated = ! empty( $result['DistributionList']['IsTruncated'] );
			$next_marker  = ( $is_truncated && ! empty( $result['DistributionList']['NextMarker'] ) ) ?
				$result['DistributionList']['NextMarker'] : null;

			// Stop if there is no next page, or the marker did not advance.
			if ( empty( $next_marker ) || $next_marker === $marker ) {
				break;
			}

			$marker = $next_marker;
		}

		return $all_dists;
	}
}
