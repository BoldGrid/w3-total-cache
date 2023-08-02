<?php
namespace W3TC;

use DOMDocument;

class Util_Ui {
	/**
	 * Returns button html
	 *
	 * @param string $text
	 * @param string $onclick
	 * @param string $class
	 * @return string
	 */
	public static function button( $text, $onclick = '', $class = 'button',
		$name = '' ) {
		$maybe_name = ( empty( $name ) ? '' : ' name="' . esc_attr( $name ) . '"' );
		return '<input type="button"' . $maybe_name . ' class="' .
			esc_attr( $class ) . '" value="' . esc_attr( $text ) .
			'" onclick="' . esc_attr( $onclick ) . '" />';
	}

	/**
	 * Returns button link html.
	 *
	 * @param string $text       Text.
	 * @param string $url        URL.
	 * @param bool   $new_window Open link in a new window.
	 * @param string $class      Class.
	 * @param string $name       Name.
	 * @return string
	 */
	public static function button_link( $text, $url, $new_window = false, $class = 'button', $name = '' ) {
		$url = str_replace( '&amp;', '&', $url );

		if ( $new_window ) {
			$onclick = sprintf( 'window.open(\'%s\');', addslashes( $url ) );
		} else {
			$onclick = '';

			if ( strpos( $class, 'w3tc-button-ignore-change' ) >= 0 ) {
				$onclick .= 'w3tc_beforeupload_unbind(); ';
			}

			$onclick .= sprintf( 'document.location.href=\'%s\';', addslashes( $url ) );
		}

		return self::button( $text, $onclick, $class, $name );
	}

	public static function url( $addon ) {
		if ( ! isset( $addon['page'] ) ) {
			$addon['page'] = Util_Request::get_string( 'page', 'w3tc_dashboard' );
		}

		$url = 'admin.php';
		$amp = '?';
		foreach ( $addon as $key => $value ) {
			$url .= $amp . rawurlencode( $key ) . '=' . rawurlencode( $value );
			$amp = '&';
		}

		$url = wp_nonce_url( $url, 'w3tc' );

		return $url;
	}

	/**
	 * Returns hide note button html
	 *
	 * @param string  $text
	 * @param string  $note
	 * @param string  $redirect
	 * @param boolean $admin         if to use config admin.
	 * @param string  $page
	 * @param string  $custom_method
	 * @return string
	 */
	public static function button_hide_note( $text, $note, $redirect = '',
		$admin = false, $page = '',
		$custom_method = 'w3tc_default_hide_note' ) {
		if ( '' === $page ) {
			$page = Util_Request::get_string( 'page', 'w3tc_dashboard' );
		}

		$url = sprintf( 'admin.php?page=%s&%s&note=%s', $page, $custom_method, $note );

		if ( $admin ) {
			$url .= '&admin=1';
		}

		if ( '' !== $redirect ) {
			$url .= '&redirect=' . rawurlencode( $redirect );
		}

		$url = wp_nonce_url( $url, 'w3tc' );

		return self::button_link( $text, $url, false, 'button', 'w3tc_hide_' . $custom_method );
	}

	public static function button_hide_note2( $parameters ) {
		return self::button_link(
			__( 'Hide this message', 'w3-total-cache' ),
			self::url( $parameters ),
			false,
			'button',
			'w3tc_hide_' . self::config_key_to_http_name( $parameters['key'] )
		);
	}

	public static function action_button( $action, $url, $class = '',
		$new_window = false ) {
		return self::button_link( $action, $url, $new_window, $class );
	}
	/**
	 * Returns popup button html
	 *
	 * @param string  $text
	 * @param string  $action
	 * @param string  $params
	 * @param integer $width
	 * @param integer $height
	 * @return string
	 */
	public static function button_popup( $text, $action, $params = '', $width = 800, $height = 600 ) {
		$url = wp_nonce_url( sprintf( 'admin.php?page=w3tc_dashboard&w3tc_%s%s', $action, ( '' !== $params ? '&' . $params : '' ) ), 'w3tc' );
		$url = str_replace( '&amp;', '&', $url );

		$onclick = sprintf( 'window.open(\'%s\', \'%s\', \'width=%d,height=%d,status=no,toolbar=no,menubar=no,scrollbars=yes\');', $url, $action, $width, $height );

		return self::button( $text, $onclick );
	}

	/**
	 * Returns label string for a config key.
	 *
	 * @param string $config_key
	 * @param string $area
	 */
	public static function config_label( $config_key ) {
		static $config_labels = null;
		if ( is_null( $config_labels ) ) {
			$config_labels = apply_filters( 'w3tc_config_labels', array() );
		}

		if ( isset( $config_labels[ $config_key ] ) ) {
			return $config_labels[ $config_key ];
		}

		return '';
	}

	/**
	 * Prints the label string for a config key.
	 *
	 * @param string $config_key
	 * @param string $area
	 */
	public static function e_config_label( $config_key ) {
		$config_label = self::config_label( $config_key );
		echo wp_kses(
			$config_label,
			self::get_allowed_html_for_wp_kses_from_content( $config_label )
		);
	}

	/**
	 * Returns postbox header
	 *
	 * WordPress 5.5 introduced .postbox-header, which broke the styles of our postboxes. This was
	 * resolved by adding additional css to /pub/css/options.css and pub/css/widget.css tagged with
	 * a "WP 5.5" comment.
	 *
	 * @todo Add .postbox-header to our postboxes and cleanup css.
	 * @link https://github.com/BoldGrid/w3-total-cache/issues/237
	 *
	 * @param string $title
	 * @param string $class
	 * @param string $id
	 * @return void
	 */
	public static function postbox_header( $title, $class = '', $id = '' ) {
		$id = ( ! empty( $id ) ) ? ' id="' . esc_attr( $id ) . '"' : '';
		echo '<div' . $id . ' class="postbox ' . esc_attr( $class ) . '">
			<h3 class="postbox-title"><span>' . wp_kses( $title, self::get_allowed_html_for_wp_kses_from_content( $title ) ) . '</span></h3>
			<div class="inside">';
	}

	/**
	 * Returns postbox header with tabs and links (used on the General settings page exclusively)
	 *
	 * WordPress 5.5 introduced .postbox-header, which broke the styles of our postboxes. This was
	 * resolved by adding additional css to /pub/css/options.css and pub/css/widget.css tagged with
	 * a "WP 5.5" comment.
	 *
	 * @todo Add .postbox-header to our postboxes and cleanup css.
	 * @link https://github.com/BoldGrid/w3-total-cache/issues/237
	 *
	 * @param string $title
	 * @param string $description
	 * @param string $class
	 * @param string $id
	 * @param string $adv_link
	 * @param array  $extra_links
	 * @return void
	 */
	public static function postbox_header_tabs( $title, $description = '', $class = '', $id = '', $adv_link = '', $extra_links = array() ) {
		$display_id         = ( ! empty( $id ) ) ? ' id="' . esc_attr( $id ) . '"' : '';
		$description        = ( ! empty( $description ) ) ? '<div class="postbox-description">' . wp_kses( $description, self::get_allowed_html_for_wp_kses_from_content( $description ) ) . '</div>' : '';
		$basic_settings_tab = ( ! empty( $adv_link ) ) ? '<a class="nav-tab nav-tab-active no-link">' . esc_html__( 'Basic Settings', 'w3-total-cache' ) . '</a>' : '';
		$adv_settings_tab   = ( ! empty( $adv_link ) ) ? '<a class="nav-tab link-tab" href="' . esc_url( $adv_link ) . '" gatitle="' . esc_attr( $id ) . '">' . esc_html__( 'Advanced Settings', 'w3-total-cache' ) . '<span class="dashicons dashicons-arrow-right-alt2"></span></a>' : '';
		
		$extra_link_tabs = '';
		foreach ( $extra_links as $extra_link_text => $extra_link ) {
			$extra_link_tabs .= '<a class="nav-tab link-tab" href="' . esc_url( $extra_link ) . '" gatitle="' . esc_attr( $extra_link_text ) . '">' . esc_html( $extra_link_text ) . '<span class="dashicons dashicons-arrow-right-alt2"></span></a>';
		}

		echo '<div' . $display_id . ' class="postbox-tabs ' . esc_attr( $class ) . '">
			<h3 class="postbox-title"><span>' . wp_kses( $title, self::get_allowed_html_for_wp_kses_from_content( $title ) ) . '</span></h3>
			' . $description . '
			<h2 class="nav-tab-wrapper">' . $basic_settings_tab . $adv_settings_tab . $extra_link_tabs . '</h2>
			<div class="inside">';
	}

