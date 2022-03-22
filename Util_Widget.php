<?php
namespace W3TC;

class Util_Widget {
	static $w3tc_dashboard_widgets = array();

	/**
	 * Registers dashboard widgets.
	 *
	 * Handles POST data, sets up filters.
	 *
	 * @since 0.9.2.6
	 */
	static public function setup() {
		global $w3tc_registered_widgets, $w3tc_registered_widget_controls, $w3tc_dashboard_control_callbacks;
		$w3tc_dashboard_control_callbacks = array();
		$screen = get_current_screen();

		$update = false;
		$widget_options = get_option( 'w3tc_dashboard_widget_options' );
		if ( !$widget_options || !is_array( $widget_options ) )
			$widget_options = array();

		// Hook to register new widgets
		// Filter widget order
		if ( is_network_admin() ) {
			do_action( 'w3tc_network_dashboard_setup' );
			$dashboard_widgets = apply_filters( 'w3tc_network_dashboard_widgets', array() );
		} else {
			do_action( 'w3tc_widget_setup' );
			$dashboard_widgets = apply_filters( 'w3tc_dashboard_widgets', array() );
		}

		foreach ( $dashboard_widgets as $widget_id ) {
			$name = empty( $w3tc_registered_widgets[$widget_id]['all_link'] ) ? $w3tc_registered_widgets[$widget_id]['name'] : $w3tc_registered_widgets[$widget_id]['name'] . " <a href='{$w3tc_registered_widgets[$widget_id]['all_link']}' class='edit-box open-box'>" . __( 'View all' ) . '</a>';
			Util_Widget::add( $widget_id, $name, $w3tc_registered_widgets[$widget_id]['callback'], $w3tc_registered_widget_controls[$widget_id]['callback'] );
		}

		if ( 'POST' == isset( $_SERVER['REQUEST_METHOD'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) : '' && isset( Util_Request::get_string( 'widget_id' ) ) ) {
			check_admin_referer( 'edit-dashboard-widget_' . Util_Request::get_string( 'widget_id' ), 'dashboard-widget-nonce' );
			ob_start(); // hack - but the same hack wp-admin/widgets.php uses
			Util_Widget::trigger_widget_control( Util_Request::get_string( 'widget_id' ) );
			ob_end_clean();
		}

		if ( $update )
			update_option( 'w3tc_dashboard_widget_options', $widget_options );

		do_action( 'do_meta_boxes', $screen->id, 'normal', '' );
		do_action( 'do_meta_boxes', $screen->id, 'side', '' );
	}

	static public function add2( $widget_id, $priority, $widget_name, $callback,
			$control_callback = null, $location = 'normal', $header_text = null,
			$header_class = '') {
		$o = new _Util_Widget_Postponed( array(
				'widget_id' => $widget_id,
				'widget_name' => $widget_name,
				'callback' => $callback,
				'control_callback' => $control_callback,
				'location' => $location,
				'header_text' => $header_text,
				'header_class' => $header_class
			) );

		add_action( 'w3tc_widget_setup',
			array( $o, 'wp_dashboard_setup' ), $priority );
		add_action( 'w3tc_network_dashboard_setup',
			array( $o, 'wp_dashboard_setup' ), $priority );
		self::$w3tc_dashboard_widgets[$widget_id] = '*';
	}

	/**
	 * Registers widget
	 */
	static public function add( $widget_id, $widget_name, $callback,
			$control_callback = null, $location = 'normal', $header_text = null,
			$header_class = '') {
		$screen = get_current_screen();
		global $w3tc_dashboard_control_callbacks;

		if ( substr( $widget_name, 0, 1 ) != '<' )
			$widget_name = '<div class="w3tc-widget-text">' . $widget_name .
				'</div>';

		// it's url
		if ( $control_callback && current_user_can( 'edit_dashboard' ) &&
			is_string( $control_callback ) ) {
			if ( !$header_text ) {
				$header_text = __( 'Configure' );
			}

			$widget_name .= ' <span class="w3tc-widget-configure postbox-title-action">' .
				'<a href="' . esc_url( $control_callback ) .
				'" class="edit-box open-box ' . esc_attr($header_class) . '">' . $header_text .
				'</a></span>';
		}

		// it's ajax callback
		if ( $control_callback && current_user_can( 'edit_dashboard' ) && is_callable( $control_callback ) ) {
			$w3tc_dashboard_control_callbacks[ $widget_id ] = $control_callback;
			$edit_val                                       = Util_Request::get_string( 'edit' );
			if ( ! empty( $edit_val ) && $widget_id === $edit_val ) {
				list( $url ) = explode( '#', add_query_arg( 'edit', false ), 2 );
				$widget_name .= ' <span class="postbox-title-action">' .
					'<a href="' . esc_url( $url ) . '">' . __( 'Cancel' ) .
					'</a></span>';

				$callback = array(
					'\W3TC\Util_Widget',
					'_dashboard_control_callback'
				);
			} else {
				list( $url ) = explode( '#', add_query_arg( 'edit', $widget_id ), 2 );
				$widget_name .= ' <span class="postbox-title-action">' .
					'<a href="' . esc_url( "$url#$widget_id" ) .
					'" class="edit-box open-box">' . __( 'Configure' ) .
					'</a></span>';
			}
		}

		$side_widgets = array();

		$priority = 'core';

		add_meta_box( $widget_id, $widget_name, $callback, $screen, $location, $priority );
	}

	/* Dashboard Widgets Controls */
	static public function _dashboard_control_callback( $dashboard, $meta_box ) {
		echo '<form action="" method="post" class="dashboard-widget-control-form">';
		Util_Widget::trigger_widget_control( $meta_box['id'] );
		wp_nonce_field( 'edit-dashboard-widget_' . $meta_box['id'], 'dashboard-widget-nonce' );
		echo '<input type="hidden" name="widget_id" value="' . esc_attr( $meta_box['id'] ) . '" />';
		submit_button( __( 'Submit' ) );
		echo '</form>';
	}

	static public function list_widgets() {
		return implode(',', array_keys(self::$w3tc_dashboard_widgets));
	}

	/**
	 * Calls widget control callback.
	 *
	 * @since 0.9.2.6
	 *
	 * @param int|bool $widget_control_id Registered Widget ID.
	 */
	static public function trigger_widget_control( $widget_control_id = false ) {
		global $w3tc_dashboard_control_callbacks;

		if ( is_scalar( $widget_control_id ) && $widget_control_id && isset( $w3tc_dashboard_control_callbacks[$widget_control_id] ) && is_callable( $w3tc_dashboard_control_callbacks[$widget_control_id] ) ) {
			call_user_func( $w3tc_dashboard_control_callbacks[$widget_control_id], '', array( 'id' => $widget_control_id, 'callback' => $w3tc_dashboard_control_callbacks[$widget_control_id] ) );
		}
	}
}



class _Util_Widget_Postponed {
	private $data = array();

	public function __construct( $data ) {
		$this->data = $data;
	}


	public function wp_dashboard_setup() {
		Util_Widget::add(
			$this->data['widget_id'],
			$this->data['widget_name'],
			$this->data['callback'],
			$this->data['control_callback'],
			$this->data['location'],
			$this->data['header_text'],
			$this->data['header_class']
		);
	}
}
