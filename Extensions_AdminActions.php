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