	/**
	 * Returns postbox footer
	 *
	 * @return void
	 */
	public static function postbox_footer() {
		echo '</div></div>';
	}

	public static function button_config_save( $id = '', $extra = '' ) {
		$b1_id = 'w3tc_save_options_' . $id;
		$b2_id = 'w3tc_default_save_and_flush_' . $id;

		?>
		<p class="submit">
			<?php
			$nonce_field = self::nonce_field( 'w3tc' );
			echo wp_kses(
				$nonce_field,
				self::get_allowed_html_for_wp_kses_from_content( $nonce_field )
			);
			?>
			<input type="submit" id="<?php echo esc_attr( $b1_id ); ?>"
				name="w3tc_save_options"
				class="w3tc-button-save button-primary"
				value="<?php esc_attr_e( 'Save all settings', 'w3-total-cache' ); ?>" />
			<?php
			echo wp_kses(
				$extra,
				self::get_allowed_html_for_wp_kses_from_content( $extra )
			);
			?>
			<?php if ( ! is_network_admin() ) : ?>
			<input type="submit" id="<?php echo esc_attr( $b2_id ); ?>"
				name="w3tc_default_save_and_flush" style="float: right"
				class="w3tc-button-save button-primary"
				value="<?php esc_attr_e( 'Save Settings & Purge Caches', 'w3-total-cache' ); ?>" />
			<?php endif ?>
		</p>
		<?php
	}

	public static function button_config_save_dropdown( $id = '', $extra = '' ) {
		?>
		<div class="w3tc-button-control-container">
			<?php
			self::print_save_split_button( $id, $extra );
			self::print_flush_split_button();
			?>
		</div>
		<?php
	}

	/**
	 * Prints the split button for saving setting.
	 *
	 * @param string $id     ID value.
	 * @param string $extra Extra values.
	 * @return void
	 */
	public static function print_save_split_button( $id = '', $extra = '' ) {
		$b1_id = 'w3tc_save_options_' . $id;
		$b2_id = 'w3tc_default_save_and_flush_' . $id;

		$nonce_field = self::nonce_field( 'w3tc' );
		echo wp_kses(
			$nonce_field,
			self::get_allowed_html_for_wp_kses_from_content( $nonce_field )
		);

		echo wp_kses(
			$extra,
			self::get_allowed_html_for_wp_kses_from_content( $extra )
		);

		?>
		<div class="btn-group w3tc-button-save-dropdown">
			<?php
			if ( ! is_network_admin() ) {
				?>
				<input type="submit" class="w3tc-button-save btn btn-primary btn-sm" name="w3tc_save_options" value="<?php esc_html_e( 'Save Settings', 'w3-total-cache' ); ?>"/>
				<button type="button" class="btn btn-primary btn-sm dropdown-toggle dropdown-toggle-split" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
					<span class="sr-only">Toggle Dropdown</span>
				</button>
				<div class="dropdown-menu dropdown-menu-right">
					<input type="submit" id="<?php echo esc_attr( $b2_id ); ?>" class="w3tc-button-save dropdown-item" name="w3tc_default_save_and_flush" value="<?php esc_html_e( 'Save Settings & Purge Caches', 'w3-total-cache' ); ?>"/>
				</div>
				<?php
			} else {
				?>
				<input type="submit" class="w3tc-button-save btn btn-primary btn-sm" name="w3tc_save_options" value="<?php esc_html_e( 'Save Settings', 'w3-total-cache' ); ?>"/>
				<?php
			}
			?>
		</div>
		<?php
	}

	/**
	 * Prints the split button for flushing caches.
	 *
	 * @return void
	 */
	public static function print_flush_split_button() {
		$config = Dispatcher::config();

		$nonce_field = self::nonce_field( 'w3tc' );
		echo wp_kses(
			$nonce_field,
			self::get_allowed_html_for_wp_kses_from_content( $nonce_field )
		);

		?>
		<div class="btn-group w3tc-button-flush-dropdown">
			<input id="flush_all" type="submit" class="btn btn-light btn-sm" name="w3tc_flush_all" value="<?php esc_html_e( 'Empty All Caches', 'w3-total-cache' ); ?>"/>
			<button type="button" class="btn btn-light btn-sm dropdown-toggle dropdown-toggle-split" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
				<span class="sr-only">Toggle Dropdown</span>
			</button>
			<div class="dropdown-menu dropdown-menu-right">
				<?php
				$actions = apply_filters( 'w3tc_dashboard_actions', array() );
				foreach ( $actions as $action ) {
					echo wp_kses(
						$action,
						array(
							'input' => array(
								'class'    => array(),
								'disabled' => array(),
								'name'     => array(),
								'type'     => array(),
								'value'    => array(),
							),
						)
					);
				}
				if ( $config->get_boolean( 'pgcache.enabled' ) ) {
					echo '<input type="submit" class="dropdown-item" name="w3tc_flush_pgcache" value="' . esc_html__( 'Empty Page Cache', 'w3-total-cache' ) . '"/>';
				}
				if ( $config->get_boolean( 'browsercache.cssjs.replace' ) || $config->get_boolean( 'browsercache.other.replace' ) ) {
					echo '<input type="submit" class="dropdown-item" name="w3tc_flush_browser_cache" value="' . esc_html__( 'Empty Browser Cache', 'w3-total-cache' ) . '"/>';
				}
				if ( $config->get_boolean( 'minify.enabled' ) ) {
					echo '<input type="submit" class="dropdown-item" name="w3tc_flush_minify" value="' . esc_html__( 'Empty Minify Cache', 'w3-total-cache' ) . '"/>';
				}
				if ( $config->get_boolean( 'dbcache.enabled' ) ) {
					echo '<input type="submit" class="dropdown-item" name="w3tc_flush_dbcache" value="' . esc_html__( 'Empty Database Cache', 'w3-total-cache' ) . '"/>';
				}
				if ( $config->getf_boolean( 'objectcache.enabled' ) ) {
					echo '<input type="submit" class="dropdown-item" name="w3tc_flush_objectcache" value="' . esc_html__( 'Empty Object Cache', 'w3-total-cache' ) . '"/>';
				}
				if ( $config->get_boolean( 'cdn.enabled' ) ) {
					$disable = $config->get_boolean( 'cdn.enabled' ) && Cdn_Util::can_purge_all( $config->get_string( 'cdn.engine' ) ) ? '' : ' disabled="disabled" ';
					echo '<input type="submit" class="dropdown-item" name="w3tc_flush_cdn"' . $disable . ' value="' . esc_html__( 'Empty CDN Cache', 'w3-total-cache' ) . '"/>';
				}
				if ( $config->is_extension_active_frontend( 'fragmentcache' ) && Util_Environment::is_w3tc_pro( $config ) ) {
					echo '<input type="submit" class="dropdown-item" name="w3tc_flush_fragmentcache" value="' . esc_html__( 'Empty Fragment Cache', 'w3-total-cache' ) . '"/>';
				}
				if ( $config->get_boolean( 'varnish.enabled' ) ) {
					echo '<input type="submit" class="dropdown-item" name="w3tc_flush_varnish" value="' . esc_html__( 'Empty Varnish Cache', 'w3-total-cache' ) . '"/>';
				}
				if ( $config->is_extension_active_frontend( 'cloudflare' ) ) {
					echo '<input type="submit" class="dropdown-item" name="w3tc_cloudflare_flush" value="' . esc_html__( 'Empty CloudFlare Cache', 'w3-total-cache' ) . '"/>';
				}
				$opcode_enabled = ( Util_Installed::opcache() || Util_Installed::apc_opcache() );
				if ( $opcode_enabled ) {
					$disable = $opcode_enabled ? '' : ' disabled="disabled" ';
					echo '<input type="submit" class="dropdown-item" name="w3tc_opcache_flush"' . $disable . ' value="' . esc_html__( 'Empty OpCode Cache', 'w3-total-cache' ) . '"/>';
				}
				?>
			</div>
		</div>
		<?php
	}

	/**
	 * Prints the form control bar
	 *
	 * @param string $id
	 * @return void
	 */
	public static function print_control_bar( $id = '' ) {
		?>
		<div class="w3tc_form_bar">
			<?php
			$custom_areas = apply_filters( 'w3tc_settings_general_anchors', array() );
			self::print_options_menu( $custom_areas );
			self::button_config_save_dropdown( $id );
			?>
		</div>
		<?php
	}

