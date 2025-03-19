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
	 * Whether the current page is a W3TC admin page.
	 *
	 * @since 2.8.6
	 *
	 * @var bool
	 */
	private $is_w3tc_page = false;

	/**
	 * Config state.
	 *
	 * @since 2.8.6
	 *
	 * @var State
	 */
	private $state;

	/**
	 * Whether the custom notices are global (display on all admin pages).
	 *
	 * @since 2.8.6
	 *
	 * @var bool
	 */
	private $has_global = false;

	/**
	 * Constructor.
	 *
	 * @since 2.8.6
	 *
	 * @return void
	 */
	public function __construct() {
		$this->is_w3tc_page = Util_Admin::is_w3tc_admin_page();
		$this->state        = Dispatcher::config_state();

		// Check for global custom notices.
		switch ( true ) {
			case $this->state->get_string( 'tasks.notices.disabled_objdisk' ):
				$this->has_global = true;
				break;
			default:
				break;
		}
	}

	/**
	 * Run if on a W3TC page or there are global notices and on an admin page.
	 *
	 * @since 2.7.5
	 *
	 * @see Util_Admin::is_w3tc_admin_page()
	 *
	 * @return void
	 */
	public function run() {
		if ( $this->is_w3tc_page || ( $this->has_global && \is_admin() ) ) {
			\add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_scripts' ) );
			\add_action( 'w3tc_ajax_get_notices', array( $this, 'w3tc_ajax_get_notices' ) );
			\add_action( 'w3tc_ajax_dismiss_notice', array( $this, 'w3tc_ajax_dismiss_notice' ) );
		}
	}

	/**
	 * Enqueue admin scripts and styles.
	 *
	 * @since 2.7.5
	 *
	 * @return void
	 */
	public function admin_enqueue_scripts() {
		if ( \user_can( \get_current_user_id(), 'manage_options' ) ) {
			\wp_register_script( 'w3tc-admin-notices', \plugins_url( 'Generic_Plugin_AdminNotices.js', W3TC_FILE ), array(), W3TC_VERSION, true );

			\wp_localize_script(
				'w3tc-admin-notices',
				'W3tcNoticeData',
				array(
					'isW3tcPage' => $this->is_w3tc_page,
					'w3tc_nonce' => \wp_create_nonce( 'w3tc' ),
				)
			);

			\wp_enqueue_script( 'w3tc-admin-notices' );

			\wp_enqueue_style( 'w3tc-admin-notices', \plugins_url( 'Generic_Plugin_AdminNotices.css', W3TC_FILE ), array(), W3TC_VERSION, 'screen' );
		}
	}

	/**
	 * Get notices ajax handler (administrators only).
	 *
	 * @since 2.7.5
	 *
	 * @see self::get_active_notices()
	 *
	 * @return void
	 */
	public function w3tc_ajax_get_notices() {
		if ( \user_can( \get_current_user_id(), 'manage_options' ) ) {
			\wp_send_json_success( array( 'noticeData' => $this->get_active_notices() ) );
		}
	}

	/**
	 * Dismiss admin notice ajax handler (administrators only).
	 *
	 * @since 2.7.5
	 *
	 * @return void
	 */
	public function w3tc_ajax_dismiss_notice() {
		if ( ! \user_can( \get_current_user_id(), 'manage_options' ) ) {
			return;
		}

		$notice_id         = Util_Request::get_integer( 'notice_id' );
		$dismissed_notices = $this->get_dismissed_notices();

		if ( $notice_id && ! \in_array( $notice_id, $dismissed_notices, true ) ) {
			$dismissed_notices[] = $notice_id;
			\update_option( 'w3tc_dismissed_notices', \array_unique( $dismissed_notices ), false );

			// Update cached notices.
			$cached_notices = $this->get_cached_notices();
			if ( $cached_notices ) {
				foreach ( $cached_notices as $key => $cached_notice ) {
					if ( $cached_notice['id'] === $notice_id ) {
						unset( $cached_notices[ $key ] );
					}
				}

				\update_option(
					'w3tc_cached_notices',
					\wp_json_encode(
						array(
							'time'    => \time(),
							'notices' => \array_values( $cached_notices ),
						)
					),
					false
				);
			}

			// Handle custom notice task state.
			switch ( $notice_id ) {
				// Custom notice ID 100000 ()"objectache-disk-disabled").
				case 100000:
					$this->state->set( 'tasks.notices.disabled_objdisk', false );
					$this->state->save();
					break;
				default:
					break;
			}

			\wp_send_json_success();
		}

		\wp_send_json_error( 'Invalid notice ID' );
	}

	/**
	 * Get dismissed notices.
	 *
	 * @since 2.7.5
	 *
	 * @return array
	 */
	private function get_dismissed_notices(): array {
		return (array) \get_option( 'w3tc_dismissed_notices', array() );
	}

	/**
	 * Get active notices.
	 *
	 * Retrieve notices that have not expired or been dismissed.
	 *
	 * @example array|null $active_notices {
	 *     Array of active notices.
	 *
	 *     @type int            $id        Notice ID (1-2,147,483,647).  If adding custom notices, use IDs >= 100,000.
	 *     @type string         $name      Name.
	 *     @type int            $is_active Is active (0 or 1).
	 *     @type string         $audience  Audience ("all", "licensed", "unlicensed").
	 *     @type int            $priority  Priority (1-255; lower number has higher priority).
	 *     @type string         $start_at  Start time (in format YYYY-MM-DD HH:MM:SS).
	 *     @type string|null    $end_at    Optional. End time (in format YYYY-MM-DD HH:MM:SS).  Default null.
	 *     @type string         $content   Notice content.
	 *     @type bool           $is_global Whether the notice is global.
	 * }
	 *
	 * @since 2.7.5
	 *
	 * @see self::get_cached_notices()
	 * @see self::merge_notices()
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

		$api_response = \wp_remote_get( esc_url( W3TC_NOTICE_FEED ) );

		if ( \is_wp_error( $api_response ) || \wp_remote_retrieve_response_code( $api_response ) !== 200 ) {
			return null;
		}

		$body    = \wp_remote_retrieve_body( $api_response );
		$notices = \json_decode( $body, true );

		if ( \json_last_error() !== \JSON_ERROR_NONE ) {
			return null;
		}

		// Add custom notices.
		$notices = $this->merge_notices( $notices, $this->get_custom_notices() );

		// Process notices.
		$active_notices    = array();
		$dismissed_notices = $this->get_dismissed_notices();
		$current_time      = new \DateTime();
		$is_pro            = Util_Environment::is_w3tc_pro( Dispatcher::config() );

		foreach ( $notices as $notice ) {
			// Process notice.
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

				$notice['content'] = \wp_kses(
					$notice['content'],
					$this->get_allowed_wp_kses()
				);

				// Add data-id attribute if needed.
				if ( \preg_match( '/<div\s+class=".*?notice.*?".*?>/', $notice['content'] ) && ! \preg_match( '/data-id="\d+"/', $notice['content'] ) ) {
					$notice['content'] = \preg_replace( '/(<div\s+class="notice.*?)(>)/', '$1 data-id="' . $notice['id'] . '"$2', $notice['content'] );
				}

				// Add dismiss button if needed.
				if ( \preg_match( '/<div\s+class=".*?notice.*?is-dismissible.*?".*?>/', $notice['content'] ) && ! \preg_match( '/<button\s+type="button"\s+class="notice-dismiss">/', $notice['content'] ) ) {
					$dismiss_button    = '<button type="button" class="notice-dismiss"><span class="screen-reader-text">Dismiss this notice.</span></button>';
					$notice['content'] = \preg_replace( '/(<\/div>)/', $dismiss_button . '$1', $notice['content'] );
				}

				// Ensure "is_global" is set, default to false.
				$notice['is_global'] = $notice['is_global'] ?? false;

				$active_notices[] = $notice;
			}
		}

		\update_option(
			'w3tc_cached_notices',
			\wp_json_encode(
				array(
					'time'    => \time(),
					'notices' => $active_notices,
				)
			),
			false
		);

		return $active_notices;
	}

	/**
	 * Get cached notices.
	 *
	 * Notices are cached for 1 day.
	 *
	 * @since 2.7.5
	 *
	 * @return array|null
	 */
	private function get_cached_notices() {
		$cached_notices = \get_option( 'w3tc_cached_notices', '' );
		$cached_notices = \json_decode( $cached_notices, true );

		if ( \json_last_error() !== JSON_ERROR_NONE ) {
			return null;
		}

		if ( isset( $cached_notices['time'] ) && $cached_notices['time'] >= \time() - DAY_IN_SECONDS ) {
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

	/**
	 * Get custom notices.
	 *
	 * @example array Notice {
	 *     @type int            $id        Notice ID (1-2,147,483,647).  If adding custom notices, use IDs >= 100,000.
	 *     @type string         $name      Name.
	 *     @type int            $is_active Is active (0 or 1).
	 *     @type string         $audience  Audience ("all", "licensed", "unlicensed").
	 *     @type int            $priority  Priority (1-255; lower number has higher priority).
	 *     @type string         $start_at  Start time (in format YYYY-MM-DD HH:MM:SS).
	 *     @type string|null    $end_at    Optional. End time (in format YYYY-MM-DD HH:MM:SS).  Default null.
	 *     @type string         $content   Notice content.
	 *     @type bool           $is_global Whether the notice is global.
	 * }
	 *
	 * @since  2.8.6
	 * @access private
	 *
	 * @return array
	 */
	private function get_custom_notices(): array {
		$notices = array();

		if ( $this->state->get_boolean( 'tasks.notices.disabled_objdisk' ) ) {
			$notices[] = array(
				'id'        => 100000, // Our API should not use IDs higher than this.  Custom notices from the filter below should use IDs >= 200,000.
				'name'      => 'objectache-disk-disabled',
				'is_active' => 1,
				'audience'  => 'all',
				'priority'  => 1,
				'start_at'  => '2025-02-17 00:00:00',
				'content'   => '<div class="notice notice-info is-dismissible w3tc-notices-custom"><p>' .
					\sprintf(
						// translators: 1: A link to the settings page.
						\esc_html__(
							'W3 Total Cache has automatically disabled object caching because it was configured to write cache files to disk. This change was made to prevent potential performance issues and excessive file creation on your server. If you wish to re-enable object caching, you can do so in the %1$s. %2$s about this change and alternative caching solutions.',
							'w3-total-cache'
						),
						'<a href="' . \esc_url( \network_admin_url( 'admin.php?page=w3tc_general#object_cache' ), null, 'link' ) . '">' .
							__( 'settings', 'w3-total-cache' ) . '</a>',
						'<a target="_blank" href="' . \esc_url( 'https://www.boldgrid.com/object-caching-changes-in-2-8-6/', null, 'link' ) .
							'" title="' . \esc_attr__( 'Disabling Object Cache using Disk', 'w3-total-cache' ) . '">' .
								\esc_html__( 'Learn more', 'w3-total-cache' ) . ' <span class="dashicons dashicons-external"></span></a>',
					) . '</p></div>',
				'is_global' => true,
			);
		}

		return $notices;
	}

	/**
	 * Merge notices.
	 *
	 * This method is used to merge notices from different sources, renumbering the numerical indices.
	 *
	 * @since 2.8.6
	 *
	 * @param array $notices1 Notices to merge.
	 * @param array $notices2 Notices to merge.
	 * @return array
	 */
	private function merge_notices( array $notices1, array $notices2 ): array {
		$notices = array();

		foreach ( array( 'notices1', 'notices2' ) as $arg ) {
			foreach ( $$arg as $notice ) {
				$notices[] = $notice;
			}
		}

		return $notices;
	}
}
