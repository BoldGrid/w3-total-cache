<?php
/**
 * File: Cdn_VaryCache.php
 *
 * @package W3TC
 */

namespace W3TC;

/**
 * Ensures BunnyCDN and Total CDN pull zones vary cache by image format when the
 * Image Service extension is active.
 */
class Cdn_VaryCache {
		/**
		 * Enable Vary Cache for WebP/AVIF when possible.
		 *
		 * @return void
		 */
	public static function maybe_set_vary() {
		$config     = Dispatcher::config();
		$state      = Dispatcher::config_state();
		$cdn_engine = $config->get_string( 'cdn.engine' );
Util_Debug::debug('applyvary',true);
		if ( ! in_array( $cdn_engine, array( 'bunnycdn', 'totalcdn' ), true ) ) {
			return;
		}

		if ( ! $config->is_extension_active( 'imageservice' ) ) {
			return;
		}

		$configured_key = 'cdn.' . $cdn_engine . '.vary_configured';
		if ( $state->get_boolean( $configured_key ) ) {
			//return;
		}

		$pull_zone_id = $config->get_integer( 'cdn.' . $cdn_engine . '.pull_zone_id' );
		$api_key      = $config->get_string( 'cdn.' . $cdn_engine . '.account_api_key' );

		if ( ! $pull_zone_id || ! $api_key ) {
			return;
		}

		try {
			Util_Debug::debug('setapi',true);
			if ( 'bunnycdn' === $cdn_engine ) {
				require_once W3TC_DIR . '/Cdn_BunnyCdn_Api.php';
				$api = new Cdn_BunnyCdn_Api(
					array(
						'account_api_key' => $api_key,
						'pull_zone_id'    => $pull_zone_id,
					)
				);
			} else {
				require_once W3TC_DIR . '/Cdn_TotalCdn_Api.php';
				$api = new Cdn_TotalCdn_Api(
					array(
						'account_api_key' => $api_key,
						'pull_zone_id'    => $pull_zone_id,
					)
				);
			}

			Util_Debug::debug('updatepullzone',true);
			$result = $api->update_pull_zone(
				$pull_zone_id,
				array(
					'EnableWebpVary' => true,
					'EnableAvifVary' => true,
				)
			);
			Util_Debug::debug('pullzoneresult',$result);
			if ( ! is_wp_error( $result ) ) {
				$state->set( $configured_key, true );
				$state->save();
			}
		} catch ( \Exception $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
			// Failing silently; admin notice will inform the user.
		}
	}
}