	public static function sealing_disabled( $key ) {
		$c = Dispatcher::config();
		if ( $c->is_sealed( $key ) ) {
			echo 'disabled="disabled" ';
		}
	}

	/**
	 * Returns nonce field HTML
	 *
	 * @param string $action
	 * @param string $name
	 * @param bool   $referer
	 * @internal param bool $echo
	 * @return string
	 */
	public static function nonce_field( $action = -1, $name = '_wpnonce', $referer = true ) {
		$return = '<input type="hidden" name="' . esc_attr( $name ) . '" value="' . esc_attr( wp_create_nonce( $action ) ) . '" />';

		if ( $referer ) {
			$return .= wp_referer_field( false );
		}

		return $return;
	}

	/**
	 * Returns an notification box
	 *
	 * @param string $message
	 * @param string $id      adds an id to the notification box.
	 * @return string
	 */
	public static function get_notification_box( $message, $id = '' ) {
		$page_val = Util_Request::get_string( 'page' );

		if ( empty( $page_val ) || ( ! empty( $page_val ) && 'w3tc_' !== substr( $page_val, 0, 5 ) ) ) {
			$logo = sprintf(
				'<img src="%s" alt="W3 Total Cache" style="height:30px;padding: 10px 2px 0 2px;" />"',
				esc_url( plugins_url( '/pub/img/W3TC_dashboard_logo_title.png', W3TC_FILE ) ) . ''
			);
		} else {
			$logo = '';
		}
		return sprintf(
			'<div %s class="updated inline">%s</div>',
			$id ? 'id="' . esc_attr( $id ) . '"' : '',
			$logo . wp_kses( $message, self::get_allowed_html_for_wp_kses_from_content( $message ) )
		);
	}

	/**
	 * Echos an notification box
	 *
	 * @param string $message
	 * @param string $id      adds an id to the notification box.
	 */
	public static function e_notification_box( $message, $id = '' ) {
		$notification_box = self::get_notification_box( $message, $id );
		echo wp_kses(
			$notification_box,
			self::get_allowed_html_for_wp_kses_from_content( $notification_box )
		);
	}

	/**
	 * Echos an error box.
	 *
	 * @param string $message Message.
	 * @param string $id      Id.
	 */
	public static function error_box( $message, $id = '' ) {
		$page_val = Util_Request::get_string( 'page' );

		if ( empty( $page_val ) || ( ! empty( $page_val ) && 'w3tc_' !== substr( $page_val, 0, 5 ) ) ) {
			$logo = sprintf(
				'<img src="%s" alt="W3 Total Cache" style="height:30px;padding: 10px 2px 0 2px;" />',
				esc_url( plugins_url( '/pub/img/W3TC_dashboard_logo_title.png', W3TC_FILE ) . '' )
			);
		} else {
			$logo = '';
		}

		$v = sprintf(
			'<div %s class="error inline">%s</div>',
			$id ? 'id="' . esc_attr( $id ) . '"' : '',
			$logo . wp_kses( $message, self::get_allowed_html_for_wp_kses_from_content( $message ) )
		);

		echo wp_kses(
			$v,
			self::get_allowed_html_for_wp_kses_from_content( $v )
		);
	}

	/**
	 * Format bytes into B, KB, MB, GB and TB
	 *
	 * @param unknown $bytes
	 * @param int     $precision
	 * @return string
	 */
	public static function format_bytes( $bytes, $precision = 2 ) {
		$units = array( 'B', 'KB', 'MB', 'GB', 'TB' );

		$bytes = max( $bytes, 0 );
		$pow   = floor( ( $bytes ? log( $bytes ) : 0 ) / log( 1024 ) );
		$pow   = min( $pow, count( $units ) - 1 );

		// Uncomment one of the following alternatives.
		$bytes /= pow( 1024, $pow );
		// $bytes /= ( 1 << ( 10 * $pow ) );

		return round( $bytes, $precision ) . ' ' . $units[ $pow ];
	}

	public static function format_mbytes( $bytes, $precision = 2 ) {
		$units = array( 'B', 'KB', 'MB', 'GB', 'TB' );

		$bytes = max( $bytes, 0 );
		$pow   = floor( ( $bytes ? log( $bytes ) : 0 ) / log( 1024 ) );
		$pow   = min( $pow, count( $units ) - 1 );

		// Uncomment one of the following alternatives.
		$bytes /= pow( 1024, $pow );
		// $bytes /= ( 1 << ( 10 * $pow ) );

		return round( $bytes, $precision ) . ' ' . $units[ $pow + 2 ];
	}

	/**
	 * Returns an input text element
	 *
	 * @param string $id
	 * @param string $name
	 * @param string $value
	 * @param bool   $disabled
	 * @param int    $size
	 */
	public static function r_hidden( $id, $name, $value ) {
		return '<input type="hidden" id="' . esc_attr( $id ) .
			'" name="' . esc_attr( $name ) .
			'" value="' . esc_attr( $value ) . '" />';
	}

	/**
	 * Echos an input text element
	 *
	 * @param string $id
	 * @param string $name
	 * @param string $value
	 * @param bool   $disabled
	 * @param int    $size
	 */
	public static function hidden( $id, $name, $value ) {
		$hidden = self::r_hidden( $id, $name, $value );
		echo wp_kses(
			$hidden,
			self::get_allowed_html_for_wp_kses_from_content( $hidden )
		);
	}

	/**
	 * Echos an label element
	 *
	 * @param string $id
	 * @param string $text
	 */
	public static function label( $id, $text ) {
		$label = '<label for="' . esc_attr( $id ) . '">' . $text . '</label>';
		echo wp_kses(
			$label,
			self::get_allowed_html_for_wp_kses_from_content( $label )
		);
	}

	/**
	 * Echos an input text element
	 *
	 * @param string $id
	 * @param string $name
	 * @param string $value
	 * @param bool   $disabled
	 * @param int    $size
	 */
	public static function textbox( $id, $name, $value, $disabled = false,
			$size = 40, $type = 'text', $placeholder = '' ) {
		echo '<input class="enabled" type="' . esc_attr( $type ) . '"
			 id="' . esc_attr( $id ) . '"
			 name="' . esc_attr( $name ) . '"
			 value="' . esc_attr( $value ) . '" ';
		disabled( $disabled );
		echo ' size="' . esc_attr( $size ) . '"';

		if ( ! empty( $placeholder ) ) {
			echo ' placeholder="' . esc_attr( $placeholder ) . '"';
		}

		echo ' />';
	}

	/**
	 * Echos an input password element
	 *
	 * @param string $id
	 * @param string $name
	 * @param string $value
	 * @param bool   $disabled
	 * @param int    $size
	 */
	public static function passwordbox( $id, $name, $value, $disabled = false, $size = 40 ) {
		echo '<input class="enabled" type="password"
			 id="' . esc_attr( $id ) . '"
			 name="' . esc_attr( $name ) . '"
			 value="' . esc_attr( $value ) . '" ';
		disabled( $disabled );
		echo ' size="' . esc_attr( $size ) . '" />';
	}

	/**
	 * Echos an select element
	 *
	 * @param string $id
	 * @param string $name
	 * @param bool   $state     whether checked or not.
	 * @param bool   $disabled
	 * @param array  $optgroups
	 */
	public static function selectbox( $id, $name, $value, $values,
			$disabled = false, $optgroups = null ) {
		echo '<select id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" ';
		disabled( $disabled );
		echo ">\n";

		if ( ! is_array( $optgroups ) ) {
			// simle control.
			foreach ( $values as $key => $descriptor ) {
				self::option( $key, $value, $descriptor );
			}
		} else {
			// with optgroups.
			$current_optgroup = -1;
			foreach ( $values as $key => $descriptor ) {
				$optgroup = ( isset( $descriptor['optgroup'] ) ? $descriptor['optgroup'] : -1 );
				if ( $optgroup !== $current_optgroup ) {
					if ( -1 !== $current_optgroup ) {
						echo '</optgroup>';
					}
					echo '<optgroup label="' . esc_attr( $optgroups[ $optgroup ] ) . '">' . "\n";
					$current_optgroup = $optgroup;
				}

				self::option( $key, $value, $descriptor );
			}

			if ( -1 !== $current_optgroup ) {
				echo '</optgroup>';
			}
		}

		echo '</select>';
	}

