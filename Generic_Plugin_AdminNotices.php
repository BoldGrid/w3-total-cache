<?php
/**
 * File: Generic_Plugin_AdminNotices.php
 *
 * @package W3TC
 *
 * @since 2.7.5
 */

namespace W3TC;

/**
 * Class Generic_Plugin_AdminNotices
 *
 * @since 2.7.5
 */
class Generic_Plugin_AdminNotices {
	/**
	 * Runs plugin
	 *
	 * @since 2.7.5
	 *
	 * @see Util_Admin::is_w3tc_admin_page()
	 */
	public function run() {
		if ( Util_Admin::is_w3tc_admin_page() ) {
			add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_scripts' ) );
			add_action( 'w3tc_ajax_get_notices', array( $this, 'w3tc_ajax_get_notices' ) );
			add_action( 'w3tc_ajax_dismiss_notice', array( $this, 'w3tc_ajax_dismiss_notice' ) );
		}
	}

	/**
	 * Enqueue admin scripts.
	 *
	 * @since 2.7.5
	 */
	public function admin_enqueue_scripts() {
		wp_enqueue_script( 'w3tc-admin-notices', plugins_url( 'Generic_Plugin_AdminNotices.js', W3TC_FILE ), array(), W3TC_VERSION, true );
	}

	/**
	 * Get notices ajax handler.
	 *
	 * @since 2.7.5
	 *
	 * @see self::get_active_notices()
	 *
	 * @return void
	 */
	public function w3tc_ajax_get_notices() {
		wp_send_json_success( array( 'noticeData' => $this->get_active_notices() ) );
	}

	/**
	 * Dismiss admin notice ajax handler.
	 *
	 * @since 2.7.5
	 *
	 * @return void
	 */
	public function w3tc_ajax_dismiss_notice() {
		$notice_id         = Util_Request::get_integer( 'notice_id' );
		$dismissed_notices = $this->get_dismissed_notices();

		if ( $notice_id ) {
			$dismissed_notices[] = $notice_id;
			update_option( 'w3tc_dismissed_notices', array_unique( $dismissed_notices ) );

			// Update cached notices.
			$cached_notices = $this->get_cached_notices();
			if ( $cached_notices ) {
				foreach ( $cached_notices as $key => $cached_notice ) {
					if ( $cached_notice['id'] === $notice_id ) {
						unset( $cached_notices[ $key ] );
					}
				}

				update_option(
					'w3tc_cached_notices',
					wp_json_encode(
						array(
							'time'    => time(),
							'notices' => array_values( $cached_notices ),
						)
					)
				);
			}

			wp_send_json_success();
		}

		wp_send_json_error( 'Invalid notice ID' );
	}

	/**
	 * Get dismissed notices.
	 *
	 * @since 2.7.5
	 *
	 * @return array|null
	 */
	private function get_dismissed_notices() {
		return get_option( 'w3tc_dismissed_notices', array() );
	}

	/**
	 * Get active notices.
	 *
	 * @since 2.7.5
	 *
	 * @see Dispatcher::config()
	 * @see Util_Environment::is_w3tc_pro()
	 *
	 * @return array|null
	 */
	private function get_active_notices() {
		$cached_notices = $this->get_cached_notices();
		if ( null !== $cached_notices ) {
			return $cached_notices;
		}

		$api_response = wp_remote_get( esc_url( W3TC_NOTICE_FEED ) );

		if ( is_wp_error( $api_response ) || wp_remote_retrieve_response_code( $api_response ) !== 200 ) {
			return null;
		}

		$body    = wp_remote_retrieve_body( $api_response );
		$notices = json_decode( $body, true );

		if ( json_last_error() !== JSON_ERROR_NONE ) {
			return null;
		}

		$active_notices    = array();
		$dismissed_notices = $this->get_dismissed_notices();
		$current_time      = new \DateTime();
		$is_pro            = Util_Environment::is_w3tc_pro( Dispatcher::config() );

		foreach ( $notices as $notice ) {
			$start_time = new \DateTime( $notice['start_at'] );
			$end_time   = isset( $notice['end_at'] ) ? new \DateTime( $notice['end_at'] ) : null;

			if (
				1 === $notice['is_active']
					&& isset( $notice['content'] )
					&& $current_time >= $start_time
					&& ( null === $end_time || $current_time <= $end_time )
					&& ! in_array( $notice['id'], $dismissed_notices, true )
			) {
				switch ( $notice['audience'] ) {
					case 'licensed':
						if ( ! $is_pro ) {
							continue 2;
						}
						break;
					case 'unlicensed':
						if ( $is_pro ) {
							continue 2;
						}
						break;
					default:
						break;
				}

				$notice['content'] = wp_kses(
					$notice['content'],
					$this->get_allowed_wp_kses()
				);

				if ( preg_match( '/<div\s+class=".*?notice.*?".*?>/', $notice['content'] ) && ! preg_match( '/data-id="\d+"/', $notice['content'] ) ) {
					$notice['content'] = preg_replace( '/(<div\s+class="notice.*?)(>)/', '$1 data-id="' . $notice['id'] . '"$2', $notice['content'] );
				}

				if ( preg_match( '/<div\s+class=".*?notice.*?is-dismissible.*?".*?>/', $notice['content'] ) && ! preg_match( '/<button\s+type="button"\s+class="notice-dismiss">/', $notice['content'] ) ) {
					$dismiss_button    = '<button type="button" class="notice-dismiss"><span class="screen-reader-text">Dismiss this notice.</span></button>';
					$notice['content'] = preg_replace( '/(<\/div>)/', $dismiss_button . '$1', $notice['content'] );
				}

				$active_notices[] = $notice;
			}
		}

		update_option(
			'w3tc_cached_notices',
			wp_json_encode(
				array(
					'time'    => time(),
					'notices' => $active_notices,
				)
			)
		);

		return $active_notices;
	}

	/**
	 * Get cached notices.
	 *
	 * @since 2.7.5
	 *
	 * @return array|null
	 */
	private function get_cached_notices() {
		$cached_notices = get_option( 'w3tc_cached_notices', '' );
		$cached_notices = json_decode( $cached_notices, true );

		if ( json_last_error() !== JSON_ERROR_NONE ) {
			return null;
		}

		if ( isset( $cached_notices['time'] ) && $cached_notices['time'] >= time() - DAY_IN_SECONDS ) {
			return $cached_notices['notices'];
		}

		return null;
	}

	/**
	 * Get allowed wp_kses.
	 *
	 * @since 2.7.5
	 *
	 * @return array
	 */
	private function get_allowed_wp_kses() {
		return array(
			'div'  => array(
				'id'    => array(),
				'class' => array(),
				'style' => array(),
			),
			'p'    => array(
				'id'    => array(),
				'class' => array(),
				'style' => array(),
			),
			'span' => array(
				'id'    => array(),
				'class' => array(),
				'style' => array(),
			),
			'a'    => array(
				'id'     => array(),
				'class'  => array(),
				'href'   => array(),
				'title'  => array(),
				'target' => array(),
				'style'  => array(),
			),
			'b'    => array(
				'id'    => array(),
				'class' => array(),
				'style' => array(),
			),
			'br'   => array(),
		);
	}
}
