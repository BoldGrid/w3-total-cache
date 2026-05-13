<?php
/**
 * File: Extensions_AdminActions.php
 *
 * @package W3TC
 */

namespace W3TC;

/**
 * Class Extensions_AdminActions
 */
class Extensions_AdminActions {
	/**
	 * Activates a specified extension.
	 *
	 * @return void
	 */
	public function w3tc_extensions_activate() {
		$config = Dispatcher::config();

		$extension = Util_Request::get_string( 'w3tc_extensions_activate' );
		$ext       = Extensions_Util::get_extension( $config, $extension );

		/**
		 * Per-extension capability gate, floored at manage_options.
		 * Prevents non-admins from activating extensions even if a
		 * downstream filter lowers the per-extension cap (rt9-220).
		 *
		 * @since X.X.X
		 */
		$capability = apply_filters(
			'w3tc_capability_extensions_activate_' . $extension,
			'manage_options'
		);
		if ( ! \current_user_can( 'manage_options' ) || empty( $capability ) || ! \current_user_can( $capability ) ) {
			wp_die(
				\esc_html__( 'You do not have sufficient permissions to perform this action.', 'w3-total-cache' ),
				'',
				array( 'response' => 403 )
			);
		}

		if ( ! is_null( $ext ) && Extensions_Util::activate_extension( $extension, $config ) ) {
			Util_Admin::redirect_with_custom_messages2(
				array(
					'notes' => array(
						sprintf(
							// Translators: 1 HTML strong tag containing extension name.
							__(
								'Extension %s has been successfully activated.',
								'w3-total-cache'
							),
							'<strong>' . $ext['name'] . '</strong>'
						),
					),
				)
			);
			return;
		}

		Util_Admin::redirect( array() );
	}
}