	private static function option( $key, $selected_value, $descriptor ) {
		if ( ! is_array( $descriptor ) ) {
			$label    = $descriptor;
			$disabled = false;
		} else {
			$label    = $descriptor['label'];
			$disabled = ! empty( $descriptor['disabled'] );
		}

		echo '<option value="' . esc_attr( $key ) . '" ';
		selected( $selected_value, $key );
		disabled( $disabled );
		echo '>' . wp_kses( $label, self::get_allowed_html_for_wp_kses_from_content( $label ) ) . '</option>' . "\n";
	}

	/**
	 * Echos a group of radio elements
	 * values: value => label pair or
	 * value => array(label, disabled, postfix).
	 */
	public static function radiogroup( $name, $value, $values,
			$disabled = false, $separator = '' ) {
		$first = true;
		foreach ( $values as $key => $label_or_array ) {
			if ( $first ) {
				$first = false;
			} else {
				echo wp_kses(
					$separator,
					self::get_allowed_html_for_wp_kses_from_content( $separator )
				);
			}

			$label         = '';
			$item_disabled = false;
			$postfix       = '';
			$pro_feature   = false;

			if ( ! is_array( $label_or_array ) ) {
				$label = $label_or_array;
			} else {
				$label         = $label_or_array['label'];
				$item_disabled = $label_or_array['disabled'];
				$postfix       = isset( $label_or_array['postfix'] ) ? $label_or_array['postfix'] : '';
				$pro_feature   = isset( $label_or_array['pro_feature'] ) ? $label_or_array['pro_feature'] : false;
			}

			if ( $pro_feature ) {
				self::pro_wrap_maybe_start();
			}
			echo '<label><input type="radio"
				 id="' . esc_attr( $name . '__' . $key ) . '"
				 name="' . esc_attr( $name ) . '"
				 value="' . esc_attr( $key ) . '"';
			checked( $value, $key );
			disabled( $disabled || $item_disabled );
			echo ' />' . wp_kses( $label, self::get_allowed_html_for_wp_kses_from_content( $label ) ) . '</label>' . wp_kses( $postfix, self::get_allowed_html_for_wp_kses_from_content( $postfix ) ) . "\n";
			if ( $pro_feature ) {
				self::pro_wrap_description(
					$label_or_array['pro_excerpt'],
					$label_or_array['pro_description'],
					$name . '__' . $key
				);

				self::pro_wrap_maybe_end( $name . '__' . $key );
			}
		}
	}

	/**
	 * Echos an input text element
	 *
	 * @param string $id
	 * @param string $name
	 * @param string $value
	 * @param bool   $disabled
	 */
	public static function textarea( $id, $name, $value, $disabled = false ) {
		?>
		<textarea class="enabled" id="<?php echo esc_attr( $id ); ?>"
			name="<?php echo esc_attr( $name ); ?>" rows="5" cols=25 style="width: 100%"
			<?php disabled( $disabled ); ?>><?php echo esc_textarea( $value ); ?></textarea>
		<?php
	}

	/**
	 * Echos an input checkbox element
	 *
	 * @param string $id
	 * @param string $name
	 * @param bool   $state    whether checked or not.
	 * @param bool   $disabled
	 */
	public static function checkbox( $id, $name, $state, $disabled = false, $label = null ) {
		if ( ! is_null( $label ) ) {
			echo '<label>';
		}

		echo '<input type="hidden" name="' . esc_attr( $name ) . '"
			 value="' . esc_attr( ( ! $disabled ? '0' : ( $state ? '1' : '0' ) ) ) . '">' . "\n";
		echo '<input class="enabled" type="checkbox" id="' . esc_attr( $id ) . '"
			 name="' . esc_attr( $name ) . '" value="1" ';
		checked( $state );
		disabled( $disabled );
		echo ' /> ';

		if ( ! is_null( $label ) ) {
			echo wp_kses( $label, self::get_allowed_html_for_wp_kses_from_content( $label ) ) . '</label>';
		}
	}

	/**
	 * Echos an element
	 *
	 * @param string $type
	 * @param string $id
	 * @param string $name
	 * @param mixed  $value
	 * @param bool   $disabled
	 */
	public static function element( $type, $id, $name, $value, $disabled = false ) {
		switch ( $type ) {
			case 'textbox':
				self::textbox( $id, $name, $value, $disabled );
				break;
			case 'password':
				self::passwordbox( $id, $name, $value, $disabled );
				break;
			case 'textarea':
				self::textarea( $id, $name, $value, $disabled );
				break;
			case 'checkbox':
			default:
				self::checkbox( $id, $name, $value, $disabled );
				break;
		}
	}

	public static function checkbox2( $e ) {
		self::checkbox(
			$e['name'],
			$e['name'],
			$e['value'],
			( isset( $e['disabled'] ) ? $e['disabled'] : false ),
			( isset( $e['label'] ) ? $e['label'] : null )
		);
	}

	public static function radiogroup2( $e ) {
		self::radiogroup(
			$e['name'],
			$e['value'],
			$e['values'],
			$e['disabled'],
			$e['separator']
		);
	}

	public static function selectbox2( $e ) {
		self::selectbox(
			$e['name'],
			$e['name'],
			$e['value'],
			$e['values'],
			( isset( $e['disabled'] ) ? $e['disabled'] : false ),
			( isset( $e['optgroups'] ) ? $e['optgroups'] : null )
		);
	}

	public static function textbox2( $e ) {
		self::textbox(
			$e['name'],
			$e['name'],
			$e['value'],
			( isset( $e['disabled'] ) ? $e['disabled'] : false ),
			( ! empty( $e['size'] ) ? $e['size'] : 20 ),
			( ! empty( $e['type'] ) ? $e['type'] : 'text' ),
			( ! empty( $e['placeholder'] ) ? $e['placeholder'] : '' )
		);
	}

	public static function textarea2( $e ) {
		self::textarea(
			$e['name'],
			$e['name'],
			$e['value'],
			( isset( $e['disabled'] ) ? $e['disabled'] : false )
		);
	}

	public static function control2( $a ) {
		if ( 'checkbox' === $a['control'] ) {
			self::checkbox2(
				array(
					'name'     => $a['control_name'],
					'value'    => $a['value'],
					'disabled' => $a['disabled'],
					'label'    => $a['checkbox_label'],
				)
			);
		} elseif ( 'radiogroup' === $a['control'] ) {
			self::radiogroup2(
				array(
					'name'      => $a['control_name'],
					'value'     => $a['value'],
					'disabled'  => $a['disabled'],
					'values'    => $a['radiogroup_values'],
					'separator' => isset( $a['radiogroup_separator'] ) ? $a['radiogroup_separator'] : '',
				)
			);
		} elseif ( 'selectbox' === $a['control'] ) {
			self::selectbox2(
				array(
					'name'      => $a['control_name'],
					'value'     => $a['value'],
					'disabled'  => $a['disabled'],
					'values'    => $a['selectbox_values'],
					'optgroups' => isset( $a['selectbox_optgroups'] ) ? $a['selectbox_optgroups'] : null,
				)
			);
		} elseif ( 'textbox' === $a['control'] ) {
			self::textbox2(
				array(
					'name'        => $a['control_name'],
					'value'       => $a['value'],
					'disabled'    => $a['disabled'],
					'type'        => isset( $a['textbox_type'] ) ? $a['textbox_type'] : null,
					'size'        => isset( $a['textbox_size'] ) ? $a['textbox_size'] : null,
					'placeholder' => isset( $a['textbox_placeholder'] ) ? $a['textbox_placeholder'] : null,
				)
			);
		} elseif ( 'textarea' === $a['control'] ) {
			self::textarea2(
				array(
					'name'     => $a['control_name'],
					'value'    => $a['value'],
					'disabled' => $a['disabled'],
				)
			);
		} elseif ( 'none' === $a['control'] ) {
			echo wp_kses( $a['none_label'], self::get_allowed_html_for_wp_kses_from_content( $a['none_label'] ) );
		} elseif ( 'button' === $a['control'] ) {
			echo '<button type="button" class="button">' . wp_kses( $a['none_label'], self::get_allowed_html_for_wp_kses_from_content( $a['none_label'] ) ) . '</button>';
		}
	}

	/**
	 * Get table classes for tables including pro features.
	 *
	 * When on the free version, tables with pro features have additional classes added to help highlight
	 * the premium feature. If the user is on pro, this class is omitted.
	 *
	 * @since 0.14.3
	 *
	 * @return string
	 */
	public static function table_class() {
		$table_class[] = 'form-table';

		if ( ! Util_Environment::is_w3tc_pro( Dispatcher::config() ) ) {
			$table_class[] = 'w3tc-pro-feature';
		}

		return implode( ' ', $table_class );
	}

