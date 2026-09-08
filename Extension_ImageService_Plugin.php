<?php
/**
 * File: Extension_ImageService_Plugin.php
 *
 * @since 2.2.0
 *
 * @package W3TC
 *
 * phpcs:disable WordPress.WP.CronInterval
 */

namespace W3TC;

defined( 'ABSPATH' ) || exit;
if ( ! defined( 'W3TC' ) ) {
	die();
}

/**
 * Extension_ImageService_Plugin
 *
 * @since 2.2.0
 */
class Extension_ImageService_Plugin {
	/**
	 * Image Service API object.
	 *
	 * @since 2.2.0
	 *
	 * @static
	 *
	 * @var Extension_ImageService_Api
	 */
	public static $api;

	/**
	 * Add hooks.
	 *
	 * @since 2.2.0
	 * @static
	 */
	public static function wp_loaded() {
		add_action(
			'w3tc_extension_load_admin',
			array(
				'\W3TC\Extension_ImageService_Plugin_Admin',
				'w3tc_extension_load_admin',
			)
		);

		// Cron event handling.
		require_once __DIR__ . '/Extension_ImageService_Cron.php';

		add_action(
			'w3tc_imageservice_cron',
			array(
				'\W3TC\Extension_ImageService_Cron',
				'run',
			)
		);

		add_action(
			'init',
			array( '\W3TC\Extension_ImageService_Cron', 'register_cron' )
		);
	}

	/**
	 * Get the Image Service API object.
	 *
	 * @since 2.2.0
	 *
	 * @return Extension_ImageService_Api
	 */
	public static function get_api() {
		if ( is_null( self::$api ) ) {
			require_once __DIR__ . '/Extension_ImageService_Api.php';
			self::$api = new Extension_ImageService_Api();
		}

		return self::$api;
	}

	/**
	 * Whether AVIF conversion is enabled.
	 *
	 * Default false. The Pro companion returns true when licensed and the
	 * imageservice.avif setting is on (unset defaults to on for Pro).
	 *
	 * @since 2.10.5
	 *
	 * @param object|null $w3tc_config Config.
	 * @return bool
	 */
	public static function is_avif_enabled( $w3tc_config = null ) {
		if ( ! is_object( $w3tc_config ) ) {
			if ( ! class_exists( '\W3TC\Dispatcher' ) ) {
				return false;
			}

			$w3tc_config = Dispatcher::config();
		}

		return (bool) apply_filters( 'w3tc_imageservice_avif_enabled', false, $w3tc_config );
	}
}

w3tc_add_action(
	'wp_loaded',
	array(
		'\W3TC\Extension_ImageService_Plugin',
		'wp_loaded',
	)
);
