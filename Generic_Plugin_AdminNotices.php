<?php
/**
 * File: Generic_Plugin_AdminNotices.php
 *
 * @package W3TC
 *
 * @since X.X.X
 */

namespace W3TC;

/**
 * Class Generic_Plugin_AdminNotices
 *
 * @since X.X.X
 */
class Generic_Plugin_AdminNotices {
	/**
	 * Config.
	 *
	 * @since X.X.X
	 *
	 * @var Config
	 */
	private $config;

	/**
	 * Is Pro.
	 *
	 * @since X.X.X
	 *
	 * @var Bool
	 */
	private $is_pro;

	/**
	 * Cached Notices.
	 *
	 * @since X.X.X
	 *
	 * @var Array
	 */
	private $cached_notices;
	
	/**
	 * Active Notices.
	 *
	 * @since X.X.X
	 *
	 * @var Array
	 */
	private $active_notices;

	/**
	 * Dismissed Notices.
	 *
	 * @since X.X.X
	 *
	 * @var Array
	 */
	private $dismissed_notices;

	/**
	 * Constructor.
	 *
	 * @since X.X.X
	 */
	public function __construct() {
		$this->config            = Dispatcher::config();
		$this->is_pro            = Util_Environment::is_w3tc_pro( $this->config );
		$this->dismissed_notices = $this->get_dismissed_notices();
		$this->cached_notices    = $this->get_cached_notices();
		$this->active_notices    = $this->get_active_notices();
	}

	/**
	 * Runs plugin
	 *
	 * @since X.X.X
	 */
	public function run() {
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_scripts' ) );
		add_action( 'w3tc_ajax_get_notices', array( $this, 'w3tc_ajax_get_notices' ) );
		add_action( 'w3tc_ajax_dismiss_notice', array( $this, 'w3tc_ajax_dismiss_notice' ) );
	}

	/**
	 * Enqueue admin scripts.
	 *
	 * @since X.X.X
	 */
	public function admin_enqueue_scripts() {
		wp_enqueue_script( 'w3tc-admin-notices', plugins_url( 'Generic_Plugin_AdminNotices.js', W3TC_FILE ), array(), W3TC_VERSION, false );
	}

	/**
	 * Get notices ajax handler.
	 *
	 * @since X.X.X
	 *
	 * @return void
	 */
	public function w3tc_ajax_get_notices() {
		if ( $this->cached_notices !== null ) {
			wp_send_json_success( array( 'noticeData' => $this->cached_notices ) );
		}

		wp_send_json_success( array( 'noticeData' => $this->active_notices ) );
	}

	/**
	 * Dismiss admin notice ajax handler.
	 *
	 * @since X.X.X
	 *
	 * @return void
	 */
	public function w3tc_ajax_dismiss_notice() {
		$notice_id = Util_Request::get_string( 'notice_id' );

		if ( $notice_id ) {
			$this->dismissed_notices[] = $notice_id;
			update_option( 'w3tc_dismissed_notices', array_unique( $this->dismissed_notices ) );
			wp_send_json_success();
		}

		wp_send_json_error( 'Invalid notice ID' );
	}

	/**
	 * Get dismissed notices.
	 *
	 * @since X.X.X
	 *
	 * @return array|null
	 */
	private function get_dismissed_notices() {
		return get_option( 'w3tc_dismissed_notices', array() );
	}

	/**
	 * Get active notices.
	 *
	 * @since X.X.X
	 *
	 * @return array|null
	 */
	private function get_active_notices() {
		if ( $this->cached_notices ) {
			return $this->cached_notices;
		}

		$api_response = wp_remote_get( esc_url( W3TC_NOTICE_FEED ) );

		if ( is_wp_error( $api_response ) ) {
			return null;
		}

		$body    = wp_remote_retrieve_body( $api_response );
		$notices = json_decode( $body, true );

		if ( json_last_error() !== JSON_ERROR_NONE ) {
			return null;
		}

		$active_notices = array();
		$current_time   = new \DateTime();

		foreach ( $notices as $notice ) {
			$start_time = new \DateTime( $notice['start_at'] );
			$end_time   = isset( $notice['end_at'] ) ? new \DateTime( $notice['end_at'] ) : null;

			if (
				$notice['is_active'] === 1
					&& isset( $notice['content'] )
					&& $current_time >= $start_time
					&& ( $end_time === null || $current_time <= $end_time )
					&& ! in_array( 'notice-' . $notice['id'], $this->dismissed_notices, true )
			) {
				switch ( $notice['audience'] ) {
					case 'licensed':
						if ( ! $this->is_pro ) {
							continue 2;
						}
						break;
					case 'unlicensed':
						if ( $this->is_pro ) {
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

				$active_notices[] = $notice;
			}
		}

		update_option( 'w3tc_cached_notices', json_encode( array( 'time' => time(), 'notices' => $active_notices ) ) );

		return $active_notices;
	}

	/**
	 * Get cached notices.
	 *
	 * @since X.X.X
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
	 * @since X.X.X
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