	/**
	 * Renders <tr> element with controls
	 * id =>
	 * label =>
	 * label_class =>
	 * <control> => details
	 * style - default is label,controls view,
	 *         alternative is one-column view
	 */
	public static function table_tr( $a ) {
		$id = isset( $a['id'] ) ? $a['id'] : '';
		$a  = apply_filters( 'w3tc_ui_settings_item', $a );

		echo '<tr><th';

		if ( isset( $a['label_class'] ) ) {
			echo ' class="' . esc_attr( $a['label_class'] ) . '"';
		}
		echo '>';
		if ( isset( $a['label'] ) ) {
			self::label( $id, $a['label'] );
		}

		echo "</th>\n<td>\n";

		foreach ( $a as $key => $e ) {
			if ( 'checkbox' === $key ) {
				self::checkbox(
					$id,
					isset( $e['name'] ) ? $e['name'] : null,
					$e['value'],
					( isset( $e['disabled'] ) ? $e['disabled'] : false ),
					( isset( $e['label'] ) ? $e['label'] : null )
				);
			} elseif ( 'description' === $key ) {
				echo '<p class="description">' . wp_kses( $e, self::get_allowed_html_for_wp_kses_from_content( $e ) ) . '</p>';
			} elseif ( 'hidden' === $key ) {
				self::hidden( '', $e['name'], $e['value'] );
			} elseif ( 'html' === $key ) {
				echo wp_kses( $e, self::get_allowed_html_for_wp_kses_from_content( $e ) );
			} elseif ( 'radiogroup' === $key ) {
				self::radiogroup(
					$e['name'],
					$e['value'],
					$e['values'],
					$e['disabled'],
					$e['separator']
				);
			} elseif ( 'selectbox' === $key ) {
				self::selectbox(
					$id,
					$e['name'],
					$e['value'],
					$e['values'],
					( isset( $e['disabled'] ) ? $e['disabled'] : false ),
					( isset( $e['optgroups'] ) ? $e['optgroups'] : null )
				);
			} elseif ( 'textbox' === $key ) {
				self::textbox(
					$id,
					$e['name'],
					$e['value'],
					( isset( $e['disabled'] ) ? $e['disabled'] : false ),
					( ! empty( $e['size'] ) ? $e['size'] : 20 ),
					( ! empty( $e['type'] ) ? $e['type'] : 'text' ),
					( ! empty( $e['placeholder'] ) ? $e['placeholder'] : '' )
				);
			} elseif ( 'textarea' === $key ) {
				self::textarea(
					$id,
					$e['name'],
					$e['value'],
					( isset( $e['disabled'] ) ? $e['disabled'] : false )
				);
			}
		}

		echo "</td></tr>\n";
	}

	/**
	 * Prints configuration item UI based on description
	 *   key => configuration key
	 *   label => configuration key's as its introduced to the user
	 *   value => it's value
	 *   disabled => if disabled
	 *
	 *   control => checkbox | radiogroup | selectbox | textbox
	 *   checkbox_label => text shown after the textbox
	 *   radiogroup_values => array of possible values for radiogroup
	 *   selectbox_values => array of possible values for dropdown
	 *   selectbox_optgroups =>
	 *   textbox_size =>
	 *
	 *   control_after => something after control to add
	 *   description => description shown to the user below
	 */
	public static function config_item( $a ) {
		/*
		 * Some items we do not want shown in the free edition.
		 *
		 * By default, they will show in free, unless 'show_in_free' is specifically passed in as false.
		 */
		$is_w3tc_free = ! Util_Environment::is_w3tc_pro( Dispatcher::config() );
		$show_in_free = ! isset( $a['show_in_free'] ) || (bool) $a['show_in_free'];
		if ( ! $show_in_free && $is_w3tc_free ) {
			return;
		}

		$a = self::config_item_preprocess( $a );

		if ( 'w3tc_single_column' === $a['label_class'] ) {
			echo '<tr><th colspan="2">';
		} else {
			echo '<tr><th class="' . esc_attr( $a['label_class'] ) . '">';

			if ( ! empty( $a['label'] ) ) {
				self::label( $a['control_name'], $a['label'] );
			}

			echo "</th>\n<td>\n";
		}

		self::control2( $a );

		if ( isset( $a['control_after'] ) ) {
			echo wp_kses(
				$a['control_after'],
				self::get_allowed_html_for_wp_kses_from_content( $a['control_after'] )
			);
		}
		if ( isset( $a['description'] ) ) {
			echo wp_kses(
				sprintf(
					'%1$s%2$s%3$s',
					'<p class="description">',
					$a['description'],
					'</p>'
				),
				array(
					'p'       => array(
						'class' => array(),
					),
					'acronym' => array(
						'title' => array(),
					),
				)
			);
		}

		echo ( isset( $a['style'] ) ? '</th>' : '</td>' );
		echo "</tr>\n";
	}

	public static function config_item_extension_enabled( $a ) {
		if ( 'w3tc_single_column' === $a['label_class'] ) {
			echo '<tr><th colspan="2">';
		} else {
			echo '<tr><th class="' . esc_attr( $a['label_class'] ) . '">';

			if ( ! empty( $a['label'] ) ) {
				self::label( $a['control_name'], $a['label'] );
			}

			echo "</th>\n<td>\n";
		}

		$c = Dispatcher::config();
		self::checkbox2(
			array(
				'name'  => 'extension__' . self::config_key_to_http_name( $a['extension_id'] ),
				'value' => $c->is_extension_active_frontend( $a['extension_id'] ),
				'label' => $a['checkbox_label'],
			)
		);

		if ( isset( $a['description'] ) ) {
			echo '<p class="description">' . wp_kses( $a['description'], self::get_allowed_html_for_wp_kses_from_content( $a['description'] ) ) . '</p>';
		}

		echo ( isset( $a['style'] ) ? '</th>' : '</td>' );
		echo "</tr>\n";
	}

	public static function config_item_pro( $a ) {
		$a = self::config_item_preprocess( $a );

		if ( 'w3tc_single_column' === $a['label_class'] ) {
			echo '<tr><th colspan="2">';
		} elseif ( 'w3tc_no_trtd' !== $a['label_class'] ) {
			echo '<tr><th class="' . esc_attr( $a['label_class'] ) . '">';

			if ( ! empty( $a['label'] ) ) {
				self::label( $a['control_name'], $a['label'] );
			}

			echo "</th>\n<td>\n";
		}

		self::pro_wrap_maybe_start();

		self::control2( $a );

		if ( isset( $a['control_after'] ) ) {
			echo wp_kses( $a['control_after'], self::get_allowed_html_for_wp_kses_from_content( $a['control_after'] ) );
		}

		if ( isset( $a['description'] ) ) {
			self::pro_wrap_description( $a['excerpt'], $a['description'], $a['control_name'] );
		}

		self::pro_wrap_maybe_end( $a['control_name'] );

		if ( 'w3tc_no_trtd' !== $a['label_class'] ) {
			echo ( isset( $a['style'] ) ? '</th>' : '</td>' );
			echo "</tr>\n";
		}
	}

	public static function config_item_preprocess( $a ) {
		$c = Dispatcher::config();

		if ( ! isset( $a['value'] ) || is_null( $a['value'] ) ) {
			$a['value'] = $c->get( $a['key'] );
			if ( is_array( $a['value'] ) ) {
				$a['value'] = implode( "\n", $a['value'] );
			}
		}

		if ( ! isset( $a['disabled'] ) || is_null( $a['disabled'] ) ) {
			$a['disabled'] = $c->is_sealed( $a['key'] );
		}

		if ( empty( $a['label'] ) ) {
			$a['label'] = self::config_label( $a['key'] );
		}

		$a['control_name'] = self::config_key_to_http_name( $a['key'] );
		$a['label_class']  = empty( $a['label_class'] ) ? '' : $a['label_class'];
		if ( empty( $a['label_class'] ) && 'checkbox' === $a['control'] ) {
			$a['label_class'] = 'w3tc_config_checkbox';
		}

		$action_key = $a['key'];
		if ( is_array( $action_key ) ) {
			$action_key = 'extension.' . $action_key[0] . '.' . $action_key[1];
		}

		return apply_filters( 'w3tc_ui_config_item_' . $action_key, $a );
	}

