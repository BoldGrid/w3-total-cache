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
		$w3tc_config = Dispatcher::config();

		$w3tc_extension = Util_Request::get_string( 'w3tc_extensions_activate' );
		$w3tc_ext       = Extensions_Util::get_extension( $w3tc_config, $w3tc_extension );

		/**
		 * Per-extension capability gate, floored at manage_options.
		 * Prevents non-admins from activating extensions even if a
		 * downstream filter lowers the per-extension cap.
		 *
		 * @since 2.10.0
		 */
		$capability = apply_filters(
			'w3tc_capability_extensions_activate_' . $w3tc_extension,
			'manage_options'
		);
		if ( ! \current_user_can( 'manage_options' ) || empty( $capability ) || ! \current_user_can( $capability ) ) {
			wp_die(
				\esc_html__( 'You do not have sufficient permissions to perform this action.', 'w3-total-cache' ),
				'',
				array( 'response' => 403 )
			);
		}

		if ( ! is_null( $w3tc_ext ) && Extensions_Util::activate_extension( $w3tc_extension, $w3tc_config ) ) {
			Util_Admin::redirect_with_custom_messages2(
				array(
					'notes' => array(
						sprintf(
							// Translators: 1 HTML strong tag containing extension name.
							__(
								'Extension %s has been successfully activated.',
								'w3-total-cache'
							),
							'<strong>' . $w3tc_ext['name'] . '</strong>'
						),
					),
				)
			);
			return;
		}

		Util_Admin::redirect( array() );
	}
}
