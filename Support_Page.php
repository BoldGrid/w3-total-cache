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
	 * Default Wufoo form hash.
	 *
	 * @since 2.10.6
	 *
	 * @var string
	 */
	const FORM_HASH_DEFAULT = 'm5pom8z0qy59rm';

	/**
	 * Third-party form script URL. HTTPS only.
	 *
	 * @since 2.10.6
	 *
	 * @var string
	 */
	const FORM_SCRIPT = 'https://www.wufoo.com/scripts/embed/form.js';

	/**
	 * Keys allowed in the Support page localization object.
	 *
	 * Contact fields are intentionally absent. The third-party form
	 * collects those on submit.
	 *
	 * @since 2.10.6
	 *
	 * @return string[]
	 */
	public static function allowed_client_keys() {
		return array(
			'home_url',
			'form_hash',
			'field_name',
			'field_value',
			'postprocess',
			'form_script',
			'page',
		);
	}

	/**
	 * Localization payload for the Support page script.
	 *
	 * Returns null on the thank-you (`done`) view so the third-party
	 * form is not offered after submission.
	 *
	 * @since 2.10.6
	 *
	 * @return array<string, string>|null
	 */
	public static function client_data() {
		if ( ! empty( Util_Request::get_string( 'done' ) ) ) {
			return null;
		}

		$form_hash   = self::FORM_HASH_DEFAULT;
		$field_name  = '';
		$field_value = '';

		$service_item_val = Util_Request::get_integer( 'service_item' );
		if ( ! empty( $service_item_val ) ) {
			$pos = $service_item_val;
			$v   = get_site_option( 'w3tc_generic_widgetservices' );
			try {
				$v = json_decode( $v, true );
				if ( isset( $v['items'] ) && isset( $v['items'][ $pos ] ) && is_array( $v['items'][ $pos ] ) ) {
					$item = $v['items'][ $pos ];
					$hash = self::sanitize_form_hash( isset( $item['form_hash'] ) ? $item['form_hash'] : '' );
					if ( '' !== $hash ) {
						$form_hash   = $hash;
						$field_name  = self::sanitize_field_name( isset( $item['parameter_name'] ) ? $item['parameter_name'] : '' );
						$field_value = self::sanitize_field_value( isset( $item['parameter_value'] ) ? $item['parameter_value'] : '' );
						if ( '' === $field_name ) {
							$field_value = '';
						}
					}
				}
			} catch ( \Exception $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
			}
		}

		$w3tc_url = get_home_url();
		if ( 'http://' === substr( $w3tc_url, 0, 7 ) ) {
			$w3tc_url = substr( $w3tc_url, 7 );
		} elseif ( 'https://' === substr( $w3tc_url, 0, 8 ) ) {
			$w3tc_url = substr( $w3tc_url, 8 );
		}

		return array(
			'home_url'     => $w3tc_url,
			'form_hash'    => $form_hash,
			'field_name'   => $field_name,
			'field_value'  => $field_value,
			'postprocess'  => rawurlencode(
				rawurlencode(
					Util_Ui::admin_url(
						Util_Nonce::admin_nonce_url( 'admin.php', 'w3tc_support_send_details' ) . '&page=w3tc_support&done=1'
					)
				)
			),
			'form_script'  => self::FORM_SCRIPT,
			'page'         => 'w3tc_support',
		);
	}

	/**
	 * Prints the W3TC support scripts in the admin area.
	 *
	 * Enqueued only on the Support page hook. The thank-you view does
	 * not receive the payload or the loader.
	 *
	 * @return void
	 */
	public static function admin_print_scripts_w3tc_support() {
		$data = self::client_data();
		if ( null === $data ) {
			return;
		}

		wp_register_script(
			'w3tc-support',
			plugins_url( 'pub/js/support.js', W3TC_FILE ),
			array(),
			W3TC_VERSION,
			true
		);

		wp_localize_script( 'w3tc-support', 'w3tc_support_data', $data );
		wp_enqueue_script( 'w3tc-support' );
	}

	/**
	 * Restrict widget form hashes to the Wufoo token alphabet.
	 *
	 * @since 2.10.6
	 *
	 * @param mixed $hash Raw hash.
	 *
	 * @return string
	 */
	public static function sanitize_form_hash( $hash ) {
		if ( ! is_string( $hash ) ) {
			return '';
		}

		$hash = trim( $hash );
		if ( '' === $hash || ! preg_match( '/^[A-Za-z0-9]+$/', $hash ) ) {
			return '';
		}

		return $hash;
	}

	/**
	 * Restrict extra Wufoo field names to `field` plus digits.
	 *
	 * @since 2.10.6
	 *
	 * @param mixed $name Raw field name.
	 *
	 * @return string
	 */
	public static function sanitize_field_name( $name ) {
		if ( ! is_string( $name ) ) {
			return '';
		}

		$name = trim( $name );
		if ( '' === $name || ! preg_match( '/^field[0-9]+$/', $name ) ) {
			return '';
		}

		return $name;
	}

	/**
	 * Sanitize an extra Wufoo field value.
	 *
	 * @since 2.10.6
	 *
	 * @param mixed $value Raw value.
	 *
	 * @return string
	 */
	public static function sanitize_field_value( $value ) {
		if ( ! is_string( $value ) && ! is_numeric( $value ) ) {
			return '';
		}

		$value = sanitize_text_field( (string) $value );
		if ( strlen( $value ) > 200 ) {
			$value = substr( $value, 0, 200 );
		}

		return $value;
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