	/**
	 * Displays config item - caching engine selectbox
	 */
	public static function config_item_engine( $a ) {
		if ( isset( $a['empty_value'] ) && $a['empty_value'] ) {
			$values[''] = array(
				'label' => 'Please select a method',
			);
		}

		$values['file']         = array(
			'label'    => __( 'Disk', 'w3-total-cache' ),
			'optgroup' => 0,
		);
		$values['apc']          = array(
			'disabled' => ! Util_Installed::apc(),
			'label'    => __( 'Opcode: Alternative PHP Cache (APC / APCu)', 'w3-total-cache' ),
			'optgroup' => 1,
		);
		$values['eaccelerator'] = array(
			'disabled' => ! Util_Installed::eaccelerator(),
			'label'    => __( 'Opcode: eAccelerator', 'w3-total-cache' ),
			'optgroup' => 1,
		);
		$values['xcache']       = array(
			'disabled' => ! Util_Installed::xcache(),
			'label'    => __( 'Opcode: XCache', 'w3-total-cache' ),
			'optgroup' => 1,
		);
		$values['wincache']     = array(
			'disabled' => ! Util_Installed::wincache(),
			'label'    => __( 'Opcode: WinCache', 'w3-total-cache' ),
			'optgroup' => 1,
		);
		$values['memcached']    = array(
			'disabled' => ! Util_Installed::memcached(),
			'label'    => __( 'Memcached', 'w3-total-cache' ),
			'optgroup' => 2,
		);
		$values['redis']        = array(
			'disabled' => ! Util_Installed::redis(),
			'label'    => __( 'Redis', 'w3-total-cache' ),
			'optgroup' => 2,
		);

		$item_engine_config = array(
			'key'                 => $a['key'],
			'label'               => ( isset( $a['label'] ) ? $a['label'] : null ),
			'disabled'            => ( isset( $a['disabled'] ) ? $a['disabled'] : null ),
			'control'             => 'selectbox',
			'selectbox_values'    => $values,
			'selectbox_optgroups' => array(
				__( 'Shared Server:', 'w3-total-cache' ),
				__( 'Dedicated / Virtual Server:', 'w3-total-cache' ),
				__( 'Multiple Servers:', 'w3-total-cache' ),
			),
			'control_after'       => isset( $a['control_after'] ) ? $a['control_after'] : null,
		);

		if ( isset( $a['pro'] ) ) {
			self::config_item_pro( $item_engine_config );
		} else {
			self::config_item( $item_engine_config );
		}
	}

	public static function pro_wrap_maybe_start() {
		if ( Util_Environment::is_w3tc_pro( Dispatcher::config() ) ) {
			return;
		}

		?>
		<div class="w3tc-gopro">
			<div class="w3tc-gopro-ribbon"><span>&bigstar; PRO</span></div>
			<div class="w3tc-gopro-content">
		<?php
	}

	public static function pro_wrap_description( $excerpt_clean, $description, $data_href ) {
		echo '<p class="description w3tc-gopro-excerpt">' . wp_kses( $excerpt_clean, self::get_allowed_html_for_wp_kses_from_content( $excerpt_clean ) ) . '</p>';

		if ( ! empty( $description ) ) {
			$d = array_map(
				function( $e ) {
					return '<p class="description">' . wp_kses( $e, self::get_allowed_html_for_wp_kses_from_content( $e ) ) . '</p>';
				},
				$description
			);

			$descriptions = implode( "\n", $d );

			echo '<div class="w3tc-gopro-description">' . wp_kses( $descriptions, self::get_allowed_html_for_wp_kses_from_content( $descriptions ) ) . '</div>';
			echo '<a href="#" class="w3tc-gopro-more" data-href="w3tc-gopro-more-' . esc_url( $data_href ) . '">' . esc_html( __( 'Show More', 'w3-total-cache' ) ) . '<span class="dashicons dashicons-arrow-down-alt2"></span></a>';
		}
	}

	public static function pro_wrap_maybe_end( $button_data_src ) {
		if ( Util_Environment::is_w3tc_pro( Dispatcher::config() ) ) {
			return;
		}

		?>
			</div>
			<div class="w3tc-gopro-action">
				<button class="button w3tc-gopro-button button-buy-plugin" data-src="<?php echo esc_attr( $button_data_src ); ?>">
					Learn more about Pro
				</button>
			</div>
		</div>
		<?php
	}

	public static function pro_wrap_maybe_start2() {
		if ( Util_Environment::is_w3tc_pro( Dispatcher::config() ) ) {
			return;
		}

		?>
		<div class="updated w3tc_note" id="licensing_terms" style="display: flex; align-items: center">
			<p style="flex-grow: 1">
		<?php
	}

	public static function pro_wrap_maybe_end2( $button_data_src ) {
		if ( Util_Environment::is_w3tc_pro( Dispatcher::config() ) ) {
			return;
		}

		?>
			</p>
			<div style="text-align: right">
				<button class="button w3tc-gopro-button button-buy-plugin" data-src="<?php echo esc_attr( $button_data_src ); ?>">
					Unlock Feature
				</button>
			</div>
		</div>
		<?php
	}



	/**
	 * On subblogs - shows button to enable/disable custom configuration
	 *   $a['key'] - config key *_overloaded which are managed
	 */
	public static function config_overloading_button( $a ) {
		$c = Dispatcher::config();
		if ( $c->is_master() ) {
			return;
		}

		if ( $c->get_boolean( $a['key'] ) ) {
			$name  = 'w3tc_config_overloaded_disable~' . self::config_key_to_http_name( $a['key'] );
			$value = __( 'Use common settings', 'w3-total-cache' );
		} else {
			$name  = 'w3tc_config_overloaded_enable~' . self::config_key_to_http_name( $a['key'] );
			$value = __( 'Use specific settings', 'w3-total-cache' );
		}

		echo '<div style="float: right">';
		echo '<input type="submit" class="button"
			 name="' . esc_attr( $name ) . '"
			 value="' . esc_attr( $value ) . '" />';
		echo '</div>';
	}

	/**
	 * Get the admin URL based on the path and the interface (network or site).
	 *
	 * @param  string $path Admin path/URI.
	 * @return string
	 */
	public static function admin_url( $path ) {
		return is_network_admin() ? network_admin_url( $path ) : admin_url( $path );
	}

	/**
	 * Returns a preview link with current state
	 *
	 * @return string
	 */
	public static function preview_link() {
		return self::button_link(
			__( 'Preview', 'w3-total-cache' ),
			self::url( array( 'w3tc_default_previewing' => 'y' ) ),
			true
		);
	}

	/**
	 * Takes seconds and converts to array('Nh ','Nm ', 'Ns ', 'Nms ') or "Nh Nm Ns Nms"
	 *
	 * @param unknown $input
	 * @param bool    $string
	 * @return array|string
	 */
	public static function secs_to_time( $input, $string = true ) {
		$input   = (float) $input;
		$time    = array();
		$msecs   = floor( $input * 1000 % 1000 );
		$seconds = $input % 60;

		$minutes = floor( $input / 60 ) % 60;
		$hours   = floor( $input / 60 / 60 ) % 60;

		if ( $hours ) {
			$time[] = $hours;
		}
		if ( $minutes ) {
			$time[] = sprintf( '%dm', $minutes );
		}
		if ( $seconds ) {
			$time[] = sprintf( '%ds', $seconds );
		}
		if ( $msecs ) {
			$time[] = sprintf( '%dms', $msecs );
		}

		if ( empty( $time ) ) {
			$time[] = sprintf( '%dms', 0 );
		}
		if ( $string ) {
			return implode( ' ', $time );
		}
		return $time;
	}

	/**
	 * Returns option name accepted by W3TC as http paramter
	 * from it's id (full name from config file)
	 */
	public static function config_key_to_http_name( $id ) {
		if ( is_array( $id ) ) {
			$id = $id[0] . '___' . $id[1];
		}

		return str_replace( '.', '__', $id );
	}

	/*
	 * Converts configuration key returned in http _GET/_POST
	 * to configuration key
	 */
	public static function config_key_from_http_name( $http_key ) {
		$a = explode( '___', $http_key );
		if ( count( $a ) === 2 ) {
			$a[0] = self::config_key_from_http_name( $a[0] );
			$a[1] = self::config_key_from_http_name( $a[1] );
			return $a;
		}

		return str_replace( '__', '.', $http_key );
	}

