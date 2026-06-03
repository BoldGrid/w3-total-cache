<?php
/**
 * File: Extensions_Util.php
 *
 * @package W3TC
 */

namespace W3TC;

/**
 * Class: Extensions_Util
 */
class Extensions_Util {
	/**
	 * Get registered extensions
	 *
	 * @static
	 *
	 * @param Config $config Configuration object.
	 * @return array
	 */
	public static function get_extensions( $config ) {
		return apply_filters( 'w3tc_extensions', __return_empty_array(), $config );
	}

	/**
	 * Get registered extension
	 *
	 * @static
	 *
	 * @param Config $config Configuration object.
	 * @param string $extension Extension.
	 * @return array
	 */
	public static function get_extension( $config, $extension ) {
		$exts = self::get_extensions( $config );

		if ( ! isset( $exts[ $extension ] ) ) {
			return null;
		}

		return $exts[ $extension ];
	}

	/**
	 * Returns the inactive extensions
	 *
	 * @static
	 *
	 * @param Config $config Configuration object.
	 * @return array
	 */
	public static function get_inactive_extensions( $config ) {
		$extensions        = self::get_extensions( $config );
		$config            = Dispatcher::config();
		$active_extensions = $config->get_array( 'extensions.active' );

		return array_diff_key( $extensions, $active_extensions );
	}

	/**
	 * Returns the active extensions.
	 *
	 * @static
	 *
	 * @param Config $config Configuration object.
	 * @return array
	 */
	public static function get_active_extensions( $config ) {
		$extensions        = self::get_extensions( $config );
		$extensions_keys   = array_keys( $extensions );
		$config            = Dispatcher::config();
		$active_extensions = $config->get_array( 'extensions.active' );

		return array_intersect_key( $extensions, $active_extensions );
	}

	/**
	 * Activate extension.
	 *
	 * @static
	 *
	 * @param string $extension        Extension.
	 * @param Config $w3_config        Configuration object.
	 * @param bool   $dont_save_config Whether or not to save configuration.  Default: false.
	 * @return bool
	 */
	public static function activate_extension( $extension, $w3_config, $dont_save_config = false ) {
		$all_extensions = self::get_extensions( $w3_config );
		$extensions     = $w3_config->get_array( 'extensions.active' );

		if ( ! $w3_config->is_extension_active( $extension ) ) {
			$meta = $all_extensions[ $extension ];

			$filename = W3TC_EXTENSION_DIR . '/' . trim( $meta['path'], '/' );

			if ( ! file_exists( $filename ) ) {
				return false;
			}

			include $filename;

			$extensions[ $extension ] = $meta['path'];

			ksort( $extensions, SORT_STRING );

			$w3_config->set( 'extensions.active', $extensions );

			// if extensions doesnt want to control frontend activity - activate it there too.
			if ( ! isset( $meta['active_frontend_own_control'] ) || ! $meta['active_frontend_own_control'] ) {
				$w3_config->set_extension_active_frontend( $extension, true );
			}

			// Check for Image Service extension status changes.
			if ( 'imageservice' === $extension ) {
				$w3_config->set( 'extension.imageservice', true );
			}

			// Save the config, unless told not to.
			try {
				if ( ! $dont_save_config ) {
					$w3_config->save();
				}

				// Set transient for displaying activation notice.
				set_transient( 'w3tc_activation_' . $extension, true, DAY_IN_SECONDS );

				/**
				 * Audit only after the change actually persists. When
				 * `$dont_save_config` is true the caller (typically a
				 * batch toggle handler) is responsible for the final
				 * save; the activation isn't durable yet, so emit a
				 * distinct `extension_activate_pending` event instead
				 * of the success event. Otherwise an audit subscriber
				 * can see `extension_activated` for a toggle that the
				 * later batched save dropped on the floor.
				 */
				Util_Debug::audit_log(
					$dont_save_config ? 'extension_activate_pending' : 'extension_activated',
					array( 'extension' => $extension )
				);

				return true;
			} catch ( \Exception $ex ) {
				Util_Debug::audit_log(
					'extension_activate_failed',
					array(
						'extension' => $extension,
						'message'   => $ex->getMessage(),
					)
				);
				return false;
			}
		}

		return false;
	}


	/**
	 * Deactivate extension.
	 *
	 * @static
	 *
	 * @param string $extension        Extension.
	 * @param Config $config           Configuration object.
	 * @param bool   $dont_save_config Whether or not to save configuration.  Default: false.
	 * @return bool
	 */
	public static function deactivate_extension( $extension, $config, $dont_save_config = false ) {
		$extensions = $config->get_array( 'extensions.active' );

		/**
		 * Distinguish a real persisted state change from a no-op call.
		 * `deactivate_extension( 'foo', $config )` against a `foo` that
		 * isn't in `extensions.active` should not emit
		 * `extension_deactivated`; emitting one would surface a state
		 * change to audit subscribers when no state change happened.
		 */
		$was_active = array_key_exists( $extension, $extensions );

		if ( $was_active ) {
			unset( $extensions[ $extension ] );
			ksort( $extensions, SORT_STRING );
			$config->set( 'extensions.active', $extensions );
		}

		$config->set_extension_active_frontend( $extension, false );

		// Check for Image Service extension status changes.
		if ( 'imageservice' === $extension ) {
			$config->set( 'extension.imageservice', false );
		}

		// Save the config, unless told not to.
		try {
			if ( ! $dont_save_config ) {
				$config->save();
			}

			// Delete transient for displaying activation notice.
			delete_transient( 'w3tc_activation_' . $extension );

			do_action( 'w3tc_deactivate_extension_' . $extension );

			/**
			 * Audit only after the change actually persists AND only
			 * when a state change actually happened. `extension_deactivate_pending`
			 * is emitted when the caller will save later (batched
			 * toggle handler); `extension_deactivate_noop` covers the
			 * no-op call against an already-inactive extension.
			 */
			if ( ! $was_active ) {
				$event = 'extension_deactivate_noop';
			} elseif ( $dont_save_config ) {
				$event = 'extension_deactivate_pending';
			} else {
				$event = 'extension_deactivated';
			}
			Util_Debug::audit_log(
				$event,
				array( 'extension' => $extension )
			);

			return true;
		} catch ( \Exception $ex ) {
			Util_Debug::audit_log(
				'extension_deactivate_failed',
				array(
					'extension' => $extension,
					'message'   => $ex->getMessage(),
				)
			);
			return false;
		}

		return false;
	}
}
