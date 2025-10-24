<?php
/**
 * File: ConfigUtil.php
 *
 * @package W3TC
 */

namespace W3TC;

/**
 * Class ConfigUtil
 *
 * phpcs:disable WordPress.PHP.NoSilencedErrors.Discouraged
 * phpcs:disable WordPress.WP.AlternativeFunctions
 */
class ConfigUtil {
	/**
	 * Checks if an item exists in the configuration storage.
	 *
	 * @param int  $blog_id ID of the blog to check for the item's existence.
	 * @param bool $preview Whether to check in the preview configuration.
	 *
	 * @return bool True if the item exists, false otherwise.
	 */
	public static function is_item_exists( $blog_id, $preview ) {
		if ( defined( 'W3TC_CONFIG_DATABASE' ) && W3TC_CONFIG_DATABASE ) {
			return ConfigDbStorage::is_item_exists( $blog_id, $preview );
		}

		return file_exists( Config::util_config_filename( 0, false ) );
	}

	/**
	 * Removes an item from the configuration storage.
	 *
	 * @param int  $blog_id ID of the blog to remove the item from.
	 * @param bool $preview Whether to remove from the preview configuration.
	 *
	 * @return void
	 */
	public static function remove_item( $blog_id, $preview ) {
		if ( defined( 'W3TC_CONFIG_DATABASE' ) && W3TC_CONFIG_DATABASE ) {
			ConfigDbStorage::remove_item( $blog_id, $preview );
		} else {
			$filename = Config::util_config_filename( $blog_id, $preview );
			@unlink( $filename );
		}

		if ( defined( 'W3TC_CONFIG_CACHE_ENGINE' ) ) {
			ConfigCache::remove_item( $blog_id, $preview );
		}
	}

	/**
	 * Copies configuration between preview and production environments.
	 *
	 * @param int $blog_id   ID of the blog for which the copy operation is performed.
	 * @param int $direction Direction of the copy. Positive for preview to production,
	 *                       negative for production to preview.
	 *
	 * @return void
	 *
	 * @throws \Util_Activation_Exception If the copy operation fails due to a write error.
	 */
	public static function preview_production_copy( $blog_id, $direction ) {
		if ( defined( 'W3TC_CONFIG_DATABASE' ) && W3TC_CONFIG_DATABASE ) {
			ConfigDbStorage::preview_production_copy( $blog_id, $direction );
		} else {
			$preview_filename    = Config::util_config_filename( $blog_id, true );
			$production_filename = Config::util_config_filename( $blog_id, false );

			if ( $direction > 0 ) {
				$src  = $preview_filename;
				$dest = $production_filename;
			} else {
				$src  = $production_filename;
				$dest = $preview_filename;
			}

			if ( ! @copy( $src, $dest ) ) {
				Util_Activation::throw_on_write_error( $dest );
			}
		}

		if ( defined( 'W3TC_CONFIG_CACHE_ENGINE' ) ) {
			ConfigCache::remove_item( $blog_id, $preview );
		}
	}

	/**
	 * Saves an item to the configuration storage.
	 *
	 * @param int   $blog_id ID of the blog to save the item to.
	 * @param bool  $preview Whether to save to the preview configuration.
	 * @param array $data    Data to be saved in the configuration.
	 *
	 * @return void
	 */
	public static function save_item( $blog_id, $preview, $data ) {
		if ( defined( 'W3TC_CONFIG_DATABASE' ) && W3TC_CONFIG_DATABASE ) {
			ConfigDbStorage::save_item( $blog_id, $preview, $data );
		} else {
			$filename = Config::util_config_filename( $blog_id, $preview );
			if ( defined( 'JSON_PRETTY_PRINT' ) ) {
				$config = wp_json_encode( $data, JSON_PRETTY_PRINT );
			} else { // for older php versions.
				$config = wp_json_encode( $data );
			}

			Util_File::file_put_contents_atomic( $filename, '<?php exit; ?>' . $config );
		}

		if ( defined( 'W3TC_CONFIG_CACHE_ENGINE' ) ) {
			ConfigCache::remove_item( $blog_id, $preview );
		}
	}
}