	public static function get_allowed_html_for_wp_kses_from_content( $content ) {
		$allowed_html = array();

		if ( empty( $content ) ) {
			return $allowed_html;
		}

		$dom = new DOMDocument();
		@$dom->loadHTML( $content );
		foreach ( $dom->getElementsByTagName( '*' ) as $tag ) {
			$tagname = $tag->tagName;
			foreach ( $tag->attributes as $attribute_name => $attribute_val ) {
				$allowed_html[ $tagname ][ $attribute_name ] = array();
			}
			$allowed_html[ $tagname ] = empty( $allowed_html[ $tagname ] ) ? array() : $allowed_html[ $tagname ];
		}
		return $allowed_html;
	}

	/**
	 * Prints breadcrumb
	 *
	 * @return void
	 */
	public static function print_breadcrumb() {
		$page         = ! empty( Util_Admin::get_current_extension() ) ? Util_Admin::get_current_extension() : Util_Admin::get_current_page();
		$page_mapping = Util_PageUrls::get_page_mapping( $page );
		$parent       = isset( $page_mapping['parent_name'] ) ? '<span class="dashicons dashicons-arrow-right-alt2"></span><a href="' . esc_url( $page_mapping['parent_link'] ) . '">' . esc_html( $page_mapping['parent_name'] ) . '</a>' : '';
		$current      = '<span class="dashicons dashicons-arrow-right-alt2"></span><span>' . esc_html( $page_mapping['page_name'] ) . '</span>';
		?>
		<p id="w3tc-breadcrumb">
			<span class="dashicons dashicons-admin-home"></span>
			<a href="<?php echo esc_url( self::admin_url( 'admin.php?page=w3tc_dashboard' ) ); ?>">W3 Total Cache</a>
			<?php echo wp_kses( $parent, self::get_allowed_html_for_wp_kses_from_content( $parent ) ); ?>
			<?php echo wp_kses( $current, self::get_allowed_html_for_wp_kses_from_content( $current ) ); ?>
		</p>
		<?php
	}

