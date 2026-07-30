<?php
/**
 * File: Support_Page.php
 *
 * @package W3TC
 */

namespace W3TC;

/**
 * Class Support_Page
 */
class Support_Page {
	/**
	 * Prints the W3TC support scripts in the admin area.
	 *
	 * This method localizes and sets up the necessary variables to be used
	 * by the W3TC support JavaScript in the admin area.
	 *
	 * @return void
	 */
	public static function admin_print_scripts_w3tc_support() {
		$w3tc_url = get_home_url();
		if ( 'http://' === substr( $w3tc_url, 0, 7 ) ) {
			$w3tc_url = substr( $w3tc_url, 7 );
		} elseif ( 'https://' === substr( $w3tc_url, 0, 8 ) ) {
			$w3tc_url = substr( $w3tc_url, 8 );
		}

		// values from widget.
		$w3tc_support_form_hash   = 'm5pom8z0qy59rm';
		$w3tc_support_field_name  = '';
		$w3tc_support_field_value = '';

		$service_item_val = Util_Request::get_integer( 'service_item' );
		if ( ! empty( $service_item_val ) ) {
			$pos = $service_item_val;

			$v = get_site_option( 'w3tc_generic_widgetservices' );
			try {
				$v = json_decode( $v, true );
				if ( isset( $v['items'] ) && isset( $v['items'][ $pos ] ) ) {
					$w3tc_i                   = $v['items'][ $pos ];
					$w3tc_support_form_hash   = $w3tc_i['form_hash'];
					$w3tc_support_field_name  = $w3tc_i['parameter_name'];
					$w3tc_support_field_value = $w3tc_i['parameter_value'];
				}
			} catch ( \Exception $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
			}
		}

		$u = wp_get_current_user();

		// w3tc-options script is already queued so attach to it
		// just to make vars printed (while it's not related by semantics).
		wp_localize_script(
			'w3tc-options',
			'w3tc_support_data',
			array(
				'home_url'    => $w3tc_url,
				'email'       => get_bloginfo( 'admin_email' ),
				'first_name'  => $u->first_name,
				'last_name'   => $u->last_name,
				'form_hash'   => $w3tc_support_form_hash,
				'field_name'  => $w3tc_support_field_name,
				'field_value' => $w3tc_support_field_value,
				'postprocess' => rawurlencode(
					rawurlencode(
						Util_Ui::admin_url(
							// The _wpnonce minted here is forwarded by options() into the w3tc_support_send_details action URL.
							Util_Nonce::admin_nonce_url( 'admin.php', 'w3tc_support_send_details' ) . '&page=w3tc_support&done=1'
						)
					)
				),
			)
		);
	}

	/**
	 * Displays the options page for W3TC support.
	 *
	 * This method renders the appropriate content based on the request parameters.
	 * If a "done" parameter is detected, it processes the request and displays
	 * the post-process content. Otherwise, it loads the main support page content.
	 *
	 * @return void
	 */
	public function options() {
		if ( ! empty( Util_Request::get_string( 'done' ) ) ) {
			$postprocess_url =
				'admin.php?page=w3tc_support&w3tc_support_send_details' .
				'&_wpnonce=' . rawurlencode( Util_Request::get_string( '_wpnonce' ) );
			foreach ( $_GET as $w3tc_p => $v ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				if ( 'page' !== $w3tc_p && '_wpnonce' !== $w3tc_p && 'done' !== $w3tc_p ) {
					$postprocess_url .= '&' . rawurlencode( $w3tc_p ) . '=' . rawurlencode( Util_Request::get_string( $w3tc_p ) );
				}
			}

			// terms accepted as a part of form.
			Licensing_Core::terms_accept();

			include W3TC_DIR . '/Support_Page_View_DoneContent.php';
		} else {
			include W3TC_DIR . '/Support_Page_View_PageContent.php';
		}
	}
}