	/**
	 * Prints the options anchor menu
	 *
	 * @param array $custom_areas Custom Areas.
	 * @return void
	 */
	public static function print_options_menu( $custom_areas = array() ) {
		$config            = Dispatcher::config();
		$state             = Dispatcher::config_state();
		$page              = Util_Admin::get_current_page();
		$licensing_visible = (
			( ! Util_Environment::is_wpmu() || is_network_admin() ) &&
			! ini_get( 'w3tc.license_key' ) &&
			'host_valid' !== $state->get_string( 'license.status' )
		);

		switch ( $page ) {
			case 'w3tc_general':
				if ( ! empty( $_REQUEST['view'] ) ) {
					break;
				}

				$message_bus_link = array();
				if ( Util_Environment::is_w3tc_pro( $config ) ) {
					$message_bus_link = array(
						array(
							'id'   => 'amazon_sns',
							'text' => esc_html__( 'Message Bus', 'w3-total-cache' ),
						),
					);
				}

				$licensing_link = array();
				if ( $licensing_visible ) {
					$licensing_link = array(
						array(
							'id'   => 'licensing',
							'text' => esc_html__( 'Licensing', 'w3-total-cache' ),
						),
					);
				}

				$links = array_merge(
					array(
						array(
							'id'   => 'general',
							'text' => esc_html__( 'General', 'w3-total-cache' ),
						),
						array(
							'id'   => 'page_cache',
							'text' => esc_html__( 'Page Cache', 'w3-total-cache' ),
						),
						array(
							'id'   => 'minify',
							'text' => esc_html__( 'Minify', 'w3-total-cache' ),
						),
						array(
							'id'   => 'system_opcache',
							'text' => esc_html__( 'Opcode Cache', 'w3-total-cache' ),
						),
						array(
							'id'   => 'database_cache',
							'text' => esc_html__( 'Database Cache', 'w3-total-cache' ),
						),
						array(
							'id'   => 'object_cache',
							'text' => esc_html__( 'Object Cache', 'w3-total-cache' ),
						),
						array(
							'id'   => 'browser_cache',
							'text' => esc_html__( 'Browser Cache', 'w3-total-cache' ),
						),
						array(
							'id'   => 'cdn',
							'text' => wp_kses(
								sprintf(
									// translators: 1 opening HTML abbr tag, 2 closing HTML abbr tag.
									__(
										'%1$sCDN%2$s',
										'w3-total-cache'
									),
									'<abbr title="' . esc_attr__( 'Content Delivery Network', 'w3-total-cache' ) . '">',
									'</abbr>'
								),
								array(
									'abbr' => array(
										'title' => array(),
									),
								)
							),
						),
						array(
							'id'   => 'reverse_proxy',
							'text' => esc_html__( 'Reverse Proxy', 'w3-total-cache' ),
						),
					),
					$message_bus_link,
					$custom_areas,
					$licensing_link,
					array(
						array(
							'id'   => 'miscellaneous',
							'text' => esc_html__( 'Miscellaneous', 'w3-total-cache' ),
						),
						array(
							'id'   => 'debug',
							'text' => esc_html__( 'Debug', 'w3-total-cache' ),
						),
						array(
							'id'   => 'google_pagespeed',
							'text' => __( 'Google PageSpeed', 'w3-total-cache' ),
						),
						array(
							'id'   => 'settings',
							'text' => esc_html__( 'Import / Export Settings', 'w3-total-cache' ),
						),
					)
				);

				$links_buff = array();
				foreach ( $links as $link ) {
					$links_buff[] = "<a href=\"#{$link['id']}\">{$link['text']}</a>";
				}

				?>
				<div id="w3tc-options-menu">
					<?php
					echo wp_kses(
						implode( ' | ', $links_buff ),
						array(
							'a' => array(
								'href'  => array(),
								'class' => array(),
							),
						)
					);
					?>
				</div>
				<?php
				break;

			case 'w3tc_pgcache':
				?>
				<div id="w3tc-options-menu">
					<a href="#general"><?php esc_html_e( 'General', 'w3-total-cache' ); ?></a> |
					<a href="#mirrors"><?php esc_html_e( 'Aliases', 'w3-total-cache' ); ?></a> |
					<a href="#cache_preload"><?php esc_html_e( 'Cache Preload', 'w3-total-cache' ); ?></a> |
					<a href="#purge_policy"><?php esc_html_e( 'Purge Policy', 'w3-total-cache' ); ?></a> |
					<a href="#rest"><?php esc_html_e( 'Rest API', 'w3-total-cache' ); ?></a> |
					<a href="#advanced"><?php esc_html_e( 'Advanced', 'w3-total-cache' ); ?></a> |
					<a href="#notes"><?php esc_html_e( 'Note(s)', 'w3-total-cache' ); ?></a>
				</div>
				<?php
				break;

			case 'w3tc_minify':
				?>
				<div id="w3tc-options-menu">
					<a href="#general"><?php esc_html_e( 'General', 'w3-total-cache' ); ?></a> |
					<a href="#html_xml">
						<?php
						echo wp_kses(
							sprintf(
								// translators: 1 opening HTML acronym tag, 2 closing HTML acronym tag,
								// translators: 3 opening HTML acronym tag, 4 closing HTML acronym tag.
								__(
									'%1$sHTML%2$s &amp; %3$sXML%4$s',
									'w3-total-cache'
								),
								'<acronym title="' . esc_attr__( 'Hypertext Markup Language', 'w3-total-cache' ) . '">',
								'</acronym>',
								'<acronym title="' . esc_attr__( 'eXtensible Markup Language', 'w3-total-cache' ) . '">',
								'</acronym>'
							),
							array(
								'acronym' => array(
									'title' => array(),
								),
							)
						);
						?>
					</a> |
					<a href="#js">
						<?php
						echo wp_kses(
							sprintf(
								// translators: 1 opening HTML acronym tag, 2 closing HTML acronym tag.
								__(
									'%1$sJS%2$s',
									'w3-total-cache'
								),
								'<acronym title="' . esc_attr__( 'JavaScript', 'w3-total-cache' ) . '">',
								'</acronym>'
							),
							array(
								'acronym' => array(
									'title' => array(),
								),
							)
						);
						?>
					</a> |
					<a href="#css">
						<?php
						echo wp_kses(
							sprintf(
								// translators: 1 opening HTML acronym tag, 2 closing HTML acronym tag.
								__(
									'%1$sCSS%2$s',
									'w3-total-cache'
								),
								'<acronym title="' . esc_attr__( 'Cascading Style Sheet', 'w3-total-cache' ) . '">',
								'</acronym>'
							),
							array(
								'acronym' => array(
									'title' => array(),
								),
							)
						);
						?>
					</a> |
					<a href="#advanced"><?php esc_html_e( 'Advanced', 'w3-total-cache' ); ?></a> |
					<a href="#notes"><?php esc_html_e( 'Note(s)', 'w3-total-cache' ); ?></a>
				</div>
				<?php
				break;

			case 'w3tc_dbcache':
				?>
				<div id="w3tc-options-menu">
					<a href="#general"><?php esc_html_e( 'General', 'w3-total-cache' ); ?></a> |
					<a href="#advanced"><?php esc_html_e( 'Advanced', 'w3-total-cache' ); ?></a>
				</div>
				<?php
				break;

			case 'w3tc_objectcache':
				?>
				<div id="w3tc-options-menu">
					<!--<a href="#advanced"><?php esc_html_e( 'Advanced', 'w3-total-cache' ); ?></a>-->
				</div>
				<?php
				break;

			case 'w3tc_browsercache':
				?>
				<div id="w3tc-options-menu">
					<a href="#general"><?php esc_html_e( 'General', 'w3-total-cache' ); ?></a> |
					<a href="#css_js">
						<?php
						echo wp_kses(
							sprintf(
								// translators: 1 opening HTML acronym tag, 2 closing HTML acronym tag,
								// translators: 3 opening HTML acronym tag, 4 closing HTML acronym tag.
								__(
									'%1$sCSS%2$s &amp; %3$sJS%4$s',
									'w3-total-cache'
								),
								'<acronym title="' . esc_attr__( 'Cascading Style Sheet', 'w3-total-cache' ) . '">',
								'</acronym>',
								'<acronym title="' . esc_attr__( 'JavaScript', 'w3-total-cache' ) . '">',
								'</acronym>'
							),
							array(
								'acronym' => array(
									'title' => array(),
								),
							)
						);
						?>
					</a> |
					<a href="#html_xml">
						<?php
						echo wp_kses(
							sprintf(
								// translators: 1 opening HTML acronym tag, 2 closing HTML acronym tag,
								// translators: 3 opening HTML acronym tag, 4 closing HTML acronym tag.
								__(
									'%1$sHTML%2$s &amp; %3$sXML%4$s',
									'w3-total-cache'
								),
								'<acronym title="' . esc_attr__( 'Hypertext Markup Language', 'w3-total-cache' ) . '">',
								'</acronym>',
								'<acronym title="' . esc_attr__( 'eXtensible Markup Language', 'w3-total-cache' ) . '">',
								'</acronym>'
							),
							array(
								'acronym' => array(
									'title' => array(),
								),
							)
						);
						?>
					</a> |
					<a href="#media"><?php esc_html_e( 'Media', 'w3-total-cache' ); ?></a> |
					<a href="#security"><?php esc_html_e( 'Security Headers', 'w3-total-cache' ); ?></a>
				</div>
				<?php
				break;

			case 'w3tc_cachegroups':
				?>
				<div id="w3tc-options-menu">
					<a href="#manage-uag"><?php esc_html_e( 'Manage User Agent Groups', 'w3-total-cache' ); ?></a> |
					<a href="#manage-rg"><?php esc_html_e( 'Manage Referrer Groups', 'w3-total-cache' ); ?></a> |
					<a href="#manage-cg"><?php esc_html_e( 'Manage Cookie Groups', 'w3-total-cache' ); ?></a>
				</div>
				<?php
				break;

			case 'w3tc_cdn':
				?>
				<div id="w3tc-options-menu">
					<a href="#general"><?php esc_html_e( 'General', 'w3-total-cache' ); ?></a> |
					<a href="#configuration"><?php esc_html_e( 'Configuration', 'w3-total-cache' ); ?></a> |
					<a href="#advanced"><?php esc_html_e( 'Advanced', 'w3-total-cache' ); ?></a> |
					<a href="#notes"><?php esc_html_e( 'Note(s)', 'w3-total-cache' ); ?></a>
				</div>
				<?php
				break;

			case 'w3tc_userexperience':
				?>
				<div id="w3tc-options-menu">
					<!--<a href="#lazy-loading"><?php esc_html_e( 'Lazy Loading', 'w3-total-cache' ); ?></a>-->
				</div>
				<?php
				break;

			case 'w3tc_install':
				?>
				<div id="w3tc-options-menu">
					<a href="#initial"><?php esc_html_e( 'Initial Installation', 'w3-total-cache' ); ?></a> |
					<?php if ( count( $rewrite_rules_descriptors ) ) : ?>
						<a href="#rules"><?php esc_html_e( 'Rewrite Rules', 'w3-total-cache' ); ?></a> |
					<?php endif ?>
					<?php if ( count( $other_areas ) ) : ?>
						<a href="#other"><?php esc_html_e( 'Other', 'w3-total-cache' ); ?></a> |
					<?php endif ?>
					<a href="#additional"><?php esc_html_e( 'Services', 'w3-total-cache' ); ?></a> |
					<a href="#modules">
						<?php
						echo wp_kses(
							sprintf(
								// translators: 1 opening HTML acronym tag, 2 closing HTML acronym tag.
								__(
									'%1$sPHP%2$s Modules',
									'w3-total-cache'
								),
								'<acronym title="' . esc_attr__( 'Hypertext Preprocessor', 'w3-total-cache' ) . '">',
								'</acronym>'
							),
							array(
								'acronym' => array(
									'title' => array(),
								),
							)
						);
						?>
					</a>
				</div>
				<?php
				break;

			case 'w3tc_fragmentcache':
				?>
				<div id="w3tc-options-menu">
					<a href="#overview"><?php esc_html_e( 'Overview', 'w3-total-cache' ); ?></a> |
					<a href="#advanced"><?php esc_html_e( 'Advanced', 'w3-total-cache' ); ?></a>
				</div>
				<?php
				break;

			case 'w3tc_monitoring':
				?>
				<div id="w3tc-options-menu">
					<a href="#application"><?php esc_html_e( 'Application', 'w3-total-cache' ); ?></a> |
					<a href="#dashboard"><?php esc_html_e( 'Dashboard', 'w3-total-cache' ); ?></a> |
					<a href="#behavior"><?php esc_html_e( 'Behavior', 'w3-total-cache' ); ?></a>
				</div>
				<?php
				break;

			case 'w3tc_extension_page_imageservice':
				?>
				<div id="w3tc-options-menu">
					<a href="#configuration"><?php esc_html_e( 'Configuration', 'w3-total-cache' ); ?></a> |
					<a href="#tools"><?php esc_html_e( 'Tools', 'w3-total-cache' ); ?></a> |
					<a href="#statistics"><?php esc_html_e( 'Statistics', 'w3-total-cache' ); ?></a>
				</div>
				<?php
				break;

			case 'w3tc_extensions':
				$extension = Util_Admin::get_current_extension();
				switch ( $extension ) {
					case 'cloudflare':
						?>
						<div id="w3tc-options-menu">
							<a href="#credentials"><?php esc_html_e( 'Credentials', 'w3-total-cache' ); ?></a> |
							<a href="#general"><?php esc_html_e( 'General', 'w3-total-cache' ); ?></a>
						</div>
						<?php
						break;

					case 'amp':
						?>
						<div id="w3tc-options-menu">
							<!--<a href="#configuration"><?php esc_html_e( 'Configuration', 'w3-total-cache' ); ?></a>-->
						</div>
						<?php
						break;

					case 'swarmify':
						?>
						<div id="w3tc-options-menu">
							<a href="#configuration"><?php esc_html_e( 'Configuration', 'w3-total-cache' ); ?></a> |
							<a href="#behavior"><?php esc_html_e( 'Behavior Settings', 'w3-total-cache' ); ?></a>
						</div>
						<?php
						break;

					case 'genesis':
						?>
						<div id="w3tc-options-menu">
							<a href="#header"><?php esc_html_e( 'Header', 'w3-total-cache' ); ?></a> |
							<a href="#content"><?php esc_html_e( 'Content', 'w3-total-cache' ); ?></a> |
							<a href="#sidebar"><?php esc_html_e( 'Sidebar', 'w3-total-cache' ); ?></a> |
							<a href="#exclusions"><?php esc_html_e( 'Exclusions', 'w3-total-cache' ); ?></a>
						</div>
						<?php
						break;
				}
			default:
				?>
				<div id="w3tc-options-menu"></div>
				<?php
				break;
		}
	}
}
